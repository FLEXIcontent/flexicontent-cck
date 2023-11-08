<?php
/**
 * @package     Flexicontent Add to Calendar Field
 *
 * @author      Shane Vanhoeck, special thanks to the FLEXIcontent team: Emmanuel Danan, Georgios Papadakis, Yannick Berges and other contributors
 * @copyright   Copyright © 2023, Com3elles, All Rights Reserved
 * @license     GNU General Public License version 3 or later; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');

$field  = $this->getField();
$item   = $this->getItem();
$config = $this->getConfig();
$data   = $this->values ?? [];
$html   = '';

if (!empty((array) $field))
{
	$formControl = 'custom[' . $field->name . '][0]';
	$form        = JForm::getInstance(
		'plg_flexicontent_fields_add_to_calendar.form.' . $field->name,
		JPATH_ROOT . '/plugins/flexicontent_fields/add_to_calendar/forms/event.xml',
		['control' => $formControl]
	);

	if ($form)
	{
		// bind data
		if (isset($data[0]) && !empty($data[0]))
		{
			$form->bind($data);
		}

		/*** modify form ***/

		// set toggled by
		$multiDatesField = $form->getFieldXml('multi_dates');
		$multiDatesField->addAttribute('data-atc-toggled-by', $formControl . '[recurrent]');
		// set default state
		$multiDates = isset($data[0]['multi_dates']) && $data[0]['multi_dates'];
		$multiDatesField->addAttribute('data-atc-initial-state', (int) $multiDates);

		// if subfields are linked (to other item fields): hide input + set html
		$addHtml   = [];
		$subfields = $config->get('subfields') ?? [];

		if (!empty($subfields))
		{
			foreach ($subfields as $subfield)
			{
				$subfield = (object) $subfield;

				if (!$subfield->linkable) continue;

				$linkedField   = null;
				$linkedFieldId = $field->parameters->get('field_' . $subfield->name);

				if (!empty($linkedFieldId) && !empty((array) $item))
				{
					// find linked field
					$linkedField = $this->getLinkedFieldById($linkedFieldId, $item);

					if (!empty($linkedField))
					{
						$dataToggledBy = '';
						$toggled       = '';

						// get subfield label before changing type to hidden
						$subfieldLabel = $form->getLabel($subfield->name);

						// hide subfield and remove required
						$form->setFieldAttribute($subfield->name, 'type', 'hidden');
						$form->setFieldAttribute($subfield->name, 'required', false);

						// set data attribute for toggle on multi dates & hide if needed
						if (in_array($subfield->name, ['start_datetime', 'end_datetime']))
						{
							$dataToggledBy = 'data-atc-toggled-by="' . $formControl . '[multi_dates]"';
							$toggled       = (isset($data[0]['multi_dates']) && $data[0]['multi_dates']) ? 'style=display:none;' : '';
						}

						// create html
						$subfieldHtml = '<div class="control-group" ' . $dataToggledBy . ' ' . $toggled . '>';
						$subfieldHtml .= '<div class="control-label">' . $subfieldLabel . '</div>';
						$subfieldHtml .= '<div class="controls">';
						$subfieldHtml .= '<p><span class="icon-link"></span> ';
						$subfieldHtml .= JText::sprintf('PLG_FLEXICONTENT_FIELDS_ADD_TO_CALENDAR_EVENT_FIELD_LINKED', $linkedField->label);
						$subfieldHtml .= '</p></div></div>';

						$addHtml[] = $subfieldHtml;
					}
				}
			}
		}

		// create field's html
		$html .= '<button type="button" class="btn btn-light js-atc-form-toggle">';
		$html .= '<span class="icon-edit"></span> Edit';
		$html .= '</button>';

		$html .= '<div class="atc-form" style="display: none;">';
		$html .= !empty($addHtml) ? implode('', $addHtml) : '';
		$html .= $form->renderFieldset('default');
		$html .= '</div>';
	}
}

$field->html[] = $html;
