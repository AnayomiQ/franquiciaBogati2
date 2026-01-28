<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../db_connection.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

startSession();
requireAuth();
requireAnyRole(['admin', 'franquiciado']);

$db = Database::getConnection();
$userRole = getCurrentUserRole();
$cedula_franquiciado = $_SESSION['cedula_franquiciado'] ?? null;

// Consulta según rol
if ($userRole === 'franquiciado') {
    $sql = "SELECT l.*, nf.nombre as nivel_nombre, cf.estado as estado_contrato 
            FROM locales l 
            LEFT JOIN nivel_franquicia nf ON l.id_nivel = nf.id_nivel 
            LEFT JOIN contratos_franquicia cf ON l.codigo_local = cf.codigo_local 
            WHERE l.cedula_franquiciado = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$cedula_franquiciado]);
} else {
    $sql = "SELECT l.*, f.nombres as franquiciado_nombre, f.apellidos as franquiciado_apellidos, 
                   nf.nombre as nivel_nombre, cf.estado as estado_contrato 
            FROM locales l 
            LEFT JOIN franquiciados f ON l.cedula_franquiciado = f.cedula 
            LEFT JOIN nivel_franquicia nf ON l.id_nivel = nf.id_nivel 
            LEFT JOIN contratos_franquicia cf ON l.codigo_local = cf.codigo_local";
    $stmt = $db->prepare($sql);
    $stmt->execute();
}

$locales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = APP_NAME . ' - Gestión de Locales';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-store me-2"></i>Gestión de Locales</h2>
            <p class="text-muted mb-0">Administra todos los locales de la franquicia</p>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevoLocalModal">
                <i class="fas fa-plus me-2"></i>Nuevo Local
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" placeholder="Nombre o ciudad..." id="searchInput">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select class="form-select" id="filterEstado">
                        <option value="">Todos</option>
                        <option value="ACTIVO">Activos</option>
                        <option value="INACTIVO">Inactivos</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ciudad</label>
                    <select class="form-select" id="filterCiudad">
                        <option value="">Todas</option>
                        <?php
                        $ciudades = array_unique(array_column($locales, 'ciudad'));
                        foreach ($ciudades as $ciudad):
                            if ($ciudad): ?>
                                <option value="<?php echo htmlspecialchars($ciudad); ?>">
                                    <?php echo htmlspecialchars($ciudad); ?>
                                </option>
                            <?php endif;
                        endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="resetFilters()">
                        <i class="fas fa-redo me-2"></i>Limpiar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Locales</h6>
                            <h3 class="mb-0"><?php echo count($locales); ?></h3>
                        </div>
                        <div class="stat-icon bg-primary">
                            <i class="fas fa-store"></i>
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
                            <h6 class="text-muted mb-2">Activos</h6>
                            <h3 class="mb-0"><?php echo count(array_filter($locales, fn($l) => ($l['estado_contrato'] ?? '') === 'ACTIVO')); ?></h3>
                        </div>
                        <div class="stat-icon bg-success">
                            <i class="fas fa-check-circle"></i>
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
                            <h6 class="text-muted mb-2">Ciudades</h6>
                            <h3 class="mb-0"><?php echo count(array_unique(array_filter(array_column($locales, 'ciudad')))); ?></h3>
                        </div>
                        <div class="stat-icon bg-info">
                            <i class="fas fa-map-marker-alt"></i>
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
                            <h6 class="text-muted mb-2">Ingresos Mensuales</h6>
                            <h3 class="mb-0">$<?php 
                                // Esta sería una consulta real de ventas
                                echo '0.00';
                            ?></h3>
                        </div>
                        <div class="stat-icon bg-warning">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Locales -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="localesTable">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Ubicación</th>
                            <th>Franquiciado</th>
                            <th>Nivel</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locales as $local): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($local['codigo_local']); ?></strong>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="fas fa-store fa-lg text-primary"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($local['nombre_local']); ?></h6>
                                            <small class="text-muted">
                                                <i class="fas fa-phone me-1"></i>
                                                <?php echo htmlspecialchars($local['telefono'] ?? 'N/A'); ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <div><?php echo htmlspecialchars($local['ciudad']); ?></div>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($local['provincia']); ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($userRole === 'admin'): ?>
                                        <div>
                                            <?php echo htmlspecialchars($local['franquiciado_nombre'] ?? 'N/A') . ' ' . 
                                                   htmlspecialchars($local['franquiciado_apellidos'] ?? ''); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-info">Propio</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo htmlspecialchars($local['nivel_nombre'] ?? 'SIN NIVEL'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php $estado = $local['estado_contrato'] ?? 'SIN CONTRATO'; ?>
                                    <span class="badge bg-<?php 
                                        echo $estado === 'ACTIVO' ? 'success' : 
                                             ($estado === 'INACTIVO' ? 'danger' : 'warning'); 
                                    ?>">
                                        <?php echo htmlspecialchars($estado); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="local_detalle.php?codigo=<?php echo $local['codigo_local']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="editar_local.php?codigo=<?php echo $local['codigo_local']; ?>" 
                                           class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="confirmDelete('<?php echo $local['codigo_local']; ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
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
                        Mostrando <?php echo count($locales); ?> de <?php echo count($locales); ?> locales
                    </span>
                </div>
                <nav>
                    <ul class="pagination mb-0">
                        <li class="page-item disabled">
                            <a class="page-link" href="#">Anterior</a>
                        </li>
                        <li class="page-item active">
                            <a class="page-link" href="#">1</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="#">2</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="#">Siguiente</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nuevo Local -->
<div class="modal fade" id="nuevoLocalModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo Local</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="procesar_local.php" method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Código del Local</label>
                            <input type="text" class="form-control" name="codigo_local" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre del Local</label>
                            <input type="text" class="form-control" name="nombre_local" value="Bogati">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Dirección</label>
                            <input type="text" class="form-control" name="direccion" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ciudad</label>
                            <input type="text" class="form-control" name="ciudad" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Provincia</label>
                            <input type="text" class="form-control" name="provincia" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" name="telefono">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Área (m²)</label>
                            <input type="number" step="0.01" class="form-control" name="area_local">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha Apertura</label>
                            <input type="date" class="form-control" name="fecha_apertura">
                        </div>
                        <?php if ($userRole === 'admin'): ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Franquiciado</label>
                            <select class="form-select" name="cedula_franquiciado">
                                <option value="">Seleccionar...</option>
                                <?php
                                $stmt = $db->query("SELECT cedula, nombres, apellidos FROM franquiciados");
                                $franquiciados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($franquiciados as $f): ?>
                                    <option value="<?php echo $f['cedula']; ?>">
                                        <?php echo htmlspecialchars($f['nombres'] . ' ' . $f['apellidos']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nivel de Franquicia</label>
                            <select class="form-select" name="id_nivel">
                                <option value="">Seleccionar...</option>
                                <?php
                                $stmt = $db->query("SELECT id_nivel, nombre FROM nivel_franquicia");
                                $niveles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($niveles as $n): ?>
                                    <option value="<?php echo $n['id_nivel']; ?>">
                                        <?php echo htmlspecialchars($n['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Local</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .stat-card {
        border: none;
        border-radius: 12px;
        transition: transform 0.3s ease;
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
    
    .table td {
        vertical-align: middle;
    }
</style>

<script>
    // Filtros
    document.getElementById('searchInput').addEventListener('keyup', function() {
        filterTable();
    });
    
    document.getElementById('filterEstado').addEventListener('change', filterTable);
    document.getElementById('filterCiudad').addEventListener('change', filterTable);
    
    function filterTable() {
        const search = document.getElementById('searchInput').value.toLowerCase();
        const estado = document.getElementById('filterEstado').value;
        const ciudad = document.getElementById('filterCiudad').value;
        
        document.querySelectorAll('#localesTable tbody tr').forEach(row => {
            const text = row.textContent.toLowerCase();
            const rowEstado = row.cells[5].textContent.trim();
            const rowCiudad = row.cells[2].textContent.trim();
            
            const matchSearch = text.includes(search);
            const matchEstado = !estado || rowEstado === estado;
            const matchCiudad = !ciudad || rowCiudad.includes(ciudad);
            
            row.style.display = (matchSearch && matchEstado && matchCiudad) ? '' : 'none';
        });
    }
    
    function resetFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('filterEstado').value = '';
        document.getElementById('filterCiudad').value = '';
        filterTable();
    }
    
    function confirmDelete(codigo) {
        if (confirm('¿Está seguro de eliminar este local? Esta acción no se puede deshacer.')) {
            window.location.href = 'eliminar_local.php?codigo=' + codigo;
        }
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>