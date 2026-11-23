/**
 * FC File GLightbox
 *
 * jQuery-free PDF viewer for the `file` and `mediafile` fields (View inside = GLightbox).
 * One page-wide GLightbox instance is created for all `.fcfile_glightbox` anchors.
 * Loaded by the fields together with the GLightbox framework.
 */
(function () {
	'use strict';

	var SELECTOR   = '.fcfile_glightbox';
	var MAX_TRIES  = 50;   // 50 x 100ms = 5s waiting for the GLightbox script
	var WAIT_DELAY = 100;

	function viewportSize() {
		// GLightbox's checkSize() appends 'px' to numeric values, so pass plain numbers.
		return {
			width:  Math.max(320, Math.round((window.innerWidth  || 1280) * 0.92)),
			height: Math.max(320, Math.round((window.innerHeight || 800) * 0.88))
		};
	}

	function create() {
		var size = viewportSize();

		window.fcFileGlightbox = window.GLightbox({
			selector: SELECTOR,
			openEffect: 'fade',
			closeEffect: 'fade',
			closeOnOutsideClick: true,
			keyboardNavigation: true,
			loop: true,
			zoomable: false,
			draggable: false,
			width: size.width,
			height: size.height
		});

		// GLightbox caches elements at init and numeric-checks data-width/height
		// (appending "px"), so viewport-relative values like 92vw are not supported.
		// Keep the modal responsive by resizing the active slide's media on open.
		function resizeActive() {
			var s = viewportSize();
			var slide = document.querySelector('.gslide.current');
			if (!slide) return;

			var inner = slide.querySelector('.ginner-container');
			var media = slide.querySelector('.gslide-media');
			var desc  = slide.querySelector('.gslide-description');
			var frame = media ? media.querySelector('iframe, .ginlined-content, .gslide-inline') : null;

			var descH = 0;
			if (desc) {
				var hasDesc = !!desc.textContent && desc.textContent.trim() !== '';
				var descStyle = window.getComputedStyle(desc);
				if (hasDesc && descStyle.display !== 'none' && descStyle.visibility !== 'hidden') {
					descH = Math.min(desc.offsetHeight || 0, Math.round(s.height * 0.25));
				}
			}

			var mediaH = Math.max(300, s.height - descH);

			if (inner) {
				inner.style.maxWidth = s.width + 'px';
				inner.style.width = s.width + 'px';
				inner.style.height = 'auto';
				inner.style.minHeight = '0';
			}

			if (media) {
				media.style.width = '100%';
				media.style.height = mediaH + 'px';
				media.style.maxWidth = '100%';
				media.style.maxHeight = 'none';
				media.style.display = 'block';
				media.style.flex = '0 0 auto';
				media.style.overflow = 'hidden';
			}

			if (frame) {
				frame.style.width = '100%';
				frame.style.height = mediaH + 'px';
				frame.style.minHeight = mediaH + 'px';
				frame.style.maxWidth = '100%';
				frame.style.border = '0';
				frame.style.display = 'block';
			}
		}

		window.fcFileGlightbox.on('slide_after_load', resizeActive);
		window.fcFileGlightbox.on('open', function () {
			window.setTimeout(resizeActive, 50);
			window.setTimeout(resizeActive, 250);
		});
		window.addEventListener('resize', resizeActive);
	}

	function boot() {
		if (typeof window.GLightbox === 'undefined') {
			if (typeof window.fcFileGlightboxTries === 'undefined') window.fcFileGlightboxTries = 0;
			if (++window.fcFileGlightboxTries < MAX_TRIES) window.setTimeout(boot, WAIT_DELAY);
			return;
		}

		if (!window.fcFileGlightbox) create();
	}

	// Re-bind after AJAX inserted new anchors (GLightbox caches its elements at init).
	window.fcFileGlightboxInit = function () {
		if (window.fcFileGlightbox) {
			try { window.fcFileGlightbox.destroy(); } catch (e) { /* already gone */ }
			window.fcFileGlightbox = null;
		}
		boot();
	};

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
