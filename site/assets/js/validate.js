/**
 * @copyright	Copyright (C) 2005 - 2012 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

var fcform_isValid = false;
var tab_focused;
var max_cat_assign_fc = 0;
var existing_cats_fc  = [];
var max_cat_overlimit_msg_fc = 'Too many categories selected. You are allowed a maximum number of ';
var fcflabels = null;
var fcflabels_errcnt = null;  // Error counter for multi-value fields
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
var JFormValidator = new Class({
	initialize: function()
	{
		// Joomla form validator should be loaded before this script, to avoid potential conflicts
		// we prevent Joomla form validation JS script to create 2nd validation object
		if (JFormValidator_fc) return JFormValidator_fc;  //
		//alert('Initializing FLEXIcontent form validator');
		
		// Initialize variables
		this.handlers	= Object();
		this.custom		= Object();

		// Default handlers
		this.setHandler('username', function (value) {
			regex = new RegExp("[\<|\>|\"|\'|\%|\;|\(|\)|\&]", "i");
			return !regex.test(value);
		});

		this.setHandler('password', function (value) {
			regex=/^\S[\S ]{2,98}\S$/;
			return regex.test(value);
		});

		this.setHandler('numeric', function (value) {
			regex=/^(\d|-)?(\d|,)*\.?\d*$/;
			return regex.test(value);
		});

		this.setHandler('email', function (value) {
			regex=/^[a-zA-Z0-9._-]+(\+[a-zA-Z0-9._-]+)*@([a-zA-Z0-9.-]+\.)+[a-zA-Z0-9.-]{2,4}$/;
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
			
			if(MooTools.version>="1.2.4") {
				//var values = $(element_id).getSelected();  // does not work in old template form overrides with no id parameter
				var values = $$(document.getElementsByName(field_name))[0].getSelected();
				values = values.map( function(g) { return g.get('value'); } );
				
				var value_catid = $$(document.getElementsByName(field_name_catid))[0].getSelected();
				value_catid = value_catid.map( function(g) { return g.get('value'); } );
				value_catid = value_catid[0];
			} else {
				//values = $(element_id).getValue();  // does not work in old template form overrides with no id parameter
				var values = $$(document.getElementsByName(field_name))[0].getValue();
				//  ** Alternative code **
				//var values = $(element_id).getChildren().filter( function(g) { return g.selected; } );
				//values = values.map( function(g) { return g.getProperty('value'); } );
				
				var value_catid = $$(document.getElementsByName(field_name_catid))[0].getValue();
			}
			
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
					alert(max_cat_overlimit_msg_fc+max_cat_assign_fc);
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
				
				min_values = el.getAttribute("min_values");
				min_values = min_values ? parseInt( min_values, 10 ) : 0;
				
				max_values = el.getAttribute("max_values");
				max_values = max_values ? parseInt( max_values, 10 ) : 0;
				
				exact_values = el.getAttribute("exact_values");
				exact_values = exact_values ? parseInt( exact_values, 10 ) : 0;
				
				js_popup_err = el.getAttribute("js_popup_err");
				js_popup_err = js_popup_err ? parseInt( js_popup_err, 10 ) : 0;
				
				// Check maximum number of selected options
				if ( min_values && count < min_values) {
					if (el.labelref && js_popup_err) alert('Number of values for field --'+el.labelref.innerHTML.replace(/^\s+|\s+$/g,'')+'-- is '+count+',\n which is less than minimum allowed: '+min_values);
					return false;
				}
				if ( max_values && count > max_values) {
					if (el.labelref && js_popup_err) alert('Number of values for field --'+el.labelref.innerHTML.replace(/^\s+|\s+$/g,'')+'-- is '+count+',\n which is more than maximum allowed: '+max_values);
					return false;
				}
				if ( exact_values && count != exact_values) {
					if (el.labelref && js_popup_err) alert('Number of values for field --'+el.labelref.innerHTML.replace(/^\s+|\s+$/g,'')+'-- is '+count+',\n but it must be exactly '+exact_values);
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
				
				min_values = el.getAttribute("min_values");
				min_values = min_values ? parseInt( min_values, 10 ) : 0;
				
				max_values = el.getAttribute("max_values");
				max_values = max_values ? parseInt( max_values, 10 ) : 0;
				
				exact_values = el.getAttribute("exact_values");
				exact_values = exact_values ? parseInt( exact_values, 10 ) : 0;
				
				js_popup_err = el.getAttribute("js_popup_err");
				js_popup_err = js_popup_err ? parseInt( js_popup_err, 10 ) : 0;
				
				// Check maximum number of selected options
				if ( min_values && count < min_values) {
					if (el.labelref && js_popup_err && fcflabels_errcnt[el.labelfor]==0) alert('Number of values for field --'+el.labelref.innerHTML.replace(/^\s+|\s+$/g,'')+'-- is '+count+',\n which is less than minimum allowed: '+min_values);
					return false;
				}
				if ( max_values && count > max_values) {
					if (el.labelref && js_popup_err && fcflabels_errcnt[el.labelfor]==0) alert('Number of values for field --'+el.labelref.innerHTML.replace(/^\s+|\s+$/g,'')+'-- is '+count+',\n which is more than maximum allowed: '+max_values);
					return false;
				}
				if ( exact_values && count != exact_values) {
					if (el.labelref && js_popup_err && fcflabels_errcnt[el.labelfor]==0) alert('Number of values for field --'+el.labelref.innerHTML.replace(/^\s+|\s+$/g,'')+'-- is '+count+',\n but it must be exactly '+exact_values);
					return false;
				}
				return true;
			}
		});
	},

	setHandler: function(name, fn, en)
	{
		en = (en == '') ? true : en;
		this.handlers[name] = { enabled: en, exec: fn };
	},

	attachToForm: function(form)
	{
		// Iterate through the form object and attach the validate method to all input fields.
		jQuery(form).find('input,textarea,select,button').each(function(){
			el = jQuery(this);
			if ( el.hasClass('required') || el.attr('aria-required')=='true' ) {
				el.attr('required', 'required');
				el.attr('aria-required', 'true');
			}
			var tag_name = el.prop("tagName").toLowerCase();
			var validate_flag = (tag_name == 'input' || tag_name == 'button') && el.prop('type') == 'submit';
			if (validate_flag) {
				if (el.hasClass('validate')) {
					el.onclick = function(){return document.formvalidator.isValid(this.form);};
				}
			} else {
				el.on('blur', function(){return document.formvalidator.validate(this);});
			}
		});
	},

	validate: function(el)
	{
		if(MooTools.version>="1.2.4") {
			el = document.id(el);
		} else {
			document.id = $;
			el = document.id(el);
		}
		jqEL = jQuery(el);
		el_value = jqEL.val();
		el_name  = jqEL.attr('name');
		
		// (try to) Find the label for the given form element, trying various indexes for our label array
		var el_id = jqEL.attr('id');
		var el_grpid = jqEL.attr('data-element-grpid');  // prefer this for radio/checkbox or other fields, if it is set
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
			}
		} else if (!el.labelref) {
			el.labelref = null;
			el.labelfor = null;
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
				//if (el.labelfor) window.console.log('INVALID with labelfor: ' + el.labelfor +': ' + fcflabels_errcnt[el.labelfor]);
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
				this.handleResponse(false, el);
				return false;
			}
		} else if( !(jqEL.attr('type') == "radio" || jqEL.attr('type') == "checkbox") ){
			// Individual radio & checkbox can have blank value, providing one element in group is set
			if ( typeof fcpass_element[el_id] != 'undefined' ) {
				// Execute the validation handler and return result
				if (this.handlers[handler].exec(el) != true) {
					this.handleResponse(false, el);
					return false;
				}
			} else if ((handler) && (handler != 'none') && (this.handlers[handler]) && el_value) {
				// Execute the validation handler and return result
				if (this.handlers[handler].exec(el_value) != true) {
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
	},

	isValid: function(form)
	{
		var valid = true;
		tab_focused = false; // global variable defined above, we use this to focus the first tab that contains required field

		// Validate form fields
		var elements = jQuery(form).find('fieldset').toArray().concat(Array.from(form.elements));
		
		for (var i=0;i < elements.length; i++) {
			if (this.validate(elements[i]) == false) {
				tab_focused = true;
				valid = false;
			}
		}

		// Run custom form validators if present
		new Hash(this.custom).each(function(validator){
			if (validator.exec() != true) {
				valid = false;
			}
		});
		
		var recaptcha = jQuery('#recaptcha_response_field');
		var recaptcha_lbl = jQuery('#recaptcha_response_field-lbl');
		if ( recaptcha.length ) {
			if ( recaptcha.val() == '' ) {
				recaptcha.addClass('invalid');
				recaptcha.attr('aria-invalid', 'true');
				if (recaptcha_lbl.length) {
					recaptcha_lbl.addClass('invalid');
					recaptcha_lbl.attr('aria-invalid', 'true');
				}
				valid = false;
			} else {
				recaptcha.removeClass('invalid');
				recaptcha.attr('aria-invalid', 'false');
				if (recaptcha_lbl.length) {
					recaptcha_lbl.removeClass('invalid');
					recaptcha_lbl.attr('aria-invalid', 'false');
				}
			}
		}
		
		fcform_isValid = valid;
		return valid;
	},

	handleResponse: function(state, el)
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
				var tabno = (tabid.search(/grpmarker_tabset_([0-9]+)_tab_([0-9]+)/) != -1) ? tabid.match(/grpmarker_tabset_([0-9]+)_tab_([0-9]+)/)[2] :  -1;
				if ( tabno==-1 ) {
					break;
				}
				fctabber[tabset.attr('id')].tabShow(tabno);
				
				tab = jQuery(tab).parent().closest("div.tabbertab");
				tabset = jQuery(tabset).parent().closest("div.tabberlive");
			}
			
			while(1) {
				if (tabset.length==0 || tab.length==0) break;
				
				var tabsetid = tabset.attr('id');
				var tabid = tab.attr('id');
				var tabno = (tabid.search(/fcform_tabset_([0-9]+)_tab_([0-9]+)/) != -1) ? tabid.match(/fcform_tabset_([0-9]+)_tab_([0-9]+)/)[2] :  -1;
				if ( tabno==-1 ) {
					break;
				}
				fctabber[tabset.attr('id')].tabShow(tabno);
				
				tab = jQuery(tab).parent().closest("div.tabbertab");
				tabset = jQuery(tabset).parent().closest("div.tabberlive");
			}
		}

		// Set the element and its label (if exists) invalid state
		if (state == false) {
			var isInvalid = jqEL.hasClass('invalid') || jqEL.attr('aria-invalid')=='true';
			jqEL.addClass('invalid').attr('aria-invalid', 'true');
			if (el.labelref) {
				var labelref = jQuery(el.labelref);
				if (!isInvalid) fcflabels_errcnt[el.labelfor]++; // Increment error count for multi-value field
				//window.console.log(el.labelfor +': ' + fcflabels_errcnt[el.labelfor]);
				// Mark /  the label to indicate validation error for current form field / fieldset
				labelref.addClass('invalid').attr('aria-invalid', 'true');
			}
		} else {
			var isInvalid = jqEL.hasClass('invalid') || jqEL.attr('aria-invalid')=='true';
			jqEL.removeClass('invalid').attr('aria-invalid', 'false');
			if (el.labelref) {
				var labelref = jQuery(el.labelref);
				if (isInvalid) fcflabels_errcnt[el.labelfor]--; // Decrement error count for multi-value field
				if (fcflabels_errcnt[el.labelfor] == 0) {
					// Unmarkup / clear CSS style to indicate no validation error for current form field / fieldset
					labelref.removeClass('invalid');
					labelref.attr('aria-invalid', 'false');
				}
			}
		}
	}
});

//alert('Loading FLEXIcontent form validator');
JFormValidator_fc = new JFormValidator();

document.formvalidator = null;
jQuery(document).ready(function() {
	document.formvalidator = JFormValidator_fc;
	jQuery('form.form-validate').each(function(){
		document.formvalidator.attachToForm($(this));
	});
	
	// Executed only once to retrieve and hash all label via their for property
	if ( !fcflabels )
	{
		fcflabels = new Object;
		fcflabels_errcnt = new Object;  // error counter for multi-value fields
		jQuery('label, span.label-fcouter > span').each( function(g) {
			g = jQuery(this);
			label_for = g.attr('for_bck');
			if ( !label_for ) label_for = g.attr('for');
			if ( label_for )  {
				fcflabels[ label_for ] = this;
				fcflabels_errcnt[ label_for ] = 0;
			}
		} );
		//var fcflabels_size = Object.size(fcflabels);  alert(fcflabels_size);
	}
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
