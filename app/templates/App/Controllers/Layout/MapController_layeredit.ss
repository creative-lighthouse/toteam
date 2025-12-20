<section class="section section--MapView">
    <% include IntroBar Title="Ebene bearbeiten", Description="$Layer.Title" %>
    <div class="section_content">
        <% with $Map %>
            <div class="map-container">
                <div class="map-controls map-controls--edit">
                    <button class="map-controls__toggle" id="toggleSidebar" aria-label="Sidebar umschalten">
                        <span class="map-controls__toggle-icon">›</span>
                    </button>
                    <h3>Ebene bearbeiten</h3>

                    <div class="map-controls__save">
                        <button class="btn btn--primary" id="saveLayer">
                            Änderungen speichern
                        </button>
                        <span class="save-status" id="saveStatus"></span>
                    </div>

                    <div class="map-controls__actions">
                        <a href="$Top.Link(view)/$ID" class="btn btn--secondary btn--small">
                            ← Zurück zur Ansicht
                        </a>
                        <button class="btn btn--secondary btn--small" id="resetMapView">
                            Ansicht zurücksetzen
                        </button>
                    </div>

                    <div class="map-controls__layer-upload">
                        <h4>Ebenen-Bild</h4>
                        <div class="layer-image-preview">
                            <% if $Top.Layer.Image.exists %>
                                <img src="$Top.Layer.Image.URL" alt="$Top.Layer.Title">
                            <% else %>
                                <div class="layer-image-placeholder">
                                    Kein Bild hochgeladen
                                </div>
                            <% end_if %>
                        </div>
                        <div class="layer-upload-controls">
                            <input type="file" id="layerImageUpload" accept="image/*" class="layer-image-input">
                            <label for="layerImageUpload" class="btn btn--primary btn--small">
                                Bild hochladen
                            </label>
                        </div>
                    </div>

                    <div class="map-controls__pois">
                        <h4>Marker (POIs)</h4>
                        <% if $Top.Layer.POIs.Count > 0 %>
                            <div class="pois-list">
                                <% loop $Top.Layer.POIs %>
                                    <div class="poi-item" data-poi-id="$ID">
                                        <div class="poi-marker" style="background-color: $getMarkerColor();">
                                            <span style="color: {$Top.getContrastColorForPOI($getMarkerColor())};">$getMarkerText</span>
                                        </div>
                                        <div class="poi-info">
                                            <strong>$Title</strong>
                                            <% if $Description %>
                                                <small>$Description.LimitCharacters(50)</small>
                                            <% end_if %>
                                        </div>
                                    </div>
                                <% end_loop %>
                            </div>
                        <% else %>
                            <p class="pois-empty">Noch keine Marker vorhanden</p>
                        <% end_if %>
                        <button class="btn btn--primary btn--small" id="addPOI">
                            + Marker hinzufügen
                        </button>
                    </div>

                    <div class="map-controls__info">
                        <p class="map-controls__help">
                            <strong>Bedienung:</strong><br>
                            • Ziehen zum Verschieben<br>
                            • Mausrad zum Zoomen<br>
                            • Klicken auf die Karte um Marker zu platzieren
                        </p>
                    </div>
                </div>

                <div class="map-renderer-wrapper">
                    <div class="map-renderer map-renderer--edit"
                         data-backgroundimage="$BackgroundImage.URL"
                         data-coordinatesupperleft="$CoordinatesUpperLeft"
                         data-coordinatesupperright="$CoordinatesUpperRight"
                         data-coordinateslowerleft="$CoordinatesLowerLeft"
                         data-coordinateslowerright="$CoordinatesLowerRight"
                         data-layer='$Top.LayerJSON.RAW'
                         data-edit-mode="true">
                        <canvas id="mapCanvas"></canvas>
                    </div>
                </div>
            </div>
        <% end_with %>
    </div>
</section>
