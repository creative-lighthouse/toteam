<div class="section section--NoticesPage">
    <div class="section_content">
        <div class="section_intro">
            <h1 class="hl1">Essensplanung</h1>
            <p>Hier findest du alles zum Thema essen. Biete Essen für die Mahlzeiten an oder schaue dir an welche Mahlzeiten geplant sind</p>
        </div>

        <kbd>IN ARBEIT</kbd>

        <div class="section_mealswithoutoffers">
            <% if $MealsWithoutOffers %>
                <% loop $MealsWithoutOffers %>
                    <% include MealSummary %>
                <% end_loop %>
            <% else %>
                <p>Es sind aktuell keine Mahlzeiten ohne Essensangebote vorhanden.</p>
            <% end_if %>
        </div>
    </div>
</div>
