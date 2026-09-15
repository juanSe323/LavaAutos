<?php
require_once __DIR__ . '/../helpers/JWTHandler.php';

// Llamar al inicio de cualquier controlador que requiera sesión activa.
// Devuelve el payload (id, nombre, rol) si el token es válido, o corta la ejecución con 401.
function verificarToken() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Token no proporcionado"]);
        exit();
    }

    $token = $matches[1];
    $payload = JWTHandler::validarToken($token);

    if (!$payload) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Token inválido o expirado"]);
        exit();
    }

    return $payload;
}

// Llamar después de verificarToken() cuando el endpoint sea exclusivo de ciertos roles.
// Ejemplo: verificarRol($payload, ['ADMINISTRADOR']);
function verificarRol($payload, $rolesPermitidos) {
    if (!in_array($payload['rol'], $rolesPermitidos)) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "No tienes permisos para esta acción"]);
        exit();
    }
}
?>
