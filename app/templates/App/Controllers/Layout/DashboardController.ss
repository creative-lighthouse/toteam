<div class="section section--DashboardPage">
    <div class="section_content">
        <div id="pwa-install-banner" class="pwa-install-banner" style="display:none;">
            <p class="pwa-install-text"><strong>Installiere die ToTeam Web-App direkt auf deinem Gerät!</strong></p>
            <span id="pwa-install-btn" class="pwa-install-btn" style="display:none;">
                <button id="pwa-install-btn-action" class="pwa-install-btn-action">App installieren</button>
            </span>
            <p id="pwa-install-hint" class="pwa-install-hint" style="display:none;">Du kannst die App über das Browser-Menü installieren.</p>
            <button id="pwa-install-close" class="pwa-install-close">&times;</button>
        </div>

        <div class="section_infobox">
            <h1 class="hl1">Dashboard</h1>
            <p>Willkommen, $User.FirstName $User.Surname!</p>
        </div>
        <div class="section_infobox">
            <h2 class="hl2">Deine anstehenden Termine</h2>
            <ul class="infobox_list">
                <% if $UpcomingEventDays %>
                    <% loop $UpcomingEventDays %>
                        <li class="<% if $Type = 'Accept' %>accepted<% else_if $Type = 'Maybe' %>maybe<% end_if %>"><a href="$Parent.Link">$Parent.Title - <i>$Parent.RenderDateWithTime</i></a></li>
                    <% end_loop %>
                <% else %>
                    <li><p>Keine anstehenden Termine</p></li>
                <% end_if %>
            </ul>
        </div>
        <div class="section_infobox">
            <h2 class="hl2">Heutige Mahlzeiten</h2>
            <ul class="infobox_list list--meals">
                <% if $MealsToday %>
                    <% loop $MealsToday %>
                        <li>
                            <h3 class="hl3">$Parent.Title <span class="meal_time">- $Parent.RenderTime</span></h3>
                            <% if $Parent.Foods %>
                                <ul class="list--foods">
                                    <% loop $Parent.Foods %>
                                        <li>
                                            <p>$Title <span>von $RenderSupplier</span></p>
                                        </li>
                                    <% end_loop %>
                                </ul>
                            <% else %>
                                <p>Noch kein Gericht eingetragen</p>
                            <% end_if %>
                        </li>
                    <% end_loop %>
                <% else %>
                    <li>Du hast heute keine Mahlzeiten</li>
                <% end_if %>
            </ul>
        </div>
    </div>
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
