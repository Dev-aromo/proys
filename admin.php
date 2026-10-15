<?php
session_start();
// Solo entra quien inició sesión con el rol "admin"
if (empty($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header('Location: personal.php');
    exit;
}
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="robots" content="noindex, nofollow"/>
  <title>Interfaz del personal — Biblioteca</title>
  <link rel="stylesheet" href="estilos.css"/>
</head>
<body>
  <header class="staff-head"><div class="staff-in">
    <div class="logo-s">Biblioteca <small>Interfaz del personal</small></div>
    <nav class="staff-nav">
      <button class="tab active" data-t="inicio">Inicio</button>
      <button class="tab" data-t="circ">Circulación</button>
      <button class="tab" data-t="cat">Catalogación</button>
    </nav>
    <div class="user"><span><?= htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') ?></span><a href="api_logout.php">Cerrar sesión</a></div>
  </div></header>

  <main class="staff wrap">
    <section id="inicio" class="panel">
      <h1>Inicio</h1>
      <div class="modulos">
        <div class="modulo" id="mCirc" tabindex="0" role="button"><div class="ic" data-ico="check" data-s="40"></div><b>Circulación</b><span class="d">Préstamos y devoluciones</span></div>
        <div class="modulo" id="mCat" tabindex="0" role="button"><div class="ic" data-ico="book" data-s="40"></div><b>Catalogación</b><span class="d">Libros y ejemplares</span></div>
      </div>
      <h2>Resumen</h2>
      <div class="kpis" id="stats"></div>
    </section>

    <section id="circ" class="panel hidden">
      <div class="layout-s">
        <aside class="sidemenu"><h3>Circulación</h3>
          <ul id="menuCirc">
            <li><button class="on" data-f="">Todos los préstamos</button></li>
            <li><button data-f="activo">En préstamo</button></li>
            <li><button data-f="vencido">Vencidos</button></li>
            <li><button data-f="devuelto">Devueltos</button></li>
          </ul>
        </aside>
        <div>
          <h1 id="tCirc">Todos los préstamos</h1>
          <div class="tabla-wrap"><table>
            <thead><tr><th>Título</th><th>Lector</th><th>Salida</th><th>Vence</th><th>Estado</th><th>Acción</th></tr></thead>
            <tbody id="tbPrest"></tbody>
          </table></div>
        </div>
      </div>
    </section>

    <section id="cat" class="panel hidden">
      <div class="layout-s">
        <aside class="sidemenu"><h3>Catalogación</h3>
          <ul><li><button id="btnNuevo">Nuevo libro</button></li></ul>
        </aside>
        <div>
          <h1>Catálogo</h1>
          <div class="tabla-wrap"><table>
            <thead><tr><th>Portada</th><th>Título</th><th>Autor</th><th>Categoría</th><th>Ejemplares</th><th>Disponibles</th><th>Acción</th></tr></thead>
            <tbody id="tbLibros"></tbody>
          </table></div>
        </div>
      </div>
    </section>
  </main>

  <dialog id="dlg">
    <form id="formLibro" autocomplete="off">
      <h2>Nuevo libro</h2>
      <div class="fg"><label for="fTitulo">Título</label><input class="input" id="fTitulo" required/></div>
      <div class="fg"><label for="fAutor">Autor</label><input class="input" id="fAutor" required/></div>
      <div class="fila2">
        <div class="fg"><label for="fCat">Categoría</label><input class="input" id="fCat" placeholder="Novela" required/></div>
        <div class="fg"><label for="fCopias">Ejemplares</label><input class="input" id="fCopias" type="number" min="1" value="1" required/></div>
      </div>
      <div class="fg"><label for="fPortada">Portada (opcional)</label>
        <input class="input" id="fPortada" type="file" accept="image/*"/>
        <div id="vistaPortada" class="vista hidden"></div>
      </div>
      <div class="acciones">
        <button type="button" class="btn" id="btnCancelar">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </dialog>
  <input type="file" id="fCambio" accept="image/*" class="hidden"/>
  <div id="toast" class="toast" role="status"></div>

  <script src="app.js"></script>
  <script>
    LOGIN_URL='personal.php'; pintarIconos(); activarTabs();
    const $=id=>document.getElementById(id);
    const TITULOS={'':'Todos los préstamos',activo:'En préstamo',vencido:'Vencidos',devuelto:'Devueltos'};
    let filtro='', LIBROS=[], PRESTAMOS=[];
    const ir=t=>document.querySelector(`[data-t="${t}"]`).click();
    $('mCirc').onclick=()=>ir('circ'); $('mCat').onclick=()=>ir('cat');
    document.querySelectorAll('.modulo').forEach(m=>m.onkeydown=e=>{if(e.key==='Enter')m.click()});

    // Pide al servidor los libros y todos los préstamos, y vuelve a dibujar todo
    async function cargar(){
      try{ [LIBROS,PRESTAMOS]=await Promise.all([api('libros.php'),api('prestamos.php')]); }
      catch(e){ toast(e.message); }
      pintarStats(); pintarPrestamos(); pintarLibros();
    }

    function pintarStats(){
      const act=PRESTAMOS.filter(p=>p.estado==='activo').length;
      const ven=PRESTAMOS.filter(p=>p.estado==='vencido').length;
      const lect=new Set(PRESTAMOS.filter(p=>p.estado!=='devuelto').map(p=>p.id_usuario)).size;
      $('stats').innerHTML=`
        <div class="kpi"><b>${LIBROS.length}</b><span>Títulos en el catálogo</span></div>
        <div class="kpi"><b>${act}</b><span>Préstamos vigentes</span></div>
        <div class="kpi rojo"><b>${ven}</b><span>Préstamos vencidos</span></div>
        <div class="kpi"><b>${lect}</b><span>Lectores con libros</span></div>`;
    }

    function pintarPrestamos(){
      $('tCirc').textContent=TITULOS[filtro];
      const ps=PRESTAMOS.filter(p=>!filtro||p.estado===filtro);
      $('tbPrest').innerHTML=ps.length?ps.map(p=>
        `<tr><td>${esc(p.titulo)}</td><td>${esc(p.lector)}</td><td>${p.fecha_salida}</td><td>${p.fecha_vence}</td><td>${badge(p.estado)}</td>
          <td>${p.estado==='devuelto'?'':`<button class="btn btn-sm" onclick="devolver(${p.id_prestamo})">Registrar devolución</button>`}</td></tr>`
      ).join(''):'<tr><td colspan="6" class="vacio">No hay préstamos en esta lista.</td></tr>';
    }

    function pintarLibros(){
      $('tbLibros').innerHTML=LIBROS.map(l=>
        `<tr><td><div class="mini" style="${portadaEstilo(l)}"></div></td><td>${esc(l.titulo)}</td><td>${esc(l.autor)}</td><td>${esc(l.categoria)}</td><td>${l.copias}</td>
          <td><span class="badge ${l.disponibles>0?'b-ok':'b-no'}">${l.disponibles}</span></td>
          <td><button class="btn btn-sm" onclick="cambiarPortada(${l.id_libro})">${l.tiene_portada?'Cambiar':'Poner'} portada</button></td></tr>`
      ).join('');
    }

    // Cambiar la portada de un libro que ya existe
    let idCambio=0, imagenNueva=null;
    function cambiarPortada(id){ idCambio=id; $('fCambio').click(); }
    $('fCambio').onchange=async e=>{
      const f=e.target.files[0]; e.target.value='';
      if(!f) return;
      try{
        const img=await imagenAJpeg(f);
        await enviar('libros.php',{accion:'portada',id_libro:idCambio,imagen:img});
        toast('Portada guardada');
      }catch(err){ toast(err.message); }
      cargar();
    };

    // Portada al agregar un libro nuevo (con vista previa)
    $('fPortada').onchange=async e=>{
      const f=e.target.files[0]; imagenNueva=null; $('vistaPortada').classList.add('hidden');
      if(!f) return;
      try{
        imagenNueva=await imagenAJpeg(f);
        $('vistaPortada').style.background=`url(${imagenNueva}) center/cover`;
        $('vistaPortada').classList.remove('hidden');
      }catch(err){ toast(err.message); e.target.value=''; }
    };

    async function devolver(id){
      try{ await enviar('prestamos.php',{accion:'devolver',id_prestamo:id}); toast('Devolución registrada'); }
      catch(e){ toast(e.message); }
      cargar();
    }

    document.querySelectorAll('#menuCirc button').forEach(b=>b.onclick=()=>{
      filtro=b.dataset.f;
      document.querySelectorAll('#menuCirc button').forEach(x=>x.classList.toggle('on',x===b));
      pintarPrestamos();
    });
    $('btnNuevo').onclick=()=>{$('formLibro').reset();imagenNueva=null;$('vistaPortada').classList.add('hidden');$('dlg').showModal()};
    $('btnCancelar').onclick=()=>$('dlg').close();
    $('formLibro').onsubmit=async e=>{
      e.preventDefault();
      try{
        await enviar('libros.php',{titulo:$('fTitulo').value,autor:$('fAutor').value,categoria:$('fCat').value,copias:+$('fCopias').value,imagen:imagenNueva});
        $('dlg').close(); toast('Libro guardado');
      }catch(err){ toast(err.message); }
      cargar();
    };
    cargar();
  </script>
</body>
</html>
