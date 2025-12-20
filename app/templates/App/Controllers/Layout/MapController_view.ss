<section class="section section--MapView">
    <% include IntroBar Title="Lageplan", Description=$Map.Title %>
    <div class="section_content">
        <% with $Map %>
            <div class="map-container">
                <div class="map-controls">
                    <button class="map-controls__toggle" id="toggleSidebar" aria-label="Sidebar umschalten">
                        <span class="map-controls__toggle-icon">›</span>
                    </button>
                    <h3>Steuerung</h3>

                    <div class="map-controls__actions">
                        <button class="btn btn--secondary btn--small" id="resetMapView">
                            Ansicht zurücksetzen
                        </button>
                    </div>

                    <% if $MapLayers.Count > 0 %>
                        <div class="map-controls__layers">
                            <h4>Ebenen</h4>
                            <div class="map-layers-list">
                                <% loop $MapLayers %>
                                    <label class="map-layer-item">
                                        <input type="checkbox"
                                               class="map-layer-toggle"
                                               data-layer-id="$ID"
                                               <% if $Active %>checked<% end_if %>>
                                        <span class="map-layer-title">$Title</span>
                                    </label>
                                <% end_loop %>
                            </div>
                        </div>
                    <% end_if %>

                    <div class="map-controls__info">
                        <p class="map-controls__help">
                            <strong>Bedienung:</strong><br>
                            • Ziehen zum Verschieben<br>
                            • Mausrad zum Zoomen<br>
                            • Touch: 2 Finger zum Zoomen<br>
                            • Windrose zeigt Norden
                        </p>
                        <% if $ShortText %>
                            <p style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e0e0e0;">
                                $ShortText
                            </p>
                        <% end_if %>
                    </div>
                </div>

                <div class="map-renderer-wrapper">
                    <div class="map-renderer"
                         data-backgroundimage="$BackgroundImage.URL"
                         data-coordinatesupperleft="$CoordinatesUpperLeft"
                         data-coordinatesupperright="$CoordinatesUpperRight"
                         data-coordinateslowerleft="$CoordinatesLowerLeft"
                         data-coordinateslowerright="$CoordinatesLowerRight"
                         data-layers='$Top.LayersJSON.RAW'>
                        <canvas id="mapCanvas"></canvas>
                    </div>
                </div>
            </div>
        <% end_with %>
    </div>
</div>
