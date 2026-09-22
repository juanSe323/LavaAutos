<?php
require_once __DIR__ . '/../config/headers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

$payload = verificarToken();
verificarRol($payload, ['ADMINISTRADOR']);

try {
    $database = new Database();
    $db = $database->getConnection();
    $metodo = $_SERVER['REQUEST_METHOD'];

    switch ($metodo) {

        case 'GET':
            $stmt = $db->query("
                SELECT c.id, c.nombre, c.telefono, c.email, c.convenio_id,
                       co.nombre AS convenio_nombre, co.descuentoPorcentaje,
                       (SELECT COUNT(*) FROM vehiculos v WHERE v.cliente_id = c.id) AS vehiculos_count
                FROM clientes c
                LEFT JOIN convenios co ON co.id = c.convenio_id
                ORDER BY c.nombre ASC
            ");
            echo json_encode(["status" => "success", "data" => $stmt->fetchAll()]);
            break;

        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            if (!isset($data['nombre'])) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "El nombre es requerido"]);
                exit();
            }
            $stmt = $db->prepare("INSERT INTO clientes (nombre, telefono, email, convenio_id) VALUES (:nombre, :telefono, :email, :convenio_id)");
            $stmt->execute([
                ':nombre' => $data['nombre'],
                ':telefono' => $data['telefono'] ?? null,
                ':email' => $data['email'] ?? null,
                ':convenio_id' => $data['convenio_id'] ?: null
            ]);
            echo json_encode(["status" => "success", "message" => "Cliente creado", "id" => $db->lastInsertId()]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents("php://input"), true);
            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "El id es requerido"]);
                exit();
            }
            $stmt = $db->prepare("UPDATE clientes SET nombre = :nombre, telefono = :telefono, email = :email, convenio_id = :convenio_id WHERE id = :id");
            $stmt->execute([
                ':nombre' => $data['nombre'],
                ':telefono' => $data['telefono'] ?? null,
                ':email' => $data['email'] ?? null,
                ':convenio_id' => $data['convenio_id'] ?: null,
                ':id' => $data['id']
            ]);
            echo json_encode(["status" => "success", "message" => "Cliente actualizado"]);
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents("php://input"), true);
            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "El id es requerido"]);
                exit();
            }
            // vehiculos.cliente_id y servicios.cliente_id tienen ON DELETE SET NULL, no se rompe nada
            $stmt = $db->prepare("DELETE FROM clientes WHERE id = :id");
            $stmt->execute([':id' => $data['id']]);
            echo json_encode(["status" => "success", "message" => "Cliente eliminado"]);
            break;

        default:
            http_response_code(405);
            echo json_encode(["status" => "error", "message" => "Método no permitido"]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
