(() => {
    const root = document.documentElement;

    const updateButtons = () => {
        const dark = root.classList.contains('theme-dark');
        root.dataset.theme = dark ? 'dark' : 'light';
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            button.setAttribute('aria-pressed', dark ? 'true' : 'false');
            button.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
            button.setAttribute('title', dark ? 'Switch to light mode' : 'Switch to dark mode');
            const icon = button.querySelector('[data-theme-icon]');
            const label = button.querySelector('[data-theme-label]');
            if (icon) icon.textContent = dark ? '☀' : '☾';
            if (label) label.textContent = dark ? 'Light mode' : 'Dark mode';
        });
    };

    const toggle = () => {
        const dark = !root.classList.contains('theme-dark');
        root.classList.toggle('theme-dark', dark);
        const value = dark ? 'dark' : 'light';
        try { localStorage.setItem('cbe-theme', value); } catch (_) {}
        // This retains the preference in browsers where localStorage is
        // blocked (private browsing, strict privacy settings, or webviews).
        document.cookie = `cbe-theme=${value}; Path=/; Max-Age=31536000; SameSite=Lax`;
        updateButtons();
    };

    // Use one delegated handler rather than binding buttons only during
    // DOMContentLoaded. Livewire can replace header content and installed
    // PWA pages can be restored from cache after that event has fired.
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-theme-toggle]');
        if (!button) return;

        event.preventDefault();
        toggle();
    }, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateButtons, { once: true });
    } else {
        updateButtons();
    }
    window.addEventListener('pageshow', updateButtons);
})();
