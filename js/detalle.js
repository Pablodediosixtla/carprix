document.addEventListener('DOMContentLoaded', () => {
    // Galería
    const mainView = document.getElementById('main-view');
    const thumbnails = document.querySelectorAll('.thumb-item');

    thumbnails.forEach(thumb => {
        thumb.addEventListener('click', function() {
            mainView.src = this.src;
            thumbnails.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // COTIZADOR LOGIC
    const rangeEnganche = document.getElementById('range-enganche');
    const displayPercent = document.getElementById('display-percent');
    const displayTotalEnganche = document.getElementById('display-total-enganche');
    const inputPlazo = document.getElementById('input-plazo');
    const btnCalculate = document.getElementById('btn-calculate');
    const tablaBody = document.getElementById('tabla-body');
    const cotizacionResult = document.getElementById('resultado-cotizacion');

    const formatMXN = (val) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(val);

    const updateUI = () => {
        const percent = rangeEnganche.value;
        const total = (CAR_PRICE * percent) / 100;
        displayPercent.innerText = `${percent}%`;
        displayTotalEnganche.innerText = formatMXN(total);
    };

    rangeEnganche.addEventListener('input', updateUI);
    updateUI(); // Carga inicial

    btnCalculate.addEventListener('click', () => {
        const enganche = (CAR_PRICE * rangeEnganche.value) / 100;
        const plazo = parseInt(inputPlazo.value);
        const saldoFinanciar = CAR_PRICE - enganche;
        const pagoMensual = saldoFinanciar / plazo;

        tablaBody.innerHTML = '';
        let saldoActual = saldoFinanciar;

        for (let i = 1; i <= plazo; i++) {
            saldoActual -= pagoMensual;
            if (saldoActual < 0) saldoActual = 0;

            const row = `
                <tr>
                    <td>${i}</td>
                    <td>${formatMXN(pagoMensual)}</td>
                    <td>${formatMXN(saldoActual)}</td>
                </tr>
            `;
            tablaBody.innerHTML += row;
        }

        cotizacionResult.style.display = 'block';
        cotizacionResult.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
});