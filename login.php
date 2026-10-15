<?php
session_start();
if (!empty($_SESSION['id_usuario'])) {
    header('Location: ' . ($_SESSION['rol'] === 'admin' ? 'admin.php' : 'catalogo.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Iniciar sesión — Biblioteca</title>
  <link rel="stylesheet" href="estilos.css"/>
</head>
<body class="login-body">
  <div class="login-shape-1"></div>
  <div class="login-shape-2"></div>

  <main class="login-card">
    <div class="login-icon" data-ico="book" data-s="28"></div>
    <h1>Biblio<span>teca</span></h1>
    <p class="sub">Inicia sesión para pedir tus préstamos</p>

    <div id="error" class="alert-error hidden"><span data-ico="alert" data-s="16"></span><span id="errorTxt"></span></div>

    <form id="form">
      <div class="fg"><label for="usuario">Correo electrónico</label>
        <input class="input" type="email" id="usuario" autocomplete="email" required autofocus/></div>
      <div class="fg"><label for="password">Contraseña</label>
        <div class="campo">
          <input class="input" type="password" id="password" autocomplete="current-password" required style="padding-right:44px"/>
          <button type="button" class="btn-ojo" id="btnToggle" aria-label="Mostrar contraseña"></button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Iniciar sesión</button>
    </form>

    <div class="enlaces-login">
      <a href="registro.php">¿No tienes cuenta? Crea una</a>
      <a href="catalogo.php">Seguir como visitante</a>
      <a href="personal.php" class="gris">Acceso del personal</a>
    </div>
    <p class="nota">Cuenta de prueba: <strong>lector@correo.com</strong> / <strong>lector123</strong></p>
  </main>

  <script src="app.js"></script>
  <script>
    pintarIconos();
    const $=id=>document.getElementById(id);
    const ojo=()=>{$('btnToggle').innerHTML=ico($('password').type==='text'?'eye':'eye-off',18)};
    ojo();
    $('btnToggle').onclick=()=>{const i=$('password');i.type=i.type==='password'?'text':'password';ojo()};

    $('form').onsubmit=async e=>{
      e.preventDefault();
      try{
        const r=await enviar('login.php',{tipo:'lector',usuario:$('usuario').value.trim(),password:$('password').value});
        location.href=r.destino;
      }catch(err){
        $('errorTxt').textContent=err.message;
        $('error').classList.remove('hidden');
      }
    };
  </script>
</body>
</html>
