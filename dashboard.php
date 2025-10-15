fede<?php
// Archivo: dashboard.php (VERSIÓN FINAL Y CORREGIDA CON DATOS REALES DE BD)
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

        
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="notificationToastContainer"></div>
</body>
</html>