# NextEra — Análisis de Use Cases vs. Arquitectura del Proyecto

> **Referencia:** Imagen *"Arquitectura del proyecto NEXTERA | Excel + Monday + DB2"* y minutas de reunión disponibles en el repositorio.
>
> **Leyenda:**
> - ✅ **Se cumple** — la arquitectura cubre el caso de uso.
> - ⚠️ **Parcialmente cubierto** — hay evidencia en la arquitectura pero existe una brecha o supuesto no confirmado.
> - ❌ **No se cumple / No se evidencia** — el diagrama no muestra ningún mecanismo que atienda el caso de uso.

---

## 1. Record Management

| # | Use Case | Estado | Observación |
|---|----------|--------|-------------|
| 1.1 | **New Record Creation** | ✅ Se cumple | `SYNC_DIFFERENCES_TO_MONDAY.py` (paso 4) detecta los Talent Request IDs que existen en Excel pero **no** en Monday (`FACT_NEXTERA_DIFFERENCES` con indicador `EXCEL_NOT_IN_MONDAY`) y los inserta en el grupo *"Diferencias vs Excel"* con `SYNC_STATUS = SENT`. El flujo completo Excel → STG → Conciliation → Sync cubre la creación de nuevos registros de forma automatizada. |
| 1.2 | **Existing Record Update** | ❌ No se cumple | El diagrama y la nota importante al pie del mismo indican explícitamente que **la sincronización actual es INSERT ONLY**. Si el Talent Request ya existe en Monday, el proceso lo omite (`SYNC_STATUS = SKIPPED`) y **no actualiza** el item. Las actualizaciones de campos son responsabilidad manual del equipo. Este caso de uso no está cubierto por la arquitectura actual. |
| 1.3 | **Record No Longer in Source Report** | ⚠️ Parcialmente cubierto | La tabla `FACT_NEXTERA_CONCILIATION` registra los tres estados de conciliación, incluyendo registros que *"solo existen en Monday"*. Sin embargo, el diagrama **no muestra ninguna acción automatizada** (borrado, archivado o cambio de estatus) cuando un registro desaparece del Excel. La minuta menciona la idea de mover registros a un grupo "Cancelado" vía automatización nativa de Monday, pero esto no está implementado en el diagrama actual. |

---

## 2. Identifier Management

| # | Use Case | Estado | Observación |
|---|----------|--------|-------------|
| 2.1 | **Talent Request Identification** | ✅ Se cumple | El `TALENT_REQUEST_ID` es el identificador normalizado central del proceso. `ETL_CONCILIATION.py` (paso 3) realiza el cruce entre las fuentes usando este ID. `SYNC_DIFFERENCES_TO_MONDAY.py` consulta si el Talent Request ya existe en Monday antes de insertar. La minuta confirma que el Talent Request ID sustituyó al Open Seat ID como llave primaria. |
| 2.2 | **Open Seat Identification** | ⚠️ Parcialmente cubierto | El workbook Excel incluye la hoja *"Raw ProM"* (reporte de Open Seats de PROM), y la minuta establece que el Open Seat es una llave secundaria en la cadena `Talent Request ID → Deal Support Request → Open Seat → Talent ID`. No obstante, el diagrama no muestra un flujo específico de identificación o enriquecimiento desde Open Seat ID hacia los registros de Monday; su uso queda como complementario sin lógica explícita de mapeo en el diagrama. |

---

## 3. Field Management

| # | Use Case | Estado | Observación |
|---|----------|--------|-------------|
| 3.1 | **Manual Field Update** | ✅ Se cumple | El diseño INSERT ONLY del proceso está deliberadamente concebido para **preservar las ediciones manuales** del equipo en Monday. Al detectar un Talent Request ya existente, el proceso marca `SYNC_STATUS = SKIPPED` y no sobrescribe ningún campo. La minuta lo confirma explícitamente: "no actualiza registros ya existentes para preservar ediciones manuales del equipo." |
| 3.2 | **Required Field Handling** | ⚠️ Parcialmente cubierto | Al insertar un nuevo item, el proceso asigna valores por defecto (`Demand Status = Draft`, `Excel Requestor = Pending`). Sin embargo, la minuta indica que los **valores correctos de estos campos aún estaban pendientes de definir** (acción asignada a Adrián + Brenda). No hay en el diagrama un mecanismo de validación o bloqueo para campos requeridos faltantes al momento de la inserción. |
| 3.3 | **Locked Field Handling** | ❌ No se cumple | El diagrama no muestra ningún mecanismo para campos bloqueados (campos de solo lectura, campos que no deben ser editados por el usuario una vez establecidos, ni validaciones de escritura). No hay referencia a este concepto en la arquitectura ni en las minutas disponibles. |

---

## 4. Exceptions & Business Scenarios

| # | Use Case | Estado | Observación |
|---|----------|--------|-------------|
| 4.1 | **Invalid / Missing Data** | ⚠️ Parcialmente cubierto | `ETL_MXDC_DEMAND.py` realiza transformaciones y conserva campos manuales al procesar el Excel, lo que implica cierto grado de limpieza. No obstante, el diagrama **no describe explícitamente** un manejo de errores para datos inválidos o faltantes (e.g., registros sin Talent Request ID, campos obligatorios vacíos). No hay un flujo de excepción o tabla de errores visible en el diagrama. |
| 4.2 | **Duplicate Record / Identifier** | ✅ Se cumple | `SYNC_DIFFERENCES_TO_MONDAY.py` incluye el paso *"Revisa duplicados por Talent Request"* antes de insertar. Si el Talent Request ya existe en Monday, el item se omite (`SYNC_STATUS = SKIPPED`) y no se crea un duplicado. Esta lógica anti-duplicados está explícitamente representada en el diagrama. |
| 4.3 | **DSR Creation** | ❌ No se cumple | El *Deal Support Request (DSR)* es identificado en la minuta como parte de la cadena de llaves del sistema (`Talent Request ID → DSR → Open Seat → Talent ID`). Sin embargo, **no existe ningún proceso o step en el diagrama** que maneje la creación, registro o sincronización de un DSR. Queda fuera del scope actual de la arquitectura. |
| 4.4 | **Administrative Open Seat Identification & Exclusion** | ❌ No se cumple | El diagrama no contempla ningún mecanismo para identificar y excluir Open Seats de tipo administrativo (posiciones que no deben ser procesadas o visibles en el board). No hay filtros, flags o reglas de exclusión documentadas en el flujo para este escenario. |

---

## 5. Access & Visibility

| # | Use Case | Estado | Observación |
|---|----------|--------|-------------|
| 5.1 | **Demand Visibility by Country / Global Demand** | ⚠️ Parcialmente cubierto | La arquitectura apunta al board *"Global Demand – Man Planner"* en Monday.com como destino de sincronización, y la minuta confirma el objetivo de escalar la visibilidad a múltiples geografías (India, Filipinas, EE. UU., Canadá, Costa Rica, México y posiblemente Brasil). Sin embargo, el diagrama **no muestra segmentación por país** dentro del board ni un mecanismo de filtrado o agrupación por geografía. La visibilidad global existe a nivel de board, pero la visibilidad diferenciada por país no está modelada en la arquitectura actual. |

---

## Resumen Ejecutivo

| Estado | Cantidad | Use Cases |
|--------|----------|-----------|
| ✅ Se cumple | 4 | New Record Creation, Talent Request Identification, Manual Field Update, Duplicate Record / Identifier |
| ⚠️ Parcialmente cubierto | 5 | Record No Longer in Source Report, Open Seat Identification, Required Field Handling, Invalid / Missing Data, Demand Visibility by Country / Global Demand |
| ❌ No se cumple | 4 | Existing Record Update, Locked Field Handling, DSR Creation, Administrative Open Seat Identification & Exclusion |

> **Nota:** Los casos marcados como ❌ representan brechas funcionales que deberán ser abordadas en próximas iteraciones del diseño o documentadas como fuera de alcance si así se decide.
