<?php // no direct access
defined('_JEXEC') or die('Restricted access');

global ${$layout};

if ($add_ccs && $caching)
{
	if (file_exists(dirname(__FILE__).DS.$layout.DS.$layout.'.css')) {
		// active layout css
		echo '<link rel="stylesheet" href="'.JURI::base(true).'/modules/mod_flexicontent/tmpl/'.$layout.'/'.$layout.'.css">';
	}
	${$layout} = 1;
}
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

<?php if ($show_more == 1) : ?>
<div class="news_readon_module">
  <div class="news_readon<?php echo $params->get('moduleclass_sfx'); ?>"<?php if ($more_css) : ?> style="<?php echo $more_css; ?>"<?php endif;?>>
	  <a class="readon" href="<?php echo JRoute::_($more_link); ?>" <?php if ($params->get('more_blank') == 1) {echo 'target="_blank"';} ?>><span><?php echo JText::_($more_title); ?></span></a>
 </div>
</div>
<?php endif;?>
