
CREATE DATABASE IF NOT EXISTS clinica
CHARACTER SET utf8mb4
COLLATE utf8mb4_general_ci;

USE clinica;

-- =========================================
-- 1. ELIMINAR TABLAS ANTERIORES
-- =========================================

DROP TABLE IF EXISTS historial_medico;
DROP TABLE IF EXISTS paciente_estudio;
DROP TABLE IF EXISTS paciente_exposicion;
DROP TABLE IF EXISTS trabajo_estudio;
DROP TABLE IF EXISTS trabajo_exposicion;
DROP TABLE IF EXISTS paciente;
DROP TABLE IF EXISTS usuario;
DROP TABLE IF EXISTS estudio;
DROP TABLE IF EXISTS exposicion;
DROP TABLE IF EXISTS trabajo;
DROP TABLE IF EXISTS obra_social;

-- =========================================
-- 2. TABLA DE TRABAJOS
-- =========================================

CREATE TABLE trabajo (
    id_trabajo INT NOT NULL AUTO_INCREMENT,
    nombre_t VARCHAR(50) NOT NULL,

    PRIMARY KEY (id_trabajo),
    UNIQUE (nombre_t)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

-- =========================================
-- 3. TABLA DE OBRAS SOCIALES
-- =========================================

CREATE TABLE obra_social (
    id_obra_social INT NOT NULL AUTO_INCREMENT,
    nombre_os VARCHAR(100) NOT NULL,
    activa BOOLEAN NOT NULL DEFAULT TRUE,

    PRIMARY KEY (id_obra_social),
    UNIQUE (nombre_os)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

-- =========================================
-- 4. TABLA DE PACIENTES
-- =========================================

CREATE TABLE paciente (
    dni_p INT PRIMARY KEY,
    nombre_p VARCHAR(30) NOT NULL,
    apellido VARCHAR(30) NOT NULL,
    cuil VARCHAR(11),
    telefono VARCHAR(20) NOT NULL,
    mail VARCHAR(100) NOT NULL,

    id_obra_social INT NULL,
    id_trabajo INT NULL,

    FOREIGN KEY (id_obra_social)
        REFERENCES obra_social(id_obra_social),

    FOREIGN KEY (id_trabajo)
        REFERENCES trabajo(id_trabajo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

-- =========================================
-- 5. TABLA DE USUARIOS
-- =========================================

CREATE TABLE usuario (
    dni_u INT PRIMARY KEY,
    nombre_u VARCHAR(30) NOT NULL,
    apellido VARCHAR(30) NOT NULL,
    mail VARCHAR(100) NOT NULL,
    contrasena VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

-- =========================================
-- 6. CATÁLOGO DE EXPOSICIONES
-- =========================================

CREATE TABLE exposicion (
    id_exposicion INT NOT NULL AUTO_INCREMENT,
    nombre_ex VARCHAR(50) NOT NULL,

    PRIMARY KEY (id_exposicion),
    UNIQUE (nombre_ex)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

-- =========================================
-- 7. CATÁLOGO DE ESTUDIOS
-- =========================================

CREATE TABLE estudio (
    id_estudio INT NOT NULL AUTO_INCREMENT,
    nombre_es VARCHAR(50) NOT NULL,

    PRIMARY KEY (id_estudio),
    UNIQUE (nombre_es)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

-- =========================================
-- 8. EXPOSICIONES HABITUALES POR TRABAJO
-- =========================================

CREATE TABLE trabajo_exposicion (
    id_trabajo INT NOT NULL,
    id_exposicion INT NOT NULL,

    PRIMARY KEY (id_trabajo, id_exposicion),

    FOREIGN KEY (id_trabajo)
        REFERENCES trabajo(id_trabajo),

    FOREIGN KEY (id_exposicion)
        REFERENCES exposicion(id_exposicion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

-- =========================================
-- 9. ESTUDIOS HABITUALES POR TRABAJO
-- =========================================

CREATE TABLE trabajo_estudio (
    id_trabajo INT NOT NULL,
    id_estudio INT NOT NULL,

    PRIMARY KEY (id_trabajo, id_estudio),

    FOREIGN KEY (id_trabajo)
        REFERENCES trabajo(id_trabajo),

    FOREIGN KEY (id_estudio)
        REFERENCES estudio(id_estudio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

-- =========================================
-- 10. EXPOSICIONES PARTICULARES POR PACIENTE
-- =========================================

CREATE TABLE paciente_exposicion (
    dni_paciente INT NOT NULL,
    id_exposicion INT NOT NULL,

    PRIMARY KEY (dni_paciente, id_exposicion),

    FOREIGN KEY (dni_paciente)
        REFERENCES paciente(dni_p),

    FOREIGN KEY (id_exposicion)
        REFERENCES exposicion(id_exposicion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

-- =========================================
-- 11. ESTUDIOS QUE DEBE REALIZARSE CADA PACIENTE
-- =========================================

CREATE TABLE paciente_estudio (
    dni_paciente INT NOT NULL,
    id_estudio INT NOT NULL,

    PRIMARY KEY (dni_paciente, id_estudio),

    FOREIGN KEY (dni_paciente)
        REFERENCES paciente(dni_p),

    FOREIGN KEY (id_estudio)
        REFERENCES estudio(id_estudio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

-- =========================================
-- 12. HISTORIAL MÉDICO
-- =========================================

CREATE TABLE historial_medico (
    id_historial INT AUTO_INCREMENT PRIMARY KEY,
    dni_paciente INT NOT NULL,
    id_estudio INT NOT NULL,

    fecha DATE NOT NULL,

    estado ENUM('Pendiente', 'Realizado')
        NOT NULL DEFAULT 'Pendiente',

    observaciones TEXT NULL,
    archivo VARCHAR(255) NULL,

    fecha_carga TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (dni_paciente)
        REFERENCES paciente(dni_p),

    FOREIGN KEY (id_estudio)
        REFERENCES estudio(id_estudio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

-- =========================================
-- 13. DATOS INICIALES DE TRABAJOS
-- =========================================

INSERT INTO trabajo (nombre_t)
VALUES ('Administrativo');

-- =========================================
-- 14. DATOS INICIALES DE OBRAS SOCIALES
-- =========================================

INSERT INTO obra_social (nombre_os)
VALUES
('OSDE'),
('Swiss Medical'),
('PAMI'),
('Obra Social XYZ');

-- =========================================
-- 15. DATOS INICIALES DE EXPOSICIONES
-- =========================================

INSERT INTO exposicion (nombre_ex)
VALUES
('Ruido'),
('Sustancias');

-- =========================================
-- 16. DATOS INICIALES DE ESTUDIOS
-- =========================================

INSERT INTO estudio (nombre_es)
VALUES
('Radiografia'),
('Analisis de sangre');

-- =========================================
-- 17. EXPOSICIONES ASOCIADAS AL TRABAJO
-- =========================================

INSERT INTO trabajo_exposicion
(id_trabajo, id_exposicion)
VALUES
(1, 1),
(1, 2);

-- =========================================
-- 18. ESTUDIOS ASOCIADOS AL TRABAJO
-- =========================================

INSERT INTO trabajo_estudio
(id_trabajo, id_estudio)
VALUES
(1, 1),
(1, 2);

-- =========================================
-- 19. PACIENTE DE PRUEBA
-- =========================================

INSERT INTO paciente (
    dni_p,
    nombre_p,
    apellido,
    cuil,
    telefono,
    mail,
    id_obra_social,
    id_trabajo
)
VALUES (
    12345678,
    'Juan',
    'Perez',
    '20345678901',
    '1234567890',
    'juan.perez@epet20.edu.ar',
    4,
    1
);

-- =========================================
-- 20. EXPOSICIONES DEL PACIENTE
-- =========================================

INSERT INTO paciente_exposicion (
    dni_paciente,
    id_exposicion
)
VALUES
(12345678, 1);

-- =========================================
-- 21. ESTUDIOS DEL PACIENTE
-- =========================================

INSERT INTO paciente_estudio (
    dni_paciente,
    id_estudio
)
VALUES
(12345678, 1);

-- =========================================
-- 22. COMPROBAR OBRAS SOCIALES
-- =========================================

SELECT
    id_obra_social,
    nombre_os,
    activa
FROM obra_social
ORDER BY nombre_os;

-- =========================================
-- 23. COMPROBAR LISTADO DE PACIENTES
-- =========================================

SELECT
    paciente.dni_p,
    paciente.nombre_p,
    paciente.apellido,
    paciente.cuil,
    paciente.telefono,
    paciente.mail,

    COALESCE(
        obra_social.nombre_os,
        'Particular / Sin obra social'
    ) AS obra_social,

    trabajo.nombre_t AS trabajo

FROM paciente

LEFT JOIN obra_social
    ON paciente.id_obra_social =
       obra_social.id_obra_social

LEFT JOIN trabajo
    ON paciente.id_trabajo =
       trabajo.id_trabajo

ORDER BY paciente.dni_p;
