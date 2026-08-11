<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

bootstrapApi(false);
$con = connectDatabase();
$user = requireAuthenticated($con);
requireAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO']);

$categoriesResult = $con->query(
    "SELECT id, nombre, tipo, descripcion, activo, orden, creado_en, actualizado_en
     FROM operativo_recompensa_categoria
     ORDER BY orden, nombre, id"
);
if (!$categoriesResult) {
    databaseError($con);
}
$categories = $categoriesResult->fetch_all(MYSQLI_ASSOC);

$catalogResult = $con->query(
    "SELECT rc.id, rc.categoria_id, rc.codigo, rc.nombre, rc.descripcion, rc.puntos,
            rc.origen, rc.permite_asignacion_manual, rc.es_sistema, rc.activo,
            cat.nombre AS categoria_nombre, cat.tipo AS categoria_tipo
     FROM operativo_recompensa_catalogo rc
     INNER JOIN operativo_recompensa_categoria cat ON cat.id = rc.categoria_id
     ORDER BY rc.es_sistema DESC, cat.orden, cat.nombre, rc.nombre"
);
if (!$catalogResult) {
    databaseError($con);
}
$catalog = $catalogResult->fetch_all(MYSQLI_ASSOC);

$prizesResult = $con->query(
    "SELECT id, nombre, descripcion, puntos_requeridos, activo, orden, creado_en, actualizado_en
     FROM operativo_recompensa_premio
     ORDER BY puntos_requeridos, orden, id"
);
if (!$prizesResult) {
    databaseError($con);
}
$prizes = $prizesResult->fetch_all(MYSQLI_ASSOC);

foreach ($categories as &$item) {
    $item['id'] = (int) $item['id'];
    $item['activo'] = (bool) $item['activo'];
    $item['orden'] = (int) $item['orden'];
}
unset($item);
foreach ($catalog as &$item) {
    $item['id'] = (int) $item['id'];
    $item['categoria_id'] = (int) $item['categoria_id'];
    $item['puntos'] = (int) $item['puntos'];
    $item['permite_asignacion_manual'] = (bool) $item['permite_asignacion_manual'];
    $item['es_sistema'] = (bool) $item['es_sistema'];
    $item['activo'] = (bool) $item['activo'];
}
unset($item);
foreach ($prizes as &$item) {
    $item['id'] = (int) $item['id'];
    $item['puntos_requeridos'] = (int) $item['puntos_requeridos'];
    $item['activo'] = (bool) $item['activo'];
    $item['orden'] = (int) $item['orden'];
}
unset($item);

$currentYear = rewardsCurrentYear();
$yearsResult = $con->query(
    "SELECT DISTINCT anio
     FROM operativo_recompensa_movimiento
     ORDER BY anio DESC"
);
if (!$yearsResult) {
    databaseError($con);
}
$availableYears = array_map('intval', array_column($yearsResult->fetch_all(MYSQLI_ASSOC), 'anio'));
if (!in_array($currentYear, $availableYears, true)) {
    array_unshift($availableYears, $currentYear);
}

$con->close();
okResponse([
    'categorias' => $categories,
    'catalogo' => $catalog,
    'premios' => $prizes,
    'anio_actual' => $currentYear,
    'anios_disponibles' => $availableYears,
]);
