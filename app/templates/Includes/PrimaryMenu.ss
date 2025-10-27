<ul class="primary_menu">
    <li>
        <a href="/dashboard" class="nav_link<% if $IsCurrentRoute(dashboard) %> nav_link--active<% end_if %>">
            <div class="nav_icon">
                <% if $IsCurrentRoute(dashboard) %>
                    <img class="nav_image" src="_resources/app/client/icons/totems/dashboard_totem.png" alt="ToTeam Logo - Zum Dashboard">
                <% else %>
                    <img class="nav_image" src="_resources/app/client/icons/totems/dashboard_totem_inactive.png" alt="ToTeam Logo - Zum Dashboard">
                <% end_if %>
            </div>
            <p class="nav_title">Dashboard</p>
        </a>
    </li>
    <li>
        <a href="/notices" class="nav_link<% if $IsCurrentRoute(notices) %> nav_link--active<% end_if %>">
            <div class="nav_icon">
                <% if $IsCurrentRoute(notices) %>
                    <img src="_resources/app/client/icons/totems/nachrichten_totem.png" alt="Nachrichten Icon" class="nav_image">
                <% else %>
                    <img src="_resources/app/client/icons/totems/nachrichten_totem_inactive.png" alt="Nachrichten Icon" class="nav_image">
                <% end_if %>
                <% if $CurrentUser.UnreadNotices.Count > 0 %>
<<<<<<< Updated upstream
                    <p class="nav_badge"><% $CurrentUser.UnreadNotices.Count %>Test</p>
=======
                    <p class="nav_badge">$CurrentUser.UnreadNotices.Count</p>
>>>>>>> Stashed changes
                <% end_if %>
            </div>
            <p class="nav_title">Wichtiges</p>
        </a>
    </li>
    <li>
        <a href="/calendar" class="nav_link<% if $IsCurrentRoute(calendar) %> nav_link--active<% end_if %>">
            <div class="nav_icon">
                <% if $IsCurrentRoute(calendar) %>
                    <img class="nav_image" src="_resources/app/client/icons/totems/kalender_totem.png" alt="Kalender Icon">
                <% else %>
                    <img class="nav_image" src="_resources/app/client/icons/totems/kalender_totem_inactive.png" alt="Kalender Icon">
                <% end_if %>
            </div>
            <p class="nav_title">Kalender</p>
        </a>
    </li>
    <li>
        <div class="nav_link">
            <div class="nav_icon">
                <div class="nav_button" data-action="toggle-secnav">
                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
            <p class="nav_title">Mehr</p>
        </div>
    </li>
</ul>
