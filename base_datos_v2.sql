-- ============================================================
--  MIGRACIÓN v2  -  Ejecutar en Navicat (base: universidad)
--  OJO: borra las 4 tablas de prueba anteriores (notas, tutores,
--  disponibilidad, asignaciones) y las crea de nuevo.
--  NO toca sheet1 (estudiantes) ni sheet3 (matrículas); solo agrega "area" a sheet2 (cursos).
-- ============================================================

DROP TABLE IF EXISTS asignaciones, solicitudes_tutoria, disponibilidad, tutores, notas;

-- 1) Área de tutoría de cada curso (Matemática / Programación / Especialidad)
--    Si ya existe la columna, comenta esta línea.
ALTER TABLE sheet2 ADD COLUMN area VARCHAR(30) NULL;

UPDATE sheet2 SET area = 'Matemática'   WHERE nombre_curso LIKE '%Matem%' OR nombre_curso LIKE '%Cálculo%' OR nombre_curso LIKE '%Calculo%' OR nombre_curso LIKE '%Álgebra%' OR nombre_curso LIKE '%Algebra%';
UPDATE sheet2 SET area = 'Programación' WHERE nombre_curso LIKE '%Program%' OR nombre_curso LIKE '%Algoritm%' OR nombre_curso LIKE '%Estructura de Datos%';
UPDATE sheet2 SET area = 'Especialidad' WHERE area IS NULL AND (nombre_curso LIKE '%Estad%' OR nombre_curso LIKE '%Probab%' OR nombre_curso LIKE '%Muestreo%' OR nombre_curso LIKE '%Regres%' OR nombre_curso LIKE '%Modelos%' OR nombre_curso LIKE '%Inferenc%');
-- Los cursos que queden con area NULL no tendrán tutoría.
-- Ajusta a mano lo que haga falta, por ejemplo:
-- UPDATE sheet2 SET area = 'Matemática' WHERE codigo_curso = 'EST05';

-- 2) Notas que registra cada alumno (ahora por id_curso)
CREATE TABLE notas (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  matricula VARCHAR(20)  NOT NULL,
  id_curso  INT          NOT NULL,
  semestre  VARCHAR(10)  NOT NULL,
  unidad    VARCHAR(20)  NOT NULL,          -- PC1, PC2, Parcial, Final
  nota      DECIMAL(4,1) NOT NULL,
  UNIQUE KEY uq_nota (matricula, id_curso, semestre, unidad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Tutores, por área
CREATE TABLE tutores (
  id     INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  area   VARCHAR(30)  NOT NULL              -- Matemática / Programación / Especialidad
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) Horarios de cada tutor (cupos = vacantes libres)
CREATE TABLE disponibilidad (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  id_tutor    INT NOT NULL,
  dia         VARCHAR(15) NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin    TIME NOT NULL,
  modalidad   VARCHAR(15) NOT NULL,         -- Virtual / Presencial
  cupos       INT NOT NULL DEFAULT 0,
  FOREIGN KEY (id_tutor) REFERENCES tutores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5) Tutorías que los alumnos solicitaron
CREATE TABLE solicitudes_tutoria (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  matricula VARCHAR(20) NOT NULL,
  id_curso  INT NOT NULL,
  semestre  VARCHAR(10) NOT NULL,
  id_disp   INT NOT NULL,
  fecha     DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sol (matricula, id_curso, semestre),
  FOREIGN KEY (id_disp) REFERENCES disponibilidad(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Datos de ejemplo (cámbialos por los reales) ----------
INSERT INTO tutores (id, nombre, area) VALUES
  (1, 'Prof. Ana López',   'Matemática'),
  (2, 'Prof. Carlos Vega', 'Matemática'),
  (3, 'Prof. Luis Quispe', 'Programación'),
  (4, 'Prof. Marta Rojas', 'Especialidad');

INSERT INTO disponibilidad (id_tutor, dia, hora_inicio, hora_fin, modalidad, cupos) VALUES
  (1, 'Jueves',    '18:00', '20:00', 'Virtual',    5),
  (2, 'Sábado',    '09:00', '11:00', 'Presencial', 3),
  (3, 'Martes',    '17:00', '19:00', 'Presencial', 4),
  (4, 'Miércoles', '16:00', '18:00', 'Virtual',    4);
