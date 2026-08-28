<?php
require_once __DIR__ . '/_layout.php';
operativoPageStart('Dashboard', 'dashboard');
?>
<section class="op-page-head op-analytics-head">
    <div>
        <span class="op-kicker">ANALÍTICA OPERATIVA</span>
        <h2>Indicadores comerciales y reconocimientos</h2>
        <p>Consulta ventas, apartados y recompensas de tu equipo completo o de una persona dentro de tu alcance.</p>
    </div>
    <div class="op-analytics-filters">
        <label class="op-field compact"><span>Año</span><select id="analytics-year"></select></label>
        <label class="op-field compact op-analytics-person-filter"><span>Persona</span><select id="analytics-person"><option value="0">Mi grupo completo</option></select></label>
        <button class="op-secondary-button" id="analytics-refresh" type="button" title="Actualizar"><i class="fa-solid fa-rotate"></i></button>
    </div>
</section>

<section class="op-metric-grid op-analytics-metrics" id="analytics-metrics">
    <article class="op-metric-card"><div class="op-metric-icon yellow"><i class="fa-solid fa-file-circle-plus"></i></div><div><span>Solicitudes</span><strong id="metric-requests">—</strong></div></article>
    <article class="op-metric-card"><div class="op-metric-icon purple"><i class="fa-solid fa-handshake"></i></div><div><span>Apartados</span><strong id="metric-reserved">—</strong></div></article>
    <article class="op-metric-card"><div class="op-metric-icon"><i class="fa-solid fa-tag"></i></div><div><span>Vendidos</span><strong id="metric-sold">—</strong></div></article>
    <article class="op-metric-card"><div class="op-metric-icon green"><i class="fa-solid fa-award"></i></div><div><span>Reconocimientos</span><strong id="metric-rewards">—</strong></div></article>
    <article class="op-metric-card"><div class="op-metric-icon blue"><i class="fa-solid fa-star"></i></div><div><span>Puntos netos</span><strong id="metric-points">—</strong></div></article>
    <article class="op-metric-card"><div class="op-metric-icon red"><i class="fa-solid fa-chart-line"></i></div><div><span>Conversión a venta</span><strong id="metric-conversion">—</strong></div></article>
</section>

<section class="op-analytics-grid">
    <article class="op-panel">
        <div class="op-panel-header">
            <div><small>TENDENCIA DEL AÑO</small><h3>Actividad comercial por mes</h3></div>
            <span class="op-muted" id="analytics-scope-label">—</span>
        </div>
        <div class="op-chart-legend" aria-hidden="true">
            <span><i class="requests"></i>Solicitudes</span>
            <span><i class="reserved"></i>Apartados</span>
            <span><i class="sold"></i>Vendidos</span>
        </div>
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

<section class="op-panel">
    <div class="op-panel-header">
        <div><small>DESEMPEÑO POR PERSONA</small><h3>Ranking del equipo</h3></div>
        <span class="op-muted" id="analytics-ranking-count">—</span>
    </div>
    <div class="op-table-wrap">
        <table class="op-table">
            <thead><tr><th>Persona</th><th>Solicitudes</th><th>Apartados</th><th>Vendidos</th><th>Conversión</th><th>Puntos</th></tr></thead>
            <tbody id="analytics-ranking"><tr><td colspan="6">Cargando equipo...</td></tr></tbody>
        </table>
    </div>
</section>

<?php operativoPageEnd(['operativo-dashboard-analytics.js']); ?>
