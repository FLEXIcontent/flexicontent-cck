<?php
/**
 * @version 1.5 stable $Id: import.php 1883 2014-04-09 17:49:21Z ggppdk $
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

$params = $this->cparams;
$document	= JFactory::getDocument();

// For tabsets/tabs ids (focusing, etc)
$tabSetCnt = -1;
$tabSetMax = -1;
$tabCnt = array();
$tabSetStack = array();

// Load JS tabber lib
$this->document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', array('version' => FLEXI_VHASH));
$this->document->addStyleSheet(JUri::root(true).'/components/com_flexicontent/assets/css/tabber.css', array('version' => FLEXI_VHASH));
$this->document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs

?>


<div id="flexicontent" class="flexicontent fcconfig-form">

<form action="index.php" method="post" name="adminForm" id="adminForm" class="form-validate form-horizontal" enctype="multipart/form-data" >


<div class="<?php echo FLEXI_J40GE ? 'row' : 'row-fluid'; ?>">

<?php if (!empty( $this->sidebar)) : ?>

	<div id="j-sidebar-container" class="span2 col-md-2">
		<?php echo str_replace('type="button"', '', $this->sidebar); ?>
	</div>
	<div id="j-main-container" class="span10 col-md-10">

<?php else : ?>

	<div id="j-main-container" class="span12 col-md-12">

<?php endif;?>


	<?php
	array_push($tabSetStack, $tabSetCnt);
	$tabSetCnt = ++$tabSetMax;
	$tabCnt[$tabSetCnt] = 0;
	?>
	
	<?php

	libxml_use_internal_errors(true);
	$xml = simplexml_load_string($this->conf['xml']);

	if (!$xml)
	{
		foreach(libxml_get_errors() as $error)
		{
			$err_msg[] = $error->message;
		}
	}

	if (!$xml)
	{
		JFactory::getApplication()->enqueueMessage('Can not parse XML file: ' . implode('<br>', $err_msg), 'warning');
	}
	
	else foreach ($xml->rows as $table)
	{
		$table_name = (string)$table->attributes()->table;
		$table_name = ucfirst(str_replace('flexicontent_', '', $table_name));
		$rows = $table->row;
		
		if (!count($rows)) {
			echo "no rows <br/>";
			continue;
		}
		
		$row = $rows[0];
		echo '<h1>'.$table_name.'</h1>';
		echo '<table class="table fcmanlist">'."\n";
		echo '<thead>'."\n";
		echo "<tr>\n";
		foreach($row as $prop => $value) {
			if ($prop=='attribs') continue;
			echo '<th>'.$prop.'</th>';
		}
		echo "</tr>\n";
		echo '</thead>'."\n";
		
		echo '<tbody>'."\n";
		foreach($rows as $row) {
			echo "<tr>\n";
			foreach($row as $prop => $value) {
				if ($prop=='attribs') continue;
				echo '<td style="text-align:center;">'.trim($value,'"').'</td>';
			}
			echo "</tr>\n";
		}
		echo '</tbody>'."\n";
		echo '</table>'."\n";
	}
	?>


	<!-- Common management fields -->
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="appsman" />
	<input type="hidden" name="view" value="appsman" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="fcform" value="1" />
	<?php echo JHtml::_('form.token'); ?>

	<!-- fc_perf -->

	</div>  <!-- j-main-container -->
</div>  <!-- row / row-fluid-->

</form>
</div><!-- #flexicontent end -->
