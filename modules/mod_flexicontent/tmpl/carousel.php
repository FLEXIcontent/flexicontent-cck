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
$padding_top_bottom_feat = (int)$params->get($layout.'_padding_top_bottom_feat', 8);
$padding_left_right_feat = (int)$params->get($layout.'_padding_left_right_feat', 12);
$margin_top_bottom_feat = (int)$params->get($layout.'_margin_left_right_feat', 4);
$margin_left_right_feat = (int)$params->get($layout.'_margin_left_right_feat', 4);
$border_width_feat = (int)$params->get($layout.'_border_width_feat', 1);


// Item Dimensions standard
$inner_inline_css = (int)$params->get($layout.'_inner_inline_css', 0);
$padding_top_bottom = (int)$params->get($layout.'_padding_top_bottom', 8);
$padding_left_right = (int)$params->get($layout.'_padding_left_right', 12);
$margin_top_bottom = (int)$params->get($layout.'_margin_left_right', 4);
$margin_left_right = (int)$params->get($layout.'_margin_left_right', 4);
$border_width = (int)$params->get($layout.'_border_width', 1);


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
$content_display = $params->get($layout.'_content_display', 0);  // 0: always visible, 1: On mouse over / item active, 2: On mouse over
$content_layout = $params->get($layout.'_content_layout', 3);  // 0/1: floated (right/left), 2/3: cleared (above/below), 4/5/6: overlayed (top/bottom/full)
$item_img_fit = $params->get($layout.'_img_fit', 1);   // 0: Auto-fit, 1: Auto-fit and stretch to larger

switch ($content_layout) {
	case 0: case 1:
		$img_container_class = ($content_layout==0 ? 'fc_float_left' : 'fc_float_right');
		$content_container_class = 'fc_floated';
		break;
	case 2: case 3:
		$img_container_class = 'fc_stretch fc_clear';
		$content_container_class = '';
		break;
	case 4: case 5: case 6:
		$img_container_class = 'fc_stretch';
		$content_container_class = 'fc_overlayed '
			.($content_layout==4 ? 'fc_top' : '')
			.($content_layout==5 ? 'fc_bottom' : '')
			.($content_layout==6 ? 'fc_full' : '')
			;
		if ($content_display >= 1) $content_container_class .= ' fc_auto_show';
		if ($content_display == 1) $content_container_class .= ' fc_show_active';
		break;
	default: $img_container_class = '';  break;
}



// ***
// *** Default image and image fitting
// ***
$mod_default_img_path = $params->get('mod_default_img_path', 'components/com_flexicontent/assets/images/image.png');
$img_path = JUri::base(true) .'/'; 

// image of FEATURED items, auto-fit and (optionally) limit to image max-dimensions to avoid stretching
$img_auto_dims_css_feat=" width: 100%; height: auto; display: block !important; border: 0 !important;";

// image of STANDARD items, auto-fit and (optionally) limit to image max-dimensions to avoid stretching
$img_auto_dims_css=" width: 100%; height: auto; display: block !important; border: 0 !important;";


/**
 * Parameters for fcxSlider JS configuration
 */

// Carousel direction and Common Dimensions
$mode = $params->get('carousel_mode', 'horizontal');

// Fixed size / Responsive
$responsive     = (int) $params->get('carousel_responsive', 1);
$item_size_px   = (int) $params->get('carousel_item_size_px', 240);
$items_per_page = (int) $params->get('carousel_items_per_page', 2);

// Edge behaviour, touch/mouse drag support
$edgewrap   = (int) $params->get('carousel_edgewrap', 1);
$touch_walk = (int) $params->get('carousel_touch_walk', 1);
$mouse_walk = (int) $params->get('carousel_mouse_walk', 0);

// Autoplay, autoplay interval, autoplay method
$autoplay = (int) $params->get('carousel_autoplay', 1);
$interval = (int) $params->get('carousel_interval', 5000);
$method   = $params->get('carousel_method', 'page');  // page, item

// Page Buttons (= carousel page handles)
$show_page_handles  = (int) $params->get('carousel_show_page_handles', 1);
$page_handle_event  = $params->get('carousel_page_handle_event', 'click');

// Item Buttons (= carousel item handles)
$show_item_handles    = (int)$params->get('carousel_show_handles', 1);
$item_handles_dir     = 'horizontal'; //$params->get('carousel_handles_dir', 'horizontal');  // horizontal, vertical
$item_handle_duration = (int) $params->get('carousel_handle_duration', 400);
$item_handle_width    = (int) $params->get('carousel_handle_width', 64);
$item_handle_height   = (int) $params->get('carousel_handle_height', 64);
$item_handle_event    = $params->get('carousel_handle_event', 'mouseover');
$item_handle_title    = (int) $params->get('carousel_handle_title', 0);
$item_handle_text     = (int) $params->get('carousel_handle_text', 0);

// Miscellaneous Optionally displayed
$show_controls   = (int) $params->get('carousel_show_controls', 1);
// Detached controls
$dcontrols_labels = (int) $params->get('carousel_dcontrols_labels', 1);
$dcontrols_auto   = (int) $params->get('carousel_dcontrols_auto', 1);
$dcontrols_pages  = (int) $params->get('carousel_dcontrols_pages', 1);
$dcontrols_items  = (int) $params->get('carousel_dcontrols_items', 1);
$dcontrols_icon   = (int) $params->get('carousel_dcontrols_icon', 0);
// Intergrated controls
$icontrols_method  = $params->get('carousel_icontrols_method', 'page');
$_icontrols_method = $icontrols_method === 'page' ? '_page' : '';

// Transition:  method and duration
$transition  = $params->get('carousel_transition', 'scroll');
$duration    = (int) $params->get('carousel_duration', 800);

// Transition easing:  method and in-out slowness
$easing       = $params->get('carousel_easing', 'quart');
$easing_inout = $params->get('carousel_easing_inout', 'easeOut');
// Moving duration for already visible items
$transition_visible_duration = (int) $params->get('carousel_transition_visible_duration', 150);

// ... calculate name of easing function
$easing_name = $easing === 'linear' || $easing === 'swing'
	? $easing
	: $easing_inout . ucfirst($easing);

// ... decide if showing handle onHover item info
$show_curritem_info = $item_handle_title === 2 || $item_handle_text === 2;

// Carousel specially created parameter values
$_fcx_edgeWrap     = $edgewrap ? "true" : "false";
$_fcx_touch_walk   = $touch_walk ? "true" : "false";
$_fcx_mouse_walk   = $mouse_walk ? "true" : "false";
$_fcx_autoPlay     = $autoplay ? "true" : "false";

$_fcx_fxOptions    = 'duration:' . $duration . ', easing: "' . $easing_name . '"';

if ($transition=='blind')
	$_fcx_fxOptions .= ', direction: "'.$params->get('carousel_jqe_direction_blind', 'up').'"';
if ($transition=='bounce' || $transition=='clip')
	$_fcx_fxOptions .= ', direction: "'.$params->get('carousel_jqe_direction', 'horizontal').'"';
if ($transition=='drop' || $transition=='slide' || $transition=='shake')
	$_fcx_fxOptions .= ', direction: "'.$params->get('carousel_jqe_direction_arrow', 'up').'"';

if ($transition=='bounce' || $transition=='pulsate' || $transition=='shake')
	$_fcx_fxOptions .= ', times: "'.(int)$params->get('carousel_jqe_times', 5).'"';
if ($transition=='bounce' || $transition=='shake')
	$_fcx_fxOptions .= ', distance: "'.(int)$params->get('carousel_jqe_distance', 20).'"';  // not setting distance for -$transition=='slide'- will use the width/height of the element as slide distance

if ($transition=='explode')
	$_fcx_fxOptions .= ', pieces: "'.(int)$params->get('carousel_jqe_pieces', 4).'"';

if ($transition=='fold')
	$_fcx_fxOptions .= ', size: "'.(int)$params->get('carousel_jqe_size_folded', 15).'"';

$_fcx_fxOptions    = '{ '.$_fcx_fxOptions.' }';

$_fcx_responsive   = $responsive;  // 0: px, 1: percentage
$_fcx_item_size    = $item_size_px;  // item width (horizontal) OR height (vertical) in case of fixed item size
$_fcx_items_per_page = $items_per_page;  // ZERO for horizontal, this value will be overwritten by auto-calulation, after page load ends

if ($interval < $duration)
{
	echo '<div class="alert">autoplay interval must not be smaller than the EFFECT (scroll/fade/etc) duration (even if autoplay is disabled), please correct in module configuration</div>';
}


/**
 * Featured
 * item placement 0: cleared, 1: as masonry tiles, 2: tabs, 3: accordion (sliders)
 */
$item_placement_feat = (int) $params->get($layout.'_item_placement_feat', 0);
$item_columns_feat   = (int) $params->get('item_columns_feat', 3);
$cols_class_feat     = $item_columns_feat <= 1 ? '' : 'cols_' . $item_columns_feat;

/**
 * Standard
 * Note: these are ignored / unsed since we items are place inside the carousel
 * item placement 0: cleared, 1: as masonry tiles, 2: tabs, 3: accordion (sliders)
 */
$item_placement_std = -1;
$item_columns_std   = 1;
$cols_class_std     = $item_columns_std  <= 1 ? '' : 'cols_' . $item_columns_std;

$document = JFactory::getDocument();
$jcookie  = JFactory::getApplication()->input->cookie;

// Add Carousel JS
flexicontent_html::loadFramework('fcxSlide');
flexicontent_html::loadFramework('mCSB');
flexicontent_html::loadFramework('imagesLoaded');
flexicontent_html::loadFramework('flexi_tmpl_common');


/**
 * Add masonry JS
 */
if (($item_placement_feat === 1 && $item_columns_feat > 1) || ($item_placement_std === 1 && $item_columns_std > 1))
{
	flexicontent_html::loadFramework('masonry');
	flexicontent_html::loadFramework('imagesLoaded');
}


/**
 * Add extra transitions that based on jQuery-UI
 */
if ($transition)
{
	$file_path = JPath::clean(JPATH_SITE.'/components/com_flexicontent/librairies/jquery/js/jquery-ui/jquery.ui.effect-'.$transition.'.min.js');

	if (file_exists($file_path))
	{
		$document->addScript(JUri::root(true).'/components/com_flexicontent/librairies/jquery/js/jquery-ui/jquery.ui.effect.min.js');
		$document->addScript(JUri::root(true).'/components/com_flexicontent/librairies/jquery/js/jquery-ui/jquery.ui.effect-'.$transition.'.min.js');
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
		$jcookie->set($cookie_name, '{}', time()+60*60*24, JUri::base(true), '');
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

<div class="carousel mod_flexicontent_wrapper mod_flexicontent_wrap<?php echo $moduleclass_sfx; ?>" id="mod_flexicontent_carousel<?php echo $container_id; ?>">


	<?php
	// Display FavList Information (if enabled)
	include(JPATH_SITE.'/modules/mod_flexicontent/tmpl_common/favlist.php');

	// Display Category Information (if enabled)
	include(JPATH_SITE.'/modules/mod_flexicontent/tmpl_common/category.php');

	$ord_titles = array(
		'popular'=>JText::_( 'FLEXI_UMOD_MOST_POPULAR'),  // popular == hits
		'rhits'=>JText::_( 'FLEXI_UMOD_LESS_VIEWED'),

		'author'=>JText::_( 'FLEXI_UMOD_AUTHOR_ALPHABETICAL'),
		'rauthor'=>JText::_( 'FLEXI_UMOD_AUTHOR_ALPHABETICAL_REVERSE'),

		'published'=>JText::_( 'FLEXI_UMOD_RECENTLY_PUBLISHED_SCHEDULED'),
		'published_oldest'=>JText::_( 'FLEXI_UMOD_OLDEST_PUBLISHED_SCHEDULED'),
		'expired'=>JText::_( 'FLEXI_UMOD_FLEXI_RECENTLY_EXPIRING_EXPIRED'),
		'expired_oldest'=>JText::_( 'FLEXI_UMOD_OLDEST_EXPIRING_EXPIRED_FIRST'),

		'commented'=>JText::_( 'FLEXI_UMOD_MOST_COMMENTED'),
		'rated'=>JText::_( 'FLEXI_UMOD_BEST_RATED' ),

		'added'=>	JText::_( 'FLEXI_UMOD_RECENTLY_ADDED'),  // added == rdate
		'addedrev'=>JText::_( 'FLEXI_UMOD_RECENTLY_ADDED_REVERSE' ),  // addedrev == date
		'updated'=>JText::_( 'FLEXI_UMOD_RECENTLY_UPDATED'),  // updated == modified

		'alpha'=>	JText::_( 'FLEXI_UMOD_ALPHABETICAL'),
		'alpharev'=>JText::_( 'FLEXI_UMOD_ALPHABETICAL_REVERSE'),   // alpharev == ralpha

		'id'=>JText::_( 'FLEXI_UMOD_HIGHEST_ITEM_ID'),
		'rid'=>JText::_( 'FLEXI_UMOD_LOWEST_ITEM_ID'),

		'catorder'=>JText::_( 'FLEXI_UMOD_CAT_ORDER'),  // catorder == order
		'jorder'=>JText::_( 'FLEXI_UMOD_CAT_ORDER_JOOMLA'),
		'random'=>JText::_( 'FLEXI_UMOD_RANDOM_ITEMS' ),
		'field'=>JText::sprintf( 'FLEXI_UMOD_CUSTOM_FIELD', $orderby_custom_field->label)
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

		<div class="mod_flexicontent_featured mod_fcitems_box_featured_<?php echo $uniq_ord_id; ?>" id="mod_fcitems_box_featured_<?php echo $uniq_ord_id; ?>">

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
					? JHtml::_('bootstrap.startTabSet', $itemset_tagid, array('active' => $last_active_tagid))
					: JHtml::_('bootstrap.startAccordion', $itemset_tagid, array('active' => $last_active_tagid));
			}

			foreach ($list[$ord]['featured'] as $item) :

				if ($item_placement_feat === 2 || $item_placement_feat === 3)
				{
					echo $item_placement_feat === 2
						? JHtml::_('bootstrap.addTab', $itemset_tagid, $itemset_tagid . '_' . $item->id, $item->title)
						: JHtml::_('bootstrap.addSlide', $itemset_tagid, $item->title, $itemset_tagid . '_' . $item->id);
				}

				$img_force_dims_css_feat = $img_auto_dims_css_feat;

				if ($item_img_fit_feat == 0 /* || $content_layout_feat <= 3*/)
				{
					$img_force_dims_css_feat .= ($item->image_w ? ' max-width:'. $item->image_w.'px; ' : '') . ($item->image_h ? ' max-height:'. $item->image_h.'px; ' : '');
				}

				if ($rowcount % $item_columns_feat === 0)
				{
					$oe_class = $oe_class === 'odd' ? 'even' : 'odd';
					$rowtoggler = !$rowtoggler;
				}

				$rowcount++;
			?>

			<!-- BOF item -->	
			<div class="mod_flexicontent_featured_wrapper<?php echo $mod_do_hlight_feat; ?><?php echo ' '.$oe_class .($item->is_active_item ? ' fcitem_active' : '') .($cols_class_feat ? ' '.$cols_class_feat : ''); ?>">
			<div class="mod_flexicontent_featured_wrapper_innerbox">

				<!-- BOF item title -->
				<?php ob_start(); ?>

					<?php if ($display_title_feat) : ?>
						<div class="fcitem_title_box">
							<span class="fcitem_title">
							<?php if ($link_title_feat) : ?>
								<a href="<?php echo $item->link; ?>"><?php echo $item->title; ?></a>
							<?php else : ?>
								<?php echo $item->title; ?>
							<?php endif; ?>
							</span>
						</div>
					<?php endif; ?>

				<?php $captured_title = ob_get_clean(); $hasTitle = (boolean) trim($captured_title); ?>
				<!-- EOF item title -->


				<!-- BOF item's image -->	
				<?php ob_start(); ?>

					<?php if ($mod_use_image_feat && $item->image_rendered) : ?>

						<div class="image_featured" <?php echo $img_container_class_feat;?>">
							<?php if ($mod_link_image_feat) : ?>
								<a href="<?php echo $item->link; ?>"><?php echo $item->image_rendered; ?></a>
							<?php else : ?>
								<?php echo $item->image_rendered; ?>
							<?php endif; ?>
						</div>

					<?php elseif ($mod_use_image_feat && $item->image) : ?>

						<div class="image_featured <?php echo $img_container_class_feat;?>">
							<?php if ($mod_link_image_feat) : ?>
								<a href="<?php echo $item->link; ?>">
									<img style="<?php echo $img_force_dims_css_feat; ?>" src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" />
								</a>
							<?php else : ?>
								<img style="<?php echo $img_force_dims_css_feat; ?>" src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" />
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

					<?php if ($mod_readmore_feat) : ?>
					<div class="fc_block">
						<div class="fcitem_readon">
							<a href="<?php echo $item->link; ?>" class="readon"><span><?php echo JText::_('FLEXI_MOD_READ_MORE'); ?></span></a>
						</div>
					</div>
					<?php endif; ?>

					<div class="clearfix"></div> 

				</div> <!-- EOF item's content -->
				<?php endif; ?>

				<?php echo $content_layout_feat==2 ? $captured_image : '';?>

			</div>  <!-- EOF wrapper_innerbox -->
			</div>  <!-- EOF wrapper -->
			<!-- EOF item -->

			<?php
				if ($item_placement_feat === 2 || $item_placement_feat === 3)
				{
					echo $item_placement_feat === 2
						? JHtml::_('bootstrap.endTab')
						: JHtml::_('bootstrap.endSlide');
				}
				elseif ($item_placement_feat === 0)  // 0: clear, 1: as masonry tiles
				{
					//echo !($rowcount%$item_columns_feat) ? '<div class="modclear"></div>' : '';
				}

			endforeach;

			if ($item_placement_feat === 2 || $item_placement_feat === 3)
			{
				echo $item_placement_feat === 2
					? JHtml::_('bootstrap.endTabSet')
					: JHtml::_('bootstrap.endAccordion');

				JFactory::getDocument()->addScriptDeclaration("
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

		<?php	$rowcount = 0; ?>

		<div id="mod_fc_carousel_mask_<?php echo $uniq_ord_id; ?>_loading" class="mod_fc_carousel_mask_loading">
			... <?php echo  JText::_('FLEXI_MOD_CAROUSEL_LOADING_IMAGES'); ?> <img alt="" src="<?php echo JUri::root(true); ?>/components/com_flexicontent/assets/images/ajax-loader.gif"/>
		</div>


<div class="mod_fc_carousel" id="mod_fc_carousel_container_<?php echo $uniq_ord_id; ?>" >

	<?php if ($show_controls==1) : ?>
	<span id="previous<?php echo $_icontrols_method; ?>_fcmod_<?php echo $uniq_ord_id; ?>"  class="mod_fc_nav fc_previous fc_<?php echo $mode; ?>" ></span> 
	<?php endif; ?>


	<div id="mod_fc_carousel_mask_<?php echo $uniq_ord_id; ?>" class="mod_fc_carousel_mask <?php echo $show_controls==1 ? 'fc_has_nav fc_'.$mode : ''; ?>">


		<!-- BOF DIV mod_flexicontent_standard (standard items) -->

		<div class="mod_flexicontent_standard mod_fcitems_box_standard_<?php echo $uniq_ord_id; ?>" id="mod_fcitems_box_standard_<?php echo $uniq_ord_id; ?>">

			<?php
			$oe_class = $rowtoggler ? 'odd' : 'even';

			foreach ($list[$ord]['standard'] as $item) :

				$img_force_dims_css = $img_auto_dims_css;

				if ($item_img_fit == 0 /* || $content_layout <= 3*/)
				{
					$img_force_dims_css .= ($item->image_w ? ' max-width:'. $item->image_w.'px; ' : '') . ($item->image_h ? ' max-height:'. $item->image_h.'px; ' : '');
				}

				if ($rowcount%$item_columns_std==0)
				{
					$oe_class = $oe_class === 'odd' ? 'even' : 'odd';
					$rowtoggler = !$rowtoggler;
				}

				$rowcount++;
			?>

			<!-- BOF item -->	
			<div class="mod_flexicontent_standard_wrapper<?php echo $mod_do_hlight; ?><?php echo ' '.$oe_class .($item->is_active_item ? ' fcitem_active' : ''); ?>"
				onmouseover="mod_fc_carousel_<?php echo $uniq_ord_id; ?>.stop(); mod_fc_carousel_<?php echo $uniq_ord_id; ?>.autoPlay=false;"
				onmouseout="if (mod_fc_carousel_<?php echo $uniq_ord_id; ?>_autoPlay==1) mod_fc_carousel_<?php echo $uniq_ord_id; ?>.play(<?php echo $interval; ?>,'next',true);	else if (mod_fc_carousel_<?php echo $uniq_ord_id; ?>_autoPlay==-1) mod_fc_carousel_<?php echo $uniq_ord_id; ?>.play(<?php echo $interval; ?>,'previous',true);"
			>
			<div class="mod_flexicontent_standard_wrapper_innerbox">

				<!-- BOF item title -->
				<?php ob_start(); ?>

					<?php if ($display_title || $item_handle_title === 2) : ?>
						<div class="fcitem_title_box" <?php echo !$display_title ? 'style="display:none!important;"' : ''; ?> >
							<span class="fcitem_title">
							<?php if ($link_title) : ?>
								<a href="<?php echo $item->link; ?>"><?php echo $item->title; ?></a>
							<?php else : ?>
								<?php echo $item->title; ?>
							<?php endif; ?>
							</span>
						</div>
					<?php endif; ?>

				<?php $captured_title = ob_get_clean(); $hasTitle = (boolean) trim($captured_title); ?>
				<!-- EOF item title -->


				<!-- BOF item's image -->
				<?php ob_start(); ?>

					<?php if ($mod_use_image && $item->image_rendered) : ?>

						<div class="image_standard" <?php echo $img_container_class;?>">
							<?php if ($mod_link_image) : ?>
								<a href="<?php echo $item->link; ?>"><?php echo $item->image_rendered; ?></a>
							<?php else : ?>
								<?php echo $item->image_rendered; ?>
							<?php endif; ?>
						</div>

					<?php elseif ($mod_use_image && $item->image) : ?>

						<div class="image_standard <?php echo $img_container_class;?>">
							<?php if ($mod_link_image) : ?>
								<a href="<?php echo $item->link; ?>">
									<img style="<?php echo $img_force_dims_css; ?>" src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" />
								</a>
							<?php else : ?>
								<img style="<?php echo $img_force_dims_css; ?>" src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" />
							<?php endif; ?>
						</div>

					<?php endif; ?>

				<?php $captured_image = ob_get_clean(); $hasImage = (boolean) trim($captured_image); ?>
				<!-- EOF item's image -->

				<?php echo $content_layout!=2 ? $captured_image : '';?>

				<!-- BOF item's content -->
				<?php if ($hasTitle || $display_date || $display_text || $display_hits || $display_voting || $display_comments || $mod_readmore || ($use_fields && @$item->fields && $fields)) : ?>
				<div class="content_standard <?php echo $content_container_class;?>">

					<?php echo $captured_title; ?>


					<?php if ($display_date && $item->date_created) : ?>
					<div class="fc_block">
						<div class="fc_inline fcitem_date created">
							<?php echo $item->date_created; ?>
						</div>
					</div>
					<?php endif; ?>

					<?php if ($display_date && $item->date_modified) : ?>
					<div class="fc_block">
						<div class="fc_inline fcitem_date modified">
							<?php echo $item->date_modified; ?>
						</div>
					</div>
					<?php endif; ?>

					<?php if ($display_hits && @ $item->hits_rendered) : ?>
					<div class="fc_block">
						<div class="fc_inline fcitem_hits">
							<?php echo $item->hits_rendered; ?>
						</div>
					</div>
					<?php endif; ?>

					<?php if ($display_voting && @ $item->voting) : ?>
					<div class="fc_block">
						<div class="fc_inline fcitem_voting">
							<?php echo $item->voting;?>
						</div>
					</div>
					<?php endif; ?>

					<?php if ($display_comments) : ?>
					<div class="fc_block">
						<div class="fc_inline fcitem_comments">
							<?php echo $item->comments_rendered; ?>
						</div>
					</div>
					<?php endif; ?>

					<?php if (($display_text && $item->text) || $item_handle_text === 2) : ?>
					<div class="fc_block fcitem_text" <?php echo !$display_text ? 'style="display:none!important;"' : ''; ?>>
						<?php echo $item->text; ?>
					</div>
					<?php endif; ?>

					<?php if ($use_fields && @$item->fields && $fields) : ?>
					<div class="fc_block fcitem_fields">

					<?php foreach ($item->fields as $k => $field) : ?>
						<?php if ( $hide_label_onempty && !strlen($field->display) ) continue; ?>
						<div class="field_block field_<?php echo $k; ?>">
							<?php if ($display_label) : ?>
							<div class="field_label"><?php echo $field->label . $text_after_label; ?></div>
							<?php endif; ?>
							<div class="field_value"><?php echo $field->display; ?></div>
						</div>
						<?php endforeach; ?>

					</div>
					<?php endif; ?>

					<?php if ($mod_readmore) : ?>
					<div class="fc_block">
						<div class="fcitem_readon">
							<a href="<?php echo $item->link; ?>" class="readon"><span><?php echo JText::_('FLEXI_MOD_READ_MORE'); ?></span></a>
						</div>
					</div>
					<?php endif; ?>

					<div class="clearfix"></div> 

				</div> <!-- EOF item's content -->
				<?php endif; ?>

				<?php echo $content_layout==2 ? $captured_image : '';?>

			</div>  <!-- EOF wrapper_innerbox -->
			</div>  <!-- EOF wrapper -->
			<!-- EOF item -->

			<?php
			if ($item_placement_std == 0)  // 0: clear, 1: as masonry tiles
			{
				//echo !($rowcount%$item_columns_std) ? '<div class="modclear"></div>' : '';
			}
			?>

			<?php endforeach; ?>
		</div>

		<!-- EOF DIV mod_flexicontent_standard (standard items) -->

	</div> <!-- mod_fc_carousel_mask{module_id} -->

	<?php if ($show_controls==1) : ?>
	<span id="next<?php echo $_icontrols_method; ?>_fcmod_<?php echo $uniq_ord_id; ?>"  class="mod_fc_nav fc_next fc_<?php echo $mode; ?>" ></span>
	<?php endif; ?>

</div> <!-- mod_fc_carousel -->


		<?php if ($show_page_handles) : ?>
			<div class="mod_fc_pages_outer">
				<div id="mod_fc_page_handles_<?php echo $uniq_ord_id; ?>" class="mod_fc_page_handles"
						onmouseover="mod_fc_carousel_<?php echo $uniq_ord_id; ?>.stop(); mod_fc_carousel_<?php echo $uniq_ord_id; ?>.autoPlay=false;"
						onmouseout="if (mod_fc_carousel_<?php echo $uniq_ord_id; ?>_autoPlay==1) mod_fc_carousel_<?php echo $uniq_ord_id; ?>.play(<?php echo $interval; ?>,'next',true);	else if (mod_fc_carousel_<?php echo $uniq_ord_id; ?>_autoPlay==-1) mod_fc_carousel_<?php echo $uniq_ord_id; ?>.play(<?php echo $interval; ?>,'previous',true);"
				>
					<?php $count=1; ?>
					<?php foreach ($list[$ord]['standard'] as $item) : ?>
					<span class="mod_fc_page_handle">
						<div class="mod_fc_page_handle_ico"></div>
					</span>
					<?php endforeach; ?>

				</div>
			</div>
		<?php endif; ?>

		<?php if (($show_controls==2) && ($dcontrols_auto || $dcontrols_pages || $dcontrols_items)) : ?>
			<div class="mod_fc_carousel_buttons_outer">
				<div class="mod_fc_carousel_buttons"
					onmouseover="mod_fc_carousel_<?php echo $uniq_ord_id; ?>.stop(); mod_fc_carousel_<?php echo $uniq_ord_id; ?>.autoPlay=false;"
					onmouseout="if (mod_fc_carousel_<?php echo $uniq_ord_id; ?>_autoPlay==1) mod_fc_carousel_<?php echo $uniq_ord_id; ?>.play(<?php echo $interval; ?>,'next',true);	else if (mod_fc_carousel_<?php echo $uniq_ord_id; ?>_autoPlay==-1) mod_fc_carousel_<?php echo $uniq_ord_id; ?>.play(<?php echo $interval; ?>,'previous',true);"
				>
					<?php if ($dcontrols_auto) : ?>
						<?php if ($dcontrols_labels) : ?>
							<span id="autoplay_controls_label_fcmod_<?php echo $uniq_ord_id; ?>" class="mod_fc_carousel_controls_label"><?php echo JText::_('FLEXI_MOD_CAROUSEL_AUTOPLAY'); ?></span>
						<?php endif; ?>
						<span id="stop_fcmod_<?php echo $uniq_ord_id; ?>" onclick="mod_fc_carousel_<?php echo $uniq_ord_id; ?>_autoPlay=0;" class="<?php if ($dcontrols_icon) : ?>mod_fc_carousel_btn_boot<?php else : ?>mod_fc_carousel_btn fc_stop<?php endif; ?>" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_STOP'); ?>"><?php if ($dcontrols_icon) : ?><i class="icon-pause"></i><?php endif; ?></span>
						<span id="backward_fcmod_<?php echo $uniq_ord_id; ?>" onclick="mod_fc_carousel_<?php echo $uniq_ord_id; ?>_autoPlay=-1;" class="<?php if ($dcontrols_icon) : ?>mod_fc_carousel_btn_boot<?php else : ?>mod_fc_carousel_btn fc_backward<?php endif; ?>" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_BACKWARD'); ?>"><?php if ($dcontrols_icon) : ?><i class="icon-backward"></i><?php endif; ?></span>
						<span id="forward_fcmod_<?php echo $uniq_ord_id; ?>" onclick="mod_fc_carousel_<?php echo $uniq_ord_id; ?>_autoPlay=1;" class="<?php if ($dcontrols_icon) : ?>mod_fc_carousel_btn_boot<?php else : ?>mod_fc_carousel_btn fc_forward<?php endif; ?>" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_FORWARD'); ?>"><?php if ($dcontrols_icon) : ?><i class="icon-forward"></i><?php endif; ?></span>
					<?php endif; ?>

					<?php if ($dcontrols_pages) : ?>
						<?php if ($dcontrols_labels) : ?>
							<span id="pages_controls_label_fcmod_<?php echo $uniq_ord_id; ?>" class="mod_fc_carousel_controls_label"><?php echo JText::_('FLEXI_MOD_CAROUSEL_PAGES'); ?></span>
						<?php endif; ?>
						<span id="previous_page_fcmod_<?php echo $uniq_ord_id; ?>" class="<?php if ($dcontrols_icon) : ?>mod_fc_carousel_btn_boot<?php else : ?>mod_fc_carousel_btn fc_previous_page<?php endif; ?>" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_PREVIOUS_PAGE'); ?>"><?php if ($dcontrols_icon) : ?><i class="icon-first"></i><?php endif; ?></span>
						<span id="next_page_fcmod_<?php echo $uniq_ord_id; ?>" class="<?php if ($dcontrols_icon) : ?>mod_fc_carousel_btn_boot<?php else : ?>mod_fc_carousel_btn fc_next_page<?php endif; ?>" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_NEXT_PAGE'); ?>"><?php if ($dcontrols_icon) : ?><i class="icon-last"></i><?php endif; ?></span>
					<?php endif; ?>

					<?php if ($dcontrols_items) : ?>
						<?php if ($dcontrols_labels) : ?>
							<span id="items_controls_label_fcmod_<?php echo $uniq_ord_id; ?>" class="mod_fc_carousel_controls_label"><?php echo JText::_('FLEXI_MOD_CAROUSEL_ITEMS'); ?></span>
						<?php endif; ?>
						<span id="previous_fcmod_<?php echo $uniq_ord_id; ?>" class="<?php if ($dcontrols_icon ==1) : ?>mod_fc_carousel_btn_boot<?php else : ?>mod_fc_carousel_btn fc_previous<?php endif; ?>" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_PREVIOUS'); ?>"><?php if ($dcontrols_icon ==1) : ?><i class="icon-arrow-left"></i><?php endif; ?></span>
						<span id="next_fcmod_<?php echo $uniq_ord_id; ?>" class="<?php if ($dcontrols_icon ==1) : ?>mod_fc_carousel_btn_boot<?php else : ?>mod_fc_carousel_btn fc_next<?php endif; ?>" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_NEXT'); ?>"><?php if ($dcontrols_icon ==1) : ?><i class="icon-arrow-right"></i><?php endif; ?></span>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ($show_item_handles) : ?>

			<div class="mod_fc_handles_outer">
				<div id="mod_fc_item_handles_<?php echo $uniq_ord_id; ?>"
					class="mod_fc_item_handles <?php echo $item_handles_dir=='horizontal' ? 'fc_add_scroller_horizontal fc_scrollbox_h' : 'fc_add_scroller fc_scrollbox_v';?>"
					onmouseover="mod_fc_carousel_<?php echo $uniq_ord_id; ?>.stop(); mod_fc_carousel_<?php echo $uniq_ord_id; ?>.autoPlay=false;"
					onmouseout="if (mod_fc_carousel_<?php echo $uniq_ord_id; ?>_autoPlay==1) mod_fc_carousel_<?php echo $uniq_ord_id; ?>.play(<?php echo $interval; ?>,'next',true);	else if (mod_fc_carousel_<?php echo $uniq_ord_id; ?>_autoPlay==-1) mod_fc_carousel_<?php echo $uniq_ord_id; ?>.play(<?php echo $interval; ?>,'previous',true);"
				>

				<?php $img_path = JUri::base(true) .'/'; ?>
				<?php $handle_classes = 'mod_fc_item_handle' . ($item_handles_dir=='horizontal' ? ' fc_scrollitem_h' : ' fc_scrollitem_v'); ?>
				<?php foreach ($list[$ord]['standard'] as $item) : ?>
					<?php
						$tip_html = '';
						if ($item_handle_title === 1 || $item_handle_text === 1)
						{
							$tip_html = flexicontent_html::getToolTip(($item_handle_title === 1 ? $item->title : null), ($item_handle_text === 1 ? $item->text : null), 0, 1);
						}
						$classes = $handle_classes . ($tip_html ? $tooltip_class : '');
					?>
						<span class="<?php echo $classes; ?>" title="<?php echo $tip_html; ?>" >
							<img alt="" src="<?php echo @ $item->image ? $item->image : $img_path.$mod_default_img_path; ?>" style="<?php echo 'width:'.$item_handle_width.'px; height:'.$item_handle_height.'px'; ?>" />
						</span>
					<?php endforeach; ?>

				</div>
			</div>
		<?php endif; ?>

		<?php if ($show_curritem_info) : ?>

			<div id="mod_fc_activeitem_info_<?php echo $uniq_ord_id; ?>" class="mod_fc_activeitem_info" >
				<?php /*echo JText::_( 'FLEXI_MOD_CAROUSEL_DISPLAYING').': ';*/ ?>

				<?php if ($item_handle_title === 2) : ?>
					<span id="mod_fc_info_title_<?php echo $uniq_ord_id; ?>" class="mod_fc_activeitem_info_title"></span>
				<?php endif; ?>

				<?php if ($item_handle_text === 2) : ?>
					<span id="mod_fc_info_text_<?php echo $uniq_ord_id; ?>" class="mod_fc_activeitem_info_text"></span>
				<?php endif; ?>

			</div>

		<?php endif; ?>

	<?php endif; ?>

	</div>

	<!-- EOF DIV mod_flexicontent -->


	<div class="modclear"></div>


	<?php
	// We need this inside the loop since ... we may have multiple orderings thus we may
	// have multiple container (1 item list container per order) being effected by JS
	$js = ''
		.'
		var mod_fc_carousel_'.$uniq_ord_id.'_ns_fxOptions='.$_fcx_fxOptions.';
		var mod_fc_carousel_'.$uniq_ord_id.'_autoPlay='.$autoplay.';
		var mod_fc_carousel_'.$uniq_ord_id.';

		jQuery(document).ready(function() {
		 jQuery("#mod_fc_carousel_container_'.$uniq_ord_id.'").imagesLoaded(function(){

			mod_fc_carousel_'.$uniq_ord_id.' = new fcxSlide({

				mode: "'.$mode.'",
				transition: "'.$transition.'",
				fxOptions: mod_fc_carousel_'.$uniq_ord_id.'_ns_fxOptions,
				transition_visible_duration: '.$transition_visible_duration.',

				items: jQuery("#mod_fcitems_box_standard_'.$uniq_ord_id.'").find("div.mod_flexicontent_standard_wrapper"),
				items_inner: jQuery("#mod_fcitems_box_standard_'.$uniq_ord_id.'").find("div.mod_flexicontent_standard_wrapper_innerbox"),
				items_box: jQuery("#mod_fcitems_box_standard_'.$uniq_ord_id.'"),
				items_mask: jQuery("#mod_fc_carousel_mask_'.$uniq_ord_id.'"),

				touch_walk: '.$_fcx_touch_walk.',
				mouse_walk: '.$_fcx_mouse_walk.',
				dragstart_margin: 20,
				dragwalk_margin: 100,

				responsive: '.$_fcx_responsive.',
				items_per_page: '.$_fcx_items_per_page.',
				item_size: '.$_fcx_item_size.',

				'.( !$show_page_handles ? '' : '
				page_handles: jQuery("#mod_fc_page_handles_'.$uniq_ord_id.'").find("span.mod_fc_page_handle"),
				page_handle_event: "'.$page_handle_event.'",
				').'

				'.( !$show_item_handles ? '' : '
				item_handles_box: jQuery("#mod_fc_item_handles_'.$uniq_ord_id.'"),
				item_handles: jQuery("#mod_fc_item_handles_'.$uniq_ord_id.'").find("span.mod_fc_item_handle"),
				item_handles_dir: "'.$item_handles_dir.'",
				item_handle_event: "'.$item_handle_event.'",
				item_handle_duration: '.$item_handle_duration.',
				').'

				'.( !$show_controls ? '' :
					'action_handles: {
						'.( ($show_controls==2 && $dcontrols_auto) ? 'stop: jQuery("#stop_fcmod_'.$uniq_ord_id.'"),' : '').'
						'.( ($show_controls==2 && $dcontrols_auto) ? 'playback:jQuery("#backward_fcmod_'.$uniq_ord_id.'"),' : '').'
						'.( ($show_controls==2 && $dcontrols_auto) ? 'play: jQuery("#forward_fcmod_'.$uniq_ord_id.'"),' : '').'
						'.( (($show_controls==1 && $icontrols_method=='page') || ($show_controls==2 && $dcontrols_pages)) ? 'previous_page:jQuery("#previous_page_fcmod_'.$uniq_ord_id.'"),' : '').'
						'.( (($show_controls==1 && $icontrols_method=='page') || ($show_controls==2 && $dcontrols_pages)) ? 'next_page: jQuery("#next_page_fcmod_'.$uniq_ord_id.'"),' : '').'
						'.( (($show_controls==1 && $icontrols_method=='item') || ($show_controls==2 && $dcontrols_items)) ? 'previous: jQuery("#previous_fcmod_'.$uniq_ord_id.'"),' : '').'
						'.( (($show_controls==1 && $icontrols_method=='item') || ($show_controls==2 && $dcontrols_items)) ? 'next: jQuery("#next_fcmod_'.$uniq_ord_id.'")' : '').'
					},
					action_handle_event: "click",
				').'

				edgeWrap: '.$_fcx_edgeWrap.',
				autoPlay: '.$_fcx_autoPlay.',
				playInterval: '.$interval.',
				playMethod: "'.$method.'",
				startItem: 0,

				onWalk: function(currentItem, currentPageHandle, currentItemHandle){
					this.items.removeClass("mod_fc_activeitem");
					jQuery(currentItem).addClass("mod_fc_activeitem");

					'.( !$show_page_handles ? '' : '
					this.page_handles.removeClass("active");
					jQuery(currentPageHandle).addClass("active");
					').'

					'.( !$show_item_handles ? '' : '
					this.item_handles.removeClass("active");
					jQuery(currentItemHandle).addClass("active");
					').'

					'.( !$show_curritem_info ? '' : '
						jQuery("#mod_fc_info_title_'.$uniq_ord_id.'").html( jQuery(currentItem).find(".fcitem_title").html() );
						jQuery("#mod_fc_info_text_'.$uniq_ord_id.'").html( jQuery(currentItem).find(".fcitem_text").html() );
					').'
				}
			});

			jQuery("#mod_fc_carousel_mask_'.$uniq_ord_id.'_loading").css("display", "none");
			jQuery("#mod_fc_carousel_mask_'.$uniq_ord_id.'").css("visibility", "visible");

			jQuery("#next_fcmod_'.$uniq_ord_id.'").css("visibility", "visible");
			jQuery("#previous_fcmod_'.$uniq_ord_id.'").css("visibility", "visible");

			jQuery("#next_page_fcmod_'.$uniq_ord_id.'").css("visibility", "visible");
			jQuery("#previous_page_fcmod_'.$uniq_ord_id.'").css("visibility", "visible");

			'.
			// Alternative but it includes padding
			// var maxHeight = jQuery("#mod_fcitems_box_standard_'.$uniq_ord_id.'")[0].clientHeight;

			// Alternative to use computed style, requires ie9+
			//var maxHeight_withpx = getComputedStyle(jQuery("#mod_fcitems_box_standard_'.$uniq_ord_id.'")[0], null).getPropertyValue("height")
			//mod_fc_carousel_'.$uniq_ord_id.'.items.each(function() {
			//	this.style.height = maxHeight_withpx;
			//});
			'
			fc_recalculateWindow();
		 });
		});
		';

	if ($js) $document->addScriptDeclaration($js);

	// ***********************************************************
	// Module specific styling (we use names containing module ID)
	// ***********************************************************

	$css = ''.
	/* CONTAINER of featured items */'
	#mod_fcitems_box_featured_'.$uniq_ord_id.' {
	}'.
	/* CONTAINER of each featured item */'
	#mod_fcitems_box_featured_'.$uniq_ord_id.' div.mod_flexicontent_standard_wrapper {
	}'.
	/* inner CONTAINER of each standard item */'
	#mod_fcitems_box_featured_'.$uniq_ord_id.' div.mod_flexicontent_standard_wrapper_innerbox {
		'.($inner_inline_css_feat ? '
		padding: '.$padding_top_bottom_feat.'px '.$padding_left_right_feat.'px !important;
		border-width: '.$border_width_feat.'px!important;
		margin: '.$margin_top_bottom_feat.'px '.$margin_left_right_feat.'px !important;
		' : '').'
	}'.

	/* CONTAINER of standard items */'
	#mod_fcitems_box_standard_'.$uniq_ord_id.' {
	}'.
	/* CONTAINER of each standard item */'
	#mod_fcitems_box_standard_'.$uniq_ord_id.' div.mod_flexicontent_standard_wrapper {
	}'.
	/* inner CONTAINER of each standard item */'
	#mod_fcitems_box_standard_'.$uniq_ord_id.' div.mod_flexicontent_standard_wrapper_innerbox {
		'.($inner_inline_css ? '
		padding: '.$padding_top_bottom.'px '.$padding_left_right.'px !important;
		border-width: '.$border_width.'px!important;
		margin: '.$margin_top_bottom.'px '.$margin_left_right.'px !important;
		' : '').'
	}'.

	/* The MASK that contains the CAROUSEL (mask clips it) */'
	#mod_fc_carousel_mask_'.$uniq_ord_id.' {
		z-index: 10
	}'.

	/* Active item information */'
	#mod_fc_activeitem_info_'.$uniq_ord_id.' {}'.

	/* Item button handles (instantly display respective item)*/'
	#mod_fc_item_handles_'.$uniq_ord_id.' {}'.

	/* CAROUSEL item/page handles clickable */'
	#mod_fc_item_handles_'.$uniq_ord_id.' span.mod_fc_item_handle:hover {
		'.($item_handle_event=='click' ? 'cursor:pointer;' : 'cursor:default;').'
	}
	#mod_fc_page_handles_'.$uniq_ord_id.' span.mod_fc_page_handle:hover {
		'.($page_handle_event=='click' ? 'cursor:pointer;' : 'cursor:default;').'
	}'
	;

	if ($css) $document->addStyleDeclaration($css);

	if ($item_placement_feat == 1 && $item_columns_feat > 1)
	{
		$js = "
		jQuery(document).ready(function(){
			var container = document.querySelector('div#mod_fcitems_box_featured_".$uniq_ord_id."');
			var msnry;
			// initialize Masonry after all images have loaded
			if (container) {
				imagesLoaded( container, function() {
					msnry = new Masonry( container );
				});
			}
		});
		";
		if ($js) $document->addScriptDeclaration($js);
	}
	if ($item_placement_std == 1 && $item_columns_std > 1)
	{
		$js = "
		jQuery(document).ready(function(){
			var container = document.querySelector('div#mod_fcitems_box_standard_".$uniq_ord_id."');
			var msnry;
			// initialize Masonry after all images have loaded
			if (container) {
				imagesLoaded( container, function() {
					msnry = new Masonry( container );
				});
			}
		});
		";
		if ($js) $document->addScriptDeclaration($js);
	}
	?>

	<?php endforeach; ?>

	<?php
	// Display readon of module
	include(JPATH_SITE.'/modules/mod_flexicontent/tmpl_common/readon.php');
	?>

</div>

<!-- EOF DIV mod_flexicontent_wrapper -->

