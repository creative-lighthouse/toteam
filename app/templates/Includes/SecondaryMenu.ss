<div class="secondarynav_wrap">
    <div class="secondarynav">
        <div class="nav_top">
            <img class="nav_logo" src="_resources/app/client/icons/totems/dashboard_totem.png" alt="ToTeam Logo - Zum Dashboard">
            <h2 class="nav_title">ToTeam - $SiteConfig.Title</h2>
        </div>
        <ul class="secondary_menu">
            <li>
                <a href="/food" class="nav_link<% if $IsCurrentRoute(food) %> nav_link--active<% end_if %>">
                    <div class="nav_icon">
                        <img src="_resources/app/client/icons/totems/essen_totem.png" alt="Essen Icon" class="nav_image">
                    </div>
                    <p class="nav_title">Essen</p>
                </a>
            </li>
            <li>
                <a href="/suggestionbox" class="nav_link<% if $IsCurrentRoute(suggestionbox) %> nav_link--active<% end_if %>">
                    <div class="nav_icon">
                        <img src="_resources/app/client/icons/totems/kummerkasten_totem.png" alt="Kummerkasten Icon" class="profile_image">
                    </div>
                    <p class="nav_title">Kummerkasten</p>
                </a>
            </li>
            <li>
                <a href="/links" class="nav_link<% if $IsCurrentRoute(links) %> nav_link--active<% end_if %>">
                    <div class="nav_icon">
                        <img src="_resources/app/client/icons/totems/downloads_totem.png" alt="Downloads Icon" class="profile_image">
                    </div>
                    <p class="nav_title">Links & Downloads</p>
                </a>
            </li>
        </ul>
        <p class="version_note"><i>ToTeam v0.1.3</i> <kbd>BETA</kbd></p>
        <div class="nav_profile_wrap">
            <a href="/profile" class="nav_profile">
                <div class="nav_icon nav_icon--profile">
                    <% with $CurrentUser %>
                        <% if $ProfileImage %>
                            <img src="$ProfileImage.FillMax(100, 100).URL" alt="Profilbild von $FirstName $Surname" class="profile_image">
                        <% else %>
                            <img src="$Gravatar" alt="Standard Profilbild" class="profile_image">
                        <% end_if %>
                    <% end_with %>
                </div>
                <div class="nav_text">
                    <p class="nav_title">$CurrentUser.FirstName</p>
                    <p class="nav_subtitle">Profil ansehen</p>
                </div>
            </a>
            <a href="$LogoutURL" class="nav_logout" title="Abmelden">
                <div class="nav_icon nav_icon--logout">
                    <img src="_resources/app/client/icons/actions/action_logout.svg" alt="Abmelden Icon" class="logout_image">
                </div>
            </a>
        </div>
    </div>
</div>
