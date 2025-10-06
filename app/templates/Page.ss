<!doctype html>
<html lang="de">
    <head>
        <% base_tag %>
        $MetaTags(false)
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta charset="utf-8">
        <title>$Title - $SiteConfig.Title</title>
        $ViteClient.RAW
        <link rel="stylesheet" href="$Vite('app/client/src/scss/main.scss')">

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

        <link rel="manifest" href="site.webmanifest" />

        <link rel="apple-touch-icon" sizes="120x120" href="_resources/app/client/icons/apple-touch-icon_120.png" />
        <link rel="apple-touch-icon" sizes="180x180" href="_resources/app/client/icons/apple-touch-icon_180.png" />
        <link rel="mask-icon" href="_resources/app/client/icons/ToTeam-safari-pinned-tab.svg" color="#4E9DAE" />
        <meta name="msapplication-TileColor" content="#d54f27" />
        <link rel="apple-touch-icon" sizes="180x180" href="_resources/app/client/icons/ToTeam-safari-pinned-tab.svg" />
        <link rel="icon" type="image/png" sizes="128x128" href="_resources/app/client/icons/ToTeam-Favicon-x128.png" />
        <link rel="icon" type="image/png" sizes="64x64" href="_resources/app/client/icons/ToTeam-Favicon-x64.png" />
        <link rel="icon" type="image/png" sizes="32x32" href="_resources/app/client/icons/ToTeam-Favicon-x32.png" />
        <link rel="icon" type="image/png" sizes="16x16" href="_resources/app/client/icons/ToTeam-Favicon-x16.png" />
    </head>
    <body>
        <div class="area_header">
            <% include Header %>
        </div>
        <main class="area_content main">
                $Layout
        </main>
        <div class="area_footer">
            <p class="footer_note"><i>ToTeam v0.1.1</i> <kbd>BETA</kbd></p>
        </div>
        <script type="module" src="$Vite('app/client/src/js/main.js')"></script>
    </body>
</html>
