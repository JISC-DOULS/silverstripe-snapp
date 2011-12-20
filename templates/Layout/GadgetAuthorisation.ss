<div id="GadgetLayout" class="typography">
<h2><% _t('GADGET.AUTHtitle', 'Authorisation') %></h2>
<div id='gadget_authorisation'>
<% if message %>
<div class='message'>$message</div>
<% end_if %>
<% if infotext %>
<div id='infotext'>
$infotext
</div>
<% end_if %>
<% if aForm %>
<div>
$aForm
</div>
<% end_if %>
</div>
</div>
<% include CloseWin %>