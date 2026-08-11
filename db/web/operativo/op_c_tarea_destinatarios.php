<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

bootstrapApi(false);
$con = connectDatabase();
$user = requireAuthenticated($con);

$ids = array_values(array_unique(taskAssignableUserIds($con, $user)));
if ($ids === []) {
    $con->close();
    okResponse(['items' => []]);
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));
$params = $ids;

$sql = "SELECT
            u.id,
            u.username,
            u.nombre,
            u.apellido_paterno,
            u.apellido_materno,
            j.supervisor_id,
            CONCAT_WS(' ', s.nombre, s.apellido_paterno, s.apellido_materno) AS supervisor_nombre,
            GROUP_CONCAT(DISTINCT r.codigo ORDER BY r.codigo SEPARATOR ',') AS roles
        FROM operativo_usuario u
        LEFT JOIN operativo_usuario_jerarquia j
            ON j.usuario_id = u.id
           AND j.activo = 1
        LEFT JOIN operativo_usuario s ON s.id = j.supervisor_id
        LEFT JOIN operativo_usuario_rol ur
            ON ur.usuario_id = u.id
           AND ur.activo = 1
        LEFT JOIN operativo_rol r
            ON r.id = ur.rol_id
           AND r.activo = 1
        WHERE u.estatus = 'Activo'
          AND u.id IN ({$placeholders})
        GROUP BY
            u.id, u.username, u.nombre, u.apellido_paterno, u.apellido_materno,
            j.supervisor_id, s.nombre, s.apellido_paterno, s.apellido_materno
        ORDER BY
            CASE WHEN u.id = ? THEN 0 ELSE 1 END,
            u.apellido_paterno, u.nombre";

$types .= 'i';
$params[] = (int) $user['id'];
$stmt = $con->prepare($sql);
if (!$stmt) {
    databaseError($con);
}
bindDynamicParams($stmt, $types, $params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$con->close();

foreach ($rows as &$row) {
    $row['id'] = (int) $row['id'];
    $row['supervisor_id'] = $row['supervisor_id'] !== null ? (int) $row['supervisor_id'] : null;
    $roles = trim((string) ($row['roles'] ?? ''));
    $row['roles'] = $roles === '' ? [] : explode(',', $roles);
    $row['nombre_completo'] = trim(implode(' ', array_filter([
        $row['nombre'] ?? '',
        $row['apellido_paterno'] ?? '',
        $row['apellido_materno'] ?? '',
    ])));
}
unset($row);

okResponse(['items' => $rows]);
