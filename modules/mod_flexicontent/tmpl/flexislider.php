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

$walk_delay = 5000;
?>

<div class="module<?php echo $moduleclass_sfx; ?>" id="module<?php echo $module->id; ?>">
	
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
		'updated'=>JText::_( 'FLEXI_RECENTLY_UPDATED'),
		'alpha'=>	JText::_( 'FLEXI_ALPHABETICAL'),
		'alpharev'=>JText::_( 'FLEXI_ALPHABETICAL_REVERSE'),
		'catorder'=>JText::_( 'FLEXI_CAT_ORDER'),
		'random'=>JText::_( 'FLEXI_RANDOM' ),
		 0=>'Default' );
	
	$separator = "";
	$rowtoggler = 0;
	$twocols = $params->get('item_columns', 1) == 2;
	
	foreach ($ordering as $ord) :
  	echo $separator;
	  if (isset($list[$ord]['featured']) || isset($list[$ord]['standard'])) {
  	  $separator = "<div class='ordering_seperator' ></div>";
    } else {
  	  $separator = "";
  	  continue;
  	}
	?>
	<div id="<?php echo ( ($ord) ? $ord : 'default' ) . $module->id; ?>" class="mod_flexicontent<?php echo ($twocols) ? ' twocol' : ''; ?>">
		
		<?php	if ($ordering_addtitle && $ord) : ?>
		<div class='order_group_title'><?php echo $ord_titles[$ord]; ?></div>
		<?php endif; ?>
		
		<!-- BOF featured items -->
		<?php if (isset($list[$ord]['featured'])) :	?>

	<div class="rslides_container">
		<div class="mod_flexicontent_featured" id="mod_fc_carousel_box_featured<?php echo $module->id ?>">
			<?php foreach ($list[$ord]['featured'] as $item) : ?>
			<?php $rowtoggler = !$rowtoggler; ?>
			
			<!-- BOF current item -->	
			<ul class="rslides">
				
				<!-- BOF current item's title -->	
				<?php if ($display_title_feat) : ?>
				<span class="fc_block" >
					<span class="fc_inline_block news_title">
						<?php if ($link_title_feat) : ?>
						<a href="<?php echo $item->link; ?>"><?php echo $item->title; ?></a>
						<?php else : ?>	
						<?php echo $item->title; ?>
						<?php endif; ?>
					</span>
				</span>
				<?php endif; ?>
				<!-- EOF current item's title -->	
				
				<!-- BOF current item's image -->	
				<?php if ($mod_use_image_feat && $item->image_rendered) : ?>

				<span class="image_featured">
					<?php if ($mod_link_image_feat) : ?>
						<a href="<?php echo $item->link; ?>"><?php echo $item->image_rendered; ?></a>
					<?php else : ?>
						<?php echo $item->image_rendered; ?>
					<?php endif; ?>
				</span>
				
				<?php elseif ($mod_use_image_feat && $item->image) : ?>
				
				<span class="image_featured">
					<?php if ($mod_link_image_feat) : ?>
						<a href="<?php echo $item->link; ?>"><img <?php echo $force_height_feat." ".$force_width_feat; ?> src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" /></a>
					<?php else : ?>
						<li><img <?php echo $force_height_feat." ".$force_width_feat; ?> src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" /></li>
					<?php endif; ?>
				</span>
				
				<?php endif; ?>
				<!-- BOF current item's image -->
				
				<!-- BOF current item's content -->
				<?php if ($display_date_feat || $display_text_feat || $mod_readmore_feat || ($use_fields_feat && @$item->fields && $fields_feat)) : ?>
				<span class="content_featured">
					
					<?php if ($display_date_feat && $item->date_created) : ?>
					<span class="fc_block">
						<span class="fc_inline news_date created">
						</br>	<?php echo $item->date_created; ?>
						</span>
					</span>
					<?php endif; ?>
					
					<?php if ($display_date_feat && $item->date_modified) : ?>
					<span class="fc_block">
						<span class="fc_inline news_date modified">
							</br><?php echo $item->date_modified; ?>
						</span>
					</span>
					<?php endif; ?>
					
					<?php if ($display_text_feat && $item->text) : ?>
					<p class="news_text">
						<?php echo $item->text; ?>
					</p>
					<?php endif; ?>
					
					<?php if ($use_fields_feat && @$item->fields && $fields_feat) : ?>
					<span class="news_fields">
						
						<?php foreach ($item->fields as $k => $field) : ?>
						<span class="field_block field_<?php echo $k; ?>">
							<?php if ($display_label_feat) : ?>
							<span class="field_label"><?php echo $field->label . $text_after_label_feat; ?></span>
							<?php endif; ?>
							<span class="field_value"><?php echo $field->display; ?></span>
						</span>
						<?php endforeach; ?>
						
					</span>
					<?php endif; ?>
					
					<?php if ($mod_readmore_feat) : ?>
					<span class="fc_block">
						<span class="news_readon">
							<a href="<?php echo $item->link; ?>" class="readon"><span><?php echo JText::sprintf('Read more...'); ?></span></a>
						</span>
					</span>
					<?php endif; ?>
					
					<span class="clearfix"></span> 
					
				</span> <!-- EOF current item's content -->
				<?php endif; ?>
				
			</ul> <!-- EOF current item -->
			<?php endforeach; ?>
			
		</div><!-- EOF featured items -->
	</div> <!-- class mod_fc_carousel_mask{module_id} -->
		
		<?php endif; ?>
		
		
		<!-- BOF standard items -->
		<?php	if (isset($list[$ord]['standard'])) : ?>
		<?php	$rowcount = 0; ?>
	
	<div id="camera_wrap_<?php echo $module->id; ?>">	
	
		<?php	foreach ($list[$ord]['standard'] as $item) : ?>
		
			<?php
			// Image is either,
			// (a) custom HTML (called "image already rendered")
			// (b) a SRC parameter (to be use for an 'img' HTML TAG)
			
			if ($mod_use_image && $item->image_rendered) {
				// Image is either already rendered, extract the SRC part of first image found, if we find any ...
				preg_match('/(src)=["\']([^"\']*)["\']/i',$item->image_rendered, $image_rendered_src);
				$image_rendered_src = @ $image_rendered_src[2];
			}
			?>
			
			<?php
			// -- CHECK if both empty and skip adding them because this will break the slider 
			if ( strlen($image_rendered_src) <= 2 && strlen($item->image) <= 2 ) continue;
			?>
			
			<div data-src="<?php echo $image_rendered_src ? $image_rendered_src : $item->image; ?>">
				<div class="camera_caption">
					
					<?php if ($display_title) : ?>
					<span class="fc_block" >
						<span class="fc_inline_block news_title">
							<?php if ($link_title) : ?>
							<a href="<?php echo $item->link; ?>"><?php echo $item->title; ?></a>
							<?php else : ?>	
							<?php echo $item->title; ?>
							<?php endif; ?>
						</span>
					</span>
					<?php endif; ?>
					
					<?php if ($display_text && $item->text) : ?>
					<p class="news_text">
						<?php echo $item->text; ?>
					</p>
					<?php endif; ?>
					</br>
					
					<span class="news_readon2">
						<a href="<?php echo $item->link; ?>" class="btn btn-warning">
							<span><?php echo JText::sprintf('Lire la suite...'); ?></span>
						</a>
					</span>
					
				</div>
			</div>
				
		<?php endforeach; ?>
			
		
	</div> <!-- class mod_fc_carousel_mask{module_id} -->
		
		
		<?php endif; ?>
				
	</div>
	<?php endforeach; ?>
	
	<?php
	// Display readon of module
	include(JPATH_SITE.'/modules/mod_flexicontent/tmpl_common/readon.php');
	?>
	
</div>

<?php $js ="
jQuery(function(){
			
			jQuery('#camera_wrap_". $module->id ."').camera({
				autostart: true,
				thumbnails: false,
				height: '200px',
				minHeight: '',
                pauseOnClick: false,
                hover: true,
                fx: 'scrollBottom',
                pagination: false,
                thumbheight: '',
                thumbwidth: '',
				portrait: false,
                alignment: 'center',
                autoAdvance: true,
				time : 7000,
				transPeriod: 1500,
                mobileAutoAdvance: true,
                barDirection: 'leftToRight',
                barPosition: 'bottom',
				loader: 'none'
			});

})";

$document 	=& JFactory::getDocument(); 
$document->addScriptDeclaration($js);
//$document->addScript(JURI::base(true).'/modules/mod_flexicontent/tmpl/'.$layout.'/js/jquery.easing.1.3.js.js');
//$document->addScript(JURI::base(true).'/modules/mod_flexicontent/tmpl/'.$layout.'/js/jquery.min.js');
//$document->addScript(JURI::base(true).'/modules/mod_flexicontent/tmpl/'.$layout.'/js/jquery.mobile.customized.min.js');
//$document->addScript(JURI::base(true).'/modules/mod_flexicontent/tmpl/'.$layout.'/js/camera.min.js');
//$document->addStyleSheet(JURI::base(true).'/modules/mod_flexicontent/tmpl/'.$layout.'/css/camera.css');

$css ="
#camera_wrap_" . $module->id . " .camera_caption {
	display: block;
	position: absolute;
}

";
$document->addStyleDeclaration($css);

?>