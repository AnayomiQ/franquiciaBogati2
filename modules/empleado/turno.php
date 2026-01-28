<?php
// modules/empleado/turno.php

require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../db_connection.php';

// Mostrar errores mientras depuras
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Iniciar sesión
startSession();

// Verificar autenticación y rol
requireAuth();
requireAnyRole(['empleado']);

// Obtener información del empleado
$userInfo = getCurrentUserInfo();
$empleado_id = getCurrentEmployeeId();
$local_codigo = getCurrentLocalCode();

if (!$empleado_id || !$local_codigo) {
    setFlashMessage('error', 'No se pudo cargar la información del empleado.');
    redirect(BASE_URL . 'login.php');
}

// Obtener conexión a la base de datos
$db = Database::getConnection();

// Mapeo de días en español
$dias_semana = [
    'LUNES' => 'Lunes',
    'MARTES' => 'Martes',
    'MIERCOLES' => 'Miércoles',
    'JUEVES' => 'Jueves',
    'VIERNES' => 'Viernes',
    'SABADO' => 'Sábado',
    'DOMINGO' => 'Domingo'
];

// Procesar acciones
$action = $_GET['action'] ?? '';
$id_turno = $_GET['id'] ?? 0;

try {
    // Obtener todos los turnos del empleado
    $stmt = $db->prepare("
        SELECT * FROM turnos_empleados 
        WHERE id_empleado = ? 
        ORDER BY 
            FIELD(dia_semana, 'LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'),
            hora_entrada ASC
    ");
    $stmt->execute([$empleado_id]);
    $turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener día actual
    $dia_actual_ingles = date('l');
    $dias_ingles_espanol = [
        'Monday' => 'LUNES',
        'Tuesday' => 'MARTES',
        'Wednesday' => 'MIERCOLES',
        'Thursday' => 'JUEVES',
        'Friday' => 'VIERNES',
        'Saturday' => 'SABADO',
        'Sunday' => 'DOMINGO'
    ];
    $dia_actual = $dias_ingles_espanol[$dia_actual_ingles] ?? 'LUNES';
    
    // Obtener turno de hoy
    $stmt_hoy = $db->prepare("
        SELECT * FROM turnos_empleados 
        WHERE id_empleado = ? AND dia_semana = ?
        ORDER BY hora_entrada ASC
        LIMIT 1
    ");
    $stmt_hoy->execute([$empleado_id, $dia_actual]);
    $turno_hoy = $stmt_hoy->fetch(PDO::FETCH_ASSOC);

    // Calcular horas semanales
    $horas_semanales = 0;
    foreach ($turnos as $turno) {
        $entrada = strtotime($turno['hora_entrada']);
        $salida = strtotime($turno['hora_salida']);
        $horas = ($salida - $entrada) / 3600;
        $horas_semanales += $horas;
    }
    
} catch (PDOException $e) {
    error_log('Error cargando turnos: ' . $e->getMessage());
    
    // Inicializar variables vacías en caso de error
    $turnos = [];
    $turno_hoy = [];
    $historial_cambios = [];
    $horas_semanales = 0;
    
    setFlashMessage('error', 'Error al cargar los turnos: ' . $e->getMessage());
}

// Configurar título de la página
$pageTitle = APP_NAME . ' - Mis Turnos';

// CSS específico
$pageStyles = ['turno.css'];

// Incluir header
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- =========================================================================== -->
<!-- ESTILOS PARA TURNOS -->
<!-- =========================================================================== -->
<style>
    :root {
        --turno-primary: linear-gradient(135deg, #FFD166 0%, #FFB347 100%);
        --turno-secondary: linear-gradient(135deg, #36D1DC 0%, #5B86E5 100%);
        --turno-success: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        --turno-warning: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);
        --turno-info: linear-gradient(135deg, #00c6ff 0%, #0072ff 100%);
        --turno-off: #f8f9fa;
    }

    .turnos-container {
        padding: 25px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        min-height: calc(100vh - 70px);
    }

    /* ENCABEZADO */
    .turno-header {
        background: white;
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 10px 40px rgba(255, 209, 102, 0.15);
        border: 1px solid rgba(255, 209, 102, 0.3);
        position: relative;
        overflow: hidden;
    }

    .turno-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path fill="%23FFD166" opacity="0.05" d="M0,0 L100,0 L100,100 Z"></path></svg>');
        background-size: cover;
    }

    .turno-title {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 15px;
        background: var(--turno-primary);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .turno-subtitle {
        color: #6c757d;
        font-size: 1.1rem;
        margin-bottom: 25px;
    }

    /* TURNO ACTUAL */
    .turno-hoy-card {
        background: linear-gradient(135deg, #36D1DC 0%, #5B86E5 100%);
        border-radius: 15px;
        padding: 25px;
        color: white;
        margin-bottom: 30px;
        box-shadow: 0 15px 50px rgba(54, 209, 220, 0.2);
        position: relative;
        overflow: hidden;
    }

    .turno-hoy-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path fill="white" opacity="0.1" d="M0,0 L100,0 L100,100 Z"></path></svg>');
        background-size: cover;
    }

    .turno-hoy-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .turno-hora {
        font-size: 3.5rem;
        font-weight: 900;
        margin: 15px 0;
        line-height: 1;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
    }

    .turno-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }

    .turno-detail {
        background: rgba(255, 255, 255, 0.2);
        padding: 15px;
        border-radius: 10px;
        backdrop-filter: blur(10px);
    }

    .turno-detail-label {
        font-size: 0.9rem;
        opacity: 0.9;
        margin-bottom: 5px;
    }

    .turno-detail-value {
        font-size: 1.3rem;
        font-weight: 700;
    }

    /* CALENDARIO SEMANAL */
    .semana-container {
        background: white;
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    }

    .semana-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f1f3f4;
    }

    .semana-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #2d3748;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .horas-totales {
        background: var(--turno-primary);
        padding: 8px 20px;
        border-radius: 50px;
        color: white;
        font-weight: 700;
        font-size: 1.1rem;
    }

    .dias-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }

    .dia-card {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 20px;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        position: relative;
    }

    .dia-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .dia-card.hoy {
        background: linear-gradient(135deg, rgba(255, 209, 102, 0.1) 0%, rgba(255, 179, 71, 0.05) 100%);
        border: 2px solid #FFD166;
    }

    .dia-header {
        text-align: center;
        margin-bottom: 15px;
    }

    .dia-nombre {
        font-size: 1.1rem;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 5px;
    }

    .dia-fecha {
        font-size: 0.9rem;
        color: #718096;
    }

    .turno-horas {
        text-align: center;
        margin: 15px 0;
    }

    .hora-entrada, .hora-salida {
        font-size: 1.3rem;
        font-weight: 700;
        margin: 5px 0;
    }

    .hora-entrada {
        color: #11998e;
    }

    .hora-salida {
        color: #f85032;
    }

    .duracion-turno {
        font-size: 0.9rem;
        color: #718096;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #e2e8f0;
    }

    /* ACCIONES */
    .acciones-turno {
        background: white;
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    }

    .acciones-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .accion-card {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 25px;
        text-align: center;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
    }

    .accion-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        text-decoration: none;
        color: inherit;
        border-color: #FFD166;
    }

    .accion-icon {
        font-size: 2.5rem;
        margin-bottom: 15px;
        background: var(--turno-primary);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .accion-title {
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 10px;
        color: #2d3748;
    }

    .accion-desc {
        font-size: 0.9rem;
        color: #718096;
        line-height: 1.4;
    }

    /* MODAL SOLICITUD */
    .modal-custom .modal-content {
        border-radius: 15px;
        border: none;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    }

    .modal-custom .modal-header {
        background: linear-gradient(135deg, #FFD166 0%, #FFB347 100%);
        color: white;
        border-radius: 15px 15px 0 0;
        padding: 20px 30px;
        border: none;
    }

    .modal-custom .modal-title {
        font-weight: 700;
        font-size: 1.5rem;
    }

    .modal-custom .modal-body {
        padding: 30px;
    }

    .form-group-turno {
        margin-bottom: 25px;
    }

    .form-group-turno label {
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 8px;
        display: block;
    }

    .form-control-turno {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .form-control-turno:focus {
        outline: none;
        border-color: #FFD166;
        box-shadow: 0 0 0 3px rgba(255, 209, 102, 0.2);
    }

    .time-input-group {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    /* RESPONSIVE */
    @media (max-width: 768px) {
        .dias-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .turno-details {
            grid-template-columns: 1fr;
        }
        
        .turno-hora {
            font-size: 2.5rem;
        }
        
        .acciones-grid {
            grid-template-columns: 1fr;
        }
        
        .time-input-group {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 576px) {
        .dias-grid {
            grid-template-columns: 1fr;
        }
        
        .semana-header {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }
    }
</style>

<!-- =========================================================================== -->
<!-- CONTENIDO PRINCIPAL -->
<!-- =========================================================================== -->
<div class="turnos-container">

    <!-- ENCABEZADO -->
    <div class="turno-header">
        <h1 class="turno-title">
            <i class="fas fa-clock me-2"></i>Mis Turnos
        </h1>
        <p class="turno-subtitle">
            Gestiona y visualiza tu horario de trabajo en <?php echo htmlspecialchars($userInfo['nombre_local'] ?? 'Bogati'); ?>
        </p>
        
        <div class="d-flex gap-3">
            <span class="badge bg-warning">
                <i class="fas fa-user-tag me-1"></i> 
                <?php echo htmlspecialchars($userInfo['cargo'] ?? 'Empleado'); ?>
            </span>
            <span class="badge bg-info">
                <i class="fas fa-store me-1"></i> 
                <?php echo htmlspecialchars($local_codigo); ?>
            </span>
            <span class="badge bg-success">
                <i class="fas fa-id-badge me-1"></i> 
                ID: <?php echo htmlspecialchars($empleado_id); ?>
            </span>
        </div>
    </div>

    <!-- TURNO DE HOY -->
    <?php if ($turno_hoy): ?>
        <div class="turno-hoy-card">
            <div class="turno-hoy-title">
                <i class="fas fa-calendar-day"></i> 
                Tu Turno Hoy - <?php echo $dias_semana[$dia_actual] ?? $dia_actual; ?>
            </div>
            
            <div class="turno-hora">
                <?php echo date('H:i', strtotime($turno_hoy['hora_entrada'])); ?> 
                - 
                <?php echo date('H:i', strtotime($turno_hoy['hora_salida'])); ?>
            </div>
            
            <div class="turno-details">
                <div class="turno-detail">
                    <div class="turno-detail-label">Estado</div>
                    <div class="turno-detail-value">
                        <?php 
                        $hora_actual = date('H:i');
                        $entrada = date('H:i', strtotime($turno_hoy['hora_entrada']));
                        $salida = date('H:i', strtotime($turno_hoy['hora_salida']));
                        
                        if ($hora_actual < $entrada) {
                            echo '<span class="badge bg-warning">POR INICIAR</span>';
                        } elseif ($hora_actual >= $entrada && $hora_actual <= $salida) {
                            echo '<span class="badge bg-success">EN TURNO</span>';
                        } else {
                            echo '<span class="badge bg-secondary">FINALIZADO</span>';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="turno-detail">
                    <div class="turno-detail-label">Horas Restantes</div>
                    <div class="turno-detail-value">
                        <?php
                        if ($hora_actual >= $entrada && $hora_actual <= $salida) {
                            $restante = (strtotime($salida) - strtotime($hora_actual)) / 3600;
                            echo number_format($restante, 1) . ' hrs';
                        } elseif ($hora_actual < $entrada) {
                            $para_iniciar = (strtotime($entrada) - strtotime($hora_actual)) / 3600;
                            echo 'Inicia en ' . number_format($para_iniciar, 1) . ' hrs';
                        } else {
                            echo '0 hrs';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="turno-detail">
                    <div class="turno-detail-label">Duración</div>
                    <div class="turno-detail-value">
                        <?php
                        $duracion = (strtotime($salida) - strtotime($entrada)) / 3600;
                        echo number_format($duracion, 1) . ' horas';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            No tienes turno asignado para hoy. Contacta con tu supervisor.
        </div>
    <?php endif; ?>

    <!-- CALENDARIO SEMANAL -->
    <div class="semana-container">
        <div class="semana-header">
            <h2 class="semana-title">
                <i class="fas fa-calendar-week me-2"></i>Horario Semanal
            </h2>
            <div class="horas-totales">
                <i class="fas fa-clock me-1"></i>
                <?php echo number_format($horas_semanales, 1); ?> hrs/semana
            </div>
        </div>
        
        <div class="dias-grid">
            <?php 
            $dias_semana_completo = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'];
            
            foreach ($dias_semana_completo as $dia): 
                $turno_dia = array_filter($turnos, function($t) use ($dia) {
                    return $t['dia_semana'] === $dia;
                });
                $turno_dia = reset($turno_dia); // Obtener primer elemento
                $es_hoy = ($dia === $dia_actual);
            ?>
                <div class="dia-card <?php echo $es_hoy ? 'hoy' : ''; ?>">
                    <div class="dia-header">
                        <div class="dia-nombre"><?php echo $dias_semana[$dia] ?? $dia; ?></div>
                        <div class="dia-fecha">
                            <?php if ($es_hoy): ?>
                                <i class="fas fa-star text-warning"></i> Hoy
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="turno-horas">
                        <?php if ($turno_dia): ?>
                            <div class="hora-entrada">
                                <i class="fas fa-sign-in-alt me-1"></i>
                                <?php echo date('H:i', strtotime($turno_dia['hora_entrada'])); ?>
                            </div>
                            <div class="hora-salida">
                                <i class="fas fa-sign-out-alt me-1"></i>
                                <?php echo date('H:i', strtotime($turno_dia['hora_salida'])); ?>
                            </div>
                            
                            <div class="duracion-turno">
                                <?php
                                $duracion = (strtotime($turno_dia['hora_salida']) - strtotime($turno_dia['hora_entrada'])) / 3600;
                                echo number_format($duracion, 1) . ' horas';
                                ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-bed fa-2x text-muted"></i>
                                <p class="text-muted mt-2 mb-0">Descanso</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- =========================================================================== -->
<!-- SCRIPTS -->
<!-- =========================================================================== -->
<script>
    /**
     * Imprimir horario en formato PDF
     */
    function imprimirHorario() {
        // Crear ventana de impresión
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Horario - <?php echo htmlspecialchars($userInfo['nombres'] ?? 'Empleado'); ?></title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    .header { text-align: center; margin-bottom: 30px; }
                    .header h1 { color: #FFB347; margin: 0; }
                    .info { margin: 20px 0; }
                    .table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                    .table th, .table td { border: 1px solid #ddd; padding: 12px; text-align: center; }
                    .table th { background-color: #FFD166; color: #333; }
                    .today { background-color: #fff3cd; }
                    .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>Horario Semanal - Bogati</h1>
                    <p>Empleado: <?php echo htmlspecialchars(getCurrentUserName()); ?></p>
                    <p>Local: <?php echo htmlspecialchars($userInfo['nombre_local'] ?? 'Bogati'); ?></p>
                    <p>Generado: ${new Date().toLocaleDateString('es-ES', { 
                        weekday: 'long', 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    })}</p>
                </div>
                
                <div class="info">
                    <p><strong>Total horas semanales:</strong> <?php echo number_format($horas_semanales, 1); ?> horas</p>
                </div>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Día</th>
                            <th>Turno</th>
                            <th>Duración</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($turnos as $turno): ?>
                        <tr class="<?php echo ($turno['dia_semana'] === $dia_actual) ? 'today' : ''; ?>">
                            <td><?php echo $dias_semana[$turno['dia_semana']] ?? $turno['dia_semana']; ?></td>
                            <td><?php echo date('H:i', strtotime($turno['hora_entrada'])); ?> - <?php echo date('H:i', strtotime($turno['hora_salida'])); ?></td>
                            <td>
                                <?php 
                                $duracion = (strtotime($turno['hora_salida']) - strtotime($turno['hora_entrada'])) / 3600;
                                echo number_format($duracion, 1) . ' horas';
                                ?>
                            </td>
                            <td>
                                <?php if ($turno['dia_semana'] === $dia_actual): ?>
                                    <strong>HOY</strong>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="footer">
                    <p>Sistema de Gestión Bogati - <?php echo date('Y'); ?></p>
                    <p>Este documento es de uso interno</p>
                </div>
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
        
        /**
         * Actualizar reloj en tiempo real
         */
        function updateClock() {
            const now = new Date();
            const horaActual = now.toLocaleTimeString('es-ES', { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit' 
            });
            
            // Actualizar estado del turno actual si existe
            const estadoElement = document.querySelector('.turno-detail .badge');
            if (estadoElement && estadoElement.textContent.includes('EN TURNO')) {
                const horasRestantes = document.querySelector('.turno-detail-value:last-child');
                if (horasRestantes && <?php echo $turno_hoy ? 'true' : 'false'; ?>) {
                    const salida = new Date('2000-01-01T<?php echo $turno_hoy['hora_salida'] ?? "00:00"; ?>');
                    const ahora = new Date('2000-01-01T' + now.toTimeString().slice(0,5));
                    
                    if (ahora < salida) {
                        const restante = (salida - ahora) / (1000 * 60 * 60);
                        horasRestantes.textContent = number_format(restante, 1) + ' hrs';
                    } else {
                        horasRestantes.textContent = '0 hrs';
                        estadoElement.className = 'badge bg-secondary';
                        estadoElement.textContent = 'FINALIZADO';
                    }
                }
            }
            
            setTimeout(updateClock, 60000); // Actualizar cada minuto
        }
        
        // Iniciar reloj
        updateClock();


    /**
     * Formatear número con decimales
     */
    function number_format(number, decimals) {
        return parseFloat(number).toFixed(decimals);
    }
</script>

<?php
// ============================================================================
// INCLUIR FOOTER
// ============================================================================
require_once __DIR__ . '/../../includes/footer.php';
?>