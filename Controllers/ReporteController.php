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
     * Genera un reporte completo de servicios con filtros y paginación opcional
     * 
     * @param array $filtros Filtros a aplicar (fecha_inicio, fecha_fin, vehiculo_id, operador_id, estado)
     * @param int|null $items_por_pagina Cantidad de registros por página
     * @param int|null $offset Desplazamiento para la paginación
     * @return array Datos del reporte
     */
    public function generarReporteServicios($filtros, $items_por_pagina = null, $offset = null)
    {
        // Obtener estadísticas generales
        $estadisticas = $this->obtenerEstadisticasServicios($filtros);

        // Obtener top vehículos
        $top_vehiculos = $this->obtenerTopVehiculos($filtros);

        // Obtener top operadores
        $top_operadores = $this->obtenerTopOperadores($filtros);

        // Obtener tendencia temporal de servicios
        $tendencia = $this->obtenerTendenciaServicios($filtros);

        // Obtener servicios detallados con paginación opcional
        $servicios = $this->obtenerServiciosDetallados($filtros, $items_por_pagina, $offset);

        // Contar total de servicios para la paginación
        $total_servicios = $this->contarServiciosFiltrados($filtros);

        return [
            'estadisticas' => $estadisticas,
            'servicios' => $servicios,
            'top_vehiculos' => $top_vehiculos,
            'top_operadores' => $top_operadores,
            'tendencia' => $tendencia,
            'total_registros' => $total_servicios
        ];
    }

    /**
     * Obtiene estadísticas generales de servicios según los filtros aplicados
     * 
     * @param array $filtros Filtros a aplicar
     * @return array Estadísticas de servicios
     */
    private function obtenerEstadisticasServicios($filtros)
    {
        try {
            $sql = "
            SELECT 
                COUNT(*) as total_servicios,
                SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) as finalizados,
                SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
                SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado = 'asignado' THEN 1 ELSE 0 END) as asignados,
                SUM(CASE WHEN estado = 'en_camino' THEN 1 ELSE 0 END) as en_camino,
                ROUND(AVG(TIMESTAMPDIFF(MINUTE, fecha_solicitud, fecha_asignacion)), 2) as tiempo_promedio_asignacion,
                ROUND(AVG(TIMESTAMPDIFF(MINUTE, fecha_asignacion, fecha_fin)), 2) as tiempo_promedio_servicio
            FROM servicios s
            WHERE 1=1
        ";

            $params = [];

            // Aplicar filtros de fecha
            if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
                $sql .= " AND DATE(s.fecha_solicitud) BETWEEN :fecha_inicio AND :fecha_fin";
                $params[':fecha_inicio'] = $filtros['fecha_inicio'];
                $params[':fecha_fin'] = $filtros['fecha_fin'];
            } else if (!empty($filtros['fecha_inicio'])) {
                $sql .= " AND DATE(s.fecha_solicitud) >= :fecha_inicio";
                $params[':fecha_inicio'] = $filtros['fecha_inicio'];
            } else if (!empty($filtros['fecha_fin'])) {
                $sql .= " AND DATE(s.fecha_solicitud) <= :fecha_fin";
                $params[':fecha_fin'] = $filtros['fecha_fin'];
            }

            // Filtro por vehículo
            if (!empty($filtros['vehiculo_id'])) {
                $sql .= " AND s.vehiculo_id = :vehiculo_id";
                $params[':vehiculo_id'] = $filtros['vehiculo_id'];
            }

            // Filtro por operador
            if (!empty($filtros['operador_id'])) {
                $sql .= " AND s.operador_id = :operador_id";
                $params[':operador_id'] = $filtros['operador_id'];
            }

            // Filtro por estado
            if (!empty($filtros['estado'])) {
                $sql .= " AND s.estado = :estado";
                $params[':estado'] = $filtros['estado'];
            }

            $stmt = $this->pdo->prepare($sql);

            // Vincular parámetros
            foreach ($params as $param => $value) {
                $tipo = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($param, $value, $tipo);
            }

            $stmt->execute();
            $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);

            // Calcular porcentajes y otras métricas derivadas
            $total = $estadisticas['total_servicios'] ?? 0;

            if ($total > 0) {
                $estadisticas['porcentaje_finalizados'] = round(($estadisticas['finalizados'] * 100) / $total, 2);
                $estadisticas['porcentaje_cancelados'] = round(($estadisticas['cancelados'] * 100) / $total, 2);
                $estadisticas['efectividad'] = round(($estadisticas['finalizados'] * 100) / ($estadisticas['finalizados'] + $estadisticas['cancelados']), 2);
            } else {
                $estadisticas['porcentaje_finalizados'] = 0;
                $estadisticas['porcentaje_cancelados'] = 0;
                $estadisticas['efectividad'] = 0;
            }

            return $estadisticas;
        } catch (PDOException $e) {
            error_log('Error al obtener estadísticas de servicios: ' . $e->getMessage());
            return [
                'total_servicios' => 0,
                'finalizados' => 0,
                'cancelados' => 0,
                'pendientes' => 0,
                'asignados' => 0,
                'en_camino' => 0,
                'tiempo_promedio_asignacion' => 0,
                'tiempo_promedio_servicio' => 0,
                'porcentaje_finalizados' => 0,
                'porcentaje_cancelados' => 0,
                'efectividad' => 0
            ];
        }
    }

    /**
     * Obtiene los vehículos con más servicios según los filtros aplicados
     * 
     * @param array $filtros Filtros a aplicar
     * @param int $limite Número máximo de vehículos a devolver
     * @return array Lista de vehículos top
     */
    private function obtenerTopVehiculos($filtros, $limite = 5)
    {
        try {
            $sql = "
            SELECT 
                v.id, 
                v.placa, 
                v.numero_movil, 
                v.marca, 
                v.modelo, 
                COUNT(s.id) as total_servicios,
                SUM(CASE WHEN s.estado = 'finalizado' THEN 1 ELSE 0 END) as finalizados,
                SUM(CASE WHEN s.estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
                CASE 
                    WHEN COUNT(s.id) > 0 THEN 
                        ROUND((SUM(CASE WHEN s.estado = 'finalizado' THEN 1 ELSE 0 END) * 100.0) / COUNT(s.id), 2)
                    ELSE 0 
                END as efectividad
            FROM 
                vehiculos v
            LEFT JOIN 
                servicios s ON v.id = s.vehiculo_id
            WHERE 1=1
        ";

            $params = [];

            // Aplicar filtros de fecha
            if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
                $sql .= " AND DATE(s.fecha_solicitud) BETWEEN :fecha_inicio AND :fecha_fin";
                $params[':fecha_inicio'] = $filtros['fecha_inicio'];
                $params[':fecha_fin'] = $filtros['fecha_fin'];
            } else if (!empty($filtros['fecha_inicio'])) {
                $sql .= " AND DATE(s.fecha_solicitud) >= :fecha_inicio";
                $params[':fecha_inicio'] = $filtros['fecha_inicio'];
            } else if (!empty($filtros['fecha_fin'])) {
                $sql .= " AND DATE(s.fecha_solicitud) <= :fecha_fin";
                $params[':fecha_fin'] = $filtros['fecha_fin'];
            }

            // Filtro por vehículo específico
            if (!empty($filtros['vehiculo_id'])) {
                $sql .= " AND v.id = :vehiculo_id";
                $params[':vehiculo_id'] = $filtros['vehiculo_id'];
            }

            // Filtro por operador
            if (!empty($filtros['operador_id'])) {
                $sql .= " AND s.operador_id = :operador_id";
                $params[':operador_id'] = $filtros['operador_id'];
            }

            // Filtro por estado
            if (!empty($filtros['estado'])) {
                $sql .= " AND s.estado = :estado";
                $params[':estado'] = $filtros['estado'];
            }

            $sql .= " GROUP BY v.id, v.placa, v.numero_movil, v.marca, v.modelo";
            $sql .= " ORDER BY total_servicios DESC";
            $sql .= " LIMIT :limite";

            $params[':limite'] = $limite;

            $stmt = $this->pdo->prepare($sql);

            // Vincular parámetros
            foreach ($params as $param => $value) {
                $tipo = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($param, $value, $tipo);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al obtener top vehículos: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene los operadores con más servicios según los filtros aplicados
     * 
     * @param array $filtros Filtros a aplicar
     * @param int $limite Número máximo de operadores a devolver
     * @return array Lista de operadores top
     */
    private function obtenerTopOperadores($filtros, $limite = 5)
    {
        try {
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
                        ROUND((SUM(CASE WHEN s.estado = 'finalizado' THEN 1 ELSE 0 END) * 100.0) / COUNT(s.id), 2)
                    ELSE 0 
                END as efectividad
            FROM 
                usuarios u
            LEFT JOIN 
                servicios s ON u.id = s.operador_id
            WHERE 
                u.rol = 'operador'
        ";

            $params = [];

            // Aplicar filtros de fecha
            if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
                $sql .= " AND DATE(s.fecha_solicitud) BETWEEN :fecha_inicio AND :fecha_fin";
                $params[':fecha_inicio'] = $filtros['fecha_inicio'];
                $params[':fecha_fin'] = $filtros['fecha_fin'];
            } else if (!empty($filtros['fecha_inicio'])) {
                $sql .= " AND DATE(s.fecha_solicitud) >= :fecha_inicio";
                $params[':fecha_inicio'] = $filtros['fecha_inicio'];
            } else if (!empty($filtros['fecha_fin'])) {
                $sql .= " AND DATE(s.fecha_solicitud) <= :fecha_fin";
                $params[':fecha_fin'] = $filtros['fecha_fin'];
            }

            // Filtro por vehículo
            if (!empty($filtros['vehiculo_id'])) {
                $sql .= " AND s.vehiculo_id = :vehiculo_id";
                $params[':vehiculo_id'] = $filtros['vehiculo_id'];
            }

            // Filtro por operador específico
            if (!empty($filtros['operador_id'])) {
                $sql .= " AND u.id = :operador_id";
                $params[':operador_id'] = $filtros['operador_id'];
            }

            // Filtro por estado
            if (!empty($filtros['estado'])) {
                $sql .= " AND s.estado = :estado";
                $params[':estado'] = $filtros['estado'];
            }

            $sql .= " GROUP BY u.id, u.nombre, u.username";
            $sql .= " ORDER BY total_servicios DESC";
            $sql .= " LIMIT :limite";

            $params[':limite'] = $limite;

            $stmt = $this->pdo->prepare($sql);

            // Vincular parámetros
            foreach ($params as $param => $value) {
                $tipo = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($param, $value, $tipo);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al obtener top operadores: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene la tendencia de servicios agrupados por día según los filtros aplicados
     * 
     * @param array $filtros Filtros a aplicar
     * @param int $limite Número máximo de días a devolver
     * @return array Datos de tendencia por día
     */
    private function obtenerTendenciaServicios($filtros, $limite = 15)
    {
        try {
            $sql = "
            SELECT 
                DATE(fecha_solicitud) as fecha,
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) as finalizados,
                SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados
            FROM 
                servicios s
            WHERE 1=1
        ";

            $params = [];

            // Aplicar filtros de fecha
            if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
                $sql .= " AND DATE(s.fecha_solicitud) BETWEEN :fecha_inicio AND :fecha_fin";
                $params[':fecha_inicio'] = $filtros['fecha_inicio'];
                $params[':fecha_fin'] = $filtros['fecha_fin'];
            } else if (!empty($filtros['fecha_inicio'])) {
                $sql .= " AND DATE(s.fecha_solicitud) >= :fecha_inicio";
                $params[':fecha_inicio'] = $filtros['fecha_inicio'];
            } else if (!empty($filtros['fecha_fin'])) {
                $sql .= " AND DATE(s.fecha_solicitud) <= :fecha_fin";
                $params[':fecha_fin'] = $filtros['fecha_fin'];
            }

            // Filtro por vehículo
            if (!empty($filtros['vehiculo_id'])) {
                $sql .= " AND s.vehiculo_id = :vehiculo_id";
                $params[':vehiculo_id'] = $filtros['vehiculo_id'];
            }

            // Filtro por operador
            if (!empty($filtros['operador_id'])) {
                $sql .= " AND s.operador_id = :operador_id";
                $params[':operador_id'] = $filtros['operador_id'];
            }

            // Filtro por estado
            if (!empty($filtros['estado'])) {
                $sql .= " AND s.estado = :estado";
                $params[':estado'] = $filtros['estado'];
            }

            $sql .= " GROUP BY DATE(fecha_solicitud)";
            $sql .= " ORDER BY fecha DESC";
            $sql .= " LIMIT :limite";

            $params[':limite'] = $limite;

            $stmt = $this->pdo->prepare($sql);

            // Vincular parámetros
            foreach ($params as $param => $value) {
                $tipo = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($param, $value, $tipo);
            }

            $stmt->execute();
            $tendencia = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Revertir el orden para que quede cronológico (más antiguo primero)
            return array_reverse($tendencia);
        } catch (PDOException $e) {
            error_log('Error al obtener tendencia de servicios: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene la lista detallada de servicios con filtros y paginación
     * 
     * @param array $filtros Filtros a aplicar
     * @param int|null $limite Cantidad máxima de registros a devolver
     * @param int|null $offset Desplazamiento para la paginación
     * @return array Lista de servicios
     */
    private function obtenerServiciosDetallados($filtros, $limite = null, $offset = null)
    {
        // Construir la consulta base
        $sql = "SELECT s.*, 
            c.nombre as cliente_nombre, c.telefono as cliente_telefono,
            d.direccion, d.referencia,
            v.placa, v.numero_movil, v.marca, v.modelo,
            u.nombre as operador_nombre,
            TIMEDIFF(s.fecha_fin, s.fecha_solicitud) as tiempo_total
            FROM servicios s
            LEFT JOIN clientes c ON s.cliente_id = c.id
            LEFT JOIN direcciones d ON s.direccion_id = d.id
            LEFT JOIN vehiculos v ON s.vehiculo_id = v.id
            LEFT JOIN usuarios u ON s.operador_id = u.id
            WHERE 1=1";

        // Parámetros para la consulta
        $params = [];

        // Aplicar filtros de fecha
        if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
            $sql .= " AND DATE(s.fecha_solicitud) BETWEEN :fecha_inicio AND :fecha_fin";
            $params[':fecha_inicio'] = $filtros['fecha_inicio'];
            $params[':fecha_fin'] = $filtros['fecha_fin'];
        } else if (!empty($filtros['fecha_inicio'])) {
            $sql .= " AND DATE(s.fecha_solicitud) >= :fecha_inicio";
            $params[':fecha_inicio'] = $filtros['fecha_inicio'];
        } else if (!empty($filtros['fecha_fin'])) {
            $sql .= " AND DATE(s.fecha_solicitud) <= :fecha_fin";
            $params[':fecha_fin'] = $filtros['fecha_fin'];
        }

        // Filtro por vehículo
        if (!empty($filtros['vehiculo_id'])) {
            $sql .= " AND s.vehiculo_id = :vehiculo_id";
            $params[':vehiculo_id'] = $filtros['vehiculo_id'];
        }

        // Filtro por operador
        if (!empty($filtros['operador_id'])) {
            $sql .= " AND s.operador_id = :operador_id";
            $params[':operador_id'] = $filtros['operador_id'];
        }

        // Filtro por estado
        if (!empty($filtros['estado'])) {
            $sql .= " AND s.estado = :estado";
            $params[':estado'] = $filtros['estado'];
        }

        // Ordenar resultados (más recientes primero)
        $sql .= " ORDER BY s.fecha_solicitud DESC";

        // Aplicar paginación si se especifica
        if ($limite !== null && $offset !== null) {
            $sql .= " LIMIT :offset, :limite";
            $params[':limite'] = (int)$limite;
            $params[':offset'] = (int)$offset;
        }

        try {
            $stmt = $this->pdo->prepare($sql);

            // Vincular parámetros
            foreach ($params as $param => $value) {
                // Determinar el tipo de parámetro
                $tipo = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($param, $value, $tipo);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al obtener detalle de servicios: ' . $e->getMessage());
            return [
                'error' => true,
                'mensaje' => 'Error al obtener servicios: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cuenta el total de servicios que coinciden con los filtros aplicados
     * 
     * @param array $filtros Filtros a aplicar
     * @return int Total de registros
     */
    private function contarServiciosFiltrados($filtros)
    {
        $sql = "SELECT COUNT(*) as total FROM servicios s
            LEFT JOIN clientes c ON s.cliente_id = c.id
            LEFT JOIN direcciones d ON s.direccion_id = d.id
            LEFT JOIN vehiculos v ON s.vehiculo_id = v.id
            LEFT JOIN usuarios u ON s.operador_id = u.id
            WHERE 1=1";

        // Parámetros para la consulta
        $params = [];

        // Aplicar los mismos filtros que en obtenerServiciosDetallados
        // Filtros de fecha
        if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
            $sql .= " AND DATE(s.fecha_solicitud) BETWEEN :fecha_inicio AND :fecha_fin";
            $params[':fecha_inicio'] = $filtros['fecha_inicio'];
            $params[':fecha_fin'] = $filtros['fecha_fin'];
        } else if (!empty($filtros['fecha_inicio'])) {
            $sql .= " AND DATE(s.fecha_solicitud) >= :fecha_inicio";
            $params[':fecha_inicio'] = $filtros['fecha_inicio'];
        } else if (!empty($filtros['fecha_fin'])) {
            $sql .= " AND DATE(s.fecha_solicitud) <= :fecha_fin";
            $params[':fecha_fin'] = $filtros['fecha_fin'];
        }

        // Filtro por vehículo
        if (!empty($filtros['vehiculo_id'])) {
            $sql .= " AND s.vehiculo_id = :vehiculo_id";
            $params[':vehiculo_id'] = $filtros['vehiculo_id'];
        }

        // Filtro por operador
        if (!empty($filtros['operador_id'])) {
            $sql .= " AND s.operador_id = :operador_id";
            $params[':operador_id'] = $filtros['operador_id'];
        }

        // Filtro por estado
        if (!empty($filtros['estado'])) {
            $sql .= " AND s.estado = :estado";
            $params[':estado'] = $filtros['estado'];
        }

        try {
            $stmt = $this->pdo->prepare($sql);

            // Vincular parámetros
            foreach ($params as $param => $value) {
                // Determinar el tipo de parámetro
                $tipo = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($param, $value, $tipo);
            }

            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] ?? 0;
        } catch (PDOException $e) {
            error_log('Error al contar servicios filtrados: ' . $e->getMessage());
            return 0;
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
