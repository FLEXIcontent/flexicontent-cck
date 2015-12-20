<?php
defined('_JEXEC') or die('Restricted access');

$cid = JRequest::getVar( 'cid', array(0) );
$edit		= JRequest::getVar('edit',true);
$text = intval($edit) ? JText::_( 'Edit' ) : JText::_( 'New' );
$cparams = JComponentHelper::getParams ('com_media');
$date_format = FLEXI_J16GE ? 'Y-m-d H:i:s' : '%Y-%m-%d %H:%M:%S';

// clean item data
JFilterOutput::objectHTMLSafe( $this->user, ENT_QUOTES, '' );

if ($this->user->get('lastvisitDate') == "0000-00-00 00:00:00") {
	$lvisit = JText::_( 'Never' );
} else {
	$lvisit	= JHTML::_('date', $this->user->get('lastvisitDate'), $date_format);
}

// Load JS tabber lib
$this->document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', FLEXI_VHASH);
$this->document->addStyleSheetVersion(JURI::root(true).'/components/com_flexicontent/assets/css/tabber.css', FLEXI_VHASH);
$this->document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs
?>
<style>
.current:after{
	clear: both;
	content: "";
	display: block;
}
</style>

<script language="javascript" type="text/javascript">
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
	
	<fieldset id="user-basic_set" class="adminform" style="border:0; margin:0;">
		<table class="admintable" cellspacing="1">
			<?php foreach($this->form->getFieldset('user_basic') as $field) :?>
				<tr>
					<td width="150" class="key">
						<?php echo $field->label; ?>
					</td>
					<td>
						<?php if ($field->fieldname == 'password') : ?>
							<?php // Disables autocomplete ?> <input type="password" style="display:none">
						<?php endif; ?>
						<?php echo $field->input; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
	</fieldset>
	
	<?php
		echo JHtml::_('tabs.start','basic-tabs-'.$this->form->getValue("id"), array('useCookie'=>1));
		echo JHtml::_('tabs.panel',JText::_('FLEXI_ACCOUNT_DETAILS'), 'user-details');
	?>
	
	<fieldset id="user-details_set" class="adminform">
		<table class="admintable" cellspacing="1">
			<?php foreach($this->form->getFieldset('user_details') as $field) :?>
				<tr>
					<td width="150" class="key"><?php echo $field->label; ?></td>
					<td><?php echo $field->input; ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	</fieldset>
	
	<?php
		echo JHtml::_('tabs.panel',JText::_('FLEXI_ACCOUNT_SETTINGS'), 'user-account');
	?>
	
	<?php
	echo JHtml::_('sliders.start');
	foreach ($this->form->getFieldsets() as $fieldset) :
		if ($fieldset->name == 'user_basic' || $fieldset->name == 'user_details') :
			continue;
		endif;
		echo JHtml::_('sliders.panel', JText::_($fieldset->label), $fieldset->name);
	?>
	<fieldset class="panelform">
	<ul class="adminformlist">
	<?php foreach($this->form->getFieldset($fieldset->name) as $field): ?>
		<?php if ($field->hidden): ?>
			<?php echo $field->input; ?>
		<?php else: ?>
			<li><?php echo $field->label; ?>
			<?php echo $field->input; ?></li>
		<?php endif; ?>
	<?php endforeach; ?>
	</ul>
	</fieldset>
	<?php endforeach; ?>
	<?php echo JHtml::_('sliders.end'); ?>

	<?php
		echo JHtml::_('tabs.panel',JText::_('FLEXI_ASSIGNED_GROUPS'), 'user-groups');
	?>
	
	<fieldset id="user-groups_set" class="adminform">
		<legend><?php echo JText::_('FLEXI_ASSIGNED_GROUPS'); ?></legend>
		<?php JHtml::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_users/helpers/html'); ?>
		<?php echo JHtml::_('access.usergroups', 'jform[groups]', $this->usergroups, true); ?>
	</fieldset>


	<?php echo JHtml::_('tabs.panel',JText::_('FLEXI_CONTACT_INFORMATION'), 'user-contact'); ?>
	
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
				<td width="120" class="key">
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
					<div style='display:none;'>
						<br />
						<input type="button" class="fc_button" value="<?php echo JText::_( 'change Contact Details' ); ?>" onclick="gotocontact( '<?php echo $this->contact[0]->id; ?>' )" />
						<i>
							<br /><br />
							'<?php echo JText::_( 'Components -> Contact -> Manage Contacts' ); ?>'
						</i>
					</div>
					<?php echo "<span class='fc-note fc-mssg'>".JText::_( 'Please note that we recomment using an Flexicontent Item to display Author details' )."</span>"; ?>
				</td>
			</tr>
		</table>
	<?php endif; /* this->contact */ ?>
	
	<?php
		$fieldSets = $this->jform_authorbasic->getFieldsets('authorbasicparams');
		foreach ($fieldSets as $name => $fieldSet) :
		
			$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_PARAMETERS_'.$name;
			echo JHtml::_('tabs.panel',JText::_($label), $name.'-options');
			echo strlen(trim(@$fieldSet->description)) ? '<p class="tip">'.$this->escape(JText::_($fieldSet->description)).'</p>' : '';
			
			echo '<fieldset class="panelform">';
			foreach ($this->jform_authorbasic->getFieldset($name) as $field) :
				echo $field->label;
				echo $field->input;
			endforeach;
			echo '</fieldset>';
			
		endforeach;
		
		
		
		echo JHtml::_('tabs.panel',JText::_('FLEXI_AUTHOR_ITEMS_LIST'), 'author-items-list');
		echo JHtml::_('tabs.start','cat-tabs-'.$this->form->getValue("id"), array('useCookie'=>1));
		
		$fieldSets = $this->jform_authorcat->getFieldsets('authorcatparams');
		$skip_fieldSets_names = array('settings','author_ext_config', 'cat_basic');
		foreach ($fieldSets as $name => $fieldSet) :
		
			if ( in_array($name, $skip_fieldSets_names) ) continue;
			$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_PARAMS_'.$name;
			echo JHtml::_('tabs.panel', str_replace(':',':<br/>', JText::_($label)), $name.'-options');
			echo strlen(trim(@$fieldSet->description)) ? '<p class="tip">'.$this->escape(JText::_($fieldSet->description)).'</p>' : '';
			
			echo '<fieldset class="panelform">';
			foreach ($this->jform_authorcat->getFieldset($name) as $field) :
				echo $field->label;
				echo $field->input;
			endforeach;
			echo '</fieldset>';
			
		endforeach;
		
		echo JHtml::_('tabs.end');
		echo JHtml::_('tabs.panel',JText::_('FLEXI_TEMPLATE'), 'author-template-options');
		?>
		
		<fieldset class="panelform">
			<?php
			echo '<span class="fc-note fc-mssg-inline" style="margin: 8px 0px!important;">' . JText::_( 'FLEXI_PARAMETERS_LAYOUT_EXPLANATION' );
			?>
			<br/><br/>
			<ol style="margin:0 0 0 16px; padding:0;">
				<li style="margin:0; padding:0;"> Select TEMPLATE layout </li>
				<li style="margin:0; padding:0;"> Open slider with TEMPLATE (layout) PARAMETERS </li>
			</ol>
			<br/>
			<b>NOTE:</b> Common method for -displaying- fields is by <b>editing the template layout</b> in template manager and placing the fields into <b>template positions</b>
			</span>
			<div class="clear"></div>
			
			<?php
			foreach($this->form->getGroup('templates') as $field):
				$_value = $this->params_author->get($field->fieldname);
				
				if ($field->hidden):
					echo $field->input;
				else:
					// setValue(), is ok if input property, has not been already created
					// otherwise we need to re-initialize (which clears input)
					//$field->setup($field->element, $_value, $field->group);
					
					$field->setValue($_value);
					echo $field->label;
					echo $field->input;
					echo '<div class="clear"></div>';
				endif;
			endforeach;
			?>
		</fieldset>
		
		<?php
		echo JHtml::_('sliders.start','theme-sliders-'.$this->form->getValue("id"), array('useCookie'=>1));
		
		foreach ($this->tmpls as $tmpl) :
		
			$fieldSets = $tmpl->params->getFieldsets('attribs');
			foreach ($fieldSets as $name => $fieldSet) :
			
				$label = !empty($fieldSet->label) ? $fieldSet->label : JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ) . ' : ' . $tmpl->name;
				echo JHtml::_('sliders.panel',JText::_($label), $tmpl->name.'-'.$name.'-options');
				echo strlen(trim(@$fieldSet->description)) ? '<p class="tip">'.$this->escape(JText::_($fieldSet->description)).'</p>' : '';
				
				echo '<fieldset class="panelform">';
				foreach ($tmpl->params->getFieldset($name) as $field) :
					if ($field->getAttribute('not_inherited')) continue;
					$fieldname =  $field->fieldname;
					$value = $tmpl->params->getValue($fieldname, $name, $this->params_authorcat->get($field->fieldname));
					echo str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_',
						$tmpl->params->getLabel($fieldname, $name));
					echo
						str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_', 
							str_replace('[attribs]', '[layouts]['.$tmpl->name.']',
								$tmpl->params->getInput($fieldname, $name, $value)
							)
						);
				endforeach;
				echo '</fieldset>';
			endforeach;
			
		endforeach;
		
		echo JHtml::_('sliders.end');
		
		echo JHtml::_('tabs.end');
	?>	
	
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