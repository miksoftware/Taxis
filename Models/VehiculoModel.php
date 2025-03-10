<?php
class Vehiculo {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Registra un nuevo vehículo
     */
    public function registrar($datos) {
        try {
            // Verificar si la placa ya existe
            if ($this->placaExiste($datos['placa'])) {
                return ['error' => true, 'mensaje' => 'La placa ya está registrada'];
            }
            
            // Verificar si el número de móvil ya existe
            if ($this->movilExiste($datos['numero_movil'])) {
                return ['error' => true, 'mensaje' => 'El número de móvil ya está registrado'];
            }
            
            $sql = "INSERT INTO vehiculos (placa, numero_movil, estado) 
                    VALUES (:placa, :numero_movil, :estado)";
            
            $stmt = $this->pdo->prepare($sql);
            
            $estado = isset($datos['estado']) ? $datos['estado'] : 'disponible';
            
            $stmt->bindParam(':placa', $datos['placa']);
            $stmt->bindParam(':numero_movil', $datos['numero_movil']);
            $stmt->bindParam(':estado', $estado);
            
            $stmt->execute();
            
            $id = $this->pdo->lastInsertId();
            
            return [
                'error' => false, 
                'mensaje' => 'Vehículo registrado correctamente', 
                'vehiculo_id' => $id
            ];
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al registrar vehículo: ' . $e->getMessage()];
        }
    }
    
    /**
     * Actualiza un vehículo existente
     */
    public function actualizar($id, $datos) {
        try {
            // Verificar si el vehículo existe
            if (!$this->existeId($id)) {
                return ['error' => true, 'mensaje' => 'El vehículo no existe'];
            }
            
            // Verificar si la placa ya está en uso por otro vehículo
            if ($this->placaExisteOtroVehiculo($datos['placa'], $id)) {
                return ['error' => true, 'mensaje' => 'La placa ya está asignada a otro vehículo'];
            }
            
            // Verificar si el número de móvil ya está en uso por otro vehículo
            if ($this->movilExisteOtroVehiculo($datos['numero_movil'], $id)) {
                return ['error' => true, 'mensaje' => 'El número de móvil ya está asignado a otro vehículo'];
            }
            
            $sql = "UPDATE vehiculos SET 
                    placa = :placa,
                    numero_movil = :numero_movil,
                    estado = :estado
                    WHERE id = :id";
            
            $stmt = $this->pdo->prepare($sql);
            
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':placa', $datos['placa']);
            $stmt->bindParam(':numero_movil', $datos['numero_movil']);
            $stmt->bindParam(':estado', $datos['estado']);
            
            $stmt->execute();
            
            return ['error' => false, 'mensaje' => 'Vehículo actualizado correctamente'];
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al actualizar vehículo: ' . $e->getMessage()];
        }
    }
    
    /**
     * Elimina un vehículo
     */
    public function eliminar($id) {
        try {
            // Verificar si el vehículo existe
            if (!$this->existeId($id)) {
                return ['error' => true, 'mensaje' => 'El vehículo no existe'];
            }
            
            // Verificar si el vehículo tiene sanciones activas
            $sql = "SELECT COUNT(*) FROM sanciones WHERE vehiculo_id = :id AND estado = 'activa'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->fetchColumn() > 0) {
                return ['error' => true, 'mensaje' => 'No se puede eliminar el vehículo porque tiene sanciones activas'];
            }
            
            // Eliminar el vehículo
            $sql = "DELETE FROM vehiculos WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            return ['error' => false, 'mensaje' => 'Vehículo eliminado correctamente'];
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al eliminar vehículo: ' . $e->getMessage()];
        }
    }
    
    /**
     * Cambia el estado de un vehículo
     */
    public function cambiarEstado($id, $estado) {
        try {
            // Verificar si el vehículo existe
            if (!$this->existeId($id)) {
                return ['error' => true, 'mensaje' => 'El vehículo no existe'];
            }
            
            // Verificar que el estado sea válido
            $estados_validos = ['disponible', 'en_servicio', 'sancionado', 'mantenimiento', 'inactivo'];
            if (!in_array($estado, $estados_validos)) {
                return ['error' => true, 'mensaje' => 'Estado no válido'];
            }
            
            $sql = "UPDATE vehiculos SET estado = :estado WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':estado', $estado);
            $stmt->execute();
            
            return ['error' => false, 'mensaje' => 'Estado del vehículo actualizado correctamente'];
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al cambiar estado del vehículo: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obtiene un vehículo por su ID
     */
    public function obtenerPorId($id) {
        try {
            $sql = "SELECT * FROM vehiculos WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$vehiculo) {
                return ['error' => true, 'mensaje' => 'Vehículo no encontrado'];
            }
            
            return $vehiculo;
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al obtener vehículo: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obtiene un vehículo por su placa
     */
    public function obtenerPorPlaca($placa) {
        try {
            $sql = "SELECT * FROM vehiculos WHERE placa = :placa";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':placa', $placa);
            $stmt->execute();
            
            $vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$vehiculo) {
                return ['error' => true, 'mensaje' => 'Vehículo no encontrado'];
            }
            
            return $vehiculo;
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al obtener vehículo: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obtiene un vehículo por su número de móvil
     */
    public function obtenerPorMovil($numero_movil) {
        try {
            $sql = "SELECT * FROM vehiculos WHERE numero_movil = :numero_movil";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':numero_movil', $numero_movil);
            $stmt->execute();
            
            $vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$vehiculo) {
                return ['error' => true, 'mensaje' => 'Vehículo no encontrado'];
            }
            
            return $vehiculo;
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al obtener vehículo: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obtiene todos los vehículos
     */
    public function obtenerTodos() {
        try {
            $sql = "SELECT * FROM vehiculos ORDER BY numero_movil ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al obtener vehículos: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obtiene vehículos por estado
     */
    public function obtenerPorEstado($estado) {
        try {
            $sql = "SELECT * FROM vehiculos WHERE estado = :estado ORDER BY numero_movil ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':estado', $estado);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al obtener vehículos: ' . $e->getMessage()];
        }
    }
    
    /**
     * Verifica si un vehículo existe por ID
     */
    private function existeId($id) {
        $sql = "SELECT COUNT(*) FROM vehiculos WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Verifica si una placa ya está registrada
     */
    private function placaExiste($placa) {
        $sql = "SELECT COUNT(*) FROM vehiculos WHERE placa = :placa";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':placa', $placa);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Verifica si una placa ya está registrada por otro vehículo
     */
    private function placaExisteOtroVehiculo($placa, $id) {
        $sql = "SELECT COUNT(*) FROM vehiculos WHERE placa = :placa AND id != :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':placa', $placa);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Verifica si un número de móvil ya está registrado
     */
    private function movilExiste($numero_movil) {
        $sql = "SELECT COUNT(*) FROM vehiculos WHERE numero_movil = :numero_movil";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':numero_movil', $numero_movil);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Verifica si un número de móvil ya está registrado por otro vehículo
     */
    private function movilExisteOtroVehiculo($numero_movil, $id) {
        $sql = "SELECT COUNT(*) FROM vehiculos WHERE numero_movil = :numero_movil AND id != :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':numero_movil', $numero_movil);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }
}
?>