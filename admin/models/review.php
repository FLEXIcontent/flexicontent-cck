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
 * FLEXIcontent Component Review Model
 *
 */
class FlexicontentModelReview extends FCModelAdmin
{
	/**
	 * Record name, (parent class property), this is used for: naming session data, XML file of class, etc
	 *
	 * @var string
	 */
	protected $name = 'review';

	/**
	 * Record database table
	 *
	 * @var string
	 */
	var $records_dbtbl = 'flexicontent_reviews';

	/**
	 * Record jtable name
	 *
	 * @var string
	 */
	var $records_jtable = 'flexicontent_reviews';

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

		$this->canManage = FlexicontentHelperPerm::getPerm()->CanReviews;
		$this->canCreate = FlexicontentHelperPerm::getPerm()->CanCreateReviews;
	}


	/**
	 * Legacy method to get the record
	 *
	 * @return	object
	 *
	 * @since	1.0
	 */
	public function getReview($pk = null)
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
		$record->title					= '';
		$record->text						= '';
		$record->state					= 0;
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
	public function canEdit($record = null)
	{
		$record  = $record ?: $this->_record;
		$user    = JFactory::getUser();

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
	 * Method to validate the data posted by the reviewer
	 *
	 * @param   array     $data  The (already JForm validated) record data
	 *
	 * @return  boolean   true on success, false on failure
	 *
	 * @since   3.3
	 */
	public function reviewerValidation($data)
	{
		$user = JFactory::getUser();
		$db   = JFactory::getDbo();

		$review_id   = $data['id'];
		$content_id  = $data['content_id'];
		$review_type = $data['type'];

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
			$this->setError('Content being reviewed not given (content_id is zero)');
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
			$reviewer = $db->setQuery($query)->loadObject();

			if ($reviewer)
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

		if ($review_type !== 'item')
		{
			$this->setError('review_type <> item is not yet supported');
			return false;
		}

		// Return the further validated data
		return $data;
	}


	/**
	 * Method to toggle approved flag of a review
	 *
	 * @param		array			$cid          Array of record ids to set to a new state
	 * @param		string    $approved     The new state
	 *
	 * @return	boolean	True on success
	 *
	 * @since	3.3.0
	 */
	public function approved($cid = array(), $value = 0, $cleanCache = true)
	{
		$cid = (array) $cid;
		$cid = ArrayHelper::toInteger($cid);
		$affected = 0;

		if (count($cid))
		{
			$user     = JFactory::getUser();
			$cid_list = implode(',', $cid);

			$query = $this->_db->getQuery(true)
				->update('#__' . $this->records_dbtbl)
				->set('approved = ' . (int) $value)
				->where('id IN (' . $cid_list . ')')
				->where('(checked_out = 0 OR checked_out = ' . (int) $user->get('id') . ')');

			$this->_db->setQuery($query)->execute();

			// Get affected records, non records may have been locked by another user
			$affected = $this->_db->getAffectedRows();

			if ($cleanCache)
			{
				$this->cleanCache(null, 0);
				$this->cleanCache(null, 1);
			}
		}

		return $affected;
	}
}
