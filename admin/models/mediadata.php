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
 * FLEXIcontent Component Mediadata Model
 *
 */
class FlexicontentModelMediadata extends FCModelAdmin
{
	/**
	 * Record name, (parent class property), this is used for: naming session data, XML file of class, etc
	 *
	 * @var string
	 */
	protected $name = 'mediadata';

	/**
	 * Record database table
	 *
	 * @var string
	 */
	var $records_dbtbl = 'flexicontent_mediadatas';

	/**
	 * Record jtable name
	 *
	 * @var string
	 */
	var $records_jtable = 'flexicontent_mediadatas';

	/**
	 * Column names
	 */
	var $state_col   = 'state';
	var $name_col    = 'title';
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
	 * @since 1.0
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		$this->canManage = FlexicontentHelperPerm::getPerm()->CanMediadatas;
		$this->canCreate = FlexicontentHelperPerm::getPerm()->CanCreateMediadatas;
	}


	/**
	 * Legacy method to get the record
	 *
	 * @return	object
	 *
	 * @since	1.0
	 */
	public function getMediadata($pk = null)
	{
		return $this->getRecord($pk);
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
		parent::getItem($pk);

		if (!$this->_record->file_id)
		{
			$this->_record->file_id = JFactory::getApplication()->input->getInt('file_id', 0);
		}

		// Attempt to load file data
		if ($this->_record->file_id)
		{
			$query = $this->_db->getQuery(true)
				->select(array('f.*, f.filename AS title', 'ua.name as uploader'))
				->from('#__flexicontent_files AS f')
				->leftJoin('#__users AS ua ON ua.id = f.uploaded_by')
				->where('f.id = ' . (int) $this->_record->file_id);

			$file = $this->_db->setQuery($query)->loadObject();
			$this->_record->file = $file;
			$this->_record->user_id = $file->uploaded_by; 
		}
		else
		{
			$this->_record->file = array('title' => '', 'uploader' => '');
			$this->_record->file = (object) $this->_record->file;
			$this->_record->user_id = 0; 
		}

		return $this->_record;
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
		$record->id					= 0;
		$record->title				= '';
		$record->text				= '';
		$record->state				= 0;
		$record->checked_out		= 0;
		$record->checked_out_time	= '';
		$record->user_id			= 0;

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

		while ($table->load(array('title' => $title)))
		{
			$title = StringHelper::increment($title);
		}

		return array($title, null);
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

		$isOwner = $record && $record->user_id == $user->id;

		return !$record || !$record->id
			? $this->canCreate
			: (($this->canCreate && $isOwner) || $this->canManage);
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
	 * Method to validate the data posted by the submitter
	 *
	 * @param   array     $data  The (already JForm validated) record data
	 *
	 * @return  boolean   true on success, false on failure
	 *
	 * @since   3.3
	 */
	public function submitterValidation($data)
	{
		$user = JFactory::getUser();
		$db   = JFactory::getDbo();

		$mediadata_id   = $data['id'];
		$content_id  = $data['content_id'];
		$mediadata_type = $data['type'];

		$errors = array();

		// Validate title, decode entities, and strip HTML
		$title = flexicontent_html::dataFilter($data['title'], $maxlength=255, 'STRING', 0);

		// Validate email
		$email = $user->id ? $user->email : flexicontent_html::dataFilter($data['email'], $maxlength=255, 'EMAIL', 0);

		// Validate text, decode entities and strip HTML
		$text = flexicontent_html::dataFilter($data['text'], $maxlength=10000, 'STRING', 0);


		/**
		 * Check for validation failures on posted data
		 */

		if (!$content_id)
		{
			$this->setError('Content being mediadataed not given (content_id is zero)');
			return false;
		}

		if (!$email)
		{
			$this->setError('Email is invalid or empty');
			return false;
		}

		if (!$user->id)
		{
			$query = 'SELECT id FROM #__users WHERE email = ' . $db->Quote($email);
			$submitter = $db->setQuery($query)->loadObject();

			if ($submitter)
			{
				$this->setError('Please login');
				return false;
			}
		}

		if (!$text)
		{
			$this->setError('Text is invalid or empty');
			return false;
		}

		if ($mediadata_type !== 'item')
		{
			$this->setError('mediadata_type <> item is not yet supported');
			return false;
		}

		// Return the further validated data
		return $data;
	}
}
