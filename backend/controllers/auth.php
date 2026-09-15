<?php
require_once __DIR__ . '/../config/headers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/JWTHandler.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['email']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Email y password son requeridos"]);
        exit();
    }

    $stmt = $db->prepare("SELECT id, nombre, email, password, rol FROM usuarios WHERE email = :email LIMIT 1");
    $stmt->bindParam(':email', $data['email']);
    $stmt->execute();
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($data['password'], $usuario['password'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Credenciales inválidas"]);
        exit();
    }

    $token = JWTHandler::generarToken([
        "id" => $usuario['id'],
        "nombre" => $usuario['nombre'],
        "rol" => $usuario['rol']
    ]);

    echo json_encode([
        "status" => "success",
        "message" => "Inicio de sesión exitoso",
        "token" => $token,
        "usuario" => [
            "id" => $usuario['id'],
            "nombre" => $usuario['nombre'],
            "rol" => $usuario['rol']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
