<?php defined('_JEXEC') or die('Restricted access');

$app = JFactory::getApplication();
$form_id = $this->form_id;
$form_name = $this->form_name;

$txtmode = $this->params->get('txtmode', 0);
$show_search_label = $this->params->get('show_search_label', 1);
$search_autocomplete = $this->params->get( 'search_autocomplete', 1 );
$show_searchphrase = $this->params->get('show_searchphrase', 1);
$default_searchphrase = $this->params->get('default_searchphrase', 'all');

$show_searchordering = $this->params->get('show_searchordering', 1);
$default_searchordering = $this->params->get('default_searchordering', 'newest');

// Whether to show advanced options,  (a) the filters, (b) the text search fields, which these depend on content types selected/configured
$autodisplayadvoptions = $this->params->get('autodisplayadvoptions', 1);
$autodisplayadvoptions = empty($this->contenttypes) && !count($this->filters)
	? 0
	: $autodisplayadvoptions;

// Whether to show advanced options or hide them, initial behaviour depends on $autodisplayadvoptions, which is calculated above
$use_advsearch_options = $app->input->get('use_advsearch_options', (int) ($autodisplayadvoptions==2), 'int');

//$show_filtersop = $this->params->get('show_filtersop', 1);
//$default_filtersop = $this->params->get('default_filtersop', 'all');

$show_searchareas = $this->params->get( 'show_searchareas', 0 );
$type_class = isset($this->contenttypes[0]) ? 'contenttype_'.$this->contenttypes[0] : '';
$tooltip_class = 'hasTooltip';

$js ="";

if ($autodisplayadvoptions)
{
 $js .= '
	jQuery(document).ready(function() {
	  var status = {
	    "true": "open",
	    "false": "close"
	  };
	  
    
	  jQuery("#fcsearch_txtflds_row").css("position","relative").hide(0, function(){}).css("position","static");
	  
	  '. (($autodisplayadvoptions==1 && !$use_advsearch_options) ? '' : 'jQuery("#fcsearch_txtflds_row").css("position","relative").toggle(500, function(){}).css("position","static");') .'
		
	  jQuery("#use_advsearch_options").click(function() {
	  
		  jQuery("#fcsearch_txtflds_row").css("position","relative").toggle(500, function(){}).css("position","static");
    });
  '
  .( 1 /*$this->params->get('canseltypes', 1)!=2*//*disable hiding*/ ? '' : '
	  jQuery("#fcsearch_contenttypes_row").css("position","relative").hide(0, function(){}).css("position","static");
	  
	  '. (($autodisplayadvoptions==1 && !$use_advsearch_options) ? '' : 'jQuery("#fcsearch_contenttypes_row").css("position","relative").toggle(500, function(){}).css("position","static");') .'
		
	  jQuery("#use_advsearch_options").click(function() {
	  
		  jQuery("#fcsearch_contenttypes_row").css("position","relative").toggle(500, function(){}).css("position","static");
    }); ').
  '
	  jQuery("#fc_advsearch_options_set").css("position","relative").hide(0, function(){}).css("position","static");
	  
	  '. (($autodisplayadvoptions==1 && !$use_advsearch_options) ? '' : 'jQuery("#fc_advsearch_options_set").css("position","relative").toggle(500, function(){}).css("position","static");') .'
		
	  jQuery("#use_advsearch_options").click(function() {
	  
		  jQuery("#fc_advsearch_options_set").css("position","relative").toggle(500, function(){}).css("position","static");
    });
    
	});
	';
}

$this->document->addScriptDeclaration($js);

$infoimage = JHtml::image ( 'components/com_flexicontent/assets/images/information.png', '', ' style="float:left; margin: 0px 8px 0px 2px;" ' );
$text_search_title_tip   = ' title="'.flexicontent_html::getToolTip('FLEXI_TEXT_SEARCH', 'FLEXI_TEXT_SEARCH_INFO', 1).'" ';
$field_filters_title_tip = ' title="'.flexicontent_html::getToolTip('FLEXI_FIELD_FILTERS', 'FLEXI_FIELD_FILTERS_INFO', 1).'" ';
$other_search_areas_title_tip = ' title="'.flexicontent_html::getToolTip('FLEXI_SEARCH_ALSO_SEARCH_IN_AREAS', 'FLEXI_SEARCH_ALSO_SEARCH_IN_AREAS_TIP', 1).'" ';

$r = 0;
?>

<form action="<?php echo $this->action; ?>" method="POST" id="<?php echo $form_id; ?>" name="<?php echo $form_name; ?>" onsubmit="">
	
	<?php if ($this->params->get('canseltypes', 1) && isset($this->lists['contenttypes'])) : ?>
	<fieldset id="fc_contenttypes_set" class="fc_search_set">
		<legend>
			<span class="fc_legend_text <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_SEARCH_CONTENT_TYPE', 'FLEXI_SEARCH_CONTENT_TYPE_TIP', 1); ?>">
				<?php /*echo $infoimage;*/ ?>
				<span><?php echo JText::_('FLEXI_SEARCH_CONTENT_TYPE'); ?></span>
			</span>
		</legend>
		
		<table id="fc_textsearch_tbl" class="fc_search_tbl <?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" >
		
			<tr id="fcsearch_contenttypes_row" class="fc_search_row_<?php echo (($r++)%2);?>">
				<?php if($this->params->get('show_type_label', 1)): ?>
				<td class="fc_search_label_cell">
					<label for="contenttypes" class="label">
						<?php echo JText::_('FLEXI_SEARCH_CONTENT_TYPE'); ?>
					</label>
				</td>
				<td class="fc_search_option_cell">
					<div class="fc_filter_html">
						<?php echo $this->lists['contenttypes'];?>
					</div>
				</td>
				<?php else: ?>
				<td class="fc_search_option_cell">
					<div class="fc_filter_html">
						<?php echo $this->lists['contenttypes'];?>
					</div>
				</td>
				<?php endif; ?>
			</tr>
		</table>
	</fieldset>
	<?php endif; ?>
	
	<fieldset id="fc_textsearch_set" class="fc_search_set">
		<legend>
			<span class="fc_legend_text <?php echo $tooltip_class; ?>" <?php echo $text_search_title_tip;?> >
				<?php /*echo $infoimage;*/ ?>
				<span><?php echo JText::_('FLEXI_TEXT_SEARCH'); ?></span>
			</span>
		</legend>
		
		<table id="fc_textsearch_tbl" class="fc_search_tbl <?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" >
			
			<tr class="fc_search_row_<?php echo (($r++)%2);?>">
				<td class="fc_search_label_cell">
					<label for="search_searchword" class="label <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_SEARCH_SEARCHWORDS', 'FLEXI_SEARCH_SEARCHWORDS_TIP', 1); ?>">
						<?php echo JText::_('FLEXI_SEARCH_SEARCHWORDS'); ?>
					</label>
				</td>
				<td class="fc_search_option_cell" style="position:relative;">
					<?php
					$append_buttons = true;
					
					$_ac_index = $txtmode ? 'fc_adv_complete' : 'fc_basic_complete';
					$text_search_class  = !$append_buttons ? 'fc_text_filter' : '';
					$_label_internal = '';//'fc_label_internal';  // data-fc_label_text="..."
					$text_search_class .= $search_autocomplete ? ($search_autocomplete==2 ? ' fc_index_complete_tlike '.$_ac_index : ' fc_index_complete_simple '.$_ac_index.' '.$_label_internal) : ' '.$_label_internal;
					$text_search_prompt = htmlspecialchars(JText::_($show_search_label==2 ? 'FLEXI_TEXT_SEARCH' : 'FLEXI_TYPE_TO_LIST'), ENT_QUOTES, 'UTF-8');
					?>
					<div class="fc_filter_html">
						<?php echo $append_buttons ? '<span class="btn-wrapper input-append">' : ''; ?>
							<input type="<?php echo $search_autocomplete==2 ? 'hidden' : 'text'; ?>" class="<?php echo $text_search_class; ?>"
								placeholder="<?php echo $text_search_prompt; ?>" name="q" size="30" maxlength="120" 
								id="search_searchword" value="<?php echo $this->escape($this->searchword);?>" />
							
							<?php $button_classes = FLEXI_J30GE ? ' btn btn-success' : ' fc_button fcsimple'; ?>
							<button class="<?php echo $button_classes; ?> button_go" onclick="var form=document.getElementById('<?php echo $form_id; ?>'); adminFormPrepare(form, 1);">
								<span class="icon-search icon-white"></span><?php echo JText::_( 'FLEXI_GO' ); ?>
							</button>
							
						<?php echo $append_buttons ? '</span>' : ''; ?>
						
						<?php if ($autodisplayadvoptions)
						{
							$checked_attr  = $use_advsearch_options ? 'checked=checked' : '';
							$checked_class = $use_advsearch_options ? 'btn-primary' : '';
							echo '
								<input type="checkbox" id="use_advsearch_options" name="use_advsearch_options" value="1" '.$checked_attr.' onclick="jQuery(this).next().toggleClass(\'btn-primary\');" />
								<label id="use_advsearch_options_lbl" class="btn '.$checked_class.' hasTooltip" for="use_advsearch_options" title="'.JText::_('FLEXI_SEARCH_ADVANCED_OPTIONS').'">
									<span class="icon-list"></span>' . JText::_('FLEXI_SEARCH_ADVANCED') . '
								</label>
							';
						} ?>
						
						<?php echo $this->lists['searchphrase']; ?>
						
						<?php
						$ignoredwords = $app->input->get('ignoredwords', '', 'string');
						$shortwords = $app->input->get('shortwords', '', 'string');
						$shortwords_sanitize = $app->input->get('shortwords_sanitize', '', 'string');
						$shortwords .= $shortwords_sanitize ? ' '.$shortwords_sanitize : '';
						$min_word_len = $app->getUserState( $app->input->get('option', '', 'cmd').'.min_word_len', 0 );
						$msg = '';
						$msg .= $ignoredwords ? JText::_('FLEXI_WORDS_IGNORED_MISSING_COMMON').': <b>'.$ignoredwords.'</b>' : '';
						$msg .= $ignoredwords && $shortwords ? ' <br/> ' : '';
						$msg .= $shortwords ? JText::sprintf('FLEXI_WORDS_IGNORED_TOO_SHORT', $min_word_len) .': <b>'.$shortwords.'</b>' : '';
						?>
						<?php if ( $msg ) : ?><span class="fc-mssg fc-note"><?php echo $msg; ?></span><?php endif; ?>
						
						<span id="<?php echo $form_id; ?>_submitWarn" class="fc-mssg fc-note" style="display:none;"><?php echo JText::_('FLEXI_FILTERS_CHANGED_CLICK_TO_SUBMIT'); ?></span>
					</div>
				</td>
			</tr>
			
			<?php /*if ( $show_searchphrase ) : ?>
			<tr class="fc_search_row_<?php echo (($r++)%2);?>">
				<td class="fc_search_label_cell">
					<label class="label <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_SEARCH_KEYWORD_REQUIREMENT', 'FLEXI_SEARCH_KEYWORD_REQUIREMENT_TIP', 1); ?>">
						<?php echo JText::_('FLEXI_SEARCH_KEYWORD_REQUIREMENT'); ?>:
					</label>
				</td>
				<td class="fc_search_option_cell">
					<div class="fc_filter_html">
						<?php echo $this->lists['searchphrase']; ?>
					</div>
				</td>
			</tr>
			<?php endif;*/ ?>

			<?php if ($this->params->get('canseltext', 1) && isset($this->lists['txtflds'])) : ?>
			
				<tr id="fcsearch_txtflds_row" class="fc_search_row_<?php echo (($r++)%2);?>">
					<td class="fc_search_label_cell">
						<label for="txtflds" class="label <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_SEARCH_SEARCHWORDS_IN_FIELDS', 'FLEXI_SEARCH_SEARCHWORDS_IN_FIELDS_TIP', 1); ?>">
							<?php echo JText::_('FLEXI_SEARCH_SEARCHWORDS_IN_FIELDS'); ?>:
						</label>
					</td>
					<td class="fc_search_option_cell">
						<div class="fc_filter_html">
							<?php echo $this->lists['txtflds'];?>
						</div>
					</td>
				</tr>
				
			<?php endif; ?>
			
		</table>
	
	</fieldset>
	
<?php if ($autodisplayadvoptions) : ?>
	
	<div id="fc_advsearch_options_set" >
		
		<?php if ( count($this->filters) > 0 || $this->type_based_search ) : ?>
			<fieldset id="fc_fieldfilters_set" class="fc_search_set <?php echo $type_class; ?>">
				<legend>
					<span class="fc_legend_text <?php echo $tooltip_class; ?>" <?php echo $field_filters_title_tip;?> >
						<?php /*echo $infoimage;*/ ?>
						<span><?php echo JText::_('FLEXI_FIELD_FILTERS')/*." ".JText::_('FLEXI_TO_FILTER_TEXT_SEARCH_RESULTS')*/; ?></span>
					</span>
				</legend>
				
			<?php	
			$filter_messages = $app->input->get('filter_messages', array(), 'array');
			$msg = '';
			$msg = implode(' <br/> ', $filter_messages);
			if ( $msg ) :
				?><div class="fcclear"></div><div class="fc-mssg fc-note"><?php echo $msg; ?></div><?php
			endif;
			?>
			<div class="fcclear"></div>
			
				<table id="fc_fieldfilters_tbl" class="fc_search_tbl <?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" >
					
				<?php if ( !count($this->filters) && $this->type_based_search ) : ?>
					<tr><td><div class="alert alert-info"><?php echo JText::_('FLEXI_SELECT_CONTENT_TYPE_BEFORE_USING_FILTERS'); ?></div></td></tr>
				<?php endif; ?>
				
				<?php /*if($show_operator = $this->params->get('show_filtersop', 1)) : ?>
					<tr class="fc_search_row_<?php echo (($r++)%2);?>">
						<td colspan="2" class="fc_search_option_cell">
							<label for="operator" class="label <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_SEARCH_FILTERS_REQUIRED', 'FLEXI_SEARCH_FILTERS_REQUIRED_TIP', 1); ?>">
								<?php echo JText::_("FLEXI_SEARCH_FILTERS_REQUIRED"); ?>:
							</label>
							<div class="fc_filter_html">
								<?php echo $this->lists['filtersop']; ?>:
							</div>
						</td>
					</tr>
				<?php endif; */ ?>
				
				<?php
				$prepend_onchange = ''; //" adminFormPrepare(document.getElementById('".$form_id."'), 1); ";
				foreach($this->filters as $filt) {
					if (empty($filt->html)) continue;
					$label = JText::_($filt->label);
					$descr = JText::_($filt->description);
					?>
					<tr class="fc_search_row_<?php echo (($r++)%2);?>">
						<td class="fc_search_label_cell">
						<?php if ($descr) : ?>
							<label for="filter_<?php echo $filt->id; ?>" class="label <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip($label, $descr, 0); ?>">
								<?php echo $label; ?>
							</label>
						<?php else : ?>
							<label for="filter_<?php echo $filt->id; ?>" class="label <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip(JText::_('FLEXI_SEARCH_MISSING_FIELD_DESCR'), JText::sprintf('FLEXI_SEARCH_MISSING_FIELD_DESCR_TIP', $label), 0); ?>">
								<?php echo $label; ?>
							</label>
						<?php endif; ?>
						</td>
						<td class="fc_search_option_cell">
							<?php
							if ( preg_match('/onchange[ ]*=[ ]*([\'"])/i', $filt->html, $matches) && preg_match('/\.submit\(\)/', $filt->html, $matches) ) {
								$filt->html = preg_replace('/onchange[ ]*=[ ]*([\'"])/i', 'onchange=${1}'.$prepend_onchange, $filt->html);
							}
							?>
							<div class="fc_filter_html">
								<?php echo $filt->html; ?>
							</div>
						</td>
					</tr>
					
				<?php } ?>
				
				</table>
				
			</fieldset>
			
		<?php endif; ?>

		<?php if ( $show_searchareas ) : ?>
			
			<fieldset id="fc_search_behavior_set" class="fc_search_set">
				<legend>
					<span class="fc_legend_text <?php echo $tooltip_class; ?>" <?php echo $other_search_areas_title_tip;?> >
						<?php /*echo $infoimage;*/ ?>
						<span><?php echo JText::_('FLEXI_SEARCH_ALSO_SEARCH_IN_AREAS'); ?></span>
					</span>
				</legend>
				
				<table id="fc_search_behavior_tbl" class="fc_search_tbl <?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" >
					
					<tr class="fc_search_row_<?php echo (($r++)%2);?>">
						<td class="fc_search_label_cell">
							<label class="label <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_SEARCH_INCLUDE_AREAS', 'FLEXI_SEARCH_INCLUDE_AREAS_TIP', 1); ?>">
								<?php echo JText::_( 'FLEXI_SEARCH_INCLUDE_AREAS' );?> :
							</label>
						</td>
						<td class="fc_search_option_cell">
							<div class="fc_filter_html">
								<?php echo $this->lists['areas']; ?>
							</div>
						</td>
					</tr>
					
				<?php if( $show_searchordering ) : ?>
					
					<tr class="fc_search_row_<?php echo (($r++)%2);?>">
						<td class="fc_search_label_cell">
							<label for="ordering" class="label <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_SEARCH_ORDERING', 'FLEXI_SEARCH_ORDERING_TIP', 1); ?>">
								<?php echo JText::_( 'FLEXI_SEARCH_ORDERING' );?>:
							</label>
						</td>
						<td class="fc_search_option_cell">
							<div class="fc_filter_html">
								<?php echo $this->lists['ordering'];?>
							</div>
						</td>
					</tr>
					
				<?php endif; ?>
				
				</table>
				
			</fieldset>
			
		<?php endif; ?>
		
	<!--/fieldset-->
	</div>
	
<?php endif; /* END OF IF autodisplayadvoptions */ ?>


	<!-- BOF items total-->
	<div id="item_total" class="item_total group">
	
		<?php if ($this->params->get('show_item_total', 1) && count($this->results)) : ?>
			<span class="fc_item_total_data">
				<?php if (@$this->resultsCounter || @$this->pageNav) echo @$this->resultsCounter ? $this->resultsCounter : $this->pageNav->getResultsCounter(); // custom Results Counter ?>
			</span>
		<?php endif; ?>
		
		<?php if ( $this->lists['limit'] ) : ?>
			<div class="fc_limit_box <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_PAGINATION', 'FLEXI_PAGINATION_INFO', 1); ?>">
				<div class="fc_limit_selector"><?php echo $this->lists['limit']; ?></div>
			</div>
		<?php endif; ?>
		
		<?php if ( $this->lists['orderby'] ) : ?>
			<div class="fc_orderby_box <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_ORDERBY', 'FLEXI_ORDERBY_INFO', 1); ?>">
				<?php if ($this->lists['orderby_2nd']) echo '<div class="label fc_orderby_level_lbl">1</div>'; ?><div class="fc_orderby_selector"><?php echo $this->lists['orderby']; ?></div>
			</div>
		<?php endif; ?>
		
		<?php if ( $this->lists['orderby_2nd'] ) : ?>
			<div class="fc_orderby_box fc_2nd_level <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_ORDERBY_2ND', 'FLEXI_ORDERBY_INFO_2ND', 1); ?>">
				<div class="label fc_orderby_level_lbl">2</div><div class="fc_orderby_selector"><?php echo $this->lists['orderby_2nd']; ?></div>
			</div>
		<?php endif; ?>
		
		<?php if (@$this->pageNav) : ?>
		<span class="fc_pages_counter">
			<span class="label"><?php echo $this->pageNav->getPagesCounter(); ?></span>
		</span>
		<?php endif; ?>
	
	</div>
	<!-- BOF items total-->


<?php /*<table class="searchintro<?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" >
	<tr>
		<td>
			<?php echo $this->result; ?>
			<br />
		</td>
	</tr>
</table> */ ?>

<?php /* Disabled no need to add 'default_searchphrase' to the form/URL since we will get proper default from menu item */ ?>
<?php /*if( !$show_searchphrase ) : ?>
	<input type="hidden" name="p" value="<?php echo $default_searchphrase;?>" />
<?php endif;*/ ?>

<?php /*if( $autodisplayadvoptions && !$show_filtersop ) : ?>
	<input type="hidden" name="filtersop" value="<?php echo $default_filtersop;?>" />
<?php endif;*/ ?>

<?php if( !$autodisplayadvoptions || !$show_searchareas ) : ?>
	<input type="hidden" name="areas[]" value="flexicontent" id="area_flexicontent" />
<?php endif; ?>

<?php if( !$show_searchordering ) : ?>
	<input type="hidden" name="ordering" value="<?php echo $default_searchordering;?>" />
<?php endif; ?>

<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="view" value="search" />
<input type="hidden" name="task" value="search" />
<input type="hidden" name="Itemid" value="<?php echo $app->input->get('Itemid', '', 'int');?>" />
</form>

<?php
// Automatic submission
$filter_autosubmit = 0;
if ($filter_autosubmit) {
	$js = '
		jQuery(document).ready(function() {
			var form=document.getElementById("'.$form_id.'");
			jQuery(form.elements).filter("input:not(.fc_autosubmit_exclude), select:not(.fc_autosubmit_exclude)").on("change", function() {
				adminFormPrepare(form, 2);
			});
			jQuery(form).attr("data-fc-autosubmit", "2");
		});
	';
} else {
	$js = '
		jQuery(document).ready(function() {
			var form=document.getElementById("'.$form_id.'");
			jQuery(form.elements).filter("input:not(.fc_autosubmit_exclude), select:not(.fc_autosubmit_exclude)").on("change", function() {
				adminFormPrepare(form, 1);
			});
			jQuery(form).attr("data-fc-autosubmit", "1");
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