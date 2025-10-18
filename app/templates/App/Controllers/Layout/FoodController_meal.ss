<div class="section section--FoodPageDetails">
    <div class="section_content">
        <% include IntroBar Title="Essensplanung", Description="Hier wird die Verpflegung geplant. Neben dem Termin findest Du hier auch weitere Informationen zu den angebotenen Gerichten und Teilnehmern. Reiche gerne Gerichte ein." %>

        <% with $Meal %>
            <div class="section_mealdetails">
                <div class="mealbox mealbox--mealheader">
                    <h2 class="hl2">$Title</h2>
                    <h3>$Parent.RenderDate | $RenderTime Uhr</h3>
                    <h4>$Parent.Parent.Title</h4>
                </div>
                <div class="mealbox mealbox--mealfoods">
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
                                    <h3 class="hl3 fooditem_title">$Title</h3>
                                    <p><b>Essenspräferenz:</b> $RenderFoodPreference</p>
                                    <% if $Notes %>
                                        <p class="fooditem_notes">$Notes</p>
                                    <% end_if %>
                                    <% if $Allergies.Count > 0 %>
                                        <p class="fooditem_allergies"><b>Allergien:</b> $RenderAllergies</p>
                                    <% end_if %>
                                    <p class="fooditem_supplier"><b>Anbieter:</b> $Supplier.Name</p>
                                </li>
                            <% end_loop %>
                        <% else %>
                            <p>Es sind aktuell noch keine Gerichte für diese Mahlzeit angeboten worden.</p>
                        <% end_if %>
                    </ul>

                    <a class="button" onclick="document.getElementById('foodadd-modal-{$ID}').showModal()" aria-label="Essen für $Title am $Parent.RenderDate um $RenderTime Uhr anbieten" title="Essen anbieten"><% if $Foods.Count > 0 %>Weiteres <% end_if %>Gericht anbieten</a>

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

                <div class="mealbox mealbox--eaters">
                    <h3 class="hl3">Teilnehmer:</h3>
                    <% if $Eaters.Count > 0 %>
                        <ul class="section_eaterlist">
                            <% loop $Eaters %>
                                <li class="eaterlist-entry">
                                    <% with $Member %>
                                        <div class="eater-number">$Pos</div>
                                        <div class="eater-avatar">
                                            <% if $ProfileImage %>
                                                <img src="$ProfileImage.URL" alt="Profilbild von $FirstName $Surname" class="profile_image">
                                            <% else %>
                                                <img src="$Gravatar" alt="Standard Profilbild" class="profile_image">
                                            <% end_if %>
                                        </div>
                                        <div class="eater-text">
                                            <h4 class="hl4 eater-name">$FirstName $LastName<% if not $FoodPreference == "None" %> - <i>$RenderFoodPreference</i><% end_if %></h4>
                                            <p class="eater-info"><b>Essenspräferenz:</b> $RenderFoodPreference</p>
                                            <% if $Allergies.Count > 0 %>
                                                <p class="eater-info"><b>Allergien:</b> $RenderAllergies</p>
                                            <% end_if %>
                                        </div>
                                    <% end_with %>
                                </li>
                            <% end_loop %>
                        </ul>
                    <% else %>
                        <p>Es sind aktuell keine Teilnehmer für diese Mahlzeit angemeldet.</p>
                    <% end_if %>
                </div>
            </div>
        <% end_with %>
    </div>
</div>
