<?php
// ============================================================
//  notas_api.php  -  Guardar / eliminar las notas del alumno
// ============================================================
require 'conexion.php';

$est = alumnoActual($conexion);
if (!$est) json_out(['ok' => false, 'mensaje' => 'Sesión no válida.'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);

$mat    = (string)$est['matricula'];
$sem    = SEMESTRE;
$accion = $_POST['accion'] ?? '';

try {
  if ($accion === 'guardar') {
    $idCurso = (int)($_POST['id_curso'] ?? 0);
    $unidad  = $_POST['unidad'] ?? '';
    $nota    = $_POST['nota'] ?? '';

    if (!estaMatriculado($conexion, $mat, $idCurso)) json_out(['ok' => false, 'mensaje' => 'Ese curso no está en tu matrícula.']);
    if (!in_array($unidad, UNIDADES, true))          json_out(['ok' => false, 'mensaje' => 'Unidad no válida.']);
    if (!is_numeric($nota) || $nota < 0 || $nota > 20) json_out(['ok' => false, 'mensaje' => 'La nota debe estar entre 0 y 20.']);
    $nota = round((float)$nota, 1);

    $st = $conexion->prepare(
      'INSERT INTO notas (matricula, id_curso, semestre, unidad, nota) VALUES (?, ?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE nota = VALUES(nota)');
    $st->bind_param('sissd', $mat, $idCurso, $sem, $unidad, $nota);
    $st->execute();
    json_out(['ok' => true]);
  }

  if ($accion === 'eliminar') {
    $id = (int)($_POST['id'] ?? 0);
    $st = $conexion->prepare('DELETE FROM notas WHERE id = ? AND matricula = ?');
    $st->bind_param('is', $id, $mat);
    $st->execute();
    json_out(['ok' => true]);
  }

  json_out(['ok' => false, 'mensaje' => 'Acción no válida.'], 400);
} catch (Throwable $ex) {
  json_out(['ok' => false, 'mensaje' => 'Error en la base de datos.'], 500);
}
