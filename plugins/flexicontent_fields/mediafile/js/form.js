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
	const waveformAudio = /\.(?:aac|flac|m4a|mp3|oga|ogg|opus|wav|webm)(?:[?#]|$)/i;

	fcfield_mediafile.debugToConsole = [];
	fcfield_mediafile.use_native_apis = [];
	fcfield_mediafile.dialog_handle = [];
	fcfield_mediafile.base_url = [];

	// Native JS toggles for the icon-only toolbar panels
	fcfield_mediafile.toggleFields = function(element_id_n)
	{
		var box = document.querySelector('.mf_value_props[data-name="' + element_id_n + '"]');
		if (!box) return;

		var isHidden = (window.getComputedStyle(box).display === 'none');
		box.style.display = isHidden ? 'block' : 'none';
		for (const btn of box.closest('.fcfieldval_container').querySelectorAll('.fc-media-edit-btn, .fcfield-mediaurl-btn')) {
			btn.classList.toggle('active', isHidden);
			btn.setAttribute('aria-expanded', String(isHidden));
		}
	}

	// Kept for backwards compatibility with the old media-URL button
	fcfield_mediafile.toggleMediaURL = function(element_id_n)
	{
		fcfield_mediafile.toggleFields(element_id_n);
	}

	fcfield_mediafile.lazyLoadAsset = function(asset)
	{
		fcfield_mediafile.initValue(asset.getAttribute('data-fc_tagid'), asset.getAttribute('data-fc_fname'));
	}

	fcfield_mediafile.initValue = function(tagid, config_name)
	{
		if (!window.WaveSurfer || !audio_spectrum_conf[config_name]) return;
		var fnn = tagid.replace(/-/g, '_');
		var file = document.getElementById('custom_' + tagid + '_file-data-txt');
		var box = document.getElementById('fc_mediafile_audio_spectrum_box_' + fnn);
		var spectrum = document.getElementById('fc_mediafile_audio_spectrum_' + fnn);
		var controls = document.getElementById('fc_mediafile_controls_' + fnn);
		if (!file || !box || !spectrum || !controls || mediaWaveforms.has(spectrum)) return;
		var slider = document.getElementById('fc_mediafile_slider_' + fnn);
		var loading_timer = null, progress_timer = null;
		var progressBar = box.querySelector('.fc_mediafile_audio_spectrum_progressbar');
		var bar = progressBar?.querySelector('.bar');
		var barText = progressBar?.querySelector('.barText');

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
			var container = spectrum;
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

			barText.textContent = Joomla.Text._('FLEXI_FIELD_MEDIAFILE_LOADING_PERCENT').replace('%s', percent);
			progressBar.style.opacity = '1';
			progressBar.style.visibility = 'visible';

			if (percent >= 100 && factor < 100)
			{
				var steps = 40;
				var frame = 0;
				loading_timer = setInterval(function ()
				{
					barText.textContent = Joomla.Text._('FLEXI_FIELD_MEDIAFILE_LOADING_PERCENT').replace('%s', percent);
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
				barText.textContent = Joomla.Text._('FLEXI_FIELD_MEDIAFILE_LOADING_PERCENT').replace('%s', percent);
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
			return loadWaveform(file, spectrum, config_name);
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
			height:        spectrum.closest('.fc-media-card') ? 64 : 128,

			// v7 removed: backend, mediaControls, xhr, scrollParent, autoCenter, hideScrollbar, plugins
		});


		function updateZoom()
		{
			const duration = audio_spectrum.getDuration();
			if (!slider || !audio_spectrum.loaded || !duration || !spectrum.clientWidth) return;
			const value = Number(slider.value);
			// Older layout overrides specify pixels per second directly.
			const pixelsPerSecond = slider.hasAttribute('data-zoom-factor')
				? (value === 1 ? 0 : value * spectrum.clientWidth / duration)
				: value;
			audio_spectrum.zoom(pixelsPerSecond);
		}
		if (slider) {
			slider.oninput = fc_debounce(updateZoom, 80);
			audio_spectrum.on('resize', updateZoom);
		}


		// Register new player to known players array
		audio_spectrum_arr[audio_spectrum_arr.length] = audio_spectrum;

		audio_spectrum.loaded            = false;
		audio_spectrum._loading_started_ = false;

		audio_spectrum._dummyProgress     = dummyProgress;
		audio_spectrum._stopProgressBar   = stopProgressBar;
		audio_spectrum._updateProgressBar = updateProgressBar;

		mediaWaveforms.set(spectrum, audio_spectrum);

		audio_spectrum.resetPreview = function()
		{
			audio_spectrum.pause();
			audio_spectrum.start_on_ready = false;
			audio_spectrum.loaded = false;
			audio_spectrum._loading_started_ = false;
			audio_spectrum._saved_peaks_ = null;
			if (slider) { slider.disabled = true; slider.value = slider.min; }
			audio_spectrum.empty();
			buttons.play.style.display = 'inline-block';
			buttons.play.disabled = false;
			buttons.pause.style.display = 'none';
			buttons.pause.disabled = true;
			buttons.pause.classList.remove('is_active');
			buttons.stop.disabled = true;
		};

		// Get control buttons
		var buttons = {
			load:  controls.querySelector('.loadBtn'),
			play:  controls.querySelector('.playBtn'),
			pause: controls.querySelector('.pauseBtn'),
			stop:  controls.querySelector('.stopBtn')
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
			if (audio_spectrum.getDuration() <= 0.001) return;
			buttons.play.disabled  = false;
			buttons.pause.disabled = false;
			buttons.stop.disabled  = false;

			if (!audio_spectrum.loaded)
			{
				audio_spectrum.loaded = true;
				if (slider) {
					slider.disabled = false;
					slider.parentNode.style.display = 'inline-block';
					slider.parentNode.style.visibility = 'visible';
					updateZoom();
				}
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



		box.addEventListener('keydown', event => {
			if (!event.target.matches('button, [role="button"], input')) keyPressedDown(event);
		});

		// Add events of playback buttons
		buttons.play.addEventListener('click', function()
		{
			if (!file.dataset.wfpreview && !file.dataset.wforiginal)
			{
				FlexiForm.alert(Joomla.JText._('FLEXI_PLEASE_UPLOAD_A_FILE'));
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
			if (file.dataset.wfpreview || file.dataset.wforiginal)
			{
				buttons.pause.disabled = true;
				buttons.stop.disabled  = true;
				buttons.play.disabled  = true;

				loadFile();
			}

			buttons.play.focus();
		}, false);


		const status = document.createElement('span');
		status.className = 'fc-waveform-status';
		status.setAttribute('role', 'status');
		controls.after(status);

		// Render pre-computed peaks or decode an available audio file.
		loadWaveform(file, spectrum, config_name);
	};

	const mediaWaveforms = new WeakMap();
	const mediaWaveformRequests = new WeakMap();

	function mediaWaveformURL(path, key)
	{
		if (/^(?:https?:)?\/\//i.test(path)) return path;
		return (fcfield_mediafile_base_url[key] || '').replace(/\/$/, '') + '/' + path.replace(/^\//, '');
	}

	function cancelWaveform(spectrum)
	{
		if (!spectrum) return;
		mediaWaveformRequests.get(spectrum)?.abort();
		mediaWaveformRequests.delete(spectrum);
	}

	async function loadWaveform(input, spectrum, key)
	{
		const player = mediaWaveforms.get(spectrum);
		if (!player || !input?.isConnected || (!input.dataset.wfpreview && !input.dataset.wforiginal)) return;
		cancelWaveform(spectrum);
		const request = new AbortController();
		mediaWaveformRequests.set(spectrum, request);
		const original = input.dataset.wforiginal;
		const preview = input.dataset.wfpreview;
		const active = () => !request.signal.aborted && input.isConnected;
		player._loading_started_ = true;
		player.loaded = false;
		player._dummyProgress();
		try {
			let peaks;
			if (input.dataset.wfpeaks) {
				try {
					const response = await fetch(mediaWaveformURL(input.dataset.wfpeaks, key), {signal: request.signal});
					const payload = response.ok ? await response.json() : null;
					const data = payload?.data;
					if (Array.isArray(data) && data.length > 1) peaks = [data[1], ...data];
				} catch { /* The player can decode audio without precomputed peaks. */ }
			}
			if (!active()) return;
			const audio = original && !peaks ? original : mediaWaveformURL(preview, key);
			try {
				await player.load(audio, peaks ? [peaks] : undefined);
			} catch (error) {
				if (!active() || !original || audio === original) throw error;
				await player.load(original);
			}
		} catch {
			if (active()) {
				player.resetPreview();
				const message = spectrum.closest('.fc_mediafile_audio_spectrum_box')?.querySelector('.fc-waveform-status');
				if (message) message.textContent = FlexiForm.text('PLG_FLEXICONTENT_FIELDS_MEDIAFILE_FILE_NOT_FOUND');
			}
		} finally {
			if (mediaWaveformRequests.get(spectrum) === request) {
				mediaWaveformRequests.delete(spectrum);
				player._stopProgressBar();
			}
		}
	}

	function clearWaveform(box)
	{
		const input = box.querySelector('.fc_filedata_txt');
		if (input) {
			delete input.dataset.wfpreview;
			delete input.dataset.wfpeaks;
			delete input.dataset.wforiginal;
		}
		const spectrum = box.querySelector('.fc_mediafile_audio_spectrum');
		cancelWaveform(spectrum);
		const player = mediaWaveforms.get(spectrum);
		player?._stopProgressBar();
		const message = spectrum?.closest('.fc_mediafile_audio_spectrum_box')?.querySelector('.fc-waveform-status');
		if (message) message.textContent = '';
		player?.resetPreview();
	}

	function assignWaveform(box, input, file, key)
	{
		if (!input) return;
		clearWaveform(box);
		if (!waveformAudio.test(file.filename)) return;
		if (String(file.secure) === '0' && String(file.url ?? 0) === '0' && input.dataset.wfPublicRoot) {
			input.dataset.wforiginal = input.dataset.wfPublicRoot + '/' + file.filename.split('/').map(encodeURIComponent).join('/');
		}
		const path = file.filename.replace(/(^|\/)([^/]+)\.[^./]+$/, '$1audio_preview/$2');
		const hasFileExtension = path !== file.filename;
		input.dataset.wfpreview = file.waveform_preview || (hasFileExtension ? path + '.mp3' : '');
		input.dataset.wfpeaks = file.waveform_peaks || (hasFileExtension ? path + '.json' : '');
		loadWaveform(input, box.querySelector('.fc_mediafile_audio_spectrum'), key);
	}

	fcfield_mediafile.disposeValue = function(box)
	{
		const spectrum = box.querySelector('.fc_mediafile_audio_spectrum');
		const player = mediaWaveforms.get(spectrum);
		if (!player) return;
		cancelWaveform(spectrum);
		player._stopProgressBar();
		player.destroy();
		mediaWaveforms.delete(spectrum);
		const index = audio_spectrum_arr.indexOf(player);
		if (index !== -1) audio_spectrum_arr.splice(index, 1);
	};

	fcfield_mediafile.showUploader = function(fieldName)
	{
		FlexiMediaField.showUploader('custom_' + fieldName + '_file-data-txt');
	};

	fcfield_mediafile.fileFiltered = function() {};

	fcfield_mediafile.fileUploaded = function(uploader, file, result, key)
	{
		const upload = FlexiMediaField.uploaded(uploader, result, '.fc_filedata_txt');
		if (upload) fcfield_mediafile.assignFile(upload.target.id, upload.file, 0, key);
	};

	fcfield_mediafile.clearFieldUploader = function(box)
	{
		FlexiMediaField.clearUploader(box);
	};

	fcfield_mediafile.clearField = function(element, options, key)
	{
		FlexiMediaField.clearFile(element, options, key, clearWaveform);
	};

	fcfield_mediafile.clearMediaFile = function(button, placeholder)
	{
		FlexiMediaField.clearMediaFile(button, placeholder, clearWaveform);
	};

	fcfield_mediafile.assignMediaFile = function(id, filename, preview)
	{
		return FlexiMediaField.assignFile(id, {id: 0, filename, preview}, 1, null, null, assignWaveform, true);
	};

	fcfield_mediafile.selectMediaFile = function(id, filename, root)
	{
		if (filename) fcfield_mediafile.assignMediaFile(id, filename, root + filename);
		if (typeof SqueezeBox !== 'undefined') SqueezeBox.close();
		fc_closeDialog('fc_modal_popup_container');
	};

	fcfield_mediafile.getFileBasename = function(path)
	{
		return String(path).split('/').pop().replace(/\.[^.]*$/, '');
	};

	fcfield_mediafile.assignFile = function(id, file, keepModal, key)
	{
		return FlexiMediaField.assignFile(id, file, keepModal, key, fcfield_mediafile.dialog_handle, assignWaveform);
	};
