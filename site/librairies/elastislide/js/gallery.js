var fc_elastislide_gallery;

jQuery(function() {
	// ======================= imagesLoaded Plugin ===============================
	// https://github.com/desandro/imagesloaded

	// jQuery('#my-container').imagesLoaded(myFunction)
	// execute a callback when all images have loaded.
	// needed because .load() doesn't work on cached images

	// callback function gets image collection as argument
	//  this is the container

	// original: mit license. paul irish. 2010.
	// contributors: Oren Solomianik, David DeSandro, Yiannis Chatzikonstantinou

	jQuery.fn.imagesLoaded = function( callback )
	{
		var $images = this.find('img'),
		len 	= $images.length,
		_this 	= this,
		blank 	= 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

		function triggerCallback()
		{
			callback.call( _this, $images );
		}

		function imgLoaded()
		{
			if ( --len <= 0 && this.src !== blank ){
				setTimeout( triggerCallback );
				$images.off( 'load error', imgLoaded );
			}
		}

		if ( !len )
		{
			triggerCallback();
		}

		$images.on( 'load error',  imgLoaded ).each( function()
		{
			// cached images don't fire load sometimes, so we reset src.
			if (this.complete || this.complete === undefined)
			{
				var src = this.src;
				// webkit hack from http://groups.google.com/group/jquery-dev/browse_thread/thread/eee6ab7b2da50e1f
				// data uri bypasses webkit log warning (thx doug jones)
				this.src = blank;
				this.src = src;
			}
		});

		return this;
	};


	var $rgGallery,  // Gallery container
	$esCarousel,     // Carousel container
	$items,          // The items carousel
	itemsCount,      // Total number of items
	uid_sfx;         // Unique TAG ID Suffix

	fc_elastislide_gallery = (function()
	{
		// Index of the current item
		var current = 0,

		// mode : carousel || fullview
		mode = 'carousel',

		// Flag that indicates if currently transitioning to a new image
		doing_anim = false,

		// Default Slideshow options
		slideshow_thumb_size = 'large',
		slideshow_auto_play = 1,
		slideshow_auto_delay = 4000,
		slideshow_transition = 'fade',
		slideshow_easing = 'swing',
		slideshow_easing_inout = 'easeOut',
		slideshow_speed = 600,

		// Default carousel options (Navigation thubmnails)
		carousel_position = 2,  // Display Position -- 0: disable, 1: below, 2: above
		carousel_visible = 2,   // Initially state  -- 0: closed, 1: open, 2: open with toggler button
		carousel_thumb_width = 90,
		carousel_thumb_margin = 2,
		carousel_thumb_border = 2,
		carousel_transition = 'scroll',
		carousel_easing = 'swing',
		carousel_easing_inout = 'easeOut',
		carousel_speed = 600,

		init = function(ops, uid)
		{
			uid_sfx     = uid;

			$rgGallery  = jQuery('#rg-gallery_' + uid_sfx);
			$esCarousel	= $rgGallery.find('div.es-carousel-wrapper');
			$items      = $esCarousel.find('ul > li');
			itemsCount  = $items.length;

			slideshow_thumb_size   = !!ops && typeof ops.slideshow_thumb_size   != 'undefined' ? ops.slideshow_thumb_size   : 'large';
			slideshow_auto_play    = !!ops && typeof ops.slideshow_auto_play    != 'undefined' ? ops.slideshow_auto_play    : 1;
			slideshow_auto_delay   = !!ops && typeof ops.slideshow_auto_delay   != 'undefined' ? ops.slideshow_auto_delay   : 4000;
			slideshow_transition   = !!ops && typeof ops.slideshow_transition   != 'undefined' ? ops.slideshow_transition   : 'cross-fade';
			slideshow_easing       = !!ops && typeof ops.slideshow_easing       != 'undefined' ? ops.slideshow_easing       : 'swing';
			slideshow_easing_inout = !!ops && typeof ops.slideshow_easing_inout != 'undefined' ? ops.slideshow_easing_inout : 'easeOut';
			slideshow_speed        = !!ops && typeof ops.slideshow_speed        != 'undefined' ? ops.slideshow_speed        : 600;

			carousel_position     = !!ops && typeof ops.carousel_position     != 'undefined' ? ops.carousel_position     : 2;
			carousel_visible      = !!ops && typeof ops.carousel_visible      != 'undefined' ? ops.carousel_visible      : 2;

			carousel_thumb_width  = !!ops && typeof ops.carousel_thumb_width  != 'undefined' ? ops.carousel_thumb_width  : 90;
			carousel_thumb_margin = !!ops && typeof ops.carousel_thumb_margin != 'undefined' ? ops.carousel_thumb_margin : 2;
			carousel_thumb_border = !!ops && typeof ops.carousel_thumb_border != 'undefined' ? ops.carousel_thumb_border : 2;
			carousel_transition   = !!ops && typeof ops.carousel_transition   != 'undefined' ? ops.carousel_transition   : 'scroll';
			carousel_easing       = !!ops && typeof ops.carousel_easing       != 'undefined' ? ops.carousel_easing       : 'swing';
			carousel_easing_inout = !!ops && typeof ops.carousel_easing_inout != 'undefined' ? ops.carousel_easing_inout : 'easeOut';
			carousel_speed        = !!ops && typeof ops.carousel_speed        != 'undefined' ? ops.carousel_speed        : 600;

			mode = carousel_position && carousel_visible ? 'carousel' : 'fullview';

			// Schedule this to execute when all thumbnails of carousel have been loaded
			$items.add('<span class="rg-loading"></span>').imagesLoaded( function()
			{
				// Add slideshow image wrapper (large image wrapper)
				_addImageWrapper();

				// Add toggler buttons that hide / show the carousel container
				if (carousel_position && carousel_visible != 1)
				{
					_addViewModes();
				}

				// Show first image
				doing_anim = true;
				_showImage( $items.eq( current ) );

			});

			// Initialize the carousel
			if (mode === 'carousel')
			{
				_initCarousel();
			}

			// Handle slideshow autoplay
			if (slideshow_auto_play)
			{
				function triggerAutoPlay()
				{
					// Navigate to next image
					_navigate('right');

					// Register next autoplay step
					setTimeout(triggerAutoPlay, slideshow_auto_delay);
				}

				// Register 1st autoplay step
				setTimeout(triggerAutoPlay, slideshow_auto_delay);
			}
		},


		_initCarousel	= function()
		{
			// we are using the elastislide plugin:
			// http://tympanus.net/codrops/2011/09/12/elastislide-responsive-carousel/
			$esCarousel.show().elastislide({
				unique_id: uid_sfx,
				imageW 	 : carousel_thumb_width,
				margin 	 : carousel_thumb_margin,
				border 	 : carousel_thumb_border,
				position : carousel_position,
				easing   : carousel_easing,
				easing_inout : carousel_easing_inout,
				speed    : carousel_speed,
				effect   : carousel_transition,
				onClick  : function( $item )
				{
					// On click show image if not already animating towards another image
					if (doing_anim)
					{
						return false;
					}

					// Set animation Flag, then show item and set reference of current item
					doing_anim = true;
					_showImage($item);
				}
			});

			// set elastislide's current to current
			$esCarousel.elastislide( 'setCurrent', current );

		},


		// Adds toggler buttons that hide / show the carousel container
		_addViewModes = function()
		{
			// top right buttons: hide / show carousel
			var $viewfull	= jQuery('<a href="#" class="rg-view-full"></a>'),
				$viewthumbs	= jQuery('<a href="#" class="rg-view-thumbs rg-view-selected"></a>');

			if ($rgGallery.hasClass('rg-bottom'))
				$rgGallery.append( jQuery('<div class="rg-view"/>').append( $viewthumbs ).append( $viewfull ) );
			else
				$rgGallery.prepend( jQuery('<div class="rg-view"/>').append( $viewthumbs ).append( $viewfull ) );

			$viewfull.on('click.rgGallery', function( event )
			{
				if (mode === 'carousel')
				{
					$esCarousel.elastislide( 'destroy' );
				}
				$esCarousel.hide();
				$viewfull.addClass('rg-view-selected');
				$viewthumbs.removeClass('rg-view-selected');
				mode	= 'fullview';
				return false;
			});

			$viewthumbs.on('click.rgGallery', function( event )
			{
				_initCarousel();
				$viewthumbs.addClass('rg-view-selected');
				$viewfull.removeClass('rg-view-selected');
				mode	= 'carousel';
				return false;
			});

			if (mode === 'fullview')
			{
				$viewfull.trigger('click');
			}
		},


		// Adds the structure for the slideshow image and the navigation (carousel) buttons (if total items > 1), initializing the navigation events
		_addImageWrapper = function()
		{
			if (carousel_position == 2)
				jQuery('#img-wrapper-tmpl_' + uid_sfx).tmpl( {itemsCount : itemsCount} ).appendTo( $rgGallery );
			else
				jQuery('#img-wrapper-tmpl_' + uid_sfx).tmpl( {itemsCount : itemsCount} ).prependTo( $rgGallery );

			if( itemsCount > 1 )
			{
				// Add navigation
				var $navPrev	= $rgGallery.find('a.rg-image-nav-prev'),
					$navNext		= $rgGallery.find('a.rg-image-nav-next'),
					$imgWrapper	= $rgGallery.find('div.rg-image');

				$navPrev.on('click.rgGallery', function( event )
				{
					_navigate( 'left' );
					return false;
				});

				$navNext.on('click.rgGallery', function( event )
				{
					_navigate( 'right' );
					return false;
				});

				// Add touchwipe events on the slideshow image wrapper
				$imgWrapper.touchwipe({
					wipeLeft			: function()
					{
						_navigate( 'right' );
					},
					wipeRight			: function()
					{
						_navigate( 'left' );
					},
					preventDefaultEvents: false
				});

				jQuery(document).on('keyup.rgGallery', function( event )
				{
					if (event.keyCode == 39)
						_navigate( 'right' );
					else if (event.keyCode == 37)
						_navigate( 'left' );
				});
			}
		},


		// Navigate to next / previous slideshow image
		_navigate = function( dir )
		{
			if (doing_anim)
			{
				return false;
			}

			if (dir === 'right')
			{
				var newCurrent = current + 1 >= itemsCount ? 0 : current + 1;
			}
			else if (dir === 'left')
			{
				var newCurrent = current - 1 < 0 ? itemsCount - 1 : current - 1;
			}

			doing_anim = true;
			_showImage( $items.eq( newCurrent ) );
		},


		// Navigate to specific carousel item and show the image that is associated to the $item
		_showImage = function( $item )
		{
			// Set new current item !!!
			current	= $item.index();

			var $loader	= $rgGallery.find('div.rg-loading').show();

			// Ignore click to already active image
			if ($item.hasClass('selected'))
			{
				$loader.hide();
				doing_anim = false;
				return;
			}

			$items.removeClass('selected');
			$item.addClass('selected');

			var $thumb = $item.find('img'),
				imagew = $thumb.attr('width') || '',
				imageh = $thumb.attr('height') || '',
				imagesrc = $thumb.data(slideshow_thumb_size),
				title = $thumb.data('title'),
				desc = $thumb.data('description');

			var easing_name = slideshow_easing != 'linear' && slideshow_easing != 'swing' ?
				slideshow_easing_inout + slideshow_easing.charAt(0).toUpperCase() + slideshow_easing.slice(1) :
				slideshow_easing;

			$rgGallery.find('div.rg-image img').stop(false, true);

			// Get active image
			var $active = $rgGallery.find('div.rg-image img.active');

			// First image shown handle differently
			if (!$active.length)
			{
				var $image = jQuery('<img src="' + imagesrc + '" width="' + imagew + '" height="' + imageh + '" class="active" />');

				$rgGallery.find('div.rg-image').append($image);
			}
			else
			{
				// Find new image
				var $image = $rgGallery.find('div.rg-image img[src$=\'' + imagesrc + '\']');

				// Add new image if not already added to the container
				if (!$image.length)
				{
					$image = jQuery('<img src="' + imagesrc + '" width="' + imagew + '" height="' + imageh + '" />');
					$rgGallery.find('div.rg-image').append($image);
				}
			}

			$rgGallery.find('div.rg-caption').hide().children('p').empty();
			if ($image.get(0).complete)
				setCaption($image, title, desc);
			else
				$image.on('load', function(){ setCaption($image, title, desc); });

			// Execute after DOM element ready aka image has finished loading (or already loaded)
			$image.ready( function()
			{
				// Hide loader animation
				$loader.hide();

				if ($active.length)
				{
					// Animate new image placing it on top of currently active
					$image.css('z-index', 2).show().css({opacity : 0});
					$image.animate( {opacity : 1},
						jQuery.extend( true, [], {
							duration : slideshow_speed,
							easing : easing_name,
							complete : function() {}
						})
					);

					// Animate previous image
					$active.css('z-index', 1);
					$active.animate( {opacity : 0},
						jQuery.extend( true, [], {
							duration : slideshow_speed,
							easing : easing_name,
							complete : function() {
								$active.hide().css({opacity : 1});
								$active.removeClass('active');
								$image.addClass('active');
							}
						})
					);

					// Update selected image in carousel
					if (mode === 'carousel')
					{
						$esCarousel.elastislide( 'reload' );
						$esCarousel.elastislide( 'setCurrent', current );
					}
				}

				// Image loaded and carousel item selected, turn off flag
				doing_anim = false;
			});
		},

		setCaption = function($image, title, desc)
		{
			// Show caption
			var captionHtml = (title ? '<span class="rg-caption-title">' + title + '</span>' : '') + (desc ? '<span class="rg-caption-desc">' + desc + '</span>' : '');

			captionHtml
				? $rgGallery.find('div.rg-caption').hide().children('p').empty().html(captionHtml)
				: $rgGallery.find('div.rg-caption').hide().children('p').empty();
				
				if (captionHtml)
				{
					var caption = $rgGallery.find('div.rg-caption').show().children('p'),
						caption_el = caption.get(0),
						slide = $image.parent(),
						slideImage = $image
						slidesBox = slide.closest('.rg-gallery')
						hasBottomCarousel = slidesBox.hasClass('rg-bottom') ;

					var left = Math.ceil((slide.width() - slideImage.width()) / 2),
						offTop = slideImage.get(0).offsetTop,
						height = Math.floor( slideImage.outerHeight(true) ),
						bottom = hasBottomCarousel ? 0: (Math.ceil(slide.height() - slideImage.outerHeight(true))),
						left   = Math.ceil((slide.width() - slideImage.width()) / 2),
						width  = slideImage.width() < slide.width() ? slideImage.width() : slide.width()
						cMarg  = parseInt(caption.css('marginLeft')) + parseInt(caption.css('marginRight'));

					caption_el.style.width = (width - cMarg) + 'px';
					caption_el.style.display = 'block';
					caption_el.style.bottom = (bottom - (hasBottomCarousel ? 0 : offTop)) + 'px';
					caption_el.style.left = left + 'px';

					$rgGallery.find('div.rg-caption').show();
				}
		},


		addItems = function( $new )
		{
			$esCarousel.find('ul').append($new);
			$items     = $items.add( jQuery($new) );
			itemsCount = $items.length;
			$esCarousel.elastislide( 'add', $new );
		};


		return {
			init : init,
			addItems : addItems
		};

	})();

	/*
	Example to add more items to the gallery:

	var $new  = jQuery('<li><a href="#"><img src="images/thumbs/1.jpg" data-large="images/1.jpg" alt="image01" data-description="From off a hill whose concave womb reworded" /></a></li>');
	gal_obj.addItems( $new );
	*/
});
