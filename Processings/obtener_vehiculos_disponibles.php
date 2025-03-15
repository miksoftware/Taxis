<?php
// Iniciar sesión
session_start();

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => true, 'mensaje' => 'No autorizado']);
    exit;
}

// Incluir la conexión a la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir el controlador de vehículos
require_once '../Controllers/VehiculoController.php';
$vehiculoController = new VehiculoController($pdo);

// Obtener vehículos disponibles
$filtros = ['estado' => 'disponible'];
$vehiculos = $vehiculoController->listar($filtros);

// Devolver respuesta
echo json_encode([
    'error' => false,
    'vehiculos' => $vehiculos
]);