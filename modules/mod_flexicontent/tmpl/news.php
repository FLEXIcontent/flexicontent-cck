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
$margin_top_bottom_feat = $params->get($layout.'_margin_left_right_feat', 4);
$margin_left_right_feat = $params->get($layout.'_margin_left_right_feat', 4);
$border_width_feat = (int)$params->get($layout.'_border_width_feat', 1);
$item_column_mode_feat = (int)$params->get($layout.'_item_column_mode_feat', 1);// 0 column mode old, 1 grid minmax size
$item_width_feat = $params->get($layout.'_item_width_feat', '200px');
$item_fit_feat = $params->get($layout.'_content_width_fit_feat', 'auto-fill');


// Item Dimensions standard
$inner_inline_css = (int)$params->get($layout.'_inner_inline_css', 0);
$padding_top_bottom = (int)$params->get($layout.'_padding_top_bottom', 8);
$padding_left_right = (int)$params->get($layout.'_padding_left_right', 12);
$margin_top_bottom = $params->get($layout.'_margin_left_right', 4);
$margin_left_right = $params->get($layout.'_margin_left_right', 4);
$border_width = (int)$params->get($layout.'_border_width', 1);
$item_column_mode_std = (int)$params->get($layout.'_item_column_mode_std', 1);// 0 column mode old, 1 grid minmax size
$item_width_std = $params->get($layout.'_item_width_std', '200px');
$item_fit_std = $params->get($layout.'_content_width_fit_std', 'auto-fill');

$readmore_align_feat = $params->get('readmore_align_feat', 'center');
$readmore_align_std = $params->get('readmore_align_std', 'center');

$readmore_class_feat = $params->get('readmore_class_feat', 'readon btn feat');
$readmore_class_std = $params->get('readmore_class_std', 'readon btn std');

// Page builder
$news_builder_layout_feat = $params->get('news_builder_layout_feat');
$news_builder_layout = $params->get('news_builder_layout');


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
$img_path = \Joomla\CMS\Uri\Uri::base(true) .'/'; 

// image of FEATURED items, auto-fit and (optionally) limit to image max-dimensions to avoid stretching
$img_auto_dims_css_feat=" width: 100%; height: auto; display: block !important; border: 0 !important;";

// image of STANDARD items, auto-fit and (optionally) limit to image max-dimensions to avoid stretching
$img_auto_dims_css=" width: 100%; height: auto; display: block !important; border: 0 !important;";


/**
 * Featured
 * item placement 0: cleared, 1: as masonry tiles, 2: tabs, 3: accordion (sliders)
 */
$item_placement_feat = (int) $params->get($layout.'_item_placement_feat', 0);
$item_columns_feat   = (int) $params->get('item_columns_feat', 3);
$cols_class_feat     = $item_columns_feat <= 1 ? '' : 'cols_' . $item_columns_feat;

/**
 * Standard
 * item placement 0: cleared, 1: as masonry tiles, 2: tabs, 3: accordion (sliders)
 */
$item_placement_std = (int) $params->get($layout.'_item_placement', 0);
$item_columns_std   = (int) $params->get('item_columns', 4);
$cols_class_std     = $item_columns_std  <= 1 ? '' : 'cols_' . $item_columns_std;

$document = \Joomla\CMS\Factory::getDocument();
$jcookie  = \Joomla\CMS\Factory::getApplication()->input->cookie;


/**
 * Add masonry JS
 */
if (($item_placement_feat === 1 && $item_columns_feat > 1) || ($item_placement_std === 1 && $item_columns_std > 1))
{
	flexicontent_html::loadFramework('masonry');
	flexicontent_html::loadFramework('imagesLoaded');
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
?>


<!-- BOF DIV mod_flexicontent_wrapper -->

<div class="news mod_flexicontent_wrapper mod_flexicontent_wrap<?php echo $moduleclass_sfx; ?>" id="mod_flexicontent_news<?php echo $container_id; ?>">


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

		<div class="mod_flexicontent_featured mod_fcitems_box_featured_<?php echo $uniq_ord_id; ?> <?php echo($cols_class_feat ? ' '.$cols_class_feat : ''); ?> " id="mod_fcitems_box_featured_<?php echo $uniq_ord_id; ?>">

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

				if ($item_img_fit_feat == 0 /* || $content_layout_feat <= 3*/)
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
			<div class="mod_flexicontent_featured_wrapper_innerbox <?php echo $img_container_class_feat;?>">


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

						<div class="image_featured">
							<?php if ($mod_link_image_feat) : ?>
								<a href="<?php echo $item->link; ?>"><?php echo $item->image_rendered; ?></a>
							<?php else : ?>
								<?php echo $item->image_rendered; ?>
							<?php endif; ?>
						</div>

					<?php elseif ($mod_use_image_feat && $item->image) : ?>

						<div class="image_featured">
							<?php if ($mod_link_image_feat) : ?>
								<a href="<?php echo $item->link; ?>">
									<img <?php echo $img_size_feat; ?> style="<?php echo $img_force_dims_css_feat; ?>" src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" />
								</a>
							<?php else : ?>
								<img <?php echo $img_size_feat; ?> style="<?php echo $img_force_dims_css_feat; ?>" src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" />
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
						<div class="fcitem_readon <?php echo $readmore_align_feat;?>">
							<a href="<?php echo $item->link; ?>" class="<?php echo $readmore_class_feat; ?>"><span><?php echo \Joomla\CMS\Language\Text::_('FLEXI_MOD_READ_MORE'); ?></span></a>
						</div>
					</div>
					<?php endif; ?>

				</div> <!-- EOF item's content -->
				<?php endif; ?>

				<?php echo $content_layout_feat==2 ? $captured_image : '';?>

				
			<?php endif; /* EOF: Content display via Parameter-based Layout */ ?>


			<?php if ($news_builder_layout_feat > 0){
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
				elseif ($item_placement_feat === 0)  // 0: clear, 1: as masonry tiles
				{
					//echo !($rowcount%$item_columns_feat) ? '<div class="modclear"></div>' : '';
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
		$rowcount = 0;
		$item_ids_index = array();

		foreach ($list[$ord]['standard'] as $item)
		{
			$item_ids_index[$item->id] = 1;
		}
		?>

		<!-- BOF DIV mod_flexicontent_standard (standard items) -->

		<div class="mod_flexicontent_standard mod_fcitems_box_standard_<?php echo $uniq_ord_id; ?> <?php echo ($cols_class_std ? ' '.$cols_class_std : ''); ?>" id="mod_fcitems_box_standard_<?php echo $uniq_ord_id; ?>">

			<?php
			$oe_class = $rowtoggler ? 'odd' : 'even';

			if ($item_placement_std === 2 || $item_placement_std === 3)
			{
				$first_item        = reset($list[$ord]['standard']);
				$itemset_tagid     = 'fc_umod_itemset_std_' . $uniq_ord_id;

				$last_active_tagid = isset($active_tagids_std->$itemset_tagid)
					? $active_tagids_std->$itemset_tagid
					: $itemset_tagid . '_' . $first_item->id;

				$tagid_parts = explode('_', $last_active_tagid);
				$activated_itemid = end($tagid_parts);

				$last_active_tagid = isset($item_ids_index[$activated_itemid])
					? $last_active_tagid
					: $itemset_tagid . '_' . $first_item->id;

				echo $item_placement_std === 2
					? \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.startTabSet', $itemset_tagid, array('active' => $last_active_tagid))
					: \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.startAccordion', $itemset_tagid, array('active' => $last_active_tagid));
			}

			foreach ($list[$ord]['standard'] as $item) :

				if ($item_placement_std === 2 || $item_placement_std === 3)
				{
					echo $item_placement_std === 2
						? \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.addTab', $itemset_tagid, $itemset_tagid . '_' . $item->id, $item->title)
						: \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.addSlide', $itemset_tagid, $item->title, $itemset_tagid . '_' . $item->id);
				}

				$img_force_dims_css = $img_auto_dims_css;

				if ($item_img_fit == 0 /* || $content_layout <= 3*/)
				{
					$img_force_dims_css .= ($item->image_w ? ' max-width:'. $item->image_w.'px; ' : '') . ($item->image_h ? ' max-height:'. $item->image_h.'px; ' : '');
				}

				$img_size = 
					($item->image_w ? ' width="' . $item->image_w . '"' : '') .
					($item->image_h ? ' height="' . $item->image_h . '"' : '')
					;

				if ($rowcount%$item_columns_std==0)
				{
					$oe_class = $oe_class === 'odd' ? 'even' : 'odd';
					$rowtoggler = !$rowtoggler;
				}

				$rowcount++;
			?>

			<!-- BOF item -->	
			<div class="mod_flexicontent_standard_wrapper<?php echo $mod_do_hlight; ?><?php echo ' '.$oe_class .($item->is_active_item ? ' fcitem_active' : '') ; ?> <?php echo ($item_placement_std == 1) ? 'masonry' : '';?> "
				onmouseover=""
				onmouseout=""
			>
			<div class="mod_flexicontent_standard_wrapper_innerbox <?php echo $img_container_class;?>">


			<?php if ($std_params_layout) : /* BOF: Content display via Parameter-based Layout */ ?>


				<!-- BOF item title -->
				<?php ob_start(); ?>

					<?php if ($display_title) : ?>
						<div class="fcitem_title_box">
							<span class="fcitem_title">
							<?php if ($link_title) : ?>
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

					<?php if ($mod_use_image && $item->image_rendered) : ?>

						<div class="image_standard">
							<?php if ($mod_link_image) : ?>
								<a href="<?php echo $item->link; ?>"><?php echo $item->image_rendered; ?></a>
							<?php else : ?>
								<?php echo $item->image_rendered; ?>
							<?php endif; ?>
						</div>

					<?php elseif ($mod_use_image && $item->image) : ?>

						<div class="image_standard">
							<?php if ($mod_link_image) : ?>
								<a href="<?php echo $item->link; ?>">
									<img <?php echo $img_size; ?> style="<?php echo $img_force_dims_css; ?>" src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" />
								</a>
							<?php else : ?>
								<img <?php echo $img_size; ?> style="<?php echo $img_force_dims_css; ?>" src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" />
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

					<?php if ($display_text && $item->text) : ?>
					<div class="fc_block fcitem_text">
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
					<div class="fc_block readmore">
						<div class="fcitem_readon <?php echo $readmore_align_std;?>">
							<a href="<?php echo $item->link; ?>" class="<?php echo $readmore_class_std; ?>"><span><?php echo \Joomla\CMS\Language\Text::_('FLEXI_MOD_READ_MORE'); ?></span></a>
						</div>
					</div>
					<?php endif; ?>

				</div> <!-- EOF item's content -->
				<?php endif; ?>

				<?php echo $content_layout==2 ? $captured_image : '';?>

				

			<?php endif; /* EOF: Content display via Parameter-based Layout */ ?>


			<?php if ($news_builder_layout > 0){
				// Content display via Builder-based Layouts
				echo $std_builder_layout
					? str_replace('{{fc-item-id}}', $item->id, $std_builder_layout)
					: '';
			}
			?>


			</div>  <!-- EOF wrapper_innerbox -->
			</div>  <!-- EOF wrapper -->
			<!-- EOF item -->

			<?php
			if ($item_placement_std === 2 || $item_placement_std === 3)
			{
				echo $item_placement_std === 2
					? \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endTab')
					: \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endSlide');
			}
			elseif ($item_placement_std === 0)  // 0: clear, 1: as masonry tiles
			{
				//echo !($rowcount%$item_columns_std) ? '<div class="modclear"></div>' : '';
			}
			?>

			<?php
			endforeach;
			if ($item_placement_std === 2 || $item_placement_std === 3)
			{
				echo $item_placement_std === 2
					? \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endTabSet')
					: \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endAccordion');

				\Joomla\CMS\Factory::getDocument()->addScriptDeclaration("
				(function($) {
					$(document).ready(function ()
					{
						$('#" . $itemset_tagid . ($item_placement_std === 2 ? 'Tabs' : '') . "').on('shown', function ()
						{
							var fcMods_conf = fclib_getCookie('" . $cookie_name ."');
							try { fcMods_conf = JSON.parse(fcMods_conf); } catch(e) { fcMods_conf = {}; }

							fcMods_conf['" . $module->id ."'] = fcMods_conf['" . $module->id ."'] || {};
							fcMods_conf['" . $module->id ."']['active_tagids_std'] = fcMods_conf['" . $module->id ."']['active_tagids_std'] || {};
							" . ($item_placement_std === 2
								? "fcMods_conf['" . $module->id ."']['active_tagids_std']['" . $itemset_tagid . "'] = $('#" . $itemset_tagid . "Tabs').next().find('.active').attr('id');"
								: "fcMods_conf['" . $module->id ."']['active_tagids_std']['" . $itemset_tagid . "'] = $('#" . $itemset_tagid . " .in').attr('id');") . "
							fclib_setCookie('" . $cookie_name ."', JSON.stringify(fcMods_conf), 7);
							window.console.log(JSON.stringify(fcMods_conf));
						});

						$('#" . $itemset_tagid . ($item_placement_std === 2 ? 'Tabs' : '') . "').on('hidden', function ()
						{
							var fcMods_conf = fclib_getCookie('" . $cookie_name ."');
							try { fcMods_conf = JSON.parse(fcMods_conf); } catch(e) { fcMods_conf = {}; }

							fcMods_conf['" . $module->id ."'] = fcMods_conf['" . $module->id ."'] || {};
							fcMods_conf['" . $module->id ."']['active_tagids_std'] = fcMods_conf['" . $module->id ."']['active_tagids_std'] || {};
							fcMods_conf['" . $module->id ."']['active_tagids_std']['" . $itemset_tagid . "'] = null;
							fclib_setCookie('" . $cookie_name ."', JSON.stringify(fcMods_conf), 7);
							window.console.log(JSON.stringify(fcMods_conf));
						});

						var fcMods_conf = fclib_getCookie('" . $cookie_name ."');
						try { fcMods_conf = JSON.parse(fcMods_conf); } catch(e) { fcMods_conf = {}; }

						fcMods_conf['" . $module->id ."'] = fcMods_conf['" . $module->id ."'] || {};
						fcMods_conf['" . $module->id ."']['active_tagids_std'] = fcMods_conf['" . $module->id ."']['active_tagids_std'] || {};

						if (!!fcMods_conf['" . $module->id ."']['active_tagids_std']['" . $itemset_tagid . "'])
						{
							// Hide default active slide
							$('#" . $itemset_tagid ." .collapse').removeClass('in');

							// Show the last active slide
							$('#' + fcMods_conf['" . $module->id ."']['active_tagids_std']['" . $itemset_tagid . "']).addClass('in');
						}
					});
				})(jQuery);
				");
			}
			?>
		</div>

		<!-- EOF DIV mod_flexicontent_standard (standard items) -->


	<?php endif; ?>

	</div>

	<!-- EOF DIV mod_flexicontent -->


	<div class="modclear"></div>


	<?php
	// We need this inside the loop since ... we may have multiple orderings thus we may
	// have multiple container (1 item list container per order) being effected by JS
	$js = ''
		;
	if ($js) $document->addScriptDeclaration($js);

	// ***********************************************************
	// Module specific styling (we use names containing module ID)
	// ***********************************************************
	function convertColumnsToPercentage($columns) {
		switch ($columns) {
			case 1:
				return '100%';
			case 2:
				return '48%';
			case 3:
				return '31%';
			case 4:
				return '23%';
			case 5:
				return '18%';
			case 6:
				return '14%';
			case 7:
				return '12%';
			case 8:
				return '10%';
			default:
				return '100%';
		}
	}
	
	if ($item_column_mode_feat == 0) {
		$item_columns_feat = convertColumnsToPercentage($item_columns_feat);
	} else {
		$item_columns_feat = $item_width_feat;
	}
	
	if ($item_column_mode_std == 0) {
		$item_columns_std = convertColumnsToPercentage($item_columns_std);
	} else {
		$item_columns_std = $item_width_std;
	}
	

	$css = ''.
	/* CONTAINER of featured items */'
	#mod_fcitems_box_featured_'.$uniq_ord_id.' {
	}'.
	/* CONTAINER of each featured item */'
	#mod_fcitems_box_featured_'.$uniq_ord_id.' div.mod_flexicontent_standard_wrapper {
	}'.
	/* inner CONTAINER of each standard item */'
	#mod_fcitems_box_featured_'.$uniq_ord_id.'.mod_flexicontent_featured {
		'.($inner_inline_css_feat ? '
		padding: '.$padding_top_bottom_feat.' '.$padding_left_right_feat.' !important;
		border-width: '.$border_width_feat.' !important;
		row-gap: '.$margin_top_bottom_feat.' !important ;
		gap:'.$margin_left_right_feat.' !important;
		' : '').'
		grid-template-columns: repeat('.$item_fit_feat.', minmax('.$item_columns_feat.', 1fr));
	}'.
	/* CONTAINER of standard items */'
	#mod_fcitems_box_standard_'.$uniq_ord_id.' {
	}'.
	/* CONTAINER of each standard item */'
	#mod_fcitems_box_standard_'.$uniq_ord_id.' div.mod_flexicontent_standard_wrapper {
	}'.
	/* inner CONTAINER of each standard item */'
	#mod_fcitems_box_standard_'.$uniq_ord_id.'.mod_flexicontent_standard {
		'.($inner_inline_css ? '
		padding: '.$padding_top_bottom.' '.$padding_left_right.' !important;
		border-width: '.$border_width.' !important;
		row-gap: '.$margin_top_bottom.' !important;
		gap:'.$margin_left_right.' !important;
		' : '').'
		grid-template-columns: repeat('.$item_fit_std.', minmax('.$item_columns_std.', 1fr));
	}'.
	/* column size for masonry */'
	#mod_fcitems_box_featured_'.$uniq_ord_id.' .mod_flexicontent_featured_wrapper.masonry{
	'.($inner_inline_css ? '
		width: calc('.$item_columns_feat.' - '.$margin_left_right_feat.') !important;
		margin-right:'.$margin_left_right_feat.' !important;
		margin-bottom:'.$margin_top_bottom_feat.' !important ;
		' : '
		width: calc('.$item_columns_feat.' - 20px) !important;
		margin-right:20px !important;
		margin-bottom:20px !important ;
		').'
	}
	#mod_fcitems_box_standard_'.$uniq_ord_id.' .mod_flexicontent_standard_wrapper.masonry {
	'.($inner_inline_css ? '
		width: calc('.$item_columns_std.' - '.$margin_left_right.') !important;
		margin-right:'.$margin_left_right.' !important;
		margin-bottom:'.$margin_top_bottom.' !important;
		' : '
		width: calc('.$item_columns_std.' - 20px) !important;
		margin-right: 20px !important;
		margin-bottom:20px !important;
		').'
		}'.
	/* responsive for masonry */'
	@media only screen and (min-device-width : 320px) and (max-device-width : 480px) {
	#mod_fcitems_box_featured_'.$uniq_ord_id.' .mod_flexicontent_featured_wrapper.masonry{
		width: 100% !important;
	}
	#mod_fcitems_box_standard_'.$uniq_ord_id.' .mod_flexicontent_standard_wrapper.masonry {
		width: 100% !important;
		}
	}'.

	''
	;

	if ($css) $document->addStyleDeclaration($css);

	if ($item_placement_feat == 1 && $item_columns_feat > 1)
	{
		$margin_left_right_feat = intval($margin_left_right_feat);
		$js = "
		jQuery(document).ready(function(){
			var container_lead = document.querySelector('div#mod_fcitems_box_featured_".$uniq_ord_id."');
			var msnry;
			// initialize Masonry after all images have loaded
			if (container_lead) {
				imagesLoaded( container_lead, function() {
					msnry = new Masonry( container_lead, {
					columnWidth: '#mod_fcitems_box_featured_$uniq_ord_id .mod_flexicontent_featured_wrapper.masonry',
					horizontalOrder: true,
					percentPosition: true
					});
				});
			}
		});
		";
		if ($js) $document->addScriptDeclaration($js);
	}
	if ($item_placement_std == 1 && $item_columns_std > 1)
	{
		$margin_left_right = intval($margin_left_right);
		$js = "
		jQuery(document).ready(function(){
			var container = document.querySelector('div#mod_fcitems_box_standard_".$uniq_ord_id."');
			var msnry;
			// initialize Masonry after all images have loaded
			if (container) {
				imagesLoaded( container, function() {
					msnry = new Masonry( container, {
	 				columnWidth: '#mod_fcitems_box_standard_$uniq_ord_id .mod_flexicontent_standard_wrapper.masonry',
					horizontalOrder: true,
					percentPosition: true
					});
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

