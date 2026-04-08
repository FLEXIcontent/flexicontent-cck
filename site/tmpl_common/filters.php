<?php
$app     = \Joomla\CMS\Factory::getApplication();
$document = \Joomla\CMS\Factory::getDocument();

$show_search_go = $params->get('show_search_go', 1);
$show_search_reset = $params->get('show_search_reset', 1);
$filter_autosubmit = $params->get('filter_autosubmit', 0);
$filter_instructions = $params->get('filter_instructions', 1);
$filter_placement = $params->get( 'filter_placement', 1 );


$badge_position = (int) $params->get('badge_position', 0);  //0:nothing 1:top 2:bottom

$flexi_button_class_go =  ($params->get('flexi_button_class_go' ,'') != '-1')  ?
	$params->get('flexi_button_class_go', 'btn btn-success')   :
	$params->get('flexi_button_class_go_custom', 'btn btn-success')  ;
$flexi_button_class_reset =  ($params->get('flexi_button_class_reset','') != '-1')  ?
	$params->get('flexi_button_class_reset', 'btn')   :
	$params->get('flexi_button_class_reset_custom', 'btn')  ;

$filters_in_lines = $filter_placement==1 || $filter_placement==2;
$filters_in_tabs  = $filter_placement==3;
$filters_in_slide = $params->get('fc_filter_in_slide', 0);
$filter_container_class  = $filters_in_lines ? 'fc_filter_line' : 'fc_filter';
$filter_container_class .= $filter_placement==2 ? ' fc_clear_label' : '';

$fgInfo = FlexicontentFields::getFieldsPerGroup();

if ($filter_placement==3) {
	$document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/tabber.css', array('version' => FLEXI_VHASH));
	$document->addScript(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', array('version' => FLEXI_VHASH));
	$document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');
	static $_filter_TABsetCnt = null;
	if ($_filter_TABsetCnt === null) $_filter_TABsCnt = -1;
	$tabSetCnt = 0;
}

$use_search  = $params->get('use_search', 1);
$show_search_label = $params->get('show_search_label', 1);
$search_autocomplete = $params->get( 'search_autocomplete', 1 );
$use_filters = $params->get('use_filters', 0) && $filters;
$show_filter_labels = $params->get('show_filter_labels', 1);
$show_search_go = $show_search_go || !$filter_autosubmit;

// Constrct badges
$badges_html = '';
if ($badge_position > 0 && $use_filters) {
    $activeFiltersData = [];
    foreach ($filters as $filt) {
        if (empty($filt->html)) continue;
        $filt_vals = $app->input->get('filter_' . $filt->id, '', 'array');
        $is_empty = true;
        if (is_array($filt_vals)) {
            foreach ($filt_vals as $v) {
                if (is_array($v)) { $v2 = reset($v); $is_empty = $is_empty && !strlen(trim(implode('', $v))); }
                else { $is_empty = $is_empty && !strlen(trim($v)); }
            }
        } else { $is_empty = !strlen(trim($filt_vals)); }

        if (!$is_empty) {
            preg_match('/<option[^>]+selected=["\']selected["\'][^>]*>(.*?)<\/option>/is', $filt->html, $matches);
            $displayVal = isset($matches[1]) ? $matches[1] : (is_array($filt_vals) ? implode(', ', $filt_vals) : $filt_vals);
            $displayVal = trim(strip_tags(preg_replace('/\s*\([^)]*\)/', '', $displayVal)));
            if (!empty($displayVal) && strpos($displayVal, '-') !== 0) {
                $activeFiltersData[] = (object)['name' => 'filter_'.$filt->id, 'label' => trim(strip_tags($filt->label)), 'value' => $displayVal];
            }
        }
    }
    if (!empty($activeFiltersData)) {
        ob_start(); ?>
        <div class="d-flex flex-wrap gap-2 mt-2 mb-3 w-100 fc-active-filters-badges">
            <?php foreach ($activeFiltersData as $b) : ?>
                <a href="javascript:void(0)" onclick="fcRemoveSingleFilter('<?php echo $b->name; ?>', '<?php echo $form_id; ?>')" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center" style="text-decoration:none;">
                    <span class="small me-1"><?php echo $b->label; ?>:</span>
                    <strong><?php echo $b->value; ?></strong>
                    <span class="ms-2 ps-2 border-start border-secondary text-danger fw-bold" style="font-size:1.1rem; line-height:1;">&times;</span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php $badges_html = ob_get_clean();
    }
}

if ( $use_search || $use_filters ) : 
	if (!$params->get('disablecss', '')) {
		$document->addStyleSheet(\Joomla\CMS\Uri\Uri::root(true).'/components/com_flexicontent/assets/css/flexi_filters.css', array('version' => FLEXI_VHASH));
	}
	$searchphrase_selector = flexicontent_html::searchphrase_selector($params, $form_name);

	if ($filters_in_slide){
		$ff_slider_tagid = 'fcfilter_form_slider' . (!empty($module->id) ? '_module_'.$module->id : '_category');
		$last_active_slide = isset($active_slides->$ff_slider_tagid) ? $active_slides->$ff_slider_tagid : null;
	}
	?>

	<div id="<?php echo $form_id; ?>_filter_box" class="fc_filter_box floattext">
		<fieldset class="fc_filter_set">

            <?php if ($badge_position === 1) echo $badges_html; ?>

			<?php if ( $use_search ) : ?>
				<div class="<?php echo $filter_container_class; ?> fc_filter_text_search fc_odd">
					<?php if ($show_search_label==1) : ?><div class="fc_filter_label"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_TEXT_SEARCH'); ?></div><?php endif; ?>
					<div class="fc_filter_html fc_text_search">
						<input type="text" class="fc_text_filter" name="filter" id="<?php echo $form_id; ?>_filter" value="<?php echo htmlspecialchars($text_search_val, ENT_COMPAT, 'UTF-8');?>" />
						<?php echo $searchphrase_selector; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ($use_filters): ?>
				<?php
				$opentag  = !$filters_in_tabs ? $params->get('filter_opentag', '') : '<div class="fctabber fields_tabset" id="fcform_tabset_'.(++$_filter_TABsetCnt).'" >';
				$closetag = !$filters_in_tabs ? $params->get('filter_closetag', '') : '</div>';
				
                echo $opentag;
                if ($filters_in_slide) echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.startAccordion', $ff_slider_tagid, array('active' => $last_active_slide));

				$n = 0;
				foreach ($filters as $filt) {
					if (empty($filt->html)) continue;
					$filt_lbl = $filt->label;
					$even_odd_class = !$filters_in_tabs ? (($n++)%2 ? ' fc_even': ' fc_odd') : '';
                    $label_outside  = !$filters_in_tabs && ($show_filter_labels==1 || ($show_filter_labels==0 && $filt->parameters->get('display_label_filter')==1));

					if ($filters_in_tabs) echo '<div class="tabbertab"><h3>'.$filt_lbl.'</h3>';
					
                    // ON AJOUTE LE SLIDE UNIQUEMENT SI L'OPTION EST ACTIVÉE
                    if ($filters_in_slide) echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.addSlide', $ff_slider_tagid, $filt_lbl, $form_id.'_filt'.$filt->id);
					
					echo '<div class="'.$filter_container_class.$even_odd_class.' fc_filter_id_'.$filt->id.'">';
					if ($label_outside) echo '<div class="fc_filter_label">'.$filt_lbl.'</div>';
					echo '<div class="fc_filter_html">'.$filt->html.'</div>';
					echo '</div>';

					if ($filters_in_slide) echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endSlide');
					if ($filters_in_tabs) echo '</div>';
				}

                if ($filters_in_slide) echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endAccordion');
				echo $closetag;
				?>
			<?php endif; ?>

			<?php if ($show_search_go || $show_search_reset) : ?>
				<div class="fc_filter_buttons_box">
					<div class="fc_buttons btn-group mb-2">
						<?php if ($show_search_go) : ?>
							<button class="<?php echo $flexi_button_class_go; ?>" onclick="var form=document.getElementById('<?php echo $form_id; ?>'); adminFormPrepare(form, 2); return false;"><i class="icon-search"></i><?php echo \Joomla\CMS\Language\Text::_('FLEXI_GO'); ?></button>
						<?php endif; ?>
						<?php if ($show_search_reset) : ?>
							<button class="<?php echo $flexi_button_class_reset; ?>" onclick="var form=document.getElementById('<?php echo $form_id; ?>'); adminFormClearFilters(form); adminFormPrepare(form, 2); return false;"><i class="icon-remove"></i><?php echo \Joomla\CMS\Language\Text::_('FLEXI_RESET'); ?></button>
						<?php endif; ?>
					</div>
				</div>
                <?php if ($badge_position === 2) echo $badges_html; ?>
			<?php endif; ?>
		</fieldset>
	</div>

	<?php
    if (!defined('FC_BADGE_JS')) {
        $document->addScriptDeclaration("function fcRemoveSingleFilter(fN, fI) { var f = document.getElementById(fI); if(!f) return; var el = f.querySelector('[name=\"'+fN+'\"], [name=\"'+fN+'[]\"]'); if(el) { if(el.tagName==='SELECT') el.selectedIndex=0; else el.value=''; adminFormPrepare(f, 2); } }");
        define('FC_BADGE_JS', 1);
    }
endif; ?>