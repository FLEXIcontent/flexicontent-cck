<?php defined('_JEXEC') or die('Restricted access');

$txtmode = $this->params->get('txtmode', 0);
$show_search_label = $this->params->get('show_search_label', 1);
$search_autocomplete = $this->params->get( 'search_autocomplete', 1 );
$show_searchphrase = $this->params->get('show_searchphrase', 1);
$default_searchphrase = $this->params->get('default_searchphrase', 'all');

$show_searchordering = $this->params->get('show_searchordering', 1);
$default_searchordering = $this->params->get('default_searchordering', 'newest');

$autodisplayadvoptions = $this->params->get('autodisplayadvoptions', 1);

//$show_filtersop = $this->params->get('show_filtersop', 1);
//$default_filtersop = $this->params->get('default_filtersop', 'all');

$show_searchareas = $this->params->get( 'show_searchareas', 0 );

$js ="";

if($autodisplayadvoptions) {
 $js .= '
	window.addEvent("domready", function() {
	  var status = {
	    "true": "open",
	    "false": "close"
	  };
	  
    
	  jQuery("#fcsearch_txtflds_row").css("position","relative").hide(0, function(){}).css("position","static");
	  
	  '. (($autodisplayadvoptions==1 && !JRequest::getInt('use_advsearch_options')) ? '' : 'jQuery("#fcsearch_txtflds_row").css("position","relative").toggle(500, function(){}).css("position","static");') .'
		
	  jQuery("#use_advsearch_options").click(function() {
	  
		  jQuery("#fcsearch_txtflds_row").css("position","relative").toggle(500, function(){}).css("position","static");
    });
  '
  .( $this->params->get('canseltypes', 1)!=2 ? '' : '
	  jQuery("#fcsearch_contenttypes_row").css("position","relative").hide(0, function(){}).css("position","static");
	  
	  '. (($autodisplayadvoptions==1 && !JRequest::getInt('use_advsearch_options')) ? '' : 'jQuery("#fcsearch_contenttypes_row").css("position","relative").toggle(500, function(){}).css("position","static");') .'
		
	  jQuery("#use_advsearch_options").click(function() {
	  
		  jQuery("#fcsearch_contenttypes_row").css("position","relative").toggle(500, function(){}).css("position","static");
    }); ').
  '
	  jQuery("#fc_advsearch_options_set").css("position","relative").hide(0, function(){}).css("position","static");
	  
	  '. (($autodisplayadvoptions==1 && !JRequest::getInt('use_advsearch_options')) ? '' : 'jQuery("#fc_advsearch_options_set").css("position","relative").toggle(500, function(){}).css("position","static");') .'
		
	  jQuery("#use_advsearch_options").click(function() {
	  
		  jQuery("#fc_advsearch_options_set").css("position","relative").toggle(500, function(){}).css("position","static");
    });
    
	});
	';
}

$this->document->addScriptDeclaration($js);

$infoimage = JHTML::image ( 'components/com_flexicontent/assets/images/information.png', '', ' style="float:left; margin: 0px 8px 0px 2px;" ' );
$text_search_title_tip   = ' title="'.JText::_('FLEXI_TEXT_SEARCH').'::'.JText::_('FLEXI_TEXT_SEARCH_INFO').'" ';
$field_filters_title_tip = ' title="'.JText::_('FLEXI_FIELD_FILTERS').'::'.JText::_('FLEXI_FIELD_FILTERS_INFO').'" ';
$other_search_areas_title_tip = ' title="'.JText::_('FLEXI_SEARCH_ALSO_SEARCH_IN_AREAS').'::'.JText::_('FLEXI_SEARCH_ALSO_SEARCH_IN_AREAS_TIP').'" ';

$r = 0;
?>

<form action="<?php echo $this->action; ?>" method="POST" id="searchForm" name="searchForm" onsubmit="">
	
	<fieldset id='fc_textsearch_set' class='fc_search_set'>
		<legend><span class="hasTip" <?php echo $text_search_title_tip;?> ><?php echo $infoimage; ?></span><?php echo JText::_('FLEXI_TEXT_SEARCH'); ?></legend>
		
		<table id="fc_textsearch_tbl" class="fc_search_tbl <?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" cellspacing="1">
			
			<tr class="fc_search_row_<?php echo (($r++)%2);?>">
				<td class='fc_search_label_cell' width="1%">
					<label for="search_searchword" class='hasTip' title='<?php echo JText::_('FLEXI_SEARCH_SEARCHWORDS'); ?>::<?php echo JText::_('FLEXI_SEARCH_SEARCHWORDS_TIP'); ?>'>
						<?php echo JText::_('FLEXI_SEARCH_SEARCHWORDS'); ?>:
					</label>
				</td>
				<td colspan="3" class="fc_search_option_cell" style="position:relative;">
					<?php
					$_ac_index = $txtmode ? 'fc_basic_complete' : 'fc_adv_complete';
					$text_search_class  = 'fc_text_filter';
					$text_search_class .= $search_autocomplete ? ($search_autocomplete==2 ? ' fc_index_complete_tlike '.$_ac_index : ' fc_index_complete_simple '.$_ac_index.' fc_label_internal') : ' fc_label_internal';
					$text_search_label = JText::_($show_search_label==2 ? 'FLEXI_TEXT_SEARCH' : 'FLEXI_TYPE_TO_LIST');
					?>
					<span class="fc_filter">
						<input type="<?php echo $search_autocomplete==2 ? 'hidden' : 'text'; ?>" class="<?php echo $text_search_class; ?>"
							fc_label_text="<?php echo $text_search_label; ?>" name="searchword" size="30" maxlength="120" 
							id="search_searchword" value="<?php echo $this->escape($this->searchword);?>" />

						<?php if ( $show_searchphrase ) echo $this->lists['searchphrase']; ?>
						
						<?php
						$ignoredwords = JRequest::getVar('ignoredwords');
						$shortwords = JRequest::getVar('shortwords');
						$shortwords_sanitize = JRequest::getVar('shortwords_sanitize');
						$shortwords .= $shortwords_sanitize ? ' '.$shortwords_sanitize : '';
						$min_word_len = JFactory::getApplication()->getUserState( JRequest::getVar('option').'.min_word_len', 0 );
						$msg = '';
						$msg .= $ignoredwords ? JText::_('FLEXI_WORDS_IGNORED_MISSING_COMMON').': <b>'.$ignoredwords.'</b>' : '';
						$msg .= $ignoredwords && $shortwords ? ' <br/> ' : '';
						$msg .= $shortwords ? JText::sprintf('FLEXI_WORDS_IGNORED_TOO_SHORT', $min_word_len) .': <b>'.$shortwords.'</b>' : '';
						?>
						<?php if ( $msg ) : ?><span class="fc-mssg fc-note"><?php echo $msg; ?></span><?php endif; ?>					
						
						<button class="fc_button button_go" onclick="var form=document.getElementById('searchForm'); adminFormPrepare(form);"><span class="fcbutton_go"><?php echo JText::_( 'FLEXI_GO' ); ?></span></button>
						
						<?php if ($autodisplayadvoptions) {
							$use_advsearch_options = JRequest::getInt('use_advsearch_options', 0);
							$checked_attr  = $use_advsearch_options ? 'checked=checked' : '';
							$checked_class = $use_advsearch_options ? 'highlight' : '';
							$use_advsearch_options_ff = '';
							$use_advsearch_options_ff .= '<label id="use_advsearch_options_lbl" class="flexi_radiotab rc5 '.$checked_class.'" style="float:none!important; display:inline-block!important; white-space:nowrap;" for="use_advsearch_options">';
							$use_advsearch_options_ff .= ' <input  href="javascript:;" onclick="fc_toggleClass(this.parentNode, \'highlight\');" id="use_advsearch_options" type="checkbox" name="use_advsearch_options" style="" value="1" '.$checked_attr.' />';
							$use_advsearch_options_ff .= ' &nbsp;'.JText::_('FLEXI_SEARCH_ADVANCED_OPTIONS');
							$use_advsearch_options_ff .= '</label>';
							echo $use_advsearch_options_ff;
						} ?>
					</span>
				</td>
			</tr>
			
			<?php /*if ( $show_searchphrase ) : ?>
			<tr class="fc_search_row_<?php echo (($r++)%2);?>">
				<td class='fc_search_label_cell'>
					<label class='hasTip' title='<?php echo JText::_('FLEXI_SEARCH_KEYWORD_REQUIREMENT'); ?>::<?php echo JText::_('FLEXI_SEARCH_KEYWORD_REQUIREMENT_TIP'); ?>'>
						<?php echo JText::_('FLEXI_SEARCH_KEYWORD_REQUIREMENT'); ?>:
					</label>
				</td>
				<td colspan="3" class="fc_search_option_cell">
					<span class="fc_filter_html">
						<?php echo $this->lists['searchphrase']; ?>
					</span>
				</td>
			</tr>
			<?php endif;*/ ?>

			<?php if ($this->params->get('canseltext', 1) && isset($this->lists['txtflds'])) : ?>
			
				<tr id="fcsearch_txtflds_row" class="fc_search_row_<?php echo (($r++)%2);?>">
					<td class='fc_search_label_cell' valign='top'>
						<label for="txtflds" class='hasTip' title='<?php echo JText::_('FLEXI_SEARCH_SEARCHWORDS_IN_FIELDS'); ?>::<?php echo JText::_('FLEXI_SEARCH_SEARCHWORDS_IN_FIELDS_TIP'); ?>'>
							<?php echo JText::_('FLEXI_SEARCH_SEARCHWORDS_IN_FIELDS'); ?>:
						</label>
					</td>
					<td colspan="3" class="fc_search_option_cell">
						<span class="fc_filter_html">
							<?php echo $this->lists['txtflds'];?>
						</span>
					</td>
				</tr>
				
			<?php endif; ?>
			
			<?php if ($this->params->get('canseltypes', 1) && isset($this->lists['contenttypes'])) : ?>
			
				<tr id="fcsearch_contenttypes_row" class="fc_search_row_<?php echo (($r++)%2);?>">
					<td class='fc_search_label_cell' valign='top'>
						<label for="contenttypes" class='hasTip' title='<?php echo JText::_('FLEXI_SEARCH_CONTENT_TYPE'); ?>::<?php echo JText::_('FLEXI_SEARCH_CONTENT_TYPE_TIP'); ?>'>
							<?php echo JText::_('FLEXI_SEARCH_CONTENT_TYPE'); ?>:
						</label>
					</td>
					<td colspan="3" class="fc_search_option_cell">
						<span class="fc_filter_html">
							<?php echo $this->lists['contenttypes'];?>
						</span>
					</td>
				</tr>
				
			<?php endif; ?>
			
		</table>
	
	</fieldset>
	
<?php if ($autodisplayadvoptions) : ?>
	
	<div id='fc_advsearch_options_set' >
	<!--fieldset id='fc_advsearch_options_set' class='fc_search_set'>
		<legend><?php echo JText::_('FLEXI_SEARCH_ADVANCED_SEARCH_OPTIONS'); ?></legend-->
		
		<?php if ( count($this->filters) > 0 ) : ?>
			<fieldset id='fc_fieldfilters_set' class='fc_search_set'>
				<legend><span class="hasTip" <?php echo $field_filters_title_tip;?> ><?php echo $infoimage; ?></span><?php echo JText::_('FLEXI_FIELD_FILTERS')." ".JText::_('FLEXI_TO_FILTER_TEXT_SEARCH_RESULTS'); ?></legend>
				
				<table id="fc_fieldfilters_tbl" class="fc_search_tbl <?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" cellspacing="1">		
				
				<?php /*if($show_operator = $this->params->get('show_filtersop', 1)) : ?>
					<tr class="fc_search_row_<?php echo (($r++)%2);?>">
						<td colspan="4" class="fc_search_option_cell">
							<label for="operator" class="hasTip" title='<?php echo JText::_('FLEXI_SEARCH_FILTERS_REQUIRED'); ?>::<?php echo JText::_('FLEXI_SEARCH_FILTERS_REQUIRED_TIP'); ?>'>
								<?php echo JText::_("FLEXI_SEARCH_FILTERS_REQUIRED"); ?>:
							</label>
							<span class="fc_filter_html">
								<?php echo $this->lists['filtersop']; ?>:
							</span>
						</td>
					</tr>
				<?php endif; */ ?>
				
				<?php foreach($this->filters as $filt) { ?>
					<?php if (empty($filt->html)) continue; ?>
					<tr class="fc_search_row_<?php echo (($r++)%2);?>">
						<td class='fc_search_label_cell' valign='top'>
						<?php if ($filt->description) : ?>
							<label for="<?php echo $filt->name; ?>" class="hasTip" title="<?php echo $filt->label; ?>::<?php echo $filt->description; ?>">
								<?php echo $filt->label; ?>:
							</label>
						<?php else : ?>
							<label for="<?php echo $filt->name; ?>" class="hasTip" title="<?php echo JText::_('FLEXI_SEARCH_MISSING_FIELD_DESCR'); ?>::<?php echo JText::sprintf('FLEXI_SEARCH_MISSING_FIELD_DESCR_TIP', $filt->label ); ?>">
								<?php echo $filt->label; ?>:
							</label>
						<?php endif; ?>
						</td>
						<td colspan="3" class="fc_search_option_cell">
							<?php
							// Form field that have form auto submit, need to be have their onChange Event prepended with the FORM PREPARATION function call
							if ( preg_match('/onchange[ ]*=[ ]*([\'"])/i', $filt->html, $matches) ) {
								if ( preg_match('/\.submit\(\)/', $filt->html, $matches) ) {
									// Autosubmit detected inside onChange event, prepend the event with form preparation function call
									$filt->html = preg_replace('/onchange[ ]*=[ ]*([\'"])/i', 'onchange=${1}adminFormPrepare(document.getElementById(\'searchForm\')); ', $filt->html);
								}
							}
							?>
							<span class="fc_filter_html">
								<?php echo $filt->html; ?>
							</span>
						</td>
					</tr>
					
				<?php } ?>
				
				</table>
				
			</fieldset>
			
		<?php endif; ?>

		<?php if ( $show_searchareas ) : ?>
			
			<fieldset id='fc_search_behavior_set' class='fc_search_set'>
				<legend>
					<span class="hasTip" <?php echo $other_search_areas_title_tip;?> ><?php echo $infoimage; ?></span><?php echo JText::_('FLEXI_SEARCH_ALSO_SEARCH_IN_AREAS'); ?>
				</legend>
				
				<table id="fc_search_behavior_tbl" class="fc_search_tbl <?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" cellspacing="1">
					
					<tr class="fc_search_row_<?php echo (($r++)%2);?>">
						<td class='fc_search_label_cell' valign='top'>
							<label class="hasTip" title='<?php echo JText::_('FLEXI_SEARCH_INCLUDE_AREAS'); ?>::<?php echo JText::_('FLEXI_SEARCH_INCLUDE_AREAS_TIP'); ?>'>
								<?php echo JText::_( 'FLEXI_SEARCH_INCLUDE_AREAS' );?> :
							</label>
						</td>
						<td colspan="3" class="fc_search_option_cell">
							<span class="fc_filter_html">
								<?php echo $this->lists['areas']; ?>
							</span>
						</td>
					</tr>	
					
				<?php if( $show_searchordering ) : ?>
					
					<tr class="fc_search_row_<?php echo (($r++)%2);?>">
						<td class='fc_search_label_cell' valign='top'>
							<label for="ordering" class="hasTip" title='<?php echo JText::_('FLEXI_SEARCH_ORDERING'); ?>::<?php echo JText::_('FLEXI_SEARCH_ORDERING_TIP'); ?>'>
								<?php echo JText::_( 'FLEXI_SEARCH_ORDERING' );?>:
							</label>
						</td>
						<td colspan="3" class="fc_search_option_cell">
							<span class="fc_filter_html">
								<?php echo $this->lists['ordering'];?>
							</span>
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
	
		<?php if ($this->params->get('show_item_total', 1)) : ?>
			<span class="fc_item_total_data">
				<?php if (@$this->resultsCounter || @$this->pageNav) echo @$this->resultsCounter ? $this->resultsCounter : $this->pageNav->getResultsCounter(); // custom Results Counter ?>
			</span>
		<?php endif; ?>
		
		<?php if ( @$this->lists['limit'] ) : ?>
			<span class="fc_limit_label hasTip" title="<?php echo JText::_('FLEXI_PAGINATION'); ?>::<?php echo JText::_('FLEXI_PAGINATION_INFO'); ?>">
				<span class="fc_limit_selector"><?php echo $this->lists['limit']; ?></span>
			</span>
		<?php endif; ?>
		
		<?php if ( @$this->lists['orderby'] ) : ?>
			<span class="fc_orderby_label hasTip" title="<?php echo JText::_('FLEXI_ORDERBY'); ?>::<?php echo JText::_('FLEXI_ORDERBY_INFO'); ?>">
				<span class="fc_orderby_selector"><?php echo $this->lists['orderby']; ?></span>
			</span>
		<?php endif; ?>
		
		<span class="fc_pages_counter">
			<small><?php if (@$this->pageNav) echo $this->pageNav->getPagesCounter(); ?></small>
		</span>
	
	</div>
	<!-- BOF items total-->


<?php /*<table class="searchintro<?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" cellspacing="1">
	<tr>
		<td>
			<?php echo $this->result; ?>
			<br />
		</td>
	</tr>
</table> */ ?>


<?php if( !$show_searchphrase ) : ?>
	<input type="hidden" name="searchphrase" value="<?php echo $default_searchphrase;?>" />
<?php endif; ?>

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
<input type="hidden" name="Itemid" value="<?php echo JRequest::getVar("Itemid");?>" />
</form>
