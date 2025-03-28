<?php
// Iniciar sesión
session_start();

// Verificación de autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => true, 'mensaje' => 'No autorizado']);
    exit;
}

// Añadir un encabezado para prevenir caché
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Incluir la conexión a la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir el controlador de servicios
require_once '../Controllers/ServicioController.php';
$servicioController = new ServicioController($pdo);

// Obtener última actualización del cliente (si hay)
$ultima_actualizacion = isset($_GET['ultima_actualizacion']) && !empty($_GET['ultima_actualizacion']) ? 
                     $_GET['ultima_actualizacion'] : '';

// Si no hay timestamp previo o se está forzando la actualización, siempre obtener datos frescos
$forzarActualizacion = isset($_GET['_cb']);

// Verificar si hay cambios desde la última actualización
$hayActualizaciones = true;

// Si tenemos un timestamp de última actualización y no estamos forzando la actualización, comprobar si hay cambios
if (!empty($ultima_actualizacion) && !$forzarActualizacion) {
    try {
        $sql = "SELECT COUNT(*) as cambios FROM servicios 
                WHERE fecha_actualizacion > :ultima_actualizacion";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':ultima_actualizacion', $ultima_actualizacion, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $hayActualizaciones = $result['cambios'] > 0;
    } catch (PDOException $e) {
        error_log("Error verificando actualizaciones: " . $e->getMessage());
        $hayActualizaciones = true;
    }
}

// Obtener timestamp actual para control de actualizaciones 
$timestamp_actual = date('Y-m-d H:i:s');

// Obtener servicios activos
// Si no hay timestamp previo, hay cambios o estamos forzando la actualización, obtener la lista completa
$servicios_activos = $hayActualizaciones || $forzarActualizacion || empty($ultima_actualizacion) ? 
                    $servicioController->listarServiciosActivos() : [];

// Devolver los datos en formato JSON
header('Content-Type: application/json');
echo json_encode([
    'error' => false,
    'timestamp' => $timestamp_actual,
    'hayActualizaciones' => $hayActualizaciones,
    'servicios' => $servicios_activos,
    'debug_info' => [
        'ultima_actualizacion_recibida' => $ultima_actualizacion,
        'forzar_actualizacion' => $forzarActualizacion,
        'numero_servicios' => count($servicios_activos)
    ]
]);
?>