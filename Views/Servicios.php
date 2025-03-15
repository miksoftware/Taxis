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

// Obtener todos los servicios activos (no finalizados ni cancelados)
$servicios_activos = $servicioController->listarServiciosActivos();

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
                        <option value="ninguno" selected>Ninguno</option>
                        <option value="aire">Aire</option>
                        <option value="baul">Baúl</option>
                        <option value="mascota">Mascota</option>
                        <option value="parrilla">Parrilla</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="daviplata">Daviplata</option>
                        <option value="polarizados">Polarizados</option>
                        <option value="silla_ruedas">Silla de Ruedas</option>
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

<!-- Panel de servicios activos -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="bi bi-list-check me-2"></i> Lista de Servicios
        </h5>
        <span class="badge bg-primary" id="contadorServicios"><?= count($servicios_activos) ?></span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="tablaServicios">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Dirección</th>
                        <th>Observaciones</th>
                        <th>Condición</th>
                        <th>Vehículo</th>
                        <th>Estado</th>
                        <th>Tiempo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($servicios_activos)): ?>
                        <?php foreach ($servicios_activos as $servicio): ?>
                            <tr>
                                <td><?= $servicio['telefono'] ?></td>
                                <td><?= $servicio['direccion'] ?></td>
                                <td>
                                    <?= empty($servicio['observaciones']) ? 'sin observaciones' : $servicio['observaciones'] ?>
                                </td>
                                <td>
                                    <?php if ($servicio['condicion'] == 'aire'): ?>
                                        <span class="badge bg-danger">Aire</span>
                                    <?php elseif ($servicio['condicion'] == 'baul'): ?>
                                        <span class="badge bg-primary">Baúl</span>
                                    <?php elseif ($servicio['condicion'] == 'mascota'): ?>
                                        <span class="badge bg-info">Mascota</span>
                                    <?php elseif ($servicio['condicion'] == 'parrilla'): ?>
                                        <span class="badge bg-danger">Parrilla</span>
                                    <?php elseif ($servicio['condicion'] == 'transferencia'): ?>
                                        <span class="badge bg-primary">Transferencia</span>
                                    <?php elseif ($servicio['condicion'] == 'daviplata'): ?>
                                        <span class="badge bg-info">Daviplata</span>
                                    <?php elseif ($servicio['condicion'] == 'polarizados'): ?>
                                        <span class="badge bg-danger">Polarizados</span>
                                    <?php elseif ($servicio['condicion'] == 'silla_ruedas'): ?>
                                        <span class="badge bg-primary">Silla de Ruedas</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    if (!empty($servicio['placa'])) {
                                        echo $servicio['placa'] . ' - ' . $servicio['numero_movil'];
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($servicio['estado'] == 'pendiente'): ?>
                                        <span class="badge bg-warning">Pendiente</span>
                                    <?php elseif ($servicio['estado'] == 'asignado'): ?>
                                        <span class="badge bg-info">Asignado</span>
                                    <?php elseif ($servicio['estado'] == 'en_camino'): ?>
                                        <span class="badge bg-primary">En camino</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($servicio['estado'] == 'pendiente'): ?>
                                        <span class="tiempoTranscurrido" data-inicio="<?= $servicio['fecha_solicitud'] ?>">
                                            <?= $servicioController->calcularTiempoTranscurrido($servicio['fecha_solicitud']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="tiempoTranscurrido" data-inicio="<?= $servicio['fecha_asignacion'] ?>">
                                            <?= $servicioController->calcularTiempoTranscurrido($servicio['fecha_asignacion']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($servicio['estado'] == 'pendiente'): ?>
                                        <button type="button" class="btn btn-primary btn-sm asignarServicio" title="Asignar vehículo"
                                            data-id="<?= $servicio['id'] ?>">
                                            <i class="bi bi-car-front"></i>
                                        </button>
                                    <?php elseif ($servicio['estado'] == 'asignado'): ?>
                                        <button type="button" class="btn btn-info btn-sm cambiarMovil" title="Cambiar movil asignado"
                                            data-id="<?= $servicio['id'] ?>">
                                            <i class="bi bi-signpost-2"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-success btn-sm finalizarServicio" title="Finalizar servicio"
                                        data-id="<?= $servicio['id'] ?>">
                                        <i class="bi bi-check-circle"></i>
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm cancelarServicio" title="Cancelar servicio"
                                        data-id="<?= $servicio['id'] ?>">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">No hay servicios activos</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para cambiar vehículo -->
<div class="modal fade" id="cambiarVehiculoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-arrow-left-right me-2"></i> Cambiar Vehículo Asignado
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="cambiarServicioId">
                <div class="mb-3">
                    <p class="mb-1"><strong>Vehículo actual:</strong> <span id="vehiculoActual"></span></p>
                </div>
                <div class="mb-3">
                    <label for="nuevoVehiculoSelect" class="form-label">Seleccione nuevo vehículo:</label>
                    <select id="nuevoVehiculoSelect" class="form-select">
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
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> El vehículo actual quedará disponible y el nuevo vehículo será asignado a este servicio.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="confirmarCambioVehiculo" class="btn btn-primary">Cambiar Vehículo</button>
            </div>
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

        // Variables para controlar el estado de los modales
        let modalEstados = {
            asignarModal: false,
            cambiarModal: false
        };

        // Variable para guardar el ID del intervalo
        let intervaloActualizacion;

        // Función para actualizar la tabla de servicios
        const actualizarTablaServicios = () => {
            // No actualizar si hay algún modal abierto para evitar interrumpir al usuario
            if (modalEstados.asignarModal || modalEstados.cambiarModal) {
                return;
            }

            // Guardar la posición de scroll actual
            const scrollPos = window.scrollY;

            // Solicitar datos actualizados mediante fetch
            fetch('../Processings/obtener_servicios_activos.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        console.error('Error al obtener datos:', data.mensaje);
                        return;
                    }

                    // Actualizar contador de servicios
                    document.getElementById('contadorServicios').textContent = data.servicios.length;

                    // Obtener la tabla actual
                    const tbody = document.querySelector('#tablaServicios tbody');

                    // Si no hay servicios, mostrar mensaje
                    if (data.servicios.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="8" class="text-center">No hay servicios activos</td></tr>`;
                        return;
                    }

                    // Generar HTML para los nuevos datos
                    let htmlServicios = '';

                    data.servicios.forEach(servicio => {
                        // Calcular el tiempo transcurrido
                        const tiempoInicio = servicio.estado === 'pendiente' ? servicio.fecha_solicitud : servicio.fecha_asignacion;
                        const ahora = new Date();
                        const fechaInicio = new Date(tiempoInicio);
                        const diffMinutos = Math.floor((ahora - fechaInicio) / 1000 / 60);

                        let tiempoFormateado;
                        if (diffMinutos < 60) {
                            tiempoFormateado = diffMinutos + ' min';
                        } else {
                            const horas = Math.floor(diffMinutos / 60);
                            const minutos = diffMinutos % 60;
                            tiempoFormateado = horas + 'h ' + minutos + 'm';
                        }

                        // Generar badge para condición
                        let condicionBadge = '';
                        if (servicio.condicion) {
                            const badgeColor = {
                                'aire': 'danger',
                                'baul': 'primary',
                                'mascota': 'info',
                                'parrilla': 'danger',
                                'transferencia': 'primary',
                                'daviplata': 'info',
                                'polarizados': 'danger',
                                'silla_ruedas': 'primary'
                            } [servicio.condicion] || 'secondary';

                            const condicionText = servicio.condicion.charAt(0).toUpperCase() + servicio.condicion.slice(1).replace('_', ' ');
                            condicionBadge = `<span class="badge bg-${badgeColor}">${condicionText}</span>`;
                        }

                        // Generar badge para estado
                        const estadoBadge = {
                            'pendiente': '<span class="badge bg-warning">Pendiente</span>',
                            'asignado': '<span class="badge bg-info">Asignado</span>',
                            'en_camino': '<span class="badge bg-primary">En camino</span>'
                        } [servicio.estado] || '';

                        // Generar botones de acción según el estado
                        let botonesAccion = '';

                        if (servicio.estado === 'pendiente') {
                            botonesAccion += `<button type="button" class="btn btn-primary btn-sm asignarServicio" title="Asignar vehículo" data-id="${servicio.id}">
                            <i class="bi bi-car-front"></i>
                        </button>`;
                        } else if (servicio.estado === 'asignado') {
                            botonesAccion += `<button type="button" class="btn btn-info btn-sm cambiarMovil" title="Cambiar movil asignado" data-id="${servicio.id}">
                            <i class="bi bi-signpost-2"></i>
                        </button>`;
                        }

                        botonesAccion += `<button type="button" class="btn btn-success btn-sm finalizarServicio" title="Finalizar servicio" data-id="${servicio.id}">
                        <i class="bi bi-check-circle"></i>
                    </button>
                    <button type="button" class="btn btn-danger btn-sm cancelarServicio" title="Cancelar servicio" data-id="${servicio.id}">
                        <i class="bi bi-x-circle"></i>
                    </button>`;

                        // Información del vehículo
                        const infoVehiculo = servicio.placa ?
                            `${servicio.placa} - ${servicio.numero_movil}` : '-';

                        // Construir la fila
                        htmlServicios += `<tr>
                        <td>${servicio.telefono}</td>
                        <td>${servicio.direccion}</td>
                        <td>${servicio.observaciones || 'sin observaciones'}</td>
                        <td>${condicionBadge}</td>
                        <td>${infoVehiculo}</td>
                        <td>${estadoBadge}</td>
                        <td><span class="tiempoTranscurrido" data-inicio="${tiempoInicio}">${tiempoFormateado}</span></td>
                        <td>${botonesAccion}</td>
                    </tr>`;
                    });

                    // Actualizar el contenido de la tabla
                    tbody.innerHTML = htmlServicios;

                    // Restaurar la posición de scroll
                    window.scrollTo(0, scrollPos);

                    // Volver a añadir event listeners a los nuevos botones
                    agregarEventListeners();
                })
                .catch(error => {
                    console.error('Error al actualizar tabla:', error);
                });
        };

        // Función para agregar event listeners a los botones de acción
        function agregarEventListeners() {
            document.querySelectorAll('.asignarServicio').forEach(button => {
                button.addEventListener('click', function() {
                    const servicioId = this.getAttribute('data-id');
                    document.getElementById('servicioId').value = servicioId;
                    new bootstrap.Modal(document.getElementById('asignarVehiculoModal')).show();
                });
            });

            document.querySelectorAll('.cambiarMovil').forEach(button => {
                button.addEventListener('click', function() {
                    const servicioId = this.getAttribute('data-id');
                    const fila = this.closest('tr');
                    const vehiculoActual = fila.querySelector('td:nth-child(5)').textContent.trim();

                    document.getElementById('cambiarServicioId').value = servicioId;
                    document.getElementById('vehiculoActual').textContent = vehiculoActual;

                    new bootstrap.Modal(document.getElementById('cambiarVehiculoModal')).show();
                });
            });

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
                                    // Forzar actualización inmediata
                                    actualizarTablaServicios();
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('Error al finalizar servicio');
                            });
                    }
                });
            });

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
                                    // Forzar actualización inmediata
                                    actualizarTablaServicios();
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('Error al cancelar servicio');
                            });
                    }
                });
            });
        }

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


        // Crear servicio
        document.getElementById('crearServicio').addEventListener('click', function() {
            if (!clienteActual || !direccionSeleccionada) {
                alert('Debe seleccionar un cliente y una dirección');
                return;
            }

            const condicion = document.getElementById('condicionServicio').value;
            const observaciones = document.getElementById('observaciones').value;

            // Mostrar indicador de carga
            const btnCrear = this;
            const textoOriginal = btnCrear.innerHTML;
            btnCrear.disabled = true;
            btnCrear.innerHTML = '<i class="bi bi-hourglass-split"></i> Procesando...';

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
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error de red: ' + response.status);
                    }
                    return response.text().then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Respuesta no válida del servidor:', text);
                            throw new Error('Formato de respuesta inválido');
                        }
                    });
                })
                .then(data => {
                    console.log('Respuesta al crear servicio:', data);

                    // Restaurar botón
                    btnCrear.disabled = false;
                    btnCrear.innerHTML = textoOriginal;

                    // Si hay error pero contiene un ID de servicio, probablemente el servicio se creó
                    if (data.error && data.id) {
                        alert('El servicio se ha creado con ID: ' + data.id + ' pero hubo un problema: ' + data.mensaje);
                        // Limpiar formulario de todas formas
                        limpiarFormularioServicio();
                        // Actualizar tabla
                        actualizarTablaServicios();
                        return;
                    }

                    // Si hay error normal
                    if (data.error) {
                        alert('Error al crear servicio: ' + data.mensaje);
                        return;
                    }

                    // Caso de éxito
                    // Limpiar formulario
                    limpiarFormularioServicio();
                    // Actualizar tabla
                    actualizarTablaServicios();
                })
                .catch(error => {
                    console.error('Error:', error);

                    // Restaurar botón
                    btnCrear.disabled = false;
                    btnCrear.innerHTML = textoOriginal;

                    alert('Error al procesar la solicitud: ' + error.message);
                });
        });

        // Función auxiliar para limpiar el formulario de servicio
        function limpiarFormularioServicio() {
            document.getElementById('telefono').value = '';
            document.getElementById('condicionServicio').value = 'ninguno';
            document.getElementById('observaciones').value = '';
            document.getElementById('clienteInfo').style.display = 'none';
            document.getElementById('direccionesContainer').style.display = 'none';
            document.getElementById('detallesServicioForm').style.display = 'none';

            // Reiniciar variables
            clienteActual = null;
            direccionSeleccionada = null;
        }

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
                        bootstrap.Modal.getInstance(document.getElementById('asignarVehiculoModal')).hide();
                        actualizarTablaServicios();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al asignar vehículo');
                });
        });

        // Confirmar cambio de vehículo
        document.getElementById('confirmarCambioVehiculo').addEventListener('click', function() {
            const servicioId = document.getElementById('cambiarServicioId').value;
            const nuevoVehiculoId = document.getElementById('nuevoVehiculoSelect').value;

            if (!servicioId || !nuevoVehiculoId) {
                alert('Debe seleccionar un vehículo para continuar');
                return;
            }

            // Mostrar indicador de carga
            const btnConfirmar = this;
            const textoOriginal = btnConfirmar.innerHTML;
            btnConfirmar.disabled = true;
            btnConfirmar.innerHTML = 'Procesando...';

            const formData = new FormData();
            formData.append('servicio_id', servicioId);
            formData.append('vehiculo_id', nuevoVehiculoId);
            formData.append('accion', 'cambiar_vehiculo');

            fetch('../Processings/procesar_servicio.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error de red: ' + response.status);
                    }
                    return response.text().then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Respuesta no válida:', text);
                            throw new Error('Formato de respuesta inválido');
                        }
                    });
                })
                .then(data => {
                    console.log('Respuesta:', data);

                    // Restaurar botón
                    btnConfirmar.disabled = false;
                    btnConfirmar.innerHTML = textoOriginal;

                    if (data.error) {
                        alert('Error: ' + data.mensaje);
                    } else {
                        alert('Vehículo cambiado correctamente');
                        bootstrap.Modal.getInstance(document.getElementById('cambiarVehiculoModal')).hide();
                        location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);

                    // Restaurar botón
                    btnConfirmar.disabled = false;
                    btnConfirmar.innerHTML = textoOriginal;

                    alert('Error al procesar la solicitud: ' + error.message);
                });
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

                            // IMPORTANTE: Añade console.log para depuración
                            console.log('Dirección guardada:', data);

                            // Seleccionar automáticamente la nueva dirección
                            direccionSeleccionada = data.direccion_id;

                            const listaDirecciones = document.getElementById('listaDirecciones');
                            const item = document.createElement('button');
                            item.type = 'button';
                            item.classList.add('list-group-item', 'list-group-item-action', 'active'); // Ya seleccionada
                            item.innerHTML = `<i class="bi bi-geo-alt me-2"></i> ${direccion}`;
                            item.addEventListener('click', function() {
                                // Desmarcar selección anterior
                                listaDirecciones.querySelectorAll('button').forEach(btn => {
                                    btn.classList.remove('active');
                                });

                                // Marcar selección actual
                                this.classList.add('active');
                                direccionSeleccionada = data.direccion_id;

                                // Mostrar formulario de detalles de servicio
                                document.getElementById('detallesServicioForm').style.display = 'block';
                            });

                            // Limpiar lista actual y agregar el nuevo elemento
                            listaDirecciones.innerHTML = '';
                            listaDirecciones.appendChild(item);

                            // Mostrar formulario de detalles de servicio inmediatamente
                            document.getElementById('detallesServicioForm').style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error al guardar dirección');
                    });
            }
        });

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

        // Actualizar tiempos cada 30 segundos
        setInterval(actualizarTiempos, 30000);

        // Función para iniciar la actualización automática
        function iniciarActualizacionAutomatica() {
            // Detectar cuando se abren/cierran modales
            document.getElementById('asignarVehiculoModal').addEventListener('shown.bs.modal', () => modalEstados.asignarModal = true);
            document.getElementById('asignarVehiculoModal').addEventListener('hidden.bs.modal', () => modalEstados.asignarModal = false);
            document.getElementById('cambiarVehiculoModal').addEventListener('shown.bs.modal', () => modalEstados.cambiarModal = true);
            document.getElementById('cambiarVehiculoModal').addEventListener('hidden.bs.modal', () => modalEstados.cambiarModal = false);

            // Iniciar actualización periódica (cada 2 segundos)
            intervaloActualizacion = setInterval(actualizarTablaServicios, 2000);

            // Detener actualizaciones cuando el usuario abandona la página
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') {
                    clearInterval(intervaloActualizacion);
                } else {
                    // Reiniciar actualizaciones cuando regresa
                    if (!intervaloActualizacion) {
                        intervaloActualizacion = setInterval(actualizarTablaServicios, 2000);
                    }
                }
            });
        }

        // Añadir listeners iniciales para los botones existentes
        agregarEventListeners();

        // Iniciar actualización automática cuando el DOM está listo
        iniciarActualizacionAutomatica();
    });
</script>

<?php
// Incluir el pie de página
require_once 'Layouts/footer.php';
?>