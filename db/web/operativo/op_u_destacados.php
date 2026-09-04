<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$user = requireAuthenticated($con);

if (!canManageCatalog($user)) {
    $con->close();
    errorResponse('No tienes permisos para administrar los autos destacados.', 403, 'FORBIDDEN');
}

$autoId = positiveInt($input['auto_id'] ?? null, 'auto_id');
$highlighted = filter_var($input['destacado'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if ($highlighted === null) {
    $con->close();
    errorResponse('El valor destacado es obligatorio.', 422, 'VALIDATION_ERROR');
}

$con->begin_transaction();
try {
    $autoStmt = $con->prepare(
        'SELECT id, estatus FROM autos WHERE id = ? FOR UPDATE'
    );
    if (!$autoStmt) {
        databaseError($con);
    }
    $autoStmt->bind_param('i', $autoId);
    $autoStmt->execute();
    $auto = $autoStmt->get_result()->fetch_assoc();
    $autoStmt->close();

    if (!$auto) {
        throw new DomainException('AUTO_NOT_FOUND');
    }

    $featuredStmt = $con->prepare(
        'SELECT posicion, auto_id
         FROM operativo_auto_destacado
         WHERE posicion BETWEEN 1 AND 3
         ORDER BY posicion
         FOR UPDATE'
    );
    if (!$featuredStmt) {
        databaseError($con);
    }
    $featuredStmt->execute();
    $currentRows = $featuredStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $featuredStmt->close();

    $currentIds = array_values(array_map(
        static fn(array $row): int => (int) $row['auto_id'],
        $currentRows
    ));
    $alreadyFeatured = in_array($autoId, $currentIds, true);

    if ($highlighted) {
        if ((string) $auto['estatus'] !== 'Disponible') {
            throw new DomainException('FEATURED_AUTO_NOT_AVAILABLE');
        }
        if (!$alreadyFeatured) {
            if (count($currentIds) >= 3) {
                throw new DomainException('FEATURED_LIMIT_REACHED');
            }
            $currentIds[] = $autoId;
        }
    } else {
        $currentIds = array_values(array_filter(
            $currentIds,
            static fn(int $id): bool => $id !== $autoId
        ));
    }

    if (!$con->query('DELETE FROM operativo_auto_destacado WHERE posicion BETWEEN 1 AND 3')) {
        databaseError($con);
    }

    if ($currentIds !== []) {
        $insert = $con->prepare(
            'INSERT INTO operativo_auto_destacado (posicion, auto_id, actualizado_por)
             VALUES (?, ?, ?)'
        );
        if (!$insert) {
            databaseError($con);
        }

        $userId = (int) $user['id'];
        foreach ($currentIds as $index => $selectedAutoId) {
            $position = $index + 1;
            $insert->bind_param('iii', $position, $selectedAutoId, $userId);
            if (!$insert->execute()) {
                $insert->close();
                databaseError($con);
            }
        }
        $insert->close();
    }

    $con->commit();
    $con->close();

    okResponse([
        'auto_id' => $autoId,
        'destacado' => $highlighted,
        'auto_ids' => $currentIds,
        'seleccionados' => count($currentIds),
        'maximo' => 3,
    ], $highlighted
        ? 'Auto agregado a destacados correctamente.'
        : 'Auto retirado de destacados correctamente.');
} catch (DomainException $e) {
    $con->rollback();
    $code = $e->getMessage();
    $con->close();

    if ($code === 'AUTO_NOT_FOUND') {
        errorResponse('El auto seleccionado no existe.', 404, $code);
    }
    if ($code === 'FEATURED_AUTO_NOT_AVAILABLE') {
        errorResponse(
            'Solo los autos con estatus Disponible pueden agregarse a destacados.',
            409,
            $code,
            ['auto_id' => $autoId, 'estatus' => (string) ($auto['estatus'] ?? '')]
        );
    }
    if ($code === 'FEATURED_LIMIT_REACHED') {
        errorResponse(
            'Ya existen tres autos destacados. Retira una estrella antes de seleccionar otro.',
            409,
            $code,
            ['maximo' => 3]
        );
    }

    errorResponse('No fue posible actualizar el auto destacado.', 400, $code);
} catch (Throwable $e) {
    $con->rollback();
    databaseError($con, $e);
}
