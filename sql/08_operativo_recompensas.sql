-- =========================================================
-- CARPRIX - MÓDULO OPERATIVO DE RECOMPENSAS
-- Ejecutar después de los scripts 02..07.
-- =========================================================

CREATE TABLE IF NOT EXISTS operativo_recompensa_categoria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(100) COLLATE utf8mb4_spanish_ci NOT NULL,
    tipo ENUM('SUMA','RESTA') COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'SUMA',
    descripcion VARCHAR(500) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    orden INT NOT NULL DEFAULT 0,
    creado_por BIGINT UNSIGNED DEFAULT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_operativo_recompensa_categoria (nombre, tipo),
    KEY idx_operativo_recompensa_categoria_activo (activo, orden, nombre),
    CONSTRAINT fk_operativo_recompensa_categoria_creado_por
        FOREIGN KEY (creado_por)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS operativo_recompensa_catalogo (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    categoria_id BIGINT UNSIGNED NOT NULL,
    codigo VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    nombre VARCHAR(140) COLLATE utf8mb4_spanish_ci NOT NULL,
    descripcion VARCHAR(500) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
    puntos INT UNSIGNED NOT NULL DEFAULT 0,
    origen ENUM('MANUAL','AUTO_APARTADO','AUTO_VENDIDO') COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'MANUAL',
    permite_asignacion_manual TINYINT(1) NOT NULL DEFAULT 1,
    es_sistema TINYINT(1) NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_por BIGINT UNSIGNED DEFAULT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_operativo_recompensa_catalogo_codigo (codigo),
    KEY idx_operativo_recompensa_catalogo_categoria (categoria_id, activo),
    KEY idx_operativo_recompensa_catalogo_origen (origen, activo),
    CONSTRAINT fk_operativo_recompensa_catalogo_categoria
        FOREIGN KEY (categoria_id)
        REFERENCES operativo_recompensa_categoria (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_operativo_recompensa_catalogo_creado_por
        FOREIGN KEY (creado_por)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS operativo_recompensa_premio (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(140) COLLATE utf8mb4_spanish_ci NOT NULL,
    descripcion VARCHAR(700) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
    puntos_requeridos INT UNSIGNED NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    orden INT NOT NULL DEFAULT 0,
    creado_por BIGINT UNSIGNED DEFAULT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_operativo_recompensa_premio_activo (activo, puntos_requeridos, orden),
    CONSTRAINT fk_operativo_recompensa_premio_creado_por
        FOREIGN KEY (creado_por)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS operativo_recompensa_movimiento (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    catalogo_id BIGINT UNSIGNED NOT NULL,
    anio SMALLINT UNSIGNED NOT NULL,
    puntos_aplicados INT NOT NULL,
    origen ENUM('MANUAL','AUTO_APARTADO','AUTO_VENDIDO') COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'MANUAL',
    referencia_tipo VARCHAR(50) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
    referencia_id BIGINT UNSIGNED DEFAULT NULL,
    clave_evento VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    asignado_por BIGINT UNSIGNED NOT NULL,
    comentario VARCHAR(700) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_operativo_recompensa_movimiento_evento (clave_evento),
    KEY idx_operativo_recompensa_movimiento_usuario_anio (usuario_id, anio, creado_en),
    KEY idx_operativo_recompensa_movimiento_catalogo (catalogo_id, creado_en),
    KEY idx_operativo_recompensa_movimiento_asignador (asignado_por, creado_en),
    CONSTRAINT fk_operativo_recompensa_movimiento_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_operativo_recompensa_movimiento_catalogo
        FOREIGN KEY (catalogo_id)
        REFERENCES operativo_recompensa_catalogo (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_operativo_recompensa_movimiento_asignado_por
        FOREIGN KEY (asignado_por)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_spanish_ci;

-- Categorías base. Pueden editarse desde Gestión de recompensas.
INSERT INTO operativo_recompensa_categoria (nombre, tipo, descripcion, activo, orden)
VALUES
    ('Ventas', 'SUMA', 'Recompensas automáticas asociadas al avance comercial de un auto.', 1, 10),
    ('Buen desempeño', 'SUMA', 'Reconocimientos manuales por desempeño, servicio o apoyo extraordinario.', 1, 20),
    ('Incidencias', 'RESTA', 'Ajustes negativos por faltas, mal servicio u otras incidencias.', 1, 30)
ON DUPLICATE KEY UPDATE
    activo = VALUES(activo),
    descripcion = VALUES(descripcion);

-- Reglas automáticas del flujo comercial. Los puntos son valores iniciales y
-- NO se sobrescriben al volver a ejecutar este script, para respetar la
-- configuración posterior realizada desde el módulo operativo.
INSERT INTO operativo_recompensa_catalogo
    (categoria_id, codigo, nombre, descripcion, puntos, origen, permite_asignacion_manual, es_sistema, activo)
SELECT id, 'AUTO_APARTADO', 'Auto apartado',
       'Se aplica automáticamente al responsable del requerimiento cuando Apartado es autorizado.',
       100, 'AUTO_APARTADO', 0, 1, 1
FROM operativo_recompensa_categoria
WHERE nombre = 'Ventas' AND tipo = 'SUMA'
LIMIT 1
ON DUPLICATE KEY UPDATE
    categoria_id = VALUES(categoria_id),
    origen = 'AUTO_APARTADO',
    permite_asignacion_manual = 0,
    es_sistema = 1;

INSERT INTO operativo_recompensa_catalogo
    (categoria_id, codigo, nombre, descripcion, puntos, origen, permite_asignacion_manual, es_sistema, activo)
SELECT id, 'AUTO_VENDIDO', 'Auto vendido',
       'Se aplica automáticamente al responsable del requerimiento cuando Vendido es autorizado.',
       300, 'AUTO_VENDIDO', 0, 1, 1
FROM operativo_recompensa_categoria
WHERE nombre = 'Ventas' AND tipo = 'SUMA'
LIMIT 1
ON DUPLICATE KEY UPDATE
    categoria_id = VALUES(categoria_id),
    origen = 'AUTO_VENDIDO',
    permite_asignacion_manual = 0,
    es_sistema = 1;

-- Ejemplos manuales editables. Sirven para probar el módulo desde el primer día.
INSERT INTO operativo_recompensa_catalogo
    (categoria_id, codigo, nombre, descripcion, puntos, origen, permite_asignacion_manual, es_sistema, activo)
SELECT id, 'BUEN_SERVICIO', 'Buen servicio al cliente',
       'Reconocimiento manual por una atención sobresaliente al cliente.',
       50, 'MANUAL', 1, 0, 1
FROM operativo_recompensa_categoria
WHERE nombre = 'Buen desempeño' AND tipo = 'SUMA'
LIMIT 1
ON DUPLICATE KEY UPDATE codigo = codigo;

INSERT INTO operativo_recompensa_catalogo
    (categoria_id, codigo, nombre, descripcion, puntos, origen, permite_asignacion_manual, es_sistema, activo)
SELECT id, 'APOYO_EXTRA', 'Apoyo extraordinario',
       'Reconocimiento manual por apoyo relevante al equipo o a la operación.',
       75, 'MANUAL', 1, 0, 1
FROM operativo_recompensa_categoria
WHERE nombre = 'Buen desempeño' AND tipo = 'SUMA'
LIMIT 1
ON DUPLICATE KEY UPDATE codigo = codigo;

INSERT INTO operativo_recompensa_catalogo
    (categoria_id, codigo, nombre, descripcion, puntos, origen, permite_asignacion_manual, es_sistema, activo)
SELECT id, 'FALTA_TRABAJO', 'Falta al trabajo',
       'Descuento manual de puntos por falta no justificada.',
       100, 'MANUAL', 1, 0, 1
FROM operativo_recompensa_categoria
WHERE nombre = 'Incidencias' AND tipo = 'RESTA'
LIMIT 1
ON DUPLICATE KEY UPDATE codigo = codigo;

INSERT INTO operativo_recompensa_catalogo
    (categoria_id, codigo, nombre, descripcion, puntos, origen, permite_asignacion_manual, es_sistema, activo)
SELECT id, 'MAL_SERVICIO', 'Mal servicio al cliente',
       'Descuento manual de puntos por una incidencia validada de atención al cliente.',
       75, 'MANUAL', 1, 0, 1
FROM operativo_recompensa_categoria
WHERE nombre = 'Incidencias' AND tipo = 'RESTA'
LIMIT 1
ON DUPLICATE KEY UPDATE codigo = codigo;
