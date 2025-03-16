<?php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Recuperar datos del formulario si existen
$form_data = isset($_SESSION['form_data']) ? $_SESSION['form_data'] : [];
$errores = isset($_SESSION['errores']) ? $_SESSION['errores'] : [];

// Limpiar sesión después de recuperar datos
unset($_SESSION['form_data']);
unset($_SESSION['errores']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Usuario - Taxi Diamantes</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/register.css">
</head>

<body>
    <div class="container">
        <?php
        // Mostrar mensaje de error si existe
        if (isset($_SESSION['error_mensaje'])) {
            echo '<div class="alert alert-danger mt-3">' . $_SESSION['error_mensaje'] . '</div>';
            unset($_SESSION['error_mensaje']);
        }
        
        // Mostrar mensaje de éxito si existe
        if (isset($_SESSION['success_mensaje'])) {
            echo '<div class="alert alert-success mt-3">' . $_SESSION['success_mensaje'] . '</div>';
            unset($_SESSION['success_mensaje']);
        }
        ?>
        
        <div class="row registration-container bg-white">
            <div class="col-md-7 form-side">
                <h2 class="mb-4 text-center">Registro de Usuario</h2>
                <p class="text-muted text-center mb-4">Complete el formulario para crear su cuenta en Taxi Diamantes</p>

                <form id="registrationForm" action="Processings/procesar_registro.php" method="post">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nombre" class="form-label">Nombre</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control <?php echo isset($errores['nombre']) ? 'is-invalid' : ''; ?>" 
                                    id="nombre" name="nombre" 
                                    value="<?php echo isset($form_data['nombre']) ? htmlspecialchars($form_data['nombre']) : ''; ?>"
                                    required>
                                <?php if (isset($errores['nombre'])): ?>
                                    <div class="invalid-feedback"><?php echo $errores['nombre']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="apellidos" class="form-label">Apellidos</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control <?php echo isset($errores['apellidos']) ? 'is-invalid' : ''; ?>" 
                                    id="apellidos" name="apellidos" 
                                    value="<?php echo isset($form_data['apellidos']) ? htmlspecialchars($form_data['apellidos']) : ''; ?>"
                                    required>
                                <?php if (isset($errores['apellidos'])): ?>
                                    <div class="invalid-feedback"><?php echo $errores['apellidos']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control <?php echo isset($errores['email']) ? 'is-invalid' : ''; ?>" 
                                id="email" name="email" 
                                value="<?php echo isset($form_data['email']) ? htmlspecialchars($form_data['email']) : ''; ?>"
                                required>
                            <?php if (isset($errores['email'])): ?>
                                <div class="invalid-feedback"><?php echo $errores['email']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="username" class="form-label">Nombre de usuario</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                            <input type="text" class="form-control <?php echo isset($errores['username']) ? 'is-invalid' : ''; ?>" 
                                id="username" name="username" 
                                value="<?php echo isset($form_data['username']) ? htmlspecialchars($form_data['username']) : ''; ?>"
                                required>
                            <?php if (isset($errores['username'])): ?>
                                <div class="invalid-feedback"><?php echo $errores['username']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control <?php echo isset($errores['password']) ? 'is-invalid' : ''; ?>" 
                                    id="password" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <?php if (isset($errores['password'])): ?>
                                    <div class="invalid-feedback"><?php echo $errores['password']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="confirmPassword" class="form-label">Confirmar contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control <?php echo isset($errores['confirmPassword']) ? 'is-invalid' : ''; ?>" 
                                    id="confirmPassword" name="confirmPassword" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <?php if (isset($errores['confirmPassword'])): ?>
                                    <div class="invalid-feedback"><?php echo $errores['confirmPassword']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                            <input type="tel" class="form-control <?php echo isset($errores['telefono']) ? 'is-invalid' : ''; ?>" 
                                id="telefono" name="telefono"
                                value="<?php echo isset($form_data['telefono']) ? htmlspecialchars($form_data['telefono']) : ''; ?>">
                            <?php if (isset($errores['telefono'])): ?>
                                <div class="invalid-feedback"><?php echo $errores['telefono']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="rol" class="form-label">Rol</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-gear"></i></span>
                            <select class="form-select <?php echo isset($errores['rol']) ? 'is-invalid' : ''; ?>" 
                                id="rol" name="rol" required>
                                <option value="" selected disabled>Seleccione un rol</option>
                                <option value="administrador" <?php echo (isset($form_data['rol']) && $form_data['rol'] === 'administrador') ? 'selected' : ''; ?>>Administrador</option>
                            </select>
                            <?php if (isset($errores['rol'])): ?>
                                <div class="invalid-feedback"><?php echo $errores['rol']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-4 form-check">
                        <input type="checkbox" class="form-check-input <?php echo isset($errores['aceptarTerminos']) ? 'is-invalid' : ''; ?>" 
                            id="aceptarTerminos" name="aceptarTerminos" required>
                        <label class="form-check-label" for="aceptarTerminos">Acepto los términos y condiciones</label>
                        <?php if (isset($errores['aceptarTerminos'])): ?>
                            <div class="invalid-feedback"><?php echo $errores['aceptarTerminos']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Registrarse</button>
                    </div>
                </form>

                <p class="mt-4 text-center">¿Ya tienes una cuenta? <a href="Views/Login.php" class="text-decoration-none">Iniciar sesión</a></p>
            </div>

            <div class="col-md-5 image-side d-none d-md-flex">
                <div class="text-center text-white">
                    <i class="bi bi-taxi-front fs-1 mb-3"></i>
                    <h3>Taxi Diamantes</h3>
                    <p>La mejor opción para tus viajes</p>
                    <img src="assets/img/taxi-logo.png" alt="Taxi Diamantes" class="img-fluid mt-4" onerror="this.style.display='none'">
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/register.js"></script>
</body>

</html>