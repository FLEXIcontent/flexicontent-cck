<?php
/**
 * @version 1.5 stable $Id: type.php 179 2010-03-24 11:31:05Z enjoyman $
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

jimport('joomla.application.component.modeladmin');

/**
 * FLEXIcontent Component Type Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelType extends JModelAdmin
{
	/**
	 * Type data
	 *
	 * @var object
	 */
	var $_id = null;
	
	var $_item = null;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();

		$array = JRequest::getVar('cid',  array(0), '', 'array');
		$array = is_array($array)?$array:array($array);
		$id = $array[0];
		if(!$id) {
			$data = JRequest::get( 'id', 0 );
			$id = @$data['jform']['id'];
		}
		$this->setId((int)$id);
	}

	/**
	 * Method to set the identifier
	 *
	 * @access	public
	 * @param	int type identifier
	 */
	function setId($id)
	{
		// Set type id and wipe data
		$this->_id	    = $id;
	}
	
	function getId() {
		return $this->_id;
	}
	
	/**
	 * Method to get the row form.
	 *
	 * @param	array	$data		Data for the form.
	 * @param	boolean	$loadData	True if the form is to load its own data (default case), false if not.
	 * @return	mixed	A JForm object on success, false on failure
	 * @since	1.6
	 */
	public function getForm($data = array(), $loadData = true) {
		// Initialise variables.
		$app = JFactory::getApplication();

		// Get the form.
		$form = $this->loadForm('com_flexicontent.type', 'type', array('control' => 'jform', 'load_data' => $loadData));
		if (empty($form)) {
			return false;
		}

		return $form;
	}
	
	/**
	 * Method to get a single record.
	 *
	 * @param	integer	$pk	The id of the primary key.
	 *
	 * @return	mixed	Object on success, false on failure.
	 * @since	1.6
	 */
	public function getItem($pk = null) {
		// Initialise variables.
		$pk		= (!empty($pk)) ? $pk : (int) $this->getState($this->getName().'.id');
		$table	= $this->getTable('flexicontent_types', '');

		if ($pk > 0) {
			// Attempt to load the row.
			$return = $table->load($pk);

			// Check for a table object error.
			if ($return === false && $table->getError()) {
				$this->setError($table->getError());
				return false;
			}
		}

		// Convert to the JObject before adding other data.
		$item = JArrayHelper::toObject($table->getProperties(1), 'JObject');

		if (property_exists($item, 'attribs')) {
			$registry = new JRegistry;
			$registry->loadJSON($item->attribs);
			$item->attribs = $registry->toArray();
		}
		$this->_item = &$item;
		return $item;
	}
	
	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return	mixed	The data for the form.
	 * @since	1.6
	 */
	public function loadFormData() {
		// Check the session for previously entered form data.
		$data = JFactory::getApplication()->getUserState('com_flexicontent.edit.type.data', NULL);

		if (empty($data)) {
			$data = $this->getItem();
		}

		return $data;
	}

	/**
	 * Method to load type data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _loadType()
	{
		// Lets load the type if it doesn't already exist
		if (empty($this->_type))
		{
			$query = 'SELECT *'
					. ' FROM #__flexicontent_types'
					. ' WHERE id = '.$this->_id
					;
			$this->_db->setQuery($query);
			$this->_type = $this->_db->loadObject();

			return (boolean) $this->_type;
		}
		return true;
	}

	/**
	 * Method to initialise the type data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _initType()
	{
		// Lets load the type if it doesn't already exist
		if (empty($this->_type))
		{
			$type = new stdClass();
			$type->id					= 0;
			$type->name					= null;
			$type->alias				= null;
			$type->published			= 1;
			$type->attribs				= null;
			$this->_type				= $type;
			return (boolean) $this->_type;
		}
		return true;
	}

	/**
	 * Method to checkin/unlock the type
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function checkin()
	{
		if ($this->_id)
		{
			$type = & JTable::getInstance('flexicontent_types', '');
			$user = &JFactory::getUser();
			return $type->checkout($user->get('id'), $this->_id);
		}
		return false;
	}

	/**
	 * Method to checkout/lock the type
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
			$type = & JTable::getInstance('flexicontent_types', '');
			return $type->checkout($uid, $this->_id);
		}
		return false;
	}

	/**
	 * Tests if the type is checked out
	 *
	 * @access	public
	 * @param	int	A user id
	 * @return	boolean	True if checked out
	 * @since	1.0
	 */
	function isCheckedOut( $uid=0 )
	{
		if ($this->_loadType())
		{
			if ($uid) {
				return ($this->_type->checked_out && $this->_type->checked_out != $uid);
			} else {
				return $this->_type->checked_out;
			}
		} elseif ($this->_id < 1) {
			return false;
		} else {
			JError::raiseWarning( 0, 'UNABLE LOAD DATA');
			return false;
		}
	}

	/**
	 * Method to store the type
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function store($data)
	{
		$type  =& $this->getTable('flexicontent_types', '');

		// bind it to the table
		if (!$type->bind($data['jform'])) {
			$this->setError( $this->_db->getErrorMsg() );
			return false;
		}

		$attribs		= $data['jform']['attribs'];
		// Build parameter INI string
		if (is_array($attribs))
		{
			$txt = array ();
			foreach ($attribs as $k => $v) {
				if (is_array($v)) {
					$v = implode('|', $v);
				}
				$txt[] = "$k=$v";
			}
			$type->attribs = implode("\n", $txt);
		}

		// Make sure the data is valid
		if (!$type->check()) {
			$this->setError($type->getError() );
			return false;
		}

		// Store it in the db
		if (!$type->store()) {
			$this->setError( $this->_db->getErrorMsg() );
			return false;
		}
		$insertid = (int)$this->_db->insertid();
		$this->_type	=& $type;
		
		// only insert default relations if the type is new
		if ($insertid) {
			$this->setId($insertid);
			$core = $this->_getCoreFields();
			foreach ($core as $fieldid) {
				$obj = new stdClass();
				$obj->field_id	 	= (int)$fieldid;
				$obj->type_id		= $type->id;

				$this->_db->insertObject('#__flexicontent_fields_type_relations', $obj);
			}
		}

		return true;
	}
	
	function addtype($name) {
		
		$obj = new stdClass();
		$obj->name	 	= $name;
		$obj->published	= 1;
		
		$this->store($obj);

	//	$this->_db->insertObject('#__flexicontent_types', $obj);
		
		return true;
	}

	function _getCoreFields(){
		
		$query = 'SELECT id'
				. ' FROM #__flexicontent_fields'
				. ' WHERE iscore = 1'
				;
		$this->_db->setQuery($query);
		$corefields = $this->_db->loadResultArray();
		
		return $corefields;
	}
	/**
	 * Auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @since	1.6
	 */
	protected function populateState() {
		$app = JFactory::getApplication('administrator');

		if (!($extension = $app->getUserState('com_flexicontent.edit.'.$this->getName().'.extension'))) {
			$extension = JRequest::getCmd('extension', 'com_flexicontent');
		}
		// Load the User state.
		if (!($pk = (int) $app->getUserState('com_flexicontent.edit.'.$this->getName().'.id'))) {
			$cid = JRequest::getVar('cid', array(0));
			$pk = (int)$cid[0];
		}
		$this->setState($this->getName().'.id', $pk);


		$this->setState('com_flexicontent.type.extension', $extension);
		$parts = explode('.',$extension);
		// extract the component name
		$this->setState('com_flexicontent.type.component', $parts[0]);
		// extract the optional section name
		$this->setState('com_flexicontent.type.section', (count($parts)>1)?$parts[1]:null);

		// Load the parameters.
		//$params	= JComponentHelper::getParams('com_flexicontent');
		//$this->setState('params', $params);
	}
	public function getAttribs() {
		if($this->_item) {
			return $this->_item->attribs;
		}
		return array();
	}
}
?>
