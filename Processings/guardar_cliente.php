<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Processings\guardar_cliente.php
// Iniciar sesión
session_start();

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => true, 'mensaje' => 'No autorizado', 'redirect' => '../Views/Login.php']);
    exit;
}

// Verificar método de solicitud
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => true, 'mensaje' => 'Método no permitido']);
    exit;
}

// Verificar datos necesarios
if (empty($_POST['telefono'])) {
    echo json_encode(['error' => true, 'mensaje' => 'El teléfono es obligatorio']);
    exit;
}

// Incluir la conexión a la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir el controlador de cliente
require_once '../Controllers/ClienteController.php';
$clienteController = new ClienteController($pdo);

// Preparar datos del cliente
$telefono = trim($_POST['telefono']);
$nombre = !empty($_POST['nombre']) ? trim($_POST['nombre']) : 'Cliente ' . $telefono;

// Comprobar si el cliente ya existe
$cliente_existente = $clienteController->buscarPorTelefono($telefono);

if ($cliente_existente) {
    // Si ya existe, devolver sus datos
    echo json_encode([
        'error' => false,
        'mensaje' => 'Cliente ya existe en el sistema',
        'id' => $cliente_existente['id'],
        'nombre' => $cliente_existente['nombre'],
        'telefono' => $cliente_existente['telefono']
    ]);
    exit;
}

// Si no existe, crear el nuevo cliente
$datos = [
    'telefono' => $telefono,
    'nombre' => $nombre,
    'fecha_registro' => date('Y-m-d H:i:s'),
    'ultima_actualizacion' => date('Y-m-d H:i:s'),
    'notas' => ''
];

$resultado = $clienteController->crear($datos);

if (isset($resultado['error']) && $resultado['error']) {
    echo json_encode($resultado);
    exit;
}

// Devolver resultado exitoso
echo json_encode([
    'error' => false,
    'mensaje' => 'Cliente creado correctamente',
    'id' => $resultado['id'],
    'nombre' => $nombre,
    'telefono' => $telefono
]);