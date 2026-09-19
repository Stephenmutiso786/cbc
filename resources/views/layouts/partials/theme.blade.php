<link rel="stylesheet" href="{{ asset('theme.css') }}?v=3">
<script>
    (() => {
        let saved = null;
        try { saved = localStorage.getItem('cbe-theme'); } catch (_) {}
        const dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.classList.toggle('theme-dark', dark);
    })();
</script>
<script src="{{ asset('theme.js') }}?v=3" defer></script>
