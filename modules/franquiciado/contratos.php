<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../db_connection.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

startSession();
requireAuth();
requireAnyRole(['franquiciado']);

$db = Database::getConnection();
$cedula_franquiciado = $_SESSION['cedula_franquiciado'] ?? null;

// Obtener parámetros de filtro
$estado = $_GET['estado'] ?? '';
$local_codigo = $_GET['local'] ?? '';

// Consulta de contratos
$sql = "SELECT cf.*, l.nombre_local, l.codigo_local,
               f.nombres as franquiciado_nombre, f.apellidos as franquiciado_apellidos,
               nf.nombre as nivel_nombre,
               DATEDIFF(cf.fecha_fin, CURDATE()) as dias_restantes
        FROM contratos_franquicia cf
        INNER JOIN locales l ON cf.codigo_local = l.codigo_local
        INNER JOIN franquiciados f ON l.cedula_franquiciado = f.cedula
        LEFT JOIN nivel_franquicia nf ON l.id_nivel = nf.id_nivel
        WHERE l.cedula_franquiciado = ?";
        
$params = [$cedula_franquiciado];

if (!empty($estado)) {
    $sql .= " AND cf.estado = ?";
    $params[] = $estado;
}

if (!empty($local_codigo)) {
    $sql .= " AND cf.codigo_local = ?";
    $params[] = $local_codigo;
}

$sql .= " ORDER BY cf.fecha_inicio DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$total_contratos = count($contratos);
$activos = count(array_filter($contratos, fn($c) => $c['estado'] === 'ACTIVO'));
$por_vencer = count(array_filter($contratos, fn($c) => $c['dias_restantes'] > 0 && $c['dias_restantes'] <= 30));

// Obtener locales para filtro
$locales_sql = "SELECT codigo_local, nombre_local FROM locales WHERE cedula_franquiciado = ?";
$locales_stmt = $db->prepare($locales_sql);
$locales_stmt->execute([$cedula_franquiciado]);
$locales_list = $locales_stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = APP_NAME . ' - Mis Contratos';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-file-contract me-2"></i>Mis Contratos</h2>
            <p class="text-muted mb-0">Gestión de contratos de franquicia</p>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevoContratoModal">
                <i class="fas fa-plus me-2"></i>Nuevo Contrato
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" id="filtroForm">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="estado">
                            <option value="">Todos los estados</option>
                            <option value="ACTIVO" <?php echo $estado === 'ACTIVO' ? 'selected' : ''; ?>>Activos</option>
                            <option value="INACTIVO" <?php echo $estado === 'INACTIVO' ? 'selected' : ''; ?>>Inactivos</option>
                            <option value="FINALIZADO" <?php echo $estado === 'FINALIZADO' ? 'selected' : ''; ?>>Finalizados</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Local</label>
                        <select class="form-select" name="local">
                            <option value="">Todos los locales</option>
                            <?php foreach ($locales_list as $local): ?>
                                <option value="<?php echo $local['codigo_local']; ?>" 
                                    <?php echo $local_codigo === $local['codigo_local'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($local['nombre_local']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="d-grid gap-2 w-100">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-2"></i>Filtrar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Contratos</h6>
                            <h3 class="mb-0"><?php echo $total_contratos; ?></h3>
                        </div>
                        <div class="stat-icon bg-primary">
                            <i class="fas fa-file-alt"></i>
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
                            <h6 class="text-muted mb-2">Contratos Activos</h6>
                            <h3 class="mb-0"><?php echo $activos; ?></h3>
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
                            <h6 class="text-muted mb-2">Por Vencer (30 días)</h6>
                            <h3 class="mb-0"><?php echo $por_vencer; ?></h3>
                        </div>
                        <div class="stat-icon bg-warning">
                            <i class="fas fa-clock"></i>
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
                            <h6 class="text-muted mb-2">Inversión Total</h6>
                            <h3 class="mb-0">$
                                <?php 
                                $total_inversion = array_sum(array_column($contratos, 'inversion_total'));
                                echo number_format($total_inversion, 2);
                                ?>
                            </h3>
                        </div>
                        <div class="stat-icon bg-info">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alertas de Vencimiento -->
    <?php 
    $contratos_por_vencer = array_filter($contratos, fn($c) => $c['dias_restantes'] > 0 && $c['dias_restantes'] <= 60);
    if (!empty($contratos_por_vencer)): ?>
    <div class="alert alert-warning mb-4">
        <div class="d-flex align-items-center">
            <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
            <div>
                <h5 class="alert-heading mb-1">¡Contratos por vencer!</h5>
                <p class="mb-0">Tienes <?php echo count($contratos_por_vencer); ?> contratos que vencen en los próximos 60 días.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabla de Contratos -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="contratosTable">
                    <thead>
                        <tr>
                            <th>N° Contrato</th>
                            <th>Local</th>
                            <th>Periodo</th>
                            <th>Inversión</th>
                            <th>Royalty</th>
                            <th>Días Restantes</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($contratos)): ?>
                            <?php foreach ($contratos as $contrato): ?>
                                <?php 
                                $dias_class = 'success';
                                if ($contrato['dias_restantes'] <= 30) $dias_class = 'danger';
                                elseif ($contrato['dias_restantes'] <= 60) $dias_class = 'warning';
                                
                                $estado_class = [
                                    'ACTIVO' => 'success',
                                    'INACTIVO' => 'secondary',
                                    'FINALIZADO' => 'warning'
                                ][$contrato['estado']] ?? 'light';
                                ?>
                                <tr>
                                    <td>
                                        <strong>#<?php echo htmlspecialchars($contrato['numero_contrato']); ?></strong>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-store text-primary me-2"></i>
                                            <span><?php echo htmlspecialchars($contrato['nombre_local']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <div><?php echo date('d/m/Y', strtotime($contrato['fecha_inicio'])); ?></div>
                                            <small class="text-muted">
                                                hasta <?php echo date('d/m/Y', strtotime($contrato['fecha_fin'])); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <strong class="text-primary">$<?php echo number_format($contrato['inversion_total'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <span class="badge bg-light text-dark">
                                                Royalty: <?php echo $contrato['royalty']; ?>%
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                Pub: <?php echo $contrato['canon_publicidad']; ?>%
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($contrato['dias_restantes'] > 0): ?>
                                            <span class="badge bg-<?php echo $dias_class; ?>">
                                                <?php echo $contrato['dias_restantes']; ?> días
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Vencido</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $estado_class; ?>">
                                            <?php echo htmlspecialchars($contrato['estado']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="detalle_contrato.php?id=<?php echo $contrato['id_contrato']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button class="btn btn-sm btn-outline-info" 
                                                    onclick="imprimirContrato(<?php echo $contrato['id_contrato']; ?>)">
                                                <i class="fas fa-print"></i>
                                            </button>
                                            <?php if ($contrato['estado'] === 'ACTIVO'): ?>
                                            <button class="btn btn-sm btn-outline-success" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#renovarContratoModal"
                                                    onclick="cargarContratoRenovar(<?php echo $contrato['id_contrato']; ?>)">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="fas fa-file-contract fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No hay contratos registrados</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevoContratoModal">
                                        <i class="fas fa-plus me-2"></i>Crear primer contrato
                                    </button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nuevo Contrato -->
<div class="modal fade" id="nuevoContratoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo Contrato de Franquicia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="procesar_contrato.php" method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Número de Contrato *</label>
                            <input type="text" class="form-control" name="numero_contrato" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Local *</label>
                            <select class="form-select" name="codigo_local" required>
                                <option value="">Seleccionar local...</option>
                                <?php foreach ($locales_list as $local): ?>
                                    <option value="<?php echo $local['codigo_local']; ?>">
                                        <?php echo htmlspecialchars($local['nombre_local']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha Inicio *</label>
                            <input type="date" class="form-control" name="fecha_inicio" required 
                                   value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha Fin *</label>
                            <input type="date" class="form-control" name="fecha_fin" required 
                                   value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Inversión Total ($) *</label>
                            <input type="number" step="0.01" class="form-control" name="inversion_total" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Royalty (%) *</label>
                            <input type="number" step="0.01" class="form-control" name="royalty" value="5.00" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Canon Publicidad (%) *</label>
                            <input type="number" step="0.01" class="form-control" name="canon_publicidad" value="2.00" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estado *</label>
                            <select class="form-select" name="estado" required>
                                <option value="ACTIVO" selected>Activo</option>
                                <option value="INACTIVO">Inactivo</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Contrato</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Renovar Contrato -->
<div class="modal fade" id="renovarContratoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Renovar Contrato</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="renovar_contrato.php" method="POST">
                <input type="hidden" name="contrato_id" id="contrato_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nueva Fecha Inicio</label>
                        <input type="date" class="form-control" name="nueva_fecha_inicio" 
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nueva Fecha Fin</label>
                        <input type="date" class="form-control" name="nueva_fecha_fin" 
                               value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ajuste Royalty (%)</label>
                        <input type="number" step="0.01" class="form-control" name="ajuste_royalty" value="0">
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Al renovar el contrato, se mantendrán los demás términos y condiciones.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Renovar Contrato</button>
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
        box-shadow: var(--card-shadow);
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
        background-color: #f8f9fa;
    }
    
    .table td {
        vertical-align: middle;
    }
    
    .alert-warning {
        border-left: 4px solid var(--warning-color);
    }
</style>

<script>
    function imprimirContrato(id) {
        window.open('imprimir_contrato.php?id=' + id, '_blank');
    }
    
    function cargarContratoRenovar(id) {
        document.getElementById('contrato_id').value = id;
        // Aquí podrías cargar datos actuales del contrato usando AJAX
    }
    
    // Validar fechas en nuevo contrato
    document.addEventListener('DOMContentLoaded', function() {
        const fechaInicio = document.querySelector('input[name="fecha_inicio"]');
        const fechaFin = document.querySelector('input[name="fecha_fin"]');
        
        if (fechaInicio && fechaFin) {
            fechaInicio.addEventListener('change', function() {
                const minFechaFin = new Date(this.value);
                minFechaFin.setDate(minFechaFin.getDate() + 1);
                fechaFin.min = minFechaFin.toISOString().split('T')[0];
                fechaFin.value = fechaFin.min;
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>