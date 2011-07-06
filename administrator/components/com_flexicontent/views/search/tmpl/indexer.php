<?php defined('_JEXEC') or die('Restricted access'); ?>
<div style="heading">Indexer Running</div>
<script type="text/javascript">
jQuery(document).ready(function() {
	var width = 0;
	var looper = 0;
	var onesector = 1000;
	var fields = new Array();
	var items = new Array();
	var number = 0;
	function updateprogress() {
		looper=looper+1;
		if(looper>number) return;
		fieldindex = Math.floor((looper-1)/items.length)%fields.length;
		itemindex = (looper-1)%items.length;
		jQuery.ajax({
			url: "index.php?option=com_flexicontent&controller=search&task=index&fieldid="+fields[fieldindex]+"&itemid="+items[itemindex],
			success: function(response2, status2, xhr2) {
				width = onesector*looper;
				percent = width/3;
				jQuery('div#insideprogress').css('width', width+'px');
				jQuery('div#updatepercent').text(percent.toFixed(2)+' %');
				setTimeout(updateprogress, 100);
			}
		});
	}
	jQuery.ajax({
		url: "index.php?option=com_flexicontent&controller=search&task=countrows",
		success: function(response, status, xhr) {
			var arr = response.split('|');
			if(arr[0]=='fail') return;
			fields = jQuery.parseJSON(arr[2]);
			items = jQuery.parseJSON(arr[3]);
			number = fields.length*items.length;
			onesector = 300/number;
			looper = 0;
			updateprogress();
		}
	});
});
window.parent.SqueezeBox.addEvent('onClose',function(){
	window.parent.location.reload(true);
});
</script>
<style>
div#advancebar{
	width:302px;
	height:17px;
	border:1px solid #000;
	padding:0px;
	margin:0px;
	float:left;
	clear:left;
}
div#insideprogress{
	width:0px;
	height:15px;
	background-color:#000;
	padding:0px;
	margin:1px;
}
div#updatepercent{
	clear:right;
}
</style>
<div class="clr"></div>
<div>&nbsp;</div>
<div>&nbsp;</div>
<div id="advancebar"><div id="insideprogress"></div></div>
<div id="updatepercent">0 %</div>
<div class="clr"></div>
