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

$mod_width_feat 	= (int)$params->get('mod_width', 110);
$mod_height_feat 	= (int)$params->get('mod_height', 110);
$mod_width 				= (int)$params->get('mod_width', 80);
$mod_height 			= (int)$params->get('mod_height', 80);

$force_width_feat="width='$mod_width_feat'";
$force_height_feat="height='$mod_height_feat'";
$force_width="width='$mod_width'";
$force_height="height='$mod_height'";

$hide_label_onempty_feat = (int)$params->get('hide_label_onempty_feat', 0);
$hide_label_onempty      = (int)$params->get('hide_label_onempty', 0);


// Carousel direction and Common Dimensions
$mode = $params->get('carousel_mode', 'horizontal');
$padding_top_bottom = (int)$params->get('carousel_padding_top_bottom', 8);
$padding_left_right = (int)$params->get('carousel_padding_left_right', 12);
$border_width = (int)$params->get('carousel_border_width', 1);

// Direction specific Dimensions : HORIZONTAL
$hdir_item_width   = (int)$params->get('carousel_hdir_item_width', 250);
$hdir_margin_right = (int)$params->get('carousel_hdir_margin_right', 12);

// Direction specific Dimensions : VERTICAL
$vdir_items = (int)$params->get('carousel_vdir_items', 2);
$vdir_margin_bottom = (int)$params->get('carousel_vdir_margin_bottom', 6);

// Thumbnail Buttons (= carousel handles)
$show_handles  = (int)$params->get('carousel_show_handles', 1);
$handle_width  = (int)$params->get('carousel_handle_width', 24);
$handle_height = (int)$params->get('carousel_handle_height', 24);
$handle_event  = $params->get('carousel_handle_event', 'mouseover');

// Autoplay, autoplay interval, and affect duration
$autoplay = $params->get('carousel_autoplay', 1);
$effect   = $params->get('carousel_effect', 'quart').':out';
$duration = (int)$params->get('carousel_duration', 1000);
$interval = (int)$params->get('carousel_interval', 5000);

// Miscellaneous Optionally displayed
$show_controls      = (int)$params->get('carousel_show_controls', 0);
$show_curritem_info = (int)$params->get('carousel_show_curritem_info', 0);


// Please examine CSS of 'mod_flexicontent_standard_wrapper' below to SUM up (a) horizontal Padding (b) horizontal Margin (c) vertical   Border
$extra_width  = 2*$padding_left_right + $hdir_margin_right  + 2*$border_width;

// Please examine CSS of 'mod_flexicontent_standard_wrapper' below to SUM up (a) vertical   Padding (b) vertical   Margin (c) horizontal Border
$extra_height = 2*$padding_top_bottom + $vdir_margin_bottom + 2*$border_width;

// Carousel Object variables
$_ns_mode         = $mode;
$_ns_handle_event = $handle_event;
$_ns_autoPlay     = $autoplay ? "true" : "false";
$_ns_interval     = $interval;
$_ns_size         = $mode=="horizontal" ? $hdir_item_width + $extra_width : 240;  // 240 is just a default it will be recalulated after page load ends
$_ns_fxOptions    = '{ duration:'.$duration.', transition: "'.$effect.'", link: "cancel" }';
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
	$item_columns = $params->get('item_columns', 1);
	$twocols = $item_columns == 2;
	
	foreach ($ordering as $ord) :
  	echo $separator;
	  if (isset($list[$ord]['featured']) || isset($list[$ord]['standard'])) {
  	  $separator = "<div class='ordering_seperator' ></div>";
    } else {
  	  $separator = "";
  	  continue;
  	}
	?>
	<div id="<?php echo 'order_'.( $ord ? $ord : 'default' ) . $module->id; ?>" class="mod_flexicontent <?php echo ($twocols) ? 'twocol' : ''; ?>">
		
		<?php	if ($ordering_addtitle && $ord) : ?>
		<div class='order_group_title'><?php echo $ord_titles[$ord]; ?></div>
		<?php endif; ?>
		
	<?php if (isset($list[$ord]['featured'])) : ?>
		<!-- BOF featured items -->
		<div class="mod_flexicontent_featured" id="mod_fcitems_box_featured<?php echo $module->id ?>">
			
			<?php foreach ($list[$ord]['featured'] as $item) : ?>
			<?php $rowtoggler = !$rowtoggler; ?>
			
			<!-- BOF current item -->	
			<div class="mod_flexicontent_featured_wrapper <?php echo ($rowtoggler) ? 'odd' : 'even'; ?>">
				
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
						<a href="<?php echo $item->link; ?>"><img <?php echo $force_height_feat." ".$force_width_feat; ?> src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" /></a>
					<?php else : ?>
						<img <?php echo $force_height_feat." ".$force_width_feat; ?> src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" />
					<?php endif; ?>
				</div>
				
				<?php endif; ?>
				<!-- BOF current item's image -->
				
				<!-- BOF current item's content -->
				<?php if ($display_date_feat || $display_text_feat || $display_hits_feat || $display_voting_feat || $display_comments_feat || $mod_readmore_feat || ($use_fields_feat && @$item->fields && $fields_feat)) : ?>
				<div class="content_featured">
					
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
					<div class="fcitem_text">
						<?php echo $item->text; ?>
					</div>
					<?php endif; ?>
					
					<?php if ($use_fields_feat && @$item->fields && $fields_feat) : ?>
					<div class="fcitem_fields">
						
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
				
			</div>
			<!-- EOF current item -->
			<?php endforeach; ?>
			
		</div>
		<!-- EOF featured items -->
	<?php endif; ?>
		
	<div class="modclear"></div>
	
	<?php if (isset($list[$ord]['standard'])) : ?>
		<!-- BOF standard items -->
		<?php	$rowcount = 0; ?>
		
		<div id="mod_fc_carousel_mask<?php echo $module->id ?>_loading" class="mod_fc_carousel_mask_loading">
			... loading <img src="<?php echo JURI::root(); ?>components/com_flexicontent/assets/images/ajax-loader.gif" align="center">
		</div>
		
<div class="mod_fc_carousel">
	
	<?php if ($show_controls==1) : ?>
	<span id="previous_fcmod_<?php echo $module->id; ?>"  class="mod_fc_nav fc_prev fc_<?php echo $mode; ?>" ></span> 
	<?php endif; ?>
	
	<div id="mod_fc_carousel_mask<?php echo $module->id ?>" class="mod_fc_carousel_mask <?php echo $show_controls==1 ? 'fc_has_nav fc_'.$mode : ''; ?>">
		
		<div class="mod_flexicontent_standard" id="mod_fcitems_box_standard<?php echo $module->id ?>">
			
			<?php $oe_class = $rowtoggler ? 'odd' : 'even'; ?>
			<?php foreach ($list[$ord]['standard'] as $item) : ?>
			<?php
				/*if ($rowcount%$item_columns==0) {
					$oe_class = $oe_class=='odd' ? 'even' : 'odd';
				}*/
				$rowcount++;
			?>
			
			<!-- BOF current item -->	
			<div class="mod_flexicontent_standard_wrapper <?php echo $oe_class; ?>"
				onmouseover="mod_fc_carousel<?php echo $module->id; ?>.stop(); mod_fc_carousel<?php echo $module->id; ?>.autoPlay=false;"
				onmouseout="if (mod_fc_carousel<?php echo $module->id ?>_autoPlay==1) mod_fc_carousel<?php echo $module->id; ?>.play(<?php echo $_ns_interval; ?>,'next',true);	else if (mod_fc_carousel<?php echo $module->id ?>_autoPlay==-1) mod_fc_carousel<?php echo $module->id; ?>.play(<?php echo $_ns_interval; ?>,'previous',true);"
			>

				<?php if ($display_title) : ?>
				<div class="fc_block" >
					<div class="fc_inline_block fcitem_title">
						<?php if ($link_title) : ?>
						<a href="<?php echo $item->link; ?>"><?php echo $item->title; ?></a>
						<?php else : ?>	
						<?php echo $item->title; ?>
						<?php endif; ?>
					</div>
				</div>
				<?php endif; ?>
				
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
				
				<div class="image_standard">
					<?php if ($mod_link_image) : ?>
						<a href="<?php echo $item->link; ?>"><img <?php echo $force_height." ".$force_width; ?> src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" /></a>
					<?php else : ?>
						<img <?php echo $force_height." ".$force_width; ?> src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" />
					<?php endif; ?>
				</div>
				
				<?php endif; ?>
				<!-- BOF current item's image -->	
				
				<!-- BOF current item's content -->
				<?php if ($display_date || $display_text || $display_hits || $display_voting || $display_comments || $mod_readmore || ($use_fields && @$item->fields && $fields)) : ?>
				<div class="content_standard">
					
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
					<div class="fcitem_text">
						<?php echo $item->text; ?>
					</div>
					<?php endif; ?>
					
					<?php if ($use_fields && @$item->fields && $fields) : ?>
					<div class="fcitem_fields">
						
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
				
			</div>
			<!-- EOF current item -->
			<?php endforeach; ?>
			
		</div>
		<!-- EOF standard items -->
		
	</div> <!-- mod_fc_carousel_mask{module_id} -->

	<?php if ($show_controls==1) : ?>
	<span id="next_fcmod_<?php echo $module->id; ?>"  class="mod_fc_nav fc_next fc_<?php echo $mode; ?>" ></span>
	<?php endif; ?>

</div> <!-- mod_fc_carousel -->
		
		<?php if ($show_controls==2) : ?>
			<div class="mod_fc_carousel_buttons">
				<span id="stop_fcmod_<?php echo $module->id; ?>" onclick="mod_fc_carousel<?php echo $module->id ?>_autoPlay=0;" class="mod_fc_carousel_btn fc_stop" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_STOP'); ?>"></span>
				<span id="backward_fcmod_<?php echo $module->id; ?>" onclick="mod_fc_carousel<?php echo $module->id ?>_autoPlay=-1;" class="mod_fc_carousel_btn fc_backward" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_BACKWARD'); ?>"></span>
				<span id="forward_fcmod_<?php echo $module->id; ?>" onclick="mod_fc_carousel<?php echo $module->id ?>_autoPlay=1;" class="mod_fc_carousel_btn fc_forward" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_BACKWARD'); ?>"></span>
				<span id="previous_fcmod_<?php echo $module->id; ?>" class="mod_fc_carousel_btn fc_previous" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_PREVIOUS'); ?>"></span>
				<span id="next_fcmod_<?php echo $module->id; ?>" class="mod_fc_carousel_btn fc_next" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_NEXT'); ?>"></span>
			</div>
		<?php endif; ?>
		
		<?php if ($show_curritem_info) : ?>
			<h4 id="mod_fc_activeitem_info<?php echo $module->id; ?>" class="mod_fc_activeitem_info" >Show: <span id="mod_fc_info<?php echo $module->id; ?>"></span></h4>
		<?php endif; ?>
		
		<?php if ($show_handles) : ?>
			<div id="mod_fc_handles<?php echo $module->id; ?>" class="mod_fc_handles"
					onmouseover="mod_fc_carousel<?php echo $module->id; ?>.stop(); mod_fc_carousel<?php echo $module->id; ?>.autoPlay=false;"
					onmouseout="if (mod_fc_carousel<?php echo $module->id ?>_autoPlay==1) mod_fc_carousel<?php echo $module->id; ?>.play(<?php echo $_ns_interval; ?>,'next',true);	else if (mod_fc_carousel<?php echo $module->id ?>_autoPlay==-1) mod_fc_carousel<?php echo $module->id; ?>.play(<?php echo $_ns_interval; ?>,'previous',true);"
			>
				
				<?php $count=1; $img_path = JURI::base(true) .'/'; ?>
				<?php foreach ($list[$ord]['standard'] as $item) : ?>
				<span>
					<img width="<?php echo $handle_width; ?>" height="<?php echo $handle_height; ?>" src="<?php echo @ $item->image ? $item->image : $img_path.'components/com_flexicontent/assets/images/image.png'/*.($count++).".".$item->title*/; ?>" />
				</span>
				<?php endforeach; ?>
				
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
flexicontent_html::loadFramework('noobSlide');
$document = JFactory::getDocument();

$js ='
	var mod_fc_carousel'.$module->id.'_ns_fxOptions='.$_ns_fxOptions.';
	var mod_fc_carousel'.$module->id.'_autoPlay='.$autoplay.';
	var mod_fc_carousel'.$module->id.';
	window.addEvent("domready",function(){
		// Walk to item
		mod_fc_carousel'.$module->id.' = new noobSlide({
			box: $("mod_fcitems_box_standard'.$module->id.'"),
			items: (MooTools.version>="1.2.4" ?
				$("mod_fcitems_box_standard'.$module->id.'").getElements("div.mod_flexicontent_standard_wrapper") : 
				$ES("div.mod_flexicontent_standard_wrapper","mod_fcitems_box_standard'.$module->id.'") ),
			'.( !$show_handles ? '' : '
			handles: (MooTools.version>="1.2.4" ?
				$("mod_fc_handles'.$module->id.'").getElements("span") : $ES("span","mod_fc_handles'.$module->id.'")),
			').'
			handle_event: "'.$_ns_handle_event.'",
			size: '.$_ns_size.',
			mode: "'.$_ns_mode.'",
			interval: '.$_ns_interval.',
			autoPlay: '.$_ns_autoPlay.',
			fxOptions: mod_fc_carousel'.$module->id.'_ns_fxOptions,
			onWalk: function(currentItem,currentHandle){
				'.( !$show_curritem_info ? '' : '
				(MooTools.version>="1.2.4" ?
					$("mod_fc_info'.$module->id.'").set("html",currentItem.getElement(".fcitem_title").get("html")) :
					$("mod_fc_info'.$module->id.'").setHTML(currentItem.getElement(".fcitem_title").innerHTML) );
				').( !$show_handles ? '' : '
				this.handles.removeClass("active");
				currentHandle.addClass("active");
				').'
			},
			'.( !$show_controls ? '' :
				(FLEXI_J16GE ? 'addButtons' : 'buttons').': {
					'.($show_controls==2 ? 'stop: $("stop_fcmod_'.$module->id.'"),' : '').'
					'.($show_controls==2 ? 'playback:$("backward_fcmod_'.$module->id.'"),' : '').'
					'.($show_controls==2 ? 'play: $("forward_fcmod_'.$module->id.'"),' : '').'
					previous: $("previous_fcmod_'.$module->id.'"),
					next: $("next_fcmod_'.$module->id.'")
				}
			').'
		});
		
		$$("#mod_fc_carousel_mask'.$module->id.'_loading").setStyle("display", "none");
		$$("#mod_fc_carousel_mask'.$module->id.'").setStyle("display", "block");
		
		/*if (mod_fc_carousel'.$module->id.'.mode=="horizontal") {*/
			// Set height of floating elements
			var maxHeight = 0;
			mod_fc_carousel'.$module->id.'.items.each(function(element) {
				maxHeight = Math.max(maxHeight, element.clientHeight - '.(2*$padding_top_bottom).');
			});
			mod_fc_carousel'.$module->id.'.items.each(function(element) {
				element.style.minHeight = maxHeight + "px";
			});
		
		'.($mode!="vertical" ? '':'
		mod_fcitems_box_standard'.$module->id.'.style.height = ('.$vdir_items.' * (maxHeight +'.$extra_height.')) + "px";
		mod_fc_carousel'.$module->id.'.size = maxHeight +'.$extra_height.';
		').'
		
		/*}*/
		'./*
		// Alternative but it includes padding
		// var maxHeight = $("mod_fcitems_box_standard'.$module->id.'").clientHeight;
		
		// Alternative to use computed style, requires ie9+
		var maxHeight_withpx = getComputedStyle($("mod_fcitems_box_standard'.$module->id.'"), null).getPropertyValue("height")
		mod_fc_carousel'.$module->id.'.items.each(function(element) {
			element.style.height = maxHeight_withpx;
		});
		*/'
	});
	';

$document->addScriptDeclaration($js);


// ***********************************************************
// Module specific styling (we use names containing module ID)
// ***********************************************************

$css = ''.

/* Featured items are not part of the carousel this is their inner CONTAINER, add some styling */'
#mod_fcitems_box_featured'.$module->id.' div.mod_flexicontent_featured_wrapper {
	border-color: #d7d7d7 #a0a0a0 #a0a0a0 #d7d7d7;
	border-width: 1px;
	border-style: solid;
}'.

/* The MASK that contains the CAROUSEL (mask clips it) */'
#mod_fc_carousel_mask'.$module->id.' {
	display: none;  './* Hide CAROUSEL till page finishes loading */'
}'.

/* CAROUSEL container (external) is the CONTAINER of standard items */'
#mod_fcitems_box_standard'.$module->id.' {}'.

/* CAROUSEL container (internal) is the inner CONTAINER of standard items */'
#mod_fcitems_box_standard'.$module->id.' div.mod_flexicontent_standard_wrapper {
	border-width: 1px !important;
	padding: '.$padding_top_bottom.'px '.$padding_left_right.'px !important;
	margin: '.($mode=="vertical" ? '0px 0px '.$vdir_margin_bottom.'px 0px' : '0px '.$hdir_margin_right.'px 0px 0px').' !important;
	width: '.($mode=="vertical" ? "auto" : $hdir_item_width.'px').' !important;
	float: '.($mode=="vertical" ? "none" : 'left').' !important;
	overflow: '.($mode=="vertical" ? "hidden" : 'auto').' !important;
	'.
	// CSS trick to force same height for all items, but crops border
	//margin-bottom: -99999px; padding-bottom: 99999px;
	'
}'.

/* Active item information */'
#mod_fc_activeitem_info'.$module->id.' {}'.

/* Item button handles (instantly display respective item)*/'
#mod_fc_handles'.$module->id.' {}

';

$document->addStyleDeclaration($css);

?>