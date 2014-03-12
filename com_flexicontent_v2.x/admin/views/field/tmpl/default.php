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
$infoimage 	= JHTML::image ( 'administrator/components/com_flexicontent/assets/images/lightbulb.png', JText::_( 'FLEXI_NOTES' ), ' style="vertical-align:top; margin-top:6px;" ' );
?>

<form action="index.php" method="post" class="form-validate" name="adminForm" id="adminForm">

<table cellspacing="0" cellpadding="0" border="0" width="100%">
	<tr>
		<td valign="top">
		
			<fieldset>
			<legend><?php echo JText::_( 'FLEXI_FIELD_PROPERTIES' ); ?></legend>
				<table class="admintable">
					<tr>
						<td class="key">
							<?php echo $this->form->getLabel('label').': *'; ?>
						</td>
						<td>
							<?php echo $this->form->getInput('label'); ?>
						</td>
					</tr>
					<?php if ($this->form->getValue('iscore') == 0) : ?>
					<tr>
						<td class="key">
							<?php echo $this->form->getLabel('name').': *'; ?>
						</td>
						<td>
							<?php echo $this->form->getInput('name'); ?>
						</td>
					</tr>
					<?php else : ?>
						<td class="key">
							<?php echo $this->form->getLabel('name'); ?>
						</td>
						<td>
							<?php echo $this->form->getValue("name"); ?>
						</td>
					<?php endif; ?>
					<?php
					$disabled = '';
					if ($this->form->getValue("id") > 0 && $this->form->getValue("id") < 7) $disabled = 'disabled="disabled"';
					?>
					<tr>
						<td class="key">
							<?php echo $this->form->getLabel('published').':'; ?>
						</td>
						<td>
							<?php
							$html = JHTML::_('select.booleanlist', 'jform[published]', 'class="inputbox"'.$disabled, $this->row->published );
							echo $html;
							?>
						</td>
					</tr>
					
					<?php if ($this->form->getValue("iscore") == 0) : ?>
					<tr>
						<td class="key">
							<?php echo $this->form->getLabel('field_type').': *'; ?>
						</td>
						<td>
							<?php echo $this->lists['fftype'] /*$this->form->getInput('field_type')*/; ?> &nbsp;&nbsp;&nbsp;
							[ <span id="field_typename"><?php echo $this->form->getValue('field_type'); ?></span> ]
						</td>
					</tr>
					<?php endif; ?>
					<tr>
						<td class="key">
							<?php echo $this->form->getLabel('ordering').': '; ?>
						</td>
						<td>
							<?php echo $this->form->getInput('ordering'); ?>
						</td>
					</tr>
					
					<?php if ($this->supportsearch || $this->supportfilter) : ?>
					<tr>
						<td colspan="2" class="key tbl_group" >
							<?php echo JText::_( 'FLEXI_CONTENT_LISTS' ); ?>
						</td>
					</tr>
					<?php endif; ?>
					
					<?php if ($this->supportsearch) : ?>
					<tr>
						<td class="key">
							<?php echo $this->form->getLabel('issearch').':'; ?>
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
							<?php echo $this->form->getLabel('isfilter').':'; ?>
						</td>
						<td>
							<?php echo $this->form->getInput('isfilter'); ?>
						</td>
					</tr>
					<?php endif; ?>
					

					<?php if ($this->supportadvsearch || $this->supportadvfilter) : ?>
					<tr>
						<td colspan="2" class="key tbl_group" >
							<?php echo JText::_( 'FLEXI_ADVANCED_SEARCH_VIEW' ); ?>
						</td>
					</tr>
					<?php endif; ?>
					
					<?php if ($this->supportadvsearch) : ?>
					<tr>
						<td class="key">
							<?php echo $this->form->getLabel('isadvsearch').':'; ?>
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
							<?php echo $this->form->getLabel('isadvfilter').':'; ?>
						</td>
						<td>
							<?php echo
								in_array($this->form->getValue('isadvfilter'),array(-1,2)) ?
									JText::_($this->form->getValue('isadvfilter')==-1 ? 'FLEXI_NO' : 'FLEXI_YES') .' -- '. JText::_('FLEXI_FIELD_ADVANCED_INDEX_PROPERTY_DIRTY') :
									$this->form->getInput('isadvfilter'); ?>
						</td>
					</tr>
					<?php endif; ?>
					
					<tr>
						<td colspan="2" class="key tbl_group" >
							<?php echo JText::_( 'FLEXI_ITEM_FORM' ); ?>
						</td>
					</tr>
					<tr<?php echo !$this->supportuntranslatable?' style="display:none;"':'';?>>
						<td class="key">
							<?php echo $this->form->getLabel('untranslatable').':'; ?>
						</td>
						<td>
							<?php echo $this->form->getInput('untranslatable'); ?>
						</td>
					</tr>

					<tr<?php echo !$this->supportformhidden?' style="display:none;"':'';?>>
					<tr>
						<td class="key">
							<?php echo $this->form->getLabel('formhidden').':'; ?>
						</td>
						<td>
							<?php echo $this->form->getInput('formhidden'); ?>
						</td>
					</tr>
					
					<?php if (FLEXI_ACCESS || FLEXI_J16GE) : ?>
					<tr<?php echo !$this->supportvalueseditable?' style="display:none;"':'';?>>
						<td class="key">
							<?php echo $this->form->getLabel('valueseditable').':'; ?>
						</td>
						<td>
							<?php echo $this->form->getInput('valueseditable'); ?>
						</td>
					</tr>
					<?php endif; ?>
					
					<tr<?php echo !$this->supportedithelp?' style="display:none;"':'';?>>
						<td class="key">
							<?php echo $this->form->getLabel('edithelp').':'; ?>
						</td>
						<td>
							<?php echo $this->form->getInput('edithelp'); ?>
						</td>
					</tr>
					
					<?php if (!FLEXI_ACCESS || FLEXI_J16GE) : ?>
					<tr>
						<td class="key">
							<?php echo $this->form->getLabel('access').':'; ?>
						</td>
						<td>
							<?php echo $this->form->getInput('access'); ?>
						</td>
					</tr>
					<?php endif; ?>
					
					<tr>
						<td class="key">
							<?php echo $this->form->getLabel('description'); ?>
						</td>
						<td>
							<?php echo $this->form->getInput('description'); ?>
						</td>
					</tr>
					
				</table>
			</fieldset>
			
			<?php
			if ($this->permission->CanConfig) :
				$this->document->addScriptDeclaration("
					window.addEvent('domready', function() {
						var slideaccess = new Fx.Slide('tabacces');
						var slidenoaccess = new Fx.Slide('notabacces');
						slideaccess.hide();
						$$('fieldset.flexiaccess legend').addEvent('click', function(ev) {
							slideaccess.toggle();
							slidenoaccess.toggle();
						});
					});
				");
			?>
			<fieldset class="flexiaccess">
				<legend><?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT' ); ?></legend>
				<table id="tabacces" class="admintable" width="100%">
					<tr>
						<td>
							<div id="access"><?php echo $this->form->getInput('rules'); ?></div>
						</td>
					</tr>
				</table>
				<div id="notabacces">
					<?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT_DESC' ); ?>
				</div>
			</fieldset>
		<?php endif; ?>
		
		</td>

		<td valign="top" width="40%" style="padding: 7px 0 0 5px">
			<table width="100%" style="border: 1px dashed silver; padding: 5px; margin-bottom: 10px;">
				<tr>
					<td width="40%">
						<strong><?php echo $this->form->getLabel('tid'); ?></strong>
						<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_NOTES' ); ?>::<?php echo JText::_( 'FLEXI_TYPES_NOTES' );?>">
							<?php echo $infoimage; ?>
						</span>
					</td>
					<td>
						<?php echo $this->form->getInput('tid'); ?>
					</td>
				</tr>
			</table>
	
			<div class="pane-sliders" id="det-pane">
				<div class="panel">
					<h3 id="standard-page" class="title jpane-toggler-down"><span><?php echo JText::_( 'FLEXI_STANDARD_FIELDS_PROPERTIES' ); ?></span></h3>
					<div class="jpane-slider content" style="border-top: medium none; border-bottom: medium none; overflow: hidden; padding-top: 0px; padding-bottom: 0px;">
					<?php
					foreach($this->form->getFieldset('basic') as $field) :
						//$input = str_replace("name=\"".$field->inputName."\"", "name=\"params[".$field->inputName."]\"", $field->input);
						?>
						<fieldset class="panelform">
						<?php echo $field->label; ?>
						<?php echo $field->input; ?>
						</fieldset>
						<?php
					endforeach;
					?>
					</div>
				</div>
				<div class="panel">
					<h3 id="group-page" class="title jpane-toggler-down"><span><?php echo JText::_( 'FLEXI_THIS_FIELDTYPE_PROPERTIES' ); ?></span></h3>
					<div id="fieldspecificproperties" class="jpane-slider content" style="border-top: medium none; border-bottom: medium none; overflow: hidden; padding-top: 0px; padding-bottom: 0px;">
					<?php
					$field_type = $this->form->getValue("field_type", NULL, "text");
					if ($field_type) {
						foreach($this->form->getFieldset('group-' . $field_type) as $field) :
							//$input = str_replace("name=\"".$field->inputName."\"", "name=\"params[".$field->inputName."]\"", $field->input);
							?>
							<fieldset class="panelform">
							<?php echo $field->label; ?>
							<?php echo $field->input; ?>
							</fieldset>
							<?php
						endforeach;
					} else {
						/*global $global_field_types;
						if(isset($global_field_types[0])) {
							// Create the form
							foreach($this->form->getFieldset('group-' . $global_field_types[0]->value) as $field) :
								//$input = str_replace("name=\"".$field->inputName."\"", "name=\"params[".$field->inputName."]\"", $field->input);
								?>
								<fieldset class="panelform">
								<?php echo $field->label; ?>
								<?php echo $field->input; ?>
								</fieldset>
								<?php
							endforeach;
						}else*/
						echo "<br /><span style=\"padding-left:25px;\"'>" . JText::_( 'FLEXI_APPLY_TO_SEE_THE_PARAMETERS' ) . "</span><br /><br />";
					}
					?>
					</div>
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
			
<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>
