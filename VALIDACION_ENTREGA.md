# Validación de entrega

## Ajustes incluidos

- Login público dirigido a `operativo/login.php`.
- Vista operativa `personas.php`.
- Alta de personas controlada por rol y jerarquía.
- Gestión de imágenes actuales y nuevas en el catálogo.
- Normalización de rutas públicas de imágenes.
- Corrección permanente del DDL de jerarquía mediante triggers.

## Validaciones estáticas

- 64 archivos PHP validados con `php -l`.
- Todos los archivos JavaScript validados con `node --check`.
- Referencias locales de vistas, CSS, JavaScript e imágenes revisadas.
- Endpoints mencionados por los JavaScript operativos confirmados en el proyecto.
- `.env`, `.git` y `.DS_Store` excluidos del ZIP de entrega.

## Base de datos

Esta actualización no necesita tablas ni columnas adicionales. Utiliza las tablas ya instaladas.
