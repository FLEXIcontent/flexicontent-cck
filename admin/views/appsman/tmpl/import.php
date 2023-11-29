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
	
	<!-- tabber start -->
	<div class="fctabber fields_tabset" id="fcform_tabset_<?php echo $tabSetCnt; ?>">
		<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-home-2">
			<h3 class="tabberheading"><?php echo JText::_("FLEXI_IMPORT");?></h3>
			<br/>
			
			<form action="index.php" method="post" name="adminForm_appsman_import" id="adminForm_appsman_import" class="form-validate form-horizontal" enctype="multipart/form-data" >
				
				<button class="btn btn-success" onclick="this.form.elements['task'].value='appsman.initxml'; this.form.submit();">
					<span class="icon-import"></span><?php echo JText::_('FLEXI_IMPORT_PREPARE_TASK'); ?>
				</button>
				<br/><br/>
				
				<table class="fc-form-tbl align-top">
					<tr>
						<td class="key">
							<label class="label" for="xmlfile"><?php echo JText::_( 'FLEXI_XMLFILE' ); ?></label>
						</td>
						<td class="data">
							<input type="file" name="xmlfile" id="xmlfile" class="required" />
						</td>
					</tr>
				</table>
				
				<br/><br/>
				<table class="fc-form-tbl" >
					<tr>
						<td>
							<fieldset>
								<legend style='color: darkgreen;'><?php echo JText::_( 'About importing configuration' ); ?></legend>
								<div class="alert alert-info">Please select a configuration file to import</div>
							</fieldset>
						</td>
					</tr>
				</table>
					
				<input type="hidden" name="option" value="com_flexicontent" />
				<input type="hidden" name="controller" value="appsman" />
				<input type="hidden" name="view" value="appsman" />
				<input type="hidden" name="task" value="" />
				<input type="hidden" name="fcform" value="1" />
				<?php echo JHtml::_( 'form.token' ); ?>
				
			</form>
			
		</div>
		
		<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-home-2">
			<h3 class="tabberheading"><?php echo JText::_("FLEXI_EXPORT");?></h3>
			
			<form action="index.php" method="post" name="adminForm_appsman_export" id="adminForm_appsman_export" class="form-validate form-horizontal" enctype="multipart/form-data" >
				
				<br/>
				<button class="btn btn-success" onclick="this.form.elements['task'].value='appsman.export'; this.form.submit();">
					<span class="icon-download"></span><?php echo JText::_('FLEXI_EXPORT_SELECTED'); ?>
				</button>
				
				<button class="btn btn-warning" onclick="this.form.elements['task'].value='appsman.exportclear'; this.form.submit();">
					<span class="icon-cancel"></span><?php echo JText::_('FLEXI_EXPORT_CLEAR_LIST'); ?>
				</button>
				<br/><br/>
				
				<label class="label" id="export_filename-lbl" for="export_filename">Export filename</label> <input id="export_filename" name="export_filename" type="text" value="" />
				<br/><br/>
				
				<?php
				$tablename_to_option = array(
					'flexicontent_fields'=>'flexicontent', 'flexicontent_types'=>'flexicontent', 'flexicontent_templates'=>'flexicontent',
					'categories'=>'flexicontent', 'usergroups'=> 'flexicontent', 'assets'=>''
				);
				$tablename_to_view   = array(
					'flexicontent_fields'=>'fields', 'flexicontent_types'=>'types', 'flexicontent_templates'=>'templates',
					'categories'=>'categories', 'usergroups'=> 'groups', 'assets'=>''
				);
				$tablename_to_title  = array(
					'flexicontent_fields'=>'Fields', 'flexicontent_types'=>'Types', 'flexicontent_templates'=>'Templates',
					'categories'=>'Categories', 'usergroups'=> 'User groups', 'assets'=>'Assets'
				);
				$session  = JFactory::getSession();
				$export_conf = $session->get('appsman_export', array(), 'flexicontent');
				?>
				
				<?php foreach ($tablename_to_title as $table_name => $_title_name) : ?>
					<div style="display:inline-block;">
						<?php
						$row_ids = isset($export_conf[$table_name]) ? $export_conf[$table_name] : array();
						$_option_name = $tablename_to_option[$table_name];
						$_view_name   = $tablename_to_view[$table_name];
						echo '<h2 style="vertical-align:middle; display:inline-block;">'.str_replace('flexicontent_', '', $_title_name).'</h2>';
						?> &nbsp;
						<?php if ($_option_name && $_view_name): ?>
						<a class="btn btn-small" href="index.php?option=com_<?php echo $_option_name; ?>&amp;view=<?php echo $_view_name; ?>" style="vertical-align:middle; display:inline-block;">
							<span class="icon-box-add"></span><?php echo JText::_('FLEXI_ADD_MORE'); ?>
						</a>
						<?php endif; ?>
					</div>
					<div class="fcclear"></div>
					<?php
					echo '<span class="label">IDs / Names</span> '.implode(', ', array_keys($row_ids));
					echo '<br/>';
					?>
				<?php endforeach; ?>


				<!-- Common management form fields -->
				<input type="hidden" name="option" value="com_flexicontent" />
				<input type="hidden" name="controller" value="appsman" />
				<input type="hidden" name="view" value="appsman" />
				<input type="hidden" name="task" value="" />
				<input type="hidden" name="fcform" value="1" />
				<?php echo JHtml::_( 'form.token' ); ?>
				
			</form>
		</div>
		
	</div>
	<!-- tabber end -->
	<?php $tabSetCnt = array_pop($tabSetStack); ?>
	
	<!-- fc_perf -->

	</div>  <!-- j-main-container -->
</div>  <!-- row / row-fluid-->

</div><!-- #flexicontent end -->
