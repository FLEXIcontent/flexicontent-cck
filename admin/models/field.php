<?php
/**
 * @version 1.5 stable $Id: field.php 1640 2013-02-28 14:45:19Z ggppdk $
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
 * FLEXIcontent Component Field Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelField extends JModelAdmin
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
		$this->_field  = null;
	}
	

	/**
	 * Method to get the record identifier
	 *
	 * @access	public
	 */
	function getId() {
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
		if ($this->_loadField())
		{
			if(isset($this->_field->$property)) {
				return $this->_field->$property;
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
	 * Method to load record data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _loadField()
	{
		// Lets load the record if it doesn't already exist
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
	 * Method to initialise the record data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _initField()
	{
		// Lets load the record if it doesn't already exist
		if ( $this->_field===null )
		{
			$field = new stdClass();
			$field->id						= 0;
			$field->field_type		= null;
			$field->name					= 'field' . ($this->_getLastId() + 1);
			$field->label					= null;
			$field->description		= '';
			$field->isfilter			= 0;
			$field->isadvfilter   = 0;
			$field->iscore				= 0;
			$field->issearch			= 1;
			$field->isadvsearch		= 0;
			$field->untranslatable= 0;
			$field->formhidden		= 0;
			$field->valueseditable= 0;
			$field->edithelp			= 2;
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
			$tbl = JTable::getInstance('flexicontent_fields', '');
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
		$tbl = JTable::getInstance('flexicontent_fields', '');
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
	 * Method to store the record
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
		$field  = $this->getTable('flexicontent_fields', '');
		$types  = isset($data['tid']) ? $data['tid'] : array(); // types to which the field is being assigned
		
		// Support for 'dirty' field properties
		if ($data['id']) {
			$field->load($data['id']);
			
			if ($field->issearch==-1 || $field->issearch==2) unset($data['issearch']);  // Already dirty
			else if (@ $data['issearch']==0 && $field->issearch==1) $data['issearch']=-1; // Becomes dirty OFF
			else if (@ $data['issearch']==1 && $field->issearch==0) $data['issearch']=2;  // Becomes dirty ON
			
			if ($field->isadvsearch==-1 || $field->isadvsearch==2) unset($data['isadvsearch']);  // Already dirty
			else if (@ $data['isadvsearch']==0 && $field->isadvsearch==1) $data['isadvsearch']=-1; // Becomes dirty OFF
			else if (@ $data['isadvsearch']==1 && $field->isadvsearch==0) $data['isadvsearch']=2;  // Becomes dirty ON
			
			if ($field->isadvfilter==-1 || $field->isadvfilter==2) unset($data['isadvfilter']);  // Already dirty
			else if (@ $data['isadvfilter']==0 && $field->isadvfilter==1) $data['isadvfilter']=-1; // Becomes dirty OFF
			else if (@ $data['isadvfilter']==1 && $field->isadvfilter==0) $data['isadvfilter']=2;  // Becomes dirty ON
			
			// FORCE dirty OFF, if field is being unpublished -and- is not already normal OFF
			if ( isset($data['published']) && $data['published']==0 && $field->published==1 ) {
				if ($field->issearch!=0) $data['issearch'] = -1;
				if ($field->isadvsearch!=0) $data['isadvsearch'] = -1;
				if ($field->isadvfilter!=0) $data['isadvfilter'] = -1;
			}
		}
		
		// bind it to the table
		if (!$field->bind($data)) {
			$this->setError( $this->_db->getErrorMsg() );
			return false;
		}
		
		// Get field attibutes, for J1.5 is params for J2.5 is attribs
		$attibutes = !FLEXI_J16GE ? $data['params'] : $data['attribs'];

		// Build attibutes INI string
		if (FLEXI_J16GE) {
			// JSON encoding allows to use new lines etc
			// handled by 'flexicontent_types' (extends JTable for flexicontent_types)
			//$field->attribs = json_encode($attibutes);
		} else {
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
		
		// Saving asset in J2.5 is handled by the fields table class
		// ...
		
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
			$types = $this->_db->loadColumn();
		}
		
		// Store field to types relations
		// delete relations which type is not part of the types array anymore
		$query 	= 'DELETE FROM #__flexicontent_fields_type_relations'
			. ' WHERE field_id = '.$field->id
			. (!empty($types) ? ' AND type_id NOT IN (' . implode(', ', $types) . ')' : '')
			;
		$this->_db->setQuery($query);
		$this->_db->execute();
		
		// draw an array of the used types
		$query 	= 'SELECT type_id'
			. ' FROM #__flexicontent_fields_type_relations'
			. ' WHERE field_id = '.$field->id
			;
		$this->_db->setQuery($query);
		$used = $this->_db->loadColumn();
		
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
				$this->_db->execute();
			}
		}
	}


	/**
	 * Method to get types list
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getTypeslist ( $type_ids=false, $check_perms = false, $published=false )
	{
		return flexicontent_html::getTypesList( $type_ids, $check_perms, $published);
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
		$used = $this->_db->loadColumn();

		return $used;
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
		$table	= $this->getTable('flexicontent_fields', '');

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
		else
		{
			$table->name = 'field' . ($this->_getLastId() + 1);
		}

		// Convert to the JObject before adding other data.
		$_prop_arr = $table->getProperties(1);
		$item = JArrayHelper::toObject($_prop_arr, 'JObject');
		if ($pk > 0)
		{
			$item->tid = $this->getTypesselected();
		}

		if (property_exists($item, 'attribs'))
		{
			$registry = new JRegistry($item->attribs);
			$item->attribs = $registry->toArray();
		}

		$field_type = JRequest::getVar('field_type', ($pk ? $table->field_type : 'text'));
		$this->setState('field.field_type', $field_type);

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
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');

		// Initialise variables.
		$field_type	= $this->getState('field.field_type');
		$client		= JApplicationHelper::getClientInfo(0);

		// Try 1.6 format: /plugins/folder/element/element.xml
		$pluginpath = JPATH_PLUGINS.DS.'flexicontent_fields'.DS.$field_type.DS.$field_type.'.xml';
		if (!JFile::exists( $pluginpath )) {
			$pluginpath = JPATH_PLUGINS.DS.'flexicontent_fields'.DS.'core'.DS.'core.xml';
		}
		if (!file_exists($pluginpath)) {
			throw new Exception(JText::sprintf('COM_PLUGINS_ERROR_FILE_NOT_FOUND', $field_type.'.xml'));
			return false;
		}

		// Load the core and/or local language file(s).
		/*	$lang->load('plg_'.$folder.'_'.$element, JPATH_ADMINISTRATOR, null, false, false)
		||	$lang->load('plg_'.$folder.'_'.$element, $client->path.'/plugins/'.$folder.'/'.$element, null, false, false)
		||	$lang->load('plg_'.$folder.'_'.$element, JPATH_ADMINISTRATOR, $lang->getDefault(), false, false)
		||	$lang->load('plg_'.$folder.'_'.$element, $client->path.'/plugins/'.$folder.'/'.$element, $lang->getDefault(), false, false);
		*/
		if (file_exists($pluginpath)) {
			// Get the plugin form.
			if (!$form->loadFile($pluginpath, false, '//config')) {
				throw new Exception(JText::_('JERROR_LOADFILE_FAILED'));
			}
		}

		// Attempt to load the xml file.
		if (!$xml = simplexml_load_file($pluginpath)) {
			throw new Exception(JText::_('JERROR_LOADFILE_FAILED'));
		}

		// Get the help data from the XML file if present.
		$help = $xml->xpath('/extension/help');
		if (!empty($help)) {
			$helpKey = trim((string) $help[0]['key']);
			$helpURL = trim((string) $help[0]['url']);

			$this->helpKey = $helpKey ? $helpKey : $this->helpKey;
			$this->helpURL = $helpURL ? $helpURL : $this->helpURL;
		}

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

		if (!($extension = $app->getUserState('com_flexicontent.edit.'.$this->getName().'.extension'))) {
			$extension = JRequest::getCmd('extension', 'com_flexicontent');
		}
		
		// Get id from user state
		$pk = $this->_id;
		if ( !$pk ) {
			$cid = $app->getUserState('com_flexicontent.edit.'.$this->getName().'.id');
			JArrayHelper::toInteger($cid, array(0));
			$pk = $cid[0];
		}
		if ( !$pk ) {
			$cid = JRequest::getVar( 'cid', array(0), $hash='default', 'array' );
			JArrayHelper::toInteger($cid, array(0));
			$pk = $cid[0];
		}
		$this->setState($this->getName().'.id', $pk);
		
		$this->setState('com_flexicontent.'.$this->getName().'.extension', $extension);
		$parts = explode('.',$extension);
		// extract the component name
		$this->setState('com_flexicontent.'.$this->getName().'.component', $parts[0]);
		// extract the optional section name
		$this->setState('com_flexicontent.'.$this->getName().'.section', (count($parts)>1)?$parts[1]:null);

		// Load the parameters.
		//$params	= JComponentHelper::getParams('com_flexicontent');
		//$this->setState('params', $params);
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
		if($this->_field) {
			return $this->_field->attribs;
		}
		return array();
	}
}
?>
