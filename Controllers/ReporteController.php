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
