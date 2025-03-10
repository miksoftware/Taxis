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
    header('Location: ../Views/ArticuloSancion.php');
    exit;
}

// Incluir la conexión a la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir el controlador de artículos
require_once '../Controllers/ArticuloSancionController.php';
$articuloController = new ArticuloSancionController($pdo);

// Procesar según la acción
$accion = $_POST['accion'];

switch ($accion) {
    case 'crear':
        // Validar datos mínimos
        if (empty($_POST['codigo']) || empty($_POST['descripcion']) || !isset($_POST['tiempo_sancion'])) {
            $_SESSION['mensaje'] = "Todos los campos son obligatorios";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/ArticuloSancion.php');
            exit;
        }
        
        // Preparar datos
        $datos = [
            'codigo' => $_POST['codigo'],
            'descripcion' => $_POST['descripcion'],
            'tiempo_sancion' => intval($_POST['tiempo_sancion']),
            'activo' => isset($_POST['activo']) ? 1 : 0
        ];
        
        // Registrar artículo
        $resultado = $articuloController->registrar($datos);
        
        if (isset($resultado['error']) && $resultado['error']) {
            $_SESSION['mensaje'] = $resultado['mensaje'];
            $_SESSION['tipo_mensaje'] = "danger";
        } else {
            $_SESSION['mensaje'] = "Artículo registrado correctamente";
            $_SESSION['tipo_mensaje'] = "success";
        }
        
        header('Location: ../Views/ArticuloSancion.php');
        break;
        
    case 'actualizar':
        // Validar ID
        if (!isset($_POST['id']) || empty($_POST['id'])) {
            $_SESSION['mensaje'] = "ID de artículo no especificado";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/ArticuloSancion.php');
            exit;
        }
        
        // Validar datos mínimos
        if (empty($_POST['codigo']) || empty($_POST['descripcion']) || !isset($_POST['tiempo_sancion'])) {
            $_SESSION['mensaje'] = "Todos los campos son obligatorios";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/ArticuloSancion.php');
            exit;
        }
        
        // Preparar datos
        $id = intval($_POST['id']);
        $datos = [
            'codigo' => $_POST['codigo'],
            'descripcion' => $_POST['descripcion'],
            'tiempo_sancion' => intval($_POST['tiempo_sancion']),
            'activo' => isset($_POST['activo']) ? 1 : 0
        ];
        
        // Actualizar artículo
        $resultado = $articuloController->actualizar($id, $datos);
        
        if (isset($resultado['error']) && $resultado['error']) {
            $_SESSION['mensaje'] = $resultado['mensaje'];
            $_SESSION['tipo_mensaje'] = "danger";
        } else {
            $_SESSION['mensaje'] = "Artículo actualizado correctamente";
            $_SESSION['tipo_mensaje'] = "success";
        }
        
        header('Location: ../Views/ArticuloSancion.php');
        break;
        
    case 'eliminar':
        // Validar ID
        if (!isset($_POST['id']) || empty($_POST['id'])) {
            $_SESSION['mensaje'] = "ID de artículo no especificado";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/ArticuloSancion.php');
            exit;
        }
        
        // Eliminar artículo
        $id = intval($_POST['id']);
        $resultado = $articuloController->eliminar($id);
        
        if (isset($resultado['error']) && $resultado['error']) {
            $_SESSION['mensaje'] = $resultado['mensaje'];
            $_SESSION['tipo_mensaje'] = "danger";
        } else {
            $_SESSION['mensaje'] = "Artículo eliminado correctamente";
            $_SESSION['tipo_mensaje'] = "success";
        }
        
        header('Location: ../Views/ArticuloSancion.php');
        break;
        
    case 'cambiar_estado':
        // Validar ID y estado
        if (!isset($_POST['id']) || empty($_POST['id']) || !isset($_POST['estado'])) {
            $_SESSION['mensaje'] = "Datos incompletos para cambiar el estado";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/ArticuloSancion.php');
            exit;
        }
        
        // Cambiar estado
        $id = intval($_POST['id']);
        $estado = intval($_POST['estado']) ? 1 : 0;
        $resultado = $articuloController->cambiarEstado($id, $estado);
        
        if (isset($resultado['error']) && $resultado['error']) {
            $_SESSION['mensaje'] = $resultado['mensaje'];
            $_SESSION['tipo_mensaje'] = "danger";
        } else {
            $_SESSION['mensaje'] = $estado ? "Artículo activado correctamente" : "Artículo desactivado correctamente";
            $_SESSION['tipo_mensaje'] = "success";
        }
        
        header('Location: ../Views/ArticuloSancion.php');
        break;
        
    default:
        $_SESSION['mensaje'] = "Acción no reconocida";
        $_SESSION['tipo_mensaje'] = "warning";
        header('Location: ../Views/ArticuloSancion.php');
}
exit;
?>