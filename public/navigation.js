(function () {
    function setMenu(open) {
        var sidebar = document.querySelector('[data-sidebar]');
        var overlay = document.querySelector('[data-sidebar-overlay]');
        var button = document.querySelector('[data-mobile-menu]');

        if (!sidebar) return;
        sidebar.classList.toggle('-translate-x-full', !open);
        sidebar.classList.toggle('translate-x-0', open);
        if (overlay) overlay.classList.toggle('hidden', !open);
        if (button) button.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.classList.toggle('overflow-hidden', open);
    }

    document.addEventListener('click', function (event) {
        var menuButton = event.target.closest('[data-mobile-menu]');
        if (menuButton) {
            event.preventDefault();
            setMenu(menuButton.getAttribute('aria-expanded') !== 'true');
            return;
        }

        if (event.target.closest('[data-sidebar-overlay], [data-sidebar-close]')) {
            setMenu(false);
            return;
        }

        if (event.target.closest('[data-sidebar] a') && window.innerWidth < 768) {
            setMenu(false);
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 768) setMenu(false);
    });
})();
