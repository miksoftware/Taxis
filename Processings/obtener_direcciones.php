<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Processings\obtener_direcciones.php
// Iniciar sesión
session_start();

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    $respuesta = [
        'error' => true, 
        'mensaje' => 'No autorizado', 
        'redirect' => '../Views/Login.php'
    ];
    echo json_encode($respuesta);
    exit;
}

// Verificar que se recibe un ID de cliente
if (!isset($_GET['cliente_id']) || empty($_GET['cliente_id'])) {
    $respuesta = ['error' => true, 'mensaje' => 'ID de cliente no proporcionado'];
    echo json_encode($respuesta);
    exit;
}

// Incluir la conexión a la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir el controlador de direcciones
require_once '../Controllers/DireccionController.php';
$direccionController = new DireccionController($pdo);

// Obtener direcciones frecuentes/recientes del cliente
$cliente_id = intval($_GET['cliente_id']);
$limite = isset($_GET['limite']) ? intval($_GET['limite']) : 5;

$direcciones = $direccionController->obtenerDireccionesFrecuentes($cliente_id, $limite);

// Preparar la respuesta
if (isset($direcciones['error']) && $direcciones['error']) {
    $respuesta = $direcciones;
} else {
    $respuesta = [
        'error' => false,
        'direcciones' => $direcciones
    ];
}

// Devolver respuesta JSON
header('Content-Type: application/json');
echo json_encode($respuesta);
exit;