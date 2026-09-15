<?php
require_once __DIR__ . '/../config/headers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

// Cualquier usuario autenticado (admin o empleado) puede consultar el catálogo.
// Solo ADMINISTRADOR puede crear, editar o eliminar.
$payload = verificarToken();

try {
    $database = new Database();
    $db = $database->getConnection();

    $metodo = $_SERVER['REQUEST_METHOD'];

    switch ($metodo) {

        case 'GET':
            $stmt = $db->query("SELECT id, nombre, precioBase, tiempo_estimado FROM tipos_servicio ORDER BY nombre ASC");
            $tipos = $stmt->fetchAll();
            echo json_encode(["status" => "success", "data" => $tipos]);
            break;

        case 'POST':
            verificarRol($payload, ['ADMINISTRADOR']);
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['nombre']) || !isset($data['precioBase'])) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Nombre y precio base son requeridos"]);
                exit();
            }

            $stmt = $db->prepare("INSERT INTO tipos_servicio (nombre, precioBase, tiempo_estimado) VALUES (:nombre, :precioBase, :tiempo_estimado)");
            $stmt->execute([
                ':nombre' => $data['nombre'],
                ':precioBase' => $data['precioBase'],
                ':tiempo_estimado' => $data['tiempo_estimado'] ?? 30
            ]);

            echo json_encode(["status" => "success", "message" => "Tipo de servicio creado", "id" => $db->lastInsertId()]);
            break;

        case 'PUT':
            verificarRol($payload, ['ADMINISTRADOR']);
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "El id es requerido"]);
                exit();
            }

            $stmt = $db->prepare("UPDATE tipos_servicio SET nombre = :nombre, precioBase = :precioBase, tiempo_estimado = :tiempo_estimado WHERE id = :id");
            $stmt->execute([
                ':nombre' => $data['nombre'],
                ':precioBase' => $data['precioBase'],
                ':tiempo_estimado' => $data['tiempo_estimado'] ?? 30,
                ':id' => $data['id']
            ]);

            echo json_encode(["status" => "success", "message" => "Tipo de servicio actualizado"]);
            break;

        case 'DELETE':
            verificarRol($payload, ['ADMINISTRADOR']);
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "El id es requerido"]);
                exit();
            }

            try {
                $stmt = $db->prepare("DELETE FROM tipos_servicio WHERE id = :id");
                $stmt->execute([':id' => $data['id']]);
                echo json_encode(["status" => "success", "message" => "Tipo de servicio eliminado"]);
            } catch (PDOException $e) {
                // Ocurre si ya hay detalle_servicios que referencian este tipo (llave foránea)
                http_response_code(409);
                echo json_encode(["status" => "error", "message" => "No se puede eliminar: este tipo de servicio ya fue usado en algún servicio registrado"]);
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
