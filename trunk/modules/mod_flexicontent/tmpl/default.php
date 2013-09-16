<?php // no direct access
defined('_JEXEC') or die('Restricted access');
?>

<div class="default mod_flexicontent_wrapper mod_flexicontent_wrap<?php echo $moduleclass_sfx; ?>" id="mod_flexicontent_default<?php echo $module->id ?>">
	
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
		<ul class="mod_flexicontent<?php echo $moduleclass_sfx; ?> mod_flexicontent_featured">
			
			<?php foreach ($list[$ord]['featured'] as $item) : ?>
			<li>
				<?php if ($add_tooltips) : ?>
				<a href="<?php echo $item->link; ?>" class="hasTip" title="<?php echo htmlspecialchars($item->fulltitle, ENT_COMPAT, "UTF-8").'::'.htmlspecialchars($item->text, ENT_COMPAT, "UTF-8"); ?>">
					<?php echo $item->title; ?>
				</a>
				<?php else : ?>
				<a href="<?php echo $item->link; ?>"><?php echo $item->title; ?></a>
				<?php endif; ?>
			</li>
			<!-- EOF current item -->
			<?php endforeach; ?>
			
		</ul>
		<!-- EOF featured items -->
		<?php endif; ?>
		
		
		<?php if (isset($list[$ord]['standard'])) : ?>
		<!-- BOF standard items -->
		
		<ul class="mod_flexicontent<?php echo $moduleclass_sfx; ?> mod_flexicontent_standard">
			
			<?php foreach ($list[$ord]['standard'] as $item) : ?>
			<li>
				<?php if ($add_tooltips) : ?>
				<a href="<?php echo $item->link; ?>" class="hasTip" title="<?php echo htmlspecialchars($item->fulltitle, ENT_COMPAT, "UTF-8").'::'.htmlspecialchars($item->text, ENT_COMPAT, "UTF-8"); ?>">
					<?php echo $item->title; ?>
				</a>
				<?php else : ?>
				<a href="<?php echo $item->link; ?>"><?php echo $item->title; ?></a>
				<?php endif; ?>
			</li>
			<!-- EOF current item -->
			<?php endforeach; ?>
			
		</ul>
		<!-- EOF standard items -->
		<?php endif; ?>
		
	</div>
	<?php endforeach; ?>
	
	<?php
	// Display readon of module
	include(JPATH_SITE.'/modules/mod_flexicontent/tmpl_common/readon.php');
	?>
	
</div>