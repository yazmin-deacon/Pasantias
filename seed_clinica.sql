
CREATE DATABASE IF NOT EXISTS clinica
CHARACTER SET utf8mb4
COLLATE utf8mb4_general_ci;

USE clinica;

-- Eliminar las tablas en orden para respetar las claves foraneas
DROP TABLE IF EXISTS paciente_estudio;
DROP TABLE IF EXISTS trabajo_estudio;
DROP TABLE IF EXISTS trabajo_exposicion;
DROP TABLE IF EXISTS paciente;
DROP TABLE IF EXISTS usuario;
DROP TABLE IF EXISTS estudio;
DROP TABLE IF EXISTS exposicion;
DROP TABLE IF EXISTS trabajo;

-- Tabla de trabajos
CREATE TABLE trabajo (
    id_trabajo INT PRIMARY KEY,
    nombre_t VARCHAR(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

-- Tabla de pacientes
CREATE TABLE paciente (
    dni_p INT PRIMARY KEY,
    nombre_p VARCHAR(30) NOT NULL,
    apellido VARCHAR(30) NOT NULL,
    cuil VARCHAR(11),
    telefono VARCHAR(20) NOT NULL,
    mail VARCHAR(100) NOT NULL,
    obra_social VARCHAR(50) NOT NULL,
    id_trabajo INT,
    FOREIGN KEY (id_trabajo)
        REFERENCES trabajo(id_trabajo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

-- Tabla de usuarios
CREATE TABLE usuario (
    dni_u INT PRIMARY KEY,
    nombre_u VARCHAR(30) NOT NULL,
    apellido VARCHAR(30) NOT NULL,
    mail VARCHAR(100) NOT NULL,
    contrasena VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

-- Tabla de exposiciones
CREATE TABLE exposicion (
    id_exposicion INT PRIMARY KEY,
    nombre_ex VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

-- Tabla de estudios
CREATE TABLE estudio (
    id_estudio INT PRIMARY KEY,
    nombre_es VARCHAR(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

-- Relacion entre trabajos y exposiciones
CREATE TABLE trabajo_exposicion (
    id_trabajo INT,
    id_exposicion INT,
    PRIMARY KEY (id_trabajo, id_exposicion),
    FOREIGN KEY (id_trabajo)
        REFERENCES trabajo(id_trabajo),
    FOREIGN KEY (id_exposicion)
        REFERENCES exposicion(id_exposicion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

-- Relacion entre trabajos y estudios
CREATE TABLE trabajo_estudio (
    id_trabajo INT,
    id_estudio INT,
    PRIMARY KEY (id_trabajo, id_estudio),
    FOREIGN KEY (id_trabajo)
        REFERENCES trabajo(id_trabajo),
    FOREIGN KEY (id_estudio)
        REFERENCES estudio(id_estudio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

-- Relacion entre pacientes y estudios
CREATE TABLE paciente_estudio (
    dni_paciente INT,
    id_estudio INT,
    PRIMARY KEY (dni_paciente, id_estudio),
    FOREIGN KEY (dni_paciente)
        REFERENCES paciente(dni_p),
    FOREIGN KEY (id_estudio)
        REFERENCES estudio(id_estudio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

-- Datos de prueba
INSERT INTO trabajo (id_trabajo, nombre_t) VALUES
(1, 'Administrativo');

INSERT INTO usuario (dni_u, nombre_u, apellido, mail, contrasena) VALUES
(12345678, 'Nacho', 'Puto', 'nacho.puto@epet20.edu.ar', 'nachoputo');

INSERT INTO exposicion (id_exposicion, nombre_ex) VALUES
(1, 'Ruido'),
(2, 'Sustancias');

INSERT INTO estudio (id_estudio, nombre_es) VALUES
(1, 'Radiografia'),
(2, 'Analisis de sangre');

INSERT INTO trabajo_exposicion (id_trabajo, id_exposicion) VALUES
(1, 1),
(1, 2);

INSERT INTO trabajo_estudio (id_trabajo, id_estudio) VALUES
(1, 1),
(1, 2);

INSERT INTO paciente (
    dni_p, nombre_p, apellido, cuil, telefono,
    mail, obra_social, id_trabajo
) VALUES (
    12345678, 'Juan', 'Perez', '20345678901', '1234567890',
    'juan.perez@epet20.edu.ar', 'Obra Social XYZ', 1
);

INSERT INTO paciente_estudio (dni_paciente, id_estudio) VALUES
(12345678, 1);

-- Comprobar el listado de pacientes
SELECT
    paciente.dni_p,
    paciente.nombre_p,
    paciente.apellido,
    paciente.cuil,
    paciente.telefono,
    paciente.mail,
    paciente.obra_social,
    trabajo.nombre_t AS trabajo
FROM paciente
LEFT JOIN trabajo
    ON paciente.id_trabajo = trabajo.id_trabajo
ORDER BY paciente.dni_p;
