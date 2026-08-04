# CARPRIX — instalación del módulo operativo comercial

1. Ejecutar `sql/02_operativo_comercial.sql` en la misma base donde existen `autos`, `operativo_usuario`, `operativo_rol` y `operativo_usuario_rol`.
2. Asignar el rol `VENTAS` a los trabajadores que registrarán requerimientos.
3. Asignar `AUTORIZADOR` a los supervisores, o utilizar `ADMIN_OPERATIVO` / `SUPER_ADMIN`.
4. Configurar las relaciones trabajador → supervisor desde `operativo/jerarquia.php`.
5. Acceder por `operativo/login.php`.

## Flujo de autorización

- Un requerimiento inicia en `Solicitado`.
- El cambio a `Apartado` genera una solicitud pendiente.
- El cambio de `Apartado` a `Vendido` genera otra solicitud pendiente.
- El supervisor configurado debe aprobar o rechazar cada solicitud.
- `SUPER_ADMIN` puede intervenir en cualquier autorización como mecanismo de contingencia.
- El catálogo público y sus consultas no fueron modificados.
