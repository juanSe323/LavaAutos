<?php
// Script de un solo uso: crea un usuario EMPLEADO de prueba para verificar el menú reducido.
// Ejecútalo UNA vez desde el navegador (http://localhost/LavaAutos/backend/scripts/crear_empleado.php)
// y luego bórralo o muévelo fuera del proyecto.

require_once __DIR__ . '/../config/db.php';

$database = new Database();
$db = $database->getConnection();

$nombre = "Empleado Prueba";
$email = "empleado@lavaautos.com";
$passwordPlano = "empleado123"; // cámbiala después de la primera prueba
$rol = "EMPLEADO";
$modalidad = "40%"; // o "50%", según lo que quieras probar

$passwordHash = password_hash($passwordPlano, PASSWORD_DEFAULT);

try {
    $db->beginTransaction();

    // 1. Crear el usuario base
    $stmt = $db->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (:nombre, :email, :password, :rol)");
    $stmt->execute([
        ':nombre' => $nombre,
        ':email' => $email,
        ':password' => $passwordHash,
        ':rol' => $rol
    ]);
    $usuarioId = $db->lastInsertId();

    // 2. Crear su fila correspondiente en empleados (todo EMPLEADO debe tener una)
    $porcentajeBase = ($modalidad === '50%') ? 50.00 : 40.00;
    $stmt2 = $db->prepare("INSERT INTO empleados (usuario_id, porcentajeBase, modalidad) VALUES (:usuario_id, :porcentajeBase, :modalidad)");
    $stmt2->execute([
        ':usuario_id' => $usuarioId,
        ':porcentajeBase' => $porcentajeBase,
        ':modalidad' => $modalidad
    ]);

    $db->commit();

    echo "Usuario empleado creado correctamente.<br>Email: $email<br>Password: $passwordPlano<br>Modalidad: $modalidad<br><br><strong>Borra este archivo después de usarlo.</strong>";
} catch (Exception $e) {
    $db->rollBack();
    echo "Error al crear el empleado: " . $e->getMessage();
}
?>
