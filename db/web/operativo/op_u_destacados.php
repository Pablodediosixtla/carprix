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

$rawIds = $input['auto_ids'] ?? null;
if (!is_array($rawIds) || count($rawIds) !== 3) {
    $con->close();
    errorResponse('Debes seleccionar exactamente tres autos destacados.', 422, 'VALIDATION_ERROR');
}

$autoIds = [];
foreach ($rawIds as $index => $value) {
    $autoIds[] = positiveInt($value, 'auto_ids[' . $index . ']');
}

if (count(array_unique($autoIds)) !== 3) {
    $con->close();
    errorResponse('Los tres autos destacados deben ser diferentes.', 422, 'DUPLICATE_FEATURED_AUTO');
}

$con->begin_transaction();
try {
    $placeholders = implode(',', array_fill(0, 3, '?'));
    $stmt = $con->prepare(
        "SELECT id, estatus
         FROM autos
         WHERE id IN ({$placeholders})
         FOR UPDATE"
    );
    if (!$stmt) {
        databaseError($con);
    }
    $params = $autoIds;
    bindDynamicParams($stmt, 'iii', $params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (count($rows) !== 3) {
        throw new DomainException('AUTO_NOT_FOUND');
    }

    foreach ($rows as $row) {
        if ((string) $row['estatus'] !== 'Disponible') {
            throw new DomainException('FEATURED_AUTO_NOT_AVAILABLE:' . (int) $row['id'] . ':' . (string) $row['estatus']);
        }
    }

    if (!$con->query('DELETE FROM operativo_auto_destacado WHERE posicion BETWEEN 1 AND 3')) {
        databaseError($con);
    }

    $insert = $con->prepare(
        'INSERT INTO operativo_auto_destacado (posicion, auto_id, actualizado_por)
         VALUES (?, ?, ?)'
    );
    if (!$insert) {
        databaseError($con);
    }

    $userId = (int) $user['id'];
    foreach ($autoIds as $offset => $autoId) {
        $position = $offset + 1;
        $insert->bind_param('iii', $position, $autoId, $userId);
        if (!$insert->execute()) {
            $insert->close();
            databaseError($con);
        }
    }
    $insert->close();

    $con->commit();
    $con->close();
    okResponse(['auto_ids' => $autoIds], 'Autos destacados actualizados correctamente.');
} catch (DomainException $e) {
    $con->rollback();
    $code = $e->getMessage();
    $con->close();

    if ($code === 'AUTO_NOT_FOUND') {
        errorResponse('Uno o más autos seleccionados no existen.', 404, $code);
    }

    if (str_starts_with($code, 'FEATURED_AUTO_NOT_AVAILABLE:')) {
        [, $autoId, $status] = array_pad(explode(':', $code, 3), 3, '');
        errorResponse(
            'Solo los autos con estatus Disponible pueden mostrarse como destacados.',
            409,
            'FEATURED_AUTO_NOT_AVAILABLE',
            ['auto_id' => (int) $autoId, 'estatus' => $status]
        );
    }

    errorResponse('No fue posible actualizar los autos destacados.', 400, $code);
} catch (Throwable $e) {
    $con->rollback();
    databaseError($con, $e);
}
