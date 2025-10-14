<div class="calendar">
    <div class="days">
        <div class="day_name">
            MO
        </div>
        <div class="day_name">
            DI
        </div>
        <div class="day_name">
            MI
        </div>
        <div class="day_name">
            DO
        </div>
        <div class="day_name">
            FR
        </div>
        <div class="day_name">
            SA
        </div>
        <div class="day_name">
            SO
        </div>

        <% loop $CalendarDays %>
            <div class="day_num<% if not $IsCurrentMonth %> ignore<% end_if %><% if $IsSelected %> selected<% end_if %> <% if not $IsCurrentMonth %>not-current-month<% end_if %>">
                <% if $IsCurrentMonth %><p class="day_number">$Number</p><% end_if %>
                <% loop $EventDays %>
                    <div class="event event--color-$ParticipationOfCurrentUser.Type" onclick="document.getElementById('event-modal-{$ID}').showModal()">
                        <p class="event_title">$Title</p>
                    </div>
                    $Top.CurrentEventDayID
                    <dialog id="event-modal-{$ID}" class="event-modal" <% if $ID == $Top.CurrentEventDayID %>data-autoopen="true"<% end_if %>>
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
                            <div class="event-participation">
                                <h4 class="event-participation_title">Deine Verfügbarkeit</h4>
                                <form class="event-response-actions" method="post" action="/calendar/changeParticipation/{$ID}">
                                    <fieldset class="fieldset-availability">
                                        <input type="hidden" name="csrf_token" value="$CSRFToken">
                                        <button type="submit" name="response" value="Accept" class="event-response-button event-response-accept <% if $ParticipationOfCurrentUser.Type == 'Accept' %>selected<% else_if $ParticipationOfCurrentUser %>unselected<% end_if %>">Dabei</button>
                                        <button type="submit" name="response" value="Maybe" class="event-response-button event-response-maybe <% if $ParticipationOfCurrentUser.Type == 'Maybe' %>selected<% else_if $ParticipationOfCurrentUser %>unselected<% end_if %>">Vielleicht</button>
                                        <button type="submit" name="response" value="Decline" class="event-response-button event-response-decline <% if $ParticipationOfCurrentUser.Type == 'Decline' %>selected<% else_if $ParticipationOfCurrentUser %>unselected<% end_if %>">Nicht dabei</button>
                                    </fieldset>
                                </form>
                                <% if $ParticipationOfCurrentUser.Type == 'Accept' || $ParticipationOfCurrentUser.Type == 'Maybe' %>
                                    <hr>
                                    <form class="event-response-actions" method="post" action="/calendar/changeParticipationTime/{$ID}">
                                        <fieldset class="fieldset-update-time">
                                            <p>Von</p>
                                            <input type="time" name="timestart" value="$ParticipationOfCurrentUser.TimeStart" step="900" class="event-response-arrival-time">
                                            <p>bis</p>
                                            <input type="time" name="timeend" value="$ParticipationOfCurrentUser.TimeEnd" step="900" class="event-response-departure-time">
                                            <button type="submit" name="response" value="UpdateTime" class="event-response-button event-response-update-time">Zeiten aktualisieren</button>
                                        </fieldset>
                                    </form>
                                <% end_if %>
                            </div>
                            <% if $Meals.Count > 0 %>
                                <hr>
                                <div class="event-meals">
                                    <h4>Willst du mitessen?</h4>
                                    <div class="meals-list">
                                        <% loop $Meals %>
                                            <div class="meal">
                                                <span class="meal-title">$RenderTime - $Title</span>
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
                                <hr>
                                <div class="event-participants">
                                    <h4>Teilnehmer:</h4>
                                    <div class="participants-list">
                                        <% loop $GroupedParticipations.GroupedBy('Type') %>
                                            <h5 class="participant-group_title">$Children.First.RenderType</h5>
                                            <% loop $Children %>
                                                <div class="participant participant--status-$Type">
                                                    <span class="participant-name">$Member.Name</span>
                                                    <% if $RenderTime != $Up.Up.RenderTime && $Up.Type == "Accept" %>
                                                        <span class="participant-status">($RenderTime)</span>
                                                    <% end_if %>
                                                </div>
                                            <% end_loop %>
                                        <% end_loop %>
                                </div>
                            <% end_if %>
                        </div>
                    </dialog>
                <% end_loop %>
            </div>
        <% end_loop %>
    </div>
</div>
