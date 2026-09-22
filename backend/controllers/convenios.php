<?php
require_once __DIR__ . '/../config/headers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

$payload = verificarToken();
verificarRol($payload, ['ADMINISTRADOR']); // gestión de convenios es exclusiva del administrador

try {
    $database = new Database();
    $db = $database->getConnection();
    $metodo = $_SERVER['REQUEST_METHOD'];

    switch ($metodo) {

        case 'GET':
            $stmt = $db->query("SELECT id, nombre, descuentoPorcentaje FROM convenios ORDER BY nombre ASC");
            echo json_encode(["status" => "success", "data" => $stmt->fetchAll()]);
            break;

        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            if (!isset($data['nombre'])) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "El nombre es requerido"]);
                exit();
            }
            $stmt = $db->prepare("INSERT INTO convenios (nombre, descuentoPorcentaje) VALUES (:nombre, :descuento)");
            $stmt->execute([
                ':nombre' => $data['nombre'],
                ':descuento' => $data['descuentoPorcentaje'] ?? 0
            ]);
            echo json_encode(["status" => "success", "message" => "Convenio creado", "id" => $db->lastInsertId()]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents("php://input"), true);
            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "El id es requerido"]);
                exit();
            }
            $stmt = $db->prepare("UPDATE convenios SET nombre = :nombre, descuentoPorcentaje = :descuento WHERE id = :id");
            $stmt->execute([
                ':nombre' => $data['nombre'],
                ':descuento' => $data['descuentoPorcentaje'] ?? 0,
                ':id' => $data['id']
            ]);
            echo json_encode(["status" => "success", "message" => "Convenio actualizado"]);
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents("php://input"), true);
            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "El id es requerido"]);
                exit();
            }
            // clientes.convenio_id tiene ON DELETE SET NULL, así que esto no rompe nada
            $stmt = $db->prepare("DELETE FROM convenios WHERE id = :id");
            $stmt->execute([':id' => $data['id']]);
            echo json_encode(["status" => "success", "message" => "Convenio eliminado"]);
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
