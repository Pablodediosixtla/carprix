-- =========================================================
-- CARPRIX - AUTOS DESTACADOS DEL INDEX
-- Ejecutar después de las tablas operativas existentes.
-- =========================================================

CREATE TABLE IF NOT EXISTS operativo_auto_destacado (
    posicion TINYINT UNSIGNED NOT NULL,
    auto_id BIGINT NOT NULL,
    actualizado_por BIGINT UNSIGNED DEFAULT NULL,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (posicion),
    UNIQUE KEY uk_operativo_auto_destacado_auto (auto_id),
    KEY idx_operativo_auto_destacado_usuario (actualizado_por),
    CONSTRAINT fk_operativo_auto_destacado_auto
        FOREIGN KEY (auto_id)
        REFERENCES autos (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_operativo_auto_destacado_usuario
        FOREIGN KEY (actualizado_por)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_spanish_ci;

-- =========================================================
-- RECONCILIACIÓN ÚNICA DE ESTATUS COMERCIAL
-- Corrige requerimientos ya aprobados antes de esta versión,
-- cuando el requerimiento cambió pero autos.estatus quedó Disponible.
-- No modifica autos Ocultos.
-- =========================================================

UPDATE autos a
INNER JOIN (
    SELECT DISTINCT auto_id
    FROM operativo_requerimiento_compra
    WHERE estatus = 'Vendido'
) r ON r.auto_id = a.id
SET a.estatus = 'Vendido'
WHERE a.estatus IN ('Disponible', 'Apartado');

UPDATE autos a
INNER JOIN (
    SELECT DISTINCT auto_id
    FROM operativo_requerimiento_compra
    WHERE estatus = 'Apartado'
) r ON r.auto_id = a.id
SET a.estatus = 'Apartado'
WHERE a.estatus = 'Disponible';
