<?php
// Iniciar sesión
session_start();

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => true, 'mensaje' => 'No autorizado', 'redirect' => '../Views/Login.php']);
    exit;
}

// Verificar parámetros
if (!isset($_GET['cliente_id'])) {
    echo json_encode(['error' => true, 'mensaje' => 'ID de cliente no proporcionado']);
    exit;
}

$cliente_id = intval($_GET['cliente_id']);

// Incluir la conexión a la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir el controlador de direcciones
require_once '../Controllers/DireccionController.php';
$direccionController = new DireccionController($pdo);

// Obtener direcciones del cliente
$direcciones = $direccionController->obtenerPorCliente($cliente_id);

if (isset($direcciones['error'])) {
    echo json_encode($direcciones);
} else {
    // Filtrar solo direcciones activas si se solicita (por defecto)
    $solo_activas = !isset($_GET['mostrar_inactivas']) || $_GET['mostrar_inactivas'] == '0';
    
    if ($solo_activas) {
        $direcciones = array_filter($direcciones, function($dir) {
            return !isset($dir['activa']) || $dir['activa'] == 1;
        });
        // Reindexar array después de filtrar
        $direcciones = array_values($direcciones);
    }
    
    echo json_encode([
        'error' => false, 
        'direcciones' => $direcciones,
        'total' => count($direcciones),
        'solo_activas' => $solo_activas
    ]);
}