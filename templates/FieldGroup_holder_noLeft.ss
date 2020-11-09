<div <% if $Name %>id="$Name"<% end_if %> class="field <% if $extraClass %>$extraClass<% end_if %>">
<% if $Title %><label class="">$Title</label><% end_if %>

<div class="fieldgroup fieldgroup--no-left<% if $Zebra %> fieldgroup-$Zebra<% end_if %>">
    <% loop $FieldList %>
        <div id="$HolderID" class="fieldgroup-field $FirstLast $EvenOdd <% if $extraClass %>$extraClass<% end_if %>">
            $SmallFieldHolder
        </div>
    <% end_loop %>
</div>
<% if $RightTitle %><label class="right">$RightTitle</label><% end_if %>
<% if $Message %><span class="message $MessageType">$Message</span><% end_if %>
<% if $Description %><span class="description">$Description</span><% end_if %>
</div>
