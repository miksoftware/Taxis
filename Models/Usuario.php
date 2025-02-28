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
            
            // Preparar la consulta
            $sql = "INSERT INTO usuarios (nombre, apellidos, email, username, password, telefono, rol, fecha_registro)
                    VALUES (:nombre, :apellidos, :email, :username, :password, :telefono, :rol, NOW())";
            
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
            
            return ['error' => false, 'mensaje' => 'Usuario registrado correctamente'];
            
        } catch (PDOException $e) {
            return ['error' => true, 'mensaje' => 'Error al registrar usuario: ' . $e->getMessage()];
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
            $sql = "SELECT * FROM usuarios WHERE (username = :username OR email = :email) AND estado = 1";
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
            $sql = "SELECT * FROM usuarios WHERE fecha_token > (NOW() - INTERVAL 30 DAY) AND estado = 1";
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




}
?>