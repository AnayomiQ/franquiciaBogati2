<?php
// modules/empleado/turnos.php

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

// Mapeo de días en español
$dias_espanol = [
    'LUNES' => 'Lunes',
    'MARTES' => 'Martes',
    'MIERCOLES' => 'Miércoles',
    'JUEVES' => 'Jueves',
    'VIERNES' => 'Viernes',
    'SABADO' => 'Sábado',
    'DOMINGO' => 'Domingo'
];

$dias_semana = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'];

// Obtener conexión a la base de datos
$db = Database::getConnection();

try {
    // Obtener todos los turnos del empleado
    $stmt = $db->prepare("
        SELECT 
            t.*,
            DATE_FORMAT(t.hora_entrada, '%H:%i') as hora_entrada_formateada,
            DATE_FORMAT(t.hora_salida, '%H:%i') as hora_salida_formateada,
            TIMEDIFF(t.hora_salida, t.hora_entrada) as horas_trabajadas
        FROM turnos_empleados t
        WHERE t.id_empleado = ?
        ORDER BY 
            FIELD(t.dia_semana, 'LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'),
            t.hora_entrada
    ");
    $stmt->execute([$empleado_id]);
    $turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar turnos por día
    $turnos_por_dia = [];
    foreach ($turnos as $turno) {
        $turnos_por_dia[$turno['dia_semana']][] = $turno;
    }
    
    // Obtener turno actual
    $dia_semana_ingles = date('l'); // Ej: Monday
    $dias_traduccion = [
        'Monday' => 'LUNES',
        'Tuesday' => 'MARTES',
        'Wednesday' => 'MIERCOLES',
        'Thursday' => 'JUEVES',
        'Friday' => 'VIERNES',
        'Saturday' => 'SABADO',
        'Sunday' => 'DOMINGO'
    ];
    $dia_actual = $dias_traduccion[$dia_semana_ingles] ?? 'LUNES';
    
    $hora_actual = date('H:i:s');
    
    $stmt = $db->prepare("
        SELECT *
        FROM turnos_empleados 
        WHERE id_empleado = ? 
        AND dia_semana = ?
        AND hora_entrada <= ?
        AND hora_salida >= ?
        LIMIT 1
    ");
    $stmt->execute([$empleado_id, $dia_actual, $hora_actual, $hora_actual]);
    $turno_actual = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener estadísticas de horas
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_turnos,
            SEC_TO_TIME(SUM(TIME_TO_SEC(TIMEDIFF(hora_salida, hora_entrada)))) as horas_semanales,
            MIN(hora_entrada) as entrada_mas_temprana,
            MAX(hora_salida) as salida_mas_tardia
        FROM turnos_empleados 
        WHERE id_empleado = ?
    ");
    $stmt->execute([$empleado_id]);
    $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('Error cargando turnos: ' . $e->getMessage());
    $turnos = [];
    $turnos_por_dia = [];
    $turno_actual = null;
    $estadisticas = [];
}

// Configurar título de la página
$pageTitle = APP_NAME . ' - Mis Turnos';
$show_breadcrumb = true;
$breadcrumb_items = [
    ['text' => 'Dashboard', 'url' => BASE_URL . 'dashboard.php'],
    ['text' => 'Panel Empleado', 'url' => BASE_URL . 'modules/empleado/dashboard.php'],
    ['text' => 'Mis Turnos']
];

// Incluir header
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .calendar-day {
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        height: 120px;
        transition: all 0.3s;
    }
    
    .calendar-day:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .calendar-day.today {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
    }
    
    .calendar-day.today .badge {
        background: white;
        color: #667eea;
    }
    
    .shift-badge {
        font-size: 0.75rem;
        padding: 3px 8px;
        margin: 2px;
        display: inline-block;
    }
    
    .current-shift-card {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
        border-radius: 15px;
        padding: 25px;
    }
    
    .time-display {
        font-family: 'Courier New', monospace;
        font-weight: bold;
        font-size: 1.5rem;
    }
    
    .day-header {
        background: #f8f9fa;
        padding: 10px;
        border-radius: 8px;
        margin-bottom: 15px;
    }
</style>

<div class="container-fluid py-4">
    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-calendar-alt text-primary me-2"></i>
                Mis Turnos y Horarios
            </h1>
            <p class="text-muted mb-0">Consulta tus horarios de trabajo</p>
        </div>
    </div>

    <!-- Turno Actual -->
    <?php if ($turno_actual): ?>
    <div class="card shadow mb-4">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-clock text-success me-2"></i>
                Turno Actual
            </h5>
        </div>
        <div class="card-body">
            <div class="current-shift-card">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h3 class="mb-3">
                            <i class="fas fa-user-clock me-2"></i>
                            Estás en Turno
                        </h3>
                        <div class="time-display mb-3">
                            <?php echo date('H:i', strtotime($turno_actual['hora_entrada'])); ?> - 
                            <?php echo date('H:i', strtotime($turno_actual['hora_salida'])); ?>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-calendar-day me-2"></i>
                            <?php echo $dias_espanol[$dia_actual]; ?> <?php echo date('d/m/Y'); ?>
                        </div>
                        <div id="contadorTurno"></div>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="display-1">
                            <i class="fas fa-business-time"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Turnos Asignados</h6>
                    <h2 class="card-title"><?php echo $estadisticas['total_turnos'] ?? 0; ?></h2>
                    <small class="text-muted">En tu horario</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Horas Semanales</h6>
                    <h2 class="card-title text-success">
                        <?php 
                        if (isset($estadisticas['horas_semanales']) && $estadisticas['horas_semanales']) {
                            $horas = explode(':', $estadisticas['horas_semanales']);
                            echo $horas[0] . 'h ' . $horas[1] . 'm';
                        } else {
                            echo '0h 0m';
                        }
                        ?>
                    </h2>
                    <small class="text-muted">Total programado</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Entrada más Temprana</h6>
                    <h2 class="card-title text-info">
                        <?php echo isset($estadisticas['entrada_mas_temprana']) && $estadisticas['entrada_mas_temprana'] ? 
                            date('H:i', strtotime($estadisticas['entrada_mas_temprana'])) : '--:--'; ?>
                    </h2>
                    <small class="text-muted">Horario mínimo</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Salida más Tardía</h6>
                    <h2 class="card-title text-warning">
                        <?php echo isset($estadisticas['salida_mas_tardia']) && $estadisticas['salida_mas_tardia'] ? 
                            date('H:i', strtotime($estadisticas['salida_mas_tardia'])) : '--:--'; ?>
                    </h2>
                    <small class="text-muted">Horario máximo</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendario Semanal -->
    <div class="card shadow mb-4">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-calendar-week text-primary me-2"></i>
                Mi Horario Semanal
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($dias_semana as $dia): ?>
                <div class="col-md-3 mb-3">
                    <div class="calendar-day <?php echo $dia === $dia_actual ? 'today' : ''; ?>">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="mb-0">
                                <?php echo $dias_espanol[$dia]; ?>
                            </h6>
                            <?php if ($dia === $dia_actual): ?>
                                <span class="badge bg-white text-primary">HOY</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (isset($turnos_por_dia[$dia])): ?>
                            <?php foreach ($turnos_por_dia[$dia] as $turno): ?>
                            <div class="shift-badge bg-primary text-white mb-1">
                                <?php echo date('H:i', strtotime($turno['hora_entrada'])); ?> - 
                                <?php echo date('H:i', strtotime($turno['hora_salida'])); ?>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <small class="text-muted">Sin turnos asignados</small>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Tabla Detallada de Turnos -->
    <div class="card shadow">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-table me-2"></i>
                Detalle de Turnos
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($turnos)): ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="tablaTurnos">
                        <thead>
                            <tr>
                                <th>Día</th>
                                <th>Entrada</th>
                                <th>Salida</th>
                                <th>Duración</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($turnos as $turno): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $dias_espanol[$turno['dia_semana']]; ?></strong>
                                    <?php if ($turno['dia_semana'] === $dia_actual): ?>
                                        <span class="badge bg-success ms-2">Hoy</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-primary fw-bold">
                                    <?php echo date('H:i', strtotime($turno['hora_entrada'])); ?>
                                </td>
                                <td class="text-danger fw-bold">
                                    <?php echo date('H:i', strtotime($turno['hora_salida'])); ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($turno['horas_trabajadas']) {
                                        $duracion = explode(':', $turno['horas_trabajadas']);
                                        echo $duracion[0] . 'h ' . $duracion[1] . 'm';
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $hora_actual_comparar = date('H:i:s');
                                    $hora_entrada = $turno['hora_entrada'];
                                    $hora_salida = $turno['hora_salida'];
                                    
                                    if ($turno['dia_semana'] === $dia_actual) {
                                        if ($hora_actual_comparar >= $hora_entrada && $hora_actual_comparar <= $hora_salida) {
                                            echo '<span class="badge bg-success">En Curso</span>';
                                        } elseif ($hora_actual_comparar < $hora_entrada) {
                                            echo '<span class="badge bg-info">Próximo</span>';
                                        } else {
                                            echo '<span class="badge bg-secondary">Finalizado</span>';
                                        }
                                    } else {
                                        echo '<span class="badge bg-light text-dark">Programado</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">No hay turnos asignados</h5>
                    <p class="text-muted">Contacta con tu supervisor para definir tus horarios</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Contador para turno actual
function actualizarContadorTurno() {
    if (!<?php echo $turno_actual ? 'true' : 'false'; ?>) return;
    
    const horaEntrada = new Date('<?php echo date('Y-m-d'); ?>T<?php echo $turno_actual['hora_entrada']; ?>');
    const horaSalida = new Date('<?php echo date('Y-m-d'); ?>T<?php echo $turno_actual['hora_salida']; ?>');
    const ahora = new Date();
    
    if (ahora >= horaEntrada && ahora <= horaSalida) {
        const transcurrido = Math.floor((ahora - horaEntrada) / 1000); // en segundos
        const horas = Math.floor(transcurrido / 3600);
        const minutos = Math.floor((transcurrido % 3600) / 60);
        const segundos = transcurrido % 60;
        
        document.getElementById('contadorTurno').innerHTML = `
            <i class="fas fa-hourglass-half me-2"></i>
            Tiempo transcurrido: ${horas}h ${minutos}m ${segundos}s
        `;
    }
}

// Actualizar contador cada segundo
if (<?php echo $turno_actual ? 'true' : 'false'; ?>) {
    setInterval(actualizarContadorTurno, 1000);
    actualizarContadorTurno();
}

// Inicializar DataTable
$(document).ready(function() {
    $('#tablaTurnos').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
        },
        order: [[0, 'asc']],
        pageLength: 10
    });
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>