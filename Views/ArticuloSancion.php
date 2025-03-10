<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Views\ArticuloSancion.php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir el encabezado
require_once 'Layouts/header.php';

// Inicializar conexión a la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir el controlador necesario
require_once '../Controllers/ArticuloSancionController.php';

// Inicializar controlador
$articuloController = new ArticuloSancionController($pdo);

// Obtener todos los artículos
$filtros = [];
$articulos = $articuloController->listar($filtros);

// Mensaje de operaciones
$mensaje = isset($_SESSION['mensaje']) ? $_SESSION['mensaje'] : null;
$tipo_mensaje = isset($_SESSION['tipo_mensaje']) ? $_SESSION['tipo_mensaje'] : null;

// Limpiar mensajes de sesión después de mostrarlos
unset($_SESSION['mensaje']);
unset($_SESSION['tipo_mensaje']);
?>

<div class="welcome-message">
    <h2>Gestión de Artículos de Sanción</h2>
    <p>Administre todos los artículos del reglamento interno que pueden aplicarse como sanción a los vehículos.</p>
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
            <i class="bi bi-journal-text me-2"></i> Artículos del Reglamento
        </h5>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevoArticuloModal">
            <i class="bi bi-plus-lg"></i> Nuevo Artículo
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="tablaArticulos" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Código</th>
                        <th>Descripción</th>
                        <th>Tiempo de Sanción</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (is_array($articulos) && !isset($articulos['error'])): ?>
                        <?php foreach ($articulos as $articulo): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($articulo['id']); ?></td>
                                <td><?php echo htmlspecialchars($articulo['codigo']); ?></td>
                                <td><?php echo htmlspecialchars($articulo['descripcion']); ?></td>
                                <td>
                                    <?php echo $articuloController->obtenerTextoTiempo($articulo['tiempo_sancion']); ?>
                                    <span class="text-muted">(<?php echo $articulo['tiempo_sancion']; ?> min)</span>
                                </td>
                                <td>
                                    <?php if ($articulo['activo']): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary btn-sm" 
                                            onclick="editarArticulo(<?php echo $articulo['id']; ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($articulo['activo']): ?>
                                            <button type="button" class="btn btn-warning btn-sm"
                                                onclick="cambiarEstado(<?php echo $articulo['id']; ?>, 0)">
                                                <i class="bi bi-toggle-on"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-secondary btn-sm"
                                                onclick="cambiarEstado(<?php echo $articulo['id']; ?>, 1)">
                                                <i class="bi bi-toggle-off"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-danger btn-sm"
                                            onclick="confirmarEliminar(<?php echo $articulo['id']; ?>, '<?php echo htmlspecialchars($articulo['codigo']); ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No hay artículos registrados</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nuevo Artículo -->
<div class="modal fade" id="nuevoArticuloModal" tabindex="-1" aria-labelledby="nuevoArticuloModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formNuevoArticulo" action="../Processings/procesar_articulo.php" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="nuevoArticuloModalLabel">
                        <i class="bi bi-journal-plus me-2"></i> Nuevo Artículo de Sanción
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="codigo" class="form-label">Código</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" required maxlength="20" placeholder="Ej. ART-001">
                        <div class="form-text">Identificador único del artículo en el reglamento</div>
                    </div>
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3" required placeholder="Describa el artículo y su aplicabilidad"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="tiempo_sancion" class="form-label">Tiempo de Sanción (minutos)</label>
                        <input type="number" class="form-control" id="tiempo_sancion" name="tiempo_sancion" required min="1" max="44640" placeholder="Ej. 60">
                        <div class="form-text">
                            Referencia: 60 min = 1 hora, 1440 min = 1 día, 10080 min = 1 semana, 44640 min = 1 mes
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" checked>
                            <label class="form-check-label" for="activo">
                                Artículo activo
                            </label>
                            <div class="form-text">Los artículos inactivos no aparecerán al aplicar sanciones</div>
                        </div>
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

<!-- Modal Editar Artículo -->
<div class="modal fade" id="editarArticuloModal" tabindex="-1" aria-labelledby="editarArticuloModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formEditarArticulo" action="../Processings/procesar_articulo.php" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="editarArticuloModalLabel">
                        <i class="bi bi-pencil-square me-2"></i> Editar Artículo de Sanción
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editar_id" name="id">
                    <div class="mb-3">
                        <label for="editar_codigo" class="form-label">Código</label>
                        <input type="text" class="form-control" id="editar_codigo" name="codigo" required maxlength="20">
                    </div>
                    <div class="mb-3">
                        <label for="editar_descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="editar_descripcion" name="descripcion" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="editar_tiempo_sancion" class="form-label">Tiempo de Sanción (minutos)</label>
                        <input type="number" class="form-control" id="editar_tiempo_sancion" name="tiempo_sancion" required min="1" max="44640">
                        <div class="form-text">
                            Referencia: 60 min = 1 hora, 1440 min = 1 día, 10080 min = 1 semana, 44640 min = 1 mes
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="editar_activo" name="activo" value="1">
                            <label class="form-check-label" for="editar_activo">
                                Artículo activo
                            </label>
                        </div>
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

<!-- Modal Confirmación de Eliminación -->
<div class="modal fade" id="confirmarEliminarModal" tabindex="-1" aria-labelledby="confirmarEliminarModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formEliminar" action="../Processings/procesar_articulo.php" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmarEliminarModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="eliminar_id" name="id">
                    <p>¿Está seguro que desea eliminar el artículo <span id="eliminar_codigo" class="fw-bold"></span>?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i> 
                        La eliminación de un artículo podría afectar a las sanciones históricas asociadas a él.
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

<!-- Modal Cambiar Estado -->
<div class="modal fade" id="cambiarEstadoModal" tabindex="-1" aria-labelledby="cambiarEstadoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formCambiarEstado" action="../Processings/procesar_articulo.php" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="cambiarEstadoModalLabel">
                        <i class="bi bi-toggle2-off me-2"></i> <span id="titulo_estado">Cambiar Estado</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="estado_id" name="id">
                    <input type="hidden" id="estado_valor" name="estado">
                    <p id="texto_estado"></p>
                    <div id="advertencia_estado" class="alert alert-warning mt-3" style="display: none;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span id="texto_advertencia"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="accion" value="cambiar_estado" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Confirmar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script para la funcionalidad -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar DataTable si está disponible
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#tablaArticulos').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json'
            },
            responsive: true
        });
    }
});

function editarArticulo(id) {
    // Obtener datos del artículo por AJAX
    fetch('../Processings/obtener_articulo.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.mensaje);
                return;
            }
            
            // Llenar el formulario
            document.getElementById('editar_id').value = data.id;
            document.getElementById('editar_codigo').value = data.codigo;
            document.getElementById('editar_descripcion').value = data.descripcion;
            document.getElementById('editar_tiempo_sancion').value = data.tiempo_sancion;
            document.getElementById('editar_activo').checked = data.activo == 1;
            
            // Mostrar el modal
            new bootstrap.Modal(document.getElementById('editarArticuloModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar datos del artículo');
        });
}

function confirmarEliminar(id, codigo) {
    document.getElementById('eliminar_id').value = id;
    document.getElementById('eliminar_codigo').textContent = codigo;
    
    new bootstrap.Modal(document.getElementById('confirmarEliminarModal')).show();
}

function cambiarEstado(id, estado) {
    document.getElementById('estado_id').value = id;
    document.getElementById('estado_valor').value = estado;
    
    // Configurar textos según el estado
    if (estado == 1) {
        document.getElementById('titulo_estado').textContent = "Activar Artículo";
        document.getElementById('texto_estado').textContent = "¿Está seguro que desea activar este artículo? Esta acción permitirá que sea utilizado al aplicar sanciones.";
        document.getElementById('advertencia_estado').style.display = 'none';
    } else {
        document.getElementById('titulo_estado').textContent = "Desactivar Artículo";
        document.getElementById('texto_estado').textContent = "¿Está seguro que desea desactivar este artículo? Esta acción impedirá que sea utilizado al aplicar nuevas sanciones.";
        document.getElementById('texto_advertencia').textContent = "La desactivación no afectará a las sanciones ya aplicadas con este artículo.";
        document.getElementById('advertencia_estado').style.display = 'block';
    }
    
    new bootstrap.Modal(document.getElementById('cambiarEstadoModal')).show();
}
</script>

<?php
// Incluir el pie de página
require_once 'Layouts/footer.php';
?>