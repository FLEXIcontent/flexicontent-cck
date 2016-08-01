<?php
/**
 * @version 1.5 stable $Id: default.php 1245 2012-04-12 05:16:57Z ggppdk $
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

$useAssocs = flexicontent_db::useAssociations();

//keep session alive while editing
JHTML::_('behavior.keepalive');

// Load JS tabber lib
$this->document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', FLEXI_VHASH);
$this->document->addStyleSheetVersion(JURI::root(true).'/components/com_flexicontent/assets/css/tabber.css', FLEXI_VHASH);
$this->document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs
$js = "
	jQuery(document).ready(function(){
		fc_bindFormDependencies('#flexicontent', 0, '');
	});
";
$this->document->addScriptDeclaration($js);
?>

<div id="flexicontent" class="flexicontent">
<form action="index.php" method="post" name="adminForm" id="adminForm" class="form-horizontal">



<div class="container-fluid" style="padding: 0px !important; margin-bottom: 12px !important;">

	<div class="span6 full_width_980">

		<span class="label-fcouter">
			<?php echo str_replace('" class="', '" class="label label-fcinner ', $this->form->getLabel('title')); ?>
		</span>
		<div class="container_fcfield">
			<?php echo $this->form->getInput('title'); ?>
		</div>

		<div class="fcclear"></div>
		<span class="label-fcouter">
			<?php echo str_replace('class="', 'class="label label-fcinner ', $this->form->getLabel('alias')); ?>
		</span>
		<div class="container_fcfield">
			<?php echo $this->form->getInput('alias'); ?>
		</div>

		<div class="fcclear"></div>
		<span class="label-fcouter">
			<?php echo str_replace('class="', 'class="label label-fcinner ', $this->form->getLabel('language')); ?>
		</span>
		<div class="container_fcfield">
			<?php echo $this->form->getInput('language'); ?>
		</div>

	</div><!-- span6 EOF -->

	<div class="span6 full_width_980">

		<span class="label-fcouter">
			<?php echo str_replace('class="', 'class="label label-fcinner ', $this->form->getLabel('published')); ?>
		</span>
		<div class="container_fcfield">
				<?php echo $this->form->getInput('published'); ?>
		</div>

		<div class="fcclear"></div>
		<span class="label-fcouter">
			<?php echo str_replace('class="', 'class="label label-fcinner ', $this->form->getLabel('parent_id')); ?>
		</span>
		<div class="container_fcfield">
			<?php echo $this->Lists['parent_id']; ?>
		</div>

	</div><!-- span6 EOF -->

</div><!-- container-fluid -->



<div class="container-fluid" style="padding: 0px !important;">
	<div class="span6 full_width_1340" style="margin-bottom: 16px !important;">

		<?php /*echo JHtml::_('tabs.start','core-tabs-cat-props-'.$this->form->getValue("id"), array('useCookie'=>1));*/ ?>
			<?php /*echo JHtml::_('tabs.panel', JText::_( 'FLEXI_DESCRIPTION' ), 'cat-description');*/ ?>

		<div class="fctabber tabset_cat_props fcparams_tabset" id="tabset_cat_props">

			<div class="tabbertab" id="tabset_cat_props_desc_tab" data-icon-class="icon-file-2" >
				<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?> </h3>

				<div class="flexi_params">
					<?php echo $this->form->getInput('description'); ?>
				</div>

			</div><?php /*echo JHtml::_('tabs.panel', JText::_('FLEXI_IMAGE'), 'cat-image');*/ ?>


			<div class="tabbertab" id="tabset_cat_props_image_tab" data-icon-class="icon-image" >
				<h3 class="tabberheading"> <?php echo JText::_('FLEXI_IMAGE'); ?> </h3>

				<?php
				$fieldSet = $this->form->getFieldset('cat_basic');

				if (isset($fieldSet->description) && trim($fieldSet->description)) :
					echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
				endif;
				?>

				<?php foreach ($fieldSet as $field) :
					echo ($field->getAttribute('type')=='separator' || $field->hidden) ? $field->input : '
					<div class="control-group">
						<div class="control-label">'.$field->label.'</div>
						<div class="controls">
							'.$field->input /* non-inherited */ .'
						</div>
					</div>
					';
				endforeach; ?>

			</div><?php /*echo JHtml::_('tabs.panel', JText::_('FLEXI_PUBLISHING'), 'publishing-details');*/ ?>


			<div class="tabbertab" id="tabset_cat_props_publishing_tab" data-icon-class="icon-calendar" >
				<h3 class="tabberheading"> <?php echo JText::_('FLEXI_PUBLISHING'); ?> </h3>

				<fieldset class="panelform">
					<?php echo $this->form->getLabel('created_user_id'); ?>
					<?php echo $this->form->getInput('created_user_id'); ?>

					<?php if (intval($this->form->getValue('created_time'))) : ?>
						<?php echo $this->form->getLabel('created_time'); ?>
						<?php echo $this->form->getInput('created_time'); ?>
					<?php endif; ?>

					<?php if ($this->form->getValue('modified_user_id')) : ?>
						<?php echo $this->form->getLabel('modified_user_id'); ?>
						<?php echo $this->form->getInput('modified_user_id'); ?>

						<?php echo $this->form->getLabel('modified_time'); ?>
						<?php echo $this->form->getInput('modified_time'); ?>
					<?php endif; ?>

					<?php echo $this->form->getLabel('hits'); ?>
					<?php echo $this->form->getInput('hits'); ?>

					<?php echo $this->form->getLabel('access'); ?>
					<?php echo $this->form->getInput('access'); ?>
				</fieldset>

			</div><?php /*echo JHtml::_('tabs.panel', JText::_('FLEXI_META_SEO'), 'metaseo-options');*/ ?>


			<div class="tabbertab" id="tabset_cat_props_metaseo_tab" data-icon-class="icon-bookmark" >
				<h3 class="tabberheading"> <?php echo JText::_('FLEXI_META_SEO'); ?> </h3>

				<fieldset class="panelform">
					<?php echo $this->form->getLabel('metadesc'); ?>
					<?php echo $this->form->getInput('metadesc'); ?>

					<?php echo $this->form->getLabel('metakey'); ?>
					<?php echo $this->form->getInput('metakey'); ?>

					<?php foreach($this->form->getGroup('metadata') as $field): ?>
						<?php if ($field->hidden): echo $field->input; ?>
						<?php else: ?>
							<?php echo $field->label; ?>
							<?php echo $field->input; /* non-inherited */?>
						<?php endif; ?>
					<?php endforeach; ?>
				</fieldset>

			</div><?php /*echo JHtml::_('tabs.panel', JText::_('FLEXI_ASSOCIATIONS'), 'content-notifications');*/ ?>


			<?php if ($useAssocs) : ?>
			<!-- Associations tab -->
			<div class="tabbertab" id="tabset_cat_props_assocs_tab" data-icon-class="icon-flag">
				<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_ASSOCIATIONS' ); ?> </h3>
				<?php echo $this->loadTemplate('associations'); ?>
			</div> <!-- end tab -->
			<?php endif; ?><?php /*echo JHtml::_('tabs.panel', JText::_('FLEXI_NOTIFICATIONS_CONF'), 'cat-perms');*/ ?>


			<div class="tabbertab" id="tabset_cat_props_content_notifications_tab" data-icon-class="icon-mail" >
				<h3 class="tabberheading"> <?php echo JText::_('FLEXI_NOTIFICATIONS_CONF'); ?> </h3>

			<?php if ( $this->cparams->get('enable_notifications', 0) && $this->cparams->get('nf_allow_cat_specific', 0) ) : ?>

				<?php
				$fieldSet = $this->form->getFieldset('cat_notifications_conf');

				if (isset($fieldSet->description) && trim($fieldSet->description)) :
					echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
				endif;
				?>

				<?php foreach ($fieldSet as $field) :
					echo ($field->getAttribute('type')=='separator' || $field->hidden) ? $field->input : '
					<div class="control-group">
						<div class="control-label">'.$field->label.'</div>
						<div class="controls">
							'.$this->getInheritedFieldDisplay($field, $this->iparams).'
						</div>
					</div>
					';
				endforeach; ?>

			<?php else :

				$_comp_state = JText::_( $this->cparams->get('enable_notifications', 0) ? 'FLEXI_ENABLED' : 'FLEXI_DISABLED' );
			 echo '<div class="alert alert-info">'.JText::sprintf('FLEXI_ABOUT_INACTIVE_CONTENT_NOTIFICATIONS', '<b>'.JText::_('FLEXI_DISABLED').'</b>', '<b>'.$_comp_state.'</b>').'</div>';

			endif; ?><?php /*echo JHtml::_('tabs.panel', JText::_('FLEXI_PERMISSIONS'), 'cat-assocs');*/ ?>

			</div>


			<?php if ( $this->perms->CanRights ) : ?>
			<!-- Permissions tab -->
			<div class="tabbertab fcperms_tab" id="tabset_cat_props_perms_tab" data-icon-class="icon-power-cord">
				<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_PERMISSIONS' ); ?> </h3>

				<div class="fc_tabset_inner">
					<div id="access"><?php echo $this->form->getInput('rules'); ?></div>
				</div>

			</div> <!-- end tab -->
			<?php endif; ?>


		</div><!-- fctabber tabset_cat_props EOF --><?php /*echo JHtml::_('tabs.end');*/ ?>

	</div><!-- span6 EOF -->


	<div class="span6 full_width_1340" style="margin-bottom: 12px !important;">

		<?php /*echo JHtml::_('tabs.start','core-tabs-cat-params-'.$this->form->getValue("id"), array('useCookie'=>1));*/ ?>
			<?php /*echo JHtml::_('tabs.panel', JText::_( 'FLEXI_CAT_DISPLAY_HEADER' ), 'cat-params-header');*/ ?>

			<div class="fctabber tabset_cat_params fcparams_tabset" id="tabset_cat_params">

				<div class="tabbertab" id="tabset_cat_params_display_header_tab" data-icon-class="icon-info-circle fc-display-params-icon" >
					<h3 class="tabberheading"> <?php echo JText::_('FLEXI_CAT_DISPLAY_HEADER'); ?> </h3>

					<?php /*echo JHtml::_('tabs.start','cat-tabs-header-'.$this->form->getValue("id"), array('useCookie'=>1));*/ ?>

					<?php
					//$display_fsets = array('cat_info_options', 'subcats_info_options', 'peercats_info_options');
					//foreach ($display_fsets as $name) :

					//	$fieldSet = $this->form->getFieldset($name);
					$fieldSet = $this->form->getFieldset('cats_display');

					//$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_PARAMS_'.$name;
					//echo JHtml::_('tabs.panel', JText::_($label), $name.'-options');

					if (isset($fieldSet->description) && trim($fieldSet->description)) :
						echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
					endif;
					?>

					<?php foreach ($fieldSet as $field) :
						echo ($field->getAttribute('type')=='separator' || $field->hidden) ? $field->input : '
						<div class="control-group">
							<div class="control-label">'.$field->label.'</div>
							<div class="controls">
								'.$this->getInheritedFieldDisplay($field, $this->iparams).'
							</div>
						</div>
						';
					endforeach; ?>

					<?php /*endforeach;*/ ?>
					<?php /*echo JHtml::_('tabs.end');*/ ?>

				</div><?php /*echo JHtml::_('tabs.panel', JText::_('FLEXI_CAT_DISPLAY_SEARCH_FILTER_FORM'), 'cat-params-search-filter-form');*/ ?>


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
								'.$this->getInheritedFieldDisplay($field, $this->iparams).'
							</div>
						</div>
						';
					endforeach; ?>

				</div><?php /*echo JHtml::_('tabs.panel', JText::_('FLEXI_LAYOUT'), 'cat-params-layout');*/?>


				<div class="tabbertab" id="tabset_cat_params_layout_tab" data-icon-class="icon-palette fc-display-params-icon" >
					<h3 class="tabberheading"> <?php echo JText::_('FLEXI_LAYOUT'); ?> </h3>

					<div class="fc-info fc-nobgimage fc-mssg-inline" style="font-size: 12px; margin: 8px 0 !important; padding: 8px !important">
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

						// setValue(), is ok if input property, has not been already created, otherwise we need to re-initialize (which clears input)
						//$field->setup($field->element, $_value, $field->group);

						$field->setValue($_value);

						echo ($field->getAttribute('type')=='separator' || $field->hidden) ? $field->input : '
						<div class="control-group">
							<div class="control-label">'.$field->label.'</div>
							<div class="controls">
								'.$this->getInheritedFieldDisplay($field, $this->iparams).'
							</div>
						</div>
						';
					endforeach; ?>

					<div class="fctabber tabset_cat_props" id="tabset_layout">

						<div class="tabbertab" id="tabset_layout_params_tab" data-icon-class="icon-palette" >
							<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_LAYOUT_PARAMETERS' ); ?> </h3>

							<div class="fc-sliders-plain-outer">
								<?php
								echo JHtml::_('sliders.start','theme-sliders-'.$this->form->getValue("id"), array('useCookie'=>1,'show'=>1));
								$groupname = 'attribs';  // Field Group name this is for name of <fields name="..." >
								$cat_layout = @ $this->row->params['clayout'];

								foreach ($this->tmpls as $tmpl) :

									$form_layout = $tmpl->params;
									$label = JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ) . ' : ' . $tmpl->name;
									echo JHtml::_('sliders.panel', $label, $tmpl->name.'-'.$groupname.'-options');

									if ($tmpl->name != $cat_layout) continue;

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
											//$value = $form_layout->getValue($fieldname, $groupname, @ $this->row->params[$fieldname]);

											$input_only = !$field->label || $field->hidden;
											echo
												($input_only ? '' :
												str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_',
													$form_layout->getLabel($fieldname, $groupname)).'
												<div class="container_fcfield">
												').

												str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_', 
													str_replace('[attribs]', '[layouts]['.$tmpl->name.']',
														$this->getInheritedFieldDisplay($field, $this->iparams)
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


						<div class="tabbertab" id="tabset_layout_switcher_tab" data-icon-class="icon-grid" >
							<h3 class="tabberheading"> <?php echo JText::_('FLEXI_CATEGORY_LAYOUT_SWITCHER'); ?> </h3>

							<?php
							$_p = & $this->row->params;
							foreach($this->form->getGroup('templates') as $field):
								$_name  = $field->fieldname;
								if ($_name=='clayout' || $_name=='clayout_mobile') continue;

								$_value = isset($_p[$_name])  ?  $_p[$_name]  :  null;

								// setValue(), is ok if input property, has not been already created, otherwise we need to re-initialize (which clears input)
								//$field->setup($field->element, $_value, $field->group);

								$field->setValue($_value);

								echo ($field->getAttribute('type')=='separator' || $field->hidden) ? $field->input : '
								<div class="control-group">
									<div class="control-label">'.$field->label.'</div>
									<div class="controls">
										'.$this->getInheritedFieldDisplay($field, $this->iparams).'
									</div>
								</div>
								';
							endforeach; ?>

						</div>

					</div>

				</div><?php /*echo JHtml::_('tabs.panel', JText::_('FLEXI_CAT_DISPLAY_ITEMS_LIST'), 'cat-params-items-list');*/ ?>


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
								'.$this->getInheritedFieldDisplay($field, $this->iparams).'
							</div>
						</div>
						';
					endforeach; ?>

				</div><?php /*echo JHtml::_('tabs.panel', JText::_('FLEXI_PARAMS_CAT_RSS_FEEDS'), 'cat-params-rss');*/ ?>


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
								'.$this->getInheritedFieldDisplay($field, $this->iparams).'
							</div>
						</div>
						';
					endforeach; ?>

				</div><?php /*echo JHtml::_('tabs.panel', (1 ? '&nbsp;' : JText::_('FLEXI_PARAMETERS_HANDLING')), 'cat-params-handling');*/ ?>


				<div class="tabbertab" id="tabset_cat_props_params_handling_tab" data-icon-class="icon-wrench" >
					<h3 class="tabberheading"> <?php echo (1 ? '&nbsp;' : JText::_('FLEXI_PARAMETERS_HANDLING')); ?> </h3>

					<span class="fcsep_level0 fc-nomargin" style="background-color: #444;">
						<?php echo JText::_('FLEXI_PARAMETERS_HANDLING'); ?>
					</span>

					<span class="btn-group input-append" style="margin: 2px 0px 6px;">
						<span id="fc-heritage-help_btn" class="btn" onclick="fc_toggle_box_via_btn('fc-heritage-help', this, 'btn-primary');" ><span class="icon-help"></span><?php echo JText::_('FLEXI_HERITAGE_OVERRIDE_ORDER'); ?></span>
					</span>
					<div class="fcclear"></div>

					<div class="fc-mssg fc-info fc-nobgimage" id="fc-heritage-help" style="margin: 2px 0px!important; font-size:12px; display: none;">
						<?php echo JText::_('FLEXI_CAT_PARAM_OVERRIDE_ORDER_DETAILS_INHERIT'); ?>
					</div>
					<div class="fcclear"></div>

					<?php foreach($this->form->getGroup('special') as $field): ?>
						<fieldset class="panelform" style="margin-top: 24px !important;">
							<?php echo $field->label; ?>
							<div class="container_fcfield">
								<?php echo $this->Lists[$field->fieldname]; ?>
							</div>
						</fieldset>
					<?php endforeach; ?>


					<fieldset class="panelform" style="margin-top: 24px !important;">
						<?php echo $this->form->getLabel('copycid'); ?>
						<div class="container_fcfield">
							<?php echo $this->Lists['copycid']; ?>
						</div>
					</fieldset>

			</div>

		</div><!-- fctabber tabset_cat_props --><?php /*echo JHtml::_('tabs.end');*/ ?>

	</div><!-- span6 -->

</div><!-- container-fluid -->


	<?php echo JHTML::_( 'form.token' ); ?>
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="id" value="<?php echo $this->form->getValue('id'); ?>" />
	<input type="hidden" name="controller" value="category" />
	<input type="hidden" name="view" value="category" />
	<input type="hidden" name="task" value="" />
	<?php echo $this->form->getInput('extension'); ?>

</form>
</div><!-- id:flexicontent -->
