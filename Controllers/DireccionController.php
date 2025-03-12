<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Controllers\DireccionController.php
require_once __DIR__ . '/../Models/DireccionModel.php';

class DireccionController {
    private $direccionModel;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->direccionModel = new Direccion($pdo);
    }

    /**
     * Crea una nueva dirección
     */
    public function crear($datos) {
        // Validar datos necesarios
        if (!isset($datos['cliente_id']) || !isset($datos['direccion']) || empty($datos['direccion'])) {
            return ['error' => true, 'mensaje' => 'Datos incompletos para crear dirección'];
        }

        // Normalizar dirección
        $datos['direccion_normalizada'] = $this->normalizarDireccion($datos['direccion']);

        // Comprobar si la dirección ya existe para este cliente
        $existe = $this->direccionModel->buscarDireccionNormalizada(
            $datos['cliente_id'], 
            $datos['direccion_normalizada']
        );

        if ($existe) {
            // Actualizar último uso
            $this->direccionModel->actualizarUltimoUso($existe['id']);
            return [
                'error' => false,
                'mensaje' => 'Dirección existente seleccionada',
                'id' => $existe['id'],
                'direccion' => $existe['direccion'],
                'es_nueva' => false
            ];
        }

        // Añadir fecha de registro y último uso
        $datos['fecha_registro'] = date('Y-m-d H:i:s');
        $datos['ultimo_uso'] = date('Y-m-d H:i:s');

        // Crear dirección
        return $this->direccionModel->crear($datos);
    }

    /**
     * Obtiene una dirección por su ID
     */
    public function obtenerPorId($id) {
        if (!$id) {
            return ['error' => true, 'mensaje' => 'ID de dirección no proporcionado'];
        }

        return $this->direccionModel->obtenerPorId($id);
    }

    /**
     * Busca direcciones por cliente
     */
    public function buscarPorCliente($cliente_id, $limite = null) {
        if (!$cliente_id) {
            return ['error' => true, 'mensaje' => 'ID de cliente no proporcionado'];
        }

        return $this->direccionModel->buscarPorCliente($cliente_id, $limite);
    }

    /**
     * Actualiza una dirección
     */
    public function actualizar($id, $datos) {
        if (!$id) {
            return ['error' => true, 'mensaje' => 'ID de dirección no proporcionado'];
        }

        // Normalizar dirección si se va a actualizar
        if (isset($datos['direccion']) && !empty($datos['direccion'])) {
            $datos['direccion_normalizada'] = $this->normalizarDireccion($datos['direccion']);

            // Comprobar si la nueva dirección ya existe para este cliente
            $direccion = $this->direccionModel->obtenerPorId($id);
            if ($direccion) {
                $existe = $this->direccionModel->buscarDireccionNormalizada(
                    $direccion['cliente_id'], 
                    $datos['direccion_normalizada']
                );

                if ($existe && $existe['id'] != $id) {
                    return [
                        'error' => true, 
                        'mensaje' => 'Ya existe una dirección similar para este cliente'
                    ];
                }
            }
        }

        return $this->direccionModel->actualizar($id, $datos);
    }

    /**
     * Elimina una dirección
     */
    public function eliminar($id) {
        if (!$id) {
            return ['error' => true, 'mensaje' => 'ID de dirección no proporcionado'];
        }

        return $this->direccionModel->eliminar($id);
    }

    /**
     * Normaliza una dirección para facilitar la detección de duplicados
     */
    public function normalizarDireccion($direccion) {
        // Convertir a minúsculas
        $dir = mb_strtolower($direccion);
        
        // Reemplazar abreviaturas comunes
        $reemplazos = [
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
        
        foreach ($reemplazos as $original => $abreviatura) {
            $dir = str_replace($original, $abreviatura, $dir);
        }
        
        // Eliminar caracteres especiales excepto # - /
        $dir = preg_replace('/[^\w\s\#\-\/]/i', '', $dir);
        
        // Eliminar espacios múltiples
        $dir = preg_replace('/\s+/', ' ', $dir);
        
        // Eliminar espacios antes y después
        return trim($dir);
    }

    /**
     * Actualiza la fecha de último uso de una dirección
     */
    public function actualizarUltimoUso($direccion_id) {
        if (!$direccion_id) {
            return ['error' => true, 'mensaje' => 'ID de dirección no proporcionado'];
        }

        return $this->direccionModel->actualizarUltimoUso($direccion_id);
    }

    /**
     * Marca una dirección como frecuente
     */
    public function marcarComoFrecuente($direccion_id, $es_frecuente = true) {
        if (!$direccion_id) {
            return ['error' => true, 'mensaje' => 'ID de dirección no proporcionado'];
        }

        return $this->direccionModel->marcarComoFrecuente($direccion_id, $es_frecuente);
    }

    /**
     * Cuenta el número de direcciones asociadas a un cliente
     */
    public function contarDireccionesPorCliente($cliente_id) {
        if (!$cliente_id) {
            return 0;
        }

        return $this->direccionModel->contarPorCliente($cliente_id);
    }

    /**
     * Obtiene las direcciones frecuentes de un cliente
     */
    public function obtenerDireccionesFrecuentes($cliente_id, $limite = 5) {
        if (!$cliente_id) {
            return ['error' => true, 'mensaje' => 'ID de cliente no proporcionado'];
        }

        return $this->direccionModel->obtenerDireccionesFrecuentes($cliente_id, $limite);
    }
}