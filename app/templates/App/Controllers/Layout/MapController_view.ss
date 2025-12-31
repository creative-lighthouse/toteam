<section class="section section--MapView">
    <% include IntroBar Title="Lageplan", Description="Bedienung:
                            • Ziehen zum Verschieben
                            • Mausrad zum Zoomen
                            • Touch: 2 Finger zum Zoomen
                            • Windrose zeigt Norden
                        " %>
    <div class="section_content">
        <% with $Map %>
            <div class="map-container">
                <div class="map-controls">
                    <button class="map-controls_toggle" id="toggleSidebar" aria-label="Sidebar umschalten">
                        <span class="map-controls_toggle-icon">›</span>
                    </button>
                    <div class="map-controls_header">
                        <h3>$Title</h3>
                    </div>

                    <div class="map-controls_wrap">
                        <% if $MapLayers.Count > 0 %>
                            <div class="map-controls_layers">
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

                        <div class="map-controls_info">
                            <% if $ShortText %>
                                <p>
                                    $ShortText
                                </p>
                            <% end_if %>
                        </div>
                    </div>

                    <div class="map-controls_actions">
                        <a href="$Top.Link()" class="button action_back">
                            ← Alle Lagepläne
                        </a>
                        <button class="button action_recenter" id="resetMapView">
                            <div class="resetMapView_button" style="mask-image: url('_resources/app/client/icons/actions/action_recenter.svg');" alt="Icon" class="icon--small"></div>
                        </button>
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
