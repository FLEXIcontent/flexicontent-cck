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
use Joomla\String\StringHelper;

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
	 * Record name
	 *
	 * @var string
	 */
	var $record_name = 'field';

	/**
	 * Record database table 
	 *
	 * @var string
	 */
	var $records_dbtbl = null;

	/**
	 * Record jtable name
	 *
	 * @var string
	 */
	var $records_jtable = null;

	/**
	 * Record primary key
	 *
	 * @var int
	 */
	var $_id = null;
	
	/**
	 * Record data
	 *
	 * @var object
	 */
	var $_record = null;

	/**
	 * Flag to indicate adding new records with next available ordering (at the end),
	 * this is ignored if this record DB model does not have 'ordering'
	 *
	 * @var boolean
	 */
	var $useLastOrdering = true;

	/**
	 * Plugin group used to trigger events
	 *
	 * @var boolean
	 */
	var $plugins_group = null;

	/**
	 * Various record specific properties
	 *
	 */
	var $field_type = null;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();

		// Initialize using default naming if not already set
		$this->records_dbtbl  = $this->records_dbtbl  ?: 'flexicontent_' . $this->record_name . 's';
		$this->records_jtable = $this->records_jtable ?: 'flexicontent_' . $this->record_name . 's';
		
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
		$this->setId($pk);

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
		$this->_id     = (int) $id;
		$this->_record = null;
		$this->setState($this->getName() . '.id', $this->_id);
	}


	/**
	 * Method to get the record identifier
	 *
	 * @access	public
	 * @return	int record identifier
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
		if ($this->_record || $this->_loadRecord())
		{
			if(isset($this->_record->$property))
			{
				return $this->_record->$property;
			}
		}
		return $default;
	}


	/**
	 * Overridden set method to pass properties on to the record
	 *
	 * @access	public
	 * @param	  string	 $property	 The name of the property
	 * @param	  mixed	   $value		   The value of the property to set
	 * @return	boolean  True on success
	 * @since	1.5
	 */
	function set($property, $value=null)
	{
		if ($this->_record || $this->_loadRecord())
		{
			$this->_record->$property = $value;
			return true;
		}

		return false;
	}


	/**
	 * Set method to pass properties on to the model object
	 *
	 * @access	public
	 * @param	  string	 $property	 The name of the property
	 * @param	  mixed	   $value		   The value of the property to set
	 * @return	void
	 * @since	3.2
	 */
	function setProperty($property, $value=null)
	{
		$this->$property = $value;
	}


	/**
	 * Legacy method to get the record
	 *
	 * @access	public
	 * @return	object
	 * @since	1.0
	 */
	function & getField($pk = null)
	{
		if ($this->_loadRecord($pk))
		{
		}
		else
		{
			$this->_initRecord();
		}

		// Extra steps after loading
		$this->_afterLoad($this->_record);

		return $this->_record;
	}


	/**
	 * Method to load record data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	private function _loadRecord($pk = null)
	{
		// Maybe we were given a name, try to use it if table has such a property
		$name = $pk != (int) $pk ? $pk : null;
		if ($name)
		{
			$table = $this->getTable($this->records_jtable, $_prefix='');
			$name = property_exists($table, 'name') ? $name : null;
		}

		// If PK was provided and it is also not a name, then treat it as a primary key value
		$pk = $pk && !$name ? (int) $pk : (int) $this->_id;

		// Lets load the record if it doesn't already exist
		if ( $this->_record===null )
		{
			$name_quoted = $name ? $this->_db->Quote($name) : null;
			if (!$name_quoted && !$pk)
			{
				$this->_record = false;
			}
			else
			{
				$query = 'SELECT *'
					. ' FROM #__' . $this->records_dbtbl
					. ' WHERE '
					. ( $name_quoted
						? ' name='.$name_quoted
						: ' id=' . (int) $pk
					);
				$this->_db->setQuery($query);
				$this->_record = $this->_db->loadObject();
			}

			if ($this->_record)
			{
				$this->_id = $this->_record->id;
			}
		}

		return (boolean) $this->_record;
	}


	/**
	 * Method to get the last id
	 *
	 * @access	private
	 * @return	int
	 * @since	1.0
	 */
	private function _getLastId()
	{
		$query  = 'SELECT MAX(id)'
			. ' FROM #__' . $this->records_dbtbl;
		$this->_db->setQuery($query);
		$lastid = $this->_db->loadResult();

		return (int) $lastid;
	}


	/**
	 * Method to initialise the record data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	private function _initRecord($record = null)
	{
		// Initialize a given record object
		if ($record) ;

		// Only initialize MEMBER property '_record' if it is not already an object
		else if ( is_object($this->_record) ) return true;

		else
		{
			// Load a JTable object with all db columns as properties, then customize some or all the properites
			$record = $this->getTable($this->records_jtable, $_prefix='');
		}

		$record->id							= 0;
		$record->field_type			= 'text';
		$record->name						= null;  //$this->record_name . ($this->_getLastId() + 1);
		$record->label					= null;
		$record->description		= null;
		$record->isfilter				= 0;
		$record->isadvfilter   	= 0;
		$record->iscore					= 0;
		$record->issearch				= 1;
		$record->isadvsearch		= 0;
		$record->untranslatable	= 0;
		$record->formhidden			= 0;
		$record->valueseditable	= 0;
		$record->edithelp				= 2;
		$record->positions			= array();
		$record->published			= 1;
		$record->attribs				= null;
		$record->access					= 1;
		$record->checked_out		= 0;
		$record->checked_out_time	= '';

		$this->_record = $record;

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
			$tbl = $this->getTable($this->records_jtable, $_prefix='');
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
	function checkout($pk = null)
	{
		// Make sure we have a record id to checkout the record with
		if ( !$pk ) $pk = $this->_id;
		if ( !$pk ) return true;
		
		// Get current user
		$user	= JFactory::getUser();
		$uid	= $user->get('id');
		
		// Lets get table record and checkout the it
		$tbl = $this->getTable($this->records_jtable, $_prefix='');
		if ( $tbl->checkout($uid, $this->_id) ) return true;
		
		// Reaching this points means checkout failed
		$this->setError( JText::_("FLEXI_ALERT_CHECKOUT_FAILED") . ' : ' . $tbl->getError() );
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
		if ($this->_id < 1)  return false;

		if ($this->_loadRecord())
		{
			if ($uid) {
				return ($this->_record->checked_out && $this->_record->checked_out != $uid);
			} else {
				return $this->_record->checked_out;
			}
		}
		else
		{
			JError::raiseWarning( 0, 'UNABLE LOAD DATA');
			return false;
		}
	}


	/**
	 * Method to store the record
	 *
	 * @param   array  $data  The form data.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   1.6
	 */
	function store($data)
	{
		// Initialise variables;
		$dispatcher = JEventDispatcher::getInstance();

		// NOTE: 'data' is typically post['jform'] and it is validated by the caller e.g. the controller
		$record = $this->getTable($this->records_jtable, $_prefix='');
		$pk = !empty($data['id']) ? $data['id'] : (int) $this->getState($this->getName() . '.id');
		$isNew = true;

		// Include the plugins for the on save events.
		if ($this->plugins_group)
		{
			JPluginHelper::importPlugin($this->plugins_group);
		}

		// Load existing data to allow maintaining any not-set properties
		if ($pk > 0)
		{
			$record->load($pk);
			$isNew = false;
		}

		// Extra steps after loading record, and before calling JTable::bind()
		$this->_prepareBind($record, $data);

		// Bind data to the jtable
		if (!$record->bind($data))
		{
			$this->setError($record->getError());
			return false;
		}

		// Put the new records in last position
		if (!$record->id && property_exists($record, 'ordering') && !empty($this->useLastOrdering))
		{
			$record->ordering = $record->getNextOrder();
		}

		// Make sure the data is valid
		if (!$record->check())
		{
			$this->setError($record->getError());
			return false;
		}

		// Trigger the onContentBeforeSave event.
		$result = $dispatcher->trigger($this->event_before_save, array($this->option . '.' . $this->name, &$record, $isNew));
		if (in_array(false, $result, true))
		{
			$this->setError($record->getError());
			return false;
		}

		// Store data in the db
		if (!$record->store())
		{
			$this->setError($record->getError());
			return false;
		}
		
		// Saving asset was handled by the JTable:store() of this CLASS model
		// ...
		
		$this->_record = $record;			 // Get the new / updated record object
		$this->_id     = $record->id;  // Get id of newly created records
		$this->setState($this->getName() . '.id', $record->id);  // Set new id into state

		// Trigger the onContentAfterSave event.
		$dispatcher->trigger($this->event_after_save, array($this->option . '.' . $this->name, &$record, $isNew, $data));

		// Extra steps after loading record, and before calling JTable::bind()
		$this->_afterStore($record, $data);

		return true;
	}


	/**
	 * Method to assign types to a field
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	private function _assignTypesToField($types)
	{
		$field = $this->_record;
		
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
	function getTypesselected($pk = 0)
	{
		$pk = $pk ?: (int) $this->_id;

		if ( ! $pk ) return array();

		$query = 'SELECT DISTINCT type_id '
			. ' FROM #__flexicontent_fields_type_relations '
			. ' WHERE field_id = ' . $pk
			;
		$this->_db->setQuery($query);

		return $this->_db->loadColumn();
	}


	/**
	 * Returns a Table object, always creating it
	 *
	 * @param	type	The table type to instantiate
	 * @param	string	A prefix for the table class name. Optional.
	 * @param	array	Configuration array for model. Optional.
	 * @return	JTable	A database object
	 * @since	1.6
	*/
	public function getTable($type = null, $prefix = '', $config = array())
	{
		$type = $type ?: $this->records_jtable;
		return JTable::getInstance($type, $prefix, $config);
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
		// Get the form.
		$form = $this->loadForm($this->option.'.'.$this->getName(), $this->getName(), array('control' => 'jform', 'load_data' => $loadData));
		if (empty($form))
		{
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
		$data = $app->getUserState($this->option.'.edit.'.$this->getName().'.data', array());

		// Clear form data from session ?
		$app->setUserState('com_flexicontent.edit.'.$this->getName().'.data', false);

		if (empty($data))
		{
			$data = $this->getItem($this->_id);
		}

		$this->preprocessData($this->option.'.'.$this->getName(), $data);
		
		return $data;
	}


	/**
	 * Method to get a record.
	 *
	 * @param	integer  $pk An optional id of the object to get, otherwise the id from the model state is used.
	 *
	 * @return	mixed 	Record data object on success, false on failure.
	 *
	 * @since	1.6
	 */
	public function getItem($pk = null)
	{
		$pk = $pk ? (int) $pk : $this->_id;
		$pk = $pk ? $pk : (int) $this->getState($this->getName().'.id');
		
		static $items = array();
		if ( $pk && isset($items[$pk]) ) return $items[$pk];
		
		// Instatiate the JTable
		$table = $this->getTable($this->records_jtable, '');

		if ($pk > 0)
		{
			// Attempt to load the row.
			$return = $table->load($pk);

			// Check for a table object error.
			if ($return === false && $table->getError())
			{
				$this->setError($table->getError());
				return false;
			}
		}
		else
		{
			// New record set desired default values into the record
			$this->_initRecord($table);
		}

		// Set record
		$this->_id = $table->id;
		$this->_record = $table;

		// Extra steps after loading
		$this->_afterLoad($this->_record);

		// Before any other maniputlations and before other any other data,
		// convert our JTable record to a JObject coping only public properies
		$_prop_arr = $table->getProperties($public_only = true);
		$item = JArrayHelper::toObject($_prop_arr, 'JObject');

		if ($pk) $items[$pk] = $item;
		return $item;
	}


	/**
	 * Method to preprocess the form.
	 *
	 * @param   JForm   $form   A JForm object.
	 * @param   mixed   $data   The data expected for the form.
	 * @param   string  $plugins_group  The name of the plugin group to import and trigger
	 *
	 * @return  void
	 *
	 * @see     JFormField
	 * @since   1.6
	 * @throws  Exception if there is an error in the form event.
	 */
	protected function preprocessForm(JForm $form, $data, $plugins_group = null)
	{
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');

		// Initialise variables.
		$client = JApplicationHelper::getClientInfo(0);

		// Try to load plugin file: /plugins/folder/element/element.xml
		$pluginpath = JPATH_PLUGINS . DS . 'flexicontent_fields' . DS . $this->field_type . DS . $this->field_type.'.xml';
		if (!JFile::exists( $pluginpath ))
		{
			$pluginpath = JPATH_PLUGINS . DS . 'flexicontent_fields' . DS . 'core' . DS . 'core.xml';
		}

		if (!file_exists($pluginpath))
		{
			throw new Exception(JText::sprintf('COM_PLUGINS_ERROR_FILE_NOT_FOUND', $this->field_type.'.xml'));
			return false;
		}

		// Load the core and/or local language file(s).
		/*	$lang->load('plg_'.$folder.'_'.$element, JPATH_ADMINISTRATOR, null, false, false)
		||	$lang->load('plg_'.$folder.'_'.$element, $client->path.'/plugins/'.$folder.'/'.$element, null, false, false)
		||	$lang->load('plg_'.$folder.'_'.$element, JPATH_ADMINISTRATOR, $lang->getDefault(), false, false)
		||	$lang->load('plg_'.$folder.'_'.$element, $client->path.'/plugins/'.$folder.'/'.$element, $lang->getDefault(), false, false);
		*/

		// Get the plugin form.
		if (!file_exists($pluginpath))
		{
			die('Plugin path not found:' . $pluginpath);
		}


		// *** Load form's XML file
		// We will load the form's XML file into a string to be able to manipulate it, before it is loaded by the JForm
		if (1)
		{
			// Read XML file
			$xml_string = str_replace(' type="radio"', ' type="fcradio"', file_get_contents($pluginpath));
			$xml = simplexml_load_string($xml_string);  //simplexml_load_file($pluginpath);
			if (!$xml)
			{
				throw new Exception(JText::_('JERROR_LOADFILE_FAILED'));
			}

			// Load XML file into the form
			$form->load($xml, false, '//config');
		}
		else
		{
			if (!$form->loadFile($pluginpath, false, '//config'))
			{
				throw new Exception(JText::_('JERROR_LOADFILE_FAILED'));
			}
			$xml = $form->getXml();
		}


		// *** Get the help data from the XML file if present.
		$docs = $xml->xpath('/extension/documentation');
		if (!empty($docs))
		{
			$this->helpTitle = trim((string) $docs[0]['title']);
			$this->helpURL   = trim((string) $docs[0]['url']);
			$this->helpModal = (int) $docs[0]['modal'];
		}

		// Trigger the default form events.
		$plugins_group = $plugins_group ?: $this->plugins_group;
		parent::preprocessForm($form, $data, $plugins_group);
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

		// Get id from user state
		$pk = $this->_id;
		$this->setState($this->getName().'.id', $pk);

		if (!($extension = $app->getUserState($this->option.'.edit.'.$this->getName().'.extension')))
		{
			$extension = $jinput->get('extension', 'com_flexicontent', 'cmd');
		}
		$this->setState($this->option.'.'.$this->getName().'.extension', $extension);
		$parts = explode('.',$extension);

		// extract the component name
		$this->setState($this->option.'.'.$this->getName().'.component', $parts[0]);

		// extract the optional section name
		$this->setState($this->option.'.'.$this->getName().'.section', (count($parts)>1)?$parts[1]:null);

		// Load the parameters.
		$params	= JComponentHelper::getParams('com_flexicontent');
		$this->setState('params', $params);
	}


	/**
	 * Method to change the title & alias.
	 *
	 * @param   integer  $parent_id  If applicable, the id of the parent (e.g. assigned category)
	 * @param   string   $alias      The alias / name.
	 * @param   string   $title      The title / label.
	 *
	 * @return  array    Contains the modified title and alias / name.
	 *
	 * @since   1.7
	 */
	protected function generateNewTitle($parent_id, $alias, $title)
	{
		// Alter the title & alias
		$table = $this->getTable();

		while ($table->load(array('name' => $alias)))
		{
			$title = StringHelper::increment($title);
			$alias = StringHelper::increment($alias, 'dash');
		}

		return array($title, $alias);
	}


	/**
	 * Method to check if the user can edit the record
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	3.2.0
	 */
	function canEdit($record=null)
	{
		$record = $record ?: $this->_record;

		return !$record || !$record->id
			? JFactory::getUser()->authorise('flexicontent.createfield', 'com_flexicontent')
			: JFactory::getUser()->authorise('flexicontent.editfield', 'com_flexicontent.field.' . $record->id);
	}


	/**
	 * Method to check if the user can edit the record
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	3.2.0
	 */
	function canEditState($record=null)
	{
		$record = $record ?: $this->_record;

		return JFactory::getUser()->authorise('flexicontent.publishfield', 'com_flexicontent.field.' . $record->id);
	}


	/**
	 * Method to check if the user can edit the record
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	3.2.0
	 */
	function canDelete($record=null)
	{
		$record = $record ?: $this->_record;

		return JFactory::getUser()->authorise('flexicontent.deletefield', 'com_flexicontent.field.' . $record->id);
	}


	/**
	 * Helper method to format a value as array
	 * 
	 * @return object
	 * @since 3.2.0
	 */
	private function formatToArray($value)
	{
		if (is_object($value))
		{
			return (array) $value;
		}
		if (!is_array($value) && !strlen($value))
		{
			return array();
		}
		return is_array($value) ? $value : array($value);
	}


	/**
	 * Helper method to PARTLY bind LAYOUT and other ARRAY properties
	 * so that any fields missing completely from the form can maintain their old values
	 * 
	 * @return object
	 * @since 3.2.0
	 */
	function mergeAttributes(&$item, &$data, $properties, $options)
	{
		if (isset($options['params_fset']) && isset($options['layout_type']))
		{
			// Merge Layout parameters into parameters of the record
			flexicontent_tmpl::mergeLayoutParams($item, $data, $options);

			// Unset layout data since these we handled above
			unset($data[$options['params_fset']]['layouts']);
		}


		// Merge specified array properties by looping through them, thus any
		// fields not present in the form will maintain their existing values
		foreach($properties as $prop)
		{
			if (is_array($data[$prop]))
			{
				// Convert property string to Registry object
				$item->$prop = new JRegistry($item->$prop);
				// Merge the field values
				foreach ($data[$prop] as $k => $v)
				{
					$item->$prop->set($k, $v);
				}
				// Convert property back to string
				$item->$prop = $item->$prop->toString();
			}
		}
	}


	/**
	 * Method to do some record / data preprocessing before call JTable::bind()
	 *
	 * Note. Typically called inside this MODEL 's store()
	 *
	 * @since	3.2.0
	 */
	private function _prepareBind($record, & $data)
	{
		// Handle data of the selected ilayout
		$jinput = JFactory::getApplication()->input;

		// Alter the title for save as copy
		$task = $jinput->get('task', '', 'cmd');
		if ($task == 'save2copy')
		{
			list($label, $name) = $this->generateNewTitle(null, $data['name'], $data['label']);
			$data['label'] = $label;
			$data['name'] = $name;
		}

		// Support for 'dirty' field properties
		if ($data['id'])
		{
			if ($record->issearch==-1 || $record->issearch==2) unset($data['issearch']);  // Already dirty
			else if (@ $data['issearch']==0 && $record->issearch==1) $data['issearch']=-1; // Becomes dirty OFF
			else if (@ $data['issearch']==1 && $record->issearch==0) $data['issearch']=2;  // Becomes dirty ON
			
			if ($record->isadvsearch==-1 || $record->isadvsearch==2) unset($data['isadvsearch']);  // Already dirty
			else if (@ $data['isadvsearch']==0 && $record->isadvsearch==1) $data['isadvsearch']=-1; // Becomes dirty OFF
			else if (@ $data['isadvsearch']==1 && $record->isadvsearch==0) $data['isadvsearch']=2;  // Becomes dirty ON
			
			if ($record->isadvfilter==-1 || $record->isadvfilter==2) unset($data['isadvfilter']);  // Already dirty
			else if (@ $data['isadvfilter']==0 && $record->isadvfilter==1) $data['isadvfilter']=-1; // Becomes dirty OFF
			else if (@ $data['isadvfilter']==1 && $record->isadvfilter==0) $data['isadvfilter']=2;  // Becomes dirty ON
			
			// FORCE dirty OFF, if field is being unpublished -and- is not already normal OFF
			if ( isset($data['published']) && $data['published']==0 && $record->published==1 )
			{
				if ($record->issearch!=0) $data['issearch'] = -1;
				if ($record->isadvsearch!=0) $data['isadvsearch'] = -1;
				if ($record->isadvfilter!=0) $data['isadvfilter'] = -1;
			}
		}
	}


	/**
	 * Method to do some work after record has been stored
	 *
	 * Note. Typically called inside this MODEL 's store()
	 *
	 * @since	3.2.0
	 */
	private function _afterStore($record, & $data)
	{
		// Assign (a) chosen types to custom field or (b) all types if field is core
		$types = ! empty($data['tid'])
			? $data['tid']
			: array();
		$this->_assignTypesToField($types);
	}


	/**
	 * Method to do some work after record has been loaded via JTable::load()
	 *
	 * Note. Typically called inside this MODEL 's store()
	 *
	 * @since	3.2.0
	 */
	private function _afterLoad($record)
	{
		// Convert attributes to a JRegistry object
		if (property_exists($record, 'attribs'))
		{
			$record->attribs = new JRegistry($record->attribs);
		}

		// Convert field positions to an array
		if (!is_array($record->positions))
		{
			$record->positions = explode("\n", $record->positions);
		}

		// Load type assigments (an array of type IDs)
		$record->tid = $this->getTypesselected($record->id);

		// Needed during preprocessForm to load correct XML file
		$this->field_type = JFactory::getApplication()->input->get('field_type', ($record->id ? $record->field_type : 'text'), 'cmd');
	}
}