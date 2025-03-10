<?php
require_once __DIR__ . '/../Models/ArticuloSancionModel.php';

class ArticuloSancionController {
    private $articuloModel;
    
    public function __construct($pdo) {
        $this->articuloModel = new ArticuloSancion($pdo);
    }
    
    /**
     * Obtiene todos los artículos de sanción
     */
    public function listar($filtros = []) {
        return $this->articuloModel->obtenerTodos($filtros);
    }
    
    /**
     * Obtiene un artículo de sanción por ID
     */
    public function obtener($id) {
        $articulo = $this->articuloModel->obtenerPorId($id);
        
        if (!$articulo || isset($articulo['error'])) {
            return ['error' => true, 'mensaje' => 'Artículo de sanción no encontrado'];
        }
        
        return $articulo;
    }
    
    /**
     * Obtiene un artículo de sanción por código
     */
    public function obtenerPorCodigo($codigo) {
        $articulo = $this->articuloModel->obtenerPorCodigo($codigo);
        
        if (!$articulo || isset($articulo['error'])) {
            return ['error' => true, 'mensaje' => 'Artículo de sanción no encontrado'];
        }
        
        return $articulo;
    }
    
    /**
     * Registra un nuevo artículo de sanción
     */
    public function registrar($datos) {
        // Validaciones básicas
        $errores = $this->validarDatos($datos);
        
        if (!empty($errores)) {
            return [
                'error' => true, 
                'mensaje' => 'Existen errores en el formulario', 
                'errores' => $errores
            ];
        }
        
        // Normalizar datos
        $datos['codigo'] = strtoupper(trim($datos['codigo']));
        $datos['descripcion'] = trim($datos['descripcion']);
        $datos['tiempo_sancion'] = (int)$datos['tiempo_sancion'];
        $datos['activo'] = isset($datos['activo']) ? (bool)$datos['activo'] : true;
        
        // Registrar artículo
        return $this->articuloModel->registrar($datos);
    }
    
    /**
     * Actualiza un artículo de sanción existente
     */
    public function actualizar($id, $datos) {
        // Validaciones básicas
        $errores = $this->validarDatos($datos);
        
        if (!empty($errores)) {
            return [
                'error' => true, 
                'mensaje' => 'Existen errores en el formulario', 
                'errores' => $errores
            ];
        }
        
        // Verificar si el artículo existe
        $articulo = $this->articuloModel->obtenerPorId($id);
        if (!$articulo || isset($articulo['error'])) {
            return ['error' => true, 'mensaje' => 'Artículo de sanción no encontrado'];
        }
        
        // Normalizar datos
        $datos['codigo'] = strtoupper(trim($datos['codigo']));
        $datos['descripcion'] = trim($datos['descripcion']);
        $datos['tiempo_sancion'] = (int)$datos['tiempo_sancion'];
        $datos['activo'] = isset($datos['activo']) ? (bool)$datos['activo'] : true;
        
        // Actualizar artículo
        return $this->articuloModel->actualizar($id, $datos);
    }
    
    /**
     * Activa o desactiva un artículo de sanción
     */
    public function cambiarEstado($id, $activo) {
        // Verificar si el artículo existe
        $articulo = $this->articuloModel->obtenerPorId($id);
        if (!$articulo || isset($articulo['error'])) {
            return ['error' => true, 'mensaje' => 'Artículo de sanción no encontrado'];
        }
        
        // Cambiar estado
        $activo = (bool)$activo;
        return $this->articuloModel->cambiarEstado($id, $activo);
    }
    
    /**
     * Elimina un artículo de sanción (baja lógica)
     */
    public function eliminar($id) {
        // Verificar si el artículo existe
        $articulo = $this->articuloModel->obtenerPorId($id);
        if (!$articulo || isset($articulo['error'])) {
            return ['error' => true, 'mensaje' => 'Artículo de sanción no encontrado'];
        }
        
        // Verificar si el artículo está en uso
        $enUso = $this->articuloModel->verificarUso($id);
        if ($enUso) {
            return [
                'error' => true, 
                'mensaje' => 'No se puede eliminar el artículo porque está siendo utilizado en sanciones'
            ];
        }
        
        // Cambiar estado a inactivo (baja lógica)
        return $this->articuloModel->cambiarEstado($id, false);
    }
    
    /**
     * Valida los datos de un artículo de sanción
     */
    private function validarDatos($datos) {
        $errores = [];
        
        // Validar código
        if (empty($datos['codigo'])) {
            $errores['codigo'] = 'El código es requerido';
        }
        
        // Validar descripción
        if (empty($datos['descripcion'])) {
            $errores['descripcion'] = 'La descripción es requerida';
        }
        
        // Validar tiempo de sanción
        if (empty($datos['tiempo_sancion'])) {
            $errores['tiempo_sancion'] = 'El tiempo de sanción es requerido';
        } elseif (!is_numeric($datos['tiempo_sancion']) || (int)$datos['tiempo_sancion'] <= 0) {
            $errores['tiempo_sancion'] = 'El tiempo de sanción debe ser un número positivo';
        }
        
        return $errores;
    }
    
    /**
     * Obtiene artículos de sanción activos
     */
    public function obtenerActivos() {
        $filtros = ['activo' => true];
        return $this->articuloModel->obtenerTodos($filtros);
    }
    
    /**
     * Obtiene texto formateado del tiempo de sanción
     */
    public function obtenerTextoTiempo($minutos) {
        $horas = floor($minutos / 60);
        $minutosRestantes = $minutos % 60;
        
        $texto = '';
        
        if ($horas > 0) {
            $texto .= $horas . ' hora' . ($horas > 1 ? 's' : '');
        }
        
        if ($minutosRestantes > 0) {
            if ($texto) {
                $texto .= ' y ';
            }
            $texto .= $minutosRestantes . ' minuto' . ($minutosRestantes > 1 ? 's' : '');
        }
        
        return $texto;
    }
}
?>