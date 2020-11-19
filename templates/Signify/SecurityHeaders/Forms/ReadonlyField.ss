<span $AttributesHTML>
	$Value
</span>
<% if $IncludeHiddenField %>
	<input $getAttributesHTML("id", "type") id="hidden-{$ID}" type="hidden" />
<% end_if %>
