<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Controllers\SancionController.php
require_once __DIR__ . '/../Models/SancionModel.php';
require_once __DIR__ . '/../Models/VehiculoModel.php';
require_once __DIR__ . '/../Models/ArticuloSancionModel.php';

class SancionController
{
    private $sancionModel;
    private $vehiculoModel;
    private $articuloModel;
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->sancionModel = new Sancion($pdo);
        $this->vehiculoModel = new Vehiculo($pdo);
        $this->articuloModel = new ArticuloSancion($pdo);
    }

    /**
     * Obtiene todas las sanciones según los filtros aplicados
     * 
     * @param array $filtros Filtros a aplicar
     * @return array Lista de sanciones
     */
    public function listar($filtros = [])
    {
        return $this->sancionModel->obtenerTodas($filtros);
    }

    /**
     * Obtiene una sanción por su ID
     * 
     * @param int $id ID de la sanción
     * @return array Datos de la sanción
     */
    public function obtener($id)
    {
        $sancion = $this->sancionModel->obtenerPorId($id);

        if (!$sancion || isset($sancion['error'])) {
            return ['error' => true, 'mensaje' => 'Sanción no encontrada'];
        }

        return $sancion;
    }

    /**
     * Aplica una sanción a un vehículo
     * 
     * @param array $datos Datos de la sanción
     * @return array Resultado de la operación
     */
    public function aplicar($datos)
    {
        // Asegurar que exista el ID de usuario
        if (!isset($datos['usuario_id']) && isset($_SESSION['usuario_id'])) {
            $datos['usuario_id'] = $_SESSION['usuario_id'];
        }

        // Validar datos obligatorios
        if (empty($datos['vehiculo_id']) || !is_numeric($datos['vehiculo_id'])) {
            return ['error' => true, 'mensaje' => 'ID de vehículo no válido'];
        }

        if (empty($datos['articulo_id']) || !is_numeric($datos['articulo_id'])) {
            return ['error' => true, 'mensaje' => 'ID de artículo no válido'];
        }

        if (empty($datos['motivo'])) {
            return ['error' => true, 'mensaje' => 'Debe proporcionar un motivo para la sanción'];
        }

        if (empty($datos['usuario_id']) || !is_numeric($datos['usuario_id'])) {
            return ['error' => true, 'mensaje' => 'ID de usuario no válido'];
        }

        // Verificar si el vehículo existe
        $vehiculo = $this->vehiculoModel->obtenerPorId($datos['vehiculo_id']);

        if (!$vehiculo || isset($vehiculo['error'])) {
            return ['error' => true, 'mensaje' => 'Vehículo no encontrado'];
        }

        // Verificar si el vehículo ya está sancionado
        if ($vehiculo['estado'] === 'sancionado') {
            return ['error' => true, 'mensaje' => 'El vehículo ya está sancionado'];
        }

        // Obtener información del artículo
        $articulo = $this->articuloModel->obtenerPorId($datos['articulo_id']);

        if (!$articulo || isset($articulo['error'])) {
            return ['error' => true, 'mensaje' => 'Artículo de sanción no encontrado'];
        }

        // Calcular fecha de finalización basada en el tiempo de sanción del artículo
        date_default_timezone_set('America/Bogota'); // Asegurar la zona horaria correcta
        $fecha_inicio = date('Y-m-d H:i:s');
        $fecha_fin = date('Y-m-d H:i:s', strtotime("+{$articulo['tiempo_sancion']} minutes"));

        // Registrar la sanción
        try {
            // Iniciar transacción
            $this->pdo->beginTransaction();

            // 1. Registrar la sanción
            $sql = "INSERT INTO sanciones (vehiculo_id, articulo_id, motivo, fecha_inicio, fecha_fin, estado, usuario_id) 
                    VALUES (:vehiculo_id, :articulo_id, :motivo, :fecha_inicio, :fecha_fin, 'activa', :usuario_id)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':vehiculo_id', $datos['vehiculo_id'], PDO::PARAM_INT);
            $stmt->bindParam(':articulo_id', $datos['articulo_id'], PDO::PARAM_INT);
            $stmt->bindParam(':motivo', $datos['motivo']);
            $stmt->bindParam(':fecha_inicio', $fecha_inicio);
            $stmt->bindParam(':fecha_fin', $fecha_fin);
            $stmt->bindParam(':usuario_id', $datos['usuario_id'], PDO::PARAM_INT);

            $stmt->execute();
            $sancion_id = $this->pdo->lastInsertId();

            // 2. Cambiar estado del vehículo a 'sancionado'
            $sqlVehiculo = "UPDATE vehiculos SET estado = 'sancionado' WHERE id = :id";
            $stmtVehiculo = $this->pdo->prepare($sqlVehiculo);
            $stmtVehiculo->bindParam(':id', $datos['vehiculo_id'], PDO::PARAM_INT);
            $stmtVehiculo->execute();

            // 3. Registrar el historial de la sanción
            $sqlHistorial = "INSERT INTO historial_sanciones (sancion_id, accion, comentario, usuario_id, fecha) 
                             VALUES (:sancion_id, 'aplicada', :comentario, :usuario_id, NOW())";

            $stmtHistorial = $this->pdo->prepare($sqlHistorial);
            $stmtHistorial->bindParam(':sancion_id', $sancion_id, PDO::PARAM_INT);
            $stmtHistorial->bindParam(':comentario', $datos['motivo']);
            $stmtHistorial->bindParam(':usuario_id', $datos['usuario_id'], PDO::PARAM_INT);
            $stmtHistorial->execute();

            // Confirmar transacción
            $this->pdo->commit();

            return [
                'error' => false,
                'mensaje' => 'Sanción aplicada correctamente',
                'id' => $sancion_id,
                'vehiculo_id' => $datos['vehiculo_id'],
                'fecha_fin' => $fecha_fin
            ];
        } catch (PDOException $e) {
            // Revertir transacción en caso de error
            $this->pdo->rollBack();
            return ['error' => true, 'mensaje' => 'Error al aplicar sanción: ' . $e->getMessage()];
        }
    }

    /**
     * Anula una sanción existente
     * 
     * @param int $id ID de la sanción
     * @param string $comentario Motivo de anulación
     * @return array Resultado de la operación
     */
    public function anular($id, $comentario)
    {
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

        if (isset($resultado['error']) && $resultado['error']) {
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
     * 
     * @return array Resultado de la operación
     */
    public function verificarVencimientos()
    {
        $resultado = $this->sancionModel->verificarSancionesVencidas();

        if (isset($resultado['error']) && $resultado['error']) {
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
     * 
     * @param int $vehiculo_id ID del vehículo
     * @return array Lista de sanciones activas
     */
    public function obtenerSancionesVehiculo($vehiculo_id)
    {
        return $this->sancionModel->obtenerPorVehiculo($vehiculo_id, 'activa');
    }

    /**
     * Verifica si un vehículo tiene sanciones activas
     * 
     * @param int $vehiculo_id ID del vehículo
     * @return bool True si tiene sanciones activas, False en caso contrario
     */
    public function vehiculoTieneSancionActiva($vehiculo_id)
    {
        $sanciones = $this->sancionModel->obtenerPorVehiculo($vehiculo_id, 'activa');

        if (isset($sanciones['error'])) {
            return false;
        }

        return count($sanciones) > 0;
    }

    /**
     * Obtiene el historial de sanciones de un vehículo
     * 
     * @param int $vehiculo_id ID del vehículo
     * @return array Historial de sanciones
     */
    public function obtenerHistorialVehiculo($vehiculo_id)
    {
        return $this->sancionModel->obtenerHistorialPorVehiculo($vehiculo_id);
    }

    /**
     * Obtiene estadísticas de sanciones
     * 
     * @param array $filtros Filtros a aplicar
     * @return array Estadísticas de sanciones
     */
    public function obtenerEstadisticas($filtros = [])
    {
        return $this->sancionModel->obtenerEstadisticas($filtros);
    }

    /**
     * Formatea un tiempo en minutos a un formato legible
     * 
     * @param int $minutos Tiempo en minutos
     * @return string Tiempo formateado
     */
    public function formatearTiempoMinutos($minutos)
    {
        $dias = floor($minutos / (24 * 60));
        $minutos = $minutos % (24 * 60);

        $horas = floor($minutos / 60);
        $minutosRestantes = $minutos % 60;

        $texto = '';

        if ($dias > 0) {
            $texto .= $dias . ' día' . ($dias > 1 ? 's' : '') . ' ';
        }

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
