<?php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: Login.php');
    exit;
}

// Incluir el encabezado
require_once 'Layouts/header.php';

// Inicializar conexión a la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir controladores necesarios
require_once '../Controllers/VehiculoController.php';
require_once '../Controllers/ServicioController.php';

// Inicializar controladores
$vehiculoController = new VehiculoController($pdo);
$servicioController = new ServicioController($pdo);

// Obtener vehículos disponibles
$filtros = ['estado' => 'disponible'];
$vehiculos = $vehiculoController->listar($filtros);

// Obtener servicios pendientes y en proceso
$servicios_pendientes = $servicioController->listarPorEstado('pendiente');
$servicios_asignados = $servicioController->listarPorEstado('asignado');
$servicios_en_camino = $servicioController->listarPorEstado('en_camino');

// Mensaje de operaciones
$mensaje = isset($_SESSION['mensaje']) ? $_SESSION['mensaje'] : null;
$tipo_mensaje = isset($_SESSION['tipo_mensaje']) ? $_SESSION['tipo_mensaje'] : null;

// Limpiar mensajes de sesión después de mostrarlos
unset($_SESSION['mensaje']);
unset($_SESSION['tipo_mensaje']);
?>

<div class="welcome-message">
    <h2>Centro de Control de Servicios</h2>
    <p>Panel para la gestión de solicitudes de servicio y asignación de vehículos.</p>
</div>

<!-- Formulario rápido de búsqueda/registro de cliente y creación de servicio -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h5 class="card-title mb-0">
            <i class="bi bi-telephone-plus me-2"></i> Nueva Solicitud de Servicio
        </h5>
    </div>
    <div class="card-body">
        <form id="formBusquedaCliente" class="mb-3">
            <div class="input-group mb-2">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="telefono" class="form-control" placeholder="Ingrese número de teléfono..." autofocus>
                <button type="submit" class="btn btn-primary">Buscar</button>
            </div>
        </form>

        <div id="clienteInfo" style="display:none;" class="mb-3">
            <!-- Aquí se mostrará la información del cliente -->
        </div>

        <div id="direccionesContainer" style="display:none;" class="mb-3">
            <h6>Seleccione una dirección:</h6>
            <div id="listaDirecciones" class="list-group mb-2">
                <!-- Aquí se cargarán las direcciones -->
            </div>

            <div id="nuevaDireccionForm" style="display:none;">
                <div class="input-group mb-2">
                    <input type="text" id="nuevaDireccion" class="form-control" placeholder="Ingrese nueva dirección...">
                    <button type="button" id="guardarDireccion" class="btn btn-success">Guardar</button>
                    <button type="button" id="cancelarDireccion" class="btn btn-secondary">Cancelar</button>
                </div>
            </div>
            <button type="button" id="mostrarNuevaDireccion" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-plus-circle"></i> Otra dirección
            </button>
        </div>

        <div id="detallesServicioForm" style="display:none;">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="condicionServicio" class="form-label">Condición del servicio:</label>
                    <select id="condicionServicio" class="form-select">
                        <option value="normal">Normal</option>
                        <option value="urgente">Urgente</option>
                        <option value="preferencial">Preferencial</option>
                        <option value="especial">Especial</option>
                        <option value="programado">Programado</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="observaciones" class="form-label">Observaciones:</label>
                    <textarea id="observaciones" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <button type="button" id="crearServicio" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Crear Servicio
            </button>
        </div>
    </div>
</div>

<!-- Panel de servicios pendientes -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="bi bi-hourglass-split me-2"></i> Servicios Pendientes
        </h5>
        <span class="badge bg-warning" id="contadorPendientes"><?= count($servicios_pendientes) ?></span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="tablaPendientes">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Dirección</th>
                        <th>Solicitud</th>
                        <th>Condición</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($servicios_pendientes)): ?>
                        <?php foreach ($servicios_pendientes as $servicio): ?>
                            <tr>
                                <td><?= $servicio['id'] ?></td>
                                <td><?= $servicio['telefono'] ?></td>
                                <td><?= $servicio['direccion'] ?></td>
                                <td><?= date('H:i', strtotime($servicio['fecha_solicitud'])) ?></td>
                                <td>
                                    <?php if ($servicio['condicion'] == 'urgente'): ?>
                                        <span class="badge bg-danger">Urgente</span>
                                    <?php elseif ($servicio['condicion'] == 'preferencial'): ?>
                                        <span class="badge bg-primary">Preferencial</span>
                                    <?php elseif ($servicio['condicion'] == 'programado'): ?>
                                        <span class="badge bg-info">Programado</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Normal</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm asignarServicio"
                                        data-id="<?= $servicio['id'] ?>">
                                        <i class="bi bi-car-front"></i> Asignar
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm cancelarServicio"
                                        data-id="<?= $servicio['id'] ?>">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No hay servicios pendientes</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Panel de servicios asignados y en curso -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="bi bi-car-front me-2"></i> Servicios en Curso
        </h5>
        <span class="badge bg-primary" id="contadorEnCurso"><?= count($servicios_asignados) + count($servicios_en_camino) ?></span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="tablaEnCurso">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Dirección</th>
                        <th>Vehículo</th>
                        <th>Estado</th>
                        <th>Tiempo</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $servicios_en_curso = array_merge($servicios_asignados, $servicios_en_camino);
                    if (!empty($servicios_en_curso)): ?>
                        <?php foreach ($servicios_en_curso as $servicio): ?>
                            <tr>
                                <td><?= $servicio['id'] ?></td>
                                <td><?= $servicio['telefono'] ?></td>
                                <td><?= $servicio['direccion'] ?></td>
                                <td><?= $servicio['placa'] ?></td>
                                <td>
                                    <?php if ($servicio['estado'] == 'asignado'): ?>
                                        <span class="badge bg-info">Asignado</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary">En camino</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="tiempoTranscurrido" data-inicio="<?= $servicio['fecha_asignacion'] ?>">
                                        <?= $servicioController->calcularTiempoTranscurrido($servicio['fecha_asignacion']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($servicio['estado'] == 'asignado'): ?>
                                        <button type="button" class="btn btn-info btn-sm enCaminoServicio"
                                            data-id="<?= $servicio['id'] ?>">
                                            <i class="bi bi-signpost-2"></i> En camino
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-success btn-sm finalizarServicio"
                                        data-id="<?= $servicio['id'] ?>">
                                        <i class="bi bi-check-circle"></i> Finalizar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No hay servicios en curso</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para asignar vehículo -->
<div class="modal fade" id="asignarVehiculoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-car-front me-2"></i> Asignar Vehículo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="servicioId">
                <div class="mb-3">
                    <label for="vehiculoSelect" class="form-label">Seleccione un vehículo:</label>
                    <select id="vehiculoSelect" class="form-select">
                        <?php if (!empty($vehiculos) && !isset($vehiculos['error'])): ?>
                            <?php foreach ($vehiculos as $vehiculo): ?>
                                <option value="<?= $vehiculo['id'] ?>">
                                    <?= htmlspecialchars($vehiculo['numero_movil']) ?> -
                                    <?= htmlspecialchars($vehiculo['placa']) ?>
                                    <?php if (!empty($vehiculo['modelo'])): ?>
                                        (<?= htmlspecialchars($vehiculo['modelo']) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No hay vehículos disponibles</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> El vehículo seleccionado será asignado a este servicio y cambiará su estado a "ocupado".
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="confirmarAsignacion" class="btn btn-primary">Asignar</button>
            </div>
        </div>
    </div>
</div>

<!-- Script para la funcionalidad de servicios -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Variables para almacenar datos
        let clienteActual = null;
        let direccionSeleccionada = null;


        // Búsqueda de cliente por teléfono
        document.getElementById('formBusquedaCliente').addEventListener('submit', function(e) {
            e.preventDefault();
            const telefono = document.getElementById('telefono').value.trim();

            if (telefono) {
                // Primero intentamos buscar el cliente
                fetch('../Processings/buscar_cliente.php?telefono=' + telefono)
                    .then(response => response.json())
                    .then(data => {
                        if (data.cliente_existe) {
                            // Cliente encontrado, mostrar info y direcciones
                            clienteActual = data.cliente;
                            mostrarInfoCliente(data.cliente);
                            if (data.direcciones && Array.isArray(data.direcciones)) {
                                cargarDireccionesExistentes(data.direcciones);
                            } else {
                                // No tiene direcciones, mostrar formulario para agregar una
                                document.getElementById('direccionesContainer').style.display = 'block';
                                document.getElementById('nuevaDireccionForm').style.display = 'block';
                                document.getElementById('mostrarNuevaDireccion').style.display = 'none';
                                document.getElementById('nuevaDireccion').focus();
                            }
                        } else {
                            // Cliente no encontrado, crearlo automáticamente
                            crearClienteAutomatico(telefono);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error al buscar cliente');
                    });
            }
        });

        // Función para crear cliente automáticamente
        function crearClienteAutomatico(telefono) {
            const formData = new FormData();
            formData.append('telefono', telefono);
            formData.append('nombre', ''); // Nombre vacío para que use el predeterminado

            fetch('../Processings/guardar_cliente.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Error al crear cliente: ' + data.mensaje);
                    } else {
                        // Cliente creado, almacenar y mostrar
                        clienteActual = {
                            id: data.id,
                            nombre: data.nombre,
                            telefono: data.telefono
                        };

                        mostrarInfoCliente(clienteActual);

                        // Mostrar formulario para agregar direccción (cliente nuevo sin direcciones)
                        document.getElementById('direccionesContainer').style.display = 'block';
                        document.getElementById('nuevaDireccionForm').style.display = 'block';
                        document.getElementById('mostrarNuevaDireccion').style.display = 'none';
                        document.getElementById('nuevaDireccion').focus();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al crear cliente automáticamente');
                });
        }

        // Función para cargar direcciones existentes
        function cargarDireccionesExistentes(direcciones) {
            const listaDirecciones = document.getElementById('listaDirecciones');
            listaDirecciones.innerHTML = '';

            if (direcciones.length > 0) {
                direcciones.forEach(dir => {
                    const item = document.createElement('button');
                    item.type = 'button';
                    item.classList.add('list-group-item', 'list-group-item-action');
                    item.innerHTML = `<i class="bi bi-geo-alt me-2"></i> ${dir.direccion}`;
                    item.addEventListener('click', function() {
                        // Desmarcar selección anterior
                        listaDirecciones.querySelectorAll('button').forEach(btn => {
                            btn.classList.remove('active');
                        });

                        // Marcar selección actual
                        this.classList.add('active');
                        direccionSeleccionada = dir.id;

                        // Mostrar formulario de detalles de servicio
                        document.getElementById('detallesServicioForm').style.display = 'block';
                    });

                    listaDirecciones.appendChild(item);
                });
            }

            document.getElementById('direccionesContainer').style.display = 'block';
        }

        // Mostrar formulario de nueva dirección
        document.getElementById('mostrarNuevaDireccion').addEventListener('click', function() {
            document.getElementById('nuevaDireccionForm').style.display = 'block';
            document.getElementById('nuevaDireccion').focus();
        });

        // Cancelar ingreso de nueva dirección
        document.getElementById('cancelarDireccion').addEventListener('click', function() {
            document.getElementById('nuevaDireccionForm').style.display = 'none';
            document.getElementById('nuevaDireccion').value = '';
        });

        // Guardar nueva dirección
        document.getElementById('guardarDireccion').addEventListener('click', function() {
            const direccion = document.getElementById('nuevaDireccion').value.trim();

            if (direccion && clienteActual) {
                const formData = new FormData();
                formData.append('cliente_id', clienteActual.id);
                formData.append('direccion', direccion);

                fetch('../Processings/guardar_direccion.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            alert('Error al guardar dirección: ' + data.mensaje);
                        } else {
                            document.getElementById('nuevaDireccionForm').style.display = 'none';
                            document.getElementById('nuevaDireccion').value = '';

                            // Seleccionar automáticamente la nueva dirección
                            direccionSeleccionada = data.direccion_id;
                            cargarDirecciones(clienteActual.id);

                            // Mostrar formulario de detalles de servicio
                            document.getElementById('detallesServicioForm').style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error al guardar dirección');
                    });
            }
        });

        // Crear servicio
        document.getElementById('crearServicio').addEventListener('click', function() {
            if (!clienteActual || !direccionSeleccionada) {
                alert('Debe seleccionar un cliente y una dirección');
                return;
            }

            const condicion = document.getElementById('condicionServicio').value;
            const observaciones = document.getElementById('observaciones').value;

            const formData = new FormData();
            formData.append('cliente_id', clienteActual.id);
            formData.append('direccion_id', direccionSeleccionada);
            formData.append('condicion', condicion);
            formData.append('observaciones', observaciones);
            formData.append('accion', 'crear');

            fetch('../Processings/procesar_servicio.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Error al crear servicio: ' + data.mensaje);
                    } else {
                        // Limpiar formulario
                        document.getElementById('telefono').value = '';
                        document.getElementById('observaciones').value = '';
                        document.getElementById('clienteInfo').style.display = 'none';
                        document.getElementById('direccionesContainer').style.display = 'none';
                        document.getElementById('detallesServicioForm').style.display = 'none';

                        // Recargar la tabla de servicios pendientes
                        location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al crear servicio');
                });
        });

        // Asignar servicio (abrir modal)
        document.querySelectorAll('.asignarServicio').forEach(button => {
            button.addEventListener('click', function() {
                const servicioId = this.getAttribute('data-id');
                document.getElementById('servicioId').value = servicioId;

                // Mostrar modal
                new bootstrap.Modal(document.getElementById('asignarVehiculoModal')).show();
            });
        });

        // Confirmar asignación de vehículo
        document.getElementById('confirmarAsignacion').addEventListener('click', function() {
            const servicioId = document.getElementById('servicioId').value;
            const vehiculoId = document.getElementById('vehiculoSelect').value;

            const formData = new FormData();
            formData.append('servicio_id', servicioId);
            formData.append('vehiculo_id', vehiculoId);
            formData.append('accion', 'asignar');

            fetch('../Processings/procesar_servicio.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Error al asignar vehículo: ' + data.mensaje);
                    } else {
                        location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al asignar vehículo');
                });
        });

        // Finalizar servicio
        document.querySelectorAll('.finalizarServicio').forEach(button => {
            button.addEventListener('click', function() {
                if (confirm('¿Está seguro de finalizar este servicio?')) {
                    const servicioId = this.getAttribute('data-id');

                    const formData = new FormData();
                    formData.append('servicio_id', servicioId);
                    formData.append('accion', 'finalizar');

                    fetch('../Processings/procesar_servicio.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.error) {
                                alert('Error al finalizar servicio: ' + data.mensaje);
                            } else {
                                location.reload();
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error al finalizar servicio');
                        });
                }
            });
        });

        // Marcar servicio en camino
        document.querySelectorAll('.enCaminoServicio').forEach(button => {
            button.addEventListener('click', function() {
                const servicioId = this.getAttribute('data-id');

                const formData = new FormData();
                formData.append('servicio_id', servicioId);
                formData.append('accion', 'en_camino');

                fetch('../Processings/procesar_servicio.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            alert('Error al actualizar estado: ' + data.mensaje);
                        } else {
                            location.reload();
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error al actualizar estado');
                    });
            });
        });

        // Cancelar servicio
        document.querySelectorAll('.cancelarServicio').forEach(button => {
            button.addEventListener('click', function() {
                if (confirm('¿Está seguro de cancelar este servicio?')) {
                    const servicioId = this.getAttribute('data-id');

                    const formData = new FormData();
                    formData.append('servicio_id', servicioId);
                    formData.append('accion', 'cancelar');

                    fetch('../Processings/procesar_servicio.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.error) {
                                alert('Error al cancelar servicio: ' + data.mensaje);
                            } else {
                                location.reload();
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error al cancelar servicio');
                        });
                }
            });
        });

        // Actualizar tiempos cada 30 segundos
        setInterval(actualizarTiempos, 30000);

        // Funciones auxiliares
        function mostrarInfoCliente(cliente) {
            const clienteInfo = document.getElementById('clienteInfo');
            clienteInfo.innerHTML = `
            <div class="alert alert-info">
                <h6 class="mb-1"><i class="bi bi-person"></i> <strong>${cliente.nombre || 'Cliente'}</strong></h6>
                <p class="mb-0"><i class="bi bi-telephone"></i> ${cliente.telefono}</p>
            </div>
        `;
            clienteInfo.style.display = 'block';
        }

        function mostrarFormularioNuevoCliente(telefono) {
            const clienteInfo = document.getElementById('clienteInfo');
            clienteInfo.innerHTML = `
            <div class="alert alert-warning">
                <h6 class="mb-2">Cliente nuevo</h6>
                <div class="mb-2">
                    <label for="nombreCliente" class="form-label">Nombre:</label>
                    <input type="text" id="nombreCliente" class="form-control" placeholder="Nombre del cliente">
                </div>
                <button id="guardarCliente" class="btn btn-primary">Guardar Cliente</button>
            </div>
        `;
            clienteInfo.style.display = 'block';

            document.getElementById('guardarCliente').addEventListener('click', function() {
                const nombre = document.getElementById('nombreCliente').value.trim();

                const formData = new FormData();
                formData.append('telefono', telefono);
                formData.append('nombre', nombre);

                fetch('../Processings/guardar_cliente.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            alert('Error al guardar cliente: ' + data.mensaje);
                        } else {
                            clienteActual = data;
                            mostrarInfoCliente(data);
                            document.getElementById('direccionesContainer').style.display = 'block';
                            document.getElementById('nuevaDireccionForm').style.display = 'block';
                            document.getElementById('mostrarNuevaDireccion').style.display = 'none';
                            document.getElementById('nuevaDireccion').focus();
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error al guardar cliente');
                    });
            });
        }

        function cargarDirecciones(clienteId) {
            fetch('../Processings/obtener_direcciones.php?cliente_id=' + clienteId)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Error al cargar direcciones: ' + data.mensaje);
                    } else {
                        const listaDirecciones = document.getElementById('listaDirecciones');
                        listaDirecciones.innerHTML = '';

                        if (data.direcciones.length > 0) {
                            data.direcciones.forEach(dir => {
                                const item = document.createElement('button');
                                item.type = 'button';
                                item.classList.add('list-group-item', 'list-group-item-action');
                                item.innerHTML = `<i class="bi bi-geo-alt me-2"></i> ${dir.direccion}`;
                                item.addEventListener('click', function() {
                                    // Desmarcar selección anterior
                                    listaDirecciones.querySelectorAll('button').forEach(btn => {
                                        btn.classList.remove('active');
                                    });

                                    // Marcar selección actual
                                    this.classList.add('active');
                                    direccionSeleccionada = dir.id;

                                    // Mostrar formulario de detalles de servicio
                                    document.getElementById('detallesServicioForm').style.display = 'block';
                                });

                                listaDirecciones.appendChild(item);
                            });
                        }

                        document.getElementById('direccionesContainer').style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar direcciones');
                });
        }

        function actualizarTiempos() {
            document.querySelectorAll('.tiempoTranscurrido').forEach(elem => {
                const inicio = elem.getAttribute('data-inicio');
                const ahora = new Date();
                const fechaInicio = new Date(inicio);

                const diff = Math.floor((ahora - fechaInicio) / 1000 / 60);

                if (diff < 60) {
                    elem.textContent = diff + ' min';
                } else {
                    const horas = Math.floor(diff / 60);
                    const minutos = diff % 60;
                    elem.textContent = horas + 'h ' + minutos + 'm';
                }
            });
        }
    });
</script>

<?php
// Incluir el pie de página
require_once 'Layouts/footer.php';
?>