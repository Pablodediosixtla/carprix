# CARPRIX — módulo operativo comercial

## Vistas operativas

- `operativo/login.php`
- `operativo/home.php`
- `operativo/personas.php`
- `operativo/catalogo.php`
- `operativo/requerimientos.php`
- `operativo/autorizaciones.php`
- `operativo/jerarquia.php`

## Personas y niveles

La nueva vista `operativo/personas.php` aplica estas reglas:

- `SUPER_ADMIN`: puede crear vendedores, supervisores y gerentes de operaciones.
- `ADMIN_OPERATIVO`: puede crear vendedores y supervisores. Al vendedor le asigna un supervisor activo; el supervisor nuevo queda por defecto dentro de su línea.
- `AUTORIZADOR`: solo puede crear vendedores y el sistema los asigna automáticamente como subordinados directos del supervisor que los crea.
- `VENTAS`: no puede crear personas.

Servicios:

- `op_c_personas.php`: consulta el equipo visible, niveles permitidos y supervisores disponibles.
- `op_i_persona.php`: crea el usuario, asigna sus roles y registra la relación jerárquica dentro de una transacción.

## Gestión de imágenes

La pantalla de catálogo permite:

- consultar las imágenes existentes;
- retirar imágenes existentes;
- agregar imágenes JPG, PNG o WEBP;
- seleccionar la imagen principal;
- cargar imágenes al crear un auto o al editarlo;
- conservar hasta 12 imágenes por auto, con máximo 8 MB por archivo.

Servicio:

- `op_upload_auto_images.php`

Las imágenes se guardan en `Catalogo/{auto_id}/`, se registran en `imagenes_autos` y la portada se actualiza en `autos.img_principal`.

## Acceso público

Todos los botones **Iniciar Sesión** de las vistas públicas apuntan ahora a:

- `/operativo/login.php`

## Flujo comercial

1. Un trabajador con rol `VENTAS` registra un requerimiento en `Solicitado`.
2. Solicita el cambio a `Apartado`.
3. El supervisor configurado aprueba o rechaza.
4. Posteriormente se solicita el cambio a `Vendido` y vuelve a requerir autorización.
5. Cada evento queda en el historial del requerimiento.
