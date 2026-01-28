<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../db_connection.php';

startSession();
requireAuth();
requireAnyRole(['admin']);

$db = Database::getConnection();

// Procesar acciones
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

// 🆕 NUEVO: Limpiar filtros
if ($action === 'clear_filters') {
    unset($_SESSION['filtros_royalty']);
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
        echo json_encode(['success' => true]);
        exit();
    }
    header('Location: pagos_royalty.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'confirmar_pago') {
        //  MEJORADO: Usando trigger automático
        $id_pago = $_POST['id_pago'];

        try {
            // Actualizar estado del pago (el TRIGGER registrará automáticamente)
            $stmt = $db->prepare("
                UPDATE pagos_royalty 
                SET estado = 'CANCELADO',
                    fecha_pago = CURDATE()
                WHERE id_pago = ? AND estado = 'PENDIENTE'
            ");
            $stmt->execute([$id_pago]);

            if ($stmt->rowCount() > 0) {
                // El trigger tg_auditoria_confirmacion_pago ya registró el log
                setFlashMessage('success', ' Pago confirmado exitosamente. El trigger registró la auditoría automáticamente.');
            } else {
                setFlashMessage('warning', ' El pago ya fue confirmado o no existe');
            }

            header('Location: pagos_royalty.php');
            exit();
        } catch (PDOException $e) {
            setFlashMessage('error', ' Error al confirmar pago: ' . $e->getMessage());
            header('Location: pagos_royalty.php');
            exit();
        }
    } elseif ($action === 'filtrar') {
        // Guardar filtros en sesión
        $_SESSION['filtros_royalty'] = [
            'mes' => $_POST['mes'] ?? null,
            'anio' => $_POST['anio'] ?? null,
            'estado' => $_POST['estado'] ?? 'all',
            'local' => $_POST['local'] ?? 'all'
        ];

        setFlashMessage('info', 'Filtros aplicados correctamente');
        header('Location: pagos_royalty.php');
        exit();
    } elseif ($action === 'generar_pagos') {
        //  USANDO PROCEDIMIENTO ALMACENADO
        $mes = $_POST['mes'] ?? date('n');
        $anio = $_POST['anio'] ?? date('Y');

        try {
            // USAR EL PROCEDIMIENTO ALMACENADO pa_generar_pagos_royalty
            $stmt = $db->prepare("CALL pa_generar_pagos_royalty(?, ?)");
            $stmt->execute([$mes, $anio]);

            // Obtener cuántos pagos se generaron
            $stmt = $db->prepare("
                SELECT COUNT(*) as total_generados 
                FROM pagos_royalty 
                WHERE mes = ? AND anio = ? AND estado = 'PENDIENTE'
            ");
            $stmt->execute([$mes, $anio]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            $pagosGenerados = $resultado['total_generados'] ?? 0;

            // Log de actividad
            logAction(
                'GENERAR_PAGO_ROYALTY',
                'pagos_royalty',
                "Pagos generados vía procedimiento almacenado - Mes: $mes/$anio - " .
                    "Total generados: $pagosGenerados"
            );

            setFlashMessage('success', " Se generaron $pagosGenerados pagos para $mes/$anio usando el procedimiento almacenado");
        } catch (PDOException $e) {
            setFlashMessage('error', ' Error al generar pagos: ' . $e->getMessage());
        }

        header('Location: pagos_royalty.php');
        exit();
    }
}

// 🆕 NUEVO: Verificar contratos vencidos automáticamente
if ($action === 'verificar_vencidos') {
    try {
        // Llamar al procedimiento almacenado (si existe)
        try {
            $stmt = $db->prepare("CALL pa_verificar_contratos_vencidos()");
            $stmt->execute();
            setFlashMessage('success', ' Verificación de contratos completada');
        } catch (Exception $e) {
            // Si el procedimiento no existe, hacer verificación manual
            $stmt = $db->prepare("
                UPDATE contratos_franquicia 
                SET estado = 'VENCIDO' 
                WHERE fecha_fin < CURDATE() AND estado = 'ACTIVO'
            ");
            $stmt->execute();
            $afectados = $stmt->rowCount();
            setFlashMessage('info', " Se marcaron $afectados contratos como vencidos (procedimiento no disponible)");
        }
        
        header('Location: pagos_royalty.php');
        exit();
    } catch (PDOException $e) {
        setFlashMessage('error', ' Error: ' . $e->getMessage());
        header('Location: pagos_royalty.php');
        exit();
    }
}

// 🆕 NUEVO: Generar reporte de pagos
if ($action === 'reporte_pendientes') {
    try {
        // Intentar usar procedimiento con CURSOR si existe
        try {
            $stmt = $db->prepare("CALL pa_generar_reporte_pendientes()");
            $stmt->execute();
            $reporte = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Si no existe, usar consulta directa
            $stmt = $db->prepare("
                SELECT 
                    cf.numero_contrato as Contrato,
                    CONCAT(f.nombres, ' ', f.apellidos) as Franquiciado,
                    l.nombre_local as Local,
                    pr.mes as Mes,
                    pr.anio as Año,
                    (pr.monto_royalty + pr.monto_publicidad) as 'Monto Pendiente'
                FROM pagos_royalty pr
                INNER JOIN contratos_franquicia cf ON pr.id_contrato = cf.id_contrato
                INNER JOIN locales l ON cf.codigo_local = l.codigo_local
                INNER JOIN franquiciados f ON cf.cedula_franquiciado = f.cedula
                WHERE pr.estado = 'PENDIENTE'
                ORDER BY pr.anio DESC, pr.mes DESC
            ");
            $stmt->execute();
            $reporte = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Exportar a CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=reporte_pendientes_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // Encabezados
        fputcsv($output, ['Contrato', 'Franquiciado', 'Local', 'Mes', 'Año', 'Monto Pendiente']);
        
        // Datos
        foreach ($reporte as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit();
    } catch (PDOException $e) {
        setFlashMessage('error', ' Error al generar reporte: ' . $e->getMessage());
        header('Location: pagos_royalty.php');
        exit();
    }
}

// Obtener estadísticas usando la VISTA vw_resumen_pagos_mensual
$stats = [];
$currentMonth = date('n');
$currentYear = date('Y');

try {
    //  USANDO FUNCIÓN SQL para cálculos
    $stmt = $db->prepare("
        SELECT 
            SUM(CASE WHEN estado = 'PENDIENTE' THEN fn_total_a_pagar(monto_royalty, monto_publicidad) ELSE 0 END) as total_monto_pendiente,
            COUNT(CASE WHEN estado = 'PENDIENTE' THEN 1 END) as total_registros_pendientes
        FROM pagos_royalty
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['pendientes'] = [
        'total' => $result['total_registros_pendientes'] ?? 0,
        'total_monto' => $result['total_monto_pendiente'] ?? 0
    ];

    // Usar la vista vw_resumen_pagos_mensual para estadísticas del mes actual
    $stmt = $db->prepare("
        SELECT 
            total_cancelado,
            total_recaudado,
            pagos_cancelados
        FROM vw_resumen_pagos_mensual
        WHERE mes = ? AND anio = ?
    ");
    $stmt->execute([$currentMonth, $currentYear]);
    $resumenMes = $stmt->fetch(PDO::FETCH_ASSOC);

    $stats['cancelados_mes'] = [
        'total' => $resumenMes['pagos_cancelados'] ?? 0,
        'total_monto' => $resumenMes['total_cancelado'] ?? 0
    ];

    // Total anual usando la vista
    $stmt = $db->prepare("
        SELECT SUM(total_cancelado) as total_anual
        FROM vw_resumen_pagos_mensual
        WHERE anio = ?
    ");
    $stmt->execute([$currentYear]);
    $stats['total_anual'] = $stmt->fetch(PDO::FETCH_COLUMN) ?? 0;
} catch (PDOException $e) {
    error_log('Error al cargar estadísticas: ' . $e->getMessage());
    $stats = [
        'pendientes' => ['total' => 0, 'total_monto' => 0],
        'cancelados_mes' => ['total' => 0, 'total_monto' => 0],
        'total_anual' => 0
    ];
}

// Obtener locales para filtro
$locales = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT 
            codigo_local, 
            nombre_local, 
            ciudad, 
            provincia
        FROM vw_pagos_royalty_detallados
        ORDER BY ciudad, nombre_local
    ");
    $stmt->execute();
    $locales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar locales: ' . $e->getMessage());
}

//  MEJORADO: Obtener pagos con FUNCIONES SQL
$filtros = $_SESSION['filtros_royalty'] ?? [
    'mes' => date('n'),
    'anio' => date('Y'),
    'estado' => 'all',
    'local' => 'all'
];

$pagos = [];
try {
    $query = "
        SELECT 
            *,
            fn_mes_espanol(mes) as mes_nombre_espanol,
            fn_total_a_pagar(monto_royalty, monto_publicidad) as total_calculado
        FROM vw_pagos_royalty_detallados
        WHERE 1=1
    ";

    $params = [];

    if (!empty($filtros['mes'])) {
        $query .= " AND mes = ?";
        $params[] = $filtros['mes'];
    }

    if (!empty($filtros['anio'])) {
        $query .= " AND anio = ?";
        $params[] = $filtros['anio'];
    }

    if ($filtros['estado'] !== 'all') {
        $query .= " AND estado = ?";
        $params[] = $filtros['estado'];
    }

    if ($filtros['local'] !== 'all') {
        $query .= " AND codigo_local = ?";
        $params[] = $filtros['local'];
    }

    $query .= " ORDER BY anio DESC, mes DESC, nombre_local";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar pagos: ' . $e->getMessage());
    setFlashMessage('error', 'Error al cargar pagos de royalties: ' . $e->getMessage());
}

$pageTitle = APP_NAME . ' - Cobro de Royalties';
$pageStyles = ['admin.css'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Mensajes Flash -->
    <?php displayFlashMessage(); ?>

    <!-- Header -->
    <div class="header-section">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <div class="bg-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                            <i class="fas fa-money-check-alt text-primary" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                    <div>
                        <h1 class="main-title mb-1">Cobro de Royalties</h1>
                        <p class="mb-0 opacity-75">Gestión de pagos de royalties y canon de publicidad</p>
                    </div>
                </div>
                <div class="btn-group">
                    <!-- Botón existente -->
                    <button class="btn btn-primary-custom me-2" data-bs-toggle="modal" data-bs-target="#modalGenerarPagos">
                        <i class="fas fa-plus me-2"></i> Generar Pagos
                    </button>
                    
                    <!-- 🆕 NUEVO: Botón para verificar contratos vencidos -->
                    <a href="?action=verificar_vencidos" class="btn btn-outline-light me-2" 
                       onclick="return confirm('¿Verificar contratos vencidos y generar notificaciones?')">
                        <i class="fas fa-calendar-check me-2"></i> Verificar Vencidos
                    </a>
                    
                    <!-- 🆕 NUEVO: Botón para descargar reporte -->
                    <a href="?action=reporte_pendientes" class="btn btn-outline-light">
                        <i class="fas fa-file-excel me-2"></i> Reporte Pendientes
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-icon icon-pendiente">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div class="stats-number"><?php echo $stats['pendientes']['total'] ?? 0; ?></div>
                        <div class="stats-label">Pagos Pendientes</div>
                        <div class="stats-amount">$<?php echo number_format($stats['pendientes']['total_monto'] ?? 0, 2); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-icon icon-cancelado">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <div class="stats-number"><?php echo $stats['cancelados_mes']['total'] ?? 0; ?></div>
                        <div class="stats-label">Pagados Este Mes</div>
                        <div class="stats-amount">$<?php echo number_format($stats['cancelados_mes']['total_monto'] ?? 0, 2); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-icon icon-anual">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>
                        <div class="stats-number">$<?php echo number_format($stats['total_anual'], 2); ?></div>
                        <div class="stats-label">Recaudación Anual</div>
                        <div class="stats-subtext"><?php echo date('Y'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filter-card mb-4">
            <h5 class="mb-3" style="color: var(--secondary-color);">
                <i class="fas fa-filter me-2"></i>Filtros de Búsqueda
            </h5>
            <form method="POST" action="?action=filtrar">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="filter-label">Mes</label>
                        <select class="form-select filter-select" name="mes">
                            <option value="">Todos los meses</option>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo ($filtros['mes'] == $i) ? 'selected' : ''; ?>>
                                    <?php echo DateTime::createFromFormat('!m', $i)->format('F'); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="filter-label">Año</label>
                        <select class="form-select filter-select" name="anio">
                            <option value="">Todos los años</option>
                            <?php for ($year = date('Y'); $year >= 2020; $year--): ?>
                                <option value="<?php echo $year; ?>" <?php echo ($filtros['anio'] == $year) ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="filter-label">Estado</label>
                        <select class="form-select filter-select" name="estado">
                            <option value="all" <?php echo ($filtros['estado'] == 'all') ? 'selected' : ''; ?>>Todos los estados</option>
                            <option value="PENDIENTE" <?php echo ($filtros['estado'] == 'PENDIENTE') ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="CANCELADO" <?php echo ($filtros['estado'] == 'CANCELADO') ? 'selected' : ''; ?>>Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="filter-label">Local</label>
                        <select class="form-select filter-select" name="local">
                            <option value="all" <?php echo ($filtros['local'] == 'all') ? 'selected' : ''; ?>>Todos los locales</option>
                            <?php foreach ($locales as $local): ?>
                                <option value="<?php echo $local['codigo_local']; ?>"
                                    <?php echo ($filtros['local'] == $local['codigo_local']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($local['nombre_local'] . ' - ' . $local['ciudad']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <button type="submit" class="btn btn-primary-custom btn-sm">
                                <i class="fas fa-search me-1"></i> Aplicar Filtros
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="limpiarFiltros">
                                <i class="fas fa-times me-1"></i> Limpiar Filtros
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabla de Pagos -->
        <div class="card border-0 shadow">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4"># Pago</th>
                                <th>Contrato / Local</th>
                                <th>Franquiciado</th>
                                <th class="text-center">Período</th>
                                <th class="text-end">Ventas Mes</th>
                                <th class="text-end">Royalty (3%)</th>
                                <th class="text-end">Publicidad (1%)</th>
                                <th class="text-end">Total a Pagar</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Fecha Pago</th>
                                <th class="text-center pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pagos)): ?>
                                <tr>
                                    <td colspan="11" class="text-center py-5">
                                        <i class="fas fa-search fa-3x mb-3 text-muted"></i>
                                        <h5>No se encontraron pagos</h5>
                                        <p class="text-muted">Ajusta los filtros o genera nuevos pagos</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pagos as $pago): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <strong>#<?php echo str_pad($pago['id_pago'], 4, '0', STR_PAD_LEFT); ?></strong>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <small class="text-muted">Contrato:</small>
                                                <strong><?php echo htmlspecialchars($pago['numero_contrato']); ?></strong>
                                                <small class="text-muted">Local:</small>
                                                <span><?php echo htmlspecialchars($pago['nombre_local'] . ' - ' . $pago['ciudad']); ?></span>
                                                <small class="text-muted"><?php echo htmlspecialchars($pago['codigo_local']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <strong><?php echo htmlspecialchars($pago['franquiciado_nombres'] ?? 'N/A'); ?></strong>
                                                <small class="text-muted"><?php echo htmlspecialchars($pago['cedula_franquiciado'] ?? 'Sin cédula'); ?></small>
                                                <small class="text-muted"><?php echo htmlspecialchars($pago['email_franquiciado'] ?? 'Sin email'); ?></small>
                                                <small class="text-muted"><?php echo htmlspecialchars($pago['telefono_franquiciado'] ?? 'Sin teléfono'); ?></small>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border">
                                                <!--  USANDO LA FUNCIÓN SQL -->
                                                <?php echo htmlspecialchars($pago['mes_nombre_espanol'] ?? '') . ' ' . $pago['anio']; ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <strong class="text-primary">
                                                $<?php echo number_format($pago['ventas_mes'], 2); ?>
                                            </strong>
                                        </td>
                                        <td class="text-end">
                                            <span class="text-info">
                                                $<?php echo number_format($pago['monto_royalty'], 2); ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <span class="text-warning">
                                                $<?php echo number_format($pago['monto_publicidad'], 2); ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <!--  USANDO EL TOTAL CALCULADO POR LA FUNCIÓN SQL -->
                                            <strong class="text-success">
                                                $<?php echo number_format($pago['total_calculado'], 2); ?>
                                            </strong>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($pago['estado'] === 'PENDIENTE'): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-clock me-1"></i> PENDIENTE
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check-circle me-1"></i> CANCELADO
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($pago['fecha_pago']): ?>
                                                <?php echo date('d/m/Y', strtotime($pago['fecha_pago'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center pe-4">
                                            <?php if ($pago['estado'] === 'PENDIENTE'): ?>
                                                <button class="btn btn-success btn-sm"
                                                    onclick="confirmarPago(<?php echo $pago['id_pago']; ?>, '<?php echo htmlspecialchars($pago['numero_contrato']); ?>')">
                                                    <i class="fas fa-check me-1"></i> Confirmar
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">
                                                    <i class="fas fa-check-double"></i> Pagado
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($pagos)): ?>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Totales:</strong></td>
                                    <td class="text-end">
                                        <strong>$<?php echo number_format(array_sum(array_column($pagos, 'ventas_mes')), 2); ?></strong>
                                    </td>
                                    <td class="text-end">
                                        <strong>$<?php echo number_format(array_sum(array_column($pagos, 'monto_royalty')), 2); ?></strong>
                                    </td>
                                    <td class="text-end">
                                        <strong>$<?php echo number_format(array_sum(array_column($pagos, 'monto_publicidad')), 2); ?></strong>
                                    </td>
                                    <td class="text-end">
                                        <strong class="text-success">
                                            $<?php echo number_format(array_sum(array_column($pagos, 'total_calculado')), 2); ?>
                                        </strong>
                                    </td>
                                    <td colspan="3"></td>
                                </tr>
                            </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- Formulario oculto para confirmar pago -->
        <form id="formConfirmarPago" method="POST" style="display: none;">
            <input type="hidden" name="action" value="confirmar_pago">
            <input type="hidden" name="id_pago" id="idPagoConfirmar">
        </form>
    </div>
</div>

<!-- Modal para generar pagos -->
<div class="modal fade" id="modalGenerarPagos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modal-custom">
            <form method="POST" action="?action=generar_pagos">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title">
                        <i class="fas fa-calculator me-2"></i>Generar Pagos Mensuales
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Se generarán pagos para todos los contratos activos usando el <strong>procedimiento almacenado</strong>.
                        Los pagos ya existentes no se duplicarán.
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="mes_generar" class="form-label fw-semibold">Mes *</label>
                            <select class="form-select form-control-custom" id="mes_generar" name="mes" required>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($i == date('n')) ? 'selected' : ''; ?>>
                                        <?php echo DateTime::createFromFormat('!m', $i)->format('F'); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="anio_generar" class="form-label fw-semibold">Año *</label>
                            <select class="form-select form-control-custom" id="anio_generar" name="anio" required>
                                <?php for ($year = date('Y'); $year >= 2020; $year--): ?>
                                    <option value="<?php echo $year; ?>" <?php echo ($year == date('Y')) ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Contratos Activos</label>
                        <div class="list-group">
                            <?php
                            try {
                                $stmt = $db->prepare("
                                    SELECT COUNT(*) as total 
                                    FROM contratos_franquicia 
                                    WHERE estado = 'ACTIVO'
                                ");
                                $stmt->execute();
                                $totalContratos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                            } catch (PDOException $e) {
                                $totalContratos = 0;
                            }
                            ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Total de contratos activos:</span>
                                    <span class="badge bg-primary"><?php echo $totalContratos; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer p-4 border-top-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary-custom">
                        <i class="fas fa-play me-1"></i> Generar Pagos
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Función para confirmar pago
    function confirmarPago(idPago, numeroContrato) {
        if (confirm(`¿Confirmar pago #${idPago} del contrato "${numeroContrato}"?\n\n El trigger registrará automáticamente la auditoría.`)) {
            document.getElementById('idPagoConfirmar').value = idPago;
            document.getElementById('formConfirmarPago').submit();
        }
    }

    // Limpiar filtros
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('limpiarFiltros').addEventListener('click', function() {
            // Redirigir sin filtros
            window.location.href = 'pagos_royalty.php?clear_filters=1';
        });

        // Verificar si hay parámetro para limpiar filtros
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('clear_filters')) {
            // Limpiar filtros de sesión
            fetch('pagos_royalty.php?action=clear_filters', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(() => {
                window.location.href = 'pagos_royalty.php';
            });
        }
    });
</script>

<style>
    :root {
        --primary-color: #4361ee;
        --secondary-color: #3a0ca3;
        --success-color: #28a745;
        --warning-color: #ffc107;
        --info-color: #17a2b8;
        --card-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        --hover-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
    }

    body {
        background-color: #f5f7fb;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .header-section {
        background: linear-gradient(135deg, #4a6baf, #2d4b8e);
        color: white;
        border-radius: 0 0 20px 20px;
        padding: 1.5rem 0;
        margin-bottom: 2rem;
        box-shadow: 0 5px 15px rgba(74, 107, 175, 0.3);
    }

    .main-title {
        font-weight: 700;
        font-size: 1.8rem;
    }

    .btn-primary-custom {
        background: linear-gradient(to right, #4a6baf, #2d4b8e);
        border: none;
        border-radius: 50px;
        padding: 10px 25px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(74, 107, 175, 0.3);
    }

    .btn-primary-custom:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(74, 107, 175, 0.4);
    }

    .btn-outline-light {
        border-color: rgba(255, 255, 255, 0.3);
        color: white;
        border-radius: 50px;
        padding: 10px 20px;
        transition: all 0.3s ease;
    }

    .btn-outline-light:hover {
        background-color: rgba(255, 255, 255, 0.1);
        border-color: white;
    }

    .filter-card {
        background: white;
        border-radius: 15px;
        box-shadow: var(--card-shadow);
        padding: 1.5rem;
        margin-bottom: 2rem;
        transition: all 0.3s ease;
    }

    .filter-card:hover {
        box-shadow: var(--hover-shadow);
    }

    .filter-label {
        font-weight: 600;
        color: var(--secondary-color);
        margin-bottom: 0.5rem;
        display: block;
    }

    .filter-select {
        border-radius: 10px;
        border: 2px solid #eef2ff;
        padding: 10px 15px;
        transition: all 0.3s ease;
    }

    .filter-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(74, 107, 175, 0.2);
    }

    .stats-card {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        transition: all 0.3s ease;
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--hover-shadow);
    }

    .stats-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
        font-size: 1.5rem;
    }

    .icon-pendiente {
        background: linear-gradient(to right, rgba(255, 193, 7, 0.15), rgba(255, 193, 7, 0.25));
        color: var(--warning-color);
    }

    .icon-cancelado {
        background: linear-gradient(to right, rgba(40, 167, 69, 0.15), rgba(40, 167, 69, 0.25));
        color: var(--success-color);
    }

    .icon-anual {
        background: linear-gradient(to right, rgba(23, 162, 184, 0.15), rgba(23, 162, 184, 0.25));
        color: var(--info-color);
    }

    .stats-number {
        font-weight: 700;
        font-size: 1.8rem;
        margin-bottom: 0.2rem;
        color: #333;
    }

    .stats-amount {
        font-size: 1.2rem;
        color: #666;
        font-weight: 600;
    }

    .stats-label {
        color: #6c757d;
        font-size: 0.9rem;
        margin-bottom: 0.2rem;
    }

    .stats-subtext {
        font-size: 0.8rem;
        color: #999;
    }

    .modal-custom {
        border-radius: 20px;
        overflow: hidden;
    }

    .modal-header-custom {
        background: linear-gradient(to right, #4a6baf, #2d4b8e);
        color: white;
        border-bottom: none;
        padding: 1.5rem 2rem;
    }

    .form-control-custom {
        border-radius: 10px;
        border: 2px solid #eef2ff;
        padding: 12px 15px;
        transition: all 0.3s ease;
    }

    .form-control-custom:focus {
        border-color: #4a6baf;
        box-shadow: 0 0 0 3px rgba(74, 107, 175, 0.2);
    }

    .table th {
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
        color: #4a6baf;
    }

    .table td {
        vertical-align: middle;
    }

    .table-hover tbody tr:hover {
        background-color: rgba(74, 107, 175, 0.05);
    }

    .badge {
        padding: 0.5em 0.8em;
        font-weight: 500;
    }

    .bg-warning {
        background-color: #ffc107 !important;
    }

    .bg-success {
        background-color: #28a745 !important;
    }

    @media (max-width: 768px) {
        .header-section {
            border-radius: 0 0 15px 15px;
        }

        .stats-card {
            padding: 1rem;
        }

        .filter-card {
            padding: 1rem;
        }

        .table-responsive {
            font-size: 0.9rem;
        }
        
        .btn-group {
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .btn-primary-custom,
        .btn-outline-light {
            width: 100%;
            margin: 0.2rem 0;
        }
    }
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>