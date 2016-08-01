<?php
defined('_JEXEC') or die('Restricted access');

$cid = JRequest::getVar( 'cid', array(0) );
$edit		= JRequest::getVar('edit',true);
$text = intval($edit) ? JText::_( 'Edit' ) : JText::_( 'New' );
$cparams = JComponentHelper::getParams ('com_media');
$date_format = FLEXI_J16GE ? 'Y-m-d H:i:s' : '%Y-%m-%d %H:%M:%S';

// Encode (UTF-8 charset) HTML entities form data so that they can be set as form field values
JFilterOutput::objectHTMLSafe( $this->user, ENT_QUOTES, $exclude_keys = '' );

if ($this->user->get('lastvisitDate') == "0000-00-00 00:00:00") {
	$lvisit = JText::_( 'Never' );
} else {
	$lvisit	= JHTML::_('date', $this->user->get('lastvisitDate'), $date_format);
}

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

<script type="text/javascript">
	/*jQuery(document).ready(function() {
		jQuery('input[type=password]').each(function() {
			jQuery(this).val('');
		});
	});*/
	
	function submitbutton(pressbutton) {
		var form = document.adminForm;
		if (pressbutton == 'cancel') {
			submitform( pressbutton );
			return;
		}
		var r = new RegExp("[\<|\>|\"|\'|\%|\;|\(|\)|\&]", "i");

		// do field validation
		if (trim(form.name.value) == "") {
			alert( "<?php echo JText::_( 'You must provide a name.', true ); ?>" );
		} else if (form.username.value == "") {
			alert( "<?php echo JText::_( 'You must provide a user login name.', true ); ?>" );
		} else if (r.exec(form.username.value) || form.username.value.length < 2) {
			alert( "<?php echo JText::_( 'WARNLOGININVALID', true ); ?>" );
		} else if (trim(form.email.value) == "") {
			alert( "<?php echo JText::_( 'You must provide an email address.', true ); ?>" );
		} else if (form.gid.value == "") {
			alert( "<?php echo JText::_( 'You must assign user to a group.', true ); ?>" );
		} else if (((trim(form.password.value) != "") || (trim(form.password2.value) != "")) && (form.password.value != form.password2.value)){
			alert( "<?php echo JText::_( 'Password do not match.', true ); ?>" );
		} else if (form.gid.value == "29") {
			alert( "<?php echo JText::_( 'WARNSELECTPF', true ); ?>" );
		} else if (form.gid.value == "30") {
			alert( "<?php echo JText::_( 'WARNSELECTPB', true ); ?>" );
		} else {
			submitform( pressbutton );
		}
	}

	function gotocontact( id ) {
		var form = document.adminForm;
		form.contact_id.value = id;
		submitform( 'contact' );
	}
</script>

<div id="flexicontent">
<form action="index.php?controller=users" method="post" name="adminForm" id="adminForm" autocomplete="off">


<div class="container-fluid" style="padding: 0px; margin-bottom: 24px;">


	<div class="span6 full_width_980" style="margin-bottom: 16px !important;">

	
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
				$db = JFactory::getDBO();
				
				$query = 'SELECT group_id FROM #__user_usergroup_map WHERE user_id = '.(int) $this->form->getValue('id');
				$db->setQuery( $query );
				$user_grpids = $db->loadColumn();
				
				// Get list of Groups for dropdown filter
				$query = 'SELECT *, id AS value, title AS text FROM #__usergroups';
				$db->setQuery( $query );
				$usergroups = $db->loadObjectList('id');
				
				$row_groupnames = array();
				foreach($user_grpids as $row_ugrp_id) {
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
							<img src="<?php echo JURI::root() . $cparams->get('image_path') . '/' . $this->contact[0]->image; ?>" alt="<?php echo JText::_( 'Contact' ); ?>" />
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

	
	<div class="span6 full_width_980" style="margin-bottom: 16px !important;">

		<div class="fctabber" id="user_authoring_params_tabset">

			<?php
			$skip_fsets = array('author_override_cat_config'=>1);
			
			$fieldSets = $this->jform_authorbasic->getFieldsets('authorbasicparams');
			foreach ($fieldSets as $name => $fieldSet) :
			
				if ( isset($skip_fsets[$name]) ) continue;
				$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_PARAMETERS_'.$name;
			?>
			
			<div class="tabbertab" id="fcform_tabset_<?php echo $name.'-options'; ?>" data-icon-class="icon-pencil" >
				<h3 class="tabberheading hasTooltip"> <?php echo JText::_( $label ); ?> </h3>
				
				<?php
				if (isset($fieldSet->description) && trim($fieldSet->description)) :
					echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
				endif;
				
				echo '<fieldset class="panelform">';
				foreach ($this->jform_authorbasic->getFieldset($name) as $field) :
					echo $field->label;
					echo $field->input;
				endforeach;
				echo '</fieldset>';
				?>
				
			</div>
			
			<?php endforeach; ?><?php /*echo JHtml::_('tabs.panel', JText::_('FLEXI_META_SEO'), 'metaseo-options');*/ ?>

		</div><!-- fctabber -->
			
	</div>
</div>

		<div style="margin-bottom: 32px;">
		<?php
			$fieldSet = $this->jform_authorbasic->getFieldset('author_override_cat_config');
			
			if (isset($fieldSet->description) && trim($fieldSet->description)) :
				echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
			endif;
			
			foreach ($fieldSet as $field) :
				//echo '<div class="fc-mssg fc-info" style="margin-bottom: 12px;">'.JText::_($field->description).'</div>';
				//echo '<span class="label label-info" style="padding: 6px;">'. JText::_( $field->getAttribute('label') ).'</span> &nbsp; ';
				echo str_replace('class=', ' style="padding: 6px; margin: 0 12px 0 0;" class=', $field->label);
				echo $field->input;
				echo '<div class="fcclear"></div>';
			endforeach;
		?>
		</div>
		
		<span style="margin:0 0 12px 0; background-color:#777; " class="fcsep_level0">Category Parameters (override)</span>

		
		<div class="fctabber" id="user_cats_params_tabset">

			<div class="tabbertab" id="tabset_cat_props_display_header_tab" data-icon-class="icon-info-circle fc-display-params-icon" >
				<h3 class="tabberheading"> <?php echo JText::_('FLEXI_CAT_DISPLAY_HEADER'); ?> </h3>
				
				<?php /*echo '<h3 class="themes-title">' . JText::_( 'FLEXI_CAT_DISPLAY_HEADER' ) . '</h3>';*/ ?>
				<?php echo JHtml::_('tabs.start','cat-tabs-header-'.$this->form->getValue("id"), array('useCookie'=>1)); ?>
				
					<?php
					$display_fsets = array('cat_info_options', 'subcats_info_options', 'peercats_info_options');
					foreach ($display_fsets as $name) :
					
						$fieldSet = $this->jform_authorcat->getFieldset($name);
						
						$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_PARAMS_'.$name;
						echo JHtml::_('tabs.panel',JText::_($label), $name.'-options');
						
						if (isset($fieldSet->description) && trim($fieldSet->description)) :
							echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
						endif;
						?>
						
						<fieldset class="panelform">
							<?php foreach ($fieldSet as $field) : ?>
								<?php echo $field->label; ?>
								<?php echo $field->input; ?>
							<?php endforeach; ?>
						</fieldset>
					<?php endforeach; ?>
				
				<?php echo JHtml::_('tabs.end'); ?>
				
			</div>
		
		
			<div class="tabbertab" id="tabset_cat_props_search_filter_form_tab" data-icon-class="icon-search fc-display-params-icon" >
				<h3 class="tabberheading"> <?php echo JText::_('FLEXI_CAT_DISPLAY_SEARCH_FILTER_FORM'); ?> </h3>
				
				<?php
				$fieldSet = $this->jform_authorcat->getFieldset('cat_items_search_filter_form');
				
				if (isset($fieldSet->description) && trim($fieldSet->description)) :
					echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
				endif;
				?>
				
				<fieldset class="panelform">
					<?php foreach ($fieldSet as $field) : ?>
						<?php echo $field->label; ?>
						<?php echo $field->input; ?>
					<?php endforeach; ?>
				</fieldset>
				
			</div><?php /*echo JHtml::_('tabs.panel', JText::_('FLEXI_LAYOUT'), 'cat-params-layout');*/?>
			
			
			<div class="tabbertab" id="fcform_tabset-author-template-options" data-icon-class="icon-palette fc-display-params-icon" >
				<h3 class="tabberheading hasTooltip"> <?php echo JText::_( 'FLEXI_LAYOUT' ); ?> </h3>
				
				<fieldset class="panelform">
					
					<div class="fc-info fc-nobgimage fc-mssg-inline" style="font-size: 12px; margin: 8px 0 !important; padding: 8px !important">
						<h3 class="themes-title">
							<?php echo JText::_( 'FLEXI_PARAMETERS_LAYOUT_EXPLANATION' ); ?>
						</h3>
						<b>NOTE:</b> Common method for -displaying- fields is by <b>editing the template layout</b> in template manager and placing the fields into <b>template positions</b>
					</div>
					<div class="fcclear"></div>
					
					<?php
					foreach($this->jform_authorcat->getGroup('templates') as $field):
						$_name  = $field->fieldname;
						if ($_name!='clayout' && $_name!='clayout_mobile') continue;
						
						$_value = $this->params_authorcat->get($_name);
						
						// setValue(), is ok if input property, has not been already created, otherwise we need to re-initialize (which clears input)
						//$field->setup($field->element, $_value, $field->group);
						
						$field->setValue($_value);
						
						if ($field->hidden): echo $field->input;
						else:
							echo $field->label;
							echo $field->input;
							echo '<div class="fcclear"></div>';
						endif;
					endforeach;
					?>
				</fieldset>


				<div class="fctabber tabset_cat_props" id="tabset_layout">
	
					<div class="tabbertab" id="tabset_layout_params_tab" data-icon-class="icon-palette" >
						<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_LAYOUT_PARAMETERS' ); ?> </h3>


						<div class="fc-sliders-plain-outer">
							<?php
							echo JHtml::_('sliders.start','theme-sliders-'.$this->form->getValue("id"), array('useCookie'=>1,'show'=>1));
							$groupname = 'attribs';  // Field Group name this is for name of <fields name="..." >
							$cat_layout = $this->params_authorcat->get('clayout');
							
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
										//$value = $form_layout->getValue($fieldname, $groupname);
										
										$input_only = !$field->label || $field->hidden;
										echo
											($input_only ? '' :
											str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_',
												$form_layout->getLabel($fieldname, $groupname)).'
											<div class="container_fcfield">
											').
											
											str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_', 
												str_replace('[attribs]', '[layouts]['.$tmpl->name.']',
													//$this->getInheritedFieldDisplay($field, $this->iparams)
													$form_layout->getInput($fieldname, $groupname/*, $value*/)   // Value already set, no need to pass it
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

					</div><?php /*echo JHtml::_('tabs.panel', JText::_('FLEXI_CATEGORY_LAYOUT_SWITCHER'), 'cat-params-layout-swithcer');*/ ?>


					<div class="tabbertab" id="tabset_cat_params_layout_switcher_tab" data-icon-class="icon-grid fc-display-params-icon" >
						<h3 class="tabberheading"> <?php echo JText::_('FLEXI_CATEGORY_LAYOUT_SWITCHER'); ?> </h3>
						
						<fieldset class="panelform">
							<?php
							foreach($this->jform_authorcat->getGroup('templates') as $field):
								$_name  = $field->fieldname;
								if ($_name=='clayout' || $_name=='clayout_mobile') continue;
								
								$_value = $this->params_authorcat->get($_name);
								
								// setValue(), is ok if input property, has not been already created, otherwise we need to re-initialize (which clears input)
								//$field->setup($field->element, $_value, $field->group);
								
								$field->setValue($_value);
								
								if ($field->hidden): echo $field->input;
								else:
									echo $field->label;
									echo $field->input;
									echo '<div class="fcclear"></div>';
								endif;
							endforeach;
							?>
						</fieldset>
						
					</div>

				</div>

			</div><?php /*echo JHtml::_('tabs.panel', JText::_('FLEXI_AUTHOR_ITEMS_LIST'), 'cat-params-items-list');*/?>
		
		
			<div class="tabbertab" id="fcform_tabset-author-items-list" data-icon-class="icon-list-2 fc-display-params-icon" >
				<h3 class="tabberheading hasTooltip"> <?php echo JText::_( 'FLEXI_AUTHOR_ITEMS_LIST' ); ?> </h3>
				
				<?php
				echo JHtml::_('tabs.start','cat-tabs-items-list-'.$this->form->getValue("id"), array('useCookie'=>1));
				
				$fieldSets = $this->jform_authorcat->getFieldsets('authorcatparams');
				$skip_fsets = array('cat_basic'=>1, 'cat_info_options'=>1, 'subcats_info_options'=>1, 'peercats_info_options'=>1, 'cat_rss_feeds'=>1, 'cat_items_search_filter_form'=>1, 'cat_notifications_conf'=>1);
				
				foreach ($fieldSets as $name => $fieldSet) :
					
					if ( isset($skip_fsets[$name]) ) continue;
					$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_PARAMS_'.$name;
					echo JHtml::_('tabs.panel', str_replace(':',':<br/>', JText::_($label)), $name.'-options');
					
					if (isset($fieldSet->description) && trim($fieldSet->description)) :
						echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
					endif;
					?>
					
					<fieldset class="panelform">
					<?php
					foreach ($this->jform_authorcat->getFieldset($name) as $field) :
						echo $field->label;
						echo $field->input;
					endforeach;
					?>
					</fieldset>
					
				<?php endforeach; ?>
				
				<?php echo JHtml::_('tabs.end'); ?>
				
			</div><?php /*echo JHtml::_('tabs.panel', JText::_('FLEXI_PARAMS_CAT_RSS_FEEDS'), 'cat-params-rss');*/?>


			<div class="tabbertab" id="tabset_cat_props_rss_feeds_tab" data-icon-class="icon-feed fc-rss-params-icon" >
				<h3 class="tabberheading"> <?php echo JText::_('FLEXI_PARAMS_CAT_RSS_FEEDS'); ?> </h3>
				
				<?php
				$fieldSet = $this->jform_authorcat->getFieldset('cat_rss_feeds');
				
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

		</div><!-- fctabber -->


	
	<div class="clr"></div>

	<input type="hidden" name="id" value="<?php echo $this->user->get('id'); ?>" />
	<input type="hidden" name="cid[]" value="<?php echo $this->user->get('id'); ?>" />
	<input type="hidden" name="controller" value="users" />
	<input type="hidden" name="view" value="user" />
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="contact_id" value="" />
	<?php if (FLEXI_J16GE ? !$this->me->authorise( 'com_users', 'email_events' ) : !$this->me->authorize( 'com_users', 'email_events' )) { ?>
	<input type="hidden" name="sendEmail" value="0" />
	<?php } ?>
	<?php echo JHTML::_( 'form.token' ); ?>
</form>
</div>