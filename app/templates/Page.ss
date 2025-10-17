<!doctype html>
<html lang="de">
    <head>
        <style>
            .offline-banner {
                background: #ffcc00;
                color: #333;
                padding: 1em;
                text-align: center;
                font-size: 1.2em;
                border-bottom: 2px solid #e6b800;
                z-index: 9999;
                position: relative;
            }
        </style>
        <% base_tag %>
        $MetaTags(false)
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta charset="utf-8">
        <title>$Title - $SiteConfig.Title</title>
        <link rel="manifest" href="site.webmanifest" />
        $ViteClient.RAW
        <link rel="stylesheet" href="$Vite('app/client/src/scss/main.scss')">
        <script type="module" src="$Vite('app/client/src/js/main.js')"></script>

        <meta name="twitter:card" content="summary" />
        <meta name="twitter:site" content="{$Title}" />
        <meta name="twitter:url" content="{$Link}" />
        <meta name="twitter:title" content="{$Title} - {$SiteConfig.Title}" />
        <meta name="twitter:description" content="{$Title}" />
        <meta name="twitter:image" content="app/client/images/ToTeam-SocialImage.png" />

        <meta property="og:type" content="website" />
        <meta property="og:title" content="{$Title} - {$SiteConfig.Title}" />
        <meta property="og:description" content="{$Title}" />
        <meta property="og:site_name" content="{$SiteConfig.Title}" />
        <meta property="og:url" content="{$Link}" />
        <meta property="og:image" content="app/client/images/ToTeam-SocialImage.png" />

        <link rel="apple-touch-icon" sizes="120x120" href="_resources/app/client/icons/apple-touch-icon_120.png" />
        <link rel="apple-touch-icon" sizes="180x180" href="_resources/app/client/icons/apple-touch-icon_180.png" />
        <link rel="mask-icon" href="_resources/app/client/icons/ToTeam-safari-pinned-tab.svg" color="#4E9DAE" />
        <meta name="msapplication-TileColor" content="#d54f27" />
        <link rel="apple-touch-icon" sizes="180x180" href="_resources/app/client/icons/ToTeam-safari-pinned-tab.svg" />
        <link rel="icon" type="image/png" sizes="128x128" href="_resources/app/client/icons/ToTeam-Favicon-x128.png" />
        <link rel="icon" type="image/png" sizes="64x64" href="_resources/app/client/icons/ToTeam-Favicon-x64.png" />
        <link rel="icon" type="image/png" sizes="32x32" href="_resources/app/client/icons/ToTeam-Favicon-x32.png" />
        <link rel="icon" type="image/png" sizes="16x16" href="_resources/app/client/icons/ToTeam-Favicon-x16.png" />
        <!-- PWA Meta-Tags -->
        <meta name="application-name" content="ToTeam" />
        <meta name="theme-color" content="#4E9DAE" />
        <meta name="mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-status-bar-style" content="default" />
        <meta name="apple-mobile-web-app-title" content="ToTeam" />
        <meta name="description" content="Team- und Event-Management als PWA" />
        <link rel="manifest" href="site.webmanifest" />
        <link rel="apple-touch-icon" sizes="180x180" href="_resources/app/client/icons/apple-touch-icon_180.png" />
        <link rel="icon" sizes="192x192" href="_resources/app/client/icons/icon_192.png" />
        <link rel="icon" sizes="512x512" href="_resources/app/client/icons/icon_512.png" />
        <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/service-worker.js')
                    .then(function(registration) {
                        console.log('Willkommen bei ToTeam!');
                    })
                    .catch(function(error) {
                        console.log('ServiceWorker registration failed:', error);
                    });
            });
        }
        </script>
    </head>
    <body>
        <div class="area_header">
            <% include Header %>
        </div>
        <main class="area_content main">
                $Layout
        </main>
        <div class="area_footer">
            <p class="footer_note"><i>ToTeam v0.1.2</i> <kbd>BETA</kbd></p>
        </div>
        <script>
            function showOfflineBanner() {
                if (!navigator.onLine) {
                    // Banner einfügen
                    if (!document.querySelector('.offline-banner')) {
                        var banner = document.createElement('div');
                        banner.className = 'offline-banner';
                        banner.innerHTML = '<strong>Offline-Modus:</strong> Du bist aktuell nicht mit dem Internet verbunden.';
                        document.body.prepend(banner);
                    }
                    // Navigation ausblenden (passe Selektoren ggf. an)
                    var nav = document.querySelector('nav');
                    if (nav) nav.style.display = 'none';
                    var header = document.querySelector('.area_header');
                    if (header) header.style.display = 'none';
                }
            }
            window.addEventListener('load', showOfflineBanner);
            window.addEventListener('online', function() {
                var banner = document.querySelector('.offline-banner');
                if (banner) banner.remove();
                var nav = document.querySelector('nav');
                if (nav) nav.style.display = '';
                var header = document.querySelector('.area_header');
                if (header) header.style.display = '';
            });
            window.addEventListener('offline', showOfflineBanner);
        </script>
    </body>
</html>
