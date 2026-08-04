# CARPRIX — instalación del módulo operativo

## Base de datos

Los cambios de personas e imágenes no requieren tablas adicionales. Utilizan:

- `operativo_usuario`
- `operativo_rol`
- `operativo_usuario_rol`
- `operativo_usuario_jerarquia`
- `autos`
- `imagenes_autos`

El archivo `sql/02_operativo_comercial.sql` ya contiene la corrección de jerarquía mediante triggers y debe conservarse como referencia para instalaciones nuevas.

## Acceso

- Login: `operativo/login.php`
- Home: `operativo/home.php`
- Personas: `operativo/personas.php`
- Catálogo: `operativo/catalogo.php`

## Alta de personas

1. El gerente crea primero a los supervisores.
2. El gerente crea vendedores y selecciona un supervisor activo.
3. Un supervisor también puede crear vendedores, pero siempre quedan asignados a su propia línea jerárquica.
4. La contraseña creada es temporal y debe cambiarse en el primer inicio de sesión.

## Imágenes

- Formatos: JPG, PNG y WEBP.
- Tamaño máximo: 8 MB por imagen.
- Máximo: 12 imágenes por auto.
- Carpeta: `Catalogo/{auto_id}/`.
- El proceso de PHP necesita permisos de escritura sobre `Catalogo`.
- `.user.ini` configura los límites de carga requeridos.

En Azure, valida desde SSH:

```bash
mkdir -p /home/site/wwwroot/Catalogo
chmod -R 755 /home/site/wwwroot/Catalogo
php -i | grep -E "upload_max_filesize|post_max_size|max_file_uploads"
```

Si `WEBSITE_RUN_FROM_PACKAGE=1`, `wwwroot` puede quedar en modo de solo lectura. En ese caso debe desactivarse ese modo o migrarse la galería a almacenamiento externo.
