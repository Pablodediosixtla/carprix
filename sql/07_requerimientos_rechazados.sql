-- CARPRIX | Requerimientos comerciales rechazados
-- Ejecutar una sola vez antes de desplegar el código de esta versión.
-- Permite que el estatus principal del requerimiento refleje un rechazo.

START TRANSACTION;

ALTER TABLE operativo_requerimiento_compra
    MODIFY COLUMN estatus ENUM('Solicitado','Apartado','Vendido','Rechazado')
        COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'Solicitado';

ALTER TABLE operativo_requerimiento_historial
    MODIFY COLUMN estatus_anterior ENUM('Solicitado','Apartado','Vendido','Rechazado')
        COLLATE utf8mb4_spanish_ci DEFAULT NULL,
    MODIFY COLUMN estatus_nuevo ENUM('Solicitado','Apartado','Vendido','Rechazado')
        COLLATE utf8mb4_spanish_ci DEFAULT NULL;

-- Reconciliación de rechazos previos del primer cambio Solicitado -> Apartado.
-- Solo toca requerimientos que aún continúan como Solicitado y cuyo último
-- intento de apartado fue rechazado.
UPDATE operativo_requerimiento_compra r
INNER JOIN (
    SELECT c.requerimiento_id, MAX(c.id) AS ultimo_cambio_id
    FROM operativo_requerimiento_cambio c
    WHERE c.estatus_origen = 'Solicitado'
      AND c.estatus_solicitado = 'Apartado'
    GROUP BY c.requerimiento_id
) ult ON ult.requerimiento_id = r.id
INNER JOIN operativo_requerimiento_cambio c ON c.id = ult.ultimo_cambio_id
SET r.estatus = 'Rechazado',
    r.fecha_actualizacion = CURRENT_TIMESTAMP
WHERE r.estatus = 'Solicitado'
  AND c.decision = 'Rechazado';

COMMIT;
