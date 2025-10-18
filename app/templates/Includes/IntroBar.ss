<div class="introbar">
    <a class="introbar-action" onclick="history.back()">
        <img class="introbar-icon" src="_resources/app/client/icons/actions/action_back.svg" alt="Back Icon">
    </a>
    <h1 class="hl1 introbar-title">$Title</h1>
    <a class="introbar-action" onclick="document.querySelector('#introbar-info-dialog').showModal()">
        <img class="introbar-icon" src="_resources/app/client/icons/actions/action_help.svg" alt="Help Icon">
    </a>
    <dialog id="introbar-info-dialog" class="introbar-info-dialog">
        <button class="dialog-close" onclick="document.getElementById('introbar-info-dialog').close()">×</button>
        <div class="dialog-header">
            <h3>$Title</h3>
        </div>
        <div class="dialog-content">
            <p>$Description</p>
        </div>
    </dialog>
</div>

<div id="pwa-install-banner" class="pwa-install-banner" style="display:none;">
    <p class="pwa-install-text"><strong>Installiere die ToTeam Web-App direkt auf deinem Gerät!</strong></p>
    <span id="pwa-install-btn" class="pwa-install-btn" style="display:none;">
        <a id="pwa-install-btn-action" class="button pwa-install-btn-action">App installieren</a>
    </span>
    <p id="pwa-install-hint" class="pwa-install-hint" style="display:none;">Du kannst die App über das Browser-Menü installieren.</p>
    <button id="pwa-install-close" class="pwa-install-close">&times;</button>
</div>

<script>
    // Cookie-Helper
    function setCookie(name, value, days) {
        var expires = "";
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days*24*60*60*1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "")  + expires + "; path=/";
    }
    function getCookie(name) {
        var nameEQ = name + "=";
        var ca = document.cookie.split(';');
        for(var i=0;i < ca.length;i++) {
            var c = ca[i];
            while (c.charAt(0)==' ') c = c.substring(1,c.length);
            if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
        }
        return null;
    }

    window.addEventListener('DOMContentLoaded', function() {
        const banner = document.getElementById('pwa-install-banner');
        const btn = document.getElementById('pwa-install-btn-action');
        const closeBtn = document.getElementById('pwa-install-close');
    });

    let deferredPrompt;
    function showBanner(installable) {
        if (getCookie('pwaInstallBannerClosed') === '1') return;
        document.getElementById('pwa-install-banner').style.display = 'block';
        document.getElementById('pwa-install-btn').style.display = installable ? 'inline-block' : 'none';
        document.getElementById('pwa-install-hint').style.display = installable ? 'none' : 'inline-block';
    }

    window.addEventListener('beforeinstallprompt', (e) => {
        //e.preventDefault();
        deferredPrompt = e;
        showBanner(true);
    });

    window.addEventListener('load', () => {
        if (getCookie('pwaInstallBannerClosed') === '1') return;
        if (!window.matchMedia('(display-mode: standalone)').matches && !deferredPrompt) {
            showBanner(false);
        }
    });

    document.getElementById('pwa-install-btn-action').addEventListener('click', function() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    document.getElementById('pwa-install-banner').style.display = 'none';
                    setCookie('pwaInstallBannerClosed', '1', 14);
                }
                deferredPrompt = null;
            });
        }
    });

    document.getElementById('pwa-install-close').addEventListener('click', function() {
        document.getElementById('pwa-install-banner').style.display = 'none';
        setCookie('pwaInstallBannerClosed', '1', 14);
    });
</script>
