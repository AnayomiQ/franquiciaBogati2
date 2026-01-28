<?php
// modules/empleado/ventas.php

require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../db_connection.php';

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

// Obtener el código del local del empleado
$empleado_id = $_SESSION['id_empleado'] ?? null;
$local_codigo = $_SESSION['codigo_local'] ?? null;

// Obtener conexión a la base de datos
$db = Database::getConnection();

// Obtener las ventas del local
try {
    // Obtener todas las ventas del local
    $stmt = $db->prepare("
        SELECT 
            v.*,
            CONCAT(c.nombres, ' ', c.apellidos) as cliente_nombre,
            c.cedula as cliente_cedula,
            COUNT(dv.id_detalle) as items_comprados,
            GROUP_CONCAT(DISTINCT p.nombre SEPARATOR ', ') as productos
        FROM ventas v
        LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
        LEFT JOIN detalle_ventas dv ON v.id_venta = dv.id_venta
        LEFT JOIN productos p ON dv.codigo_producto = p.codigo_producto
        WHERE v.codigo_local = ?
        GROUP BY v.id_venta
        ORDER BY v.fecha_venta DESC
    ");
    $stmt->execute([$local_codigo]);
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener estadísticas
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_ventas,
            COALESCE(SUM(total), 0) as total_ingresos,
            DATE_FORMAT(MIN(fecha_venta), '%d/%m/%Y') as primera_venta,
            DATE_FORMAT(MAX(fecha_venta), '%d/%m/%Y') as ultima_venta
        FROM ventas 
        WHERE codigo_local = ?
    ");
    $stmt->execute([$local_codigo]);
    $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('Error cargando ventas: ' . $e->getMessage());
    $ventas = [];
    $estadisticas = [];
}

// Configurar título de la página
$pageTitle = APP_NAME . ' - Ventas';
$show_breadcrumb = true;
$breadcrumb_items = [
    ['text' => 'Dashboard', 'url' => BASE_URL . 'dashboard.php'],
    ['text' => 'Panel Empleado', 'url' => BASE_URL . 'modules/empleado/dashboard.php'],
    ['text' => 'Ventas']
];

// Incluir header
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-cash-register text-primary me-2"></i>
            Gestión de Ventas
        </h1>
        <div>
            <a href="nueva_venta.php" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i>
                Nueva Venta
            </a>
            <button class="btn btn-outline-secondary" onclick="window.print()">
                <i class="fas fa-print me-2"></i>
                Imprimir
            </button>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Total Ventas</h6>
                    <h3 class="card-title"><?php echo $estadisticas['total_ventas'] ?? 0; ?></h3>
                    <small class="text-muted">Todas las ventas registradas</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Ingresos Totales</h6>
                    <h3 class="card-title">$<?php echo number_format($estadisticas['total_ingresos'] ?? 0, 2); ?></h3>
                    <small class="text-muted">Acumulado histórico</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Primera Venta</h6>
                    <h3 class="card-title"><?php echo $estadisticas['primera_venta'] ?? '--/--/----'; ?></h3>
                    <small class="text-muted">Fecha inicial</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Última Venta</h6>
                    <h3 class="card-title"><?php echo $estadisticas['ultima_venta'] ?? '--/--/----'; ?></h3>
                    <small class="text-muted">Fecha más reciente</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Ventas -->
    <div class="card shadow">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-list me-2"></i>
                Historial de Ventas
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($ventas)): ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="tablaVentas">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Productos</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ventas as $venta): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($venta['id_venta']); ?></td>
                                    <td>
                                        <?php echo date('d/m/Y H:i', strtotime($venta['fecha_venta'])); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($venta['cliente_nombre'] ?? 'Cliente no registrado'); ?>
                                        <?php if ($venta['cliente_cedula']): ?>
                                            <br><small class="text-muted">CI: <?php echo htmlspecialchars($venta['cliente_cedula']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars(substr($venta['productos'] ?? 'Sin productos', 0, 50)); ?>...</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $venta['items_comprados']; ?></span>
                                    </td>
                                    <td class="fw-bold text-success">
                                        $<?php echo number_format($venta['total'], 2); ?>
                                    </td>
                                    <td>
                                        <a href="detalle_venta.php?id=<?php echo $venta['id_venta']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button class="btn btn-sm btn-outline-secondary"
                                                onclick="imprimirFactura(<?php echo $venta['id_venta']; ?>)">
                                            <i class="fas fa-receipt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">No hay ventas registradas</h5>
                    <p class="text-muted">Comienza registrando tu primera venta</p>
                    <a href="nueva_venta.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle me-2"></i>
                        Crear Primera Venta
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Inicializar DataTable
    $(document).ready(function() {
        $('#tablaVentas').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
            },
            order: [[1, 'desc']],
            pageLength: 10
        });
    });

    function imprimirFactura(idVenta) {
        window.open('imprimir_factura.php?id=' + idVenta, '_blank');
    }
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>