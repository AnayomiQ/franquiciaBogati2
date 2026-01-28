<?php
// modules/empleado/capacitaciones.php

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

// Obtener ID del empleado
$empleado_id = $_SESSION['id_empleado'] ?? null;

// Obtener conexión a la base de datos
$db = Database::getConnection();

try {
    // Obtener capacitaciones asignadas al empleado
    $stmt = $db->prepare("
        SELECT 
            c.*,
            ec.fecha_capacitacion,
            ec.calificacion,
            ec.aprobado,
            CASE 
                WHEN ec.calificacion >= 7 THEN 'APROBADO'
                WHEN ec.calificacion < 7 AND ec.calificacion IS NOT NULL THEN 'REPROBADO'
                WHEN ec.fecha_capacitacion IS NOT NULL AND ec.calificacion IS NULL THEN 'PENDIENTE CALIFICACIÓN'
                ELSE 'PENDIENTE'
            END as estado_capacitacion,
            CASE 
                WHEN ec.fecha_capacitacion IS NULL THEN 'PENDIENTE'
                WHEN ec.fecha_capacitacion > CURDATE() THEN 'PROGRAMADA'
                ELSE 'REALIZADA'
            END as situacion
        FROM capacitaciones c
        LEFT JOIN empleado_capacitacion ec ON c.id_capacitacion = ec.id_capacitacion 
            AND ec.id_empleado = ?
        WHERE c.obligatoria = 1
        ORDER BY ec.fecha_capacitacion DESC, c.nombre ASC
    ");
    $stmt->execute([$empleado_id]);
    $capacitaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener estadísticas
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_capacitaciones,
            SUM(CASE WHEN ec.aprobado = 1 THEN 1 ELSE 0 END) as aprobadas,
            SUM(CASE WHEN ec.calificacion IS NOT NULL AND ec.aprobado = 0 THEN 1 ELSE 0 END) as reprobadas,
            SUM(CASE WHEN ec.fecha_capacitacion IS NULL THEN 1 ELSE 0 END) as pendientes,
            AVG(ec.calificacion) as promedio_calificacion
        FROM capacitaciones c
        LEFT JOIN empleado_capacitacion ec ON c.id_capacitacion = ec.id_capacitacion 
            AND ec.id_empleado = ?
        WHERE c.obligatoria = 1
    ");
    $stmt->execute([$empleado_id]);
    $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener próximas capacitaciones programadas
    $stmt = $db->prepare("
        SELECT 
            c.*,
            ec.fecha_capacitacion
        FROM capacitaciones c
        INNER JOIN empleado_capacitacion ec ON c.id_capacitacion = ec.id_capacitacion 
            AND ec.id_empleado = ?
        WHERE ec.fecha_capacitacion >= CURDATE()
        ORDER BY ec.fecha_capacitacion ASC
        LIMIT 5
    ");
    $stmt->execute([$empleado_id]);
    $proximas_capacitaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('Error cargando capacitaciones: ' . $e->getMessage());
    $capacitaciones = [];
    $estadisticas = [];
    $proximas_capacitaciones = [];
}

// Configurar título de la página
$pageTitle = APP_NAME . ' - Mis Capacitaciones';
$show_breadcrumb = true;
$breadcrumb_items = [
    ['text' => 'Dashboard', 'url' => BASE_URL . 'dashboard.php'],
    ['text' => 'Panel Empleado', 'url' => BASE_URL . 'modules/empleado/dashboard.php'],
    ['text' => 'Capacitaciones']
];

// Incluir header
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-graduation-cap text-primary me-2"></i>
                Mis Capacitaciones
            </h1>
            <p class="text-muted mb-0">Gestiona tu formación y desarrollo profesional</p>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Total Capacitaciones</h6>
                    <h2 class="card-title"><?php echo $estadisticas['total_capacitaciones'] ?? 0; ?></h2>
                    <small class="text-muted">Asignadas</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Aprobadas</h6>
                    <h2 class="card-title text-success"><?php echo $estadisticas['aprobadas'] ?? 0; ?></h2>
                    <small class="text-muted">Completadas con éxito</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Pendientes</h6>
                    <h2 class="card-title text-warning"><?php echo $estadisticas['pendientes'] ?? 0; ?></h2>
                    <small class="text-muted">Por realizar</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Promedio</h6>
                    <h2 class="card-title text-info">
                        <?php 
                        $promedio = $estadisticas['promedio_calificacion'] ?? 0;
                        echo $promedio > 0 ? number_format($promedio, 1) : 'N/A';
                        ?>
                    </h2>
                    <small class="text-muted">Calificación promedio</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Próximas Capacitaciones -->
    <?php if (!empty($proximas_capacitaciones)): ?>
    <div class="card shadow mb-4">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-calendar-check text-success me-2"></i>
                Próximas Capacitaciones
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($proximas_capacitaciones as $capacitacion): ?>
                <div class="col-md-6 mb-3">
                    <div class="card border-start border-success border-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-title mb-1"><?php echo htmlspecialchars($capacitacion['nombre']); ?></h6>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo $capacitacion['duracion_horas']; ?> horas
                                        •
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('d/m/Y', strtotime($capacitacion['fecha_capacitacion'])); ?>
                                    </small>
                                </div>
                                <span class="badge bg-success">Programada</span>
                            </div>
                            <p class="card-text mt-2 small">
                                <?php echo htmlspecialchars(substr($capacitacion['descripcion'] ?? '', 0, 100)); ?>...
                            </p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Lista Completa de Capacitaciones -->
    <div class="card shadow">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="fas fa-list me-2"></i>
                Todas mis Capacitaciones
            </h5>
            <div class="btn-group">
                <button class="btn btn-sm btn-outline-secondary" onclick="filtrarCapacitaciones('TODAS')">Todas</button>
                <button class="btn btn-sm btn-outline-success" onclick="filtrarCapacitaciones('APROBADO')">Aprobadas</button>
                <button class="btn btn-sm btn-outline-warning" onclick="filtrarCapacitaciones('PENDIENTE')">Pendientes</button>
                <button class="btn btn-sm btn-outline-danger" onclick="filtrarCapacitaciones('REPROBADO')">Reprobadas</button>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($capacitaciones)): ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="tablaCapacitaciones">
                        <thead>
                            <tr>
                                <th>Capacitación</th>
                                <th>Tipo</th>
                                <th>Duración</th>
                                <th>Fecha</th>
                                <th>Calificación</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($capacitaciones as $capacitacion): ?>
                            <tr class="fila-capacitacion" data-estado="<?php echo $capacitacion['estado_capacitacion']; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($capacitacion['nombre']); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars(substr($capacitacion['descripcion'] ?? '', 0, 80)); ?>...
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo htmlspecialchars($capacitacion['tipo'] ?? 'General'); ?>
                                    </span>
                                    <?php if ($capacitacion['obligatoria']): ?>
                                        <span class="badge bg-danger">Obligatoria</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $capacitacion['duracion_horas']; ?> horas
                                </td>
                                <td>
                                    <?php if ($capacitacion['fecha_capacitacion']): ?>
                                        <?php echo date('d/m/Y', strtotime($capacitacion['fecha_capacitacion'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No programada</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($capacitacion['calificacion']): ?>
                                        <span class="badge 
                                            <?php echo $capacitacion['calificacion'] >= 7 ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo number_format($capacitacion['calificacion'], 1); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $badge_class = '';
                                    switch($capacitacion['estado_capacitacion']) {
                                        case 'APROBADO':
                                            $badge_class = 'bg-success';
                                            break;
                                        case 'REPROBADO':
                                            $badge_class = 'bg-danger';
                                            break;
                                        case 'PENDIENTE CALIFICACIÓN':
                                            $badge_class = 'bg-warning';
                                            break;
                                        default:
                                            $badge_class = 'bg-secondary';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo $capacitacion['estado_capacitacion']; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="verDetalleCapacitacion(<?php echo $capacitacion['id_capacitacion']; ?>)">
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
                    <i class="fas fa-graduation-cap fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">No hay capacitaciones asignadas</h5>
                    <p class="text-muted">Contacta con tu supervisor para conocer las capacitaciones disponibles</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Función para filtrar capacitaciones
function filtrarCapacitaciones(estado) {
    const filas = document.querySelectorAll('.fila-capacitacion');
    
    filas.forEach(fila => {
        if (estado === 'TODAS') {
            fila.style.display = '';
        } else {
            const filaEstado = fila.getAttribute('data-estado').toUpperCase();
            if (filaEstado.includes(estado)) {
                fila.style.display = '';
            } else {
                fila.style.display = 'none';
            }
        }
    });
}

// Función para ver detalles de capacitación
function verDetalleCapacitacion(idCapacitacion) {
    // Crear modal con información estática ya que no tenemos tabla de detalles
    const modalHTML = `
        <div class="modal fade" id="modalDetalleCapacitacion" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Detalles de la Capacitación</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Los detalles específicos de esta capacitación se cargarán aquí.</p>
                        <p>Para más información, contacta al administrador de capacitaciones.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Crear y mostrar el modal
    const modalContainer = document.createElement('div');
    modalContainer.innerHTML = modalHTML;
    document.body.appendChild(modalContainer);
    
    const modal = new bootstrap.Modal(modalContainer.querySelector('#modalDetalleCapacitacion'));
    modal.show();
    
    // Remover el modal del DOM cuando se cierre
    modalContainer.querySelector('#modalDetalleCapacitacion').addEventListener('hidden.bs.modal', function() {
        document.body.removeChild(modalContainer);
    });
}

// Inicializar DataTable
$(document).ready(function() {
    $('#tablaCapacitaciones').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
        },
        order: [[3, 'desc']],
        pageLength: 10,
        columnDefs: [
            { orderable: false, targets: [6] } // Deshabilitar ordenación en columna Acciones
        ]
    });
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>