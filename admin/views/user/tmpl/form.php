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
$this->document->addScript(JURI::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js');
$this->document->addStyleSheet(JURI::root(true).'/components/com_flexicontent/assets/css/tabber.css');
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
	
/*
--------------------------------------------------
+ SET TAB MEMORY
==================================================
*/	
jQuery(function($) 
                    { 
                      $('a[data-toggle="tab"]').on('shown', function () {
                        //save the latest tab; use cookies if you like 'em better:
                        localStorage.setItem('lastTab', $(this).attr('href'));
                       });

                      //go to the latest tab, if it exists:
                      var lastTab = localStorage.getItem('lastTab');
                      if (lastTab) {
                         $('a[href=' + lastTab + ']').tab('show');
                      }
                      else
                      {
                        // Set the first tab if cookie do not exist
                        $('a[data-toggle="tab"]:first').tab('show');
                      }
                  });
</script>

<div id="flexicontent">
<form action="index.php?controller=users" method="post" name="adminForm" class="form-horizontal" autocomplete="off">
	
    <div class="block-flat">
	<fieldset id="user-basic_set" class="adminform">
    
    
      <?php foreach($this->form->getFieldset('user_basic') as $field) :?>
      <div class="control-group">
	  <div class="control-label"><?php echo $field->label; ?></div>
        <div class="controls"><?php echo $field->input; ?></div>
      </div>
      <?php endforeach; ?>
      

	</fieldset>
	

<?php /*?>	<?php
		echo JHtml::_('tabs.start','basic-tabs-'.$this->form->getValue("id"), array('useCookie'=>1));
		echo JHtml::_('tabs.panel',JText::_('FLEXI_ACCOUNT_DETAILS'), 'user-details');
	?>
	
	<fieldset id="user-details_set" class="adminform">
		 <?php foreach($this->form->getFieldset('user_details') as $field) :?>
        <div class="control-group"> 
		<div class="control-label"><?php echo $field->label; ?></div>
          <div class="controls"><?php echo $field->input; ?></div>
        </div>
        <?php endforeach; ?>
	</fieldset><?php */?>
	
    </div>
	<h3><?php
		echo JText::_('FLEXI_ACCOUNT_DETAILS');
	?></h3>
	
<!--JoomlaID-->
<?php 
$options = array(
'active'    => 'tab1_id'    // Not in docs, but DOES work
); ?>


<?php echo JHtml::_('bootstrap.startTabSet', 'ID-Tabs-Group', $options, array('useCookie'=>1));?> 

<!--FLEXI_ACCOUNT_DETAILS TAB1 -->
<?php echo JHtml::_('bootstrap.addTab', 'ID-Tabs-Group', 'tab1_id', JText::_('FLEXI_ACCOUNT_DETAILS')); ?> 
<fieldset id="user-details_set" class="adminform">
		 <?php foreach($this->form->getFieldset('user_details') as $field) :?>
        <div class="control-group"> 
		<div class="control-label"><?php echo $field->label; ?></div>
          <div class="controls"><?php echo $field->input; ?></div>
        </div>
        <?php endforeach; ?>
	</fieldset>
<?php echo JHtml::_('bootstrap.endTab');?> 
<!--/FLEXI_ACCOUNT_DETAILS TAB1 -->


<!--FLEXI_ASSIGNED_GROUPS TAB2 -->
<?php echo JHtml::_('bootstrap.addTab', 'ID-Tabs-Group', 'tab2_id', JText::_('FLEXI_ASSIGNED_GROUPS'), 'user-groups'); ?> 

	<fieldset id="user-groups_set" class="adminform">
		<div class="adminformlist">
		<?php JHtml::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_users/helpers/html'); ?>
		<?php echo JHtml::_('access.usergroups', 'jform[groups]', $this->usergroups, true); ?>
        </div>
	</fieldset>
    
<?php echo JHtml::_('bootstrap.endTab');?> 
<!--/ FLEXI_ASSIGNED_GROUPS TAB2 -->

<!--FLEXI_user_details TAB3 -->
<?php 

foreach ($this->form->getFieldsets() as $fieldset) :
		if ($fieldset->name == 'user_basic' || $fieldset->name == 'user_details') :
			continue;
		endif;
echo JHtml::_('bootstrap.addTab', 'ID-Tabs-Group', 'tab3_id',  JText::_($fieldset->label), $fieldset->name); ?> 

<fieldset class="panelform">
	<div class="adminformlist">
	<?php foreach($this->form->getFieldset($fieldset->name) as $field): ?>
		<?php if ($field->hidden): ?>
			<?php echo $field->input; ?>
		<?php else: ?>
      <div class="control-group">
            <div class="control-label">
			<?php echo $field->label; ?></div>
			<div class="controls"><?php echo $field->input; ?></div>
            </div>
		<?php endif; ?>
	<?php endforeach; ?>
	</div>
	</fieldset>
    
<?php echo JHtml::_('bootstrap.endTab');
endforeach; ?>
<!--/FLEXI_user_details TAB3 -->



<!--FLEXI_CONTACT TAB4 -->
<?php echo JHtml::_('bootstrap.addTab', 'ID-Tabs-Group', 'tab4_id', JText::_('FLEXI_CONTACT_INFORMATION'), 'user-contact'); ?> 
<?php if (!$this->contact) :?>
<span class="fc-mssg fc-note">
						<b><?php echo JText::_( 'FLEXI_NO_CONTACT_INFORMATION' ); ?>:</b>
						<br /><br />
						<?php echo JText::_( 'FLEXI_MANAGE_IN_CONTACT_COMPONENT' ); ?>.
					</span>
   	<?php else : ?>
	
			<div class="control-group">
            <div class="control-label">
					<?php echo JText::_( 'Name' ); ?>
				</div><div class="controls">
					<strong>
						<?php echo $this->contact[0]->name;?>
					</strong>
				</div>
                </div>
			<div class="control-group">
            <div class="control-label">
					<?php echo JText::_( 'Position' ); ?>
				</div><div class="controls">
					<strong>
						<?php echo $this->contact[0]->con_position;?>
					</strong>
				</div></div>
                
			<div class="control-group">
            <div class="control-label">
					<?php echo JText::_( 'Telephone' ); ?>
				</div><div class="controls">
					<strong>
						<?php echo $this->contact[0]->telephone;?>
					</strong>
				</div></div>
                
			<div class="control-group">
            <div class="control-label">
					<?php echo JText::_( 'Fax' ); ?>
				</div><div class="controls">
					<strong>
						<?php echo $this->contact[0]->fax;?>
					</strong>
				</div></div>
			<div class="control-group">
            <div class="control-label">
					<?php echo JText::_( 'Misc' ); ?>
				</div><div class="controls">
					<strong>
						<?php echo $this->contact[0]->misc;?>
					</strong>
				</div>
                </div>
			<?php if ($this->contact[0]->image) { ?>
			<div class="control-group">
            <div class="control-label">
					<?php echo JText::_( 'Image' ); ?>
				</div><div class="controls">
					<img src="<?php echo JURI::root() . $cparams->get('image_path') . '/' . $this->contact[0]->image; ?>" align="middle" alt="<?php echo JText::_( 'Contact' ); ?>" />
			</div>
                </div>
			<?php } ?>
			<div class="control-group">
            <div class="control-label">&nbsp;</div><div class="controls">
					<div style='display:none;'>
						<br />
						<input type="button" class="fc_button" value="<?php echo JText::_( 'change Contact Details' ); ?>" onclick="gotocontact( '<?php echo $this->contact[0]->id; ?>' )" />
						<i>
							<br /><br />
							'<?php echo JText::_( 'Components -> Contact -> Manage Contacts' ); ?>'
						</i>
					</div>
					<?php echo "<span class='fc-note fc-mssg'>".JText::_( 'Please note that we recomment using an Flexicontent Item to display Author details' )."</span>"; ?>
				</div>
			</div>
	
	<?php endif; /* this->contact */ ?>
    
<?php echo JHtml::_('bootstrap.endTab');?> 
<!--/FLEXI_CONTACT TAB4 -->

<!-- AUTHORING TAB5-->
<?php 
$fieldSets = $this->jform_authorbasic->getFieldsets('authorbasicparams');
		foreach ($fieldSets as $name => $fieldSet) :
		
			$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_PARAMETERS_'.$name;
echo JHtml::_('bootstrap.addTab', 'ID-Tabs-Group', 'tab5_id', JText::_($label)); ?> 


<?php 
echo strlen(trim(@$fieldSet->description)) ? '<p class="tip">'.$this->escape(JText::_($fieldSet->description)).'</p>' : '';

foreach($this->jform_authorbasic->getFieldset($name) as $field) :?>
        <div class="control-group"> 
		<div class="control-label"><?php echo $field->label; ?></div>
          <div class="controls"><?php echo $field->input; ?></div>
        </div>
        <?php endforeach; ?>
        
<?php echo JHtml::_('bootstrap.endTab');
endforeach;?> 
<!-- / AUTHORING TAB5-->


<?php echo JHtml::_('bootstrap.addTab', 'ID-Tabs-Group', 'tab6_id', JText::_('FLEXI_AUTHOR_ITEMS_LIST'), 'author-items-list'); ?> 

<!--
#####
TAB TAB-->
<?php 
$options2 = array(
'active'    => 'cat_info_options-options'    // Not in docs, but DOES work
); ?>
<?php echo JHtml::_('bootstrap.startTabSet', 'TabTab', $options2, array('useCookie'=>1));?> 


<?php 
$fieldSets = $this->jform_authorcat->getFieldsets('authorcatparams');
		$skip_fieldSets_names = array('settings','author_ext_config', 'cat_basic');
		foreach ($fieldSets as $name => $fieldSet) :
		
			if ( in_array($name, $skip_fieldSets_names) ) continue;
			$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_PARAMS_'.$name;

echo JHtml::_('bootstrap.addTab', 'TabTab', str_replace(':',':<br/>', $name.'-options'), JText::_($label));?>

<?php 
/* inputs */
echo strlen(trim(@$fieldSet->description)) ? '<p class="tip">'.$this->escape(JText::_($fieldSet->description)).'</p>' : '';

foreach ($this->jform_authorcat->getFieldset($name) as $field) :?>
        <div class="control-group"> 
		<div class="control-label"><?php echo $field->label; ?></div>
          <div class="controls"><?php echo $field->input; ?></div>
        </div>
        <?php endforeach; ?>
        
<?php echo JHtml::_('bootstrap.endTab');
endforeach;?>


<?php echo JHtml::_('bootstrap.endTabSet');?>
<!--
/#####
TAB TAB-->
<?php echo JHtml::_('bootstrap.endTab');?> 


<!--TEMPLATE TAB 7 -->
<?php echo JHtml::_('bootstrap.addTab', 'ID-Tabs-Group', 'tab7_id', JText::_('FLEXI_TEMPLATE'), 'author-template-options'); ?> 

<?php echo '<span class="fc-note fc-mssg-inline">' . JText::_( 'FLEXI_PARAMETERS_LAYOUT_EXPLANATION' );
			?>
			
			<ol>
				<li> Select TEMPLATE layout </li>
				<li> Open slider with TEMPLATE (layout) PARAMETERS </li>
			</ol>
			
			<p><b>NOTE:</b> Common method for -displaying- fields is by <b>editing the template layout</b> in template manager and placing the fields into <b>template positions</b></p>
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
				echo '<div class="control-group"><div class="control-label">';
					$fieldname =  ''.$field->fieldname;
					$value = $tmpl->params->getValue($fieldname, $name, $this->params_authorcat->get($field->fieldname));
					echo str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_',
						$tmpl->params->getLabel($fieldname, $name));
					echo '</div><div class="controls">';
					echo
						str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_', 
							str_replace('[attribs]', '[layouts]['.$tmpl->name.']',
								$tmpl->params->getInput($fieldname, $name, $value)
							)
						);
					echo '</div></div>';
				endforeach;
				echo '</fieldset>';
			endforeach;
			
		endforeach;
		
		echo JHtml::_('sliders.end');
		
		echo JHtml::_('tabs.end');
	?>	
 <!-- / CONTENT TAB 7 END-->
<?php echo JHtml::_('bootstrap.endTab');?> 
<!-- / TEMPLATE TAB 7 -->
<?php echo JHtml::_('bootstrap.endTabSet');?>
    

	
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
