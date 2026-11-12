SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `estudio` (
  `id_estudio` int(11) NOT NULL,
  `nombre_es` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estudio`
--

INSERT INTO `estudio` (`id_estudio`, `nombre_es`) VALUES
(2, 'Analisis de sangre'),
(1, 'Radiografia'),
(3, 'resonancia');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `exposicion`
--

CREATE TABLE `exposicion` (
  `id_exposicion` int(11) NOT NULL,
  `nombre_ex` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `exposicion`
--

INSERT INTO `exposicion` (`id_exposicion`, `nombre_ex`) VALUES
(10, 'Acrilonitrilo'),
(2, 'Altura'),
(9, 'Benceno'),
(17, 'Brigadista'),
(1, 'Conducción de Grúas, Autoelevadores, etc.'),
(15, 'Esfuerzo con Columna/Lumbosacra'),
(16, 'Espacio confinado'),
(7, 'Estireno'),
(8, 'Etilbenceno'),
(11, 'Fenol'),
(12, 'Hexano'),
(18, 'Maquinaria pesada/Liviana'),
(4, 'Partículas (Polvo, Asbesto)'),
(6, 'Químicos/Hidrocarburos'),
(3, 'Ruido'),
(13, 'Tolueno'),
(5, 'Vibraciones'),
(14, 'Xilenos');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_medico`
--

CREATE TABLE `historial_medico` (
  `id_historial` int(11) NOT NULL,
  `dni_paciente` int(11) NOT NULL,
  `id_estudio` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `estado` enum('Pendiente','Realizado') NOT NULL DEFAULT 'Pendiente',
  `observaciones` text DEFAULT NULL,
  `archivo` varchar(255) DEFAULT NULL,
  `fecha_carga` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `historial_medico`
--

INSERT INTO `historial_medico` (`id_historial`, `dni_paciente`, `id_estudio`, `fecha`, `estado`, `observaciones`, `archivo`, `fecha_carga`) VALUES
(1, 48597674, 3, '2026-11-18', 'Realizado', 'putooo', NULL, '2026-11-07 16:21:22');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `obra_social`
--

CREATE TABLE `obra_social` (
  `id_obra_social` int(11) NOT NULL,
  `nombre_os` varchar(100) NOT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `obra_social`
--

INSERT INTO `obra_social` (`id_obra_social`, `nombre_os`, `activa`) VALUES
(1, 'OSDE', 1),
(2, 'Swiss Medical', 1),
(3, 'PAMI', 1),
(4, 'Obra Social XYZ', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `paciente`
--

CREATE TABLE `paciente` (
  `dni_p` int(11) NOT NULL,
  `nombre_p` varchar(30) NOT NULL,
  `apellido` varchar(30) NOT NULL,
  `cuil` varchar(11) DEFAULT NULL,
  `telefono` varchar(20) NOT NULL,
  `mail` varchar(100) NOT NULL,
  `id_obra_social` int(11) DEFAULT NULL,
  `id_trabajo` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `paciente`
--

INSERT INTO `paciente` (`dni_p`, `nombre_p`, `apellido`, `cuil`, `telefono`, `mail`, `id_obra_social`, `id_trabajo`) VALUES
(12345678, 'Juan', 'Perez', '20345678901', '1234567890', 'juan.perez@epet20.edu.ar', 4, 1),
(48597674, 'Nacho', 'Caminos', '01448597674', '2147483647', 'ignacioCaminos@gmail.com', NULL, 4);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `paciente_estudio`
--

CREATE TABLE `paciente_estudio` (
  `dni_paciente` int(11) NOT NULL,
  `id_estudio` int(11) NOT NULL,
  `estado` enum('Pendiente','Realizado') NOT NULL DEFAULT 'Pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `paciente_estudio`
--

INSERT INTO `paciente_estudio` (`dni_paciente`, `id_estudio`, `estado`) VALUES
(12345678, 1, 'Pendiente'),
(48597674, 3, 'Pendiente');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `paciente_exposicion`
--

CREATE TABLE `paciente_exposicion` (
  `dni_paciente` int(11) NOT NULL,
  `id_exposicion` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `paciente_exposicion`
--

INSERT INTO `paciente_exposicion` (`dni_paciente`, `id_exposicion`) VALUES
(12345678, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `trabajo`
--

CREATE TABLE `trabajo` (
  `id_trabajo` int(11) NOT NULL,
  `nombre_t` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `trabajo`
--

INSERT INTO `trabajo` (`id_trabajo`, `nombre_t`) VALUES
(1, 'Administrativo'),
(4, 'caca'),
(5, 'popo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `trabajo_estudio`
--

CREATE TABLE `trabajo_estudio` (
  `id_trabajo` int(11) NOT NULL,
  `id_estudio` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `trabajo_estudio`
--

INSERT INTO `trabajo_estudio` (`id_trabajo`, `id_estudio`) VALUES
(1, 1),
(1, 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `trabajo_exposicion`
--

CREATE TABLE `trabajo_exposicion` (
  `id_trabajo` int(11) NOT NULL,
  `id_exposicion` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `trabajo_exposicion`
--

INSERT INTO `trabajo_exposicion` (`id_trabajo`, `id_exposicion`) VALUES
(1, 1),
(1, 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `turno`
--

CREATE TABLE `turno` (
  `id_turno` int(11) NOT NULL,
  `dni_paciente` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `motivo` varchar(150) NOT NULL,
  `estado` enum('Pendiente','Confirmado','Realizado','Cancelado','Ausente') NOT NULL DEFAULT 'Pendiente',
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `dni_u` int(11) NOT NULL,
  `nombre_u` varchar(30) NOT NULL,
  `apellido` varchar(30) NOT NULL,
  `mail` varchar(100) NOT NULL,
  `contrasena` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`dni_u`, `nombre_u`, `apellido`, `mail`, `contrasena`) VALUES
(11111111, 'Pasantias', 'Practicas', 'pasantias@gmail.com', '$2y$10$uw5q6sgorVfbAyLjdC9OG.wPXKSFHW34px41n6S/VjoyZTBlKZrbu');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `estudio`
--
ALTER TABLE `estudio`
  ADD PRIMARY KEY (`id_estudio`),
  ADD UNIQUE KEY `nombre_es` (`nombre_es`);

--
-- Indices de la tabla `exposicion`
--
ALTER TABLE `exposicion`
  ADD PRIMARY KEY (`id_exposicion`),
  ADD UNIQUE KEY `nombre_ex` (`nombre_ex`);

--
-- Indices de la tabla `historial_medico`
--
ALTER TABLE `historial_medico`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `dni_paciente` (`dni_paciente`),
  ADD KEY `id_estudio` (`id_estudio`);

--
-- Indices de la tabla `obra_social`
--
ALTER TABLE `obra_social`
  ADD PRIMARY KEY (`id_obra_social`),
  ADD UNIQUE KEY `nombre_os` (`nombre_os`);

--
-- Indices de la tabla `paciente`
--
ALTER TABLE `paciente`
  ADD PRIMARY KEY (`dni_p`),
  ADD KEY `id_obra_social` (`id_obra_social`),
  ADD KEY `id_trabajo` (`id_trabajo`);

--
-- Indices de la tabla `paciente_estudio`
--
ALTER TABLE `paciente_estudio`
  ADD PRIMARY KEY (`dni_paciente`,`id_estudio`),
  ADD KEY `id_estudio` (`id_estudio`);

--
-- Indices de la tabla `paciente_exposicion`
--
ALTER TABLE `paciente_exposicion`
  ADD PRIMARY KEY (`dni_paciente`,`id_exposicion`),
  ADD KEY `id_exposicion` (`id_exposicion`);

--
-- Indices de la tabla `trabajo`
--
ALTER TABLE `trabajo`
  ADD PRIMARY KEY (`id_trabajo`),
  ADD UNIQUE KEY `nombre_t` (`nombre_t`);

--
-- Indices de la tabla `trabajo_estudio`
--
ALTER TABLE `trabajo_estudio`
  ADD PRIMARY KEY (`id_trabajo`,`id_estudio`),
  ADD KEY `id_estudio` (`id_estudio`);

--
-- Indices de la tabla `trabajo_exposicion`
--
ALTER TABLE `trabajo_exposicion`
  ADD PRIMARY KEY (`id_trabajo`,`id_exposicion`),
  ADD KEY `id_exposicion` (`id_exposicion`);

--
-- Indices de la tabla `turno`
--
ALTER TABLE `turno`
  ADD PRIMARY KEY (`id_turno`),
  ADD KEY `idx_turno_fecha` (`fecha`,`hora`),
  ADD KEY `idx_turno_paciente` (`dni_paciente`),
  ADD KEY `idx_turno_estado` (`estado`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`dni_u`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `estudio`
--
ALTER TABLE `estudio`
  MODIFY `id_estudio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `exposicion`
--
ALTER TABLE `exposicion`
  MODIFY `id_exposicion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `historial_medico`
--
ALTER TABLE `historial_medico`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `obra_social`
--
ALTER TABLE `obra_social`
  MODIFY `id_obra_social` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `trabajo`
--
ALTER TABLE `trabajo`
  MODIFY `id_trabajo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `turno`
--
ALTER TABLE `turno`
  MODIFY `id_turno` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `historial_medico`
--
ALTER TABLE `historial_medico`
  ADD CONSTRAINT `historial_medico_ibfk_1` FOREIGN KEY (`dni_paciente`) REFERENCES `paciente` (`dni_p`),
  ADD CONSTRAINT `historial_medico_ibfk_2` FOREIGN KEY (`id_estudio`) REFERENCES `estudio` (`id_estudio`);

--
-- Filtros para la tabla `paciente`
--
ALTER TABLE `paciente`
  ADD CONSTRAINT `paciente_ibfk_1` FOREIGN KEY (`id_obra_social`) REFERENCES `obra_social` (`id_obra_social`),
  ADD CONSTRAINT `paciente_ibfk_2` FOREIGN KEY (`id_trabajo`) REFERENCES `trabajo` (`id_trabajo`);

--
-- Filtros para la tabla `paciente_estudio`
--
ALTER TABLE `paciente_estudio`
  ADD CONSTRAINT `paciente_estudio_ibfk_1` FOREIGN KEY (`dni_paciente`) REFERENCES `paciente` (`dni_p`),
  ADD CONSTRAINT `paciente_estudio_ibfk_2` FOREIGN KEY (`id_estudio`) REFERENCES `estudio` (`id_estudio`);

--
-- Filtros para la tabla `paciente_exposicion`
--
ALTER TABLE `paciente_exposicion`
  ADD CONSTRAINT `paciente_exposicion_ibfk_1` FOREIGN KEY (`dni_paciente`) REFERENCES `paciente` (`dni_p`),
  ADD CONSTRAINT `paciente_exposicion_ibfk_2` FOREIGN KEY (`id_exposicion`) REFERENCES `exposicion` (`id_exposicion`);

--
-- Filtros para la tabla `trabajo_estudio`
--
ALTER TABLE `trabajo_estudio`
  ADD CONSTRAINT `trabajo_estudio_ibfk_1` FOREIGN KEY (`id_trabajo`) REFERENCES `trabajo` (`id_trabajo`),
  ADD CONSTRAINT `trabajo_estudio_ibfk_2` FOREIGN KEY (`id_estudio`) REFERENCES `estudio` (`id_estudio`);

--
-- Filtros para la tabla `trabajo_exposicion`
--
ALTER TABLE `trabajo_exposicion`
  ADD CONSTRAINT `trabajo_exposicion_ibfk_1` FOREIGN KEY (`id_trabajo`) REFERENCES `trabajo` (`id_trabajo`),
  ADD CONSTRAINT `trabajo_exposicion_ibfk_2` FOREIGN KEY (`id_exposicion`) REFERENCES `exposicion` (`id_exposicion`);

--
-- Filtros para la tabla `turno`
--
ALTER TABLE `turno`
  ADD CONSTRAINT `fk_turno_paciente` FOREIGN KEY (`dni_paciente`) REFERENCES `paciente` (`dni_p`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
