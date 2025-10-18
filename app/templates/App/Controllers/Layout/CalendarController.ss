<div class="section section--CalendarPage">
    <% include IntroBar Title="Kalender", Description="Hier kannst du deine Termine und Veranstaltungen verwalten, sowie deine Verfügbarkeiten eingeben" %>
    <div class="section_content">
        <div class="calendar-header">
            <a class="button" href="$LinkToPreviousMonth"><</a>
            <h2 class="hl2">$CurrentMonthTitle</h2>
            <a class="button" href="$LinkToNextMonth">></a>
        </div>
        $Calendar
        <% if $ICSLink %>
            <div class="copy-container">
                <button type="button" class="button copy-btn" data-copy-target="$ICSLink">
                    Link für externe Kalender kopieren
                </button>
                <span class="copy-feedback" style="display:none; color:green;">✓ Link kopiert</span>
            </div>
        <% end_if %>
    </div>
</div>
