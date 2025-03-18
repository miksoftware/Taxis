<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Controllers\DireccionController.php
require_once __DIR__ . '/../Models/DireccionModel.php';

class DireccionController
{
    private $direccionModel;
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->direccionModel = new Direccion($pdo);

        // Verificar si la columna activa existe, si no, agregarla
        $this->verificarColumnaActiva();
    }

    /**
     * Obtiene todas las direcciones de un cliente
     */
    public function obtenerPorCliente($cliente_id)
    {
        if (!$cliente_id) {
            return ['error' => true, 'mensaje' => 'ID de cliente no proporcionado'];
        }

        return $this->direccionModel->obtenerPorCliente($cliente_id);
    }

    /**
     * Obtiene una dirección por su ID
     */
    public function obtenerPorId($id)
    {
        if (!$id) {
            return ['error' => true, 'mensaje' => 'ID de dirección no proporcionado'];
        }

        return $this->direccionModel->obtenerPorId($id);
    }

    /**
     * Busca direcciones por cliente
     */
    public function buscarPorCliente($cliente_id, $limite = null)
    {
        if (!$cliente_id) {
            return ['error' => true, 'mensaje' => 'ID de cliente no proporcionado'];
        }

        return $this->direccionModel->buscarPorCliente($cliente_id, $limite);
    }

    /**
     * Crear una nueva dirección
     */
    public function crear($datos)
    {
        // Validar datos
        if (!isset($datos['cliente_id']) || !isset($datos['direccion'])) {
            return ['error' => true, 'mensaje' => 'Datos incompletos'];
        }

        // Verificar si la dirección ya existe para este cliente
        if ($this->direccionModel->existeDireccion($datos['cliente_id'], $datos['direccion'])) {
            return ['error' => true, 'mensaje' => 'Esta dirección ya existe para este cliente'];
        }

        // Crear dirección
        return $this->direccionModel->crear($datos);
    }

    /**
     * Actualizar una dirección existente
     */
    public function actualizar($id, $datos)
    {
        // Validar datos
        if (!$id) {
            return ['error' => true, 'mensaje' => 'ID no proporcionado'];
        }

        // Obtener dirección actual para verificar
        $direccion_actual = $this->direccionModel->obtenerPorId($id);
        if (!$direccion_actual || isset($direccion_actual['error'])) {
            return ['error' => true, 'mensaje' => 'Dirección no encontrada'];
        }

        // Verificar si ha cambiado la dirección y si ya existe otra igual
        if (isset($datos['direccion']) && $datos['direccion'] !== $direccion_actual['direccion']) {
            if ($this->direccionModel->existeDireccion($direccion_actual['cliente_id'], $datos['direccion'])) {
                return ['error' => true, 'mensaje' => 'Esta dirección ya existe para este cliente'];
            }
        }

        // Actualizar dirección
        return $this->direccionModel->actualizar($id, $datos);
    }

    /**
     * Eliminar una dirección
     */
    public function eliminar($id)
    {
        if (!$id) {
            return ['error' => true, 'mensaje' => 'ID no proporcionado'];
        }

        return $this->direccionModel->eliminar($id);
    }

    /**
     * Normaliza una dirección para facilitar la detección de duplicados
     * Utiliza la implementación del modelo para mantener consistencia
     */
    public function normalizarDireccion($direccion)
    {
        // Crear una instancia temporal del modelo de dirección si no existe
        if (!$this->direccionModel) {
            $this->direccionModel = new Direccion($this->pdo);
        }

        // Usar la implementación privada del modelo a través de un método público
        return $this->direccionModel->normalizarDireccionPublica($direccion);
    }

    



    /**
     * Actualiza la fecha de último uso de una dirección
     */
    public function actualizarUltimoUso($direccion_id)
    {
        if (!$direccion_id) {
            return ['error' => true, 'mensaje' => 'ID de dirección no proporcionado'];
        }

        return $this->direccionModel->actualizarUltimoUso($direccion_id);
    }

    /**
     * Marca dirección como frecuente/no frecuente
     */
    public function marcarFrecuente($id, $es_frecuente = true)
    {
        if (!$id) {
            return ['error' => true, 'mensaje' => 'ID de dirección no proporcionado'];
        }

        return $this->direccionModel->marcarFrecuente($id, $es_frecuente);
    }

    /**
     * Cuenta el número de direcciones asociadas a un cliente
     */
    public function contarDireccionesPorCliente($cliente_id)
    {
        if (!$cliente_id) {
            return 0;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) AS total 
                FROM direcciones 
                WHERE cliente_id = :cliente_id
            ");
            $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Obtiene las direcciones frecuentes de un cliente
     */
    public function obtenerDireccionesFrecuentes($cliente_id, $limite = 5)
    {
        if (!$cliente_id) {
            return ['error' => true, 'mensaje' => 'ID de cliente no proporcionado'];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM direcciones 
                WHERE cliente_id = :cliente_id 
                AND es_frecuente = 1 
                AND activa = 1
                ORDER BY ultimo_uso DESC 
                LIMIT :limite
            ");
            $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [
                'error' => true,
                'mensaje' => 'Error al obtener direcciones frecuentes: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar el estado de una dirección (activa/inactiva)
     */
    public function actualizarEstado($id, $activa = true)
    {
        if (!$id) {
            return ['error' => true, 'mensaje' => 'ID de dirección no proporcionado'];
        }

        return $this->direccionModel->actualizarEstado($id, $activa);
    }

    /**
     * Verificar si la columna activa existe, si no, agregarla
     */
    private function verificarColumnaActiva()
    {
        return $this->direccionModel->agregarColumnaActiva();
    }

    /**
     * Obtiene direcciones para autocompletar
     */
    public function buscarParaAutocompletar($cliente_id, $termino, $limite = 10)
    {
        if (!$cliente_id) {
            return ['error' => true, 'mensaje' => 'ID de cliente no proporcionado'];
        }

        try {
            $termino = '%' . $termino . '%';
            $stmt = $this->pdo->prepare("
                SELECT id, direccion, referencia, es_frecuente
                FROM direcciones 
                WHERE cliente_id = :cliente_id 
                AND activa = 1
                AND (
                    direccion LIKE :termino OR 
                    direccion_normalizada LIKE :termino OR 
                    referencia LIKE :termino
                )
                ORDER BY es_frecuente DESC, ultimo_uso DESC
                LIMIT :limite
            ");
            $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            $stmt->bindParam(':termino', $termino, PDO::PARAM_STR);
            $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [
                'error' => true,
                'mensaje' => 'Error al buscar direcciones: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene las últimas direcciones usadas por un cliente
     */
    public function obtenerUltimasDirecciones($cliente_id, $limite = 5)
    {
        if (!$cliente_id) {
            return ['error' => true, 'mensaje' => 'ID de cliente no proporcionado'];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM direcciones 
                WHERE cliente_id = :cliente_id 
                AND activa = 1
                ORDER BY ultimo_uso DESC 
                LIMIT :limite
            ");
            $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [
                'error' => true,
                'mensaje' => 'Error al obtener últimas direcciones: ' . $e->getMessage()
            ];
        }
    }
}
