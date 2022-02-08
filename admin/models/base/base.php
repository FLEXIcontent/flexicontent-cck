<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Table\Table;

jimport('legacy.model.admin');
require_once('traitbase.php');

/**
 * FLEXIcontent Component BASE (form) Model
 *
 */
abstract class FCModelAdmin extends JModelAdmin
{
	use FCModelTraitBase;

	/**
	 * Array of URL variable names that should be tried for getting record id
	 *
	 * @var array
	 */
	var $record_keys = array('id', 'cid');

	/**
	 * Record name, (parent class property), this is used for: naming session data, XML file of class, etc
	 *
	 * @var string
	 */
	protected $name = null;

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
	 * Column names
	 */
	var $state_col   = null;
	var $name_col    = null;
	var $parent_col  = null;

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
	 * Events context to use during model FORM events and diplay PREPARE events triggering
	 *
	 * @var object
	 */
	var $events_context = null;

	/**
	 * Record's type alias string
	 *
	 * @var        string
	 */
	var $type_alias = null;

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
	 * Context to use for registering (language) associations
	 *
	 * @var string
	 */
	var $associations_context = false;

	/**
	 * A message queue when appropriate
	 *
	 * @var string
	 */
	var $_messages= array();

	/**
	 * Various record specific properties
	 *
	 */

	/**
	 * List filters that are always applied
	 */
	var $hard_filters = array();

	/**
	 * Array of supported state conditions of the record
	 */
	var $supported_conditions = array(
		 1 => 'FLEXI_PUBLISHED',
		 0 => 'FLEXI_UNPUBLISHED',
		 2 => 'FLEXI_ARCHIVED',
		-2 => 'FLEXI_TRASHED'
	);

	/**
	 * Groups of Fields that can be partially present in the form
	 */
	var $mergeableGroups = array();


	/**
	 * Constructor
	 *
	 * @since 3.3.0
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		// Make sure this is correct if called from different component ...
		$this->option = 'com_flexicontent';

		// Initialize using default naming if not already set
		$this->records_dbtbl  = $this->records_dbtbl  ?: 'flexicontent_' . $this->getName() . 's';
		$this->records_jtable = $this->records_jtable ?: 'flexicontent_' . $this->getName() . 's';

		$this->events_context = $this->events_context ?: $this->option . '.' . $this->getName();
		$this->type_alias     = $this->type_alias     ?: $this->option . '.' . $this->getName();

		$jinput = JFactory::getApplication()->input;
		$pk = null;

		// Try all key in prefered order
 		foreach($this->record_keys as $key)
 		{
			if ($pk === null)
			{
				$id = $jinput->get($key, array(), 'array');
				if (count($id))
				{
					$id = ArrayHelper::toInteger($id);
					$pk = (int) $id[0];
				}
			}
		}

		// Finally try getting id from JForm submitted data
		if ($pk === null)
		{
			$data = $jinput->get('jform', array(), 'array');
			$pk = isset($data['id']) ? (int) $data['id'] : 0;
		}

		$pk = $pk ?: 0;
		$this->setId($pk);
	}


	/**
	 * Method to set the record identifier
	 *
	 * @param		int	    $id        record identifier
	 *
	 * @since	3.3.0
	 */
	public function setId($id)
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
	 * @return	int record identifier
	 */
	public function getId()
	{
		return $this->_id;
	}


	/**
	 * Overridden get method to get properties from the record
	 *
	 * @param	string	$property	The name of the property
	 * @param	mixed	$value		The value of the property to set
	 *
	 * @return 	mixed 				The value of the property
	 *
	 * @since	1.0
	 */
	public function get($property, $default=null)
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
	 * @param	  string	 $property	 The name of the property
	 * @param	  mixed	   $value		   The value of the property to set
	 *
	 * @return	boolean  True on success
	 *
	 * @since	1.5
	 */
	public function set($property, $value=null)
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
	 * @param	  string	 $property	 The name of the property
	 * @param	  mixed	   $value		   The value of the property to set
	 *
	 * @return	void
	 *
	 * @since	3.2
	 */
	public function setProperty($property, $value=null)
	{
		$this->$property = $value;
	}


	/**
	 * Method to get a record via an alternative way that allows using multiple property values
	 *
	 * @return	object
	 *
	 * @since	1.0
	 */
	public function getRecord($pk = null)
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
	 * @return	boolean	True on success
	 *
	 * @since	1.0
	 */
	protected function _loadRecord($pk = null)
	{
		$table = $this->getTable();

		if (is_array($pk))
		{
			$data = $pk;
		}
		elseif ($pk)
		{
			/**
			 * If PK is not an integer, then possibly we were given a name,
			 * try to match it with columns 'name' or 'alias', if they exist
			 */
			if (!is_integer($pk))
			{
				$pk_col = null;
				$columns = array('name', 'alias');

				foreach($columns as $column)
				{
					$pk_col = !$pk_col && property_exists($table, $column) ? $column : $pk_col;
				}
			}

			/**
			 * Either use the 'name' / 'alias' / ... column, or use the primary column value
			 */
			$data = !empty($pk_col)
				? array($pk_col => (string) $pk)
				: array($table->getKeyName() => (int) $pk);
		}

		/**
		 * Lets load the record if not already loaded
		 */
		 
		if (!empty($data))
		{
			$data_matches = $data && (boolean) $this->_record;

			foreach ($data as $k => $v)
			{
				$data_matches = $data_matches && (string) $this->_record->$k === (string) $v;
			}

			if (!$data_matches)
			{
				$this->_record = false;
				
				// Attempt to load the row.
				$return = $table->load($data);

				// Check for a table object error.
				if ($return === false && $table->getError())
				{
					$this->setError($table->getError());
					return false;
				}

				// Set record
				$this->_id = $table->id;
				$this->_record = $table;

				// Extra steps after loading
				$this->_afterLoad($this->_record);
			}
		}

		return (boolean) $this->_record;
	}


	/**
	 * Method to initialise the record data
	 *
	 * @param   object      $record    The record being initialized
	 * @param   boolean     $initOnly  If true then only a new record will be initialized without running the _afterLoad() method
	 * @return	boolean	True on success
	 * @since	1.5
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
	public function isCheckedOut( $uid=0 )
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
	public function store($data)
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
	public function save($data)
	{
		// Initialise variables
		$app        = JFactory::getApplication();
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
		$results = FLEXI_J40GE
			? $app->triggerEvent($this->event_before_save, array($this->option . '.' . $this->name, &$record, $isNew, $data))
			: $dispatcher->trigger($this->event_before_save, array($this->option . '.' . $this->name, &$record, $isNew, $data));

		// Abort record store if any plugin returns a result === false
		if (is_array($results) && in_array(false, $results, true))
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

		// Trigger the after save event (typically: onContentAfterSave)
		$results = FLEXI_J40GE
			? $app->triggerEvent($this->event_after_save, array($this->option . '.' . $this->name, &$record, $isNew, $data))
			: $dispatcher->trigger($this->event_after_save, array($this->option . '.' . $this->name, &$record, $isNew, $data));

		// Abort further actions if any plugin returns a result === false
		/*if (is_array($results) && in_array(false, $results, true))
		{
			$this->setError($record->getError());
			return false;
		}*/

		// Extra steps after loading record, and before calling JTable::bind()
		$this->_afterStore($record, $data);

		// Clear the cache
		$this->cleanCache(null, 0);
		$this->cleanCache(null, 1);

		return true;
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
		// Set form path in case we are called form different extension
		\JForm::addFormPath(JPATH_BASE.DS.'components'.DS.'com_flexicontent' . '/models/forms');
		\JForm::addFieldPath(JPATH_BASE.DS.'components'.DS.'com_flexicontent' . '/models/fields');

		// Get the form.
		$form_name    = $this->events_context;
		$xml_filename = $this->getName();
		$form = $this->loadForm($form_name, $xml_filename, array('control' => 'jform', 'load_data' => $loadData));

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
			$item = $this->getItem();

			/**
			 * Because the record data are meant for JForm before any other manipulations and before any
			 * other data is added, convert our JTable record to a JObject coping only public properies
			 */
			$_prop_arr = $item->getProperties($public_only = true);
			$data = ArrayHelper::toObject($_prop_arr, 'JObject');
		}
		else
		{
		}

		$this->preprocessData($this->events_context, $data);

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

		if ($pk && isset($items[$pk]))
		{
			return $items[$pk];
		}

		// Instatiate the JTable
		$table = $this->getTable();

		if ($pk)
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

		// Add to cache if not a new record
		if ($pk)
		{
			$items[$pk] = $this->_record;
		}

		return $this->_record;
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
	 * Method to validate the form data.
	 *
	 * @param   \JForm  $form   The form to validate against.
	 * @param   array   $data   The data to validate.
	 * @param   string  $group  The name of the field group to validate.
	 *
	 * @return  array|boolean  Array of filtered data if valid, false otherwise.
	 *
	 * @see     \JFormRule
	 * @see     \JFilterInput
	 * @since   3.3.0
	 */
	public function validate($form, $data, $group = null)
	{
		return parent::validate($form, $data, $group);
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
		$app = JFactory::getApplication();

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
	function saveAssociations(&$record, &$data, $add_current = true)
	{
		if( !$this->associations_context ) return true;

		$record = $record ? $record: $this->_record;
		return flexicontent_db::saveAssociations($record, $data, $this->associations_context, $add_current);
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
	 * @return	boolean	True on success
	 *
	 * @since	3.2.0
	 */
	public function canEdit($record = null, $user = null)
	{
		if ($user)
		{
			throw new Exception(__FUNCTION__ . '(): Error model does not support checking ACL of specific user', 500);
		}

		$record = $record ?: $this->_record;
		$user   = $user ?: JFactory::getUser();

		return false;
	}


	/**
	 * Method to check if the user can edit record 's state
	 *
	 * @return	boolean	True on success
	 *
	 * @since	3.2.0
	 */
	public function canEditState($record = null, $user = null)
	{
		if ($user)
		{
			throw new Exception(__FUNCTION__ . '(): Error model does not support checking ACL of specific user', 500);
		}

		$record = $record ?: $this->_record;
		$user   = $user ?: JFactory::getUser();

		return false;
	}


	/**
	 * Method to check if the user can delete the record
	 *
	 * @return	boolean	True on success
	 *
	 * @since	3.2.0
	 */
	public function canDelete($record = null)
	{
		$record = $record ?: $this->_record;

		return false;
	}


	/**
	 * Helper method to format a value as array
	 *
	 * @return object
	 * @since 3.2.0
	 */
	public function formatToArray($value)
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
		$form = $this->getForm();

		/**
		 * Mark form fields that have been skipped (according to configuration),
		 * setting them to false to indicate maintaining their DB values
		 */
		$this->handlePartialForm($form, $data);

		/**
		 * Canonicalize data that should be present in the form by
		 * setting them to '' to indicate clearing their DB values
		 */
		$this->_canonicalData($form, $data);


		/**
		 * Filter layout parameters if the were given, and merge them into existing layout parameters (in DB)
		 */

		if (isset($options['params_fset']) && isset($options['layout_type']))
		{
			// Merge Layout parameters into parameters of the record
			JLoader::register('flexicontent_tmpl', JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'helpers'.DS.'tmpl.php');
			$layout_data = flexicontent_tmpl::mergeLayoutParams($item, $data, $options);

			// Unset layout data since these we handled above
			unset($data[$options['params_fset']]['layouts']);
		}


		/**
		 * Create one Registry object for every existing data property
		 */

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
			$file_exists = file_exists($model_xml_filepath);

			if (!$file_exists && FLEXI_J40GE)
			{
				$model_xml_filepath = JPath::clean(JPATH_BASE.DS.'components'.DS . $extension_name . DS.'forms'.DS . $model_name . '.xml');
				$file_exists = file_exists($model_xml_filepath);
			}

			if (!$file_exists)
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
				//$db_data_registry[$prop]->loadArray($data[$prop]);
				$db_data_registry[$prop] = $this->maintainDbData($db_data_registry[$prop], $data[$prop]);

				// Add the layout data too (validated above)
				if (!empty($layout_data) && $prop == $options['params_fset'])
				{
					//$db_data_registry[$prop]->loadArray($layout_data);
					$db_data_registry[$prop] = $this->maintainDbData($db_data_registry[$prop], $layout_data);
				}

				// Convert property back to string
				$item->$prop = $db_data_registry[$prop]->toString();
			}
		}
	}


	/**
	 * Method to get the records having the given name
	 *
	 * @since	3.2.1.8
	 */
	public function loadRecordsByName($name = null)
	{
		// Check 'name' columns and then check 'alias' column exists, if none then clear $name
		$table = $this->getTable();

		$name_property = property_exists($table, 'name')
			? 'name'
			: (property_exists($table, 'alias')
				? 'alias'
				: (property_exists($table, 'title')
					? 'title'
					: null
				)
			);

		$name = $name_property ? $name : null;

		if ($name)
		{
			$query = $this->_db->getQuery(true)
				->select('*')
				->from('#__' . $this->records_dbtbl)
				->where($this->_db->quoteName('name') . ' = ' . $this->_db->Quote($name))
			;
			return $this->_db->setQuery($query)->loadObjectList('id');
		}

		return false;
	}


	/**
	 * Method to initialize member variables used by batch methods and other methods like saveorder()
	 *
	 * @return  void
	 *
	 * @since   3.3.0
	 */
	public function initBatch()
	{
		if ((FLEXI_J38GE && $this->batchSet === null) || (!FLEXI_J38GE && empty($this->batchSet)))
		{
			$this->batchSet = true;

			// Get current user
			$this->user = \JFactory::getUser();

			// Get table
			$this->table = $this->getTable($this->records_dbtbl, 'JTable');

			// Get table class name
			$tc = explode('\\', get_class($this->table));
			$this->tableClassName = end($tc);

			// Get UCM Type data
			$this->contentType = new \JUcmType;
			$this->type = $this->contentType->getTypeByTable($this->tableClassName)
				?: $this->contentType->getTypeByAlias($this->type_alias);

			// Get tabs observer
			if (!FLEXI_J38GE)
			{
				$this->tagsObserver = $this->table->getObserverOfClass('JTableObserverTags');
			}
			elseif (!FLEXI_J40GE)
			{
				$this->tagsObserver = $this->table->getObserverOfClass('Joomla\CMS\Table\Observer\Tags');
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
	 * Method to modify specific attributes of a record saving them into the DB
	 *
	 * @param		int				$id				The record id
	 * @param		array			$values		The attributes values indexed by attribute names
	 * @param		int				$propname	The record's property that contains the attributes
	 *
	 * @return	boolean		True on success, false on failure
	 *
	 * @since	3.3.0
	 */
	public function setAttributeValues($id, $values, $propname = 'attribs')
	{
		// Attempt to load the row
		$record = $this->getTable();

		if (!$record->load($id))
		{
			$this->setError($record->getError());
			return false;
		}

		// Try to decode the attributes
		$attribs = json_decode($record->$propname);

		// Set new attribute values
		foreach ($values as $i => $v)
		{
			$attribs->$i = $v;
		}

		$record->$propname = json_encode($attribs);

		// Store data in the db
		if (!$record->store())
		{
			$this->setError($record->getError());
			return false;
		}

		return true;
	}


	/**
	 * Method to do some record / data preprocessing before call JTable::bind()
	 *
	 * Note. Typically called inside this MODEL 's store()
	 *
	 * @param   object     $record   The record object
	 * @param   array      $data     The new data array
	 *
	 * @since	3.2.0
	 */
	protected function _prepareBind($record, & $data)
	{
		// (For nested records) Set the new parent id if parent id not matched OR while New/Save as Copy .
		if (in_array('FCModelTraitNestableRecord', class_uses($this)))
		{
			if (property_exists($record, 'parent_id') && ($record->parent_id != $data['parent_id'] || $data['id'] == 0))
			{
				$record->setLocation($data['parent_id'], 'last-child');
			}
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
		if ($task === 'save2copy')
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
	 * @param   object     $record   The record object
	 * @param   array      $data     The new data array
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


	/**
	 * Method to canonicalize the form data that should be present in the form by setting them to '' to indicate clearing their DB values
	 *
	 * @param   JForm   $form   A JForm object.
	 * @param   mixed   $data   The data expected for the form.
	 *
	 * @return  void
	 *
	 * @since   3.3.0
	 */
	protected function _canonicalData($form, & $data)
	{
		foreach($this->mergeableGroups as $grp_name)
		{
			if (is_object($data))
			{
				continue;
			}

			foreach ($form->getFieldsets($grp_name) as $fsname => $fieldSet)
			{
				foreach ($form->getFieldset($fsname) as $field)
				{
					if (!isset($data[$grp_name][$field->fieldname]))
					{
						/**
						 * Field was not skipped but also was no value was posted,
						 * set form value to '' so that DB value gets cleared during merging
						 */
						$data[$grp_name][$field->fieldname] = '';
					}
				}
			}
		}
	}


	/**
	 * Method to maintain database values if posted specific data are missing (or validation cleared them) from the posted form (value is === false)
	 *
	 * @param   array   $db_data     The existing db data
	 * @param   array   $form_data   The form submitted data but canonicalized data (missing parameters were set to false)
	 *
	 * @return  void
	 *
	 * @since   3.3.0
	 */
	protected function maintainDbData($db_data, $form_data)
	{
		foreach($form_data as $i => $v)
		{
			// Recursion
			if (is_array($v))
			{
				$db_data[$i] = $this->maintainDbData($db_data[$i], $form_data[$i]);
			}

			// Use form value
			elseif ($v !== false)
			{
				$db_data[$i] = $v;
			}

			// Clear DB value because it has bogus FALSE value from previous save operation
			elseif ($db_data[$i] === false)
			{
				$db_data[$i] = '';
			}
		}

		return $db_data;
	}


	/**
	 * Method to handle partial form data
	 *
	 * @param   JForm   $form   A JForm object.
	 * @param   mixed   $data   The data expected for the form.
	 *
	 * @return  void
	 *
	 * @since   3.3.0
	 */
	protected function handlePartialForm($form, & $data)
	{
	}


	/**
	 * Custom clean the cache
	 *
	 * @param   string   $group      Clean cache only in the given group
	 * @param   integer  $client_id  Site Cache (0) / Admin Cache (1)
	 *
	 * @return  void
	 *
	 * @since   3.2.0
	 */
	protected function cleanCache($group = NULL, $client_id = 0)
	{
		if ($group)
		{
			parent::cleanCache($group, $client_id);
		}

		// An empty '$group' will clean '$this->option' which is the Component VIEW Cache, we will do a little more ...
		else
		{
			/**
			 * Note: null should be the same as $this->option ...
			 * Maybe add option not clean Component's VIEW cache it will be too aggressive ...
			 */
			if (1)
			{
				parent::cleanCache(null, $client_id);
				parent::cleanCache('com_content', $client_id);
			}
		}
	}


	/**
	 * Method to change the state of given record (used by AJAX calls)
	 *
	 * @return  boolean    True on success
	 *
	 * @since	3.2.0
	 */
	public function setitemstate($id, $state = 1, $cleanCache = true)
	{
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();

		static $event_failed_notice_added = false;

		if (!$id)
		{
			return false;
		}

		// Sanitize id
		$cid = (array) $id;
		$cid = ArrayHelper::toInteger($cid);

		// Recursively add all ancestors or descendants if this is a nested record
		if (in_array('FCModelTraitNestableRecord', class_uses($this)))
		{
			// If publishing then add all parents to the list, so that they get published too
			if ($state == 1)
			{
				foreach ($cid as $_id)
				{
					$this->_addPathRecords($_id, $cid, 'parents');
				}
			}

			// If not publishing then all children to the list, so that they get the new state too
			else
			{
				foreach ($cid as $_id)
				{
					$this->_addPathRecords($_id, $cid, 'children');
				}
			}
		}

		$cid = ArrayHelper::toInteger($cid);
		$cid_list = implode(',', $cid);

		if (!empty($this->event_recid_col))
		{
			$query = $this->_db->getQuery(true)
				->select($this->_db->qn($this->event_recid_col))
				->from('#__' . $this->records_dbtbl)
				->set($this->_db->qn($this->state_col) . ' = ' . (int) $state)
				->where('id IN (' . $cid_list . ')')
			;
			$event_ids = $this->_db->setQuery($query)->loadColumn();

			foreach ($event_ids as $i => $v)
			{
				if (!$v)
				{
					unset($event_ids[$i]);
				}
			}
		}
		else
		{
			$event_ids = $cid;
		}


		/**
		 * First update records
		 */

		if (!empty($this->use_jtable_publishing))
		{
			$table = $this->getTable();

			if (!$table->publish($cid, $state, $user->get('id')))
			{
				$this->setError($table->getError());

				return false;
			}
		}
		else
		{
			$query = $this->_db->getQuery(true)
				->update('#__' . $this->records_dbtbl)
				->set($this->_db->qn($this->state_col) . ' = ' . (int) $state)
				->where('id IN (' . $cid_list . ')')
			;
			$this->_db->setQuery($query)->execute();

			if (!empty($this->_setstate_tbls))
			{
				foreach($this->_setstate_tbls as $tbl_name => $config)
				{
					if (in_array($this->state_col, $this->tbl_ext_cols[$tbl_name]))
					{
						$config['rids'] = ArrayHelper::toInteger($config['rids']);
						$rids_list =  implode( ',', $config['rids']);

						$query = $this->_db->getQuery(true)
							->update('#__' . $tbl_name)
							->set($this->_db->qn($config['state_col']) . ' = ' . (int) $state)
							->where($this->_db->qn($config['recid_col']) . ' IN (' . $cid_list . ')')
						;
						$this->_db->setQuery($query)->execute();
					}
				}
			}
		}


		/**
		 * Update version table
		 */
		if ($this->getName() === 'item')
		{
			$v = FLEXIUtilities::getCurrentVersions((int) $id);

			$query = 'UPDATE #__flexicontent_items_versions'
				. ' SET value = ' . (int) $state
				. ' WHERE item_id = ' . (int) $id
				. ' AND valueorder = 1'
				. ' AND field_id = 10'
				. ' AND version = ' . (int) $v['version']
			;
			$this->_db->setQuery($query)->execute();
		}


		/**
		 * Trigger Event 'onContentChangeState' of Joomla's Content plugins
		 */
		if (empty($this->skipChangeStateEvent) && !empty($event_ids))
		{
			// Make sure we import flexicontent AND content plugins since we will be triggering their events
			JPluginHelper::importPlugin('content');

			$jinput     = JFactory::getApplication()->input;
			$dispatcher = JEventDispatcher::getInstance();

			// Compatibility steps, so that 3rd party plugins using the change state event work properly
			$jm_state = $state;

			$parts = explode('.', $this->events_context);
			$is_proxied_context = count($parts) >= 2 && $parts[0] !== $this->option;

			if ($is_proxied_context)
			{
				$current_view   = $jinput->getCmd('view', '');
				$current_option = $jinput->getCmd('option', '');

				$jinput->set('isflexicontent', 'yes');
				$jinput->set('view', $parts[1]);
				$jinput->set('option', $parts[0]);

				/**
				 * Set a Joomla compatible state as we need to map to
				 * a joomla's existing states ... when triggering events
				 */

				// Published states
				if (in_array($state, array(1,-5)))
				{
					$jm_state = 1;
				}

				// Unpublished states
				elseif (in_array($state, array(0,-3,-4)))
				{
					$jm_state = 0;
				}

				// Trashed & Archive states
				else
				{
					$jm_state = $state;
				}

				// Workaround for extensions using the model but not setting correct include path
				$component = $parts[1] === 'category' ? 'com_categories' : $parts[1];
				JModelLegacy::addIncludePath(JPATH_BASE . '/components/' . $component . '/models');
			}

			//Trigger the event
			$results = FLEXI_J40GE
				? $app->triggerEvent($this->event_change_state, array($this->events_context, $event_ids, $jm_state))
				: $dispatcher->trigger($this->event_change_state, array($this->events_context, $event_ids, $jm_state));

			// Revert compatibilty steps ...
			// besides the plugins using the change state event, should have updated DB state value anyway
			if ($is_proxied_context)
			{
				$jinput->set('view', $current_view);
				$jinput->set('option', $current_option);
			}

			// Abort further actions if any plugin returns a result === false
			if (is_array($results) && in_array(false, $results, true))
			{
				if (!$event_failed_notice_added)
				{
					$this->setError('State change events reported a failure during handling the change to the new state');
					$event_failed_notice_added = true;
				}

				return false;
			}
		}

		if ($cleanCache)
		{
			$this->cleanCache(null, 0);
			$this->cleanCache(null, 1);
		}

		return true;
	}

	/**
	 * START OF MODEL SPECIFIC METHODS
	 */

}
