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
	
	// Check task is 'apply_ajax' / 'apply'
	var match_apply_ajax = new RegExp(/(.|^)apply_ajax$/);  // Do AJAX submit if 'apply_ajax' task
	var match_apply      = new RegExp(/(.|^)apply$/);  // Do AJAX submit if 'apply' task and form has ATTRIBUTE: 'data-fc_force_apply_ajax'
	
	// Check task is 'cancel'
	var match_cancel = new RegExp(/(.*.|^)cancel$/);
	var isCancel = match_cancel.test(task);
	
	
	// *****************
	// AJAX submit FLAGs
	// *****************
	
	var doajax_allowed = true;
	var doajax_submit  = match_apply_ajax.test(task)  ||  (match_apply.test(task) && $form.attr('data-fc_force_apply_ajax'));
	$form.data('doajax_submit', doajax_submit);  // Pass (enable/disable) FLAG to AJAX submit handler
	
	
	// **********************
	// Serialize submit FLAGs
	// **********************
	
	var doserialized_allowed = true;
	var doserialized_submit  = $form.attr('data-fc_doserialized_submit');
	$form.data('doserialized_submit', doserialized_submit);  // Pass (enable/disable) FLAG to serialized submit handler
	
	
	// *************************
	// SERIALIZED SUBMIT HANDLER
	// *************************
	
	// Declare serialization related variables in current function scope, outside the submit handler
	var form_fields_active, sdata, sdata_count,
		sdata_id = 'fcdata_serialized';
	
	if (doserialized_allowed && !$form.data('fc_serialized_submit_handler_added'))
	{
		$form.data('fc_serialized_submit_handler_added', true);
		
		$form.on('submit',function(e)
		{
			if ( !$form.data('doserialized_submit') )
			{
				return true;
			}
			$form.data('doserialized_submit', 0);  // Clear FLAG
			
			// These should have been removed already by cleanup process, but make sure that any previously injected fields are removed
			jQuery('#'+sdata_id).remove();
			jQuery('#'+sdata_id+'_count').remove();
			
			// Get form's active elements
			//form_fields_active = $form.find('textarea:enabled, select:enabled, input[type="radio"]:enabled:checked, input[type="checkbox"]:enabled:checked, input:not(:button):not(:radio):not(:checkbox):enabled').toArray();
			form_fields_active = jQuery(form.elements).filter( 'textarea:enabled, select:enabled, input[type="radio"]:enabled:checked, input[type="checkbox"]:enabled:checked, input:not(:button):not(:radio):not(:checkbox):enabled' );
			
			// Get max input vars limitation
			max_input_vars = typeof Joomla.fc_max_input_vars !== 'undefined' ? Joomla.fc_max_input_vars : 1000;
			
			// Abort serialization if estimated form variable count is lower than max_input_vars
			if (form_fields_active.length < max_input_vars - 50)
			{
				return true;
			}
			
			// Add form field that will hold the serialized form data, removing it if it exists already
			$form.append('<input type="hidden" name="'+sdata_id+'" id="'+sdata_id+'" value="" />');
			sdata = jQuery('#'+sdata_id);
			
			// Add form field that will hold the serialized form data counter, removing it if it exists already
			$form.append('<input type="hidden" name="'+sdata_id+'_count" id="'+sdata_id+'_count" value="" />');
			sdata_count = jQuery('#'+sdata_id+'_count');
			
			sdata_count.val( form_fields_active.length );
			sdata.val( JSON.stringify( $form.serializeArray() ) );  //sdata.val( $form.serialize() );
				
			// Disable the form fields that were serialized, so that they will not be submitted (form fields of type 'file' are not included, and will be submitted normally)
			for ( var i = 0, l = form_fields_active.length; i < l; i++ )
			{
				// Get field type / tag name
				var el = form_fields_active[i];
				var type = el.type || el.tagName.toLowerCase();
				
				// Only disable the enabled fields that were serialized, input-file was not serialized
				if (type!='file')
				{
					jQuery(el).attr('disabled', 'disabled');
				}
			};
			
			// Set FLAG for AJAX submit handler to restore the form
			$form.data('form_was_serialized', 1);
		});
	}
	
	
	// *************************
	// AJAXIFIED SUBMIT HANDLER
	// *************************
	
	if (doajax_allowed && !$form.data('fc_ajaxified_submit_handler_added'))
	{
		$form.data('fc_ajaxified_submit_handler_added', true);
			
		$form.on('submit',function(e)
		{
			// This will not be reached if
			// - either HTML5 validation fails,
			// - or any previous submit handler has called preventDefault()
			
			if ( !$form.data('doajax_submit') )
			{
				return true;
			}
			$form.data('doajax_submit', 0);  // Clear FLAG
			
			// Do not use AJAX if form has an enabled and non-empty file input
			var file_inputs = $form.find('input[type="file"]:enabled');
			if ( file_inputs.length )
			{
				var has_selected_file = false;
				file_inputs.each(function()
				{
					has_selected_file = has_selected_file || jQuery(this).val() != '';
					if (has_selected_file) return false;  // stop .each 
				});
				
				if (has_selected_file)
				{
					return true;
				}
			}
			
			// CHECKs are done, do the AJAX submit, but first prevent the normal browser submit from continuing further
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
						// Remove serialization data field and active elements counter field
						sdata.remove();
						sdata_count.remove();
						
						// Restore any modified form elements
						for ( var i = 0, l = form_fields_active.length; i < l; i++ )
						{
							var el = form_fields_active[i];
							jQuery(el).removeAttr('disabled');
						};
						
						// Clear "form was serialized" FLAG
						$form.data('form_was_serialized', 0);
					}
				}
			});
			return false;  // Indicate that nothing more should be done ... in case anyone is listening after us
		});
	}
	
	
	// *******************
	// Submit progress bar
	// *******************
	
	$form.data('add_submit_animations', !isCancel );  // Pass (enable/disable) FLAG to add submit animation
	
	if (!Joomla.fc_progressbar_submit_handler_success)
	{
		Joomla.fc_progressbar_submit_handler_success = true;
			
		$form.on('submit',function(e)
		{
			if ( !$form.data('add_submit_animations') )
			{
				return true;
			}
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
	var task_field_exists = typeof form.task !== 'undefined' && typeof form.task.value !== 'undefined';
	var form_task = task ? task : (task_field_exists  ?  form.task.value  :  '');

	// Do form validation if button task is not 'cancel'
	var match_cancel = new RegExp(/(.*.|^)cancel$/);
	var isCancel = match_cancel.test(form_task);

	// For flexicontent views we will do validation too (FLAG: fc_validateOnSubmitForm), NOTE: for non-FC views this is done before the method is called
	var doValidation = typeof window.fc_validateOnSubmitForm !== undefined ? window.fc_validateOnSubmitForm : 0;
	if ( doValidation && document.formvalidator && !isCancel )
	{
		var isValid = document.formvalidator.isValid(form);
		if (!isValid)
		{
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
			return false;
		}
	}

	// Modify form task after JS validation has run
	if (task && task_field_exists) {
		form.task.value = task;
	}

	// Suppress validateForm() of htm5fallback.js
	if ( form.H5Form ) {
		form.H5Form.donotValidate = true;
	}

	// HTML5 VALIDATION: Suppress if "*.cancel" or "cancel" tasks
	// (*) we force it to OFF ... currently we will not use it all
	if (1 || isCancel)
		form.setAttribute('novalidate', 'novalidate');

	// HTML5 VALIDATION: do according to form original code
	else if (typeof validate === 'undefined' || validate === null)
		;

	// HTML5 VALIDATION: Force OFF / ON
	else !validate ?
		form.setAttribute('novalidate', 'novalidate') :
		form.removeAttribute('novalidate') ;

	fc_submit_form(form, form_task); // Submit the form
}
