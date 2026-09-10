<link rel="stylesheet" href="{{ asset('theme.css') }}?v=1">
<script>
    (() => {
        const saved = localStorage.getItem('cbe-theme');
        const dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.classList.toggle('theme-dark', dark);
    })();
</script>
