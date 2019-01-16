<?php

$valid_age_max        = (int) $field->parameters->get('valid_age_max', 0);
$age_unit_type        = (int) $field->parameters->get('age_unit', 7);
$show_age_unit        = (int) $field->parameters->get('show_age_unit', 1);

$sm_unit_only_until   = (int) $field->parameters->get('sm_unit_only_until', 2);
$append_sm_unit_until = (int) $field->parameters->get('append_sm_unit_until', 4);
$append_sm_unit_pfx   = $field->parameters->get('append_sm_unit_pfx ', ' +&nbsp;');
$append_sm_unit_sfx   = $field->parameters->get('append_sm_unit_sfx ', '');

$n = 0;
foreach ($values as $value)
{
	// If time is not allowed then disgard it from the value
	if (strlen($value) && !$date_allowtime)
	{
		@ list($date, $time) = preg_split('#\s+#', $value, $limit=2);
	}
	else
	{
		$date = $value;
	}

	// Try to parse the date string
	if (!empty($date))
	{
		$now = new DateTime();

		$vals = array();

		try {
			$date = new DateTime($value);

			$interval = $now->diff($date);
			$invalid = ($valid_age_max && $interval->y > $valid_age_max)
				|| $interval->y < 0
				|| $interval->m < 0
				|| $interval->d < 0;

			if ($invalid)
			{
				$age = JText::_('FLEXI_NA');
			}
			else
			{
				$age = '';

				switch($age_unit_type)
				{
					// YEARS
					case 7:
						$u = 'y';
						$s = 'm';
						$JText_normal_unit = JText::_('FLEXI_FIELD_DATE_YEARS');
						$JText_small_unit  = JText::_('FLEXI_FIELD_DATE_MONTHS');
						$small_units = 12;
						break;
					// QUARTERS (disabled our diff does not calculate this and our filtering does not support it, so such display will cause confusion)
					/*case 6:
						$u = 'q';
						$s = 'm';
						$JText_normal_unit = JText::_('FLEXI_FIELD_DATE_QUARTERS');
						$JText_small_unit  = JText::_('FLEXI_FIELD_DATE_MONTHS');
						$small_units = 3;
						break;*/
					// MONTHS
					case 5:
						$u = 'm';
						$s = 'd';
						$JText_normal_unit = JText::_('FLEXI_FIELD_DATE_MONTHS');
						$JText_small_unit  = JText::_('FLEXI_FIELD_DATE_DAYS');
						$small_units = 3;
						break;
					// WEEKS (disabled our diff does not calculate this and our filtering does not support it, so such display will cause confusion)
					/*case 3:
						$u = 'ww';
						$s = 'd';
						$JText_normal_unit = JText::_('FLEXI_FIELD_DATE_WEEKS');
						$JText_small_unit  = JText::_('FLEXI_FIELD_DATE_DAYS');
						$small_units = 7;
						break;*/
					// DAYS
					case 2:
						$u = 'd';
						$s = 'h';
						$JText_normal_unit = JText::_('FLEXI_FIELD_DATE_DAYS');
						$JText_small_unit  = JText::_('FLEXI_FIELD_DATE_HOURS');
						$small_units = 24;
						break;
				}
				
				// Create age according to unit configuration
				if (empty($age))
				{
					if ($sm_unit_only_until && $interval->$u < $sm_unit_only_until)
					{
						$age = ($small_units * $interval->$u + $interval->$s) . ' ' . $JText_small_unit;
					}
					elseif ($append_sm_unit_until && $interval->$u < $append_sm_unit_until)
					{
						$age = $interval->$u
							. ($show_age_unit ? ' ' . $JText_normal_unit : '')
							. $append_sm_unit_pfx . $interval->$s . ' ' . $JText_small_unit . $append_sm_unit_sfx;
					}
					else
					{
						$age = ($interval->$u + ($interval->$s >= ($small_units / 2) ? 1 : 0))
							. ($show_age_unit ? ' ' . $JText_normal_unit : '');
					}
				}
			}
		}
		catch (Exception $e) {
			$age = JText::_('FLEXI_NA');
		}
	}

	// Skip empty value, adding an empty placeholder if field inside in field group
	if (empty($date))
	{
		if ( $is_ingroup )
		{
			$field->{$prop}[$n++]	= $no_value_msg;
		}
		continue;
	}

	// Add prefix / suffix
	$field->{$prop}[$n]	= $pretext . $age . $posttext;

	// Add microdata to every value if field -- is -- in a field group
	if ($is_ingroup && $itemprop) $field->{$prop}[$n] = '<div style="display:inline" itemprop="'.$itemprop.'" >' .$field->{$prop}[$n]. '</div>';

	$n++;
	if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}