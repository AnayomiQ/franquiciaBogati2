<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../db_connection.php';

startSession();
requireAuth();
requireAnyRole(['admin']);

$db = Database::getConnection();

$id = $_GET['id'] ?? 0;

if ($id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM usuarios_sistema WHERE id_usuario = ?");
        $stmt->execute([$id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario) {
            echo json_encode(['success' => true, 'usuario' => $usuario]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID no válido']);
}
?>