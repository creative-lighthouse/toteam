<div class="section section--SuggestionPage">
    <div class="section_content">
        <div class="section_header">
            <h1>$Title</h1>
            <p>Hier kannst du Kritik, Anmerkungen oder Probleme ansprechen. Dein Eintrag wird vertraulich behandelt und nur von den Administratoren, bzw. dem betroffenen Mitglied eingesehen. Du kannst auch anonym bleiben, wenn du das möchtest.</p>
        </div>
        <div class="section_addnew">
            <h2>Neuen Eintrag hinzufügen</h2>
            $SuggestionBoxForm
        </div>
        <div class="section_newsuggestions">
            <h2>Neue Einträge</h2>
            <% if $NewSuggestions %>
                <% loop $NewSuggestions %>
                    <div class="suggestion">
                        <h3>$Title</h3>
                        <p>$Description</p>
                    </div>
                <% end_loop %>
            <% else %>
                <p>Keine neuen Einträge für dich vorhanden.</p>
            <% end_if %>
        </div>
        <div class="section_oldsuggestions">
            <h2>Ältere Einträge</h2>
            <% if $OldSuggestions %>
                <% loop $OldSuggestions %>
                    <div class="suggestion">
                        <h3>$Title</h3>
                        <p>$Description</p>
                    </div>
                <% end_loop %>
            <% else %>
                <p>Keine älteren Einträge für dich vorhanden.</p>
            <% end_if %>
        </div>
    </div>
</div>
