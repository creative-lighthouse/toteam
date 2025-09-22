<div class="section section--TeaserElement">
    <div class="section_content">
        <div class="section_intro">

        </div>
        <div class="section_teaserlist">
            <% loop $Teaser %>
                <div class="teaserlist_entry">
                    <div class="teaserlist_entry_image">
                        $Image.ScaleWidth(800)
                    </div>
                    <h3>$Title</h3>
                    <div class="teaserlist_entry_content">
                        $Content
                    </div>
                    <% if $Button %>
                        <% include Button Link=$Button %>
                    <% end_if %>
                </div>
            <% end_loop %>
        </div>
    </div>
</div>
