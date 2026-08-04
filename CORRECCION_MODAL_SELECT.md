# Corrección del cierre inesperado de modales

Se corrigió el manejador global de modales en `js/operativo-common.js`.

La versión anterior detectaba clics fuera del modal mediante coordenadas. Los menús nativos de los elementos `<select>` pueden reportar el clic de una opción fuera del rectángulo del `<dialog>`, provocando que el modal se cerrara al seleccionar un valor.

Ahora el modal solo se cierra cuando el clic ocurre directamente sobre el backdrop (`event.target === dialog`).

También se incrementó la versión de caché de los recursos operativos en `operativo/_layout.php` para obligar al navegador a descargar el JavaScript corregido.
