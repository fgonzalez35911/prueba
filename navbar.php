<?php
// Archivo: navbar.php
// CORRECCIÓN: Evitar el Notice si la sesión ya está activa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php'; 

$rol_usuario_nav = $_SESSION['usuario_rol'] ?? 'empleado';
$nombre_usuario_nav = $_SESSION['usuario_nombre'] ?? 'Invitado';
$foto_perfil_nav = $_SESSION['usuario_perfil'] ?? 'default.png'; 
$id_usuario_nav = $_SESSION['usuario_id'] ?? 0;

// Lógica para el Conteo de Notificaciones (Inicial)
$notificaciones_no_leidas = 0;
if ($id_usuario_nav > 0 && isset($pdo)) {
    try {
        $sql_notif = "SELECT COUNT(*) FROM notificaciones WHERE id_usuario_destino = :id_user AND leida = 0";
        $stmt_notif = $pdo->prepare($sql_notif);
        $stmt_notif->execute([':id_user' => $id_usuario_nav]); 
        $notificaciones_no_leidas = $stmt_notif->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error al cargar conteo de notificaciones: " . $e->getMessage());
    }
}
?>

<style>
    /* Filtro CSS y tamaño para el logo (log.png) */
    .logo-invertido {
        height: 32px; /* LOGO MÁS GRANDE */
        margin-right: 10px; 
        /* Aplica filtro para invertir el color (negro a blanco) */
        filter: invert(100%) grayscale(100%) brightness(200%); 
    }
</style>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            
            <img src="assets/log.png" alt="Logo Logística ACTIS" class="logo-invertido"> 
            Logística | ACTIS
            
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNavDropdown">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'noticias_ffaa.php' ? 'active' : ''; ?>" href="noticias_ffaa.php">
                        <i class="fas fa-bullhorn"></i> Noticias
                    </a>
                </li>
                
                <?php if ($rol_usuario_nav === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tarea_crear.php' ? 'active' : ''; ?>" href="tarea_crear.php">
                         <i class="fas fa-plus-circle"></i> Crear Tarea
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tareas_lista.php' ? 'active' : ''; ?>" href="tareas_lista.php">
                        <i class="fas fa-tasks"></i> Tareas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'chat.php' ? 'active' : ''; ?>" href="chat.php">
                        <i class="fas fa-comments"></i> Chat
                    </a>
                </li>
                 <?php if ($rol_usuario_nav === 'admin'): ?>
                <li class="nav-item dropdown">
                    <?php 
                    // Lógica para activar el dropdown si estamos en cualquier página de admin_ o avisos_
                    $currentPage = basename($_SERVER['PHP_SELF']);
                    $adminActive = (strpos($currentPage, 'admin_') !== false || strpos($currentPage, 'avisos_') !== false) ? 'active' : '';
                    ?>
                    <a class="nav-link dropdown-toggle <?php echo $adminActive; ?>" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-cog"></i> Administración
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                        <li><a class="dropdown-item" href="admin_usuarios.php">Usuarios y Roles</a></li>
                        <li><a class="dropdown-item" href="admin_categorias.php">Categorías</a></li>
                        <li><a class="dropdown-item" href="avisos_lista.php">Administrar Avisos</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav">
                 <li class="nav-item dropdown me-3">
                    <a class="nav-link" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
                        <i class="fas fa-bell"></i> 
                        <span class="d-lg-none ms-2">Notificaciones</span> 
                        <span class="badge bg-danger rounded-pill" id="notification-badge" style="display: <?php echo $notificaciones_no_leidas > 0 ? 'inline' : 'none'; ?>">
                            <?php echo $notificaciones_no_leidas; ?>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown" id="notifications-list" style="width: 300px;">
                        <li><a class="dropdown-item text-center text-muted" href="#">Cargando...</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="uploads/perfiles/<?php echo htmlspecialchars($foto_perfil_nav); ?>" alt="Perfil" class="rounded-circle" style="width: 30px; height: 30px; object-fit: cover;">
                        <?php echo htmlspecialchars($nombre_usuario_nav); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownMenuLink">
                        <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-user-circle me-2"></i> Mi Perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer" style="z-index: 1080;">
    </div>


<script>
    let lastCheckTime = Date.now(); 
    const currentUserId = <?php echo json_encode($id_usuario_nav); ?>;
    
    // FUNCIÓN: Reproducir Sonido de Alerta 
    function playNotificationSound() {
        const soundPath = 'assets/alert.mp3'; 

        try {
            const audio = new Audio(soundPath); 
            audio.volume = 0.8; 
            const playPromise = audio.play();

            if (playPromise !== undefined) {
                playPromise.catch(e => {
                    console.warn(`[Sound] Sonido bloqueado por el navegador o ruta incorrecta (${soundPath}).`, e);
                });
            }
        } catch (e) {
            console.error("[Sound] Error grave al intentar crear el objeto Audio. Verifique si el archivo existe:", e);
        }
    }


    // 1. Función para actualizar el badge (el número rojo)
    function updateNotificationBadge(count) {
        const badge = document.getElementById('notification-badge');
        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'inline' : 'none';
        }
    }

    // 2. Función para cargar la lista de notificaciones en el dropdown
    function loadNotificationsList(event) {
        const list = document.getElementById('notifications-list');
        list.innerHTML = '<li><a class="dropdown-item text-center text-primary" href="#"><i class="fas fa-sync fa-spin me-2"></i> Cargando...</a></li>';

        fetch(`notificaciones_fetch.php?last=null`) 
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Error HTTP: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                list.innerHTML = '';
                if (data.notifications.length === 0) {
                    list.innerHTML = '<li><a class="dropdown-item text-center text-muted" href="#">No hay notificaciones.</a></li>';
                    list.innerHTML += '<li><hr class="dropdown-divider"></li>';
                    list.innerHTML += '<li><a class="dropdown-item text-center small" href="notificaciones_lista.php">Ver todas</a></li>';
                    return;
                }

                data.notifications.forEach(notif => {
                    // Mapeo de íconos según el tipo 
                    let icon = 'fa-info-circle';
                    let typeColor = '';
                    
                    if (notif.tipo === 'chat') {
                        icon = 'fa-comment';
                    } else if (notif.tipo === 'nueva_tarea') {
                        icon = 'fa-clipboard-list';
                    } else if (notif.tipo === 'tarea_terminada') { // <-- Tipo nuevo
                        icon = 'fa-exclamation-triangle';
                        typeColor = 'text-warning'; 
                    }
                    
                    const statusClass = notif.leida == 0 ? 'fw-bold' : 'text-muted'; 
                    
                    list.innerHTML += `
                        <li>
                            <a class="dropdown-item ${statusClass} ${typeColor} text-wrap" href="${notif.url}" data-notif-id="${notif.id_notificacion}" onclick="markAsRead(this)">
                                <i class="fas ${icon} me-1"></i>
                                ${notif.mensaje} 
                                <span class="small d-block fw-normal text-truncate">${notif.fecha_creacion}</span>
                            </a>
                        </li>
                    `;
                });
                
                list.innerHTML += '<li><hr class="dropdown-divider"></li>';
                list.innerHTML += '<li><a class="dropdown-item text-center small" href="notificaciones_lista.php">Ver todas</a></li>';

             })
             .catch(error => {
                 list.innerHTML = '<li><a class="dropdown-item text-center text-danger" href="#">Error al cargar.</a></li>';
                 console.error('Error al cargar lista de notificaciones:', error);
             });
    }


    // 3. Función para marcar como leída
    function markAsRead(element, id_notificacion_fallback = null) {
        let id_notif;
        if (element) {
            id_notif = element.getAttribute('data-notif-id');
        } else if (id_notificacion_fallback) {
            id_notif = id_notificacion_fallback;
        } else {
             return;
        }

        fetch(`notificaciones_mark_read.php?id=${id_notif}`)
             .then(response => {
                if(response.ok) {
                    if (element) {
                        element.classList.remove('fw-bold');
                        element.classList.add('text-muted');
                        element.onclick = null;
                    }
                    setTimeout(checkNewNotifications, 500); 
                }
             })
             .catch(error => console.error('Error al marcar como leída:', error));
    }


    // 4. Función de Chequeo Periódico (Polling)
    function checkNewNotifications() {
        const now = Date.now(); 
        const timeToSend = lastCheckTime; 
        
        const url = `notificaciones_fetch.php?last=${timeToSend}`;

        fetch(url)
            .then(response => {
                 if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                updateNotificationBadge(data.unread_count);

                if (data.new_notifications && data.new_notifications.length > 0) {
                    showNewNotificationToasts(data.new_notifications);
                    playNotificationSound(); 
                }
                
                lastCheckTime = now;
            })
            .catch(error => {
                console.error('Error en el polling de notificaciones:', error);
            });
    }

    // 5. Mostrar Toasts con lógica para tarea_terminada
    function showNewNotificationToasts(newNotifications) {
        if (typeof bootstrap === 'undefined' || typeof bootstrap.Toast === 'undefined') {
             console.error('ERROR: Bootstrap 5 JS o el componente Toast no está cargado.');
             return;
        }

        const toastContainer = document.getElementById('notificationToastContainer'); 
        if (!toastContainer) {
            console.warn('Contenedor Toast no encontrado (ID: notificationToastContainer).');
            return;
        }

        newNotifications.forEach(notif => {
            let iconClass = 'fas fa-info-circle';
            let titleText = 'Nueva Notificación';
            let bgColor = 'bg-info'; 
            let linkText = 'Ver Detalle';

            if (notif.tipo === 'nueva_tarea') {
                iconClass = 'fas fa-clipboard-list';
                titleText = 'Nueva Tarea Asignada';
                bgColor = 'bg-success'; 
                linkText = 'Ver Tarea';
            } else if (notif.tipo === 'chat') {
                iconClass = 'fas fa-comment';
                titleText = 'Nuevo Mensaje';
                bgColor = 'bg-primary'; 
                linkText = 'Ver Mensaje';
            } else if (notif.tipo === 'tarea_terminada') {
                // Estilo para la notificación de Tarea Terminada (Amarilla)
                iconClass = 'fas fa-exclamation-triangle'; 
                titleText = 'Tarea Lista para Verificación';
                bgColor = 'bg-warning text-dark'; 
                linkText = 'Revisar Tarea';
            }
            
            // 2. Solo mostrar Toast si el usuario NO está en la página de destino
            if (window.location.href.indexOf(notif.url) === -1) {
                
                const textClass = notif.tipo === 'tarea_terminada' ? 'text-dark' : 'text-white';
                
                const toastHtml = `
                    <div class="toast align-items-center ${textClass} ${bgColor} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="7000"> 
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="${iconClass} me-2"></i> 
                                <strong>${titleText}</strong> 
                                <span class="d-block small mt-1">${notif.mensaje.substring(0, 50)}${notif.mensaje.length > 50 ? '...' : ''}</span>
                                <a href="${notif.url}" class="btn btn-sm btn-light ms-2 text-primary mt-1" onclick="markAsRead(null, ${notif.id_notificacion});">${linkText}</a>
                            </div>
                            <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
                        </div>
                    </div>
                `;
                
                toastContainer.insertAdjacentHTML('beforeend', toastHtml);
                
                const newToastEl = toastContainer.lastElementChild;
                const toast = new bootstrap.Toast(newToastEl);

                newToastEl.addEventListener('hidden.bs.toast', () => {
                    markAsRead(null, notif.id_notificacion);
                    newToastEl.remove();
                });
                
                toast.show();
            } else {
                 markAsRead(null, notif.id_notificacion); 
            }
        });
    }

    // 6. Event Listeners y Polling
    document.addEventListener('DOMContentLoaded', () => {
        if (currentUserId > 0) {
            const dropdownEl = document.getElementById('notificationsDropdown');
            if (dropdownEl) {
                dropdownEl.addEventListener('shown.bs.dropdown', loadNotificationsList); 
            }
            
            checkNewNotifications();
            // *** CAMBIO A 6 SEGUNDOS ***
            setInterval(checkNewNotifications, 6000); 
        }
    });

</script>