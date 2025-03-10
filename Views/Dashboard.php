<?php 
// Incluir el encabezado
require_once 'Layouts/header.php';
?>

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
            <div class="card-header">
                <h5 class="card-title mb-0">Actividad reciente</h5>
            </div>
            <div class="card-body">
                <p class="text-muted text-center">No hay actividad reciente para mostrar.</p>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir el pie de página
require_once 'Layouts/footer.php';
?>