<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

class flexicontent_state
{
	static $instance = null;   // Needed to be defined in extended class for proper late static binding, definition here is not enough
	var $records = null;
	var $types = null;
	var $ckname = null;

	/**
	 * Gets class's singleton object
	 *
	 * @return  object
	 *
	 * @since 3.3.0
	 */
	public static function getInstance()
	{
		if (static::$instance === null)
		{
			static::$instance = new flexicontent_state();
		}

		return static::$instance;
	}

	/**
	 * Method to load records from cookie
	 *
	 * @access public
	 *
	 * @return void
	 *
	 * @since 3.3.0
	 */
	protected function loadState()
	{
		// Load the cookie, only if not already loaded
		if ($this->records)
		{
			return;
		}
		$this->records = JFactory::getApplication()->input->cookie->get($this->ckname, '{}', 'string');

		// Parse the favourites
		try
		{
			$this->records = json_decode($this->records);
		}
		catch (Exception $e)
		{
			$jcookie->set($this->ckname, '{}', time()+60*60*24*(365*5), JUri::base(true), '');
		}

		// Make sure it is a class
		if (!$this->records)
		{
			$this->records = new stdClass();
		}

		// Convert data to array and disgard not known types
		foreach($this->records as $type => $id_arr)
		{
			if (isset($this->types[$type]))
			{
				$this->records->$type = (array)$id_arr;
				continue;
			}

			unset ($this->records->$type);
		}

		// Validate record ids of each type as integers, and also validate record data
		foreach($this->types as $type => $i)
		{
			$arr = array();
			if (!isset($this->records->$type))
			{
				$this->records->$type = array();
			}

			foreach($this->records->$type as $record_id => $record_data)
			{
				$record_id = (int) $record_id;
				$arr[$record_id] = $this->validateRecordData($record_data);
			}
			$this->records->$type = $arr;
		}
	}


	/**
	 * Method to validate the record data
	 *
	 * @access public
	 * @param  mixed    $record_data   The data of the record inside the cookie
	 *
	 * @return mixed    The validated data
	 *
	 * @since 3.3.0
	 */
	protected function validateRecordData($record_data)
	{
		// By default no modification is done, extending classes may implement a custom validation
		return $record_data;
	}


	/**
	 * Method to get all records or records of a specific type from state (typically from cookie or session or both)
	 *
	 * @access public
	 * @param  $type    The type of favourites
	 * @return void
	 *
	 * @since 3.3.0
	 */
	public function getRecords($type=null)
	{
		$this->loadState();

		return $type ? $this->records->$type : $this->records;
	}


	/**
	 * Method to save records into state (typically cookie or session or both)
	 *
	 * @access public
	 * @return void
	 *
	 * @since 3.3.0
	 */
	public function saveState()
	{
		$this->loadState();

		$app = JFactory::getApplication();
		$jcookie = $app->input->cookie;

		// Clear any cookie set to current path, and set cookie at top-level folder of current joomla installation
		$jcookie->set($this->ckname, null, 1, '', '');
		$jcookie->set($this->ckname, json_encode($this->records), time()+60*60*24*(365*5), JUri::base(true), '');
	}
}

