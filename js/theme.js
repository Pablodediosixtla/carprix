(() => {
    const STORAGE_KEY = 'carprix-theme';
    const root = document.documentElement;

    const getSystemTheme = () => (
        window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches
            ? 'light'
            : 'dark'
    );

    const getInitialTheme = () => {
        const savedTheme = localStorage.getItem(STORAGE_KEY);
        return savedTheme === 'light' || savedTheme === 'dark'
            ? savedTheme
            : getSystemTheme();
    };

    const updateToggle = (theme) => {
        const button = document.getElementById('theme-toggle');
        if (!button) return;

        const icon = button.querySelector('i');
        const nextTheme = theme === 'dark' ? 'light' : 'dark';
        const nextLabel = nextTheme === 'light' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro';

        button.setAttribute('aria-label', nextLabel);
        button.setAttribute('title', nextLabel);
        button.setAttribute('aria-pressed', theme === 'light' ? 'true' : 'false');

        if (icon) {
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
    };

    const applyTheme = (theme, persist = false) => {
        root.setAttribute('data-theme', theme);
        root.style.colorScheme = theme;
        updateToggle(theme);

        if (persist) {
            localStorage.setItem(STORAGE_KEY, theme);
        }
    };

    applyTheme(getInitialTheme());

    document.addEventListener('DOMContentLoaded', () => {
        const button = document.getElementById('theme-toggle');
        const header = document.querySelector('.main-header');

        updateToggle(root.getAttribute('data-theme'));

        if (button) {
            button.addEventListener('click', () => {
                const currentTheme = root.getAttribute('data-theme') || 'dark';
                const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
                applyTheme(nextTheme, true);
            });
        }

        const updateHeaderState = () => {
            if (header) {
                header.classList.toggle('scrolled', window.scrollY > 40);
            }
        };

        updateHeaderState();
        window.addEventListener('scroll', updateHeaderState, { passive: true });
    });

    window.addEventListener('storage', (event) => {
        if (event.key === STORAGE_KEY && (event.newValue === 'light' || event.newValue === 'dark')) {
            applyTheme(event.newValue);
        }
    });
})();
