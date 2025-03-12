<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Processings\procesar_cliente.php
// Iniciar sesión
session_start();

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode(['error' => true, 'mensaje' => 'No autorizado', 'redirect' => '../Views/Login.php']);
    } else {
        header('Location: ../Views/Login.php');
    }
    exit;
}

// Verificar que hay una acción a realizar
if (!isset($_POST['accion'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode(['error' => true, 'mensaje' => 'Acción no especificada']);
    } else {
        header('Location: ../Views/Clientes.php');
    }
    exit;
}

// Incluir la conexión a la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir el controlador de clientes
require_once '../Controllers/ClienteController.php';
$clienteController = new ClienteController($pdo);

// Procesar según la acción
$accion = $_POST['accion'];
$es_ajax = isset($_POST['ajax']) && $_POST['ajax'] == 1;

switch ($accion) {
    case 'crear':
        // Validar datos mínimos
        if (empty($_POST['telefono'])) {
            if ($es_ajax) {
                echo json_encode(['error' => true, 'mensaje' => 'El teléfono es obligatorio']);
            } else {
                $_SESSION['mensaje'] = 'El teléfono es obligatorio';
                $_SESSION['tipo_mensaje'] = 'danger';
                header('Location: ../Views/Clientes.php');
            }
            exit;
        }
        
        // Preparar datos
        $datos = [
            'telefono' => $_POST['telefono'],
            'nombre' => isset($_POST['nombre']) ? $_POST['nombre'] : 'Cliente ' . $_POST['telefono'],
            'notas' => isset($_POST['notas']) ? $_POST['notas'] : ''
        ];
        
        // Crear cliente
        $resultado = $clienteController->crear($datos);
        
        if ($es_ajax) {
            echo json_encode($resultado);
        } else {
            if (isset($resultado['error']) && $resultado['error']) {
                $_SESSION['mensaje'] = $resultado['mensaje'];
                $_SESSION['tipo_mensaje'] = 'danger';
            } else {
                $_SESSION['mensaje'] = 'Cliente creado correctamente';
                $_SESSION['tipo_mensaje'] = 'success';
            }
            header('Location: ../Views/Clientes.php');
        }
        break;
        
    case 'crear_rapido':
        // Validar teléfono
        if (empty($_POST['telefono'])) {
            echo json_encode(['error' => true, 'mensaje' => 'El teléfono es obligatorio']);
            exit;
        }
        
        // Crear cliente rápido
        $resultado = $clienteController->crearRapido($_POST['telefono']);
        echo json_encode($resultado);
        break;
        
    case 'actualizar':
        // Validar ID
        if (empty($_POST['id'])) {
            if ($es_ajax) {
                echo json_encode(['error' => true, 'mensaje' => 'ID de cliente no proporcionado']);
            } else {
                $_SESSION['mensaje'] = 'ID de cliente no proporcionado';
                $_SESSION['tipo_mensaje'] = 'danger';
                header('Location: ../Views/Clientes.php');
            }
            exit;
        }
        
        // Preparar datos
        $id = intval($_POST['id']);
        $datos = [];
        
        if (isset($_POST['nombre'])) {
            $datos['nombre'] = $_POST['nombre'];
        }
        
        if (isset($_POST['notas'])) {
            $datos['notas'] = $_POST['notas'];
        }
        
        // Actualizar cliente
        $resultado = $clienteController->actualizar($id, $datos);
        
        if ($es_ajax) {
            echo json_encode($resultado);
        } else {
            if (isset($resultado['error']) && $resultado['error']) {
                $_SESSION['mensaje'] = $resultado['mensaje'];
                $_SESSION['tipo_mensaje'] = 'danger';
            } else {
                $_SESSION['mensaje'] = 'Cliente actualizado correctamente';
                $_SESSION['tipo_mensaje'] = 'success';
            }
            header('Location: ../Views/Clientes.php');
        }
        break;
        
    default:
        if ($es_ajax) {
            echo json_encode(['error' => true, 'mensaje' => 'Acción no válida']);
        } else {
            $_SESSION['mensaje'] = 'Acción no válida';
            $_SESSION['tipo_mensaje'] = 'danger';
            header('Location: ../Views/Clientes.php');
        }
}