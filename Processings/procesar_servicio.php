<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Processings\procesar_servicio.php
// Iniciar sesión
session_start();

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => true, 'mensaje' => 'No autorizado', 'redirect' => '../Views/Login.php']);
    exit;
}

// Verificar método de solicitud
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => true, 'mensaje' => 'Método no permitido']);
    exit;
}

// Verificar acción
if (!isset($_POST['accion'])) {
    echo json_encode(['error' => true, 'mensaje' => 'Acción no especificada']);
    exit;
}

// Incluir la conexión a la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir el controlador de servicios
require_once '../Controllers/ServicioController.php';
$servicioController = new ServicioController($pdo);

// Procesar según la acción
$accion = $_POST['accion'];

switch ($accion) {
    case 'crear':
        // Verificar datos necesarios
        if (empty($_POST['cliente_id']) || empty($_POST['direccion_id']) || empty($_POST['condicion'])) {
            echo json_encode(['error' => true, 'mensaje' => 'Faltan datos obligatorios']);
            exit;
        }

        // Información de depuración
        error_log("Creando servicio: " . json_encode($_POST));

        // Comprobar la conexión
        try {
            $testStmt = $pdo->query("SELECT 1");
            error_log("Conexión a BD exitosa");
        } catch (PDOException $e) {
            error_log("Error de conexión: " . $e->getMessage());
            echo json_encode(['error' => true, 'mensaje' => 'Error de conexión: ' . $e->getMessage()]);
            exit;
        }

        $datos = [
            'cliente_id' => intval($_POST['cliente_id']),
            'direccion_id' => intval($_POST['direccion_id']),
            'condicion' => $_POST['condicion'],
            'observaciones' => isset($_POST['observaciones']) ? $_POST['observaciones'] : '',
            'operador_id' => $_SESSION['usuario_id']
        ];

        // Comprobar que operador_id es un entero válido
        if (!is_int($datos['operador_id'])) {
            error_log("operador_id no es un entero: " . gettype($datos['operador_id']) . " valor: " . $datos['operador_id']);
            $datos['operador_id'] = intval($datos['operador_id']);
        }

        error_log("Datos de servicio: " . json_encode($datos));

        // Crear el servicio
        $resultado = $servicioController->crear($datos);

        // Información de depuración
        error_log("Resultado creación: " . json_encode($resultado));

        echo json_encode($resultado);
        break;


    case 'cambiar_vehiculo':
        if (!isset($_POST['servicio_id']) || !isset($_POST['vehiculo_id'])) {
            echo json_encode(['error' => true, 'mensaje' => 'Datos incompletos']);
            exit;
        }

        $servicio_id = intval($_POST['servicio_id']);
        $vehiculo_id = intval($_POST['vehiculo_id']);

        $resultado = $servicioController->cambiarVehiculo($servicio_id, $vehiculo_id);
        echo json_encode($resultado);
        break;

    case 'asignar':
        // Verificar datos necesarios
        if (empty($_POST['servicio_id']) || empty($_POST['vehiculo_id'])) {
            echo json_encode(['error' => true, 'mensaje' => 'Faltan datos obligatorios']);
            exit;
        }

        $resultado = $servicioController->asignar(
            intval($_POST['servicio_id']),
            intval($_POST['vehiculo_id'])
        );
        echo json_encode($resultado);
        break;

    case 'finalizar':
        // Verificar datos necesarios
        if (empty($_POST['servicio_id'])) {
            echo json_encode(['error' => true, 'mensaje' => 'ID de servicio no proporcionado']);
            exit;
        }

        $resultado = $servicioController->finalizar(intval($_POST['servicio_id']));
        echo json_encode($resultado);
        break;

    case 'cancelar':
        // Verificar datos necesarios
        if (empty($_POST['servicio_id'])) {
            echo json_encode(['error' => true, 'mensaje' => 'ID de servicio no proporcionado']);
            exit;
        }

        $resultado = $servicioController->cancelar(intval($_POST['servicio_id']));
        echo json_encode($resultado);
        break;

    default:
        echo json_encode(['error' => true, 'mensaje' => 'Acción no válida']);
}
