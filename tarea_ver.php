<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$id_usuario = $_SESSION['usuario_id'];
$rol_usuario = $_SESSION['usuario_rol'];
$mensaje = '';
$alerta_tipo = '';

// 1. Obtener ID de la tarea desde la URL
$id_tarea = $_GET['id'] ?? 0;
if (!is_numeric($id_tarea) || $id_tarea <= 0) {
    header("Location: tareas_lista.php");
    exit();
}

// 2. Cargar detalles de la tarea
try {
    $sql_tarea = "
        SELECT 
            t.*, 
            uc.nombre_completo AS creador_nombre, 
            ua.nombre_completo AS asignado_nombre,
            c.nombre AS categoria_nombre
        FROM tareas t
        LEFT JOIN usuarios uc ON t.id_creador = uc.id_usuario
        LEFT JOIN usuarios ua ON t.id_asignado = ua.id_usuario
        LEFT JOIN categorias c ON t.id_categoria = c.id_categoria
        WHERE t.id_tarea = :id_tarea
    ";
    $stmt_tarea = $pdo->prepare($sql_tarea);
    $stmt_tarea->bindParam(':id_tarea', $id_tarea, PDO::PARAM_INT);
    $stmt_tarea->execute();
    $tarea = $stmt_tarea->fetch();

    if (!$tarea) {
        die("Error: Tarea no encontrada.");
    }
    
    // 3. Cargar adjuntos iniciales y finales
    $sql_adjuntos = "
        SELECT * FROM adjuntos_tarea 
        WHERE id_tarea = :id_tarea 
        ORDER BY fecha_subida
    ";
    $stmt_adjuntos = $pdo->prepare($sql_adjuntos);
    $stmt_adjuntos->bindParam(':id_tarea', $id_tarea, PDO::PARAM_INT);
    $stmt_adjuntos->execute();
    $adjuntos = $stmt_adjuntos->fetchAll();
    
    // Uso de array_filter con función de flecha para simplificar
    $adjuntos_iniciales = array_filter($adjuntos, fn($a) => $a['tipo_adjunto'] == 'inicial');
    $adjuntos_finales = array_filter($adjuntos, fn($a) => $a['tipo_adjunto'] == 'final_tecnico');
    

    // --- RESTRICCIÓN DE ACCESO ---
    // Empleado solo puede ver las asignadas a él
    if ($rol_usuario === 'empleado' && $tarea['id_asignado'] != $id_usuario) {
        die("Acceso denegado. Esta tarea no está asignada a usted.");
    }

} catch (PDOException $e) {
    die("Error de BD al cargar la tarea: " . $e->getMessage());
}

// --- LÓGICA DE CONTROL DE ADJUNTO FINAL ---
$adjunto_obligatorio = $tarea['adjunto_final_obligatorio'] == 1;
$adjunto_subido = count($adjuntos_finales) > 0;
// $puede_finalizar ya no se usa, pues la validación se hace en el POST unificado.


// ***************************************************************
// 4. LÓGICA UNIFICADA para FINALIZAR TAREA (EMPLEADO)
//    Maneja la nota, la subida de archivos y el cambio de estado en un solo paso.
// ***************************************************************
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['finalizar_tarea_completo'])) {
    
    $nota_final = trim($_POST['nota_final'] ?? ''); 
    $nuevo_estado = 'finalizada_tecnico';
    $archivos_subidos_count = 0;
    
    if ($tarea['id_asignado'] != $id_usuario) {
         $mensaje = "Solo el técnico asignado puede finalizar la tarea.";
         $alerta_tipo = 'danger';
    } elseif ($tarea['estado'] === 'verificada_admin') {
         $mensaje = "No puede finalizar, la tarea ya fue verificada.";
         $alerta_tipo = 'warning';
    } else {
        try {
            $pdo->beginTransaction();
            $mensaje_final_adj = '';

            // 1. Manejo del Adjunto Final (si es subido)
            if (isset($_FILES['adjunto_final']) && $_FILES['adjunto_final']['error'] === UPLOAD_ERR_OK) {
                
                $directorio_adjuntos = 'uploads/adjuntos/';
                $nombre_archivo = $_FILES['adjunto_final']['name'];
                $extension = pathinfo($nombre_archivo, PATHINFO_EXTENSION); 
                $nombre_guardado = "final_{$id_tarea}_" . time() . "." . $extension;
                $ruta_completa = $directorio_adjuntos . $nombre_guardado;

                if (move_uploaded_file($_FILES['adjunto_final']['tmp_name'], $ruta_completa)) {
                    // Insertar registro del adjunto final
                    $sql_adjunto = "INSERT INTO adjuntos_tarea (id_tarea, nombre_archivo, ruta_archivo, tipo_adjunto, id_usuario_subida) 
                                    VALUES (:id_tarea, :nombre, :ruta, 'final_tecnico', :id_usuario)";
                    $stmt_adjunto = $pdo->prepare($sql_adjunto);
                    $stmt_adjunto->execute([
                        ':id_tarea' => $id_tarea,
                        ':nombre' => $nombre_archivo,
                        ':ruta' => $nombre_guardado,
                        ':id_usuario' => $id_usuario
                    ]);
                    $archivos_subidos_count++;
                    $mensaje_final_adj = "Hoja de ruta subida. ";
                } else {
                    throw new Exception("Error al subir el archivo al servidor.");
                }
            } 
            
            // 2. Validación de adjunto obligatorio (Si es requerido y no se subió en este POST)
            if ($adjunto_obligatorio && $archivos_subidos_count === 0 && count($adjuntos_finales) === 0) {
                 // Si es obligatorio, no se subió AHORA y no había NINGUNO antes.
                 throw new Exception("Adjunto obligatorio no fue seleccionado.");
            }

            // 3. Actualizar Tarea (Estado, Nota Final y Fecha)
            $sql_update = "UPDATE tareas SET 
                                estado = :estado, 
                                nota_final = :nota_final, 
                                fecha_finalizacion = NOW() 
                           WHERE id_tarea = :id AND id_asignado = :id_asignado";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                ':estado' => $nuevo_estado,
                ':nota_final' => $nota_final,
                ':id' => $id_tarea,
                ':id_asignado' => $id_usuario
            ]);
            
            // 4. Notificación al Administrador 
            $id_administrador = $tarea['id_creador']; 
            $nombre_tecnico = $tarea['asignado_nombre']; 
            $titulo_tarea = $tarea['titulo']; 
            
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
            $mensaje = $mensaje_final_adj . "Tarea Finalizada y enviada a verificación.";
            $alerta_tipo = 'success';
            header("Location: tarea_ver.php?id=" . $id_tarea . "&msg=" . urlencode($mensaje) . "&type=success");
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = "Error al finalizar la tarea: " . $e->getMessage();
            $alerta_tipo = 'danger';
            error_log("Error al finalizar tarea unificada: " . $e->getMessage());
        }
    }
}
// ***************************************************************
// FIN LÓGICA UNIFICADA
// ***************************************************************


// 5. Lógica para CAMBIO DE ESTADO (EMPLEADO) - Se mantiene simplificada para volver a 'en_proceso'
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cambiar_estado'])) {
    $nuevo_estado = $_POST['nuevo_estado'];

    // Lógica para que el empleado solo pueda cambiar a 'en_proceso' (especialmente después de una modificación pendiente)
    if ($nuevo_estado !== 'en_proceso') {
        $mensaje = "Estado no válido para esta acción.";
        $alerta_tipo = 'danger';
    } elseif ($tarea['id_asignado'] != $id_usuario) {
        $mensaje = "Solo el técnico asignado puede cambiar el estado.";
        $alerta_tipo = 'danger';
    } else {
        try {
            $sql_update = "UPDATE tareas SET estado = :estado WHERE id_tarea = :id";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->bindParam(':estado', $nuevo_estado);
            $stmt_update->bindParam(':id', $id_tarea, PDO::PARAM_INT);
            $stmt_update->execute();
            
            $mensaje = "Estado de la tarea actualizado a: En Proceso.";
            $alerta_tipo = 'success';
            header("Location: tarea_ver.php?id=" . $id_tarea . "&msg=" . urlencode($mensaje) . "&type=success");
            exit();

        } catch (PDOException $e) {
            $mensaje = "Error al actualizar estado: " . $e->getMessage();
            $alerta_tipo = 'danger';
        }
    }
}

// 6. Lógica para VERIFICACIÓN/SOLICITUD DE MODIFICACIÓN (ADMIN) - Se mantiene sin cambios
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verificar_tarea']) && $rol_usuario === 'admin') {
    $accion = $_POST['verificar_tarea']; // 'verificar' o 'rechazar'
    $comentario = trim($_POST['comentario_admin'] ?? ''); 
    
    $nuevo_estado = ($accion === 'verificar') ? 'verificada_admin' : 'modificacion_pendiente';
    
    if ($tarea['estado'] !== 'finalizada_tecnico' && $tarea['estado'] !== 'modificacion_pendiente') {
        $mensaje = "La tarea debe estar en estado 'Finalizada por Técnico' para ser verificada o solicitada para modificación.";
        $alerta_tipo = 'warning';
    } else {
        try {
            $comentario_final = empty($comentario) ? NULL : $comentario; 

            $sql_update = "UPDATE tareas SET estado = :estado, comentario_admin = :comentario WHERE id_tarea = :id";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->bindParam(':estado', $nuevo_estado);
            $stmt_update->bindParam(':comentario', $comentario_final); 
            $stmt_update->bindParam(':id', $id_tarea, PDO::PARAM_INT);
            $stmt_update->execute();
            
            $mensaje = ($accion === 'verificar') ? "Tarea Verificada y Aprobada." : "Modificación solicitada al técnico.";
            $alerta_tipo = ($accion === 'verificar') ? 'success' : 'warning';
            
            // LÓGICA DE NOTIFICACIÓN DE MODIFICACIÓN 
            if ($accion === 'rechazar') {
                 $msg_notif = "El Administrador ha solicitado una modificación en Tarea #{$id_tarea}.";
                 $url_notif = "tarea_ver.php?id=" . $id_tarea;
                 
                 $sql_notif = "INSERT INTO notificaciones (id_usuario_destino, id_tarea_origen, tipo, mensaje, url) 
                               VALUES (:id_destino, :id_tarea, 'modificacion_admin', :mensaje, :url)";
                 $stmt_notif = $pdo->prepare($sql_notif);
                 $stmt_notif->execute([
                     ':id_destino' => $tarea['id_asignado'], 
                     ':id_tarea' => $id_tarea,
                     ':mensaje' => $msg_notif,
                     ':url' => $url_notif
                 ]);
            }
            
            header("Location: tarea_ver.php?id=" . $id_tarea . "&msg=" . urlencode($mensaje) . "&type=" . $alerta_tipo);
            exit();

        } catch (PDOException $e) {
            $mensaje = "Error al verificar/solicitar modificación: " . $e->getMessage();
            $alerta_tipo = 'danger';
        }
    }
}


// 7. Mensajes de Redirección (para que se muestren después del header location)
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $mensaje = urldecode($_GET['msg']);
    $alerta_tipo = $_GET['type'];
}


// Funciones de ayuda 
function getPrioridadClass($prioridad) {
    switch ($prioridad) {
        case 'urgente': return 'bg-danger text-white';
        case 'alta': return 'bg-warning text-dark';
        case 'media': return 'bg-info text-white';
        case 'baja': return 'bg-success text-white';
        default: return 'bg-secondary text-white';
    }
}
function getEstadoClass($estado) {
    switch ($estado) {
        case 'pendiente': return 'badge bg-danger';
        case 'en_proceso': return 'badge bg-primary';
        case 'finalizada_tecnico': return 'badge bg-info text-white'; 
        case 'verificada_admin': return 'badge bg-success';
        case 'modificacion_pendiente': return 'badge bg-warning text-dark'; 
        default: return 'badge bg-secondary';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tarea #<?php echo htmlspecialchars($id_tarea); ?> - Gestión Logística</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .card-descripcion {
            border-left: 5px solid var(--bs-primary);
            padding-left: 15px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h1 class="mb-4">Tarea #<?php echo htmlspecialchars($id_tarea); ?>: <?php echo htmlspecialchars($tarea['titulo']); ?></h1>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $alerta_tipo; ?>" role="alert">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <i class="fas fa-info-circle"></i> Información General
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Categoría:</strong> <span class="badge bg-secondary"><?php echo htmlspecialchars($tarea['categoria_nombre']); ?></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Prioridad:</strong> 
                                <span class="badge <?php echo getPrioridadClass($tarea['prioridad']); ?>">
                                    <?php echo ucfirst($tarea['prioridad']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="row mb-3">
                             <div class="col-md-6">
                                <strong>Creador:</strong> <?php echo htmlspecialchars($tarea['creador_nombre']); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Asignado a:</strong> <?php echo htmlspecialchars($tarea['asignado_nombre'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                             <div class="col-md-6">
                                <strong>Fecha Creación:</strong> <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_creacion'])); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Fecha Límite:</strong> <?php echo $tarea['fecha_limite'] ? date('d/m/Y', strtotime($tarea['fecha_limite'])) : 'N/A'; ?>
                            </div>
                        </div>
                        <hr>
                        <h5>Descripción Detallada:</h5>
                        <div class="card-descripcion">
                             <?php echo $tarea['descripcion']; ?>
                        </div>
                        
                        <?php if (!empty($tarea['nota_final'])): ?>
                            <h5 class="mt-4">Nota de Finalización:</h5>
                            <div class="alert alert-light border border-secondary p-3">
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($tarea['nota_final'])); ?></p>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <i class="fas fa-paperclip"></i> Adjuntos Iniciales (Solicitud)
                    </div>
                    <ul class="list-group list-group-flush">
                        <?php if (count($adjuntos_iniciales) > 0): ?>
                            <?php foreach ($adjuntos_iniciales as $adj): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <i class="fas fa-file me-2"></i> <?php echo htmlspecialchars($adj['nombre_archivo']); ?>
                                    <a href="descargar_adjunto.php?id=<?php echo htmlspecialchars($adj['id_adjunto']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-download"></i> Ver/Descargar
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="list-group-item text-muted">No se adjuntaron archivos con la solicitud inicial.</li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                 <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <i class="fas fa-file-signature"></i> Adjuntos Finales (Hoja de Ruta/Comprobante)
                    </div>
                    <ul class="list-group list-group-flush">
                         <?php if (count($adjuntos_finales) > 0): ?>
                            <?php foreach ($adjuntos_finales as $adj): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <i class="fas fa-file-pdf me-2"></i> <?php echo htmlspecialchars($adj['nombre_archivo']); ?> (Subido: <?php echo date('d/m/Y', strtotime($adj['fecha_subida'])); ?>)
                                    <a href="descargar_adjunto.php?id=<?php echo htmlspecialchars($adj['id_adjunto']); ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-download"></i> Ver/Descargar
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="list-group-item text-muted">Aún no hay comprobantes de trabajo finalizado.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4 text-center">
                    <div class="card-header bg-secondary text-white">
                        Estado Actual
                    </div>
                    <div class="card-body">
                        <h2>
                            <span class="<?php echo getEstadoClass($tarea['estado']); ?> p-2">
                                <?php echo ucfirst(str_replace('_', ' ', $tarea['estado'])); ?>
                            </span>
                        </h2>
                        
                        <?php 
                        if (!empty($tarea['comentario_admin'])):
                            $alert_class = ($tarea['estado'] === 'modificacion_pendiente') ? 'alert-danger' : 'alert-info';
                            $comment_title = ($tarea['estado'] === 'modificacion_pendiente') ? 'Modificación Solicitada:' : 'Comentario del Administrador:';
                        ?>
                            <div class="alert <?php echo $alert_class; ?> mt-3 small">
                                <strong><?php echo $comment_title; ?></strong>
                                <p class="mb-0"><?php echo htmlspecialchars($tarea['comentario_admin']); ?></p>
                            </div>
                        <?php endif; ?>
                        <hr>
                        <p class="mb-0 small">Adjunto Final Requerido: 
                            <?php if ($adjunto_obligatorio): ?>
                                <span class="badge bg-danger">OBLIGATORIO</span>
                            <?php else: ?>
                                <span class="badge bg-success">OPCIONAL</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <?php if ($rol_usuario === 'empleado' && $tarea['id_asignado'] == $id_usuario): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-tools"></i> Acciones del Técnico
                        </div>
                        <div class="card-body">
                            
                            <?php if ($tarea['estado'] !== 'verificada_admin'): ?>
                                
                                <h6>Finalizar Tarea (Único Paso)</h6>

                                <form method="POST" action="tarea_ver.php?id=<?php echo $id_tarea; ?>" enctype="multipart/form-data" class="mb-4">
                                    
                                    <div class="mb-3">
                                        <label for="nota_final" class="form-label small">Nota de Finalización (Opcional)</label>
                                        <textarea class="form-control" id="nota_final" name="nota_final" rows="2" placeholder="Detalle observaciones o resultados..."><?php echo htmlspecialchars($tarea['nota_final'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="mb-3">
                                        <label for="adjunto_final" class="form-label small">Adjuntar Hoja de Ruta/Comprobante</label>
                                        <input class="form-control" type="file" id="adjunto_final" name="adjunto_final" 
                                               <?php echo $adjunto_obligatorio && count($adjuntos_finales) === 0 ? 'required' : ''; ?>>
                                        <small class="text-muted">
                                            <?php if ($adjunto_obligatorio): ?>
                                                <i class="fas fa-exclamation-triangle text-danger"></i> Archivo OBLIGATORIO
                                                <?php echo count($adjuntos_finales) > 0 ? '(Ya hay un adjunto subido)' : '(Necesario para finalizar)'; ?>
                                            <?php else: ?>
                                                Archivo Opcional.
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    
                                    <button type="submit" name="finalizar_tarea_completo" class="btn btn-success w-100">
                                        <i class="fas fa-check"></i> Finalizar y Enviar a Verificación
                                    </button>
                                </form>
                                <?php if ($tarea['estado'] === 'modificacion_pendiente'): ?>
                                    <hr>
                                    <p class="small text-danger">El Administrador solicitó modificaciones.</p>
                                    <form method="POST" action="tarea_ver.php?id=<?php echo $id_tarea; ?>">
                                        <input type="hidden" name="nuevo_estado" value="en_proceso">
                                        <button 
                                            type="submit" 
                                            name="cambiar_estado" 
                                            class="btn btn-warning w-100 btn-sm"
                                        >Marcar como 'En Proceso' (para modificar)</button>
                                    </form>
                                <?php endif; ?>

                            <?php else: ?>
                                <div class="alert alert-success text-center">Proceso finalizado.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($rol_usuario === 'admin' && ($tarea['estado'] === 'finalizada_tecnico' || $tarea['estado'] === 'modificacion_pendiente')): ?>
                    <div class="card mb-4 border-warning">
                        <div class="card-header bg-warning text-dark">
                            <i class="fas fa-user-shield"></i> Verificación del Administrador
                        </div>
                        <div class="card-body">
                            <p class="small text-muted">El técnico ha finalizado/re-enviado la tarea y espera su verificación.</p>
                            <form method="POST" action="tarea_ver.php?id=<?php echo $id_tarea; ?>">
                                <div class="mb-3">
                                    <label for="comentario_admin" class="form-label">Comentarios</label> 
                                    <textarea class="form-control" id="comentario_admin" name="comentario_admin" rows="2"><?php echo htmlspecialchars($tarea['comentario_admin'] ?? ''); ?></textarea>
                                    <small class="text-muted">Guarda el comentario para el registro, sea que apruebe o solicite modificación.</small>
                                </div>
                                <button type="submit" name="verificar_tarea" value="verificar" class="btn btn-success w-100 mb-2">
                                    <i class="fas fa-check"></i> Dar Visto Bueno
                                </button>
                                <button type="submit" name="verificar_tarea" value="rechazar" class="btn btn-danger w-100">
                                    <i class="fas fa-undo"></i> Solicitar Más Modificación
                                </button>
                            </form>
                        </div>
                    </div>
                <?php elseif ($rol_usuario === 'admin' && ($tarea['estado'] === 'verificada_admin')): ?>
                     <div class="alert alert-secondary text-center">
                        Tarea con proceso finalizado.
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer" style="z-index: 1080;">
</div>
</body>
</html>