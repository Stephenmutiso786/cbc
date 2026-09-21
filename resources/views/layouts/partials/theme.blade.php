@php($themeAssetBase = rtrim(request()->getBaseUrl(), '/'))
<link rel="stylesheet" href="{{ $themeAssetBase }}/theme.css?v=5">
<script>
    (() => {
        let saved = null;
        try { saved = localStorage.getItem('cbe-theme'); } catch (_) {}
        if (!saved) {
            const match = document.cookie.match(/(?:^|; )cbe-theme=(dark|light)(?:;|$)/);
            saved = match ? match[1] : null;
        }
        const dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.classList.toggle('theme-dark', dark);
        document.documentElement.dataset.theme = dark ? 'dark' : 'light';
    })();
</script>
<script src="{{ $themeAssetBase }}/theme.js?v=5" defer></script>
