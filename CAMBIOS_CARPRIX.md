# Actualización visual CARPRIX

## Archivos agregados

- `img/favicon.ico`: favicon utilizado por todas las vistas.
- `img/favicon-source.png`: fuente PNG editable del favicon de ejemplo.
- `img/favicon-180.png`: icono para dispositivos Apple.
- `js/theme.js`: cambio persistente entre modo oscuro y claro.

## Cambios principales

1. Todas las vistas cargan el favicon.
2. Todas las vistas incluyen un botón de tema oscuro/claro en el encabezado.
3. La preferencia se conserva en `localStorage` y también considera el tema del sistema en la primera visita.
4. En `views/detalle.php`, la ficha de nombre, mensualidad/precio e ID aparece arriba de la imagen en móvil.
5. En móvil, la imagen principal usa `object-fit: contain` dentro de un escenario 4:3 para mostrar el vehículo completo sin recortarlo.
6. Las miniaturas móviles se muestran en una fila horizontal desplazable.

## Ruta del icono editable

Reemplaza `img/favicon.ico` con tu versión final. Puedes editar primero `img/favicon-source.png` y exportarla nuevamente como `.ico`.

## Lógica no modificada

No se modificaron los servicios PHP de base de datos, las consultas, las rutas de imágenes ni la estructura del catálogo.
