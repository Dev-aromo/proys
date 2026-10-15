<?php
require_once 'api_comun.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['error' => 'Método no permitido'], 405);
}

$in       = datos_entrada();
$esPersonal = (($in['tipo'] ?? '') === 'personal');
$usuario  = trim($in['usuario'] ?? '');
$password = (string)($in['password'] ?? '');

if ($usuario === '' || $password === '') {
    responder(['error' => 'Por favor completa todos los campos.'], 422);
}

// Los lectores entran con su correo; el personal, con su usuario
if ($esPersonal) {
    $sql = "SELECT id_usuario, nombre, password, rol FROM usuarios WHERE usuario = ? AND rol = 'admin'";
} else {
    $sql = "SELECT id_usuario, nombre, password, rol FROM usuarios WHERE correo = ? AND rol = 'lector'";
    $usuario = mb_strtolower($usuario);
}
$u = consultar($conexion, $sql, 's', $usuario)->get_result()->fetch_assoc();

if (!$u || !password_verify($password, $u['password'])) {
    responder(['error' => $esPersonal ? 'Usuario o contraseña incorrectos.' : 'Correo o contraseña incorrectos.'], 403);
}

session_regenerate_id(true);
$_SESSION['id_usuario'] = (int)$u['id_usuario'];
$_SESSION['nombre']     = $u['nombre'];
$_SESSION['rol']        = $u['rol'];

responder(['ok' => true, 'destino' => $u['rol'] === 'admin' ? 'admin.php' : 'catalogo.php']);
