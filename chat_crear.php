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

// Cargar todos los usuarios (excluyendo al usuario actual)
$usuarios_disponibles = [];
try {
    $sql_users = "SELECT id_usuario, nombre_completo FROM usuarios WHERE id_usuario != :id_usuario ORDER BY nombre_completo";
    $stmt_users = $pdo->prepare($sql_users);
    $stmt_users->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
    $stmt_users->execute();
    $usuarios_disponibles = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = "Error al cargar usuarios: " . $e->getMessage();
    $alerta_tipo = 'danger';
}

// Lógica de Creación
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['crear_chat'])) {
    
    $tipo = $_POST['tipo'] ?? ''; // 'individual' o 'grupal'
    $nombre_grupo = trim($_POST['nombre_grupo'] ?? null);
    $participantes_ids = $_POST['participantes'] ?? [];

    // Validar tipo
    if (!in_array($tipo, ['individual', 'grupal'])) {
        $mensaje = "Tipo de chat no válido.";
        $alerta_tipo = 'danger';
    } 
    // Validar participantes
    elseif (empty($participantes_ids)) {
        $mensaje = "Debe seleccionar al menos un participante.";
        $alerta_tipo = 'danger';
    } 
    // Validar nombre de grupo
    elseif ($tipo === 'grupal' && empty($nombre_grupo)) {
        $mensaje = "Para un chat grupal, debe proporcionar un nombre.";
        $alerta_tipo = 'danger';
    } else {
        
        // 1. Preparar la lista final de participantes (incluyendo al creador)
        $participantes_final = array_unique(array_merge([$id_usuario], array_map('intval', $participantes_ids)));
        
        $pdo->beginTransaction();
        try {
            $id_nueva_conv = null;

            if ($tipo === 'individual' && count($participantes_final) === 2) {
                // Lógica de chat individual: EVITAR DUPLICADOS
                $otro_participante_id = ($participantes_final[0] == $id_usuario) ? $participantes_final[1] : $participantes_final[0];

                // Buscar si ya existe una conversación individual entre estos dos usuarios
                $sql_find = "SELECT c.id_conversacion FROM conversaciones c
                             WHERE c.tipo = 'individual'
                             AND EXISTS (SELECT 1 FROM participantes_chat pc1 WHERE pc1.id_conversacion = c.id_conversacion AND pc1.id_usuario = :user1)
                             AND EXISTS (SELECT 1 FROM participantes_chat pc2 WHERE pc2.id_conversacion = c.id_conversacion AND pc2.id_usuario = :user2)
                             AND (SELECT COUNT(id_usuario) FROM participantes_chat WHERE id_conversacion = c.id_conversacion) = 2";
                             
                $stmt_find = $pdo->prepare($sql_find);
                $stmt_find->execute([':user1' => $id_usuario, ':user2' => $otro_participante_id]);
                
                if ($id_conv_existente = $stmt_find->fetchColumn()) {
                    $id_nueva_conv = $id_conv_existente;
                    $mensaje = "Ya existe un chat individual con este usuario. Redirigiendo...";
                    $alerta_tipo = 'info';
                } else {
                    // Crear nueva conversación individual
                    $sql_conv = "INSERT INTO conversaciones (tipo) VALUES ('individual')";
                    $pdo->exec($sql_conv);
                    $id_nueva_conv = $pdo->lastInsertId();
                }
            } elseif ($tipo === 'grupal') {
                 // Crear nueva conversación grupal (siempre se crea una nueva)
                $sql_conv = "INSERT INTO conversaciones (tipo, nombre_grupo) VALUES ('grupal', :nombre)";
                $stmt_conv = $pdo->prepare($sql_conv);
                $stmt_conv->execute([':nombre' => $nombre_grupo]);
                $id_nueva_conv = $pdo->lastInsertId();
            }

            // Si se creó una nueva conversación, insertar participantes
            if ($id_nueva_conv && $id_nueva_conv != $id_conv_existente) {
                $sql_part = "INSERT INTO participantes_chat (id_conversacion, id_usuario) VALUES (:id_conv, :id_user)";
                $stmt_part = $pdo->prepare($sql_part);
                
                foreach ($participantes_final as $user_id) {
                    $stmt_part->execute([':id_conv' => $id_nueva_conv, ':id_user' => $user_id]);
                }
                
                $mensaje = ($tipo === 'grupal' ? "Chat grupal '$nombre_grupo'" : "Chat individual") . " creado exitosamente.";
                $alerta_tipo = 'success';
            }


            $pdo->commit();
            
            // Redirigir a la conversación recién creada/encontrada
            header("Location: chat.php?id_conversacion=" . $id_nueva_conv);
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $mensaje = "Error de BD al crear el chat: " . $e->getMessage();
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
    <title>Crear Chat - Mensajería</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h1 class="mb-4"><i class="fas fa-plus"></i> Iniciar Nueva Conversación</h1>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $alerta_tipo; ?>" role="alert">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="chat_crear.php">
            <input type="hidden" name="crear_chat" value="1">
            
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    Tipo de Conversación
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Seleccione el Tipo:</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo" id="tipo_individual" value="individual" checked>
                            <label class="form-check-label" for="tipo_individual">
                                <i class="fas fa-user"></i> Chat Individual (1 a 1)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo" id="tipo_grupal" value="grupal">
                            <label class="form-check-label" for="tipo_grupal">
                                <i class="fas fa-users"></i> Chat Grupal (Con Nombre y Múltiples Participantes)
                            </label>
                        </div>
                    </div>

                    <div id="grupo-nombre-container" class="mb-3" style="display: none;">
                        <label for="nombre_grupo" class="form-label">Nombre del Grupo</label>
                        <input type="text" class="form-control" id="nombre_grupo" name="nombre_grupo" maxlength="100">
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    Seleccionar Participantes
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="participantes" class="form-label">Participantes (Ctrl/Cmd + Clic para seleccionar varios)</label>
                        <select class="form-select" id="participantes" name="participantes[]" multiple required size="10">
                            <?php foreach ($usuarios_disponibles as $user): ?>
                                <option value="<?php echo $user['id_usuario']; ?>">
                                    <?php echo htmlspecialchars($user['nombre_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                           Para un chat individual, solo selecciona a una persona. Para grupal, selecciona dos o más.
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mb-5">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-comments"></i> Iniciar Chat
                </button>
            </div>
        </form>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tipoIndividual = document.getElementById('tipo_individual');
            const tipoGrupal = document.getElementById('tipo_grupal');
            const grupoNombreContainer = document.getElementById('grupo-nombre-container');
            const participantesSelect = document.getElementById('participantes');

            function toggleGrupoNombre() {
                if (tipoGrupal.checked) {
                    grupoNombreContainer.style.display = 'block';
                    participantesSelect.setAttribute('size', 10); // Aseguramos que se puedan ver varios
                } else {
                    grupoNombreContainer.style.display = 'none';
                    participantesSelect.setAttribute('size', 2); // Para individual, mejor un tamaño pequeño
                }
            }

            tipoIndividual.addEventListener('change', toggleGrupoNombre);
            tipoGrupal.addEventListener('change', toggleGrupoNombre);
            
            // Llamada inicial
            toggleGrupoNombre();
        });
    </script>
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer" style="z-index: 1080;">
</div>
</body>
</html>