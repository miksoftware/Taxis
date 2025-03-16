<?php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Incluir la configuración de la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

require_once '../Controllers/ReporteController.php';
require_once '../Controllers/ServicioController.php';
require_once '../Controllers/VehiculoController.php';
require_once '../Controllers/UsuarioController.php';

$reporteController = new ReporteController($pdo);
$servicioController = new ServicioController($pdo);
$vehiculoController = new VehiculoController($pdo);
$usuarioController = new UsuarioController($pdo);

// Obtener listas para filtros
$vehiculos = $vehiculoController->listar();
// Incluir header
include 'Layouts/header.php';

// Obtener parámetros
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('-30 days'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$vehiculo_id = isset($_GET['vehiculo_id']) ? $_GET['vehiculo_id'] : '';
$operador_id = isset($_GET['operador_id']) ? $_GET['operador_id'] : '';
$estado = isset($_GET['estado']) ? $_GET['estado'] : '';

// Si ya hay parámetros de filtro, cargar el reporte
$hayFiltros = isset($_GET['fecha_inicio']) || isset($_GET['fecha_fin']) || isset($_GET['vehiculo_id']) || isset($_GET['operador_id']) || isset($_GET['estado']);

// Obtener estadísticas
$filtros = [
    'fecha_inicio' => $fecha_inicio,
    'fecha_fin' => $fecha_fin,
    'vehiculo_id' => $vehiculo_id,
    'operador_id' => $operador_id,
    'estado' => $estado
];

// Generar reporte
$reporte = $reporteController->generarReporteServicios($filtros);
$estadisticas = $reporte['estadisticas'] ?? [];
$detalle_servicios = $reporte['servicios'] ?? [];
$top_vehiculos = $reporte['top_vehiculos'] ?? [];
$top_operadores = $reporte['top_operadores'] ?? [];

// Función para formatear tiempo
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

<div class="container-fluid">
    <!-- Encabezado de página -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Reporte de Servicios</h1>
        <a href="Reportes.php" class="btn btn-sm btn-secondary shadow-sm">
            <i class="bi bi-arrow-left"></i> Volver a Reportes
        </a>
    </div>

    <!-- Tarjeta de Filtros -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filtros</h6>
        </div>
        <div class="card-body">
            <form id="formFiltros" method="get">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="fecha_inicio">Fecha Inicio</label>
                        <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?= $fecha_inicio ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="fecha_fin">Fecha Fin</label>
                        <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?= $fecha_fin ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="vehiculo_id">Vehículo</label>
                        <select class="form-control" id="vehiculo_id" name="vehiculo_id">
                            <option value="">Todos</option>
                            <?php foreach ($vehiculos as $v): 
                                if (isset($v['error'])) continue; ?>
                                <option value="<?= $v['id'] ?>" <?= $vehiculo_id == $v['id'] ? 'selected' : '' ?>>
                                    <?= $v['numero_movil'] ?> - <?= $v['placa'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="operador_id">Operador</label>
                        <select class="form-control" id="operador_id" name="operador_id">
                            <option value="">Todos</option>
                            <?php foreach ($operadores as $op): 
                                if (isset($op['error'])) continue; ?>
                                <option value="<?= $op['id'] ?>" <?= $operador_id == $op['id'] ? 'selected' : '' ?>>
                                    <?= $op['nombre'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="estado">Estado</label>
                        <select class="form-control" id="estado" name="estado">
                            <option value="">Todos</option>
                            <option value="finalizado" <?= $estado == 'finalizado' ? 'selected' : '' ?>>Finalizados</option>
                            <option value="cancelado" <?= $estado == 'cancelado' ? 'selected' : '' ?>>Cancelados</option>
                            <option value="pendiente" <?= $estado == 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                            <option value="asignado" <?= $estado == 'asignado' ? 'selected' : '' ?>>Asignados</option>
                            <option value="en_camino" <?= $estado == 'en_camino' ? 'selected' : '' ?>>En camino</option>
                        </select>
                    </div>
                    <div class="col-md-9 mb-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary mr-2">
                            <i class="bi bi-filter"></i> Aplicar Filtros
                        </button>
                        <button type="button" id="btnLimpiarFiltros" class="btn btn-outline-secondary">
                            <i class="bi bi-eraser"></i> Limpiar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($hayFiltros): ?>
        <!-- Tarjetas de Resumen -->
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Servicios Totales
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $estadisticas['total'] ?? 0 ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-card-list fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Finalizados
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $estadisticas['finalizados'] ?? 0 ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-check-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                    Cancelados
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $estadisticas['cancelados'] ?? 0 ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-x-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Efectividad
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= isset($estadisticas['total']) && $estadisticas['total'] > 0 
                                        ? round(($estadisticas['finalizados'] * 100) / $estadisticas['total'], 1) . '%'
                                        : '0%' ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-bar-chart fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="row">
            <!-- Gráfico de Estados -->
            <div class="col-xl-6 col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Distribución por Estado</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height:260px;">
                            <canvas id="graficoEstadoServicios"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfico de Tendencia -->
            <div class="col-xl-6 col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Tendencia de Servicios</h6>
                        <div class="dropdown no-arrow">
                            <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink"
                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="bi bi-three-dots-vertical text-gray-400"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in"
                                aria-labelledby="dropdownMenuLink">
                                <a class="dropdown-item" href="#" id="verGraficoBarras">Ver Gráfico de Barras</a>
                                <a class="dropdown-item" href="#" id="verGraficoLineas">Ver Gráfico de Líneas</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height:260px;">
                            <canvas id="graficoTendenciaServicios"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Top Vehículos -->
            <div class="col-xl-6 col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Top Vehículos</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($top_vehiculos)): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Vehículo</th>
                                            <th>Total</th>
                                            <th>Finalizados</th>
                                            <th>Cancelados</th>
                                            <th>Efectividad</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_vehiculos as $vehiculo): ?>
                                            <tr>
                                                <td><?= $vehiculo['placa'] ?> (<?= $vehiculo['numero_movil'] ?>)</td>
                                                <td><?= $vehiculo['total_servicios'] ?></td>
                                                <td><?= $vehiculo['finalizados'] ?></td>
                                                <td><?= $vehiculo['cancelados'] ?></td>
                                                <td>
                                                    <?= $vehiculo['efectividad'] ?>%
                                                    <div class="progress progress-sm">
                                                        <div class="progress-bar bg-success" role="progressbar" 
                                                            style="width: <?= $vehiculo['efectividad'] ?>%" 
                                                            aria-valuenow="<?= $vehiculo['efectividad'] ?>" 
                                                            aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <p class="text-muted">No hay datos disponibles para el período seleccionado.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Top Operadores -->
            <div class="col-xl-6 col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Top Operadores</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($top_operadores)): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Operador</th>
                                            <th>Total</th>
                                            <th>Finalizados</th>
                                            <th>Cancelados</th>
                                            <th>Efectividad</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_operadores as $operador): ?>
                                            <tr>
                                                <td><?= $operador['nombre'] ?></td>
                                                <td><?= $operador['total_servicios'] ?></td>
                                                <td><?= $operador['finalizados'] ?></td>
                                                <td><?= $operador['cancelados'] ?></td>
                                                <td>
                                                    <?= $operador['efectividad'] ?>%
                                                    <div class="progress progress-sm">
                                                        <div class="progress-bar bg-success" role="progressbar" 
                                                            style="width: <?= $operador['efectividad'] ?>%" 
                                                            aria-valuenow="<?= $operador['efectividad'] ?>" 
                                                            aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <p class="text-muted">No hay datos disponibles para el período seleccionado.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Detalle de Servicios -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Detalle de Servicios</h6>
                <div>
                    <span id="totalRegistros">
                        <?= count($detalle_servicios) ?> registros encontrados
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="tablaServicios" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>#ID</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Dirección</th>
                                <th>Vehículo</th>
                                <th>Operador</th>
                                <th>Estado</th>
                                <th>Tiempo Total</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($detalle_servicios)): ?>
                                <?php foreach ($detalle_servicios as $servicio): ?>
                                    <tr>
                                        <td><?= $servicio['id'] ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($servicio['fecha_solicitud'])) ?></td>
                                        <td>
                                            <?= !empty($servicio['cliente_nombre']) ? $servicio['cliente_nombre'] : $servicio['cliente_telefono'] ?>
                                        </td>
                                        <td><?= $servicio['direccion'] ?? 'N/A' ?></td>
                                        <td>
                                            <?= !empty($servicio['placa']) ? $servicio['numero_movil'] . ' - ' . $servicio['placa'] : 'N/A' ?>
                                        </td>
                                        <td><?= $servicio['operador_nombre'] ?? 'N/A' ?></td>
                                        <td>
                                            <?php
                                            $badge_class = 'bg-secondary';
                                            switch ($servicio['estado']) {
                                                case 'pendiente': $badge_class = 'bg-warning'; break;
                                                case 'asignado': $badge_class = 'bg-primary'; break;
                                                case 'en_camino': $badge_class = 'bg-info'; break;
                                                case 'finalizado': $badge_class = 'bg-success'; break;
                                                case 'cancelado': $badge_class = 'bg-danger'; break;
                                            }
                                            ?>
                                            <span class="badge <?= $badge_class ?>"><?= ucfirst($servicio['estado']) ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            if (!empty($servicio['fecha_fin']) && !empty($servicio['fecha_solicitud'])) {
                                                $inicio = new DateTime($servicio['fecha_solicitud']);
                                                $fin = new DateTime($servicio['fecha_fin']);
                                                $intervalo = $inicio->diff($fin);
                                                $minutos = $intervalo->days * 24 * 60 + $intervalo->h * 60 + $intervalo->i;
                                                echo formatearTiempo($minutos);
                                            } else {
                                                echo 'En curso';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info verDetalle" data-id="<?= $servicio['id'] ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center">No se encontraron servicios para los criterios seleccionados</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <p><i class="bi bi-info-circle"></i> Utilice los filtros para generar el reporte de servicios.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de Detalle de Servicio -->
<div class="modal fade" id="modalDetalleServicio" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle del Servicio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detalleServicioContenido">
                <!-- Contenido dinámico -->
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

        console.log("DOM cargado - Configurando eventos");

        // Variables para gráficos
        let graficoEstadoServicios;
        let graficoTendenciaServicios;
        let graficoTopVehiculos;

        // Inicializar gráficos si hay datos
        <?php if ($hayFiltros && isset($estadisticas['total']) && $estadisticas['total'] > 0): ?>
            inicializarGraficos();
        <?php endif; ?>

        // Event Listeners
        if (formFiltros) {
            formFiltros.addEventListener('submit', function(e) {
                e.preventDefault();
                aplicarFiltros();
            });
        }

        if (btnLimpiarFiltros) {
            btnLimpiarFiltros.addEventListener('click', function() {
                formFiltros.reset();
                document.getElementById('fecha_inicio').value = '<?= date('Y-m-d', strtotime('-30 days')); ?>';
                document.getElementById('fecha_fin').value = '<?= date('Y-m-d'); ?>';
                aplicarFiltros();
            });
        }

        if (document.getElementById('verGraficoBarras')) {
            document.getElementById('verGraficoBarras').addEventListener('click', function(e) {
                e.preventDefault();
                if (graficoTendenciaServicios) {
                    graficoTendenciaServicios.config.type = 'bar';
                    graficoTendenciaServicios.update();
                }
            });
        }

        if (document.getElementById('verGraficoLineas')) {
            document.getElementById('verGraficoLineas').addEventListener('click', function(e) {
                e.preventDefault();
                if (graficoTendenciaServicios) {
                    graficoTendenciaServicios.config.type = 'line';
                    graficoTendenciaServicios.update();
                }
            });
        }

        // Agregar eventos a los botones de detalle
        document.querySelectorAll('.verDetalle').forEach(btn => {
            console.log("Configurando botón verDetalle:", btn);
            btn.addEventListener('click', function() {
                const servicioId = this.getAttribute('data-id');
                console.log("Click en verDetalle para servicio ID:", servicioId);
                verDetalleServicio(servicioId);
            });
        });

        // Función para aplicar filtros
        function aplicarFiltros() {
            const formData = new FormData(formFiltros);
            const queryParams = new URLSearchParams(formData).toString();
            window.location.href = 'ReporteVehiculos.php?' + queryParams;
        }

        // Función para inicializar los gráficos
        function inicializarGraficos() {
            // Gráfico de estado de servicios (pastel)
            const ctxEstados = document.getElementById('graficoEstadoServicios').getContext('2d');
            const datosEstados = {
                finalizados: <?= $estadisticas['finalizados'] ?? 0 ?>,
                cancelados: <?= $estadisticas['cancelados'] ?? 0 ?>,
                pendientes: <?= $estadisticas['pendientes'] ?? 0 ?>,
                en_curso: <?= ($estadisticas['asignados'] ?? 0) + ($estadisticas['en_camino'] ?? 0) ?>
            };

            graficoEstadoServicios = new Chart(ctxEstados, {
                type: 'pie',
                data: {
                    labels: ['Finalizados', 'Cancelados', 'Pendientes', 'En Curso'],
                    datasets: [{
                        data: [datosEstados.finalizados, datosEstados.cancelados, datosEstados.pendientes, datosEstados.en_curso],
                        backgroundColor: ['#1cc88a', '#e74a3b', '#f6c23e', '#36b9cc'],
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
                            text: 'Distribución por Estado'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });

            // Gráfico de tendencia (línea o barras)
            <?php 
            // Preparar datos para el gráfico de tendencia
            $fechas = [];
            $totales = [];
            $finalizados = [];
            $cancelados = [];
            
            if (isset($reporte['tendencia']) && !empty($reporte['tendencia'])) {
                foreach ($reporte['tendencia'] as $fecha => $datos) {
                    $fechas[] = $fecha;
                    $totales[] = $datos['total'];
                    $finalizados[] = $datos['finalizados'];
                    $cancelados[] = $datos['cancelados'];
                }
            }
            ?>
            
            const ctxTendencia = document.getElementById('graficoTendenciaServicios').getContext('2d');
            
            const fechasTendencia = <?= json_encode($fechas) ?>;
            const totalesTendencia = <?= json_encode($totales) ?>;
            const finalizadosTendencia = <?= json_encode($finalizados) ?>;
            const canceladosTendencia = <?= json_encode($cancelados) ?>;

            graficoTendenciaServicios = new Chart(ctxTendencia, {
                type: 'line',
                data: {
                    labels: fechasTendencia,
                    datasets: [
                        {
                            label: 'Total',
                            data: totalesTendencia,
                            backgroundColor: 'rgba(78, 115, 223, 0.05)',
                            borderColor: 'rgba(78, 115, 223, 1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3,
                            pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                            pointBorderColor: '#fff',
                            pointRadius: 3
                        },
                        {
                            label: 'Finalizados',
                            data: finalizadosTendencia,
                            backgroundColor: 'rgba(28, 200, 138, 0)',
                            borderColor: 'rgba(28, 200, 138, 1)',
                            borderWidth: 2,
                            tension: 0.3,
                            pointBackgroundColor: 'rgba(28, 200, 138, 1)',
                            pointBorderColor: '#fff',
                            pointRadius: 3
                        },
                        {
                            label: 'Cancelados',
                            data: canceladosTendencia,
                            backgroundColor: 'rgba(231, 74, 59, 0)',
                            borderColor: 'rgba(231, 74, 59, 1)',
                            borderWidth: 2,
                            tension: 0.3,
                            pointBackgroundColor: 'rgba(231, 74, 59, 1)',
                            pointBorderColor: '#fff',
                            pointRadius: 3
                        }
                    ]
                },
                options: {
                    responsive: true,
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
                        title: {
                            display: true,
                            text: 'Tendencia de Servicios'
                        }
                    }
                }
            });

            // Gráfico de top vehículos (barras horizontales)
            <?php if (!empty($top_vehiculos) && count($top_vehiculos) > 0): ?>
            const ctxTopVehiculos = document.getElementById('graficoTopVehiculos')?.getContext('2d');
            
            if (ctxTopVehiculos) {
                const vehiculosLabels = <?= json_encode(array_map(fn($v) => $v['numero_movil'] . ' - ' . $v['placa'], array_slice($top_vehiculos, 0, 5))) ?>;
                const vehiculosFinalizados = <?= json_encode(array_map(fn($v) => $v['finalizados'], array_slice($top_vehiculos, 0, 5))) ?>;
                const vehiculosCancelados = <?= json_encode(array_map(fn($v) => $v['cancelados'], array_slice($top_vehiculos, 0, 5))) ?>;

                graficoTopVehiculos = new Chart(ctxTopVehiculos, {
                    type: 'bar',
                    data: {
                        labels: vehiculosLabels,
                        datasets: [
                            {
                                label: 'Finalizados',
                                data: vehiculosFinalizados,
                                backgroundColor: 'rgba(28, 200, 138, 0.7)',
                                borderColor: 'rgba(28, 200, 138, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Cancelados',
                                data: vehiculosCancelados,
                                backgroundColor: 'rgba(231, 74, 59, 0.7)',
                                borderColor: 'rgba(231, 74, 59, 1)',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                beginAtZero: true,
                                stacked: false,
                                ticks: {
                                    precision: 0
                                }
                            },
                            y: {
                                stacked: false
                            }
                        },
                        plugins: {
                            title: {
                                display: true,
                                text: 'Top 5 Vehículos por Servicios'
                            }
                        }
                    }
                });
            }
            <?php endif; ?>
        }

        // Función para ver detalle de servicio
        function verDetalleServicio(servicioId) {
            const modal = document.getElementById('modalDetalleServicio');
            const contenido = document.getElementById('detalleServicioContenido');

            // Mostrar carga en el modal
            contenido.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';

            // Verificar si Bootstrap está disponible
            if (typeof bootstrap !== 'undefined') {
                const modalInstance = new bootstrap.Modal(modal);
                modalInstance.show();
            } else {
                console.error("Bootstrap no está disponible");
                // Fallback básico para mostrar el modal
                modal.style.display = 'block';
                modal.classList.add('show');
            }

            // Realizar solicitud al servidor
            fetch(`../Processings/obtener_detalle_servicio.php?id=${servicioId}`)
                .then(response => {
                    console.log("Respuesta recibida:", response);
                    return response.json();
                })
                .then(data => {
                    console.log('Datos recibidos:', data);
                    if (data.error) {
                        contenido.innerHTML = `<div class="alert alert-danger">${data.mensaje}</div>`;
                        return;
                    }

                    const servicio = data.servicio || {};
                    const eventos = data.eventos || [];
                    
                    // Determinar tiempo total
                    let tiempoTotal = 'En curso';
                    if (servicio.estado === 'finalizado' || servicio.estado === 'cancelado') {
                        if (servicio.fecha_fin && servicio.fecha_solicitud) {
                            const inicio = new Date(servicio.fecha_solicitud);
                            const fin = new Date(servicio.fecha_fin);
                            const diff = fin - inicio; // diferencia en milisegundos
                            const minutos = Math.floor(diff / 60000);
                            tiempoTotal = formatearTiempo(minutos);
                        }
                    }
                    
                    // Determinar cliente
                    const nombreCliente = servicio.cliente_nombre || servicio.cliente_telefono || 'N/A';

                    // Construir HTML del detalle
                    let html = `
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="m-0">Servicio #${servicio.id}</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Cliente:</strong> ${nombreCliente}</p>
                                    <p><strong>Teléfono:</strong> ${servicio.cliente_telefono || 'N/A'}</p>
                                    <p><strong>Dirección:</strong> ${servicio.direccion || 'N/A'}</p>
                                    <p><strong>Referencia:</strong> ${servicio.referencia || 'N/A'}</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Fecha Solicitud:</strong> ${formatearFechaHora(servicio.fecha_solicitud)}</p>
                                    <p><strong>Estado:</strong> ${formatearEstado(servicio.estado)}</p>
                                    <p><strong>Operador:</strong> ${servicio.operador_nombre || 'N/A'}</p>
                                    <p><strong>Tiempo Total:</strong> ${tiempoTotal}</p>
                                </div>
                            </div>
                        </div>
                    </div>`;

                    // Información del vehículo si existe
                    if (servicio.placa) {
                        html += `
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="m-0">Información del Vehículo</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Vehículo:</strong> ${servicio.numero_movil} - ${servicio.placa}</p>
                                        <p><strong>Marca/Modelo:</strong> ${servicio.marca || 'N/A'} / ${servicio.modelo || 'N/A'}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Color:</strong> ${servicio.color || 'N/A'}</p>
                                        <p><strong>Conductor:</strong> ${servicio.conductor_nombre || 'N/A'}</p>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    }

                    // Comentarios del cliente si existen
                    if (servicio.comentarios) {
                        html += `
                        <div class="card mb-4">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="m-0">Comentarios del Cliente</h5>
                            </div>
                            <div class="card-body">
                                <p>${servicio.comentarios}</p>
                            </div>
                        </div>`;
                    }

                    // Historial de eventos del servicio
                    if (eventos && eventos.length > 0) {
                        html += `
                        <div class="card mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="m-0">Historial de Eventos</h5>
                            </div>
                            <div class="card-body">
                                <div class="timeline">`;
                        
                        eventos.forEach(evento => {
                            html += `
                            <div class="timeline-item">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-0">${formatearFechaHora(evento.fecha_evento)}</h6>
                                    <p><strong>${evento.tipo}</strong>: ${evento.descripcion}</p>
                                    <small class="text-muted">Por: ${evento.usuario_nombre || 'Sistema'}</small>
                                </div>
                            </div>`;
                        });
                        
                        html += `
                                </div>
                            </div>
                        </div>`;
                    }

                    contenido.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    contenido.innerHTML = '<div class="alert alert-danger">Error al cargar los detalles del servicio</div>';
                });
        }

        // Función para formatear estado de servicio
        function formatearEstado(estado) {
            if (!estado) return 'N/A';
            
            const estados = {
                'pendiente': '<span class="badge bg-warning">Pendiente</span>',
                'asignado': '<span class="badge bg-primary">Asignado</span>',
                'en_camino': '<span class="badge bg-info">En camino</span>',
                'finalizado': '<span class="badge bg-success">Finalizado</span>',
                'cancelado': '<span class="badge bg-danger">Cancelado</span>'
            };
            
            return estados[estado] || estado;
        }
    });

    // Funciones auxiliares globales
    function formatearTiempo(minutos) {
        if (!minutos) return '0 min';
        minutos = parseInt(minutos);
        if (minutos < 60) return minutos + ' min';
        const horas = Math.floor(minutos / 60);
        const min = minutos % 60;
        return horas + 'h ' + (min > 0 ? min + 'min' : '');
    }

    function formatearFechaHora(fechaHora) {
        if (!fechaHora) return 'N/A';
        const f = new Date(fechaHora);
        return f.toLocaleDateString('es-ES') + ' ' + f.toLocaleTimeString('es-ES', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function formatearFecha(fecha) {
        if (!fecha) return 'N/A';
        const f = new Date(fecha);
        return f.toLocaleDateString('es-ES');
    }
</script>

<?php include 'Layouts/footer.php'; ?>