/* Biblioteca — utilidades compartidas: iconos, formato y comunicación con el servidor (archivos api_*.php) */

/* ---------- Iconos ---------- */
const ICONOS={
  lock:'<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
  book:'<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
  search:'<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
  eye:'<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
  'eye-off':'<path d="M17.9 17.9A10.9 10.9 0 0 1 12 19c-6.5 0-10-7-10-7a18.5 18.5 0 0 1 5.1-5.9M9.9 4.2A9.7 9.7 0 0 1 12 5c6.5 0 10 7 10 7a18.5 18.5 0 0 1-2.2 3.2M1 1l22 22"/>',
  arrow:'<path d="M5 12h14M12 5l7 7-7 7"/>',
  alert:'<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>',
  plus:'<path d="M12 5v14M5 12h14"/>',
  check:'<path d="M20 6 9 17l-5-5"/>'
};
const ico=(n,s=18)=>`<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${ICONOS[n]}</svg>`;
const pintarIconos=()=>document.querySelectorAll('[data-ico]').forEach(e=>e.innerHTML=ico(e.dataset.ico,+e.dataset.s||18));

/* ---------- Comunicación con el servidor ----------
   Cada ruta ('libros.php', 'prestamos.php', 'login.php') llama al archivo api_<ruta> del mismo sitio. */

let LOGIN_URL='login.php';   // a dónde mandar si la sesión venció (el panel admin lo cambia)

async function api(ruta,opciones={}){
  let r;
  try{
    r=await fetch('api_'+ruta,{credentials:'same-origin',headers:{'Content-Type':'application/json'},...opciones});
  }catch{
    throw new Error('No se pudo conectar con el servidor. ¿Abriste la página desde localhost?');
  }
  let d={}; try{d=await r.json()}catch{}
  if(r.status===401){location.href=LOGIN_URL;return new Promise(()=>{})}   // sesión vencida
  if(!r.ok) throw new Error(d.error||'Ocurrió un error en el servidor');
  return d;
}
const enviar=(ruta,datos)=>api(ruta,{method:'POST',body:JSON.stringify(datos)});

/* ---------- Portadas ---------- */
// Estilo CSS de una portada: la imagen si el libro la tiene, o su color
const portadaEstilo=l=>l.tiene_portada
  ?`background:url(api_portada.php?id=${l.id_libro}&v=${l.portada_v}) center/cover no-repeat,#${esc(l.color)}`
  :`background:#${esc(l.color)}`;

// Convierte la imagen elegida en un JPEG de 300x420 (recortada al centro) antes de subirla
function imagenAJpeg(archivo,ancho=300,alto=420){
  return new Promise((ok,mal)=>{
    if(!archivo||!archivo.type.startsWith('image/')) return mal(new Error('Elige un archivo de imagen'));
    const url=URL.createObjectURL(archivo), img=new Image();
    img.onload=()=>{
      const c=document.createElement('canvas'); c.width=ancho; c.height=alto;
      const x=c.getContext('2d'); x.fillStyle='#fff'; x.fillRect(0,0,ancho,alto);
      const k=Math.max(ancho/img.width,alto/img.height), w=img.width*k, h=img.height*k;
      x.drawImage(img,(ancho-w)/2,(alto-h)/2,w,h);
      URL.revokeObjectURL(url); ok(c.toDataURL('image/jpeg',0.85));
    };
    img.onerror=()=>{URL.revokeObjectURL(url);mal(new Error('No se pudo leer la imagen. Prueba con JPG o PNG.'))};
    img.src=url;
  });
}

/* ---------- Formato ---------- */
const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const BADGE={activo:['b-info','En préstamo'],vencido:['b-no','Vencido'],devuelto:['b-off','Devuelto']};
const badge=e=>`<span class="badge ${BADGE[e][0]}">${BADGE[e][1]}</span>`;

/* ---------- Interfaz ---------- */
function toast(m){const t=document.getElementById('toast');t.textContent=m;t.classList.add('show');clearTimeout(t._h);t._h=setTimeout(()=>t.classList.remove('show'),2800)}
function activarTabs(){
  const tabs=document.querySelectorAll('.tab');
  tabs.forEach(b=>b.onclick=()=>{
    tabs.forEach(x=>x.classList.toggle('active',x===b));
    document.querySelectorAll('.panel').forEach(p=>p.classList.toggle('hidden',p.id!==b.dataset.t));
  });
}
