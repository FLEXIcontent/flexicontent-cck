<?php
defined('_JEXEC') or die;

$app    = \Joomla\CMS\Factory::getApplication();
$document = \Joomla\CMS\Factory::getDocument();

$show_search_go = $params->get('show_search_go', 1);
$show_search_reset = $params->get('show_search_reset', 1);
$filter_autosubmit = $params->get('filter_autosubmit', 0);
$filter_instructions = $params->get('filter_instructions', 1);
$filter_placement = $params->get( 'filter_placement', 1 );
$badge_position = (int) $params->get('badge_position', 0); 

$flexi_button_class_go = ($params->get('flexi_button_class_go' ,'') != '-1') ? $params->get('flexi_button_class_go', 'btn btn-success') : $params->get('flexi_button_class_go_custom', 'btn btn-success');
$flexi_button_class_reset = ($params->get('flexi_button_class_reset','') != '-1') ? $params->get('flexi_button_class_reset', 'btn') : $params->get('flexi_button_class_reset_custom', 'btn');

$filters_in_lines = $filter_placement==1 || $filter_placement==2;
$filters_in_tabs  = $filter_placement==3;
$filters_in_slide = $params->get('fc_filter_in_slide', 0);
$filter_container_class  = $filters_in_lines ? 'fc_filter_line' : 'fc_filter';
$filter_container_class .= $filter_placement==2 ? ' fc_clear_label' : '';

if ($filter_placement==3) {
	$document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/tabber.css', array('version' => FLEXI_VHASH));
	$document->addScript(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', array('version' => FLEXI_VHASH));
	static $_filter_TABsetCnt = null;
	if ($_filter_TABsetCnt === null) $_filter_TABsetCnt = -1;
}

$use_search  = $params->get('use_search', 1);
$show_search_label = $params->get('show_search_label', 1);
$use_filters = $params->get('use_filters', 0) && !empty($filters);
$show_filter_labels = $params->get('show_filter_labels', 1);
$show_search_go = $show_search_go || !$filter_autosubmit;

$badges_html = '';
if ($badge_position > 0 && $use_filters) {
    $activeFiltersData = [];
    foreach ($filters as $filt) {
        if (empty($filt->html)) continue;
        $val = $app->input->get('filter_' . $filt->id, '', 'array');
        if (!empty($val)) {
            preg_match('/<option[^>]+selected=["\']selected["\'][^>]*>(.*?)<\/option>/is', $filt->html, $matches);
            $displayVal = isset($matches[1]) ? $matches[1] : (is_array($val) ? implode(', ', $val) : $val);
            $displayVal = trim(strip_tags(preg_replace('/\s*\([^)]*\)/', '', (string)$displayVal)));
            
            if (!empty($displayVal) && strpos($displayVal, '-') !== 0 && !in_array(strtolower($displayVal), ['all', 'tous', 'any', ',', ''])) {
              $filterName = 'filter_'.$filt->id;
                $activeFiltersData[] = (object)['name' => 'filter_'.$filt->id, 'label' => trim(strip_tags($filt->label)), 'value' => $displayVal];
            }
        }
    }
    if (!empty($activeFiltersData)) {
        ob_start(); ?>
        <div class="fc-active-badges d-flex flex-wrap gap-2 mt-3 mb-3 w-100">
            <?php foreach ($activeFiltersData as $b) : ?>
                <div class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center bg-light" style="text-transform:none; pointer-events:none;">
                    <span class="me-1 small text-muted"><?php echo $b->label; ?>:</span>
                    <strong class="me-2 text-dark"><?php echo $b->value; ?></strong>
                    <a href="javascript:void(0)" class="text-danger fw-bold ps-2 border-start border-secondary" 
                       style="pointer-events:auto; text-decoration:none; font-size:1.2rem; line-height:1; margin-left:5px;" 
                       onclick="fcRemoveSingleFilter('<?php echo $b->name; ?>', this)">
                       &times;
                    </a>
                </div>
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
		$ff_slider_id = (!empty($module->id) ? '_module_' . $module->id : '_category');
		$ff_slider_tagid = 'fcfilter_form_slider'.$ff_slider_id;
		$last_active_slide = isset($active_slides->$ff_slider_tagid) ? $active_slides->$ff_slider_tagid : null;
	}
	?>

	<div id="<?php echo $form_id; ?>_filter_box" class="fc_filter_box floattext">
		<fieldset class="fc_filter_set">
            
            <?php if ($badge_position === 1) echo $badges_html; ?>

			<?php if ( $use_search ) : ?>
				<div class="<?php echo $filter_container_class; ?> fc_filter_text_search fc_odd">
					<?php if ($show_search_label==1) : ?>
						<div class="fc_filter_label"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_TEXT_SEARCH'); ?></div>
					<?php endif; ?>
					<div class="fc_filter_html fc_text_search">
						<input type="text" class="fc_text_filter" name="filter" id="<?php echo $form_id; ?>_filter" value="<?php echo htmlspecialchars($text_search_val, ENT_COMPAT, 'UTF-8');?>" placeholder="<?php echo \Joomla\CMS\Language\Text::_('FLEXI_TYPE_TO_LIST'); ?>" />
						<?php echo $searchphrase_selector; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ($use_filters): ?>
				<?php
				$opentag  = !$filters_in_tabs ? $params->get('filter_opentag', '') : '<div class="fctabber fields_tabset" id="fcform_tabset_'.(++$_filter_TABsetCnt).'" >';
				$closetag = !$filters_in_tabs ? $params->get('filter_closetag', '') : '</div>';

				echo $opentag;

                if ($filters_in_slide) {
                    echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.startAccordion', $ff_slider_tagid, array('active' => $last_active_slide));
                }

                $n = 0;
				foreach ($filters as $filt) {
					if (empty($filt->html)) continue;
					$filt_lbl = $filt->label;
					$label_outside = !$filters_in_tabs && ($show_filter_labels==1 || ($show_filter_labels==0 && $filt->parameters->get('display_label_filter')==1));
					$even_odd_class = !$filters_in_tabs ? (($n++)%2 ? ' fc_even': ' fc_odd') : '';
					$is_empty = empty($app->input->get('filter_' . $filt->id, '', 'array'));

					if ($filters_in_tabs) echo '<div class="tabbertab"><h3>' . $filt_lbl . '</h3>';
					
					if ($filters_in_slide) {
						echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.addSlide', $ff_slider_tagid, $filt_lbl, '_filters_slide'.$filt->id);
					}

					echo '<div class="'.$filter_container_class.$even_odd_class . (!$is_empty ? ' active' : '' ) . ' fc_filter_id_'.$filt->id.'">';
					if ($label_outside) echo '<div class="fc_filter_label">' .$filt_lbl. '</div>';
					echo '<div class="fc_filter_html">'.$filt->html.'</div>';
					echo '</div>';

					if ($filters_in_slide) {
						echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endSlide');
					}

					if ($filters_in_tabs) echo '</div>';
				}

                if ($filters_in_slide) {
                    echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endAccordion');
                }

				echo $closetag;
				?>
			<?php endif; ?>

			<?php if ($show_search_go || $show_search_reset) : ?>
				<div class="<?php echo $filter_container_class; ?> fc_filter_buttons_box">
					<div class="fc_buttons btn-group">
						<?php if ($show_search_go) : ?>
							<button type="button" class="<?php echo $flexi_button_class_go; ?>" onclick="var f=jQuery(this).closest('form')[0]; adminFormPrepare(f, 2); return false;">
								<i class="icon-search"></i> <?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_GO' ); ?>
							</button>
						<?php endif; ?>
						<?php if ($show_search_reset) : ?>
							<button type="button" class="<?php echo $flexi_button_class_reset; ?>" onclick="var f=jQuery(this).closest('form')[0]; adminFormClearFilters(f); adminFormPrepare(f, 2); return false;">
								<i class="icon-remove"></i> <?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_RESET' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
            
            <?php if ($badge_position === 2) echo $badges_html; ?>

		</fieldset>
	</div>

	<?php
$js = "
window.fcRemoveSingleFilter = function(fieldName, el) {
    var form = jQuery(el).closest('form');
    if (!form.length) return;
    // Cherche l'input exact ET les inputs indexés (filter_18[1], filter_18[2]...)
    var field = form.find(
        '[name=\"'+fieldName+'\"],' +
        '[name=\"'+fieldName+'[]\"],' +
        '[name=\"'+fieldName+'[1]\"],' +
        '[name=\"'+fieldName+'[2]\"]'
    );
    if (field.length) {
        field.each(function() {
            var f = jQuery(this);
            if (f.is('select')) {
                f.prop('selectedIndex', 0);
                if (f.data('select2')) f.val(null).trigger('change.select2');
            } else {
                f.val('');
            }
        });
        if (typeof adminFormPrepare === 'function') {
            adminFormPrepare(form[0], 2);
        } else {
            form[0].submit();
        }
    }
};

jQuery(document).ready(function($) {
    var containerId = '".$form_id."_filter_box';

    function fixFormAutosubmit() {
        var form = jQuery('#' + containerId).closest('form');
        if (!form.length) return;
        var current = form.attr('data-fc-autosubmit');
        if (!current || current === '0') {
            form.attr('data-fc-autosubmit', ".($filter_autosubmit ? '2' : '1').");
        }
    }
    fixFormAutosubmit();

    \$(document).on('change', '#' + containerId + ' input:not([type=hidden]), #' + containerId + ' select', function() {
        if (!\$(this).hasClass('fc_autosubmit_exclude')) {
            var f = this.form || \$(this).closest('form')[0];
            if (typeof adminFormPrepare === 'function') {
                adminFormPrepare(f, ".($filter_autosubmit ? '2' : '1').");
            }
        }
    });
});";
$document->addScriptDeclaration($js);

endif; ?>