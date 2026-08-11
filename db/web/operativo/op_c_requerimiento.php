<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(false);
$con = connectDatabase();
$user = requireAuthenticated($con);
$requirementId = positiveInt($input['id'] ?? null, 'id');

$sql = "SELECT
            r.*,
            a.marca, a.modelo, a.anio, a.precio, a.img_principal,
            CONCAT(cu.nombre, ' ', cu.apellido_paterno) AS creado_por_nombre,
            CONCAT(au.nombre, ' ', au.apellido_paterno) AS asignado_a_nombre
        FROM operativo_requerimiento_compra r
        INNER JOIN autos a ON a.id = r.auto_id
        INNER JOIN operativo_usuario cu ON cu.id = r.creado_por
        INNER JOIN operativo_usuario au ON au.id = r.asignado_a
        WHERE r.id = ?
        LIMIT 1";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $requirementId);
$stmt->execute();
$requirement = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$requirement) {
    $con->close();
    errorResponse('Requerimiento no encontrado.', 404, 'REQUIREMENT_NOT_FOUND');
}
if (!canViewRequirementRecord(
    $con,
    $user,
    (int) $requirement['creado_por'],
    (int) $requirement['asignado_a']
)) {
    $con->close();
    errorResponse('No tienes acceso a este requerimiento.', 403, 'FORBIDDEN');
}

$historyStmt = $con->prepare(
    "SELECT h.id, h.tipo_evento, h.estatus_anterior, h.estatus_nuevo,
            h.detalle, h.creado_en,
            CONCAT(u.nombre, ' ', u.apellido_paterno) AS usuario
     FROM operativo_requerimiento_historial h
     INNER JOIN operativo_usuario u ON u.id = h.usuario_id
     WHERE h.requerimiento_id = ?
     ORDER BY h.creado_en DESC, h.id DESC"
);
$historyStmt->bind_param('i', $requirementId);
$historyStmt->execute();
$history = $historyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$historyStmt->close();

$changesStmt = $con->prepare(
    "SELECT c.id, c.estatus_origen, c.estatus_solicitado, c.motivo,
            c.decision, c.comentario_decision, c.fecha_solicitud, c.fecha_decision,
            CONCAT(s.nombre, ' ', s.apellido_paterno) AS solicitante,
            CONCAT(a.nombre, ' ', a.apellido_paterno) AS aprobador
     FROM operativo_requerimiento_cambio c
     INNER JOIN operativo_usuario s ON s.id = c.solicitado_por
     LEFT JOIN operativo_usuario a ON a.id = c.aprobador_id
     WHERE c.requerimiento_id = ?
     ORDER BY c.fecha_solicitud DESC, c.id DESC"
);
$changesStmt->bind_param('i', $requirementId);
$changesStmt->execute();
$changes = $changesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$changesStmt->close();
$con->close();

$requirement['id'] = (int) $requirement['id'];
$requirement['auto_id'] = (int) $requirement['auto_id'];
$requirement['anio'] = (int) $requirement['anio'];
$requirement['precio'] = (float) $requirement['precio'];
$requirement['monto_propuesto'] = $requirement['monto_propuesto'] !== null
    ? (float) $requirement['monto_propuesto']
    : null;

okResponse([
    'requerimiento' => $requirement,
    'historial' => $history,
    'solicitudes_cambio' => $changes,
]);
