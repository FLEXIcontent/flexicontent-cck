/**
 * @version 1.5 stable $Id: admin.js 183 2009-11-18 10:30:48Z vistamedia $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */


/**
* Check the form is valid and if true, submit it (overload the joomla.javascript.js one)
*/
function fc_submit_form(form, task, validate)
{
	var $form = jQuery(form);
	
	// *************
	// Do task tests
	// *************
	
	// Check is AJAX submit task
	var match_apply_ajax = new RegExp(/(.|^)apply_ajax$/);  // Do AJAX submit if 'apply_ajax' task
	var match_apply      = new RegExp(/(.|^)apply$/);  // Do AJAX submit if 'apply' task and form has ATTRIBUTE: 'data-fc_doajax_submit'
	
	var doajax_allowed = true;
	var doajax_submit  = match_apply_ajax.test(task)  ||  (match_apply.test(task) && $form.attr('data-fc_doajax_submit'));
	$form.data('doajax_submit', doajax_submit);  // Pass FLAG to submit handler
	
	// Check is cancel task
	var match_cancel = new RegExp(/(.*.|^)cancel$/);
	var isCancel = match_cancel.test(task);
	
	
	// ***********************
	// Serialize submit needed
	// ***********************
	
	var doserialized_allowed = $form.attr('data-fc_doserialized_submit');
	var doserialized_submit  = false;
	
	if (doserialized_allowed)
	{
		// Get form's active elements
		var form_fields_active = $form.find('textarea:enabled, select:enabled, input[type="radio"]:enabled:checked, input[type="checkbox"]:enabled:checked, input:not(:button):not(:radio):not(:checkbox):enabled');
		
		var max_input_vars = typeof fc_max_input_vars !== 'undefined' ? fc_max_input_vars : 1000;
		
		// Do serialization if estimated form variable count higher than max_input_vars
		doserialized_submit = form_fields_active.length > max_input_vars;
		
		var sdata_id = 'fcdata_serialized';
		var sdata = jQuery('#'+sdata_id);  // Holds serialized form data
		var sdata_count = jQuery('#'+sdata_id+'_count');  // Holds serialized form data counter
	}
	$form.data('doserialized_submit', doserialized_submit);  // Pass FLAG to submit handler
	
	
	// *************************
	// SERIALIZED SUBMIT HANDLER
	// *************************
	
	if (doserialized_allowed && !Joomla.fc_serialized_submit_handler_added)
	{
		Joomla.fc_serialized_submit_handler_added = true;
			
		$form.on('submit',function(e)
		{
			if ( !$form.data('doserialized_submit') ) return true;
			$form.data('doserialized_submit', 0);  // Clear FLAG
			
			// Add form field that will hold the serialized form data, if this has not already been added
			if (!sdata.length)
			{
				$form.append('<input type="hidden" name="'+sdata_id+'" id="'+sdata_id+'" value="" />');
				sdata = jQuery('#'+sdata_id);
			}
			
			// Add form field that will hold the serialized form data counter, if this has not already been added
			if (!sdata_count.length)
			{
				$form.append('<input type="hidden" name="'+sdata_id+'_count" id="'+sdata_id+'_count" value="" />');
				sdata_count = jQuery('#'+sdata_id+'_count');
			}
			sdata_count.val( form_fields_active.length );
			sdata.val( JSON.stringify( $form.serializeArray() ) );  //sdata.val( $form.serialize() );
				
			// Disable the form fields that were serialized, so that they will not be submitted (form fields of type 'file' are not included, and will be submitted normally)
			form_fields_active.each(function()
			{
				var type = this.type || this.tagName.toLowerCase();
				if (type=='file') return;
				var el = jQuery(this);
				el.attr('data-fcs_disabled', el.attr('disabled'));
				el.attr('disabled', true);
			});
			
			sdata.attr('disabled', false);
			$form.data('form_was_serialized', 1);  // Pass FLAG to AJAX submit handler
		});
	}
	
	
	// *************************
	// AJAXIFIED SUBMIT HANDLER
	// *************************
	
	if (doajax_allowed && !Joomla.fc_ajaxified_submit_handler_added)
	{
		Joomla.fc_ajaxified_submit_handler_added = true;
			
		$form.on('submit',function(e)
		{
			// This will not be reached if
			// - either HTML5 validation fails,
			// - or any previous handler calls preventDefault()
			
			if ( !$form.data('doajax_submit') ) return true;
			$form.data('doajax_submit', 0);  // Clear FLAG
			
			e.preventDefault();
			
			$form.after('<span id="fc_doajax_loading"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center" /> ... Saving</span>');
			$form.hide();
			$form.append('<input type="hidden" name="fc_doajax_submit" id="fc_doajax_submit" value="1" />');
			jQuery.ajax({
				type: form.method,
				url: form.action,
				data: $form.serialize(),
				success: function (data) {
					jQuery('#fc_doajax_loading').remove();
					jQuery('#fc_doajax_submit').remove();
					jQuery('#fc_filter_form_blocker').remove();
					$form.show();
					jQuery('#system-message-container').html(data);
					if ( $form.data('form_was_serialized') )
					{
						$form.data('form_was_serialized', 0);  // Clear FLAG
						sdata.val( '' );
						form_fields_active.each(function() {
							var el = jQuery(this);
							el.attr('data-fcs_disabled')  ?  el.attr('disabled', el.attr('data-fcs_disabled'))  :  el.removeAttr('disabled');
							el.removeAttr('data-fcs_disabled');
						});
					}
				}
			});
			return false;  // Indicate that nothing more should be done ... in case anyone is listening after us
		});
	}
	
	
	// *******************
	// Submit progress bar
	// *******************
	
	if (!Joomla.fc_progressbar_submit_handler_success)
	{
		Joomla.fc_progressbar_submit_handler_success = true;
			
		$form.on('submit',function(e)
		{
			if ( !isCancel )
			{
				jQuery('body').prepend(
				 	'<span id="fc_filter_form_blocker">' +
				    '<span class="fc_blocker_opacity"></span>' +
				    '<span class="fc_blocker_content">' +
				    	Joomla.JText._('FLEXI_FORM_IS_BEING_SUBMITTED') +
				    	'<div class="fc_blocker_bar"><div></div></div>' +
				    '</span>' +
				  '</span>');
				var fc_filter_form_blocker = jQuery("#fc_filter_form_blocker");
				if (fc_filter_form_blocker) {
					fc_filter_form_blocker.css("display", "block");
					fc_admin_progress(95, jQuery('#fc_filter_form_blocker .fc_blocker_bar'));
				}
			}
		});
	}
	
	// Submit the form, triggering submit handlers properly: Create an input type="submit" and click it
	var button = document.createElement('input');
	button.style.display = 'none';
	button.type = 'submit';
	form.appendChild(button).click();
	
	// If "submit" was prevented (e.g. due HTML5 validation or due to AJAX submit), make sure we don't get a build up of submit buttons
	form.removeChild(button);
}


function fc_admin_progress(percent, element) {
	var progressBarWidth = percent * element.width() / 100;
	element.find('div').animate({ width: progressBarWidth }, 5000).html("");
}


// Overload Joomla submit function
Joomla.submitform = function(task, form, validate)
{
	if (!form) {
		form = document.getElementById('adminForm');
	}
	var form_task = task ? task : (typeof form.task !== 'undefinded' && typeof form.task.value !== 'undefinded'  ?  form.task.value  :  '');
	
	// Do form validation if button task is not 'cancel'
	var match_cancel = new RegExp(/(.*.|^)cancel$/);
	var isCancel = match_cancel.test(form_task);
	if ( document.formvalidator && !isCancel )
	{
		var vTimeStart = new Date().getTime();
		var isValid = document.formvalidator.isValid(form);
		var vTimeDiff = (new Date())  - vTimeStart;
		//alert('Form validation time: ' + vTimeDiff + ' isValid: ' + isValid);
		//window.console.log( 'isValid() time: ' + vTimeDiff );
		
		if (!isValid)  // If form is invalid, then focus the first invalid element, or focus the all errors container
		{
			var invalid = jQuery('.invalid').first();  // Get single element so that hidden check will work
			if (invalid.is(':hidden')) {
				invalid = jQuery('#system-message-container');
			}
			
			if (invalid.length) {
				var pos = invalid.offset().top - 80;
				jQuery('html, body').animate({
					scrollTop: pos
				}, 1000);
				invalid[0].focus();
			}
			return false;
		}
	}
	
	// Modify form task after JS validation has run
	if (task) {
		form.task.value = task;
	}
	
	// Disable HTML5 validation , currently we will only do not need it
	// but main reason is to prevent validateForm() of html5fallback.js from running !!
	form.removeAttribute('novalidate') ;
	
	// HTML5 VALIDATION: Do according to form original code
	/*if (typeof validate === 'undefined' || validate === null);

	// HTML5 VALIDATION: Force OFF / ON
	else !validate ?
			form.setAttribute('novalidate', 'novalidate') :
			form.removeAttribute('novalidate') ;*/
	
	// HTML5 VALIDATION: Suppress if "*.cancel" or "cancel" tasks
	if ( isCancel )  form.setAttribute('novalidate', 'novalidate');
	
	fc_submit_form(form, task); // Submit the form
}
