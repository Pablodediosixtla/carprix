(() => {
    'use strict';

    const scriptUrl = document.currentScript?.src
        ? new URL(document.currentScript.src, window.location.href)
        : new URL('js/public-operativo-session.js', window.location.href);
    const siteRoot = new URL('../', scriptUrl);
    const sessionUrl = new URL('db/web/operativo/op_session.php', siteRoot);
    const homeUrl = new URL('operativo/home.php', siteRoot);
    const loginUrl = new URL('operativo/login.php', siteRoot);

    const sessionPromise = (async () => {
        try {
            const response = await fetch(sessionUrl, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: '{}',
                cache: 'no-store',
            });
            if (!response.ok) return { authenticated: false, usuario: null };
            const body = await response.json();
            return {
                authenticated: Boolean(body?.data?.authenticated && body?.data?.usuario),
                usuario: body?.data?.usuario || null,
            };
        } catch (error) {
            return { authenticated: false, usuario: null };
        }
    })();

    const applyNavigation = async () => {
        const session = await sessionPromise;
        document.querySelectorAll('[data-operativo-access]').forEach((link) => {
            if (session.authenticated) {
                link.href = homeUrl.href;
                link.textContent = 'Home Operativo';
                link.title = 'Ir al Home de la operación';
            } else {
                link.href = loginUrl.href;
                link.textContent = 'Iniciar Sesión';
                link.removeAttribute('title');
            }
        });
        window.dispatchEvent(new CustomEvent('carprix:operativo-session', { detail: session }));
        return session;
    };

    window.CARPRIX_PUBLIC_OPERATIVO = {
        siteRoot,
        sessionUrl,
        homeUrl,
        loginUrl,
        getSession: () => sessionPromise,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyNavigation, { once: true });
    } else {
        applyNavigation();
    }
})();
