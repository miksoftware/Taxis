<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Models\SancionModel.php
class Sancion
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Obtiene todas las sanciones
     * 
     * @param array $filtros Filtros para la consulta
     * @return array Lista de sanciones
     */
    public function obtenerTodas($filtros = [])
    {
        try {
            $sql = "SELECT s.*, 
                           v.placa as vehiculo_placa, 
                           v.numero_movil as vehiculo_movil,
                           a.codigo as articulo_codigo, 
                           a.descripcion as articulo_descripcion,
                           a.tiempo_sancion as tiempo_sancion,
                           CONCAT(u.nombre, ' ', u.apellidos) as usuario_nombre
                    FROM sanciones s
                    INNER JOIN vehiculos v ON s.vehiculo_id = v.id
                    INNER JOIN articulos_sancion a ON s.articulo_id = a.id
                    INNER JOIN usuarios u ON s.usuario_id = u.id
                    WHERE 1=1";

            $params = [];

            // Aplicar filtros
            if (!empty($filtros['estado'])) {
                $sql .= " AND s.estado = :estado";
                $params[':estado'] = $filtros['estado'];
            }

            if (!empty($filtros['vehiculo_id'])) {
                $sql .= " AND s.vehiculo_id = :vehiculo_id";
                $params[':vehiculo_id'] = $filtros['vehiculo_id'];
            }

            if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
                $sql .= " AND s.fecha_inicio BETWEEN :fecha_inicio AND :fecha_fin";
                $params[':fecha_inicio'] = $filtros['fecha_inicio'];
                $params[':fecha_fin'] = $filtros['fecha_fin'];
            }

            $sql .= " ORDER BY s.fecha_inicio DESC";

            // Si hay límite y offset
            if (!empty($filtros['limite'])) {
                $sql .= " LIMIT :limite";
                $params[':limite'] = (int)$filtros['limite'];

                if (!empty($filtros['offset'])) {
                    $sql .= " OFFSET :offset";
                    $params[':offset'] = (int)$filtros['offset'];
                }
            }

            $stmt = $this->pdo->prepare($sql);

            // Bindear parámetros
            foreach ($params as $key => $value) {
                if (strpos($key, 'limite') !== false || strpos($key, 'offset') !== false) {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value);
                }
            }

            $stmt->execute();
            $sanciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular tiempo restante para sanciones activas
            $ahora = new DateTime();

            foreach ($sanciones as &$sancion) {
                if ($sancion['estado'] === 'activa') {
                    $fin = new DateTime($sancion['fecha_fin']);

                    if ($fin > $ahora) {
                        $intervalo = $ahora->diff($fin);
                        $sancion['tiempo_restante'] = $this->formatearTiempoRestante($intervalo);
                        $sancion['segundos_restantes'] = $fin->getTimestamp() - $ahora->getTimestamp();
                    } else {
                        $sancion['tiempo_restante'] = 'Vencida';
                        $sancion['segundos_restantes'] = 0;
                    }
                } else {
                    $sancion['tiempo_restante'] = 'N/A';
                    $sancion['segundos_restantes'] = 0;
                }
            }

            return $sanciones;
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al obtener sanciones: ' . $e->getMessage()];
        }
    }

    /**
     * Obtiene una sanción por su ID
     * 
     * @param int $id ID de la sanción
     * @return array Datos de la sanción
     */
    public function obtenerPorId($id)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.*, 
                       v.placa as vehiculo_placa, 
                       v.numero_movil as vehiculo_movil,
                       a.codigo as articulo_codigo, 
                       a.descripcion as articulo_descripcion,
                       a.tiempo_sancion as tiempo_sancion,
                       CONCAT(u.nombre, ' ', u.apellidos) as usuario_nombre
                FROM sanciones s
                INNER JOIN vehiculos v ON s.vehiculo_id = v.id
                INNER JOIN articulos_sancion a ON s.articulo_id = a.id
                INNER JOIN usuarios u ON s.usuario_id = u.id
                WHERE s.id = :id
            ");

            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado) {
                // Calcular tiempo restante si la sanción está activa
                if ($resultado['estado'] === 'activa') {
                    $ahora = new DateTime();
                    $fin = new DateTime($resultado['fecha_fin']);

                    if ($fin > $ahora) {
                        $intervalo = $ahora->diff($fin);
                        $resultado['tiempo_restante'] = $this->formatearTiempoRestante($intervalo);
                        $resultado['segundos_restantes'] = $fin->getTimestamp() - $ahora->getTimestamp();
                    } else {
                        $resultado['tiempo_restante'] = 'Vencida';
                        $resultado['segundos_restantes'] = 0;
                    }
                } else {
                    $resultado['tiempo_restante'] = 'N/A';
                    $resultado['segundos_restantes'] = 0;
                }

                // Obtener historial relacionado
                $stmt = $this->pdo->prepare("
                    SELECT h.*,
                           CONCAT(u.nombre, ' ', u.apellidos) as usuario_nombre,
                           DATE_FORMAT(h.fecha, '%d/%m/%Y %H:%i:%s') as fecha_formateada
                    FROM historial_sanciones h
                    INNER JOIN usuarios u ON h.usuario_id = u.id
                    WHERE h.sancion_id = :sancion_id
                    ORDER BY h.fecha DESC
                ");
                $stmt->bindParam(':sancion_id', $id, PDO::PARAM_INT);
                $stmt->execute();

                $resultado['historial'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return $resultado;
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al obtener sanción: ' . $e->getMessage()];
        }
    }

    /**
     * Aplica una sanción a un vehículo
     * 
     * @param array $datos Datos de la sanción
     * @return array Resultado de la operación
     */
    public function aplicar($datos)
    {
        try {
            $this->pdo->beginTransaction();

            // Insertar la sanción
            $sql = "INSERT INTO sanciones (vehiculo_id, articulo_id, fecha_inicio, fecha_fin, estado, usuario_id, motivo)
                    VALUES (:vehiculo_id, :articulo_id, :fecha_inicio, :fecha_fin, 'activa', :usuario_id, :motivo)";

            $stmt = $this->pdo->prepare($sql);

            $fecha_inicio = date('Y-m-d H:i:s');

            // Obtener el tiempo de sanción del artículo
            $stmtArticulo = $this->pdo->prepare("SELECT tiempo_sancion FROM articulos_sancion WHERE id = :id");
            $stmtArticulo->bindParam(':id', $datos['articulo_id'], PDO::PARAM_INT);
            $stmtArticulo->execute();
            $articulo = $stmtArticulo->fetch(PDO::FETCH_ASSOC);

            if (!$articulo) {
                $this->pdo->rollBack();
                return ['error' => true, 'mensaje' => 'Artículo de sanción no encontrado'];
            }


            // Calcular fecha de finalización
            date_default_timezone_set('America/Bogota'); // Asegurar la zona horaria correcta
            $fecha_inicio = date('Y-m-d H:i:s');
            $fecha_fin = date('Y-m-d H:i:s', strtotime("+{$articulo['tiempo_sancion']} minutes"));

            $stmt->bindParam(':vehiculo_id', $datos['vehiculo_id'], PDO::PARAM_INT);
            $stmt->bindParam(':articulo_id', $datos['articulo_id'], PDO::PARAM_INT);
            $stmt->bindParam(':fecha_inicio', $fecha_inicio);
            $stmt->bindParam(':fecha_fin', $fecha_fin);
            $stmt->bindParam(':usuario_id', $datos['usuario_id'], PDO::PARAM_INT);
            $stmt->bindParam(':motivo', $datos['motivo']);

            $stmt->execute();
            $sancion_id = $this->pdo->lastInsertId();

            // Cambiar estado del vehículo a "sancionado"
            $sqlVehiculo = "UPDATE vehiculos SET estado = 'sancionado' WHERE id = :id";
            $stmtVehiculo = $this->pdo->prepare($sqlVehiculo);
            $stmtVehiculo->bindParam(':id', $datos['vehiculo_id'], PDO::PARAM_INT);
            $stmtVehiculo->execute();

            // Registrar en historial
            $sql = "INSERT INTO historial_sanciones (sancion_id, accion, usuario_id, comentario, fecha)
                    VALUES (:sancion_id, 'aplicada', :usuario_id, :comentario, NOW())";

            $stmt = $this->pdo->prepare($sql);

            $stmt->bindParam(':sancion_id', $sancion_id, PDO::PARAM_INT);
            $stmt->bindParam(':usuario_id', $datos['usuario_id'], PDO::PARAM_INT);
            $stmt->bindParam(':comentario', $datos['motivo']);

            $stmt->execute();

            $this->pdo->commit();

            return [
                'error' => false,
                'mensaje' => 'Sanción aplicada correctamente',
                'sancion_id' => $sancion_id,
                'fecha_fin' => $fecha_fin
            ];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['error' => true, 'mensaje' => 'Error al aplicar sanción: ' . $e->getMessage()];
        }
    }

    /**
     * Cambia el estado de una sanción
     * 
     * @param int $id ID de la sanción
     * @param string $estado Nuevo estado
     * @return array Resultado de la operación
     */
    public function cambiarEstado($id, $estado)
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE sanciones SET estado = :estado WHERE id = :id");

            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':estado', $estado);

            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return ['error' => false, 'mensaje' => 'Estado de sanción actualizado correctamente'];
            } else {
                return ['error' => true, 'mensaje' => 'No se pudo actualizar el estado de la sanción'];
            }
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al cambiar estado de sanción: ' . $e->getMessage()];
        }
    }

    /**
     * Registra una entrada en el historial de sanciones
     * 
     * @param int $sancion_id ID de la sanción
     * @param string $accion Acción realizada
     * @param int $usuario_id ID del usuario que realiza la acción
     * @param string $comentario Comentario opcional
     * @return array Resultado de la operación
     */
    public function registrarHistorial($sancion_id, $accion, $usuario_id, $comentario)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO historial_sanciones (sancion_id, accion, usuario_id, comentario, fecha)
                VALUES (:sancion_id, :accion, :usuario_id, :comentario, NOW())
            ");

            $stmt->bindParam(':sancion_id', $sancion_id, PDO::PARAM_INT);
            $stmt->bindParam(':accion', $accion);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->bindParam(':comentario', $comentario);

            $stmt->execute();

            return ['error' => false, 'mensaje' => 'Historial registrado correctamente'];
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al registrar historial: ' . $e->getMessage()];
        }
    }

    /**
     * Obtiene sanciones por vehículo y estado opcional
     * 
     * @param int $vehiculo_id ID del vehículo
     * @param string $estado Estado opcional para filtrar
     * @return array Lista de sanciones
     */
    public function obtenerPorVehiculo($vehiculo_id, $estado = null)
    {
        try {
            $sql = "SELECT s.*, 
                           a.codigo as articulo_codigo,
                           a.descripcion as articulo_descripcion,
                           a.tiempo_sancion as tiempo_sancion
                    FROM sanciones s
                    INNER JOIN articulos_sancion a ON s.articulo_id = a.id
                    WHERE s.vehiculo_id = :vehiculo_id";

            if ($estado !== null) {
                $sql .= " AND s.estado = :estado";
            }

            $sql .= " ORDER BY s.fecha_inicio DESC";

            $stmt = $this->pdo->prepare($sql);

            $stmt->bindParam(':vehiculo_id', $vehiculo_id, PDO::PARAM_INT);

            if ($estado !== null) {
                $stmt->bindParam(':estado', $estado);
            }

            $stmt->execute();

            $sanciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular tiempo restante para sanciones activas
            $ahora = new DateTime();

            foreach ($sanciones as &$sancion) {
                if ($sancion['estado'] === 'activa') {
                    $fin = new DateTime($sancion['fecha_fin']);

                    if ($fin > $ahora) {
                        $intervalo = $ahora->diff($fin);
                        $sancion['tiempo_restante'] = $this->formatearTiempoRestante($intervalo);
                        $sancion['segundos_restantes'] = $fin->getTimestamp() - $ahora->getTimestamp();
                    } else {
                        $sancion['tiempo_restante'] = 'Vencida';
                        $sancion['segundos_restantes'] = 0;
                    }
                }
            }

            return $sanciones;
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al obtener sanciones por vehículo: ' . $e->getMessage()];
        }
    }

    /**
     * Obtiene el historial completo de sanciones por vehículo
     * 
     * @param int $vehiculo_id ID del vehículo
     * @return array Historial de sanciones
     */
    public function obtenerHistorialPorVehiculo($vehiculo_id)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.*,
                       a.codigo as articulo_codigo,
                       a.descripcion as articulo_descripcion,
                       CONCAT(u.nombre, ' ', u.apellidos) as usuario_nombre,
                       TIMEDIFF(s.fecha_fin, s.fecha_inicio) as duracion
                FROM sanciones s
                INNER JOIN articulos_sancion a ON s.articulo_id = a.id
                INNER JOIN usuarios u ON s.usuario_id = u.id
                WHERE s.vehiculo_id = :vehiculo_id
                ORDER BY s.fecha_inicio DESC
            ");

            $stmt->bindParam(':vehiculo_id', $vehiculo_id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al obtener historial por vehículo: ' . $e->getMessage()];
        }
    }

    /**
     * Verifica y actualiza las sanciones vencidas
     * 
     * @return array Resultado de la operación
     */
    public function verificarSancionesVencidas()
    {
        try {
            $this->pdo->beginTransaction();

            // Crear un archivo de depuración
            $log_file = dirname(dirname(__FILE__)) . '/logs/sancion_verificacion.log';
            $debug_info = date('Y-m-d H:i:s') . " - Iniciando verificación de sanciones vencidas\n";
            file_put_contents($log_file, $debug_info, FILE_APPEND);

            // Obtener sanciones activas vencidas - Añadimos LIMIT para depuración
            $sql = "SELECT s.id, s.vehiculo_id, s.fecha_fin 
                    FROM sanciones s 
                    WHERE s.estado = 'activa' AND s.fecha_fin < NOW() 
                    LIMIT 10"; // Limitamos a 10 para depuración

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $sanciones_vencidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $debug_info = date('Y-m-d H:i:s') . " - Encontradas " . count($sanciones_vencidas) . " sanciones vencidas\n";
            file_put_contents($log_file, $debug_info, FILE_APPEND);

            foreach ($sanciones_vencidas as $sancion) {
                $debug_info = date('Y-m-d H:i:s') . " - Procesando sanción ID: " . $sancion['id'] .
                    " | Vehículo ID: " . $sancion['vehiculo_id'] .
                    " | Fecha fin: " . $sancion['fecha_fin'] . "\n";
                file_put_contents($log_file, $debug_info, FILE_APPEND);

                // Actualizar estado de la sanción
                $stmt = $this->pdo->prepare("UPDATE sanciones SET estado = 'cumplida' WHERE id = :id");
                $stmt->bindParam(':id', $sancion['id'], PDO::PARAM_INT);
                $stmt->execute();

                // Actualizar estado del vehículo
                $stmt = $this->pdo->prepare("UPDATE vehiculos SET estado = 'disponible' WHERE id = :id");
                $stmt->bindParam(':id', $sancion['vehiculo_id'], PDO::PARAM_INT);
                $stmt->execute();

                // Registrar en historial
                $sql = "INSERT INTO historial_sanciones (sancion_id, accion, usuario_id, comentario, fecha) 
                        VALUES (:sancion_id, 'cumplida', 1, 'Sanción cumplida automáticamente', NOW())";
                $stmt = $this->pdo->prepare($sql);

                $stmt->bindParam(':sancion_id', $sancion['id'], PDO::PARAM_INT);
                $stmt->execute();

                $debug_info = date('Y-m-d H:i:s') . " - Sanción ID " . $sancion['id'] . " actualizada correctamente\n";
                file_put_contents($log_file, $debug_info, FILE_APPEND);
            }

            $this->pdo->commit();

            $debug_info = date('Y-m-d H:i:s') . " - Verificación completada\n\n";
            file_put_contents($log_file, $debug_info, FILE_APPEND);

            return [
                'error' => false,
                'mensaje' => 'Sanciones actualizadas correctamente',
                'actualizadas' => count($sanciones_vencidas)
            ];
        } catch (PDOException $e) {
            $this->pdo->rollBack();

            $debug_info = date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n\n";
            file_put_contents($log_file, $debug_info, FILE_APPEND);

            return ['error' => true, 'mensaje' => 'Error al verificar sanciones vencidas: ' . $e->getMessage()];
        }
    }

    /**
     * Formatea el tiempo restante de una sanción
     * 
     * @param DateInterval $intervalo Intervalo de tiempo
     * @return string Tiempo formateado
     */
    private function formatearTiempoRestante($intervalo)
    {
        $formato = '';

        if ($intervalo->d > 0) {
            $formato .= $intervalo->d . 'd ';
        }

        $formato .= sprintf('%02d:%02d:%02d', $intervalo->h, $intervalo->i, $intervalo->s);

        return $formato;
    }

    /**
     * Obtiene estadísticas de sanciones
     * 
     * @param array $filtros Filtros para la consulta
     * @return array Estadísticas de sanciones
     */
    public function obtenerEstadisticas($filtros = [])
    {
        try {
            $condiciones = '';
            $params = [];

            if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
                $condiciones .= " AND s.fecha_inicio BETWEEN :fecha_inicio AND :fecha_fin";
                $params[':fecha_inicio'] = $filtros['fecha_inicio'];
                $params[':fecha_fin'] = $filtros['fecha_fin'];
            }

            // Total de sanciones por estado
            $sql = "
                SELECT s.estado, COUNT(*) as total
                FROM sanciones s
                WHERE 1=1 $condiciones
                GROUP BY s.estado
            ";

            $stmt = $this->pdo->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();
            $estadisticas_estado = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formatear resultados
            $estados = ['activa' => 0, 'cumplida' => 0, 'anulada' => 0];

            foreach ($estadisticas_estado as $item) {
                $estados[$item['estado']] = (int)$item['total'];
            }

            // Artículos más aplicados
            $sql = "
                SELECT a.codigo, a.descripcion, COUNT(*) as total
                FROM sanciones s
                INNER JOIN articulos_sancion a ON s.articulo_id = a.id
                WHERE 1=1 $condiciones
                GROUP BY a.id
                ORDER BY total DESC
                LIMIT 5
            ";

            $stmt = $this->pdo->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();
            $articulos_top = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'por_estado' => $estados,
                'articulos_top' => $articulos_top,
                'total' => array_sum($estados)
            ];
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al obtener estadísticas: ' . $e->getMessage()];
        }
    }
}
