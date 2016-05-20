/**
 * @copyright	Copyright (C) 2005 - 2012 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// TODO: check which variables do not need to be global
var fcform_isValid = false;
var first_invalid_field_found;
var max_cat_assign_fc = 0;
var existing_cats_fc  = [];
var fcpass_element = new Object();  // Validation functions needing element instead of value
fcpass_element['jform_catid'] = 1;

/*Object.size = function(obj) {
	var size = 0, key;
	for (key in obj) {
		if (obj.hasOwnProperty(key)) size++;
	}
	return size;
};*/


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
	var handlers, inputEmail, custom,

	initialize = function()
	{
		// Joomla form validator should be loaded before this script, to avoid potential conflicts
		// we prevent Joomla form validation JS script to create 2nd validation object
		if (JFormValidator_fc) return JFormValidator_fc;  //
		//alert('Initializing FLEXIcontent form validator');
		
		// Initialize variables
		handlers = {};
		custom = custom || {};

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
			var nl, i;
			if (par.parentNode == null) {
				return true;
			} else {
				var options = par.parentNode.getElementsByTagName('input');
				
				for (i=0, nl = options; i<nl.length; i++) {
					if (nl[i].checked) return true;
				}
				
				return false;
			}
		});

		setHandler('checkbox', function (par) {
			var nl, i;
			if (par.parentNode == null) {
				return true;
			} else {
				var options = par.parentNode.getElementsByTagName('input');
				
				for (i=0, nl = options; i<nl.length; i++) {
					if (nl[i].checked) return true;
				}
				
				return false;
			}
		});

		setHandler('checkbox2', function (par) {
			var nl, i;
			if (par.parentNode == null) {
				return true;
			} else {
				var options = par.parentNode.getElementsByTagName('input');
				
				var count = 0;
				for (i=0, nl = options; i<nl.length; i++) {
					if (nl[i].checked) count++;
				}
				
				//exactly 2 options
				if(count == 2) return true;
				return false;
			}
		});

		setHandler('checkbox3', function (par) {
			var nl, i;
			if (par.parentNode == null) {
				return true;
			} else {
				var options = par.parentNode.getElementsByTagName('input');
				
				var count = 0;
				for (i=0, nl = options; i<nl.length; i++) {
					if (nl[i].checked) count++;
				}
				
				//exactly 3 options
				if(count == 3) return true;
				return false;
			}
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
			if (values && values.length == 1) {
				$el.val(values[0]);
				if ($el.val()) {  // main category tree maybe different than secondary, so check value exists
					if ($el.hasClass('use_select2_lib')) {
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
			for (i=0, nl = options; i<nl.length; i++) {
				if (nl[i].selected) count++;
			}
			
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
			var $next = $el.next();
			
			if ( errorMessage )
			{
				if ( $next.hasClass('invalid_jfield_message') )
					$next.html(errorMessage);
				else
					jQuery('<span class="alert alert-warning invalid_jfield_message" style="display:inline-block; margin: 2px 12px;">' + errorMessage + '</span>').insertAfter( $el );
				return false;
			}
			
			else if ( $next.hasClass('invalid_jfield_message') )
			{
				$next.remove();
			}
			
			return true;
		});

		setHandler('cboxlimitations', function (el)
		{
			if ( !el.parentNode.parentNode ) return true;
			
			var nl, i;
			var options = el.parentNode.parentNode.getElementsByTagName('input');
			var count = 0;
			for (i=0, nl = options; i<nl.length; i++) {
				if (nl[i].checked) count++;
			}
			
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
			var $next = $parent.next();
			
			if ( errorMessage )
			{
				if ( $next.hasClass('invalid_jfield_message') )
					$next.html(errorMessage);
				else
					jQuery('<span class="alert alert-warning invalid_jfield_message" style="display:inline-block; margin: 2px 12px;">' + errorMessage + '</span>').insertAfter( $parent );
				return false;
			}
			
			else if ( $next.hasClass('invalid_jfield_message') )
			{
				$next.remove();
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

	attachToForm = function(form)
	{
 	 	var inputFields = [], elements,
 	 		$form = jQuery(form);
		
		var isIE8 = isIE() ? isIE() < 9 : false;
		
		// Iterate through the form object and attach the validate method to all input fields.
		//elements = $form.find('input, textarea, select, fieldset, button');
		elements = $form.find('fieldset').toArray().concat(Array.from(form.elements));

 	 	for (var i = 0, l = elements.length; i < l; i++)
 	 	{
			var $el = jQuery(elements[i]);
			var tagName = $el.prop("tagName").toLowerCase();
			var tagType = $el.attr('type');
			
			if ( $el.hasClass('required') )
			{
				$el.attr('aria-required', 'true').attr('required', 'required');
			}
			
			// Styled via JS
			if ( $el.hasClass('use_select2_lib') || $el.hasClass('use_prettycheckable') )
			{
				$el.on('change', function(){ return document.formvalidator.validate(this); });
			}
			
			// Radio / checkbox
			else if ( tagType == 'radio' || tagType == 'checkbox' )
			{
				isIE8 ?
					$el.on('change', function(){ document.formvalidator.validate(this); return true; }) :  /*IE8 or less*/
					$el.on('change', function(){ return document.formvalidator.validate(this); }) ;
			}
			
			// Submit button, needs to return true false to avoid form being submited 
			else if ( (tagName == 'input' || tagName == 'button') && (tagType == 'submit' || tagType === 'image') )
			{
				if ($el.hasClass('validate')) {
					$el.on('click', function(){
						return document.formvalidator.isValid(this.form);
					});
				}
			}
			
			// text inputs, selects, ... other ?
			else if (tagName !== 'button' && !(tagName === 'input' && $el.attr('type') === 'button'))
			{
				if (tagName !== 'fieldset') {
					$el.on('blur', function(){ return document.formvalidator.validate(this); });
	 	 	 	 	if (tagName == 'input' && $el.hasClass('validate-email') && inputEmail) {
	 	 	 	 		try { $el.get(0).type = 'email'; } catch (e) { /*IE8 or less*/}
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
			for_id = $lbl.attr('for');
			if (for_id)
			{
				$lbls_hash[for_id] = $lbl;
			}

			// Also check ID-lbl as ID of label, needed for fieldset class="radio/checkbox"
			// for other cases it is better not to rely on this, be compliant and specify ' for="..." '
			var lbl_id = $lbl.attr('id');
			if (lbl_id && lbl_id.indexOf('-lbl', lbl_id.length - 4) !== -1) {
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
				el_id = $el.attr('data-element-grpid');
				el_id = el_id ? el_id : $el.attr('id');
				
				if ( !el_id )
					$el.data('label', false) ;
				else if ( $lbls_hash.hasOwnProperty(el_id) )
					$el.data('label', $lbls_hash[el_id]);
				else
				{
					el_id = el_id.replace(/_[0-9]+$/, '')
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
		var el_id = $el.attr('id');

		if (el_id && el_id.substring(0,11) == 'jform_rules') {
			return true;
		}
		
		// Ignore the element if its currently disabled, because are not submitted for the http-request. For those case return always true.
		if($el.attr('disabled')) {
			handleResponse(true, $el);
			return true;
		}

		// BASIC 'required' VALIDATION: check that field has a non-empty value
		if ( $el.hasClass('required') || $el.attr('aria-required')=='true' ) {
			if($el.attr('type') == 'radio' || $el.attr('type') == 'checkbox') {
				// radio/checkbox can be checked only via specific validation handler if this is set
			}
			else if ($el.val() === null || $el.val().length==0) {
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
			if (handlers[handler].exec(el) != true) {
				handleResponse(false, $el);
				return false;
			}
		} else if( !($el.attr('type') == "radio" || $el.attr('type') == "checkbox") ){
			// Individual radio & checkbox can have blank value, providing one element in group is set
			if ( typeof fcpass_element[el_id] != 'undefined' ) {
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
					if ($el.hasClass('required')) {
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
		var vTimeStart = new Date().getTime();
 		var fields, valid = true, message, error, label, invalid = [], i, l;
 		
 		// Remove any inline error messages (added by any previous form, note we do not add this to individual fields)
		jQuery('.invalid_jfield_message').remove();
		
		// Global variable defined above, we use this to focus the first tab that contains required field
		first_invalid_field_found = false;
 		
 		// Get fieldset containers of (checkbox/radio) and all form fields
 		//fields = jQuery(form).find('input, textarea, select, fieldset');
		fields = jQuery(form).find('fieldset').toArray().concat(Array.from(form.elements));
		
		// Validate form fields
		for (i = 0, l = fields.length; i < l; i++)
		{
			var $el = jQuery(fields[i]), tagName = $el.prop("tagName").toLowerCase();
			if ( $el.hasClass('novalidate') || tagName=='button' ) {
				continue;
			}
			if ( ! this.validate(fields[i]) )
			{
				first_invalid_field_found = true;
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
		
 	 	if (!valid && invalid.length > 0) {
 	 	 	message = Joomla.JText._('JLIB_FORM_FIELD_INVALID');
 	 	 	error = {"error": []};
 	 	 	var added = [];
 	 	 	for (i = invalid.length - 1; i >= 0; i--) {
 	 	 		label = jQuery(invalid[i]).data("label");
 	 			if (label && typeof added[label.text()] === 'undefined')
				{
 	 	 			error.error.push(message + label.text().replace("*", ""));
					//if (jQuery(invalid[i].attr('title')) message = message + ' ' + jQuery(invalid[i].attr('title');
	 	 	 		added[label.text()] = 1;
				}
 	 	 	}
 	 	 	Joomla.renderMessages(error);
 	 	}
		
		fcform_isValid = valid;
		if (!!Joomla.fc_debug) window.console.log( 'isValid() time: ' + ((new Date())  - vTimeStart) + ' ms');
		return valid;
	},

	handleResponse = function(state, $el)
	{
		// If given field has failed validation, and it is the FIRST field to fail the validation then check if it is inside a TAB, make sure the TAB get focused
		if (state === false && !first_invalid_field_found)
		{
			var tab = $el.parent().closest("div.tabbertab");
			var tabset = $el.parent().closest("div.tabberlive");
			
			while(1) {
				if (tabset.length==0 || tab.length==0) break;
				
				var tabsetid = tabset.attr('id');
				var tabid = tab.attr('id');
				var tabno = tab.index() - 1;
				fctabber[tabset.attr('id')].tabShow(tabno);
				
				tab = jQuery(tab).parent().closest("div.tabbertab");
				tabset = jQuery(tabset).parent().closest("div.tabberlive");
			}
		}
		
		// If given field has failed validation, then check if it is part of a field group and make sure the fied group is expanded
		if (state === false)
		{
			var fieldval_box = $el.parent().closest("li.fcfieldval_container");
			fieldval_box.find(".toggle_group_down").trigger('click');
			
			var fieldgroup_box = fieldval_box.parent().parent();
			if (fieldgroup_box.is(":hidden")) fieldgroup_box.prev().find('.show_vals_btn').trigger('click');
		}
		
 		// Get the label
 	 	var $label = $el.data('label');
 	 	if ($label === undefined) {
 	 		$label = findLabel($el);
 	 	}
		
		// Set the element and its label (if exists) invalid state
		if (state === false)
		{
			// Add INVALID to ELEMENT (but not to checkboxes/radio if their group label was found)
			if ($el.attr('type') == 'checkbox' || $el.attr('type') == 'radio') {
				if (!$label) $el.addClass('invalid').attr('aria-invalid', 'true');
			}
			else {
				$el.addClass('invalid').attr('aria-invalid', 'true');
			}
			
			// Add INVALID to LABEL, (Mark / the label to indicate validation error for current form field / fieldset)
			if ($label) {
				$label.addClass('invalid');
			}
		}
		else
		{
			// Remove INVALID from ELEMENT
			$el.removeClass('invalid').attr('aria-invalid', 'false');
			
			// Remove INVALID from LABEL, (Unmarkup / clear CSS style to indicate no validation error for current form field / fieldset)
			if ($label) {
				$label.removeClass('invalid');
			}
		}
	},
	
	isIE = function() {
		var userAgent = navigator.userAgent.toLowerCase();
		return (userAgent.indexOf('msie') != -1) ? parseInt(userAgent.split('msie')[1]) : false;
	};
	
	
	// Initialize handlers and attach validation to form
	initialize();

	// Public API methods an public properties of the class
	return {
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


function flexi_submit(task, btn_box, msg_box) {
	Joomla.submitbutton(task);
	if (fcform_isValid) {
		if (typeof btn_box !== 'undefined') {
			//alert('hide submit btns');
			jQuery('#'+btn_box).hide();
		}
		if (typeof msg_box !== 'undefined') {
			//alert('show submit msg');
			jQuery('#'+msg_box).show();
		}
	}
}
