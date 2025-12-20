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
                                    <div class="map-layer-item-wrapper">
                                        <label class="map-layer-item">
                                            <input type="checkbox"
                                                   class="map-layer-toggle"
                                                   data-layer-id="$ID"
                                                   <% if $Active %>checked<% end_if %>>
                                            <span class="map-layer-title">$Title</span>
                                        </label>
                                        <a href="$Top.Link(layeredit)/$ID" class="map-layer-edit" title="Ebene bearbeiten">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </a>
                                    </div>
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
