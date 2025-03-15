<?php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: Login.php');
    exit;
}

// Incluir la configuración de la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Cargar datos necesarios para el dashboard
require_once '../Controllers/DashboardController.php';
$dashboardController = new DashboardController($pdo);
$stats = $dashboardController->obtenerEstadisticas();

// Datos del usuario actual
$usuario_nombre = isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : 'Usuario';
$usuario_rol = isset($_SESSION['usuario_rol']) ? $_SESSION['usuario_rol'] : 'Operador';

// Incluir el encabezado
require_once 'Layouts/header.php';

// Definir período para filtrado
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'hoy';

// Determinar el título según el período
switch ($periodo) {
    case 'semana':
        $titulo_periodo = "Esta semana";
        break;
    case 'mes':
        $titulo_periodo = "Este mes";
        break;
    default:
        $titulo_periodo = "Hoy";
        break;
}
?>

<div class="container-fluid">
    <!-- Encabezado y filtros -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
        <div>
            <form id="formPeriodo" class="d-inline-block">
                <div class="btn-group" role="group">
                    <a href="?periodo=hoy" class="btn btn-sm <?= $periodo == 'hoy' ? 'btn-primary' : 'btn-outline-primary' ?>">Hoy</a>
                    <a href="?periodo=semana" class="btn btn-sm <?= $periodo == 'semana' ? 'btn-primary' : 'btn-outline-primary' ?>">Esta semana</a>
                    <a href="?periodo=mes" class="btn btn-sm <?= $periodo == 'mes' ? 'btn-primary' : 'btn-outline-primary' ?>">Este mes</a>
                </div>
            </form>
            <button id="btnRefreshDashboard" class="btn btn-sm btn-success ms-2">
                <i class="bi bi-arrow-clockwise"></i> Actualizar
            </button>
        </div>
    </div>

    <!-- Mensaje de bienvenida -->
    <div class="card shadow mb-4">
        <div class="card-body py-3 d-flex align-items-center justify-content-between">
            <div>
                <h4 class="mb-0">Bienvenido, <?php echo htmlspecialchars($usuario_nombre); ?></h4>
                <p class="mb-0 text-gray-600">Has iniciado sesión como <span class="badge bg-primary"><?php echo htmlspecialchars($usuario_rol); ?></span></p>
            </div>
            <div class="border-start ps-4">
                <div class="small text-gray-500">Fecha actual</div>
                <div class="fw-bold" id="fecha-actual"><?= date('d/m/Y H:i') ?></div>
            </div>
        </div>
    </div>

    <!-- Primera fila de tarjetas -->
    <div class="row">
        <!-- Tarjeta de servicios -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Servicios (<?= $titulo_periodo ?>)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['servicios_total'] ?></div>
                            <div class="mt-2 small">
                                <span class="text-success me-2"><?= $stats['servicios_finalizados'] ?> finalizados</span>
                                <span class="text-danger"><?= $stats['servicios_cancelados'] ?> cancelados</span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-taxi-front fs-2 text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $stats['efectividad'] ?>%;"
                            aria-valuenow="<?= $stats['efectividad'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="small text-gray-500 text-end mt-1"><?= $stats['efectividad'] ?>% completados</div>
                </div>
            </div>
        </div>

        <!-- Tarjeta de vehículos -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Vehículos</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <span class="text-success"><?= $stats['vehiculos_disponibles'] ?></span> / <?= $stats['vehiculos_total'] ?>
                            </div>
                            <div class="mt-2 small">
                                <div><span class="badge bg-success me-1"><?= $stats['vehiculos_disponibles'] ?></span> disponibles</div>
                                <div><span class="badge bg-warning me-1"><?= $stats['vehiculos_ocupados'] ?></span> ocupados</div>
                                <div><span class="badge bg-info me-1"><?= $stats['vehiculos_mantenimiento'] ?></span> mantenimiento</div>
                                <div><span class="badge bg-danger me-1"><?= $stats['vehiculos_sancionados'] ?></span> sancionados</div>
                                <div><span class="badge bg-secondary me-1"><?= $stats['vehiculos_inactivos'] ?></span> inactivos</div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-car-front fs-2 text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white p-1">
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" role="progressbar"
                            style="width: <?= ($stats['vehiculos_disponibles'] / $stats['vehiculos_total']) * 100 ?>%;"
                            aria-valuenow="<?= ($stats['vehiculos_disponibles'] / $stats['vehiculos_total']) * 100 ?>"
                            aria-valuemin="0" aria-valuemax="100"></div>
                        <div class="progress-bar bg-warning" role="progressbar"
                            style="width: <?= ($stats['vehiculos_ocupados'] / $stats['vehiculos_total']) * 100 ?>%;"
                            aria-valuenow="<?= ($stats['vehiculos_ocupados'] / $stats['vehiculos_total']) * 100 ?>"
                            aria-valuemin="0" aria-valuemax="100"></div>
                        <div class="progress-bar bg-info" role="progressbar"
                            style="width: <?= ($stats['vehiculos_mantenimiento'] / $stats['vehiculos_total']) * 100 ?>%;"
                            aria-valuenow="<?= ($stats['vehiculos_mantenimiento'] / $stats['vehiculos_total']) * 100 ?>"
                            aria-valuemin="0" aria-valuemax="100"></div>
                        <div class="progress-bar bg-danger" role="progressbar"
                            style="width: <?= ($stats['vehiculos_sancionados'] / $stats['vehiculos_total']) * 100 ?>%;"
                            aria-valuenow="<?= ($stats['vehiculos_sancionados'] / $stats['vehiculos_total']) * 100 ?>"
                            aria-valuemin="0" aria-valuemax="100"></div>
                        <div class="progress-bar bg-secondary" role="progressbar"
                            style="width: <?= ($stats['vehiculos_inactivos'] / $stats['vehiculos_total']) * 100 ?>%;"
                            aria-valuenow="<?= ($stats['vehiculos_inactivos'] / $stats['vehiculos_total']) * 100 ?>"
                            aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-1 px-2">
                        <a href="Vehiculo.php" class="btn btn-sm btn-outline-success">Ver vehículos</a>
                        <div class="small text-gray-500"><?= $stats['disponibilidad_vehiculos'] ?>% disponibles</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjeta de tiempo promedio respuesta -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Tiempo promedio de respuesta</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['tiempo_promedio_minutos'] ?> min</div>
                            <div class="mt-2 small">
                                <span class="text-gray-600"><?= $stats['tiempo_promedio_segundos'] ?> segundos</span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-clock-history fs-2 text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="progress" style="height: 8px;">
                        <?php
                        // A menor tiempo, mayor puntaje (verde = rápido, amarillo = medio, rojo = lento)
                        $tiempo_color = "bg-success";
                        if ($stats['tiempo_promedio_minutos'] > 10) {
                            $tiempo_color = "bg-danger";
                        } else if ($stats['tiempo_promedio_minutos'] > 5) {
                            $tiempo_color = "bg-warning";
                        }

                        // Calcular porcentaje invertido (menor tiempo = mejor)
                        $tiempo_score = max(0, 100 - min(100, ($stats['tiempo_promedio_minutos'] * 10)));
                        ?>
                        <div class="progress-bar <?= $tiempo_color ?>" role="progressbar" style="width: <?= $tiempo_score ?>%;"
                            aria-valuenow="<?= $tiempo_score ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="small text-gray-500 text-end mt-1">Meta: 5 minutos</div>
                </div>
            </div>
        </div>

        <!-- Tarjeta de servicios pendientes -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Pendientes de asignación</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['servicios_pendientes'] ?></div>
                            <div class="mt-2 small">
                                <?php if ($stats['servicios_pendientes'] > 0): ?>
                                    <span class="text-danger">
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        Requieren atención
                                    </span>
                                <?php else: ?>
                                    <span class="text-success">
                                        <i class="bi bi-check-circle me-1"></i>
                                        Todo en orden
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-hourglass-split fs-2 text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white text-center">
                    <a href="Servicios.php?filtro=pendientes" class="btn btn-sm btn-danger w-100">
                        Ver servicios pendientes
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts específicos para el Dashboard -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Actualizar la hora en tiempo real
        function actualizarHora() {
            const ahora = new Date();
            const opciones = {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            document.getElementById('fecha-actual').textContent = ahora.toLocaleDateString('es-ES', opciones);
        }
        setInterval(actualizarHora, 60000);
        actualizarHora();

        // Datos para gráficos
        const datosServicios = {
            labels: <?= json_encode($stats['horas']) ?>,
            datasets: [{
                label: 'Servicios',
                data: <?= json_encode($stats['servicios_por_hora']) ?>,
                backgroundColor: 'rgba(78, 115, 223, 0.2)',
                borderColor: 'rgba(78, 115, 223, 1)',
                borderWidth: 2,
                pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                tension: 0.3
            }]
        };

        const datosVehiculos = {
            labels: ['Disponibles', 'En servicio', 'Mantenimiento', 'Inactivos'],
            datasets: [{
                data: [
                    <?= $stats['vehiculos_disponibles'] ?>,
                    <?= $stats['vehiculos_ocupados'] ?>,
                    <?= $stats['vehiculos_mantenimiento'] ?>,
                    <?= $stats['vehiculos_inactivos'] ?>
                ],
                backgroundColor: [
                    'rgba(40, 167, 69, 0.8)',
                    'rgba(255, 193, 7, 0.8)',
                    'rgba(220, 53, 69, 0.8)',
                    'rgba(108, 117, 125, 0.8)'
                ],
                borderColor: [
                    'rgba(40, 167, 69, 1)',
                    'rgba(255, 193, 7, 1)',
                    'rgba(220, 53, 69, 1)',
                    'rgba(108, 117, 125, 1)'
                ],
                borderWidth: 1
            }]
        };

        // Configuración de gráficos
        const configServicios = {
            type: 'line',
            data: datosServicios,
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        };

        const configVehiculos = {
            type: 'doughnut',
            data: datosVehiculos,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                cutout: '65%'
            }
        };

        // Inicializar gráficos
        const ctxServicios = document.getElementById('serviciosPorHoraChart').getContext('2d');
        window.serviciosChart = new Chart(ctxServicios, configServicios);

        const ctxVehiculos = document.getElementById('vehiculosChart').getContext('2d');
        window.vehiculosChart = new Chart(ctxVehiculos, configVehiculos);

        // Función para cambiar tipo de gráfico
        document.querySelectorAll('[data-chart-type]').forEach(item => {
            item.addEventListener('click', event => {
                event.preventDefault();
                const chartType = event.target.getAttribute('data-chart-type');

                if (window.serviciosChart) {
                    window.serviciosChart.destroy();
                }

                configServicios.type = chartType;
                window.serviciosChart = new Chart(ctxServicios, configServicios);
            });
        });

        // Función para actualizar el dashboard
        document.getElementById('btnRefreshDashboard').addEventListener('click', function(e) {
            e.preventDefault();
            location.reload();
        });

        // Función para actualizar sección de actividad reciente
        document.getElementById('btnActualizarActividad').addEventListener('click', function(e) {
            e.preventDefault();

            // Aquí se implementaría una llamada AJAX para actualizar solo la sección de actividad
            const actividadContainer = document.querySelector('.timeline-activity');
            actividadContainer.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';

            // Simulación de carga (reemplazar por AJAX real)
            setTimeout(() => {
                fetch('Processings/obtener_actividad_reciente.php')
                    .then(response => response.json())
                    .then(data => {
                        // Actualizar contenido
                        if (data.error) {
                            actividadContainer.innerHTML = '<div class="alert alert-danger">Error al cargar la actividad reciente</div>';
                        } else {
                            // Aquí se actualizaría con los datos reales
                            actividadContainer.innerHTML = '<div class="text-center text-success py-5"><i class="bi bi-check-circle fs-1"></i><p class="mt-2">Actividad actualizada</p></div>';
                        }
                    })
                    .catch(error => {
                        actividadContainer.innerHTML = '<div class="alert alert-danger">Error de conexión</div>';
                    });
            }, 1000);
        });
    });
</script>

<?php
// Incluir el pie de página
require_once 'Layouts/footer.php';
?>