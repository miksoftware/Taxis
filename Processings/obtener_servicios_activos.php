<?php
// Iniciar sesión
session_start();

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => true, 'mensaje' => 'No autorizado', 'redirect' => '../Views/Login.php']);
    exit;
}

// Incluir la conexión a la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir el controlador de servicios
require_once '../Controllers/ServicioController.php';
$servicioController = new ServicioController($pdo);

// Obtener servicios activos
try {
    $servicios_activos = $servicioController->listarServiciosActivos();
    
    // Asegurarse de que sea un array (para manejar casos donde no hay servicios)
    if (!is_array($servicios_activos)) {
        $servicios_activos = [];
    }
    
    // Devolver los datos
    header('Content-Type: application/json');
    echo json_encode([
        'error' => false,
        'servicios' => $servicios_activos
    ]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'mensaje' => 'Error al obtener servicios activos: ' . $e->getMessage()
    ]);
}