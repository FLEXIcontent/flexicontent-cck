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
$hint_image = '<i class="icon-info"></i>';//JHtml::image ( 'administrator/components/com_flexicontent/assets/images/comments.png', JText::_( 'FLEXI_NOTES' ), 'style="vertical-align:top;"' );
$warn_image = '<i class="icon-warning"></i>';//JHtml::image ( 'administrator/components/com_flexicontent/assets/images/note.gif', JText::_( 'FLEXI_NOTES' ), 'style="vertical-align:top;"' );
$conf_image = '<i class="icon-cog"></i>';

$form = $this->form;

// Load JS tabber lib
$this->document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', FLEXI_VHASH);
$this->document->addStyleSheetVersion(JUri::root(true).'/components/com_flexicontent/assets/css/tabber.css', FLEXI_VHASH);
$this->document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs

// Add FC dependencies JS
$js = "
	jQuery(document).ready(function(){
		fc_bindFormDependencies('#flexicontent', 0, '');
	});
";

// Handle some readonly (server-side ignored) properties of CORE fields
// ... done by model's preprocessForm() method, but left here as an example
/*$js .= "
	jQuery( document ).ready(function() {".
		($form->getValue("id") > 0 && $form->getValue("id") < 7 ? "
		setTimeout(function(){ 
			jQuery('#jform_published').css('pointer-events', 'none').off('click');
			jQuery('#jform_published').find('.btn').addClass('disabled');
			jQuery('#jform_name').attr('readonly', 'readonly');
			jQuery('#jform_name').prop('readonly', true);
		}, 1);
		" : '')."
	});
";*/
$this->document->addScriptDeclaration($js);
?>

<div class="flexicontent" id="flexicontent">
<form action="index.php" method="post" name="adminForm" id="adminForm" class="form-validate form-horizontal">

<div class="container-fluid row" style="padding: 0px !important; margin: 0px !important">

	<div class="span6 col-6 full_width_980">
	
		<!--span class="badge"><h3><?php echo JText::_( /*'FLEXI_STANDARD_FIELDS_PROPERTIES'*/'Common configuration' ); ?></h3></span-->
		
		<table class="fc-form-tbl fcinner" style="margin-bottom:12px;">
			<tr>
				<td class="key">
					<?php echo $form->getLabel('label'); ?>
				</td>
				<td>
					<?php echo $form->getInput('label'); ?>
				</td>
			</tr>

			<tr>
				<td class="key">
					<?php echo $form->getLabel('name'); ?>
				</td>
				<td>
					<?php echo $form->getInput('name'); ?>
					<input type="hidden" id="jform_title" name="jform[title]" value="<?php echo $this->form->getValue('name'); ?>" />

					<?php if (! $form->getValue('iscore')) : ?>
						<span class="fc-info fc-nobgimage fc-mssg fc-mssg-inline <?php echo $tip_class; ?>" data-placement="bottom" title="<?php echo '<b>'.JText::_('FLEXI_NOTES').'</b><br/>'.JText::_('FLEXI_FIELDNAME_CHANGE_WARNING'); ?>">
							<?php echo $warn_image; ?>
							<?php echo $conf_image; ?>
							<?php echo JText::_('FLEXI_NOTES'); ?>
						</span>
					<?php endif; ?>

					<?php
					if ($form->getValue('field_type') === 'textarea')
					{
						$customize_mssg = flexicontent_html::getToolTip(JText::_('FLEXI_NOTES'), JText::sprintf('FLEXI_CORE_FIELDS_CUSTOMIZATION', 'text', '<b>'.JText::_('FLEXI_DESCRIPTION').'</b>', 'text'), 0, 1);
					}
					elseif ($form->getValue('field_type')=='maintext') 
					{
						$customize_mssg = flexicontent_html::getToolTip(JText::_('FLEXI_NOTES'), JText::sprintf('FLEXI_FIELD_CUSTOMIZE_PER_CONTENT_TYPE', 'textarea', 'text', 'text'), 0, 1);
					}
					?>

					<?php if (! empty($customize_mssg)) : ?>
						<span class="fc-info fc-nobgimage fc-mssg fc-mssg-inline <?php echo $tip_class; ?>" data-placement="bottom" title="<?php echo $customize_mssg; ?>">
							<?php echo $hint_image; ?>
							<?php echo $conf_image; ?>
							<?php echo JText::_('FLEXI_NOTES'); ?>
						</span>
					<?php endif; ?>

				</td>
			</tr>

			
			<?php if ($form->getValue("iscore") == 0) : ?>
			<tr>
				<td class="key">
				<?php echo $form->getLabel('field_type'); ?>
				</td>
				<td>
				<?php echo $this->lists['field_type']; ?>
				&nbsp;&nbsp;&nbsp;
				[ <span id="field_typename"><?php echo $form->getValue('field_type'); ?></span> ]
				</td>
			</tr>
			<?php endif; ?>

		</table>
		
		<div class="fctabber fields_tabset" id="field_basic_props_tabset">
			
			<div class="tabbertab" id="fcform_tabset_common_basic_tab" data-icon-class="icon-home-2" >
				<h3 class="tabberheading hasTooltip"> <?php echo JText::_( 'FLEXI_BASIC' ); ?> </h3>
				
				<table class="fc-form-tbl fcinner">
					<tr>
						<td class="key">
							<?php echo $form->getLabel('published'); ?>
						</td>
						<td>
							<?php echo $form->getInput('published'); ?>
						</td>
					</tr>
					
					<tr>
						<td class="key">
							<?php echo $form->getLabel('access'); ?>
						</td>
						<td>
							<?php echo $form->getInput('access'); ?>
						</td>
					</tr>
					
					<tr>
						<td class="key">
							<?php echo $form->getLabel('ordering'); ?>
						</td>
						<td>
							<?php echo $form->getInput('ordering'); ?>
						</td>
					</tr>
				</table>
				
				<div class="fcclear"></div>				

				<table class="fc-form-tbl fcinner">
					<tr>
						<td style="padding-top:24px;">
							<?php $box_class = $this->row->iscore ? 'fc-info' : ($this->typesselected ? 'fc-success' : 'fc-warning'); ?>
							<span class="<?php echo $box_class; ?> fc-mssg" style="width:90%; margin:6px 0px 0px 0px !important;">
								<?php echo JText::_( $this->row->iscore ? 'FLEXI_SELECT_TYPES_CORE_NOTES' : 'FLEXI_SELECT_TYPES_CUSTOM_NOTES' ); ?>
							</span>
						</td>
					</tr>
					<tr>
						<td>
							<label class="label label-warning" style="vertical-align:middle;">
								<?php echo JText::_( 'FLEXI_TYPES' ); ?>
							</label>
							<?php echo /*FLEXI_J16GE ? $form->getInput('tid') :*/ $this->lists['tid']; ?>
						</td>
					</tr>
				</table>
					
			</div>
			
			
			<div class="tabbertab" id="fcform_tabset_common_item_form_tab" data-icon-class="icon-pencil" >
				<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_ITEM_FORM' ); ?> </h3>
				<table class="fc-form-tbl fcinner">
					
					<tr<?php echo !$this->supportuntranslatable?' style="display:none;"':'';?>>
						<td class="key">
							<?php echo $form->getLabel('untranslatable'); ?>
						</td>
						<td>
							<?php echo $form->getInput('untranslatable'); ?>
						</td>
					</tr>
	
					<tr<?php echo !$this->supportformhidden?' style="display:none;"':'';?>>
						<td class="key">
							<?php echo $form->getLabel('formhidden'); ?>
						</td>
						<td>
							<?php echo $form->getInput('formhidden'); ?>
						</td>
					</tr>
					
					<tr<?php echo !$this->supportvalueseditable?' style="display:none;"':'';?>>
						<td class="key">
							<?php echo $form->getLabel('valueseditable'); ?>
						</td>
						<td>
							<?php echo $form->getInput('valueseditable'); ?>
						</td>
					</tr>
					
					<tr<?php echo !$this->supportedithelp?' style="display:none;"':'';?>>
						<td class="key">
							<?php echo $form->getLabel('edithelp'); ?>
						</td>
						<td>
							<?php echo $form->getInput('edithelp'); ?>
						</td>
					</tr>
					
					<tr>
						<td class="key">
							<?php echo $form->getLabel('description'); ?>
						</td>
						<td>
							<?php echo $form->getInput('description'); ?>
						</td>
					</tr>
					
				</table>
			</div>
			
			
			<?php if ($this->supportsearch || $this->supportfilter || $this->supportadvsearch || $this->supportadvfilter) : ?>
			<div class="tabbertab" id="fcform_tabset_common_search_filtering_tab" data-icon-class="icon-search" >
				<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_FIELD_SEARCH_FILTERING' ); ?> </h3>
				
				<?php if ($this->supportsearch || $this->supportfilter) : ?>
					<span class="fcsep_level1" style="width:90%; margin-top:16px;"><?php echo JText::_( 'FLEXI_BASIC_INDEX' ); ?></span>
					<span class="fcsep_level4 alert alert-info" style="margin-left: 32px;"><?php echo JText::_( 'FLEXI_BASIC_INDEX_NOTES' ); ?></span>
					<div class="fcclear"></div>
				
					<table class="fc-form-tbl fcinner">
						<?php if ($this->supportsearch) : ?>
						<tr>
							<td class="key">
								<?php echo $form->getLabel('issearch'); ?>
							</td>
							<td>
								<?php echo
									in_array($form->getValue('issearch'),array(-1,2)) ?
										JText::_($form->getValue('issearch')==-1 ? 'FLEXI_NO' : 'FLEXI_YES') .' -- 
										<a href="index.php?option=com_flexicontent&view=search&layout=indexer&tmpl=component&indexer=basic" class="btn btn-warning" onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 550, 350, function(){window.location.reload(false)}); return false;">'
											.JText::_('FLEXI_FIELD_DIRTY_REBUILD_SEARCH_INDEX').'
										</a>' :
										$form->getInput('issearch'); ?>
							</td>
						</tr>
						<?php endif; ?>
						
						<?php if ($this->supportfilter) : ?>
						<tr>
							<td class="key">
								<?php echo $form->getLabel('isfilter'); ?>
							</td>
							<td>
								<?php echo $form->getInput('isfilter'); ?>
							</td>
						</tr>
						<?php endif; ?>
					</table>
				<?php endif; ?>
				
				
				<?php if ($this->supportadvsearch || $this->supportadvfilter) : ?>
					<span class="fcsep_level1" style="width:90%; margin-top:16px; "><?php echo JText::_( 'FLEXI_ADV_INDEX' ); ?></span>
					<span class="fcsep_level4 alert alert-info" style="margin-left: 32px;"><?php echo JText::_( 'FLEXI_ADV_INDEX_NOTES' ); ?></span>
					<div class="fcclear"></div>
					
					<table class="fc-form-tbl fcinner">
						<?php if ($this->supportadvsearch) : ?>
						<tr>
							<td class="key">
								<?php echo $form->getLabel('isadvsearch'); ?>
							</td>
							<td>
								<?php echo
									in_array($form->getValue('isadvsearch'),array(-1,2)) ?
										JText::_($form->getValue('isadvsearch')==-1 ? 'FLEXI_NO' : 'FLEXI_YES') .' -- 
										<a href="index.php?option=com_flexicontent&view=search&layout=indexer&tmpl=component&indexer=advanced" class="btn btn-warning" onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 550, 350, function(){window.location.reload(false)}); return false;">'
											.JText::_('FLEXI_FIELD_DIRTY_REBUILD_SEARCH_INDEX').'
										</a>' :
										$form->getInput('isadvsearch'); ?>
							</td>
						</tr>
						<?php endif; ?>
						
						<?php if ($this->supportadvfilter) : ?>
						<tr>
							<td class="key">
								<?php echo $form->getLabel('isadvfilter'); ?>
							</td>
							<td>
								<?php echo
									in_array($form->getValue('isadvfilter'),array(-1,2)) ?
										JText::_($form->getValue('isadvfilter')==-1 ? 'FLEXI_NO' : 'FLEXI_YES') .' -- 
										<a href="index.php?option=com_flexicontent&view=search&layout=indexer&tmpl=component&indexer=advanced" class="btn btn-warning" onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 550, 350, function(){window.location.reload(false)}); return false;">'
											.JText::_('FLEXI_FIELD_DIRTY_REBUILD_SEARCH_INDEX').'
										</a>' :
										$form->getInput('isadvfilter'); ?>
							</td>
						</tr>
						<?php endif; ?>
					</table>
				<?php endif; ?>
				
			</div>
			<?php endif; ?>
			
			
			<?php if ($this->perms->CanConfig) :
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
			<div class="tabbertab" id="fcform_tabset_common_perms_tab" data-icon-class="icon-power-cord" >
				<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_PERMISSIONS' ); ?> </h3>
				
				<?php /*
				<fieldset id="flexiaccess" class="flexiaccess basicfields_set">
					<legend><?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT' ); ?></legend>
					<div id="tabacces">
				*/ ?>
						<div id="access"><?php echo $this->form->getInput('rules'); ?></div>
				<?php /*
					</div>
					<div id="notabacces">
					<?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT_DESC' ); ?>
					</div>
				</fieldset>
				*/ ?>
				
			</div>
			<?php endif; ?>		
			
		</div>

	</div>
	<div class="span6 col-6 full_width_980 padded_wrap_box">
			
		<span class="fcsep_level0" style="margin:0 0 12px 0; background-color:#777; "><?php echo JText::_( /*'FLEXI_THIS_FIELDTYPE_PROPERTIES'*/'FIELD TYPE specific configuration' ); ?></span>
			
		<div id="fieldspecificproperties">
			<div class="fctabber fields_tabset" id="field_specific_props_tabset">
			<?php
			// Remove hidden attribute
			foreach ($form->getXml()->config->fields->fieldset as $fieldset)
			{
				$fieldset->attributes()->hidden = null;
			}

			$fieldSets = $form->getFieldsets('attribs');
			$field_type = $form->getValue("field_type", NULL, "text");
			$prefix_len = strlen('group-'.$field_type.'-');

			if ($field_type) foreach ($fieldSets as $name => $fieldSet)
			{
				if ($name!='basic' && $name!='standard' && (substr($name, 0, $prefix_len)!='group-'.$field_type.'-' || $name==='group-'.$field_type) ) continue;
				if ($fieldSet->label) $label = JText::_($fieldSet->label);
				else $label = $name=='basic' || $name=='standard' ? JText::_('FLEXI_BASIC') : ucfirst(str_replace("group-", "", $name));
				
				if (@$fieldSet->label_prefix) $label = JText::_($fieldSet->label_prefix) .' - '. $label;
				$icon = @$fieldSet->icon_class ? 'data-icon-class="'.$fieldSet->icon_class.'"' : '';
				$prepend = @$fieldSet->prepend_text ? 'data-prefix-text="'.JText::_($fieldSet->prepend_text).'"' : '';
				
				$description = $fieldSet->description ? JText::_($fieldSet->description) : '';
				?>
				<div class="tabbertab" id="fcform_tabset_<?php echo $name; ?>_tab" <?php echo $icon; ?> <?php echo $prepend; ?>>
					<h3 class="tabberheading hasTooltip" title="<?php echo $description; ?>"><?php echo $label; ?> </h3>
					<?php $i = 0; ?>
					<?php foreach ($form->getFieldset($name) as $field) {
						$_depends = $field->getAttribute('depend_class');

						if ( $field->getAttribute('box_type') )
							echo $field->input;
						else
							echo '
						<fieldset class="panelform'.($i ? '' : ' fc-nomargin').' '.($_depends ? ' '.$_depends : '').'" id="'.$field->id.'-container">
							'.($field->label ? '
								<span class="label-fcouter">'.str_replace('class="', 'class="label-fcinner ', $field->label).'</span>
								<div class="container_fcfield">'.$field->input.'</div>
							' : $field->input).'
						</fieldset>
						';
						$i++;
					} ?>
				</div>
				<?php
			} else {
				echo "<br /><span style=\"padding-left:25px;\"'>" . JText::_( 'FLEXI_APPLY_TO_SEE_THE_PARAMETERS' ) . "</span><br /><br />";
			}
			?>
			</div>
		</div>

	</div>

</div>


	<?php echo JHtml::_( 'form.token' ); ?>
	<input type="hidden" name="option" value="com_flexicontent" />
	<?php echo $this->form->getInput('id'); ?>
	<input type="hidden" name="controller" value="fields" />
	<input type="hidden" name="view" value="field" />
	<input type="hidden" name="task" value="" />
	<?php echo $form->getInput('iscore'); ?>

</form>
</div>
<div style="margin-bottom:24px;"></div>

<?php
//keep session alive while editing
JHtml::_('behavior.keepalive');
?>
