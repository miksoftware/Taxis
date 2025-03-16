<?php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => true, 'mensaje' => 'No autorizado']);
    exit;
}

// Incluir la configuración de la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir controladores necesarios
require_once '../Controllers/VehiculoController.php';
require_once '../Controllers/SancionController.php';

// Instanciar controladores
$vehiculoController = new VehiculoController($pdo);
$sancionController = new SancionController($pdo);

// Obtener parámetros
$vehiculo_id = isset($_GET['vehiculo_id']) ? intval($_GET['vehiculo_id']) : 0;
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('-90 days'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');

// Validar parámetros
if ($vehiculo_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => true, 'mensaje' => 'ID de vehículo no válido']);
    exit;
}

try {
    // Obtener información del vehículo
    $vehiculo = $vehiculoController->obtener($vehiculo_id);
    
    if (!$vehiculo || isset($vehiculo['error'])) {
        throw new Exception('No se encontró información del vehículo');
    }
    
    // Obtener historial de sanciones para este vehículo en el período especificado
    $filtros = [
        'vehiculo_id' => $vehiculo_id,
        'fecha_inicio' => $fecha_inicio,
        'fecha_fin' => $fecha_fin
    ];
    
    $sanciones = $sancionController->listar($filtros);
    
    // Si no hay sanciones, devolver array vacío
    if (isset($sanciones['error'])) {
        $sanciones = [];
    }
    
    // Generar estadísticas de artículos para este vehículo
    $estadisticas = ['articulos' => []];
    
    if (count($sanciones) > 0) {
        foreach ($sanciones as $sancion) {
            $codigo = $sancion['articulo_codigo'] ?? 'N/A';
            
            if (!isset($estadisticas['articulos'][$codigo])) {
                $estadisticas['articulos'][$codigo] = [
                    'codigo' => $codigo,
                    'descripcion' => $sancion['articulo_descripcion'] ?? 'Sin descripción',
                    'count' => 0
                ];
            }
            
            $estadisticas['articulos'][$codigo]['count']++;
        }
    }
    
    // Preparar respuesta
    $respuesta = [
        'error' => false,
        'vehiculo' => $vehiculo,
        'sanciones' => $sanciones,
        'estadisticas' => $estadisticas
    ];
    
    // Devolver respuesta en formato JSON
    header('Content-Type: application/json');
    echo json_encode($respuesta);
    
} catch (Exception $e) {
    // En caso de error, devolver mensaje
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'mensaje' => 'Error al obtener información: ' . $e->getMessage(),
        'detalle' => $e->getTraceAsString()
    ]);
}
?>