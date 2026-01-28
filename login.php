<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/includes/functions.php';

// Redirigir si ya está autenticado
redirectIfAuthenticated();

// Inicializar variables
$error = '';
$success = '';

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $rolSeleccionado = trim($_POST['rol'] ?? '');

    if (empty($username) || empty($password) || empty($rolSeleccionado)) {
        $error = 'Por favor, complete todos los campos';
    } else {
        $db = Database::getConnection();

        try {
            // Buscar usuario por username o email
            $stmt = $db->prepare("
                SELECT 
                    us.*,
                    e.codigo_local,
                    e.id_empleado,
                    f.cedula AS cedula_franquiciado,
                    f.nombres AS nombres_franquiciado,
                    f.apellidos AS apellidos_franquiciado
                FROM usuarios_sistema us
                LEFT JOIN empleados e ON us.id_empleado = e.id_empleado
                LEFT JOIN franquiciados f ON us.cedula_franquiciado = f.cedula
                WHERE (us.username = ? OR us.email = ?) AND us.rol = ?
                LIMIT 1
            ");
            $stmt->execute([$username, $username, $rolSeleccionado]);
            $user = $stmt->fetch();

            if ($user) {
                // Verificar contraseña
                if (password_verify($password, $user['password_hash']) || $password === $user['password_hash']) {
                    // Verificar estado del usuario
                    if ($user['estado'] !== 'ACTIVO') {
                        $error = 'Tu cuenta está ' . strtolower($user['estado']) . '. Contacta al administrador.';
                        logAction('LOGIN_FALLIDO', 'Usuario inactivo: ' . $username, 'usuarios_sistema');
                    } else {
                        // Configurar sesión
                        $_SESSION['user_id'] = $user['id_usuario'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_nombres'] = $user['nombres'] ?? ($user['nombres_franquiciado'] ?? '');
                        $_SESSION['user_apellidos'] = $user['apellidos'] ?? ($user['apellidos_franquiciado'] ?? '');
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_role'] = $user['rol'];
                        $_SESSION['is_authenticated'] = true;
                        $_SESSION['last_activity'] = time();

                        // Local del usuario
                        if (!empty($user['cedula_franquiciado'])) {
                            $_SESSION['cedula_franquiciado'] = $user['cedula_franquiciado'];

                            // Obtener el primer local del franquiciado
                            $stmt_local = $db->prepare("SELECT codigo_local FROM locales WHERE cedula_franquiciado = ? LIMIT 1");
                            $stmt_local->execute([$user['cedula_franquiciado']]);
                            $local = $stmt_local->fetch();
                            $_SESSION['codigo_local'] = $local['codigo_local'] ?? null;
                        } elseif (!empty($user['id_empleado'])) {
                            $_SESSION['id_empleado'] = $user['id_empleado'];
                            $_SESSION['codigo_local'] = $user['codigo_local'];
                        } elseif ($user['rol'] === 'admin') {
                            $_SESSION['codigo_local'] = null; // admin no tiene local
                        }

                        // Actualizar último acceso
                        $updateStmt = $db->prepare("UPDATE usuarios_sistema SET ultimo_acceso = CURRENT_TIMESTAMP WHERE id_usuario = ?");
                        $updateStmt->execute([$user['id_usuario']]);

                        // Registrar log
                        logAction('LOGIN_EXITOSO', 'Inicio de sesión desde ' . get_client_ip(), 'usuarios_sistema');

                        // Redirigir al dashboard
                        header('Location: ' . BASE_URL . 'dashboard.php');
                        exit();
                    }
                } else {
                    $error = 'Usuario o contraseña incorrectos';
                    logAction('LOGIN_FALLIDO', 'Contraseña incorrecta: ' . $username, 'usuarios_sistema');
                }
            } else {
                $error = 'Usuario no encontrado';
                logAction('LOGIN_FALLIDO', 'Usuario no encontrado: ' . $username, 'usuarios_sistema');
            }
        } catch (PDOException $e) {
            error_log('Error en login: ' . $e->getMessage());
            $error = 'Error en el sistema. Por favor, intente más tarde.';
        }
    }
}

// Incluir header público
$page_title = 'Iniciar Sesión - ' . APP_NAME;
include __DIR__ . '/includes/header_public.php';
?>

<style>
/* Paleta de colores: Amarillo (#FDB813), Blanco (#FFFFFF), Negro (#000000), Café (#8B4513) */
:root {
    --amarillo: #FDB813;
    --amarillo-claro: #FFE082;
    --amarillo-oscuro: #FF8C00;
    --blanco: #FFFFFF;
    --negro: #000000;
    --negro-claro: #333333;
    --cafe: #8B4513;
    --cafe-claro: #A0522D;
    --cafe-oscuro: #654321;
    --gris: #F5F5F5;
}

/* Animaciones para las letras */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes textGlow {
    0%, 100% {
        text-shadow: 0 0 5px rgba(253, 184, 19, 0.5);
    }
    50% {
        text-shadow: 0 0 15px rgba(253, 184, 19, 0.8);
    }
}

@keyframes pulse {
    0% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
    100% {
        transform: scale(1);
    }
}

/* Estilos existentes */
.login-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background: linear-gradient(135deg, var(--amarillo) 0%, var(--cafe) 100%);
    position: relative;
    overflow: hidden;
}

.login-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path fill="%23FFFFFF" opacity="0.1" d="M0,0 L100,0 L100,100 Z"/></svg>');
    background-size: cover;
    pointer-events: none;
}

.login-card {
    background: var(--blanco);
    padding: 40px;
    border-radius: 15px;
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2);
    width: 100%;
    max-width: 400px;
    position: relative;
    z-index: 1;
    border: 1px solid var(--amarillo-claro);
    animation: fadeInUp 0.8s ease-out;
}

.brand-container {
    text-align: center;
    margin-bottom: 30px;
}

.brand-text {
    font-family: 'Arial Black', sans-serif;
    font-size: 42px;
    font-weight: 900;
    background: linear-gradient(45deg, var(--amarillo), var(--cafe));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: textGlow 3s ease-in-out infinite;
    letter-spacing: 2px;
    text-transform: uppercase;
    position: relative;
    display: inline-block;
}

.brand-text::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 60px;
    height: 3px;
    background: var(--amarillo);
    border-radius: 2px;
}

.login-title {
    text-align: center;
    color: var(--negro);
    margin-bottom: 30px;
    font-size: 26px;
    font-weight: 700;
    animation: fadeInUp 0.8s ease-out 0.1s both;
    position: relative;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.login-title::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 40px;
    height: 2px;
    background: var(--cafe);
}

.form-group {
    position: relative;
    margin-bottom: 25px;
    animation: fadeInUp 0.8s ease-out 0.2s both;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    color: var(--negro-claro);
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-group:focus-within .form-label {
    color: var(--cafe);
    transform: translateY(-2px);
}

.form-control-custom {
    width: 100%;
    padding: 14px 15px 14px 45px;
    border: 2px solid var(--amarillo-claro);
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s ease;
    box-sizing: border-box;
    background: var(--gris);
    color: var(--negro);
    font-weight: 500;
}

.form-control-custom:focus {
    outline: none;
    border-color: var(--amarillo);
    box-shadow: 0 0 0 3px rgba(253, 184, 19, 0.2);
    background: var(--blanco);
    animation: pulse 0.3s ease;
}

.input-icon {
    position: absolute;
    left: 15px;
    top: 40px;
    color: var(--cafe);
    transition: all 0.3s ease;
    font-size: 16px;
}

.form-group:focus-within .input-icon {
    color: var(--amarillo-oscuro);
    transform: scale(1.1);
}

.password-toggle {
    position: absolute;
    right: 15px;
    top: 40px;
    background: none;
    border: none;
    color: var(--cafe);
    cursor: pointer;
    padding: 0;
    font-size: 18px;
    transition: all 0.3s ease;
    z-index: 2;
}

.password-toggle:hover {
    color: var(--amarillo-oscuro);
    transform: scale(1.1);
}

.btn-acceso {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, var(--amarillo) 0%, var(--cafe) 100%);
    color: var(--blanco);
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    animation: fadeInUp 0.8s ease-out 0.4s both;
    text-transform: uppercase;
    letter-spacing: 1px;
    position: relative;
    overflow: hidden;
}

.btn-acceso::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.btn-acceso:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(139, 69, 19, 0.3);
    animation: pulse 1s infinite;
}

.btn-acceso:hover::before {
    width: 300px;
    height: 300px;
}

.btn-acceso:active {
    transform: translateY(0);
}

.forgot-password {
    text-align: center;
    margin-top: 25px;
    animation: fadeInUp 0.8s ease-out 0.6s both;
}

.forgot-link {
    color: var(--cafe);
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    position: relative;
    padding: 5px 10px;
    border-radius: 5px;
}

.forgot-link::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--amarillo);
    transition: width 0.3s ease;
}

.forgot-link:hover {
    color: var(--amarillo-oscuro);
    background: rgba(253, 184, 19, 0.1);
}

.forgot-link:hover::after {
    width: 100%;
}

.alert-custom {
    background: rgba(253, 184, 19, 0.1);
    border: 2px solid var(--amarillo);
    color: var(--cafe-oscuro);
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 25px;
    font-size: 14px;
    font-weight: 600;
    animation: fadeInUp 0.5s ease-out;
    text-align: center;
}

/* Animación de partículas en el fondo */
@keyframes float {
    0%, 100% {
        transform: translateY(0) rotate(0deg);
    }
    50% {
        transform: translateY(-20px) rotate(180deg);
    }
}

.floating-particles {
    position: absolute;
    width: 100%;
    height: 100%;
    pointer-events: none;
}

.particle {
    position: absolute;
    background: var(--amarillo);
    border-radius: 50%;
    animation: float 6s infinite ease-in-out;
    opacity: 0.3;
}

.particle:nth-child(1) { top: 10%; left: 10%; width: 20px; height: 20px; animation-delay: 0s; }
.particle:nth-child(2) { top: 20%; right: 15%; width: 15px; height: 15px; animation-delay: 1s; }
.particle:nth-child(3) { bottom: 30%; left: 20%; width: 25px; height: 25px; animation-delay: 2s; }
.particle:nth-child(4) { bottom: 20%; right: 25%; width: 18px; height: 18px; animation-delay: 3s; }
.particle:nth-child(5) { top: 40%; left: 80%; width: 22px; height: 22px; animation-delay: 4s; }
</style>

<!-- Contenedor centrado para el formulario -->
<div class="login-container">
    <!-- Partículas flotantes -->
    <div class="floating-particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>
    
    <div class="login-card">
        
        <!-- MARCA -->
        <div class="brand-container">
            <span class="brand-text">Bogati</span>
        </div>

        <!-- Título -->
        <h2 class="login-title">Iniciar sesión</h2>

        <!-- Mostrar error si existe -->
        <?php if ($error): ?>
            <div class="alert-custom">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Formulario -->
        <form method="POST" action="">
            <!-- Escoger Rol -->
            <div class="form-group">
                <label class="form-label">Escoger Rol</label>
                <select class="form-control-custom" name="rol" required>
                    <option value="">Seleccione un rol</option>
                    <option value="admin" <?= (($_POST['rol'] ?? '') === 'admin') ? 'selected' : '' ?>>Administrador</option>
                    <option value="franquiciado" <?= (($_POST['rol'] ?? '') === 'franquiciado') ? 'selected' : '' ?>>Franquiciado</option>
                    <option value="empleado" <?= (($_POST['rol'] ?? '') === 'empleado') ? 'selected' : '' ?>>Empleado</option>
                </select>
                <i class="fas fa-user-tag input-icon"></i>
            </div>

            <!-- Usuario o Email -->
            <div class="form-group">
                <label class="form-label">Usuario </label>
                <input 
                    type="text"
                    class="form-control-custom"
                    name="username"
                    placeholder="Ingrese su usuario o email"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    required
                    autofocus
                >
                <i class="fas fa-user input-icon"></i>
            </div>

            <!-- Contraseña -->
            <div class="form-group">
                <label class="form-label">Contraseña</label>
                <input 
                    type="password" 
                    class="form-control-custom" 
                    name="password" 
                    id="passwordInput"
                    placeholder="Ingrese su contraseña" 
                    required
                >
                <i class="fas fa-lock input-icon"></i>
                <button type="button" class="password-toggle" id="togglePassword">
                    <i class="fas fa-eye"></i>
                </button>
            </div>

            <!-- Botón -->
            <button type="submit" class="btn-acceso">Ingresar al Sistema</button>

            <!-- Olvidó contraseña -->
            <div class="forgot-password">
                <a href="#" class="forgot-link">¿Olvidó su contraseña?</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('passwordInput');
    const eyeIcon = togglePassword.querySelector('i');
    
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        // Cambiar ícono
        if (type === 'text') {
            eyeIcon.classList.remove('fa-eye');
            eyeIcon.classList.add('fa-eye-slash');
        } else {
            eyeIcon.classList.remove('fa-eye-slash');
            eyeIcon.classList.add('fa-eye');
        }
    });
    
    // Efecto de animación para los campos al cargar
    const formGroups = document.querySelectorAll('.form-group');
    formGroups.forEach((group, index) => {
        group.style.animationDelay = `${index * 0.1}s`;
    });
    
    // Efecto ripple en botón
    const buttons = document.querySelectorAll('.btn-acceso');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            const x = e.clientX - e.target.offsetLeft;
            const y = e.clientY - e.target.offsetTop;
            
            const ripple = document.createElement('span');
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
});
</script>

<!-- Quité la línea del footer que causaba error -->
<!-- Si necesitas footer, crea el archivo footer_public.php en includes/ -->
