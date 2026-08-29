-- =========================================================
-- CARPRIX - METAS COMERCIALES POR PERSONA
-- Reserva: meta mensual.
-- Venta: meta anual (mes = 0).
--
-- La ausencia de registro equivale a meta 0.
-- Ejecutar antes de desplegar el código de la versión que usa
-- el módulo Operativo > Metas.
-- =========================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS operativo_meta_usuario (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    tipo ENUM('RESERVA','VENTA')
        CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
    anio SMALLINT UNSIGNED NOT NULL,
    mes TINYINT UNSIGNED NOT NULL DEFAULT 0,
    meta INT UNSIGNED NOT NULL DEFAULT 0,
    equipo_lider_id BIGINT UNSIGNED DEFAULT NULL,
    origen ENUM('PRORRATEO','AJUSTE_MANUAL')
        CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'PRORRATEO',
    asignada_por BIGINT UNSIGNED NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_operativo_meta_usuario_periodo (usuario_id, tipo, anio, mes),
    KEY idx_operativo_meta_periodo (tipo, anio, mes),
    KEY idx_operativo_meta_equipo (equipo_lider_id, tipo, anio, mes),
    KEY idx_operativo_meta_asignada_por (asignada_por),
    CONSTRAINT fk_operativo_meta_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_operativo_meta_equipo_lider
        FOREIGN KEY (equipo_lider_id)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_operativo_meta_asignada_por
        FOREIGN KEY (asignada_por)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS operativo_meta_historial (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    tipo ENUM('RESERVA','VENTA')
        CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
    anio SMALLINT UNSIGNED NOT NULL,
    mes TINYINT UNSIGNED NOT NULL DEFAULT 0,
    meta_anterior INT UNSIGNED NOT NULL DEFAULT 0,
    meta_nueva INT UNSIGNED NOT NULL DEFAULT 0,
    equipo_lider_id BIGINT UNSIGNED DEFAULT NULL,
    accion ENUM('PRORRATEO_EQUIPO','AJUSTE_MANUAL')
        CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
    ejecutado_por BIGINT UNSIGNED NOT NULL,
    comentario VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_operativo_meta_historial_usuario (usuario_id, anio, mes),
    KEY idx_operativo_meta_historial_equipo (equipo_lider_id, anio, mes),
    KEY idx_operativo_meta_historial_actor (ejecutado_por, creado_en),
    CONSTRAINT fk_operativo_meta_historial_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_operativo_meta_historial_equipo
        FOREIGN KEY (equipo_lider_id)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_operativo_meta_historial_actor
        FOREIGN KEY (ejecutado_por)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_spanish_ci;

COMMIT;

-- Validación:
-- SHOW TABLES LIKE 'operativo_meta%';
-- SELECT * FROM operativo_meta_usuario ORDER BY id DESC;
