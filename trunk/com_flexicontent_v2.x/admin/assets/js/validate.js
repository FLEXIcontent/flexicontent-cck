/**
 * @copyright	Copyright (C) 2005 - 2012 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

Object.append(Browser.Features, {
	inputemail: (function() {
		var i = document.createElement("input");
		i.setAttribute("type", "email");
		return i.type !== "text";
	})()
});

var tab_focused;

/**
 * Unobtrusive Form Validation library
 *
 * Inspired by: Chris Campbell <www.particletree.com>
 *
 * @package		Joomla.Framework
 * @subpackage	Forms
 * @since		1.5
 */
var JFormValidator = new Class({
	initialize: function()
	{
		// Initialize variables
		this.handlers	= Object();
		this.custom		= Object();

		// Default handlers
		this.setHandler('username',
			function (value) {
				regex = new RegExp("[\<|\>|\"|\'|\%|\;|\(|\)|\&]", "i");
				return !regex.test(value);
			}
		);

		this.setHandler('password',
			function (value) {
				regex=/^\S[\S ]{2,98}\S$/;
				return regex.test(value);
			}
		);

		this.setHandler('numeric',
			function (value) {
				regex=/^(\d|-)?(\d|,)*\.?\d*$/;
				return regex.test(value);
			}
		);

		this.setHandler('email',
			function (value) {
				regex=/^[a-zA-Z0-9._-]+(\+[a-zA-Z0-9._-]+)*@([a-zA-Z0-9.-]+\.)+[a-zA-Z0-9.-]{2,4}$/;
				return regex.test(value);
			}
		);

		this.setHandler('fieldname',
			function (value) {
				regex=/^[a-zA-Z0-9_-]+$/;
				return regex.test(value);
			}
		);

		this.setHandler('radio',
			function (par) {
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
			}
		);

		this.setHandler('checkbox',
			function (par) {
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
			}
		);
		
		this.setHandler('checkbox2',
			function (par) {
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
			}
		);
		
		this.setHandler('checkbox3',
			function (par) {
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
			}
		);

		this.setHandler('selmul',
			function (par) {
				var nl, i;
				if (par.parentNode == null) {
					return true;
				} else {
					var options = par.parentNode.getElementsByTagName('option');
			
					var count = 0;
					for (i=0, nl = options; i<nl.length; i++) {
						if (nl[i].selected) count++;
					}
			
					// Check maximum number of selected options
					if(count <= max_sel) return true;
					return false;
				}
			}
		);

		// Attach to forms with class 'form-validate'
		var forms = $$('form.form-validate');
		forms.each(function(form){ this.attachToForm(form); }, this);
	},

	setHandler: function(name, fn, en)
	{
		en = (en == '') ? true : en;
		this.handlers[name] = { enabled: en, exec: fn };
	},

	attachToForm: function(form)
	{
		// Iterate through the form object and attach the validate method to all input fields.
		form.getElements('input,textarea,select,button').each(function(el){
			el = document.id(el);
			if (el.hasClass('required')) {
				el.set('aria-required', 'true');
				el.set('required', 'required');
			}
			if ((el.get('tag') == 'input' || el.get('tag') == 'button') && el.get('type') == 'submit') {
				if (el.hasClass('validate')) {
					el.onclick = function(){return document.formvalidator.isValid(this.form);};
				}
			} else {
				el.addEvent('blur', function(){return document.formvalidator.validate(this);});
				if (el.hasClass('validate-email') && Browser.Features.inputemail) {
					el.type = 'email';
				}
			}
		});
	},

	validate: function(el)
	{
		if(MooTools.version>="1.2.4") {
		  el = document.id(el);
			el_value = el.get('value');
		} else {
			document.id = $;
		  el = document.id(el);
			el.get = el.getProperty;
			el.set = el.setProperty;
			el_value = el.getValue();
		}
		
		// Ignore the element if its currently disabled, because are not submitted for the http-request. For those case return always true.
		if(el.get('disabled')) {
			this.handleResponse(true, el);
			return true;
		}

		// If the field is required make sure it has a value
		if (el.hasClass('required')) {
			if(el.get('type') == 'radio' || el.get('type') == 'checkbox') {
				// Checked specially bellow
			}
			else if (!el_value || el_value == false) {
				this.handleResponse(false, el);
				return false;
			}
		}

		// Only validate the field if the validate class is set
		var handler = (el.className && el.className.search(/validate-([a-zA-Z0-9\_\-]+)/) != -1) ? el.className.match(/validate-([a-zA-Z0-9\_\-]+)/)[1] : "";
		if (handler == '') {
			this.handleResponse(true, el);
			return true;
		}

		// Check the additional validation types
	  // Individual radio & checkbox can have blank value, providing one element in group is set
	  if(!(el.getProperty('type') == "radio" || el.getProperty('type') == "checkbox")){
			if ((handler) && (handler != 'none') && (this.handlers[handler]) && el_value) {
				// Execute the validation handler and return result
				if (this.handlers[handler].exec(el_value) != true) {
					this.handleResponse(false, el);
					return false;
				}
			}
	  } else {
	     if ((handler) && (handler != 'none') && (this.handlers[handler])) {
	        if(el.getProperty('type') == "radio" || el.getProperty('type') == "checkbox"){
	          if ($(el).hasClass('required')) {
	               if (this.handlers[handler].exec(el.parentNode) != true) {
		               this.handleResponse(false, el);
		               return false;
	               }
	           }
	        }
	     }
	  }
	
		// Return validation state
		this.handleResponse(true, el);
		return true;
	},

	isValid: function(form)
	{
		var valid = true;
		tab_focused = false; // global variable defined above, we use this to focus the first tab that contains required field

		// Validate form fields
		var elements = form.getElements('fieldset').concat(Array.from(form.elements));
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

		return valid;
	},

	handleResponse: function(state, el)
	{
		// Extra code for auto-focusing the tab that contains the first field to fail the validation
		if (typeof jQuery != 'undefined' && state === false && tab_focused === false) {
			var tabcont = jQuery(el).closest("div.tabbertab");
			if (tabcont.length != 0) {
				var tabcontid = tabcont.attr('id');
				var tabno = (tabcontid.search(/grpmarker([0-9]+)/) != -1) ? tabcontid.match(/grpmarker([0-9]+)/)[1] : "";
				fctabber.tabShow(tabno);
			}
		}
		
		// Find the label object for the given field if it exists
		if (!(el.labelref)) {
			var labels = $$('label');
			labels.each(function(label){
				if (label.get('for') == el.get('id') ||
						label.get('for') == el.get('name') ||
						label.get('for')+'[]' == el.get('name')
				) {
					el.labelref = label;
				}
			});
		}

		// Set the element and its label (if exists) invalid state
		if (state == false) {
			el.addClass('invalid');
			el.set('aria-invalid', 'true');
			if (el.labelref) {
				document.id(el.labelref).addClass('invalid');
				document.id(el.labelref).set('aria-invalid', 'true');
			}
		} else {
			el.removeClass('invalid');
			el.set('aria-invalid', 'false');
			if (el.labelref) {
				document.id(el.labelref).removeClass('invalid');
				document.id(el.labelref).set('aria-invalid', 'false');
			}
		}
	}
});

document.formvalidator = null;
window.addEvent('domready', function(){
	document.formvalidator = new JFormValidator();
});