<?php
/**
 * @version 1.5 stable $Id: default.php 1890 2014-04-26 04:19:53Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

defined('_JEXEC') or die('Restricted access');
use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;
$app = JFactory::getApplication();
$doc = JFactory::getDocument();
$tooltip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';

require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');
$action = JRoute::_(FlexicontentHelperRoute::getSearchRoute(0, $itemid), true);
$form_id = "default_form_".$module->id;
$form_name = "default_form_".$module->id;

$txtmode = (int) $params->get('txtmode', 0);
$show_search_label = $params->get('show_search_label', 1);
$search_autocomplete = $params->get( 'search_autocomplete', 1 );
$flexi_button_class_go =  ($params->get('flexi_button_class_go' ,'') != '-1')  ?
    $params->get('flexi_button_class_go', (FLEXI_J30GE ? 'btn btn-success' : 'fc_button'))   :
    $params->get('flexi_button_class_go_custom', (FLEXI_J30GE ? 'btn btn-success' : 'fc_button'))  ;
$flexi_button_class_direct =  ($params->get('flexi_button_class_direct' ,'') != '-1')  ?
    $params->get('flexi_button_class_direct', (FLEXI_J30GE ? 'btn' : 'fc_button'))   :
    $params->get('flexi_button_class_direct_custom', (FLEXI_J30GE ? 'btn' : 'fc_button'))  ;
$flexi_button_class_advanced =  ($params->get('flexi_button_class_advanced' ,'') != '-1')  ?
    $params->get('flexi_button_class_advanced', (FLEXI_J30GE ? 'btn' : 'fc_button'))   :
    $params->get('flexi_button_class_advanced_custom', (FLEXI_J30GE ? 'btn' : 'fc_button'));

// Get if text searching according to specific (single) content type
$show_txtfields = (int) $params->get('show_txtfields', 1);  // 0: hide, 1: according to content, 2: use custom configuration
$show_txtfields = !$txtmode ? 0 : $show_txtfields;  // disable this flag if using BASIC index for text search

/**
 * ***** TODO check these variables that are normally meant
 * ***** for advanced search view only and not for module
 */

// Get if filtering according to specific (single) content type
$show_filters      = 1;
$type_based_search = 0;
$canseltypes = $params->get('canseltypes', 1);  // SET "type selection FLAG" back into parameters


// Force single type selection and showing the content type selector
/*
$show_filters   = (int) $params->get('show_filters', 1);  // 0: hide, 1: according to content, 2: use custom configuration
$type_based_search = $show_filters === 1 || $show_txtfields === 1;
$canseltypes = $type_based_search ? 1 : $canseltypes;
*/


/**
 * Get Content Types allowed for user selection in the Search Form
 * Also retrieve their configuration, plus the currently selected types
 */

// Get them from configuration
$contenttypes = $params->get('contenttypes', array(), 'array');

// Sanitize them as integers and as an array
$contenttypes = ArrayHelper::toInteger($contenttypes);

// Make sure these are unique too
$contenttypes = array_unique($contenttypes);

// Check for zero content types (can occur during sanitizing content ids to integers)
foreach($contenttypes as $i => $v)
{
	if (!$contenttypes[$i])
	{
		unset($contenttypes[$i]);
	}
}

// Force hidden content type selection if only 1 content type was initially configured
//$canseltypes = count($contenttypes) === 1 ? 0 : $canseltypes;

// Type data and configuration (parameters), if no content types specified then all will be retrieved
$typeData = flexicontent_db::getTypeData($contenttypes);
$contenttypes = array();

foreach($typeData as $tdata)
{
	$contenttypes[] = $tdata->id;
}

// Get Content Types to use either those currently selected in the Search Form, or those hard-configured in the search menu item
if ($canseltypes)
{
	// Get them from user request data
	$form_contenttypes = $jinput->get('contenttypes', array(), 'array');

	// Sanitize them as integers and as an array
	$form_contenttypes = ArrayHelper::toInteger($form_contenttypes);

	// Make sure these are unique too
	$form_contenttypes = array_unique($form_contenttypes);

	// Check for zero content type (can occur during sanitizing content ids to integers)
	foreach($form_contenttypes as $i => $v)
	{
		if (!$form_contenttypes[$i])
		{
			unset($form_contenttypes[$i]);
		}
	}

	// Limit to allowed item types (configuration) if this is empty
	$form_contenttypes = array_intersect($contenttypes, $form_contenttypes);

	// If we found some allowed content types then use them otherwise keep the configuration defaults
	if (!empty($form_contenttypes))
	{
		$contenttypes = $form_contenttypes;
	}
}

// Type based seach, get a single content type (first one, if more than 1 were given ...)
if ($type_based_search && $canseltypes && !empty($form_contenttypes))
{
	$single_contenttype = reset($form_contenttypes);
	$contenttypes = $form_contenttypes = array($single_contenttype);
}
else
{
	$single_contenttype = false;
}



/**
 * Text Search Fields of the search form
 */

if (!$txtmode)
{
	$txtflds = array();
	$fields_text = array();
}

else
{
	$txtflds = '';

	if ($show_txtfields === 1)
	{
		$txtflds = $single_contenttype
			? $typeData[$single_contenttype]->params->get('searchable', '')
			: '';
	}
	elseif ($show_txtfields)
	{
		$txtflds = $params->get('txtflds', '');
	}

	// Sanitize them
	$txtflds = preg_replace("/[\"'\\\]/u", "", $txtflds);
	$txtflds = array_unique(preg_split("/\s*,\s*/u", $txtflds));
	if ( !strlen($txtflds[0]) ) unset($txtflds[0]);

	// Create a comma list of them
	$txtflds_list = count($txtflds) ? "'".implode("','", $txtflds)."'" : '';

	// Retrieve field properties/parameters, verifying the support to be used as Text Search Fields
	// This will return all supported fields if field limiting list is empty
	$fields_text = FlexicontentFields::getSearchFields($key='id', $indexer='advanced', $txtflds_list, $contenttypes, $load_params=true, 0, 'search');

	// If all entries of field limiting list were invalid, get ALL
	if (empty($fields_text))
	{
		if (!empty($contenttypes))
		{
			$fields_text = FlexicontentFields::getSearchFields($key='id', $indexer='advanced', null, $contenttypes, $load_params=true, 0, 'search');
		}
		else
		{
			$fields_text = array();
		}
	}
}

/**
 * Create Form Elements (the 'lists' array)
 */

$lists = array();

// *** Selector of Content Types
if ($canseltypes)
{
	$types = array();

	if ($show_filters)
	{
		$types[] = JHtml::_('select.option', '', JText::_('FLEXI_PLEASE_SELECT'));
	}

	foreach($typeData as $type)
	{
		$types[] = JHtml::_('select.option', $type->id, JText::_($type->name));
	}

	$attrs = array();
	$attrs['class'] = 'fc_field_filter use_select2_lib fc_prompt_internal';
	//$attrs['class'] .= ' fc_label_internal';  $attrs['data-fc_label_text'] = "...";

	if ($show_filters)
	{
		$attrs['onchange'] = "adminFormPrepare(this.form); this.form.submit();";
		$attrs['class'] .= ' fc_is_selmultiple';
	}
	else
	{
		 $attrs['multiple'] = "multiple";
	}
	$attrs['size'] = "5";
	$attrs['data-placeholder']    = htmlspecialchars(JText::_('FLEXI_CLICK_TO_LIST', ENT_QUOTES, 'UTF-8'));
	$attrs['data-fc_prompt_text'] = htmlspecialchars(JText::_('FLEXI_TYPE_TO_FILTER', ENT_QUOTES, 'UTF-8'));

	$lists['contenttypes'] = JHtml::_('select.genericlist',
		$types,
		'contenttypes[]',
		$attrs,
		'value',
		'text',
		(empty($form_contenttypes) ? '' : $form_contenttypes),
		'contenttypes_'.$module->id
	);
}

// *** Selector of Fields for text searching
// THIS is wrong value 1 means hide the fields and use the configured fields
// if( in_array($txtmode, array(1,2)) && count($fields_text) )
if( $txtmode==2 && count($fields_text) )
{
	// Get selected text fields in the Search Form
	$form_txtflds = $jinput->get('txtflds', array(), 'array');

	if ($form_txtflds)
	{
		foreach ($form_txtflds as $i => $form_txtfld)
		{
			$form_txtflds[$i] = JFilterInput::getInstance()->clean($form_txtfld, 'string');
		}
	}

	$lists['txtflds'] = JHtml::_('select.genericlist',
		$fields_text,
		'txtflds[]',
		array(
			'multiple' => 'multiple',
			'size' => '5',
			'class' => 'fc_field_filter use_select2_lib fc_prompt_internal fc_is_selmultiple',
			'data-placeholder' => htmlspecialchars(JText::_('FLEXI_CLICK_TO_LIST', ENT_QUOTES, 'UTF-8')),
			'data-fc_prompt_text' => htmlspecialchars(JText::_('FLEXI_TYPE_TO_FILTER', ENT_QUOTES, 'UTF-8')),
		),
		'name',
		'label',
		$form_txtflds,
		'txtflds'
	);
}
$autodisplayadvoptions = $params->get('autodisplayadvoptions', 1);
//$autodisplayadvoptions = empty($contenttypes) ? 0 : $autodisplayadvoptions;
// Whether to show advanced options or hide them, initial behaviour depends on $autodisplayadvoptions, which is calculated above
$use_advsearch_options = $app->input->get('use_advsearch_options', (int) ($autodisplayadvoptions==2), 'int');


$js ="";

if ($autodisplayadvoptions)
{
 $js .= '
	jQuery(document).ready(function() {
	  var status = {
	    "true": "open",
	    "false": "close"
	  };


	  jQuery("#modfcadvsearch_fcsearch_txtflds_row_'.$module->id.'").css("position","relative").hide(0, function(){}).css("position","static");

	  '. (($autodisplayadvoptions==1 && !$use_advsearch_options) ? '' : 'jQuery("#modfcadvsearch_fcsearch_txtflds_row_'.$module->id.'").css("position","relative").toggle(500, function(){}).css("position","static");') .'

	  jQuery("#modfcadvsearch_use_advsearch_options_'.$module->id.'").click(function() {

		  jQuery("#modfcadvsearch_fcsearch_txtflds_row_'.$module->id.'").css("position","relative").toggle(500, function(){}).css("position","static");
    });
  '
  .( 1 /*$this->params->get('canseltypes', 1)!=2*//*disable hiding*/ ? '' : '
	  jQuery("#modfcadvsearch_fcsearch_contenttypes_row_'.$module->id.'").css("position","relative").hide(0, function(){}).css("position","static");

	  '. (($autodisplayadvoptions==1 && !$use_advsearch_options) ? '' : 'jQuery("#modfcadvsearch_fcsearch_contenttypes_row_'.$module->id.'").css("position","relative").toggle(500, function(){}).css("position","static");') .'

	  jQuery("#modfcadvsearch_use_advsearch_options_'.$module->id.'").click(function() {

		  jQuery("#modfcadvsearch_fcsearch_contenttypes_row_'.$module->id.'").css("position","relative").toggle(500, function(){}).css("position","static");
    }); ').
  '
	  jQuery("#modfcadvsearch_fc_advsearch_options_set_'.$module->id.'").css("position","relative").hide(0, function(){}).css("position","static");

	  '. (($autodisplayadvoptions==1 && !$use_advsearch_options) ? '' : 'jQuery("#modfcadvsearch_fc_advsearch_options_set_'.$module->id.'").css("position","relative").toggle(500, function(){}).css("position","static");') .'

	  jQuery("#modfcadvsearch_use_advsearch_options_'.$module->id.'").click(function() {

		  jQuery("#modfcadvsearch_fc_advsearch_options_set_'.$module->id.'").css("position","relative").toggle(500, function(){}).css("position","static");
    });

	});
	';
}

$doc->addScriptDeclaration($js);
?>

<div class="mod_flexiadvsearch_wrapper mod_flexiadvsearch_wrap<?php echo $moduleclass_sfx; ?>" id="mod_flexiadvsearch_default<?php echo $module->id ?>">

<form class="mod_flexiadvsearch<?php echo $params->get('moduleclass_sfx'); ?>" name="<?php echo $form_name; ?>" id="<?php echo $form_id; ?>" action="<?php echo $action; ?>" method="post" role="search">

	<?php if ($params->get('canseltypes', 1) && isset($lists['contenttypes'])) : ?>
	<fieldset id="modfcadvsearch_fc_advsearch_options_set_<?php echo $module->id;?>" class="fc_search_set">
		<legend>
			<span class="fc_legend_text <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_SEARCH_CONTENT_TYPE', 'FLEXI_SEARCH_CONTENT_TYPE_TIP', 1); ?>">
				<span><?php echo JText::_('FLEXI_SEARCH_CONTENT_TYPE'); ?></span>
			</span>
		</legend>

		<table id="fc_textsearch_tbl_<?php echo $module->id;?>" class="fc_search_tbl <?php echo $params->get('pageclass_sfx', ''); ?>" >

			<tr id="modfcadvsearch_fcsearch_contenttypes_row_<?php echo $module->id;?>" class="fc_search_row_<?php echo (($r++)%2);?>">
				<?php if($params->get('show_type_label', 1)): ?>
				<td class="fc_search_label_cell">
					<label for="contenttypes" class="label">
						<?php echo JText::_('FLEXI_SEARCH_CONTENT_TYPE'); ?>
					</label>
				</td>
				<td class="fc_search_option_cell">
					<div class="fc_filter_html">
						<?php echo $lists['contenttypes'];?>
					</div>
				</td>
				<?php else: ?>
				<td class="fc_search_option_cell">
					<div class="fc_filter_html">
						<?php echo $lists['contenttypes'];?>
					</div>
				</td>
				<?php endif; ?>
			</tr>
		</table>
	</fieldset>
	<?php endif; ?>

	<?php if (!$params->get('canseltypes', 1)) : ?>
		<?php foreach($typeData as $type) : ?>
		<input type="hidden" name="contenttypes[]" value="<?php echo $type->id;?>" />
		<?php endforeach;?>
	<?php endif; ?>

	<div class="search<?php echo $params->get('moduleclass_sfx') ?>">
		<input name="option" type="hidden" value="com_flexicontent" />
		<input name="view" type="hidden" value="search" />
		<span class="fc_filter_html fc_text_search">
		<?php
		$prependToText =
			( $button && $button_pos == 'left' ) ||
			( $direct && $direct_pos == 'left' ) ||
			( $link_to_advsearch && $link_to_advsearch_pos == 'left' );
		$appendToText =
			( $button && $button_pos == 'right' ) ||
			( $direct && $direct_pos == 'right' ) ||
			( $link_to_advsearch && $link_to_advsearch_pos == 'right' );
		$isInputGrp = $prependToText || $appendToText;

		$_ac_index = $txtmode ? 'fc_adv_complete' : 'fc_basic_complete';
		$text_search_class  = !$isInputGrp ? 'fc_text_filter' : '';
		$_label_internal = '';//'fc_label_internal';  // data-fc_label_text="..."
		$text_search_class .= $search_autocomplete ? ($search_autocomplete==2 ? ' fc_index_complete_tlike '.$_ac_index : ' fc_index_complete_simple '.$_ac_index.' '.$_label_internal) : ' '.$_label_internal;

		//$text_search_label = JText::_($show_search_label==2 ? 'FLEXI_TEXT_SEARCH' : 'FLEXI_TYPE_TO_LIST');
		$search_inner_width = JText::_($params->get('search_inner_width', 20));
		$search_inner_prompt = JText::_($params->get('search_inner_prompt', 'FLEXI_ADV_MOD_SEARCH_PROMPT'));
		$width = $params->get('width', 10);
		$maxchars = $params->get('maxchars', 200);

		$button_html = $direct_html = $hidden_html = false;
		$top_html = $bottom_html = $output = array();

		//$output[] = '<input name="q" id="mod_search_searchword-'.$module->id.'" maxlength="'.$maxlength.'" alt="'.$button_text.'" class="fc_field_filter inputbox" type="text" size="'.$width.'" value="'.$text.'"  onblur="if(this.value==\'\') this.value=\''.$text.'\';" onfocus="if(this.value==\''.$text.'\') this.value=\'\';" />';

		$q = $app->input->getString('q', '');
		$searchword = $app->input->getString('filter', $q);

		$output[] = '
			<input type="'.($search_autocomplete==2 ? 'hidden' : 'text').'"
				data-txt_ac_lang="' . JFactory::getLanguage()->getTag() . '"
				id="mod_search_searchword-'.$module->id.'" class="'.$text_search_class.'"
				placeholder="'.$search_inner_prompt.'" label="'.$search_inner_prompt.'"  name="q" '.($search_autocomplete==2 ? '' : ' size="'.$search_inner_width.'" maxlength="'.$maxchars.'"').' value="'.$searchword.'" aria-label="'.$search_inner_prompt.'"  />';

		// Search's GO button
		if ($button) :
			if ($button_as) :
				$button_html = '<input type="image" title="'.$button_text.'" class="'.(!$isInputGrp ? 'fc_filter_button' : '').$tooltip_class.' '.$flexi_button_class_go.'" src="' . JUri::root(true) . '/' . $button_image . '" onclick="this.form.q.focus();"/>';
			else :
				$button_html = '<input type="submit" value="'.$button_text.'" class="'.(!$isInputGrp ? 'fc_filter_button' : '').' '.$flexi_button_class_go.'" onclick="this.form.q.focus();"/>';
			endif;
		else :
			/* Hidden submit button so that pressing Enter will work */
			$hidden_html = '<input type="submit" value="'.$button_text.'" style="position:absolute; left:-9999px;" onclick="this.form.q.focus();" />';
		endif;

		if ($button_html) switch ($button_pos) :
			case 'top'   : $top_html[]    = $button_html;  break;
			case 'bottom': $bottom_html[] = $button_html;  break;
			case 'right' : array_push($output, $button_html);  break;
			case 'left'  :
			default      : array_unshift($output, $button_html); break;
		endswitch;

		// Search's DIRECT (lucky) button
		if ($direct) :
			if ($direct_as) :
				// hidden field, is workaround for image button not being able to submit a value
				$direct_html = '
					<input type="hidden" name="direct" value="" />
					<input type="image" title="'.$direct_text.'" class="'.(!$isInputGrp ? 'fc_filter_button' : '').$tooltip_class.' '.$flexi_button_class_direct.'" src="' . JUri::root(true) . '/' . $direct_image . '" onclick="this.form.direct.value=1; this.form.q.focus();"/>
					';
			else :
			 $direct_html = '<input type="submit" name="direct" value="'.$direct_text.'" class="'.(!$isInputGrp ? 'fc_filter_button' : '').' '.$flexi_button_class_direct.'" onclick="this.form.q.focus();"/>';
			endif;

			if ($direct_html) switch ($direct_pos) :
				case 'top'   : $top_html[]    = $direct_html;  break;
				case 'bottom': $bottom_html[] = $direct_html;  break;
				case 'right' : array_push($output, $direct_html);  break;
				case 'left'  :
				default      : array_unshift($output, $direct_html); break;
			endswitch;
		endif;

		// Search's 'ADVANCED' link button
		if ($link_to_advsearch) :
			$link_to_advsearch_html = '<input type="button" onclick="window.location.href=\''.$action.'\';" class="'.(!$isInputGrp ? 'fc_filter_button' : '').' '.$flexi_button_class_advanced.'" value="'.$link_to_advsearch_txt.'" />';

			if ($link_to_advsearch_html) switch ($link_to_advsearch_pos) :
				case 'top'   : $top_html[]    = $link_to_advsearch_html;  break;
				case 'bottom': $bottom_html[] = $link_to_advsearch_html;  break;
				case 'right' : array_push($output, $link_to_advsearch_html);  break;
				case 'left'  :
				default      : array_unshift($output, $link_to_advsearch_html); break;
			endswitch;
		endif;

		// Display the optional buttons and advanced search box
		if ($autodisplayadvoptions)
		{
			$checked_attr  = $use_advsearch_options ? 'checked=checked' : '';
			$checked_class = $use_advsearch_options ? 'btn-primary' : '';
			$advbox_button_html = '
			<input type="checkbox" id="modfcadvsearch_use_advsearch_options_'.$module->id.'" name="use_advsearch_options" value="1" '.$checked_attr.' onclick="jQuery(this).next().toggleClass(\'btn-primary\');" style="display:none" />
			<label id="modfcadvsearch_use_advsearch_options_lbl_'.$module->id.'" class="btn '.$checked_class.' hasTooltip" for="modfcadvsearch_use_advsearch_options_'.$module->id.'" title="'.JText::_('FLEXI_SEARCH_ADVANCED_OPTIONS').'">
				<span class="icon-list"></span>' . JText::_('FLEXI_SEARCH_ADVANCED') . '
			</label>
			';
			array_push($output, $advbox_button_html);
		}

		// If using button in same row try to create bootstrap btn input append
		$txt_grp_class = $params->get('bootstrap_ver', 2)==2  ?  (($prependToText ? ' input-prepend' : '') . ($appendToText ? ' input-append' : '')) : 'input-group';
		$input_grp_class = $params->get('bootstrap_ver', 2)==2  ?  'input-prepend  input-append' : 'input-group';

		$output =
			(count($top_html) > 1 ? '<span class="btn-wrapper '.$input_grp_class.'">'.implode("\n", $top_html).'</span>' : implode("\n", $top_html)).
			(count($output) > 1 ? '<span class="btn-wrapper '.$txt_grp_class.'">'.implode("\n", $output).'</span>' : implode("\n", $output)).
			(count($bottom_html) > 1 ? '<span class="btn-wrapper '.$input_grp_class.'">'.implode("\n", $bottom_html).'</span>' : implode("\n", $bottom_html));

		echo $output . $hidden_html;
		?>

		</span>
	</div>

<?php if ($autodisplayadvoptions && $params->get('canseltext', 1) && isset($lists['txtflds'])) : ?>
<table>
	<tr id="modfcadvsearch_fcsearch_txtflds_row_<?php echo $module->id;?>" class="fc_search_row_<?php echo (($r++)%2);?>">
		<td class="fc_search_label_cell">
			<label for="txtflds" class="label <?php echo $tooltip_class; ?>" title="<?php echo flexicontent_html::getToolTip('FLEXI_SEARCH_SEARCHWORDS_IN_FIELDS', 'FLEXI_SEARCH_SEARCHWORDS_IN_FIELDS_TIP', 1); ?>">
				<?php echo JText::_('FLEXI_SEARCH_SEARCHWORDS_IN_FIELDS'); ?>:
			</label>
		</td>
		<td class="fc_search_option_cell">
			<div class="fc_filter_html">
				<?php echo $lists['txtflds'];?>
			</div>
		</td>
	</tr>
</table>
<?php endif; ?>

</form>
</div>

<?php
$js = '
	jQuery(document).ready(function() {
		jQuery("#'.$form_id.' input:not(.fc_autosubmit_exclude):not(.select2-input), #'.$form_id.' select:not(.fc_autosubmit_exclude)").on("change", function() {
			var form=document.getElementById("'.$form_id.'");
			adminFormPrepare(form, 1);
		});
	});
';
$doc->addScriptDeclaration($js);
?>
