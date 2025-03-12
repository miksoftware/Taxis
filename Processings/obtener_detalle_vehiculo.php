<?php
// Iniciar sesión
session_start();

// Verificar autenticación 
if (!isset($_SESSION['usuario_id'])) {
    echo '<div class="alert alert-danger">No autorizado</div>';
    exit;
}

// Verificar ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="alert alert-danger">ID no proporcionado</div>';
    exit;
}

// Incluir la conexión a la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir los controladores necesarios
require_once '../Controllers/VehiculoController.php';
require_once '../Controllers/SancionController.php';

// Inicializar controladores
$vehiculoController = new VehiculoController($pdo);
$sancionController = new SancionController($pdo);

// Obtener datos del vehículo
$id = intval($_GET['id']);
$vehiculo = $vehiculoController->obtener($id);

// Verificar si se obtuvo el vehículo
if (!$vehiculo || isset($vehiculo['error'])) {
    echo '<div class="alert alert-danger">No se encontró el vehículo solicitado</div>';
    exit;
}

// Obtener historial de sanciones si no está incluido en la respuesta del controlador
if (!isset($vehiculo['historial_sanciones'])) {
    $historialSanciones = $sancionController->obtenerHistorialVehiculo($id);
} else {
    $historialSanciones = $vehiculo['historial_sanciones'];
}
?>

<div class="row">
    <!-- Información básica del vehículo -->
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold">Datos del Vehículo</h6>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <th width="30%">Placa:</th>
                        <td><?php echo htmlspecialchars($vehiculo['placa']); ?></td>
                    </tr>
                    <tr>
                        <th>Nº Móvil:</th>
                        <td><?php echo htmlspecialchars($vehiculo['numero_movil']); ?></td>
                    </tr>
                    <tr>
                        <th>Estado:</th>
                        <td>
                            <?php
                            $estado_texto = '';
                            $badge_class = '';
                            
                            switch ($vehiculo['estado']) {
                                case 'disponible':
                                    $estado_texto = 'Disponible';
                                    $badge_class = 'bg-success';
                                    break;         
                                case 'sancionado':
                                    $estado_texto = 'Sancionado';
                                    $badge_class = 'bg-danger';
                                    break;
                                case 'mantenimiento':
                                    $estado_texto = 'En mantenimiento';
                                    $badge_class = 'bg-warning text-dark';
                                    break;
                                case 'inactivo':
                                    $estado_texto = 'Inactivo';
                                    $badge_class = 'bg-secondary';
                                    break;
                                default:
                                    $estado_texto = ucfirst($vehiculo['estado']);
                                    $badge_class = 'bg-info';
                                    break;
                            }
                            ?>
                            <span class="badge <?php echo $badge_class; ?>"><?php echo $estado_texto; ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th>Fecha Registro:</th>
                        <td><?php echo date('d/m/Y H:i', strtotime($vehiculo['fecha_registro'])); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Información de sanción activa (si existe) -->
    <div class="col-lg-6">
        <?php if ($vehiculo['estado'] === 'sancionado' && isset($vehiculo['sancion_activa'])): ?>
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    <h6 class="m-0 font-weight-bold">Sanción Activa</h6>
                </div>
                <div class="card-body">
                    <table class="table">
                        <tr>
                            <th width="30%">Artículo:</th>
                            <td>
                                <?php 
                                echo htmlspecialchars($vehiculo['sancion_activa']['articulo_codigo'] . ' - ' . 
                                                     $vehiculo['sancion_activa']['articulo_descripcion']); 
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Tiempo:</th>
                            <td><?php echo htmlspecialchars($vehiculo['sancion_activa']['tiempo_sancion']); ?> minutos</td>
                        </tr>
                        <tr>
                            <th>Inicio:</th>
                            <td><?php echo date('d/m/Y H:i', strtotime($vehiculo['sancion_activa']['fecha_inicio'])); ?></td>
                        </tr>
                        <tr>
                            <th>Fin estimado:</th>
                            <td><?php echo date('d/m/Y H:i', strtotime($vehiculo['sancion_activa']['fecha_fin'])); ?></td>
                        </tr>
                        <tr>
                            <th>Tiempo restante:</th>
                            <td>
                                <?php if (isset($vehiculo['sancion_activa']['tiempo_restante'])): ?>
                                    <?php echo htmlspecialchars($vehiculo['sancion_activa']['tiempo_restante']); ?>
                                <?php else: ?>
                                    <span class="text-muted">No disponible</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Motivo:</th>
                            <td><?php echo nl2br(htmlspecialchars($vehiculo['sancion_activa']['motivo'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Historial de sanciones -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold">Historial de Sanciones</h6>
    </div>
    <div class="card-body">
        <?php if (is_array($historialSanciones) && !empty($historialSanciones) && !isset($historialSanciones['error'])): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Artículo</th>
                            <th>Motivo</th>
                            <th>Duración</th>
                            <th>Fecha Inicio</th>
                            <th>Fecha Fin</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historialSanciones as $sancion): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($sancion['articulo_codigo'] . ' - ' . $sancion['articulo_descripcion']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($sancion['motivo'])); ?></td>
                                <td><?php echo htmlspecialchars($sancion['tiempo_sancion']); ?> minutos</td>
                                <td><?php echo date('d/m/Y H:i', strtotime($sancion['fecha_inicio'])); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($sancion['fecha_fin'])); ?></td>
                                <td>
                                    <?php
                                    $estado_sancion = '';
                                    $badge_sancion = '';
                                    
                                    switch ($sancion['estado']) {
                                        case 'activa':
                                            $estado_sancion = 'Activa';
                                            $badge_sancion = 'bg-danger';
                                            break;
                                        case 'cumplida':
                                            $estado_sancion = 'Cumplida';
                                            $badge_sancion = 'bg-success';
                                            break;
                                        case 'anulada':
                                            $estado_sancion = 'Anulada';
                                            $badge_sancion = 'bg-warning text-dark';
                                            break;
                                        default:
                                            $estado_sancion = ucfirst($sancion['estado']);
                                            $badge_sancion = 'bg-info';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_sancion; ?>"><?php echo $estado_sancion; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i> Este vehículo no tiene historial de sanciones.
            </div>
        <?php endif; ?>
    </div>
</div>