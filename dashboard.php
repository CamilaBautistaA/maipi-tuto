<?php
// ============================================================
//  dashboard.php  -  Pantalla principal del MAIPI
//  Va en la MISMA carpeta que index.html y login.php
// ============================================================
session_start();

// Si no hay sesión, vuelve al login
if (empty($_SESSION['email'])) { header('Location: index.html'); exit; }

// Cerrar sesión: dashboard.php?salir=1
if (isset($_GET['salir'])) { session_destroy(); header('Location: index.html'); exit; }

// ---- CONEXIÓN (igual que en login.php) ----
// ---- CONEXIÓN ----
$conexion = new mysqli("sql211.infinityfree.com", "if0_43256743", "67YpiGZF2JZfRtm", "if0_43256743_maipi_db");
if ($conexion->connect_error) { die("Error de conexión: " . $conexion->connect_error); }
$conexion->set_charset("utf8mb4");

// ---- Buscar al estudiante por el correo de la sesión ----
$st = $conexion->prepare("SELECT * FROM sheet1 WHERE email_institucional = ? LIMIT 1");
$st->bind_param("s", $_SESSION['email']);
$st->execute();
$est = $st->get_result()->fetch_assoc();
$st->close();
$conexion->close();
if (!$est) { session_destroy(); header('Location: index.html'); exit; }

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$nombreCompleto = trim($est['nombres'] . ' ' . $est['paterno'] . ' ' . $est['materno']);
$iniciales      = mb_strtoupper(mb_substr($est['nombres'], 0, 1) . mb_substr($est['paterno'], 0, 1));
$primerNombre   = explode(' ', trim($est['nombres']))[0];
$nombreCorto    = mb_substr($primerNombre, 0, 1) . '. ' . $est['paterno'] . ' ' . mb_substr($est['materno'], 0, 1) . '.';

// Foto: fotos/<matricula>.jpg  (si no existe, se muestran las iniciales)
$matSegura = preg_replace('/[^A-Za-z0-9_-]/', '', $est['matricula']);
$fotoRuta  = 'fotos/' . $matSegura . '.jpg';
$tieneFoto = file_exists(__DIR__ . '/' . $fotoRuta);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MAIPI | Intranet del Estudiante</title>
<style>
  :root {
    --azul: #0b4a99; --verde: #1fb36b; --dorado: #ffc107; --fondo: #f1f4f8;
    --texto: #2b2f36; --suave: #6b7280; --rojo: #e0304a;
  }
  * { box-sizing: border-box; }
  body { margin: 0; font-family: "Segoe UI", Roboto, Arial, sans-serif; background: var(--fondo); color: var(--texto); }

  /* ===== Barra lateral ===== */
  .side { position: fixed; inset: 0 auto 0 0; width: 230px; background: var(--azul); color: #fff; overflow-y: auto; padding-bottom: 20px; }
  .side .top { text-align: center; padding: 18px 16px 14px; border-bottom: 1px solid rgba(255,255,255,.25); margin: 0 16px; }
  .side .escudo { width: 100px; height: 115px; object-fit: contain; }
  .side .maipi { font-family: "Times New Roman", Georgia, serif; font-size: 1.5rem; color: #3fd699; margin: 14px 0 0; }
  .side .sub { font-size: .8rem; }
  .side .quien { text-align: center; color: #f5b014; font-weight: 700; padding: 16px; border-bottom: 1px solid rgba(255,255,255,.25); margin: 0 16px 14px; line-height: 1.5; }
  .side h4 { margin: 18px 18px 6px; font-size: .68rem; font-weight: 500; color: rgba(255,255,255,.65); letter-spacing: .5px; }
  .side a { display: flex; align-items: center; gap: 12px; padding: 10px 14px; margin: 2px 10px; border-radius: 4px; color: #fff; text-decoration: none; font-size: .85rem; font-weight: 600; }
  .side a:hover { background: rgba(255,255,255,.12); }
  .side a.activo { background: var(--dorado); color: #222; }
  .side a i { font-style: normal; width: 22px; text-align: center; }

  /* ===== Contenido ===== */
  .contenido { margin-left: 230px; }
  .cabecera { background: #fff; height: 85px; border-bottom: 1px solid #dde3ea; display: flex; align-items: flex-start; justify-content: flex-end; gap: 18px; padding: 14px 28px; color: var(--azul); font-weight: 600; font-size: .95rem; }
  .cabecera .sep { color: #999; }
  .avatar { display: inline-grid; place-items: center; width: 30px; height: 30px; border-radius: 50%; background: #4ed1a1; color: #fff; font-size: .65rem; font-weight: 700; vertical-align: middle; }
  .cabecera .usuario { color: #333; font-weight: 400; font-size: .85rem; }
  .cabecera .salir { color: var(--azul); font-size: .8rem; font-weight: 400; }

  .pagina { padding: 0 26px 40px; margin-top: -45px; }
  .card { background: #fff; border: 1px solid #e3e8ee; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,.05); }

  /* Perfil */
  .perfil { display: flex; gap: 40px; padding: 38px 40px; align-items: center; flex-wrap: wrap; }
  .foto { width: 260px; height: 310px; border-radius: 50%; border: 3px solid #eee; overflow: hidden; background: #dfe6ee; display: grid; place-items: center; flex: none; }
  .foto img { width: 100%; height: 100%; object-fit: cover; }
  .foto span { font-size: 4rem; font-weight: 700; color: #8a97a8; }
  .datos { flex: 1; min-width: 300px; }
  .datos h1 { margin: 0 0 6px; font-size: 1.7rem; font-weight: 600; }
  .datos h2 { margin: 0 0 6px; font-size: 1.3rem; font-weight: 600; }
  .datos h3 { margin: 0 0 14px; font-size: 1.2rem; font-weight: 600; }
  table { width: 100%; border-collapse: collapse; font-size: .9rem; }
  tr:nth-child(odd) { background: #f8f8f8; }
  th, td { text-align: left; padding: 14px 12px; }
  th { width: 38%; font-weight: 600; }

  /* Aviso */
  .aviso { display: flex; gap: 20px; align-items: flex-start; background: #d4edda; border: 1px solid #c3e0cb; border-radius: 4px; padding: 14px 20px; margin: 30px 0 22px; color: #155724; }
  .aviso .campana { font-size: 3.2rem; line-height: 1; }
  .aviso h3 { margin: 0 0 6px; font-size: 1.15rem; color: #0f3d1d; }
  .aviso p { margin: 0 0 8px; font-size: .88rem; line-height: 1.5; }
  .aviso button { background: #dc3545; color: #fff; border: 0; border-radius: 4px; padding: 8px 16px; font-weight: 600; cursor: pointer; }

  /* Tarjetas */
  .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px 30px; }
  .opcion { display: flex; align-items: center; gap: 14px; padding: 26px 30px; text-decoration: none; color: inherit; transition: box-shadow .15s, transform .15s; }
  .opcion:hover { box-shadow: 0 4px 12px rgba(0,0,0,.1); transform: translateY(-2px); }
  .opcion .ico { font-size: 2.6rem; width: 52px; text-align: center; }
  .opcion strong { display: block; font-size: 1.15rem; font-weight: 600; }
  .opcion small { color: var(--suave); font-size: .8rem; }

  @media (max-width: 860px) {
    .side { position: static; width: 100%; }
    .contenido { margin-left: 0; }
    .grid { grid-template-columns: 1fr; }
    .foto { width: 200px; height: 240px; }
  }
</style>
</head>
<body>

<aside class="side">
  <div class="top">
    <img class="escudo" src="escudo.unalm.png" alt="Escudo UNALM">
    <p class="maipi">MAIPI</p>
    <div class="sub">Intranet del Estudiante</div>
  </div>
  <div class="quien"><?= e($nombreCorto) ?><br>DNI <?= e($est['numero_doc_identidad']) ?></div>

  <h4>ACADÉMICO</h4>
  <a href="dashboard.php" class="activo"><i>🛠</i> Trámites Académicos</a>
  <a href="#"><i>📅</i> Horario</a>
  <a href="#"><i>📘</i> Cursos Matriculados</a>
  <a href="tutorias.php"><i>🎓</i> Tutorías</a>
  <a href="#"><i>📄</i> Historial Académico</a>
  <a href="#"><i>☑</i> Avance Curricular</a>
  <a href="#"><i>🗂</i> Malla Curricular</a>
  <a href="#"><i>💔</i> Deudas Materiales</a>

  <h4>BIENESTAR</h4>
  <a href="#"><i>🍴</i> Comedor</a>
  <a href="#"><i>➕</i> Centro Médico</a>
  <a href="#"><i>🏢</i> Exo. Seguro Accidentes</a>

  <h4>TRÁMITES</h4>
  <a href="#"><i>✨</i> Trámites</a>

  <h4>FINANCIERO</h4>
  <a href="#"><i>💵</i> Pagos Matrícula</a>
  <a href="#"><i>🔍</i> Deuda Pendiente</a>

  <h4>ATENCIÓN USUARIO</h4>
  <a href="#"><i>🤝</i> Soporte</a>
</aside>

<div class="contenido">
  <header class="cabecera">
    <span><?= e($est['carrera']) ?></span><span class="sep">|</span>
    <span><?= e($est['matricula']) ?></span><span class="sep">|</span>
    <span>🔔</span>
    <span><span class="avatar"><?= e($iniciales) ?></span> <span class="usuario"><?= e($nombreCompleto) ?></span></span>
    <a class="salir" href="dashboard.php?salir=1">Salir</a>
  </header>

  <div class="pagina">
    <section class="card perfil">
      <div class="foto">
        <?php if ($tieneFoto): ?>
          <img src="<?= e($fotoRuta) ?>" alt="<?= e($nombreCompleto) ?>">
        <?php else: ?>
          <span><?= e($iniciales) ?></span>
        <?php endif; ?>
      </div>
      <div class="datos">
        <h1><?= e($nombreCompleto) ?></h1>
        <h2><?= e($est['matricula']) ?> - <?= e($est['modalidad']) ?></h2>
        <h3>DNI <?= e($est['numero_doc_identidad']) ?></h3>
        <table>
          <tr><th>Facultad</th><td><?= e($est['facultad']) ?></td></tr>
          <tr><th>Especialidad</th><td><?= e($est['carrera']) ?></td></tr>
          <?php if (!empty($est['plan_estudios'])): ?>
            <tr><th>Plan de Estudios</th><td><?= e($est['plan_estudios']) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($est['tutor'])): ?>
            <tr><th>Tutor</th><td><?= e($est['tutor']) ?></td></tr>
          <?php endif; ?>
        </table>
      </div>
    </section>

    <section class="aviso">
      <div class="campana">🔕</div>
      <div>
        <h3>Actualización de Ficha Socioeconomica</h3>
        <p>Se encuentra habilitada la actualización de la Ficha Socioeconómica. Les pedimos completar y actualizar su información de manera responsable y veraz. Estos datos son importantes para conocer mejor la realidad de nuestra comunidad estudiantil y permitir que la Universidad pueda orientar de manera adecuada sus servicios y apoyos.</p>
        <button type="button">Actualizar</button>
      </div>
    </section>

    <section class="grid">
      <a class="card opcion" href="#"><span class="ico">📗</span><div><strong>Cursos Matriculados</strong><small>Revisa en que cursos estás matriculado.</small></div></a>
      <a class="card opcion" href="#"><span class="ico">🗓</span><div><strong>Horario</strong><small>Revisa tu horario.</small></div></a>
      <a class="card opcion" href="#"><span class="ico">📄</span><div><strong>Historial Académico</strong><small>Toda la información de tu historial académico.</small></div></a>
      <a class="card opcion" href="#"><span class="ico">✅</span><div><strong>Avance Curricular</strong><small>Toda la información de tu avance curricular.</small></div></a>
      <a class="card opcion" href="#"><span class="ico">🗂</span><div><strong>Malla Curricular</strong><small>Toda la información de tu malla curricular.</small></div></a>
      <a class="card opcion" href="#"><span class="ico">💔</span><div><strong>Deudas Materiales</strong><small>Toda la información de tus deudas de materiales.</small></div></a>
    </section>
  </div>
</div>

</body>
</html>