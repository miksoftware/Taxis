<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Models\DireccionModel.php
class Direccion {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Crea una nueva dirección en la base de datos
     */
    public function crear($datos) {
        try {
            $this->pdo->beginTransaction();

            $sql = "INSERT INTO direcciones 
                    (cliente_id, direccion, direccion_normalizada, referencia, es_frecuente, ultimo_uso, fecha_registro) 
                    VALUES 
                    (:cliente_id, :direccion, :direccion_normalizada, :referencia, :es_frecuente, :ultimo_uso, :fecha_registro)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':cliente_id', $datos['cliente_id'], PDO::PARAM_INT);
            $stmt->bindParam(':direccion', $datos['direccion'], PDO::PARAM_STR);
            $stmt->bindParam(':direccion_normalizada', $datos['direccion_normalizada'], PDO::PARAM_STR);
            
            $referencia = isset($datos['referencia']) ? $datos['referencia'] : '';
            $stmt->bindParam(':referencia', $referencia, PDO::PARAM_STR);
            
            $es_frecuente = isset($datos['es_frecuente']) ? $datos['es_frecuente'] : false;
            $stmt->bindParam(':es_frecuente', $es_frecuente, PDO::PARAM_BOOL);
            
            $stmt->bindParam(':ultimo_uso', $datos['ultimo_uso'], PDO::PARAM_STR);
            $stmt->bindParam(':fecha_registro', $datos['fecha_registro'], PDO::PARAM_STR);
            
            $stmt->execute();
            $id = $this->pdo->lastInsertId();
            
            $this->pdo->commit();
            
            return [
                'error' => false,
                'mensaje' => 'Dirección creada correctamente',
                'id' => $id,
                'direccion' => $datos['direccion'],
                'es_nueva' => true
            ];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return [
                'error' => true,
                'mensaje' => 'Error al crear dirección: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene una dirección por su ID
     */
    public function obtenerPorId($id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT d.*, c.telefono 
                FROM direcciones d
                LEFT JOIN clientes c ON d.cliente_id = c.id
                WHERE d.id = :id
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Busca direcciones por cliente
     */
    public function buscarPorCliente($cliente_id, $limite = null) {
        try {
            $sql = "
                SELECT * 
                FROM direcciones 
                WHERE cliente_id = :cliente_id 
                ORDER BY ultimo_uso DESC
            ";
            
            if ($limite !== null && is_numeric($limite)) {
                $sql .= " LIMIT :limite";
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            
            if ($limite !== null && is_numeric($limite)) {
                $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al buscar direcciones: ' . $e->getMessage()];
        }
    }

    /**
     * Busca direcciones por texto normalizado
     */
    public function buscarDireccionNormalizada($cliente_id, $direccion_normalizada) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * 
                FROM direcciones 
                WHERE cliente_id = :cliente_id 
                AND direccion_normalizada = :direccion_normalizada
                LIMIT 1
            ");
            $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            $stmt->bindParam(':direccion_normalizada', $direccion_normalizada, PDO::PARAM_STR);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Actualiza una dirección existente
     */
    public function actualizar($id, $datos) {
        try {
            $this->pdo->beginTransaction();
            
            $fields = [];
            $params = [':id' => $id];
            
            foreach ($datos as $key => $value) {
                if ($value !== null) {
                    $fields[] = "$key = :$key";
                    $params[":$key"] = $value;
                }
            }
            
            $sql = "UPDATE direcciones SET " . implode(', ', $fields) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $this->pdo->commit();
            
            return [
                'error' => false,
                'mensaje' => 'Dirección actualizada correctamente'
            ];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return [
                'error' => true,
                'mensaje' => 'Error al actualizar dirección: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Elimina una dirección
     */
    public function eliminar($id) {
        try {
            // Verificar si la dirección tiene servicios asociados
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM servicios 
                WHERE direccion_id = :id
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->fetchColumn() > 0) {
                return [
                    'error' => true,
                    'mensaje' => 'No se puede eliminar la dirección porque tiene servicios asociados'
                ];
            }
            
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("DELETE FROM direcciones WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $this->pdo->commit();
            
            return [
                'error' => false,
                'mensaje' => 'Dirección eliminada correctamente'
            ];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return [
                'error' => true,
                'mensaje' => 'Error al eliminar dirección: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Actualiza la fecha de último uso de una dirección
     */
    public function actualizarUltimoUso($direccion_id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE direcciones 
                SET ultimo_uso = NOW() 
                WHERE id = :id
            ");
            
            $stmt->bindParam(':id', $direccion_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return ['error' => false];
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al actualizar último uso: ' . $e->getMessage()];
        }
    }

    /**
     * Marca una dirección como frecuente o no
     */
    public function marcarComoFrecuente($direccion_id, $es_frecuente = true) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE direcciones 
                SET es_frecuente = :es_frecuente 
                WHERE id = :id
            ");
            
            $frecuente = $es_frecuente ? 1 : 0;
            $stmt->bindParam(':es_frecuente', $frecuente, PDO::PARAM_INT);
            $stmt->bindParam(':id', $direccion_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return ['error' => false];
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al marcar como frecuente: ' . $e->getMessage()];
        }
    }

    /**
     * Cuenta el número de direcciones de un cliente
     */
    public function contarPorCliente($cliente_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM direcciones 
                WHERE cliente_id = :cliente_id
            ");
            
            $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Obtiene las direcciones frecuentes o más recientes de un cliente
     */
    public function obtenerDireccionesFrecuentes($cliente_id, $limite = 5) {
        try {
            // Esta consulta prioriza primero las direcciones frecuentes,
            // luego las ordena por último uso para mostrar las más recientes
            $stmt = $this->pdo->prepare("
                SELECT id, direccion, referencia, es_frecuente, ultimo_uso
                FROM direcciones
                WHERE cliente_id = :cliente_id
                ORDER BY es_frecuente DESC, ultimo_uso DESC
                LIMIT :limite
            ");
            
            $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al obtener direcciones: ' . $e->getMessage()];
        }
    }
}