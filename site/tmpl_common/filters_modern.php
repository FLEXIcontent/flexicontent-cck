<?php
// Modern filters template - Bootstrap 5 + Mobile-first
defined('_JEXEC') or die;

$app      = \Joomla\CMS\Factory::getApplication();
$document = \Joomla\CMS\Factory::getDocument();

// ── params ──────────────────────────────────────────────────────────
$show_search_go     = $params->get('show_search_go', 1);
$show_search_reset  = $params->get('show_search_reset', 1);
$filter_autosubmit  = $params->get('filter_autosubmit', 0);
$filter_instructions = $params->get('filter_instructions', 1);
$filter_placement   = $params->get('filter_placement', 1);
$badge_position     = (int) $params->get('badge_position', 0);

$use_search         = $params->get('use_search', 1);
$show_search_label  = $params->get('show_search_label', 1);
$use_filters        = $params->get('use_filters', 0) && !empty($filters);
$show_filter_labels = $params->get('show_filter_labels', 1);
$show_search_go     = $show_search_go || !$filter_autosubmit;

$filters_in_lines   = $filter_placement == 1 || $filter_placement == 2;
$filters_in_tabs    = $filter_placement == 3;
$filters_in_slide   = $params->get('fc_filter_in_slide', 0);

if (!($use_search || $use_filters)) return;

// ── CSS ──────────────────────────────────────────────────────────────
if (!$params->get('disablecss', '')) {
    $document->getWebAssetManager()->registerAndUseStyle(
        'fc-flexi_filters',
        \Joomla\CMS\Uri\Uri::root().'components/com_flexicontent/assets/css/flexi_filters.css',
        array('version' => FLEXI_VHASH)
    );
}

// ── Active filter badges ─────────────────────────────────────────────
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
            if (!empty($displayVal) && strpos($displayVal, '-') !== 0 && !in_array(strtolower($displayVal), ['all','tous','any',',',''])) {
                $activeFiltersData[] = (object)[
                    'name'  => 'filter_'.$filt->id,
                    'label' => trim(strip_tags($filt->label)),
                    'value' => $displayVal,
                ];
            }
        }
    }
    if (!empty($activeFiltersData)) {
        ob_start(); ?>
        <div class="fc-active-badges d-flex flex-wrap gap-2 mt-2 mb-3 w-100" role="group" aria-label="<?php echo \Joomla\CMS\Language\Text::_('FLEXI_ACTIVE_FILTERS'); ?>">
            <?php foreach ($activeFiltersData as $b) : ?>
                <span class="badge fc-active-badge d-inline-flex align-items-center gap-1 py-2 px-3">
                    <span class="fc-badge-label opacity-75"><?php echo htmlspecialchars($b->label); ?>:</span>
                    <strong><?php echo htmlspecialchars($b->value); ?></strong>
                    <button type="button" class="btn-close btn-close-white btn-sm ms-1" aria-label="<?php echo \Joomla\CMS\Language\Text::_('JREMOVE'); ?>"
                        onclick="fcRemoveSingleFilter('<?php echo htmlspecialchars($b->name); ?>', this)" style="font-size:.65em;"></button>
                </span>
            <?php endforeach; ?>
        </div>
        <?php $badges_html = ob_get_clean();
    }
}

// ── Accordion slider setup ──────────────────────────────────────────
if ($filters_in_slide) {
    $ff_slider_id    = (!empty($module->id) ? '_module_' . $module->id : '_category');
    $ff_slider_tagid = 'fcfilter_form_slider'.$ff_slider_id;
    $last_active_slide = isset($active_slides->$ff_slider_tagid) ? $active_slides->$ff_slider_tagid : null;
}

$searchphrase_selector = flexicontent_html::searchphrase_selector($params, $form_name);
?>

<div id="<?php echo $form_id; ?>_filter_box" class="fc-filter-box-modern">

    <?php if ($badge_position === 1) echo $badges_html; ?>

    <div class="fc-filter-card card shadow-sm border-0">
        <div class="card-body p-3 p-md-4">

            <?php if ($use_search) : ?>
            <!-- ── Text search ── -->
            <div class="fc-search-row mb-3">
                <?php if ($show_search_label) : ?>
                    <label for="<?php echo $form_id; ?>_filter" class="form-label fc-filter-heading fw-semibold small text-uppercase tracking-wide mb-1">
                        <i class="fas fa-search me-1 opacity-50"></i><?php echo \Joomla\CMS\Language\Text::_('FLEXI_TEXT_SEARCH'); ?>
                    </label>
                <?php endif; ?>
                <div class="input-group input-group-lg fc-search-input-group">
                    <span class="input-group-text bg-transparent border-end-0 text-muted">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text"
                        class="form-control border-start-0 fc_text_filter ps-0"
                        name="filter"
                        id="<?php echo $form_id; ?>_filter"
                        value="<?php echo htmlspecialchars($text_search_val, ENT_COMPAT, 'UTF-8'); ?>"
                        placeholder="<?php echo \Joomla\CMS\Language\Text::_('FLEXI_TYPE_TO_LIST'); ?>"
                        autocomplete="off"
                        aria-label="<?php echo \Joomla\CMS\Language\Text::_('FLEXI_TEXT_SEARCH'); ?>"
                    />
                    <?php if ($show_search_go && !$use_filters) : ?>
                    <button type="button" class="btn btn-primary fc-btn-go px-4" onclick="var f=jQuery(this).closest('form')[0]; adminFormPrepare(f,2); return false;">
                        <i class="fas fa-search"></i> <span class="d-none d-sm-inline"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_GO'); ?></span>
                    </button>
                    <?php endif; ?>
                </div>
                <?php echo $searchphrase_selector; ?>
            </div>
            <?php endif; ?>

            <?php if ($use_filters) : ?>
            <!-- ── Filters grid ── -->
            <?php
            $opentag  = !$filters_in_tabs ? $params->get('filter_opentag', '') : '';
            $closetag = !$filters_in_tabs ? $params->get('filter_closetag', '') : '';
            echo $opentag;
            if ($filters_in_slide) {
                echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.startAccordion', $ff_slider_tagid, array('active' => $last_active_slide));
            }
            ?>
            <div class="row g-3 fc-filters-grid<?php echo $filters_in_tabs ? ' fc-filters-tabs' : ''; ?>">
            <?php
            $n = 0;
            foreach ($filters as $filt) {
                if (empty($filt->html)) continue;
                $filt_lbl    = $filt->label;
                $label_show  = !$filters_in_tabs && ($show_filter_labels == 1 || ($show_filter_labels == 0 && $filt->parameters->get('display_label_filter') == 1));
                $is_active   = !empty($app->input->get('filter_' . $filt->id, '', 'array'));
                $n++;

                if ($filters_in_slide) {
                    echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.addSlide', $ff_slider_tagid, $filt_lbl, '_filters_slide'.$filt->id);
                }
                ?>
                <div class="col-12 col-sm-6 col-lg-4 fc-filter-col fc_filter_id_<?php echo $filt->id; ?><?php echo $is_active ? ' fc-filter-active' : ''; ?>">
                    <div class="fc-filter-item h-100">
                        <?php if ($label_show) : ?>
                            <label class="form-label fc-filter-label fw-semibold small text-uppercase mb-1 <?php echo $is_active ? 'text-primary' : 'text-muted'; ?>">
                                <?php echo strip_tags($filt_lbl); ?>
                                <?php if ($is_active) : ?><i class="fas fa-circle text-primary ms-1" style="font-size:.4em;vertical-align:middle;"></i><?php endif; ?>
                            </label>
                        <?php endif; ?>
                        <div class="fc-filter-control<?php echo $is_active ? ' fc-control-active' : ''; ?>">
                            <?php echo $filt->html; ?>
                        </div>
                    </div>
                </div>
                <?php
                if ($filters_in_slide) {
                    echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endSlide');
                }
            }
            ?>
            </div><!-- /.fc-filters-grid -->

            <?php
            if ($filters_in_slide) {
                echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endAccordion');
            }
            echo $closetag;
            ?>
            <?php endif; /* $use_filters */ ?>

            <!-- ── Action buttons ── -->
            <?php if ($show_search_go || $show_search_reset) : ?>
            <div class="fc-filter-actions d-flex gap-2 flex-wrap mt-3 pt-3 border-top">
                <?php if ($show_search_go) : ?>
                <button type="button" class="btn btn-primary fc-btn-go flex-grow-1 flex-sm-grow-0"
                    onclick="var f=jQuery(this).closest('form')[0]; adminFormPrepare(f,2); return false;">
                    <i class="fas fa-search me-1"></i><?php echo \Joomla\CMS\Language\Text::_('FLEXI_GO'); ?>
                </button>
                <?php endif; ?>
                <?php if ($show_search_reset) : ?>
                <button type="button" class="btn btn-outline-secondary fc-btn-reset"
                    onclick="var f=jQuery(this).closest('form')[0]; adminFormClearFilters(f); adminFormPrepare(f,2); return false;">
                    <i class="fas fa-times me-1"></i><?php echo \Joomla\CMS\Language\Text::_('FLEXI_RESET'); ?>
                </button>
                <?php endif; ?>
                <?php if ($use_filters) : ?>
                <span class="fc-filter-count ms-auto align-self-center text-muted small d-none d-sm-block">
                    <?php echo count($filters); ?> <?php echo \Joomla\CMS\Language\Text::_('FLEXI_FILTERS'); ?>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div><!-- /.card-body -->
    </div><!-- /.fc-filter-card -->

    <?php if ($badge_position === 2) echo $badges_html; ?>

</div><!-- /.fc-filter-box-modern -->

<?php
// ── Inline JS (autosubmit + remove badge) ───────────────────────────
$js = "
window.fcRemoveSingleFilter = function(fieldName, el) {
    var form = jQuery(el).closest('form');
    if (!form.length) return;
    var field = form.find('[name=\"'+fieldName+'\"],[name=\"'+fieldName+'[]\"],[name=\"'+fieldName+'[1]\"],[name=\"'+fieldName+'[2]\"]');
    field.each(function() {
        var f = jQuery(this);
        if (f.is('select')) { f.prop('selectedIndex',0); if(f.data('select2')) f.val(null).trigger('change.select2'); }
        else { f.val(''); }
    });
    if (typeof adminFormPrepare === 'function') adminFormPrepare(form[0], 2);
    else form[0].submit();
};
jQuery(document).ready(function(\$) {
    var containerId = '".addslashes($form_id)."_filter_box';
    var form = \$('#'+containerId).closest('form');
    if (!form.attr('data-fc-autosubmit') || form.attr('data-fc-autosubmit')==='0') {
        form.attr('data-fc-autosubmit', '".($filter_autosubmit ? '2' : '1')."');
    }
    \$(document).on('change', '#'+containerId+' input:not([type=hidden]), #'+containerId+' select', function() {
        if (!\$(this).hasClass('fc_autosubmit_exclude')) {
            var f = this.form || \$(this).closest('form')[0];
            if (typeof adminFormPrepare === 'function') adminFormPrepare(f, '".($filter_autosubmit ? '2' : '1')."');
        }
    });
});";
$document->addScriptDeclaration($js);
