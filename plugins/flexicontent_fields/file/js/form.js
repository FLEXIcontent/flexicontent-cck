	var fcfield_file = {};
	
	fcfield_file.debugToConsole = [];
	fcfield_file.use_native_apis = [];
	fcfield_file.dialog_handle = [];
	fcfield_file.pdf_thumb_url = '';

	// Native JS toggles for the icon-only toolbar panels
	fcfield_file.toggleFields = function(element_id_n)
	{
		var box = document.querySelector('.fc-file-value-props[data-name="' + element_id_n + '"]');
		if (!box) return;

		var btn = document.querySelector('.fc-file-edit-btn[data-for="' + element_id_n + '"]');
		var isHidden = (window.getComputedStyle(box).display === 'none');
		box.style.display = isHidden ? 'block' : 'none';
		if (btn) btn.classList.toggle('active', isHidden);
	}

	// Kept for backwards compatibility with the old media-URL button
	fcfield_file.toggleMediaURL = function(element_id_n)
	{
		fcfield_file.toggleFields(element_id_n);
	}

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
			fcfield_file.setFileInfo(box, '', '', {});
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

	// Show original file name and title (altname) of a value, the other data (url, description, custom1/2, ext, size) go into the tooltip
	// data: object with the other data, merged into the current data if merge is true, otherwise replacing them
	fcfield_file.setFileInfo = function(container, original, title, data, merge)
	{
		original = original || '';
		title    = title || '';
		container.find('.fc_filedata_title').val(original);
		container.find('.fc_filedata_alttitle').val(title);

		container.find('.fc-file-info').each(function()
		{
			var box = jQuery(this), info = {};
			if (merge)
			{
				try { info = JSON.parse(box.attr('data-fc-info') || '{}') || {}; } catch (e) { info = {}; }
			}
			info = jQuery.extend(info, data || {});
			info.title = title;
			box.attr('data-fc-info', JSON.stringify(info));
			fcfield_file.updateTip(box);
		});
	};


	/*
	 * Tooltip (the standard Bootstrap one, as used by the other form labels) with the data of the file:
	 * Title, URL link, Description, custom1/custom2, MIME type / Size
	 */
	fcfield_file.mimes = {
		pdf: 'application/pdf', zip: 'application/zip', rar: 'application/vnd.rar', '7z': 'application/x-7z-compressed',
		gz: 'application/gzip', tar: 'application/x-tar', dmg: 'application/x-apple-diskimage',
		exe: 'application/vnd.microsoft.portable-executable', msi: 'application/x-msi', apk: 'application/vnd.android.package-archive',
		doc: 'application/msword', docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		xls: 'application/vnd.ms-excel', xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		ppt: 'application/vnd.ms-powerpoint', pptx: 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		odt: 'application/vnd.oasis.opendocument.text', ods: 'application/vnd.oasis.opendocument.spreadsheet',
		txt: 'text/plain', csv: 'text/csv', html: 'text/html', htm: 'text/html', css: 'text/css', js: 'text/javascript',
		json: 'application/json', xml: 'application/xml', rtf: 'application/rtf',
		jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', gif: 'image/gif', webp: 'image/webp', svg: 'image/svg+xml',
		bmp: 'image/bmp', ico: 'image/x-icon', tif: 'image/tiff', tiff: 'image/tiff', avif: 'image/avif',
		mp3: 'audio/mpeg', wav: 'audio/wav', ogg: 'audio/ogg', m4a: 'audio/mp4', flac: 'audio/flac',
		mp4: 'video/mp4', webm: 'video/webm', mov: 'video/quicktime', avi: 'video/x-msvideo', mkv: 'video/x-matroska',
		epub: 'application/epub+zip', iso: 'application/x-iso9660-image'
	};

	fcfield_file.formatSize = function(size)
	{
		if (size === '' || size === null || typeof size === 'undefined') return '';
		if (isNaN(size)) return String(size);   // already formatted text, e.g. "12 KB"
		size = parseFloat(size);
		if (!size) return '';
		if (size < 1024) return size + ' B';
		if (size < 1048576) return (size / 1024).toFixed(size < 10240 ? 1 : 0).replace(/\.0$/, '') + ' KB';
		return (size / 1048576).toFixed(1).replace(/\.0$/, '') + ' MB';
	};

	// HTML of the tooltip, empty string if there is nothing to show
	fcfield_file.buildTipHtml = function(box)
	{
		var info = {}, labels = {};
		try { info   = JSON.parse(box.attr('data-fc-info') || '{}') || {}; } catch (e) {}
		try { labels = JSON.parse(box.attr('data-fc-custom-labels') || '{}') || {}; } catch (e) {}

		var plain = function(html, max)
		{
			var txt = jQuery('<div></div>').html(String(html || '')).text().replace(/\s+/g, ' ').trim();
			return txt.length > max ? txt.substr(0, max) + '\u2026' : txt;
		};

		var root = jQuery('<div class="fc-file-tip-box"></div>');
		var add = function(cls, text, label)
		{
			if (!text) return;
			var line = jQuery('<div></div>').addClass(cls);
			if (label) line.append(jQuery('<b></b>').text(label + ': '));
			root.append(line.append(document.createTextNode(text)));
		};

		add('fc-file-tip-title', plain(info.title, 200));
		add('fc-file-tip-url', info.url ? String(info.url) : '');
		add('fc-file-tip-desc', plain(info.description, 300));
		add('fc-file-tip-custom', plain(info.custom1, 300), labels[1] || '');
		add('fc-file-tip-custom', plain(info.custom2, 300), labels[2] || '');
		add('fc-file-tip-meta', [fcfield_file.mimes[String(info.ext || '').toLowerCase()] || '', fcfield_file.formatSize(info.size)].filter(Boolean).join(' \u00b7 '));

		return root.children().length ? jQuery('<div></div>').append(root).html() : '';
	};

	fcfield_file.updateTip = function(box)
	{
		if (typeof jQuery.fn.tooltip !== 'function') return;
		var html = fcfield_file.buildTipHtml(box);

		box.find('input.fc-file-info-val').each(function()
		{
			var el = jQuery(this);
			el.tooltip('dispose');
			el.removeAttr('title').removeAttr('data-bs-original-title');
			if (html) el.attr('title', html).tooltip({html: true, container: 'body', trigger: 'hover'});
		});
	};

	document.addEventListener('DOMContentLoaded', function()
	{
		if (typeof jQuery === 'undefined') return;
		jQuery('.fc-file-info').each(function() { fcfield_file.updateTip(jQuery(this)); });
	});

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
		fcfield_file.setFileInfo(container, originalname, '', {url: /^(https?:)?\/\//i.test(filename || '') ? filename : '', description: '', custom1: '', custom2: '', ext: ext, size: ''});
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
		fcfield_file.setFileInfo(container, originalname, file.altname && (file.altname!=file.filename) ? file.altname : '', {
			url: parseInt(file.url, 10) === 1 ? file.filename : '',
			description: file.description || '',
			custom1: file.custom1 || '',
			custom2: file.custom2 || '',
			ext: file.ext || '',
			size: typeof file.size !== 'undefined' ? file.size : ''
		});

		var is_pdf      = (file.ext && String(file.ext).toLowerCase() === 'pdf') || /\.pdf$/i.test(file.filename || '');
		var pdf_preview = (is_pdf && !file.preview && fcfield_file.pdf_thumb_url && file.id)
			? fcfield_file.pdf_thumb_url.replace('__ID__', file.id)
			: '';
		var has_preview = !!file.preview || !!pdf_preview;

		container.find('.fc_preview_thumb')
			.attr('src', file.preview ? file.preview : (pdf_preview || 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs='))
			.css('object-fit', pdf_preview ? 'contain' : '')
			.css('padding', pdf_preview ? '4px' : '')
			.css('background', pdf_preview ? '#fff' : '');

		if (has_preview)
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
			container.find('.fc_preview_text').html(String(file.ext || ext).toUpperCase());

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

	/**
	 * Open the file edit form (file manager record form) of the file assigned to a value, inside a modal dialog
	 */
	fcfield_file.openEditDialog = function(btn, url_tpl, checkin_url_tpl, title, msg_nofile)
	{
		var id = parseInt(jQuery(btn).closest('.fcfieldval_container').find('input.fc-file-id').first().val(), 10);

		if (!id)
		{
			alert(msg_nofile);
			return false;
		}

		// Release the lock of the file record when the dialog is closed (saving also checks the record in)
		var checkin = function()
		{
			try {
				fetch(checkin_url_tpl.replace('__file_id__', id), {credentials: 'same-origin', redirect: 'manual'}).catch(function(){});
			} catch (e) {}
		};

		fcfield_file.dialog_handle['_edit'] = fc_showDialog(url_tpl.replace('__file_id__', id), 'fc_modal_popup_container', 0, 0, 0, checkin, {title: title, paddingW: 10, paddingH: 16});
		return false;
	}


	/**
	 * Refresh the displayed data of every value (of any file field in the form) that uses the given file,
	 * called by the file edit form (inside the modal) after the file record is saved
	 */
	fcfield_file.refreshFileRows = function(file)
	{
		var file_id = parseInt(file.id, 10);
		if (!file_id) return;

		jQuery('input.fc-file-id').filter(function() { return parseInt(this.value, 10) === file_id; }).each(function()
		{
			var container = jQuery(this).closest('.fcfieldval_container');
			var filename  = file.filename || '';
			var altname   = file.altname || '';
			var original  = file.filename_original ? file.filename_original : filename;

			container.find('.fc_filedata_storage_name').text(filename);
			container.find('.fc_filedata_txt').val(filename).attr('data-filename', filename).data('filename', filename);
			container.find('.fc_filedata_txt_nowrap').empty().append(document.createTextNode(filename)).append('<br/>').append(document.createTextNode(altname));
			var upd = {description: file.description || ''};
			if (typeof file.custom1 !== 'undefined') upd.custom1 = file.custom1 || '';
			if (typeof file.custom2 !== 'undefined') upd.custom2 = file.custom2 || '';
			fcfield_file.setFileInfo(container, original, altname, upd, true);
			container.find('.fc_filetitle').val(altname).blur();
			container.find('.fc_filedesc').val(file.description || '');
			if (typeof file.custom1 !== 'undefined') container.find('.fc_filecustom1').val(file.custom1 || '');
			if (typeof file.custom2 !== 'undefined') container.find('.fc_filecustom2').val(file.custom2 || '');

			// Extension text of the preview box (only when no image preview is shown)
			var ext = (file.ext || '').toString().toUpperCase();
			if (ext && !(container.find('.fc_preview_thumb').attr('src') || '').match(/phpThumb/i))
			{
				container.find('.fc_preview_text').text(ext);
			}
		});
	}
