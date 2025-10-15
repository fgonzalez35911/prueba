<?php
session_start();
include 'conexion.php';

// 1. Proteger la página (solo Admin puede crear tareas)
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$id_creador = $_SESSION['usuario_id'];
$mensaje = '';
$alerta_tipo = '';

// Variables para llenar el formulario si hay un error
$titulo = '';
$descripcion = '';
$fecha_limite = '';
$prioridad = '';
$id_asignado_seleccionado = '';
$id_categoria_seleccionada = '';
// Nueva variable para el control
$adjunto_obligatorio_seleccionado = 1; // Default a Obligatorio (1)


// 2. Cargar listados necesarios (Categorías y Usuarios/Empleados)
try {
    // Categorías
    $stmt_cats = $pdo->query("SELECT id_categoria, nombre FROM categorias ORDER BY nombre");
    $categorias = $stmt_cats->fetchAll();
    
    // Usuarios (solo empleados, ya que el admin no se asigna tareas)
    $stmt_users = $pdo->query("SELECT id_usuario, nombre_completo FROM usuarios WHERE rol = 'empleado' ORDER BY nombre_completo");
    $empleados = $stmt_users->fetchAll();
    
} catch (PDOException $e) {
    die("Error al cargar datos de apoyo: " . $e->getMessage());
}


// 3. Lógica para la CREACIÓN de la Tarea (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['crear_tarea'])) {
    
    // a. Capturar y sanear datos
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = $_POST['descripcion'] ?? ''; // La descripción ya viene saneada por TinyMCE
    $id_categoria_seleccionada = $_POST['id_categoria'] ?? null;
    $id_asignado_seleccionado = $_POST['id_asignado'] ?? null;
    $prioridad = $_POST['prioridad'] ?? 'media';
    $fecha_limite = trim($_POST['fecha_limite'] ?? null); // Puede ser null
    $estado_inicial = 'pendiente';
    // -> NUEVA VARIABLE
    $adjunto_obligatorio_seleccionado = $_POST['adjunto_final_obligatorio'] ?? 0;

    // b. Validaciones básicas
    if (empty($titulo) || empty($descripcion) || empty($id_categoria_seleccionada) || empty($id_asignado_seleccionado)) {
        $mensaje = "Todos los campos obligatorios (Título, Descripción, Categoría, Asignado a) deben ser llenados.";
        $alerta_tipo = 'danger';
    } else {
        
        // c. Iniciar la transacción para asegurar que la tarea y adjuntos se guarden
        $pdo->beginTransaction();
        
        try {
            // i. Insertar la Tarea
            // -> SQL ACTUALIZADO CON adjunto_final_obligatorio
            $sql_tarea = "INSERT INTO tareas 
                (id_creador, id_asignado, id_categoria, titulo, descripcion, prioridad, fecha_limite, estado, adjunto_final_obligatorio) 
                VALUES (:id_creador, :id_asignado, :id_categoria, :titulo, :descripcion, :prioridad, :fecha_limite, :estado, :adjunto_obligatorio)";
            
            $stmt_tarea = $pdo->prepare($sql_tarea);
            $stmt_tarea->execute([
                ':id_creador' => $id_creador,
                ':id_asignado' => $id_asignado_seleccionado,
                ':id_categoria' => $id_categoria_seleccionada,
                ':titulo' => $titulo,
                ':descripcion' => $descripcion,
                ':prioridad' => $prioridad,
                ':fecha_limite' => $fecha_limite ?: null, // Usar null si está vacío
                ':estado' => $estado_inicial,
                // -> NUEVO BINDING
                ':adjunto_obligatorio' => $adjunto_obligatorio_seleccionado 
            ]);
            
            $id_tarea = $pdo->lastInsertId();

            // ii. Procesar Adjuntos Iniciales (Archivos)
            if (isset($_FILES['adjuntos']) && count($_FILES['adjuntos']['name']) > 0) {
                
                $directorio_adjuntos = 'uploads/adjuntos/';
                
                // Asegurar que el directorio exista (si no lo creamos en pasos anteriores)
                if (!is_dir($directorio_adjuntos)) {
                    mkdir($directorio_adjuntos, 0777, true);
                }

                // Recorrer cada archivo subido
                foreach ($_FILES['adjuntos']['name'] as $key => $nombre_archivo) {
                    if ($_FILES['adjuntos']['error'][$key] === UPLOAD_ERR_OK) {
                        
                        $tmp_name = $_FILES['adjuntos']['tmp_name'][$key];
                        $extension = pathinfo($nombre_archivo, PATHINFO_EXTENSION);
                        
                        // Generar nombre seguro: inicial_[id_tarea]_[timestamp].[ext]
                        $nombre_guardado = "inicial_{$id_tarea}_" . time() . "_{$key}." . $extension;
                        $ruta_completa = $directorio_adjuntos . $nombre_guardado;

                        if (move_uploaded_file($tmp_name, $ruta_completa)) {
                            // Insertar registro del adjunto en la BD
                            $sql_adjunto = "INSERT INTO adjuntos_tarea 
                                (id_tarea, nombre_archivo, ruta_archivo, tipo_adjunto, id_usuario_subida) 
                                VALUES (:id_tarea, :nombre, :ruta, 'inicial', :id_usuario)";
                            
                            $stmt_adjunto = $pdo->prepare($sql_adjunto);
                            $stmt_adjunto->execute([
                                ':id_tarea' => $id_tarea,
                                ':nombre' => $nombre_archivo,
                                ':ruta' => $nombre_guardado,
                                ':id_usuario' => $id_creador
                            ]);
                        }
                    }
                }
            }
            
            // iii. ** LÓGICA DE NOTIFICACIÓN NUEVA TAREA **
            $msg_notif = "¡Nueva tarea asignada! Tarea #{$id_tarea}: " . $titulo;
            $url_notif = "tarea_ver.php?id=" . $id_tarea;
            
            $sql_notif = "INSERT INTO notificaciones (id_usuario_destino, id_tarea_origen, tipo, mensaje, url) 
                          VALUES (:id_destino, :id_tarea, 'nueva_tarea', :mensaje, :url)";
            $stmt_notif = $pdo->prepare($sql_notif);
            $stmt_notif->execute([
                ':id_destino' => $id_asignado_seleccionado, // El técnico
                ':id_tarea' => $id_tarea,
                ':mensaje' => $msg_notif,
                ':url' => $url_notif
            ]);
            // ** FIN LÓGICA DE NOTIFICACIÓN **


            // iv. Finalizar la transacción
            $pdo->commit();
            
            $mensaje = "Tarea '$titulo' creada y asignada exitosamente (ID: {$id_tarea}).";
            $alerta_tipo = 'success';
            
            // Limpiar variables para un nuevo formulario
            $titulo = $descripcion = $fecha_limite = $id_asignado_seleccionado = $id_categoria_seleccionada = '';
            // Resetear la selección del nuevo campo
            $adjunto_obligatorio_seleccionado = 1;

            // Opcional: Redirigir a la vista de la tarea creada
            header("Location: tarea_ver.php?id=" . $id_tarea . "&msg=" . urlencode($mensaje) . "&type=success");
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $mensaje = "Error de BD al crear la tarea: " . $e->getMessage();
            $alerta_tipo = 'danger';
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = "Error al mover los archivos: " . $e->getMessage();
            $alerta_tipo = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Nueva Tarea - Gestión Logística</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js" integrity="sha512-4JkZ4C4V+J/mIq1tIq+r7tNnJjI2zB/tF0X5wz3I8D/Tj8yCq9XwD/Q+T8w/w1P+Vw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
      tinymce.init({
        selector: '#descripcion',
        plugins: 'advlist autolink lists link image charmap print preview anchor',
        toolbar_mode: 'floating',
        height: 300,
        content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }'
      });
    </script>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h1 class="mb-4"><i class="fas fa-plus-circle"></i> Crear Nueva Tarea</h1>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $alerta_tipo; ?>" role="alert">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="tarea_crear.php" enctype="multipart/form-data">
            <input type="hidden" name="crear_tarea" value="1">
            
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    Detalles de la Tarea
                </div>
                <div class="card-body">
                    
                    <div class="mb-3">
                        <label for="titulo" class="form-label">Título <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="titulo" name="titulo" value="<?php echo htmlspecialchars($titulo); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción Detallada <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="descripcion" name="descripcion"><?php echo $descripcion; ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="id_categoria" class="form-label">Categoría <span class="text-danger">*</span></label>
                            <select class="form-select" id="id_categoria" name="id_categoria" required>
                                <option value="" disabled selected>Seleccione una categoría</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id_categoria']; ?>" 
                                            <?php echo ($id_categoria_seleccionada == $cat['id_categoria']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="prioridad" class="form-label">Prioridad</label>
                            <select class="form-select" id="prioridad" name="prioridad" required>
                                <option value="media" <?php echo ($prioridad === 'media') ? 'selected' : ''; ?>>Media</option>
                                <option value="baja" <?php echo ($prioridad === 'baja') ? 'selected' : ''; ?>>Baja</option>
                                <option value="alta" <?php echo ($prioridad === 'alta') ? 'selected' : ''; ?>>Alta</option>
                                <option value="urgente" <?php echo ($prioridad === 'urgente') ? 'selected' : ''; ?>>Urgente</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    Asignación y Plazos
                </div>
                <div class="card-body">
                     <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="id_asignado" class="form-label">Asignar a Técnico <span class="text-danger">*</span></label>
                            <select class="form-select" id="id_asignado" name="id_asignado" required>
                                <option value="" disabled selected>Seleccione un empleado/técnico</option>
                                <?php foreach ($empleados as $emp): ?>
                                    <option value="<?php echo $emp['id_usuario']; ?>" 
                                            <?php echo ($id_asignado_seleccionado == $emp['id_usuario']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['nombre_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_limite" class="form-label">Fecha Límite (Opcional)</label>
                            <input type="date" class="form-control" id="fecha_limite" name="fecha_limite" value="<?php echo htmlspecialchars($fecha_limite); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    Requisito de Cierre (Hoja de Ruta)
                </div>
                <div class="card-body">
                     <label class="form-label">¿El Técnico Debe Adjuntar la Hoja de Ruta Final?</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="adjunto_final_obligatorio" id="adjunto_obligatorio_si" value="1" <?php echo ($adjunto_obligatorio_seleccionado == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="adjunto_obligatorio_si">
                            <i class="fas fa-file-pdf"></i> **Sí, Obligatorio.** (Para tareas con comprobante de firma)
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="adjunto_final_obligatorio" id="adjunto_obligatorio_no" value="0" <?php echo ($adjunto_obligatorio_seleccionado == 0) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="adjunto_obligatorio_no">
                            <i class="fas fa-times-circle"></i> **No, Opcional.** (Para tareas urgentes o diarias)
                        </label>
                    </div>
                    <small class="text-muted mt-2 d-block">Esta configuración obligará o no al técnico a subir un adjunto final antes de dar por terminada la tarea y enviarla a verificación.</small>
                </div>
            </div>
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    Adjuntar Documentos Iniciales (Opcional)
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="adjuntos" class="form-label">Seleccionar Archivos (Múltiple)</label>
                        <input class="form-control" type="file" id="adjuntos" name="adjuntos[]" multiple>
                        <small class="text-muted">PDF, imágenes, documentos. El tamaño máximo de subida está limitado por la configuración de PHP.</small>
                    </div>
                </div>
            </div>


            <div class="text-center mb-5">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-paper-plane"></i> Crear y Asignar Tarea
                </button>
            </div>
        </form>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>