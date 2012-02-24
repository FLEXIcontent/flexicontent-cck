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
?>

<div class="mod_flexicontent_wrapper mod_flexicontent_wrap<?php echo $moduleclass_sfx; ?>" id="news<?php echo $module->id ?>">
	
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

		<div class="mod_flexicontent_featured">
			
			<?php foreach ($list[$ord]['featured'] as $item) : ?>
			<?php $rowtoggler = !$rowtoggler; ?>
			
			<!-- BOF current item -->	
			<div class="mod_flexicontent_featured_wrapper <?php echo ($rowtoggler)?'odd':'even'; ?>">
				
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
						<img <?php echo $force_height_feat." ".$force_width_feat; ?> src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" />
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
							<?php echo $item->date_created; ?>
						</span>
					</span>
					<?php endif; ?>
					
					<?php if ($display_date_feat && $item->date_modified) : ?>
					<span class="fc_block">
						<span class="fc_inline news_date modified">
							<?php echo $item->date_modified; ?>
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
				
			</div> <!-- EOF current item -->
			<?php endforeach; ?>
			
		</div><!-- EOF featured items -->
		
		<?php endif; ?>
		
		
		<!-- BOF standard items -->
		<?php	if (isset($list[$ord]['standard'])) : ?>
		<?php	$rowcount = 0; ?>
		
		<div class="mod_flexicontent_standard">
			
			<?php foreach ($list[$ord]['standard'] as $item) : ?>
			<?php $rowcount++; ?>
			
			<!-- BOF current item -->	
			<div class="mod_flexicontent_standard_wrapper <?php echo ($rowcount%4==1 || $rowcount%4==2)?'odd':'even'; ?>">

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
				
				<!-- BOF current item's image -->	
				<?php if ($mod_use_image && $item->image_rendered) : ?>
				<span class="image_standard">
					<?php if ($mod_link_image) : ?>
						<a href="<?php echo $item->link; ?>"><?php echo $item->image_rendered; ?></a>
					<?php else : ?>
						<?php echo $item->image_rendered; ?>
					<?php endif; ?>
				</span>
				
				<?php elseif ($mod_use_image && $item->image) : ?>
				
				<span class="image_standard">
					<?php if ($mod_link_image) : ?>
						<a href="<?php echo $item->link; ?>"><img <?php echo $force_height." ".$force_width; ?> src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" /></a>
					<?php else : ?>
						<img <?php echo $force_height." ".$force_width; ?> src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" />
					<?php endif; ?>
				</span>
				
				<?php endif; ?>
				<!-- BOF current item's image -->	
				
				<!-- BOF current item's content -->
				<?php if ($display_date || $display_text || $mod_readmore || ($use_fields && @$item->fields && $fields)) : ?>
				<span class="content_standard">
										
					<?php if ($display_date && $item->date_created) : ?>
					<span class="fc_block">
						<span class="fc_inline news_date created">
							<?php echo $item->date_created; ?>
						</span>
					</span>
					<?php endif; ?>
					
					<?php if ($display_date && $item->date_modified) : ?>
					<span class="fc_block">
						<span class="fc_inline news_date modified">
							<?php echo $item->date_modified; ?>
						</span>
					</span>
					<?php endif; ?>
					
					<?php if ($display_text && $item->text) : ?>
					<p class="news_text">
						<?php echo $item->text; ?>
					</p>
					<?php endif; ?>
					
					<?php if ($use_fields && @$item->fields && $fields) : ?>
					<span class="news_fields">
						
						<?php foreach ($item->fields as $k => $field) : ?>
						<span class="field_block field_<?php echo $k; ?>">
							<?php if ($display_label) : ?>
							<span class="field_label"><?php echo $field->label . $text_after_label; ?></span>
							<?php endif; ?>
							<span class="field_value"><?php echo $field->display; ?></span>
						</span>
						<?php endforeach; ?>
						
					</span>
					<?php endif; ?>
					
					<?php if ($mod_readmore) : ?>
					<span class="fc_block">
						<span class="news_readon">
							<a href="<?php echo $item->link; ?>" class="readon"><span><?php echo JText::sprintf('Read more...'); ?></span></a>
						</span>
					</span>
					<?php endif; ?>

					<span class="clearfix"></span> 
					
				</span> <!-- EOF current item's content -->
				<?php endif; ?>
				
			</div> <!-- EOF current item -->
			<?php echo !($rowcount%2) ? '<div class="modclear"></div>' : ''; ?>
			<?php endforeach; ?>
			
		</div><!-- EOF standard items -->
		<?php endif; ?>
				
		<div class="modclear"></div>

	</div>
	<?php endforeach; ?>
	
	<?php
	// Display readon of module
	include(JPATH_SITE.'/modules/mod_flexicontent/tmpl_common/readon.php');
	?>
	
</div>