<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

bootstrapApi(false);
$con = connectDatabase();
$user = requireAuthenticated($con);

$ids = rewardAssignableUserIds($con, $user);
if ($ids === []) {
    $con->close();
    okResponse(['personas' => [], 'catalogo' => []]);
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));
$params = $ids;
$stmt = $con->prepare(
    "SELECT id, username, nombre, apellido_paterno, apellido_materno
     FROM operativo_usuario
     WHERE estatus = 'Activo'
       AND id IN ({$placeholders})
     ORDER BY apellido_paterno, apellido_materno, nombre"
);
if (!$stmt) {
    databaseError($con);
}
bindDynamicParams($stmt, $types, $params);
$stmt->execute();
$people = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
foreach ($people as &$person) {
    $person['id'] = (int) $person['id'];
    $person['nombre_completo'] = trim(implode(' ', array_filter([
        $person['nombre'] ?? '',
        $person['apellido_paterno'] ?? '',
        $person['apellido_materno'] ?? '',
    ])));
}
unset($person);

$result = $con->query(
    "SELECT
        rc.id, rc.codigo, rc.nombre, rc.descripcion, rc.puntos,
        cat.nombre AS categoria_nombre, cat.tipo AS categoria_tipo
     FROM operativo_recompensa_catalogo rc
     INNER JOIN operativo_recompensa_categoria cat
        ON cat.id = rc.categoria_id
       AND cat.activo = 1
     WHERE rc.activo = 1
       AND rc.permite_asignacion_manual = 1
       AND rc.origen = 'MANUAL'
     ORDER BY cat.orden, cat.nombre, rc.nombre"
);
if (!$result) {
    databaseError($con);
}
$catalog = $result->fetch_all(MYSQLI_ASSOC);
$con->close();
foreach ($catalog as &$item) {
    $item['id'] = (int) $item['id'];
    $item['puntos'] = (int) $item['puntos'];
    $item['puntos_aplicados'] = rewardSignedPoints((string) $item['categoria_tipo'], (int) $item['puntos']);
}
unset($item);

okResponse(['personas' => $people, 'catalogo' => $catalog]);
