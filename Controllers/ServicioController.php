<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Controllers\ServicioController.php
require_once __DIR__ . '/../Models/ServicioModel.php';
require_once __DIR__ . '/../Models/ClienteModel.php';
require_once __DIR__ . '/../Models/DireccionModel.php';
require_once __DIR__ . '/../Models/VehiculoModel.php';

class ServicioController
{
    private $servicioModel;
    private $clienteModel;
    private $direccionModel;
    private $vehiculoModel;
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->servicioModel = new Servicio($pdo);
        $this->clienteModel = new Cliente($pdo);
        $this->direccionModel = new Direccion($pdo);
        $this->vehiculoModel = new Vehiculo($pdo);
    }

    /**
     * Crea un nuevo servicio
     */
    public function crear($datos)
    {
        // Validar datos necesarios
        if (!isset($datos['cliente_id']) || !isset($datos['direccion_id'])) {
            return [
                'error' => true,
                'mensaje' => 'Cliente y dirección son obligatorios'
            ];
        }

        // Verificar que el cliente y la dirección existan
        $cliente = $this->clienteModel->obtenerPorId($datos['cliente_id']);
        if (!$cliente) {
            return [
                'error' => true,
                'mensaje' => 'Cliente no encontrado'
            ];
        }

        $direccion = $this->direccionModel->obtenerPorId($datos['direccion_id']);
        if (!$direccion) {
            return [
                'error' => true,
                'mensaje' => 'Dirección no encontrada'
            ];
        }

        // Crear el servicio
        try {
            $resultado = $this->servicioModel->crear($datos);

            // Asegurar que tenemos un formato de respuesta consistente
            if (isset($resultado['error']) && $resultado['error']) {
                return $resultado;
            }

            // Si la creación fue exitosa pero no tenemos formato estándar
            if (!isset($resultado['error'])) {
                return [
                    'error' => false,
                    'mensaje' => 'Servicio creado correctamente',
                    'id' => isset($resultado['id']) ? $resultado['id'] : (is_numeric($resultado) ? $resultado : null)
                ];
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("Error al crear servicio: " . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => 'Error al crear servicio: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Asigna un vehículo a un servicio
     */
    public function asignar($servicio_id, $vehiculo_id)
    {
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
     * Cambia el vehículo asignado a un servicio
     */
    public function cambiarVehiculo($servicio_id, $nuevo_vehiculo_id)
    {
        // Validar datos
        if (!$servicio_id || !$nuevo_vehiculo_id) {
            return [
                'error' => true,
                'mensaje' => 'ID de servicio o ID de vehículo no proporcionados'
            ];
        }

        // Verificar que el servicio existe y está en estado asignado
        $servicio = $this->servicioModel->obtenerPorId($servicio_id);
        if (!$servicio) {
            return [
                'error' => true,
                'mensaje' => 'Servicio no encontrado'
            ];
        }

        if ($servicio['estado'] !== 'asignado' && $servicio['estado'] !== 'en_camino') {
            return [
                'error' => true,
                'mensaje' => 'Solo se puede cambiar el vehículo de servicios en estado "asignado" o "en camino"'
            ];
        }

        // Verificar que el nuevo vehículo existe y está disponible
        $nuevoVehiculo = $this->vehiculoModel->obtenerPorId($nuevo_vehiculo_id);
        if (!$nuevoVehiculo || (isset($nuevoVehiculo['error']) && $nuevoVehiculo['error'])) {
            return [
                'error' => true,
                'mensaje' => 'Vehículo no encontrado'
            ];
        }

        if ($nuevoVehiculo['estado'] !== 'disponible') {
            return [
                'error' => true,
                'mensaje' => 'El vehículo seleccionado no está disponible'
            ];
        }

        // Obtener el vehículo actual
        $vehiculo_actual_id = $servicio['vehiculo_id'];

        // 1. Actualizar el servicio con el nuevo vehículo
        $datos_servicio = [
            'vehiculo_id' => $nuevo_vehiculo_id,
            'fecha_asignacion' => date('Y-m-d H:i:s')
        ];

        $resultado_servicio = $this->servicioModel->actualizar($servicio_id, $datos_servicio);
        if (isset($resultado_servicio['error']) && $resultado_servicio['error']) {
            return [
                'error' => true,
                'mensaje' => 'Error al actualizar servicio: ' . $resultado_servicio['mensaje']
            ];
        }

        // 2. Actualizar estado del nuevo vehículo a ocupado
        $resultado_ocupar = $this->vehiculoModel->cambiarEstado($nuevo_vehiculo_id, 'ocupado');
        if (isset($resultado_ocupar['error']) && $resultado_ocupar['error']) {
            // Si falla, intentamos revertir el cambio en el servicio
            $this->servicioModel->actualizar($servicio_id, [
                'vehiculo_id' => $vehiculo_actual_id,
                'fecha_actualizacion' => date('Y-m-d H:i:s')
            ]);

            return [
                'error' => true,
                'mensaje' => 'Error al ocupar nuevo vehículo: ' . $resultado_ocupar['mensaje']
            ];
        }

        // 3. Liberar el vehículo anterior (si existe)
        if ($vehiculo_actual_id) {
            $resultado_liberar = $this->vehiculoModel->cambiarEstado($vehiculo_actual_id, 'disponible');
            if (isset($resultado_liberar['error']) && $resultado_liberar['error']) {
                // No revertimos los cambios anteriores porque el servicio ya tiene un nuevo vehículo asignado que funciona
                error_log("Error al liberar vehículo anterior: " . $resultado_liberar['mensaje']);
                // Continuamos con el proceso, no es crítico
            }
        }

        return [
            'error' => false,
            'mensaje' => 'Vehículo cambiado correctamente'
        ];
    }


    /**
     * Actualiza el estado del servicio a 'en_camino'
     */
    public function enCamino($servicio_id)
    {
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
    public function finalizar($servicio_id)
    {
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
    public function cancelar($servicio_id)
    {
        // Validar datos
        if (!$servicio_id) {
            return ['error' => true, 'mensaje' => 'ID de servicio no proporcionado'];
        }

        // Verificar que el servicio existe y está pendiente
        $servicio = $this->servicioModel->obtenerPorId($servicio_id);
        if (!$servicio) {
            return ['error' => true, 'mensaje' => 'Servicio no encontrado'];
        }

        // Actualizar el servicio
        $datos_servicio = [
            'estado' => 'cancelado',
            'fecha_fin' => date('Y-m-d H:i:s')
        ];

        // Liberar el vehículo
        $resultado_vehiculo = $this->vehiculoModel->cambiarEstado($servicio['vehiculo_id'], 'disponible');
        if (isset($resultado_vehiculo['error']) && $resultado_vehiculo['error']) {
            return $resultado_vehiculo;
        }

        return $this->servicioModel->actualizar($servicio_id, $datos_servicio);
    }

    /**
     * Lista los servicios por estado
     */
    public function listarPorEstado($estado)
    {
        // Validar estado
        $estados_validos = ['pendiente', 'asignado', 'en_camino', 'finalizado', 'cancelado'];
        if (!in_array($estado, $estados_validos)) {
            return ['error' => true, 'mensaje' => 'Estado no válido'];
        }

        return $this->servicioModel->listarPorEstado($estado);
    }

    /**
     * Lista todos los servicios activos (no finalizados ni cancelados)
     */
    public function listarServiciosActivos()
    {
        return $this->servicioModel->listarServiciosActivos();
    }

    /**
     * Calcula el tiempo transcurrido desde una fecha hasta ahora
     */
    public function calcularTiempoTranscurrido($fecha_inicio)
    {
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

    /**
     * Cuenta la cantidad de servicios de un cliente en un mes específico
     */
    public function contarPorClienteMes($cliente_id, $mes, $anio)
    {
        if (!$cliente_id || !$mes || !$anio) {
            return 0;
        }

        try {
            $inicio_mes = sprintf('%d-%02d-01 00:00:00', $anio, $mes);
            $ultimo_dia = date('t', strtotime($inicio_mes)); // Obtiene el último día del mes
            $fin_mes = sprintf('%d-%02d-%d 23:59:59', $anio, $mes, $ultimo_dia);

            $sql = "
            SELECT COUNT(*) as total 
            FROM servicios 
            WHERE cliente_id = :cliente_id 
            AND fecha_solicitud BETWEEN :inicio_mes AND :fin_mes
        ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            $stmt->bindParam(':inicio_mes', $inicio_mes, PDO::PARAM_STR);
            $stmt->bindParam(':fin_mes', $fin_mes, PDO::PARAM_STR);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return intval($resultado['total']);
        } catch (PDOException $e) {
            error_log('Error al contar servicios por mes: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Cuenta el total de servicios de un cliente
     */
    public function contarPorCliente($cliente_id)
    {
        if (!$cliente_id) {
            return 0;
        }

        try {
            $sql = "SELECT COUNT(*) as total FROM servicios WHERE cliente_id = :cliente_id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return intval($resultado['total']);
        } catch (PDOException $e) {
            error_log('Error al contar servicios totales: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtiene el historial de servicios de un cliente con paginación
     */
    public function obtenerHistorialCliente($cliente_id, $limite = 10, $offset = 0)
    {
        if (!$cliente_id) {
            return ['error' => true, 'mensaje' => 'ID de cliente no proporcionado'];
        }

        try {
            // Consulta para obtener los servicios
            $sql = "
            SELECT s.*, 
                   d.direccion,
                   v.numero_movil, 
                   v.placa
            FROM servicios s
            LEFT JOIN direcciones d ON s.direccion_id = d.id
            LEFT JOIN vehiculos v ON s.vehiculo_id = v.id
            WHERE s.cliente_id = :cliente_id
            ORDER BY s.fecha_solicitud DESC
            LIMIT :limite OFFSET :offset
        ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Consulta para contar el total de servicios
            $sql_count = "SELECT COUNT(*) as total FROM servicios WHERE cliente_id = :cliente_id";

            $stmt_count = $this->pdo->prepare($sql_count);
            $stmt_count->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            $stmt_count->execute();

            $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];

            return [
                'servicios' => $servicios,
                'total' => $total
            ];
        } catch (PDOException $e) {
            return [
                'error' => true,
                'mensaje' => 'Error al obtener historial de servicios: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene estadísticas de servicios de un cliente
     */
    public function obtenerEstadisticasCliente($cliente_id)
    {
        if (!$cliente_id) {
            return [
                'total' => 0,
                'finalizados' => 0,
                'cancelados' => 0,
                'frecuencia_mensual' => 0,
                'ultima_solicitud' => null
            ];
        }

        try {
            $sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) as finalizados,
                SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
                MAX(fecha_solicitud) as ultima_solicitud
            FROM servicios
            WHERE cliente_id = :cliente_id
        ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            // Calcular frecuencia mensual (promedio servicios por mes)
            $sql_primer_servicio = "
            SELECT MIN(fecha_solicitud) as primer_servicio
            FROM servicios
            WHERE cliente_id = :cliente_id
        ";

            $stmt_primer = $this->pdo->prepare($sql_primer_servicio);
            $stmt_primer->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            $stmt_primer->execute();

            $primer_servicio = $stmt_primer->fetch(PDO::FETCH_ASSOC)['primer_servicio'];

            if ($primer_servicio) {
                $fecha_inicio = new DateTime($primer_servicio);
                $fecha_actual = new DateTime();

                $diff = $fecha_inicio->diff($fecha_actual);
                $meses = ($diff->y * 12) + $diff->m;

                if ($meses > 0) {
                    $resultado['frecuencia_mensual'] = round($resultado['total'] / $meses, 1);
                } else {
                    $resultado['frecuencia_mensual'] = $resultado['total']; // Todo en el mismo mes
                }
            } else {
                $resultado['frecuencia_mensual'] = 0;
            }

            return $resultado;
        } catch (PDOException $e) {
            error_log('Error al obtener estadísticas del cliente: ' . $e->getMessage());
            return [
                'total' => 0,
                'finalizados' => 0,
                'cancelados' => 0,
                'frecuencia_mensual' => 0,
                'ultima_solicitud' => null
            ];
        }
    }
}
