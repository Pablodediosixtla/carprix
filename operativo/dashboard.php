<?php
require_once __DIR__ . '/_layout.php';
operativoPageStart('Dashboard', 'dashboard');
?>
<section class="op-page-head op-analytics-head">
    <div>
        <span class="op-kicker">ANALÍTICA OPERATIVA</span>
        <h2>Indicadores comerciales, metas y reconocimientos</h2>
        <p>Analiza equipos completos, personas y períodos específicos dentro de tu alcance jerárquico.</p>
    </div>
    <div class="op-analytics-filters">
        <label class="op-field compact"><span>Año</span><select id="analytics-year"></select></label>
        <div class="op-field compact op-analytics-month-field">
            <span>Mes</span>
            <details class="op-multi-select" id="analytics-month-filter">
                <summary id="analytics-month-summary">Todos los meses</summary>
                <div class="op-multi-select-menu" id="analytics-month-menu">
                    <label class="op-multi-select-option all"><input type="checkbox" id="analytics-month-all" checked><span>Todos los meses</span></label>
                    <div class="op-multi-select-divider"></div><div id="analytics-month-options"></div>
                    <button class="op-primary-button compact" id="analytics-month-apply" type="button">Aplicar meses</button>
                </div>
            </details>
        </div>
        <label class="op-field compact op-analytics-team-filter"><span>Equipo</span><select id="analytics-team"><option value="0">Cargando equipos...</option></select></label>
        <label class="op-field compact op-analytics-person-filter"><span>Persona</span><select id="analytics-person"><option value="0">Todo el equipo</option></select></label>
        <button class="op-secondary-button" id="analytics-refresh" type="button" title="Actualizar" aria-label="Actualizar dashboard"><i class="fa-solid fa-rotate"></i></button>
    </div>
</section>

<section class="op-metric-grid op-analytics-metrics" id="analytics-metrics">
    <article class="op-metric-card"><div class="op-metric-icon yellow"><i class="fa-solid fa-file-circle-plus"></i></div><div><span>Solicitudes</span><strong id="metric-requests">—</strong></div></article>
    <article class="op-metric-card"><div class="op-metric-icon purple"><i class="fa-solid fa-handshake"></i></div><div><span>Apartados</span><strong id="metric-reserved">—</strong></div></article>
    <article class="op-metric-card"><div class="op-metric-icon purple"><i class="fa-solid fa-bullseye"></i></div><div><span>Meta apartados</span><strong id="metric-reserve-goal">—</strong></div></article>
    <article class="op-metric-card"><div class="op-metric-icon"><i class="fa-solid fa-tag"></i></div><div><span>Vendidos</span><strong id="metric-sold">—</strong></div></article>
    <article class="op-metric-card"><div class="op-metric-icon"><i class="fa-solid fa-bullseye"></i></div><div><span>Meta venta anual</span><strong id="metric-sale-goal">—</strong></div></article>
    <article class="op-metric-card"><div class="op-metric-icon green"><i class="fa-solid fa-award"></i></div><div><span>Reconocimientos</span><strong id="metric-rewards">—</strong></div></article>
    <article class="op-metric-card"><div class="op-metric-icon blue"><i class="fa-solid fa-star"></i></div><div><span>Puntos netos</span><strong id="metric-points">—</strong></div></article>
    <article class="op-metric-card"><div class="op-metric-icon red"><i class="fa-solid fa-chart-line"></i></div><div><span>Conversión a venta</span><strong id="metric-conversion">—</strong></div></article>
</section>

<section class="op-analytics-goal-grid">
    <article class="op-panel op-analytics-goal-card">
        <div class="op-panel-header"><div><small>META DE RESERVAS</small><h3>Cumplimiento del período</h3></div><strong id="goal-reserve-progress-label">0%</strong></div>
        <div class="op-goal-progress"><span id="goal-reserve-progress"></span></div>
        <p><strong id="goal-reserve-actual">0</strong> apartados de <strong id="goal-reserve-target">0</strong> de meta para los meses seleccionados.</p>
    </article>
    <article class="op-panel op-analytics-goal-card">
        <div class="op-panel-header"><div><small>META DE VENTAS</small><h3>Cumplimiento anual</h3></div><strong id="goal-sale-progress-label">0%</strong></div>
        <div class="op-goal-progress"><span id="goal-sale-progress"></span></div>
        <p><strong id="goal-sale-actual">0</strong> vendidos en el año de <strong id="goal-sale-target">0</strong> de meta anual.</p>
    </article>
</section>

<section class="op-analytics-grid">
    <article class="op-panel">
        <div class="op-panel-header"><div><small>ACTIVIDAD DEL PERÍODO</small><h3>Actividad comercial por mes</h3></div><span class="op-muted" id="analytics-scope-label">—</span></div>
        <div class="op-chart-legend" aria-hidden="true"><span><i class="requests"></i>Solicitudes</span><span><i class="reserved"></i>Apartados</span><span><i class="sold"></i>Vendidos</span><span><i class="reserve-goal-line"></i>Meta de reservas</span></div>
        <p class="op-analytics-chart-help"><i class="fa-solid fa-arrow-pointer"></i> Haz clic en un mes para filtrar el dashboard y consultar su detalle.</p>
        <div class="op-analytics-chart" id="analytics-chart"><div class="op-loading">Cargando indicadores...</div></div>
    </article>
    <article class="op-panel">
        <div class="op-panel-header"><div><small>RECONOCIMIENTOS</small><h3>Balance de recompensas</h3></div></div>
        <div class="op-reward-balance-list">
            <div><span>Puntos obtenidos</span><strong class="positive" id="metric-points-positive">—</strong></div>
            <div><span>Puntos descontados</span><strong class="negative" id="metric-points-negative">—</strong></div>
            <div><span>Movimientos</span><strong id="metric-reward-movements">—</strong></div>
            <div><span>Requerimientos rechazados</span><strong id="metric-rejected">—</strong></div>
        </div>
    </article>
</section>

<section class="op-panel op-analytics-detail-panel" id="analytics-detail-panel" hidden>
    <div class="op-panel-header op-analytics-detail-head">
        <div><small>DETALLE DEL PERÍODO</small><h3 id="analytics-detail-title">Detalle</h3></div>
        <div class="op-analytics-detail-actions"><span class="op-muted" id="analytics-detail-count">—</span><button class="op-secondary-button compact" id="analytics-clear-months" type="button"><i class="fa-solid fa-calendar-days"></i> Ver todos los meses</button></div>
    </div>
    <div class="op-table-wrap">
        <table class="op-table op-analytics-detail-table" data-sort-table="detail">
            <thead><tr>
                <th><button class="op-sort-button" data-sort-key="movimiento">Movimiento <i class="fa-solid fa-sort"></i></button></th>
                <th><button class="op-sort-button" data-sort-key="fecha_evento">Fecha <i class="fa-solid fa-sort"></i></button></th>
                <th><button class="op-sort-button" data-sort-key="folio">Folio / Auto <i class="fa-solid fa-sort"></i></button></th>
                <th><button class="op-sort-button" data-sort-key="cliente_nombre">Cliente <i class="fa-solid fa-sort"></i></button></th>
                <th><button class="op-sort-button" data-sort-key="responsable_nombre">Responsable <i class="fa-solid fa-sort"></i></button></th>
                <th><button class="op-sort-button" data-sort-key="monto_propuesto">Monto <i class="fa-solid fa-sort"></i></button></th>
                <th><button class="op-sort-button" data-sort-key="estatus">Estatus actual <i class="fa-solid fa-sort"></i></button></th>
            </tr></thead>
            <tbody id="analytics-detail-body"><tr><td colspan="7">Selecciona uno o varios meses para consultar el detalle.</td></tr></tbody>
        </table>
    </div>
</section>

<section class="op-panel">
    <div class="op-panel-header"><div><small>DESEMPEÑO POR PERSONA</small><h3>Ranking del equipo</h3></div><span class="op-muted" id="analytics-ranking-count">—</span></div>
    <div class="op-table-wrap">
        <table class="op-table op-analytics-ranking-table" data-sort-table="ranking">
            <thead><tr>
                <th><button class="op-sort-button" data-sort-key="nombre_completo">Persona <i class="fa-solid fa-sort"></i></button></th>
                <th><button class="op-sort-button" data-sort-key="solicitudes">Solicitudes <i class="fa-solid fa-sort"></i></button></th>
                <th><button class="op-sort-button" data-sort-key="apartados">Apartados <i class="fa-solid fa-sort"></i></button></th>
                <th><button class="op-sort-button" data-sort-key="meta_apartados">Meta apartados <i class="fa-solid fa-sort"></i></button></th>
                <th><button class="op-sort-button" data-sort-key="cumplimiento_apartados">% reserva <i class="fa-solid fa-sort"></i></button></th>
                <th><button class="op-sort-button" data-sort-key="vendidos">Vendidos período <i class="fa-solid fa-sort"></i></button></th>
                <th><button class="op-sort-button" data-sort-key="vendidos_anio">Vendidos año <i class="fa-solid fa-sort"></i></button></th>
                <th><button class="op-sort-button" data-sort-key="meta_ventas">Meta venta <i class="fa-solid fa-sort"></i></button></th>
                <th><button class="op-sort-button" data-sort-key="cumplimiento_ventas">% venta <i class="fa-solid fa-sort"></i></button></th>
                <th><button class="op-sort-button" data-sort-key="puntos">Puntos <i class="fa-solid fa-sort"></i></button></th>
            </tr></thead>
            <tbody id="analytics-ranking"><tr><td colspan="10">Cargando equipo...</td></tr></tbody>
        </table>
    </div>
</section>
<?php operativoPageEnd(['operativo-dashboard-analytics.js']); ?>
