<div class="section section--FoodPageDetails">
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
                <ul class="section_foodlist">
                    <% if $Foods.Count > 0 %>
                        <% loop $Foods.Sort("Status") %>
                            <li class="fooditem fooditem--$Status">
                                <% if $Status == "Accepted" %>
                                    <img class="fooditem_statusicon" src="../_resources/app/client/icons/states/state_accepted.svg" alt="Akzeptiert">
                                <% else_if $Status == "Rejected" %>
                                    <img class="fooditem_statusicon" src="../_resources/app/client/icons/states/state_decline.svg" alt="Abgelehnt">
                                <% else_if $Status == "New" %>
                                    <img class="fooditem_statusicon" src="../_resources/app/client/icons/states/state_new.svg" alt="Neu">
                                <% end_if %>
                                <h4 class="hl4 fooditem_title"><b>$Title</b> <% if $FoodPreference %> - <i>$RenderFoodPreference</i><% end_if %></h4>
                                <% if $Notes %>
                                    <p class="fooditem_notes">$Notes</p>
                                <% end_if %>
                                <% if $Allergies.Count > 0 %>
                                    <p class="fooditem_allergies">Kann folgende Allergien auslösen: $RenderAllergies</p>
                                <% end_if %>
                                <p class="fooditem_supplier">Anbieter: $Supplier.Name</p>
                            </li>
                        <% end_loop %>
                    <% else %>
                        <p>Es sind aktuell noch keine Gerichte für diese Mahlzeit angeboten worden.</p>
                    <% end_if %>
                </ul>

                <a class="button" onclick="document.getElementById('foodadd-modal-{$ID}').showModal()" aria-label="Essen für $Title am $Parent.RenderDate um $RenderTime Uhr anbieten" title="Essen anbieten">Weiteres Gericht hinzufügen</a>

                <dialog id="foodadd-modal-{$ID}" class="foodadd-modal">
                    <button class="dialog-close" onclick="document.getElementById('foodadd-modal-{$ID}').close()">×</button>
                    <div class="dialog-header">
                        <h3>Essen anbieten</h3>
                        <h4>$Parent.Title - $Title</h4>
                        <p class="dialog-date">$Parent.RenderDate um $RenderTime Uhr</p>
                    </div>
                    <div class="dialog-content">
                        <form method="post" action="$Top.FoodAddLink">
                            <div class="field">
                                <label for="title">Name des Gerichts:</label>
                                <input type="text" id="title" name="title" required />
                            </div>
                            <div class="field">
                                <label for="notes">Kurze Beschreibung:</label>
                                <textarea id="notes" name="notes"></textarea>
                            </div>
                            <div class="field">
                                <label for="foodpreference">Ist dein Gericht Vegan oder Vegetarisch?</label>
                                <select id="foodpreference" name="foodpreference">
                                    <option value="">Nicht vegan oder vegetarisch</option>
                                    <option value="vegan">Vegan</option>
                                    <option value="vegetarisch">Vegetarisch</option>
                                </select>
                            </div>
                            <div class="field">
                                <legend>Welche Allergien kann dein Gericht auslösen?</legend>
                                <% loop $Top.AllAllergies %>
                                    <div class="checkbox-entry">
                                        <input type="checkbox" id="allergy-$ID" name="allergies[]" value="$ID" />
                                        <label for="allergy-$ID">$Title</label>
                                    </div>
                                <% end_loop %>
                            </div>
                            <input type="hidden" name="mealid" value="$ID" />
                            <button type="submit" class="button">Essen anbieten</button>
                        </form>
                    </div>
                </dialog>
            </div>
        <% end_with %>
    </div>
</div>
