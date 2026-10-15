<?php
require_once 'api_comun.php';
// El catálogo (GET) es público: lo ven también los visitantes. Agregar libros (POST) es solo del admin.

const COLORES = ['15803D', '166534', '14532D', '16A34A', '047857', '065F46'];

// ---------- GET: lista de libros con disponibilidad ----------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $uid = (int)($_SESSION['id_usuario'] ?? 0);     // 0 = visitante
    $st = $conexion->prepare(
        "SELECT l.id_libro, l.titulo, l.autor, l.categoria, l.copias, l.color,
                (l.portada IS NOT NULL) AS tiene_portada, l.portada_v,
                l.copias - (SELECT COUNT(*) FROM prestamos p
                            WHERE p.id_libro = l.id_libro AND p.estado = 'activo') AS disponibles,
                EXISTS(SELECT 1 FROM prestamos p
                       WHERE p.id_libro = l.id_libro AND p.id_usuario = ? AND p.estado = 'activo') AS lo_tengo,
                EXISTS(SELECT 1 FROM carrito c
                       WHERE c.id_libro = l.id_libro AND c.id_usuario = ?) AS en_carrito
         FROM libros l
         ORDER BY l.titulo"
    );
    $st->bind_param('ii', $uid, $uid);
    $st->execute();
    responder($st->get_result()->fetch_all(MYSQLI_ASSOC));
}

// ---------- POST: agregar un libro (solo admin) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_rol('admin');
    $in        = datos_entrada();

    if (($in['accion'] ?? '') === 'portada') {            // cambiar la portada de un libro existente
        $id  = (int)($in['id_libro'] ?? 0);
        $bin = imagen_desde_datos($in['imagen'] ?? '');
        $st  = $conexion->prepare('SELECT 1 FROM libros WHERE id_libro = ?');
        $st->bind_param('i', $id);
        $st->execute();
        if (!$st->get_result()->fetch_row()) {
            responder(['error' => 'El libro no existe'], 404);
        }
        guardar_portada($conexion, $id, $bin);
        responder(['ok' => true]);
    }

    $titulo    = trim($in['titulo'] ?? '');
    $autor     = trim($in['autor'] ?? '');
    $categoria = trim($in['categoria'] ?? '');
    $copias    = (int)($in['copias'] ?? 0);

    if ($titulo === '' || $autor === '' || $categoria === '' || $copias < 1 || $copias > 999) {
        responder(['error' => 'Revisa los datos del libro.'], 422);
    }
    $titulo    = mb_substr($titulo, 0, 200);
    $autor     = mb_substr($autor, 0, 150);
    $categoria = mb_substr($categoria, 0, 80);

    $bin   = !empty($in['imagen']) ? imagen_desde_datos($in['imagen']) : null;

    $n     = (int)$conexion->query('SELECT COUNT(*) FROM libros')->fetch_row()[0];
    $color = COLORES[$n % count(COLORES)];

    $st = $conexion->prepare('INSERT INTO libros (titulo, autor, categoria, copias, color) VALUES (?, ?, ?, ?, ?)');
    $st->bind_param('sssis', $titulo, $autor, $categoria, $copias, $color);
    $st->execute();
    $idNuevo = $conexion->insert_id;
    if ($bin !== null) {
        guardar_portada($conexion, $idNuevo, $bin);
    }
    responder(['ok' => true, 'id_libro' => $idNuevo], 201);
}

responder(['error' => 'Método no permitido'], 405);
