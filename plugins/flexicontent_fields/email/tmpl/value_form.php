<?php
JHtml::_('behavior.formvalidator');
// Create field's HTML
$field->{$prop} = array();
$n = 0;

foreach ($values as $value)
{

	//dump($value);
	// Basic sanity check for a valid email address
	$value['addr'] = !empty($value['addr']) && strpos($value['addr'], '@') !== false ? $value['addr'] : '';

	// Skip empty value, adding an empty placeholder if field inside in field group
	if ( empty($value['addr']) )
	{
		if ( $is_ingroup )
		{
			$field->{$prop}[$n++]	= '';
		}
		continue;
	}

	// If not using property or property is empty, then use default property value
	// NOTE: default property values have been cleared, if (propertyname_usage != 2)
	$addr = $value['addr'];
	$text = @$value['text'];
	$text = ($usetitle && strlen($text))  ?  $text  :  $default_title;

	if ( !strlen($text) || !$usetitle )
	{
		$text = JStringPunycode::emailToUTF8($addr);  // email in Punycode to UTF8, for the purpose of displaying it
		$text_is_email = 1;
	}
	else
	{
		$text_is_email = strpos($text,'@') !== false;
	}

	// Create field's display
	$submit_class = $field->parameters->get('submit_class', 'btn');
	$display_placeholder = $field->parameters->get('disp_placeholder', 1);
	// Use paremeters to decide if email should be cloaked and if we need a mailto: link
	//title form display
	$titleform = JText::_($field->parameters->get('title_form', ''));
	$display_titleform = $field->parameters->get('display_title_form', 0);
	if ($display_titleform){
		$titleformD = '<LEGEND>'.$titleform.'</LEGEND>';
	} else {
		$titleformD='';
	}

	//firstname field
	$firstname_value= preg_replace("#[^a-zA-Z-0-9]#", "", JText::_('FLEXI_FIELD_EMAIL_FIRSTNAME_LABEL_VALUE'));
	$firstname_field_display = $field->parameters->get('display_firstname_field', 1);
	$firstname_field_required = $field->parameters->get('firstname_field_required', 1);
	$required = $firstname_field_required ? 'required':'';
	if ($firstname_field_display && !$display_placeholder){
		$firstname_field = '<div class="field">
		<label for="'.$firstname_value.'">'.JText::_('FLEXI_FIELD_EMAIL_FIRSTNAME_LABEL_VALUE').
		'</label> <input type="text" name="first_name" id="'.$firstname_value.'" class="'.$required.'">
		</div>';
	}elseif ($firstname_field_display && $display_placeholder){
        $firstname_field = '<div class="field">
        <input type="text" name="first_name" id="'.$firstname_value.'" class="'.$required.'" placeholder="'.JText::_('FLEXI_FIELD_EMAIL_FIRSTNAME_LABEL_VALUE').'">
		</div>';
	}else {
		$firstname_field='';
	}

	//lastname field
	$lastname_value= preg_replace("#[^a-zA-Z-0-9]#", "", JText::_('FLEXI_FIELD_EMAIL_LASTNAME_LABEL_VALUE'));
	$lastname_field_display = $field->parameters->get('display_lastname_field', 1);
	$lastname_field_required = $field->parameters->get('lastname_field_required', 1);
	$required = $lastname_field_required ? 'required':'';
	if ($lastname_field_display && !$display_placeholder){
		$lastname_field = '<div class="field">
		<label for="'.$lastname_value.'">'.JText::_('FLEXI_FIELD_EMAIL_LASTNAME_LABEL_VALUE').
		'</label> <input type="text" name="last_name" id="'.$lastname_value.'" class="'.$required.'">
		</div>';
	}elseif ($lastname_field_display && $display_placeholder){
		$lastname_field = '<div class="field">
		<input type="text" name="last_name" id="'.$lastname_value.'" class="'.$required.'"
		placeholder="'.JText::_('FLEXI_FIELD_EMAIL_LASTNAME_LABEL_VALUE').'">
		</div>';
	}else {
		$lastname_field='';
	}

	//emailfrom field
	$emailfrom_value= preg_replace("#[^a-zA-Z-0-9]#", "", JText::_('FLEXI_FIELD_EMAIL_EMAILFROM_LABEL_VALUE'));
	$emailfrom_field_display = $field->parameters->get('display_emailfrom_field', 1);
	$emailfrom_field_required = $field->parameters->get('emailfrom_field_required', 1);
	$required = $emailfrom_field_required ? 'required':'';
	if ($emailfrom_field_display && !$display_placeholder){
		$emailfrom_field = '<div class="field">
		<label for="'.$emailfrom_value.'">'.JText::_('FLEXI_FIELD_EMAIL_EMAILFROM_LABEL_VALUE').
		'</label> <input type="text" name="email_from" id="'.$emailfrom_value.'" class="'.$required.'">
		</div>';
	}elseif ($emailfrom_field_display && $display_placeholder){
		$emailfrom_field = '<div class="field">
		<input type="text" name="emailfrom" id="'.$emailfrom_value.'" class="'.$required.'"
		placeholder="'.JText::_('FLEXI_FIELD_EMAIL_EMAILFROM_LABEL_VALUE').'">
		</div>';
	}else {
		$emailfrom_field ='';
	}

	//subject field
	$subject_value= preg_replace("#[^a-zA-Z-0-9]#", "", JText::_('FLEXI_FIELD_EMAIL_SUBJECT_LABEL_VALUE'));
	$subject_field_display = $field->parameters->get('display_subject_field', 1);
	$subject_field_required = $field->parameters->get('subject_field_required', 1);
	$required = $subject_field_required ? 'required':'';
	if ($subject_field_display && !$display_placeholder){
		$subject_field = '<div class="field">
		<label for="'.$subject_value.'">'.JText::_('FLEXI_FIELD_EMAIL_SUBJECT_LABEL_VALUE').
		'</label> <input type="text" name="subject" id="'.$subject_value.'" class="'.$required.'">
		</div>';
	}elseif ($subject_field_display && $display_placeholder){
		$subject_field = '<div class="field">
		<input type="text" name="subject" id="'.$subject_value.'" class="'.$required.'"
		placeholder="'.JText::_('FLEXI_FIELD_EMAIL_SUBJECT_LABEL_VALUE').'">
		</div>';
	}else {
		$subject_field='';
	}

	//message field
	$message_value = preg_replace("#[^a-zA-Z-0-9]#", "", JText::_('FLEXI_FIELD_EMAIL_MESSAGE_LABEL_VALUE'));
	$message_field_display = $field->parameters->get('display_message_field', 1);
	$message_field_required = $field->parameters->get('message_field_required', 1);
	$required = $message_field_required ? 'required':'';
	if ($message_field_display && !$display_placeholder){
		$message_field = '<div class="field">
		<label for="'.$message_value.'">'.JText::_('FLEXI_FIELD_EMAIL_MESSAGE_LABEL_VALUE').
		'</label> <input type="text" name="message" id="'.$message_value.'" class="'.$required.'">
		</div>';
	}elseif ($message_field_display && $display_placeholder){
		$message_field = '<div class="field">
		<input type="text" name="message" id="'.$message_value.'" class="'.$required.'"
		placeholder="'.JText::_('FLEXI_FIELD_EMAIL_MESSAGE_LABEL_VALUE').'">
		</div>';
	}else {
		$message_field='';
	}

	// Consent field
	$consent_field_display = $field->parameters->get('display_consent', 1);
	$consent_field_text = $field->parameters->get('text_consent', 'FLEXI_FIELD_EMAIL_CONSENT_LABEL_VALUE');
	$consent_field_link = $field->parameters->get('link_consent', '');
	if($consent_field_display){
		$consent_field = '<div class="field"><input type="checkbox" id="consent" name="consent" value="consent" class="required">
		<label for="consent">
		<a href="'.$consent_field_link.'" target="_blank">'.Jtext::_($consent_field_text).'</a>
		</label></div>';
	}

	//Captcha
		$captcha_display = $field->parameters->get('display_captcha', 0);
		if($captcha_display){
			JPluginHelper::importPlugin('captcha');
			$dispatcher = JEventDispatcher::getInstance();
			$dispatcher->trigger('onInit','captcha_div');
			$captcha_div = '<div id="captcha_div"></div>';
		}else {
			$captcha_div='';
		}

	//Fake id form, cutt email on @ and set startemail
	$formid =  explode("@", $addr);
	$formid = $formid[0];

	$html = '
		<form id="contact-form-'.$formid.'" method="POST" class="form-validate">
		<fieldset>
			'.$titleformD.'
			'.$firstname_field.'
			'.$lastname_field.'
			'.$emailfrom_field.'
			'.$subject_field.'
			'.$message_field.'
			'.$consent_field.'

		<input type="submit" name="submit" value="'.JText::_('FLEXI_FIELD_EMAIL_SUBMIT_LABEL_VALUE').'" class="'.$submit_class.'">
		<input type="hidden" name="emailtask" value="plg.email.submit" />
		<input type="hidden" name="emailto" value="'.$addr.'" />
		<input type="hidden" name="itemid" value="'.$item->id.'" />
		<input type="hidden" name="itemtitle" value="'.$item->title.'" />
		<input type="hidden" name="itemalias" value="'.$item->alias.'" />
		<input type="hidden" name="catid" value="'.$item->catid.'" />
		<input type="hidden" name="return" value="" />
		'.JHtml::_("form.token").'
		</fieldset>

		</form>';



		//parti manquante

	// Add prefix / suffix
	$field->{$prop}[$n]	= $pretext . $html . $posttext;

	$n++;
	if (!$multiple)
	break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}
