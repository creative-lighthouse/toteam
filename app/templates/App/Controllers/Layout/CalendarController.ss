<div class="section section--CalendarPage">
    <div class="section_intro">
            <h1>Kalender</h1>
            <p>Hier kannst du deine Termine und Veranstaltungen verwalten, sowie deine Verfügbarkeiten eingeben</p>
        </div>
    <div class="calendar-header">
        <a href="$LinkToPreviousMonth"><</a>
        <h2>$CurrentMonthTitle</h2>
        <a href="$LinkToNextMonth">></a>
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
