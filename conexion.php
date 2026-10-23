<?php
// ============================================================
//  conexion.php  -  Conexión y funciones compartidas
// ============================================================
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

const SEMESTRE    = '2026-II';                  // debe coincidir con matriculas.semestre
const NOTA_MINIMA = 10.5;                       // promedio menor a esto => puede solicitar tutoría
const UNIDADES    = ['PC1', 'PC2', 'Parcial', 'Final'];

// ====== NOMBRES REALES DE TUS TABLAS EN NAVICAT (cámbialos si se llaman distinto) ======
const TABLA_MATRICULAS = 'sheet3';          // la que tiene: id_matricula, matricula, id_curso, semestre
const TABLA_CURSOS     = 'sheet2';              // la que tiene: id_curso, codigo_curso, nombre_curso, carrera, ciclo

$conexion = new mysqli('localhost', 'root', '', 'universidad');
$conexion->set_charset('utf8mb4');

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function json_out($datos, $codigo = 200) {
  http_response_code($codigo);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($datos, JSON_UNESCAPED_UNICODE);
  exit;
}

function alumnoActual($conexion) {
  if (empty($_SESSION['email'])) return null;
  $st = $conexion->prepare('SELECT * FROM sheet1 WHERE email_institucional = ? LIMIT 1');
  $st->bind_param('s', $_SESSION['email']);
  $st->execute();
  $fila = $st->get_result()->fetch_assoc();
  $st->close();
  return $fila ?: null;
}

function horarioTexto($dia, $ini, $fin) {
  return $dia . ' ' . substr($ini, 0, 5) . ' - ' . substr($fin, 0, 5);
}

// Cursos en los que el alumno está matriculado este semestre: [id_curso => fila]
function cursosMatriculados($conexion, $mat) {
  $sem = SEMESTRE;
  $st = $conexion->prepare(
    'SELECT c.id_curso, c.codigo_curso, c.nombre_curso, c.area
       FROM `' . TABLA_MATRICULAS . '` m JOIN `' . TABLA_CURSOS . '` c ON c.id_curso = m.id_curso
      WHERE m.matricula = ? AND m.semestre = ? ORDER BY c.nombre_curso');
  $st->bind_param('ss', $mat, $sem);
  $st->execute();
  $out = [];
  foreach ($st->get_result() as $r) { $out[(int)$r['id_curso']] = $r; }
  $st->close();
  return $out;
}

function estaMatriculado($conexion, $mat, $idCurso) {
  $sem = SEMESTRE;
  $st = $conexion->prepare('SELECT 1 FROM `' . TABLA_MATRICULAS . '` WHERE matricula = ? AND id_curso = ? AND semestre = ? LIMIT 1');
  $st->bind_param('sis', $mat, $idCurso, $sem);
  $st->execute();
  $ok = $st->get_result()->num_rows > 0;
  $st->close();
  return $ok;
}

// Promedio de las notas registradas de un curso (null si no hay notas)
function promedioCurso($conexion, $mat, $idCurso) {
  $sem = SEMESTRE;
  $st = $conexion->prepare('SELECT AVG(nota) AS p FROM notas WHERE matricula = ? AND id_curso = ? AND semestre = ?');
  $st->bind_param('sis', $mat, $idCurso, $sem);
  $st->execute();
  $p = $st->get_result()->fetch_assoc()['p'];
  $st->close();
  return $p === null ? null : (float)$p;
}

// Tutorías solicitadas por el alumno: [id_curso => fila]
function solicitudes($conexion, $mat) {
  $sem = SEMESTRE;
  $st = $conexion->prepare(
    'SELECT s.id, s.id_curso, c.nombre_curso, t.nombre AS tutor, d.dia, d.hora_inicio, d.hora_fin, d.modalidad
       FROM solicitudes_tutoria s
       JOIN `' . TABLA_CURSOS . '` c ON c.id_curso = s.id_curso
       JOIN disponibilidad d ON d.id = s.id_disp
       JOIN tutores t        ON t.id = d.id_tutor
      WHERE s.matricula = ? AND s.semestre = ? ORDER BY s.id');
  $st->bind_param('ss', $mat, $sem);
  $st->execute();
  $out = [];
  foreach ($st->get_result() as $r) {
    $r['horario'] = horarioTexto($r['dia'], $r['hora_inicio'], $r['hora_fin']);
    $out[(int)$r['id_curso']] = $r;
  }
  $st->close();
  return $out;
}

// Horarios con cupos de los tutores de un área
function horariosDisponibles($conexion, $area) {
  $st = $conexion->prepare(
    "SELECT d.id, t.nombre AS tutor, d.dia, d.hora_inicio, d.hora_fin, d.modalidad, d.cupos
       FROM disponibilidad d JOIN tutores t ON t.id = d.id_tutor
      WHERE t.area = ? AND d.cupos > 0
      ORDER BY FIELD(d.dia,'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'), d.hora_inicio");
  $st->bind_param('s', $area);
  $st->execute();
  $out = [];
  foreach ($st->get_result() as $r) {
    $r['horario'] = horarioTexto($r['dia'], $r['hora_inicio'], $r['hora_fin']);
    $out[] = $r;
  }
  $st->close();
  return $out;
}