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
	$label_position = $field->parameters->get('label_position', '');
	//label position
	if ($label_position ==='top'){
		$class="label-top";
	}elseif ($label_position ==='placeholder'){
		$class="placeholder";
	}else{
		$class='';
	}

	// Add styles for label position
	$document = JFactory::getDocument();
	$styleurl = JUri::root(true) . '/plugins/flexicontent_fields/email/css/style.css';
	$document->addStyleSheet($styleurl);

	// Use paremeters to decide if email should be cloaked and if we need a mailto: link
	//title form display
	$titleform = JText::_($field->parameters->get('title_form', ''));
	$display_titleform = $field->parameters->get('display_title_form', 0);
	if ($display_titleform){
		$titleformD = '<LEGEND>'.$titleform.'</LEGEND>';
	} else {
		$titleformD='';
	}

	//modal display
	$use_modal = $field->parameters->get('use_modal', 1);
	$modal_button_text = $field->parameters->get('modal_button_text', 'FLEXI_FIELD_EMAIL_MODAL_BUTTON_CONTENT');
	$modal_button_class = $field->parameters->get('modal_button_class', 'btn btn-info');
	if ($use_modal == 1){
		$modal_header = "<button data-toggle='modal' data-target='#myModal' class='$modal_button_class'>".Jtext::_($modal_button_text)."</button>
		<div id='myModal' class='modal fade' role='dialog'>
	  <div class='modal-dialog'>
		<div class='modal-content'>
		  <div class='modal-header' style='border-bottom: 0px solid #eee;'>
			<button type='button' class='close' data-dismiss='modal'>&times;</button>
		  </div>
		  <div class='modal-body'>";
		  $modal_footer = "</div></div></div>";
	}else{
		$modal_header = "";
		$modal_footer = "";

	}

	// Consent field
	$consent_field_display = $field->parameters->get('display_consent', 1);
	$consent_field_text = $field->parameters->get('text_consent', 'FLEXI_FIELD_EMAIL_CONSENT_LABEL_VALUE');
	$consent_field_link = $field->parameters->get('link_consent', '');
	if($consent_field_display){
		$consent_field = '<div class="field form-group control-group"><input type="checkbox" id="consent" name="consent" value="consent" class="required">
		<label for="consent">
		<a href="'.$consent_field_link.'" target="_blank">'.Jtext::_($consent_field_text).'</a>
		</label></div>';
	}

	//Captcha
	$captcha_display = $field->parameters->get('display_captcha', 0);
    $joomla_captcha = JFactory::getConfig()->get('captcha');
if ( $joomla_captcha != '0' && $captcha_display) {
    JPluginHelper::importPlugin('captcha');
    $dispatcher = JDispatcher::getInstance();
    // This will put the code to load reCAPTCHA's JavaScript file into your <head>
    $dispatcher->trigger('onInit', 'dynamic_recaptcha_1');
    // This will return the array of HTML code.
    $recaptcha = $dispatcher->trigger('onDisplay', array(null, 'dynamic_recaptcha_1', 'class="required"'));
}
  if (isset($recaptcha[0]) && $joomla_captcha != "0" && $captcha_display != "0") {
		$captcha_display= '<div class="captcha form-group control-group">'.$recaptcha[0].'</div>';
}else{
    $captcha_display="";
  }

	//Fake id form, cutt email on @ and set startemail
	$formid =  explode("@", $addr);
	$formid = $formid[0];
	$fields_display='';
	$list_fields = $field->parameters->get('form_fields');
								if ($list_fields){
	              foreach( $list_fields as $list_fields_idx => $list_field ) {
									//print_r ($list_field);
									//check and create required class
									$required ='';
									if ($list_field->field_required){
										$required="required";
									}
									//create JText value
									$field_label = JText::_($list_field->field_label);

									//create field id
									$field_id = preg_replace("#[^a-zA-Z-0-9]#", "", JText::_($list_field->field_name));

								//create field name
									$field_name = $formid.'['.JText::_($list_field->field_name).']';

									//create field value
									$field_value = preg_replace("#[^a-zA-Z-0-9]#", "", JText::_($list_field->field_value));

									//placeholder
									$placeholder = ($label_position === 'placeholder') ? 'placeholder="'.$list_field->field_label.'"': '';

									if($list_field->field_type === 'text') {
										$fields_display .= '<div class="'.$field_id.' field field_text form-group control-group"><label for="'.$field_id.'" class="'.$class.'">'.$field_label.'</label><input type="text" name="'.$field_name.'" id="'.$field_id.'" '.$placeholder.' aria-label="'.$field_label.'" class="form-control '.$required.'" style="margin:0"></div>';
										}
									if($list_field->field_type === 'email') {
										$fields_display .= '<div class="'.$field_id.' field field_email form-group control-group"><label for="'.$field_id.'" class="'.$class.'">'.$field_label.'</label><input type="email" name="'.$field_name.'" id="'.$field_id.'" '.$placeholder.' aria-label="'.$field_label.'" class="form-control '.$required.' validate-email" style="margin:0"></div>';
										}
									if($list_field->field_type === 'date') {
											$fields_display .= '<div class="'.$field_id.' field field_date form-group control-group"><label for="'.$field_id.'" class="'.$class.'">'.$field_label.'</label><input type="date" name="'.$field_name.'" id="'.$field_id.'" '.$placeholder.' aria-label="'.$field_label.'" class="form-control '.$required.'" style="margin:0"></div>';
											}
									if($list_field->field_type === 'datetime-local') {
											$fields_display .= '<div class="'.$field_id.' field field_datetime form-group control-group"><label for="'.$field_id.'" class="'.$class.'">'.$field_label.'</label><input type="datetime-local" name="'.$field_name.'" id="'.$field_id.'" '.$placeholder.' aria-label="'.$field_label.'" class="form-control '.$required.'" style="margin:0"></div>';
											}
									if($list_field->field_type === 'textarea') {
										$fields_display .= '<div class="'.$field_id.' field field_textarea form-group control-group"><label for="'.$field_id.'" class="'.$class.'">'.$field_label.'</label><textarea rows="4" cols="50" name="'.$field_name.'" '.$placeholder.' aria-label="'.$field_label.'" id="'.$field_id.'" class="form-control '.$required.'" style="width: 100%;
    margin: 0;"></textarea></div>';
										}
									if($list_field->field_type === 'radio') {
										$values_field = explode(";;",$list_field->field_value);
										$fields_display .= '<div class="'.$field_id.' field field_radio form-group control-group"><label for="'.$field_id.'" class="'.$class.'-radio">'.$list_field->field_label.'</label>';
											foreach( $values_field as $value_field ) {
												$value =  JText::_($value_field);
												$fields_display .= '<input type="radio" value="'.$value.'" name="'.$formid.'['.$value.']'.'" aria-label="'.$value.'" style="margin:0" class="form-control"><label for="'.$field_id.'">'.$value.'</label>';
											}
											$fields_display .='</div>';
									}
									if($list_field->field_type === 'checkbox') {
										$values_field = explode(";;",$list_field->field_value);
										$fields_display .= '<div class="'.$field_id.' field field_checkbox form-group control-group"><label for="'.$field_id.'" class="'.$class.'-checkbox">'.$list_field->field_label.'</label>';
											foreach( $values_field as $value_field ) {
												$value = JText::_($value_field);
												$fields_display .= '<input type="checkbox" value="'.$value.'" name="'.$value.'" class="form-control" aria-label="'.$value.'" style="margin:0" ><label for="'.$field_id.'">'.$value.'</label>'; //TODO add required system
											}
											$fields_display .='</div>';
									}
									if($list_field->field_type === 'select') {
										$values_field = explode(";;",$list_field->field_value);
										$select_label = ($class == 'placeholder') ? $field_label : JText::_('FLEXI_SELECT');
										$fields_display .= '<div class="'.$field_id.' field field_select form-group control-group" ><label for="'.$field_id.'" class="'.$class.'" style="margin:0">'.$field_label.'</label><select id="'.$field_name.'" name="'.$field_name.'" aria-label="'.$field_label.'" class="form-control"><option value="">'.$select_label.'</option>';//TODO add required system
											foreach( $values_field as $value_field ) {
												$value = JText::_($value_field);
												$fields_display .='<option value="'.$value.'">'.JText::_($value_field).'</option>';
											}
										$fields_display .='</select></div>';
									}
									if($list_field->field_type === 'file') {
										$values_field = explode(";;",$list_field->field_value);
										if (!empty($values_field[1]) && $values_field[1] === 'multiple'){
											$uploadmode = 'multiple';
											$maxupload  = 'data-max="'.$values_field[2].'"';
										}else{
											$uploadmode='';
											$maxupload  ='';
										}
										//placehoder is already printed because needed for js alert
										$fields_display .= '<div class="'.$field_id.' field field_file form-group control-group"><label for="'.$field_id.'" class="'.$class.'">'.$field_label.'</label><input type="file" name="'.$field_name.'[]" accept="'.$values_field[0].'" id="'.$field_id.'" placeholder="'.$list_field->field_label.'" aria-label="'.$field_label.'" class="inputfile '.$required.'" '.$uploadmode.' style="margin:0"  '.$maxupload.' ></div>';
									}
									if($list_field->field_type === 'phone') {
										$fields_display .= '<div class="'.$field_id.' field field_phone form-group control-group"><label for="'.$field_id.'" class="'.$class.'">'.$field_label.'</label><input type="phone" name="'.$field_name.'" pattern="'.$value.'" id="'.$field_id.'" '.$placeholder.' aria-label="'.$field_label.'" class="form-control '.$required.'" style="margin:0"></div>';
									}
									if($list_field->field_type === 'hidden') {
										$fields_display .= '<input type="hidden" name="'.$field_name.'" id="'.$field_id.'" value="'.$field_value.'">';
									}
									if($list_field->field_type === 'freehtml') {
										$fields_display .= '<div class="'.$field_id.' field field_html form-group control-group"><p>'.$field_label.'</p><p>'.JText::_($list_field->field_value).'</p></div>';
									}
									if($list_field->field_type === 'url') {
										$fields_display .= '<div class="'.$field_id.' field field_url form-group control-group"><label for="'.$field_id.'" class="'.$class.'">'.$field_label.'</label><input type="url" name="'.$field_name.'" pattern="'.$list_field->field_value.'" id="'.$field_id.'" '.$placeholder.' aria-label="'.$field_label.'" class="form-control '.$required.'" style="margin:0"></div>';
									}
									if($list_field->field_type === 'range') {
										$values_field = explode(";;",$list_field->field_value);
										$fields_display .= '<div class="'.$field_id.' field field_range form-group control-group"><label for="'.$field_id.'" class="'.$class.'">'.$field_label.'</label><input type="range" name="'.$field_name.'" min="'.$values_field[0].'" max="'.$values_field[1].'" step="'.$values_field[2].'" id="'.$field_id.'" '.$placeholder.' aria-label="'.$field_label.'" class="form-control '.$required.'" style="margin:0"></div>';
									}
							}
						}

	$html = '
	'.$modal_header.'
		<form id="contact-form-'.$formid.'" method="POST" class="form-validate" enctype="multipart/form-data">
		<fieldset>
			'.$titleformD.'
			'.$fields_display.'
			'.$consent_field.'
			'.$captcha_display.'
		<div class="form-group control-group submit-button">
		<input type="submit" name="submit" value="'.JText::_('FLEXI_FIELD_EMAIL_SUBMIT_LABEL_VALUE').'" class="'.$submit_class.'">
		</div>
		<input type="hidden" name="emailtask" value="plg.email.submit" />
		<input type="hidden" name="formid" value="'.$formid.'" />
		<input type="hidden" name="emailauthor" value="'.$addr.'" />
		<input type="hidden" name="itemid" value="'.$item->id.'" />
		<input type="hidden" name="itemtitle" value="'.$item->title.'" />
		<input type="hidden" name="itemalias" value="'.$item->alias.'" />
		<input type="hidden" name="itemauthor" value="'.$item->author.'" />
		<input type="hidden" name="catid" value="'.$item->catid.'" />
		<input type="hidden" name="return" value="" />
		'.JHtml::_("form.token").'
		</fieldset>
		</form>
		<script>
			const qsa=(s,o)=>[...(o||document).querySelectorAll(s)],
      		qs=(s,o)=>qsa(s,o)[0];
			qs("input[type=submit]").addEventListener(\'click\',function(e){
 			qsa("input[type=\'file\']").forEach(inp=>{
    			if (inp.files.length > inp.dataset.max){
    			alert(`'.JText::_("FLEXI_ALLOWED_NUM_FILES").' ${inp.dataset.max} '.JText::_("FLEXI_FILES_FOR").' ${inp.placeholder}`);
    		e.preventDefault();
  				}
 				})
			});
		</script>
		'.$modal_footer.'
    ';



		//parti manquante

	// Add prefix / suffix
	$field->{$prop}[$n]	= $pretext . $html . $posttext;

	$n++;
	if (!$multiple)
	break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}
