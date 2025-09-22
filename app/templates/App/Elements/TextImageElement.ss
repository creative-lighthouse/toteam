<div class="section section--TextImageElement $Highlight $Variant $ImgWidth">
    <div class="section_content">
        <% if $Image %>
            <div class="section_image">
                $Image.ScaleWidth(800)
            </div>
        <% end_if %>

        <div class="section_text">
            <% if $ShowTitle %>
                <h2>$Title</h2>
            <% end_if %>
            <div class="section_text_content">
                $Text
            </div>
            <% if $Button %>
                <% include Button Link=$Button %>
            <% end_if %>
        </div>
    </div>
</div>
