<?php
// Iniciar sesión
session_start();

// Verificar autenticación (si tienes un sistema de autenticación)
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
    header('Location: ../Views/Vehiculo.php');
    exit;
}

// Incluir la conexión a la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir el controlador de vehículos
require_once '../Controllers/VehiculoController.php';
$vehiculoController = new VehiculoController($pdo);

// Procesar según la acción
$accion = $_POST['accion'];

switch ($accion) {
    case 'crear':
        // Validar datos mínimos
        if (empty($_POST['placa']) || empty($_POST['numero_movil'])) {
            $_SESSION['mensaje'] = "Todos los campos son obligatorios";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/Vehiculo.php');
            exit;
        }
        
        // Preparar datos
        $datos = [
            'placa' => strtoupper(trim($_POST['placa'])),
            'numero_movil' => trim($_POST['numero_movil']),
            'estado' => $_POST['estado']
        ];
        
        // Registrar vehículo
        $resultado = $vehiculoController->registrar($datos);
        
        if (isset($resultado['error']) && $resultado['error']) {
            $_SESSION['mensaje'] = $resultado['mensaje'];
            $_SESSION['tipo_mensaje'] = "danger";
        } else {
            $_SESSION['mensaje'] = "Vehículo registrado correctamente";
            $_SESSION['tipo_mensaje'] = "success";
        }
        
        header('Location: ../Views/Vehiculo.php');
        break;
        
    case 'actualizar':
        // Validar ID
        if (!isset($_POST['id']) || empty($_POST['id'])) {
            $_SESSION['mensaje'] = "ID de vehículo no especificado";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/Vehiculo.php');
            exit;
        }
        
        // Validar datos mínimos
        if (empty($_POST['placa']) || empty($_POST['numero_movil'])) {
            $_SESSION['mensaje'] = "Todos los campos son obligatorios";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/Vehiculo.php');
            exit;
        }
        
        // Preparar datos
        $id = intval($_POST['id']);
        $datos = [
            'placa' => strtoupper(trim($_POST['placa'])),
            'numero_movil' => trim($_POST['numero_movil']),
            'estado' => $_POST['estado']
        ];
        
        // Actualizar vehículo
        $resultado = $vehiculoController->actualizar($id, $datos);
        
        if (isset($resultado['error']) && $resultado['error']) {
            $_SESSION['mensaje'] = $resultado['mensaje'];
            $_SESSION['tipo_mensaje'] = "danger";
        } else {
            $_SESSION['mensaje'] = "Vehículo actualizado correctamente";
            $_SESSION['tipo_mensaje'] = "success";
        }
        
        header('Location: ../Views/Vehiculo.php');
        break;
        
    case 'eliminar':
        // Validar ID
        if (!isset($_POST['id']) || empty($_POST['id'])) {
            $_SESSION['mensaje'] = "ID de vehículo no especificado";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/Vehiculo.php');
            exit;
        }
        
        // Eliminar vehículo
        $id = intval($_POST['id']);
        $resultado = $vehiculoController->eliminar($id);
        
        if (isset($resultado['error']) && $resultado['error']) {
            $_SESSION['mensaje'] = $resultado['mensaje'];
            $_SESSION['tipo_mensaje'] = "danger";
        } else {
            $_SESSION['mensaje'] = "Vehículo eliminado correctamente";
            $_SESSION['tipo_mensaje'] = "success";
        }
        
        header('Location: ../Views/Vehiculo.php');
        break;
        
    case 'cambiar_estado':
        // Validar ID y estado
        if (!isset($_POST['id']) || empty($_POST['id']) || !isset($_POST['estado']) || empty($_POST['estado'])) {
            $_SESSION['mensaje'] = "Datos incompletos para cambiar el estado";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/Vehiculo.php');
            exit;
        }
        
        // Cambiar estado
        $id = intval($_POST['id']);
        $estado = $_POST['estado'];
        $resultado = $vehiculoController->cambiarEstado($id, $estado);
        
        if (isset($resultado['error']) && $resultado['error']) {
            $_SESSION['mensaje'] = $resultado['mensaje'];
            $_SESSION['tipo_mensaje'] = "danger";
        } else {
            $_SESSION['mensaje'] = "Estado del vehículo actualizado correctamente";
            $_SESSION['tipo_mensaje'] = "success";
        }
        
        header('Location: ../Views/Vehiculo.php');
        break;
        
    default:
        $_SESSION['mensaje'] = "Acción no reconocida";
        $_SESSION['tipo_mensaje'] = "warning";
        header('Location: ../Views/Vehiculo.php');
}
exit;
?>