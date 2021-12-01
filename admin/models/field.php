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
 * FLEXIcontent Component Field Model
 *
 */
class FlexicontentModelField extends FCModelAdmin
{
	/**
	 * Record name, (parent class property), this is used for: naming session data, XML file of class, etc
	 *
	 * @var string
	 */
	protected $name = 'field';

	/**
	 * Record database table
	 *
	 * @var string
	 */
	var $records_dbtbl = 'flexicontent_fields';

	/**
	 * Record jtable name
	 *
	 * @var string
	 */
	var $records_jtable = 'flexicontent_fields';

	/**
	 * Column names
	 */
	var $state_col   = 'published';
	var $name_col    = 'label';
	var $parent_col  = null;//'parent_id';

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
	 * Use language associations
	 *
	 * @var string
	 */
	var $associations_context = false;

	/**
	 * Various record specific properties
	 *
	 */
	protected $forced_field_type = null;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		$jinput = JFactory::getApplication()->input;
		$filter = JFilterInput::getInstance();

		$data = $jinput->post->get('jform', array(), 'array');

		// Force new field_type, so that type-specific parameters will be validated according to the new field type
		if (isset($data['field_type']))
		{
			$this->setFieldType($data['field_type']);
		}

		$this->canManage    = FlexicontentHelperPerm::getPerm()->CanFields;
		$this->canCreate    = FlexicontentHelperPerm::getPerm()->CanAddField;
	}


	/**
	 * Legacy method to get the record
	 *
	 * @return	object
	 *
	 * @since	1.0
	 */
	public function getField($pk = null)
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
		$record->field_type			= 'text';
		$record->name						= null;  //$this->getName() . ($this->_getLastId() + 1);
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
	 * Method to set a new field type. The type will be validated if it exists during JForm preprocessing
	 *
	 * @param   string  $field_type  The forced field type
	 *
	 * @since   3.3.0
	 */
	public function setFieldType($field_type)
	{
		$this->forced_field_type = JFilterInput::getInstance()->clean($field_type, 'CMD');
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
		$data_obj = $data && is_array($data) ? (object) $data : $data;

		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');

		// Initialise variables.
		$client = JApplicationHelper::getClientInfo(0);


		/**
		 * Get plugin name from field type
		 * Use overridden field type if this was set, e.g. switching current to a new field type
		 */
		$plugin_name = $data_obj
			? (!empty($data_obj->iscore) ? 'core' : $data_obj->field_type)
			: 'text';
		$plugin_name = $this->forced_field_type ?: $plugin_name;


		/**
		 * Try to load plugin file: /plugins/folder/element/element.xml
		 */
		$plugin_path = JPath::clean(JPATH_PLUGINS . DS . 'flexicontent_fields' . DS . $plugin_name . DS . $plugin_name . '.xml');

		if (!JFile::exists($plugin_path))
		{
			throw new Exception('Error field XML file for field type: - ' . $plugin_name . '- was not found');
		}


		/**
		 * Set new field_type, this is needed e.g. after for form reload due to some error
		 */

		if (empty($this->_record->iscore))
		{
			$this->_record->field_type = $plugin_name;
		}

		if ($data_obj && empty($data_obj->iscore))
		{
			$data_obj->field_type = $plugin_name;
		}


		/**
		 * Do not allow changing some properties
		 */

		if (!empty($this->_record->iscore))
		{
			$form->setFieldAttribute('name', 'readonly', 'true');
			$form->setFieldAttribute('name', 'filter', 'unset');
		}

		$form->setFieldAttribute('iscore', 'readonly', 'true');
		$form->setFieldAttribute('iscore', 'filter', 'unset');

		if ($this->_record->id > 0 && $this->_record->id < 7)
		{
			$form->setFieldAttribute('published', 'readonly', 'true');
			$form->setFieldAttribute('published', 'filter', 'unset');
		}


		// ***
		// *** Load extra XML files into the JForm, these will be used e.g. during validation
		// ***

		// We will load the form's XML file into a string to be able to manipulate it, before it is loaded by the JForm
		$xml_string = str_replace(' type="radio"', ' type="fcradio"', file_get_contents($plugin_path));
		$xml = simplexml_load_string($xml_string);  //simplexml_load_file($plugin_path);
		if (!$xml)
		{
			throw new Exception(JText::_('JERROR_LOADFILE_FAILED'));
		}

		// Load XML file into the form
		$form->load($xml, false, '//config');


		// ***
		// *** Get the help data from the XML file if present.
		// ***

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

		while ($table->load(array('name' => $alias, 'label' => $title)))
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
	public function canEdit($record = null, $user = null)
	{
		if ($user)
		{
			throw new Exception(__FUNCTION__ . '(): Error model does not support checking ACL of specific user', 500);
		}

		$record  = $record ?: $this->_record;
		$user    = $user ?: JFactory::getUser();

		return !$record || !$record->id
			? $this->canCreate
			: $user->authorise('flexicontent.editfield', 'com_flexicontent.field.' . $record->id);
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

		$record  = $record ?: $this->_record;
		$user    = $user ?: JFactory::getUser();

		return $record->id < 7
			?	false
			: $user->authorise('flexicontent.publishfield', 'com_flexicontent.field.' . $record->id);
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

		return $record->id < 7
			?	false
			: $user->authorise('flexicontent.deletefield', 'com_flexicontent.field.' . $record->id);
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
		 * Support for 'dirty' field properties
		 */
		if ($data['id'])
		{
			if ($record->issearch==-1 || $record->issearch==2) unset($data['issearch']);  // Already dirty
			elseif (@ $data['issearch']==0 && $record->issearch==1) $data['issearch'] = -1; // Becomes dirty OFF
			elseif (@ $data['issearch']==1 && $record->issearch==0) $data['issearch'] = 2;  // Becomes dirty ON

			if ($record->isadvsearch==-1 || $record->isadvsearch==2) unset($data['isadvsearch']);  // Already dirty
			elseif (@ $data['isadvsearch']==0 && $record->isadvsearch==1) $data['isadvsearch'] = -1; // Becomes dirty OFF
			elseif (@ $data['isadvsearch']==1 && $record->isadvsearch==0) $data['isadvsearch'] = 2;  // Becomes dirty ON

			if ($record->isadvfilter==-1 || $record->isadvfilter==2) unset($data['isadvfilter']);  // Already dirty
			elseif (@ $data['isadvfilter']==0 && $record->isadvfilter==1) $data['isadvfilter'] = -1; // Becomes dirty OFF
			elseif (@ $data['isadvfilter']==1 && $record->isadvfilter==0) $data['isadvfilter'] = 2;  // Becomes dirty ON

			// FORCE dirty OFF, if field is being unpublished -and- is not already normal OFF
			if (isset($data['published']) && $data['published']==0 && $record->published==1)
			{
				if ($record->issearch!=0) $data['issearch'] = -1;
				if ($record->isadvsearch!=0) $data['isadvsearch'] = -1;
				if ($record->isadvfilter!=0) $data['isadvfilter'] = -1;
			}
		}

		/**
		 * Positions are always posted, otherwise they must be cleared
		 */
		if (!isset($data['positions']))
		{
			$data['positions'] = '';
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
	 * @param	object   $record   The record object
	 *
	 * @since	3.2.0
	 */
	protected function _afterLoad($record)
	{
		parent::_afterLoad($record);

		// Record was not found / not created, nothing to do
		if (!$record)
		{
			return;
		}

		// Convert field positions to an array
		if (!is_array($record->positions))
		{
			$record->positions = explode("\n", $record->positions);
		}

		// Load type assigments (an array of type IDs)
		$record->tid = $this->getFieldType($record->id);
	}


	/**
	 * START OF MODEL SPECIFIC METHODS
	 */


	/**
	 * Method to assign types to a field
	 *
	 * @return  boolean    True on success
	 *
	 * @since	1.0
	 */
	protected function _assignTypesToField($types)
	{
		$field = $this->_record;

		/**
		 * Override 'types' for core fields, since the core field must be assigned to all types
		 * but alllow core fields 'voting', 'favourites, to selectively assigned to types
		 */
		if ($field->iscore && !in_array($field->field_type, array('voting', 'favourites'), true))
		{
			$query = $this->_db->getQuery(true)
				->select('id')
				->from('#__flexicontent_types');

			$types = $this->_db->setQuery($query)->loadColumn();
		}

		/**
		 * Store field to types relations
		 * Try to avoid unneeded deletion and insertions
		 */

		// First, delete existing types assignments no longer used by the field
		$query = $this->_db->getQuery(true)
			->delete('#__flexicontent_fields_type_relations')
			->where('field_id = ' . (int) $field->id);

		if (!empty($types))
		{
			$query->where('type_id NOT IN (' . implode(', ', $types) . ')');
		}

		$this->_db->setQuery($query)->execute();

		// Second, find type assignments of the field that did not changed
		$query = $this->_db->getQuery(true)
			->select('type_id')
			->from('#__flexicontent_fields_type_relations')
			->where('field_id = ' . (int) $field->id);

		$used = $this->_db->setQuery($query)->loadColumn();

		// Third, insert only the new records
		foreach ($types as $type)
		{
			if (!in_array($type, $used))
			{
				// Get last position of each field in each type
				$query = $this->_db->getQuery(true)
					->select('MAX(ordering) as ordering')
					->from('#__flexicontent_fields_type_relations')
					->where('type_id = ' . (int) $type);

				$ordering = $this->_db->setQuery($query)->loadResult();

				// Insert new type assignment using the next available ordering
				$ordering += 1;

				$query = $this->_db->getQuery(true)
					->insert('#__flexicontent_fields_type_relations')
					->columns(array(
						$this->_db->quoteName('field_id'),
						$this->_db->quoteName('type_id'),
						$this->_db->quoteName('ordering')
					))
					->values(
						(int) $field->id . ' , ' .
						(int) $type . ' , ' .
						(int) $ordering
					);

				$this->_db->setQuery($query)->execute();
			}
		}
	}


	/**
	 * Method to get types list
	 *
	 * @return array
	 *
	 * @since 1.5
	 */
	public function getTypeslist ( $type_ids=false, $check_perms = false, $published=false )
	{
		return flexicontent_html::getTypesList( $type_ids, $check_perms, $published);
	}


	/**
	 * Method to the Field Type for a given or for current field ID
	 *
	 * @return array
	 *
	 * @since 3.2
	 */
	public function getFieldType($pk = 0)
	{
		$pk = $pk ?: (int) $this->_id;

		if (!$pk)
		{
			return array();
		}

		$query = $this->_db->getQuery(true)
			->select('DISTINCT type_id')
			->from('#__flexicontent_fields_type_relations')
			->where('field_id = ' . (int) $pk);

		return $this->_db->setQuery($query)->loadColumn();
	}
}
