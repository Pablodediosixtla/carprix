<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

bootstrapApi(false);
$con = connectDatabase();
$user = requireAuthenticated($con);

$fields = [
    'marcas' => 'marca',
    'tipos' => 'tipo',
    'ubicaciones' => 'ubicacion',
    'transmisiones' => 'transmision',
    'colores' => 'color',
    'motores' => 'motor',
    'combustibles' => 'combustible',
    'tracciones' => 'traccion',
];

$options = [];
foreach ($fields as $key => $column) {
    $sql = "SELECT DISTINCT {$column} AS valor
            FROM autos
            WHERE {$column} IS NOT NULL
              AND TRIM({$column}) <> ''
            ORDER BY {$column}";
    $result = $con->query($sql);
    if (!$result) {
        databaseError($con);
    }
    $options[$key] = array_values(array_filter(array_map(
        static fn(array $row): string => trim((string) ($row['valor'] ?? '')),
        $result->fetch_all(MYSQLI_ASSOC)
    )));
    $result->free();
}

$defaults = [
    'transmisiones' => ['Automatico', 'Automático', 'Manual'],
    'combustibles' => ['Gasolina', 'Diesel', 'Hibrido', 'Híbrido', 'Electrico', 'Eléctrico'],
    'tracciones' => ['Delantera', 'Trasera', '4x2', '4x4', 'AWD'],
];

foreach ($defaults as $key => $values) {
    $options[$key] = array_values(array_unique(array_merge($options[$key] ?? [], $values)));
    natcasesort($options[$key]);
    $options[$key] = array_values($options[$key]);
}

$con->close();

okResponse([
    'opciones' => $options,
    'permisos' => [
        'puede_editar' => canManageCatalog($user),
    ],
]);
