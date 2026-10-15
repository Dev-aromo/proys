<?php
session_start();
$destino = (($_SESSION['rol'] ?? '') === 'admin') ? 'personal.php' : 'catalogo.php';
$_SESSION = [];
session_destroy();
header('Location: ' . $destino);
exit;
