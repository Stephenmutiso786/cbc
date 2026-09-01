(function () {
    var deferredPrompt;
    var installButton;
    var helpPanel;
    var statusBadge;

    function isInstalled() {
        return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    }

    function closeHelp() {
        if (helpPanel) {
            helpPanel.remove();
            helpPanel = null;
        }
    }

    function showHelp() {
        if (helpPanel) return;
        helpPanel = document.createElement('div');
        helpPanel.setAttribute('role', 'dialog');
        helpPanel.setAttribute('aria-label', 'Install CBC School app');
        helpPanel.style.cssText = 'position:fixed;right:1rem;bottom:4.5rem;z-index:61;max-width: min( calc(100vw - 2rem), 22rem);border:1px solid #bbf7d0;border-radius:1rem;background:#fff;color:#172016;padding:1rem;box-shadow:0 14px 40px rgba(0,0,0,.22);font:14px/1.45 sans-serif';
        var title = document.createElement('strong');
        title.textContent = 'Install CBC School';
        title.style.cssText = 'display:block;margin-bottom:.45rem;font-size:15px';
        var instructions = document.createElement('p');
        instructions.style.margin = '0 0 .75rem';
        if (/iphone|ipad|ipod/i.test(navigator.userAgent)) {
            instructions.textContent = 'Tap Share, choose Add to Home Screen, then tap Add.';
        } else {
            instructions.textContent = 'Open your browser menu and choose Install app or Add to Home screen.';
        }
        var close = document.createElement('button');
        close.type = 'button';
        close.textContent = 'Close';
        close.style.cssText = 'border:0;border-radius:.5rem;background:#166534;color:#fff;padding:.45rem .75rem;font-weight:600;cursor:pointer';
        close.addEventListener('click', closeHelp);
        helpPanel.append(title, instructions, close);
        document.body.appendChild(helpPanel);
    }

    function createInstallButton() {
        if (installButton || !document.body || isInstalled()) return;
        installButton = document.createElement('button');
        installButton.type = 'button';
        installButton.textContent = 'Install app';
        installButton.setAttribute('aria-label', 'Install CBC School app');
        installButton.style.cssText = 'position:fixed;right:1rem;bottom:1rem;z-index:60;border:0;border-radius:9999px;background:#166534;color:#fff;padding:.7rem 1rem;font:600 14px sans-serif;box-shadow:0 8px 24px rgba(0,0,0,.2);cursor:pointer';
        installButton.addEventListener('click', function () {
            if (!deferredPrompt) {
                showHelp();
                return;
            }
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function () {
                deferredPrompt = null;
                installButton.remove();
                installButton = null;
            });
        });
        document.body.appendChild(installButton);
    }

    function updateConnectionStatus() {
        if (!document.body) return;
        if (!statusBadge) {
            statusBadge = document.createElement('div');
            statusBadge.setAttribute('role', 'status');
            statusBadge.style.cssText = 'position:fixed;left:1rem;bottom:1rem;z-index:59;border-radius:9999px;padding:.45rem .7rem;font:600 12px sans-serif;box-shadow:0 5px 16px rgba(0,0,0,.14)';
            document.body.appendChild(statusBadge);
        }
        var online = navigator.onLine;
        statusBadge.textContent = online ? 'Online' : 'Offline';
        statusBadge.style.background = online ? '#dcfce7' : '#fee2e2';
        statusBadge.style.color = online ? '#166534' : '#991b1b';
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredPrompt = event;
        createInstallButton();
    });

    window.addEventListener('appinstalled', function () {
        closeHelp();
        if (installButton) installButton.remove();
        installButton = null;
        deferredPrompt = null;
    });

    window.addEventListener('online', updateConnectionStatus);
    window.addEventListener('offline', updateConnectionStatus);

    if ('serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js').catch(function () {});
        });
    }

    // Keep a visible install action on mobile, including iOS where the native
    // beforeinstallprompt event is not supported.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            createInstallButton();
            updateConnectionStatus();
        });
    } else {
        createInstallButton();
        updateConnectionStatus();
    }
})();
