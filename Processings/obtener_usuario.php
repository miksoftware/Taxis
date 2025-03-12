<?php
// Iniciar sesión
session_start();

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => true, 'mensaje' => 'No autorizado']);
    exit;
}

// Verificar que sea administrador
if ($_SESSION['usuario_rol'] !== 'administrador') {
    echo json_encode(['error' => true, 'mensaje' => 'No tiene permisos para realizar esta acción']);
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

// Incluir el controlador de usuarios
require_once '../Controllers/UsuarioController.php';
$usuarioController = new UsuarioController($pdo);

// Obtener datos del usuario
$id = intval($_GET['id']);
$usuario = $usuarioController->obtener($id);

// Devolver respuesta en formato JSON
header('Content-Type: application/json');

if (isset($usuario['error']) && $usuario['error']) {
    echo json_encode(['error' => true, 'mensaje' => $usuario['mensaje']]);
} else {
    // Quitar la contraseña para mayor seguridad
    if (isset($usuario['password'])) {
        unset($usuario['password']);
    }
    
    echo json_encode($usuario);
}
exit;
?>