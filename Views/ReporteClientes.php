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

// Importar controladores necesarios
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

require_once '../Controllers/ClienteController.php';
$clienteController = new ClienteController($pdo);

require_once '../Controllers/ServicioController.php';
$servicioController = new ServicioController($pdo);

require_once '../Controllers/DireccionController.php';
$direccionController = new DireccionController($pdo);

// Variables de búsqueda y paginación
$busqueda = $_GET['busqueda'] ?? '';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$porPagina = 10;

// Calcular el offset para la paginación
$offset = ($pagina - 1) * $porPagina;

// Obtener datos según los filtros
$clientes = $clienteController->listarPaginado($busqueda, $porPagina, $offset);
$totalClientes = $clienteController->contarTotal($busqueda);
$totalPaginas = ceil($totalClientes / $porPagina);

// DEBUG: Verificar valores
// echo "Búsqueda: " . $busqueda . " | Página: " . $pagina . " | Offset: " . $offset;
// var_dump($clientes);

// Incluir header
include 'Layouts/header.php';
?>

<div class="container-fluid">
    <!-- Encabezado de la página -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Reportes de Clientes</h1>
        
        <div class="d-flex">
            <a href="Reportes.php" class="btn btn-sm btn-primary shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Volver a Reportes
            </a>
        </div>
    </div>
    
    <!-- Buscador y listado de clientes -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold text-primary">Buscar cliente</h6>
        </div>
        <div class="card-body">
            <form action="" method="GET" class="mb-4">
                <div class="row g-2">
                    <div class="col-md-8">
                        <input type="text" name="busqueda" class="form-control" placeholder="Buscar por nombre o teléfono..." value="<?= htmlspecialchars($busqueda) ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-1"></i> Buscar
                        </button>
                        <a href="ReporteClientes.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-1"></i> Limpiar
                        </a>
                    </div>
                </div>
            </form>
            
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Teléfono</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clientes)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-4">No se encontraron clientes</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td><?= htmlspecialchars($cliente['nombre']) ?></td>
                                <td><?= htmlspecialchars($cliente['telefono']) ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary ver-historial" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#historialModal" 
                                            data-cliente-id="<?= $cliente['id'] ?>" 
                                            data-cliente-nombre="<?= htmlspecialchars($cliente['nombre']) ?>">
                                        <i class="bi bi-clock-history"></i> Historial
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            <?php if ($totalPaginas > 1): ?>
            <nav aria-label="Paginación" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?pagina=<?= $pagina - 1 ?>&busqueda=<?= urlencode($busqueda) ?>">Anterior</a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                    <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                        <a class="page-link" href="?pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                        <a class="page-link" href="?pagina=<?= $pagina + 1 ?>&busqueda=<?= urlencode($busqueda) ?>">Siguiente</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de Historial de Servicios -->
<div class="modal fade" id="historialModal" tabindex="-1" aria-labelledby="historialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="historialModalLabel">Historial de Servicios</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center py-5" id="historialLoading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2">Cargando historial...</p>
                </div>
                <div id="historialContenido" class="d-none">
                    <!-- Aquí se cargará el historial mediante AJAX -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php include 'Layouts/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manejar click en ver historial
    document.querySelectorAll('.ver-historial').forEach(button => {
        button.addEventListener('click', function() {
            const clienteId = this.getAttribute('data-cliente-id');
            const clienteNombre = this.getAttribute('data-cliente-nombre');
            
            // Actualizar título del modal
            document.getElementById('historialModalLabel').textContent = `Historial de Servicios - ${clienteNombre}`;
            
            // Mostrar loading
            document.getElementById('historialLoading').classList.remove('d-none');
            document.getElementById('historialContenido').classList.add('d-none');
            
            // Cargar los datos mediante AJAX
            fetch(`../Processings/ObtenerHistorialCliente.php?cliente_id=${clienteId}`)
                .then(response => response.text())
                .then(html => {
                    // Ocultar loading y mostrar contenido
                    document.getElementById('historialLoading').classList.add('d-none');
                    const contenido = document.getElementById('historialContenido');
                    contenido.innerHTML = html;
                    contenido.classList.remove('d-none');
                })
                .catch(error => {
                    console.error('Error al cargar el historial:', error);
                    document.getElementById('historialContenido').innerHTML = `<div class="alert alert-danger">Error al cargar el historial: ${error.message}</div>`;
                    document.getElementById('historialLoading').classList.add('d-none');
                    document.getElementById('historialContenido').classList.remove('d-none');
                });
        });
    });
});
</script>