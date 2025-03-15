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

require_once '../Controllers/SancionController.php';
require_once '../Controllers/VehiculoController.php';
require_once '../Controllers/ArticuloSancionController.php';

$sancionController = new SancionController($pdo);
$vehiculoController = new VehiculoController($pdo);
$articuloController = new ArticuloSancionController($pdo);

// Obtener todos los vehículos para el filtro
$vehiculos = $vehiculoController->listar();
// Obtener todos los artículos de sanción para el filtro
$articulos = $articuloController->obtenerActivos();

// Incluir header
include 'Layouts/header.php';

// Obtener parámetros
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('-90 days'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$vehiculo_id = isset($_GET['vehiculo_id']) ? $_GET['vehiculo_id'] : '';
$articulo_id = isset($_GET['articulo_id']) ? $_GET['articulo_id'] : '';
$estado = isset($_GET['estado']) ? $_GET['estado'] : '';

// Si ya hay parámetros de filtro, cargar el reporte inicial
$hayFiltros = isset($_GET['fecha_inicio']) || isset($_GET['vehiculo_id']) || isset($_GET['articulo_id']) || isset($_GET['estado']);
if ($hayFiltros) {
    // Primero crear los filtros
    $filtros = [
        'fecha_inicio' => $fecha_inicio,
        'fecha_fin' => $fecha_fin,
        'vehiculo_id' => $vehiculo_id,
        'articulo_id' => $articulo_id,
        'estado' => $estado
    ];

    // Luego generar el reporte
    $sanciones = $sancionController->listar($filtros);
    $estadisticas = $sancionController->obtenerEstadisticas($filtros);
} else {
    $sanciones = [];
    $estadisticas = ['por_estado' => ['activa' => 0, 'cumplida' => 0, 'anulada' => 0], 'articulos_top' => [], 'total' => 0];
}
?>

<div class="container-fluid">
    <!-- Encabezado de página -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Reporte de Sanciones</h1>
        <div>
            <a href="Reportes.php" class="btn btn-sm btn-secondary shadow-sm">
                <i class="bi bi-arrow-left"></i> Volver a Reportes
            </a>
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
                <div class="col-md-2">
                    <label for="vehiculo_id" class="form-label">Vehículo</label>
                    <select class="form-select" id="vehiculo_id" name="vehiculo_id">
                        <option value="">Todos los vehículos</option>
                        <?php
                        if (!isset($vehiculos['error'])) {
                            foreach ($vehiculos as $vehiculo): ?>
                                <option value="<?= $vehiculo['id'] ?>" <?= $vehiculo_id == $vehiculo['id'] ? 'selected' : '' ?>><?= htmlspecialchars($vehiculo['placa'] . ' (' . $vehiculo['numero_movil'] . ')') ?></option>
                        <?php endforeach;
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="articulo_id" class="form-label">Artículo</label>
                    <select class="form-select" id="articulo_id" name="articulo_id">
                        <option value="">Todos los artículos</option>
                        <?php
                        if (!isset($articulos['error'])) {
                            foreach ($articulos as $articulo): ?>
                                <option value="<?= $articulo['id'] ?>" <?= $articulo_id == $articulo['id'] ? 'selected' : '' ?>><?= htmlspecialchars($articulo['codigo'] . ' - ' . substr($articulo['descripcion'], 0, 30)) ?></option>
                        <?php endforeach;
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="estado" class="form-label">Estado</label>
                    <select class="form-select" id="estado" name="estado">
                        <option value="">Todos los estados</option>
                        <option value="activa" <?= $estado == 'activa' ? 'selected' : '' ?>>Activa</option>
                        <option value="cumplida" <?= $estado == 'cumplida' ? 'selected' : '' ?>>Cumplida</option>
                        <option value="anulada" <?= $estado == 'anulada' ? 'selected' : '' ?>>Anulada</option>
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
        <!-- Tarjetas de resumen -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Sanciones</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $hayFiltros ? $estadisticas['total'] : '0' ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-exclamation-triangle-fill fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Sanciones Activas</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $hayFiltros ? ($estadisticas['por_estado']['activa'] ?? 0) : '0' ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-clock-history fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Sanciones Cumplidas</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $hayFiltros ? ($estadisticas['por_estado']['cumplida'] ?? 0) : '0' ?>
                            </div>
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
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Sanciones Anuladas</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $hayFiltros ? ($estadisticas['por_estado']['anulada'] ?? 0) : '0' ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-x-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos -->
    <?php if ($hayFiltros && $estadisticas['total'] > 0): ?>
        <div class="row mb-4">
            <!-- Gráfico de Estado de Sanciones -->
            <div class="col-xl-6">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Estado de Sanciones</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-pie pt-4">
                            <canvas id="graficoEstadoSanciones"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfico de Artículos más Sancionados -->
            <div class="col-xl-6">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Artículos más Aplicados</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-bar pt-4">
                            <canvas id="graficoArticulosSanciones"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tabla de vehículos más sancionados -->
    <?php if ($hayFiltros && !empty($sanciones) && !isset($sanciones['error'])): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Vehículos más Sancionados</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="tablaVehiculosSancionados" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Placa</th>
                                <th>Número Móvil</th>
                                <th>Total Sanciones</th>
                                <th>Activas</th>
                                <th>Cumplidas</th>
                                <th>Anuladas</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Agrupar sanciones por vehículo
                            $vehiculosSancionados = [];
                            foreach ($sanciones as $sancion) {
                                $vehiculoId = $sancion['vehiculo_id'];

                                if (!isset($vehiculosSancionados[$vehiculoId])) {
                                    $vehiculosSancionados[$vehiculoId] = [
                                        'placa' => $sancion['vehiculo_placa'],
                                        'numero_movil' => $sancion['vehiculo_movil'],
                                        'total' => 0,
                                        'activa' => 0,
                                        'cumplida' => 0,
                                        'anulada' => 0,
                                        'id' => $vehiculoId
                                    ];
                                }

                                $vehiculosSancionados[$vehiculoId]['total']++;
                                $vehiculosSancionados[$vehiculoId][$sancion['estado']]++;
                            }

                            // Ordenar por total de sanciones (mayor a menor)
                            usort($vehiculosSancionados, function ($a, $b) {
                                return $b['total'] - $a['total'];
                            });

                            foreach ($vehiculosSancionados as $vehiculo):
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($vehiculo['placa']) ?></td>
                                    <td><?= htmlspecialchars($vehiculo['numero_movil']) ?></td>
                                    <td class="text-center"><?= $vehiculo['total'] ?></td>
                                    <td class="text-center">
                                        <?php if ($vehiculo['activa'] > 0): ?>
                                            <span class="badge bg-warning"><?= $vehiculo['activa'] ?></span>
                                        <?php else: ?>
                                            <?= $vehiculo['activa'] ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= $vehiculo['cumplida'] ?></td>
                                    <td class="text-center"><?= $vehiculo['anulada'] ?></td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-info verDetalle" data-id="<?= $vehiculo['id'] ?>">
                                            <i class="bi bi-eye"></i> Ver detalle
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Listado de sanciones -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Detalle de Sanciones</h6>
            <div>
                <span id="totalRegistros">
                    <?= $hayFiltros && !isset($sanciones['error']) ? count($sanciones) : '0' ?> registros encontrados
                </span>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="tablaSanciones" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Vehículo</th>
                            <th>Artículo</th>
                            <th>Estado</th>
                            <th>Duración</th>
                            <th>Tiempo Restante</th>
                            <th>Aplicada por</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($hayFiltros && !empty($sanciones) && !isset($sanciones['error'])): ?>
                            <?php foreach ($sanciones as $sancion): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($sancion['fecha_inicio'])) ?></td>
                                    <td>
                                        <?= htmlspecialchars($sancion['vehiculo_placa']) ?>
                                        (<?= htmlspecialchars($sancion['vehiculo_movil']) ?>)
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($sancion['articulo_codigo']) ?> -
                                        <?= htmlspecialchars(substr($sancion['articulo_descripcion'], 0, 30)) ?>
                                        <?= strlen($sancion['articulo_descripcion']) > 30 ? '...' : '' ?>
                                    </td>
                                    <td class="text-center">
                                        <?= ($sancion['estado']) ?>
                                    </td>
                                    <td class="text-center">
                                        <?= formatearTiempo($sancion['tiempo_sancion']) ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($sancion['estado'] === 'activa'): ?>
                                            <?= $sancion['tiempo_restante'] ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($sancion['usuario_nombre']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No se encontraron datos para el período seleccionado</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Detalles de Vehículo -->
<div class="modal fade" id="modalDetalleVehiculo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de Sanciones del Vehículo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detalleVehiculoContenido">
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
        let graficoEstadoSanciones;
        let graficoArticulosSanciones;
        let graficoTendencia;

        // Inicializar gráficos si hay datos
        <?php if ($hayFiltros && $estadisticas['total'] > 0): ?>
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
                document.getElementById('fecha_inicio').value = '<?= date('Y-m-d', strtotime('-90 days')); ?>';
                document.getElementById('fecha_fin').value = '<?= date('Y-m-d'); ?>';
                aplicarFiltros();
            });
        }

        // Agregar eventos a los botones de detalle
        document.querySelectorAll('.verDetalle').forEach(btn => {
            console.log("Configurando botón verDetalle:", btn);
            btn.addEventListener('click', function() {
                const vehiculoId = this.getAttribute('data-id');
                console.log("Click en verDetalle para vehículo ID:", vehiculoId);
                verDetalleVehiculo(vehiculoId);
            });
        });

        // Función para aplicar filtros
        function aplicarFiltros() {
            const formData = new FormData(formFiltros);
            const queryParams = new URLSearchParams(formData).toString();
            window.location.href = 'ReporteSanciones.php?' + queryParams;
        }

        // Función para inicializar los gráficos
        function inicializarGraficos() {
            // Gráfico de estado de sanciones (pastel)
            const ctxEstados = document.getElementById('graficoEstadoSanciones').getContext('2d');
            const datosEstados = {
                activa: <?= $estadisticas['por_estado']['activa'] ?? 0 ?>,
                cumplida: <?= $estadisticas['por_estado']['cumplida'] ?? 0 ?>,
                anulada: <?= $estadisticas['por_estado']['anulada'] ?? 0 ?>
            };

            graficoEstadoSanciones = new Chart(ctxEstados, {
                type: 'pie',
                data: {
                    labels: ['Activas', 'Cumplidas', 'Anuladas'],
                    datasets: [{
                        data: [datosEstados.activa, datosEstados.cumplida, datosEstados.anulada],
                        backgroundColor: ['#f6c23e', '#1cc88a', '#e74a3b'],
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

            // Gráfico de artículos más aplicados (barras)
            const ctxArticulos = document.getElementById('graficoArticulosSanciones').getContext('2d');

            <?php
            // Preparar datos para el gráfico de artículos
            $etiquetasArticulos = [];
            $datosArticulos = [];

            if (!empty($estadisticas['articulos_top'])) {
                foreach ($estadisticas['articulos_top'] as $articulo) {
                    $etiquetasArticulos[] = $articulo['codigo'];
                    $datosArticulos[] = $articulo['total'];
                }
            }
            ?>

            const etiquetasArticulos = <?= json_encode($etiquetasArticulos) ?>;
            const datosArticulos = <?= json_encode($datosArticulos) ?>;

            graficoArticulosSanciones = new Chart(ctxArticulos, {
                type: 'bar',
                data: {
                    labels: etiquetasArticulos,
                    datasets: [{
                        label: 'Cantidad de Sanciones',
                        data: datosArticulos,
                        backgroundColor: 'rgba(78, 115, 223, 0.7)',
                        borderColor: 'rgba(78, 115, 223, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    maintainAspectRatio: false,
                    responsive: true,
                    scales: {
                        x: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Artículos más Aplicados'
                        }
                    }
                }
            });

            // Gráfico de tendencia de sanciones (línea)
            <?php if (isset($estadisticas['tendencia']) && !empty($estadisticas['tendencia'])): ?>
                const ctxTendencia = document.getElementById('graficoTendenciaSanciones').getContext('2d');

                const fechasTendencia = <?= json_encode(array_keys($estadisticas['tendencia'])) ?>;
                const datosTendencia = <?= json_encode(array_values($estadisticas['tendencia'])) ?>;

                graficoTendencia = new Chart(ctxTendencia, {
                    type: 'line',
                    data: {
                        labels: fechasTendencia,
                        datasets: [{
                            label: 'Cantidad de Sanciones',
                            data: datosTendencia,
                            fill: false,
                            borderColor: 'rgba(54, 162, 235, 1)',
                            tension: 0.1,
                            pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                            pointBorderColor: '#fff',
                            pointRadius: 4
                        }]
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
                                text: 'Tendencia de Sanciones'
                            }
                        }
                    }
                });
            <?php endif; ?>
        }

        // Función para ver detalle de vehículo
        function verDetalleVehiculo(vehiculoId) {
            const modal = document.getElementById('modalDetalleVehiculo');
            const contenido = document.getElementById('detalleVehiculoContenido');

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

            // Construir parámetros para la solicitud
            const params = new URLSearchParams();
            params.append('vehiculo_id', vehiculoId);
            params.append('fecha_inicio', document.getElementById('fecha_inicio').value);
            params.append('fecha_fin', document.getElementById('fecha_fin').value);

            // Realizar solicitud al servidor para obtener historial de sanciones del vehículo
            fetch('../Processings/obtener_historial_sanciones_vehiculo.php?' + params.toString())
                .then(response => {
                    console.log("Respuesta recibida:", response);
                    return response.json();
                })
                .then(data => {
                    console.log("Datos recibidos:", data);
                    if (data.error) {
                        contenido.innerHTML = `<div class="alert alert-danger">${data.mensaje}</div>`;
                        return;
                    }

                    // Datos del vehículo
                    const vehiculo = data.vehiculo || {};
                    const sanciones = data.sanciones || [];
                    const conductores = data.conductores || [];

                    let html = `
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="m-0">Vehículo: ${vehiculo.placa || 'N/A'} (Móvil: ${vehiculo.numero_movil || 'N/A'})</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Marca:</strong> ${vehiculo.marca || 'N/A'}</p>
                                    <p><strong>Modelo:</strong> ${vehiculo.modelo || 'N/A'}</p>
                                    <p><strong>Color:</strong> ${vehiculo.color || 'N/A'}</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Estado:</strong> ${formatearEstadoVehiculo(vehiculo.estado)}</p>
                                    <p><strong>Total Sanciones:</strong> ${sanciones.length}</p>
                                    <p><strong>Propietario:</strong> ${vehiculo.propietario_nombre || 'N/A'}</p>
                                </div>
                            </div>
                        </div>
                    </div>`;

                    // Mostrar conductores si existen
                    if (conductores && conductores.length > 0) {
                        html += `
                        <div class="card mb-4">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="m-0">Conductores asignados</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Nombre</th>
                                                <th>Cédula</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>`;

                        conductores.forEach(conductor => {
                            html += `
                                <tr>
                                    <td>${conductor.nombre || 'N/A'}</td>
                                    <td>${conductor.cedula || 'N/A'}</td>
                                    <td>${formatearEstadoConductor(conductor.estado)}</td>
                                </tr>`;
                        });

                        html += `
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>`;
                    }

                    // Mostrar historial de sanciones
                    if (sanciones.length > 0) {
                        html += `
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="m-0">Historial de Sanciones</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Artículo</th>
                                                <th>Duración</th>
                                                <th>Estado</th>
                                                <th>Aplicada por</th>
                                            </tr>
                                        </thead>
                                        <tbody>`;

                        sanciones.forEach(sancion => {
                            html += `
                            <tr>
                                <td>${formatearFechaHora(sancion.fecha_inicio)}</td>
                                <td>${sancion.articulo_codigo || 'N/A'} - ${sancion.articulo_descripcion || 'N/A'}</td>
                                <td>${formatearTiempo(sancion.tiempo_sancion)}</td>
                                <td>${formatearEstado(sancion.estado)}</td>
                                <td>${sancion.usuario_nombre || 'N/A'}</td>
                            </tr>`;
                        });

                        html += `
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>`;

                        // Añadir gráfico de artículos aplicados a este vehículo
                        if (data.estadisticas && data.estadisticas.articulos) {
                            html += `
                            <div class="card mb-4">
                                <div class="card-header bg-success text-white">
                                    <h5 class="m-0">Distribución de Artículos</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container" style="position: relative; height:250px;">
                                        <canvas id="graficoArticulosVehiculo"></canvas>
                                    </div>
                                </div>
                            </div>`;
                        }
                    } else {
                        html += `<div class="alert alert-info">No hay historial de sanciones para este vehículo en el período seleccionado.</div>`;
                    }

                    contenido.innerHTML = html;

                    // Crear gráfico de distribución de artículos si hay datos
                    if (data.estadisticas && data.estadisticas.articulos) {
                        const ctxArticulosVehiculo = document.getElementById('graficoArticulosVehiculo').getContext('2d');

                        const articulosLabels = [];
                        const articulosData = [];
                        const articulosColors = [
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(255, 206, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(153, 102, 255, 0.7)',
                            'rgba(255, 159, 64, 0.7)'
                        ];

                        Object.keys(data.estadisticas.articulos).forEach((codigo, index) => {
                            articulosLabels.push(codigo);
                            articulosData.push(data.estadisticas.articulos[codigo].count);
                        });

                        new Chart(ctxArticulosVehiculo, {
                            type: 'doughnut',
                            data: {
                                labels: articulosLabels,
                                datasets: [{
                                    data: articulosData,
                                    backgroundColor: articulosColors,
                                    borderColor: articulosColors.map(color => color.replace('0.7', '1')),
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'right',
                                        labels: {
                                            boxWidth: 15
                                        }
                                    }
                                }
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    contenido.innerHTML = '<div class="alert alert-danger">Error al cargar los detalles del vehículo</div>';
                });
        }

        // Función para formatear estado de sanción
        function formatearEstado(estado) {
            if (!estado) return 'N/A';

            const estados = {
                'activa': '<span class="badge bg-warning">Activa</span>',
                'cumplida': '<span class="badge bg-success">Cumplida</span>',
                'anulada': '<span class="badge bg-danger">Anulada</span>'
            };

            return estados[estado] || estado;
        }

        // Función para formatear estado de vehículo
        function formatearEstadoVehiculo(estado) {
            if (!estado) return 'N/A';

            const estados = {
                'activo': '<span class="badge bg-success">Activo</span>',
                'inactivo': '<span class="badge bg-danger">Inactivo</span>',
                'mantenimiento': '<span class="badge bg-warning">En Mantenimiento</span>',
                'sancionado': '<span class="badge bg-dark">Sancionado</span>'
            };

            return estados[estado] || estado;
        }

        // Función para formatear estado del conductor
        function formatearEstadoConductor(estado) {
            if (!estado) return 'N/A';

            const estados = {
                'activo': '<span class="badge bg-success">Activo</span>',
                'inactivo': '<span class="badge bg-danger">Inactivo</span>',
                'vacaciones': '<span class="badge bg-info">Vacaciones</span>',
                'licencia': '<span class="badge bg-warning">En Licencia</span>'
            };

            return estados[estado] || estado;
        }
    });

    // Funciones globales auxiliares
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