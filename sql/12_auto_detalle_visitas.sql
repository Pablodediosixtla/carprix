-- =========================================================
-- CARPRIX - VISITAS A DETALLE DE AUTOS
-- Fecha: 2026-09-04
-- =========================================================
-- Registra el número acumulado de accesos a la vista pública
-- de detalle por auto. Los autos sin registro se consideran
-- con 0 visitas.
-- =========================================================

CREATE TABLE IF NOT EXISTS auto_detalle_visita (
    auto_id BIGINT NOT NULL,
    total_visitas BIGINT UNSIGNED NOT NULL DEFAULT 0,
    ultima_visita_en DATETIME DEFAULT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (auto_id),
    KEY idx_auto_detalle_visita_total (total_visitas),
    KEY idx_auto_detalle_visita_ultima (ultima_visita_en),

    CONSTRAINT fk_auto_detalle_visita_auto
        FOREIGN KEY (auto_id)
        REFERENCES autos (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_spanish_ci;
