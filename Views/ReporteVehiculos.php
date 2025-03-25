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
$operadores = $usuarioController->listar();
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

$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$items_por_pagina = 15;  // Puedes ajustar este número según tus necesidades
$offset = ($pagina_actual - 1) * $items_por_pagina;

// Generar reporte
$reporte = $reporteController->generarReporteServicios($filtros, $items_por_pagina, $offset);

// Solo para depuración
/*
echo '<pre>';
echo 'Estructura de $reporte: ';
print_r($reporte);
echo '</pre>';
exit;
*/

if (isset($reporte['error']) && $reporte['error']) {
    echo '<div class="alert alert-danger">';
    echo '<strong>Error:</strong> ' . $reporte['mensaje'];
    echo '</div>';
}

// Validación y normalización de datos
$estadisticas = is_array($reporte['estadisticas'] ?? null) ? $reporte['estadisticas'] : [
    'total_servicios' => 0,
    'finalizados' => 0,
    'cancelados' => 0,
    'pendientes' => 0,
    'asignados' => 0,
    'en_camino' => 0
];

$detalle_servicios = is_array($reporte['servicios'] ?? null) ? $reporte['servicios'] : [];
$top_vehiculos = is_array($reporte['top_vehiculos'] ?? null) ? $reporte['top_vehiculos'] : [];
$top_operadores = is_array($reporte['top_operadores'] ?? null) ? $reporte['top_operadores'] : [];
$total_servicios = isset($reporte['total_registros']) ? intval($reporte['total_registros']) : 0;
$total_paginas = ceil($total_servicios / $items_por_pagina);


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
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $estadisticas['total_servicios'] ?? 0 ?></div>
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
                                    <?= isset($estadisticas['total_servicios']) && $estadisticas['total_servicios'] > 0
                                        ? round(($estadisticas['finalizados'] * 100) / $estadisticas['total_servicios'], 1) . '%'
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
                        <?= $total_servicios ?> registros encontrados (Página <?= $pagina_actual ?> de <?= $total_paginas ?>)
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
                                                case 'pendiente':
                                                    $badge_class = 'bg-warning';
                                                    break;
                                                case 'asignado':
                                                    $badge_class = 'bg-primary';
                                                    break;
                                                case 'en_camino':
                                                    $badge_class = 'bg-info';
                                                    break;
                                                case 'finalizado':
                                                    $badge_class = 'bg-success';
                                                    break;
                                                case 'cancelado':
                                                    $badge_class = 'bg-danger';
                                                    break;
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

                <!-- Controles de Paginación -->
                <?php if ($hayFiltros && $total_paginas > 1): ?>
                    <div class="mt-4">
                        <nav aria-label="Navegación de páginas">
                            <ul class="pagination justify-content-center">
                                <!-- Botón Anterior -->
                                <?php if ($pagina_actual > 1): ?>
                                    <li class="page-item">
                                        <?php
                                        $params = $_GET;
                                        $params['pagina'] = $pagina_actual - 1;
                                        $query_string = http_build_query($params);
                                        ?>
                                        <a class="page-link" href="?<?= $query_string ?>" aria-label="Anterior">
                                            <span aria-hidden="true">&laquo;</span> Anterior
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link"><span aria-hidden="true">&laquo;</span> Anterior</span>
                                    </li>
                                <?php endif; ?>

                                <!-- Números de página -->
                                <?php
                                // Determinar el rango de páginas a mostrar
                                $rango = 2; // Mostrar 2 páginas antes y después de la actual
                                $inicio_rango = max(1, $pagina_actual - $rango);
                                $fin_rango = min($total_paginas, $pagina_actual + $rango);

                                // Mostrar primera página si no está en el rango
                                if ($inicio_rango > 1) {
                                    $params = $_GET;
                                    $params['pagina'] = 1;
                                    echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($params) . '">1</a></li>';
                                    if ($inicio_rango > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }

                                // Mostrar páginas del rango
                                for ($i = $inicio_rango; $i <= $fin_rango; $i++) {
                                    $params = $_GET;
                                    $params['pagina'] = $i;
                                    $active = ($i == $pagina_actual) ? 'active' : '';
                                    echo '<li class="page-item ' . $active . '"><a class="page-link" href="?' . http_build_query($params) . '">' . $i . '</a></li>';
                                }

                                // Mostrar última página si no está en el rango
                                if ($fin_rango < $total_paginas) {
                                    if ($fin_rango < $total_paginas - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    $params = $_GET;
                                    $params['pagina'] = $total_paginas;
                                    echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($params) . '">' . $total_paginas . '</a></li>';
                                }
                                ?>

                                <!-- Botón Siguiente -->
                                <?php if ($pagina_actual < $total_paginas): ?>
                                    <li class="page-item">
                                        <?php
                                        $params = $_GET;
                                        $params['pagina'] = $pagina_actual + 1;
                                        $query_string = http_build_query($params);
                                        ?>
                                        <a class="page-link" href="?<?= $query_string ?>" aria-label="Siguiente">
                                            Siguiente <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">Siguiente <span aria-hidden="true">&raquo;</span></span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>

                        <div class="text-center text-muted small">
                            Mostrando registros <?= ($offset + 1) ?> al <?= min($offset + $items_por_pagina, $total_servicios) ?> de un total de <?= $total_servicios ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <p><i class="bi bi-info-circle"></i> Utilice los filtros para generar el reporte de servicios.</p>
        </div>
    <?php endif; ?>
</div>

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
        <?php if ($hayFiltros && isset($estadisticas['total_servicios']) && $estadisticas['total_servicios'] > 0): ?>
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

                // Mantener solo fecha_inicio y fecha_fin, eliminar otros filtros
                const urlParams = new URLSearchParams();
                urlParams.append('fecha_inicio', document.getElementById('fecha_inicio').value);
                urlParams.append('fecha_fin', document.getElementById('fecha_fin').value);

                // Eliminar la paginación para volver a la primera página con los filtros básicos
                window.location.href = 'ReporteVehiculos.php?' + urlParams.toString();
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

            // Eliminar el parámetro de página para reiniciar en página 1 con nuevos filtros
            formData.delete('pagina');

            const queryParams = new URLSearchParams(formData).toString();
            window.location.href = 'ReporteVehiculos.php?' + queryParams;
        }

        // Función para inicializar los gráficos
        function inicializarGraficos() {
            // Gráfico de estado de servicios (pastel)
            const ctxEstados = document.getElementById('graficoEstadoServicios').getContext('2d');
            const datosEstados = {
                finalizados: <?= isset($estadisticas['finalizados']) ? (int)$estadisticas['finalizados'] : 0 ?>,
                cancelados: <?= isset($estadisticas['cancelados']) ? (int)$estadisticas['cancelados'] : 0 ?>,
                pendientes: <?= isset($estadisticas['pendientes']) ? (int)$estadisticas['pendientes'] : 0 ?>,
                en_curso: <?= (isset($estadisticas['asignados']) ? (int)$estadisticas['asignados'] : 0) +
                                (isset($estadisticas['en_camino']) ? (int)$estadisticas['en_camino'] : 0) ?>
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

            // Verificación robusta y conversión de datos
            if (isset($reporte['tendencia']) && !empty($reporte['tendencia'])) {
                foreach ($reporte['tendencia'] as $item) {
                    try {
                        // Si es un objeto o array asociativo con claves específicas
                        if (is_array($item) && isset($item['fecha'])) {
                            $fechas[] = date('d/m/Y', strtotime($item['fecha']));
                            $totales[] = (int)($item['total_servicios'] ?? 0);
                            $finalizados[] = (int)($item['finalizados'] ?? 0);
                            $cancelados[] = (int)($item['cancelados'] ?? 0);
                        }
                        // Si es un array indexado (numérico)
                        else if (is_array($item) && isset($item[0])) {
                            $fechas[] = date('d/m/Y', strtotime($item[0]));
                            $totales[] = (int)($item[1] ?? 0);
                            $finalizados[] = (int)($item[2] ?? 0);
                            $cancelados[] = (int)($item[3] ?? 0);
                        }
                        // Si la estructura es completamente diferente, intentaremos extraer información útil
                        else if (is_scalar($item) && is_string($item)) {
                            // Podría ser una fecha como string
                            $fechas[] = date('d/m/Y', strtotime($item));
                            $totales[] = 0;
                            $finalizados[] = 0;
                            $cancelados[] = 0;
                        }
                        // Ignoramos otros casos
                    } catch (Exception $e) {
                        // Simplemente ignoramos entradas erróneas
                        error_log("Error procesando datos de tendencia: " . $e->getMessage());
                    }
                }
            }

            // Asegurarnos de que hay datos para el gráfico
            if (empty($fechas)) {
                $fechas = [date('d/m/Y')];
                $totales = [0];
                $finalizados = [0];
                $cancelados = [0];
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
                    datasets: [{
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
                            datasets: [{
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