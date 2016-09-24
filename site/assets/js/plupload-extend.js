
	var fc_plupload_loaded_imgs = {};
	
	// Handle the PostInit event. At this point, we will know which runtime
	// has loaded, and whether or not drag-drop functionality is supported.
	// --
	// NOTE: we use the "PostInit" instead of the "Init" event in order for the "dragdrop" feature to be correct defined

	function fc_plupload_handle_init( uploader, params )
	{
		var uploader_container = jQuery(uploader.settings.container);

		uploader_container.find(".plupload_header_content").prepend(`
			<span class="btn fc_plupload_toggleThumbs_btn" style="float:right; margin: 12px;" onclick="uploader_container.toggleClass("fc_uploader_hide_preview");">
				<span class="icon-image"></span> Thumbnails
			</span>
		`);
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
			var row_id = uploader.files[i].id;
			!!form_data[row_id] ?
				jQuery("#"+row_id).find(".fc_props_edit_btn").addClass("btn-success") :
				jQuery("#"+row_id).find(".fc_props_edit_btn").removeClass("btn-success") ;
		}
	}


	// Create client side image preview. This is given a File object (as presented by Plupload),
	// and show the client-side-only preview of the selected image object.

	function fc_plupload_extend_row( uploader, i, item )
	{
		var file = uploader.files[i];
		var row_id = file.id;
		var row = jQuery("#"+row_id);
		var item = row.find(".plupload_file_name");
		var is_img = file.name.match(/\.(jpg|jpeg|png|gif)$/i);


		/*
		 * Add properties editing button
		 */
		var btn_box = jQuery("<span class=\"btn-group fc_uploader_row_btns\"></span>").insertAfter( item );

		if (!fc_file_folder_mode)
		{
			var properties_handle = jQuery("<span class=\"btn fc_props_edit_btn icon-pencil\"></span>").appendTo( btn_box );
			var fileprops_message = jQuery("<div class=\"fileprops_message fc_ajax_message_box\"></div>").insertAfter( btn_box );

			// Add opening of file properties modal form on click of properties button
			properties_handle.on( "click", function()
			{
				var form_data = jQuery(uploader.settings.container).data("form_data");
				if (!form_data) form_data = {};
				var file_id = jQuery(this).closest("li").attr("id");
				var file_data = typeof form_data[file_id] == "undefined" ? false : form_data[file_id];

				// Set the EDIT btn that open current file properties (this can used to find the file row being edited)
				var btn = jQuery(this);
				var form_box = jQuery("#filePropsForm_box");
				var form = jQuery("#filePropsForm");
				form.data("edit_btn", btn);

				// Restore the form field data and set ... and then also set the file ID in it
				fc_restore_form_field_values(form, file_data);
				form.find('[name="uploader_file_id"]').val(file_id);

				// Now show the form
				fc_file_props_handle = fc_showAsDialog(form_box, null, null, null, { title: Joomla.JText._('FLEXI_FILE_PROPERTIES') });
			});
		}


		/*
		 * Add image preview button ... 
		 */
		if ( is_img )
		{
			var imgpreview_handle = jQuery("<span class=\"btn fc_img_preview_btn icon-eye\"></span>").appendTo( btn_box );
			var box = jQuery("<span class=\"plupload_img_preview\"></span>").insertAfter( btn_box );

			// Try to use already loaded image, otherwise we will load it later
			var image_already_loaded = !! fc_plupload_loaded_imgs[row_id];
			if (!image_already_loaded)
			{
				fc_plupload_loaded_imgs[row_id] = jQuery( "<img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" />" );
			}


			row.addClass("fc_uploader_is_image");     // Add class to indicate that file ROW is an image
			fc_plupload_loaded_imgs[row_id].appendTo( box );   // Add existing image or loading animation icon to the DOM


			// Add image zoom-in-out on click of image preview button
			imgpreview_handle.add(fc_plupload_loaded_imgs[row_id]).on( "click", function()
			{
				// Close any open previews
				var row, btn, img_box;
				row = jQuery(this).closest("li");
				btn = row.find(".fc_img_preview_btn");
				img_box = row.find(".plupload_img_preview");

				row.closest("ul").find("li:not('#" + btn.closest("li").attr("id") + "') .btn.fc_img_preview_btn.active").trigger("click");
				if (row.hasClass("fc_uploader_zoomed"))
				{
					btn.removeClass("active btn-info");
					row.removeClass("fc_uploader_zoomed");
					setTimeout(function(){ row.removeClass("fc_uploader_zooming"); }, 400);
				}
				else {
					if (row.hasClass("fc_uploader_zooming")) return;
					row.addClass("fc_uploader_zooming fc_uploader_zoomed");
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
				this.downsize( 800, 600 );

				// Now that the image is preloaded, grab the Base64 encoded data URL. This will show the image without making an Network request using the client-side file binary.
				fc_plupload_loaded_imgs[row_id].prop( "src", this.getAsDataURL() );
			};

			// Calling the .getSource() on the file will return an instance of mOxie.File, which is a unified file wrapper that can be used across the various runtimes.
			// Wiki: https://github.com/moxiecode/plupload/wiki/File
			file.preloader.load( file.getSource() );
		}
	}

