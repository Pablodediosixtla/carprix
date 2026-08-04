# CARPRIX — módulo operativo comercial

## Vistas agregadas

- `operativo/login.php`
- `operativo/home.php`
- `operativo/catalogo.php`
- `operativo/requerimientos.php`
- `operativo/autorizaciones.php`
- `operativo/jerarquia.php`

## Servicios agregados

### Dashboard y catálogo

- `op_dashboard.php`
- `op_c_catalogo.php`
- `op_c_auto.php`
- `op_i_auto.php`
- `op_u_auto.php`

### Requerimientos y autorizaciones

- `op_c_requerimientos.php`
- `op_c_requerimiento.php`
- `op_i_requerimiento.php`
- `op_i_cambio_estatus.php`
- `op_c_autorizaciones.php`
- `op_u_autorizacion.php`

### Jerarquía

- `op_c_jerarquia.php`
- `op_u_jerarquia.php`

## Flujo

1. Un trabajador con rol `VENTAS` registra un requerimiento en `Solicitado`.
2. Solicita el cambio a `Apartado`.
3. El supervisor configurado aprueba o rechaza.
4. Posteriormente se solicita el cambio a `Vendido` y vuelve a requerir autorización.
5. Cada evento queda en el historial del requerimiento.

## Lógica pública

No se modificaron las vistas públicas ni los servicios públicos del catálogo.
