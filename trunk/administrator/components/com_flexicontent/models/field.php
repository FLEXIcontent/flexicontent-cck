<?php
/**
 * @version 1.5 stable $Id$
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
 * FLEXIcontent Component Field Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelField extends JModel
{
	/**
	 * Field primary key
	 *
	 * @var int
	 */
	var $_id = null;
	
	/**
	 * Field data
	 *
	 * @var object
	 */
	var $_field = null;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();

		$array = JRequest::getVar('cid',  array(0), '', 'array');
		$array = is_array($array) ? $array : array($array);
		$id = $array[0];
		if(!$id) {
			$post = JRequest::get( 'post' );
			$data = FLEXI_J16GE ? @$post['jform'] : $post;
			$id = @$data['id'];
		}
		$this->setId((int)$id);
	}

	/**
	 * Method to set the identifier
	 *
	 * @access	public
	 * @param	int field identifier
	 */
	function setId($id)
	{
		// Set field id and wipe data
		$this->_id     = $id;
		$this->_field  = null;
	}
	

	/**
	 * Method to get the field identifier
	 *
	 * @access	public
	 */
	function getId() {
		return $this->_id;
	}
	
	/**
	 * Overridden get method to get properties from the field
	 *
	 * @access	public
	 * @param	string	$property	The name of the property
	 * @param	mixed	$value		The value of the property to set
	 * @return 	mixed 				The value of the property
	 * @since	1.0
	 */
	function get($property, $default=null)
	{
		if ($this->_loadField())
		{
			if(isset($this->_field->$property)) {
				return $this->_field->$property;
			}
		}
		return $default;
	}

	/**
	 * Method to get field data
	 *
	 * @access	public
	 * @return	array
	 * @since	1.0
	 */
	function &getField()
	{
		if ($this->_loadField()) {
			// extra steps after loading
			$this->_field->positions = explode("\n", $this->_field->positions);
		} else {
			$this->_initField();
		}
		
		return $this->_field;
	}


	/**
	 * Method to load field data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _loadField()
	{
		// Lets load the field if it doesn't already exist
		if ( $this->_field===null )
		{
			$query = 'SELECT *'
					. ' FROM #__flexicontent_fields'
					. ' WHERE id = '.$this->_id
					;
			$this->_db->setQuery($query);
			$this->_field = $this->_db->loadObject();

			return (boolean) $this->_field;
		}
		return true;
	}

	/**
	 * Method to get the last id
	 *
	 * @access	private
	 * @return	int
	 * @since	1.0
	 */
	function _getLastId()
	{
		$query  = 'SELECT MAX(id)'
				. ' FROM #__flexicontent_fields'
				;
		$this->_db->setQuery($query);
		$lastid = $this->_db->loadResult();
		
		return (int)$lastid;
	}

	/**
	 * Method to initialise the field data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _initField()
	{
		// Lets load the field if it doesn't already exist
		if ( $this->_field===null )
		{
			$field = new stdClass();
			$field->id						= 0;
			$field->field_type		= null;
			$field->name					= 'field' . ($this->_getLastId() + 1);
			$field->label					= null;
			$field->description		= '';
			$field->isfilter			= 0;
			$field->iscore				= 0;
			$field->issearch			= 1;
			$field->isadvsearch		= 0;
			$field->untranslatable= 0;
			$field->positions			= array();
			$field->published			= 1;
			$field->attribs				= null;
			$field->access				= 0;
			$this->_field					= $field;
			return (boolean) $this->_field;
		}
		return true;
	}

	/**
	 * Method to checkin/unlock the field
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function checkin()
	{
		if ($this->_id)
		{
			$field = & JTable::getInstance('flexicontent_fields', '');
			$user = &JFactory::getUser();
			return $field->checkout($user->get('id'), $this->_id);
		}
		return false;
	}

	/**
	 * Method to checkout/lock the field
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
			$field = & JTable::getInstance('flexicontent_fields', '');
			return $field->checkout($uid, $this->_id);
		}
		return false;
	}

	/**
	 * Tests if the field is checked out
	 *
	 * @access	public
	 * @param	int	A user id
	 * @return	boolean	True if checked out
	 * @since	1.0
	 */
	function isCheckedOut( $uid=0 )
	{
		if ($this->_loadField())
		{
			if ($uid) {
				return ($this->_field->checked_out && $this->_field->checked_out != $uid);
			} else {
				return $this->_field->checked_out;
			}
		} elseif ($this->_id < 1) {
			return false;
		} else {
			JError::raiseWarning( 0, 'UNABLE LOAD DATA');
			return false;
		}
	}

	/**
	 * Method to store the field
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function store($data)
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );

		// NOTE: 'data' is post['jform'] for J2.5 (this is done by the controller or other caller)
		$field  	=& $this->getTable('flexicontent_fields', '');
		$types		= $data['tid'];
		
		// bind it to the table
		if (!$field->bind($data)) {
			$this->setError( $this->_db->getErrorMsg() );
			return false;
		}
		
		// Get field attibutes, for J1.5 is params for J2.5 is attribs
		$attibutes = !FLEXI_J16GE ? $data['params'] : $data['attribs'];

		// Build attibutes INI string
		if (is_array($attibutes))
		{
			$txt = array ();
			foreach ($attibutes as $k => $v) {
				if (is_array($v)) {
					$v = implode('|', $v);
				}
				$txt[] = "$k=$v";
			}
			$field->attribs = implode("\n", $txt);
		}

		// Put the new fields in last position
		if (!$field->id) {
			$field->ordering = $field->getNextOrder();
		}

		// Make sure the data is valid
		if (!$field->check()) {
			$this->setError($field->getError() );
			return false;
		}

		// Store it in the db
		if (!$field->store()) {
			$this->setError( $this->_db->getErrorMsg() );
			return false;
		}
		
		if (FLEXI_ACCESS) {
			FAccess::saveaccess( $field, 'field' );
		} else if (FLEXI_J16GE) {
			// saving asset in J2.5 is handled by the fields table class
		}
		
		$this->_field = & $field;
		$this->_id    = $field->id;
		
		// Assign (a) chosen types to custom field or (b) all types if field is core
		$this->_assignTypesToField($types);
		
		return true;
	}

	/**
	 * Method to assign types to a field
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _assignTypesToField($types)
	{
		$field = & $this->_field;
		
		// Override 'types' for core fields, since the core field must be assigned to all types
		if ($field->iscore == 1)
		{
			$query 	= 'SELECT id'
				. ' FROM #__flexicontent_types'
				;
			$this->_db->setQuery($query);
			$types = $this->_db->loadResultArray();
		}
		
		// Store field to types relations
		// delete relations which type is not part of the types array anymore
		$query 	= 'DELETE FROM #__flexicontent_fields_type_relations'
			. ' WHERE field_id = '.$field->id
			. ($types ? ' AND type_id NOT IN (' . implode(', ', $types) . ')' : '')
			;
		$this->_db->setQuery($query);
		$this->_db->query();
		
		// draw an array of the used types
		$query 	= 'SELECT type_id'
			. ' FROM #__flexicontent_fields_type_relations'
			. ' WHERE field_id = '.$field->id
			;
		$this->_db->setQuery($query);
		$used = $this->_db->loadResultArray();
		
		foreach($types as $type)
		{
			// insert only the new records
			if (!in_array($type, $used)) {
				//get last position of each field in each type;
				$query 	= 'SELECT max(ordering) as ordering'
					. ' FROM #__flexicontent_fields_type_relations'
					. ' WHERE type_id = ' . $type
					;
				$this->_db->setQuery($query);
				$ordering = $this->_db->loadResult()+1;

				$query 	= 'INSERT INTO #__flexicontent_fields_type_relations (`field_id`, `type_id`, `ordering`)'
					.' VALUES(' . $field->id . ',' . $type . ', ' . $ordering . ')'
					;
				$this->_db->setQuery($query);
				$this->_db->query();
			}
		}
	}

	/**
	 * Method to get types list when performing an edit action
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getTypeslist()
	{
		$query = 'SELECT id, name'
			. ' FROM #__flexicontent_types'
			. ' WHERE published = 1'
			. ' ORDER BY name ASC'
			;
		$this->_db->setQuery($query);
		$types = $this->_db->loadObjectList();
		return $types;	
	}
	
	/**
	 * Method to get used types when performing an edit action
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getTypesselected()
	{
		$query = 'SELECT DISTINCT type_id FROM #__flexicontent_fields_type_relations WHERE field_id = ' . (int)$this->_id;
		$this->_db->setQuery($query);
		$used = $this->_db->loadResultArray();
		return $used;
	}
	
}

?>