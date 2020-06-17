	/*var tag = document.createElement('script');
	tag.src = "https://www.youtube.com/iframe_api";
	//tag.crossorigin = 'anonymous';

	var firstScriptTag = document.getElementsByTagName('script')[0];
	firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);*/


	var fcview_sharemedia = {};
	fcview_sharemedia.player = [];
	fcview_sharemedia.playerState = [];

	document.addEventListener('DOMContentLoaded', () => {
		var iframes = document.querySelectorAll('div.fc_use_plyr');

		[].forEach.call(iframes, function(iframe) {
			//console.log(iframe);
			//console.log(iframe.id);
			//console.log(iframe.getAttribute('data-video-id'));

			fcview_sharemedia.player[iframe.id] = new Plyr('#' + iframe.id, {
				controls: ['play-large', 'play', 'progress', 'current-time', 'mute', 'volume', 'captions', 'settings', 'pip', 'airplay', 'fullscreen'],
				youtube: { noCookie: false, rel: 0, showinfo: 0, iv_load_policy: 3, modestbranding: 1 }
			});
			//console.log(fcview_sharemedia.player[iframe.id]);
		});
	});
