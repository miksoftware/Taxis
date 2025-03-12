<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Processings\obtener_detalle_sancion.php
// Iniciar sesión
session_start();

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    echo '<div class="alert alert-danger">Debe iniciar sesión para realizar esta acción</div>';
    exit;
}

// Verificar ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="alert alert-danger">ID de sanción no proporcionado</div>';
    exit;
}

// Incluir la conexión a la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir controlador necesario
require_once '../Controllers/SancionController.php';

$sancionController = new SancionController($pdo);

// Obtener la sanción
$id = intval($_GET['id']);
$sancion = $sancionController->obtener($id);

if (isset($sancion['error'])) {
    echo '<div class="alert alert-danger">' . $sancion['mensaje'] . '</div>';
    exit;
}
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h6 class="fw-bold">Vehículo</h6>
        <p>
            <span class="badge bg-secondary">Placa</span> <?php echo htmlspecialchars($sancion['vehiculo_placa']); ?><br>
            <span class="badge bg-secondary">Móvil</span> <?php echo htmlspecialchars($sancion['vehiculo_movil']); ?>
        </p>
    </div>
    <div class="col-md-6">
        <h6 class="fw-bold">Estado de sanción</h6>
        <p>
            <?php if ($sancion['estado'] === 'activa'): ?>
                <span class="badge bg-danger">Activa</span>
                <?php if (isset($sancion['tiempo_restante'])): ?>
                    <small class="ms-2">Tiempo restante: <?php echo $sancion['tiempo_restante']; ?></small>
                <?php endif; ?>
            <?php elseif ($sancion['estado'] === 'cumplida'): ?>
                <span class="badge bg-success">Cumplida</span>
            <?php elseif ($sancion['estado'] === 'anulada'): ?>
                <span class="badge bg-warning text-dark">Anulada</span>
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-6">
        <h6 class="fw-bold">Artículo de sanción</h6>
        <p>
            <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($sancion['articulo_codigo']); ?></span><br>
            <?php echo htmlspecialchars($sancion['articulo_descripcion']); ?><br>
            <small class="text-muted">
                Tiempo de sanción: <?php echo $sancionController->formatearTiempoMinutos($sancion['tiempo_sancion']); ?>
            </small>
        </p>
    </div>
    <div class="col-md-6">
        <h6 class="fw-bold">Fechas</h6>
        <p>
            <span class="badge bg-secondary">Inicio</span> <?php echo date('d/m/Y H:i:s', strtotime($sancion['fecha_inicio'])); ?><br>
            <span class="badge bg-secondary">Finalización</span> <?php echo date('d/m/Y H:i:s', strtotime($sancion['fecha_fin'])); ?>
        </p>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <h6 class="fw-bold">Motivo de la sanción</h6>
        <p><?php echo nl2br(htmlspecialchars($sancion['motivo'])); ?></p>
    </div>
</div>

<?php if (isset($sancion['historial']) && !empty($sancion['historial'])): ?>
    <div class="row mb-3">
        <div class="col-12">
            <h6 class="fw-bold">Historial de la sanción</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Acción</th>
                            <th>Usuario</th>
                            <th>Comentario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sancion['historial'] as $entrada): ?>
                            <tr>
                                <td><?php echo $entrada['fecha_formateada']; ?></td>
                                <td>
                                    <?php if ($entrada['accion'] === 'aplicada'): ?>
                                        <span class="badge bg-primary">Aplicada</span>
                                    <?php elseif ($entrada['accion'] === 'anulada'): ?>
                                        <span class="badge bg-warning text-dark">Anulada</span>
                                    <?php elseif ($entrada['accion'] === 'cumplida'): ?>
                                        <span class="badge bg-success">Cumplida</span>
                                    <?php else: ?>
                                        <span class="badge bg-info"><?php echo ucfirst($entrada['accion']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($entrada['usuario_nombre']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($entrada['comentario'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>