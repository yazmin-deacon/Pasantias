
-- ==============================================
-- BASE DE DATOS: CLINICA
-- SISTEMA DE GESTION DE PACIENTES
-- ==============================================

CREATE DATABASE IF NOT EXISTS clinica
CHARACTER SET utf8mb4
COLLATE utf8mb4_general_ci;

USE clinica;


-- ==============================================
-- 1. TRABAJOS
-- ==============================================

CREATE TABLE IF NOT EXISTS trabajo (
    id_trabajo INT AUTO_INCREMENT PRIMARY KEY,
    nombre_t VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- 2. OBRAS SOCIALES
-- ==============================================

CREATE TABLE IF NOT EXISTS obra_social (
    id_obra_social INT AUTO_INCREMENT PRIMARY KEY,
    nombre_os VARCHAR(100) NOT NULL UNIQUE,
    activa TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- 3. PACIENTES
-- ==============================================

CREATE TABLE IF NOT EXISTS paciente (
    dni_p INT PRIMARY KEY,
    nombre_p VARCHAR(30) NOT NULL,
    apellido VARCHAR(30) NOT NULL,
    cuil VARCHAR(11),
    telefono VARCHAR(20) NOT NULL,
    mail VARCHAR(100) NOT NULL,

    id_obra_social INT NULL,
    id_trabajo INT NULL,

    CONSTRAINT fk_paciente_obra_social
        FOREIGN KEY (id_obra_social)
        REFERENCES obra_social(id_obra_social)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_paciente_trabajo
        FOREIGN KEY (id_trabajo)
        REFERENCES trabajo(id_trabajo)
        ON UPDATE CASCADE
        ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- 4. USUARIOS DEL SISTEMA
-- ==============================================

CREATE TABLE IF NOT EXISTS usuario (
    dni_u INT PRIMARY KEY,
    nombre_u VARCHAR(30) NOT NULL,
    apellido VARCHAR(30) NOT NULL,
    mail VARCHAR(100) NOT NULL,
    contrasena VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- 5. EXPOSICIONES
-- ==============================================

CREATE TABLE IF NOT EXISTS exposicion (
    id_exposicion INT AUTO_INCREMENT PRIMARY KEY,
    nombre_ex VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- 6. ESTUDIOS
-- ==============================================

CREATE TABLE IF NOT EXISTS estudio (
    id_estudio INT AUTO_INCREMENT PRIMARY KEY,
    nombre_es VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- 7. TRABAJO - EXPOSICION
-- ==============================================

CREATE TABLE IF NOT EXISTS trabajo_exposicion (
    id_trabajo INT NOT NULL,
    id_exposicion INT NOT NULL,

    PRIMARY KEY (id_trabajo, id_exposicion),

    CONSTRAINT fk_te_trabajo
        FOREIGN KEY (id_trabajo)
        REFERENCES trabajo(id_trabajo)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_te_exposicion
        FOREIGN KEY (id_exposicion)
        REFERENCES exposicion(id_exposicion)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- 8. TRABAJO - ESTUDIO
-- ==============================================

CREATE TABLE IF NOT EXISTS trabajo_estudio (
    id_trabajo INT NOT NULL,
    id_estudio INT NOT NULL,

    PRIMARY KEY (id_trabajo, id_estudio),

    CONSTRAINT fk_ts_trabajo
        FOREIGN KEY (id_trabajo)
        REFERENCES trabajo(id_trabajo)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_ts_estudio
        FOREIGN KEY (id_estudio)
        REFERENCES estudio(id_estudio)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- 9. PACIENTE - EXPOSICION
-- ==============================================

CREATE TABLE IF NOT EXISTS paciente_exposicion (
    dni_paciente INT NOT NULL,
    id_exposicion INT NOT NULL,

    PRIMARY KEY (dni_paciente, id_exposicion),

    CONSTRAINT fk_pe_paciente
        FOREIGN KEY (dni_paciente)
        REFERENCES paciente(dni_p)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_pe_exposicion
        FOREIGN KEY (id_exposicion)
        REFERENCES exposicion(id_exposicion)
        ON DELETE RESTRICT
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- 10. PACIENTE - ESTUDIO
-- ==============================================

CREATE TABLE IF NOT EXISTS paciente_estudio (
    dni_paciente INT NOT NULL,
    id_estudio INT NOT NULL,

    estado ENUM(
        'Pendiente',
        'Realizado'
    ) NOT NULL DEFAULT 'Pendiente',

    PRIMARY KEY (dni_paciente, id_estudio),

    CONSTRAINT fk_pest_paciente
        FOREIGN KEY (dni_paciente)
        REFERENCES paciente(dni_p)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_pest_estudio
        FOREIGN KEY (id_estudio)
        REFERENCES estudio(id_estudio)
        ON DELETE RESTRICT
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- 11. HISTORIAL MEDICO
-- ==============================================

CREATE TABLE IF NOT EXISTS historial_medico (
    id_historial INT AUTO_INCREMENT PRIMARY KEY,

    dni_paciente INT NOT NULL,
    id_estudio INT NOT NULL,

    fecha DATE NOT NULL,

    estado ENUM(
        'Pendiente',
        'Realizado'
    ) NOT NULL DEFAULT 'Pendiente',

    observaciones TEXT NULL,
    archivo VARCHAR(255) NULL,

    fecha_carga TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_historial_paciente (dni_paciente),
    INDEX idx_historial_fecha (fecha),

    CONSTRAINT fk_historial_paciente
        FOREIGN KEY (dni_paciente)
        REFERENCES paciente(dni_p)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_historial_estudio
        FOREIGN KEY (id_estudio)
        REFERENCES estudio(id_estudio)
        ON DELETE RESTRICT
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- 12. TURNOS
-- ==============================================

CREATE TABLE IF NOT EXISTS turno (
    id_turno INT AUTO_INCREMENT PRIMARY KEY,

    dni_paciente INT NOT NULL,

    fecha DATE NOT NULL,
    hora TIME NOT NULL,

    motivo VARCHAR(150) NOT NULL,

    estado ENUM(
        'Pendiente',
        'Confirmado',
        'Realizado',
        'Cancelado',
        'Ausente'
    ) NOT NULL DEFAULT 'Pendiente',

    observaciones TEXT NULL,

    fecha_creacion TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_turno_fecha (fecha, hora),
    INDEX idx_turno_paciente (dni_paciente),
    INDEX idx_turno_estado (estado),

    CONSTRAINT fk_turno_paciente
        FOREIGN KEY (dni_paciente)
        REFERENCES paciente(dni_p)
        ON DELETE RESTRICT
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- DATOS DE EJEMPLO
-- SOLO SE INSERTAN SI NO EXISTEN
-- ==============================================


-- ----------------------------------------------
-- TRABAJO
-- ----------------------------------------------

INSERT IGNORE INTO trabajo (nombre_t)
VALUES ('Administrativo');


-- ----------------------------------------------
-- OBRA SOCIAL
-- ----------------------------------------------

INSERT IGNORE INTO obra_social (nombre_os, activa)
VALUES ('Obra Social XYZ', 1);


-- ----------------------------------------------
-- EXPOSICIONES
-- ----------------------------------------------

INSERT IGNORE INTO exposicion (nombre_ex)
VALUES
    ('Ruido'),
    ('Sustancias');


-- ----------------------------------------------
-- ESTUDIOS
-- ----------------------------------------------

INSERT IGNORE INTO estudio (nombre_es)
VALUES
    ('Radiografia'),
    ('Analisis de sangre');


-- ----------------------------------------------
-- PACIENTE DE EJEMPLO
-- ----------------------------------------------

INSERT IGNORE INTO paciente (
    dni_p,
    nombre_p,
    apellido,
    cuil,
    telefono,
    mail,
    id_obra_social,
    id_trabajo
)
SELECT
    12345678,
    'Juan',
    'Perez',
    '20345678901',
    '1234567890',
    'juan.perez@epet20.edu.ar',
    os.id_obra_social,
    t.id_trabajo
FROM obra_social os
CROSS JOIN trabajo t
WHERE os.nombre_os = 'Obra Social XYZ'
  AND t.nombre_t = 'Administrativo';


-- ----------------------------------------------
-- EXPOSICION DEL PACIENTE DE EJEMPLO
-- ----------------------------------------------

INSERT IGNORE INTO paciente_exposicion (
    dni_paciente,
    id_exposicion
)
SELECT
    p.dni_p,
    e.id_exposicion
FROM paciente p
CROSS JOIN exposicion e
WHERE p.dni_p = 12345678
  AND e.nombre_ex = 'Ruido';


-- ----------------------------------------------
-- ESTUDIO DEL PACIENTE DE EJEMPLO
-- ----------------------------------------------

INSERT IGNORE INTO paciente_estudio (
    dni_paciente,
    id_estudio,
    estado
)
SELECT
    p.dni_p,
    e.id_estudio,
    'Pendiente'
FROM paciente p
CROSS JOIN estudio e
WHERE p.dni_p = 12345678
  AND e.nombre_es = 'Radiografia';


-- ==============================================
-- FIN DEL SEED
-- ==============================================

SELECT 'Base de datos preparada correctamente' AS mensaje;
