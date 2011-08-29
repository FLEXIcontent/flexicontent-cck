<?php defined('_JEXEC') or die('Restricted access'); ?>

<?php JHTML::_('behavior.tooltip'); ?>

<?php
	$cid = JRequest::getVar( 'cid', array(0) );
	$edit		= JRequest::getVar('edit',true);
	$text = intval($edit) ? JText::_( 'Edit' ) : JText::_( 'New' );
	$cparams = JComponentHelper::getParams ('com_media');
?>

<?php
	// clean item data
	JFilterOutput::objectHTMLSafe( $this->user, ENT_QUOTES, '' );

	if ($this->user->get('lastvisitDate') == "0000-00-00 00:00:00") {
		$lvisit = JText::_( 'Never' );
	} else {
		$lvisit	= JHTML::_('date', $this->user->get('lastvisitDate'), '%Y-%m-%d %H:%M:%S');
	}
?>
<script language="javascript" type="text/javascript">
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
<form action="index.php?controller=users" method="post" name="adminForm" autocomplete="off">
	<div class="col width-45">
		
	 <fieldset class="adminform">			
	 <legend style='color:darkred;'><?php echo JText::_( 'FLEXI_AUTHOR_JOOMLA_USER_DATA' ); ?></legend>

		<fieldset class="adminform">
		<legend><?php echo JText::_( 'User Details' ); ?></legend>
			<table class="admintable" cellspacing="1">
				<tr>
					<td width="150" class="key">
						<label for="name">
							<?php echo JText::_( 'Name' ); ?>
						</label>
					</td>
					<td>
						<input type="text" name="name" id="name" class="inputbox" size="40" value="<?php echo $this->user->get('name'); ?>" />
					</td>
				</tr>
				<tr>
					<td class="key">
						<label for="username">
							<?php echo JText::_( 'Username' ); ?>
						</label>
					</td>
					<td>
						<input type="text" name="username" id="username" class="inputbox" size="40" value="<?php echo $this->user->get('username'); ?>" autocomplete="off" />
					</td>
				</tr>
				<tr>
					<td class="key">
						<label for="email">
							<?php echo JText::_( 'Email' ); ?>
						</label>
					</td>
					<td>
						<input class="inputbox" type="text" name="email" id="email" size="40" value="<?php echo $this->user->get('email'); ?>" />
					</td>
				</tr>
				<tr>
					<td class="key">
						<label for="password">
							<?php echo JText::_( 'New Password' ); ?>
						</label>
					</td>
					<td>
						<?php if(!$this->user->get('password')) : ?>
							<input class="inputbox disabled" type="password" name="password" id="password" size="40" value="" disabled="disabled" />
						<?php else : ?>
							<input class="inputbox" type="password" name="password" id="password" size="40" value=""/>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td class="key">
						<label for="password2">
							<?php echo JText::_( 'Verify Password' ); ?>
						</label>
					</td>
					<td>
						<?php if(!$this->user->get('password')) : ?>
							<input class="inputbox disabled" type="password" name="password2" id="password2" size="40" value="" disabled="disabled" />
						<?php else : ?>
							<input class="inputbox" type="password" name="password2" id="password2" size="40" value=""/>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td valign="top" class="key">
						<label for="gid">
							<?php echo JText::_( 'Group' ); ?>
						</label>
					</td>
					<td>
						<?php echo $this->lists['gid']; ?>
					</td>
				</tr>
				<?php if ($this->me->authorize( 'com_users', 'block user' )) { ?>
				<tr>
					<td class="key">
						<?php echo JText::_( 'Block User' ); ?>
					</td>
					<td>
						<?php echo $this->lists['block']; ?>
					</td>
				</tr>
				<?php } if ($this->me->authorize( 'com_users', 'email_events' )) { ?>
				<tr>
					<td class="key">
						<?php echo JText::_( 'Receive System Emails' ); ?>
					</td>
					<td>
						<?php echo $this->lists['sendEmail']; ?>
					</td>
				</tr>
				<?php } if( $this->user->get('id') ) { ?>
				<tr>
					<td class="key">
						<?php echo JText::_( 'Register Date' ); ?>
					</td>
					<td>
						<?php echo JHTML::_('date', $this->user->get('registerDate'), '%Y-%m-%d %H:%M:%S');?>
					</td>
				</tr>
				<tr>
					<td class="key">
						<?php echo JText::_( 'Last Visit Date' ); ?>
					</td>
					<td>
						<?php echo $lvisit; ?>
					</td>
				</tr>
				<?php } ?>
			</table>
		</fieldset>

		<fieldset class="adminform">
		<legend><?php echo JText::_( 'Parameters' ); ?></legend>
			<table class="admintable">
				<tr>
					<td>
						<?php
							$params = $this->user->getParameters(true);
							echo $params->render( 'params' );
						?>
					</td>
				</tr>
			</table>
		</fieldset>

		<fieldset class="adminform">			
		<legend><?php echo JText::_( 'Contact Information' ); ?></legend>
		<?php if ( !$this->contact ) { ?>
			<table class="admintable">
				<tr>
					<td>
						<br />
						<span class="note">
							<?php echo JText::_( 'No Contact details linked to this User' ); ?>:
							<br />
							<?php echo JText::_( 'SEECOMPCONTACTFORDETAILS' ); ?>.
						</span>
						<br /><br />
					</td>
				</tr>
			</table>
		<?php } else { ?>
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
					<td valign="top">
						<img src="<?php echo JURI::root() . $cparams->get('image_path') . '/' . $this->contact[0]->image; ?>" align="middle" alt="<?php echo JText::_( 'Contact' ); ?>" />
					</td>
				</tr>
				<?php } ?>
				<tr>
					<td class="key">&nbsp;</td>
					<td>
						<div style='display:none;'>
							<br />
							<input class="button" type="button" value="<?php echo JText::_( 'change Contact Details' ); ?>" onclick="gotocontact( '<?php echo $this->contact[0]->id; ?>' )" />
							<i>
								<br /><br />
								'<?php echo JText::_( 'Components -> Contact -> Manage Contacts' ); ?>'
							</i>
						</div>
						<?php echo "<b>".JText::_( 'Please note that we recomment using an Flexicontent Item to display Author details' )."</b>"; ?>
					</td>
				</tr>
			</table>
			<?php } ?>
		</fieldset>
	
	 </fieldset>
	 
	</div>
	<div class="col width-55">
		
		<fieldset class="adminform">			
		<legend style='color:darkred;'><?php echo JText::_( 'FLEXI_AUTHOR_EXTENDED_DATA' ); ?></legend>
			<table class="admintable">
				<tr>
					<td>
						<?php
						echo $this->pane->startPane( 'author-pane' );
						
						$title = JText::_( 'FLEXI_AUTHOR_BASIC_PARAMS' );
						echo $this->pane->startPanel( $title, 'basic' );
						//$parampath = JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models';
						//$file = $parampath.DS.'author.xml';
						//$author_params = new JParameter("");
						//$author_params->loadSetupFile($file);
						//echo  $author_params->render( 'authorbasicparams', 'author_ext_config' );
						echo $this->form_authorbasic->render('authorbasicparams', 'author_ext_config');
						echo $this->pane->endPanel();
						
						$title = JText::_( 'FLEXI_ACCESS' );
						if (!FLEXI_ACCESS) :
						echo $this->pane->startPanel( $title, 'access' );
						?>
						<table>
							<tr>
								<td>
									<label for="access">
										<?php echo JText::_( 'FLEXI_ACCESS' ).':'; ?>
									</label>
								</td>
								<td>
									<?php echo $this->Lists['access']; ?>
								</td>
							</tr>
						</table>
						<?php
						echo $this->pane->endPanel();
						endif;
						
						/*$title = JText::_( 'FLEXI_IMAGE' );
						echo $this->pane->startPanel( $title, 'image' );
						?>
						<table>
							<tr>
								<td>
									<label for="image">
										<?php echo JText::_( 'FLEXI_CHOOSE_IMAGE' ).':'; ?>
									</label>
								</td>
								<td>
									<?php echo $this->Lists['imagelist']; ?>
								</td>
							</tr>
							<tr>
								<td></td>
								<td>
									<script language="javascript" type="text/javascript">
										if (document.forms[0].image.options.value!=''){
											jsimg='../images/stories/' + getSelectedValue( 'adminForm', 'image' );
										} else {
											jsimg='../images/M_images/blank.png';
										}
										document.write('<img src=' + jsimg + ' name="imagelib" width="80" height="80" border="2" alt="Preview" />');
									</script>
									<br /><br />
								</td>
							</tr>
						</table>
						<?php
						echo $this->pane->endPanel();*/
		
						echo '<h3 class="themes-title">' . JText::_( 'FLEXI_AUTHOR_CATEGORY_PARAMS' ) . '</h3>';
						
						$title = JText::_( 'FLEXI_PARAMETERS_STANDARD' );
						echo $this->pane->startPanel( $title, "params-std" );
						echo $this->form_authorcat->render('authorcatparams');
						echo $this->pane->endPanel();
		
						$title = JText::_( 'FLEXI_PARAMETERS_COMMON' );
						echo $this->pane->startPanel( $title, "params-common" );
						echo $this->form_authorcat->render('authorcatparams', 'common');
						echo $this->pane->endPanel();
						
						echo '<h3 class="themes-title">' . JText::_( 'FLEXI_PARAMETERS_THEMES' ) . '</h3>';
		
						foreach ($this->tmpls as $tmpl) {
							$title = JText::_( 'FLEXI_PARAMETERS_SPECIFIC' ) . ' : ' . $tmpl->name;
							echo $this->pane->startPanel( $title, "params-".$tmpl->name );
							echo $tmpl->params->render('params');
							echo $this->pane->endPanel();
						}
		
						echo $this->pane->endPane();
						?>


					</td>
				</tr>
			</table>
		</fieldset>
		
	</div>
	<div class="clr"></div>

	<input type="hidden" name="id" value="<?php echo $this->user->get('id'); ?>" />
	<input type="hidden" name="cid[]" value="<?php echo $this->user->get('id'); ?>" />
	<input type="hidden" name="controller" value="users" />
	<input type="hidden" name="view" value="user" />
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="contact_id" value="" />
	<?php if (!$this->me->authorize( 'com_users', 'email_events' )) { ?>
	<input type="hidden" name="sendEmail" value="0" />
	<?php } ?>
	<?php echo JHTML::_( 'form.token' ); ?>
</form>
