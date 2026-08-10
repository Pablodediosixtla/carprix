<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

function normalizeAutoUploadFiles(array $files): array
{
    if (!isset($files['name'])) {
        return [];
    }

    if (!is_array($files['name'])) {
        return [[
            'name' => (string) $files['name'],
            'type' => (string) ($files['type'] ?? ''),
            'tmp_name' => (string) ($files['tmp_name'] ?? ''),
            'error' => (int) ($files['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($files['size'] ?? 0),
        ]];
    }

    $normalized = [];
    foreach ($files['name'] as $index => $name) {
        $normalized[] = [
            'name' => (string) $name,
            'type' => (string) ($files['type'][$index] ?? ''),
            'tmp_name' => (string) ($files['tmp_name'][$index] ?? ''),
            'error' => (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($files['size'][$index] ?? 0),
        ];
    }

    return $normalized;
}

function decodeIdArray(mixed $value): array
{
    $decoded = json_decode((string) ($value ?? '[]'), true);
    if (!is_array($decoded)) {
        return [];
    }

    return array_values(array_unique(array_filter(
        array_map('intval', $decoded),
        static fn(int $id): bool => $id > 0
    )));
}

function decodePathArray(mixed $value): array
{
    $decoded = json_decode((string) ($value ?? '[]'), true);
    if (!is_array($decoded)) {
        return [];
    }

    $paths = [];
    foreach ($decoded as $path) {
        $clean = cleanString($path, 500);
        if ($clean !== '') {
            $paths[$clean] = true;
        }
    }
    return array_keys($paths);
}

function localAutoImageAbsolutePath(string $projectRoot, int $autoId, string $route): ?string
{
    $route = ltrim(str_replace('\\', '/', trim($route)), '/');
    $prefix = "Catalogo/{$autoId}/";

    if ($route === '' || !str_starts_with($route, $prefix) || str_contains($route, '..')) {
        return null;
    }

    return rtrim($projectRoot, '/') . '/' . $route;
}

function targetDimensions(int $width, int $height, int $maxWidth = 1920, int $maxHeight = 1440): array
{
    if ($width <= 0 || $height <= 0) {
        return [0, 0];
    }

    $ratio = min(1, $maxWidth / $width, $maxHeight / $height);
    return [
        max(1, (int) round($width * $ratio)),
        max(1, (int) round($height * $ratio)),
    ];
}

function orientGdImage(GdImage $image, string $tmpName, string $mime): GdImage
{
    if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
        return $image;
    }

    $exif = @exif_read_data($tmpName);
    $orientation = (int) ($exif['Orientation'] ?? 1);
    $rotated = null;

    if ($orientation === 3) {
        $rotated = imagerotate($image, 180, 0);
    } elseif ($orientation === 6) {
        $rotated = imagerotate($image, -90, 0);
    } elseif ($orientation === 8) {
        $rotated = imagerotate($image, 90, 0);
    }

    if ($rotated instanceof GdImage) {
        imagedestroy($image);
        return $rotated;
    }

    return $image;
}

function compressImageWithGd(string $tmpName, string $mime, string $destinationBase): array
{
    if (!function_exists('imagecreatefromstring')) {
        throw new RuntimeException('GD_NOT_AVAILABLE');
    }

    $raw = @file_get_contents($tmpName);
    if ($raw === false) {
        throw new RuntimeException('IMAGE_READ_ERROR');
    }

    $source = @imagecreatefromstring($raw);
    if (!$source instanceof GdImage) {
        throw new RuntimeException('IMAGE_DECODE_ERROR');
    }

    $source = orientGdImage($source, $tmpName, $mime);
    $width = imagesx($source);
    $height = imagesy($source);
    [$targetWidth, $targetHeight] = targetDimensions($width, $height);

    $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
    if (!$canvas instanceof GdImage) {
        imagedestroy($source);
        throw new RuntimeException('IMAGE_MEMORY_ERROR');
    }

    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
    imagefill($canvas, 0, 0, $transparent);

    if (!imagecopyresampled(
        $canvas,
        $source,
        0,
        0,
        0,
        0,
        $targetWidth,
        $targetHeight,
        $width,
        $height
    )) {
        imagedestroy($source);
        imagedestroy($canvas);
        throw new RuntimeException('IMAGE_RESIZE_ERROR');
    }

    imagedestroy($source);

    if (function_exists('imagewebp')) {
        $destination = $destinationBase . '.webp';
        $saved = imagewebp($canvas, $destination, 82);
        $extension = 'webp';
    } else {
        // JPEG fallback: fondo blanco para imágenes con transparencia.
        $jpegCanvas = imagecreatetruecolor($targetWidth, $targetHeight);
        $white = imagecolorallocate($jpegCanvas, 255, 255, 255);
        imagefill($jpegCanvas, 0, 0, $white);
        imagealphablending($jpegCanvas, true);
        imagecopy($jpegCanvas, $canvas, 0, 0, 0, 0, $targetWidth, $targetHeight);
        $destination = $destinationBase . '.jpg';
        $saved = imagejpeg($jpegCanvas, $destination, 82);
        imagedestroy($jpegCanvas);
        $extension = 'jpg';
    }

    imagedestroy($canvas);

    if (!$saved || !is_file($destination) || filesize($destination) <= 0) {
        @unlink($destination);
        throw new RuntimeException('IMAGE_SAVE_ERROR');
    }

    return [
        'path' => $destination,
        'extension' => $extension,
        'width' => $targetWidth,
        'height' => $targetHeight,
        'bytes' => (int) filesize($destination),
    ];
}

function compressImageWithImagick(string $tmpName, string $destinationBase): array
{
    if (!class_exists('Imagick')) {
        throw new RuntimeException('IMAGICK_NOT_AVAILABLE');
    }

    $image = new Imagick($tmpName);
    if (method_exists($image, 'autoOrient')) {
        $image->autoOrient();
    } elseif (method_exists($image, 'autoOrientImage')) {
        $image->autoOrientImage();
    }

    $width = $image->getImageWidth();
    $height = $image->getImageHeight();
    [$targetWidth, $targetHeight] = targetDimensions($width, $height);
    if ($targetWidth !== $width || $targetHeight !== $height) {
        $image->thumbnailImage($targetWidth, $targetHeight, true, true);
    }

    $supportsWebp = in_array('WEBP', $image->queryFormats('WEBP'), true);
    if ($supportsWebp) {
        $extension = 'webp';
        $image->setImageFormat('webp');
        $image->setImageCompressionQuality(82);
    } else {
        $extension = 'jpg';
        $image->setImageBackgroundColor('white');
        if ($image->getImageAlphaChannel()) {
            $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        }
        $image->setImageFormat('jpeg');
        $image->setImageCompressionQuality(82);
    }

    $destination = $destinationBase . '.' . $extension;
    if (!$image->writeImage($destination)) {
        $image->clear();
        $image->destroy();
        throw new RuntimeException('IMAGE_SAVE_ERROR');
    }

    $finalWidth = $image->getImageWidth();
    $finalHeight = $image->getImageHeight();
    $image->clear();
    $image->destroy();

    if (!is_file($destination) || filesize($destination) <= 0) {
        @unlink($destination);
        throw new RuntimeException('IMAGE_SAVE_ERROR');
    }

    return [
        'path' => $destination,
        'extension' => $extension,
        'width' => $finalWidth,
        'height' => $finalHeight,
        'bytes' => (int) filesize($destination),
    ];
}

function compressCatalogImage(string $tmpName, string $mime, string $destinationBase): array
{
    if (function_exists('imagecreatefromstring')) {
        return compressImageWithGd($tmpName, $mime, $destinationBase);
    }
    if (class_exists('Imagick')) {
        return compressImageWithImagick($tmpName, $destinationBase);
    }

    // Fallback para App Service sin GD/Imagick. La vista operativa ya
    // comprime las imágenes en el navegador antes de enviarlas.
    $extension = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => throw new RuntimeException('IMAGE_TYPE_NOT_ALLOWED'),
    };
    $destination = $destinationBase . '.' . $extension;
    if (!copy($tmpName, $destination) || !is_file($destination) || filesize($destination) <= 0) {
        @unlink($destination);
        throw new RuntimeException('IMAGE_SAVE_ERROR');
    }
    $dimensions = @getimagesize($destination);
    return [
        'path' => $destination,
        'extension' => $extension,
        'width' => (int) ($dimensions[0] ?? 0),
        'height' => (int) ($dimensions[1] ?? 0),
        'bytes' => (int) filesize($destination),
    ];
}

$input = bootstrapMultipartApi(true);
$con = connectDatabase();
$user = requireAuthenticated($con);
requireAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'INVENTARIO']);

$autoId = positiveInt($input['auto_id'] ?? null, 'auto_id');
$removeIds = decodeIdArray($input['remove_image_ids'] ?? '[]');
$removePaths = decodePathArray($input['remove_image_paths'] ?? '[]');
$primaryExistingPath = cleanString($input['primary_existing_path'] ?? '', 500);
$primaryNewIndex = isset($input['primary_new_index']) && $input['primary_new_index'] !== ''
    ? (int) $input['primary_new_index']
    : -1;

$autoStmt = $con->prepare('SELECT id, img_principal FROM autos WHERE id = ? LIMIT 1');
if (!$autoStmt) {
    databaseError($con);
}
$autoStmt->bind_param('i', $autoId);
$autoStmt->execute();
$auto = $autoStmt->get_result()->fetch_assoc();
$autoStmt->close();

if (!$auto) {
    $con->close();
    errorResponse('Auto no encontrado.', 404, 'AUTO_NOT_FOUND');
}

$imageStmt = $con->prepare(
    'SELECT id, ruta_imagen, orden FROM imagenes_autos WHERE auto_id = ? ORDER BY orden, id'
);
if (!$imageStmt) {
    databaseError($con);
}
$imageStmt->bind_param('i', $autoId);
$imageStmt->execute();
$existingRows = $imageStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$imageStmt->close();

$knownById = [];
$knownPaths = [];
$currentPrimary = cleanString($auto['img_principal'] ?? '', 500);
if ($currentPrimary !== '') {
    $knownPaths[$currentPrimary] = true;
}
foreach ($existingRows as $row) {
    $id = (int) $row['id'];
    $path = cleanString($row['ruta_imagen'] ?? '', 500);
    if ($path === '') {
        continue;
    }
    $knownById[$id] = $path;
    $knownPaths[$path] = true;
}

foreach ($removeIds as $id) {
    if (!isset($knownById[$id])) {
        $con->close();
        errorResponse('Una de las imágenes que intentas retirar no pertenece al auto.', 422, 'IMAGE_NOT_OWNED');
    }
}
foreach ($removePaths as $path) {
    if (!isset($knownPaths[$path])) {
        $con->close();
        errorResponse('Una de las rutas de imagen no pertenece al auto.', 422, 'IMAGE_NOT_OWNED');
    }
}

$files = normalizeAutoUploadFiles($_FILES['imagenes'] ?? []);
$files = array_values(array_filter(
    $files,
    static fn(array $file): bool => $file['error'] !== UPLOAD_ERR_NO_FILE
));

$maxFileBytes = 8 * 1024 * 1024;
$allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
$validatedFiles = [];

foreach ($files as $file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $con->close();
        errorResponse('Una imagen no pudo cargarse. Revisa el tamaño y vuelve a intentarlo.', 422, 'IMAGE_UPLOAD_ERROR');
    }
    if ($file['size'] <= 0 || $file['size'] > $maxFileBytes) {
        $con->close();
        errorResponse('Cada imagen debe pesar máximo 8 MB.', 422, 'IMAGE_TOO_LARGE');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        $con->close();
        errorResponse('El archivo recibido no es una carga válida.', 422, 'INVALID_UPLOAD');
    }

    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
    }
    if ($mime === '' && function_exists('mime_content_type')) {
        $mime = (string) mime_content_type($file['tmp_name']);
    }
    if (!in_array($mime, $allowedMime, true)) {
        $con->close();
        errorResponse('Solo se permiten imágenes JPG, PNG o WEBP.', 422, 'IMAGE_TYPE_NOT_ALLOWED');
    }

    $dimensions = @getimagesize($file['tmp_name']);
    if (!$dimensions || (int) $dimensions[0] <= 0 || (int) $dimensions[1] <= 0) {
        $con->close();
        errorResponse('Una imagen no pudo ser validada.', 422, 'INVALID_IMAGE');
    }
    if (((int) $dimensions[0] * (int) $dimensions[1]) > 60000000) {
        $con->close();
        errorResponse('La resolución de una imagen es demasiado grande.', 422, 'IMAGE_DIMENSIONS_TOO_LARGE');
    }

    $file['mime'] = $mime;
    $validatedFiles[] = $file;
}

$removePathMap = array_fill_keys($removePaths, true);
foreach ($removeIds as $id) {
    $removePathMap[$knownById[$id]] = true;
}

$survivingPaths = [];
foreach (array_keys($knownPaths) as $path) {
    if (!isset($removePathMap[$path])) {
        $survivingPaths[$path] = true;
    }
}

if ((count($survivingPaths) + count($validatedFiles)) > 12) {
    $con->close();
    errorResponse('El auto puede tener máximo 12 imágenes.', 422, 'IMAGE_LIMIT_EXCEEDED');
}

$projectRoot = dirname(__DIR__, 3);
$relativeDirectory = "Catalogo/{$autoId}";
$absoluteDirectory = $projectRoot . '/' . $relativeDirectory;

if ($validatedFiles !== [] && !is_dir($absoluteDirectory)) {
    if (!mkdir($absoluteDirectory, 0755, true) && !is_dir($absoluteDirectory)) {
        $con->close();
        errorResponse('No fue posible crear la carpeta de imágenes del auto.', 500, 'IMAGE_DIRECTORY_ERROR');
    }
}
if ($validatedFiles !== [] && !is_writable($absoluteDirectory)) {
    $con->close();
    errorResponse(
        'La carpeta del catálogo no tiene permisos de escritura. Revisa el almacenamiento del App Service.',
        500,
        'IMAGE_DIRECTORY_NOT_WRITABLE'
    );
}

$newRoutes = [];
$newAbsoluteFiles = [];
$compressionStats = [];

try {
    foreach ($validatedFiles as $file) {
        $baseName = 'Img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5));
        $destinationBase = $absoluteDirectory . '/' . $baseName;
        $compressed = compressCatalogImage($file['tmp_name'], $file['mime'], $destinationBase);

        @chmod($compressed['path'], 0644);
        $route = '/' . $relativeDirectory . '/' . basename($compressed['path']);
        $newRoutes[] = $route;
        $newAbsoluteFiles[] = $compressed['path'];
        $compressionStats[] = [
            'archivo' => (string) $file['name'],
            'bytes_originales' => (int) $file['size'],
            'bytes_finales' => (int) $compressed['bytes'],
            'ancho' => (int) $compressed['width'],
            'alto' => (int) $compressed['height'],
        ];
    }
} catch (RuntimeException $e) {
    foreach ($newAbsoluteFiles as $createdFile) {
        @unlink($createdFile);
    }
    $con->close();
    errorResponse('No fue posible comprimir y guardar una de las imágenes.', 500, 'IMAGE_COMPRESSION_ERROR');
}

$con->begin_transaction();

try {
    if ($removeIds !== []) {
        $placeholders = implode(',', array_fill(0, count($removeIds), '?'));
        $types = 'i' . str_repeat('i', count($removeIds));
        $params = array_merge([$autoId], $removeIds);
        $deleteStmt = $con->prepare(
            "DELETE FROM imagenes_autos WHERE auto_id = ? AND id IN ({$placeholders})"
        );
        if (!$deleteStmt) {
            throw new RuntimeException($con->error);
        }
        bindDynamicParams($deleteStmt, $types, $params);
        if (!$deleteStmt->execute()) {
            throw new RuntimeException($deleteStmt->error, $deleteStmt->errno);
        }
        $deleteStmt->close();
    }

    if ($removePaths !== []) {
        $placeholders = implode(',', array_fill(0, count($removePaths), '?'));
        $types = 'i' . str_repeat('s', count($removePaths));
        $params = array_merge([$autoId], $removePaths);
        $deletePathStmt = $con->prepare(
            "DELETE FROM imagenes_autos WHERE auto_id = ? AND ruta_imagen IN ({$placeholders})"
        );
        if (!$deletePathStmt) {
            throw new RuntimeException($con->error);
        }
        bindDynamicParams($deletePathStmt, $types, $params);
        if (!$deletePathStmt->execute()) {
            throw new RuntimeException($deletePathStmt->error, $deletePathStmt->errno);
        }
        $deletePathStmt->close();
    }

    $orderResult = $con->query(
        'SELECT COALESCE(MAX(orden), 0) AS max_orden FROM imagenes_autos WHERE auto_id = ' . (int) $autoId
    );
    if (!$orderResult) {
        throw new RuntimeException($con->error);
    }
    $orderRow = $orderResult->fetch_assoc();
    $orderResult->free();
    $nextOrder = (int) ($orderRow['max_orden'] ?? 0) + 1;

    if ($newRoutes !== []) {
        $insertImage = $con->prepare(
            'INSERT INTO imagenes_autos (auto_id, ruta_imagen, orden) VALUES (?, ?, ?)'
        );
        if (!$insertImage) {
            throw new RuntimeException($con->error);
        }
        foreach ($newRoutes as $route) {
            $insertImage->bind_param('isi', $autoId, $route, $nextOrder);
            if (!$insertImage->execute()) {
                throw new RuntimeException($insertImage->error, $insertImage->errno);
            }
            $nextOrder++;
        }
        $insertImage->close();
    }

    $allRemaining = array_keys($survivingPaths);
    foreach ($newRoutes as $route) {
        if (!in_array($route, $allRemaining, true)) {
            $allRemaining[] = $route;
        }
    }

    $primaryPath = '';
    if ($primaryNewIndex >= 0 && isset($newRoutes[$primaryNewIndex])) {
        $primaryPath = $newRoutes[$primaryNewIndex];
    } elseif ($primaryExistingPath !== '' && in_array($primaryExistingPath, $allRemaining, true)) {
        $primaryPath = $primaryExistingPath;
    } elseif ($currentPrimary !== '' && in_array($currentPrimary, $allRemaining, true)) {
        $primaryPath = $currentPrimary;
    } elseif ($allRemaining !== []) {
        $primaryPath = $allRemaining[0];
    }

    $updateAuto = $con->prepare('UPDATE autos SET img_principal = ? WHERE id = ?');
    if (!$updateAuto) {
        throw new RuntimeException($con->error);
    }
    $updateAuto->bind_param('si', $primaryPath, $autoId);
    if (!$updateAuto->execute()) {
        throw new RuntimeException($updateAuto->error, $updateAuto->errno);
    }
    $updateAuto->close();

    $con->commit();

    foreach (array_keys($removePathMap) as $route) {
        if (in_array($route, $allRemaining, true)) {
            continue;
        }
        $absolutePath = localAutoImageAbsolutePath($projectRoot, $autoId, $route);
        if ($absolutePath !== null && is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    $originalBytes = array_sum(array_column($compressionStats, 'bytes_originales'));
    $finalBytes = array_sum(array_column($compressionStats, 'bytes_finales'));

    $con->close();
    okResponse([
        'auto_id' => $autoId,
        'img_principal' => $primaryPath,
        'imagenes_agregadas' => $newRoutes,
        'total_imagenes' => count($allRemaining),
        'compresion' => [
            'bytes_originales' => $originalBytes,
            'bytes_finales' => $finalBytes,
            'ahorro_bytes' => max(0, $originalBytes - $finalBytes),
            'max_ancho' => 1920,
            'max_alto' => 1440,
            'calidad' => 82,
        ],
    ], 'Imágenes comprimidas y actualizadas correctamente.');
} catch (Throwable $e) {
    $con->rollback();
    foreach ($newAbsoluteFiles as $createdFile) {
        @unlink($createdFile);
    }
    databaseError($con, $e);
}
