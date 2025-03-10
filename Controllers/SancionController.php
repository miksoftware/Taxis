<?php
require_once __DIR__ . '/../Models/SancionModel.php';
require_once __DIR__ . '/../Models/VehiculoModel.php';
require_once __DIR__ . '/../Models/ArticuloSancionModel.php';

class SancionController {
    private $sancionModel;
    private $vehiculoModel;
    private $articuloModel;
    
    public function __construct($pdo) {
        $this->sancionModel = new Sancion($pdo);
        $this->vehiculoModel = new Vehiculo($pdo);
        $this->articuloModel = new ArticuloSancion($pdo);
    }
    
    /**
     * Obtiene todas las sanciones según los filtros aplicados
     */
    public function listar($filtros = []) {
        return $this->sancionModel->obtenerTodas($filtros);
    }
    
    /**
     * Obtiene una sanción por su ID
     */
    public function obtener($id) {
        $sancion = $this->sancionModel->obtenerPorId($id);
        
        if (!$sancion || isset($sancion['error'])) {
            return ['error' => true, 'mensaje' => 'Sanción no encontrada'];
        }
        
        return $sancion;
    }
    
    /**
     * Procesa la aplicación de una nueva sanción
     */
    public function aplicar($datos) {
        // Validaciones básicas
        $errores = [];
        
        if (empty($datos['vehiculo_id'])) {
            $errores['vehiculo_id'] = 'El vehículo es requerido';
        } else {
            // Verificar que el vehículo existe y está disponible
            $vehiculo = $this->vehiculoModel->obtenerPorId($datos['vehiculo_id']);
            if (!$vehiculo || isset($vehiculo['error'])) {
                $errores['vehiculo_id'] = 'El vehículo seleccionado no existe';
            } elseif ($vehiculo['estado'] === 'sancionado') {
                $errores['vehiculo_id'] = 'El vehículo ya tiene una sanción activa';
            }
        }
        
        if (empty($datos['articulo_id'])) {
            $errores['articulo_id'] = 'El artículo de sanción es requerido';
        } else {
            // Verificar que el artículo existe y está activo
            $articulo = $this->articuloModel->obtenerPorId($datos['articulo_id']);
            if (!$articulo || isset($articulo['error'])) {
                $errores['articulo_id'] = 'El artículo de sanción seleccionado no existe';
            } elseif (!$articulo['activo']) {
                $errores['articulo_id'] = 'El artículo de sanción seleccionado no está activo';
            }
        }
        
        if (empty($datos['motivo'])) {
            $errores['motivo'] = 'El motivo de la sanción es requerido';
        }
        
        if (!empty($errores)) {
            return [
                'error' => true, 
                'mensaje' => 'Existen errores en el formulario', 
                'errores' => $errores
            ];
        }
        
        // Calcular fecha de finalización basada en tiempo de sanción
        $articulo = $this->articuloModel->obtenerPorId($datos['articulo_id']);
        $fecha_inicio = date('Y-m-d H:i:s');
        $tiempo_sancion = $articulo['tiempo_sancion']; // en minutos
        $fecha_fin = date('Y-m-d H:i:s', strtotime("+$tiempo_sancion minutes"));
        
        $datos_sancion = [
            'vehiculo_id' => $datos['vehiculo_id'],
            'articulo_id' => $datos['articulo_id'],
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'usuario_id' => $_SESSION['usuario_id'],
            'motivo' => $datos['motivo']
        ];
        
        // Aplicar la sanción
        $resultado = $this->sancionModel->crear($datos_sancion);
        
        if ($resultado['error']) {
            return $resultado;
        }
        
        // Actualizar estado del vehículo a "sancionado"
        $this->vehiculoModel->cambiarEstado($datos['vehiculo_id'], 'sancionado');
        
        return $resultado;
    }
    
    /**
     * Anula una sanción existente
     */
    public function anular($id, $comentario) {
        // Verificar si la sanción existe y está activa
        $sancion = $this->sancionModel->obtenerPorId($id);
        
        if (!$sancion || isset($sancion['error'])) {
            return ['error' => true, 'mensaje' => 'Sanción no encontrada'];
        }
        
        if ($sancion['estado'] !== 'activa') {
            return ['error' => true, 'mensaje' => 'Solo se pueden anular sanciones activas'];
        }
        
        // Validar comentario
        if (empty($comentario)) {
            return ['error' => true, 'mensaje' => 'Debe proporcionar un comentario para anular la sanción'];
        }
        
        // Anular la sanción
        $resultado = $this->sancionModel->cambiarEstado($id, 'anulada');
        
        if ($resultado['error']) {
            return $resultado;
        }
        
        // Registrar en historial
        $this->sancionModel->registrarHistorial($id, 'anulada', $_SESSION['usuario_id'], $comentario);
        
        // Actualizar estado del vehículo a "disponible"
        $this->vehiculoModel->cambiarEstado($sancion['vehiculo_id'], 'disponible');
        
        return [
            'error' => false,
            'mensaje' => 'Sanción anulada correctamente'
        ];
    }
    
    /**
     * Verifica y actualiza las sanciones vencidas
     */
    public function verificarVencimientos() {
        $resultado = $this->sancionModel->verificarSancionesVencidas();
        
        if ($resultado['error']) {
            return $resultado;
        }
        
        // Si se actualizaron sanciones, registrar en log
        if ($resultado['actualizadas'] > 0) {
            error_log(date('Y-m-d H:i:s') . " - {$resultado['actualizadas']} sanciones actualizadas por vencimiento");
        }
        
        return $resultado;
    }
    
    /**
     * Obtiene las sanciones activas de un vehículo
     */
    public function obtenerSancionesVehiculo($vehiculo_id) {
        return $this->sancionModel->obtenerPorVehiculo($vehiculo_id, 'activa');
    }
    
    /**
     * Verifica si un vehículo tiene sanciones activas
     */
    public function vehiculoTieneSancionActiva($vehiculo_id) {
        $sanciones = $this->sancionModel->obtenerPorVehiculo($vehiculo_id, 'activa');
        
        if (isset($sanciones['error'])) {
            return false;
        }
        
        return count($sanciones) > 0;
    }
    
    /**
     * Obtiene el historial de sanciones de un vehículo
     */
    public function obtenerHistorialVehiculo($vehiculo_id) {
        return $this->sancionModel->obtenerHistorialPorVehiculo($vehiculo_id);
    }
    
    /**
     * Obtiene estadísticas de sanciones
     */
    public function obtenerEstadisticas($filtros = []) {
        return $this->sancionModel->obtenerEstadisticas($filtros);
    }
    
    /**
     * Formatea un tiempo en minutos a un formato legible
     */
    public function formatearTiempoMinutos($minutos) {
        $horas = floor($minutos / 60);
        $minutosRestantes = $minutos % 60;
        
        $texto = '';
        
        if ($horas > 0) {
            $texto .= $horas . ' hora' . ($horas > 1 ? 's' : '');
        }
        
        if ($minutosRestantes > 0) {
            if ($texto) {
                $texto .= ' y ';
            }
            $texto .= $minutosRestantes . ' minuto' . ($minutosRestantes > 1 ? 's' : '');
        }
        
        if ($texto === '') {
            $texto = '0 minutos';
        }
        
        return $texto;
    }
}
?>