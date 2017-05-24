<?php
/**
 * @version 1.5 stable $Id: category.php 1372 2012-07-08 19:08:21Z ggppdk $
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
 * FLEXIcontent Component Category Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelCategory extends JModelAdmin
{
	/**
	 * Record name
	 *
	 * @var string
	 */
	var $record_name = 'category';

	/**
	 * Record database table 
	 *
	 * @var string
	 */
	var $records_dbtbl = 'com_categories';

	/**
	 * Record jtable name
	 *
	 * @var string
	 */
	var $records_jtable = 'flexicontent_categories';

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
	var $useLastOrdering = false;

	/**
	 * Plugin group used to trigger events
	 *
	 * @var boolean
	 */
	var $plugins_group = 'content';

	/**
	 * Various record specific properties
	 *
	 */
	var $_inherited_params = null;  // @var object, Inherited parameters
	var $extension_proxy = 'com_content';

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
	function &getCategory($pk = null)
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
		$record->parent_id			= 0;
		$record->title					= null;
		$record->name						= null;  //$this->record_name . ($this->_getLastId() + 1);
		$record->alias					= null;
		$record->description		= null;
		$record->extension			= FLEXI_CAT_EXTENSION;
		$record->image_position	= 'left';
		$record->published			= 1;
		$record->params					= null;
		$record->editor					= null;
		$record->ordering				= 0;
		$record->access					= 1;
		$record->count					= 0;
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
	function save($data)
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


		// Merge template fieldset this should include at least 'clayout' and optionally 'clayout_mobile' parameters
		if( !empty($data['templates']) )
		{
			$data['params'] = array_merge($data['params'], $data['templates']);
			unset($data['templates']);
		}

		// Merge other special parameters, e.g. 'inheritcid'
		if( !empty($data['special']) )
		{
			$data['params'] = array_merge($data['params'], $data['special']);
			unset($data['special']);
		}

		// Get RAW layout field values, validation will follow ...
		$raw_data = JFactory::getApplication()->input->post->get('jform', array(), 'array');
		$data['params']['layouts'] = !empty($raw_data['layouts']) ? $raw_data['layouts'] : null;


		// ***
		// *** Special handling of some FIELDSETs: e.g. 'attribs/params' and optionally for other fieldsets too, like: 'metadata'
		// *** By doing partial merging of these arrays we support having only a sub-set of them inside the form
		// *** we will use mergeAttributes() instead of bind(), thus fields that are not set will maintain their current DB values,
		// ***
		$mergeProperties = array('params');
		$mergeOptions = array('params_fset' => 'params', 'layout_type' => 'category');
		$this->mergeAttributes($record, $data, $mergeProperties, $mergeOptions);

		// Unset the above handled FIELDSETs from $data, since we selectively merged them above into the RECORD,
		// thus they will not overwrite the respective RECORD's properties during call of JTable::bind()
		foreach($mergeProperties as $prop)
		{
			unset($data[$prop]);
		}


		// Set the new parent id if parent id not matched OR while New/Save as Copy .
		if ($record->parent_id != $data['parent_id'] || $data['id'] == 0)
		{
			$record->setLocation($data['parent_id'], 'last-child');
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

		// Update language Associations
		$this->saveAssociations($record, $data);

		// Trigger the onContentAfterSave event.
		$dispatcher->trigger($this->event_after_save, array($this->option . '.' . $this->name, &$record, $isNew, $data));

		// Rebuild the path for the category:
		if (!$record->rebuildPath($record->id))
		{
			$this->setError($record->getError());
			return false;
		}

		// Rebuild the paths of the category's children:
		if (!$record->rebuild($record->id, $record->lft, $record->level, $record->path))
		{
			$this->setError($record->getError());
			return false;
		}

		// Restore extension property of the category to 'com_content'
		if ($record->id)
		{
			$query 	= 'UPDATE #__categories'
				. ' SET extension = "com_content" '
				. ' WHERE id = ' . (int)$record->id
				;
		}

		// Clear the cache
		$this->cleanCache();

		return true;
	}


	/**
	 * Custom clean the cache
	 *
	 * @since	1.6
	 */
	protected function cleanCache($group = NULL, $client_id = -1)
	{
		// -1 means both, but we will do both always
		parent::cleanCache('com_flexicontent');
		parent::cleanCache('com_flexicontent_cats');
		parent::cleanCache('com_content');
		parent::cleanCache('mod_articles_archive');
		parent::cleanCache('mod_articles_categories');
		parent::cleanCache('mod_articles_category');
		parent::cleanCache('mod_articles_latest');
		parent::cleanCache('mod_articles_news');
		parent::cleanCache('mod_articles_popular');
	}
	
	
	
	/**
	 * Method to load inherited parameters
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	function getInheritedParams($force=false)
	{
		if ( $this->_inherited_params !== NULL && !$force ) return $this->_inherited_params;
		$id = (int)$this->_id;
		
		$app = JFactory::getApplication();
		
		// a. Clone component parameters ... we will use these as parameters base for merging
		$compParams = clone(JComponentHelper::getComponent('com_flexicontent')->params);     // Get the COMPONENT only parameters
		
		// b. Retrieve category parameters and create parameter object
		if ($id) {
			$query = 'SELECT params FROM #__categories WHERE id = ' . $id;
			$this->_db->setQuery($query);
			$catParams = $this->_db->loadResult();
			$catParams = new JRegistry($catParams);
		} else {
			$catParams = new JRegistry();
		}
		
		
		// c. Retrieve inherited parameter and create parameter objects
		global $globalcats;
		$heritage_stack = array();
		$inheritcid = $catParams->get('inheritcid', '');
		$inheritcid_comp = $compParams->get('inheritcid', -1);
		$inherit_parent = $inheritcid==='-1' || ($inheritcid==='' && $inheritcid_comp);
		
		// CASE A: inheriting from parent category tree
		if ( $id && $inherit_parent && !empty($globalcats[$id]->ancestorsonly) ) {
			$order_clause = 'level';  // 'FIELD(id, ' . $globalcats[$id]->ancestorsonly . ')';
			$query = 'SELECT title, id, params FROM #__categories'
				.' WHERE id IN ( ' . $globalcats[$id]->ancestorsonly . ')'
				.' ORDER BY '.$order_clause.' DESC';
			$this->_db->setQuery($query);
			$catdata = $this->_db->loadObjectList('id');
			if (!empty($catdata)) {
				foreach ($catdata as $parentcat) {
					$parentcat->params = new JRegistry($parentcat->params);
					array_push($heritage_stack, $parentcat);
					$inheritcid = $parentcat->params->get('inheritcid', '');
					$inherit_parent = $inheritcid==='-1' || ($inheritcid==='' && $inheritcid_comp);
					if ( !$inherit_parent ) break; // Stop inheriting from further parent categories
				}
			}
		}
		
		// CASE B: inheriting from specific category
		else if ( $id && $inheritcid > 0 && !empty($globalcats[$inheritcid]) ){
			$query = 'SELECT title, params FROM #__categories WHERE id = '. $inheritcid;
			$this->_db->setQuery($query);
			$catdata = $this->_db->loadObject();
			if ($catdata) {
				$catdata->params = new JRegistry($catdata->params);
				array_push($heritage_stack, $catdata);
			}
		}
		
		
		// *******************************************************************************************************
		// Start merging of parameters, OVERRIDE ORDER: layout(template-manager)/component/ancestors-cats/category
		// *******************************************************************************************************
		
		// -1. layout parameters will be placed on top at end of this code ...
		
		// 0. Start from component parameters
		$params = new JRegistry();
		$params->merge($compParams);
		
		// 1. Merge category's inherited parameters (e.g. ancestor categories or specific category)
		while (!empty($heritage_stack)) {
			$catdata = array_pop($heritage_stack);
			if ($catdata->params->get('orderbycustomfieldid')==="0") $catdata->params->set('orderbycustomfieldid', '');
			$params->merge($catdata->params);
		}
		
		// 2. Merge category parameters -- CURRENT CATEGORY PARAMETERS MUST BE SKIPED ! we only want the inherited parameters
		//if ($catParams->get('orderbycustomfieldid')==="0") $catParams->set('orderbycustomfieldid', '');
		//$params->merge($catParams);
		
		// Retrieve Layout's parameters
		$layoutParams = flexicontent_tmpl::getLayoutparams('category', $params->get('clayout'), '', $force);
		$layoutParams = new JRegistry($layoutParams);
		
		// Allow global layout parameters to be inherited properly, placing on TOP of all others
		$this->_inherited_params = clone($layoutParams);
		$this->_inherited_params->merge($params);
		
		return $this->_inherited_params;
	}


	/**
	 * Method to get the parameters of another category
	 *
	 * @access	public
	 * @params	int			id of the category
	 * @return	string		ini string of params
	 * @since	1.5
	 */
	function getParams($id)
	{
		$query 	= 'SELECT params FROM #__categories'
				. ' WHERE id = ' . (int)$id
				;
		$this->_db->setQuery($query);

		return $this->_db->loadResult();
	}


	/**
	 * Method to copy category parameters
	 *
	 * @param 	int 	$id of target
	 * @param 	string 	$params to copy
	 * @return 	boolean	true on success
	 * 
	 * @since 1.5
	 */
	function copyParams($id, $params)
	{
		$query 	= 'UPDATE #__categories'
			. ' SET params = ' . $this->_db->Quote($params)
			. ' WHERE id = ' . (int)$id
			;
		$this->_db->setQuery($query);
		if (!$this->_db->execute()) {
			return false;
		}
		return true;
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
		$extension = $this->getState('category.extension');
		$jinput = JFactory::getApplication()->input;

		// A workaround to get the extension and other data into the model for save requests.
		if (empty($extension) && isset($data['extension']))
		{
			$extension = $data['extension'];
			$parts = explode('.', $extension);

			$this->setState('category.extension', $extension);
			$this->setState('category.component', $parts[0]);
			$this->setState('category.section', @$parts[1]);
		}
		$this->setState('category.language', isset($data['language']) ? $data['language'] : null);

		// Get the form.
		$form = $this->loadForm($this->option.'.'.$this->getName(), $this->getName(), array('control' => 'jform', 'load_data' => $loadData));

		if (empty($form))
		{
			return false;
		}

		// Modify the form based on Edit State access controls.
		if (empty($data['extension']))
		{
			$data['extension'] = $extension;
		}

		// Force asset from request
		$categoryId = $jinput->get('id');
		$assetKey   = $categoryId ? $this->extension_proxy . '.category.' . $categoryId : $this->extension_proxy;

		if (!JFactory::getUser()->authorise('core.edit.state', $assetKey))
		{
			// Disable fields for display.
			$form->setFieldAttribute('ordering', 'disabled', 'true');
			$form->setFieldAttribute('published', 'disabled', 'true');

			// Disable fields while saving.
			// The controller has already verified this is a record you can edit.
			$form->setFieldAttribute('ordering', 'filter', 'unset');
			$form->setFieldAttribute('published', 'filter', 'unset');
		}

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
		$app->setUserState($this->option.'.edit.'.$this->getName().'.data', false);

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
		$item = parent::getItem($pk);

		if ( $item )
		{
			// Prime required properties.
			if (empty($item->id))
			{
				$item->parent_id	= $this->getState('category.parent_id');
				$item->extension	= $this->getState('category.extension');
			}

			// Convert the metadata field to an array.
			$registry = new JRegistry($item->metadata);
			$item->metadata = $registry->toArray();

			// Convert the created and modified dates to local user time for display in the form.
			jimport('joomla.utilities.date');
			
			$site_zone = JFactory::getApplication()->getCfg('offset');
			$user_zone = JFactory::getUser()->getParam('timezone', $site_zone);
			$tz_string = $user_zone;
			$tz = new DateTimeZone( $tz_string );
			
			if (intval($item->created_time))
			{
				$date = new JDate($item->created_time);
				$date->setTimezone($tz);
				$item->created_time = $date->toSql(true);
			}
			else
			{
				$item->created_time = null;
			}

			if (intval($item->modified_time))
			{
				$date = new JDate($item->modified_time);
				$date->setTimezone($tz);
				$item->modified_time = $date->toSql(true);
			}
			else
			{
				$item->modified_time = null;
			}

			$this->_record = $item;

			$useAssocs = $this->useAssociations();
			if ($useAssocs)
			{
				if ($item->id != null)
				{
					$item->associations = CategoriesHelper::getAssociations($item->id, $item->extension);
					JArrayHelper::toInteger($item->associations);
				}
				else
				{
					$item->associations = array();
				}
			}
		}

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
		jimport('joomla.filesystem.path');

		$lang = JFactory::getLanguage();
		$component = $this->getState('category.component');
		$section = $this->getState('category.section');
		$extension = JFactory::getApplication()->input->get('extension', null);

		// Get the component form if it exists
		$name = 'category' . ($section ? ('.' . $section) : '');

		// Try to find the component helper.
		$eName = str_replace('com_', '', $component);
		$path = JPath::clean(JPATH_ADMINISTRATOR . "/components/$component/helpers/category.php");

		if (file_exists($path))
		{
			$cName = ucfirst($eName) . ucfirst($section) . 'HelperCategory';

			JLoader::register($cName, $path);

			if (class_exists($cName) && is_callable(array($cName, 'onPrepareForm')))
			{
				$lang->load($component, JPATH_BASE, null, false, false)
					|| $lang->load($component, JPATH_BASE . '/components/' . $component, null, false, false)
					|| $lang->load($component, JPATH_BASE, $lang->getDefault(), false, false)
					|| $lang->load($component, JPATH_BASE . '/components/' . $component, $lang->getDefault(), false, false);
				call_user_func_array(array($cName, 'onPrepareForm'), array(&$form));

				// Check for an error.
				if ($form instanceof Exception)
				{
					$this->setError($form->getMessage());

					return false;
				}
			}
		}

		// Set the access control rules field component value.
		$form->setFieldAttribute('rules', 'component', $component);
		$form->setFieldAttribute('rules', 'section', $name);

		// Association category items
		if ($this->useAssociations())
		{
			$languages = JLanguageHelper::getContentLanguages(false, true, null, 'ordering', 'asc');
			$data_language = !empty($data->language) ? $data->language : $this->getState('category.language');

			if (count($languages) > 1)
			{
				$addform = new SimpleXMLElement('<form />');
				$fields = $addform->addChild('fields');
				$fields->addAttribute('name', 'associations');
				$fieldset = $fields->addChild('fieldset');
				$fieldset->addAttribute('name', 'item_associations');
				$fieldset->addAttribute('description', 'COM_CATEGORIES_ITEM_ASSOCIATIONS_FIELDSET_DESC');

				foreach ($languages as $language)
				{
					if ($language->lang_code == $data_language) continue;
					$field = $fieldset->addChild('field');
					$field->addAttribute('name', $language->lang_code);
					$field->addAttribute('type', 'qfcategory');
					$field->addAttribute('language', $language->lang_code);
					$field->addAttribute('label', $language->title);
					$field->addAttribute('class', 'label');
					$field->addAttribute('translate_label', 'false');
					$field->addAttribute('extension', $extension);
					$field->addAttribute('edit', 'true');
					$field->addAttribute('clear', 'true');
					$field->addAttribute('filter', 'INT');  // also enforced later, but better to have it here too
				}

				$form->load($addform, false);
			}
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

		$parentId = $app->input->getInt('parent_id');
		$this->setState('category.parent_id', $parentId);

		// Load the User state.
		$pk = $this->_id ?: $app->input->getInt('id');
		$this->_id = $pk;
		$this->setState($this->getName() . '.id', $pk);

		$extension = $app->input->getCmd('extension', FLEXI_CAT_EXTENSION);
		$this->setState('category.extension', $extension);
		$parts = explode('.', $extension);

		// Extract the component name
		$this->setState('category.component', $parts[0]);

		// Extract the optional section name
		$this->setState('category.section', (count($parts) > 1) ? $parts[1] : null);

		// Load the parameters.
		$params	= JComponentHelper::getParams('com_flexicontent');
		$this->setState('params', $params);
	}


	/**
	 * Method to get parameters of parent categories
	 *
	 * @access public
	 * @return	string
	 * @since	1.6
	 */
	function getParentParams($cid)
	{
		if (empty($cid)) return array();
		
		global $globalcats;
		$db = JFactory::getDBO();
		
		// Select the required fields from the table.
		$query = ' SELECT id, params '
			. ' FROM #__categories '
			. ' WHERE id IN (' . $globalcats[$cid]->ancestors . ') '
			. ' ORDER BY level ASC '
			;
		$db->setQuery( $query );
		$data = $db->loadObjectList('id');
		return $data;
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
	function saveAssociations(&$item, &$data)
	{
		$item = $item ? $item: $this->_record;
		$context = 'com_categories';
		
		return flexicontent_db::saveAssociations($item, $data, $context);
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
	 * Method to check if the user can edit the record
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
	 * Method to check if the user can edit the record
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
			list($title, $alias) = $this->generateNewTitle($data['parent_id'], $data['alias'], $data['title']);
			$data['title'] = $title;
			$data['alias'] = $alias;
		}

		// Optional copy parameters from another category
		$copycid = (int) $data['copycid'];
		if ($copycid)
		{
			unset($data['params']);
			$record->params = $this->getParams($copycid);
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
	}
}
