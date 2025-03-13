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

// Obtener estadísticas básicas para mostrar en el resumen
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

require_once '../Controllers/DashboardController.php';
$dashboardController = new DashboardController($pdo);
$stats = $dashboardController->obtenerEstadisticas('hoy');

// Incluir header
include 'Layouts/header.php';
?>

<div class="container-fluid">
    <!-- Encabezado con stats rápidos -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Reportes y Estadísticas</h1>
        
        <div class="d-flex">
            <div class="me-3 d-flex align-items-center">
                <span class="badge bg-primary rounded-pill me-2"><?= $stats['servicios_total'] ?? 0 ?></span>
                <span class="small text-gray-600">Servicios hoy</span>
            </div>
            <a href="Dashboard.php" class="btn btn-sm btn-primary shadow-sm">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard
            </a>
        </div>
    </div>
    
    <!-- Resumen de datos rápido -->
    <div class="row mb-4">
        <div class="col-xl-12">
            <div class="card shadow-sm border-0 bg-light">
                <div class="card-body p-3">
                    <div class="row g-0 text-center">
                        <div class="col-md-3 col-sm-6 border-end">
                            <div class="p-3">
                                <h3 class="display-6 fw-bold text-primary"><?= $stats['servicios_finalizados'] ?? 0 ?></h3>
                                <p class="mb-0 text-muted small">Servicios finalizados</p>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 border-end">
                            <div class="p-3">
                                <h3 class="display-6 fw-bold text-danger"><?= $stats['servicios_cancelados'] ?? 0 ?></h3>
                                <p class="mb-0 text-muted small">Servicios cancelados</p>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 border-end">
                            <div class="p-3">
                                <h3 class="display-6 fw-bold text-success"><?= $stats['vehiculos_disponibles'] ?? 0 ?></h3>
                                <p class="mb-0 text-muted small">Vehículos disponibles</p>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="p-3">
                                <h3 class="display-6 fw-bold text-warning"><?= $stats['servicios_pendientes'] ?? 0 ?></h3>
                                <p class="mb-0 text-muted small">Servicios pendientes</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <h5 class="mb-3 text-gray-700 border-start border-primary border-4 ps-2">Informes disponibles</h5>

    <!-- Categorías de reportes -->
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Análisis de Operaciones</h6>
                    <i class="bi bi-bar-chart-line fs-4 text-primary opacity-75"></i>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="ReporteUsuarios.php" class="btn btn-outline-primary d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-person-badge me-2"></i> Rendimiento de Usuarios</span>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                    <p class="card-text mt-3 small text-muted">
                        Reportes enfocados en la operación diaria del sistema, eficiencia de usuarios y métricas de servicio.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-danger">Cumplimiento Normativo</h6>
                    <i class="bi bi-clipboard-check fs-4 text-danger opacity-75"></i>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="ReporteSanciones.php" class="btn btn-outline-danger d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-exclamation-triangle me-2"></i> Sanciones y Multas</span>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                    <p class="card-text mt-3 small text-muted">
                        Reportes de cumplimiento normativo, sanciones activas y control de documentación legal.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-success">Gestión de Servicios</h6>
                    <i class="bi bi-truck fs-4 text-success opacity-75"></i>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="ReporteVehiculos.php" class="btn btn-outline-success d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-car-front me-2"></i> Estadísticas de Servicios</span>
                            <i class="bi bi-chevron-right"></i>
                        </a>                        
                    </div>
                    <p class="card-text mt-3 small text-muted">
                        Reportes de estado de servicios.
                    </p>
                </div>
            </div>
        </div>
    </div>    
</div>

<?php include 'Layouts/footer.php'; ?>