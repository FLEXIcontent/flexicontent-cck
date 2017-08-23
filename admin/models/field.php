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
require_once('base.php');

/**
 * FLEXIcontent Component Field Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelField extends FCModelAdmin
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
	var $field_type = null;
	var $plugin_name = null;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();
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
		return parent::getRecord($pk);
	}


	/**
	 * Method to initialise the record data
	 *
	 * @access	protected
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	protected function _initRecord(&$record = null, $initOnly = false)
	{
		parent::_initRecord($record, $initOnly);

		// Set some new record specific properties, note most properties already have proper values
		// Either the DB default values (set by getTable() method) or the values set by _afterLoad() method
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
		$data_obj = $data && is_array($data) ? (object) $data : $data;

		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');

		// Initialise variables.
		$client = JApplicationHelper::getClientInfo(0);

		// Get plugin name from field type
		$plugin_name = $data_obj
			? ($data_obj->iscore ? 'core' : $data_obj->field_type)
			: ($this->plugin_name ?: 'text');
		$plugin_name = JFactory::getApplication()->input->get('field_type', $plugin_name, 'cmd');

		// Try to load plugin file: /plugins/folder/element/element.xml
		$plugin_path = JPATH_PLUGINS . DS . 'flexicontent_fields' . DS . $plugin_name . DS . $plugin_name . '.xml';
		if (!JFile::exists($plugin_path))
		{
			throw new Exception('Error field XML file for field type: - ' . $this->field_type . '- was not found');
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
	 * @access	public
	 * @return	boolean	True on success
	 * @since	3.2.0
	 */
	function canEdit($record=null)
	{
		$record = $record ?: $this->_record;
		$user = JFactory::getUser();

		return !$record || !$record->id
			? $user->authorise('flexicontent.createfield', 'com_flexicontent')
			: $user->authorise('flexicontent.editfield', 'com_flexicontent.field.' . $record->id);
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
		$user = JFactory::getUser();

		return $user->authorise('flexicontent.publishfield', 'com_flexicontent.field.' . $record->id);
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
		$user = JFactory::getUser();

		return $user->authorise('flexicontent.deletefield', 'com_flexicontent.field.' . $record->id);
	}


	/**
	 * Method to do some record / data preprocessing before call JTable::bind()
	 *
	 * Note. Typically called inside this MODEL 's store()
	 *
	 * @since	3.2.0
	 */
	protected function _prepareBind($record, & $data)
	{
		parent::_prepareBind($record, $data);

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

		if (!$data['id'] && $data['iscore'])
		{
			$this->setError('Field\'s "iscore" property is ON, but creating new fields as CORE is not allowed');
			return false;
		}
	}


	/**
	 * Method to do some work after record has been stored
	 *
	 * Note. Typically called inside this MODEL 's store()
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

		// Needed during preprocessForm to load correct XML file
		$this->field_type  = $record->field_type;
		$this->plugin_name = $record->iscore ? 'core' : $record->field_type;
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
	 * Method to the Field Type for a given or for current field ID
	 * 
	 * @return array
	 * @since 3.2
	 */
	function getFieldType($pk = 0)
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
}
