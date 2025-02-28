<?php
require_once __DIR__ . '/../Models/Usuario.php';

class UsuarioController {
    private $usuarioModel;
    
    public function __construct($pdo) {
        $this->usuarioModel = new Usuario($pdo);
    }
    
    /**
     * Procesa el registro de un nuevo usuario
     * 
     * @param array $datos
     * @return array
     */
    public function registrar($datos) {
        // Validar datos
        $errores = $this->validarDatos($datos);
        
        if (count($errores) > 0) {
            return ['error' => true, 'mensaje' => 'Error en los datos', 'errores' => $errores];
        }
        
        // Si no hay errores, registrar el usuario
        return $this->usuarioModel->registrar($datos);
    }
    
    /**
     * Valida los datos del formulario de registro
     * 
     * @param array $datos
     * @return array Errores encontrados
     */
    private function validarDatos($datos) {
        $errores = [];
        
        // Validar nombre
        if (empty($datos['nombre']) || strlen($datos['nombre']) < 2) {
            $errores['nombre'] = 'El nombre es requerido y debe tener al menos 2 caracteres';
        }
        
        // Validar apellidos
        if (empty($datos['apellidos']) || strlen($datos['apellidos']) < 2) {
            $errores['apellidos'] = 'Los apellidos son requeridos y deben tener al menos 2 caracteres';
        }
        
        // Validar email
        if (empty($datos['email']) || !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            $errores['email'] = 'Debe proporcionar un email válido';
        }
        
        // Validar username
        if (empty($datos['username']) || strlen($datos['username']) < 4) {
            $errores['username'] = 'El nombre de usuario es requerido y debe tener al menos 4 caracteres';
        }
        
        // Validar password
        if (empty($datos['password']) || strlen($datos['password']) < 6) {
            $errores['password'] = 'La contraseña es requerida y debe tener al menos 6 caracteres';
        }
        
        // Validar confirmación de password
        if ($datos['password'] !== $datos['confirmPassword']) {
            $errores['confirmPassword'] = 'Las contraseñas no coinciden';
        }
        
        // Validar rol
        if (empty($datos['rol']) || !in_array($datos['rol'], ['administrador', 'operador'])) {
            $errores['rol'] = 'Debe seleccionar un rol válido';
        }
        
        return $errores;
    }


    /**
     * Procesa el inicio de sesión de un usuario
     * 
     * @param string $username
     * @param string $password
     * @return array
     */
    public function login($username, $password) {
        // Validación básica
        if (empty($username) || empty($password)) {
            return ['exito' => false, 'mensaje' => 'Debes proporcionar usuario y contraseña'];
        }
        
        // Verificar credenciales
        return $this->usuarioModel->verificarCredenciales($username, $password);
    }
    
    /**
     * Genera un token para la función "recordarme"
     * 
     * @param int $usuario_id
     * @return string|false
     */
    public function generarTokenRecuerdo($usuario_id) {
        return $this->usuarioModel->generarTokenRecuerdo($usuario_id);
    }
    
    /**
     * Verifica un token de recordar y devuelve los datos del usuario
     * 
     * @param string $token
     * @return array|false
     */
    public function verificarTokenRecuerdo($token) {
        return $this->usuarioModel->verificarTokenRecuerdo($token);
    }
    
    /**
     * Actualiza la fecha de último acceso del usuario
     * 
     * @param int $usuario_id
     * @return bool
     */
    public function actualizarUltimoAcceso($usuario_id) {
        return $this->usuarioModel->actualizarUltimoAcceso($usuario_id);
    }

}
?>