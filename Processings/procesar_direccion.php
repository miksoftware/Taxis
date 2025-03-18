<?php
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

// Incluir el controlador de direcciones
require_once '../Controllers/DireccionController.php';
$direccionController = new DireccionController($pdo);

// Procesar según la acción
$accion = $_POST['accion'];

switch ($accion) {
    case 'crear':
        // Verificar datos necesarios
        if (empty($_POST['cliente_id']) || empty($_POST['direccion'])) {
            echo json_encode(['error' => true, 'mensaje' => 'Faltan datos obligatorios']);
            exit;
        }
        
        $datos = [
            'cliente_id' => intval($_POST['cliente_id']),
            'direccion' => $_POST['direccion'],
            'referencia' => isset($_POST['referencia']) ? $_POST['referencia'] : '',
            'es_frecuente' => isset($_POST['es_frecuente']) ? intval($_POST['es_frecuente']) : 0
        ];
        
        $resultado = $direccionController->crear($datos);
        echo json_encode($resultado);
        break;
    
    case 'actualizar':
        // Verificar datos necesarios
        if (empty($_POST['id'])) {
            echo json_encode(['error' => true, 'mensaje' => 'ID de dirección no proporcionado']);
            exit;
        }
        
        $id = intval($_POST['id']);
        $datos = [];
        
        if (isset($_POST['direccion'])) $datos['direccion'] = $_POST['direccion'];
        if (isset($_POST['referencia'])) $datos['referencia'] = $_POST['referencia'];
        if (isset($_POST['es_frecuente'])) $datos['es_frecuente'] = intval($_POST['es_frecuente']);
        
        $resultado = $direccionController->actualizar($id, $datos);
        echo json_encode($resultado);
        break;


        case 'editar_direccion':
            // Verificar datos necesarios
            if (empty($_POST['servicio_id']) || empty($_POST['direccion_id']) || empty($_POST['nueva_direccion'])) {
                echo json_encode(['error' => true, 'mensaje' => 'Datos incompletos']);
                exit;
            }
            
            $servicio_id = intval($_POST['servicio_id']);
            $direccion_id = intval($_POST['direccion_id']);
            $nueva_direccion = trim($_POST['nueva_direccion']);
            
            // Cargar controlador de servicios para actualizar la referencia
            require_once '../Controllers/ServicioController.php';
            $servicioController = new ServicioController($pdo);
            
            // Preparar datos para actualización
            $datos = [
                'direccion' => $nueva_direccion
            ];
            
            // Actualizar dirección
            $resultado = $direccionController->actualizar($direccion_id, $datos);
            
            if (!isset($resultado['error']) || !$resultado['error']) {
                // Actualizar la referencia en el servicio si es necesario
                $servicioController->actualizarDireccion($servicio_id, $direccion_id);
                
                // Actualizar último uso
                $direccionController->actualizarUltimoUso($direccion_id);
                
                echo json_encode([
                    'error' => false,
                    'mensaje' => 'Dirección actualizada correctamente',
                    'direccion' => $nueva_direccion
                ]);
            } else {
                echo json_encode($resultado);
            }
            break;
    
    case 'eliminar':
        // Verificar datos necesarios
        if (empty($_POST['id'])) {
            echo json_encode(['error' => true, 'mensaje' => 'ID de dirección no proporcionado']);
            exit;
        }
        
        $id = intval($_POST['id']);
        $resultado = $direccionController->eliminar($id);
        echo json_encode($resultado);
        break;
    
    case 'actualizar_estado':
        // Verificar datos necesarios
        if (empty($_POST['id']) || !isset($_POST['activa'])) {
            echo json_encode(['error' => true, 'mensaje' => 'Datos incompletos']);
            exit;
        }
        
        $id = intval($_POST['id']);
        $activa = intval($_POST['activa']);
        $resultado = $direccionController->actualizarEstado($id, $activa);
        echo json_encode($resultado);
        break;
    
    case 'marcar_frecuente':
        // Verificar datos necesarios
        if (empty($_POST['id']) || !isset($_POST['es_frecuente'])) {
            echo json_encode(['error' => true, 'mensaje' => 'Datos incompletos']);
            exit;
        }
        
        $id = intval($_POST['id']);
        $es_frecuente = intval($_POST['es_frecuente']);
        $resultado = $direccionController->marcarFrecuente($id, $es_frecuente);
        echo json_encode($resultado);
        break;
    
    default:
        echo json_encode(['error' => true, 'mensaje' => 'Acción no válida']);
}