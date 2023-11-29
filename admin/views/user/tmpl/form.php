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

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

$cid = JFactory::getApplication()->input->get('cid', array(0), 'array');
ArrayHelper::toInteger($cid);

$edit = JFactory::getApplication()->input->get('edit', true);
$text = intval($edit)
	? JText::_('Edit')
	: JText::_('New');

$cparams = JComponentHelper::getParams ('com_media');
$date_format = FLEXI_J16GE ? 'Y-m-d H:i:s' : '%Y-%m-%d %H:%M:%S';


if ($this->row->get('lastvisitDate') == "0000-00-00 00:00:00") {
	$lvisit = JText::_( 'Never' );
} else {
	$lvisit	= JHtml::_('date', $this->row->get('lastvisitDate'), $date_format);
}

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
?>

<script>
	function gotocontact( id ) {
		var form = document.adminForm;
		form.contact_id.value = id;
		Joomla.submitform( 'contact' );
	}
</script>

<div id="flexicontent">
<form action="index.php" method="post" name="adminForm" id="adminForm" class="form-validate form-horizontal" autocomplete="off">

<div class="container-fluid row" style="padding: 0px !important; margin: 0px! important;">

	<div class="span6 col-6 full_width_980" style="margin-bottom: 16px !important;">

		<div class="fcsep_level_h">
			<?php echo JText::_('FLEXI_USER'); ?>
		</div>
	
		<?php foreach($this->form->getFieldset('user_basic') as $field) :?>
			<span class="label-fcouter">
				<?php echo $field->label; ?>
			</span>
			<div class="container_fcfield">
				<?php if ($field->fieldname == 'password') : ?>
					<?php // Disables autocomplete ?> <input type="password" style="display:none">
				<?php endif; ?>
				<?php echo $field->input; ?>
			</div>
			<div class="fcclear"></div>
		<?php endforeach; ?>
		
			<span class="label-fcouter">
				<label class="label"><?php echo 'User groups'; ?></label>
			</span>
			<div class="container_fcfield">
				<?php


				// DB Query to get -mulitple- user group ids for all authors,
				// Get user-To-usergoup mapping for users in current page
				$db = JFactory::getDbo();
				
				$query = 'SELECT group_id FROM #__user_usergroup_map WHERE user_id = '.(int) $this->form->getValue('id');
				$user_grpids = $db->setQuery($query)->loadColumn();
				
				// Get list of Groups for dropdown filter
				$query = 'SELECT *, id AS value, title AS text FROM #__usergroups';
				$usergroups = $db->setQuery($query)->loadObjectList('id');
				
				$row_groupnames = array();

				foreach($user_grpids as $row_ugrp_id)
				{
					$row_groupnames[] = $usergroups[$row_ugrp_id]->title;
				}

				$row_groupnames = implode(', ', $row_groupnames);
				echo '<span class="alert alert-info">'.$row_groupnames.'</span>';
				?>
			</div>

		<div class="fcclear"></div>



		<?php /*
		<div class="fctabber" id="user_props_tabset">

			<div class="tabbertab" id="fcform_tabset_user-details" data-icon-class="icon-home-2" >
				<h3 class="tabberheading hasTooltip"> <?php echo JText::_( 'FLEXI_ACCOUNT_DETAILS' ); ?> </h3>
		
				<table class="fc-form-tbl" id="user-details_set">
					
				<?php foreach($this->form->getFieldset('user_details') as $field) :?>
				
					<tr>
						<td class="key"><?php echo $field->label; ?></td>
						<td><?php echo $field->input; ?></td>
					</tr>
					
				<?php endforeach; ?>
					
				</table>
			
			</div>
			<div class="tabbertab" id="fcform_tabset_user-account" data-icon-class="icon-options" >
				<h3 class="tabberheading hasTooltip"> <?php echo JText::_( 'FLEXI_ACCOUNT_SETTINGS' ); ?> </h3>
			
				<table class="fc-form-tbl">
					
				<?php foreach($this->form->getFieldset('user_settings') as $field): ?>
					
					<?php if ($field->hidden) :
						echo '<tr style="display:none;"><td colspan="2">'.$field->input.'</td></tr>';
						continue;
					endif; ?>
					
					<tr>
						<td class="key"><?php echo $field->label; ?></td>
						<td><?php echo $field->input; ?></td>
					</tr>
					
				<?php endforeach; ?>
				
				</table>
		
			</div>


			<div class="tabbertab" id="fcform_tabset_user-contact" data-icon-class="icon-envelope" >
				<h3 class="tabberheading hasTooltip"> <?php echo JText::_( 'FLEXI_CONTACT_INFORMATION' ); ?> </h3>
			
			<?php if (!$this->contact) :?>
				<table class="admintable" style="width:100%">
					<tr>
						<td>
							<span class="fc-mssg fc-note" style="padding:16px; border:1px solid lightgray;">
								<b><?php echo JText::_( 'FLEXI_NO_CONTACT_INFORMATION' ); ?>:</b>
								<br /><br />
								<?php echo JText::_( 'FLEXI_MANAGE_IN_CONTACT_COMPONENT' ); ?>.
							</span>
						</td>
					</tr>
				</table>
			<?php else : ?>
				<table class="admintable">
					<tr>
						<td class="key">
							<?php echo JText::_( 'Name' ); ?>
						</td>
						<td>
							<strong>
								<?php echo $this->contact[0]->name;?>
							</strong>
						</td>
					</tr>
					<tr>
						<td class="key">
							<?php echo JText::_( 'Position' ); ?>
						</td>
						<td >
							<strong>
								<?php echo $this->contact[0]->con_position;?>
							</strong>
						</td>
					</tr>
					<tr>
						<td class="key">
							<?php echo JText::_( 'Telephone' ); ?>
						</td>
						<td >
							<strong>
								<?php echo $this->contact[0]->telephone;?>
							</strong>
						</td>
					</tr>
					<tr>
						<td class="key">
							<?php echo JText::_( 'Fax' ); ?>
						</td>
						<td >
							<strong>
								<?php echo $this->contact[0]->fax;?>
							</strong>
						</td>
					</tr>
					<tr>
						<td class="key">
							<?php echo JText::_( 'Misc' ); ?>
						</td>
						<td >
							<strong>
								<?php echo $this->contact[0]->misc;?>
							</strong>
						</td>
					</tr>
					<?php if ($this->contact[0]->image) { ?>
					<tr>
						<td class="key">
							<?php echo JText::_( 'Image' ); ?>
						</td>
						<td style="vertical-align:top; text-align:center;">
							<img src="<?php echo JUri::root() . $cparams->get('image_path') . '/' . $this->contact[0]->image; ?>" alt="<?php echo JText::_( 'Contact' ); ?>" />
						</td>
					</tr>
					<?php } ?>
					<tr>
						<td class="key">&nbsp;</td>
						<td>
							<div>
								<br />
								<input type="button" class="fc_button" value="<?php echo JText::_( 'change Contact Details' ); ?>" onclick="gotocontact( '<?php echo $this->contact[0]->id; ?>' )" />
								<i>
									<br /><br />
									'<?php echo JText::_( 'Components -> Contact -> Manage Contacts' ); ?>'
								</i>
							</div>
							
							<?php if (0): ?>
							<div class='fc-note fc-mssg'>
								<?php echo JText::_( 'Please note that we recomment using an Flexicontent Item to display Author details' ); ?>
							</div>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			<?php endif; ?>
			
			</div>
			*/ ?>

			
			<?php /*echo JHtml::_('tabs.panel', JText::_('FLEXI_EMAIL_NOTIFICATIONS_CONF'), 'content-notifications');*/ ?>
			<?php /* // WE HAVE NOT IMPLEMENTED THIS PER AUTHOR YET
			<div class="tabbertab" id="fcform_tabset-author-content-notifications-tab" data-icon-class="icon-mail" >
				<h3 class="tabberheading"> <?php echo JText::_('FLEXI_EMAIL_NOTIFICATIONS_CONF'); ?> </h3>
				
				<?php
				$fieldSet = $this->jform_authorcat->getFieldset('cat_notifications_conf');
				
				if (isset($fieldSet->description) && trim($fieldSet->description)) :
					echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
				endif;
				?>
				
				<fieldset class="panelform">
				<?php
				foreach ($fieldSet as $field) :
					echo $field->label;
					echo $field->input;
				endforeach;
				?>
				</fieldset>
				
			</div>
			
			<div class="tabbertab" id="fcform_tabset_user-groups" data-icon-class="icon-users" >
				<h3 class="tabberheading hasTooltip"> <?php echo JText::_( 'FLEXI_ASSIGNED_GROUPS' ); ?> </h3>
				
				<fieldset id="user-groups_set">
					<?php JHtml::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_users/helpers/html'); ?>
					<?php echo JHtml::_('access.usergroups', 'jform[groups]', $this->usergroups, true); ?>
				</fieldset>
				
			</div>
			
		</div><!-- fctabber -->
		*/ ?>

	</div>

<?php
	$skip_fsets = array('author_override_cat_config'=>1);
	$fieldSets = $this->jform_authorbasic->getFieldsets('authorbasicparams');
	$total_fsets = count($fieldSets) - count($skip_fsets);
	
	foreach ($fieldSets as $name => $fieldSet) :
		if ( isset($skip_fsets[$name]) ) continue;
		$single_fset = reset($fieldSets);
		$single_fset_label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_PARAMETERS_'.$name;
	endforeach;
?>
	
	<div class="span6 col-6 full_width_980" style="margin-bottom: 16px !important;">

		<?php if ($total_fsets==1) : ?>
		<div class="fcsep_level_h">
			<?php echo JText::_($single_fset_label); ?>
		</div>
		<?php endif; ?>

		<div class="fctabber" id="user_authoring_params_tabset">

			<?php
			foreach ($fieldSets as $name => $fieldSet) :

				if ( isset($skip_fsets[$name]) ) continue;
				$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_PARAMETERS_'.$name;
			?>

			<div class="tabbertab" id="fcform_tabset_<?php echo $name.'-options'; ?>" data-icon-class="icon-pencil" >
				<h3 class="tabberheading <?php if ($total_fsets==1) echo 'hidden'; ?>"> <?php echo JText::_( $label ); ?> </h3>

				<?php
				if (isset($fieldSet->description) && trim($fieldSet->description)) :
					echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
				endif;

				foreach ($this->jform_authorbasic->getFieldset($name) as $field) :
					echo ($field->getAttribute('type')=='separator' || $field->hidden) ? $field->input : '
					<div class="control-group">
						<div class="control-label">'.$field->label.'</div>
						<div class="controls">
							' . $field->input /* non-inherited */ . '
						</div>
					</div>
					';
				endforeach; ?>

			</div>
			
			<?php endforeach; ?><?php /*echo JHtml::_('tabs.panel', JText::_('FLEXI_META_SEO'), 'metaseo-options');*/ ?>

		</div><!-- fctabber -->
			
	</div>

</div>


<div class="fcsep_level_h">
	<?php echo JText::_('FLEXI_DISPLAY_PARAMETERS'); ?>
</div>


		<div style="margin-bottom: 32px;">
			<div class="fcclear"></div>
			<?php
				$fieldSet = $this->jform_authorbasic->getFieldset('author_override_cat_config');
			
					if (isset($fieldSet->description) && trim($fieldSet->description)) :
						echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
					endif;
					?>

					<?php foreach ($fieldSet as $field) :
					echo ($field->getAttribute('type')=='separator' || $field->hidden)
						? $field->input
						: '
						<div class="control-group">
							<div class="control-label">'.$field->label.'</div>
							<div class="controls">
								' . $field->input /* non-inherited */ . '
							</div>
						</div>
						';
				endforeach;
			?>
		</div>


		<div class="override_cat_conf" style="margin-bottom: 16px !important;">

			<div class="fctabber tabset_cat_params fcparams_tabset" id="tabset_cat_params">

				<div class="tabbertab" id="tabset_cat_params_display_header_tab" data-icon-class="icon-info-circle fc-display-params-icon" >
					<h3 class="tabberheading"> <?php echo JText::_('FLEXI_CAT_DISPLAY_HEADER'); ?> </h3>

					<?php
					$fieldSet = $this->jform_authorcat->getFieldset('cats_display');

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

				</div><!-- tabbertab FLEXI_CAT_DISPLAY_HEADER -->


				<div class="tabbertab" id="tabset_cat_params_search_filter_form_tab" data-icon-class="icon-search fc-display-params-icon" >
					<h3 class="tabberheading"> <?php echo JText::_('FLEXI_CAT_DISPLAY_SEARCH_FILTER_FORM'); ?> </h3>

					<?php
					$fieldSet = $this->jform_authorcat->getFieldset('cat_search_filter_form');

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
					foreach($this->jform_authorcat->getGroup('templates') as $field):
						$_name  = $field->fieldname;
						if ($_name!='clayout' && $_name!='clayout_mobile') continue;

						$_value = $this->params_authorcat->get($_name);

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
								<?php echo JText::_( 'FLEXI_TMPL_USING_INHERITED_CATEGORY_LAYOUT' ). ': <b>'. $this->params_authorcat->get('clayout') .'</b>'; ?>
							</div>
							<div class="fcclear"></div>

							<div class="fc-sliders-plain-outer">
								<?php
								$slider_set_id = 'theme-sliders-'.$this->form->getValue("id");
								//echo JHtml::_('sliders.start','theme-sliders-'.$this->form->getValue("id"), array('useCookie'=>1,'show'=>1));
								echo JHtml::_('bootstrap.startAccordion', $slider_set_id, array(/*'active' => ''*/));
								$groupname = 'attribs';  // Field Group name this is for name of <fields name="..." >
								$cat_layout = $this->params_authorcat->get('clayout');

								foreach ($this->tmpls as $tmpl) :

									$form_layout = $tmpl->params;
									$label = JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ) . ' : ' . $tmpl->name;
									//echo JHtml::_('sliders.panel', $label, $tmpl->name.'-'.$groupname.'-options');
									echo JHtml::_('bootstrap.addSlide', $slider_set_id, $label, $slider_id = $tmpl->name.'-'.$groupname.'-options');

									if (!$cat_layout || $tmpl->name != $cat_layout)
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
												$_value = $form_layout->getValue($fieldname, $groupname, @ $this->iparams[$fieldname]);

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
															str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_',
																$form_layout->getLabel($fieldname, $groupname)
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

							</div><!-- class="fc-sliders-plain-outer" -->

						</div><!-- tabbertab FLEXI_LAYOUT_PARAMETERS -->


						<div class="tabbertab" id="tabset_layout_switcher_tab" data-icon-class="icon-grid" >
							<h3 class="tabberheading"> <?php echo JText::_('FLEXI_CATEGORY_LAYOUT_SWITCHER'); ?> </h3>

							<?php
							foreach($this->jform_authorcat->getGroup('templates') as $field):
								$_name  = $field->fieldname;
								if ($_name=='clayout' || $_name=='clayout_mobile') continue;

								$_value = $this->params_authorcat->get($_name);

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
					<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_AUTHOR_ITEMS_LIST' ); ?> </h3>

					<?php
					$fieldSet = $this->jform_authorcat->getFieldset('cat_items_list');

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
					$fieldSet = $this->jform_authorcat->getFieldset('cat_rss_feeds');

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

			</div><!-- fctabber tabset_cat_props -->



	<div class="fcclear"></div>

	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="id" value="<?php echo $this->row->get('id'); ?>" />
	<input type="hidden" name="cid[]" value="<?php echo $this->row->get('id'); ?>" />
	<input type="hidden" name="controller" value="users" />
	<input type="hidden" name="view" value="user" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="contact_id" value="" />
	<?php if (!JFactory::getUser()->authorise('com_users', 'email_events')) : ?>
		<input type="hidden" name="sendEmail" value="0" />
	<?php endif; ?>
	<?php echo JHtml::_( 'form.token' ); ?>

</form>
</div><!-- id:flexicontent -->