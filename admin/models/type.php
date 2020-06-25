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

require_once('base/base.php');

/**
 * FLEXIcontent Component Type Model
 *
 */
class FlexicontentModelType extends FCModelAdmin
{
	/**
	 * Record name, (parent class property), this is used for: naming session data, XML file of class, etc
	 *
	 * @var string
	 */
	protected $name = 'type';

	/**
	 * Record database table
	 *
	 * @var string
	 */
	var $records_dbtbl = 'flexicontent_types';

	/**
	 * Record jtable name
	 *
	 * @var string
	 */
	var $records_jtable = 'flexicontent_types';

	/**
	 * Column names
	 */
	var $state_col   = 'published';
	var $name_col    = 'name';
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
		 1 => 'FLEXI_ENABLED',
		 0 => 'FLEXI_DISABLED',
		-2 => 'FLEXI_TRASHED',
	);

	/**
	 * Groups of Fields that can be partially present in the form
	 */
	var $mergeableGroups = array('attribs');

	/**
	 * Various record specific properties
	 *
	 */
	// ...

	/**
	 * Constructor
	 *
	 * @since 3.3.0
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		$this->canManage = FlexicontentHelperPerm::getPerm()->CanTypes;
		$this->canCreate = $this->canManage;
	}


	/**
	 * Legacy method to get the record
	 *
	 * @return	object
	 *
	 * @since	1.0
	 */
	public function getType($pk = null)
	{
		return parent::getRecord($pk);
	}


	/**
	 * Method to initialise the record data
	 *
	 * @param   object      $record    The record being initialized
	 * @param   boolean     $initOnly  If true then only a new record will be initialized without running the _afterLoad() method
	 *
	 * @return	boolean	True on success
	 *
	 * @since	1.5
	 */
	protected function _initRecord(&$record = null, $initOnly = false)
	{
		parent::_initRecord($record, $initOnly);

		// Set some new record specific properties, note most properties already have proper values
		// Either the DB default values (set by getTable() method) or the values set by _afterLoad() method
		$record->id							= 0;
		$record->name						= null;  //$this->getName() . ($this->_getLastId() + 1);
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
		return parent::store($data);
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
		parent::preprocessForm($form, $data, $plugins_group);
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

		while ($table->load(array('name' => $title, 'alias' => $alias)))
		{
			$title = StringHelper::increment($title);
			$alias = StringHelper::increment($alias, 'dash');
		}

		return array($title, $alias);
	}


	/**
	 * Method to check if the user can edit the record
	 *
	 * @return	boolean	True on success
	 *
	 * @since	3.2.0
	 */
	public function canEdit($record = null)
	{
		$record  = $record ?: $this->_record;
		$user    = JFactory::getUser();

		return !$record || !$record->id
			? $this->canCreate
			: $this->canManage;
	}


	/**
	 * Method to check if the user can edit record 's state
	 *
	 * @return	boolean	True on success
	 *
	 * @since	3.2.0
	 */
	public function canEditState($record = null)
	{
		$record  = $record ?: $this->_record;
		$user    = JFactory::getUser();

		return $this->canManage;
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
		$record  = $record ?: $this->_record;
		$user    = JFactory::getUser();

		return $this->canManage;
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
		/**
		 * Special handling of some FIELDSETs: e.g. 'attribs/params' and optionally for other fieldsets too, like: 'metadata'
		 * By doing partial merging of these arrays we support having only a sub-set of them inside the form
		 */

		// Get RAW layout field values, validation will follow ...
		$raw_data = JFactory::getApplication()->input->post->get('jform', array(), 'array');
		$data['attribs']['layouts'] = !empty($raw_data['layouts']) ? $raw_data['layouts'] : null;

		// We will use mergeAttributes() instead of bind(), thus fields that are not set will maintain their current DB values,
		$mergeProperties = $this->mergeableGroups;
		$mergeOptions = array(
			'params_fset'  => 'attribs',
			'layout_type'  => 'item',
			'model_names'  => array($this->option => $this->getName()),
			'cssprep_save' => false,
		);
		$this->mergeAttributes($record, $data, $mergeProperties, $mergeOptions);

		// Unset the above handled FIELDSETs from $data, since we selectively merged them above into the RECORD,
		// thus they will not overwrite the respective RECORD's properties during call of JTable::bind()
		foreach($mergeProperties as $prop)
		{
			unset($data[$prop]);
		}

		// Call parent class bind preparation
		parent::_prepareBind($record, $data);
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
		parent::_afterStore($record, $data);

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
	 * @param	object   $record   The record object
	 *
	 * @since	3.2.0
	 */
	protected function _afterLoad($record)
	{
		parent::_afterLoad($record);
	}


	/**
	 * START OF MODEL SPECIFIC METHODS
	 */

	/**
	 * Method to add core field relation to a type
	 *
	 * @return  boolean    True on success
	 *
	 * @since	1.5
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
	 *
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
}
