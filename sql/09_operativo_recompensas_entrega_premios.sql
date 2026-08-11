-- =========================================================
-- CARPRIX - ENTREGA ANUAL DE PREMIOS DE RECOMPENSAS
-- Ejecutar después de 08_operativo_recompensas.sql
-- =========================================================

CREATE TABLE IF NOT EXISTS operativo_recompensa_premio_otorgado (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    premio_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    anio SMALLINT UNSIGNED NOT NULL,
    puntos_al_otorgar INT NOT NULL,
    otorgado_por BIGINT UNSIGNED NOT NULL,
    comentario VARCHAR(700) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
    otorgado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_operativo_premio_usuario_anio (premio_id, usuario_id, anio),
    KEY idx_operativo_premio_otorgado_anio (anio, premio_id, otorgado_en),
    KEY idx_operativo_premio_otorgado_usuario (usuario_id, anio),
    KEY idx_operativo_premio_otorgado_actor (otorgado_por, otorgado_en),
    CONSTRAINT fk_operativo_premio_otorgado_premio
        FOREIGN KEY (premio_id)
        REFERENCES operativo_recompensa_premio (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_operativo_premio_otorgado_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_operativo_premio_otorgado_actor
        FOREIGN KEY (otorgado_por)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_spanish_ci;
