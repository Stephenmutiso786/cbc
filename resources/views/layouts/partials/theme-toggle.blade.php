<button type="button" class="theme-toggle" data-theme-toggle aria-label="Toggle dark mode" aria-pressed="false">
    <span data-theme-icon aria-hidden="true">Moon</span><span data-theme-label>Dark mode</span>
</button>
<script>
    (() => {
        const update = () => {
            const dark = document.documentElement.classList.contains('theme-dark');
            document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
                button.setAttribute('aria-pressed', dark ? 'true' : 'false');
                button.querySelector('[data-theme-icon]')?.replaceChildren(document.createTextNode(dark ? 'Sun' : 'Moon'));
                button.querySelector('[data-theme-label]')?.replaceChildren(document.createTextNode(dark ? 'Light mode' : 'Dark mode'));
            });
        };
        document.addEventListener('click', (event) => {
            if (!event.target.closest('[data-theme-toggle]')) return;
            const dark = !document.documentElement.classList.contains('theme-dark');
            document.documentElement.classList.toggle('theme-dark', dark);
            localStorage.setItem('cbe-theme', dark ? 'dark' : 'light');
            update();
        });
        update();
    })();
</script>
