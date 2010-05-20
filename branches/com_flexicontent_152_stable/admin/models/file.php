<?php
/**
 * @version 1.5 stable $Id: file.php 183 2009-11-18 10:30:48Z vistamedia $
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

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.model');

/**
 * FLEXIcontent Component File Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelFile extends JModel
{
	/**
	 * File data
	 *
	 * @var object
	 */
	var $_file = null;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();

		$array = JRequest::getVar('cid',  0, '', 'array');
		$this->setId((int)$array[0]);
	}

	/**
	 * Method to set the identifier
	 *
	 * @access	public
	 * @param	int file identifier
	 */
	function setId($id)
	{
		// Set file id and wipe data
		$this->_id	    = $id;
		$this->_file	= null;
	}
	
	/**
	 * Overridden get method to get properties from the file
	 *
	 * @access	public
	 * @param	string	$property	The name of the property
	 * @param	mixed	$value		The value of the property to set
	 * @return 	mixed 				The value of the property
	 * @since	1.0
	 */
	function get($property, $default=null)
	{
		if ($this->_loadFile()) {
			if(isset($this->_file->$property)) {
				return $this->_file->$property;
			}
		}
		return $default;
	}

	/**
	 * Method to get file data
	 *
	 * @access	public
	 * @return	array
	 * @since	1.0
	 */
	function &getFile()
	{
		if ($this->_loadFile())
		{

		}
		return $this->_file;
	}


	/**
	 * Method to load file data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _loadFile()
	{
		// Lets load the file if it doesn't already exist
		if (empty($this->_file))
		{
			$query = 'SELECT *'
					. ' FROM #__flexicontent_files'
					. ' WHERE id = '.$this->_id
					;
			$this->_db->setQuery($query);
			$this->_file = $this->_db->loadObject();

			return (boolean) $this->_file;
		}
		return true;
	}
	

	/**
	 * Method to checkin/unlock the file
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function checkin()
	{
		if ($this->_id)
		{
			$tag = & JTable::getInstance('flexicontent_files', '');
			return $tag->checkout($uid, $this->_id);
		}
		return false;
	}

	/**
	 * Method to checkout/lock the file
	 *
	 * @access	public
	 * @param	int	$uid	User ID of the user checking the item out
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function checkout($uid = null)
	{
		if ($this->_id)
		{
			// Make sure we have a user id to checkout the group with
			if (is_null($uid)) {
				$user	=& JFactory::getUser();
				$uid	= $user->get('id');
			}
			// Lets get to it and checkout the thing...
			$file = & JTable::getInstance('flexicontent_files', '');
			return $file->checkout($uid, $this->_id);
		}
		return false;
	}

	/**
	 * Tests if the category is checked out
	 *
	 * @access	public
	 * @param	int	A user id
	 * @return	boolean	True if checked out
	 * @since	1.0
	 */
	function isCheckedOut( $uid=0 )
	{
		if ($this->_loadFile())
		{
			if ($uid) {
				return ($this->_file->checked_out && $this->_file->checked_out != $uid);
			} else {
				return $this->_file->checked_out;
			}
		} elseif ($this->_id < 1) {
			return false;
		} else {
			JError::raiseWarning( 0, 'UNABLE LOAD DATA');
			return false;
		}
	}

	/**
	 * Method to store the file information
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function store($data)
	{
		$file  =& $this->getTable('flexicontent_files', '');

		// bind it to the table
		if (!$file->bind($data)) {
			$this->setError( $this->_db->getErrorMsg() );
			return false;
		}

		// Make sure the data is valid
		if (!$file->check()) {
			$this->setError( $file->getError() );
			return false;
		}

		// Store it in the db
		if (!$file->store()) {
			$this->setError( $this->_db->getErrorMsg() );
			return false;
		}
		
		$this->_file	=& $file;

		return true;
	}
}
?>