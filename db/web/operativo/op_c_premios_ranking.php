<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(false);
$con = connectDatabase();
$user = requireAuthenticated($con);
requireAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'RH']);

$premioId = positiveInt($input['premio_id'] ?? null, 'premio_id');
$currentYear = rewardsCurrentYear();
$year = (int) ($input['anio'] ?? $currentYear);

if ($year < 2020 || $year > $currentYear) {
    $con->close();
    errorResponse('El año solicitado no es válido.', 422, 'VALIDATION_ERROR', ['field' => 'anio']);
}

$prizeStmt = $con->prepare(
    "SELECT id, nombre, descripcion, puntos_requeridos, activo
     FROM operativo_recompensa_premio
     WHERE id = ?
     LIMIT 1"
);
if (!$prizeStmt) {
    databaseError($con);
}
$prizeStmt->bind_param('i', $premioId);
$prizeStmt->execute();
$prize = $prizeStmt->get_result()->fetch_assoc();
$prizeStmt->close();

if (!$prize) {
    $con->close();
    errorResponse('Premio no encontrado.', 404, 'PRIZE_NOT_FOUND');
}

$sql = "SELECT
            u.id,
            u.username,
            u.email,
            u.nombre,
            u.apellido_paterno,
            u.apellido_materno,
            COALESCE(points.total_puntos, 0) AS puntos,
            COALESCE(roles.roles, '') AS roles,
            award.id AS otorgamiento_id,
            award.puntos_al_otorgar,
            award.comentario AS comentario_otorgamiento,
            award.otorgado_en,
            CONCAT_WS(' ', actor.nombre, actor.apellido_paterno) AS otorgado_por_nombre
        FROM operativo_usuario u
        LEFT JOIN (
            SELECT usuario_id, SUM(puntos_aplicados) AS total_puntos
            FROM operativo_recompensa_movimiento
            WHERE anio = ?
            GROUP BY usuario_id
        ) points ON points.usuario_id = u.id
        LEFT JOIN (
            SELECT ur.usuario_id,
                   GROUP_CONCAT(DISTINCT r.codigo ORDER BY r.codigo SEPARATOR ',') AS roles
            FROM operativo_usuario_rol ur
            INNER JOIN operativo_rol r
                ON r.id = ur.rol_id
               AND r.activo = 1
            WHERE ur.activo = 1
            GROUP BY ur.usuario_id
        ) roles ON roles.usuario_id = u.id
        LEFT JOIN operativo_recompensa_premio_otorgado award
            ON award.usuario_id = u.id
           AND award.premio_id = ?
           AND award.anio = ?
        LEFT JOIN operativo_usuario actor
            ON actor.id = award.otorgado_por
        WHERE u.estatus = 'Activo'
        ORDER BY COALESCE(points.total_puntos, 0) DESC,
                 u.apellido_paterno,
                 u.nombre,
                 u.id";

$stmt = $con->prepare($sql);
if (!$stmt) {
    databaseError($con);
}
$stmt->bind_param('iii', $year, $premioId, $year);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$required = (int) $prize['puntos_requeridos'];
$eligibleCount = 0;
$awardedCount = 0;
$pendingCount = 0;

foreach ($rows as &$row) {
    $row['id'] = (int) $row['id'];
    $row['puntos'] = (int) $row['puntos'];
    $row['otorgamiento_id'] = $row['otorgamiento_id'] !== null ? (int) $row['otorgamiento_id'] : null;
    $row['puntos_al_otorgar'] = $row['puntos_al_otorgar'] !== null ? (int) $row['puntos_al_otorgar'] : null;
    $row['elegible'] = $row['puntos'] >= $required;
    $row['otorgado'] = $row['otorgamiento_id'] !== null;
    $row['puntos_faltantes'] = max(0, $required - $row['puntos']);
    $row['roles'] = $row['roles'] !== '' ? explode(',', (string) $row['roles']) : [];

    if ($row['elegible']) {
        $eligibleCount++;
    }
    if ($row['otorgado']) {
        $awardedCount++;
    }
    if ($row['elegible'] && !$row['otorgado']) {
        $pendingCount++;
    }
}
unset($row);

$prize['id'] = (int) $prize['id'];
$prize['puntos_requeridos'] = $required;
$prize['activo'] = (bool) $prize['activo'];

$con->close();
okResponse([
    'anio' => $year,
    'premio' => $prize,
    'resumen' => [
        'elegibles' => $eligibleCount,
        'otorgados' => $awardedCount,
        'pendientes_entrega' => $pendingCount,
    ],
    'usuarios' => $rows,
]);
