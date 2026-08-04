<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

bootstrapApi(false);
$con = connectDatabase();
$user = requireAuthenticated($con);

function scalarCount(mysqli $con, string $sql): int
{
    $result = $con->query($sql);
    if (!$result) {
        databaseError($con);
    }
    return (int) ($result->fetch_assoc()['total'] ?? 0);
}

$catalogTotal = scalarCount($con, 'SELECT COUNT(*) AS total FROM autos');
$catalogAvailable = scalarCount($con, "SELECT COUNT(*) AS total FROM autos WHERE estatus = 'Disponible'");
$requested = scalarCount($con, "SELECT COUNT(*) AS total FROM operativo_requerimiento_compra WHERE estatus = 'Solicitado'");
$reserved = scalarCount($con, "SELECT COUNT(*) AS total FROM operativo_requerimiento_compra WHERE estatus = 'Apartado'");
$sold = scalarCount($con, "SELECT COUNT(*) AS total FROM operativo_requerimiento_compra WHERE estatus = 'Vendido'");

if (isSuperAdmin($user) || hasAnyRole($user, ['ADMIN_OPERATIVO'])) {
    $pendingApprovals = scalarCount($con, "SELECT COUNT(*) AS total FROM operativo_requerimiento_cambio WHERE decision = 'Pendiente'");
} else {
    $stmt = $con->prepare("SELECT COUNT(*) AS total
                           FROM operativo_requerimiento_cambio
                           WHERE decision = 'Pendiente'
                             AND aprobador_id = ?");
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $pendingApprovals = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
}

$latestSql = "SELECT
                r.id, r.folio, r.cliente_nombre, r.estatus,
                r.fecha_solicitud, r.monto_propuesto,
                a.id AS auto_id, a.marca, a.modelo, a.anio,
                CONCAT(u.nombre, ' ', u.apellido_paterno) AS responsable
              FROM operativo_requerimiento_compra r
              INNER JOIN autos a ON a.id = r.auto_id
              INNER JOIN operativo_usuario u ON u.id = r.asignado_a
              ORDER BY r.fecha_solicitud DESC
              LIMIT 6";
$latest = $con->query($latestSql)->fetch_all(MYSQLI_ASSOC);

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
    'ultimos_requerimientos' => $latest,
]);
