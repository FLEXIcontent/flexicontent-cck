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
	<?php echo \Joomla\CMS\HTML\HTMLHelper::_( 'form.token' ); ?>
</form>

<?php
flexicontent_html::loadFramework('flexi-lib');
\Joomla\CMS\Factory::getApplication()->getDocument()->addScript(\Joomla\CMS\Uri\Uri::root(true) . '/components/com_flexicontent/assets/js/admin-actions.js', array('version' => FLEXI_VHASH));
?>
<script>
FlexiForm.ready(() => FlexiAdmin.runImport(<?php echo json_encode(array(
 'countUrl' => 'index.php?option=com_flexicontent&task=import.getlineno&format=raw',
 // The CSV writer is in the HTML controller; only getlineno uses the raw controller.
 'importUrl' => 'index.php?option=com_flexicontent&task=import.importcsv',
 'batch' => (int) $this->conf['items_per_step'],
)); ?>));
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
<div id="fc_import_failed_box" class="alert alert-danger" style="display: none; margin-top: 12px;"></div>
<div id="statuslog"></div>
<div class="fcclear" style="margin-top:16px;"></div>
<details style="margin-top:8px;">
	<summary style="cursor:pointer; font-weight:bold; color:#666;">Debug log (click to expand)</summary>
	<div id="debuglog" style="font-family:monospace; font-size:12px; background:#f8f8f8; border:1px solid #ccc; padding:8px; max-height:300px; overflow:auto; margin-top:4px;"></div>
</details>
<div class="fcclear"></div>
