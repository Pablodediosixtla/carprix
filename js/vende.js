document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const form = document.getElementById('form-vende');
    if (!form) return;

    const panels = [...document.querySelectorAll('[data-step-panel]')];
    const indicators = [...document.querySelectorAll('[data-step-indicator]')];
    const message = document.getElementById('vende-mensaje');
    const submitButton = document.getElementById('btn-vende');
    const summary = document.getElementById('request-summary');
    const yearSelect = document.getElementById('v-anio');
    const terms = document.getElementById('v-terminos');
    const termsError = document.getElementById('terms-error');
    const refrendoDebtWrap = document.getElementById('refrendo-adeudo-wrap');
    const refrendoDebtInput = document.getElementById('v-refrendo-adeudo');
    const wizard = document.getElementById('solicitud-venta');

    let currentStep = 1;
    let vehicleCatalog = {
        marcas: [],
        modelos: [],
        anios: [],
        colores: [],
        transmisiones: [],
    };

    const OTHER_VALUE = '__OTRO__';
    const catalogBindings = {
        'v-marca': 'v-marca-otro',
        'v-modelo': 'v-modelo-otro',
        'v-anio': 'v-anio-otro',
        'v-color': 'v-color-otro',
        'v-transmision': 'v-transmision-otro',
        'v-tipo-factura': 'v-tipo-factura-otro',
    };

    const mobileMenu = document.getElementById('mobile-menu');
    const navMenu = document.getElementById('nav-menu');

    const toggleMobileMenu = () => {
        if (!mobileMenu || !navMenu) return;
        const isOpen = navMenu.classList.toggle('active');
        mobileMenu.setAttribute('aria-expanded', String(isOpen));
    };

    if (mobileMenu && navMenu) {
        mobileMenu.addEventListener('click', toggleMobileMenu);
        mobileMenu.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggleMobileMenu();
            }
        });
    }

    const appendOptions = (select, values, placeholder, { includeOther = true } = {}) => {
        if (!select) return;
        const normalized = [...new Set((values || []).map((value) => String(value).trim()).filter(Boolean))];
        select.innerHTML = `<option value="">${placeholder}</option>` + normalized
            .map((value) => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`)
            .join('') + (includeOther ? `<option value="${OTHER_VALUE}">Otro</option>` : '');
    };

    const populateFallbackYears = () => {
        const currentYear = new Date().getFullYear() + 1;
        const oldestYear = 1980;
        const years = [];
        for (let year = currentYear; year >= oldestYear; year -= 1) years.push(year);
        appendOptions(yearSelect, years, 'Selecciona año');
    };

    const resolveCatalogValue = (selectId) => {
        const select = document.getElementById(selectId);
        if (!select) return '';
        if (select.value !== OTHER_VALUE) return select.value.trim();
        const customId = catalogBindings[selectId];
        return customId ? document.getElementById(customId)?.value.trim() || '' : '';
    };

    const toggleOtherField = (select) => {
        const customId = catalogBindings[select.id];
        if (!customId) return;
        const custom = document.getElementById(customId);
        if (!custom) return;
        const useOther = select.value === OTHER_VALUE;
        custom.hidden = !useOther;
        custom.required = useOther;
        if (!useOther) {
            custom.value = '';
            clearFieldError(custom);
        }
    };

    const populateModels = () => {
        const brandSelect = document.getElementById('v-marca');
        const modelSelect = document.getElementById('v-modelo');
        if (!brandSelect || !modelSelect) return;

        const selectedBrand = resolveCatalogValue('v-marca');
        let models = [];
        if (brandSelect.value && brandSelect.value !== OTHER_VALUE) {
            models = vehicleCatalog.modelos
                .filter((item) => String(item.marca) === selectedBrand)
                .map((item) => item.modelo);
        }

        appendOptions(
            modelSelect,
            models,
            brandSelect.value === OTHER_VALUE ? 'Selecciona o captura modelo' : (brandSelect.value ? 'Selecciona modelo' : 'Selecciona primero una marca')
        );
        modelSelect.disabled = !brandSelect.value;
        document.getElementById('v-modelo-otro').hidden = true;
        document.getElementById('v-modelo-otro').required = false;
    };

    const loadVehicleCatalogOptions = async () => {
        try {
            const response = await fetch(`../db/web/get_catalogo_opciones.php?ts=${Date.now()}`, {
                cache: 'no-store',
                headers: { 'Accept': 'application/json' },
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const result = await response.json();
            if (!result.ok || !result.data) throw new Error(result.error || 'Catálogo no disponible');

            vehicleCatalog = {
                marcas: Array.isArray(result.data.marcas) ? result.data.marcas : [],
                modelos: Array.isArray(result.data.modelos) ? result.data.modelos : [],
                anios: Array.isArray(result.data.anios) ? result.data.anios : [],
                colores: Array.isArray(result.data.colores) ? result.data.colores : [],
                transmisiones: Array.isArray(result.data.transmisiones) ? result.data.transmisiones : [],
            };

            appendOptions(document.getElementById('v-marca'), vehicleCatalog.marcas, 'Selecciona marca');
            appendOptions(yearSelect, vehicleCatalog.anios, 'Selecciona año');
            appendOptions(document.getElementById('v-color'), vehicleCatalog.colores, 'Selecciona color');
            appendOptions(document.getElementById('v-transmision'), vehicleCatalog.transmisiones, 'Selecciona transmisión');
            populateModels();
        } catch (error) {
            console.error('No fue posible cargar opciones del catálogo:', error);
            populateFallbackYears();
            appendOptions(document.getElementById('v-marca'), [], 'Selecciona marca');
            appendOptions(document.getElementById('v-color'), [], 'Selecciona color');
            appendOptions(document.getElementById('v-transmision'), [], 'Selecciona transmisión');
            populateModels();
            setMessage('No pudimos cargar el catálogo de opciones. Puedes seleccionar “Otro” y capturar los datos manualmente.', 'info');
        }
    };

    const setMessage = (text = '', type = 'info') => {
        if (!text) {
            message.textContent = '';
            message.className = 'form-status';
            return;
        }

        message.textContent = text;
        message.className = `form-status is-visible is-${type}`;
    };

    const clearFieldError = (field) => {
        if (!field) return;
        const group = field.closest('.form-group, .radio-fieldset');
        if (group) group.classList.remove('has-error');

        const error = group ? group.querySelector('.field-error') : null;
        if (error) error.textContent = '';
    };

    const setFieldError = (field, text) => {
        if (!field) return;
        const group = field.closest('.form-group, .radio-fieldset');
        if (group) group.classList.add('has-error');

        const error = group ? group.querySelector('.field-error') : null;
        if (error) error.textContent = text;
    };

    const validateEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    const validatePhone = (value) => value.replace(/\D/g, '').length >= 10;

    const validatePanel = (step) => {
        const panel = panels.find((item) => Number(item.dataset.stepPanel) === step);
        if (!panel) return true;

        let valid = true;
        const requiredFields = [...panel.querySelectorAll('input[required], select[required], textarea[required]')]
            .filter((field) => field.type !== 'radio' && field.type !== 'checkbox');

        requiredFields.forEach((field) => {
            clearFieldError(field);
            const value = field.value.trim();

            if (!value) {
                setFieldError(field, 'Este campo es obligatorio.');
                valid = false;
                return;
            }

            if (field.type === 'email' && !validateEmail(value)) {
                setFieldError(field, 'Ingresa un correo electrónico válido.');
                valid = false;
            }

            if (field.type === 'tel' && !validatePhone(value)) {
                setFieldError(field, 'Ingresa un teléfono de al menos 10 dígitos.');
                valid = false;
            }

            if (field.id === 'v-km' && Number(value) < 0) {
                setFieldError(field, 'El kilometraje no puede ser negativo.');
                valid = false;
            }
        });

        if (step === 2) {
            const refrendoSelected = form.querySelector('input[name="refrendo"]:checked');
            const radioFieldset = form.querySelector('.radio-fieldset');
            const radioError = form.querySelector('[data-radio-error="refrendo"]');

            radioFieldset.classList.remove('has-error');
            radioError.textContent = '';

            if (!refrendoSelected) {
                radioFieldset.classList.add('has-error');
                radioError.textContent = 'Selecciona el estado del refrendo.';
                valid = false;
            } else if (refrendoSelected.value === 'Con adeudo' && !refrendoDebtInput.value.trim()) {
                setFieldError(refrendoDebtInput, 'Indica el monto aproximado del adeudo.');
                valid = false;
            }
        }

        if (!valid) {
            const firstInvalid = panel.querySelector('.has-error input, .has-error select, .has-error textarea');
            if (firstInvalid) firstInvalid.focus({ preventScroll: true });
            setMessage('Revisa los campos marcados antes de continuar.', 'error');
        } else {
            setMessage();
        }

        return valid;
    };

    const updateSummary = () => {
        const fullName = [
            document.getElementById('v-nombre').value.trim(),
            document.getElementById('v-apellido-paterno').value.trim(),
            document.getElementById('v-apellido-materno').value.trim()
        ].filter(Boolean).join(' ');

        const vehicle = [
            resolveCatalogValue('v-marca'),
            resolveCatalogValue('v-modelo'),
            document.getElementById('v-version').value.trim(),
            resolveCatalogValue('v-anio')
        ].filter(Boolean).join(' ');

        const location = [
            document.getElementById('v-municipio').value.trim(),
            document.getElementById('v-estado').value
        ].filter(Boolean).join(', ');

        const items = [
            ['Solicitante', fullName],
            ['Vehículo', vehicle],
            ['Kilometraje', `${Number(document.getElementById('v-km').value || 0).toLocaleString('es-MX')} km`],
            ['Color', resolveCatalogValue('v-color')],
            ['Transmisión', resolveCatalogValue('v-transmision')],
            ['Ubicación', location],
            ['Teléfono', document.getElementById('v-tel').value.trim()],
            ['Correo', document.getElementById('v-email').value.trim()]
        ];

        summary.innerHTML = items.map(([label, value]) => `
            <div class="summary-item">
                <strong>${escapeHtml(label)}</strong>
                <span>${escapeHtml(value || 'No capturado')}</span>
            </div>
        `).join('');
    };

    const escapeHtml = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const showStep = (step) => {
        currentStep = step;

        panels.forEach((panel) => {
            const panelStep = Number(panel.dataset.stepPanel);
            const active = panelStep === step;
            panel.hidden = !active;
            panel.classList.toggle('is-active', active);
        });

        indicators.forEach((indicator) => {
            const indicatorStep = Number(indicator.dataset.stepIndicator);
            const active = indicatorStep === step;
            const complete = indicatorStep < step;

            indicator.classList.toggle('is-active', active);
            indicator.classList.toggle('is-complete', complete);
            indicator.setAttribute('tabindex', active ? '0' : '-1');

            if (active) {
                indicator.setAttribute('aria-current', 'step');
            } else {
                indicator.removeAttribute('aria-current');
            }
        });

        if (step === 3) updateSummary();

        const top = wizard.getBoundingClientRect().top + window.scrollY - 92;
        window.scrollTo({ top, behavior: 'smooth' });
    };

    form.addEventListener('click', (event) => {
        const nextButton = event.target.closest('[data-next-step]');
        const prevButton = event.target.closest('[data-prev-step]');

        if (nextButton) {
            const nextStep = Number(nextButton.dataset.nextStep);
            if (validatePanel(currentStep)) showStep(nextStep);
        }

        if (prevButton) {
            showStep(Number(prevButton.dataset.prevStep));
        }
    });

    form.addEventListener('input', (event) => {
        clearFieldError(event.target);
        if (message.classList.contains('is-error')) setMessage();
    });

    form.addEventListener('change', (event) => {
        clearFieldError(event.target);

        if (event.target.matches('select') && catalogBindings[event.target.id]) {
            toggleOtherField(event.target);
            if (event.target.id === 'v-marca') populateModels();
        }

        if (event.target.name === 'refrendo') {
            const hasDebt = event.target.value === 'Con adeudo';
            refrendoDebtWrap.hidden = !hasDebt;
            refrendoDebtInput.required = hasDebt;
            if (!hasDebt) {
                refrendoDebtInput.value = '';
                clearFieldError(refrendoDebtInput);
            }
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        termsError.textContent = '';
        if (!terms.checked) {
            termsError.textContent = 'Debes aceptar los términos para enviar la solicitud.';
            terms.focus();
            return;
        }

        if (!validatePanel(1) || !validatePanel(2)) {
            showStep(!validatePanel(1) ? 1 : 2);
            return;
        }

        const firstName = document.getElementById('v-nombre').value.trim();
        const paternalSurname = document.getElementById('v-apellido-paterno').value.trim();
        const maternalSurname = document.getElementById('v-apellido-materno').value.trim();
        const email = document.getElementById('v-email').value.trim();
        const refrendo = form.querySelector('input[name="refrendo"]:checked');
        const debtAmount = refrendoDebtInput.value.trim();
        const imperfections = document.getElementById('v-imperfecciones').value.trim();
        const state = document.getElementById('v-estado').value;
        const municipality = document.getElementById('v-municipio').value.trim();

        /*
         * El endpoint actual no tiene columnas separadas para correo, refrendo,
         * imperfecciones o ubicación. Se integran en comentarios para conservar
         * toda la información sin modificar la base de datos ni insert_prospecto.php.
         */
        const comments = [
            `Correo: ${email}`,
            `Refrendo: ${refrendo ? refrendo.value : 'No indicado'}${debtAmount ? ` (adeudo aproximado: $${Number(debtAmount).toLocaleString('es-MX')})` : ''}`,
            `Ubicación del vehículo: ${municipality}, ${state}`,
            `Imperfecciones: ${imperfections}`
        ].join(' | ');

        const payload = {
            marca: resolveCatalogValue('v-marca'),
            modelo: resolveCatalogValue('v-modelo'),
            version: document.getElementById('v-version').value.trim(),
            anio: resolveCatalogValue('v-anio'),
            km: document.getElementById('v-km').value,
            color: resolveCatalogValue('v-color'),
            transmision: resolveCatalogValue('v-transmision'),
            tipo_factura: resolveCatalogValue('v-tipo-factura'),
            propietarios: document.getElementById('v-propietarios').value,
            nombre: [firstName, paternalSurname, maternalSurname].filter(Boolean).join(' '),
            tel: document.getElementById('v-tel').value.trim(),
            comentarios: comments
        };

        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Enviando...';
        setMessage('Procesando tu solicitud...', 'info');

        try {
            const response = await fetch('../db/web/insert_prospecto.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            let result;
            try {
                result = await response.json();
            } catch (parseError) {
                throw new Error('El servidor devolvió una respuesta no válida.');
            }

            if (!response.ok || !result.ok) {
                throw new Error(result.error || 'No fue posible registrar la solicitud.');
            }

            setMessage(result.mensaje || 'Solicitud registrada correctamente. Un asesor te contactará.', 'success');
            form.reset();
            Object.values(catalogBindings).forEach((customId) => {
                const custom = document.getElementById(customId);
                if (custom) {
                    custom.hidden = true;
                    custom.required = false;
                    custom.value = '';
                }
            });
            populateModels();
            refrendoDebtWrap.hidden = true;
            refrendoDebtInput.required = false;
            showStep(1);
        } catch (error) {
            console.error('Error en la solicitud:', error);
            setMessage(`No pudimos enviar la solicitud: ${error.message}`, 'error');
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = 'Enviar solicitud <i class="fas fa-paper-plane" aria-hidden="true"></i>';
        }
    });

    loadVehicleCatalogOptions();
    showStep(1);
});
