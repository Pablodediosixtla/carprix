<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

bootstrapApi(false);
$con = connectDatabase();
requireAuthenticated($con);

$sql = "SELECT
            u.id,
            u.nombre,
            u.apellido_paterno,
            u.apellido_materno,
            u.fecha_nacimiento,
            DAY(u.fecha_nacimiento) AS dia,
            GROUP_CONCAT(DISTINCT r.codigo ORDER BY r.codigo SEPARATOR ',') AS roles
        FROM operativo_usuario u
        LEFT JOIN operativo_usuario_rol ur
            ON ur.usuario_id = u.id
           AND ur.activo = 1
        LEFT JOIN operativo_rol r
            ON r.id = ur.rol_id
           AND r.activo = 1
        WHERE u.estatus = 'Activo'
          AND u.fecha_nacimiento IS NOT NULL
          AND MONTH(u.fecha_nacimiento) = MONTH(CURDATE())
        GROUP BY
            u.id, u.nombre, u.apellido_paterno, u.apellido_materno,
            u.fecha_nacimiento
        ORDER BY DAY(u.fecha_nacimiento), u.apellido_paterno, u.nombre";

$result = $con->query($sql);
if (!$result) {
    databaseError($con);
}
$rows = $result->fetch_all(MYSQLI_ASSOC);
$con->close();

$todayDay = (int) date('j');
foreach ($rows as &$row) {
    $roles = trim((string) ($row['roles'] ?? ''));
    $row['id'] = (int) $row['id'];
    $row['dia'] = (int) $row['dia'];
    $row['es_hoy'] = $row['dia'] === $todayDay;
    $row['roles'] = $roles === '' ? [] : explode(',', $roles);
    $row['nombre_completo'] = trim(implode(' ', array_filter([
        $row['nombre'] ?? '',
        $row['apellido_paterno'] ?? '',
        $row['apellido_materno'] ?? '',
    ])));
}
unset($row);

okResponse([
    'items' => $rows,
    'total' => count($rows),
    'mes' => (int) date('n'),
]);
