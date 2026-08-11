-- =========================================================
-- CARPRIX - MÓDULO OPERATIVO DE TAREAS
-- Ejecutar después de sql/02_operativo_comercial.sql
-- =========================================================

CREATE TABLE IF NOT EXISTS operativo_tarea (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    folio VARCHAR(30) COLLATE utf8mb4_spanish_ci NOT NULL,
    titulo VARCHAR(150) COLLATE utf8mb4_spanish_ci NOT NULL,
    descripcion TEXT COLLATE utf8mb4_spanish_ci,
    prioridad ENUM('Baja','Media','Alta','Urgente')
        COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'Media',
    estatus ENUM('Pendiente','En progreso','En revision','Completada','Cancelada')
        COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'Pendiente',
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME DEFAULT NULL,
    fecha_completada DATETIME DEFAULT NULL,
    creado_por BIGINT UNSIGNED NOT NULL,
    asignado_a BIGINT UNSIGNED NOT NULL,
    aprobador_id BIGINT UNSIGNED DEFAULT NULL,
    requiere_aprobacion TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_operativo_tarea_folio (folio),
    KEY idx_operativo_tarea_asignado (asignado_a, estatus, fecha_fin),
    KEY idx_operativo_tarea_creador (creado_por, estatus, creado_en),
    KEY idx_operativo_tarea_aprobador (aprobador_id, estatus, fecha_fin),
    CONSTRAINT fk_operativo_tarea_creado_por
        FOREIGN KEY (creado_por)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_operativo_tarea_asignado_a
        FOREIGN KEY (asignado_a)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_operativo_tarea_aprobador
        FOREIGN KEY (aprobador_id)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS operativo_tarea_comentario (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tarea_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    comentario TEXT COLLATE utf8mb4_spanish_ci NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_operativo_tarea_comentario_tarea (tarea_id, creado_en),
    KEY idx_operativo_tarea_comentario_usuario (usuario_id, creado_en),
    CONSTRAINT fk_operativo_tarea_comentario_tarea
        FOREIGN KEY (tarea_id)
        REFERENCES operativo_tarea (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_operativo_tarea_comentario_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS operativo_tarea_aprobacion (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tarea_id BIGINT UNSIGNED NOT NULL,
    solicitado_por BIGINT UNSIGNED NOT NULL,
    aprobador_id BIGINT UNSIGNED NOT NULL,
    decision ENUM('Pendiente','Aprobado','Rechazado','Cancelado')
        COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'Pendiente',
    comentario VARCHAR(500) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
    fecha_solicitud TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_decision DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_operativo_tarea_aprobacion_tarea (tarea_id, decision, fecha_solicitud),
    KEY idx_operativo_tarea_aprobacion_aprobador (aprobador_id, decision, fecha_solicitud),
    CONSTRAINT fk_operativo_tarea_aprobacion_tarea
        FOREIGN KEY (tarea_id)
        REFERENCES operativo_tarea (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_operativo_tarea_aprobacion_solicitante
        FOREIGN KEY (solicitado_por)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_operativo_tarea_aprobacion_aprobador
        FOREIGN KEY (aprobador_id)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS operativo_tarea_historial (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tarea_id BIGINT UNSIGNED NOT NULL,
    tipo_evento ENUM(
        'CREACION',
        'ACTUALIZACION',
        'ESTATUS',
        'COMENTARIO',
        'SOLICITUD_APROBACION',
        'APROBACION',
        'RECHAZO',
        'CANCELACION'
    ) COLLATE utf8mb4_spanish_ci NOT NULL,
    estatus_anterior ENUM('Pendiente','En progreso','En revision','Completada','Cancelada')
        COLLATE utf8mb4_spanish_ci DEFAULT NULL,
    estatus_nuevo ENUM('Pendiente','En progreso','En revision','Completada','Cancelada')
        COLLATE utf8mb4_spanish_ci DEFAULT NULL,
    detalle VARCHAR(500) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_operativo_tarea_historial_tarea (tarea_id, creado_en),
    KEY idx_operativo_tarea_historial_usuario (usuario_id, creado_en),
    CONSTRAINT fk_operativo_tarea_historial_tarea
        FOREIGN KEY (tarea_id)
        REFERENCES operativo_tarea (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_operativo_tarea_historial_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES operativo_usuario (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_spanish_ci;
