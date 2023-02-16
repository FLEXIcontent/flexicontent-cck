<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

//adding inline help
if (FLEXI_J40GE) JToolbarHelper::inlinehelp();

// Load JS tabber lib
$this->document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', array('version' => FLEXI_VHASH));
$this->document->addStyleSheet(JUri::root(true).'/components/com_flexicontent/assets/css/tabber.css', array('version' => FLEXI_VHASH));
$this->document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs
$js = "
	jQuery(document).ready(function(){
		fc_bindFormDependencies('#flexicontent', 0, '');
	});
";
$this->document->addScriptDeclaration($js);
?>

<div id="flexicontent" class="flexicontent fcconfig-form">
<form action="index.php" method="post" name="adminForm" id="adminForm" class="form-validate form-horizontal">

	<div class="container-fluid row" style="padding: 0px !important; margin: 0px! important;">

		<div class="span6 col-6 full_width_980">

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

		<div class="span6 col-6 full_width_980">

			<div class="fc-info fc-nobgimage fc-mssg" style="display:block; float:left; clear:both; margin: 32px 0px 32px 0px !important; font-size:12px;">
				<?php echo str_replace('<br/>', ' ', JText::_('FLEXI_ITEM_PARAM_OVERRIDE_ORDER_DETAILS')); ?>
			</div>

		</div>

	</div>


	<div class="fctabber fields_tabset" id="field_specific_props_tabset">

		<div class="tabbertab" id="core_fields-options" data-icon-class="icon-paragraph-justify" >
			<h3 class="tabberheading"> <?php echo JText::_('FLEXI_DESCRIPTION'); ?> </h3>
			<div class="alert alert-success" style="display: inline-block;">
				<?php echo JText::_('FLEXI_REGARDING_ITEM_TYPE_DESCRIPTION_TEXT_USAGE'); ?>
			</div>
			<?php echo $this->form->getInput('description'); ?>

			</div>

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
							' . $this->getFieldInheritedDisplay($field, $this->cparams) . '
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
							' . $this->getFieldInheritedDisplay($field, $this->cparams) . '
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
							' . $this->getFieldInheritedDisplay($field, $this->cparams) . '
						</div>
					</div>
					';
				}
			endforeach; ?>

			<?php $item_layout = $this->row->attribs->get('ilayout'); ?>

			<div class="fc-sliders-plain-outer <?php echo $item_layout ? 'fc_preloaded' : ''; ?>">
				<?php
				$slider_set_id = 'theme-sliders-' . $this->form->getValue('id');
				//echo JHtml::_('sliders.start', $slider_set_id, array('useCookie'=>1));
				echo JHtml::_('bootstrap.startAccordion', $slider_set_id, array(/*'active' => ''*/));

				$groupname = 'attribs';  // Field Group name this is for name of <fields name="..." >

				foreach ($this->tmpls as $tmpl) :

					$form_layout = $tmpl->params;
					$slider_title = '
						<span class="btn"><i class="icon-edit"></i>
							' . JText::_('FLEXI_PARAMETERS_THEMES_SPECIFIC') . ' : ' . $tmpl->name . '
						</span>';
					$slider_id = $tmpl->name . '-' . $groupname . '-options';

					//echo JHtml::_('sliders.panel', $slider_title, $slider_id);
					echo JHtml::_('bootstrap.addSlide', $slider_set_id, $slider_title, $slider_id);

					if ($tmpl->name !== $item_layout)
					{
						echo JHtml::_('bootstrap.endSlide');
						continue;
					}

					// Display only current layout and only get global layout parameters for it
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
							//if ($field->getAttribute('cssprep')) continue;

							$fieldname  = $field->fieldname;
							$cssprep    = $field->getAttribute('cssprep');
							$labelclass = $cssprep == 'less' ? 'fc_less_parameter' : '';

							// For J3.7.0+ , we have extra form methods Form::getFieldXml()
							if ($cssprep && FLEXI_J37GE)
							{
								$_value = $form_layout->getValue($fieldname, $groupname, $layoutParams->get($fieldname));

								// Not only set the disabled attribute but also clear the required attribute to avoid issues with some fields (like 'color' field)
								$form_layout->setFieldAttribute($fieldname, 'disabled', 'true', $field->group);
								$form_layout->setFieldAttribute($fieldname, 'required', 'false', $field->group);

								$field->setup($form_layout->getFieldXml($fieldname, $field->group), $_value, $field->group);
							}

							echo ($field->getAttribute('type')=='separator' || $field->hidden || !$field->label)
							 ? $field->input
							 : '
								<div class="control-group" id="'.$field->id.'-container">
									<div class="control-label">'.
										str_replace('class="', 'class="'.$labelclass.' ',
											str_replace(' for="', ' data-for="',
												str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_',
													$form_layout->getLabel($fieldname, $groupname)
												)
											)
										) . '
									</div>
									<div class="controls">
										' . ($cssprep && !FLEXI_J37GE
											? (isset($this->iparams[$fieldname]) ? '<i>' . $this->iparams[$fieldname] . '</i>' : '<i>default</i>')
											:
											str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_',
												str_replace('[attribs]', '[layouts]['.$tmpl->name.']',
													$this->getFieldInheritedDisplay($field, $layoutParams)
													//$form_layout->getInput($fieldname, $groupname/*, $value*/)   // Value already set, no need to pass it
												)
											)
										) .
										($cssprep ? ' <span class="icon-info hasTooltip" title="' . JText::_('Used to auto-create a CSS styles file. To modify this, you can edit layout in template manager', true) . '"></span>' : '') . '
									</div>
								</div>
							';

						endforeach; ?>

						</fieldset>

					<?php endforeach; //fieldSets ?>
					<?php echo JHtml::_('bootstrap.endSlide'); ?>

				<?php endforeach; //tmpls ?>

				<?php echo JHtml::_('bootstrap.endAccordion'); //echo JHtml::_('sliders.end'); ?>

			</div><!-- END class="fc-sliders-plain-outer" -->
		</div> <!-- END class="fc_tabset_inner" -->

	</div><!-- END tabbertab FLEXI_LAYOUT -->



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
