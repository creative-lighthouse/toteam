<!doctype html>
<html lang="de">
    <head>
        <% base_tag %>
        $MetaTags(false)
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta charset="utf-8">
        <title>$SiteConfig.Title - Vue App</title>
        <link rel="manifest" href="/site.webmanifest" />
        
        <!-- Vite Client for HMR in development -->
        $ViteClient.RAW
        
        <!-- Main Styles (SCSS wird weiterhin verwendet) -->
        <link rel="stylesheet" href="$Vite('app/client/src/scss/main.scss')">
        
        <!-- Vue App Script -->
        <script type="module" src="$Vite('app/client/src/vue/app.js')"></script>
        
        <!-- Prevent loading of old main.js -->
        <style>
            body {
                min-height: 100vh;
                background-color: var(--ColorLightGray, #f5f5f5);
            }
        </style>
        
        <!-- Favicons -->
        <link rel="apple-touch-icon" sizes="120x120" href="/_resources/app/client/icons/apple-touch-icon_120.png" />
        <link rel="apple-touch-icon" sizes="180x180" href="/_resources/app/client/icons/apple-touch-icon_180.png" />
        <link rel="mask-icon" href="/_resources/app/client/icons/ToTeam-safari-pinned-tab.svg" color="#4E9DAE" />
        <link rel="icon" type="image/png" sizes="128x128" href="/_resources/app/client/icons/ToTeam-Favicon-x128.png" />
        <link rel="icon" type="image/png" sizes="64x64" href="/_resources/app/client/icons/ToTeam-Favicon-x64.png" />
        <link rel="icon" type="image/png" sizes="32x32" href="/_resources/app/client/icons/ToTeam-Favicon-x32.png" />
        <link rel="icon" type="image/png" sizes="16x16" href="/_resources/app/client/icons/ToTeam-Favicon-x16.png" />
        
        <!-- PWA Meta-Tags -->
        <meta name="application-name" content="ToTeam" />
        <meta name="theme-color" content="#4E9DAE" />
        <meta name="mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-status-bar-style" content="default" />
        <meta name="apple-mobile-web-app-title" content="ToTeam" />
        <meta name="description" content="Team- und Event-Management als PWA" />
    </head>
    <body>
        <!-- Vue App Mount Point -->
        <div id="app"></div>
        
        <!-- Service Worker Registration -->
        <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/service-worker.js')
                    .then(function(registration) {
                        console.log('ServiceWorker registered:', registration);
                    })
                    .catch(function(error) {
                        console.log('ServiceWorker registration failed:', error);
                    });
            });
        }
        </script>
    </body>
</html>
