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
$infoimage 	= JHTML::image ( 'administrator/components/com_flexicontent/assets/images/lightbulb.png', JText::_( 'FLEXI_NOTES' ), ' style="vertical-align:bottom;" ' );
?>

<form action="index.php" method="post" class="form-validate" name="adminForm" id="adminForm">

<table cellspacing="0" cellpadding="0" border="0" width="100%">
	<tr>
		<td valign="top">
		
			<fieldset>
			<legend><?php echo JText::_( 'FLEXI_FIELD_PROPERTIES' ); ?></legend>
				<table class="admintable">
					<tr>
						<td colspan="2">
							<span class="fcsep_level2" style="width:90%"><?php echo JText::_( 'FLEXI_BASIC' ); ?></span>
						</td>
					</tr>
					<tr>
						<td class="key">
							<label for="label" class="hasTip" title="<?php echo JText::_('FLEXI_FIELD_LABEL').'::'.JText::_('FLEXI_FIELD_FIELDLABEL_DESC');?>">
								<?php echo JText::_( 'FLEXI_FIELD_LABEL' ).'*'; ?>
							</label>
						</td>
						<td>
							<input id="label" name="label" value="<?php echo $this->row->label; ?>" class="required" maxlength="255" />
						</td>
					</tr>
					<?php if ($this->row->iscore == 0) : ?>
					<tr>
						<td class="key">
							<label for="name" class="hasTip" title="<?php echo JText::_('FLEXI_FIELD_NAME').'::'.JText::_('FLEXI_FIELD_FIELDNAME_DESC');?>">
								<?php echo JText::_( 'FLEXI_FIELD_NAME' ).'*'; ?>
							</label>
						</td>
						<td>
							<input id="name" name="name" value="<?php echo $this->row->name; ?>" class="required" />
						</td>
					</tr>
					<?php else : ?>
						<td class="key">
							<label for="name" class="hasTip" title="<?php echo JText::_('FLEXI_FIELD_NAME').'::'.JText::_('FLEXI_FIELD_FIELDNAME_DESC');?>">
								<?php echo JText::_( 'FLEXI_FIELD_NAME' ).'*'; ?>
							</label>
						</td>
						<td>
							<?php echo $this->row->name; ?>
						</td>
					<?php endif; ?>
					<?php
					$disabled = '';
					if ($this->row->id > 0 && $this->row->id < 7) $disabled = 'disabled="disabled"';
					?>
					<tr>
						<td class="key">
							<label class="hasTip" title="<?php echo JText::_('FLEXI_PUBLISHED').'::'.JText::_('FLEXI_FIELD_PUBLISHED_DESC');?>">
								<?php echo JText::_( 'FLEXI_PUBLISHED' ); ?>
							</label>
						</td>
						<td>
							<?php
							$html = JHTML::_('select.booleanlist', 'published', 'class="inputbox"'.$disabled, $this->row->published );
							echo $html;
							?>
						</td>
					</tr>
					
					<?php if ($this->row->iscore == 0) : ?>
					<tr>
						<td class="key">
							<label for="field_type" class="hasTip" title="<?php echo JText::_('FLEXI_FIELD_TYPE').'::'.JText::_('FLEXI_FIELD_FIELDTYPE_DESC');?>">
								<?php echo JText::_( 'FLEXI_FIELD_TYPE' ).'*'; ?>
							</label>
						</td>
						<td>
							<?php echo $this->lists['field_type']; ?>
							&nbsp;&nbsp;&nbsp;
							[ <span id="field_typename"><?php echo $this->row->field_type; ?></span> ]
						</td>
					</tr>
					<?php endif; ?>
					<tr>
						<td class="key">
							<label for="ordering" class="hasTip" title="<?php echo JText::_('Ordering').'::'.JText::_('FLEXI_FIELD_ORDER_DESC');?>">
								<?php echo JText::_( 'Ordering' ); ?>
							</label>
						</td>
						<td>
							<?php echo $this->lists['ordering']; ?>
						</td>
					</tr>

					<tr>
						<td colspan="2">
							<?php $box_class = $this->row->iscore ? 'fc-info' : ($this->typesselected ? 'fc-success' : 'fc-warning'); ?>
							<span class="<?php echo $box_class; ?> fc-mssg" style="width:90%; margin:6px 0px 0px 0px !important;">
								<?php echo JText::_( $this->row->iscore ? 'FLEXI_SELECT_TYPES_CORE_NOTES' : 'FLEXI_SELECT_TYPES_CUSTOM_NOTES' ); ?>
							</span>
						</td>
					</tr>
					
					<tr>
						<td colspan="2">
							<span class="flexi label hasTip" title="<?php echo JText::_('FLEXI_TYPES').'::'.JText::_('FLEXI_TYPES_NOTES');?>">
								<?php echo JText::_( 'FLEXI_TYPES' ); ?>
							</span>
							<?php echo /*FLEXI_J16GE ? $this->form->getInput('tid') :*/ $this->lists['tid']; ?>
						</td>
					</tr>

					<?php if ($this->supportsearch || $this->supportfilter) : ?>
					<tr>
						<td colspan="2">
							<span class="fcsep_level2" style="width:90%; margin-top:16px;"><?php echo JText::_( 'FLEXI_BASIC_INDEX' ); ?></span>
							<span class="fcsep_level3"><?php echo JText::_( 'FLEXI_BASIC_INDEX_NOTES' ); ?></span>
						</td>
					</tr>
					<?php endif; ?>
					
					<?php if ($this->supportsearch) : ?>
					<tr>
						<td class="key">
							<label class="hasTip" title="<?php echo JText::_('FLEXI_FIELD_CONTENT_LIST_TEXT_SEARCHABLE').'::'.JText::_('FLEXI_FIELD_CONTENT_LIST_TEXT_SEARCHABLE_DESC');?>">
								<?php echo JText::_( 'FLEXI_FIELD_CONTENT_LIST_TEXT_SEARCHABLE' ); ?>
							</label>
						</td>
						<td>
							<?php echo
								in_array($this->form->getValue('issearch'),array(-1,2)) ?
									JText::_($this->form->getValue('issearch')==-1 ? 'FLEXI_NO' : 'FLEXI_YES') .' -- '. JText::_('FLEXI_FIELD_BASIC_INDEX_PROPERTY_DIRTY') :
									JHTML::_('select.booleanlist', 'issearch', 'class="inputbox"', $this->row->issearch ); ?>
						</td>
					</tr>
					<?php endif; ?>
					
					<?php if ($this->supportfilter) : ?>
					<tr>
						<td class="key">
							<label class="hasTip" title="<?php echo JText::_('FLEXI_FIELD_CONTENT_LIST_FILTERABLE').'::'.JText::_('FLEXI_FIELD_CONTENT_LIST_FILTERABLE_DESC');?>">
								<?php echo JText::_( 'FLEXI_FIELD_CONTENT_LIST_FILTERABLE' ); ?>
							</label>
						</td>
						<td>
							<?php echo JHTML::_('select.booleanlist', 'isfilter', 'class="inputbox"', $this->row->isfilter ); ?>
						</td>
					</tr>
					<?php endif; ?>
					

					<?php if ($this->supportadvsearch || $this->supportadvfilter) : ?>
					<tr>
						<td colspan="2">
							<span class="fcsep_level2" style="width:90%; margin-top:16px; "><?php echo JText::_( 'FLEXI_ADV_INDEX' ); ?></span>
							<span class="fcsep_level3"><?php echo JText::_( 'FLEXI_ADV_INDEX_NOTES' ); ?></span>
						</td>
					</tr>
					<?php endif; ?>
					
					<?php if ($this->supportadvsearch) : ?>
					<tr>
						<td class="key">
							<label class="hasTip" title="<?php echo JText::_('FLEXI_FIELD_ADVANCED_TEXT_SEARCHABLE').'::'.JText::_('FLEXI_FIELD_ADVANCED_TEXT_SEARCHABLE_DESC');?>">
								<?php echo JText::_( 'FLEXI_FIELD_ADVANCED_TEXT_SEARCHABLE' ); ?>
							</label>
						</td>
						<td>
							<?php echo
								in_array($this->form->getValue('isadvsearch'),array(-1,2)) ?
									JText::_($this->form->getValue('isadvsearch')==-1 ? 'FLEXI_NO' : 'FLEXI_YES') .' -- '. JText::_('FLEXI_FIELD_ADVANCED_INDEX_PROPERTY_DIRTY') :
									JHTML::_('select.booleanlist', 'isadvsearch', 'class="inputbox"', $this->row->isadvsearch ); ?>
						</td>
					</tr>
					<?php endif; ?>
					
					<?php if ($this->supportadvfilter) : ?>
					<tr>
						<td class="key">
							<label class="hasTip" title="<?php echo JText::_('FLEXI_FIELD_ADVANCED_FILTERABLE').'::'.JText::_('FLEXI_FIELD_ADVANCED_FILTERABLE_DESC');?>">
								<?php echo JText::_( 'FLEXI_FIELD_ADVANCED_FILTERABLE' ); ?>
							</label>
						</td>
						<td>
							<?php echo
								in_array($this->form->getValue('isadvfilter'),array(-1,2)) ?
									JText::_($this->form->getValue('isadvfilter')==-1 ? 'FLEXI_NO' : 'FLEXI_YES') .' -- '. JText::_('FLEXI_FIELD_ADVANCED_INDEX_PROPERTY_DIRTY') :
									JHTML::_('select.booleanlist', 'isadvfilter', 'class="inputbox"', $this->row->isadvfilter ); ?>
						</td>
					</tr>
					<?php endif; ?>
					
					<tr>
						<td colspan="2">
							<span class="fcsep_level2" style="width:90%; margin-top:16px; "><?php echo JText::_( 'FLEXI_ITEM_FORM' ); ?></span>
						</td>
					</tr>
					<tr<?php echo !$this->supportuntranslatable?' style="display:none;"':'';?>>
						<td class="key">
							<label class="hasTip" title="<?php echo JText::_('FLEXI_FIELD_UNTRANSLATABLE').'::'.JText::_('FLEXI_FIELD_UNTRANSLATABLE_DESC');?>">
								<?php echo JText::_( 'FLEXI_FIELD_UNTRANSLATABLE' ); ?>
							</label>
						</td>
						<td>
							<?php
							$html = JHTML::_('select.booleanlist', 'untranslatable', 'class="inputbox"', $this->row->untranslatable );
							echo $html;
							?>
						</td>
					</tr>

					<tr<?php echo !$this->supportformhidden?' style="display:none;"':'';?>>
						<td class="key">
							<label for="access" class="hasTip" title="<?php echo JText::_('FLEXI_FORM_HIDDEN').'::'.JText::_('FLEXI_FORM_HIDDEN_DESC');?>">
								<?php echo JText::_( 'FLEXI_FORM_HIDDEN' ); ?>
							</label>
						</td>
						<td>
							<?php echo $this->lists['formhidden']; ?>
						</td>
					</tr>
					
					<?php if (FLEXI_ACCESS || FLEXI_J16GE) : ?>
					<tr<?php echo !$this->supportvalueseditable?' style="display:none;"':'';?>>
						<td class="key">
							<label for="access" class="hasTip" title="<?php echo JText::_('FLEXI_VALUES_EDITABLE_BY').'::'.JText::_('FLEXI_VALUES_EDITABLE_BY_DESC');?>">
								<?php echo JText::_( 'FLEXI_VALUES_EDITABLE_BY' ); ?>
							</label>
						</td>
						<td>
							<?php echo $this->lists['valueseditable']; ?>
						</td>
					</tr>
					<?php endif; ?>
					
					<tr<?php echo !$this->supportedithelp?' style="display:none;"':'';?>>
						<td class="key">
							<label for="access" class="hasTip" title="<?php echo JText::_('FLEXI_EDIT_HELP').'::'.JText::_('FLEXI_EDIT_HELP_DESC');?>">
								<?php echo JText::_( 'FLEXI_EDIT_HELP' ); ?>
							</label>
						</td>
						<td>
							<?php echo $this->lists['edithelp']; ?>
						</td>
					</tr>
					
					<tr>
						<td class="key">
							<label for="description" class="hasTip" title="<?php echo JText::_('FLEXI_FIELD_DESCRIPTION').'::'.JText::_('FLEXI_FIELD_DESCRIPTION_DESC');?>">
								<?php echo JText::_( 'FLEXI_FIELD_DESCRIPTION' ); ?>
							</label>
						</td>
						<td>
							<textarea id="description" cols="30" rows="5" name="description"><?php echo $this->row->description; ?></textarea>
						</td>
					</tr>
					
					<?php if (!FLEXI_ACCESS || FLEXI_J16GE) : ?>
					<tr>
						<td class="key">
							<label for="access" class="hasTip" title="<?php echo JText::_('FLEXI_ACCESS_LEVEL').'::'.JText::_('FLEXI_FIELD_ACCESSLEVEL_DESC');?>">
								<?php echo JText::_( 'FLEXI_ACCESS_LEVEL' ); ?>
							</label>
						</td>
						<td>
							<?php echo $this->lists['access']; ?>
						</td>
					</tr>
					<?php endif; ?>
					
				</table>
			</fieldset>
			
			<?php
			if (FLEXI_ACCESS) :
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
							<div id="access"><?php echo $this->lists['access']; ?></div>
						</td>
					</tr>
				</table>
				<div id="notabacces">
					<?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT_DESC' ); ?>
				</div>
			</fieldset>
		<?php endif; ?>
		
		</td>

		<td valign="top" width="50%" style="padding: 7px 0 0 24px">
			
			<div class="pane-sliders" id="det-pane" style="margin-top:0px !important;">
				
				<div class="panel" style="margin-bottom:24px !important; padding:2px !important;">
					<h3 id="standard-page" class="title jpane-toggler-down"><span><?php echo JText::_( 'FLEXI_STANDARD_FIELDS_PROPERTIES' ); ?></span></h3>
					<div class="jpane-slider content" style="border-top: medium none; border-bottom: medium none; overflow: hidden; padding-top: 6px; padding-bottom: 6px;">
						
						<?php
						echo $this->form->render('params', 'standard');
						?>
					</div>
					
				</div>
				
				<div class="panel" style="padding:2px !important;">
					<h3 id="group-page" class="title jpane-toggler-down"><span><?php echo JText::_( 'FLEXI_THIS_FIELDTYPE_PROPERTIES' ); ?></span></h3>
					<div id="fieldspecificproperties" class="jpane-slider content" style="border-top: medium none; border-bottom: medium none; overflow: hidden; padding-top: 6px; padding-bottom: 6px;">
					<?php
					if ($this->row->field_type)
					{
						echo $this->form->render('params', 'group-' . $this->row->field_type );
					} else {
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
<?php if ($this->row->iscore == 1) : ?>
<input type="hidden" name="iscore" value="<?php echo $this->row->iscore; ?>" />
<input type="hidden" name="name" value="<?php echo $this->row->name; ?>" />
<input type="hidden" name="field_type" value="<?php echo $this->row->field_type; ?>" />
<?php endif; ?>
<input type="hidden" name="id" value="<?php echo $this->row->id; ?>" />
<input type="hidden" name="controller" value="fields" />
<input type="hidden" name="view" value="field" />
<input type="hidden" name="task" value="" />
</form>
			
<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>
