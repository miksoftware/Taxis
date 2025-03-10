<?php
// Iniciar sesión si no está iniciada
session_start();
// Incluir el encabezado
require_once 'Layouts/header.php';

// Inicializar conexión a la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir los controladores necesarios
require_once '../Controllers/VehiculoController.php';
require_once '../Controllers/ArticuloSancionController.php';

// Inicializar controladores
$vehiculoController = new VehiculoController($pdo);
$articuloController = new ArticuloSancionController($pdo);

// Obtener vehículos
$filtros = [];
$vehiculos = $vehiculoController->listar($filtros);

// Obtener artículos activos para el modal de sanciones
$articulos = $articuloController->obtenerActivos();

// Mensaje de operaciones
$mensaje = isset($_SESSION['mensaje']) ? $_SESSION['mensaje'] : null;
$tipo_mensaje = isset($_SESSION['tipo_mensaje']) ? $_SESSION['tipo_mensaje'] : null;

// Limpiar mensajes de sesión después de mostrarlos
unset($_SESSION['mensaje']);
unset($_SESSION['tipo_mensaje']);
?>

<div class="welcome-message">
    <h2>Gestión de Vehículos</h2>
    <p>Administre todos los vehículos del sistema, controle su estado y gestione sanciones.</p>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="bi bi-car-front me-2"></i> Vehículos registrados
        </h5>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevoVehiculoModal">
            <i class="bi bi-plus-lg"></i> Nuevo Vehículo
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="tablaVehiculos" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Placa</th>
                        <th>Nº Móvil</th>
                        <th>Estado</th>
                        <th>Fecha Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (is_array($vehiculos) && !isset($vehiculos['error'])): ?>
                        <?php foreach ($vehiculos as $vehiculo): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($vehiculo['id']); ?></td>
                                <td><?php echo htmlspecialchars($vehiculo['placa']); ?></td>
                                <td><?php echo htmlspecialchars($vehiculo['numero_movil']); ?></td>
                                <td>
                                    <?php
                                    $estado_texto = '';
                                    $badge_class = '';
                                    
                                    switch ($vehiculo['estado']) {
                                        case 'disponible':
                                            $estado_texto = 'Disponible';
                                            $badge_class = 'bg-success';
                                            break;
                                        case 'en_servicio':
                                            $estado_texto = 'En servicio';
                                            $badge_class = 'bg-primary';
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
                                    <?php if ($vehiculo['estado'] === 'sancionado' && isset($vehiculo['sancion_activa'])): ?>
                                        <span class="ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Tiempo restante: <?php echo $vehiculo['sancion_activa']['tiempo_restante']; ?>">
                                            <i class="bi bi-clock-history text-danger"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($vehiculo['fecha_registro'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-info btn-sm" 
                                            onclick="verDetalleVehiculo(<?php echo $vehiculo['id']; ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm" 
                                            onclick="editarVehiculo(<?php echo $vehiculo['id']; ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($vehiculo['estado'] !== 'sancionado'): ?>
                                            <button type="button" class="btn btn-danger btn-sm"
                                                onclick="confirmarEliminar(<?php echo $vehiculo['id']; ?>, '<?php echo htmlspecialchars($vehiculo['placa']); ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <button type="button" class="btn btn-warning btn-sm" 
                                                onclick="mostrarSancionForm(<?php echo $vehiculo['id']; ?>, '<?php echo htmlspecialchars($vehiculo['placa']); ?>', '<?php echo htmlspecialchars($vehiculo['numero_movil']); ?>')">
                                                <i class="bi bi-exclamation-triangle"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-success btn-sm"
                                                onclick="anularSancion(<?php echo $vehiculo['id']; ?>)">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No hay vehículos registrados</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nuevo Vehículo -->
<div class="modal fade" id="nuevoVehiculoModal" tabindex="-1" aria-labelledby="nuevoVehiculoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formNuevoVehiculo" action="../Processings/procesar_vehiculo.php" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="nuevoVehiculoModalLabel">
                        <i class="bi bi-car-front me-2"></i> Nuevo Vehículo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="placa" class="form-label">Placa</label>
                        <input type="text" class="form-control" id="placa" name="placa" required maxlength="8" placeholder="Ej. ABC123">
                        <div class="form-text">Formato: ABC123 o ABC1234</div>
                    </div>
                    <div class="mb-3">
                        <label for="numero_movil" class="form-label">Número de Móvil</label>
                        <input type="text" class="form-control" id="numero_movil" name="numero_movil" required maxlength="4" placeholder="Ej. 1234">
                        <div class="form-text">Número identificador del vehículo (máximo 4 dígitos)</div>
                    </div>
                    <div class="mb-3">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado" required>
                            <option value="disponible">Disponible</option>
                            <option value="en_servicio">En Servicio</option>
                            <option value="mantenimiento">Mantenimiento</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="accion" value="crear" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Vehículo -->
<div class="modal fade" id="editarVehiculoModal" tabindex="-1" aria-labelledby="editarVehiculoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formEditarVehiculo" action="../Processings/procesar_vehiculo.php" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="editarVehiculoModalLabel">
                        <i class="bi bi-pencil-square me-2"></i> Editar Vehículo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editar_id" name="id">
                    <div class="mb-3">
                        <label for="editar_placa" class="form-label">Placa</label>
                        <input type="text" class="form-control" id="editar_placa" name="placa" required maxlength="8">
                    </div>
                    <div class="mb-3">
                        <label for="editar_numero_movil" class="form-label">Número de Móvil</label>
                        <input type="text" class="form-control" id="editar_numero_movil" name="numero_movil" required maxlength="4">
                    </div>
                    <div class="mb-3">
                        <label for="editar_estado" class="form-label">Estado</label>
                        <select class="form-select" id="editar_estado" name="estado" required>
                            <option value="disponible">Disponible</option>
                            <option value="en_servicio">En Servicio</option>
                            <option value="mantenimiento">Mantenimiento</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="accion" value="actualizar" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detalles Vehículo -->
<div class="modal fade" id="detalleVehiculoModal" tabindex="-1" aria-labelledby="detalleVehiculoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detalleVehiculoModalLabel">
                    <i class="bi bi-info-circle me-2"></i> Detalles del Vehículo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detalleVehiculoContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Sancionar Vehículo -->
<div class="modal fade" id="sancionarModal" tabindex="-1" aria-labelledby="sancionarModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formSancionar" action="../Processings/procesar_sancion.php" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="sancionarModalLabel">
                        <i class="bi bi-exclamation-triangle me-2"></i> Aplicar Sanción
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="sancion_vehiculo_id" name="vehiculo_id">
                    <div class="mb-3">
                        <label class="form-label">Vehículo</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-car-front"></i></span>
                            <input type="text" class="form-control" id="sancion_vehiculo_info" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="articulo_id" class="form-label">Artículo de Sanción</label>
                        <select class="form-select" id="articulo_id" name="articulo_id" required>
                            <option value="">Seleccionar artículo...</option>
                            <?php if (is_array($articulos) && !isset($articulos['error'])): ?>
                                <?php foreach ($articulos as $articulo): ?>
                                    <option value="<?php echo $articulo['id']; ?>"><?php echo htmlspecialchars($articulo['codigo'] . ' - ' . $articulo['descripcion'] . ' (' . $articuloController->obtenerTextoTiempo($articulo['tiempo_sancion']) . ')'); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="motivo" class="form-label">Motivo</label>
                        <textarea class="form-control" id="motivo" name="motivo" rows="3" required placeholder="Describa el motivo de la sanción"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="accion" value="aplicar" class="btn btn-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i> Aplicar Sanción
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Confirmación de Eliminación -->
<div class="modal fade" id="confirmarEliminarModal" tabindex="-1" aria-labelledby="confirmarEliminarModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formEliminar" action="../Processings/procesar_vehiculo.php" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmarEliminarModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="eliminar_id" name="id">
                    <p>¿Está seguro que desea eliminar el vehículo <span id="eliminar_placa" class="fw-bold"></span>?</p>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i> Esta acción no se puede deshacer.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="accion" value="eliminar" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i> Eliminar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script para la funcionalidad -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips de Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Inicializar DataTable si está disponible
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#tablaVehiculos').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json'
            },
            responsive: true
        });
    }
});

function editarVehiculo(id) {
    // Obtener datos del vehículo por AJAX
    fetch('../Processings/obtener_vehiculo.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.mensaje);
                return;
            }
            
            // Llenar el formulario
            document.getElementById('editar_id').value = data.id;
            document.getElementById('editar_placa').value = data.placa;
            document.getElementById('editar_numero_movil').value = data.numero_movil;
            document.getElementById('editar_estado').value = data.estado;
            
            // Mostrar el modal
            new bootstrap.Modal(document.getElementById('editarVehiculoModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar datos del vehículo');
        });
}

function verDetalleVehiculo(id) {
    const detalleModal = new bootstrap.Modal(document.getElementById('detalleVehiculoModal'));
    const detalleContent = document.getElementById('detalleVehiculoContent');
    
    // Mostrar spinner de carga
    detalleContent.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
        </div>
    `;
    
    detalleModal.show();
    
    // Cargar detalles por AJAX
    fetch('../Processings/obtener_detalle_vehiculo.php?id=' + id)
        .then(response => response.text())
        .then(data => {
            detalleContent.innerHTML = data;
        })
        .catch(error => {
            console.error('Error:', error);
            detalleContent.innerHTML = '<div class="alert alert-danger">Error al cargar los detalles del vehículo</div>';
        });
}

function mostrarSancionForm(id, placa, movil) {
    document.getElementById('sancion_vehiculo_id').value = id;
    document.getElementById('sancion_vehiculo_info').value = `Placa: ${placa} | Móvil: ${movil}`;
    
    new bootstrap.Modal(document.getElementById('sancionarModal')).show();
}

function confirmarEliminar(id, placa) {
    document.getElementById('eliminar_id').value = id;
    document.getElementById('eliminar_placa').textContent = placa;
    
    new bootstrap.Modal(document.getElementById('confirmarEliminarModal')).show();
}

function anularSancion(vehiculoId) {
    // Redireccionar a la página de anular sanción
    window.location.href = 'anular_sancion.php?vehiculo_id=' + vehiculoId;
}
</script>

<?php
// Incluir el pie de página
require_once 'Layouts/footer.php';
?>