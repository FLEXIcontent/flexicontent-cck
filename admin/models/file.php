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
 * FLEXIcontent Component File Model
 *
 */
class FlexicontentModelFile extends FCModelAdmin
{
	/**
	 * Record name, (parent class property), this is used for: naming session data, XML file of class, etc
	 *
	 * @var string
	 */
	protected $name = 'file';

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
	 * Context to use for registering (language) associations
	 *
	 * @var string
	 */
	var $associations_context = false;

	/**
	 * Array of supported state conditions of the record
	 */
	var $supported_conditions = array(
		 1 => 'FLEXI_PUBLISHED',
		 0 => 'FLEXI_UNPUBLISHED',
		-2 => 'FLEXI_TRASHED',
	);

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

		$this->canManage = FlexicontentHelperPerm::getPerm()->CanFiles;
	}


	/**
	 * Legacy method to get the record
	 *
	 * @return	object
	 *
	 * @since	1.0
	 */
	public function getFile($pk = null)
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

		while ($table->load(array('filename' => $title)))
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
		$record  = $record ?: $this->_record;
		$user    = $user ?: JFactory::getUser();
		$isOwner = $record && $user->id && $record->uploaded_by == $user->id;

		if ($record->id && $record->estorage_fieldid < 0)
		{
			JFactory::getApplication()->enqueueMessage(JText::_( 'File is being moved to external storage, please edit later' ), 'warning');
			return false;
		}

		$canupload = $user->authorise('flexicontent.uploadfiles', 'com_flexicontent');
		$canedit = $user->authorise('flexicontent.editfile', 'com_flexicontent');
		$caneditown = $user->authorise('flexicontent.editownfile', 'com_flexicontent') && $isOwner;
		return !$record || !$record->id
			? $canupload
			: $canedit || $caneditown;
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
		$record  = $record ?: $this->_record;
		$user    = $user ?: JFactory::getUser();
		$isOwner = $record && $user->id && $record->uploaded_by == $user->id;

		$canpublish = $user->authorise('flexicontent.publishfile', 'com_flexicontent');
		$canpublishown = $user->authorise('flexicontent.publishownfile', 'com_flexicontent') && $isOwner;
		return !$record || !$record->id
			? false
			: $canpublish || $canpublishown;
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
		$isOwner = $record && $user->id && $record->uploaded_by == $user->id;

		$candelete = $user->authorise('flexicontent.deletefile', 'com_flexicontent');
		$candeleteown = $user->authorise('flexicontent.deleteownfile', 'com_flexicontent') && $isOwner;
		return !$record || !$record->id
			? false
			: $candelete || $candeleteown;
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
	 * Returns the size of a file without downloading it, or -1 if the file size could not be determined.
	 *
	 * @param $url - The location of the remote file to download. Cannot be null or empty.
	 *
	 * @return The size of the file referenced by $url,
	 * or -1 if the size could not be determined
	 * or -999 if there was an error
	 */
	function get_file_size_from_url($url, $retry = true)
	{
		$original_url = $url;
		$retry = $retry === true ? 6 : 0;

		// clear last error
		$ignore_last_error = error_get_last();

		try {
			$headers = array('Location' => $url);

			// Follow the Location headers until the actual file URL is known
			while (isset($headers['Location']))
			{
				$url = is_array($headers['Location'])
					? end($headers['Location'])
					: $headers['Location'];

				$headers = @ get_headers($url, 1);

				// Check for get headers failing to execute
				if ($headers === false)
				{
					$error = error_get_last();

					$error_message = is_array($error) && isset($error['message'])
						? $error['message']
						: 'Error retrieving headers of URL';
					$this->setError($error_message);

					return -999;
				}

				// Check for bad response from server, e.g. not found 404 , or 403 no access
				$n = 0;
				while(isset($headers[$n]))
				{
					$code = (int) substr($headers[$n], 9, 3);
					if ($code < 200 || $code >= 400 )
					{
						$this->setError($headers[$n]);
						return -999;
					}
					$n++;
				}
			}
		}

		catch (RuntimeException $e) {
			$this->setError($e->getMessage());
			return -999;  // indicate a fatal error
		}

		// Work-around with content length missing during 1st try, just retry once more
		if (!isset($headers["Content-Length"]) && $retry)
		{
			return $this->get_file_size_from_url($original_url, --$retry);
		}

		$headers["Content-Length"] = is_array($headers["Content-Length"]) ? end($headers["Content-Length"]) : $headers["Content-Length"];

		// Get file size, -1 indicates that the size could not be determined
		return isset($headers["Content-Length"])
			? $headers["Content-Length"]
			: -1;
	}
}
