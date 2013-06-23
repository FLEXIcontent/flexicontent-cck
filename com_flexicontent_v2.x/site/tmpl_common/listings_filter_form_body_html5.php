<?php
// **************************************************************************************************************
// Form for (a) Text search, Field Filters, Alpha-Index, Items Total Statistics, Selectors(e.g. per page, orderby)
// **************************************************************************************************************

$infoimage = JHTML::image ( 'components/com_flexicontent/assets/images/information.png', '' );
$limit_selector = flexicontent_html::limit_selector( $this->params );
$orderby_selector = flexicontent_html::ordery_selector( $this->params );

$search_shown  = $this->params->get('use_search', 0);
$filters_shown = $this->params->get('use_filters', 0) && $this->filters;
$compact_search_with_filters = $this->params->get('compact_search_with_filters', 1);
$doing_compact = $search_shown && $filters_shown && $compact_search_with_filters;

$show_search_tip       = $search_shown && $this->params->get('show_search_tip');
$show_filters_list_tip = $filters_shown && $this->params->get('show_filters_list_tip');

$legend_class  = 'fc_text_filter_label fcleft';
$legend_class .= ($show_search_tip || $show_filters_list_tip) ? ' hasTip' : '';
$legend_tip  = ($show_search_tip || $show_filters_list_tip) ? '::' : '';
$legend_tip .= $show_search_tip ? '&lt;b&gt;'.JText::_('FLEXI_TEXT_SEARCH').'&lt;/b&gt;&lt;br/&gt;'.JText::_('FLEXI_TEXT_SEARCH_INFO') : '';
$legend_tip .= ($show_search_tip || $show_filters_list_tip) ? '&lt;br/&gt;&lt;br/&gt;' : '';
$legend_tip .= $show_filters_list_tip ? '&lt;b&gt;'.JText::_('FLEXI_FIELD_FILTERS').'&lt;/b&gt;&lt;br/&gt;'.JText::_('FLEXI_FIELD_FILTERS_INFO') : '';

$show_filter_labels = $this->params->get('show_filter_labels', 1);
$filter_perrow = $this->params->get( 'filter_perrow', 0 );

$filter_container_class = $filter_perrow ? 'filter_line' : 'filter';
?>

	<?php if ( $search_shown || $filters_shown ) : /* BOF search and filters block */ ?>
	
	<div id="fc_filter_box" class="floattext control-group group">
		
		<fieldset class="fc_filter_set">
			
		<?php if ( $search_shown ) : /* BOF search */ ?>
			
			<?php if ($legend_tip) :?>
			<legend>
				<span class="<?php echo $legend_class; ?>" title="<?php echo $legend_tip; ?>">
					<!--span class="fcleft"><?php echo $infoimage; ?></span-->
					<span class="fcleft"><?php echo JText::_('FLEXI_SEARCH_FILTERING'); ?></span>
				</span>
			</legend>
			<?php endif; ?>
			
			<span class="<?php echo $doing_compact ? 'filter' : 'filter_line'; ?>">
				<?php echo $this->params->get('show_search_label', 1) ? '<span class="filter_label">'.JText::_('FLEXI_TEXT_SEARCH').'</span>' : ''; ?>
				<span class="filter_html">
				<input class="fc_text_filter rc5 input-medium search-query" type="text" name="filter" id="filter" value="<?php echo $this->lists['filter'];?>" />
				
					<?php if ( !$doing_compact && ($this->params->get('show_search_go', 1) || $this->params->get('show_search_reset', 1)) ) : ?>
					<span class="fc_text_filter_buttons">
						
						<?php if ($this->params->get('show_search_go', 1)) : ?>
						<button class="fc_button button_go btn" onclick="var form=document.getElementById('adminForm'); adminFormPrepare(form);">
							<span class="fcbutton_go"><?php echo JText::_( $filters_shown ? 'FLEXI_APPLY_FILTERING' : 'FLEXI_GO' ); ?></span>
						</button>
						<?php endif; ?>
						
						<?php if ($this->params->get('show_search_reset', 1)) : ?>
						<button class="fc_button button_reset btn" onclick="var form=document.getElementById('adminForm'); adminFormClearFilters(form); adminFormPrepare(form);">
							<span class="fcbutton_reset"><?php echo JText::_( $filters_shown ? 'FLEXI_REMOVE_FILTERING' : 'FLEXI_RESET' ); ?></span>
						</button>
						<?php endif; ?>
						
					</span>
					<?php endif; ?>
				
				</span>
				
			</span>
			
		<?php endif; /* EOF search */ ?>
		
		
		<?php if ($filters_shown): /* BOF filter */ ?>
			
			<?php
			foreach ($this->filters as $filt) :
				if (empty($filt->html)) continue;
				// Form field that have form auto submit, need to be have their onChange Event prepended with the FORM PREPARATION function call
				if ( preg_match('/onchange[ ]*=[ ]*([\'"])/i', $filt->html, $matches) ) {
					if ( preg_match('/\.submit\(\)/', $filt->html, $matches) ) {
						// Autosubmit detected inside onChange event, prepend the event with form preparation function call
						if ( $this->params->get('disable_filter_autosubmit', 0) ) {
							$filt->html = preg_replace('/onchange[ ]*=[ ]*([\'"])/i', 'onchange="document.getElementById(\'submitWarn\').style.display = \'block\';" onchange_removed=${1}', $filt->html);
							// The onChange Event, has his autosubmit removed, force GO button (in case GO button was not already inside search box)
							$force_go = true;
						} else {
							$filt->html = preg_replace('/onchange[ ]*=[ ]*([\'"])/i', 'onchange=${1}adminFormPrepare(document.getElementById(\'adminForm\')); ', $filt->html);
						}
					} else {
						// The onChange Event, has no autosubmit, force GO button (in case GO button was not already inside search box)
						$force_go = true;
					}
				} else {
					// Filter has no onChange event and thus no autosubmit, force GO button  (in case GO button was not already inside search box)
					$force_go = true;
				}
				
				$_filter_html  = '<span class="'.$filter_container_class.'" >' ."\n";
				$_filter_html .= ($show_filter_labels==1 || ($show_filter_labels==0 && $filt->parameters->get('display_label_filter')==1)) ? ' <span class="filter_label">' .$filt->label. '</span>' ."\n"  :  '';
				$_filter_html .= ' <span class="filter_html">' .$filt->html. '</span>' ."\n";
				$_filter_html .= '</span>' ."\n";
				$filters_html[] = $_filter_html;
			endforeach;
			
			// Sub-category prefix/suffix/separator parameters
			$pretext = $this->params->get( 'filter_pretext', '' ); $posttext = $this->params->get( 'filter_posttext', '' );
			$opentag = $this->params->get( 'filter_opentag', '' ); $closetag = $this->params->get( 'filter_closetag', '' );
			
			$separatorf = $this->params->get( 'filter_separatorf', 1 );
			$separators_arr = array( 0 => '&nbsp;', 1 => '<br />', 2 => '&nbsp;|&nbsp;', 3 => ',&nbsp;', 4 => $closetag.$opentag, 5 => '' );
			$separatorf = isset($separators_arr[$separatorf]) ? $separators_arr[$separatorf] : '&nbsp;';
			
			// Create the HTML of sub-category list , add configured separator
			$filters_html = implode($separatorf, $filters_html);
			// Add open/close tag to the HTML
			echo $filters_html = $opentag .$filters_html. $closetag;
			
			$go_added_already = !$doing_compact && $this->params->get('use_search') && $this->params->get('show_search_go', 1);
			$reset_added_already = !$doing_compact && $this->params->get('use_search') && $this->params->get('show_search_reset', 1);
			?>
			
			<?php if (!empty($force_go) && !$go_added_already) : ?>
			<span class="fc_text_filter_buttons">
				<span id="submitWarn" class="fc_mini_note_box" style="display:none;"><?php echo JText::_('FLEXI_FILTERS_CHANGED_CLICK_TO_SUBMIT'); ?></span>
				<button class="fc_button button_go btn" onclick="var form=document.getElementById('adminForm'); adminFormPrepare(form);">
					<span class="fcbutton_go"><?php echo JText::_( 'FLEXI_APPLY_FILTERING' ); ?></span>
				</button>
				
				<?php if (!$reset_added_already) : ?>
				<button class="fc_button button_reset btn" onclick="var form=document.getElementById('adminForm'); adminFormClearFilters(form);  adminFormPrepare(form);">
					<span class="fcbutton_reset"><?php echo JText::_( 'FLEXI_REMOVE_FILTERING' ); ?></span>
				</button>
				<?php endif; ?>
				
			</span>
			<?php endif; ?>
			
		<?php endif; /* EOF filter */ ?>
		
		</fieldset>
		
	</div>
	<?php endif; /* EOF search and filter block */ ?>
	
	
	<?php
	if ($this->params->get('show_alpha', 1)) :
		echo $this->loadTemplate('alpha_html5');
	endif;
	?>

	<?php if (count($this->items) && ($this->params->get('show_item_total', 1) || $limit_selector || $orderby_selector )) : ?>

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
		
		<span class="fc_pages_counter">
			<small><?php echo $this->pageNav->getPagesCounter(); ?></small>
		</span>
	
	</div>
	<!-- BOF items total-->

	<?php endif; ?>
