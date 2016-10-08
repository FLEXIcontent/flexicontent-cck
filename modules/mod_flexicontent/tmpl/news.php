<?php 
/**
 * $caching
 * $ordering
 * $count
 * $featured
 *
 * // Display parameters
 * $moduleclass_sfx
 * $layout 
 * $width
 * $height
 * // standard
 * $display_title
 * $link_title
 * $display_date
 * $display_text
 * $mod_readmore
 * $mod_use_image
 * $mod_link_image
 * // featured
 * $display_title_feat 
 * $link_title_feat 
 * $display_date_feat
 * $display_text_feat 
 * $mod_readmore_feat
 * $mod_use_image_feat 
 * $mod_link_image_feat 
 *
 * // Fields parameters
 * $use_fields 
 * $display_label 
 * $fields 
 * // featured
 * $use_fields_feat
 * $display_label_feat 
 * $fields_feat 
 *
 * // Custom parameters
 * $custom1 
 * $custom2 
 * $custom3 
 * $custom4 
 * $custom5 
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



// *****************************************************
// Content placement and default image of standard items
// *****************************************************
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



// *******************************
// Default image and image fitting
// *******************************
$mod_default_img_path = $params->get('mod_default_img_path', 'components/com_flexicontent/assets/images/image.png');
$img_path = JURI::base(true) .'/'; 

// image of FEATURED items, auto-fit and (optionally) limit to image max-dimensions to avoid stretching
$img_auto_dims_css_feat=" width: 100%; height: auto; display: block !important; border: 0 !important;";

// image of STANDARD items, auto-fit and (optionally) limit to image max-dimensions to avoid stretching
$img_auto_dims_css=" width: 100%; height: auto; display: block !important; border: 0 !important;";


// Featured
$item_columns_feat = $params->get('item_columns_feat', 1);
$item_placement_feat = $params->get($layout.'_item_placement_feat', 0);  // 0: cleared, 1: as masonry tiles
$cols_class_feat = ($item_columns_feat <= 1)  ?  ''  :  'cols_'.$item_columns_feat;
	
// Standard
$item_placement_std = $params->get($layout.'_item_placement', 0);  // -1: other, 0: cleared, 1: as masonry tiles
$item_columns_std = $params->get('item_columns', 2);
$cols_class_std  = ($item_columns_std  <= 1)  ?  ''  :  'cols_'.$item_columns_std;

$document = JFactory::getDocument();

// Add masonry JS
if ( ($item_placement_feat == 1 && $item_columns_feat > 1) || ($item_placement_std == 1 && $item_columns_std > 1) )
{
	flexicontent_html::loadFramework('masonry');
	flexicontent_html::loadFramework('imagesLoaded');
}
$container_id = $module->id . (count($catdata_arr)>1 && $catdata ? '_'.$catdata->id : '');
?>

<div class="news mod_flexicontent_wrapper mod_flexicontent_wrap<?php echo $moduleclass_sfx; ?>" id="mod_flexicontent_news<?php echo $container_id; ?>">
	
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
		'random'=>JText::_( 'FLEXI_UMOD_RANDOM_ITEMS' ),
		'field'=>JText::sprintf( 'FLEXI_UMOD_CUSTOM_FIELD', $orderby_custom_field->label)
	);
	
	$separator = "";
	$rowtoggler = 0;
	
	foreach ($ordering as $ord) :
  	echo $separator;
	  if (isset($list[$ord]['featured']) || isset($list[$ord]['standard'])) {
  	  $separator = "<div class='ordering_separator' ></div>";
    } else {
  	  $separator = "";
  	  continue;
  	}
  	// PREPEND ORDER if using more than 1 orderings ...
  	$order_name = $ord ? $ord : 'default';
		$uniq_ord_id = (count($list)>1 ? $order_name : '').$container_id;
	?>
	<div id="<?php echo 'order_'.$order_name.$container_id; ?>" class="mod_flexicontent">
		
		<?php	if ($ordering_addtitle && $ord) : ?>
		<div class='order_group_title'><?php echo isset($ord_titles[$ord]) ? $ord_titles[$ord] : $ord; ?></div>
		<?php endif; ?>
	
		
	<?php if (isset($list[$ord]['featured'])) : ?>
	
		<!-- BOF featured items -->
		<?php	$rowcount = 0; ?>
		
		<div class="mod_flexicontent_featured mod_fcitems_box_featured_<?php echo $uniq_ord_id; ?>" id="mod_fcitems_box_featured_<?php echo $uniq_ord_id; ?>">
			
			<?php $oe_class = $rowtoggler ? 'odd' : 'even'; ?>
			
			<?php foreach ($list[$ord]['featured'] as $item) : ?>
			<?php
				$img_force_dims_css_feat = $img_auto_dims_css_feat;
				if ($item_img_fit_feat==0/* || $content_layout_feat <= 3*/)
				{
					$img_force_dims_css_feat .= ($item->image_w ? ' max-width:'. $item->image_w.'px; ' : '') . ($item->image_h ? ' max-height:'. $item->image_h.'px; ' : '');
				}

				if ($rowcount%$item_columns_feat==0)
				{
					$oe_class = $oe_class=='odd' ? 'even' : 'odd';
					$rowtoggler = !$rowtoggler;
				}
				$rowcount++;
			?>
			
			<!-- BOF current item -->	
			<div class="mod_flexicontent_featured_wrapper<?php echo $mod_do_hlight_feat; ?><?php echo ' '.$oe_class .($item->is_active_item ? ' fcitem_active' : '') .($cols_class_feat ? ' '.$cols_class_feat : ''); ?>">
			<div class="mod_flexicontent_featured_wrapper_innerbox">
			
				<!-- BOF current item's title -->	
				<?php ob_start(); ?>
				<?php if ($display_title_feat) : ?>
				<div class="fc_block" >
					<div class="fc_inline fcitem_title">
						<?php if ($link_title_feat) : ?>
						<a href="<?php echo $item->link; ?>"><?php echo $item->title; ?></a>
						<?php else : ?>	
						<?php echo $item->title; ?>
						<?php endif; ?>
					</div>
				</div>
				<?php endif; ?>
				<?php $captured_title = ob_get_clean(); $hasTitle = (boolean) trim($captured_title); ?>
				<!-- EOF current item's title -->	
				
				<!-- BOF current item's image -->	
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
				<!-- BOF current item's image -->
				
				<?php echo $content_layout_feat!=2 ? $captured_image : '';?>
				
				<!-- BOF current item's content -->
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
					
				</div> <!-- EOF current item's content -->
				<?php endif; ?>
				
				<?php echo $content_layout_feat==2 ? $captured_image : '';?>
				
			</div>  <!-- EOF wrapper_innerbox -->
			</div>  <!-- EOF wrapper -->
			<!-- EOF current item -->
			<?php if ($item_placement_feat==0) /* 0: clear, 1: as masonry tiles */ echo !($rowcount%$item_columns_feat) ? '<div class="modclear"></div>' : ''; ?>
			<?php endforeach; ?>
			
		</div>
		
		<!-- EOF featured items -->
		
	<?php endif; ?>
		
	<div class="modclear"></div>
	
		
	<?php if (isset($list[$ord]['standard'])) : ?>
	
		<!-- BOF standard items -->
		<?php	$rowcount = 0; ?>
		
		<div class="mod_flexicontent_standard mod_fcitems_box_standard_<?php echo $uniq_ord_id; ?>" id="mod_fcitems_box_standard_<?php echo $uniq_ord_id; ?>">
			
			<?php $oe_class = $rowtoggler ? 'odd' : 'even'; $n=-1; ?>
			
			<?php foreach ($list[$ord]['standard'] as $item) : ?>
			<?php
				$img_force_dims_css = $img_auto_dims_css;
				if ($item_img_fit==0/* || $content_layout <= 3*/)
				{
					$img_force_dims_css .= ($item->image_w ? ' max-width:'. $item->image_w.'px; ' : '') . ($item->image_h ? ' max-height:'. $item->image_h.'px; ' : '');
				}

				if ($rowcount%$item_columns_std==0)
				{
					$oe_class = $oe_class=='odd' ? 'even' : 'odd';
					$rowtoggler = !$rowtoggler;
				}
				$rowcount++;
				$n++;
			?>
			
			<!-- BOF current item -->	
			<div class="mod_flexicontent_standard_wrapper<?php echo $mod_do_hlight; ?><?php echo ' '.$oe_class .($item->is_active_item ? ' fcitem_active' : '') .($cols_class_std ? ' '.$cols_class_std : ''); ?>"
				onmouseover=""
				onmouseout=""
			>
			<div class="mod_flexicontent_standard_wrapper_innerbox">
				
				<!-- BOF current item's title -->	
				<?php ob_start(); ?>
				<?php if ($display_title) : ?>
				<div class="fc_block" >
					<div class="fc_inline fcitem_title">
						<?php if ($link_title) : ?>
							<a href="<?php echo $item->link; ?>"><?php echo $item->title; ?></a>
						<?php else : ?>	
							<?php echo $item->title; ?>
						<?php endif; ?>
					</div>
				</div>
				<?php endif; ?>
				<?php $captured_title = ob_get_clean(); $hasTitle = (boolean) trim($captured_title); ?>
				<!-- EOF current item's title -->	
				
				<!-- BOF current item's image -->	
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
				<!-- BOF current item's image -->	
				
				<?php echo $content_layout!=2 ? $captured_image : '';?>
				
				<!-- BOF current item's content -->
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
					<div class="fc_block">
						<div class="fcitem_readon">
							<a href="<?php echo $item->link; ?>" class="readon"><span><?php echo JText::_('FLEXI_MOD_READ_MORE'); ?></span></a>
						</div>
					</div>
					<?php endif; ?>

					<div class="clearfix"></div> 
					
				</div> <!-- EOF current item's content -->
				<?php endif; ?>
				
				<?php echo $content_layout==2 ? $captured_image : '';?>
				
			</div>  <!-- EOF wrapper_innerbox -->
			</div>  <!-- EOF wrapper -->
			<!-- EOF current item -->
			<?php if ($item_placement_std==0) echo !($rowcount%$item_columns_std) ? '<div class="modclear"></div>' : ''; ?>
			<?php endforeach; ?>
			
		</div>
		<!-- EOF standard items -->
	
	<?php endif; ?>
		
	<div class="modclear"></div>
	
	</div>
	
	<?php
	// We need this inside the loop since ... we may have multiple orderings thus we may
	// have multiple container (1 item list container per order) being effected by JS
	$js = ''
		;
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
	
	''
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
