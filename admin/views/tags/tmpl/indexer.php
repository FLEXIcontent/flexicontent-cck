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

$records_name = 'tags';
$ctrl_task = 'task=tags.';

$app = \Joomla\CMS\Factory::getApplication();
$indexer_name = $app->input->get('indexer', 'tag_mappings', 'cmd');
$rebuildmode  = $app->input->get('rebuildmode', '', 'cmd');
?>

<div>&nbsp;</div>
<div style="heading">
	<?php echo \Joomla\CMS\Language\Text::_('FLEXI_TASK_RUNNING'); ?> ... <br/>
	
<?php
flexicontent_html::loadFramework('flexi-lib');
\Joomla\CMS\Factory::getApplication()->getDocument()->addScript(\Joomla\CMS\Uri\Uri::root(true) . '/components/com_flexicontent/assets/js/admin-actions.js', array('version' => FLEXI_VHASH));

$indexer_query = 'index.php?option=com_flexicontent&format=raw&' . $ctrl_task;
$indexer_params = '&indexer=' . rawurlencode($indexer_name) . '&rebuildmode=' . rawurlencode($rebuildmode)
 . '&' . \Joomla\CMS\Session\Session::getFormToken() . '=1';
?>
<script>
FlexiForm.ready(() => FlexiAdmin.runIndexer(<?php echo json_encode(array(
 'countUrl' => $indexer_query . 'countrows' . $indexer_params,
 'indexUrl' => $indexer_query . 'index' . $indexer_params,
 'records' => $records_name,
)); ?>));
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