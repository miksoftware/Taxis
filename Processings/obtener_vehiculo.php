<?php
// Iniciar sesión
session_start();

// Verificar autenticación 
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => true, 'mensaje' => 'No autorizado']);
    exit;
}

// Verificar ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['error' => true, 'mensaje' => 'ID no proporcionado']);
    exit;
}

// Incluir la conexión a la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir el controlador de vehículos
require_once '../Controllers/VehiculoController.php';
$vehiculoController = new VehiculoController($pdo);

// Obtener datos del vehículo
$id = intval($_GET['id']);
$vehiculo = $vehiculoController->obtener($id);

// Devolver respuesta en formato JSON
header('Content-Type: application/json');
echo json_encode($vehiculo);
exit;
?>