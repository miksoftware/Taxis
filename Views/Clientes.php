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

// Incluir la conexión a la base de datos y controladores necesarios
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

require_once '../Controllers/ClienteController.php';
require_once '../Controllers/ServicioController.php';

$clienteController = new ClienteController($pdo);
$servicioController = new ServicioController($pdo);

// Obtener todos los clientes (con paginación)
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$items_por_pagina = 15;
$offset = ($pagina_actual - 1) * $items_por_pagina;

// Filtros de búsqueda
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : '';

// Obtener clientes según filtros
$clientes = $clienteController->listarPaginado($filtro, $items_por_pagina, $offset);
$total_clientes = $clienteController->contarTotal($filtro);
$total_paginas = ceil($total_clientes / $items_por_pagina);

// Obtener el mes actual para el conteo de servicios mensuales
$mes_actual = date('m');
$año_actual = date('Y');

// Incluir header
$titulo_pagina = "Gestión de Clientes";
include 'Layouts/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Gestión de Clientes</h1>
    
    <!-- Buscador de clientes -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold">Buscar clientes</h6>
        </div>
        <div class="card-body">
            <form method="get" class="mb-3">
                <div class="input-group">
                    <input type="text" class="form-control" name="filtro" placeholder="Buscar por nombre o teléfono" value="<?= htmlspecialchars($filtro) ?>">
                    <div class="input-group-append">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-search"></i> Buscar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de clientes -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold">Listado de Clientes</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="tablaClientes" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Teléfono</th>
                            <th>Nombre</th>
                            <th>Servicios este mes</th>
                            <th>Total servicios</th>
                            <th>Fecha registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($clientes) && !isset($clientes['error'])): ?>
                            <?php foreach ($clientes as $cliente): ?>
                                <?php 
                                    // Obtener contadores de servicios
                                    $servicios_mes = $servicioController->contarPorClienteMes($cliente['id'], $mes_actual, $año_actual);
                                    $servicios_total = $servicioController->contarPorCliente($cliente['id']);
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($cliente['telefono']) ?></td>
                                    <td><?= htmlspecialchars($cliente['nombre']) ?></td>
                                    <td class="text-center">
                                        <?= $servicios_mes ?>
                                    </td>
                                    <td class="text-center">
                                        <?= $servicios_total ?>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($cliente['fecha_registro'])) ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info verDirecciones" data-id="<?= $cliente['id'] ?>" data-nombre="<?= htmlspecialchars($cliente['nombre']) ?>">
                                            <i class="bi bi-geo-alt"></i> Direcciones
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No se encontraron clientes</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>
                <nav aria-label="Navegación de páginas">
                    <ul class="pagination justify-content-center">
                        <?php if ($pagina_actual > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?pagina=<?= $pagina_actual - 1 ?>&filtro=<?= urlencode($filtro) ?>">Anterior</a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link">Anterior</span>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $pagina_actual - 2); $i <= min($total_paginas, $pagina_actual + 2); $i++): ?>
                            <li class="page-item <?= $i === $pagina_actual ? 'active' : '' ?>">
                                <a class="page-link" href="?pagina=<?= $i ?>&filtro=<?= urlencode($filtro) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($pagina_actual < $total_paginas): ?>
                            <li class="page-item">
                                <a class="page-link" href="?pagina=<?= $pagina_actual + 1 ?>&filtro=<?= urlencode($filtro) ?>">Siguiente</a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link">Siguiente</span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal para ver/eliminar direcciones -->
<div class="modal fade" id="direccionesModal" tabindex="-1" aria-labelledby="direccionesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="direccionesModalLabel">Direcciones</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="listaDirecciones" class="mb-3">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                    </div>
                </div>
                <div id="nuevaDireccionForm" class="border-top pt-3 mt-3" style="display: none;">
                    <div class="mb-3">
                        <label for="nuevaDireccion" class="form-label">Nueva dirección</label>
                        <input type="text" class="form-control" id="nuevaDireccion" placeholder="Ingrese la dirección">
                    </div>
                    <div class="mb-3">
                        <label for="nuevaReferencia" class="form-label">Referencia (opcional)</label>
                        <input type="text" class="form-control" id="nuevaReferencia" placeholder="Ej: Casa amarilla, portón negro">
                    </div>
                    <input type="hidden" id="clienteIdDireccion" value="">
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary me-2" id="cancelarNuevaDireccion">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="guardarNuevaDireccion">Guardar</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" id="agregarDireccion">
                    <i class="bi bi-plus-circle"></i> Agregar dirección
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript para la gestión de clientes -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Ver direcciones de un cliente
    document.querySelectorAll('.verDirecciones').forEach(btn => {
        btn.addEventListener('click', function() {
            const clienteId = this.getAttribute('data-id');
            const clienteNombre = this.getAttribute('data-nombre');
            
            document.getElementById('clienteIdDireccion').value = clienteId;
            document.getElementById('direccionesModalLabel').textContent = `Direcciones de ${clienteNombre}`;
            
            // Cargar direcciones
            cargarDireccionesCliente(clienteId);
            
            // Mostrar modal
            new bootstrap.Modal(document.getElementById('direccionesModal')).show();
        });
    });
    
    // Agregar nueva dirección (mostrar formulario)
    document.getElementById('agregarDireccion').addEventListener('click', function() {
        document.getElementById('nuevaDireccionForm').style.display = 'block';
        document.getElementById('nuevaDireccion').focus();
    });
    
    // Cancelar nueva dirección
    document.getElementById('cancelarNuevaDireccion').addEventListener('click', function() {
        document.getElementById('nuevaDireccionForm').style.display = 'none';
        document.getElementById('nuevaDireccion').value = '';
        document.getElementById('nuevaReferencia').value = '';
    });
    
    // Guardar nueva dirección
    document.getElementById('guardarNuevaDireccion').addEventListener('click', function() {
        const clienteId = document.getElementById('clienteIdDireccion').value;
        const direccion = document.getElementById('nuevaDireccion').value.trim();
        const referencia = document.getElementById('nuevaReferencia').value.trim();
        
        if (!direccion) {
            alert('La dirección no puede estar vacía');
            return;
        }
        
        const formData = new FormData();
        formData.append('cliente_id', clienteId);
        formData.append('direccion', direccion);
        formData.append('referencia', referencia);
        formData.append('accion', 'crear');
        
        fetch('../Processings/procesar_direccion.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Error: ' + data.mensaje);
            } else {
                // Limpiar formulario
                document.getElementById('nuevaDireccion').value = '';
                document.getElementById('nuevaReferencia').value = '';
                document.getElementById('nuevaDireccionForm').style.display = 'none';
                
                // Recargar direcciones
                cargarDireccionesCliente(clienteId);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al guardar dirección');
        });
    });
    
    // Editar cliente
    document.querySelectorAll('.editarCliente').forEach(btn => {
        btn.addEventListener('click', function() {
            const clienteId = this.getAttribute('data-id');
            
            // Cargar datos del cliente
            fetch(`../Processings/obtener_cliente.php?id=${clienteId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Error: ' + data.mensaje);
                    } else {
                        document.getElementById('editClienteId').value = data.id;
                        document.getElementById('editTelefono').value = data.telefono;
                        document.getElementById('editNombre').value = data.nombre;
                        document.getElementById('editNotas').value = data.notas || '';
                        
                        // Mostrar modal
                        new bootstrap.Modal(document.getElementById('editarClienteModal')).show();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar datos del cliente');
                });
        });
    });
    
    // Guardar cambios de cliente
    document.getElementById('guardarCambiosCliente').addEventListener('click', function() {
        const clienteId = document.getElementById('editClienteId').value;
        const nombre = document.getElementById('editNombre').value.trim();
        const notas = document.getElementById('editNotas').value.trim();
        
        if (!nombre) {
            alert('El nombre no puede estar vacío');
            return;
        }
        
        const formData = new FormData();
        formData.append('id', clienteId);
        formData.append('nombre', nombre);
        formData.append('notas', notas);
        formData.append('accion', 'actualizar');
        
        fetch('../Processings/procesar_cliente.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Error: ' + data.mensaje);
            } else {
                // Cerrar modal
                bootstrap.Modal.getInstance(document.getElementById('editarClienteModal')).hide();
                // Recargar la página para ver los cambios
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al guardar cambios');
        });
    });
    
    // Función para cargar direcciones de un cliente
    function cargarDireccionesCliente(clienteId) {
        const contenedor = document.getElementById('listaDirecciones');
        contenedor.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            </div>
        `;
        
        fetch(`../Processings/obtener_direcciones.php?cliente_id=${clienteId}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    contenedor.innerHTML = `<div class="alert alert-danger">${data.mensaje}</div>`;
                } else if (data.direcciones && data.direcciones.length > 0) {
                    let html = '<div class="list-group">';
                    
                    data.direcciones.forEach(dir => {
                        html += `
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">${dir.direccion}</h6>
                                    ${dir.referencia ? `<small class="text-muted">${dir.referencia}</small>` : ''}
                                </div>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-warning marcarFrecuente" data-id="${dir.id}" data-frecuente="${dir.es_frecuente}">
                                        <i class="bi ${dir.es_frecuente == 1 ? 'bi-star-fill' : 'bi-star'}"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger eliminarDireccion" data-id="${dir.id}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                    contenedor.innerHTML = html;
                    
                    // Agregar eventos a los botones de eliminar y marcar como frecuente
                    setupDireccionesEventListeners();
                } else {
                    contenedor.innerHTML = '<div class="alert alert-info">Este cliente no tiene direcciones registradas.</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                contenedor.innerHTML = '<div class="alert alert-danger">Error al cargar direcciones</div>';
            });
    }
    
    // Configurar eventos para los botones en las direcciones
    function setupDireccionesEventListeners() {
        // Eliminar dirección
        document.querySelectorAll('.eliminarDireccion').forEach(btn => {
            btn.addEventListener('click', function() {
                const direccionId = this.getAttribute('data-id');
                
                if (confirm('¿Está seguro de eliminar esta dirección?')) {
                    const formData = new FormData();
                    formData.append('id', direccionId);
                    formData.append('accion', 'eliminar');
                    
                    fetch('../Processings/procesar_direccion.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            if(data.mensaje.includes('servicios asociados')) {
                                if(confirm('Esta dirección tiene servicios asociados. ¿Desea marcarla como inactiva en lugar de eliminarla?')) {
                                    marcarDireccionInactiva(direccionId);
                                }
                            } else {
                                alert('Error: ' + data.mensaje);
                            }
                        } else {
                            // Recargar direcciones
                            const clienteId = document.getElementById('clienteIdDireccion').value;
                            cargarDireccionesCliente(clienteId);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error al eliminar dirección');
                    });
                }
            });
        });
        
        // Marcar como frecuente
        document.querySelectorAll('.marcarFrecuente').forEach(btn => {
            btn.addEventListener('click', function() {
                const direccionId = this.getAttribute('data-id');
                const esFrecuente = this.getAttribute('data-frecuente') == '1' ? '0' : '1';
                
                const formData = new FormData();
                formData.append('id', direccionId);
                formData.append('es_frecuente', esFrecuente);
                formData.append('accion', 'marcar_frecuente');
                
                fetch('../Processings/procesar_direccion.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Error: ' + data.mensaje);
                    } else {
                        // Recargar direcciones
                        const clienteId = document.getElementById('clienteIdDireccion').value;
                        cargarDireccionesCliente(clienteId);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al actualizar dirección');
                });
            });
        });
    }
    
    // Función para marcar dirección como inactiva en lugar de eliminarla
    function marcarDireccionInactiva(direccionId) {
        const formData = new FormData();
        formData.append('id', direccionId);
        formData.append('activa', '0');
        formData.append('accion', 'actualizar_estado');
        
        fetch('../Processings/procesar_direccion.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Error: ' + data.mensaje);
            } else {
                // Recargar direcciones
                const clienteId = document.getElementById('clienteIdDireccion').value;
                cargarDireccionesCliente(clienteId);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al actualizar dirección');
        });
    }
});
</script>

<?php include 'Layouts/footer.php'; ?>