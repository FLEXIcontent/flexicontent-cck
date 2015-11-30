/**
 * @copyright	Copyright (C) 2005 - 2012 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// TODO: check which variables do not need to be global
var fcform_isValid = false;
var tab_focused;
var max_cat_assign_fc = 0;
var existing_cats_fc  = [];
var fcflabels = null;
var popup_msg_done = null;  // Popup-once message for multi-value fields
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
	this.initialize = function()
	{
		// Joomla form validator should be loaded before this script, to avoid potential conflicts
		// we prevent Joomla form validation JS script to create 2nd validation object
		if (JFormValidator_fc) return JFormValidator_fc;  //
		//alert('Initializing FLEXIcontent form validator');
		
 	 	inputEmail = (function() {
 	 	 	var input = document.createElement("input");
 	 	 	input.setAttribute("type", "email");
 	 	 	return input.type !== "text";
 	 	})();
		
		// Initialize variables
		this.handlers	= Object();
		this.custom		= Object();

		// Default handlers
		this.setHandler('username', function (value) {
 	 	 	var regex = new RegExp("[\<|\>|\"|\'|\%|\;|\(|\)|\&]", "i");
			return !regex.test(value);
		});

		this.setHandler('password', function (value) {
			var regex = /^\S[\S ]{2,98}\S$/;
			return regex.test(value);
		});

		this.setHandler('numeric', function (value) {
 	 		var regex = /^(\d|-)?(\d|,)*\.?\d*$/;
			return regex.test(value);
		});

		this.setHandler('email', function (value) {
			value = punycode.toASCII(value);
			var regex = /^[a-zA-Z0-9.!#$%&’*+\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
			return regex.test(value);
		});

		this.setHandler('fieldname', function (value) {
			regex=/^[a-zA-Z0-9_-]+$/;
			return regex.test(value);
		});

		this.setHandler('radio', function (par) {
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

		this.setHandler('checkbox', function (par) {
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

		this.setHandler('checkbox2', function (par) {
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

		this.setHandler('checkbox3', function (par) {
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

		this.setHandler('catid', function (el) {
			// Check for value if primary category is set
			jqEL = jQuery(el);
			var value = jqEL.val();
			if (value) return true;
			
			// Retrieve selected values for secondary categories
			var element_id = 'jform_cid';
			var field_name = 'jform[cid][]';
			
			// If exactly one secondary category was selected then set it as primary
			var values = jQuery(document.getElementsByName(field_name)).val();
			if (values && values.length == 1) {
				jqEL.val(values[0]);
				if (jqEL.val()) {  // main category tree maybe different than secondary, so check value exists
					if (jqEL.hasClass('use_select2_lib')) {
						jqEL.trigger('change');
					}
					return true;
				}
			}
			return false;
		});

		this.setHandler('fccats', function (el) {
			//var value = el.get('value');
			
			// Retrieve selected values for secondary categories
			var element_id = 'jform_cid';
			var field_name = 'jform[cid][]';
			var field_name_catid = 'jform[catid]';
			
			// Get first secondary category selected to use as maincat
			var values = jQuery('#'+element_id).val();
			var value_catid = values[0];
			
			//window.console.log(values);
			//window.console.log(existing_cats_fc);
			
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

		this.setHandler('sellimitations', function (el) {
			var nl, i;
			if (el == null) {
				return true;
			} else {
				var options = el.getElementsByTagName('option');
				
				var count = 0;
				for (i=0, nl = options; i<nl.length; i++) {
					if (nl[i].selected) count++;
				}
				
				min_values = el.getAttribute("data-min_values");
				min_values = min_values ? parseInt( min_values, 10 ) : 0;
				
				max_values = el.getAttribute("data-max_values");
				max_values = max_values ? parseInt( max_values, 10 ) : 0;
				
				exact_values = el.getAttribute("data-exact_values");
				exact_values = exact_values ? parseInt( exact_values, 10 ) : 0;
				
				js_popup_err = el.getAttribute("data-js_popup_err");
				js_popup_err = js_popup_err ? parseInt( js_popup_err, 10 ) : 0;
				
				// Check maximum number of selected options
				if ( min_values && count < min_values) {
					if (el.labelref && js_popup_err && !popup_msg_done[el.labelfor]) alert('Number of values for field --'+el.labelref.innerHTML.replace(/^\s+|\s+$/g,'')+'-- is '+count+',\n which is less than minimum allowed: '+min_values);
					popup_msg_done[el.labelfor]=1;
					return false;
				}
				if ( max_values && count > max_values) {
					if (el.labelref && js_popup_err && !popup_msg_done[el.labelfor]) alert('Number of values for field --'+el.labelref.innerHTML.replace(/^\s+|\s+$/g,'')+'-- is '+count+',\n which is more than maximum allowed: '+max_values);
					popup_msg_done[el.labelfor]=1;
					return false;
				}
				if ( exact_values && count != exact_values) {
					if (el.labelref && js_popup_err && !popup_msg_done[el.labelfor]) alert('Number of values for field --'+el.labelref.innerHTML.replace(/^\s+|\s+$/g,'')+'-- is '+count+',\n but it must be exactly '+exact_values);
					popup_msg_done[el.labelfor]=1;
					return false;
				}
				return true;
			}
		});

		this.setHandler('cboxlimitations', function (el) {
			var nl, i;
			if (el.parentNode.parentNode == null) {
				return true;
			} else {
				var options = el.parentNode.parentNode.getElementsByTagName('input');
				
				var count = 0;
				for (i=0, nl = options; i<nl.length; i++) {
					if (nl[i].checked) count++;
				}
				
				min_values = el.getAttribute("data-min_values");
				min_values = min_values ? parseInt( min_values, 10 ) : 0;
				
				max_values = el.getAttribute("data-max_values");
				max_values = max_values ? parseInt( max_values, 10 ) : 0;
				
				exact_values = el.getAttribute("data-exact_values");
				exact_values = exact_values ? parseInt( exact_values, 10 ) : 0;
				
				js_popup_err = el.getAttribute("data-js_popup_err");
				js_popup_err = js_popup_err ? parseInt( js_popup_err, 10 ) : 0;
				
				// Check maximum number of selected options
				if ( min_values && count < min_values) {
					if (el.labelref && js_popup_err && !popup_msg_done[el.labelfor]) alert('Number of values for field --'+el.labelref.innerHTML.replace(/^\s+|\s+$/g,'')+'-- is '+count+',\n which is less than minimum allowed: '+min_values);
					popup_msg_done[el.labelfor]=1;
					return false;
				}
				if ( max_values && count > max_values) {
					if (el.labelref && js_popup_err && !popup_msg_done[el.labelfor]) alert('Number of values for field --'+el.labelref.innerHTML.replace(/^\s+|\s+$/g,'')+'-- is '+count+',\n which is more than maximum allowed: '+max_values);
					popup_msg_done[el.labelfor]=1;
					return false;
				}
				if ( exact_values && count != exact_values) {
					if (el.labelref && js_popup_err && !popup_msg_done[el.labelfor]) alert('Number of values for field --'+el.labelref.innerHTML.replace(/^\s+|\s+$/g,'')+'-- is '+count+',\n but it must be exactly '+exact_values);
					popup_msg_done[el.labelfor]=1;
					return false;
				}
				return true;
			}
		});
	};

	this.setHandler = function(name, fn, en)
	{
		en = (en == '') ? true : en;
		this.handlers[name] = { enabled: en, exec: fn };
	};

	this.attachToForm = function(form)
	{
		// Iterate through the form object and attach the validate method to all input fields.
		jQuery(form).find('input,textarea,select,button').each(function(){
			el = jQuery(this);
			if ( el.hasClass('required') || el.attr('aria-required')=='true' )
			{
				el.attr('required', 'required');
				el.attr('aria-required', 'true');
			}
			
			var tag_type = el.attr('type');
			var tag_name = el.prop("tagName").toLowerCase();
			
			// Styled via JS
			if ( el.hasClass('use_select2_lib') || el.hasClass('use_prettycheckable') )
			{
				el.on('change', function(){ return document.formvalidator.validate(this); });
			}
			
			// Radio / checkbox
			else if ( tag_type == 'radio' || tag_type == 'checkbox' )
			{
				el.on('click', function(){ document.formvalidator.validate(this); return true; });
			}
			
			// Submit button, needs to return true false to avoid form being submited 
			else if ( (tag_name == 'input' || tag_name == 'button') && (tag_type == 'submit' || tag_type === 'image') )
			{
				if (el.hasClass('validate')) {
					el.on('click', function(){
						return document.formvalidator.isValid(this.form);
					});
				}
			}
			
			// text inputs, selects, ... other ?
			else
			{
				el.on('blur', function(){ return document.formvalidator.validate(this); });
 	 	 	 	if (tag_name == 'input' && el.hasClass('validate-email') && inputEmail) {
 	 	 	 		try { el.get(0).type = 'email'; } catch (e) { /*IE8 or less*/}
 	 	 	 	}
			}
			
		});
	};

	this.validate = function(el)
	{
		var jqEL = jQuery(el);
		var el_value = jqEL.val();
		var el_name  = jqEL.attr('name');
		var el_id    = jqEL.attr('id');
		var el_grpid = jqEL.attr('data-element-grpid');  // prefer this for radio/checkbox or other fields, if it is set
		
		if (el_id && el_id.substring(0,11) == 'jform_rules') {
			this.handleResponse(true, el);
			return true;
		}
		
		// (try to) Find the label for the given form element, trying various indexes for our label array
		if ( !el.labelref && (el_id || el_grpid) )
		{
			el.labelfor = null;
			var lblfor = el_grpid ? el_grpid : el_id;
			if ( !el.labelref )  el.labelref = fcflabels[  lblfor  ];  // Try id / grpid directly
			if ( !el.labelref )  el.labelref = fcflabels[  lblfor = lblfor.replace(/_[0-9]+$/, '')  ];  // try removing trailing number (multi-value index)
			if ( !el.labelref )  el.labelref = fcflabels[  lblfor = lblfor.replace(/custom_/, '')  ];  // try removing 'custom_'
			if ( !el.labelref )  el.labelref = fcflabels[  lblfor = 'custom_' + lblfor  ];  // try adding 'custom_'
			if ( el.labelref ) {
				el.labelfor = lblfor; // store HTML tag id of the label, to use in error counter of multi-value fields
			} else {
				var lbl = jQuery('label[for="'+lblfor+'"]');
				if (lbl.length) {
					el.labelref = fcflabels[lblfor] = lbl;
				}
			}
		} else if (!el.labelref) {
			el.labelref = null;
			el.labelfor = null;
		} else if (el_id || el_grpid) {
			var lblfor = el_grpid ? el_grpid : el_id;
			if ( el.labelref != fcflabels[ lblfor ]) {
				var lbl = jQuery('label[for="'+lblfor+'"]');
				if (lbl.length) {
					el.labelref = fcflabels[lblfor] = lbl;
				}
			}
		}
		
		// Ignore the element if its currently disabled, because are not submitted for the http-request. For those case return always true.
		if(jqEL.attr('disabled')) {
			this.handleResponse(true, el);
			return true;
		}

		// BASIC 'required' VALIDATION: check that field has a non-empty value
		if ( jqEL.hasClass('required') || jqEL.attr('aria-required')=='true' ) {
			if(jqEL.attr('type') == 'radio' || jqEL.attr('type') == 'checkbox') {
				// radio/checkbox can be checked only via specific validation handler if this is set
			}
			else if (el_value === null || el_value.length==0) {
				//if (el.labelfor) window.console.log('INVALID with labelfor: ' + el.labelfor +': ' + popup_msg_done[el.labelfor]);
				//else window.console.log('INVALID with element id:' + el_id);
				this.handleResponse(false, el);
				return false;
			}
		}

		// If no more validation is needed:  mark/update element's display as valid and return success
		var handler = (el.className && el.className.search(/validate-([a-zA-Z0-9\_\-]+)/) != -1) ? el.className.match(/validate-([a-zA-Z0-9\_\-]+)/)[1] : "";
		if (handler == '') {
			this.handleResponse(true, el);
			return true;
		}
		
		// ADVANCED method-specific validation for fields 'validation-*' CSS class
		if (handler == "sellimitations" || handler == "cboxlimitations") {
			// Execute the validation handler and return result
			if (this.handlers[handler].exec(el) != true) {
				//if (el.labelfor) window.console.log('INVALID with labelfor: ' + el.labelfor +': ' + popup_msg_done[el.labelfor]);
				//else window.console.log('INVALID with element id:' + el_id);
				this.handleResponse(false, el);
				return false;
			}
		} else if( !(jqEL.attr('type') == "radio" || jqEL.attr('type') == "checkbox") ){
			// Individual radio & checkbox can have blank value, providing one element in group is set
			if ( typeof fcpass_element[el_id] != 'undefined' ) {
				// Execute the validation handler and return result
				if (this.handlers[handler].exec(el) != true) {
					//if (el.labelfor) window.console.log('INVALID with labelfor: ' + el.labelfor +': ' + popup_msg_done[el.labelfor]);
					//else window.console.log('INVALID with element id:' + el_id);
					this.handleResponse(false, el);
					return false;
				}
			} else if ((handler) && (handler != 'none') && (this.handlers[handler]) && el_value) {
				// Execute the validation handler and return result
				if (this.handlers[handler].exec(el_value) != true) {
					//if (el.labelfor) window.console.log('INVALID with labelfor: ' + el.labelfor +': ' + popup_msg_done[el.labelfor]);
					//else window.console.log('INVALID with element id:' + el_id);
					this.handleResponse(false, el);
					return false;
				}
			}
		} else {
			if ((handler) && (handler != 'none') && (this.handlers[handler])) {
				if(jqEL.attr('type') == "radio" || jqEL.attr('type') == "checkbox"){
					if (jqEL.hasClass('required')) {
						// Execute the validation handler and return result
						if (this.handlers[handler].exec(el.parentNode) != true) {
							//if (el.labelfor) window.console.log('INVALID with labelfor: ' + el.labelfor +': ' + popup_msg_done[el.labelfor]);
							//else window.console.log('INVALID with element id:' + el_id);
							this.handleResponse(false, el);
							return false;
						}
					}
				}
			}
		}
	
		// No errors found by method-specific validation handlers:  mark/update element's display as valid and return success
		this.handleResponse(true, el);
		return true;
	};

	this.isValid = function(form)
	{
 		var valid = true;
		tab_focused = false; // global variable defined above, we use this to focus the first tab that contains required field
		
 		var message, error, label, invalid = [];
 		var i, l;
 		
 		// Get fieldset containers of (checkbox/radio) and all form fields
		var fields = jQuery(form).find('fieldset').toArray().concat(Array.from(form.elements));
		
		// Validate form fields
 	 	for (i = 0, l = fields.length; i < l; i++)
 	 	{
			if (this.validate(fields[i]) == false) {
				tab_focused = true;
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
 	 	 		//label = jQuery(invalid[i]).data("label");
 	 	 		label = jQuery(invalid[i].labelref);
 	 	 		
 	 			if (label && typeof added[label.text()] === 'undefined')
				{
 	 	 			error.error.push(message + label.text().replace("*", ""));
				}
 	 	 		added[label.text()] = 1;
 	 	 	}
 	 	 	Joomla.renderMessages(error);
 	 	}
		
		fcform_isValid = valid;
		return valid;
	};

	this.handleResponse = function(state, el)
	{
		// Extra code for auto-focusing the tab that contains the first field to fail the validation
		jqEL = jQuery(el);
		if (state === false && tab_focused === false) {
			var tab = jqEL.parent().closest("div.tabbertab");
			var tabset = jqEL.parent().closest("div.tabberlive");
			
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
		
		// Extra code to open the field group that contains the first field to fail the validation
		if (state === false)
		{
			var fieldval_box = jqEL.parent().closest("li.fcfieldval_container");
			fieldval_box.find(".toggle_group_down").trigger('click');
			
			var fieldgroup_box = fieldval_box.parent().parent();
			if (fieldgroup_box.is(":hidden")) fieldgroup_box.prev().find('.show_vals_btn').trigger('click');
		}
		
		// If it is already invalid
		var isInvalid = jqEL.hasClass('invalid') || jqEL.attr('aria-invalid')=='true';
		
		// Set the element and its label (if exists) invalid state
		if (state == false)
		{
			// Add INVALID to ELEMENT (but not to checkboxes/radio if their group label was found)
			if (jqEL.attr('type') == 'checkbox' || jqEL.attr('type') == 'radio') {
				if (!el.labelref) jqEL.addClass('invalid').attr('aria-invalid', 'true');
			}
			else {
				jqEL.addClass('invalid').attr('aria-invalid', 'true');
			}
			
			// Add INVALID to LABEL, (Mark /  the label to indicate validation error for current form field / fieldset)
			if (el.labelref) {
				var labelref = jQuery(el.labelref);
				labelref.addClass('invalid');
			}
		}
		else
		{
			// Remove INVALID from ELEMENT
			jqEL.removeClass('invalid').attr('aria-invalid', 'false');
			
			// Remove INVALID from LABEL, (Unmarkup / clear CSS style to indicate no validation error for current form field / fieldset)
			if (el.labelref) {
				jQuery(el.labelref).removeClass('invalid');
			}
		}
	};
	
	this.initialize();
};

//alert('Loading FLEXIcontent form validator');
JFormValidator_fc = new JFormValidator();

document.formvalidator = null;
jQuery(document).ready(function() {
	//console.time("timing attachToForm()");
	document.formvalidator = JFormValidator_fc;
	jQuery('form.form-validate').each(function(){
		document.formvalidator.attachToForm(this);
	});
	//console.timeEnd("timing attachToForm()");
	
	// Executed only once to retrieve and hash all label via their for property
	//console.time("timing form labels hash mapping");
	if ( !fcflabels )
	{
		fcflabels = new Object;
		popup_msg_done = new Object;  // Popup-once message for multi-value fields
		var err_cnt = 0;
		jQuery('label, span.label-fcouter > span').each( function(g) {
			g = jQuery(this);
			label_for = g.attr('data-for_bck');
			if ( !label_for ) label_for = g.attr('for_bck');  // compatibility for older form overrides
			if ( !label_for ) label_for = g.attr('for');
			if ( label_for )  {
				fcflabels[ label_for ] = this;
				popup_msg_done[ label_for ] = 0;
			} else {
				//window.console.log( g.append(jQuery('#xxx').clone()).html() );
				err_cnt++;
			}
		} );
		var fcflabels_size = Object.keys(fcflabels).length; 
		//window.console.log('fcflabels_size: ' + fcflabels_size); //alert(fcflabels_size);
		//window.console.log('err_cnt: ' + err_cnt); //alert(err_cnt);
	}
	//console.timeEnd("timing form labels hash mapping");
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
