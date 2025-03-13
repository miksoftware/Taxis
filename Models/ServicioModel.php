<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Models\ServicioModel.php
class Servicio
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Crea un nuevo servicio en la base de datos
     */
    public function crear($datos)
    {
        try {
            $this->pdo->beginTransaction();

            $sql = "INSERT INTO servicios (cliente_id, direccion_id, condicion, observaciones, estado, fecha_solicitud, operador_id) 
                    VALUES (:cliente_id, :direccion_id, :condicion, :observaciones, :estado, :fecha_solicitud, :operador_id)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':cliente_id', $datos['cliente_id'], PDO::PARAM_INT);
            $stmt->bindParam(':direccion_id', $datos['direccion_id'], PDO::PARAM_INT);
            $stmt->bindParam(':condicion', $datos['condicion'], PDO::PARAM_STR);
            $stmt->bindParam(':observaciones', $datos['observaciones'], PDO::PARAM_STR);
            $stmt->bindParam(':estado', $datos['estado'], PDO::PARAM_STR);
            $stmt->bindParam(':fecha_solicitud', $datos['fecha_solicitud'], PDO::PARAM_STR);
            $stmt->bindParam(':operador_id', $datos['operador_id'], PDO::PARAM_INT);

            $stmt->execute();
            $id = $this->pdo->lastInsertId();

            // Si el servicio es creado correctamente, actualizar la fecha de último uso de la dirección
            $sql_dir = "UPDATE direcciones SET ultimo_uso = NOW() WHERE id = :direccion_id";
            $stmt_dir = $this->pdo->prepare($sql_dir);
            $stmt_dir->bindParam(':direccion_id', $datos['direccion_id'], PDO::PARAM_INT);
            $stmt_dir->execute();

            $this->pdo->commit();

            return [
                'error' => false,
                'mensaje' => 'Servicio creado correctamente',
                'id' => $id
            ];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return [
                'error' => true,
                'mensaje' => 'Error al crear servicio: ' . $e->getMessage(),
                'debug_info' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene un servicio por su ID
     */
    public function obtenerPorId($id)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.*, c.telefono, d.direccion, v.placa 
                FROM servicios s
                LEFT JOIN clientes c ON s.cliente_id = c.id
                LEFT JOIN direcciones d ON s.direccion_id = d.id
                LEFT JOIN vehiculos v ON s.vehiculo_id = v.id
                WHERE s.id = :id
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Actualiza un servicio existente
     */
    public function actualizar($id, $datos)
    {
        try {
            $this->pdo->beginTransaction();

            $fields = [];
            $params = [':id' => $id];

            foreach ($datos as $key => $value) {
                if ($value !== null) {
                    $fields[] = "$key = :$key";
                    $params[":$key"] = $value;
                } else {
                    $fields[] = "$key = NULL";
                }
            }

            $sql = "UPDATE servicios SET " . implode(', ', $fields) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $this->pdo->commit();

            return [
                'error' => false,
                'mensaje' => 'Servicio actualizado correctamente'
            ];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return [
                'error' => true,
                'mensaje' => 'Error al actualizar servicio: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Lista los servicios por estado
     */
    public function listarPorEstado($estado)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.*, c.telefono, d.direccion, v.placa 
                FROM servicios s
                LEFT JOIN clientes c ON s.cliente_id = c.id
                LEFT JOIN direcciones d ON s.direccion_id = d.id
                LEFT JOIN vehiculos v ON s.vehiculo_id = v.id
                WHERE s.estado = :estado
                ORDER BY s.fecha_solicitud DESC
            ");
            $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Obtiene la lista de servicios del día actual
     */
    public function obtenerServiciosHoy()
    {
        try {
            $fecha_inicio = date('Y-m-d 00:00:00');
            $fecha_fin = date('Y-m-d 23:59:59');

            $stmt = $this->pdo->prepare("
                SELECT s.*, c.telefono, d.direccion, v.placa 
                FROM servicios s
                LEFT JOIN clientes c ON s.cliente_id = c.id
                LEFT JOIN direcciones d ON s.direccion_id = d.id
                LEFT JOIN vehiculos v ON s.vehiculo_id = v.id
                WHERE s.fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin
                ORDER BY s.fecha_solicitud DESC
            ");
            $stmt->bindParam(':fecha_inicio', $fecha_inicio, PDO::PARAM_STR);
            $stmt->bindParam(':fecha_fin', $fecha_fin, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Obtiene métricas básicas de servicios del día
     */
    public function obtenerMetricasHoy()
    {
        try {
            $fecha_inicio = date('Y-m-d 00:00:00');
            $fecha_fin = date('Y-m-d 23:59:59');

            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) as finalizados,
                    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                    SUM(CASE WHEN estado = 'asignado' OR estado = 'en_camino' THEN 1 ELSE 0 END) as en_curso,
                    SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados
                FROM servicios
                WHERE fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin
            ");
            $stmt->bindParam(':fecha_inicio', $fecha_inicio, PDO::PARAM_STR);
            $stmt->bindParam(':fecha_fin', $fecha_fin, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [
                'total' => 0,
                'finalizados' => 0,
                'pendientes' => 0,
                'en_curso' => 0,
                'cancelados' => 0
            ];
        }
    }

    /**
     * Registra un cambio de estado en el historial de servicios
     */
    public function registrarHistorialEstado($servicio_id, $estado_anterior, $estado_nuevo, $usuario_id)
    {
        try {
            $stmt = $this->pdo->prepare("
            INSERT INTO historial_servicios (servicio_id, estado_anterior, estado_nuevo, fecha_cambio, usuario_id)
            VALUES (:servicio_id, :estado_anterior, :estado_nuevo, NOW(), :usuario_id)
        ");
            $stmt->bindParam(':servicio_id', $servicio_id, PDO::PARAM_INT);
            $stmt->bindParam(':estado_anterior', $estado_anterior, PDO::PARAM_STR);
            $stmt->bindParam(':estado_nuevo', $estado_nuevo, PDO::PARAM_STR);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->execute();

            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Cuenta servicios de un cliente en un mes específico
     */
    public function contarServiciosPorClienteMes($cliente_id, $mes, $anio)
    {
        try {
            $inicio_mes = sprintf('%d-%02d-01 00:00:00', $anio, $mes);
            $ultimo_dia = date('t', strtotime($inicio_mes));
            $fin_mes = sprintf('%d-%02d-%d 23:59:59', $anio, $mes, $ultimo_dia);

            $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total 
            FROM servicios 
            WHERE cliente_id = :cliente_id 
            AND fecha_solicitud BETWEEN :inicio_mes AND :fin_mes
        ");
            $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            $stmt->bindParam(':inicio_mes', $inicio_mes, PDO::PARAM_STR);
            $stmt->bindParam(':fin_mes', $fin_mes, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Cuenta el total de servicios de un cliente
     */
    public function contarServiciosPorCliente($cliente_id)
    {
        try {
            $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total 
            FROM servicios 
            WHERE cliente_id = :cliente_id
        ");
            $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        } catch (PDOException $e) {
            return 0;
        }
    }
}
