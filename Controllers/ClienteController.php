<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Controllers\ClienteController.php
require_once __DIR__ . '/../Models/ClienteModel.php';

class ClienteController {
    private $clienteModel;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->clienteModel = new Cliente($pdo);
    }

    /**
     * Busca un cliente por su número de teléfono
     */
    public function buscarPorTelefono($telefono) {
        if (empty($telefono)) {
            return ['error' => true, 'mensaje' => 'Teléfono no proporcionado'];
        }
        
        return $this->clienteModel->obtenerPorTelefono($telefono);
    }

    /**
     * Obtiene un cliente por su ID
     */
    public function obtenerPorId($id) {
        if (!$id) {
            return ['error' => true, 'mensaje' => 'ID de cliente no proporcionado'];
        }
        
        return $this->clienteModel->obtenerPorId($id);
    }

    /**
     * Crea un nuevo cliente
     */
    public function crear($datos) {
        // Validar datos mínimos
        if (empty($datos['telefono'])) {
            return ['error' => true, 'mensaje' => 'El teléfono es obligatorio'];
        }
        
        // Si no se proporcionó un nombre, usar el teléfono como nombre provisional
        if (empty($datos['nombre'])) {
            $datos['nombre'] = 'Cliente ' . $datos['telefono'];
        }
        
        return $this->clienteModel->crear($datos);
    }

    /**
     * Actualiza un cliente existente
     */
    public function actualizar($id, $datos) {
        if (!$id) {
            return ['error' => true, 'mensaje' => 'ID de cliente no proporcionado'];
        }
        
        return $this->clienteModel->actualizar($id, $datos);
    }

    /**
     * Busca clientes con autocompletado
     */
    public function buscarAutocompletado($termino) {
        if (empty($termino)) {
            return [];
        }
        
        return $this->clienteModel->buscarAutocompletado($termino);
    }

    /**
     * Obtiene los últimos servicios de un cliente
     */
    public function obtenerUltimosServicios($cliente_id, $limite = 5) {
        if (!$cliente_id) {
            return ['error' => true, 'mensaje' => 'ID de cliente no proporcionado'];
        }
        
        return $this->clienteModel->obtenerUltimosServicios($cliente_id, $limite);
    }
    
    /**
     * Crea un cliente rápido sólo con el teléfono
     */
    public function crearRapido($telefono) {
        if (empty($telefono)) {
            return ['error' => true, 'mensaje' => 'El teléfono es obligatorio'];
        }
        
        // Verificar si ya existe
        $existe = $this->clienteModel->obtenerPorTelefono($telefono);
        if ($existe) {
            return [
                'error' => false, 
                'mensaje' => 'Cliente ya existe', 
                'id' => $existe['id'],
                'nombre' => $existe['nombre'],
                'telefono' => $existe['telefono'],
                'ya_existe' => true
            ];
        }
        
        // Crear cliente rápido
        $datos = [
            'nombre' => 'Cliente ' . $telefono,
            'telefono' => $telefono
        ];
        
        return $this->clienteModel->crear($datos);
    }
}