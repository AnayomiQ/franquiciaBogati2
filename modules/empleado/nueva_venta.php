<?php
// modules/empleado/nueva_venta.php

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

// Configurar título de la página
$pageTitle = APP_NAME . ' - Nueva Venta';
$show_breadcrumb = true;
$breadcrumb_items = [
    ['text' => 'Dashboard', 'url' => BASE_URL . 'dashboard.php'],
    ['text' => 'Panel Empleado', 'url' => BASE_URL . 'modules/empleado/dashboard.php'],
    ['text' => 'Ventas', 'url' => BASE_URL . 'modules/empleado/ventas.php'],
    ['text' => 'Nueva Venta']
];

// Incluir header
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-cart-plus text-primary me-2"></i>
                        Nueva Venta
                    </h5>
                </div>
                <div class="card-body">
                    <form id="formVenta" method="POST" action="procesar_venta.php">
                        <!-- Seleccionar Cliente -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Cliente</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <select class="form-select" id="selectCliente" name="id_cliente">
                                        <option value="">Seleccionar cliente existente</option>
                                        <!-- Las opciones se llenarán con JavaScript -->
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <button type="button" class="btn btn-outline-primary w-100" 
                                            data-bs-toggle="modal" data-bs-target="#modalNuevoCliente">
                                        <i class="fas fa-user-plus me-2"></i>
                                        Crear Nuevo Cliente
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Buscar Productos -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Buscar Productos</label>
                            <div class="input-group">
                                <input type="text" class="form-control" 
                                       id="buscarProducto" 
                                       placeholder="Buscar por nombre o código...">
                                <button class="btn btn-primary" type="button">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Lista de Productos Seleccionados -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3">Productos en la Venta</h6>
                            <div class="table-responsive">
                                <table class="table" id="tablaProductos">
                                    <thead>
                                        <tr>
                                            <th width="40%">Producto</th>
                                            <th width="15%">Precio</th>
                                            <th width="15%">Cantidad</th>
                                            <th width="15%">Subtotal</th>
                                            <th width="15%">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="cuerpoTablaProductos">
                                        <!-- Se llenará con JavaScript -->
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-end fw-bold">Total:</td>
                                            <td colspan="2" class="fw-bold text-success" id="totalVenta">$0.00</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Método de Pago -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Método de Pago</label>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" 
                                               name="metodo_pago" value="efectivo" checked>
                                        <label class="form-check-label">
                                            <i class="fas fa-money-bill-wave me-2"></i>
                                            Efectivo
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" 
                                               name="metodo_pago" value="tarjeta">
                                        <label class="form-check-label">
                                            <i class="fas fa-credit-card me-2"></i>
                                            Tarjeta
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" 
                                               name="metodo_pago" value="transferencia">
                                        <label class="form-check-label">
                                            <i class="fas fa-university me-2"></i>
                                            Transferencia
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Observaciones -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Observaciones</label>
                            <textarea class="form-control" name="observaciones" 
                                      rows="3" placeholder="Notas adicionales..."></textarea>
                        </div>

                        <!-- Botones -->
                        <div class="d-flex justify-content-between">
                            <a href="ventas.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-check-circle me-2"></i>
                                Procesar Venta
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Resumen -->
        <div class="col-md-4">
            <div class="card shadow sticky-top" style="top: 20px;">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-receipt text-success me-2"></i>
                        Resumen de Venta
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="fw-bold">Empleado:</h6>
                        <p><?php echo htmlspecialchars($userInfo['nombres'] . ' ' . $userInfo['apellidos']); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="fw-bold">Local:</h6>
                        <p><?php echo htmlspecialchars($local_codigo ?? 'No asignado'); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="fw-bold">Fecha:</h6>
                        <p><?php echo date('d/m/Y H:i'); ?></p>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Subtotal:</span>
                        <span id="resumenSubtotal">$0.00</span>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>IVA (12%):</span>
                        <span id="resumenIVA">$0.00</span>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Descuento:</span>
                        <span id="resumenDescuento">$0.00</span>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold">Total:</h5>
                        <h3 class="text-success fw-bold" id="resumenTotal">$0.00</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Nuevo Cliente -->
<div class="modal fade" id="modalNuevoCliente" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Formulario para nuevo cliente -->
                <div class="mb-3">
                    <label class="form-label">Cédula</label>
                    <input type="text" class="form-control" id="clienteCedula">
                </div>
                <div class="mb-3">
                    <label class="form-label">Nombres</label>
                    <input type="text" class="form-control" id="clienteNombres">
                </div>
                <div class="mb-3">
                    <label class="form-label">Apellidos</label>
                    <input type="text" class="form-control" id="clienteApellidos">
                </div>
                <div class="mb-3">
                    <label class="form-label">Teléfono</label>
                    <input type="tel" class="form-control" id="clienteTelefono">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" id="clienteEmail">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarCliente()">
                    <i class="fas fa-save me-2"></i>
                    Guardar Cliente
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// JavaScript para manejar la venta
let carrito = [];

$(document).ready(function() {
    cargarClientes();
    cargarProductos();
});

function cargarClientes() {
    // AJAX para cargar clientes
    $.ajax({
        url: 'obtener_clientes.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            let select = $('#selectCliente');
            select.empty();
            select.append('<option value="">Seleccionar cliente existente</option>');
            
            response.forEach(cliente => {
                select.append(`
                    <option value="${cliente.id_cliente}">
                        ${cliente.nombres} ${cliente.apellidos} - CI: ${cliente.cedula}
                    </option>
                `);
            });
        }
    });
}

function cargarProductos() {
    // Esta función cargaría los productos disponibles
    // Implementar según necesidad
}

function actualizarResumen() {
    let subtotal = 0;
    
    carrito.forEach(item => {
        subtotal += item.precio * item.cantidad;
    });
    
    let iva = subtotal * 0.12;
    let descuento = 0; // Implementar lógica de descuento si es necesario
    let total = subtotal + iva - descuento;
    
    $('#resumenSubtotal').text('$' + subtotal.toFixed(2));
    $('#resumenIVA').text('$' + iva.toFixed(2));
    $('#resumenDescuento').text('$' + descuento.toFixed(2));
    $('#resumenTotal').text('$' + total.toFixed(2));
    $('#totalVenta').text('$' + total.toFixed(2));
}

function guardarCliente() {
    // Implementar AJAX para guardar nuevo cliente
}

</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>