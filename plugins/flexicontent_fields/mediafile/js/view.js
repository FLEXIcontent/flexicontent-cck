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
			assetsObserver.observe(resource);
		});
	}

	_loadCallback(entries, assetsObserver)
	{
		entries.filter(entry => entry.isIntersecting).forEach(entry =>
		{
			fcview_mediafile.lazyLoadAsset(entry.target);
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

	var fcview_mediafile = {};
	var fcview_mediafile_base_url = [];
	var audio_spectrum_arr = [];
	var audio_spectrum_conf = [];

	fcview_mediafile.debugToConsole = [];
	fcview_mediafile.use_native_apis = [];
	fcview_mediafile.dialog_handle = [];
	fcview_mediafile.base_url = [];

	fcview_mediafile.lazyLoadAsset = function(asset)
	{
		fcview_mediafile.initValue(asset.getAttribute('data-fc_tagid'), asset.getAttribute('data-fc_fname'));
	}

	fcview_mediafile.initValue = function(tagid, config_name)
	{
		var fnn  = tagid.replace(/-/g, '_');
		var file = jQuery('#fcview_' + tagid + '_file-data-txt');
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

		// v7: WaveSurfer creates a shadow host <div> inside our container;
		// the timer element lives inside the shadow root's .wrapper (position:relative)
		var _timerEl = null;

		var ensureTimer = function()
		{
			if (_timerEl && _timerEl.isConnected) return _timerEl;

			var container  = document.querySelector('#fc_mediafile_audio_spectrum_' + fnn);
			if (!container) return null;

			// WaveSurfer v7: first child is shadow host; .wrapper is inside its shadow root
			var shadowHost = container.firstElementChild;
			var wrapperEl  = (shadowHost && shadowHost.shadowRoot)
			               ? shadowHost.shadowRoot.querySelector('.wrapper')
			               : container;
			if (!wrapperEl) wrapperEl = container;

			_timerEl = document.createElement('div');
			_timerEl.className = 'fccurrentTimeBox';
			_timerEl.style.cssText =
				'display:none;position:absolute;z-index:11;top:38%;'
				+ 'background:#2db383;color:white;'
				+ 'padding:4px 4px 4px 8px;box-sizing:border-box;'
				+ 'opacity:0.7;border-radius:0;width:55px;font-size:12px;';
			wrapperEl.appendChild(_timerEl);
			return _timerEl;
		}

		var updateTimer = function()
		{
			var formattedTime = secondsToTimestamp(audio_spectrum.getCurrentTime());

			var timer = ensureTimer();
			if (!timer) return;

			var container = document.querySelector('#fc_mediafile_audio_spectrum_' + fnn);
			var duration  = audio_spectrum.getDuration() || 1;
			var progress  = audio_spectrum.getCurrentTime() / duration;
			var width     = Math.round(progress * container.offsetWidth);

			timer.style.display     = 'block';
			timer.innerHTML         = formattedTime;
			timer.style.left        = width < timer.offsetWidth ? (width + 'px') : ((width - timer.offsetWidth - 2) + 'px');
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
			var url   = isURL ? file.data('wfpreview') : fcview_mediafile_base_url[config_name] + '/' + file.data('wfpreview');
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
			var zoomLevel = parseInt(slider.value);
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
			if (_timerEl) _timerEl.style.display = 'none';
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

		// v7: 'timeupdate' replaces 'audioprocess'; no timer on 'ready' — shown only during playback
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
				alert(Joomla.JText._('Preview can not load file. Reference to preview file is empty.'));
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
			if (_timerEl) _timerEl.style.display = 'none';
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
			var mp3Url  = isURL ? file.data('wfpreview') : fcview_mediafile_base_url[config_name] + '/' + file.data('wfpreview');
			var jsonUrl = isURL ? file.data('wfpeaks')   : fcview_mediafile_base_url[config_name] + '/' + file.data('wfpeaks');

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
