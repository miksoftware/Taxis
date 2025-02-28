<?php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si el usuario ya está autenticado, redirigir al dashboard
if(isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true) {
    header('Location: dashboard.php');
    exit;
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
    <title>Iniciar Sesión - Taxi Diamantes</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/login.css">    
</head>

<body>
    <div class="container">
        <?php
        // Mostrar mensaje de error si existe
        if (isset($_SESSION['error_mensaje'])) {
            echo '<div class="alert alert-danger">' . $_SESSION['error_mensaje'] . '</div>';
            unset($_SESSION['error_mensaje']);
        }
        
        // Mostrar mensaje de éxito si existe
        if (isset($_SESSION['success_mensaje'])) {
            echo '<div class="alert alert-success">' . $_SESSION['success_mensaje'] . '</div>';
            unset($_SESSION['success_mensaje']);
        }
        ?>
        
        <div class="row login-container">
            <div class="col-md-6 form-side">
                <h2 class="mb-4 text-center">Iniciar Sesión</h2>
                <p class="text-muted text-center mb-4">Ingrese sus credenciales para acceder al sistema</p>

                <form id="loginForm" action="../Processings/procesar_login.php" method="post">
                    <div class="mb-3">
                        <label for="username" class="form-label">Usuario o Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control <?php echo isset($errores['username']) ? 'is-invalid' : ''; ?>" 
                                id="username" name="username" 
                                value="<?php echo isset($form_data['username']) ? htmlspecialchars($form_data['username']) : ''; ?>"
                                required>
                            <?php if (isset($errores['username'])): ?>
                                <div class="invalid-feedback"><?php echo $errores['username']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-4">
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

                    <div class="d-flex justify-content-between mb-4">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="recordar" name="recordar">
                            <label class="form-check-label" for="recordar">Recordarme</label>
                        </div>
                        <a href="recuperar_password.php" class="text-decoration-none">¿Olvidó su contraseña?</a>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Iniciar Sesión</button>
                    </div>
                </form>

                <p class="mt-4 text-center">¿No tiene cuenta? <a href="../Index.php" class="text-decoration-none">Registrarse</a></p>
            </div>

            <div class="col-md-6 image-side d-none d-md-flex">
                <div class="text-center text-white">
                    <i class="bi bi-taxi-front display-1 mb-3 taxi-logo"></i>
                    <h2 class="fw-bold">Taxi Diamantes</h2>
                    <p class="lead">La mejor opción para tus viajes</p>
                    <img src="../assets/img/taxi-logo.png" alt="Taxi Diamantes" class="img-fluid mt-4 taxi-logo" onerror="this.style.display='none'">
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/login.js"></script>
</body>

</html>