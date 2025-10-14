<header>
    <div class="header_nav">
        <ul class="nav_menu">
            <% if $CurrentUser %>
                <li>
                    <a href="/dashboard" class="nav_link<% if $IsCurrentRoute(dashboard) %> nav_link--active<% end_if %>">
                        <div class="nav_icon nav_icon--dashboard">
                            <img src="_resources/app/client/icons/ToTeam-safari-pinned-tab.svg" alt="ToTeam Logo - Zum Dashboard">
                        </div>
                        <p class="nav_title">Dashboard</p>
                    </a>
                </li>
                <li>
                    <a href="/notices" class="nav_link<% if $IsCurrentRoute(notices) %> nav_link--active<% end_if %>">
                        <div class="nav_icon nav_icon--notices">
                            <img src="_resources/app/client/icons/totems/nachrichten_totem.png" alt="Nachrichten Icon" class="nav_image">
                        </div>
                        <p class="nav_title">Wichtiges</p>
                    </a>
                </li>
                <li>
                    <a href="/calendar" class="nav_link<% if $IsCurrentRoute(calendar) %> nav_link--active<% end_if %>">
                        <div class="nav_icon nav_icon--calendar">
                            <img src="_resources/app/client/icons/totems/kalender_totem.png" alt="Kalender Icon" class="nav_image">
                        </div>
                        <p class="nav_title">Kalender</p>
                    </a>
                </li>
                <li>
                    <a href="/food" class="nav_link<% if $IsCurrentRoute(food) %> nav_link--active<% end_if %>">
                        <div class="nav_icon nav_icon--food">
                            <img src="_resources/app/client/icons/totems/essen_totem.png" alt="Essen Icon" class="nav_image">
                        </div>
                        <p class="nav_title">Essen</p>
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
    </div>
</header>
