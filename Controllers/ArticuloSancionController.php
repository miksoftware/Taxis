<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Controllers\ArticuloSancionController.php
require_once __DIR__ . '/../Models/ArticuloSancionModel.php';

class ArticuloSancionController {
    private $articuloModel;
    
    public function __construct($pdo) {
        $this->articuloModel = new ArticuloSancion($pdo);
    }
    
    /**
     * Obtiene todos los artículos según los filtros
     */
    public function listar($filtros = []) {
        return $this->articuloModel->listar($filtros);
    }
    
    /**
     * Obtiene un artículo por su ID
     */
    public function obtener($id) {
        return $this->articuloModel->obtenerPorId($id);
    }
    
    /**
     * Obtiene artículos activos
     */
    public function obtenerActivos() {
        return $this->articuloModel->obtenerActivos();
    }
    
    /**
     * Registra un nuevo artículo
     */
    public function registrar($datos) {
        // Sanear datos
        $datos['codigo'] = strtoupper(trim($datos['codigo']));
        $datos['descripcion'] = trim($datos['descripcion']);
        $datos['tiempo_sancion'] = (int)$datos['tiempo_sancion'];
        
        return $this->articuloModel->registrar($datos);
    }
    
    /**
     * Actualiza un artículo existente
     */
    public function actualizar($id, $datos) {
        // Sanear datos
        $datos['codigo'] = strtoupper(trim($datos['codigo']));
        $datos['descripcion'] = trim($datos['descripcion']);
        $datos['tiempo_sancion'] = (int)$datos['tiempo_sancion'];
        
        return $this->articuloModel->actualizar($id, $datos);
    }
    
    /**
     * Elimina un artículo
     */
    public function eliminar($id) {
        return $this->articuloModel->eliminar($id);
    }
    
    /**
     * Cambia el estado de un artículo
     */
    public function cambiarEstado($id, $estado) {
        return $this->articuloModel->cambiarEstado($id, $estado);
    }
    
    /**
     * Formatea el tiempo para presentación
     */
    public function obtenerTextoTiempo($minutos) {
        return $this->articuloModel->formatearTiempo($minutos);
    }
}
?>