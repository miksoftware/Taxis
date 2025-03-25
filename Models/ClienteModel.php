<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Models\ClienteModel.php
class Cliente
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Obtiene un cliente por su teléfono
     */
    public function obtenerPorTelefono($telefono)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM clientes WHERE telefono = :telefono");
            $stmt->bindParam(':telefono', $telefono, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Obtiene un cliente por su ID
     */
    public function obtenerPorId($id)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM clientes WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }



    /**
     * Crea un nuevo cliente
     */
    public function crear($datos)
    {
        try {
            $this->pdo->beginTransaction();

            // Verificar si ya existe un cliente con el mismo teléfono
            $existe = $this->obtenerPorTelefono($datos['telefono']);
            if ($existe) {
                return [
                    'error' => true,
                    'mensaje' => 'Ya existe un cliente con este número de teléfono'
                ];
            }

            $sql = "INSERT INTO clientes (nombre, telefono, fecha_registro) 
                    VALUES (:nombre, :telefono, NOW())";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':nombre', $datos['nombre'], PDO::PARAM_STR);
            $stmt->bindParam(':telefono', $datos['telefono'], PDO::PARAM_STR);

            $stmt->execute();
            $id = $this->pdo->lastInsertId();

            $this->pdo->commit();

            return [
                'error' => false,
                'mensaje' => 'Cliente registrado correctamente',
                'id' => $id,
                'nombre' => $datos['nombre'],
                'telefono' => $datos['telefono']
            ];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return [
                'error' => true,
                'mensaje' => 'Error al registrar cliente: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Actualiza un cliente existente
     */
    public function actualizar($id, $datos)
    {
        try {
            $this->pdo->beginTransaction();

            $sql = "UPDATE clientes SET ";
            $params = [':id' => $id];
            $updates = [];

            if (isset($datos['nombre'])) {
                $updates[] = "nombre = :nombre";
                $params[':nombre'] = $datos['nombre'];
            }

            if (isset($datos['notas'])) {
                $updates[] = "notas = :notas";
                $params[':notas'] = $datos['notas'];
            }

            $sql .= implode(', ', $updates) . ", ultima_actualizacion = NOW() WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $this->pdo->commit();

            return ['error' => false, 'mensaje' => 'Cliente actualizado correctamente'];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['error' => true, 'mensaje' => 'Error al actualizar cliente: ' . $e->getMessage()];
        }
    }

    /**
     * Busca clientes con autocompletado por teléfono o nombre
     */
    public function buscarAutocompletado($termino)
    {
        try {
            $termino = "%$termino%"; // Para búsqueda parcial

            $stmt = $this->pdo->prepare("
                SELECT id, telefono, nombre 
                FROM clientes 
                WHERE telefono LIKE :termino 
                OR nombre LIKE :termino
                ORDER BY ultima_actualizacion DESC 
                LIMIT 10
            ");
            $stmt->bindParam(':termino', $termino, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error en la búsqueda: ' . $e->getMessage()];
        }
    }

    /**
     * Obtiene los últimos servicios de un cliente
     */
    public function obtenerUltimosServicios($cliente_id, $limite = 5)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.id, s.fecha_solicitud, d.direccion, 
                       v.numero as vehiculo, s.estado
                FROM servicios s
                LEFT JOIN direcciones d ON s.direccion_id = d.id
                LEFT JOIN vehiculos v ON s.vehiculo_id = v.id
                WHERE s.cliente_id = :cliente_id
                ORDER BY s.fecha_solicitud DESC
                LIMIT :limite
            ");
            $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al obtener servicios: ' . $e->getMessage()];
        }
    }

   /**
 * Lista clientes con paginación y filtro
 *
 * @param string $filtro Texto para filtrar por nombre o teléfono
 * @param int $limite Cantidad de registros por página
 * @param int $offset Desplazamiento para paginación
 * @return array Lista de clientes
 */
public function listarPaginado($filtro = '', $limite = 15, $offset = 0)
{
    try {
        $condicion = '';
        $params = [];
        
        if (!empty($filtro)) {
            $condicion = " WHERE nombre LIKE :filtro OR telefono LIKE :filtro ";
            $params[':filtro'] = '%' . $filtro . '%';
        }
        
        $sql = "SELECT * FROM clientes $condicion ORDER BY id DESC LIMIT :limite OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        
        if (!empty($filtro)) {
            $stmt->bindValue(':filtro', '%' . $filtro . '%', PDO::PARAM_STR);
        }
        
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log('Error al listar clientes paginados: ' . $e->getMessage());
        return [];
    }
}

/**
 * Cuenta el total de clientes según un filtro
 *
 * @param string $filtro Texto para filtrar
 * @return int Total de clientes
 */
public function contarTotal($filtro = '')
{
    try {
        $condicion = '';
        $params = [];
        
        if (!empty($filtro)) {
            $condicion = " WHERE nombre LIKE :filtro OR telefono LIKE :filtro ";
            $params[':filtro'] = '%' . $filtro . '%';
        }
        
        $sql = "SELECT COUNT(*) as total FROM clientes $condicion";
        
        $stmt = $this->pdo->prepare($sql);
        
        if (!empty($filtro)) {
            $stmt->bindValue(':filtro', '%' . $filtro . '%', PDO::PARAM_STR);
        }
        
        $stmt->execute();
        
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$resultado['total'];
        
    } catch (PDOException $e) {
        error_log('Error al contar clientes: ' . $e->getMessage());
        return 0;
    }
}
}
