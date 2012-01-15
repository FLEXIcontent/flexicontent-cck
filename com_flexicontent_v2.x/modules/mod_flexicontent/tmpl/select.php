<?php // no direct access
defined('_JEXEC') or die('Restricted access');
?>

<div class="mod_flexicontent_wrapper mod_flexicontent_wrap<?php echo $moduleclass_sfx; ?>" id="select<?php echo $module->id ?>">
	
	<?php
	// Display Category Information
	include(JPATH_SITE.'/modules/mod_flexicontent/tmpl_common/category.php');
	?>
	
	<form class="mod_flexicontent<?php echo $params->get('moduleclass_sfx'); ?>" name="select_form_<?php echo $module->id; ?>" id="select_form_<?php echo $module->id; ?>">
	<?php
	$js = 'onchange="window.location=this.form.select_list_'.$module->id.'.value;"';
	$options = array();
	$options[] = JHTML::_('select.option', '', JText::_('FLEXI_PLEASE_SELECT'));
	foreach ($ordering as $ord) :
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