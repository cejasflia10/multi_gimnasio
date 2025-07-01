<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

echo "<h2 style='color:gold;background:black;padding:20px;'>🔍 Sesión actual:</h2>";
echo "<pre style='color:lime;background:black;padding:20px;'>";
print_r($_SESSION);
echo "</pre>";

if (!isset($_SESSION['cliente_id'])) {
    echo "<p style='color:red;text-align:center;font-size:20px;'>❌ Acceso denegado</p>";
} else {
    echo "<p style='color:lime;text-align:center;font-size:20px;'>✅ Acceso permitido: cliente ID " . $_SESSION['cliente_id'] . "</p>";
}
?>
