<?php
require_once __DIR__ . '/../config/headers.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

// Endpoint de ejemplo: cualquier usuario autenticado (ADMINISTRADOR o EMPLEADO) puede entrar.
$payload = verificarToken();

// Ejemplo de restricción por rol (comentado, úsalo cuando lo necesites):
// verificarRol($payload, ['ADMINISTRADOR']);

echo json_encode([
    "status" => "success",
    "message" => "Token válido, sesión activa",
    "usuario" => $payload
]);
?>
