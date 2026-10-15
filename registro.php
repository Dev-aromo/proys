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
  <title>Crear cuenta — Biblioteca</title>
  <link rel="stylesheet" href="estilos.css"/>
</head>
<body class="login-body">
  <div class="login-shape-1"></div>
  <div class="login-shape-2"></div>

  <main class="login-card">
    <div class="login-icon" data-ico="book" data-s="28"></div>
    <h1>Crea tu <span>cuenta</span></h1>
    <p class="sub">La necesitas para agregar libros al carrito y pedir préstamos</p>

    <div id="error" class="alert-error hidden"><span data-ico="alert" data-s="16"></span><span id="errorTxt"></span></div>

    <form id="form">
      <div class="fg"><label for="nombre">Nombre completo</label>
        <input class="input" type="text" id="nombre" autocomplete="name" maxlength="100" required autofocus/></div>
      <div class="fg"><label for="correo">Correo electrónico</label>
        <input class="input" type="email" id="correo" autocomplete="email" maxlength="150" required/></div>
      <div class="fg"><label for="password">Contraseña (mínimo 8 caracteres)</label>
        <input class="input" type="password" id="password" autocomplete="new-password" minlength="8" maxlength="72" required/></div>
      <div class="fg"><label for="password2">Repite la contraseña</label>
        <input class="input" type="password" id="password2" autocomplete="new-password" minlength="8" maxlength="72" required/></div>
      <button type="submit" class="btn btn-primary btn-block">Crear cuenta</button>
    </form>

    <div class="enlaces-login">
      <a href="login.php">¿Ya tienes cuenta? Inicia sesión</a>
      <a href="catalogo.php">Seguir como visitante</a>
    </div>
    <p class="nota">Usaremos tu correo para avisarte de tus préstamos.</p>
  </main>

  <script src="app.js"></script>
  <script>
    pintarIconos();
    const $=id=>document.getElementById(id);
    const falla=m=>{$('errorTxt').textContent=m;$('error').classList.remove('hidden')};

    $('form').onsubmit=async e=>{
      e.preventDefault();
      if($('password').value!==$('password2').value) return falla('Las contraseñas no coinciden.');
      try{
        const r=await enviar('registro.php',{nombre:$('nombre').value.trim(),correo:$('correo').value.trim(),password:$('password').value});
        location.href=r.destino;
      }catch(err){ falla(err.message); }
    };
  </script>
</body>
</html>
