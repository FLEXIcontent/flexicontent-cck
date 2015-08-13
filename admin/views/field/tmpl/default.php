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
$hint_image = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/comment.png', JText::_( 'FLEXI_NOTES' ), 'style="vertical-align:top;"' );
unset($form);
$form = & $this->form;

// Load JS tabber lib
$this->document->addScript(JURI::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js');
$this->document->addStyleSheet(JURI::root(true).'/components/com_flexicontent/assets/css/tabber.css');
$this->document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs
$js = "
	jQuery(document).ready(function(){
		fc_bind_form_togglers('#flexicontent', 0, '');
	});
";
$this->document->addScriptDeclaration($js);
?>

<div class="flexicontent" id="flexicontent">
  <form action="index.php" method="post" class="form-validate form-horizontal" name="adminForm" id="adminForm">
    <div class="container-fluid" style="padding:0px!important;">
      <div class="span6 full_width_980"> 
        
        <!--span class="badge"><h3><?php echo JText::_( /*'FLEXI_STANDARD_FIELDS_PROPERTIES'*/'Common configuration' ); ?></h3></span-->
        
        <div class="block-flat fc-form-tbl">
          <div class="control-group">
            <div class="control-label"> <?php echo $form->getLabel('label'); ?></div>
            <div class="controls"> <?php echo $form->getInput('label'); ?> </div>
          </div>
          <?php if ($form->getValue('iscore') == 0) : ?>
          <div class="control-group">
            <div class="control-label"> <?php echo $form->getLabel('name'); ?> </div>
            <div class="controls"> <?php echo $form->getInput('name'); ?>
              <?php if ($form->getValue('field_type')=='textarea') : ?>
              <span class="fc-info fc-nobgimage fc-mssg fc-mssg-inline"><?php echo JText::_('FLEXI_NOTES'); ?>: <span class="<?php echo $tip_class; ?>" data-placement="bottom"
									title="<?php echo flexicontent_html::getToolTip(JText::_('FLEXI_NOTES'), JText::sprintf('FLEXI_CORE_FIELDS_CUSTOMIZATION', 'text', '<b>'.JText::_('FLEXI_DESCRIPTION').'</b>', 'text'), 0, 1); ?>"> <?php echo $hint_image; ?> </span> </span>
              <?php endif; ?>
            </div>
          </div>
          <?php else : ?>
          <div class="control-group">
            <div class="control-label"> <?php echo $form->getLabel('name'); ?></div>
            <div class="controls"> <span class="badge badge-info"><?php echo $form->getValue("name"); ?></span>
              <?php if ($form->getValue('field_type')=='maintext') : ?>
              <span class="fc-info fc-nobgimage fc-mssg fc-mssg-inline"><?php echo JText::_('FLEXI_NOTES'); ?>: <span class="<?php echo $tip_class; ?>" data-placement="bottom"
									title="<?php echo flexicontent_html::getToolTip(JText::_('FLEXI_NOTES'), JText::sprintf('FLEXI_FIELD_CUSTOMIZE_PER_CONTENT_TYPE', 'textarea', 'text', 'text'), 0, 1); ?>"> <?php echo $hint_image; ?> </span> </span>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($form->getValue("iscore") == 0) : ?>
          <div class="control-group">
            <div class="control-label"> <?php echo $form->getLabel('field_type'); ?> </div>
            <div class="controls"> <?php echo $this->lists['field_type']; ?> &nbsp;&nbsp;&nbsp;
              [ <span id="field_typename"><?php echo $form->getValue('field_type'); ?></span> ] </div>
          </div>
          <?php endif; ?>
        </div>
        <div class="fctabber fields_tabset" id="field_specific_props_tabset">
          <div class="tabbertab" id="fcform_tabset_common_basic_tab" data-icon-class="icon-home-2" >
            <h3 class="tabberheading hasTooltip"> <?php echo JText::_( 'FLEXI_BASIC' ); ?> </h3>
            <div class="fc-form-tbl">
              <div class="control-group">
                <div class="control-label"><?php echo $form->getLabel('published'); ?></div>
                <div class="controls"> <?php echo $form->getInput('published'); ?>
                  <?php
							$disabled = ($form->getValue("id") > 0 && $form->getValue("id") < 7);
							if ($disabled) {
								$this->document->addScriptDeclaration("
									jQuery( document ).ready(function() {
										setTimeout(function(){ 
											jQuery('#jform_published input').attr('disabled', 'disabled').off('click');
												jQuery('#jform_published label').attr('disabled', true).css('pointer-events', 'none').off('click');
										}, 1);
									});
								");
							}
							?>
                </div>
              </div>
              <div class="control-group">
                <div class="control-label"><?php echo $form->getLabel('access'); ?></div>
                <div class="controls"> <?php echo $form->getInput('access'); ?> </div>
              </div>
              <div class="control-group">
                <div class="control-label"><?php echo $form->getLabel('ordering'); ?></div>
                <div class="controls"> <?php echo $form->getInput('ordering'); ?> </div>
              </div>
              <div class="control-group">
                <?php $box_class = $this->row->iscore ? 'fc-info' : ($this->typesselected ? 'fc-success' : 'fc-warning'); ?>
                <div class="controls"><span class="<?php echo $box_class; ?> fc-mssg" style="width:90%; margin:6px 0px 0px 0px !important;"> <?php echo JText::_( $this->row->iscore ? 'FLEXI_SELECT_TYPES_CORE_NOTES' : 'FLEXI_SELECT_TYPES_CUSTOM_NOTES' ); ?> </span> </div>
              </div>
              <div class="control-group">
                <div class="control-label">
                  <label class="label label-warning"><?php echo JText::_( 'FLEXI_TYPES' ); ?></label>
                </div>
                <div class="controls"><?php echo /*FLEXI_J16GE ? $form->getInput('tid') :*/ $this->lists['tid']; ?></div>
              </div>
            </div>
          </div>
          <div class="tabbertab" id="fcform_tabset_common_item_form_tab" data-icon-class="icon-pencil" >
            <h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_ITEM_FORM' ); ?> </h3>
            <div class="fc-form-tbl">
              <div class="control-group <?php echo !$this->supportuntranslatable?' none':'';?>">
                <div class="control-label"> <?php echo $form->getLabel('untranslatable'); ?></div>
                <div class="controls"> <?php echo $form->getInput('untranslatable'); ?> </div>
              </div>
              <div class="control-group <?php echo !$this->supportformhidden?' none':'';?>">
                <div class="control-label"> <?php echo $form->getLabel('formhidden'); ?></div>
                <div class="controls"> <?php echo $form->getInput('formhidden'); ?> </div>
              </div>
              <div class="control-group <?php echo !$this->supportvalueseditable?' none':'';?>">
                <div class="control-label"> <?php echo $form->getLabel('valueseditable'); ?></div>
                <div class="controls"> <?php echo $form->getInput('valueseditable'); ?> </div>
              </div>
              <div class="control-group <?php echo !$this->supportedithelp?' none':'';?>">
                <div class="control-label"> <?php echo $form->getLabel('edithelp'); ?></div>
                <div class="controls"> <?php echo $form->getInput('edithelp'); ?> </div>
              </div>
              <div class="control-group">
                <div class="control-label"> <?php echo $form->getLabel('description'); ?></div>
                <div class="controls"> <?php echo $form->getInput('description'); ?> </div>
              </div>
            </div>
          </div>
          <?php if ($this->supportsearch || $this->supportfilter || $this->supportadvsearch || $this->supportadvfilter) : ?>
          <div class="tabbertab" id="fcform_tabset_common_search_filtering_tab" data-icon-class="icon-search" >
            <h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_FIELD_SEARCH_FILTERING' ); ?> </h3>
            <?php if ($this->supportsearch || $this->supportfilter) : ?>
            <div class="row-fluid">
              <div class="span12"> <span class="fcsep_level1"><?php echo JText::_( 'FLEXI_BASIC_INDEX' ); ?></span> <span class="fcsep_level4"><?php echo JText::_( 'FLEXI_BASIC_INDEX_NOTES' ); ?></span> </div>
            </div>
            <div class="fc-form-tbl m20">
              <?php if ($this->supportsearch) : ?>
              <div class="control-group">
                <div class="control-label"><?php echo $form->getLabel('issearch'); ?></div>
                <div class="controls"> <?php echo
									in_array($form->getValue('issearch'),array(-1,2)) ?
										JText::_($form->getValue('issearch')==-1 ? 'FLEXI_NO' : 'FLEXI_YES') .' -- '. JText::_('FLEXI_FIELD_BASIC_INDEX_PROPERTY_DIRTY') :
										$form->getInput('issearch'); ?></div>
              </div>
              <?php endif; ?>
              <?php if ($this->supportfilter) : ?>
              <div class="control-group">
                <div class="control-label"><?php echo $form->getLabel('isfilter'); ?></div>
                <div class="controls"><?php echo $form->getInput('isfilter'); ?></div>
              </div>
              <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($this->supportadvsearch || $this->supportadvfilter) : ?>
            <div class="row-fluid">
              <div class="span12"> <span class="fcsep_level1"><?php echo JText::_( 'FLEXI_ADV_INDEX' ); ?></span> <span class="fcsep_level4"><?php echo JText::_( 'FLEXI_ADV_INDEX_NOTES' ); ?></span> </div>
            </div>
            <div class="fcclear"></div>
            <div class="fc-form-tbl m20">
              <?php if ($this->supportadvsearch) : ?>
              <div class="control-group">
                <div class="control-label"><?php echo $form->getLabel('isadvsearch'); ?></div>
                <div class="controls"><?php echo
									in_array($form->getValue('isadvsearch'),array(-1,2)) ?
										JText::_($form->getValue('isadvsearch')==-1 ? 'FLEXI_NO' : 'FLEXI_YES') .' -- '. JText::_('FLEXI_FIELD_ADVANCED_INDEX_PROPERTY_DIRTY') :
										$form->getInput('isadvsearch'); ?></div>
              </div>
              <?php endif; ?>
              <?php if ($this->supportadvfilter) : ?>
              <div class="control-group">
                <div class="control-label"><?php echo $form->getLabel('isadvfilter'); ?></div>
                <div class="controls"><?php echo
									in_array($form->getValue('isadvfilter'),array(-1,2)) ?
										JText::_($form->getValue('isadvfilter')==-1 ? 'FLEXI_NO' : 'FLEXI_YES') .' -- '. JText::_('FLEXI_FIELD_ADVANCED_INDEX_PROPERTY_DIRTY') :
										$form->getInput('isadvfilter'); ?></div>
              </div>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
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
      <div class="span6 full_width_980 padded_wrap_box">
        <div class="row-fluid">
          <div class="span12"> <span class="fcsep_level0"><?php echo JText::_( /*'FLEXI_THIS_FIELDTYPE_PROPERTIES'*/'FIELD TYPE specific configuration' ); ?></span> </div>
        </div>
        <div id="fieldspecificproperties" class="m20">
          <div class="fctabber fields_tabset" id="field_specific_props_tabset" >
            <?php
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
							$_depends = FLEXI_J30GE ? $field->getAttribute('depend_class') :
								$form->getFieldAttribute($field->__get('fieldname'), 'depend_class', '', 'attribs');
							echo '
							<fieldset class="control-group" id="'.$field->id.'-container">
								'.($field->label ? '
									<div class="control-label">'.$field->label.'</div>
									<div class="controls">'.$field->input.'</div>
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
    <?php echo JHTML::_( 'form.token' ); ?>
    <input type="hidden" name="option" value="com_flexicontent" />
    <?php if ($form->getValue('iscore') == 1) : ?>
    <input type="hidden" name="jform[iscore]" value="<?php echo $form->getValue("iscore"); ?>" />
    <input type="hidden" name="jform[name]" value="<?php echo $form->getValue("name"); ?>" />
    <?php endif; ?>
    <input type="hidden" name="jform[id]" value="<?php echo $form->getValue("id"); ?>" />
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
