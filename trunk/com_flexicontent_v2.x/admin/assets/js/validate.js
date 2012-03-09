/**
* @version		$Id: validate.js 11 2009-06-14 10:25:23Z vistamedia $
* @package		Joomla
* @copyright	Copyright (C) 2005 - 2008 Open Source Matters. All rights reserved.
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL, see LICENSE.php
* Joomla! is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/

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
				regex=/^[a-zA-Z0-9._-]+@([a-zA-Z0-9.-]+\.)+[a-zA-Z0-9.-]{2,4}$/;
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
		$A(form.elements).each(function(el){
			el = $(el);
			if ((el.get('tag') == 'input' || el.get('tag') == 'button') && el.getProperty('type') == 'submit') {
				if (el.hasClass('validate')) {
					el.onclick = function(){return document.formvalidator.isValid(this.form);};
				}
			} else {
				el.addEvent('blur', function(){return document.formvalidator.validate(this);});
			}
		});
	},

	validate: function(el)
	{
	  // Declare the variable if not already / IE8 validation fix by ggppdk ;)
	  el = $(el);
	  // If the field is required make sure it has a value
	  if(!(el.getProperty('type') == "radio" || el.getProperty('type') == "checkbox")){
	      if ($(el).hasClass('required')) {
	         if (!($(el).get('value')) || ($(el).get('value') == false)) {
	            this.handleResponse(false, el);
	            return false;
	         }
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
	     if ((handler) && (handler != 'none') && (this.handlers[handler]) && $(el).get('value')) {
	        // Execute the validation handler and return result
	        if (this.handlers[handler].exec($(el).get('value')) != true) {
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

		// Validate form fields
		for (var i=0;i < form.elements.length; i++) {
			if (this.validate(form.elements[i]) == false) {
				//alert(form.elements[i].name);
				valid = false;
			}
		}

		// Run custom form validators if present
		$A(this.custom).each(function(validator){
			/*if (validator.exec() != true) {
				valid = false;
			}*/
		});

		return valid;
	},

	handleResponse: function(state, el)
	{
		// Find the label object for the given field if it exists
		if (!(el.labelref)) {
			var labels = $$('label');
			labels.each(function(label){
				if (label.getProperty('for') == el.getProperty('name') || label.getProperty('for') == el.getProperty('id') || label.getProperty('for')+'[]' == el.getProperty('name')) {
					el.labelref = label;
				}
			});
		}

		// Set the element and its label (if exists) invalid state
		if (state == false) {
			el.addClass('invalid');
			if (el.labelref) {
				$(el.labelref).addClass('invalid');
			}
		} else {
			el.removeClass('invalid');
			if (el.labelref) {
				$(el.labelref).removeClass('invalid');
			}
		}
	}
});

document.formvalidator = null;
window.addEvent('domready', function(){
	document.formvalidator = new JFormValidator();
});