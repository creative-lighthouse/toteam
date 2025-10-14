<div class="section section--FoodPage">
    <div class="section_content">
        <div class="section_intro">
            <h1 class="hl1">Essensplanung</h1>
            <p>Hier findest du alles zum Thema essen. Biete Essen für die Mahlzeiten an oder schaue dir an welche Mahlzeiten geplant sind</p>
        </div>

        <div class="section_mealswithoutfood section_mealcategory">
            <h2 class="hl2">Mahlzeiten ohne Gerichte</h2>
            <p>Die folgenden Mahlzeiten haben noch keine Essensangebote. Wenn du Essen für eine dieser Mahlzeiten anbieten möchtest, klicke auf "Essen anbieten".</p>
            <ul class="section_mealswithoutfood_list">
                <% if $MealsWithoutFood.Count > 0 %>
                    <% loop $MealsWithoutFood %>
                        <li class="mealwithoutfood meallist-entry">
                            <span class="mealwithoutfood-title"><b>$Title</b> am $Parent.RenderDate um $RenderTime Uhr ($Parent.Title)</span>
                            <a class="button button--small" onclick="document.getElementById('foodadd-modal-{$ID}').showModal()">Essen anbieten</a>

                            <dialog id="foodadd-modal-{$ID}" class="foodadd-modal">
                                <button class="dialog-close" onclick="document.getElementById('foodadd-modal-{$ID}').close()">×</button>
                                <div class="dialog-header">
                                    <h3>Essen anbieten</h3>
                                    <h4>$Parent.Title - $Title</h4>
                                    <p class="dialog-date">$Parent.RenderDate um $RenderTime Uhr</p>
                                </div>
                                <div class="dialog-content">
                                    <form method="post" action="$Top.FoodAddLink">
                                        <label for="title">Name des Gerichts:</label>
                                        <input type="text" id="title" name="title" required />
                                        <label for="notes">Kurze Beschreibung:</label>
                                        <textarea id="notes" name="notes"></textarea>
                                        <label for="foodpreference">Ist dein Gericht Vegan oder Vegetarisch?</label>
                                        <select id="foodpreference" name="foodpreference">
                                            <option value="">Nicht vegan oder vegetarisch</option>
                                            <option value="vegan">Vegan</option>
                                            <option value="vegetarisch">Vegetarisch</option>
                                        </select>
                                        <fieldset>
                                            <legend>Welche Allergien kann dein Gericht auslösen?</legend>
                                            <% loop $Top.AllAllergies %>
                                                <div>
                                                    <input type="checkbox" id="allergy-$ID" name="allergies[]" value="$ID" />
                                                    <label for="allergy-$ID">$Title</label>
                                                </div>
                                            <% end_loop %>
                                        </fieldset>
                                        <input type="hidden" name="mealid" value="$ID" />
                                        <button type="submit" class="button">Essen anbieten</button>
                                    </form>
                                </div>
                            </dialog>
                        </li>
                    <% end_loop %>
                <% else %>
                    <p>Es sind aktuell keine Mahlzeiten ohne Essensangebote vorhanden.</p>
                <% end_if %>
            </ul>
        </div>

        <div class="section_suppliedmeals section_mealcategory">
            <h2 class="hl2">Von dir angebotene Gerichte:</h2>
            <ul class="section_suppliedmeals_list">
                <% if $UserSuppliedFoods.Count > 0 %>
                    <% loop $UserSuppliedFoods %>
                        <% if $Meals.Count > 0 %>
                            <li class="suppliedmeal meallist-entry">
                                <span class="suppliedmeal-title"><b>$Title</b> für <b>$Meals.First.Parent.Title</b> am $Meals.First.Parent.RenderDate um $Meals.First.RenderTime Uhr</span>
                                <a href="$Meals.First.DetailsLink" class="button button--small">Mahlzeit ansehen</a>
                            </li>
                        <% else %>
                            <li class="suppliedmeal meallist-entry">
                                <span class="suppliedmeal-title"><b>$Title</b></span>
                                <em>Dieses Gericht ist aktuell keiner Mahlzeit zugeordnet</em>
                            </li>
                        <% end_if %>
                    <% end_loop %>
                <% else %>
                    <p>Es sind aktuell keine angebotenen Gerichte von dir in der Zukunft</p>
                <% end_if %>
            </ul>
        </div>

        <div class="section_allmeals section_mealcategory">
            <h2 class="hl2">Alle anstehenden Mahlzeiten</h2>
            <ul class="section_allmeals_list">
                <% if $Meals.Count > 0 %>
                    <% loop $Meals %>
                        <li class="allmeals_meal meallist-entry">
                            <span class="meal-title"><b>$Title</b> am $Parent.RenderDate um $RenderTime Uhr ($Parent.Title)</span>
                            <a href="$DetailsLink" class="button button--small">Details ansehen</a>
                        </li>
                    <% end_loop %>
                <% else %>
                    <p>Es sind aktuell keine Mahlzeiten ohne Essensangebote vorhanden.</p>
                <% end_if %>
            </ul>
        </div>
    </div>
</div>
