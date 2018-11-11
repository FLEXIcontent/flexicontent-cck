
var fc_file_props_handle = null;
var fc_file_count = 0;
var fc_plupload_loaded_imgs = {};

var fc_plupload_submit_props_form;
var fc_plupload;


(function($) {


fc_plupload = function(options)
{
	this.options = {
		mode: 'ui'
	};

	if( typeof options !== 'undefined') for (var key in options)
	{
		this.options[key] = options[key];  //window.console.log(key, options[key]);
	};

	this.uploader_instances = {};



	// *
	// * Auto-resize the currently open dialog vertically or horizontally
	// *

	this.autoResize = function(sfx)
	{
		var uploader_container = sfx ? $('#' + this.options.tag_id + sfx) : $('.'+this.options.tag_id);
		if (this.options.height_spare == 0) return;  // No resizing
		var window_h = $( window ).height();
		var window_w = $( window ).width();

		// Also set filelist height
		var max_filelist_h = 800;
		var plupload_filelist_h = max_filelist_h > (window_h - this.options.height_spare) ? (window_h - this.options.height_spare) : max_filelist_h;
		uploader_container.find('.plupload_filelist:not(.plupload_filelist_header):not(.plupload_filelist_footer)').css({ 'height': plupload_filelist_h+'px' });
	}


	this.clearUploader = function(sfx)
	{
		if (!!this.uploader_instances[sfx])
		{
			this.uploader_instances[sfx].destroy();
			this.uploader_instances[sfx] = null;
		}
	}

	// *
	// * Show plupload , also loading it if not already loaded
	// *

	this.toggleUploader = function(sfx, forced_action)
	{
		sfx = typeof sfx !== 'undefined' && sfx !== null ? sfx : '_';
		var uploader_container = $('#' + this.options.tag_id + sfx);
		var toggle_action = forced_action || (!this.uploader_instances[sfx] || uploader_container.is(':hidden') ? 'show' : 'hide');

		var IEversion = fc_isIE();
		var is_IE8_IE9 = IEversion && IEversion < 10;
		var runtimes = !is_IE8_IE9  ?  'html5,flash,silverlight,html4'  : 'flash,html4';  //,silverlight,html5

		if (!this.uploader_instances[sfx] && is_IE8_IE9 && !fc_has_flash_addon())
		{
			$('<span class="alert alert-warning fc-iblock">You have Internet explorer 8 / 9. Please install and activate (allow) FLASH add-on, for image preview to work</span>').insertBefore(uploader_container);
		}

		// Already initialized
		if (!!this.uploader_instances[sfx])
		{
			//this.uploader_instances[sfx].refresh();  // refresh it
			//this.uploader_instances[sfx].splice();   // empty it, ... not needed and problematic ... commented out
			toggle_action == 'hide' ? uploader_container.hide() : uploader_container.show();
			return this.uploader_instances[sfx];
		}
		//window.console.log(this.options.mode=='ui' ? 'Creating plupload UI' : 'Creating pluploadQueue');


		// Resize images at client-side if supported by the browser
		var resize_options = !this.options.resize_on_upload ? null :
		{
			width : this.options.upload_max_w,
			height : this.options.upload_max_h,
			quality : this.options.upload_quality,
			crop: this.options.upload_crop
		};

		// Specify which files to browse for, if using plupload UI then it is also possible to prevent picking file over the upload limit, but since we have client-side resizing, we will not use it
		var filters_options = this.options.view_layout != 'image'  ? (this.options.mode=='ui' ? {} : []) : (this.options.mode=='ui' ?
			{
				//max_file_size : this.options.upload_maxsize,
				mime_types: [
					{title : 'Image files', extensions : 'jpg,jpeg,gif,png'},
					{title : 'Zip files', extensions : 'zip,avi'}
				]
			} :
			[
				{title : 'Image files', extensions : 'jpg,jpeg,gif,png'},
				{title : 'Zip files', extensions : 'zip,avi'}
			]
		);


		var uploader_options =
		{
			// Multiple runtimes in prefered order
			runtimes : runtimes,

			// Flash & Silverlight runtimes (optional / fallback 'runtimes', usefull mostly for older browsers)
			flash_swf_url : this.options.flash_swf_url,
			silverlight_xap_url : this.options.silverlight_xap_url,

			// General settings
			url : this.options.action,
			prevent_duplicates : true,
			max_file_count: this.options.upload_maxcount,
			autostart_on_select: this.options.autostart_on_select,
			edit_properties: this.options.edit_properties,
			add_size_slider: this.options.add_size_slider,
			tag_id_sfx: sfx,

			refresh_on_complete: this.options.refresh_on_complete,
			thumb_size_default: this.options.thumb_size_default,

			// Set maximum file size and chunking to 1 MB
			max_file_size : this.options.upload_maxsize,
			chunk_size: '1mb',

			// Resize images at client-side
			resize : resize_options,

			// Specify what files to browse for
			filters : filters_options,

			// Rename files by clicking on their titles
			rename: true,

			// Enable ability to drag n drop files onto the widget (currently only HTML5 supports that)
			dragdrop: true,
			
			handle_FileFiltered: this.options.handle_FileFiltered,
			handle_FileUploaded: this.options.handle_FileUploaded,

			init: {
				PostInit: this.handle_PostInit,
				FilesAdded: this.handle_FilesChanged,
				FilesRemoved: this.handle_FilesChanged,

				BeforeUpload: function (up, file)
				{
					// Called right before the upload for a given file starts, can be used to cancel it if required
					up.settings.multipart_params = {
						filename: file.name,
						file_row_id: file.id
					};
					$('#'+file.id).find('.fc_props_edit_btn').remove();
				},

				QueueChanged: function (up)
				{
					var max_file_count = up.getOption('max_file_count');
					if (!!max_file_count && up.files.length > max_file_count)
					{
						up.files.splice(max_file_count, up.files.length);
						alert('Please add only ' + max_file_count + ' files');
					}
				},

				UploadComplete: function (up, files)
				{
					if (up.getOption('refresh_on_complete'))
					{
						window.document.body.innerHTML = '<span class="fc_loading_msg">Reloading ... please wait</span>';
						//window.location.replace(window.location.href);
						//window.location.reload(false);
						window.location.href = window.location.href;
					}
					var _this = $(up).data('fc_plupload_instance');
					$.each(files, function(i, file) {
						_this.extend_row( up, i, {isapplicable: {edit: false, preview: true}} );
					});
				},

				FileFiltered: function (up, file)
				{
					var func = up.getOption('handle_FileFiltered');
					if (typeof func == 'function') func.apply(this, arguments);
				},
				FileUploaded: function (up, file, result)
				{
					var func = up.getOption('handle_FileUploaded');
					if (typeof func == 'function') func.apply(this, arguments);
				}
			}
		}


		// Options supported only by plupload in jQuery UI mode
		if (this.options.mode=='ui')
		{
			var plupload_ui_options =
			{
				// Sort files
				sortable: true,

				// Native views to activate
				views: {
					list: true,
					thumbs: true,
					active: 'list'
				}
			}
		}

		// Options supported only by plupload in jQuery UI mode
		else
		{
			// 'sortable', and 'views' are not natively supported by '*Queue' , but we will add them and also enhance them ...
			var plupload_q_options = {};
		}

		// Instantiate the uploader
		if (this.options.mode=='ui')
		{
			var up = this.uploader_instances[sfx] = uploader_container.plupload( jQuery.extend(uploader_options, plupload_ui_options) );
		}
		else
		{
			// Need to make 2nd call to get the created uploader instance
			uploader_container.pluploadQueue( jQuery.extend(uploader_options, plupload_q_options) );
			var up = this.uploader_instances[sfx] = uploader_container.pluploadQueue();
		}

		// Set our uploader instance to use it inside member functions when called statically
		$(up).data('fc_plupload_instance', this);
		$(uploader_container).data('plupload_instance', up);

		// It is also possible to bind events also after initialization
		//up.bind('PostInit', this.handle_PostInit);
		//up.bind('FilesAdded', this.handle_FilesChanged);
		//up.bind('FilesRemoved', this.handle_FilesChanged);

		// Toggle the uploader container
		toggle_action == 'hide' ? uploader_container.hide() : uploader_container.show();
		return this.uploader_instances[sfx];
	};



	// *
	// * Handle the PostInit event. At this point, we will know which runtime
	// * has loaded, and whether or not drag-drop functionality is supported.
	// * NOTE: we use the "PostInit" instead of the "Init" event in order for the "dragdrop" feature to be correct defined
	// *

	this.handle_PostInit = function(uploader)
	{
		// Get 'fc_plupload' class instance from uploader
		var _this = $(uploader).data('fc_plupload_instance');
		var uploader_container = $(uploader.settings.container);

		// Autostart uploading
		if (uploader.getOption('autostart_on_select'))
		{
			uploader_container.find('.plupload_button.plupload_start').hide();
		}

		var slider_cfg = _this.options.thumb_size_slider_cfg;
		var slider_sfx = uploader.getOption('add_size_slider') ? uploader.getOption('tag_id_sfx') : '';
		var slider_tag_id = slider_cfg ? slider_cfg.name + slider_sfx : '';

		if (!uploader.getOption('max_file_count') || uploader.getOption('max_file_count') > 1)
		{
			uploader_container.find('.plupload_filelist').sortable({
				cancel: '.fc_zooming',
				containment: 'parent',
				tolerance: 'pointer',
				distance: 12,
				stop: function(event,ui)
				{
					var _files = new Array;

					// Find new order
					for ( var i = 0 ; i < uploader.files.length ; i++ )
					{
						var file_row_id = uploader.files[i].id;
						var index = $('#'+file_row_id).index();
						_files[index] = uploader.files[i];
					}

					// Apply new order (!Do not try to make an array assignment (object assignment) it will not work)
					for ( var i = 0 ; i < _files.length ; i++ )
					{
						uploader.files[i] = _files[i];
					}
				}
			});
		}

		var uploader_header = uploader_container.find('.plupload_header_content');

		// Remove useless title 'Using runtime ...'
		uploader_container.find('.plupload_container').removeAttr('title');

		/*if (uploader_header.find('.fc-uploader-loading').length==0)
		{
			uploader_header.prepend('\
				<div class="fc-mssg-inline fc-success fc-small fc-iblock fc-nobgimage fc-uploader-loading" style="display: none;">\
					<img src="components/com_flexicontent/assets/images/ajax-loader.gif" /> ' + Joomla.JText._('FLEXI_LOADING_IMAGES') + ' ...\
				</div>\
			');
		}*/

		if (slider_cfg && uploader.getOption('add_size_slider'))
		{
			uploader_header.prepend('\
				<select id="'+slider_tag_id+'-sel" name="'+slider_tag_id+'-sel" class="fc_uploader_size_select" type="text" style="display: none;"></select>\
				<div id="'+slider_tag_id+'_nouislider" class="fc_uploader_grid_element fc_uploader_size_slider" style="display: none;"></div>\
				<div class="fc_slider_input_box">\
					<input id="'+slider_tag_id+'-val" name="'+slider_tag_id+'-val" type="text" size="12" value="150" />\
				</div>\
			');
		}

		if (uploader_header.find('.fc_plupload_toggleThumbs_btn').length==0)
		{
			uploader_header
			.prepend('\
				<span class="btn fc_plupload_toggleThumbs_btn hasTooltip" title="' + Joomla.JText._('FLEXI_THUMBNAILS') + '" style="float:right; margin: 12px 8px;" onclick="jQuery(this).closest(\'.plupload_container\').toggleClass(\'fc_uploader_hide_preview\');">\
					<span class="icon-image"></span>\
				</span>\
			')
			.prepend('\
			<div class="btn-group" style="margin: 12px; float: right;">\
				<button type="button" class="btn list-view hasTooltip active" id="btn-upload-list-view" onclick="fc_toggle_view_mode(jQuery(this)); jQuery(this).next().removeClass(\'active\'); jQuery(this).addClass(\'active\'); jQuery(this).closest(\'.plupload_scroll\').parent().removeClass(\'fc_uploader_thumbs_view\');" data-toggle_selector=".fc_uploader_list_element" style="width: 24px;" title="Details"><i class="icon-list-view"></i></button>\
				<button type="button" class="btn grid-view hasTooltip" id="btn-upload-grid-view" onclick="fc_toggle_view_mode(jQuery(this)); jQuery(this).prev().removeClass(\'active\'); jQuery(this).addClass(\'active\'); jQuery(this).closest(\'.plupload_scroll\').parent().addClass(\'fc_uploader_thumbs_view\');" data-toggle_selector=".fc_uploader_grid_element" style="width: 24px;" title="Grid"><i class="icon-grid-view"></i></button>\
			</div>\
			');
		}

		setTimeout(function(){
			// Attach slider
			if (slider_cfg && uploader.getOption('add_size_slider'))
			{
				fc_attachSingleSlider( jQuery.extend(true, {sfx: slider_sfx, start_hidden: true}, slider_cfg) );
				$('#'+slider_tag_id).hide();  // TODO add config option for this
			}
			// Set initial thumbnail size
			var thumb_size_default = slider_tag_id && $('#'+slider_tag_id+'-val').length ? $('#'+slider_tag_id+'-val').val() : (uploader.getOption('thumb_size_default') ? uploader.getOption('thumb_size_default') : '150');
			$(uploader.settings.container).addClass('thumb_' + thumb_size_default);
		}, 40);
	};



	// *
	// * Handle the files-added event. This is different that the queue-changed event.
	// * Since at this point, we have an opportunity to reject files from the queue.
	// *

	this.handle_FilesChanged = function(uploader, files)
	{
		// Get 'fc_plupload' class instance from uploader
		var _this = $(uploader).data('fc_plupload_instance');

		// Get per file form data from uploader
		var form_data = $(uploader.settings.container).data('form_data');
		if (!form_data) form_data = {};

		// Update thumb size
		var slider_cfg = _this.options.thumb_size_slider_cfg;
		var slider_sfx = uploader.getOption('add_size_slider') ? uploader.getOption('tag_id_sfx') : '';
		var slider_tag_id = slider_cfg ? slider_cfg.name + slider_sfx : '';
		if (slider_cfg)
		{
			var thumb_size_default = $('#'+slider_tag_id+'-val').length ? $('#'+slider_tag_id+'-val').val() : (uploader.getOption('thumb_size_default') ? uploader.getOption('thumb_size_default') : '150');
			$(uploader.settings.container).addClass('thumb_' + thumb_size_default);
		}

		// Since the full list is recreated, on new file(s) added. We need to loop through all
		// files and update their client-side preview, and not only through the newly added files
		for ( var i = 0 ; i < uploader.files.length ; i++ )
		{
			// Add extra functionality to the file row: File properties and file preview
			_this.extend_row( uploader, i, {isapplicable: {edit: true, preview: true}} );

			// Mark edit button with SUCCESS color to show that it has already assigned file properties
			var file_row_id = uploader.files[i].id;
			!!form_data[file_row_id] ?
				$('#'+file_row_id).find('.fc_props_edit_btn').addClass('btn-success') :
				$('#'+file_row_id).find('.fc_props_edit_btn').removeClass('btn-success') ;
		}

		var slider_cfg = _this.options.thumb_size_slider_cfg;
		$('#'+slider_tag_id+'-sel').trigger('change');
		$('#'+slider_tag_id+'_nouislider').trigger('change');

		// Autostart uploading
		if (uploader.getOption('autostart_on_select'))
		{
			$(uploader.settings.container).find('.plupload_filelist_footer').hide();
			//setTimeout(function(){ uploader.start(); }, 5000);
			uploader.start();
		}
	};



	// *
	// * Create client side image preview. This is given a File object (as presented by Plupload),
	// * and show the client-side-only preview of the selected image object.
	// *

	this.extend_row = function(uploader, i, ops)
	{
		// Get 'fc_plupload' class instance from uploader
		var _this = $(uploader).data('fc_plupload_instance');

		var IEversion = fc_isIE();
		var is_IE8_IE9 = IEversion && IEversion < 10;

		edit_properties = uploader.getOption('edit_properties');
		edit_properties = edit_properties !== false ? true : edit_properties;
	
		var file = uploader.files[i];
		var file_row_id = file.id;
		var file_row = $('#'+file_row_id);
		var file_name_box = file_row.find('.plupload_file_name');
		var is_img = is_IE8_IE9 && !fc_has_flash_addon() ? 0 : file.name.match(/\.(jpg|jpeg|png|gif)$/i);

		// Add extra CSS classes to the delete buttons
		file_row.find('.plupload_file_action > a').addClass('fc_uploader_row_remove');

		/*
		 * Add properties editing button
		 */
		var btn_box = $('<span class="btn-group fc_uploader_row_btns"></span>').insertAfter( file_name_box );

		if (edit_properties && ops.isapplicable.edit)
		{
			var properties_handle = $('<span class="btn fc_props_edit_btn icon-pencil"></span>').appendTo( btn_box );
			var fileprops_message = $('<div class="fileprops_message fc_ajax_message_box"></div>').insertAfter( btn_box );

			// Add opening of file properties modal form on click of properties button
			properties_handle.on( "click", function()
			{
				var form_data = $(uploader.settings.container).data('form_data');
				if (!form_data) form_data = {};
				var file_row_id = $(this).closest('li').attr('id');
				var file_data = typeof form_data[file_row_id] == "undefined" ? false : form_data[file_row_id];

				// Set the EDIT btn that open current file properties (this can used to find the file row being edited)
				var btn = $(this);
				var form_box = $('#filePropsForm_box');
				var form = $('#filePropsForm');
				form.data('edit_btn', btn);

				// Restore the form field data and set ... and then also set the file ID in it
				fc_restore_form_field_values(form, file_data);
				form.find('[name="file_row_id"]').val(file_row_id);

				// Get current filename and extension from the file row
				var file_name = btn.closest('li').find('.plupload_file_name').text();

				var name_parts = file_name.split('.');
				var part_ext  = name_parts.length == 1 ? '' : name_parts[name_parts.length-1];
				var regexp = new RegExp('.'+part_ext+'$');
				var part_name = file_name.replace(regexp, '');

				form.find('[name="file-props-name-ext"]').val( part_ext );
				form.find('[name="file-props-name"]').val( part_name );

				// Now show the form
				fc_file_props_handle = fc_showAsDialog(form_box.get(0), 600, 600, null, { title: Joomla.JText._('FLEXI_FILE_PROPERTIES'), paddingW: 10, paddingH: 10,  });
			});
		}


		/*
		 * Add image preview button ... 
		 */
		if (is_img && ops.isapplicable.preview)
		{
			var imgpreview_handle = $('<span class="btn fc_img_preview_btn icon-search"></span>').appendTo( btn_box );
			var box = $('<span class="plupload_img_preview"></span>').insertAfter( btn_box );

			// Try to use already loaded image, otherwise we will load it later
			var image_already_loaded = !! fc_plupload_loaded_imgs[file_row_id];
			if (!image_already_loaded)
			{
				fc_file_count++;
				//$('.fc-uploader-loading').show();
				fc_plupload_loaded_imgs[file_row_id] = $('<img class="plupload_loading_img" src="components/com_flexicontent/assets/images/ajax-loader.gif" />');
			}


			file_row.addClass('fc_uploader_is_image');     // Add class to indicate that file ROW is an image
			fc_plupload_loaded_imgs[file_row_id].appendTo( box );   // Add existing image or loading animation icon to the DOM


			// Add image zoom-in-out on click of image preview button
			imgpreview_handle.add(fc_plupload_loaded_imgs[file_row_id]).on( "click", function()
			{
				// Close any open previews
				var IEversion = fc_isIE();
				var file_row, btn, img_box, img;
				file_row = $(this).closest('li');
				btn = file_row.find('.fc_img_preview_btn');
				img_box = file_row.find('.plupload_img_preview');
				img = img_box.find('img');
				var is_img = this.tagName=='IMG';

				file_row.closest('ul').find('li:not("#' + file_row.attr('id') + '") .btn.fc_img_preview_btn.active').trigger('click');
				if (file_row.hasClass('fc_zoomed'))
				{
					btn.removeClass('active btn-info');
					file_row.removeClass('fc_zoomed');
					setTimeout(function(){
						file_row.removeClass('fc_zooming');
						$('#fc-fileman-overlay').hide();
						if (IEversion && IEversion < 9) img.css('left', '');
					}, (!IEversion || IEversion > 9 ? 320 : 20));
				}
				else
				{
					if (file_row.hasClass('fc_zooming')) return;  // Zooming already started
					if (is_img /*&& file_row.closest('.plupload_scroll').parent().hasClass('fc_uploader_thumbs_view')*/) return;    // Avoid zooming when clicking thumbnails in grid view and for consistency in list view too ...
					$('#fc-fileman-overlay').show();
					file_row.addClass('fc_zooming');
					setTimeout(function(){
						file_row.addClass('fc_zoomed');
						btn.addClass('active btn-info');
						if (IEversion && IEversion < 9) img.css('left', $(window).width()/2-(img.width()/2));
					}, 20);
				}
			});


			// Done if image has been loaded already
			if ( image_already_loaded) return;


			// Create an instance of the window.moxie.image (Image object) --  Wiki: https://github.com/moxiecode/moxie/wiki/Image
			// This utility object provides several means of reading in and loading image data from various sources.
			file.preloader = new window.moxie.image.Image();

			// Define the onload BEFORE you execute the load() command as load() does not execute async.
			file.preloader.onload = function()
			{
				// This will scale the image (in memory) before it tries to render it. This just reduces the amount of Base64 data that needs to be rendered.
				// Use higher resultion to allow zooming and also for better thumbnail
				var min_dimension = this.width > this.height ? this.height : this.width;
				min_dimension = min_dimension > 1000 ? 1000 : min_dimension;
				this.downsize({width: min_dimension, height: min_dimension, crop: 'cc'});

				// Now that the image is preloaded, grab the Base64 encoded data URL. This will show the image without making an Network request using the client-side file binary.
				fc_plupload_loaded_imgs[file_row_id].prop( "src", this.getAsDataURL() ).removeClass('plupload_loading_img');
				fc_file_count--;
				//if (fc_file_count==0) $('.fc-uploader-loading').hide();
			};

			// Calling the .getSource() on the file will return an instance of window.moxie.file (File object), which is a unified file wrapper that can be used across the various runtimes.
			// Wiki: https://github.com/moxiecode/plupload/wiki/File
			file.preloader.load( file.getSource() );
		}
	}



	// *
	// * AJAX submit a files property form
	// *

	this.submit_props_form = function(obj, uploader)
	{
		// Get 'fc_plupload' class instance from uploader
		var _this = $(uploader).data('fc_plupload_instance');

		// Close (hide) the modal containing form
		fc_file_props_handle.dialog('close');

		// Get form, form data
		var IEversion = fc_isIE();
		var form = (!IEversion || IEversion > 8) ? $(obj.form) : $(obj).closest('form');
		var data = form.serialize();	

		// Mark EDIT button of the file row, as having file properties
		var btn = form.data('edit_btn');	
		if (btn) btn.addClass('btn-success');

		// Store file properties of the current file row, so that they can be reloaded and re-edited, without contacting WEB server
		var file_row_id = form.find('[name="file_row_id"]').val();
		var file_row = $('#'+file_row_id);

		var form_data = $(uploader.settings.container).data('form_data');
		if (!form_data) form_data = {};
		form_data[file_row_id] = form.serializeObject();
		$(uploader.settings.container).data('form_data', form_data);

		// Update file row so that new filename is displayed
		var new_filename = form.find('[name="file-props-name"]').val();
		new_filename = fc_sanitize_filename( new_filename ) + '.' +  form.find('[name="file-props-name-ext"]').val();
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
		var props_msg_box = file_row.find('.fileprops_message');
		props_msg_box.html('<div class="fc_loading_msg fc_loading_box">' + Joomla.JText._('FLEXI_APPLYING_DOT') + '</div>');
		props_msg_box.css({display: '', opacity: ''});   // show message
		props_msg_box.parent().find('.plupload_img_preview').css('display', 'none');  // Hide preview image

		// Hide uploader buttons until server responds
		var uploader_footer = $(uploader.settings.container).find('.plupload_filelist_footer')
		uploader_footer.hide();

		// Store file properties into USER's session by sending them to the SERVER
		$.ajax({
			url: form.attr('action'),
			type: 'POST',
			dataType: "json",
			data: data,
			success: function(data) {
				uploader_footer.show();  // Show uploader buttons previously hidden
				props_msg_box.html('');  // Start with empting the file row's message box
				try {
					var response = typeof data !== "object" ? $.parseJSON( data ) : data;
					$('#system-message-container').html(!!response.sys_messages ? response.sys_messages : '');
					props_msg_box.append(response.result);
					setTimeout(function(){ props_msg_box.fadeOut(1000); }, 1000);
					setTimeout(function(){ props_msg_box.parent().find('.plupload_img_preview').css('display', '') }, 2000);
					//if(window.console) window.console.log(response);
				} catch(err) {
					props_msg_box.html('<span class="alert alert-warning fc-iblock">' + err.message + '</span>');
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
}


})(jQuery);
