<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Views\Usuarios.php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar que el usuario esté autenticado y sea administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'administrador') {
    $_SESSION['mensaje'] = "Acceso denegado. Debe iniciar sesión como administrador.";
    $_SESSION['tipo_mensaje'] = "danger";
    header('Location: Login.php');
    exit;
}

// Incluir el encabezado
require_once 'Layouts/header.php';

// Inicializar conexión a la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir el controlador de usuarios
require_once '../Controllers/UsuarioController.php';
$usuarioController = new UsuarioController($pdo);

// Obtener todos los usuarios
$usuarios = $usuarioController->listar();

// Mensaje de operaciones
$mensaje = isset($_SESSION['mensaje']) ? $_SESSION['mensaje'] : null;
$tipo_mensaje = isset($_SESSION['tipo_mensaje']) ? $_SESSION['tipo_mensaje'] : null;

// Limpiar mensajes de sesión después de mostrarlos
unset($_SESSION['mensaje']);
unset($_SESSION['tipo_mensaje']);
?>

<div class="welcome-message">
    <h2>Gestión de Usuarios</h2>
    <p>Administre todos los usuarios del sistema y sus permisos.</p>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="bi bi-people me-2"></i> Lista de Usuarios
        </h5>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevoUsuarioModal">
            <i class="bi bi-person-plus-fill"></i> Nuevo Usuario
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="tablaUsuarios" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Fecha Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (is_array($usuarios) && !isset($usuarios['error'])): ?>
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($usuario['id']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellidos']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['username']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                <td>
                                    <?php if ($usuario['rol'] === 'administrador'): ?>
                                        <span class="badge bg-danger">Administrador</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary">Operador</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($usuario['estado'] === 'activo'): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary btn-sm"
                                            onclick="editarUsuario(<?php echo $usuario['id']; ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <?php if ($usuario['id'] != $_SESSION['usuario_id']): // No permitir cambiar estado del propio usuario 
                                        ?>
                                            <?php if ($usuario['estado'] === 'activo'): ?>
                                                <button type="button" class="btn btn-warning btn-sm"
                                                    onclick="cambiarEstado(<?php echo $usuario['id']; ?>, 'inactivo', '<?php echo htmlspecialchars($usuario['username']); ?>')">
                                                    <i class="bi bi-person-x"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-success btn-sm"
                                                    onclick="cambiarEstado(<?php echo $usuario['id']; ?>, 'activo', '<?php echo htmlspecialchars($usuario['username']); ?>')">
                                                    <i class="bi bi-person-check"></i>
                                                </button>
                                            <?php endif; ?>

                                            <button type="button" class="btn btn-info btn-sm"
                                                onclick="resetPassword(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['username']); ?>')">
                                                <i class="bi bi-key"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">No hay usuarios registrados</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nuevo Usuario -->
<div class="modal fade" id="nuevoUsuarioModal" tabindex="-1" aria-labelledby="nuevoUsuarioModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formNuevoUsuario" action="../Processings/procesar_usuario.php" method="post" class="needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="nuevoUsuarioModalLabel">
                        <i class="bi bi-person-plus-fill me-2"></i> Nuevo Usuario
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                            <div class="invalid-feedback">El nombre es obligatorio</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="apellidos" class="form-label">Apellidos <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="apellidos" name="apellidos" required>
                            <div class="invalid-feedback">Los apellidos son obligatorios</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required>
                        <div class="invalid-feedback">Ingrese un email válido</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Nombre de usuario <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" required>
                            <div class="invalid-feedback">El nombre de usuario es obligatorio</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="telefono" name="telefono">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Contraseña <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="6">
                            <div class="form-text">Mínimo 6 caracteres</div>
                            <div class="invalid-feedback">La contraseña debe tener al menos 6 caracteres</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirmPassword" class="form-label">Confirmar contraseña <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                            <div class="invalid-feedback">Las contraseñas deben coincidir</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="rol" class="form-label">Rol <span class="text-danger">*</span></label>
                        <select class="form-select" id="rol" name="rol" required>
                            <option value="">Seleccione un rol</option>
                            <option value="administrador">Administrador</option>
                            <option value="operador">Operador</option>
                        </select>
                        <div class="invalid-feedback">Seleccione un rol</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="accion" value="crear" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Usuario -->
<div class="modal fade" id="editarUsuarioModal" tabindex="-1" aria-labelledby="editarUsuarioModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formEditarUsuario" action="../Processings/procesar_usuario.php" method="post" class="needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="editarUsuarioModalLabel">
                        <i class="bi bi-pencil-square me-2"></i> Editar Usuario
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editar_id" name="id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editar_nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editar_nombre" name="nombre" required>
                            <div class="invalid-feedback">El nombre es obligatorio</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editar_apellidos" class="form-label">Apellidos <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editar_apellidos" name="apellidos" required>
                            <div class="invalid-feedback">Los apellidos son obligatorios</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="editar_email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="editar_email" name="email" required>
                        <div class="invalid-feedback">Ingrese un email válido</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editar_username" class="form-label">Nombre de usuario <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editar_username" name="username" required>
                            <div class="invalid-feedback">El nombre de usuario es obligatorio</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editar_telefono" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="editar_telefono" name="telefono">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="editar_rol" class="form-label">Rol <span class="text-danger">*</span></label>
                        <select class="form-select" id="editar_rol" name="rol" required>
                            <option value="administrador">Administrador</option>
                            <option value="operador">Operador</option>
                        </select>
                        <div class="invalid-feedback">Seleccione un rol</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="accion" value="actualizar" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Cambiar Estado -->
<div class="modal fade" id="cambiarEstadoModal" tabindex="-1" aria-labelledby="cambiarEstadoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formCambiarEstado" action="../Processings/procesar_usuario.php" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="cambiarEstadoModalLabel">
                        <i class="bi bi-toggle2-off me-2"></i> <span id="titulo_estado">Cambiar Estado</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="estado_id" name="id">
                    <input type="hidden" id="estado_valor" name="estado">
                    <p>¿Está seguro que desea <span id="accion_estado">cambiar el estado</span> del usuario <span id="nombre_usuario" class="fw-bold"></span>?</p>

                    <div id="advertencia_desactivar" class="alert alert-warning mt-3" style="display: none;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        El usuario no podrá iniciar sesión mientras esté desactivado.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="accion" value="cambiar_estado" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Confirmar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Restablecer Contraseña -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formResetPassword" action="../Processings/procesar_usuario.php" method="post" class="needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="resetPasswordModalLabel">
                        <i class="bi bi-key me-2"></i> Restablecer Contraseña
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="reset_id" name="id">
                    <p>Establecer nueva contraseña para el usuario <span id="reset_username" class="fw-bold"></span>:</p>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nueva contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                        <div class="form-text">Mínimo 6 caracteres</div>
                        <div class="invalid-feedback">La contraseña debe tener al menos 6 caracteres</div>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_new_password" class="form-label">Confirmar nueva contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
                        <div class="invalid-feedback">Las contraseñas deben coincidir</div>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill"></i>
                        La contraseña debe tener al menos 6 caracteres.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="accion" value="reset_password" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Restablecer Contraseña
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts para la funcionalidad de los usuarios -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar DataTable si está disponible
        if (typeof $.fn.DataTable !== 'undefined') {
            $('#tablaUsuarios').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json'
                },
                responsive: true
            });
        }

        // Validación de formularios con Bootstrap
        const forms = document.querySelectorAll('.needs-validation');

        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                // Validación adicional para contraseñas
                if (form.id === 'formNuevoUsuario') {
                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirmPassword').value;

                    if (password !== confirmPassword) {
                        document.getElementById('confirmPassword').setCustomValidity('Las contraseñas no coinciden');
                        event.preventDefault();
                        event.stopPropagation();
                    } else {
                        document.getElementById('confirmPassword').setCustomValidity('');
                    }
                }

                if (form.id === 'formResetPassword') {
                    const password = document.getElementById('new_password').value;
                    const confirmPassword = document.getElementById('confirm_new_password').value;

                    if (password !== confirmPassword) {
                        document.getElementById('confirm_new_password').setCustomValidity('Las contraseñas no coinciden');
                        event.preventDefault();
                        event.stopPropagation();
                    } else {
                        document.getElementById('confirm_new_password').setCustomValidity('');
                    }
                }

                form.classList.add('was-validated');
            }, false);
        });
    });

    function editarUsuario(id) {
        // Obtener datos del usuario por AJAX
        fetch('../Processings/obtener_usuario.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.mensaje);
                    return;
                }

                // Llenar el formulario
                document.getElementById('editar_id').value = data.id;
                document.getElementById('editar_nombre').value = data.nombre;
                document.getElementById('editar_apellidos').value = data.apellidos;
                document.getElementById('editar_email').value = data.email;
                document.getElementById('editar_username').value = data.username;
                document.getElementById('editar_telefono').value = data.telefono || '';
                document.getElementById('editar_rol').value = data.rol;

                // Mostrar el modal
                new bootstrap.Modal(document.getElementById('editarUsuarioModal')).show();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al cargar datos del usuario');
            });
    }

    function cambiarEstado(id, estado, username) {
        document.getElementById('estado_id').value = id;
        document.getElementById('estado_valor').value = estado;
        document.getElementById('nombre_usuario').textContent = username;

        // Configurar textos según el estado
        if (estado === 'activo') {
            document.getElementById('titulo_estado').textContent = "Activar Usuario";
            document.getElementById('accion_estado').textContent = "activar";
            document.getElementById('advertencia_desactivar').style.display = 'none';
        } else {
            document.getElementById('titulo_estado').textContent = "Desactivar Usuario";
            document.getElementById('accion_estado').textContent = "desactivar";
            document.getElementById('advertencia_desactivar').style.display = 'block';
        }

        new bootstrap.Modal(document.getElementById('cambiarEstadoModal')).show();
    }

    function resetPassword(id, username) {
        document.getElementById('reset_id').value = id;
        document.getElementById('reset_username').textContent = username;

        // Limpiar campos de contraseña
        document.getElementById('new_password').value = '';
        document.getElementById('confirm_new_password').value = '';
        document.getElementById('confirm_new_password').setCustomValidity('');

        new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
    }
</script>

<?php
// Incluir el pie de página
require_once 'Layouts/footer.php';
?>