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

// 4. Create (print) the form
$n=0;
?>
<form id='<?php echo $form_id; ?>' action='<?php echo $form_target; ?>' method='<?php echo $form_method; ?>' >

<?php if ( !empty($cats_select_field) ) : ?>
<span class="fc_filter <?php echo ($n++)%2 ? ' fc_even': ' fc_odd'; ?>">
	<span class="fc_filter_label fc_cid_label"><span class="cid_loading" id="cid_loading_<?php echo $module->id; ?>"></span><?php echo JText::_('FLEXI_FILTER_CATEGORY'); ?> : </span>
	<span class="fc_filter_html fc_cid_selector"><?php echo $cats_select_field; ?></span>
<?php elseif ( !empty($cat_hidden_field) ): ?>
	<?php echo $cat_hidden_field; ?>
<?php endif; ?>
</span>

	
<?php if ($limit_selector) : ?>
<span class="fc_filter <?php echo ($n++)%2 ? ' fc_even': ' fc_odd'; ?>">
	<span class="fc_filter_label fc_limit_label hasTip" title="<?php echo JText::_('FLEXI_FILTER_PAGINATION'); ?>::<?php echo JText::_('FLEXI_FILTER_PAGINATION_INFO'); ?>">
		<?php echo JText::_('FLEXI_FILTER_PAGINATION'); ?> :
	</span>
	<span class="fc_filter_html fc_limit_selector"><?php echo $limit_selector;?></span>
</span>
<?php endif; ?>

<?php if ($orderby_selector) : ?>
<span class="fc_filter <?php echo ($n++)%2 ? ' fc_even': ' fc_odd'; ?>">
	<span class="fc_filter_label fc_orderby_label hasTip" title="<?php echo JText::_('FLEXI_FILTER_ORDERBY'); ?>::<?php echo JText::_('FLEXI_FILTER_ORDERBY_INFO'); ?>">
		<?php echo JText::_('FLEXI_FILTER_ORDERBY'); ?> :
	</span>
	<span class="fc_filter_html fc_orderby_selector"><?php echo $orderby_selector;?></span>
</span>
<?php endif; ?>

<?php
	// Prefix/Suffix texts
	$pretext = $params->get( 'filter_pretext', '' );
	$posttext = $params->get( 'filter_posttext', '' );
	// Open/Close tags
	$opentag = $params->get( 'filter_opentag', '' );
	$closetag = $params->get( 'filter_closetag', '' );
	
	$filters_html = array();
	foreach ($filters as $filter_name => $filt)
	{
		if (empty($filt->html)) continue;
		$show_label = $show_filter_labels==1 || ($show_filter_labels==0 && $filt->parameters->get('display_label_filter')==1);
		
		$_filter_html  = $pretext;
		$_filter_html .= '<span class="'.$filter_container_class.(($n++)%2 ? ' fc_even': ' fc_odd').'" >' ."\n";
		$_filter_html .= $show_label ? ' <span class="fc_filter_label">' .$filt->label. '</span>' ."\n"  :  '';
		$_filter_html .= ' <span class="fc_filter_html">' .$filt->html. '</span>' ."\n";
		$_filter_html .= '</span>'."\n";
		$_filter_html .= $posttext;
		$filters_html[] = $_filter_html;
	}
	// (if) Using separator
	$separatorf = '';
	if ( $filter_placement==0 ) {  
		$separatorf = $params->get( 'filter_separatorf', 1 );
		$separators_arr = array( 0 => '&nbsp;', 1 => '<br />', 2 => '&nbsp;|&nbsp;', 3 => ',&nbsp;', 4 => $closetag.$opentag, 5 => '' );
		$separatorf = isset($separators_arr[$separatorf]) ? $separators_arr[$separatorf] : '&nbsp;';
	}
	
	// Create HTML of filters
	echo $opentag . implode($separatorf, $filters_html) . $closetag;
	unset ($filters_html);
?>
	
<?php if (!empty($force_go) || !$autosubmit) :?>
<span id="<?php echo $form_id; ?>_submitWarn" class="fc-mssg fc-note" style="display:none;"><?php echo JText::_('FLEXI_FILTERS_CHANGED_CLICK_TO_SUBMIT'); ?></span>
<span class="fc_buttons">
	<button class="fc_button button_go hasTip" title="<?php echo JText::_( 'FLEXI_FILTER_GO' ); ?>::<?php echo JText::_( 'FLEXI_FILTER_GO_INFO' ); ?>" onclick="var form=document.getElementById('<?php echo $form_id; ?>');                                     adminFormPrepare(form);"><span class="fcbutton_go"><?php echo JText::_( 'FLEXI_FILTER_GO' ); ?></span></button>
	<button class="fc_button button_reset hasTip" title="<?php echo JText::_( 'FLEXI_FILTER_RESET' ); ?>::<?php echo JText::_( 'FLEXI_FILTER_RESET_INFO' ); ?>" onclick="var form=document.getElementById('<?php echo $form_id; ?>'); adminFormClearFilters(form);  adminFormPrepare(form);"><span class="fcbutton_reset"><?php echo JText::_( 'FLEXI_FILTER_RESET' ); ?></span></button>
</span>
<?php endif; ?>

</form>
<?php //endif; ?>

</div> <!-- mod_flexifilter_wrap -->
