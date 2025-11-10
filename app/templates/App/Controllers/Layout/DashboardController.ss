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

        <% if $CurrentUser.TodaysParticipations.Count > 0 %>
            <div class="section_infobox">
                <h2 class="hl2">Das steht heute an:</h2>
                <ul class="infobox_list agenda">
                    <% loop $CurrentUser.TodaysParticipations %>
                        <li class="agenda--item">
                            <h3 class="hl3">$Parent.Title</h3>
                            <p><b>Uhrzeit:</b> $Parent.RenderTime</p>
                            <p><b>Ort:</b> $Parent.Location</p>
                            <% if $Parent.Description %>
                                <p>$Parent.Description</p>
                            <% end_if %>
                            <% if $Parent.Agenda %>
                                <h4 class="hl4">Tagesplan</h4>
                                <ul class="list--agenda-points">
                                    <% loop $Parent.Agenda %>
                                        <% if $Type == "AgendaPoint" %>
                                            <li>
                                                <p><strong>$Item.RenderTime:</strong> $Item.Title</p>
                                                <div>
                                                    $Item.Description
                                                </div>
                                            </li>
                                        <% else_if $Type == "Meal" %>
                                            <li>
                                                <p><strong>$Item.RenderTime:</strong> $Item.Title</p>
                                                <% if $Item.Foods %>
                                                    <% loop $Item.Foods %>
                                                        <div>
                                                            <p>- $Title</p>
                                                        </div>
                                                    <% end_loop %>
                                                <% end_if %>
                                            </li>
                                        <% end_if %>
                                    <% end_loop %>
                                </ul>
                            <% end_if %>
                            <a class="button" href="$Parent.Link">Termindetails anzeigen</a>
                        </li>
                    <% end_loop %>
                </ul>
            </div>
        <% end_if %>

        <div class="section_infobox">
            <h2 class="hl2">Deine anstehenden Termine:</h2>
            <ul class="infobox_list">
                <% if $UpcomingEventDays %>
                    <% loop $UpcomingEventDays %>
                        <li class="<% if $Type = 'Accept' %>accepted<% else_if $Type = 'Maybe' %>maybe<% end_if %>"><a href="$Parent.Link">$Parent.Title - <i>$Parent.RenderDateWithTime</i></a></li>
                    <% end_loop %>
                <% else %>
                    <li><a href="calendar">Keine anstehenden Termine</a></li>
                <% end_if %>
            </ul>
        </div>

        <div class="section_infobox">
            <h2 class="hl2">Termine ohne deine Rückmeldung:</h2>
            <ul class="infobox_list">
                <% if $EventDaysWithoutFeedback %>
                    <% loop $EventDaysWithoutFeedback %>
                        <li class="käse"><a href="$Link">$Title - <i>$RenderDateWithTime</i></a></li>
                        <%-- <li class="<% if $Type = 'Accept' %>accepted<% else_if $Type = 'Maybe' %>maybe<% end_if %>"><a href="$Parent.Link">$Parent.Title - <i>$Parent.RenderDateWithTime</i></a></li> --%>
                    <% end_loop %>
                <% else %>
                    <li><a href="calendar">Keine ausstehenden Umfragen</a></li>
                <% end_if %>
            </ul>
        </div>

        <% if $MealsWithoutFoodSupplied %>
            <div class="section_infobox">
                <h2 class="hl2">Es gibt aktuell Mahlzeiten ohne Gerichte:</h2>
                <p>Kannst du ggf. zu einer dieser Mahlzeiten etwas zum Essen beisteuern?</p>
                <ul class="infobox_list mealswithoutfood">
                    <% loop $MealsWithoutFoodSupplied %>
                        <li class="mealswithoutfood--item">
                            <p class="list--mealswithoutfood-title"><b>$Children.First.Parent.Title ($Children.First.Parent.RenderDate)</b></p>
                            <ul class="list--mealswithoutfood-meals">
                                <% loop $Children %>
                                    <li><a href="$DetailsLink">$RenderTime - $Title</a></li>
                                <% end_loop %>
                            </ul>
                        </li>
                    <% end_loop %>
                </ul>
            </div>
        <% end_if %>
    </div>
</div>
