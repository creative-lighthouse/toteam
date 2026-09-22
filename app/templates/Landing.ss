<!doctype html>
<html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>ToTeam</title>

        <!-- Vite Client for HMR in development -->
        $ViteClient.RAW

        <link rel="stylesheet" href="$Vite('app/client/src/scss/main.scss')">
        <script type="module" src="$Vite('app/client/src/vue/landing.js')"></script>

        <!-- Favicons -->
        <link rel="apple-touch-icon" sizes="120x120" href="/_resources/app/client/icons/apple-touch-icon_120.png" />
        <link rel="apple-touch-icon" sizes="180x180" href="/_resources/app/client/icons/apple-touch-icon_180.png" />
        <link rel="mask-icon" href="/_resources/app/client/icons/ToTeam-safari-pinned-tab.svg" color="#4E9DAE" />
        <link rel="icon" type="image/png" sizes="128x128" href="/_resources/app/client/icons/ToTeam-Favicon-x128.png" />
        <link rel="icon" type="image/png" sizes="64x64" href="/_resources/app/client/icons/ToTeam-Favicon-x64.png" />
        <link rel="icon" type="image/png" sizes="32x32" href="/_resources/app/client/icons/ToTeam-Favicon-x32.png" />
        <link rel="icon" type="image/png" sizes="16x16" href="/_resources/app/client/icons/ToTeam-Favicon-x16.png" />
    </head>
    <body>
        <div id="app"></div>
        <script>window.__TOTEAM_LOGGED_IN__ = <% if $LoggedIn %>true<% else %>false<% end_if %>;</script>
    </body>
</html>
