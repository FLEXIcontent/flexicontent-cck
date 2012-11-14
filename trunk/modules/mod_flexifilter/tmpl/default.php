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
$form_method = 'POST';   // DO NOT CHANGE THIS

// 4. Create (print) the form
?>
<form id='<?php echo $form_id; ?>' action='<?php echo $form_target; ?>' method='<?php echo $form_method; ?>' >

<?php if ( !empty($cats_select_field) ) : ?>
	<span class="fc_cid_label"><span class="cid_loading" id="cid_loading_<?php echo $module->id; ?>"></span><?php echo JText::_('FLEXI_FILTER_CATEGORY'); ?> : </span>
	<span class="fc_cid_selector"><?php echo $cats_select_field; ?></span>
	<span class="fc_cid_clear"></span>
<?php elseif ( !empty($cat_hidden_field) ): ?>
	<?php echo $cat_hidden_field; ?>
<?php endif; ?>

<span id="fc_filter_mod_elements_<?php echo $module->id; ?>" style="<?php echo !$catid ? 'display:none;' : ''; ?>" >
<?php
foreach ($filters as $filter_name => $filter) :
	if ( !isset($filter_html[$filter->id]) ) continue;
?>

	<?php if ( $params->get('show_filter_labels', 1)==1 ) : ?>
		<span class="fc_filter_label"><?php echo $filter->label; ?> : </span>
	<?php endif; ?>
	
	<span class="fc_filter_selector"><?php echo $filter_html[$filter->id]; ?></span>
	<span class="fc_filter_clear"></span>

<?php endforeach; ?>
	
	<?php if ($limit_selector) : ?>
		<span class="fc_limit_label hasTip" title="<?php echo JText::_('FLEXI_FILTER_PAGINATION'); ?>::<?php echo JText::_('FLEXI_FILTER_PAGINATION_INFO'); ?>">
			<?php echo JText::_('FLEXI_FILTER_PAGINATION'); ?> :
		</span>
		<span class="fc_limit_selector"><?php echo $limit_selector;?></span>
		<span class="fc_limit_clear"></span>
	<?php endif; ?>
	
	<?php if ($orderby_selector) : ?>
		<span class="fc_orderby_label hasTip" title="<?php echo JText::_('FLEXI_FILTER_ORDERBY'); ?>::<?php echo JText::_('FLEXI_FILTER_ORDERBY_INFO'); ?>">
			<?php echo JText::_('FLEXI_FILTER_ORDERBY'); ?> :
		</span>
		<span class="fc_orderby_selector"><?php echo $orderby_selector;?></span>
		<span class="fc_orderby_clear"></span>
	<?php endif; ?>
	
	<?php if (!empty($force_go) || !$autosubmit) :?>
	<button class="fc_button button_go hasTip" title="<?php echo JText::_( 'FLEXI_FILTER_GO' ); ?>::<?php echo JText::_( 'FLEXI_FILTER_GO_INFO' ); ?>" onclick="var form=document.getElementById('<?php echo $form_id; ?>');                                     adminFormPrepare(form);"><span class="fcbutton_go"><?php echo JText::_( 'FLEXI_FILTER_GO' ); ?></span></button>
	<button class="fc_button button_reset hasTip" title="<?php echo JText::_( 'FLEXI_FILTER_RESET' ); ?>::<?php echo JText::_( 'FLEXI_FILTER_RESET_INFO' ); ?>" onclick="var form=document.getElementById('<?php echo $form_id; ?>'); adminFormClearFilters(form);  adminFormPrepare(form);"><span class="fcbutton_reset"><?php echo JText::_( 'FLEXI_FILTER_RESET' ); ?></span></button>
	<?php endif; ?>
</span>
</form>
<?php //endif; ?>

</div> <!-- mod_flexifilter_wrap -->
