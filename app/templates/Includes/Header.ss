<header>
    <div class="header_nav">
        <a href="" class="nav_brand">
            <img src="_resources/app/client/icons/ToTeam-safari-pinned-tab.svg">
        </a>
        <div class="nav_menu">
            <% loop $Menu(1) %>
                <% if $MenuPosition == "main" %>
                    <a href="$Link" class="nav_link<% if $LinkOrSection == "section" %> nav_link--active<% end_if %>">
                        <div class="nav_icon">
                            <% if $PageIcon %>
                                <img src="$PageIcon.Url" alt="$MenuTitle Icon" class="nav_icon_image">
                            <% end_if %>
                        </div>
                        <p class="nav_title">$MenuTitle</p>
                    </a>
                <% end_if %>
            <% end_loop %>
        </div>
        <div class="nav_button" data-behaviour="toggle-menu">
            <div class="bar1"></div>
            <div class="bar2"></div>
            <div class="bar3"></div>
            <div class="bar4"></div>
        </div>
    </div>
</header>
