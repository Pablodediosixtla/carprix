(() => {
    'use strict';

    const STORAGE_KEY = 'carprix-theme';
    const root = document.documentElement;
    const THEMES = new Set(['dark', 'light']);

    const readStoredTheme = () => {
        try {
            const value = window.localStorage.getItem(STORAGE_KEY);
            return THEMES.has(value) ? value : null;
        } catch (error) {
            return null;
        }
    };

    const storeTheme = (theme) => {
        try {
            window.localStorage.setItem(STORAGE_KEY, theme);
        } catch (error) {
            // El cambio sigue funcionando durante la sesión aunque el navegador bloquee localStorage.
        }
    };

    const getInitialTheme = () => readStoredTheme() || 'dark';

    const updateThemeColor = (theme) => {
        const themeColor = document.querySelector('meta[name="theme-color"]');
        if (themeColor) {
            themeColor.setAttribute('content', theme === 'light' ? '#ffffff' : '#1a1a1a');
        }
    };

    const updateButtons = (theme) => {
        document.querySelectorAll('[data-theme-toggle], #theme-toggle').forEach((button) => {
            const nextTheme = theme === 'dark' ? 'light' : 'dark';
            const label = nextTheme === 'light'
                ? 'Cambiar a tema claro'
                : 'Cambiar a tema oscuro';
            const icon = button.querySelector('i');
            const text = button.querySelector('[data-theme-label]');

            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
            button.setAttribute('aria-pressed', String(theme === 'light'));

            if (icon) {
                icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
            if (text) {
                text.textContent = label;
            }
        });
    };

    const applyTheme = (theme, persist = false) => {
        const validTheme = THEMES.has(theme) ? theme : 'dark';
        root.setAttribute('data-theme', validTheme);
        root.style.colorScheme = validTheme;

        if (document.body) {
            document.body.classList.toggle('theme-light', validTheme === 'light');
            document.body.classList.toggle('theme-dark', validTheme === 'dark');
        }

        updateThemeColor(validTheme);
        updateButtons(validTheme);

        if (persist) {
            storeTheme(validTheme);
        }

        window.dispatchEvent(new CustomEvent('carprix:themechange', {
            detail: { theme: validTheme }
        }));
    };

    // Se aplica antes de cargar el CSS para evitar el destello de un tema incorrecto.
    applyTheme(getInitialTheme());

    const bindThemeControls = () => {
        applyTheme(root.getAttribute('data-theme') || getInitialTheme());

        document.querySelectorAll('[data-theme-toggle], #theme-toggle').forEach((button) => {
            if (button.dataset.themeBound === 'true') return;

            button.dataset.themeBound = 'true';
            button.addEventListener('click', () => {
                const currentTheme = root.getAttribute('data-theme') || 'dark';
                applyTheme(currentTheme === 'dark' ? 'light' : 'dark', true);
            });
        });

        const header = document.querySelector('.main-header');
        const updateHeaderState = () => {
            if (header) {
                header.classList.toggle('scrolled', window.scrollY > 40);
            }
        };

        updateHeaderState();
        window.addEventListener('scroll', updateHeaderState, { passive: true });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindThemeControls, { once: true });
    } else {
        bindThemeControls();
    }

    window.addEventListener('storage', (event) => {
        if (event.key === STORAGE_KEY && THEMES.has(event.newValue)) {
            applyTheme(event.newValue, false);
        }
    });
})();
