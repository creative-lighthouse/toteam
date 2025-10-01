<% with $CurrentUser %>
    <div class="section section--ProfilePage">
        <div class="section_content">
            <h1 class="section_title">Profil von $FirstName $Surname <kbd>ALPHA</kbd></h1>
            <div class="section_profileimage">
                <% if $ProfileImage %>
                    <img src="$ProfileImage.URL" alt="Profilbild von $FirstName $Surname" class="profile_image">
                <% else %>
                    <img src="$Gravatar" alt="Standard Profilbild" class="profile_image">
                <% end_if %>
            </div>
            <table class="section_details">
                <tr>
                    <td><strong>Vorname:</strong></td>
                    <td>$FirstName</td>
                </tr>
                <tr>
                    <td><strong>Nachname:</strong></td>
                    <td>$Surname</td>
                </tr>
                <tr>
                    <td><strong>Mitglied seit:</strong></td>
                    <td>$Joindate.Nice</td>
                </tr>
                <tr>
                    <td><strong>Geburtsdatum:</strong></td>
                    <td>$DateOfBirth.Nice</td>
                </tr>
                <tr>
                    <td><strong>Essgewohnheiten:</strong></td>
                    <td>$RenderFoodPreference</td>
                </tr>
                <tr>
                    <td><strong>Allergien:</strong></td>
                    <td>$RenderAllergies</td>
                </tr>
            </table>
            <a class="button" onclick="document.getElementById('editProfileDialog').showModal()">Profil bearbeiten</a>
            <dialog class="dialog dialog--editprofile" id="editProfileDialog">
                <div class="dialog_content">
                    <button class="dialog_close" onclick="document.getElementById('editProfileDialog').close()">&times;</button>
                    <h2>Profil bearbeiten</h2>
                    <% if $Top.EditProfileForm %>
                        $Top.EditProfileForm
                    <% else %>
                        <p>Fehler: Formular konnte nicht geladen werden.</p>
                    <% end_if %>
                </div>
            </dialog>
        </div>
    </div>
<% end_with %>
