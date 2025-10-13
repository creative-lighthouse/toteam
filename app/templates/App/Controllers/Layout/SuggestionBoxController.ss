<div class="section section--SuggestionPage">
    <div class="section_content">
        <div class="section_intro">
            <h1>Kummerkasten</h1>
            <p>Hier kannst du Kritik, Anmerkungen oder Probleme ansprechen. Wenn du willst, kannst du dabei auch anonym bleiben oder eine betreffende Person auswählen</p>
        </div>
        <div class="section_addnew">
            <h2>Neuen Eintrag hinzufügen</h2>
            $SuggestionBoxForm
        </div>
        <div class="section_newsuggestions">
            <h2>Neue Einträge<a href="/suggestionbox/markallasseen/$ID" class="button button--small btn_markall">Alle als gelesen markieren</a></h2>
            <% if $NewSuggestions %>
                <% loop $NewSuggestions %>
                    <div class="suggestion">
                        <p class="recipient_note">
                            <% if $HasRecipient %>
                                <b>An dich</b>
                            <% else %>
                                <b>An alle</b>
                            <% end_if %>
                            <% if $IsAnonymous %>
                                von <i>Anonym</i>
                            <% else %>
                                von <i>$Sender.Name</i>
                            <% end_if %>
                        </p>
                        <h3>$Title</h3>
                        <p>$Description</p>
                        <a href="/suggestionbox/markasseen/$ID" class="button button--small btn_markone">Als gelesen markieren</a>
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
                    <div class="suggestion <% if $HasRecipient %>suggestion--personal<% end_if %>">
                        <p class="recipient_note">
                            <% if $HasRecipient %>
                                <b>An dich</b>
                            <% else %>
                                <b>An alle</b>
                            <% end_if %>
                            <% if $IsAnonymous %>
                                von <i>Anonym</i>
                            <% else %>
                                von <i>$Sender.Name</i>
                            <% end_if %>
                        </p>
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
