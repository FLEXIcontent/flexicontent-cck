	var fcfield_file = {};
	
	fcfield_file.debugToConsole = [];
	fcfield_file.use_native_apis = [];
	fcfield_file.dialog_handle = [];

	fcfield_file.initValue = function(field_name_n, config_name)
	{
		//window.console.log(field_name_n);
		var fnn  = field_name_n.replace(/-/g, '_');
		var file = jQuery('#custom_' + field_name_n + '_file-data-txt');
	}


	fcfield_file.showUploader = function(field_name_n, config_name)
	{
		var fnn  = field_name_n.replace(/-/g, '_');
		var box = jQuery('#custom_' + field_name_n + '_file-data-txt').closest('.fcfieldval_container');
		box.find('.fc_files_uploader_toggle_btn').click();
	}


	fcfield_file.fileFiltered = function(uploader, file, config_name)
	{
	}


	fcfield_file.fileUploaded = function(uploader, file, result, config_name)
	{
		// Get 'fc_plupload' class instance from uploader
		var _this = jQuery(uploader).data('fc_plupload_instance');
		try {
			var response = eval(result.response);
		} catch(err) {
			var response = eval('(' + result.response + ')');
		}

		if (!!response.error)
		{
			alert(response.error.message);
			return;
		}

		//window.console.log(response.data);
		var file = response.data;
		file.targetid    = jQuery(uploader.settings.container).closest('.fcfieldval_container').find('.fc_filedata_txt').attr('id');
		file.preview_url = jQuery(uploader.settings.container).find('.plupload_img_preview > img').attr('src');
		fcfield_file.assignFile(file.targetid, file, 0, config_name);
	}


	fcfield_file.clearFieldUploader = function(box, config_name)
	{
		var upload_container = box.find('.fc_file_uploader');
		var upload_instance = upload_container.data('plupload_instance');

		var upBTN = box.find('.fc_files_uploader_toggle_btn');
		if (upload_instance)
		{
			jQuery(upload_instance).data('fc_plupload_instance').clearUploader(upBTN.data('rowno'));
		}
		upBTN.removeClass('active');
		upload_container.hide();
	}


	fcfield_file.clearField = function(el, options, config_name)
	{
		var box = jQuery(el).closest('.fcfieldval_container');
		var hasValue = box.find('.hasvalue').val();
		var valcounter = document.getElementById('custom_' + config_name);
		//if (window.console) window.console.log('valcounter: ' + valcounter.value);

		options = options || {};
		options.hide_image = options.hide_image || false;
		options.keep_props = options.keep_props || false;

		box.find('.field-media-wrapper').find('.button-clear').click();
		box.find('.clear-btn').click();

		if (options.hide_image)
		{
			box.find('.fcfield_preview_box').hide();
		}
		else
		{
			//console.log('clear_props');
			fcfield_file.clearFieldUploader(box, config_name);

			box.find('.fc_filedata_txt').val('');
			box.find('.fc_filedata_txt_nowrap').html('');
			box.find('.fc_filedata_txt').removeAttr('data-filename');
			box.find('.fc_filedata_txt').data('filename', null);
			box.find('.hasvalue').val('');
			box.find('.fc_preview_thumb').attr('src', 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');
			box.find('.fc_preview_text').html('');
			box.find('.fc_preview_msg').html('');
			box.find('.fcfield_preview_box').hide();

			box.find('.fc_media_file_box').show(); // media field if it exists

			if (hasValue) valcounter.value = ( valcounter.value=='' || valcounter.value=='1' )  ?  ''  :  parseInt(valcounter.value) - 1;
			//if (window.console) window.console.log('valcounter: ' + valcounter.value);
		}
		if (options.keep_props)
		{
			box.find('input, textarea').val('');
		}
	}

	fcfield_file.clearMediaFile = function(clear_btn, placeholder_path)
	{
		var box = jQuery(clear_btn).closest('.fcfieldval_container');
		box.find('.fc-file-id').val('0');
		box.find('.fc_filedata_txt').val('');
		box.find('.inline-preview-img').attr('src', placeholder_path);
		box.find('.inline-preview-obj').attr('data', placeholder_path);
		box.find('.inline-preview-img').hide();
		box.find('.inline-preview-obj').hide();
	}

	fcfield_file.assignMediaFile = function(value_container_id, filename, file_preview)
	{
		//filename = decodeURIComponent(filename);
		var originalname = filename;
		var displaytitle = '';
		var text_nowrap  = filename;

		var container   = jQuery('#'+value_container_id).closest('.fcfieldval_container');
		var config_name = jQuery('#'+value_container_id).data('config_name');

		var ext      = filename ? filename.split('.').pop() : '';
		var baseName = fcfield_mediafile.getFileBasename(filename);
		container.find('.fc-file-id').val(0);
		container.find('.fc_filedata_storage_name').html(filename);
		container.find('.fc_filedata_txt').val(originalname).removeClass('file_unpublished').blur();
		container.find('.fc_filedata_txt').removeAttr('data-filename');
		container.find('.fc_filedata_txt').data('filename', filename);
		container.find('.fc_filedata_txt_nowrap').html(text_nowrap).show();
		container.find('.fc_filedata_title').val(displaytitle);
		container.find('.fc_preview_thumb').attr('src', file_preview);

		var is_image = file_preview.match(/\.(jpeg|jpg|gif|png|webp)$/) != null;
		if (is_image)
		{
			container.find('.fc_preview_text').html('');
			//container.find('.fcfield_preview_box').show();
			container.find('.inline-preview-img').show();

		}
		else
		{
			container.find('.fc_preview_text').html(ext.toUpperCase());
			container.find('.inline-preview-img').hide();
			if (container.find('.fcfield_preview_box').hasClass('auto'))
			{
				container.find('.fcfield_preview_box').hide();
			}
		}

		container.find('.fc_filetitle').val(filename).blur();
		container.find('.fc_filelang').val('').trigger('change');
		container.find('.fc_filedesc').val('');

		// Increment value counter (which is optionally used as 'required' form element)
		var valcounter = document.getElementById('custom_' + config_name);
		if (valcounter)
		{
			valcounter.value = valcounter.value=='' ? '1' : parseInt(valcounter.value) + 1;
		}

		var remove_obj = container.find('.inlinefile-del');
		remove_obj.removeAttr('checked').trigger('change');

		// Re-validate
		jQuery(valcounter).trigger('blur');
		return true;
	}

	fcfield_file.getFileBasename = function(str) 
	{
		var base = new String(str).substring(str.lastIndexOf('/') + 1); 
		if (base.lastIndexOf(".") != -1)
		{
			base = base.substring(0, base.lastIndexOf("."));
		}
		return base;
	}

	fcfield_file.assignFile = function(value_container_id, file, keep_modal, config_name)
	{
		// Decode php utf8_encode
		for (const key in file) {
			try {
				file[key] = decodeURIComponent(file[key]);
			} catch(err) {
				file[key] = file[key];
			}
		}

		// We use altname (aka title) that is by default (unless modified) same as 'filename_original'
		var originalname = file.filename_original ? file.filename_original : file.filename;
		var displaytitle = file.altname && (file.altname!=file.filename) ? file.altname : '-';
		var text_nowrap  = file.altname && (file.altname!=file.filename) ? file.filename+'<br/>'+file.altname : '';

		var container = jQuery('#'+value_container_id).closest('.fcfieldval_container');
		
		var ext      = file.filename ? file.filename.split('.').pop() : '';
		var baseName = fcfield_file.getFileBasename(file.filename);


		container.find('.fc-file-id').val(file.id);
		container.find('.fc_filedata_storage_name').html(file.filename);
		container.find('.fc_filedata_txt').val(originalname).removeClass('file_unpublished').blur();
		container.find('.fc_filedata_txt').removeAttr('data-filename');
		container.find('.fc_filedata_txt').data('filename', file.filename);
		container.find('.fc_filedata_txt_nowrap').html(text_nowrap).show();
		container.find('.fc_filedata_title').val(displaytitle);

		container.find('.fc_preview_thumb').attr('src', file.preview ? file.preview : 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');

		if (file.preview)
		{
			container.find('.fc_preview_text').html('');
			if (keep_modal != 2)
			{
				container.find('.fcfield_preview_box').show();
				fcfield_file.clearFieldUploader(container, config_name);
			}
		}
		else
		{
			container.find('.fc_preview_text').html(file.ext.toUpperCase());

			if (container.find('.fcfield_preview_box').hasClass('auto'))
			{
				container.find('.fcfield_preview_box').hide();
			}
		}

		container.find('.fc_filetitle').val(file.altname).blur();
		container.find('.fc_filelang').val(file.language).trigger('change');
		container.find('.fc_filedesc').val(file.description);

		// Increment value counter (which is optionally used as 'required' form element)
		var valcounter = document.getElementById('custom_' + config_name);
		if (valcounter)
		{
			valcounter.value = valcounter.value=='' ? '1' : parseInt(valcounter.value) + 1;
		}

		var remove_obj = container.find('.inlinefile-del');
		remove_obj.removeAttr('checked').trigger('change');

		// Close file select modal dialog
		if (!keep_modal && !!fcfield_file.dialog_handle[config_name])
		{
			fcfield_file.dialog_handle[config_name].dialog('close');
		}

		// Re-validate
		jQuery(valcounter).trigger('blur');
		return true;
	}
