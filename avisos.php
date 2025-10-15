<?php
// Archivo: avisos.php
// Página dedicada a Avisos y Comunicación Interna

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php'; 

// 1. Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$id_usuario = $_SESSION['usuario_id'];
$rol_usuario = $_SESSION['usuario_rol'] ?? 'empleado';
$nombre_usuario = $_SESSION['usuario_nombre'] ?? 'Usuario';

$avisos_activos = [];

try {
    // Consulta para obtener todos los avisos activos, ordenados por fecha
    $sql_avisos = "
        SELECT 
            a.id_aviso, a.titulo, a.contenido, a.fecha_publicacion, a.prioridad, 
            u.nombre_completo AS creador_nombre
        FROM avisos a
        JOIN usuarios u ON a.id_creador = u.id_usuario
        WHERE a.es_activo = 1 
        ORDER BY FIELD(a.prioridad, 'urgente', 'importante', 'informativo'), a.fecha_publicacion DESC
    ";
    $stmt_avisos = $pdo->prepare($sql_avisos);
    $stmt_avisos->execute();
    $avisos_activos = $stmt_avisos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error de BD en avisos.php: " . $e->getMessage());
}

/**
 * Función helper para obtener la clase y el ícono de prioridad
 * @param string $prioridad
 * @return array
 */
function getAvisoPrioridadInfo($prioridad) {
    switch (strtolower($prioridad)) {
        case 'urgente': 
            return ['class' => 'danger', 'icon' => 'exclamation-triangle', 'text' => '¡Urgente!'];
        case 'importante': 
            return ['class' => 'warning', 'icon' => 'exclamation-circle', 'text' => 'Importante'];
        case 'informativo': 
            return ['class' => 'info', 'icon' => 'info-circle', 'text' => 'Informativo'];
        default: 
            return ['class' => 'secondary', 'icon' => 'bell', 'text' => 'General'];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avisos y Comunicación Interna | Logística ACTIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .page-header {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }
        .aviso-card {
            border-left: 5px solid;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .aviso-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
        }
    </style>
</head>
<body>

    <?php include 'navbar.php'; // Incluye el navbar ?>

    <div class="container mt-4">
        
        <div class="page-header">
            <h1 class="h3 mb-1 text-gray-800">
                <i class="fas fa-bullhorn me-2 text-primary"></i> Avisos y Comunicación Interna
            </h1>
            <p class="text-muted mb-0">Mantente al día con los comunicados oficiales del equipo de <?php echo ucfirst($rol_usuario); ?>.</p>
        </div>

        <?php if ($rol_usuario === 'admin'): ?>
            <div class="row mb-4">
                <div class="col-12 text-end">
                    <a href="avisos_admin.php" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i> Gestionar Avisos
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($avisos_activos)): ?>
            <div class="alert alert-info text-center" role="alert">
                <i class="fas fa-info-circle me-2"></i> Actualmente no hay avisos activos.
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($avisos_activos as $aviso): 
                    $info = getAvisoPrioridadInfo($aviso['prioridad']);
                    $border_color = 'border-' . $info['class'];
                    $text_color = 'text-' . $info['class'];
                ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm aviso-card <?php echo $border_color; ?>" style="border-left-width: 5px;">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <span class="badge bg-<?php echo $info['class']; ?> text-uppercase">
                                    <i class="fas fa-<?php echo $info['icon']; ?> me-1"></i> <?php echo $info['text']; ?>
                                </span>
                                <small class="text-muted">Publicado: <?php echo date('d/m/Y H:i', strtotime($aviso['fecha_publicacion'])); ?></small>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title <?php echo $text_color; ?> fw-bold"><?php echo htmlspecialchars($aviso['titulo']); ?></h5>
                                <p class="card-text text-muted small mb-3">Por: <?php echo htmlspecialchars($aviso['creador_nombre']); ?></p>
                                <p class="card-text">
                                    <?php 
                                        $contenido_completo = htmlspecialchars($aviso['contenido']);
                                        $snippet = substr($contenido_completo, 0, 150);
                                        if (strlen($contenido_completo) > 150) {
                                            $snippet .= '...';
                                        }
                                        echo $snippet;
                                    ?>
                                </p>
                            </div>
                            <div class="card-footer text-end bg-white border-top-0">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#avisoModal<?php echo $aviso['id_aviso']; ?>">
                                    Leer Aviso Completo
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal fade" id="avisoModal<?php echo $aviso['id_aviso']; ?>" tabindex="-1" aria-labelledby="avisoModalLabel<?php echo $aviso['id_aviso']; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-<?php echo $info['class']; ?> text-white">
                                    <h5 class="modal-title" id="avisoModalLabel<?php echo $aviso['id_aviso']; ?>">
                                        <i class="fas fa-<?php echo $info['icon']; ?> me-2"></i> <?php echo htmlspecialchars($aviso['titulo']); ?>
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3 small text-muted">
                                        <i class="fas fa-user-edit me-1"></i> Por: <?php echo htmlspecialchars($aviso['creador_nombre']); ?>
                                        <span class="ms-3"><i class="fas fa-calendar-alt me-1"></i> Publicado: <?php echo date('d/m/Y H:i', strtotime($aviso['fecha_publicacion'])); ?></span>
                                    </div>
                                    <hr>
                                    <p style="white-space: pre-wrap;"><?php echo htmlspecialchars($aviso['contenido']); ?></p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>