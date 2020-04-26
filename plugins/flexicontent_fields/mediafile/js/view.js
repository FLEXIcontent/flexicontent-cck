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

		this.bindEvents();
		this.init();
	}

	bindEvents() {
		//this._lazyLoadAsset = this._lazyLoadAsset.bind(this, add extra vars here);
	}

	init() {
		const assetsObserver = new IntersectionObserver((entries, assetsObserver) =>
		{
			entries.filter(entry => entry.isIntersecting).forEach(entry =>
			{
				this._lazyLoadAsset(entry.target);
				assetsObserver.unobserve(entry.target);
			});
		}, this.options);
		this.resources.forEach(resource =>
		{
			assetsObserver.observe(resource);
		});
	}

	_lazyLoadAsset(asset) {
		fcview_mediafile.initValue(asset.getAttribute('data-fc_tagid'), asset.getAttribute('data-fc_fname'));
	}
}

	var fcview_mediafile = {};
	var fcview_mediafile_base_url = [];
	var audio_spectrum_arr = [];

	fcview_mediafile.debugToConsole = [];
	fcview_mediafile.use_native_apis = [];
	fcview_mediafile.dialog_handle = [];
	fcview_mediafile.base_url = [];

	fcview_mediafile.initValue = function(tagid, config_name)
	{
		//window.console.log(tagid);
		var fnn  = tagid.replace(/-/g, '_');
		var file = jQuery('#fcview_' + tagid + '_file-data-txt');
		var loading_timer = null, progress_timer = null;

		var box = jQuery('#fc_mediafile_audio_spectrum_box_' + fnn);
		var _progressBar = box.find('.fc_mediafile_audio_spectrum_progressbar');
		var bar = _progressBar.find('.bar').get(0);
		var barText = _progressBar.find('.barText').get(0);
		var progressBar = box.find('.fc_mediafile_audio_spectrum_progressbar').get(0);
		var mediaPlayer = jQuery('#fc_mediafile_audio_spectrum_box_' + fnn).find('.fc_mediafile_audio_spectrum').get(0);

		var keyPlayPause = function keyPlayPause(key_event) {
			if (key_event.keyCode == 32 || key_event.keyCode == 13 /*/*&& key_event.target.nodeName !== "WAVE"*/)
			{
				key_event.preventDefault();
				//window.console.log(key_event.target.nodeName);

				// Toggle playing state
				var event = document.createEvent("HTMLEvents");
				event.initEvent("click", true, true);
				event.eventName = "click";
				audio_spectrum.backend.isPaused() ? buttons.play.dispatchEvent(event) :  buttons.pause.dispatchEvent(event);

				return false;
			}
		}

		var updateTimer = function updateTimer()
		{
			var formattedTime = secondsToTimestamp(audio_spectrum.getCurrentTime());
			//jQuery('#fc_mediafile_current_time_' + fnn).text(formattedTime);

			var wave = jQuery('#fc_mediafile_audio_spectrum_' + fnn + ' wave wave');
			if (!wave.find('.fccurrentTimeBox').length)
			{
				wave.append(jQuery('<div class="fccurrentTimeBox" style="position:absolute; z-index: 11; right:0; top: 38%; background: #777; color: white; padding: 4px 4px 4px 8px; opacity: 70%;"></div>'));
			}
			wave.find('.fccurrentTimeBox').html(formattedTime);
		}

		var seekHandler = function seekHandler(position)
		{
			audio_spectrum._position_ = position;

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

		var updateProgressBar = function (percent, eventTarget)
		{
			var factor = 100;
			
			barText.innerHTML = 'Loading : ' + percent + ' %';
			//bar.style.width = (percent * factor / 100.0) + '%';
			progressBar.style.opacity = '1';
			progressBar.style.visibility = 'visible';

			if (percent >= 100 && factor < 100)
			{
				var steps = 40;
				var frame = 0;
				loading_timer = setInterval(function ()
				{
					barText.innerHTML = 'Loading : ' + percent + ' %';
					//bar.style.width = factor + (frame * (factor / steps)) + '%';
					frame += 1;
					if (frame >= steps)
					{
						clearInterval(loading_timer);
						loading_timer = 0;
					}
				}, 50);
			}
			//window.console.log(eventTarget);
		}

		var stopProgressBar = function (percent)
		{
			if (loading_timer) clearInterval(loading_timer);
			if (progress_timer) clearInterval(progress_timer);

			loading_timer = null;
			progress_timer = null;

			if (!!stopProgressBar.stopping) return;
			stopProgressBar.stopping = 1;

			// Hide and reset progress bar

			if (!!percent)
			{
				barText.innerHTML = 'Loading : ' + percent + ' %';
				progressBar.style.opacity = '0.75';
				//bar.style.width = percent + '%';

				setTimeout(function () {
					stopProgressBar.stopping = 0;
					progressBar.style.visibility = 'hidden';
					barText.innerHTML = '';
					//bar.style.width = 0;
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
			var peaks = audio_spectrum.backend.peaks || null;

			//window.console.log('Loading');
			//window.console.log('isURL: ' + isURL);
			//window.console.log('filename: ' + file.data('wfpreview'));
			//window.console.log('Base URL: ' + fcview_mediafile_base_url[config_name]);

			isURL ? audio_spectrum.load(file.data('wfpreview'), peaks) : audio_spectrum.load(fcview_mediafile_base_url[config_name] + '/' + file.data('wfpreview'), peaks);
			audio_spectrum.drawBuffer();
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
		    waveColor: '#81bff7', 
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

		// Register new player to known players array
		audio_spectrum_arr[audio_spectrum_arr.length] = audio_spectrum;

		// Variable to check if song is loaded
		audio_spectrum.loaded = false;

		// Some references to methods so that the can be used externally
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

		// Create a reference of the buttons inside the player instance
		audio_spectrum._buttons_ = buttons;

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


		audio_spectrum.on('loading', function (percent, eventTarget)
		{
			//window.console.log('loading: (waveform is loading / calculating peaks)');
			//updateProgressBar(percent, eventTarget);
		});

		audio_spectrum.on('waveform-ready', function()
		{
			//window.console.log('waveform-ready: (Peaks loading is DONE)');
			//stopProgressBar(100);
		});

		audio_spectrum.on('ready', function()
		{
			//window.console.log('ready: (Player ready to play)');
			//stopProgressBar(100);

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
				!!audio_spectrum._position_ ? audio_spectrum.play(audio_spectrum._position_ * audio_spectrum.getDuration()) : audio_spectrum.play();
				audio_spectrum._position_ = null; 

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


		buttons.play.addEventListener("keydown", function (event) {
			return keyPlayPause(event);
		}, false);
		buttons.pause.addEventListener("keydown", function (event) {
			return keyPlayPause(event);
		}, false);

		// Add events of playback buttons
		buttons.play.addEventListener('click', function()
		{
			if (!!!file.data('wfpreview'))
			{
				alert(Joomla.JText._('Reference to File is empty can not loade file'));
				return;
			}

			// Stop all other known players
			var i;
			for (i = 0; i < audio_spectrum_arr.length; i++)
			{
				if (!audio_spectrum_arr[i].backend.isPaused())
				{
					// Toggle playing state
					var event = document.createEvent("HTMLEvents");
					event.initEvent("click", true, true);
					event.eventName = "click";

					audio_spectrum_arr[i]._noBtnFocus_ = 1;
					audio_spectrum_arr[i]._buttons_.pause.dispatchEvent(event);
					audio_spectrum_arr[i]._noBtnFocus_ = null;
				}
			}

			// Load song when play is pressed
			if (!audio_spectrum.loaded)
			{
				audio_spectrum.start_on_ready = true;
				loadFile();
			}
			else
			{
				!!audio_spectrum._position_ ? audio_spectrum.play(audio_spectrum._position_ * audio_spectrum.getDuration()) : audio_spectrum.play();
				audio_spectrum._position_ = null; 
			}

			buttons.play.disabled = true;
			buttons.pause.disabled = false;
			buttons.stop.disabled = false;

			buttons.play.style.display = 'none';
			buttons.pause.style.display = 'inline-block';
			buttons.stop.style.display = 'none';
			buttons.load.style.display = 'none';

			buttons.pause.focus();
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

			// Do not focus when pausing due to starting another player
			if (!!!audio_spectrum._noBtnFocus_) buttons.play.focus();
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

			buttons.play.focus();
		}, false);

		// Add event of load button to allow loading new files
		buttons.load.addEventListener('click', function()
		{
			if (!!file.data('wfpreview'))
			{
				buttons.pause.disabled = true;
				buttons.stop.disabled = true;
				buttons.play.disabled = true;

				loadFile();
			}

			buttons.play.focus();
		}, false);


		// Load the audio file
		if (!!file.data('wfpreview'))
		{
			var isURL = /^(f|ht)tps?:\/\//i.test(file.data('wfpreview'));
			//window.console.log('isURL: ' + isURL);
			//window.console.log('filename: ' + file.data('wfpreview'));
			//window.console.log('Base URL: ' + fcview_mediafile_base_url[config_name]);


			// Set peaks
			var mp3Url = isURL ? file.data('wfpreview') : fcview_mediafile_base_url[config_name] + '/' + file.data('wfpreview');
			var jsonUrl = isURL ? file.data('wfpeaks') : fcview_mediafile_base_url[config_name] + '/' + file.data('wfpeaks');

			//dummyProgress();
			updateProgressBar(0);

			var r = audio_spectrum.util.ajax({
				responseType: 'json',
				url: jsonUrl
			});
			
			r.on('progress', function(t)
			{
				var e;
        if (t.lengthComputable) e = t.loaded / t.total;
        else e = t.loaded / (t.loaded + 1e6);
				//window.console.log(t + ' - ' + e);
        updateProgressBar(Math.round(100 * e), null);
			});

			r.on('success', function (response)
			{
				var data = response.data;
				data.unshift(data[1]);

				// Scale peaks
				audio_spectrum.backend.peaks = data; //.map(p => p/128);

				// Alternative we can load the file now ... using the peaks to avoid full download
				//audio_spectrum.load(mp3Url, data);

				// Do a waveform reDraw without any delay !!
				audio_spectrum.drawBuffer();
				
				// Stop progressBar but first move it to 100%
				stopProgressBar(100);
			});
		}

	}


