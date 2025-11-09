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
            <div class="day_num<% if not $IsCurrentMonth %> ignore<% end_if %><% if not $IsCurrentMonth %>not-current-month<% end_if %><%if $IsToday %> date-today<% end_if %>">
                <% if $IsCurrentMonth %><p class="day_number">$Number</p><% end_if %>
                <% loop $EventDays %>
                    <div class="event event--color-$ParticipationOfCurrentUser.Type event--status-$Status" onclick="document.getElementById('event-modal-{$ID}').showModal()">
                        <p class="event_title">$RenderTitle</p>
                    </div>
                    <% include CalendarDialog CurrentEventDayID=$Top.CurrentEventDayID, Controller=$Top.Controller %>
                <% end_loop %>
            </div>
        <% end_loop %>
    </div>
</div>
