<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.FlexicontentFieldSearch
 * @version     2.2.0
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Language\Text;

class PlgSystemFlexifieldsearch extends CMSPlugin
{
    protected $app;
    protected $autoloadLanguage = true;

    public function onBeforeRender(): void
    {
        if (!$this->app->isClient('administrator')) {
            return;
        }

        $input     = $this->app->input;
        $option    = $input->get('option', '', 'cmd');
        $task      = $input->get('task', '', 'string');
        $view      = $input->get('view', '', 'string');
        $layout    = $input->get('layout', '', 'string');
        $component = $input->get('component', '', 'cmd');

        $isTargetView = (
            // Édition d'un champ Flexicontent
            ($option === 'com_flexicontent' && ($task === 'field.edit' || strpos($task, 'field.') === 0 || $view === 'field'))
            // Édition d'une catégorie Flexicontent
            || ($option === 'com_flexicontent' && $view === 'category' && $layout === 'edit')
            // Édition d'un type Flexicontent
            || ($option === 'com_flexicontent' && ($task === 'type.edit' || strpos($task, 'type.') === 0 || $view === 'type'))
            // Configuration globale Flexicontent
            || ($option === 'com_config' && $view === 'component' && $component === 'com_flexicontent')
            // Édition d'un template Flexicontent
            || ($option === 'com_flexicontent' && $view === 'template')
            // Édition d'un module (FAB masqué automatiquement si pas de champs FC)
            || ($option === 'com_modules' && $view === 'module' && $layout === 'edit')
            // Édition d'un menu (FAB masqué automatiquement si pas de champs FC)
            || ($option === 'com_menus' && $view === 'item' && $layout === 'edit')
        );

        if (!$isTargetView) {
            return;
        }

        $doc = $this->app->getDocument();
        $doc->addStyleDeclaration($this->getSearchCss());
        $doc->addScriptDeclaration($this->getSearchJs());
    }

    private function getSearchCss(): string
    {
        return <<<CSS
/* ===== FC FieldSearch — Bouton flottant ===== */

#fc-search-fab {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    z-index: 1060;
    width: 3.25rem;
    height: 3.25rem;
    border-radius: 50%;
    box-shadow: 0 4px 16px rgba(0,0,0,.35);
    transition: transform .15s ease, box-shadow .15s ease;
}
#fc-search-fab:hover {
    transform: scale(1.08);
    box-shadow: 0 6px 20px rgba(0,0,0,.45);
}
#fc-search-fab .fc-fab-icon {
    font-size: 1.25rem;
    line-height: 1;
}

#fc-fab-badge {
    position: absolute;
    top: -.25rem;
    right: -.25rem;
    min-width: 1.35rem;
    height: 1.35rem;
    font-size: .7rem;
    line-height: 1.35rem;
    padding: 0 .3rem;
    border-radius: 1rem;
    display: none;
}

#fc-search-panel {
    position: fixed;
    bottom: 6rem;
    right: 2rem;
    z-index: 1055;
    width: 360px;
    max-height: 80vh;
    overflow-y: auto;
    border-radius: .5rem;
    box-shadow: 0 8px 32px rgba(0,0,0,.3);
    opacity: 0;
    transform: translateY(1rem) scale(.97);
    pointer-events: none;
    transition: opacity .2s ease, transform .2s ease;
}
#fc-search-panel.fc-panel-open {
    opacity: 1;
    transform: translateY(0) scale(1);
    pointer-events: auto;
}
#fc-search-panel .fc-panel-header {
    border-bottom: 1px solid rgba(0,0,0,.1);
}
#fc-search-panel .fc-panel-body {
    max-height: calc(80vh - 8rem);
    overflow-y: auto;
}

.fc-tab-badge {
    font-size: .65rem;
    padding: .15em .45em;
    border-radius: 1rem;
    margin-left: .3rem;
    vertical-align: middle;
    font-weight: 700;
}
.fc-search-highlight {
    border-radius: .2rem;
    padding: 0 .15rem;
}
.fc-search-hidden {
    display: none !important;
}
.fc-no-results-msg {
    display: none;
    font-style: italic;
}
.fc-tab-result-item {
    cursor: pointer;
    border-left: 3px solid transparent;
    transition: border-color .1s, background-color .1s;
    border-radius: 0 .25rem .25rem 0;
}
.fc-tab-result-item:hover {
    border-left-color: var(--bs-primary, #0d6efd);
}

/* LIGHT MODE */
#fc-search-panel { background: #fff; color: #212529; }
#fc-search-panel .fc-panel-header { background: #f8f9fa; border-bottom-color: #dee2e6; }
#fc-search-panel .fc-card-body { background: #fff; }
#fc-search-panel .fc-panel-body { background: #fff; }
#fc-search-panel .fc-search-input { background: #fff; color: #212529; border: 1px solid #ced4da; }
#fc-search-panel .fc-search-input::placeholder { color: #6c757d; }
#fc-search-panel .fc-search-input:focus { background: #fff; color: #212529; border-color: #86b7fe; box-shadow: 0 0 0 .2rem rgba(13,110,253,.2); outline: none; }
#fc-search-panel .fc-clear-btn { background: #f8f9fa; color: #6c757d; border: 1px solid #ced4da; border-left: none; }
#fc-search-panel .fc-clear-btn:hover { background: #e9ecef; color: #212529; }
#fc-search-panel .fc-stats { color: #6c757d; }
#fc-search-panel .fc-panel-header .fw-semibold { color: #212529; }
#fc-search-panel .fc-tab-result-item { color: #212529; }
#fc-search-panel .fc-tab-result-item:hover { background-color: #f0f4ff; }
#fc-search-panel .border-top { border-color: #dee2e6 !important; }
#fc-search-panel .fc-btn-close { filter: none; }
#fc-search-panel .fc-tab-result-section-label { color: #0d6efd; }
.fc-search-highlight { background: #ffb514; color: #000; }
.tabbernav .fc-tab-badge.fc-badge-active { background-color: #ffb514 !important; color: #000 !important; }
.tabbernav .fc-tab-badge.fc-badge-zero   { background-color: #6c757d !important; color: #fff !important; }

/* DARK MODE */
[data-bs-theme="dark"] #fc-search-panel { background: #1e2128; color: #dee2e6; }
[data-bs-theme="dark"] #fc-search-panel .fc-panel-header { background: #16181d; border-bottom-color: #373b47; }
[data-bs-theme="dark"] #fc-search-panel .fc-card-body { background: #1e2128; }
[data-bs-theme="dark"] #fc-search-panel .fc-panel-body { background: #1e2128; }
[data-bs-theme="dark"] #fc-search-panel .fc-search-input { background: #2c2f3a; color: #dee2e6; border: 1px solid #4a4f5e; }
[data-bs-theme="dark"] #fc-search-panel .fc-search-input::placeholder { color: #6c757d; }
[data-bs-theme="dark"] #fc-search-panel .fc-search-input:focus { background: #2c2f3a; color: #fff; border-color: #6ea8fe; box-shadow: 0 0 0 .2rem rgba(110,168,254,.2); outline: none; }
[data-bs-theme="dark"] #fc-search-panel .fc-clear-btn { background: #2c2f3a; color: #adb5bd; border: 1px solid #4a4f5e; border-left: none; }
[data-bs-theme="dark"] #fc-search-panel .fc-clear-btn:hover { background: #373b47; color: #fff; }
[data-bs-theme="dark"] #fc-search-panel .fc-stats { color: #6c757d; }
[data-bs-theme="dark"] #fc-search-panel .fc-panel-header .fw-semibold { color: #dee2e6; }
[data-bs-theme="dark"] #fc-search-panel .fc-tab-result-item { color: #dee2e6; }
[data-bs-theme="dark"] #fc-search-panel .fc-tab-result-item:hover { background-color: #2a2f3d; }
[data-bs-theme="dark"] #fc-search-panel .border-top { border-color: #373b47 !important; }
[data-bs-theme="dark"] #fc-search-panel .fc-btn-close { filter: invert(1) grayscale(1); }
[data-bs-theme="dark"] .fc-search-highlight { background: #b8860b; color: #fff; }
[data-bs-theme="dark"] #fc-search-panel .fc-tab-result-section-label { color: #6ea8fe; }
[data-bs-theme="dark"] .tabbernav .fc-tab-badge.fc-badge-active { background-color: #ffb514 !important; color: #000 !important; }
[data-bs-theme="dark"] .tabbernav .fc-tab-badge.fc-badge-zero   { background-color: #4a4f5e !important; color: #adb5bd !important; }
CSS;
    }

    private function getSearchJs(): string
    {
        $strings = json_encode([
            'placeholder' => Text::_('PLG_SYSTEM_FLEXICONTENT_FIELDSEARCH_PLACEHOLDER'),
            'clear'       => Text::_('PLG_SYSTEM_FLEXICONTENT_FIELDSEARCH_CLEAR'),
            'clearTitle'  => Text::_('PLG_SYSTEM_FLEXICONTENT_FIELDSEARCH_CLEAR_TITLE'),
            'noResults'   => Text::_('PLG_SYSTEM_FLEXICONTENT_FIELDSEARCH_NO_RESULTS'),
            'statsSuffix' => Text::_('PLG_SYSTEM_FLEXICONTENT_FIELDSEARCH_STATS_SUFFIX'),
            'panelTitle'  => Text::_('PLG_SYSTEM_FLEXICONTENT_FIELDSEARCH_PANEL_TITLE'),
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

        $jsStringsBlock = "var fcStrings = {$strings};";

        $jsBody = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {

    // -------------------------------------------------------
    // 1. Injection du DOM : bouton FAB + panneau
    // -------------------------------------------------------
    var fab = document.createElement('button');
    fab.id = 'fc-search-fab';
    fab.type = 'button';
    fab.className = 'btn btn-primary';
    fab.setAttribute('aria-label', fcStrings.panelTitle);
    fab.setAttribute('title', fcStrings.panelTitle);
    fab.innerHTML = [
        '<span class="fc-fab-icon" aria-hidden="true">',
        '  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">',
        '    <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.099zm-5.242 1.156a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11z"/>',
        '  </svg>',
        '</span>',
        '<span id="fc-fab-badge" class="badge bg-warning text-dark position-absolute"></span>'
    ].join('');
    document.body.appendChild(fab);

    var panel = document.createElement('div');
    panel.id = 'fc-search-panel';
    panel.className = 'card border-0';
    panel.innerHTML = [
        '<div class="fc-panel-header card-header d-flex align-items-center gap-2 py-2 px-3">',
        '  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="flex-shrink-0 text-primary" viewBox="0 0 16 16">',
        '    <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.099zm-5.242 1.156a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11z"/>',
        '  </svg>',
        '  <span class="fw-semibold small flex-grow-1">' + fcStrings.panelTitle + '</span>',
        '  <button type="button" id="fc-panel-close" class="btn-close fc-btn-close btn-sm" aria-label="' + fcStrings.clearTitle + '"></button>',
        '</div>',
        '<div class="fc-card-body py-2 px-3">',
        '  <div class="input-group input-group-sm">',
        '    <input type="text" id="fc-fieldsearch-input" class="form-control fc-search-input" placeholder="' + fcStrings.placeholder + '" autocomplete="off" />',
        '    <button type="button" id="fc-fieldsearch-clear" class="btn fc-clear-btn" title="' + fcStrings.clearTitle + '">',
        '      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">',
        '        <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/>',
        '      </svg>',
        '    </button>',
        '  </div>',
        '  <div id="fc-fieldsearch-stats" class="fc-stats mt-1 small"></div>',
        '</div>',
        '<div class="fc-panel-body" id="fc-tab-results"></div>'
    ].join('');
    document.body.appendChild(panel);

    // -------------------------------------------------------
    // 2. Masquer le FAB si aucun élément Flexicontent détecté
    // (utile pour modules et menus non-Flexicontent)
    // -------------------------------------------------------
    var hasFcContent = document.querySelector(
        'fieldset.panelform, .fc-form-tbl, [id*="jform_attribs"], .fctabber, .tabbertab, .control-group'
    );
    if (!hasFcContent) {
        fab.style.display = 'none';
        panel.style.display = 'none';
        return;
    }

    // -------------------------------------------------------
    // 3. Fonctions utilitaires
    // -------------------------------------------------------
    function getFieldsetText(fs) {
        var parts = [];

        var lbl = fs.querySelector('label.label-fcinner');
        if (lbl) parts.push(lbl.textContent || '');

        var propLbl = fs.querySelector('label.fc-prop-lbl');
        if (propLbl) parts.push(propLbl.textContent || '');

        var controlLbl = fs.querySelector('label[data-for], label[id$="-lbl"]');
        if (controlLbl) parts.push(controlLbl.textContent || '');

        var descEl = fs.querySelector('.form-text');
        if (descEl) parts.push(descEl.textContent || '');

        var popoverEl = fs.querySelector('[data-bs-content]');
        if (popoverEl) parts.push(popoverEl.getAttribute('data-bs-content') || '');

        var contentEl = fs.querySelector('[data-content]');
        if (contentEl) parts.push(contentEl.getAttribute('data-content') || '');

        var titleEl = fs.querySelector('[data-title]');
        if (titleEl) parts.push(titleEl.getAttribute('data-title') || '');

        fs.childNodes.forEach(function(node) {
            if (node.nodeType === Node.TEXT_NODE) {
                parts.push(node.textContent);
            }
        });

        fs.querySelectorAll(':scope > h2, :scope > h3, :scope > h4, :scope > h5, :scope > h6').forEach(function(h) {
            parts.push(h.textContent);
        });

        return parts.join(' ').toLowerCase().replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ');
    }

    function getAllParentTabs(el) {
        var tabs = [];
        var p = el.parentElement;
        while (p) {
            if (p.classList && (p.classList.contains('tabbertab') || p.tagName === 'JOOMLA-TAB-ELEMENT')) {
                tabs.push(p);
            }
            p = p.parentElement;
        }
        return tabs;
    }

    function getTabLabel(tabId) {
        var nav = tabNavMap[tabId];
        if (!nav) return tabId;
        var span = nav.querySelector('span:not(.accordion-icon):not(.text-muted):not(.fc-tab-badge)');
        return span ? span.textContent.trim() : nav.textContent.trim().replace(/\s+/g, ' ');
    }

    function escapeHtml(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function activateTab(navEl) {
        ['mousedown', 'click'].forEach(function(evtName) {
            navEl.dispatchEvent(new MouseEvent(evtName, {
                bubbles: true, cancelable: true, view: window
            }));
        });
    }

    // -------------------------------------------------------
    // 4. Collecte des éléments cherchables
    // -------------------------------------------------------

    // Type 1 : fieldsets Flexicontent (panelform)
    var fieldsetItems = Array.from(
        document.querySelectorAll('fieldset.panelform, fieldset[id*="jform_attribs"]')
    ).filter(function(fs) {
        // Exclure les fieldsets enfants d'un autre fieldset panelform (ex: boutons radio)
        var p = fs.parentElement;
        while (p) {
            if (p.tagName === 'FIELDSET' && (
                p.classList.contains('panelform') ||
                (p.id && p.id.indexOf('jform_attribs') === 0)
            )) {
                return false;
            }
            p = p.parentElement;
        }
        // Exclure les séparateurs de section sans vrai label de champ
        if (!fs.querySelector('label.label-fcinner') && !fs.querySelector('label.fc-prop-lbl')) {
            return false;
        }
        return true;
    });

    // Type 2 : lignes de tableau (field_basic_props)
    var tableRowItems = Array.from(
        document.querySelectorAll('table.fc-form-tbl tr')
    ).filter(function(tr) {
        return tr.querySelector('label.fc-prop-lbl');
    });

    // Type 3 : control-group Joomla (config globale, catégorie, type, modules, menus)
    var controlGroupItems = Array.from(
        document.querySelectorAll('.control-group')
    ).filter(function(cg) {
        return cg.querySelector('label[data-for], label[id$="-lbl"]');
    });

    var searchableFieldsets = fieldsetItems.concat(tableRowItems).concat(controlGroupItems);

    // -------------------------------------------------------
    // 5. Collecte des onglets (Flexicontent + Joomla natif)
    // -------------------------------------------------------
    var tabNavItems = document.querySelectorAll('.tabbernav > li > a.tabberheading');
    var tabContents = Array.from(document.querySelectorAll('.tabbertab'));
    var tabNavMap   = {};

    // Onglets Flexicontent tabber
    tabNavItems.forEach(function(navA) {
        var li  = navA.parentElement;
        var nav = li.parentElement;
        var idx = Array.from(nav.querySelectorAll('li')).indexOf(li);
        var tabs = Array.from(nav.parentElement.querySelectorAll(':scope > .tabbertab'));
        if (tabs[idx]) {
            tabNavMap[tabs[idx].id] = navA;
        }
    });

    // Onglets Joomla natif (joomla-tab-element)
    Array.from(document.querySelectorAll('joomla-tab-element')).forEach(function(tabEl) {
        tabContents.push(tabEl);
        var navBtn = document.querySelector('button[aria-controls="' + tabEl.id + '"]');
        if (navBtn) {
            tabNavMap[tabEl.id] = navBtn;
        }
    });

    // Badges sur les onglets tabber
    tabNavItems.forEach(function(navA) {
        if (!navA.querySelector('.fc-tab-badge')) {
            var b = document.createElement('span');
            b.className = 'fc-tab-badge badge fc-badge-zero d-none';
            navA.appendChild(b);
        }
    });

    // Messages "aucun résultat" dans chaque tab
    tabContents.forEach(function(tab) {
        if (!tab.querySelector('.fc-no-results-msg')) {
            var msg = document.createElement('p');
            msg.className = 'fc-no-results-msg text-muted fst-italic px-3 py-2 small';
            msg.textContent = fcStrings.noResults;
            tab.insertBefore(msg, tab.firstChild);
        }
    });

    // -------------------------------------------------------
    // 6. Recherche principale
    // -------------------------------------------------------
    var debounceTimer = null;

    function doSearch(query) {
        query = query.trim().toLowerCase();

        var tabCounts  = {};
        var tabResults = {};
        tabContents.forEach(function(tab) {
            tabCounts[tab.id]  = 0;
            tabResults[tab.id] = [];
        });

        var totalVisible   = 0;
        var totalFieldsets = searchableFieldsets.length;

        // Nettoyage highlights
        document.querySelectorAll('.fc-search-highlight').forEach(function(el) {
            var p = el.parentNode;
            p.replaceChild(document.createTextNode(el.textContent), el);
            p.normalize();
        });

        searchableFieldsets.forEach(function(fs) {
            var parentTabs = getAllParentTabs(fs);

            if (!query) {
                fs.classList.remove('fc-search-hidden');
                return;
            }

            var matches = getFieldsetText(fs).indexOf(query) !== -1;

            if (matches) {
                fs.classList.remove('fc-search-hidden');
                totalVisible++;

                // Compteur sur tous les onglets parents
                parentTabs.forEach(function(tab) {
                    tabCounts[tab.id] = (tabCounts[tab.id] || 0) + 1;
                });

                // Résultat cliquable dans l'onglet le plus proche uniquement
                var closestTab = parentTabs[0];
                if (closestTab) {
                    var lbl = fs.querySelector('label.label-fcinner, label.fc-prop-lbl, label[data-for], label[id$="-lbl"]');
                    if (lbl) {
                        var labelText = lbl.textContent.trim().replace(/\s+/g, ' ');
                        if (labelText && labelText !== '—') {
                            tabResults[closestTab.id].push({ label: labelText, fs: fs });
                        }
                    }
                }

                highlightInFieldset(fs, query);
            } else {
                fs.classList.add('fc-search-hidden');
            }
        });

        // Badges + messages no-result par onglet
        tabContents.forEach(function(tab) {
            var navEl    = tabNavMap[tab.id];
            var noResMsg = tab.querySelector('.fc-no-results-msg');
            var count    = tabCounts[tab.id] || 0;

            if (!query) {
                if (navEl) {
                    var b = navEl.querySelector('.fc-tab-badge');
                    if (b) b.classList.add('d-none');
                }
                if (noResMsg) noResMsg.style.display = 'none';
                return;
            }

            if (navEl) {
                var b = navEl.querySelector('.fc-tab-badge');
                if (!b) {
                    b = document.createElement('span');
                    b.className = 'fc-tab-badge badge';
                    navEl.appendChild(b);
                }
                b.classList.remove('d-none', 'fc-badge-active', 'fc-badge-zero');
                b.classList.add(count > 0 ? 'fc-badge-active' : 'fc-badge-zero');
                b.textContent = count;
            }

            if (noResMsg) {
                noResMsg.style.display = (count === 0 && query) ? 'block' : 'none';
            }
        });

        // Stats
        var statsEl = document.getElementById('fc-fieldsearch-stats');
        if (statsEl) {
            statsEl.textContent = query
                ? totalVisible + ' / ' + totalFieldsets + ' ' + fcStrings.statsSuffix
                : '';
        }

        // Badge FAB
        var fabBadge = document.getElementById('fc-fab-badge');
        if (fabBadge) {
            fabBadge.textContent = totalVisible;
            fabBadge.style.display = (query && totalVisible > 0) ? 'block' : 'none';
        }

        renderTabResults(tabResults, query);
    }

    // -------------------------------------------------------
    // 7. Rendu des résultats dans le panneau
    // -------------------------------------------------------
    function renderTabResults(tabResults, query) {
        var container = document.getElementById('fc-tab-results');
        if (!container) return;
        container.innerHTML = '';
        if (!query) return;

        tabContents.forEach(function(tab) {
            var results = tabResults[tab.id] || [];

            // Pas de résultats cliquables : badge à 0/gris, pas de section
            if (results.length === 0) {
                var navEl = tabNavMap[tab.id];
                if (navEl) {
                    var b = navEl.querySelector('.fc-tab-badge');
                    if (b && b.classList.contains('fc-badge-active')) {
                        b.classList.remove('fc-badge-active');
                        b.classList.add('fc-badge-zero');
                        b.textContent = '0';
                    }
                }
                return;
            }

            var section = document.createElement('div');
            section.className = 'border-top border-secondary px-3 py-2';

            var heading = document.createElement('div');
            heading.className = 'd-flex align-items-center gap-2 mb-1';
            heading.innerHTML = [
                '<span class="small fw-semibold fc-tab-result-section-label">' + escapeHtml(getTabLabel(tab.id)) + '</span>',
                '<span class="badge bg-primary rounded-pill">' + results.length + '</span>'
            ].join('');
            section.appendChild(heading);

            results.forEach(function(item) {
                var row = document.createElement('div');
                row.className = 'fc-tab-result-item small ps-2 py-1';
                row.textContent = item.label;

                row.addEventListener('click', function() {
                    var parentTabs = getAllParentTabs(item.fs).reverse();
                    var delay = 0;
                    parentTabs.forEach(function(t) {
                        var navEl = tabNavMap[t.id];
                        if (navEl) {
                            setTimeout(function() { activateTab(navEl); }, delay);
                            delay += 80;
                        }
                    });
                    setTimeout(function() {
                        item.fs.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, delay + 150);
                    closePanel();
                });

                section.appendChild(row);
            });

            container.appendChild(section);
        });
    }

    // -------------------------------------------------------
    // 8. Highlight
    // -------------------------------------------------------
    function highlightInFieldset(fs, query) {
        fs.querySelectorAll([
            'label.label-fcinner',
            'label.fc-prop-lbl',
            'label[data-for]',
            'label[id$="-lbl"]',
            'h2, h3, h4, h5, h6'
        ].join(', ')).forEach(function(lbl) {
            highlightTextNode(lbl, query);
        });
    }

    function highlightTextNode(el, query) {
        if (!query) return;
        var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null, false);
        var nodes  = [];
        var node;
        while ((node = walker.nextNode())) nodes.push(node);

        nodes.forEach(function(textNode) {
            var text      = textNode.textContent;
            var lowerText = text.toLowerCase();
            var idx       = lowerText.indexOf(query);
            if (idx === -1) return;

            var frag = document.createDocumentFragment();
            var last = 0;
            while (idx !== -1) {
                frag.appendChild(document.createTextNode(text.slice(last, idx)));
                var mark = document.createElement('mark');
                mark.className = 'fc-search-highlight bg-warning text-dark';
                mark.textContent = text.slice(idx, idx + query.length);
                frag.appendChild(mark);
                last = idx + query.length;
                idx  = lowerText.indexOf(query, last);
            }
            frag.appendChild(document.createTextNode(text.slice(last)));
            textNode.parentNode.replaceChild(frag, textNode);
        });
    }

    // -------------------------------------------------------
    // 9. Ouverture / fermeture du panneau
    // -------------------------------------------------------
    function openPanel() {
        panel.classList.add('fc-panel-open');
        fab.classList.add('active');
        setTimeout(function() {
            var inp = document.getElementById('fc-fieldsearch-input');
            if (inp) inp.focus();
        }, 50);
    }

    function closePanel() {
        panel.classList.remove('fc-panel-open');
        fab.classList.remove('active');
    }

    fab.addEventListener('click', function(e) {
        e.stopPropagation();
        if (panel.classList.contains('fc-panel-open')) {
            closePanel();
        } else {
            openPanel();
        }
    });

    var closeBtn = document.getElementById('fc-panel-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', closePanel);
    }

    document.addEventListener('click', function(e) {
        if (
            panel.classList.contains('fc-panel-open') &&
            !panel.contains(e.target) &&
            !fab.contains(e.target)
        ) {
            closePanel();
        }
    });

    // -------------------------------------------------------
    // 10. Event listeners input
    // -------------------------------------------------------
    var searchInput = document.getElementById('fc-fieldsearch-input');
    var clearBtn    = document.getElementById('fc-fieldsearch-clear');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var val = this.value;
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() { doSearch(val); }, 150);
        });

        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (this.value) {
                    this.value = '';
                    doSearch('');
                } else {
                    closePanel();
                }
            }
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
            }
            doSearch('');
        });
    }

    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            openPanel();
        }
    });

    console.log('[FC FieldSearch] Plugin chargé — ' + searchableFieldsets.length + ' champs indexés.');
});
JS;

        return $jsStringsBlock . "\n" . $jsBody;
    }
}
