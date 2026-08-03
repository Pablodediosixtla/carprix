# CARPRIX - Actualización visual y responsive

## 1. Identidad oficial

Se extrajeron del PDF oficial las variantes de marca y se integraron sin alterar las consultas ni las rutas de imágenes del catálogo.

Archivos principales:

- `img/brand/logo-carprix-dark.svg`: logotipo completo blanco/verde para fondos oscuros.
- `img/brand/logo-carprix-light.svg`: logotipo completo negro/verde para fondos claros.
- `img/brand/logo-carprix-wordmark-dark.svg`: logotipo sin leyenda para encabezado móvil oscuro.
- `img/brand/logo-carprix-wordmark-light.svg`: logotipo sin leyenda para encabezado móvil claro.
- `img/brand/carprix-icon.svg`: isotipo oficial de la letra A con automóvil.
- `img/favicon.ico`: favicon oficial multirresolución.
- `img/favicon-180.png`: icono para dispositivos Apple.
- `docs/brand/LOGO CarPrix Editable.pdf`: archivo fuente proporcionado.

El logotipo completo se usa en escritorio y footer. En móvil, el encabezado usa únicamente el nombre CARPRIX, sin la leyenda, para mantener una navegación limpia.

## 2. Tema oscuro y claro

La lógica está centralizada en `js/theme.js`.

- Tema oscuro predeterminado.
- El botón sol/luna funciona en todas las vistas.
- La selección se guarda en `localStorage` con la clave `carprix-theme`.
- El tema claro usa fondos blancos, texto negro y el verde corporativo `#39B54A`.
- Se actualiza el color del navegador mediante `meta[name="theme-color"]`.
- Se agregaron versiones a los recursos CSS/JS para evitar que el navegador conserve archivos anteriores en caché.

## 3. Detalle móvil

En `views/detalle.php`:

- El encabezado principal de navegación muestra únicamente el logotipo tipográfico en móvil.
- El nombre, precio, mensualidad e ID se presentan en una tarjeta independiente, arriba de la galería.
- Se eliminaron los textos provisionales `Cargando...` de la tarjeta del vehículo.
- La fotografía principal usa `object-fit: contain` para no cortar defensas, espejos, techo o llantas.
- La galería móvil usa una superficie cuadrada estable y miniaturas horizontales.
- Al tocar la imagen se abre un visor de pantalla completa con navegación anterior/siguiente.

## 4. Archivos principales modificados

- `index.php`
- `views/catalogo.php`
- `views/contacto.php`
- `views/detalle.php`
- `views/nosotros.php`
- `views/ubicaciones.php`
- `views/vende.php`
- `css/styles.css`
- `css/catalogo.css`
- `css/contacto.css`
- `css/detalle.css`
- `css/nosotros.css`
- `css/ubicaciones.css`
- `css/vende.css`
- `js/theme.js`
- `js/detalle.js`

## 5. Lógica preservada

No se modificaron:

- la conexión a base de datos;
- los endpoints PHP;
- el cuerpo de consulta enviado a `get_autos.php`;
- la forma de recuperar `img_principal` e `imagenes`;
- las rutas del catálogo;
- la lógica de carga del inventario.
