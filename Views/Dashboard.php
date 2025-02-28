<?php
// Iniciar sesión
session_start();

// Verificar si el usuario está autenticado
if(!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    // Si no está autenticado, redirigir al login
    $_SESSION['error_mensaje'] = "Debe iniciar sesión para acceder al panel de control";
    header('Location: Login.php');
    exit;
}

// Recuperar información del usuario
$usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$usuario_rol = $_SESSION['usuario_rol'] ?? 'desconocido';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - Taxi Diamantes</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #ffc107;
            --dark-color: #212529;
        }
        .sidebar {
            background-color: var(--dark-color);
            height: 100vh;
            position: fixed;
            padding-top: 1rem;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,.75);
            padding: .75rem 1rem;
        }
        .sidebar .nav-link:hover {
            color: rgba(255,255,255,1);
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: var(--primary-color);
            border-radius: .25rem;
            font-weight: 500;
        }
        .sidebar .nav-link i {
            margin-right: .5rem;
        }
        .navbar {
            background-color: var(--primary-color);
        }
        .content {
            margin-left: 240px;
            padding: 2rem;
        }
        .welcome-message {
            background: linear-gradient(135deg, #ffcc33, #ff9900);
            color: var(--dark-color);
            padding: 1.5rem;
            border-radius: .5rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,.1);
            margin-bottom: 2rem;
        }
        @media (max-width: 991.98px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .content {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="#">
                <i class="bi bi-taxi-front me-2"></i>
                Taxi Diamantes
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($usuario_nombre); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="perfil.php"><i class="bi bi-person me-2"></i> Perfil</a></li>
                            <li><a class="dropdown-item" href="cambiar_password.php"><i class="bi bi-key me-2"></i> Cambiar contraseña</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../Processings/procesar_logout.php"><i class="bi bi-box-arrow-right me-2"></i> Cerrar sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="d-flex flex-column">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <?php if ($usuario_rol === 'administrador'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="usuarios.php">
                                <i class="bi bi-people"></i> Usuarios
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="servicios.php">
                                <i class="bi bi-journal-text"></i> Servicios
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reportes.php">
                                <i class="bi bi-bar-chart"></i> Reportes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="configuracion.php">
                                <i class="bi bi-gear"></i> Configuración
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <main class="col-md-9 ms-sm-auto col-lg-10 content">
                <div class="welcome-message">
                    <h2>Bienvenido, <?php echo htmlspecialchars($usuario_nombre); ?></h2>
                    <p>Has iniciado sesión como <?php echo htmlspecialchars($usuario_rol); ?>.</p>
                    <p>Desde este panel podrás gestionar todos los aspectos del sistema Taxi Diamantes.</p>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="bi bi-journal-check me-2"></i> Servicios hoy</h5>
                                <h2 class="card-text display-5">0</h2>
                                <p class="text-muted">Servicios realizados</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="bi bi-graph-up me-2"></i> Ingresos</h5>
                                <h2 class="card-text display-5">$0</h2>
                                <p class="text-muted">Ingresos del día</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="bi bi-people me-2"></i> Usuarios</h5>
                                <h2 class="card-text display-5">1</h2>
                                <p class="text-muted">Usuarios activos</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">Actividad reciente</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted text-center">No hay actividad reciente para mostrar.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>