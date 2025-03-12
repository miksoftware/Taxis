<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Controllers\ServicioController.php
require_once __DIR__ . '/../Models/ServicioModel.php';
require_once __DIR__ . '/../Models/ClienteModel.php';
require_once __DIR__ . '/../Models/DireccionModel.php';
require_once __DIR__ . '/../Models/VehiculoModel.php';

class ServicioController {
    private $servicioModel;
    private $clienteModel;
    private $direccionModel;
    private $vehiculoModel;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->servicioModel = new Servicio($pdo);
        $this->clienteModel = new Cliente($pdo);
        $this->direccionModel = new Direccion($pdo);
        $this->vehiculoModel = new Vehiculo($pdo);
    }

    /**
     * Crea un nuevo servicio
     */
    public function crear($datos) {
        // Validar datos necesarios
        if (!isset($datos['cliente_id']) || !isset($datos['direccion_id']) || !isset($datos['condicion'])) {
            return ['error' => true, 'mensaje' => 'Datos incompletos'];
        }
    
        // Verificar que el cliente y la dirección existen
        $cliente = $this->clienteModel->obtenerPorId($datos['cliente_id']);
        if (!$cliente) {
            return ['error' => true, 'mensaje' => 'Cliente no encontrado'];
        }
    
        $direccion = $this->direccionModel->obtenerPorId($datos['direccion_id']);
        if (!$direccion) {
            return ['error' => true, 'mensaje' => 'Dirección no encontrada'];
        }
    
        // Crear el servicio
        $servicio = [
            'cliente_id' => $datos['cliente_id'],
            'direccion_id' => $datos['direccion_id'],
            'condicion' => $datos['condicion'],
            'observaciones' => isset($datos['observaciones']) ? $datos['observaciones'] : '',
            'estado' => 'pendiente',
            'fecha_solicitud' => date('Y-m-d H:i:s'),
            'operador_id' => isset($datos['operador_id']) ? $datos['operador_id'] : $_SESSION['usuario_id']
        ];
    
        return $this->servicioModel->crear($servicio);
    }

    /**
     * Asigna un vehículo a un servicio
     */
    public function asignar($servicio_id, $vehiculo_id) {
        // Validar datos
        if (!$servicio_id || !$vehiculo_id) {
            return ['error' => true, 'mensaje' => 'Datos incompletos'];
        }

        // Verificar que el servicio existe y está pendiente
        $servicio = $this->servicioModel->obtenerPorId($servicio_id);
        if (!$servicio) {
            return ['error' => true, 'mensaje' => 'Servicio no encontrado'];
        }

        if ($servicio['estado'] !== 'pendiente') {
            return ['error' => true, 'mensaje' => 'El servicio no está en estado pendiente'];
        }

        // Verificar que el vehículo existe y está disponible
        $vehiculo = $this->vehiculoModel->obtenerPorId($vehiculo_id);
        if (!$vehiculo) {
            return ['error' => true, 'mensaje' => 'Vehículo no encontrado'];
        }

        if ($vehiculo['estado'] !== 'disponible') {
            return ['error' => true, 'mensaje' => 'El vehículo no está disponible'];
        }

        // Actualizar el estado del servicio a 'asignado'
        $datos_servicio = [
            'vehiculo_id' => $vehiculo_id,
            'estado' => 'asignado',
            'fecha_asignacion' => date('Y-m-d H:i:s')
        ];

        $resultado_servicio = $this->servicioModel->actualizar($servicio_id, $datos_servicio);
        if (isset($resultado_servicio['error']) && $resultado_servicio['error']) {
            return $resultado_servicio;
        }

        // Actualizar el estado del vehículo a 'ocupado'
        $resultado_vehiculo = $this->vehiculoModel->cambiarEstado($vehiculo_id, 'ocupado');
        if (isset($resultado_vehiculo['error']) && $resultado_vehiculo['error']) {
            // Revertir cambios en el servicio si falla el cambio en el vehículo
            $this->servicioModel->actualizar($servicio_id, [
                'vehiculo_id' => null,
                'estado' => 'pendiente',
                'fecha_asignacion' => null
            ]);
            return $resultado_vehiculo;
        }

        return ['error' => false, 'mensaje' => 'Servicio asignado correctamente'];
    }

    /**
     * Actualiza el estado del servicio a 'en_camino'
     */
    public function enCamino($servicio_id) {
        // Validar datos
        if (!$servicio_id) {
            return ['error' => true, 'mensaje' => 'ID de servicio no proporcionado'];
        }

        // Verificar que el servicio existe y está asignado
        $servicio = $this->servicioModel->obtenerPorId($servicio_id);
        if (!$servicio) {
            return ['error' => true, 'mensaje' => 'Servicio no encontrado'];
        }

        if ($servicio['estado'] !== 'asignado') {
            return ['error' => true, 'mensaje' => 'El servicio no está en estado asignado'];
        }

        // Actualizar estado
        $resultado = $this->servicioModel->actualizar($servicio_id, [
            'estado' => 'en_camino'
        ]);

        return $resultado;
    }

    /**
     * Finaliza un servicio
     */
    public function finalizar($servicio_id) {
        // Validar datos
        if (!$servicio_id) {
            return ['error' => true, 'mensaje' => 'ID de servicio no proporcionado'];
        }

        // Verificar que el servicio existe y está asignado o en camino
        $servicio = $this->servicioModel->obtenerPorId($servicio_id);
        if (!$servicio) {
            return ['error' => true, 'mensaje' => 'Servicio no encontrado'];
        }

        if ($servicio['estado'] !== 'asignado' && $servicio['estado'] !== 'en_camino') {
            return ['error' => true, 'mensaje' => 'El servicio no está en un estado que se pueda finalizar'];
        }

        // Actualizar el servicio
        $datos_servicio = [
            'estado' => 'finalizado',
            'fecha_fin' => date('Y-m-d H:i:s')
        ];

        $resultado_servicio = $this->servicioModel->actualizar($servicio_id, $datos_servicio);
        if (isset($resultado_servicio['error']) && $resultado_servicio['error']) {
            return $resultado_servicio;
        }

        // Liberar el vehículo
        $resultado_vehiculo = $this->vehiculoModel->cambiarEstado($servicio['vehiculo_id'], 'disponible');
        if (isset($resultado_vehiculo['error']) && $resultado_vehiculo['error']) {
            return $resultado_vehiculo;
        }

        return ['error' => false, 'mensaje' => 'Servicio finalizado correctamente'];
    }

    /**
     * Cancela un servicio pendiente
     */
    public function cancelar($servicio_id) {
        // Validar datos
        if (!$servicio_id) {
            return ['error' => true, 'mensaje' => 'ID de servicio no proporcionado'];
        }

        // Verificar que el servicio existe y está pendiente
        $servicio = $this->servicioModel->obtenerPorId($servicio_id);
        if (!$servicio) {
            return ['error' => true, 'mensaje' => 'Servicio no encontrado'];
        }

        if ($servicio['estado'] !== 'pendiente') {
            return ['error' => true, 'mensaje' => 'Solo se pueden cancelar servicios pendientes'];
        }

        // Actualizar el servicio
        $datos_servicio = [
            'estado' => 'cancelado',
            'fecha_fin' => date('Y-m-d H:i:s')
        ];

        return $this->servicioModel->actualizar($servicio_id, $datos_servicio);
    }

    /**
     * Lista los servicios por estado
     */
    public function listarPorEstado($estado) {
        // Validar estado
        $estados_validos = ['pendiente', 'asignado', 'en_camino', 'finalizado', 'cancelado'];
        if (!in_array($estado, $estados_validos)) {
            return ['error' => true, 'mensaje' => 'Estado no válido'];
        }

        return $this->servicioModel->listarPorEstado($estado);
    }

    /**
     * Calcula el tiempo transcurrido desde una fecha hasta ahora
     */
    public function calcularTiempoTranscurrido($fecha_inicio) {
        $inicio = new DateTime($fecha_inicio);
        $ahora = new DateTime();
        
        $diff = $ahora->diff($inicio);
        $minutos = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
        
        if ($minutos < 60) {
            return $minutos . ' min';
        } else {
            $horas = floor($minutos / 60);
            $min_restantes = $minutos % 60;
            return $horas . 'h ' . $min_restantes . 'm';
        }
    }

    
}