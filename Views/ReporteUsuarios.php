<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Views\ReporteUsuarios.php


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

require_once '../Controllers/ReporteController.php';

$reporteController = new ReporteController($pdo);

// Obtener lista de usuarios para el filtro
$usuarios = $reporteController->obtenerTodosUsuarios();

// Incluir header
include 'Layouts/header.php';

// Obtener parámetros
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('-30 days'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$usuario_id = isset($_GET['usuario_id']) ? $_GET['usuario_id'] : '';

// Si ya hay parámetros de filtro, cargar el reporte inicial
$hayFiltros = isset($_GET['fecha_inicio']) || isset($_GET['usuario_id']) || isset($_GET['rol']);
if ($hayFiltros) {
    // Primero crear los filtros
    $filtros = [
        'fecha_inicio' => $fecha_inicio,
        'fecha_fin' => $fecha_fin,
        'usuario_id' => $usuario_id
    ];

    // Luego generar el reporte
    $reporte = $reporteController->generarReporteUsuarios($filtros);

    // Y finalmente imprimir para debug
    echo "<!-- Debug: ";
    echo "Filtros: ";
    print_r($filtros);
    echo "Reporte: ";
    print_r($reporte);
    echo " -->";
} else {
    $reporte = null;
}
?>

<div class="container-fluid">
    <!-- Encabezado de página -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Reporte de Rendimiento de Usuarios</h1>
        <div>
            <a href="Reportes.php" class="btn btn-sm btn-secondary shadow-sm">
                <i class="bi bi-arrow-left"></i> Volver a Reportes
            </a>
            <button id="btnExportarExcel" class="btn btn-sm btn-success shadow-sm">
                <i class="bi bi-file-earmark-excel"></i> Exportar a Excel
            </button>
            <button id="btnExportarPDF" class="btn btn-sm btn-danger shadow-sm">
                <i class="bi bi-file-earmark-pdf"></i> Exportar a PDF
            </button>
        </div>
    </div>

    <!-- Filtros de Reporte -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filtros</h6>
        </div>
        <div class="card-body">
            <form id="formFiltros" method="get" class="row g-3">
                <!-- Período de tiempo -->
                <div class="col-md-3">
                    <label for="fecha_inicio" class="form-label">Desde</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>">
                </div>
                <div class="col-md-3">
                    <label for="fecha_fin" class="form-label">Hasta</label>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?php echo $fecha_fin; ?>">
                </div>

                <!-- Filtros específicos -->
                <div class="col-md-3">
                    <label for="usuario_id" class="form-label">Usuario</label>
                    <select class="form-select" id="usuario_id" name="usuario_id">
                        <option value="">Todos los usuarios</option>
                        <?php foreach ($usuarios as $usuario): ?>
                            <option value="<?= $usuario['id'] ?>" <?= $usuario_id == $usuario['id'] ? 'selected' : '' ?>><?= htmlspecialchars($usuario['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>


                <!-- Botón de filtrado -->
                <div class="col-12 mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-filter"></i> Aplicar Filtros
                    </button>
                    <button type="button" id="btnLimpiarFiltros" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Limpiar Filtros
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Resumen Visual -->
    <div class="row mb-4" id="resumenVisual">
        <!-- Contenido dinámico: tarjetas de resumen -->
        <?php
        $servicios = ['total_servicios' => 'Total Servicios', 'finalizados' => 'Servicios Finalizados', 'cancelados' => 'Servicios Cancelados'];
        $colors = ['primary', 'success', 'danger'];
        $icons = ['chat-square-dots', 'check-circle', 'x-circle'];
        $i = 0;

        foreach ($servicios as $key => $label): ?>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-<?= $colors[$i] ?> shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-<?= $colors[$i] ?> text-uppercase mb-1"><?= $label ?></div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800" id="<?= $key ?>">
                                    <?= $hayFiltros && isset($reporte['totales'][$key]) ? $reporte['totales'][$key] : '-' ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-<?= $icons[$i] ?> fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php
            $i++;
        endforeach; ?>
        <!-- Efectividad Promedio -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Efectividad Promedio</div>
                            <div class="row no-gutters align-items-center">
                                <div class="col-auto">
                                    <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800" id="efectividadPromedio">
                                        <?= $hayFiltros && isset($reporte['totales']['efectividad']) ? $reporte['totales']['efectividad'] . '%' : '-' ?>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="progress progress-sm mr-2">
                                        <div class="progress-bar bg-info" role="progressbar"
                                            style="width: <?= $hayFiltros && isset($reporte['totales']['efectividad']) ? $reporte['totales']['efectividad'] . '%' : '0%' ?>"
                                            id="barraEfectividad"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-percent fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico -->
    <?php if ($hayFiltros && !empty($reporte['usuarios'])): ?>
        <div class="row mb-4">
            <div class="col-xl-12">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Rendimiento de Usuarios</h6>
                        <div class="dropdown no-arrow">
                            <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="bi bi-three-dots-vertical text-gray-400"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                                <div class="dropdown-header">Opciones de Gráfico:</div>
                                <a class="dropdown-item" href="#" id="verGraficoBarras">Ver como barras</a>
                                <a class="dropdown-item" href="#" id="verGraficoLineas">Ver como líneas</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-area">
                            <canvas id="graficoUsuarios"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tabla de Resultados -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Detalle por Usuario</h6>
            <div>
                <span id="totalRegistros">
                    <?= $hayFiltros && isset($reporte['usuarios']) ? count($reporte['usuarios']) : '0' ?> registros encontrados
                </span>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="tablaUsuarios" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Nombre</th>
                            <th>Total Servicios</th>
                            <th>Finalizados</th>
                            <th>Cancelados</th>
                            <th>Efectividad</th>
                            <th>Tiempo Promedio</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($hayFiltros && !empty($reporte['usuarios'])): ?>
                            <?php foreach ($reporte['usuarios'] as $usuario): ?>
                                <tr>
                                    <td><?= htmlspecialchars($usuario['username']) ?></td>
                                    <td><?= htmlspecialchars($usuario['nombre']) ?></td>
                                    <td class="text-center"><?= $usuario['total_servicios'] ?></td>
                                    <td class="text-center"><?= $usuario['finalizados'] ?></td>
                                    <td class="text-center"><?= $usuario['cancelados'] ?></td>
                                    <td class="text-center"><?= $usuario['efectividad'] ?>%</td>
                                    <td class="text-center"><?= formatearTiempo($usuario['tiempo_promedio_asignacion']) ?></td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-info verDetalle" data-id="<?= $usuario['id'] ?>">
                                            <i class="bi bi-eye"></i> Ver detalle
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No se encontraron datos para el período seleccionado</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Detalles -->
<div class="modal fade" id="modalDetalleUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detalleUsuarioContenido">
                <!-- Contenido dinámico -->
            </div>
            <!-- Añadir el canvas para el gráfico -->
            <div class="chart-container" style="height: 300px;">
                <canvas id="graficoDetalleUsuario"></canvas>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts necesarios -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Referencias a elementos importantes
        const formFiltros = document.getElementById('formFiltros');
        const btnLimpiarFiltros = document.getElementById('btnLimpiarFiltros');
        const btnExportarExcel = document.getElementById('btnExportarExcel');
        const btnExportarPDF = document.getElementById('btnExportarPDF');

        // Variables para gráficos
        let graficoUsuarios;

        // Inicializar gráficos si hay datos
        <?php if ($hayFiltros && !empty($reporte['usuarios'])): ?>
            inicializarGrafico();
        <?php endif; ?>

        // Event Listeners
        formFiltros.addEventListener('submit', function(e) {
            e.preventDefault();
            aplicarFiltros();
        });

        btnLimpiarFiltros.addEventListener('click', function() {
            formFiltros.reset();
            document.getElementById('fecha_inicio').value = '<?= date('Y-m-d', strtotime('-30 days')); ?>';
            document.getElementById('fecha_fin').value = '<?= date('Y-m-d'); ?>';
            aplicarFiltros();
        });

        if (document.getElementById('verGraficoBarras')) {
            document.getElementById('verGraficoBarras').addEventListener('click', function(e) {
                e.preventDefault();
                if (graficoUsuarios) {
                    graficoUsuarios.config.type = 'bar';
                    graficoUsuarios.update();
                }
            });
        }

        if (document.getElementById('verGraficoLineas')) {
            document.getElementById('verGraficoLineas').addEventListener('click', function(e) {
                e.preventDefault();
                if (graficoUsuarios) {
                    graficoUsuarios.config.type = 'line';
                    graficoUsuarios.update();
                }
            });
        }

        btnExportarExcel.addEventListener('click', exportarExcel);
        btnExportarPDF.addEventListener('click', exportarPDF);

        // Agregar eventos a los botones de detalle
        document.querySelectorAll('.verDetalle').forEach(btn => {
            btn.addEventListener('click', function() {
                const usuarioId = this.getAttribute('data-id');
                verDetalleUsuario(usuarioId);
            });
        });

        // Función para aplicar filtros
        function aplicarFiltros() {
            const formData = new FormData(formFiltros);
            const queryParams = new URLSearchParams(formData).toString();
            window.location.href = 'ReporteUsuarios.php?' + queryParams;
        }

        // Función para inicializar el gráfico principal
        function inicializarGrafico() {
            const ctx = document.getElementById('graficoUsuarios').getContext('2d');
            const labels = <?= json_encode(array_map(fn($u) => $u['nombre'], $reporte['usuarios'] ?? [])) ?>;
            const finalizados = <?= json_encode(array_map(fn($u) => $u['finalizados'], $reporte['usuarios'] ?? [])) ?>;
            const cancelados = <?= json_encode(array_map(fn($u) => $u['cancelados'], $reporte['usuarios'] ?? [])) ?>;

            graficoUsuarios = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                            label: 'Servicios Finalizados',
                            backgroundColor: 'rgba(40, 167, 69, 0.7)',
                            borderColor: 'rgba(40, 167, 69, 1)',
                            borderWidth: 1,
                            data: finalizados
                        },
                        {
                            label: 'Servicios Cancelados',
                            backgroundColor: 'rgba(220, 53, 69, 0.7)',
                            borderColor: 'rgba(220, 53, 69, 1)',
                            borderWidth: 1,
                            data: cancelados
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    responsive: true,
                    scales: {
                        x: {
                            stacked: false
                        },
                        y: {
                            stacked: false,
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        },
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    }
                }
            });
        }

        // Función para ver detalle de usuario (reemplazar la existente)
        function verDetalleUsuario(usuarioId) {
            const modal = document.getElementById('modalDetalleUsuario');
            const contenido = document.getElementById('detalleUsuarioContenido');
            const graficoContainer = document.querySelector('#modalDetalleUsuario .chart-container');
            const canvas = document.getElementById('graficoDetalleUsuario');

            // Mostrar carga en el modal
            contenido.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
            graficoContainer.style.display = 'none';

            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();

            const formData = new FormData(formFiltros);
            formData.append('usuario_id', usuarioId);
            formData.append('detalle', '1');
            const params = new URLSearchParams(formData).toString();

            fetch('../Processings/generar_reporte_usuarios.php?' + params)
                .then(response => response.json())
                .then(data => {
                    console.log('Datos recibidos:', data);
                    if (data.error) {
                        contenido.innerHTML = `<div class="alert alert-danger">${data.mensaje}</div>`;
                        return;
                    }

                    // Datos del usuario
                    const usuario = data.usuario || {};
                    const estadisticas = data.estadisticas || {};

                    let html = `
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="m-0">${usuario.nombre || 'Usuario'}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Usuario:</strong> ${usuario.usuario || usuario.username || 'N/A'}</p>
                                <p><strong>Rol:</strong> ${usuario.rol || 'N/A'}</p>
                                <p><strong>Estado:</strong> ${usuario.estado || 'N/A'}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Fecha registro:</strong> ${usuario.fecha_registro ? formatearFecha(usuario.fecha_registro) : 'N/A'}</p>
                                <p><strong>Último acceso:</strong> ${usuario.ultimo_acceso ? formatearFechaHora(usuario.ultimo_acceso) : 'N/A'}</p>
                            </div>
                        </div>
                    </div>
                </div>`;

                    // Agregar estadísticas si existen
                    if (estadisticas) {
                        html += `
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="m-0">Estadísticas en el período</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <tbody>
                                    <tr>
                                        <th>Total Servicios</th>
                                        <td class="text-center">${estadisticas.total_servicios || 0}</td>
                                        <th>Finalizados</th>
                                        <td class="text-center">${estadisticas.finalizados || 0}</td>
                                    </tr>
                                    <tr>
                                        <th>Cancelados</th>
                                        <td class="text-center">${estadisticas.cancelados || 0}</td>
                                        <th>Efectividad</th>
                                        <td class="text-center">${estadisticas.efectividad || 0}%</td>
                                    </tr>
                                    <tr>
                                        <th>Tiempo Promedio de Asignación</th>
                                        <td colspan="3" class="text-center">${formatearTiempo(estadisticas.tiempo_promedio_asignacion || 0)}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>`;

                        // Mostrar servicios recientes si existen
                        if (estadisticas.servicios_recientes && estadisticas.servicios_recientes.length > 0) {
                            html += `
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="m-0">Servicios Recientes</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Cliente</th>
                                            <th>Fecha</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;

                            estadisticas.servicios_recientes.forEach(servicio => {
                                html += `
                        <tr>
                            <td>${servicio.cliente_nombre || servicio.cliente_telefono || 'N/A'}</td>
                            <td>${formatearFechaHora(servicio.fecha_solicitud)}</td>
                            <td>${formatearEstado(servicio.estado)}</td>
                        </tr>`;
                            });

                            html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>`;
                        }

                        // Mostrar el gráfico
                        graficoContainer.style.display = 'block';
                        const ctxPie = canvas.getContext('2d');

                        // Destruir gráfico anterior si existe
                        if (window.graficoDetalle) {
                            window.graficoDetalle.destroy();
                        }

                        window.graficoDetalle = new Chart(ctxPie, {
                            type: 'pie',
                            data: {
                                labels: ['Finalizados', 'Cancelados'],
                                datasets: [{
                                    data: [
                                        estadisticas.finalizados || 0,
                                        estadisticas.cancelados || 0
                                    ],
                                    backgroundColor: ['rgba(40, 167, 69, 0.7)', 'rgba(220, 53, 69, 0.7)'],
                                    borderColor: ['rgba(40, 167, 69, 1)', 'rgba(220, 53, 69, 1)'],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'bottom'
                                    },
                                    title: {
                                        display: true,
                                        text: 'Distribución de Servicios'
                                    }
                                }
                            }
                        });
                    }

                    contenido.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    contenido.innerHTML = '<div class="alert alert-danger">Error al cargar los detalles del usuario</div>';
                    graficoContainer.style.display = 'none';
                });
        }

        function exportarExcel() {
            const formData = new FormData(formFiltros);
            formData.append('formato', 'excel');
            const params = new URLSearchParams(formData).toString();
            window.open('../Processings/exportar_reporte_usuarios.php?' + params, '_blank');
        }

        function exportarPDF() {
            const formData = new FormData(formFiltros);
            formData.append('formato', 'pdf');
            const params = new URLSearchParams(formData).toString();
            window.open('../Processings/exportar_reporte_usuarios.php?' + params, '_blank');
        }

        function formatearEstado(estado) {
            if (!estado) return 'N/A';
            const estados = {
                'pendiente': '<span class="badge bg-warning">Pendiente</span>',
                'asignado': '<span class="badge bg-primary">Asignado</span>',
                'en_curso': '<span class="badge bg-info">En curso</span>',
                'finalizado': '<span class="badge bg-success">Finalizado</span>',
                'cancelado': '<span class="badge bg-danger">Cancelado</span>'
            };
            return estados[estado] || estado;
        }
    });

    function formatearTiempo(minutos) {
        if (!minutos) return '0 min';
        minutos = parseInt(minutos);
        if (minutos < 60) return minutos + ' min';
        const horas = Math.floor(minutos / 60);
        const min = minutos % 60;
        return horas + 'h ' + (min > 0 ? min + 'min' : '');
    }

    function formatearFecha(fecha) {
        if (!fecha) return 'N/A';
        const f = new Date(fecha);
        return f.toLocaleDateString('es-ES');
    }

    function formatearFechaHora(fechaHora) {
        if (!fechaHora) return 'N/A';
        const f = new Date(fechaHora);
        return f.toLocaleDateString('es-ES') + ' ' + f.toLocaleTimeString('es-ES', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }
</script>

<?php
// Función de ayuda para formatear tiempo en PHP (para la tabla inicial)
function formatearTiempo($minutos)
{
    if ($minutos === null || $minutos == 0) return '0 min';

    $minutos = intval($minutos);
    if ($minutos < 60) return $minutos . ' min';

    $horas = floor($minutos / 60);
    $min = $minutos % 60;

    return $horas . 'h ' . ($min > 0 ? $min . 'min' : '');
}
?>

<?php include 'Layouts/footer.php'; ?>