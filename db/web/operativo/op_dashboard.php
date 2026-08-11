<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

bootstrapApi(false);
$con = connectDatabase();
$user = requireAuthenticated($con);

function dashboardScalarCount(mysqli $con, string $sql): int
{
    $result = $con->query($sql);
    if (!$result) {
        databaseError($con);
    }
    return (int) ($result->fetch_assoc()['total'] ?? 0);
}

function dashboardRequirementCount(
    mysqli $con,
    string $status,
    ?array $visibleUserIds
): int {
    if ($visibleUserIds === null) {
        $stmt = $con->prepare(
            'SELECT COUNT(*) AS total
             FROM operativo_requerimiento_compra
             WHERE estatus = ?'
        );
        if (!$stmt) {
            databaseError($con);
        }
        $stmt->bind_param('s', $status);
    } elseif ($visibleUserIds === []) {
        return 0;
    } else {
        $placeholders = implode(',', array_fill(0, count($visibleUserIds), '?'));
        $sql = "SELECT COUNT(*) AS total
                FROM operativo_requerimiento_compra
                WHERE estatus = ?
                  AND (creado_por IN ({$placeholders}) OR asignado_a IN ({$placeholders}))";
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            databaseError($con);
        }
        $types = 's' . str_repeat('i', count($visibleUserIds) * 2);
        $params = array_merge([$status], $visibleUserIds, $visibleUserIds);
        bindDynamicParams($stmt, $types, $params);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        databaseError($con);
    }
    $total = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    return $total;
}

function dashboardLatestRequirements(
    mysqli $con,
    ?array $visibleUserIds
): array {
    $where = '';
    $types = '';
    $params = [];

    if ($visibleUserIds !== null) {
        if ($visibleUserIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($visibleUserIds), '?'));
        $where = "WHERE (r.creado_por IN ({$placeholders}) OR r.asignado_a IN ({$placeholders}))";
        $types = str_repeat('i', count($visibleUserIds) * 2);
        $params = array_merge($visibleUserIds, $visibleUserIds);
    }

    $sql = "SELECT
                r.id, r.folio, r.cliente_nombre, r.estatus,
                r.fecha_solicitud, r.monto_propuesto,
                a.id AS auto_id, a.marca, a.modelo, a.anio,
                CONCAT(u.nombre, ' ', u.apellido_paterno) AS responsable
            FROM operativo_requerimiento_compra r
            INNER JOIN autos a ON a.id = r.auto_id
            INNER JOIN operativo_usuario u ON u.id = r.asignado_a
            {$where}
            ORDER BY r.fecha_solicitud DESC
            LIMIT 6";

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        databaseError($con);
    }
    if ($types !== '') {
        bindDynamicParams($stmt, $types, $params);
    }
    if (!$stmt->execute()) {
        $stmt->close();
        databaseError($con);
    }
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

$salesOnly = isSalesOnlyOperationalUser($user);
$visibleUserIds = requirementVisibleUserIds($con, $user);

// Los indicadores comerciales siempre respetan el alcance jerárquico del
// usuario: un supervisor ve lo suyo + toda su descendencia; un vendedor solo
// lo suyo; perfiles con acceso global conservan la vista completa.
$requested = dashboardRequirementCount($con, 'Solicitado', $visibleUserIds);
$reserved = dashboardRequirementCount($con, 'Apartado', $visibleUserIds);
$sold = dashboardRequirementCount($con, 'Vendido', $visibleUserIds);

// Un usuario exclusivamente VENTAS no necesita recibir ni mostrar los demás
// totalizadores. Los demás perfiles sí conservan todos los indicadores.
$catalogTotal = $salesOnly
    ? null
    : dashboardScalarCount($con, 'SELECT COUNT(*) AS total FROM autos');
$catalogAvailable = $salesOnly
    ? null
    : dashboardScalarCount($con, "SELECT COUNT(*) AS total FROM autos WHERE estatus = 'Disponible'");

$pendingApprovals = null;
if (!$salesOnly) {
    if (hasFullRequestApprovalAccess($user)) {
        $pendingApprovals = dashboardScalarCount(
            $con,
            "SELECT COUNT(*) AS total
             FROM operativo_requerimiento_cambio
             WHERE decision = 'Pendiente'"
        );
    } elseif (hasAnyRole($user, ['AUTORIZADOR'])) {
        // Un supervisor solamente cuenta autorizaciones de subordinados
        // directos. Nunca cuenta ni puede resolver sus propias solicitudes.
        $stmt = $con->prepare(
            "SELECT COUNT(*) AS total
             FROM operativo_requerimiento_cambio c
             WHERE c.decision = 'Pendiente'
               AND c.solicitado_por <> ?
               AND EXISTS (
                   SELECT 1
                   FROM operativo_usuario_jerarquia j
                   WHERE j.usuario_id = c.solicitado_por
                     AND j.supervisor_id = ?
                     AND j.activo = 1
               )"
        );
        if (!$stmt) {
            databaseError($con);
        }
        $userId = (int) $user['id'];
        $stmt->bind_param('ii', $userId, $userId);
        $stmt->execute();
        $pendingApprovals = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $stmt->close();
    } else {
        $pendingApprovals = 0;
    }
}

$latest = dashboardLatestRequirements($con, $visibleUserIds);
foreach ($latest as &$item) {
    $item['id'] = (int) $item['id'];
    $item['auto_id'] = (int) $item['auto_id'];
    $item['anio'] = (int) $item['anio'];
    $item['monto_propuesto'] = $item['monto_propuesto'] !== null
        ? (float) $item['monto_propuesto']
        : null;
}
unset($item);

$con->close();

okResponse([
    'resumen' => [
        'catalogo_total' => $catalogTotal,
        'catalogo_disponibles' => $catalogAvailable,
        'requerimientos_solicitados' => $requested,
        'requerimientos_apartados' => $reserved,
        'requerimientos_vendidos' => $sold,
        'autorizaciones_pendientes' => $pendingApprovals,
    ],
    'metricas_visibles' => $salesOnly
        ? [
            'requerimientos_solicitados',
            'requerimientos_apartados',
            'requerimientos_vendidos',
        ]
        : [
            'catalogo_total',
            'catalogo_disponibles',
            'requerimientos_solicitados',
            'requerimientos_apartados',
            'requerimientos_vendidos',
            'autorizaciones_pendientes',
        ],
    'alcance_requerimientos' => [
        'global' => $visibleUserIds === null,
        'usuario_ids' => $visibleUserIds,
    ],
    'ultimos_requerimientos' => $latest,
]);
