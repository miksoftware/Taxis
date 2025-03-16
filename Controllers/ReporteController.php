<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Controllers\ReporteController.php

require_once __DIR__ . '/../Models/ReporteModel.php';
require_once __DIR__ . '/../Models/Usuario.php';

class ReporteController
{
    private $reporteModel;
    private $usuarioModel;
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->reporteModel = new Reporte($pdo);
        $this->usuarioModel = new Usuario($pdo);
    }

    /**
     * Genera un reporte de actividad de usuarios
     * @param array $filtros Arreglo con filtros como fecha_inicio, fecha_fin, usuario_id
     * @return array Datos del reporte
     */
    public function generarReporteUsuarios($filtros = [])
    {
        try {
            // Verificar y configurar fechas
            $fecha_inicio = isset($filtros['fecha_inicio']) ? $filtros['fecha_inicio'] : date('Y-m-d', strtotime('-30 days'));
            $fecha_fin = isset($filtros['fecha_fin']) ? $filtros['fecha_fin'] : date('Y-m-d');

            // Construir consulta base
            $sql = "
    SELECT 
        u.id,
        u.nombre,
        u.username,
        COUNT(s.id) as total_servicios,
        SUM(CASE WHEN s.estado = 'finalizado' THEN 1 ELSE 0 END) as finalizados,
        SUM(CASE WHEN s.estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
        CASE 
            WHEN COUNT(s.id) > 0 THEN 
                ROUND(SUM(CASE WHEN s.estado = 'finalizado' THEN 1 ELSE 0 END) * 100.0 / COUNT(s.id), 2)
            ELSE 0 
        END as efectividad
    FROM 
        usuarios u
    LEFT JOIN 
        servicios s ON u.id = s.operador_id AND s.fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin
";

            // Agregar filtros adicionales
            $condiciones = [];
            $params = [
                ':fecha_inicio' => $fecha_inicio . ' 00:00:00',
                ':fecha_fin' => $fecha_fin . ' 23:59:59'
            ];

            if (!empty($filtros['usuario_id'])) {
                $condiciones[] = "u.id = :usuario_id";
                $params[':usuario_id'] = $filtros['usuario_id'];
            }

            if (!empty($filtros['rol'])) {
                $condiciones[] = "u.rol = :rol";
                $params[':rol'] = $filtros['rol'];
            }

            if (!empty($condiciones)) {
                $sql .= " WHERE " . implode(" AND ", $condiciones);
            }

            // Agrupar y ordenar
            $sql .= " GROUP BY u.id, u.nombre, u.username ORDER BY total_servicios DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular tiempo promedio de asignación para cada usuario
            foreach ($datos as &$usuario) {
                $usuario['tiempo_promedio_asignacion'] = $this->calcularTiempoPromedioAsignacion($usuario['id'], $fecha_inicio, $fecha_fin);
            }

            // Calcular resumen general
            $resumen = [
                'total_servicios' => array_sum(array_column($datos, 'total_servicios')),
                'finalizados' => array_sum(array_column($datos, 'finalizados')),
                'cancelados' => array_sum(array_column($datos, 'cancelados')),
                'promedio_efectividad' => 0
            ];

            if ($resumen['total_servicios'] > 0) {
                $resumen['promedio_efectividad'] = round(($resumen['finalizados'] * 100.0) / $resumen['total_servicios'], 2);
            }

            // Preparar datos para gráfico
            $grafico = $this->prepararDatosGraficoUsuarios($datos);

            return [
                'error' => false,
                'usuarios' => $datos,
                'totales' => $resumen,
                'grafico' => $grafico,
                'total' => count($datos),
                'filtros' => [
                    'fecha_inicio' => $fecha_inicio,
                    'fecha_fin' => $fecha_fin
                ]
            ];
        } catch (PDOException $e) {
            return [
                'error' => true,
                'mensaje' => 'Error al generar reporte de usuarios: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene estadísticas de servicios por usuario
     */
    public function obtenerEstadisticasUsuario($usuario_id, $fecha_inicio, $fecha_fin)
    {
        // Validar ID de usuario
        if (empty($usuario_id)) {
            return ['error' => true, 'mensaje' => 'ID de usuario no proporcionado'];
        }

        // Validar fechas
        if (empty($fecha_inicio) || empty($fecha_fin)) {
            return ['error' => true, 'mensaje' => 'Debe proporcionar fechas de inicio y fin'];
        }

        // Obtener datos del usuario
        $usuario = $this->usuarioModel->obtener($usuario_id);
        if (isset($usuario['error'])) {
            return $usuario;
        }

        // Obtener estadísticas
        $estadisticas = $this->reporteModel->obtenerEstadisticasUsuario($usuario_id, $fecha_inicio, $fecha_fin);

        // Agregar información del usuario
        $estadisticas['usuario'] = $usuario;

        return $estadisticas;
    }

    /**
     * Obtiene el resumen de todos los usuarios con sus estadísticas
     */
    public function obtenerResumenUsuarios($fecha_inicio, $fecha_fin)
    {
        // Validar fechas
        if (empty($fecha_inicio) || empty($fecha_fin)) {
            return ['error' => true, 'mensaje' => 'Debe proporcionar fechas de inicio y fin'];
        }

        // Obtener resumen
        return $this->reporteModel->obtenerResumenUsuarios($fecha_inicio, $fecha_fin);
    }

    /**
     * Genera un reporte de servicios con estadísticas
     * @param array $filtros Filtros como fecha_inicio, fecha_fin, vehiculo_id, operador_id, estado
     * @return array Datos del reporte
     */
    public function generarReporteServicios($filtros = [])
    {
        try {
            // Verificar y configurar fechas
            $fecha_inicio = isset($filtros['fecha_inicio']) ? $filtros['fecha_inicio'] : date('Y-m-d', strtotime('-30 days'));
            $fecha_fin = isset($filtros['fecha_fin']) ? $filtros['fecha_fin'] : date('Y-m-d');

            // Inicializar arrays de resultados
            $estadisticas = [
                'total' => 0,
                'finalizados' => 0,
                'cancelados' => 0,
                'pendientes' => 0,
                'asignados' => 0,
                'en_camino' => 0
            ];

            // Construir consulta base para estadísticas globales
            $sql_estadisticas = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) as finalizados,
                SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
                SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado = 'asignado' THEN 1 ELSE 0 END) as asignados,
                SUM(CASE WHEN estado = 'en_camino' THEN 1 ELSE 0 END) as en_camino
            FROM 
                servicios s
            WHERE 
                s.fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin";

            // Construir consulta para detalle de servicios
            $sql_servicios = "
            SELECT 
                s.id,
                s.fecha_solicitud,
                s.fecha_asignacion,
                s.fecha_fin,
                s.direccion,
                s.referencia,
                s.estado,
                s.comentarios,
                c.telefono as cliente_telefono,
                c.nombre as cliente_nombre,
                v.placa,
                v.numero_movil,
                v.marca,
                v.modelo,
                v.color,
                u.nombre as operador_nombre
            FROM 
                servicios s
            LEFT JOIN 
                clientes c ON s.cliente_id = c.id
            LEFT JOIN 
                vehiculos v ON s.vehiculo_id = v.id
            LEFT JOIN 
                usuarios u ON s.operador_id = u.id
            WHERE 
                s.fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin";

            // Construir consulta para top vehículos
            $sql_top_vehiculos = "
            SELECT 
                v.id,
                v.placa,
                v.numero_movil,
                COUNT(s.id) as total_servicios,
                SUM(CASE WHEN s.estado = 'finalizado' THEN 1 ELSE 0 END) as finalizados,
                SUM(CASE WHEN s.estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
                CASE 
                    WHEN COUNT(s.id) > 0 THEN 
                        ROUND(SUM(CASE WHEN s.estado = 'finalizado' THEN 1 ELSE 0 END) * 100.0 / COUNT(s.id), 1)
                    ELSE 0 
                END as efectividad
            FROM 
                servicios s
            JOIN 
                vehiculos v ON s.vehiculo_id = v.id
            WHERE 
                s.fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin";

            // Construir consulta para top operadores
            $sql_top_operadores = "
            SELECT 
                u.id,
                u.nombre,
                COUNT(s.id) as total_servicios,
                SUM(CASE WHEN s.estado = 'finalizado' THEN 1 ELSE 0 END) as finalizados,
                SUM(CASE WHEN s.estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
                CASE 
                    WHEN COUNT(s.id) > 0 THEN 
                        ROUND(SUM(CASE WHEN s.estado = 'finalizado' THEN 1 ELSE 0 END) * 100.0 / COUNT(s.id), 1)
                    ELSE 0 
                END as efectividad
            FROM 
                servicios s
            JOIN 
                usuarios u ON s.operador_id = u.id
            WHERE 
                s.fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin";

            // Consulta para tendencia diaria
            $sql_tendencia = "
            SELECT 
                DATE(fecha_solicitud) as fecha,
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) as finalizados,
                SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados
            FROM 
                servicios
            WHERE 
                fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin
        ";

            // Preparar parámetros para consultas
            $params = [
                ':fecha_inicio' => $fecha_inicio . ' 00:00:00',
                ':fecha_fin' => $fecha_fin . ' 23:59:59'
            ];

            // Agregar filtros adicionales si existen
            $condiciones_adicionales = [];

            if (!empty($filtros['vehiculo_id'])) {
                $condiciones_adicionales[] = "s.vehiculo_id = :vehiculo_id";
                $params[':vehiculo_id'] = $filtros['vehiculo_id'];
            }

            if (!empty($filtros['operador_id'])) {
                $condiciones_adicionales[] = "s.operador_id = :operador_id";
                $params[':operador_id'] = $filtros['operador_id'];
            }

            if (!empty($filtros['estado'])) {
                $condiciones_adicionales[] = "s.estado = :estado";
                $params[':estado'] = $filtros['estado'];
            }

            // Agregar condiciones a las consultas
            if (!empty($condiciones_adicionales)) {
                $condiciones_str = " AND " . implode(" AND ", $condiciones_adicionales);

                $sql_estadisticas .= $condiciones_str;
                $sql_servicios .= $condiciones_str;
                $sql_top_vehiculos .= $condiciones_str;
                $sql_top_operadores .= $condiciones_str;
                $sql_tendencia .= $condiciones_str;
            }

            // Completar las consultas con agrupamiento y ordenación
            $sql_top_vehiculos .= " GROUP BY v.id, v.placa, v.numero_movil ORDER BY total_servicios DESC LIMIT 10";
            $sql_top_operadores .= " GROUP BY u.id, u.nombre ORDER BY total_servicios DESC LIMIT 10";
            $sql_tendencia .= " GROUP BY DATE(fecha_solicitud) ORDER BY fecha";
            $sql_servicios .= " ORDER BY s.fecha_solicitud DESC";

            // Obtener estadísticas generales
            $stmt = $this->pdo->prepare($sql_estadisticas);
            $stmt->execute($params);
            $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);

            // Obtener detalle de servicios
            $stmt = $this->pdo->prepare($sql_servicios);
            $stmt->execute($params);
            $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Obtener top vehículos
            $stmt = $this->pdo->prepare($sql_top_vehiculos);
            $stmt->execute($params);
            $top_vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Obtener top operadores
            $stmt = $this->pdo->prepare($sql_top_operadores);
            $stmt->execute($params);
            $top_operadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Obtener tendencia
            $stmt = $this->pdo->prepare($sql_tendencia);
            $stmt->execute($params);
            $tendencia_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Transformar tendencia en un formato más conveniente para gráficos
            $tendencia = [];
            foreach ($tendencia_raw as $item) {
                $fecha_formateada = date('d/m/Y', strtotime($item['fecha']));
                $tendencia[$fecha_formateada] = [
                    'fecha' => $item['fecha'],
                    'total' => (int)$item['total'],
                    'finalizados' => (int)$item['finalizados'],
                    'cancelados' => (int)$item['cancelados']
                ];
            }

            // Preparar y retornar el resultado completo
            return [
                'error' => false,
                'estadisticas' => $estadisticas,
                'servicios' => $servicios,
                'top_vehiculos' => $top_vehiculos,
                'top_operadores' => $top_operadores,
                'tendencia' => $tendencia,
                'filtros' => [
                    'fecha_inicio' => $fecha_inicio,
                    'fecha_fin' => $fecha_fin,
                    'vehiculo_id' => $filtros['vehiculo_id'] ?? null,
                    'operador_id' => $filtros['operador_id'] ?? null,
                    'estado' => $filtros['estado'] ?? null
                ]
            ];
        } catch (PDOException $e) {
            return [
                'error' => true,
                'mensaje' => 'Error al generar reporte de servicios: ' . $e->getMessage(),
                'estadisticas' => [],
                'servicios' => [],
                'top_vehiculos' => [],
                'top_operadores' => [],
                'tendencia' => []
            ];
        }
    }


    /**
     * Obtiene todos los usuarios
     */
    public function obtenerTodosUsuarios()
    {
        return $this->usuarioModel->listar();
    }

    /**
     * Calcula el tiempo promedio de asignación para un usuario
     */
    private function calcularTiempoPromedioAsignacion($usuario_id, $fecha_inicio, $fecha_fin)
    {
        try {
            $sql = "
                SELECT 
                    AVG(TIMESTAMPDIFF(MINUTE, fecha_solicitud, fecha_asignacion)) as tiempo_promedio
                FROM 
                    servicios
                WHERE 
                    operador_id = :operador_id 
                    AND fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin
                    AND fecha_asignacion IS NOT NULL
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':operador_id' => $usuario_id,
                ':fecha_inicio' => $fecha_inicio . ' 00:00:00',
                ':fecha_fin' => $fecha_fin . ' 23:59:59'
            ]);

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['tiempo_promedio'] ?? 0;
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Prepara datos para el gráfico de usuarios
     */
    private function prepararDatosGraficoUsuarios($datos)
    {
        // Tomar los 5 usuarios con más servicios para el gráfico
        $datosGrafico = array_slice($datos, 0, 5);

        $labels = array_column($datosGrafico, 'nombre');

        $series = [
            [
                'label' => 'Servicios Totales',
                'data' => array_column($datosGrafico, 'total_servicios')
            ],
            [
                'label' => 'Finalizados',
                'data' => array_column($datosGrafico, 'finalizados')
            ],
            [
                'label' => 'Cancelados',
                'data' => array_column($datosGrafico, 'cancelados')
            ]
        ];

        return [
            'labels' => $labels,
            'series' => $series,
            'tipo' => 'bar'
        ];
    }

    /**
     * Obtiene detalles de un usuario específico para el reporte
     */
    public function obtenerDetalleUsuario($usuario_id, $fecha_inicio, $fecha_fin)
    {
        try {
            // Obtener información básica del usuario
            $sql = "
                SELECT 
                    u.*,
                    COUNT(s.id) as total_servicios,
                    SUM(CASE WHEN s.estado = 'finalizado' THEN 1 ELSE 0 END) as finalizados,
                    SUM(CASE WHEN s.estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
                    CASE 
                        WHEN COUNT(s.id) > 0 THEN 
                            ROUND(SUM(CASE WHEN s.estado = 'finalizado' THEN 1 ELSE 0 END) * 100.0 / COUNT(s.id), 2)
                        ELSE 0 
                    END as efectividad
                FROM 
                    usuarios u
                LEFT JOIN 
                    servicios s ON u.id = s.operador_id AND s.fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin
                WHERE 
                    u.id = :operador_id
                GROUP BY 
                    u.id
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':operador_id' => $usuario_id,
                ':fecha_inicio' => $fecha_inicio . ' 00:00:00',
                ':fecha_fin' => $fecha_fin . ' 23:59:59'
            ]);

            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                return [
                    'error' => true,
                    'mensaje' => 'Usuario no encontrado'
                ];
            }

            // Calcular tiempo promedio de asignación
            $usuario['tiempo_promedio'] = $this->calcularTiempoPromedioAsignacion($usuario_id, $fecha_inicio, $fecha_fin);

            // Obtener servicios recientes de este usuario
            $sql = "
                SELECT 
                    s.id,
                    s.fecha_solicitud,
                    s.estado,
                    c.telefono as cliente_telefono,
                    c.nombre as cliente_nombre,
                    CONCAT(v.numero_movil, ' - ', v.placa) as vehiculo
                FROM 
                    servicios s
                LEFT JOIN 
                    clientes c ON s.cliente_id = c.id
                LEFT JOIN 
                    vehiculos v ON s.vehiculo_id = v.id
                WHERE 
                    s.operador_id = :operador_id
                    AND s.fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin
                ORDER BY 
                    s.fecha_solicitud DESC
                LIMIT 10
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':operador_id' => $usuario_id,
                ':fecha_inicio' => $fecha_inicio . ' 00:00:00',
                ':fecha_fin' => $fecha_fin . ' 23:59:59'
            ]);

            $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Agrupar datos por día
            $sql = "
                SELECT 
                    DATE(fecha_solicitud) as fecha,
                    COUNT(*) as total,
                    SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) as finalizados,
                    SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados
                FROM 
                    servicios
                WHERE 
                    operador_id = :operador_id
                    AND fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin
                GROUP BY 
                    DATE(fecha_solicitud)
                ORDER BY 
                    fecha
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':operador_id' => $usuario_id,
                ':fecha_inicio' => $fecha_inicio . ' 00:00:00',
                ':fecha_fin' => $fecha_fin . ' 23:59:59'
            ]);

            $datos_diarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Preparar datos para gráfico
            $labels = array_column($datos_diarios, 'fecha');
            $series = [
                [
                    'label' => 'Total',
                    'data' => array_column($datos_diarios, 'total')
                ],
                [
                    'label' => 'Finalizados',
                    'data' => array_column($datos_diarios, 'finalizados')
                ],
                [
                    'label' => 'Cancelados',
                    'data' => array_column($datos_diarios, 'cancelados')
                ]
            ];

            $grafico = [
                'labels' => $labels,
                'series' => $series,
                'tipo' => 'line'
            ];

            return [
                'error' => false,
                'usuario' => $usuario,
                'servicios' => $servicios,
                'grafico' => $grafico
            ];
        } catch (PDOException $e) {
            return [
                'error' => true,
                'mensaje' => 'Error al obtener detalle del usuario: ' . $e->getMessage()
            ];
        }
    }
}
