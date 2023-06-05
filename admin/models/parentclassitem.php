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

require_once('base/base.php');

/**
 * FLEXIcontent Component (Common) Item Model (FrontEnd / BackEnd)
 *
 */
class ParentClassItem extends FCModelAdmin
{
	/**
	 * Record name, (parent class property), this is used for: naming session data, XML file of class, etc
	 *
	 * @var string
	 */
	protected $name = 'item';

	/**
	 * Record database table
	 *
	 * @var string
	 */
	var $records_dbtbl = 'content';

	/**
	 * Record jtable name
	 *
	 * @var string
	 */
	var $records_jtable = 'flexicontent_items';

	/**
	 * Column names
	 */
	var $state_col   = 'state';
	var $name_col    = 'title';
	var $parent_col  = 'catid';

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
	var $events_context = 'com_content.article';

	/**
	 * Record's type alias string
	 *
	 * @var        string
	 */
	public $type_alias = 'com_content.article';

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
	var $associations_context = 'com_content.item';


	/**
	 * Array of supported state conditions of the record
	 */
	var $supported_conditions = array(
		 1 => 'FLEXI_PUBLISHED',
		 0 => 'FLEXI_UNPUBLISHED',
		-5 => 'FLEXI_IN_PROGRESS',
		-3 => 'FLEXI_PENDING',
		-4 => 'FLEXI_TO_WRITE',
		 2 => 'FLEXI_ARCHIVED',
		-2 => 'FLEXI_TRASHED'
	);

	/**
	 * Groups of Fields that can be partially present in the form
	 */
	var $mergeableGroups = array('attribs', 'metadata');

	/**
	 * Various record specific properties
	 *
	 */

	/**
	 * component + type parameters
	 *
	 * @var object
	 */
	var $_cparams = null;

	/**
	 * Item current category id (currently used for FRONTEND only)
	 *
	 * @var int
	 */
	var $_cid = null;

	/**
	 * Template configuration name (layout)
	 *
	 * @var int
	 */
	var $_ilayout = null;

	/**
	 * Item 's type or type via URL variable for new items
	 *
	 * @var int
	 */
	var $_typeid = null;

	/**
	 * Item version of loaded data
	 *
	 * @var int
	 */
	var $_version = null;

	/**
	 * Associated item translations
	 *
	 * @var array
	 */
	var $_translations = null;

	/**
	 * Constructor
	 *
	 * @since 3.3.0
	 */
	public function __construct($config = array())
	{
		if (JFactory::getApplication()->isClient('site'))
		{
			$this->record_keys = array('id');
		}

		$this->debug_tags = false;
		$this->use_jtable_publishing = true;

		parent::__construct($config);

		$jinput = JFactory::getApplication()->input;

		// Set current category ID and current type ID via URL variables (only for new items)
		$currcatid = $this->_id
			? 0  // existing item, keep current main category
			: ($jinput->get('catid', 0, 'int') ? $jinput->get('catid', 0, 'int') : $jinput->get('cid', 0, 'int'));
		$typeid = $this->_id
			? 0  // existing item, keep current type
			: $jinput->get('typeid', 0, 'int');

		// For new item, try to use posted data
		if (!$this->_id && (!$currcatid || !$typeid))
		{
			$data = $jinput->get('jform', array(), 'array');

			$currcatid = !$currcatid && isset($data['catid'])
				? (int) $data['catid']
				: $currcatid;
			$typeid = !$typeid && isset($data['type_id'])
				? (int) $data['type_id']
				: $typeid;
		}

		$this->setId($this->_id, $currcatid, $typeid, null);
	}


	/**
	 * Method to set the identifier
	 *
	 * @param		int	    $id        record identifier
	 * @param		int	    $currcatid record's current category
	 * @param		int	    $typeid    record's type
	 * @param		string  $ilayout   record's item layout
	 *
	 * @since	3.3.0
	 */
	function setId($id, $currcatid = null, $typeid = null, $ilayout = null)
	{
		// A cache of categories assigned to every item
		static $item_cids = array();

		// Set record id and wipe data, if setting a different ID
		if ($this->_id != $id)
		{
			$this->_id = (int) $id;
			$this->_record = null;
			$this->_version = null;
			$this->_cparams = null;
		}

		// Same ID if not setting custom category / type / layout then return
		elseif ($currcatid === null && $typeid === null && $ilayout === null)
		{
			return;
		}

		// Set current category and verify that item is assigned to this category, (SECURITY concern)
		$this->_cid = $currcatid !== null
			? (int) $currcatid
			: (int) $this->_cid;

		// Verify current category is assigned
		if ($this->_id)
		{
			if (!isset($item_cids[$this->_id]))
			{
				$query = 'SELECT catid '
					. ' FROM #__flexicontent_cats_item_relations '
					. ' WHERE itemid = ' . (int) $this->_id;

				$item_cids[$this->_id] = $this->_db->setQuery($query)->loadObjectList('catid');
			}

			if (!isset($item_cids[$this->_id][$this->_cid]))
			{
				$this->_cid = 0;
			}
		}

		// Get main category as current category fallback
		if (!$this->_cid)
		{
			$query = 'SELECT catid '
				. ' FROM #__content'
				. ' WHERE id = ' . (int) $this->_id;
			$this->_cid = $this->_db->setQuery($query)->loadResult();
		}

		// Set item layout
		$this->_ilayout = $ilayout;

		// Set item type, then, -for new item- verify item type exists, -for existing item- its current type will be forced (SECURITY concern)
		$this->_typeid = $typeid !== null
			? (int) $typeid
			: (int) $this->_typeid;

		// Verify item's type. This method verify new item type exists or gets item type for existing item
		if ($this->_id || $this->_typeid)
		{
			// For new items if item type is not found, then an empty type is return that has ZERO id (=item type not set)
			$this->_typeid = $this->getItemType()->id;
		}

		// Recalculate if needed, component + type parameters
		$this->getComponentTypeParams();
	}


	/**
	 * Method to get component + type parameters these are enough for the backend,
	 * also they are needed before frontend view's FULL item parameters are created
	 *
	 * @access	public
	 * @return	int item identifier
	 */
	function getComponentTypeParams($forced_typeid = 0)
	{
		// Calculate component + type parameters
		static $CTparams = array();
		if (isset($CTparams[$this->_typeid]))
		{
			$this->_cparams = $CTparams[$this->_typeid];
			return $this->_cparams;
		}

		// Get component parameters
		$params  = new JRegistry();
		$cparams = JComponentHelper::getParams('com_flexicontent');
		$params->merge($cparams);

		// Merge into them the type parameters, *(type was set/verified above)
		if ($this->_typeid || $forced_typeid)
		{
			$tparams = $this->getTypeparams($forced_typeid);
			$tparams = $this->_new_JRegistry($tparams);
			$params->merge($tparams);
		}

		// Return forced parameters without setting them into the object
		if ($forced_typeid)
		{
			return $params;
		}

		// Set and return component + type parameters
		$this->_cparams = $CTparams[$this->_typeid] = $params;
		return $this->_cparams;
	}


	/**
	 * Method to get item's type id
	 *
	 * @access	public
	 * @return	int item identifier
	 */
	function getTypeId()
	{
		return $this->_typeid;
	}


	/**
	 * Method to set & override item's layout
	 *
	 * @access	public
	 * @param	int item identifier
	 */
	function setItemLayout($name=null)
	{
		$this->_ilayout = $name;
	}


	/**
	 * Method to get a record.
	 *
	 * @param	  integer   $pk                 An optional id of the object to get, otherwise the id from the model state is used.
	 * @param	  boolean   $check_view_access  Whether to check current user access of the item ... this includes CATEGORY ACCESS + TYPE ACCESS of the item
	 * @param	  boolean   $no_cache           Force reloading the item, e.g. useful if item may have changed in the DB, or if current object may have been modified
	 * @param	  boolean   $force_version      Force loading data of specific item version
	 *
	 * @return	mixed 	Record data object on success, false on failure.
	 *
	 * @since	1.6
	 */
	public function getItem($pk = null, $check_view_access = true, $no_cache = false, $force_version = 0)
	{
		$app     = JFactory::getApplication();
		$cparams = $this->_cparams;
		$jinput  = $app->input;

		// View access done is meant only for FRONTEND !!! ... force it to false
		if ($app->isClient('administrator'))
		{
			$check_view_access = false;
		}

		// Initialise and set primary if it was not given already
		$pk = !empty($pk) ? $pk : $this->_id;
		$pk = !empty($pk) ? $pk : (int) $this->getState($this->getName().'.id');

		/**
		 * Set new item id, clearing item data, ONLY IF DIFFERENT than existing primary key
		 * But do not reset typeid, etc for new item creation
		 */
		if ($pk != $this->_id || $no_cache)
		{
			$pk
				? $this->setId($pk, 0, 0, null)
				: $this->setId($pk);
		}

		// --. Try to load existing item ... ZERO $force_version means unversioned data or maintain currently loaded version
		if ($pk && $this->_loadRecord($pk, $no_cache, $force_version))
		{
			/**
			 * Successfully loaded existing item, do some extra manipulation of the loaded item ...
			 */

			// Load item parameters with heritage
			$this->_loadItemParams($no_cache);

			// Check item viewing access
			if ($check_view_access)
			{
				$this->_check_viewing_access($force_version);
			}
		}

		// --. Failed to load existing item, or check_view_access indicates not to create a new item object
		elseif ($pk || $check_view_access === 2)
		{
			if ($app->isClient('site'))
			{
				$msg = $pk ?
					JText::sprintf('FLEXI_CONTENT_UNAVAILABLE_ITEM_NOT_FOUND', $pk) :   // ID is set, indicate that it was not found
					JText::_( 'FLEXI_REQUESTED_PAGE_COULD_NOT_BE_FOUND' );  // ID is not set propably some bad URL so give a more general message
			}
			else
			{
				$msg = JText::_('Item not found') . ': ' . ($pk ?: '');
			}

			// In case of checking view access ONLY THEN throw a non found exception
			if ($check_view_access)
			{
				throw new Exception($msg, 404);
			}

			// Return to caller indication that loading failed, set Error Message if not already set
			else
			{
				if (!$this->getError())
				{
					$this->setError($msg);
				}

				return false;
			}
		}

		// --. Initialize new item, if not already initialized, currently this succeeds always
		elseif (!$this->_record)
		{
			$this->_initRecord();

			// Load item parameters with heritage, (SUBMIT ITEM FORM)
			$this->_loadItemParams($no_cache);

			// Update item's default language now that all parameters have been loaded
			$this->_record->language = $this->getDefaultItemLanguage($this->_record);
		}

		// Verify item's type. This method verify new item type exists or gets item type for existing item
		$this->_typeid = $this->_record->type_id = $this->getItemType()->id;

		// Recalculate if needed, component + type parameters
		$this->getComponentTypeParams();

		// This is used in places that item data need to be retrieved again because item object was not given
		global $fc_view_item;
		$fc_view_item = $this->_record;

		return $this->_record;
	}


	/**
	 * Method to load record data
	 *
	 * @return	boolean	True on success
	 *
	 * @since	1.0
	 */
	protected function _loadRecord($pk = null, $no_cache=false, $force_version=0)
	{
		//echo 'force_version: '.$force_version ."<br/>";
		//echo "<pre>"; debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS); echo "</pre>";

		// This is ITEM cache. NOTE: only unversioned items are cached
		static $items = array();
		global $fc_list_items;    // Global item cache (unversioned items)
		static $unapproved_version_notice;

		// If PK was provided and it is also not a name, then treat it as a primary key value
		$pk = $pk ? (int) $pk : (int) $this->_id;

		// Only try to load existing item
		if (!$pk)
		{
			return false;
		}

		// -- To load a different item:
		// a. use member function function setId($id, $currcatid=0) to change primary key and then call getItem()
		// b. call getItem($pk, $check_view_access=true) passing the item id and maybe also disabling read access checkings, to avoid unwanted messages/errors

		// Clear item to make sure it is reloaded
		if ( $no_cache )
		{
			//echo "********************************<br/>\n CLEARING CACHE of ALREADY loaded item: {$pk}<br/> ********************************<br/><br/><br/>";
			$this->_record = null;
		}

		// Only retrieve item if not already, ZERO $force_version means unversioned data or maintain currently loaded version
		elseif (isset($this->_record) && (!$force_version || $force_version==$this->_version))
		{
			//echo "********************************<br/>\n RETURNING ALREADY loaded item: {$pk}<br/> ********************************<br/><br/><br/>";
			//echo "<pre>"; debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS); echo "</pre>";
			return (boolean) $this->_record;
		}

		$db   = $this->_db;
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		$cparams = $this->_cparams;
		$jinput  = $app->input;
		$task    = $jinput->get('task', false, 'cmd');
		$layout  = $jinput->get('layout', false, 'cmd');
		$view    = $jinput->get('view', false, 'cmd');
		$option  = $jinput->get('option', false, 'cmd');
		$use_versioning = $cparams->get('use_versioning', 1);
		$editjf_translations = $cparams->get('editjf_translations', 0);


		// ***
		// *** Check if loading specific VERSION and generate version related messages
		// ***

		$current_version = (int) FLEXIUtilities::getCurrentVersions($pk, true, $force=true); // Get current item version
		$last_version    = (int) FLEXIUtilities::getLastVersions($pk, true, $force=true);    // Get last version (=latest one saved, highest version id)

		// ***
		// *** Decide the version to load: (a) the one specified or (b) UNversioned data (these should be same as current version data) or (c) the latest one
		// ***

		// Force version to zero (load current version), when not using versioning mode
		if (!$use_versioning)
		{
			$version = 0;
		}

		// Force a specific version
		else if ($force_version)
		{
			$version = (int) $force_version;

			/**
			 * Load latest, but catch cases when we enable versioning mode after an item has been saved in unversioning mode
			 * in these case we will load CURRENT version instead of the default for the item edit form which is the LATEST (for backend/fontend)
			 */

			if ($version === -1)
			{
				//echo "LOADING LATEST: current_version >= last_version : $current_version >= $last_version <br/>";
				$version = ($current_version >= $last_version) ? 0 : $last_version;
			}
			// Force currently active version for negative version passed
			else
			{
				$version = $version > 0 ? $version : 0;
			}
		}

		// Load currently active version
		else
		{
			$version = 0;
		}

		// Check if item is alredy loaded and is of correct version
		if ( $this->_version == $version && isset($this->_record) )
		{
			return (boolean) $this->_record;
		}
		$this->_version = $version; // Set number of loaded version
		//echo 'version: '.$version ."<br/>";

		// Current version number given, the data from the versioning table should be the same as the data from normal tables
		// we do not force $version to ZERO to allow testing the field data of current version from the versioning table
		//if ($version == $current_version) $version = 0;   // Force zero to retrieve unversioned data

		// Check if not loading the current version while we are in edit form, and raise a notice to inform the user
		if ($version && $version != $current_version  && $task=='edit' && $option=='com_flexicontent' && !$unapproved_version_notice)
		{
			$unapproved_version_notice = 1;
			if (!$app->isClient('administrator'))
			{
				$message = (object) array('type'=>'notice', 'showAfterLoad'=>true, 'text'=>
					JText::_('FLEXI_LOADING_UNAPPROVED_VERSION_NOTICE')
				);
			}
			else
			{
				$message = (object) array('type'=>'notice', 'showAfterLoad'=>true, 'text'=>
					JText::_('FLEXI_LOADING_UNAPPROVED_VERSION_NOTICE_ADMIN') . ' :: ' .
					JText::sprintf('FLEXI_LOADED_VERSION_INFO_NOTICE_ADMIN', $version, $current_version)
				);
			}
			$this->registerMessage($message);
		}


		// Only unversioned items are cached, use cache if no specific version was requested
		if ( !$version && isset($items[$pk]) && !$no_cache)
		{
			//echo "********************************<br/>\n RETURNING CACHED item: {$pk}<br/> ********************************<br/><br/><br/>";
			$this->_record = $items[$pk];
			return (boolean) $this->_record;
		}

		//echo "**************************<br/>\n LOADING item id: {$pk}  version:{$this->_version}<br/> **************************<br/><br/><br/>";
		//echo "<pre>"; debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS); echo "</pre>";


		// ***
		// *** TRY TO LOAD ITEM DATA
		// ***
		try
		{
			// ***
			// *** Item Retrieval BACKEND
			// ***
			if ( $app->isClient('administrator') && $view !== 'itemcompare')
			{
				$item   = $this->getTable();
				$result = $item->load($pk);  // try loading existing item data
				if ($result===false)
				{
					$this->setError('Record # ' . $pk . ' not found');
					return false;
				}
			}

			// ***
			// *** Item Retrieval FRONTEND
			// ***
			else
			{
				// Extra access columns for main category and content type (item access will be added as 'access')
				$select_access = 'mc.access as category_access, ty.access as type_access';

				// Access Flags for: content type, main category, item
				$aid_arr = JAccess::getAuthorisedViewLevels($user->get('id'));
				$aid_list = implode(",", $aid_arr);
				$select_access .= ', CASE WHEN ty.access IN (0,'.$aid_list.') THEN 1 ELSE 0 END AS has_type_access';
				$select_access .= ', CASE WHEN mc.access IN (0,'.$aid_list.') THEN 1 ELSE 0 END AS has_mcat_access';
				$select_access .= ', CASE WHEN  i.access IN (0,'.$aid_list.') THEN 1 ELSE 0 END AS has_item_access';

				// SQL date strings, current date and null date
				$sqlNowDateQuoted  = $db->Quote(JFactory::getDate()->toSql());
				$sqlNullDateQuoted = $db->Quote($db->getNullDate());

				// Decide to limit to CURRENT CATEGORY
				$limit_to_cid = $this->_cid ? ' AND rel.catid = '. (int) $this->_cid : ' AND rel.catid = i.catid';

				// Get voting resolution
				$rating_resolution = (int)$this->getVotingResolution();

				// *******************************
				// Initialize and create the query
				// *******************************
				$query = $db->getQuery(true);

				$query->select('i.*, ie.*');                              // Item basic and extended data
				$query->select($select_access);                              // Access Columns and Access Flags for: content type, main category, item
				if ($version) $query->select('ver.version_id');           // Versioned item viewing
				$query->select('c.id AS catid, i.catid as maincatid');    // Current category id and Main category id
				$query->select(
					'c.title AS category_title, c.alias AS category_alias, c.lft,c.rgt, '.  // Current category data
					'mc.title AS maincat_title, mc.alias AS maincat_alias');                // Main category data
				$query->select('ty.name AS typename, ty.alias as typealias');             // Content Type data, and author data
				$query->select('ua.name AS author');                                       // Author data

				// Rating count, Rating & Score
				$query->select('v.rating_count as rating_count, ROUND( v.rating_sum / v.rating_count ) AS rating, ((v.rating_sum / v.rating_count)*'.(100 / $rating_resolution).') as score');

				// Item and Current Category slugs (for URL)
				$query->select('CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug');
				$query->select('CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug');

				// Publication Scheduled / Expired Flags
				$query->select('CASE WHEN i.publish_up is NULL OR i.publish_up = '.$sqlNullDateQuoted.' OR i.publish_up <= '.$sqlNowDateQuoted.' THEN 0 ELSE 1 END as publication_scheduled');
				$query->select('CASE WHEN i.publish_down is NULL OR i.publish_down = '.$sqlNullDateQuoted.' OR i.publish_down >= '.$sqlNowDateQuoted.' THEN 0 ELSE 1 END as publication_expired' );

				if (FLEXI_J40GE)
				{
					$query->select([
						$db->quoteName('wa.stage_id', 'stage_id'),
						$db->quoteName('ws.title', 'stage_title'),
						$db->quoteName('ws.workflow_id', 'workflow_id'),
						$db->quoteName('w.title', 'workflow_title'),
					]);
				}

				// From content table, and extended item table, content type table, user table, rating table, categories relation table
				$query->from('#__content AS i');
				$query->join('LEFT', '#__flexicontent_items_ext AS ie ON ie.item_id = i.id');
				$query->join('LEFT', '#__flexicontent_types AS ty ON (CASE WHEN ie.type_id = 0 THEN ' . (int) $this->_typeid . ' ELSE ie.type_id END) = ty.id');
				$query->join('LEFT', '#__users AS ua on ua.id = i.created_by');
				$query->join('LEFT', '#__content_rating AS v ON i.id = v.content_id');
				$query->join('LEFT', '#__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id' . $limit_to_cid);

				// Join twice on category table, once for current category and once for item's main category
				$query->join('LEFT', '#__categories AS c on c.id = rel.catid');   // All item's categories
				$query->join('LEFT', '#__categories AS mc on mc.id = i.catid');   // Item's main category

				if (FLEXI_J40GE)
				{
					$query->join('LEFT', '#__workflow_associations AS wa ON wa.item_id = i.id AND wa.extension = "com_content.article"');
					$query->join('LEFT', '#__workflow_stages AS ws ON ws.id = wa.stage_id');
					$query->join('LEFT', '#__workflows AS w ON w.id = ws.workflow_id');
				}

				// NOTE: version_id is used by field helper file to load the specified version, the reason for left join here is to verify that the version exists
				if ($version)
				{
					$query->join('LEFT', '#__flexicontent_versions AS ver ON ver.item_id = i.id AND ver.version_id = '. $db->Quote($version) );
				}

				$query->where('i.id = ' . (int) $pk);

				$db->setQuery($query);  //echo $db->replacePrefix($query);

				// Do not translate, this is the Falang overriden method of a database extended Class
				// Try to execute query directly and load the data as an object
				if ( FLEXI_FISH && $task=='edit' && $option=='com_flexicontent' && in_array( $app->getCfg('dbtype') , array('mysqli','mysql') ) )
				{
					$data = flexicontent_db::directQuery($query);
					$data = isset($data[0]) ? $data[0] : null;
				}
				else
				{
					$data = $db->loadObject();  //print_r($data); exit;
				}

				if (!$data)
				{
					$this->setError('Record # ' . $pk . ' not found');
					return false;
				}

				if ($version && !$data->version_id)
				{
					$app->enqueueMessage(JText::sprintf('NOTICE: Requested item version %d was not found, loaded currently active version', $version), 'notice');
				}

				$item = & $data;
			}

			/**
			 * Make sure item has workflow assigned
			 */
			if (FLEXI_J40GE && $item->stage_id === null)
			{
				flexicontent_db::assign_default_WF($item->id, $item);
			}

			// Catch case that current category ID has been changed since last checked !!
			if (!$item->catid)
			{
				$item->catid = $item->maincatid;
			}

			// Retrieve voting information
			$votes = $this->getVotes();
			$item->vote = isset($votes[$this->_id]) ? $votes[$this->_id] : null;

			// Use configured type if existing item has no type
			$this->_typeid = $item->type_id ?: $this->_typeid;
			$item->type_id = $this->_typeid;
			$this->getComponentTypeParams();

			// -- Create the description field called 'text' by appending introtext + readmore + fulltext
			$item->text = $item->introtext;
			$item->text .= StringHelper::strlen( StringHelper::trim($item->fulltext) ) ? '<hr id="system-readmore" />' . $item->fulltext : "";

			//echo "<br/>Current version (Frontend Active): " . $item->version;
			//echo "<br/>Version to load: ".$version;
			//echo "<br/><b> *** db title:</b> ".$item->title;
			//echo "<br/><b> *** db text:</b> ".$item->text;
			//echo "<pre>*** item data: "; print_r($item); echo "</pre>"; exit;

			// Load associated content items
			$useAssocs = $this->useAssociations();

			if ($useAssocs)
			{
				$item->associations = array();

				if ($item->id != null)
				{
					$associations = JLanguageAssociations::getAssociations('com_content', '#__content', 'com_content.item', $item->id);

					foreach ($associations as $tag => $association)
					{
						$item->associations[$tag] = $association->id;
					}
					$item->associations = ArrayHelper::toInteger($item->associations);
				}
			}

			// ***
			// *** Retrieve all active site languages, and create empty item translation objects for each of them
			// ***
			$nn_content_tbl = 'falang_content';

			if ( FLEXI_FISH )
			{
				$site_languages = FLEXIUtilities::getlanguageslist();
				$item_translations = new stdClass();
				foreach($site_languages as $lang_id => $lang_data)
				{
					if ( !$lang_id && $item->language!='*' ) continue;
					$lang_data->fields = new stdClass();
					$item_translations->{$lang_id} = $lang_data;
				}
			}

			// ***
			// *** Retrieve and prepare Falang data
			// ***

			if ( FLEXI_FISH && $task=='edit' && $option=='com_flexicontent' )
			{
				// -- Try to retrieve all Falang data for the current item
				$query = 'SELECT jfc.language_id, jfc.reference_field, jfc.value, jfc.published'
					. ' FROM #__' . $nn_content_tbl . ' AS jfc '
					. ' WHERE jfc.reference_table = ' . $db->Quote($this->records_dbtbl) . ' AND jfc.reference_id = ' . (int) $pk
					;
				$translated_fields = $db->setQuery($query)->loadObjectList();

				if ( $editjf_translations == 0 && $translated_fields )  // 1:disable without warning about found translations
				{
					$app->enqueueMessage(
						'3rd party Falang translations detected for current item. ' .
						'You can either enable editing them or disable this message in FLEXIcontent component configuration'
					, 'message');
				}

				else if ( $editjf_translations == 2 )
				{
					// -- Parse translation data according to their language
					if ( $translated_fields )
					{
						// Add retrieved translated item properties
						foreach ($translated_fields as $field_data)
						{
							$item_translations ->{$field_data->language_id} ->fields ->{$field_data->reference_field} = new stdClass();
							$item_translations ->{$field_data->language_id} ->fields ->{$field_data->reference_field}->value = $field_data->value;
							$found_languages[$field_data->language_id] = $item_translations->{$field_data->language_id}->name;
						}
						//echo "<br/>Falang translations found for: " . implode(",", $found_languages);
					}

					foreach ($item_translations as $lang_id => $translation_data)
					{
						// Default title can be somewhat long, trim it to first word, so that it is more suitable for tabs
						list($translation_data->name) = explode(' ', trim($translation_data->name));

						// Create text field value for all languages
						$translation_data->fields->text = new stdClass();
						$translation_data->fields->text->value = @ $translation_data->fields->introtext->value;
						if ( StringHelper::strlen( StringHelper::trim(@$translation_data->fields->fulltext->value) ) ) {
							$translation_data->fields->text->value .=  '<hr id="system-readmore" />' . @ $translation_data->fields->fulltext->value;
						}
					}

					$item->item_translations = & $item_translations;
				}
			}
			//echo "<pre>"; print_r($item->item_translations); exit;


			// ***
			// *** Overwrite item fields with the requested VERSION data
			// ***

			$item->current_version = $current_version;
			$item->last_version    = $last_version;
			if ($use_versioning && $version)
			{
				// Overcome possible group concat limitation
				$query="SET SESSION group_concat_max_len = 9999999";
				$db->setQuery($query);
				$db->execute();

				$query = "SELECT f.id, f.name, f.field_type, GROUP_CONCAT(iv.value SEPARATOR ',') as value, count(f.id) as valuecount, iv.field_id"
					." FROM #__flexicontent_items_versions as iv "
					." LEFT JOIN #__flexicontent_fields as f on f.id=iv.field_id"
					." WHERE iv.version='".$version."' AND (f.iscore=1 OR iv.field_id=-1 OR iv.field_id=-2) AND iv.item_id='".$pk."'"
					." GROUP BY f.id";
				$db->setQuery($query);
				$fields = $db->loadObjectList();
				$fields = $fields ? $fields : array();

				// Use versioned data, by overwriting the item data
				//echo "<br/>Overwritting fields with version: $version";
				foreach($fields as $f)
				{
					//echo "<br/><b>{$f->field_id} : ". $f->name."</b> : "; print_r($f->value);

					$fieldname = $f->name;

					// Skip fields that should not have been versioned: hits, state, voting
					if ($f->field_type=='hits' || $f->field_type=='state' || $f->field_type=='voting')
					{
						continue;
					}

					// Set version variable to indicate the loaded version
					else if ($f->field_type=='version')
					{
						$item->version = $version;
					}

					// categories and tags must have been serialized but some earlier versions did not do it,
					// we will check before unserializing them, otherwise they were concatenated to a single string and use explode ...
					else if( $fieldname=='categories'|| $fieldname=='tags' )
					{
						$array = flexicontent_db::unserialize_array($f->value, $force_array=false, $force_value=false);
						$item->$fieldname = $array ?: explode(',', $f->value);
					}

					else if ($f->field_id==-1)
					{
						if ( FLEXI_FISH )
						{
							$jfdata = flexicontent_db::unserialize_array($f->value, $force_array=false, $force_value=false);
							$item_lang = substr($item->language ,0,2);
							if ($jfdata) foreach ($item_translations as $lang_id => $translation_data)
							{
								//echo "<br/>Adding values for: ".$translation_data->shortcode;
								if ( empty($jfdata[$translation_data->shortcode]) ) continue;
								foreach ($jfdata[$translation_data->shortcode] as $fieldname => $fieldvalue)
								{
									//echo "<br/>".$translation_data->shortcode.": $fieldname => $fieldvalue";
									if ($translation_data->shortcode != $item_lang)
									{
										$translation_data->fields->$fieldname = new stdClass();
										$translation_data->fields->$fieldname->value = $fieldvalue;
									}
									else
									{
										$item->$fieldname = $fieldvalue;
									}
								}
							}
						}
					}

					// Other item properties that were versioned, such as alias, catid, meta params, attribs
					else if ($f->field_id==-2)
					{
						$item_data = flexicontent_db::unserialize_array($f->value, $force_array=false, $force_value=false);
						if ($item_data) foreach ($item_data as $k => $v) $item->$k = $v;
					}

					// Other fields (maybe serialized or not but we do not unserialized them, this is responsibility of the field itself)
					else if ($fieldname)
					{
						$item->$fieldname = $f->value;
					}
				}
				// The text field is stored in the db as to seperate fields: introtext & fulltext
				// So we search for the {readmore} tag and split up the text field accordingly.
				$this->splitText($item);
			}

			// -- Retrieve tags field value (if not using versioning)
			if ( $use_versioning && $version )
			{
				// Check version value was found
				if (!isset($item->tags) || !is_array($item->tags))
				{
					$item->tags = array();
				}
			}
			else
			{
				// Retrieve unversioned value
				$query = $db->getQuery(true)
					->select('DISTINCT tid')
					->from('#__flexicontent_tags_item_relations')
					->where('itemid = ' . (int) $pk);
				$item->tags = $db->setQuery($query)->loadColumn();
				$item->tags = array_reverse($item->tags);
			}

			echo empty($this->debug_tags) ? null : '<b>' . __FUNCTION__ . '()</b><blockquote> DB fctag_ids: ' . print_r($item->tags, true) . '</blockquote>';

			if ($task === 'edit' && $option === 'com_flexicontent')
			{
				// 1. Merge ($_replaceTags = false) Joomla tags assignment into FC tags assignments
				// 2. Find if we needed to add FC Tag assignments to Joomla tags assignments
				$new_fctags_ids = $this->mergeJTagsAssignments($item, $_jtags = null, $_replaceTags = false);

				// 3. Add (if needed) extra Joomla tags assignments so that they match FC Tag assignment
				//    ... since we overwrite, we do this only if we are executing FLEXIcontent component
				if ($new_fctags_ids) $this->saveJTagsAssignments($item->tags, $item->id);
			}

			// -- Retrieve categories field value (if not using versioning)
			if ( $use_versioning && $version )
			{
				// Check version value was found, and is valid (above code should have produced an array)
				if ( !isset($item->categories) || !is_array($item->categories) )
				{
					$item->categories = array();
				}
			}
			else
			{
				// Retrieve unversioned value
				$query = $db->getQuery(true)
					->select('DISTINCT catid')
					->from('#__flexicontent_cats_item_relations')
					->where('itemid = ' . (int) $pk);
				$item->categories = $db->setQuery($query)->loadColumn();
			}

			// Make sure catid is in categories array
			if ($item->catid && !in_array($item->catid, $item->categories))
			{
				$item->categories[] = $item->catid;
			}

			// 'cats' is an alias of categories
			$item->cats = & $item->categories;

			// Set original content item id, e.g. maybe used by some fields that are marked as untranslatable
			$useAssocs = $this->useAssociations();
			if ($useAssocs)
			{
				$site_default = substr(flexicontent_html::getSiteDefaultLang(), 0,2);
				$is_content_default_lang = $site_default == substr($item->language, 0,2);
				if ($is_content_default_lang)
				{
					$item->lang_parent_id = $item->id;
				}
				else
				{
					$item->lang_parent_id = 0;
					$langAssocs = $this->getLangAssocs($item->id);
					foreach($langAssocs as $content_id => $_assoc)
					{
						if ($site_default == substr($_assoc->language, 0,2))
						{
							$item->lang_parent_id = $content_id;
							break;
						}
					}
				}
			}

			// Execute the extra "_afterLoad" record steps
			$this->_afterLoad($item);

			// Assign found record to the _record member property
			$this->_record = & $item;
		}

		catch (JException $e)
		{
			$this->_record = false;
			$this->_typeid = 0;
			$this->getComponentTypeParams();
			if (!$version)
			{
				$items[$pk] = false;
				$fc_list_items[$pk] = false;
			}

			$this->setError($e);
		}

		// Add to record to cache if it is non-versioned data
		$is_cli = php_sapi_name() === 'cli';
		if (!$version && !$is_cli)
		{
			$items[$pk] = $this->_record;
			$fc_list_items[$pk] = $this->_record;
		}

		return (boolean) $this->_record;
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
		static $cache = array();
		$cache_index = $this->_id . '.' . (string) $loadData;

		if (!$data)
		{

			if (isset($cache[$cache_index]))
			{
				return $cache[$cache_index];
			}
		}

		$app = JFactory::getApplication();
		$perms = FlexicontentHelperPerm::getPerm();

		// Retrieve item if not already done, (loading item should been done by the view !), but:
		// 1) allow maintaining existing value:   $no_cache=false
		// 2) load by default the last saved version:   $force_version=-1   means latest (last saved) version)
		$this->getItem(null, $check_view_access=false, $no_cache=false, $force_version=0);

		// Prepare item data for being loaded into the form:
		// (a) Convert parameters 'images', 'urls,' 'attribs' & 'metadata' to an array
		// (b) Set property 'cid' (form field categories)

		$this->_record->itemparams = new JRegistry();

		if ($this->_id)
		{
			$json_columns = array('images', 'urls', 'attribs', 'metadata');

			foreach($json_columns as $colname)
			{
				$this->_prepareMergeJsonParams($colname);
			}
		}
		else
		{
			$this->_record->attribs = array();
			$this->_record->metadata = array();
			$this->_record->images = array();
			$this->_record->urls = array();
		}
		//echo "<pre>"; print_r($this->_record->itemparams); exit;

		// Set item property 'cid' (form field categories is named cid)
		$this->_record->cid = $this->_record->categories;

		// Get the form.
		$form = parent::getForm($data, $loadData);

		if (empty($form))
		{
			return false;
		}

		unset($this->_record->cid);

		// Determine correct permissions to check.
		$id = !empty($data['id']) ? $data['id'] : (int) $this->getState($this->getName().'.id');
		$isNew = !$id;

		// Existing record
		if (!$isNew)
		{
			// Can only edit in selected categories.
			$form->setFieldAttribute('catid', 'action', 'core.edit');
			// Can only edit own in selected categories.
			$form->setFieldAttribute('catid', 'action', 'core.edit.own');
		}

		// New record
		else
		{
			// Can only create in selected categories.
			$form->setFieldAttribute('catid', 'action', 'core.create');
		}

		$canEditState = $this->canEditState( empty($data) ? null : (object)$data );
		$autoPublished = !empty($this->_record->submit_conf['autopublished']);

		// Modify the form based on Edit State access controls.
		if (!$autoPublished && !$canEditState)
		{
			$frontend_new = $isNew && $app->isClient('site');

			/* Allow 'publish_up' & 'publish_down' only for NEW items
			 * These will either be overriden on item creation (via menu override)
			 * or they should be checked by the reviewer that will approve / publish the item
			 */
			if ($isNew)
			{
				$form->setValue('publish_up', null, '');
				$form->setValue('publish_down', null, '');
				$form->setFieldAttribute('publish_up', 'hint', JText::_('FLEXI_FIELD_ACCESS_CHECKED_DURING_SAVE'));
				$form->setFieldAttribute('publish_down', 'hint', JText::_('FLEXI_FIELD_ACCESS_CHECKED_DURING_SAVE'));
			}
			// (item edit form ACL) edit publish up / down
			elseif (empty($perms->EditPublishUpDown))
			{
				$form->setFieldAttribute('publish_up', 'disabled', 'true');
				$form->setFieldAttribute('publish_down', 'disabled', 'true');
				$form->setFieldAttribute('publish_up', 'filter', 'unset');
				$form->setFieldAttribute('publish_down', 'filter', 'unset');
			}

			// Skip disabling & unsetting state for new items in frontend to allow override via menu (auto-publish), menu override must be checked during store
			if (!$frontend_new)
			{
				$form->setFieldAttribute('state', 'disabled', 'true');
				$form->setFieldAttribute('state', 'filter', 'unset');
			}

			// DO not -disable- & -filter- 'vstate' it is needed during model's store and any tampering by user of it has no effect if no publish privilege !
			//$form->setFieldAttribute('vstate', 'disabled', 'true');
			//$form->setFieldAttribute('vstate', 'filter', 'unset');
		}


		// (no edit state ACL or is frontend) disable & filter fields 'featured' & 'ordering' fields
		if (!$app->isClient('administrator') || !$canEditState)
		{
			$form->setFieldAttribute('featured', 'disabled', 'true');
			$form->setFieldAttribute('ordering', 'disabled', 'true');
			$form->setFieldAttribute('featured', 'filter', 'unset');
			$form->setFieldAttribute('ordering', 'filter', 'unset');
		}

		// (item edit form ACL) edit creation date
		if (!$perms->EditCreationDate)
		{
			$form->setFieldAttribute('created', 'disabled', 'true');
			$form->setFieldAttribute('created', 'filter', 'unset');
		}

		// (item edit form ACL) edit creation date
		if (!$perms->EditCreator)
		{
			$form->setFieldAttribute('created_by', 'disabled', 'true');
			$form->setFieldAttribute('created_by_alias', 'disabled', 'true');
			$form->setFieldAttribute('created_by', 'filter', 'unset');
			$form->setFieldAttribute('created_by_alias', 'filter', 'unset');
		}

		// (item edit form ACL) edit viewing access level
		if (!$perms->CanAccLvl || !$canEditState)
		{
			$form->setFieldAttribute('access', 'disabled', 'true');
			$form->setFieldAttribute('access', 'filter', 'unset');
		}

		// (item edit form ACL) edit viewing access level
		if (!$perms->CanRights)
		{
			$form->setFieldAttribute('rules', 'disabled', 'true');
			$form->setFieldAttribute('rules', 'filter', 'unset');
		}

		// Check if item has languages associations, and disable changing category and language in frontend
		/*$useAssocs = $this->useAssociations();

		if (!$isNew && $app->isClient('site') && $useAssocs)
		{
			$associations = JLanguageAssociations::getAssociations('com_content', '#__content', 'com_content.item', $id);

			// Make fields read only
			if (!empty($associations))
			{
				$form->setFieldAttribute('language', 'readonly', 'true');
				$form->setFieldAttribute('catid', 'readonly', 'true');
				$form->setFieldAttribute('language', 'filter', 'unset');
				$form->setFieldAttribute('catid', 'filter', 'unset');
			}
		}*/

		if (!$data)
		{
			$cache[$cache_index] = $form;
		}

		return $form;
	}


	/**
	 * Auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @param   JForm   $form   The form object
	 * @param   array   $data   The data to be merged into the form object
	 * @param   string  $plugins_group  The name of the plugin group to import and trigger
	 *
	 * @return  void
	 *
	 * @since    3.0
	 */
	protected function preprocessForm(JForm $form, $data, $plugins_group = null)
	{
		// Association content items
		$app = JFactory::getApplication();
		$useAssocs = $this->useAssociations();

		if ($useAssocs)
		{
			$languages = JLanguageHelper::getLanguages('lang_code');
			$addform = new SimpleXMLElement('<form />');
			$fields = $addform->addChild('fields');
			$fields->addAttribute('name', 'associations');
			$fieldset = $fields->addChild('fieldset');
			$fieldset->addAttribute('name', 'item_associations');
			$fieldset->addAttribute('description', 'COM_CONTENT_ITEM_ASSOCIATIONS_FIELDSET_DESC');
			$add = false;

			foreach ($languages as $tag => $language)
			{
				if (empty($data->language) || $tag != $data->language)
				{
					$add = true;
					$field = $fieldset->addChild('field');
					$field->addAttribute('name', $tag);
					$field->addAttribute('type', 'item');
					$field->addAttribute('language', $tag);
					$field->addAttribute('label', $language->title);
					$field->addAttribute('translate_label', 'false');
					$field->addAttribute('edit', 'true');
					$field->addAttribute('clear', 'true');
					$field->addAttribute('filter', 'INT');  // also enforced later, but better to have it here too
				}
			}
			if ($add)
			{
				$form->load($addform, false);
			}
		}

		// Trigger the default form events.
		$plugins_group = $plugins_group ?: $this->plugins_group;

		try
		{
			parent::preprocessForm($form, $data, $plugins_group);
		}
		catch (Exception $e)
		{
			$app      = JFactory::getApplication();
			$isClient = $app->isClient('site');
			$errMssg  = $isClient
				? 'Form maybe loaded / saved incomplete'
				: '3rd-party plugin triggering to prepare form failed. Form will be loaded/saved only with basic data : <b> ' . $e->getMessage() . '</b>';
			$app->enqueueMessage($errMssg, ($isClient ? 'notice' : 'warning'));
		}
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
			$item = $this->getItem(null, $check_view_access=false, $no_cache=false, $force_version=0);

			/**
			 * Clone the item, skipping any extra JRegistry / Array properties like fields and parameters
			 * that will slow down or cause recursion during JForm operations like bind
			 */

			$data = $this->getTable();

			foreach($item as $i => $v)
			{

				if ($i === 'fields' || $i === 'parameters')
				{
					continue;
				}

				if (is_object($v))
				{
					/**
					 * Form fieldsets like 'params', if they are a registry object they may
					 * cause a failure due to some 3rd party plugins, convert them to arrays
					 */
					$data->$i = $v instanceof Registry
						? $data->params->toArray()
						: clone $v;
				}
				else
				{
					$data->$i = $v;
				}
			}
		}
		else
		{
			// Split text to introtext & fulltext
			if ( !StringHelper::strlen(StringHelper::trim(@$data['introtext'])) && !StringHelper::strlen(StringHelper::trim(@$data['fulltext'])) )
			{
				$this->splitText($data);
			}

			if ($this->_record)
			{
				if ( StringHelper::strlen(StringHelper::trim(@$data['text'])) )      $this->_record->text      = $data['text'];
				if ( StringHelper::strlen(StringHelper::trim(@$data['introtext'])) ) $this->_record->introtext = $data['introtext'];
				if ( StringHelper::strlen(StringHelper::trim(@$data['fulltext'])) )  $this->_record->fulltext  = $data['fulltext'];
				if ( isset($data['language']) )  $this->_record->language  = $data['language'];
				if ( isset($data['catid']) )     $this->_record->catid  = $data['catid'];
			}
		}

		$events_context = $this->events_context ?: $this->option.'.'.$this->getName();
		$this->preprocessData($events_context, $data);

		return $data;
	}


	/**
	 * Method to calculate Item Access Permissions
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	function getItemAccess()
	{
		$iparams_extra = new JRegistry();

		$user    = JFactory::getUser();
		$session = JFactory::getSession();
		$asset   = $this->type_alias . '.' . $this->_id;

		// Check if item was editable, but was rendered non-editable
		$hasTmpEdit = false;
		$hasCoupon  = false;
		if ($session->has('rendered_uneditable', 'flexicontent'))
		{
			$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
			$hasTmpEdit = !empty($this->_id) && !empty($rendered_uneditable[$this->_id]);  // editable temporarily
			$hasCoupon  = !empty($this->_id) && !empty($rendered_uneditable[$this->_id]) && $rendered_uneditable[$this->_id] == 2;  // editable temporarily via coupon
		}


		// ***
		// *** Compute CREATE access permissions.
		// ***

		if ( !$this->_id )
		{
 			// First check type 's create-items ACL. NOTE: an empty type_id, results to true, this will allow form to open without an empty TYPE selector
			$hasTypeCreate = FlexicontentHelperPerm::checkTypeAccess($this->_typeid, 'core.create');
			if (!$hasTypeCreate)
			{
				return $iparams_extra;  // no create items access in type, return
			}

			// Check that user can create item in at least one category ... NOTE: a hard deny on global / component, will be checked to avoid checking ALL categories
			$canCreateAny = FlexicontentHelperPerm::getPermAny('core.create');
			$iparams_extra->set('access-create', $canCreateAny);

			// Skip further ACL checks, since for new item, we do not calculate EDIT, DELETE and VIEW access
			return $iparams_extra;
		}


		// ***
		// *** Existing item, load item if not already loaded
		// ***

		if ( empty($this->_record) )
		{
			$this->_record = $this->getItem();
		}

		$isOwner = !empty($this->_record->created_by) && $this->_record->created_by == $user->get('id');

		if ( $this->_id )
		{
			// ***
			// *** Compute EDIT access permissions.
			// ***

			if ($hasTmpEdit)
			{
				$iparams_extra->set('access-edit', true);
			}
			else
			{
				// Get "edit /edit.own items" permission on the type of the item
				// NOTE: these always return true, as they do not exist YET, so they are for possible future use
				$hasTypeEdit = FlexicontentHelperPerm::checkTypeAccess($this->_typeid, 'core.edit');
				$hasTypeEditOwn = FlexicontentHelperPerm::checkTypeAccess($this->_typeid, 'core.edit.own');

				// Check edit permission on the item
				if ($hasTypeEdit && $user->authorise('core.edit', $asset))
				{
					$iparams_extra->set('access-edit', true);
				}

				// no edit permission, Check if edit.own permission on the item
				else if ($hasTypeEditOwn && $user->authorise('core.edit.own', $asset) && $isOwner)
				{
					$iparams_extra->set('access-edit', true);
				}
			}


			// ***
			// *** Compute EDIT STATE access permissions.
			// ***

			$iparams_extra->set('access-edit-state', $this->canEditState($this->_record));


			// ***
			// *** Compute DELETE access permissions.
			// ***

			// Get "delete items" permission on the type of the item
			// NOTE: these always return true, as they do not exist YET, so they are for possible future use
			$hasTypeDelete = FlexicontentHelperPerm::checkTypeAccess($this->_typeid, 'core.delete');
			$hasTypeDeleteOwn = FlexicontentHelperPerm::checkTypeAccess($this->_typeid, 'core.delete.own');

			// first check delete permission on the item
			if ($hasTypeDelete && $user->authorise('core.delete', $asset))
			{
				$iparams_extra->set('access-delete', true);
			}
			// no delete permission, check delete.own permission if the item is owned by the user
			else if ($hasTypeDeleteOwn && $user->authorise('core.delete.own', $asset) && ($isOwner || $hasCoupon)) // hasCoupon acts as item owner
			{
				$iparams_extra->set('access-delete', true);
			}
		}


		// ***
		// *** Compute VIEW access permissions.
		// ***

		$iparams_extra->set('access-view', $this->canView($this->_record));

		return $iparams_extra;
	}



	/**
	 * Method to check if you can assign a (new/existing) item in the specified categories
	 *
	 * @param	array	An array of input data.
	 *
	 * @return	boolean
	 * @since	1.6
	 */
	protected function itemAllowedInCats($data = array())
	{
		// Initialise variables.
		$user		= JFactory::getUser();

		$cats = isset($data['cid']) ? $data['cid'] : array();
		if ( !empty($data['catid']) && !in_array($data['catid'], $cats) )
		{
			$cats[] =  $data['catid'];
		}

		$allow = null;
		if (count($cats))
		{
			$allow = true;
			foreach ($cats as $currcatid)
			{
				// If the category has been passed in the data or URL check it.
				$cat_allowed = $user->authorise('core.create', 'com_content.category.' . $currcatid);
				if (!$cat_allowed)
				{
					JFactory::getApplication()->enqueueMessage("No access to add item to category with id " . $currcatid, 'warning');
					return false;
				}
				$allow &= $cat_allowed;
			}
		}

		if ($allow === null)
		{
			// no categories specified, revert to the component permissions.
			$allow	= $user->authorise('core.create', 'com_flexicontent');
		}

		return $allow;
	}


	/**
	 * Method to check if the user can create items of the given type
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function canCreateType($type_ids=false, $any = true, & $types = null)
	{
		$types = $this->getTypeslist ( $type_ids );
		if ( empty($types) ) return false;

		$user	= JFactory::getUser();
		$canCreate = $any ? false : true;

		foreach ($types as $type)
		{
			$type->allowed = ! $type->itemscreatable || $user->authorise('core.create', 'com_flexicontent.type.' . $type->id);

			// Require ANY or ALL
			$canCreate = $any  ?  ($canCreate || $type->allowed)  :  ($canCreate && $type->allowed);
			if ($canCreate && $any) return true;
		}

		return $canCreate;
	}


	/**
	 * Method to initialise the record data
	 *
	 * @return	boolean	True on success
	 *
	 * @since	1.5
	 */
	protected function _initRecord(&$record = null, $initOnly = false)
	{
		parent::_initRecord($record, $parent_initOnly = true);

		// Get some variables
		$app     = JFactory::getApplication();
		$user    = JFactory::getUser();


		// ***
		// *** Set model's default values into the record properties, overriding DB table column default values
		// ***

		$this->setDefaults($record);


		// ***
		// *** non-DB properties
		// ***
		$record->text      = null;
		$record->sectionid = FLEXI_SECTION;  // Deprecated do not use !!

		$record->current_version = 0;
		$record->last_version    = 0;
		$record->lang_parent_id  = 0;  // Deprecated do not use !!

		$record->rating_count = 0;
		$record->rating       = 0;
		$record->score        = 0;

		$record->parameters   = $this->_cparams;  // Initialized to component + type parameters


		// ***
		// *** non-DB properties, that are handled by _afteLoad() below
		// ***

		//$record->access_level  = null;  // Title of item's access level

		// Item's main category
		//$record->categoryslug    = null;   // Slug
		//$record->category_access = null;   // Access level

		// Item's Type
		//$record->typename     = null;  // Title
		//$record->typealias    = null;  // Alias
		//$record->type_access  = null;  // Access level

		//$record->creator       = '';  // Name of item's creator (owner)
		//$record->creatoremail  = '';  // Email of item's creator (owner)
		//$record->author        = '';  // ... alias of the creator

		//$record->modifier      = '';  // Name of item's last modifier
		//$record->modifieremail = '';  // Email of item's last modifier

		$this->_record = $record;

		// Execute "_afterLoad" steps, we prevented parent method from running these during record initialization
		if ( !$initOnly )
		{
			$this->_afterLoad($this->_record);
		}

		return true;
	}


	/**
	 * Return default language for new items
	 *
	 * @since	3.3
	 */
	protected function getDefaultItemLanguage($record)
	{
		$params = !empty($record->parameters) ? $record->parameters : $this->_cparams;

		$app    = JFactory::getApplication();
		$user   = JFactory::getUser();
		$CFGsfx = $app->isClient('site') ? '_fe' : '_be';

		$default_lang = $params->get('default_language' . $CFGsfx, '_author_lang_');

		// Check if using author default language
		$default_lang = $default_lang === '_author_lang_'
			? $user->getParam(($app->isClient('site') ? 'language' : 'admin_language'), '*')
			: $default_lang;

		// Check if using Site default language
		$default_lang = $default_lang === '_site_default_'
			? JComponentHelper::getParams('com_languages')->get('site', '*')
			: $default_lang;

		return $default_lang;
	}


	/**
	 * Set model's default values into the record properties, overriding DB table column default values
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @since	1.6
	 */
	protected function setDefaults($record)
	{
		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();

		// Default -- Dates: current and null.
		$currentdate = JFactory::getDate();

		// Default -- Created By (Owner).
		$default_created_by = $user->get('id') ?: flexicontent_db::getSuperUserID();

		// Default -- Access level.
		// -- NOTE: This default will be forced if user has not access level edit privelege
		$default_accesslevel = $app->getCfg( 'access', $public_acclevel = 1 );

		// Default -- Publication state.
		// -- NOTE: this will only be used if user has publish privilege, otherwise items will be forced to (a) pending_approval state for NEW ITEMS and (b) to item's current state for EXISTING ITEMS
		$default_state = $app->isClient('administrator')
			? $this->_cparams->get('new_item_state', $pubished_state = 1)      // Use the configured setting for backend items
			: $this->_cparams->get('new_item_state_fe', $pubished_state = 1);  // Use the configured setting for frontend items

		// Default -- Language
		// -- NOTE: There are language limitations per user / usergroup that may override the defaults ...
		$default_lang = $this->getDefaultItemLanguage($record);

		// ***
		// *** DB properties that do not use DB / getTable() defaults
		// ***

		$record->id             = 0;  // DB setting: no default value
		$record->version        = 0;  // DB setting: 1
		$record->created        = $currentdate->toSql();
		$record->created_by     = $default_created_by;
		$record->publish_up     = $currentdate->toSql();
		$record->publish_down   = FLEXI_J40GE ? NULL : $this->_db->getNullDate();
		$record->access         = $default_accesslevel;
		$record->state          = $default_state;
		$record->language       = $default_lang;


		// ***
		// *** DB properties that use DB / getTable() defaults
		// ***

		//$record->catid        = 0;
		//$record->title        = '';  // required
		//$record->alias        = '';
		//$record->introtext    = null;  // required
		//$record->fulltext     = null;  // required
		//$record->metadesc     = null;
		//$record->metakey      = null;
		//$record->hits         = 0;

		//$record->images       = null;
		//$record->urls         = null;
		//$record->attribs      = null;
		//$record->metadata     = null;

		//$record->modified     = FLEXI_J40GE ? NULL : $this->_db->getNullDate();
		//$record->modified_by  = 0;
		//$record->created_by_alias = null;


		// ***
		// *** (a) Some items_ext DB properties , (b) Type id & main category id,  (c) Categories and Tags
		// ***

		$record->search_index = null;

		$record->type_id      = $this->_typeid;
		$record->catid        = $this->_cid;

		$record->categories   = array();
		$record->tags         = array();
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
		parent::populateState();

		// Set global parameters: component + type parameters, ? UNUSED ? maybe used by parent class
		$this->setState('params', $this->_cparams);
	}


	/**
	 * Method to create or update a record a record
	 *
	 * @param		array			$data        Array of record data to use for creating or updating a record
	 * @param		boolean   $checkACL    Whether to check ACL: edit.state.*, and editfieldvalue
	 *
	 * @return	boolean	True on success
	 *
	 * @since	3.3.0
	 */
	function store($data, $checkACL = true)
	{
		global $fc_run_times;
		$start_microtime = microtime(true);

		// ***
		// *** Initialize various variables
		// ***

		$db     = $this->_db;
		$app    = JFactory::getApplication();
		$user   = JFactory::getUser();
		$isSite = $app->isClient('site');
		$CFGsfx = $isSite ? '_fe' : '_be';

		$jinput     = JFactory::getApplication()->input;
		$dispatcher = JEventDispatcher::getInstance();
		$cparams    = $this->_cparams;

		$option = $jinput->get('option', '', 'cmd');
		$view   = $jinput->get('view', '', 'cmd');
		$task   = $jinput->get('task', '', 'cmd');
		$jinput->set('isflexicontent', 'yes');

		$use_versioning = $cparams->get('use_versioning', 1);
		$print_logging_info = $cparams->get('print_logging_info');

		// Dates displayed in the item form, are in user timezone for J2.5, and in site's default timezone for J1.5
		$site_zone = $app->getCfg('offset');
		$user_zone = $user->getParam('timezone', $site_zone);
		$tz_offset = $user_zone;

		// Sanitize id and approval flag as integers
		$data['vstate'] = (int)$data['vstate'];
		$data['id']     = (int)$data['id'];
		$isNew = ! $data['id'];


		// ***
		// *** Get an empty item model (with default values)
		// ***

		$item = $this->getTable();
		$item->_isnew = $isNew;  // Pass information, if item is new to the fields


		// ***
		// *** Load existing item into the empty item model
		// ***

		if (!$isNew)
		{
			$item->load( $data['id'] );

			// Retrieve property: 'tags', that do not exist in the DB TABLE class, but are created by the ITEM model
			$query = $db->getQuery(true)
				->select('DISTINCT tid')
				->from('#__flexicontent_tags_item_relations')
				->where('itemid = ' . (int) $item->id);
			$item->tags = $db->setQuery($query)->loadColumn();
			$item->tags = array_reverse($item->tags);

			// Retrieve property: 'categories', that do not exist in the DB TABLE class, but are created by the ITEM model
			$query = $db->getQuery(true)
				->select('DISTINCT catid')
				->from('#__flexicontent_cats_item_relations')
				->where('itemid = ' . (int) $item->id);
			$item->categories = $db->setQuery($query)->loadColumn();

			// We need to convert FC item state TO a joomla's article state ... when triggering the before save content event
			$fc_state = $item->state;
			if ( in_array($fc_state, array(1,-5)) ) $jm_state = 1;           // published states
			else if ( in_array($fc_state, array(0,-3,-4)) ) $jm_state = 0;   // unpublished states
			else $jm_state = $fc_state;                                      // trashed & archive states

			// Frontend SECURITY concern: ONLY allow to set item type for new items !!! ... or for items without type ?!
			if ($isSite && $item->type_id && ($option === 'com_flexicontent' || $item->version > 1))
			{
				unset($data['type_id']);
			}

			// Also make sure owner of existing item is not empty to avoid using GUEST user as owner during ACL calculation
			if ( !$item->created_by )
			{
				$item->created_by = flexicontent_db::getSuperUserID();
			}

			// Also make sure we have a valid access level
			if ( !$item->access || !flexicontent_html::userlevel(null, $item->access, null, null, '', $_createlist = false) )
			{
				$item->access = $app->getCfg( 'access', $public_acclevel = 1 );
			}
		}


		// ***
		// *** Reset new item, just set some properties since we have already created a new item only via DB TABLE object
		// ***

		else
		{
			// Reset record properties to their DB table Columns default values
			$item->reset();

			// Set model's default values into the record properties, overriding DB table column default values
			$this->setDefaults($item);
		}

		// Make sure type is set e.g. coming from Joomla article form
		if (!$item->type_id && !empty($data['type_id']))
		{
			$item->type_id = (int) $data['type_id'];
		}

		// ***
		// *** Create a copy of the old item, to be able to reference previous values
		// ***

		$old_item = clone($item);


		// ***
		// *** Process given tags, if none given then do not set $data['tags'] (current DB values for tags will be maintained)
		// ***

		$this->_prepareTags($item, $data);


		// ***
		// *** Process given categories, if none given then use their current DB value to make the processing
		// ***

		$this->_prepareCategories($item, $data);


		// ***
		// *** Check and process other item DATA like title, description, etc
		// ***

		// Trim title, but allow not setting it ... to maintain current value (but we will also need to override 'required' during validation)
		if (isset($data['title']))
		{
			$data['title'] = trim($data['title']);
		}


		// Reconstruct 'text' (description) field if it has splitted up e.g. to seperate editors per tab
		if (isset($data['text']) && is_array($data['text']))
		{
			// Force a readmore at the end of text[0] (=before TABs text) ... so that the before TABs text acts as introtext
			$data['text'][0] .= (preg_match('#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i', $data['text'][0]) == 0) ? ("\n".'<hr id="system-readmore" />') : "" ;
			$data['text'] = implode('', $data['text']);
		}

		// The text field is stored in the db as to seperate fields: introtext & fulltext
		// So we search for the {readmore} tag and split up the text field accordingly.
		$this->splitText($data);


		// ***
		// *** Special handling of some FIELDSETs: e.g. 'attribs/params' and optionally for other fieldsets too, like: 'metadata'
		// *** By doing partial merging of these arrays we support having only a sub-set of them inside the form
		// *** we will use mergeAttributes() instead of bind(), thus fields that are not set will maintain their current DB values,
		// ***
		$mergeProperties = $this->mergeableGroups;
		$mergeOptions = array(
			'params_fset'  => 'attribs',
			'layout_type'  => 'item',
			'model_names'  => array($this->option => $this->getName(), 'com_content' => 'article'),
			'cssprep_save' => false,
		);
		$this->mergeAttributes($item, $data, $mergeProperties, $mergeOptions);


		// Unset the above handled FIELDSETs from $data, since we selectively merged them above into the RECORD,
		// thus they will not overwrite the respective RECORD's properties during call of JTable::bind()
		foreach($mergeProperties as $prop)
		{
			unset($data[$prop]);
		}


		/**
		 * ACL CHECKs
		 */

		if ($checkACL)
		{
			/**
			 * Retrieve submit configuration for new items in frontend
			 */

			if ($isSite && $isNew && !empty($data['submit_conf']))
			{
				$h = $data['submit_conf'];
				$session = JFactory::getSession();
				$item_submit_conf = $session->get('item_submit_conf', array(),'flexicontent');
				$submit_conf = !empty($item_submit_conf[$h]) ? $item_submit_conf[$h] : null;

				$autopublished    = $submit_conf && !empty($submit_conf['autopublished'])    ? $submit_conf['autopublished']    : null;  // Override flag for both TYPE and CATEGORY ACL
				$overridecatperms = $submit_conf && !empty($submit_conf['overridecatperms']) ? $submit_conf['overridecatperms'] : null;  // Override flag for CATEGORY ACL

				if ( $autopublished)
				{
					// Dates forced during autopublishing
					if (!empty($submit_conf['autopublished_up_interval']))
					{
						$publish_up_date = JFactory::getDate(); // Gives editor's timezone by default
						$publish_up_date->modify('+ '.$submit_conf['autopublished_up_interval'].' minutes');
						$publish_up_forced = $publish_up_date->toSql();
					}
					if (!empty($submit_conf['autopublished_down_interval']))
					{
						$publish_down_date = JFactory::getDate(); // Gives editor's timezone by default
						$publish_down_date->modify('+ '.$submit_conf['autopublished_down_interval'].' minutes');
						$publish_down_forced = $publish_down_date->toSql();
					}
				}

				// Other submit configuration
				$submit_cids  = isset($submit_conf['cids']) ? $submit_conf['cids'] : null;
				$maincatid    = isset($submit_conf['maincatid']) ? (int) $submit_conf['maincatid'] : 0;
				$maincat_show = isset($submit_conf['maincat_show']) ? (int) $submit_conf['maincat_show'] : 0;
				$postcats     = isset($submit_conf['postcats']) ? (int) $submit_conf['postcats'] : 0;
			}
			else
			{
				$autopublished    = 0;
				$overridecatperms = 0;

				// Other submit configuration
				$submit_cids  = null;
				$maincatid    = 0;
				$maincat_show = 0;
				$postcats     = 0;
			}


			/**
			 * SECURITY concern: Check form tampering of categories, of:
			 * (a) menu overridden categories for frontent item submit
			 * (b) or check user has 'create' privilege in item categories
			 */

			$allowed_cid = $overridecatperms
				? $submit_cids
				: FlexicontentHelperPerm::getAllowedCats($user, $actions_allowed = array('core.create'), $require_all=true);

			// Add the "default" main category to allowed categories
			if ($overridecatperms && $maincatid)
			{
				$allowed_cid[] = $maincatid;
			}

			// Force main category if main category selector (maincat_show:1) was disabled (and default main category was configured)
			if ($overridecatperms && $maincat_show === 1 && $maincatid)
			{
				$data['catid'] = $maincatid;
			}

			if (!empty($allowed_cid))
			{
				// Add existing item's categories into the user allowed categories
				$allowed_cid = array_merge($allowed_cid, $item->categories);

				// Check main category tampering
				if (!in_array($data['catid'], $allowed_cid) && $data['catid'] != $item->catid)
				{
					$this->setError( 'main category is not in allowed list (form tampered ?)' );
					return false;
				}

				// Check multi category tampering
				if (!$isNew || !$overridecatperms || $postcats === 2)
				{
					$data['categories'] = array_intersect($data['categories'], $allowed_cid);
				}
				elseif ($postcats === 1)
				{
					$data['categories'] = array($data['catid']);
				}
				// $postcats === 0 or other
				else
				{
					$data['categories'] = $allowed_cid;
				}

				// Make sure values are unique
				$data['categories'] = array_keys(array_flip($data['categories']));
			}


			/**
			 * SECURITY concern: Prevent users from changing the item owner and creation date unless either they are super admin or they have special ACL privileges
			 */

			if (!$user->authorise('flexicontent.editcreator', 'com_flexicontent'))
			{
				unset($data['created_by']);
				unset($data['created_by_alias']);
			}

			if (!$user->authorise('flexicontent.editcreationdate', 'com_flexicontent'))
			{
				unset($data['created']);
			}


			/**
			 * SECURITY concern: Check can edit state using proper owner, type and new category (all of them FORCED above)
			 */

			// Save old main category & creator (owner)
			$old_created_by = $item->created_by;
			$old_catid      = $item->catid;

			// Set proper into the item record proper owner (FORCED above) and type(FORCED above) + new main category
			$item->created_by = isset($data['created_by']) ? $data['created_by'] : $item->created_by;
			$item->catid      = $data['catid'];
			$item->type_id    = isset($data['type_id']) ? $data['type_id'] : $item->type_id;

			$canEditState     = $this->canEditState($item);

			// Restore old main category & creator (owner) (in case following code chooses to keep them)
			$item->created_by = $old_created_by;
			$item->catid      = $old_catid;

			// If cannot edit state or is frontend, then prevent user from changing 'featured' & 'ordering'
			if (!$canEditState || $isSite)
			{
				unset($data['featured']);
				unset($data['ordering']);
			}


			/**
			 * ACL CHECK: if no edit state then do extra filtering of fields
			 * Note some fields are not filtered since item will go under approval
			 */
			if (!$canEditState)
			{
				$AutoApproveChanges = $user->authorise('flexicontent.autoapprovechanges',	'com_flexicontent');
				$data['vstate'] = $AutoApproveChanges ? 2 : 1;

				// Allow 'publish_up' & 'publish_down' only for NEW items (we may override these with specific value below ...)
				if (!$isNew && !$user->authorise('flexicontent.editpublishupdown', 'com_flexicontent'))
				{
					unset($data['publish_up']);
					unset($data['publish_down']);
				}

				// Override the above values with forced auto-publishing data
				if (!empty($publish_up_forced))
				{
					$data['publish_up'] = $publish_up_forced;
				}

				if (!empty($publish_down_forced))
				{
					$data['publish_down'] = $publish_down_forced;
				}

				$pubished_state = 1;
				$draft_state = -4;
				$pending_approval_state = -3;

				// For existing items, force pending-state approval when user cannot publish, and versioning is OFF (aka not possible to use 'currently' active version + unapproved new version)
				if (!$isNew)
				{
					$catid_changed_n_vers_off = $old_catid != $data['catid'] && !$use_versioning;

					if ($catid_changed_n_vers_off)
					{
						$app->enqueueMessage(JText::_('FLEXI_WARNING_CONTENT_UNPUBLISHED_CAT_CHANGED_REAPPROVAL_NEEDED'), 'warning');
					}

					$data['state'] = $catid_changed_n_vers_off
						? $pending_approval_state
						: $item->state;
				}

				// Autopublishing new item via menu configuration
				elseif ($autopublished)
				{
					$data['state'] = $pubished_state;
				}

				// The preselected forced state of -NEW- items for users that CANNOT publish, and autopublish via menu item is disabled
				else
				{
					$data['state'] = !$isSite
						? $cparams->get('non_publishers_item_state', $draft_state)  // Use the configured setting for backend items
						: $cparams->get('non_publishers_item_state_fe', $pending_approval_state);  // Use the configured setting for frontend items
				}
			}

			/**
			 * SECURITY concern: Check form tampering of allowed languages
			 * NOTE: hiding language is checked by controller
			 */

			$authorparams = flexicontent_db::getUserConfig($user->get('id'));
			$allowed_langs = $authorparams->get('langs_allowed',null);
			$allowed_langs = !$allowed_langs ? null : FLEXIUtilities::paramToArray($allowed_langs);

			if (!$isNew && $allowed_langs)
			{
				$allowed_langs[] = $item->language;
			}

			if ($allowed_langs && isset($data['language']) && !in_array($data['language'], $allowed_langs))
			{
				$app->enqueueMessage('You are not allowed to assign language: '.$data['language'].' to Content Items', 'warning');
				unset($data['language']);
				if ($isNew) return false;
			}
		}

		else
		{
			$canEditState = true;
		}


		// ***
		// *** Prior to bind changes
		// ***

		// For new items get next available ordering number
		if ($isNew)
		{
			$this->useLastOrdering = empty($item->ordering) || !$canEditState;
		}

		// Extra steps after loading record, and before calling JTable::bind()
		$this->_prepareBind($item, $data);


		// ***
		// *** Bind given (possibly modifed) item DATA and PARAMETERS to the item object
		// ***

		if ( !$item->bind($data) )
		{
			$app->enqueueMessage('Failed to bind() item data', 'warning');
			$this->setError($item->getError());
			return false;
		}


		// ***
		// *** Check and correct CORE item properties (some such work was done above before binding)
		// ***

		// -- Modification Date and Modifier, (a) new item gets null modification date and (b) existing item get the current date
		if ($isNew)
		{
			$item->modified    = FLEXI_J40GE ? NULL : $this->_db->getNullDate();
			$item->modified_by = 0;
		}
		else
		{
			$datenow = JFactory::getDate();
			$item->modified    = $datenow->toSql();
			$item->modified_by = $user->get('id');
		}

		// Clear up-to date marking of this item so that its modification time will be used ...
		$item->is_uptodate = 0;

		// -- Creation Date
		if ($item->created && StringHelper::strlen(StringHelper::trim( $item->created )) <= 10)
		{
			$item->created 	.= ' 00:00:00';
		}
		$date = JFactory::getDate($item->created);
		if (!empty($data['created']))
		{
			$date->setTimeZone( new DateTimeZone( $tz_offset ) );  // Date originated from form field, so it was in user's timezone
		}
		$item->created = $date->toSql();


		// -- Publish UP Date
		if ($item->publish_up && StringHelper::strlen(StringHelper::trim( $item->publish_up )) <= 10)
		{
			$item->publish_up 	.= ' 00:00:00';
		}
		$date = JFactory::getDate($item->publish_up);
		if (!empty($data['publish_up']))
		{
			$date->setTimeZone( new DateTimeZone( $tz_offset ) );   // Date originated from form field, so it was in user's timezone
		}
		$item->publish_up = $date->toSql();


		// -- Publish Down Date
		if (!$item->publish_down || trim($item->publish_down) == JText::_('FLEXI_NEVER') || trim($item->publish_down) == '' || $item->publish_down == $db->getNullDate())
		{
			$item->publish_down = FLEXI_J40GE ? NULL : $this->_db->getNullDate();
		}
		else
		{
			if ( StringHelper::strlen(StringHelper::trim( $item->publish_down )) <= 10 )
			{
				$item->publish_down .= ' 00:00:00';
			}
			$date = JFactory::getDate($item->publish_down);
			if (!empty($data['publish_down']))
			{
				$date->setTimeZone( new DateTimeZone( $tz_offset ) );  // Date originated from form field, so it was in user's timezone
			}
			$item->publish_down = $date->toSql();
		}


		// Auto assign the default language if not set, (security of allowing language usage and of language in user's allowed languages was checked above)
		$item->language = $item->language ?: $this->getDefaultItemLanguage($item);


		// Ignore language parent id, we are now using J3.x associations
		$item->lang_parent_id = 0;


		// ***
		// *** Get version info, force version approval ON is versioning disabled, and decide new item's current version number
		// ***

		$current_version = (int) FLEXIUtilities::getCurrentVersions($item->id, true);
		$last_version    = (int) FLEXIUtilities::getLastVersions($item->id, true);

		// (a) Force item approval when versioning disabled
		$data['vstate'] = ( !$use_versioning ) ? 2 : $data['vstate'];

		// (b) Force item approval when item is not yet visible (is in states (a) Draft or (b) Pending Approval)
		$data['vstate'] = ( $item->state==-3 || $item->state==-4 ) ? 2 : $data['vstate'];

		// Decide new current version for the item, this depends if versioning is ON and if versioned is approved
		if ( !$use_versioning ) {
			// not using versioning, increment current version numbering
			$item->version = $isNew ? 1 : $current_version+1;
		} else {
			// using versioning, increment last version numbering, or keep current version number if new version was not approved
			$item->version = $isNew ? 1 : ( $data['vstate']==2 ? $last_version+1 : $current_version);
		}
		// *** Item version should be zero when form was loaded with no type id,
		// *** thus next item form load will load default values of custom fields
		$item->version = ($isNew && !empty($data['type_id_not_set']) ) ? 0 : $item->version;

		if ( $print_logging_info ) @$fc_run_times['item_store_prepare'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;


		// ***
		// *** Make sure we import flexicontent AND content plugins since we will be triggering their events
		// ***

		JPluginHelper::importPlugin('flexicontent');

		if ($this->plugins_group)
		{
			JPluginHelper::importPlugin($this->plugins_group);
		}


		// ***
		// *** Trigger Event 'onBeforeSaveItem' of FLEXIcontent plugins (such plugin is the 'flexinotify' plugin)
		// ***

		if ( $print_logging_info ) $start_microtime = microtime(true);

		$this->_update_mcats($data, $item, true);

		$results = FLEXI_J40GE
			? $app->triggerEvent('onBeforeSaveItem', array(&$item, $isNew))
			: $dispatcher->trigger('onBeforeSaveItem', array(&$item, $isNew));

		$this->_update_mcats($data, $item);

		// Abort item save if any plugin returns a result === false
		if (is_array($results) && in_array(false, $results, true))
		{
			$this->setError('At least 1 content plugin has failed to save its data (Event onBeforeSaveItem). Aborting');
			if ($item->getError()) $app->enqueueMessage($item->getError(), 'notice');
			return false;
		}

		if ( $print_logging_info ) $fc_run_times['onBeforeSaveItem_event'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;


		// ***
		// *** Trigger Event 'OnBeforeContentSave' (J1.5) or 'onContentBeforeSave' (J2.5) of Joomla's Content plugins
		// ***

		if ($option === 'com_flexicontent' || !$option)
		{
			// Some compatibility steps
			if (!$isNew)
			{
				$db->setQuery( 'UPDATE #__content SET state = '. $jm_state .' WHERE id = '.$item->id );
				$db->execute();
			}
			$jinput->set('view', 'article');
			$jinput->set('option', 'com_content');

			if ( $print_logging_info ) $start_microtime = microtime(true);

			$this->_update_mcats($data, $item, true);

			$results = FLEXI_J40GE
				? $app->triggerEvent($this->event_before_save, array('com_content.article', &$item, $isNew, $data))
				: $dispatcher->trigger($this->event_before_save, array('com_content.article', &$item, $isNew, $data));

			$this->_update_mcats($data, $item);

			if ( $print_logging_info ) $fc_run_times['onContentBeforeSave_event'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

			// Reverse compatibility steps
			if (!$isNew)
			{
				$db->setQuery( 'UPDATE #__content SET state = '. $fc_state .' WHERE id = '.$item->id );
				$db->execute();
			}
			$jinput->set('view', $view);
			$jinput->set('option', $option);

			// Abort item save if any plugin returns a result === false
			if (is_array($results) && in_array(false, $results, true))
			{
				$this->setError('At least 1 content plugin has failed to save its data (Event onBeforeSaveItem). Aborting');
				if ($item->getError()) $app->enqueueMessage($item->getError(), 'notice');
				return false;
			}
		}


		// ***
		// *** IF new item, create it before saving the fields (and constructing the search_index out of searchable fields)
		// ***

		if ( $print_logging_info ) $start_microtime = microtime(true);

		// Only create the item not save the CUSTOM fields yet, no need to rebind this is already done above
		if( $isNew )
		{
			if (!$this->applyCurrentVersion($item, $data, $createonly=true))
			{
				return false;
			}
		}

		// ??? Make sure the data of the model are correct  ??? ... maybe this no longer needed
		// e.g. because getForm() is used to validate input data and may have set an empty item and empty id
		// e.g. type_id of item may have been altered by authorized users
		else
		{
			$this->_id     = $item->id;
			$this->_record = & $item;
			$this->_typeid = $item->type_id;
			$this->getComponentTypeParams();
		}

		// Set item parameters, to the component + type parametes e.g. needed by onBeforeSaveField event
		$item->parameters = $this->_cparams;

		if ( $print_logging_info ) $fc_run_times['item_store_core'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;


		// ***
		// *** Save fields values to appropriate tables (versioning table or normal tables)
		// *** NOTE: This allow canceling of item save operation, if 'abort' is returned
		// ***

		// Do not try to load fields / save field values, if applying type
		$result = true;
		if ($task != 'apply_type')
		{
			if ( $print_logging_info ) $start_microtime = microtime(true);

			// null indicates to retrieve it inside saveFields
			$files = null;  // $_FILES;  //$app->input->files->get('files');
			$core_data_via_events = null;

			$result = $this->saveFields($isNew, $item, $data, $files, $old_item, $core_data_via_events, $checkACL);

			// Allow custom redirection on failure
			if (!empty($item->abort_save))
			{
				$result = 'abort';
				unset($item->abort_save);

				if (!empty($item->abort_redirect_url))
				{
					$this->abort_redirect_url = $item->abort_redirect_url;
					unset($item->abort_redirect_url);
				}
			}

			if ( $print_logging_info ) $fc_run_times['item_store_custom'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;


			// Re-split possibly modified text to introtext, fulltext
			$this->splitText($core_data_via_events);

			// Re-bind (possibly modified data) to the item
			if (!$item->bind($core_data_via_events))
			{
				$app->enqueueMessage('Failed to (re) bind() item data. Aborting', 'error');
				$this->setError($item->getError());
				return false;
			}
		}

		$version_approved = $isNew || $data['vstate']==2;

		if ($result === 'abort')
		{
			if ($isNew)
			{
				$db->setQuery('DELETE FROM #__assets WHERE id = (SELECT asset_id FROM #__content WHERE id='.$item->id.')');
				$db->execute();
				$db->setQuery('DELETE FROM #__content WHERE id ='.$item->id);
				$db->execute();
				$db->setQuery('DELETE FROM #__flexicontent_items_ext WHERE item_id='.$item->id);
				$db->execute();
				$db->setQuery('DELETE FROM #__flexicontent_items_tmp WHERE id='.$item->id);
				$db->execute();

				$db->setQuery('DELETE FROM #__flexicontent_tags_item_relations WHERE itemid='.$item->id);
				$db->execute();
				$db->setQuery('DELETE FROM #__flexicontent_fields_item_relations WHERE item_id='.$item->id);
				$db->execute();
				$db->setQuery('DELETE FROM #__flexicontent_items_versions WHERE item_id='.$item->id);
				$db->execute();

				// Delete data that were bogusly in the past
				$db->setQuery('DELETE FROM #__flexicontent_fields_item_relations WHERE item_id NOT IN (SELECT id FROM #__content)');
				$db->execute();
				$db->setQuery('DELETE FROM #__flexicontent_items_versions WHERE item_id NOT IN (SELECT id FROM #__content)');
				$db->execute();

				$this->setId(0);
				$this->setError( $this->getError().' '.JText::_('FLEXI_NEW_ITEM_NOT_CREATED') );
			}
			else
			{
				$this->setError( $this->getError().' '.JText::_('FLEXI_EXISTING_ITEM_NOT_SAVED') );
			}

			// Return false this will indicate to the controller to abort saving
			// and set POSTED data into session so that form reloads them properly
			return false;
		}


		// ***
		// *** ITEM DATA SAVED:  EITHER new, OR approving current item version
		// ***

		if ( $version_approved )
		{
			// Save -both- item CORE data AND custom fields, rebinding the CORE ITEM DATA since the onBeforeSaveField may have modified them
			if ( $print_logging_info ) $start_microtime = microtime(true);
			if( !$this->applyCurrentVersion($item, $data, $createonly=false) ) return false;
			if ( $print_logging_info ) @$fc_run_times['item_store_core'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			//echo "<pre>"; var_dump($data); exit();


			// Update Joomla Featured FLAG
			$this->featured(array($item->id), $item->featured);


			// ***
			// *** Trigger Event 'onAfterContentSave' (J1.5) OR 'onContentAfterSave' (J2.5 ) of Joomla's Content plugins
			// ***
			if ($option === 'com_flexicontent' || !$option)
			{
				if ( $print_logging_info ) $start_microtime = microtime(true);

				// Some compatibility steps
				$jinput->set('view', null);
				$jinput->set('option', 'com_content');

				$results = FLEXI_J40GE
					? $app->triggerEvent($this->event_after_save, array('com_content.article', &$item, $isNew, $data))
					: $dispatcher->trigger($this->event_after_save, array('com_content.article', &$item, $isNew, $data));

				// Abort further actions if any plugin returns a result === false
				/*if (is_array($results) && in_array(false, $results, true))
				{
					$this->setError('At least 1 content plugin has failed to save its data (Event onContentAfterSave). Aborting');
					if ($item->getError()) $app->enqueueMessage($item->getError(), 'notice');
					return false;
				}*/

				// Reverse compatibility steps
				$jinput->set('view', $view);
				$jinput->set('option', $option);

				if ( $print_logging_info ) @$fc_run_times['onContentAfterSave_event'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			}
		}


		// ***
		// *** Trigger Event 'onAfterSaveItem' of FLEXIcontent plugins (such plugin is the 'flexinotify' plugin)
		// ***

		if ( $print_logging_info ) $start_microtime = microtime(true);

		$results = FLEXI_J40GE
			? $app->triggerEvent('onAfterSaveItem', array(&$item, &$data))
			: $dispatcher->trigger('onAfterSaveItem', array(&$item, &$data));

		// Abort further actions if any plugin returns a result === false
		/*if (is_array($results) && in_array(false, $results, true))
		{
			$this->setError('At least 1 content plugin has failed to save its data (Event onAfterSaveItem). Aborting');
			if ($item->getError()) $app->enqueueMessage($item->getError(), 'notice');
			return false;
		}*/

		if ( $print_logging_info ) @$fc_run_times['onAfterSaveItem_event'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;


		// ***
		// *** ITEM DATA NOT SAVED:  NEITHER new, NOR approving current item version
		// ***

		if ( !$version_approved )
		{
			if ( $cparams->get('approval_warning_aftersubmit' . $CFGsfx, 1) )
			{
				// Warn editor that his/her changes will need approval to before becoming active / visible
				$canEditState
					? JFactory::getApplication()->enqueueMessage(JText::_('FLEXI_SAVED_VERSION_WAS_NOT_APPROVED_NOTICE'.($app->isClient('administrator') ? '_ADMIN' : '')), 'notice')
					: JFactory::getApplication()->enqueueMessage(JText::_('FLEXI_SAVED_VERSION_MUST_BE_APPROVED_NOTICE'.($app->isClient('administrator') ? '_ADMIN' : '')), 'notice');
			}
			// Set modifier and modification time (as if item has been saved), so that we can use this information for updating the versioning tables
			$datenow = JFactory::getDate();
			$item->modified			= $datenow->toSql();
			$item->modified_by	= $user->get('id');
		}


		// ***
		// *** Create and store version METADATA information
		// ***

		if ( $print_logging_info ) $start_microtime = microtime(true);
		if ($use_versioning)
		{
			$v = new stdClass();
			$v->item_id			= (int)$item->id;
			$v->version_id	= ($isNew && !empty($data['type_id_not_set']) ) ? 0 : (int)$last_version+1;
			$v->created			= $item->created;
			$v->created_by	= $item->created_by;
			if ($item->modified && $item->modified != $this->_db->getNullDate()) {
				// NOTE: We set modifier as creator of the version, and modication date as creation date of the version
				$v->created		 = $item->modified;
				$v->created_by = $item->modified_by;
			}
			$v->comment		= isset($data['versioncomment']) ? htmlspecialchars($data['versioncomment'], ENT_QUOTES) : '';
			$this->_db->insertObject('#__flexicontent_versions', $v);
		}


		// ***
		// *** Delete old versions that are above the limit of kept versions
		// ***

		$vcount	= FLEXIUtilities::getVersionsCount($item->id);
		$vmax	= $cparams->get('nr_versions', 10);

		if ($vcount > $vmax) {
			$deleted_version = FLEXIUtilities::getFirstVersion($item->id, $vmax, $current_version);
			$query = 'DELETE'
					.' FROM #__flexicontent_items_versions'
					.' WHERE item_id = ' . (int)$item->id
					.' AND version <=' . $deleted_version
					.' AND version!=' . (int)$current_version
					;
			$this->_db->setQuery($query);
			$this->_db->execute();

			$query = 'DELETE'
					.' FROM #__flexicontent_versions'
					.' WHERE item_id = ' . (int)$item->id
					.' AND version_id <=' . $deleted_version
					.' AND version_id!=' . (int)$current_version
					;
			$this->_db->setQuery($query);
			$this->_db->execute();
		}
		if ( $print_logging_info ) @$fc_run_times['ver_cleanup_ver_metadata'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;


		// ***
		// *** Trigger Event 'onCompleteSaveItem' of FLEXIcontent plugins (such plugin is the 'flexinotify' plugin)
		// ***

		if ( $print_logging_info ) $start_microtime = microtime(true);

		$results = FLEXI_J40GE
			? $app->triggerEvent('onCompleteSaveItem', array(&$item, &$fields))
			: $dispatcher->trigger('onCompleteSaveItem', array(&$item, &$fields));

		// Abort further actions if any plugin returns a result === false
		/*if (is_array($results) && in_array(false, $results, true))
		{
			$this->setError('At least 1 content plugin has failed to save its data (Event onCompleteSaveItem). Aborting');
			if ($item->getError()) $app->enqueueMessage($item->getError(), 'notice');
			return false;
		}*/

		if ( $print_logging_info ) @$fc_run_times['onCompleteSaveItem_event'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

		return true;
	}


	/**
	 * Method to save field values of the item in field versioning DB table or in ..._fields_item_relations DB table
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function saveFields($isNew, &$item, &$data, &$files, &$old_item = null, &$core_data_via_events = null, $checkACL = true)
	{
		if (!$old_item)
		{
			$old_item = & $item;
		}

		$app     = JFactory::getApplication();
		$user    = JFactory::getUser();
		$isSite  = $app->isClient('site');
		$isAdmin = !$isSite;  // Treat non-site (e.g. CLI) as if being admin
		
		$dispatcher = JEventDispatcher::getInstance();
		$cparams    = $this->_cparams;
		$use_versioning = $cparams->get('use_versioning', 1);
		$print_logging_info = $cparams->get('print_logging_info');
		$last_version = (int) FLEXIUtilities::getLastVersions($item->id, true);
		$mval_query = true;

		if ( $print_logging_info ) global $fc_run_times;
		if ( $print_logging_info ) $start_microtime = microtime(true);


		// ***
		// *** Checks for untranslatable fields
		// ***

		// CASE 1. Check if saving an item that translates an original content in site's default language
		// ... Decide whether to retrieve field values of untranslatable fields from the original content item
		$useAssocs = $this->useAssociations();

		$site_default = substr(flexicontent_html::getSiteDefaultLang(), 0,2);
		$is_content_default_lang = $site_default == substr($item->language, 0,2);

		$get_untraslatable_values = $useAssocs && !$is_content_default_lang;
		if ($useAssocs)
		{
			$langAssocs = $this->getLangAssocs();
			// ... Get item ids of the associated items, so that we save into the untranslatable fields
			$_langAssocs = $langAssocs;
			unset($_langAssocs[$this->_id]);
			$assoc_item_ids = array_keys($_langAssocs);
		}
		if (empty($assoc_item_ids)) $assoc_item_ids = array();


		// ***
		// *** Get item's fields ... and their values (for untranslatable fields the field values from original content item are retrieved
		// ***

		$original_content_id = 0;
		if ($get_untraslatable_values)
		{
			foreach($langAssocs as $content_id => $_assoc)
			{
				if ($site_default == substr($_assoc->language, 0,2))
				{
					$original_content_id = $content_id;
					break;
				}
			}
		}
		//JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): '.$original_content_id.' '.print_r($assoc_item_ids, true),'message');
		$fields = $this->getExtrafields($force=true, $original_content_id, $old_item);
		$item->fields = & $fields;
		$item->calculated_fieldvalues = array();

		// Get uploaded files information (name, size, location, etc) and also apply default 'file-is-safe' filtering
		if ($files === null)
		{
			foreach($fields as $field)
			{
				$file = $app->input->files->get($field->name, null, 'cmd');
				if ($file)
				{
					$files[$field->name] = $file;
				}
			}
		}


		// ***
		// *** Loop through Fields triggering onBeforeSaveField Event handlers, this was seperated from the rest of the process
		// *** to give chance to ALL fields to check their DATA and cancel item saving process before saving any new field values
		// ***

		$searchindex = array();
		//$qindex = array();

		$core_data_via_events = array();  // Extra validation for some core fields via onBeforeSaveField
		$core_via_post = array('title'=>1, 'text'=>1);

		$postdata = array();

		// Save main category id of the item
		$this->_update_mcats($data, $item, true);

		foreach($fields as $field)
		{
			// Set vstate property into the field object to allow this to be changed be the before saving  field event handler
			$field->item_vstate = $data['vstate'];

			$is_editable   = !$checkACL || !$field->valueseditable || $user->authorise('flexicontent.editfieldvalues', 'com_flexicontent.field.' . $field->id);
			$is_hidden_fe  = $checkACL && $isSite && ($field->formhidden==1 || $field->formhidden==3 || $field->parameters->get('frontend_hidden'));
			$is_hidden_be  = $checkACL && $isAdmin && ($field->formhidden==2 || $field->formhidden==3 || $field->parameters->get('backend_hidden'));
			$maintain_dbval = false;

			// FORM HIDDEN FIELDS (FRONTEND/BACKEND) AND (ACL) UNEDITABLE FIELDS: maintain their DB value ...
			// NOTE: this will not / should not execute if $checkACL is OFF !!!
			if ($is_hidden_fe || $is_hidden_be || !$is_editable)
			{
				$postdata[$field->name] = $field->value;
				$maintain_dbval = true;
			}

			else if ($field->iscore)
			{
				// (posted) CORE FIELDS: if not set maintain their DB value ...
				if ( isset($core_via_post[$field->name]) )
				{
					if ( isset($data[$field->name]) ) {
						$postdata[$field->name] = $data[$field->name];
					}
					else {
						$postdata[$field->name] = $field->value;
						$maintain_dbval = true;
					}
				}

				// (not posted) CORE FIELDS: get current value
				else
				{
					// Get value from the updated item instead of old data
					$postdata[$field->name] = $this->getCoreFieldValue($field, 0);
				}
			}

			// OTHER (non-hidden) CUSTOM FIELDS
			else
			{
				$postdata[$field->name] = isset($data['custom'][$field->name]) ? $data['custom'][$field->name] : null;
			}

			// Force array for field values
			if ( !is_array($postdata[$field->name]) )
			{
				$postdata[$field->name] = strlen($postdata[$field->name] ?? '') ? array($postdata[$field->name]) : array();
			}

			// Unserialize values already serialized values
			// e.g. (a) if current values used are from DB or (b) are being imported from CSV file that contains exported serialized data
			// (we exclude inner serialized objects, as theses are not valid user data)
			foreach ($postdata[$field->name] as $i => $postdata_val)
			{
				$postdata[$field->name][$i] = flexicontent_db::unserialize_array($postdata_val, $force_array=false, $force_value=true);
			}

			// Trigger plugin Event 'onBeforeSaveField'
			if (!$field->iscore || isset($core_via_post[$field->name]))
			{
				//$qindex[$field->name] = NULL;
				$field_type = $field->iscore ? 'core' : $field->field_type;
				$file_data  = isset($files[$field->name]) ? $files[$field->name] : null;  // Pass a copy field's FILE data
				$result = FLEXIUtilities::call_FC_Field_Func($field_type, 'onBeforeSaveField', array( &$field, &$postdata[$field->name], &$file_data, &$item /*, &$qindex[$field->name]*/ ));

				if ($result===false)
				{
					// Field requested to abort item saving
					$this->setError( JText::sprintf('FLEXI_FIELD_VALUE_IS_INVALID', $field->label) );
					return 'abort';
				}
			}

			// Currently other CORE fields, these are skipped we will not call onBeforeSaveField() on them, neither rebind them
			else
			{
			}

			// Get vstate property from the field object back to the data array ... in case it was modified, since some field may decide to prevent approval !
			$data['vstate'] = $field->item_vstate;
		}

		// Update multi-category data, in case category was modified
		$this->_update_mcats($data, $item);



		// ***
		// *** Set values of other fields (e.g. this is used for "Properties as Fields" feature)
		// ***

		foreach($item->calculated_fieldvalues as $fieldname => $fieldvalues)
		{
			if (isset($fields[$fieldname]))
			{
				$postdata[$fieldname] = $fieldvalues;
			}
		}
		unset($item->calculated_fieldvalues);



		// ***
		// *** Set postdata / filedata field properties
		// ***

		foreach($fields as $field)
		{
			$field->filedata = isset($files[$field->name]) ? $files[$field->name] : null;
			$field->postdata = $this->formatToArray( $postdata[$field->name] );
		}


		// ***
		// *** Trigger plugin Event 'onAllFieldsPostDataValidated'
		// ***

		// Save main category id of the item
		$this->_update_mcats($data, $item, true);

		foreach($fields as $field)
		{
			$field_type = $field->iscore ? 'core' : $field->field_type;
			$result = FLEXIUtilities::call_FC_Field_Func($field_type, 'onAllFieldsPostDataValidated', array( &$field, &$item ));

			if ($result === 'abort')
			{
				return $result;
			}

			// For CORE field get the modified data, which will be used for storing in DB (these will be re-bind later)
			if ( isset($core_via_post[$field->name]) )
			{
				$core_data_via_events[$field->name] = isset($field->postdata[0]) ? $field->postdata[0] : '';
			}

			// Make sure any modified values are an array
			$field->postdata = $this->formatToArray( $field->postdata );
		}

		// Update multi-category data, in case category was modified
		$this->_update_mcats($data, $item);


		if ( $print_logging_info ) @$fc_run_times['fields_value_preparation'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		if ( $print_logging_info ) $start_microtime = microtime(true);


		/**
		 * Clean-up per field search-index TABLES
		 */

		$filterables = FlexicontentFields::getSearchFields('id', $indexer='advanced', null, null, $_load_params=true, 0, $search_type='filter');
		$filterables = array_keys($filterables);
		$filterables = array_flip($filterables);

		$dbprefix = $app->getCfg('dbprefix');
		$dbname   = $app->getCfg('db');
		$tbl_prefix = $dbprefix . 'flexicontent_advsearch_index_field_';
		$query = 'SELECT TABLE_NAME
			FROM INFORMATION_SCHEMA.TABLES
			WHERE TABLE_SCHEMA = ' . $this->_db->Quote($dbname) . ' AND TABLE_NAME LIKE ' . $this->_db->Quote($tbl_prefix . '%')
		;
		$tbl_names = $this->_db->setQuery($query)->loadColumn();

		foreach($tbl_names as $tbl_name)
		{
			$_field_id = str_replace($tbl_prefix, '', $tbl_name);

			// Drop the table of no longer filterable field
			if (!isset($filterables[$_field_id]))
			{
				$this->_db->setQuery( 'DROP TABLE IF EXISTS '.$tbl_name );
			}

			// Remove item's old advanced search index entries
			else
			{
				$query = 'DELETE FROM ' . $tbl_name . ' WHERE item_id = ' . (int) $item->id;
				$this->_db->setQuery($query)->execute();
			}
		}


		/**
		 * VERIFY all search tables exist
		 */

		$tbl_names_flipped = array_flip($tbl_names);
		foreach ($filterables as $_field_id => $_ignored)
		{
			$tbl_name = $app->getCfg('dbprefix').'flexicontent_advsearch_index_field_'.$_field_id;
			if ( isset($tbl_names_flipped[$tbl_name]) ) continue;
			$query = '
			CREATE TABLE IF NOT EXISTS `' .$tbl_name. '` (
			  `sid` int(11) NOT NULL auto_increment,
			  `field_id` int(11) NOT NULL,
			  `item_id` int(11) NOT NULL,
			  `extraid` int(11) NOT NULL,
			  `search_index` longtext NOT NULL,
			  `value_id` varchar(255) NULL,
			  PRIMARY KEY (`field_id`,`item_id`,`extraid`),
			  KEY `sid` (`sid`),
			  KEY `field_id` (`field_id`),
			  KEY `item_id` (`item_id`),
			  FULLTEXT `search_index` (`search_index`),
			  KEY `value_id` (`value_id`)
			) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`
			';
			$this->_db->setQuery($query);
			$this->_db->execute();
		}


		/**
		 * Loop through Fields triggering onIndexAdvSearch, onIndexSearch Event handlers, this was seperated from the before save field
		 * event, so that we will update search indexes only if the above has not canceled saving OR has not canceled version approval
		 */

		$ai_query_vals = array();
		$ai_query_vals_f = array();

		if ($fields)
		{
			foreach($fields as $field)
			{
				$field_type = $field->iscore ? 'core' : $field->field_type;

				// Update (regardless of state!!) search indexes if document version is approved OR item is new
				if ($data['vstate'] == 2 || $isNew)
				{
					// Trigger plugin Event 'onIndexAdvSearch' to update field-item pair records in advanced search index
					FLEXIUtilities::call_FC_Field_Func($field_type, 'onIndexAdvSearch', array( &$field, &$field->postdata, &$item ));

					if (isset($field->ai_query_vals))
					{
						foreach ($field->ai_query_vals as $query_val)
						{
							$ai_query_vals[] = $query_val;
						}

						// Current for advanced index only
						if (isset($filterables[$field->id]))
						{
							foreach ($field->ai_query_vals as $query_val) $ai_query_vals_f[$field->id][] = $query_val;
						}
					}

					// Trigger plugin Event 'onIndexSearch' to update item 's (basic) search index record
					FLEXIUtilities::call_FC_Field_Func($field_type, 'onIndexSearch', array( &$field, &$field->postdata, &$item ));

					if (isset($field->search[$item->id]) && strlen($field->search[$item->id]))
					{
						$searchindex[] = $field->search[$item->id];
					}
				}
			}
		}

		// Remove item's old advanced search index entries
		$query = "DELETE FROM #__flexicontent_advsearch_index WHERE item_id=". $item->id;
		$this->_db->setQuery($query)->execute();

		// Store item's advanced search index entries
		$queries = array();

		if (count($ai_query_vals))
		{
			$queries[] = "INSERT INTO #__flexicontent_advsearch_index "
				." (field_id,item_id,extraid,search_index,value_id) VALUES "
				.implode(",", $ai_query_vals);

			$this->_db->setQuery($query)->execute();
		}

		// Current for advanced index only
		foreach($ai_query_vals_f as $_field_id => $_query_vals)
		{
			$queries[] = "INSERT INTO #__flexicontent_advsearch_index_field_".$_field_id
				." (field_id,item_id,extraid,search_index,value_id) VALUES "
				.implode(",", $_query_vals);
		}

		foreach ($queries as $query)
		{
			$this->_db->setQuery($query)->execute();
		}

		// Assigned created basic search index into item object
		$search_prefix = $cparams->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix
		$item->search_index = implode(' | ', $searchindex);
		if ($search_prefix && $item->search_index) $item->search_index = preg_replace('/(\b[^\s,\.]+\b)/u', $search_prefix.'$0', trim($item->search_index));

		// Check if vstate was set to 1 (no approve new version) while versioning is disabled
		if (!$use_versioning && $data['vstate']!=2) {
			$data['vstate'] = 2;
			$app->enqueueMessage('vstate cannot be set to 1 (=no approve new version) when versioning is disabled', 'notice' );
		}

		if ( $print_logging_info ) @$fc_run_times['fields_value_indexing'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;



		/**
		 * IF new version is approved, remove old version values from the field table
		 */

		if ( $print_logging_info ) $start_microtime = microtime(true);

		if ($data['vstate'] == 2)
		{
			$query = 'DELETE FROM #__flexicontent_fields_item_relations WHERE item_id = ' . (int) $item->id;
			$this->_db->setQuery($query)->execute();

			$query = 'DELETE FROM #__flexicontent_items_versions WHERE item_id = ' . (int) $item->id . ' AND version=' . ((int) $last_version + 1);
			$this->_db->setQuery($query)->execute();

			$untranslatable_fields = array();
			if ($fields) foreach($fields as $field)
			{
				if(	$field->iscore ) continue;
				if (count($assoc_item_ids) && $field->untranslatable)
				{
					// Delete field values in all translating items, if current field is untranslatable and current item version is approved
					// NOTE: item itself is not include in associated translations, no need to check for it and skip it
					if (! $mval_query)
					{
						$query = 'DELETE FROM #__flexicontent_fields_item_relations WHERE item_id IN ('.implode(',',$assoc_item_ids).') AND field_id='.$field->id;
						$this->_db->setQuery($query)->execute();
					}
					else
					{
						$untranslatable_fields[] = $field->id;
					}
				}
			}

			if (count($untranslatable_fields))
			{
				$query = 'DELETE FROM #__flexicontent_fields_item_relations WHERE item_id IN ('.implode(',',$assoc_item_ids).') AND field_id IN ('.implode(',',$untranslatable_fields) .')';
				$this->_db->setQuery($query)->execute();
			}
		}


		/**
		 * Loop through Fields saving the field values
		 */

		if ($fields)
		{
			// Do not save if versioning disabled or item has no type (version 0)
			$record_versioned_data = $use_versioning && $item->version;

			$ver_query_vals = array();
			$rel_query_vals = array();
			$rel_update_objs = array();

			// Add the new values to the database
			foreach($fields as $field)
			{
				//$qindex_values = $qindex[$field->name];
				$i = 1;

				foreach ($field->postdata as $posted_value)
				{
					// Create field obj for DB insertion
					$obj = new stdClass();
					$obj->field_id 		= !empty($field->use_field_id) ? $field->use_field_id : $field->id;
					$obj->item_id 		= !empty($field->use_item_id) ? $field->use_item_id : $item->id;
					$obj->valueorder	= !empty($field->use_valueorder) ? $field->use_valueorder : $i;
					$obj->suborder    = 1;
					$obj->version			= (int)$last_version+1;
					$use_ingroup = $field->parameters->get('use_ingroup', 0);

					// Serialize the properties of the value, normally this is redudant, since the field must have had serialized the parameters of each value already
					if ( !empty($field->use_suborder) && is_array($posted_value) )
						$obj->value = null;
					else
						$obj->value = is_array($posted_value) ? serialize($posted_value) : $posted_value;
					//$obj->qindex01 = isset($qindex_values['qindex01']) ? $qindex_values['qindex01'] : NULL;
					//$obj->qindex02 = isset($qindex_values['qindex02']) ? $qindex_values['qindex02'] : NULL;
					//$obj->qindex03 = isset($qindex_values['qindex03']) ? $qindex_values['qindex03'] : NULL;

					// -- a. Add versioning values, but do not version the 'hits' or 'state' or 'voting' fields
					if ($record_versioned_data && $field->field_type!='hits' && $field->field_type!='state' && $field->field_type!='voting')
					{
						// Insert only if value non-empty
						if ( !empty($field->use_suborder) && is_array($posted_value) )
						{
							$obj->suborder = 1;

							foreach ($posted_value as $v)
							{
								$obj->value = $v;
								//echo "<pre>"; print_r($obj); echo "</pre>";
								if (! $mval_query) $this->_db->insertObject('#__flexicontent_items_versions', $obj);
								else $ver_query_vals[] = "("
									.$obj->field_id. "," .$obj->item_id. "," .$obj->valueorder. "," .$obj->suborder. "," .$obj->version. "," .$this->_db->Quote($obj->value)
								.")";
								$obj->suborder++;
							}
							unset($v);
						}

						// ISSET will also skip -null-, but valueorder will be incremented (e.g. we want this for fields in a field group)
						elseif (isset($obj->value) && strlen(trim($obj->value)))
						//else if ( isset($obj->value) && ($use_ingroup || strlen(trim($obj->value))) )
						{
							//echo "<pre>"; print_r($obj); echo "</pre>";
							if (! $mval_query) $this->_db->insertObject('#__flexicontent_items_versions', $obj);
							else $ver_query_vals[] = "("
								.$obj->field_id. "," .$obj->item_id. "," .$obj->valueorder. "," .$obj->suborder. "," .$obj->version. "," .$this->_db->Quote($obj->value)
								//. "," .$this->_db->Quote($obj->qindex01) . "," .$this->_db->Quote($obj->qindex02) . "," .$this->_db->Quote($obj->qindex03)
							.")";
						}
					}
					//echo $field->field_type." - ".$field->name." - ".strlen(trim($obj->value))." ".$field->iscore."<br/>";

					// -- b. If item is new OR version is approved, AND field is not core (aka stored in the content table or in special table), then add field value to field values table
					if(	( $isNew || $data['vstate']==2 ) && !$field->iscore )
					{
						// UNSET version it it used only verion data table, and insert only if value non-empty
						unset($obj->version);

						if (!empty($field->use_suborder) && is_array($posted_value))
						{
							$obj->suborder = 1;
							foreach ($posted_value as $v)
							{
								$obj->value = $v;

								if (!$mval_query)
								{
									$this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
									flexicontent_db::setValues_commonDataTypes($obj);
								}
								else
								{
									$rel_query_vals[] = "("
										.$obj->field_id. "," .$obj->item_id. "," .$obj->valueorder. "," .$obj->suborder. "," .$this->_db->Quote($obj->value)
									.")";
									$rel_update_objs[] = clone($obj);
								}

								$obj->suborder++;
							}
							unset($v);
						}

						// ISSET will also skip -null-, but valueorder will be incremented (e.g. we want this for fields in a field group)
						elseif (isset($obj->value) && strlen(trim($obj->value)))
						//else if ( isset($obj->value) && ($use_ingroup || strlen(trim($obj->value))) )
						{
							if (! $mval_query)
							{
								$this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
								flexicontent_db::setValues_commonDataTypes($obj);
							}
							else
							{
								$rel_query_vals[] = "("
									.$obj->field_id. "," .$obj->item_id. "," .$obj->valueorder. "," .$obj->suborder. "," .$this->_db->Quote($obj->value)
									//. "," .$this->_db->Quote($obj->qindex01) . "," .$this->_db->Quote($obj->qindex02) . "," .$this->_db->Quote($obj->qindex03)
								.")";
								$rel_update_objs[] = clone($obj);
							}

							// Save field value in all translating items, if current field is untranslatable
							// NOTE: item itself is not include in associated translations, no need to check for it and skip it
							if (count($assoc_item_ids) && $field->untranslatable)
							{
								foreach($assoc_item_ids as $t_item_id)
								{
									//echo "setting Untranslatable value for item_id: ".$t_item_id ." field_id: ".$field->id."<br/>";
									$obj->item_id = $t_item_id;
									if (! $mval_query)
									{
										$this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
										flexicontent_db::setValues_commonDataTypes($obj);
									}
									else
									{
										$rel_query_vals[] = "("
											.$obj->field_id. "," .$obj->item_id. "," .$obj->valueorder. "," .$obj->suborder. "," .$this->_db->Quote($obj->value)
											//. "," .$this->_db->Quote($obj->qindex01) . "," .$this->_db->Quote($obj->qindex02) . "," .$this->_db->Quote($obj->qindex03)
										.")";
										$rel_update_objs[] = clone($obj);
									}
								}
							}
						}
					}
					$i++;
				}
			}


			/**
			 * Insert values in item fields versioning table
			 */

			if (count($ver_query_vals))
			{
				$query = "INSERT INTO #__flexicontent_items_versions "
					." (field_id,item_id,valueorder,suborder,version,value"
					//.",qindex01,qindex02,qindex03"
					.") VALUES "
					."\n".implode(",\n", $ver_query_vals);

				$this->_db->setQuery($query)->execute();
			}


			/**
			 * Insert values in item fields relation table
			 */

			if (count($rel_query_vals))
			{
				$query = "INSERT INTO #__flexicontent_fields_item_relations "
					." (field_id,item_id,valueorder,suborder,value"
					//.",qindex01,qindex02,qindex03"
					.") VALUES "
					."\n".implode(",\n", $rel_query_vals);

				$this->_db->setQuery($query)->execute();
			}

			// Update values of common data types
			foreach($rel_update_objs as $obj) flexicontent_db::setValues_commonDataTypes($obj);
			//echo "<pre>"; print_r($rel_update_objs); exit;


			/**
			 * Save other versioned item data into the field versioning table
			 */

			// a. Save a version of item properties that do not have a corresponding CORE Field
			if ( $record_versioned_data )
			{
				$obj = new stdClass();
				$obj->field_id 		= -2;  // ID of Fake Field used to contain item properties not having a corresponding CORE field
				$obj->item_id 		= $item->id;
				$obj->valueorder	= 1;
				$obj->suborder	  = 1;
				$obj->version			= (int)$last_version+1;

				$item_data = array();
				$iproperties = array('alias', 'catid', 'metadesc', 'metakey', 'metadata', 'attribs', 'urls', 'images');
				foreach ( $iproperties as $iproperty) $item_data[$iproperty] = $item->{$iproperty};

				$obj->value = serialize( $item_data );
				$this->_db->insertObject('#__flexicontent_items_versions', $obj);
			}

			// b. Finally save a version of the posted Falang translated data for J1.5, if such data are editted inside the item edit form
			/*if ( FLEXI_FISH && !empty($data['jfdata']) && $record_versioned_data )
			{
				$obj = new stdClass();
				$obj->field_id 		= -1;  // ID of Fake Field used to contain the Falang translated item data
				$obj->item_id 		= $item->id;
				$obj->valueorder	= 1;
				$obj->suborder    = 1;
				$obj->version			= (int)$last_version+1;

				$item_lang = substr($item->language ,0,2);
				$data['jfdata'][$item_lang]['title'] = $item->title;
				$data['jfdata'][$item_lang]['alias'] = $item->alias;
				$data['jfdata'][$item_lang]['text'] = $item->text;
				$data['jfdata'][$item_lang]['metadesc'] = $item->metadesc;
				$data['jfdata'][$item_lang]['metakey'] = $item->metakey;
				$obj->value = serialize($data['jfdata']);
				$this->_db->insertObject('#__flexicontent_items_versions', $obj);
			}*/
		}

		if ( $print_logging_info ) @$fc_run_times['fields_value_saving'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;


		/**
		 * Trigger onAfterSaveField Event
		 */

		if ( $fields )
		{
			if ( $print_logging_info ) $start_microtime = microtime(true);
			foreach($fields as $field)
			{
				$field_type = $field->iscore ? 'core' : $field->field_type;
				$file_data  = isset($files[$field->name]) ? $files[$field->name] : null;  // Pass a copy field's FILE data
				$result = FLEXIUtilities::call_FC_Field_Func($field_type, 'onAfterSaveField', array( &$field, &$field->postdata, &$file_data, &$item ));
				// *** $result is ignored
			}
			if ( $print_logging_info ) @$fc_run_times['onAfterSaveField_event'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		}

		return true;
	}


	/**
	 * Method to apply a NEW CURRENT version when saving an APPROVED item version
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function applyCurrentVersion(&$item, &$data, $createonly=false)
	{
		$app     = JFactory::getApplication();
		$user    = JFactory::getUser();
		$cparams = $this->_cparams;
		$isNew   = !$item->id;

		$editjf_translations = $cparams->get('editjf_translations', 0);

		// ***
		// *** Check and store item in the db
		// ***

		// Make sure the data is valid
		if (!$item->check())
		{
			$this->setError($item->getError());
			return false;
		}

		if (!$item->store())
		{
			$this->setError($item->getError());
			return false;
		}

		// Set model properties
		$this->_id     = $item->id;
		$this->_record = & $item;
		$this->_typeid = $item->type_id;
		$this->getComponentTypeParams();


		// ***
		// *** Assign default workflow to new items
		// ***

		if (FLEXI_J40GE && $isNew)
		{
		  flexicontent_db::assign_default_WF($item->id, $item);
		}


		// ***
		// *** Update language Associations
		// ***

		$this->saveAssociations($item, $data);


		// ***
		// *** Save access information
		// ***

		// Rules for J1.6+ are handled in the JTABLE class of the item with overriden JTable functions: bind() and store()


		// ***
		// *** If creating only return ...
		// ***

		if ($createonly) return true;


		// ***
		// *** Save Falang data in the db
		// ***

		if ( FLEXI_FISH && $editjf_translations==2 )   // 0:disable with warning about found translations,  1:disable without warning about found translations,  2:edit-save translations,
		{
			$this->_saveJFdata( $data['jfdata'], $item );
		}


		// ***
		// *** Delete old tag relations and store the new ones
		// ***

		if (isset($data['tags']))
		{
			echo empty($this->debug_tags) ? null : '<b>' . __FUNCTION__ . '()</b><blockquote> Replacing Tags with: ' . print_r($data['tags'], true) . '</blockquote>';
			$this->saveFcTagsAssignments($data['tags'], $item->id, $_replace = true);
			$this->saveJTagsAssignments($data['tags'], $item->id);
		}


		// ***
		// *** Delete only category relations which are not part of the categories array anymore to avoid loosing ordering
		// ***

		$cats = & $data['categories'];
		$query 	= 'DELETE FROM #__flexicontent_cats_item_relations'
			. ' WHERE itemid = ' . (int) $item->id
			. ($cats ? ' AND catid NOT IN (' . implode(', ', $cats) . ')' : '')
			;
		$this->_db->setQuery($query)->execute();

		// Get an array of the item's used categories (already assigned in DB)
		$query 	= 'SELECT catid'
			. ' FROM #__flexicontent_cats_item_relations'
			. ' WHERE itemid = ' . (int) $item->id
			;
		$non_altered_cats = $this->_db->setQuery($query)->loadColumn();

		// Insert only the new records
		$cat_vals = array();
		foreach($cats as $catid)
		{
			if (!in_array($catid, $non_altered_cats))
			{
				$cat_vals[] = '(' . (int) $catid . ',' . (int) $item->id . ')';
			}
		}

		if ( !empty($cat_vals) )
		{
			$query 	= 'INSERT INTO #__flexicontent_cats_item_relations'
				. ' (catid, itemid)'
				. ' VALUES ' . implode(',', $cat_vals)
				;
			$this->_db->setQuery($query)->execute();
		}

		return true;
	}


	/**
	 * Method to save Falang item translation data
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _saveJFdata( & $jfdata_arr, & $item)
	{
		//$user_currlang = flexicontent_html::getUserCurrentLang();                  // user's -current- language
		//$default_sitelang = substr(flexicontent_html::getSiteDefaultLang(),0,2);   // site (frontend) -content- language
		//$item_lang = substr($item->language ,0,2);                                 // item language
		$nn_content_tbl = 'falang_content';

		$db = $this->_db;
		$app = JFactory::getApplication();
		$dbprefix = $app->getCfg('dbprefix');
		$dbtype   = $app->getCfg('dbtype');

		if ( in_array($dbtype, array('mysqli','mysql')) )
		{
			$query = "UPDATE #__content SET title=".$db->Quote($item->title).",  alias=".$db->Quote($item->alias).",  introtext=".$db->Quote($item->introtext)
				.",  `fulltext`=".$db->Quote($item->fulltext).",  images=".$db->Quote($item->images).",  metadesc=".$db->Quote($item->metadesc).",  metakey=".$db->Quote($item->metakey)
				.", publish_up=".$db->Quote($item->publish_up).",  publish_down=".$db->Quote($item->publish_down).",  attribs=".$db->Quote($item->attribs)." WHERE id=".$db->Quote($item->id);
			//echo $query."<br/>\n";

			//$query = $db->replacePrefix($query);
			$query = str_replace("#__", $dbprefix, $query);
			$db_connection = $db->getConnection();
			//echo "<pre>"; print_r($query); echo "\n\n";

			if ($dbtype == 'mysqli')
			{
				$result = mysqli_query( $db_connection , $query );
				if ($result===false)
				{
					$app->enqueueMessage(__FUNCTION__.'():: '.mysqli_error($db_connection), 'error');
					return false;
				}
			}
			else if ($dbtype == 'mysql')
			{
				$result = mysql_query( $query, $db_connection  );
				if ($result===false)
				{
					$app->enqueueMessage(__FUNCTION__.'():: '.mysql_error($db_connection), 'error');
					return false;
				}
			}
			else
			{
				$msg = 'unreachable code in '.__FUNCTION__.'(): direct db query, unsupported DB TYPE';
				throw new Exception($msg, 500);
			}
		}

		$modified = $item->modified ? $item->modified : $item->created;
		$modified_by = $item->modified_by ? $item->modified_by : $item->created_by;

		// Get active languages
		$langs	= FLEXIUtilities::getLanguages('shortcode');

		// Loop through Falang translations storing them
		foreach($jfdata_arr as $shortcode => $jfdata)
		{
			if (!isset($langs->$shortcode))
			{
				continue;
			}

			// Reconstruct (main)text field if it has splitted up e.g. to seperate editors per tab
			if (!empty($jfdata['text']) && is_array($jfdata['text']))
			{
				// Force a readmore at the end of text[0] (=before TABs text) ... so that the before TABs text acts as introtext
				$jfdata['text'][0] .= (preg_match('#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i', $jfdata['text'][0]) == 0) ? ("\n".'<hr id="system-readmore" />') : "" ;
				$jfdata['text'] = implode('', $jfdata['text']);
			}
			else if ( empty($jfdata['text']) )
			{
				$jfdata['text'] = '';
			}

			$jfdata['title'] = trim($jfdata['title']);

			$jfdata['lang_code'] = $langs->$shortcode->code;
			$jfdata['alias'] = $this->getSafeUniqueAlias($item, $jfdata);

			// Search for the {readmore} tag and split the text up accordingly.
			$this->splitText($jfdata);

			// Delete existing Falang translation data for the current item
			$query = 'DELETE FROM  #__' . $nn_content_tbl
				. ' WHERE language_id = ' . (int) $langs->$shortcode->id
				. '  AND reference_table = ' . $db->Quote($this->records_dbtbl)
				. '  AND reference_id = ' . (int) $item->id
				;
			$db->setQuery($query)->execute();

			// Apply new translation data
			$translated_fields = array(
				'title',
				'alias',
				'introtext',
				'fulltext',
				'metadesc',
				'metakey'
			);
			foreach ($translated_fields as $fieldname)
			{
				// Check that data exist and they are non-zero length -string-
				if ( !isset($jfdata[$fieldname]) || !is_string($jfdata[$fieldname]) || !strlen($jfdata[$fieldname]) )
				{
					continue;
				}
				$query = 'INSERT INTO #__' . $nn_content_tbl
					. ' (language_id, reference_id, reference_table, reference_field, value, original_value, original_text, modified, modified_by, published) '
					. ' VALUES ( '
						. (int) $langs->$shortcode->id . ', '
						. (int) $item->id . ', '
						. $db->Quote($this->records_dbtbl) . ', '
						. $db->Quote($fieldname) . ', '
						. $db->Quote($jfdata[$fieldname]) . ', '
						. $db->Quote(md5($item->$fieldname)) . ', '
						. $db->Quote($item->$fieldname) . ', '
						. $db->Quote($modified) . ', '
						. $db->Quote($modified_by) . ', '
						. '1)';
				$db->setQuery($query)->execute();
			}
		}

		return true;
	}


	/**
	 * Method to load content article parameters
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	function _loadItemParams($force=false)
	{
		if (!$force && !empty($this->_record->parameters)) return;

		$app = JFactory::getApplication();
		$menu = $app->getMenu()->getActive();  // Retrieve currently active menu item (NOTE: this applies when Itemid variable or menu item alias exists in the URL)
		$isnew = !$this->_id;


		/**
		 * Retrieve RELATED parameters that will be merged into item's parameters
		 */

		// Retrieve COMPONENT parameters
		$compParams = JComponentHelper::getComponent('com_flexicontent')->params;

		// Retrieve parameters of current category (NOTE: this applies when cid variable exists in the URL)
		$catParams = '';
		if ($this->_cid)
		{
			$query = 'SELECT c.title, c.params FROM #__categories AS c WHERE c.id = ' . (int) $this->_cid;
			$this->_db->setQuery($query);
			$catData = $this->_db->loadObject();
			$catParams = $catData->params;
			$this->_record->category_title = $catData->title;
		}
		$catParams = $this->_new_JRegistry($catParams);

		// Retrieve/Create item's Content Type parameters
		$typeParams = $this->getTypeparams();
		$typeParams = $this->_new_JRegistry($typeParams);

		// Create item parameters
		if ( !is_object($this->_record->attribs) )
		{
			try
			{
				$itemParams = new JRegistry($this->_record->attribs);
			}
			catch (Exception $e)
			{
				$itemParams = flexicontent_db::check_fix_JSON_column('attribs', 'content', 'id', $this->_record->id, $this->_record->attribs);
			}
		}
		else
		{
			$itemParams = $this->_record->attribs;
		}

		/**
		 * Bug fix for bad parameter merge code in item model for parameters not present in the form
		 */
		$itemParams = $this->_new_JRegistry($itemParams);

		// Retrieve Layout's parameters, also deciding the layout
		if ($app->isClient('administrator') || !empty($this->isForm))
		{
			$ilayout = $itemParams->get('ilayout', $typeParams->get('ilayout', 'grid'));
			$this->setItemLayout($ilayout);
		}
		else
		{
			$this->decideLayout($compParams, $typeParams, $itemParams, $catParams);
		}

		$layoutParams = $this->getLayoutparams();
		$layoutParams = $this->_new_JRegistry($layoutParams);  //print_r($layoutParams);


		/**
		 * Start merging of parameters, OVERRIDE ORDER: layout(template-manager)/component/category/type/item/menu/access
		 */

		// a0. Merge Layout parameters into the page configuration
		$params = new JRegistry();
		$params->merge($layoutParams);

		// a1. Start with empty registry, then merge COMPONENT parameters
		$params->merge($compParams);

		// b. Merge parameters from current category, but prevent some settings from propagating ... to the item, that are meant for
		//    category view only, these are legacy settings that were removed from category.xml, but may exist in saved configurations
		
		// Do not merge ALL category parameters !! into item, as they are 99% irrelevant
		if (0)
		{
			$catParams->set('show_title', '');
			$catParams->set('show_editbutton', '');
			$params->merge($catParams);
		}
		else
		{
			if (strlen($catParams->get('automatic_pathways', ''))) $params->set('automatic_pathways', $catParams->get('automatic_pathways'));
			if (strlen($catParams->get('add_canonical', ''))) $params->set('add_canonical', $catParams->get('add_canonical'));
			if (strlen($catParams->get('microdata_itemtype_cat', ''))) $params->set('microdata_itemtype', $catParams->get('microdata_itemtype_cat'));
			if (strlen($catParams->get('comments', ''))) $params->set('comments', $catParams->get('comments'));
		}

		// c. Merge TYPE parameters into the page configuration
		$params->merge($typeParams);

		// d. Merge ITEM parameters into the page configuration
		$params->merge($itemParams);

		// e. Merge ACCESS permissions into the page configuration
		$accessperms = $this->getItemAccess();
		$params->merge($accessperms);

		// d. Merge the active menu parameters, verify menu item points to current FLEXIcontent object
		if ( $menu && !empty($this->mergeMenuParams) )
		{
			if (!empty($this->isForm))
			{
				$this->menu_matches = false;
				$view_ok = 'item' == @$menu->query['view'] || 'article' == @$menu->query['view'];
				$this->menu_matches = $view_ok;
			}
			else
			{
				$view_ok = 'item' == @$menu->query['view'] || 'article' == @$menu->query['view'];
				$cid_ok  = $app->input->get('cid', 0, 'INT') == (int) @$menu->query['cid'];
				$id_ok   = $app->input->get('id', 0, 'INT')  == (int) @$menu->query['id'];
				$this->menu_matches = $view_ok /*&& $cid_ok*/ && $id_ok;
			}
		}

		// Active menu did not match to current item
		else
		{
			$this->menu_matches = false;
		}

		// MENU ITEM matched, merge parameters and use its page heading (but use menu title if the former is not set)
		if ( $this->menu_matches )
		{
			$params->merge($menu->getParams());
			$default_heading = $menu->title;

			// Cross set (show_) page_heading / page_title for compatibility of J2.5+ with J1.5 template (and for J1.5 with J2.5 template)
			$params->def('page_heading', $params->get('page_title',   $default_heading));
			$params->def('page_title',   $params->get('page_heading', $default_heading));
		  $params->def('show_page_heading', $params->get('show_page_title',   0));
		  $params->def('show_page_title',   $params->get('show_page_heading', 0));
		}

		// MENU ITEM did not match, clear page title (=browser window title) and page heading so that they are calculated below
		else
		{
			// Clear some menu parameters
			//$params->set('pageclass_sfx',	'');  // CSS class SUFFIX is behavior, so do not clear it ?

			// Calculate default page heading (=called page title in J1.5), which in turn will be document title below !! ...
			$default_heading = empty($this->isForm) ? $this->_record->title :
				(!$isnew ? JText::_( 'FLEXI_EDIT' ) : JText::_( 'FLEXI_NEW' ));

			// Decide to show page heading (=J1.5 page title), there is no need for this in item view
			$show_default_heading = 0;

			// Set both (show_) page_heading / page_title for compatibility of J2.5+ with J1.5 template (and for J1.5 with J2.5 template)
			$params->set('page_title',   $default_heading);
			$params->set('page_heading', $default_heading);
		  $params->set('show_page_heading', $show_default_heading);
			$params->set('show_page_title',   $show_default_heading);
		}

		// Prevent showing the page heading if (a) IT IS same as item title and (b) item title is already configured to be shown
		if ( $params->get('show_title', 1) ) {
			if ($params->get('page_heading') == $this->_record->title) $params->set('show_page_heading', 0);
			if ($params->get('page_title')   == $this->_record->title) $params->set('show_page_title',   0);
		}

		// Also convert metadata property string to registry object
		try
		{
			$this->_record->metadata = new JRegistry($this->_record->metadata);
		}
		catch (Exception $e)
		{
			$this->_record->metadata = flexicontent_db::check_fix_JSON_column('metadata', 'content', 'id', $this->_record->id);
		}

		// Manually apply metadata from type parameters ... currently only 'robots' makes sense to exist per type
		if ( !$this->_record->metadata->get('robots') )   !$this->_record->metadata->set('robots', $typeParams->get('robots'));


		/**
		 * Finally set 'parameters' property of the item
		 */
		$this->_record->parameters = $params;
	}


	/**
	 * Method to fetch used tags IDs as an array when performing an edit action
	 *
	 * @param int id
	 * @return array
	 * @since 1.0
	 */
	function getUsedtagsIds($item_id=0)
	{
		// Allow retrieval of tags of any item
		$item_id = $item_id ? $item_id : $this->_id;

		// *** NOTE: this->_record->tags may already contain a VERSIONED array of values !!!
		if( $this->_id == $item_id && !empty($this->_record->tags) ) {
			// Return existing tags of current item
			return $this->_record->tags;
		}
		else if ($item_id) {
			// Not current item, or current item's tags are not set
			$query = "SELECT tid FROM #__flexicontent_tags_item_relations WHERE itemid ='".$item_id."'";
			$this->_db->setQuery($query);
			$tags = $this->_db->loadColumn();
			if ($this->_id == $item_id) {
				// Retrieved tags of current item, set them
				$this->_record->tags = $tags;
			}
			$tags = array_reverse($tags);
			return $tags;
		} else {
			return array();
		}
	}


	/**
	 * Method to get the list of the used tags
	 *
	 * @param 	array
	 * @return 	array
	 * @since 	3.0.15
	 */
	function getTagsByIds($tagIds, $indexed = true)
	{
		if (empty($tagIds))
		{
			return array();
		}

		$lang_code = !empty($this->_record->language) ? $this->_record->language : JFactory::getLanguage()->getTag();

		$query = $this->_db->getQuery(true)
			->select('la.*')
			->from('#__languages AS la')
			->where('la.lang_code = ' . $this->_db->quote($lang_code))
			;
		$lang = $this->_db->setQuery($query)->loadObject();

		$query 	= 'SELECT t.*, t.id as tid,'
			. (!FLEXI_FALANG || !$lang ? ' "" AS translated_text' : ' fa.value AS translated_text')
			. ' FROM #__flexicontent_tags as t '
			. (!FLEXI_FALANG || !$lang ? '' : ' LEFT JOIN #__falang_content AS fa ON fa.reference_table = "tags" AND fa.reference_field = "title" AND fa.reference_id = t.jtag_id'
				. ' AND fa.language_id = ' . (int) $lang->lang_id)
			. ' WHERE t.id IN (\'' . implode("','", $tagIds).'\')'
			. ' ORDER BY name ASC'
			;
		$tagsData = $this->_db->setQuery($query)->loadObjectList('tid');

		if ($indexed) return $tagsData;

		$tags = array();
		foreach($tagIds as $tid)
		{
			if ( !empty($tagsData[$tid]) )
			{
				$tags[] = $tagsData[$tid];
			}
		}

		return $tags;
	}


	/**
	 * Method to get the list of the used tags
	 *
	 * @param 	array
	 * @return 	array
	 * @since 	1.5.2
	 */
	function getUsedtagsData($tagIds, $indexed = false)
	{
		return $this->getTagsByIds($tagIds, $indexed);
	}


	/**
	 * Method to get a list of all available tags Data
	 *
	 * @param 	array
	 * @return 	array
	 * @since 	1.5.2
	 */
	function getAlltags()
	{
		$query = 'SELECT * FROM #__flexicontent_tags ORDER BY name';
		$tags = $this->_db->setQuery($query)->loadObjectList();

		return $tags;
	}


	/**
	 * Method to restore an old version
	 *
	 * @param int id
	 * @param int version
	 * @return int
	 * @since 1.5
	 */
	// !!!!!!!!!!!!!!!!! INCOMPLETE !!!!!!!!!!!!!!!!!!!!!
	function restore($version, $id)
	{
		// delete current field values
		$query = 'DELETE FROM #__flexicontent_fields_item_relations WHERE item_id = '.(int)$id;
		$this->_db->setQuery($query);
		$this->_db->execute();

		// load field values from the version to restore
		$query 	= 'SELECT item_id, field_id, value, valueorder, suborder, iscore'
				. ' FROM #__flexicontent_items_versions as iv'
				. ' LEFT JOIN #__flexicontent_fields as f ON iv.field_id=f.id'
				. ' WHERE item_id = '. (int)$id
				. ' AND version = '. (int)$version
				;
		$this->_db->setQuery($query);
		$versionrecords = $this->_db->loadObjectList();

		// restore the old values
		foreach ($versionrecords as $versionrecord)
		{
			if (!(int)$versionrecord->iscore)
			{
				$this->_db->insertObject('#__flexicontent_fields_item_relations', $versionrecord);
				flexicontent_db::setValues_commonDataTypes($versionrecord);
			}
		}
		$query = "UPDATE #__content SET version='$version' WHERE id='$id';";
		$this->_db->setQuery($query);
		$this->_db->execute($query);
		// handle the maintext not very elegant but functions properly
		$row = $this->getTable();
		$row->load($id);

		if (@$versionrecords[0]->value) {
			// Search for the {readmore} tag and split the text up accordingly.
			$row->text = $versionrecords[0]->value;
			$this->splitText($row);
		}
		//$row->store();
	}


	/**
	 * Method to fetch tags according to a given mask
	 *
	 * @return  An array of tag data objects
	 *
	 * @since 1.5
	 */
	function gettags($mask="", $limit = 100)
	{
		$query = $this->_db->getQuery(true)
			->select('*')
			->from('#__flexicontent_tags')
			->where('published = 1')
			->order($this->_db->quoteName('name'))
			->setLimit((int) $limit, $offset = 0);

		if ($mask)
		{
			$escaped_mask = $this->_db->escape($mask, true);
			$quoted_escaped_mask = $this->_db->Quote('%' . $escaped_mask . '%', false);

			$query->where($this->_db->quoteName('name') . ' LIKE ' . $quoted_escaped_mask);
		}

		$tags = $this->_db->setQuery($query)->loadObjectList();

		return $tags;
	}


	/**
	 * Method to reset hits
	 *
	 * @param int id
	 * @return int
	 * @since 1.0
	 */
	function resetHits($id = 0)
	{
		$id = (int) ($id ?: $this->_id);

		if (!$id)
		{
			return;
		}

		$row = $this->getTable();
		$row->load($id);
		$row->hits = 0;
		$row->store();

		return $row->id;
	}


	/**
	 * Method to reset votes
	 *
	 * @param int id
	 * @return int
	 * @since 1.0
	 */
	function resetVotes($id = 0)
	{
		$id = (int) ($id ?: $this->_id);

		if (!$id)
		{
			return;
		}

		// Delete main vote type
		$query = 'DELETE FROM #__content_rating WHERE content_id = ' . (int) $id;
		$this->_db->setQuery($query)->execute();

		// Delete extra vote types
		$query = 'DELETE FROM #__flexicontent_items_extravote WHERE content_id = ' . (int) $id;
		$this->_db->setQuery($query)->execute();
	}


	/**
	 * Method to get votes
	 *
	 * @param int id
	 * @return object
	 * @since 1.0
	 */
	public function getVotes($cids = null)
	{
		if ($cids && !is_array($cids))
		{
			$cids = array($cids);
		}

		elseif (!$cids && $this->_id)
		{
			$cids = array($this->_id);
		}

		$cids = ArrayHelper::toInteger($cids);

		if (!$cids)
		{
			return array();
		}

		$query = $this->_db->getQuery(true)
			->select('*')
			->from('#__content_rating')
			->where('content_id IN (' . implode(', ', $cids) . ')');

		$votes = $this->_db->setQuery($query)->loadObjectList('content_id');

		$query = $this->_db->getQuery(true)
			->select('*, field_id as extra_id')
			->from('#__flexicontent_items_extravote')
			->where('content_id IN (' . implode(', ', $cids) . ')');

		$extra_votes = $this->_db->setQuery($query)->loadObjectList();

		// Assign each item 's extra votes to the item's votes as member variable "extra"
		foreach ($extra_votes as $extra_vote)
		{
			$votes[$extra_vote->content_id]->extra[$extra_vote->extra_id] = $extra_vote;
		}

		// Compatibility for legacy calling of the method
		if (isset($votes[$this->_id]))
		{
			$votes[0] = $votes[$this->_id];
		}

		return $votes;
	}


	public function getRatingDisplay($id = 0)
	{
		$id = (int) ($id ?: $this->_id);

		$votes = $this->getVotes($id);

		if ($votes)
		{
			$rating_resolution = $this->getVotingResolution($id);
			$rating_sum = (int) $votes[$id]->rating_sum;
			$rating_count = (int) $votes[$id]->rating_count;

			$score = $rating_sum / $rating_count * (100 / $rating_resolution);
			$score = round($score, 2);
			$vote  = $rating_count . ' ' . JText::_($rating_count > 1 ? 'FLEXI_VOTES' : 'FLEXI_VOTE');
			return $score . '% | ' . $vote;
		}

		return JText::_('FLEXI_NOT_RATED_YET');
	}


	function getVotingResolution($id = 0)
	{
		static $rating_resolution = array();
		$id = (int) ($id ?: $this->_id);
		if (isset($rating_resolution[$id])) return $rating_resolution[$id];

		$this->_db->setQuery('SELECT * FROM #__flexicontent_fields WHERE field_type="voting"');
		$field = $this->_db->loadObject();
		$item = JTable::getInstance( $type = 'flexicontent_items', $prefix = '', $config = array() );
		$item->load( $id );
		FlexicontentFields::loadFieldConfig($field, $item);

		$_rating_resolution = (int)$field->parameters->get('rating_resolution', 5);
		$_rating_resolution = $_rating_resolution >= 5   ?  $_rating_resolution  :  5;
		$_rating_resolution = $_rating_resolution <= 100 ?  $_rating_resolution  :  100;
		$rating_resolution[$id] = $_rating_resolution;

		return $rating_resolution[$id];
	}


	/**
	 * Method to get hits
	 *
	 * @param int id
	 * @return int
	 * @since 1.0
	 */
	function gethits($id = 0)
	{
		$id = (int) ($id ?: $this->_id);

		$this->_db->setQuery('SELECT hits FROM #__content WHERE id = ' . (int) $id);
		return $this->_db->loadResult();
	}



	/**
	 * Method to get subscriber count
	 *
	 * @TODO add the notification as an option with a checkbox in the favourites screen
	 * @return object
	 * @since 1.5
	 */
	function getSubscribersCount($id = 0)
	{
		static $subscribers = array();
		$id = (int) ($id ?: $this->_id);
		if ( isset($subscribers[$id]) ) return $subscribers[$id];

		$query	= 'SELECT COUNT(*)'
				.' FROM #__flexicontent_favourites AS f'
				.' LEFT JOIN #__users AS u'
				.' ON u.id = f.userid'
				.' WHERE f.itemid = ' . (int) $id
				.'  AND u.block=0 ' //.' AND f.notify = 1'
				;
		$this->_db->setQuery($query);
		$subscribers[$id] = $this->_db->loadResult();
		return $subscribers[$id];
	}


	/**
	 * Find the item type for a given or for current item ID, ... verifying that the type exists ...
	 *
	 * @return array
	 * @since 1.5
	 */
	function getItemType()
	{
		static $itemTypes = array();
		static $knownTypes = null;

		if ($knownTypes === null)
		{
			$query = 'SELECT id, name FROM #__flexicontent_types';
			$knownTypes = $this->_db->setQuery($query)->loadObjectList('id');
		}
		$knownTypes[0] = (object) array('id' => 0, 'name'=> null);

		// New item, just verify item type exists
		if ( !$this->_id )
		{
			return isset($knownTypes[$this->_typeid])
				? $knownTypes[$this->_typeid]
				: (object) array('id' => 0, 'name'=> null);
		}

		// Existing item, return its type if already known
		elseif (isset($itemTypes[$this->_id]))
		{
			return $itemTypes[$this->_id];
		}

		// Existing item, get its type
		$query = 'SELECT ie.type_id'
			. ' FROM #__flexicontent_items_ext as ie'
			. ' JOIN #__flexicontent_types as t ON ie.type_id=t.id'
			. ' WHERE ie.item_id = ' . (int) $this->_id;
		$typeID = (int) $this->_db->setQuery($query)->loadResult();

		// Use configured type if existing item has no type
		if (!$typeID)
		{
			return isset($knownTypes[$this->_typeid])
				? $knownTypes[$this->_typeid]
				: $knownTypes[0];
		}

		$itemTypes[$this->_id] = isset($knownTypes[$typeID])
			? $knownTypes[$typeID]
			: $knownTypes[0];

		return $itemTypes[$this->_id];
	}


	/**
	 * Method to get used categories when performing an edit action
	 *
	 * @return array
	 * @since 1.0
	 */
	function getCatsselected($item_id = 0)
	{
		// Allow retrieval of categories of any item
		$item_id = (int) ($item_id ?: $this->_id);

		// Return existing categories of current item
		// NOTE: 'categories' property may already contain a VERSIONED array of values
		if( $this->_id == $item_id && !empty($this->_record->categories) )
		{
			return $this->_record->categories;
		}

		// Not current item, or current item's categories are not set
		else if ($item_id)
		{
			$query = 'SELECT tid FROM #__flexicontent_cats_item_relations WHERE itemid = ' . (int) $item_id;
			$categories = $this->_db->setQuery($query)->loadColumn();

			if ($this->_id == $item_id)
			{
				// Retrieved categories of current item, set them
				$this->_record->categories = $categories;

				// Also set 'cats' which is alias of categories (possibly used by CORE plugin for displaying in frontend)
				$this->_record->cats = & $this->_record->categories;
			}

			return $categories;
		}

		// Zero item_id return empty array
		else
		{
			return array();
		}
	}


	/**
	 * Method to get the type parameters of an item
	 *
	 * @return string
	 * @since 1.5
	 */
	function getTypeparams($forced_typeid = 0)
	{
		$typeid = $forced_typeid ?: $this->_typeid;

		return flexicontent_db::getTypeAttribs(false, $typeid, 0);
	}


	/**
	 * Method to get the layout parameters of an item
	 *
	 * @return string
	 * @since 3.0
	 */
	function getLayoutparams($force = false)
	{
		return $this->_ilayout ? flexicontent_tmpl::getLayoutparams('items', $this->_ilayout, '', $force) : '';
	}


	/**
	 * Method to get types list
	 *
	 * @return array
	 * @since 1.5
	 */
	function getTypeslist ( $type_ids=false, $check_perms = false, $published=true )
	{
		return flexicontent_html::getTypesList( $type_ids, $check_perms, $published);
	}


	/**
	 * Method to retrieve the value of a core field for a specified item version
	 *
	 * @return array
	 * @since 1.5
	 */
	function getCoreFieldValue($field, $version = 0, $old_item=null)
	{
		if ($old_item) {
			$item = $old_item;
		} else if (isset($this->_record)) {
			$item = $this->_record;
		} else {
			$item = $this->getItem();  // This fuction calls the load item function for existing item and init item function in the case of new item
		}

		switch ($field->field_type) {
			case 'created': // created
			$field_value = array($item->created);
			break;

			case 'createdby': // created by
			$field_value = array($item->created_by);
			break;

			case 'modified': // modified
			$field_value = array($item->modified);
			break;

			case 'modifiedby': // modified by
			$field_value = array($item->modified_by);
			break;

			case 'title': // title
			$field_value = array($item->title);
			break;

			case 'hits': // hits
			$field_value = array($item->hits);
			break;

			case 'type': // document type
			$field_value = array($item->type_id);
			break;

			case 'version': // version
			$field_value = array($item->version);
			break;

			case 'state': // publication state
			$field_value = array($item->state);
			break;

			case 'voting': // voting button // remove dummy value in next version for legacy purposes
			$field_value = array('button'); // dummy value to force display
			break;

			case 'favourites': // favourites button // remove dummy value in next version for legacy purposes
			$field_value = array('button'); // dummy value to force display
			break;

			case 'score': // voting score // remove dummy value in next version for legacy purposes
			$field_value = array('button'); // dummy value to force display
			break;

			case 'categories': // assigned categories
			$field_value = isset($item->categories) ? $item->categories : array();
			break;

			case 'tags': // assigned tags
			$field_value = isset($item->tags) ? $item->tags : array();
			break;

			case 'maintext': // main text
			$value = StringHelper::strlen( StringHelper::trim($item->fulltext ?? '') ) ? $item->introtext . "<hr id=\"system-readmore\" />" . $item->fulltext : $item->introtext;
			$field_value = array($value);
			break;
		}

		return $field_value;
	}


	/**
	 * Method to get the values of an extrafield
	 *
	 * @return object
	 * @since 1.5
	 * @todo move in a specific class and add the parameter $itemid
	 */
	function getCustomFieldsValues($item_id=0, $version=0)
	{
		if (!$item_id)  $item_id = $this->_id;
		if (!$item_id)  return array();

		static $field_values;
		if ( isset($field_values[$item_id][$version] ) )
			return $field_values[$item_id][$version];

		$cparams = $this->_cparams;
		$use_versioning = $cparams->get('use_versioning', 1);

		$query = 'SELECT field_id, value, valueorder, suborder'
			.( ($version<=0 || !$use_versioning) ? ' FROM #__flexicontent_fields_item_relations AS fv' : ' FROM #__flexicontent_items_versions AS fv' )
			.' WHERE fv.item_id = ' . (int)$item_id
			.( ($version>0 && $use_versioning) ? ' AND fv.version='.((int)$version) : '')
			.' ORDER BY field_id, valueorder, suborder'
			;
		$this->_db->setQuery($query);
		$rows = $this->_db->loadObjectList();

		// Add values to cached array
		$field_values[$item_id][$version] = array();
		foreach ($rows as $row) {
			$field_values[$item_id][$version][$row->field_id][$row->valueorder-1][$row->suborder-1] = $row->value;
		}

		foreach ($field_values[$item_id][$version] as & $fv) {
			foreach ($fv as & $ov) {
				if (count($ov) == 1) $ov = reset($ov);
			}
			unset($ov);
		}
		unset($fv);

		return $field_values[$item_id][$version];
	}


	/**
	 * Method to get the FIELDs (configuration and values) belonging to the Content Type of:
	 * (a) either the current item or (b) or the current _typeid (new items)
	 *
	 * NOTE: Fields are skipped if (a) are not pubished OR (b) no longer belong to the item type
	 * NOTE: VERSIONED field values will be retrieved if version is set in the HTTP REQUEST !!!
	 *
	 * @return object
	 * @since 1.5
	 */
	function getExtrafields($force = false, $original_content_id = 0, $old_item=null)
	{
		static $fields;
		if ($fields && !$force)
		{
			return $fields;
		}

		$use_versioning = $this->_cparams->get('use_versioning', 1);

		// Get item's type_id, loading item if neccessary
		$typeid = $this->_id ? $this->get('type_id') : 0;
		$typeid = $typeid ?: $this->_typeid;

		$type_join = ' JOIN #__flexicontent_fields_type_relations AS ftrel ON ftrel.field_id = fi.id AND ftrel.type_id = ' . $typeid;

		$query = 'SELECT  fi.*'
			. ' FROM #__flexicontent_fields AS fi'
			. ($typeid ? $type_join : '')            // Require field belonging to item type
			. ' WHERE fi.published = 1'              // Require field published
			. ($typeid ? '' : ' AND fi.iscore = 1')  // Get CORE fields when typeid not set
			. ' ORDER BY '. ($typeid ? 'ftrel.ordering, ' : '') .'fi.ordering, fi.name'
			;
		$fields = $this->_db->setQuery($query)->loadObjectList('name');

		// Get values of CUSTOM fields for current item
		$custom_vals[$this->_id] = $this->getCustomFieldsValues($this->_id, $this->_version);

		// Get values of language parent item (if not loading a specific version) to use them for untranslatable fields
		if ( $original_content_id && !$this->_version)
		{
			$custom_vals[$original_content_id] = $this->getCustomFieldsValues($original_content_id, 0);
		}

		foreach ($fields as $field)
		{
			$field->item_id = (int)$this->_id;

			// Version number should be ZERO when versioning disabled, or when wanting to load the current version !!!
			// Load CURRENT (non-versioned) core field from properties of item object
			if ( (!$this->_version || !$use_versioning) && $field->iscore)
			{
				$field->value = $this->getCoreFieldValue($field, $this->_version, $old_item);
			}

			// Load non core field (versioned or non-versioned) OR core field (versioned only)
			// while checking if current field is using untranslatable value from original content item
			else
			{
				$item_id = ($original_content_id && $field->untranslatable && !$this->_version) ? $original_content_id : $this->_id;
				$field->value = isset($custom_vals[$item_id][$field->id]) ? $custom_vals[$item_id][$field->id] : array();

				// Categories and Tags must have been serialized but some early versions did not do it, we will check before unserializing them
				if ($field->name=='categories' || $field->name=='tags')
				{
					$array = !isset($field->value[0]) ? array() : flexicontent_db::unserialize_array($field->value[0], $force_array=true, $force_value=false);
					if ( $array!==false )
					{
						$field->value = $array;
					}
				}
			}

			//echo "Got ver($this->_version) id {$field->id}: ". $field->name .": ";  print_r($field->value); 	echo "<br/>";
			$field->parameters = new JRegistry($field->attribs);
		}

		return $fields;
	}


	/**
	 * Method to get the versionlist which belongs to the item
	 *
	 * @return object
	 * @since 1.5
	 */
	function getVersionList($limitstart=0, $versionsperpage=0)
	{
		$query 	= 'SELECT v.version_id AS nr, v.created AS date, ua.name AS modifier, v.comment AS comment'
				.' FROM #__flexicontent_versions AS v'
				.' LEFT JOIN #__users AS ua ON ua.id = v.created_by'
				.' WHERE item_id = ' . (int)$this->_id
				.' ORDER BY version_id ASC'
				. ($versionsperpage?' LIMIT '.$limitstart.','.$versionsperpage:'')
				;
		$this->_db->setQuery($query);
		return $this->_db->loadObjectList();
	}


	/**
	 * Method to count the number of versions that the item has
	 *
	 * @return object
	 * @since 1.5
	 */
	function getVersionCount()
	{
		$query 	= 'SELECT count(*) as num'
				.' FROM #__flexicontent_versions AS v'
				.' LEFT JOIN #__users AS ua ON ua.id = v.created_by'
				.' WHERE item_id = ' . (int) $this->_id
				;
		$this->_db->setQuery($query);
		return $this->_db->loadResult();
	}


	/*
	 * Method to retrieve the configuration for the Content Submit/Update notifications
	 */
	function & getNotificationsConf(&$params)
	{
		static $nConf = null;
		if ($nConf !== null) return $nConf;

		// (a) Check if notifications are not enabled
		if ( !$params->get('enable_notifications', 0) )
		{
			$nConf = false;
			return $nConf;
		}

		$db = JFactory::getDbo();
		$nConf = new stdClass();

		// (b) Get Content Type specific notifications (that override global)
		$only_cat_conf = $params->get('nf_allow_cat_specific') && $params->get('cats_enable_notifications') == '2';
		$nConf->userlist_notify_new            = $only_cat_conf ? array() : FLEXIUtilities::paramToArray( $params->get('userlist_notify_new'), $regex="/[\s]*,[\s]*/", $filterfunc="intval");
		$nConf->usergrps_notify_new            = $only_cat_conf ? array() : FLEXIUtilities::paramToArray( $params->get('usergrps_notify_new', array()) );
		$nConf->userlist_notify_new_pending    = $only_cat_conf ? array() : FLEXIUtilities::paramToArray( $params->get('userlist_notify_new_pending'), $regex="/[\s]*,[\s]*/", $filterfunc="intval");
		$nConf->usergrps_notify_new_pending    = $only_cat_conf ? array() : FLEXIUtilities::paramToArray( $params->get('usergrps_notify_new_pending', array()) );

		$nConf->userlist_notify_existing             = $only_cat_conf ? array() : FLEXIUtilities::paramToArray( $params->get('userlist_notify_existing'), $regex="/[\s]*,[\s]*/", $filterfunc="intval");
		$nConf->usergrps_notify_existing             = $only_cat_conf ? array() : FLEXIUtilities::paramToArray( $params->get('usergrps_notify_existing', array()) );
		$nConf->userlist_notify_existing_reviewal    = $only_cat_conf ? array() : FLEXIUtilities::paramToArray( $params->get('userlist_notify_existing_reviewal'), $regex="/[\s]*,[\s]*/", $filterfunc="intval");
		$nConf->usergrps_notify_existing_reviewal    = $only_cat_conf ? array() : FLEXIUtilities::paramToArray( $params->get('usergrps_notify_existing_reviewal', array()) );

		// (c) Get category specific notifications
		if ( $params->get('nf_allow_cat_specific') )
		{
			$cats = $this->get('categories');
			$query = "SELECT params FROM #__categories WHERE id IN (".implode(',',$cats).")";
			$db->setQuery( $query );
			$mcats_params = $db->loadColumn();

			foreach ($mcats_params as $cat_params)
			{
				$cat_params = $this->_new_JRegistry($cat_params);
				if ( ! $cat_params->get('cats_enable_notifications', 0) ) continue;  // Skip this category if category-specific notifications are not enabled for this category

				$cats_userlist_notify_new            = FLEXIUtilities::paramToArray( $cat_params->get('cats_userlist_notify_new'), $regex="/[\s]*,[\s]*/", $filterfunc="intval");
				$cats_usergrps_notify_new            = FLEXIUtilities::paramToArray( $cat_params->get('cats_usergrps_notify_new', array()) );
				$cats_userlist_notify_new_pending    = FLEXIUtilities::paramToArray( $cat_params->get('cats_userlist_notify_new_pending'), $regex="/[\s]*,[\s]*/", $filterfunc="intval");
				$cats_usergrps_notify_new_pending    = FLEXIUtilities::paramToArray( $cat_params->get('cats_usergrps_notify_new_pending', array()) );

				$cats_userlist_notify_existing             = FLEXIUtilities::paramToArray( $cat_params->get('cats_userlist_notify_existing'), $regex="/[\s]*,[\s]*/", $filterfunc="intval");
				$cats_usergrps_notify_existing             = FLEXIUtilities::paramToArray( $cat_params->get('cats_usergrps_notify_existing', array()) );
				$cats_userlist_notify_existing_reviewal    = FLEXIUtilities::paramToArray( $cat_params->get('cats_userlist_notify_existing_reviewal'), $regex="/[\s]*,[\s]*/", $filterfunc="intval");
				$cats_usergrps_notify_existing_reviewal    = FLEXIUtilities::paramToArray( $cat_params->get('cats_usergrps_notify_existing_reviewal', array()) );

				$nConf->userlist_notify_new            = array_unique(array_merge($nConf->userlist_notify_new,            $cats_userlist_notify_new));
				$nConf->usergrps_notify_new            = array_unique(array_merge($nConf->usergrps_notify_new,            $cats_usergrps_notify_new));
				$nConf->userlist_notify_new_pending    = array_unique(array_merge($nConf->userlist_notify_new_pending,    $cats_userlist_notify_new_pending));
				$nConf->usergrps_notify_new_pending    = array_unique(array_merge($nConf->usergrps_notify_new_pending,    $cats_usergrps_notify_new_pending));

				$nConf->userlist_notify_existing             = array_unique(array_merge($nConf->userlist_notify_existing,             $cats_userlist_notify_existing));
				$nConf->usergrps_notify_existing             = array_unique(array_merge($nConf->usergrps_notify_existing,             $cats_usergrps_notify_existing));
				$nConf->userlist_notify_existing_reviewal    = array_unique(array_merge($nConf->userlist_notify_existing_reviewal,    $cats_userlist_notify_existing_reviewal));
				$nConf->usergrps_notify_existing_reviewal    = array_unique(array_merge($nConf->usergrps_notify_existing_reviewal,    $cats_usergrps_notify_existing_reviewal));
			}
		}
		//echo "<pre>"; print_r($nConf); exit;

		// Construct configuation parameter names
		$nConf_emails = new stdClass();
		$notify_types = array('notify_new', 'notify_new_pending', 'notify_existing', 'notify_existing_reviewal');
		foreach ($notify_types as $ntype) {
			$ugrps   [$ntype] = 'usergrps_'.$ntype;
			$ulist   [$ntype] = 'userlist_'.$ntype;
		}

		// (e) Get emails, but first convert user groups to user ids
		foreach ($notify_types as $ntype)
		{
			$user_emails = array();

			// emails for user ids
			$user_emails_ulist = array();
			$_user_ids = array();
			$_user_names = array();
			foreach ($nConf->{$ulist[$ntype]} as $user_id_name) {
				if ( is_numeric($user_id_name) ) $_user_ids[] = (int) $user_id_name;
				else $_user_names[] = $db->Quote($user_id_name);
			}
			if ( count($_user_ids) || count($_user_names) )
			{
				$query = "SELECT DISTINCT email FROM #__users";
				$where_clauses = array();
				if ( count($_user_ids) )   $where_clauses[] = " id IN (".implode(",",$_user_ids).") ";
				if ( count($_user_names) ) {
					$_user_names_quoted = array();
					foreach ($_user_names as $_user_name) {
						$_user_names_quoted[] = $db->Quote($_user_name);
					}
					$where_clauses[] = " username IN (".implode(",",$_user_names_quoted).") ";
				}
				$query .= " WHERE " . implode (' OR ', $where_clauses);
				$db->setQuery( $query );
				$user_emails_ulist = $db->loadColumn();
			}

			$user_emails_ugrps = array();

			if (count($nConf->{$ugrps[$ntype]}))
			{
				// emails for user groups
				$query = 'SELECT DISTINCT email FROM #__users as u'
					. ' JOIN #__user_usergroup_map ugm ON u.id = ugm.user_id AND ugm.group_id IN (' . implode(',', $nConf->{$ugrps[$ntype]}) . ')';

				$user_emails_ugrps = $db->setQuery($query)->loadColumn();
			}

			// merge them
			$user_emails = array_unique( array_merge($user_emails_ulist, $user_emails_ugrps) );

			$nConf_emails->{$ntype} = $user_emails;
		}

		$nConf->emails = $nConf_emails;
		//echo "<pre>"; print_r($nConf); exit;

		return $nConf;
	}


	// *****************************************************************************************
	// If there are emails to notify for current saving case, then send the notifications emails
	// *****************************************************************************************
	function sendNotificationEmails(&$notify_vars, &$params, $manual_approval_request=0)
	{
		$needs_version_reviewal     = $notify_vars->needs_version_reviewal;
		$needs_publication_approval = $notify_vars->needs_publication_approval;

		$isNew         = $notify_vars->isnew;
		$notify_emails = $notify_vars->notify_emails;
		$notify_text   = $notify_vars->notify_text;
		$before_cats   = $notify_vars->before_cats;
		$after_cats    = $notify_vars->after_cats;
		$oitem         = $notify_vars->original_item;

		if ( !count($notify_emails) ) return true;

		$app     = JFactory::getApplication();
		$db      = JFactory::getDbo();
		$user    = JFactory::getUser();
		$use_versioning = $this->_cparams->get('use_versioning', 1);

		// Get category titles of categories add / removed from the item
		$cats_added_titles = $cats_removed_titles = array();
		if ( !$isNew )
		{
			$cats_added_ids = array_diff(array_keys($after_cats), array_keys($before_cats));
			foreach($cats_added_ids as $cats_added_id)
			{
				$cats_added_titles[] = $after_cats[$cats_added_id]->title;
			}

			$cats_removed_ids = array_diff(array_keys($before_cats), array_keys($after_cats));
			foreach($cats_removed_ids as $cats_removed_id)
			{
				$cats_removed_titles[] = $before_cats[$cats_removed_id]->title;
			}
			$cats_altered = count($cats_added_ids) + count($cats_removed_ids);
			$after_maincat = $this->get('catid');
		}

		// Get category titles in the case of new item or categories unchanged
		$cats_titles = array();
		if ( $isNew || !$cats_altered)
		{
			foreach($after_cats as $after_cat)
			{
				$cats_titles[] = $after_cat->title;
			}
		}


		// **************
		// CREATE SUBJECT
		// **************
		$srvname = preg_replace('#www\.#','', $_SERVER['SERVER_NAME']);
		$url     = parse_url($srvname);
		$domain  = !empty($url["host"]) ? $url["host"] : $url["path"];
		$subject = '['.$domain.'] - ';
		if ( !$manual_approval_request ) {

			// (a) ADD INFO of being new or updated
			$subject .= JText::_( $isNew? 'FLEXI_NF_NEW_CONTENT_SUBMITTED' : 'FLEXI_NF_EXISTING_CONTENT_UPDATED') . " ";

			// (b) ADD INFO about editor's name and username (or being guest)
			$subject .= !$user->get('id') ? JText::sprintf('FLEXI_NF_BY_GUEST') : JText::sprintf('FLEXI_NF_BY_USER', $user->get('name'), $user->get('username'));

			// (c) (new items) ADD INFO for content needing publication approval
			if ($isNew) {
				$subject .= ": ";
				$subject .= JText::_( $needs_publication_approval ? 'FLEXI_NF_NEEDS_PUBLICATION_APPROVAL' : 'FLEXI_NF_NO_APPROVAL_NEEDED');
			}

			// (d) (existing items with versioning) ADD INFO for content needing version reviewal
			if ( !$isNew && $use_versioning) {
				$subject .= ": ";
				$subject .= JText::_( $needs_version_reviewal ? 'FLEXI_NF_NEEDS_VERSION_REVIEWAL' : 'FLEXI_NF_NO_REVIEWAL_NEEDED');
			}

		} else {
			$subject .= JText::_('FLEXI_APPROVAL_REQUEST');
		}


		// *******************
		// CREATE MESSAGE BODY
		// *******************

		$nf_extra_properties = $params->get('nf_extra_properties', array('creator','modifier','created','modified','viewlink','editlinkfe','editlinkbe','introtext','fulltext'));
		$nf_extra_properties  = FLEXIUtilities::paramToArray($nf_extra_properties);

		// ADD INFO for item title
		$body  = '<b>'.JText::_( 'FLEXI_NF_CONTENT_TITLE' ) . "</b>: ";
		if ( !$isNew ) {
			$_changed = $oitem->title != $this->get('title');
			$body .= " [ ". JText::_($_changed ? 'FLEXI_NF_MODIFIED' : 'FLEXI_NF_UNCHANGED') . " ] : <br/>\r\n";
			$body .= !$_changed ? "" : $oitem->title . " &nbsp; ==> &nbsp; ";
		}
		$body .= $this->get('title'). "<br/>\r\n<br/>\r\n";

		// ADD INFO about state
		$state_names = array(1=>'FLEXI_PUBLISHED', -5=>'FLEXI_IN_PROGRESS', 0=>'FLEXI_UNPUBLISHED', -3=>'FLEXI_PENDING', -4=>'FLEXI_TO_WRITE', (FLEXI_J16GE ? 2:-1)=>'FLEXI_ARCHIVED', -2=>'FLEXI_TRASHED');

		$body .= '<b>'.JText::_( 'FLEXI_NF_CONTENT_STATE' ) . "</b>: ";
		if ( !$isNew ) {
			$_changed = $oitem->state != $this->get('state');
			$body .= " [ ". JText::_( $_changed ? 'FLEXI_NF_MODIFIED' : 'FLEXI_NF_UNCHANGED') . " ] : <br/>\r\n";
			$body .= !$_changed ? "" : JText::_( $state_names[$oitem->state] ) . " &nbsp; ==> &nbsp; ";
		}
		$body .= JText::_( $state_names[$this->get('state')] ) ."<br/><br/>\r\n";

		// ADD INFO for author / modifier
		if ( in_array('creator',$nf_extra_properties) )
		{
			$body .= '<b>'.JText::_( 'FLEXI_NF_CREATOR_LONG' ) . "</b>: ";
			if ( !$isNew ) {
				$_changed = $oitem->created_by != $this->get('created_by');
				$body .= " [ ". JText::_($_changed ? 'FLEXI_NF_MODIFIED' : 'FLEXI_NF_UNCHANGED') . " ] : <br/>\r\n";
				$body .= !$_changed ? "" : $oitem->creator . " &nbsp; ==> &nbsp; ";
			}
			$body .= $this->get('creator'). "<br/>\r\n";
		}
		if ( in_array('modifier',$nf_extra_properties) && !$isNew )
		{
			$body .= '<b>'.JText::_( 'FLEXI_NF_MODIFIER_LONG' ) . "</b>: ";
			$body .= $this->get('modifier'). "<br/>\r\n";
		}
		$body .= "<br/>\r\n";

		// ADD INFO about creation / modification times. Use site's timezone !! we must
		// (a) set timezone to be site's timezone then
		// (b) call $date_OBJECT->format()  with s local flag parameter set to true
		$tz_offset = JFactory::getApplication()->getCfg('offset');
		$tz = new DateTimeZone($tz_offset);
		$tz_offset_str = $tz->getOffset(new JDate()) / 3600;
		$tz_offset_str = ' &nbsp; (UTC+'.$tz_offset_str.') ';

		if ( in_array('created',$nf_extra_properties) )
		{
			$date_created = JFactory::getDate($this->get('created'));
			$date_created->setTimezone($tz);

			$body .= '<b>'.JText::_( 'FLEXI_NF_CREATION_TIME' ) . "</b>: ";
			$body .= $date_created->format($format = 'D, d M Y H:i:s', $local = true);
			$body .= $tz_offset_str. "<br/>\r\n";
		}
		if ( in_array('modified',$nf_extra_properties) && !$isNew )
		{
			$date_modified = JFactory::getDate($this->get('modified'));
			$date_modified->setTimezone($tz);

			$body .= '<b>' . JText::_( 'FLEXI_NF_MODIFICATION_TIME' ) . '</b>: '
				. $date_modified->format($format = 'D, d M Y H:i:s', $local = true)
				. $tz_offset_str. "<br/>\r\n";
		}
		$body .= "<br/>\r\n";

		// ADD INFO about category assignments
		$body .= '<b>'.JText::_( 'FLEXI_NF_CATEGORIES_ASSIGNMENTS').'</b>';
		$body .= !$isNew
			? " [ ". JText::_( $cats_altered ? 'FLEXI_NF_MODIFIED' : 'FLEXI_NF_UNCHANGED') . " ] : <br/>\r\n"
			: " : <br/>\r\n";

		if ( !empty($cats_titles) )
		{
			foreach ($cats_titles as $i => $cats_title)
			{
				$body .= " &nbsp; ". ($i+1) .". ". $cats_title ."<br/>\r\n";
			}
		}

		// ADD INFO for category assignments added or removed
		if ( !empty($cats_added_titles) )
		{
			$body .= '<b>'.JText::_( 'FLEXI_NF_ITEM_CATEGORIES_ADDED') . "</b> : <br/>\r\n";
			foreach ($cats_added_titles as $i => $cats_title)
			{
				$body .= " &nbsp; ". ($i+1) .". ". $cats_title ."<br/>\r\n";
			}
		}
		if ( !empty($cats_removed_titles) )
		{
			$body .= '<b>'.JText::_( 'FLEXI_NF_ITEM_CATEGORIES_REMOVED') . "</b> : <br/>\r\n";
			foreach ($cats_removed_titles as $i => $cats_title)
			{
				$body .= " &nbsp; ". ($i+1) .". ". $cats_title ."<br/>\r\n";
			}
		}
		$body .= "<br/>\r\n<br/>\r\n";

		// ADD INFO for custom notify text
		$subject .= ' '. JText::_( $notify_text );

		// Create the non-SEF URL
		$site_languages = FLEXIUtilities::getLanguages();
		$sef_lang = $this->_record->language != '*' && isset($site_languages->{$this->_record->language}) ? $site_languages->{$this->_record->language}->sef : '';
		$item_url =
			FlexicontentHelperRoute::getItemRoute($this->_record->slug, $this->_record->categoryslug, 0, $this->_record)
			. ($sef_lang ? '&lang=' . $sef_lang : '');

		// Create the SEF URL
		$item_url = $app->isClient('administrator')
			? flexicontent_html::getSefUrl($item_url)   // ..., $_xhtml= true, $_ssl=-1);
			: JRoute::_($item_url);  // ..., $_xhtml= true, $_ssl=-1);

		// Make URL absolute since this URL will be emailed
		$item_url = JUri::getInstance()->toString(array('scheme', 'host', 'port')) . $item_url;

		// ADD INFO for view/edit link
		if ( in_array('viewlink',$nf_extra_properties) )
		{
			$body .= '<b>'.JText::_( 'FLEXI_NF_VIEW_IN_FRONTEND' ) . "</b> : <br/>\r\n &nbsp; ";
			$link = $item_url;
			$body .= '<a href="' . $link . '" target="_blank">' . $link . "</a><br/>\r\n<br/>\r\n";  // THIS IS BOGUS *** for unicode menu aliases
			//$body .= $link . "<br/>\r\n<br/>\r\n";
		}
		if ( in_array('editlinkfe',$nf_extra_properties) )
		{
			$body .= '<b>'.JText::_( 'FLEXI_NF_EDIT_IN_FRONTEND' ) . "</b> : <br/>\r\n &nbsp; ";
			$link = $item_url . (strstr($item_url, '?') ? '&amp;' : '?') . 'task=edit';
			$body .= '<a href="' . $link . '" target="_blank">' . $link . "</a><br/>\r\n<br/>\r\n";  // THIS IS BOGUS *** for unicode menu aliases
			//$body .= $link . "<br/>\r\n<br/>\r\n";
		}
		if ( in_array('editlinkbe',$nf_extra_properties) )
		{
			$body .= '<b>'.JText::_( 'FLEXI_NF_EDIT_IN_BACKEND' ) . "</b> : <br/>\r\n &nbsp; ";
			$fc_ctrl_task = 'task=items.edit';
			$link = JRoute::_( JUri::root().'administrator/index.php?option=com_flexicontent&'.$fc_ctrl_task.'&cid='.$this->get('id') );
			$body .= '<a href="' . $link . '" target="_blank">' . $link . "</a><br/>\r\n<br/>\r\n";  // THIS IS BOGUS *** for unicode menu aliases
			//$body .= $link . "<br/>\r\n<br/>\r\n";
		}

		// ADD INFO for introtext/fulltext
		if ( $params->get('nf_add_introtext') )
		{
			//echo "<pre>"; print_r($this->_record); exit;
			$body .= "<br/><br/>\r\n";
			$body .= "*************************************************************** <br/>\r\n";
			$body .= JText::_( 'FLEXI_NF_INTROTEXT_LONG' ) . "<br/>\r\n";
			$body .= "*************************************************************** <br/>\r\n";
			$body .= flexicontent_html::striptagsandcut( $this->get('introtext'), 200 );
		}
		if ( $params->get('nf_add_fulltext') )
		{
			$body .= "<br/><br/>\r\n";
			$body .= "*************************************************************** <br/>\r\n";
			$body .= JText::_( 'FLEXI_NF_FULLTEXT_LONG' ) . "<br/>\r\n";
			$body .= "*************************************************************** <br/>\r\n";
			$body .= flexicontent_html::striptagsandcut( $this->get('fulltext'), 200 );
		}


		// **********
		// Send email
		// **********
		$from      = $app->getCfg( 'mailfrom' );
		$fromname  = $app->getCfg( 'fromname' );
		$recipient = $params->get('nf_send_as_bcc', 0) ? array($from) : $notify_emails;
		$html_mode = true;
		$cc  = null;
		$bcc = $params->get('nf_send_as_bcc', 0) ? $notify_emails : null;
		$attachment  = null;
		$replyto     = null;
		$replytoname = null;

		// Remove main recepient from BCC, to avoid email failing
		if ($bcc)
		{
			$_bcc_ = array_flip($bcc);
			if ( isset($_bcc_[$from]) ) unset($bcc[$_bcc_[$from]]);
		}

		$send_result = JFactory::getMailer()->sendMail( $from, $fromname, $recipient, $subject, $body, $html_mode, $cc, $bcc, $attachment, $replyto, $replytoname );

		$debug_str = ""
			."<br/>FROM: $from"
			."<br/>FROMNAME:  $fromname <br/>"
			."<br/>RECIPIENTS: " .implode(",", $recipient)
			."<br/>BCC: ". ($bcc ? implode(",",$bcc) : '')."<br/>"
			."<br/>SUBJECT: $subject <br/>"
			."<br/><br/>**********<br/>BODY<br/>**********<br/> $body <br/>"
			;

		if ($send_result) {
			// OK
			if ($params->get('nf_enable_debug',0)) {
				$app->enqueueMessage("Sending WORKFLOW notification emails SUCCESS", 'message' );
				$app->enqueueMessage($debug_str, 'message' );
			}
		} else {
			// NOT OK
			if ($params->get('nf_enable_debug',0)) {
				$app->enqueueMessage("Sending WORKFLOW notification emails FAILED", 'warning' );
				$app->enqueueMessage($debug_str, 'message' );
			}
		}
		return $send_result;
	}


	/**
	 * Method to build an object with the items submitted to approval
	 * it also verifies if the item state are correct (draft state is -4)
	 * and if it belongs to the user
	 *
	 * @access	public
	 * @params	array
	 * @return	object
	 * @since	1.5
	 */
	function isUserDraft($cid)
	{
		$user = JFactory::getUser();

		if ($cid)
		{
			$query 	= 'SELECT c.id, c.catid, c.created_by, c.title, cat.title AS cattitle, c.checked_out'
					. ' FROM #__content AS c'
					. ' LEFT JOIN #__categories AS cat on cat.id = c.catid'
					. ' WHERE c.state = -4'
					//. ' AND c.created_by = ' . (int) $user->get('id')
					. (FLEXI_J16GE ? ' AND cat.extension="'.FLEXI_CAT_EXTENSION.'"' : '')
					. ' AND c.id IN ( '. implode(',', $cid).' )'
					//. ' AND ( c.checked_out = 0 OR checked_out IS NULL OR ( c.checked_out = ' . (int) $user->get('id'). ' ) )'
					;
			$this->_db->setQuery( $query );
			$cids = $this->_db->loadObjectList();
		}

		return $cids;
	}


	/**
	 * Method to find reviewers of new item
	 *
	 * @access	public
	 * @params	int			the id of the item
	 * @params	int			the catid of the item
	 * @return	object		the validators object
	 * @since	1.5
	 */
	function getApprovalRequestReceivers($id, $catid)
	{
		// We will use the email receivers of --new items-- pending approval, as receivers of the manual approval request
		$nConf = $this->getNotificationsConf($this->_cparams);
		$validators = new stdClass();
		$validators->notify_emails = $nConf->emails->notify_new_pending;
		$validators->notify_text = ''; // clear this ... default is : 'text_notify_new_pending', but it is not used the case of manual approval

		return $validators;
	}


	/**
	 * Logic to submit item to approval
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function approval($cid)
	{
		$db = $this->_db;
		$user = JFactory::getUser();
		$approvables = $this->isUserDraft($cid);

		$requestApproval = $user->authorise('flexicontent.requestapproval',	'com_flexicontent');

		$submitted = 0;
		$noprivilege = array();
		$checked_out = array();
		$publishable = array();
		foreach ($approvables as $approvable)
		{
			// Check if not owned (while not have global request approval privilege)
			if ( !$requestApproval && $approvable->created_by != (int) $user->get('id') ) {
				$noprivilege[] = $item->title;
				continue;
			}

			// Check if checked out (edited) by different user
			if ( $approvable->checked_out != 0 && $approvable->checked_out != (int) $user->get('id') ) {
				$checked_out[] = $item->title;
				continue;
			}

			// Get item setting it into the model (ITEM DATE: _id, _type_id, _params, etc will be updated)
			$item = $this->getItem($approvable->id, $check_view_access=false, $no_cache=true);

			// Get publish privilege
			$canEditState = $this->canEditState( $item );
			if ( $canEditState ) {
				$publishable[] = $item->title;
				continue;
			}

			// Set to pending approval
			$this->setitemstate($approvable->id, -3);

			$validators = $this->getApprovalRequestReceivers($approvable->id, $approvable->catid);

			if ( !count($validators->notify_emails) ) {
				$validators->notify_emails[] = JFactory::getApplication()->getCfg('mailfrom');
			}

			$query 	= 'SELECT DISTINCT c.id, c.title FROM #__categories AS c'
				. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.catid = c.id'
				. ' WHERE rel.itemid = '.(int) $approvable->id;
			$db->setQuery( $query );
			$after_cats = $db->loadObjectList('id');

			$notify_vars = new stdClass();
			$notify_vars->needs_version_reviewal     = 0;
			$notify_vars->needs_publication_approval = 1;
			$notify_vars->isnew         = 1;
			$notify_vars->notify_emails = $validators->notify_emails;
			$notify_vars->notify_text   = $validators->notify_text;
			$notify_vars->before_cats   = array();
			$notify_vars->after_cats    = $after_cats;

			$this->sendNotificationEmails($notify_vars, $this->_cparams, $manual_approval_request=1);
			$submitted++;
		}

		// Number of submitted items
		if ( $submitted) {
			$approve_str = $submitted > 1 ? 'FLEXI_APPROVAL_ITEMS_SUBMITTED' : 'FLEXI_APPROVAL_ITEM_SUBMITTED';
			$msg = ($submitted > 1 ? $submitted : '') . JText::_( $approve_str );
		} else {
			$msg = JText::_( 'FLEXI_APPROVAL_NO_ITEMS_SUBMITTED' );
		}

		// Number of excluded items, and message that items must be owned and in draft state
		$excluded = count($cid) - $submitted;
		$msg .= $excluded  ?  ' '. $excluded .' '. JText::_( 'FLEXI_APPROVAL_ITEMS_EXCLUDED' )  :  '';

		// Message about excluded non-owned items, that are being owned be a different user (this means current user does not have global request approval privilege)
		if ( count($noprivilege) ) {
			$noprivilege_str = '"'. implode('" , "', $noprivilege) .'"';
			$msg .= '<div>'.JText::sprintf('FLEXI_APPROVAL_NO_REQUEST_PRIV_EXCLUDED', $noprivilege_str).'</div>';
		}

		// Message about excluded checked_out items, that are being edited be a different user
		if ( count($checked_out) ) {
			$checked_out_str = '"'. implode('" , "', $checked_out) .'"';
			$msg .= '<div>'.JText::sprintf('FLEXI_APPROVAL_CHECKED_OUT_EXCLUDED', $checked_out_str).'</div>';
		}

		// Message about excluded publishable items, that can be published by the owner
		if ( count($publishable) ) {
			$publishable_str = '"'. implode('" , "', $publishable) .'"';
			$msg .= '<div>'.JText::sprintf('FLEXI_APPROVAL_PUBLISHABLE_EXCLUDED', $publishable_str).'</div>';
		}

		$this->cleanCache(null, 0);
		$this->cleanCache(null, 1);
		return $msg;
	}


	/**
	 * Method to get item (language) associations
	 *
	 * @param		int			The id of the item
	 *
	 * @return	array		The array of associations
	 */
	function getLangAssocs($id=0)
	{
		static $translations = array(0 => array());

		$id = (int) ($id ?: $this->_id);

		// No cached data, get associated translations
		if ($id && !isset($translations[$id]))
		{
			$translations[$id] = array();

			foreach(flexicontent_db::getLangAssocs(array($id)) as $item_id => $assocs)
			{
				$translations[$item_id] = $assocs;
			}
		}

		return $translations[$id];
	}


	/**
	 * Method to toggle the featured setting of articles.
	 *
	 * @param	array	The ids of the items to toggle.
	 * @param	int		The value to toggle to.
	 *
	 * @return	boolean	True on success.
	 */
	public function featured($pks, $value = 0, $cleanCache = true)
	{
		$db  = $this->_db;
		$pks = (array) $pks;
		$pks = ArrayHelper::toInteger($pks);

		if (count($pks))
		{
			try {
				/**
				 * Toggle featured flag in the data tables
				 */
				$query = 'UPDATE #__%s SET featured = ' . (int) $value . ' WHERE id IN (' . implode(',', $pks) . ')';
				$db->setQuery(sprintf($query, $this->records_dbtbl))->execute();
				$db->setQuery(sprintf($query, 'flexicontent_items_tmp'))->execute();

				/**
				 * Add / Remove record in the 'content_frontpage' DB table
				 */

				// (a) To clear flag just delete the records
				if ((int) $value == 0)
				{
					$db->setQuery('DELETE FROM #__content_frontpage WHERE content_id IN ('.implode(',', $pks).')')->execute();
				}

				// (b) To set records we need to add ONLY the new ones
				else
				{
					// First, we find out which of our new featured articles are already featured.
					$old_featured = $db->setQuery('SELECT f.content_id FROM #__content_frontpage AS f WHERE content_id IN ('.implode(',', $pks).')')->loadColumn();
					$new_featured = array_diff($pks, $old_featured);

					// Now add only the new records
					if (count($new_featured))
					{
						$tuples = array();

						foreach ($new_featured as $pk)
						{
							$tuples[] = '(' . $pk . ', 0)';
						}

						$db->setQuery(
							'INSERT INTO #__content_frontpage '
							. '(' . $db->quoteName('content_id') . ', ' . $db->quoteName('ordering') . ')'
							. ' VALUES ' . implode(',', $tuples)
						)->execute();
					}
				}
			}
			catch (Exception $e) {
				$this->setError($e->getMessage());
				return false;
			}

			$this->getTable('flexicontent_content_frontpage', '')->reorder();

			if ($cleanCache)
			{
				$this->cleanCache(null, 0);
				$this->cleanCache(null, 1);
			}
		}

		return true;
	}


	/**
	 * Method to split description text into 'introtext' and 'fulltext' so that these properties are ready for storing in the DB
	 *
	 */
	function splitText(& $data)
	{
		// The text field is stored in the db as to seperate fields: introtext & fulltext
		// So we search for the {readmore} tag and split up the text field accordingly.
		$pattern = '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
		if (is_array($data))
		{
			$tagPos = preg_match($pattern, $data['text'] ?? '');
			if ($tagPos == 0)
			{
				$data['introtext'] = @ $data['text'];
				$data['fulltext']  = '';
			}
			else
			{
				list($data['introtext'], $data['fulltext']) = preg_split($pattern, $data['text'], 2);
				$data['fulltext'] = StringHelper::strlen( StringHelper::trim($data['fulltext']) ) ? $data['fulltext'] : '';
			}
		}

		else
		{
			$item = & $data;
			$tagPos = preg_match($pattern, $item->text);
			if ($tagPos == 0)
			{
				$item->introtext = $item->text;
				$item->fulltext  = '';
			}
			else
			{
				list($item->introtext, $item->fulltext) = preg_split($pattern, $item->text, 2);
				$item->fulltext = StringHelper::strlen( StringHelper::trim($item->fulltext) ) ? $item->fulltext : '';
			}
		}
	}


	/**
	 * Check that alias is unique within item's categories
	 */
	function getSafeUniqueAlias($item, $data)
	{
		// Set alias if not already set
		$alias = trim($data['alias']);
		$alias = strlen($alias) ? $alias : $data['title'];

		// Make alias safe and transliterate it
		$alias = JApplicationHelper::stringURLSafe($alias, $data['lang_code']);

		// Check for almost empty alias and use date and seconds plugs language code
		if (trim(str_replace('-', '', $alias)) == '')
		{
			return JFactory::getDate()->format('Y-m-d-H-i-s') . '-' . $data['lang_code'];
		}

		// Also check for unique Alias
		$sub_q = 'SELECT catid FROM #__flexicontent_cats_item_relations WHERE itemid=' . (int) $item->id;
		$query = 'SELECT COUNT(*) FROM #__flexicontent_items_tmp AS i '
			.' JOIN #__flexicontent_items_ext AS e ON i.id = e.item_id '
			.' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON i.id = rel.itemid '
			.' WHERE i.alias=' . $this->_db->Quote($alias)
			.'  AND (i.catid=' . (int) $item->catid
			// CURRENTLY for unique alias check only main category,
			// as checking all categories (should we choose to do it), should be done in a different way, checking every category with individual SQL query in a loop
			//. (!empty($item->categories) ? ' OR rel.catid IN (' . implode(', ', ArrayHelper::toInteger($item->categories)) . ')' : '')
			. ' )'
			.'  AND i.language = ' . $this->_db->Quote($item->language)
			.'  AND i.id <> ' . (int) $item->id
			;

		$count = $this->_db->setQuery($query)->loadResult();
		$duplicates_found = (boolean) $count;

		return !$duplicates_found
			? $alias
			: $alias . ' (' . $item->id . ')';
	}


	/**
	 * Method to prepare categories in data array for being store in the DB
	 *
	 */
	protected function _prepareCategories($item, & $data)
	{
		$cats = isset($data['categories'])
			? $data['categories']
			: (isset($data['cid']) ? $data['cid'] : $item->categories);
		$featured_cats = isset($data['featured_cid'])
			? $data['featured_cid']
			: array();

		// Force arrays of integers
		$cats = ArrayHelper::toInteger($cats);
		$featured_cats = ArrayHelper::toInteger($featured_cats);

		// Auto-assign a not set main category, to be the first out of secondary categories,
		if ( empty($data['catid']) && !empty($cats[0]) )
		{
			$data['catid'] = $cats[0];
		}

		$cats_indexed = array_flip($cats);
		// Add the primary cat to the array if it's not already in
		if ( !empty($data['catid']) && !isset($cats_indexed[$data['catid']]) )
		{
			$cats_indexed[$data['catid']] = 1;
		}

		// Add the featured cats to the array if it's not already in
		if ( !empty($featured_cats) ) foreach ( $featured_cats as $featured_cat )
		{
			if ( $featured_cat && !isset($cats_indexed[$featured_cat]) )  $cats_indexed[$featured_cat] = 1;
		}

		// Reassign (unique) categories back to the cats array
		$cats = array_keys($cats_indexed);


		// ***
		// *** Retrieve author configuration, and apply category limitations
		// ***

		$user = JFactory::getUser();
		$authorparams = flexicontent_db::getUserConfig($user->get('id'));

		// At least one category needs to be assigned
		if (!is_array( $cats ) || count( $cats ) < 1)
		{
			$this->setError(JText::_('FLEXI_OPERATION_FAILED') .", ". JText::_('FLEXI_REASON') .": ". JText::_('FLEXI_SELECT_CATEGORY'));
			return false;
		}

		// Check more than allowed categories
		else
		{
			// Get author's maximum allowed categories per item and set js limitation
			$max_cat_assign = intval($authorparams->get('max_cat_assign',0));

			// Verify category limitation for current author
			if ( $max_cat_assign && count($cats) > $max_cat_assign )
			{
				$existing_only = false;
				if ( count($cats) <= count($item->categories) )
				{
					// Maximum number of categories is exceeded, but do not abort if only using existing categories
					$existing_only = true;
					foreach ($cats as $newcat)
					{
						$existing_only = $existing_only && in_array($newcat, $item->categories);
					}
				}

				if (!$existing_only)
				{
					$this->setError(JText::_('FLEXI_OPERATION_FAILED') .", ". JText::_('FLEXI_REASON') .": ". JText::_('FLEXI_TOO_MANY_ITEM_CATEGORIES').$max_cat_assign);
					return false;
				}
			}
		}


		// ***
		// *** Set processed categories it to real name of field: 'categories' INSTEAD OF 'cid'
		// ***

		$data['categories']  = $cats;
		unset($data['cid']);
		unset($data['featured_cid']);
	}


	/**
	 * Method to prepare tags in data array for being store in the DB
	 *
	 */
	protected function _prepareTags($item, & $data)
	{
		if ( !isset($data['tag']) && ! isset($data['tags']) )
		{
			// Do not set $data['tags'] thus item will maintain its current tag assignments
			return;
		}

		$tags = isset($data['tags'])
			? $data['tags']
			: $data['tag'];

		// Make tags unique
		$tags = ArrayHelper::toInteger($tags);
		$tags = array_keys(array_flip($tags));

		// Remove Empty Tags
		foreach($tags as $i => $tag)
		{
			if (!$tag) unset($tags[$i]);
		}

		// Set tags back using itsreal name of field: 'tags'       INSTEAD OF 'tag'
		$data['tags'] = $tags;
		unset($data['tag']);

		echo empty($this->debug_tags) ? null : '<b>' . __FUNCTION__ . '()</b><blockquote> Prepared tags: ' . print_r($tags, true) . '</blockquote>';
	}


	/**
	 * Method to merge JSON (column) data to item parameters
	 *
	 */
	private function _prepareMergeJsonParams($colname)
	{
		$registry = new JRegistry();

		try
		{
			is_array($this->_record->$colname)
				? $registry->loadArray($this->_record->$colname)
				: $registry->loadString($this->_record->$colname);
		}
		catch (Exception $e)
		{
			$registry = flexicontent_db::check_fix_JSON_column($colname, $this->records_dbtbl, 'id', $this->_record->id, $this->_record->$colname);
		}

		$this->_record->$colname = $registry->toArray();
		$this->_record->itemparams->merge($registry);
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
	protected function cleanCache($group = null, $client_id = 0)
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

			parent::cleanCache('com_flexicontent_items', $client_id);
			parent::cleanCache('com_flexicontent_filters', $client_id);
		}
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

		while ($table->load(array('alias' => $alias)))
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
		$record  = $record ?: $this->_record;
		$user    = $user ?: JFactory::getUser();
		$session = JFactory::getSession();
		$isOwner = !empty($record->created_by) && ( $record->created_by == $user->get('id') );

		// Check if item was editable, but was rendered non-editable
		$hasTmpEdit = false;
		$hasCoupon  = false;
		if ($session->has('rendered_uneditable', 'flexicontent'))
		{
			$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
			$hasTmpEdit = !empty($record->id) && !empty($rendered_uneditable[$record->id]);  // editable temporarily
			$hasCoupon = !empty($record->id) && !empty($rendered_uneditable[$record->id]) && $rendered_uneditable[$record->id] == 2;  // editable temporarily via coupon
		}

		if ($hasTmpEdit)
		{
			return true;
		}

		// Get "edit items" permission on the type of the item
		$hasTypeEdit    = !$record->type_id ? true : FlexicontentHelperPerm::checkTypeAccess($record->type_id, 'core.edit');
		$hasTypeEditOwn = !$record->type_id ? true : FlexicontentHelperPerm::checkTypeAccess($record->type_id, 'core.edit.own');
		if (!$hasTypeEdit && !$hasTypeEditOwn) return false;

		// Existing item, use item specific permissions
		if (!empty($record->id))
		{
			$asset = $this->type_alias . '.' . $record->id;
			$allowed =
				($hasTypeEdit && $user->authorise('core.edit', $asset)) ||
				($hasTypeEditOwn && $user->authorise('core.edit.own', $asset) && ($isOwner || $hasCoupon));  // hasCoupon acts as item owner
		}

		// *** New item *** ... edit should not be used on new item, always return false
		else
		{
			$allowed = false;
		}

		return $allowed;
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
		$session = JFactory::getSession();
		$isOwner = !empty($record->created_by) && ( $record->created_by == $user->get('id') );

		$hasCoupon = false;
		if ($session->has('rendered_uneditable', 'flexicontent'))
		{
			$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
			$hasCoupon = !empty($record->id) && !empty($rendered_uneditable[$record->id]) && $rendered_uneditable[$record->id] == 2;  // editable temporarily via coupon
		}

		// Get "edit items state" permission on the type of the item
		$hasTypeEditState    = !$record->type_id ? true : FlexicontentHelperPerm::checkTypeAccess($record->type_id, 'core.edit.state');
		$hasTypeEditStateOwn = !$record->type_id ? true : FlexicontentHelperPerm::checkTypeAccess($record->type_id, 'core.edit.state.own');
		if (!$hasTypeEditState && !$hasTypeEditStateOwn) return false;

		// Existing item, use item specific permissions
		if (!empty($record->id))
		{
			$asset = $this->type_alias . '.' . $record->id;
			$allowed =
				($hasTypeEditState && $user->authorise('core.edit.state', $asset)) ||
				($hasTypeEditStateOwn && $user->authorise('core.edit.state.own', $asset) && ($isOwner || $hasCoupon));  // hasCoupon acts as item owner
		}

		// *** New item *** (NOTE: VALIDATION should be done again during item creation !!)
		elseif ( !empty($record->catid) )
		{
			// New item with main category set, use category ACL
			$cat_asset = 'com_content.category.' . (int) $record->catid;
			$allowed =
				($hasTypeEditState && $user->authorise('core.edit.state', $cat_asset)) ||
				($hasTypeEditStateOwn && $user->authorise('core.edit.state.own', $cat_asset) && $isOwner);
		}
		else
		{
			// New item  without main category set, use general edit.state/edit.state.own permissions
			$allowed =
				($hasTypeEditState && $user->authorise('core.edit.state', 'com_flexicontent')) ||
				($hasTypeEditStateOwn && $user->authorise('core.edit.state.own', 'com_flexicontent'));
		}

		return $allowed;
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
		$isOwner = !empty($record->created_by) && ( $record->created_by == $user->get('id') );

		// Existing item, use item specific permissions
		if (!empty($record->id))
		{
			$asset = $this->type_alias . '.' . $record->id;
			$allowed = $user->authorise('core.delete', $asset) || ($user->authorise('core.delete.own', $asset) && $isOwner);
		}

		// Delete should not be used only on existing records, always return false
		else
		{
			$allowed = false;
		}

		return $allowed;
	}


	/**
	 * Method to check if the user can edit record 's state
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	3.2.0
	 */
	function canView($record=null)
	{
		$record = $record ?: $this->_record;
		$user = JFactory::getUser();
		$session = JFactory::getSession();
		$isOwner = !empty($this->_record->created_by) && $this->_record->created_by == $user->get('id');

		// The access filter has been set, we already know current user can view this item or we should not check access
		if ($this->getState('filter.access'))
		{
			return true;
		}

		// The access filter has not been set, we will set access flag(s) if not set already the layout takes some responsibility for display of limited information,
		$groups = JAccess::getAuthorisedViewLevels($user->get('id'));

		if ( !isset($record->has_item_access) )
		{
			$record->has_item_access = in_array($record->access, $groups);
		}

		if ( !isset($record->has_mcat_access) )
		{
			$no_mcat_info = $record->catid == 0 || !isset($record->category_access) || $record->category_access === null;
			$record->has_mcat_access = $no_mcat_info || in_array($record->category_access, $groups);
		}

		if ( !isset($record->has_type_access) )
		{
			$no_type_info = $this->_typeid == 0 || !isset($record->type_access) || $record->type_access === null;
			$record->has_type_access = $no_type_info || in_array($record->type_access, $groups);
		}

		return $record->has_item_access && $record->has_mcat_access && $record->has_type_access;
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
	// TODO add call of this method, currently unused
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

		// ***
		// *** Retrieve item properties not defined in the model's CLASS (if not already popuplated)
		// ***

		$public_acclevel = 1;


		// ***
		// *** Item's access level data (its title, e.g. title: 'Public')
		// ***

		if ( !$record->access )
		{
			$record->access_level = '';
		}
		else if ( !isset($record->access_level) )
		{
			$query = 'SELECT title FROM #__viewlevels WHERE id = '. (int) $record->access;
			$this->_db->setQuery($query);
			$record->access_level = $this->_db->loadResult();
		}


		// ***
		// *** Get category data for the item's main category, access is used later to determine viewing of the item
		// ***

		if ( !$record->catid )
		{
			$record->categoryslug    = '';
			$record->category_access = $public_acclevel;
		}
		else if ( !isset($record->category_access) || !isset($record->categoryslug) )
		{
			$query = 'SELECT id, title, access FROM #__categories WHERE id = '. (int) $record->catid;
			$cat = $this->_db->setQuery($query)->loadObject();
			$record->categoryslug    = $cat ? $cat->id . ':' . $cat->title : '';
			$record->category_access = $cat ? $cat->access : $public_acclevel;
		}
		// Force to public access if category access level is empty
		$record->category_access = !empty($record->category_access) ? $record->category_access : $public_acclevel;


		// ***
		// *** Item's slug
		// ***

		$record->slug = isset($record->slug) ? $record->slug : $record->id . ':' . $record->alias ;


		// ***
		// *** Get type data for the item's type, access is used later to determine viewing of the item
		// ***

		if ( !$record->type_id )
		{
			$record->typename    = '';
			$record->typealias   = '';
			$record->type_access = '';
		}
		else if ( !isset($record->type_access) || !isset($record->typename) || !isset($record->typealias) )
		{
			$query = 'SELECT name, alias, access FROM #__flexicontent_types WHERE id = '. (int) $record->type_id;
			$type = $this->_db->setQuery($query)->loadObject();
			$record->typename    = $type ? $type->name : '';
			$record->typealias   = $type ? $type->alias : '';
			$record->type_access = $type ? $type->access : $public_acclevel;
		}
		// Force to public access if type access level is empty
		$record->type_access = !empty($record->type_access) ? $record->type_access : $public_acclevel;


		// ***
		// *** Get rating data
		// ***

		if (!$record->id)
		{
			$record->rating_count = 0;
			$record->rating       = 0;
			$record->score        = 0;
		}
		elseif (!isset($record->rating_count) || !isset($record->rating) || !isset($record->score))
		{
			$rating_resolution = (int)$this->getVotingResolution();

			// Get category access for the item's main category, used later to determine viewing of the item
			$query = 'SELECT '
				.' v.rating_count as rating_count, ROUND( v.rating_sum / v.rating_count ) AS rating, ((v.rating_sum / v.rating_count)*'.(100 / $rating_resolution).') as score'
				.' FROM #__content_rating AS v WHERE v.content_id = '. (int) $record->id
				;
			$this->_db->setQuery($query);
			$rating_data = $this->_db->loadObject();
			$record->rating_count = !$rating_data ? 0 : $rating_data->rating_count;
			$record->rating       = !$rating_data ? 0 : $rating_data->rating;
			$record->score        = !$rating_data ? 0 : $rating_data->score;
		}


		// ***
		// *** Retrieve Creator (owner) data (e.g. name and email used to display the gravatar)
		// ***

		// For new item, if creator id is empty, then set it to current user id
		if ( !$record->id && !$record->created_by )
		{
			$record->created_by = JFactory::getUser()->id;
		}

		if ( !$record->created_by )
		{
			$record->creator      = '';
			$record->creatoremail = '';
			$record->author = & $record->creator;  // an alias
		}
		else if ( !isset($record->creator) || !isset($record->creatoremail) || !isset($record->author) )
		{
			$query = 'SELECT name, email FROM #__users WHERE id = '. (int) $record->created_by;
			$creator = $this->_db->setQuery($query)->loadObject();
			$record->creator      = $creator ? $creator->name : '';
			$record->creatoremail = $creator ? $creator->email : '';
			$record->author = & $record->creator;  // an alias
		}


		// ***
		// *** Retrieve Last modifier data
		// ***

		if ($record->created_by == $record->modified_by)
		{
			$record->modifier = $record->creator;
			$record->modifieremail = $record->creatoremail;
		}

		if ( !$record->modified_by )
		{
			$record->modifier = '';
			$record->modifieremail = '';
		}
		else if ( !isset($record->modifier) || !isset($record->modifieremail) )
		{
			$query = 'SELECT name, email FROM #__users WHERE id = '. (int) $record->modified_by;
			$modifier = $this->_db->setQuery($query)->loadObject();
			$record->modifier      = $modifier ? $modifier->name : '';
			$record->modifieremail = $modifier ? $modifier->email : '';
		}


		// Clear modified Date, if it is an empty "DB-null" date
		if ($record->modified == $this->_db->getNulldate())
		{
			$record->modified = null;
		}


		// ***
		// *** Detect if current version doesnot exist in version table and add it !!! e.g. after enabling versioning
		// ***

		if ( $this->_cparams->get('use_versioning', 1) && $record->id && $record->current_version > $record->last_version )
		{
			require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'flexicontent.php');
			$fleximodel = new FlexicontentModelFlexicontent();
			$fleximodel->addCurrentVersionData($record->id);
			$record->last_version = $record->last_version = $record->current_version;
		}
	}


	/**
	 * Method to sync FLEXIcontent item's tags with their respective Joomla article tags
	 *
	 * @return  void
	 *
	 * @since   3.3.0
	 */
	protected function syncJTagAssignments($fctags, $pks, $contexts, $replaceTags = true)
	{
		// Make sure re-usable member properties have been initialized
		$this->initBatch();

		$jtag_ids = array();
		$jtags = $this->createFindJoomlaTags($fctags, true, $indexCol = 'id');

		foreach($jtags as $fctag_id => $jtag)
		{
			$fctags[$fctag_id]->jtag_id_new = $jtag ? $jtag->id : 0;

			/**
			 * Warning ... the TagsHelper method expects that Tag Ids are 'STRING' type ! or if we want to pass tag
			 * titles of existing tags, ... we need to prefix them with '#new#' to avoid being interpreted as Tag Ids
			 */
			if ($jtag)
			{
				$jtag_ids[] = (string) $jtag->id;
			}

			$DEBUG_fctag_TO_jtag[$fctag_id] = $jtag ? $jtag->id : 0;
		}

		echo empty($this->debug_tags) ? null : '<b>' . __FUNCTION__ . '()</b><blockquote>'
			. (count($jtags) ?
				' FCTAG to JTAG ids: ' . print_r($DEBUG_fctag_TO_jtag, true) . '<br>'
					. ' Assigning Joomla Tags to item ' . reset($pks)
				: ' Deleting Joomla Tags assignments of item ' . reset($pks)
			) . '</blockquote>';

		foreach ($pks as $pk)
		{
			if ($this->user->authorise('core.edit', $contexts[$pk]))
			{
				$this->table->reset();
				$this->table->load($pk);

				// Add new tags, keeping existing ones
				if (FLEXI_J40GE)
				{
					$setTagsEvent = \Joomla\CMS\Event\AbstractEvent::create(
						'onTableSetNewTags',
						array(
							'subject'     => $this->table,
							'newTags'     => $jtag_ids,
							'replaceTags' => $replaceTags,
						)
					);

					try
					{
						$this->table->getDispatcher()->dispatch('onTableSetNewTags', $setTagsEvent);
					}
					catch (\RuntimeException $e)
					{
						$this->setError($e->getMessage());

						return false;
					}
				}
				else
				{
					if (!$jtag_ids)
					{
						//$this->table->tagsHelper->deleteTagData($this->table, $this->table->id);
						$this->tagsObserver->onBeforeDelete($this->table->id);
					}
					else
					{
						$result = $this->tagsObserver->setNewTags($jtag_ids, $replaceTags);

						if (!$result)
						{
							$this->setError($this->table->getError());

							return false;
						}
					}
				}
				//$this->table->store();
			}
			else
			{
				$this->setError(\JText::_('JLIB_APPLICATION_ERROR_BATCH_CANNOT_EDIT'));

				return false;
			}
		}

		// Clean the cache
		$this->cleanCache(null, 0);
		$this->cleanCache(null, 1);
	}


	/**
	 * Method to update mapping of FLEXIcontent tags to their respective Joomla tags
	 *
	 * @return  void
	 *
	 * @since   3.3.0
	 */
	protected function updateJTagMappings($fctags)
	{
		$i = 0;
		$query_vals = array();

		foreach($fctags as $fctag)
		{
			if ($fctag->jtag_id_new != $fctag->jtag_id)
			{
				$fctag->jtag_id = $fctag->jtag_id_new;

				$query_vals[$fctag->id] = ' WHEN ' . $fctag->id . ' THEN ' . $this->_db->Quote((int) $fctag->jtag_id);

				$DEBUG_fctag_TO_jtag[$fctag->id] = $fctag->jtag_id;
			}
			unset($fctag->jtag_id_new);
			$i++;
		}

		echo empty($this->debug_tags) ? null : '<b>' . __FUNCTION__ . '()</b><blockquote> '
			. (count($query_vals)
				? ' FCTAG to JTAG ids: ' . print_r($DEBUG_fctag_TO_jtag, true) . '<br>'
					. ' -- Setting jtag_id column OF __flexicontent_tags TABLE'
				: ' no need to update <b>jtag_id</b> column OF __flexicontent_tags TABLE '
			)  . '</blockquote>';

		if (count($query_vals))
		{
			$query 	= 'UPDATE #__flexicontent_tags SET jtag_id = CASE id '
				. implode(' ', $query_vals)
				. ' END '
				. ' WHERE id IN (' . implode(',', array_keys($query_vals)) . ')'
				;
			$this->_db->setQuery($query)->execute();
		}
	}


	/**
	 * Method to populate respective Joomla Tag data for a given array of FC tags
	 *
	 * @param   array   $fctags      The array of FC Tags
	 *
	 * @return  array   The array of FC Tags that was provided
	 *
	 * @since   3.3.0
	 */
	protected function loadJoomlaTagsData(& $fctags)
	{
		if (empty($fctags))
		{
			return $fctags;
		}

		$jtag_ids = array();

		foreach ($fctags as $fctag)
		{
			if ((int) $fctag->jtag_id)
			{
				$jtag_ids[] = (int) $fctag->jtag_id;
			}
		}

		if ($jtag_ids)
		{
			$query = $this->_db->getQuery(true)
				->select('*')
				->from('#__tags')
				->where('id IN (' . implode(', ', $jtag_ids) . ')');
			$jtags = $this->_db->setQuery($query)->loadObject('id');

			foreach ($fctags as $fctag)
			{
				$fctag->jtag = isset($jtags[$fctag->jtag_id])
					? $jtags[$fctag->jtag_id]
					: null;
			}
		}

		return $fctags;
	}


	/**
	 * Find Joomla tags when given respective FC tags, creating them if they do not exist
	 *
	 * @param   array   $tags       Tags text array from the field
	 * @param   array   $checkACL   Flag to indicate if tag creation ACL should be used
	 * @param   string  $indexCol   Tag table column (name) whose value to use for indexing the return array
	 *
	 * @return  array   An array of tag data (or null tag data), indexed by the value of the given tag column
	 *
	 * @since   3.3.0
	 */
	public function createFindJoomlaTags($tags, $checkACL = true, $indexCol = 'id')
	{
		return flexicontent_db::createFindJoomlaTags($tags, $checkACL, $indexCol);
	}


	/**
	 * Find FC tags when given respective Joomla tags, creating them if they do not exist
	 *
	 * @param   array   $tags       Tags text array from the field
	 * @param   array   $checkACL   Flag to indicate if tag creation ACL should be used
	 * @param   string  $indexCol   Tag table column (name) whose value to use for indexing the return array
	 *
	 * @return  array   An array of tag data (or null tag data), indexed by the value of the given tag column
	 *
	 * @since   3.3.0
	 */
	public function createFindFcTags($tags, $checkACL = true, $indexCol = 'id')
	{
		$newTags = array();

		if (empty($tags))
		{
			return $newTags;
		}

		// We will use the tags table to store them
		JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_flexicontent/tables');
		$tagTable  = JTable::getInstance('flexicontent_tags', '');
		$canCreate = FlexicontentHelperPerm::getPerm()->CanTags;

		foreach ($tags as $key => $tag)
		{
			$tagText = $tag->title;

			$loaded = false;

			// Clear old data if exist
			$tagTable->reset();

			// (A) Try to load the selected tag via id
			if ($tag->fctag_id && $tagTable->load((int) $tag->fctag_id))
			{
				$loaded = true;
			}

			// (B) Try to load the selected tag via title
			elseif ($tagTable->load(array('name' => $tagText)))
			{
				$loaded = true;
			}

			// (C) Create the tag if we are allowed to do it
			else
			{
				if ($checkACL && !$canCreate)
				{
					$newTags[$tag->{$indexCol}] = null;
					continue;
				}

				// Set title then call check() method to auto-create an alias
				$tagTable->name = $tagText;
				$tagTable->check();

				// (C) Try to load the selected tag, via auto-created alias
				if ($tagTable->alias && $tagTable->load(array('alias' => $tagTable->alias)))
				{
					$loaded = true;
				}

				// (D) Tag not found. Create a new tag at top-level with language 'ALL', with public access
				else
				{
					// Prepare tag data
					$tagTable->id = 0;
					$tagTable->name = $tagText;
					$tagTable->jtag_id = $tag->id;
					$tagTable->published = 1;

					// Try to store tag
					if ($tagTable->check())
					{
						if ($tagTable->store())
						{
							$loaded = true;
						}
					}
				}
			}

			if ($loaded)
			{
				if (!$tagTable->jtag_id)
				{
					$tagTable->jtag_id = $tag->id;
					$tagTable->store();
				}

				$newTags[$tag->{$indexCol}] = (object) array(
					'id' => (int) $tagTable->id,
					'title' => $tagTable->name,
					'alias' => $tagTable->alias
				);
			}
			else
			{
				$newTags[$tag->{$indexCol}] = null;
			}
		}

		return $newTags;
	}


	/**
	 * Method to get an array of the Joomla tags assigned to an item, optionally retrieving the tag data.
	 *
	 * @param   string   $contentType  Content type alias. Dot separated.
	 * @param   integer  $id           Id of the item to retrieve tags for.
	 * @param   boolean  $getTagData   If true, data from the tags table will be included, defaults to true.
	 *
	 * @return  array    Array of of tag objects
	 *
	 * @since   3.3.0
	 */
	public function getJTagsAssignments($contentType = null, $id = 0, $getTagData = true)
	{
		$this->initBatch();
		$db = $this->_db;

		$contentType = $contentType ?: $this->type_alias;
		$id = (int) ($id ?: $this->_id);

		// Create the query
		$query = $db->getQuery(true)
			->select('m.tag_id, ft.id AS fctag_id')
			->from('#__contentitem_tag_map AS m ')
			->join('INNER', '#__tags AS t ON m.tag_id = t.id')
			->join('LEFT', '#__flexicontent_tags AS ft ON ft.jtag_id = t.id')
			->where(array(
				'm.type_alias = ' . $db->quote($contentType),
				'm.content_item_id = ' . (int) $id,
			));

		if ($getTagData)
		{
			$query->select('t.*');
		}

		return $db->setQuery($query)->loadObjectList();
	}


	/**
	 * Method to merge Joomla tags assignments of an item, to existing FC tag assignments of the item, finding / creating / mapping respective FC tags as needed
	 *
	 * @param   array    $item       The FC item object
	 * @param   array    $jtags      The Joomla tags assigned to the item
	 *
	 * @return  array    Array all FC tags assigned to the item after merging Joomla tag assignments
	 *
	 * @since   3.3.0
	 */
	public function mergeJTagsAssignments($item = null, $jtags = null, $replaceTags = false)
	{
		$db = $this->_db;

		$item = $item ?: $this->_record;
		$jtags = $jtags !== null
			? $jtags
			: $this->getJTagsAssignments();

		// Find / Create respective fc tags
		$fctags = $this->createFindFcTags($jtags, $_checkACL = false, $_indexCol = 'id');

		$old_tags = array_flip($item->tags);
		$new_tags = array();

		// Find which tags are not already assigned to given FC item, or add them all if replacing existing ones
		foreach ($fctags as $fctag)
		{
			if ($replaceTags || !isset($old_tags[$fctag->id]))
			{
				$new_tags[] = $fctag->id;
			}
		}

		echo empty($this->debug_tags) ? null : '<b>' . __FUNCTION__ . '()</b><blockquote>'
			. ' EXISTING fctag_ids  = ' . print_r($old_tags, true) . '<br>'
			. ' EXTRA fctag_ids due to JTags = ' . print_r($new_tags, true)
			. '</blockquote>';


		// Only update FC tag relations in DB if we have new tags
		if (count($new_tags) || $replaceTags)
		{
			$tags = array_flip($new_tags);
			$this->saveFcTagsAssignments($new_tags, $item->id, $replaceTags);

			// Add the new assignment to the already loaded FC item
			foreach($new_tags as $fctag_id)
			{
				$item->tags[] = $fctag_id;
			}
		}

		return $new_tags;
	}



	/**
	 * Method to save Joomla tags when given existing Joomla tags assigned to an item
	 *
	 * @param   array     $tags       The FC tag ids to be assigned to the item
	 * @param   integer   $id         The FC item id
	 * @param   boolean   $replace    Indicates that old tags needed to be deleted (being replaced)
	 *
	 * @return  void
	 *
	 * @since   3.3.0
	 */
	public function saveJTagsAssignments($fctag_ids, $id = null)
	{
		$id = (int) ($id ?: $this->_id);

		// Make sure re-usable member properties have been initialized
		$this->initBatch();
		$asset = $this->type_alias . '.' . $id;

		// Create the tags helper instance will update the Joomla tags of the article, using the given tags observer instance
		if (!FLEXI_J40GE)
		{
			$this->createTagsHelper($this->tagsObserver, $this->type, $id, $this->type_alias, $this->table);
		}

		// Load data of FLEXIcontent tags
		$fctags = $this->getTagsByIds($fctag_ids);
		echo empty($this->debug_tags) ? null : '<b>' . __FUNCTION__ . '()</b><blockquote> '
			. ' -- fctag_ids: ' . print_r($fctag_ids, true) . '<br>'
			. ' -- CALLING -- <u>syncJTagAssignments</u>(fctags) -- TO save <b>JTAG assignments</b> for item ' . $id . '<br>'
			. ' -- CALLING -- <u>updateJTagMappings</u>(fctags) -- TO update <b>jtag_id</b> column of TBL \'flexicontent_tags\'<br>'
			. '</blockquote>';

		// Sync the tags assignments
		$this->syncJTagAssignments($fctags, array($id), array($id => $asset),  true);

		// Update (if needed) mappings of FLEXIcontent tags to Joomla tags
		$this->updateJTagMappings($fctags);
	}


	/**
	 * Method to save FC tags assigned to an item when given an array of FC tags Ids
	 *
	 * @param   array     $tags       The FC tag ids to be assigned to the item
	 * @param   integer   $id         The FC item id
	 * @param   boolean   $replace    Indicates that old tags needed to be deleted (being replaced)
	 *
	 * @return  void
	 *
	 * @since   3.3.0
	 */
	public function saveFcTagsAssignments($tags, $id = null, $replaceTags = true)
	{
		$db = $this->_db;
		$id = (int) ($id ?: $this->_id);

		// Delete old tag relations
		if ($replaceTags)
		{
			$query = 'DELETE FROM #__flexicontent_tags_item_relations'
				. ' WHERE itemid = ' . (int) $id
				;
			$db->setQuery($query)->execute();
		}

		echo empty($this->debug_tags) ? null : '<b>' . __FUNCTION__ . '()</b><blockquote> tags: ' . print_r($tags, true) . '</blockquote>';

		$tag_vals = array();
		foreach($tags as $tagid)
		{
			$tag_vals[] = '(' . (int) $tagid . ',' . (int) $id . ')';
		}

		// Store the new tag relations
		if (count($tag_vals))
		{
			// If replacing Tags then it is safe to use 'INSERT' otherwise avoid duplicate errors using the slower 'REPLACE'
			$query = ($replaceTags ? 'INSERT' : 'REPLACE')
				. ' INTO #__flexicontent_tags_item_relations'
				. ' (tid, itemid)'
				. ' VALUES ' . implode(',', $tag_vals)
				;
			$db->setQuery($query)->execute();
		}
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
		$app    = JFactory::getApplication();
		$isSite = $app->isClient('site');
		$CFGsfx = $isSite ? '_fe' : '_be';

		if (empty($this->_record) || empty($this->_record->parameters))
		{
			return;
		}

		foreach($this->mergeableGroups as $grp_name)
		{
			if ($grp_name === 'metadata')
			{
				if ($this->_record->parameters->get('usemetadata' . $CFGsfx, ($isSite ? 1 : 2)) < 1)
				{
					// Unset so that they will not be used during binding
					unset($data['metakey']);
					unset($data['metadesc']);
				}

				if ($this->_record->parameters->get('usemetadata' . $CFGsfx, ($isSite ? 1 : 2)) < 2)
				{
					// Set to false to indicate maintaining DB value
					foreach ($form->getGroup($grp_name) as $field)
					{
						$data[$grp_name][$field->fieldname] = false;
					}
				}

				continue;
			}

			foreach ($form->getFieldsets($grp_name) as $fsname => $fieldSet)
			{
				$skip = ($fsname === 'params-basic' && $this->_record->parameters->get('usedisplaydetails' . $CFGsfx, ($isSite ? 0 : 2)) < 1)
					|| ($fsname === 'params-advanced' && $this->_record->parameters->get('usedisplaydetails' . $CFGsfx, ($isSite ? 0 : 2)) < 2)
					|| ($fsname === 'params-seoconf' && $this->_record->parameters->get('useseoconf' . $CFGsfx, ($isSite ? 0 : 1)) < 1)
					|| ($fsname === 'themes' && $this->_record->parameters->get('selecttheme' . $CFGsfx, ($isSite ? 0 : 1)) < 1)
					;

				// Check for values that were posted individually and do not skipped them, e.g. comments in params-advanced
				$individual_params = array();
				if ($fsname === 'params-advanced' && (int) $this->_record->parameters->get('allowdisablingcomments' . $CFGsfx, 1))
				{
					$individual_params['comments'] = isset($data[$grp_name]['comments']) ? $data[$grp_name]['comments'] : false;
				}

				if ($skip)
				{
					// Set to false to indicate maintaining DB value
					foreach ($form->getFieldset($fsname) as $field)
					{
						$data[$grp_name][$field->fieldname] = false;
					}

					// Check for values that were posted individually and do not skipped them
					foreach ($individual_params as $field_name => $field_value)
					{
						$data[$grp_name][$field_name] = $field_value;
					}
				}
			}
		}
	}


	/**
	 * Create a JRegistry object checking for legacy bug of bad parameter merging code in during model saving
	 */
	private function _new_JRegistry($params)
	{
		if (!is_object($params))
		{
			$params = new JRegistry($params);
		}

		$attribs = $params->toArray();
		$err = false;

		foreach ($attribs as $i => $v)
		{
			if ($v === false)
			{
				unset($attribs[$i]);
				$err = true;
			}
		}

		if ($err || !is_object($params))
		{
			$params = new JRegistry($attribs);
		}

		return $params;
	}


	/**
	 * Re-add possibly modified main category to the multi-categories after events like:
	 *   onContentBeforeSave, onBeforeSaveField, onAllFieldsPostDataValidated, etc
	 *
	 * @param   array     $data         The form data by reference
	 * @param   object    $item         The item record
	 * @param   boolean   $save_catid   Indicates to save (remember) the current catid till next method call
	 *
	 * @return  void
	 *
	 * @since   3.4.0
	 */
	private function _update_mcats(&$data, $item, $save_catid = false)
	{
		static $main_catids = array();

		// Store current main category
		if ($save_catid)
		{
			$main_catids[$item->id] = $item->catid;
		}

		// Store update categories array
		else
		{
			$catid_before_events = $main_catids[$item->id];

			$item->catid   = (int) $item->catid;
			$data['catid'] = $item->catid;

			$cats_indexed = array_flip($data['categories']);
			unset($cats_indexed[$catid_before_events]);
			$cats_indexed[$item->catid] = 1;

			$data['categories'] = array_keys($cats_indexed);
		}
	}
}
