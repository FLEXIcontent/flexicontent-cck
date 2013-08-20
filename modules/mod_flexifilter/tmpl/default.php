<?php // no direct access
defined('_JEXEC') or die('Restricted access');

// use css class fc_nnnnn_clear to override wrapping
?>

<div class="mod_flexifilter_wrapper mod_flexifilter_wrap<?php echo $moduleclass_sfx; ?>" id="mod_flexifilter_default<?php echo $module->id ?>">

<?php
// 3. Prepare remaining form parameters
$form_target = '';
if ($catid) {
	$db = & JFactory::getDBO();
	$query 	= 'SELECT CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
		.' FROM #__categories AS c WHERE c.id = '.$catid;
	$db->setQuery( $query );
	$categoryslug = $db->loadResult();
	$form_target = JRoute::_(FlexicontentHelperRoute::getCategoryRoute($categoryslug), false);
}
$form_id = $form_name;
$form_method = 'post';   // DO NOT CHANGE THIS

$show_filter_labels = $params->get('show_filter_labels', 1);
$filter_placement = $params->get( 'filter_placement', 1 );
$filter_container_class = $filter_placement ? 'fc_filter_line' : 'fc_filter';
$text_search_val = JRequest::getString('filter', '', 'default');

// 4. Create (print) the form
$n=0;
?>
<form id='<?php echo $form_id; ?>' action='<?php echo $form_target; ?>' method='<?php echo $form_method; ?>' >

<?php if ( !empty($cats_select_field) ) : ?>
<span class="fc_filter <?php echo ($n++)%2 ? ' fc_even': ' fc_odd'; ?>">
	<span class="fc_filter_label fc_cid_label"><?php echo JText::_('FLEXI_FILTER_CATEGORY'); ?> : </span>
	<span class="fc_filter_html fc_cid_selector"><span class="cid_loading" id="cid_loading_<?php echo $module->id; ?>"></span><?php echo $cats_select_field; ?></span>
<?php elseif ( !empty($cat_hidden_field) ): ?>
	<?php echo $cat_hidden_field; ?>
<?php endif; ?>
</span>

<div class="fcclear"></div>

<?php include(JPATH_SITE.'/components/com_flexicontent/tmpl_common/filters.php'); ?>

</form>

</div> <!-- mod_flexifilter_wrap -->
