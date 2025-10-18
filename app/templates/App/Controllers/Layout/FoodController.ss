<div class="section section--FoodPage">
    <% include IntroBar Title="Essensplanung", Description="Hier findest du alles zum Thema essen. Biete Essen für die Mahlzeiten an oder schaue dir an welche Mahlzeiten geplant sind" %>
    <div class="section_content">
        <div class="section_foodbox">
            <% if $Meals.GroupedBy('ParentID').First.Children.Count > 0 %>
                <ul class="foodbox-grid">
                    <% loop $Meals.GroupedBy('ParentID') %>
                        <li class="foodbox-day">
                            <% with $Children.First.Parent %>
                                <h2>$Title</h2>
                                <p>am $RenderDate</p>
                            <% end_with %>
                            <ul class="meallist eventday-meallist">
                                <% loop $Children %>
                                    <li class="meallist-entry allmeals_meal">
                                        <a class="meallist-entry-link" href="$DetailsLink" title="Mahlzeit ansehen" aria-label="Mahlzeit $Title am $Parent.RenderDate um $RenderTime Uhr ansehen">
                                            <span class="meal-title"><b>$Title</b> um $RenderTime Uhr</span>
                                            <% if $Foods.Count > 0 %>
                                            <ul class="foodlist">
                                                <% loop $Foods %>
                                                    <li class="foodlist-entry">
                                                        <span class="food-title">$Title <i class="foodlist-entry-supplier">($Supplier.FirstName)</i></span>
                                                    </li>
                                                <% end_loop %>
                                            </ul>
                                            <% else %>
                                                <p><i>Keine Gerichte verfügbar</i></p>
                                            <% end_if %>
                                        </a>
                                    </li>
                                <% end_loop %>
                            </ul>
                        </li>
                    <% end_loop %>
                </ul>
            <% else %>
                <p>Es sind aktuell keine Mahlzeiten geplant.</p>
            <% end_if %>
        </div>

        <hr>

        <% if $UserSuppliedFoods.Count > 0 %>
            <details class="section_foodbox section_foodbox--suppliedfoods">
                <summary>
                    <h2 class="hl2">Von dir angebotene Gerichte:</h2>
                </summary>
                <ul class="foodbox-list">
                    <% loop $UserSuppliedFoods %>
                        <% if $Meals.Count > 0 %>
                            <li class="meallist-entry">
                                <span class="suppliedmeal-title"><b>$Title</b> für <b>$Meals.First.Parent.Title</b> am $Meals.First.Parent.RenderDate um $Meals.First.RenderTime Uhr</span>
                                <div class="food-actions">
                                    <a href="$Meals.First.DetailsLink" class="icon-button" title="Mahlzeit ansehen">
                                        <img src="../_resources/app/client/icons/actions/action_eye.svg" alt="" class="button-icon" />
                                    </a>
                                    <a class="icon-button" title="Gericht bearbeiten" onclick="document.getElementById('foodedit-modal-{$ID}').showModal()">
                                        <img src="../_resources/app/client/icons/actions/action_edit.svg" alt="" class="button-icon" />
                                    </a>

                                    <dialog id="foodedit-modal-{$ID}" class="foodedit-modal">
                                        <button class="dialog-close" onclick="document.getElementById('foodedit-modal-{$ID}').close()">×</button>
                                        <div class="dialog-header">
                                            <h3>Essen anbieten</h3>
                                            <p>$Meals.First.Title - $Meals.First.Parent.RenderDate um $Meals.First.RenderTime Uhr</p>
                                            <p class="dialog-date"><b>$Title</b></p>
                                        </div>
                                        <div class="dialog-content">
                                            <form method="post" action="$Top.FoodEditLink">
                                                <div class="field">
                                                    <label for="title">Name des Gerichts:</label>
                                                    <input type="text" id="title" name="title" required value="$Title"/>
                                                </div>
                                                <div class="field">
                                                    <label for="notes">Kurze Beschreibung:</label>
                                                    <textarea id="notes" name="notes">$Notes</textarea>
                                                </div>
                                                <div class="field">
                                                    <label for="foodpreference">Ist dein Gericht Vegan oder Vegetarisch?</label>
                                                    <select id="foodpreference" name="foodpreference">
                                                        <option value=""<% if not $FoodPreference %> selected<% end_if %>>Nicht vegan oder vegetarisch</option>
                                                        <option value="vegan"<% if $FoodPreference == 'vegan' %> selected<% end_if %>>Vegan</option>
                                                        <option value="vegetarisch"<% if $FoodPreference == 'vegetarisch' %> selected<% end_if %>>Vegetarisch</option>
                                                    </select>
                                                </div>
                                                <div class="field">
                                                    <legend>Welche Allergien kann dein Gericht auslösen?</legend>
                                                    <% loop $Top.AllAllergies %>
                                                        <div class="checkbox-entry">
                                                            <input <% if $IsInFood($Up.ID) %>checked<% end_if %> type="checkbox" id="allergy-$ID" name="allergies[]" value="$ID" />
                                                            <label for="allergy-$ID">$Title</label>
                                                        </div>
                                                    <% end_loop %>
                                                </div>
                                                <input type="hidden" name="mealid" value="$Meals.First.ID" />
                                                <input type="hidden" name="foodid" value="$ID" />
                                                <button type="submit" class="button">Gericht bearbeiten</button>
                                            </form>
                                        </div>
                                    </dialog>
                                </div>
                            </li>
                        <% else %>
                            <li class="meallist-entry section_meal suppliedmeal">
                                <span class="suppliedmeal-title"><b>$Title</b></span>
                                <em>Dieses Gericht ist aktuell keiner Mahlzeit zugeordnet</em>
                            </li>
                        <% end_if %>
                    <% end_loop %>
                </ul>
            </details>
        <% end_if %>
    </div>
</div>
