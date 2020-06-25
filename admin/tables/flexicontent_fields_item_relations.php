<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

/**
 * FLEXIcontent table class
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.5
 */
class flexicontent_fields_item_relations extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $field_id 				= null;

	/**
	 * Primary Key
	 * @var int
	 */
	var $item_id				= null;

	/**
	 * Main order
	 * @var int
	 */
	var $valueorder				= null;

	/**
	 * Sub order
	 * @var int
	 */
	var $suborder				= null;

	/**
	 * Field value
	 * @var text
	 */
	var $value					= null;

	/**
	 * An Integer index of the value
	 * @var bigint
	 */
	var $value_integer  = null;

	/**
	 * A decimal index of the value
	 * @var decimal(65,15)
	 */
	var $value_decimal  = null;

	/**
	 * A date-time index of the value
	 * @var datetime
	 */
	var $value_datetime = null;

	/**
	 * A custom integer index of some value property
	 * @var bigint
	 */
	//var $reference_1  = null;

	function __construct(& $db) {
		parent::__construct('#__flexicontent_fields_item_relations', 'item_id', $db);
	}

	// overloaded check function
	function check()
	{
		return true;
	}


	/**
	 * Method to store a row in the database from the JTable instance properties.
	 *
	 * If a primary key value is set the row with that primary key value will be updated with the instance property values.
	 * If no primary key value is set a new row will be inserted into the database with the properties from the JTable instance.
	 *
	 * @param   boolean  $updateNulls  True to update fields even if they are null.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   11.1
	 */
	public function store($updateNulls = false)
	{
		$result = parent::store($updateNulls); // use inherited method to store data

		$query = 'UPDATE ' . $this->_db->quoteName($this->_tbl)
			. ' SET value_integer = CAST(value AS SIGNED), value_decimal = CAST(value AS DECIMAL(65,15)), value_datetime = CAST(value AS DATETIME) '
			. ' WHERE item_id = ' . (int) $obj->item_id . ' AND field_id = ' . (int) $obj->field_id . ' AND valueorder = ' . (int) $obj->valueorder. ' AND suborder = ' . (int) $obj->suborder;
		$this->_db->setQuery($query);
		$this->_db->execute();

		return $result;
	}
}
