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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'crear') {
        //  MEJORADO: Trigger manejará la auditoría automáticamente
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $nombres = $_POST['nombres'];
        $apellidos = $_POST['apellidos'];
        $email = $_POST['email'];
        $rol = $_POST['rol'];
        $estado = $_POST['estado'];

        try {
            $stmt = $db->prepare("
                INSERT INTO usuarios_sistema 
                (username, password_hash, nombres, apellidos, email, rol, estado, ultimo_acceso) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$username, $password, $nombres, $apellidos, $email, $rol, $estado]);
            
            //  YA NO NECESITAMOS: El trigger tg_auditoria_crear_usuario registra automáticamente
            setFlashMessage('success', ' Usuario creado exitosamente. La auditoría fue registrada por el trigger.');
            header('Location: usuarios.php');
            exit();
        } catch (PDOException $e) {
            setFlashMessage('error', ' Error al crear usuario: ' . $e->getMessage());
        }
    }
    elseif ($action === 'editar') {
        //  MEJORADO: Triggers manejarán auditoría de cambios
        $id_usuario = $_POST['id_usuario'];
        $nombres = $_POST['nombres'];
        $apellidos = $_POST['apellidos'];
        $email = $_POST['email'];
        $rol = $_POST['rol'];
        $estado = $_POST['estado'];

        try {
            $stmt = $db->prepare("
                UPDATE usuarios_sistema 
                SET nombres = ?, apellidos = ?, email = ?, rol = ?, estado = ?
                WHERE id_usuario = ?
            ");
            $stmt->execute([$nombres, $apellidos, $email, $rol, $estado, $id_usuario]);
            
            // Si se proporcionó nueva contraseña
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE usuarios_sistema SET password_hash = ? WHERE id_usuario = ?");
                $stmt->execute([$password, $id_usuario]);
                
                //  El trigger tg_auditoria_cambio_estado_usuario detecta el cambio de contraseña
            }
            
            setFlashMessage('success', ' Usuario actualizado. Los triggers registraron los cambios automáticamente.');
            header('Location: usuarios.php');
            exit();
        } catch (PDOException $e) {
            setFlashMessage('error', ' Error al actualizar usuario: ' . $e->getMessage());
        }
    }
    elseif ($action === 'eliminar') {
        //  MEJORADO: El trigger tg_auditoria_eliminar_usuario registrará la acción
        try {
            $stmt = $db->prepare("DELETE FROM usuarios_sistema WHERE id_usuario = ? AND id_usuario != ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            
            if ($stmt->rowCount() > 0) {
                setFlashMessage('success', ' Usuario eliminado. El trigger registró la auditoría automáticamente.');
            } else {
                setFlashMessage('warning', ' No se puede eliminar su propio usuario');
            }
            
            header('Location: usuarios.php');
            exit();
        } catch (PDOException $e) {
            setFlashMessage('error', ' Error al eliminar usuario: ' . $e->getMessage());
        }
    }
    // Desactivar usuarios inactivos automáticamente
    elseif ($action === 'desactivar_inactivos') {
        $dias = $_POST['dias_inactividad'] ?? 90;
        
        try {
            // Llamar procedimiento almacenado
            $stmt = $db->prepare("CALL pa_desactivar_usuarios_inactivos(?)");
            $stmt->execute([$dias]);
            
            // Obtener resultado
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            $usuariosDesactivados = $resultado['usuarios_desactivados'] ?? 0;
            
            if ($usuariosDesactivados > 0) {
                setFlashMessage('success', " Se desactivaron $usuariosDesactivados usuarios por inactividad de más de $dias días");
            } else {
                setFlashMessage('info', 'ℹ️ No hay usuarios inactivos que desactivar');
            }
            
            header('Location: usuarios.php');
            exit();
        } catch (PDOException $e) {
            setFlashMessage('error', ' Error: ' . $e->getMessage());
            header('Location: usuarios.php');
            exit();
        }
    }
}

// Generar reporte de actividad
if ($action === 'reporte_actividad') {
    try {
        $stmt = $db->prepare("CALL pa_reporte_actividad_usuarios()");
        $stmt->execute();
        
        $reporte = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Exportar a CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=reporte_actividad_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Usuario', 'Rol', 'Total Acciones (30 días)', 'Última Actividad', 'Nivel Actividad']);
        
        foreach ($reporte as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit();
    } catch (PDOException $e) {
        setFlashMessage('error', ' Error: ' . $e->getMessage());
        header('Location: usuarios.php');
        exit();
    }
}

//  MEJORADO: Obtener lista de usuarios usando la VISTA vw_usuarios_completos
$usuarios = [];
try {
    $stmt = $db->prepare("
        SELECT *
        FROM vw_usuarios_completos
        ORDER BY id_usuario DESC
    ");
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al cargar usuarios: ' . $e->getMessage());
    setFlashMessage('error', 'Error al cargar usuarios');
}

// Obtener datos de usuario específico para editar
$usuarioEditar = null;
if ($action === 'editar' && $id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM usuarios_sistema WHERE id_usuario = ?");
        $stmt->execute([$id]);
        $usuarioEditar = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error al cargar usuario para editar: ' . $e->getMessage());
    }
}

// Obtener estadísticas usando la vista
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN estado = 'ACTIVO' THEN 1 END) as activos,
            COUNT(CASE WHEN rol = 'ADMIN' THEN 1 END) as admins,
            COUNT(CASE WHEN rol = 'EMPLEADO' THEN 1 END) as empleados,
            COUNT(CASE WHEN rol = 'FRANQUICIADO' THEN 1 END) as franquiciados,
            COUNT(CASE WHEN dias_sin_acceso > 90 THEN 1 END) as inactivos_90,
            COUNT(CASE WHEN dias_sin_acceso > 30 THEN 1 END) as inactivos_30
        FROM vw_usuarios_completos
    ");
    $stmt->execute();
    $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $estadisticas = [
        'total' => 0, 'activos' => 0, 'admins' => 0,
        'empleados' => 0, 'franquiciados' => 0, 'inactivos_90' => 0, 'inactivos_30' => 0
    ];
}

$pageTitle = APP_NAME . ' - Gestión de Usuarios';
$pageStyles = ['admin.css'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        
        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-users me-2"></i>Gestión de Usuarios
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <!-- Botón existente -->
                    <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#modalUsuario">
                        <i class="fas fa-plus me-1"></i> Nuevo Usuario
                    </button>
                    
                    <!-- Botón para desactivar inactivos -->
                    <button class="btn btn-warning me-2" data-bs-toggle="modal" data-bs-target="#modalDesactivarInactivos">
                        <i class="fas fa-user-slash me-1"></i> Desactivar Inactivos
                    </button>
                    
                    <!-- Botón para reporte -->
                    <a href="?action=reporte_actividad" class="btn btn-info">
                        <i class="fas fa-file-excel me-1"></i> Reporte de Actividad
                    </a>
                </div>
            </div>

            <!-- Mensajes Flash -->
            <?php displayFlashMessage(); ?>

            <!-- Estadísticas de Usuarios -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Total Usuarios</h6>
                                    <h3 class="mb-0"><?php echo $estadisticas['total']; ?></h3>
                                </div>
                                <div>
                                    <i class="fas fa-users fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Activos</h6>
                                    <h3 class="mb-0"><?php echo $estadisticas['activos']; ?></h3>
                                </div>
                                <div>
                                    <i class="fas fa-user-check fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Administradores</h6>
                                    <h3 class="mb-0"><?php echo $estadisticas['admins']; ?></h3>
                                </div>
                                <div>
                                    <i class="fas fa-user-shield fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Inactivos 90+ días</h6>
                                    <h3 class="mb-0"><?php echo $estadisticas['inactivos_90']; ?></h3>
                                </div>
                                <div>
                                    <i class="fas fa-user-slash fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <input type="text" id="filtroTexto" class="form-control"
                                   placeholder="Buscar usuario, nombre o email">
                        </div>

                        <div class="col-md-3">
                            <select id="filtroRol" class="form-select">
                                <option value="">Todos los roles</option>
                                <option value="ADMIN">Administrador</option>
                                <option value="EMPLEADO">Empleado</option>
                                <option value="FRANQUICIADO">Franquiciado</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <select id="filtroEstado" class="form-select">
                                <option value="">Todos los estados</option>
                                <option value="ACTIVO">Activo</option>
                                <option value="INACTIVO">Inactivo</option>
                                <option value="SUSPENDIDO">Suspendido</option>
                            </select>
                        </div>

                        <div class="col-md-2 d-grid">
                            <button class="btn btn-outline-secondary" onclick="limpiarFiltros()">
                                Limpiar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Usuarios -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="tablaUsuarios">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuario</th>
                                    <th>Nombre Completo</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Último Acceso</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><?php echo $usuario['id_usuario']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($usuario['username']); ?></strong>
                                            
                                            <!-- Indicador de actividad -->
                                            <?php if (isset($usuario['dias_sin_acceso'])): ?>
                                                <?php if ($usuario['dias_sin_acceso'] > 90): ?>
                                                    <span class="badge bg-danger ms-2" title="Más de 90 días sin acceso">
                                                        <i class="fas fa-exclamation-triangle"></i>
                                                    </span>
                                                <?php elseif ($usuario['dias_sin_acceso'] > 30): ?>
                                                    <span class="badge bg-warning ms-2" title="Más de 30 días sin acceso">
                                                        <i class="fas fa-clock"></i>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($usuario['nombre_completo']); ?>
                                            
                                            <!-- Mostrar información adicional según rol -->
                                            <?php if ($usuario['rol'] === 'FRANQUICIADO' && $usuario['franquiciado_nombre']): ?>
                                                <br><small class="text-muted">
                                                    <i class="fas fa-store"></i> <?php echo htmlspecialchars($usuario['franquiciado_nombre']); ?>
                                                </small>
                                            <?php elseif ($usuario['rol'] === 'EMPLEADO' && $usuario['empleado_nombre']): ?>
                                                <br><small class="text-muted">
                                                    <i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($usuario['empleado_cargo']); ?>
                                                    - <?php echo htmlspecialchars($usuario['nombre_local'] ?? 'Sin local'); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $usuario['rol'] === 'ADMIN' ? 'danger' : 'primary'; ?>">
                                                <?php echo $usuario['rol']; ?>
                                            </span>
                                            
                                            <!-- Total de acciones -->
                                            <?php if (isset($usuario['total_acciones']) && $usuario['total_acciones'] > 0): ?>
                                                <br><small class="text-muted">
                                                    <?php echo $usuario['total_acciones']; ?> acciones
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <!-- Estado con indicador de actividad real -->
                                            <span class="badge bg-<?php echo $usuario['estado'] === 'ACTIVO' ? 'success' : 'secondary'; ?>">
                                                <?php echo $usuario['estado']; ?>
                                            </span>
                                            
                                            <?php if (isset($usuario['estado_real']) && $usuario['estado_real'] !== $usuario['estado']): ?>
                                                <br><small class="text-warning">
                                                    (<?php echo str_replace('_', ' ', $usuario['estado_real']); ?>)
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $usuario['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) : 'Nunca'; ?>
                                            
                                            <!-- Días sin acceso -->
                                            <?php if (isset($usuario['dias_sin_acceso']) && $usuario['dias_sin_acceso'] > 0): ?>
                                                <br><small class="text-muted">
                                                    Hace <?php echo $usuario['dias_sin_acceso']; ?> días
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="usuarios.php?action=editar&id=<?php echo $usuario['id_usuario']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                                                   data-bs-target="#modalUsuario" onclick="cargarUsuario(<?php echo $usuario['id_usuario']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($usuario['id_usuario'] != $_SESSION['user_id']): ?>
                                                    <button class="btn btn-sm btn-outline-danger" 
                                                            onclick="confirmarEliminar(<?php echo $usuario['id_usuario']; ?>, '<?php echo htmlspecialchars($usuario['username']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal para crear/editar usuario -->
<div class="modal fade" id="modalUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formUsuario" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_usuario" id="id_usuario" value="">
                    <input type="hidden" name="action" id="formAction" value="crear">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Usuario *</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                        <small class="text-muted">Mínimo 4 caracteres</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label" id="labelPassword">Contraseña *</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <small class="text-muted" id="passwordHelp">
                            <span id="fortalezaPassword"></span>
                        </small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombres" class="form-label">Nombres *</label>
                            <input type="text" class="form-control" id="nombres" name="nombres" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="apellidos" class="form-label">Apellidos *</label>
                            <input type="text" class="form-control" id="apellidos" name="apellidos" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="rol" class="form-label">Rol *</label>
                            <select class="form-select" id="rol" name="rol" required>
                                <option value="ADMIN">Administrador</option>
                                <option value="EMPLEADO">Empleado</option>
                                <option value="FRANQUICIADO">Franquiciado</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="estado" class="form-label">Estado *</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="ACTIVO">Activo</option>
                                <option value="INACTIVO">Inactivo</option>
                                <option value="SUSPENDIDO">Suspendido</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Información de triggers -->
                    <div class="alert alert-info mt-3">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            <strong>Automatización activada:</strong> 
                            Todos los cambios serán auditados automáticamente por los triggers de la base de datos.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para desactivar usuarios inactivos -->
<div class="modal fade" id="modalDesactivarInactivos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?action=desactivar_inactivos">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="fas fa-user-slash me-2"></i>
                        Desactivar Usuarios Inactivos
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Esta acción desactivará todos los usuarios que no hayan accedido
                        al sistema en el tiempo especificado usando el <strong>procedimiento almacenado</strong>.
                    </div>
                    
                    <div class="mb-3">
                        <label for="dias_inactividad" class="form-label">
                            Días de inactividad *
                        </label>
                        <select class="form-select" name="dias_inactividad" id="dias_inactividad" required>
                            <option value="30">30 días</option>
                            <option value="60">60 días</option>
                            <option value="90" selected>90 días</option>
                            <option value="180">180 días</option>
                            <option value="365">1 año</option>
                        </select>
                        <small class="text-muted">
                            Se ejecutará el procedimiento almacenado: <code>pa_desactivar_usuarios_inactivos()</code>
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Vista Previa:</label>
                        <div id="previsualizacion" class="border p-2 bg-light">
                            <small class="text-muted">
                                Seleccione un período para ver los usuarios afectados
                            </small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-warning" 
                            onclick="return confirm('¿Está seguro de desactivar estos usuarios?\n\nEl procedimiento almacenado manejará todo automáticamente.')">
                        <i class="fas fa-check me-1"></i>
                        Ejecutar Procedimiento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Formulario oculto para eliminar -->
<form id="formEliminar" method="POST" style="display: none;">
    <input type="hidden" name="action" value="eliminar">
    <input type="hidden" name="id" id="idEliminar">
</form>

<script>
// Función para cargar datos de usuario en el modal
function cargarUsuario(id) {
    fetch(`ajax/get-usuario.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const usuario = data.usuario;
                document.getElementById('modalTitulo').textContent = 'Editar Usuario';
                document.getElementById('formAction').value = 'editar';
                document.getElementById('id_usuario').value = usuario.id_usuario;
                document.getElementById('username').value = usuario.username;
                document.getElementById('username').readOnly = true;
                document.getElementById('password').required = false;
                document.getElementById('labelPassword').textContent = 'Nueva Contraseña (opcional)';
                document.getElementById('passwordHelp').innerHTML = '<i class="fas fa-info-circle"></i> Dejar en blanco para mantener la contraseña actual. El trigger registrará el cambio.';
                document.getElementById('nombres').value = usuario.nombres;
                document.getElementById('apellidos').value = usuario.apellidos;
                document.getElementById('email').value = usuario.email;
                document.getElementById('rol').value = usuario.rol;
                document.getElementById('estado').value = usuario.estado;
            }
        })
        .catch(error => console.error('Error:', error));
}

// Función para validar fortaleza de contraseña
document.getElementById('password')?.addEventListener('input', function() {
    const password = this.value;
    const indicador = document.getElementById('fortalezaPassword');
    
    if (password.length === 0) {
        indicador.innerHTML = '';
        return;
    }
    
    let fortaleza = 0;
    let mensaje = '';
    let color = 'text-danger';
    
    if (password.length >= 8) fortaleza++;
    if (/[A-Z]/.test(password)) fortaleza++;
    if (/[a-z]/.test(password)) fortaleza++;
    if (/[0-9]/.test(password)) fortaleza++;
    if (/[^A-Za-z0-9]/.test(password)) fortaleza++;
    
    switch(fortaleza) {
        case 1:
            mensaje = 'Muy débil';
            break;
        case 2:
            mensaje = 'Débil';
            color = 'text-warning';
            break;
        case 3:
            mensaje = 'Moderada';
            color = 'text-info';
            break;
        case 4:
            mensaje = 'Fuerte';
            color = 'text-success';
            break;
        case 5:
            mensaje = 'Muy fuerte';
            color = 'text-success';
            break;
    }
    
    indicador.innerHTML = `<span class="${color}"><i class="fas fa-shield-alt"></i> ${mensaje}</span>`;
});

// Función para filtrar usuarios
function filtrarUsuarios() {
    const texto = document.getElementById('filtroTexto').value.toLowerCase();
    const rol = document.getElementById('filtroRol').value;
    const estado = document.getElementById('filtroEstado').value;

    document.querySelectorAll('#tablaUsuarios tbody tr').forEach(fila => {
        const contenido = fila.innerText.toLowerCase();
        const rolFila = fila.children[4].innerText.trim().split('\n')[0];
        const estadoFila = fila.children[5].innerText.trim().split('\n')[0];

        let visible = true;

        if (texto && !contenido.includes(texto)) visible = false;
        if (rol && rol !== rolFila) visible = false;
        if (estado && estado !== estadoFila) visible = false;

        fila.style.display = visible ? '' : 'none';
    });
}

// Asignar eventos de filtrado
document.getElementById('filtroTexto').addEventListener('keyup', filtrarUsuarios);
document.getElementById('filtroRol').addEventListener('change', filtrarUsuarios);
document.getElementById('filtroEstado').addEventListener('change', filtrarUsuarios);

function limpiarFiltros() {
    document.getElementById('filtroTexto').value = '';
    document.getElementById('filtroRol').value = '';
    document.getElementById('filtroEstado').value = '';
    filtrarUsuarios();
}

// Previsualización de usuarios a desactivar
document.getElementById('dias_inactividad')?.addEventListener('change', function() {
    const dias = this.value;
    const preview = document.getElementById('previsualizacion');
    
    // Contar usuarios inactivos en la tabla actual
    let contador = 0;
    let listaUsuarios = [];
    
    document.querySelectorAll('#tablaUsuarios tbody tr').forEach(fila => {
        const diasTexto = fila.querySelector('td:nth-child(7) small')?.textContent;
        const username = fila.querySelector('td:nth-child(2) strong')?.textContent;
        
        if (diasTexto && username) {
            const match = diasTexto.match(/Hace (\d+) días/);
            if (match && parseInt(match[1]) > parseInt(dias)) {
                contador++;
                listaUsuarios.push(username.trim());
            }
        }
    });
    
    if (contador > 0) {
        preview.innerHTML = `
            <div class="mb-2">
                <i class="fas fa-info-circle text-warning"></i>
                <strong>${contador}</strong> usuario(s) serán desactivados
                (sin acceso por más de ${dias} días)
            </div>
            <div class="mt-2">
                <small><strong>Usuarios afectados:</strong></small><br>
                <small class="text-muted">${listaUsuarios.slice(0, 5).join(', ')}${listaUsuarios.length > 5 ? '...' : ''}</small>
            </div>
        `;
    } else {
        preview.innerHTML = `
            <div class="text-success">
                <i class="fas fa-check-circle"></i>
                No hay usuarios que cumplan con este criterio.
            </div>
        `;
    }
});

// Función para confirmar eliminación
function confirmarEliminar(id, username) {
    if (confirm(`¿Está seguro de eliminar al usuario "${username}"?\n\n El trigger registrará esta acción automáticamente.`)) {
        document.getElementById('idEliminar').value = id;
        document.getElementById('formEliminar').submit();
    }
}

// Resetear modal al cerrar
document.getElementById('modalUsuario').addEventListener('hidden.bs.modal', function () {
    document.getElementById('modalTitulo').textContent = 'Nuevo Usuario';
    document.getElementById('formAction').value = 'crear';
    document.getElementById('formUsuario').reset();
    document.getElementById('username').readOnly = false;
    document.getElementById('password').required = true;
    document.getElementById('labelPassword').textContent = 'Contraseña *';
    document.getElementById('passwordHelp').innerHTML = '<span id="fortalezaPassword"></span>';
    document.getElementById('fortalezaPassword').innerHTML = '';
});

// Inicializar previsualización al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    const diasSelect = document.getElementById('dias_inactividad');
    if (diasSelect) {
        diasSelect.dispatchEvent(new Event('change'));
    }
});
</script>

<style>
/* Estilos para indicadores */
.badge.bg-warning {
    cursor: help;
}

.badge.bg-danger {
    cursor: help;
}

.card {
    transition: transform 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
}

/* Estilos para los filtros */
#previsualizacion {
    min-height: 80px;
    border-radius: 5px;
}

/* Estilos para la fortaleza de contraseña */
#fortalezaPassword {
    font-size: 0.85rem;
    font-weight: 600;
}

/* Responsive */
@media (max-width: 768px) {
    .btn-toolbar {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .btn-toolbar .btn {
        width: 100%;
    }
    
    .row .col-md-3 {
        margin-bottom: 1rem;
    }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>