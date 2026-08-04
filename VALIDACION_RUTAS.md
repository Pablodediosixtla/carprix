# Validación de rutas CARPRIX

## Correcciones aplicadas

1. Todos los servicios de `db/web/operativo/` ahora cargan correctamente:
   `db/web/auth/auth_bootstrap.php` mediante `__DIR__ . '/../auth/auth_bootstrap.php'`.
2. `db/web/auth/auth_bootstrap.php` ahora resuelve la conexión local en:
   `db/conn/conn_db.php` mediante `dirname(__DIR__, 2) . '/conn/conn_db.php'`.
3. Los endpoints públicos `get_autos.php`, `insert_auto.php`, `insert_contacto.php` y
   `update_auto.php` usan `__DIR__` para el fallback local hacia `db/conn/conn_db.php`.
4. Se corrigió el comentario de ejecución de `create_first_admin.php` para reflejar su ruta real.

## Rutas validadas

- Vistas a CSS, JavaScript, logos y favicon.
- JavaScript a endpoints PHP públicos.
- Servicios operativos a archivos comunes de autenticación.
- Bootstrap de autenticación a conexión de base de datos.
- Endpoint de prospectos a conexión de base de datos.
- Flujo de despliegue GitHub Actions a la raíz del App Service.

## Archivos excluidos del ZIP corregido

- `.git/`
- `.env`
- `.DS_Store`

Estos archivos son locales y no deben formar parte del paquete de despliegue.

## Observación de seguridad

`db/conn/conn_db.php` conserva la lógica original, como fue solicitado. No se modificaron
credenciales ni configuración SSL. Se recomienda migrar las credenciales a variables de entorno
en una fase posterior.
