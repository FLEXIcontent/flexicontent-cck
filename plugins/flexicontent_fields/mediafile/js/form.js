class fc_Waveform_LazyLoad
{
	constructor(element, options)
	{
		this.options = {
			root: null,
			rootMargin: '0px 0px',
			threshold: 0.25,
			...options,
		};
		this.element = element;
		this.resources = this.element.querySelectorAll('.fc_mediafile_audio_spectrum_box');

		this.init();
	}

	init() {
		const assetsObserver = new IntersectionObserver(
			this._loadCallback,
			this.options
		);

		this._bindEvents(assetsObserver);

		this.resources.forEach(resource =>
		{
			window.console.log('Observing: ' + resource.id);
			assetsObserver.observe(resource);
		});
	}

	_loadCallback(entries, assetsObserver)
	{
		entries.filter(entry => entry.isIntersecting).forEach(entry =>
		{
			window.console.log('Loading: ' + entry.target.id);
			fcfield_mediafile.lazyLoadAsset(entry.target);
			assetsObserver.unobserve(entry.target);
		});
	}

	_bindEvents(obj)
	{
		// Not working in SAFARI !!!
	}
}

	// Local debounce utility — WaveSurfer.util.debounce was removed in v7
	var fc_debounce = function(func, wait)
	{
		var timeout;
		return function() {
			var context = this, args = arguments;
			clearTimeout(timeout);
			timeout = setTimeout(function() { func.apply(context, args); }, wait);
		};
	};

	var fcfield_mediafile = {};
	var fcfield_mediafile_base_url = [];
	var audio_spectrum_arr = [];
	var audio_spectrum_conf = [];

	fcfield_mediafile.debugToConsole = [];
	fcfield_mediafile.use_native_apis = [];
	fcfield_mediafile.dialog_handle = [];
	fcfield_mediafile.base_url = [];

	fcfield_mediafile.lazyLoadAsset = function(asset)
	{
		fcfield_mediafile.initValue(asset.getAttribute('data-fc_tagid'), asset.getAttribute('data-fc_fname'));
	}

	fcfield_mediafile.initValue = function(tagid, config_name)
	{
		var fnn  = tagid.replace(/-/g, '_');
		var file = jQuery('#custom_' + tagid + '_file-data-txt');
		var loading_timer = null, progress_timer = null;

		var box    = jQuery('#fc_mediafile_audio_spectrum_box_' + fnn);
		var slider = document.querySelector('#fc_mediafile_slider_' + fnn);

		var _progressBar = box.find('.fc_mediafile_audio_spectrum_progressbar');
		if (_progressBar.length)
		{
			var bar          = _progressBar.find('.bar').get(0);
			var barText      = _progressBar.find('.barText').get(0);
			var progressBar  = box.find('.fc_mediafile_audio_spectrum_progressbar').get(0);
		}
		else
		{
			progressBar = false;
		}

		var keyPressedDown = function(key_event)
		{
			var k = key_event.keyCode;

			//space = 32, enter = 13, left = 37, up = 38, right = 39, down = 40
			if (k == 32 || k == 13 || k == 37 || k == 38 || k == 39 || k == 40)
			{
				key_event.preventDefault();

				if (k == 32 || k == 13)
				{
					var event = document.createEvent("HTMLEvents");
					event.initEvent("click", true, true);
					event.eventName = "click";
					// v7: isPlaying() replaces !backend.isPaused()
					audio_spectrum.isPlaying() ? buttons.pause.dispatchEvent(event) : buttons.play.dispatchEvent(event);
				}
				else if (k == 37 || k == 39)
				{
					audio_spectrum.skip(k == 37 ? -10 : 10);
				}
				else if (k == 38 || k == 40)
				{
					var vol = audio_spectrum.getVolume();
					audio_spectrum.setVolume(k == 38 ? (vol < 0.9 ? vol + 0.1 : 1) : (vol > 0.1 ? vol - 0.1 : 0));
				}

				return false;
			}
		}

		var updateTimer = function()
		{
			var formattedTime = secondsToTimestamp(audio_spectrum.getCurrentTime());

			// v7: WaveSurfer no longer creates <wave> elements; position timer inside the container
			var container = document.querySelector('#fc_mediafile_audio_spectrum_' + fnn);
			if (!container) return;

			if (!container.querySelector('.fccurrentTimeBox'))
			{
				container.insertAdjacentHTML('beforeend', '<div class="fccurrentTimeBox"></div>');
			}

			var timer    = container.querySelector('.fccurrentTimeBox');
			var duration = audio_spectrum.getDuration() || 1;
			var progress = audio_spectrum.getCurrentTime() / duration;
			var width    = Math.round(progress * container.offsetWidth);

			timer.innerHTML = formattedTime;
			timer.style.left = width < timer.offsetWidth ? (width + 'px') : ((width - timer.offsetWidth - 2) + 'px');
			timer.style.borderRadius = width < timer.offsetWidth ? '0 8px 8px 0' : '8px 0 0 8px';
		}

		// v7: 'interaction' event fires with newTime in seconds (v6 'seek' fired with 0-1 progress)
		var seekHandler = function(newTime)
		{
			var duration = audio_spectrum.getDuration() || 1;
			audio_spectrum._position_ = newTime / duration;

			if (!audio_spectrum.isPlaying())
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

		var updateProgressBar = function (percent, eventTarget)
		{
			if (!progressBar) return;

			var factor = 100;

			barText.innerHTML = 'Loading : ' + percent + ' %';
			progressBar.style.opacity = '1';
			progressBar.style.visibility = 'visible';

			if (percent >= 100 && factor < 100)
			{
				var steps = 40;
				var frame = 0;
				loading_timer = setInterval(function ()
				{
					barText.innerHTML = 'Loading : ' + percent + ' %';
					frame += 1;
					if (frame >= steps)
					{
						clearInterval(loading_timer);
						loading_timer = 0;
					}
				}, 50);
			}
		}

		var stopProgressBar = function (percent)
		{
			if (!progressBar) return;

			if (loading_timer) clearInterval(loading_timer);
			if (progress_timer) clearInterval(progress_timer);

			loading_timer = null;
			progress_timer = null;

			if (!!stopProgressBar.stopping) return;
			stopProgressBar.stopping = 1;

			if (!!percent)
			{
				barText.innerHTML = 'Loading : ' + percent + ' %';
				progressBar.style.opacity = '0.75';

				setTimeout(function () {
					stopProgressBar.stopping = 0;
					progressBar.style.visibility = 'hidden';
					barText.innerHTML = '';
				}, 750);
			}
			else
			{
				stopProgressBar.stopping = 0;
				progressBar.style.visibility = 'hidden';
				bar.style.width = 0;
				barText.innerHTML = '';
			}
		}

		var dummyProgress = function ()
		{
			if (!progressBar) return;

			var percent = 0;
			if (!!!audio_spectrum.loaded)
			{
				progress_timer = setInterval(function ()
				{
					percent = percent + 2;
					updateProgressBar(percent);

					if (percent == 100)
					{
						clearInterval(progress_timer);
						progress_timer = 0;
					}
				}, 50);
			}
		}

		var loadFile = function ()
		{
			var isURL = /^(f|ht)tps?:\/\//i.test(file.data('wfpreview'));
			var url   = isURL ? file.data('wfpreview') : fcfield_mediafile_base_url[config_name] + '/' + file.data('wfpreview');
			// v7: load() renders waveform and loads audio; pass peaks only when available
			if (audio_spectrum._saved_peaks_) {
				audio_spectrum.load(url, [audio_spectrum._saved_peaks_]);
			} else {
				audio_spectrum.load(url);
			}
			audio_spectrum._loading_started_ = true;
		}


		// Create WaveSurfer object (v7 API)
		var audio_spectrum = WaveSurfer.create({
			container: '#fc_mediafile_audio_spectrum_' + fnn,
			normalize:  true,
			fillParent: true,
			interact:   true,

			waveColor:     audio_spectrum_conf[config_name]['waveColor'],
			progressColor: audio_spectrum_conf[config_name]['progressColor'],
			cursorColor:   audio_spectrum_conf[config_name]['cursorColor'],
			cursorWidth:   parseInt(audio_spectrum_conf[config_name]['cursorWidth']) || 2,
			height:        128,

			// v7 removed: backend, mediaControls, xhr, scrollParent, autoCenter, hideScrollbar, plugins
		});


		if (!!slider) slider.oninput = fc_debounce(function()
		{
			window.console.log(slider.value);
			var zoomLevel = parseInt(slider.value);
			window.console.log('Zooming to: ' + zoomLevel);
			audio_spectrum.zoom(zoomLevel);
			updateTimer();
		}, 200);


		// Register new player to known players array
		audio_spectrum_arr[audio_spectrum_arr.length] = audio_spectrum;

		audio_spectrum.loaded            = false;
		audio_spectrum._loading_started_ = false;

		audio_spectrum._dummyProgress     = dummyProgress;
		audio_spectrum._stopProgressBar   = stopProgressBar;
		audio_spectrum._updateProgressBar = updateProgressBar;

		jQuery('#fc_mediafile_audio_spectrum_' + fnn).data('audio_spectrum', audio_spectrum);

		// v7 polyfill for empty() — used by clearField() to reset the waveform display
		audio_spectrum.empty = function()
		{
			try { audio_spectrum.stop(); } catch(e) {}
			audio_spectrum.loaded            = false;
			audio_spectrum._loading_started_ = false;
			audio_spectrum._saved_peaks_     = null;
			var container = document.querySelector('#fc_mediafile_audio_spectrum_' + fnn);
			if (container)
			{
				container.querySelectorAll('canvas').forEach(function(c) {
					c.getContext('2d').clearRect(0, 0, c.width, c.height);
				});
			}
		};

		// Get control buttons
		var controls = jQuery('#fc_mediafile_controls_' + fnn);
		var buttons = {
			load:  controls.find('.loadBtn').get(0),
			play:  controls.find('.playBtn').get(0),
			pause: controls.find('.pauseBtn').get(0),
			stop:  controls.find('.stopBtn').get(0)
		}

		audio_spectrum._buttons_ = buttons;

		buttons.pause.disabled = true;
		buttons.stop.disabled  = true;

		// v7: WaveSurfer handles resize automatically via ResizeObserver — no manual redraw needed


		audio_spectrum.on('loading', function (percent)
		{
			// fires during audio file fetch (0–100)
		});

		// v7: 'decode' replaces 'waveform-ready'
		audio_spectrum.on('decode', function()
		{
		});

		audio_spectrum.on('finish', function()
		{
			audio_spectrum.seekTo(0);
			audio_spectrum.pause();
			buttons.pause.classList.remove('is_active');
			buttons.play.focus();

			buttons.pause.disabled = true;
			buttons.play.disabled  = false;

			buttons.play.style.display  = 'inline-block';
			buttons.pause.style.display = 'none';
			buttons.stop.style.display  = 'none';
			buttons.load.style.display  = 'none';
		});

		audio_spectrum.on('ready', function()
		{
			buttons.play.disabled  = false;
			buttons.pause.disabled = false;
			buttons.stop.disabled  = false;

			if (!audio_spectrum.loaded)
			{
				audio_spectrum.loaded = true;
			}

			if (!!audio_spectrum.start_on_ready)
			{
				audio_spectrum.start_on_ready = false;

				// v7: play() no longer accepts a start-time argument; use seekTo() first
				if (!!audio_spectrum._position_)
				{
					audio_spectrum.seekTo(audio_spectrum._position_);
				}
				audio_spectrum.play();
				audio_spectrum._position_ = null;

				buttons.play.disabled  = true;
				buttons.pause.disabled = false;
				buttons.stop.disabled  = false;

				buttons.pause.classList.add('is_active');

				buttons.play.style.display  = 'none';
				buttons.pause.style.display = 'inline-block';
				buttons.stop.style.display  = 'none';
				buttons.load.style.display  = 'none';
			}
		});

		audio_spectrum.on('ready', updateTimer);
		// v7: 'timeupdate' replaces 'audioprocess'
		audio_spectrum.on('timeupdate', updateTimer);

		// v7: 'interaction' replaces 'seek'; callback receives newTime in seconds, not 0-1 progress
		audio_spectrum.on('interaction', seekHandler);

		audio_spectrum.on('error', function(e) {
			console.warn(e);
		});

		box.get(0).addEventListener("keydown", function (event) {
			return keyPressedDown(event);
		}, false);
		box.get(0).addEventListener("keydown", function (event) {
			return keyPressedDown(event);
		}, false);

		buttons.play.addEventListener("keydown", function (event) {
			return keyPressedDown(event);
		}, false);
		buttons.pause.addEventListener("keydown", function (event) {
			return keyPressedDown(event);
		}, false);

		// Add events of playback buttons
		buttons.play.addEventListener('click', function()
		{
			if (!!!file.data('wfpreview'))
			{
				alert(Joomla.JText._('FLEXI_PLEASE_UPLOAD_A_FILE'));
				return;
			}

			// Stop all other known players
			var i;
			for (i = 0; i < audio_spectrum_arr.length; i++)
			{
				// v7: isPlaying() replaces !backend.isPaused()
				if (audio_spectrum_arr[i].isPlaying())
				{
					var event = document.createEvent("HTMLEvents");
					event.initEvent("click", true, true);
					event.eventName = "click";

					audio_spectrum_arr[i]._noBtnFocus_ = 1;
					audio_spectrum_arr[i]._buttons_.pause.dispatchEvent(event);
					audio_spectrum_arr[i]._noBtnFocus_ = null;
				}
			}

			if (!audio_spectrum.loaded)
			{
				audio_spectrum.start_on_ready = true;
				// Only call loadFile() if load() hasn't been triggered yet (peaks AJAX may have done it)
				if (!audio_spectrum._loading_started_)
				{
					loadFile();
				}
			}
			else
			{
				// v7: seekTo(0-1 progress) then play()
				if (!!audio_spectrum._position_)
				{
					audio_spectrum.seekTo(audio_spectrum._position_);
					audio_spectrum._position_ = null;
				}
				audio_spectrum.play();
			}

			if (!!slider) slider.parentNode.style.display    = 'inline-block';
			if (!!slider) slider.parentNode.style.visibility = 'visible';
			buttons.play.disabled  = true;
			buttons.pause.disabled = false;
			buttons.stop.disabled  = false;

			buttons.pause.classList.add('is_active');

			buttons.play.style.display  = 'none';
			buttons.pause.style.display = 'inline-block';
			buttons.stop.style.display  = 'none';
			buttons.load.style.display  = 'none';

			buttons.pause.focus();
		}, false);

		buttons.pause.addEventListener('click', function()
		{
			audio_spectrum.pause();
			buttons.pause.disabled = true;
			buttons.play.disabled  = false;

			buttons.pause.classList.remove('is_active');

			buttons.play.style.display  = 'inline-block';
			buttons.pause.style.display = 'none';
			buttons.stop.style.display  = 'none';
			buttons.load.style.display  = 'none';

			if (!!!audio_spectrum._noBtnFocus_) buttons.play.focus();
		}, false);

		buttons.stop.addEventListener('click', function()
		{
			audio_spectrum.stop();
			buttons.pause.disabled = true;
			buttons.stop.disabled  = true;
			buttons.play.disabled  = false;

			buttons.pause.classList.remove('is_active');

			buttons.play.style.display  = 'inline-block';
			buttons.pause.style.display = 'none';
			buttons.stop.style.display  = 'none';
			buttons.load.style.display  = 'none';

			buttons.play.focus();
		}, false);

		buttons.load.addEventListener('click', function()
		{
			if (!!file.data('wfpreview'))
			{
				buttons.pause.disabled = true;
				buttons.stop.disabled  = true;
				buttons.play.disabled  = true;

				loadFile();
			}

			buttons.play.focus();
		}, false);


		// Render waveform from pre-computed peaks (if peaks JSON is available)
		if (!!file.data('wfpreview') && !!file.data('wfpeaks'))
		{
			var isURL   = /^(f|ht)tps?:\/\//i.test(file.data('wfpreview'));
			var mp3Url  = isURL ? file.data('wfpreview') : fcfield_mediafile_base_url[config_name] + '/' + file.data('wfpreview');
			var jsonUrl = isURL ? file.data('wfpeaks')   : fcfield_mediafile_base_url[config_name] + '/' + file.data('wfpeaks');

			updateProgressBar(0);

			jQuery.ajax({
				url:      jsonUrl,
				dataType: 'json',
				data:     { format: 'json' },

				progress: function(t)
				{
					var e = t.lengthComputable ? t.loaded / t.total : t.loaded / (t.loaded + 1e6);
					updateProgressBar(Math.round(100 * e), null);
				},

				success: function(response)
				{
					var data = response.data;
					data.unshift(data[1]);

					// v7: store peaks for reuse; wrap in array for per-channel format
					audio_spectrum._saved_peaks_ = data;

					// v7: load() with peaks renders waveform immediately while audio loads in background
					audio_spectrum.load(mp3Url, [data]);
					audio_spectrum._loading_started_ = true;

					stopProgressBar(100);
				}
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
			fcfield_mediafile.clearFieldUploader(box, config_name);
			// v7: empty() is a polyfill added to each instance in initValue
			box.find('.fc_mediafile_audio_spectrum').data('audio_spectrum').empty();

			box.find('.fc_filedata_txt').val('');
			box.find('.fc_filedata_txt_nowrap').html('');
			box.find('.fc_filedata_txt').removeAttr('data-filename').removeAttr('data-wfpreview').removeAttr('data-wfpeaks');
			box.find('.fc_filedata_txt').data('filename', null).data('wfpreview', null).data('wfpeaks', null);
			box.find('.hasvalue').val('');
			box.find('.fc_preview_thumb').attr('src', 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');
			box.find('.fc_preview_text').html('');
			box.find('.fc_preview_msg').html('');
			box.find('.fcfield_preview_box').hide();

			box.find('.fc_media_file_box').show();

			if (hasValue) valcounter.value = ( valcounter.value=='' || valcounter.value=='1' )  ?  ''  :  parseInt(valcounter.value) - 1;
		}
		if (options.keep_props)
		{
			box.find('input, textarea').val('');
		}
	}

	fcfield_mediafile.clearMediaFile = function(clear_btn, placeholder_path)
	{
		var box = jQuery(clear_btn).closest('.fcfieldval_container');
		box.find('.fc-file-id').val('0');
		box.find('.fc_filedata_txt').val('');
		box.find('.inline-preview-img').attr('src', placeholder_path);
		box.find('.inline-preview-obj').attr('data', placeholder_path);
		box.find('.inline-preview-img').hide();
		box.find('.inline-preview-obj').hide();
	}

	fcfield_mediafile.assignMediaFile = function(value_container_id, filename, file_preview)
	{
		var originalname = filename;
		var displaytitle = '';
		var text_nowrap  = filename;

		var container   = jQuery('#'+value_container_id).closest('.fcfieldval_container');
		var config_name = jQuery('#'+value_container_id).data('config_name');

		var ext      = filename ? filename.split('.').pop() : '';
		var baseName = fcfield_mediafile.getFileBasename(filename);

		var waveform_preview = !filename ? '' : filename.substr(0, filename.lastIndexOf(".")) + '.mp3';
		var waveform_peaks   = !filename ? '' : filename.substr(0, filename.lastIndexOf(".")) + '.json';

		waveform_preview = !filename ? '' : waveform_preview.replace(baseName, '/audio_preview/' + baseName);
		waveform_peaks   = !filename ? '' : waveform_peaks.replace(baseName, '/audio_preview/' + baseName);

		var _name = filename;
		var _url = fcfield_mediafile_base_url[config_name] + '/' + filename;
		var isURL = /^(f|ht)tps?:\/\//i.test(_name);

		var mp3Preview = isURL ? waveform_preview : fcfield_mediafile_base_url[config_name] + waveform_preview;
		var jsonPeaks  = isURL ? waveform_peaks : fcfield_mediafile_base_url[config_name] + waveform_peaks;

		container.find('.fc-file-id').val(0);
		container.find('.fc_filedata_storage_name').html(filename);
		container.find('.fc_filedata_txt').val(originalname).removeClass('file_unpublished').blur();
		container.find('.fc_filedata_txt').removeAttr('data-filename');
		container.find('.fc_filedata_txt').data('filename', filename);
		container.find('.fc_filedata_txt_nowrap').html(text_nowrap).show();
		container.find('.fc_filedata_title').val(displaytitle);
		container.find('.fc_preview_thumb').attr('src', file_preview);

		container.find('.fc_filedata_txt').data('wfpreview', waveform_preview);
		container.find('.fc_filedata_txt').data('wfpeaks', waveform_peaks);

		var is_image = file_preview.match(/\.(jpeg|jpg|gif|png|webp)$/) != null;
		if (is_image)
		{
			container.find('.fc_preview_text').html('');
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

		// Load the audio waveform
		var audio_spectrum = container.find('.fc_mediafile_audio_spectrum').data('audio_spectrum');
		if (!!filename)
		{
			audio_spectrum._dummyProgress();

			jQuery.ajax({
				dataType: 'json',
				url: jsonPeaks
			}).success(function (response)
			{
				var data = response.data;
				data.unshift(data[1]);

				// v7: store peaks and render waveform via load()
				audio_spectrum._saved_peaks_     = data;
				audio_spectrum._loading_started_ = false;
				audio_spectrum.loaded            = false;

				setTimeout(function () {
					audio_spectrum._stopProgressBar();
					// v7: load() with peaks renders waveform; wrap peaks in array for per-channel format
					audio_spectrum.load(mp3Preview, [data]);
					audio_spectrum._loading_started_ = true;
				}, 10);
			});
		}

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

	fcfield_mediafile.getFileBasename = function(str)
	{
		var base = new String(str).substring(str.lastIndexOf('/') + 1);
		if (base.lastIndexOf(".") != -1)
		{
			base = base.substring(0, base.lastIndexOf("."));
		}
		return base;
	}

	fcfield_mediafile.assignFile = function(value_container_id, file, keep_modal, config_name)
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
		var baseName = fcfield_mediafile.getFileBasename(file.filename);

		var waveform_preview = !file.filename ? '' : file.filename.substr(0, file.filename.lastIndexOf(".")) + '.mp3';
		var waveform_peaks   = !file.filename ? '' : file.filename.substr(0, file.filename.lastIndexOf(".")) + '.json';

		waveform_preview = !file.filename ? '' : waveform_preview.replace(baseName, '/audio_preview/' + baseName);
		waveform_peaks   = !file.filename ? '' : waveform_peaks.replace(baseName, '/audio_preview/' + baseName);

		var _name = file.filename;
		var _url = fcfield_mediafile_base_url[config_name] + '/' + file.filename;
		var isURL = /^(f|ht)tps?:\/\//i.test(_name);

		var mp3Preview = isURL ? waveform_preview : fcfield_mediafile_base_url[config_name] + waveform_preview;
		var jsonPeaks  = isURL ? waveform_peaks : fcfield_mediafile_base_url[config_name] + waveform_peaks;

		container.find('.fc-file-id').val(file.id);
		container.find('.fc_filedata_storage_name').html(file.filename);
		container.find('.fc_filedata_txt').val(originalname).removeClass('file_unpublished').blur();
		container.find('.fc_filedata_txt').removeAttr('data-filename');
		container.find('.fc_filedata_txt').data('filename', file.filename);
		container.find('.fc_filedata_txt').data('wfpreview', waveform_preview);
		container.find('.fc_filedata_txt').data('wfpeaks', waveform_peaks);
		container.find('.fc_filedata_txt_nowrap').html(text_nowrap).show();
		container.find('.fc_filedata_title').val(displaytitle);

		container.find('.fc_preview_thumb').attr('src', file.preview ? file.preview : 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');

		if (file.preview)
		{
			container.find('.fc_preview_text').html('');
			if (keep_modal != 2)
			{
				container.find('.fcfield_preview_box').show();
				fcfield_mediafile.clearFieldUploader(container, config_name);
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

		var audio_spectrum = container.find('.fc_mediafile_audio_spectrum').data('audio_spectrum');
		container.find('.fc_filetitle').val(file.altname).blur();
		container.find('.fc_filelang').val(file.language).trigger('change');
		container.find('.fc_filedesc').val(file.description);

		// Load the audio waveform
		if (!!file.filename)
		{
			audio_spectrum._dummyProgress();

			jQuery.ajax({
				dataType: 'json',
				url: jsonPeaks
			}).success(function (response)
			{
				var data = response.data;
				data.unshift(data[1]);

				// v7: store peaks and render waveform via load()
				audio_spectrum._saved_peaks_     = data;
				audio_spectrum._loading_started_ = false;
				audio_spectrum.loaded            = false;

				setTimeout(function () {
					audio_spectrum._stopProgressBar();
					// v7: load() with peaks renders waveform; wrap peaks in array for per-channel format
					audio_spectrum.load(mp3Preview, [data]);
					audio_spectrum._loading_started_ = true;
				}, 10);
			});
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
