<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Processings\procesar_sancion.php
// Iniciar sesión
session_start();

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['mensaje'] = "Debe iniciar sesión para realizar esta acción";
    $_SESSION['tipo_mensaje'] = "danger";
    header('Location: ../Views/Login.php');
    exit;
}

// Verificar si hay una acción a realizar
if (!isset($_POST['accion'])) {
    $_SESSION['mensaje'] = "Acción no especificada";
    $_SESSION['tipo_mensaje'] = "danger";
    header('Location: ../Views/vehiculo.php');
    exit;
}

// Incluir la conexión a la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir los controladores necesarios
require_once '../Controllers/SancionController.php';
require_once '../Controllers/ArticuloSancionController.php';
require_once '../Controllers/VehiculoController.php';

// Inicializar controladores
$sancionController = new SancionController($pdo);
$vehiculoController = new VehiculoController($pdo);
$articuloController = new ArticuloSancionController($pdo);

// Procesar según la acción
$accion = $_POST['accion'];

switch ($accion) {
    case 'aplicar':
        // Validación más clara para mostrar mensajes específicos
        if (empty($_POST['vehiculo_id'])) {
            $_SESSION['mensaje'] = "Error: No se ha seleccionado un vehículo";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/vehiculo.php');
            exit;
        }
        
        if (empty($_POST['articulo_id'])) {
            $_SESSION['mensaje'] = "Error: Debe seleccionar un artículo de sanción";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/vehiculo.php');
            exit;
        }
        
        if (empty($_POST['motivo'])) {
            $_SESSION['mensaje'] = "Error: Debe ingresar un motivo para la sanción";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/vehiculo.php');
            exit;
        }

        // Verificar que el vehículo exista y esté disponible
        $vehiculo = $vehiculoController->obtener($_POST['vehiculo_id']);
        if (isset($vehiculo['error']) || !$vehiculo) {
            $_SESSION['mensaje'] = "Error: El vehículo seleccionado no existe";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/vehiculo.php');
            exit;
        }

        if ($vehiculo['estado'] === 'sancionado') {
            $_SESSION['mensaje'] = "Error: El vehículo ya se encuentra sancionado";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/vehiculo.php');
            exit;
        }

        // Verificar que el artículo exista
        $articulo = $articuloController->obtener($_POST['articulo_id']);
        if (isset($articulo['error']) || !$articulo) {
            $_SESSION['mensaje'] = "Error: El artículo seleccionado no existe";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/vehiculo.php');
            exit;
        }
        
        // Aplicar sanción
        $datos = [
            'vehiculo_id' => intval($_POST['vehiculo_id']),
            'articulo_id' => intval($_POST['articulo_id']),
            'motivo' => trim($_POST['motivo']),
            'usuario_id' => $_SESSION['usuario_id'] // Agregar el ID del usuario que aplica la sanción
        ];
        
        $resultado = $sancionController->aplicar($datos);
        
        if (isset($resultado['error']) && $resultado['error']) {
            $_SESSION['mensaje'] = "Error al aplicar sanción: " . $resultado['mensaje'];
            $_SESSION['tipo_mensaje'] = "danger";
        } else {
            // Obtener información del vehículo para el mensaje
            $fecha_fin = date('d/m/Y H:i', strtotime($resultado['fecha_fin']));
            $_SESSION['mensaje'] = "Sanción aplicada correctamente. El vehículo quedará inhabilitado hasta el " . $fecha_fin;
            $_SESSION['tipo_mensaje'] = "success";
        }
        
        header('Location: ../Views/vehiculo.php');
        break;
        
    case 'anular':
        // Validar datos
        if (empty($_POST['id'])) {
            $_SESSION['mensaje'] = "ID de sanción no proporcionado";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/Sanciones.php');
            exit;
        }
        
        if (empty($_POST['comentario'])) {
            $_SESSION['mensaje'] = "Debe ingresar un motivo para anular la sanción";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/Sanciones.php');
            exit;
        }
        
        // Obtener la sanción
        $sancion = $sancionController->obtener($_POST['id']);
        if (isset($sancion['error']) || !$sancion) {
            $_SESSION['mensaje'] = "Error: La sanción no existe";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/Sanciones.php');
            exit;
        }
        
        if ($sancion['estado'] !== 'activa') {
            $_SESSION['mensaje'] = "Error: Solo se pueden anular sanciones activas";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/Sanciones.php');
            exit;
        }
        
        // Anular sanción
        $resultado = $sancionController->anular($_POST['id'], $_POST['comentario']);
        
        if (isset($resultado['error']) && $resultado['error']) {
            $_SESSION['mensaje'] = "Error al anular sanción: " . $resultado['mensaje'];
            $_SESSION['tipo_mensaje'] = "danger";
        } else {
            $_SESSION['mensaje'] = "Sanción anulada correctamente. El vehículo ya está disponible nuevamente.";
            $_SESSION['tipo_mensaje'] = "success";
        }
        
        header('Location: ../Views/Sanciones.php');
        break;
        
    case 'verificar_vencimientos':
        // Solo permitir a usuarios autenticados con permisos adecuados
        if (!isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] !== 'administrador') {
            $_SESSION['mensaje'] = "No tiene permisos para realizar esta acción";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/Sanciones.php');
            exit;
        }
        
        // Verificar vencimientos manualmente
        $resultado = $sancionController->verificarVencimientos();
        
        if (isset($resultado['error']) && $resultado['error']) {
            $_SESSION['mensaje'] = "Error al verificar vencimientos: " . $resultado['mensaje'];
            $_SESSION['tipo_mensaje'] = "danger";
        } else {
            if ($resultado['actualizadas'] > 0) {
                $_SESSION['mensaje'] = "Verificación completada: " . $resultado['actualizadas'] . " sanciones actualizadas";
                $_SESSION['tipo_mensaje'] = "success";
            } else {
                $_SESSION['mensaje'] = "Verificación completada: No hay sanciones vencidas pendientes de actualizar";
                $_SESSION['tipo_mensaje'] = "info";
            }
        }
        
        header('Location: ../Views/Sanciones.php');
        break;
        
    default:
        $_SESSION['mensaje'] = "Acción no reconocida";
        $_SESSION['tipo_mensaje'] = "danger";
        header('Location: ../Views/vehiculo.php');
}

exit;
?>