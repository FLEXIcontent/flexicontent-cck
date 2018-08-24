<?php
defined( '_JEXEC' ) or die( 'Restricted access' );
require_once('state.php');

class flexicontent_revs extends flexicontent_state
{
	static $instance = null;   // Needed to for late static binding, definition in parent is not enough, do not remove
	var $records = null;
	var $types = array('item' => 0, 'category' => 1);
	var $ckname = 'fcfavs';


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
			static::$instance = new flexicontent_favs();
		}

		return static::$instance;
	}


	/**
	 * Method to toggle Favoured FLAG form a given $type / (record) $id pair
	 *
	 * @access public
	 * @param  $type    The type of favourites
	 * @param  $id      The ID of a record
	 * @return void
	 *
	 * @since 3.3.0
	 */
	public function toggleIsFavoured($type, $id)
	{
		$this->loadState();
		$records = !empty($this->records->$type) ? $this->records->$type : array();

		if (isset($records[$id]))
		{
			unset($records[$id]);
			$this->records->$type = $records;
			return -1;
		}
		else
		{
			$records[$id] = 1;
			$this->records->$type = $records;
			return 1;
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
		// If cookie data of a record is set we assumed record is favoured
		return 1;
	}
}
