-- =========================================================
-- CARPRIX - ROL RECURSOS HUMANOS (RH)
-- Migración segura para el esquema operativo existente.
-- No crea tablas nuevas.
-- =========================================================

START TRANSACTION;

INSERT INTO operativo_rol
    (codigo, nombre, descripcion, es_sistema, activo)
VALUES
    (
        'RH',
        'Recursos Humanos',
        'Gestiona personas y jerarquía, consulta analítica global y administra reconocimientos sin autorizar requerimientos comerciales.',
        1,
        1
    )
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    descripcion = VALUES(descripcion),
    es_sistema = 1,
    activo = 1;

COMMIT;

-- Validación sugerida:
-- SELECT id, codigo, nombre, descripcion, es_sistema, activo
-- FROM operativo_rol
-- WHERE codigo = 'RH';
