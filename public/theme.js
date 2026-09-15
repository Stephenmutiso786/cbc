(() => {
    const root = document.documentElement;

    const updateButtons = () => {
        const dark = root.classList.contains('theme-dark');
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
        try { localStorage.setItem('cbe-theme', dark ? 'dark' : 'light'); } catch (_) {}
        updateButtons();
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            button.addEventListener('click', toggle);
        });
        updateButtons();
    });
})();
