<?php
// **************************************************************************************************************
// Form for (a) Text search, Field Filters, Alpha-Index, Items Total Statistics, Selectors(e.g. per page, orderby)
// **************************************************************************************************************

$infoimage = JHTML::image ( 'components/com_flexicontent/assets/images/icon-16-hint.png', '' );
$limit_selector = flexicontent_html::limit_selector( $this->params );
$orderby_selector = flexicontent_html::ordery_selector( $this->params );

$search_tip_class = $this->params->get('show_search_tip') ? ' hasTip ' : '';
$search_tip_title = $this->params->get('show_search_tip') ? ' title="'.JText::_('FLEXI_SEARCH').'::'.JText::_('FLEXI_TEXT_SEARCH_INFO').'" ' : '';
$filters_list_tip_class = $this->params->get('show_filters_list_tip') ? ' hasTip ' : '';
$filters_list_tip_title = $this->params->get('show_filters_list_tip') ? ' title="'.JText::_('FLEXI_FIELD_FILTERS').'::'.JText::_('FLEXI_FIELD_FILTERS_INFO').'" ' : '';
?>

	<?php if ((($this->params->get('use_filters', 0)) && $this->filters) || ($this->params->get('use_search'))) : /* BOF filter ans search block */ ?>
	
	<div id="fc_filter" class="floattext control-group group">
		
		<?php if ($this->params->get('use_search', 0)) : /* BOF search */ ?>
		<div class="fc_text_filter_box">
			
			<?php if ($this->params->get('show_search_label', 1)) : ?>
				<span class="fc_text_filter_label <?php echo $search_tip_class;?>" <?php echo $search_tip_title;?> ><?php echo JText::_('FLEXI_SEARCH'); ?>:</span>
				<?php echo $this->params->get('compact_search_with_filters', 1) ? '<br/>' : ''; ?>
			<?php elseif ( $this->params->get('show_search_tip') ): ?>
				<span class="hasTip" <?php echo $search_tip_title;?> ><?php echo $infoimage; ?></span>
			<?php endif; ?>
			
			<input class="fc_text_filter input-medium search-query" type="text" name="filter" id="filter" value="<?php echo $this->lists['filter'];?>" />
			<?php echo $this->params->get('compact_search_with_filters', 1) ? '<br/>' : ''; ?>
			
			<?php if ($this->params->get('show_search_go', 1) || $this->params->get('show_search_reset', 1)) : ?>
				<span class="fc_text_filter_buttons">
					
					<?php if ($this->params->get('show_search_go', 1)) : ?>
						<button class="fc_button button_go btn" onclick="var form=document.getElementById('adminForm');                               adminFormPrepare(form);"><span class="fcbutton_go"><?php echo JText::_( 'FLEXI_GO' ); ?></span></button>
					<?php endif; ?>
					
					<?php if ($this->params->get('show_search_reset', 1)) : ?>
						<button class="fc_button button_reset btn" onclick="var form=document.getElementById('adminForm'); adminFormClearFilters(form);  adminFormPrepare(form);"><span class="fcbutton_reset"><?php echo JText::_( 'FLEXI_RESET' ); ?></span></button>
					<?php endif; ?>
					
				</span>
			<?php endif; ?>	
			
		</div>
		<?php endif; /* EOF search */ ?>

		<?php if ( !$this->params->get('compact_search_with_filters', 1) && $this->params->get('use_search') && ($this->params->get('use_filters', 0) && $this->filters) ) : ?>
			<div class="fc_text_filter_splitter"></div>
		<?php endif; ?>

		<?php if ($this->params->get('use_filters', 0) && $this->filters) : /* BOF filter */ ?>
		
			<?php if ($this->params->get('show_filters_list_label', 1)) : ?>
				<span class="fc_field_filters_list_label hasTip" title="<?php echo JText::_('FLEXI_FIELD_FILTERS'); ?>::<?php echo JText::_('FLEXI_FIELD_FILTERS_INFO'); ?>"><?php echo JText::_('FLEXI_FIELD_FILTERS'); ?>:</span>
				<?php echo $this->params->get('compact_search_with_filters', 1) ? '<br/>' : ''; ?>
			<?php elseif ( $this->params->get('show_filters_list_tip') ): ?>
				<span class="fc_field_filters_list_tipicon hasTip" <?php echo $filters_list_tip_title;?> ><?php echo $infoimage; ?></span>
			<?php endif; ?>
			
			<?php
			foreach ($this->filters as $filt) :
				if (empty($filt->html)) continue;
				// Add form preparation
				if ( preg_match('/onchange[ ]*=[ ]*([\'"])/i', $filt->html, $matches) ) {
					$filt->html = preg_replace('/onchange[ ]*=[ ]*([\'"])/i', 'onchange=${1}adminFormPrepare(document.getElementById(\'adminForm\'));', $filt->html);
				} else {
					$filt->html = preg_replace('/<(select|input)/i', '<${1} onchange="adminFormPrepare(document.getElementById(\'adminForm\'));"', $filt->html);
				}
				?>
				<span class="filter" >
				
					<?php if ( $this->params->get('show_filter_labels', 0) ) : ?>
						<span class="filter_label">
							<?php echo $filt->label; ?>
						</span>
					<?php endif; ?>
				
					<span class="filter_html">
						<?php echo $filt->html; ?>
					</span>
				
				</span>
			<?php endforeach; ?>
			
			<?php if (!$this->params->get('use_search')) : ?>
				<button class="fc_button button_reset btn" onclick="var form=document.getElementById('adminForm'); adminFormClearFilters(form);  adminFormPrepare(form);"><span class="fcbutton_reset"><?php echo JText::_( 'FLEXI_RESET' ); ?></span></button>
			<?php endif; ?>
		
		<?php endif; /* EOF filter */ ?>
		
	</div>
	<?php endif; /* EOF filter and search block */ ?>
	<?php
	if ($this->params->get('show_alpha', 1)) :
		echo $this->loadTemplate('alpha_html5');
	endif;
	?>

	<?php if (count($this->items)) : ?>

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
	
	</div>
	<!-- BOF items total-->

	<?php endif; ?>
