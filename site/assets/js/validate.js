// TODO: check which variables do not need to be global
var fcform_1st_invalid_found = false;

var max_cat_assign_fc = 0;
var existing_cats_fc  = [];
var fcpass_element = new Object();  // Validation functions needing element instead of value
fcpass_element['jform_catid'] = 1;


// Joomla form validator should be loaded before this script, to allow proper overriding


/**
 * Unobtrusive Form Validation library
 *
 * Inspired by: Chris Campbell <www.particletree.com>
 *
 * @package		Joomla.Framework
 * @subpackage	Forms
 * @since		1.5
 */
var JFormValidator_fc = null;
var JFormValidator = function()
{
	"use strict";
	var handlers, inputEmail, custom, IEversion, isIE8, isIE9, isRTL,

	initialize = function()
	{
		// Prevent Joomla form validation JS script to create 2nd validation object
		if (JFormValidator_fc) return JFormValidator_fc;

		//Joomla.fc_debug = 1;
		//window.console.log('Initializing FLEXIcontent JFormValidator');
		
		// Initialize variables
		handlers = {};
		custom = custom || {};
		IEversion = isIE();
		isIE8 = IEversion ? IEversion < 9 : false;
		isIE9 = IEversion ? IEversion < 10 : false;
		isRTL = jQuery(document.querySelector("html")).attr('dir') === 'rtl' ? true : false;

 	 	inputEmail = (function() {
 	 	 	var input = document.createElement("input");
 	 	 	input.setAttribute("type", "email");
 	 	 	return input.type !== "text";
 	 	})();

		// Default handlers
		setHandler('username', function (value) {
 	 	 	var regex = new RegExp("[\<|\>|\"|\'|\%|\;|\(|\)|\&]", "i");
			return !regex.test(value);
		});

		setHandler('password', function (value) {
			var regex = /^\S[\S ]{2,98}\S$/;
			return regex.test(value);
		});

		setHandler('numeric', function (value) {
 	 		var regex = /^(\d|-)?(\d|,)*\.?\d*$/;
			return regex.test(value);
		});

		setHandler('email', function (value) {
			value = punycode.toASCII(value);
			var regex = /^[a-zA-Z0-9.!#$%&’*+\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
			return regex.test(value);
		});

		setHandler('fieldname', function (value) {
			var regex=/^[a-zA-Z0-9_-]+$/;
			return regex.test(value);
		});

		setHandler('radio', function (par) {
			if (par.parentNode == null) return true;
			//window.console.log( 'validate-radio: ' + jQuery(par.parentNode).attr('id') );

			var options = par.parentNode.getElementsByTagName('input');
			var nl, i;
			for (i=0, nl = options; i<nl.length; i++)
			{
				if (nl[i].checked) return true;
			}
			return false;
		});

		setHandler('checkbox', function (par) {
			if (par.parentNode == null) return true;
			//window.console.log( 'validate-checkbox: ' + jQuery(par.parentNode).attr('id') );

			var options = par.parentNode.getElementsByTagName('input');
			var nl, i;
			for (i=0, nl = options; i<nl.length; i++)
			{
				if (nl[i].checked) return true;
			}
			return false;
		});

		setHandler('catid', function (el) {
			// Check for value if primary category is set
			var $el = jQuery(el);
			var value = $el.val();
			if (value) return true;
			
			// Retrieve selected values for secondary categories
			var element_id = 'jform_cid';
			var field_name = 'jform[cid][]';
			
			// If exactly one secondary category was selected then set it as primary
			var values = jQuery(document.getElementsByName(field_name)).val();
			if (values && values.length == 1)
			{
				$el.val(values[0]);
				// Main category tree maybe different than secondary, so check value exists
				if ($el.val())
				{
					if ($el.hasClass('use_select2_lib'))
					{
						$el.trigger('change');
					}
					return true;
				}
			}
			return false;
		});

		setHandler('fccats', function (el) {
			//var value = el.get('value');
			
			// Retrieve selected values for secondary categories
			var element_id = 'jform_cid';
			var field_name = 'jform[cid][]';
			var field_name_catid = 'jform[catid]';
			
			// Get first secondary category selected to use as maincat
			var values = jQuery('#'+element_id).val();
			var value_catid = values[0];
			
			// Check if maincat is not in already selected secondary cats
			var add_val = ( value_catid && ( jQuery.inArray(value_catid, values) == -1) ) ? 1 : 0;
			
			// Check if the number of categories is over the allowed limit for current user
			if (max_cat_assign_fc && (values.length+add_val) > max_cat_assign_fc) {
				var existing_only = 1;
				for (var i = 0; i < values.length; i++) {
					existing_only = existing_only && ( jQuery.inArray(values[i], existing_cats_fc) >= 0 );
				}
				existing_only = existing_only && ( jQuery.inArray(value_catid, existing_cats_fc) >= 0 );
				if (!existing_only) {
					alert(Joomla.JText._('FLEXI_TOO_MANY_ITEM_CATEGORIES') + max_cat_assign_fc);
					return false;
				}
			}
			return true;
		});

		setHandler('sellimitations', function (el)
		{
			if ( !el ) return true;

			var nl, i;
			var options = el.getElementsByTagName('option');
			var count = 0;
			for (i=0, nl = options; i<nl.length; i++)
			{
				if (nl[i].selected) count++;
			}
			//window.console.log('sellimitations: ' + el.name + ' -- ' + el.id + ' selected count: ' + count);
			
			var min_values = el.getAttribute("data-min_values");
			min_values = min_values ? parseInt( min_values, 10 ) : 0;
			
			var max_values = el.getAttribute("data-max_values");
			max_values = max_values ? parseInt( max_values, 10 ) : 0;
			
			var exact_values = el.getAttribute("data-exact_values");
			exact_values = exact_values ? parseInt( exact_values, 10 ) : 0;
			
			// Check number of values limitations
			var errorMessage = false;
			if ( min_values && count < min_values) {
				errorMessage = Joomla.JText._('FLEXI_FIELD_SELECT_AT_LEAST_PROMPT') + ': ' + min_values;
			}
			else if ( max_values && count > max_values) {
				errorMessage = Joomla.JText._('FLEXI_FIELD_SELECT_UP_TO_PROMPT') + ': ' + max_values;
			}
			else if ( exact_values && count != exact_values) {
				errorMessage = Joomla.JText._('FLEXI_FIELD_SELECT_EXACTLY_PROMPT') + ': ' + exact_values;
			}

			var $el = jQuery(el);
			var $el_NP = !isRTL ? $el.next() : $el.prev();

			if ( errorMessage )
			{
				if ( $el_NP.hasClass('fc-field-invalid') )
				{
					$el_NP.html(errorMessage);
				}
				else
				{
					var err_msg = jQuery('<span class="alert alert-warning fc-field-invalid fc_notes">' + errorMessage + '</span>');
					!isRTL ? err_msg.insertAfter( $el ) : err_msg.insertBefore( $el );
				}

				/*var $sel2box = $el.prev('.select2-container');
				if ( $sel2box.length && !$sel2box.prev().hasClass('fc-field-invalid') )
				{
					var err_msg = jQuery('<span class="fc-field-invalid alert alert-error">' + Joomla.JText._($el.val() ? 'FLEXI_INVALID' : 'FLEXI_REQUIRED') + '</span>');
					!isRTL ? err_msg.insertBefore( $sel2box ) : err_msg.insertAfter( $sel2box );
				}*/
				return false;
			}

			else if ( $el_NP.hasClass('fc-field-invalid') )
			{
				$el_NP.remove();
			}

			return true;
		});

		setHandler('cboxlimitations', function (el)
		{
			if ( !el.parentNode.parentNode ) return true;

			var el_name = el.name;
			var $form = jQuery(el.form);

			var fcform_input_sets = $form.data('fcform_input_sets');
			if (fcform_input_sets && typeof fcform_input_sets[el_name] !== 'undefined') return null;

			var nl, i;
			var options = el.parentNode.parentNode.getElementsByTagName('input');
			var count = 0;
			for (i=0, nl = options; i<nl.length; i++)
			{
				if (nl[i].checked) count++;
			}

			// Do not revalidate other checkboxes with same name
			if (fcform_input_sets && typeof fcform_input_sets[el_name] === 'undefined')
			{
				fcform_input_sets[el_name] = count;
				$form.data('fcform_input_sets', fcform_input_sets);
			}
			//window.console.log('cboxlimitations: ' + el.name + ' -- ' + el.id + ' selected count: ' + count);
			
			var min_values = el.getAttribute("data-min_values");
			min_values = min_values ? parseInt( min_values, 10 ) : 0;
			
			var max_values = el.getAttribute("data-max_values");
			max_values = max_values ? parseInt( max_values, 10 ) : 0;
			
			var exact_values = el.getAttribute("data-exact_values");
			exact_values = exact_values ? parseInt( exact_values, 10 ) : 0;
			
			// Check number of values limitations
			var errorMessage = false;
			if ( min_values && count < min_values) {
				errorMessage = 'Minimum number of values: '+min_values;
			}
			else if ( max_values && count > max_values) {
				errorMessage = 'Maximum number of values: '+max_values;
			}
			else if ( exact_values && count != exact_values) {
				errorMessage = 'Number of values must be exactly : '+exact_values;
			}

			var $el = jQuery(el);
			var $parent = $el.closest('.fc_input_set');
			var $el_NP = !isRTL ? $parent.next() : $parent.prev();

			if ( errorMessage )
			{

				if ( $el_NP.hasClass('fc-field-invalid') )
				{
					$el_NP.html(errorMessage);
				}
				else
				{
					var err_msg = jQuery('<span class="alert alert-warning fc-field-invalid fc_notes">' + errorMessage + '</span>');
					!isRTL ? err_msg.insertAfter( $parent ) : err_msg.insertBefore( $parent );
				}

				return false;
			}
			
			else if ( $el_NP.hasClass('fc-field-invalid') )
			{
				$el_NP.remove();
			}
			
			return true;
		});
	},

	setHandler = function(name, fn, en)
	{
		en = (en === '') ? true : en;
		handlers[name] = {
			enabled : en,
			exec : fn
		};
	},

	attachTinyMceValidation = function($el)
	{
		setTimeout(function () {
			if (typeof tinyMCE !== 'undefined' && tinyMCE && $el.hasClass('mce_editable'))
			{
				var editor = tinyMCE.get( $el.attr('id') );
				if (tinyMCE.majorVersion >= 4)
				{
					editor.on('change', function (e)
					{
						var ed = this;
						var $el = jQuery('#' + ed.id);
						var editor_text = $el.is(":visible") ? $el.val() : ed.getContent().trim();

						if (!$el.is(":visible")) $el.val(editor_text);
						if (editor_text === null || editor_text.length==0) ed.setContent('');
	
	          $el.trigger('blur');
					});
				}
				else
				{
					editor.onChange.add(function (ed, e)
					{
						var $el = jQuery('#' + ed.editorId);
						var editor_text = $el.is(":visible") ? $el.val() : ed.getContent().trim();

						if (!$el.is(":visible")) $el.val(editor_text);
						if (editor_text === null || editor_text.length==0) ed.setContent('');

	          $el.trigger('blur');
					});
				}
			}
		}, 10000);
	},
	
	attachToForm = function(form, container)
	{
 	 	var inputFields = [], elements,
 	 		$form = jQuery(form);
		
		// Iterate through the form object and attach the validate method to all input fields.
		//var elements = $form.find('input, textarea, select, fieldset, button');
		var elements = typeof container !== 'undefined' && container ?
			jQuery(container).find('input, textarea, select, fieldset, button').toArray() :
			$form.find('fieldset').toArray().concat(Array.from(form.elements)) ;

 	 	for (var i = 0, l = elements.length; i < l; i++)
 	 	{
			var $el = jQuery(elements[i]);
			var tagName = elements[i].tagName;
			var inputType = tagName != 'INPUT' ? false : elements[i].type;
			
			if ( hasClass(elements[i], 'required') )
			{
				$el.attr('aria-required', 'true').attr('required', 'required');
			}
			
			// Styled via JS
			if ( hasClass(elements[i], 'use_select2_lib') || hasClass(elements[i], 'use_prettycheckable') )
			{
				$el.on('change', function(){ return document.formvalidator.validate(this); });
			}
			
			// Radio / checkbox
			else if ( inputType == 'radio' || inputType == 'checkbox' )
			{
				isIE8 ?
					$el.on('change', function(){ document.formvalidator.validate(this); return true; }) :  /*IE8 or less*/
					$el.on('change', function(){ return document.formvalidator.validate(this); }) ;
			}
			
			// Submit button, needs to return true false to avoid form being submited 
			else if ( (tagName == 'INPUT' || tagName == 'BUTTON') && (inputType == 'submit' || inputType === 'image') )
			{
				if (hasClass(elements[i], 'validate')) {
					$el.on('click', function(){
						return document.formvalidator.isValid(this.form);
					});
				}
			}
			
			// Text inputs, selects, textareas ... other ?
			else if (tagName !== 'BUTTON' && !(tagName === 'INPUT' && inputType === 'button'))
			{
				if (tagName == 'TEXTAREA')
				{
					var field_box = $el.closest('.fcfield_box.required_box');
					if (field_box.length)
					{
						$el.addClass('required').addClass('required_boxed').data('label_text', field_box.data('label_text'));
					}

					document.formvalidator.attachTinyMceValidation($el);
				}
				if (tagName !== 'FIELDSET')
				{
					$el.on('blur', function(){ return document.formvalidator.validate(this); });   // Catch user leaving the form element

					if (tagName == 'INPUT')
					{
						$el.on('change', function() { return document.formvalidator.validate(this); });  // Catch JS (e.g. inputmask JS) modifying the input and then triggering change event

						if (hasClass(elements[i], 'validate-email') && inputEmail)
						{
							try { elements[i].type = 'email'; } catch (e) { /*IE8 or less or other non-supporting browsers*/}
						}
					}
				}
			}

		}
	},

	refreshFormLabels = function(form)
	{
		var vTimeStart = new Date().getTime();
		
		// Iteration through DOM labels
		var $lbl, $el, for_id, el_id, $lbls_hash = {};
		
		jQuery('label').each(function()
		{
			$lbl = jQuery(this);
			for_id = this.getAttribute('for');
			for_id = for_id ? for_id : this.getAttribute('data-for');
			if (for_id)
			{
				$lbls_hash[for_id] = $lbl;
			}

			// Also check ID-lbl as ID of label, needed for fieldset class="radio/checkbox"
			// for other cases it is better not to rely on this, be compliant and specify ' for="..." '
			var lbl_id = this.getAttribute('id');
			if (lbl_id && lbl_id.indexOf('-lbl', lbl_id.length - 4) !== -1)
			{
				$lbls_hash[lbl_id.slice(0, -4)] = $lbl;
			}
		});
		
		// Set to zero length the .data('label') of elements without one
		var f = jQuery(form).find('fieldset').toArray().concat(Array.from(form.elements));

		for(var i=0; i<f.length; i++)
		{
			$el = jQuery(f[i]);
			if ( $el.data('label') === undefined )
			{
				el_id = f[i].getAttribute('data-element-grpid');
				el_id = el_id ? el_id : f[i].getAttribute('id');
				
				if ( !el_id )
					$el.data('label', false) ;
				else if ( $lbls_hash.hasOwnProperty(el_id) )
					$el.data('label', $lbls_hash[el_id]);
				else
				{
					el_id = el_id.replace(/_[0-9]+$/, '');
					$lbls_hash.hasOwnProperty(el_id) ?
						$el.data('label', $lbls_hash[el_id]) :
						$el.data('label', false) ;
				}
			}
		}
		if (!!Joomla.fc_debug) window.console.log( 'refreshFormLabels() time: ' + ((new Date())  - vTimeStart) + ' ms -- LABELs hash size: ' + Object.keys($lbls_hash).length);
	},


 	findLabel = function($elem)
 	{
  	var $label = $elem.data('label');
  	
		if ( !!$label ) return $label;  // $label && $label !== undefined
		
  	// New element encountered (first run or / newly injected into the dom), redo iteration of DOM labels ... updating this and any other injected elements
		// Prefer 'data-element-grpid' for radio/checkbox or other fields, if it is set
		var id = $elem.attr('data-element-grpid');
		id = id ? id : $elem.attr('id');
		
		!id ?
			$elem.data('label', false) :
			refreshFormLabels($elem.get(0).form) ;
  	
    // Before returning label checking if it is set, (refreshFormLabels() should have set it, but check anyway)
    $label = $elem.data('label');
		if ($label === undefined)
		{
			$label = false;
			$elem.data('label', $label);
		}
		return $label;
	},


	validate = function(el)
	{
		var $el = jQuery(el);
		var $form = $el.form ? jQuery($el.form) : $el.closest('form');
		if ($form.length && $form.data('skip_validation')) return true;

		var el_id = $el.attr('id');
		//window.console.log('validate on :' + el_id + ' = ' + '-' + $el.val() + '-');

		// Ignore the element if its currently disabled, because are not submitted for the http-request. For those case return always true.
		if($el.attr('disabled'))
		{
			handleResponse(true, $el);
			return true;
		}

		if (el_id && el_id.substring(0,11) == 'jform_rules') {
			return true;
		}

		// BASIC 'required' VALIDATION: check that field has a non-empty value
		if ( hasClass(el, 'required') || $el.attr('aria-required')=='true' )
		{
			var inputType = $el.attr('type');

			// textareas that use JS editors, need special call to get current contents
			if (el_id && $el.prop("tagName") == 'TEXTAREA')
			{
				// tinyMCE
				if ((hasClass(el, 'mce_editable') || hasClass(el, 'wf-editor')) && typeof tinyMCE !== 'undefined' && tinyMCE && tinyMCE.get(el_id))
				{
					// Visible element means editor is currently OFF
					var editor_text = $el.is(":visible") ? $el.val() : tinyMCE.get(el_id).getContent().trim();
					if (!$el.is(":visible")) $el.val(editor_text);
					if (editor_text === null || editor_text.length==0) tinyMCE.get(el_id).setContent('');
					$el.data('use_fcfield_box', 1);
				}
				// CodeMirror
				else if ($el.next('.CodeMirror').length)
				{
					// Visible element means editor is OFF
					var editor_text = $el.is(":visible") ? $el.val() : $el.next('.CodeMirror').get(0).CodeMirror.getValue().trim();
					if (!$el.is(":visible")) $el.val(editor_text);
					if (editor_text === null || editor_text.length==0) $el.next('.CodeMirror').get(0).CodeMirror.setValue('');
					$el.data('use_fcfield_box', 1);
				}
				else $el.data('use_fcfield_box', 0);
			}

			// Radio / Checkbox can not be checked with val() instead we will use special validation handlers
			if (inputType == 'radio' || inputType == 'checkbox') ;

			// Check remaining 'required' field using .val()
			else if ($el.val() === null || $el.val().length==0)
			{
				handleResponse(false, $el);
				return false;
			}
		}

		// If no more validation is needed:  mark/update element's display as valid and return success
		var handler = (el.className && el.className.search(/validate-([a-zA-Z0-9\_\-]+)/) != -1) ? el.className.match(/validate-([a-zA-Z0-9\_\-]+)/)[1] : "";
		if (handler == '') {
			handleResponse(true, $el);
			return true;
		}
		
		// ADVANCED method-specific validation for fields 'validation-*' CSS class
		if (handler == "sellimitations" || handler == "cboxlimitations") {
			// Execute the validation handler and return result
			var result = handlers[handler].exec(el);
			if (result === null) return true;  // Already handled

			if (result != true) {
				handleResponse(false, $el);
				return false;
			}
		} else if ( !($el.attr('type') == "radio" || $el.attr('type') == "checkbox") ){
			// Individual radio & checkbox can have blank value, providing one element in group is set
			if ( typeof fcpass_element[el_id] !== 'undefined' ) {
				// Execute the validation handler and return result
				if (handlers[handler].exec(el) != true) {
					handleResponse(false, $el);
					return false;
				}
			} else if ((handler) && (handler != 'none') && (handlers[handler]) && $el.val()) {
				// Execute the validation handler and return result
				if (handlers[handler].exec($el.val()) != true) {
					handleResponse(false, $el);
					return false;
				}
			}
		} else {
			if ((handler) && (handler != 'none') && (handlers[handler])) {
				if($el.attr('type') == "radio" || $el.attr('type') == "checkbox"){
					if (hasClass(el, 'required')) {
						// Execute the validation handler and return result
						if (handlers[handler].exec(el.parentNode) != true) {
							handleResponse(false, $el);
							return false;
						}
					}
				}
			}
		}

		// No errors found by method-specific validation handlers:  mark/update element's display as valid and return success
		handleResponse(true, $el);
		return true;
	},


	isValid = function(form)
	{
		var $form = jQuery(form);
		var isFCvalidation = typeof window.fc_validateOnSubmitForm !== undefined ? window.fc_validateOnSubmitForm : 0;
		var use_all_mssg_box = $form.data('use_all_mssg_box');

		if ($form.length && $form.data('skip_validation'))
		{
			$form.data('fcform_isValid', true);
			return true;
		}

		var vTimeStart = new Date().getTime();
 		var fields, valid = true, message, error, label, label_text, usage_text, invalid_el, invalid = [], i, l;
 		
 		// Remove any inline error messages (added by any previous form, note we do not add this to individual fields)
		jQuery('.fc-field-invalid').remove();
		
		// Global variable defined above, we use this to focus the first tab that contains required field
		fcform_1st_invalid_found = false;
		$form.data('fcform_input_sets', []);
 		
 		// Get fieldset containers of (checkbox/radio) and all form fields
 		//fields = jQuery(form).find('input, textarea, select, fieldset');
		fields = jQuery(form).find('fieldset').toArray().concat(Array.from(form.elements));
		
		// Validate form fields
		for (i = 0, l = fields.length; i < l; i++)
		{
			if ( hasClass(fields[i], 'novalidate') || fields[i].tagName=='BUTTON' )
			{
				continue;
			}
			if ( ! this.validate(fields[i]) )
			{
				fcform_1st_invalid_found = true;
				valid = false;
				invalid.push(fields[i]);
			}
		}

		// Run custom form validators if present
		jQuery.each(this.custom, function(key, validator) {
			if (validator.exec() != true) {
				valid = false;
			}
		});
		
		// Special handling for making the recaptcha field to be "required", for other captcha plugins,
		// we will have only the server side validation, or also any client side checks done by the plugin itself
		var recaptcha = jQuery('#recaptcha_response_field');
		var recaptcha_lbl = jQuery('#recaptcha_response_field-lbl');
		if ( recaptcha.length ) {
			if ( recaptcha.val() == '' ) {
				recaptcha.addClass('invalid');
				recaptcha.attr('aria-invalid', 'true');
				if (recaptcha_lbl.length) recaptcha_lbl.addClass('invalid');
				valid = false;
			} else {
				recaptcha.removeClass('invalid');
				recaptcha.attr('aria-invalid', 'false');
				if (recaptcha_lbl.length) recaptcha_lbl.removeClass('invalid');
			}
		}

 	 	if (!valid && invalid.length > 0)
		{
			message = Joomla.JText._('JLIB_FORM_FIELD_INVALID');
			error = {"error": []};
			var added = [];
			for (i = invalid.length - 1; i >= 0; i--)
			{
				invalid_el = jQuery(invalid[i]);
				label = invalid_el.data('label');
				label_text = label ? label.text() : invalid_el.data('label_text');
				if (label_text && typeof added[label_text] === 'undefined')
				{
					//var usage_text = invalid_el.attr('title') ? ': ' + invalid_el.attr('title') : '';
					error.error.push(message + label_text.replace('*', '') /*+ usage_text*/);
					added[label_text] = 1;
				}
			}
			if (use_all_mssg_box || !isFCvalidation)
			{
				Joomla.renderMessages(error);
			}
		}
		else
		{
			jQuery('#system-message-container').html('');
		}

		$form.data('fcform_isValid', valid);    // Set flag for form validation state
		$form.data('fcform_input_sets', null);  // Clear to allow atomic calls to validate()

		if (!!Joomla.fc_debug) window.console.log( 'isValid() time: ' + ((new Date())  - vTimeStart) + ' ms');
		
		// Do scrolling here if not an FC form
		if (!valid && !isFCvalidation)
		{
			window.console.log('scrolling');
			// Form is not invalid, focus the first invalid element, or focus the all errors container
			var invalid = jQuery('.invalid').first();  // Get single element so that hidden check will work

			setTimeout(function()   // This works without the timeout too ...
			{
				if (invalid.is(':hidden'))
				{
					invalid = jQuery('#system-message-container');
				}
				if (invalid.length)
				{
					var pos = invalid.offset().top - 80;
					jQuery('html, body').animate({
						scrollTop: pos
					}, 400);
					invalid[0].focus();
				}
			}, 20);
		}
		
		return valid;
	},

	handleResponse = function(state, $el)
	{
		// If given field has failed validation, and it is the FIRST field to fail the validation then check if it is inside a TAB, make sure the TAB get focused
		if (state === false && !fcform_1st_invalid_found)
		{
			jQuery('[href="#attrib-fcfields"]').trigger('click');

			var tab = $el.parent().closest("div.tabbertab");
			var tabset = $el.parent().closest("div.tabberlive");
			
			while(1)
			{
				if (tabset.length==0 || tab.length==0) break;
				
				var tabsetid = tabset.attr('id');
				var tabid = tab.attr('id');
				var tabno = tab.index() - 1;
				fctabber[tabset.attr('id')].tabShow(tabno);
				
				tab = jQuery(tab).parent().closest("div.tabbertab");
				tabset = jQuery(tabset).parent().closest("div.tabberlive");
			}

			// Performance concern ... only do for first invalid field  -- Check of fieldgroup value box is hidden and open it
			var fieldval_box = $el.parent().closest("li.fcfieldval_container");
			var fieldgroup_box = fieldval_box.parent().parent();
			if (fieldgroup_box.is(":hidden"))
			{
				fieldgroup_box.prev().find('.fc-show-vals-btn').data('fc_noeffect', 1).trigger('click');
			}

			// Performance concern ... only do for first invalid field  --  Check if it is part of a field group and make sure the fied group is expanded
			fieldval_box.find(".fc-toggle-group-down").filter(":visible").data('fc_noeffect', 1).trigger('click');   // Only if is drop down button is visible = valuebox is hidden
		}
		
 		// Get the label
 	 	var $label = $el.data('label');
 	 	if ($label === undefined)
		{
 	 		$label = findLabel($el);
 	 	}

		var el = $el.get(0);
		var tagName = el.tagName;
		var inputType = tagName != 'INPUT' ? false : el.type;
		var is_radio_check = inputType == 'checkbox' || inputType == 'radio';

		var $form = jQuery(el.form);
		var use_all_mssg_box = $form.data('use_all_mssg_box');

		// Set the element and its label (if exists) invalid state
		if (state === false)
		{
			// Add INVALID to ELEMENT (but not to checkboxes/radio, because we will try to add find an outer container for them)
			if (!is_radio_check)
			{
				$el.addClass('invalid').attr('aria-invalid', 'true');
			}

			// Add INVALID to LABEL, (Thus adding CSS style that indicates a validation error for current form field / fieldset)
			if ($label)
			{
				$label.addClass('invalid');
			}

			// NOT using inline messages and also label found, skip inline message
			if (use_all_mssg_box && $label);

			// Add inline message
			else if ($el.data('use_fcfield_box'))
			{
				if (!$el.closest('.fcfield_box.required_box').prev().hasClass('fc-field-invalid'))
				{
					jQuery('<span class="fc-field-invalid alert alert-error">' + Joomla.JText._($el.val() ? 'FLEXI_INVALID' : 'FLEXI_REQUIRED') + '</span>').insertBefore( $el.closest('.fcfield_box.required_box') );
				}
			}
			else if ($el.parent().hasClass('fc-xpended-row'))
			{
				if (!$el.parent().next().hasClass('fc-field-invalid'))
				{
					$el.parent().css({'width': 'auto'});
					jQuery('<span class="fc-field-invalid alert alert-error">' + Joomla.JText._($el.val() ? 'FLEXI_INVALID' : 'FLEXI_REQUIRED') + '</span>').insertAfter( $el.parent() );
				}
			}

			else if ($el.hasClass('fc_hidden_value'))
			{
				if (!$el.parent().children(':first-child').hasClass('fc-field-invalid'))
				{
					jQuery('<span class="fc-field-invalid alert alert-error">' + Joomla.JText._($el.val() ? 'FLEXI_INVALID' : 'FLEXI_REQUIRED') + '</span>').prependTo( $el.parent() );
				}
			}

			// Do not add for radio-buttons / checkoxes, since these have custom code
			else if (!is_radio_check)
			{
				if (!$el.next().hasClass('fc-field-invalid'))
				{
					$el.css({'width': 'auto'});
					jQuery('<span class="fc-field-invalid alert alert-error">' + Joomla.JText._($el.val() ? 'FLEXI_INVALID' : 'FLEXI_REQUIRED') + '</span>').insertAfter( $el );
				}
			}
			
			if (is_radio_check)
			{
				var inputSet = $el.data('inputSet');
				if (inputSet === undefined)
				{
					inputSet = $el.closest('.fc_input_set, .btn-group');
					inputSet.length ? inputSet.find('input').data('inputSet', inputSet) : $el.data('inputSet', false);
				}
				if (inputSet && inputSet.length)
				{
					var inputSet_NP = !isRTL ? inputSet.next() : inputSet.prev();

					if (!inputSet_NP.hasClass('fc-field-invalid'))
					{
						var err_msg = jQuery('<span class="fc-field-invalid alert alert-error">' + Joomla.JText._('FLEXI_INVALID') + '</span>');
						!isRTL ? err_msg.insertAfter( inputSet ) : err_msg.insertBefore( inputSet );
					}
					inputSet.addClass('invalid');
				}
				else {
					//if (!$label) $el.addClass('invalid').attr('aria-invalid', 'true');
				}
			}

			// If field uses an outer visible container then mark it as invalid
			if ($el.data('use_fcfield_box')) $el.closest('.fcfield_box.required_box').addClass('invalid');
		}
		else
		{
			// Remove INVALID from ELEMENT
			$el.removeClass('invalid').attr('aria-invalid', 'false');
			
			// Remove INVALID from LABEL, (Thus clearing CSS style that indicates a validation error for current form field / fieldset)
			if ($label)
			{
				$label.removeClass('invalid');
			}

			// NOT using inline messages and also label found, nothing to do
			if (use_all_mssg_box && $label);

			// Remove any inline message
			else if ($el.data('use_fcfield_box'))
			{
				$el.closest('.fcfield_box.required_box').prev('.fc-field-invalid').remove();
			}
			else if ($el.parent().hasClass('fc-xpended-row'))
			{
				$el.parent().css({'width': ''}).next('.fc-field-invalid').remove();
			}

			else if ($el.hasClass('fc_hidden_value'))
			{
				$el.parent().children(':first-child.fc-field-invalid').remove();
			}

			// Do not remove for radio-buttons / checkoxes, have custom code for this
			else if (!is_radio_check)
			{
				$el.css({'width': ''}).next('.fc-field-invalid').remove() ;
			}

			if (is_radio_check)
			{
				var inputSet = $el.data('inputSet');
				if ( inputSet && inputSet.length )
				{
					var inputSet_NP = !isRTL ? inputSet.next() : inputSet.prev();

					if (inputSet_NP.hasClass('fc-field-invalid')) inputSet_NP.remove();
					inputSet.removeClass('invalid');
				}
			}

			// If field uses an outer visible container then remove invalid marking from it
			if ($el.data('use_fcfield_box')) $el.closest('.fcfield_box.required_box').removeClass('invalid');
		}
	},
	
	isIE = function() {
		var userAgent = navigator.userAgent.toLowerCase();
		return (userAgent.indexOf('msie') != -1) ? parseInt(userAgent.split('msie')[1]) : false;
	},
	
	hasClass = function(el, class_name)
	{
		return isIE8 || isIE9 ? jQuery(el).hasClass(class_name) : el.classList.contains(class_name);
	}

	
	// Initialize handlers and attach validation to form
	initialize();

	// Public API methods an public properties of the class
	return {
		attachTinyMceValidation : attachTinyMceValidation,
		isValid : isValid,
		validate : validate,
		setHandler : setHandler,
		attachToForm : attachToForm,
		custom: custom
	};
};

//alert('Loading FLEXIcontent form validator');
JFormValidator_fc = new JFormValidator();

document.formvalidator = null;

jQuery(document).ready(function()
{
	document.formvalidator = JFormValidator_fc;
	
	var vTimeStart = new Date().getTime();
	jQuery('form.form-validate').each(function(){
		document.formvalidator.attachToForm(this);
	});
	if (!!Joomla.fc_debug) window.console.log( 'attachToForm() time: ' + ((new Date())  - vTimeStart) + ' ms');
});


function flexi_submit(task, btn_box, msg_box)
{
	Joomla.submitbutton(task);
	$form = jQuery('#adminForm');
	
	if ($form.data('fcform_isValid'))
	{
		if (typeof btn_box !== 'undefined')
		{
			//alert('hide submit btns');
			jQuery('#'+btn_box).hide();
			$form.data('btn_box', jQuery('#'+btn_box));
		}
		if (typeof msg_box !== 'undefined')
		{
			//alert('show submit msg');
			jQuery('#'+msg_box).show();
			$form.data('msg_box', jQuery('#'+msg_box));
		}
	}
}
