<?php
// ============================================================
//  tutorias.php  -  Mi Progreso y Tutorías (MAIPI)
//  1) El alumno registra notas de SUS cursos matriculados
//  2) Si el promedio de un curso es bajo, ve los horarios con cupos
//  3) Solicita la tutoría (se descuenta un cupo)
// ============================================================
require 'conexion.php';

if (isset($_GET['salir'])) { session_destroy(); header('Location: index.html'); exit; }
$est = alumnoActual($conexion);
if (!$est) { session_destroy(); header('Location: index.html'); exit; }

$nombreCompleto = trim($est['nombres'] . ' ' . $est['paterno'] . ' ' . $est['materno']);
$iniciales      = mb_strtoupper(mb_substr($est['nombres'], 0, 1) . mb_substr($est['paterno'], 0, 1));
$primerNombre   = explode(' ', trim($est['nombres']))[0];
$nombreCorto    = mb_substr($primerNombre, 0, 1) . '. ' . $est['paterno'] . ' ' . mb_substr($est['materno'], 0, 1) . '.';
$nombreLista    = mb_strtoupper($est['paterno'] . ' ' . $est['materno']) . ', ' . $est['nombres'];

$mat = (string)$est['matricula'];
$sem = SEMESTRE;

$cursos = cursosMatriculados($conexion, $mat);          // [id_curso => fila]

$st = $conexion->prepare("SELECT id, id_curso, unidad, nota FROM notas WHERE matricula = ? AND semestre = ?
                          ORDER BY id_curso, FIELD(unidad,'PC1','PC2','Parcial','Final')");
$st->bind_param('ss', $mat, $sem);
$st->execute();
$filas = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

// Cursos en riesgo: promedio < NOTA_MINIMA
$porCurso = [];
foreach ($filas as $f) { $porCurso[(int)$f['id_curso']][] = (float)$f['nota']; }
$enRiesgo = [];
foreach ($porCurso as $idc => $lista) {
  if (isset($cursos[$idc]) && array_sum($lista) / count($lista) < NOTA_MINIMA) $enRiesgo[] = $idc;
}

$solicitudes = solicitudes($conexion, $mat);            // [id_curso => fila]
$horarios = [];                                         // [id_curso => lista de horarios con cupos]
foreach ($enRiesgo as $idc) {
  if (!isset($solicitudes[$idc]) && !empty($cursos[$idc]['area'])) {
    $horarios[$idc] = horariosDisponibles($conexion, $cursos[$idc]['area']);
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MAIPI | Tutorías</title>
<style>
  :root {
    --azul: #0b4a99; --verde: #14513f; --verde2: #1fb36b; --dorado: #ffc107; --fondo: #f1f4f8;
    --texto: #2b2f36; --suave: #6b7280; --rojo: #d32f2f; --alerta: #f5a623;
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
  .cabecera { background: #fff; height: 60px; border-bottom: 1px solid #dde3ea; display: flex; align-items: center; justify-content: flex-end; gap: 18px; padding: 0 28px; color: var(--azul); font-weight: 600; font-size: .95rem; }
  .cabecera .sep { color: #999; }
  .avatar { display: inline-grid; place-items: center; width: 30px; height: 30px; border-radius: 50%; background: #4ed1a1; color: #fff; font-size: .65rem; font-weight: 700; vertical-align: middle; }
  .cabecera .usuario { color: #333; font-weight: 400; font-size: .85rem; }
  .cabecera .salir { color: var(--azul); font-size: .8rem; font-weight: 400; }

  .pagina { padding: 24px 26px 40px; }
  .card { background: #fff; border: 1px solid #e3e8ee; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }

  /* Resumen superior */
  .resumen { display: flex; align-items: center; gap: 40px; padding: 18px 26px; margin-bottom: 22px; flex-wrap: wrap; }
  .resumen .nombre { font-size: 1.05rem; flex: 1; min-width: 220px; }
  .resumen .cred { display: flex; align-items: center; gap: 14px; color: #444; font-size: .9rem; }
  .badge { background: #1a2bd8; color: #fff; border-radius: 14px; padding: 3px 14px; font-weight: 700; }
  .resumen .mat { font-weight: 600; }

  .dos { display: grid; grid-template-columns: 1.7fr 1fr; gap: 24px; align-items: start; }
  .panel { padding: 22px 24px; }
  .panel h2 { margin: 0 0 4px; font-size: 1.3rem; font-weight: 400; color: #333; }
  .panel h3 { margin: 18px 0 14px; font-size: 1.1rem; font-weight: 600; }

  /* Tabla de notas */
  .scroll { overflow-x: auto; }
  table.notas { width: 100%; border-collapse: collapse; font-size: .85rem; min-width: 560px; }
  table.notas th { background: var(--verde); color: #fff; text-align: left; padding: 12px 10px; font-weight: 600; }
  table.notas td { padding: 10px; border-bottom: 1px solid #eee; }
  table.notas tr:hover td { background: #f6faf8; }
  .bajo { color: var(--rojo); font-weight: 600; }
  .btn-edit { background: var(--verde); color: #fff; border: 0; border-radius: 4px; padding: 4px 12px; font-size: .75rem; cursor: pointer; }
  .btn-edit:hover { filter: brightness(1.2); }

  /* Panel derecho */
  .alerta-titulo { display: flex; align-items: center; gap: 10px; font-size: 1.25rem; font-weight: 600; margin: 0 0 12px; }
  .tri { color: var(--alerta); font-size: 1.6rem; }
  .alerta-caja { display: flex; gap: 10px; border: 1px solid #ececec; border-radius: 4px; padding: 10px 12px; font-size: .85rem; line-height: 1.45; margin-bottom: 14px; }
  .btn-procesar { width: 100%; background: var(--verde); color: #fff; border: 0; border-radius: 4px; padding: 11px; font-size: .9rem; font-weight: 600; cursor: pointer; }
  .btn-procesar:hover { filter: brightness(1.2); }
  .btn-procesar:disabled { opacity: .6; cursor: wait; }
  .verif { border: 1px solid #e3e3e3; border-radius: 4px; margin-top: 14px; font-size: .85rem; }
  .verif summary { padding: 10px 12px; cursor: pointer; list-style: none; display: flex; justify-content: space-between; }
  .verif summary::-webkit-details-marker { display: none; }
  .verif .cuerpo { padding: 0 14px 12px; }
  .ok { margin: 6px 0 10px; }
  .det { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
  .det td { padding: 5px 0; border-bottom: 1px solid #eee; }
  .det td:first-child { font-weight: 600; width: 32%; }
  .programadas p { font-size: .88rem; line-height: 1.5; margin: 0 0 12px; }
  .programadas ul { margin: 0; padding-left: 18px; font-size: .88rem; line-height: 1.8; }
  .programadas a { color: var(--azul); }

  /* Formulario de notas */
  .form-nota { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; background: #f6faf8; border: 1px solid #dcebe5; border-radius: 6px; padding: 14px; margin-bottom: 18px; }
  .form-nota label { display: block; font-size: .75rem; font-weight: 600; margin-bottom: 4px; }
  .form-nota input, .form-nota select { padding: 8px 10px; border: 1px solid #c9d3cf; border-radius: 4px; font: .85rem "Segoe UI", Arial, sans-serif; }
  .form-nota .c-curso { flex: 1; min-width: 180px; } .form-nota .c-curso input { width: 100%; }
  .form-nota input[type=number] { width: 90px; }
  .btn-guardar { background: var(--verde); color: #fff; border: 0; border-radius: 4px; padding: 9px 18px; font-weight: 600; cursor: pointer; }
  .btn-guardar:disabled { opacity: .6; }
  .msg { font-size: .8rem; margin: 0 0 12px; padding: 6px 10px; border-radius: 4px; background: #fdecea; color: #a12622; }
  .btn-del { background: #fff; color: var(--rojo); border: 1px solid var(--rojo); border-radius: 4px; padding: 3px 9px; font-size: .75rem; cursor: pointer; margin-left: 4px; }
  .vacio { text-align: center; color: var(--suave); padding: 22px !important; }
  .prog { margin: 0 0 10px; font-size: .88rem; line-height: 1.5; padding-left: 10px; border-left: 3px solid var(--verde2); }
  .warn { color: #a15c00; }

  /* Horarios y solicitudes */
  .riesgo { border-top: 1px solid #eee; padding-top: 12px; margin-top: 14px; }
  .riesgo h4 { margin: 0 0 8px; font-size: .95rem; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
  .area { background: #e3f2ec; color: var(--verde); font-size: .7rem; padding: 2px 8px; border-radius: 10px; font-weight: 600; }
  .slot { display: flex; justify-content: space-between; align-items: center; gap: 8px; border: 1px solid #e3e3e3; border-radius: 4px; padding: 8px 10px; margin-bottom: 8px; font-size: .8rem; line-height: 1.4; }
  .slot small { color: var(--suave); }
  .btn-sol { background: var(--verde); color: #fff; border: 0; border-radius: 4px; padding: 6px 12px; font-size: .78rem; font-weight: 600; cursor: pointer; white-space: nowrap; }
  .btn-sol:hover { filter: brightness(1.2); }
  .btn-sol:disabled { opacity: .6; cursor: wait; }
  .ok-sol { background: #e8f5ee; border: 1px solid #c3e0cb; border-radius: 4px; padding: 9px 12px; font-size: .82rem; line-height: 1.5; }
  .btn-cancel { background: none; border: 0; color: var(--rojo); text-decoration: underline; cursor: pointer; font-size: .78rem; padding: 0; margin-top: 4px; }

  @media (max-width: 1100px) { .dos { grid-template-columns: 1fr; } }
  @media (max-width: 860px) { .side { position: static; width: 100%; } .contenido { margin-left: 0; } }
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
  <a href="dashboard.php"><i>🛠</i> Trámites Académicos</a>
  <a href="#"><i>📅</i> Horario</a>
  <a href="#"><i>📘</i> Cursos Matriculados</a>
  <a href="tutorias.php" class="activo"><i>🎓</i> Tutorías</a>
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
    <a class="salir" href="tutorias.php?salir=1">Salir</a>
  </header>

  <div class="pagina">
    <section class="card resumen">
      <div class="nombre"><?= e($nombreLista) ?></div>
      <div class="cred">Cursos matriculados: <span class="badge"><?= count($cursos) ?></span></div>
      <div class="mat"><?= e($est['matricula']) ?></div>
    </section>

    <p class="msg" id="msg" hidden></p>

    <div class="dos">
      <!-- ===== Izquierda: registro y lista de notas ===== -->
      <section class="card panel">
        <h2>Mi Progreso y Tutorías - Ciclo <?= e($sem) ?></h2>
        <h3>Cursos Matriculados y Notas de Evaluación</h3>

        <?php if (!$cursos): ?>
          <p class="msg">No tienes cursos matriculados en <?= e($sem) ?>.</p>
        <?php else: ?>
        <form id="form-nota" class="form-nota">
          <div class="c-curso">
            <label for="n-curso">Curso</label>
            <select id="n-curso" name="id_curso" style="width:100%" required>
              <?php foreach ($cursos as $c): ?>
                <option value="<?= (int)$c['id_curso'] ?>"><?= e($c['codigo_curso'] . ' - ' . $c['nombre_curso']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="n-unidad">Unidad/Evaluación</label>
            <select id="n-unidad" name="unidad">
              <?php foreach (UNIDADES as $u): ?><option><?= e($u) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="n-nota">Nota (0-20)</label>
            <input type="number" id="n-nota" name="nota" min="0" max="20" step="0.5" required>
          </div>
          <button type="submit" class="btn-guardar" id="btn-guardar">Guardar nota</button>
        </form>
        <?php endif; ?>

        <div class="scroll">
          <table class="notas">
            <thead>
              <tr><th>Curso</th><th>Ciclo</th><th>Unidad/Evaluación</th><th>Nota</th><th>Estado</th><th>Acción</th></tr>
            </thead>
            <tbody>
              <?php if (!$filas): ?>
                <tr><td colspan="6" class="vacio">Aún no registras notas. Agrégalas con el formulario de arriba.</td></tr>
              <?php endif; ?>
              <?php foreach ($filas as $f): $idc = (int)$f['id_curso']; ?>
                <tr>
                  <td><?= e($cursos[$idc]['nombre_curso'] ?? 'Curso #' . $idc) ?></td>
                  <td><?= e($sem) ?></td>
                  <td><?= e($f['unidad']) ?></td>
                  <td class="<?= (float)$f['nota'] < NOTA_MINIMA ? 'bajo' : '' ?>"><?= sprintf('%04.1f', $f['nota']) ?></td>
                  <td>Calificado</td>
                  <td>
                    <button type="button" class="btn-edit"
                      data-curso="<?= $idc ?>" data-unidad="<?= e($f['unidad']) ?>" data-nota="<?= e($f['nota']) ?>">edit</button>
                    <button type="button" class="btn-del" data-id="<?= (int)$f['id'] ?>" title="Eliminar">✕</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- ===== Derecha: sistema de tutorías ===== -->
      <aside>
        <section class="card panel">
          <h2 style="font-size:1.1rem;font-weight:600;margin-bottom:0;">Sistema de Tutorías Automatizado</h2>
        </section>

        <section class="card panel" style="margin-top:18px;">
          <p class="alerta-titulo"><span class="tri">⚠</span> Alerta y Asignación de Tutoría</p>

          <?php if (!$filas): ?>
            <div class="alerta-caja"><span>Registra tus notas para que el sistema evalúe si necesitas tutoría.</span></div>
          <?php elseif (!$enRiesgo): ?>
            <div class="alerta-caja"><span>✅ Vas bien: ningún curso requiere tutoría por ahora.</span></div>
          <?php else: ?>
            <div class="alerta-caja">
              <span class="tri" style="font-size:1.2rem;">⚠</span>
              <span>Alerta Académica: cursos que requieren tutoría en (<?= e(implode(', ', array_map(fn($i) => $cursos[$i]['nombre_curso'], $enRiesgo))) ?>)</span>
            </div>

            <?php foreach ($enRiesgo as $idc): $c = $cursos[$idc]; ?>
              <div class="riesgo">
                <h4><?= e($c['nombre_curso']) ?> <span class="area"><?= e($c['area'] ?: 'Sin área') ?></span></h4>

                <?php if (isset($solicitudes[$idc])): $sol = $solicitudes[$idc]; ?>
                  <div class="ok-sol">
                    ✅ <strong>Tutoría solicitada</strong><br>
                    <?= e($sol['tutor']) ?> · <?= e($sol['horario']) ?> · <?= e($sol['modalidad']) ?><br>
                    <button type="button" class="btn-cancel" data-id="<?= (int)$sol['id'] ?>">Cancelar solicitud</button>
                  </div>
                <?php elseif (empty($c['area'])): ?>
                  <p class="warn" style="font-size:.82rem;">Este curso aún no tiene un área de tutoría asignada.</p>
                <?php elseif (!$horarios[$idc]): ?>
                  <p class="warn" style="font-size:.82rem;">Sin cupos disponibles por ahora. Inténtalo más tarde.</p>
                <?php else: foreach ($horarios[$idc] as $h): ?>
                  <div class="slot">
                    <div>
                      <strong><?= e($h['tutor']) ?></strong><br>
                      <?= e($h['horario']) ?> · <?= e($h['modalidad']) ?><br>
                      <small><?= (int)$h['cupos'] ?> cupo(s) disponible(s)</small>
                    </div>
                    <button type="button" class="btn-sol" data-curso="<?= $idc ?>" data-disp="<?= (int)$h['id'] ?>">Solicitar</button>
                  </div>
                <?php endforeach; endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </section>

        <section class="card panel programadas" style="margin-top:18px;">
          <h2 style="font-size:1.15rem;font-weight:600;margin-bottom:14px;">Próximas Tutorías Programadas</h2>
          <?php if ($solicitudes): foreach ($solicitudes as $sol): ?>
            <div class="prog"><strong><?= e($sol['nombre_curso']) ?></strong><br><?= e($sol['tutor']) ?> · <?= e($sol['horario']) ?> · <?= e($sol['modalidad']) ?></div>
          <?php endforeach; else: ?>
            <p>No tienes tutorías programadas.</p>
          <?php endif; ?>
          <p><strong>Recursos de Apoyo Recomendados</strong></p>
          <ul>
            <li><a href="#">Guía de problemas resueltos</a></li>
            <li><a href="#">Videoclases del ciclo</a></li>
            <li><a href="#">Biblioteca virtual UNALM</a></li>
          </ul>
        </section>
      </aside>
    </div>
  </div>
</div>

<script>
  const msg = document.getElementById("msg");
  const mostrarMsg = t => { msg.textContent = t; msg.hidden = false; window.scrollTo({ top: 0, behavior: "smooth" }); };

  async function post(url, datos) {
    const r = await fetch(url, { method: "POST", body: datos });
    return r.json();
  }
  // Envía una acción y recarga si salió bien
  async function accion(url, campos, boton) {
    const datos = new FormData();
    Object.entries(campos).forEach(([k, v]) => datos.append(k, v));
    if (boton) boton.disabled = true;
    msg.hidden = true;
    try {
      const res = await post(url, datos);
      if (res.ok) { location.reload(); return; }
      mostrarMsg(res.mensaje || "No se pudo completar la acción.");
    } catch (err) {
      mostrarMsg("No se pudo conectar con el servidor.");
    }
    if (boton) boton.disabled = false;
  }

  // ---- Guardar nota ----
  const formNota = document.getElementById("form-nota");
  if (formNota) formNota.addEventListener("submit", e => {
    e.preventDefault();
    accion("notas_api.php", {
      accion: "guardar",
      id_curso: document.getElementById("n-curso").value,
      unidad: document.getElementById("n-unidad").value,
      nota: document.getElementById("n-nota").value
    }, document.getElementById("btn-guardar"));
  });

  // ---- Editar (carga la fila en el formulario) / Eliminar ----
  document.querySelectorAll(".btn-edit").forEach(b => b.addEventListener("click", () => {
    document.getElementById("n-curso").value  = b.dataset.curso;
    document.getElementById("n-unidad").value = b.dataset.unidad;
    document.getElementById("n-nota").value   = b.dataset.nota;
    document.getElementById("n-nota").focus();
    window.scrollTo({ top: 0, behavior: "smooth" });
  }));
  document.querySelectorAll(".btn-del").forEach(b => b.addEventListener("click", () => {
    if (confirm("¿Eliminar esta nota?")) accion("notas_api.php", { accion: "eliminar", id: b.dataset.id }, b);
  }));

  // ---- Solicitar / cancelar tutoría ----
  document.querySelectorAll(".btn-sol").forEach(b => b.addEventListener("click", () => {
    accion("tutoria_api.php", { accion: "solicitar", id_curso: b.dataset.curso, id_disp: b.dataset.disp }, b);
  }));
  document.querySelectorAll(".btn-cancel").forEach(b => b.addEventListener("click", () => {
    if (confirm("¿Cancelar esta solicitud de tutoría?")) accion("tutoria_api.php", { accion: "cancelar", id: b.dataset.id }, b);
  }));
</script>
</body>
</html>
