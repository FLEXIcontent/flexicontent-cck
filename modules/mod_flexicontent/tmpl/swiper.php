<?php
/**
 * @version 1.5 stable $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();

$tooltip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';

$mod_width_feat 	= (int)$params->get('mod_width_feat', 110);
$mod_height_feat 	= (int)$params->get('mod_height_feat', 110);

$mod_width 				= (int)$params->get('mod_width', 80);
$mod_height 			= (int)$params->get('mod_height', 80);

$hide_label_onempty_feat = (int)$params->get('hide_label_onempty_feat', 0);
$hide_label_onempty      = (int)$params->get('hide_label_onempty', 0);

$hl_items_onnav_feat = (int)$params->get($layout.'_hl_items_onnav_feat', 0);
$mod_do_hlight_feat = '';
$mod_do_hlight_feat .= $hl_items_onnav_feat == 1 || $hl_items_onnav_feat == 3 ? ' mod_hl_active' : '';
$mod_do_hlight_feat .= $hl_items_onnav_feat == 2 || $hl_items_onnav_feat == 3 ? ' mod_hl_hover' : '';

$hl_items_onnav = (int)$params->get($layout.'_hl_items_onnav', 0);
$mod_do_hlight = '';
$mod_do_hlight .= $hl_items_onnav == 1 || $hl_items_onnav == 3 ? ' mod_hl_active' : '';
$mod_do_hlight .= $hl_items_onnav == 2 || $hl_items_onnav == 3 ? ' mod_hl_hover' : '';

// Item Dimensions featured
$inner_inline_css_feat = (int)$params->get($layout.'_inner_inline_css_feat', 0);
$padding_top_bottom_feat = $params->get($layout.'_padding_top_bottom_feat', '0');
$padding_left_right_feat = $params->get($layout.'_padding_left_right_feat', '0');
$margin_top_bottom_feat = $params->get($layout.'_margin_top_bottom_feat', '2rem');
$margin_left_right_feat = $params->get($layout.'_margin_left_right_feat', '2rem');
$border_width_feat = $params->get($layout.'_border_width_feat', '1px');
$border_style_feat = $params->get($layout.'_border_style_feat', 'solid');
$border_color_feat = $params->get($layout.'_border_color_feat', '#cccccc');
$border_radius_feat = $params->get($layout.'_border_radius_feat', '0');
$item_column_mode_feat = (int)$params->get($layout.'_column_mode_feat', 0);// 0 column mode old, 1 grid minmax size
$item_width_feat = $params->get($layout.'_item_width_feat', '200px');
$item_fit_feat = $params->get($layout.'_content_width_fit_feat', 'auto-fill');
$item_height_feat = $params->get($layout.'_content_height_fit_feat', 1); //0 Content height, 1 Force same height

// Item Dimensions standard
$inner_inline_css_std = (int)$params->get($layout.'_inner_inline_css', 0);
$padding_top_bottom_std = $params->get($layout.'_padding_top_bottom', '8px');
$padding_left_right_std = $params->get($layout.'_padding_left_right', '12px');
$margin_top_bottom_std = $params->get($layout.'_margin_top_bottom', '0');
$margin_left_right_std = $params->get($layout.'_margin_left_right', '0');
$border_width_std = $params->get($layout.'_border_width', '0');
$border_style_std = $params->get($layout.'_border_style', 'solid');
$border_color_std = $params->get($layout.'_border_color', '#cccccc');
$border_radius_std = $params->get($layout.'_border_radius', '0');
$item_height_std = $params->get($layout.'_content_height_fit_std', 1); //0 Content height, 1 Force same height


// *****************************************************
// Content placement and default image of featured items
// *****************************************************
$content_display_feat = $params->get($layout.'_content_display_feat', 0);  // 0: always visible, 1: On mouse over / item active, 2: On mouse over
$content_layout_feat = $params->get($layout.'_content_layout_feat', 3);  // 0/1: floated (right/left), 2/3: cleared (above/below), 4/5/6: overlayed (top/bottom/full)
$item_img_fit_feat = $params->get($layout.'_img_fit_feat', 1);   // 0: Auto-fit, 1: Auto-fit and stretch to larger

switch ($content_layout_feat) {
	case 0: case 1:
		$img_container_class_feat = ($content_layout_feat==0 ? 'fc_float_left' : 'fc_float_right');
		$content_container_class_feat = 'fc_floated';
		break;
	case 2: case 3:
		$img_container_class_feat = 'fc_stretch fc_clear';
		$content_container_class_feat = '';
		break;
	case 4: case 5: case 6:
		$img_container_class_feat = 'fc_stretch';
		$content_container_class_feat = 'fc_overlayed '
			.($content_layout_feat==4 ? 'fc_top' : '')
			.($content_layout_feat==5 ? 'fc_bottom' : '')
			.($content_layout_feat==6 ? 'fc_full' : '')
			;
		if ($content_display_feat >= 1) $content_container_class_feat .= ' fc_auto_show';
		if ($content_display_feat == 1) $content_container_class_feat .= ' fc_show_active';
		break;
	default: $img_container_class_feat = '';  break;
}



// ***
// *** Content placement and default image of standard items
// ***
$content_display_std = $params->get($layout.'_content_display', 0);  // 0: always visible, 1: On mouse over / item active, 2: On mouse over
$content_layout_std = $params->get($layout.'_content_layout', 3);  // 0/1: floated (right/left), 2/3: cleared (above/below), 4/5/6: overlayed (top/bottom/full)
$item_img_fit_std = $params->get($layout.'_img_fit', 1);   // 0: Auto-fit, 1: Auto-fit and stretch to larger

switch ($content_layout_std) {
	case 0: case 1:
		$img_container_class_std = ($content_layout_std==0 ? 'fc_float_left' : 'fc_float_right');
		$content_container_class_std = 'fc_floated';
		break;
	case 2: case 3:
		$img_container_class_std = 'fc_stretch fc_clear';
		$content_container_class_std = '';
		break;
	case 4: case 5: case 6:
		$img_container_class_std = 'fc_stretch';
		$content_container_class_std = 'fc_overlayed '
			.($content_layout_std ==4 ? 'fc_top' : '')
			.($content_layout_std ==5 ? 'fc_bottom' : '')
			.($content_layout_std ==6 ? 'fc_full' : '')
			;
		if ($content_display_std >= 1) $content_container_class_std .= ' fc_auto_show';
		if ($content_display_std == 1) $content_container_class_std .= ' fc_show_active';
		break;
	default: $img_container_class_std = '';  break;
}



// ***
// *** Default image and image fitting
// ***
$mod_default_img_path = $params->get('mod_default_img_path', 'components/com_flexicontent/assets/images/image.png');
$img_path = \Joomla\CMS\Uri\Uri::base(true) .'/'; 

// image of FEATURED items, auto-fit and (optionally) limit to image max-dimensions to avoid stretching
$img_auto_dims_css_feat =" width: 100%; height: auto; display: block !important; border: 0 !important;";

// image of STANDARD items, auto-fit and (optionally) limit to image max-dimensions to avoid stretching
$img_auto_dims_css_std =" width: 100%; height: auto; display: block !important; border: 0 !important;";


/**
 * Parameters for Swiper JS configuration
 */

// Helper: parse Flexi yes/no string params
if ( !function_exists('fc_swiper_mod_yn') )
{
	function fc_swiper_mod_yn($v, $default = 0)
	{
		$v = $v === null ? $default : $v;
		if (is_bool($v)) return (int) $v;
		if (is_string($v))
		{
			if ($v === 'true'  || $v === '1') return 1;
			if ($v === 'false' || $v === '0') return 0;
		}
		return (int) $v;
	}
}

// Carousel playback / appearance
$sw_autoplay        = fc_swiper_mod_yn( $params->get($layout.'_autoplay', '0'), 0 );
$sw_autoplay_delay  = (int) $params->get($layout.'_autoplay_delay', 3000 );
$sw_autoplay_hover  = fc_swiper_mod_yn( $params->get($layout.'_autoplay_hover', 'false'), 0 );
$sw_loop            = fc_swiper_mod_yn( $params->get($layout.'_loop_mode', 'false'), 0 );
$sw_rewind          = fc_swiper_mod_yn( $params->get($layout.'_rewind', 'false'), 0 );
$sw_speed           = (int) $params->get($layout.'_speed', 300 );
$sw_show_pagination = fc_swiper_mod_yn( $params->get($layout.'_show_pagination', 'true'), 1 );
$sw_pag_type        = $params->get($layout.'_pagination_type', 'bullets' );
$sw_pag_clickable   = fc_swiper_mod_yn( $params->get($layout.'_pagination_clickable', 'true'), 1 );
$sw_pag_dynamic     = fc_swiper_mod_yn( $params->get($layout.'_pagination_dynamic', 'false'), 0 );
$sw_pag_hideonclick = fc_swiper_mod_yn( $params->get($layout.'_pagination_hideonclick', 'false'), 0 );
$sw_pag_fraction    = $params->get($layout.'_pagination_fraction', '{current} / {total}' );
$sw_accent_color    = trim( $params->get($layout.'_accent_color', '#007aff' ) );
if ($sw_accent_color && !preg_match('/^#[0-9a-fA-F]{6}$/', $sw_accent_color))
{
	$sw_accent_color = '';
}
$sw_nav_color = trim( $params->get($layout.'_nav_color', '' ) );
if ($sw_nav_color && !preg_match('/^#[0-9a-fA-F]{6}$/', $sw_nav_color))
{
	$sw_nav_color = '';
}
$sw_show_nav      = fc_swiper_mod_yn( $params->get($layout.'_show_nav', 'true'), 1 );
$sw_show_thumbs   = fc_swiper_mod_yn( $params->get($layout.'_show_thumbs', 'true'), 1 );
$sw_thumb_size    = $params->get($layout.'_thumb_size', 'custom' );
if (!in_array($sw_thumb_size, array('s', 'm', 'l', 'custom'))) $sw_thumb_size = 'custom';
$sw_thumb_custom_w = (int) $params->get($layout.'_thumb_custom_w', 0 );
$sw_thumb_height  = (int) $params->get($layout.'_thumb_height', 80 );
$sw_thumb_position = $params->get($layout.'_thumb_position', 'below' );
if (!in_array($sw_thumb_position, array('above','left','right'))) $sw_thumb_position = 'below';
$sw_thumb_is_vertical = in_array($sw_thumb_position, array('left','right'));

// Slide sizing (uniform cell height)
$sw_slide_height   = trim( $params->get($layout.'_slide_height', '' ) );
$sw_slide_is_ratio = $sw_slide_height !== '' && strpos($sw_slide_height, '/') !== false;
$sw_slide_css_value = $sw_slide_is_ratio ? $sw_slide_height : ((int) $sw_slide_height ? (int) $sw_slide_height . 'px' : '');
$sw_auto_height    = fc_swiper_mod_yn( $params->get($layout.'_auto_height', 'false'), 0 );
$sw_image_fit      = $params->get($layout.'_image_fit', 'cover' );
if (!in_array($sw_image_fit, array('cover', 'contain'))) $sw_image_fit = 'cover';
$sw_carousel_padding = trim( $params->get($layout.'_carousel_padding', '' ) );
if ($sw_carousel_padding !== '' && !preg_match('/^(\d+(\.\d+)?[a-z%]*)(\s+(\d+(\.\d+)?[a-z%]*)){0,3}$/i', $sw_carousel_padding)) $sw_carousel_padding = '';

// Behavior parameters
$sw_direction    = $params->get($layout.'_direction', 'horizontal' );
$sw_effect       = $params->get($layout.'_effect', 'slide' );
$sw_cube_shadow          = fc_swiper_mod_yn( $params->get($layout.'_cube_shadow', 'true'), 1 );
$sw_flip_limit_rotation  = fc_swiper_mod_yn( $params->get($layout.'_flip_limit_rotation', 'true'), 1 );
$sw_cards_rotate         = fc_swiper_mod_yn( $params->get($layout.'_cards_rotate', 'true'), 1 );
$sw_cards_per_slide_offset = (float) $params->get($layout.'_cards_per_slide_offset', 8 );
$sw_cards_per_slide_rotate = (float) $params->get($layout.'_cards_per_slide_rotate', 2 );
$sw_slide_shadows        = fc_swiper_mod_yn( $params->get($layout.'_slide_shadows', 'true'), 1 );
$sw_coverflow_rotate  = (int) $params->get($layout.'_coverflow_rotate', 50 );
$sw_coverflow_stretch = (int) $params->get($layout.'_coverflow_stretch', 0 );
$sw_coverflow_depth   = (int) $params->get($layout.'_coverflow_depth', 100 );
$sw_coverflow_scale   = (float) $params->get($layout.'_coverflow_scale', 1 );
$sw_space_between  = (int) $params->get($layout.'_space_between', 10 );
$sw_centered       = fc_swiper_mod_yn( $params->get($layout.'_centered_slides', 'false'), 0 );
$sw_slides_per_view  = $params->get($layout.'_slides_per_view', '1' );
$sw_slides_per_group = $params->get($layout.'_slides_per_group', '1' );
$sw_grid_rows        = (int) $params->get($layout.'_grid_rows', 1 );
$sw_grid_fill        = $params->get($layout.'_grid_fill', 'row' );
$sw_responsive_spv   = fc_swiper_mod_yn( $params->get($layout.'_responsive_spv', 'false'), 0 );
$sw_spv_tablet       = $sw_responsive_spv ? (string) $params->get($layout.'_slides_per_view_tablet', '1') : '';
$sw_spv_phone        = $sw_responsive_spv ? (string) $params->get($layout.'_slides_per_view_phone', '1') : '';

// Swiper limitations (same as the Image field Swiper gallery):
// - Fade/Cube/Flip always display ONE slide at a time (slides per view forced to 1)
// - multi-row grid works with slide / fade / flip / coverflow / cards, cube renders broken cells
$sw_single_slide_effect = in_array($sw_effect, array('fade', 'cube', 'flip'));
$sw_grid_supported      = in_array($sw_effect, array('slide', 'fade', 'flip', 'coverflow', 'cards'));
if (!$sw_grid_supported)
{
	$sw_grid_rows = 1;
}
if ($sw_single_slide_effect)
{
	$sw_slides_per_view = '1';
	$sw_responsive_spv  = 0;
	$sw_spv_tablet      = '';
	$sw_spv_phone       = '';
}
$sw_grab_cursor    = fc_swiper_mod_yn( $params->get($layout.'_grab_cursor', 'false'), 0 );
$sw_keyboard       = fc_swiper_mod_yn( $params->get($layout.'_keyboard', 'false'), 0 );
$sw_mousewheel     = fc_swiper_mod_yn( $params->get($layout.'_mousewheel', 'false'), 0 );

// Scrollbar options
$sw_show_scrollbar      = fc_swiper_mod_yn( $params->get($layout.'_show_scrollbar', 'false'), 0 );
$sw_scrollbar_draggable = fc_swiper_mod_yn( $params->get($layout.'_scrollbar_draggable', 'true'), 1 );
$sw_scrollbar_hide      = fc_swiper_mod_yn( $params->get($layout.'_scrollbar_hide', 'false'), 0 );
$sw_scrollbar_drag_size = (int) $params->get($layout.'_scrollbar_drag_size', 0 );

// Multi-row grid REQUIRES a defined wrapper height (Grid sets each slide height as a
// percentage of .swiper-wrapper which is height:100%). autoHeight and loop are
// incompatible with grid rows.
if ($sw_grid_rows > 1)
{
	$sw_auto_height = 0;
	$sw_loop        = 0;
}

// Vertical mode REQUIRES a defined container height (fallback handled in CSS below).
// For vertical, a fixed height is used (a 16/9 ratio would scale the height with the
// module width and get very tall on wide modules). Grid keeps the 16/9 ratio fallback.

// Auto height + explicit pixel slide height → the fixed height is IGNORED by Swiper's
// autoHeight (wrapper sized to the active slide). Make it act as a CAP: never exceed it.
$sw_carousel_cap = ($sw_auto_height && $sw_slide_height !== '' && !$sw_slide_is_ratio) ? (int) $sw_slide_height : 0;

// Pre-compute cell/container sizing rules for the scoped CSS
// - horizontal, single row, explicit height → uniform slide (cell) cards
// - grid / vertical → the container needs a definite height, slides fill it (16/9 for grid, fixed px for vertical)
$sw_slide_h_css = '';
$sw_main_h_css  = '';
if (!$sw_auto_height)
{
	if ($sw_slide_height !== '')
	{
		$h_rule = $sw_slide_is_ratio ? 'aspect-ratio: ' . $sw_slide_height . ';' : 'height: ' . $sw_slide_css_value . ';';
		if ($sw_grid_rows > 1 || $sw_direction === 'vertical')
		{
			$sw_main_h_css = $h_rule;
		}
		else
		{
			$sw_slide_h_css = $h_rule;
		}
	}

	// Grid REQUIRES a definite height too, but its cell math (percentage of the wrapper)
	// needs any definite height — 16/9 ratio is the validated default for grids.
	if (!$sw_main_h_css && $sw_grid_rows > 1)
	{
		$sw_main_h_css = 'aspect-ratio: 16/9;';
	}
	// Vertical mode: fixed height fallback so it does not scale with module width
	// (a 16/9 ratio would be unusably tall on wide modules). min() caps it on short viewports.
	if (!$sw_main_h_css && $sw_direction === 'vertical')
	{
		$sw_main_h_css = 'height: min(70vh, 420px);';
	}
}


$readmore_align_feat = $params->get('readmore_align_feat', 'center');
$readmore_align_std = $params->get('readmore_align_std', 'center');
$readmore_class_feat = $params->get('readmore_class_feat', 'readon btn feat');
$readmore_class_std = $params->get('readmore_class_std', 'readon btn std');

// Respect the site template's button rules: the FlexiContent default classes (readon btn feat/std)
// are replaced by the template button style, keeping any user-configured custom class untouched.
if ($readmore_class_feat === 'readon btn feat') $readmore_class_feat = 'readon btn btn-primary';
if ($readmore_class_std === 'readon btn std')  $readmore_class_std  = 'readon btn btn-primary';

/**
 * Featured
 * item placement 0: cleared, 1: as masonry tiles, 2: tabs, 3: accordion (sliders)
 */
$item_placement_feat = (int) $params->get($layout.'_item_placement_feat', 1);
$item_columns_feat   = (int) $params->get('item_columns_feat', 3);
/** add column class or no class for grid mode */
if ($item_columns_feat  >= 1 && $item_column_mode_feat == 1 ){
	$cols_class_feat = '';
}else {
	$cols_class_feat  ='cols_' .$item_columns_feat;
}

/**
 * Standard
 * Note: standard items are placed inside the Swiper carousel (positioned by its JS),
 * not via CSS Grid/masonry/tabs/accordion, so item_placement_std is fixed.
 */
$item_placement_std = -1;
$item_columns_std   = 1;

$document = \Joomla\CMS\Factory::getDocument();
$jcookie  = \Joomla\CMS\Factory::getApplication()->input->cookie;

// Add Swiper JS + single per-page initialisation script (shared by all module instances / orderings)
global $fc_swiper_mod_js_added;
if (empty($fc_swiper_mod_js_added))
{
	$fc_swiper_mod_js_added = true;
	flexicontent_html::loadFramework('swiper');

	$js = "
(function() {
	if (window.fcSwiperModInit) return;
	window.fcSwiperModInit = true;

	function fcSwiperModInitAll() {
		if (typeof window.Swiper === 'undefined') {
			window.setTimeout(fcSwiperModInitAll, 100);
			return;
		}

		document.querySelectorAll('.fc_swiper_mod_container').forEach(function(container) {
			if (container.getAttribute('data-fc-swiper-init')) return;
			container.setAttribute('data-fc-swiper-init', '1');

			var mainEl  = container.querySelector('.fc_swiper_main');
			var thumbEl = container.querySelector('.fc_swiper_thumbs');
			if (!mainEl) return;

			var spaceValue = parseInt(container.getAttribute('data-space'), 10);
			var fx = container.getAttribute('data-effect') || 'slide';
			var mainParams = {
				spaceBetween: isNaN(spaceValue) ? 10 : spaceValue,
				speed: parseInt(container.getAttribute('data-speed') || '300', 10) || 300,
				observer: true,
				observeParents: false,
				watchOverflow: true,
				fitToWrapper: false,
				direction: container.getAttribute('data-direction') || 'horizontal',
				effect: fx
			};

			var spv = container.getAttribute('data-slides-per-view') || '1';
			var spvTablet = container.getAttribute('data-spv-tablet');
			var spvPhone = container.getAttribute('data-spv-phone');

			// Swiper limitation: fade/cube/flip only ever show ONE slide at a time
			if (fx === 'fade' || fx === 'cube' || fx === 'flip') {
				spv = '1';
				spvTablet = '';
				spvPhone = '';
			}

			// Effects with 3D slide shadows (generic option) + their specific options
			var slideShadows = container.getAttribute('data-slide-shadows') !== '0';

			if (fx === 'cube') {
				mainParams.cubeEffect = {
					slideShadows: slideShadows,
					shadow: container.getAttribute('data-cube-shadow') !== '0'
				};
			}

			if (fx === 'flip') {
				mainParams.flipEffect = {
					slideShadows: slideShadows,
					limitRotation: container.getAttribute('data-flip-limit-rotation') !== '0'
				};
			}

			if (fx === 'cards') {
				var caOffset = parseInt(container.getAttribute('data-cards-per-slide-offset'), 10);
				var caRotate = parseInt(container.getAttribute('data-cards-per-slide-rotate'), 10);
				mainParams.cardsEffect = {
					slideShadows: slideShadows,
					rotate: container.getAttribute('data-cards-rotate') !== '0',
					perSlideOffset: isNaN(caOffset) ? 8 : caOffset,
					perSlideRotate: isNaN(caRotate) ? 2 : caRotate
				};
			}

			if (fx === 'coverflow') {
				var cfRotate  = parseInt(container.getAttribute('data-coverflow-rotate'), 10);
				var cfStretch = parseInt(container.getAttribute('data-coverflow-stretch'), 10);
				var cfDepth   = parseInt(container.getAttribute('data-coverflow-depth'), 10);
				var cfScale   = parseFloat(container.getAttribute('data-coverflow-scale'));
				mainParams.coverflowEffect = {
					slideShadows: slideShadows,
					rotate: isNaN(cfRotate) ? 50 : cfRotate,
					stretch: isNaN(cfStretch) ? 0 : cfStretch,
					depth: isNaN(cfDepth) ? 100 : cfDepth,
					scale: isNaN(cfScale) ? 1 : cfScale
				};
			}

			if (spvPhone || spvTablet) {
				mainParams.slidesPerView = spvPhone === 'auto' ? 'auto' : parseInt(spvPhone, 10) || 1;
				mainParams.breakpoints = {};
				if (spvTablet) {
					mainParams.breakpoints[768] = { slidesPerView: spvTablet === 'auto' ? 'auto' : parseInt(spvTablet, 10) || 1 };
				}
				mainParams.breakpoints[1200] = { slidesPerView: spv === 'auto' ? 'auto' : parseInt(spv, 10) || 1 };
			} else {
				mainParams.slidesPerView = spv === 'auto' ? 'auto' : parseInt(spv, 10) || 1;
			}

			var spg = container.getAttribute('data-slides-per-group') || '1';
			mainParams.slidesPerGroup = parseInt(spg, 10) || 1;

			if (container.getAttribute('data-auto-height') === '1') {
				mainParams.autoHeight = true;
			}

			if (container.getAttribute('data-centered') === '1') {
				mainParams.centeredSlides = true;
			}

			if (container.getAttribute('data-grab-cursor') === '1') {
				mainParams.grabCursor = true;
			}

			if (container.getAttribute('data-keyboard') === '1') {
				mainParams.keyboard = { enabled: true };
			}

			if (container.getAttribute('data-mousewheel') === '1') {
				mainParams.mousewheel = { forceToAxis: true };
			}

			if (container.getAttribute('data-loop') === '1') {
				mainParams.loop = true;
			}

			if (container.getAttribute('data-rewind') === '1') {
				mainParams.rewind = true;
			}

			// Grid layout is supported by slide, fade, flip, coverflow and cards effects
			var gridRows = parseInt(container.getAttribute('data-grid-rows') || '1', 10) || 1;
			if (fx !== 'slide' && fx !== 'fade' && fx !== 'flip' && fx !== 'coverflow' && fx !== 'cards') {
				gridRows = 1;
			}
			if (gridRows > 1) {
				mainParams.grid = {
					rows: gridRows,
					fill: container.getAttribute('data-grid-fill') || 'row'
				};
				if (mainParams.slidesPerView === 'auto') {
					mainParams.slidesPerView = 1;
				}
			}

			if (container.getAttribute('data-autoplay') === '1') {
				mainParams.autoplay = {
					delay: parseInt(container.getAttribute('data-autoplay-delay') || '3000', 10) || 3000,
					disableOnInteraction: false,
					pauseOnMouseEnter: container.getAttribute('data-autoplay-hover') === '1'
				};
			}

			if (container.getAttribute('data-pagination') === '1' && mainEl.querySelector('.swiper-pagination')) {
				var pagParams = {
					el: mainEl.querySelector('.swiper-pagination'),
					clickable: container.getAttribute('data-pag-clickable') === '1'
				};

				var pagType = container.getAttribute('data-pag-type') || 'bullets';
				if (pagType !== 'bullets') {
					pagParams.type = pagType;
				}

				if (pagType === 'bullets' && container.getAttribute('data-pag-dynamic') === '1') {
					pagParams.dynamicBullets = true;
				}

				if (pagType === 'fraction') {
					var fractionFormat = container.getAttribute('data-pag-fraction') || '{current} / {total}';
					pagParams.renderFraction = function(currentClass, totalClass) {
						return fractionFormat
							.replace(/\{current\}/g, '<span class=\"' + currentClass + '\"></span>')
							.replace(/\{total\}/g, '<span class=\"' + totalClass + '\"></span>');
					};
				}

				if (container.getAttribute('data-pag-hideonclick') === '1') {
					pagParams.hideOnClick = true;
				}

				mainParams.pagination = pagParams;
			}

			if (container.getAttribute('data-nav') === '1') {
				mainParams.navigation = {
					nextEl: mainEl.querySelector('.swiper-button-next'),
					prevEl: mainEl.querySelector('.swiper-button-prev')
				};
			}

			if (container.getAttribute('data-scrollbar') === '1' && mainEl.querySelector('.swiper-scrollbar')) {
				var sbParams = {
					el: mainEl.querySelector('.swiper-scrollbar'),
					draggable: container.getAttribute('data-scrollbar-draggable') === '1',
					hide: container.getAttribute('data-scrollbar-hide') === '1'
				};
				var sbDragSize = parseInt(container.getAttribute('data-scrollbar-drag-size') || '0', 10) || 0;
				if (sbDragSize) {
					sbParams.dragSize = sbDragSize;
				}
				mainParams.scrollbar = sbParams;
			}

			// Thumbnail controller
			if (container.getAttribute('data-thumbs') === '1' && thumbEl) {
				var thumbParams = {
					spaceBetween: 8,
					slidesPerView: 'auto',
					freeMode: true,
					watchSlidesProgress: true
				};
				if (container.getAttribute('data-thumb-vertical') === '1') {
					thumbParams.direction = 'vertical';
					thumbParams.centeredSlides = true;
					thumbParams.centeredSlidesBounds = true;
				}
				var thumbSwiper = new Swiper(thumbEl, thumbParams);
				mainParams.thumbs = { swiper: thumbSwiper };
			}

			// Force all carousel images to load (Swiper uses translate3d, lazy images never trigger)
			container.querySelectorAll('.swiper-slide img[loading=\"lazy\"]').forEach(function(img) {
				img.removeAttribute('loading');
				if (!img.complete) { img.src = img.src; }
			});

			var swiper = new Swiper(mainEl, mainParams);
			container.querySelectorAll('.swiper-slide img').forEach(function(img) {
				if (!img.complete) {
					img.addEventListener('load', function() { swiper.update(); }, { once: true });
				}
			});
		});
	}

	if (document.readyState === 'complete' || document.readyState === 'interactive') fcSwiperModInitAll();
	else document.addEventListener('DOMContentLoaded', fcSwiperModInitAll);
})();
";
	$wa->addInlineScript($js);
}


/**
 * Add masonry JS (featured items)
 */
if ($item_placement_feat === 1 && $item_columns_feat > 1)
{
	flexicontent_html::loadFramework('masonry');
	flexicontent_html::loadFramework('imagesLoaded');
}


/**
 * Content display via Parameter-based Layout
 */
$feat_params_layout = (int) $params->get($layout.'_params_layout_feat', 1);
$std_params_layout  = (int) $params->get($layout.'_params_layout', 1);
$css_prefix = '#mod_flexicontent_' . $module->id;


/**
 * Content display via Builder-based Layouts
 */
$feat_builder_layout_num = (int) $params->get($layout.'_builder_layout_feat', 1);
$std_builder_layout_num  = (int) $params->get($layout.'_builder_layout', 1);

if ($feat_builder_layout_num)
{
	$builder_layout_name = 'builder_layout' . $feat_builder_layout_num;
	$feat_builder_layout = trim($params->get($builder_layout_name . '_html', ''));
	
	$sub_prefix = $feat_builder_layout_num !== $std_builder_layout_num
		? ' .mod_flexicontent_featured '
		: '';

	if ($feat_builder_layout)
	{
		modFlexicontentHelper::loadBuilderLayoutAssets(
			$module,
			$params,
			$builder_layout_name,
			$css_prefix . $sub_prefix
		);

		$matches = null;
		preg_match_all('/\sid="([a-zA-Z0-9_-]*)"/', $feat_builder_layout, $matches);

		foreach ($matches[0] as $i => $k)
		{
			$tagid = $matches[1][$i];
			$feat_builder_layout = str_replace('id="' . $tagid . '"', 'id="' . $tagid . '_{{fc-item-id}}"', $feat_builder_layout);
			$feat_builder_layout = str_replace('"#' . $tagid . '"', '"#' . $tagid . '_{{fc-item-id}}"', $feat_builder_layout);
			$feat_builder_layout = str_replace("'#" . $tagid . "'", "'#" . $tagid . "_{{fc-item-id}}'", $feat_builder_layout);
		}
	}
}


if ($std_builder_layout_num)
{
	$builder_layout_name = 'builder_layout' . $std_builder_layout_num;
	$std_builder_layout  = trim($params->get('builder_layout' . $std_builder_layout_num . '_html', ''));

	$sub_prefix = $feat_builder_layout_num !== $std_builder_layout_num
		? ' .mod_flexicontent_standard '
		: '';

	// Only if it has non-empty sub_prefix
	if ($std_builder_layout && $sub_prefix)
	{
		modFlexicontentHelper::loadBuilderLayoutAssets(
			$module,
			$params,
			$builder_layout_name,
			$css_prefix . $sub_prefix
		);

		$matches = null;
		preg_match_all('/\sid="([a-zA-Z0-9_-]*)"/', $std_builder_layout, $matches);

		foreach ($matches[0] as $i => $k)
		{
			$tagid = $matches[1][$i];
			$std_builder_layout = str_replace('id="' . $tagid . '"', 'id="' . $tagid . '_{{fc-item-id}}"', $std_builder_layout);
			$std_builder_layout = str_replace('"#' . $tagid . '"', '"#' . $tagid . '_{{fc-item-id}}"', $std_builder_layout);
			$std_builder_layout = str_replace("'#" . $tagid . "'", "'#" . $tagid . "_{{fc-item-id}}'", $std_builder_layout);
		}
	}
}


/**
 * Get active Tabs / Sliders (accordion) from cookie
 */
if ($item_placement_feat === 2 || $item_placement_std === 2 || $item_placement_feat === 3 || $item_placement_std === 3)
{
	$cookie_name = 'fc_modules_data';
	$fcMods_conf = $jcookie->get($cookie_name, '{}', 'string');

	try
	{
		$fcMods_conf = json_decode($fcMods_conf);
	}
	catch (Exception $e)
	{
		$jcookie->set($cookie_name, '{}', time()+60*60*24, \Joomla\CMS\Uri\Uri::base(true), '');
	}

	$fcMods_conf = is_object($fcMods_conf)
		? $fcMods_conf
		: (new stdClass);

	$fcMod_conf = isset($fcMods_conf->{$module->id})
		? $fcMods_conf->{$module->id}
		: (new stdClass);

	$active_tagids_feat = isset($fcMod_conf->active_tagids_feat) ? $fcMod_conf->active_tagids_feat : (new stdClass);
	$active_tagids_std  = isset($fcMod_conf->active_tagids_std) ? $fcMod_conf->active_tagids_std : (new stdClass);
}


$container_id = $module->id . (count($catdata_arr) > 1 && $catdata ? '_' . $catdata->id : '');
?>


<!-- BOF DIV mod_flexicontent_wrapper -->

<div class="swiper mod_flexicontent_wrapper mod_flexicontent_wrap" id="mod_flexicontent_swiper<?php echo $container_id; ?>">


	<?php
	// Display FavList Information (if enabled)
	include(JPATH_SITE.'/modules/mod_flexicontent/tmpl_common/favlist.php');

	// Display Category Information (if enabled)
	include(JPATH_SITE.'/modules/mod_flexicontent/tmpl_common/category.php');

	$ord_titles = array(
		'popular'=>\Joomla\CMS\Language\Text::_( 'FLEXI_UMOD_MOST_POPULAR'),  // popular == hits
		'rhits'=>\Joomla\CMS\Language\Text::_( 'FLEXI_UMOD_LESS_VIEWED'),

		'author'=>\Joomla\CMS\Language\Text::_( 'FLEXI_UMOD_AUTHOR_ALPHABETICAL'),
		'rauthor'=>\Joomla\CMS\Language\Text::_( 'FLEXI_UMOD_AUTHOR_ALPHABETICAL_REVERSE'),

		'published'=>\Joomla\CMS\Language\Text::_( 'FLEXI_UMOD_RECENTLY_PUBLISHED_SCHEDULED'),
		'published_oldest'=>\Joomla\CMS\Language\Text::_( 'FLEXI_UMOD_OLDEST_PUBLISHED_SCHEDULED'),
		'expired'=>\Joomla\CMS\Language\Text::_( 'FLEXI_UMOD_FLEXI_RECENTLY_EXPIRING_EXPIRED'),
		'expired_oldest'=>\Joomla\CMS\Language\Text::_( 'FLEXI_UMOD_OLDEST_EXPIRING_EXPIRED_FIRST'),

		'commented'=>\Joomla\CMS\Language\Text::_( 'FLEXI_UMOD_MOST_COMMENTED'),
		'rated'=>\Joomla\CMS\Language\Text::_( 'FLEXI_UMOD_BEST_RATED' ),

		'added'=>	\Joomla\CMS\Language\Text::_( 'FLEXI_UMOD_RECENTLY_ADDED'),  // added == rdate
		'addedrev'=>\Joomla\CMS\Language\Text::_( 'FLEXI_UMOD_RECENTLY_ADDED_REVERSE' ),  // addedrev == date
		'updated'=>\Joomla\CMS\Language\Text::_( 'FLEXI_UMOD_RECENTLY_UPDATED'),  // updated == modified

		'alpha'=>	\Joomla\CMS\Language\Text::_( 'FLEXI_UMOD_ALPHABETICAL'),
		'alpharev'=>\Joomla\CMS\Language\Text::_( 'FLEXI_UMOD_ALPHABETICAL_REVERSE'),   // alpharev == ralpha

		'id'=>\Joomla\CMS\Language\Text::_( 'FLEXI_UMOD_HIGHEST_ITEM_ID'),
		'rid'=>\Joomla\CMS\Language\Text::_( 'FLEXI_UMOD_LOWEST_ITEM_ID'),

		'catorder'=>\Joomla\CMS\Language\Text::_( 'FLEXI_UMOD_CAT_ORDER'),  // catorder == order
		'jorder'=>\Joomla\CMS\Language\Text::_( 'FLEXI_UMOD_CAT_ORDER_JOOMLA'),
		'random'=>\Joomla\CMS\Language\Text::_( 'FLEXI_UMOD_RANDOM_ITEMS' ),
		'field'=>\Joomla\CMS\Language\Text::sprintf( 'FLEXI_UMOD_CUSTOM_FIELD', $orderby_custom_field->label)
	);

	$separator  = '';
	$rowtoggler = 0;

	foreach ($ordering as $ord) :
  	echo $separator;

	  if (!isset($list[$ord]['featured']) && !isset($list[$ord]['standard']))
		{
  	  $separator = '';
  	  continue;
  	}

 	  $separator = '<div class="ordering_separator"></div>';

  	// PREPEND ORDER if using more than 1 orderings ...
  	$order_name = $ord ?: 'default';
		$uniq_ord_id = (count($list) > 1 ? $order_name : '') . $container_id;
	?>


	<!-- BOF DIV mod_flexicontent -->

	<div id="<?php echo 'order_' . $order_name . $container_id; ?>" class="mod_flexicontent">


		<?php	if ($ordering_addtitle && $ord) : ?>
		<div class='order_group_title'><?php echo isset($ord_titles[$ord]) ? $ord_titles[$ord] : $ord; ?></div>
		<?php endif; ?>

	<?php if (isset($list[$ord]['featured'])) : ?>

		<?php
		$rowcount = 0;
		$item_ids_index = array();

		foreach ($list[$ord]['featured'] as $item)
		{
			$item_ids_index[$item->id] = 1;
		}
		?>

		<!-- BOF DIV mod_flexicontent_featured (featured items) -->

		<div class="mod_flexicontent_featured mod_fcitems_box_featured_<?php echo $uniq_ord_id; ?> <?php echo ($cols_class_feat ? ' '.$cols_class_feat : ''); ?>" id="mod_fcitems_box_featured_<?php echo $uniq_ord_id; ?>">

			<?php
			$oe_class = $rowtoggler ? 'odd' : 'even';

			if ($item_placement_feat === 2 || $item_placement_feat === 3)
			{
				$first_item        = reset($list[$ord]['featured']);
				$itemset_tagid     = 'fc_umod_itemset_feat_' . $uniq_ord_id;

				$last_active_tagid = isset($active_tagids_feat->$itemset_tagid)
					? $active_tagids_feat->$itemset_tagid
					: $itemset_tagid . '_' . $first_item->id;

				$tagid_parts = explode('_', $last_active_tagid);
				$activated_itemid = end($tagid_parts);

				$last_active_tagid = isset($item_ids_index[$activated_itemid])
					? $last_active_tagid
					: $itemset_tagid . '_' . $first_item->id;

				echo $item_placement_feat === 2
					? \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.startTabSet', $itemset_tagid, array('active' => $last_active_tagid))
					: \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.startAccordion', $itemset_tagid, array('active' => $last_active_tagid));
			}

			foreach ($list[$ord]['featured'] as $item) :

				if ($item_placement_feat === 2 || $item_placement_feat === 3)
				{
					echo $item_placement_feat === 2
						? \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.addTab', $itemset_tagid, $itemset_tagid . '_' . $item->id, $item->title)
						: \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.addSlide', $itemset_tagid, $item->title, $itemset_tagid . '_' . $item->id);
				}

				$img_force_dims_css_feat = $img_auto_dims_css_feat;

				if ($item_img_fit_feat == 0)
				{
					$img_force_dims_css_feat .= ($item->image_w ? ' max-width:'. $item->image_w.'px; ' : '') . ($item->image_h ? ' max-height:'. $item->image_h.'px; ' : '');
				}
				
				$img_size_feat = 
					($item->image_w ? ' width="' . $item->image_w . '"' : '') .
					($item->image_h ? ' height="' . $item->image_h . '"' : '')
					;

				if ($rowcount % $item_columns_feat === 0)
				{
					$oe_class = $oe_class === 'odd' ? 'even' : 'odd';
					$rowtoggler = !$rowtoggler;
				}

				$rowcount++;
			?>

			<!-- BOF item -->	
			<div class="mod_flexicontent_featured_wrapper<?php echo $mod_do_hlight_feat; ?><?php echo ' '.$oe_class .($item->is_active_item ? ' fcitem_active' : ''); ?> <?php echo ($item_placement_feat == 1) ? 'masonry' : '';?>">
			<div class="mod_flexicontent_featured_wrapper_innerbox">


			<?php if ($feat_params_layout) : /* BOF: Content display via Parameter-based Layout */ ?>


				<!-- BOF item title -->
				<?php ob_start(); ?>

					<?php if ($display_title_feat) : ?>
						<div class="fcitem_title_box">
							<span class="fcitem_title">
							<?php if ($link_title_feat) : ?>
								<a href="<?php echo $item->link; ?>"><h3><?php echo $item->title; ?></h3></a>
							<?php else : ?>
								<h3><?php echo $item->title; ?></h3>
							<?php endif; ?>
							</span>
						</div>
					<?php endif; ?>

				<?php $captured_title = ob_get_clean(); $hasTitle = (boolean) trim($captured_title); ?>
				<!-- EOF item title -->


				<!-- BOF item's image -->	
				<?php ob_start(); ?>

					<?php if ($mod_use_image_feat && $item->image_rendered) : ?>

						<div class="image_featured <?php echo $img_container_class_feat;?>">
							<?php if ($mod_link_image_feat) : ?>
								<a href="<?php echo $item->link; ?>"><?php echo $item->image_rendered; ?></a>
							<?php else : ?>
								<?php echo $item->image_rendered; ?>
							<?php endif; ?>
						</div>

					<?php elseif ($mod_use_image_feat && $item->image) : ?>

						<div class="image_featured <?php echo $img_container_class_feat;?>">
							<?php
							$_alt_f = flexicontent_html::striptagsandcut($item->fulltitle, 60);
							$_pic_f = modFlexicontentHelper::makeWebpPicture($item->image, '', '', '', $_alt_f, $mod_width_feat, $mod_height_feat);
							?>
							<?php if ($mod_link_image_feat) : ?>
								<a href="<?php echo $item->link; ?>"><?php echo $_pic_f; ?></a>
							<?php else : ?>
								<?php echo $_pic_f; ?>
							<?php endif; ?>
						</div>

					<?php endif; ?>

				<?php $captured_image = ob_get_clean(); $hasImage = (boolean) trim($captured_image); ?>
				<!-- EOF item's image -->

				<?php echo $content_layout_feat!=2 ? $captured_image : '';?>


				<!-- BOF item's content -->
				<?php if ($hasTitle || $display_date_feat || $display_text_feat || $display_hits_feat || $display_voting_feat || $display_comments_feat || $mod_readmore_feat || ($use_fields_feat && @$item->fields && $fields_feat)) : ?>
				<div class="content_featured <?php echo $content_container_class_feat;?>">

					<?php echo $captured_title; ?>


					<?php if ($display_date_feat && $item->date_created) : ?>
					<div class="fc_block">
						<div class="fc_inline fcitem_date created">
							<?php echo $item->date_created; ?>
						</div>
					</div>
					<?php endif; ?>

					<?php if ($display_date_feat && $item->date_modified) : ?>
					<div class="fc_block">
						<div class="fc_inline fcitem_date modified">
							<?php echo $item->date_modified; ?>
						</div>
					</div>
					<?php endif; ?>

					<?php if ($display_hits_feat && @ $item->hits_rendered) : ?>
					<div class="fc_block">
						<div class="fc_inline fcitem_hits">
							<?php echo $item->hits_rendered; ?>
						</div>
					</div>
					<?php endif; ?>

					<?php if ($display_voting_feat && @ $item->voting) : ?>
					<div class="fc_block">
						<div class="fc_inline fcitem_voting">
							<?php echo $item->voting;?>
						</div>
					</div>
					<?php endif; ?>

					<?php if ($display_comments_feat) : ?>
					<div class="fc_block">
						<div class="fc_inline fcitem_comments">
							<?php echo $item->comments_rendered; ?>
						</div>
					</div>
					<?php endif; ?>

					<?php if ($display_text_feat && $item->text) : ?>
					<div class="fc_block fcitem_text">
						<?php echo $item->text; ?>
					</div>
					<?php endif; ?>

					<?php if ($use_fields_feat && @$item->fields && $fields_feat) : ?>
					<div class="fc_block fcitem_fields">

					<?php foreach ($item->fields as $k => $field) : ?>
						<?php if ( $hide_label_onempty_feat && !strlen($field->display) ) continue; ?>
						<div class="field_block field_<?php echo $k; ?>">
							<?php if ($display_label_feat) : ?>
							<div class="field_label"><?php echo $field->label . $text_after_label_feat; ?></div>
							<?php endif; ?>
							<div class="field_value"><?php echo $field->display; ?></div>
						</div>
					<?php endforeach; ?>

					</div>
					<?php endif; ?>


				</div> <!-- EOF item's content -->
				<?php endif; ?>

				<?php if ($mod_readmore_feat) : ?>
					<div class="fc_block readmore <?php echo ($item_height_feat == 1) ? "force-height" : ""; ;?>">
						<div class="fcitem_readon <?php echo $readmore_align_feat;?>">
							<a href="<?php echo $item->link; ?>" class="<?php echo $readmore_class_feat; ?>"><span><?php echo \Joomla\CMS\Language\Text::_('FLEXI_MOD_READ_MORE'); ?></span></a>
						</div>
					</div>
					<?php endif; ?>

				<?php echo $content_layout_feat==2 ? $captured_image : '';?>


			<?php endif; /* EOF: Content display via Parameter-based Layout */ ?>


			<?php if($feat_builder_layout_num > 0){
				// Content display via Builder-based Layouts
				echo $feat_builder_layout
					? str_replace('{{fc-item-id}}', $item->id, $feat_builder_layout)
					: '';
				}
			?>


			</div>  <!-- EOF wrapper_innerbox -->
			</div>  <!-- EOF wrapper -->
			<!-- EOF item -->

			<?php
				if ($item_placement_feat === 2 || $item_placement_feat === 3)
				{
					echo $item_placement_feat === 2
						? \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endTab')
						: \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endSlide');
				}

			endforeach;

			if ($item_placement_feat === 2 || $item_placement_feat === 3)
			{
				echo $item_placement_feat === 2
					? \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endTabSet')
					: \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endAccordion');

				\Joomla\CMS\Factory::getDocument()->addScriptDeclaration("
				(function($) {
					$(document).ready(function ()
					{
						$('#" . $itemset_tagid . ($item_placement_feat === 2 ? 'Tabs' : '') . "').on('shown', function ()
						{
							var fcMods_conf = fclib_getCookie('" . $cookie_name ."');
							try { fcMods_conf = JSON.parse(fcMods_conf); } catch(e) { fcMods_conf = {}; }

							fcMods_conf['" . $module->id ."'] = fcMods_conf['" . $module->id ."'] || {};
							fcMods_conf['" . $module->id ."']['active_tagids_feat'] = fcMods_conf['" . $module->id ."']['active_tagids_feat'] || {};
							" . ($item_placement_feat === 2
								? "fcMods_conf['" . $module->id ."']['active_tagids_feat']['" . $itemset_tagid . "'] = $('#" . $itemset_tagid . "Tabs').next().find('.active').attr('id');"
								: "fcMods_conf['" . $module->id ."']['active_tagids_feat']['" . $itemset_tagid . "'] = $('#" . $itemset_tagid . " .in').attr('id');") . "
							fclib_setCookie('" . $cookie_name ."', JSON.stringify(fcMods_conf), 7);
							window.console.log(JSON.stringify(fcMods_conf));
						});

						$('#" . $itemset_tagid . ($item_placement_feat === 2 ? 'Tabs' : '') . "').on('hidden', function ()
						{
							var fcMods_conf = fclib_getCookie('" . $cookie_name ."');
							try { fcMods_conf = JSON.parse(fcMods_conf); } catch(e) { fcMods_conf = {}; }

							fcMods_conf['" . $module->id ."'] = fcMods_conf['" . $module->id ."'] || {};
							fcMods_conf['" . $module->id ."']['active_tagids_feat'] = fcMods_conf['" . $module->id ."']['active_tagids_feat'] || {};
							fcMods_conf['" . $module->id ."']['active_tagids_feat']['" . $itemset_tagid . "'] = null;
							fclib_setCookie('" . $cookie_name ."', JSON.stringify(fcMods_conf), 7);
							window.console.log(JSON.stringify(fcMods_conf));
						});

						var fcMods_conf = fclib_getCookie('" . $cookie_name ."');
						try { fcMods_conf = JSON.parse(fcMods_conf); } catch(e) { fcMods_conf = {}; }

						fcMods_conf['" . $module->id ."'] = fcMods_conf['" . $module->id ."'] || {};
						fcMods_conf['" . $module->id ."']['active_tagids_feat'] = fcMods_conf['" . $module->id ."']['active_tagids_feat'] || {};

						if (!!fcMods_conf['" . $module->id ."']['active_tagids_feat']['" . $itemset_tagid . "'])
						{
							// Hide default active slide
							$('#" . $itemset_tagid ." .collapse').removeClass('in');

							// Show the last active slide
							$('#' + fcMods_conf['" . $module->id ."']['active_tagids_feat']['" . $itemset_tagid . "']).addClass('in');
						}
					});
				})(jQuery);
				");
			}
			?>

		</div>

		<!-- EOF DIV mod_flexicontent_featured (featured items) -->


	<?php endif; ?>

	<div class="modclear"></div>


	<?php if (isset($list[$ord]['standard'])) : ?>

		<?php
		// Unique id of this Swiper carousel instance
		$uid = 'fc_swiper_mod_' . $uniq_ord_id;

		$rowcount = 0;
		$sw_slides = array();
		$sw_thumbs = array();
		$oe_class = $rowtoggler ? 'odd' : 'even';
		$img_path = \Joomla\CMS\Uri\Uri::base(true) . '/';

		foreach ($list[$ord]['standard'] as $item) :

			$img_force_dims_css_std = $img_auto_dims_css_std;

			if ($item_img_fit_std == 0)
			{
				$img_force_dims_css_std .= ($item->image_w ? ' max-width:'. $item->image_w.'px; ' : '') . ($item->image_h ? ' max-height:'. $item->image_h.'px; ' : '');
			}

			$img_size = 
				($item->image_w ? ' width="' . $item->image_w . '"' : '') .
				($item->image_h ? ' height="' . $item->image_h . '"' : '')
				;

			if ($rowcount % $item_columns_std == 0)
			{
				$oe_class = $oe_class === 'odd' ? 'even' : 'odd';
				$rowtoggler = !$rowtoggler;
			}

			$rowcount++;

			// BOF item title
			ob_start();

				if ($display_title) : ?>
					<div class="fcitem_title_box">
						<span class="fcitem_title">
						<?php if ($link_title) : ?>
							<a href="<?php echo $item->link; ?>"><h3><?php echo $item->title; ?></h3></a>
						<?php else : ?>
							<h3><?php echo $item->title; ?></h3>
						<?php endif; ?>
						</span>
					</div>
				<?php endif;

			$captured_title = ob_get_clean(); $hasTitle = (boolean) trim($captured_title);
			// EOF item title


			// BOF item's image
			ob_start();

				if ($mod_use_image && $item->image_rendered) : ?>

					<div class="image_standard <?php echo $img_container_class_std;?>">
						<?php if ($mod_link_image) : ?>
							<a href="<?php echo $item->link; ?>"><?php echo $item->image_rendered; ?></a>
						<?php else : ?>
							<?php echo $item->image_rendered; ?>
						<?php endif; ?>
					</div>

				<?php elseif ($mod_use_image && $item->image) : ?>

					<div class="image_standard <?php echo $img_container_class_std;?>">
						<?php
						$_alt_s = flexicontent_html::striptagsandcut($item->fulltitle, 60);
						$_pic_s = modFlexicontentHelper::makeWebpPicture($item->image, '', '', '', $_alt_s, $mod_width, $mod_height);
						?>
						<?php if ($mod_link_image) : ?>
							<a href="<?php echo $item->link; ?>"><?php echo $_pic_s; ?></a>
						<?php else : ?>
							<?php echo $_pic_s; ?>
						<?php endif; ?>
					</div>

				<?php endif;

			$captured_image = ob_get_clean(); $hasImage = (boolean) trim($captured_image);
			// EOF item's image


			$slide_html = '<div class="swiper-slide">'
				. '<div class="mod_flexicontent_standard_wrapper' . $mod_do_hlight . ' ' . $oe_class . ($item->is_active_item ? ' fcitem_active' : '') . '">'
				. '<div class="mod_flexicontent_standard_wrapper_innerbox ' . $img_container_class_std . '">';

			if ($std_params_layout) :  // Content display via Parameter-based Layout

				$slide_html .= ($content_layout_std != 2 ? $captured_image : '');

				if ($hasTitle || $display_date || $display_text || $display_hits || $display_voting || $display_comments || $mod_readmore || ($use_fields && @$item->fields && $fields))
				{
					$slide_html .= '<div class="content_standard ' . $content_container_class_std . '">';
					$slide_html .= $captured_title;

					if ($display_date && $item->date_created)
					{
						$slide_html .= '<div class="fc_block"><div class="fc_inline fcitem_date created">' . $item->date_created . '</div></div>';
					}

					if ($display_date && $item->date_modified)
					{
						$slide_html .= '<div class="fc_block"><div class="fc_inline fcitem_date modified">' . $item->date_modified . '</div></div>';
					}

					if ($display_hits && @ $item->hits_rendered)
					{
						$slide_html .= '<div class="fc_block"><div class="fc_inline fcitem_hits">' . $item->hits_rendered . '</div></div>';
					}

					if ($display_voting && @ $item->voting)
					{
						$slide_html .= '<div class="fc_block"><div class="fc_inline fcitem_voting">' . $item->voting . '</div></div>';
					}

					if ($display_comments)
					{
						$slide_html .= '<div class="fc_block"><div class="fc_inline fcitem_comments">' . $item->comments_rendered . '</div></div>';
					}

					if ($display_text && $item->text)
					{
						$slide_html .= '<div class="fc_block fcitem_text">' . $item->text . '</div>';
					}

					if ($use_fields && @$item->fields && $fields)
					{
						$slide_html .= '<div class="fc_block fcitem_fields">';

						foreach ($item->fields as $k => $field)
						{
							if ($hide_label_onempty && !strlen($field->display)) continue;
							$slide_html .= '<div class="field_block field_' . $k . '">';
							if ($display_label) $slide_html .= '<div class="field_label">' . $field->label . $text_after_label . '</div>';
							$slide_html .= '<div class="field_value">' . $field->display . '</div></div>';
						}

						$slide_html .= '</div>';
					}

					if ($mod_readmore)
					{
						$slide_html .= '<div class="fc_block"><div class="fcitem_readon ' . $readmore_align_std . '">'
							. '<a href="' . $item->link . '" class="' . $readmore_class_std . '"><span>' . \Joomla\CMS\Language\Text::_('FLEXI_MOD_READ_MORE') . '</span></a></div></div>';
					}

					$slide_html .= '<div class="clearfix"></div></div>';
				}

				$slide_html .= ($content_layout_std == 2 ? $captured_image : '');

			endif;  // EOF: Content display via Parameter-based Layout

			// Content display via Builder-based Layouts
			if ($std_builder_layout > 0)
			{
				$slide_html .= $std_builder_layout ? str_replace('{{fc-item-id}}', $item->id, $std_builder_layout) : '';
			}

			$slide_html .= '</div></div></div>';  // EOF innerbox + wrapper + slide

			$sw_slides[] = $slide_html;


			// BOF thumbnail (one per item): extract the src of image_rendered (<picture>) or fallback to $item->image
			$_sw_thumb_raw = '';
			if (@ $item->image_rendered && preg_match('/src=["\'](.*?)["\' ]/i', $item->image_rendered, $_sw_m))
			{
				$_sw_raw = html_entity_decode($_sw_m[1]);
				if (strpos($_sw_raw, 'phpThumb.php') !== false && preg_match('/[?&]src=([^&]+)/', $_sw_raw, $_sw_sm))
				{
					$_sw_thumb_raw = urldecode($_sw_sm[1]);
				}
				else
				{
					$_sw_thumb_raw = $_sw_raw;
				}
			}
			elseif (@ $item->image)
			{
				$_sw_thumb_raw = $item->image;
			}
			if (!$_sw_thumb_raw) $_sw_thumb_raw = $img_path . $mod_default_img_path;

			// Pre-generated image sizes (s/m/l) from the FLEXIcontent image field, when available.
			// The module keeps the first image value of the field ('[0]').
			$_sw_fc_size = array('s' => 'small', 'm' => 'medium', 'l' => 'large');
			$_sw_use_pregen = '';
			if ($sw_thumb_size !== 'custom' && isset($item->_row->fields) && is_array($item->_row->fields))
			{
				foreach ($item->_row->fields as $_sw_field)
				{
					if (!isset($_sw_field->thumbs_src[$_sw_fc_size[$sw_thumb_size]][0])) continue;
					$_sw_pregen_url = $_sw_field->thumbs_src[$_sw_fc_size[$sw_thumb_size]][0];
					if ($_sw_pregen_url)
					{
						$_sw_use_pregen = $_sw_pregen_url;
						break;
					}
				}
			}

			$_sw_h = (int) $sw_thumb_height;
			$_sw_style = $sw_thumb_is_vertical
				? 'width:'.$_sw_h.'px; height:auto; max-width:none; object-fit:cover; cursor:pointer;'
				: 'height:'.$_sw_h.'px; width:auto; max-width:none; object-fit:cover; cursor:pointer;';

			if ($_sw_use_pregen)
			{
				// Use the pre-generated FC thumbnail directly (faster, no on-the-fly resize)
				$sw_thumbs[] = '<div class="swiper-slide"><img src="' . htmlspecialchars($_sw_use_pregen) . '" alt="" style="' . $_sw_style . '" loading="lazy" decoding="async" /></div>';
			}
			else
			{
				$_sw_hb = (!preg_match('#^http|^https|^ftp|^/#i', $_sw_thumb_raw)) ? \Joomla\CMS\Uri\Uri::base(true).'/' : '';
				$_sw_thumb_raw_enc = str_replace(' ', '%20', $_sw_thumb_raw);
				if ($sw_thumb_size === 'custom' && $sw_thumb_custom_w > 0)
				{
					// phpThumb: custom width, height automatic (aspect ratio preserved, no crop)
					$_sw_conf = '&w='.$sw_thumb_custom_w.'&h=0&aoe=1&q=85&zc=0';
				}
				else
				{
					// phpThumb: fixed height (aspect ratio preserved, no crop)
					$_sw_conf = '&h='.$_sw_h.'&w=0&aoe=1&q=85&zc=0';
				}
				$_sw_base = \Joomla\CMS\Uri\Uri::root(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$_sw_hb.$_sw_thumb_raw_enc;
				$sw_thumbs[] = '<div class="swiper-slide"><picture>'
					. '<source type="image/webp" srcset="'.$_sw_base.$_sw_conf.'&f=webp">'
					. '<img src="'.$_sw_base.$_sw_conf.'&amp;f=jpg" alt="" style="'.$_sw_style.'" loading="lazy" decoding="async" />'
					. '</picture></div>';
			}
			// EOF thumbnail

		endforeach;
		?>

		<!-- BOF DIV mod_flexicontent_standard (standard items) -->

		<div class="mod_flexicontent_standard mod_fcitems_box_standard_<?php echo $uniq_ord_id; ?>" id="mod_fcitems_box_standard_<?php echo $uniq_ord_id; ?>">

			<?php
			// Thumbnail strip HTML
			$sw_thumbs_html = '';
			if ($sw_show_thumbs && count($sw_thumbs))
			{
				$sw_thumb_cls    = 'swiper fc_swiper_thumbs' . ($sw_thumb_is_vertical ? ' fc_swiper_thumbs_vertical' : '');
				$sw_thumb_margin = $sw_thumb_position === 'above' ? 'margin-bottom:10px;' : 'margin-top:10px;';
				$sw_thumbs_html = '
				<div class="' . $sw_thumb_cls . '" style="' . $sw_thumb_margin . ' padding-bottom: 2px;">
					<div class="swiper-wrapper">
						' . implode('', $sw_thumbs) . '
					</div>
				</div>';
			}
			$sw_thumbs_above = $sw_thumb_position === 'above' ? $sw_thumbs_html : '';
			$sw_thumbs_below = $sw_thumb_position !== 'above' ? $sw_thumbs_html : '';
			?>

			<div id="<?php echo $uid; ?>" class="fc_swiper_mod_container fc_swiper_thumbs_<?php echo $sw_thumb_position; ?>"
				data-speed="<?php echo $sw_speed; ?>"
				data-loop="<?php echo ($sw_loop ? 1 : 0); ?>"
				data-rewind="<?php echo ($sw_rewind ? 1 : 0); ?>"
				data-grid-rows="<?php echo $sw_grid_rows; ?>"
				data-grid-fill="<?php echo $sw_grid_fill; ?>"
				data-autoplay="<?php echo ($sw_autoplay ? 1 : 0); ?>"
				data-autoplay-delay="<?php echo $sw_autoplay_delay; ?>"
				data-autoplay-hover="<?php echo ($sw_autoplay_hover ? 1 : 0); ?>"
				data-pagination="<?php echo ($sw_show_pagination ? 1 : 0); ?>"
				data-pag-type="<?php echo $sw_pag_type; ?>"
				data-pag-clickable="<?php echo ($sw_pag_clickable ? 1 : 0); ?>"
				data-pag-dynamic="<?php echo ($sw_pag_dynamic ? 1 : 0); ?>"
				data-pag-hideonclick="<?php echo ($sw_pag_hideonclick ? 1 : 0); ?>"
				data-pag-fraction="<?php echo htmlspecialchars($sw_pag_fraction, ENT_QUOTES); ?>"
				data-nav="<?php echo ($sw_show_nav ? 1 : 0); ?>"
				data-thumbs="<?php echo ($sw_show_thumbs && count($sw_thumbs) ? 1 : 0); ?>"
				data-thumb-vertical="<?php echo ($sw_thumb_is_vertical ? 1 : 0); ?>"
				data-direction="<?php echo $sw_direction; ?>"
				data-auto-height="<?php echo ($sw_auto_height ? 1 : 0); ?>"
				data-effect="<?php echo $sw_effect; ?>"
				data-slide-shadows="<?php echo $sw_slide_shadows; ?>"
				data-cube-shadow="<?php echo $sw_cube_shadow; ?>"
				data-flip-limit-rotation="<?php echo $sw_flip_limit_rotation; ?>"
				data-cards-rotate="<?php echo $sw_cards_rotate; ?>"
				data-cards-per-slide-offset="<?php echo $sw_cards_per_slide_offset; ?>"
				data-cards-per-slide-rotate="<?php echo $sw_cards_per_slide_rotate; ?>"
				data-coverflow-rotate="<?php echo $sw_coverflow_rotate; ?>"
				data-coverflow-stretch="<?php echo $sw_coverflow_stretch; ?>"
				data-coverflow-depth="<?php echo $sw_coverflow_depth; ?>"
				data-coverflow-scale="<?php echo $sw_coverflow_scale; ?>"
				data-space="<?php echo $sw_space_between; ?>"
				data-slides-per-view="<?php echo $sw_slides_per_view; ?>"
				data-slides-per-group="<?php echo $sw_slides_per_group; ?>"
				data-spv-tablet="<?php echo $sw_spv_tablet; ?>"
				data-spv-phone="<?php echo $sw_spv_phone; ?>"
				data-centered="<?php echo ($sw_centered ? 1 : 0); ?>"
				data-grab-cursor="<?php echo ($sw_grab_cursor ? 1 : 0); ?>"
				data-keyboard="<?php echo ($sw_keyboard ? 1 : 0); ?>"
				data-mousewheel="<?php echo ($sw_mousewheel ? 1 : 0); ?>"
				data-scrollbar="<?php echo ($sw_show_scrollbar ? 1 : 0); ?>"
				data-scrollbar-draggable="<?php echo ($sw_scrollbar_draggable ? 1 : 0); ?>"
				data-scrollbar-hide="<?php echo ($sw_scrollbar_hide ? 1 : 0); ?>"
				data-scrollbar-drag-size="<?php echo $sw_scrollbar_drag_size; ?>"
				style="width:100%; max-width:100%; overflow:hidden;">

				<?php echo $sw_thumbs_above; ?>

				<div class="swiper fc_swiper_main">
					<div class="swiper-wrapper">
						<?php echo implode('', $sw_slides); ?>
					</div>
					<?php if ($sw_show_pagination) : ?><div class="swiper-pagination"></div><?php endif; ?>
					<?php if ($sw_show_nav) : ?><div class="swiper-button-prev"></div><div class="swiper-button-next"></div><?php endif; ?>
					<?php if ($sw_show_scrollbar) : ?><div class="swiper-scrollbar"></div><?php endif; ?>
				</div>

				<?php echo $sw_thumbs_below; ?>

			</div>
			<div class="fcclear"></div>

		</div>

		<!-- EOF DIV mod_flexicontent_standard (standard items) -->

	<?php endif; ?>

	</div>

	<!-- EOF DIV mod_flexicontent -->


	<div class="modclear"></div>


	<?php
	// ***********************************************************
	// Module specific styling (we use names containing module ID)
	// ***********************************************************

	if ($item_column_mode_feat == 0 ){
		switch ($item_columns_feat) {
			case 1:
				$item_columns_feat = '100%';
				break;
			case 2:
				$item_columns_feat = '50%';
				break;
			case 3:
				$item_columns_feat = '33%';
				break;
			case 4:
				$item_columns_feat = '25%';
				break;
			case 5:
				$item_columns_feat = '20%';
				break;
			case 6:
				$item_columns_feat = '16%';
				break;
			case 7:
				$item_columns_feat = '14%';
				break;
			case 8:
				$item_columns_feat = '12%';
				break;
		}
	}else{
		$item_columns_feat = $item_width_feat;
	}

	$item_columns_feat_int = (int) $item_columns_feat;

	$css = '';

	$css .= '/* CONTAINER of featured items */
#mod_fcitems_box_featured_'.$uniq_ord_id.' {
}
/* CONTAINER of each featured item */
#mod_fcitems_box_featured_'.$uniq_ord_id.' div.mod_flexicontent_standard_wrapper {
}';
	$css .= '/* inner CONTAINER (grid parent) of feat items: gap & column sizing apply here */
#mod_fcitems_box_featured_'.$uniq_ord_id.'.mod_flexicontent_featured {
	'.($inner_inline_css_feat ? '
	row-gap: '.$margin_top_bottom_feat.' !important ;
	gap:'.$margin_left_right_feat.' !important;
	' : '').'
	grid-template-columns: repeat('.$item_fit_feat.', minmax('.$item_columns_feat.', 1fr));
}';

	$css .= '/* contour (padding/border) of EACH feat item, not the grid container */
#mod_fcitems_box_featured_'.$uniq_ord_id.' .mod_flexicontent_featured_wrapper_innerbox {
	'.($inner_inline_css_feat ? '
	padding: '.$padding_top_bottom_feat.' '.$padding_left_right_feat.' !important;
	border-width: '.$border_width_feat.' !important;
	border-style: '.$border_style_feat.' !important;
	border-color: '.$border_color_feat.' !important;
	border-radius: '.$border_radius_feat.' !important;
	' : '').'
	'.($inner_inline_css_feat && trim($border_radius_feat) !== '' && trim($border_radius_feat) !== '0' ? '
	overflow: hidden !important;
	' : '').'
}';

	$css .= '/* CONTAINER of standard items */
#mod_fcitems_box_standard_'.$uniq_ord_id.' {
}
/* inner box: border, padding, radius (margin/spacing handled by Swiper\'s spaceBetween) */
#mod_fcitems_box_standard_'.$uniq_ord_id.' div.mod_flexicontent_standard_wrapper_innerbox {
	'.($inner_inline_css_std ? '
	padding: '.$padding_top_bottom_std.' '.$padding_left_right_std.' !important;
	border-width: '.$border_width_std.' !important;
	border-style: '.$border_style_std.' !important;
	border-color: '.$border_color_std.' !important;
	border-radius: '.$border_radius_std.' !important;
	' : '').'
	'.($inner_inline_css_std && trim($border_radius_std) !== '' && trim($border_radius_std) !== '0' ? '
	overflow: hidden !important;
	' : '').'
}';

	$css .= '/* Swiper carousel container */
#'.$uid.'.fc_swiper_mod_container {
	'.($sw_accent_color ? '
	--swiper-theme-color: '.$sw_accent_color.';' : '').'
}';

	$css .= '#'.$uid.' .fc_swiper_main .swiper-slide img,
#'.$uid.' .fc_swiper_main .swiper-slide picture img {
	max-width: 100%; height: auto; display: block;
}';

	$css .= '/* Content placement: anchors */
#'.$uid.' .fc_swiper_main .mod_flexicontent_standard_wrapper,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .mod_flexicontent_featured_wrapper { position: relative; }
#'.$uid.' .fc_swiper_main .image_standard,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .image_featured { line-height: 0; }
#'.$uid.' .fc_swiper_main .fc_overlayed,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .fc_overlayed {
	background-color: rgba(0,0,0,0.4);
	width: 100%;
	padding: 2%;
	margin: 0%;
	position: absolute;
	left: 0px;
	z-index: 2;
	color: #fff;
}
#'.$uid.' .fc_swiper_main .fc_overlayed .fcitem_title a,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .fc_overlayed .fcitem_title a { color: #fff; }
#'.$uid.' .fc_swiper_main .fc_overlayed .fc_block,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .fc_overlayed .fc_block { line-height: 160%; }
#'.$uid.' .fc_swiper_main .fc_bottom,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .fc_bottom { bottom: 0px; }
#'.$uid.' .fc_swiper_main .fc_top,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .fc_top { top: 0px; }
#'.$uid.' .fc_swiper_main .fc_full,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .fc_full { top: 0px; height: 96%; }
#'.$uid.' .fc_swiper_main .fc_stretch,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .fc_stretch {
	width: 100% !important;
	border: 0 !important;
	margin: 0 !important;
	padding: 0 !important;
	float: none !important;
	display: block;
}
#'.$uid.' .fc_swiper_main .fc_float_left,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .fc_float_left {
	float: left !important;
	margin: 0px 12px 8px 0;
	display: block;
}
#'.$uid.' .fc_swiper_main .fc_float_right,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .fc_float_right {
	float: right !important;
	margin: 0px 0 8px 12px;
	display: block;
}
#'.$uid.' .fc_swiper_main .fc_floated,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .fc_floated {}
#'.$uid.' .fc_swiper_main .fc_clear,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .fc_clear { clear: both !important; }
'.($content_layout_std == 3 ? '
/* Cleared content below image (standard): space between the image and the text */
#'.$uid.' .fc_swiper_main .content_standard { padding-top: 12px; }' : '').'
'.($content_layout_std == 2 ? '
/* Cleared content above image (standard): space between the text and the image */
#'.$uid.' .fc_swiper_main .content_standard { padding-bottom: 12px; }' : '').'
'.($content_layout_feat == 3 ? '
/* Cleared content below image (featured): space between the image and the text */
#mod_fcitems_box_featured_'.$uniq_ord_id.' .content_featured { padding-top: 12px; }' : '').'
'.($content_layout_feat == 2 ? '
/* Cleared content above image (featured): space between the text and the image */
#mod_fcitems_box_featured_'.$uniq_ord_id.' .content_featured { padding-bottom: 12px; }' : '').'

/* Readmore button: fit to its text and honour left/center/right alignment */
#'.$uid.' .content_standard .fc_block:has(.fcitem_readon),
#'.$uid.' .content_featured .fc_block:has(.fcitem_readon),
#mod_fcitems_box_featured_'.$uniq_ord_id.' .content_featured .fc_block:has(.fcitem_readon) {
	margin-top: auto; /* push readmore to the bottom of the item */
}
#'.$uid.' .fcitem_readon,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .fcitem_readon { display: flex; }
#'.$uid.' .fcitem_readon.left,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .fcitem_readon.left { justify-content: flex-start; }
#'.$uid.' .fcitem_readon.center,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .fcitem_readon.center { justify-content: center; }
#'.$uid.' .fcitem_readon.right,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .fcitem_readon.right { justify-content: flex-end; }
#'.$uid.' .fcitem_readon a.readon,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .fcitem_readon a.readon {
	display: inline-block;
	width: auto !important;
	max-width: 100%;
}

/* Item titles: remove the link underline */
#'.$uid.' .fcitem_title a,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .fcitem_title a,
#'.$uid.' .fcitem_title a:hover,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .fcitem_title a:hover { text-decoration: none; }

/* AUTO-SHOW contents (overlay on hover / active) */
#'.$uid.' .mod_flexicontent_standard_wrapper .fc_auto_show,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .mod_flexicontent_featured_wrapper .fc_auto_show {
	-webkit-transition: max-height 0.3s linear, opacity 0.3s linear, padding 0.3s linear, background-color 0.3s linear;
	transition: max-height 0.3s linear, opacity 0.3s linear, padding 0.3s linear, background-color 0.3s linear;
	opacity: 0.0;
	filter: alpha(opacity=0);
	max-height: 0;
	padding-top: 0px;
	padding-bottom: 0px;
	overflow-y: hidden;
	width: 100%;
}
#'.$uid.' .mod_flexicontent_standard_wrapper.mod_fc_activeitem .fc_auto_show.fc_show_active,
#'.$uid.' .mod_flexicontent_standard_wrapper:hover .fc_auto_show,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .mod_flexicontent_featured_wrapper.mod_fc_activeitem .fc_auto_show.fc_show_active,
#mod_fcitems_box_featured_'.$uniq_ord_id.' .mod_flexicontent_featured_wrapper:hover .fc_auto_show {
	opacity: 1;
	filter: alpha(opacity=100);
	background-color: rgba(0,0,0,0.75);
	max-height: 100%;
}';

	if ($sw_image_fit == 'contain')
	{
		$css .= '#'.$uid.' .fc_swiper_main .image_standard {
	display: flex; align-items: center; justify-content: center;
}
#'.$uid.' .fc_swiper_main .image_standard img,
#'.$uid.' .fc_swiper_main .image_standard picture img {
	width: auto !important; height: auto !important; max-width: 100% !important; margin-left: auto; margin-right: auto;
}';
	}

	if ($sw_carousel_cap)
	{
		$css .= '#'.$uid.' .fc_swiper_main { max-height: '.$sw_carousel_cap.'px; }
#'.$uid.' .mod_flexicontent_standard_wrapper_innerbox { max-height: '.$sw_carousel_cap.'px; overflow: hidden; }
#'.$uid.' .fc_swiper_main .swiper-slide img,
#'.$uid.' .fc_swiper_main .swiper-slide picture img { max-height: '.$sw_carousel_cap.'px; }';
	}

	if ($sw_main_h_css)
	{
		$css .= '#'.$uid.' .fc_swiper_main { '.$sw_main_h_css.' }
#'.$uid.' .fc_swiper_main .swiper-wrapper { height: 100%; }
'.($sw_grid_rows <= 1 ? '
#'.$uid.' .fc_swiper_main .swiper-slide { height: 100%; }' : '').'
#'.$uid.' .fc_swiper_main .mod_flexicontent_standard_wrapper_innerbox { height: 100%; overflow: hidden; }';
	}

	if ($sw_slide_h_css)
	{
		$css .= '#'.$uid.' .swiper-slide { '.$sw_slide_h_css.' }
#'.$uid.' .swiper-slide .mod_flexicontent_standard_wrapper_innerbox { height: 100%; overflow: hidden; }';
	}

	if ($sw_carousel_padding)
	{
		$css .= '#'.$uid.' .fc_swiper_main { padding: '.$sw_carousel_padding.'; box-sizing: border-box; }';
	}

	$css .= '/* Thumbnail strip */
#'.$uid.' .fc_swiper_thumbs .swiper-slide { width: auto !important; opacity: 0.45; transition: opacity 0.25s ease; }
#'.$uid.' .fc_swiper_thumbs .swiper-slide-thumb-active { opacity: 1; }
#'.$uid.' .fc_swiper_thumbs .swiper-slide img { display: block; border-radius: 4px; border: 2px solid transparent; }
#'.$uid.' .fc_swiper_thumbs .swiper-slide-thumb-active img { border-color: var(--swiper-theme-color, #007aff); }';

	if ($sw_thumb_is_vertical)
	{
		$css .= '#'.$uid.' { display: flex; align-items: stretch; gap: 10px; }
#'.$uid.'.fc_swiper_thumbs_left { flex-direction: row-reverse; }
#'.$uid.' .fc_swiper_main { flex: 1; min-width: 0; }
#'.$uid.' .fc_swiper_thumbs_vertical { flex: 0 0 auto; width: '.$sw_thumb_height.'px; padding: 0; margin: 0 !important; }
#'.$uid.' .fc_swiper_thumbs_vertical .swiper-wrapper { height: 100%; }
#'.$uid.' .fc_swiper_thumbs_vertical .swiper-slide { width: 100% !important; height: auto !important; opacity: 0.45; transition: opacity 0.25s ease; }
#'.$uid.' .fc_swiper_thumbs_vertical .swiper-slide-thumb-active { opacity: 1; }
#'.$uid.' .fc_swiper_thumbs_vertical .swiper-slide img { width: 100% !important; height: auto !important; max-width: none !important; }
#'.$uid.' .fc_swiper_thumbs_vertical .swiper-slide-thumb-active img { border-color: var(--swiper-theme-color, #007aff); }';
	}

	$css .= '/* Pagination */
#'.$uid.' .swiper-pagination-bullet { width: 10px; height: 10px; }
#'.$uid.' .swiper-pagination-fraction { font-size: 14px; color: #fff; background: rgba(0,0,0,.45); padding: 4px 12px; border-radius: 20px; }
#'.$uid.' .swiper-pagination-progressbar { background: rgba(0,0,0,.3); border-radius: 20px; overflow: hidden; }
#'.$uid.' .swiper-pagination-progressbar .swiper-pagination-progressbar-fill { background: var(--swiper-theme-color, #007aff); }
#'.$uid.' .swiper-scrollbar { --swiper-scrollbar-size: 6px; --swiper-scrollbar-bg-color: rgba(0,0,0,.3); --swiper-scrollbar-drag-bg-color: var(--swiper-theme-color, #007aff); }
#'.$uid.' .swiper-scrollbar-drag { border-radius: 6px; }';

	if ($sw_nav_color)
	{
		$css .= '#'.$uid.' .swiper-button-prev,
#'.$uid.' .swiper-button-next { color: '.$sw_nav_color.'; text-shadow: 0 1px 3px rgba(0,0,0,.5); }';
	}

	if ($sw_direction === 'vertical')
	{
		$css .= '#'.$uid.' .fc_swiper_main.swiper-vertical .swiper-button-prev,
#'.$uid.' .fc_swiper_main.swiper-vertical .swiper-button-next { left: 50%; right: auto; margin-top: 0; }
#'.$uid.' .fc_swiper_main.swiper-vertical .swiper-button-prev { top: 10px; transform: translateX(-50%) rotate(90deg); }
#'.$uid.' .fc_swiper_main.swiper-vertical .swiper-button-next { top: auto; bottom: 10px; transform: translateX(-50%) rotate(90deg); }';
	}

	$css .= '/* column size for masonry (featured only - standard items are positioned by Swiper) */
#mod_fcitems_box_featured_'.$uniq_ord_id.' .mod_flexicontent_featured_wrapper.masonry {
	'.($inner_inline_css_feat ? '
	width: calc('.$item_columns_feat.' - '.$margin_left_right_feat.') !important;
	margin-right:'.$margin_left_right_feat.' !important;
	margin-bottom:'.$margin_top_bottom_feat.' !important ;
	' : '
	width: calc('.$item_columns_feat.' - 20px) !important;
	margin-right:20px !important;
	margin-bottom:20px !important ;
	').'
}';

	$css .= '/* responsive for masonry */
@media only screen and (min-device-width : 320px) and (max-device-width : 480px) {
#mod_fcitems_box_featured_'.$uniq_ord_id.' .mod_flexicontent_featured_wrapper.masonry{
	width: 100% !important;
}
}';

	if ($css) $wa->addInlineStyle($css);


	if ($item_placement_feat == 1 && $item_columns_feat_int > 1)
	{
		$js = "
		jQuery(document).ready(function(){
			var container_feat = document.querySelector('div#mod_fcitems_box_featured_".$uniq_ord_id."');
			var msnry;
			// initialize Masonry after all images have loaded
			if (container_feat) {
				imagesLoaded( container_feat, function() {
					msnry = new Masonry( container_feat, {
					columnWidth: '#mod_fcitems_box_featured_$uniq_ord_id .mod_flexicontent_featured_wrapper.masonry',
					horizontalOrder: true,
					percentPosition: true
					});
				});
			}
		});
		";
		if ($js) $wa->addInlineScript($js);
	}
	?>


	<?php endforeach; ?>

	<?php
	// Display readon of module
	include(JPATH_SITE.'/modules/mod_flexicontent/tmpl_common/readon.php');
	?>

</div>

<!-- EOF DIV mod_flexicontent_wrapper -->