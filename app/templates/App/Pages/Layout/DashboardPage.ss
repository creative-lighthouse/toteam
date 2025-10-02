<div class="section section--DashboardPage">
    <div class="section_content">
        <div class="section_infobox">
            <h1 class="hl1">Dashboard</h1>
            <p>Willkommen, $User.FirstName $User.Surname!</p>
        </div>
        <div class="section_infobox">
            <h2 class="hl2">Deine anstehenden Termine</h2>
            <ul class="infobox_list">
                <% loop $UpcomingEventDays %>
                    <li class="<% if $Type = 'Accept' %>accepted<% else_if $Type = 'Maybe' %>maybe<% end_if %>"><a href="$Parent.Link">$Parent.Title - <i>$Parent.RenderDateWithTime</i></a></li>
                <% end_loop %>
            </ul>
            <a href="$ParticipationPage.Link" class="button button--arrow">Weitere Verfügbarkeiten eingeben</a>
        </div>
        <div class="section_infobox">
            <h2 class="hl2">Deine zuletzt zugewiesenen Aufgaben</h2>
            <ul class="infobox_list">
                <% loop $LatestTasks %>
                    <li>$Title</li>
                <% end_loop %>
                <div class="coming_soon">
                    <kbd>coming soon...</kbd>
                </div>
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
