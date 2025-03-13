<?php
// filepath: c:\xampp\htdocs\GitHub\Taxis\Models\ReporteModel.php

class Reporte {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtiene estadísticas de servicios por usuario
     * 
     * @param int $usuario_id ID del usuario
     * @param string $fecha_inicio Fecha de inicio (formato Y-m-d)
     * @param string $fecha_fin Fecha de fin (formato Y-m-d)
     * @return array Estadísticas del usuario
     */
    public function obtenerEstadisticasUsuario($usuario_id, $fecha_inicio, $fecha_fin) {
        try {
            // Formar fechas con hora
            $inicio = $fecha_inicio . ' 00:00:00';
            $fin = $fecha_fin . ' 23:59:59';
            
            // Consulta principal
            $sql = "
                SELECT 
                    COUNT(*) as total_servicios,
                    SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) as finalizados,
                    SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
                    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                    SUM(CASE WHEN estado = 'asignado' THEN 1 ELSE 0 END) as asignados,
                    AVG(TIMESTAMPDIFF(MINUTE, fecha_solicitud, fecha_asignacion)) as tiempo_promedio_asignacion
                FROM 
                    servicios
                WHERE 
                    operador_id = :operador_id
                    AND fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':operador_id', $usuario_id, PDO::PARAM_INT);
            $stmt->bindParam(':fecha_inicio', $inicio, PDO::PARAM_STR);
            $stmt->bindParam(':fecha_fin', $fin, PDO::PARAM_STR);
            $stmt->execute();
            
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Calcular efectividad
            if ($resultado['total_servicios'] > 0) {
                $resultado['efectividad'] = round(($resultado['finalizados'] * 100) / $resultado['total_servicios'], 2);
            } else {
                $resultado['efectividad'] = 0;
            }
            
            // Calcular servicios por día
            $sql_diario = "
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
                    fecha ASC
            ";
            
            $stmt = $this->pdo->prepare($sql_diario);
            $stmt->bindParam(':operador_id', $usuario_id, PDO::PARAM_INT);
            $stmt->bindParam(':fecha_inicio', $inicio, PDO::PARAM_STR);
            $stmt->bindParam(':fecha_fin', $fin, PDO::PARAM_STR);
            $stmt->execute();
            
            $resultado['desglose_diario'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Obtener servicios más recientes
            $sql_recientes = "
                SELECT 
                    s.id,
                    s.fecha_solicitud,
                    s.fecha_asignacion,
                    s.fecha_fin,
                    s.estado,
                    c.telefono as cliente_telefono,
                    c.nombre as cliente_nombre,
                    COALESCE(d.direccion, 'Dirección no disponible') as direccion,
                    COALESCE(CONCAT(v.numero_movil, ' - ', v.placa), 'Sin asignar') as vehiculo
                FROM 
                    servicios s
                LEFT JOIN 
                    clientes c ON s.cliente_id = c.id
                LEFT JOIN 
                    direcciones d ON s.direccion_id = d.id
                LEFT JOIN 
                    vehiculos v ON s.vehiculo_id = v.id
                WHERE 
                    s.operador_id = :operador_id
                    AND s.fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin
                ORDER BY 
                    s.fecha_solicitud DESC
                LIMIT 10
            ";
            
            $stmt = $this->pdo->prepare($sql_recientes);
            $stmt->bindParam(':operador_id', $usuario_id, PDO::PARAM_INT);
            $stmt->bindParam(':fecha_inicio', $inicio, PDO::PARAM_STR);
            $stmt->bindParam(':fecha_fin', $fin, PDO::PARAM_STR);
            $stmt->execute();
            
            $resultado['servicios_recientes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Tiempos promedio por estado
            $sql_tiempos = "
                SELECT 
                    AVG(CASE 
                        WHEN fecha_asignacion IS NOT NULL THEN 
                            TIMESTAMPDIFF(MINUTE, fecha_solicitud, fecha_asignacion)
                        ELSE NULL
                    END) as tiempo_asignacion,
                    AVG(CASE 
                        WHEN fecha_fin IS NOT NULL AND fecha_asignacion IS NOT NULL THEN 
                            TIMESTAMPDIFF(MINUTE, fecha_asignacion, fecha_fin)
                        ELSE NULL
                    END) as tiempo_servicio,
                    AVG(CASE 
                        WHEN fecha_fin IS NOT NULL THEN 
                            TIMESTAMPDIFF(MINUTE, fecha_solicitud, fecha_fin)
                        ELSE NULL
                    END) as tiempo_total
                FROM 
                    servicios
                WHERE 
                    operador_id = :operador_id
                    AND fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin
            ";
            
            $stmt = $this->pdo->prepare($sql_tiempos);
            $stmt->bindParam(':operador_id', $usuario_id, PDO::PARAM_INT);
            $stmt->bindParam(':fecha_inicio', $inicio, PDO::PARAM_STR);
            $stmt->bindParam(':fecha_fin', $fin, PDO::PARAM_STR);
            $stmt->execute();
            
            $resultado['tiempos'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'error' => false,
                'estadisticas' => $resultado
            ];
            
        } catch (PDOException $e) {
            return [
                'error' => true,
                'mensaje' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene un resumen de todos los usuarios con sus estadísticas
     * 
     * @param string $fecha_inicio Fecha de inicio (formato Y-m-d)
     * @param string $fecha_fin Fecha de fin (formato Y-m-d)
     * @return array Resumen de usuarios
     */
    public function obtenerResumenUsuarios($fecha_inicio, $fecha_fin) {
        try {
            // Formar fechas con hora
            $inicio = $fecha_inicio . ' 00:00:00';
            $fin = $fecha_fin . ' 23:59:59';
            
            $sql = "
                SELECT 
                    u.id, 
                    u.nombre, 
                    u.username, 
                    u.rol,
                    u.estado,
                    COUNT(s.id) as total_servicios,
                    SUM(CASE WHEN s.estado = 'finalizado' THEN 1 ELSE 0 END) as finalizados,
                    SUM(CASE WHEN s.estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
                    CASE 
                        WHEN COUNT(s.id) > 0 THEN 
                            ROUND((SUM(CASE WHEN s.estado = 'finalizado' THEN 1 ELSE 0 END) * 100.0) / COUNT(s.id), 2)
                        ELSE 0 
                    END as efectividad,
                    AVG(CASE WHEN s.fecha_asignacion IS NOT NULL THEN 
                        TIMESTAMPDIFF(MINUTE, s.fecha_solicitud, s.fecha_asignacion)
                    ELSE NULL END) as tiempo_promedio_asignacion
                FROM 
                    usuarios u
                LEFT JOIN 
                    servicios s ON u.id = s.operador_id 
                        AND s.fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin
                GROUP BY 
                    u.id, u.nombre, u.username, u.rol, u.estado
                ORDER BY 
                    total_servicios DESC
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':fecha_inicio', $inicio, PDO::PARAM_STR);
            $stmt->bindParam(':fecha_fin', $fin, PDO::PARAM_STR);
            $stmt->execute();
            
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular totales globales
            $totales = [
                'total_servicios' => 0,
                'finalizados' => 0,
                'cancelados' => 0,
                'efectividad' => 0
            ];
            
            foreach ($usuarios as $usuario) {
                $totales['total_servicios'] += $usuario['total_servicios'];
                $totales['finalizados'] += $usuario['finalizados'];
                $totales['cancelados'] += $usuario['cancelados'];
            }
            
            if ($totales['total_servicios'] > 0) {
                $totales['efectividad'] = round(($totales['finalizados'] * 100.0) / $totales['total_servicios'], 2);
            }
            
            return [
                'error' => false,
                'usuarios' => $usuarios,
                'totales' => $totales,
                'periodo' => [
                    'fecha_inicio' => $fecha_inicio,
                    'fecha_fin' => $fecha_fin
                ]
            ];
            
        } catch (PDOException $e) {
            return [
                'error' => true,
                'mensaje' => 'Error al obtener resumen de usuarios: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene estadísticas por servicios en un rango de fechas
     * 
     * @param array $filtros Filtros como fecha_inicio, fecha_fin, usuario_id, vehiculo_id
     * @return array Estadísticas de servicios
     */
    public function obtenerEstadisticasServicios($filtros = []) {
        try {
            // Configurar fechas
            $fecha_inicio = isset($filtros['fecha_inicio']) ? $filtros['fecha_inicio'] . ' 00:00:00' : date('Y-m-d', strtotime('-30 days')) . ' 00:00:00';
            $fecha_fin = isset($filtros['fecha_fin']) ? $filtros['fecha_fin'] . ' 23:59:59' : date('Y-m-d') . ' 23:59:59';
            
            // Construir consulta base
            $sql = "
                SELECT 
                    COUNT(*) as total_servicios,
                    SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) as finalizados,
                    SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
                    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                    SUM(CASE WHEN estado = 'asignado' THEN 1 ELSE 0 END) as asignados,
                    AVG(CASE WHEN fecha_asignacion IS NOT NULL THEN 
                        TIMESTAMPDIFF(MINUTE, fecha_solicitud, fecha_asignacion)
                    ELSE NULL END) as tiempo_promedio_asignacion,
                    AVG(CASE WHEN fecha_fin IS NOT NULL AND fecha_asignacion IS NOT NULL THEN 
                        TIMESTAMPDIFF(MINUTE, fecha_asignacion, fecha_fin)
                    ELSE NULL END) as tiempo_promedio_servicio
                FROM 
                    servicios
                WHERE 
                    fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin
            ";
            
            // Agregar filtros adicionales
            $condiciones = [];
            $params = [
                ':fecha_inicio' => $fecha_inicio,
                ':fecha_fin' => $fecha_fin
            ];
            
            if (!empty($filtros['usuario_id'])) {
                $condiciones[] = "usuario_id = :usuario_id";
                $params[':usuario_id'] = $filtros['usuario_id'];
            }
            
            if (!empty($filtros['vehiculo_id'])) {
                $condiciones[] = "vehiculo_id = :vehiculo_id";
                $params[':vehiculo_id'] = $filtros['vehiculo_id'];
            }
            
            if (!empty($filtros['cliente_id'])) {
                $condiciones[] = "cliente_id = :cliente_id";
                $params[':cliente_id'] = $filtros['cliente_id'];
            }
            
            if (!empty($filtros['estado'])) {
                $condiciones[] = "estado = :estado";
                $params[':estado'] = $filtros['estado'];
            }
            
            if (!empty($condiciones)) {
                $sql .= " AND " . implode(" AND ", $condiciones);
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Calcular efectividad
            if ($estadisticas['total_servicios'] > 0) {
                $estadisticas['efectividad'] = round(($estadisticas['finalizados'] * 100.0) / $estadisticas['total_servicios'], 2);
            } else {
                $estadisticas['efectividad'] = 0;
            }
            
            // Obtener desglose diario
            $sql_diario = "
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
            
            // Agregar los mismos filtros
            if (!empty($condiciones)) {
                $sql_diario .= " AND " . implode(" AND ", $condiciones);
            }
            
            $sql_diario .= " GROUP BY DATE(fecha_solicitud) ORDER BY fecha ASC";
            
            $stmt = $this->pdo->prepare($sql_diario);
            $stmt->execute($params);
            
            $desglose_diario = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'error' => false,
                'estadisticas' => $estadisticas,
                'desglose_diario' => $desglose_diario,
                'filtros' => [
                    'fecha_inicio' => substr($fecha_inicio, 0, 10),
                    'fecha_fin' => substr($fecha_fin, 0, 10)
                ]
            ];
            
        } catch (PDOException $e) {
            return [
                'error' => true,
                'mensaje' => 'Error al obtener estadísticas de servicios: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene estadísticas de sanciones para un reporte
     * 
     * @param array $filtros Filtros como fecha_inicio, fecha_fin, vehiculo_id
     * @return array Estadísticas de sanciones
     */
    public function obtenerEstadisticasSanciones($filtros = []) {
        try {
            // Configurar fechas
            $fecha_inicio = isset($filtros['fecha_inicio']) ? $filtros['fecha_inicio'] . ' 00:00:00' : date('Y-m-d', strtotime('-30 days')) . ' 00:00:00';
            $fecha_fin = isset($filtros['fecha_fin']) ? $filtros['fecha_fin'] . ' 23:59:59' : date('Y-m-d') . ' 23:59:59';
            
            // Consulta principal para estadísticas
            $sql = "
                SELECT 
                    COUNT(*) as total_sanciones,
                    AVG(tiempo_sancion) as tiempo_promedio,
                    SUM(tiempo_sancion) as tiempo_total
                FROM 
                    sanciones
                WHERE 
                    fecha_inicio BETWEEN :fecha_inicio AND :fecha_fin
            ";
            
            // Agregar filtros adicionales
            $condiciones = [];
            $params = [
                ':fecha_inicio' => $fecha_inicio,
                ':fecha_fin' => $fecha_fin
            ];
            
            if (!empty($filtros['vehiculo_id'])) {
                $condiciones[] = "vehiculo_id = :vehiculo_id";
                $params[':vehiculo_id'] = $filtros['vehiculo_id'];
            }
            
            if (!empty($filtros['articulo_id'])) {
                $condiciones[] = "articulo_id = :articulo_id";
                $params[':articulo_id'] = $filtros['articulo_id'];
            }
            
            if (!empty($condiciones)) {
                $sql .= " AND " . implode(" AND ", $condiciones);
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Obtener desglose por artículo
            $sql_articulos = "
                SELECT 
                    a.id,
                    a.codigo,
                    a.descripcion,
                    COUNT(*) as cantidad,
                    AVG(s.tiempo_sancion) as tiempo_promedio
                FROM 
                    sanciones s
                JOIN 
                    articulos_sancion a ON s.articulo_id = a.id
                WHERE 
                    s.fecha_inicio BETWEEN :fecha_inicio AND :fecha_fin
            ";
            
            // Agregar filtros sobre vehículos si existen
            if (!empty($filtros['vehiculo_id'])) {
                $sql_articulos .= " AND s.vehiculo_id = :vehiculo_id";
            }
            
            $sql_articulos .= " GROUP BY a.id, a.codigo, a.descripcion ORDER BY cantidad DESC";
            
            $stmt = $this->pdo->prepare($sql_articulos);
            $stmt->execute($params);
            
            $desglose_articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Obtener desglose por vehículo
            $sql_vehiculos = "
                SELECT 
                    v.id,
                    v.numero_movil,
                    v.placa,
                    COUNT(*) as cantidad,
                    SUM(s.tiempo_sancion) as tiempo_total
                FROM 
                    sanciones s
                JOIN 
                    vehiculos v ON s.vehiculo_id = v.id
                WHERE 
                    s.fecha_inicio BETWEEN :fecha_inicio AND :fecha_fin
            ";
            
            // Agregar filtro por artículo si existe
            if (!empty($filtros['articulo_id'])) {
                $sql_vehiculos .= " AND s.articulo_id = :articulo_id";
            }
            
            $sql_vehiculos .= " GROUP BY v.id, v.numero_movil, v.placa ORDER BY cantidad DESC";
            
            $stmt = $this->pdo->prepare($sql_vehiculos);
            $stmt->execute($params);
            
            $desglose_vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'error' => false,
                'estadisticas' => $estadisticas,
                'desglose_articulos' => $desglose_articulos,
                'desglose_vehiculos' => $desglose_vehiculos,
                'filtros' => [
                    'fecha_inicio' => substr($fecha_inicio, 0, 10),
                    'fecha_fin' => substr($fecha_fin, 0, 10)
                ]
            ];
            
        } catch (PDOException $e) {
            return [
                'error' => true,
                'mensaje' => 'Error al obtener estadísticas de sanciones: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene estadísticas de clientes para reportes
     * 
     * @param array $filtros Filtros como fecha_inicio, fecha_fin, cliente_id
     * @return array Estadísticas de clientes
     */
    public function obtenerEstadisticasClientes($filtros = []) {
        try {
            // Configurar fechas
            $fecha_inicio = isset($filtros['fecha_inicio']) ? $filtros['fecha_inicio'] . ' 00:00:00' : date('Y-m-d', strtotime('-30 days')) . ' 00:00:00';
            $fecha_fin = isset($filtros['fecha_fin']) ? $filtros['fecha_fin'] . ' 23:59:59' : date('Y-m-d') . ' 23:59:59';
            
            // Consulta principal para top clientes
            $sql = "
                SELECT 
                    c.id,
                    c.telefono,
                    c.nombre,
                    COUNT(s.id) as total_servicios,
                    SUM(CASE WHEN s.estado = 'finalizado' THEN 1 ELSE 0 END) as servicios_finalizados,
                    SUM(CASE WHEN s.estado = 'cancelado' THEN 1 ELSE 0 END) as servicios_cancelados,
                    COUNT(DISTINCT d.id) as total_direcciones
                FROM 
                    clientes c
                LEFT JOIN 
                    servicios s ON c.id = s.cliente_id 
                        AND s.fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin
                LEFT JOIN 
                    direcciones d ON c.id = d.cliente_id
                GROUP BY 
                    c.id, c.telefono, c.nombre
                ORDER BY 
                    total_servicios DESC
                LIMIT 50
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':fecha_inicio', $fecha_inicio, PDO::PARAM_STR);
            $stmt->bindParam(':fecha_fin', $fecha_fin, PDO::PARAM_STR);
            $stmt->execute();
            
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular totales
            $totales = [
                'total_servicios' => 0,
                'servicios_finalizados' => 0,
                'servicios_cancelados' => 0,
                'clientes_activos' => 0
            ];
            
            foreach ($clientes as $cliente) {
                $totales['total_servicios'] += $cliente['total_servicios'];
                $totales['servicios_finalizados'] += $cliente['servicios_finalizados'];
                $totales['servicios_cancelados'] += $cliente['servicios_cancelados'];
                if ($cliente['total_servicios'] > 0) {
                    $totales['clientes_activos']++;
                }
            }
            
            // Obtener estadísticas de direcciones
            $sql_direcciones = "
                SELECT 
                    COUNT(*) as total_direcciones,
                    SUM(CASE WHEN es_frecuente = 1 THEN 1 ELSE 0 END) as direcciones_frecuentes
                FROM 
                    direcciones
            ";
            
            $stmt = $this->pdo->prepare($sql_direcciones);
            $stmt->execute();
            
            $estadisticas_direcciones = $stmt->fetch(PDO::FETCH_ASSOC);
            $totales = array_merge($totales, $estadisticas_direcciones);
            
            // Cliente específico si se solicita
            $cliente_detalle = null;
            if (!empty($filtros['cliente_id'])) {
                $sql_cliente = "
                    SELECT 
                        c.*,
                        COUNT(s.id) as total_servicios,
                        SUM(CASE WHEN s.estado = 'finalizado' THEN 1 ELSE 0 END) as servicios_finalizados,
                        SUM(CASE WHEN s.estado = 'cancelado' THEN 1 ELSE 0 END) as servicios_cancelados,
                        MAX(s.fecha_solicitud) as ultimo_servicio,
                        COUNT(DISTINCT d.id) as total_direcciones
                    FROM 
                        clientes c
                    LEFT JOIN 
                        servicios s ON c.id = s.cliente_id AND s.fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin
                    LEFT JOIN 
                        direcciones d ON c.id = d.cliente_id
                    WHERE 
                        c.id = :cliente_id
                    GROUP BY 
                        c.id
                ";
                
                $stmt = $this->pdo->prepare($sql_cliente);
                $stmt->bindParam(':cliente_id', $filtros['cliente_id'], PDO::PARAM_INT);
                $stmt->bindParam(':fecha_inicio', $fecha_inicio, PDO::PARAM_STR);
                $stmt->bindParam(':fecha_fin', $fecha_fin, PDO::PARAM_STR);
                $stmt->execute();
                
                $cliente_detalle = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Obtener direcciones del cliente
                if ($cliente_detalle) {
                    $sql_dir = "
                        SELECT 
                            d.*,
                            COUNT(s.id) as total_servicios
                        FROM 
                            direcciones d
                        LEFT JOIN 
                            servicios s ON d.id = s.direccion_id AND s.fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin
                        WHERE 
                            d.cliente_id = :cliente_id
                        GROUP BY 
                            d.id
                        ORDER BY 
                            total_servicios DESC, d.ultimo_uso DESC
                    ";
                    
                    $stmt = $this->pdo->prepare($sql_dir);
                    $stmt->bindParam(':cliente_id', $filtros['cliente_id'], PDO::PARAM_INT);
                    $stmt->bindParam(':fecha_inicio', $fecha_inicio, PDO::PARAM_STR);
                    $stmt->bindParam(':fecha_fin', $fecha_fin, PDO::PARAM_STR);
                    $stmt->execute();
                    
                    $cliente_detalle['direcciones'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            
            return [
                'error' => false,
                'clientes' => $clientes,
                'totales' => $totales,
                'cliente_detalle' => $cliente_detalle,
                'filtros' => [
                    'fecha_inicio' => substr($fecha_inicio, 0, 10),
                    'fecha_fin' => substr($fecha_fin, 0, 10)
                ]
            ];
            
        } catch (PDOException $e) {
            return [
                'error' => true,
                'mensaje' => 'Error al obtener estadísticas de clientes: ' . $e->getMessage()
            ];
        }
    }
}