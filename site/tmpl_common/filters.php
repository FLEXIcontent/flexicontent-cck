<?php
$app    = JFactory::getApplication();
$document = JFactory::getDocument();

// Form (text search / filters) configuration
$show_search_go = $params->get('show_search_go', 1);
$show_search_reset = $params->get('show_search_reset', 1);
$filter_autosubmit = $params->get('filter_autosubmit', 0);
$filter_instructions = $params->get('filter_instructions', 1);
$filter_placement = $params->get( 'filter_placement', 1 );

$flexi_button_class_go =  ($params->get('flexi_button_class_go' ,'') != '-1')  ?
    $params->get('flexi_button_class_go', 'btn btn-success')   :
    $params->get('flexi_button_class_go_custom', 'btn btn-success')  ;
$flexi_button_class_reset =  ($params->get('flexi_button_class_reset','') != '-1')  ?
    $params->get('flexi_button_class_reset', 'btn')   :
    $params->get('flexi_button_class_reset_custom', 'btn')  ;

$filters_in_lines = $filter_placement==1 || $filter_placement==2;
$filters_in_tabs  = $filter_placement==3;
$filter_container_class  = $filters_in_lines ? 'fc_filter_line' : 'fc_filter';
$filter_container_class .= $filter_placement==2 ? ' fc_clear_label' : '';

// Get field group information
$fgInfo = FlexicontentFields::getFieldsPerGroup();

// Prepare for filters inside TABs
if ($filter_placement==3) {
	$document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/tabber.css', FLEXI_VHASH);
	$document->addScriptVersion(JUri::base(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', FLEXI_VHASH);
	$document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');
	static $_filter_TABsetCnt = null;
	if ($_filter_TABsetCnt === null) $_filter_TABsCnt = -1;
	$tabSetCnt = 0;
}

// Text Search configuration
$use_search  = $params->get('use_search', 1);
$show_search_label = $params->get('show_search_label', 1);
$search_autocomplete = $params->get( 'search_autocomplete', 1 );

// Categories used for Text Search auto-complete
$txt_ac_cid    = $params->get('txt_ac_cid', 'NA');
$txt_ac_cids   = $params->get('txt_ac_cids', array());
$txt_ac_usesubs= $params->get('txt_ac_usesubs', 2);  // 2: all subcat levels, 0: OFF

// Filters configuration
$use_filters = $params->get('use_filters', 0) && $filters;
$show_filter_labels = $params->get('show_filter_labels', 1);

// a ZERO initial value of show_search_go ... is AUTO
$show_search_go = $show_search_go || !$filter_autosubmit;// || $use_search;

// Calculate needed flags
$filter_instructions = ($use_search || $use_filters) ? $filter_instructions : 0;

// Create instructions (tooltip or inline message)
$legend_class = 'fc_legend_text';
$legend_tip = '';

if ($filter_instructions == 1)
{
	$legend_class .= FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
	$legend_tip =
		 ($use_search ? '<b>'.JText::_('FLEXI_TEXT_SEARCH').'</b><br/>'.JText::_('FLEXI_TEXT_SEARCH_INFO') : '')
		.(($use_search || $use_filters) ? '<br/><br/>' : '')
		.($use_filters ? '<b>'.JText::_('FLEXI_FIELD_FILTERS').'</b><br/>'.JText::_('FLEXI_FIELD_FILTERS_INFO') : '')
		;
	$legend_tip = flexicontent_html::getToolTip(null, $legend_tip, 0, 1);
}

elseif ($filter_instructions == 2)
{
	$legend_inline =
		 ($use_search ? '<strong>'.JText::_('FLEXI_TEXT_SEARCH').'</strong><br/>'.JText::_('FLEXI_TEXT_SEARCH_INFO') : '')
		.(($use_search || $use_filters) ? '<br/><br/>' : '')
		.($use_filters ? '<strong>'.JText::_('FLEXI_FIELD_FILTERS').'</strong><br/>'.JText::_('FLEXI_FIELD_FILTERS_INFO') : '')
		;
}

if ( $use_search || $use_filters ) : /* BOF search and filters block */
	if (!$params->get('disablecss', ''))
	{
		$document->addStyleSheetVersion(JUri::root(true).'/components/com_flexicontent/assets/css/flexi_filters.css', FLEXI_VHASH);
	}
	$searchphrase_selector = flexicontent_html::searchphrase_selector($params, $form_name);
?>

<div id="<?php echo $form_id; ?>_filter_box" class="fc_filter_box floattext">

	<fieldset class="fc_filter_set">

		<?php if ($filter_instructions == 1) : ?>
		<legend>
			<span class="<?php echo $legend_class; ?>" title="<?php echo $legend_tip; ?>">
				<span><?php echo JText::_('FLEXI_SEARCH_FILTERING'); ?></span>
			</span>
		</legend>
		<?php endif; ?>

		<?php if ($filter_instructions == 2) :?>
			<div class="fc-mssg fc-info"><?php echo $legend_inline; ?></div>
		<?php endif; ?>

		<?php if ( $use_search ) : /* BOF search */ ?>
			<?php
			$ignoredwords = $app->input->getString('ignoredwords');
			$shortwords = $app->input->getString('shortwords');
			$min_word_len = $app->getUserState($app->input->getCmd('option') . '.min_word_len', 0);

			$msg = '';
			$msg .= $ignoredwords
				? JText::_('FLEXI_WORDS_IGNORED_MISSING_COMMON') . ': <b>' . $ignoredwords . '</b>'
				: '';
			$msg .= $ignoredwords && $shortwords
				? ' <br/> '
				: '';
			$msg .= $shortwords
				? JText::sprintf('FLEXI_WORDS_IGNORED_TOO_SHORT', $min_word_len) . ': <b>' . $shortwords . '</b>'
				: '';
			?>

			<div class="<?php echo $filter_container_class; ?> fc_filter_text_search fc_odd">
				<?php
				$text_search_class = 'fc_text_filter';
				$_label_internal = '';//'fc_label_internal';  // data-fc_label_text="..."
				$text_search_class .= $search_autocomplete ? ($search_autocomplete==2 ? ' fc_index_complete_tlike fc_basic_complete' : ' fc_index_complete_simple fc_basic_complete '.$_label_internal) : ' '.$_label_internal;
				$text_search_prompt = htmlspecialchars(JText::_($show_search_label==2 ? 'FLEXI_TEXT_SEARCH' : 'FLEXI_TYPE_TO_LIST'), ENT_QUOTES, 'UTF-8');
				?>

				<?php if ($show_search_label==1) : ?>
					<div class="fc_filter_label"><?php echo JText::_('FLEXI_TEXT_SEARCH'); ?></div>
				<?php endif; ?>

				<div class="fc_filter_html fc_text_search">
					<input type="text" class="<?php echo $text_search_class; ?>"
						<?php echo 'data-txt_ac_lang="'.JFactory::getLanguage()->getTag().'"'; ?>
						<?php echo 'data-txt_ac_cid="'.$txt_ac_cid.'"'; ?>
						<?php echo 'data-txt_ac_cids="'. implode(',', $txt_ac_cids) .'"'; ?>
						<?php echo 'data-txt_ac_usesubs="'. $txt_ac_usesubs .'"'; ?>
						placeholder="<?php echo $text_search_prompt; ?>" name="filter"
						id="<?php echo $form_id; ?>_filter" value="<?php echo htmlspecialchars($text_search_val, ENT_COMPAT, 'UTF-8');?>" />
					<?php echo $searchphrase_selector; ?>

					<?php if ( $msg ) : ?><div class="fc-mssg fc-note"><?php echo $msg; ?></div><?php endif; ?>
				</div>

			</div>

		<?php endif; /* EOF search */ ?>

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

		<?php if ($use_filters): /* BOF filter */ ?>
			<?php
			// Prefix/Suffix texts
			$pretext  = $params->get('filter_pretext', '');
			$posttext = $params->get('filter_posttext', '');

			// Open/Close tags
			$opentag  = !$filters_in_tabs
				? $params->get('filter_opentag', '')
				: '<div class="fctabber fields_tabset" id="fcform_tabset_'.(++$_filter_TABsetCnt).'" >';
			$closetag = !$filters_in_tabs
				? $params->get('filter_closetag', '')
				: '</div>';

			$n = 0;
			$prepend_onchange = ''; //" adminFormPrepare(document.getElementById('".$form_id."'), 1); ";

			$filters_html = array();

			foreach ($filters as $filt)
			{
				if (empty($filt->html))
				{
					continue;
				}

				$filt_lbl = $filt->label;

				if (isset($fgInfo->field_to_grp[$filt->id]))
				{
					$fieldgrp_id = $fgInfo->field_to_grp[$filt->id];
					$filt_lbl = '<div class="label label-info">'.$fgInfo->grps[$fieldgrp_id]->label .'</div><br/>'. $filt_lbl;
				}

				/*
				 * Support for old 3rd party filters, that include an auto-submit statement or include a fixed form name
				 * These CUSTOM fields should be updated to have this auto-submit code removed fixed form name changed too
				 */

				/*
				 * Compatibility workaround 1
				 * These fields need to be have their onChange Event prepended with the FORM PREPARATION function call,
				 * ... but if these filters change value after we 'prepare' form then we have an issue ...
				 */
				if (preg_match('/onchange[ ]*=[ ]*([\'"])/i', $filt->html, $matches) && preg_match('/\.submit\(\)/', $filt->html, $matches))
				{
					$filt->html = preg_replace('/onchange[ ]*=[ ]*([\'"])/i', 'onchange=${1}'.$prepend_onchange, $filt->html);
				}

				/*
				 * Compatibility workaround 2
				 * These fields also need to have any 'adminForm' string present in their filter's HTML replaced with the name of our form
				 */
				$filt->html = preg_replace('/([\'"])adminForm([\'"])/', '${1}'.$form_name.'${2}', $filt->html);

				$label_outside  = !$filters_in_tabs && ($show_filter_labels==1 || ($show_filter_labels==0 && $filt->parameters->get('display_label_filter')==1));
				$even_odd_class = !$filters_in_tabs ? (($n++)%2 ? ' fc_even': ' fc_odd') : '';

				// Highlight active filter
				$filt_vals = $app->input->get('filter_' . $filt->id, '', 'array');

				// Skip filters without value
				if (is_array($filt_vals))
				{
					if (!count($filt_vals))
					{
						$is_empty = true;
					}
					else
					{
						$v = reset($filt_vals);

						$is_empty = is_array($v)
							? false
							: !strlen(trim(implode('', $filt_vals)));
					}
				}
				else
				{
					$is_empty = !strlen(trim($filt_vals));
				}

				$filter_label_class = !$is_empty
					? 'fc_filter_active'
					: 'fc_filter_inactive';

				$_filter_html =

					/* Optional TAB start and filter label as TAB title */
					($filters_in_tabs ? '
					<div class="tabbertab" id="fcform_tabset_'.$_filter_TABsetCnt.'_tab_'.($tabSetCnt++).'" >
						<h3 class="tabberheading ' . $filter_label_class . '">' . $filt_lbl . (!$is_empty ? ' *' : '') . '</h3>' : '')

						/* External filter container */.'
						<div class="'.$filter_container_class.$even_odd_class.' fc_filter_id_'.$filt->id.'" >'.

							/* Optional filter label before filter's HTML */
							($label_outside ? '
							<div class="fc_filter_label fc_label_field_'.$filt->id.'">' .$filt_lbl. '</div>' : '')

							/* Internal filter container and filter 's HTML */.'
							<div class="fc_filter_html fc_html_field_'.$filt->id.'">'
								.$filt->html.'
							</div>

						</div>
					'.

					/* Optional TAB end */
					($filters_in_tabs ? '
					</div>' : '').'
				';

				$_filter_html = $filter_placement != 3
					? $pretext . $_filter_html . $posttext
					: $_filter_html;

				$filters_html[] = $_filter_html;
			}


			// (if) Using separator
			$separatorf = '';
			if ($filter_placement == 0)
			{
				$separatorf = $params->get( 'filter_separatorf', 1 );
				$separators_arr = array( 0 => '&nbsp;', 1 => '<br />', 2 => '&nbsp;|&nbsp;', 3 => ',&nbsp;', 4 => $closetag.$opentag, 5 => '' );
				$separatorf = isset($separators_arr[$separatorf]) ? $separators_arr[$separatorf] : '&nbsp;';
			}

			// Create HTML of filters
			echo $opentag . implode($separatorf, $filters_html) . $closetag;
			unset ($filters_html);
			?>


		<?php endif; /* EOF filter */ ?>


		<?php if (!$show_search_go) : ?>
			<div style="display:none; ">
				<input type="submit" onclick="var form=document.getElementById('<?php echo $form_id; ?>'); adminFormPrepare(form, 2); return false;" />
			</div>
		<?php endif; ?>

		<?php if ($show_search_go || $show_search_reset) : ?>
		<div class="<?php echo $filter_container_class; ?> fc_filter_buttons_box">
			<div class="fc_buttons btn-group">
				<?php if ($show_search_go) : ?>
				<button class="<?php echo $flexi_button_class_go; ?>" onclick="var form=document.getElementById('<?php echo $form_id; ?>'); adminFormPrepare(form, 2); return false;" title="<?php echo JText::_( 'FLEXI_APPLY_FILTERING' ); ?>">
					<i class="icon-search"></i><?php echo JText::_( 'FLEXI_GO' ); ?>
				</button>
				<?php endif; ?>

				<?php if ($show_search_reset) : ?>
				<button class="<?php echo $flexi_button_class_reset; ?>" onclick="var form=document.getElementById('<?php echo $form_id; ?>'); adminFormClearFilters(form); adminFormPrepare(form, 2); return false;" title="<?php echo JText::_( 'FLEXI_REMOVE_FILTERING' ); ?>">
					<i class="icon-remove"></i><?php echo JText::_( 'FLEXI_RESET' ); ?>
				</button>
				<?php endif; ?>

			</div>
			<div id="<?php echo $form_id; ?>_submitWarn" class="fc-mssg fc-note" style="display:none;"><?php echo JText::_('FLEXI_FILTERS_CHANGED_CLICK_TO_SUBMIT'); ?></div>
		</div>
		<?php endif; ?>

	</fieldset>

</div>

<?php endif; /* EOF search and filter block */

// Automatic submission
if ($filter_autosubmit) {
	$js = '
		jQuery(document).ready(function() {
			var form=document.getElementById("'.$form_id.'");
			jQuery(form.elements).filter("input:not(.fc_autosubmit_exclude):not(.select2-input), select:not(.fc_autosubmit_exclude)").on("change", function() {
				adminFormPrepare(form, 2);
			});
			jQuery(form).attr("data-fc-autosubmit", "2");
		});
	';
} else {
	$js = '
		jQuery(document).ready(function() {
			var form=document.getElementById("'.$form_id.'");
			jQuery(form.elements).filter("input:not(.fc_autosubmit_exclude):not(.select2-input), select:not(.fc_autosubmit_exclude)").on("change", function() {
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
$document->addScriptDeclaration($js);