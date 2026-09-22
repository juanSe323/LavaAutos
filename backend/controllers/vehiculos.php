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
            if (!isset($_GET['cliente_id'])) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "cliente_id es requerido"]);
                exit();
            }
            $stmt = $db->prepare("SELECT id, placa, tipo, marca, modelo FROM vehiculos WHERE cliente_id = :cliente_id ORDER BY placa ASC");
            $stmt->execute([':cliente_id' => $_GET['cliente_id']]);
            echo json_encode(["status" => "success", "data" => $stmt->fetchAll()]);
            break;

        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            $vehiculosValidos = ['CARRO', 'MOTO', 'CAMIONETA'];

            if (empty($data['placa']) || empty($data['cliente_id']) || !in_array($data['tipo'] ?? '', $vehiculosValidos)) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "placa, tipo (válido) y cliente_id son requeridos"]);
                exit();
            }

            try {
                $stmt = $db->prepare("INSERT INTO vehiculos (placa, tipo, marca, modelo, cliente_id) VALUES (:placa, :tipo, :marca, :modelo, :cliente_id)");
                $stmt->execute([
                    ':placa' => strtoupper(trim($data['placa'])),
                    ':tipo' => $data['tipo'],
                    ':marca' => $data['marca'] ?? null,
                    ':modelo' => $data['modelo'] ?? null,
                    ':cliente_id' => $data['cliente_id']
                ]);
                echo json_encode(["status" => "success", "message" => "Vehículo agregado", "id" => $db->lastInsertId()]);
            } catch (PDOException $e) {
                http_response_code(409);
                echo json_encode(["status" => "error", "message" => "Ya existe un vehículo registrado con esa placa"]);
            }
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents("php://input"), true);
            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "El id es requerido"]);
                exit();
            }
            try {
                $stmt = $db->prepare("DELETE FROM vehiculos WHERE id = :id");
                $stmt->execute([':id' => $data['id']]);
                echo json_encode(["status" => "success", "message" => "Vehículo eliminado"]);
            } catch (PDOException $e) {
                http_response_code(409);
                echo json_encode(["status" => "error", "message" => "No se puede eliminar: este vehículo ya tiene servicios registrados"]);
            }
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
