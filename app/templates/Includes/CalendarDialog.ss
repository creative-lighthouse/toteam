
<dialog id="event-modal-{$ID}" class="event-modal" <% if $ID == $CurrentEventDayID %>data-autoopen="true"<% end_if %>>
    <button class="dialog-close" onclick="document.getElementById('event-modal-{$ID}').close()">×</button>
    <div class="dialog-header">
        <h2 class="dialog-title">$Parent.Title</h2>
        <h3 class="dialog-title">$Title</h3>
        <% if $Location %>
            <p class="dialog-date">$RenderDateWithTime, $Location</p>
        <% else %>
            <p class="dialog-date">$RenderDateWithTime</p>
        <% end_if %>
    </div>
    <div class="dialog-content">
        <% if $Description %>
            <details class="dialog-infobox">
            <summary class="dialog-summary dialog-headline">Details</summary>
            <p class="dialog-summary-content">$Description</p>
            </details>
        <% end_if %>
        <div class="dialog-infobox infobox--participation">
            <p class="dialog-headline">Bist du dabei?</p>
            <form class="event-response-actions js-participation-form" method="post" action="/calendar/changeParticipation/{$ID}">
                <fieldset class="fieldset-availability">
                    <input type="hidden" name="csrf_token" value="$CSRFToken">
                    <button type="button" name="response" value="Accept" class="event-response-button event-response-accept <% if $ParticipationOfCurrentUser.Type == 'Accept' %>selected<% else_if $ParticipationOfCurrentUser %>unselected<% end_if %>">Ja</button>
                    <button type="button" name="response" value="Maybe" class="event-response-button event-response-maybe <% if $ParticipationOfCurrentUser.Type == 'Maybe' %>selected<% else_if $ParticipationOfCurrentUser %>unselected<% end_if %>">Vielleicht</button>
                    <button type="button" name="response" value="Decline" class="event-response-button event-response-decline <% if $ParticipationOfCurrentUser.Type == 'Decline' %>selected<% else_if $ParticipationOfCurrentUser %>unselected<% end_if %>">Nein</button>
                </fieldset>
            </form>
            <div id="participation-time-form-container-{$ID}" style="display:<% if $ParticipationOfCurrentUser.Type == 'Decline' %>none<% else %>block<% end_if %>">
                <form class="event-response-actions" method="post" action="/calendar/changeParticipationTime/{$ID}">
                    <fieldset class="fieldset-update-time">
                        <p>Von</p>
                        <input type="time" name="timestart" value="$ParticipationOfCurrentUser.TimeStart" step="900" class="event-response-arrival-time">
                        <p>bis</p>
                        <input type="time" name="timeend" value="$ParticipationOfCurrentUser.TimeEnd" step="900" class="event-response-departure-time">
                    </fieldset>
                </form>
            </div>
        </div>
        <% if $Meals.Count > 0 %>
            <div class="dialog-infobox infobox--meals">
                <p class="dialog-headline">Willst du mitessen?</p>
                <div class="meals-list">
                    <% loop $Meals %>
                        <div class="meal">
                            <span class="meal-title">$RenderTime - <a href="/food/meal/$ID">$Title</a></span>
                            <form class="event-response-actions" method="post" action="/calendar/changeParticipationFood/{$Up.ID}">
                                <fieldset class="fieldset-availability">
                                    <input type="hidden" name="meal" value="$ID">
                                    <button type="submit" name="response" value="Accept" class="event-response-button event-response-accept <% if $MealParticipationOfCurrentUser.Type == 'Accept' %>selected<% else_if $MealParticipationOfCurrentUser %>unselected<% end_if %>">Ja</button>
                                    <button type="submit" name="response" value="Decline" class="event-response-button event-response-decline <% if $MealParticipationOfCurrentUser.Type == 'Decline' %>selected<% else_if $MealParticipationOfCurrentUser %>unselected<% end_if %>">Nein</button>
                                </fieldset>
                            </form>
                        </div>
                    <% end_loop %>
                </div>
            </div>
        <% end_if %>
        <% if $Participations.Count > 0 %>
            <div class="dialog-infobox infobox--participants">
                <p class="dialog-headline">Teilnehmer:</p>
                <div class="participants-list">
                    <% loop $GroupedParticipations.GroupedBy('Type') %>
                        <h5 class="participant-group_title">$Children.First.RenderType</h5>
                        <% loop $Children %>
                            <div class="participant participant--status-$Type">
                                <span class="participant-name" <% if $Top.Controller.IsCurrentMember($Member.ID) %> data-me="1"<% end_if %>>$Member.Name</span>
                                <% if $RenderTime != $Up.Up.RenderTime %>
                                    <% if $Up.Type == 'Accept' || $Up.Type == 'Maybe' %>
                                        <span class="participant-status">($RenderTime)</span>
                                    <% end_if %>
                                <% end_if %>
                            </div>
                        <% end_loop %>
                    <% end_loop %>
                </div>
            </div>
        <% end_if %>
    </div>
</dialog>
