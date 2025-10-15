<?php
// Archivo: tarea_editar.php
session_start();
include 'conexion.php';

// 1. Proteger la página (solo Admin puede editar tareas)
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$id_creador = $_SESSION['usuario_id'];
$id_tarea = (int)($_GET['id'] ?? 0); 
$mensaje = '';
$alerta_tipo = '';

// Si no hay ID de tarea válido, redirigir
if ($id_tarea <= 0) {
    header("Location: tareas_lista.php");
    exit();
}

// Variables para llenar el formulario
$titulo = '';
$descripcion = '';
$fecha_limite = '';
$prioridad = '';
$id_asignado_seleccionado = '';
$id_categoria_seleccionada = '';
$adjunto_obligatorio_seleccionado = 1; // Default a Obligatorio (1)

// 2. Manejar el POST (Guardar Cambios)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 2.1 Capturar y sanear los datos del formulario
    $titulo_nuevo = trim($_POST['titulo'] ?? '');
    $descripcion_nueva = trim($_POST['descripcion'] ?? '');
    $id_asignado_nuevo = (int)($_POST['id_asignado'] ?? 0);
    $id_categoria_nueva = (int)($_POST['id_categoria'] ?? 0);
    $fecha_limite_nueva = $_POST['fecha_limite'] ?? '';
    $prioridad_nueva = $_POST['prioridad'] ?? '';
    // Aseguramos que sea 0 o 1
    $adjunto_obligatorio_nuevo = (int)($_POST['adjunto_obligatorio'] ?? 0); 

    // 2.2 Validación básica
    if (empty($titulo_nuevo) || empty($descripcion_nueva) || $id_asignado_nuevo <= 0 || $id_categoria_nueva <= 0 || empty($fecha_limite_nueva)) {
        $mensaje = "Error: Faltan campos obligatorios para guardar la tarea.";
        $alerta_tipo = 'danger';
    } else {
        try {
            // 2.3 Construir la consulta de ACTUALIZACIÓN (UPDATE)
            $sql_update = "UPDATE tareas SET
                                titulo = :titulo,
                                descripcion = :descripcion,
                                id_asignado = :id_asignado,
                                id_categoria = :id_categoria,
                                fecha_limite = :fecha_limite,
                                prioridad = :prioridad,
                                adjunto_obligatorio = :adjunto_obligatorio
                           WHERE id_tarea = :id_tarea";
            
            $stmt_update = $pdo->prepare($sql_update);
            
            // 2.4 Ejecutar la actualización
            $stmt_update->execute([
                ':titulo' => $titulo_nuevo,
                ':descripcion' => $descripcion_nueva,
                ':id_asignado' => $id_asignado_nuevo,
                ':id_categoria' => $id_categoria_nueva,
                ':fecha_limite' => $fecha_limite_nueva,
                ':prioridad' => $prioridad_nueva,
                ':adjunto_obligatorio' => $adjunto_obligatorio_nuevo,
                ':id_tarea' => $id_tarea // ID de la tarea a actualizar
            ]);

            // 2.5 Si la actualización fue exitosa
            $mensaje = "¡Tarea #{$id_tarea} actualizada con éxito!";
            $alerta_tipo = 'success';
            
            // Reasignar los valores para que se muestren los datos actualizados en el formulario
            $titulo = $titulo_nuevo;
            $descripcion = $descripcion_nueva;
            $id_asignado_seleccionado = $id_asignado_nuevo;
            $id_categoria_seleccionada = $id_categoria_nueva;
            $fecha_limite = $fecha_limite_nueva;
            $prioridad = $prioridad_nueva;
            $adjunto_obligatorio_seleccionado = $adjunto_obligatorio_nuevo;

        } catch (PDOException $e) {
            $mensaje = "Error de base de datos al actualizar la tarea: " . $e->getMessage();
            $alerta_tipo = 'danger';
            error_log($mensaje);
        }
    }
}


// 3. Cargar listados necesarios y datos de la tarea (Se ejecuta siempre, incluso después del POST, para rellenar)
try {
    // Categorías
    $stmt_cats = $pdo->query("SELECT id_categoria, nombre FROM categorias ORDER BY nombre");
    $categorias = $stmt_cats->fetchAll();
    
    // Usuarios (solo empleados)
    $stmt_users = $pdo->query("SELECT id_usuario, nombre_completo FROM usuarios WHERE rol = 'empleado' ORDER BY nombre_completo");
    $empleados = $stmt_users->fetchAll();
    
    // Si no es un POST, o si el POST falló, cargamos los datos originales/existentes
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $alerta_tipo === 'danger') {
        $sql_tarea = "SELECT * FROM tareas WHERE id_tarea = :id_tarea";
        $stmt_tarea = $pdo->prepare($sql_tarea);
        $stmt_tarea->execute([':id_tarea' => $id_tarea]);
        $tarea = $stmt_tarea->fetch(PDO::FETCH_ASSOC);

        if (!$tarea) {
            header("Location: tareas_lista.php");
            exit();
        }
        
        // Rellenar variables con datos de la tarea
        $titulo = $tarea['titulo'];
        $descripcion = $tarea['descripcion'];
        $fecha_limite = $tarea['fecha_limite'];
        $prioridad = $tarea['prioridad'];
        $id_asignado_seleccionado = $tarea['id_asignado'];
        $id_categoria_seleccionada = $tarea['id_categoria'];
        
        // CORREGIDO: Usa el operador de coalescencia nula (??) para evitar la advertencia en tareas antiguas
        $adjunto_obligatorio_seleccionado = $tarea['adjunto_obligatorio'] ?? 1; 
    }

} catch (PDOException $e) {
    die("Error al cargar datos de apoyo o tarea: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Tarea #<?php echo htmlspecialchars($id_tarea); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Editar Tarea <span class="badge bg-secondary">#<?php echo htmlspecialchars($id_tarea); ?></span></h1>
            <a href="tareas_lista.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Volver a la Lista
            </a>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $alerta_tipo; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="tarea_editar.php?id=<?php echo htmlspecialchars($id_tarea); ?>" method="POST" enctype="multipart/form-data">
            
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    Datos Principales de la Tarea
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="titulo" class="form-label">Título de la Tarea</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" value="<?php echo htmlspecialchars($titulo); ?>" required maxlength="100">
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción Detallada</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="4" required><?php echo htmlspecialchars($descripcion); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="id_asignado" class="form-label">Asignar a Empleado</label>
                            <select class="form-select" id="id_asignado" name="id_asignado" required>
                                <option value="">Seleccione un empleado</option>
                                <?php foreach ($empleados as $empleado): ?>
                                    <option value="<?php echo $empleado['id_usuario']; ?>" 
                                            <?php echo ($id_asignado_seleccionado == $empleado['id_usuario']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($empleado['nombre_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="id_categoria" class="form-label">Categoría</label>
                            <select class="form-select" id="id_categoria" name="id_categoria" required>
                                <option value="">Seleccione una categoría</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['id_categoria']; ?>"
                                            <?php echo ($id_categoria_seleccionada == $categoria['id_categoria']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($categoria['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fecha_limite" class="form-label">Fecha Límite</label>
                            <input type="date" class="form-control" id="fecha_limite" name="fecha_limite" value="<?php echo htmlspecialchars(substr($fecha_limite, 0, 10)); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="prioridad" class="form-label">Prioridad</label>
                            <select class="form-select" id="prioridad" name="prioridad" required>
                                <?php 
                                $prioridades = ['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'critica' => 'Crítica'];
                                foreach ($prioridades as $val => $label): ?>
                                    <option value="<?php echo $val; ?>" <?php echo ($prioridad == $val) ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    Configuración de Finalización
                </div>
                <div class="card-body">
                     <label class="form-label d-block">¿Adjunto Final Obligatorio?</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="adjunto_obligatorio" id="adjunto_obligatorio_si" value="1" 
                               <?php echo ($adjunto_obligatorio_seleccionado == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="adjunto_obligatorio_si">
                            <i class="fas fa-check-circle text-success"></i> **Sí, Obligatorio.**
                        </label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="adjunto_obligatorio" id="adjunto_obligatorio_no" value="0" 
                               <?php echo ($adjunto_obligatorio_seleccionado == 0) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="adjunto_obligatorio_no">
                            <i class="fas fa-times-circle text-danger"></i> **No, Opcional.**
                        </label>
                    </div>
                    <small class="text-muted mt-2 d-block">Esta configuración obligará o no al técnico a subir un adjunto final antes de dar por terminada la tarea y enviarla a verificación.</small>
                </div>
            </div>
            
            <div class="text-center mb-5">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </div>
        </form>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>