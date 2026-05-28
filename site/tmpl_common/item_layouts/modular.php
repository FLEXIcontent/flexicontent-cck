<?php
/**
 * @package     FLEXIcontent
 * @copyright   (C) 2009-2018 Emmanuel Danan, Georgios Papadakis, Yannick Berges
 * @license     GNU/GPL v2
 *
 * modular.php — data-driven via le JSON du Page Builder visuel.
 *
 * Logique :
 *   1. On tente de lire $this->params->get('layout_json').
 *   2. Si le JSON est valide et non vide → rendu dynamique.
 *   3. Sinon → fallback sur le layout statique original (positions hardcodées).
 *
 * Structure JSON attendue (produite par default.php) :
 * {
 *   "rows": [
 *     {
 *       "id": "r1",
 *       "zones": [
 *         { "id": "z1", "name": "subtitle1", "colPct": 100, "fields": ["title"] },
 *         { "id": "z2", "name": "image",     "colPct": 33,  "fields": ["image"] }
 *       ]
 *     }
 *   ]
 * }
 *
 * colPct est converti en classe Bootstrap 5 col-N (1–12).
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;

// ---------------------------------------------------------------------------
// Initialisations de base (identiques à l'original)
// ---------------------------------------------------------------------------
$tmpl = $this->tmpl;
$item = $this->item;
$menu = \Joomla\CMS\Factory::getApplication()->getMenu()->getActive();

/** Tooltips front-end */
$cparams = \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent');
if ($cparams->get('add_tooltips', 1)) {
    \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.popover', '.hasTooltip', ['trigger' => 'click hover']);
}
\Joomla\CMS\Factory::getDocument()->addScript(
    \Joomla\CMS\Uri\Uri::base(true) . '/components/com_flexicontent/assets/js/tabber-minimized.js',
    ['version' => FLEXI_VHASH]
);
\Joomla\CMS\Factory::getDocument()->addStyleSheet(
    \Joomla\CMS\Uri\Uri::base(true) . '/components/com_flexicontent/assets/css/tabber.css',
    ['version' => FLEXI_VHASH]
);
\Joomla\CMS\Factory::getDocument()->addScriptDeclaration(
    ' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); '
);

// Prepend TOC à la description si présent
if (isset($item->toc)) {
    $item->fields['text']->display = $item->toc . $item->fields['text']->display;
}

// ---------------------------------------------------------------------------
// Classes CSS personnalisées (héritées des paramètres du layout)
// ---------------------------------------------------------------------------
$box_class_subtitle1 = $this->params->get('box_class_subtitle1', 'flexi lineinfo subtitle1');
$box_class_subtitle2 = $this->params->get('box_class_subtitle2', 'flexi lineinfo subtitle2');
$box_class_subtitle3 = $this->params->get('box_class_subtitle3', 'flexi lineinfo subtitle3');
$box_class_image     = $this->params->get('box_class_image',  'flexi image');
$box_class_top       = $this->params->get('box_class_top',    'flexi infoblock');
$box_class_descr     = $this->params->get('box_class_descr',  'flexi description');
$box_class_bottom    = $this->params->get('box_class_bottom', 'flexi bottomblock infoblock');

// Map position → classe CSS personnalisée (fallback sur une classe generique)
$position_custom_class = [
    'subtitle1'   => $box_class_subtitle1,
    'subtitle2'   => $box_class_subtitle2,
    'subtitle3'   => $box_class_subtitle3,
    'image'       => $box_class_image,
    'top'         => $box_class_top,
    'description' => $box_class_descr,
    'bottom'      => $box_class_bottom,
];

// ---------------------------------------------------------------------------
// SEO / structure HTML principale (identique à l'original)
// ---------------------------------------------------------------------------
$page_heading_shown =
    $this->params->get('show_page_heading', 1) &&
    $this->params->get('page_heading') != $item->title &&
    $this->params->get('show_title', 1);

$mainAreaTag         = $page_heading_shown ? 'section' : 'article';
$itemTitleHeaderLevel = $page_heading_shown ? '2' : '1';
$tabsHeaderLevel     = $itemTitleHeaderLevel == '2' ? '3' : '2';

$page_classes  = 'flexicontent  ';
$page_classes .= $this->pageclass_sfx ? ' page' . $this->pageclass_sfx : '';
$page_classes .= ' fcitems fcitem' . $item->id;
$page_classes .= ' fctype' . $item->type_id;
$page_classes .= ' fcmaincat' . $item->catid;
if ($menu) {
    $page_classes .= ' menuitem' . $menu->id;
}

$microdata_itemtype      = $this->params->get('microdata_itemtype', 'Article');
$microdata_itemtype_code = 'itemscope itemtype="http://schema.org/' . $microdata_itemtype . '"';

// ---------------------------------------------------------------------------
// Helper : convertit un pourcentage en numéro de colonne Bootstrap (1–12)
// ---------------------------------------------------------------------------
function fcpb_pct_to_col(float $pct): int
{
    $steps = [8, 16, 25, 33, 42, 50, 58, 67, 75, 83, 92, 100];
    $cols  = [1,  2,  3,  4,  5,  6,  7,  8,  9, 10, 11,  12];
    $best  = 12;
    $bestDiff = 100;
    foreach ($steps as $i => $step) {
        $diff = abs($pct - $step);
        if ($diff < $bestDiff) {
            $bestDiff = $diff;
            $best = $cols[$i];
        }
    }
    return $best;
}

// ---------------------------------------------------------------------------
// Helper : affiche une position FLEXIcontent dans une <div> Bootstrap
// ---------------------------------------------------------------------------
function fcpb_render_position(object $item, string $posName, string $colClass, string $customClass = ''): void
{
    if (!isset($item->positions[$posName])) {
        return;
    }

    $wrapClass  = trim('col-12 ' . $colClass . ' fcpb-zone');
    // $customClass = classe CSS personnalisée depuis $position_custom_class (ex: 'flexi image')
    // Si pas de classe custom, on applique juste 'flexi lineinfo posName'
    $innerClass = $customClass ?: trim('flexi lineinfo ' . $posName);
    ?>
    <div class="<?php echo $wrapClass; ?>" data-position="<?php echo htmlspecialchars($posName, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="<?php echo htmlspecialchars($innerClass, ENT_QUOTES, 'UTF-8'); ?>">
            <?php foreach ($item->positions[$posName] as $field) : ?>
                <div class="flexi element field_<?php echo $field->name; ?>">
                    <?php if ($field->label) : ?>
                        <span class="flexi label field_<?php echo $field->name; ?>">
                            <?php echo $field->label; ?>
                        </span>
                    <?php endif; ?>
                    <div class="flexi value field_<?php echo $field->name; ?>">
                        <?php echo $field->display; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

// ---------------------------------------------------------------------------
// Lecture du JSON du Page Builder
// ---------------------------------------------------------------------------
$layout_json = $this->params->get('layout_json', '');
$layout_data = null;

if ($layout_json) {
    $decoded = json_decode($layout_json, true);
    if (json_last_error() === JSON_ERROR_NONE && !empty($decoded['rows'])) {
        $layout_data = $decoded;
    }
}

$use_builder_layout = ($layout_data !== null);

// ---------------------------------------------------------------------------
// Reconstruction de $item->positions à partir du JSON builder
//
// FLEXIcontent ne peuple $item->positions que pour les positions déclarées
// dans item.xml (fieldgroups). Les zones libres du builder (noms arbitraires)
// ne sont pas connues de FLEXIcontent.
//
// On reconstruit le tableau en lisant les champs assignés dans chaque zone
// du JSON, en les cherchant dans $item->fields.
// ---------------------------------------------------------------------------
if ($use_builder_layout) {

    // Collecter tous les champs utilisés dans le builder
    // dont le display est vide — il faut déclencher leur plugin
    $builder_fields_needing_display = array();
    foreach ($layout_data['rows'] as $row) {
        foreach ($row['zones'] as $zone) {
            foreach (($zone['fields'] ?? []) as $field_name) {
                if (isset($item->fields[$field_name]) && $item->fields[$field_name]->display === '') {
                    $builder_fields_needing_display[$field_name] = $item->fields[$field_name];
                }
            }
        }
    }

    // Déclencher le plugin FLEXIcontent pour chaque champ sans display
    if (!empty($builder_fields_needing_display)) {

        // Charger FlexicontentHelperField si pas encore chargé
        if (!class_exists('FlexicontentHelperField')) {
            $helper_path = JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/field.php';
            if (file_exists($helper_path)) {
                require_once $helper_path;
            }
        }

        // Importer les plugins de champs FLEXIcontent
        \Joomla\CMS\Plugin\PluginHelper::importPlugin('flexicontent_fields');
        $app = \Joomla\CMS\Factory::getApplication();

        foreach ($builder_fields_needing_display as $field_name => $field) {
            // Méthode 1 : via FlexicontentHelperField si disponible
            if (class_exists('FlexicontentHelperField') && method_exists('FlexicontentHelperField', 'getFieldDisplay')) {
                FlexicontentHelperField::getFieldDisplay($field, $item, $this->params, 'item');

            // Méthode 2 : triggerEvent direct (Joomla 3/4)
            } elseif (method_exists($app, 'triggerEvent')) {
                $app->triggerEvent('onDisplayFieldValue', array(&$field, &$item, &$this->params, 'com_flexicontent.item'));

            // Méthode 3 : dispatcher Joomla 5
            } else {
                try {
                    $dispatcher = $app->getDispatcher();
                    $event = new \Joomla\Event\Event('onDisplayFieldValue', [
                        'field'   => $field,
                        'item'    => $item,
                        'params'  => $this->params,
                        'context' => 'com_flexicontent.item',
                    ]);
                    $dispatcher->dispatch('onDisplayFieldValue', $event);
                } catch (\Exception $e) {
                    // Silencieux
                }
            }
        }
    }

    // Reconstruire les positions depuis le JSON
    foreach ($layout_data['rows'] as $row) {
        foreach ($row['zones'] as $zone) {
            $pos_name = $zone['name'];

            if (!empty($zone['fields'])) {
                $item->positions[$pos_name] = array();
                foreach ($zone['fields'] as $field_name) {
                    if (isset($item->fields[$field_name])) {
                        $item->positions[$pos_name][] = $item->fields[$field_name];
                    }
                }
                if (empty($item->positions[$pos_name])) {
                    unset($item->positions[$pos_name]);
                }
            } elseif (isset($item->positions[$pos_name])) {
                unset($item->positions[$pos_name]);
            }
        }
    }
}

// DEBUG — à retirer
file_put_contents(JPATH_ROOT.'/method_debug.txt',
    'FlexicontentHelperField exists: ' . (class_exists('FlexicontentHelperField') ? 'YES' : 'NO') . "
" .
    'flexicontent_html exists: ' . (class_exists('flexicontent_html') ? 'YES' : 'NO') . "
" .
    'tmpl property: ' . print_r(array_keys((array)$this), true)
);
// FIN DEBUG

// DEBUG temporaire
file_put_contents(JPATH_ROOT.'/img_debug2.txt',
    'FlexicontentHelperField exists after load: ' . (class_exists('FlexicontentHelperField') ? 'YES' : 'NO') . "\n" .
    'field15 display after trigger: [' . ($item->fields['field15']->display ?? 'N/A') . "]\n"
);
// FIN DEBUG

// ---------------------------------------------------------------------------
// RENDU HTML
// ---------------------------------------------------------------------------
?>
<?php echo '<' . $mainAreaTag; ?> id="flexicontent"
    class="<?php echo $page_classes; ?>"
    <?php echo $microdata_itemtype_code; ?>>

    <?php echo ($mainAreaTag === 'section') ? '<header>' : ''; ?>

    <?php if ($item->event->beforeDisplayContent) : ?>
        <!-- BOF beforeDisplayContent -->
        <aside class="fc_beforeDisplayContent">
            <?php echo $item->event->beforeDisplayContent; ?>
        </aside>
        <!-- EOF beforeDisplayContent -->
    <?php endif; ?>

    <?php if (\Joomla\CMS\Factory::getApplication()->input->getInt('print', 0)) : ?>
        <!-- BOF Print handling -->
        <?php if ($this->params->get('print_behaviour', 'auto') === 'auto') : ?>
            <script>jQuery(document).ready(function(){ window.print(); });</script>
        <?php elseif ($this->params->get('print_behaviour') === 'button') : ?>
            <input type="button" id="printBtn" value="<?php echo \Joomla\CMS\Language\Text::_('Print'); ?>"
                class="btn btn-info"
                onclick='this.style.display="none"; window.print(); return false;'>
        <?php endif; ?>
        <!-- EOF Print handling -->

    <?php else : ?>

        <?php
        $pdfbutton      = flexicontent_html::pdfbutton($item, $this->params);
        $mailbutton     = flexicontent_html::mailbutton(FLEXI_ITEMVIEW, $this->params, $item->categoryslug, $item->slug, 0, $item);
        $printbutton    = flexicontent_html::printbutton($this->print_link, $this->params);
        $editbutton     = flexicontent_html::editbutton($item, $this->params);
        $statebutton    = flexicontent_html::statebutton($item, $this->params);
        $deletebutton   = flexicontent_html::deletebutton($item, $this->params);
        $approvalbutton = flexicontent_html::approvalbutton($item, $this->params);
        ?>

        <?php if ($pdfbutton || $mailbutton || $printbutton || $editbutton || $deletebutton || $statebutton || $approvalbutton) : ?>
            <!-- BOF buttons -->
            <?php if ($this->params->get('btn_grp_dropdown')) : ?>
                <div class="buttons btn-group">
                    <button type="button" class="btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="<?php echo $this->params->get('btn_grp_dropdown_class', 'icon-options'); ?>"></span>
                    </button>
                    <ul class="dropdown-menu">
                        <?php echo $pdfbutton    ? '<li>' . $pdfbutton    . '</li>' : ''; ?>
                        <?php echo $mailbutton   ? '<li>' . $mailbutton   . '</li>' : ''; ?>
                        <?php echo $printbutton  ? '<li>' . $printbutton  . '</li>' : ''; ?>
                        <?php echo $editbutton   ? '<li>' . $editbutton   . '</li>' : ''; ?>
                        <?php echo $deletebutton ? '<li>' . $deletebutton . '</li>' : ''; ?>
                        <?php echo $approvalbutton ? '<li>' . $approvalbutton . '</li>' : ''; ?>
                    </ul>
                    <?php echo $statebutton; ?>
                </div>
            <?php else : ?>
                <div class="buttons d-flex flex-wrap gap-1">
                    <?php echo $pdfbutton; ?>
                    <?php echo $mailbutton; ?>
                    <?php echo $printbutton; ?>
                    <?php echo $editbutton; ?>
                    <?php echo $deletebutton; ?>
                    <?php echo $statebutton; ?>
                    <?php echo $approvalbutton; ?>
                </div>
            <?php endif; ?>
            <!-- EOF buttons -->
        <?php endif; ?>

    <?php endif; ?>

    <?php if ($page_heading_shown) : ?>
        <!-- BOF page heading -->
        <h1 class="componentheading"><?php echo $this->params->get('page_heading'); ?></h1>
        <!-- EOF page heading -->
    <?php endif; ?>

    <?php echo ($mainAreaTag === 'section') ? '</header>' : ''; ?>
    <?php echo ($mainAreaTag === 'section') ? '<article>' : ''; ?>

    <?php
    // Titre + afterDisplayTitle + subtitle1/2/3
    $header_shown =
        $this->params->get('show_title', 1) ||
        $item->event->afterDisplayTitle ||
        isset($item->positions['subtitle1']) ||
        isset($item->positions['subtitle2']) ||
        isset($item->positions['subtitle3']);
    ?>

    <?php if ($header_shown) : ?>
    <header>
    <?php endif; ?>

        <?php if ($this->params->get('show_title', 1)) : ?>
            <!-- BOF item title -->
            <h<?php echo $itemTitleHeaderLevel; ?> class="contentheading">
                <span class="fc_item_title" itemprop="name">
                    <?php
                    $maxLen = (int) $this->params->get('title_cut_text', 200);
                    echo (StringHelper::strlen($item->title) > $maxLen)
                        ? StringHelper::substr($item->title, 0, $maxLen) . ' ...'
                        : $item->title;
                    ?>
                </span>
            </h<?php echo $itemTitleHeaderLevel; ?>>
            <!-- EOF item title -->
        <?php endif; ?>

        <?php if ($item->event->afterDisplayTitle) : ?>
            <div class="fc_afterDisplayTitle">
                <?php echo $item->event->afterDisplayTitle; ?>
            </div>
        <?php endif; ?>

        <?php
        // En mode builder, subtitle1/2/3 sont gérés par le builder.
        // On les rend dans le header UNIQUEMENT si on est en fallback statique
        // OU si la position n'apparaît pas dans le JSON builder.
        $builder_positions = [];
        if ($use_builder_layout) {
            foreach ($layout_data['rows'] as $_row) {
                foreach ($_row['zones'] as $_zone) {
                    $builder_positions[] = $_zone['name'];
                }
            }
        }
        foreach (['subtitle1', 'subtitle2', 'subtitle3'] as $subpos) :
            if ($use_builder_layout && in_array($subpos, $builder_positions, true)) continue;
            $subclass = $position_custom_class[$subpos] ?? ('flexi lineinfo ' . $subpos);
            if (isset($item->positions[$subpos])) :
        ?>
        <!-- BOF <?php echo $subpos; ?> block -->
        <div class="<?php echo $subclass; ?>">
            <?php foreach ($item->positions[$subpos] as $field) : ?>
                <div class="flexi element field_<?php echo $field->name; ?>">
                    <?php if ($field->label) : ?>
                        <span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
                    <?php endif; ?>
                    <div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <!-- EOF <?php echo $subpos; ?> block -->
        <?php endif; endforeach; ?>

    <?php if ($header_shown) : ?>
    </header>
    <?php endif; ?>

    <div class="fcclear"></div>

    <?php /* ================================================================
        ZONE PRINCIPALE — DATA-DRIVEN ou FALLBACK STATIQUE
       ================================================================ */ ?>

    <?php if ($use_builder_layout) : ?>

        <?php
        /**
         * RENDU DYNAMIQUE via layout_json
         * Chaque rangée → <div class="row g-2">
         * Chaque zone   → col-12 col-md-N
         * Les positions subtitle1/2/3 sont exclues ici car rendues dans le header
         */
        $static_positions = ['subtitle1', 'subtitle2', 'subtitle3'];

        foreach ($layout_data['rows'] as $row_index => $row) :
            // Vérifier si au moins une zone de cette rangée a du contenu
            $row_has_content = false;
            foreach ($row['zones'] as $zone) {
                if (
                    !in_array($zone['name'], $static_positions, true) &&
                    isset($item->positions[$zone['name']])
                ) {
                    $row_has_content = true;
                    break;
                }
            }
            if (!$row_has_content) continue;
        ?>

        <?php
        // Classes CSS de la rangée (définies dans le builder)
        $row_extra_class = !empty($row['rowClass']) ? ' ' . htmlspecialchars($row['rowClass'], ENT_QUOTES, 'UTF-8') : '';
        ?>
        <!-- BOF builder row <?php echo $row_index + 1; ?> -->
        <div class="row g-2 align-items-start<?php echo $row_extra_class; ?>">

            <?php foreach ($row['zones'] as $zone) :
                // Ignorer les positions de header déjà rendues
                if (in_array($zone['name'], $static_positions, true)) continue;

                $col_n          = fcpb_pct_to_col((float)($zone['colPct'] ?? 100));
                $zone_extra_css = !empty($zone['zoneClass']) ? ' ' . htmlspecialchars($zone['zoneClass'], ENT_QUOTES, 'UTF-8') : '';
                $col_class      = 'col-md-' . $col_n . $zone_extra_css;
                // Classe CSS interne : utiliser la classe custom si elle existe, sinon vide (fcpb_render_position applique le fallback)
                $custom_class   = $position_custom_class[$zone['name']] ?? '';

                fcpb_render_position($item, $zone['name'], $col_class, $custom_class);
            endforeach; ?>

        </div>
        <!-- EOF builder row <?php echo $row_index + 1; ?> -->

        <?php endforeach; ?>

    <?php else : ?>

        <?php /* ================================================================
            FALLBACK STATIQUE — layout original de modular.php
            Actif tant qu'aucun layout_json n'a été sauvegardé via le builder.
           ================================================================ */ ?>

        <?php /* ---- Tabs subtitle ---- */ ?>
        <?php
        $tabcount = 12;
        $createtabs = false;
        for ($tc = 1; $tc <= $tabcount; $tc++) {
            $createtabs = $createtabs || isset($item->positions['subtitle_tab' . $tc]);
        }
        ?>
        <?php if ($createtabs) : ?>
        <section>
            <div id="fc_subtitle_tabset" class="fctabber">
            <?php for ($tc = 1; $tc <= $tabcount; $tc++) :
                $tabpos_name  = 'subtitle_tab' . $tc;
                $tabpos_label = \Joomla\CMS\Language\Text::_($this->params->get('subtitle_tab' . $tc . '_label', $tabpos_name));
                $box_class    = $this->params->get('box_class_subtitle_tab' . $tc, 'flexi lineinfo');
                if (!isset($item->positions[$tabpos_name])) continue;
            ?>
                <div id="fc_<?php echo $tabpos_name; ?>" class="tabbertab">
                    <h3 class="tabberheading"><?php echo $tabpos_label; ?></h3>
                    <div class="<?php echo $box_class; ?>">
                        <?php foreach ($item->positions[$tabpos_name] as $field) : ?>
                            <div class="flexi element field_<?php echo $field->name; ?>">
                                <?php if ($field->label) : ?>
                                    <span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
                                <?php endif; ?>
                                <div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endfor; ?>
            </div>
        </section>
        <?php endif; ?>

        <div class="fcclear"></div>

        <?php /* ---- Image + Top ---- */ ?>
        <?php if (isset($item->positions['image']) || isset($item->positions['top'])) : ?>
            <aside class="flexi topblock">
                <?php if (isset($item->positions['image'])) : ?>
                    <div class="<?php echo $box_class_image; ?>">
                        <?php foreach ($item->positions['image'] as $field) : ?>
                            <div class="flexi element field_<?php echo $field->name; ?>">
                                <?php if ($field->label) : ?>
                                    <span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
                                <?php endif; ?>
                                <div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($item->positions['top'])) : ?>
                    <?php $top_cols = $this->params->get('top_cols', 'two'); ?>
                    <div class="<?php echo $box_class_top; ?> <?php echo $top_cols; ?>cols">
                        <ul class="flexi">
                            <?php foreach ($item->positions['top'] as $field) : ?>
                                <li class="flexi lvbox field_<?php echo $field->name; ?>">
                                    <div>
                                        <?php if ($field->label) : ?>
                                            <span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
                                        <?php endif; ?>
                                        <div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </aside>
        <?php endif; ?>

        <div class="fcclear"></div>

        <?php /* ---- Description ---- */ ?>
        <?php if (isset($item->positions['description'])) : ?>
            <div class="<?php echo $box_class_descr; ?>">
                <?php foreach ($item->positions['description'] as $field) : ?>
                    <?php if ($field->label) : ?>
                        <div class="desc-title label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></div>
                    <?php endif; ?>
                    <div class="desc-content field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="fcclear"></div>

        <?php /* ---- Tabs bottom ---- */ ?>
        <?php
        $createtabs = false;
        for ($tc = 1; $tc <= $tabcount; $tc++) {
            $createtabs = $createtabs || isset($item->positions['bottom_tab' . $tc]);
        }
        ?>
        <?php if ($createtabs) : ?>
        <section>
            <div id="fc_bottom_tabset" class="fctabber">
            <?php for ($tc = 1; $tc <= $tabcount; $tc++) :
                $tabpos_name  = 'bottom_tab' . $tc;
                $tabpos_label = \Joomla\CMS\Language\Text::_($this->params->get('bottom_tab' . $tc . '_label', $tabpos_name));
                $box_class    = $this->params->get('box_class_bottom_tab' . $tc, 'flexi lineinfo');
                if (!isset($item->positions[$tabpos_name])) continue;
            ?>
                <div id="fc_<?php echo $tabpos_name; ?>" class="tabbertab">
                    <h3 class="tabberheading"><?php echo $tabpos_label; ?></h3>
                    <div class="<?php echo $box_class; ?>">
                        <?php foreach ($item->positions[$tabpos_name] as $field) : ?>
                            <div class="flexi element field_<?php echo $field->name; ?>">
                                <?php if ($field->label) : ?>
                                    <span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
                                <?php endif; ?>
                                <div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endfor; ?>
            </div>
        </section>
        <?php endif; ?>

        <div class="fcclear"></div>

        <?php /* ---- Bottom ---- */ ?>
        <?php if (isset($item->positions['bottom'])) : ?>
            <?php $bottom_cols = $this->params->get('bottom_cols', 'two'); ?>
            <div class="<?php echo $box_class_bottom; ?> <?php echo $bottom_cols; ?>cols">
                <ul class="flexi">
                    <?php foreach ($item->positions['bottom'] as $field) : ?>
                        <li class="flexi lvbox field_<?php echo $field->name; ?>">
                            <div>
                                <?php if ($field->label) : ?>
                                    <span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
                                <?php endif; ?>
                                <div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

    <?php endif; /* end if $use_builder_layout */ ?>

    <?php /* ================================================================
        FOOTER — commun aux deux modes
       ================================================================ */ ?>

    <div class="fcclear"></div>

    <?php if ($item->event->afterDisplayContent) : ?>
        <!-- BOF afterDisplayContent -->
        <aside class="fc_afterDisplayContent">
            <?php echo $item->event->afterDisplayContent; ?>
        </aside>
        <!-- EOF afterDisplayContent -->
    <?php endif; ?>

    <?php echo ($mainAreaTag === 'section') ? '</article>' : ''; ?>

    <?php if ($this->params->get('comments') && !\Joomla\CMS\Factory::getApplication()->input->getInt('print', 0)) : ?>
        <!-- BOF comments -->
        <section class="comments">
        <?php
        if ($this->params->get('comments') == 1) :
            if (file_exists(JPATH_SITE . DS . 'components' . DS . 'com_jcomments' . DS . 'jcomments.php')) :
                require_once(JPATH_SITE . DS . 'components' . DS . 'com_jcomments' . DS . 'jcomments.php');
                echo JComments::showComments($item->id, 'com_flexicontent', $this->escape($item->title));
            endif;
        endif;
        if ($this->params->get('comments') == 2) :
            if (file_exists(JPATH_SITE . DS . 'plugins' . DS . 'content' . DS . 'jom_comment_bot.php')) :
                require_once(JPATH_SITE . DS . 'plugins' . DS . 'content' . DS . 'jom_comment_bot.php');
                echo jomcomment($item->id, 'com_flexicontent');
            endif;
        endif;
        ?>
        </section>
        <!-- EOF comments -->
    <?php endif; ?>

<?php echo '</' . $mainAreaTag . '>'; ?>