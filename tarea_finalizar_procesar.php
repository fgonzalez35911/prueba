<?php
// Archivo: tarea_finalizar_procesar.php
session_start();
include 'conexion.php'; 

// 1. Proteger la página (solo Empleado y solo POST)
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'empleado' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$id_usuario_actual = $_SESSION['usuario_id'];
$id_tarea = (int)($_POST['id_tarea'] ?? 0);
$nota_final = trim($_POST['nota_final'] ?? '');
$nuevo_estado = 'finalizada_tecnico';
$redirect_error = "tareas_lista.php?error=Error al procesar la finalización de la tarea.";
$redirect_success = "tareas_lista.php?success=Tarea #{$id_tarea} completada y enviada a verificación.";

if ($id_tarea <= 0) {
    header("Location: " . $redirect_error);
    exit();
}

try {
    $pdo->beginTransaction();

    // 2. Obtener datos de la tarea para validación, adjunto obligatorio y administrador
    // Incluye 'adjunto_obligatorio'
    $sql_tarea = "SELECT t.id_creador, t.titulo, t.adjunto_obligatorio, u.nombre_completo as nombre_tecnico
                  FROM tareas t
                  JOIN usuarios u ON t.id_asignado = u.id_usuario
                  WHERE t.id_tarea = :id_tarea AND t.id_asignado = :id_asignado AND t.estado = 'en_proceso'";
    $stmt_tarea = $pdo->prepare($sql_tarea);
    $stmt_tarea->execute([
        ':id_tarea' => $id_tarea,
        ':id_asignado' => $id_usuario_actual
    ]);
    $tarea_info = $stmt_tarea->fetch(PDO::FETCH_ASSOC);

    if (!$tarea_info) {
        throw new Exception("Tarea no encontrada, no asignada a usted o ya está finalizada/verificada.");
    }
    
    // Si la columna adjunto_obligatorio no existe, asumimos 1 (obligatorio)
    $adjunto_obligatorio = $tarea_info['adjunto_obligatorio'] ?? 1; 
    $id_administrador = $tarea_info['id_creador'];
    $titulo_tarea = $tarea_info['titulo'];
    $nombre_tecnico = $tarea_info['nombre_tecnico'];

    // 3. Manejo y Validación de Adjuntos
    $archivos_adjuntos = $_FILES['adjuntos_finales'] ?? [];
    $archivos_subidos_count = 0;

    if (isset($archivos_adjuntos['name']) && is_array($archivos_adjuntos['name'])) {
        
        $upload_dir = 'uploads/tareas/' . $id_tarea . '/';
        
        // Crear carpeta si no existe (Permiso 0777 es necesario para XAMPP/WAMP)
        if (!is_dir($upload_dir)) {
            // true en el tercer argumento permite la creación recursiva de directorios
            if (!mkdir($upload_dir, 0777, true)) {
                 throw new Exception("Error al crear el directorio de subida: {$upload_dir}. Verifique permisos.");
            }
        }

        foreach ($archivos_adjuntos['name'] as $key => $name) {
            // Solo procesamos archivos que se subieron sin error y tienen nombre
            if ($archivos_adjuntos['error'][$key] === UPLOAD_ERR_OK && !empty($name)) {
                $nombre_temporal = $archivos_adjuntos['tmp_name'][$key];
                $nombre_original = basename($archivos_adjuntos['name'][$key]);
                $tamano = $archivos_adjuntos['size'][$key];
                
                // Generar nombre seguro y único
                $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
                $nombre_seguro = uniqid('final_') . '.' . $extension;
                $ruta_destino = $upload_dir . $nombre_seguro;
                $ruta_relativa_db = $ruta_destino; 

                if (move_uploaded_file($nombre_temporal, $ruta_destino)) {
                    // Insertar registro del adjunto en la tabla 'adjuntos'
                    $sql_insert_adjunto = "INSERT INTO adjuntos (id_tarea, nombre_archivo, ruta_relativa, tamano, tipo_adjunto) 
                                           VALUES (:id_tarea, :nombre, :ruta, :tamano, 'final')";
                    $stmt_adjunto = $pdo->prepare($sql_insert_adjunto);
                    $stmt_adjunto->execute([
                        ':id_tarea' => $id_tarea,
                        ':nombre' => $nombre_original,
                        ':ruta' => $ruta_relativa_db,
                        ':tamano' => $tamano
                    ]);
                    $archivos_subidos_count++;
                } else {
                    throw new Exception("Error al mover el archivo subido: {$nombre_original}.");
                }
            }
        }
    }
    
    // 4. Validación de adjunto obligatorio
    if ($adjunto_obligatorio == 1 && $archivos_subidos_count === 0) {
        throw new Exception("Adjunto de finalización es obligatorio, pero no se subió ningún archivo.");
    }


    // 5. Actualizar el estado de la tarea y la nota final
    $sql_update = "UPDATE tareas SET 
                        estado = :estado, 
                        fecha_finalizacion = NOW(), 
                        nota_final = :nota_final 
                   WHERE id_tarea = :id_tarea AND id_asignado = :id_asignado AND estado = 'en_proceso'";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([
        ':estado' => $nuevo_estado,
        ':nota_final' => $nota_final,
        ':id_tarea' => $id_tarea,
        ':id_asignado' => $id_usuario_actual
    ]);

    
    // 6. Insertar NOTIFICACIÓN para el ADMINISTRADOR
    if (!empty($id_administrador) && is_numeric($id_administrador) && $id_administrador > 0) {
        $mensaje_notificacion = "El técnico {$nombre_tecnico} ha finalizado la tarea: {$titulo_tarea}. Requiere su verificación.";
        
        $url_notificacion = "tarea_ver.php?id={$id_tarea}"; 
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
    header("Location: " . $redirect_success);

} catch (Exception $e) {
    $pdo->rollBack();
    // Guardar el error en el log para debugging
    error_log("Error al finalizar tarea #{$id_tarea} por el usuario #{$id_usuario_actual}: " . $e->getMessage());
    
    // Redireccionar con un mensaje de error
    $error_message = urlencode("Error al finalizar la tarea: " . $e->getMessage());
    header("Location: " . "tareas_lista.php?error={$error_message}");
}
?>