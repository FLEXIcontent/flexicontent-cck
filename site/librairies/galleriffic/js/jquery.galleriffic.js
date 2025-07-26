/**
 * jQuery Galleriffic plugin
 *
 * Copyright (c) 2008 Trent Foley (https://trentacular.com)
 * Licensed under the MIT License:
 *   https://www.opensource.org/licenses/mit-license.php
 *
 * Much thanks to primary contributer Ponticlaro (https://www.ponticlaro.com)
 *
 * Modified by Jay Hayes (https://iamvery.com)
 * Modified by ggppdk (https:://flexicontent.org)
 */
;(function($) {
	// Globally keep track of all images by their unique hash.  Each item is an image data object.
	var allImages = {};
	var imageCounter = 0;

	// Galleriffic static class
	$.galleriffic = {
		version: '2.1.0',

		// Strips HTML tags
		stripHtml: function(html) {
			var tmp = document.createElement("DIV");
			tmp.innerHTML = html;
			return tmp.textContent || tmp.innerText || "";
		},

		// Strips invalid characters and any leading # characters
		normalizeHash: function(hash) {
			return hash.replace(/^.*#/, '').replace(/\?.*$/, '');
		},

		getImage: function(hash) {
			if (!hash)
				return undefined;

			hash = $.galleriffic.normalizeHash(hash);
			return allImages[hash];
		},

		// Global function that looks up an image by its hash and displays the image.
		// Returns false when an image is not found for the specified hash.
		// @param {String} hash This is the unique hash value assigned to an image.
		gotoImage: function(hash) {
			var imageData = $.galleriffic.getImage(hash);
			if (!imageData)
				return false;

			var gallery = imageData.gallery;
			gallery.gotoImage(imageData);

			return true;
		},

		// Removes an image from its respective gallery by its hash.
		// Returns false when an image is not found for the specified hash or the
		// specified owner gallery does match the located images gallery.
		// @param {String} hash This is the unique hash value assigned to an image.
		// @param {Object} ownerGallery (Optional) When supplied, the located images
		// gallery is verified to be the same as the specified owning gallery before
		// performing the remove operation.
		removeImageByHash: function(hash, ownerGallery) {
			var imageData = $.galleriffic.getImage(hash);
			if (!imageData)
				return false;

			var gallery = imageData.gallery;
			if (ownerGallery && ownerGallery != gallery)
				return false;

			return gallery.removeImageByIndex(imageData.index);
		}
	};

	var defaults = {
		use_pages:                 true,
		delay:                     3000,
		numThumbs:                 20,
		slideHeight:               0,
		preloadAhead:              40, // Set to -1 to preload all images
		enableTopPager:            false,
		enableBottomPager:         true,
		maxPagesToShow:            7,
		imageContainerSel:         '',
		captionContainerSel:       '',
		ssControlsContainerSel:    '',
		navControlsContainerSel:   '',
		loadingContainerSel:       '',
		playLinkText:              'Play',
		pauseLinkText:             'Pause',
		prevLinkText:              'Previous',
		nextLinkText:              'Next',
		nextPageLinkText:          'Next &rsaquo;',
		prevPageLinkText:          '&lsaquo; Prev',
		enableHistory:             false,
		enableFancybox:            false,
		fancyOptions:              {},
		enableKeyboardNavigation:  true,
		autoStart:                 false,
		syncTransitions:           true,
		defaultTransitionDuration: 1000,

		// accepts a delegate like such: function(prevIndex, nextIndex) { ... }
		onSlideChange:             undefined,

		// accepts a delegate like such: function(slide, caption, isSync, callback) { ... }
		onTransitionOut:           function(slide, caption, isSync, callback)
		{
			//slide.fadeTo(this.getDefaultTransitionDuration(isSync), 0.0, callback);
			//caption.fadeTo(this.getDefaultTransitionDuration(isSync), 0.0);

			slide.css('opacity', 0.0);
			caption.css('opacity', 0.0);

			setTimeout(function() {
				typeof callback === 'function' && callback();
			}, this.getDefaultTransitionDuration(isSync));
		},

		// accepts a delegate like such: function(slide, caption, isSync) { ... }
		onTransitionIn:            function(slide, caption, isSync)
		{
			const duration = this.getDefaultTransitionDuration(isSync);

			// Delay the opacity change slightly to allow the slide element to be added to the DOM before the fade occurs
			setTimeout(function() {
				//slide.fadeTo(duration, 1.0);
				slide.css('opacity', 1.0);
			}, 20);

			// Position the caption at the bottom of the image and set its opacity
			const slideImage = slide.find('img');

			const left = Math.ceil((slide.width() - slideImage.width()) / 2),
				offTop = slideImage.get(0).offsetTop,
				height = Math.floor(slideImage.outerHeight(true)),
				bottom = Math.ceil(slide.height() - slideImage.outerHeight(true)),
				width = slideImage.width() < slide.width() ? slideImage.width() : slide.width();

			//slide.closest('.slideshow-container').find('.nav-controls-box').find('a').css({'height': height});
			//slide.closest('.slideshow-container').find('.loader').css({'height': height});

			const caption_el = caption.get(0);
			caption_el.style.width = width + 'px';
			caption_el.style.display = 'block';
			caption_el.style.bottom = (bottom - offTop) + 'px';
			caption_el.style.left = left + 'px';
		},

		// accepts a delegate like such: function(callback) { ... }
		onPageTransitionOut:       function(callback)
		{
			this.fadeTo('fast', 0.0, callback);
		},

		// accepts a delegate like such: function() { ... }
		onPageTransitionIn:        function()
		{
			this.fadeTo('fast', 1.0);
		},

		onImageAdded:              undefined, // accepts a delegate like such: function(imageData, $li) { ... }
		onImageRemoved:            undefined  // accepts a delegate like such: function(imageData, $li) { ... }
	};

	// Primary Galleriffic initialization function that should be called on the thumbnail container.
	$.fn.galleriffic = function(settings) {
		//  Extend Gallery Object
		$.extend(this, {
			// Returns the version of the script
			version: $.galleriffic.version,

			// Current state of the slideshow
			isSlideshowRunning: false,
			slideshowTimeout: undefined,

			// This function is attached to the click event of generated hyperlinks within the gallery
			clickHandler: function(e, link) {
				this.pause();

				if (!this.enableHistory) {
					// The href attribute holds the unique hash for an image
					var hash = $.galleriffic.normalizeHash($(link).attr('href'));
					$.galleriffic.gotoImage(hash);
					e.preventDefault();
				}
			},

			// Appends an image to the end of the set of images.  Argument listItem can be either a jQuery DOM element or arbitrary html.
			// @param listItem Either a jQuery object or a string of html of the list item that is to be added to the gallery.
			appendImage: function(listItem) {
				this.addImage(listItem, false, false);
				return this;
			},

			// Inserts an image into the set of images.  Argument listItem can be either a jQuery DOM element or arbitrary html.
			// @param listItem Either a jQuery object or a string of html of the list item that is to be added to the gallery.
			// @param {Integer} position The index within the gallery where the item shouold be added.
			insertImage: function(listItem, position) {
				this.addImage(listItem, false, true, position);
				return this;
			},

			// Adds an image to the gallery and optionally inserts/appends it to the DOM (thumbExists)
			// @param listItem Either a jQuery object or a string of html of the list item that is to be added to the gallery.
			// @param {Boolean} thumbExists Specifies whether the thumbnail already exists in the DOM or if it needs to be added.
			// @param {Boolean} insert Specifies whether the the image is appended to the end or inserted into the gallery.
			// @param {Integer} position The index within the gallery where the item shouold be added.
			addImage: function(listItem, thumbExists, insert, position) {
				var $li = ( typeof listItem === "string" ) ? $(listItem) : listItem;
				var $aThumb = $li.find('a.thumb');
				var slideUrl = $aThumb.attr('href');
				var title = $aThumb.attr('title');
				var width = $aThumb.attr('data-width');
				var height = $aThumb.attr('data-height');
				var $caption = $li.find('.caption').remove();
				var $fancy = $li.find('a.gf_fancybox');//.find('a.fancy').remove();
				var hash = $aThumb.attr('name');

				// Increment the image counter
				imageCounter++;

				// Autogenerate a hash value if none is present or if it is a duplicate
				if (!hash || allImages[''+hash]) {
					hash = imageCounter;
				}

				// Set position to end when not specified
				if (!insert)
					position = this.data.length;

				var imageData = {
					title:title,
					width:width,
					height:height,
					slideUrl:slideUrl,
					caption:$caption,
					fancy:$fancy,
					hash:hash,
					gallery:this,
					index:position
				};

				// Add the imageData to this gallery's array of images
				if (insert) {
					this.data.splice(position, 0, imageData);

					// Reset index value on all imageData objects
					this.updateIndices(position);
				}
				else {
					this.data.push(imageData);
				}

				var gallery = this;

				// Add the element to the DOM
				if (!thumbExists) {
					// Update thumbs passing in addition post transition out handler
					this.updateThumbs(function() {
						var $thumbsUl = gallery.find('ul.thumbs');
						if (insert)
							$thumbsUl.children(':eq('+position+')').before($li);
						else
							$thumbsUl.append($li);

						if (gallery.onImageAdded)
							gallery.onImageAdded(imageData, $li);
					});
				}

				// Register the image globally
				allImages[''+hash] = imageData;

				// Setup attributes and click handler
				$aThumb.attr('rel', 'history')
					.attr('href', '#'+hash)
					.removeAttr('name')
					.click(function(e) {
						gallery.clickHandler(e, this);
					});

				return this;
			},

			// Removes an image from the gallery based on its index.
			// Returns false when the index is out of range.
			removeImageByIndex: function(index) {
				if (index < 0 || index >= this.data.length)
					return false;

				var imageData = this.data[index];
				if (!imageData)
					return false;

				this.removeImage(imageData);

				return true;
			},

			// Convenience method that simply calls the global removeImageByHash method.
			removeImageByHash: function(hash) {
				return $.galleriffic.removeImageByHash(hash, this);
			},

			// Removes an image from the gallery.
			removeImage: function(imageData) {
				var index = imageData.index;

				// Remove the image from the gallery data array
				this.data.splice(index, 1);

				// Remove the global registration
				delete allImages[''+imageData.hash];

				// Remove the image's list item from the DOM
				this.updateThumbs(function() {
					var $li = gallery.find('ul.thumbs')
						.children(':eq('+index+')')
						.remove();

					if (gallery.onImageRemoved)
						gallery.onImageRemoved(imageData, $li);
				});

				// Update each image objects index value
				this.updateIndices(index);

				return this;
			},

			// Updates the index values of the each of the images in the gallery after the specified index
			updateIndices: function(startIndex) {
				for (i = startIndex; i < this.data.length; i++) {
					this.data[i].index = i;
				}

				return this;
			},

			// Scraped the thumbnail container for thumbs and adds each to the gallery
			initializeThumbs: function() {
				this.data = [];
				var gallery = this;

				this.find('ul.thumbs > li').each(function(i) {
					gallery.addImage($(this), true, false);
				});

				return this;
			},

			isPreloadComplete: false,

			// Initalizes the image preloader
			preloadInit: function() {
				if (this.preloadAhead == 0) return this;
				if(!this.currentImage) return this;

				this.preloadStartIndex = this.currentImage.index;
				var nextIndex = this.getNextIndex(this.preloadStartIndex);
				return this.preloadRecursive(this.preloadStartIndex, nextIndex);
			},

			// Changes the location in the gallery the preloader should work
			// @param {Integer} index The index of the image where the preloader should restart at.
			preloadRelocate: function(index) {
				// By changing this startIndex, the current preload script will restart
				this.preloadStartIndex = index;
				return this;
			},

			// Recursive function that performs the image preloading
			// @param {Integer} startIndex The index of the first image the current preloader started on.
			// @param {Integer} currentIndex The index of the current image to preload.
			preloadRecursive: function(startIndex, currentIndex) {
				// Check if startIndex has been relocated
				if (startIndex != this.preloadStartIndex) {
					var nextIndex = this.getNextIndex(this.preloadStartIndex);
					return this.preloadRecursive(this.preloadStartIndex, nextIndex);
				}

				var gallery = this;

				// Now check for preloadAhead count
				var preloadCount = currentIndex - startIndex;
				if (preloadCount < 0)
					preloadCount = this.data.length-1-startIndex+currentIndex;
				if (this.preloadAhead >= 0 && preloadCount > this.preloadAhead) {
					// Do this in order to keep checking for relocated start index
					setTimeout(function() { gallery.preloadRecursive(startIndex, currentIndex); }, 500);
					return this;
				}

				var imageData = this.data[currentIndex];
				if (!imageData)
					return this;

				// If already loaded, continue
				if (imageData.image)
					return this.preloadNext(startIndex, currentIndex);

				// Preload the image
				var image = new Image();

				image.onload = function() {
					imageData.image = this;
					gallery.preloadNext(startIndex, currentIndex);
				};

				image.alt = imageData.title;
				image.src = imageData.slideUrl;

				image.width  = imageData.width;
				image.height = imageData.height;
				image.style.maxWidth  = imageData.width + 'px';
				image.style.maxHeight = (this.slideHeight > imageData.height ? imageData.height + 'px' : '');
				return this;
			},

			// Called by preloadRecursive in order to preload the next image after the previous has loaded.
			// @param {Integer} startIndex The index of the first image the current preloader started on.
			// @param {Integer} currentIndex The index of the current image to preload.
			preloadNext: function(startIndex, currentIndex) {
				var nextIndex = this.getNextIndex(currentIndex);
				if (nextIndex == startIndex) {
					this.isPreloadComplete = true;
				} else {
					// Use setTimeout to free up thread
					var gallery = this;
					setTimeout(function() { gallery.preloadRecursive(startIndex, nextIndex); }, 100);
				}

				return this;
			},

			// Safe way to get the next image index relative to the current image.
			// If the current image is the last, returns 0
			getNextIndex: function(index) {
				var nextIndex = index+1;
				if (nextIndex >= this.data.length)
					nextIndex = 0;
				return nextIndex;
			},

			// Safe way to get the previous image index relative to the current image.
			// If the current image is the first, return the index of the last image in the gallery.
			getPrevIndex: function(index) {
				var prevIndex = index-1;
				if (prevIndex < 0)
					prevIndex = this.data.length-1;
				return prevIndex;
			},

			// Pauses the slideshow
			pause: function() {
				this.isSlideshowRunning = false;
				if (this.slideshowTimeout) {
					clearTimeout(this.slideshowTimeout);
					this.slideshowTimeout = undefined;
				}

				if (this.$ssControlsContainer) {
					this.$ssControlsContainer
						.find('div.ss-controls a').removeClass().addClass('play')
						.attr('title', '')
					//.attr('title', $.galleriffic.stripHtml(this.playLinkText))
						.attr('href', '#play')
						.html(this.playLinkText);
				}

				return this;
			},

			// Plays the slideshow
			play: function() {
				this.isSlideshowRunning = true;

				if (this.$ssControlsContainer) {
					this.$ssControlsContainer
						.find('div.ss-controls a').removeClass().addClass('pause')
						.attr('title', '')
					//.attr('title', $.galleriffic.stripHtml(this.pauseLinkText))
						.attr('href', '#pause')
						.html(this.pauseLinkText);
				}

				if (!this.slideshowTimeout) {
					var gallery = this;
					this.slideshowTimeout = setTimeout(function() { gallery.ssAdvance(); }, this.delay);
				}

				return this;
			},

			// Toggles the state of the slideshow (playing/paused)
			toggleSlideshow: function() {
				if (this.isSlideshowRunning)
					this.pause();
				else
					this.play();

				return this;
			},

			// Advances the slideshow to the next image and delegates navigation to the
			// history plugin when history is enabled
			// enableHistory is true
			ssAdvance: function() {
				if (this.isSlideshowRunning)
					this.next(true);

				return this;
			},

			// Advances the gallery to the next image.
			// @param {Boolean} dontPause Specifies whether to pause the slideshow.
			// @param {Boolean} bypassHistory Specifies whether to delegate navigation to the history plugin when history is enabled.
			next: function(dontPause, bypassHistory) {
				this.gotoIndex(this.getNextIndex(this.currentImage.index), dontPause, bypassHistory);
				return this;
			},

			// Navigates to the previous image in the gallery.
			// @param {Boolean} dontPause Specifies whether to pause the slideshow.
			// @param {Boolean} bypassHistory Specifies whether to delegate navigation to the history plugin when history is enabled.
			previous: function(dontPause, bypassHistory) {
				this.gotoIndex(this.getPrevIndex(this.currentImage.index), dontPause, bypassHistory);
				return this;
			},

			// Navigates to the next page in the gallery.
			// @param {Boolean} dontPause Specifies whether to pause the slideshow.
			// @param {Boolean} bypassHistory Specifies whether to delegate navigation to the history plugin when history is enabled.
			nextPage: function(dontPause, bypassHistory) {
				var page = this.getCurrentPage();
				var lastPage = this.getNumPages() - 1;
				if (page < lastPage) {
					var startIndex = page * this.numThumbs;
					var nextPage = startIndex + this.numThumbs;
					this.gotoIndex(nextPage, dontPause, bypassHistory);
				}

				return this;
			},

			// Navigates to the previous page in the gallery.
			// @param {Boolean} dontPause Specifies whether to pause the slideshow.
			// @param {Boolean} bypassHistory Specifies whether to delegate navigation to the history plugin when history is enabled.
			previousPage: function(dontPause, bypassHistory) {
				var page = this.getCurrentPage();
				if (page > 0) {
					var startIndex = page * this.numThumbs;
					var prevPage = startIndex - this.numThumbs;
					this.gotoIndex(prevPage, dontPause, bypassHistory);
				}

				return this;
			},

			// Navigates to the image at the specified index in the gallery
			// @param {Integer} index The index of the image in the gallery to display.
			// @param {Boolean} dontPause Specifies whether to pause the slideshow.
			// @param {Boolean} bypassHistory Specifies whether to delegate navigation to the history plugin when history is enabled.
			gotoIndex: function(index, dontPause, bypassHistory) {
				if (!dontPause)
					this.pause();

				if (index < 0) index = 0;
				else if (index >= this.data.length) index = this.data.length-1;

				var imageData = this.data[index];

				if (!bypassHistory && this.enableHistory)
					$.history.load(String(imageData.hash));  // At the moment, history.load only accepts string arguments
				else
					this.gotoImage(imageData);

				return this;
			},

			// This function is garaunteed to be called anytime a gallery slide changes.
			// @param {Object} imageData An object holding the image metadata of the image to navigate to.
			gotoImage: function(imageData) {
				if (!imageData) return this;
				var index = imageData.index;

				// Prevent reloading same image
				if (this.currentImage && this.currentImage.index == index)
					return this;

				if (this.onSlideChange && this.currentImage)
					this.onSlideChange(this.currentImage.index, index);

				this.currentImage = imageData;
				this.preloadRelocate(index);

				this.refresh();

				return this;
			},

			// Returns the default transition duration value.  The value is halved when not
			// performing a synchronized transition.
			// @param {Boolean} isSync Specifies whether the transitions are synchronized.
			getDefaultTransitionDuration: function(isSync) {
				if (isSync)
					return this.defaultTransitionDuration;
				return this.defaultTransitionDuration / 2;
			},

			// Rebuilds the slideshow image and controls and performs transitions
			refresh: function() {

				var touchDevice = ('ontouchstart' in document.documentElement);
				if (touchDevice && typeof window.orientation !== 'undefined')
				{
					if (typeof this.custom_styles === 'undefined' || !!!this.custom_styles)
					{
						this.custom_styles = document.createElement('style');
						document.head.appendChild(this.custom_styles);
					}

					if (window.orientation == 0)
					{
						this.custom_styles.innerHTML = '#gf_container_' + this.unique_id + ' div.slideshow-container, #gf_container_' + this.unique_id + ' span.image-wrapper a {max-height: 50vh; }';
					}
					else
					{
						this.custom_styles.innerHTML = '#gf_container_' + this.unique_id + ' div.slideshow-container, #gf_container_' + this.unique_id + ' span.image-wrapper a {max-height: 96vh; }';
					}
				}

				var imageData = this.currentImage;
				if (!imageData)
					return this;

				var index = imageData.index;

				// Update Controls
				if (this.$navControlsContainer) {
					this.$navControlsContainer
						.find('div.nav-controls a.prev').attr('href', '#'+this.data[this.getPrevIndex(index)].hash).end()
						.find('div.nav-controls a.next').attr('href', '#'+this.data[this.getNextIndex(index)].hash);
				}

				var previousSlide = this.$imageContainer.find('span.image-wrapper.current').addClass('previous').removeClass('current');
				var previousCaption = 0;

				if (this.$captionContainer) {
					previousCaption = this.$captionContainer.find('span.image-caption.current').addClass('previous').removeClass('current');
				}

				// Perform transitions simultaneously if syncTransitions is true and the next image is already preloaded
				var isSync = this.syncTransitions && imageData.image;

				// Flag we are transitioning
				var isTransitioning = true;
				var gallery = this;

				var transitionOutCallback = function() {
					// Flag that the transition has completed
					isTransitioning = false;

					// Hide the old slide
					previousSlide.hide();//.remove();

					// Hide old caption
					if (previousCaption)
						previousCaption.hide();//.remove();

					if (!isSync) {
						if (imageData.image && imageData.hash == gallery.data[gallery.currentImage.index].hash) {
							gallery.buildImage(imageData, isSync);
						} else {
							// Show loading container
							if (gallery.$loadingContainer) {
								gallery.$loadingContainer.show();
							}
						}
					}
				};

				if (previousSlide.length == 0) {
					// For the first slide, the previous slide will be empty, so we will call the callback immediately
					transitionOutCallback();
				} else {
					if (this.onTransitionOut) {
						this.onTransitionOut(previousSlide, previousCaption, isSync, transitionOutCallback);
					} else {
						//previousSlide.fadeTo(this.getDefaultTransitionDuration(isSync), 0.0, transitionOutCallback);
						previousSlide.css('opacity', 0.0);
						setTimeout(function() {
							typeof transitionOutCallback === 'function' && transitionOutCallback();
						}, this.getDefaultTransitionDuration(isSync));

						//previousCaption && previousCaption.fadeTo(this.getDefaultTransitionDuration(isSync), 0.0);
						previousCaption && previousCaption.css('opacity', 0.0);
					}
				}

				// Go ahead and begin transitioning in of next image
				if (isSync)
					this.buildImage(imageData, isSync);

				if (!imageData.image) {
					var image = new Image();

					// Wire up mainImage onload event
					image.onload = function() {
						imageData.image = this;

						// Only build image if the out transition has completed and we are still on the same image hash
						if (!isTransitioning && imageData.hash == gallery.data[gallery.currentImage.index].hash) {
							gallery.buildImage(imageData, isSync);
						}
					};

					// set alt and src
					image.alt = imageData.title;
					image.src = imageData.slideUrl;

					image.width  = imageData.width;
					image.height = imageData.height;
					image.style.maxWidth  = imageData.width + 'px';
					image.style.maxHeight = (this.slideHeight > imageData.height ? imageData.height + 'px' : '');
				}

				// This causes the preloader (if still running) to relocate out from the currentIndex
				this.relocatePreload = true;

				return this.syncThumbs();
			},

			// Called by the refresh method after the previous image has been transitioned out or at the same time
			// as the out transition when performing a synchronous transition.
			// @param {Object} imageData An object holding the image metadata of the image to build.
			// @param {Boolean} isSync Specifies whether the transitions are synchronized.
			buildImage: function(imageData, isSync) {
				var gallery = this;
				var nextIndex = this.getNextIndex(imageData.index);

				// Check for already created slide
				var newSlide = this.$imageContainer.find('.index_' + imageData.index).removeClass('previous').addClass('current').css({display: 'block', opacity: 0});

				// Construct new hidden span for the image
				if (!newSlide.length)
				{
					newSlide = this.$imageContainer
						.append('<span class="image-wrapper current index_' + imageData.index + '"></span>')
						.find('span.image-wrapper.current').css('opacity', '0');

					// Prevent click if a drag was started (minor drags are not considered, see dragstart_margin parameter)
					var onClick = 'if (gf_gallery_' + this.unique_id + '.mSlider.isDragging) {event.preventDefault(); event.stopPropagation(); return false; }';

					//if (this.enableFancybox && imageData.fancy.attr('href'))
					if (this.enableFancybox && imageData.fancy)
					{
						imageData.fancy.detach().appendTo(newSlide).show();
						//newSlide.append('<a class="fancy-link" href="' + imageData.fancy.attr('href') + '" title="' + imageData.title + '" onclick="' + onClick + '"> </a>');
						//newSlide.find('a').fancybox(this.fancyOptions);
					}
					else
					{
						newSlide.append('<a class="advance-link" rel="history" href="#'+this.data[nextIndex].hash+'" title="'+imageData.title+'" onclick="' + onClick + '"> </a>');
						newSlide.find('a').click(function(e) {
							gallery.clickHandler(e, this);
						});
					}

					newSlide.find('a').prepend(imageData.image);
				}

				var newCaption = 0;
				if (this.$captionContainer)
				{
					newCaption = this.$captionContainer.find('.index_' + imageData.index).removeClass('previous').addClass('current').css({'display': 'block', 'opacity': 0});

					if (!newCaption.length)
					{
						// Construct new hidden caption for the image
						newCaption = this.$captionContainer
							.append('<span class="image-caption current index_' + imageData.index + '" style="opacity: 0"></span>')
							.append(imageData.caption);
					}

					//newCaption.fadeTo(this.getDefaultTransitionDuration(isSync), 1.0);

					newCaption.css('opacity', 1.0);
					setTimeout(function() {
						newCaption.removeClass('transitioning');
					}, this.getDefaultTransitionDuration(isSync));
				}

				// Hide the loading conatiner
				if (this.$loadingContainer) {
					this.$loadingContainer.hide();
				}

				// Transition in the new image
				if (this.onTransitionIn) {
					this.onTransitionIn(newSlide, newCaption, isSync);
				} else {
					//newSlide.fadeTo(this.getDefaultTransitionDuration(isSync), 1.0);
					//newCaption && newCaption.fadeTo(this.getDefaultTransitionDuration(isSync), 1.0);
					newSlide.css('opacity', 1.0);
					newCaption && newCaption.css('opacity', 1.0);
				}

				if (this.isSlideshowRunning) {
					if (this.slideshowTimeout)
						clearTimeout(this.slideshowTimeout);

					this.slideshowTimeout = setTimeout(function() { gallery.ssAdvance(); }, this.delay);
				}

				return this;
			},

			// Returns the current page index that should be shown for the currentImage
			getCurrentPage: function() {
				if(!this.currentImage) return 0;
				return Math.floor(this.currentImage.index / this.numThumbs);
			},

			// Applies the selected class to the current image's corresponding thumbnail.
			// Also checks if the current page has changed and updates the displayed page of thumbnails if necessary.
			syncThumbs: function()
			{
				var $thumbsUl = this.find('ul.thumbs'),
					$thumbs     = $thumbsUl.children(),
					$thumbOld   = $thumbs.filter('.selected'),
					$thumbNew   = $thumbs.eq(this.currentImage.index),
					page        = this.getCurrentPage(),
					pBox        = $thumbsUl.parent().get(0);
					
				// Go to correct page
				if (page != this.displayedPage)
				{
					this.updateThumbs();
				}

				// Scroll to new image
				if (pBox.scrollWidth > pBox.clientWidth)
				{
					var elem_oLeft = $thumbNew.get(0).offsetLeft;
					var elem_width = $thumbNew.outerWidth(true);

					var elem_rightEdge = elem_oLeft + elem_width;
					var pBox_rightEdge = pBox.scrollLeft + pBox.clientWidth;

					//window.console.log('elem_oLeft: ' + elem_oLeft + ' - elem_width: ' + elem_width + ' - elem_rightEdge: ' + elem_rightEdge);
					//window.console.log('pBox.scrollLeft: ' + pBox.scrollLeft + ' - pBox.clientWidth: ' + pBox.clientWidth + ' - pBox_rightEdge: ' + pBox_rightEdge);

					var extra = 3 * elem_width / 4;
					if (pBox_rightEdge < elem_rightEdge + extra)
					{
						//pBox.scrollLeft = elem_rightEdge - pBox.clientWidth + extra;
						$(pBox).animate({ scrollLeft: (elem_rightEdge - pBox.clientWidth + extra) }, $thumbOld.length ? 600 : 0);
					}

					if (elem_oLeft < pBox.scrollLeft + extra)
					{
						//pBox.scrollLeft = elem_oLeft - extra;
						$(pBox).animate({ scrollLeft: (elem_oLeft - extra) }, $thumbOld.length ? 600 : 0);
					}
				}

				// Remove existing selected class and add selected class to new thumb
				$thumbOld.removeClass('selected');
				$thumbNew.addClass('selected');

				return this;
			},

			// Performs transitions on the thumbnails container and updates the set of
			// thumbnails that are to be displayed and the navigation controls.
			// @param {Delegate} postTransitionOutHandler An optional delegate that is called after
			// the thumbnails container has transitioned out and before the thumbnails are rebuilt.
			updateThumbs: function(postTransitionOutHandler) {
				var gallery = this;
				var transitionOutCallback = function() {
					// Call the Post-transition Out Handler
					if (postTransitionOutHandler)
						postTransitionOutHandler();

					gallery.rebuildThumbs();

					// Transition In the thumbsContainer
					if (gallery.onPageTransitionIn)
						gallery.onPageTransitionIn();
					else
						gallery.show();
				};

				// Transition Out the thumbsContainer
				if (this.onPageTransitionOut) {
					this.onPageTransitionOut(transitionOutCallback);
				} else {
					this.hide();
					transitionOutCallback();
				}

				return this;
			},

			// Updates the set of thumbnails that are to be displayed and the navigation controls.
			rebuildThumbs: function() {
				var needsPagination = this.data.length > this.numThumbs;

				// Rebuild top pager
				if (this.enableTopPager) {
					var $topPager = this.find('div.top');
					if ($topPager.length == 0)
						$topPager = this.prepend('<div class="top pagination' + (this.enableTopPager == 2 ? ' gf_inline_nav' : '') + '"></div>').find('div.top');
					else
						$topPager.empty();

					if (needsPagination)
						this.buildPager($topPager);
				}

				// Rebuild bottom pager
				if (this.enableBottomPager) {
					var $bottomPager = this.find('div.bottom');
					if ($bottomPager.length == 0)
						$bottomPager = this.append('<div class="bottom pagination"></div>').find('div.bottom');
					else
						$bottomPager.empty();

					if (needsPagination)
						this.buildPager($bottomPager);
				}

				var page = this.getCurrentPage();
				var startIndex = page*this.numThumbs;
				var stopIndex = startIndex+this.numThumbs-1;
				if (stopIndex >= this.data.length)
					stopIndex = this.data.length-1;

				// Show/Hide thumbs
				var $thumbsUl = this.find('ul.thumbs');
				$thumbsUl.find('li').each(function(i) {
					var $li = $(this);
					if (i >= startIndex && i <= stopIndex) {
						$li.show();
					} else {
						$li.hide();
					}
				});

				this.displayedPage = page;

				// Remove the noscript class from the thumbs container ul
				$thumbsUl.removeClass('noscript');

				return this;
			},

			// Returns the total number of pages required to display all the thumbnails.
			getNumPages: function() {
				return Math.ceil(this.data.length/this.numThumbs);
			},

			// Rebuilds the pager control in the specified matched element.
			// @param {jQuery} pager A jQuery element set matching the particular pager to be rebuilt.
			buildPager: function(pager) {
				var gallery    = this;

				var $thumbsUl  = gallery.find('ul.thumbs');
				var $thumbs    = gallery.find('ul.thumbs li');

				// Find thumbs per page
				this.numThumbs = Math.floor( ($thumbsUl.width() - 1) / $thumbs.first().outerWidth(true) );
				// Balance thumbs in last page
				this.numThumbs = Math.ceil($thumbs.length / Math.ceil($thumbs.length / this.numThumbs));
				
				//window.console.log( ($thumbsUl.width() - 1) + ' - ' + $thumbs.first().outerWidth(true) + ' - ' + ( ($thumbsUl.width() - 1) / $thumbs.first().outerWidth(true) ) );

				var numPages   = this.getNumPages();
				var page       = this.getCurrentPage();
				var startIndex = page * this.numThumbs;
				var pagesRemaining = this.maxPagesToShow - 1;

				var pageNum = page - Math.floor((this.maxPagesToShow - 1) / 2) + 1;
				if (pageNum > 0) {
					var remainingPageCount = numPages - pageNum;
					if (remainingPageCount < pagesRemaining) {
						pageNum = pageNum - (pagesRemaining - remainingPageCount);
					}
				}

				if (pageNum < 0) {
					pageNum = 0;
				}

				// Prev Page Link
				if (page > 0) {
					var prevPage = startIndex - this.numThumbs;
					pager.append('<a rel="history" class="prev_page_btn" href="#'+this.data[prevPage].hash+'" title="'+(this.enableTopPager != 2 ? this.prevPageLinkText : (page > 0 ? page : '-'))+'">'+this.prevPageLinkText+'</a>');
				}
				else {
					pager.append('<span class="ellipsis prev_page_btn">'+this.prevPageLinkText+'</span>');
				}

				if (this.enableTopPager != 2)
				{
					// Create First Page link if needed
					if (pageNum > 0) {
						this.buildPageLink(pager, 0, numPages);
						if (pageNum > 1)
							pager.append('<span class="ellipsis">&hellip;</span>');

						pagesRemaining--;
					}

					// Page Index Links
					while (pagesRemaining > 0) {
						this.buildPageLink(pager, pageNum, numPages);
						pagesRemaining--;
						pageNum++;
					}

					// Create Last Page link if needed
					if (pageNum < numPages) {
						var lastPageNum = numPages - 1;
						if (pageNum < lastPageNum)
							pager.append('<span class="ellipsis">&hellip;</span>');

						this.buildPageLink(pager, lastPageNum, numPages);
					}
				}
				else
				{
					pager.append('<span class="gf_pagination_info">' + (page + 1) + '/' + numPages + '</span>');
				}


				// Next Page Link
				var nextPage = startIndex + this.numThumbs;
				if (nextPage < this.data.length) {
					pager.append('<a rel="history" class="next_page_btn" href="#'+this.data[nextPage].hash+'" title="'+(this.enableTopPager != 2 ? this.nextPageLinkText : (page < numPages ? page+2 : '-'))+'">'+this.nextPageLinkText+'</a>');
				}
				else {
					pager.append('<span class="ellipsis next_page_btn">'+this.nextPageLinkText+'</span>');
				}

				pager.find('a').click(function(e) {
					gallery.clickHandler(e, this);
				});

				return this;
			},

			// Builds a single page link within a pager.  This function is called by buildPager
			// @param {jQuery} pager A jQuery element set matching the particular pager to be rebuilt.
			// @param {Integer} pageNum The page number of the page link to build.
			// @param {Integer} numPages The total number of pages required to display all thumbnails.
			buildPageLink: function(pager, pageNum, numPages) {
				var pageLabel = pageNum + 1;
				var currentPage = this.getCurrentPage();
				if (pageNum == currentPage)
					pager.append('<span class="current">'+pageLabel+'</span>');
				else if (pageNum < numPages) {
					var imageIndex = pageNum*this.numThumbs;
					pager.append('<a rel="history" href="#'+this.data[imageIndex].hash+'" title="'+pageLabel+'">'+pageLabel+'</a>');
				}

				return this;
			}
		});

		// Now initialize the gallery
		$.extend(this, defaults, settings);

		// Verify the history plugin is available
		if (this.enableHistory && !$.history)
			this.enableHistory = false;

		// Verify the fancybox plugin is available
		if (this.enableFancybox && !$.fancybox)
			this.enableFancybox = false;

		// Select containers
		if (this.imageContainerSel) this.$imageContainer = $(this.imageContainerSel);
		if (this.captionContainerSel) this.$captionContainer = $(this.captionContainerSel);
		if (this.loadingContainerSel) this.$loadingContainer = $(this.loadingContainerSel);

		// Initialize the thumbails
		this.initializeThumbs();

		if (this.maxPagesToShow < 3)
			this.maxPagesToShow = 3;

		this.displayedPage = -1;
		var gallery = this;

		// Hide the loadingContainer
		if (this.$loadingContainer)
			this.$loadingContainer.hide();

		// Setup controls
		if (this.ssControlsContainerSel) {
			this.$ssControlsContainer = $(this.ssControlsContainerSel).empty();
			if (this.autoStart) {
				this.$ssControlsContainer
					.append('<div class="ss-controls"><a href="#pause" class="pause" title="'+this.pauseLinkText+'">'+this.pauseLinkText+'</a></div>');
			} else {
				this.$ssControlsContainer
					.append('<div class="ss-controls"><a href="#play" class="play" title="'+this.playLinkText+'">'+this.playLinkText+'</a></div>');
			}

			this.$ssControlsContainer.find('div.ss-controls a')
				.click(function(e) {
					gallery.toggleSlideshow();
					e.preventDefault();
					return false;
				});
		}

		if (this.navControlsContainerSel) {
			this.$navControlsContainer = $(this.navControlsContainerSel).empty();
			this.$navControlsContainer
				.append('<div class="nav-controls"><a class="prev" rel="history" data-title="'+this.prevLinkText+'"><div>'+this.prevLinkText+'</div></a><a class="next" rel="history" data-title="'+this.nextLinkText+'"><div>'+this.nextLinkText+'</div></a></div>')
				.find('div.nav-controls a')
				.click(function(e) {
					gallery.clickHandler(e, this);
				});
		}

		var initFirstImage = !this.enableHistory || !location.hash;
		if (this.enableHistory && location.hash) {
			var hash = $.galleriffic.normalizeHash(location.hash);
			var imageData = allImages[hash];
			if (!imageData)
				initFirstImage = true;
		}

		// Setup gallery to show the first image
		if (initFirstImage)
			this.gotoIndex(0, false, true);

		// Setup Keyboard Navigation
		if (this.enableKeyboardNavigation) {
			$(gallery).keydown(function(e) {
				var key = e.charCode ? e.charCode : e.keyCode ? e.keyCode : 0;
				switch(key) {
					case 32: // space
					case 13: // enter
						gallery.next();
						e.preventDefault();
						break;
					case 33: // Page Up
						gallery.previousPage();
						e.preventDefault();
						break;
					case 34: // Page Down
						gallery.nextPage();
						e.preventDefault();
						break;
					case 35: // End
						gallery.gotoIndex(gallery.data.length-1);
						e.preventDefault();
						break;
					case 36: // Home
						gallery.gotoIndex(0);
						e.preventDefault();
						break;
					case 37: // left arrow
						gallery.previous();
						e.preventDefault();
						break;
					case 39: // right arrow
						gallery.next();
						e.preventDefault();
						break;
				}
			});
		}

		// Resize thumbnails container
		$(window).resize(function()
		{
			gallery.rebuildThumbs();
		});


		/**
		 * TOUCH events for CAROUSEL area
		 */

		var slider = {
			mode: 'horizontal',
			dragstart_margin: 20,
			dragwalk_margin: (this.use_pages ? 100 : 20),
			isDragging: false,
			items_box: this.find('ul.thumbs')
		};

		var boxPos = 0;
		var startPos = 0;
		var prop = slider.mode=='horizontal' ? 'left' : 'top';


		// Prevent click if a drag was started (minor drags are not considered, see dragstart_margin parameter)
		this.find('ul.thumbs li a img').on('click', function(ev)
		{
			// Click event is scheduled/queued after mouseup event, isDragging FLAG is cleared via setTimeout, thus we can use it here to prevent clicks
			if (slider.isDragging)
			{
				var e = ev.originalEvent;
				e.preventDefault();
				e.stopPropagation();
			}
		});

		var startEvents = 'mousedown touchstart';
		this.on(startEvents, function(ev)
		{
			var e = ev.originalEvent;  // Get original event
			if (ev.type=='mousedown')
			{
				// In the case of using mouse events, we need to prevent events for 'mousedown'
				e.preventDefault(); e.stopPropagation();  // this may create undesired effects in some cases
			}

			var obj = ev.type!='touchstart' ? e : e.changedTouches[0]; // reference first touch point for this event

			// Indicate draging by changing mouse cursor
			$(slider.items_box).css('cursor', 'pointer' );

			// Stop all animations and force them to complete, to get proper current position of the slider
			$(slider.items_box).finish();
			boxPos = parseInt($(slider.items_box).css(slider.mode=='horizontal' ? 'left' : 'top'));
			startPos = parseInt(slider.mode=='horizontal' ? obj.clientX : obj.clientY);
			//window.console.log ('Status: mousedown -- Start coordinate: ' + startPos + 'px');
		});


		var moveEvents = 'mousemove touchmove';
		this.on(moveEvents, function(ev)
		{
			var e = ev.originalEvent;  // Get original event
			//e.preventDefault();	//e.stopPropagation();

			var obj = ev.type!='touchmove' ? e : e.changedTouches[0]; // reference first touch point for this event
			var moveStarted = (startPos!=0);
			if (!moveStarted) return;

			var travelDist = parseInt(slider.mode=='horizontal' ? obj.clientX : obj.clientY) - startPos;
			//window.console.log ('Status: mousemove -- Distance traveled: ' + travelDist + 'px');

			// Check if drap distance is over the drag start threshold
			if (!slider.isDragging && Math.abs(travelDist) < slider.dragstart_margin) return;
			slider.isDragging = true;

			// Touch/Mouse Drag is at new point, retarget to new point,
			// Cancel all animations without completing them, to allow continuing from current position, thus avoiding position jump to previous touch position
			$(slider.items_box).stop(true, false);

			(slider.mode=='horizontal')
				? $(slider.items_box).animate({ left: boxPos+travelDist }, 'fast')
				: $(slider.items_box).animate({ top: boxPos+travelDist }, 'fast');
		});


		var endEvents = 'mouseleave mouseup touchend';
		this.on(endEvents, function(ev)
		{
			var e = ev.originalEvent;  // Get original event
			//e.preventDefault(); //e.stopPropagation();

			var obj = ev.type!='touchend' ? e : e.changedTouches[0]; // reference first touch point for this event

			var moveStarted = (startPos!=0);
			if (!moveStarted) return;  // check if initial click was not inside the slider
			var travelDist = parseInt(slider.mode=='horizontal' ? obj.clientX : obj.clientY) - startPos;
			//window.console.log ('Status: mouseup -- End coordinate: ' + (slider.mode=='horizontal' ? obj.clientX : obj.clientY) + 'px');

			$(slider.items_box).css('cursor', 'auto');  // restore mouse pointer

			// Check if drag distance is over the drag walk threshold, and walk the slider to proper direction,
			if (Math.abs(travelDist) > slider.dragwalk_margin)
			{
				//window.console.log('DO MOVE: ' + travelDist + ' ' + gallery.currentImage.index + ' ' + gallery.numThumbs);

				// Cancel all animations without completing them, to allow walking from current position, thus avoiding position jump to last touch position
				$(slider.items_box).stop(true, false);

				// Cancel autoplay, to avoid confusion to the user
				gallery.pause();

				if (this.use_pages)
				{
					var page = gallery.getCurrentPage();
					var do_move_next = ((travelDist < 0) && page < gallery.getNumPages() - 1);
					var do_move_prev = ((travelDist > 0) && page > 0);
					//window.console.log(page + ' ' + (gallery.getNumPages() - 1) + travelDist + ' ' + do_move_next + ' ' + do_move_prev);

					// Walk the slider
					if (do_move_next || do_move_prev)
					{
						// Goto next page but first hide items box to avoid potential ``flashing``
						$(slider.items_box).hide();
						(travelDist < 0) ? gallery.nextPage(true, true) : gallery.previousPage(true, true);

						$(slider.items_box).css(prop, travelDist < 0 ? '50%' : '-50%');

						// Consider delay in hiding currently selected item
						this.find('ul.thumbs li.selected').hide();
					}

					// If having pagination then move back to start
					setTimeout(function()
					{
						$(slider.items_box).show();
						var css = {};
						css[prop] = 0;
						$(slider.items_box).animate(css, 'fast');
					}, 200);
				}
				else
				{
					// Move thumbs container to start (we scrolled the parent of the thumbs container, not the thumbs container itself)
					$(slider.items_box).css(prop, 0);

					// We scrolled the parent of the thumbs container, not the thumbs container itself
					var node = $(slider.items_box).get(0).parentNode;

					//window.console.log ('scrollLeft (before): ' + $(node).scrollLeft() + ' scrollLeft New: ' + ($(node).scrollLeft() - travelDist));
					(slider.mode=='horizontal') ? (node.scrollLeft -= travelDist) : (node.scrollTop += travelDist);
					//window.console.log ('scrollLeft (after): ' + $(node).scrollLeft());
				}
			}

			else
			{
				//window.console.log('CANCEL MOVE: ' + travelDist);

				// Drag is under threshold, return slider to original position before dragging was stated,
				// But first cancel all animations without completing, to allow returning from current position, thus avoiding position jump to last touch position
				$(slider.items_box).stop(true, false);
				var css = {};
				css[prop] = boxPos;
				$(slider.items_box).animate(css, 'fast');
			}

			startPos = 0;
			setTimeout(function(){ slider.isDragging = false; }, 100);
		});



		/**
		 * MAIN AREA IMAGE
		 */
		var mSlider = {
			mode: 'horizontal',
			dragstart_margin: 20,
			dragwalk_margin: (this.use_pages ? 100 : 20),
			isDragging: false,
			items_box: $(this.imageContainerSel)
		};

		this.mSlider = mSlider;

		var mBoxPos = 0;
		var mStartPos = 0;
		var mProp = this.mSlider.mode=='horizontal' ? 'left' : 'top';


		var startEvents = 'mousedown touchstart';
		mSlider.items_box.on(startEvents, function(ev)
		{
			var e = ev.originalEvent;  // Get original event
			if (ev.type=='mousedown')
			{
				// In the case of using mouse events, we need to prevent events for 'mousedown'
				e.preventDefault(); e.stopPropagation();  // this may create undesired effects in some cases
			}

			var obj = ev.type!='touchstart' ? e : e.changedTouches[0]; // reference first touch point for this event

			// Indicate draging by changing mouse cursor
			//mSlider.items_box.css('cursor', (mSlider.mode=='horizontal' ? (mTravelDist<0 ? 'w-resize' : 'e-resize') : (mTravelDist<0 ? 'n-resize' : 's-resize')) );
			mSlider.items_box.css('cursor', 'pointer' );

			// Stop all animations and force them to complete, to get proper current position of the slider
			mSlider.items_box.finish();
			mBoxPos = parseInt(mSlider.items_box.css(mSlider.mode=='horizontal' ? 'left' : 'top'));
			mStartPos = parseInt(mSlider.mode=='horizontal' ? obj.clientX : obj.clientY);
			//window.console.log ('Status: mousedown -- Start coordinate: ' + mStartPos + 'px');
		});


		var moveEvents = 'mousemove touchmove';
		mSlider.items_box.on(moveEvents, function(ev)
		{
			var e = ev.originalEvent;  // Get original event
			//e.preventDefault();	//e.stopPropagation();

			var obj = ev.type!='touchmove' ? e : e.changedTouches[0]; // reference first touch point for this event
			var moveStarted = (mStartPos!=0);
			if (!moveStarted) return;

			var mTravelDist = parseInt(mSlider.mode=='horizontal' ? obj.clientX : obj.clientY) - mStartPos;
			//window.console.log ('Status: mousemove -- Distance traveled: ' + mTravelDist + 'px');

			// Check if drap distance is over the drag start threshold
			if (!mSlider.isDragging && Math.abs(mTravelDist) < mSlider.dragstart_margin) return;
			mSlider.isDragging = true;
			//window.console.log ('Dragging');

			// Touch/Mouse Drag is at new point, retarget to new point,
			// Cancel all animations without completing them, to allow continuing from current position, thus avoiding position jump to previous touch position
			mSlider.items_box.stop(true, false);

			(mSlider.mode=='horizontal')
				? mSlider.items_box.animate({ left: mBoxPos+mTravelDist }, 'fast')
				: mSlider.items_box.animate({ top: mBoxPos+mTravelDist }, 'fast');
		});


		var endEvents = 'mouseleave mouseup touchend';
		mSlider.items_box.on(endEvents, function(ev)
		{
			var e = ev.originalEvent;  // Get original event
			//e.preventDefault(); //e.stopPropagation();

			var obj = ev.type!='touchend' ? e : e.changedTouches[0]; // reference first touch point for this event

			var moveStarted = (mStartPos!=0);
			if (!moveStarted) return;  // check if initial click was not inside the slider
			var mTravelDist = parseInt(mSlider.mode=='horizontal' ? obj.clientX : obj.clientY) - mStartPos;
			//window.console.log ('Status: mouseup -- End coordinate: ' + (mSlider.mode=='horizontal' ? obj.clientX : obj.clientY) + 'px');

			mSlider.items_box.css('cursor', 'auto');  // restore mouse pointer

			// Check if drag distance is over the drag walk threshold, and walk the slider to proper direction,
			if (Math.abs(mTravelDist) > mSlider.dragwalk_margin)
			{
				//window.console.log('DO main MOVE: ' + mTravelDist + ' ' + gallery.currentImage.index + ' ' + gallery.numThumbs);

				// Cancel all animations without completing them, to allow walking from current position, thus avoiding position jump to last touch position
				mSlider.items_box.stop(true, false);

				// Cancel autoplay, to avoid confusion to the user
				gallery.pause();

				if (1)
				{
					// Move container to start (we scrolled it but now need it back to proper position for moving to next image)
					var css = {};
					css[mProp] = 0;
					mSlider.items_box.animate(css, 'slow');

					// Go to next / previous image
					(mTravelDist < 0) ? gallery.next() : gallery.previous();
				}
			}

			else
			{
				//window.console.log('CANCEL main MOVE: ' + mTravelDist);

				// Drag is under threshold, return slider to original position before dragging was stated,
				// But first cancel all animations without completing, to allow returning from current position, thus avoiding position jump to last touch position
				mSlider.items_box.stop(true, false);
				var css = {};
				css[mProp] = mBoxPos;
				mSlider.items_box.animate(css, 'fast');
			}

			mStartPos = 0;
			setTimeout(function(){ mSlider.isDragging = false; }, 100);
		});



		// Auto start the slideshow
		if (this.autoStart)
			this.play();

		// Kickoff Image Preloader after 1 second
		setTimeout(function() { gallery.preloadInit(); }, 1000);

		return this;
	};
})(jQuery);
