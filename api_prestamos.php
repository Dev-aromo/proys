<?php
require_once 'api_comun.php';
exigir_rol();

// ---------- GET: el admin ve todos los préstamos; el lector, solo los suyos ----------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $base = "SELECT p.id_prestamo, p.id_libro, p.id_usuario, l.titulo, u.nombre AS lector,
                   p.fecha_salida, p.fecha_vence, p.fecha_devolucion,
                   CASE WHEN p.estado = 'devuelto' THEN 'devuelto'
                        WHEN p.fecha_vence < CURDATE() THEN 'vencido'
                        ELSE 'activo' END AS estado
            FROM prestamos p
            JOIN libros l ON l.id_libro = p.id_libro
            JOIN usuarios u ON u.id_usuario = p.id_usuario";
    if ($_SESSION['rol'] === 'admin') {
        $res = $conexion->query("$base ORDER BY p.id_prestamo DESC");
    } else {
        $uid = (int)$_SESSION['id_usuario'];
        $st = $conexion->prepare("$base WHERE p.id_usuario = ? ORDER BY p.id_prestamo DESC");
        $st->bind_param('i', $uid);
        $st->execute();
        $res = $st->get_result();
    }
    responder($res->fetch_all(MYSQLI_ASSOC));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['error' => 'Método no permitido'], 405);
}

$in     = datos_entrada();
$accion = $in['accion'] ?? '';

// ---------- POST accion=devolver: el admin registra una devolución ----------
if ($accion === 'devolver') {
    exigir_rol('admin');
    $id = (int)($in['id_prestamo'] ?? 0);
    $st = $conexion->prepare(
        "UPDATE prestamos SET estado = 'devuelto', fecha_devolucion = CURDATE()
         WHERE id_prestamo = ? AND estado = 'activo'"
    );
    $st->bind_param('i', $id);
    $st->execute();
    if ($st->affected_rows === 0) {
        responder(['error' => 'El préstamo no existe o ya fue devuelto'], 404);
    }
    responder(['ok' => true]);
}

responder(['error' => 'Acción no válida'], 422);
