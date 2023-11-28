<?php
/**
 * @version 1.5 stable $Id: indexer.php 1775 2013-09-27 02:04:02Z ggppdk $
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

$records_name = 'items';
$ctrl_task = 'task=items.';

$app = JFactory::getApplication();
$indexer_name = $app->input->get('indexer', 'tag_assignments', 'cmd');
$rebuildmode  = $app->input->get('rebuildmode', '', 'cmd');
?>

<div>&nbsp;</div>
<div style="heading">
	<?php echo JText::_('FLEXI_TASK_RUNNING'); ?> ... <br/>
	
<script>
jQuery(document).ready(function() {
	var total_time = 0;
	var records_per_call = 1000;
	var width = 0;
	var looper = 0;
	var onesector = 1000;
	var fields_length = 0;
	var items_length = 0;
	var number = 0;
	function updateprogress() {
		if(looper>=number && looper) {
			jQuery('div#insideprogress').css('width', '300px');
			jQuery('div#updatepercent').text(' 100 %');
			jQuery('div#statuscomment').html( jQuery('div#statuscomment').html() + '<br/><br/><strong>INDEXING FINISHED</strong>. You may close this window');
			//jQuery('img#page_loading_img').hide();
			return;
		}

		var start_time = new Date().getTime();

		jQuery.ajax({
			url: "index.php?option=com_flexicontent&format=raw&<?php echo $ctrl_task; ?>index&records_per_call="+records_per_call+"&records_cnt="+looper+"&indexer=<?php echo $indexer_name;?>"+"&rebuildmode=<?php echo $rebuildmode; ?>",
			success: function(response, status2, xhr2) {
				var request_time = new Date().getTime() - start_time;
				total_time += request_time;
				
				var arr = response.split('|');
				var result = arr[0].trim();
				if ( result=='fail' || result!='success' )
				{
					jQuery('div#statuscomment').html( '<span style="font-weight:bold;">INDEXER HALTED, due to server response</span>: <br/> ' + (result=='fail'  ?  arr[1]  :  response) );
					//jQuery('img#page_loading_img').hide();
					looper = number;
					return;
				}
				//looper=looper+records_per_call;
				looper=parseInt(arr[1]);
				
				width = onesector*looper;
				if (width>300) width = 300;
				percent = width/3;
				jQuery('div#insideprogress').css('width', width+'px');
				jQuery('div#updatepercent').html(' '+percent.toFixed(2)+' %');
				jQuery('div#statuscomment').html(
					(looper<number?looper:number)+' / '+number+' <?php echo $records_name; ?> <br/>'
					+ '<br/>' + arr[2]
					+ '<br/>' + 'Total task time: '+parseFloat(total_time/1000).toFixed(2) + ' secs'
					+ '<br/>' + arr[3]
				);
				setTimeout(updateprogress, 20);  // milliseconds to delay updating the HTML display
			},
			error: function (xhr, ajaxOptions, thrownError) {
				alert(xhr.status);
				alert(thrownError);
			}
		});
	}

	var start_time = new Date().getTime();

	jQuery.ajax({
		url: "index.php?option=com_flexicontent&format=raw&<?php echo $ctrl_task; ?>countrows&indexer=<?php echo $indexer_name;?>&<?php echo JSession::getFormToken().'=1'; ?>",
		success: function(response, status, xhr) {
			var request_time = new Date().getTime() - start_time;
			total_time += request_time;
			
			var arr = response.split('|');
			var result = arr[0].trim();
			if( result=='fail' || result!='success' )
			{
				jQuery('div#statuscomment').html( '<span style="font-weight:bold;">INDEXER HALTED, due to server response</span>: <br/> ' + (result=='fail'  ?  arr[1]  :  response) );
				return;
			}
			//items = jQuery.parseJSON(arr[1]);
			//fields = jQuery.parseJSON(arr[2]);
			//number = fields.length*items.length;
			
			items_length = arr[1];
			fields_length = arr[2];
			number = items_length;
			onesector = (number==0)?300:(300/number);
			
			looper=parseInt(arr[3]);
			width = 0;
			percent = 0;
			jQuery('div#insideprogress').css('width', width+'px');
			jQuery('div#updatepercent').html(' '+percent.toFixed(2)+' %');
			jQuery('div#statuscomment').html(
				(looper<number?looper:number)+' / '+number+' <?php echo $records_name; ?> <br/>'
					+ '<br/>' + arr[4]
					+ '<br/>' + 'Total task time: '+parseFloat(total_time/1000).toFixed(2) + ' secs'
					+ '<br/>' + arr[5]
			);
			updateprogress();
		},
		error: function (xhr, ajaxOptions, thrownError) {
			alert(xhr.status);
			alert(thrownError);
		}
	});
});
</script>

<style>
div#advancebar{
	width:302px;
	height:17px;
	border:1px solid #000;
	padding:0px;
	margin:0 10px 0 0;
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
	color:darkgreen;
	margin-top:30px;
}
</style>
<div class="clr"></div>
<div>&nbsp;</div>
<div id="advancebar"><div id="insideprogress"></div></div>
<div id="updatepercent">0 %</div>
<div class="clr"></div>
<div id="statuscomment">Initializing <img id="page_loading_img" src="components/com_flexicontent/assets/images/ajax-loader.gif"></div>
<div class="clr"></div>
