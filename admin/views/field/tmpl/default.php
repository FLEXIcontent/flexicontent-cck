<?php
/**
 * @version 1.5 stable $Id: default.php 1125 2012-01-26 12:38:53Z ggppdk $
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

$tip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
$btn_class = FLEXI_J30GE ? 'btn' : 'fc_button fcsimple';
$infoimage 	= JHTML::image ( 'administrator/components/com_flexicontent/assets/images/lightbulb.png', JText::_( 'FLEXI_NOTES' ), ' style="vertical-align:top; margin-top:6px;" ' );

// Load JS tabber lib
$this->document->addScript(JURI::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js');
$this->document->addStyleSheet(JURI::root(true).'/components/com_flexicontent/assets/css/tabber.css');
$this->document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs
?>

<div class="flexicontent" id="flexicontent">
<form action="index.php" method="post" class="form-validate" name="adminForm" id="adminForm">

<table cellspacing="0" cellpadding="0" border="0" width="100%"><tr>

	<td valign="top" width="50%">
	
		<!--span class="badge"><h3><?php echo JText::_( /*'FLEXI_STANDARD_FIELDS_PROPERTIES'*/'Common configuration' ); ?></h3></span-->
		
		<table class="fc-form-tbl" style="margin-bottom:12px;">
			<tr>
				<td class="key">
					<?php echo $this->form->getLabel('label'); ?>
				</td>
				<td>
					<?php echo $this->form->getInput('label'); ?>
				</td>
			</tr>
			<?php if ($this->form->getValue('iscore') == 0) : ?>
			<tr>
				<td class="key">
					<?php echo $this->form->getLabel('name'); ?>
				</td>
				<td>
					<?php echo $this->form->getInput('name'); ?>
				</td>
			</tr>
			<?php else : ?>
			<tr>
				<td class="key">
					<?php echo $this->form->getLabel('name'); ?>
				</td>
				<td>
					<?php echo $this->form->getValue("name"); ?>
				</td>
			</tr>
			<?php endif; ?>

			
			<?php if ($this->form->getValue("iscore") == 0) : ?>
			<tr>
				<td class="key">
				<?php echo $this->form->getLabel('field_type'); ?>
				</td>
				<td>
				<?php echo $this->lists['field_type']; ?>
				&nbsp;&nbsp;&nbsp;
				[ <span id="field_typename"><?php echo $this->form->getValue('field_type'); ?></span> ]
				</td>
			</tr>
			<?php endif; ?>

		</table>
		
		<div class="fctabber fields_tabset" id="field_specific_props_tabset">
			
			<div class="tabbertab" id="fcform_tabset_common_basic_tab">
				<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_BASIC' ); ?> </h3>
				
				<table class="fc-form-tbl">
					<tr>
						<td class="key">
							<?php echo $this->form->getLabel('published'); ?>
						</td>
						<td>
							<?php echo $this->form->getInput('published'); ?>
							<?php
							$disabled = ($this->form->getValue("id") > 0 && $this->form->getValue("id") < 7);
							if ($disabled) {
								$this->document->addScriptDeclaration("
									jQuery( document ).ready(function() {
										setTimeout(function(){ 
											jQuery('#jform_published input').attr('disabled', 'disabled').off('click');
											jQuery('#jform_published label').attr('disabled', true).off('click');
										}, 1);
									});
								");
							}
							?>
						</td>
					</tr>
					
					<tr>
						<td class="key">
							<?php echo $this->form->getLabel('access'); ?>
						</td>
						<td>
							<?php echo $this->form->getInput('access'); ?>
						</td>
					</tr>
					
					<tr>
						<td class="key">
							<?php echo $this->form->getLabel('ordering'); ?>
						</td>
						<td>
							<?php echo $this->form->getInput('ordering'); ?>
						</td>
					</tr>
					
					<tr>
						<td colspan="2" style="padding-top:24px;">
							<?php $box_class = $this->row->iscore ? 'fc-info' : ($this->typesselected ? 'fc-success' : 'fc-warning'); ?>
							<span class="<?php echo $box_class; ?> fc-mssg" style="width:90%; margin:6px 0px 0px 0px !important;">
								<?php echo JText::_( $this->row->iscore ? 'FLEXI_SELECT_TYPES_CORE_NOTES' : 'FLEXI_SELECT_TYPES_CUSTOM_NOTES' ); ?>
							</span>
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<span class="label label-warning" style="vertical-align:middle;">
								<?php echo JText::_( 'FLEXI_TYPES' ); ?>
							</span>
							<?php echo /*FLEXI_J16GE ? $this->form->getInput('tid') :*/ $this->lists['tid']; ?>
						</td>
					</tr>
					
				</table>
			</div>
			
			
			<div class="tabbertab" id="fcform_tabset_common_basic_tab">
				<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_FIELD_SEARCH_FILTERING' ); ?> </h3>
				
				<?php if ($this->supportsearch || $this->supportfilter) : ?>
					<span class="fcsep_level1" style="width:90%; margin-top:16px;"><?php echo JText::_( 'FLEXI_BASIC_INDEX' ); ?></span>
					<span class="fcsep_level4" style="margin-left: 32px;"><?php echo JText::_( 'FLEXI_BASIC_INDEX_NOTES' ); ?></span>
					<div class="fcclear"></div>
				<?php endif; ?>
					
				<table class="fc-form-tbl">
					<?php if ($this->supportsearch) : ?>
					<tr>
						<td class="key">
							<?php echo $this->form->getLabel('issearch'); ?>
						</td>
						<td>
							<?php echo
								in_array($this->form->getValue('issearch'),array(-1,2)) ?
									JText::_($this->form->getValue('issearch')==-1 ? 'FLEXI_NO' : 'FLEXI_YES') .' -- '. JText::_('FLEXI_FIELD_BASIC_INDEX_PROPERTY_DIRTY') :
									$this->form->getInput('issearch'); ?>
						</td>
					</tr>
					<?php endif; ?>
					
					<?php if ($this->supportfilter) : ?>
					<tr>
						<td class="key">
							<?php echo $this->form->getLabel('isfilter'); ?>
						</td>
						<td>
							<?php echo $this->form->getInput('isfilter'); ?>
						</td>
					</tr>
					<?php endif; ?>
				</table>
				
				<?php if ($this->supportadvsearch || $this->supportadvfilter) : ?>
					<span class="fcsep_level1" style="width:90%; margin-top:16px; "><?php echo JText::_( 'FLEXI_ADV_INDEX' ); ?></span>
					<span class="fcsep_level4" style="margin-left: 32px;"><?php echo JText::_( 'FLEXI_ADV_INDEX_NOTES' ); ?></span>
					<div class="fcclear"></div>
				<?php endif; ?>
				
				<table class="fc-form-tbl">
					<?php if ($this->supportadvsearch) : ?>
					<tr>
						<td class="key">
							<?php echo $this->form->getLabel('isadvsearch'); ?>
						</td>
						<td>
							<?php echo
								in_array($this->form->getValue('isadvsearch'),array(-1,2)) ?
									JText::_($this->form->getValue('isadvsearch')==-1 ? 'FLEXI_NO' : 'FLEXI_YES') .' -- '. JText::_('FLEXI_FIELD_ADVANCED_INDEX_PROPERTY_DIRTY') :
									$this->form->getInput('isadvsearch'); ?>
						</td>
					</tr>
					<?php endif; ?>
					
					<?php if ($this->supportadvfilter) : ?>
					<tr>
						<td class="key">
							<?php echo $this->form->getLabel('isadvfilter'); ?>
						</td>
						<td>
							<?php echo
								in_array($this->form->getValue('isadvfilter'),array(-1,2)) ?
									JText::_($this->form->getValue('isadvfilter')==-1 ? 'FLEXI_NO' : 'FLEXI_YES') .' -- '. JText::_('FLEXI_FIELD_ADVANCED_INDEX_PROPERTY_DIRTY') :
									$this->form->getInput('isadvfilter'); ?>
						</td>
					</tr>
					<?php endif; ?>
				</table>
				
			</div>
			
			
			<div class="tabbertab" id="fcform_tabset_common_basic_tab">
				<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_ITEM_FORM' ); ?> </h3>
				<table class="fc-form-tbl">
					
					<tr<?php echo !$this->supportuntranslatable?' style="display:none;"':'';?>>
						<td class="key">
							<?php echo $this->form->getLabel('untranslatable'); ?>
						</td>
						<td>
							<?php echo $this->form->getInput('untranslatable'); ?>
						</td>
					</tr>
	
					<tr<?php echo !$this->supportformhidden?' style="display:none;"':'';?>>
						<td class="key">
							<?php echo $this->form->getLabel('formhidden'); ?>
						</td>
						<td>
							<?php echo $this->form->getInput('formhidden'); ?>
						</td>
					</tr>
					
					<tr<?php echo !$this->supportvalueseditable?' style="display:none;"':'';?>>
						<td class="key">
							<?php echo $this->form->getLabel('valueseditable'); ?>
						</td>
						<td>
							<?php echo $this->form->getInput('valueseditable'); ?>
						</td>
					</tr>
					
					<tr<?php echo !$this->supportedithelp?' style="display:none;"':'';?>>
						<td class="key">
							<?php echo $this->form->getLabel('edithelp'); ?>
						</td>
						<td>
							<?php echo $this->form->getInput('edithelp'); ?>
						</td>
					</tr>
					
					<tr>
						<td class="key">
							<?php echo $this->form->getLabel('description'); ?>
						</td>
						<td>
							<?php echo $this->form->getInput('description'); ?>
						</td>
					</tr>
					
				</table>
			</div>
			
			
			<?php if ($this->permission->CanConfig) :
				/*$this->document->addScriptDeclaration("
					window.addEvent('domready', function() {
						var slideaccess = new Fx.Slide('tabacces');
						var slidenoaccess = new Fx.Slide('notabacces');
						slideaccess.hide();
						$$('fieldset.flexiaccess legend').addEvent('click', function(ev) {
							slideaccess.toggle();
							slidenoaccess.toggle();
						});
					});
				");*/
			?>
			<div class="tabbertab" id="fcform_tabset_common_basic_tab">
				<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_PERMISSIONS' ); ?> </h3>
				<!--fieldset class="flexiaccess">
					<legend><?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT' ); ?></legend-->
					<table id="tabacces" class="fc-form-tbl" width="100%">
						<tr>
							<td>
								<div id="access"><?php echo $this->form->getInput('rules'); ?></div>
							</td>
						</tr>
					</table>
					<div id="notabacces">
						<?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT_DESC' ); ?>
					</div>
				<!--/fieldset-->
			</div>
			<?php endif; ?>		
			
		</div>
		
		
	</td>
	<td valign="top" width="50%" style="padding: 0px 0 0 24px">
			
			<span class="fcsep_level0" style="margin:0 0 12px 0; background-color:#333; "><?php echo JText::_( /*'FLEXI_THIS_FIELDTYPE_PROPERTIES'*/'FIELD TYPE specific configuration' ); ?></span>
			
			<div id="fieldspecificproperties">
				<div class="fctabber fields_tabset" id="field_specific_props_tabset">
				<?php
				$fieldSets = $this->form->getFieldsets('attribs');
				$field_type = $this->form->getValue("field_type", NULL, "text");
				$prefix_len = strlen('group-'.$field_type);
				if ($field_type) foreach ($fieldSets as $name => $fieldSet)
				{
					if ($name!='basic' && $name!='standard' && substr($name, 0, $prefix_len)!='group-'.$field_type ) continue;
					if ($fieldSet->label) $label = JText::_($fieldSet->label);
					else $label = $name=='basic' || $name=='standard' ? JText::_('FLEXI_BASIC') : ucfirst(str_replace("group-", "", $name));
					?>
					<div class="tabbertab" id="fcform_tabset_<?php echo $name; ?>_tab">
						<h3 class="tabberheading"> <?php echo $label; ?> </h3>
						<?php $i = 0; ?>
						<?php foreach ($this->form->getFieldset($name) as $field) { 
							echo '<fieldset class="panelform '.($i ? '' : 'fc-nomargin').'">' . $field->label . $field->input . '</fieldset>' . "\n";
						} ?>
					</div>
					<?php
				} else {
					echo "<br /><span style=\"padding-left:25px;\"'>" . JText::_( 'FLEXI_APPLY_TO_SEE_THE_PARAMETERS' ) . "</span><br /><br />";
				}
				?>
				</div>
			</div>
			
		</td>
	</tr>
</table>
<?php echo JHTML::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<?php if ($this->form->getValue('iscore') == 1) : ?>
<input type="hidden" name="jform[iscore]" value="<?php echo $this->form->getValue("iscore"); ?>" />
<input type="hidden" name="jform[name]" value="<?php echo $this->form->getValue("name"); ?>" />
<?php endif; ?>
<input type="hidden" name="jform[id]" value="<?php echo $this->form->getValue("id"); ?>" />
<input type="hidden" name="controller" value="fields" />
<input type="hidden" name="view" value="field" />
<input type="hidden" name="task" value="" />
</form>
</div>
<div style="margin-bottom:24px;"></div>
			
<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>
