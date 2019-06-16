	var fcview_mediafile = {};
	var fcview_mediafile_base_url = [];

	fcview_mediafile.debugToConsole = [];
	fcview_mediafile.use_native_apis = [];
	fcview_mediafile.dialog_handle = [];
	fcview_mediafile.base_url = [];

	fcview_mediafile.initValue = function(field_name_n, config_name)
	{
		window.console.log(field_name_n);
		var fnn  = field_name_n.replace(/-/g, '_');
		var file = jQuery('#fcview_' + field_name_n + '_file-data-txt');
		var file_val = file.data('value');

		// Create WaveSurfer object
		var audio_spectrum = WaveSurfer.create({
			container: '#fc_mediafile_audio_spectrum_' + fnn,
			waveColor: 'violet',
			progressColor: 'purple',
			//backend: 'MediaElement',
			//backend: 'WebAudio',
			mediaControls: true,
			xhr: {
				format: 'jsonp',
				requestHeaders: [
					{
						key: "crossDomain",
						value: true
						//key: "Origin",
						//value: window.location.protocol + '//' + window.location.host + fc_root_uri
					}
				]
			}
		});


		// Get control buttons
		var controls = jQuery('#fc_mediafile_controls_' + fnn);
		var buttons = {
			load:  controls.find('.loadBtn').get(0),
			play:  controls.find('.playBtn').get(0),
			pause: controls.find('.pauseBtn').get(0),
			stop:  controls.find('.stopBtn').get(0)
		}

		buttons.pause.disabled = true;
		buttons.stop.disabled = true;
		buttons.play.disabled = true;


		// When window is resized update the player
		window.addEventListener('resize', function()
		{
			// Get the current progress according to the cursor position
			/*var currentProgress = audio_spectrum.getCurrentTime() / audio_spectrum.getDuration();

			// Reset graph
			audio_spectrum.empty();
			audio_spectrum.drawBuffer();

			// Set original position
			audio_spectrum.seekTo(currentProgress);

			// Enable/Disable respectively buttons
			buttons.pause.disabled = true;
			buttons.play.disabled = false;
			buttons.stop.disabled = false;*/
		}, false);


		var loading_timer;

		audio_spectrum.on('loading', function (percents, eventTarget)
		{
			var box = jQuery('#fc_mediafile_audio_spectrum_box_' + fnn)
			box.show();

			var progressBar = box.find('.progress');
			progressBar.get(0).style.visibility = 'visible';
			progressBar.find('.bar').get(0).style.width = (percents / 2) + '%';

			if (percents >= 100)
			{
				var steps = 200;
				var frame = 0;
				loading_timer = setInterval(function ()
				{
					progressBar.find('.bar').get(0).style.width = 50 + (frame * (50 / steps)) + '%';
					frame += 1;
					if (frame >= steps) clearInterval(loading_timer);
				}, 20);
			}
			window.console.log('loading');
			window.console.log(percents);
			window.console.log(eventTarget);
		});


		audio_spectrum.on('ready', function()
		{
			window.console.log('is ready');
			clearInterval(loading_timer);

			var box = jQuery('#fc_mediafile_audio_spectrum_box_' + fnn)
			box.show();

			var progressBar = box.find('.progress');
			progressBar.get(0).style.visibility = 'hidden';
			progressBar.find('.bar').get(0).style.width = 0;

			buttons.pause.disabled = true;
			buttons.stop.disabled = true;
			buttons.play.disabled = false;
		});

		// Add events of playback buttons
		buttons.play.addEventListener('click', function()
		{
			audio_spectrum.play();
			buttons.pause.disabled = false;
			buttons.stop.disabled = false;
			buttons.play.disabled = true;
		}, false);

		buttons.pause.addEventListener('click', function()
		{
			audio_spectrum.pause();
			buttons.pause.disabled = true;
			buttons.play.disabled = false;
		}, false);

		buttons.stop.addEventListener('click', function()
		{
			audio_spectrum.stop();
			buttons.pause.disabled = true;
			buttons.stop.disabled = true;
			buttons.play.disabled = false;
		}, false);

		// Add event of load button to allow loading new files
		buttons.load.addEventListener('click', function()
		{
			if (!!file_val)
			{
				buttons.pause.disabled = true;
				buttons.stop.disabled = true;
				buttons.play.disabled = true;

				var isURL = /^(f|ht)tps?:\/\//i.test(file_val);
				isURL ? audio_spectrum.load(file_val) : audio_spectrum.load(fcview_mediafile_base_url[config_name] + '/' + file_val);
			}
		}, false);

		// Load the audio file
		if (!!file_val)
		{
			var isURL = /^(f|ht)tps?:\/\//i.test(file_val);
			isURL ? audio_spectrum.load(file_val) : audio_spectrum.load(fcview_mediafile_base_url[config_name] + '/' + file_val);
		}
	}


	fcview_mediafile.fileFiltered = function(uploader, file, config_name)
	{
	}


	fcview_mediafile.fileUploaded = function(uploader, file, result, config_name)
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
		file.targetid    = jQuery(uploader.settings.container).closest('.fcfieldval_container').find('.existingname').attr('id');
		file.preview_url = jQuery(uploader.settings.container).find('.plupload_img_preview > img').attr('src');
		fcview_mediafile.assignFile(file.targetid, file.filename, file.preview_url, 0, config_name);
	}


	fcview_mediafile.clearFieldUploader = function(box, config_name)
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


	fcview_mediafile.clearField = function(el, options, config_name)
	{
		var box = jQuery(el).closest('.fcfieldval_container');
		var hasValue = box.find('.hasvalue').val();
		var valcounter = document.getElementById('custom_' + config_name);
		//if (window.console) window.console.log('valcounter: ' + valcounter.value);

		options = options || {};
		options.hide_image = options.hide_image || false;
		options.keep_props = options.keep_props || false;

		if (options.hide_image)
		{
			box.find('.fcimg_preview_box').hide();
		}
		else
		{
			fcview_mediafile.clearFieldUploader(box, config_name);

			box.find('.originalname').val('');
			box.find('.existingname').val('');
			box.find('.hasvalue').val('');
			box.find('.preview_image').attr('src', 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');
			box.find('.fcimg_preview_msg').html(' ');
			box.find('.fcimg_preview_box').show();

			if (hasValue) valcounter.value = ( valcounter.value=='' || valcounter.value=='1' )  ?  ''  :  parseInt(valcounter.value) - 1;
			//if (window.console) window.console.log('valcounter: ' + valcounter.value);
		}
		if (options.keep_props)
		{
			box.find('input, textarea').val('');
		}
	}


	fcview_mediafile.assignFile = function(value_container_id, file, keep_modal, config_name)
	{
		// We use altname (aka title) that is by default (unless modified) same as 'filename_original'
		var originalname = file.filename_original ? file.filename_original : file.filename;
		var displaytitle = file.altname && (file.altname!=file.filename) ? file.altname : '-';
		var text_nowrap  = file.altname && (file.altname!=file.filename) ? file.filename+'<br/>'+file.altname : '';

		window.console.log(value_container_id);
		window.console.log(jQuery('#'+value_container_id).length);

		var container = jQuery('#'+value_container_id).closest('.fcfieldval_container');

		container.find('.fc_fileid').val(file.id);
		container.find('.fc_filedata_storage_name').html(file.filename);
		container.find('.fc_filedata_txt').val(originalname).removeClass('file_unpublished').blur();
		container.find('.fc_filedata_txt_nowrap').html(text_nowrap).show();
		container.find('.fc_filedata_title').html(displaytitle);

		container.find('.fc_preview_thumb').attr('src', file.preview ? file.preview : 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');

		if (file.preview)
		{
			if (keep_modal != 2)
			{
				container.find('.fcimg_preview_box').show();
				fcfield_file.clearFieldUploader(container, config_name);
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
		if (!keep_modal && !!fcview_mediafile.dialog_handle[config_name])
		{
			fcview_mediafile.dialog_handle[config_name].dialog('close');
		}

		// Re-validate
		jQuery(valcounter).trigger('blur');
		return true;
	}
