<?php
require_once 'api_comun.php';
exigir_rol('lector');

const MAX_PRESTAMOS = 3;      // máximo de libros entre préstamos activos y carrito
const DIAS_PRESTAMO = 7;      // duración de cada préstamo

$uid = (int)$_SESSION['id_usuario'];

function cuantos($conexion, $sql, $tipos, ...$params) {
    return (int)consultar($conexion, $sql, $tipos, ...$params)->get_result()->fetch_row()[0];
}

// ---------- GET: libros del carrito ----------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $st = consultar($conexion,
        "SELECT l.id_libro, l.titulo, l.autor, l.color, (l.portada IS NOT NULL) AS tiene_portada, l.portada_v,
                l.copias - (SELECT COUNT(*) FROM prestamos p
                            WHERE p.id_libro = l.id_libro AND p.estado = 'activo') AS disponibles
         FROM carrito c
         JOIN libros l ON l.id_libro = c.id_libro
         WHERE c.id_usuario = ?
         ORDER BY c.agregado_en, l.titulo", 'i', $uid);
    responder($st->get_result()->fetch_all(MYSQLI_ASSOC));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['error' => 'Método no permitido'], 405);
}

$in     = datos_entrada();
$accion = $in['accion'] ?? '';

// ---------- POST accion=agregar ----------
if ($accion === 'agregar') {
    $libro = (int)($in['id_libro'] ?? 0);
    $l = consultar($conexion, 'SELECT copias FROM libros WHERE id_libro = ?', 'i', $libro)->get_result()->fetch_assoc();
    if (!$l) {
        responder(['error' => 'El libro no existe'], 404);
    }
    if (cuantos($conexion, "SELECT COUNT(*) FROM prestamos WHERE id_libro = ? AND id_usuario = ? AND estado = 'activo'", 'ii', $libro, $uid) > 0) {
        responder(['error' => 'Ya tienes este libro en préstamo'], 409);
    }
    if (cuantos($conexion, "SELECT COUNT(*) FROM prestamos WHERE id_libro = ? AND estado = 'activo'", 'i', $libro) >= (int)$l['copias']) {
        responder(['error' => 'No hay ejemplares disponibles'], 409);
    }
    if (cuantos($conexion, 'SELECT COUNT(*) FROM carrito WHERE id_usuario = ? AND id_libro = ?', 'ii', $uid, $libro) > 0) {
        responder(['error' => 'Ese libro ya está en tu carrito'], 409);
    }
    $activos   = cuantos($conexion, "SELECT COUNT(*) FROM prestamos WHERE id_usuario = ? AND estado = 'activo'", 'i', $uid);
    $enCarrito = cuantos($conexion, 'SELECT COUNT(*) FROM carrito WHERE id_usuario = ?', 'i', $uid);
    if ($activos + $enCarrito >= MAX_PRESTAMOS) {
        responder(['error' => 'Solo puedes tener ' . MAX_PRESTAMOS . ' libros entre préstamos y carrito'], 409);
    }
    consultar($conexion, 'INSERT INTO carrito (id_usuario, id_libro) VALUES (?, ?)', 'ii', $uid, $libro);
    responder(['ok' => true], 201);
}

// ---------- POST accion=quitar ----------
if ($accion === 'quitar') {
    $libro = (int)($in['id_libro'] ?? 0);
    consultar($conexion, 'DELETE FROM carrito WHERE id_usuario = ? AND id_libro = ?', 'ii', $uid, $libro);
    responder(['ok' => true]);
}

// ---------- POST accion=confirmar: el carrito se convierte en préstamos ----------
if ($accion === 'confirmar') {
    $conexion->begin_transaction();   // todo o nada; los FOR UPDATE evitan que dos personas se lleven el último ejemplar

    $ids = array_column(
        consultar($conexion, 'SELECT id_libro FROM carrito WHERE id_usuario = ? ORDER BY id_libro FOR UPDATE', 'i', $uid)
            ->get_result()->fetch_all(MYSQLI_ASSOC),
        'id_libro');
    if (!$ids) {
        $conexion->rollback();
        responder(['error' => 'Tu carrito está vacío'], 409);
    }

    $activos = cuantos($conexion, "SELECT COUNT(*) FROM prestamos WHERE id_usuario = ? AND estado = 'activo' FOR UPDATE", 'i', $uid);
    if ($activos + count($ids) > MAX_PRESTAMOS) {
        $conexion->rollback();
        responder(['error' => 'Solo puedes tener ' . MAX_PRESTAMOS . ' libros en préstamo a la vez'], 409);
    }

    $titulos = [];
    foreach ($ids as $id) {
        $l = consultar($conexion, 'SELECT titulo, copias FROM libros WHERE id_libro = ? FOR UPDATE', 'i', $id)->get_result()->fetch_assoc();
        if (!$l) {
            $conexion->rollback();
            responder(['error' => 'Un libro de tu carrito ya no existe. Quítalo e intenta de nuevo.'], 409);
        }
        $ocupados = cuantos($conexion, "SELECT COUNT(*) FROM prestamos WHERE id_libro = ? AND estado = 'activo' FOR UPDATE", 'i', $id);
        if ($ocupados >= (int)$l['copias']) {
            $conexion->rollback();
            responder(['error' => 'Ya no hay ejemplares de «' . $l['titulo'] . '». Quítalo del carrito.'], 409);
        }
        $mios = cuantos($conexion, "SELECT COUNT(*) FROM prestamos WHERE id_libro = ? AND id_usuario = ? AND estado = 'activo' FOR UPDATE", 'ii', $id, $uid);
        if ($mios > 0) {
            $conexion->rollback();
            responder(['error' => 'Ya tienes «' . $l['titulo'] . '» en préstamo. Quítalo del carrito.'], 409);
        }
        $titulos[] = $l['titulo'];
    }

    $dias = (int)DIAS_PRESTAMO;
    foreach ($ids as $id) {
        consultar($conexion,
            "INSERT INTO prestamos (id_libro, id_usuario, fecha_salida, fecha_vence)
             VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL $dias DAY))", 'ii', $id, $uid);
    }
    consultar($conexion, 'DELETE FROM carrito WHERE id_usuario = ?', 'i', $uid);
    $conexion->commit();

    $vence = consultar($conexion, "SELECT DATE_ADD(CURDATE(), INTERVAL $dias DAY)")->get_result()->fetch_row()[0];

    $u = consultar($conexion, 'SELECT nombre, correo FROM usuarios WHERE id_usuario = ?', 'i', $uid)->get_result()->fetch_assoc();
    if ($u && $u['correo']) {
        enviar_correo($u['correo'], 'Tu préstamo en la Biblioteca',
            "Hola {$u['nombre']},\n\nRegistramos tu préstamo:\n- " . implode("\n- ", $titulos)
          . "\n\nFecha límite de devolución: $vence\n\nGracias por usar la Biblioteca.");
    }
    responder(['ok' => true, 'vence' => $vence, 'total' => count($ids)], 201);
}

responder(['error' => 'Acción no válida'], 422);
