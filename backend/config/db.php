<?php
class Database {
    private $host = "localhost";
    private $db_name = "lavaautos";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $exception) {
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(["status" => "error", "message" => "Error BD: " . $exception->getMessage()]);
            exit();
        }

        return $this->conn;
    }
}
?>