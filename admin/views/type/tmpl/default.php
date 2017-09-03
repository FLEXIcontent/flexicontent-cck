<?php
/**
 * @version 1.5 stable $Id: default.php 1079 2012-01-02 00:18:34Z ggppdk $
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

// Load JS tabber lib
$this->document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', FLEXI_VHASH);
$this->document->addStyleSheetVersion(JUri::root(true).'/components/com_flexicontent/assets/css/tabber.css', FLEXI_VHASH);
$this->document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs
$js = "
	jQuery(document).ready(function(){
		fc_bindFormDependencies('#flexicontent', 0, '');
	});
";
$this->document->addScriptDeclaration($js);
?>

<div id="flexicontent" class="flexicontent">
<form action="index.php" method="post" name="adminForm" id="adminForm" class="form-validate form-horizontal">

	<div class="container-fluid">
		<div class="span6 full_width_980">
			
			<table class="fc-form-tbl" style="margin-bottom:12px;">
				
				<tr>
					<td class="key">
							<?php echo $this->form->getLabel('name'); ?>
					</td>
					<td>
						<?php echo $this->form->getInput('name'); ?>
						<input type="hidden" id="jform_title" name="jform[title]" value="<?php echo $this->form->getValue('name'); ?>" />
					</td>
				</tr>
				
				<tr>
					<td class="key">
						<?php echo $this->form->getLabel('published'); ?>
					</td>
					<td>
						<?php echo $this->form->getInput('published'); ?>
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
						<?php echo $this->form->getLabel('alias'); ?>
					</td>
					<td>
						<?php echo $this->form->getInput('alias'); ?>
					</td>
				</tr>
				
				<tr>
					<td class="key">
						<?php echo $this->form->getLabel('itemscreatable'); ?>
					</td>
					<td>
						<?php echo $this->form->getInput('itemscreatable'); ?>
					</td>
				</tr>
				
			</table>
		
		</div>
		<div class="span6 full_width_980">

			<div class="fc-info fc-nobgimage fc-mssg" style="display:block; float:left; clear:both; margin: 32px 0px 32px 0px !important; font-size:12px;">
				<?php echo str_replace('<br/>', ' ', JText::_('FLEXI_ITEM_PARAM_OVERRIDE_ORDER_DETAILS')); ?>
			</div>

		</div>
	</div>
	
	
	<div class="fctabber fields_tabset" id="field_specific_props_tabset">
		
		<div class="tabbertab" id="core_fields-options" data-icon-class="icon-cogs" >
			<h3 class="tabberheading"> <?php echo JText::_('FLEXI_CORE_FIELDS'); ?> </h3>
			
			<?php
			//echo JHtml::_('sliders.start','basic-sliders-'.$this->form->getValue("id"), array('useCookie'=>1));
			$fieldSets = $this->form->getFieldsets('attribs');
			$prefix_len = strlen('customize_field-');
			foreach ($fieldSets as $fsname => $fieldSet) :
				if ( substr($fsname, 0, $prefix_len)!='customize_field-' ) continue;
				
				$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_'.strtoupper($fsname).'_FIELDSET_LABEL';
				//echo JHtml::_('sliders.panel', JText::_($label), $fsname.'-options');
				if (isset($fieldSet->description) && trim($fieldSet->description)) :
					echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
				endif;
				
				foreach ($this->form->getFieldset($fsname) as $field) :
					$_depends = $field->getAttribute('depend_class');
					echo ($field->getAttribute('type')=='separator' || $field->hidden) ? $field->input : '
					<div class="control-group'.($_depends ? ' '.$_depends : '').'" id="'.$field->id.'-container">
						<div class="control-label">'.$field->label.'</div>
						<div class="controls">
							'.$this->getInheritedFieldDisplay($field, $this->cparams).'
						</div>
					</div>
					';
				endforeach;
			endforeach;
			//echo JHtml::_('sliders.end');
			?>
		</div>

		<?php
		//echo JHtml::_('sliders.start','basic-sliders-'.$this->form->getValue("id"), array('useCookie'=>1));
		$fieldSets = $this->form->getFieldsets('attribs');
		$prefix_len = strlen('customize_field-');
		foreach ($fieldSets as $fsname => $fieldSet) :
			if ( $fsname=='themes' || substr($fsname, 0, $prefix_len)=='customize_field-' ) continue;
			
			$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_'.strtoupper($fsname).'_FIELDSET_LABEL';
?>
		<div class="tabbertab" id="<?php echo $fsname; ?>-options" data-icon-class="<?php echo isset($fieldSet->icon_class) ? $fieldSet->icon_class : 'icon-pencil';?>" >
			<h3 class="tabberheading"> <?php echo JText::_($label); ?> </h3>
<?php
			//echo JHtml::_('sliders.panel', JText::_($label), $fsname.'-options');
			if (isset($fieldSet->description) && trim($fieldSet->description)) :
				echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
			endif;
			
			foreach ($this->form->getFieldset($fsname) as $field) :
				$_depends = $field->getAttribute('depend_class');
				if ( $field->getAttribute('box_type') )
					echo $field->input;
				else
					echo ($field->getAttribute('type')=='separator' || $field->hidden) ? $field->input : '
					<div class="control-group'.($_depends ? ' '.$_depends : '').'" id="'.$field->id.'-container">
						<div class="control-label">'.$field->label.'</div>
						<div class="controls">
							'.$this->getInheritedFieldDisplay($field, $this->cparams).'
						</div>
					</div>
					';
					
			endforeach;
		?>
		</div>
		
		<?php endforeach;
		//echo JHtml::_('sliders.end');
		?>
		
		
	<!-- Template tab -->
	<div class="tabbertab" id="themes-options" data-icon-class="icon-palette">
		<h3 class="tabberheading"> <?php echo JText::_('FLEXI_LAYOUT'); ?> </h3>
		
		<div class="fc_tabset_inner">
			
			<span class="btn-group input-append" style="margin: 2px 0px 6px;">
				<span id="fc-layouts-help_btn" class="btn" onclick="fc_toggle_box_via_btn('fc-layouts-help', this, 'btn-primary');" ><span class="icon-help"></span><?php echo JText::_('JHELP'); ?></span>
			</span>
			<div class="fcclear"></div>

			<div class="fc-info fc-nobgimage fc-mssg-inline" id="fc-layouts-help" style="margin: 2px 0px!important; font-size: 12px; display: none;">
				<h3 class="themes-title">
					<?php echo JText::_( 'FLEXI_PARAMETERS_LAYOUT_EXPLANATION' ); ?>
				</h3>
				<b>NOTE:</b> Common method for -displaying- fields is by <b>editing the template layout</b> in template manager and placing the fields into <b>template positions</b>
			</div>
			<div class="fcclear"></div>
			
			<?php
			foreach ($this->form->getFieldset('themes') as $field):
				if (!$field->label || $field->hidden)
				{
					echo $field->input;
					continue;
				}
				elseif ($field->input)
				{
					$_depends = $field->getAttribute('depend_class');
					echo '
					<div class="control-group'.($_depends ? ' '.$_depends : '').'" id="'.$field->id.'-container">
						<div class="control-label">'.$field->label.'</div>
						<div class="controls">
							'.$this->getInheritedFieldDisplay($field, $this->cparams).'
						</div>
					</div>
					';
				}
			endforeach; ?>
			
			<div class="fc-sliders-plain-outer">
				<?php
				echo JHtml::_('sliders.start','theme-sliders-'.$this->form->getValue("id"), array('useCookie'=>1));
				$groupname = 'attribs';  // Field Group name this is for name of <fields name="..." >
				$item_layout = $this->row->attribs->get('ilayout');
				
				foreach ($this->tmpls as $tmpl) :
					
					$form_layout = $tmpl->params;
					$label = '<span class="btn"><i class="icon-edit"></i>'.JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ) . ' : ' . $tmpl->name.'</span>';
					echo JHtml::_('sliders.panel', $label, $tmpl->name.'-'.$groupname.'-options');
					
					if ($tmpl->name != $item_layout) continue;

					// Display only current layout and only get globalb layout parameters for it
					$layoutParams = flexicontent_tmpl::getLayoutparams('items', $tmpl->name, '');
					$layoutParams = new JRegistry($layoutParams);
					
					$fieldSets = $form_layout->getFieldsets($groupname);
					foreach ($fieldSets as $fsname => $fieldSet) : ?>
						<fieldset class="panelform params_set">
						
						<?php
						if (isset($fieldSet->label) && trim($fieldSet->label)) :
							echo '<div style="margin:0 0 12px 0; font-size: 16px; background-color: #333; float:none;" class="fcsep_level0">'.JText::_($fieldSet->label).'</div>';
						endif;
						if (isset($fieldSet->description) && trim($fieldSet->description)) :
							echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
						endif;
						
						foreach ($form_layout->getFieldset($fsname) as $field) :
							
							if ($field->getAttribute('not_inherited')) continue;
							if ($field->getAttribute('cssprep')) continue;
							
							$fieldname = $field->fieldname;
							//$value = $form_layout->getValue($fieldname, $groupname, $this->row->attribs->get($fieldname));
							
							$input_only = !$field->label || $field->hidden;
							echo
								($input_only ? '' :
								str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_',
									$form_layout->getLabel($fieldname, $groupname)).'
								<div class="container_fcfield">
								').
								
								str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_', 
									str_replace('[attribs]', '[layouts]['.$tmpl->name.']',
										$this->getInheritedFieldDisplay($field, $layoutParams)
										//$form_layout->getInput($fieldname, $groupname/*, $value*/)   // Value already set, no need to pass it
									)
								).
								
								($input_only ? '' : '
								</div>
								');
						endforeach; ?>
						
						</fieldset>
						
					<?php endforeach; //fieldSets ?>
				<?php endforeach; //tmpls ?>
				
				<?php echo JHtml::_('sliders.end'); ?>
			</div>
		</div>
	</div>
	
	
	<!-- Permissions tab -->
	<div class="tabbertab" id="permissions-options" data-icon-class="icon-power-cord">
		<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_PERMISSIONS' ); ?> </h3>
		
		<div class="fc_tabset_inner">
			<div id="access"><?php echo $this->form->getInput('rules'); ?></div>
		</div>
		
	</div> <!-- end tab -->
	
</div> <!-- end of tab set -->

<?php echo JHtml::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<?php echo $this->form->getInput('id'); ?>
<input type="hidden" name="controller" value="types" />
<input type="hidden" name="view" value="type" />
<input type="hidden" name="task" value="" />
</form>
</div>


<?php
//keep session alive while editing
JHtml::_('behavior.keepalive');
?>
