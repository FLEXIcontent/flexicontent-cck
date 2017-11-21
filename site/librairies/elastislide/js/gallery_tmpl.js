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


	// Gallery container
	var $rgGallery= jQuery('#rg-gallery_unique_gal_id'),

	// Carousel container
	$esCarousel		= $rgGallery.find('div.es-carousel-wrapper'),

	// The items carousel
	$items				= $esCarousel.find('ul > li'),

	// Total number of items
	itemsCount		= $items.length;


	Gallery = (function()
	{
		// Index of the current item
		var current	= 0,

		// mode : carousel || fullview
		mode 			= 'carousel',

		// control if one image is being loaded
		doing_animation			= false,

		// slideshow options
		slideshow_thumb_size = 'large',
		slideshow_auto_play = 1,
		slideshow_auto_delay = 4000,
		slideshow_transition = 'fade',
		slideshow_easing = 'swing',
		slideshow_easing_inout = 'easeOut',
		slideshow_speed = 600,

		// position of carousel (navigation thubmnails), 0: disable, 1: below, 2: above
		carousel_position = 1,
		carousel_visible = 2,
		
		// other carousel options
		carousel_thumb_width = 120,
		carousel_transition = 'scroll',
		carousel_easing = 'swing',
		carousel_easing_inout = 'easeOut',
		carousel_speed = 600,

		init = function(ops)
		{
			slideshow_thumb_size   = !!ops && typeof ops.slideshow_thumb_size   != 'undefined' ? ops.slideshow_thumb_size   : 'large';
			slideshow_auto_play    = !!ops && typeof ops.slideshow_auto_play    != 'undefined' ? ops.slideshow_auto_play    : 1;
			slideshow_auto_delay   = !!ops && typeof ops.slideshow_auto_delay   != 'undefined' ? ops.slideshow_auto_delay   : 4000;
			slideshow_transition   = !!ops && typeof ops.slideshow_transition   != 'undefined' ? ops.slideshow_transition   : 'cross-fade';
			slideshow_easing       = !!ops && typeof ops.slideshow_easing       != 'undefined' ? ops.slideshow_easing       : 'swing';
			slideshow_easing_inout = !!ops && typeof ops.slideshow_easing_inout != 'undefined' ? ops.slideshow_easing_inout : 'easeOut';
			slideshow_speed        = !!ops && typeof ops.slideshow_speed        != 'undefined' ? ops.slideshow_speed        : 600;

			carousel_position     = !!ops && typeof ops.carousel_position     != 'undefined' ? ops.carousel_position     : 1;
			carousel_visible      = !!ops && typeof ops.carousel_visible      != 'undefined' ? ops.carousel_visible      : 2;

			carousel_thumb_width  = !!ops && typeof ops.carousel_thumb_width  != 'undefined' ? ops.carousel_thumb_width  : 120;
			carousel_transition   = !!ops && typeof ops.carousel_transition   != 'undefined' ? ops.carousel_transition   : 'scroll';
			carousel_easing       = !!ops && typeof ops.carousel_easing       != 'undefined' ? ops.carousel_easing       : 'swing';
			carousel_easing_inout = !!ops && typeof ops.carousel_easing_inout != 'undefined' ? ops.carousel_easing_inout : 'easeOut';
			carousel_speed        = !!ops && typeof ops.carousel_speed        != 'undefined' ? ops.carousel_speed        : 600;
			
			mode = carousel_position && carousel_visible ? 'carousel' : 'fullview';

			// (not necessary) preloading the images here...
			$items.add('<span class="rg-loading"></span>').imagesLoaded( function()
			{
				// add options
				if (carousel_position && carousel_visible != 1) _addViewModes();
				
				// add slideshow image wrapper
				_addImageWrapper();
				
				// show first image
				_showImage( $items.eq( current ) );
					
			});
			
			// initialize the carousel
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
					_navigate( 'right' );

					// Register next autoplay step
					setTimeout(triggerAutoPlay, slideshow_auto_delay);
				}

				// Register 1st autoplay step
				setTimeout(triggerAutoPlay, slideshow_auto_delay);
			}
		},


		_initCarousel = function()
		{
			// we are using the elastislide plugin:
			// http://tympanus.net/codrops/2011/09/12/elastislide-responsive-carousel/
			$esCarousel.show().elastislide({
				imageW 	 : carousel_thumb_width,
				position : carousel_position,
				easing   : carousel_easing,
				easing_inout : carousel_easing_inout,
				speed    : carousel_speed,
				effect   : carousel_transition,
				onClick	 : function( $item )
				{
					// on click show image if not already animating towards another image
					if (doing_animation) return false;
					doing_animation = true;

					_showImage($item);

					// change current
					current	= $item.index();
				}
			});

			// set elastislide's current to current
			$esCarousel.elastislide( 'setCurrent', current );
			
		},


		_addViewModes = function()
		{
			// top right buttons: hide / show carousel
			var $viewfull	= jQuery('<a href="#" class="rg-view-full"></a>'),
				$viewthumbs	= jQuery('<a href="#" class="rg-view-thumbs rg-view-selected"></a>');

			$rgGallery.prepend( jQuery('<div class="rg-view"/>').append( $viewfull ).append( $viewthumbs ) );

			$viewfull.on('click.rgGallery', function( event )
			{
				if( mode === 'carousel' )
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


		_addImageWrapper = function()
		{
			// adds the structure for the slideshow image and the navigation buttons (if total items > 1)
			// also initializes the navigation events
			
			if (carousel_position == 2)
				jQuery('#img-wrapper-tmpl_unique_gal_id').tmpl( {itemsCount : itemsCount} ).appendTo( $rgGallery );
			else
				jQuery('#img-wrapper-tmpl_unique_gal_id').tmpl( {itemsCount : itemsCount} ).prependTo( $rgGallery );

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


		// Navigate to next previous slideshow image
		_navigate = function( dir )
		{
			if (doing_animation) return false;
			doing_animation = true;

			if( dir === 'right' )
			{
				if( current + 1 >= itemsCount )
					current = 0;
				else
					++current;
			}

			else if( dir === 'left' )
			{
				if( current - 1 < 0 )
					current = itemsCount - 1;
				else
					--current;
			}

			_showImage( $items.eq( current ) );
		},


		_showImage = function( $item )
		{
			// Shows the slideshow image that is associated to the $item

			var $loader	= $rgGallery.find('div.rg-loading').show();

			$items.removeClass('selected');
			$item.addClass('selected');

			var $thumb = $item.find('img'),
				imagesrc = $thumb.data(slideshow_thumb_size),
				title = $thumb.data('description');

			var easing_name = slideshow_easing != 'linear' && slideshow_easing != 'swing' ?
				slideshow_easing_inout + slideshow_easing.charAt(0).toUpperCase() + slideshow_easing.slice(1) :
				slideshow_easing;

			$rgGallery.find('div.rg-image img').stop(false, true);

			// Get active image
			var $active = $rgGallery.find('div.rg-image img.active');
			if (!$active.length)
			{
				$active = jQuery('<img src="' + imagesrc + '" class="active" />').hide();
				$rgGallery.find('div.rg-image').append($active);
				$active.ready( function()
				{
					$rgGallery.find('div.rg-image').css('height', $active.height());
					$active.fadeIn(slideshow_speed);
					$loader.hide();
				});

				// First image shown terminate
				return;
			}

			// Add new image if not already added to the container
			var $image = $rgGallery.find('div.rg-image img[src$=\'' + imagesrc + '\']');
			if (!$image.length)
			{
				$image = jQuery('<img src="' + imagesrc + '" />');
				$rgGallery.find('div.rg-image').append($image);
			}

			// Execute after DOM element ready aka image has finished loading (or already loaded)
			$image.ready( function()
			{
				// Force height to fit images
				var height = 0;
				$rgGallery.find('div.rg-image img').each(function() {
					height = jQuery(this).height() > height ? jQuery(this).height() : height;
				});
				$rgGallery.find('div.rg-image').css('height', height);

				$image.css('z-index', 2).show().css({opacity : 0});
				$image.animate( {opacity : 1},
					jQuery.extend( true, [], {
						duration : slideshow_speed,
						easing : easing_name,
						complete : function() {}
					})
				);

				$active.animate( {opacity : 0},
					jQuery.extend( true, [], {
						duration : slideshow_speed,
						easing : easing_name,
						complete : function() {
							$active.hide().css({opacity : 1});
							$active.css('z-index', 1).removeClass('active'); //reset the z-index and unhide the old active image
							$image.css('z-index', 3).addClass('active'); // Make the newly activated image the top one
						}
					})
				);
				//$rgGallery.find('div.rg-image').css('height', $image.height());

				/*$active.fadeOut(slideshow_speed, function() { // Fade out currently active image
					$active.css('z-index', 1).show().removeClass('active'); //reset the z-index and unhide the old active image
					$image.css('z-index', 3).addClass('active'); // Make the newly activated image the top one
				});*/

				if( title )
				{
					$rgGallery.find('div.rg-caption').show().children('p').empty().text( title );
				}
				$loader.hide();

				if( mode === 'carousel' )
				{
					$esCarousel.elastislide( 'reload' );
					$esCarousel.elastislide( 'setCurrent', current );
				}

				doing_animation = false;
			});
		},


		addItems = function( $new )
		{
			$esCarousel.find('ul').append($new);
			$items 		= $items.add( jQuery($new) );
			itemsCount	= $items.length;
			$esCarousel.elastislide( 'add', $new );
		};


		return {
			init : init,
			addItems : addItems
		};

	})();

	Gallery.init(elastislide_options_unique_gal_id);

	/*
	Example to add more items to the gallery:
	
	var $new  = jQuery('<li><a href="#"><img src="images/thumbs/1.jpg" data-large="images/1.jpg" alt="image01" data-description="From off a hill whose concave womb reworded" /></a></li>');
	Gallery.addItems( $new );
	*/
});