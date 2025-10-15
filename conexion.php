<?php
// Configuración de la conexión a la base de datos
$servidor = "localhost"; // Servidor de la base de datos (XAMPP)
$usuario = "root";       // Usuario de la base de datos
$password = "";          // Contraseña del usuario (por defecto vacía en XAMPP)
$base_de_datos = "gestion_tareas_logistica"; // Nombre de la BD que creamos

try {
    // Crear una nueva instancia de PDO (PHP Data Objects)
    $pdo = new PDO("mysql:host=$servidor;dbname=$base_de_datos;charset=utf8", $usuario, $password);
    
    // Configurar el modo de error para que PDO lance excepciones
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Opcional: Establecer el modo de fetch por defecto a asociativo
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    //echo "Conexión exitosa"; // Descomenta para probar la conexión
    
} catch (PDOException $e) {
    // Si la conexión falla, se captura la excepción y se muestra un mensaje de error
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
?>