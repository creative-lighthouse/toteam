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
                    <li><a href="$Parent.Link">$Parent.Title - <i>$Parent.RenderDateWithTime</i></a></li>
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
    </div>
</div>
