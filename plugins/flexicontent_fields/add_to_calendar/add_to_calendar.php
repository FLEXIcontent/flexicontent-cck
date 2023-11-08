<?php
/**
 * @package     Flexicontent Add to Calendar Field
 *
 * @author      Shane Vanhoeck, special thanks to the FLEXIcontent team: Emmanuel Danan, Georgios Papadakis, Yannick Berges and other contributors
 * @copyright   Copyright © 2023, Com3elles, All Rights Reserved
 * @license     GNU General Public License version 3 or later; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');

JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');
JLoader::register('Config', JPATH_ROOT . '/plugins/flexicontent_fields/add_to_calendar/config.php');

class plgFlexicontent_fieldsAdd_To_Calendar extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitly when a field can render other field types
	var $task_callable = null;  // Field's methods allowed to be called via AJAX

	protected Config $config;
	protected $values = [];
	protected $field;

	/**
	 * Constructor
	 *
	 * @param $subject
	 * @param $params
	 */
	public function __construct(&$subject, $params)
	{
		parent::__construct($subject, $params);

		$this->config = new Config();
	}

	/*** DISPLAY methods, item form & frontend views ***/

	/**
	 * Method to create field's HTML display for item form
	 *
	 * @param $field
	 * @param $item
	 */
	public function onDisplayField(&$field, &$item)
	{
		parent::onDisplayField($field, $item);

		// add custom styles & script once
		static $initialized = null;
		if ($initialized === null)
		{
			$initialized = 1;

			$document = JFactory::getDocument();

			$document->addStyleSheet(JUri::root(true) . '/plugins/flexicontent_fields/add_to_calendar/dist/css/styles.css');
			$document->addScript(
				JUri::root(true) . '/plugins/flexicontent_fields/add_to_calendar/dist/js/form.min.js',
				false,
				['version' => FLEXI_VHASH]
			);
		}
	}

	/**
	 * Method to create field's HTML display for frontend views
	 *
	 * @param $field
	 * @param $item
	 * @param $values
	 * @param $prop
	 */
	public function onDisplayFieldValue(&$field, $item, $values = null, $prop = 'display')
	{
		parent::onDisplayFieldValue($field, $item, $values, $prop);

		// add js lib once
		static $initialized = null;
		if ($initialized === null)
		{
			$initialized = 1;

			$document = JFactory::getDocument();

			$document->addScript(
				JUri::root(true) . '/plugins/flexicontent_fields/add_to_calendar/dist/js/add-to-calendar-button.min.js',
				false,
				['version' => FLEXI_VHASH]
			);
		}
	}

	/*** METHODS HANDLING before & after saving / deleting field events ***/

	/**
	 * Method to handle field's values before they are saved into the DB
	 *
	 * @param $field
	 * @param $post
	 * @param $file
	 * @param $item
	 *
	 *
	 * @throws Exception
	 */
	public function onBeforeSaveField(&$field, &$post, &$file, &$item)
	{
		if (!in_array($field->field_type, (array) static::$field_types)) return;

		$this->setField($field);

		// Check if field has posted data
		if (empty($post) || !is_array($post)) return;

		if (!empty($post[0]))
		{
			// get form custom data for linked fields
			$custom    = $_POST['custom'];
			$subfields = $this->config->get('subfields');

			// clean data
			foreach ($post[0] as $k => $v)
			{
				// find subfield
				$idx = array_search($k, array_column($subfields, 'name'));

				if ($idx === false)
				{
					$post[0][$k] = '';
					continue;
				}

				$subfield = (object) $subfields[$idx];

				// check if subfield is linked
				$linkedFieldId = $field->parameters->get('field_' . $subfield->name, '');
				$linkedField   = $this->getLinkedFieldById($linkedFieldId, $item);

				if ($subfield->linkable && !empty((array) $linkedField))
				{
					// get linked field data
					$lData = $custom[$linkedField->name][0] ?? ''; // is always an array

					// handle field type
					switch ($linkedField->field_type)
					{
						case 'title':
							$lData = $_POST['jform']['title'] ?? '';
							break;
						case 'maintext':
							$lData = $_POST['jform']['text'] ?? '';
							break;
						case 'addressint':
							$lData = $lData['addr_display'] ?? '';
							break;
					}

					if (!empty($lData))
					{
						$lData = $this->filterData($lData, $subfield->validation);
					}

					$post[0][$subfield->name] = $lData;
				}
				else
				{
					$post[0][$k] = $this->filterData($v, $subfield->validation);
				}
			}

			// remove unnecessary data
			$post[0] = $this->removeUnecessaryData($post[0]);
		}
	}

	/**
	 * Method to take any actions/cleanups needed after field's values are saved into the DB
	 *
	 * @param $field
	 * @param $post
	 * @param $file
	 * @param $item
	 */
	public function onAfterSaveField(&$field, &$post, &$file, &$item)
	{
		if (!in_array($field->field_type, (array) static::$field_types)) return;
	}


	/**
	 * Method called just before the item is deleted to remove custom item data related to the field
	 *
	 * @param $field
	 * @param $item
	 */
	public function onBeforeDeleteField(&$field, &$item)
	{
		if (!in_array($field->field_type, (array) static::$field_types)) return;
	}

	/*** VARIOUS HELPER METHODS ***/

	/**
	 * Getter for config.
	 *
	 * @return Config
	 */
	protected function getConfig()
	{
		return $this->config;
	}

	/**
	 * Method to check if chosen language is supported.
	 *
	 * If chosen language is supported the language tag will be returned.
	 * If not language will fall back to English.
	 *
	 * @param   string  $language  The language to check upon.
	 *
	 * @return string The language tag as ISO 639-1 code on success, English language tag on failure.
	 *
	 * @link https://www.w3schools.com/tags/ref_language_codes.asp
	 */
	protected function getLanguage($language)
	{
		$default = 'en';

		if ($language === 'system')
		{
			$language = explode('-', JFactory::getLanguage()->get('tag'))[0];
		}

		if (empty($language) || !in_array($language, $this->config->get('supportedLanguages')))
		{
			return $default;
		}

		return $language;
	}

	/**
	 * Get linked field.
	 *
	 * @param   integer  $linkedFieldId
	 * @param   object   $item
	 *
	 * @return mixed|null
	 */
	protected function getLinkedFieldById($linkedFieldId, $item)
	{
		// get fields by ids
		$byIds = FlexicontentFields::indexFieldsByIds($item->fields, $item);

		// return field object
		return $byIds[$linkedFieldId] ?? null;
	}

	/**
	 * Filter input data
	 *
	 * @param   array   $data
	 * @param   string  $validation
	 *
	 * @return mixed|string
	 * @throws Exception
	 */
	private function filterData($data, $validation = 'string')
	{
		$inputFilter = JFilterInput::getInstance();

		// validate url
		if ($validation === 'string' && filter_var($data, FILTER_VALIDATE_URL))
		{
			return flexicontent_html::dataFilter($data, 0, 'url');
		}

		// validate time
		if ($validation === 'time')
		{
			if (!preg_match('/^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/', $data))
			{
				return '';
			}

			return $data;
		}

		// validate date
		if ($validation === 'date')
		{
			$timestamp = strtotime($data);

			if (!$timestamp)
			{
				return '';
			}

			return JDate::getInstance($timestamp)->format($this->config->getFormat('datetime'));
		}

		// validate dates array (subform)
		if ($validation === 'dates')
		{
			return $this->filterDates($data);
		}

		// validate month day interval (subform)
		if ($validation === 'monthDayInterval')
		{
			return $this->filterMonthDayIntervals($data);
		}

		// clean int (not being taken into account by flexi dataFilter !?)
		if ($validation === 'int' || $validation === 'uint')
		{
			return $inputFilter->clean($data, $validation);
		}

		// validate array (array validation string = e.g.: array:int, array:string, etc...)
		if (explode(':', $validation)[0] === 'array')
		{
			$validationType = explode(':', $validation)[1];

			return $this->filterArray($data, $validationType);
		}

		return flexicontent_html::dataFilter($data, 0, $validation);
	}

	/**
	 * Method to validate a dates array.
	 *
	 * @param   array  $data  An array of dates (see: {@see /plugins/flexicontent_fields/add_to_calendar/forms/date.xml}).
	 *
	 * @return array
	 */
	private function filterDates($data)
	{
		$canUseParent = ['name', 'description', 'location'];

		if (!is_array($data) || empty($data))
		{
			return [];
		}

		// A date has same type of subfields e.g.: name, description, etc..., and some specific e.g.: name_use_parent, description_use_parent, etc...
		$subfields = $this->config->get('subfields');

		foreach ($data as $i => $date)
		{
			// clean each field of date with corresponding validation
			foreach ($date as $k => $v)
			{
				// ignore values
				if (in_array($k, $canUseParent) && isset($date[$k . '_use_parent']) && (int) $date[$k . '_use_parent'] === 1)
				{
					$date[$k] = '';
					continue;
				}

				// find subfield
				$idx = array_search($k, array_column($subfields, 'name'));

				if ($idx === false)
				{
					$date[$k] = '';
					continue;
				}

				$subfield = (object) $subfields[$idx];

				// clean data
				$date[$k] = $this->filterData($v, $subfield->validation);
			}

			$data[$i] = $date;
		}

		return $data;
	}

	private function filterMonthDayIntervals($data)
	{
		if (!is_array($data) || empty($data))
		{
			return [];
		}

		// handle multiple
		if (array_keys($data)[0] === 'recurrence_by_month_day_interval0')
		{
			foreach ($data as $i => $monthDayInterval)
			{
				if (!$this->validateMonthDayInterval($monthDayInterval))
				{
					unset($data[$i]);
				}
			}

			return $data;
		}

		// handle single
		return $this->validateMonthDayInterval($data) ? $data : [];
	}

	/**
	 * Method to validate a month day interval.
	 *
	 * A month day interval is composed of a weekday (must be an abbreviated string like MO,TH,...)
	 * and an interval (the nth day of the month), a number between 1 and 4.
	 *
	 * @param $monthDayInterval
	 *
	 * @return bool
	 *
	 * @throws Exception
	 * @link see for the allowed week day abbreviations: .../config.php
	 */
	private function validateMonthDayInterval($monthDayInterval)
	{
		$weekDayAbbreviations = $this->config->get('weekDayAbbreviations');

		if (!is_array($monthDayInterval) || empty($monthDayInterval))
		{
			return false;
		}

		$interval = $monthDayInterval['interval'] ?? '';
		$day      = $monthDayInterval['day'] ?? '';

		$intervalValid = !empty($interval) && is_numeric($interval) && $interval >= 1 && $interval <= 4;
		$dayValid      = !empty($day) && in_array($day, $weekDayAbbreviations);

		return $intervalValid && $dayValid;
	}

	/**
	 * Filter an array of data.
	 *
	 * @param   array   $data            The array to validate.
	 * @param   string  $validationType  The validation type (e.g.: string, int, etc...),
	 *                                   all items of the array will be filtered by the same validation type.
	 *
	 * @throws Exception
	 */
	private function filterArray($data, $validationType)
	{
		if (!is_array($data))
		{
			return [];
		}

		foreach ($data as $k => $v)
		{
			$data[$k] = $this->filterData($v, $validationType);
		}

		return $data;
	}

	/**
	 * Transform dates array to array of date objects.
	 *
	 * @link https://add-to-calendar-button.com/examples#case-5.
	 *
	 * @param   array  $data        An array containing date arrays.
	 * @param   array  $parentData  Some data than can be transferred from parent to child (e.g: location).
	 *
	 * @return array An array of date objects on success, or empty array on failure.
	 * @throws Exception
	 */
	protected function parseDates($data, $parentData = [])
	{
		$dates        = [];
		$ignoreValues = ['all_day', 'name_use_parent', 'description_use_parent', 'location_use_parent'];
		$canUseParent = ['name', 'description', 'location'];

		if (empty($data)) return $dates;

		// create array of date objects
		foreach ($data as $date)
		{
			$newDate = new stdClass();
			$allDay  = $date['all_day'];

			// create date object
			foreach ($date as $k => $v)
			{
				// snake case to camelcase
				$prop = lcfirst(str_replace('_', '', ucwords($k, '_')));

				// ignore values
				if (empty($v) || in_array($k, $ignoreValues))
				{
					continue;
				}

				// set parent's value
				if (in_array($k, $canUseParent))
				{
					if ($date[$k . '_use_parent'] === 1 && isset($parentData[$k]))
					{
						$v = $parentData[$k];
					}
				}

				// separate datetime to date and time
				if ($k === 'start_datetime' || $k === 'end_datetime')
				{
					$datetime = Jdate::getInstance($v);

					$dateProp             = str_replace('time', '', $prop); // e.g.: startDate
					$newDate->{$dateProp} = $datetime->format($this->config->getFormat('date'));

					if (!$allDay)
					{
						$timeProp             = str_ireplace('datet', 'T', $prop); // e.g.: startTime
						$newDate->{$timeProp} = $datetime->format($this->config->getFormat('time'));
					}

					continue;
				}

				$newDate->{$prop} = $v;
			}

			$dates[] = $newDate;
		}

		return $dates;
	}

	/**
	 * Method to calculate the number of recurrences between start date and end of recurrence date.
	 *
	 * @param $data
	 *
	 * @return int|string The recurrence count to be applied or an empty string which equals infinite repetition.
	 *
	 * @throws Exception
	 */
	protected function calculateRecurrenceCount($data)
	{
		$app = JFactory::getApplication();

		$recurrence = $data['recurrence'] ?? '';
		$start      = explode(' ', $data['start_datetime'])[0] ?? ''; // only keep date
		$until      = explode(' ', $data['recurrence_until'])[0] ?? ''; // only keep date
		$dateFormat = $this->config->getFormat('date');

		if (!is_array($data) || empty($data) || empty($recurrence) || empty($start))
		{
			$app->enqueueMessage(
				JText::_('Could not calculate recurrence count, data is missing.'),
				'warning'
			);

			return '';
		}

		// no count if until is empty, will act as infinite
		if (empty($data['recurrence_until']))
		{
			return '';
		}

		// validate start and end date
		if (!strtotime($start) || !strtotime($until))
		{
			$app->enqueueMessage(
				JText::_('Could not calculate recurrence count, please provide a valid start & end date.'),
				'warning'
			);

			return '';
		}

		$start = DateTime::createFromFormat($dateFormat, $start);
		$until = DateTime::createFromFormat($dateFormat, $until);

		$diff = $start->diff($until);

		// date has passed
		if ($start->format($dateFormat) > $until->format($dateFormat))
		{
			return 0;
		}

		// calculate recurrence count depending on recurrence type
		$interval = $data['recurrence_interval'] ?? 1;

		switch ($recurrence)
		{
			case 'rrule':
				return '';
			case 'daily':
				return (int) (floor($diff->days / $interval) + 1);
			case 'weekly':
				// must multiply by number of days otherwise it will only count number of repetitions for one day
				$nbDays = 1;
				$weeks  = floor($diff->days / 7);

				if (!empty($data['recurrence_by_day']) && is_array($data['recurrence_by_day']))
				{
					$nbDays = count($data['recurrence_by_day']);
				}

				// make sure end date is last
				if ($diff->days === 1 && $nbDays > 1)
				{
					$nbDays = 1;
				}

				return (int) (floor(($weeks * $nbDays) / $interval)) + 1;
			case 'monthly':
				$type   = $data['recurrence_by_month_type'];
				$nbDays = 1;
				$months = (int) $diff->format('%m') + ((int) $diff->format('%y') * 12);

				if (!in_array($type, $this->config->get('recurrenceByMonthTypes')))
				{
					return 1;
				}

				if ($type === 'week_day')
				{
					// handle multiple (single by default)
					if (array_keys($data['recurrence_by_month_day_interval'])[0] === 'recurrence_by_month_day_interval0')
					{
						$nbDays = count($data['recurrence_by_month_day_interval']) ?? 1;
					}
				}

				return (int) (floor(($months * $nbDays) / $interval)) + 1;
			case 'yearly':
				$years = (int) $diff->format('%y') + 1;

				return (int) (floor($years / $interval));
			default:
				return 1;
		}
	}

	/**
	 * Method to remove/empty unecessary data from the post data before saving.
	 *
	 * @param   array  $post  The post data to be saved.
	 *
	 * @return mixed
	 *
	 * @throws Exception
	 */
	private function removeUnecessaryData($post)
	{
		$isMultiDates  = isset($post['multi_dates']) && (int) $post['multi_dates'] === 1;
		$hasRecurrence = isset($post['recurrent']) && (int) $post['recurrent'] === 1;

		// multi dates can not be a recurring event
		if ($isMultiDates)
		{
			$post['recurrent'] = 0;

			return $this->clearValuesByKeys($post, [
				'start_datetime',
				'end_datetime',
				'recurrence',
				'rrule',
				'recurrence_interval',
				'recurrence_by_day',
				'recurrence_by_month_type',
				'recurrence_by_month_day_interval',
				'recurrence_until'
			]);
		}

		// clear multi dates related values
		$post['multi_dates'] = 0;
		$post['dates']       = [];

		// handle recurrence
		if ($hasRecurrence)
		{
			// clear recurrence options that are not applied
			switch ($post['recurrence'])
			{
				case 'rrule':
					return $this->clearValuesByKeys($post, [
						'recurrence_interval',
						'recurrence_until',
						'recurrence_by_day',
						'recurrence_by_month_type',
						'recurrence_by_month_day_interval',
					]);
				case 'daily':
					return $this->clearValuesByKeys($post, [
						'rrule',
						'recurrence_by_day',
						'recurrence_by_month_type',
						'recurrence_by_month_day_interval',
					]);
				case 'weekly':
					return $this->clearValuesByKeys($post, [
						'rrule',
						'recurrence_by_month_type',
						'recurrence_by_month_day_interval',
					]);
				case 'monthly':
					$type = $post['recurrence_by_month_type'];

					return $this->clearValuesByKeys($post, [
						'rrule',
						'recurrence_by_day',
						($type !== 'week_day') ? 'recurrence_by_mont_day_interval' : ''
					]);
				case 'yearly':
					return $this->clearValuesByKeys($post, [
						'rrule',
						'recurrence_by_day',
						'recurrence_by_month_type',
						'recurrence_by_month_day_interval'
					]);
			}
		}

		// remove all recurrence options
		$post['recurrent'] = 0;
		foreach ($post as $k => $v)
		{
			if (str_contains('recurrence', $k) || $k === 'rrule')
			{
				$post[$k] = '';
			}
		}

		return $post;
	}

	/**
	 * Method to clear values in array by their keys.
	 *
	 * @param   array         $data  An array of key value pairs from wich to clear out the values.
	 * @param   string|array  $keys  A key or an array of keys from wich to clear the values in the data array.
	 *
	 * @return array
	 * @throws Exception
	 */
	private function clearValuesByKeys($data, $keys)
	{
		if (!is_array($data))
		{
			throw new Exception('Data must be an array');
		}

		if (is_array($keys))
		{
			foreach ($keys as $key)
			{
				if (isset($data[$key]) && !empty($data[$key]))
				{
					$data[$key] = '';
				}
			}
		}
		else
		{
			$key = $keys;

			if (isset($data[$key]) && !empty($data[$key]))
			{
				$data[$key] = '';
			}
		}

		return $data;
	}

	/**
	 * Method to parse an array of month day intervals (day, interval) into a valid mont day interval string
	 * e.g.: 3MO,1FR,... (equals to: every 3rd monday and 1st friday of the month).
	 *
	 * @param   array  $data  Either an array with a single month day interval (e.g.: ['day'=>'', 'interval' => 1])
	 *                        or an array of mont day intervals (e.g.: [['day' => '', 'interval' => 1],[...]]).
	 *
	 * @return string The parsed string.
	 */
	protected function parseMonthDayInterval($data)
	{
		$parsed = [];

		if (!is_array($data) || empty($data))
		{
			return '';
		}

		// handle multiple
		if (array_keys($data)[0] === 'recurrence_by_month_day_interval0')
		{
			foreach ($data as $monthDayInterval)
			{
				$parsed[] = $monthDayInterval['interval'] . $monthDayInterval['day'];
			}

			return implode(',', $parsed);
		}

		// handle single
		return $data['interval'] . $data['day'];
	}
}
