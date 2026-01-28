<?php
// modules/admin/capacitaciones_gestion.php

require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../db_connection.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

startSession();
requireAuth();
requireAnyRole(['admin']);

$db = Database::getConnection();

// DEBUG: Mostrar mensajes de error
echo "<!-- DEBUG: Script cargado -->";

/* ======================
   PROCESAR FORMULARIOS
====================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<!-- DEBUG: Método POST detectado -->";
    
    // APROBAR CALIFICACIÓN
    if (isset($_POST['aprobar']) && isset($_POST['id_empleado']) && isset($_POST['id_capacitacion'])) {
        echo "<!-- DEBUG: Procesando aprobación -->";
        
        $id_empleado = intval($_POST['id_empleado']);
        $id_capacitacion = intval($_POST['id_capacitacion']);
        $calificacion = floatval($_POST['calificacion']);
        $aprobado = $calificacion >= 7 ? 1 : 0;

        echo "<!-- DEBUG: id_empleado=$id_empleado, id_capacitacion=$id_capacitacion, calificacion=$calificacion -->";
        
        try {
            // Verificar si existe el registro primero
            $check = $db->prepare("SELECT * FROM empleado_capacitacion WHERE id_empleado = ? AND id_capacitacion = ?");
            $check->execute([$id_empleado, $id_capacitacion]);
            $existe = $check->fetch();
            
            if ($existe) {
                // Actualizar calificación
                $stmt = $db->prepare("
                    UPDATE empleado_capacitacion 
                    SET calificacion = ?, aprobado = ? 
                    WHERE id_empleado = ? AND id_capacitacion = ?
                ");
                $resultado = $stmt->execute([$calificacion, $aprobado, $id_empleado, $id_capacitacion]);
                
                if ($resultado) {
                    setFlashMessage('success', "✅ Calificación registrada: $calificacion/10");
                    echo "<!-- DEBUG: Calificación actualizada exitosamente -->";
                } else {
                    setFlashMessage('error', "❌ Error al actualizar calificación");
                    echo "<!-- DEBUG: Error en execute() -->";
                }
            } else {
                setFlashMessage('warning', "⚠️ No se encontró el registro para actualizar");
                echo "<!-- DEBUG: Registro no encontrado -->";
            }
            
        } catch (Exception $e) {
            setFlashMessage('error', "❌ Error: " . $e->getMessage());
            echo "<!-- DEBUG: Excepción: " . $e->getMessage() . " -->";
        }
    }
    
    // ASIGNAR EMPLEADO
    elseif (isset($_POST['asignar_empleado']) && isset($_POST['empleado_id']) && isset($_POST['capacitacion_id'])) {
        echo "<!-- DEBUG: Procesando asignación -->";
        
        $empleado_id = intval($_POST['empleado_id']);
        $capacitacion_id = intval($_POST['capacitacion_id']);
        $fecha_asignacion = $_POST['fecha_asignacion'];
        
        try {
            // Verificar que empleado y capacitación existen
            $check_emp = $db->prepare("SELECT id_empleado FROM empleados WHERE id_empleado = ?");
            $check_emp->execute([$empleado_id]);
            
            $check_cap = $db->prepare("SELECT id_capacitacion FROM capacitaciones WHERE id_capacitacion = ?");
            $check_cap->execute([$capacitacion_id]);
            
            if ($check_emp->fetch() && $check_cap->fetch()) {
                // Insertar asignación
                $stmt = $db->prepare("
                    INSERT INTO empleado_capacitacion (id_empleado, id_capacitacion, fecha_capacitacion)
                    VALUES (?, ?, ?)
                ");
                $resultado = $stmt->execute([$empleado_id, $capacitacion_id, $fecha_asignacion]);
                
                if ($resultado) {
                    setFlashMessage('success', "✅ Empleado asignado exitosamente");
                    echo "<!-- DEBUG: Asignación exitosa -->";
                } else {
                    setFlashMessage('error', "❌ Error al asignar empleado");
                    echo "<!-- DEBUG: Error en insert -->";
                }
            } else {
                setFlashMessage('warning', "⚠️ Empleado o capacitación no encontrados");
                echo "<!-- DEBUG: Empleado o capacitación no existen -->";
            }
            
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                setFlashMessage('warning', "⚠️ Este empleado ya está asignado a esta capacitación");
            } else {
                setFlashMessage('error', "❌ Error: " . $e->getMessage());
            }
            echo "<!-- DEBUG: Excepción: " . $e->getMessage() . " -->";
        }
    }
    
    // Redirigir para evitar reenvío de formulario
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/* ======================
   OBTENER DATOS
====================== */
echo "<!-- DEBUG: Iniciando obtención de datos -->";

try {
    // 1. OBTENER CAPACITACIONES
    $stmt = $db->query("SELECT * FROM capacitaciones ORDER BY nombre");
    $capacitaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<!-- DEBUG: Capacitaciones encontradas: " . count($capacitaciones) . " -->";
    
    // 2. OBTENER EMPLEADOS
    $stmt = $db->query("
        SELECT e.*, l.nombre_local 
        FROM empleados e
        LEFT JOIN locales l ON e.codigo_local = l.codigo_local
        WHERE e.estado = 'ACTIVO'
        ORDER BY e.nombres
    ");
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<!-- DEBUG: Empleados encontrados: " . count($empleados) . " -->";
    
    // 3. OBTENER ASIGNACIONES CON DATOS
    $stmt = $db->query("
        SELECT 
            ec.id_empleado,
            ec.id_capacitacion,
            ec.fecha_capacitacion,
            ec.calificacion,
            ec.aprobado,
            CONCAT(e.nombres, ' ', e.apellidos) as empleado_nombre,
            e.cargo,
            l.nombre_local,
            c.nombre as capacitacion_nombre,
            c.tipo,
            c.duracion_horas
        FROM empleado_capacitacion ec
        INNER JOIN empleados e ON ec.id_empleado = e.id_empleado
        LEFT JOIN locales l ON e.codigo_local = l.codigo_local
        INNER JOIN capacitaciones c ON ec.id_capacitacion = c.id_capacitacion
        ORDER BY ec.fecha_capacitacion DESC, e.nombres
    ");
    $asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<!-- DEBUG: Asignaciones encontradas: " . count($asignaciones) . " -->";
    
    // 4. ESTADÍSTICAS
    $total_asignaciones = count($asignaciones);
    $pendientes = 0;
    $aprobados = 0;
    $reprobados = 0;
    
    foreach ($asignaciones as $asig) {
        if ($asig['calificacion'] === null) {
            $pendientes++;
        } elseif ($asig['aprobado'] == 1) {
            $aprobados++;
        } else {
            $reprobados++;
        }
    }
    
    
} catch (Exception $e) {
    echo "<!-- DEBUG: Error en consultas: " . $e->getMessage() . " -->";
    $capacitaciones = [];
    $empleados = [];
    $asignaciones = [];
    $total_asignaciones = 0;
    $pendientes = 0;
    $aprobados = 0;
    $reprobados = 0;
    
    setFlashMessage('error', "❌ Error al cargar datos: " . $e->getMessage());
}

// CAPACITACIONES DISPONIBLES
$stmtCap = $db->query("SELECT * FROM vw_capacitaciones ORDER BY obligatoria DESC, nombre");
$capacitaciones = $stmtCap->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = APP_NAME . ' - Gestión de Capacitaciones';
require_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- DEBUG INFO (quitar en producción) -->
    <?php if (isset($_GET['debug'])): ?>
    <div class="alert alert-info">
        <h5>Información de Depuración</h5>
        <p>Capacitaciones: <?php echo count($capacitaciones); ?></p>
        <p>Empleados: <?php echo count($empleados); ?></p>
        <p>Asignaciones: <?php echo count($asignaciones); ?></p>
        <p>Pendientes: <?php echo $pendientes; ?></p>
        <p>Aprobados: <?php echo $aprobados; ?></p>
        <p>Reprobados: <?php echo $reprobados; ?></p>
    </div>
    <?php endif; ?>

    <!-- ENCABEZADO -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-user-check text-primary me-2"></i>
                Gestión de Capacitaciones
            </h1>
            <p class="text-muted mb-0">Administración de capacitaciones y evaluaciones</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAsignar">
            <i class="fas fa-user-plus me-2"></i>
            Asignar Empleado
        </button>
    </div>

    <!-- ESTADÍSTICAS -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Total Asignaciones</h6>
                    <h2 class="card-title"><?php echo $total_asignaciones; ?></h2>
                    <small class="text-muted">Registros en el sistema</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Pendientes</h6>
                    <h2 class="card-title text-warning"><?php echo $pendientes; ?></h2>
                    <small class="text-muted">Por evaluar</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Aprobados</h6>
                    <h2 class="card-title text-success"><?php echo $aprobados; ?></h2>
                    <small class="text-muted">Evaluaciones aprobadas</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Reprobados</h6>
                    <h2 class="card-title text-danger"><?php echo $reprobados; ?></h2>
                    <small class="text-muted">Evaluaciones reprobadas</small>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLA PRINCIPAL -->
    <div class="card shadow">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="fas fa-table me-2"></i>
                Asignaciones de Capacitaciones
            </h5>
            <div class="btn-group">
                <button class="btn btn-sm btn-outline-secondary" onclick="filtrarTabla('TODOS')">Todos</button>
                <button class="btn btn-sm btn-outline-warning" onclick="filtrarTabla('PENDIENTE')">Pendientes</button>
                <button class="btn btn-sm btn-outline-success" onclick="filtrarTabla('APROBADO')">Aprobados</button>
                <button class="btn btn-sm btn-outline-danger" onclick="filtrarTabla('REPROBADO')">Reprobados</button>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($asignaciones)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-graduation-cap fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">No hay asignaciones registradas</h5>
                    <p class="text-muted">Comienza asignando empleados a capacitaciones</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAsignar">
                        <i class="fas fa-user-plus me-2"></i>
                        Crear Primera Asignación
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="tablaAsignaciones">
                        <thead>
                            <tr>
                                <th>Empleado</th>
                                <th>Cargo</th>
                                <th>Local</th>
                                <th>Capacitación</th>
                                <th>Fecha</th>
                                <th>Calificación</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($asignaciones as $asig): 
                                $estado = '';
                                $clase_fila = '';
                                
                                if ($asig['calificacion'] === null) {
                                    $estado = 'PENDIENTE';
                                    $clase_fila = 'fila-pendiente';
                                } elseif ($asig['aprobado'] == 1) {
                                    $estado = 'APROBADO';
                                    $clase_fila = 'fila-aprobada';
                                } else {
                                    $estado = 'REPROBADO';
                                    $clase_fila = 'fila-reprobada';
                                }
                            ?>
                            <tr class="fila-asignacion <?php echo $clase_fila; ?>" data-estado="<?php echo $estado; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($asig['empleado_nombre']); ?></strong>
                                    <br>
                                    <small class="text-muted">ID: <?php echo $asig['id_empleado']; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($asig['cargo']); ?></td>
                                <td><?php echo htmlspecialchars($asig['nombre_local']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($asig['capacitacion_nombre']); ?>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($asig['tipo']); ?> • 
                                        <?php echo $asig['duracion_horas']; ?> horas
                                    </small>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($asig['fecha_capacitacion'])); ?>
                                </td>
                                <td class="text-center fw-bold">
                                    <?php if ($asig['calificacion'] !== null): ?>
                                        <span class="badge <?php echo $asig['aprobado'] ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo number_format($asig['calificacion'], 1); ?>/10
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($asig['calificacion'] === null): ?>
                                        <span class="badge bg-warning">Pendiente</span>
                                    <?php elseif ($asig['aprobado']): ?>
                                        <span class="badge bg-success">Aprobado</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Reprobado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($asig['calificacion'] === null): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="id_empleado" value="<?php echo $asig['id_empleado']; ?>">
                                            <input type="hidden" name="id_capacitacion" value="<?php echo $asig['id_capacitacion']; ?>">
                                            
                                            <div class="input-group input-group-sm" style="width: 120px;">
                                                <input type="number" name="calificacion" step="0.1" min="0" max="10"
                                                       class="form-control" placeholder="0-10" required>
                                                <button class="btn btn-success" type="submit" name="aprobar" title="Aprobar">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">Evaluado</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- TABLA CAPACITACIONES DISPONIBLES -->
<div class="card shadow mt-5">
    <div class="card-header bg-white">
        <h5 class="card-title mb-0">
            <i class="fas fa-book me-2"></i>
            Capacitaciones Disponibles
        </h5>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Duración</th>
                        <th>Tipo</th>
                        <th>Obligatoria</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($capacitaciones as $cap): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($cap['nombre']) ?></strong></td>
                            <td><?= htmlspecialchars($cap['descripcion']) ?></td>
                            <td><?= $cap['duracion_horas'] ?> horas</td>
                            <td>
                                <span class="badge bg-info">
                                    <?= htmlspecialchars($cap['tipo']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($cap['obligatoria']): ?>
                                    <span class="badge bg-danger">Sí</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">No</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL: ASIGNAR EMPLEADO -->
<div class="modal fade" id="modalAsignar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Asignar Empleado a Capacitación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="asignar_empleado" value="1">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Seleccionar Empleado</label>
                        <select class="form-select" name="empleado_id" required>
                            <option value="">Seleccionar empleado...</option>
                            <?php foreach ($empleados as $emp): ?>
                            <option value="<?php echo $emp['id_empleado']; ?>">
                                <?php echo htmlspecialchars($emp['nombres'] . ' ' . $emp['apellidos']); ?>
                                - <?php echo htmlspecialchars($emp['nombre_local']); ?>
                                (<?php echo htmlspecialchars($emp['cargo']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Seleccionar Capacitación</label>
                        <select class="form-select" name="capacitacion_id" required>
                            <option value="">Seleccionar capacitación...</option>
                            <?php foreach ($capacitaciones as $cap): ?>
                            <option value="<?php echo $cap['id_capacitacion']; ?>">
                                <?php echo htmlspecialchars($cap['nombre']); ?>
                                (<?php echo htmlspecialchars($cap['tipo']); ?>)
                                <?php if ($cap['obligatoria']): ?> - OBLIGATORIA<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Fecha de Capacitación</label>
                        <input type="date" class="form-control" name="fecha_asignacion" required
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>
                        Asignar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Función para filtrar la tabla
function filtrarTabla(estado) {
    const filas = document.querySelectorAll('.fila-asignacion');
    
    filas.forEach(fila => {
        if (estado === 'TODOS') {
            fila.style.display = '';
        } else {
            const filaEstado = fila.getAttribute('data-estado');
            if (filaEstado === estado) {
                fila.style.display = '';
            } else {
                fila.style.display = 'none';
            }
        }
    });
}

// Inicializar DataTable
$(document).ready(function() {
    $('#tablaAsignaciones').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
        },
        order: [[4, 'desc']], // Ordenar por fecha descendente
        pageLength: 10,
        columnDefs: [
            { orderable: false, targets: [7] } // Columna Acciones no ordenable
        ]
    });
    
    // Validación de calificación
    $('input[name="calificacion"]').on('change', function() {
        const valor = parseFloat($(this).val());
        if (valor < 0 || valor > 10) {
            alert('La calificación debe estar entre 0 y 10');
            $(this).val('');
            $(this).focus();
        }
    });
});

// Validación del formulario de asignación
document.querySelector('form[action*="asignar_empleado"]').addEventListener('submit', function(e) {
    const empleado = this.querySelector('select[name="empleado_id"]').value;
    const capacitacion = this.querySelector('select[name="capacitacion_id"]').value;
    const fecha = this.querySelector('input[name="fecha_asignacion"]').value;
    
    if (!empleado || !capacitacion || !fecha) {
        e.preventDefault();
        alert('Por favor, complete todos los campos requeridos');
    }
    
    // Validar fecha futura
    const hoy = new Date().toISOString().split('T')[0];
    if (fecha < hoy) {
        if (!confirm('La fecha seleccionada es anterior a hoy. ¿Desea continuar?')) {
            e.preventDefault();
        }
    }
});
</script>

<style>
.fila-pendiente {
    background-color: #fff9e6 !important;
}

.fila-aprobada {
    background-color: #e6fffa !important;
}

.fila-reprobada {
    background-color: #ffe6e6 !important;
}

.fila-pendiente:hover {
    background-color: #fff0cc !important;
}

.fila-aprobada:hover {
    background-color: #ccf5eb !important;
}

.fila-reprobada:hover {
    background-color: #ffcccc !important;
}

.input-group-sm {
    max-width: 150px;
}

.badge {
    font-size: 0.8em;
    padding: 4px 8px;
}
</style>

<?php require_once '../../includes/footer.php'; ?>