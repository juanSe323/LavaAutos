<?php
require_once __DIR__ . '/../config/headers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

$payload = verificarToken();
verificarRol($payload, ['ADMINISTRADOR']); // gestión de empleados es exclusiva del administrador

try {
    $database = new Database();
    $db = $database->getConnection();
    $metodo = $_SERVER['REQUEST_METHOD'];

    switch ($metodo) {

        case 'GET':
            $stmt = $db->query("
                SELECT e.id, u.id AS usuario_id, u.nombre, u.email, u.telefono, e.modalidad, e.porcentajeBase
                FROM empleados e
                JOIN usuarios u ON u.id = e.usuario_id
                ORDER BY u.nombre ASC
            ");
            echo json_encode(["status" => "success", "data" => $stmt->fetchAll()]);
            break;

        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['nombre']) || !isset($data['email']) || empty($data['password'])) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Nombre, email y contraseña son requeridos"]);
                exit();
            }

            $modalidad = in_array($data['modalidad'] ?? '', ['40%', '50%']) ? $data['modalidad'] : '40%';
            $porcentajeBase = $modalidad === '50%' ? 50.00 : 40.00;

            $db->beginTransaction();
            try {
                $stmtUsuario = $db->prepare("INSERT INTO usuarios (nombre, email, password, rol, telefono) VALUES (:nombre, :email, :password, 'EMPLEADO', :telefono)");
                $stmtUsuario->execute([
                    ':nombre' => $data['nombre'],
                    ':email' => $data['email'],
                    ':password' => password_hash($data['password'], PASSWORD_DEFAULT),
                    ':telefono' => $data['telefono'] ?? null
                ]);
                $usuarioId = $db->lastInsertId();

                $stmtEmpleado = $db->prepare("INSERT INTO empleados (usuario_id, porcentajeBase, modalidad) VALUES (:usuario_id, :porcentajeBase, :modalidad)");
                $stmtEmpleado->execute([
                    ':usuario_id' => $usuarioId,
                    ':porcentajeBase' => $porcentajeBase,
                    ':modalidad' => $modalidad
                ]);

                $db->commit();
                echo json_encode(["status" => "success", "message" => "Empleado creado"]);
            } catch (PDOException $e) {
                $db->rollBack();
                if ($e->getCode() === '23000') {
                    http_response_code(409);
                    echo json_encode(["status" => "error", "message" => "Ya existe un usuario con ese email"]);
                } else {
                    throw $e;
                }
            }
            break;

        case 'PUT':
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['id']) || !isset($data['usuario_id'])) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "id y usuario_id son requeridos"]);
                exit();
            }

            $modalidad = in_array($data['modalidad'] ?? '', ['40%', '50%']) ? $data['modalidad'] : '40%';
            $porcentajeBase = $modalidad === '50%' ? 50.00 : 40.00;

            $db->beginTransaction();
            try {
                if (!empty($data['password'])) {
                    $stmtUsuario = $db->prepare("UPDATE usuarios SET nombre = :nombre, email = :email, telefono = :telefono, password = :password WHERE id = :usuario_id");
                    $stmtUsuario->execute([
                        ':nombre' => $data['nombre'],
                        ':email' => $data['email'],
                        ':telefono' => $data['telefono'] ?? null,
                        ':password' => password_hash($data['password'], PASSWORD_DEFAULT),
                        ':usuario_id' => $data['usuario_id']
                    ]);
                } else {
                    // Sin contraseña nueva: se deja la que ya tenía
                    $stmtUsuario = $db->prepare("UPDATE usuarios SET nombre = :nombre, email = :email, telefono = :telefono WHERE id = :usuario_id");
                    $stmtUsuario->execute([
                        ':nombre' => $data['nombre'],
                        ':email' => $data['email'],
                        ':telefono' => $data['telefono'] ?? null,
                        ':usuario_id' => $data['usuario_id']
                    ]);
                }

                $stmtEmpleado = $db->prepare("UPDATE empleados SET modalidad = :modalidad, porcentajeBase = :porcentajeBase WHERE id = :id");
                $stmtEmpleado->execute([
                    ':modalidad' => $modalidad,
                    ':porcentajeBase' => $porcentajeBase,
                    ':id' => $data['id']
                ]);

                $db->commit();
                echo json_encode(["status" => "success", "message" => "Empleado actualizado"]);
            } catch (PDOException $e) {
                $db->rollBack();
                if ($e->getCode() === '23000') {
                    http_response_code(409);
                    echo json_encode(["status" => "error", "message" => "Ya existe un usuario con ese email"]);
                } else {
                    throw $e;
                }
            }
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['usuario_id'])) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "usuario_id es requerido"]);
                exit();
            }

            try {
                // Borrar el usuario borra en cascada su fila de empleados (ON DELETE CASCADE)
                $stmt = $db->prepare("DELETE FROM usuarios WHERE id = :usuario_id");
                $stmt->execute([':usuario_id' => $data['usuario_id']]);
                echo json_encode(["status" => "success", "message" => "Empleado eliminado"]);
            } catch (PDOException $e) {
                // Ocurre si el empleado ya tiene servicios registrados (servicios.empleado_id lo referencia)
                http_response_code(409);
                echo json_encode(["status" => "error", "message" => "No se puede eliminar: este empleado ya tiene servicios registrados"]);
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
