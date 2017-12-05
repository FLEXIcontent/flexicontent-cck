<?php // no direct access
defined('_JEXEC') or die('Restricted access');

flexicontent_html::loadFramework('select2');
$container_id = $module->id . (count($catdata_arr)>1 && $catdata ? '_'.$catdata->id : '');
?>

<div class="select mod_flexicontent_wrapper mod_flexicontent_wrap<?php echo $moduleclass_sfx; ?>" id="mod_flexicontent_select<?php echo $container_id; ?>">
	
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
		'jorder'=>JText::_( 'FLEXI_UMOD_CAT_ORDER_JOOMLA'),
		'random'=>JText::_( 'FLEXI_UMOD_RANDOM_ITEMS' ),
		'field'=>JText::sprintf( 'FLEXI_UMOD_CUSTOM_FIELD', $orderby_custom_field->label)
	);
	?>
	
	<form class="mod_flexicontent<?php echo $params->get('moduleclass_sfx'); ?>" name="select_form_<?php echo $module->id; ?>" id="select_form_<?php echo $module->id; ?>">
	<?php
	$attribs = array('class' => 'use_select2_lib', 'onchange' => 'window.location=this.form.select_list_'.$module->id.'.value;');
	$options = array();
	$options[] = JHtml::_('select.option', '', JText::_('FLEXI_PLEASE_SELECT'));
	
	$ord_count = 0;
	foreach ($ordering as $ord) :
		$ord_count++;
		
		if ($ordering_addtitle && $ord) :
			if ($ord_count > 1)
				$options[] = JHtml::_('select.optgroup', '' );
			$options[] = JHtml::_('select.optgroup', (isset($ord_titles[$ord]) ? $ord_titles[$ord] : $ord) );
		endif;
		
		if (isset($list[$ord]['featured'])) :
			foreach ($list[$ord]['featured'] as $item) :
				$options[] = JHtml::_('select.option', $item->link, $item->title);
			endforeach;
		endif;
		
		if (isset($list[$ord]['standard'])) :
			foreach ($list[$ord]['standard'] as $item) :
				$options[] = JHtml::_('select.option', $item->link, $item->title);
			endforeach;
		endif;
		
	endforeach;
	
	echo JHtml::_('select.genericlist', $options, 'select_list_'.$module->id, $attribs, 'value', 'text', null);
	?>
	</form>

	<div class="modclear"></div>
	
	<?php
	// Display readon of module
	include(JPATH_SITE.'/modules/mod_flexicontent/tmpl_common/readon.php');
	?>
	
</div>