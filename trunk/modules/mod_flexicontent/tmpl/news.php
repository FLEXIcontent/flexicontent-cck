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
 * $display_text
 * $mod_readmore
 * $mod_use_image
 * $mod_image
 * $mod_link_image
 * $mod_width
 * $mod_height
 * $mod_method
 * // featured
 * $display_title_feat 
 * $link_title_feat 
 * $display_text_feat 
 * $mod_readmore_feat
 * $mod_use_image_feat 
 * $mod_link_image_feat 
 * $mod_width_feat 
 * $mod_height_feat 
 * $mod_method_feat 
 *
 * // Fields parameters
 * $use_fields 
 * $display_label 
 * $fields 
 * // featured
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

global ${$layout};

if ($add_tooltips)
	JHTML::_('behavior.tooltip');
?>

<div class="mod_flexicontent_wrapper mod_flexicontent_wrap<?php echo $moduleclass_sfx; ?>" id="news<?php echo $module->id ?>">
	<?php
	
	if ($catdata) {
		echo "<div class='currcatdata' style='clear:both; float:left;'>\n";
		if (isset($catdata->title)) {
			if (isset($catdata->titlelink)) {
				echo "<a href='{$catdata->titlelink}'>";
			}
			echo "<div class='currcattitle'>". $catdata->title ."</div>\n";
			if (isset($catdata->titlelink)) {
				echo "</a>";
			}
		}
		if (isset($catdata->image)) {
			echo "<div class='image_currcat'>";
			if (isset($catdata->imagelink)) {
				echo "<a href='{$catdata->imagelink}'>";
			}
			echo "<img src='{$catdata->image}' alt='".flexicontent_html::striptagsandcut($catdata->title, 60)."' />\n";
			if (isset($catdata->imagelink)) {
				echo "</a>";
			}
			echo "</div>\n";
		}
		if (isset($catdata->description)) {
			echo "<div class='currcatdescr'>". $catdata->description ."</div>\n";
		}
		echo "</div>\n";
	}

	$ord_titles = array(
		'popular'=>JText::_( 'FLEXI_MOST_POPULAR'),
		'commented'=>JText::_( 'FLEXI_MOST_COMMENTED'),
		'rated'=>JText::_( 'FLEXI_BEST_RATED' ),
		'added'=>	JText::_( 'FLEXI_RECENTLY_ADDED'),
		'updated'=>JText::_( 'FLEXI_RECENTLY_UPDATED'),
		'alpha'=>	JText::_( 'FLEXI_ALPHABETICAL'),
		'alpharev'=>JText::_( 'FLEXI_ALPHABETICAL_REVERSE'),
		'catorder'=>JText::_( 'FLEXI_CAT_ORDER'),
		'random'=>JText::_( 'FLEXI_RANDOM' ) );
	
	$separator = "";
	foreach ($ordering as $ord) :
  	echo $separator;
	  if (isset($list[$ord]['featured']) || isset($list[$ord]['standard'])) {
  	  $separator = "<div class='ordering_seperator' ></div>";
    } else {
  	  $separator = "";
  	}
  	
		if ($ordering_addtitle && $ord) echo "<div class='order_group_title'> ".$ord_titles[$ord]." </div>";
	?>
	<div id="<?php echo $ord.$module->id; ?>" class="mod_flexicontent<?php echo (isset($list[$ord]['featured'])) ? ' twocol' : ''; ?>">
		
		<?php
		if (isset($list[$ord]['featured'])) :
		?>
		<div class="mod_flexicontent_featured">
			<?php foreach ($list[$ord]['featured'] as $item) : ?>
			<div class="mod_flexicontent_featured_wrapper">
				<?php if ($mod_use_image_feat && $item->image) : ?>
				<div class="image_featured">
					<?php if ($mod_link_image_feat) : ?>
					<a href="<?php echo $item->link; ?>"><img src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" /></a>
					<?php else : ?>
					<img src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" />
					<?php endif; ?>
				</div>
				<?php endif; ?>
				<?php if ($display_title_feat || $display_date || $display_text_feat || $mod_readmore_feat || ($use_fields && @$item->fields && $fields_feat)) : ?>
				<div class="content_featured">
					
					<?php if ($display_title_feat) : ?>
					<div class="news_title">
						<?php if ($link_title_feat) : ?>
						<a href="<?php echo $item->link; ?>"><?php echo $item->title; ?></a>
						<?php else : ?>	
						<?php echo $item->title; ?>
						<?php endif; ?>
					</div>
					<?php endif; ?>
					
					<?php if ($date_display) : ?>
					<div class="news_date">
						<?php echo $item->date; ?>
					</div>
					<?php endif; ?>
					
					<?php if ($display_text_feat && $item->text) : ?>
					<div class="news_text">
						<?php echo $item->text; ?>
					</div>
					<?php endif; ?>
					<?php if ($use_fields && @$item->fields && $fields_feat) : ?>
					<div class="news_fields">
						<?php foreach ($item->fields as $k => $field) : ?>
						<div class="field_block field_<?php echo $k; ?>">
							<?php if ($display_label_feat) : ?>
							<span class="field_label"><?php echo $field->label . ': '; ?></span>
							<?php endif; ?>
							<span class="field_value"><?php echo $field->display; ?></span>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
					<?php if ($mod_readmore_feat) : ?>
					<div class="news_readon">
						<a href="<?php echo $item->link; ?>" class="readon"><span><?php echo JText::sprintf('Read more...'); ?></span></a>
					</div>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			</div>
			<div class="modclear"></div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<?php
		if (isset($list[$ord]['standard'])) :
		?>
		<div class="mod_flexicontent_standard">
			<?php foreach ($list[$ord]['standard'] as $item) : ?>
			<div class="mod_flexicontent_standard_wrapper">
				<?php if ($mod_use_image && $item->image) : ?>
				<div class="image_standard">
					<?php if ($mod_link_image) : ?>
					<a href="<?php echo $item->link; ?>"><img src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" /></a>
					<?php else : ?>
					<img src="<?php echo $item->image; ?>" alt="<?php echo flexicontent_html::striptagsandcut($item->fulltitle, 60); ?>" />
					<?php endif; ?>
				</div>
				<?php endif; ?>
				<?php if ($display_title || $date_display || $display_text || $mod_readmore || ($use_fields && @$item->fields && $fields)) : ?>
				<div class="content_standard">
					
					<?php if ($display_title) : ?>
					<div class="news_title">
						<?php if ($link_title) : ?>
						<a href="<?php echo $item->link; ?>"><?php echo $item->title; ?></a>
						<?php else : ?>	
						<?php echo $item->title; ?>
						<?php endif; ?>
					</div>
					<?php endif; ?>
					
					<?php if ($date_display) : ?>
					<div class="news_date">
						<?php echo $item->date; ?>
					</div>
					<?php endif; ?>
					
					<?php if ($display_text && $item->text) : ?>
					<div class="news_text">
						<?php echo $item->text; ?>
					</div>
					<?php endif; ?>
					<?php if ($use_fields && @$item->fields && $fields) : ?>
					<div class="news_fields">
						<?php foreach ($item->fields as $k => $field) : ?>
						<div class="field_block field_<?php echo $k; ?>">
							<?php if ($display_label) : ?>
							<span class="field_label"><?php echo $field->label . ': '; ?></span>
							<?php endif; ?>
							<span class="field_value"><?php echo $field->display; ?></span>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
					<?php if ($mod_readmore) : ?>
					<div class="news_readon">
						<a href="<?php echo $item->link; ?>"><span><?php echo JText::sprintf('Read more...'); ?></span></a>
					</div>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			</div>
			<div class="modclear"></div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
		
		<div class="modclear"></div>

	</div>
	<?php endforeach; ?>

	<?php if ($show_more == 1) : ?>
	<div class="news_readon_module">
	  <div class="news_readon<?php echo $params->get('moduleclass_sfx'); ?>"<?php if ($more_css) : ?> style="<?php echo $more_css; ?>"<?php endif;?>>
		  <a class="readon" href="<?php echo JRoute::_($more_link); ?>" <?php if ($params->get('more_blank') == 1) {echo 'target="_blank"';} ?>><span><?php echo JText::_($more_title); ?></span></a>
	 </div>
	</div>
	<?php endif;?>

</div>
