<?php // no direct access
defined('_JEXEC') or die('Restricted access');

// use css class fc_nnnnn_clear to override wrapping
?>

<div class="mod_flexifilter_wrapper mod_flexifilter_wrap<?php echo $moduleclass_sfx; ?>" id="mod_flexifilter_default<?php echo $module->id ?>">

<?php
// Prepare remaining form parameters
$form_id = $form_name;
$form_method = 'post';   // DO NOT CHANGE THIS

$show_filter_labels = $params->get('show_filter_labels', 1);
$filter_placement = $params->get( 'filter_placement', 1 );
$filter_container_class  = $filter_placement ? 'fc_filter_line' : 'fc_filter';
$filter_container_class .= $filter_placement==2 ? ' fc_clear_label' : '';
$text_search_val = JRequest::getString('filter', '', 'default');
$slider_title =$params->get('slider_title', 'FLEXI_SLIDER_SEARCH');
$slider_mod =$params->get('slider_mod', 0);

// 4. Create (print) the form
?>
<form id='<?php echo $form_id; ?>' action='<?php echo $form_target; ?>' data-fcform_default_action='<?php echo $default_target; ?>' method='<?php echo $form_method; ?>' >

<?php if ( !empty($cats_select_field) ) : ?>
<fieldset class="fc_filter_set" style="padding-bottom:0px;">
	<span class="<?php echo $filter_container_class. ' fc_odd'; ?>" style="margin-bottom:0px;">
		<span class="fc_filter_label fc_cid_label"><?php echo JText::_($mcats_selection ? 'FLEXI_FILTER_CATEGORIES' : 'FLEXI_FILTER_CATEGORY'); ?></span>
		<span class="fc_filter_html fc_cid_selector"><span class="cid_loading" id="cid_loading_<?php echo $module->id; ?>"></span><?php echo $cats_select_field; ?></span>
	</span>
</fieldset>
<div class="fcclear"></div>
<?php elseif ( !empty($cat_hidden_field) ): ?>
	<?php echo $cat_hidden_field; ?>
<?php endif; ?>

<?php 
	if ($slider_mod){
		$slider_mod = JHtml::_('sliders.start',$slider_title, array('useCookie'=>1 ,'startOffset'=>-1, 'startTransition'=>1));
		$slider_mod .=  JHtml::_('sliders.panel', $slider_title, 'slider1');
		$endslider_mod = JHtml::_('sliders.end');
		echo $slider_mod;
	}
?>
<?php include(JPATH_SITE.'/components/com_flexicontent/tmpl_common/filters.php'); ?>
<?php 
	if ($slider_mod){
		echo $endslider_mod;
	} 
?>
</form>

</div> <!-- mod_flexifilter_wrap -->
