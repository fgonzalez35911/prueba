<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$id_usuario = $_SESSION['usuario_id'];
$mensaje = '';
$alerta_tipo = '';
$usuario_data = false; // Inicializar a false para la verificación

// 1. Obtener datos del usuario actual
try {
    $sql = "SELECT nombre_completo, usuario, email, telefono, foto_perfil FROM usuarios WHERE id_usuario = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id_usuario, PDO::PARAM_INT);
    $stmt->execute();
    $usuario_data = $stmt->fetch();

    // Importante: Si no se encuentran datos, se muestra un error (aunque no debería pasar si está logueado)
    if (!$usuario_data) {
        die("Error: No se pudieron cargar los datos del usuario. Por favor, contacte al administrador.");
    }
} catch (PDOException $e) {
    die("Error al cargar datos de perfil: " . $e->getMessage());
}

// Lógica para ACTUALIZAR PERFIL
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['actualizar_perfil'])) {
    $nombre_completo = trim($_POST['nombre_completo']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    $password_actual = $_POST['password_actual'] ?? '';
    $password_nueva = $_POST['password_nueva'] ?? '';
    $password_confirmar = $_POST['password_confirmar'] ?? '';
    
    $foto_perfil_actual = $usuario_data['foto_perfil'];
    $nuevo_nombre_foto = $foto_perfil_actual;
    $cambio_contrasena = false;

    // A. Manejo de la subida de foto
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $nombre_archivo = $_FILES['foto_perfil']['name'];
        $extension = pathinfo($nombre_archivo, PATHINFO_EXTENSION);
        $nuevo_nombre_foto = 'user_' . $id_usuario . '_' . time() . '.' . $extension;
        $ruta_destino = 'uploads/perfiles/' . $nuevo_nombre_foto;

        if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $ruta_destino)) {
            // CORRECCIÓN: Verificar que la foto anterior no sea 'default.png' y que no esté vacía antes de intentar unlink
            if (!empty($foto_perfil_actual) && $foto_perfil_actual != 'default.png' && file_exists('uploads/perfiles/' . $foto_perfil_actual)) {
                // Warning solucionado: Se verifica que el valor no sea solo el directorio o vacío.
                unlink('uploads/perfiles/' . $foto_perfil_actual); 
            }
            // Actualizar la sesión con la nueva foto
            $_SESSION['usuario_perfil'] = $nuevo_nombre_foto;
        } else {
            $mensaje = "Error al subir la imagen.";
            $alerta_tipo = 'warning';
        }
    }

    // B. Manejo de la Contraseña
    $password_update_sql = "";
    $params = [
        ':nombre_completo' => $nombre_completo,
        ':email' => $email,
        ':telefono' => $telefono,
        ':foto_perfil' => $nuevo_nombre_foto,
        ':id' => $id_usuario
    ];

    if (!empty($password_nueva)) {
        // Verificar contraseña actual
        $sql_check = "SELECT password FROM usuarios WHERE id_usuario = :id";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->bindParam(':id', $id_usuario, PDO::PARAM_INT);
        $stmt_check->execute();
        $hash_db = $stmt_check->fetchColumn();

        if (password_verify($password_actual, $hash_db)) {
            if ($password_nueva === $password_confirmar) {
                $password_hashed = password_hash($password_nueva, PASSWORD_DEFAULT);
                $password_update_sql = ", password = :password_hashed";
                $params[':password_hashed'] = $password_hashed;
                $cambio_contrasena = true;
            } else {
                $mensaje = "La nueva contraseña y su confirmación no coinciden.";
                $alerta_tipo = 'danger';
            }
        } else {
            $mensaje = "La contraseña actual es incorrecta.";
            $alerta_tipo = 'danger';
        }
    }

    // C. Ejecutar la actualización (solo si no hubo errores de contraseña)
    if ($alerta_tipo !== 'danger') {
        try {
            $sql_update = "UPDATE usuarios SET 
                nombre_completo = :nombre_completo,
                email = :email,
                telefono = :telefono,
                foto_perfil = :foto_perfil 
                {$password_update_sql}
                WHERE id_usuario = :id";
            
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute($params);

            // Recargar datos para reflejar cambios en el formulario
            $sql_reload = "SELECT nombre_completo, usuario, email, telefono, foto_perfil FROM usuarios WHERE id_usuario = :id";
            $stmt_reload = $pdo->prepare($sql_reload);
            $stmt_reload->bindParam(':id', $id_usuario, PDO::PARAM_INT);
            $stmt_reload->execute();
            $usuario_data_reload = $stmt_reload->fetch();

            // CORRECCIÓN: Verificar la recarga (solución a los Warnings en líneas inferiores)
            if ($usuario_data_reload) {
                 $usuario_data = $usuario_data_reload;
                 $_SESSION['usuario_nombre'] = $usuario_data['nombre_completo'];
                 $mensaje = "Perfil actualizado exitosamente." . ($cambio_contrasena ? " (Contraseña cambiada)" : "");
                 $alerta_tipo = 'success';
            } else {
                 $mensaje = "Perfil actualizado, pero no se pudieron recargar los datos. Por favor, refresque la página.";
                 $alerta_tipo = 'warning';
            }
            

        } catch (PDOException $e) {
            $mensaje = "Error al actualizar la base de datos: " . $e->getMessage();
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
    <title>Mi Perfil - Gestión de Tareas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h1 class="mb-4"><i class="fas fa-user-circle"></i> Mi Perfil</h1>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $alerta_tipo; ?>" role="alert">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4 text-center">
                <img src="uploads/perfiles/<?php echo htmlspecialchars($usuario_data['foto_perfil']); ?>" class="img-thumbnail mb-3" alt="Foto de Perfil" style="width: 180px; height: 180px; object-fit: cover;">
                <h4><?php echo htmlspecialchars($usuario_data['nombre_completo']); ?></h4>
                <p class="text-muted">@<?php echo htmlspecialchars($usuario_data['usuario']); ?></p>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        Actualizar Información Personal
                    </div>
                    <div class="card-body">
                        <form method="POST" action="perfil.php" enctype="multipart/form-data">
                            <input type="hidden" name="actualizar_perfil" value="1">

                            <h5 class="mb-3">Datos Básicos</h5>
                            <div class="mb-3">
                                <label for="nombre_completo" class="form-label">Nombre Completo</label>
                                <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" value="<?php echo htmlspecialchars($usuario_data['nombre_completo']); ?>" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Correo Electrónico</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($usuario_data['email']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="telefono" class="form-label">Número de Teléfono</label>
                                    <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($usuario_data['telefono']); ?>">
                                </div>
                            </div>

                            <h5 class="mb-3 mt-3">Foto de Perfil</h5>
                            <div class="mb-3">
                                <label for="foto_perfil" class="form-label">Subir nueva foto (JPG, PNG)</label>
                                <input class="form-control" type="file" id="foto_perfil" name="foto_perfil" accept="image/jpeg,image/png">
                            </div>

                            <h5 class="mb-3 mt-4">Cambiar Contraseña (Opcional)</h5>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="password_actual" class="form-label">Contraseña Actual</label>
                                    <input type="password" class="form-control" id="password_actual" name="password_actual">
                                    <small class="text-muted">Requerida si desea cambiar la contraseña.</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="password_nueva" class="form-label">Nueva Contraseña</label>
                                    <input type="password" class="form-control" id="password_nueva" name="password_nueva">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="password_confirmar" class="form-label">Confirmar Nueva Contraseña</label>
                                    <input type="password" class="form-control" id="password_confirmar" name="password_confirmar">
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cambios</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer" style="z-index: 1080;">
</div>
</body>
</html>