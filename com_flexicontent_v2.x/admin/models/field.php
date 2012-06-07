<?php
/**
 * @version 1.5 stable $Id: field.php 1340 2012-06-06 02:30:49Z ggppdk $
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
		$form = $this->loadForm('com_flexicontent.'.$this->getName(), $this->getName(), array('control' => 'jform', 'load_data' => $loadData));
		if (empty($form)) {
			return false;
		}

		return $form;
	}
	
	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return	mixed	The data for the form.
	 * @since	1.6
	 */
	protected function loadFormData() {
		// Check the session for previously entered form data.
		$data = JFactory::getApplication()->getUserState('com_flexicontent.edit.'.$this->getName().'.data', array());

		if (empty($data)) {
			$data = $this->getItem();
		}

		return $data;
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
		static $item;
		if(!$item) {
			// Initialise variables.
			$pk		= (!empty($pk)) ? $pk : (int) $this->getState($this->getName().'.id');
			$table	= $this->getTable('flexicontent_fields', '');

			if ($pk > 0) {
				// Attempt to load the row.
				$return = $table->load($pk);

				// Check for a table object error.
				if ($return === false && $table->getError()) {
					$this->setError($table->getError());
					return false;
				}
			} else {
				$table->name					= 'field' . ($this->_getLastId() + 1);
			}

			// Convert to the JObject before adding other data.
			$item = JArrayHelper::toObject($table->getProperties(1), 'JObject');
			if ($pk > 0) {
				$item->tid = $this->getTypesselected();
			}

			if (property_exists($item, 'attribs')) {
				$registry = new JRegistry;
				$registry->loadJSON($item->attribs);
				$item->attribs = $registry->toArray();
			}
			$field_type = $pk ? $table->field_type : JRequest::getVar('field_type', 'text');
			$this->setState('field.field_type', $field_type);
		}
		return $item;
	}
	
	/**
	 * @param	object	A form object.
	 * @param	mixed	The data expected for the form.
	 * @return	mixed	True if successful.
	 * @throws	Exception if there is an error in the form event.
	 * @since	1.6
	 */
	protected function preprocessForm($form, $data) {
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
		parent::preprocessForm($form, $data);
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
			$pk = (int)@$cid[0];
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
	 * Method to get field attributes
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @since	1.6
	 */
	public function getAttribs() {
		if($this->_field) {
			return $this->_field->attribs;
		}
		return array();
	}
}
?>
