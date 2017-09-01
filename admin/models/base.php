<?php
/**
 * @version 1.5 stable $Id: item.php 1244 2012-04-12 05:07:35Z ggppdk $
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
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('legacy.model.admin');
use Joomla\String\StringHelper;

/**
 * FLEXIcontent Component BASE Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
abstract class FCModelAdmin extends JModelAdmin
{
	/**
	 * Record name
	 *
	 * @var string
	 */
	var $record_name = null;

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
	 * Events context to use during model FORM events triggering
	 *
	 * @var object
	 */
	var $events_context = null;

	/**
	 * Flag to indicate adding new records with next available ordering (at the end),
	 * this is ignored if this record DB model does not have 'ordering'
	 *
	 * @var boolean
	 */
	var $useLastOrdering = false;

	/**
	 * Plugin group used to trigger events
	 *
	 * @var boolean
	 */
	var $plugins_group = null;

	/**
	 * Records real extension
	 *
	 * @var string
	 */
	var $extension_proxy = null;

	/**
	 * Use language associations
	 *
	 * @var string
	 */
	var $associations_context = false;

	/**
	 * Use language associations
	 *
	 * @var string
	 */
	var $_messages= array();

	/**
	 * Various record specific properties
	 *
	 */
	// ...

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
		$pk = null;

		if ($pk === null)
		{
			$id = $jinput->get('id', array(), 'array');
			if (count($id))
			{
				JArrayHelper::toInteger($id);
				$pk = (int) $id[0];
			}
		}

		if ($pk === null)
		{
			$cid = $jinput->get('cid', array(), 'array');
			if (count($cid))
			{
				JArrayHelper::toInteger($cid);
				$pk = (int) $cid[0];
			}
		}

		if ($pk === null)
		{
			$data = $jinput->get('jform', array(), 'array');
			$pk = isset($data['id']) ? (int) $data['id'] : 0;
		}

		$pk = $pk ?: 0;
		$this->setId($pk);
	}


	/**
	 * Method to set the identifier
	 *
	 * @access	public
	 * @param	int record identifier
	 */
	function setId($id)
	{
		// Set record id and wipe data, if setting a different ID
		if ($this->_id != $id)
		{
			$this->_id     = (int) $id;
			$this->_record = null;
			$this->setState($this->getName() . '.id', $this->_id);
		}
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
			if (isset($this->_record->$property))
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
	function & getRecord($pk = null)
	{
		if ($this->_loadRecord($pk))
		{
		}
		else
		{
			$this->_initRecord();
		}

		return $this->_record;
	}


	/**
	 * Method to load record data
	 *
	 * @access	protected
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	protected function _loadRecord($pk = null)
	{
		// Maybe we were given a name, try to use it if table has such a property
		$name = $pk != (int) $pk ? $pk : null;
		if ($name)
		{
			// Check 'name' columns and then check 'alias' column exists, if none then clear $name
			$table = $this->getTable();
			$name_property = property_exists($table, 'name')
				? 'name'
				: (property_exists($table, 'alias')
					? 'alias'
					: null);
			$name = $name_property ? $name : null;
		}

		// Lets load the record if it doesn't already exist
		if ( $this->_record===null || ($name && $this->_record->$name_property != $name) || (!$name && $this->_record->id != $pk) )
		{
			// If PK was provided and it is also not a name, then treat it as a primary key value
			$pk = $pk && !$name ? (int) $pk : (int) $this->_id;

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

			// Extra steps after loading
			$this->_afterLoad($this->_record);
		}

		return (boolean) $this->_record;
	}


	/**
	 * Method to get the last id
	 *
	 * @access	protected
	 * @return	int
	 * @since	1.0
	 */
	protected function _getLastId()
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
	 * @access	protected
	 * @param	object    $record   The record being initialized
	 * @param	boolean   $uid      If true then only a new record will be initialized without running the _afterLoad() method
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	protected function _initRecord(&$record = null, $initOnly = false)
	{
		// Initialize a given record object
		if ($record) ;

		// Only initialize MEMBER property '_record' if it is not already an object
		else if ( is_object($this->_record) )
		{
			$record = $this->_record;
			return true;
		}

		else
		{
			// Load a JTable object with all db columns as properties, then customize some or all the properites
			$record = $this->getTable();
		}

		// Set some new record specific properties, note most properties already have proper values
		// Either the DB default values (set by getTable() method) or the values set by _afterLoad() method
		// ...
		$this->_record = $record;

		// Extra steps after loading
		if ( !$initOnly )
		{
			$this->_afterLoad($this->_record);
		}

		return true;
	}


	/**
	 * Method to checkin/unlock the record
	 *
	 * @access	public
	 * @param	  int    $pk   The record id to checkin (unlock)
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function checkin($pk = NULL)
	{
		if (!$pk) $pk = $this->_id;

		if ($pk)
		{
			$tbl = $this->getTable();
			return $tbl->checkin($pk);
		}
		return false;
	}


	/**
	 * Method to checkout/lock the record
	 *
	 * @access	public
	 * @param	  int    $pk   The record id to checkin (unlock)
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
		$tbl = $this->getTable();
		if ( $tbl->checkout($uid, $this->_id) ) return true;
		
		// Reaching this points means checkout failed
		$this->setError( JText::_("FLEXI_ALERT_CHECKOUT_FAILED") . ' : ' . $tbl->getError() );
		return false;
	}


	/**
	 * Tests if the record is checked out
	 *
	 * @access	public
	 *
	 * @param	int	A user id
	 *
	 * @return	boolean	True if checked out
	 *
	 * @since   3.2.0
	 */
	function isCheckedOut( $uid=0 )
	{
		if ($this->_id < 1)  return false;

		if ($this->_record || $this->_loadRecord())
		{
			if ($uid) {
				return ($this->_record->checked_out && $this->_record->checked_out != $uid);
			} else {
				return $this->_record->checked_out;
			}
		}
		else
		{
			$this->setError('Unable to Load Data');
			return false;
		}
	}

	/**
	 * Legacy method to store the record, use save() instead
	 *
	 * @param   array  $data  The form data.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   3.2.0
	 */
	function store($data)
	{
		return $this->save($data);
	}


	/**
	 * Method to save the record
	 *
	 * @param   array  $data  The form data.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   3.2.0
	 */
	function save($data)
	{
		// Initialise variables
		$dispatcher = JEventDispatcher::getInstance();

		// Note that 'data' is typically post['jform'] and it is validated by the caller e.g. the controller
		if (is_object($data))
		{
			$data = (array) $data;
		}

		// Get record 's primary key and set the 'isNew' Flag
		$pk = !empty($data['id'])
			? (int) $data['id']
			: (int) $this->getState($this->getName() . '.id');
		$isNew = true;

		// Include the plugins for the on save events.
		if ($this->plugins_group)
		{
			JPluginHelper::importPlugin($this->plugins_group);
		}

		// Get a JTable object
		$record = $this->getTable();

		// Load data of existing record to allow maintaining any not-set properties
		if ($pk > 0)
		{
			$record->load($pk);
			$isNew = false;
		}
		else
		{
			$record->reset();
		}

		// Extra steps after loading record, and before calling JTable::bind()
		if ($this->_prepareBind($record, $data) === false)
		{
			// Just return, the error was set already
			return false;
		}

		// Bind data to the jtable
		if (!$record->bind($data))
		{
			$this->setError($record->getError());
			return false;
		}

		// Make sure the data is valid
		if (!$record->check())
		{
			$this->setError($record->getError());
			return false;
		}

		// Trigger the before save event (typically: onContentBeforeSave)
		$result = $dispatcher->trigger($this->event_before_save, array($this->option . '.' . $this->name, &$record, $isNew, $data));
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

		// Update language Associations
		$this->saveAssociations($record, $data);

		// Trigger the after save event (typically: onContentBeforeSave)
		$dispatcher->trigger($this->event_after_save, array($this->option . '.' . $this->name, &$record, $isNew, $data));

		// Extra steps after loading record, and before calling JTable::bind()
		$this->_afterStore($record, $data);

		// Clear the cache
		$this->cleanCache();

		return true;
	}


	/**
	 * Custom clean the cache
	 *
	 * @param   string   $group      Clean cache only in the given group
	 * @param   integer  $client_id  Site Cache (0) / Admin Cache (1) or both Caches (-1)
	 *
	 * @return  void
	 *
	 * @since   3.2.0
	 */
	protected function cleanCache($group = NULL, $client_id = -1)
	{
		if ($client_id === -1)
		{
			parent::cleanCache($group ?: 'com_flexicontent', 0);
			parent::cleanCache($group ?: 'com_flexicontent', 1);
		}
		else
		{
			parent::cleanCache($group ?: 'com_flexicontent', $client_id);
		}
	}
	

	/**
	 * Returns a Table object, always creating it
	 *
	 * @param	type	The table type to instantiate
	 * @param	string	A prefix for the table class name. Optional.
	 * @param	array	Configuration array for model. Optional.
	 *
	 * @return	JTable	A database object
	 *
	 * @since   3.2.0
	*/
	public function getTable($type = null, $prefix = '', $config = array())
	{
		$type = $type ?: $this->records_jtable;
		return JTable::getInstance($type, $prefix, $config);
	}


	/**
	 * Method to get the record form.
	 *
	 * @param   array    $data      Data for the form.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  JForm|boolean  A JForm object on success, false on failure
	 *
	 * @since   1.6
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// Get the form.
		$events_context = $this->events_context ?: $this->option.'.'.$this->getName();
		$form = $this->loadForm($events_context, $this->getName(), array('control' => 'jform', 'load_data' => $loadData));

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

		// Clear form data from session
		$app->setUserState($this->option.'.edit.'.$this->getName().'.data', false);

		if (empty($data))
		{
			$data = $this->getItem();
		}
		else
		{
		}

		$events_context = $this->events_context ?: $this->option.'.'.$this->getName();
		$this->preprocessData($events_context, $data);

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
		$table = $this->getTable();

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

		// Before any other manipulations and before other any other data is added,
		// convert our JTable record to a JObject coping only public properies
		$_prop_arr = $table->getProperties($public_only = true);
		$item = JArrayHelper::toObject($_prop_arr, 'JObject');

		// Add to cache if not a new record
		if ($pk)
		{
			$items[$pk] = $this->_record;
		}
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

		// Set parent_id into state (later ignored if not this record type has no such property)
		$parentId = $app->input->getInt('parent_id');
		$this->setState($this->getName().'.parent_id', $parentId);

		// Load the User state.
		$pk = $this->_id ?: $app->input->getInt('id');
		$this->_id = $pk;
		$this->setState($this->getName() . '.id', $pk);

		// Extract the extension name
		$extension = $app->input->getCmd('extension', $this->extension_proxy ?: 'com_flexicontent');
		$this->setState($this->getName().'.extension', $extension);
		$parts = explode('.', $extension);

		// Extract the component name
		$this->setState($this->getName().'.component', $parts[0]);

		// Extract the optional section name
		$this->setState($this->getName().'.section', (count($parts) > 1) ? $parts[1] : null);

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

		while ($table->load(array('alias' => $alias, 'parent_id' => $parent_id)))
		{
			$title = StringHelper::increment($title);
			$alias = StringHelper::increment($alias, 'dash');
		}

		return array($title, $alias);
	}


	/**
	 * Method to save language associations
	 *
	 * @return  boolean True if successful
	 */
	function saveAssociations(&$record, &$data)
	{
		if( !$this->associations_context ) return true;

		$record = $record ? $record: $this->_record;
		return flexicontent_db::saveAssociations($record, $data, $this->associations_context);
	}


	/**
	 * Method to determine if J3.1+ associations should be used
	 *
	 * @return  boolean True if using J3 associations; false otherwise.
	 */
	public function useAssociations()
	{
		return flexicontent_db::useAssociations();
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

		return parent::canEdit($record);
	}


	/**
	 * Method to check if the user can edit record 's state
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	3.2.0
	 */
	function canEditState($record=null)
	{
		$record = $record ?: $this->_record;

		return parent::canEditState($record);
	}


	/**
	 * Method to check if the user can delete the record
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	3.2.0
	 */
	function canDelete($record=null)
	{
		$record = $record ?: $this->_record;

		return parent::canDelete($record);
	}


	/**
	 * Helper method to format a value as array
	 * 
	 * @return object
	 * @since 3.2.0
	 */
	function formatToArray($value)
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
		//***
		//*** Filter layout parameters if the were given, and merge them into existing layout parameters (in DB)
		//***

		if (isset($options['params_fset']) && isset($options['layout_type']))
		{
			// Merge Layout parameters into parameters of the record
			JLoader::register('flexicontent_tmpl', JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'helpers'.DS.'tmpl.php');
			$layout_data = flexicontent_tmpl::mergeLayoutParams($item, $data, $options);

			// Unset layout data since these we handled above
			unset($data[$options['params_fset']]['layouts']);
		}


		//***
		//*** Create one Registry object for every existing data property
		//***

		$db_data_registry = array();
		foreach($properties as $prop)
		{
			$db_data_registry[$prop] = new JRegistry();
		}


		//***
		//*** Filter via all given JForms
		//***
		
		foreach($options['model_names'] as $extension_name => $model_name)
		{
			// Check XML file exists
			$model_xml_filepath = JPath::clean(JPATH_BASE.DS.'components'.DS . $extension_name . DS.'models'.DS.'forms'.DS . $model_name . '.xml');
			if (!file_exists($model_xml_filepath))
			{
				throw new Exception('Error reading model \'s form XML file : ' . $model_xml_filepath . ' file not found', 500);
			}

			// Attempt to parse the XML file
			$xml = simplexml_load_file($model_xml_filepath);
			if (!$xml)
			{
				throw new Exception('Error parsing model \'s XML form file : ' . $model_xml_filepath, 500);
			}

			// Create a JForm object to validate EXISTIND DB data according to the XML file of the model
			$jform = new JForm($extension_name . '.' . $model_name, array('control' => 'jform', 'load_data' => false));
			$xml_string = $xml->asXML();
			$jform->load($xml_string);

			foreach($properties as $prop)
			{
				if (isset($data[$prop]) && is_array($data[$prop]))
				{
					// Filter the existing data with the current JForm
					$db_data = new JRegistry($item->$prop);
					$db_data = array($prop => $db_data->toArray());
					$db_data = $jform->filter($db_data);

					// Merge the above into the existing data Registry object of the corresponding property
					if (!empty($db_data[$prop]))
					{
						$db_data_registry[$prop]->loadArray($db_data[$prop]);
					}
				}
			}
		}


		//***
		//*** Add to existing data the new data and the layout data
		//***

		foreach($properties as $prop)
		{
			if (isset($data[$prop]) && is_array($data[$prop]))
			{
				// Overwrite existing data with new data
				$db_data_registry[$prop]->loadArray($data[$prop]);

				// Add the layout data too (validated above)
				if (!empty($layout_data) && $prop == $options['params_fset'])
				{
					$db_data_registry[$prop]->loadArray($layout_data);
				}

				// Convert property back to string
				$item->$prop = $db_data_registry[$prop]->toString();
			}
		}
	}


	/**
	 * Method to get the enqueued message array
	 *
	 * @since	3.2.0
	 */
	public function getMessageQueue()
	{
		return $this->_messages;
	}


	/**
	 * Method to register a message to the message Queue
	 *
	 * @access	protected
	 * @param	object   $message   The message object
	 *
	 * @since	3.2.0
	 */
	protected function registerMessage($message)
	{
		$this->_messages[] = $message;
	}


	/**
	 * Method to add a message to the message Queue
	 *
	 * @access	protected
	 * @param	object   $message   The message object
	 *
	 * @since	3.2.0
	 */
	public function enqueueMessages($exclude = array())
	{
		$app = JFactory::getApplication();
		$messages = $this->getMessageQueue();

		if ($messages)
		{
			foreach($messages as $message)
			{
				// Skip some message if this was requested
				if ( isset($exclude['showAfterLoad']) && !empty($message->showAfterLoad) )
				{
					continue;
				}
				$app->enqueueMessage($message->text, $message->type);
			}
		}
	}


	/**
	 * Method to do some record / data preprocessing before call JTable::bind()
	 *
	 * Note. Typically called inside this MODEL 's store()
	 *
	 * @access	protected
	 * @param	object   $record   The record object
	 * @param	array    $data     The new data array
	 *
	 * @since	3.2.0
	 */
	protected function _prepareBind($record, & $data)
	{
		// (For nested records) Set the new parent id if parent id not matched OR while New/Save as Copy .
		if (property_exists($record, 'parent_id') && ($record->parent_id != $data['parent_id'] || $data['id'] == 0))
		{
			$record->setLocation($data['parent_id'], 'last-child');
		}

		// Put the new records in last position
		if (property_exists($record, 'ordering') && !$record->id && !empty($this->useLastOrdering))
		{
			$record->ordering = $record->getNextOrder();
		}

		// Handle data of the selected ilayout
		$jinput = JFactory::getApplication()->input;
		$task   = $jinput->get('task', '', 'cmd');

		$parent_id  = isset($data['parent_id']) ? $data['parent_id'] : null;
		$alias_prop = isset($data['alias']) ? 'alias' : (isset($data['name']) ? 'name' : null);
		$title_prop = isset($data['title']) ? 'title' : (isset($data['label']) ? 'label' : (isset($data['name']) ? 'name' : null));

		// Alter the title for save as copy
		if ($task == 'save2copy')
		{
			list($title, $alias) = $this->generateNewTitle(
				$parent_id,
				$alias_prop ? $data[$alias_prop] : null,
				$title_prop ? $data[$title_prop] : null
			);
			if ($title && $title_prop)
			{
				$data[$title_prop] = $title;
			}

			if ($alias && $alias_prop)
			{
				$data[$alias_prop] = $alias;
			}
		}
	}


	/**
	 * Method to do some work after record has been stored
	 *
	 * Note. Typically called inside this MODEL 's store()
	 *
	 * @access	protected
	 * @param	object   $record   The record object
	 * @param	array    $data     The new data array
	 *
	 * @since	3.2.0
	 */
	protected function _afterStore($record, & $data)
	{
	}


	/**
	 * Method to do some work after record has been loaded via JTable::load()
	 *
	 * Note. Typically called inside this MODEL 's store()
	 *
	 * @access	protected
	 * @param	object   $record   The record object
	 *
	 * @since	3.2.0
	 */
	protected function _afterLoad($record)
	{
		// Record was not found / not created, nothing to do
		if (!$record)
		{
			return;
		}

		// Convert attributes to a JRegistry object
		if (property_exists($record, 'attribs') && !is_object($record->attribs))
		{
			$record->attribs = new JRegistry($record->attribs);
		}

		// Convert parameters to a JRegistry object
		if (property_exists($record, 'params') && !is_object($record->params))
		{
			$record->params = new JRegistry($record->params);
		}
	}
}
