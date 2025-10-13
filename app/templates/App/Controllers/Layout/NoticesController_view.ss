<div class="section section--NoticesPage">
    <div class="section_content">
        <div class="section_intro">
            <h1 class="hl1">Ankündigungen</h1>
            <p>Hier findest du alle aktuellen Ankündigungen. Schau regelmäßig vorbei, um nichts zu verpassen!</p>
        </div>

        <a href="$Link" class="back-link">← Zurück zur Übersicht</a>
        <% with $Notice %>
            <h2>$Title</h2>
            <div class="notice-text">
                $LongText
            </div>
        <% end_with %>
    </div>
</div>
