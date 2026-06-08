<header class="area_header">
    <div class="section_logo">
        <a href="/"><img src="/_resources/app/client/icons/ToTeam-Logo-x512.png" alt="ToTeam Logo"></a>
    </div>
    <div class="section_menu">
        <nav>
            <ul class="main_menu">
                <% loop $Menu %>
                    <li><a href="$Link" class="<% if $LinkingMode == 'current' %>current<% end_if %>">$Title</a></li>
                <% end_loop %>
                <% if $CurrentUser %>
                    <li><a href="/app/dashboard">App starten</a></li>
                <% else %>
                    <li><a href="/app/login">Login</a></li>
                <% end_if %>
            </ul>
        </nav>
    </div>
</header>
