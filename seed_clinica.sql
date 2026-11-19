
-- ==============================================
-- DUCCA - BASE DE DATOS COMPLETA
-- Compatible con MySQL / MariaDB de XAMPP
-- ==============================================
-- IMPORTAR SOLO SOBRE UNA BASE NUEVA Y VACÍA.
-- No incluye pacientes ni usuarios anteriores.
-- ==============================================

CREATE DATABASE IF NOT EXISTS clinica
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE clinica;

-- ==============================================
-- 1. USUARIOS
-- ==============================================

CREATE TABLE usuario (
    dni_u INT NOT NULL,
    nombre_u VARCHAR(30) NOT NULL,
    apellido VARCHAR(30) NOT NULL,
    mail VARCHAR(100) NOT NULL,
    contrasena VARCHAR(255) NOT NULL,

    PRIMARY KEY (dni_u)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- 2. OBRAS SOCIALES
-- ==============================================

CREATE TABLE obra_social (
    id_obra_social INT NOT NULL AUTO_INCREMENT,
    nombre_os VARCHAR(100) NOT NULL,
    activa TINYINT(1) NOT NULL DEFAULT 1,

    PRIMARY KEY (id_obra_social),
    UNIQUE KEY uq_obra_social_nombre (nombre_os)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- 3. TRABAJOS
-- ==============================================

CREATE TABLE trabajo (
    id_trabajo INT NOT NULL AUTO_INCREMENT,
    nombre_t VARCHAR(50) NOT NULL,

    PRIMARY KEY (id_trabajo),
    UNIQUE KEY uq_trabajo_nombre (nombre_t)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- 4. EXPOSICIONES
-- ==============================================

CREATE TABLE exposicion (
    id_exposicion INT NOT NULL AUTO_INCREMENT,
    nombre_ex VARCHAR(50) NOT NULL,

    PRIMARY KEY (id_exposicion),
    UNIQUE KEY uq_exposicion_nombre (nombre_ex)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- 5. ESTUDIOS
-- ==============================================

CREATE TABLE estudio (
    id_estudio INT NOT NULL AUTO_INCREMENT,
    nombre_es VARCHAR(50) NOT NULL,

    PRIMARY KEY (id_estudio),
    UNIQUE KEY uq_estudio_nombre (nombre_es)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- 6. PACIENTES
-- ==============================================

CREATE TABLE paciente (
    dni_p INT NOT NULL,

    nombre_p VARCHAR(30) NOT NULL,
    apellido VARCHAR(30) NOT NULL,

    cuil VARCHAR(11) DEFAULT NULL,
    telefono VARCHAR(20) NOT NULL,
    mail VARCHAR(100) NOT NULL,

    id_obra_social INT DEFAULT NULL,
    id_trabajo INT DEFAULT NULL,

    domicilio_calle VARCHAR(100) DEFAULT NULL,
    domicilio_altura VARCHAR(15) DEFAULT NULL,
    domicilio_piso_depto VARCHAR(30) DEFAULT NULL,
    domicilio_localidad VARCHAR(100) DEFAULT NULL,
    domicilio_provincia VARCHAR(100) DEFAULT NULL,
    domicilio_codigo_postal VARCHAR(15) DEFAULT NULL,
    domicilio_pais VARCHAR(100) DEFAULT NULL,

    estado ENUM('activo', 'archivado')
        NOT NULL DEFAULT 'activo',

    fecha_archivado DATETIME DEFAULT NULL,

    PRIMARY KEY (dni_p),

    KEY idx_paciente_obra (id_obra_social),
    KEY idx_paciente_trabajo (id_trabajo),
    KEY idx_paciente_estado (estado),

    CONSTRAINT fk_paciente_obra
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
-- 7. RELACIÓN TRABAJO - EXPOSICIÓN
-- ==============================================

CREATE TABLE trabajo_exposicion (
    id_trabajo INT NOT NULL,
    id_exposicion INT NOT NULL,

    PRIMARY KEY (id_trabajo, id_exposicion),

    KEY idx_te_exposicion (id_exposicion),

    CONSTRAINT fk_te_trabajo
        FOREIGN KEY (id_trabajo)
        REFERENCES trabajo(id_trabajo)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_te_exposicion
        FOREIGN KEY (id_exposicion)
        REFERENCES exposicion(id_exposicion)
        ON UPDATE CASCADE
        ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- 8. RELACIÓN TRABAJO - ESTUDIO
-- ==============================================

CREATE TABLE trabajo_estudio (
    id_trabajo INT NOT NULL,
    id_estudio INT NOT NULL,

    PRIMARY KEY (id_trabajo, id_estudio),

    KEY idx_te_estudio (id_estudio),

    CONSTRAINT fk_trabajo_estudio_trabajo
        FOREIGN KEY (id_trabajo)
        REFERENCES trabajo(id_trabajo)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_trabajo_estudio_estudio
        FOREIGN KEY (id_estudio)
        REFERENCES estudio(id_estudio)
        ON UPDATE CASCADE
        ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- 9. RELACIÓN PACIENTE - EXPOSICIÓN
-- ==============================================

CREATE TABLE paciente_exposicion (
    dni_paciente INT NOT NULL,
    id_exposicion INT NOT NULL,

    PRIMARY KEY (dni_paciente, id_exposicion),

    KEY idx_pe_exposicion (id_exposicion),

    CONSTRAINT fk_paciente_exposicion_paciente
        FOREIGN KEY (dni_paciente)
        REFERENCES paciente(dni_p)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_paciente_exposicion_exposicion
        FOREIGN KEY (id_exposicion)
        REFERENCES exposicion(id_exposicion)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- 10. RELACIÓN PACIENTE - ESTUDIO
-- ==============================================

CREATE TABLE paciente_estudio (
    dni_paciente INT NOT NULL,
    id_estudio INT NOT NULL,

    estado ENUM('Pendiente', 'Realizado')
        NOT NULL DEFAULT 'Pendiente',

    PRIMARY KEY (dni_paciente, id_estudio),

    KEY idx_paciente_estudio_estudio (id_estudio),

    CONSTRAINT fk_paciente_estudio_paciente
        FOREIGN KEY (dni_paciente)
        REFERENCES paciente(dni_p)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_paciente_estudio_estudio
        FOREIGN KEY (id_estudio)
        REFERENCES estudio(id_estudio)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- 11. HISTORIAL MÉDICO
-- ==============================================

CREATE TABLE historial_medico (
    id_historial INT NOT NULL AUTO_INCREMENT,

    dni_paciente INT NOT NULL,
    id_estudio INT NOT NULL,

    fecha DATE NOT NULL,

    estado ENUM('Pendiente', 'Realizado')
        NOT NULL DEFAULT 'Pendiente',

    observaciones TEXT DEFAULT NULL,
    archivo VARCHAR(255) DEFAULT NULL,

    fecha_carga TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id_historial),

    KEY idx_historial_paciente (dni_paciente),
    KEY idx_historial_estudio (id_estudio),

    CONSTRAINT fk_historial_paciente
        FOREIGN KEY (dni_paciente)
        REFERENCES paciente(dni_p)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_historial_estudio
        FOREIGN KEY (id_estudio)
        REFERENCES estudio(id_estudio)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- 12. TURNOS
-- ==============================================

CREATE TABLE turno (
    id_turno INT NOT NULL AUTO_INCREMENT,

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

    observaciones TEXT DEFAULT NULL,

    fecha_creacion TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id_turno),

    KEY idx_turno_fecha (fecha, hora),
    KEY idx_turno_paciente (dni_paciente),
    KEY idx_turno_estado (estado),

    CONSTRAINT fk_turno_paciente
        FOREIGN KEY (dni_paciente)
        REFERENCES paciente(dni_p)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- 13. AUDITORÍA DE PACIENTES
-- ==============================================

CREATE TABLE auditoria_pacientes (
    id_auditoria INT NOT NULL AUTO_INCREMENT,

    dni_paciente INT NOT NULL,

    accion ENUM('archivar', 'reactivar')
        NOT NULL,

    fecha_accion DATETIME
        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    usuario_responsable VARCHAR(100)
        DEFAULT NULL,

    PRIMARY KEY (id_auditoria),

    KEY idx_auditoria_paciente (dni_paciente)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==============================================
-- CARGAR LAS 18 EXPOSICIONES ORIGINALES
-- ==============================================

INSERT INTO exposicion
(id_exposicion, nombre_ex)
VALUES
(1, 'Conducción de Grúas, Autoelevadores, etc.'),
(2, 'Altura'),
(3, 'Ruido'),
(4, 'Partículas (Polvo, Asbesto)'),
(5, 'Vibraciones'),
(6, 'Químicos/Hidrocarburos'),
(7, 'Estireno'),
(8, 'Etilbenceno'),
(9, 'Benceno'),
(10, 'Acrilonitrilo'),
(11, 'Fenol'),
(12, 'Hexano'),
(13, 'Tolueno'),
(14, 'Xilenos'),
(15, 'Esfuerzo con Columna/Lumbosacra'),
(16, 'Espacio confinado'),
(17, 'Brigadista'),
(18, 'Maquinaria pesada/Liviana');


-- ==============================================
-- VERIFICACIONES
-- ==============================================

SELECT COUNT(*) AS cantidad_tablas
FROM information_schema.tables
WHERE table_schema = 'clinica'
  AND table_type = 'BASE TABLE';

SELECT COUNT(*) AS exposiciones_cargadas
FROM exposicion;

SELECT nombre_ex
FROM exposicion
ORDER BY nombre_ex;
