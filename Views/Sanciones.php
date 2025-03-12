<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Views\sanciones.php
// Iniciar sesión si no está iniciada
session_start();
// Incluir el encabezado
require_once 'Layouts/header.php';

// Inicializar conexión a la base de datos
require_once '../config/database.php';
$db = new Database();
$pdo = $db->getPdo();

// Incluir los controladores necesarios
require_once '../Controllers/SancionController.php';
require_once '../Controllers/VehiculoController.php';

// Inicializar controladores
$sancionController = new SancionController($pdo);
$vehiculoController = new VehiculoController($pdo);

// Verificar y actualizar sanciones vencidas
$sancionController->verificarVencimientos();

// Obtener sanciones activas
$filtros = ['estado' => 'activa'];
$sanciones = $sancionController->listar($filtros);

// Mensaje de operaciones
$mensaje = isset($_SESSION['mensaje']) ? $_SESSION['mensaje'] : null;
$tipo_mensaje = isset($_SESSION['tipo_mensaje']) ? $_SESSION['tipo_mensaje'] : null;

// Limpiar mensajes de sesión después de mostrarlos
unset($_SESSION['mensaje']);
unset($_SESSION['tipo_mensaje']);
?>

<div class="welcome-message">
    <h2>Vehículos Sancionados</h2>
    <p>Listado de vehículos actualmente sancionados y tiempo restante de sanción.</p>
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
            <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> Vehículos en sanción
        </h5>
        <div>
            <a href="vehiculo.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver a vehículos
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="tablaSanciones" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Vehículo</th>
                        <th>Artículo</th>
                        <th>Motivo</th>
                        <th>Fecha inicio</th>
                        <th>Fecha fin</th>
                        <th>Tiempo restante</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (is_array($sanciones) && !isset($sanciones['error']) && !empty($sanciones)): ?>
                        <?php foreach ($sanciones as $sancion): ?>
                            <tr data-sancion-id="<?php echo $sancion['id']; ?>" data-segundos-restantes="<?php echo isset($sancion['segundos_restantes']) ? $sancion['segundos_restantes'] : 0; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($sancion['vehiculo_placa']); ?></strong>
                                    <div class="text-muted small">Móvil: <?php echo htmlspecialchars($sancion['vehiculo_movil']); ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($sancion['articulo_codigo']); ?></span>
                                    <div class="small"><?php echo htmlspecialchars($sancion['articulo_descripcion']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($sancion['motivo']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($sancion['fecha_inicio'])); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($sancion['fecha_fin'])); ?></td>
                                <td class="tiempo-restante">
                                    <?php if (isset($sancion['tiempo_restante'])): ?>
                                        <?php if ($sancion['tiempo_restante'] !== 'Vencida'): ?>
                                            <span class="badge bg-danger countdown"><?php echo $sancion['tiempo_restante']; ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Sanción vencida</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No disponible</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-info btn-sm"
                                            onclick="verDetalleSancion(<?php echo $sancion['id']; ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-success btn-sm"
                                            onclick="anularSancion(<?php echo $sancion['id']; ?>, '<?php echo htmlspecialchars($sancion['vehiculo_placa']); ?>')">
                                            <i class="bi bi-check-circle"></i> Anular
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No hay vehículos sancionados actualmente</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Anular Sanción -->
<div class="modal fade" id="anularSancionModal" tabindex="-1" aria-labelledby="anularSancionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formAnularSancion" action="../Processings/procesar_sancion.php" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="anularSancionModalLabel">
                        <i class="bi bi-check-circle me-2"></i> Anular Sanción
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="anular_sancion_id" name="id">
                    <p>¿Está seguro que desea anular la sanción del vehículo <span id="anular_vehiculo_placa" class="fw-bold"></span>?</p>
                    <div class="mb-3">
                        <label for="comentario" class="form-label">Motivo de anulación</label>
                        <textarea class="form-control" id="comentario" name="comentario" rows="3" required></textarea>
                        <div class="form-text">Por favor ingrese el motivo por el cual está anulando esta sanción.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="accion" value="anular" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i> Anular Sanción
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detalle Sanción -->
<div class="modal fade" id="detalleSancionModal" tabindex="-1" aria-labelledby="detalleSancionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detalleSancionModalLabel">
                    <i class="bi bi-info-circle me-2"></i> Detalle de Sanción
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detalleSancionContent">
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

<!-- Script para la cuenta regresiva y funciones -->
<script>
    /**
     * Script para la gestión de sanciones
     */
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar DataTable si está disponible
        if (typeof $.fn.DataTable !== 'undefined') {
            $('#tablaSanciones').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json'
                },
                responsive: true
            });
        }

        // Iniciar cuenta regresiva para cada sanción
        iniciarCuentaRegresiva();
    });

    /**
     * Inicializar las cuentas regresivas
     */
    function iniciarCuentaRegresiva() {
        const elementosSancion = document.querySelectorAll('tr[data-sancion-id]');

        elementosSancion.forEach(elemento => {
            const segundosRestantes = parseInt(elemento.getAttribute('data-segundos-restantes'), 10);
            const elementoCountdown = elemento.querySelector('.countdown');

            if (elementoCountdown && segundosRestantes > 0) {
                // Iniciar cuenta regresiva
                let tiempoRestante = segundosRestantes;

                const interval = setInterval(() => {
                    tiempoRestante--;

                    if (tiempoRestante <= 0) {
                        clearInterval(interval);
                        elementoCountdown.textContent = 'Sanción vencida';
                        elementoCountdown.classList.remove('bg-danger');
                        elementoCountdown.classList.add('bg-secondary');

                        // Opcionalmente recargar la página para actualizar el estado
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    } else {
                        elementoCountdown.textContent = formatearTiempoRestante(tiempoRestante);
                    }
                }, 1000);
            }
        });
    }

    /**
     * Formatear el tiempo restante en formato legible
     */
    function formatearTiempoRestante(segundos) {
        const dias = Math.floor(segundos / (24 * 3600));
        segundos = segundos % (24 * 3600);
        const horas = Math.floor(segundos / 3600);
        segundos %= 3600;
        const minutos = Math.floor(segundos / 60);
        segundos %= 60;

        let formato = '';

        if (dias > 0) {
            formato += dias + 'd ';
        }

        return formato +
            String(horas).padStart(2, '0') + ':' +
            String(minutos).padStart(2, '0') + ':' +
            String(segundos).padStart(2, '0');
    }

    /**
     * Ver detalles completos de una sanción
     */
    function verDetalleSancion(id) {
        const detalleModal = new bootstrap.Modal(document.getElementById('detalleSancionModal'));
        const detalleContent = document.getElementById('detalleSancionContent');

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
        fetch(`../Processings/obtener_detalle_sancion.php?id=${id}`)
            .then(response => response.text())
            .then(data => {
                detalleContent.innerHTML = data;
            })
            .catch(error => {
                console.error('Error:', error);
                detalleContent.innerHTML = '<div class="alert alert-danger">Error al cargar los detalles de la sanción</div>';
            });
    }

    /**
     * Anular una sanción
     */
    function anularSancion(id, placa) {
        document.getElementById('anular_sancion_id').value = id;
        document.getElementById('anular_vehiculo_placa').textContent = placa;

        new bootstrap.Modal(document.getElementById('anularSancionModal')).show();
    }
</script>

<?php
// Incluir el pie de página
require_once 'Layouts/footer.php';
?>