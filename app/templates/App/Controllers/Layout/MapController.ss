<div class="section section--MapPage">
    <% include IntroBar Title="Lagepläne", Description="Hier findest du eine Übersicht über alle verfügbaren Lagepläne." %>
    <div class="section_content">
        <ul class="map-list">
            <% loop ActiveMaps %>
                <li class="map-entry">
                    <a href="$Link" class="map-entry_link">
                        <div class="map-entry_thumbnail">
                            <img src="$BackgroundImage.FillMax(150, 150).Url" alt="Lageplan von $Title">
                        </div>
                        <div class="map-entry_info">
                            <h3 class="map-entry_title">$Title</h3>
                            <p class="map-entry_description">$Description</p>
                        </div>
                    </a>
                </li>
            <% end_loop %>
        </ul>
    </div>
</div>
