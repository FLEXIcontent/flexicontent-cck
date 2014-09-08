<?php defined('_JEXEC') or die('Restricted access');

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
if (empty($this->contenttypes)) $autodisplayadvoptions = 0;

// Whether to show advanced options or hide them, initial behaviour depends on $autodisplayadvoptions, which is calculated above
$use_advsearch_options = JRequest::getInt('use_advsearch_options', $autodisplayadvoptions==2);

//$show_filtersop = $this->params->get('show_filtersop', 1);
//$default_filtersop = $this->params->get('default_filtersop', 'all');

$show_searchareas = $this->params->get( 'show_searchareas', 0 );
$type_class = isset($this->contenttypes[0]) ? 'contenttype_'.$this->contenttypes[0] : '';

$js ="";

if($autodisplayadvoptions) {
 $js .= '
	window.addEvent("domready", function() {
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

$infoimage = JHTML::image ( 'components/com_flexicontent/assets/images/information.png', '', ' style="float:left; margin: 0px 8px 0px 2px;" ' );
if(FLEXI_J30GE){
	JHtml::_('bootstrap.tooltip');
	$text_search_title_tip   = ' title="'.JHtml::tooltipText(trim(JText::_('FLEXI_TEXT_SEARCH'), ':'), JText::_('FLEXI_TEXT_SEARCH_INFO'), 0).'" ';
	$field_filters_title_tip = 'title="'.JHtml::tooltipText(trim(JText::_('FLEXI_FIELD_FILTERS'), ':'), JText::_('FLEXI_FIELD_FILTERS_INFO'), 0).'" ';
	$other_search_areas_title_tip = 'title="'.JHtml::tooltipText(trim(JText::_('FLEXI_SEARCH_ALSO_SEARCH_IN_AREAS'), ':'), JText::_('FLEXI_SEARCH_ALSO_SEARCH_IN_AREAS_TIP'), 0).'" ';
} else {
	$text_search_title_tip   = ' title="'.JText::_('FLEXI_TEXT_SEARCH').'::'.JText::_('FLEXI_TEXT_SEARCH_INFO').'" ';
	$field_filters_title_tip = ' title="'.JText::_('FLEXI_FIELD_FILTERS').'::'.JText::_('FLEXI_FIELD_FILTERS_INFO').'" ';
	$other_search_areas_title_tip = ' title="'.JText::_('FLEXI_SEARCH_ALSO_SEARCH_IN_AREAS').'::'.JText::_('FLEXI_SEARCH_ALSO_SEARCH_IN_AREAS_TIP').'" ';
}
$r = 0;
?>

<form action="<?php echo $this->action; ?>" method="POST" id="<?php echo $form_id; ?>" name="<?php echo $form_name; ?>" onsubmit="">
	
	<?php if ($this->params->get('canseltypes', 1) && isset($this->lists['contenttypes'])) : ?>
	<fieldset id='fc_contenttypes_set' class='fc_search_set'>
		<legend>
			<span class='<?php echo FLEXI_J30GE?'hasTooltip':'hasTip'; ?>' title='<?php echo FLEXI_J30GE?JHtml::tooltipText(trim(JText::_('FLEXI_SEARCH_CONTENT_TYPE'), ':'), JText::_('FLEXI_SEARCH_CONTENT_TYPE_TIP'), 0):JText::_('FLEXI_SEARCH_CONTENT_TYPE')."::".JText::_('FLEXI_SEARCH_CONTENT_TYPE_TIP'); ?>'><?php echo $infoimage; ?></span>
			<?php echo JText::_('FLEXI_SEARCH_CONTENT_TYPE'); ?>
		</legend>
		
		<table id="fc_textsearch_tbl" class="fc_search_tbl <?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" cellspacing="1">
		
			<tr id="fcsearch_contenttypes_row" class="fc_search_row_<?php echo (($r++)%2);?>">
				<?php if($this->params->get('show_type_label', 1)): ?>
				<td class='fc_search_label_cell' width="1%">
					<label for="contenttypes">
						<?php echo JText::_('FLEXI_SEARCH_CONTENT_TYPE'); ?>
					</label>
				</td>
				<td class="fc_search_option_cell">
					<span class="fc_filter_html">
						<?php echo $this->lists['contenttypes'];?>
					</span>
				</td>
				<?php else: ?>
				<td colspan="1" class="fc_search_option_cell">
					<span class="fc_filter_html">
						<?php echo $this->lists['contenttypes'];?>
					</span>
				</td>
				<?php endif; ?>
			</tr>
		</table>
	</fieldset>
	<?php endif; ?>
	
	<fieldset id='fc_textsearch_set' class='fc_search_set'>
		<legend>
			<span class="<?php echo FLEXI_J30GE?'hasTooltip':'hasTip'; ?>" <?php echo $text_search_title_tip;?> ><?php echo $infoimage; ?></span>
			<?php echo JText::_('FLEXI_TEXT_SEARCH'); ?>
		</legend>
		
		<table id="fc_textsearch_tbl" class="fc_search_tbl <?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" cellspacing="1">
			
			<tr class="fc_search_row_<?php echo (($r++)%2);?>">
				<td class='fc_search_label_cell' width="1%">
					<label for="search_searchword" class='<?php echo FLEXI_J30GE?'hasTooltip':'hasTip'; ?>' title='<?php  echo FLEXI_J30GE?JHtml::tooltipText(trim(JText::_('FLEXI_SEARCH_SEARCHWORDS'), ':'), JText::_('FLEXI_SEARCH_SEARCHWORDS_TIP'), 0):JText::_('FLEXI_SEARCH_SEARCHWORDS').'::'.JText::_('FLEXI_SEARCH_SEARCHWORDS_TIP'); ?>'>
						<?php echo JText::_('FLEXI_SEARCH_SEARCHWORDS'); ?>
					</label>
				</td>
				<td colspan="3" class="fc_search_option_cell" style="position:relative;">
					<?php
					$_ac_index = $txtmode ? 'fc_adv_complete' : 'fc_basic_complete';
					$text_search_class  = 'fc_text_filter';
					$text_search_class .= $search_autocomplete ? ($search_autocomplete==2 ? ' fc_index_complete_tlike '.$_ac_index : ' fc_index_complete_simple '.$_ac_index.' fc_label_internal') : ' fc_label_internal';
					$text_search_label = JText::_($show_search_label==2 ? 'FLEXI_TEXT_SEARCH' : 'FLEXI_TYPE_TO_LIST');
					?>
					<span class="fc_filter">
						<input type="<?php echo $search_autocomplete==2 ? 'hidden' : 'text'; ?>" class="<?php echo $text_search_class; ?>"
							data-fc_label_text="<?php echo $text_search_label; ?>" name="searchword" size="30" maxlength="120" 
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
						
						<span id="<?php echo $form_id; ?>_submitWarn" class="fc-mssg fc-note" style="display:none;"><?php echo JText::_('FLEXI_FILTERS_CHANGED_CLICK_TO_SUBMIT'); ?></span>
						
						<button class="fc_button button_go" onclick="var form=document.getElementById('<?php echo $form_id; ?>'); adminFormPrepare(form, 1);"><span class="fcbutton_go"><?php echo JText::_( 'FLEXI_GO' ); ?></span></button>
						
						<?php if ($autodisplayadvoptions) {
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
					<label class='<?php echo FLEXI_J30GE?'hasTooltip':'hasTip'; ?>' title='<?php echo FLEXI_J30GE?JHtml::tooltipText(trim(JText::_('FLEXI_SEARCH_KEYWORD_REQUIREMENT'), ':'), JText::_('FLEXI_SEARCH_KEYWORD_REQUIREMENT_TIP'), 0):JText::_('FLEXI_SEARCH_KEYWORD_REQUIREMENT').'::'.JText::_('FLEXI_SEARCH_KEYWORD_REQUIREMENT_TIP'); ?>'>
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
						<label for="txtflds" class='<?php echo FLEXI_J30GE?'hasTooltip':'hasTip'; ?>' title='<?php echo FLEXI_J30GE?JHtml::tooltipText(trim(JText::_('FLEXI_SEARCH_SEARCHWORDS_IN_FIELDS'), ':'), JText::_('FLEXI_SEARCH_SEARCHWORDS_IN_FIELDS_TIP'), 0):JText::_('FLEXI_SEARCH_SEARCHWORDS_IN_FIELDS').'::'.JText::_('FLEXI_SEARCH_SEARCHWORDS_IN_FIELDS_TIP'); ?>'>
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
			
		</table>
	
	</fieldset>
	
<?php if ($autodisplayadvoptions) : ?>
	
	<div id='fc_advsearch_options_set' >
	<!--fieldset id='fc_advsearch_options_set' class='fc_search_set'>
		<legend><?php echo JText::_('FLEXI_SEARCH_ADVANCED_SEARCH_OPTIONS'); ?></legend-->
		
		<?php if ( count($this->filters) > 0 ) : ?>
			<fieldset id='fc_fieldfilters_set' class='fc_search_set <?php echo $type_class; ?>'>
				<legend>
					<span class="<?php echo FLEXI_J30GE?'hasTooltip':'hasTip'; ?>" <?php echo $field_filters_title_tip;?> ><?php echo $infoimage; ?></span>
					<?php echo JText::_('FLEXI_FIELD_FILTERS')." ".JText::_('FLEXI_TO_FILTER_TEXT_SEARCH_RESULTS'); ?>
				</legend>
				
				<table id="fc_fieldfilters_tbl" class="fc_search_tbl <?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" cellspacing="1">		
				
				<?php /*if($show_operator = $this->params->get('show_filtersop', 1)) : ?>
					<tr class="fc_search_row_<?php echo (($r++)%2);?>">
						<td colspan="4" class="fc_search_option_cell">
							<label for="operator" class="<?php echo FLEXI_J30GE?'hasTooltip':'hasTip'; ?>" title='<?php echo FLEXI_J30GE?JHtml::tooltipText(trim(JText::_('FLEXI_SEARCH_FILTERS_REQUIRED'), ':'), JText::_('FLEXI_SEARCH_FILTERS_REQUIRED_TIP'), 0):JText::_('FLEXI_SEARCH_FILTERS_REQUIRED').'::'.JText::_('FLEXI_SEARCH_FILTERS_REQUIRED_TIP'); ?>'>
								<?php echo JText::_("FLEXI_SEARCH_FILTERS_REQUIRED"); ?>:
							</label>
							<span class="fc_filter_html">
								<?php echo $this->lists['filtersop']; ?>:
							</span>
						</td>
					</tr>
				<?php endif; */ ?>
				
				<?php
				$prepend_onchange = " adminFormPrepare(document.getElementById('".$form_id."'), 1); ";
				foreach($this->filters as $filt) {
					if (empty($filt->html)) continue;
					$label = JText::_($filt->label);
					$descr = JText::_($filt->description);
					?>
					<tr class="fc_search_row_<?php echo (($r++)%2);?>">
						<td class='fc_search_label_cell' valign='top'>
						<?php if ($descr) : ?>
							<label for="<?php echo $filt->name; ?>" class="<?php echo FLEXI_J30GE?'hasTooltip':'hasTip'; ?>" title="<?php echo FLEXI_J30GE?JHtml::tooltipText(trim($label, ':'), $descr, 0):$label.'::'.$descr; ?>">
								<?php echo JText::_($label); ?>
							</label>
						<?php else : ?>
							<label for="<?php echo $filt->name; ?>" class="<?php echo FLEXI_J30GE?'hasTooltip':'hasTip'; ?>" title="<?php echo FLEXI_J30GE?JHtml::tooltipText(trim(JText::_('FLEXI_SEARCH_MISSING_FIELD_DESCR'), ':'), JText::sprintf('FLEXI_SEARCH_MISSING_FIELD_DESCR_TIP', $label ), 0):JText::_('FLEXI_SEARCH_MISSING_FIELD_DESCR').'::'.JText::sprintf('FLEXI_SEARCH_MISSING_FIELD_DESCR_TIP', $label ); ?>">
								<?php echo $label; ?>
							</label>
						<?php endif; ?>
						</td>
						<td colspan="3" class="fc_search_option_cell">
							<?php
							if ( preg_match('/onchange[ ]*=[ ]*([\'"])/i', $filt->html, $matches) && preg_match('/\.submit\(\)/', $filt->html, $matches) ) {
								$filt->html = preg_replace('/onchange[ ]*=[ ]*([\'"])/i', 'onchange=${1}'.$prepend_onchange, $filt->html);
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
					<span class="<?php echo FLEXI_J30GE?'hasTooltip':'hasTip'; ?>" <?php echo $other_search_areas_title_tip;?> ><?php echo $infoimage; ?></span><?php echo JText::_('FLEXI_SEARCH_ALSO_SEARCH_IN_AREAS'); ?>
				</legend>
				
				<table id="fc_search_behavior_tbl" class="fc_search_tbl <?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" cellspacing="1">
					
					<tr class="fc_search_row_<?php echo (($r++)%2);?>">
						<td class='fc_search_label_cell' valign='top'>
							<label class="<?php echo FLEXI_J30GE?'hasTooltip':'hasTip'; ?>" title='<?php echo FLEXI_J30GE?JHtml::tooltipText(trim(JText::_('FLEXI_SEARCH_INCLUDE_AREAS'), ':'), JText::_('FLEXI_SEARCH_INCLUDE_AREAS_TIP'), 0):JText::_('FLEXI_SEARCH_INCLUDE_AREAS').'::'.JText::_('FLEXI_SEARCH_INCLUDE_AREAS_TIP'); ?>'>
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
							<label for="ordering" class="<?php echo FLEXI_J30GE?'hasTooltip':'hasTip'; ?>" title='<?php echo FLEXI_J30GE?JHtml::tooltipText(trim(JText::_('FLEXI_SEARCH_ORDERING'), ':'), JText::_('FLEXI_SEARCH_ORDERING_TIP'), 0):JText::_('FLEXI_SEARCH_ORDERING').'::'.JText::_('FLEXI_SEARCH_ORDERING_TIP'); ?>'>
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
			<span class="fc_limit_label <?php echo FLEXI_J30GE?'hasTooltip':'hasTip'; ?>" title="<?php echo FLEXI_J30GE?JHtml::tooltipText(trim(JText::_('FLEXI_PAGINATION'), ':'), JText::_('FLEXI_PAGINATION_INFO'), 0):JText::_('FLEXI_PAGINATION').'::'.JText::_('FLEXI_PAGINATION_INFO'); ?>">
				<span class="fc_limit_selector"><?php echo $this->lists['limit']; ?></span>
			</span>
		<?php endif; ?>
		
		<?php if ( @$this->lists['orderby'] ) : ?>
			<span class="fc_orderby_label <?php echo FLEXI_J30GE?'hasTooltip':'hasTip'; ?>" title="<?php echo FLEXI_J30GE?JHtml::tooltipText(trim(JText::_('FLEXI_ORDERBY'), ':'), JText::_('FLEXI_ORDERBY_INFO'), 0):JText::_('FLEXI_ORDERBY').'::'.JText::_('FLEXI_ORDERBY_INFO'); ?>">
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

<?php
// Automatic submission
$filter_autosubmit = 0;
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