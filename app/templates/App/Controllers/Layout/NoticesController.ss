<div class="section section--NoticesPage">
    <% include IntroBar Title="Ankündigungen", Description="Hier findest du alle aktuellen Ankündigungen. Schau regelmäßig vorbei, um nichts zu verpassen!" %>

    <div class="section_content">
        <% if $Notices.Count > 0 %>
            <ul class="notices-list">
                <% loop $Notices %>
                    <li class="notice_item_wrapper">
                        <a href="$Link" class="notice_item">
                            <% if $IsNewForUser %>
                                <span class="new_badge">NEU</span>
                            <% end_if %>
                            <p class="notice_date"><i>Veröffentlicht am</i> $Created.Nice <% if $Category %><i>in</i> $Category.Title<% end_if %></p>
                            <h3 class="notice_title">$Title</h3>
                            <p class="notice_text">$ShortText</p>
                        </a>
                    </li>
                <% end_loop %>
            </ul>
        <% else %>
            <p class="no-notices">Keine aktuellen Ankündigungen gefunden.</p>
        <% end_if %>
    </div>
</div>
