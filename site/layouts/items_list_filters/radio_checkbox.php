<?php			

/**
 * Place override at: /templates/TEMPLATENAME/html/layouts/com_flexicontent/items_list_filters/
 */

extract($displayData);
$_s = $isSearchView ? '_s' : '';

// Component's parameters
$cparams = JComponentHelper::getParams('com_flexicontent');  // createFilter maybe called in backend too ...
$use_font_icons = $cparams->get('use_font_icons', 1);

// Field's parameters
$label_filter = $filter->parameters->get( 'display_label_filter'.$_s, 0 ) ;   // How to show filter label
$faceted_filter = $filter->parameters->get( 'faceted_filter'.$_s, 2);
$display_filter_as = $filter->parameters->get( 'display_filter_as'.$_s, 0 );  // Filter Type of Display

// Field's parameters, that are specific to some types
$filter_vals_display = $filter->parameters->get( 'filter_vals_display'.$_s, 0 ); // radioimage / checkboximage

if ($filter_vals_display)
{
	$icon_size  = $filter->parameters->get( 'icon_size_filter'.$_s );
	$icon_color = $filter->parameters->get( 'icon_color_filter'.$_s );
	
	$icon_class = ($icon_size ? ' fc-icon-'.$icon_size : '');
	$icon_style = ($icon_color ? ' color: '.$icon_color.';' : '');
}

$scroll_min = 10;  // Minimum number of filter values, after which a content scrollbar is added, TODO: add parameter for this
$add_scrollbar = count($results) >= $scroll_min;

if ($add_scrollbar)
{
	flexicontent_html::loadFramework('mCSB');
}

$clear_values = 0;
$value_style = $clear_values ? 'float:left; clear:both;' : '';

$i = 0;

$checked = is_array($value)
	? !count($value) || (!is_array(reset($value))  && !strlen(reset($value)))
	: !strlen($value);			$checked_attr = $checked ? 'checked="checked"' : '';
$checked_attr = $checked ? 'checked="checked"' : '';
$checked_class = $checked ? 'fc_highlight' : '';
$checked_class_li = $checked ? ' fc_checkradio_checked' : '';

$filter->html .= '
	<div class="fc_checkradio_group_wrapper fc_add_scroller'.($add_scrollbar ? ' fc_list_filter_wrapper':'').'">
		<ul class="fc_field_filter fc_checkradio_group'.($add_scrollbar ? ' fc_list_filter':'').'">
			<li class="fc_checkradio_option fc_checkradio_special'.$checked_class_li.'" style="'.$value_style.'">
			' . ($label_filter==2  ? ' <span class="fc_filter_label_inline">'.$filter->label.'</span> ' : '');

if ($display_filter_as == 4)
{
	$filter->html .= '
				<input onchange="fc_toggleClassGrp(this, \'fc_highlight\', 1);"
					id="'.$filter_ffid.$i.'" type="radio" name="'.$filter_ffname.'"
					value="" '.$checked_attr.' class="fc_checkradio" />';
}
else
{
	$filter->html .= '
				<input onchange="fc_toggleClass(this, \'fc_highlight\', 1);"
					id="'.$filter_ffid.$i.'" type="checkbox" name="'.$filter_ffname.'[]"
					value="" '.$checked_attr.' class="fc_checkradio" />';
}

$tooltip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
$tooltip_title = flexicontent_html::getToolTip('FLEXI_REMOVE_ALL', '', $translate=1, $escape=1);
$filter->html .= '<label class="'.$checked_class.$tooltip_class.'" for="'.$filter_ffid.$i.'" '
	.' title="'.$tooltip_title.'" '
	.($checked ? ' style="display:none!important;" ' : ' style="background:none!important; padding-left:0px!important;" ').'>'.
	'<span class="fc_delall_filters"></span>';
$filter->html .= '</label> '
	.($filter->parameters->get('filter_values_require_all_tip', 0) ? ' <span class="fc_filter_tip_inline badge bg-info badge-info">'.JText::_(!$require_all_values ? 'FLEXI_ANY_OF' : 'FLEXI_ALL_OF').'</span> ' : '')
	.' </li>';
$i++;

foreach ($results as $result)
{
	if ( !strlen($result->value) )
	{
		continue;
	}
	$checked = ($display_filter_as==5)
		? in_array($result->value, $value)
		: $result->value == $value;

	$checked_attr = $checked
		? ' checked=checked ' : '';
	$disable_attr = $faceted_filter==2 && !$result->found
		? ' disabled=disabled ' : '';

	$checked_class = $checked
		? 'fc_highlight' : '';
	$checked_class .= $faceted_filter==2 && !$result->found
		? ' fcdisabled ' : '';
	$checked_class_li = $checked
		? ' fc_checkradio_checked' : '';

	$result_text_encoded = htmlspecialchars($result->text, ENT_COMPAT, 'UTF-8');

	$filter->html .= '<li class="fc_checkradio_option'.$checked_class_li.'" style="'.$value_style.'">';
	
	// *** PLACE image before label (and e.g. (default) above the label)
	if ($filter_vals_display == 2)
	{
		$filter->html .= isset( $result->image_url ) ?
			'<span class="fc_filter_val_img"><img onclick="jQuery(this).closest(\'li\').find(\'input\').click();" src="'.$result->image_url.'" alt="'.$result_text_encoded.'" title="'.$result_text_encoded.'" /></span>' :
			'<span class="fc_filter_val_img"><span onclick="jQuery(this).closest(\'li\').find(\'input\').click();" class="'.$result->image.$icon_class.'" style="'.$icon_style.'" title="'.$result_text_encoded.'"></span></span>' ;
	}
	
	if ($display_filter_as==4)
	{
		$filter->html .= ' <input onchange="fc_toggleClassGrp(this, \'fc_highlight\');" ';
		$filter->html .= '  id="'.$filter_ffid.$i.'" type="radio" name="'.$filter_ffname.'" ';
		$filter->html .= '  value="'.$result->value.'" '.$checked_attr.$disable_attr.' class="fc_checkradio" />';
	}
	else
	{
		$filter->html .= ' <input onchange="fc_toggleClass(this, \'fc_highlight\');" ';
		$filter->html .= '  id="'.$filter_ffid.$i.'" type="checkbox" name="'.$filter_ffname.'[]" ';
		$filter->html .= '  value="'.$result->value.'" '.$checked_attr.$disable_attr.' class="fc_checkradio" />';
	}
	
	$filter->html .= '<label class="fc_filter_val fc_cleared '.$checked_class.'" for="'.$filter_ffid.$i.'">';
	if ($filter_vals_display == 0 || $filter_vals_display == 2)
		$filter->html .= '<span class="fc_filter_val_lbl">' . $result_text_encoded . '</span>';
	else if ($add_usage_counters && $result->found)
		$filter->html .= '<span class="fc_filter_val_lbl">('.$result->found.')</span>';
	$filter->html .= '</label>';
	
	// *** PLACE image after label (and e.g. (default) next to the label)
	if ($filter_vals_display == 1)
	{
		$filter->html .= isset( $result->image_url ) ?
			'<span class="fc_filter_val_img">
				<img onclick="jQuery(this).closest(\'li\').find(\'input\').click();" src="'.$result->image_url.'" alt="' . $result_text_encoded . '" title="' . $result_text_encoded . '" />
			</span>' :
			'<span class="fc_filter_val_img">
				<span onclick="jQuery(this).closest(\'li\').find(\'input\').click();" class="'.$result->image.$icon_class.'" style="'.$icon_style.'" title="' . $result_text_encoded . '"></span>
			</span>' ;
	}
	
	$filter->html .= '</li>';
	$i++;
}
$filter->html .= '</ul>';
$filter->html .= '</div>';
