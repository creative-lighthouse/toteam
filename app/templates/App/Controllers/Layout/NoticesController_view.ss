<div class="section section--NoticesPage">
    <div class="section_content">
        <% include IntroBar Title="Ankündigungen", Description="Hier findest du alle aktuellen Ankündigungen. Schau regelmäßig vorbei, um nichts zu verpassen!" %>

        <% with $Notice %>
            <h2>$Title</h2>
            <div class="notice-text">
                $LongText
            </div>
        <% end_with %>
    </div>
</div>
