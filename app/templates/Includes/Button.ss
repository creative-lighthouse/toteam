<% with $Link %>
    <% if $exists %>
        <a href="$URL" <% if $OpenInNew %>target="_blank" rel="noopener noreferrer"<% end_if %> class="link--button">$Title</a>
    <% end_if %>
<% end_with %>
