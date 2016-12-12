
	var fc_file_props_handle = null;
	var fc_file_count = 0;
	var fc_plupload_loaded_imgs = {};
	
	// Handle the PostInit event. At this point, we will know which runtime
	// has loaded, and whether or not drag-drop functionality is supported.
	// --
	// NOTE: we use the "PostInit" instead of the "Init" event in order for the "dragdrop" feature to be correct defined

	function fc_plupload_handle_init( uploader, params )
	{
		//if(window.console) window.console.log( "PostInit event" );
		var uploader_container = jQuery(uploader.settings.container);

		if (uploader_container.find('.fc_plupload_toggleThumbs_btn').length==0)
		{
			uploader_container.find('.plupload_filelist').sortable({
				containment: 'parent',
				tolerance: 'pointer'
			});
			uploader_container.find('.plupload_header_content')
			.prepend('\
				<div id="fc-uploader-loading" class="fc-mssg-inline fc-success  fc-small fc-iblock fc-nobgimage" style="display: none;">\
					<img src="components/com_flexicontent/assets/images/ajax-loader.gif" /> ' + Joomla.JText._('FLEXI_LOADING_IMAGES') + ' ...\
				</div>\
			')
			.prepend('\
				<div id="fc-uploader-grid-thumb-size_nouislider" class="fman_grid_element"></div>\
				<div class="fc_slider_input_box">\
					<input id="fc-uploader-grid-thumb-size-val" name="fc-uploader-grid-thumb-size-val" type="text" size="12" value="140" />\
				</div>\
			')
			.prepend('\
				<span class="btn fc_plupload_toggleThumbs_btn" style="float:right; margin: 12px 8px;" onclick="jQuery(this).closest(\'.plupload_container\').toggleClass(\'fc_uploader_hide_preview\');">\
					<span class="icon-image"></span> <span class="fc_hidden_960">' + Joomla.JText._('FLEXI_THUMBNAILS') + '</span>\
				</span>\
			')
			.prepend('\
			<div class="btn-group" style="margin: 12px; float: right;">\
				<button type="button" class="btn list-view hasTooltip active" id="btn-upload-list-view" onclick="jQuery(this).next().removeClass(\'active\'); jQuery(this).addClass(\'active\'); jQuery(this).closest(\'.plupload_container\').removeClass(\'fc_uploader_thumbs_view\');" style="width: 60px;" title="Details"><i class="icon-list-view"></i></button>\
				<button type="button" class="btn grid-view hasTooltip" id="btn-upload-grid-view" onclick="jQuery(this).prev().removeClass(\'active\'); jQuery(this).addClass(\'active\'); jQuery(this).closest(\'.plupload_container\').addClass(\'fc_uploader_thumbs_view\');" style="width: 60px;" title="Grid"><i class="icon-grid-view"></i></button>\
			</div>\
			');

			setTimeout(function(){ fc_attachSingleSlider(fc_uploader_slider_cfg); }, 40);
		}
	}


	// Handle the files-added event. This is different that the queue-changed event.
	// Since at this point, we have an opportunity to reject files from the queue.

	function fc_plupload_handle_filesChanged( uploader, files )
	{
		//if(window.console) window.console.log( "Files added." );

		// Get per file form data from uploader
		var form_data = jQuery(uploader.settings.container).data("form_data");
		if (!form_data) form_data = {};

		// Since the full list is recreated, on new file(s) added. We need to loop through all
		// files and update their client-side preview, and not only through the newly added files
		for ( var i = 0 ; i < uploader.files.length ; i++ )   //for ( var i = 0 ; i < files.length ; i++ )
		{
			// Add extra functionality to the file row: File properties and file preview
			fc_plupload_extend_row( uploader, i );

			// Mark edit button with SUCCESS color to show that it has already assigned file properties
			var file_row_id = uploader.files[i].id;
			!!form_data[file_row_id] ?
				jQuery("#"+file_row_id).find('.fc_props_edit_btn').addClass("btn-success") :
				jQuery("#"+file_row_id).find('.fc_props_edit_btn').removeClass("btn-success") ;
		}
	}


	// Create client side image preview. This is given a File object (as presented by Plupload),
	// and show the client-side-only preview of the selected image object.

	function fc_plupload_extend_row( uploader, i )
	{
		var IEversion = isIE();
		var is_IE8_IE9 = IEversion && IEversion < 10;

		params = typeof params == "undefined" ? {} : params;
		params.edit_properties = typeof params.edit_properties == "undefined" ? 1 : params.edit_properties;
		
		var file = uploader.files[i];
		var file_row_id = file.id;
		var file_row = jQuery('#'+file_row_id);
		var file_name_box = file_row.find('.plupload_file_name');
		var is_img = is_IE8_IE9 && !fc_has_flash_addon() ? 0 : file.name.match(/\.(jpg|jpeg|png|gif)$/i);

		// Add extra CSS classes to the delete buttons
		file_row.find('.plupload_file_action > a').addClass('icon-remove fc_uploader_row_remove');
		file_row.addClass('thumb_' + jQuery('#fc-uploader-grid-thumb-size-val').val());

		/*
		 * Add properties editing button
		 */
		var btn_box = jQuery("<span class=\"btn-group fc_uploader_row_btns\"></span>").insertAfter( file_name_box );

		if (params.edit_properties)
		{
			var properties_handle = jQuery("<span class=\"btn fc_props_edit_btn icon-pencil\"></span>").appendTo( btn_box );
			var fileprops_message = jQuery("<div class=\"fileprops_message fc_ajax_message_box\"></div>").insertAfter( btn_box );

			// Add opening of file properties modal form on click of properties button
			properties_handle.on( "click", function()
			{
				var form_data = jQuery(uploader.settings.container).data("form_data");
				if (!form_data) form_data = {};
				var file_row_id = jQuery(this).closest("li").attr("id");
				var file_data = typeof form_data[file_row_id] == "undefined" ? false : form_data[file_row_id];

				// Set the EDIT btn that open current file properties (this can used to find the file row being edited)
				var btn = jQuery(this);
				var form_box = jQuery("#filePropsForm_box");
				var form = jQuery("#filePropsForm");
				form.data("edit_btn", btn);

				// Restore the form field data and set ... and then also set the file ID in it
				fc_restore_form_field_values(form, file_data);
				form.find('[name="file_row_id"]').val(file_row_id);

				// Get current filename and extension from the file row
				var file_name = btn.closest("li").find('.plupload_file_name').text();

				var name_parts = file_name.split('.');
				var part_ext  = name_parts.length == 1 ? '' : name_parts[name_parts.length-1];
				var regexp = new RegExp('.'+part_ext+'$');
				var part_name = file_name.replace(regexp, '');

				form.find('[name="file-props-name-ext"]').val( part_ext );
				form.find('[name="file-props-name"]').val( part_name );

				// Now show the form
				fc_file_props_handle = fc_showAsDialog(form_box, 800, 600, null, { title: Joomla.JText._('FLEXI_FILE_PROPERTIES') });
			});
		}


		/*
		 * Add image preview button ... 
		 */
		if ( is_img )
		{
			var imgpreview_handle = jQuery("<span class=\"btn fc_img_preview_btn icon-search\"></span>").appendTo( btn_box );
			var box = jQuery("<span class=\"plupload_img_preview\"></span>").insertAfter( btn_box );

			// Try to use already loaded image, otherwise we will load it later
			var image_already_loaded = !! fc_plupload_loaded_imgs[file_row_id];
			if (!image_already_loaded)
			{
				fc_file_count++;
				jQuery('#fc-uploader-loading').show();
				fc_plupload_loaded_imgs[file_row_id] = jQuery( "<img class=\"plupload_loading_img\" src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" />" );
			}


			file_row.addClass("fc_uploader_is_image");     // Add class to indicate that file ROW is an image
			fc_plupload_loaded_imgs[file_row_id].appendTo( box );   // Add existing image or loading animation icon to the DOM


			// Add image zoom-in-out on click of image preview button
			imgpreview_handle.add(fc_plupload_loaded_imgs[file_row_id]).on( "click", function()
			{
				// Close any open previews
				var file_row, btn, img_box;
				file_row = jQuery(this).closest("li");
				btn = file_row.find('.fc_img_preview_btn');
				img_box = file_row.find('.plupload_img_preview');
				var is_img = this.tagName=='IMG';

				file_row.closest("ul").find("li:not('#" + btn.closest('li').attr('id') + "') .btn.fc_img_preview_btn.active").trigger("click");
				if (file_row.hasClass("fc_uploader_zoomed"))
				{
					btn.removeClass("active btn-info");
					file_row.removeClass("fc_uploader_zoomed");
					setTimeout(function(){ file_row.removeClass("fc_uploader_zooming"); }, 620);
				}
				else
				{
					if (file_row.hasClass("fc_uploader_zooming")) return;  // Zooming already started
					if (is_img && file_row.closest('.plupload_container').hasClass("fc_uploader_thumbs_view")) return;    // Avoid zooming when clicking thumbnails in grid view
					file_row.addClass("fc_uploader_zooming fc_uploader_zoomed");
					btn.addClass("active btn-info");
				}
			});


			// Done if image has been loaded already
			if ( image_already_loaded) return;


			// Create an instance of the mOxie Image object.  --  Wiki: https://github.com/moxiecode/moxie/wiki/Image
			// This utility object provides several means of reading in and loading image data from various sources.
			file.preloader = new mOxie.Image();

			// Define the onload BEFORE you execute the load() command as load() does not execute async.
			file.preloader.onload = function()
			{
				// This will scale the image (in memory) before it tries to render it. This just reduces the amount of Base64 data that needs to be rendered.
				// Use higher resultion to allow zooming and also for better thumbnail
				this.downsize( 1000, 1000 );

				// Now that the image is preloaded, grab the Base64 encoded data URL. This will show the image without making an Network request using the client-side file binary.
				fc_plupload_loaded_imgs[file_row_id].prop( "src", this.getAsDataURL() ).removeClass('plupload_loading_img');
				fc_file_count--;
				if (fc_file_count==0) jQuery('#fc-uploader-loading').hide();
			};

			// Calling the .getSource() on the file will return an instance of mOxie.File, which is a unified file wrapper that can be used across the various runtimes.
			// Wiki: https://github.com/moxiecode/plupload/wiki/File
			file.preloader.load( file.getSource() );
		}
	}


	function fc_plupload_submit_props_form(obj, uploader)
	{
		fc_file_props_handle.dialog('close');  // Close form dialog
	
		// Get form, form data
		var IEversion = isIE();
		var form = (!IEversion || IEversion > 8) ? jQuery(obj.form) : jQuery(obj).closest('form');
		var data = form.serialize();	

		// Mark EDIT button of the file row, as having file properties
		var btn = form.data("edit_btn");	
		if (btn) btn.addClass('btn-success');

		// Store file properties of the current file row, so that they can be reloaded and re-edited, without contacting WEB server
		var file_row_id = form.find('[name="file_row_id"]').val();
		var file_row = jQuery("#"+file_row_id);

		var form_data = jQuery(uploader.settings.container).data("form_data");
		if (!form_data) form_data = {};
		form_data[file_row_id] = form.serializeObject();
		jQuery(uploader.settings.container).data("form_data", form_data);

		// Update file row so that new filename is displayed
		var new_filename = form.find('[name="file-props-name"]').val();
		new_filename = fc_plupload_sanitize_filename( new_filename ) + '.' +  form.find('[name="file-props-name-ext"]').val();
		if (new_filename != '')
		{
			var file_name_box = file_row.find('.plupload_file_name');
			var old_filename = file_name_box.text();
			file_name_box.html('<span>'+new_filename+'</span>');

			// Set new filename into the files data of the uploader
			for ( var i = 0 ; i < uploader.files.length ; i++ )
			{
				var file = uploader.files[i];
				if (file.name == old_filename)
				{
					file.name = new_filename;
				}
			}
		}

		// Set data
		var props_msg_box = jQuery("li#"+file_row_id).find('.fileprops_message');
		props_msg_box.html("<div class=\"fc_loading_msg\">"+Joomla.JText._('FLEXI_APPLYING_DOT')+"</div>");
		props_msg_box.css({display: '', opacity: ''});   // show message
		props_msg_box.parent().find('.plupload_img_preview').css('display', 'none');  // Hide preview image

		// Hide uploader buttons until server responds
		var uploader_footer = jQuery(uploader.settings.container).find('.plupload_filelist_footer')
		uploader_footer.hide();

		// Store file properties into USER's session by sending them to the SERVER
		jQuery.ajax({
			url: form.attr('action'),
			type: 'POST',
			dataType: "json",
			data: data,
			success: function(data) {
				uploader_footer.show();  // Show uploader buttons previously hidden
				props_msg_box.html('');  // Start with empting the file row's message box
				try {
					var response = typeof data !== "object" ? jQuery.parseJSON( data ) : data;
					jQuery('#system-message-container').html(!!response.sys_messages ? response.sys_messages : '');
					props_msg_box.append(response.result);
					setTimeout(function(){ props_msg_box.fadeOut(1000); }, 1000);
					setTimeout(function(){ props_msg_box.parent().find('.plupload_img_preview').css('display', '') }, 2000);
					//if(window.console) window.console.log(response);
				} catch(err) {
					props_msg_box.html("<span class=\"alert alert-warning fc-iblock\">': "+err.message+"</span>");
				};
				uploader_footer.show();  // Show uploader buttons previously hidden
			},
			error: function (xhr, ajaxOptions, thrownError) {
				uploader_footer.show();  // Show uploader buttons previously hidden
				props_msg_box.html('');
				alert('Error status: ' + xhr.status + ' , Error text: ' + thrownError);
			}
		});
	}


	function fc_plupload_sanitize_filename(text)
	{
		var result = '';
		if (!text) return result;

		var validChars = new RegExp('[A-Za-z0-9\.\_\-]+');

		for (var i = 0; i < text.length; ++i)
		{
			if (validChars.test(text[i]))
			{
				result += text[i];
			}
		}
		return result;
	}
