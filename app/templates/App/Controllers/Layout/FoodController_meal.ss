<div class="section section--NoticesPage">
    <div class="section_content">
        <div class="section_intro">
            <h1 class="hl1">Essensplanung</h1>
            <p>Hier findest du alles zum Thema essen. Biete Essen für die Mahlzeiten an oder schaue dir an welche Mahlzeiten geplant sind</p>
        </div>

        <a href="food" class="">← Zurück zur Übersicht</a>

        <% with $Meal %>
            <div class="section_mealdetails">
                <h2 class="hl2">$Parent.Title - $Title am $Parent.RenderDate um $RenderTime Uhr</h2>

                <h3 class="hl3">Angebotene Gerichte</h3>
                <ul class="section_mealdetails_foodlist">
                    <% if $Foods.Count > 0 %>
                        <% loop $Foods %>
                            <li class="mealdetails_foodlist_item">
                                <span class="mealdetails_foodlist_item_title"><b>$Title</b> <% if $FoodPreference %> - <i>$RenderFoodPreference</i><% end_if %></span>
                                <% if $Notes %>
                                    <p class="mealdetails_foodlist_item_notes">$Notes</p>
                                <% end_if %>
                                <% if $Allergies.Count > 0 %>
                                    <p class="mealdetails_foodlist_item_allergies">Kann folgende Allergien auslösen: $RenderAllergies</p>
                                <% end_if %>
                                <p class="mealdetails_foodlist_item_supplier">Anbieter: $Supplier.Name</p>
                                <p>Status: $RenderStatus</p>
                            </li>
                        <% end_loop %>
                    <% else %>
                        <p>Es sind aktuell noch keine Gerichte für diese Mahlzeit angeboten worden.</p>
                    <% end_if %>
            </div>
        <% end_with %>
    </div>
</div>
