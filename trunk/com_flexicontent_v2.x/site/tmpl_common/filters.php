<?php

// Form (text search / filters) configuration
$show_search_go = $params->get('show_search_go', 1);
$show_search_reset = $params->get('show_search_reset', 1);
$filter_autosubmit = $params->get('filter_autosubmit', 0);
$filter_instructions = $params->get('filter_instructions', 1);
$filter_placement = $params->get( 'filter_placement', 1 );

$filter_container_class  = $filter_placement ? 'fc_filter_line' : 'fc_filter';
$filter_container_class .= $filter_placement==2 ? ' fc_clear_label' : '';

// Text Search configuration
$use_search  = $params->get('use_search', 1);
$show_search_label = $params->get('show_search_label', 1);
$search_autocomplete = $params->get( 'search_autocomplete', 1 );

// Filters configuration
$use_filters = $params->get('use_filters', 0) && $filters;
$show_filter_labels = $params->get('show_filter_labels', 1);

// a ZERO initial value of show_search_go ... is AUTO
$show_search_go = $show_search_go || !$filter_autosubmit || $use_search;

// Calculate needed flags
$filter_instructions = ($use_search || $use_filters) ? $filter_instructions : 0;

// Create instructions (tooltip or inline message)
$legend_class = 'fc_legend_text';
$legend_tip = '';
if ($filter_instructions == 1) {
	$legend_class .= ' hasTip';
	$legend_tip  = '::';
	$legend_tip .= $use_search ? '&lt;b&gt;'.JText::_('FLEXI_TEXT_SEARCH').'&lt;/b&gt;&lt;br/&gt;'.JText::_('FLEXI_TEXT_SEARCH_INFO') : '';
	$legend_tip .= ($use_search || $use_filters) ? '&lt;br/&gt;&lt;br/&gt;' : '';
	$legend_tip .= $use_filters ? '&lt;b&gt;'.JText::_('FLEXI_FIELD_FILTERS').'&lt;/b&gt;&lt;br/&gt;'.JText::_('FLEXI_FIELD_FILTERS_INFO') : '';
} else if ($filter_instructions == 2) {
	$legend_inline ='';
	$legend_inline .= $use_search ? '<strong>'.JText::_('FLEXI_TEXT_SEARCH').'</strong><br/>'.JText::_('FLEXI_TEXT_SEARCH_INFO') : '';
	$legend_inline .= ($use_search || $use_filters) ? '<br/><br/>' : '';
	$legend_inline .= $use_filters ? '<strong>'.JText::_('FLEXI_FIELD_FILTERS').'</strong><br/>'.JText::_('FLEXI_FIELD_FILTERS_INFO') : '';
}

?>

<?php if ( $use_search || $use_filters ) : /* BOF search and filters block */ ?>

	<?php
	$searchphrase_selector = flexicontent_html::searchphrase_selector($params, $form_name);
	?>

<div id="<?php echo $form_id; ?>_filter_box" class="fc_filter_box floattext">
	
	<fieldset class="fc_filter_set">
		
		<?php if ($filter_instructions == 1) : ?>
		<legend>
			<span class="<?php echo $legend_class; ?>" title="<?php echo $legend_tip; ?>">
				<span class=""><?php echo JText::_('FLEXI_SEARCH_FILTERING'); ?></span>
			</span>
		</legend>
		<?php endif; ?>
		
		<?php if ($filter_instructions == 2) :?>
			<span class="fc-mssg fc-info"><?php echo $legend_inline; ?></span>
		<?php endif; ?>
		
		<?php if ( $use_search ) : /* BOF search */ ?>
			<?php
			$ignoredwords = JRequest::getVar('ignoredwords');
			$shortwords = JRequest::getVar('shortwords');
			$min_word_len = JFactory::getApplication()->getUserState( JRequest::getVar('option').'.min_word_len', 0 );
			$msg = '';
			$msg .= $ignoredwords ? JText::_('FLEXI_WORDS_IGNORED_MISSING_COMMON').': <b>'.$ignoredwords.'</b>' : '';
			$msg .= $ignoredwords && $shortwords ? ' <br/> ' : '';
			$msg .= $shortwords ? JText::sprintf('FLEXI_WORDS_IGNORED_TOO_SHORT', $min_word_len) .': <b>'.$shortwords.'</b>' : '';
			?>
			
			<span class="<?php echo $filter_container_class; ?> fc_filter_text_search fc_odd">
				<?php
				$text_search_class = 'fc_text_filter';
				$text_search_class .= $search_autocomplete ? ($search_autocomplete==2 ? ' fc_index_complete_tlike fc_basic_complete' : ' fc_index_complete_simple fc_basic_complete fc_label_internal') : ' fc_label_internal';
				$text_search_label = JText::_($show_search_label==2 ? 'FLEXI_TEXT_SEARCH' : 'FLEXI_TYPE_TO_LIST');
				?>
				
				<?php if ($show_search_label==1) : ?>
					<span class="fc_filter_label"><?php echo JText::_('FLEXI_TEXT_SEARCH'); ?></span>
				<?php endif; ?>
				
				<span class="fc_filter_html">
					<input type="<?php echo $search_autocomplete==2 ? 'hidden' : 'text'; ?>" class="<?php echo $text_search_class; ?>"
						fc_label_text="<?php echo $text_search_label; ?>" name="filter"
						id="<?php echo $form_id; ?>_filter" value="<?php echo $text_search_val;?>" />
					<?php echo $searchphrase_selector; ?>
					
					<?php if ( $filter_placement && ($show_search_go || $show_search_reset) ) : ?>
					<span id="<?php echo $form_id; ?>_submitWarn" class="fc-mssg fc-note" style="display:none;"><?php echo JText::_('FLEXI_FILTERS_CHANGED_CLICK_TO_SUBMIT'); ?></span>
					<span class="fc_buttons">
						<?php if ($show_search_go) : ?>
						<button class="fc_button button_go" onclick="var form=document.getElementById('<?php echo $form_id; ?>'); adminFormPrepare(form, 2); return false;">
							<span class="fcbutton_go"><?php echo JText::_( $use_filters ? 'FLEXI_APPLY_FILTERING' : 'FLEXI_GO' ); ?></span>
						</button>
						<?php endif; ?>
						
						<?php if ($show_search_reset) : ?>
						<button class="fc_button button_reset" onclick="var form=document.getElementById('<?php echo $form_id; ?>'); adminFormClearFilters(form); adminFormPrepare(form, 1); return false;">
							<span class="fcbutton_reset"><?php echo JText::_( $use_filters ? 'FLEXI_REMOVE_FILTERING' : 'FLEXI_RESET' ); ?></span>
						</button>
						<?php endif; ?>
						
					</span>
					<?php endif; ?>
				
					<?php if ( $msg ) : ?><span class="fc-mssg fc-note"><?php echo $msg; ?></span><?php endif; ?>
				</span>
				
			</span>
			
		<?php endif; /* EOF search */ ?>
		
		
		<?php if ($use_filters): /* BOF filter */ ?>
			<?php
			// Prefix/Suffix texts
			$pretext = $params->get( 'filter_pretext', '' );
			$posttext = $params->get( 'filter_posttext', '' );
			
			// Open/Close tags
			$opentag = $params->get( 'filter_opentag', '' );
			$closetag = $params->get( 'filter_closetag', '' );
			?>
			
			<?php
			$n=0;
			$prepend_onchange = " adminFormPrepare(document.getElementById('".$form_id."'), 1); ";
			foreach ($filters as $filt) :
				if (empty($filt->html)) continue;
				
				// Support for old 3rd party filters, that include an auto-submit statement or include a fixed form name
				// These CUSTOM fields should be updated to have this auto-submit code removed fixed form name changed too
				
				// Compatibility HACK 1
				// These fields need to be have their onChange Event prepended with the FORM PREPARATION function call,
				// ... but if these filters change value after we 'prepare' form then we have an issue ...
				if ( preg_match('/onchange[ ]*=[ ]*([\'"])/i', $filt->html, $matches) && preg_match('/\.submit\(\)/', $filt->html, $matches) ) {
					$filt->html = preg_replace('/onchange[ ]*=[ ]*([\'"])/i', 'onchange=${1}'.$prepend_onchange, $filt->html);
				}
				
				// Compatibility HACK 2
				// These fields also need to have any 'adminForm' string present in their filter's HTML replaced with the name of our form
				$filter_html[$filt->id] = preg_replace('/([\'"])adminForm([\'"])/', '${1}'.$form_name.'${2}', $filt->html);
				
				$_filter_html  = $pretext;
				$_filter_html .= '<span class="'.$filter_container_class.(($n++)%2 ? ' fc_even': ' fc_odd').' fc_filter_id_'.$filt->id.'" >' ."\n";
				$_filter_html .= ($show_filter_labels==1 || ($show_filter_labels==0 && $filt->parameters->get('display_label_filter')==1))
					? ' <span class="fc_filter_label fc_label_field_'.$filt->id.'">' .$filt->label. '</span>' ."\n"  :  '';
				$_filter_html .= ' <span class="fc_filter_html fc_html_field_'.$filt->id.'">' .$filt->html. '</span>' ."\n";
				$_filter_html .= '</span>'."\n";
				$_filter_html .= $posttext;
				$filters_html[] = $_filter_html;
			endforeach;
			
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
			
			$buttons_added_already = $filter_placement && $use_search;
			?>
			
			<?php if ($show_search_go && !$buttons_added_already) : ?>
			<span class="fc_filter">
				<span id="<?php echo $form_id; ?>_submitWarn" class="fc-mssg fc-note" style="display:none;"><?php echo JText::_('FLEXI_FILTERS_CHANGED_CLICK_TO_SUBMIT'); ?></span>
				<span class="fc_buttons">
					<button class="fc_button button_go" onclick="var form=document.getElementById('<?php echo $form_id; ?>'); adminFormPrepare(form, 2); return false;">
						<span class="fcbutton_go"><?php echo JText::_( 'FLEXI_APPLY_FILTERING' ); ?></span>
					</button>
					
					<?php if ($show_search_reset && !$buttons_added_already) : ?>
					<button class="fc_button button_reset" onclick="var form=document.getElementById('<?php echo $form_id; ?>'); adminFormClearFilters(form); adminFormPrepare(form, 1); return false;">
						<span class="fcbutton_reset"><?php echo JText::_( 'FLEXI_REMOVE_FILTERING' ); ?></span>
					</button>
					<?php endif; ?>
					
				</span>
			</span>
			<?php endif; ?>
			
		<?php endif; /* EOF filter */ ?>
		
	</fieldset>
		
</div>
<?php endif; /* EOF search and filter block */ ?>
<?php

// Automatic submission
if ($filter_autosubmit) {
	$js = '
		jQuery(document).ready(function() {
			jQuery("#'.$form_id.' input:not(.fc_autosubmit_exclude), #'.$form_id.' select:not(.fc_autosubmit_exclude)").on("change", function() {
				var form=document.getElementById("'.$form_id.'");
				adminFormPrepare(form, 2);
			});
		});
	';
} else {
	$js = '
		jQuery(document).ready(function() {
			jQuery("#'.$form_id.' input:not(.fc_autosubmit_exclude), #'.$form_id.' select:not(.fc_autosubmit_exclude)").on("change", function() {
				var form=document.getElementById("'.$form_id.'");
				adminFormPrepare(form, 1);
			});
		});
	';
}

// Notify select2 fields to clear their values when reseting the form
$js .= '
		jQuery(document).ready(function() {
			jQuery("#'.$form_id.' .fc_button.button_reset").on("click", function() {
				jQuery("#'.$form_id.'_filter_box .use_select2_lib").select2("val", "");
			});
		});
	';
$document = JFactory::getDocument();
$document->addScriptDeclaration($js);
?>