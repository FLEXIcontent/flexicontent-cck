<?php
/**
 * @version     FLEXIcontent Layout Editor — with visual page builder
 * @package     FLEXIcontent
 * @license     GNU/GPL v2
 *
 * Builder intégré : drag & drop zones, resize handles entre colonnes,
 * champs FLEXIcontent assignables par drag, export JSON dans adminForm.
 */

defined('_JEXEC') or die('Restricted access');

if (FLEXI_J40GE) \Joomla\CMS\Toolbar\ToolbarHelper::inlinehelp();

jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.file');
jimport('joomla.filesystem.path');

if ( !$this->layout->name ) die('Template folder does not exist');

$this->document->addScript(\Joomla\CMS\Uri\Uri::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', array('version' => FLEXI_VHASH));
$this->document->addStyleSheet(\Joomla\CMS\Uri\Uri::root(true).'/components/com_flexicontent/assets/css/tabber.css', array('version' => FLEXI_VHASH));
$this->document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');

$tip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
$btn_class = FLEXI_J30GE ? 'btn' : 'fc_button fcsimple';

$app = \Joomla\CMS\Factory::getApplication();
$db  = \Joomla\CMS\Factory::getDbo();
\Joomla\CMS\Factory::getApplication()->setUserState('editor.source.syntax', 'css');

// -----------------------------------------------------------------------
// Codemirror check
// -----------------------------------------------------------------------
$query = $db->getQuery(true)
    ->select('COUNT(*)')
    ->from('#__extensions as a')
    ->where('(a.name =' . $db->quote('plg_editors_codemirror') . ' AND a.enabled = 1)');
$db->setQuery($query);
$use_editor = (boolean)$db->loadResult();
if (!$use_editor) {
    $app->enqueueMessage(\Joomla\CMS\Language\Text::_('Codemirror est désactivé, un textarea simple sera utilisé'), 'warning');
}

\Joomla\CMS\Language\Text::script('FLEXI_TMPLS_LOAD_FILE_BEFORE_SAVING', true);
\Joomla\CMS\Language\Text::script('FLEXI_TMPLS_SAVE_BUILT_IN_TEMPLATE_FILE_WARNING', true);
\Joomla\CMS\Language\Text::script('FLEXI_SAVING', true);
\Joomla\CMS\Language\Text::script('FLEXI_LOADING', true);

// -----------------------------------------------------------------------
// Existing layout_json (from saved params)
// -----------------------------------------------------------------------
// Lire la valeur depuis conf->attribs (tableau issu de Registry->toArray() en base)
// La valeur est stockée en base64 pour survivre au getArray() de Joomla.
$saved_layout_json = '';
$raw = '';
if (!empty($this->conf->attribs['layout_json'])) {
    $raw = $this->conf->attribs['layout_json'];
} else {
    $raw = $this->layout->params->getValue('layout_json', 'attribs', '');
}
if ($raw) {
    // Tenter un décodage base64 ; si invalide, utiliser tel quel (rétrocompat)
    $decoded = base64_decode($raw, true);
    $saved_layout_json = ($decoded !== false && json_decode($decoded) !== null)
        ? $decoded
        : $raw;
}

// -----------------------------------------------------------------------
// Code insertion snippets (kept from original)
// -----------------------------------------------------------------------
$code_btn_lbls = array(
    'fieldPosXML'    => 'FLEXI_ADD_FIELD_POSITION_XML',
    'paramTextXML'   => 'FLEXI_ADD_PARAMETER_TEXT_XML',
    'paramRadioXML'  => 'FLEXI_ADD_PARAMETER_RADIO_XML',
    'paramSelectXML' => 'FLEXI_ADD_PARAMETER_SELECT_XML',
    'itemPosHTML'    => 'FLEXI_ADD_FIELD_POSITION_PHP',
    'catPosHTML'     => 'FLEXI_ADD_FIELD_POSITION_PHP',
    'itemFieldDisplay' => 'FLEXI_ADD_FIELD_DISPLAY',
    'catFieldDisplay'  => 'FLEXI_ADD_FIELD_DISPLAY',
);
$code_btn_tips = array(
    'fieldPosXML'    => 'Place new position inside &lt;fieldgroup&gt;&lt;/fieldgroup&gt; and set a name.',
    'paramTextXML'   => 'Place new parameter inside &lt;fields ...&gt;&lt;/fields&gt; and set a unique name.',
    'paramRadioXML'  => 'Place new radio parameter inside &lt;fields ...&gt;&lt;/fields&gt;.',
    'paramSelectXML' => 'Place new select parameter inside &lt;fields ...&gt;&lt;/fields&gt;.',
    'itemPosHTML'    => 'Loops through fields of a position — place outside other position code.',
    'catPosHTML'     => 'Loops through fields of a position — place outside other position code.',
    'itemFieldDisplay' => 'Displays a single field manually (add it to renderonly position first).',
    'catFieldDisplay'  => 'Displays a single field manually.',
);
$code_btn_rawcode = array(
'fieldPosXML' => "\n<group>myposition</group>\n",
'paramTextXML' => "\n<field name=\"my_param01\" type=\"text\" size=\"10\" default=\"Default value\" label=\"Label\" description=\"Description\" />\n",
'paramRadioXML' => "\n<field name=\"my_param01\" type=\"radio\" default=\"two\" label=\"Label\" description=\"Description\" class=\"btn-group btn-group-yesno\">\n\t<option value=\"one\">Label one</option>\n\t<option value=\"two\">Label two</option>\n</field>\n",
'paramSelectXML' => "\n<field name=\"my_param01\" type=\"list\" default=\"2\" label=\"Label\" description=\"Description\">\n\t<option value=\"1\">Case 1</option>\n\t<option value=\"2\">Case 2</option>\n</field>\n",
'itemPosHTML' => "\n<!-- BOF myposition block -->\n<?php \$_pos = \"myposition\"; ?>\n<?php if (isset(\$item->positions[\$_pos])) : ?>\n<div class=\"flexi lineinfo <?php echo \$_pos; ?> group\">\n\t<?php foreach (\$item->positions[\$_pos] as \$field) : ?>\n\t<div class=\"flexi element field_<?php echo \$field->name; ?>\">\n\t\t<?php if (\$field->label) : ?><span class=\"flexi label field_<?php echo \$field->name; ?>\"><?php echo \$field->label; ?></span><?php endif; ?>\n\t\t<div class=\"flexi value field_<?php echo \$field->name; ?>\"><?php echo \$field->display; ?></div>\n\t</div>\n\t<?php endforeach; ?>\n</div>\n<?php endif; ?>\n<!-- EOF myposition block -->\n",
'itemFieldDisplay' => "\n<?php echo \$item->fields[\"fieldname\"]->display; ?>\n",
);
$code_btn_rawcode['catPosHTML']      = $code_btn_rawcode['itemPosHTML'];
$code_btn_rawcode['catFieldDisplay'] = $code_btn_rawcode['itemFieldDisplay'];

$pfx = $this->layout->view == 'category' ? 'FCC' : 'FCI';
?>

<?php /* ============================================================
   STYLES DU PAGE BUILDER
   ============================================================ */ ?>
<style>
/* ---- Builder wrapper ---- */
#fc-page-builder{padding:12px 0;user-select:none;}
#fc-page-builder *{box-sizing:border-box;}

/* ---- Toolbar ---- */
.fcpb-toolbar{display:flex;align-items:center;gap:8px;padding:8px 10px;background:#f8f8f6;border:1px solid #ddd;border-radius:6px;margin-bottom:12px;flex-wrap:wrap;}
.fcpb-toolbar-title{font-size:13px;font-weight:600;color:#333;margin-right:4px;}
.fcpb-toolbar-sep{width:1px;height:20px;background:#ddd;margin:0 2px;}
.fcpb-btn{font-size:12px;padding:5px 10px;background:#fff;border:1px solid #ccc;border-radius:4px;cursor:pointer;display:inline-flex;align-items:center;gap:5px;color:#333;line-height:1;}
.fcpb-btn:hover{background:#eef;border-color:#99a;}
.fcpb-btn.success{background:#e6f4ea;border-color:#5a9;color:#185;}
.fcpb-btn.success:hover{background:#d0ecda;}
.fcpb-btn.primary{background:#e8eeff;border-color:#79a;color:#147;}
.fcpb-btn.danger{background:#fff0f0;border-color:#c77;color:#811;}

/* ---- Canvas ---- */
#fcpb-canvas{display:flex;flex-direction:column;gap:10px;min-height:80px;}

/* ---- Row ---- */
.fcpb-row{border:1px dashed #bbb;border-radius:8px;padding:8px 10px 10px;position:relative;background:#fafafa;}
.fcpb-row.drag-over-row{border-color:#6699cc;background:#f0f4ff;}
.fcpb-row-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;}
.fcpb-row-label{font-size:11px;color:#888;font-weight:500;text-transform:uppercase;letter-spacing:.4px;}
.fcpb-row-actions{display:flex;gap:4px;}

/* ---- Zone grid inside a row ---- */
.fcpb-row-grid{display:flex;gap:0;align-items:stretch;position:relative;}

/* ---- Zone ---- */
.fcpb-zone{background:#fff;border:1px solid #dde;border-radius:6px;padding:8px;position:relative;min-height:70px;overflow:hidden;flex-shrink:0;}
.fcpb-zone.dragging-zone{opacity:.35;border-style:dashed;}
.fcpb-zone.drop-target{border-color:#5599ee;background:#eef4ff;}
.fcpb-zone-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;}
.fcpb-zone-name{border:none;background:transparent;font-size:12px;font-weight:600;color:#333;width:100px;padding:0;cursor:text;}
.fcpb-zone-name:focus{outline:1px solid #99b;border-radius:2px;background:#fff;padding:1px 3px;}
.fcpb-zone-meta{font-size:10px;color:#999;white-space:nowrap;}
.fcpb-zone-del{font-size:11px;padding:2px 5px;background:transparent;border:1px solid #ecc;border-radius:3px;cursor:pointer;color:#c55;line-height:1;}
.fcpb-zone-del:hover{background:#fee;}

/* ---- Resize handle between zones ---- */
.fcpb-resize-handle{width:8px;flex-shrink:0;cursor:col-resize;display:flex;align-items:center;justify-content:center;position:relative;z-index:10;}
.fcpb-resize-handle::after{content:'';display:block;width:3px;height:32px;background:#ccc;border-radius:2px;transition:background .15s;}
.fcpb-resize-handle:hover::after,.fcpb-resize-handle.resizing::after{background:#5599ee;}

/* ---- Field chips inside a zone ---- */
.fcpb-fields{display:flex;flex-wrap:wrap;gap:4px;min-height:28px;}
.fcpb-field-chip{display:inline-flex;align-items:center;gap:3px;font-size:11px;padding:3px 7px;background:#f0f0f8;border:1px solid #d0d0e8;border-radius:99px;cursor:grab;white-space:nowrap;color:#445;}
.fcpb-field-chip.is-core::before{content:'●';font-size:7px;color:#5599ee;margin-right:1px;}
.fcpb-field-chip .chip-del{border:none;background:transparent;color:#aaa;cursor:pointer;font-size:13px;line-height:1;padding:0 0 0 2px;}
.fcpb-field-chip .chip-del:hover{color:#c44;}
.fcpb-zone-empty-hint{font-size:11px;color:#bbb;font-style:italic;padding:4px 0;}
.fcpb-zone.drop-target .fcpb-zone-empty-hint{color:#5599ee;}

/* ---- Layout deux colonnes : pool gauche / canvas droite ---- */
#fcpb-body{display:flex;gap:10px;align-items:flex-start;}
#fcpb-sidebar{width:200px;flex-shrink:0;position:sticky;top:10px;}
#fcpb-main{flex:1;min-width:0;}

/* ---- Filtres pool ---- */
#fcpb-pool select{font-size:11px;padding:2px 4px;border:1px solid #ddd;border-radius:3px;color:#555;max-width:100%;margin-bottom:6px;}
.fcpb-field-chip.fcpb-hidden{display:none;}

/* ---- Field pool (colonne gauche) ---- */
#fcpb-pool{border:1px solid #e0e0e0;border-radius:6px;padding:10px 10px;background:#fff;}
.fcpb-pool-title{font-size:11px;color:#888;font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-bottom:7px;}
.fcpb-pool-section{margin-bottom:8px;}
.fcpb-pool-section-label{font-size:10px;color:#aaa;text-transform:uppercase;letter-spacing:.3px;margin-bottom:4px;padding:2px 0;border-bottom:1px solid #f0f0f0;}
#fcpb-pool-fields{display:flex;flex-direction:column;gap:4px;}
#fcpb-pool-fields .fcpb-field-chip{cursor:grab;justify-content:flex-start;}
#fcpb-pool-fields .fcpb-field-chip:hover{background:#e8e8f8;border-color:#99a;}
.fcpb-pool-empty{font-size:11px;color:#ccc;font-style:italic;padding:4px 0;}

/* ---- Row class input ---- */
.fcpb-row-class-input{border:none;background:transparent;font-size:11px;color:#888;width:130px;padding:1px 4px;border-radius:3px;font-style:italic;}
.fcpb-row-class-input:focus{outline:1px solid #99b;background:#fff;color:#333;font-style:normal;}
.fcpb-row-class-input::placeholder{color:#ccc;}

/* ---- Zone class input ---- */
.fcpb-zone-class-input{border:none;background:transparent;font-size:10px;color:#aaa;width:90px;padding:1px 3px;border-radius:3px;font-style:italic;}
.fcpb-zone-class-input:focus{outline:1px solid #99b;background:#fff;color:#333;font-style:normal;}
.fcpb-zone-class-input::placeholder{color:#ddd;}

/* ---- Row drag handle ---- */
.fcpb-row-handle{cursor:grab;color:#bbb;font-size:16px;padding:0 6px 0 0;line-height:1;user-select:none;flex-shrink:0;}
.fcpb-row-handle:hover{color:#5599ee;}
.fcpb-row.dragging-row{opacity:.35;border-style:dashed;}
.fcpb-row.drop-row-before{border-top:3px solid #5599ee;}
.fcpb-row.drop-row-after{border-bottom:3px solid #5599ee;}

/* ---- Add-zone button inside a row ---- */
.fcpb-add-zone{font-size:11px;padding:4px 9px;background:transparent;border:1px dashed #bbb;border-radius:4px;cursor:pointer;color:#888;margin-top:6px;}
.fcpb-add-zone:hover{border-color:#5599ee;color:#147;}

/* ---- PHP output panel ---- */
#fcpb-php-output{display:none;margin-top:10px;background:#282c34;color:#abb2bf;border-radius:6px;padding:14px;font-family:monospace;font-size:12px;line-height:1.7;white-space:pre-wrap;max-height:320px;overflow-y:auto;}
</style>

<?php /* ============================================================
   SCRIPTS DU PAGE BUILDER (chargés avant le DOM inline)
   ============================================================ */ ?>
<script>
/* ================================================================
   FC PAGE BUILDER — données initiales et config
   ================================================================ */
(function(global){

/* ---- Champs disponibles depuis PHP ---- */
global.FCPB_FIELDS = [
<?php
$allFields = $this->fields;
foreach ($allFields as $field) :
    $label    = addslashes($field->label);
    $name     = addslashes($field->name);
    $iscore   = $field->iscore ? 'true' : 'false';
    $type_ids = !empty($field->type_ids) ? implode(' ', array_map(function($id){ return 'content_type_'.$id; }, $field->type_ids)) : '';
    $ftype    = 'field_type_' . $field->field_type;
    $classes  = trim('fcpb-field-chip ' . ($field->iscore ? 'is-core ' : '') . $type_ids . ' ' . $ftype);
    echo "  {name:'{$name}', label:'{$label}', isCore:{$iscore}, classes:'" . addslashes($classes) . "'},\n";
endforeach;
?>
];

/* ---- État sauvegardé (ou défaut) ---- */
var savedJson = <?php echo $saved_layout_json ? json_encode($saved_layout_json) : '""'; ?>;

var defaultLayout = {
    rows: [
        {id:'r1', rowClass:'', zones:[
            {id:'z1', name:'subtitle1', colPct:100, zoneClass:'', fields:[]}
        ]},
        {id:'r2', rowClass:'', zones:[
            {id:'z2', name:'image',   colPct:33,  zoneClass:'', fields:[]},
            {id:'z3', name:'top',     colPct:67,  zoneClass:'', fields:[]}
        ]},
        {id:'r3', rowClass:'', zones:[
            {id:'z4', name:'description', colPct:100, zoneClass:'', fields:[]}
        ]},
        {id:'r4', rowClass:'', zones:[
            {id:'z5', name:'bottom', colPct:100, zoneClass:'', fields:[]}
        ]}
    ]
};

try {
    global.FCPB_STATE = savedJson ? JSON.parse(savedJson) : defaultLayout;
} catch(e) {
    global.FCPB_STATE = defaultLayout;
}

/* ---- BS5 col map ---- */
global.FCPB_PCT_TO_COL = function(pct) {
    var steps = [8,16,25,33,42,50,58,67,75,83,92,100];
    var cols  = [1,2,3,4,5,6,7,8,9,10,11,12];
    var best = 12;
    var bestDiff = 100;
    for (var i=0;i<steps.length;i++) {
        var d = Math.abs(pct - steps[i]);
        if (d < bestDiff){ bestDiff=d; best=cols[i]; }
    }
    return best;
};

})(window);
</script>

<div id="flexicontent" class="flexicontent fcconfig-form">
<form action="index.php" method="post" name="adminForm" id="adminForm" class="form-validate form-horizontal">

    <div class="fctabber tabset_layout fcparams_tabset" id="tabset_layout" style="margin:16px 0 !important;">

        <?php /* ========================================================
           TAB 1 — INFORMATION
           ======================================================== */ ?>
        <div class="tabbertab" id="tabset_layout_information_tab" data-icon-class="icon-info">
            <h3 class="tabberheading"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_INFORMATION'); ?></h3>
            <table>
                <tr>
                    <td style="vertical-align:top;">
                        <img src="../<?php echo $this->layout->thumb; ?>" alt="<?php echo \Joomla\CMS\Language\Text::_('FLEXI_TEMPLATE_THUMBNAIL'); ?>" style="max-width:none;" />
                    </td>
                    <td style="vertical-align:top;">
                        <table class="admintable" id="lay-desc-table">
                            <tr><td style="text-align:right;"><label class="label"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_FOLDER'); ?></label></td>
                                <td><span class="badge bg-warning badge-warning"><?php echo $this->layout->name; ?></span></td></tr>
                            <tr><td style="text-align:right;"><label class="label"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_VIEW'); ?></label></td>
                                <td><span class="badge bg-success badge-success"><?php echo $this->layout->view; ?></span></td></tr>
                            <tr><td style="padding-top:12px;" colspan="2"></td></tr>
                            <tr><td style="text-align:right;"><label class="label"><?php echo \Joomla\CMS\Language\Text::_('Default title'); ?></label></td>
                                <td><?php echo \Joomla\CMS\Language\Text::_($this->layout->defaulttitle); ?></td></tr>
                            <tr><td style="text-align:right;"><label class="label"><?php echo \Joomla\CMS\Language\Text::_('Description'); ?></label></td>
                                <td><?php echo \Joomla\CMS\Language\Text::_($this->layout->description); ?></td></tr>
                            <tr><td style="text-align:right;"><label class="label"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_AUTHOR'); ?></label></td>
                                <td><?php echo $this->layout->author; ?></td></tr>
                            <tr><td style="text-align:right;"><label class="label"><?php echo \Joomla\CMS\Language\Text::_('Version'); ?></label></td>
                                <td><?php echo $this->layout->version; ?></td></tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <?php /* ========================================================
           TAB 2 — FIELDS PLACEMENT (original drag & drop FLEXIcontent)
           ======================================================== */ ?>
        <div class="tabbertab" id="tabset_layout_fields_placement_tab" data-icon-class="icon-signup">
            <h3 class="tabberheading"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_FIELDS_PLACEMENT'); ?></h3>

            <div class="fcclear"></div>
            <div class="fc-mssg fc-success fc-nobgimage" style="font-size:100%;margin:4px 0;">
                <span style="font-weight:bold;"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_NOTES'); ?>:</span>
                <?php echo \Joomla\CMS\Language\Text::_('FLEXI_INSTRUCTIONS_ADD_FIELD_TO_LAYOUT_POSITION'); ?>
            </div>

            <div class="container-fluid row" style="padding:0!important;margin:0!important;">
                <div class="span6 col-6 full_width_980">
                    <fieldset id="available_fields_container">
                        <legend style="margin:0 0 12px 0;font-size:14px;padding:6px;background:gray;" class="fcsep_level1"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_AVAILABLE_FIELDS'); ?></legend>
                        <div style="float:left;clear:both;width:100%;margin:0 0 12px;">
                            <div style="float:left;margin-right:32px;">
                                <div style="float:left;" class="positions_title label"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_FILTER').' '.\Joomla\CMS\Language\Text::_('FLEXI_TYPE'); ?></div>
                                <div style="float:left;clear:both;"><?php echo sprintf(str_replace('__au__', '_available', $this->content_type_select), 'available_fields_container', 'hide', 'available'); ?></div>
                            </div>
                            <div style="float:left;">
                                <div style="float:left;" class="positions_title label"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_FILTER').' '.\Joomla\CMS\Language\Text::_('FLEXI_FIELD_TYPE'); ?></div>
                                <div style="float:left;clear:both;"><?php echo sprintf(str_replace('__au__', '_available', $this->field_type_select), 'available_fields_container', 'hide', 'available'); ?></div>
                            </div>
                        </div>
                        <div class="positions_title label text-white bg-info label-info" style="margin-top:10px;"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_CORE_FIELDS'); ?></div>
                        <div class="positions_container">
                            <ul id="sortablecorefields" class="positions">
                            <?php foreach ($this->fields as $field) : if ($field->iscore && (!in_array($field->name, $this->used))) : $cl="fields core".(!empty($field->type_ids)?" content_type_".implode(" content_type_",$field->type_ids):"")." field_type_".$field->field_type; ?>
                            <li class="<?php echo $cl; ?>" id="field_<?php echo $field->name; ?>"><?php echo $field->label; ?></li>
                            <?php endif; endforeach; ?>
                            </ul>
                        </div>
                        <div class="positions_title label text-white bg-info label-info" style="margin-top:10px;"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_CUSTOM_NON_CORE_FIELDS'); ?></div>
                        <div class="positions_container">
                            <ul id="sortableuserfields" class="positions">
                            <?php foreach ($this->fields as $field) : if (!$field->iscore && (!in_array($field->name, $this->used))) : $cl="fields user".(!empty($field->type_ids)?" content_type_".implode(" content_type_",$field->type_ids):"")." field_type_".$field->field_type; ?>
                            <li class="<?php echo $cl; ?>" id="field_<?php echo $field->name; ?>"><?php echo $field->label.' #'.$field->id; ?></li>
                            <?php endif; endforeach; ?>
                            </ul>
                        </div>
                    </fieldset>
                </div>

                <div class="span6 col-6 full_width_980 padded_wrap_box">
                    <fieldset id="layout_positions_container">
                        <legend style="margin:0 0 12px 0;font-size:14px;padding:6px;background:gray;" class="fcsep_level1"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_AVAILABLE_POS'); ?></legend>
                        <div style="float:left;clear:both;width:100%;margin:0 0 12px;">
                            <div style="float:left;margin-right:32px;">
                                <div style="float:left;" class="positions_title label"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_FILTER').' '.\Joomla\CMS\Language\Text::_('FLEXI_TYPE'); ?></div>
                                <div style="float:left;clear:both;"><?php echo sprintf(str_replace('__au__','_used',$this->content_type_select),'layout_positions_container','highlight','used'); ?></div>
                            </div>
                            <div style="float:left;">
                                <div style="float:left;" class="positions_title label"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_FILTER').' '.\Joomla\CMS\Language\Text::_('FLEXI_FIELD_TYPE'); ?></div>
                                <div style="float:left;clear:both;"><?php echo sprintf(str_replace('__au__','_used',$this->field_type_select),'layout_positions_container','highlight','used'); ?></div>
                            </div>
                        </div>
                        <?php
                        if (isset($this->layout->positions)) :
                            $count=-1; $posrow=null;
                            foreach ($this->layout->positions as $pos) :
                                $count++;
                                $posrow_prev=$posrow;
                                $posrow    = isset($this->layout->attributes[$count]['posrow']) ? $this->layout->attributes[$count]['posrow'] : '';
                                $postitle  = isset($this->layout->attributes[$count]['title'])  ? $this->layout->attributes[$count]['title']  : $pos;
                                $title_color = isset($this->layout->attributes[$count]['tcolor']) ? 'background-color:'.$this->layout->attributes[$count]['tcolor'].';' : '';
                                echo ($posrow_prev && $posrow_prev!=$posrow) ? "</td></tr></table>\n" : "";
                                if ($posrow) {
                                    echo ($posrow_prev!=$posrow) ? "<table style='width:100%;'><tr class='fieldgrprow'><td class='fieldgrprow_cell'>\n" : "</td><td class='fieldgrprow_cell'>\n";
                                }
                        ?>
                        <div class="positions_title label text-white bg-success label-success" style="color:white;margin:10px 0 -6px;display:inline-block;padding:5px;<?php echo $title_color; ?>"><?php echo $postitle; ?></div>
                        <?php
                                if (isset($this->layout->attributes[$count]['readonly'])) {
                                    echo "<div class='positions_readonly_info fc-mssg fc-info fc-nobgimage'>Non-editable position.</div>";
                                    continue;
                                }
                        ?>
                        <div class="positions_container">
                            <ul id="sortable-<?php echo $pos; ?>" class="positions">
                            <?php
                            if (isset($this->fbypos[$pos])) :
                                foreach ($this->fbypos[$pos]->fields as $f) :
                                    if (isset($this->fields[$f])) :
                                        $field=$this->fields[$f];
                                        $cl="fields ".($field->iscore?'core':'user');
                                        $cl.=!empty($field->type_ids)?" content_type_".implode(" content_type_",$field->type_ids):"";
                                        $cl.=" field_type_".$field->field_type;
                            ?>
                            <li class="<?php echo $cl; ?>" id="field_<?php echo $field->name; ?>"><?php echo $field->label.($field->iscore?'':' #'.$field->id); ?></li>
                            <?php endif; endforeach; endif; ?>
                            </ul>
                        </div>
                        <input type="hidden" name="<?php echo $pos; ?>" id="<?php echo $pos; ?>" value="" />
                        <?php
                            endforeach;
                            echo $posrow ? "</td></tr></table>\n" : "";
                        else :
                            echo \Joomla\CMS\Language\Text::_('FLEXI_NO_GROUPS_AVAILABLE');
                        endif;
                        ?>
                    </fieldset>
                </div>
            </div>
        </div>

        <?php /* ========================================================
           TAB 3 — PAGE BUILDER VISUEL (NOUVEAU)
           ======================================================== */ ?>
        <div class="tabbertab" id="tabset_layout_builder_tab" data-icon-class="icon-grid">
            <h3 class="tabberheading">Page Builder visuel</h3>

            <div class="fc-mssg fc-info fc-nobgimage" style="font-size:100%;margin:4px 0 12px;">
                <strong>Page builder Bootstrap 5</strong> — Construis visuellement la mise en page.
                Glisse les champs dans les zones, redimensionne les colonnes par la poignée centrale,
                puis clique <em>Sauvegarder</em> pour enregistrer le JSON et générer le PHP.
            </div>

            <div id="fc-page-builder">

                <!-- Toolbar -->
                <div class="fcpb-toolbar">
                    <span class="fcpb-toolbar-title">Layout builder</span>
                    <div class="fcpb-toolbar-sep"></div>
                    <button type="button" class="fcpb-btn" id="fcpb-add-row">+ Rangée</button>
                    <button type="button" class="fcpb-btn" id="fcpb-reset">↺ Réinitialiser</button>
                    <div class="fcpb-toolbar-sep"></div>
                    <button type="button" class="fcpb-btn primary" id="fcpb-gen-php">&#9881; Voir PHP</button>
                    <button type="button" class="fcpb-btn success" id="fcpb-save">&#10003; Sauvegarder JSON</button>
                </div>

                <!-- Layout deux colonnes -->
                <div id="fcpb-body">

                    <!-- Colonne gauche : champs disponibles -->
                    <div id="fcpb-sidebar">
                        <div id="fcpb-pool">
                            <div class="fcpb-pool-title">Champs</div>

                            <?php /* Filtre type de contenu — même mécanisme JS natif FC (action hide) */ ?>
                            <?php if ($this->content_type_select) : ?>
                            <div style="margin-bottom:4px;">
                                <div style="font-size:10px;color:#aaa;margin-bottom:2px;"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_FILTER').' '.\Joomla\CMS\Language\Text::_('FLEXI_TYPE'); ?></div>
                                <?php echo sprintf(str_replace('__au__', '_builder', $this->content_type_select), 'fcpb-pool', 'hide', 'builder'); ?>
                            </div>
                            <?php endif; ?>

                            <?php /* Filtre type de champ */ ?>
                            <?php if ($this->field_type_select) : ?>
                            <div style="margin-bottom:8px;">
                                <div style="font-size:10px;color:#aaa;margin-bottom:2px;"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_FILTER').' '.\Joomla\CMS\Language\Text::_('FLEXI_FIELD_TYPE'); ?></div>
                                <?php echo sprintf(str_replace('__au__', '_builder', $this->field_type_select), 'fcpb-pool', 'hide', 'builder'); ?>
                            </div>
                            <?php endif; ?>

                            <div id="fcpb-pool-fields"></div>
                        </div>
                    </div>

                    <!-- Colonne droite : canvas + php output -->
                    <div id="fcpb-main">
                        <div id="fcpb-canvas"></div>
                        <pre id="fcpb-php-output"></pre>
                    </div>

                </div><!-- /#fcpb-body -->

            </div><!-- /#fc-page-builder -->

            <?php /* Champ RAW hors jform — lu par le controleur via getString('layout_json_raw').
                      Le JS y ecrit le JSON encode en base64 pour survivre au filtrage Joomla. */ ?>
            <input type="hidden"
                   name="layout_json_raw"
                   id="fcpb-json-field"
                   value="<?php echo base64_encode($saved_layout_json); ?>" />

        </div>

        <?php /* ========================================================
           TAB 4 — DISPLAY PARAMETERS
           ======================================================== */ ?>
        <div class="tabbertab" id="tabset_layout_disp_params_tab" data-icon-class="icon-options">
            <h3 class="tabberheading"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_DISPLAY_PARAMETERS'); ?></h3>
            <div style="max-width:1024px;margin-top:16px;">
                <?php
                $groupname = 'attribs';
                $fieldSets = $this->layout->params->getFieldsets($groupname);
                foreach ($fieldSets as $fsname => $fieldSet) :
                    if (isset($fieldSet->description) && trim($fieldSet->description)) {
                        echo '<div class="fc-mssg fc-info">'.(\Joomla\CMS\Language\Text::_($fieldSet->description)).'</div>';
                    }
                ?>
                <fieldset class="panelform">
                    <?php foreach ($this->layout->params->getFieldset($fsname) as $field) :
                        $fieldname = $field->__get('fieldname');
                        $cssprep   = $field->getAttribute('cssprep');
                        $_labelclass = $cssprep == 'less' ? 'fc_less_parameter' : '';
                        $value = $this->layout->params->getValue($fieldname, $groupname, @$this->conf->attribs[$fieldname]);
                        $value = $value !== '' ? $value : null;
                        if ($field->getAttribute('type')=='separator' || $field->hidden) { echo $field->input; continue; }
                        echo '<div class="control-group">';
                        echo '<div class="control-label">'.str_replace('class="','class="'.$_labelclass.' ',str_replace('jform_attribs_','jform_layouts_'.$this->layout->name.'_',$this->layout->params->getLabel($fieldname,$groupname))).'</div>';
                        echo '<div class="controls">'.str_replace('jform_attribs_','jform_layouts_'.$this->layout->name.'_',str_replace('[attribs]','[layouts]['.$this->layout->name.']',$this->layout->params->getInput($fieldname,$groupname,$value))).'</div>';
                        echo '</div>';
                    endforeach; ?>
                </fieldset>
                <?php endforeach; ?>
            </div>
        </div>

        <?php /* ========================================================
           TAB 5 — EDIT FILES
           ======================================================== */ ?>
        <div class="tabbertab" id="tabset_layout_edit_files_tab" data-icon-class="icon-signup">
            <h3 class="tabberheading"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_EDIT_LAYOUT_FILES'); ?></h3>
            <div class="container-fluid row">
                <div id="layout-filelist-container" class="span3 col-3">
                    <span class="fcsep_level0" style="margin:0 0 12px 0;background:#333;">
                        <span class="badge"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_LAYOUT_FILES'); ?></span>
                    </span>
                    <?php
                    $tmpldir = JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.$this->layout->name;
                    $it = new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpldir)), '#('.$this->layout->view.'(_.*\\.|\\.)(php|xml|less|css|js)|include.*less|seo.{1}'.$this->layout->view.'.*php)#i');
                    $it->rewind();
                    $ext_badge = array('php'=>'success','xml'=>'info','css'=>'warning','js'=>'important','ini'=>'info','less'=>'inverse');
                    while($it->valid()){
                        if(!$it->isDot()){
                            $subpath=$it->getSubPath();
                            $subpath_highlighted=$subpath?'<span class="label">'.str_replace('\\','/',$subpath).'/</span>':'';
                            $filename=basename($it->getSubPathName());
                            $pi=pathinfo($it->key());
                            $ext=$pi['extension'];
                            $file_type=isset($ext_badge[$ext])?'<span class="badge badge-'.$ext_badge[$ext].'">'.$ext.'</span> ':'<span class="badge">---</span> ';
                            echo $file_type.'<a href="javascript:;" class="'.$tip_class.'" onclick="load_layout_file(\''.addslashes($this->layout->name).'\', \''.addslashes($it->getSubPathName()).'\', \'0\', \'\'); return false;">'.$subpath_highlighted.'&nbsp;'.$filename.'</a><br/>';
                        }
                        $it->next();
                    }
                    ?>
                </div>
                <div id="layout-fileeditor-container" class="span9 col-9">
                    <span class="fcsep_level0" style="margin:0 0 12px 0;background:#333;">
                        <span id="layout_edit_name_container" class="label text-white bg-info label-info"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_NO_FILE_LOADED'); ?></span>
                    </span>
                    <div class="fcclear"></div>
                    <div id="ajax-system-message-container"></div>
                    <div class="fcclear"></div>
                    <?php
                    if ($use_editor) {
                        $editor = \Joomla\CMS\Editor\Editor::getInstance('codemirror');
                        $editor_plg_params = array('mode'=>'php');
                    }
                    $elementid_n='editor__file_contents'; $fieldname_n='file_contents';
                    $cols='80'; $rows_n='16'; $width='100%'; $height='400px';
                    $txtarea = !$use_editor
                        ? '<textarea id="'.$elementid_n.'" name="'.$fieldname_n.'" style="width:100%;" cols="'.$cols.'" rows="'.$rows_n.'" form="layout_file_editor_form"></textarea>'
                        : $editor->display($fieldname_n,'',$width,$height,$cols,$rows_n,false,$elementid_n,null,null,$editor_plg_params);
                    echo $txtarea;
                    ?>
                    <br/>
                    <?php echo str_replace('<input','<input form="layout_file_editor_form"',\Joomla\CMS\HTML\HTMLHelper::_('form.token')); ?>
                    <input type="hidden" name="load_mode"    id="editor__load_mode"    form="layout_file_editor_form"/>
                    <input type="hidden" name="layout_name"  id="editor__layout_name"  form="layout_file_editor_form"/>
                    <input type="hidden" name="file_subpath" id="editor__file_subpath"  form="layout_file_editor_form"/>
                    <input type="hidden" name="btn_classes"  id="editor__btn_classes"  form="layout_file_editor_form"/>
                    <input type="button" id="editor__save_file_btn"   class="<?php echo $btn_class; ?> btn-success" onclick="save_layout_file('layout_file_editor_form');return false;" style="display:none;" value="Save" form="layout_file_editor_form"/>
                    <input type="button" id="editor__download_file_btn" class="<?php echo $btn_class; ?> btn-info" onclick="load_layout_file('','',2,-1);return false;" style="display:none;" value="Download" form="layout_file_editor_form"/>

                    <?php foreach ($code_btn_lbls as $_posname => $btn_lbl) : ?>
                    <div class="code_box <?php echo $_posname; ?> nowrap_box" style="display:none;">
                        <div class="btn <?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('Insert code',$code_btn_tips[$_posname],0,1); ?>" onclick="toggle_code_inputbox(this);">
                            <span class="icon-eye"></span><?php echo \Joomla\CMS\Language\Text::_($code_btn_lbls[$_posname]); ?>
                        </div>
                        <div class="nowrap_box" style="display:none;float:left;clear:both;margin:2px 0 0;">
                            <div class="alert alert-warning" style="clear:both;margin:2px 0;"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_COPY_CODE'); ?></div>
                            <div class="alert alert-info" style="clear:both;margin:2px 0;"><?php echo $code_btn_tips[$_posname]; ?></div>
                        </div>
                        <textarea style="float:left;clear:both;display:none;width:100%;" rows="20" form="code_insertion_form"><?php echo htmlspecialchars($code_btn_rawcode[$_posname]); ?></textarea>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="fcclear"></div>
            </div>
        </div>

    </div><!-- /.fctabber -->

    <input type="hidden" name="option"     value="com_flexicontent" />
    <input type="hidden" name="controller" value="templates" />
    <input type="hidden" name="rows"       id="rows"       value="" />
    <input type="hidden" name="positions"  id="positions"  value="<?php echo $this->positions; ?>" />
    <input type="hidden" name="view"       value="template" />
    <input type="hidden" name="type"       value="<?php echo $this->type; ?>" />
    <input type="hidden" name="folder"     value="<?php echo $this->folder; ?>" />
    <input type="hidden" name="task"       value="" />
    <?php echo \Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
</form>

<form id="layout_file_editor_form" name="layout_file_editor_form" action="index.php?option=com_flexicontent&task=templates.loadlayoutfile&format=raw" method="POST"></form>
<form id="code_insertion_form" name="code_insertion_form" action="#" method="POST"></form>
</div><!-- /#flexicontent -->

<?php /* ============================================================
   SCRIPT PRINCIPAL DU PAGE BUILDER
   ============================================================ */ ?>
<script>
(function(){
'use strict';

/* ----------------------------------------------------------------
   STATE
   ---------------------------------------------------------------- */
var state  = FCPB_STATE;      // {rows:[{id,zones:[{id,name,colPct,fields:[]}]}]}
var fields = FCPB_FIELDS;     // [{name,label,isCore}]
var uid    = Date.now();

function nextId(prefix){ uid++; return prefix+uid; }

/* ----------------------------------------------------------------
   HELPERS
   ---------------------------------------------------------------- */
function findZone(zoneId){
    for(var ri=0;ri<state.rows.length;ri++){
        for(var zi=0;zi<state.rows[ri].zones.length;zi++){
            if(state.rows[ri].zones[zi].id===zoneId)
                return {row:state.rows[ri], zone:state.rows[ri].zones[zi], ri:ri, zi:zi};
        }
    }
    return null;
}
function allUsedFields(){
    var used={};
    state.rows.forEach(function(r){
        r.zones.forEach(function(z){ z.fields.forEach(function(f){ used[f]=1; }); });
    });
    return used;
}
function fieldByName(name){
    for(var i=0;i<fields.length;i++) if(fields[i].name===name) return fields[i];
    return {name:name,label:name,isCore:false};
}
function pctToLabel(pct){
    var map=[[8,'1/12'],[16,'2/12'],[25,'3/12 (1/4)'],[33,'4/12 (1/3)'],[42,'5/12'],[50,'6/12 (1/2)'],
             [58,'7/12'],[67,'8/12 (2/3)'],[75,'9/12 (3/4)'],[83,'10/12'],[92,'11/12'],[100,'12/12']];
    var best=map[11]; var bd=100;
    map.forEach(function(m){ var d=Math.abs(pct-m[0]); if(d<bd){bd=d;best=m;} });
    return 'col-'+FCPB_PCT_TO_COL(pct)+' ('+best[1]+')';
}

/* ----------------------------------------------------------------
   DRAG STATE
   ---------------------------------------------------------------- */
var drag = {
    type: null,      // 'field' | 'zone' | 'row'
    fieldName: null,
    fromZoneId: null,  // null = from pool
    zoneId: null,
    fromRowId: null,
    rowId: null,
};

/* ----------------------------------------------------------------
   RENDER
   ---------------------------------------------------------------- */
function render(){
    var canvas = document.getElementById('fcpb-canvas');
    canvas.innerHTML='';
    state.rows.forEach(function(row){
        canvas.appendChild(buildRow(row));
    });
    renderPool();
}

function buildRow(row){
    var div = document.createElement('div');
    div.className='fcpb-row';
    div.dataset.rowId=row.id;

    /* drop target pour réordonner les rangées */
    div.addEventListener('dragover',function(e){
        if(drag.type!=='row' || drag.rowId===row.id) return;
        e.preventDefault();
        e.stopPropagation();
        document.querySelectorAll('.fcpb-row').forEach(function(r){
            r.classList.remove('drop-row-before','drop-row-after');
        });
        var rect=div.getBoundingClientRect();
        div.classList.add(e.clientY < rect.top + rect.height/2 ? 'drop-row-before' : 'drop-row-after');
    });
    div.addEventListener('dragleave',function(e){
        if(!div.contains(e.relatedTarget)){
            div.classList.remove('drop-row-before','drop-row-after');
        }
    });
    div.addEventListener('drop',function(e){
        if(drag.type!=='row' || drag.rowId===row.id) return;
        e.preventDefault();
        e.stopPropagation();
        var rect=div.getBoundingClientRect();
        var before = e.clientY < rect.top + rect.height/2;
        div.classList.remove('drop-row-before','drop-row-after');
        moveRow(drag.rowId, row.id, before);
        drag.type=null; drag.rowId=null;
    });

    /* row bar */
    var bar=document.createElement('div');
    bar.className='fcpb-row-bar';

    /* poignée drag — seul élément draggable pour la rangée */
    var handle=document.createElement('span');
    handle.className='fcpb-row-handle';
    handle.textContent='⠿';
    handle.title='Glisser pour déplacer la rangée';
    handle.draggable=true;
    handle.addEventListener('dragstart',function(e){
        e.stopPropagation();
        drag.type='row';
        drag.rowId=row.id;
        drag.fieldName=null;
        drag.zoneId=null;
        div.classList.add('dragging-row');
        e.dataTransfer.effectAllowed='move';
        e.dataTransfer.setData('text/plain', row.id);
    });
    handle.addEventListener('dragend',function(){
        div.classList.remove('dragging-row');
        document.querySelectorAll('.fcpb-row').forEach(function(r){
            r.classList.remove('drop-row-before','drop-row-after');
        });
        drag.type=null; drag.rowId=null;
    });

    var lbl=document.createElement('span');
    lbl.className='fcpb-row-label';
    lbl.textContent='Rangée — '+row.zones.length+' zone(s)';

    var rowClassInp=document.createElement('input');
    rowClassInp.type='text';
    rowClassInp.className='fcpb-row-class-input';
    rowClassInp.placeholder='CSS row (ex: py-4 bg-light)';
    rowClassInp.value=row.rowClass||'';
    rowClassInp.title='Classes CSS ajoutées sur le <div class="row ...">  de cette rangée';
    (function(r, el){
        el.addEventListener('input',  function(){ r.rowClass=this.value.trim(); serializeToHidden(); });
        el.addEventListener('change', function(){ r.rowClass=this.value.trim(); serializeToHidden(); });
    })(row, rowClassInp);

    var acts=document.createElement('div');
    acts.className='fcpb-row-actions';

    var btnAddZone=document.createElement('button');
    btnAddZone.type='button';
    btnAddZone.className='fcpb-btn';
    btnAddZone.textContent='+ Zone';
    btnAddZone.onclick=(function(rid){ return function(){ addZone(rid); }; })(row.id);

    var btnDel=document.createElement('button');
    btnDel.type='button';
    btnDel.className='fcpb-btn danger';
    btnDel.textContent='✕ Rangée';
    btnDel.onclick=(function(rid){ return function(){ deleteRow(rid); }; })(row.id);

    acts.appendChild(btnAddZone);
    acts.appendChild(btnDel);
    bar.appendChild(handle);
    bar.appendChild(lbl);
    bar.appendChild(rowClassInp);
    bar.appendChild(acts);
    div.appendChild(bar);

    /* zone grid */
    var grid=document.createElement('div');
    grid.className='fcpb-row-grid';

    row.zones.forEach(function(zone, zi){
        /* zone cell */
        var zDiv=buildZone(zone, row.id);
        grid.appendChild(zDiv);

        /* resize handle after every zone except the last */
        if(zi < row.zones.length-1){
            var handle=document.createElement('div');
            handle.className='fcpb-resize-handle';
            handle.title='Glisser pour redimensionner';
            attachResizeHandle(handle, row, zi);
            grid.appendChild(handle);
        }
    });

    /* add-zone shortcut below grid */
    var addBtn=document.createElement('button');
    addBtn.type='button';
    addBtn.className='fcpb-add-zone';
    addBtn.textContent='+ Ajouter une zone dans cette rangée';
    addBtn.onclick=(function(rid){ return function(){ addZone(rid); }; })(row.id);

    div.appendChild(grid);
    div.appendChild(addBtn);

    /* row drop (for field overflow) */
    div.addEventListener('dragover',function(e){e.preventDefault();});

    return div;
}

function buildZone(zone, rowId){
    var div=document.createElement('div');
    div.className='fcpb-zone';
    div.dataset.zoneId=zone.id;
    div.style.width=zone.colPct+'%';
    div.draggable=true;

    /* zone header */
    var hdr=document.createElement('div');
    hdr.className='fcpb-zone-header';

    var inp=document.createElement('input');
    inp.className='fcpb-zone-name';
    inp.value=zone.name;
    inp.title='Nom de la position FLEXIcontent';
    (function(z, el){
        el.addEventListener('input',  function(){ z.name=this.value; serializeToHidden(); });
        el.addEventListener('change', function(){ z.name=this.value; serializeToHidden(); });
    })(zone, inp);

    var meta=document.createElement('span');
    meta.className='fcpb-zone-meta';
    meta.textContent=pctToLabel(zone.colPct);

    var zoneClassInp=document.createElement('input');
    zoneClassInp.type='text';
    zoneClassInp.className='fcpb-zone-class-input';
    zoneClassInp.placeholder='CSS zone';
    zoneClassInp.value=zone.zoneClass||'';
    zoneClassInp.title='Classes CSS ajoutées sur le <div class="col-md-N ..."> de cette zone';
    (function(z, el){
        el.addEventListener('input',  function(){ z.zoneClass=this.value.trim(); serializeToHidden(); });
        el.addEventListener('change', function(){ z.zoneClass=this.value.trim(); serializeToHidden(); });
    })(zone, zoneClassInp);
    zoneClassInp.addEventListener('click',function(e){ e.stopPropagation(); });

    var del=document.createElement('button');
    del.type='button';
    del.className='fcpb-zone-del';
    del.textContent='✕';
    del.title='Supprimer cette zone';
    del.onclick=(function(zid,rid){ return function(e){ e.stopPropagation(); deleteZone(rid,zid); }; })(zone.id,rowId);

    hdr.appendChild(inp);
    hdr.appendChild(zoneClassInp);
    hdr.appendChild(meta);
    hdr.appendChild(del);
    div.appendChild(hdr);

    /* fields */
    var fc=document.createElement('div');
    fc.className='fcpb-fields';
    fc.dataset.zoneId=zone.id;

    if(zone.fields.length===0){
        var hint=document.createElement('span');
        hint.className='fcpb-zone-empty-hint';
        hint.textContent='Déposer des champs ici';
        fc.appendChild(hint);
    } else {
        zone.fields.forEach(function(fname){
            fc.appendChild(buildChip(fname, zone.id));
        });
    }
    div.appendChild(fc);

    /* zone drag (reorder zones) */
    div.addEventListener('dragstart',function(e){
        e.stopPropagation();
        drag.type='zone';
        drag.zoneId=zone.id;
        drag.fromRowId=rowId;
        drag.fieldName=null;
        div.classList.add('dragging-zone');
        e.dataTransfer.effectAllowed='move';
    });
    div.addEventListener('dragend',function(){
        div.classList.remove('dragging-zone');
    });
    div.addEventListener('dragover',function(e){
        e.preventDefault();
        if(drag.type==='field') div.classList.add('drop-target');
    });
    div.addEventListener('dragleave',function(){
        div.classList.remove('drop-target');
    });
    div.addEventListener('drop',function(e){
        e.preventDefault(); e.stopPropagation();
        div.classList.remove('drop-target');
        if(drag.type==='field'){
            addFieldToZone(zone.id, drag.fieldName, drag.fromZoneId);
            drag.fieldName=null; drag.fromZoneId=null;
        } else if(drag.type==='zone' && drag.zoneId && drag.zoneId!==zone.id){
            swapZones(drag.zoneId, zone.id);
        }
        drag.type=null;
    });

    return div;
}

function buildChip(fieldName, zoneId){
    var fd=fieldByName(fieldName);
    var span=document.createElement('span');
    span.className='fcpb-field-chip'+(fd.isCore?' is-core':'');
    span.draggable=true;
    span.dataset.field=fieldName;
    span.dataset.zone=zoneId;
    span.textContent=fd.label;

    var del=document.createElement('button');
    del.type='button';
    del.className='chip-del';
    del.textContent='×';
    del.title='Retirer ce champ';
    del.onclick=(function(zid,fn){ return function(e){ e.stopPropagation(); removeField(zid,fn); }; })(zoneId,fieldName);
    span.appendChild(del);

    span.addEventListener('dragstart',function(e){
        e.stopPropagation();
        drag.type='field';
        drag.fieldName=fieldName;
        drag.fromZoneId=zoneId;
        e.dataTransfer.effectAllowed='move';
    });
    return span;
}

function makePoolChip(f){
    var span=document.createElement('span');
    span.className=f.classes || ('fcpb-field-chip'+(f.isCore?' is-core':''));
    span.draggable=true;
    span.dataset.field=f.name;
    span.dataset.zone='pool';
    span.title=f.name;
    span.textContent=f.label;
    span.addEventListener('dragstart',function(e){
        drag.type='field';
        drag.fieldName=f.name;
        drag.fromZoneId=null;
        e.dataTransfer.effectAllowed='copy';
    });
    return span;
}

function renderPool(){
    var pool=document.getElementById('fcpb-pool-fields');
    pool.innerHTML='';
    var used=allUsedFields();
    var core=fields.filter(function(f){ return !used[f.name] && f.isCore; });
    var custom=fields.filter(function(f){ return !used[f.name] && !f.isCore; });

    if(!core.length && !custom.length){
        var empty=document.createElement('span');
        empty.className='fcpb-pool-empty';
        empty.textContent='Tous les champs sont placés.';
        pool.appendChild(empty);
        return;
    }

    /* Section Core */
    if(core.length){
        var sec=document.createElement('div');
        sec.className='fcpb-pool-section';
        var lbl=document.createElement('div');
        lbl.className='fcpb-pool-section-label';
        lbl.textContent='Champs core';
        sec.appendChild(lbl);
        core.forEach(function(f){ sec.appendChild(makePoolChip(f)); });
        pool.appendChild(sec);
    }

    /* Section Custom */
    if(custom.length){
        var sec2=document.createElement('div');
        sec2.className='fcpb-pool-section';
        var lbl2=document.createElement('div');
        lbl2.className='fcpb-pool-section-label';
        lbl2.textContent='Champs custom';
        sec2.appendChild(lbl2);
        custom.forEach(function(f){ sec2.appendChild(makePoolChip(f)); });
        pool.appendChild(sec2);
    }

    /* Réappliquer les filtres actifs sur les chips fraîchement créés */
    setTimeout(function(){ if(typeof window.fcpb_applyFilters === 'function') window.fcpb_applyFilters(); }, 0);
}

/* ----------------------------------------------------------------
   RESIZE HANDLES — drag between zones of the same row
   ---------------------------------------------------------------- */
function attachResizeHandle(handle, row, leftIndex){
    var startX=0, startPcts=[], totalPct=0;

    handle.addEventListener('mousedown',function(e){
        e.preventDefault();
        handle.classList.add('resizing');

        startX=e.clientX;

        /* capture current widths */
        startPcts=row.zones.map(function(z){ return z.colPct; });
        totalPct = startPcts[leftIndex] + startPcts[leftIndex+1];

        /* get the pixel width of the row grid to convert px→% */
        var grid=handle.parentNode;
        var gridW=grid.offsetWidth || 800;

        function onMove(ev){
            var dx=ev.clientX - startX;
            var dpct = (dx / gridW) * 100;

            var newLeft  = startPcts[leftIndex]   + dpct;
            var newRight = startPcts[leftIndex+1] - dpct;

            /* clamp to minimum 8% per column */
            if(newLeft  < 8){ newLeft=8;  newRight=totalPct-8; }
            if(newRight < 8){ newRight=8; newLeft=totalPct-8; }

            /* snap to BS5 grid (multiples of 100/12 ≈ 8.33) */
            newLeft  = Math.round(newLeft  / (100/12)) * (100/12);
            newRight = totalPct - newLeft;
            if(newRight < 8){ newRight=100/12; newLeft=totalPct-newRight; }

            row.zones[leftIndex].colPct   = newLeft;
            row.zones[leftIndex+1].colPct = newRight;

            /* live update widths without full re-render */
            var grid2=handle.parentNode;
            var zoneDivs=Array.prototype.filter.call(grid2.children,function(c){ return c.classList.contains('fcpb-zone'); });
            if(zoneDivs[leftIndex])   zoneDivs[leftIndex].style.width   = newLeft+'%';
            if(zoneDivs[leftIndex+1]) zoneDivs[leftIndex+1].style.width = newRight+'%';

            /* update meta labels */
            var metas=zoneDivs[leftIndex] && zoneDivs[leftIndex].querySelectorAll('.fcpb-zone-meta');
            if(metas && metas[0]) metas[0].textContent=pctToLabel(newLeft);
            var metas2=zoneDivs[leftIndex+1] && zoneDivs[leftIndex+1].querySelectorAll('.fcpb-zone-meta');
            if(metas2 && metas2[0]) metas2[0].textContent=pctToLabel(newRight);
        }

        function onUp(){
            handle.classList.remove('resizing');
            document.removeEventListener('mousemove',onMove);
            document.removeEventListener('mouseup',onUp);
            serializeToHidden();
        }

        document.addEventListener('mousemove',onMove);
        document.addEventListener('mouseup',onUp);
    });
}

/* ----------------------------------------------------------------
   MUTATIONS
   ---------------------------------------------------------------- */
function moveRow(fromId, toId, before){
    var fromIdx = state.rows.findIndex(function(r){ return r.id===fromId; });
    var toIdx   = state.rows.findIndex(function(r){ return r.id===toId; });
    if(fromIdx===-1 || toIdx===-1 || fromIdx===toIdx) return;
    var row = state.rows.splice(fromIdx, 1)[0];
    // recalculer toIdx après splice
    toIdx = state.rows.findIndex(function(r){ return r.id===toId; });
    var insertAt = before ? toIdx : toIdx+1;
    state.rows.splice(insertAt, 0, row);
    render();
    serializeToHidden();
}

function addZone(rowId){
    var row=state.rows.filter(function(r){ return r.id===rowId; })[0];
    if(!row) return;
    var zid=nextId('z');
    var remaining=100;
    row.zones.forEach(function(z){ remaining-=z.colPct; });
    var newPct=Math.max(8, Math.round(remaining / (100/12)) * (100/12));
    if(newPct<8) newPct=100/12;
    /* shrink last zone to make space */
    if(row.zones.length){
        var last=row.zones[row.zones.length-1];
        if(last.colPct - newPct >= 8) last.colPct -= newPct;
        else { newPct=Math.floor(last.colPct/2); last.colPct-=newPct; }
    }
    row.zones.push({id:zid, name:'pos_'+uid, colPct:newPct, zoneClass:'', fields:[]});
    render();
    serializeToHidden();
}

function deleteZone(rowId, zoneId){
    var row=state.rows.filter(function(r){ return r.id===rowId; })[0];
    if(!row) return;
    var removed=row.zones.filter(function(z){ return z.id===zoneId; })[0];
    row.zones=row.zones.filter(function(z){ return z.id!==zoneId; });
    /* redistribute freed width to last remaining zone */
    if(removed && row.zones.length){
        row.zones[row.zones.length-1].colPct += removed.colPct;
    }
    render();
    serializeToHidden();
}

function deleteRow(rowId){
    state.rows=state.rows.filter(function(r){ return r.id!==rowId; });
    render();
    serializeToHidden();
}

function addFieldToZone(zoneId, fieldName, fromZoneId){
    /* remove from source zone if coming from another zone */
    if(fromZoneId && fromZoneId!=='pool'){
        var src=findZone(fromZoneId);
        if(src) src.zone.fields=src.zone.fields.filter(function(f){ return f!==fieldName; });
    }
    var dest=findZone(zoneId);
    if(dest && dest.zone.fields.indexOf(fieldName)===-1){
        dest.zone.fields.push(fieldName);
    }
    render();
    serializeToHidden();
}

function removeField(zoneId, fieldName){
    var z=findZone(zoneId);
    if(z) z.zone.fields=z.zone.fields.filter(function(f){ return f!==fieldName; });
    render();
    serializeToHidden();
}

function swapZones(aId, bId){
    var a=findZone(aId), b=findZone(bId);
    if(!a||!b) return;
    /* swap within same row */
    if(a.row===b.row){
        var tmp=a.row.zones[a.zi];
        a.row.zones[a.zi]=b.row.zones[b.zi];
        b.row.zones[b.zi]=tmp;
    }
    render();
    serializeToHidden();
}

function addRow(){
    var rid=nextId('r');
    var zid=nextId('z');
    state.rows.push({id:rid, rowClass:'', zones:[{id:zid, name:'position_'+uid, colPct:100, zoneClass:'', fields:[]}]});
    render();
    serializeToHidden();
}

/* ----------------------------------------------------------------
   SERIALIZATION → hidden field
   ---------------------------------------------------------------- */
function syncInputsToState(){
    /* Lire tous les inputs de classe/nom visibles et forcer la sync avec state
       avant la sérialisation — au cas où un input n'aurait pas encore déclenché blur */
    document.querySelectorAll('.fcpb-row').forEach(function(rowEl){
        var rid = rowEl.dataset.rowId;
        var r   = state.rows.filter(function(r){ return r.id===rid; })[0];
        if(!r) return;
        var classInp = rowEl.querySelector('.fcpb-row-class-input');
        if(classInp) r.rowClass = classInp.value.trim();

        rowEl.querySelectorAll('.fcpb-zone').forEach(function(zoneEl){
            var zid = zoneEl.dataset.zoneId;
            var z   = null;
            r.zones.forEach(function(zz){ if(zz.id===zid) z=zz; });
            if(!z) return;
            var nameInp      = zoneEl.querySelector('.fcpb-zone-name');
            var zoneClassInp = zoneEl.querySelector('.fcpb-zone-class-input');
            if(nameInp)      z.name      = nameInp.value;
            if(zoneClassInp) z.zoneClass = zoneClassInp.value.trim();
        });
    });
}

function serializeToHidden(){
    syncInputsToState();
    var json = JSON.stringify(state);

    /* Encoder en base64 pour survivre au getArray() de Joomla qui filtre les caractères JSON.
       PHP décodera avec base64_decode() dans $saved_layout_json. */
    var encoded = btoa(unescape(encodeURIComponent(json)));
    var f = document.getElementById('fcpb-json-field');
    if(f) f.value = encoded;
}

/* ----------------------------------------------------------------
   PHP GENERATION
   ---------------------------------------------------------------- */
function generatePHP(){
    /* Les balises PHP sont construites dynamiquement pour éviter
       que le parser PHP du serveur ne les interprète dans ce bloc JS. */
    var o='<?', c='?>';
    var php=o+'php /* Auto-generated by FC Page Builder */\n\n'+c+'\n';
    state.rows.forEach(function(row, ri){
        var rowCls = 'row g-2 align-items-start'+(row.rowClass ? ' '+row.rowClass : '');
        php+='<!-- Row '+(ri+1)+' -->\n<div class="'+rowCls+'">\n';
        row.zones.forEach(function(zone){
            var col=FCPB_PCT_TO_COL(zone.colPct);
            var pos=zone.name;
            var zoneCls = 'col-12 col-md-'+col+(zone.zoneClass ? ' '+zone.zoneClass : '');
            php+='  '+o+'php if (isset($item->positions[\''+pos+'\'])) : '+c+'\n';
            php+='  <div class="'+zoneCls+'">\n';
            php+='    '+o+'php foreach ($item->positions[\''+pos+'\'] as $field) : '+c+'\n';
            php+='    <div class="flexi element field_'+o+'php echo $field->name; '+c+'">\n';
            php+='      '+o+'php if ($field->label) : '+c+'\n';
            php+='        <span class="flexi label field_'+o+'php echo $field->name; '+c+'">'+o+'php echo $field->label; '+c+'</span>\n';
            php+='      '+o+'php endif; '+c+'\n';
            php+='      <div class="flexi value field_'+o+'php echo $field->name; '+c+'">'+o+'php echo $field->display; '+c+'</div>\n';
            php+='    </div>\n';
            php+='    '+o+'php endforeach; '+c+'\n';
            php+='  </div>\n';
            php+='  '+o+'php endif; '+c+'\n';
        });
        php+='</div><!-- /row '+(ri+1)+' -->\n\n';
    });
    return php;
}

/* ----------------------------------------------------------------
   TOOLBAR BUTTONS
   ---------------------------------------------------------------- */
document.getElementById('fcpb-add-row').onclick=function(){ addRow(); };

document.getElementById('fcpb-reset').onclick=function(){
    if(!confirm('Réinitialiser le layout builder ?')) return;
    state={rows:[
        {id:nextId('r'),rowClass:'',zones:[{id:nextId('z'),name:'subtitle1',colPct:100,zoneClass:'',fields:[]}]},
        {id:nextId('r'),rowClass:'',zones:[{id:nextId('z'),name:'image',colPct:33,zoneClass:'',fields:[]},{id:nextId('z'),name:'top',colPct:67,zoneClass:'',fields:[]}]},
        {id:nextId('r'),rowClass:'',zones:[{id:nextId('z'),name:'description',colPct:100,zoneClass:'',fields:[]}]},
        {id:nextId('r'),rowClass:'',zones:[{id:nextId('z'),name:'bottom',colPct:100,zoneClass:'',fields:[]}]}
    ]};
    render();
    serializeToHidden();
    document.getElementById('fcpb-php-output').style.display='none';
};

document.getElementById('fcpb-gen-php').onclick=function(){
    var out=document.getElementById('fcpb-php-output');
    out.textContent=generatePHP();
    out.style.display='block';
    out.scrollIntoView({behavior:'smooth',block:'nearest'});
};

/* Intercepter TOUTES les façons de soumettre le formulaire Joomla */
(function(){

    /* 1. Wrap Joomla.submitbutton (toolbar Save / Apply) */
    function wrapSubmitButton(){
        if(typeof Joomla === 'undefined' || !Joomla.submitbutton) return false;
        var orig = Joomla.submitbutton.bind(Joomla);
        Joomla.submitbutton = function(task){
            serializeToHidden();
            orig(task);
        };
        return true;
    }

    /* Tenter immédiatement, puis repoll si Joomla pas encore chargé */
    if(!wrapSubmitButton()){
        var poll = setInterval(function(){
            if(wrapSubmitButton()) clearInterval(poll);
        }, 100);
    }

    /* 2. Intercepter le submit natif du form (capture phase) */
    var form = document.getElementById('adminForm');
    if(form){
        form.addEventListener('submit', function(){ serializeToHidden(); }, true);
    }

    /* 3. Sécurité : MutationObserver sur adminForm pour détecter un submit dynamique */
    if(typeof MutationObserver !== 'undefined' && form){
        var obs = new MutationObserver(function(){ serializeToHidden(); });
        obs.observe(form, {attributes:true, attributeFilter:['action']});
    }

})();

document.getElementById('fcpb-save').onclick=function(){
    serializeToHidden();
    if(typeof Joomla !== 'undefined' && Joomla.submitbutton){
        Joomla.submitbutton('templates.apply');
    } else {
        document.getElementById('adminForm').task.value = 'templates.apply';
        document.getElementById('adminForm').submit();
    }
};

/* ----------------------------------------------------------------
   FILTRES — les selects utilisent j2select (jQuery chosen/select2).
   L'événement 'change' natif n'est pas déclenché — on branche jQuery.
   ---------------------------------------------------------------- */
(function(){
    var activeContentType = '';
    var activeFieldType   = '';

    function applyFilters(){
        var chips = document.querySelectorAll('#fcpb-pool-fields .fcpb-field-chip');
        chips.forEach(function(chip){
            var show = true;
            if(activeContentType && !chip.classList.contains(activeContentType)) show=false;
            if(activeFieldType   && !chip.classList.contains(activeFieldType))   show=false;
            chip.style.display = show ? '' : 'none';
        });
        /* Masquer les titres de section si tous leurs chips sont cachés */
        document.querySelectorAll('#fcpb-pool-fields .fcpb-pool-section').forEach(function(sec){
            var visible = Array.prototype.slice.call(sec.querySelectorAll('.fcpb-field-chip'))
                .filter(function(c){ return c.style.display !== 'none'; }).length > 0;
            sec.style.display = visible ? '' : 'none';
        });
    }
    /* Exposer pour renderPool() */
    window.fcpb_applyFilters = applyFilters;

    function handleSelectChange(id, val){
        val = val || '';
        var isFieldType = (id.indexOf('field_type') !== -1);
        if(val === '') {
            if(isFieldType) activeFieldType='';
            else activeContentType='';
        } else if(isFieldType) {
            activeFieldType = 'field_type_' + val;
        } else {
            activeContentType = 'content_type_' + val;
        }
        applyFilters();
    }

    function bindSelects(){
        if(typeof jQuery === 'undefined') { setTimeout(bindSelects, 200); return; }
        /* Binder sur le document pour éviter les problèmes de j2select
           qui peut déplacer le <select> hors du container #fcpb-pool */
        jQuery(document).off('change.fcpb_ct change.fcpb_ft')
            .on('change.fcpb_ct', '#content_type_builder', function(){
                handleSelectChange(this.id, jQuery(this).val());
            })
            .on('change.fcpb_ft', '#field_type_builder', function(){
                handleSelectChange(this.id, jQuery(this).val());
            });
    }

    if(typeof jQuery !== 'undefined'){
        jQuery(document).ready(function(){ bindSelects(); });
    } else {
        setTimeout(bindSelects, 500);
    }
})();

/* ----------------------------------------------------------------
   BOOT
   ---------------------------------------------------------------- */
render();
serializeToHidden();

})();
</script>

<?php
// Init JS sort (existing FLEXIcontent mechanism)
?>
<script>
function tmpls_fcfield_init_ordering(){
    <?php echo $this->jssort . ';'; ?>
}
var jformToken   = '<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>';
var isCoreLayout = <?php echo in_array($this->layout->name, array('grid','table','faq','items-tabbed')) ? 1 : 0; ?>;
</script>
<?php $this->document->addScript(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/js/layout_editor.js', array('version' => FLEXI_VHASH)); ?>