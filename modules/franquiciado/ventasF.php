<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../db_connection.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

startSession();
requireAuth();
requireAnyRole(['admin', 'franquiciado', 'empleado']);

$db = Database::getConnection();
$userRole = getCurrentUserRole();
$local_codigo = $_SESSION['codigo_local'] ?? null;
$cedula_franquiciado = $_SESSION['cedula_franquiciado'] ?? null;

// Determinar filtro según rol
$where = '';
$params = [];

if ($userRole === 'empleado') {
    $where = "WHERE v.codigo_local = ?";
    $params[] = $local_codigo;
} elseif ($userRole === 'franquiciado') {
    $where = "WHERE l.cedula_franquiciado = ?";
    $params[] = $cedula_franquiciado;
}

$sql = "SELECT v.*, l.nombre_local, 
               CONCAT(c.nombres, ' ', c.apellidos) as cliente_nombre,
               c.cedula as cliente_cedula,
               COUNT(dv.id_detalle) as items
        FROM ventas v
        LEFT JOIN locales l ON v.codigo_local = l.codigo_local
        LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
        LEFT JOIN detalle_ventas dv ON v.id_venta = dv.id_venta
        $where
        GROUP BY v.id_venta
        ORDER BY v.fecha_venta DESC
        LIMIT 50";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
// Estadísticas
$stats_sql = "
    SELECT 
        COUNT(*) AS total_ventas,
        COALESCE(SUM(v.total), 0) AS total_ingresos,
        COALESCE(AVG(v.total), 0) AS promedio,
        COUNT(DISTINCT v.id_cliente) AS clientes_unicos
    FROM ventas v
    JOIN locales l ON v.codigo_local = l.codigo_local
";

if ($where) {
    $stats_sql .= " $where";
    $stmt = $db->prepare($stats_sql);
    $stmt->execute($params);
} else {
    $stmt = $db->query($stats_sql);
}


$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$pageTitle = APP_NAME . ' - Gestión de Ventas';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-shopping-cart me-2"></i>Gestión de Ventas</h2>
            <p class="text-muted mb-0">Registro y consulta de todas las ventas</p>
        </div>
        <div>
            <a href="nueva_venta.php" class="btn btn-primary me-2">
                <i class="fas fa-plus me-2"></i>Nueva Venta
            </a>
            <button class="btn btn-outline-primary" onclick="exportToExcel()">
                <i class="fas fa-download me-2"></i>Exportar
            </button>
        </div>
    </div>

    <!-- Filtros Rápidos -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                <input type="date" class="form-control" id="fechaInicio" value="<?php echo date('Y-m-01'); ?>">
            </div>
        </div>
        <div class="col-md-3">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                <input type="date" class="form-control" id="fechaFin" value="<?php echo date('Y-m-d'); ?>">
            </div>
        </div>
        <div class="col-md-3">
            <select class="form-select" id="filtroLocal">
                <option value="">Todos los locales</option>
                <?php
                $locales_sql = "SELECT codigo_local, nombre_local FROM locales";
                if ($userRole === 'franquiciado') {
                    $locales_sql .= " WHERE cedula_franquiciado = ?";
                    $stmt = $db->prepare($locales_sql);
                    $stmt->execute([$cedula_franquiciado]);
                } else {
                    $stmt = $db->query($locales_sql);
                }
                $locales_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($locales_list as $local): ?>
                    <option value="<?php echo $local['codigo_local']; ?>">
                        <?php echo htmlspecialchars($local['nombre_local']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn btn-primary w-100" onclick="filtrarVentas()">
                <i class="fas fa-filter me-2"></i>Filtrar
            </button>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Ventas Totales</h6>
                            <h3 class="mb-0"><?php echo $stats['total_ventas']; ?></h3>
                        </div>
                        <div class="stat-icon bg-primary">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Ingresos Totales</h6>
                            <h3 class="mb-0">$<?php echo number_format($stats['total_ingresos'], 2); ?></h3>
                        </div>
                        <div class="stat-icon bg-success">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Ticket Promedio</h6>
                            <h3 class="mb-0">$<?php echo number_format($stats['promedio'], 2); ?></h3>
                        </div>
                        <div class="stat-icon bg-info">
                            <i class="fas fa-receipt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Clientes Únicos</h6>
                            <h3 class="mb-0"><?php echo $stats['clientes_unicos']; ?></h3>
                        </div>
                        <div class="stat-icon bg-warning">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico de Ventas (Placeholder) -->
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Tendencia de Ventas</h5>
        </div>
        <div class="card-body">
            <div id="ventasChart" style="height: 300px;">
                <div class="text-center py-5">
                    <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Gráfico de ventas se cargará aquí</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Ventas -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="ventasTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Local</th>
                            <th>Cliente</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Forma Pago</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ventas as $venta): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        #<?php echo $venta['id_venta']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y H:i', strtotime($venta['fecha_venta'])); ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-store text-primary me-2"></i>
                                        <span><?php echo htmlspecialchars($venta['nombre_local']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($venta['cliente_nombre']): ?>
                                        <div>
                                            <div><?php echo htmlspecialchars($venta['cliente_nombre']); ?></div>
                                            <small class="text-muted"><?php echo $venta['cliente_cedula']; ?></small>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No registrado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $venta['items']; ?> items</span>
                                </td>
                                <td>
                                    <strong class="text-success">$<?php echo number_format($venta['total'], 2); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        <?php echo htmlspecialchars($venta['forma_pago'] ?? 'Efectivo'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="detalle_venta.php?id=<?php echo $venta['id_venta']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button class="btn btn-sm btn-outline-info" 
                                                onclick="imprimirFactura(<?php echo $venta['id_venta']; ?>)">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <?php if ($userRole === 'admin'): ?>
                                        <a href="editar_venta.php?id=<?php echo $venta['id_venta']; ?>" 
                                           class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div>
                    <span class="text-muted">
                        Mostrando <?php echo count($ventas); ?> ventas
                    </span>
                </div>
                <nav>
                    <ul class="pagination mb-0">
                        <li class="page-item">
                            <a class="page-link" href="#"><i class="fas fa-chevron-left"></i></a>
                        </li>
                        <li class="page-item active">
                            <a class="page-link" href="#">1</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="#">2</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="#"><i class="fas fa-chevron-right"></i></a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<style>
    .stat-card {
        border: none;
        border-radius: 12px;
        transition: transform 0.3s ease;
        margin-bottom: 1rem;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--hover-shadow);
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
    }
    
    .table th {
        border-top: none;
        border-bottom: 2px solid #f0f0f0;
        font-weight: 600;
        color: #6c757d;
    }
    
    .input-group-text {
        background-color: #f8f9fa;
        border-right: none;
    }
</style>

<script>
    function filtrarVentas() {
        const inicio = document.getElementById('fechaInicio').value;
        const fin = document.getElementById('fechaFin').value;
        const local = document.getElementById('filtroLocal').value;
        
        // Aquí iría la lógica para filtrar las ventas
        // Por ahora solo mostramos un mensaje
        alert(`Filtrando ventas del ${inicio} al ${fin}${local ? ' para local: ' + local : ''}`);
    }
    
    function exportToExcel() {
        // Lógica para exportar a Excel
        alert('Exportando datos a Excel...');
    }
    
    function imprimirFactura(id) {
        window.open('imprimir_factura.php?id=' + id, '_blank');
    }
    
    // Inicializar datepickers
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('fechaFin').max = today;
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>