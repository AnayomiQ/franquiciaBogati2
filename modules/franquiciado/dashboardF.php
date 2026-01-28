<?php
require_once __DIR__ . '/../../config.php';      
require_once __DIR__ . '/../../includes/functions.php';  
require_once __DIR__ . '/../../db_connection.php';

// Mostrar errores mientras depuras
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Iniciar sesión
startSession();

// Verificar autenticación
requireAuth();

// Verificar que sea franquiciado
requireAnyRole(['franquiciado']);

// Obtener información del usuario
$userInfo = getCurrentUserInfo();
if (!$userInfo) {
    session_destroy();
    setFlashMessage('error', 'No se pudo cargar la información del usuario. Por favor, inicie sesión nuevamente.');
    redirect(BASE_URL . 'login.php');
}

// Obtener datos del usuario
$userName = getCurrentUserName();
$userRole = getCurrentUserRole();
$cedula_franquiciado = $_SESSION['cedula_franquiciado'] ?? $userInfo['cedula_franquiciado'] ?? null;

// Verificar si hay algún mensaje flash
if (isset($_GET['message'])) {
    $messageType = $_GET['message_type'] ?? 'info';
    $message = urldecode($_GET['message']);
    setFlashMessage($messageType, $message);
}

// Obtener conexión a la base de datos
$db = Database::getConnection();

try {
    // Obtener información del franquiciado
    $stmt = $db->prepare("
        SELECT 
            f.*,
            COUNT(l.codigo_local) as total_locales,
            SUM(CASE WHEN l.cedula_franquiciado IS NOT NULL THEN 1 ELSE 0 END) as locales_activos
        FROM franquiciados f
        LEFT JOIN locales l ON f.cedula = l.cedula_franquiciado
        WHERE f.cedula = ?
        GROUP BY f.cedula
    ");
    $stmt->execute([$cedula_franquiciado]);
    $franquiciado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$franquiciado) {
        throw new Exception("No se encontró información del franquiciado");
    }
    
    // Obtener locales del franquiciado
    $stmt = $db->prepare("
        SELECT 
            l.*,
            nf.nombre as nivel_nombre,
            nf.costo as nivel_costo,
            cf.numero_contrato,
            cf.estado as estado_contrato,
            cf.fecha_fin,
            DATEDIFF(cf.fecha_fin, CURDATE()) as dias_restantes_contrato
        FROM locales l
        LEFT JOIN nivel_franquicia nf ON l.id_nivel = nf.id_nivel
        LEFT JOIN contratos_franquicia cf ON l.codigo_local = cf.codigo_local
        WHERE l.cedula_franquiciado = ?
        ORDER BY l.fecha_apertura DESC
    ");
    $stmt->execute([$cedula_franquiciado]);
    $locales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener ventas totales del mes actual
    $currentMonth = date('Y-m');
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(v.total), 0) as ventas_mes,
            COALESCE(COUNT(v.id_venta), 0) as transacciones_mes,
            COALESCE(COUNT(DISTINCT v.id_cliente), 0) as clientes_mes,
            COALESCE(AVG(v.total), 0) as ticket_promedio
        FROM ventas v
        INNER JOIN locales l ON v.codigo_local = l.codigo_local
        WHERE l.cedula_franquiciado = ?
        AND DATE_FORMAT(v.fecha_venta, '%Y-%m') = ?
    ");
    $stmt->execute([$cedula_franquiciado, $currentMonth]);
    $ventas_mes = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener ventas del día
    $today = date('Y-m-d');
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(v.total), 0) as ventas_hoy,
            COALESCE(COUNT(v.id_venta), 0) as transacciones_hoy
        FROM ventas v
        INNER JOIN locales l ON v.codigo_local = l.codigo_local
        WHERE l.cedula_franquiciado = ?
        AND DATE(v.fecha_venta) = ?
    ");
    $stmt->execute([$cedula_franquiciado, $today]);
    $ventas_hoy = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener productos más vendidos este mes
    $stmt = $db->prepare("
        SELECT 
            p.nombre,
            p.codigo_producto,
            SUM(dv.cantidad) as total_vendido,
            SUM(dv.cantidad * dv.precio_unitario) as ingresos_generados
        FROM detalle_ventas dv
        INNER JOIN ventas v ON dv.id_venta = v.id_venta
        INNER JOIN locales l ON v.codigo_local = l.codigo_local
        INNER JOIN productos p ON dv.codigo_producto = p.codigo_producto
        WHERE l.cedula_franquiciado = ?
        AND DATE_FORMAT(v.fecha_venta, '%Y-%m') = ?
        GROUP BY p.codigo_producto
        ORDER BY total_vendido DESC
        LIMIT 5
    ");
    $stmt->execute([$cedula_franquiciado, $currentMonth]);
    $productos_top = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener inventario crítico (todos los locales)
    $stmt = $db->prepare("
        SELECT 
            i.codigo_local,
            i.codigo_producto,
            p.nombre as producto_nombre,
            l.nombre_local,
            i.cantidad,
            i.cantidad_minima,
            ROUND((i.cantidad / i.cantidad_minima) * 100) as porcentaje_stock
        FROM inventario i
        INNER JOIN productos p ON i.codigo_producto = p.codigo_producto
        INNER JOIN locales l ON i.codigo_local = l.codigo_local
        WHERE l.cedula_franquiciado = ?
        AND i.cantidad <= i.cantidad_minima
        ORDER BY i.cantidad ASC
        LIMIT 8
    ");
    $stmt->execute([$cedula_franquiciado]);
    $inventario_critico = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener pagos pendientes de royalty
    $stmt = $db->prepare("
        SELECT 
            pr.*,
            cf.numero_contrato,
            l.nombre_local,
            l.codigo_local
        FROM pagos_royalty pr
        INNER JOIN contratos_franquicia cf ON pr.id_contrato = cf.id_contrato
        INNER JOIN locales l ON cf.codigo_local = l.codigo_local
        WHERE l.cedula_franquiciado = ?
        AND pr.estado = 'PENDIENTE'
        ORDER BY pr.anio DESC, pr.mes DESC
        LIMIT 5
    ");
    $stmt->execute([$cedula_franquiciado]);
    $pagos_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener empleados activos
    $stmt = $db->prepare("
        SELECT 
            e.*,
            l.nombre_local,
            TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) as edad
        FROM empleados e
        INNER JOIN locales l ON e.codigo_local = l.codigo_local
        WHERE l.cedula_franquiciado = ?
        AND e.estado = 'ACTIVO'
        ORDER BY e.fecha_contratacion DESC
        LIMIT 6
    ");
    $stmt->execute([$cedula_franquiciado]);
    $empleados_activos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener campañas de marketing activas
    $stmt = $db->prepare("
        SELECT 
            mc.*,
            cl.codigo_local,
            cl.presupuesto_asignado
        FROM marketing_campanas mc
        INNER JOIN campana_local cl ON mc.id_campana = cl.id_campana
        INNER JOIN locales l ON cl.codigo_local = l.codigo_local
        WHERE l.cedula_franquiciado = ?
        AND mc.estado = 'ACTIVO'
        AND mc.fecha_fin >= CURDATE()
        ORDER BY mc.fecha_inicio DESC
        LIMIT 4
    ");
    $stmt->execute([$cedula_franquiciado]);
    $campanas_activas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular total de pagos pendientes
    $total_pagos_pendientes = 0;
    foreach ($pagos_pendientes as $pago) {
        $total_pagos_pendientes += ($pago['monto_royalty'] + $pago['monto_publicidad']);
    }
    
    // Estadísticas mapeadas
    $stats_mapped = [
        'locales_totales' => count($locales),
        'locales_activos' => $franquiciado['locales_activos'] ?? 0,
        'ventas_mes' => number_format($ventas_mes['ventas_mes'] ?? 0, 2),
        'transacciones_mes' => $ventas_mes['transacciones_mes'] ?? 0,
        'clientes_mes' => $ventas_mes['clientes_mes'] ?? 0,
        'ticket_promedio' => number_format($ventas_mes['ticket_promedio'] ?? 0, 2),
        'ventas_hoy' => number_format($ventas_hoy['ventas_hoy'] ?? 0, 2),
        'transacciones_hoy' => $ventas_hoy['transacciones_hoy'] ?? 0,
        'pagos_pendientes' => number_format($total_pagos_pendientes, 2),
        'total_pagos' => count($pagos_pendientes),
        'empleados_activos' => count($empleados_activos),
        'inventario_critico' => count($inventario_critico),
        'campanas_activas' => count($campanas_activas)
    ];
    
} catch (PDOException $e) {
    error_log('Error cargando datos del dashboard franquiciado: ' . $e->getMessage());
    
    // Inicializar variables vacías
    $franquiciado = [];
    $locales = [];
    $productos_top = [];
    $inventario_critico = [];
    $pagos_pendientes = [];
    $empleados_activos = [];
    $campanas_activas = [];
    $stats_mapped = [
        'locales_totales' => 0,
        'locales_activos' => 0,
        'ventas_mes' => '0.00',
        'transacciones_mes' => 0,
        'clientes_mes' => 0,
        'ticket_promedio' => '0.00',
        'ventas_hoy' => '0.00',
        'transacciones_hoy' => 0,
        'pagos_pendientes' => '0.00',
        'total_pagos' => 0,
        'empleados_activos' => 0,
        'inventario_critico' => 0,
        'campanas_activas' => 0
    ];
}

// Configurar título de la página
$pageTitle = APP_NAME . ' - Panel del Franquiciado';

// CSS específico
$pageStyles = ['dashboard.css'];

// Incluir header
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- =========================================================================== -->
<!-- ESTILOS INLINE CON PALETA CAFÉ/NARANJA/MARRÓN PIEL -->
<!-- =========================================================================== -->
<style>
    :root {
        /* Paleta café/naranja/marrón piel */
        --cafe-oscuro: #8B4513;
        --cafe-medio: #A0522D;
        --cafe-claro: #D2B48C;
        --naranja-oscuro: #FF8C00;
        --naranja-medio: #FFA500;
        --naranja-claro: #FFB74D;
        --piel-oscuro: #E6BE8A;
        --piel-medio: #F5DEB3;
        --piel-claro: #FAEBD7;
        --crema: #FFF8DC;
        --marron-chocolate: #7B3F00;
        
        /* Gradientes específicos para franquiciado */
        --franchise-primary: linear-gradient(135deg, var(--cafe-oscuro) 0%, var(--naranja-oscuro) 100%);
        --franchise-secondary: linear-gradient(135deg, var(--piel-oscuro) 0%, var(--naranja-claro) 100%);
        --franchise-success: linear-gradient(135deg, #8FBC8F 0%, #3CB371 100%);
        --franchise-warning: linear-gradient(135deg, var(--naranja-medio) 0%, #FFD700 100%);
        --franchise-danger: linear-gradient(135deg, #CD5C5C 0%, #B22222 100%);
        --franchise-info: linear-gradient(135deg, #87CEEB 0%, #4682B4 100%);
        --franchise-purple: linear-gradient(135deg, #7f00ff 0%, #e100ff 100%);
    }

    .dashboard-content {
        padding: 20px;
        background: linear-gradient(135deg, var(--piel-claro) 0%, var(--crema) 100%);
        min-height: calc(100vh - 70px);
        position: relative;
        overflow-x: hidden;
    }

    .dashboard-content::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 300px;
        background: var(--franchise-primary);
        z-index: 0;
        opacity: 0.08;
    }

    /* HEADER FRANQUICIADO */
    .franchise-header {
        background: linear-gradient(135deg, rgba(139, 69, 19, 0.1) 0%, rgba(245, 222, 179, 0.05) 100%);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        color: var(--marron-chocolate);
        padding: 30px;
        border-radius: 20px;
        margin-bottom: 30px;
        box-shadow: 0 15px 50px rgba(139, 69, 19, 0.15);
        position: relative;
        overflow: hidden;
    }

    .franchise-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path fill="%23A0522D" opacity="0.05" d="M0,0 L100,0 L100,100 Z"></path></svg>');
        background-size: cover;
    }

    .franchise-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }

    .franchise-details {
        flex: 1;
    }

    .franchise-details h2 {
        font-size: 2.2rem;
        font-weight: 800;
        margin-bottom: 10px;
        background: var(--franchise-primary);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
    }

    .franchise-details p {
        font-size: 1.1rem;
        opacity: 0.9;
        margin-bottom: 15px;
        color: var(--cafe-medio);
    }

    .franchise-stats {
        display: flex;
        gap: 20px;
        margin-top: 20px;
        flex-wrap: wrap;
    }

    .franchise-stat {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 20px;
        background: rgba(139, 69, 19, 0.1);
        border-radius: 10px;
        border: 1px solid rgba(210, 180, 140, 0.3);
    }

    .franchise-stat i {
        color: var(--cafe-oscuro);
        font-size: 1.2rem;
    }

    .franchise-stat span {
        font-weight: 600;
        color: var(--cafe-oscuro);
    }

    .franchise-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: var(--franchise-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2.5rem;
        font-weight: 700;
        border: 5px solid white;
        box-shadow: 0 10px 30px rgba(139, 69, 19, 0.3);
    }

    /* ESTADÍSTICAS PRINCIPALES */
    .franchise-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .franchise-stat-card {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(250, 235, 215, 0.8) 100%);
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 8px 30px rgba(139, 69, 19, 0.08);
        border: 1px solid var(--piel-medio);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        cursor: pointer;
    }

    .franchise-stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(139, 69, 19, 0.15);
        border-color: var(--naranja-claro);
    }

    .stat-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
    }

    .stat-card-icon {
        width: 60px;
        height: 60px;
        border-radius: 15px;
        background: var(--franchise-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        box-shadow: 0 5px 15px rgba(139, 69, 19, 0.2);
    }

    .stat-card-value {
        font-size: 2.5rem;
        font-weight: 800;
        margin: 10px 0;
        background: var(--franchise-primary);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        line-height: 1;
    }

    .stat-card-label {
        font-size: 0.9rem;
        color: var(--cafe-medio);
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .stat-card-subtitle {
        font-size: 0.85rem;
        color: var(--cafe-claro);
    }

    .stat-card-progress {
        height: 6px;
        background: rgba(210, 180, 140, 0.3);
        border-radius: 3px;
        margin-top: 15px;
        overflow: hidden;
    }

    .stat-card-progress-bar {
        height: 100%;
        background: var(--franchise-primary);
        border-radius: 3px;
        transition: width 1s ease;
    }

    /* SECCIONES DE CONTENIDO */
    .franchise-sections {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .franchise-section-card {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 8px 30px rgba(139, 69, 19, 0.08);
        transition: transform 0.3s ease;
        border: 1px solid var(--piel-medio);
    }

    .franchise-section-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(139, 69, 19, 0.15);
        border-color: var(--cafe-claro);
    }

    .section-card-header {
        padding: 20px;
        background: linear-gradient(135deg, var(--piel-claro) 0%, var(--crema) 100%);
        border-bottom: 1px solid var(--piel-medio);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .section-card-header h5 {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--marron-chocolate);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-card-header h5 i {
        color: var(--cafe-oscuro);
    }

    .section-card-body {
        padding: 20px;
    }

    /* LOCALES */
    .locales-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }

    .local-card {
        background: linear-gradient(135deg, #ffffff 0%, var(--piel-claro) 100%);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 5px 20px rgba(139, 69, 19, 0.05);
        transition: all 0.3s ease;
        border: 1px solid var(--piel-medio);
        position: relative;
        overflow: hidden;
    }

    .local-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(139, 69, 19, 0.15);
        border-color: var(--naranja-claro);
    }

    .local-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--franchise-primary);
    }

    .local-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }

    .local-name {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--marron-chocolate);
        margin: 0;
    }

    .local-status {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        background: rgba(143, 188, 143, 0.2);
        color: #3CB371;
    }

    .local-details {
        font-size: 0.85rem;
        color: var(--cafe-medio);
        margin-bottom: 15px;
    }

    .local-details i {
        width: 16px;
        text-align: center;
        margin-right: 5px;
        color: var(--cafe-claro);
    }

    .local-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 15px;
        border-top: 1px solid var(--piel-medio);
        font-size: 0.8rem;
        color: var(--cafe-claro);
    }

    /* PRODUCTOS TOP */
    .product-top-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        margin-bottom: 10px;
        background: rgba(255, 165, 0, 0.1);
        border-radius: 10px;
        border-left: 4px solid var(--naranja-medio);
        transition: all 0.3s ease;
    }

    .product-top-item:hover {
        background: rgba(255, 165, 0, 0.2);
        transform: translateX(5px);
    }

    .product-top-rank {
        font-size: 1.2rem;
        font-weight: 800;
        color: var(--cafe-oscuro);
        min-width: 30px;
    }

    .product-top-info {
        flex: 1;
        margin: 0 15px;
    }

    .product-top-info h6 {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--marron-chocolate);
    }

    .product-top-meta {
        font-size: 0.8rem;
        color: var(--cafe-claro);
    }

    .product-top-sales {
        text-align: right;
        font-weight: 700;
        color: var(--cafe-oscuro);
    }

    /* INVENTARIO CRÍTICO */
    .inventory-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        margin-bottom: 10px;
        background: rgba(205, 92, 92, 0.1);
        border-radius: 10px;
        border-left: 4px solid var(--franchise-danger);
        transition: all 0.3s ease;
    }

    .inventory-item:hover {
        background: rgba(205, 92, 92, 0.2);
        transform: translateX(5px);
    }

    .inventory-info h6 {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--marron-chocolate);
    }

    .inventory-local {
        font-size: 0.8rem;
        color: var(--cafe-claro);
    }

    .inventory-stock {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .stock-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 700;
        background: var(--franchise-danger);
        color: white;
        animation: pulse 2s infinite;
    }

    /* PAGOS PENDIENTES */
    .payment-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        margin-bottom: 10px;
        background: rgba(255, 0, 0, 0.05);
        border-radius: 10px;
        border-left: 4px solid #FF4444;
        transition: all 0.3s ease;
    }

    .payment-item:hover {
        background: rgba(255, 0, 0, 0.1);
        transform: translateX(5px);
    }

    .payment-info h6 {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--marron-chocolate);
    }

    .payment-period {
        font-size: 0.8rem;
        color: var(--cafe-claro);
    }

    .payment-amount {
        text-align: right;
    }

    .payment-total {
        font-weight: 700;
        color: #FF4444;
        font-size: 1.1rem;
    }

    .payment-detail {
        font-size: 0.8rem;
        color: var(--cafe-claro);
    }

    /* EMPLEADOS */
    .employees-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
    }

    .employee-card {
        background: linear-gradient(135deg, #ffffff 0%, var(--piel-claro) 100%);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 5px 20px rgba(139, 69, 19, 0.05);
        transition: all 0.3s ease;
        border: 1px solid var(--piel-medio);
    }

    .employee-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(139, 69, 19, 0.15);
        border-color: var(--naranja-claro);
    }

    .employee-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: var(--franchise-secondary);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--marron-chocolate);
        font-weight: 700;
        font-size: 1.5rem;
        margin: 0 auto 15px;
        border: 3px solid white;
        box-shadow: 0 5px 15px rgba(139, 69, 19, 0.2);
    }

    .employee-info h6 {
        margin: 0 0 5px 0;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--marron-chocolate);
    }

    .employee-role {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 15px;
        font-size: 0.75rem;
        font-weight: 700;
        background: rgba(139, 69, 19, 0.1);
        color: var(--cafe-oscuro);
        margin-bottom: 10px;
    }

    .employee-local {
        font-size: 0.8rem;
        color: var(--cafe-claro);
    }

    /* CAMPAÑAS ACTIVAS */
    .campaign-item {
        padding: 15px;
        margin-bottom: 10px;
        background: rgba(135, 206, 235, 0.1);
        border-radius: 10px;
        border-left: 4px solid #87CEEB;
        transition: all 0.3s ease;
    }

    .campaign-item:hover {
        background: rgba(135, 206, 235, 0.2);
        transform: translateX(5px);
    }

    .campaign-info h6 {
        margin: 0 0 10px 0;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--marron-chocolate);
    }

    .campaign-meta {
        display: flex;
        justify-content: space-between;
        font-size: 0.8rem;
        color: var(--cafe-claro);
    }

    .campaign-dates {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .campaign-budget {
        font-weight: 600;
        color: var(--cafe-oscuro);
    }

    /* ACCIONES RÁPIDAS */
    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-top: 25px;
    }

    .quick-action-btn {
        background: white;
        border: 2px solid var(--piel-medio);
        border-radius: 12px;
        padding: 20px 15px;
        text-align: center;
        color: var(--cafe-medio);
        text-decoration: none;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }

    .quick-action-btn:hover {
        border-color: var(--cafe-oscuro);
        background: rgba(139, 69, 19, 0.05);
        transform: translateY(-3px);
        color: var(--cafe-oscuro);
        text-decoration: none;
        box-shadow: 0 10px 25px rgba(139, 69, 19, 0.15);
    }

    .quick-action-btn i {
        font-size: 2rem;
        background: var(--franchise-primary);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .quick-action-btn span {
        font-size: 0.9rem;
        font-weight: 600;
    }

    /* BOTONES Y BADGES */
    .btn-sm {
        font-weight: 600;
        border-radius: 8px;
    }

    .btn-warning {
        background: var(--franchise-warning);
        border-color: var(--naranja-medio);
        color: var(--marron-chocolate);
    }

    .btn-primary {
        background: var(--franchise-primary);
        border-color: var(--cafe-oscuro);
        color: white;
    }

    .btn-success {
        background: var(--franchise-success);
        border-color: #3CB371;
        color: white;
    }

    .btn-info {
        background: var(--franchise-info);
        border-color: #4682B4;
        color: white;
    }

    .btn-outline-primary {
        border-color: var(--cafe-oscuro);
        color: var(--cafe-oscuro);
    }

    .btn-outline-primary:hover {
        background: var(--cafe-oscuro);
        border-color: var(--cafe-oscuro);
        color: white;
    }

    .badge.bg-warning {
        background: var(--franchise-warning) !important;
        color: var(--marron-chocolate);
    }

    .badge.bg-info {
        background: var(--franchise-info) !important;
        color: white;
    }

    .badge.bg-success {
        background: var(--franchise-success) !important;
        color: white;
    }

    .badge.bg-danger {
        background: var(--franchise-danger) !important;
        color: white;
    }

    /* RESPONSIVE */
    @media (max-width: 768px) {
        .franchise-info {
            flex-direction: column;
            text-align: center;
        }

        .franchise-avatar {
            width: 100px;
            height: 100px;
            font-size: 2rem;
        }

        .franchise-stats-grid {
            grid-template-columns: 1fr;
        }

        .franchise-sections {
            grid-template-columns: 1fr;
        }

        .locales-grid {
            grid-template-columns: 1fr;
        }

        .employees-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .quick-actions-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 576px) {
        .employees-grid {
            grid-template-columns: 1fr;
        }

        .quick-actions-grid {
            grid-template-columns: 1fr;
        }

        .franchise-stats {
            flex-direction: column;
        }
    }

    /* ANIMACIONES */
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.7; }
        100% { opacity: 1; }
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .fade-in {
        animation: fadeIn 0.5s ease forwards;
    }
</style>

<!-- =========================================================================== -->
<!-- CONTENIDO PRINCIPAL DEL DASHBOARD FRANQUICIADO -->
<!-- =========================================================================== -->
<div class="dashboard-content">

    <!-- ENCABEZADO FRANQUICIADO -->
    <div class="franchise-header">
        <div class="franchise-info">
            <div class="franchise-details">
                <h2><i class="fas fa-crown me-2"></i>Bienvenido, <?php echo htmlspecialchars($userName); ?></h2>
                <p>
                    Franquiciado desde <?php echo date('d/m/Y', strtotime($franquiciado['fecha_registro'] ?? date('Y-m-d'))); ?> |
                    Estado: <span class="badge bg-<?php echo $franquiciado['estado'] == 'ACTIVO' ? 'success' : 'warning'; ?>">
                        <?php echo htmlspecialchars($franquiciado['estado'] ?? 'PROSPECTO'); ?>
                    </span>
                </p>
                <div class="franchise-stats">
                    <div class="franchise-stat">
                        <i class="fas fa-store"></i>
                        <span><?php echo $stats_mapped['locales_totales']; ?> Locales</span>
                    </div>
                    <div class="franchise-stat">
                        <i class="fas fa-chart-line"></i>
                        <span>$<?php echo $stats_mapped['ventas_mes']; ?> este mes</span>
                    </div>
                    <div class="franchise-stat">
                        <i class="fas fa-users"></i>
                        <span><?php echo $stats_mapped['empleados_activos']; ?> Empleados</span>
                    </div>
                </div>
            </div>
            <div class="franchise-avatar">
                <?php echo strtoupper(substr($userName, 0, 1)); ?>
            </div>
        </div>
    </div>

    <!-- ESTADÍSTICAS PRINCIPALES -->
    <div class="franchise-stats-grid">
        <div class="franchise-stat-card" onclick="window.location.href='ventas.php'">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-label">VENTAS DEL MES</div>
                    <div class="stat-card-value">$<?php echo $stats_mapped['ventas_mes']; ?></div>
                    <div class="stat-card-subtitle"><?php echo $stats_mapped['transacciones_mes']; ?> transacciones</div>
                </div>
                <div class="stat-card-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
            </div>
            <div class="stat-card-progress">
                <div class="stat-card-progress-bar" style="width: <?php echo min(($stats_mapped['transacciones_mes'] / 100) * 100, 100); ?>%"></div>
            </div>
        </div>

        <div class="franchise-stat-card" onclick="window.location.href='clientes.php'">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-label">CLIENTES ATENDIDOS</div>
                    <div class="stat-card-value"><?php echo $stats_mapped['clientes_mes']; ?></div>
                    <div class="stat-card-subtitle">Este mes</div>
                </div>
                <div class="stat-card-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div class="stat-card-progress">
                <div class="stat-card-progress-bar" style="width: <?php echo min(($stats_mapped['clientes_mes'] / 50) * 100, 100); ?>%"></div>
            </div>
        </div>

        <div class="franchise-stat-card" onclick="window.location.href='pagos.php'">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-label">PAGOS PENDIENTES</div>
                    <div class="stat-card-value">$<?php echo $stats_mapped['pagos_pendientes']; ?></div>
                    <div class="stat-card-subtitle"><?php echo $stats_mapped['total_pagos']; ?> facturas</div>
                </div>
                <div class="stat-card-icon" style="background: var(--franchise-danger);">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
            <div class="stat-card-progress">
                <div class="stat-card-progress-bar" style="width: <?php echo min($stats_mapped['total_pagos'] * 20, 100); ?>%; background: var(--franchise-danger);"></div>
            </div>
        </div>
    </div>

    <!-- SECCIONES DE CONTENIDO -->
    <div class="franchise-sections">
        <!-- MIS LOCALES -->
        <div class="franchise-section-card">
            <div class="section-card-header">
                <h5><i class="fas fa-store"></i> Mis Locales</h5>
                <a href="localesF.php" class="btn btn-sm btn-outline-primary">
                    Ver todos
                </a>
            </div>
            <div class="section-card-body">
                <?php if (!empty($locales)): ?>
                    <div class="locales-grid">
                        <?php foreach ($locales as $local): ?>
                            <div class="local-card" onclick="window.location.href='local_detalle.php?codigo=<?php echo $local['codigo_local']; ?>'">
                                <div class="local-header">
                                    <h6 class="local-name"><?php echo htmlspecialchars($local['nombre_local']); ?></h6>
                                    <span class="local-status"><?php echo $local['estado_contrato'] ?? 'SIN CONTRATO'; ?></span>
                                </div>
                                <div class="local-details">
                                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($local['ciudad'] . ', ' . $local['provincia']); ?></p>
                                    <p><i class="fas fa-calendar-alt"></i> Abierto: <?php echo date('d/m/Y', strtotime($local['fecha_apertura'])); ?></p>
                                </div>
                                <div class="local-footer">
                                    <span><i class="fas fa-tag"></i> <?php echo $local['nivel_nombre'] ?? 'SIN NIVEL'; ?></span>
                                    <span>
                                        <?php if (isset($local['dias_restantes_contrato']) && $local['dias_restantes_contrato'] > 0): ?>
                                            <i class="fas fa-clock"></i> <?php echo $local['dias_restantes_contrato']; ?> días
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-store fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No tienes locales registrados</p>
                        <a href="nuevo_local.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> Registrar Local
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- PRODUCTOS MÁS VENDIDOS -->
        <div class="franchise-section-card">
            <div class="section-card-header">
                <h5><i class="fas fa-star"></i> Productos Top del Mes</h5>
                <a href="productos.php" class="btn btn-sm btn-warning">
                    Ver todos
                </a>
            </div>
            <div class="section-card-body">
                <?php if (!empty($productos_top)): ?>
                    <?php $rank = 1; ?>
                    <?php foreach ($productos_top as $producto): ?>
                        <div class="product-top-item">
                            <div class="product-top-rank">#<?php echo $rank++; ?></div>
                            <div class="product-top-info">
                                <h6><?php echo htmlspecialchars($producto['nombre']); ?></h6>
                                <div class="product-top-meta">Código: <?php echo htmlspecialchars($producto['codigo_producto']); ?></div>
                            </div>
                            <div class="product-top-sales">
                                <div><?php echo number_format($producto['total_vendido']); ?> unidades</div>
                                <small class="text-muted">$<?php echo number_format($producto['ingresos_generados'], 2); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No hay ventas registradas este mes</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- PAGOS PENDIENTES -->
        <div class="franchise-section-card">
            <div class="section-card-header">
                <h5><i class="fas fa-file-invoice-dollar"></i> Pagos Pendientes</h5>
                <a href="pagosF.php" class="btn btn-sm btn-danger">
                    Pagar ahora
                </a>
            </div>
            <div class="section-card-body">
                <?php if (!empty($pagos_pendientes)): ?>
                    <?php foreach ($pagos_pendientes as $pago): ?>
                        <div class="payment-item" onclick="window.location.href='detalle_pago.php?id=<?php echo $pago['id_pago']; ?>'">
                            <div class="payment-info">
                                <h6>Contrato <?php echo htmlspecialchars($pago['numero_contrato']); ?></h6>
                                <div class="payment-period"><?php echo $pago['mes']; ?>/<?php echo $pago['anio']; ?> - <?php echo htmlspecialchars($pago['nombre_local']); ?></div>
                            </div>
                            <div class="payment-amount">
                                <div class="payment-total">$<?php echo number_format($pago['monto_royalty'] + $pago['monto_publicidad'], 2); ?></div>
                                <div class="payment-detail">
                                    Royalty: $<?php echo number_format($pago['monto_royalty'], 2); ?> | 
                                    Pub: $<?php echo number_format($pago['monto_publicidad'], 2); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <p class="text-muted">No hay pagos pendientes</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- EMPLEADOS ACTIVOS -->
        <div class="franchise-section-card">
            <div class="section-card-header">
                <h5><i class="fas fa-user-tie"></i> Empleados Activos</h5>
                <a href="empleadosF.php" class="btn btn-sm btn-info">
                    Gestionar
                </a>
            </div>
            <div class="section-card-body">
                <?php if (!empty($empleados_activos)): ?>
                    <div class="employees-grid">
                        <?php foreach ($empleados_activos as $empleado): ?>
                            <div class="employee-card" onclick="window.location.href='detalle_empleado.php?id=<?php echo $empleado['id_empleado']; ?>'">
                                <div class="employee-avatar">
                                    <?php echo strtoupper(substr($empleado['nombres'], 0, 1)); ?>
                                </div>
                                <div class="employee-info">
                                    <h6><?php echo htmlspecialchars($empleado['nombres'] . ' ' . $empleado['apellidos']); ?></h6>
                                    <span class="employee-role"><?php echo htmlspecialchars($empleado['cargo']); ?></span>
                                    <div class="employee-local">
                                        <i class="fas fa-store"></i> <?php echo htmlspecialchars($empleado['nombre_local']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No hay empleados activos</p>
                        <a href="nuevo_empleado.php" class="btn btn-outline-primary">
                            <i class="fas fa-plus me-1"></i> Contratar
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- CAMPAÑAS ACTIVAS -->
        <div class="franchise-section-card">
            <div class="section-card-header">
                <h5><i class="fas fa-bullhorn"></i> Campañas Activas</h5>
                <span class="badge bg-info"><?php echo $stats_mapped['campanas_activas']; ?> activas</span>
            </div>
            <div class="section-card-body">
                <?php if (!empty($campanas_activas)): ?>
                    <?php foreach ($campanas_activas as $campana): ?>
                        <div class="campaign-item" onclick="window.location.href='detalle_campana.php?id=<?php echo $campana['id_campana']; ?>'">
                            <h6><?php echo htmlspecialchars($campana['nombre']); ?></h6>
                            <div class="campaign-meta">
                                <div class="campaign-dates">
                                    <span><i class="far fa-calendar-alt"></i> <?php echo date('d/m', strtotime($campana['fecha_inicio'])); ?> - <?php echo date('d/m', strtotime($campana['fecha_fin'])); ?></span>
                                </div>
                                <div class="campaign-budget">
                                    $<?php echo number_format($campana['presupuesto_asignado'] ?? 0, 2); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No hay campañas activas</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<!-- =========================================================================== -->
<!-- SCRIPTS JAVASCRIPT -->
<!-- =========================================================================== -->
<script>
    /**
     * Animación de entrada para elementos
     */
    document.addEventListener('DOMContentLoaded', function() {
        // Efecto fade in para las cards
        const cards = document.querySelectorAll('.franchise-stat-card, .franchise-section-card, .local-card, .employee-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.classList.add('fade-in');
            
            setTimeout(() => {
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });

        // Inicializar tooltips de Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Auto-refresh de estadísticas cada 5 minutos
        setInterval(refreshStats, 5 * 60 * 1000);
    });

    /**
     * Solicitar pedido de producto
     */
    function solicitarPedido(codigoProducto, codigoLocal) {
        if (confirm('¿Solicitar pedido de este producto?')) {
            fetch('../../includes/solicitar_pedido.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    codigo_producto: codigoProducto,
                    codigo_local: codigoLocal,
                    franquiciado_cedula: '<?php echo $cedula_franquiciado; ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Pedido solicitado exitosamente');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al procesar la solicitud');
            });
        }
    }

    /**
     * Refrescar estadísticas
     */
    function refreshStats() {
        fetch('../../includes/update_franchise_stats.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Actualizar contadores si es necesario
                    console.log('Estadísticas actualizadas');
                }
            })
            .catch(error => console.log('Error actualizando stats:', error));
    }

    /**
     * Redirigir a sección específica
     */
    function goToSection(section) {
        window.location.href = section + '.php';
    }

    /**
     * Mostrar notificación de actualización
     */
    function showUpdateNotification() {
        const hora = new Date().getHours();
        let mensaje = '';
        
        if (hora < 12) mensaje = '¡Buenos días!';
        else if (hora < 18) mensaje = '¡Buenas tardes!';
        else mensaje = '¡Buenas noches!';
        
        if (Notification.permission === 'granted') {
            new Notification(mensaje, {
                body: 'Panel de franquiciado actualizado',
                icon: '../../assets/img/logo.png'
            });
        }
    }

    // Solicitar permisos para notificaciones
    if (Notification.permission === 'default') {
        Notification.requestPermission();
    }

    // Mostrar notificación después de 5 segundos
    setTimeout(showUpdateNotification, 5000);
</script>

<?php
// ============================================================================
// INCLUIR FOOTER
// ============================================================================
require_once __DIR__ . '/../../includes/footer.php';
?>