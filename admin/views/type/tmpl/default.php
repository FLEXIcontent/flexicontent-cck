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

<div id="flexicontent" class="flexicontent">
<form action="index.php" method="post" name="adminForm" id="adminForm">

	<div class="container-fluid">
		<div class="span6 full_width_980">
			
			<table class="fc-form-tbl" style="margin-bottom:12px;">
				<tr>
					<td class="key">
							<?php echo $this->form->getLabel('name'); ?>
					</td>
					<td>
						<?php echo $this->form->getInput('name'); ?>
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
				<?php if (FLEXI_ACCESS || FLEXI_J16GE) : ?>
				<tr>
					<td class="key">
						<?php echo $this->form->getLabel('itemscreatable'); ?>
					</td>
					<td>
						<?php echo $this->form->getInput('itemscreatable'); ?>
					</td>
				</tr>
				<?php endif; ?>
			</table>
		
		</div>
		<div class="span6 full_width_980">

			<span class="fc-info fc-nobgimage fc-mssg" style="display:block; float:left; clear:both; margin: 32px 0px 32px 0px !important; font-size:12px;">
				<?php echo str_replace('<br/>', ' ', JText::_('FLEXI_ITEM_PARAM_OVERRIDE_ORDER_DETAILS')); ?>
			</span>

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
				//echo JHtml::_('sliders.panel',JText::_($label), $fsname.'-options');
				if (isset($fieldSet->description) && trim($fieldSet->description)) :
					echo '<p class="tip">'.$this->escape(JText::_($fieldSet->description)).'</p>';
				endif;
				
				foreach ($this->form->getFieldset($fsname) as $field) :
					$_depends = FLEXI_J30GE ? $field->getAttribute('depend_class') :
						$this->form->getFieldAttribute($field->__get('fieldname'), 'depend_class', '', 'attribs');
					echo '
					<fieldset class="panelform'.($_depends ? ' '.$_depends : '').'" id="'.$field->id.'-container">
						'.($field->label ? '
							<span class="label-fcouter">'.str_replace('class="', 'class="label label-fcinner ', $field->label).'</span>
							<div class="container_fcfield">'.$field->input.'</div>
						' : $field->input).'
					</fieldset>
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
			//echo JHtml::_('sliders.panel',JText::_($label), $fsname.'-options');
			if (isset($fieldSet->description) && trim($fieldSet->description)) :
				echo '<p class="tip">'.$this->escape(JText::_($fieldSet->description)).'</p>';
			endif;
			
			foreach ($this->form->getFieldset($fsname) as $field) :
				$_depends = FLEXI_J30GE ? $field->getAttribute('depend_class') :
					$this->form->getFieldAttribute($field->__get('fieldname'), 'depend_class', '', 'attribs');
				echo '
				<fieldset class="panelform'.($_depends ? ' '.$_depends : '').'" id="'.$field->id.'-container">
					'.($field->label ? '
						<span class="label-fcouter">'.str_replace('class="', 'class="label label-fcinner ', $field->label).'</span>
						<div class="container_fcfield">'.$field->input.'</div>
					' : $field->input).'
				</fieldset>
				';
			endforeach;
		?>
		</div>
		
		<?php endforeach;
		//echo JHtml::_('sliders.end');
		?>
		
		
		<!-- Template tab -->
		<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-palette">
			<h3 class="tabberheading"> <?php echo JText::_('FLEXI_TEMPLATE'); ?> </h3>
		
			<div class="fc_tabset_inner">
				<?php
				echo '<span class="fc-info fc-nobgimage fc-mssg-inline" style="margin: 0px 0px 24px 0px !important; font-size:12px; min-width:100%; box-sizing:border-box;">' . JText::_( 'FLEXI_PARAMETERS_LAYOUT_EXPLANATION' ) ;
				?>
				<br/>
				<ol style="margin:0 0 0 16px; padding:0;">
					<li style="margin:0; padding:0;"> Select TEMPLATE layout </li>
					<li style="margin:0; padding:0;"> Open slider with TEMPLATE (layout) PARAMETERS </li>
				</ol>
				<br/>
				<b>NOTE:</b> Common method for -displaying- fields is by <b>editing the template layout</b> in template manager and placing the fields into <b>template positions</b>
				</span>
				<div class="clear"></div>
				
				<?php
				foreach ($this->form->getFieldset('themes') as $field) :
					$_depends = FLEXI_J30GE ? $field->getAttribute('depend_class') :
						$this->form->getFieldAttribute($field->__get('fieldname'), 'depend_class', '', 'attribs');
					echo '
					<fieldset class="panelform'.($_depends ? ' '.$_depends : '').'" id="'.$field->id.'-container">
						'.($field->label && empty($field->hidden) ? '
							<span class="label-fcouter">'.str_replace('class="', 'class="label label-fcinner ', $field->label).'</span>
							<div class="container_fcfield">'.$field->input.'</div>
						' : $field->input).'
					</fieldset>
					';
				endforeach;
				
				echo JHtml::_('sliders.start','theme-sliders-'.$this->form->getValue("id"), array('useCookie'=>1));
				$groupname = 'attribs';  // Field Group name this is for name of <fields name="..." >
				foreach ($this->tmpls as $tmplname => $tmpl) :
					$fieldSets = $tmpl->params->getFieldsets($groupname);
					foreach ($fieldSets as $fsname => $fieldSet) :
						$label = !empty($fieldSet->label) ? $fieldSet->label : JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ) . ' : ' . $tmpl->name;
						echo JHtml::_('sliders.panel',JText::_($label), $tmpl->name.'-'.$fsname.'-options');
						if (isset($fieldSet->description) && trim($fieldSet->description)) :
							echo '<p class="tip">'.$this->escape(JText::_($fieldSet->description)).'</p>';
						endif;
						?>
						<fieldset class="panelform">
							<?php foreach ($tmpl->params->getFieldset($fsname) as $field) :
								if ($field->getAttribute('not_inherited')) continue;
								$fieldname =  $field->__get('fieldname');
								$value = $tmpl->params->getValue($fieldname, $groupname, @$this->row->attribs[$fieldname]);
								echo str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_',
									$tmpl->params->getLabel($fieldname, $groupname));
								echo
									str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_', 
										str_replace('[attribs]', '[layouts]['.$tmpl->name.']',
											$tmpl->params->getInput($fieldname, $groupname, $value)
										)
									);
							endforeach; ?>
						</fieldset>
					<?php endforeach; //fieldSets ?>
				<?php endforeach; //tmpls ?>
				
				<?php echo JHtml::_('sliders.end'); ?>
				
		</div>	
	</div>
	
	
	<!-- Permissions tab -->
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-power-cord">
		<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_PERMISSIONS' ); ?> </h3>
		
		<div class="fc_tabset_inner">
			<div id="access"><?php echo $this->form->getInput('rules'); ?></div>
		</div>
		
	</div> <!-- end tab -->
	
</div> <!-- end of tab set -->

<?php echo JHTML::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<?php echo $this->form->getInput('id'); ?>
<input type="hidden" name="controller" value="types" />
<input type="hidden" name="view" value="type" />
<input type="hidden" name="task" value="" />
</form>
</div>


<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>
