<?php
include 'conexion.php';
$q = $_GET['q'] ?? '';
$q = $conexion->real_escape_string($q);

$result = $conexion->query("
  SELECT id, nombre, apellido, dni 
  FROM clientes 
  WHERE dni LIKE '%$q%' OR apellido LIKE '%$q%' OR rfid_uid LIKE '%$q%'
  LIMIT 10
");

$clientes = [];
while ($row = $result->fetch_assoc()) {
  $clientes[] = $row;
}

echo json_encode($clientes);
