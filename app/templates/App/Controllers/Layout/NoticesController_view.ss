<div class="section section--NoticesPage">
    <div class="section_content">
        <a href="$Link" class="back-link">← Zurück zur Übersicht</a>
        <% with $Notice %>
            <h2>$Title</h2>
            <div class="notice-text">
                $LongText
            </div>
        <% end_with %>
    </div>
</div>
