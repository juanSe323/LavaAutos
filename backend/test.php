<?php
require_once __DIR__ . '/config/headers.php';
require_once __DIR__ . '/config/db.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if($db) {
        echo json_encode([
            "status" => "success",
            "message" => "¡Conexión exitosa a la base de datos de LavaAutos!",
            "timestamp" => date("Y-m-d H:i:s")
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>