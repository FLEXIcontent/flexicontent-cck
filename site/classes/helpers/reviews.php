<?php
defined( '_JEXEC' ) or die( 'Restricted access' );
require_once('state.php');

class flexicontent_favs extends flexicontent_state
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

		if (isset($this->records->$type[$id]))
		{
			unset($this->records->$type[$id]);
			return -1;
		}
		else
		{
			$this->records->$type[$id] = 1;
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
