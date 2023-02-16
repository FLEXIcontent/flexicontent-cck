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

$useAssocs = flexicontent_db::useAssociations();

// For tabsets/tabs ids (focusing, etc)
$tabSetCnt = -1;
$tabSetMax = -1;
$tabCnt = array();
$tabSetStack = array();

//keep session alive while editing
JHtml::_('behavior.keepalive');

// Load JS tabber lib
$this->document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', array('version' => FLEXI_VHASH));
$this->document->addStyleSheet(JUri::root(true).'/components/com_flexicontent/assets/css/tabber.css', array('version' => FLEXI_VHASH));
$this->document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs
$js = "
	jQuery(document).ready(function(){
		fc_bindFormDependencies('#flexicontent', 2, '.control-group');
	});
";
$this->document->addScriptDeclaration($js);
//adding inline help
if (FLEXI_J40GE) JToolbarHelper::inlinehelp();
?>

<div id="flexicontent" class="flexicontent fcconfig-form">
<form action="index.php" method="post" name="adminForm" id="adminForm" class="form-validate form-horizontal">


	<div class="fcclear"></div>

	<div class="form-inline form-inline-header">

		<div class="control-group">
			<div class="control-label"> <?php echo $this->form->getLabel('title'); ?> </div>
			<div class="controls">
				<?php echo $this->form->getInput('title'); ?>
			</div>
		</div>

		<div class="control-group">
			<div class="control-label"> <?php echo $this->form->getLabel('alias'); ?> </div>
			<div class="controls">
				<?php echo $this->form->getInput('alias'); ?>
			</div>
		</div>

	</div>

	<div class="fcclear"></div>

	<div class="fctabber tabset_cat_props fcparams_tabset" id="tabset_cat_props">

		<div class="tabbertab" id="tabset_cat_props_desc_tab" data-icon-class="icon-file-2" >
			<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_BASIC' ); ?> </h3>

			<div class="container-fluid row" style="padding: 0px !important; margin: 0px !important">

				<!--LEFT COLUMN-->
				<div class="span8 col-8 full_width_980 off-white">

					<div class="fcclear"></div>
					<div class="flexi_params">
						<?php echo $this->form->getInput('description'); ?>
					</div>

				</div>

				<!--RIGHT COLUMN-->
				<div class="span4 col-4 full_width_980 off-white">

					<div class="form-vertical">

						<div class="control-group">
							<div class="control-label">
								<?php echo $this->form->getLabel('parent_id'); ?>
							</div>
							<div class="controls">
								<?php echo $this->lists['parent_id']; ?>
							</div>
						</div>

						<div class="control-group">
							<div class="control-label">
								<?php echo $this->form->getLabel('published'); ?>
							</div>
							<div class="controls">
									<?php echo $this->form->getInput('published'); ?>
							</div>
						</div>

						<div class="control-group">
							<div class="control-label">
								<?php echo $this->form->getLabel('access'); ?>
							</div>
							<div class="controls">
								<?php echo $this->form->getInput('access'); ?>
							</div>
						</div>

						<div class="control-group">
							<div class="control-label">
								<?php echo $this->form->getLabel('language'); ?>
							</div>
							<div class="controls">
								<?php echo $this->form->getInput('language'); ?>
							</div>
						</div>

						<div class="control-group">
							<div class="control-label">
								<?php echo $this->form->getLabel('note'); ?>
							</div>
							<div class="controls">
									<?php echo $this->form->getInput('note'); ?>
							</div>
						</div>

						<?php
						$fieldSet = $this->form->getFieldset('basic');

						if (isset($fieldSet->description) && trim($fieldSet->description)) :
							echo '<div class="fc-mssg fc-info">' . JText::_($fieldSet->description) . '</div>';
						endif;
						?>

						<?php foreach ($fieldSet as $field) :
							echo ($field->getAttribute('type') == 'separator' || $field->hidden) ? $field->input : '
							<div class="control-group">
								<div class="control-label">' . $field->label . '</div>
								<div class="controls">
									' . $this->getFieldInheritedDisplay($field, $this->iparams) . '
								</div>
							</div>
							';
						endforeach; ?>

					</div>

				</div>

			</div><!--.container-fluid row-->

		</div><!-- tabbertab FLEXI_BASIC -->


		<div class="tabbertab" id="tabset_cat_props_metaseo_tab" data-icon-class="icon-bookmark" >
			<h3 class="tabberheading"> <?php echo JText::_('FLEXI_PUBLISHING') . ', ' . JText::_('FLEXI_META'); ?> </h3>

			<div class="container-fluid row" style="padding: 0px !important; margin: 0px !important">

				<div class="span6 col-6 full_width_980" style="max-width: 980px;">
					<div class="fcsep_level1"><?php echo JText::_('FLEXI_PUBLISHING'); ?></div>
					<div class="fcclear"></div>
					

					<?php /* No inheritage needed for these */ ?>
					<?php echo JLayoutHelper::render('joomla.edit.publishingdata', $this); ?>

				</div>

				<!--RIGHT COLUMN-->
				<div class="span6 col-6 full_width_980" style="max-width: 980px;">
					<div class="fcsep_level1"><?php echo JText::_('FLEXI_META_SEO'); ?></div>
					<div class="fcclear"></div>

					<?php /*echo JLayoutHelper::render('joomla.edit.metadata', $this);*/ ?>
					<?php
					$fieldnames_arr = array(
						'metadesc' => null,
						'metakey' => null
					);
					foreach ($this->form->getGroup('metadata') as $field)
					{
						$fieldnames_arr[ $field->fieldname ] = 'metadata';
					}

					foreach ($fieldnames_arr as $fieldnames => $groupname)
					{
						foreach ((array) $fieldnames as $f)
						{
							$field = $this->form->getField($f, $groupname);
							if (!$field) continue;

							echo ($field->getAttribute('type') == 'separator' || $field->hidden) ? $field->input : '
							<div class="control-group">
								<div class="control-label">' . $field->label . '</div>
								<div class="controls">
									' . $field->input /* non-inherited */ . '
								</div>
							</div>
							';
						}
					}
					?>

				</div>

			</div>

		</div><!-- tabbertab FLEXI_PUBLISHING , FLEXI_META -->


		<?php if ($useAssocs) : ?>

		<div class="tabbertab" id="tabset_cat_props_metaseo_tab" data-icon-class="icon-tree-2" >
			<h3 class="tabberheading"> <?php echo JText::_('FLEXI_ASSOCIATIONS'); ?> </h3>

			<?php echo $this->loadTemplate('associations'); ?>
		</div><!-- tabbertab FLEXI_ASSOCIATIONS -->

		<?php endif; ?>



		<div class="tabbertab" id="tabset_cat_params_display_tab" data-icon-class="icon-screen fc-display-params-icon" >
			<h3 class="tabberheading"> <?php echo JText::_('FLEXI_DISPLAY'); ?> </h3>


			<div class="fctabber tabset_cat_params fcparams_tabset" id="tabset_cat_params">

				<div class="tabbertab" id="tabset_cat_params_display_header_tab" data-icon-class="icon-info-circle fc-display-params-icon" >
					<h3 class="tabberheading"> <?php echo JText::_('FLEXI_CAT_DISPLAY_HEADER'); ?> </h3>

					<?php
					$fieldSet = $this->form->getFieldset('cats_display');

					if (isset($fieldSet->description) && trim($fieldSet->description)) :
						echo '<div class="fc-mssg fc-info">' . JText::_($fieldSet->description) . '</div>';
					endif;
					?>

					<?php foreach ($fieldSet as $field) :
						echo ($field->getAttribute('type') == 'separator' || $field->hidden) ? $field->input : '
						<div class="control-group">
							<div class="control-label">' . $field->label . '</div>
							<div class="controls">
								' . $this->getFieldInheritedDisplay($field, $this->iparams) . '
							</div>
						</div>
						';
					endforeach; ?>

				</div><!-- tabbertab FLEXI_CAT_DISPLAY_HEADER -->


				<div class="tabbertab" id="tabset_cat_params_search_filter_form_tab" data-icon-class="icon-search fc-display-params-icon" >
					<h3 class="tabberheading"> <?php echo JText::_('FLEXI_CAT_DISPLAY_SEARCH_FILTER_FORM'); ?> </h3>

					<?php
					$fieldSet = $this->form->getFieldset('cat_search_filter_form');

					if (isset($fieldSet->description) && trim($fieldSet->description)) :
						echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
					endif;
					?>

					<?php foreach ($fieldSet as $field) :
						echo ($field->getAttribute('type')=='separator' || $field->hidden) ? $field->input : '
						<div class="control-group">
							<div class="control-label">'.$field->label.'</div>
							<div class="controls">
								' . $this->getFieldInheritedDisplay($field, $this->iparams) . '
							</div>
						</div>
						';
					endforeach; ?>

				</div><!-- tabbertab FLEXI_CAT_DISPLAY_SEARCH_FILTER_FORM -->


				<div class="tabbertab" id="tabset_cat_params_layout_tab" data-icon-class="icon-palette fc-display-params-icon" >
					<h3 class="tabberheading"> <?php echo JText::_('FLEXI_LAYOUT'); ?> </h3>

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
					<div class="fcclear"></div><br/>

					<?php
					$_p = & $this->row->params;
					foreach($this->form->getGroup('templates') as $field):
						$_name  = $field->fieldname;
						if ($_name!='clayout' && $_name!='clayout_mobile') continue;

						$_value = isset($_p[$_name])  ?  $_p[$_name]  :  null;

						/**
						 * We need to set value manually here because the values are save in the 'attribs' group, but the parameters are really located in the 'templates' group ...
						 * ...setValue(), is ok if input property, has not been already created, otherwise we need to re-initialize (which clears input)
						 */
						//$field->setup($this->form->getFieldXml($field->name, $field->group), $_value, $field->group);
						$field->setValue($_value);

						echo ($field->getAttribute('type')=='separator' || $field->hidden) ? $field->input : '
						<div class="control-group">
							<div class="control-label">'.$field->label.'</div>
							<div class="controls">
								' . $this->getFieldInheritedDisplay($field, $this->iparams) . '
							</div>
						</div>
						';
					endforeach; ?>

					<div class="fctabber tabset_cat_props" id="tabset_layout">

						<div class="tabbertab" id="tabset_layout_params_tab" data-icon-class="icon-palette" >
							<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_LAYOUT_PARAMETERS' ); ?> </h3>

							<div class="fc-success fc-mssg-inline" style="font-size: 12px; margin: 8px 0 !important;" id="__category_inherited_layout__">
								<?php echo JText::_( 'FLEXI_TMPL_USING_INHERITED_CATEGORY_LAYOUT' ). ': <b>'. $this->iparams->get('clayout') .'</b>'; ?>
							</div>
							<div class="fcclear"></div>

							<?php $cat_layout = $this->row->params->get('clayout'); ?>

							<div class="fc-sliders-plain-outer <?php echo $cat_layout ? 'fc_preloaded' : ''; ?>">
								<?php
								$slider_set_id = 'theme-sliders-' . $this->form->getValue('id');
								//echo JHtml::_('sliders.start', $slider_set_id, array('useCookie'=>1, 'show'=>1));
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

									if (!$cat_layout || $tmpl->name !== $cat_layout)
									{
										echo JHtml::_('bootstrap.endSlide');
										continue;
									}

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
												$_value = $form_layout->getValue($fieldname, $groupname, $this->iparams->get($fieldname));

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
																	$this->getFieldInheritedDisplay($field, $this->iparams)
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

						</div><!-- END tabbertab FLEXI_LAYOUT_PARAMETERS -->


						<div class="tabbertab" id="tabset_layout_switcher_tab" data-icon-class="icon-grid" >
							<h3 class="tabberheading"> <?php echo JText::_('FLEXI_CATEGORY_LAYOUT_SWITCHER'); ?> </h3>

							<?php
							$_p = & $this->row->params;
							foreach($this->form->getGroup('templates') as $field):
								$_name  = $field->fieldname;
								if ($_name=='clayout' || $_name=='clayout_mobile') continue;

								$_value = isset($_p[$_name])  ?  $_p[$_name]  :  null;

								/**
								 * We need to set value manually here because the values are save in the 'attribs' group, but the parameters are really located in the 'templates' group ...
								 * ...setValue(), is ok if input property, has not been already created, otherwise we need to re-initialize (which clears input)
								 */
								//$field->setup($this->form->getFieldXml($field->name, $field->group), $_value, $field->group);
								$field->setValue($_value);

								echo ($field->getAttribute('type')=='separator' || $field->hidden) ? $field->input : '
								<div class="control-group">
									<div class="control-label">'.$field->label.'</div>
									<div class="controls">
										' . $this->getFieldInheritedDisplay($field, $this->iparams) . '
									</div>
								</div>
								';
							endforeach; ?>

						</div><!-- tabbertab FLEXI_CATEGORY_LAYOUT_SWITCHER -->

					</div><!-- fctabber FLEXI_LAYOUT -->

				</div><!-- tabbertab FLEXI_LAYOUT -->


				<div class="tabbertab tabbertabdefault" id="tabset_cat_params_itemslist_tab" data-icon-class="icon-list-2 fc-display-params-icon" >
					<h3 class="tabberheading"> <?php echo JText::_('FLEXI_CAT_DISPLAY_ITEMS_LIST'); ?> </h3>

					<?php
					$fieldSet = $this->form->getFieldset('cat_items_list');

					if (isset($fieldSet->description) && trim($fieldSet->description)) :
						echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
					endif;
					?>

					<?php foreach ($fieldSet as $field) :
						echo ($field->getAttribute('type')=='separator' || $field->hidden) ? $field->input : '
						<div class="control-group">
							<div class="control-label">'.$field->label.'</div>
							<div class="controls">
								' . $this->getFieldInheritedDisplay($field, $this->iparams) . '
							</div>
						</div>
						';
					endforeach; ?>

				</div><!-- tabbertab FLEXI_CAT_DISPLAY_ITEMS_LIST -->


				<div class="tabbertab" id="tabset_cat_params_rss_feeds_tab" data-icon-class="icon-feed fc-rss-params-icon" >
					<h3 class="tabberheading"> <?php echo JText::_('FLEXI_PARAMS_CAT_RSS_FEEDS'); ?> </h3>

					<?php
					$fieldSet = $this->form->getFieldset('cat_rss_feeds');

					if (isset($fieldSet->description) && trim($fieldSet->description)) :
						echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
					endif;
					?>

					<?php foreach ($fieldSet as $field) :
						echo ($field->getAttribute('type')=='separator' || $field->hidden) ? $field->input : '
						<div class="control-group">
							<div class="control-label">'.$field->label.'</div>
							<div class="controls">
								' . $this->getFieldInheritedDisplay($field, $this->iparams) . '
							</div>
						</div>
						';
					endforeach; ?>

				</div><!-- tabbertab FLEXI_PARAMS_CAT_RSS_FEEDS -->


				<div class="tabbertab" id="tabset_cat_props_params_handling_tab" data-icon-class="icon-wrench" >
					<h3 class="tabberheading"> <?php echo (1 ? '&nbsp;' : JText::_('FLEXI_PARAMETERS_HANDLING')); ?> </h3>

					<div class="fcsep_level0 fc-nomargin" style="background-color: #444;">
						<?php echo JText::_('FLEXI_PARAMETERS_HANDLING'); ?>
					</div>

					<span class="btn-group input-append" style="margin: 2px 0px 6px;">
						<span id="fc-heritage-help_btn" class="btn" onclick="fc_toggle_box_via_btn('fc-heritage-help', this, 'btn-primary');" ><span class="icon-help"></span><?php echo JText::_('FLEXI_HERITAGE_OVERRIDE_ORDER'); ?></span>
					</span>
					<div class="fcclear"></div>

					<div class="fc-mssg fc-info fc-nobgimage" id="fc-heritage-help" style="margin: 2px 0px!important; font-size:12px; display: none;">
						<?php echo JText::_('FLEXI_CAT_PARAM_OVERRIDE_ORDER_DETAILS_INHERIT'); ?>
					</div>
					<div class="fcclear"></div>

					<?php foreach($this->form->getGroup('special') as $field): ?>
						<div class="control-group">
							<div class="control-label"><?php echo $field->label; ?></div>
							<div class="controls">
								<?php echo $this->lists[$field->fieldname]; ?>
							</div>
						</div>
					<?php endforeach; ?>


					<div class="control-group">
						<div class="control-label">
							<?php echo $this->form->getLabel('copycid'); ?>
						</div>
						<div class="controls">
							<?php echo $this->lists['copycid']; ?>
						</div>
					</div>

				</div><!-- tabbertab FLEXI_PARAMETERS_HANDLING -->

			</div><!-- fctabber tabset_cat_params EOF -->

		</div><!-- tabbertab FLEXI_DISPLAY -->


		<div class="tabbertab" id="tabset_cat_props_content_notifications_tab" data-icon-class="icon-mail" >
			<h3 class="tabberheading"> <?php echo JText::_('FLEXI_NOTIFICATIONS_CONF'); ?> </h3>

			<?php
			$fieldSet = $this->form->getFieldset('notifications_conf');

			if (isset($fieldSet->description) && trim($fieldSet->description)) :
				echo '<div class="fc-mssg fc-info">' . JText::_($fieldSet->description) . '</div>';
			endif;
			?>

			<?php foreach ($fieldSet as $field) :
				echo ($field->getAttribute('type') == 'separator' || $field->hidden) ? $field->input : '
				<div class="control-group">
					<div class="control-label">' . $field->label . '</div>
					<div class="controls">
						' . $this->getFieldInheritedDisplay($field, $this->iparams) . '
					</div>
				</div>
				';
			endforeach; ?>

		<?php if ($this->cparams->get('nf_allow_cat_specific', 0)) : ?>

			<?php
			$fieldSet = $this->form->getFieldset('cat_notifications_conf');

				if (isset($fieldSet->description) && trim($fieldSet->description)) :
					echo '<div class="fc-mssg fc-info">' . JText::_($fieldSet->description) . '</div>';
				endif;

				foreach ($fieldSet as $field) :
					echo ($field->getAttribute('type') == 'separator' || $field->hidden) ? $field->input : '
					<div class="control-group">
						<div class="control-label">' . $field->label . '</div>
						<div class="controls">
							' . $this->getFieldInheritedDisplay($field, $this->iparams) . '
						</div>
					</div>
					';
				endforeach; ?>

			<?php else : ?>

				<div class="fcsep_level0">
					<?php echo JText::_('FLEXI_NOTIFY_EMAIL_RECEPIENTS'); ?>
				</div>
				<div class="fcclear"></div>

				<div class="alert alert-info">
					<?php echo JText::_('FLEXI_INACTIVE_PER_CONTENT_CAT_NOTIFICATIONS_INFO'); ?>
				</div>

			<?php endif; ?>

		</div><!-- tabbertab FLEXI_NOTIFICATIONS_CONF -->


		<?php if ( $this->perms->CanRights ) : ?>

		<div class="tabbertab fcperms_tab" id="tabset_cat_props_perms_tab" data-icon-class="icon-power-cord">
			<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_PERMISSIONS' ); ?> </h3>

			<div class="fc_tabset_inner">
				<div id="access">
					<?php echo $this->form->getInput('rules'); ?>
				</div>
			</div>

		</div><!-- tabbertab FLEXI_PERMISSIONS -->

		<?php endif; ?>

	</div><!-- fctabber tabset_cat_props -->


	<div class="fcclear"></div>

	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="id" value="<?php echo $this->form->getValue('id'); ?>" />
	<input type="hidden" name="controller" value="<?php echo $this->controller; ?>" />
	<input type="hidden" name="view" value="<?php echo $this->view; ?>" />
	<input type="hidden" name="task" value="" />
	<?php echo $this->form->getInput('extension'); ?>
	<?php echo JHtml::_( 'form.token' ); ?>


</form>
</div><!-- id:flexicontent -->
