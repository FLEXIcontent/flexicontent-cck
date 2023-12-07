<?php // no direct access
defined('_JEXEC') or die('Restricted access');

$tooltip_class = FLEXI_J30GE ? 'hasTooltip' : 'hasTip';
$container_id = $module->id . (count($catdata_arr)>1 && $catdata ? '_'.$catdata->id : '');
?>

<div class="default mod_flexicontent_wrapper mod_flexicontent_wrap<?php echo $moduleclass_sfx; ?>" id="mod_flexicontent_default<?php echo $container_id; ?>">
	
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
	
	$separator = "";
	
	foreach ($ordering as $ord) :
  	echo $separator;
	  if (isset($list[$ord]['featured']) || isset($list[$ord]['standard'])) {
  	  $separator = "<div class='ordering_separator' ></div>";
    } else {
  	  $separator = "";
  	  continue;
  	}
	?>
	<div id="<?php echo 'order_'.( $ord ? $ord : 'default' ) . $module->id; ?>" class="mod_flexicontent">
		
		<?php	if ($ordering_addtitle && $ord) : ?>
		<div class='order_group_title'><?php echo isset($ord_titles[$ord]) ? $ord_titles[$ord] : $ord; ?></div>
		<?php endif; ?>
		
		<?php if (isset($list[$ord]['featured'])) : ?>
		<!-- BOF featured items -->
		<ul class="mod_flexicontent<?php echo $moduleclass_sfx; ?> mod_flexicontent_featured">
			
			<?php foreach ($list[$ord]['featured'] as $item) : ?>
			<li class="<?php echo $item->is_active_item ? 'fcitem_active' : ''; ?>" >
				<a href="<?php echo $item->link; ?>"
						class="fcitem_link <?php echo $tooltip_class; ?>"
						title="<?php echo flexicontent_html::getToolTip($item->fulltitle, $item->text, 0, 1); ?>">
					<?php echo $item->title; ?>
				</a>
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
			<li class="<?php echo $item->is_active_item ? 'fcitem_active' : ''; ?>" >
				<a href="<?php echo $item->link; ?>"
						class="fcitem_link <?php echo $tooltip_class; ?>"
						title="<?php echo flexicontent_html::getToolTip($item->fulltitle, $item->text, 0, 1); ?>">
					<?php echo $item->title; ?>
				</a>
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