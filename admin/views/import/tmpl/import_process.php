<?php
/**
 * @version 1.5 stable $Id: importer.php 1614 2013-01-04 03:57:15Z ggppdk $
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
$import_task = 'task=import.';
?>
<div style="heading">
	Import Running ... <br/><br/>
	<b>NOTE:</b><br/>
	Only the <b>execution time</b> of import process is displayed below, <br/>
	the <b>network request / reply time</b> is NOT included
</div>

<form action="index.php" method="post" enctype="multipart/form-data" name="adminForm" id="adminForm">
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="import" />
	<input type="hidden" name="view" value="import" />
	<input type="hidden" name="task" value="" />
	<?php echo JHtml::_( 'form.token' ); ?>
</form>

<script>

jQuery(document).ready(function()
{
	var items_per_call = <?php echo (int) $this->conf['items_per_step']; ?>;
	var width = 0;
	var looper = 0;
	var onesector = 1000;
	var items_length = 0;
	var number = 0;
	var form_token = '';

	function updateprogress()
	{
		//alert(' ' +looper + '>=' + number);
		if (looper>=number && looper)
		{
			jQuery('div#statuscomment').html( jQuery('div#statuscomment').text() + ' , IMPORT FINISHED. You can review AND/OR RESET import process');
			return;
		}

		jQuery.ajax({
			url: "index.php?option=com_flexicontent&<?php echo $import_task; ?>importcsv&items_per_call="
				+items_per_call+"&itemcnt="+looper+'&'+form_token+'=1',
			success: function(response, status2, xhr2) {
				var arr = response.split('||||');
				if(arr[0]=='fail') {
					jQuery('div#statuscomment').html(arr[1]);
					looper = number;
					return;
				} else {
					form_token = arr[1];
				}
				width = onesector*looper;
				if (width>300) width = 300;
				percent = width/3;
				jQuery('div#insideprogress').css('width', width+'px');
				jQuery('div#updatepercent').text(' '+percent.toFixed(2)+' %');
				jQuery('div#statuscomment').html((looper<number?looper:number)+' / '+number+' items ');
				jQuery('div#statuslog').append(arr[2]);
				setTimeout(updateprogress, 20);  // milliseconds to delay updating the HTML display
			}
		});
		looper=looper+items_per_call;
	}

	jQuery.ajax({
		url: "index.php?option=com_flexicontent&<?php echo $import_task; ?>getlineno&format=raw",
		success: function(response, status, xhr) {
			//alert(response);
			var arr = response.split('|');
			if(arr[0]=='fail') {
				jQuery('div#statuscomment').html(arr[1]);
				return;
			}
			//items = jQuery.parseJSON(arr[1]);
			//fields = jQuery.parseJSON(arr[2]);
			//number = fields.length*items.length;
			
			items_length = arr[1];
			items_lineno = arr[2];
			form_token = arr[3];
			number = parseInt(items_length);
			onesector = (number==0)?300:(300/number);
			looper = parseInt(items_lineno);
			if(looper>=number && looper) {
				var msg = 'IMPORT already FINISHED. Please RESET import process';
				alert(msg);
				jQuery('div#statuscomment').html( msg );
				return;
			} else {
				updateprogress();
			}
		}
	});
});

</script>

<style>
	div#advancebar {
		width:302px;
		height:17px;
		border:1px solid #000;
		padding:0px;
		margin:0px;
		float:left;
		clear:left;
	}
	div#insideprogress {
		width:0px;
		height:15px;
		background-color:#000;
		padding:0px;
		margin:1px;
	}
	div#updatepercent {
		clear:right;
	}
	div#statuscomment {
		color:red;
		margin-top:16px;
	}
	div#statuslog {
		color:black;
		margin-top:16px;
		height: 200px;
		border: 3px inset; 
		overflow: auto;
	}
</style>

<div class="fcclear"></div>

<div id="advancebar" style="margin-top: 24px;">
	<div id="insideprogress"></div>
</div>

<div id="updatepercent">0 %</div>

<div class="fcclear"></div>
<div id="statuscomment"></div>
<div id="statuslog"></div>
<div class="fcclear"></div>
