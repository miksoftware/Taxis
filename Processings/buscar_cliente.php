<?php
// Iniciar sesión
session_start();

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => true, 'mensaje' => 'No autorizado', 'redirect' => '../Views/Login.php']);
    exit;
}

// Verificar parámetro de teléfono
if (!isset($_GET['telefono']) || empty($_GET['telefono'])) {
    echo json_encode(['error' => true, 'mensaje' => 'Número de teléfono no proporcionado']);
    exit;
}

// Incluir la conexión a la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir el controlador de clientes
require_once '../Controllers/ClienteController.php';
$clienteController = new ClienteController($pdo);

// Buscar cliente por teléfono
$telefono = trim($_GET['telefono']);
$cliente = $clienteController->buscarPorTelefono($telefono);

if ($cliente) {
    // Cliente encontrado - Obtener sus direcciones frecuentes
    require_once '../Controllers/DireccionController.php';
    $direccionController = new DireccionController($pdo);
    $direcciones = $direccionController->obtenerUltimasDirecciones($cliente['id'], 30);
    
    if (isset($direcciones['error'])) {
        $direcciones = []; // Si hay error, inicializar como array vacío
    }
    
    // Devolver respuesta
    echo json_encode([
        'error' => false,
        'cliente_existe' => true,
        'cliente' => [
            'id' => $cliente['id'],
            'nombre' => $cliente['nombre'],
            'telefono' => $cliente['telefono']
        ],
        'direcciones' => $direcciones
    ]);
} else {
    // Cliente no encontrado
    echo json_encode([
        'error' => false,
        'cliente_existe' => false,
        'telefono' => $telefono
    ]);
}