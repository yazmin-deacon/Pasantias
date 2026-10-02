-- =====================================================================
--  Seed para el sistema CRUD de las Clases 11, 12 y 13
--  Base: escuela · Tablas: cursos, alumnos
--
--  Cómo usarlo en XAMPP:
--    1. Abrí phpMyAdmin (http://localhost/phpmyadmin).
--    2. Pestaña "SQL" (sin seleccionar ninguna base) → pegá este
--       archivo completo → Continuar.
--       (o: Importar → elegir seed_escuela.sql → Continuar)
--    3. Entrá a http://localhost/crud/listar.php: tenés que ver
--       "Cantidad de alumnos cargados: 10".
--
--  Se puede ejecutar todas las veces que quieras: borra y vuelve a
--  crear las dos tablas, así que sirve para "resetear" la base
--  después de probar altas, bajas y modificaciones.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS escuela
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE escuela;

-- Se borra primero alumnos porque tiene la clave foránea hacia cursos
DROP TABLE IF EXISTS alumnos;
DROP TABLE IF EXISTS cursos;

CREATE TABLE cursos (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE alumnos (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    nombre  VARCHAR(50)  NOT NULL,
    edad    INT          NOT NULL,
    email   VARCHAR(100) NOT NULL,
    idCurso INT          NOT NULL,
    FOREIGN KEY (idCurso) REFERENCES cursos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===== 3 cursos =====
INSERT INTO cursos (nombre) VALUES
    ('5° A'),
    ('5° B'),
    ('6° A');

-- ===== 10 alumnos =====
-- Hay acentos, eñe y un apóstrofo a propósito: sirven para comprobar
-- el charset utf8mb4 (Clase 11) y las consultas preparadas (Clase 12).
INSERT INTO alumnos (nombre, edad, email, idCurso) VALUES
    ('Ana Pérez',          17, 'ana.perez@epet20.edu.ar',      1),
    ('Lucía Paz',          17, 'lucia.paz@epet20.edu.ar',      2),
    ('Tomás Ruiz',         16, 'tomas.ruiz@epet20.edu.ar',     1),
    ('Patricio O''Connor', 18, 'patricio.oconnor@epet20.edu.ar', 3),
    ('Valentina Muñoz',    17, 'valentina.munoz@epet20.edu.ar', 2),
    ('Joaquín Núñez',      16, 'joaquin.nunez@epet20.edu.ar',  1),
    ('Martina Sosa',       17, 'martina.sosa@epet20.edu.ar',   3),
    ('Bruno D''Alessandro',18, 'bruno.dalessandro@epet20.edu.ar', 2),
    ('Camila Ferreyra',    17, 'camila.ferreyra@epet20.edu.ar', 3),
    ('Nicolás Ibáñez',     16, 'nicolas.ibanez@epet20.edu.ar', 1);

-- Comprobación rápida (la misma consulta del bloque 2 de listar.php)
SELECT alumnos.id, alumnos.nombre, alumnos.edad, alumnos.email, cursos.nombre AS curso
FROM alumnos
INNER JOIN cursos ON alumnos.idCurso = cursos.id
ORDER BY alumnos.id;
