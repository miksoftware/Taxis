<?php
require_once __DIR__ . '/../Models/Usuario.php';

class UsuarioController
{
    private $usuarioModel;

    public function __construct($pdo)
    {
        $this->usuarioModel = new Usuario($pdo);
    }

    /**
     * Procesa el registro de un nuevo usuario
     * 
     * @param array $datos
     * @return array
     */
    public function registrar($datos)
    {
        // Validar datos
        $errores = $this->validarDatos($datos);

        if (count($errores) > 0) {
            return ['error' => true, 'mensaje' => 'Error en los datos', 'errores' => $errores];
        }

        // Si no hay errores, registrar el usuario
        return $this->usuarioModel->registrar($datos);
    }

    /**
     * Obtiene la lista de todos los usuarios
     * 
     * @return array
     */
    public function listar()
    {
        return $this->usuarioModel->listar();
    }

    /**
     * Obtiene los datos de un usuario específico
     * 
     * @param int $id
     * @return array
     */
    public function obtener($id)
    {
        if (empty($id) || !is_numeric($id)) {
            return ['error' => true, 'mensaje' => 'ID de usuario no válido'];
        }

        return $this->usuarioModel->obtener($id);
    }

    

    /**
     * Actualiza la información de un usuario
     * 
     * @param int $id
     * @param array $datos
     * @return array
     */
    public function actualizar($id, $datos)
    {
        // Validar ID
        if (empty($id) || !is_numeric($id)) {
            return ['error' => true, 'mensaje' => 'ID de usuario no válido'];
        }

        // Validar datos básicos (sin validar contraseña)
        $errores = $this->validarDatosActualizacion($datos);

        if (count($errores) > 0) {
            return ['error' => true, 'mensaje' => 'Error en los datos', 'errores' => $errores];
        }

        // Actualizar usuario
        return $this->usuarioModel->actualizar($id, $datos);
    }

    

    /**
     * Cambia el estado de un usuario (activo/inactivo)
     * 
     * @param int $id
     * @param string $estado 'activo' o 'inactivo'
     * @return array
     */
    public function cambiarEstado($id, $estado)
    {
        // Validar ID
        if (empty($id) || !is_numeric($id)) {
            return ['error' => true, 'mensaje' => 'ID de usuario no válido'];
        }

        // Validar estado
        if (!in_array($estado, ['activo', 'inactivo'])) {
            return ['error' => true, 'mensaje' => 'Estado no válido'];
        }

        return $this->usuarioModel->cambiarEstado($id, $estado);
    }

    /**
     * Restablece la contraseña de un usuario
     * 
     * @param int $id
     * @param string $password
     * @return array
     */
    public function resetPassword($id, $password)
    {
        // Validar ID
        if (empty($id) || !is_numeric($id)) {
            return ['error' => true, 'mensaje' => 'ID de usuario no válido'];
        }

        // Validar contraseña
        if (empty($password) || strlen($password) < 8) {
            return ['error' => true, 'mensaje' => 'La contraseña debe tener al menos 8 caracteres'];
        }

        return $this->usuarioModel->resetPassword($id, $password);
    }

    /**
     * Valida los datos del formulario de registro
     * 
     * @param array $datos
     * @return array Errores encontrados
     */
    private function validarDatos($datos)
    {
        $errores = [];

        // Validaciones de nombre, email, etc. (mantener igual)...

        // CAMBIO: Validar rol para permitir solo 'administrador' y 'operador'
        if (empty($datos['rol']) || !in_array($datos['rol'], ['administrador', 'operador'])) {
            $errores['rol'] = 'Debe seleccionar un rol válido (administrador u operador)';
        }

        return $errores;
    }

    /**
     * Valida los datos para actualización (sin validar contraseña)
     * 
     * @param array $datos
     * @return array Errores encontrados
     */
    private function validarDatosActualizacion($datos)
    {
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

        // Validar rol
        if (empty($datos['rol']) || !in_array($datos['rol'], ['administrador', 'operador'])) {
            $errores['rol'] = 'Debe seleccionar un rol válido (administrador u operador)';
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
    public function login($username, $password)
    {
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
    public function generarTokenRecuerdo($usuario_id)
    {
        return $this->usuarioModel->generarTokenRecuerdo($usuario_id);
    }

    /**
     * Verifica un token de recordar y devuelve los datos del usuario
     * 
     * @param string $token
     * @return array|false
     */
    public function verificarTokenRecuerdo($token)
    {
        return $this->usuarioModel->verificarTokenRecuerdo($token);
    }

    /**
     * Actualiza la fecha de último acceso del usuario
     * 
     * @param int $usuario_id
     * @return bool
     */
    public function actualizarUltimoAcceso($usuario_id)
    {
        return $this->usuarioModel->actualizarUltimoAcceso($usuario_id);
    }
}
