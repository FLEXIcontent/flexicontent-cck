<?php
/**
 * @package     Flexicontent Add to Calendar Field
 *
 * @author      Shane Vanhoeck, special thanks to the FLEXIcontent team: Emmanuel Danan, Georgios Papadakis, Yannick Berges and other contributors
 * @copyright   Copyright © 2023, Com3elles, All Rights Reserved
 * @license     GNU General Public License version 3 or later; see LICENSE
 */

class Config
{
	// datetime, date & time formats
	const DATETIME_FORMAT = 'Y-m-d H:i';
	const DATE_FORMAT = 'Y-m-d';
	const TIME_FORMAT = 'H:i';

	private $recurrenceByMonthTypes = ['week_day', 'month_day'];

	private array $weekDayAbbreviations = [
		'SU',
		'MO',
		'TU',
		'WE',
		'TH',
		'FR',
		'SA'
	];

	private array $supportedLanguages = [
		'ar',
		'cs',
		'de',
		'en',
		'es',
		'fi',
		'fr',
		'hi',
		'id',
		'it',
		'ja',
		'ko',
		'nl',
		'no',
		'ro',
		'pl',
		'pt',
		'sv',
		'tr',
		'vi',
		'zh'
	];

	private array $subfields = [
		[
			'name'       => 'name',
			'validation' => 'string',
			'linkable'   => true
		],
		[
			'name'       => 'description',
			'validation' => 'string',
			'linkable'   => true
		],
		[
			'name'       => 'location',
			'validation' => 'string',
			'linkable'   => true
		],
		[
			'name'       => 'recurrent',
			'validation' => 'uint',
			'linkable'   => false
		],
		[
			'name'       => 'recurrence',
			'validation' => 'string',
			'linkable'   => false,
		],
		[
			'name'       => 'rrule',
			'validation' => 'string',
			'linkable'   => false
		],
		[
			'name'       => 'recurrence_interval',
			'validation' => 'uint',
			'linkable'   => false,
		],
		[
			'name'       => 'recurrence_by_day',
			'validation' => 'array:string',
			'linkable'   => false,
		],
		[
			'name'       => 'recurrence_by_month_type',
			'validation' => 'string',
			'linkable'   => false
		],
		[
			'name'       => 'recurrence_by_month_day_interval',
			'validation' => 'monthDayInterval',
			'linkable'   => false
		],
		[
			'name'       => 'recurrence_until',
			'validation' => 'date',
			'linkable'   => false
		],
		[
			'name'       => 'multi_dates',
			'validation' => 'uint',
			'linkable'   => false
		],
		[
			'name'       => 'dates',
			'validation' => 'dates',
			'linkable'   => false
		],
		[
			'name'       => 'all_day',
			'validation' => 'uint',
			'linkable'   => false
		],
		[
			'name'       => 'start_datetime',
			'validation' => 'date',
			'linkable'   => true
		],
		[
			'name'       => 'end_datetime',
			'validation' => 'date',
			'linkable'   => true
		],
		[
			'name'       => 'name_use_parent',
			'validation' => 'uint',
			'linkable'   => false
		],
		[
			'name'       => 'description_use_parent',
			'validation' => 'uint',
			'linkable'   => false
		],
		[
			'name'       => 'location_use_parent',
			'validation' => 'uint',
			'linkable'   => false
		],
	];

	/**
	 * Property getter
	 *
	 * @param   string  $prop
	 *
	 * @return mixed
	 *
	 * @throws Exception
	 */
	public function get(string $prop)
	{
		if (!property_exists($this, $prop))
		{
			throw new Exception('Property not found: ' . $prop);
		}

		return $this->{$prop};
	}

	/**
	 * Method to get a format.
	 *
	 * @param   string  $type  The type of format to get e.g.: datetime, date or time.
	 *
	 * @return string
	 *
	 * @throws Exception
	 */
	public function getFormat(string $type): string
	{
		switch (strtolower($type))
		{
			case 'datetime':
				return self::DATETIME_FORMAT;
			case 'date':
				return self::DATE_FORMAT;
			case 'time':
				return self::TIME_FORMAT;
			default:
				throw new Exception('Format type unknown, please provide a valid format type e.g.: datetime, date or time');
		}
	}

	/**
	 * Get abbreviated day of the week from day number in english (used for recurrence).
	 *
	 * @param   integer  $day  The numeric day of the week.
	 *
	 * @return  string  The abbreviated day of the week in english.
	 *
	 * @throws Exception
	 */
	public function getDayAbbreviation(int $day): string
	{
		if (!in_array($day, array_keys($this->weekDayAbbreviations)))
		{
			throw new Exception('Day must be an integer between 0 and 6 included.');
		}

		return $this->weekDayAbbreviations[$day];
	}
}
