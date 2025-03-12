<?php
// Establecer zona horaria para Colombia 
date_default_timezone_set('America/Bogota');

// Este script está diseñado para ejecutarse por un cron job para verificar y actualizar
// automáticamente el estado de las sanciones vencidas

// Establecer el entorno sin salida HTML
define('CRON_RUNNING', true);

// Importante: Ajustar las rutas para que sean absolutas
$basePath = dirname(dirname(__FILE__));

// Incluir la conexión a la base de datos
require_once $basePath . '/config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir los controladores y modelos necesarios
require_once $basePath . '/Controllers/SancionController.php';
require_once $basePath . '/Models/SancionModel.php';
require_once $basePath . '/Models/VehiculoModel.php';

// Inicializar controlador
$sancionController = new SancionController($pdo);

// Comprobar si hay sanciones activas que deberían verificarse
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sanciones WHERE estado = 'activa'");
    $stmt->execute();
    $sancionesActivas = $stmt->fetchColumn();
    
    // Comprobar si hay sanciones que ya vencieron pero no se han actualizado
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sanciones WHERE estado = 'activa' AND fecha_fin < NOW()");
    $stmt->execute();
    $sancionesVencidas = $stmt->fetchColumn();
    
    $debug_info = "Hora servidor: " . date('Y-m-d H:i:s') . 
                 " | Sanciones activas: " . $sancionesActivas . 
                 " | Sanciones vencidas pendientes: " . $sancionesVencidas . "\n";
    
    file_put_contents($basePath . '/logs/sancion_debug.log', $debug_info, FILE_APPEND);
} catch (PDOException $e) {
    file_put_contents($basePath . '/logs/sancion_error.log', date('Y-m-d H:i:s') . " - Error consultando sanciones: " . $e->getMessage() . "\n", FILE_APPEND);
}

// Verificar vencimientos
$resultado = $sancionController->verificarVencimientos();

// Registrar resultado
$log_message = date('Y-m-d H:i:s') . " - " . 
               ($resultado['error'] ? "ERROR: " . $resultado['mensaje'] : 
               "OK: " . $resultado['actualizadas'] . " sanciones actualizadas") . "\n";

// Escribir a un archivo de log
file_put_contents($basePath . '/logs/sancion_cron.log', $log_message, FILE_APPEND);

if (!CRON_RUNNING) {
    echo json_encode($resultado);
}
?>