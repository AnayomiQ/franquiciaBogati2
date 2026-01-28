<?php
// modules/empleado/inventario.php

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

try {
    // Obtener inventario del local
    $stmt = $db->prepare("
        SELECT 
            i.*,
            p.nombre as producto_nombre,
            p.precio,
            p.costo,
            c.nombre as categoria_nombre,
            ROUND((i.cantidad / i.cantidad_minima) * 100) as porcentaje_stock
        FROM inventario i
        INNER JOIN productos p ON i.codigo_producto = p.codigo_producto
        LEFT JOIN categorias_productos c ON p.id_categoria = c.id_categoria
        WHERE i.codigo_local = ?
        ORDER BY i.cantidad ASC
    ");
    $stmt->execute([$local_codigo]);
    $inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener estadísticas
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_productos,
            SUM(i.cantidad) as total_unidades,
            SUM(CASE WHEN i.cantidad <= i.cantidad_minima THEN 1 ELSE 0 END) as productos_bajos,
            SUM(CASE WHEN i.cantidad = 0 THEN 1 ELSE 0 END) as productos_agotados,
            SUM(p.costo * i.cantidad) as valor_total
        FROM inventario i
        INNER JOIN productos p ON i.codigo_producto = p.codigo_producto
        WHERE i.codigo_local = ?
    ");
    $stmt->execute([$local_codigo]);
    $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener productos bajos en stock
    $stmt = $db->prepare("
        SELECT 
            i.*,
            p.nombre as producto_nombre,
            p.precio,
            c.nombre as categoria_nombre
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
    
} catch (PDOException $e) {
    error_log('Error cargando inventario: ' . $e->getMessage());
    $inventario = [];
    $estadisticas = [];
    $productos_bajos = [];
}

// Configurar título de la página
$pageTitle = APP_NAME . ' - Inventario';
$show_breadcrumb = true;
$breadcrumb_items = [
    ['text' => 'Dashboard', 'url' => BASE_URL . 'dashboard.php'],
    ['text' => 'Panel Empleado', 'url' => BASE_URL . 'modules/empleado/dashboard.php'],
    ['text' => 'Inventario']
];

// Incluir header
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-boxes text-primary me-2"></i>
                Gestión de Inventario
            </h1>
            <p class="text-muted mb-0">Control de stock del local</p>
        </div>
        <button class="btn btn-primary" onclick="actualizarInventario()">
            <i class="fas fa-sync-alt me-2"></i>
            Actualizar Inventario
        </button>
    </div>

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Total Productos</h6>
                    <h2 class="card-title"><?php echo $estadisticas['total_productos'] ?? 0; ?></h2>
                    <small class="text-muted">En inventario</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Total Unidades</h6>
                    <h2 class="card-title text-success"><?php echo $estadisticas['total_unidades'] ?? 0; ?></h2>
                    <small class="text-muted">En stock</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Productos Bajos</h6>
                    <h2 class="card-title text-warning"><?php echo $estadisticas['productos_bajos'] ?? 0; ?></h2>
                    <small class="text-muted">Necesitan reabastecimiento</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Valor Total</h6>
                    <h2 class="card-title text-danger">
                        $<?php echo number_format($estadisticas['valor_total'] ?? 0, 2); ?>
                    </h2>
                    <small class="text-muted">Valor del inventario</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Productos Bajos en Stock -->
    <?php if (!empty($productos_bajos)): ?>
    <div class="card shadow mb-4 border-warning">
        <div class="card-header bg-warning text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Productos que Necesitan Atención
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Stock Actual</th>
                            <th>Mínimo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos_bajos as $producto): ?>
                        <tr class="<?php echo $producto['cantidad'] == 0 ? 'table-danger' : 'table-warning'; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($producto['producto_nombre']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría'); ?></td>
                            <td class="fw-bold"><?php echo $producto['cantidad']; ?></td>
                            <td><?php echo $producto['cantidad_minima']; ?></td>
                            <td>
                                <?php if ($producto['cantidad'] == 0): ?>
                                    <span class="badge bg-danger">AGOTADO</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">BAJO STOCK</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="solicitarReabastecimiento('<?php echo $producto['codigo_producto']; ?>')">
                                    <i class="fas fa-bell me-1"></i>
                                    Solicitar
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Inventario Completo -->
    <div class="card shadow">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="fas fa-list me-2"></i>
                Inventario Completo
            </h5>
            <div class="input-group" style="width: 300px;">
                <input type="text" class="form-control" placeholder="Buscar producto..." id="buscarProducto">
                <button class="btn btn-outline-secondary" type="button">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($inventario)): ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="tablaInventario">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Stock</th>
                                <th>Mínimo</th>
                                <th>Máximo</th>
                                <th>Precio</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventario as $item): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['producto_nombre']); ?></strong>
                                    <br>
                                    <small class="text-muted">Código: <?php echo htmlspecialchars($item['codigo_producto']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($item['categoria_nombre'] ?? 'Sin categoría'); ?></td>
                                <td class="fw-bold"><?php echo $item['cantidad']; ?></td>
                                <td><?php echo $item['cantidad_minima']; ?></td>
                                <td><?php echo $item['cantidad_maxima']; ?></td>
                                <td class="text-success fw-bold">
                                    $<?php echo number_format($item['precio'], 2); ?>
                                </td>
                                <td>
                                    <?php
                                    $porcentaje = $item['porcentaje_stock'];
                                    if ($porcentaje >= 100) {
                                        echo '<span class="badge bg-success">ÓPTIMO</span>';
                                    } elseif ($porcentaje >= 50) {
                                        echo '<span class="badge bg-info">NORMAL</span>';
                                    } elseif ($porcentaje > 0) {
                                        echo '<span class="badge bg-warning">BAJO</span>';
                                    } else {
                                        echo '<span class="badge bg-danger">AGOTADO</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="ajustarStock('<?php echo $item['codigo_producto']; ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-info" 
                                            onclick="verDetalleProducto('<?php echo $item['codigo_producto']; ?>')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">El inventario está vacío</h5>
                    <p class="text-muted">Contacta con el administrador para agregar productos</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Función para solicitar reabastecimiento
function solicitarReabastecimiento(codigoProducto) {
    if (confirm('¿Solicitar reabastecimiento de este producto al encargado?')) {
        // Aquí iría la llamada AJAX para enviar la solicitud
        alert('Solicitud de reabastecimiento enviada para el producto: ' + codigoProducto);
    }
}

// Función para ajustar stock
function ajustarStock(codigoProducto) {
    const cantidad = prompt('Ingrese la nueva cantidad en stock para el producto ' + codigoProducto + ':');
    
    if (cantidad !== null && !isNaN(cantidad) && cantidad >= 0) {
        // Aquí iría la llamada AJAX para actualizar el stock
        alert('Stock actualizado a ' + cantidad + ' unidades');
        location.reload();
    }
}

// Función para ver detalle de producto
function verDetalleProducto(codigoProducto) {
    // Aquí se abriría un modal con los detalles del producto
    alert('Mostrando detalles del producto: ' + codigoProducto);
}

// Función para actualizar inventario
function actualizarInventario() {
    if (confirm('¿Desea actualizar todo el inventario? Esto puede tomar unos momentos.')) {
        // Aquí iría la llamada AJAX para actualizar el inventario
        location.reload();
    }
}

// Inicializar DataTable
$(document).ready(function() {
    $('#tablaInventario').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
        },
        order: [[2, 'asc']],
        pageLength: 10
    });
    
    // Filtro de búsqueda
    $('#buscarProducto').on('keyup', function() {
        $('#tablaInventario').DataTable().search($(this).val()).draw();
    });
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>