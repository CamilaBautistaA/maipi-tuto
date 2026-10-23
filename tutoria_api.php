<?php
// ============================================================
//  tutoria_api.php  -  Solicitar o cancelar una tutoría
//  Solicitar: el alumno debe estar matriculado, tener promedio < NOTA_MINIMA
//             y el horario debe ser de un tutor del área del curso con cupos.
// ============================================================
require 'conexion.php';

$est = alumnoActual($conexion);
if (!$est) json_out(['ok' => false, 'mensaje' => 'Sesión no válida.'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);

$mat    = (string)$est['matricula'];
$sem    = SEMESTRE;
$accion = $_POST['accion'] ?? '';

try {
  // ---------------- SOLICITAR ----------------
  if ($accion === 'solicitar') {
    $idCurso = (int)($_POST['id_curso'] ?? 0);
    $idDisp  = (int)($_POST['id_disp'] ?? 0);

    if (!estaMatriculado($conexion, $mat, $idCurso)) json_out(['ok' => false, 'mensaje' => 'Ese curso no está en tu matrícula.']);
    $prom = promedioCurso($conexion, $mat, $idCurso);
    if ($prom === null || $prom >= NOTA_MINIMA) json_out(['ok' => false, 'mensaje' => 'Este curso no requiere tutoría con tus notas actuales.']);

    $st = $conexion->prepare('SELECT area FROM `' . TABLA_CURSOS . '` WHERE id_curso = ?');
    $st->bind_param('i', $idCurso); $st->execute();
    $area = $st->get_result()->fetch_assoc()['area'] ?? null;
    $st->close();
    if (!$area) json_out(['ok' => false, 'mensaje' => 'Este curso aún no tiene área de tutoría.']);

    $st = $conexion->prepare('SELECT 1 FROM solicitudes_tutoria WHERE matricula = ? AND id_curso = ? AND semestre = ?');
    $st->bind_param('sis', $mat, $idCurso, $sem); $st->execute();
    $ya = $st->get_result()->num_rows > 0;
    $st->close();
    if ($ya) json_out(['ok' => false, 'mensaje' => 'Ya solicitaste tutoría para este curso.']);

    $conexion->begin_transaction();
    // Bloquea la fila para que dos alumnos no tomen el último cupo
    $st = $conexion->prepare(
      'SELECT d.id FROM disponibilidad d JOIN tutores t ON t.id = d.id_tutor
        WHERE d.id = ? AND t.area = ? AND d.cupos > 0 FOR UPDATE');
    $st->bind_param('is', $idDisp, $area); $st->execute();
    $hay = $st->get_result()->num_rows > 0;
    $st->close();
    if (!$hay) { $conexion->rollback(); json_out(['ok' => false, 'mensaje' => 'Ese horario ya no tiene cupos. Elige otro.']); }

    $up = $conexion->prepare('UPDATE disponibilidad SET cupos = cupos - 1 WHERE id = ? AND cupos > 0');
    $up->bind_param('i', $idDisp); $up->execute();
    $ok = $up->affected_rows === 1;
    $up->close();
    if (!$ok) { $conexion->rollback(); json_out(['ok' => false, 'mensaje' => 'Ese horario ya no tiene cupos. Elige otro.']); }

    $ins = $conexion->prepare('INSERT INTO solicitudes_tutoria (matricula, id_curso, semestre, id_disp) VALUES (?, ?, ?, ?)');
    $ins->bind_param('sisi', $mat, $idCurso, $sem, $idDisp); $ins->execute();
    $ins->close();
    $conexion->commit();
    json_out(['ok' => true]);
  }

  // ---------------- CANCELAR (devuelve el cupo) ----------------
  if ($accion === 'cancelar') {
    $id = (int)($_POST['id'] ?? 0);
    $conexion->begin_transaction();
    $st = $conexion->prepare('SELECT id_disp FROM solicitudes_tutoria WHERE id = ? AND matricula = ? FOR UPDATE');
    $st->bind_param('is', $id, $mat); $st->execute();
    $fila = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$fila) { $conexion->rollback(); json_out(['ok' => false, 'mensaje' => 'Solicitud no encontrada.']); }

    $del = $conexion->prepare('DELETE FROM solicitudes_tutoria WHERE id = ?');
    $del->bind_param('i', $id); $del->execute(); $del->close();
    $up = $conexion->prepare('UPDATE disponibilidad SET cupos = cupos + 1 WHERE id = ?');
    $up->bind_param('i', $fila['id_disp']); $up->execute(); $up->close();
    $conexion->commit();
    json_out(['ok' => true]);
  }

  json_out(['ok' => false, 'mensaje' => 'Acción no válida.'], 400);
} catch (Throwable $ex) {
  try { $conexion->rollback(); } catch (Throwable $x) {}
  json_out(['ok' => false, 'mensaje' => 'Error en la base de datos.'], 500);
}