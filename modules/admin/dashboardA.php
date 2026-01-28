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

// Verificar que sea admin
requireAnyRole(['admin']); 

// Obtener información del usuario usando la función que creaste
$userInfo = getCurrentUserInfo();
if (!$userInfo) {
    // Si no se pudo obtener la info, redirigir al login
    session_destroy();
    setFlashMessage('error', 'No se pudo cargar la información del usuario. Por favor, inicie sesión nuevamente.');
    redirect(BASE_URL . 'login.php');
}

// Obtener nombre del usuario
$userName = getCurrentUserName(); 

// Obtener rol del usuario
$userRole = getCurrentUserRole();

// Obtener último acceso
$ultimoAcceso = date('d/m/Y H:i', strtotime($userInfo['ultimo_acceso'] ?? 'now'));

// Verificar si hay algún mensaje flash
if (isset($_GET['message'])) {
    $messageType = $_GET['message_type'] ?? 'info';
    $message = urldecode($_GET['message']);
    setFlashMessage($messageType, $message);
}

// Obtener conexión a la base de datos
$db = Database::getConnection();

// Obtener estadísticas para el dashboard
try {
    // Conteo de franquiciados activos
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM franquiciados WHERE estado = 'ACTIVO'");
    $stmt->execute();
    $franquiciadosCount = $stmt->fetchColumn();
    
    // Conteo de locales activos (todos los locales en la tabla)
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM locales");
    $stmt->execute();
    $localesCount = $stmt->fetchColumn();
    
    // Conteo de empleados activos
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM empleados WHERE estado = 'ACTIVO'");
    $stmt->execute();
    $empleadosCount = $stmt->fetchColumn();
    
    // Conteo de usuarios del sistema
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM usuarios_sistema");
    $stmt->execute();
    $usuariosCount = $stmt->fetchColumn();
    
    // Conteo de productos
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM productos");
    $stmt->execute();
    $productosCount = $stmt->fetchColumn();
    
    // Conteo de contratos activos
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM contratos_franquicia WHERE estado = 'ACTIVO'");
    $stmt->execute();
    $contratosCount = $stmt->fetchColumn();
    
    // Conteo de usuarios registrados hoy
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM usuarios_sistema WHERE DATE(ultimo_acceso) = CURDATE()");
    $stmt->execute();
    $usuariosHoy = $stmt->fetchColumn();
    
    // Ventas del mes actual
    $currentMonth = date('Y-m');
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total), 0) as total_ventas 
        FROM ventas 
        WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = ?
    ");
    $stmt->execute([$currentMonth]);
    $ventasMes = $stmt->fetchColumn();
    
    // Pagos pendientes
    $stmt = $db->prepare("
        SELECT COUNT(*) as pendientes 
        FROM pagos_royalty 
        WHERE estado = 'PENDIENTE'
    ");
    $stmt->execute();
    $pagosPendientes = $stmt->fetchColumn();
    
    // ================================================================
    // NUEVAS CONSULTAS ESPECÍFICAS PARA TUS TABLAS
    // ================================================================
    
    // Obtener últimos usuarios registrados
    $stmt = $db->prepare("
        SELECT 
            id_usuario as id,
            CONCAT(nombres, ' ', apellidos) as nombre_completo,
            rol,
            email,
            ultimo_acceso as fecha_registro,
            estado
        FROM usuarios_sistema
        ORDER BY ultimo_acceso DESC
        LIMIT 6
    ");
    $stmt->execute();
    $ultimosUsuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener contratos por vencer (próximos 90 días)
    $stmt = $db->prepare("
        SELECT 
            cf.id_contrato as id,
            cf.numero_contrato,
            CONCAT(f.nombres, ' ', f.apellidos) as franquiciado_nombre,
            cf.fecha_fin,
            cf.estado,
            DATEDIFF(cf.fecha_fin, CURDATE()) as dias_restantes
        FROM contratos_franquicia cf
        LEFT JOIN franquiciados f ON cf.cedula_franquiciado = f.cedula
        WHERE cf.estado = 'ACTIVO'
        AND cf.fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
        ORDER BY cf.fecha_fin ASC
        LIMIT 10
    ");
    $stmt->execute();
    $contratosPorVencer = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener inventario crítico (stock <= stock_minimo)
    $stmt = $db->prepare("
        SELECT 
            i.codigo_local,
            i.codigo_producto,
            p.nombre as producto_nombre,
            l.nombre_local as nombre_local,
            i.cantidad as stock,
            i.cantidad_minima as stock_minimo,
            ROUND((i.cantidad / i.cantidad_minima) * 100) as porcentaje
        FROM inventario i
        INNER JOIN productos p ON i.codigo_producto = p.codigo_producto
        INNER JOIN locales l ON i.codigo_local = l.codigo_local
        WHERE i.cantidad <= i.cantidad_minima
        ORDER BY (i.cantidad / i.cantidad_minima) ASC
        LIMIT 10
    ");
    $stmt->execute();
    $inventarioCritico = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar total de productos críticos
    $totalProductosCriticos = count($inventarioCritico);
    
    // Obtener actividad reciente
    $stmt = $db->prepare("
        SELECT 
            ls.id_log as id,
            ls.id_usuario,
            CONCAT(us.nombres, ' ', us.apellidos) as empleado_nombre,
            ls.accion,
            ls.fecha_hora,
            DATE(ls.fecha_hora) as fecha,
            TIME(ls.fecha_hora) as hora,
            ls.ip_address,
            ls.tabla_afectada as detalles
        FROM logs_sistema ls
        LEFT JOIN usuarios_sistema us ON ls.id_usuario = us.id_usuario
        ORDER BY ls.fecha_hora DESC
        LIMIT 8
    ");
    $stmt->execute();
    $empleadosActividad = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mapear estadísticas
    $stats_mapped = [
        'usuarios' => $usuariosCount,
        'franquiciados' => $franquiciadosCount,
        'locales' => $localesCount,
        'empleados' => $empleadosCount,
        'productos' => $productosCount,
        'contratos' => $contratosCount,
        'usuarios_hoy' => $usuariosHoy,
        'ventas_mes' => number_format($ventasMes, 2),
        'pagos_pendientes' => $pagosPendientes
    ];
    
} catch (PDOException $e) {
    error_log('Error cargando datos del dashboard: ' . $e->getMessage());
    
    // Inicializar variables faltantes
    $ultimosUsuarios = [];
    $contratosPorVencer = [];
    $inventarioCritico = [];
    $totalProductosCriticos = 0;
    $empleadosActividad = [];
    $stats_mapped = [
        'usuarios' => 0,
        'franquiciados' => 0,
        'locales' => 0,
        'empleados' => 0,
        'productos' => 0,
        'contratos' => 0,
        'usuarios_hoy' => 0,
        'ventas_mes' => '0.00',
        'pagos_pendientes' => 0
    ];
}

// Configurar título de la página
$pageTitle = APP_NAME . ' - Panel de Administración';

// CSS específico para el dashboard
$pageStyles = ['dashboard.css'];

// JavaScript para gráficos
$pageScripts = [
    'https://cdn.jsdelivr.net/npm/chart.js',
    'dashboard-charts.js'
];

// Incluir header
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- =========================================================================== -->
<!-- ESTILOS INLINE CON PALETA CAFÉ/NARANJA/MARRÓN PIEL -->
<!-- =========================================================================== -->
<style>
    :root {
        /* Paleta principal café/naranja/marrón piel */
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
        
        /* Gradientes con la nueva paleta */
        --primary-gradient: linear-gradient(135deg, var(--cafe-oscuro) 0%, var(--naranja-oscuro) 100%);
        --secondary-gradient: linear-gradient(135deg, var(--piel-oscuro) 0%, var(--naranja-claro) 100%);
        --success-gradient: linear-gradient(135deg, #8FBC8F 0%, #3CB371 100%);
        --info-gradient: linear-gradient(135deg, #87CEEB 0%, #4682B4 100%);
        --warning-gradient: linear-gradient(135deg, var(--naranja-medio) 0%, #FFD700 100%);
        --danger-gradient: linear-gradient(135deg, #CD5C5C 0%, #B22222 100%);
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
        background: var(--primary-gradient);
        z-index: 0;
        opacity: 0.08;
    }

    /* HEADER CON ESTILO CAFÉ */
    .welcome-section {
        background: linear-gradient(135deg, rgba(139, 69, 19, 0.1) 0%, rgba(245, 222, 179, 0.05) 100%);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        color: var(--marron-chocolate);
        padding: 40px;
        border-radius: 24px;
        margin-bottom: 40px;
        box-shadow:
            0 20px 60px rgba(139, 69, 19, 0.1),
            inset 0 1px 0 rgba(255, 255, 255, 0.5);
        position: relative;
        overflow: hidden;
        z-index: 1;
    }

    .welcome-section::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: linear-gradient(45deg,
                transparent 30%,
                rgba(255, 165, 0, 0.1) 50%,
                transparent 70%);
        animation: shine 3s infinite linear;
    }

    @keyframes shine {
        0% {
            transform: translateX(-100%) translateY(-100%) rotate(45deg);
        }
        100% {
            transform: translateX(100%) translateY(100%) rotate(45deg);
        }
    }

    .welcome-text h2 {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 15px;
        background: var(--primary-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        display: inline-block;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
    }

    .welcome-text p {
        font-size: 1.2rem;
        opacity: 0.9;
        font-weight: 500;
        color: var(--cafe-medio);
    }

    .welcome-stats {
        display: flex;
        gap: 30px;
        margin-top: 30px;
        flex-wrap: wrap;
    }

    .welcome-stat {
        text-align: center;
        padding: 20px;
        border-radius: 16px;
        background: rgba(210, 180, 140, 0.15);
        backdrop-filter: blur(10px);
        min-width: 150px;
        transition: all 0.3s ease;
        border: 1px solid rgba(210, 180, 140, 0.3);
    }

    .welcome-stat:hover {
        transform: translateY(-5px);
        background: rgba(210, 180, 140, 0.25);
        box-shadow: 0 10px 30px rgba(139, 69, 19, 0.15);
    }

    .welcome-stat .number {
        font-size: 2.5rem;
        font-weight: 800;
        display: block;
        background: var(--primary-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .welcome-stat .label {
        font-size: 0.9rem;
        color: var(--cafe-medio);
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 600;
    }

    /* CARDS DE ESTADÍSTICAS */
    .dashboard-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
        margin-bottom: 50px;
        z-index: 1;
        position: relative;
    }

    .stat-card {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(250, 235, 215, 0.8) 100%);
        backdrop-filter: blur(15px);
        -webkit-backdrop-filter: blur(15px);
        border: 1px solid var(--piel-oscuro);
        border-radius: 20px;
        padding: 30px;
        box-shadow:
            0 10px 40px rgba(139, 69, 19, 0.1),
            inset 0 1px 0 rgba(255, 255, 255, 0.6);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        height: 100%;
        cursor: pointer;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--primary-gradient);
    }

    .stat-card:hover {
        transform: translateY(-10px) scale(1.02);
        box-shadow:
            0 25px 60px rgba(139, 69, 19, 0.2),
            inset 0 1px 0 rgba(255, 255, 255, 0.6);
        border-color: var(--naranja-medio);
    }

    .stat-card-primary::before {
        background: var(--primary-gradient);
    }

    .stat-card-success::before {
        background: var(--success-gradient);
    }

    .stat-card-info::before {
        background: var(--info-gradient);
    }

    .stat-card-warning::before {
        background: var(--warning-gradient);
    }

    .stat-card-danger::before {
        background: var(--danger-gradient);
    }

    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 25px;
    }

    .stat-icon {
        width: 70px;
        height: 70px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        color: white;
        box-shadow: 0 10px 20px rgba(139, 69, 19, 0.2);
        transition: transform 0.3s ease;
    }

    .stat-card-primary .stat-icon {
        background: var(--primary-gradient);
    }

    .stat-card-success .stat-icon {
        background: var(--success-gradient);
    }

    .stat-card-warning .stat-icon {
        background: var(--warning-gradient);
    }

    .stat-card:hover .stat-icon {
        transform: scale(1.1) rotate(5deg);
    }

    .stat-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--cafe-medio);
        text-transform: uppercase;
        letter-spacing: 1.5px;
        margin-bottom: 10px;
    }

    .stat-value {
        font-size: 3.2rem;
        font-weight: 900;
        margin: 15px 0;
        background: var(--primary-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        line-height: 1;
    }

    .stat-progress {
        height: 8px;
        background: rgba(210, 180, 140, 0.3);
        border-radius: 10px;
        overflow: hidden;
        margin: 20px 0;
        position: relative;
    }

    .stat-progress-bar {
        height: 100%;
        border-radius: 10px;
        background: var(--primary-gradient);
        position: relative;
        transition: width 1s ease-in-out;
    }

    .stat-progress-bar::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(90deg,
                transparent,
                rgba(255, 255, 255, 0.4),
                transparent);
        animation: shimmer 2s infinite;
    }

    @keyframes shimmer {
        0% {
            transform: translateX(-100%);
        }
        100% {
            transform: translateX(100%);
        }
    }

    /* GRID DE CONTENIDO */
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        gap: 30px;
        margin-bottom: 40px;
        z-index: 1;
        position: relative;
    }

    .grid-card {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(139, 69, 19, 0.08);
        transition: transform 0.3s ease;
        border: 1px solid var(--piel-medio);
        grid-column: span 6;
    }

    .grid-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 60px rgba(139, 69, 19, 0.15);
        border-color: var(--cafe-claro);
    }

    .grid-card.full-width {
        grid-column: span 12;
    }

    .card-header {
        padding: 25px;
        background: linear-gradient(135deg, var(--piel-claro) 0%, var(--crema) 100%);
        border-bottom: 1px solid var(--piel-medio);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .card-header h5 {
        margin: 0;
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--marron-chocolate);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .card-header h5 i {
        color: var(--cafe-oscuro);
        font-size: 1.5rem;
    }

    .card-body {
        padding: 25px;
    }

    /* TARJETAS DE USUARIOS */
    .user-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 20px;
    }

    .user-card {
        background: linear-gradient(135deg, #ffffff 0%, var(--piel-claro) 100%);
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 8px 30px rgba(139, 69, 19, 0.05);
        transition: all 0.3s ease;
        border: 1px solid var(--piel-medio);
        text-align: center;
        position: relative;
    }

    .user-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 15px 40px rgba(139, 69, 19, 0.15);
        border-color: var(--naranja-claro);
    }

    .user-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: var(--primary-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 28px;
        font-weight: 700;
        margin: 0 auto 20px;
        box-shadow: 0 10px 25px rgba(139, 69, 19, 0.3);
        border: 4px solid white;
    }

    .user-info h5 {
        font-size: 1.1rem;
        font-weight: 700;
        margin-bottom: 10px;
        color: var(--marron-chocolate);
    }

    .user-role {
        display: inline-block;
        padding: 6px 15px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        background: linear-gradient(135deg, rgba(210, 180, 140, 0.3) 0%, rgba(255, 165, 0, 0.2) 100%);
        color: var(--cafe-oscuro);
    }

    .user-status {
        font-size: 0.85rem;
        color: var(--cafe-medio);
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid var(--piel-medio);
    }

    /* ACTIVIDAD TIMELINE */
    .activity-timeline {
        position: relative;
        padding-left: 40px;
    }

    .activity-timeline::before {
        content: '';
        position: absolute;
        left: 20px;
        top: 0;
        bottom: 0;
        width: 3px;
        background: var(--primary-gradient);
        border-radius: 3px;
    }

    .activity-item {
        position: relative;
        margin-bottom: 30px;
        background: linear-gradient(135deg, #ffffff 0%, var(--piel-claro) 100%);
        border-radius: 16px;
        padding: 20px;
        border: 1px solid var(--piel-medio);
        box-shadow: 0 5px 20px rgba(139, 69, 19, 0.03);
        transition: transform 0.3s ease;
    }

    .activity-item:hover {
        transform: translateX(10px);
        border-color: var(--cafe-claro);
    }

    .activity-dot {
        position: absolute;
        left: -40px;
        top: 25px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 4px solid white;
        box-shadow: 0 0 0 4px;
        z-index: 2;
    }

    .activity-dot-success {
        box-shadow: 0 0 0 4px #3CB371;
    }

    .activity-dot-primary {
        box-shadow: 0 0 0 4px var(--cafe-oscuro);
    }

    .activity-dot-warning {
        box-shadow: 0 0 0 4px var(--naranja-medio);
    }

    .activity-dot-danger {
        box-shadow: 0 0 0 4px #B22222;
    }

    .activity-content h6 {
        font-size: 1rem;
        font-weight: 700;
        color: var(--marron-chocolate);
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .activity-content p {
        font-size: 0.95rem;
        color: var(--cafe-medio);
        margin-bottom: 10px;
    }

    .activity-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.85rem;
        color: var(--cafe-claro);
    }

    /* BOTONES */
    .btn-outline-primary {
        border-color: var(--cafe-oscuro);
        color: var(--cafe-oscuro);
    }

    .btn-outline-primary:hover {
        background: var(--cafe-oscuro);
        border-color: var(--cafe-oscuro);
        color: white;
    }

    .btn-warning {
        background: var(--warning-gradient);
        border-color: var(--naranja-medio);
        color: var(--marron-chocolate);
    }

    .badge.bg-primary {
        background: var(--primary-gradient) !important;
        color: white;
    }

    .badge.bg-success {
        background: var(--success-gradient) !important;
        color: white;
    }

    .badge.bg-warning {
        background: var(--warning-gradient) !important;
        color: var(--marron-chocolate);
    }

    .badge.bg-info {
        background: var(--info-gradient) !important;
        color: white;
    }

    .badge.bg-danger {
        background: var(--danger-gradient) !important;
        color: white;
    }

    /* RESPONSIVE */
    @media (max-width: 1200px) {
        .grid-card {
            grid-column: span 12;
        }
    }

    @media (max-width: 768px) {
        .dashboard-cards {
            grid-template-columns: 1fr;
        }

        .welcome-section {
            padding: 25px;
        }

        .welcome-text h2 {
            font-size: 2rem;
        }

        .welcome-stats {
            flex-direction: column;
            gap: 15px;
        }

        .welcome-stat {
            min-width: 100%;
        }

        .user-grid {
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        }
    }

    @media (max-width: 576px) {
        .user-grid {
            grid-template-columns: 1fr;
        }
    }

    /* ANIMACIONES */
    @keyframes float {
        0%, 100% {
            transform: translateY(0);
        }
        50% {
            transform: translateY(-10px);
        }
    }

    .floating {
        animation: float 3s ease-in-out infinite;
    }
</style>

<!-- =========================================================================== -->
<!-- CONTENIDO PRINCIPAL -->
<!-- =========================================================================== -->
<div class="dashboard-content">

    <!-- ENCABEZADO DE BIENVENIDA -->
    <div class="welcome-section">
        <div class="welcome-text">
            <h2><i class="fas fa-crown me-2"></i>Dashboard del Administrador</h2>
            <p class="mb-3">
                Bienvenido, 
                <strong><?php echo htmlspecialchars($userName ?? '', ENT_QUOTES, 'UTF-8'); ?></strong> |
                Último acceso: <?php echo $ultimoAcceso ?? '—'; ?> |
                Rol: 
                <span class="badge bg-primary">
                    <?php echo htmlspecialchars($userRole ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </p>
        </div>

        <div class="welcome-stats">
            <div class="welcome-stat floating" style="animation-delay: 0s;">
                <span class="number"><?php echo $stats_mapped['usuarios'] ?? '0'; ?></span>
                <span class="label">Usuarios</span>
            </div>
            <div class="welcome-stat floating" style="animation-delay: 0.2s;">
                <span class="number"><?php echo $stats_mapped['locales'] ?? '0'; ?></span>
                <span class="label">Locales</span>
            </div>
            <div class="welcome-stat floating" style="animation-delay: 0.4s;">
                <span class="number"><?php echo $stats_mapped['contratos'] ?? '0'; ?></span>
                <span class="label">Contratos</span>
            </div>
            <div class="welcome-stat floating" style="animation-delay: 0.6s;">
                <span class="number"><?php echo $totalProductosCriticos ?? '0'; ?></span>
                <span class="label">Alertas</span>
            </div>
        </div>
    </div>

    <!-- ======================================================================= -->
    <!-- ESTADÍSTICAS PRINCIPALES -->
    <!-- ======================================================================= -->
    <div class="dashboard-cards">
        <!-- USUARIOS -->
        <div class="stat-card stat-card-primary" onclick="window.location.href='<?php echo BASE_URL; ?>modules/admin/usuarios.php'">
            <div class="stat-header">
                <div>
                    <div class="stat-title">Usuarios Totales</div>
                    <div class="stat-value"><?php echo $stats_mapped['usuarios'] ?? '0'; ?></div>
                    <div class="stat-progress">
                        <div class="stat-progress-bar" style="width: <?php echo min(($stats_mapped['usuarios'] ?? 0) * 5, 100); ?>%"></div>
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div class="stat-footer d-flex justify-content-between align-items-center">
                <div class="stat-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    <span class="ms-1"><?php echo $stats_mapped['usuarios_hoy'] ?? '0'; ?> nuevos hoy</span>
                </div>
                <a href="<?php echo BASE_URL; ?>modules/admin/usuarios.php" class="stat-action">
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- FRANQUICIADOS -->
        <div class="stat-card stat-card-success" onclick="window.location.href='<?php echo BASE_URL; ?>modules/admin/franquiciados.php'">
            <div class="stat-header">
                <div>
                    <div class="stat-title">Franquiciados</div>
                    <div class="stat-value"><?php echo $stats_mapped['franquiciados'] ?? '0'; ?></div>
                    <div class="stat-progress">
                        <div class="stat-progress-bar" style="width: <?php echo min(($stats_mapped['franquiciados'] ?? 0) * 10, 100); ?>%"></div>
                    </div>
                </div>
                <div class="stat-icon" style="background: var(--success-gradient);">
                    <i class="fas fa-handshake"></i>
                </div>
            </div>
            <div class="stat-footer d-flex justify-content-between align-items-center">
                <div class="stat-trend trend-up">
                    <i class="fas fa-store"></i>
                    <span class="ms-1"><?php echo $stats_mapped['locales'] ?? '0'; ?> locales</span>
                </div>
                <a href="<?php echo BASE_URL; ?>modules/admin/franquiciados.php" class="stat-action">
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- PRODUCTOS -->
        <div class="stat-card stat-card-warning" onclick="window.location.href='<?php echo BASE_URL; ?>modules/admin/productos.php'">
            <div class="stat-header">
                <div>
                    <div class="stat-title">Productos</div>
                    <div class="stat-value"><?php echo $stats_mapped['productos'] ?? '0'; ?></div>
                    <div class="stat-progress">
                        <div class="stat-progress-bar" style="width: <?php echo min(($stats_mapped['productos'] ?? 0), 100); ?>%"></div>
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-boxes"></i>
                </div>
            </div>
            <div class="stat-footer d-flex justify-content-between align-items-center">
                <div class="stat-trend trend-down">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span class="ms-1"><?php echo $totalProductosCriticos ?? '0'; ?> alertas</span>
                </div>
                <a href="<?php echo BASE_URL; ?>modules/admin/productos.php" class="stat-action">
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- ======================================================================= -->
    <!-- SECCIÓN DE INFORMACIÓN RÁPIDA -->
    <!-- ======================================================================= -->
    <div class="dashboard-grid">

        <!-- ÚLTIMOS USUARIOS -->
        <div class="grid-card">
            <div class="card-header">
                <h5><i class="fas fa-user-plus"></i> Últimos Usuarios</h5>
                <a href="<?php echo BASE_URL; ?>modules/admin/usuarios.php" class="btn btn-sm btn-outline-primary">
                    Ver todos
                </a>
            </div>
            <div class="card-body">
                <div class="user-grid">
                    <?php if (!empty($ultimosUsuarios)): ?>
                        <?php foreach ($ultimosUsuarios as $usuario):
                            $nombre = isset($usuario['nombre_completo']) ? $usuario['nombre_completo'] : 
                                     (isset($usuario['nombres']) ? $usuario['nombres'] . ' ' . ($usuario['apellidos'] ?? '') : 'Usuario');
                            $inicial = strtoupper(substr(trim($nombre), 0, 1));
                            $rol = $usuario['rol'] ?? 'Usuario';
                            $fecha = isset($usuario['fecha_registro']) ? date('d/m/Y', strtotime($usuario['fecha_registro'])) : 'N/A';
                        ?>
                            <div class="user-card">
                                <div class="user-avatar">
                                    <?php echo htmlspecialchars($inicial); ?>
                                </div>
                                <div class="user-info">
                                    <h5><?php echo htmlspecialchars($nombre); ?></h5>
                                    <span class="user-role"><?php echo htmlspecialchars($rol); ?></span>
                                    <div class="user-status">
                                        <small>
                                            <i class="far fa-calendar me-1"></i>
                                            <?php echo $fecha; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No hay usuarios recientes</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- CONTRATOS POR VENCER -->
        <div class="grid-card">
            <div class="card-header">
                <h5><i class="fas fa-clock"></i> Contratos por Vencer</h5>
                <a href="<?php echo BASE_URL; ?>modules/admin/contratos.php" class="btn btn-sm btn-warning">
                    <i class="fas fa-eye me-1"></i> Ver todos
                </a>
            </div>
            <div class="card-body">
                <?php if (!empty($contratosPorVencer)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Contrato</th>
                                    <th>Franquiciado</th>
                                    <th>Días</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contratosPorVencer as $contrato):
                                    $numero_contrato = $contrato['numero_contrato'] ?? 'N/A';
                                    $franquiciado_nombre = $contrato['franquiciado_nombre'] ?? 'Sin nombre';
                                    $dias_restantes = $contrato['dias_restantes'] ?? 0;
                                    $estado = $contrato['estado'] ?? 'PENDIENTE';
                                    $id = $contrato['id'] ?? 0;

                                    $dias_class = $dias_restantes <= 7 ? 'danger' : ($dias_restantes <= 15 ? 'warning' : 'info');
                                ?>
                                    <tr onclick="window.location.href='<?php echo BASE_URL; ?>modules/admin/contratos.php?id=<?php echo $id; ?>'"
                                        style="cursor: pointer;">
                                        <td>
                                            <strong>#<?php echo htmlspecialchars($numero_contrato); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($franquiciado_nombre); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $dias_class; ?>">
                                                <?php echo $dias_restantes; ?> días
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $estado == 'VIGENTE' ? 'success' : 'warning'; ?>">
                                                <?php echo htmlspecialchars($estado); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <p class="text-muted">No hay contratos próximos a vencer</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ACTIVIDAD RECIENTE -->
        <div class="grid-card full-width">
            <div class="card-header">
                <h5><i class="fas fa-user-tie"></i> Actividad Reciente</h5>
                <button class="btn btn-sm btn-outline-primary" onclick="refreshActivity()">
                    <i class="fas fa-sync-alt me-1"></i> Actualizar
                </button>
            </div>
            <div class="card-body">
                <div class="activity-timeline">
                    <?php if (!empty($empleadosActividad)): ?>
                        <?php foreach ($empleadosActividad as $log):
                            $action_class = 'primary';
                            $icon = 'user';
                            $accion = strtoupper($log['accion'] ?? '');
                            $empleado_nombre = $log['empleado_nombre'] ?? 'Sistema';
                            $fecha = $log['fecha'] ?? date('d/m/Y');
                            $hora = $log['hora'] ?? date('H:i');
                            $ip = $log['ip_address'] ?? 'N/A';

                            // Definir iconos y colores según tipo de acción
                            if (strpos($accion, 'LOGIN') !== false) {
                                $action_class = 'success';
                                $icon = 'sign-in-alt';
                            } elseif (strpos($accion, 'CREAR') !== false || strpos($accion, 'INSERT') !== false) {
                                $action_class = 'success';
                                $icon = 'plus-circle';
                            } elseif (strpos($accion, 'ACTUALIZAR') !== false || strpos($accion, 'UPDATE') !== false) {
                                $action_class = 'warning';
                                $icon = 'edit';
                            } elseif (strpos($accion, 'ELIMINAR') !== false || strpos($accion, 'DELETE') !== false) {
                                $action_class = 'danger';
                                $icon = 'trash-alt';
                            }
                        ?>
                            <div class="activity-item">
                                <div class="activity-dot activity-dot-<?php echo $action_class; ?>"></div>
                                <div class="activity-content">
                                    <h6>
                                        <i class="fas fa-<?php echo $icon; ?> text-<?php echo $action_class; ?>"></i>
                                        <?php echo htmlspecialchars($empleado_nombre); ?>
                                    </h6>
                                    <p class="mb-2"><?php echo htmlspecialchars($log['accion'] ?? 'Actividad'); ?></p>
                                    <div class="activity-meta">
                                        <span>
                                            <i class="far fa-clock me-1"></i>
                                            <?php echo $hora; ?> • <?php echo $fecha; ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars($ip); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No hay actividad reciente registrada</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================== -->
<!-- SCRIPTS JAVASCRIPT -->
<!-- =========================================================================== -->
<script>
    function refreshActivity() {
        const btn = event.target.closest('button');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Actualizando...';
        btn.disabled = true;

        setTimeout(() => {
            location.reload();
        }, 1000);
    }

    // Auto-refresh cada 10 minutos
    setTimeout(() => {
        location.reload();
    }, 10 * 60 * 1000);

    // Efecto hover en cards
    document.querySelectorAll('.stat-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-10px) scale(1.02)';
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    // Animación de entrada
    document.addEventListener('DOMContentLoaded', function() {
        const elements = document.querySelectorAll('.stat-card, .grid-card, .user-card');
        elements.forEach((element, index) => {
            element.style.opacity = '0';
            element.style.transform = 'translateY(20px)';

            setTimeout(() => {
                element.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }, index * 100);
        });
    });

    // Actualizar progreso de las barras
    document.querySelectorAll('.stat-progress-bar').forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';

        setTimeout(() => {
            bar.style.width = width;
        }, 500);
    });
</script>

<?php
// ============================================================================
// INCLUIR FOOTER
// ============================================================================
require_once __DIR__ . '/../../includes/footer.php';
?>