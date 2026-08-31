(function () {
    var deferredPrompt;
    var installButton;

    function createInstallButton() {
        if (installButton || !document.body) return;
        installButton = document.createElement('button');
        installButton.type = 'button';
        installButton.textContent = 'Install app';
        installButton.setAttribute('aria-label', 'Install CBC School app');
        installButton.style.cssText = 'position:fixed;right:1rem;bottom:1rem;z-index:60;border:0;border-radius:9999px;background:#166534;color:#fff;padding:.7rem 1rem;font:600 14px sans-serif;box-shadow:0 8px 24px rgba(0,0,0,.2);cursor:pointer';
        installButton.addEventListener('click', function () {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function () {
                deferredPrompt = null;
                installButton.remove();
                installButton = null;
            });
        });
        document.body.appendChild(installButton);
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredPrompt = event;
        createInstallButton();
    });

    window.addEventListener('appinstalled', function () {
        if (installButton) installButton.remove();
        installButton = null;
        deferredPrompt = null;
    });

    if ('serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js').catch(function () {});
        });
    }
})();
