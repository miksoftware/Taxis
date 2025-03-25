<?php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    exit('Acceso denegado');
}

// Verificar que se proporcionó el ID del cliente
if (!isset($_GET['cliente_id']) || empty($_GET['cliente_id'])) {
    exit('ID de cliente no válido');
}

$cliente_id = (int)$_GET['cliente_id'];

// Importar controladores necesarios
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

require_once '../Controllers/ServicioController.php';
$servicioController = new ServicioController($pdo);

require_once '../Controllers/ClienteController.php';
$clienteController = new ClienteController($pdo);

// Obtener datos del cliente
$cliente = $clienteController->obtenerPorId($cliente_id);
if (!$cliente) {
    exit('Cliente no encontrado');
}

// Obtener historial de servicios del cliente directamente con SQL
try {
    // Consulta para obtener los servicios con todos los datos necesarios
    $sql = "
    SELECT s.*, 
           d.direccion as direccion,
           v.placa as vehiculo_placa,
           v.numero_movil as movil,
           CONCAT(u.nombre, ' ', u.apellidos) as conductor_nombre
    FROM servicios s
    LEFT JOIN direcciones d ON s.direccion_id = d.id
    LEFT JOIN vehiculos v ON s.vehiculo_id = v.id
    LEFT JOIN usuarios u ON v.conductor_id = u.id
    WHERE s.cliente_id = :cliente_id
    ORDER BY s.fecha_solicitud DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
    $stmt->execute();
    $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas básicas del cliente
    $estadisticas = $servicioController->obtenerEstadisticasCliente($cliente_id);
    
} catch (PDOException $e) {
    exit('Error al obtener historial: ' . $e->getMessage());
}
?>

<div class="row mb-3">
    <div class="col-md-6">
        <div class="card border-0 bg-light">
            <div class="card-body">
                <h6 class="card-title">Datos del cliente</h6>
                <p class="mb-1"><strong>Nombre:</strong> <?= htmlspecialchars($cliente['nombre']) ?></p>
                <p class="mb-1"><strong>Teléfono:</strong> <?= htmlspecialchars($cliente['telefono']) ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 bg-light">
            <div class="card-body">
                <h6 class="card-title">Estadísticas</h6>
                <div class="row">
                    <div class="col-6">
                        <p class="mb-1"><strong>Total servicios:</strong> <?= $estadisticas['total'] ?? 0 ?></p>
                        <p class="mb-1"><strong>Finalizados:</strong> <?= $estadisticas['finalizados'] ?? 0 ?></p>
                    </div>
                    <div class="col-6">
                        <p class="mb-1"><strong>Cancelados:</strong> <?= $estadisticas['cancelados'] ?? 0 ?></p>
                        <p class="mb-1"><strong>Pendientes:</strong> <?= ($estadisticas['total'] - $estadisticas['finalizados'] - $estadisticas['cancelados']) ?? 0 ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($historial)): ?>
<div class="alert alert-info">
    No hay servicios registrados para este cliente.
</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead>
            <tr>
                <th>Fecha y Hora</th>
                <th>Direccion</th>
                <th>Vehículo</th>
                <th class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($historial as $servicio): ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($servicio['fecha_solicitud'])) ?></td>
                <td><?= htmlspecialchars($servicio['direccion'] ?? 'N/A') ?></td>
                <td>
                    <?php if (!empty($servicio['vehiculo_placa'])): ?>
                        <?= htmlspecialchars($servicio['vehiculo_placa']) ?>
                        (<?= htmlspecialchars($servicio['movil']) ?>)
                    <?php else: ?>
                        No asignado
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php 
                    $badgeClass = 'bg-secondary';
                    switch ($servicio['estado']) {
                        case 'pendiente': $badgeClass = 'bg-warning text-dark'; break;
                        case 'asignado': $badgeClass = 'bg-info'; break;
                        case 'en_camino': $badgeClass = 'bg-primary'; break;
                        case 'finalizado': $badgeClass = 'bg-success'; break;
                        case 'cancelado': $badgeClass = 'bg-danger'; break;
                    }
                    ?>
                    <span class="badge <?= $badgeClass ?>"><?= ucfirst(str_replace('_', ' ', $servicio['estado'])) ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
// Obtener las direcciones más frecuentes
$direcciones = [];
foreach ($historial as $servicio) {
    if (!empty($servicio['direccion'])) {
        if (!isset($direcciones[$servicio['direccion']])) {
            $direcciones[$servicio['direccion']] = 0;
        }
        $direcciones[$servicio['direccion']]++;
    }
}

// Ordenar por frecuencia
arsort($direcciones);
// Tomar las 3 más frecuentes
$direcciones = array_slice($direcciones, 0, 3);
?>

<!-- Direcciones más frecuentes -->
<div class="card mt-4">
    <div class="card-header bg-light">
        <h6 class="card-title mb-0">Direcciones más frecuentes</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <?php foreach ($direcciones as $direccion => $cantidad): ?>
            <div class="col-md-4 mb-2">
                <div class="d-flex align-items-center">
                    <i class="bi bi-geo-alt text-primary me-2"></i>
                    <div>
                        <div class="small text-truncate" style="max-width: 200px;"><?= htmlspecialchars($direccion) ?></div>
                        <div class="text-muted small"><?= $cantidad ?> servicios</div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>