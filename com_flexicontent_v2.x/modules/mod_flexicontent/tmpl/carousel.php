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
$walk_delay = 5000;
?>

<div class="news mod_flexicontent_wrapper mod_flexicontent_wrap<?php echo $moduleclass_sfx; ?>" id="news<?php echo $module->id ?>">
	
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
	<div id="<?php echo ( ($ord) ? $ord : 'default' ) . $module->id; ?>" class="mod_flexicontent <?php echo ($twocols) ? 'twocol' : ''; ?>">
		
		<?php	if ($ordering_addtitle && $ord) : ?>
		<div class='order_group_title'><?php echo $ord_titles[$ord]; ?></div>
		<?php endif; ?>
		
		<?php if (isset($list[$ord]['featured'])) : ?>
		<!-- BOF featured items -->
	<div class="mod_fc_carousel_mask<?php echo $module->id ?>">
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
	</div> <!-- class mod_fc_carousel_mask{module_id} -->
		
		<?php endif; ?>
		
		
		<?php if (isset($list[$ord]['standard'])) : ?>
		<!-- BOF standard items -->
		<?php	$rowcount = 0; ?>
		
	<div class="mod_fc_carousel_mask<?php echo $module->id ?>">
		<div class="mod_flexicontent_standard" id="mod_fcitems_box_standard<?php echo $module->id ?>">
			
			<?php $oe_class = $rowtoggler ? 'odd' : 'even'; ?>
			<?php foreach ($list[$ord]['standard'] as $item) : ?>
			<?php
				if ($rowcount%$item_columns==0) {
					$oe_class = $oe_class=='odd' ? 'even' : 'odd';
				}
				$rowcount++;
			?>
			
			<!-- BOF current item -->	
			<div class="mod_flexicontent_standard_wrapper <?php echo $oe_class; ?>"
				onmouseover="mod_fc_carousel<?php echo $module->id; ?>.stop();"
				onmouseout="if (mod_fc_carousel<?php echo $module->id; ?>.autoPlay==true) mod_fc_carousel<?php echo $module->id; ?>.play(<?php echo $walk_delay; ?>,'next',true); else mod_fc_carousel<?php echo $module->id; ?>.stop();"
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
			<?php /*echo !($rowcount%2) ? '<div class="modclear"></div>' : '';*/ ?>
			<?php endforeach; ?>
			
		</div>
		<!-- EOF standard items -->
	</div> <!-- class mod_fc_carousel_mask{module_id} -->
		
		<div class="mod_fc_carousel_buttons">
			<span id="stop_fcmod_<?php echo $module->id; ?>" class="mod_fc_carousel_btn fc_stop" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_STOP'); ?>"></span>
			<span id="previous_fcmod_<?php echo $module->id; ?>" class="mod_fc_carousel_btn fc_previous" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_PREVIOUS'); ?>"></span>
			<span id="next_fcmod_<?php echo $module->id; ?>" class="mod_fc_carousel_btn fc_next" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_NEXT'); ?>"></span>
			<span id="backward_fcmod_<?php echo $module->id; ?>" class="mod_fc_carousel_btn fc_backward" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_BACKWARD'); ?>"></span>
			<span id="forward_fcmod_<?php echo $module->id; ?>" class="mod_fc_carousel_btn fc_forward" title="<?php echo JText::_('FLEXI_MOD_CAROUSEL_BACKWARD'); ?>"></span>
		</div>
		
		<h4 id="mod_fc_curritem_title<?php echo $module->id; ?>">Show: <span id="mod_fc_info<?php echo $module->id; ?>"></span></h4>
		<div id="mod_fc_handles<?php echo $module->id; ?>" class="mod_fc_handles"
				onmouseover="mod_fc_carousel<?php echo $module->id; ?>.stop();"
				onmouseout="if (mod_fc_carousel<?php echo $module->id; ?>.autoPlay==true) mod_fc_carousel<?php echo $module->id; ?>.play(<?php echo $walk_delay; ?>,'next',true); else mod_fc_carousel<?php echo $module->id; ?>.stop();"
		>
			<?php
			$count=1;
			$img_path = JURI::base(true) .'/';
			?>
			<?php foreach ($list[$ord]['standard'] as $item) : ?>
			<span>
				<img width="32" height="32" src="<?php echo @ $item->image ? $item->image : $img_path.'components/com_flexicontent/assets/images/image.png'/*.($count++).".".$item->title*/; ?>" />
			</span>
			<?php endforeach; ?>
		</div>
		
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
	var mod_fc_carousel'.$module->id.';
	window.addEvent("domready",function(){
		// Walk to item
		mod_fc_carousel'.$module->id.' = new noobSlide({
			box: $("mod_fcitems_box_standard'.$module->id.'"),
			items: (MooTools.version>="1.2.4" ?
				$("mod_fcitems_box_standard'.$module->id.'").getElements("div.mod_flexicontent_standard_wrapper") : 
				$ES("div.mod_flexicontent_standard_wrapper","mod_fcitems_box_standard'.$module->id.'") ),
			handles: (MooTools.version>="1.2.4" ?
				$("mod_fc_handles'.$module->id.'").getElements("span") : $ES("span","mod_fc_handles'.$module->id.'")),
			
			handle_event: "mouseenter",
			size: 280,'./* ITEM width + padding + margin + border-width */'
			autoPlay: true,
			interval: '.$walk_delay.',
			onWalk: function(currentItem,currentHandle){
				(MooTools.version>="1.2.4" ?
					$("mod_fc_info'.$module->id.'").set("html",currentItem.getElement(".fcitem_title").get("html")) :
					$("mod_fc_info'.$module->id.'").setHTML(currentItem.getElement(".fcitem_title").innerHTML) );
				this.handles.removeClass("active");
				currentHandle.addClass("active");
			},
			'.(FLEXI_J16GE ? 'addButtons' : 'buttons').': {
				stop: $("stop_fcmod_'.$module->id.'"),
				previous: $("previous_fcmod_'.$module->id.'"),
				playback:$("backward_fcmod_'.$module->id.'"),
				play: $("forward_fcmod_'.$module->id.'"),
				next: $("next_fcmod_'.$module->id.'")
			}
		});
		
		// Alternative but it includes padding
		// var maxHeight = $("mod_fcitems_box_standard'.$module->id.'").clientHeight;
		
		// Set height of floating elements
		var maxHeight = 0;
		mod_fc_carousel'.$module->id.'.items.each(function(element) {
			maxHeight = Math.max(maxHeight, element.scrollHeight);
		});
		mod_fc_carousel'.$module->id.'.items.each(function(element) {
			element.style.height = maxHeight + "px";
		});'.
		
		/*
		// Alternative to use computed style, requires ie9+
		var maxHeight_withpx = getComputedStyle($("mod_fcitems_box_standard'.$module->id.'"), null).getPropertyValue("height")
		mod_fc_carousel'.$module->id.'.items.each(function(element) {
			element.style.height = maxHeight_withpx;
		});
		*/'
	})';

$document->addScriptDeclaration($js);

$css ='
.mod_fc_carousel_mask'.$module->id.' {
	position:relative;
	width: 100%;
	/*height: 150px;*/
	overflow:hidden;
}
#mod_fcitems_box_standard'.$module->id.' {
	display:block;
	position:relative;
	width:96%;
	padding: 0% 1%;
	margin: 0px 1% 0px 1%;
}
#mod_fcitems_box_standard'.$module->id.' div.mod_flexicontent_standard_wrapper {
	border: 1px dashed #555555;
	width:250px;
	padding:0px 12px 0px 12px;
	margin:0px 2px 0px 2px;
	height:100%;
	float:left;
	background:#f0f0f0;
	// CSS trick to force same height for all items, but crops border
	/*margin-bottom: -99999px;
  padding-bottom: 99999px;*/
}

#mod_fc_curritem_title'.$module->id.' {
	float:left;
	display:block;
	padding: 4px 2% 0px 2%;
	margin: 6px 6px 0px 6px;
}

#mod_fc_handles'.$module->id.' {
	width:96%;
	padding: 4px 2% 0px 2%;
	margin: 0px;
	
	float:none;
	display:block;
	clear:both;
}
';

$document->addStyleDeclaration($css);

?>