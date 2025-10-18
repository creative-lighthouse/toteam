<div class="section section--DashboardPage">
<% include IntroBar Title="Dashboard", Description="Willkommen auf deinem ToTeam Dashboard! Hier findest du eine Übersicht über deine anstehenden Termine und heutigen Mahlzeiten." %>
    <div class="section_content">
        <div class="section_infobox infobox--welcome">
            <p class="welcome_text">Willkommen zurück, <b>$User.FirstName!</b></p>
            <div class="welcome_profileimage">
                <% with $CurrentUser %>
                    <% if $ProfileImage %>
                        <img src="$ProfileImage.FillMax(300, 300).URL" alt="Profilbild von $FirstName $Surname" class="profile_image">
                    <% else %>
                        <img src="$Gravatar" alt="Standard Profilbild" class="profile_image">
                    <% end_if %>
                <% end_with %>
            </div>
        </div>
        <div class="section_infobox">
            <h2 class="hl2">Deine anstehenden Termine</h2>
            <ul class="infobox_list">
                <% if $UpcomingEventDays %>
                    <% loop $UpcomingEventDays %>
                        <li class="<% if $Type = 'Accept' %>accepted<% else_if $Type = 'Maybe' %>maybe<% end_if %>"><a href="$Parent.Link">$Parent.Title - <i>$Parent.RenderDateWithTime</i></a></li>
                    <% end_loop %>
                <% else %>
                    <li><p>Keine anstehenden Termine</p></li>
                <% end_if %>
            </ul>
        </div>
        <div class="section_infobox">
            <h2 class="hl2">Heutige Mahlzeiten</h2>
            <ul class="infobox_list list--meals">
                <% if $MealsToday %>
                    <% loop $MealsToday %>
                        <li>
                            <h3 class="hl3">$Parent.Title <span class="meal_time">- $Parent.RenderTime</span></h3>
                            <% if $Parent.Foods %>
                                <ul class="list--foods">
                                    <% loop $Parent.Foods %>
                                        <li>
                                            <p>$Title <span>von $RenderSupplier</span></p>
                                        </li>
                                    <% end_loop %>
                                </ul>
                            <% else %>
                                <p>Noch kein Gericht eingetragen</p>
                            <% end_if %>
                        </li>
                    <% end_loop %>
                <% else %>
                    <li>Du hast heute keine Mahlzeiten</li>
                <% end_if %>
            </ul>
        </div>
    </div>
</div>
