<?php
session_start();
if (($_SESSION['rol'] ?? '') === 'admin') {
    header('Location: admin.php');
    exit;
}
$logueado = !empty($_SESSION['id_usuario']);
$nombre   = $logueado ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : '';
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Catálogo — Biblioteca</title>
  <meta name="description" content="Busca libros en el catálogo de la biblioteca y revisa su disponibilidad."/>
  <link rel="stylesheet" href="estilos.css"/>
</head>
<body>
  <div class="mast"><div class="wrap mast-in">
    <?php if ($logueado): ?>
      <span>Hola, <?= $nombre ?></span>
      <a href="#" id="lnkCarrito">Mi carrito <span class="badge b-ok" id="nCarrito">0</span></a>
      <a href="#" id="lnkCuenta">Mis préstamos</a>
      <a href="api_logout.php">Cerrar sesión</a>
    <?php else: ?>
      <span class="gris">Visitante</span>
      <a href="login.php">Iniciar sesión</a>
      <a href="registro.php">Crear cuenta</a>
    <?php endif; ?>
  </div></div>
  <header class="head wrap"><div class="logo">Biblio<b>teca</b></div><span class="tag">Catálogo en línea</span></header>

  <div class="buscador"><div class="wrap">
    <form id="fb" class="fb">
      <select class="input" id="campo" aria-label="Buscar en">
        <option value="todo">Catálogo completo</option><option value="titulo">Título</option>
        <option value="autor">Autor</option><option value="cat">Categoría</option>
      </select>
      <input class="input" id="q" placeholder="Buscar en el catálogo" aria-label="Buscar"/>
      <button class="btn btn-primary" type="submit">Buscar</button>
    </form>
  </div></div>

  <main class="wrap">
    <ul class="nav-tabs">
      <li><button class="tab active" data-t="catalogo">Catálogo</button></li>
      <?php if ($logueado): ?>
      <li><button class="tab" data-t="carrito" id="tabCarrito">Mi carrito</button></li>
      <li><button class="tab" data-t="mios" id="tabMios">Mis préstamos</button></li>
      <?php endif; ?>
    </ul>

    <section id="catalogo" class="panel">
      <?php if (!$logueado): ?>
      <p class="aviso">Estás viendo el catálogo como visitante. <a href="registro.php">Crea una cuenta</a> o <a href="login.php">inicia sesión</a> para agregar libros al carrito y pedir préstamos.</p>
      <?php endif; ?>
      <div class="layout">
        <aside>
          <div class="facet"><h3>Disponibilidad</h3><ul id="fDisp"></ul></div>
          <div class="facet"><h3>Categoría</h3><ul id="fCat"></ul></div>
          <a href="#" id="limpiar" class="quitar hidden">Quitar filtros</a>
        </aside>
        <div>
          <div class="barra-res">
            <span id="cuenta"></span>
            <label>Ordenar por
              <select class="input" id="orden"><option value="rel">Relevancia</option><option value="titulo">Título (A–Z)</option><option value="autor">Autor (A–Z)</option></select>
            </label>
          </div>
          <ol id="lista" class="resultados"></ol>
        </div>
      </div>
    </section>

    <?php if ($logueado): ?>
    <section id="carrito" class="panel hidden">
      <h1>Mi carrito</h1>
      <div class="tabla-wrap"><table>
        <thead><tr><th>Portada</th><th>Título</th><th>Autor</th><th>Acción</th></tr></thead>
        <tbody id="tbCarrito"></tbody>
      </table></div>
      <div class="pie-carrito">
        <span id="resumenCarrito" class="gris"></span>
        <button class="btn btn-primary" id="btnConfirmar" disabled>Confirmar préstamo</button>
      </div>
    </section>

    <section id="mios" class="panel hidden">
      <h1 id="hMios">Préstamos</h1>
      <div class="tabla-wrap"><table>
        <thead><tr><th>Título</th><th>Fecha de salida</th><th>Fecha de vencimiento</th><th>Estado</th></tr></thead>
        <tbody id="tbMios"></tbody>
      </table></div>
      <p class="gris" style="margin-top:12px">Las devoluciones se registran en el mostrador de la biblioteca.</p>
    </section>
    <?php endif; ?>
  </main>
  <div id="toast" class="toast" role="status"></div>

  <script src="app.js"></script>
  <script>
    const LOGUEADO=<?= $logueado ? 'true' : 'false' ?>;
    pintarIconos(); activarTabs();
    const $=id=>document.getElementById(id);
    let LIBROS=[], MIS=[], CARRITO=[], fCat='', soloDisp=false;

    if(LOGUEADO){
      $('lnkCarrito').onclick=e=>{e.preventDefault();$('tabCarrito').click()};
      $('lnkCuenta').onclick=e=>{e.preventDefault();$('tabMios').click()};
    }

    // Pide al servidor los libros (y, si hay sesión, préstamos y carrito) y vuelve a dibujar todo
    async function cargar(){
      try{
        if(LOGUEADO){ [LIBROS,MIS,CARRITO]=await Promise.all([api('libros.php'),api('prestamos.php'),api('carrito.php')]); }
        else{ LIBROS=await api('libros.php'); }
      }catch(e){ toast(e.message); }
      pintarCatalogo();
      if(LOGUEADO){ pintarCarrito(); pintarMios(); }
    }

    function base(){
      const q=$('q').value.trim().toLowerCase(), c=$('campo').value;
      return LIBROS.filter(l=>{
        const t=c==='titulo'?l.titulo:c==='autor'?l.autor:c==='cat'?l.categoria:l.titulo+' '+l.autor+' '+l.categoria;
        return t.toLowerCase().includes(q);
      });
    }

    function accionLibro(l){
      const d=l.disponibles;
      if(!LOGUEADO) return d>0?'<a class="btn btn-sm" href="login.php">Inicia sesión para pedir</a>':'';
      if(l.lo_tengo) return '<span class="badge b-info">Ya lo tienes en préstamo</span>';
      if(l.en_carrito) return '<span class="badge b-ok">En tu carrito</span>';
      return d>0?`<button class="btn btn-primary btn-sm" onclick="agregar(${l.id_libro})">Agregar al carrito</button>`:'';
    }

    function pintarCatalogo(){
      const b=base();
      const cats={}; b.forEach(l=>cats[l.categoria]=(cats[l.categoria]||0)+1);
      const nDisp=b.filter(l=>l.disponibles>0).length;
      $('fDisp').innerHTML=
        `<li class="${!soloDisp?'on':''}"><a href="#" data-disp="0">Todos</a><span>${b.length}</span></li>
         <li class="${soloDisp?'on':''}"><a href="#" data-disp="1">Con ejemplares disponibles</a><span>${nDisp}</span></li>`;
      $('fCat').innerHTML=Object.entries(cats).map(([c,n])=>
        `<li class="${fCat===c?'on':''}"><a href="#" data-cat="${esc(c)}">${esc(c)}</a><span>${n}</span></li>`).join('')||'<li class="gris">Sin categorías</li>';
      $('limpiar').classList.toggle('hidden',!fCat&&!soloDisp);
      document.querySelectorAll('[data-disp]').forEach(a=>a.onclick=e=>{e.preventDefault();soloDisp=a.dataset.disp==='1';pintarCatalogo()});
      document.querySelectorAll('[data-cat]').forEach(a=>a.onclick=e=>{e.preventDefault();fCat=fCat===a.dataset.cat?'':a.dataset.cat;pintarCatalogo()});

      const r=b.filter(l=>(!fCat||l.categoria===fCat)&&(!soloDisp||l.disponibles>0));
      const o=$('orden').value;
      if(o==='titulo') r.sort((x,y)=>x.titulo.localeCompare(y.titulo));
      if(o==='autor') r.sort((x,y)=>x.autor.localeCompare(y.autor));
      $('cuenta').textContent=r.length===1?'Tu búsqueda arrojó 1 resultado.':`Tu búsqueda arrojó ${r.length} resultados.`;

      $('lista').innerHTML=r.length?r.map((l,i)=>{
        const d=l.disponibles, acc=accionLibro(l);
        return `<li class="res"><span class="n">${i+1}.</span>
          <div class="thumb" style="${portadaEstilo(l)}">${l.tiene_portada?'':esc(l.titulo)}</div>
          <div><h3>${esc(l.titulo)}</h3>
            <p>por ${esc(l.autor)}</p>
            <p class="gris">Categoría: ${esc(l.categoria)}</p>
            <p>Disponibilidad: ${d>0?`<span class="disp-ok">${d} de ${l.copias} ejemplares disponibles</span>`:'<span class="disp-no">Sin ejemplares disponibles</span>'}</p>
            ${acc?`<div class="acc">${acc}</div>`:''}</div></li>`;
      }).join(''):'<li class="vacio">No se encontraron resultados. Prueba con otro título o autor.</li>';
    }

    function pintarCarrito(){
      $('nCarrito').textContent=CARRITO.length;
      $('tbCarrito').innerHTML=CARRITO.length?CARRITO.map(l=>
        `<tr><td><div class="mini" style="${portadaEstilo(l)}"></div></td>
          <td>${esc(l.titulo)}${l.disponibles<1?' <span class="badge b-no">Sin ejemplares</span>':''}</td>
          <td>${esc(l.autor)}</td>
          <td><button class="btn btn-sm" onclick="quitar(${l.id_libro})">Quitar</button></td></tr>`
      ).join(''):'<tr><td colspan="4" class="vacio">Tu carrito está vacío. Agrega libros desde el catálogo.</td></tr>';
      $('resumenCarrito').textContent=CARRITO.length?`${CARRITO.length} libro(s) · cada préstamo dura 7 días`:'';
      $('btnConfirmar').disabled=!CARRITO.length;
    }

    function pintarMios(){
      $('hMios').textContent=`Préstamos (${MIS.filter(p=>p.estado!=='devuelto').length})`;
      $('tbMios').innerHTML=MIS.length?MIS.map(p=>
        `<tr><td>${esc(p.titulo)}</td><td>${p.fecha_salida}</td><td>${p.fecha_vence}</td><td>${badge(p.estado)}</td></tr>`
      ).join(''):'<tr><td colspan="4" class="vacio">Aún no tienes préstamos. Agrega libros al carrito y confirma tu préstamo.</td></tr>';
    }

    async function agregar(id){
      try{ await enviar('carrito.php',{accion:'agregar',id_libro:id}); toast('Agregado a tu carrito'); }
      catch(e){ toast(e.message); }
      cargar();
    }
    async function quitar(id){
      try{ await enviar('carrito.php',{accion:'quitar',id_libro:id}); }
      catch(e){ toast(e.message); }
      cargar();
    }
    if(LOGUEADO){
      $('btnConfirmar').onclick=async()=>{
        $('btnConfirmar').disabled=true;
        try{
          const r=await enviar('carrito.php',{accion:'confirmar'});
          toast('Préstamo confirmado. Devuélvelos antes del '+r.vence);
          await cargar(); $('tabMios').click();
        }catch(e){ toast(e.message); await cargar(); }
      };
    }

    $('fb').onsubmit=e=>{e.preventDefault();pintarCatalogo()};
    $('q').oninput=pintarCatalogo; $('campo').onchange=pintarCatalogo; $('orden').onchange=pintarCatalogo;
    $('limpiar').onclick=e=>{e.preventDefault();fCat='';soloDisp=false;pintarCatalogo()};
    cargar();
  </script>
</body>
</html>
