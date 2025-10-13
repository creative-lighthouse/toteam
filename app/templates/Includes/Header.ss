<header>
    <div class="header_nav">
        <a href="/dashboard" class="nav_brand">
            <img src="_resources/app/client/icons/ToTeam-safari-pinned-tab.svg" alt="ToTeam Logo - Zum Dashboard">
        </a>
        <ul class="nav_menu">
            <% if $CurrentUser %>
                <li>
                    <a href="/notices" class="nav_link<% if $IsCurrentRoute(notices) %> nav_link--active<% end_if %>">
                        <div class="nav_icon nav_icon--notices">
                            <img src="_resources/app/client/icons/totems/nachrichten_totem.png" alt="Nachrichten Icon" class="profile_image">
                        </div>
                        <p class="nav_title">Ankündigungen</p>
                    </a>
                </li>
                <li>
                    <a href="/calendar" class="nav_link<% if $IsCurrentRoute(calendar) %> nav_link--active<% end_if %>">
                        <div class="nav_icon nav_icon--calendar">
                            <img src="_resources/app/client/icons/totems/kalender_totem.png" alt="Kalender Icon" class="profile_image">
                        </div>
                        <p class="nav_title">Kalender</p>
                    </a>
                </li>
                <li>
                    <a href="/suggestionbox" class="nav_link<% if $IsCurrentRoute(suggestionbox) %> nav_link--active<% end_if %>">
                        <div class="nav_icon nav_icon--suggestions">
                            <img src="_resources/app/client/icons/totems/kummerkasten_totem.png" alt="Kummerkasten Icon" class="profile_image">
                        </div>
                        <p class="nav_title">Kummerkasten</p>
                    </a>
                </li>
                <li>
                    <a href="/profile" class="nav_link<% if $IsCurrentRoute(profile) %> nav_link--active<% end_if %>">
                        <div class="nav_icon nav_icon--profile">
                            <% with $CurrentUser %>
                                <% if $ProfileImage %>
                                    <img src="$ProfileImage.FitMax(100, 100).URL" alt="Profilbild von $FirstName $Surname" class="profile_image">
                                <% else %>
                                    <img src="$Gravatar" alt="Standard Profilbild" class="profile_image">
                                <% end_if %>
                            <% end_with %>
                        </div>
                        <p class="nav_title">$CurrentUser.FirstName</p>
                    </a>
                </li>
            <% end_if %>
        </div>
        <div class="nav_button" data-behaviour="toggle-menu">
            <div class="bar1"></div>
            <div class="bar2"></div>
            <div class="bar3"></div>
            <div class="bar4"></div>
        </div>
    </div>
</header>
