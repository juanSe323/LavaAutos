<?php
// Script de un solo uso: crea el primer usuario ADMINISTRADOR para poder probar el login.
// Ejecútalo UNA vez desde el navegador (http://localhost/LavaAutos/backend/scripts/crear_admin.php)
// y luego bórralo o muévelo fuera del proyecto.

require_once __DIR__ . '/../config/db.php';

$database = new Database();
$db = $database->getConnection();

$nombre = "Admin Prueba";
$email = "admin@lavaautos.com";
$passwordPlano = "admin123"; // cámbiala después de la primera prueba
$rol = "ADMINISTRADOR";

$passwordHash = password_hash($passwordPlano, PASSWORD_DEFAULT);

try {
    $stmt = $db->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (:nombre, :email, :password, :rol)");
    $stmt->execute([
        ':nombre' => $nombre,
        ':email' => $email,
        ':password' => $passwordHash,
        ':rol' => $rol
    ]);
    echo "Usuario admin creado correctamente.<br>Email: $email<br>Password: $passwordPlano<br><br><strong>Borra este archivo después de usarlo.</strong>";
} catch (Exception $e) {
    echo "Error al crear el admin: " . $e->getMessage();
}
?>
