<?php
// Iniciar sesión
session_start();

// Verificar autenticación (si tienes un sistema de autenticación)
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

// Incluir el controlador de artículos
require_once '../Controllers/ArticuloSancionController.php';
$articuloController = new ArticuloSancionController($pdo);

// Obtener datos del artículo
$id = intval($_GET['id']);
$articulo = $articuloController->obtener($id);

// Devolver respuesta en formato JSON
header('Content-Type: application/json');
echo json_encode($articulo);
exit;
?>