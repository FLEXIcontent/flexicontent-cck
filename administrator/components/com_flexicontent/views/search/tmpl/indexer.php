<?php
/**
 * @version 1.5 stable $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

defined('_JEXEC') or die('Restricted access');
?>
<div style="heading">Indexer Running</div>
<script type="text/javascript">
jQuery(document).ready(function() {
	var items_per_call = 50;
	var width = 0;
	var looper = 0;
	var onesector = 1000;
	var fields = new Array();
	var items = new Array();
	var number = 0;
	var errorstring = new Array();
	errorstring[0] = 'Not yet select article type for advance search.';
	errorstring[1] = 'Cannot index because no flexicontent field(s) use advance search mode.';
	function updateprogress() {
		//looper=looper+1;
		if(looper>number) {
			jQuery('div#statuscomment').text(number+'/'+number+' items , INDEXING FINISHED!');
			//if(looper==(number+1)) {
				//jQuery('div#statuscomment').text('Completed!');
			//}
			return;
		}
		fieldindex = Math.floor((looper-1)/items.length)%fields.length;
		itemindex = (looper-1)%items.length;
		jQuery.ajax({
			//url: "index.php?option=com_flexicontent&controller=search&task=index&fieldid="+fields[fieldindex]+"&itemid="+items[itemindex],
			url: "index.php?option=com_flexicontent&controller=search&task=index&items_per_call="+items_per_call+"&itemcnt="+looper,
			success: function(response, status2, xhr2) {
				var arr = response.split('|');
				if(arr[0]=='fail') {
					jQuery('div#statuscomment').text(errorstring[arr[1]]);
					looper = number;
					return;
				}
				width = onesector*looper;
				if (width>300) width = 300
				percent = width/3;
				jQuery('div#insideprogress').css('width', width+'px');
				jQuery('div#updatepercent').text(percent.toFixed(2)+' %');
				jQuery('div#statuscomment').text(looper+'/'+number+' items '+response);
				setTimeout(updateprogress, 100);
			}
		});
		looper=looper+items_per_call;
	}
	jQuery.ajax({
		url: "index.php?option=com_flexicontent&controller=search&task=countrows",
		success: function(response, status, xhr) {
			var arr = response.split('|');
			if(arr[0]=='fail') {
				jQuery('div#statuscomment').text(errorstring[arr[1]]);
				return;
			}
			fields = jQuery.parseJSON(arr[1]);
			items = jQuery.parseJSON(arr[2]);
			//number = fields.length*items.length;
			number = items.length;
			onesector = (number==0)?300:(300/number);
			if(number==0) {
				jQuery('div#statuscomment').text(errorstring[1]);
				return;
			}
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
div#statuscomment{
	color:red;
	margin-top:30px;
}
</style>
<div class="clr"></div>
<div>&nbsp;</div>
<div>&nbsp;</div>
<div id="advancebar"><div id="insideprogress"></div></div>
<div id="updatepercent">0 %</div>
<div class="clr"></div>
<div id="statuscomment"></div>
<div class="clr"></div>

