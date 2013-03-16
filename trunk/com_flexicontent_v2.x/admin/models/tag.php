<?php
/**
 * @version 1.5 stable $Id: tag.php 1577 2012-12-02 15:10:44Z ggppdk $
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
 * FLEXIcontent Component Tag Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelTag extends JModelLegacy
{
	/**
	 * Tag data
	 *
	 * @var object
	 */
	var $_tag = null;

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
	 * @param	int tag identifier
	 */
	function setId($id)
	{
		// Set tag id and wipe data
		$this->_id	    = $id;
		$this->_tag	= null;
	}
	
	/**
	 * Overridden get method to get properties from the tag
	 *
	 * @access	public
	 * @param	string	$property	The name of the property
	 * @param	mixed	$value		The value of the property to set
	 * @return 	mixed 				The value of the property
	 * @since	1.0
	 */
	function get($property, $default=null)
	{
		if ($this->_loadTag()) {
			if(isset($this->_tag->$property)) {
				return $this->_tag->$property;
			}
		}
		return $default;
	}

	/**
	 * Method to get tag data
	 *
	 * @access	public
	 * @return	array
	 * @since	1.0
	 */
	function &getTag()
	{
		if ($this->_loadTag())
		{

		}
		else  $this->_initTag();

		return $this->_tag;
	}


	/**
	 * Method to load tag data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _loadTag()
	{
		// Lets load the tag if it doesn't already exist
		if (empty($this->_tag))
		{
			$query = 'SELECT *'
					. ' FROM #__flexicontent_tags'
					. ' WHERE id = '.$this->_id
					;
			$this->_db->setQuery($query);
			$this->_tag = $this->_db->loadObject();

			return (boolean) $this->_tag;
		}
		return true;
	}

	/**
	 * Method to initialise the tag data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _initTag()
	{
		// Lets load the tag if it doesn't already exist
		if (empty($this->_tag))
		{
			$tag = new stdClass();
			$tag->id					= 0;
			$tag->name					= null;
			$tag->alias					= null;
			$tag->published				= 1;
			$this->_tag				= $tag;
			return (boolean) $this->_tag;
		}
		return true;
	}

	/**
	 * Method to checkin/unlock the tag
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function checkin()
	{
		if ($this->_id)
		{
			$tag = JTable::getInstance('flexicontent_tags', '');
			return $tag->checkout($uid, $this->_id);
		}
		return false;
	}

	/**
	 * Method to checkout/lock the tag
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
				$user	= JFactory::getUser();
				$uid	= $user->get('id');
			}
			// Lets get to it and checkout the thing...
			$tag = JTable::getInstance('flexicontent_tags', '');
			return $tag->checkout($uid, $this->_id);
		}
		return false;
	}

	/**
	 * Tests if the tag is checked out
	 *
	 * @access	public
	 * @param	int	A user id
	 * @return	boolean	True if checked out
	 * @since	1.0
	 */
	function isCheckedOut( $uid=0 )
	{
		if ($this->_loadTag())
		{
			if ($uid) {
				return ($this->_tag->checked_out && $this->_tag->checked_out != $uid);
			} else {
				return $this->_tag->checked_out;
			}
		} elseif ($this->_id < 1) {
			return false;
		} else {
			JError::raiseWarning( 0, 'UNABLE LOAD DATA');
			return false;
		}
	}

	/**
	 * Method to store the tag
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function store($data)
	{
		$tag = $this->getTable('flexicontent_tags', '');

		// bind it to the table
		if (!$tag->bind($data)) {
			$this->setError( $this->_db->getErrorMsg() );
			return false;
		}

		// Make sure the data is valid
		if (!$tag->check()) {
			$this->setError($tag->getError() );
			return false;
		}

		// Store it in the db
		if (!$tag->store()) {
			$this->setError( $this->_db->getErrorMsg() );
			return false;
		}
		
		$this->_tag	=& $tag;

		return true;
	}
	
	function addtag($name){
		
		$obj = new stdClass();
		$obj->name	 	= $name;
		$obj->published	= 1;
		
		if($this->store($obj)) {
			return true;
		}

		return false;
	}

}
?>