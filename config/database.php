<?php
class Database {
    private $pdo;
    
    public function __construct() {
        $host = 'localhost'; 
        $dbname = 'taxi_diamantes'; 
        $username = 'root';
        $password = ''; 
        
        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
    
    public function getPdo() {
        return $this->pdo;
    }
}
?>