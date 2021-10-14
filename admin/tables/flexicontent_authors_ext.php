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


class flexicontent_authors_ext extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */

	/** @var int */
	var $user_id 	= null;
	/** @var string */
	var $author_basicparams  = null;
	/** @var string */
	var $author_catparams  = null;

	function __construct(& $db) {
		parent::__construct('#__flexicontent_authors_ext', 'user_id', $db);
	}

	// overloaded check function
	function check()
	{
		// Check row exists, since we don't have a separate auto-increment primary key,
		// we have to check and create the row manually, when the user_id row cannot be found in the table
		$query = "SELECT user_id FROM #__flexicontent_authors_ext ".
			" WHERE user_id = ". (int) $this->user_id;
		$row = $this->_db->setQuery($query)->loadResult();

		if (!$row)
		{
			$query = "INSERT INTO #__flexicontent_authors_ext (`user_id`) VALUES ('". (int) $this->user_id ."')";
			
			$this->_db->setQuery($query)->execute();
		}

		return true;
	}

	/**
	 * Overloaded bind function
	 *
	 * @param   array  $array   Named array
	 * @param   mixed  $ignore  An optional array or space separated list of properties
	 *                          to ignore while binding.
	 *
	 * @return  mixed  Null if operation was satisfactory, otherwise returns an error string
	 *
	 * @see     JTable:bind
	 * @since   11.1
	 */
	public function bind($array, $ignore = '')
  {
  	// Convert params from arrays to text, so that they can be stored in the text DB column

  	// ****************************************************
  	// AUTHOR BASIC PARAMETERS (author-specific parameters)
  	// ****************************************************
  	$params = "";
  	if (isset($array['author_basicparams']) && is_array($array['author_basicparams'])) {
	  	foreach($array['author_basicparams'] as $index => $value) {
	  		$params .= "$index=";
	  		if ( is_array($value) ) {
	  			$params .= implode('|', $value);
	  		} else {
	  			$params .= "$value";
	  		}
	 			$params .= "\n";
	  	}
	  	$array['author_basicparams'] = $params;
  	}

  	// ************************************************************************
  	// CATEGORY PARAMETERS (will be saved but used only if override is enabled)
  	// ************************************************************************
  	$params = "";
  	if (isset($array['author_catparams']) && is_array($array['author_catparams'])) {
	  	foreach($array['author_catparams'] as $index => $value) {
	  		$params .= "$index=";
	  		if ( is_array($value) ) {
	  			$params .= implode('|', $value);
	  		} else {
	  			$params .= "$value";
	  		}
	 			$params .= "\n";
	  	}
			$array['author_catparams'] = $params;
  	}

  	return parent::bind( $array, $ignore );
  }


  /**
   * Overloaded load function
   *
   * @param mixed   $keys     An optional primary key value to load the row by, or an array of fields to match
   * @param boolean $reset    True to reset the default values before loading the new row
   *
   * @return boolean          True if successful. False if row not found or on error (internal error state set in that case).
   * @access public
   * @see    JTable:load
   */
  function load ($keys = NULL,$reset = true) {
  	return parent::load( $keys, $reset );
  }


  /**
   * Overloaded save function
   *
   * @param array $source         Array or object of values to bind,check & store in the db
   * @param string $order_filter  Filter for the order updating. See JTable/reorder
   * @param mixed $ignore         Array or space separated list of fields not to bind
   *
   * @return bolean               TRUE if completely successful, FALSE if partially or not succesful.
   * @access public
   * @see    JTable:save
   */
  function save( $source, $order_filter='', $ignore='' )
  {
		if (!$this->bind( $source, $ignore )) {
			return false;
		}
		if (!$this->check()) {
			return false;
		}
		if (!$this->store()) {
			return false;
		}
		if (!$this->checkin()) {
			return false;
		}
		if ($order_filter)
		{
			$filter_value = $this->$order_filter;
			$this->reorder( $order_filter ? (FLEXI_J16GE ? $this->_db->quoteName( $order_filter ) : $this->_db->nameQuote( $order_filter ))	.' = '.$this->_db->Quote( $filter_value ) : '');
		}
		return true;
	}

}
