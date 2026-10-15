<?php
// Código compartido por todos los archivos api_*.php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

// Cualquier error inesperado responde algo genérico (sin mostrar datos internos)
set_exception_handler(function ($e) {
    responder(['error' => 'Error del servidor'], 500);
});

function responder($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

// Exige haber iniciado sesión; si se pasa $rol, exige además ese rol ('lector' o 'admin')
function exigir_rol($rol = null) {
    if (empty($_SESSION['id_usuario'])) {
        responder(['error' => 'Sesión no iniciada'], 401);
    }
    if ($rol !== null && $_SESSION['rol'] !== $rol) {
        responder(['error' => 'No tienes permiso para hacer esto'], 403);
    }
}

// Lee el JSON que manda el navegador con fetch()
function datos_entrada() {
    $d = json_decode(file_get_contents('php://input'), true);
    return is_array($d) ? $d : [];
}

// Recibe una imagen como texto "data:image/jpeg;base64,..." y devuelve los bytes del JPEG (o responde error)
function imagen_desde_datos($dataUrl) {
    $inicio = 'data:image/jpeg;base64,';
    if (!is_string($dataUrl) || strpos($dataUrl, $inicio) !== 0) {
        responder(['error' => 'La imagen no es válida'], 422);
    }
    $bin = base64_decode(substr($dataUrl, strlen($inicio)), true);
    if ($bin === false || strlen($bin) > 400000) {
        responder(['error' => 'La imagen es demasiado grande o no es válida'], 422);
    }
    $info = @getimagesizefromstring($bin);
    if (!$info || $info[2] !== IMAGETYPE_JPEG) {
        responder(['error' => 'La imagen no es válida'], 422);
    }
    return $bin;
}

// Guarda (o reemplaza) la portada de un libro dentro de la base de datos
function guardar_portada($conexion, $idLibro, $bin) {
    $nulo = null;
    $st = $conexion->prepare('UPDATE libros SET portada = ?, portada_v = portada_v + 1 WHERE id_libro = ?');
    $st->bind_param('bi', $nulo, $idLibro);
    $st->send_long_data(0, $bin);
    $st->execute();
}

// Correo desde el que se envían los avisos (cámbialo por uno de tu dominio)
const CORREO_REMITENTE = 'no-responder@monroyb25.atwebpages.com';

// Envía un correo de texto. Devuelve false si el hosting no lo permitió (no detiene nada)
function enviar_correo($para, $asunto, $mensaje) {
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $cabeceras = "From: Biblioteca <" . CORREO_REMITENTE . ">\r\n"
               . "Reply-To: " . CORREO_REMITENTE . "\r\n"
               . "MIME-Version: 1.0\r\n"
               . "Content-Type: text/plain; charset=UTF-8\r\n";
    $asuntoCodificado = '=?UTF-8?B?' . base64_encode($asunto) . '?=';
    return @mail($para, $asuntoCodificado, $mensaje, $cabeceras);
}

// Ejecuta una consulta preparada: consultar($conexion, 'SELECT ... WHERE id = ?', 'i', $id)
function consultar($conexion, $sql, $tipos = '', ...$params) {
    $st = $conexion->prepare($sql);
    if ($tipos !== '') {
        $st->bind_param($tipos, ...$params);
    }
    $st->execute();
    return $st;
}
