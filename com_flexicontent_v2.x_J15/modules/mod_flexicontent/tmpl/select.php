<?php // no direct access
defined('_JEXEC') or die('Restricted access');
?>

<div class="select mod_flexicontent_wrapper mod_flexicontent_wrap<?php echo $moduleclass_sfx; ?>" id="mod_flexicontent_select<?php echo $module->id ?>">
	
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
	?>
	
	<form class="mod_flexicontent<?php echo $params->get('moduleclass_sfx'); ?>" name="select_form_<?php echo $module->id; ?>" id="select_form_<?php echo $module->id; ?>">
	<?php
	$js = 'onchange="window.location=this.form.select_list_'.$module->id.'.value;"';
	$options = array();
	$options[] = JHTML::_('select.option', '', JText::_('FLEXI_PLEASE_SELECT'));
	
	$ord_count = 0;
	foreach ($ordering as $ord) :
		$ord_count++;
		
		if ($ordering_addtitle && $ord) :
			if ($ord_count > 1)
				$options[] = JHTML::_('select.optgroup', '' );
			$options[] = JHTML::_('select.optgroup', $ord_titles[$ord] );
		endif;
		
		if (isset($list[$ord]['featured'])) :
			foreach ($list[$ord]['featured'] as $item) :
				$options[] = JHTML::_('select.option', $item->link, $item->title);
			endforeach;
		endif;
		
		if (isset($list[$ord]['standard'])) :
			foreach ($list[$ord]['standard'] as $item) :
				$options[] = JHTML::_('select.option', $item->link, $item->title);
			endforeach;
		endif;
		
	endforeach;
	
	echo JHTML::_('select.genericlist', $options, 'select_list_'.$module->id, $js, 'value', 'text', null);
	?>
	</form>

	<div class="modclear"></div>
	
	<?php
	// Display readon of module
	include(JPATH_SITE.'/modules/mod_flexicontent/tmpl_common/readon.php');
	?>
	
</div>