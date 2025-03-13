<?php
class Usuario {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Registra un nuevo usuario en el sistema
     * 
     * @param array $datos Datos del usuario
     * @return array Resultado de la operación
     */
    public function registrar($datos) {
        // Verificar si el email ya existe
        if ($this->emailExiste($datos['email'])) {
            return ['error' => true, 'mensaje' => 'El correo electrónico ya está registrado'];
        }
        
        // Verificar si el username ya existe
        if ($this->usernameExiste($datos['username'])) {
            return ['error' => true, 'mensaje' => 'El nombre de usuario ya está en uso'];
        }
        
        try {
            // Encriptar la contraseña
            $password_hash = password_hash($datos['password'], PASSWORD_DEFAULT);
            
            // CAMBIO: Usar 'estado' en lugar de 'activo'
            $sql = "INSERT INTO usuarios (nombre, apellidos, email, username, password, telefono, rol, estado, fecha_registro)
                    VALUES (:nombre, :apellidos, :email, :username, :password, :telefono, :rol, 'activo', NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            
            // Asignar valores
            $stmt->bindParam(':nombre', $datos['nombre']);
            $stmt->bindParam(':apellidos', $datos['apellidos']);
            $stmt->bindParam(':email', $datos['email']);
            $stmt->bindParam(':username', $datos['username']);
            $stmt->bindParam(':password', $password_hash);
            $stmt->bindParam(':telefono', $datos['telefono']);
            $stmt->bindParam(':rol', $datos['rol']);
            
            // Ejecutar consulta
            $stmt->execute();
            
            return [
                'error' => false, 
                'mensaje' => 'Usuario registrado correctamente',
                'id' => $this->pdo->lastInsertId()
            ];
            
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al registrar usuario: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obtiene la lista de todos los usuarios
     * 
     * @return array
     */
    public function listar() {
        try {
            $sql = "SELECT * FROM usuarios ORDER BY fecha_registro DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al listar usuarios: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obtiene los datos de un usuario específico
     * 
     * @param int $id
     * @return array
     */
    public function obtener($id) {
        try {
            $sql = "SELECT * FROM usuarios WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$usuario) {
                return ['error' => true, 'mensaje' => 'Usuario no encontrado'];
            }
            
            return $usuario;
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al obtener usuario: ' . $e->getMessage()];
        }
    }
    
    /**
     * Actualiza la información de un usuario
     * 
     * @param int $id
     * @param array $datos
     * @return array
     */
    public function actualizar($id, $datos) {
        try {
            // Verificar si el email ya existe (excepto para este usuario)
            $sql = "SELECT COUNT(*) FROM usuarios WHERE email = :email AND id != :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':email', $datos['email']);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->fetchColumn() > 0) {
                return ['error' => true, 'mensaje' => 'El correo electrónico ya está registrado por otro usuario'];
            }
            
            // Verificar si el username ya existe (excepto para este usuario)
            $sql = "SELECT COUNT(*) FROM usuarios WHERE username = :username AND id != :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':username', $datos['username']);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->fetchColumn() > 0) {
                return ['error' => true, 'mensaje' => 'El nombre de usuario ya está en uso por otro usuario'];
            }
            
            // Actualizar usuario
            $sql = "UPDATE usuarios SET 
                    nombre = :nombre,
                    apellidos = :apellidos,
                    email = :email,
                    username = :username,
                    telefono = :telefono,
                    rol = :rol
                    WHERE id = :id";
                    
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':nombre', $datos['nombre']);
            $stmt->bindParam(':apellidos', $datos['apellidos']);
            $stmt->bindParam(':email', $datos['email']);
            $stmt->bindParam(':username', $datos['username']);
            $stmt->bindParam(':telefono', $datos['telefono']);
            $stmt->bindParam(':rol', $datos['rol']);
            $stmt->bindParam(':id', $id);
            
            $stmt->execute();
            
            return ['error' => false, 'mensaje' => 'Usuario actualizado correctamente'];
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al actualizar usuario: ' . $e->getMessage()];
        }
    }
    
    /**
     * Cambia el estado de un usuario (activo/inactivo)
     * 
     * @param int $id
     * @param int $estado 1 para activo, 0 para inactivo
     * @return array
     */
    public function cambiarEstado($id, $estado) {
        try {
            // Validar que el usuario exista
            $sql = "SELECT COUNT(*) FROM usuarios WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->fetchColumn() == 0) {
                return ['error' => true, 'mensaje' => 'Usuario no encontrado'];
            }
            
            // CAMBIO: Usar directamente los valores 'activo' o 'inactivo'
            // Validar que el estado sea correcto
            if (!in_array($estado, ['activo', 'inactivo'])) {
                return ['error' => true, 'mensaje' => 'Estado no válido'];
            }
            
            // Cambiar estado
            $sql = "UPDATE usuarios SET estado = :estado WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':estado', $estado);
            $stmt->bindParam(':id', $id);
            
            $stmt->execute();
            
            return [
                'error' => false, 
                'mensaje' => ($estado == 'activo') ? 'Usuario activado correctamente' : 'Usuario desactivado correctamente'
            ];
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al cambiar estado: ' . $e->getMessage()];
        }
    }
    
    /**
     * Restablece la contraseña de un usuario
     * 
     * @param int $id
     * @param string $password
     * @return array
     */
    public function resetPassword($id, $password) {
        try {
            // Validar que el usuario exista
            $sql = "SELECT COUNT(*) FROM usuarios WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->fetchColumn() == 0) {
                return ['error' => true, 'mensaje' => 'Usuario no encontrado'];
            }
            
            // Encriptar la nueva contraseña
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Actualizar contraseña
            $sql = "UPDATE usuarios SET password = :password WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':password', $password_hash);
            $stmt->bindParam(':id', $id);
            
            $stmt->execute();
            
            return ['error' => false, 'mensaje' => 'Contraseña restablecida correctamente'];
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al restablecer contraseña: ' . $e->getMessage()];
        }
    }
    
    /**
     * Verifica si ya existe un usuario con el email proporcionado
     * 
     * @param string $email
     * @return bool
     */
    private function emailExiste($email) {
        $sql = "SELECT COUNT(*) FROM usuarios WHERE email = :email";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Verifica si ya existe un usuario con el username proporcionado
     * 
     * @param string $username
     * @return bool
     */
    private function usernameExiste($username) {
        $sql = "SELECT COUNT(*) FROM usuarios WHERE username = :username";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Verifica las credenciales de un usuario para el inicio de sesión
     * 
     * @param string $username
     * @param string $password
     * @return array
     */
    public function verificarCredenciales($username, $password) {
        try {
            // El username puede ser el nombre de usuario o el email
            $sql = "SELECT * FROM usuarios WHERE (username = :username OR email = :email) AND estado = 'activo'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $username);
            $stmt->execute();
            
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$usuario) {
                return ['exito' => false, 'mensaje' => 'Credenciales incorrectas o usuario inactivo'];
            }
            
            // Verificar la contraseña
            if (password_verify($password, $usuario['password'])) {
                return [
                    'exito' => true,
                    'mensaje' => 'Inicio de sesión exitoso',
                    'usuario' => $usuario
                ];
            } else {
                return ['exito' => false, 'mensaje' => 'Contraseña incorrecta'];
            }
        } catch (PDOException $e) {
            return ['exito' => false, 'mensaje' => 'Error al verificar credenciales: ' . $e->getMessage()];
        }
    }
    
    /**
     * Genera un token de "recordar" para el usuario
     * 
     * @param int $usuario_id
     * @return string|false
     */
    public function generarTokenRecuerdo($usuario_id) {
        try {
            // Generar token aleatorio
            $token = bin2hex(random_bytes(32));
            $hash_token = password_hash($token, PASSWORD_DEFAULT);
            
            // Guardar en la base de datos
            $sql = "UPDATE usuarios SET token_recuperacion = :token, fecha_token = NOW() WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':token', $hash_token);
            $stmt->bindParam(':id', $usuario_id);
            $stmt->execute();
            
            // Devolver el token para guardarlo en cookie
            return $token;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Verifica un token de "recordar" para inicio de sesión automático
     * 
     * @param string $token
     * @return array|false
     */
    public function verificarTokenRecuerdo($token) {
        try {
            // Buscar usuarios con token
            $sql = "SELECT * FROM usuarios WHERE fecha_token > (NOW() - INTERVAL 30 DAY) AND estado = 'activo'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            while ($usuario = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Verificar si el token coincide
                if ($usuario['token_recuperacion'] && password_verify($token, $usuario['token_recuperacion'])) {
                    return [
                        'exito' => true,
                        'usuario' => $usuario
                    ];
                }
            }
            
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Actualiza la fecha de último acceso del usuario
     * 
     * @param int $usuario_id
     * @return bool
     */
    public function actualizarUltimoAcceso($usuario_id) {
        try {
            $sql = "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $usuario_id);
            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Elimina un usuario (no recomendado, mejor usar cambiarEstado)
     * 
     * @param int $id
     * @return array
     */
    public function eliminar($id) {
        try {
            // Verificar si el usuario existe
            $sql = "SELECT COUNT(*) FROM usuarios WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->fetchColumn() == 0) {
                return ['error' => true, 'mensaje' => 'Usuario no encontrado'];
            }
            
            // Eliminar usuario
            $sql = "DELETE FROM usuarios WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            return ['error' => false, 'mensaje' => 'Usuario eliminado correctamente'];
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al eliminar usuario: ' . $e->getMessage()];
        }
    }
/**
     * Obtiene estadísticas de servicios por usuario en un rango de fechas
     */
    public function obtenerEstadisticasUsuario($usuario_id, $fecha_inicio, $fecha_fin) {
        try {
            // Validar parámetros
            $fecha_inicio = $fecha_inicio . ' 00:00:00';
            $fecha_fin = $fecha_fin . ' 23:59:59';
            
            // Estadísticas básicas de servicios
            $sql = "
                SELECT 
                    COUNT(*) as total_servicios,
                    SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) as servicios_cancelados,
                    SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) as servicios_finalizados,
                    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as servicios_pendientes,
                    SUM(CASE WHEN estado = 'asignado' THEN 1 ELSE 0 END) as servicios_asignados,
                    MIN(fecha_solicitud) as primera_solicitud,
                    MAX(fecha_solicitud) as ultima_solicitud
                FROM servicios
                WHERE usuario_id = :usuario_id
                AND fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->bindParam(':fecha_inicio', $fecha_inicio, PDO::PARAM_STR);
            $stmt->bindParam(':fecha_fin', $fecha_fin, PDO::PARAM_STR);
            $stmt->execute();
            
            $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Obtener detalles de servicios para este usuario en el rango
            $sql_servicios = "
                SELECT 
                    s.id,
                    s.cliente_id,
                    s.vehiculo_id,
                    s.direccion_id,
                    s.estado,
                    s.fecha_solicitud,
                    s.fecha_asignacion,
                    s.fecha_fin,
                    s.observaciones,
                    c.telefono as cliente_telefono,
                    c.nombre as cliente_nombre,
                    v.numero_movil,
                    v.placa,
                    d.direccion
                FROM servicios s
                LEFT JOIN clientes c ON s.cliente_id = c.id
                LEFT JOIN vehiculos v ON s.vehiculo_id = v.id
                LEFT JOIN direcciones d ON s.direccion_id = d.id
                WHERE s.usuario_id = :usuario_id
                AND s.fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin
                ORDER BY s.fecha_solicitud DESC
            ";
            
            $stmt_servicios = $this->pdo->prepare($sql_servicios);
            $stmt_servicios->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt_servicios->bindParam(':fecha_inicio', $fecha_inicio, PDO::PARAM_STR);
            $stmt_servicios->bindParam(':fecha_fin', $fecha_fin, PDO::PARAM_STR);
            $stmt_servicios->execute();
            
            $servicios = $stmt_servicios->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular promedio de tiempo de asignación
            $sql_tiempo = "
                SELECT 
                    AVG(TIMESTAMPDIFF(MINUTE, fecha_solicitud, fecha_asignacion)) as promedio_asignacion,
                    AVG(TIMESTAMPDIFF(MINUTE, fecha_asignacion, fecha_fin)) as promedio_servicio
                FROM servicios
                WHERE usuario_id = :usuario_id
                AND fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin
                AND estado = 'finalizado'
                AND fecha_asignacion IS NOT NULL
                AND fecha_fin IS NOT NULL
            ";
            
            $stmt_tiempo = $this->pdo->prepare($sql_tiempo);
            $stmt_tiempo->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt_tiempo->bindParam(':fecha_inicio', $fecha_inicio, PDO::PARAM_STR);
            $stmt_tiempo->bindParam(':fecha_fin', $fecha_fin, PDO::PARAM_STR);
            $stmt_tiempo->execute();
            
            $tiempos = $stmt_tiempo->fetch(PDO::FETCH_ASSOC);
            
            // Combinar resultados
            $resultado = [
                'estadisticas' => $estadisticas,
                'servicios' => $servicios,
                'tiempos' => $tiempos,
                'usuario_id' => $usuario_id,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin
            ];
            
            return $resultado;
            
        } catch (PDOException $e) {
            return [
                'error' => true,
                'mensaje' => 'Error al obtener estadísticas de usuario: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtiene la lista de usuarios con conteo básico de servicios
     */
    public function obtenerResumenUsuarios($fecha_inicio, $fecha_fin) {
        try {
            // Validar parámetros
            $fecha_inicio = $fecha_inicio . ' 00:00:00';
            $fecha_fin = $fecha_fin . ' 23:59:59';
            
            $sql = "
                SELECT 
                    u.id,
                    u.nombre,
                    u.apellidos,
                    u.username,
                    u.email,
                    u.rol,
                    COUNT(s.id) as total_servicios,
                    SUM(CASE WHEN s.estado = 'cancelado' THEN 1 ELSE 0 END) as servicios_cancelados,
                    SUM(CASE WHEN s.estado = 'finalizado' THEN 1 ELSE 0 END) as servicios_finalizados
                FROM usuarios u
                LEFT JOIN servicios s ON u.id = s.usuario_id AND s.fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin
                GROUP BY u.id
                ORDER BY total_servicios DESC
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':fecha_inicio', $fecha_inicio, PDO::PARAM_STR);
            $stmt->bindParam(':fecha_fin', $fecha_fin, PDO::PARAM_STR);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            return [
                'error' => true,
                'mensaje' => 'Error al obtener resumen de usuarios: ' . $e->getMessage()
            ];
        }
    }



}
?>