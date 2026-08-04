-- =========================================================
-- CARPRIX - MÓDULO OPERATIVO COMERCIAL
-- Ejecutar después de crear operativo_usuario, operativo_rol
-- y operativo_usuario_rol.
-- =========================================================

START TRANSACTION;

INSERT INTO operativo_rol
    (codigo, nombre, descripcion, es_sistema, activo)
VALUES
    ('VENTAS', 'Ventas', 'Crea y administra requerimientos de compra de clientes.', 1, 1),
    ('AUTORIZADOR', 'Autorizador', 'Autoriza cambios de estatus conforme a la jerarquía operativa.', 1, 1)
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    descripcion = VALUES(descripcion),
    activo = 1;

CREATE TABLE IF NOT EXISTS operativo_usuario_jerarquia (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    supervisor_id BIGINT UNSIGNED NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    asignado_por BIGINT UNSIGNED DEFAULT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_operativo_jerarquia_usuario (usuario_id),
    KEY idx_operativo_jerarquia_supervisor (supervisor_id, activo),
    CONSTRAINT fk_operativo_jerarquia_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_operativo_jerarquia_supervisor
        FOREIGN KEY (supervisor_id)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_operativo_jerarquia_asignado_por
        FOREIGN KEY (asignado_por)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS operativo_requerimiento_compra (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    folio VARCHAR(30) COLLATE utf8mb4_spanish_ci NOT NULL,
    auto_id BIGINT NOT NULL,
    cliente_nombre VARCHAR(150) COLLATE utf8mb4_spanish_ci NOT NULL,
    cliente_telefono VARCHAR(20) COLLATE utf8mb4_spanish_ci NOT NULL,
    cliente_email VARCHAR(150) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
    cliente_identificacion VARCHAR(100) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
    monto_propuesto DECIMAL(12,2) DEFAULT NULL,
    forma_pago ENUM('Contado','Financiamiento','Otro')
        COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'Contado',
    comentarios TEXT COLLATE utf8mb4_spanish_ci,
    estatus ENUM('Solicitado','Apartado','Vendido')
        COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'Solicitado',
    creado_por BIGINT UNSIGNED NOT NULL,
    asignado_a BIGINT UNSIGNED NOT NULL,
    fecha_solicitud TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    fecha_apartado DATETIME DEFAULT NULL,
    fecha_venta DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_operativo_requerimiento_folio (folio),
    KEY idx_operativo_requerimiento_auto (auto_id, estatus),
    KEY idx_operativo_requerimiento_estatus (estatus, fecha_solicitud),
    KEY idx_operativo_requerimiento_creado (creado_por, fecha_solicitud),
    KEY idx_operativo_requerimiento_asignado (asignado_a, estatus),
    CONSTRAINT fk_operativo_requerimiento_auto
        FOREIGN KEY (auto_id)
        REFERENCES autos (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_operativo_requerimiento_creado_por
        FOREIGN KEY (creado_por)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_operativo_requerimiento_asignado_a
        FOREIGN KEY (asignado_a)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS operativo_requerimiento_cambio (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    requerimiento_id BIGINT UNSIGNED NOT NULL,
    estatus_origen ENUM('Solicitado','Apartado','Vendido')
        COLLATE utf8mb4_spanish_ci NOT NULL,
    estatus_solicitado ENUM('Solicitado','Apartado','Vendido')
        COLLATE utf8mb4_spanish_ci NOT NULL,
    motivo VARCHAR(500) COLLATE utf8mb4_spanish_ci NOT NULL,
    solicitado_por BIGINT UNSIGNED NOT NULL,
    aprobador_id BIGINT UNSIGNED DEFAULT NULL,
    decision ENUM('Pendiente','Aprobado','Rechazado','Cancelado')
        COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'Pendiente',
    comentario_decision VARCHAR(500) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
    fecha_solicitud TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_decision DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_operativo_cambio_pendiente (decision, aprobador_id, fecha_solicitud),
    KEY idx_operativo_cambio_requerimiento (requerimiento_id, decision),
    KEY idx_operativo_cambio_solicitante (solicitado_por, fecha_solicitud),
    CONSTRAINT fk_operativo_cambio_requerimiento
        FOREIGN KEY (requerimiento_id)
        REFERENCES operativo_requerimiento_compra (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_operativo_cambio_solicitado_por
        FOREIGN KEY (solicitado_por)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_operativo_cambio_aprobador
        FOREIGN KEY (aprobador_id)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT chk_operativo_cambio_estatus
        CHECK (estatus_origen <> estatus_solicitado)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS operativo_requerimiento_historial (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    requerimiento_id BIGINT UNSIGNED NOT NULL,
    tipo_evento ENUM(
        'CREACION',
        'ACTUALIZACION',
        'SOLICITUD_CAMBIO',
        'APROBACION',
        'RECHAZO',
        'ASIGNACION'
    ) COLLATE utf8mb4_spanish_ci NOT NULL,
    estatus_anterior ENUM('Solicitado','Apartado','Vendido')
        COLLATE utf8mb4_spanish_ci DEFAULT NULL,
    estatus_nuevo ENUM('Solicitado','Apartado','Vendido')
        COLLATE utf8mb4_spanish_ci DEFAULT NULL,
    detalle VARCHAR(500) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_operativo_historial_requerimiento (requerimiento_id, creado_en),
    KEY idx_operativo_historial_usuario (usuario_id, creado_en),
    CONSTRAINT fk_operativo_historial_requerimiento
        FOREIGN KEY (requerimiento_id)
        REFERENCES operativo_requerimiento_compra (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_operativo_historial_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_spanish_ci;

COMMIT;

-- La regla usuario != supervisor se implementa con triggers porque
-- MySQL no permite usar esas columnas en un CHECK cuando participan
-- en llaves foráneas con acciones referenciales.
DROP TRIGGER IF EXISTS trg_operativo_jerarquia_bi;
DROP TRIGGER IF EXISTS trg_operativo_jerarquia_bu;

DELIMITER $$

CREATE TRIGGER trg_operativo_jerarquia_bi
BEFORE INSERT ON operativo_usuario_jerarquia
FOR EACH ROW
BEGIN
    IF NEW.usuario_id = NEW.supervisor_id THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Un usuario no puede ser su propio supervisor.';
    END IF;
END$$

CREATE TRIGGER trg_operativo_jerarquia_bu
BEFORE UPDATE ON operativo_usuario_jerarquia
FOR EACH ROW
BEGIN
    IF NEW.usuario_id = NEW.supervisor_id THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Un usuario no puede ser su propio supervisor.';
    END IF;
END$$

DELIMITER ;

