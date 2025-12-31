<section class="section section--MapView">
    <% include IntroBar Title="Ebene bearbeiten", Description="$Layer.Title" %>
    <div class="section_content">
        <% with $Map %>
            <div class="map-container">
                <div class="map-controls map-controls--edit">
                    <button class="map-controls_toggle" id="toggleSidebar" aria-label="Sidebar umschalten">
                        <span class="map-controls_toggle-icon">›</span>
                    </button>
                    <div class="map-controls_header">
                        <h3>Ebene bearbeiten</h3>
                        <button class="button header_save" id="saveLayer">
                            <div class="headerSave_button" style="display: block; mask-image: url('_resources/app/client/icons/actions/action_save.svg');" alt="Icon" class="icon--small"></div>
                            <div class="headerSave_saving" style="display: none; mask-image: url('_resources/app/client/icons/actions/action_reload.svg');" alt="Icon" class="icon--small"></div>
                            <div class="headerSave_saved" style="display: none; mask-image: url('_resources/app/client/icons/actions/action_check.svg');" alt="Icon" class="icon--small"></div>
                        </button>
                    </div>

                    <div class="map-controls_wrap">
                        <div class="map-controls_edit-fields">
                            <div class="edit-field">
                                <label for="layerTitle">Titel</label>
                                <input type="text" id="layerTitle" class="form-control" value="$Top.Layer.Title" placeholder="Ebenen-Titel">
                            </div>

                            <div class="edit-field">
                                <label for="layerDescription">Beschreibung</label>
                                <textarea id="layerDescription" class="form-control" rows="3" placeholder="Beschreibung der Ebene">$Top.Layer.Description</textarea>
                            </div>

                            <div class="edit-field">
                                <label for="layerColor">Ebenen-Farbe</label>
                                <div class="color-picker-wrapper">
                                    <input type="color" id="layerColor" value="$Top.Layer.LayerColor">
                                    <input type="text" id="layerColorText" class="form-control" value="$Top.Layer.LayerColor" placeholder="#999999">
                                </div>
                            </div>
                        </div>

                        <div class="map-controls_layer-upload">
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

                        <div class="map-controls_pois">
                            <h4>Marker (POIs)</h4>
                            <% if $Top.Layer.POIs.Count > 0 %>
                                <div class="pois-list">
                                    <% loop $Top.Layer.POIs %>
                                        <div class="poi-item" data-poi-id="$ID" data-poi-position="$Coordinates">
                                            <div class="poi-marker" style="background-color: $getMarkerColor();">
                                                <span style="color: {$Top.getContrastColorForPOI($getMarkerColor())};">$getMarkerText</span>
                                            </div>
                                            <div class="poi-info">
                                                <strong>$Title</strong>
                                                <% if $Description %>
                                                    <small>$Description.LimitCharacters(50)</small>
                                                <% end_if %>
                                            </div>
                                            <button class="poi-item_delete" data-poi-id="$ID" title="Marker löschen">
                                                <div style="mask-image: url('_resources/app/client/icons/actions/action_trash.svg');" class="icon--small"></div>
                                            </button>
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

                        <div class="map-controls_info">
                            <p class="map-controls_help">
                                <strong>Bedienung:</strong><br>
                                • Ziehen zum Verschieben<br>
                                • Mausrad zum Zoomen<br>
                                • Klicken auf die Karte um Marker zu platzieren
                            </p>
                        </div>
                    </div>

                    <div class="map-controls_actions">
                        <a href="$Top.Link(view)/$ID" class="button action_back">
                            ← Zurück zum Lageplan
                        </a>
                        <button class="button action_recenter" id="resetMapView">
                            <div class="resetMapView_button" style="mask-image: url('_resources/app/client/icons/actions/action_recenter.svg');" alt="Icon" class="icon--small"></div>
                        </button>
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
