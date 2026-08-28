<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$user = requireAuthenticated($con);
requireAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'RH']);

$entity = strtoupper(cleanString($input['entidad'] ?? '', 30));
$id = max(0, (int) ($input['id'] ?? 0));
$actorId = (int) $user['id'];

if ($entity === 'CATEGORIA') {
    $name = requireString($input, 'nombre', 'nombre', 100);
    $type = strtoupper(requireString($input, 'tipo', 'tipo', 10));
    $description = cleanString($input['descripcion'] ?? '', 500);
    $active = !array_key_exists('activo', $input) || filter_var($input['activo'], FILTER_VALIDATE_BOOL) ? 1 : 0;
    $order = max(0, (int) ($input['orden'] ?? 0));
    if (!in_array($type, ['SUMA', 'RESTA'], true)) {
        $con->close();
        errorResponse('Tipo de categoría no válido.', 422, 'VALIDATION_ERROR');
    }

    if ($id > 0) {
        $stmt = $con->prepare(
            "UPDATE operativo_recompensa_categoria
             SET nombre = ?, tipo = ?, descripcion = NULLIF(?, ''), activo = ?, orden = ?
             WHERE id = ?"
        );
        if (!$stmt) databaseError($con);
        $stmt->bind_param('sssiii', $name, $type, $description, $active, $order, $id);
    } else {
        $stmt = $con->prepare(
            "INSERT INTO operativo_recompensa_categoria
                (nombre, tipo, descripcion, activo, orden, creado_por)
             VALUES (?, ?, NULLIF(?, ''), ?, ?, ?)"
        );
        if (!$stmt) databaseError($con);
        $stmt->bind_param('sssiii', $name, $type, $description, $active, $order, $actorId);
    }

    if (!$stmt->execute()) {
        $message = $stmt->errno === 1062 ? 'Ya existe una categoría con ese nombre y tipo.' : 'No fue posible guardar la categoría.';
        $stmt->close();
        $con->close();
        errorResponse($message, $message[0] === 'Y' ? 409 : 500, 'CATEGORY_SAVE_ERROR');
    }
    $savedId = $id > 0 ? $id : (int) $stmt->insert_id;
    $stmt->close();
    $con->close();
    okResponse(['id' => $savedId], 'Categoría guardada correctamente.');
}

if ($entity === 'CATALOGO') {
    $categoryId = positiveInt($input['categoria_id'] ?? null, 'categoria_id');
    $name = requireString($input, 'nombre', 'nombre', 140);
    $description = cleanString($input['descripcion'] ?? '', 500);
    $points = max(0, (int) ($input['puntos'] ?? 0));
    $active = !array_key_exists('activo', $input) || filter_var($input['activo'], FILTER_VALIDATE_BOOL) ? 1 : 0;
    if ($points <= 0) {
        $con->close();
        errorResponse('El valor debe ser mayor que cero puntos.', 422, 'VALIDATION_ERROR');
    }

    $existing = null;
    if ($id > 0) {
        $check = $con->prepare("SELECT codigo, origen, es_sistema FROM operativo_recompensa_catalogo WHERE id = ? LIMIT 1");
        if (!$check) databaseError($con);
        $check->bind_param('i', $id);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();
        if (!$existing) {
            $con->close();
            errorResponse('Recompensa de catálogo no encontrada.', 404, 'REWARD_CATALOG_NOT_FOUND');
        }
    }

    if ($existing && (int) $existing['es_sistema'] === 1) {
        // Las reglas AUTO_APARTADO/AUTO_VENDIDO conservan su código y origen;
        // el administrador gestiona nombre, categoría, valor y vigencia.
        $stmt = $con->prepare(
            "UPDATE operativo_recompensa_catalogo
             SET categoria_id = ?, nombre = ?, descripcion = NULLIF(?, ''), puntos = ?, activo = ?
             WHERE id = ?"
        );
        if (!$stmt) databaseError($con);
        $stmt->bind_param('issiii', $categoryId, $name, $description, $points, $active, $id);
    } elseif ($id > 0) {
        $code = strtoupper(trim((string) ($input['codigo'] ?? '')));
        $code = preg_replace('/[^A-Z0-9_\-]/', '_', $code) ?: '';
        if ($code === '') {
            $con->close();
            errorResponse('El código es obligatorio.', 422, 'VALIDATION_ERROR');
        }
        $manual = 1;
        $origin = 'MANUAL';
        $stmt = $con->prepare(
            "UPDATE operativo_recompensa_catalogo
             SET categoria_id = ?, codigo = ?, nombre = ?, descripcion = NULLIF(?, ''), puntos = ?,
                 origen = ?, permite_asignacion_manual = ?, activo = ?
             WHERE id = ?"
        );
        if (!$stmt) databaseError($con);
        $stmt->bind_param('isssisiii', $categoryId, $code, $name, $description, $points, $origin, $manual, $active, $id);
    } else {
        $code = strtoupper(trim((string) ($input['codigo'] ?? '')));
        $code = preg_replace('/[^A-Z0-9_\-]/', '_', $code) ?: '';
        if ($code === '') {
            $con->close();
            errorResponse('El código es obligatorio.', 422, 'VALIDATION_ERROR');
        }
        $manual = 1;
        $system = 0;
        $origin = 'MANUAL';
        $stmt = $con->prepare(
            "INSERT INTO operativo_recompensa_catalogo
                (categoria_id, codigo, nombre, descripcion, puntos, origen,
                 permite_asignacion_manual, es_sistema, activo, creado_por)
             VALUES (?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) databaseError($con);
        $stmt->bind_param('isssisiiii', $categoryId, $code, $name, $description, $points, $origin, $manual, $system, $active, $actorId);
    }

    if (!$stmt->execute()) {
        $errno = $stmt->errno;
        $stmt->close();
        $con->close();
        errorResponse($errno === 1062 ? 'Ya existe una recompensa con ese código.' : 'No fue posible guardar la recompensa.', $errno === 1062 ? 409 : 500, 'REWARD_CATALOG_SAVE_ERROR');
    }
    $savedId = $id > 0 ? $id : (int) $stmt->insert_id;
    $stmt->close();
    $con->close();
    okResponse(['id' => $savedId], 'Recompensa de catálogo guardada correctamente.');
}

if ($entity === 'PREMIO') {
    $name = requireString($input, 'nombre', 'nombre', 140);
    $description = cleanString($input['descripcion'] ?? '', 700);
    $points = max(0, (int) ($input['puntos_requeridos'] ?? 0));
    $active = !array_key_exists('activo', $input) || filter_var($input['activo'], FILTER_VALIDATE_BOOL) ? 1 : 0;
    $order = max(0, (int) ($input['orden'] ?? 0));
    if ($points <= 0) {
        $con->close();
        errorResponse('Los puntos requeridos deben ser mayores que cero.', 422, 'VALIDATION_ERROR');
    }

    if ($id > 0) {
        $stmt = $con->prepare(
            "UPDATE operativo_recompensa_premio
             SET nombre = ?, descripcion = NULLIF(?, ''), puntos_requeridos = ?, activo = ?, orden = ?
             WHERE id = ?"
        );
        if (!$stmt) databaseError($con);
        $stmt->bind_param('ssiiii', $name, $description, $points, $active, $order, $id);
    } else {
        $stmt = $con->prepare(
            "INSERT INTO operativo_recompensa_premio
                (nombre, descripcion, puntos_requeridos, activo, orden, creado_por)
             VALUES (?, NULLIF(?, ''), ?, ?, ?, ?)"
        );
        if (!$stmt) databaseError($con);
        $stmt->bind_param('ssiiii', $name, $description, $points, $active, $order, $actorId);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        $con->close();
        errorResponse('No fue posible guardar el premio.', 500, 'PRIZE_SAVE_ERROR');
    }
    $savedId = $id > 0 ? $id : (int) $stmt->insert_id;
    $stmt->close();
    $con->close();
    okResponse(['id' => $savedId], 'Premio guardado correctamente.');
}

$con->close();
errorResponse('Entidad de gestión no válida.', 422, 'VALIDATION_ERROR');
