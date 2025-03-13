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

// Incluir el controlador de reportes
require_once '../Controllers/ReporteController.php';
$reporteController = new ReporteController($pdo);

// Obtener parámetros
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('-30 days'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$usuario_id = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : null;
$rol = isset($_GET['rol']) ? $_GET['rol'] : null;
$detalle = isset($_GET['detalle']) ? (bool)$_GET['detalle'] : false;

// Si es una petición de detalles
if ($detalle && $usuario_id) {
    // Obtener detalles del usuario específico
    $resultado = $reporteController->obtenerEstadisticasUsuario($usuario_id, $fecha_inicio, $fecha_fin);
    
    // Devolver resultado
    header('Content-Type: application/json');
    echo json_encode($resultado);
    exit;
}

// Si no es detalle, preparar filtros para reporte general
$filtros = [
    'fecha_inicio' => $fecha_inicio,
    'fecha_fin' => $fecha_fin
];

if ($usuario_id) {
    $filtros['usuario_id'] = $usuario_id;
}

if ($rol) {
    $filtros['rol'] = $rol;
}

// Generar reporte
$reporte = $reporteController->generarReporteUsuarios($filtros);

// Devolver resultado como JSON
header('Content-Type: application/json');
echo json_encode($reporte);