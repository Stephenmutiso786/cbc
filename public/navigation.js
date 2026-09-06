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

    function initMenu() {
        var button = document.querySelector('[data-mobile-menu]');
        var overlay = document.querySelector('[data-sidebar-overlay]');
        var closeButtons = document.querySelectorAll('[data-sidebar-close]');

        if (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                setMenu(button.getAttribute('aria-expanded') !== 'true');
            });
        }
        if (overlay) overlay.addEventListener('click', function () { setMenu(false); });
        closeButtons.forEach(function (closeButton) {
            closeButton.addEventListener('click', function () { setMenu(false); });
        });
    }

    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-sidebar] a') && window.innerWidth < 768) {
            setMenu(false);
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMenu);
    } else {
        initMenu();
    }

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 768) setMenu(false);
    });
})();
