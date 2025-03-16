<?php
// Iniciar sesión
session_start();

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['mensaje'] = "Debe iniciar sesión para realizar esta acción";
    $_SESSION['tipo_mensaje'] = "danger";
    header('Location: ../Views/Login.php');
    exit;
}

// Verificar que sea administrador
if ($_SESSION['usuario_rol'] !== 'administrador') {
    $_SESSION['mensaje'] = "No tiene permisos para realizar esta acción";
    $_SESSION['tipo_mensaje'] = "danger";
    header('Location: ../Views/index.php');
    exit;
}

// Verificar si hay una acción a realizar
if (!isset($_POST['accion'])) {
    $_SESSION['mensaje'] = "Acción no especificada";
    $_SESSION['tipo_mensaje'] = "danger";
    header('Location: ../Views/Usuarios.php');
    exit;
}

// Incluir la conexión a la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir el controlador de usuarios
require_once '../Controllers/UsuarioController.php';
$usuarioController = new UsuarioController($pdo);

// Procesar según la acción
$accion = $_POST['accion'];

switch ($accion) {
    case 'crear':
        // Validación básica
        $campos_requeridos = ['nombre', 'apellidos', 'email', 'username', 'password', 'confirmPassword', 'rol'];
        foreach ($campos_requeridos as $campo) {
            if (!isset($_POST[$campo]) || empty($_POST[$campo])) {
                $_SESSION['mensaje'] = "Todos los campos marcados con * son obligatorios";
                $_SESSION['tipo_mensaje'] = "danger";
                header('Location: ../Views/Usuarios.php');
                exit;
            }
        }

        // Validar que las contraseñas coincidan
        if ($_POST['password'] !== $_POST['confirmPassword']) {
            $_SESSION['mensaje'] = "Las contraseñas no coinciden";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/Usuarios.php');
            exit;
        }
        
        // Preparar datos
        $datos = [
            'nombre' => trim($_POST['nombre']),
            'apellidos' => trim($_POST['apellidos']),
            'email' => trim($_POST['email']),
            'username' => trim($_POST['username']),
            'password' => $_POST['password'],
            'confirmPassword' => $_POST['confirmPassword'],
            'telefono' => isset($_POST['telefono']) ? trim($_POST['telefono']) : '',
            'rol' => $_POST['rol']
        ];
        
        // Registrar usuario
        $resultado = $usuarioController->registrar($datos);
        
        if (isset($resultado['error']) && $resultado['error']) {
            $_SESSION['mensaje'] = $resultado['mensaje'];
            $_SESSION['tipo_mensaje'] = "danger";
        } else {
            $_SESSION['mensaje'] = "Usuario registrado correctamente";
            $_SESSION['tipo_mensaje'] = "success";
        }
        
        header('Location: ../Views/Usuarios.php');
        break;
        
    case 'actualizar':
        // Validar ID
        if (!isset($_POST['id']) || empty($_POST['id'])) {
            $_SESSION['mensaje'] = "ID de usuario no especificado";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/Usuarios.php');
            exit;
        }
        
        // Validación básica
        $campos_requeridos = ['nombre', 'apellidos', 'email', 'username', 'rol'];
        foreach ($campos_requeridos as $campo) {
            if (!isset($_POST[$campo]) || empty($_POST[$campo])) {
                $_SESSION['mensaje'] = "Todos los campos marcados con * son obligatorios";
                $_SESSION['tipo_mensaje'] = "danger";
                header('Location: ../Views/Usuarios.php');
                exit;
            }
        }
        
        // Preparar datos
        $id = intval($_POST['id']);
        $datos = [
            'nombre' => trim($_POST['nombre']),
            'apellidos' => trim($_POST['apellidos']),
            'email' => trim($_POST['email']),
            'username' => trim($_POST['username']), // Aunque no lo cambies, se pasa para validación
            'telefono' => isset($_POST['telefono']) ? trim($_POST['telefono']) : '',
            'rol' => $_POST['rol']
        ];
        
        // Actualizar usuario
        $resultado = $usuarioController->actualizar($id, $datos);
        
        if (isset($resultado['error']) && $resultado['error']) {
            $_SESSION['mensaje'] = $resultado['mensaje'];
            $_SESSION['tipo_mensaje'] = "danger";
        } else {
            $_SESSION['mensaje'] = "Usuario actualizado correctamente";
            $_SESSION['tipo_mensaje'] = "success";
        }
        
        header('Location: ../Views/Usuarios.php');
        break;
        
    case 'cambiar_estado':
        // Validar ID y estado
        if (!isset($_POST['id']) || empty($_POST['id']) || !isset($_POST['estado'])) {
            $_SESSION['mensaje'] = "Datos incompletos para cambiar el estado";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/Usuarios.php');
            exit;
        }
        
        // Verificar que no esté cambiando su propio estado
        $id = intval($_POST['id']);
        if ($id === $_SESSION['usuario_id']) {
            $_SESSION['mensaje'] = "No puede cambiar su propio estado";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/Usuarios.php');
            exit;
        }
        
        // Cambiar estado
        $estado = intval($_POST['estado']);
        $resultado = $usuarioController->cambiarEstado($id, $estado);
        
        if (isset($resultado['error']) && $resultado['error']) {
            $_SESSION['mensaje'] = $resultado['mensaje'];
            $_SESSION['tipo_mensaje'] = "danger";
        } else {
            $_SESSION['mensaje'] = $estado ? "Usuario activado correctamente" : "Usuario desactivado correctamente";
            $_SESSION['tipo_mensaje'] = "success";
        }
        
        header('Location: ../Views/Usuarios.php');
        break;
        
    case 'reset_password':
        // Validar ID
        if (!isset($_POST['id']) || empty($_POST['id'])) {
            $_SESSION['mensaje'] = "ID de usuario no especificado";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/Usuarios.php');
            exit;
        }
        
        // Validar contraseñas
        if (empty($_POST['new_password']) || empty($_POST['confirm_new_password'])) {
            $_SESSION['mensaje'] = "Debe proporcionar una nueva contraseña";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/Usuarios.php');
            exit;
        }
        
        if ($_POST['new_password'] !== $_POST['confirm_new_password']) {
            $_SESSION['mensaje'] = "Las contraseñas no coinciden";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/Usuarios.php');
            exit;
        }
        
        // Verificar requisitos de contraseña
        if (strlen($_POST['new_password']) < 8) {
            $_SESSION['mensaje'] = "La contraseña debe tener al menos 8 caracteres";
            $_SESSION['tipo_mensaje'] = "danger";
            header('Location: ../Views/Usuarios.php');
            exit;
        }
        
        $id = intval($_POST['id']);
        $password = $_POST['new_password'];
        
        // Restablecer contraseña
        $resultado = $usuarioController->resetPassword($id, $password);
        
        if (isset($resultado['error']) && $resultado['error']) {
            $_SESSION['mensaje'] = $resultado['mensaje'];
            $_SESSION['tipo_mensaje'] = "danger";
        } else {
            $_SESSION['mensaje'] = "Contraseña restablecida correctamente";
            $_SESSION['tipo_mensaje'] = "success";
        }
        
        header('Location: ../Views/Usuarios.php');
        break;
        
    default:
        $_SESSION['mensaje'] = "Acción no reconocida";
        $_SESSION['tipo_mensaje'] = "warning";
        header('Location: ../Views/Usuarios.php');
}

exit;
?>