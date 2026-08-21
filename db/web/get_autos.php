<?php
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['http://localhost:3000', 'https://carprix.com.mx', 'https://www.carprix.com.mx'];

if (in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Vary: Origin");
}
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept");
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

$path = realpath("/home/site/wwwroot/db/conn/conn_db.php");
if ($path && file_exists($path)) { include $path; } else { include __DIR__ . "/../conn/conn_db.php"; }


function normalizarRutaImagenPublica($ruta) {
    $ruta = trim((string)$ruta);
    if ($ruta === '' || preg_match('#^(https?:)?//#i', $ruta) || str_starts_with($ruta, 'data:')) {
        return $ruta;
    }
    return '/' . ltrim(str_replace('\\', '/', $ruta), './');
}

$in = json_decode(file_get_contents("php://input"), true) ?? [];
$id = isset($in['id']) ? (int)$in['id'] : null;

$con = conectar();

if ($id) {
    // Consulta para traer los datos del auto
    $sql = "SELECT * FROM autos WHERE id = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $autos = $result->fetch_all(MYSQLI_ASSOC);
        
        // Si el auto existe, vamos por sus imágenes adicionales
        if (count($autos) > 0) {
            $sql_img = "SELECT ruta_imagen FROM imagenes_autos WHERE auto_id = ? ORDER BY orden ASC";
            $stmt_img = $con->prepare($sql_img);
            $stmt_img->bind_param("i", $id);
            $stmt_img->execute();
            $res_img = $stmt_img->get_result();
            
            $imagenes_extra = [];
            while ($row = $res_img->fetch_assoc()) {
                $imagenes_extra[] = normalizarRutaImagenPublica($row['ruta_imagen']);
            }
            
            // Añadimos el array de imágenes al objeto del auto
            $autos[0]['imagenes'] = $imagenes_extra;
            $stmt_img->close();
        }
        
        foreach ($autos as &$auto) {
            $auto['img_principal'] = normalizarRutaImagenPublica($auto['img_principal'] ?? '');
        }
        unset($auto);
        echo json_encode(["ok" => true, "data" => $autos]);
    } else {
        echo json_encode(["ok" => false, "error" => $con->error]);
    }
    $stmt->close();

} else {
    // Consulta general para el catálogo público.
    // Disponibles primero y en orden aleatorio en cada carga; Apartados/Vendidos al final.
    $sql = "SELECT *
            FROM autos
            WHERE estatus <> 'Oculto'
            ORDER BY
                CASE estatus
                    WHEN 'Disponible' THEN 0
                    WHEN 'Apartado' THEN 1
                    WHEN 'Vendido' THEN 2
                    ELSE 3
                END ASC,
                CASE WHEN estatus = 'Disponible' THEN RAND() ELSE NULL END,
                fecha_carga DESC,
                id DESC";
    $stmt = $con->prepare($sql);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $autos = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($autos as &$auto) {
            $auto['img_principal'] = normalizarRutaImagenPublica($auto['img_principal'] ?? '');
        }
        unset($auto);
        echo json_encode(["ok" => true, "data" => $autos]);
    } else {
        echo json_encode(["ok" => false, "error" => $con->error]);
    }
    $stmt->close();
}

$con->close();
?>