<?php
session_start();
include 'conexion.php';

// 1. Proteger la página (solo Admin)
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$mensaje = '';
$alerta_tipo = '';

// 2. Lógica para CREAR NUEVO USUARIO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['crear_usuario'])) {
    $nombre_completo = trim($_POST['nombre_completo']);
    $usuario = trim($_POST['usuario']);
    $password = $_POST['password'];
    $rol = 'empleado'; // Los admins solo pueden crear empleados desde aquí.
    
    // Hash de la contraseña (ESENCIAL para seguridad)
    $password_hashed = password_hash($password, PASSWORD_DEFAULT);

    try {
        $sql = "INSERT INTO usuarios (nombre_completo, usuario, password, rol) VALUES (:nombre_completo, :usuario, :password_hashed, :rol)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':nombre_completo', $nombre_completo);
        $stmt->bindParam(':usuario', $usuario);
        $stmt->bindParam(':password_hashed', $password_hashed);
        $stmt->bindParam(':rol', $rol);

        if ($stmt->execute()) {
            $mensaje = "El usuario '$usuario' ha sido creado exitosamente.";
            $alerta_tipo = 'success';
        } else {
            $mensaje = "Error al crear el usuario. El nombre de usuario podría ya existir.";
            $alerta_tipo = 'danger';
        }

    } catch (PDOException $e) {
        $mensaje = "Error de BD: " . $e->getMessage();
        $alerta_tipo = 'danger';
    }
}

// 3. Lógica para OBTENER LISTA DE USUARIOS (solo empleados)
try {
    $sql_list = "SELECT id_usuario, nombre_completo, usuario, rol, email, telefono FROM usuarios WHERE rol = 'empleado' ORDER BY nombre_completo";
    $stmt_list = $pdo->query($sql_list);
    $usuarios_empleados = $stmt_list->fetchAll();
} catch (PDOException $e) {
    $usuarios_empleados = [];
    $mensaje = "Error al cargar la lista de usuarios: " . $e->getMessage();
    $alerta_tipo = 'warning';
}

// Contenido HTML/Bootstrap
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .profile-img { /* Estilos de dashboard.php */
            width: 40px; height: 40px; border-radius: 50%; object-fit: cover; margin-right: 10px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h1 class="mb-4"><i class="fas fa-users"></i> Gestión de Usuarios Empleados</h1>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $alerta_tipo; ?>" role="alert">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                Crear Nuevo Empleado
            </div>
            <div class="card-body">
                <form method="POST" action="admin_usuarios.php">
                    <input type="hidden" name="crear_usuario" value="1">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="nombre_completo" class="form-label">Nombre Completo</label>
                            <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" required>
                        </div>
                        <div class="col-md-4">
                            <label for="usuario" class="form-label">Usuario (Login)</label>
                            <input type="text" class="form-control" id="usuario" name="usuario" required>
                        </div>
                        <div class="col-md-4">
                            <label for="password" class="form-label">Contraseña Inicial</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-success"><i class="fas fa-plus-circle"></i> Crear Empleado</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <h3 class="mt-5">Listado de Empleados/Técnicos</h3>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Nombre Completo</th>
                        <th>Usuario (Login)</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($usuarios_empleados) > 0): ?>
                        <?php foreach ($usuarios_empleados as $usuario): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($usuario['id_usuario']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['nombre_completo']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['usuario']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['email'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($usuario['telefono'] ?? 'N/A'); ?></td>
                            <td>
                                <button class="btn btn-sm btn-info disabled" title="Funcionalidad en desarrollo"><i class="fas fa-edit"></i> Editar</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No hay empleados registrados todavía.</td>
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