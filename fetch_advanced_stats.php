<?php
// Archivo: fetch_advanced_stats.php
// Objetivo: Procesar filtros y devolver datos de estadísticas avanzadas en formato JSON.

// 1. Inicializar sesión y conexión a la base de datos
session_start();
// Asegúrate de que 'conexion.php' provea el objeto $pdo
include 'conexion.php'; 

// Asegurar que solo usuarios logueados y con permisos (si aplica) puedan acceder
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit();
}

header('Content-Type: application/json');

// 2. Obtener y sanitizar los parámetros de entrada (filtros)
$groupBy = $_GET['groupBy'] ?? 'week'; // Por defecto: semana
$filterCategory = $_GET['filterCategory'] ?? '';
$filterTechnician = $_GET['filterTechnician'] ?? '';
$id_usuario_actual = $_SESSION['usuario_id'];
$rol_usuario_actual = $_SESSION['usuario_rol'];

// Definir las columnas de agrupamiento según el filtro
switch ($groupBy) {
    case 'day':
        // Agrupar por día (fecha de cierre, si está cerrada) o fecha de creación (si está activa)
        $date_format = "DATE_FORMAT(t.fecha_creacion, '%d-%m-%Y')";
        $group_by_clause = "DATE(t.fecha_creacion)";
        $label_alias = "label_day";
        break;
    case 'month':
        // Agrupar por mes
        $date_format = "DATE_FORMAT(t.fecha_creacion, '%Y-%m')";
        $group_by_clause = "YEAR(t.fecha_creacion), MONTH(t.fecha_creacion)";
        $label_alias = "label_month";
        break;
    case 'week':
    default:
        // Agrupar por semana (por defecto)
        $date_format = "CONCAT('Semana ', WEEK(t.fecha_creacion, 1), ' - ', YEAR(t.fecha_creacion))";
        $group_by_clause = "YEAR(t.fecha_creacion), WEEK(t.fecha_creacion, 1)";
        $label_alias = "label_week";
        break;
}

// 3. Construir la consulta SQL base
// Usamos LEFT JOIN para obtener el nombre de la categoría y del técnico
$sql = "
    SELECT
        {$date_format} AS {$label_alias},
        COUNT(t.id_tarea) AS total_tareas,
        SUM(CASE WHEN t.estado = 'cerrada' THEN 1 ELSE 0 END) AS tareas_cerradas
    FROM 
        tareas t
    WHERE 
        1=1 
";

$params = [];

// Aplicar filtro de categoría
if (!empty($filterCategory)) {
    $sql .= " AND t.id_categoria = :category_id";
    $params[':category_id'] = $filterCategory;
}

// Aplicar filtro de técnico
if (!empty($filterTechnician)) {
    $sql .= " AND t.id_usuario_asignado = :technician_id";
    $params[':technician_id'] = $filterTechnician;
}

// Límite de datos para un período reciente (ejemplo: últimos 3 meses)
// Se puede ajustar según la necesidad. Aquí se usa una ventana de 90 días.
$sql .= " AND t.fecha_creacion >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";

// 4. Aplicar agrupamiento y ordenamiento
$sql .= " 
    GROUP BY 
        {$group_by_clause}
    ORDER BY 
        {$group_by_clause} ASC
";

// 5. Ejecutar la consulta y formatear los resultados
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $dataTotal = [];
    $dataCerradas = [];
    
    foreach ($results as $row) {
        $labels[] = $row[$label_alias];
        $dataTotal[] = (int)$row['total_tareas'];
        $dataCerradas[] = (int)$row['tareas_cerradas'];
    }

    // 6. Devolver la respuesta en formato JSON
    echo json_encode([
        'success' => true,
        'message' => 'Datos de estadísticas avanzados cargados correctamente.',
        'labels' => $labels,
        'dataTotal' => $dataTotal, // Total de tareas creadas en ese período
        'dataCerradas' => $dataCerradas // Tareas cerradas en ese período
    ]);

} catch (PDOException $e) {
    // Manejo de errores de base de datos
    error_log("Error en fetch_advanced_stats: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al ejecutar la consulta de estadísticas.']);
}
?>