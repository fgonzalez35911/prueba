<?php
session_start();
include 'conexion.php';

// Proteger la página (solo Admin)
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$mensaje = '';
$alerta_tipo = '';

// Lógica para CREAR/EDITAR/ELIMINAR Categoría
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // CREAR CATEGORÍA
    if (isset($_POST['crear_categoria'])) {
        $nombre = trim($_POST['nombre']);
        $descripcion = trim($_POST['descripcion']);

        try {
            $sql = "INSERT INTO categorias (nombre, descripcion) VALUES (:nombre, :descripcion)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->execute();
            $mensaje = "Categoría '{$nombre}' creada exitosamente.";
            $alerta_tipo = 'success';
        } catch (PDOException $e) {
            $mensaje = "Error al crear la categoría. Podría ya existir o ser un problema de BD.";
            $alerta_tipo = 'danger';
        }
    }

    // ELIMINAR CATEGORÍA
    if (isset($_POST['eliminar_categoria'])) {
        $id_categoria = $_POST['id_categoria'];
        try {
            // Intentamos eliminar
            $sql = "DELETE FROM categorias WHERE id_categoria = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $id_categoria, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                 $mensaje = "Categoría eliminada exitosamente.";
                 $alerta_tipo = 'success';
            } else {
                 $mensaje = "La categoría no existe o no se pudo eliminar.";
                 $alerta_tipo = 'warning';
            }
        } catch (PDOException $e) {
            // Error si hay tareas asociadas (FOREIGN KEY RESTRICT)
            $mensaje = "No se puede eliminar la categoría porque hay tareas asignadas a ella. Modifícalas primero.";
            $alerta_tipo = 'danger';
        }
    }
}

// Lógica para OBTENER LISTA DE CATEGORÍAS
try {
    $sql_list = "SELECT id_categoria, nombre, descripcion FROM categorias ORDER BY nombre";
    $stmt_list = $pdo->query($sql_list);
    $categorias = $stmt_list->fetchAll();
} catch (PDOException $e) {
    $categorias = [];
    $mensaje = "Error al cargar la lista de categorías: " . $e->getMessage();
    $alerta_tipo = 'warning';
}

// Contenido HTML/Bootstrap
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Categorías - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .profile-img { /* Estilos de dashboard.php */
            width: 40px; height: 40px; border-radius: 50%; object-fit: cover; margin-right: 10px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; // Lo crearemos abajo para no repetir código ?>

    <div class="container mt-4">
        <h1 class="mb-4"><i class="fas fa-tags"></i> Gestión de Categorías</h1>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $alerta_tipo; ?>" role="alert">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <i class="fas fa-plus"></i> Crear Nueva Categoría
            </div>
            <div class="card-body">
                <form method="POST" action="admin_categorias.php">
                    <input type="hidden" name="crear_categoria" value="1">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label for="nombre" class="form-label">Nombre de Categoría</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="col-md-5">
                            <label for="descripcion" class="form-label">Descripción (Opcional)</label>
                            <input type="text" class="form-control" id="descripcion" name="descripcion">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">Guardar Categoría</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <h3 class="mt-5">Listado de Categorías</h3>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($categorias) > 0): ?>
                        <?php foreach ($categorias as $cat): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cat['id_categoria']); ?></td>
                            <td><?php echo htmlspecialchars($cat['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($cat['descripcion']); ?></td>
                            <td>
                                <form method="POST" action="admin_categorias.php" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar esta categoría? Si tiene tareas asignadas, la acción fallará.');">
                                    <input type="hidden" name="eliminar_categoria" value="1">
                                    <input type="hidden" name="id_categoria" value="<?php echo $cat['id_categoria']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Eliminar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">No hay categorías registradas.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer" style="z-index: 1080;">
</div>
</body>
</html>