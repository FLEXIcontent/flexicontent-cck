<?php			

extract($displayData);

// Make use of select2 lib
flexicontent_html::loadFramework('select2');
$classes  = " use_select2_lib";
$extra_param = '';
$options = array();

// MULTI-select: special label and prompts
if ($display_filter_as == 6)
{
	$classes .= ' fc_prompt_internal fc_is_selmultiple';
	
	// Add field's LABEL internally or click to select PROMPT (via js)
	$_inner_lb = $label_filter==2 ? $filter->label : JText::_('FLEXI_CLICK_TO_LIST');
	if ($label_filter==2)
	{
		$options[] = JHtml::_('select.option', '', $_inner_lb, 'value', 'text', $_disabled = true);
	}
	$extra_param = ' data-placeholder="'.htmlspecialchars($_inner_lb, ENT_QUOTES, 'UTF-8').'"';

	// Add type to filter PROMPT (via js)
	$extra_param .= ' data-fc_prompt_text="'.htmlspecialchars(JText::_('FLEXI_TYPE_TO_FILTER'), ENT_QUOTES, 'UTF-8').'"';
}

// SINGLE-select does not has an internal label a drop-down list option
else
{
	if ($label_filter==-1) {  // *** e.g. BACKEND ITEMS MANAGER custom filter
		$filter->html = '<span class="'.$filter->parameters->get( 'label_filter_css'.$_s, 'label' ).'">'.$filter->label.'</span>';
		$first_option_txt = '';
	} else if ($label_filter==2) {
		$first_option_txt = $filter->label;
	} else {
		$first_option_txt = $filter->parameters->get( 'filter_usefirstoption'.$_s, 0) ? $filter->parameters->get( 'filter_firstoptiontext'.$_s, 'FLEXI_ALL') : 'FLEXI_ANY';
		$first_option_txt = JText::_($first_option_txt);
	}
	$options[] = JHtml::_('select.option', '', !$first_option_txt ? '-' : '- '.$first_option_txt.' -');
}


foreach ($results as $result)
{
	if ( !strlen($result->value) ) continue;
	$options[] = JHtml::_('select.option', $result->value, $result->text, 'value', 'text', $disabled = ($faceted_filter==2 && !$result->found));
}

// Create HTML tag attributes
$attribs_str  = ' class="fc_field_filter'.$classes.'" '.$extra_param;
$attribs_str .= $display_filter_as==6 ? ' multiple="multiple" size="5" ' : '';
if ( $extra_attribs = $filter->parameters->get( 'filter_extra_attribs'.$_s, '' ) )
{
	$attribs_str .= $extra_attribs;
}
//$attribs_str .= ($display_filter_as==0 || $display_filter_as==6) ? ' onchange="document.getElementById(\''.$formName.'\').submit();"' : '';

if ($display_filter_as==6 && $combine_tip)
{
	$filter->html	.= ' <span class="fc_filter_tip_inline badge badge-info">'.JText::_(!$require_all_param ? 'FLEXI_ANY_OF' : 'FLEXI_ALL_OF').'</span> ';
}

// Calculate if field has value
$has_value = (!is_array($value) && strlen($value)) || (is_array($value) && count($value));
$filter->html	.= $label_filter==2 && $has_value
	? ' <span class="badge fc_mobile_label" style="display:none;">'.JText::_($filter->label).'</span> '
	: '';

if ($display_filter_as==0 || $display_filter_as==6)
{
	// Need selected values: array('') instead of array(), to force selecting the "field's prompt option" (e.g. field label) thus avoid "0 selected" display in mobiles
	$filter->html	.= $display_filter_as != 6
		? JHtml::_('select.genericlist', $options, $filter_ffname, $attribs_str, 'value', 'text', $value, $filter_ffid)
		: JHtml::_('select.genericlist', $options, $filter_ffname.'[]', $attribs_str, 'value', 'text', ($label_filter==2 && !count($value) ? array('') : $value), $filter_ffid);
}
else
{
	$filter->html	.=
		JHtml::_('select.genericlist', $options, $filter_ffname.'[1]', $attribs_str, 'value', 'text', @ $value[1], $filter_ffid.'1') . '
			' . $opentag_filter . ($use_font_icons ? ' <span class="fc_icon_range icon-arrow-left-4"></span><span class="fc_icon_range icon-arrow-right-4"></span> ' : ' <span class="fc_range"></span> ') . $closetag_filter .'
		' . JHtml::_('select.genericlist', $options, $filter_ffname.'[2]', $attribs_str, 'value', 'text', @ $value[2], $filter_ffid.'2');
}