<?php			

/**
 * Place override at: /templates/TEMPLATENAME/html/layouts/com_flexicontent/items_list_filters/
 */

extract($displayData);
$_s = $isSearchView ? '_s' : '';

$anyAsFirstLastValues = true;
$showAsTooltips = false;

// Component's parameters
$cparams = JComponentHelper::getParams('com_flexicontent');  // createFilter maybe called in backend too ...
$use_font_icons = $cparams->get('use_font_icons', 1);

// Field's parameters
$label_filter = $filter->parameters->get( 'display_label_filter'.$_s, 0 ) ;   // How to show filter label
$faceted_filter = $filter->parameters->get( 'faceted_filter'.$_s, 2);
$display_filter_as = $filter->parameters->get( 'display_filter_as'.$_s, 0 );  // Filter Type of Display

$isDate = in_array($filter->field_type, array('date','created','modified')) || $filter->parameters->get('isdate',0);
$isSlider = $display_filter_as == 7 || $display_filter_as == 8;
$slider_display_config = $filter->parameters->get( 'slider_display_config'.$_s, 1 );  // Slider found values: 1 or custom values/labels: 2

$size = $filter->parameters->get( 'text_filter_size', $isDate ? 15 : 30);        // Size of filter

if (!$isSlider)
{
	$_inner_lb = $label_filter==2 ? $filter->label : JText::_($isDate ? 'FLEXI_CLICK_CALENDAR' : ''/*'FLEXI_TYPE_TO_LIST'*/);
	$_inner_lb = htmlspecialchars($_inner_lb, ENT_QUOTES, 'UTF-8');

	$attribs_str = ' class="fc_field_filter '.($isDate ? 'fc_iscalendar' : '').'" placeholder="'.$_inner_lb.'"';
	$attribs_arr = array('class'=>'fc_field_filter '.($isDate ? 'fc_iscalendar' : '').'', 'placeholder' => $_inner_lb );
}

else
{
	$attribs_str = "";

	$value1 = $display_filter_as==8 ? @ $value[1] : $value;
	$value2 = @ $value[2];

	if ($isSlider && $slider_display_config==1)
	{
		$start = $min = 0;
		$end = $max = -1;
		$step = 1;
		
		$step_values = array();
		$step_labels = array();
		$i = 0;

		if ($anyAsFirstLastValues)
		{
			$step_values[] = "''";
			$step_labels[] = JText::_('FLEXI_ANY');
			$i++;
		}

		foreach ($results as $result)
		{
			if (!strlen($result->value))
			{
				continue;
			}

			$step_values[] = "'" . addcslashes($result->value, "'") . "'";
			$step_labels[] = $result->text;

			if ($result->value==$value1)
			{
				$start = $i;
			}

			if ($result->value==$value2)
			{
				$end = $i;
			}
			$i++;
		}

		// Set max according considering the skipped empty values
		$max = ($i - 1) + ($display_filter_as == 7 ? 0 : ($anyAsFirstLastValues ? 1 : 0));  //count($results)-1;
		if ($end == -1) $end = $max;  // Set end to last element if it was not set

		if ($display_filter_as == 8 && $anyAsFirstLastValues)
		{
			$step_values[] = "''";
			$step_labels[] = JText::_('FLEXI_ANY');
		}

		$step_range = 
				"step: 1,
				range: {'min': " .$min. ", 'max': " .$max. "},";
	}

	else if ($isSlider)
	{
		$custom_range  = $filter->parameters->get( 'slider_custom_range'.$_s, "'min': '', '25%': 500, '50%': 2000, '75%': 10000, 'max': ''" );
		$custom_labels = preg_split("/\s*##\s*/u", $filter->parameters->get( 'slider_custom_labels'.$_s, 'label_any ## label_500 ## label_2000 ## label_10000 ## label_any' ));

		if ($filter->parameters->get('slider_custom_labels_jtext'.$_s, 0))
		{
			foreach ($custom_labels as $i=> $custom_label)
			{
				$custom_labels[$i] = JText::_($custom_label);  // Language filter the custom labels
			}
		}

		$custom_vals = json_decode('{'.str_replace("'", '"', $custom_range).'}', true);

		// Terminate layouut execution, if misconfiguration is detected
		if (!$custom_vals)
		{
			$filter->html = '
				<div class="alert">
					Bad syntax for custom range for slider filter: '.$filter->label."
					EXAMPLE: <br/> 'min': 0, '25%': 500, '50%': 2000, '75%': 10000, 'max': 50000".'
				</div>';

			return;
		}

		$start = 0;
		$end   = count($custom_vals)-1;

		foreach ($custom_vals as $i => $v)
		{
			$step_values[$i] = "'" . addcslashes($v, "'") . "'";
		}

		$step_labels = & $custom_labels;
		$i = 0;
		$set_start = strlen($value1) > 0;
		$set_end   = strlen($value2) > 0;

		foreach ($custom_vals as $n => $custom_val)
		{
			if ($set_start && $custom_val==$value1) $start = $i;
			if ($set_end   && $custom_val==$value2) $end   = $i;
			$custom_vals[$n] = $i++;
		}

		$step_range = '
				snap: true,
				range: '.json_encode($custom_vals).',
		';
	}

	flexicontent_html::loadFramework('nouislider');

	$left_no = $display_filter_as==7 ? '' : '1';
	$rght_no = '2';  // sometimes unused

	$js = "
		jQuery(document).ready(function()
		{
			var slider = document.getElementById('".$filter_ffid."_nouislider');

			var input1 = document.getElementById('".$filter_ffid.$left_no."');
			var input2 = document.getElementById('".$filter_ffid.$rght_no."');
			var isSingle = ".($display_filter_as==7 ? '1' : '0').";

			var step_values = [".implode(', ', $step_values)."];
			var step_labels = [\"".implode('", "', array_map('addslashes', $step_labels))."\"];

			noUiSlider.create(slider, {".
				($display_filter_as==7 ? "
					start: ".$start.",
					connect: false,
				" : "
					start: [".$start.", ".$end."],
					connect: true,
				")."
					".$step_range."
			});

			var showAsTooltips = " . ($showAsTooltips ? 1 : 0) . ";
			var nodeHandles = slider.getElementsByClassName('noUi-handle'),
				tooltips = [];
			var mssgHandle = jQuery(slider).parent().find('.fcfilter_nouislider_txtbox').get(0),
				txtBoxes = [];

			// Add divs to the slider handles.
			for ( var i = 0; i < nodeHandles.length; i++ )
			{
				if (showAsTooltips)
				{
					tooltips[i] = document.createElement('span');
					nodeHandles[i].appendChild(tooltips[i]);

					tooltips[i].className += 'fc-sliderTooltip'; // Add a class for styling
					tooltips[i].innerHTML = '<span></span>'; // Add additional markup
					tooltips[i] = tooltips[i].getElementsByTagName('span')[0];  // Replace the tooltip reference with the span we just added
				}
				else
				{
					if (nodeHandles.length == 2 && i == 0)
					{
						var sep = document.createElement('span');
						sep.innerHTML = '<b>" . JText::_('FLEXI_FROM') . "</b>:&nbsp;';
						mssgHandle.appendChild(sep);
					}

					if (nodeHandles.length == 2 && i == 1)
					{
						var sep = document.createElement('span');
						sep.innerHTML = ' &nbsp; <b>" . JText::_('FLEXI_TO') . "</b>:&nbsp;';
						mssgHandle.appendChild(sep);
					}

					txtBoxes[i] = document.createElement('span');
					mssgHandle.appendChild(txtBoxes[i]);

					txtBoxes[i].className += ''; // Add a class for styling
					txtBoxes[i].innerHTML = '<span></span>'; // Add additional markup
					txtBoxes[i] = txtBoxes[i].getElementsByTagName('span')[0];  // Replace reference with the span we just added
				}
			}

			// When the slider changes, display the value in the tooltips and set it into the input form elements
			slider.noUiSlider.on('update', function(values, handle)
			{
				var value = parseInt(values[handle]);
				var i = value;
				
				if (handle)
				{
					input2.value = typeof step_values[value] !== 'undefined' ? step_values[value] : value;
				}

				else
				{
					input1.value = typeof step_values[value] !== 'undefined' ? step_values[value] : value;
				}
				
				var tooltip_text = typeof step_labels[value] !== 'undefined' ? step_labels[value] : value;

				if (showAsTooltips)
				{
					var max_len = 36;
					tooltips[handle].innerHTML = tooltip_text.length > max_len+4 ? tooltip_text.substring(0, max_len)+' ...' : tooltip_text;

					var left  = jQuery(tooltips[handle]).closest('.noUi-origin').position().left;
					var width = jQuery(tooltips[handle]).closest('.noUi-base').width();
					
					//window.console.log ('handle: ' + handle + ', left : ' + left + ', width : ' + width);
					if (isSingle)
					{
						left<(50/100)*width ?
							jQuery(tooltips[handle]).parent().removeClass('fc-left').addClass('fc-right') :
							jQuery(tooltips[handle]).parent().removeClass('fc-right').addClass('fc-left');
					}

					else if (handle)
					{
						left<=(76/100)*width ?
							jQuery(tooltips[handle]).parent().removeClass('fc-left').addClass('fc-right') :
							jQuery(tooltips[handle]).parent().removeClass('fc-right').addClass('fc-left');
						left<=(49/100)*width ?
							jQuery(tooltips[handle]).parent().addClass('fc-bottom') :
							jQuery(tooltips[handle]).parent().removeClass('fc-bottom');
					}

					else
					{
						left>=(24/100)*width ?
							jQuery(tooltips[handle]).parent().removeClass('fc-right').addClass('fc-left') :
							jQuery(tooltips[handle]).parent().removeClass('fc-left').addClass('fc-right');
						left>=(51/100)*width ?
							jQuery(tooltips[handle]).parent().addClass('fc-bottom') :
							jQuery(tooltips[handle]).parent().removeClass('fc-bottom');
					}
				}
				else
				{
					var max_len = 56;
					txtBoxes[handle].innerHTML = tooltip_text.length > max_len+4 ? tooltip_text.substring(0, max_len)+' ...' : tooltip_text;
				}

			});
			
			// Handle form autosubmit
			slider.noUiSlider.on('change', function()
			{
				var slider = jQuery('#".$filter_ffid."_nouislider');
				var jform  = slider.closest('form');
				var form   = jform.get(0);
				adminFormPrepare(form, parseInt(jform.attr('data-fc-autosubmit')));
			});
			
			input1.addEventListener('change', function()
			{
				var value = 0;  // default is first value = empty
				for(var i=1; i<step_values.length-1; i++) {
					if (step_values[i] == this.value) { value=i; break; }
				}
				slider.noUiSlider.set([value, null]);
			});

			".($display_filter_as==8 ? "
			input2.addEventListener('change', function()
			{
				var value = step_values.length-1;  // default is last value = empty
				for(var i=1; i<step_values.length-1; i++) {
					if (step_values[i] == this.value) { value=i; break; }
				}
				slider.noUiSlider.set([null, value]);
			});
			" : "")."
		});
	";

	JFactory::getDocument()->addScriptDeclaration($js);
	//JFactory::getDocument()->addStyleDeclaration("");
}

if ($display_filter_as==1 || $display_filter_as==7)
{
	if ($isDate && !$isSlider)
	{
		$filter->html	.= '
			<div class="fc_filter_element">
				'.FlexicontentFields::createCalendarField($value, $allowtime=0, $filter_ffname, $filter_ffid, $attribs_arr, $skip_on_invalid=false, $timezone=false, $filter->date_txtformat ).'
			</div>';
	}
	else
	{
		$filter->html	.=
		($isSlider ? '<div id="'.$filter_ffid.'_nouislider" class="fcfilter_with_nouislider ' . ($showAsTooltips ? '' : 'noToolTipSlider') . '"></div><div class="fc_slider_input_box">' : '').'
			<div class="fc_filter_element">
				<input id="'.$filter_ffid.'" name="'.$filter_ffname.'" '.$attribs_str.' type="text" size="'.$size.'" value="'.htmlspecialchars(@ $value, ENT_COMPAT, 'UTF-8').'" />
			</div>
		'.($isSlider ? '</div><div class="fcfilter_nouislider_txtbox">' . ($label_filter==2 ? '<span class="fc_slider_inner_label">' . $filter->label . ': </span>' : '') . '</div>' : '');
	}
}

else
{
	if ($isDate && !$isSlider)
	{
		$filter->html	.= '
			<div class="fc_filter_element">
				'.FlexicontentFields::createCalendarField(@ $value[1], $allowtime=0, $filter_ffname.'[1]', $filter_ffid.'1', $attribs_arr, $skip_on_invalid=false, $timezone=false, $filter->date_txtformat ).'
			</div>
			' . ($use_font_icons ? ' <span class="fc_icon_range icon-arrow-left-4"></span><span class="fc_icon_range icon-arrow-right-4"></span> ' : ' <span class="fc_range"></span> ') . '
			<div class="fc_filter_element">
				'.FlexicontentFields::createCalendarField(@ $value[2], $allowtime=0, $filter_ffname.'[2]', $filter_ffid.'2', $attribs_arr, $skip_on_invalid=false, $timezone=false, $filter->date_txtformat ).'
			</div>';
	}
	else
	{
		$size = (int) ($size / 2);
		$filter->html	.=
		($isSlider ? '<div id="'.$filter_ffid.'_nouislider" class="fcfilter_with_nouislider ' . ($showAsTooltips ? '' : 'noToolTipSlider') . '"></div><div class="fc_slider_input_box">' : '').'
			<div class="fc_filter_element">
				<input name="'.$filter_ffname.'[1]" '.$attribs_str.' id="'.$filter_ffid.'1" type="text" size="'.$size.'" value="'.htmlspecialchars($value[1] ?? '', ENT_COMPAT, 'UTF-8').'" />
			</div>
			' . ($use_font_icons ? ' <span class="fc_icon_range icon-arrow-left-4"></span><span class="fc_icon_range icon-arrow-right-4"></span> ' : ' <span class="fc_range"></span> ') . '
			<div class="fc_filter_element">
				<input name="'.$filter_ffname.'[2]" '.$attribs_str.' id="'.$filter_ffid.'2" type="text" size="'.$size.'" value="'.htmlspecialchars($value[2] ?? '', ENT_COMPAT, 'UTF-8').'" />
			</div>
		'.($isSlider ? '</div><div class="fcfilter_nouislider_txtbox">' . ($label_filter==2 ? '<span class="fc_slider_inner_label">' . $filter->label . ': </span>' : '') . '</div>' : '');
	}
}