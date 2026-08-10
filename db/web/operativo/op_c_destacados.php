<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

bootstrapApi(false);
$con = connectDatabase();
$user = requireAuthenticated($con);

$stmt = $con->prepare(
    "SELECT
        d.posicion,
        a.id,
        a.marca,
        a.modelo,
        a.anio,
        a.precio,
        a.kilometraje,
        a.ubicacion,
        a.img_principal,
        a.estatus,
        d.actualizado_en
     FROM operativo_auto_destacado d
     INNER JOIN autos a ON a.id = d.auto_id
     WHERE d.posicion BETWEEN 1 AND 3
       AND a.estatus = 'Disponible'
     ORDER BY d.posicion"
);
if (!$stmt) {
    databaseError($con);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$con->close();

$byPosition = [];
foreach ($rows as $row) {
    $position = (int) $row['posicion'];
    $byPosition[$position] = [
        'posicion' => $position,
        'id' => (int) $row['id'],
        'marca' => (string) $row['marca'],
        'modelo' => (string) $row['modelo'],
        'anio' => (int) $row['anio'],
        'precio' => (float) $row['precio'],
        'kilometraje' => (int) $row['kilometraje'],
        'ubicacion' => (string) $row['ubicacion'],
        'img_principal' => (string) $row['img_principal'],
        'estatus' => (string) $row['estatus'],
        'actualizado_en' => $row['actualizado_en'],
    ];
}

$slots = [];
for ($position = 1; $position <= 3; $position++) {
    $slots[] = $byPosition[$position] ?? [
        'posicion' => $position,
        'id' => null,
        'marca' => null,
        'modelo' => null,
        'anio' => null,
        'precio' => null,
        'kilometraje' => null,
        'ubicacion' => null,
        'img_principal' => null,
        'estatus' => null,
        'actualizado_en' => null,
    ];
}

okResponse([
    'items' => $slots,
    'permisos' => [
        'puede_editar' => canManageCatalog($user),
    ],
]);
