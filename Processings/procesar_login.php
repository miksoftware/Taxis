<?php
// Iniciar sesión
session_start();

// Incluir la configuración de la base de datos
require_once '../config/database.php';

// Crear instancia de la base de datos y obtener la conexión PDO
$db = new Database();
$pdo = $db->getPdo();

// Incluir el controlador
require_once '../Controllers/UsuarioController.php';

// Crear instancia del controlador 
$usuarioController = new UsuarioController($pdo);

// Verificar que el formulario ha sido enviado vía POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Recoger los datos del formulario
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $recordar = isset($_POST['recordar']);
    
    // Validar datos básicos
    $errores = [];
    
    if (empty($username)) {
        $errores['username'] = 'El nombre de usuario es requerido';
    }
    
    if (empty($password)) {
        $errores['password'] = 'La contraseña es requerida';
    }
    
    if (count($errores) > 0) {
        $_SESSION['errores'] = $errores;
        $_SESSION['form_data'] = ['username' => $username];
        $_SESSION['error_mensaje'] = 'Por favor, complete todos los campos.';
        header('Location: ../Views/Login.php');
        exit;
    }
    
    // Procesar el inicio de sesión
    $resultado = $usuarioController->login($username, $password);
    
    // Si hay errores, redirigir al formulario con los errores
    if (!$resultado['exito']) {
        $_SESSION['error_mensaje'] = $resultado['mensaje'];
        $_SESSION['form_data'] = ['username' => $username];
        header('Location: ../Views/Login.php');
        exit;
    }
    
    // Si todo va bien, establecer variables de sesión para el usuario
    $_SESSION['usuario_id'] = $resultado['usuario']['id'];
    $_SESSION['usuario_nombre'] = $resultado['usuario']['nombre'] . ' ' . $resultado['usuario']['apellidos'];
    $_SESSION['usuario_rol'] = $resultado['usuario']['rol'];
    $_SESSION['autenticado'] = true;
    
    // Si el usuario seleccionó "recordarme", establecer una cookie
    if ($recordar) {
        $token = $usuarioController->generarTokenRecuerdo($resultado['usuario']['id']);
        if ($token) {
            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true); // 30 días
        }
    }
    
    // Actualizar último acceso
    $usuarioController->actualizarUltimoAcceso($resultado['usuario']['id']);
    
    // Mensaje de éxito
    $_SESSION['success_mensaje'] = 'Inicio de sesión exitoso';
    
    // Redirigir al dashboard
    header('Location: ../Views/Dashboard.php');
    exit;
} else {
    // Si no se ha enviado el formulario, redirigir al formulario
    header('Location: ../Views/Login.php');
    exit;
}
?>