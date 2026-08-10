-- =========================================================
-- CARPRIX - REQUERIMIENTOS DE PUBLICACIÓN DE CATÁLOGO
-- Ejecutar después de sql/02_operativo_comercial.sql
-- =========================================================

CREATE TABLE IF NOT EXISTS operativo_catalogo_requerimiento (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    auto_id BIGINT NOT NULL,
    tipo ENUM('PUBLICACION') COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'PUBLICACION',
    estatus_origen ENUM('Oculto','Disponible','Apartado','Vendido')
        COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'Oculto',
    estatus_solicitado ENUM('Oculto','Disponible','Apartado','Vendido')
        COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'Disponible',
    motivo VARCHAR(500) COLLATE utf8mb4_spanish_ci NOT NULL,
    solicitado_por BIGINT UNSIGNED NOT NULL,
    aprobador_id BIGINT UNSIGNED DEFAULT NULL,
    decision ENUM('Pendiente','Aprobado','Rechazado','Cancelado')
        COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'Pendiente',
    comentario_decision VARCHAR(500) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
    fecha_solicitud TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_decision DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_catalogo_req_auto (auto_id, decision, fecha_solicitud),
    KEY idx_catalogo_req_aprobador (aprobador_id, decision, fecha_solicitud),
    KEY idx_catalogo_req_solicitante (solicitado_por, decision, fecha_solicitud),
    CONSTRAINT fk_catalogo_req_auto
        FOREIGN KEY (auto_id)
        REFERENCES autos (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_catalogo_req_solicitante
        FOREIGN KEY (solicitado_por)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_catalogo_req_aprobador
        FOREIGN KEY (aprobador_id)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_spanish_ci;
