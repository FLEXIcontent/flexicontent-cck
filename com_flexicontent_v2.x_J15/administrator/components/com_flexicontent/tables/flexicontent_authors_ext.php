<?php
/**
 * @version 1.0 $Id: flexicontent_authors_ext.php 864 2011-08-28 00:44:02Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

defined('_JEXEC') or die('Restricted access');

/**
 * FLEXIcontent table class
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
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

	function flexicontent_authors_ext(& $db) {
		parent::__construct('#__flexicontent_authors_ext', 'user_id', $db);
	}
	
	// overloaded check function
	function check()
	{
		// Check row exists, since we don't have a separate auto-increment primary key,
		// we have to check and create the row manually, when the user_id row cannot be found in the table
		$query = "SELECT user_id FROM #__flexicontent_authors_ext ".
			" WHERE user_id = ". (int) $this->user_id;
		$this->_db->setQuery($query);
		$row = $this->_db->loadResult();
		if (!$row) {
			$query = "INSERT INTO #__flexicontent_authors_ext (`user_id`) VALUES ('". (int) $this->user_id ."')";
			$this->_db->setQuery($query);
			if ( ! $this->_db->query() ) {
				$this->_error = 'Database error while creating author extended data row for user_id: '. (int) $this->user_id. ' DB ERROR: '.$this->_db->getErrorMsg();
				JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
				return false;
			}
		}
		
		return true;
	}

  /**
   * Overloaded bind function
   *
   * @param array $array  Array or object of values to bind
   * @param mixed $ignore Array or space separated list of fields not to bind
   *
   * @return null|string Success returns null, failure returns an error
   * @access public
   * @see    JTable:bind
   */
  function bind( $array, $ignore = '' )
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
?>