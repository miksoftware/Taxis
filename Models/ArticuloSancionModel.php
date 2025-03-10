<?php
class ArticuloSancion {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Registra un nuevo artículo de sanción
     */
    public function registrar($datos) {
        try {
            // Verificar si el código ya existe
            if ($this->codigoExiste($datos['codigo'])) {
                return ['error' => true, 'mensaje' => 'El código de artículo ya está registrado'];
            }
            
            $sql = "INSERT INTO articulos_sancion (codigo, descripcion, tiempo_sancion, activo) 
                    VALUES (:codigo, :descripcion, :tiempo_sancion, :activo)";
            
            $stmt = $this->pdo->prepare($sql);
            
            $activo = isset($datos['activo']) ? $datos['activo'] : true;
            
            $stmt->bindParam(':codigo', $datos['codigo']);
            $stmt->bindParam(':descripcion', $datos['descripcion']);
            $stmt->bindParam(':tiempo_sancion', $datos['tiempo_sancion'], PDO::PARAM_INT);
            $stmt->bindParam(':activo', $activo, PDO::PARAM_BOOL);
            
            $stmt->execute();
            
            $id = $this->pdo->lastInsertId();
            
            return [
                'error' => false, 
                'mensaje' => 'Artículo de sanción registrado correctamente', 
                'articulo_id' => $id
            ];
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al registrar artículo de sanción: ' . $e->getMessage()];
        }
    }
    
/**
 * Verifica si un artículo está siendo usado en alguna sanción
 * @param int $id ID del artículo a verificar
 * @return bool True si está en uso, False si no
 */
public function verificarUso($id) {
    try {
        $sql = "SELECT COUNT(*) FROM sanciones WHERE articulo_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error al verificar uso de artículo: " . $e->getMessage());
        return true; // Por seguridad, devolvemos true en caso de error
    }
}

    /**
     * Actualiza un artículo de sanción existente
     */
    public function actualizar($id, $datos) {
        try {
            // Verificar si el artículo existe
            if (!$this->existeId($id)) {
                return ['error' => true, 'mensaje' => 'El artículo de sanción no existe'];
            }
            
            // Verificar si el código ya está en uso por otro artículo
            if ($this->codigoExisteOtroArticulo($datos['codigo'], $id)) {
                return ['error' => true, 'mensaje' => 'El código de artículo ya está asignado a otro artículo'];
            }
            
            $sql = "UPDATE articulos_sancion SET 
                    codigo = :codigo,
                    descripcion = :descripcion,
                    tiempo_sancion = :tiempo_sancion,
                    activo = :activo
                    WHERE id = :id";
            
            $stmt = $this->pdo->prepare($sql);
            
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':codigo', $datos['codigo']);
            $stmt->bindParam(':descripcion', $datos['descripcion']);
            $stmt->bindParam(':tiempo_sancion', $datos['tiempo_sancion'], PDO::PARAM_INT);
            $stmt->bindParam(':activo', $datos['activo'], PDO::PARAM_BOOL);
            
            $stmt->execute();
            
            return ['error' => false, 'mensaje' => 'Artículo de sanción actualizado correctamente'];
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al actualizar artículo de sanción: ' . $e->getMessage()];
        }
    }
    
    /**
     * Elimina un artículo de sanción
     */
    public function eliminar($id) {
        try {
            // Verificar si el artículo existe
            if (!$this->existeId($id)) {
                return ['error' => true, 'mensaje' => 'El artículo de sanción no existe'];
            }
            
            // Verificar si el artículo está siendo usado en alguna sanción
            $sql = "SELECT COUNT(*) FROM sanciones WHERE articulo_id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->fetchColumn() > 0) {
                return ['error' => true, 'mensaje' => 'No se puede eliminar el artículo porque está siendo usado en sanciones'];
            }
            
            // Eliminar el artículo
            $sql = "DELETE FROM articulos_sancion WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            return ['error' => false, 'mensaje' => 'Artículo de sanción eliminado correctamente'];
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al eliminar artículo de sanción: ' . $e->getMessage()];
        }
    }
    
    /**
     * Cambia el estado de un artículo de sanción
     */
    public function cambiarEstado($id, $activo) {
        try {
            // Verificar si el artículo existe
            if (!$this->existeId($id)) {
                return ['error' => true, 'mensaje' => 'El artículo de sanción no existe'];
            }
            
            $sql = "UPDATE articulos_sancion SET activo = :activo WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':activo', $activo, PDO::PARAM_BOOL);
            $stmt->execute();
            
            $estado = $activo ? 'activado' : 'desactivado';
            return ['error' => false, 'mensaje' => 'Artículo de sanción ' . $estado . ' correctamente'];
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al cambiar estado del artículo: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obtiene un artículo de sanción por su ID
     */
    public function obtenerPorId($id) {
        try {
            $sql = "SELECT * FROM articulos_sancion WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $articulo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$articulo) {
                return ['error' => true, 'mensaje' => 'Artículo de sanción no encontrado'];
            }
            
            return $articulo;
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al obtener artículo de sanción: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obtiene un artículo de sanción por su código
     */
    public function obtenerPorCodigo($codigo) {
        try {
            $sql = "SELECT * FROM articulos_sancion WHERE codigo = :codigo";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':codigo', $codigo);
            $stmt->execute();
            
            $articulo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$articulo) {
                return ['error' => true, 'mensaje' => 'Artículo de sanción no encontrado'];
            }
            
            return $articulo;
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al obtener artículo de sanción: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obtiene todos los artículos de sanción
     */
    public function obtenerTodos() {
        try {
            $sql = "SELECT * FROM articulos_sancion ORDER BY codigo ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al obtener artículos de sanción: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obtiene artículos de sanción activos
     */
    public function obtenerActivos() {
        try {
            $sql = "SELECT * FROM articulos_sancion WHERE activo = TRUE ORDER BY codigo ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al obtener artículos de sanción: ' . $e->getMessage()];
        }
    }
    
    /**
     * Verifica si un artículo existe por ID
     */
    private function existeId($id) {
        $sql = "SELECT COUNT(*) FROM articulos_sancion WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Verifica si un código ya está registrado
     */
    private function codigoExiste($codigo) {
        $sql = "SELECT COUNT(*) FROM articulos_sancion WHERE codigo = :codigo";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':codigo', $codigo);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Verifica si un código ya está registrado por otro artículo
     */
    private function codigoExisteOtroArticulo($codigo, $id) {
        $sql = "SELECT COUNT(*) FROM articulos_sancion WHERE codigo = :codigo AND id != :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':codigo', $codigo);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }
}
?>