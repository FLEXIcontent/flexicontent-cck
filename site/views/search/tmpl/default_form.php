<?php defined('_JEXEC') or die('Restricted access');

use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\HTML\HTMLHelper;

$app = Factory::getApplication();
$form_id = $this->form_id;
$form_name = $this->form_name;

$txtmode = $this->params->get('txtmode', 0);
$show_search_label = $this->params->get('show_search_label', 1);
$search_autocomplete = $this->params->get( 'search_autocomplete', 1 );
$show_searchphrase = $this->params->get('show_searchphrase', 1);
$default_searchphrase = $this->params->get('default_searchphrase', 'all');

$show_searchordering = $this->params->get('show_searchordering', 1);
$default_searchordering = $this->params->get('default_searchordering', 'newest');

$disp_slide_filter = $this->params->get('disp_slide_filter', 0);
$form_placement = (int) $this->params->get('form_placement', 0);
$buttons_position = (int) $this->params->get('buttons_position', 0);//1 after search 0 before advanced search
$append_buttons =  (int) $this->params->get('append_buttons', 0);
$show_search_reset = $this->params->get('show_search_reset', 1);
$flexi_button_class_reset =  ($this->params->get('flexi_button_class_reset','') != '-1')  ?
	$this->params->get('flexi_button_class_reset', 'btn')   :
	$this->params->get('flexi_button_class_reset_custom', 'btn')  ;
$flexi_button_class_go =  ($this->params->get('flexi_button_class_go' ,'') != '-1')  ?
	$this->params->get('flexi_button_class_go', 'btn btn-success')   :
	$this->params->get('flexi_button_class_go_custom', 'btn btn-success')  ;

if ($form_placement)
{
	$form_placement_class = $form_placement ? 'col-search span3 col-md-3' : 'top-search';
	$form_placement_style = $form_placement === 1 ? 'float: left; margin: 0 0 1rem 0;' : 'float: right; margin: 0 0 1rem 0;';
}
else
{
	$form_placement_class = '';
	$form_placement_style = '';
}


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

$infoimage = HTMLHelper::image ( 'components/com_flexicontent/assets/images/information.png', '', ' style="float:left; margin: 0px 8px 0px 2px;" ' );
$text_search_title_tip   = ' title="'.flexicontent_html::getToolTip('FLEXI_TEXT_SEARCH', 'FLEXI_TEXT_SEARCH_INFO', 1).'" ';
$field_filters_title_tip = ' title="'.flexicontent_html::getToolTip('FLEXI_FIELD_FILTERS', 'FLEXI_FIELD_FILTERS_INFO', 1).'" ';
$other_search_areas_title_tip = ' title="'.flexicontent_html::getToolTip('FLEXI_SEARCH_ALSO_SEARCH_IN_AREAS', 'FLEXI_SEARCH_ALSO_SEARCH_IN_AREAS_TIP', 1).'" ';

$r = 0;


/**
 * Filters in slider
 */
$jcookie = Factory::getApplication()->input->cookie;
$cookie_name = 'fc_active_TabSlideFilter';

if ($disp_slide_filter)
{
	$ff_slider_tagid = 'menu-sliders-filter';
	$active_slides = $jcookie->get($cookie_name, '{}', 'string');

	try
	{
		$active_slides = json_decode($active_slides);
	}
	catch (Exception $e)
	{
		$jcookie->set($cookie_name, '{}', time()+60*60*24*(365*5), JUri::base(true), '');
	}

	$last_active_slide = isset($active_slides->$ff_slider_tagid) ? $active_slides->$ff_slider_tagid : null;
}
?>

<div class="fcclear"></div>

<form
	action="<?php echo $this->action; ?>" method="POST"
	id="<?php echo $form_id; ?>" name="<?php echo $form_name; ?>" onsubmit=""
	class="<?php echo $form_placement_class;?>" style="<?php echo $form_placement_style;?>"
>
	
	<?php if ($this->params->get('canseltypes', 1) && isset($this->lists['contenttypes'])) : ?>
	<fieldset id="fc_contenttypes_set" class="fc_search_set">
		<legend>
			<span class="fc_legend_text <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_SEARCH_CONTENT_TYPE', 'FLEXI_SEARCH_CONTENT_TYPE_TIP', 1); ?>">
				<?php /*echo $infoimage;*/ ?>
				<span><?php echo Text::_('FLEXI_SEARCH_CONTENT_TYPE'); ?></span>
			</span>
		</legend>
		
		<div id="fc_textsearch_tbl" class="fc_search_tbl <?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" >
		
			<div id="fcsearch_contenttypes_row" class="fc_search_row_<?php echo (($r++)%2);?>">
				<?php if($this->params->get('show_type_label', 1)): ?>
				<div class="fc_search_label_cell">
					<label for="contenttypes" class="label_fcflt">
						<?php echo Text::_('FLEXI_SEARCH_CONTENT_TYPE'); ?>
					</label>
				</div>
				<div class="fc_search_option_cell">
					<div class="fc_filter_html">
						<?php echo $this->lists['contenttypes'];?>
					</div>
				</div>
				<?php else: ?>
				<div class="fc_search_option_cell">
					<div class="fc_filter_html">
						<?php echo $this->lists['contenttypes'];?>
					</div>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</fieldset>
	<?php endif; ?>
	
	<fieldset id="fc_textsearch_set" class="fc_search_set">
		<legend>
			<span class="fc_legend_text <?php echo $tooltip_class; ?>" <?php echo $text_search_title_tip;?> >
				<?php /*echo $infoimage;*/ ?>
				<span><?php echo Text::_('FLEXI_TEXT_SEARCH'); ?></span>
			</span>
		</legend>
		
		<div id="fc_textsearch_tbl" class="fc_search_tbl <?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" >
			
			<div class="fc_search_row_<?php echo (($r++)%2);?>">
				<div class="fc_search_label_cell">
					<label for="search_searchword" class="label_fcflt <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_SEARCH_SEARCHWORDS', 'FLEXI_SEARCH_SEARCHWORDS_TIP', 1); ?>">
						<?php echo Text::_('FLEXI_SEARCH_SEARCHWORDS'); ?>
					</label>
				</div>
				<div class="fc_search_option_cell" style="position:relative;">
					<?php
					$append_buttons = true;
					
					$_ac_index = $txtmode ? 'fc_adv_complete' : 'fc_basic_complete';
					$text_search_class  = !$append_buttons ? 'fc_text_filter' : '';
					$_label_internal = '';//'fc_label_internal';  // data-fc_label_text="..."
					$text_search_class .= $search_autocomplete ? ($search_autocomplete==2 ? ' fc_index_complete_tlike '.$_ac_index : ' fc_index_complete_simple '.$_ac_index.' '.$_label_internal) : ' '.$_label_internal;
					$text_search_prompt = htmlspecialchars(Text::_($show_search_label==2 ? 'FLEXI_TEXT_SEARCH' : 'FLEXI_TYPE_TO_LIST'), ENT_QUOTES, 'UTF-8');
					?>
					<div class="fc_filter_html">
						<?php 
                      $append_button_classes = FLEXI_J30GE ? 'input-group' : ' input-append';
                      echo $append_buttons ? '<span class="btn-wrapper '.$append_button_classes.'">' : ''; ?>
							<input type="<?php echo $search_autocomplete==2 ? 'hidden' : 'text'; ?>" class="<?php echo $text_search_class; ?>"
								data-txt_ac_lang="<?php echo Factory::getLanguage()->getTag(); ?>"
								placeholder="<?php echo $text_search_prompt; ?>" name="q" size="30" maxlength="120" 
								id="search_searchword" value="<?php echo $this->escape($this->searchword);?>" style="" />
							
							<?php 
							if ($buttons_position) : ?>
                      <?php if ($form_placement != 0) : ?>
						<div class="btn-group"> 
                        <?php endif; ?>
								<button class="<?php echo $flexi_button_class_go; ?>" onclick="var form=document.getElementById('<?php echo $form_id; ?>'); adminFormPrepare(form, 1);">
									<span class="icon-search icon-white"></span><?php echo Text::_( 'FLEXI_GO' ); ?>
								</button>
                      <?php if ($show_search_reset) : ?>
							<button class="<?php echo $flexi_button_class_reset; ?>" onclick="var form=document.getElementById('<?php echo $form_id; ?>'); adminFormClearFilters(form); adminFormPrepare(form, 2); return false;" title="<?php echo Text::_( 'FLEXI_REMOVE_FILTERING' ); ?>">
								<i class="icon-remove"></i><?php echo Text::_( 'FLEXI_RESET' ); ?>
							</button>
						<?php endif; ?>
                        <?php if ($form_placement != 0) : ?>
                        </div>
                      <?php endif; ?>
                      
							<?php endif; ?>
							
						<?php echo $append_buttons ? '</span>' : ''; ?>
						
						<?php if ($autodisplayadvoptions)
						{
							$checked_attr  = $use_advsearch_options ? 'checked=checked' : '';
							$checked_class = $use_advsearch_options ? 'btn-primary' : '';
							echo '
								<input type="checkbox" id="use_advsearch_options" name="use_advsearch_options" value="1" '.$checked_attr.' onclick="jQuery(this).next().toggleClass(\'btn-primary\');" style="display:none"/>
								<label id="use_advsearch_options_lbl" class="btn '.$checked_class.' hasTooltip" for="use_advsearch_options" title="'.Text::_('FLEXI_SEARCH_ADVANCED_OPTIONS').'">
									<span class="icon-list"></span>' . Text::_('FLEXI_SEARCH_ADVANCED') . '
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
						$msg .= $ignoredwords ? Text::_('FLEXI_WORDS_IGNORED_MISSING_COMMON').': <b>'.$ignoredwords.'</b>' : '';
						$msg .= $ignoredwords && $shortwords ? ' <br/> ' : '';
						$msg .= $shortwords ? Text::sprintf('FLEXI_WORDS_IGNORED_TOO_SHORT', $min_word_len) .': <b>'.$shortwords.'</b>' : '';
						?>
						<?php if ( $msg ) : ?><span class="fc-mssg fc-note"><?php echo $msg; ?></span><?php endif; ?>
						
						<span id="<?php echo $form_id; ?>_submitWarn" class="fc-mssg fc-note" style="display:none;"><?php echo Text::_('FLEXI_FILTERS_CHANGED_CLICK_TO_SUBMIT'); ?></span>
					</div>
				</div>
			</div>
			
			<?php /*if ( $show_searchphrase ) : ?>
			<tr class="fc_search_row_<?php echo (($r++)%2);?>">
				<td class="fc_search_label_cell">
					<label class="label <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_SEARCH_KEYWORD_REQUIREMENT', 'FLEXI_SEARCH_KEYWORD_REQUIREMENT_TIP', 1); ?>">
						<?php echo Text::_('FLEXI_SEARCH_KEYWORD_REQUIREMENT'); ?>:
					</label>
				</td>
				<td class="fc_search_option_cell">
					<div class="fc_filter_html">
						<?php echo $this->lists['searchphrase']; ?>
					</div>
				</td>
			</tr>
			<?php endif;*/ ?>

			<?php if ($autodisplayadvoptions && $this->params->get('canseltext', 1) && isset($this->lists['txtflds'])) : ?>
			
				<div id="fcsearch_txtflds_row" class="fc_search_row_<?php echo (($r++)%2);?>">
					<div class="fc_search_label_cell">
						<label for="txtflds" class=" <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_SEARCH_SEARCHWORDS_IN_FIELDS', 'FLEXI_SEARCH_SEARCHWORDS_IN_FIELDS_TIP', 1); ?>">
							<?php echo Text::_('FLEXI_SEARCH_SEARCHWORDS_IN_FIELDS'); ?>:
						</label>
					</div>
					<div class="fc_search_option_cell">
						<div class="fc_filter_html">
							<?php echo $this->lists['txtflds'];?>
						</div>
					</div>
				</div>
				
			<?php endif; ?>
			
		</div>
	
	</fieldset>
	
<?php if ($autodisplayadvoptions) : ?>
	
	<div id="fc_advsearch_options_set" >
		
		<?php if ( count($this->filters) > 0 || $this->type_based_search ) : ?>
			<fieldset id="fc_fieldfilters_set" class="fc_search_set <?php echo $type_class; ?>">
				<legend>
					<span class="fc_legend_text <?php echo $tooltip_class; ?>" <?php echo $field_filters_title_tip;?> >
						<?php /*echo $infoimage;*/ ?>
						<span><?php echo Text::_('FLEXI_FIELD_FILTERS')/*." ".Text::_('FLEXI_TO_FILTER_TEXT_SEARCH_RESULTS')*/; ?></span>
					</span>
				</legend>
				
			<?php	
			$filter_messages = $app->getUserState('filter_messages', array(), 'array');
			$filter_messages = $filter_messages ?: array();
			$app->setUserState('filter_messages', null);
			$msg = '';
			$msg = implode(' <br/> ', $filter_messages);
			if ( $msg ) :
				?><div class="fcclear"></div><div class="fc-mssg fc-note"><?php echo $msg; ?></div><?php
			endif;
			?>
			<div class="fcclear"></div>
			
				<div id="fc_fieldfilters_tbl" class="fc_search_tbl <?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" >
				<ul>
					
				<?php if ( !count($this->filters) && $this->type_based_search ) : ?>
					<div class="alert alert-info"><?php echo Text::_('FLEXI_SELECT_CONTENT_TYPE_BEFORE_USING_FILTERS'); ?></div>
				<?php endif; ?>
				
				<?php /*if($show_operator = $this->params->get('show_filtersop', 1)) : ?>
					<tr class="fc_search_row_<?php echo (($r++)%2);?>">
						<td colspan="2" class="fc_search_option_cell">
							<label for="operator" class="label <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_SEARCH_FILTERS_REQUIRED', 'FLEXI_SEARCH_FILTERS_REQUIRED_TIP', 1); ?>">
								<?php echo Text::_("FLEXI_SEARCH_FILTERS_REQUIRED"); ?>:
							</label>
							<div class="fc_filter_html">
								<?php echo $this->lists['filtersop']; ?>:
							</div>
						</td>
					</tr>
				<?php endif; */ ?>
				
				<?php
				$display_cat_list = $this->params->get('display_cat_list', 0);
				if($display_cat_list) {
					$label = Text::_('JCATEGORY');
					$descr = Text::_('JCATEGORY');
					$cid = $app->input->get('cid', 0);
				?>
					<li class="fc_search_row_<?php echo (($r++)%2);?>">
						<div class="fc_search_label_cell">
						<label for="filter_category" class=" <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip($label, $descr, 0); ?>">
								<?php echo $label; ?>
						</label>
						</div>
						<div class="fc_search_option_cell">
							<?php
							$catid_fieldname = 'cid';
							$_fld_classes .= ' fc_autosubmit_exclude';  // exclude from autosubmit because we need to get single category SEF url before submitting, and then submit ...
							$_fld_size = "";
							$_fld_onchange = '';
							$_fld_name = $catid_fieldname;
							$_fld_multiple = '';

							$_fld_attributes = ' class="'.$_fld_classes.'" '.$_fld_size.$_fld_onchange.$_fld_multiple;
						
							$allowedtree = FLEXIadvsearchHelper::decideCats($this->params);
							//$selected_cats = $params->get('catids', array());
							$selected_cats = $cid?array($cid):array();
							?>
							<div class="fc_filter_html">
								<?php echo flexicontent_cats::buildcatselect($allowedtree, $_fld_name, $selected_cats, '- '.Text::_($this->params->get('search_firstoptiontext_s', 'FLEXI_ALL')).' -', $_fld_attributes, $check_published = true, $check_perms = false, array(), $require_all=false); ?>
							</div>
						</div>
					</li>
				<?php
				}
				$prepend_onchange = ''; //" adminFormPrepare(document.getElementById('".$form_id."'), 1); ";
				if ($disp_slide_filter){
				echo HTMLHelper::_('bootstrap.startAccordion','menu-sliders-filter', array('active' => $last_active_slide));
				}
				foreach($this->filters as $filt) {
					if (empty($filt->html)) continue;
					$label = Text::_($filt->label);
					$descr = Text::_($filt->description);
					?>
					
					<li class="fc_search_row_<?php echo (($r++)%2);?>">
					
					<?php 
					if ($disp_slide_filter){
					echo HTMLHelper::_('bootstrap.addSlide','menu-sliders-filter' , $label, 'x-' . $filt->id); 
					//HTMLHelper::_('bootstrap.addSlide','menu-sliders-filter' , $label, $filt->id);
					}  ?>
						<div class="fc_search_label_cell">
						<?php if ($descr) : ?>
							<label for="filter_<?php echo $filt->id; ?>" class="label_fcflt <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip($label, $descr, 0); ?>">
								<?php echo $label; ?>
							</label>
						<?php else : ?>
							<label for="filter_<?php echo $filt->id; ?>" class="label_fcflt <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip(Text::_('FLEXI_SEARCH_MISSING_FIELD_DESCR'), Text::sprintf('FLEXI_SEARCH_MISSING_FIELD_DESCR_TIP', $label), 0); ?>">
								<?php echo $label; ?>
							</label>
						<?php endif; ?>
						</div>
						<div class="fc_search_option_cell">
							<?php
							if ( preg_match('/onchange[ ]*=[ ]*([\'"])/i', $filt->html, $matches) && preg_match('/\.submit\(\)/', $filt->html, $matches) ) {
								$filt->html = preg_replace('/onchange[ ]*=[ ]*([\'"])/i', 'onchange=${1}'.$prepend_onchange, $filt->html);
							}
							?>
							<div class="fc_filter_html">
								<?php echo $filt->html; ?>
							</div>
						</div>
						<?php if ($disp_slide_filter){
							echo HTMLHelper::_('bootstrap.endSlide');
						 } ?>
						
					</li>
					
					
				<?php } ?>
				<?php if ($disp_slide_filter) : ?>
				<?php echo HTMLHelper::_('bootstrap.endAccordion'); ?>
				<?php endif; ?>
				</ul>
				</div>	
			</fieldset>
			
		<?php endif; ?>

		<?php if ( $show_searchareas ) : ?>
			
			<fieldset id="fc_search_behavior_set" class="fc_search_set">
				<legend>
					<span class="fc_legend_text <?php echo $tooltip_class; ?>" <?php echo $other_search_areas_title_tip;?> >
						<?php /*echo $infoimage;*/ ?>
						<span><?php echo Text::_('FLEXI_SEARCH_ALSO_SEARCH_IN_AREAS'); ?></span>
					</span>
				</legend>
				
				<div id="fc_search_behavior_tbl" class="fc_search_tbl <?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" >
					
					<tr class="fc_search_row_<?php echo (($r++)%2);?>">
						<td class="fc_search_label_cell">
							<label class="label_fcflt <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_SEARCH_INCLUDE_AREAS', 'FLEXI_SEARCH_INCLUDE_AREAS_TIP', 1); ?>">
								<?php echo Text::_( 'FLEXI_SEARCH_INCLUDE_AREAS' );?> :
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
							<label for="ordering" class="label_fcflt <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_SEARCH_ORDERING', 'FLEXI_SEARCH_ORDERING_TIP', 1); ?>">
								<?php echo Text::_( 'FLEXI_SEARCH_ORDERING' );?>:
							</label>
						</td>
						<td class="fc_search_option_cell">
							<div class="fc_filter_html">
								<?php echo $this->lists['ordering'];?>
							</div>
						</td>
					</tr>
					
				<?php endif; ?>
				
				</div>
				
			</fieldset>
			
		<?php endif; ?>
		
	<!--/fieldset-->
	</div>
	
<?php endif; /* END OF IF autodisplayadvoptions */ ?>

<?php
if (!$buttons_position): ?>
 <div class="btn-group">      
	<button class="<?php echo $flexi_button_class_go; ?> button_go" onclick="var form=document.getElementById('<?php echo $form_id; ?>'); adminFormPrepare(form, 1);">
		<span class="icon-search icon-white"></span><?php echo Text::_( 'FLEXI_GO' ); ?>
	</button>
    <?php if ($show_search_reset) : ?>
							<button class="<?php echo $flexi_button_class_reset; ?>" onclick="var form=document.getElementById('<?php echo $form_id; ?>'); adminFormClearFilters(form); adminFormPrepare(form, 2); return false;" title="<?php echo Text::_( 'FLEXI_REMOVE_FILTERING' ); ?>">
								<i class="icon-remove"></i><?php echo Text::_( 'FLEXI_RESET' ); ?>
							</button>
  <?php endif; ?>
	</div>
<?php endif; ?>


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
				<?php if ($this->lists['orderby_2nd']) echo '<div class="label_fcflt fc_orderby_level_lbl">1</div>'; ?><div class="fc_orderby_selector"><?php echo $this->lists['orderby']; ?></div>
			</div>
		<?php endif; ?>
		
		<?php if ( $this->lists['orderby_2nd'] ) : ?>
			<div class="fc_orderby_box fc_2nd_level <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_ORDERBY_2ND', 'FLEXI_ORDERBY_INFO_2ND', 1); ?>">
				<div class="label_fcflt fc_orderby_level_lbl">2</div><div class="fc_orderby_selector"><?php echo $this->lists['orderby_2nd']; ?></div>
			</div>
		<?php endif; ?>
		
		<?php if (@$this->pageNav) : ?>
		<span class="fc_pages_counter">
			<span class="label_fcflt"><?php echo $this->pageNav->getPagesCounter(); ?></span>
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
			if (!!form)
			{
				jQuery(form.elements).filter("input:not(.fc_autosubmit_exclude):not(.select2-input), select:not(.fc_autosubmit_exclude)").on("change", function() {
					adminFormPrepare(form, 2);
				});
				jQuery(form).attr("data-fc-autosubmit", "2");
			}
		});
	';
} else {
	$js = '
		jQuery(document).ready(function() {
			var form=document.getElementById("'.$form_id.'");
			if (!!form)
			{
				jQuery(form.elements).filter("input:not(.fc_autosubmit_exclude):not(.select2-input), select:not(.fc_autosubmit_exclude)").on("change", function() {
					adminFormPrepare(form, 1);
				});
				jQuery(form).attr("data-fc-autosubmit", "1");
			}
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
$document = Factory::getDocument();
$document->addScriptDeclaration($js);


// FORM in slider
if ($disp_slide_filter)
{
	Factory::getDocument()->addScriptDeclaration("
	(function($) {
		$(document).ready(function ()
		{
			$('#" . $ff_slider_tagid ."').on('shown', function ()
			{
				var active_slides = fclib_getCookie('" . $cookie_name ."');
				try { active_slides = JSON.parse(active_slides); } catch(e) { active_slides = {}; }

				active_slides['" . $ff_slider_tagid ."'] = $('#" . $ff_slider_tagid ." .in').attr('id');
				fclib_setCookie('" . $cookie_name ."', JSON.stringify(active_slides), 7);
				//window.console.log(JSON.stringify(active_slides));
			});

			$('#" . $ff_slider_tagid ."').on('hidden', function ()
			{
				var active_slides = fclib_getCookie('" . $cookie_name ."');
				try { active_slides = JSON.parse(active_slides); } catch(e) { active_slides = {}; }

				active_slides['" . $ff_slider_tagid ."'] = null;
				fclib_setCookie('" . $cookie_name ."', JSON.stringify(active_slides), 7);
				//window.console.log(JSON.stringify(active_slides));
			});

			var active_slides = fclib_getCookie('" . $cookie_name ."');
			try { active_slides = JSON.parse(active_slides); } catch(e) { active_slides = {}; }

			if (!!active_slides['" . $ff_slider_tagid ."'])
			{
				// Hide default active slide
				$('#" . $ff_slider_tagid ." .collapse').removeClass('in');

				// Show the last active slide
				$('#' + active_slides['" . $ff_slider_tagid ."']).addClass('in');
			}
		});
	})(jQuery);
	");
}
