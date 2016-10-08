<?php
/**
 * @version 1.5 stable $Id: type.php 1933 2014-08-06 15:24:37Z ggppdk $
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

jimport('legacy.model.admin');

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
	 * Type primary key
	 *
	 * @var int
	 */
	var $_id = null;
	
	/**
	 * Type data
	 *
	 * @var object
	 */
	var $_type = null;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();
		
		$jinput = JFactory::getApplication()->input;

		$id = $jinput->get('id', array(0), 'array');
		JArrayHelper::toInteger($id, array(0));
		$pk = (int) $id[0];

		if (!$pk)
		{
			$cid = $jinput->get('cid', array(0), 'array');
			JArrayHelper::toInteger($cid, array(0));
			$pk = (int) $cid[0];
		}
		
		if (!$pk)
		{
			$data = $jinput->get('jform', array('id'=>0), 'array');
			$pk = (int) $data['id'];
		}
		$this->setId((int)$pk);

		$this->populateState();
	}

	/**
	 * Method to set the identifier
	 *
	 * @access	public
	 * @param	int record identifier
	 */
	function setId($id)
	{
		// Set record id and wipe data
		$this->_id     = $id;
		$this->_type   = null;
	}
	

	/**
	 * Method to get the record identifier
	 *
	 * @access	public
	 */
	function getId()
	{
		return $this->_id;
	}
	
	/**
	 * Overridden get method to get properties from the record
	 *
	 * @access	public
	 * @param	string	$property	The name of the property
	 * @param	mixed	$value		The value of the property to set
	 * @return 	mixed 				The value of the property
	 * @since	1.0
	 */
	function get($property, $default=null)
	{
		if ($this->_loadType())
		{
			if(isset($this->_type->$property)) {
				return $this->_type->$property;
			}
		}
		return $default;
	}

	/**
	 * Method to get record data
	 *
	 * @access	public
	 * @return	array
	 * @since	1.0
	 */
	function &getType()
	{
		if ($this->_loadType()) {
			// extra steps after loading
			// ...
		} else {
			$this->_initType();
		}
		
		return $this->_type;
	}


	/**
	 * Method to load record data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _loadType()
	{
		// Lets load the record if it doesn't already exist
		if ( $this->_type===null )
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
	 * Method to get the last id
	 *
	 * @access	private
	 * @return	int
	 * @since	1.0
	 */
	function _getLastId()
	{
		$query  = 'SELECT MAX(id)'
				. ' FROM #__flexicontent_types'
				;
		$this->_db->setQuery($query);
		$lastid = $this->_db->loadResult();
		
		return (int)$lastid;
	}

	/**
	 * Method to initialise the record data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _initType()
	{
		// Lets load the record if it doesn't already exist
		if ( $this->_type===null )
		{
			$type = new stdClass();
			$type->id					= 0;
			$type->name				= null;
			$type->alias			= null;
			$type->published	= 1;
			$type->itemscreatable= 0;
			$type->attribs		= null;
			$type->access			= 0;
			$this->_type			= $type;
			return (boolean) $this->_type;
		}
		return true;
	}

	/**
	 * Method to checkin/unlock the record
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function checkin($pk = NULL)
	{
		if (!$pk) $pk = $this->_id;

		if ($pk)
		{
			$tbl = JTable::getInstance('flexicontent_types', '');
			return $tbl->checkin($pk);
		}
		return false;
	}
	
	
	/**
	 * Method to checkout/lock the record
	 *
	 * @access	public
	 * @param	int	$uid	User ID of the user checking the item out
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function checkout($pk = null)   // UPDATED to match function signature of J1.6+ models
	{
		// Make sure we have a record id to checkout the record with
		if ( !$pk ) $pk = $this->_id;
		if ( !$pk ) return true;
		
		// Get current user
		$user	= JFactory::getUser();
		$uid	= $user->get('id');
		
		// Lets get table record and checkout the it
		$tbl = JTable::getInstance('flexicontent_types', '');
		if ( $tbl->checkout($uid, $this->_id) ) return true;
		
		// Reaching this points means checkout failed
		$this->setError( FLEXI_J16GE ? $tbl->getError() : JText::_("FLEXI_ALERT_CHECKOUT_FAILED") );
		return false;
	}
	
	
	/**
	 * Tests if the record is checked out
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
	 * Method to store the record
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function store($data)
	{
		$app = JFactory::getApplication();

		// NOTE: 'data' is post['jform'] for J2.5 (this is done by the controller or other caller)
		$type = $this->getTable('flexicontent_types', '');
		
		// Load existing data and set new record flag
		$isnew = ! (boolean) $data['id'];
		if ($data['id'])  $type->load($data['id']);
		
		// Retrieve form data these are subject to basic filtering
		$jform = $app->input->post->get('jform', array(), 'array');
		
		// Merge attributes
		$ilayout = $data['attribs']['ilayout'];
		if( !empty($jform['layouts'][$ilayout]) )
		{
			$data['attribs'] = array_merge($data['attribs'], $jform['layouts'][$ilayout]);
		}

		// JSON encoding allows to use new lines etc, handled by 'flexicontent_types' (extends JTable for flexicontent_types)
		//$data['attribs'] = json_encode($data['attribs']);
		// bind it to the table
		if (!$type->bind($data)) {
			$this->setError( $this->_db->getErrorMsg() );
			return false;
		}
		
		// Put the new types in last position, currently this column is missing
		/*if (!$type->id) {
			$type->ordering = $type->getNextOrder();
		}*/

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
		
		// Saving asset in J2.5 is handled by the types table class
		// ...
		
		$this->_type = & $type;
		$this->_id   = $type->id;
		
		// Only insert default relations if the type is new
		if ( $isnew )
			$this->_addCoreFieldRelations();
		
		return true;
	}


	/**
	 * Method to add core field relation to a type
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _addCoreFieldRelations()
	{
		$type = & $this->_type;
		
		// Get core fields
		$core = $this->_getCoreFields();
		
		// Insert core field relations to the DB
		foreach ($core as $fieldid) {
			$obj = new stdClass();
			$obj->field_id  = (int)$fieldid;
			$obj->type_id   = $type->id;
			$this->_db->insertObject('#__flexicontent_fields_type_relations', $obj);
		}
	}


	/**
	 * Method to get core field ids
	 * 
	 * @return array
	 * @since 1.5
	 */
	function _getCoreFields()
	{
		$query = 'SELECT id'
				. ' FROM #__flexicontent_fields'
				. ' WHERE iscore = 1'
				;
		$this->_db->setQuery($query);
		$corefields = $this->_db->loadColumn();

		return $corefields;
	}


	/**
	 * Method to get the row form.
	 *
	 * @param   array    $data      An optional array of data for the form to interogate.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  mixed  A JForm object on success, false on failure
	 *
	 * @since   1.6
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// Initialise variables.
		$app = JFactory::getApplication();

		// Get the form.
		$form = $this->loadForm('com_flexicontent.'.$this->getName(), $this->getName(), array('control' => 'jform', 'load_data' => $loadData));
		if (empty($form)) {
			return false;
		}
		$form->option = $this->option;
		$form->context = $this->getName();

		return $form;
	}


	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  mixed  The data for the form.
	 *
	 * @since   1.6
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$app = JFactory::getApplication();
		$data = $app->getUserState('com_flexicontent.edit.'.$this->getName().'.data', array());

		// Clear form data from session ?
		$app->setUserState('com_flexicontent.edit.'.$this->getName().'.data', false);

		if (empty($data)) {
			$data = $this->getItem($this->_id);
		}

		$this->preprocessData('com_flexicontent.'.$this->getName(), $data);
		
		return $data;
	}


	/**
	 * Method to get a single record.
	 *
	 * @param   integer  $pk  The id of the primary key.
	 *
	 * @return  mixed	Object on success, false on failure.
	 *
	 * @since   1.6
	 */
	public function getItem($pk = null)
	{
		$pk = $pk ? (int) $pk : $this->_id;
		$pk = $pk ? $pk : (int) $this->getState($this->getName().'.id');
		
		static $items = array();
		if ( $pk && isset($items[$pk]) ) return $items[$pk];
		
		// Instatiate the JTable
		$table	= $this->getTable('flexicontent_types', '');

		if ($pk > 0)
		{
			// Attempt to load the row.
			$return = $table->load($pk);

			// Check for a table object error.
			if ($return === false && $table->getError()) {
				$this->setError($table->getError());
				return false;
			}
		}

		// Convert to the JObject before adding other data.
		$_prop_arr = $table->getProperties(1);
		$item = JArrayHelper::toObject($_prop_arr, 'JObject');

		if (property_exists($item, 'attribs'))
		{
			$registry = new JRegistry($item->attribs);
			$item->attribs = $registry->toArray();
		}

		if ($pk) $items[$pk] = $item;
		return $item;
	}


	/**
	 * Override JModelAdmin::preprocessForm to ensure the correct plugin group is loaded.
	 *
	 * @param   JForm   $form   A JForm object.
	 * @param   mixed   $data   The data expected for the form.
	 * @param   string  $group  The name of the plugin group to import (defaults to "content").
	 *
	 * @return  void
	 *
	 * @since   1.6
	 * @throws  Exception if there is an error in the form event.
	 */
	protected function preprocessForm(JForm $form, $data, $group = 'content')
	{
		// Trigger the default form events.
		parent::preprocessForm($form, $data, $plugin_type='_none_');  // by default content plugins are imported, skip them
	}


	/**
	 * Auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @since	1.6
	 */
	protected function populateState()
	{
		$app = JFactory::getApplication('administrator');
		$jinput = $app->input;

		if (!($extension = $app->getUserState('com_flexicontent.edit.'.$this->getName().'.extension')))
		{
			$extension = $jinput->get('extension', 'com_flexicontent', 'cmd');
		}

		// Get id from user state
		$pk = $this->_id;
		$this->setState($this->getName().'.id', $pk);

		$this->setState('com_flexicontent.'.$this->getName().'.extension', $extension);
		$parts = explode('.',$extension);
		// extract the component name
		$this->setState('com_flexicontent.'.$this->getName().'.component', $parts[0]);
		// extract the optional section name
		$this->setState('com_flexicontent.'.$this->getName().'.section', (count($parts)>1)?$parts[1]:null);

		// Load the parameters.
		$params	= JComponentHelper::getParams('com_flexicontent');
		$this->setState('params', $params);
	}
	
	/**
	 * Method to get record attributes
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @since	1.6
	 */
	public function getAttribs()
	{
		if($this->_type) {
			return $this->_type->attribs;
		}
		return array();
	}
}
?>
