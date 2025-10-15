<?php
// Archivo FEDE: dashboard.php (VERSIÓN FINAL Y CORREGIDA CON DATOS REALES DE BD)
session_start();
// Asegúrate de que este archivo 'conexion.php' exista y provea $pdo
include 'conexion.php'; 

// 1. Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$id_usuario = $_SESSION['usuario_id'];
$rol_usuario = $_SESSION['usuario_rol'];
$nombre_usuario = $_SESSION['usuario_nombre'] ?? 'Usuario';

// --- Funciones Helper ---
function getGreeting() {
    $hour = date('H');
    if ($hour >= 5 && $hour < 12) {
        return 'Buenos días';
    } elseif ($hour >= 12 && $hour < 19) {
        return 'Buenas tardes';
    } else {
        return 'Buenas noches';
    }
}
$saludo = getGreeting();

function getPrioridadClassDashboard($prioridad) {
    switch ($prioridad) {
        case 'urgente': return 'bg-danger text-white';
        case 'alta': return 'bg-warning text-dark';
        case 'media': return 'bg-info text-white';
        case 'baja': return 'bg-success text-white';
        default: return 'bg-secondary text-white';
    }
}

// --- Lista de Frases Motivadoras ---
$frases_motivadoras = [
    "¡El éxito es la suma de pequeños esfuerzos repetidos día tras día!",
    "La logística no es solo mover cosas, es mover el futuro. ¡Excelente trabajo!",
    "Mantén la calma y continúa. Cada tarea es un paso hacia el gran objetivo.",
    "La calidad no es un acto, es un hábito. ¡Vamos por ello!",
    "Somos lo que hacemos repetidamente. La excelencia, entonces, no es un acto, sino un hábito.",
    "Hoy es una nueva oportunidad para hacerlo mejor que ayer. ¡A trabajar!",
    "Cada problema es un regalo: sin problemas, no creceríamos. ¡A solucionar!",
];

// Seleccionar una frase al azar
$frase_del_dia = $frases_motivadoras[array_rand($frases_motivadoras)];


// --- Lógica para el Resumen Rápido, Alertas y KPIs ---
$resumen = [
    'pendiente' => 0, 'en_proceso' => 0, 'finalizada_tecnico' => 0,
    'verificada_admin' => 0, 'modificacion_pendiente' => 0, 
];
$tareas_admin_verificar = [];
$tareas_admin_atrasadas = [];
$tareas_empleado_urgentes = [];
$avisos_activos = []; 

// Variables de los 10 nuevos widgets inicializadas
$sla_porcentaje = 0;
$carga_trabajo_data_raw = [];
$actividad_reciente = [];
$categoria_data_raw = [];
$tareas_proximo_vencimiento = [];
$uso_recursos_data_raw = []; // Mantener como mock o consulta si tienes tabla 'recursos'

try {
    $filtro_sql = '';
    $params = [];
    if ($rol_usuario === 'empleado') {
        $filtro_sql = ' WHERE id_asignado = :id_usuario';
        $params[':id_usuario'] = $id_usuario;
    }
    
    // Resumen por estado (Tarjetas Superiores)
    $sql_resumen = "SELECT estado, COUNT(*) as total FROM tareas" . $filtro_sql . " GROUP BY estado";
    $stmt_resumen = $pdo->prepare($sql_resumen);
    $stmt_resumen->execute($params);
    
    while ($row = $stmt_resumen->fetch(PDO::FETCH_ASSOC)) {
        $resumen[$row['estado']] = $row['total'];
    }
    
    // **********************************************
    //  LÓGICA DE ALCANCE ORIGINAL (ADMIN/EMPLEADO)
    // **********************************************

    // Lógica de Alertas Dinámicas (por Rol)
    if ($rol_usuario === 'admin') {
        // ADMIN: Tareas Pendientes de Verificación
        $sql_verificar = "
            SELECT t.id_tarea, t.titulo, u.nombre_completo AS tecnico_nombre, t.fecha_finalizacion
            FROM tareas t
            JOIN usuarios u ON t.id_asignado = u.id_usuario
            WHERE t.estado = 'finalizada_tecnico'
            ORDER BY t.fecha_finalizacion DESC
            LIMIT 5
        ";
        $stmt_verificar = $pdo->prepare($sql_verificar);
        $stmt_verificar->execute();
        $tareas_admin_verificar = $stmt_verificar->fetchAll(PDO::FETCH_ASSOC);

        // ADMIN: Tareas Atrasadas
        $sql_atrasadas = "
            SELECT t.id_tarea, t.titulo, t.fecha_limite, u.nombre_completo AS asignado_nombre
            FROM tareas t
            JOIN usuarios u ON t.id_asignado = u.id_usuario
            WHERE t.fecha_limite < CURDATE() 
              AND t.estado IN ('pendiente', 'en_proceso', 'modificacion_pendiente')
            ORDER BY t.fecha_limite ASC
            LIMIT 5
        ";
        $stmt_atrasadas = $pdo->prepare($sql_atrasadas);
        $stmt_atrasadas->execute();
        $tareas_admin_atrasadas = $stmt_atrasadas->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($rol_usuario === 'empleado') {
        // EMPLEADO: Mis Tareas Más Urgentes/Cercanas
        $sql_urgentes = "
            SELECT t.id_tarea, t.titulo, t.prioridad, t.fecha_limite
            FROM tareas t
            WHERE t.id_asignado = :id_usuario 
              AND t.estado IN ('pendiente', 'en_proceso', 'modificacion_pendiente')
            ORDER BY FIELD(t.prioridad, 'urgente', 'alta', 'media', 'baja'), t.fecha_limite ASC
            LIMIT 5
        ";
        $stmt_urgentes = $pdo->prepare($sql_urgentes);
        $stmt_urgentes->execute([':id_usuario' => $id_usuario]);
        $tareas_empleado_urgentes = $stmt_urgentes->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Avisos Internos
    $sql_avisos = "
        SELECT a.titulo, a.contenido, u.nombre_completo 
        FROM avisos a
        JOIN usuarios u ON a.id_creador = u.id_usuario
        WHERE a.es_activo = 1 
        ORDER BY a.fecha_publicacion DESC 
        LIMIT 3
    ";
    $stmt_avisos = $pdo->prepare($sql_avisos);
    $stmt_avisos->execute();
    $avisos_activos = $stmt_avisos->fetchAll(PDO::FETCH_ASSOC);

    // Métricas Clave (KPIs originales y TMR)
    $sql_kpis = "
        SELECT estado, COUNT(*) as total 
        FROM tareas 
        WHERE estado IN ('verificada_admin', 'modificacion_pendiente')
          AND fecha_finalizacion >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY estado
    ";
    $stmt_kpis = $pdo->prepare($sql_kpis);
    $stmt_kpis->execute();
    $kpis_data = $stmt_kpis->fetchAll(PDO::FETCH_ASSOC);
    
    $total_verificados = 0;
    $aprobadas = 0;
    $rechazadas = 0;
    
    foreach ($kpis_data as $row) {
        $total_verificados += $row['total'];
        if ($row['estado'] === 'verificada_admin') {
            $aprobadas = $row['total'];
        } elseif ($row['estado'] === 'modificacion_pendiente') {
            $rechazadas = $row['total'];
        }
    }
    
    $eficiencia_porcentaje = $total_verificados > 0 ? round(($aprobadas / $total_verificados) * 100) : 0;
    
    // 2. KPI: Tiempos Medios de Resolución (TMR)
    $tiempo_promedio = 'N/A';
    if ($aprobadas > 0) {
        $sql_avg_time = "
            SELECT AVG(TIMESTAMPDIFF(HOUR, fecha_asignacion, fecha_finalizacion)) as avg_hours
            FROM tareas 
            WHERE estado = 'verificada_admin' 
              AND fecha_finalizacion IS NOT NULL
              AND fecha_asignacion IS NOT NULL
        ";
        $stmt_avg = $pdo->prepare($sql_avg_time);
        $stmt_avg->execute();
        $avg_hours = $stmt_avg->fetchColumn();
        
        if ($avg_hours !== false && $avg_hours !== null) {
            $dias = floor($avg_hours / 24);
            $horas = round($avg_hours % 24);
            $tiempo_promedio = ($dias > 0 ? "{$dias}d " : "") . "{$horas}h";
        }
    }
    
    // **********************************************
    //  LÓGICA CORREGIDA PARA LOS 10 NUEVOS WIDGETS
    // **********************************************
    
    // --- 1. KPI: Tasa de Cumplimiento (SLA) ---
    // Tareas finalizadas en los últimos 30 días vs. Tareas finalizadas a tiempo (antes de fecha_limite)
    $sql_sla = "
        SELECT 
            COUNT(*) as total_cerradas,
            SUM(CASE WHEN fecha_finalizacion <= fecha_limite THEN 1 ELSE 0 END) as a_tiempo
        FROM tareas 
        WHERE estado = 'verificada_admin'
          AND fecha_finalizacion >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ";
    $stmt_sla = $pdo->prepare($sql_sla);
    $stmt_sla->execute();
    $sla_data = $stmt_sla->fetch(PDO::FETCH_ASSOC);
    
    $total_tareas_cerradas_mes = $sla_data['total_cerradas'];
    $tareas_a_tiempo = $sla_data['a_tiempo'];
    $sla_porcentaje = ($total_tareas_cerradas_mes > 0) ? round(($tareas_a_tiempo / $total_tareas_cerradas_mes) * 100) : 0;
    
    // --- 3. Gráfico: Carga de Trabajo por Técnico ---
    // Tareas 'pendiente', 'en_proceso', 'modificacion_pendiente' por empleado.
    $sql_carga = "
        SELECT u.nombre_completo AS nombre, COUNT(t.id_tarea) AS count
        FROM usuarios u
        LEFT JOIN tareas t ON u.id_usuario = t.id_asignado AND t.estado IN ('pendiente', 'en_proceso', 'modificacion_pendiente')
        WHERE u.rol = 'empleado'
        GROUP BY u.id_usuario, u.nombre_completo
        ORDER BY count DESC
    ";
    $stmt_carga = $pdo->prepare($sql_carga);
    $stmt_carga->execute();
    $carga_trabajo_data_raw = $stmt_carga->fetchAll(PDO::FETCH_ASSOC);
    
    $chart_labels_carga = json_encode(array_column($carga_trabajo_data_raw, 'nombre'));
    $chart_data_carga = json_encode(array_column($carga_trabajo_data_raw, 'count'));


    // --- 4. Lista: Actividad Reciente del Sistema ---
    // Consulta simulada para un feed de actividad (requiere una tabla de historial/log real)
    // NOTA: Se mantiene la estructura con datos relevantes, pero la consulta real dependería de una tabla de LOGS.
    // Aquí se simula extrayendo los últimos cambios de estado de la tabla de tareas.
    $sql_actividad = "
        SELECT 
            t.id_tarea, t.titulo, u.nombre_completo, t.estado, t.prioridad, t.fecha_finalizacion
        FROM tareas t
        JOIN usuarios u ON t.id_asignado = u.id_usuario
        WHERE t.estado IN ('finalizada_tecnico', 'verificada_admin', 'modificacion_pendiente')
        ORDER BY t.fecha_finalizacion DESC, t.fecha_creacion DESC
        LIMIT 4
    ";
    $stmt_actividad = $pdo->prepare($sql_actividad);
    $stmt_actividad->execute();
    $actividad_data_raw = $stmt_actividad->fetchAll(PDO::FETCH_ASSOC);

    $actividad_reciente = [];
    foreach($actividad_data_raw as $item) {
        $mensaje = "";
        $icono = "";
        $hora = date('H:i', strtotime($item['fecha_finalizacion'] ?? date('Y-m-d H:i:s'))); // Usar fecha_finalizacion o fecha actual
        
        switch ($item['estado']) {
            case 'finalizada_tecnico':
                $mensaje = "Tarea #{$item['id_tarea']} ({$item['titulo']}) finalizada por {$item['nombre_completo']}.";
                $icono = 'fas fa-check-circle text-success';
                break;
            case 'verificada_admin':
                $mensaje = "Tarea #{$item['id_tarea']} aprobada y verificada por el Administrador.";
                $icono = 'fas fa-thumbs-up text-primary';
                break;
            case 'modificacion_pendiente':
                $mensaje = "El Admin solicitó modificación en Tarea #{$item['id_tarea']} ({$item['titulo']}).";
                $icono = 'fas fa-undo text-warning';
                break;
            default:
                continue 2; // Saltar
        }
        $actividad_reciente[] = ['hora' => $hora, 'mensaje' => $mensaje, 'icono' => $icono];
    }
    
    // --- 5. Gráfico: Distribución de Tareas por Categoría ---
    // Tareas activas (pendiente, en_proceso, mod_pendiente) por categoría.
    $sql_categoria = "
        SELECT c.nombre, COUNT(t.id_tarea) AS count
        FROM categorias c
        JOIN tareas t ON c.id_categoria = t.id_categoria
        WHERE t.estado IN ('pendiente', 'en_proceso', 'modificacion_pendiente')
        GROUP BY c.nombre
        ORDER BY count DESC
    ";
    $stmt_categoria = $pdo->prepare($sql_categoria);
    $stmt_categoria->execute();
    $categoria_data_raw = $stmt_categoria->fetchAll(PDO::FETCH_ASSOC);

    $chart_labels_categoria = json_encode(array_column($categoria_data_raw, 'nombre'));
    $chart_data_categoria = json_encode(array_column($categoria_data_raw, 'count'));
    
    // --- 7. Lista: Tareas Próximo Vencimiento ---
    // Tareas activas cuya fecha_limite es en las próximas 48 horas.
    $sql_vencimiento = "
        SELECT id_tarea, titulo, fecha_limite, prioridad
        FROM tareas
        WHERE estado IN ('pendiente', 'en_proceso', 'modificacion_pendiente')
          AND fecha_limite IS NOT NULL
          AND fecha_limite <= DATE_ADD(NOW(), INTERVAL 48 HOUR)
        ORDER BY fecha_limite ASC
        LIMIT 5
    ";
    $stmt_vencimiento = $pdo->prepare($sql_vencimiento);
    $stmt_vencimiento->execute();
    $tareas_proximo_vencimiento = $stmt_vencimiento->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear fechas para la vista
    $tareas_proximo_vencimiento = array_map(function($tarea) {
        $fecha_limite = new DateTime($tarea['fecha_limite']);
        $hoy = new DateTime();
        $manana = new DateTime('+1 day');

        if ($fecha_limite->format('Y-m-d') == $hoy->format('Y-m-d')) {
            $limite_formateado = 'Hoy, ' . $fecha_limite->format('H:i');
        } elseif ($fecha_limite->format('Y-m-d') == $manana->format('Y-m-d')) {
            $limite_formateado = 'Mañana, ' . $fecha_limite->format('H:i');
        } else {
            $limite_formateado = $fecha_limite->format('D, H:i'); // Muestra día de la semana
        }
        $tarea['limite'] = $limite_formateado;
        return $tarea;
    }, $tareas_proximo_vencimiento);


    // --- 9. Gráfico: Uso de Recursos ---
    // Esto requiere una tabla de 'recursos' y 'uso_recursos'. 
    // Como no tenemos esas tablas, MANTENEMOS ESTO COMO EJEMPLO (MOCK) pero bien estructurado.
    $uso_recursos_data_raw = [
        ['recurso' => 'Vehículo A', 'uso' => 85],
        ['recurso' => 'Vehículo B', 'uso' => 50],
        ['recurso' => 'Vehículo C', 'uso' => 95],
    ];
    $chart_labels_recurso = json_encode(array_column($uso_recursos_data_raw, 'recurso'));
    $chart_data_recurso = json_encode(array_column($uso_recursos_data_raw, 'uso'));
    $chart_colors_recurso = json_encode(['#198754', '#0dcaf0', '#dc3545']); 


    // --- 10. Gráfico: Tendencia Tareas Nuevas vs. Cerradas ---
    // Obtener datos por semana (últimas 4 semanas)
    $chart_labels_trend = [];
    $chart_data_trend_new = [];
    $chart_data_trend_closed = [];

    // Lógica para generar las últimas 4 semanas y hacer la consulta
    for ($i = 3; $i >= 0; $i--) {
        // Calcular inicio y fin de la semana $i (Semana 0 = actual, Semana 3 = hace 3 semanas)
        $start_week = date('Y-m-d', strtotime("monday -{$i} weeks"));
        $end_week = date('Y-m-d', strtotime("sunday -{$i} weeks"));
        
        $chart_labels_trend[] = "Semana " . date('W', strtotime($start_week)); // W = número de semana ISO

        // Consulta para Tareas Creadas en la semana
        $sql_new = "SELECT COUNT(*) FROM tareas WHERE fecha_creacion BETWEEN :start_w AND :end_w";
        $stmt_new = $pdo->prepare($sql_new);
        $stmt_new->execute([':start_w' => $start_week, ':end_w' => $end_week]);
        $chart_data_trend_new[] = $stmt_new->fetchColumn();

        // Consulta para Tareas Cerradas (verificada_admin) en la semana
        $sql_closed = "SELECT COUNT(*) FROM tareas WHERE estado = 'verificada_admin' AND fecha_finalizacion BETWEEN :start_w AND :end_w";
        $stmt_closed = $pdo->prepare($sql_closed);
        $stmt_closed->execute([':start_w' => $start_week, ':end_w' => $end_week]);
        $chart_data_trend_closed[] = $stmt_closed->fetchColumn();
    }
    
    $chart_labels_trend = json_encode($chart_labels_trend);
    $chart_data_trend_new = json_encode($chart_data_trend_new);
    $chart_data_trend_closed = json_encode($chart_data_trend_closed);
    
    
    // --- PREPARACIÓN DE DATOS PARA GRÁFICOS INICIALES ---
    // 1. Gráfico de Pastel (Resumen de Estados)
    $resumen_completo = [
        'pendiente' => $resumen['pendiente'], 
        'en_proceso' => $resumen['en_proceso'], 
        'revision' => $resumen['finalizada_tecnico'] + $resumen['modificacion_pendiente'], 
        'verificada_admin' => $resumen['verificada_admin'], 
    ];

    $chart_labels_status = json_encode(['Pendientes', 'En Proceso', 'En Revisión', 'Verificadas']);
    $chart_data_status = json_encode([
        $resumen_completo['pendiente'], 
        $resumen_completo['en_proceso'], 
        $resumen_completo['revision'], 
        $resumen_completo['verificada_admin']
    ]);
    
    // 2. Gráfico de Barras (Distribución por Prioridad)
    $sql_prioridad = "
        SELECT prioridad, COUNT(*) as total 
        FROM tareas t
        " . $filtro_sql . "
        GROUP BY prioridad
        ORDER BY FIELD(prioridad, 'urgente', 'alta', 'media', 'baja')
    ";
    $stmt_prioridad = $pdo->prepare($sql_prioridad);
    $stmt_prioridad->execute($params);
    $prioridad_data_raw = $stmt_prioridad->fetchAll(PDO::FETCH_ASSOC);

    $prioridad_map = [
        'urgente' => 0, 'alta' => 0, 'media' => 0, 'baja' => 0
    ];
    foreach ($prioridad_data_raw as $row) {
        if (isset($prioridad_map[$row['prioridad']])) {
            $prioridad_map[$row['prioridad']] = $row['total'];
        }
    }

    $chart_labels_priority = json_encode(['Urgente', 'Alta', 'Media', 'Baja']);
    $chart_data_priority = json_encode(array_values($prioridad_map));
    $chart_colors_priority = json_encode([
        '#dc3545', '#ffc107', '#0dcaf0', '#198754' 
    ]);
    
    // --- DATOS PARA FILTROS AVANZADOS ---
    // Obtener todas las categorías para el filtro
    $sql_categorias = "SELECT id_categoria, nombre FROM categorias ORDER BY nombre ASC";
    $stmt_categorias = $pdo->prepare($sql_categorias);
    $stmt_categorias->execute();
    $categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener todos los usuarios (técnicos/empleados) para el filtro
    $sql_usuarios = "SELECT id_usuario, nombre_completo, rol FROM usuarios WHERE rol = 'empleado' ORDER BY nombre_completo ASC";
    $stmt_usuarios = $pdo->prepare($sql_usuarios);
    $stmt_usuarios->execute();
    $usuarios_empleados = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);
    

} catch (PDOException $e) {
    // Manejo de error de conexión o consulta a la BD
    error_log("Error de BD en Dashboard: " . $e->getMessage());
    // Establecer datos vacíos para evitar errores fatales en la vista
    $contenido_principal = 'Error al cargar el resumen de tareas. Contacte al administrador.';
    $resumen = array_fill_keys(array_keys($resumen), 0);
    $sla_porcentaje = 0;
    $tiempo_promedio = 'Error';
    // Se recomienda detener la ejecución o mostrar un mensaje claro al usuario
}

// Lógica de redirección específica para la tarjeta de Revisión
$revision_link = ($rol_usuario === 'admin') 
    ? "tareas_lista.php?estado=finalizada_tecnico" 
    : "tareas_lista.php?estado=modificacion_pendiente";

// (8. KPI: Tareas Pendientes de Aprobación) es $resumen['finalizada_tecnico']
// (6. Mapa de Ubicaciones Críticas) es una tarjeta placeholder
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gestión Logística</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> 
    <style>
        .card { transition: transform 0.2s; }
        .card:hover { transform: translateY(-3px); }
        .alert-heading i { margin-right: 10px; }
        
        /* Contenedor para que los gráficos tengan un tamaño consistente */
        .chart-container {
            position: relative; 
            width: 100%;
            height: 300px; 
            margin: auto;
        }
        
        /* Estilos específicos para el indicador de SLA (1) */
        .progress-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
            /* La variable PHP $sla_porcentaje se usa aquí */
            background: conic-gradient(
                #198754 0% <?php echo $sla_porcentaje; ?>%, 
                #e9ecef <?php echo $sla_porcentaje; ?>% 100%
            );
        }
        .progress-circle::after {
            content: '';
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: white;
            position: absolute;
        }
        .progress-circle span {
            position: relative;
            z-index: 1;
            font-weight: bold;
            color: #212529;
        }
        
        /* Estilos para Avisos (para evitar que las imágenes rompan el layout) */
        .aviso-content img {
            max-width: 100%; 
            height: auto !important; 
            display: block;
            margin: 10px 0; 
        }

    </style>
</head>
<body>
    <?php include 'navbar.php'; ?> 

    <div class="container-fluid mt-4">
        
        <div class="alert alert-primary shadow-sm" role="alert">
            <h4 class="alert-heading mb-2"><i class="fas fa-hand-paper"></i> <?php echo $saludo; ?>, <?php echo htmlspecialchars($nombre_usuario); ?>.</h4>
            
            <h5 class="mb-0 text-muted fst-italic">
                <i class="fas fa-quote-left me-2"></i><?php echo htmlspecialchars($frase_del_dia); ?>
            </h5>
            
        </div>

        <h2 class="mt-4 mb-3">Resumen Rápido por Estado</h2>
        <div class="row">
            
            <div class="col-md-6 col-lg-3 mb-4">
                <a href="tareas_lista.php?estado=pendiente" class="text-decoration-none">
                    <div class="card bg-danger text-white shadow">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-3"><i class="fas fa-clock fa-2x"></i></div>
                                <div class="col-9 text-end">
                                    <h5 class="text-uppercase mb-0 small">Pendientes</h5>
                                    <h1 class="display-4"><?php echo $resumen['pendiente']; ?></h1>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="col-md-6 col-lg-3 mb-4">
                <a href="tareas_lista.php?estado=en_proceso" class="text-decoration-none">
                    <div class="card bg-primary text-white shadow">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-3"><i class="fas fa-play-circle fa-2x"></i></div>
                                <div class="col-9 text-end">
                                    <h5 class="text-uppercase mb-0 small">En Proceso</h5>
                                    <h1 class="display-4"><?php echo $resumen['en_proceso']; ?></h1>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="col-md-6 col-lg-3 mb-4">
                <a href="<?php echo $revision_link; ?>" class="text-decoration-none">
                    <div class="card bg-info text-white shadow">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-3"><i class="fas fa-share-square fa-2x"></i></div>
                                <div class="col-9 text-end">
                                    <h5 class="text-uppercase mb-0 small">Revisión</h5>
                                    <h1 class="display-4"><?php echo $resumen['finalizada_tecnico'] + $resumen['modificacion_pendiente']; ?></h1>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="col-md-6 col-lg-3 mb-4">
                <a href="tareas_lista.php?estado=verificada_admin" class="text-decoration-none">
                    <div class="card bg-success text-white shadow">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-3"><i class="fas fa-clipboard-check fa-2x"></i></div>
                                <div class="col-9 text-end">
                                    <h5 class="text-uppercase mb-0 small">Verificadas</h5>
                                    <h1 class="display-4"><?php echo $resumen['verificada_admin']; ?></h1>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div> 
        
        <h2 class="mt-4 mb-3"><i class="fas fa-tachometer-alt me-2"></i>Métricas de Rendimiento Clave</h2>
        <div class="row">
            
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card shadow border-success h-100">
                    <div class="card-body text-center">
                        <h5 class="text-success text-uppercase small mb-3">Tasa de Cumplimiento (SLA)</h5>
                        <div class="progress-circle mx-auto mb-3">
                            <span class="fs-4"><?php echo $sla_porcentaje; ?>%</span>
                        </div>
                        <p class="card-text small text-muted">Tareas finalizadas a tiempo (últimos 30 días).</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card shadow border-primary h-100">
                    <div class="card-body text-center">
                         <h5 class="text-primary text-uppercase small mb-3">Tiempo Medio de Resolución (TMR)</h5>
                         <i class="fas fa-hourglass-half fa-3x text-primary mb-3"></i>
                         <h1 class="display-4 mb-0"><?php echo htmlspecialchars($tiempo_promedio); ?></h1>
                         <p class="card-text small text-muted">Promedio desde la asignación hasta la verificación final.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 mb-4">
                <a href="tareas_lista.php?estado=finalizada_tecnico" class="text-decoration-none">
                    <div class="card shadow border-warning h-100">
                        <div class="card-body text-center">
                            <h5 class="text-warning text-uppercase small mb-3">Pendientes de Aprobación</h5>
                            <i class="fas fa-user-check fa-3x text-warning mb-3"></i>
                            <h1 class="display-4 mb-0"><?php echo $resumen['finalizada_tecnico']; ?></h1>
                            <p class="card-text small text-muted">Tareas listas para ser verificadas por el administrador.</p>
                        </div>
                    </div>
                </a>
            </div>
        </div>
        
        <h2 class="mt-4 mb-3"><i class="fas fa-chart-area me-2"></i>Análisis de Carga y Distribución</h2>
        <div class="row mt-4 mb-4">
            
            <div class="col-lg-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-secondary text-white"><i class="fas fa-chart-pie"></i> Distribución de Tareas por Estado</div>
                    <div class="card-body d-flex justify-content-center">
                         <div class="chart-container">
                            <canvas id="tareasPieChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-secondary text-white"><i class="fas fa-chart-bar"></i> Distribución de Tareas por Prioridad</div>
                    <div class="card-body d-flex justify-content-center">
                        <div class="chart-container">
                            <canvas id="prioridadBarChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-dark text-white"><i class="fas fa-users-cog"></i> Carga de Trabajo por Técnico (Activas)</div>
                    <div class="card-body d-flex justify-content-center">
                        <div class="chart-container">
                            <canvas id="cargaBarChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-dark text-white"><i class="fas fa-tags"></i> Distribución de Tareas por Categoría (Activas)</div>
                    <div class="card-body d-flex justify-content-center">
                        <div class="chart-container">
                            <canvas id="categoriaPieChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
        
        <div class="row mt-4 mb-4">
            
            <div class="col-lg-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-success text-white"><i class="fas fa-line-chart"></i> Tendencia: Nuevas vs. Cerradas (Últimas 4 Semanas)</div>
                    <div class="card-body d-flex justify-content-center">
                        <div class="chart-container">
                            <canvas id="trendLineChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark"><i class="fas fa-dolly"></i> Nivel de Uso de Recursos Clave (%)</div>
                    <div class="card-body d-flex flex-column justify-content-center align-items-center text-center">
                         <div class="chart-container">
                            <canvas id="recursoBarChart"></canvas>
                        </div>
                        <p class="small text-muted mt-2">
                           *Nota: Este gráfico es un ejemplo (mock) y necesita una tabla `recursos` real para datos automáticos.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <h2 class="mt-4 mb-3"><i class="fas fa-list-alt me-2"></i>Alertas y Actividad</h2>
        <div class="row">
            
            <div class="col-lg-4 mb-4">
                <div class="card shadow border-danger h-100">
                    <div class="card-header bg-danger text-white"><i class="fas fa-calendar-times"></i> Tareas Próximo Vencimiento</div>
                    <ul class="list-group list-group-flush">
                        <?php if (count($tareas_proximo_vencimiento) > 0): ?>
                            <?php foreach ($tareas_proximo_vencimiento as $tarea): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <a href="tarea_ver.php?id=<?php echo $tarea['id_tarea']; ?>">#<?php echo htmlspecialchars($tarea['id_tarea']); ?> - <?php echo htmlspecialchars($tarea['titulo']); ?></a>
                                        <small class="d-block text-muted">Límite: <?php echo htmlspecialchars($tarea['limite']); ?></small>
                                    </div>
                                    <span class="badge <?php echo getPrioridadClassDashboard($tarea['prioridad']); ?>">
                                        <?php echo ucfirst($tarea['prioridad']); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="list-group-item text-center text-muted">No hay tareas con vencimiento próximo.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="col-lg-4 mb-4">
                <div class="card shadow border-dark h-100">
                    <div class="card-header bg-dark text-white"><i class="fas fa-history"></i> Actividad Reciente del Sistema</div>
                    <ul class="list-group list-group-flush">
                        <?php if (count($actividad_reciente) > 0): ?>
                            <?php foreach ($actividad_reciente as $actividad): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="me-2">
                                        <i class="<?php echo $actividad['icono']; ?> me-2"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <p class="mb-0 small"><?php echo htmlspecialchars($actividad['mensaje']); ?></p>
                                    </div>
                                    <span class="badge bg-light text-muted ms-2"><?php echo htmlspecialchars($actividad['hora']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="list-group-item text-center text-muted">No hay actividad reciente registrada.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <div class="col-lg-4 mb-4">
                <div class="card shadow border-warning h-100">
                    <div class="card-header bg-warning text-dark"><i class="fas fa-map-marker-alt"></i> Mapa de Ubicaciones Críticas</div>
                    <div class="card-body d-flex flex-column justify-content-center align-items-center text-center">
                        <i class="fas fa-map fa-3x text-warning mb-3"></i>
                        <p class="card-text small text-muted">
                           Aquí se mostraría un mapa o lista de las 5 tareas Urgentes más críticas con información de geolocalización.
                        </p>
                        <a href="#" class="btn btn-sm btn-outline-warning mt-2">Ver Mapa Logístico</a>
                    </div>
                </div>
            </div>

        </div>

        <div class="row mt-4">
            
            <div class="col-lg-7 mb-4">
                
                <?php if ($rol_usuario === 'admin'): ?>
                    <div class="card shadow border-info mb-4">
                        <div class="card-header bg-info text-white"><i class="fas fa-clipboard-list"></i> Tareas Pendientes de Verificación (<?php echo count($tareas_admin_verificar); ?>)</div>
                        <ul class="list-group list-group-flush">
                            <?php if (count($tareas_admin_verificar) > 0): ?>
                                <?php foreach ($tareas_admin_verificar as $tarea): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <a href="tarea_ver.php?id=<?php echo $tarea['id_tarea']; ?>">#<?php echo htmlspecialchars($tarea['id_tarea']); ?> - <?php echo htmlspecialchars($tarea['titulo']); ?></a>
                                            <small class="d-block text-muted">Técnico: <?php echo htmlspecialchars($tarea['tecnico_nombre']); ?></small>
                                        </div>
                                        <span class="badge bg-secondary">Finalizada: <?php echo date('H:i', strtotime($tarea['fecha_finalizacion'])); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="list-group-item text-center text-muted">No hay tareas pendientes de su verificación.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <div class="card shadow border-danger mb-4">
                        <div class="card-header bg-danger text-white"><i class="fas fa-exclamation-triangle"></i> Alerta de Tareas Atrasadas (<?php echo count($tareas_admin_atrasadas); ?>)</div>
                        <ul class="list-group list-group-flush">
                            <?php if (count($tareas_admin_atrasadas) > 0): ?>
                                <?php foreach ($tareas_admin_atrasadas as $tarea): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <a href="tarea_ver.php?id=<?php echo $tarea['id_tarea']; ?>" class="text-danger">#<?php echo htmlspecialchars($tarea['id_tarea']); ?> - <?php echo htmlspecialchars($tarea['titulo']); ?></a>
                                            <small class="d-block text-muted">Asignado: <?php echo htmlspecialchars($tarea['asignado_nombre']); ?></small>
                                        </div>
                                        <span class="badge bg-dark">Vencida: <?php echo date('d/m/Y', strtotime($tarea['fecha_limite'])); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="list-group-item text-center text-muted">¡Buen trabajo! No hay tareas con fecha límite vencida.</li>
                            <?php endif; ?>
                        </ul>
                    </div>

                     <div class="card shadow mb-4">
                        <div class="card-header bg-secondary text-white"><i class="fas fa-chart-bar"></i> Métricas de Verificación (Últimos 30 días)</div>
                        <div class="card-body">
                            <h6 class="card-title">Eficiencia de Verificación</h6>
                            <div class="row">
                                <div class="col-6 text-center">
                                    <h3 class="text-success"><?php echo $eficiencia_porcentaje; ?>%</h3>
                                    <p class="small text-success mb-1">Aprobadas (<?php echo $aprobadas; ?>)</p>
                                </div>
                                <div class="col-6 text-center">
                                    <h3 class="text-danger"><?php echo 100 - $eficiencia_porcentaje; ?>%</h3>
                                    <p class="small text-danger mb-1">Rechazadas (<?php echo $rechazadas; ?>)</p>
                                </div>
                            </div>
                        </div>
                    </div>               


                    <div class="card shadow mb-4">
                        <div class="card-header bg-dark text-white">
                            <i class="fas fa-filter me-2"></i> Tareas Completadas por Periodo y Filtro
                        </div>
                        <div class="card-body">
                            <div class="row g-3 mb-4 align-items-end">
                                <div class="col-md-3">
                                    <label for="timeRange" class="form-label small">Rango de Tiempo</label>
                                    <select class="form-select" id="timeRange">
                                        <option value="week">Últimos 7 días</option>
                                        <option value="month" selected>Últimos 30 días</option>
                                        <option value="year">Último Año</option>
                                    </select>
                                </div>
                                <?php if ($rol_usuario === 'admin'): ?>
                                    <div class="col-md-4">
                                        <label for="technicianFilter" class="form-label small">Filtrar por Técnico</label>
                                        <select class="form-select" id="technicianFilter">
                                            <option value="all" selected>Todos los Técnicos</option>
                                            <?php foreach ($usuarios_empleados as $user): ?>
                                                <option value="<?php echo $user['id_usuario']; ?>"><?php echo htmlspecialchars($user['nombre_completo']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                                <div class="col-md-4">
                                    <label for="categoryFilter" class="form-label small">Filtrar por Categoría</label>
                                    <select class="form-select" id="categoryFilter">
                                        <option value="all" selected>Todas las Categorías</option>
                                        <?php foreach ($categorias as $cat): ?>
                                            <option value="<?php echo $cat['id_categoria']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <button class="btn btn-primary w-100" id="applyFiltersBtn"><i class="fas fa-redo"></i></button>
                                </div>
                            </div>
                            
                            <div class="chart-container">
                                 <canvas id="advancedBarChart"></canvas>
                            </div>
                            <div class="text-center mt-3" id="loadingAdvancedChart" style="display:none;">
                                <i class="fas fa-spinner fa-spin me-2"></i> Cargando datos...
                            </div>
                             <div class="text-center mt-3 alert alert-danger" id="errorAdvancedChart" style="display:none;">
                                Error al cargar la estadística avanzada.
                            </div>
                            
                        </div>
                    </div>

                <?php elseif ($rol_usuario === 'empleado'): ?>
                    <div class="card shadow border-danger">
                        <div class="card-header bg-danger text-white"><i class="fas fa-bolt"></i> Mis Tareas Más Urgentes (<?php echo count($tareas_empleado_urgentes); ?>)</div>
                        <ul class="list-group list-group-flush">
                            <?php if (count($tareas_empleado_urgentes) > 0): ?>
                                <?php foreach ($tareas_empleado_urgentes as $tarea): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <a href="tarea_ver.php?id=<?php echo $tarea['id_tarea']; ?>">#<?php echo htmlspecialchars($tarea['id_tarea']); ?> - <?php echo htmlspecialchars($tarea['titulo']); ?></a>
                                            <small class="d-block text-muted">Límite: <?php echo $tarea['fecha_limite'] ? date('d/m/Y', strtotime($tarea['fecha_limite'])) : 'N/A'; ?></small>
                                        </div>
                                        <span class="badge <?php echo getPrioridadClassDashboard($tarea['prioridad']); ?>">
                                            <?php echo ucfirst($tarea['prioridad']); ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="list-group-item text-center text-muted">No tienes tareas urgentes o pendientes en este momento.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-5 mb-4">
                
                <div class="card shadow mb-4">
                    <div class="card-header bg-dark text-white"><i class="fas fa-bullhorn"></i> Avisos y Noticias Internas</div>
                    <div class="card-body">
                        <?php if (count($avisos_activos) > 0): ?>
                            <?php foreach ($avisos_activos as $aviso): ?>
                                <div class="aviso-display-item"> <h6 class="card-title text-primary"><?php echo htmlspecialchars($aviso['titulo']); ?></h6>
                                    
                                    <div class="aviso-content small mb-1">
                                        <?php echo $aviso['contenido']; ?>
                                    </div>
                                    
                                    <p class="small text-muted border-bottom pb-2 mb-2 pt-2">Publicado por: <?php echo htmlspecialchars($aviso['nombre_completo']); ?></p>
                                </div>
                            <?php endforeach; ?>
                            <a href="#" class="btn btn-sm btn-outline-dark">Ver Historial de Avisos (Pendiente)</a>
                        <?php else: ?>
                            <p class="card-text small text-muted text-center">No hay avisos internos activos en este momento. (Recuerde crear la tabla `avisos` y la página de administración).</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                
                
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Declaración global para los gráficos avanzados/nuevos
        let advancedBarChart; 
        let cargaBarChart;
        let categoriaPieChart;
        let trendLineChart;
        let recursoBarChart;

        document.addEventListener('DOMContentLoaded', function() {
            
            // --------------------------------------------------------
            // --- LÓGICA DE GRÁFICOS INICIALES (ESTADO Y PRIORIDAD) ---
            // --------------------------------------------------------
            
            // --- GRÁFICO DE PASTEL (ESTADOS) ---
            const ctxPie = document.getElementById('tareasPieChart');
            
            const labelsStatus = <?php echo $chart_labels_status; ?>;
            const dataStatus = <?php echo $chart_data_status; ?>;

            if (dataStatus.some(val => val > 0)) {
                new Chart(ctxPie, {
                    type: 'pie', 
                    data: {
                        labels: labelsStatus,
                        datasets: [{
                            data: dataStatus,
                            backgroundColor: [
                                '#dc3545', 
                                '#0d6efd', 
                                '#0dcaf0', 
                                '#198754'  
                            ],
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false, 
                        plugins: {
                            legend: {
                                position: 'bottom',
                            },
                            title: {
                                display: false,
                            }
                        }
                    }
                });
            } else {
                ctxPie.closest('.card-body').innerHTML = '<p class="text-center text-muted">No hay datos de tareas para mostrar.</p>';
            }

            // --- GRÁFICO DE BARRAS (PRIORIDAD) ---
            const ctxBar = document.getElementById('prioridadBarChart');
            
            const labelsPriority = <?php echo $chart_labels_priority; ?>;
            const dataPriority = <?php echo $chart_data_priority; ?>;
            const colorsPriority = <?php echo $chart_colors_priority; ?>;

            if (dataPriority.some(val => val > 0)) {
                new Chart(ctxBar, {
                    type: 'bar', 
                    data: {
                        labels: labelsPriority,
                        datasets: [{
                            label: 'Número de Tareas',
                            data: dataPriority,
                            backgroundColor: colorsPriority, 
                            borderColor: colorsPriority.map(color => color + 'dd'), 
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false, 
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Cantidad'
                                },
                                ticks: { precision: 0 }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Prioridad'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            title: {
                                display: false,
                            }
                        }
                    }
                });
            } else {
                ctxBar.closest('.card-body').innerHTML = '<p class="text-center text-muted">No hay tareas pendientes o en proceso para mostrar prioridad.</p>';
            }
            
            // --------------------------------------------------------
            // --- LÓGICA DE NUEVOS GRÁFICOS (3, 5, 9, 10) ---
            // --------------------------------------------------------

            // --- 3. GRÁFICO: Carga de Trabajo por Técnico (REAL) ---
            const ctxCarga = document.getElementById('cargaBarChart');
            const labelsCarga = <?php echo $chart_labels_carga; ?>;
            const dataCarga = <?php echo $chart_data_carga; ?>;

            if (dataCarga.some(val => val > 0)) {
                cargaBarChart = new Chart(ctxCarga, {
                    type: 'horizontalBar', 
                    data: {
                        labels: labelsCarga,
                        datasets: [{
                            label: 'Tareas Activas',
                            data: dataCarga,
                            backgroundColor: 'rgba(255, 99, 132, 0.6)', 
                            borderColor: 'rgba(255, 99, 132, 1)', 
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false, 
                        scales: {
                            x: {
                                beginAtZero: true,
                                title: { display: true, text: 'Cantidad de Tareas' },
                                ticks: { precision: 0 }
                            }
                        },
                        plugins: { legend: { display: false } }
                    }
                });
            } else {
                 ctxCarga.closest('.card-body').innerHTML = '<p class="text-center text-muted">No hay tareas activas asignadas a técnicos.</p>';
            }

            // --- 5. GRÁFICO: Distribución de Tareas por Categoría (REAL) ---
            const ctxCategoria = document.getElementById('categoriaPieChart');
            const labelsCategoria = <?php echo $chart_labels_categoria; ?>;
            const dataCategoria = <?php echo $chart_data_categoria; ?>;
            
            if (dataCategoria.some(val => val > 0)) {
                categoriaPieChart = new Chart(ctxCategoria, {
                    type: 'doughnut', 
                    data: {
                        labels: labelsCategoria,
                        datasets: [{
                            data: dataCategoria,
                            backgroundColor: [
                                '#ffc107', 
                                '#0dcaf0', 
                                '#198754', 
                                '#0d6efd',
                                '#dc3545',
                                '#6f42c1'
                            ],
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false, 
                        plugins: { legend: { position: 'bottom' } }
                    }
                });
            } else {
                 ctxCategoria.closest('.card-body').innerHTML = '<p class="text-center text-muted">No hay tareas activas con categorías asignadas.</p>';
            }
            
            // --- 9. GRÁFICO: Uso de Recursos (MOCK) ---
            const ctxRecurso = document.getElementById('recursoBarChart');
            const labelsRecurso = <?php echo $chart_labels_recurso; ?>;
            const dataRecurso = <?php echo $chart_data_recurso; ?>;
            const colorsRecurso = <?php echo $chart_colors_recurso; ?>;
            
            recursoBarChart = new Chart(ctxRecurso, {
                type: 'bar', 
                data: {
                    labels: labelsRecurso,
                    datasets: [{
                        label: '% de Uso (Capacidad)',
                        data: dataRecurso,
                        backgroundColor: colorsRecurso, 
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false, 
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: { display: true, text: 'Porcentaje (%)' },
                            ticks: { precision: 0 }
                        }
                    },
                    plugins: { legend: { display: false } }
                }
            });


            // --- 10. GRÁFICO: Tendencia Tareas Nuevas vs. Cerradas (REAL) ---
            const ctxTrend = document.getElementById('trendLineChart');
            const labelsTrend = <?php echo $chart_labels_trend; ?>;
            const dataTrendNew = <?php echo $chart_data_trend_new; ?>;
            const dataTrendClosed = <?php echo $chart_data_trend_closed; ?>;

            if (dataTrendNew.some(val => val > 0) || dataTrendClosed.some(val => val > 0)) {
                trendLineChart = new Chart(ctxTrend, {
                    type: 'line', 
                    data: {
                        labels: labelsTrend,
                        datasets: [{
                            label: 'Tareas Creadas',
                            data: dataTrendNew,
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13, 110, 253, 0.1)',
                            fill: true,
                            tension: 0.1
                        },
                        {
                            label: 'Tareas Cerradas',
                            data: dataTrendClosed,
                            borderColor: '#198754',
                            backgroundColor: 'rgba(25, 135, 84, 0.1)',
                            fill: true,
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false, 
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Cantidad' },
                                ticks: { precision: 0 }
                            }
                        },
                        plugins: { legend: { position: 'top' } }
                    }
                });
            } else {
                 ctxTrend.closest('.card-body').innerHTML = '<p class="text-center text-muted">No hay datos de tendencias de tareas en las últimas 4 semanas.</p>';
            }
            
            // --------------------------------------------------------
            // --- LÓGICA DE CLIMA (Pronóstico Logístico) ---
            // --------------------------------------------------------
            
             function fetchWeather(lat, lon) {
                const tempEl = document.getElementById('weatherTemp');
                const descEl = document.getElementById('weatherDesc');
                const locEl = document.getElementById('weatherLocation');
                
                tempEl.innerHTML = '<i class="fas fa-sync fa-spin text-primary me-2"></i> Cargando...';
                descEl.textContent = 'Obteniendo datos de la API de Clima...';

                // ** NOTA: Necesitas crear el archivo fetch_weather.php **
                fetch(`fetch_weather.php?lat=${lat}&lon=${lon}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Actualizar el body de la tarjeta
                            tempEl.innerHTML = `<i class="${data.icon} me-2"></i> ${data.temp}`;
                            descEl.textContent = `Condición: ${data.desc}`;
                            locEl.textContent = data.location;
                        } else {
                            // Mostrar mensaje de error de la API
                            tempEl.innerHTML = `<i class="${data.icon} me-2"></i> N/A`;
                            descEl.textContent = `Error: ${data.desc}`;
                            locEl.textContent = data.location;
                        }
                    })
                    .catch(error => {
                        console.error('Error al obtener el clima:', error);
                        // Mensaje de error genérico
                        tempEl.innerHTML = '<i class="fas fa-exclamation-circle text-danger me-2"></i> N/A';
                        descEl.textContent = 'Fallo de conexión al servicio de clima.';
                        locEl.textContent = 'Error de Red';
                    });
            }

            function getLocation() {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            fetchWeather(position.coords.latitude, position.coords.longitude);
                        },
                        (error) => {
                            // En caso de error o denegación de permiso, usar una ubicación por defecto (Ej: Buenos Aires)
                            console.warn(`Error al obtener la ubicación: ${error.message}. Usando ubicación por defecto.`);
                            fetchWeather('-34.6037', '-58.3816'); // Coordenadas de Buenos Aires
                        }
                    );
                } else {
                    console.warn("Geolocalización no soportada por el navegador. Usando ubicación por defecto.");
                    fetchWeather('-34.6037', '-58.3816');
                }
            }
            
            // Iniciar la carga del clima
            getLocation();
            
            // --------------------------------------------------------
            // --- LÓGICA DE GRÁFICO AVANZADO CON FILTROS ---
            // --------------------------------------------------------
            
            const applyFiltersBtn = document.getElementById('applyFiltersBtn');
            const timeRangeSelect = document.getElementById('timeRange');
            const technicianFilterSelect = document.getElementById('technicianFilter');
            const categoryFilterSelect = document.getElementById('categoryFilter');
            const loadingIndicator = document.getElementById('loadingAdvancedChart');
            const errorIndicator = document.getElementById('errorAdvancedChart');
            const ctxAdvanced = document.getElementById('advancedBarChart');
            
            // Función para inicializar el Gráfico Avanzado (solo se llama una vez)
            function initAdvancedChart() {
                advancedBarChart = new Chart(ctxAdvanced, {
                    type: 'bar',
                    data: {
                        labels: [], // Se llenará con las fechas
                        datasets: [{
                            label: 'Tareas Verificadas',
                            data: [],
                            backgroundColor: 'rgba(54, 162, 235, 0.7)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Cantidad de Tareas' },
                                ticks: { precision: 0 } // Asegurar números enteros
                            },
                            x: {
                                title: { display: true, text: 'Periodo' }
                            }
                        },
                        plugins: {
                            legend: { display: true, position: 'top' },
                            title: { display: false }
                        }
                    }
                });
            }
            
            // Función principal para cargar los datos del gráfico avanzado
            function loadAdvancedChartData() {
                const range = timeRangeSelect.value;
                const technicianId = technicianFilterSelect ? technicianFilterSelect.value : 'all';
                const categoryId = categoryFilterSelect.value;

                loadingIndicator.style.display = 'block';
                errorIndicator.style.display = 'none';
                
                // ** NOTA: Necesitas crear el archivo fetch_advanced_stats.php **
                const url = `fetch_advanced_stats.php?range=${range}&technician=${technicianId}&category=${categoryId}`;
                
                fetch(url)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`Error HTTP: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        loadingIndicator.style.display = 'none';

                        if (data.success) {
                            // Actualizar el gráfico
                            advancedBarChart.data.labels = data.labels;
                            advancedBarChart.data.datasets[0].data = data.data;
                            advancedBarChart.data.datasets[0].label = `Tareas Verificadas (${range})`;
                            
                            // Ajustar la etiqueta del eje X según el rango
                            let xTitle = 'Día';
                            if (range === 'month') xTitle = 'Semana del Mes';
                            if (range === 'year') xTitle = 'Mes';

                            advancedBarChart.options.scales.x.title.text = xTitle;
                            
                            advancedBarChart.update();
                        } else {
                            errorIndicator.textContent = data.message || 'No se pudieron obtener los datos.';
                            errorIndicator.style.display = 'block';
                            // Limpiar el gráfico si falla
                            advancedBarChart.data.labels = [];
                            advancedBarChart.data.datasets[0].data = [];
                            advancedBarChart.update();
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching advanced stats:', error);
                        loadingIndicator.style.display = 'none';
                        errorIndicator.textContent = 'Error de conexión o servidor.';
                        errorIndicator.style.display = 'block';
                    });
            }

            // Inicializar el gráfico al cargar la página
            initAdvancedChart();
            
            // Aplicar filtros al cargar (se seleccionó 'month' por defecto)
            // Esto fallará hasta que 'fetch_advanced_stats.php' exista.
            loadAdvancedChartData(); 
            
            // Event listener para el botón de aplicar filtros
            applyFiltersBtn.addEventListener('click', loadAdvancedChartData);
        });
    </script>
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer"></div>
</body>
</html>