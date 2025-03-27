<?php
require_once __DIR__ . '/../Models/VehiculoModel.php';
require_once __DIR__ . '/../Models/SancionModel.php';

class VehiculoController {
    private $vehiculoModel;
    private $sancionModel;
    
    public function __construct($pdo) {
        $this->vehiculoModel = new Vehiculo($pdo);
        $this->sancionModel = new Sancion($pdo);
    }
    
    /**
     * Obtiene todos los vehículos
     */
    public function listar($filtros = []) {
        $vehiculos = $this->vehiculoModel->obtenerTodos($filtros);
        
        // Verificar si hay vehículos sancionados y calcular tiempo restante
        foreach ($vehiculos as &$vehiculo) {
            if ($vehiculo['estado'] === 'sancionado') {
                $sanciones = $this->sancionModel->obtenerPorVehiculo($vehiculo['id'], 'activa');
                if (!empty($sanciones) && !isset($sanciones['error'])) {
                    $vehiculo['sancion_activa'] = $sanciones[0];
                }
            }
        }
        
        return $vehiculos;
    }
    
    /**
     * Obtiene un vehículo por ID
     */
    public function obtener($id) {
        $vehiculo = $this->vehiculoModel->obtenerPorId($id);
        
        if (!$vehiculo || isset($vehiculo['error'])) {
            return ['error' => true, 'mensaje' => 'Vehículo no encontrado'];
        }
        
        // Si el vehículo está sancionado, obtener información de la sanción
        if ($vehiculo['estado'] === 'sancionado') {
            $sanciones = $this->sancionModel->obtenerPorVehiculo($id, 'activa');
            if (!empty($sanciones) && !isset($sanciones['error'])) {
                $vehiculo['sancion_activa'] = $sanciones[0];
            }
        }
        
        // Obtener historial de sanciones
        $historial = $this->sancionModel->obtenerHistorialPorVehiculo($id);
        if (!isset($historial['error'])) {
            $vehiculo['historial_sanciones'] = $historial;
        }
        
        return $vehiculo;
    }
    
    /**
     * Registra un nuevo vehículo
     */
    public function registrar($datos) {
        // Validaciones básicas
        $errores = $this->validarDatos($datos);
        
        if (!empty($errores)) {
            return [
                'error' => true, 
                'mensaje' => 'Existen errores en el formulario', 
                'errores' => $errores
            ];
        }
        
        // Normalizar datos
        $datos['placa'] = strtoupper(trim($datos['placa']));
        $datos['numero_movil'] = trim($datos['numero_movil']);
        $datos['estado'] = !empty($datos['estado']) ? $datos['estado'] : 'disponible';
        
        // Registrar vehículo
        return $this->vehiculoModel->registrar($datos);
    }
    
    /**
     * Actualiza un vehículo existente
     */
    public function actualizar($id, $datos) {
        // Validaciones básicas
        $errores = $this->validarDatos($datos);
        
        if (!empty($errores)) {
            return [
                'error' => true, 
                'mensaje' => 'Existen errores en el formulario', 
                'errores' => $errores
            ];
        }
        
        // Verificar si el vehículo existe
        $vehiculo = $this->vehiculoModel->obtenerPorId($id);
        if (!$vehiculo || isset($vehiculo['error'])) {
            return ['error' => true, 'mensaje' => 'Vehículo no encontrado'];
        }
        
        // Normalizar datos
        $datos['placa'] = strtoupper(trim($datos['placa']));
        $datos['numero_movil'] = trim($datos['numero_movil']);
        
        // Actualizar vehículo
        return $this->vehiculoModel->actualizar($id, $datos);
    }
    
    /**
     * Cambia el estado de un vehículo
     */
    public function cambiarEstado($id, $estado) {
        // Verificar si el vehículo existe
        $vehiculo = $this->vehiculoModel->obtenerPorId($id);
        if (!$vehiculo || isset($vehiculo['error'])) {
            return ['error' => true, 'mensaje' => 'Vehículo no encontrado'];
        }
        
        // Verificar si el estado es válido
        $estados_validos = ['disponible', 'en_servicio', 'mantenimiento', 'inactivo'];
        if (!in_array($estado, $estados_validos)) {
            return ['error' => true, 'mensaje' => 'Estado inválido'];
        }
        
        // Si el vehículo está sancionado, no permitir cambio a otro estado que no sea "sancionado"
        if ($vehiculo['estado'] === 'sancionado') {
            $sanciones = $this->sancionModel->obtenerPorVehiculo($id, 'activa');
            if (!empty($sanciones) && !isset($sanciones['error'])) {
                return [
                    'error' => true, 
                    'mensaje' => 'No se puede cambiar el estado del vehículo porque tiene una sanción activa'
                ];
            }
        }
        
        // Cambiar estado
        return $this->vehiculoModel->cambiarEstado($id, $estado);
    }
    
    /**
     * Elimina un vehículo (baja lógica)
     */
    public function eliminar($id) {
        // Verificar si el vehículo existe
        $vehiculo = $this->vehiculoModel->obtenerPorId($id);
        if (!$vehiculo || isset($vehiculo['error'])) {
            return ['error' => true, 'mensaje' => 'Vehículo no encontrado'];
        }
        
        // Verificar si tiene sanciones activas
        $sanciones = $this->sancionModel->obtenerPorVehiculo($id, 'activa');
        if (!empty($sanciones) && !isset($sanciones['error'])) {
            return [
                'error' => true, 
                'mensaje' => 'No se puede eliminar el vehículo porque tiene sanciones activas'
            ];
        }
        
        // Cambiar estado a inactivo (baja lógica)
        return $this->vehiculoModel->cambiarEstado($id, 'inactivo');
    }
    
    /**
     * Valida los datos de un vehículo
     */
    private function validarDatos($datos) {
        $errores = [];
        
        // Validar placa
        if (empty($datos['placa'])) {
            $errores['placa'] = 'La placa es requerida';
        } elseif (!preg_match('/^[A-Z0-9]{6,8}$/', strtoupper(trim($datos['placa'])))) {
            $errores['placa'] = 'Formato de placa inválido';
        }
                
        return $errores;
    }
    
    /**
     * Obtiene estadísticas de vehículos
     */
    public function obtenerEstadisticas() {
        return $this->vehiculoModel->obtenerEstadisticas();
    }
    
    /**
     * Verifica disponibilidad de un vehículo
     */
    public function verificarDisponibilidad($id) {
        // Verificar si el vehículo existe
        $vehiculo = $this->vehiculoModel->obtenerPorId($id);
        if (!$vehiculo || isset($vehiculo['error'])) {
            return ['disponible' => false, 'mensaje' => 'Vehículo no encontrado'];
        }
        
        // Verificar estado
        $disponible = $vehiculo['estado'] === 'disponible';
        
        return [
            'disponible' => $disponible,
            'estado' => $vehiculo['estado'],
            'mensaje' => $disponible ? 'Vehículo disponible' : 'Vehículo no disponible: ' . $this->traducirEstado($vehiculo['estado'])
        ];
    }
    
    /**
     * Traduce estados para mensajes amigables
     */
    private function traducirEstado($estado) {
        $estados = [
            'disponible' => 'Disponible',
            'sancionado' => 'Sancionado',
            'mantenimiento' => 'En mantenimiento',
            'inactivo' => 'Inactivo'
        ];
        
        return isset($estados[$estado]) ? $estados[$estado] : $estado;
    }
}
?>