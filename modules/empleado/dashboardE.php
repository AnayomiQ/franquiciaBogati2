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

// Verificar que sea empleado
requireAnyRole(['empleado']);

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

// USAR LOS MISMOS NOMBRES QUE EL LOGIN
$empleado_id = $_SESSION['id_empleado'] ?? null;
$local_codigo = $_SESSION['codigo_local'] ?? null;

// Verificar si hay algún mensaje flash
if (isset($_GET['message'])) {
    $messageType = $_GET['message_type'] ?? 'info';
    $message = urldecode($_GET['message']);
    setFlashMessage($messageType, $message);
}

// Obtener conexión a la base de datos
$db = Database::getConnection();

try {
    // Obtener información detallada del empleado
    $stmt = $db->prepare("
        SELECT 
            e.*, 
            l.nombre_local, 
            l.direccion, 
            l.ciudad, 
            l.provincia,
            l.telefono as telefono_local,
            l.codigo_local,
            f.nombres as franquiciado_nombres,
            f.apellidos as franquiciado_apellidos
        FROM empleados e
        LEFT JOIN locales l ON e.codigo_local = l.codigo_local
        LEFT JOIN franquiciados f ON l.cedula_franquiciado = f.cedula
        WHERE e.id_empleado = ?
    ");
    $stmt->execute([$empleado_id]);
    $empleado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Asegurar que local_codigo esté definido
    if (!$local_codigo && isset($empleado['codigo_local'])) {
        $local_codigo = $empleado['codigo_local'];
        $_SESSION['local_codigo'] = $local_codigo;
    }
    
    // Si no hay local_codigo, mostrar error
    if (!$local_codigo) {
        throw new Exception("No se encontró el código del local asignado");
    }
    
    // Obtener estadísticas del día
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as ventas_hoy,
            COALESCE(SUM(total), 0) as total_hoy,
            COALESCE(AVG(total), 0) as promedio_venta,
            COUNT(DISTINCT id_cliente) as clientes_hoy
        FROM ventas 
        WHERE codigo_local = ? 
        AND DATE(fecha_venta) = CURDATE()
    ");
    $stmt->execute([$local_codigo]);
    $estadisticas_hoy = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener productos bajos en stock
    $stmt = $db->prepare("
        SELECT 
            i.*,
            p.nombre as producto_nombre,
            p.precio,
            c.nombre as categoria_nombre,
            ROUND((i.cantidad / i.cantidad_minima) * 100) as porcentaje_stock
        FROM inventario i
        INNER JOIN productos p ON i.codigo_producto = p.codigo_producto
        LEFT JOIN categorias_productos c ON p.id_categoria = c.id_categoria
        WHERE i.codigo_local = ?
        AND (i.cantidad <= i.cantidad_minima OR i.necesita_reabastecer = 1)
        ORDER BY i.cantidad ASC
        LIMIT 10
    ");
    $stmt->execute([$local_codigo]);
    $productos_bajos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener turno actual
    $dia_semana = date('l');
    $dias_espanol = [
        'Monday' => 'LUNES',
        'Tuesday' => 'MARTES',
        'Wednesday' => 'MIERCOLES',
        'Thursday' => 'JUEVES',
        'Friday' => 'VIERNES',
        'Saturday' => 'SABADO',
        'Sunday' => 'DOMINGO'
    ];
    $dia_actual = $dias_espanol[$dia_semana] ?? 'LUNES';
    
    $stmt = $db->prepare("
        SELECT * FROM turnos_empleados 
        WHERE id_empleado = ? 
        AND dia_semana = ?
        ORDER BY hora_entrada ASC
    ");
    $stmt->execute([$empleado_id, $dia_actual]);
    $turno_actual = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener últimos clientes registrados en el local
    $stmt = $db->prepare("
        SELECT 
            c.*,
            COUNT(v.id_venta) as total_compras,
            MAX(v.fecha_venta) as ultima_compra
        FROM clientes c
        LEFT JOIN ventas v ON c.id_cliente = v.id_cliente 
            AND v.codigo_local = ?
        WHERE v.codigo_local = ?
        GROUP BY c.id_cliente
        ORDER BY ultima_compra DESC
        LIMIT 5
    ");
    $stmt->execute([$local_codigo, $local_codigo]);
    $ultimos_clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener ventas recientes
    $stmt = $db->prepare("
        SELECT 
            v.*,
            CONCAT(c.nombres, ' ', c.apellidos) as cliente_nombre,
            c.cedula as cliente_cedula,
            COUNT(dv.id_detalle) as items_comprados
        FROM ventas v
        LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
        LEFT JOIN detalle_ventas dv ON v.id_venta = dv.id_venta
        WHERE v.codigo_local = ?
        ORDER BY v.fecha_venta DESC
        LIMIT 8
    ");
    $stmt->execute([$local_codigo]);
    $ventas_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas mapeadas para uso fácil
    $stats_mapped = [
        'ventas_hoy' => $estadisticas_hoy['ventas_hoy'] ?? 0,
        'total_hoy' => number_format($estadisticas_hoy['total_hoy'] ?? 0, 2),
        'promedio_venta' => number_format($estadisticas_hoy['promedio_venta'] ?? 0, 2),
        'clientes_hoy' => $estadisticas_hoy['clientes_hoy'] ?? 0,
        'productos_bajos' => count($productos_bajos)
    ];
    
} catch (PDOException $e) {
    error_log('Error cargando datos del dashboard empleado: ' . $e->getMessage());
    
    // Inicializar variables vacías en caso de error
    $empleado = [];
    $productos_bajos = [];
    $turno_actual = [];
    $ultimos_clientes = [];
    $ventas_recientes = [];
    $stats_mapped = [
        'ventas_hoy' => 0,
        'total_hoy' => '0.00',
        'promedio_venta' => '0.00',
        'clientes_hoy' => 0,
        'productos_bajos' => 0
    ];
}

// Configurar título de la página
$pageTitle = APP_NAME . ' - Panel del Empleado';

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
        
        /* Gradientes para empleado */
        --employee-primary: linear-gradient(135deg, var(--cafe-oscuro) 0%, var(--naranja-oscuro) 100%);
        --employee-secondary: linear-gradient(135deg, var(--piel-oscuro) 0%, var(--naranja-claro) 100%);
        --employee-success: linear-gradient(135deg, #8FBC8F 0%, #3CB371 100%);
        --employee-warning: linear-gradient(135deg, var(--naranja-medio) 0%, #FFD700 100%);
        --employee-danger: linear-gradient(135deg, #CD5C5C 0%, #B22222 100%);
        --employee-info: linear-gradient(135deg, #87CEEB 0%, #4682B4 100%);
        --glass-bg: rgba(139, 69, 19, 0.08);
        --glass-border: rgba(210, 180, 140, 0.3);
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
        background: var(--employee-primary);
        z-index: 0;
        opacity: 0.08;
    }

    /* HEADER BIENVENIDA EMPLEADO CON ESTILO CAFÉ */
    .welcome-section-employee {
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

    .welcome-section-employee::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path fill="%23A0522D" opacity="0.05" d="M0,0 L100,0 L100,100 Z"></path></svg>');
        background-size: cover;
    }

    .employee-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }

    .employee-info {
        flex: 1;
    }

    .employee-info h2 {
        font-size: 2.2rem;
        font-weight: 800;
        margin-bottom: 10px;
        background: var(--employee-primary);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
    }

    .employee-info p {
        font-size: 1.1rem;
        opacity: 0.9;
        margin-bottom: 15px;
        color: var(--cafe-medio);
    }

    .employee-local {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 8px 20px;
        background: rgba(139, 69, 19, 0.1);
        border-radius: 50px;
        border: 2px solid rgba(210, 180, 140, 0.3);
        font-weight: 600;
        color: var(--cafe-oscuro);
    }

    .employee-meta .badge {
        font-weight: 600;
    }

    .employee-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: var(--employee-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 3rem;
        font-weight: 700;
        border: 5px solid white;
        box-shadow: 0 10px 30px rgba(139, 69, 19, 0.3);
    }

    /* ESTADÍSTICAS DEL DÍA */
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .day-stat-card {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(250, 235, 215, 0.8) 100%);
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 8px 30px rgba(139, 69, 19, 0.08);
        border: 1px solid var(--piel-medio);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        border-top: 4px solid transparent;
        cursor: pointer;
    }

    .day-stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(139, 69, 19, 0.15);
        border-color: var(--naranja-claro);
    }

    .day-stat-card.sales {
        border-top-color: var(--cafe-oscuro);
    }

    .day-stat-card.revenue {
        border-top-color: var(--naranja-medio);
    }

    .day-stat-card.customers {
        border-top-color: var(--employee-success);
    }

    .day-stat-card.average {
        border-top-color: #4682B4;
    }

    .stat-value-large {
        font-size: 2.5rem;
        font-weight: 800;
        margin: 10px 0;
        line-height: 1;
        background: var(--employee-primary);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .stat-label {
        font-size: 0.9rem;
        color: var(--cafe-medio);
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 600;
    }

    .stat-icon {
        position: absolute;
        top: 20px;
        right: 20px;
        font-size: 2rem;
        opacity: 0.2;
        color: var(--cafe-oscuro);
    }

    .small.text-muted {
        color: var(--cafe-claro) !important;
    }

    /* SECCIONES DE CONTENIDO */
    .content-sections {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .section-card {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 8px 30px rgba(139, 69, 19, 0.08);
        transition: transform 0.3s ease;
        border: 1px solid var(--piel-medio);
    }

    .section-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(139, 69, 19, 0.15);
        border-color: var(--cafe-claro);
    }

    .section-header {
        padding: 20px;
        background: linear-gradient(135deg, var(--piel-claro) 0%, var(--crema) 100%);
        border-bottom: 1px solid var(--piel-medio);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .section-header h5 {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--marron-chocolate);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-header h5 i {
        color: var(--cafe-oscuro);
    }

    .section-body {
        padding: 20px;
    }

    /* TABLA DE PRODUCTOS BAJOS */
    .product-low-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        margin-bottom: 10px;
        background: rgba(205, 92, 92, 0.1);
        border-radius: 10px;
        border-left: 4px solid var(--employee-danger);
        transition: all 0.3s ease;
    }

    .product-low-item:hover {
        background: rgba(205, 92, 92, 0.2);
        transform: translateX(5px);
    }

    .product-info h6 {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
        color: var(--marron-chocolate);
    }

    .text-muted {
        color: var(--cafe-claro) !important;
    }

    .product-stock {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .stock-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 700;
    }

    .stock-critical {
        background: var(--employee-danger);
        color: white;
        animation: pulse 2s infinite;
    }

    .stock-low {
        background: var(--naranja-medio);
        color: var(--marron-chocolate);
    }

    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.7; }
        100% { opacity: 1; }
    }

    .btn-outline-danger {
        border-color: var(--employee-danger);
        color: var(--employee-danger);
    }

    .btn-outline-danger:hover {
        background: var(--employee-danger);
        border-color: var(--employee-danger);
        color: white;
    }

    /* TURNO ACTUAL */
    .shift-container {
        background: linear-gradient(135deg, var(--cafe-medio) 0%, var(--naranja-medio) 100%);
        border-radius: 15px;
        padding: 25px;
        color: white;
        position: relative;
        overflow: hidden;
    }

    .shift-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path fill="white" opacity="0.1" d="M0,0 L100,0 L100,100 Z"></path></svg>');
        background-size: cover;
    }

    .shift-info h4 {
        font-size: 1.5rem;
        margin-bottom: 15px;
        font-weight: 700;
    }

    .shift-time {
        font-size: 2.5rem;
        font-weight: 800;
        margin: 10px 0;
    }

    .shift-details {
        display: flex;
        gap: 20px;
        margin-top: 20px;
        flex-wrap: wrap;
    }

    .shift-detail {
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(255, 255, 255, 0.2);
        padding: 10px 15px;
        border-radius: 10px;
        backdrop-filter: blur(10px);
    }

    /* ACCIONES RÁPIDAS */
    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 15px;
        margin-top: 25px;
    }

    .quick-action {
        background: white;
        border: 2px solid var(--piel-medio);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        color: var(--cafe-medio);
        text-decoration: none;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }

    .quick-action:hover {
        border-color: var(--cafe-oscuro);
        background: rgba(139, 69, 19, 0.05);
        transform: translateY(-3px);
        color: var(--cafe-oscuro);
        text-decoration: none;
        box-shadow: 0 10px 25px rgba(139, 69, 19, 0.15);
    }

    .quick-action i {
        font-size: 2rem;
        background: var(--employee-primary);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .quick-action span {
        font-size: 0.9rem;
        font-weight: 600;
    }

    /* VENTAS RECIENTES */
    .sale-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        margin-bottom: 10px;
        background: rgba(143, 188, 143, 0.1);
        border-radius: 10px;
        border-left: 4px solid #3CB371;
        transition: all 0.3s ease;
    }

    .sale-item:hover {
        background: rgba(143, 188, 143, 0.2);
        transform: translateX(5px);
    }

    .sale-info h6 {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--marron-chocolate);
    }

    .sale-amount {
        font-weight: 700;
        color: #11998e;
        font-size: 1.1rem;
    }

    /* CLIENTES RECIENTES */
    .client-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        margin-bottom: 10px;
        background: rgba(134, 206, 235, 0.1);
        border-radius: 10px;
        transition: all 0.3s ease;
    }

    .client-item:hover {
        background: rgba(134, 206, 235, 0.2);
        transform: translateX(5px);
    }

    .client-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: var(--employee-secondary);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--marron-chocolate);
        font-weight: 700;
        font-size: 1.2rem;
    }

    .client-info h6 {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--marron-chocolate);
    }

    .client-meta {
        font-size: 0.8rem;
        color: var(--cafe-claro);
    }

    /* RELOJ EN TIEMPO REAL */
    .live-clock-container {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: rgba(139, 69, 19, 0.9);
        color: white;
        padding: 15px 25px;
        border-radius: 15px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(210, 180, 140, 0.3);
        z-index: 1000;
        box-shadow: 0 10px 30px rgba(139, 69, 19, 0.3);
    }

    .clock-time {
        font-size: 1.8rem;
        font-weight: 700;
        font-family: 'Courier New', monospace;
        text-align: center;
    }

    .clock-date {
        font-size: 0.9rem;
        opacity: 0.8;
        text-align: center;
    }

    /* BOTONES ESTILIZADOS */
    .btn-sm {
        font-weight: 600;
    }

    .btn-warning {
        background: var(--employee-warning);
        border-color: var(--naranja-medio);
        color: var(--marron-chocolate);
    }

    .btn-primary {
        background: var(--employee-primary);
        border-color: var(--cafe-oscuro);
        color: white;
    }

    .btn-success {
        background: var(--employee-success);
        border-color: #3CB371;
        color: white;
    }

    .btn-info {
        background: var(--employee-info);
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

    /* BADGES CON ESTILO CAFÉ */
    .badge.bg-warning {
        background: var(--employee-warning) !important;
        color: var(--marron-chocolate);
    }

    .badge.bg-info {
        background: var(--employee-info) !important;
        color: white;
    }

    .badge.bg-success {
        background: var(--employee-success) !important;
        color: white;
    }

    .badge.bg-light {
        background: var(--piel-medio) !important;
        color: var(--marron-chocolate);
    }

    /* RESPONSIVE */
    @media (max-width: 768px) {
        .employee-header {
            flex-direction: column;
            text-align: center;
        }

        .employee-avatar {
            width: 100px;
            height: 100px;
            font-size: 2.5rem;
        }

        .stats-container {
            grid-template-columns: 1fr;
        }

        .content-sections {
            grid-template-columns: 1fr;
        }

        .quick-actions-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .shift-details {
            flex-direction: column;
        }

        .live-clock-container {
            position: static;
            margin-top: 20px;
            width: 100%;
        }
    }

    @media (max-width: 576px) {
        .quick-actions-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- =========================================================================== -->
<!-- CONTENIDO PRINCIPAL DEL DASHBOARD EMPLEADO -->
<!-- =========================================================================== -->
<div class="dashboard-content">

    <!-- ENCABEZADO DE BIENVENIDA -->
    <div class="welcome-section-employee">
        <div class="employee-header">
            <div class="employee-info">
                <h2><i class="fas fa-user-tie me-2"></i>Bienvenido, <?php echo htmlspecialchars($userName); ?></h2>
                <p>
                    <span class="employee-local">
                        <i class="fas fa-store"></i>
                        <?php echo htmlspecialchars($empleado['nombre_local'] ?? 'Local Bogati'); ?>
                    </span>
                    <span class="ms-3">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($empleado['ciudad'] ?? '') . ', ' . htmlspecialchars($empleado['provincia'] ?? ''); ?>
                    </span>
                </p>
                <div class="employee-meta">
                    <span class="badge bg-warning me-2">
                        <i class="fas fa-id-badge"></i> ID: <?php echo htmlspecialchars($empleado_id); ?>
                    </span>
                    <span class="badge bg-info me-2">
                        <i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($empleado['cargo'] ?? 'Empleado'); ?>
                    </span>
                    <span class="badge bg-success">
                        <i class="fas fa-calendar-check"></i> Contratado: <?php echo date('d/m/Y', strtotime($empleado['fecha_contratacion'] ?? date('Y-m-d'))); ?>
                    </span>
                </div>
            </div>
            <div class="employee-avatar">
                <?php echo strtoupper(substr($userName, 0, 1)); ?>
            </div>
        </div>
    </div>

    <!-- ESTADÍSTICAS DEL DÍA -->
    <div class="stats-container">
        <div class="day-stat-card sales" onclick="window.location.href='ventas.php'">
            <div class="stat-label">Ventas Hoy</div>
            <div class="stat-value-large"><?php echo $stats_mapped['ventas_hoy']; ?></div>
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="small text-muted">Total del día</div>
        </div>

        <div class="day-stat-card revenue" onclick="window.location.href='historial.php'">
            <div class="stat-label">Ingresos Hoy</div>
            <div class="stat-value-large">$<?php echo $stats_mapped['total_hoy']; ?></div>
            <div class="stat-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="small text-muted">Acumulado</div>
        </div>

        <div class="day-stat-card customers" onclick="window.location.href='clientes.php'">
            <div class="stat-label">Clientes Hoy</div>
            <div class="stat-value-large"><?php echo $stats_mapped['clientes_hoy']; ?></div>
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="small text-muted">Atención al cliente</div>
        </div>

        <div class="day-stat-card average" onclick="window.location.href='historial.php'">
            <div class="stat-label">Ticket Promedio</div>
            <div class="stat-value-large">$<?php echo $stats_mapped['promedio_venta']; ?></div>
            <div class="stat-icon">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="small text-muted">Por venta</div>
        </div>
    </div>

    <!-- SECCIONES DE CONTENIDO -->
    <div class="content-sections">

        <!-- PRODUCTOS BAJOS EN STOCK -->
        <div class="section-card">
            <div class="section-header">
                <h5><i class="fas fa-exclamation-triangle"></i> Productos Bajos en Stock</h5>
                <a href="inventario.php" class="btn btn-sm btn-warning">
                    Ver todo
                </a>
            </div>
            <div class="section-body">
                <?php if (!empty($productos_bajos)): ?>
                    <?php foreach ($productos_bajos as $producto): ?>
                        <div class="product-low-item">
                            <div class="product-info">
                                <h6><?php echo htmlspecialchars($producto['producto_nombre']); ?></h6>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría'); ?>
                                </small>
                            </div>
                            <div class="product-stock">
                                <span class="stock-badge <?php echo ($producto['cantidad'] <= $producto['cantidad_minima']) ? 'stock-critical' : 'stock-low'; ?>">
                                    <?php echo $producto['cantidad']; ?> unidades
                                </span>
                                <button class="btn btn-sm btn-outline-danger" 
                                        onclick="solicitarReabastecimiento('<?php echo $producto['codigo_producto']; ?>')">
                                    <i class="fas fa-bell"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <p class="text-muted">¡Todo el stock está en buen nivel!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- TURNO ACTUAL -->
        <div class="section-card">
            <div class="section-header">
                <h5><i class="fas fa-clock"></i> Mi Turno Hoy</h5>
                <a href="turno.php" class="btn btn-sm btn-primary">
                    <i class="fas fa-calendar-alt"></i> Ver horarios
                </a>
            </div>
            <div class="section-body">
                <?php if ($turno_actual): ?>
                    <div class="shift-container">
                        <div class="shift-info">
                            <h4>Turno de <?php echo htmlspecialchars($dia_actual); ?></h4>
                            <div class="shift-time">
                                <?php echo date('H:i', strtotime($turno_actual['hora_entrada'])); ?> - 
                                <?php echo date('H:i', strtotime($turno_actual['hora_salida'])); ?>
                            </div>
                            <div class="shift-details">
                                <div class="shift-detail">
                                    <i class="fas fa-door-open"></i>
                                    <div>
                                        <small>Entrada</small>
                                        <div class="fw-bold"><?php echo $turno_actual['hora_entrada']; ?></div>
                                    </div>
                                </div>
                                <div class="shift-detail">
                                    <i class="fas fa-door-closed"></i>
                                    <div>
                                        <small>Salida</small>
                                        <div class="fw-bold"><?php echo $turno_actual['hora_salida']; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No tienes turno asignado para hoy</p>
                        <a href="turno.php" class="btn btn-outline-primary">
                            Ver mis horarios
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- VENTAS RECIENTES -->
        <div class="section-card">
            <div class="section-header">
                <h5><i class="fas fa-history"></i> Ventas Recientes</h5>
                <a href="historial.php" class="btn btn-sm btn-success">
                    Ver historial
                </a>
            </div>
            <div class="section-body">
                <?php if (!empty($ventas_recientes)): ?>
                    <?php foreach ($ventas_recientes as $venta): ?>
                        <div class="sale-item" onclick="window.location.href='detalle_venta.php?id=<?php echo $venta['id_venta']; ?>'">
                            <div class="sale-info">
                                <h6>#<?php echo htmlspecialchars($venta['numero_factura'] ?? $venta['id_venta']); ?></h6>
                                <small class="text-muted">
                                    <?php echo date('H:i', strtotime($venta['fecha_venta'])); ?> • 
                                    <?php echo htmlspecialchars($venta['cliente_nombre'] ?? 'Cliente no registrado'); ?>
                                </small>
                            </div>
                            <div class="sale-amount">
                                $<?php echo number_format($venta['total'], 2); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No hay ventas recientes</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- CLIENTES RECIENTES -->
        <div class="section-card">
            <div class="section-header">
                <h5><i class="fas fa-user-friends"></i> Clientes Recientes</h5>
                <a href="clientes.php" class="btn btn-sm btn-info">
                    Ver todos
                </a>
            </div>
            <div class="section-body">
                <?php if (!empty($ultimos_clientes)): ?>
                    <?php foreach ($ultimos_clientes as $cliente): ?>
                        <div class="client-item" onclick="window.location.href='cliente_detalle.php?id=<?php echo $cliente['id_cliente']; ?>'">
                            <div class="client-avatar">
                                <?php echo strtoupper(substr($cliente['nombres'] ?? 'C', 0, 1)); ?>
                            </div>
                            <div class="client-info">
                                <h6><?php echo htmlspecialchars($cliente['nombres'] . ' ' . $cliente['apellidos']); ?></h6>
                                <div class="client-meta">
                                    <?php echo htmlspecialchars($cliente['cedula'] ?? ''); ?> • 
                                    <?php echo $cliente['total_compras'] ?? 0; ?> compras
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No hay clientes recientes</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ACCIONES RÁPIDAS -->
    <div class="section-card">
        <div class="section-header">
            <h5><i class="fas fa-bolt"></i> Acciones Rápidas</h5>
            <span class="badge bg-light text-dark">Acceso directo</span>
        </div>
        <div class="section-body">
            <div class="quick-actions-grid">
                <a href="ventas.php" class="quick-action">
                    <i class="fas fa-cash-register"></i>
                    <span>Nueva Venta</span>
                </a>
                <a href="inventario.php" class="quick-action">
                    <i class="fas fa-boxes"></i>
                    <span>Ver Inventario</span>
                </a>
                <a href="clientes.php" class="quick-action">
                    <i class="fas fa-users"></i>
                    <span>Gestión Clientes</span>
                </a>
                <a href="turno.php" class="quick-action">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Mis Turnos</span>
                </a>
                <a href="perfil.php" class="quick-action">
                    <i class="fas fa-user-cog"></i>
                    <span>Mi Perfil</span>
                </a>
                <a href="reportes.php" class="quick-action">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reportes</span>
                </a>
            </div>
        </div>
    </div>

    <!-- RELOJ EN TIEMPO REAL -->
    <div class="live-clock-container">
        <div class="clock-time" id="currentTime">--:--:--</div>
        <div class="clock-date" id="currentDate">Cargando...</div>
    </div>
</div>

<!-- =========================================================================== -->
<!-- SCRIPTS JAVASCRIPT -->
<!-- =========================================================================== -->
<script>
    /**
     * Actualizar reloj en tiempo real
     */
    function updateClock() {
        const now = new Date();
        const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
        
        document.getElementById('currentDate').textContent = 
            now.toLocaleDateString('es-ES', dateOptions);
        document.getElementById('currentTime').textContent = 
            now.toLocaleTimeString('es-ES', timeOptions);
        
        setTimeout(updateClock, 1000);
    }

    /**
     * Solicitar reabastecimiento de producto
     */
    function solicitarReabastecimiento(codigoProducto) {
        if (confirm('¿Solicitar reabastecimiento de este producto al encargado?')) {
            fetch('../../includes/solicitar_reabastecimiento.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    codigo_producto: codigoProducto,
                    local_codigo: '<?php echo $local_codigo; ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Solicitud enviada exitosamente');
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
     * Auto-refresh de estadísticas cada 2 minutos
     */
    function refreshStats() {
        fetch('../../includes/update_stats.php')
            .then(response => response.json())
            .then(data => {
                if (data.ventas_hoy) {
                    document.querySelector('.day-stat-card.sales .stat-value-large').textContent = data.ventas_hoy;
                    document.querySelector('.day-stat-card.revenue .stat-value-large').textContent = '$' + data.total_hoy;
                    document.querySelector('.day-stat-card.customers .stat-value-large').textContent = data.clientes_hoy;
                }
            })
            .catch(error => console.log('Error actualizando stats:', error));
    }

    /**
     * Efectos visuales
     */
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar reloj
        updateClock();
        
        // Auto-refresh cada 2 minutos
        setInterval(refreshStats, 2 * 60 * 1000);
        
        // Efectos hover en cards
        const cards = document.querySelectorAll('.day-stat-card, .section-card, .quick-action');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
        
        // Notificación de actividad
        if (Notification.permission === 'granted') {
            // Configurar notificaciones si están permitidas
        } else if (Notification.permission !== 'denied') {
            Notification.requestPermission();
        }
        
        // Actualizar hora de turno
        const horaActual = new Date().toLocaleTimeString('es-ES', {hour12: false});
        const turnoElement = document.querySelector('.shift-time');
        if (turnoElement) {
            const turnoHora = turnoElement.textContent.split(' - ');
            const horaEntrada = turnoHora[0];
            const horaSalida = turnoHora[1];
            
            if (horaActual >= horaEntrada && horaActual <= horaSalida) {
                turnoElement.classList.add('text-success');
                turnoElement.innerHTML += '<br><small class="badge bg-success">EN TURNO</small>';
            } else if (horaActual < horaEntrada) {
                turnoElement.classList.add('text-warning');
                turnoElement.innerHTML += '<br><small class="badge bg-warning">PRÓXIMO</small>';
            } else {
                turnoElement.classList.add('text-muted');
                turnoElement.innerHTML += '<br><small class="badge bg-secondary">FINALIZADO</small>';
            }
        }
    });

    /**
     * Mostrar notificación de bienvenida
     */
    function showWelcomeNotification() {
        const hora = new Date().getHours();
        let mensaje = '';
        
        if (hora < 12) mensaje = '¡Buenos días!';
        else if (hora < 18) mensaje = '¡Buenas tardes!';
        else mensaje = '¡Buenas noches!';
        
        if (Notification.permission === 'granted') {
            new Notification(mensaje, {
                body: 'Listo para atender clientes en <?php echo htmlspecialchars($empleado["nombre_local"] ?? "Bogati"); ?>',
                icon: '../../assets/img/logo.png'
            });
        }
    }

    // Mostrar notificación después de 3 segundos
    setTimeout(showWelcomeNotification, 3000);
</script>

<?php
// ============================================================================
// INCLUIR FOOTER
// ============================================================================
require_once __DIR__ . '/../../includes/footer.php';
?>