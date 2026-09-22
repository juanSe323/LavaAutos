<?php
require_once __DIR__ . '/../config/headers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

$payload = verificarToken();

try {
    $database = new Database();
    $db = $database->getConnection();

    $metodo = $_SERVER['REQUEST_METHOD'];

    if ($metodo === 'POST') {

        $data = json_decode(file_get_contents("php://input"), true);

        $tipoVehiculo = $data['tipo_vehiculo'] ?? null;
        $tiposServicioIds = $data['tipos_servicio_ids'] ?? [];
        $placa = trim($data['placa'] ?? '');

        $vehiculosValidos = ['CARRO', 'MOTO', 'CAMIONETA'];
        if (!in_array($tipoVehiculo, $vehiculosValidos)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Tipo de vehículo inválido"]);
            exit();
        }
        if (empty($tiposServicioIds)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Debes seleccionar al menos un tipo de servicio"]);
            exit();
        }

        // El servicio siempre queda ligado al empleado que inició sesión (nunca se recibe por body)
        $stmtEmp = $db->prepare("SELECT id FROM empleados WHERE usuario_id = :usuario_id");
        $stmtEmp->execute([':usuario_id' => $payload['id']]);
        $empleado = $stmtEmp->fetch();
        if (!$empleado) {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "Este usuario no tiene un registro de empleado asociado"]);
            exit();
        }

        $db->beginTransaction();

        // La placa es opcional en el mockup, pero vehiculos.placa es NOT NULL/unique en la BD.
        // Si no la dan, generamos una temporal identificable para no romper esa restricción.
        if ($placa === '') {
            $placa = 'SP-' . strtoupper(substr(uniqid(), -6));
        }

        $stmtVeh = $db->prepare("SELECT id, cliente_id FROM vehiculos WHERE placa = :placa");
        $stmtVeh->execute([':placa' => $placa]);
        $vehiculo = $stmtVeh->fetch();

        if ($vehiculo) {
            $vehiculoId = $vehiculo['id'];
            $clienteId = $vehiculo['cliente_id']; // si el vehículo ya está registrado a un cliente, se hereda aquí
        } else {
            $stmtInsVeh = $db->prepare("INSERT INTO vehiculos (placa, tipo) VALUES (:placa, :tipo)");
            $stmtInsVeh->execute([':placa' => $placa, ':tipo' => $tipoVehiculo]);
            $vehiculoId = $db->lastInsertId();
            $clienteId = null;
        }

        // Si el vehículo pertenece a un cliente con convenio activo, se aplica su descuento automáticamente.
        // Esto es lo que reconoce una placa de convenio sin que el empleado tenga que hacer nada extra.
        $descuentoPorcentaje = 0;
        if ($clienteId) {
            $stmtDescuento = $db->prepare("
                SELECT co.descuentoPorcentaje
                FROM clientes cl
                JOIN convenios co ON co.id = cl.convenio_id
                WHERE cl.id = :cliente_id
            ");
            $stmtDescuento->execute([':cliente_id' => $clienteId]);
            $convenio = $stmtDescuento->fetch();
            if ($convenio) {
                $descuentoPorcentaje = $convenio['descuentoPorcentaje'];
            }
        }

        // Los precios se calculan del lado del servidor según la combinación servicio+vehículo
        $placeholders = implode(',', array_fill(0, count($tiposServicioIds), '?'));
        $stmtTarifas = $db->prepare("SELECT tipo_servicio_id, precio FROM tarifas WHERE tipo_vehiculo = ? AND tipo_servicio_id IN ($placeholders)");
        $stmtTarifas->execute(array_merge([$tipoVehiculo], $tiposServicioIds));
        $tarifasEncontradas = $stmtTarifas->fetchAll();

        if (count($tarifasEncontradas) === 0) {
            $db->rollBack();
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Ninguno de los servicios seleccionados está disponible para este tipo de vehículo"]);
            exit();
        }

        $subtotal = 0;
        foreach ($tarifasEncontradas as $t) {
            $subtotal += $t['precio'];
        }
        $total = round($subtotal * (1 - ($descuentoPorcentaje / 100)), 2);

        $stmtServicio = $db->prepare("INSERT INTO servicios (estado, total, descuento_porcentaje, empleado_id, cliente_id, vehiculo_id) VALUES ('PENDIENTE', :total, :descuento, :empleado_id, :cliente_id, :vehiculo_id)");
        $stmtServicio->execute([
            ':total' => $total,
            ':descuento' => $descuentoPorcentaje,
            ':empleado_id' => $empleado['id'],
            ':cliente_id' => $clienteId,
            ':vehiculo_id' => $vehiculoId
        ]);
        $servicioId = $db->lastInsertId();

        $stmtDetalle = $db->prepare("INSERT INTO detalle_servicios (servicio_id, tipo_servicio_id, cantidad, subtotal) VALUES (:servicio_id, :tipo_servicio_id, 1, :subtotal)");
        foreach ($tarifasEncontradas as $t) {
            $stmtDetalle->execute([
                ':servicio_id' => $servicioId,
                ':tipo_servicio_id' => $t['tipo_servicio_id'],
                ':subtotal' => $t['precio']
            ]);
        }

        $db->commit();

        echo json_encode([
            "status" => "success",
            "message" => "Servicio registrado correctamente",
            "servicio" => [
                "id" => $servicioId,
                "placa" => $placa,
                "subtotal" => $subtotal,
                "descuento_porcentaje" => $descuentoPorcentaje,
                "total" => $total,
                "estado" => "PENDIENTE"
            ]
        ]);

    } elseif ($metodo === 'PUT') {

        $data = json_decode(file_get_contents("php://input"), true);
        $id = $data['id'] ?? null;
        $estado = $data['estado'] ?? null;

        $estadosValidos = ['PENDIENTE', 'EN_PROCESO', 'LISTO', 'ENTREGADO'];
        if (!$id || !in_array($estado, $estadosValidos)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "id y estado (válido) son requeridos"]);
            exit();
        }

        // Un EMPLEADO solo puede tocar servicios que él mismo registró; ADMINISTRADOR puede tocar cualquiera
        if ($payload['rol'] !== 'ADMINISTRADOR') {
            $stmtEmp = $db->prepare("SELECT id FROM empleados WHERE usuario_id = :usuario_id");
            $stmtEmp->execute([':usuario_id' => $payload['id']]);
            $empleado = $stmtEmp->fetch();

            $stmtCheck = $db->prepare("SELECT id FROM servicios WHERE id = :id AND empleado_id = :empleado_id");
            $stmtCheck->execute([':id' => $id, ':empleado_id' => $empleado['id'] ?? 0]);
            if (!$stmtCheck->fetch()) {
                http_response_code(403);
                echo json_encode(["status" => "error", "message" => "No puedes modificar un servicio que no registraste"]);
                exit();
            }
        }

        $stmt = $db->prepare("UPDATE servicios SET estado = :estado, fecha_finalizacion = :fecha_finalizacion WHERE id = :id");
        $stmt->execute([
            ':estado' => $estado,
            ':fecha_finalizacion' => $estado === 'ENTREGADO' ? date('Y-m-d H:i:s') : null,
            ':id' => $id
        ]);

        echo json_encode(["status" => "success", "message" => "Estado actualizado"]);

    } elseif ($metodo === 'GET') {

        // Historial: el administrador ve todo, el empleado solo ve lo que él mismo registró
        if ($payload['rol'] === 'ADMINISTRADOR') {
            $stmt = $db->query("
                SELECT s.id, s.fecha, s.fecha_finalizacion, s.estado, s.total, s.descuento_porcentaje,
                       v.placa, v.tipo AS tipo_vehiculo, u.nombre AS empleado_nombre, cl.nombre AS cliente_nombre,
                       TIMESTAMPDIFF(MINUTE, s.fecha, s.fecha_finalizacion) AS duracion_minutos
                FROM servicios s
                JOIN vehiculos v ON v.id = s.vehiculo_id
                JOIN empleados e ON e.id = s.empleado_id
                JOIN usuarios u ON u.id = e.usuario_id
                LEFT JOIN clientes cl ON cl.id = s.cliente_id
                ORDER BY s.fecha DESC
                LIMIT 50
            ");
            $servicios = $stmt->fetchAll();
        } else {
            $stmtEmp = $db->prepare("SELECT id FROM empleados WHERE usuario_id = :usuario_id");
            $stmtEmp->execute([':usuario_id' => $payload['id']]);
            $empleado = $stmtEmp->fetch();

            $stmt = $db->prepare("
                SELECT s.id, s.fecha, s.fecha_finalizacion, s.estado, s.total, s.descuento_porcentaje,
                       v.placa, v.tipo AS tipo_vehiculo,
                       TIMESTAMPDIFF(MINUTE, s.fecha, s.fecha_finalizacion) AS duracion_minutos
                FROM servicios s
                JOIN vehiculos v ON v.id = s.vehiculo_id
                WHERE s.empleado_id = :empleado_id
                ORDER BY s.fecha DESC
                LIMIT 50
            ");
            $stmt->execute([':empleado_id' => $empleado['id'] ?? 0]);
            $servicios = $stmt->fetchAll();
        }

        echo json_encode(["status" => "success", "data" => $servicios]);

    } else {
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Método no permitido"]);
    }

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>