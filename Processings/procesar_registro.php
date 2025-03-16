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
    $datos = [
        'nombre' => trim($_POST['nombre']),
        'apellidos' => trim($_POST['apellidos']),
        'email' => trim($_POST['email']),
        'username' => trim($_POST['username']),
        'password' => $_POST['password'],
        'confirmPassword' => $_POST['confirmPassword'],
        'telefono' => isset($_POST['telefono']) ? trim($_POST['telefono']) : '',
        'rol' => trim($_POST['rol'])
    ];
    
    // Procesar el registro
    $resultado = $usuarioController->registrar($datos);
    
    // Si hay errores, redirigir al formulario con los errores
    if ($resultado['error']) {
        $_SESSION['error_mensaje'] = $resultado['mensaje'];
        
        if (isset($resultado['errores'])) {
            $_SESSION['errores'] = $resultado['errores'];
        }
        
        // Guardar los datos para no perderlos
        $_SESSION['form_data'] = $datos;
        
        // Redirigir al formulario
        header('Location: ../Index.php');
        exit;
    }
    
    // Si todo va bien, establecer variables de sesión para el usuario
    $_SESSION['usuario_id'] = $resultado['usuario_id'] ?? 0;
    $_SESSION['usuario_nombre'] = $datos['nombre'] . ' ' . $datos['apellidos'];
    $_SESSION['usuario_rol'] = $datos['rol'];
    $_SESSION['autenticado'] = false;
    
    // Mensaje de éxito
    $_SESSION['success_mensaje'] = $resultado['mensaje'];
    
    // Redirigir al dashboard
    header('Location: ../Views/Login.php');
    exit;
} else {
    // Si no se ha enviado el formulario, redirigir al formulario
    header('Location: ../Index.php');
    exit;
}
?>