<?php
/**
 * @package     Flexicontent Add to Calendar Field
 *
 * @author      Shane Vanhoeck, special thanks to the FLEXIcontent team: Emmanuel Danan, Georgios Papadakis, Yannick Berges and other contributors
 * @copyright   Copyright © 2023, Com3elles, All Rights Reserved
 * @license     GNU General Public License version 3 or later; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');

$field        = $this->getField();
$config       = $this->getConfig();
$data         = $this->values ?? [];
$debug        = false;
$layoutConfig = [];
$dates        = [];
$html         = '';

// get data
if (isset($data[0]) && !empty($data[0]))
{
	$data = $data[0]; // form data is stored in custom[' . $field->name . '][0]

	// get dates
	if (isset($data['dates']) && !empty($data['dates']))
	{
		$dates = $this->parseDates(
			$data['dates'],
			[
				'name'        => $data['name'],
				'description' => $data['description'],
				'location'    => $data['location']
			]
		);
	}
}

// create layout config
if (!empty((array) $field))
{
	$debug        = (bool) $field->parameters->get('debug', 0);
	$layoutConfig = [
		'options'              => $field->parameters->get('layout_options', ['iCal']),
		'btn_style'            => $field->parameters->get('layout_btn_style', 'default'),
		'custom_css'           => $field->parameters->get('layout_custom_css', ''),
		'size'                 => $field->parameters->get('layout_size', '6'),
		'light_mode'           => $field->parameters->get('layout_light_mode', 'light'),
		'inline'               => $field->parameters->get('layout_inline', 0),
		'btns_list'            => $field->parameters->get('layout_btns_list', 0),
		'hide_text_label_btn'  => $field->parameters->get('layout_hide_text_label_btn', 0),
		'btn_label'            => $field->parameters->get('layout_btn_label', 'PLG_FLEXICONTENT_FIELDS_ADD_TO_CALENDAR_LAYOUT_BTN_LABEL_DEFAULT'),
		'trigger'              => $field->parameters->get('layout_trigger', 'hover'),
		'list_style'           => $field->parameters->get('layout_list_style', 'dropdown'),
		'hide_bg'              => $field->parameters->get('layout_hide_bg', 0),
		'hide_icon_btn'        => $field->parameters->get('layout_hide_icon_btn', 0),
		'hide_icon_list'       => $field->parameters->get('layout_hide_icon_list', 0),
		'hide_icon_modal'      => $field->parameters->get('layout_hide_icon_modal', 0),
		'hide_text_label_list' => $field->parameters->get('layout_hide_text_label_list', 0),
		'hide_checkmark'       => $field->parameters->get('layout_hide_checkmark', 0),
		'language'             => $this->getLanguage($field->parameters->get('language', 'en'))
	];
}

// create and set html

// set alert if data is empty and debug enabled
if (empty($data) && $debug)
{
	$html = '<div class="alert alert-danger">';
	$html .= JText::sprintf('PLG_FLEXICONTENT_FIELDS_ADD_TO_CALENDAR_VALUE_DISPLAY_NO_DATA_DEBUG_ALERT', $field->label);
	$html .= '</div>';

	$field->{$prop}[] = $html;

	return;
}

// create field value display
$html = '<add-to-calendar-button';

// enable debug
if ($debug)
{
	$html .= ' debug="true"';
}

// set data attributes
$html .= ' name="' . $data['name'] . '"';
$html .= $data['description'] ? ' description="' . $data['description'] . '"' : '';
$html .= ' timezone="' . $field->parameters->get('timezone', 'UTC') . '"';
$html .= $data['location'] ? ' location="' . $data['location'] . '"' : '';

if (!empty($dates))
{
	// multi dates display
	$html .= 'dates=\'' . json_encode($dates) . '\'';
}
else
{
	// default display
	$html .= !empty($data['start_datetime']) ? ' startDate="' . JHtml::date($data['start_datetime'], $config->getFormat('date')) . '"' : '';
	$html .= !empty($data['end_datetime']) ? ' endDate="' . JHtml::date($data['end_datetime'], $config->getFormat('date')) . '"' : '';
	$html .= !$data['all_day'] && !empty($data['start_datetime']) ? ' startTime="' . JHtml::date($data['start_datetime'], $config->getFormat('time')) . '"' : '';
	$html .= !$data['all_day'] && !empty($data['end_datetime']) ? ' endTime="' . JHtml::date($data['end_datetime'], $config->getFormat('time')) . '"' : '';

	// set recurrence
	if (!empty($data['recurrent']) && $data['recurrent'] === 1)
	{
		$recurrence = $data['recurrence'];

		if ($recurrence === 'rrule')
		{
			$html .= ' recurrence="RRULE:' . $data['rrule'] . '"';
		}
		else
		{
			$interval = $data['recurrence_interval'] ?? 1; // interval is at least 1
			$count    = $this->calculateRecurrenceCount($data); // no count = infinite

			// default options
			$html .= ' recurrence="' . $recurrence . '"';
			$html .= ' recurrence_interval="' . $interval . '"';
			$html .= $count !== '' ? ' recurrence_count="' . $count . '"' : '';

			// specific options
			switch ($recurrence)
			{
				case 'weekly':
					$html .= !empty($data['recurrence_by_day']) ? ' recurrence_byDay="' . implode(',', $data['recurrence_by_day']) . '"' : '';
					break;
				case 'monthly':
					$type = $data['recurrence_by_month_type'] ?? '';

					if ($type === 'week_day')
					{
						// create month day interval string, e.g.: 3MO,1FR (every third Monday and every first Friday of the month)
						$monthDayInterval = $this->parseMonthDayInterval($data['recurrence_by_month_day_interval']);

						$html .= !empty($monthDayInterval) ? ' recurrence_byDay="' . $monthDayInterval . '"' : '';
					}

					break;
			}
		}
	}
}

// set layout attributes
$html .= ' options="' . str_replace('"', '\'', json_encode($layoutConfig['options'])) . '"';
$html .= ' buttonStyle="' . $layoutConfig['btn_style'] . '"';
$html .= ' size="' . $layoutConfig['size'] . '"';
$html .= ' lightMode="' . $layoutConfig['light_mode'] . '"';
$html .= ' buttonsList="' . ($layoutConfig['btns_list'] ? 'true' : 'false') . '"';
$html .= ' hideTextLabelButton="' . ($layoutConfig['hide_text_label_btn'] ? 'true' : 'false') . '"';
$html .= ' label="' . JText::_($layoutConfig['btn_label']) . '"';
$html .= ' trigger="' . $layoutConfig['trigger'] . '"';
$html .= ' listStyle="' . $layoutConfig['list_style'] . '"';
$html .= ' hideBackground="' . ($layoutConfig['hide_bg'] ? 'true' : 'false') . '"';
$html .= ' hideIconButton="' . ($layoutConfig['hide_icon_btn'] ? 'true' : 'false') . '"';
$html .= ' hideIconList="' . ($layoutConfig['hide_icon_list'] ? 'true' : 'false') . '"';
$html .= ' hideIconModal="' . ($layoutConfig['hide_icon_modal'] ? 'true' : 'false') . '"';
$html .= ' hideTextLabelList="' . ($layoutConfig['hide_text_label_list'] ? 'true' : 'false') . '"';
$html .= ' hideCheckmark="' . ($layoutConfig['hide_checkmark'] ? 'true' : 'false') . '"';
$html .= ' inline="' . ($layoutConfig['inline'] ? 'true' : 'false') . '"';
$html .= ' language="' . $layoutConfig['language'] . '"';

$html .= '></add-to-calendar-button>';

$field->{$prop}[] = $html;
