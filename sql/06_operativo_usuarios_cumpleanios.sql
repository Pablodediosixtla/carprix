-- CARPRIX - Datos personales operativos / cumpleaños
-- Agrega fecha de nacimiento de forma idempotente.

SET @carprix_schema := DATABASE();

SELECT COUNT(*) INTO @carprix_has_birthdate
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @carprix_schema
  AND TABLE_NAME = 'operativo_usuario'
  AND COLUMN_NAME = 'fecha_nacimiento';

SET @carprix_sql := IF(
    @carprix_has_birthdate = 0,
    'ALTER TABLE operativo_usuario ADD COLUMN fecha_nacimiento DATE NULL AFTER telefono',
    'SELECT ''fecha_nacimiento ya existe'' AS info'
);
PREPARE carprix_stmt FROM @carprix_sql;
EXECUTE carprix_stmt;
DEALLOCATE PREPARE carprix_stmt;

SELECT COUNT(*) INTO @carprix_has_birthdate_index
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @carprix_schema
  AND TABLE_NAME = 'operativo_usuario'
  AND INDEX_NAME = 'idx_operativo_usuario_fecha_nacimiento';

SET @carprix_sql := IF(
    @carprix_has_birthdate_index = 0,
    'CREATE INDEX idx_operativo_usuario_fecha_nacimiento ON operativo_usuario (fecha_nacimiento)',
    'SELECT ''idx_operativo_usuario_fecha_nacimiento ya existe'' AS info'
);
PREPARE carprix_stmt FROM @carprix_sql;
EXECUTE carprix_stmt;
DEALLOCATE PREPARE carprix_stmt;
