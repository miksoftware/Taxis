<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Models\DireccionModel.php
class Direccion
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Crea una nueva dirección en la base de datos
     */
    public function crear($datos)
    {
        try {
            // Normalizar dirección (convertir a minúsculas, etc.)
            if (!isset($datos['direccion_normalizada'])) {
                $datos['direccion_normalizada'] = $this->normalizarDireccion($datos['direccion']);
            }

            $this->pdo->beginTransaction();

            $sql = "INSERT INTO direcciones 
                    (cliente_id, direccion, direccion_normalizada, referencia, es_frecuente, activa, ultimo_uso, fecha_registro) 
                    VALUES 
                    (:cliente_id, :direccion, :direccion_normalizada, :referencia, :es_frecuente, 1, NOW(), NOW())";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':cliente_id', $datos['cliente_id'], PDO::PARAM_INT);
            $stmt->bindParam(':direccion', $datos['direccion'], PDO::PARAM_STR);
            $stmt->bindParam(':direccion_normalizada', $datos['direccion_normalizada'], PDO::PARAM_STR);

            $referencia = isset($datos['referencia']) ? $datos['referencia'] : '';
            $stmt->bindParam(':referencia', $referencia, PDO::PARAM_STR);

            $es_frecuente = isset($datos['es_frecuente']) ? $datos['es_frecuente'] : 0;
            $stmt->bindParam(':es_frecuente', $es_frecuente, PDO::PARAM_INT);

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
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return [
                'error' => true,
                'mensaje' => 'Error al crear dirección: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene todas las direcciones de un cliente
     */
    public function obtenerPorCliente($cliente_id)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * 
                FROM direcciones 
                WHERE cliente_id = :cliente_id 
                ORDER BY es_frecuente DESC, ultimo_uso DESC
            ");
            $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [
                'error' => true,
                'mensaje' => 'Error al obtener direcciones: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene una dirección por su ID
     */
    public function obtenerPorId($id)
    {
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
     * Actualiza una dirección existente
     */
    public function actualizar($id, $datos)
    {
        try {
            $this->pdo->beginTransaction();

            $fields = [];
            $params = [':id' => $id];

            foreach ($datos as $key => $value) {
                if ($key === 'direccion') {
                    $fields[] = "direccion = :direccion";
                    $params[':direccion'] = $value;

                    // También actualizar la versión normalizada
                    $fields[] = "direccion_normalizada = :direccion_normalizada";
                    $params[':direccion_normalizada'] = $this->normalizarDireccion($value);
                } else if ($key !== 'id' && $value !== null) {
                    $fields[] = "$key = :$key";
                    $params[":$key"] = $value;
                }
            }

            if (count($fields) > 0) {
                $sql = "UPDATE direcciones SET " . implode(', ', $fields) . " WHERE id = :id";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            }

            $this->pdo->commit();

            return [
                'error' => false,
                'mensaje' => 'Dirección actualizada correctamente'
            ];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return [
                'error' => true,
                'mensaje' => 'Error al actualizar dirección: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Elimina una dirección
     */
    public function eliminar($id)
    {
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
                    'mensaje' => 'No se puede eliminar la dirección porque tiene servicios asociados',
                    'tiene_servicios' => true
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
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return [
                'error' => true,
                'mensaje' => 'Error al eliminar dirección: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Actualiza la fecha de último uso de una dirección
     */
    public function actualizarUltimoUso($direccion_id)
    {
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
            return [
                'error' => true,
                'mensaje' => 'Error al actualizar último uso: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Marca una dirección como frecuente o no frecuente
     */
    public function marcarFrecuente($id, $es_frecuente = true)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE direcciones 
                SET es_frecuente = :es_frecuente 
                WHERE id = :id
            ");

            $frecuente_val = $es_frecuente ? 1 : 0;
            $stmt->bindParam(':es_frecuente', $frecuente_val, PDO::PARAM_INT);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'error' => false,
                'mensaje' => 'Dirección actualizada correctamente'
            ];
        } catch (PDOException $e) {
            return [
                'error' => true,
                'mensaje' => 'Error al marcar dirección como frecuente: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Actualiza el estado de una dirección (activa/inactiva)
     */
    public function actualizarEstado($id, $activa = true)
    {
        try {
            // Verificar si esta dirección tiene servicios asociados
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM servicios 
                WHERE direccion_id = :id
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $tiene_servicios = ($stmt->fetchColumn() > 0);

            // Si tiene servicios asociados, marcamos como inactiva en lugar de eliminar
            if ($tiene_servicios && !$activa) {
                $stmt = $this->pdo->prepare("
                    UPDATE direcciones 
                    SET activa = :activa
                    WHERE id = :id
                ");
                $activa_val = $activa ? 1 : 0;
                $stmt->bindParam(':activa', $activa_val, PDO::PARAM_INT);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();

                return [
                    'error' => false,
                    'mensaje' => 'La dirección ha sido marcada como inactiva'
                ];
            } else if (!$tiene_servicios && !$activa) {
                // Si no tiene servicios, podemos eliminarla directamente
                return $this->eliminar($id);
            } else {
                // Activar dirección
                $stmt = $this->pdo->prepare("
                    UPDATE direcciones 
                    SET activa = :activa
                    WHERE id = :id
                ");
                $activa_val = $activa ? 1 : 0;
                $stmt->bindParam(':activa', $activa_val, PDO::PARAM_INT);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();

                return [
                    'error' => false,
                    'mensaje' => 'Estado de la dirección actualizado correctamente'
                ];
            }
        } catch (PDOException $e) {
            return [
                'error' => true,
                'mensaje' => 'Error al actualizar estado de dirección: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verifica si una dirección ya existe para un cliente
     */
    public function existeDireccion($cliente_id, $direccion)
    {
        try {
            $dir_normalizada = $this->normalizarDireccion($direccion);

            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM direcciones 
                WHERE cliente_id = :cliente_id 
                AND direccion_normalizada = :direccion_normalizada
            ");

            $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            $stmt->bindParam(':direccion_normalizada', $dir_normalizada, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Normaliza una dirección para facilitar búsquedas y evitar duplicados
     */
    private function normalizarDireccion($direccion)
    {
        // Convertir a minúsculas y eliminar espacios innecesarios
        $dir = mb_strtolower(trim($direccion), 'UTF-8');

        // Normalizar abreviaturas comunes
        $patrones = [
           'calle' => 'cl',
            'cl.' => 'cl',
            'cll' => 'cl',
            'carrera' => 'kr',
            'cra' => 'kr',
            'kr.' => 'kr',
            'carr' => 'kr',
            'crr' => 'kr',
            'avenida' => 'av',
            'av.' => 'av',
            'diagonal' => 'dg',
            'dg.' => 'dg',
            'transversal' => 'tv',
            'tv.' => 'tv',
            'trans' => 'tv',
            'tra' => 'tv',
            'numero' => '#',
            'No.' => '#',
            'No' => '#',
            'nro' => '#',
            'n°' => '#',
            'apartamento' => 'apto',
            'apt' => 'apto',
            'ap' => 'apto',
            'interior' => 'int',
            'int.' => 'int',
            'bloque' => 'bl',
            'bl.' => 'bl',
            'torre' => 'tr',
            'tr.' => 'tr',
            'edificio' => 'ed',
            'ed.' => 'ed',
            'urbanización' => 'urb',
            'urb.' => 'urb',
            'conjunto' => 'cj',
            'cj.' => 'cj',
            'conj' => 'cj',
            'manzana' => 'mz',
            'mz.' => 'mz',
        ];

        foreach ($patrones as $patron => $reemplazo) {
            $dir = str_replace($patron, $reemplazo, $dir);
        }

        // Eliminar caracteres especiales excepto letras, números, espacios y algunos símbolos comunes
        $dir = preg_replace('/[^\w\s\#\-\/]/u', '', $dir);

        // Eliminar múltiples espacios y reemplazar por uno solo
        $dir = preg_replace('/\s+/', ' ', $dir);

        return trim($dir);
    }

    /**
     * Versión pública del método de normalización para uso desde el controlador
     */
    public function normalizarDireccionPublica($direccion)
    {
        return $this->normalizarDireccion($direccion);
    }

    /**
     * Agregar columna activa a la tabla direcciones si no existe
     */
    public function agregarColumnaActiva()
    {
        try {
            // Verificar si la columna ya existe
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'direcciones' 
                AND COLUMN_NAME = 'activa'
            ");
            $stmt->execute();

            if ($stmt->fetchColumn() == 0) {
                // La columna no existe, agregarla
                $this->pdo->exec("ALTER TABLE direcciones ADD COLUMN activa TINYINT(1) NOT NULL DEFAULT 1 AFTER es_frecuente");
                return [
                    'error' => false,
                    'mensaje' => 'Columna activa agregada correctamente'
                ];
            }

            return [
                'error' => false,
                'mensaje' => 'La columna activa ya existe'
            ];
        } catch (PDOException $e) {
            return [
                'error' => true,
                'mensaje' => 'Error al agregar columna: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Busca direcciones por cliente con opciones de filtrado
     */
    public function buscarPorCliente($cliente_id, $limite = null)
    {
        try {
            $sql = "
            SELECT * 
            FROM direcciones 
            WHERE cliente_id = :cliente_id 
            ORDER BY es_frecuente DESC, ultimo_uso DESC
        ";

            // Añadir límite si está especificado
            if ($limite !== null) {
                $sql .= " LIMIT :limite";
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);

            if ($limite !== null) {
                $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
            }

            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [
                'error' => true,
                'mensaje' => 'Error al buscar direcciones: ' . $e->getMessage()
            ];
        }
    }
}
