<?php
// Archivo: tarea_actualizar_estado.php
session_start();
include 'conexion.php'; 

header('Content-Type: application/json');

// 1. Verificación de seguridad y rol
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'empleado') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado o no autorizado.']);
    exit();
}

$id_usuario_actual = $_SESSION['usuario_id'];
$id_tarea = (int)($_POST['id_tarea'] ?? 0);
$nuevo_estado = $_POST['nuevo_estado'] ?? '';

if ($id_tarea <= 0 || $nuevo_estado !== 'finalizada_tecnico') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos de entrada inválidos.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 2. Obtener datos de la tarea para identificar al Administrador (id_creador)
    $sql_tarea = "SELECT t.id_creador, t.titulo, u.nombre_completo as nombre_tecnico
                  FROM tareas t
                  JOIN usuarios u ON t.id_asignado = u.id_usuario
                  WHERE t.id_tarea = :id_tarea AND t.id_asignado = :id_asignado";
    $stmt_tarea = $pdo->prepare($sql_tarea);
    $stmt_tarea->execute([
        ':id_tarea' => $id_tarea,
        ':id_asignado' => $id_usuario_actual
    ]);
    $tarea_info = $stmt_tarea->fetch(PDO::FETCH_ASSOC);

    if (!$tarea_info) {
        throw new Exception("Tarea no encontrada o no asignada a este usuario.");
    }

    $id_administrador = $tarea_info['id_creador'];
    $titulo_tarea = $tarea_info['titulo'];
    $nombre_tecnico = $tarea_info['nombre_tecnico'];


    // 3. Actualizar el estado de la tarea
    $sql_update = "UPDATE tareas SET estado = :estado, fecha_finalizacion = NOW() 
                   WHERE id_tarea = :id_tarea AND id_asignado = :id_asignado AND estado = 'en_proceso'";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([
        ':estado' => $nuevo_estado,
        ':id_tarea' => $id_tarea,
        ':id_asignado' => $id_usuario_actual
    ]);

    
    // 4. Insertar NOTIFICACIÓN para el ADMINISTRADOR
    if (!empty($id_administrador) && is_numeric($id_administrador) && $id_administrador > 0) {
        $mensaje_notificacion = "El técnico {$nombre_tecnico} ha finalizado la tarea: {$titulo_tarea}. Requiere su verificación.";
        
        // *** CAMBIO CRÍTICO APLICADO AQUÍ ***
        $url_notificacion = "tarea_ver.php?id={$id_tarea}"; // Enlace directo a la vista de la tarea para la revisión
        // ************************************
        
        $tipo_notificacion = "tarea_terminada"; 
        
        $sql_notif = "INSERT INTO notificaciones (id_usuario_destino, mensaje, url, tipo, leida, fecha_creacion) 
                      VALUES (:id_destino, :mensaje, :url, :tipo, 0, NOW())";
        $stmt_notif = $pdo->prepare($sql_notif);
        $stmt_notif->execute([
            ':id_destino' => $id_administrador, 
            ':mensaje' => $mensaje_notificacion,
            ':url' => $url_notificacion,
            ':tipo' => $tipo_notificacion
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Tarea actualizada y administrador notificado.']);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    error_log("Error al finalizar tarea: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>