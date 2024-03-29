<?php  // *** DO NOT EDIT THIS FILE, CREATE A COPY !!

/**
 * (Inline) Gallery layout  --  Elastislide
 *
 * This layout does not support inline_info, pretext, posttext
 *
 * Note: This layout uses a thumbnail list created with -- large -- size thubmnails, these will be then thumbnailed by the JS gallery code
 * Note: This is an inline carousel gallery (Responsive image gallery with togglable thumbnail-strip, plus previewer and description)
 */

if ($is_ingroup)
{
	$field->{$prop}[] = 'Usage of this gallery inside field-group not possible, outer container can not be added';

	return;
}


// ***
// *** Values loop
// ***

$i = -1;
foreach ($values as $n => $value)
{
	// Include common layout code for preparing values, but you may copy here to customize
	$result = include( JPATH_ROOT . '/plugins/flexicontent_fields/image/tmpl_common/prepare_value_display.php' );
	if ($result === _FC_CONTINUE_) continue;
	if ($result === _FC_BREAK_) break;

	// Inform about smaller image sizes than the current selected
	$srcset = array();
	$_sizes = array();

	$img_size_attrs = '';

	$size_w_s = isset($value['size_w_s']) ? $value['size_w_s'] : 0;
	$size_h_s = isset($value['size_h_s']) ? $value['size_h_s'] : 0;
	$size_w_m = isset($value['size_w_m']) ? $value['size_w_m'] : 0;
	$size_h_m = isset($value['size_h_m']) ? $value['size_h_m'] : 0;
	$size_w_l = isset($value['size_w_l']) ? $value['size_w_l'] : 0;
	$size_h_l = isset($value['size_h_l']) ? $value['size_h_l'] : 0;

	if ($size === 'l')
	{
		$w_l = $size_w_l ?: $field->parameters->get('w_l', self::$default_widths['l']);
		$srcset[] = \Joomla\CMS\Uri\Uri::root() . $srcl . ' ' . $w_l . 'w';
		$_sizes[] = '(min-width: ' . $w_l . 'px) ' . $w_l . 'px';
	}

	if ($size === 'l' || $size === 'm')
	{
		$w_m = $size_w_m ?: $field->parameters->get('w_m', self::$default_widths['m']);
		$srcset[] = \Joomla\CMS\Uri\Uri::root() . $srcm . ' ' . $w_m . 'w';
		$_sizes[] = '(min-width: ' . $w_m . 'px) ' . $w_m . 'px';

		$w_s = $size_w_s ?: $field->parameters->get('w_s', self::$default_widths['s']);
		$srcset[] = \Joomla\CMS\Uri\Uri::root() . $srcs . ' ' . $w_s . 'w';
		$_sizes[] = $w_s . 'px';
	}

	if (count($srcset))
	{
		$img_size_attrs .= ' srcset="' . implode(', ', $srcset) . '"';
		$img_size_attrs .= ' sizes="' . implode(', ', $_sizes) . '"';
	}

	// Inform browser of real images sizes and of desired image size
	$img_size_attrs .= ' width="' . $w . '" height="' . $h . '" style="height: auto; max-width: 100%;" ';

	$img_legend_custom ='
		<img src="'.\Joomla\CMS\Uri\Uri::root(true).'/'.$src.'" alt="' . $alt_encoded . '"' . $legend . ' class="' . $class . '"
			' . $img_size_attrs . '
			data-medium="' . \Joomla\CMS\Uri\Uri::root(true).'/'.$srcm . '"
			data-large="' . \Joomla\CMS\Uri\Uri::root(true).'/'.$srcl . '"
			data-title="' . $title_encoded . '"
			data-description="' . $desc_encoded . '" itemprop="image"/>
	';
	$group_str = $group_name ? 'rel="['.$group_name.']"' : '';
	$field->{$prop}[] =
		'<li><a href="javascript:;" class="fc_image_thumb">
			'.$img_legend_custom.'
		</a></li>';
}



// ***
// *** Add per field custom JS
// ***

if (!isset(static::$js_added[$field->id][__FILE__]))
{
	flexicontent_html::loadFramework('elastislide');

	static::$js_added[$field->id][__FILE__] = array();
}

// ***
// *** Add - per (field, item) pair - custom JS
// ***

if (!isset(static::$js_added[$field->id][__FILE__][$item->id]))
{
	$uid = 'es_'.$field_name_js."_fcitem".$item->id;
	
	/**
	 * Slideshow configuration
	 */

	$slideshow_thumb_size   = $field->parameters->get( $PPFX_ . 'slideshow_thumb_size', 'large' );
	$slideshow_thumb_height = (int) $field->parameters->get( $PPFX_ . 'slideshow_thumb_height', 600 );
	$slideshow_auto_play    = (int) $field->parameters->get( $PPFX_ . 'slideshow_auto_play', 0 );
	$slideshow_auto_delay   = (int) $field->parameters->get( $PPFX_ . 'slideshow_auto_delay', 4000 );
	$slideshow_transition   = $field->parameters->get( $PPFX_ . 'slideshow_transition', 'cross-fade' );
	$slideshow_easing       = $field->parameters->get( $PPFX_ . 'slideshow_easing', 'swing');
	$slideshow_easing_inout = $field->parameters->get( $PPFX_ . 'slideshow_easing_inout', 'easeOut' );
	$slideshow_speed        = (int) $field->parameters->get( $PPFX_ . 'slideshow_speed', 600 );  // Transition Duration
	$slideshow_popup        = (int) $field->parameters->get( $PPFX_ . 'slideshow_popup', 1 );


	/**
	 * Carousel configuration
	 */

	// Carousel Buttons (Togglers)
	$carousel_position     = (int) $field->parameters->get( $PPFX_ . 'carousel_position', 2 );
	$carousel_visible      = (int) $field->parameters->get( $PPFX_ . 'carousel_visible', 2 );
	// Carousel Thumbnails
	$carousel_thumb_size   = $field->parameters->get( $PPFX_ . 'carousel_thumb_size', 's' );
	$carousel_thumb_width  = (int) $field->parameters->get( $PPFX_ . 'carousel_thumb_width', 90 );
	$carousel_thumb_height = (int) $field->parameters->get( $PPFX_ . 'carousel_thumb_height', 90 );
	$carousel_thumb_border = (int) $field->parameters->get( $PPFX_ . 'carousel_thumb_border', 2 );
	$carousel_thumb_margin = (int) $field->parameters->get( $PPFX_ . 'carousel_thumb_margin', 2 );
	// Carousel Transition
	$carousel_transition   = $field->parameters->get( $PPFX_ . 'carousel_transition', 'scroll' );
	$carousel_easing       = $field->parameters->get( $PPFX_ . 'carousel_easing', 'swing');
	$carousel_easing_inout = $field->parameters->get( $PPFX_ . 'carousel_easing_inout', 'easeOut' );
	$carousel_speed        = (int) $field->parameters->get( $PPFX_ . 'carousel_speed', 600 );


	\Joomla\CMS\Factory::getDocument()->addScriptDeclaration('
	jQuery(document).ready(function()
	{
		var elastislide_options_'.$uid.' = {
			slideshow_thumb_size: \'' . $slideshow_thumb_size . '\',
			slideshow_auto_play: ' . $slideshow_auto_play . ',
			slideshow_auto_delay: ' . $slideshow_auto_delay . ',
			slideshow_transition: \'' . $slideshow_transition . '\',
			slideshow_easing: \'' . $slideshow_easing . '\',
			slideshow_easing_inout: \'' . $slideshow_easing_inout . '\',
			slideshow_speed: ' . $slideshow_speed . ',

			carousel_position: ' . $carousel_position . ',
			carousel_visible: ' . $carousel_visible . ',

			carousel_thumb_width: ' . $carousel_thumb_width . ',
			carousel_thumb_border: ' . $carousel_thumb_border . ',
			carousel_thumb_margin: ' . $carousel_thumb_margin . ',
			carousel_transition: \'' . $carousel_transition . '\',
			carousel_easing: \'' . $carousel_easing . '\',
			carousel_easing_inout: \'' . $carousel_easing_inout . '\',
			carousel_speed: ' . $carousel_speed . '
		};

		fc_elastislide_gallery.init(elastislide_options_'.$uid.', "'.$uid.'");
	});
	');

	\Joomla\CMS\Factory::getDocument()->addCustomTag('
	<script id="img-wrapper-tmpl_'.$uid.'" type="text/x-jquery-tmpl">
		<div class="rg-image-wrapper">
			{{if itemsCount > 1}}
				<div class="rg-image-nav">
					<a href="javascript:;" class="rg-image-nav-prev"><div>'.\Joomla\CMS\Language\Text::_('FLEXI_PREVIOUS').'</div></a>
					<a href="javascript:;" class="rg-image-nav-next"><div>'.\Joomla\CMS\Language\Text::_('FLEXI_NEXT').'</div></a>
				</div>
			{{/if}}
			<div class="rg-image"></div>
			<div class="rg-loading"></div>
			<div class="rg-caption-wrapper">
				<div class="rg-caption" style="display:none;" ontouchstart="e.preventDefault();">
					<p></p>
				</div>
			</div>
		</div>
	</script>
	');

	static::$js_added[$field->id][__FILE__][$item->id] = true;
}



/**
 * Include common layout code before finalize values
 */

$result = include( JPATH_ROOT . '/plugins/flexicontent_fields/image/tmpl_common/before_values_finalize.php' );
if ($result !== _FC_RETURN_)
{
	// ***
	// *** Add container HTML (if required by current layout) and add value separator (if supported by current layout), then finally apply open/close tags
	// ***

	// Add container HTML
	// (note: we will use large image thumbnail as preview, JS will size them done)
	$uid = 'es_'.$field_name_js."_fcitem".$item->id;
	$field->{$prop} = '
	<style>
		div#rg-gallery_'.$uid.'.rg-gallery > .rg-image-wrapper { height: ' . $slideshow_thumb_height . 'px; }

		div#rg-gallery_' . $uid . ' .es-carousel ul li,
		div#rg-gallery_' . $uid . ' .es-carousel ul li a {
			width:' . ($carousel_thumb_width + 2 * $carousel_thumb_border ). 'px !important;
			height:' . ($carousel_thumb_height + 2 * $carousel_thumb_border ). 'px !important;
		}
		div#rg-gallery_' . $uid . ' .es-carousel ul li a img {
			width:' . $carousel_thumb_width . 'px !important;
		}
	</style>

	<div id="rg-gallery_'.$uid.'" class="rg-gallery' . ($carousel_position === 1 ? ' rg-bottom' : '') . '" >
		<div class="rg-thumbs">
			<!-- Elastislide Carousel Thumbnail Viewer -->
			<div class="es-carousel-wrapper">
				<div class="es-carousel">
					<ul>
						' . implode('', $field->{$prop}) . '
					</ul>
				</div>
			</div>
			<!-- End Elastislide Carousel Thumbnail Viewer -->
		</div><!-- rg-thumbs -->
	</div><!-- rg-gallery -->
	';

	// Apply open/close tags
	$field->{$prop}  = $opentag . $field->{$prop} . $closetag;
}
