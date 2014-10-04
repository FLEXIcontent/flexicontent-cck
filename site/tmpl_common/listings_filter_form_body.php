<?php
// ***********************************************************************************************************
// Form for Text search, Field Filters, Alpha-Index, Items Total Statistics, Selectors(e.g. per page, orderby)
// ***********************************************************************************************************

// Text search, Field Filters
$params  = & $this->params;
$form_id = $form_name = 'adminForm';
$filters = & $this->filters;
$text_search_val = $this->lists['filter'];
include "filters.php";

// Alpha-Index
if ($this->params->get('show_alpha', 1)) :
	echo $this->loadTemplate('alpha');
endif;

$limit_selector = flexicontent_html::limit_selector( $this->params );
$orderby_selector = flexicontent_html::ordery_selector( $this->params, $formname='adminForm', $autosubmit=1, $extra_order_types=array(), $sfx='');
$orderby_selector_2nd = flexicontent_html::ordery_selector( $this->params, $formname='adminForm', $autosubmit=1, $extra_order_types=array(), $sfx='_2nd');
?>

<?php if (count($this->items) && ($this->params->get('show_item_total', 1) || $limit_selector || $orderby_selector || $orderby_selector_2nd )) : ?>

	<!-- BOF items total-->
	<div id="item_total" class="item_total group">
	
		<?php if ($this->params->get('show_item_total', 1)) : ?>
			<span class="fc_item_total_data">
				<?php echo @$this->resultsCounter ? $this->resultsCounter : $this->pageNav->getResultsCounter(); // custom Results Counter ?>
			</span>
		<?php endif; ?>
		
		<?php if ($limit_selector) : ?>
			<span class="fc_limit_label hasTip" title="<?php echo JText::_('FLEXI_PAGINATION'); ?>::<?php echo JText::_('FLEXI_PAGINATION_INFO'); ?>">
				<span class="fc_limit_selector"><?php echo $limit_selector;?></span>
			</span>
		<?php endif; ?>
		
		<?php if ($orderby_selector) : ?>
			<span class="fc_orderby_label hasTip" title="<?php echo JText::_('FLEXI_ORDERBY'); ?>::<?php echo JText::_('FLEXI_ORDERBY_INFO'); ?>">
				<span class="fc_orderby_selector"><?php echo $orderby_selector;?></span>
			</span>
		<?php endif; ?>
		
		<?php if ($orderby_selector_2nd) : ?>
			<span class="fc_orderby_label hasTip" title="<?php echo JText::_('FLEXI_ORDERBY'); ?>::<?php echo JText::_('FLEXI_ORDERBY_INFO'); ?>">
				<span class="fc_orderby_selector"><?php echo $orderby_selector_2nd;?></span>
			</span>
		<?php endif; ?>
		
		<span class="fc_pages_counter">
			<small><?php echo $this->pageNav->getPagesCounter(); ?></small>
		</span>
	
	</div>
	<!-- BOF items total-->

<?php endif; ?>