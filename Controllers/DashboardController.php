<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Controllers\DashboardController.php

require_once __DIR__ . '/../Models/ServicioModel.php';
require_once __DIR__ . '/../Models/VehiculoModel.php';
require_once __DIR__ . '/../Models/Usuario.php';
require_once __DIR__ . '/../Models/SancionModel.php';

class DashboardController {
    private $pdo;
    private $servicioModel;
    private $vehiculoModel;
    private $usuarioModel;
    private $sancionModel;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->servicioModel = new Servicio($pdo);
        $this->vehiculoModel = new Vehiculo($pdo);
        $this->usuarioModel = new Usuario($pdo);
        $this->sancionModel = new Sancion($pdo);
    }

    /**
     * Obtener todas las estadísticas necesarias para el dashboard
     * @param string $periodo 'hoy', 'semana', 'mes'
     * @return array Estadísticas del sistema
     */
    public function obtenerEstadisticas($periodo = 'hoy') {
        // Determinar las fechas según el período
        $fecha_inicio = $this->obtenerFechaInicio($periodo);
        $fecha_fin = date('Y-m-d H:i:s');

        // Obtener estadísticas básicas de servicios
        $stats_servicios = $this->obtenerEstadisticasServicios($fecha_inicio, $fecha_fin);
        
        // Obtener estadísticas de vehículos
        $stats_vehiculos = $this->obtenerEstadisticasVehiculos();
        
        // Obtener estadísticas de operadores
        $top_operadores = $this->obtenerTopOperadores($fecha_inicio, $fecha_fin);
        
        // Obtener actividad reciente
        $actividad_reciente = $this->obtenerActividadReciente();
        
        // Obtener alertas del sistema
        $alertas = $this->obtenerAlertasSistema();
        
        // Obtener distribución de servicios por zona
        $servicios_zonas = $this->obtenerServiciosPorZona($fecha_inicio, $fecha_fin);
        
        // Obtener datos para el gráfico de servicios por hora
        $servicios_hora = $this->obtenerServiciosPorHora($fecha_inicio, $fecha_fin);
        
        // Combinar todas las estadísticas
        return array_merge(
            $stats_servicios,
            $stats_vehiculos,
            [
                'top_operadores' => $top_operadores,
                'actividad_reciente' => $actividad_reciente,
                'alertas' => $alertas,
                'servicios_zonas' => $servicios_zonas,
                'horas' => $servicios_hora['horas'],
                'servicios_por_hora' => $servicios_hora['servicios']
            ]
        );
    }

    /**
     * Determinar fecha de inicio según período
     */
    private function obtenerFechaInicio($periodo) {
        switch ($periodo) {
            case 'semana':
                return date('Y-m-d H:i:s', strtotime('-7 days'));
            case 'mes':
                return date('Y-m-d H:i:s', strtotime('-30 days'));
            default: // hoy
                return date('Y-m-d 00:00:00'); // Desde el comienzo del día actual
        }
    }

    /**
     * Obtener estadísticas de servicios
     */
    private function obtenerEstadisticasServicios($fecha_inicio, $fecha_fin) {
        try {
            // Total de servicios en el período
            $sql_total = "SELECT COUNT(*) as total FROM servicios 
                          WHERE fecha_solicitud BETWEEN ? AND ?";
            $stmt_total = $this->pdo->prepare($sql_total);
            $stmt_total->execute([$fecha_inicio, $fecha_fin]);
            $total_servicios = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Servicios por estado
            $sql_estados = "SELECT estado, COUNT(*) as cantidad FROM servicios 
                           WHERE fecha_solicitud BETWEEN ? AND ? 
                           GROUP BY estado";
            $stmt_estados = $this->pdo->prepare($sql_estados);
            $stmt_estados->execute([$fecha_inicio, $fecha_fin]);
            $estados = $stmt_estados->fetchAll(PDO::FETCH_ASSOC);
            
            // Inicializar contadores
            $finalizados = 0;
            $cancelados = 0;
            $pendientes = 0;
            
            // Contar servicios por estado
            foreach ($estados as $estado) {
                if ($estado['estado'] === 'finalizado') {
                    $finalizados = $estado['cantidad'];
                } else if ($estado['estado'] === 'cancelado') {
                    $cancelados = $estado['cantidad'];
                } else if ($estado['estado'] === 'pendiente' || $estado['estado'] === 'solicitado') {
                    $pendientes += $estado['cantidad'];
                }
            }
            
            // Calcular efectividad (porcentaje de servicios finalizados con éxito)
            $efectividad = $total_servicios > 0 ? 
                round(($finalizados / $total_servicios) * 100, 1) : 0;
            
            // Calcular tiempo promedio de asignación
            $sql_tiempo = "SELECT AVG(TIMESTAMPDIFF(SECOND, fecha_solicitud, fecha_asignacion)) as promedio 
                          FROM servicios 
                          WHERE fecha_solicitud BETWEEN ? AND ? 
                          AND fecha_asignacion IS NOT NULL";
            $stmt_tiempo = $this->pdo->prepare($sql_tiempo);
            $stmt_tiempo->execute([$fecha_inicio, $fecha_fin]);
            $tiempo_promedio_segundos = $stmt_tiempo->fetch(PDO::FETCH_ASSOC)['promedio'] ?? 0;
            $tiempo_promedio_minutos = round($tiempo_promedio_segundos / 60, 1);
            
            return [
                'servicios_total' => $total_servicios,
                'servicios_finalizados' => $finalizados,
                'servicios_cancelados' => $cancelados,
                'servicios_pendientes' => $pendientes,
                'efectividad' => $efectividad,
                'tiempo_promedio_segundos' => $tiempo_promedio_segundos,
                'tiempo_promedio_minutos' => $tiempo_promedio_minutos
            ];
            
        } catch (PDOException $e) {
            error_log("Error al obtener estadísticas de servicios: " . $e->getMessage());
            return [
                'servicios_total' => 0,
                'servicios_finalizados' => 0,
                'servicios_cancelados' => 0,
                'servicios_pendientes' => 0,
                'efectividad' => 0,
                'tiempo_promedio_segundos' => 0,
                'tiempo_promedio_minutos' => 0
            ];
        }
    }

    /**
     * Obtener estadísticas de vehículos
     */
    private function obtenerEstadisticasVehiculos() {
        try {
            // Total de vehículos
            $sql_total = "SELECT COUNT(*) as total FROM vehiculos";
            $stmt_total = $this->pdo->prepare($sql_total);
            $stmt_total->execute();
            $total = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Vehículos por estado
            $sql_estados = "SELECT estado, COUNT(*) as cantidad FROM vehiculos GROUP BY estado";
            $stmt_estados = $this->pdo->prepare($sql_estados);
            $stmt_estados->execute();
            $estados = $stmt_estados->fetchAll(PDO::FETCH_ASSOC);
            
            // Inicializar contadores
            $activos = 0;
            $disponibles = 0;
            $ocupados = 0;
            $mantenimiento = 0;
            $inactivos = 0;
            
            // Contar vehículos por estado
            foreach ($estados as $estado) {
                if ($estado['estado'] === 'activo') {
                    $activos += $estado['cantidad'];
                    
                    // Los vehículos activos pueden estar disponibles u ocupados
                    // Consultar vehículos activos que están en servicio
                    $sql_ocupados = "SELECT COUNT(*) as ocupados FROM vehiculos v
                                     INNER JOIN servicios s ON v.id = s.vehiculo_id
                                     WHERE v.estado = 'activo' 
                                     AND s.estado IN ('asignado', 'en_curso')";
                    $stmt_ocupados = $this->pdo->prepare($sql_ocupados);
                    $stmt_ocupados->execute();
                    $ocupados = $stmt_ocupados->fetch(PDO::FETCH_ASSOC)['ocupados'] ?? 0;
                    
                    // Los disponibles son los activos menos los ocupados
                    $disponibles = $activos - $ocupados;
                    
                } else if ($estado['estado'] === 'mantenimiento') {
                    $mantenimiento = $estado['cantidad'];
                } else if ($estado['estado'] === 'inactivo') {
                    $inactivos = $estado['cantidad'];
                }
            }
            
            // Calcular disponibilidad (porcentaje de vehículos disponibles)
            $disponibilidad = $total > 0 ? 
                round(($disponibles / $total) * 100, 1) : 0;
            
            return [
                'vehiculos_total' => $total,
                'vehiculos_activos' => $activos,
                'vehiculos_disponibles' => $disponibles,
                'vehiculos_ocupados' => $ocupados,
                'vehiculos_mantenimiento' => $mantenimiento,
                'vehiculos_inactivos' => $inactivos,
                'disponibilidad_vehiculos' => $disponibilidad
            ];
            
        } catch (PDOException $e) {
            error_log("Error al obtener estadísticas de vehículos: " . $e->getMessage());
            return [
                'vehiculos_total' => 0,
                'vehiculos_activos' => 0,
                'vehiculos_disponibles' => 0,
                'vehiculos_ocupados' => 0,
                'vehiculos_mantenimiento' => 0,
                'vehiculos_inactivos' => 0,
                'disponibilidad_vehiculos' => 0
            ];
        }
    }

    /**
     * Obtener top operadores según rendimiento
     */
    private function obtenerTopOperadores($fecha_inicio, $fecha_fin) {
        try {
            $sql = "SELECT 
                    u.id, 
                    u.nombre,
                    COUNT(s.id) as total_servicios,
                    SUM(CASE WHEN s.estado = 'finalizado' THEN 1 ELSE 0 END) as finalizados,
                    SUM(CASE WHEN s.estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
                    CASE 
                        WHEN COUNT(s.id) > 0 
                        THEN ROUND(SUM(CASE WHEN s.estado = 'finalizado' THEN 1 ELSE 0 END) * 100 / COUNT(s.id), 1)
                        ELSE 0 
                    END as efectividad,
                    AVG(TIMESTAMPDIFF(SECOND, s.fecha_solicitud, s.fecha_asignacion)) / 60 as tiempo_promedio
                FROM 
                    usuarios u
                LEFT JOIN 
                    servicios s ON u.id = s.operador_id AND s.fecha_solicitud BETWEEN ? AND ?
                WHERE 
                    u.rol IN ('operador', 'admin')
                GROUP BY 
                    u.id, u.nombre
                ORDER BY 
                    total_servicios DESC
                LIMIT 5";
                
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$fecha_inicio, $fecha_fin]);
            $operadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Formatear los datos
            foreach ($operadores as &$op) {
                $op['tiempo_promedio'] = round($op['tiempo_promedio'], 1);
            }
            
            return $operadores;
            
        } catch (PDOException $e) {
            error_log("Error al obtener top operadores: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener actividad reciente del sistema
     */
    private function obtenerActividadReciente() {
        try {
            // Aquí normalmente consultaríamos una tabla de registros de actividad
            // Como ejemplo, simularemos algunos datos con eventos recientes de servicios y vehículos
            
            // Últimos servicios creados o actualizados
            $sql_servicios = "SELECT 
                             s.id, s.estado, s.direccion_origen, s.fecha_solicitud, s.fecha_actualizacion,
                             c.nombre as cliente_nombre, u.nombre as operador_nombre
                             FROM servicios s
                             LEFT JOIN clientes c ON s.cliente_id = c.id
                             LEFT JOIN usuarios u ON s.operador_id = u.id
                             ORDER BY s.fecha_actualizacion DESC
                             LIMIT 5";
            $stmt_servicios = $this->pdo->prepare($sql_servicios);
            $stmt_servicios->execute();
            $servicios = $stmt_servicios->fetchAll(PDO::FETCH_ASSOC);
            
            // Últimos cambios en vehículos
            $sql_vehiculos = "SELECT 
                              v.id, v.placa, v.estado, v.fecha_actualizacion
                              FROM vehiculos v
                              ORDER BY v.fecha_actualizacion DESC
                              LIMIT 3";
            $stmt_vehiculos = $this->pdo->prepare($sql_vehiculos);
            $stmt_vehiculos->execute();
            $vehiculos = $stmt_vehiculos->fetchAll(PDO::FETCH_ASSOC);
            
            // Combinar y formatear actividades
            $actividades = [];
            
            // Formatear servicios
            foreach ($servicios as $servicio) {
                $tiempo = $this->formatearTiempoTranscurrido($servicio['fecha_actualizacion']);
                $titulo = '';
                $descripcion = '';
                $icono = '';
                
                switch ($servicio['estado']) {
                    case 'solicitado':
                        $titulo = 'Nuevo servicio solicitado';
                        $descripcion = 'Cliente: ' . ($servicio['cliente_nombre'] ?? 'Anónimo') . ' - Origen: ' . substr($servicio['direccion_origen'], 0, 30) . '...';
                        $icono = 'bi-plus-circle-fill text-primary';
                        break;
                    case 'asignado':
                        $titulo = 'Servicio asignado';
                        $descripcion = 'Operador ' . ($servicio['operador_nombre'] ?? 'N/A') . ' asignó servicio #' . $servicio['id'];
                        $icono = 'bi-check-circle-fill text-success';
                        break;
                    case 'en_curso':
                        $titulo = 'Servicio iniciado';
                        $descripcion = 'El servicio #' . $servicio['id'] . ' está en curso';
                        $icono = 'bi-arrow-right-circle-fill text-info';
                        break;
                    case 'finalizado':
                        $titulo = 'Servicio completado';
                        $descripcion = 'El servicio #' . $servicio['id'] . ' ha sido finalizado exitosamente';
                        $icono = 'bi-check-all text-success';
                        break;
                    case 'cancelado':
                        $titulo = 'Servicio cancelado';
                        $descripcion = 'El servicio #' . $servicio['id'] . ' fue cancelado';
                        $icono = 'bi-x-circle-fill text-danger';
                        break;
                }
                
                $actividades[] = [
                    'titulo' => $titulo,
                    'descripcion' => $descripcion,
                    'tiempo' => $tiempo,
                    'icono' => $icono
                ];
            }
            
            // Formatear vehículos
            foreach ($vehiculos as $vehiculo) {
                $tiempo = $this->formatearTiempoTranscurrido($vehiculo['fecha_actualizacion']);
                $titulo = 'Cambio estado vehículo';
                $icono = 'bi-truck text-secondary';
                
                switch ($vehiculo['estado']) {
                    case 'activo':
                        $descripcion = 'El vehículo ' . $vehiculo['placa'] . ' está ahora activo y disponible';
                        break;
                    case 'mantenimiento':
                        $descripcion = 'El vehículo ' . $vehiculo['placa'] . ' entró a mantenimiento';
                        $icono = 'bi-tools text-warning';
                        break;
                    case 'inactivo':
                        $descripcion = 'El vehículo ' . $vehiculo['placa'] . ' fue marcado como inactivo';
                        $icono = 'bi-slash-circle text-danger';
                        break;
                }
                
                $actividades[] = [
                    'titulo' => $titulo,
                    'descripcion' => $descripcion,
                    'tiempo' => $tiempo,
                    'icono' => $icono
                ];
            }
            
            // Ordenar por tiempo más reciente
            usort($actividades, function($a, $b) {
                return strtotime($b['tiempo']) - strtotime($a['tiempo']);
            });
            
            // Limitar a las 5 actividades más recientes
            return array_slice($actividades, 0, 5);
            
        } catch (PDOException $e) {
            error_log("Error al obtener actividad reciente: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener alertas activas del sistema
     */
    private function obtenerAlertasSistema() {
        try {
            $alertas = [];
            
            // Alerta 1: Servicios pendientes sin asignar por más de 15 minutos
            $sql_pendientes = "SELECT COUNT(*) as cantidad
                               FROM servicios 
                               WHERE estado = 'solicitado' 
                               AND fecha_solicitud < DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
            $stmt_pendientes = $this->pdo->prepare($sql_pendientes);
            $stmt_pendientes->execute();
            $pendientes = $stmt_pendientes->fetch(PDO::FETCH_ASSOC)['cantidad'] ?? 0;
            
            if ($pendientes > 0) {
                $alertas[] = [
                    'titulo' => 'Servicios sin asignar',
                    'descripcion' => "Hay $pendientes servicios pendientes por más de 15 minutos sin ser asignados.",
                    'icono' => 'bi-exclamation-circle-fill',
                    'tiempo' => 'Ahora',
                    'accion' => 'Ver servicios',
                    'accion_url' => 'Servicios.php?filtro=pendientes'
                ];
            }
            
            // Alerta 2: Vehículos en mantenimiento programado para hoy
            $sql_mantenimiento = "SELECT COUNT(*) as cantidad
                                  FROM mantenimientos 
                                  WHERE fecha = CURDATE() 
                                  AND estado = 'programado'";
            $stmt_mantenimiento = $this->pdo->prepare($sql_mantenimiento);
            $stmt_mantenimiento->execute();
            $mantenimientos = $stmt_mantenimiento->fetch(PDO::FETCH_ASSOC)['cantidad'] ?? 0;
            
            if ($mantenimientos > 0) {
                $alertas[] = [
                    'titulo' => 'Mantenimientos programados',
                    'descripcion' => "Hay $mantenimientos vehículos con mantenimiento programado para hoy.",
                    'icono' => 'bi-tools',
                    'tiempo' => 'Hoy',
                    'accion' => 'Ver mantenimientos',
                    'accion_url' => 'Mantenimientos.php'
                ];
            }
            
            // Alerta 3: Sanciones sin resolver
            $sql_sanciones = "SELECT COUNT(*) as cantidad
                             FROM sanciones
                             WHERE estado = 'pendiente'";
            $stmt_sanciones = $this->pdo->prepare($sql_sanciones);
            $stmt_sanciones->execute();
            $sanciones = $stmt_sanciones->fetch(PDO::FETCH_ASSOC)['cantidad'] ?? 0;
            
            if ($sanciones > 0) {
                $alertas[] = [
                    'titulo' => 'Sanciones sin resolver',
                    'descripcion' => "Hay $sanciones sanciones pendientes que requieren atención.",
                    'icono' => 'bi-clipboard-x',
                    'tiempo' => 'Pendiente',
                    'accion' => 'Ver sanciones',
                    'accion_url' => 'Sanciones.php?filtro=pendientes'
                ];
            }
            
            // Alerta 4: Vehículos con baja disponibilidad
            $sql_baja_disp = "SELECT COUNT(*) as cantidad 
                              FROM vehiculos
                              WHERE estado = 'activo'
                              AND disponibilidad < 50";
            $stmt_baja_disp = $this->pdo->prepare($sql_baja_disp);
            $stmt_baja_disp->execute();
            $baja_disp = $stmt_baja_disp->fetch(PDO::FETCH_ASSOC)['cantidad'] ?? 0;
            
            if ($baja_disp > 0) {
                $alertas[] = [
                    'titulo' => 'Baja disponibilidad de vehículos',
                    'descripcion' => "$baja_disp vehículos tienen menos del 50% de disponibilidad.",
                    'icono' => 'bi-battery-half',
                    'tiempo' => 'Crítico',
                    'accion' => 'Ver detalles',
                    'accion_url' => 'Vehiculos.php?filtro=baja_disponibilidad'
                ];
            }
            
            return $alertas;
            
        } catch (PDOException $e) {
            error_log("Error al obtener alertas del sistema: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener distribución de servicios por zona
     */
    private function obtenerServiciosPorZona($fecha_inicio, $fecha_fin) {
        try {
            // En un sistema real, esto consultaría una tabla con zonas geográficas
            // Como ejemplo, usaremos una consulta que clasifica por algún campo como "zona"
            // o por las primeras letras de la dirección
            
            // Simulación con distribución aleatoria
            return [
                'norte' => rand(10, 50),
                'centro' => rand(20, 70),
                'sur' => rand(15, 60),
                'este' => rand(5, 30),
                'oeste' => rand(10, 40)
            ];
            
        } catch (PDOException $e) {
            error_log("Error al obtener servicios por zona: " . $e->getMessage());
            return [
                'norte' => 0,
                'centro' => 0,
                'sur' => 0,
                'este' => 0,
                'oeste' => 0
            ];
        }
    }

    /**
     * Obtener servicios por hora para el gráfico
     */
    private function obtenerServiciosPorHora($fecha_inicio, $fecha_fin) {
        try {
            // Inicializar array de horas y servicios
            $horas = [];
            $servicios = [];
            
            // Determinar el rango de horas según el período
            $periodo_tipo = 'hoy';
            if (date('Y-m-d', strtotime($fecha_inicio)) != date('Y-m-d')) {
                $periodo_tipo = 'historico';
            }
            
            if ($periodo_tipo == 'hoy') {
                // Si es hoy, mostrar por hora del día
                for ($i = 0; $i < 24; $i++) {
                    $hora_formateada = sprintf("%02d:00", $i);
                    $horas[] = $hora_formateada;
                    
                    $sql = "SELECT COUNT(*) as cantidad FROM servicios
                            WHERE fecha_solicitud BETWEEN ? AND ?
                            AND HOUR(fecha_solicitud) = ?";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([
                        date('Y-m-d 00:00:00'),
                        date('Y-m-d 23:59:59'),
                        $i
                    ]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $servicios[] = $result['cantidad'] ?? 0;
                }
            } else {
                // Si es histórico, agrupar por día
                $fecha_actual = new DateTime($fecha_inicio);
                $fecha_fin_dt = new DateTime($fecha_fin);
                
                while ($fecha_actual <= $fecha_fin_dt) {
                    $fecha_str = $fecha_actual->format('Y-m-d');
                    $horas[] = $fecha_actual->format('d/m');
                    
                    $sql = "SELECT COUNT(*) as cantidad FROM servicios
                            WHERE fecha_solicitud BETWEEN ? AND ?";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([
                        $fecha_str . ' 00:00:00',
                        $fecha_str . ' 23:59:59'
                    ]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $servicios[] = $result['cantidad'] ?? 0;
                    
                    $fecha_actual->modify('+1 day');
                }
            }
            
            return [
                'horas' => $horas,
                'servicios' => $servicios
            ];
            
        } catch (PDOException $e) {
            error_log("Error al obtener servicios por hora: " . $e->getMessage());
            return [
                'horas' => ['00:00', '06:00', '12:00', '18:00'],
                'servicios' => [0, 0, 0, 0]
            ];
        }
    }

    /**
     * Formatear tiempo transcurrido para la actividad reciente
     */
    private function formatearTiempoTranscurrido($fecha) {
        $ahora = new DateTime();
        $tiempo = new DateTime($fecha);
        $intervalo = $ahora->diff($tiempo);
        
        if ($intervalo->y > 0) {
            return "hace " . $intervalo->y . " año" . ($intervalo->y > 1 ? "s" : "");
        }
        if ($intervalo->m > 0) {
            return "hace " . $intervalo->m . " mes" . ($intervalo->m > 1 ? "es" : "");
        }
        if ($intervalo->d > 0) {
            return "hace " . $intervalo->d . " día" . ($intervalo->d > 1 ? "s" : "");
        }
        if ($intervalo->h > 0) {
            return "hace " . $intervalo->h . " hora" . ($intervalo->h > 1 ? "s" : "");
        }
        if ($intervalo->i > 0) {
            return "hace " . $intervalo->i . " minuto" . ($intervalo->i > 1 ? "s" : "");
        }
        
        return "hace unos segundos";
    }
}
?>