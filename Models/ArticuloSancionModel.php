<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Models\ArticuloSancionModel.php
class ArticuloSancion {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtiene todos los artículos de sanción
     */
    public function listar($filtros = []) {
        try {
            $where = "1=1"; // Condición siempre verdadera como base
            $params = [];

            // Aplicar filtros si existen
            if (!empty($filtros['codigo'])) {
                $where .= " AND codigo LIKE :codigo";
                $params[':codigo'] = '%' . $filtros['codigo'] . '%';
            }
            
            if (!empty($filtros['estado'])) {
                $where .= " AND estado = :estado";
                $params[':estado'] = $filtros['estado'];
            }
            
            $sql = "SELECT * FROM articulos_sancion WHERE {$where} ORDER BY codigo ASC";
            $stmt = $this->pdo->prepare($sql);
            
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al listar artículos: ' . $e->getMessage()];
        }
    }

    /**
     * Obtiene un artículo por su ID
     */
    public function obtenerPorId($id) {
        try {
            $sql = "SELECT * FROM articulos_sancion WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $articulo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$articulo) {
                return ['error' => true, 'mensaje' => 'Artículo no encontrado'];
            }
            
            return $articulo;
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al obtener artículo: ' . $e->getMessage()];
        }
    }

    /**
     * Obtiene artículos activos
     */
    public function obtenerActivos() {
        try {
            $sql = "SELECT * FROM articulos_sancion WHERE estado = 'activo' ORDER BY codigo ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al obtener artículos activos: ' . $e->getMessage()];
        }
    }

    /**
     * Registra un nuevo artículo de sanción
     */
    public function registrar($datos) {
        try {
            // Validar datos
            $errores = $this->validarDatos($datos);
            if (!empty($errores)) {
                return ['error' => true, 'mensaje' => implode('. ', $errores)];
            }

            // Verificar si ya existe un artículo con el mismo código
            if ($this->codigoExiste($datos['codigo'])) {
                return ['error' => true, 'mensaje' => 'Ya existe un artículo con el código ' . $datos['codigo']];
            }

            $sql = "INSERT INTO articulos_sancion (codigo, descripcion, tiempo_sancion, estado) 
                    VALUES (:codigo, :descripcion, :tiempo_sancion, :estado)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':codigo', $datos['codigo']);
            $stmt->bindParam(':descripcion', $datos['descripcion']);
            $stmt->bindParam(':tiempo_sancion', $datos['tiempo_sancion'], PDO::PARAM_INT);
            $stmt->bindParam(':estado', $datos['estado']);
            
            $stmt->execute();
            
            return [
                'error' => false,
                'mensaje' => 'Artículo registrado correctamente',
                'id' => $this->pdo->lastInsertId()
            ];
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al registrar artículo: ' . $e->getMessage()];
        }
    }

    /**
     * Actualiza un artículo de sanción
     */
    public function actualizar($id, $datos) {
        try {
            // Verificar si el artículo existe
            if (!$this->existeId($id)) {
                return ['error' => true, 'mensaje' => 'El artículo no existe'];
            }

            // Validar datos
            $errores = $this->validarDatos($datos);
            if (!empty($errores)) {
                return ['error' => true, 'mensaje' => implode('. ', $errores)];
            }

            // Verificar si el código ya existe en otro artículo
            if ($this->codigoExisteOtroArticulo($datos['codigo'], $id)) {
                return ['error' => true, 'mensaje' => 'Ya existe otro artículo con el código ' . $datos['codigo']];
            }

            $sql = "UPDATE articulos_sancion 
                    SET codigo = :codigo, 
                        descripcion = :descripcion, 
                        tiempo_sancion = :tiempo_sancion, 
                        estado = :estado 
                    WHERE id = :id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':codigo', $datos['codigo']);
            $stmt->bindParam(':descripcion', $datos['descripcion']);
            $stmt->bindParam(':tiempo_sancion', $datos['tiempo_sancion'], PDO::PARAM_INT);
            $stmt->bindParam(':estado', $datos['estado']);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            $stmt->execute();
            
            return ['error' => false, 'mensaje' => 'Artículo actualizado correctamente'];
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al actualizar artículo: ' . $e->getMessage()];
        }
    }

    /**
     * Elimina un artículo de sanción (cambia estado a inactivo)
     */
    public function eliminar($id) {
        try {
            // Verificar si el artículo existe
            if (!$this->existeId($id)) {
                return ['error' => true, 'mensaje' => 'El artículo no existe'];
            }

            // Verificar si el artículo está siendo usado en sanciones activas
            $sql = "SELECT COUNT(*) FROM sanciones s 
                    JOIN articulos_sancion a ON s.articulo_id = a.id 
                    WHERE a.id = :id AND s.estado = 'activa'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->fetchColumn() > 0) {
                return [
                    'error' => true, 
                    'mensaje' => 'No se puede eliminar este artículo porque está siendo usado en sanciones activas'
                ];
            }

            // Cambiar estado a inactivo
            $sql = "UPDATE articulos_sancion SET estado = 'inactivo' WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            return ['error' => false, 'mensaje' => 'Artículo eliminado correctamente'];
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al eliminar artículo: ' . $e->getMessage()];
        }
    }

    /**
     * Cambia el estado de un artículo
     */
    public function cambiarEstado($id, $estado) {
        try {
            // Verificar estado válido
            if (!in_array($estado, ['activo', 'inactivo'])) {
                return ['error' => true, 'mensaje' => 'Estado no válido'];
            }

            // Verificar si el artículo existe
            if (!$this->existeId($id)) {
                return ['error' => true, 'mensaje' => 'El artículo no existe'];
            }
            
            // Si se va a inactivar, verificar que no esté en uso en sanciones activas
            if ($estado === 'inactivo') {
                $sql = "SELECT COUNT(*) FROM sanciones s 
                        JOIN articulos_sancion a ON s.articulo_id = a.id 
                        WHERE a.id = :id AND s.estado = 'activa'";
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();

                if ($stmt->fetchColumn() > 0) {
                    return [
                        'error' => true, 
                        'mensaje' => 'No se puede inactivar este artículo porque está siendo usado en sanciones activas'
                    ];
                }
            }

            $sql = "UPDATE articulos_sancion SET estado = :estado WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':estado', $estado);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            return ['error' => false, 'mensaje' => 'Estado del artículo actualizado correctamente'];
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al cambiar estado del artículo: ' . $e->getMessage()];
        }
    }

    /**
     * Convierte el tiempo en minutos a un formato legible
     */
    public function formatearTiempo($minutos) {
        if ($minutos < 60) {
            return $minutos . ' minutos';
        } elseif ($minutos < 1440) {
            $horas = floor($minutos / 60);
            $min = $minutos % 60;
            return $horas . ' hora' . ($horas != 1 ? 's' : '') . 
                   ($min > 0 ? ' ' . $min . ' minuto' . ($min != 1 ? 's' : '') : '');
        } else {
            $dias = floor($minutos / 1440);
            $horas = floor(($minutos % 1440) / 60);
            $min = $minutos % 60;
            
            $resultado = $dias . ' día' . ($dias != 1 ? 's' : '');
            if ($horas > 0) {
                $resultado .= ' ' . $horas . ' hora' . ($horas != 1 ? 's' : '');
            }
            if ($min > 0) {
                $resultado .= ' ' . $min . ' minuto' . ($min != 1 ? 's' : '');
            }
            
            return $resultado;
        }
    }

    /**
     * Valida los datos de un artículo
     */
    private function validarDatos($datos) {
        $errores = [];
        
        // Validar código
        if (empty($datos['codigo'])) {
            $errores[] = 'El código es obligatorio';
        } elseif (!preg_match('/^[A-Z0-9\-]{1,20}$/', $datos['codigo'])) {
            $errores[] = 'El código debe contener solo letras mayúsculas, números y guiones, máximo 20 caracteres';
        }
        
        // Validar descripción
        if (empty($datos['descripcion'])) {
            $errores[] = 'La descripción es obligatoria';
        } elseif (strlen($datos['descripcion']) > 255) {
            $errores[] = 'La descripción no debe exceder los 255 caracteres';
        }
        
        // Validar tiempo de sanción
        if (!isset($datos['tiempo_sancion']) || $datos['tiempo_sancion'] === '') {
            $errores[] = 'El tiempo de sanción es obligatorio';
        } elseif (!is_numeric($datos['tiempo_sancion']) || $datos['tiempo_sancion'] <= 0) {
            $errores[] = 'El tiempo de sanción debe ser un número positivo';
        }
        
        // Validar estado
        if (empty($datos['estado'])) {
            $errores[] = 'El estado es obligatorio';
        } elseif (!in_array($datos['estado'], ['activo', 'inactivo'])) {
            $errores[] = 'El estado debe ser activo o inactivo';
        }
        
        return $errores;
    }

    /**
     * Verifica si existe un artículo con el ID proporcionado
     */
    private function existeId($id) {
        $sql = "SELECT COUNT(*) FROM articulos_sancion WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Verifica si ya existe un artículo con el código proporcionado
     */
    private function codigoExiste($codigo) {
        $sql = "SELECT COUNT(*) FROM articulos_sancion WHERE codigo = :codigo";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':codigo', $codigo);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Verifica si existe otro artículo con el mismo código
     */
    private function codigoExisteOtroArticulo($codigo, $id) {
        $sql = "SELECT COUNT(*) FROM articulos_sancion WHERE codigo = :codigo AND id != :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':codigo', $codigo);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }
}
?>