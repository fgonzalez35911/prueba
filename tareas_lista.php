<?php
// Archivo: tareas_lista.php
session_start();
include 'conexion.php'; 

// 1. Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$id_usuario = $_SESSION['usuario_id'];
$rol_usuario = $_SESSION['usuario_rol'];
// Obtener filtro de estado de la URL. Por defecto, 'todas' si no se especifica
$estado_filtro = $_GET['estado'] ?? 'todas'; 

// 2. Lógica para filtrar tareas según el rol y el estado
$sql = "SELECT id_tarea, titulo, estado, fecha_creacion, fecha_limite FROM tareas";
$params = [];
$where_clauses = [];

// Si es empleado, solo ve sus tareas asignadas
if ($rol_usuario === 'empleado') {
    $where_clauses[] = "id_asignado = :id_usuario";
    $params[':id_usuario'] = $id_usuario;
}

// Si hay un filtro de estado (y no es 'todas')
if ($estado_filtro !== 'todas') {
    $where_clauses[] = "estado = :estado";
    $params[':estado'] = $estado_filtro;
}

// Construir la cláusula WHERE final
if (count($where_clauses) > 0) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY fecha_limite ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar lista de tareas: " . $e->getMessage());
    $tareas = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Tareas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h1>Lista de Tareas (Filtro: <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $estado_filtro))); ?>)</h1>
        
        <div class="mb-3">
            <a href="tareas_lista.php?estado=pendiente" class="btn btn-info btn-sm">Pendientes</a>
            <a href="tareas_lista.php?estado=en_proceso" class="btn btn-warning btn-sm">En Proceso</a>
            <a href="tareas_lista.php?estado=finalizada_tecnico" class="btn btn-primary btn-sm">Para Revisión</a>
            <a href="tareas_lista.php?estado=todas" class="btn btn-secondary btn-sm">Todas</a>
        </div>
        
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>Estado</th>
                    <th>Fecha Límite</th>
                    <th>Acción</th> 
                </tr>
            </thead>
            <tbody>
                <?php if (count($tareas) > 0): ?>
                    <?php foreach ($tareas as $tarea): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($tarea['id_tarea']); ?></td>
                        <td><?php echo htmlspecialchars($tarea['titulo']); ?></td>
                        <td>
                            <?php 
                            $badge_class = 'bg-secondary';
                            if ($tarea['estado'] === 'pendiente') $badge_class = 'bg-info';
                            if ($tarea['estado'] === 'en_proceso') $badge_class = 'bg-warning text-dark';
                            if ($tarea['estado'] === 'finalizada_tecnico') $badge_class = 'bg-primary';
                            if ($tarea['estado'] === 'verificada_admin') $badge_class = 'bg-success';
                            if ($tarea['estado'] === 'rechazada') $badge_class = 'bg-danger';
                            ?>
                            <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $tarea['estado']))); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($tarea['fecha_limite']); ?></td>
                        
                        <td>
                        <?php
                        // Lógica de visualización para el Administrador
                        if ($rol_usuario === 'admin') {
                            if ($tarea['estado'] === 'finalizada_tecnico') {
                                // Tarea lista para revisión
                                echo '<a href="tarea_ver.php?id=' . $tarea['id_tarea'] . '" class="btn btn-sm btn-primary me-2">';
                                echo '<i class="fas fa-search"></i> Revisar Tarea';
                                echo '</a>';
                            } else if ($tarea['estado'] === 'verificada_admin') {
                                // Tarea terminada y verificada
                                echo '<span class="badge bg-success">Verificada</span>';
                            } else {
                                // Tareas en cualquier otro estado (pendiente, en_proceso, rechazada)
                                // Botón de EDICIÓN (para el Administrador)
                                echo '<a href="tarea_editar.php?id=' . $tarea['id_tarea'] . '" class="btn btn-sm btn-info me-2">';
                                echo '<i class="fas fa-edit"></i> Editar';
                                echo '</a>';
                                // Botón de Ver (para el Administrador)
                                echo '<a href="tarea_ver.php?id=' . $tarea['id_tarea'] . '" class="btn btn-sm btn-outline-secondary">';
                                echo '<i class="fas fa-eye"></i> Ver';
                                echo '</a>';
                            }
                            
                        // Lógica de visualización para el Empleado
                        } else if ($rol_usuario === 'empleado') {
                            if ($tarea['estado'] === 'en_proceso') {
                                // Botón para finalizar (llama a la función JS)
                                echo '<button type="button" class="btn btn-sm btn-success" onclick="finalizarTarea(' . $tarea['id_tarea'] . ')">';
                                echo '<i class="fas fa-check"></i> Finalizar / Enviar a Revisión';
                                echo '</button>';
                            } else if ($tarea['estado'] === 'finalizada_tecnico') {
                                 echo '<span class="badge bg-primary">Pendiente Revisión</span>';
                            } else {
                                // Si no está en_proceso ni finalizada, solo puede ver el detalle
                                echo '<a href="tarea_ver.php?id=' . $tarea['id_tarea'] . '" class="btn btn-sm btn-outline-secondary">';
                                echo 'Ver Detalle';
                                echo '</a>';
                            }
                        }
                        ?>
                        </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">No se encontraron tareas con este filtro.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

    </div> 
        

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function finalizarTarea(id_tarea) {
        if (!id_tarea || id_tarea == 0) {
            alert("Error: ID de tarea no válido.");
            return;
        }

        if (!confirm("ADVERTENCIA: ¿Está seguro de que desea marcar esta tarea como FINALIZADA para revisión?")) {
            return;
        }
        
        // Deshabilitar el botón específico (si existe)
        const btn = document.querySelector(`button[onclick*="finalizarTarea(${id_tarea})"]`);
        if (btn) {
           btn.disabled = true;
           btn.textContent = 'Enviando...';
        }


        const data = new URLSearchParams();
        data.append('id_tarea', id_tarea);
        data.append('nuevo_estado', 'finalizada_tecnico'); 

        fetch('tarea_actualizar_estado.php', {
            method: 'POST',
            body: data
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('Tarea enviada a revisión. El administrador ha sido notificado.');
                // Recargar la página para actualizar el estado
                window.location.reload(); 
            } else {
                alert('Error al finalizar la tarea: ' + data.error);
                console.error('Error al finalizar tarea:', data.error);
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'Finalizar / Enviar a Revisión';
                }
            }
        })
        .catch(error => {
            alert('Error de red o servidor: ' + error.message);
            console.error('Error de fetch:', error);
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Finalizar / Enviar a Revisión';
            }
        });
    }
    </script>
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer" style="z-index: 1080;">
    </div>
    
</body>
</html>