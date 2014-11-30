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
 * $add_ccs
 * $add_tooltips
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

$mod_width_feat 	= (int)$params->get('mod_width', 110);
$mod_height_feat 	= (int)$params->get('mod_height', 110);
$mod_width 				= (int)$params->get('mod_width', 80);
$mod_height 			= (int)$params->get('mod_height', 80);


$hide_label_onempty_feat = (int)$params->get('hide_label_onempty_feat', 0);
$hide_label_onempty      = (int)$params->get('hide_label_onempty', 0);


// Carousel direction and Common Dimensions
$mode = $params->get('carousel_mode', 'horizontal');

// Fixed size / Responsive
$responsive   = (int)$params->get('carousel_responsive', 1);
$item_size_px = (int)$params->get('carousel_item_size_px', 240);
$items_per_page = (int)$params->get('carousel_items_per_page', 2);

// Item Dimensions
$inner_inline_css = (int)$params->get('carousel_inner_inline_css', 1);
$padding_top_bottom = (int)$params->get('carousel_padding_top_bottom', 8);
$padding_left_right = (int)$params->get('carousel_padding_left_right', 12);
$margin_top_bottom= (int)$params->get('carousel_margin_left_right', 0);
$margin_left_right = (int)$params->get('carousel_margin_left_right', 0);
$border_width = (int)$params->get('carousel_border_width', 1);

// Content placement and default image
$content_display = $params->get('carousel_content_display', 1);  // 0: always visible, 1: On mouse over / item active, 2: On mouse over
$content_layout = $params->get('carousel_content_layout', 0);  // 0/1: floated (right/left), 2/3: cleared (above/below), 4/5/6: overlayed (top/bottom/full)

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

// Default image and image fitting
$mod_default_img_path = $params->get('mod_default_img_path', 'components/com_flexicontent/assets/images/image.png');
$item_img_fit = $params->get('carousel_img_fit', 0);
$img_path = JURI::base(true) .'/'; 

$img_force_dims_feat="max-width:".$mod_width_feat."px; max-height:".$mod_height_feat."px; width: auto; height: auto; display: block!important;";
$img_force_dims=" width: 100%; height: auto; display: block!important; border: 0 !important;";

// Limit auto-fit to image max-dimensions to avoid stretching
$_img_limit_dims_=" max-width:".$mod_width."px; max-height:".$mod_height."px;";
if ($item_img_fit==0 || $content_layout <= 1) {
	$img_force_dims .= $_img_limit_dims_;
}

// Edge behaviour, touch/mouse drag support
$edgewrap = (int)$params->get('carousel_edgewrap', 1);
$touch_walk = (int)$params->get('carousel_touch_walk', 1);
$mouse_walk = (int)$params->get('carousel_mouse_walk', 0);

// Autoplay, autoplay interval, autoplay method
$autoplay = (int)$params->get('carousel_autoplay', 1);
$interval = (int)$params->get('carousel_interval', 5000);
$method   = $params->get('carousel_method', 'page');  // page, item

// Page Buttons (= carousel page handles)
$show_page_handles  = (int)$params->get('carousel_show_page_handles', 1);
$page_handle_event  = $params->get('carousel_page_handle_event', 'click');

// Item Buttons (= carousel item handles)
$show_item_handles    = (int)$params->get('carousel_show_handles', 1);
$item_handle_duration = (int)$params->get('carousel_handle_duration', 400);
$item_handle_width    = (int)$params->get('carousel_handle_width', 64);
$item_handle_height   = (int)$params->get('carousel_handle_height', 64);
$item_handle_event    = $params->get('carousel_handle_event', 'mouseover');
$item_handle_title    = $params->get('carousel_handle_title', 0);
$item_handle_text     = $params->get('carousel_handle_text', 0);

// Miscellaneous Optionally displayed
$show_controls   = (int)$params->get('carousel_show_controls', 1);
// Detached controls
$dcontrols_labels = (int)$params->get('carousel_dcontrols_labels', 1);
$dcontrols_auto   = (int)$params->get('carousel_dcontrols_auto', 1);
$dcontrols_pages  = (int)$params->get('carousel_dcontrols_pages', 1);
$dcontrols_items  = (int)$params->get('carousel_dcontrols_items', 1);
// Intergrated controls
$icontrols_method  = $params->get('carousel_icontrols_method', 'page');
$_icontrols_method = ($icontrols_method=='page' ? '_page' : '');

// Transition:  method and duration
$transition  = $params->get('carousel_transition', 'scroll');
$duration    = (int)$params->get('carousel_duration', 800);

// Transition easing:  method and in-out slowness
$easing       = $params->get('carousel_easing', 'quart');
$easing_inout = $params->get('carousel_easing_inout', 'easeOut');
// Moving duration for already visible items
$transition_visible_duration = (int)$params->get('carousel_transition_visible_duration', 100);

// ... calculate name of easing function
$easing_name = ($easing == 'linear' || $easing == 'swing') ?  $easing  :  $easing_inout . ucfirst($easing);

// ... decide if showing handle onHover item info
$show_curritem_info = $item_handle_title==2 || $item_handle_text==2;

// Carousel specially created parameter values
$_fcx_edgeWrap     = $edgewrap ? "true" : "false";
$_fcx_touch_walk   = $touch_walk ? "true" : "false";
$_fcx_mouse_walk   = $mouse_walk ? "true" : "false";
$_fcx_autoPlay     = $autoplay ? "true" : "false";
$_fcx_fxOptions    = '{ duration:'.$duration.', easing: "'.$easing_name.'" }';
$_fcx_item_size    = $item_size_px;  // item width (horizontal) OR height (vertical) in case of fixed item size
$_fcx_responsive   = $responsive;  // 0: px, 1: percentage
$_fcx_items_per_page = $items_per_page;  // ZERO for horizontal, this value will be overwritten by auto-calulation, after page load ends

if ($interval < $duration) {
	echo "autoplay interval must not be smaller than the EFFECT (scroll/fade/etc) duration (even if autoplay is disabled), please correct in module configuration";
}
?>

<div class="carousel mod_flexicontent_wrapper mod_flexicontent_wrap<?php echo $moduleclass_sfx; ?>" id="mod_flexicontent_carousel<?php echo $module->id ?>">
	
	<?php
	// Display FavList Information (if enabled)
	include(JPATH_SITE.'/modules/mod_flexicontent/tmpl_common/favlist.php');
	
	// Display Category Information (if enabled)
	include(JPATH_SITE.'/modules/mod_flexicontent/tmpl_common/category.php');
	
	$ord_titles = array(
		'popular'=>JText::_( 'FLEXI_MOST_POPULAR'),
		'commented'=>JText::_( 'FLEXI_MOST_COMMENTED'),
		'rated'=>JText::_( 'FLEXI_BEST_RATED' ),
		'added'=>	JText::_( 'FLEXI_RECENTLY_ADDED'),
		'addedrev'=>JText::_( 'FLEXI_RECENTLY_ADDED_REVERSE' ),
		'updated'=>JText::_( 'FLEXI_RECENTLY_UPDATED'),
		'alpha'=>	JText::_( 'FLEXI_ALPHABETICAL'),
		'alpharev'=>JText::_( 'FLEXI_ALPHABETICAL_REVERSE'),
		'id'=>JText::_( 'FLEXI_HIGHEST_ITEM_ID'),
		'rid'=>JText::_( 'FLEXI_LOWEST_ITEM_ID'),
		'catorder'=>JText::_( 'FLEXI_CAT_ORDER'),
		'random'=>JText::_( 'FLEXI_RANDOM' ),
		'field'=>JText::_( 'FLEXI_CUSTOM_FIELD' ),
		 0=>'Default' );
	
	$separator = "";
	$rowtoggler = 0;
	$item_columns_feat = $params->get('item_columns_feat', 1);
	$item_columns_std  = 1;
	$cols_class_feat = ($item_columns_feat <= 1)  ?  ''  :  'cols_'.$item_columns_feat;
	$cols_class_std  = '';
	
	foreach ($ordering as $ord) :
  	echo $separator;
	  if (isset($list[$ord]['featured']) || isset($list[$ord]['standard'])) {
  	  $separator = "<div class='ordering_seperator' ></div>";
    } else {
  	  $separator = "";
  	  continue;
  	}
	?>
	<div id="<?php echo 'order_'.( $ord ? $ord : 'default' ) . $module->id; ?>" class="mod_flexicontent">
		
		<?php	if ($ordering_addtitle && $ord) : ?>
		<div class='order_group_title'><?php echo $ord_titles[$ord]; ?></div>
		<?php endif; ?>
	
		
	<?php if (isset($list[$ord]['featured'])) : ?>
	
		<!-- BOF featured items -->
		<?php	$rowcount = 0; ?>
		
		<div class="mod_flexicontent_featured" id="mod_fcitems_box_featured<?php echo $module->id ?>">
			
			<?php $oe_class = $rowtoggler ? 'odd' : 'even'; ?>
			
			<?php foreach ($list[$ord]['featured'] as $item) : ?>
			<?php
				if ($rowcount%$item_columns_feat==0) {
					$oe_class = $oe_class=='odd' ? 'even' : 'odd';
					$rowtoggler = !$rowtoggler;
				}
				$rowcount++;
			?>
			
			<!-- BOF current item -->	
			<div class="mod_flexicontent_featured_wrapper<?php echo ' '.$oe_class .($item->is_active_item ? ' fcitem_active' : '') .($cols_class_feat ? ' '.$cols_class_feat : ''); ?>">
			<div class="mod_flexicontent_featured_wrapper_innerbox">
			
				<?php ob_start(); ?>
				<!-- BOF current item's image -->	
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
							<img style="<?php echo $img_force_dims_feat; ?>" src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" />
						</a>
					<?php else : ?>
						<img style="<?php echo $img_force_dims_feat; ?>" src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" />
					<?php endif; ?>
				</div>
				
				<?php endif; ?>
				<!-- BOF current item's image -->
				<?php $captured_image = ob_get_clean(); ?>
				
				<?php echo $content_layout!=2 ? $captured_image : '';?>
				
				<!-- BOF current item's content -->
				<?php if ($display_date_feat || $display_text_feat || $display_hits_feat || $display_voting_feat || $display_comments_feat || $mod_readmore_feat || ($use_fields_feat && @$item->fields && $fields_feat)) : ?>
				<div class="content_featured">
					
					<!-- BOF current item's title -->	
					<?php if ($display_title_feat) : ?>
					<div class="fc_block" >
						<div class="fc_inline_block fcitem_title">
							<?php if ($link_title_feat) : ?>
							<a href="<?php echo $item->link; ?>"><?php echo $item->title; ?></a>
							<?php else : ?>	
							<?php echo $item->title; ?>
							<?php endif; ?>
						</div>
					</div>
					<?php endif; ?>
					<!-- EOF current item's title -->	
				
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
				
				<?php echo $content_layout==2 ? $captured_image : '';?>
				
			</div>  <!-- EOF wrapper_innerbox -->
			</div>  <!-- EOF wrapper -->
			<!-- EOF current item -->
			<?php echo !($rowcount%$item_columns_feat) ? '<div class="modclear"></div>' : ''; ?>
			<?php endforeach; ?>
			
		</div>
		
		<!-- EOF featured items -->
		
	<?php endif; ?>
		
	<div class="modclear"></div>
	
		
	<?php if (isset($list[$ord]['standard'])) : ?>
	
		<!-- BOF standard items -->
		<?php	$rowcount = 0; ?>
		
		<div id="mod_fc_carousel_mask<?php echo $module->id ?>_loading" class="mod_fc_carousel_mask_loading">
			... <?php echo  JText::_('FLEXI_MOD_CAROUSEL_LOADING_IMAGES'); ?> <img alt="" src="<?php echo JURI::root(); ?>components/com_flexicontent/assets/images/ajax-loader.gif" align="middle" />
		</div>
		
<div class="mod_fc_carousel" id="mod_fc_carousel_container_<?php echo $module->id ?>" >
	
	<?php if ($show_controls==1) : ?>
	<span id="previous<?php echo $_icontrols_method; ?>_fcmod_<?php echo $module->id; ?>"  class="mod_fc_nav fc_previous fc_<?php echo $mode; ?>" ></span> 
	<?php endif; ?>
	
	<div id="mod_fc_carousel_mask<?php echo $module->id ?>" class="mod_fc_carousel_mask <?php echo $show_controls==1 ? 'fc_has_nav fc_'.$mode : ''; ?>">
		
		<div class="mod_flexicontent_standard" id="mod_fcitems_box_standard<?php echo $module->id ?>">
			
			<?php $oe_class = $rowtoggler ? 'odd' : 'even'; $n=-1; ?>
			
			<?php foreach ($list[$ord]['standard'] as $item) : ?>
			<?php
				if ($rowcount%$item_columns_std==0) {
					$oe_class = $oe_class=='odd' ? 'even' : 'odd';
					$rowtoggler = !$rowtoggler;
				}
				$rowcount++;
				$n++;
			?>
			
			<!-- BOF current item -->	
			<div class="mod_flexicontent_standard_wrapper<?php echo ' '.$oe_class .($item->is_active_item ? ' fcitem_active' : ''); ?>"
				onmouseover="mod_fc_carousel<?php echo $module->id; ?>.stop(); mod_fc_carousel<?php echo $module->id; ?>.autoPlay=false;"
				onmouseout="if (mod_fc_carousel<?php echo $module->id ?>_autoPlay==1) mod_fc_carousel<?php echo $module->id; ?>.play(<?php echo $interval; ?>,'next',true);	else if (mod_fc_carousel<?php echo $module->id ?>_autoPlay==-1) mod_fc_carousel<?php echo $module->id; ?>.play(<?php echo $interval; ?>,'previous',true);"
			>
			<div class="mod_flexicontent_standard_wrapper_innerbox">

				<?php ob_start(); ?>
				<!-- BOF current item's image -->	
				<?php if ($mod_use_image && $item->image_rendered) : ?>
				<div class="image_standard">
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
							<img style="<?php echo $img_force_dims; ?>" src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" />
						</a>
					<?php else : ?>
						<img style="<?php echo $img_force_dims; ?>" src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" />
					<?php endif; ?>
				</div>
				
				<?php endif; ?>
				<!-- BOF current item's image -->	
				<?php $captured_image = ob_get_clean(); ?>
				
				<?php echo $content_layout!=2 ? $captured_image : '';?>
				
				<!-- BOF current item's content -->
				<?php if ($display_date || $display_text || $display_hits || $display_voting || $display_comments || $mod_readmore || ($use_fields && @$item->fields && $fields)) : ?>
				<div class="content_standard <?php echo $content_container_class;?>">
					
					<?php if ($display_title || $item_handle_title==2) : ?>
					<div class="fc_block" <?php echo !$display_title ? 'style="display:none!important;"' : ''; ?> >
						<div class="fc_inline_block fcitem_title">
							<?php if ($link_title) : ?>
								<a href="<?php echo $item->link; ?>"><?php echo $item->title; ?></a>
							<?php else : ?>	
								<?php echo $item->title; ?>
							<?php endif; ?>
						</div>
					</div>
					<?php endif; ?>
					
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
					
					<?php if ( ($display_text && $item->text) || $item_handle_text==2 ) : ?>
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
					
				</div> <!-- EOF current item's content -->
				
				<?php echo $content_layout==2 ? $captured_image : '';?>
				
				<?php endif; ?>
				
			</div>  <!-- EOF wrapper_innerbox -->
			</div>  <!-- EOF wrapper -->
			<!-- EOF current item -->
			<?php endforeach; ?>
			
		</div>
		<!-- EOF standard items -->
		
	</div> <!-- mod_fc_carousel_mask{module_id} -->

	<?php if ($show_controls==1) : ?>
	<span id="next<?php echo $_icontrols_method; ?>_fcmod_<?php echo $module->id; ?>"  class="mod_fc_nav fc_next fc_<?php echo $mode; ?>" ></span>
	<?php endif; ?>

</div> <!-- mod_fc_carousel -->
		
		<?php if ($show_page_handles) : ?>
			<div class="mod_fc_pages_outer">
				<div id="mod_fc_page_handles<?php echo $module->id; ?>" class="mod_fc_page_handles"
						onmouseover="mod_fc_carousel<?php echo $module->id; ?>.stop(); mod_fc_carousel<?php echo $module->id; ?>.autoPlay=false;"
						onmouseout="if (mod_fc_carousel<?php echo $module->id ?>_autoPlay==1) mod_fc_carousel<?php echo $module->id; ?>.play(<?php echo $interval; ?>,'next',true);	else if (mod_fc_carousel<?php echo $module->id ?>_autoPlay==-1) mod_fc_carousel<?php echo $module->id; ?>.play(<?php echo $interval; ?>,'previous',true);"
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
					onmouseover="mod_fc_carousel<?php echo $module->id; ?>.stop(); mod_fc_carousel<?php echo $module->id; ?>.autoPlay=false;"
					onmouseout="if (mod_fc_carousel<?php echo $module->id ?>_autoPlay==1) mod_fc_carousel<?php echo $module->id; ?>.play(<?php echo $interval; ?>,'next',true);	else if (mod_fc_carousel<?php echo $module->id ?>_autoPlay==-1) mod_fc_carousel<?php echo $module->id; ?>.play(<?php echo $interval; ?>,'previous',true);"
				>
					<?php if ($dcontrols_auto) : ?>
						<?php if ($dcontrols_labels) : ?>
							<span id="autoplay_controls_label_fcmod_<?php echo $module->id; ?>" class="mod_fc_carousel_controls_label"><?php echo JText::_('FLEXI_MOD_CAROUSEL_AUTOPLAY'); ?></span>
						<?php endif; ?>
						<span id="stop_fcmod_<?php echo $module->id; ?>" onclick="mod_fc_carousel<?php echo $module->id ?>_autoPlay=0;" class="mod_fc_carousel_btn fc_stop" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_STOP'); ?>"></span>
						<span id="backward_fcmod_<?php echo $module->id; ?>" onclick="mod_fc_carousel<?php echo $module->id ?>_autoPlay=-1;" class="mod_fc_carousel_btn fc_backward" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_BACKWARD'); ?>"></span>
						<span id="forward_fcmod_<?php echo $module->id; ?>" onclick="mod_fc_carousel<?php echo $module->id ?>_autoPlay=1;" class="mod_fc_carousel_btn fc_forward" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_FORWARD'); ?>"></span>
					<?php endif; ?>
					
					<?php if ($dcontrols_pages) : ?>
						<?php if ($dcontrols_labels) : ?>
							<span id="pages_controls_label_fcmod_<?php echo $module->id; ?>" class="mod_fc_carousel_controls_label"><?php echo JText::_('FLEXI_MOD_CAROUSEL_PAGES'); ?></span>
						<?php endif; ?>
						<span id="previous_page_fcmod_<?php echo $module->id; ?>" class="mod_fc_carousel_btn fc_previous_page" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_PREVIOUS_PAGE'); ?>"></span>
						<span id="next_page_fcmod_<?php echo $module->id; ?>" class="mod_fc_carousel_btn fc_next_page" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_NEXT_PAGE'); ?>"></span>
					<?php endif; ?>
					
					<?php if ($dcontrols_items) : ?>
						<?php if ($dcontrols_labels) : ?>
							<span id="items_controls_label_fcmod_<?php echo $module->id; ?>" class="mod_fc_carousel_controls_label"><?php echo JText::_('FLEXI_MOD_CAROUSEL_ITEMS'); ?></span>
						<?php endif; ?>
						<span id="previous_fcmod_<?php echo $module->id; ?>" class="mod_fc_carousel_btn fc_previous" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_PREVIOUS'); ?>"></span>
						<span id="next_fcmod_<?php echo $module->id; ?>" class="mod_fc_carousel_btn fc_next" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_NEXT'); ?>"></span>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
		
		<?php if ($show_item_handles) : ?>
		
			<!-- fc_add_scroller_horizontal -->
			<div class="mod_fc_handles_outer">
				<div id="mod_fc_item_handles<?php echo $module->id; ?>" class="mod_fc_item_handles fc_add_scroller_horizontal"
						onmouseover="mod_fc_carousel<?php echo $module->id; ?>.stop(); mod_fc_carousel<?php echo $module->id; ?>.autoPlay=false;"
						onmouseout="if (mod_fc_carousel<?php echo $module->id ?>_autoPlay==1) mod_fc_carousel<?php echo $module->id; ?>.play(<?php echo $interval; ?>,'next',true);	else if (mod_fc_carousel<?php echo $module->id ?>_autoPlay==-1) mod_fc_carousel<?php echo $module->id; ?>.play(<?php echo $interval; ?>,'previous',true);"
				>
				
				<?php $img_path = JURI::base(true) .'/'; ?>
				<?php foreach ($list[$ord]['standard'] as $item) : ?>
					<?php
						$tip_html = '';
						if ( $item_handle_title==1 || $item_handle_text==1) {
							$tip_html = flexicontent_html::getToolTip( ($item_handle_title==1 ? $item->title : null), ($item_handle_text==1 ? $item->text : null), 0, 1);
						}
						$classes = 'mod_fc_item_handle' . ($tip_html ? $tooltip_class : '');
					?>
						<span class="<?php echo $classes; ?>" title="<?php echo $tip_html; ?>" >
							<img alt="" src="<?php echo @ $item->image ? $item->image : $img_path.$mod_default_img_path; ?>" style="<?php echo 'width:'.$item_handle_width.'px; height:'.$item_handle_height.'px'; ?>" />
						</span>
					<?php endforeach; ?>
				
				</div>
			</div>
		<?php endif; ?>
	
		<?php if ($show_curritem_info) : ?>
		
			<div id="mod_fc_activeitem_info<?php echo $module->id; ?>" class="mod_fc_activeitem_info" >
				<?php /*echo JText::_( 'FLEXI_MOD_CAROUSEL_DISPLAYING').': ';*/ ?>
				<?php if ($item_handle_title==2) : ?>
					<span id="mod_fc_info_title<?php echo $module->id; ?>" class="mod_fc_activeitem_info_title"></span>
				<?php endif; ?>
				
				<?php if ($item_handle_text==2) : ?>
					<span id="mod_fc_info_text<?php echo $module->id; ?>" class="mod_fc_activeitem_info_text"></span>
				<?php endif; ?>
			</div>
			
		<?php endif; ?>
		
	<?php endif; ?>
		
	<div class="modclear"></div>
	
	</div>
	<?php endforeach; ?>
	
	<?php
	// Display readon of module
	include(JPATH_SITE.'/modules/mod_flexicontent/tmpl_common/readon.php');
	?>
	
</div>

<?php
flexicontent_html::loadFramework('fcxSlide');
$document = JFactory::getDocument();

$js ='
	var mod_fc_carousel'.$module->id.'_ns_fxOptions='.$_fcx_fxOptions.';
	var mod_fc_carousel'.$module->id.'_autoPlay='.$autoplay.';
	var mod_fc_carousel'.$module->id.';
	
	jQuery(document).ready(function() {
	 jQuery("#mod_fc_carousel_container_'.$module->id.'").imagesLoaded(function(){
	
		mod_fc_carousel'.$module->id.' = new fcxSlide({
		
			mode: "'.$mode.'",
			transition: "'.$transition.'",
			fxOptions: mod_fc_carousel'.$module->id.'_ns_fxOptions,
			transition_visible_duration: '.$transition_visible_duration.',
			
			items: jQuery("#mod_fcitems_box_standard'.$module->id.'").find("div.mod_flexicontent_standard_wrapper"),
			items_inner: jQuery("#mod_fcitems_box_standard'.$module->id.'").find("div.mod_flexicontent_standard_wrapper_innerbox"),
			items_box: jQuery("#mod_fcitems_box_standard'.$module->id.'"),
			items_mask: jQuery("#mod_fc_carousel_mask'.$module->id.'"),
			items_per_page: '.$_fcx_items_per_page.',
			item_size: '.$_fcx_item_size.',
			responsive: '.$_fcx_responsive.',
			
			'.( !$show_page_handles ? '' : '
			page_handles: jQuery("#mod_fc_page_handles'.$module->id.'").find("span.mod_fc_page_handle"),
			page_handle_event: "'.$page_handle_event.'",
			').'
			
			'.( !$show_item_handles ? '' : '
			item_handles_box: jQuery("#mod_fc_item_handles'.$module->id.'"),
			item_handles: jQuery("#mod_fc_item_handles'.$module->id.'").find("span.mod_fc_item_handle"),
			item_handle_event: "'.$item_handle_event.'",
			item_handle_duration: '.$item_handle_duration.',
			').'
			
			'.( !$show_controls ? '' :
				'action_handles: {
					'.( ($show_controls==2 && $dcontrols_auto) ? 'stop: jQuery("#stop_fcmod_'.$module->id.'"),' : '').'
					'.( ($show_controls==2 && $dcontrols_auto) ? 'playback:jQuery("#backward_fcmod_'.$module->id.'"),' : '').'
					'.( ($show_controls==2 && $dcontrols_auto) ? 'play: jQuery("#forward_fcmod_'.$module->id.'"),' : '').'
					'.( (($show_controls==1 && $icontrols_method=='page') || ($show_controls==2 && $dcontrols_pages)) ? 'previous_page:jQuery("#previous_page_fcmod_'.$module->id.'"),' : '').'
					'.( (($show_controls==1 && $icontrols_method=='page') || ($show_controls==2 && $dcontrols_pages)) ? 'next_page: jQuery("#next_page_fcmod_'.$module->id.'"),' : '').'
					'.( (($show_controls==1 && $icontrols_method=='item') || ($show_controls==2 && $dcontrols_items)) ? 'previous: jQuery("#previous_fcmod_'.$module->id.'"),' : '').'
					'.( (($show_controls==1 && $icontrols_method=='item') || ($show_controls==2 && $dcontrols_items)) ? 'next: jQuery("#next_fcmod_'.$module->id.'")' : '').'
				},
				action_handle_event: "click",
			').'
			
			edgeWrap: '.$_fcx_edgeWrap.',
			touch_walk: '.$_fcx_touch_walk.',
			mouse_walk: '.$_fcx_mouse_walk.',
			autoPlay: '.$_fcx_autoPlay.',
			playInterval: '.$interval.',
			playMethod: "'.$method.'",
			
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
					jQuery("#mod_fc_info_title'.$module->id.'").html( jQuery(currentItem).find(".fcitem_title").html() );
					jQuery("#mod_fc_info_text'.$module->id.'").html( jQuery(currentItem).find(".fcitem_text").html() );
				').'
			}
		});
		
		jQuery("#mod_fc_carousel_mask'.$module->id.'_loading").css("display", "none");
		jQuery("#mod_fc_carousel_mask'.$module->id.'").css("visibility", "visible");
		
		jQuery("#next_fcmod_'.$module->id.'").css("visibility", "visible");
		jQuery("#previous_fcmod_'.$module->id.'").css("visibility", "visible");
		
		jQuery("#next_page_fcmod_'.$module->id.'").css("visibility", "visible");
		jQuery("#previous_page_fcmod_'.$module->id.'").css("visibility", "visible");
		
		'.
		// Alternative but it includes padding
		// var maxHeight = jQuery("#mod_fcitems_box_standard'.$module->id.'")[0].clientHeight;
		
		// Alternative to use computed style, requires ie9+
		//var maxHeight_withpx = getComputedStyle(jQuery("#mod_fcitems_box_standard'.$module->id.'")[0], null).getPropertyValue("height")
		//mod_fc_carousel'.$module->id.'.items.each(function() {
		//	this.style.height = maxHeight_withpx;
		//});
		'
		fc_recalculateWindow();
	 });
	});
	';

$document->addScriptDeclaration($js);


// ***********************************************************
// Module specific styling (we use names containing module ID)
// ***********************************************************

$css = ''.

/* Featured items are not part of the carousel this is their inner CONTAINER, add some styling */'
#mod_fcitems_box_featured'.$module->id.' div.mod_flexicontent_featured_wrapper_innerbox {
}'.

/* The MASK that contains the CAROUSEL (mask clips it) */'
#mod_fc_carousel_mask'.$module->id.' {
}'.

/* CAROUSEL container (external) is the CONTAINER of standard items */'
#mod_fcitems_box_standard'.$module->id.' {}'.

/* CAROUSEL container (internal) is the inner CONTAINER of standard items */'
#mod_fcitems_box_standard'.$module->id.' div.mod_flexicontent_standard_wrapper {
}
#mod_fcitems_box_standard'.$module->id.' div.mod_flexicontent_standard_wrapper_innerbox {
	'.($inner_inline_css ? '
	padding: '.$padding_top_bottom.'px '.$padding_left_right.'px !important;
	border-width: '.$border_width.'px!important;
	margin: '.$margin_top_bottom.'px '.$margin_left_right.'px !important;
	' : '').'
	'.
	// CSS trick to force same height for all items, but crops border
	//margin-bottom: -99999px; padding-bottom: 99999px;
	'
}'.

/* Active item information */'
#mod_fc_activeitem_info'.$module->id.' {}'.

/* Item button handles (instantly display respective item)*/'
#mod_fc_item_handles'.$module->id.' {}'.

/* CAROUSEL item/page handles clickable */'
#mod_fc_item_handles'.$module->id.' span.mod_fc_item_handle:hover {
	'.($item_handle_event=='click' ? 'cursor:pointer;' : '').'
}
#mod_fc_page_handles'.$module->id.' span.mod_fc_page_handle:hover {
	'.($page_handle_event=='click' ? 'cursor:pointer;' : '').'
}'
;

$document->addStyleDeclaration($css);
flexicontent_html::loadFramework('mCSB');
flexicontent_html::loadFramework('imagesLoaded');
