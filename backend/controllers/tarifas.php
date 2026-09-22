<?php
require_once __DIR__ . '/../config/headers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

// Cualquier usuario autenticado puede consultar tarifas (el empleado las necesita para cotizar).
// Solo ADMINISTRADOR puede crearlas/editarlas/borrarlas.
$payload = verificarToken();

try {
    $database = new Database();
    $db = $database->getConnection();
    $metodo = $_SERVER['REQUEST_METHOD'];

    if ($metodo === 'GET') {

        if (isset($_GET['tipo_vehiculo'])) {
            // Usado por la terminal del empleado: solo los servicios disponibles para ese vehículo
            $stmt = $db->prepare("
                SELECT ts.id AS tipo_servicio_id, ts.nombre, t.precio
                FROM tarifas t
                JOIN tipos_servicio ts ON ts.id = t.tipo_servicio_id
                WHERE t.tipo_vehiculo = :tipo_vehiculo
                ORDER BY ts.nombre ASC
            ");
            $stmt->execute([':tipo_vehiculo' => $_GET['tipo_vehiculo']]);
        } else {
            // Usado por el admin: la matriz completa
            $stmt = $db->query("
                SELECT t.id, t.tipo_servicio_id, ts.nombre AS tipo_servicio_nombre, t.tipo_vehiculo, t.precio
                FROM tarifas t
                JOIN tipos_servicio ts ON ts.id = t.tipo_servicio_id
                ORDER BY ts.nombre ASC, t.tipo_vehiculo ASC
            ");
        }
        echo json_encode(["status" => "success", "data" => $stmt->fetchAll()]);

    } elseif ($metodo === 'POST' || $metodo === 'PUT') {
        verificarRol($payload, ['ADMINISTRADOR']);
        $data = json_decode(file_get_contents("php://input"), true);

        $tipoServicioId = $data['tipo_servicio_id'] ?? null;
        $tipoVehiculo = $data['tipo_vehiculo'] ?? null;
        $precio = $data['precio'] ?? null;

        $vehiculosValidos = ['CARRO', 'MOTO', 'CAMIONETA'];
        if (!$tipoServicioId || !in_array($tipoVehiculo, $vehiculosValidos)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "tipo_servicio_id y tipo_vehiculo son requeridos"]);
            exit();
        }

        if ($precio === null || $precio === '' || (float)$precio <= 0) {
            // Precio vacío o 0 = este servicio no aplica para ese vehículo -> se elimina la tarifa
            $stmt = $db->prepare("DELETE FROM tarifas WHERE tipo_servicio_id = :tsid AND tipo_vehiculo = :tv");
            $stmt->execute([':tsid' => $tipoServicioId, ':tv' => $tipoVehiculo]);
            echo json_encode(["status" => "success", "message" => "Tarifa eliminada (servicio no disponible para este vehículo)"]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO tarifas (tipo_servicio_id, tipo_vehiculo, precio)
                VALUES (:tsid, :tv, :precio)
                ON DUPLICATE KEY UPDATE precio = :precio2
            ");
            $stmt->execute([
                ':tsid' => $tipoServicioId,
                ':tv' => $tipoVehiculo,
                ':precio' => $precio,
                ':precio2' => $precio
            ]);
            echo json_encode(["status" => "success", "message" => "Tarifa guardada"]);
        }

    } else {
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Método no permitido"]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>