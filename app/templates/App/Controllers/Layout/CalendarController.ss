<div class="section section--CalendarPage">
    <% include IntroBar Title="Kalender", Description="Hier kannst du deine Termine und Veranstaltungen verwalten, sowie deine Verfügbarkeiten eingeben" %>
    <div class="section_content">
        <div class="calendar-header">
            <a class="button" href="$LinkToPreviousMonth"><</a>
            <h2 class="hl2">$CurrentMonthTitle</h2>
            <a class="button" href="$LinkToNextMonth">></a>
        </div>
        $Calendar
        <div class="calendar_actions">
            <% if $ICSLink %>
                <div class="copy-container">
                    <button type="button" class="button copy-btn" data-copy-target="$ICSLink">
                        Link für externe Kalender kopieren
                    </button>
                    <span class="copy-feedback" style="display:none; color:green;">✓ Link kopiert</span>
                </div>
            <% end_if %>
            <div class="calendar_filter">
                <select id="calendar-filter-select" onchange="location = this.value;">
                    <option value="/calendar" <% if not $FilterType %>selected<% end_if %>>Alle Veranstaltungen</option>
                    <option value="/calendar?filter=my" <% if $FilterType == 'my' %>selected<% end_if %>>Meine Veranstaltungen</option>
                    <option value="/calendar?filter=suggested" <% if $FilterType == 'suggested' %>selected<% end_if %>>Vorgeschlagene Veranstaltungen</option>
                </select>
            </div>
        </div>
    </div>
</div>
