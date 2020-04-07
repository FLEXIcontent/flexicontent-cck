	var fcfield_mediafile = {};
	var fcfield_mediafile_base_url = [];
	
	fcfield_mediafile.debugToConsole = [];
	fcfield_mediafile.use_native_apis = [];
	fcfield_mediafile.dialog_handle = [];
	fcfield_mediafile.base_url = [];

	fcfield_mediafile.initValue = function(tagid, config_name)
	{
		//window.console.log(tagid);
		var fnn  = tagid.replace(/-/g, '_');
		var file = jQuery('#custom_' + tagid + '_file-data-txt');

		var updateTimer = function updateTimer()
		{
			var formattedTime = secondsToTimestamp(audio_spectrum.getCurrentTime());
			//jQuery('#fc_mediafile_current_time_' + fnn).text(formattedTime);

			var wave = jQuery('#fc_mediafile_audio_spectrum_' + fnn + ' wave wave');
			if (!wave.find('.fccurrentTimeBox').length)
			{
				wave.append(jQuery('<div class="fccurrentTimeBox" style="position:absolute; z-index: 11; right:0; top: 38%; background: #777; color: white; padding: 4px; opacity: 70%;"></div>'));
			}
			wave.find('.fccurrentTimeBox').html(formattedTime);
		}

		var seekHandler = function seekHandler(position)
		{
			audio_spectrum._position = position;

			// Auto start playback if not already started once
			if (audio_spectrum.backend.isPaused())
			{
				var event = document.createEvent("HTMLEvents");
				event.initEvent("click", true, true);
				event.eventName = "click";
				buttons.play.dispatchEvent(event);
			}
		}

		var secondsToTimestamp = function(seconds)
		{
			seconds = Math.floor(seconds);
			var h = Math.floor(seconds / 3600);
			var m = Math.floor((seconds - (h * 3600)) / 60);
			var s = seconds - (h * 3600) - (m * 60);

			h = h < 10 ? '0' + h : h;
			m = m < 10 ? '0' + m : m;
			s = s < 10 ? '0' + s : s;

			if (h > 0)
			 return h + ':' + m + ':' + s;
			else
			 return m + ':' + s;
		}

		// Imitate SoundCloud's mirror effect on the waveform. Only works on iOS. (Adapted from the wavesurfer.js demo.) 
		//var ctx = document.createElement('canvas').getContext('2d');
		//var linGrad = ctx.createLinearGradient(0, 56, 0, 200);
		//linGrad.addColorStop(0.5, 'rgba(255, 255, 255, 0.88)');
		//linGrad.addColorStop(0.5, 'rgba(183, 183, 183, 0.88)');


		// Create WaveSurfer object
		var audio_spectrum = WaveSurfer.create({
			container: '#fc_mediafile_audio_spectrum_' + fnn,

		    scrollParent: false,
		    //waveColor: linGrad, 
		    progressColor: '#bbbaba',
		    cursorColor: '#ddd',
		    cursorWidth: 2,
		    height: 128,
		    //barWidth: 0.5,
			//barHeight: 1.1,
		    backend: 'MediaElement',
		    normalize: true,

			//backend: 'MediaElement',
			//backend: 'WebAudio',
			//mediaControls: true,
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
			},

			plugins: [
				/*WaveSurfer.cursor.create({
						showTime: true,
						opacity: 1,
						customShowTimeStyle: {
								'background-color': '#000',
								color: '#fff',
								padding: '2px',
								'font-size': '10px'
						}
				})*/
		]
		});


		// Variable to check if song is loaded
		audio_spectrum.loaded = false;

		jQuery('#fc_mediafile_audio_spectrum_' + fnn).data('audio_spectrum', audio_spectrum);

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
		//buttons.play.disabled = true;


		// Redraw the waveform when resizing or changing orientation. Enton Biba http://codepen.io/entonbiba/pen/VPqvME
		var responsiveWave = audio_spectrum.util.debounce(function()
		{
			audio_spectrum.drawBuffer();
		}, 150);
		window.addEventListener('resize', responsiveWave);


		// When window is resized update the player
		/*window.addEventListener('resize', function()
		{
			// Get the current progress according to the cursor position
			var currentProgress = audio_spectrum.getCurrentTime() / audio_spectrum.getDuration();

			// Reset graph
			audio_spectrum.empty();
			audio_spectrum.drawBuffer();

			// Set original position
			audio_spectrum.seekTo(currentProgress);

			// Enable/Disable respectively buttons
			buttons.pause.disabled = true;
			buttons.play.disabled = false;
			buttons.stop.disabled = false;
		}, false);*/


		var loading_timer;

		/*audio_spectrum.on('loading', function (percents, eventTarget)
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
			//window.console.log('Loading file (Wavesurfer JS): ' + percents + ' %');
			//window.console.log(eventTarget);
		});

		audio_spectrum.on('waveform-ready', function()
		{
			//window.console.log('Loading is DONE');
			clearInterval(loading_timer);

			var box = jQuery('#fc_mediafile_audio_spectrum_box_' + fnn)
			box.show();

			// Hide and reset progress bar
			var progressBar = box.find('.progress');
			progressBar.get(0).style.visibility = 'hidden';
			progressBar.find('.bar').get(0).style.width = 0;
		});*/

		audio_spectrum.on('ready', function()
		{
			var box = jQuery('#fc_mediafile_audio_spectrum_box_' + fnn)
			box.show();

			// Hide and reset progress bar
			var progressBar = box.find('.progress');
			progressBar.get(0).style.visibility = 'hidden';
			progressBar.find('.bar').get(0).style.width = 0;

			// Enable buttons
			buttons.play.disabled = false;
			buttons.pause.disabled = false;
			buttons.stop.disabled = false;

			// Start playing after song is loaded
			if (!audio_spectrum.loaded)
			{
				audio_spectrum.loaded = true;
			}

			// Start playing after song is loaded
			if (!!audio_spectrum.start_on_ready)
			{
				audio_spectrum.start_on_ready = false;
				!!audio_spectrum._position ? audio_spectrum.play(audio_spectrum._position * audio_spectrum.getDuration()) : audio_spectrum.play();
				audio_spectrum._position = null; 

				buttons.play.disabled = false;
				buttons.pause.disabled = true;
				buttons.stop.disabled = true;

				buttons.play.style.display = 'none';
				buttons.pause.style.display = 'inline-block';
				buttons.stop.style.display = 'none';
				buttons.load.style.display = 'none';
			}
		});

		/*
		 * Add display of current time
		 */
		audio_spectrum.on('ready', updateTimer);
		audio_spectrum.on('audioprocess', updateTimer);

		// Need to watch for seek in addition to audioprocess as audioprocess doesn't fire (if the audio is paused)
		audio_spectrum.on('seek', seekHandler);


		// Add events of playback buttons
		buttons.play.addEventListener('click', function()
		{
			// Load song when play is pressed
			if (!audio_spectrum.loaded)
			{
				audio_spectrum.start_on_ready = true;
				var isURL = /^(f|ht)tps?:\/\//i.test(file.data('wfpreview'));
				window.console.log('isURL: ' + isURL);
				window.console.log('filename: ' + file.data('wfpreview'));
				window.console.log('Base URL: ' + fcfield_mediafile_base_url[config_name]);
				var peaks = audio_spectrum.backend.peaks || null;
				isURL ? audio_spectrum.load(file.data('wfpreview'), peaks) : audio_spectrum.load(fcfield_mediafile_base_url[config_name] + '/' + file.data('wfpreview'), peaks);
				audio_spectrum.drawBuffer();
			}
			else
			{
				!!audio_spectrum._position ? audio_spectrum.play(audio_spectrum._position * audio_spectrum.getDuration()) : audio_spectrum.play();
				audio_spectrum._position = null; 
			}

			buttons.play.disabled = true;
			buttons.pause.disabled = false;
			buttons.stop.disabled = false;

			buttons.play.style.display = 'none';
			buttons.pause.style.display = 'inline-block';
			buttons.stop.style.display = 'none';
			buttons.load.style.display = 'none';
		}, false);

		buttons.pause.addEventListener('click', function()
		{
			audio_spectrum.pause();
			buttons.pause.disabled = true;
			buttons.play.disabled = false;

			buttons.play.style.display = 'inline-block';
			buttons.pause.style.display = 'none';
			buttons.stop.style.display = 'none';
			buttons.load.style.display = 'none';
		}, false);

		buttons.stop.addEventListener('click', function()
		{
			audio_spectrum.stop();
			buttons.pause.disabled = true;
			buttons.stop.disabled = true;
			buttons.play.disabled = false;

			buttons.play.style.display = 'inline-block';
			buttons.pause.style.display = 'none';
			buttons.stop.style.display = 'none';
			buttons.load.style.display = 'none';
		}, false);

		// Add event of load button to allow loading new files
		buttons.load.addEventListener('click', function()
		{
			if (!!file.data('wfpreview'))
			{
				buttons.pause.disabled = true;
				buttons.stop.disabled = true;
				buttons.play.disabled = true;

				var isURL = /^(f|ht)tps?:\/\//i.test(file.data('wfpreview'));
				//window.console.log('isURL: ' + isURL);
				//window.console.log('filename: ' + file.data('wfpreview'));
				//window.console.log('Base URL: ' + fcfield_mediafile_base_url[config_name]);
				var peaks = audio_spectrum.backend.peaks || null;
				isURL ? audio_spectrum.load(file.data('wfpreview'), peaks) : audio_spectrum.load(fcfield_mediafile_base_url[config_name] + '/' + file.data('wfpreview'), peaks);
			}
		}, false);


		// Load the audio file
		if (!!file.data('wfpreview'))
		{
			var isURL = /^(f|ht)tps?:\/\//i.test(file.data('wfpreview'));
			//window.console.log('isURL: ' + isURL);
			//window.console.log('filename: ' + file.data('wfpreview'));
			//window.console.log('Base URL: ' + fcfield_mediafile_base_url[config_name]);


			// Set peaks
			var mp3Url = isURL ? file.data('wfpreview') : fcfield_mediafile_base_url[config_name] + '/' + file.data('wfpreview');
			var jsonUrl = isURL ? file.data('wfpeaks') : fcfield_mediafile_base_url[config_name] + '/' + file.data('wfpeaks');

			audio_spectrum.util.ajax({
				responseType: 'json',
				url: jsonUrl
			}).on('success', function (response) {
				var data = response.data;
				data.unshift(data[1]);

				// Scale peaks
				audio_spectrum.backend.peaks = data; //.map(p => p/128);

				// Draw peaks
				setTimeout(function () {
					audio_spectrum.drawBuffer();
				}, 100);

				//audio_spectrum.load(mp3Url, data);
			});
		}
	}

	fcfield_mediafile.showUploader = function(field_name_n, config_name)
	{
		var fnn  = field_name_n.replace(/-/g, '_');
		var box = jQuery('#custom_' + field_name_n + '_file-data-txt').closest('.fcfieldval_container');
		box.find('.fc_files_uploader_toggle_btn').click();
	}

	fcfield_mediafile.fileFiltered = function(uploader, file, config_name)
	{
	}


	fcfield_mediafile.fileUploaded = function(uploader, file, result, config_name)
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
		fcfield_mediafile.assignFile(file.targetid, file, 0, config_name);
	}


	fcfield_mediafile.clearFieldUploader = function(box, config_name)
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


	fcfield_mediafile.clearField = function(el, options, config_name)
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
			fcfield_mediafile.clearFieldUploader(box, config_name);
			box.find('.fc_mediafile_audio_spectrum').data('audio_spectrum').empty();

			box.find('.fc_filedata_txt').val('');
			box.find('.fc_filedata_txt_nowrap').html('');
			box.find('.fc_filedata_txt').removeAttr('data-filename').removeAttr('data-wfpreview').removeAttr('data-wfpeaks');
			box.find('.fc_filedata_txt').data('filename', null).data('wfpreview', null).data('wfpeaks', null);
			box.find('.hasvalue').val('');
			box.find('.fc_preview_thumb').attr('src', 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');
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


	fcfield_mediafile.assignFile = function(value_container_id, file, keep_modal, config_name)
	{
		// We use altname (aka title) that is by default (unless modified) same as 'filename_original'
		var originalname = file.filename_original ? file.filename_original : file.filename;
		var displaytitle = file.altname && (file.altname!=file.filename) ? file.altname : '-';
		var text_nowrap  = file.altname && (file.altname!=file.filename) ? file.filename+'<br/>'+file.altname : '';

		var container = jQuery('#'+value_container_id).closest('.fcfieldval_container');

		container.find('.fc_fileid').val(file.id);
		container.find('.fc_filedata_storage_name').html(file.filename);
		container.find('.fc_filedata_txt').val(originalname).removeClass('file_unpublished').blur();
		container.find('.fc_filedata_txt').removeAttr('data-filename');
		container.find('.fc_filedata_txt').data('filename', file.filename);
		container.find('.fc_filedata_txt_nowrap').html(text_nowrap).show();
		container.find('.fc_filedata_title').html(displaytitle);

		container.find('.fc_preview_thumb').attr('src', file.preview ? file.preview : 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');

		if (file.preview)
		{
			if (keep_modal != 2)
			{
				container.find('.fcimg_preview_box').show();
				fcfield_mediafile.clearFieldUploader(container, config_name);
			}
		}

		container.find('.fc_mediafile_audio_spectrum').data('audio_spectrum').empty();
		container.find('.fc_filetitle').val(file.altname).blur();
		container.find('.fc_filelang').val(file.language).trigger('change');
		container.find('.fc_filedesc').val(file.description);

		// Load the audio file
		if (!!file.filename)
		{
			var audio_spectrum = container.find('.fc_mediafile_audio_spectrum').data('audio_spectrum');
			var isURL = /^(f|ht)tps?:\/\//i.test(file.filename);
			isURL ? audio_spectrum.load(file.filename) : audio_spectrum.load(fcfield_mediafile_base_url[config_name] + '/' + file.filename);
		}

		// Increment value counter (which is optionally used as 'required' form element)
		var valcounter = document.getElementById('custom_' + config_name);
		if (valcounter)
		{
			valcounter.value = valcounter.value=='' ? '1' : parseInt(valcounter.value) + 1;
		}

		var remove_obj = container.find('.inlinefile-del');
		remove_obj.removeAttr('checked').trigger('change');

		// Close file select modal dialog
		if (!keep_modal && !!fcfield_mediafile.dialog_handle[config_name])
		{
			fcfield_mediafile.dialog_handle[config_name].dialog('close');
		}

		// Re-validate
		jQuery(valcounter).trigger('blur');
		return true;
	}
