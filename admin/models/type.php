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
use Joomla\String\StringHelper;

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
	 * Record name
	 *
	 * @var string
	 */
	var $record_name = 'type';

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
	function & getType($pk = null)
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
		$record->name						= null;  //$this->record_name . ($this->_getLastId() + 1);
		$record->alias					= null;
		$record->published			= 1;
		$record->itemscreatable	= 0;
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


		// Get RAW layout field values, validation will follow ...
		$raw_data = JFactory::getApplication()->input->post->get('jform', array(), 'array');
		$data['params']['layouts'] = !empty($raw_data['layouts']) ? $raw_data['layouts'] : null;


		// ***
		// *** Special handling of some FIELDSETs: e.g. 'attribs/params' and optionally for other fieldsets too, like: 'metadata'
		// *** By doing partial merging of these arrays we support having only a sub-set of them inside the form
		// *** we will use mergeAttributes() instead of bind(), thus fields that are not set will maintain their current DB values,
		// ***
		$mergeProperties = array('attribs');
		$mergeOptions = array('params_fset' => 'attribs', 'layout_type' => 'item');
		$this->mergeAttributes($record, $data, $mergeProperties, $mergeOptions);

		// Unset the above handled FIELDSETs from $data, since we selectively merged them above into the RECORD,
		// thus they will not overwrite the respective RECORD's properties during call of JTable::bind()
		foreach($mergeProperties as $prop)
		{
			unset($data[$prop]);
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
	 * Method to add core field relation to a type
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	private function _addCoreFieldRelations()
	{
		// Get core fields
		$core = $this->_getCoreFields();

		// Insert core field relations to the DB
		foreach ($core as $fieldid)
		{
			$obj = new stdClass();
			$obj->field_id  = (int) $fieldid;
			$obj->type_id   = $this->_record->id;
			$this->_db->insertObject('#__flexicontent_fields_type_relations', $obj);
		}
	}


	/**
	 * Method to get core field ids
	 * 
	 * @return array
	 * @since 1.5
	 */
	private function _getCoreFields()
	{
		$query = 'SELECT id'
			. ' FROM #__flexicontent_fields'
			. ' WHERE iscore = 1'
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
			? FlexicontentHelperPerm::getPerm()->CanTypes
			: FlexicontentHelperPerm::getPerm()->CanTypes;
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

		return FlexicontentHelperPerm::getPerm()->CanTypes;
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

		return FlexicontentHelperPerm::getPerm()->CanTypes;
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
			list($title, $name) = $this->generateNewTitle(null, $data['name'], $data['title']);
			$data['title'] = $title;
			$data['name'] = $name;
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
		// Only insert default relations if the type is new
		if ( ! $data['id'] )
		{
			$this->_addCoreFieldRelations();
		}
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
	}
}