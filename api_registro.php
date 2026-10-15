<?php
require_once 'api_comun.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['error' => 'Método no permitido'], 405);
}

$in       = datos_entrada();
$nombre   = trim($in['nombre'] ?? '');
$correo   = mb_strtolower(trim($in['correo'] ?? ''));
$password = (string)($in['password'] ?? '');

if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100) {
    responder(['error' => 'Escribe tu nombre (mínimo 2 letras).'], 422);
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 150) {
    responder(['error' => 'Escribe un correo válido.'], 422);
}
if (strlen($password) < 8 || strlen($password) > 72) {
    responder(['error' => 'La contraseña debe tener entre 8 y 72 caracteres.'], 422);
}

$existe = consultar($conexion, 'SELECT 1 FROM usuarios WHERE correo = ? OR usuario = ?', 'ss', $correo, $correo);
if ($existe->get_result()->fetch_row()) {
    responder(['error' => 'Ya existe una cuenta con ese correo.'], 409);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
try {
    consultar($conexion,
        "INSERT INTO usuarios (nombre, usuario, correo, password, rol) VALUES (?, ?, ?, ?, 'lector')",
        'ssss', $nombre, $correo, $correo, $hash);
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1062) {
        responder(['error' => 'Ya existe una cuenta con ese correo.'], 409);
    }
    throw $e;
}

session_regenerate_id(true);
$_SESSION['id_usuario'] = (int)$conexion->insert_id;
$_SESSION['nombre']     = $nombre;
$_SESSION['rol']        = 'lector';

enviar_correo($correo, 'Bienvenido a la Biblioteca',
    "Hola $nombre,\n\nTu cuenta en la Biblioteca fue creada con este correo.\n"
  . "Ya puedes buscar libros, agregarlos a tu carrito y confirmar tus préstamos.\n\n¡Buena lectura!");

responder(['ok' => true, 'destino' => 'catalogo.php'], 201);
