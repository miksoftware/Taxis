<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Processings\guardar_direccion.php
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

// Verificar que sea una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $respuesta = ['error' => true, 'mensaje' => 'Método no permitido'];
    echo json_encode($respuesta);
    exit;
}

// Verificar datos necesarios
if (empty($_POST['cliente_id']) || empty($_POST['direccion'])) {
    $respuesta = ['error' => true, 'mensaje' => 'Datos incompletos'];
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

// Preparar datos
$cliente_id = intval($_POST['cliente_id']);
$direccion = trim($_POST['direccion']);
$referencia = isset($_POST['referencia']) ? trim($_POST['referencia']) : '';

// Normalizar la dirección para facilitar la detección de duplicados
$direccion_normalizada = $direccionController->normalizarDireccion($direccion);

// Verificar si la dirección ya existe para este cliente
$direcciones = $direccionController->buscarPorCliente($cliente_id);

$direccion_existe = false;
$direccion_id = null;

if (!isset($direcciones['error'])) {
    foreach ($direcciones as $dir) {
        $dir_normalizada = $direccionController->normalizarDireccion($dir['direccion']);
        if ($dir_normalizada === $direccion_normalizada) {
            $direccion_existe = true;
            $direccion_id = $dir['id'];
            break;
        }
    }
}

// Si la dirección no existe, crearla
if (!$direccion_existe) {
    $datos = [
        'cliente_id' => $cliente_id,
        'direccion' => $direccion,
        'direccion_normalizada' => $direccion_normalizada,
        'referencia' => $referencia,
        'es_frecuente' => false
    ];
    
    $resultado = $direccionController->crear($datos);
    
    if (isset($resultado['error']) && $resultado['error']) {
        echo json_encode($resultado);
        exit;
    }
    
    $direccion_id = $resultado['id'];
    
    // Verificar si hay que marcar como frecuente
    $total_direcciones = $direccionController->contarDireccionesPorCliente($cliente_id);
    
    // Si tiene menos de 5 direcciones, marcar como frecuente
    if ($total_direcciones < 5) {
        $direccionController->marcarComoFrecuente($direccion_id);
    }
}

// Actualizar fecha de último uso de la dirección
$direccionController->actualizarUltimoUso($direccion_id);

// Devolver respuesta
$respuesta = [
    'error' => false,
    'mensaje' => $direccion_existe ? 'Dirección existente seleccionada' : 'Dirección guardada correctamente',
    'direccion_id' => $direccion_id,
    'es_nueva' => !$direccion_existe
];

echo json_encode($respuesta);
exit;
?>