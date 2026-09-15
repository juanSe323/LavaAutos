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

        $stmtVeh = $db->prepare("SELECT id FROM vehiculos WHERE placa = :placa");
        $stmtVeh->execute([':placa' => $placa]);
        $vehiculo = $stmtVeh->fetch();

        if ($vehiculo) {
            $vehiculoId = $vehiculo['id'];
        } else {
            $stmtInsVeh = $db->prepare("INSERT INTO vehiculos (placa, tipo) VALUES (:placa, :tipo)");
            $stmtInsVeh->execute([':placa' => $placa, ':tipo' => $tipoVehiculo]);
            $vehiculoId = $db->lastInsertId();
        }

        // Los precios se calculan del lado del servidor, nunca se confía en lo que mande el frontend
        $placeholders = implode(',', array_fill(0, count($tiposServicioIds), '?'));
        $stmtTipos = $db->prepare("SELECT id, precioBase FROM tipos_servicio WHERE id IN ($placeholders)");
        $stmtTipos->execute($tiposServicioIds);
        $tiposEncontrados = $stmtTipos->fetchAll();

        if (count($tiposEncontrados) === 0) {
            $db->rollBack();
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Ningún tipo de servicio válido fue encontrado"]);
            exit();
        }

        $total = 0;
        foreach ($tiposEncontrados as $t) {
            $total += $t['precioBase'];
        }

        $stmtServicio = $db->prepare("INSERT INTO servicios (estado, total, empleado_id, vehiculo_id) VALUES ('PENDIENTE', :total, :empleado_id, :vehiculo_id)");
        $stmtServicio->execute([
            ':total' => $total,
            ':empleado_id' => $empleado['id'],
            ':vehiculo_id' => $vehiculoId
        ]);
        $servicioId = $db->lastInsertId();

        $stmtDetalle = $db->prepare("INSERT INTO detalle_servicios (servicio_id, tipo_servicio_id, cantidad, subtotal) VALUES (:servicio_id, :tipo_servicio_id, 1, :subtotal)");
        foreach ($tiposEncontrados as $t) {
            $stmtDetalle->execute([
                ':servicio_id' => $servicioId,
                ':tipo_servicio_id' => $t['id'],
                ':subtotal' => $t['precioBase']
            ]);
        }

        $db->commit();

        echo json_encode([
            "status" => "success",
            "message" => "Servicio registrado correctamente",
            "servicio" => [
                "id" => $servicioId,
                "placa" => $placa,
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

        $stmt = $db->prepare("UPDATE servicios SET estado = :estado WHERE id = :id");
        $stmt->execute([':estado' => $estado, ':id' => $id]);

        echo json_encode(["status" => "success", "message" => "Estado actualizado"]);

    } elseif ($metodo === 'GET') {

        // Historial: el administrador ve todo, el empleado solo ve lo que él mismo registró
        if ($payload['rol'] === 'ADMINISTRADOR') {
            $stmt = $db->query("
                SELECT s.id, s.fecha, s.estado, s.total, v.placa, v.tipo AS tipo_vehiculo, u.nombre AS empleado_nombre
                FROM servicios s
                JOIN vehiculos v ON v.id = s.vehiculo_id
                JOIN empleados e ON e.id = s.empleado_id
                JOIN usuarios u ON u.id = e.usuario_id
                ORDER BY s.fecha DESC
                LIMIT 50
            ");
            $servicios = $stmt->fetchAll();
        } else {
            $stmtEmp = $db->prepare("SELECT id FROM empleados WHERE usuario_id = :usuario_id");
            $stmtEmp->execute([':usuario_id' => $payload['id']]);
            $empleado = $stmtEmp->fetch();

            $stmt = $db->prepare("
                SELECT s.id, s.fecha, s.estado, s.total, v.placa, v.tipo AS tipo_vehiculo
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