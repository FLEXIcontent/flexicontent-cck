<?php
/**
 * @version 1.5 stable $Id: item.php 1244 2012-04-12 05:07:35Z ggppdk $
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
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('legacy.model.admin');
use Joomla\String\StringHelper;

/**
 * FLEXIcontent Component Item Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class ParentClassItem extends JModelAdmin
{
	var $_name = 'ParentClassItem';
	
	/**
	 * component + type parameters
	 *
	 * @var object
	 */
	var $_cparams = null;
	
	/**
	 * Item data
	 *
	 * @var object
	 */
	var $_item = null;

	/**
	 * Item primary key
	 *
	 * @var int
	 */
	var $_id = null;
	
	/**
	 * Item current category id (used for FRONTEND only)
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
	 * @since 1.0
	 */
	public function __construct()
	{
		parent::__construct();
		
		$app = JFactory::getApplication();
		$jinput = $app->input;
		
		// --. Get & Set ITEM's primary key (pk) and (for frontend) the current category
		if (!$app->isAdmin())
		{
			// FRONTEND, use "id" from request
			$pk = $jinput->get('id', 0, 'int');
			$curcatid = $jinput->get('task', '', 'cmd')  ?  0  :  $jinput->get('cid', 0, 'int');
		}
		else
		{
			$id = $jinput->get('id', array(0), 'array');
			JArrayHelper::toInteger($id, array(0));
			$pk = (int) $id[0];
			
			if (!$pk)
			{
				$cid = $jinput->get('cid', array(0), 'array');
				JArrayHelper::toInteger($cid, array(0));
				$pk = (int) $cid[0];
			}
			
			// Finally try form
			if (!$pk)
			{
				$data = $jinput->get('jform', array('id'=>0), 'array');
				$pk = (int) $data['id'];
			}

			$curcatid = 0;
		}
		
		$typeid = !$pk ? JRequest::getInt('typeid', 0) : 0;  // Set type id only for new items
		$this->setId($pk, $curcatid, $typeid);  // NOTE: when setting $pk to a new value the $this->_item is cleared
		
		$this->populateState();
	}
	
	
	/**
	 * Method to set the identifier
	 *
	 * @access	public
	 * @param	int item identifier
	 */
	function setId($id, $currcatid=0, $typeid=0, $ilayout=null)
	{
		// Set a new item id and wipe data
		if ($this->_id != $id) {
			$this->_item = null;
			$this->_version = null;
			$this->_cparams = null;
		}
		$this->_id = (int) $id;
		
		// Set current category and verify that item is assigned to this category, (SECURITY concern)
		$this->_cid = (int) $currcatid;
		if ($this->_cid)
		{
			$q = "SELECT catid FROM #__flexicontent_cats_item_relations WHERE itemid =". (int)$this->_id ." AND catid = ". (int)$this->_cid;
			$this->_db->setQuery($q);
			$result = $this->_db->loadResult();
			$this->_cid = $result ? $this->_cid : 0;  // Clear cid, if category not assigned to the item
		}
		
		// Set item layout
		$this->_ilayout = $ilayout;
		
		// Set item type, will be verified below
		$this->_typeid = $typeid;
		
		// Get the type of an existing item, or check that the type of new item exists
		if ($this->_id || $this->_typeid)
		{
			$this->getTypesselected();  // Check, set, or clear member variable: $this->_typeid
		}
		
		// Recalcuclate if needed, component + type parameters
		$this->getComponentTypeParams();
	}
	
	
	/**
	 * Method to get component + type parameters these are enough for the backend,
	 * also they are needed before frontend view's FULL item parameters are created
	 *
	 * @access	public
	 * @return	int item identifier
	 */
	function getComponentTypeParams()
	{
		// Calculate component + type parameters
		if ($this->_cparams) return $this->_cparams;
		$app = JFactory::getApplication();
		
		// Get component parameters
		$params  = new JRegistry();
		$cparams = JComponentHelper::getParams('com_flexicontent');
		$params->merge($cparams);
		
		// Merge into them the type parameters, *(type was set/verified above)
		if ($this->_typeid) {
			$tparams = $this->getTypeparams();
			$tparams = new JRegistry($tparams);
			$params->merge($tparams);
		}
		
		// Set and return component + type parameters
		$this->_cparams = $params;
		return $this->_cparams;
	}
	
	
	/**
	 * Method to get item's id
	 *
	 * @access	public
	 * @return	int item identifier
	 */
	function getId()
	{
		return $this->_id;
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
	 * Overridden get method to get properties from the item
	 *
	 * @access	public
	 * @param	string	$property	The name of the property
	 * @param	mixed	$value		The value of the property to set
	 * @return 	mixed 				The value of the property
	 * @since	1.5
	 */
	function get($property, $default=null)
	{
		if ($this->_item || $this->_loadItem()) {
			if(isset($this->_item->$property)) {
				return $this->_item->$property;
			}
		}
		return $default;
	}


	/**
	 * Overridden set method to pass properties on to the item
	 *
	 * @access	public
	 * @param	string	$property	The name of the property
	 * @param	mixed	$value		The value of the property to set
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function set( $property, $value=null )
	{
		if ($this->_item || $this->_loadItem()) {
			$this->_item->$property = $value;
			return true;
		} else {
			return false;
		}
	}
	
	
	/**
	 * Method to get item data
	 *
	 * @access	public
	 * @return	array
	 * @since	1.0
	 */
	function &getItem($pk=null, $check_view_access=true, $no_cache=false, $force_version=0)
	{
		$app     = JFactory::getApplication();
		$cparams = $this->_cparams;
		
		// View access done is meant only for FRONTEND !!! ... force it to false
		if ( $app->isAdmin() ) $check_view_access = false;
		
		// Initialise and set primary if it was not given already
		$pk = !empty($pk) ? $pk : $this->_id;
		$pk = !empty($pk) ? $pk : (int) $this->getState($this->getName().'.id');
		
		// Set new item id, clearing item data, ONLY IF DIFFERENT than existing primary key
		if ($pk != $this->_id) {
			$this->setId($pk);
		}
		
		// --. Try to load existing item ... ZERO $force_version means unversioned data or maintain currently loaded version
		if ( $pk && $this->_loadItem($no_cache, $force_version) )
		{
			// Successfully loaded existing item, do some extra manipulation of the loaded item ...
			// Extra Steps for Frontend
			if ( !$app->isAdmin() )  {
				// Load item parameters with heritage
				$this->_loadItemParams($no_cache);
				// Check item viewing access
				if ( $check_view_access ) $this->_check_viewing_access($force_version);
			}
		}
		
		// --. Failed to load existing item, or check_view_access indicates not to create a new item object
		else if ( $pk || $check_view_access===2 )
		{
			$msg = $pk ?
				JText::sprintf('FLEXI_CONTENT_UNAVAILABLE_ITEM_NOT_FOUND', $pk) :   // ID is set, indicate that it was not found
				JText::_( 'FLEXI_REQUESTED_PAGE_COULD_NOT_BE_FOUND' );  // ID is not set propably some bad URL so give a more general message
			throw new Exception($msg, 404);
		}
		
		// --. Initialize new item, currently this succeeds always
		else
		{
			$this->_typeid = JRequest::getInt('typeid', 0);  // Get this again since it might have been change since model was constructed
			$this->_initItem();
			if ( !$app->isAdmin() )  {
				// Load item parameters with heritage, (SUBMIT ITEM FORM)
				$this->_loadItemParams($no_cache);
			}
		}
		
		// Verify item's type
		$this->_item->type_id = $this->getTypesselected()->id;  // Also checks, sets, or clears member variable: $this->_typeid
		
		return $this->_item;
	}
	
	
	/**
	 * Method to load item data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _loadItem( $no_cache=false, $force_version=0 )
	{
		if(!$this->_id) return false;  // Only try to load existing item
		
		//echo 'force_version: '.$force_version ."<br/>";
		//echo "<pre>"; debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS); echo "</pre>";
		
		// -- To load a different item:
		// a. use member function function setId($id, $currcatid=0) to change primary key and then call getItem()
		// b. call getItem($pk, $check_view_access=true) passing the item id and maybe also disabling read access checkings, to avoid unwanted messages/errors
		
		// This is ITEM cache. NOTE: only unversioned items are cached
		static $items = array();
		global $fc_list_items;    // Global item cache (unversioned items)
		
		// Clear item to make sure it is reloaded
		if ( $no_cache ) {
			$this->_item = null;
		}
		
		// Only retrieve item if not already, ZERO $force_version means unversioned data or maintain currently loaded version
		else if ( isset($this->_item) && (!$force_version || $force_version==$this->_version) ) {
			//echo "********************************<br/>\n RETURNING ALREADY loaded item: {$this->_id}<br/> ********************************<br/><br/><br/>";
			return (boolean) $this->_item;
		}
		
		static $unapproved_version_notice;
		
		$db   = $this->_db;
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		$cparams = $this->_cparams;
		$task    = JRequest::getVar('task', false);
		$layout  = JRequest::getVar('layout', false);
		$view    = JRequest::getVar('view', false);
		$option  = JRequest::getVar('option', false);
		$use_versioning = $cparams->get('use_versioning', 1);
		$editjf_translations = $cparams->get('editjf_translations', 0);
		
		// ***********************************************************************
		// Check if loading specific VERSION and generate version related messages
		// ***********************************************************************
		
		$current_version = (int) FLEXIUtilities::getCurrentVersions($this->_id, true, $force=true); // Get current item version
		$last_version    = (int) FLEXIUtilities::getLastVersions($this->_id, true, $force=true);    // Get last version (=latest one saved, highest version id)
		
		// -- Decide the version to load: (a) the one specified or (b) UNversioned data (these should be same as current version data) or (c) the latest one
		if ( !$use_versioning ) {
			// Force version to zero (load current version), when not using versioning mode
			$version = 0;
		} else if ($force_version) {
			$version = (int)$force_version;
			if ($version == -1) {
				// Load latest, but catch cases when we enable versioning mode after an item has been saved in unversioning mode
				// in these case we will load CURRENT version instead of the default for the item edit form which is the LATEST (for backend/fontend)
				//echo "LOADING LATEST: current_version >= last_version : $current_version >= $last_version <br/>";
				$version = ($current_version >= $last_version) ? 0 : $last_version;
			}
		} else {
			$version = 0; // Load unversioned data
		}
		
		// Check if item is alredy loaded and is of correct version
		if ( $this->_version == $version && isset($this->_item) ) {
			return (boolean) $this->_item;
		}
		$this->_version = $version; // Set number of loaded version
		//echo 'version: '.$version ."<br/>";
		
		// Current version number given, the data from the versioning table should be the same as the data from normal tables
		// we do not force $version to ZERO to allow testing the field data of current version from the versioning table
		//if ($version == $current_version) $version = 0;   // Force zero to retrieve unversioned data
		
		// Check if not loading the current version while we are in edit form, and raise a notice to inform the user
		if ($version && $version != $current_version  && $task=='edit' && $option=='com_flexicontent' && !$unapproved_version_notice) {
			$unapproved_version_notice = 1;
			if (!$app->isAdmin()) {
				JError::raiseNotice(10, JText::_('FLEXI_LOADING_UNAPPROVED_VERSION_NOTICE') );
			} else {
				JError::raiseNotice(10,
					JText::_('FLEXI_LOADING_UNAPPROVED_VERSION_NOTICE') . ' :: ' .
					JText::sprintf('FLEXI_LOADED_VERSION_INFO_NOTICE', $version, $current_version)
				);
			}
		}
		
		
		// Only unversioned items are cached, use cache if no specific version was requested
		if ( !$version && isset($items[$this->_id]) ) {
			//echo "********************************<br/>\n RETURNING CACHED item: {$this->_id}<br/> ********************************<br/><br/><br/>";
			$this->_item = $items[$this->_id];
			return (boolean) $this->_item;
		}
		
		//echo "**************************<br/>\n LOADING item id: {$this->_id}  version:{$this->_version}<br/> **************************<br/><br/><br/>";
		
		// *********************
		// TRY TO LOAD ITEM DATA
		// *********************
		try
		{
			if ( $app->isAdmin() )
			{
				// **********************
				// Item Retrieval BACKEND
				// **********************
				$item   = $this->getTable('flexicontent_items', '');
				$result = $item->load($this->_id);  // try loading existing item data
				if ($result===false) {
					$this->_item = false;
					if (!$version) {
						$items[$this->_id] = $this->_item;
						$fc_list_items[$this->_id] = $this->_item;
					}
					return false; // item not found, return
				}
			}
			else
			{
				// ***********************
				// Item Retrieval FRONTEND
				// ***********************
				
				// Extra access columns for main category and content type (item access will be added as 'access')
				$select_access = 'mc.access as category_access, ty.access as type_access';
				
				// Access Flags for: content type, main category, item
				$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
				$aid_list = implode(",", $aid_arr);
				$select_access .= ', CASE WHEN ty.access IN (0,'.$aid_list.') THEN 1 ELSE 0 END AS has_type_access';
				$select_access .= ', CASE WHEN mc.access IN (0,'.$aid_list.') THEN 1 ELSE 0 END AS has_mcat_access';
				$select_access .= ', CASE WHEN  i.access IN (0,'.$aid_list.') THEN 1 ELSE 0 END AS has_item_access';
				
				// SQL date strings, current date and null date
				$nowDate = $db->Quote( JFactory::getDate()->toSql() );
				$nullDate	= $db->Quote($db->getNullDate());
				
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
				$query->select('u.name AS author');                                       // Author data
				
				// Rating count, Rating & Score
				$query->select('v.rating_count as rating_count, ROUND( v.rating_sum / v.rating_count ) AS rating, ((v.rating_sum / v.rating_count)*'.(100 / $rating_resolution).') as score');
				
				// Item and Current Category slugs (for URL)
				$query->select('CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug');
				$query->select('CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug');
				
				// Publication Scheduled / Expired Flags
				$query->select('CASE WHEN i.publish_up = '.$nullDate.' OR i.publish_up <= '.$nowDate.' THEN 0 ELSE 1 END as publication_scheduled');
				$query->select('CASE WHEN i.publish_down = '.$nullDate.' OR i.publish_down >= '.$nowDate.' THEN 0 ELSE 1 END as publication_expired' );
				
				// From content table, and extended item table, content type table, user table, rating table, categories relation table
				$query->from('#__content AS i');
				$query->join('LEFT', '#__flexicontent_items_ext AS ie ON ie.item_id = i.id');
				$query->join('LEFT', '#__flexicontent_types AS ty ON ie.type_id = ty.id');
				$query->join('LEFT', '#__users AS u on u.id = i.created_by');
				$query->join('LEFT', '#__content_rating AS v ON i.id = v.content_id');
				$query->join('LEFT', '#__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id' . $limit_to_cid);
				
				// Join twice on category table, once for current category and once for item's main category
				$query->join('LEFT', '#__categories AS c on c.id = rel.catid');   // All item's categories
				$query->join('LEFT', '#__categories AS mc on mc.id = i.catid');   // Item's main category
				
				// HANDLE J1.6+ ancestor category being unpublished, when badcats.id is not null,
				// then the item is inside in an unpublished ancestor category, thus inaccessible
				/*$query->select('CASE WHEN badcats.id is null THEN 1 ELSE 0 END AS ancestor_cats_published');
				$subquery = ' (SELECT cat.id as id FROM #__categories AS cat JOIN #__categories AS parent ';
				$subquery .= 'ON cat.lft BETWEEN parent.lft AND parent.rgt ';
				$subquery .= 'WHERE parent.extension = ' . $db->Quote('com_content');
				$subquery .= ' AND parent.published <= 0 GROUP BY cat.id)';
				$query->join('LEFT', $subquery . ' AS badcats ON badcats.id = c.id');*/
				
				if ($version) {
					// NOTE: version_id is used by field helper file to load the specified version, the reason for left join here is to verify that the version exists
					$query->join('LEFT', '#__flexicontent_versions AS ver ON ver.item_id = i.id AND ver.version_id = '. $db->Quote($version) );
				}
				
				// Join on contact table, to get contact data of author
				//$query = 'SHOW TABLES LIKE "' . JFactory::getApplication()->getCfg('dbprefix') . 'contact_details"';
				//$db->setQuery($query);
				//$contact_details_tbl_exists = (boolean) count($db->loadObjectList());
				//if ( $contact_details_tbl_exists) {
				//	$query->select('contact.id as contactid' ) ;
				//	$query->join('LEFT','#__contact_details AS contact on contact.user_id = i.created_by');
				//}
				
				// Join over the categories to get parent category titles
				//$query->select('parent.title as parent_title, parent.id as parent_id, parent.path as parent_route, parent.alias as parent_alias');
				//$query->join('LEFT', '#__categories as parent ON parent.id = c.parent_id');
				
				$query->where('i.id = ' . (int) $this->_id);
				//echo $db->replacePrefix($query);

				
				$db->setQuery($query);
				
				// Try to execute query directly and load the data as an object
				if ( FLEXI_FISH && $task=='edit' && $option=='com_flexicontent' && in_array( $app->getCfg('dbtype') , array('mysqli','mysql') ) ) {
					$data = flexicontent_db::directQuery($query);
					$data = @ $data[0];
					//$data = $db->loadObject(null, false);   // do not, translate, this is the JoomFish overridden method of Database extended Class
				} else {
					$data = $db->loadObject();
				}
				
				// Check for SQL error
				if ( $db->getErrorNum() )  throw new Exception($db->getErrorMsg(), 500);
				//print_r($data); exit;
				
				if (!$data) {
					$this->_item = false;
					$this->_typeid = 0;
					if (!$version) {
						$items[$this->_id] = $this->_item;
						$fc_list_items[$this->_id] = $this->_item;
					}
					return false; // item not found, return
				}
				
				if ($version && !$data->version_id) {
					JError::raiseNotice(10, JText::sprintf('NOTICE: Requested item version %d was not found, loaded currently active version', $version));
				}
				
				$item = & $data;
			}
			$this->_typeid = $item->type_id;
			
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
					JArrayHelper::toInteger($item->associations);
				}
			}
			
			// *************************************************************************************************
			// -- Retrieve all active site languages, and create empty item translation objects for each of them
			// *************************************************************************************************
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
			
			// **********************************
			// Retrieve and prepare JoomFish data
			// **********************************
			if ( FLEXI_FISH && $task=='edit' && $option=='com_flexicontent' )
			{
				// -- Try to retrieve all joomfish data for the current item
				$query = "SELECT jfc.language_id, jfc.reference_field, jfc.value, jfc.published "
						." FROM #__".$nn_content_tbl." as jfc "
						." WHERE jfc.reference_table='content' AND jfc.reference_id = {$this->_id} ";
				$db->setQuery($query);
				$translated_fields = $db->loadObjectList();
				
				if ( $editjf_translations == 0 && $translated_fields ) {  // 1:disable without warning about found translations
					$app->enqueueMessage( "3rd party Joom!Fish/Falang translations detected for current item."
						." You can either enable editing them or disable this message in FLEXIcontent component configuration", 'message' );
				} else if ( $editjf_translations == 2 ) {
					if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
					
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
						//echo "<br/>Joom!Fish translations found for: " . implode(",", $found_languages);
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
			
			// *****************************************************
			// Overwrite item fields with the requested VERSION data
			// *****************************************************
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
					." WHERE iv.version='".$version."' AND (f.iscore=1 OR iv.field_id=-1 OR iv.field_id=-2) AND iv.item_id='".$this->_id."'"
					." GROUP BY f.id";
				$db->setQuery($query);
				$fields = $db->loadObjectList();
				$fields = $fields ? $fields : array();
				
				//echo "<br/>Overwritting fields with version: $version";
				foreach($fields as $f) {
					//echo "<br/><b>{$f->field_id} : ". $f->name."</b> : "; print_r($f->value);
					
					// Use versioned data, by overwriting the item data 
					$fieldname = $f->name;
					if ($f->field_type=='hits' || $f->field_type=='state' || $f->field_type=='voting') {
						// skip fields that should not have been versioned: hits, state, voting
						continue;
					} else if ($f->field_type=='version') {
						// set version variable to indicate the loaded version
						$item->version = $version;
					} else if( $fieldname=='categories'|| $fieldname=='tags' ) {
						// categories and tags must have been serialized but some earlier versions did not do it,
						// we will check before unserializing them, otherwise they were concatenated to a single string and use explode ...
						$item->$fieldname = ($array = @unserialize($f->value)) ? $array : explode(",", $f->value);
					} else if ($f->field_id==-1) {
						if ( FLEXI_FISH ) {
							$jfdata = unserialize($f->value);
							$item_lang = substr($item->language ,0,2);
							foreach ($item_translations as $lang_id => $translation_data) {
								//echo "<br/>Adding values for: ".$translation_data->shortcode;
								if ( empty($jfdata[$translation_data->shortcode]) ) continue;
								foreach ($jfdata[$translation_data->shortcode] as $fieldname => $fieldvalue)
								{
									//echo "<br/>".$translation_data->shortcode.": $fieldname => $fieldvalue";
									if ($translation_data->shortcode != $item_lang) {
										$translation_data->fields->$fieldname = new stdClass();
										$translation_data->fields->$fieldname->value = $fieldvalue;
									} else {
										$item->$fieldname = $fieldvalue;
									}
								}
							}
						}
					} else if ($f->field_id==-2) {
						// Other item properties that were versioned, such as alias, catid, meta params, attribs
						$item_data = unserialize($f->value);
						//$item->bind($item_data);
						foreach ($item_data as $k => $v) $item->$k = $v;
					} else if ($fieldname) {
						// Other fields (maybe serialized or not but we do not unserialized them, this is responsibility of the field itself)
						$item->$fieldname = $f->value;
					}
				}
				// The text field is stored in the db as to seperate fields: introtext & fulltext
				// So we search for the {readmore} tag and split up the text field accordingly.
				$this->splitText($item);
			}
			
			// -- Retrieve tags field value (if not using versioning)
			if ( $use_versioning && $version ) {
				// Check version value was found
				if ( !isset($item->tags) || !is_array($item->tags) )
					$item->tags = array();
			} else {
				// Retrieve unversioned value
				$query = 'SELECT DISTINCT tid FROM #__flexicontent_tags_item_relations WHERE itemid = ' . (int)$this->_id;
				$db->setQuery($query);
				$item->tags = $db->loadColumn();
				$item->tags = array_reverse($item->tags);
			}
			
			// -- Retrieve categories field value (if not using versioning)
			if ( $use_versioning && $version ) {
				// Check version value was found, and is valid (above code should have produced an array)
				if ( !isset($item->categories) || !is_array($item->categories) )
					$item->categories = array();
			} else {
				// Retrieve unversioned value
				$query = 'SELECT DISTINCT catid FROM #__flexicontent_cats_item_relations WHERE itemid = ' . (int)$this->_id;
				$db->setQuery($query);
				$item->categories = $db->loadColumn();
			}
			
			// Make sure catid is in categories array
			if ( !in_array($item->catid, $item->categories) ) $item->categories[] = $item->catid;
			
			// 'cats' is an alias of categories
			$item->cats = & $item->categories;
			
			// Set original content item id, e.g. maybe used by some fields that are marked as untranslatable
			$useAssocs = $this->useAssociations();
			if ($useAssocs)
			{
				$site_default = substr(flexicontent_html::getSiteDefaultLang(), 0,2);
				$is_content_default_lang = $site_default == substr($item->language, 0,2);
				if ($is_content_default_lang) {
					$item->lang_parent_id = $item->id;
				} else {
					$item->lang_parent_id = 0;
					$langAssocs = $this->getLangAssocs($item->id);
					foreach($langAssocs as $content_id => $_assoc) {
						if ($site_default == substr($_assoc->language, 0,2)) {
							$item->lang_parent_id = $content_id;
							break;
						}
					}
				}
			}
			
			
			// *********************************************************
			// Retrieve item properties not defined in the model's CLASS
			// *********************************************************
			
			if ( !isset($item->author) )
			{
				$query = 'SELECT name FROM #__users WHERE id = '. (int) ($item->id ? $item->created_by : $user->id);
				$db->setQuery($query);
				$item->author = $db->loadResult();
			}
			
			$query = 'SELECT title FROM #__viewlevels WHERE id = '. (int) $item->access;
			$db->setQuery($query);
			$item->access_level = $db->loadResult();
			
			// Category access is retrieved here for J1.6+, for J1.5 we use FLEXIaccess
			// Get category access for the item's main category, used later to determine viewing of the item
			$query = 'SELECT access FROM #__categories WHERE id = '. (int) $item->catid;
			$db->setQuery($query);
			$item->category_access = $db->loadResult();
			
			// Typecast some properties in case LEFT JOIN produced nulls
			if ( !isset($item->type_access) ) {
				$public_acclevel = 1;
				$item->type_access = $public_acclevel;
			}
			
			if ( !isset($item->rating_count) ) {
				$rating_resolution = (int)$this->getVotingResolution();
				
				// Get category access for the item's main category, used later to determine viewing of the item
				$query = 'SELECT '
					.' v.rating_count as rating_count, ROUND( v.rating_sum / v.rating_count ) AS rating, ((v.rating_sum / v.rating_count)*'.(100 / $rating_resolution).') as score'
					.' FROM #__content_rating AS v WHERE v.content_id = '. (int) $item->id
					;
				$db->setQuery($query);
				$rating_data = $db->loadObject();
				$item->rating_count = !$rating_data ? 0 : $rating_data->rating_count;
				$item->rating       = !$rating_data ? 0 : $rating_data->rating_count;
				$item->score        = !$rating_data ? 0 : $rating_data->score;
			}
			$item->typename     = (string) @ $item->typename;
			$item->typealias    = (string) @ $item->typealias;
			
			// Retrieve Creator NAME and email (used to display the gravatar)
			$query = 'SELECT name, email FROM #__users WHERE id = '. (int) $item->created_by;
			$db->setQuery($query);
			$creator_data = $db->loadObject();
			$item->creator = $creator_data ? $creator_data->name : '';
			$item->creatoremail = $creator_data ? $creator_data->email : '';
			
			// Retrieve Modifier NAME
			if ($item->created_by == $item->modified_by) {
				$item->modifier = $item->creator;
			} else {
				$query = 'SELECT name, email FROM #__users WHERE id = '. (int) $item->modified_by;
				$db->setQuery($query);
				$modifier_data = $db->loadObject();
				$item->modifier = $modifier_data ? $modifier_data->name : '';
				$item->modifieremail = $modifier_data ? $modifier_data->email : '';
			}
			
			// Clear modified Date, if it is an invalid "null" date
			if ($item->modified == $db->getNulldate()) {
				$item->modified = null;
			}
			
			// ***************************************************************************************
			// Assign to the item data member variable and cache it if loaded an unversioned item data
			// ***************************************************************************************
			$this->_item = & $item;
			if (!$version) {
				$items[$this->_id] = $this->_item;
				$fc_list_items[$this->_id] = $this->_item;
			}
			
			// ******************************************************************************************************
			// Detect if current version doesnot exist in version table and add it !!! e.g. after enabling versioning 
			// ******************************************************************************************************
			if ( $use_versioning && $current_version > $last_version ) {
				require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'flexicontent.php');
				$fleximodel = new FlexicontentModelFlexicontent();
				$fleximodel->addCurrentVersionData($item->id);
				$item->last_version = $last_version = $current_version;
			}
		}
		
		// ***********************************************
		// CATCH EXCEPTION THROWN DURING LOADING ITEM DATA
		// ***********************************************
		catch (JException $e)
		{
			$this->_item = false;
			if ($e->getCode() == 404) {
				// Need to go thru the error handler to allow Redirect to work.
				$msg = $e->getMessage();
				throw new Exception($msg, 404);
			}
			else {
				$this->setError($e);
				$this->_item = false;
			}
		}
		
		/*$session = JFactory::getSession();
		$postdata = $session->get('item_edit_postdata', array(), 'flexicontent');
		if (count($postdata)) {
			$session->set('item_edit_postdata', null, 'flexicontent');
			// ...
		}*/
		
		// Add to cache if it is non-version data
		if (!$version) {
			$items[$this->_id] = $this->_item;
			$fc_list_items[$this->_id] = $this->_item;
		}
		
		// return true if item was loaded successfully
		return (boolean) $this->_item;
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
	public function getTable($type = 'flexicontent_items', $prefix = '', $config = array()) {
		return JTable::getInstance($type, $prefix, $config);
	}
	
	/**
	 * Method to get the row form.
	 *
	 * @param	array	$data		Data for the form.
	 * @param	boolean	$loadData	True if the form is to load its own data (default case), false if not.
	 * @return	mixed	A JForm object on success, false on failure
	 * @since	1.6
	 */
	public function getForm($data = array(), $loadData = true)
	{
		$app = JFactory::getApplication();
		
		// Retrieve item if not already done, (loading item should been done by the view !), but:
		// 1) allow maintaining existing value:   $no_cache=false
		// 2) load by default the last saved version:   $force_version=-1   means latest (last saved) version)
		$this->getItem(null, $check_view_access=false, $no_cache=false, $force_version=0);
		
		// *********************************************************
		// Prepare item data for being loaded into the form:
		// (a) Convert parameters 'images', 'urls,' 'attribs' & 'metadata' to an array
		// (b) Set property 'cid' (form field categories)
		// *********************************************************
		
		$this->_item->itemparams = new JRegistry();
		
		if ($this->_id) {
			// Convert the images
			$images = $this->_item->images;
			$registry = new JRegistry;
			$registry->loadString($images);
			$this->_item->images = $registry->toArray();
			$this->_item->itemparams->merge($registry);
	
			// Convert the urls
			$urls = $this->_item->urls;
			$registry = new JRegistry;
			$registry->loadString($urls);
			$this->_item->urls = $registry->toArray();
			$this->_item->itemparams->merge($registry);
	
			// Convert the attribs
			$attribs = $this->_item->attribs;
			$registry = new JRegistry;
			$registry->loadString($attribs);
			$this->_item->attribs = $registry->toArray();
			$this->_item->itemparams->merge($registry);
	
			// Convert the metadata
			$metadata = $this->_item->metadata;
			$registry = new JRegistry;
			$registry->loadString($metadata);
			$this->_item->metadata = $registry->toArray();
			$this->_item->itemparams->merge($registry);
		} else {
			$images = $urls = $attribs = $metadata = '';
			$this->_item->attribs = array();
			$this->_item->metadata = array();
			$this->_item->images = array();
			$this->_item->urls = array();
		}
		//echo "<pre>"; print_r($this->_item->itemparams); exit;
		
		// Set item property 'cid' (form field categories is named cid)
		$this->_item->cid = $this->_item->categories;
		
		// ****************************************************************************
		// Load item data into the form and restore the changes done above to item data
		// ****************************************************************************
		$form = $this->loadForm('com_flexicontent.'.$this->getName(), $this->getName(), array('control' => 'jform', 'load_data' => $loadData));
		if (empty($form)) {
			return false;
		}
		$form->option = $this->option;
		$form->context = $this->getName();
		
		$this->_item->images = $images;
		$this->_item->urls   = $urls;
		$this->_item->attribs  = $attribs;
		$this->_item->metadata = $metadata;
		unset($this->_item->cid);
		
		// Determine correct permissions to check.
		$id = @$data['id'] ? $data['id'] : (int) $this->getState($this->getName().'.id');
		if ($id) {
			// Existing record. Can only edit in selected categories.
			$form->setFieldAttribute('catid', 'action', 'core.edit');
			// Existing record. Can only edit own articles in selected categories.
			$form->setFieldAttribute('catid', 'action', 'core.edit.own');
		}
		else {
			// New record. Can only create in selected categories.
			$form->setFieldAttribute('catid', 'action', 'core.create');
		}

		// Modify the form based on Edit State access controls.
		if ( empty($this->_item->submit_conf['autopublished']) && !$this->canEditState( empty($data) ? null : (object)$data ) )
		{
			$frontend_new = !$id && $app->isSite();
			
			// Disable fields for display.
			$form->setFieldAttribute('featured', 'disabled', 'true');
			$form->setFieldAttribute('ordering', 'disabled', 'true');
			$form->setFieldAttribute('publish_up', 'disabled', 'true');
			$form->setFieldAttribute('publish_down', 'disabled', 'true');
			$form->setFieldAttribute('created_by', 'disabled', 'true');
			$form->setFieldAttribute('created_by_alias', 'disabled', 'true');
			if ( !$frontend_new ) {
				// skip new items in frontend to allow override via menu (auto-publish), menu override must be checked during store
				$form->setFieldAttribute('state', 'disabled', 'true');   // only for existing items, not for new to allow menu item override
			}
			//$form->setFieldAttribute('vstate', 'disabled', 'true');  // DO not -disable- will cause problems
			
			// Disable fields while saving.
			// The controller has already verified this is an article you can edit.
			$form->setFieldAttribute('featured', 'filter', 'unset');
			$form->setFieldAttribute('ordering', 'filter', 'unset');
			$form->setFieldAttribute('publish_up', 'filter', 'unset');
			$form->setFieldAttribute('publish_down', 'filter', 'unset');
			$form->setFieldAttribute('created_by', 'filter', 'unset');
			$form->setFieldAttribute('created_by_alias', 'filter', 'unset');
			if ( !$frontend_new ) {
				// skip new items in frontend to allow override via menu (auto-publish), menu override must be check during store
				$form->setFieldAttribute('state', 'filter', 'unset');   // only for existing items, not for new to allow menu item override
			}
			//$form->setFieldAttribute('vstate', 'filter', 'unset');   // DO not -filter- will cause problems
		}
		
		// Check if article is associated, and disable changing category and language
		/*$useAssocs = $this->useAssociations();
		
		if ($id && $app->isSite() && $useAssocs)
		{
			$associations = JLanguageAssociations::getAssociations('com_content', '#__content', 'com_content.item', $id);
			
			// Make fields read only
			if ($associations)
			{
				$form->setFieldAttribute('language', 'readonly', 'true');
				$form->setFieldAttribute('catid', 'readonly', 'true');
				$form->setFieldAttribute('language', 'filter', 'unset');
				$form->setFieldAttribute('catid', 'filter', 'unset');
			}
		}*/

		return $form;
	}
	
	
	/**
	 * Auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @param   JForm   $form   The form object
	 * @param   array   $data   The data to be merged into the form object
	 * @param   string  $group  The plugin group to be executed
	 *
	 * @return  void
	 *
	 * @since    3.0
	 */
	protected function preprocessForm(JForm $form, $data, $group = 'content')
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

		parent::preprocessForm($form, $data, $group);
	}
	
	
	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return	mixed	The data for the form.
	 * @since	1.6
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$app = JFactory::getApplication();
		$data = $app->getUserState('com_flexicontent.edit.'.$this->getName().'.data', array());
		
		// Clear form data from session ?
		$app->setUserState('com_flexicontent.edit.'.$this->getName().'.data', false);
		
		if (empty($data)) {
			$data = $this->_item ? $this->_item : $this->getItem();
		} else {
			// Split text to introtext & fulltext
			if ( !StringHelper::strlen(StringHelper::trim(@$data['introtext'])) && !StringHelper::strlen(StringHelper::trim(@$data['fulltext'])) ) {
				$this->splitText($data);
			}
			
			if ($this->_item) {
				if ( StringHelper::strlen(StringHelper::trim(@$data['text'])) )      $this->_item->text      = $data['text'];
				if ( StringHelper::strlen(StringHelper::trim(@$data['introtext'])) ) $this->_item->introtext = $data['introtext'];
				if ( StringHelper::strlen(StringHelper::trim(@$data['fulltext'])) )  $this->_item->fulltext  = $data['fulltext'];
				if ( isset($data['language']) )  $this->_item->language  = $data['language'];
				if ( isset($data['catid']) )     $this->_item->catid  = $data['catid'];
			}
		}

		// If there are params fieldsets in the form it will fail with a registry object
		if (isset($data->params) && $data->params instanceof Registry)
		{
			$data->params = $data->params->toArray();
		}

		$this->preprocessData('com_flexicontent.'.$this->getName(), $data);
		
		return $data;
	}
	
	
	/**
	 * Method to calculate Item Access Permissions
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	function getItemAccess($create_cats=array()) {
		$iparams_extra = new JRegistry;
		$user		= JFactory::getUser();
		$session = JFactory::getSession();
		$asset	= 'com_content.article.'.$this->_id;
		
		$isOwner = !empty($this->_item->created_by) && $this->_item->created_by == $user->get('id');
		$hasTmpEdit = false;
		$hasCoupon  = false;
		if ($session->has('rendered_uneditable', 'flexicontent')) {
			$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
			$hasTmpEdit = !empty($this->_id) && !empty($rendered_uneditable[$this->_id]);  // editable temporarily
			$hasCoupon  = !empty($this->_id) && !empty($rendered_uneditable[$this->_id]) && $rendered_uneditable[$this->_id] == 2;  // editable temporarily via coupon
		}
		
		// Compute CREATE access permissions.
		if ( !$this->_id ) {
			
			// Check if general create permission is missing, NOTE: THIS CAN BE SOFT DENY
			// ... so we do need to check category 'create' privilege for all categories !!
			/*if ( !$user->authorise('core.create', 'com_flexicontent') ) {
				$iparams_extra->set('access-create', false);
				return $iparams_extra;  // New item, so do not calculate EDIT, DELETE and VIEW access
			}*/
			
			$hasTypeCreate = FlexicontentHelperPerm::checkTypeAccess($this->_typeid, 'core.create'); // an empty type_id, results to true, maybe later check all types for create
			if (!$hasTypeCreate) {
				return $iparams_extra;  // no create items access in type, return
			}
			
			// Check that user can create item in at least one category ...
			$canCreateAny = FlexicontentHelperPerm::getPermAny('core.create');
			$iparams_extra->set('access-create', $canCreateAny);
			return $iparams_extra;  // New item, so do not calculate EDIT, DELETE and VIEW access
		}
		
		// Not a new item retrieve item if not already done
		if ( empty($this->_item) ) {
			$this->_item = $this->getItem();
		}

		// Compute EDIT access permissions.
		if ( $this->_id ) {
			if ($hasTmpEdit)
			{
				$iparams_extra->set('access-edit', true);
			} else {
				
				// get "edit items" permission on the type of the item
				$hasTypeEdit = FlexicontentHelperPerm::checkTypeAccess($this->_typeid, 'core.edit');
				$hasTypeEditOwn = FlexicontentHelperPerm::checkTypeAccess($this->_typeid, 'core.edit.own');
				
				// first check edit permission on the item
				if ($hasTypeEdit && $user->authorise('core.edit', $asset)) {
					$iparams_extra->set('access-edit', true);
				}
				// no edit permission, check if edit.own is available for this item
				else if ($hasTypeEditOwn && $user->authorise('core.edit.own', $asset) && $isOwner)
				{
					$iparams_extra->set('access-edit', true);
				}
			}
		}

		// Compute EDIT STATE access permissions.
		if ( $this->_id ) {
			// get "edit state of items" permission on the type of the item
			$hasTypeEditState = FlexicontentHelperPerm::checkTypeAccess($this->_typeid, 'core.edit.state');
			$hasTypeEditStateOwn = FlexicontentHelperPerm::checkTypeAccess($this->_typeid, 'core.edit.state.own');
			
			// first check edit.state permission on the item
			if ($hasTypeEditState && $user->authorise('core.edit.state', $asset)) {
				$iparams_extra->set('access-edit-state', true);
			}
			// no edit.state permission, check if edit.state.own is available for this item
			else if ($hasTypeEditStateOwn && $user->authorise('core.edit.state.own', $asset) && ($isOwner || $hasCoupon)) // hasCoupon acts as item owner
			{
				$iparams_extra->set('access-edit-state', true);
			}
		}
		
		// Compute DELETE access permissions.
		if ( $this->_id )
		{
			$hasTypeDelete = FlexicontentHelperPerm::checkTypeAccess($this->_typeid, 'core.delete');
			$hasTypeDeleteOwn = FlexicontentHelperPerm::checkTypeAccess($this->_typeid, 'core.delete.own');
			
			// first check delete permission on the item
			if ($hasTypeDelete && $user->authorise('core.delete', $asset)) {
				$iparams_extra->set('access-delete', true);
			}
			// no delete permission, chekc delete.own permission if the item is owned by the user
			else if ($hasTypeDeleteOwn && $user->authorise('core.delete.own', $asset) && ($isOwner || $hasCoupon)) // hasCoupon acts as item owner
			{
				$iparams_extra->set('access-delete', true);
			}
		}
		
		// Compute VIEW access permissions.
		if ($access = $this->getState('filter.access')) {
			// The access filter has been set,
			// we already know current user can view this item or we should not check access
			$iparams_extra->set('access-view', true);
		}
		else {
			// The access filter has not been set, we will set access flag(s) if not set already
			// the layout takes some responsibility for display of limited information,
			$groups = JAccess::getAuthorisedViewLevels($user->id);
			
			if ( !isset($this->_item->has_item_access) ) {
				$this->_item->has_item_access = in_array($this->_item->access, $groups);
			}
			if ( !isset($this->_item->has_mcat_access) ) {
				$no_mcat_info = $this->_item->catid == 0 || !isset($this->_item->category_access) || $this->_item->category_access === null;
				$this->_item->has_mcat_access = $no_mcat_info || in_array($this->_item->category_access, $groups);
			}
			if ( !isset($this->_item->has_type_access) ) {
				$no_type_info = $this->_typeid == 0 || !isset($this->_item->type_access) || $this->_item->type_access === null;
				$this->_item->has_type_access = $no_type_info || in_array($this->_item->type_access, $groups);
			}
			$iparams_extra->set('access-view', $this->_item->has_item_access && $this->_item->has_mcat_access && $this->_item->has_type_access);
		}

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
		if ( !empty($data['catid']) && !in_array($data['catid'], $cats) ) {
			$cats[] =  $data['catid'];
		}
		
		$allow = null;
		if (count($cats)) {
			$allow = true;
			foreach ($cats as $curcatid) {
				// If the category has been passed in the data or URL check it.
				$cat_allowed = $user->authorise('core.create', 'com_content.category.'.$curcatid);
				if (!$cat_allowed) {
					return JError::raiseWarning( 500, "No access to add item to category with id ".$curcatid );
				}
				$allow &= $cat_allowed;
			}
		}
		
		if ($allow === null) {
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
	 * Method to check if the user can edit the STATE of the item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function canEditState($item=null, $check_cat_perm=true)
	{
		if ( empty($item) ) $item = $this->_item;
		$user = JFactory::getUser();
		$session = JFactory::getSession();
		
		$isOwner = !empty($item->created_by) && ( $item->created_by == $user->get('id') );
		$hasCoupon = false;
		if ($session->has('rendered_uneditable', 'flexicontent')) {
			$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
			$hasCoupon = !empty($item->id) && !empty($rendered_uneditable[$item->id]) && $rendered_uneditable[$item->id] == 2;  // editable temporarily via coupon
		}
		
		// get "edit items state" permission on the type of the item
		$hasTypeEditState    = !$item->type_id ? true : FlexicontentHelperPerm::checkTypeAccess($item->type_id, 'core.edit.state');
		$hasTypeEditStateOwn = !$item->type_id ? true : FlexicontentHelperPerm::checkTypeAccess($item->type_id, 'core.edit.state.own');
		if (!$hasTypeEditState && !$hasTypeEditStateOwn) return false;
		
		if ( !empty($item->id) )
		{
			// Existing item, use item specific permissions
			$asset = 'com_content.article.' . $item->id;
			$allowed =
				($hasTypeEditState && $user->authorise('core.edit.state', $asset)) ||
				($hasTypeEditStateOwn && $user->authorise('core.edit.state.own', $asset) && ($isOwner || $hasCoupon));
		}
		elseif ( $check_cat_perm && !empty($item->catid) )
		{
			// *** New item *** with main category set
			$cat_asset = 'com_content.category.' . (int)@ $item->catid;
			$allowed =
				($hasTypeEditState && $user->authorise('core.edit.state', $cat_asset)) ||
				($hasTypeEditStateOwn && $user->authorise('core.edit.state.own', $cat_asset) && $isOwner);
		}
		else
		{
			// *** New item *** get general edit/publish/delete permissions
			$allowed =
				($hasTypeEditState && $user->authorise('core.edit.state', 'com_flexicontent')) ||
				($hasTypeEditStateOwn && $user->authorise('core.edit.state.own', 'com_flexicontent'));
		}
		
		return $allowed;
	}
	
	
	/**
	 * Method to initialise the item data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _initItem()
	{
		// Lets load the item if it doesn't already exist
		if (empty($this->_item))
		{
			// Get some variables
			$app  = JFactory::getApplication();
			$user = JFactory::getUser();
			$createdate = JFactory::getDate();
			$nullDate   = $this->_db->getNullDate();
			$cparams    = $this->_cparams;
			
			// Load default empty item
			$item = JTable::getInstance('flexicontent_items', ''); 
			
			$public_accesslevel  = 1;
			$default_accesslevel = $app->getCfg( 'access', $public_accesslevel );
			
			// Decide default publication state. NOTE this will only be used if user has publish privilege, otherwise items
			// will be forced to (a) pending_approval state for NEW ITEMS and (b) to item's current state for EXISTING ITEMS
			$pubished_state = 1;  $draft_state = -4;  $pending_approval_state = -3;
			if ( $app->isAdmin() ) {
				$default_state = $cparams->get('new_item_state', $pubished_state);     // Use the configured setting for backend items
			} else {
				$default_state = $cparams->get('new_item_state_fe', $pubished_state);  // Use the configured setting for frontend items
			}
			
			// Decide default language
			$default_lang = '*';  //flexicontent_html::getSiteDefaultLang();
			$default_lang = $app->isSite() ? $cparams->get('default_language_fe', $default_lang) : $default_lang;
			if ($default_lang=='_author_lang_') $default_lang = $user->getParam('language', '*');
			
			// Override defaults values, we assigned all properties, 
			// despite many of them having the correct value already
			$item->id           = 0;
			$item->cid          = array();
			$item->categories   = array();
			$item->catid        = null;
			$item->title        = null;
			$item->alias        = null;
			$item->title_alias  = null;  // deprecated do not use
			$item->introtext    = null;
			$item->fulltext     = null;
			$item->author       = null;
			$item->text         = null;
			$item->sectionid    = FLEXI_SECTION;
			$item->type_id      = $this->_typeid;
			$item->typename     = null;
			$item->typealias    = null;
			$item->score        = 0;
			$item->votecount    = 0;
			$item->hits         = 0;
			$item->version      = 0;
			$item->current_version = 0;
			$item->last_version    = 0;
			$item->metadesc     = null;
			$item->metakey      = null;
			$item->created      = $createdate->toUnix();
			$item->created_by   = $user->get('id');
			$item->created_by_alias = null;
			$item->creator      = null;
			$item->modified     = $nullDate;
			$item->modified_by  = null;
			$item->modifier     = null;
			$item->publish_up   = $createdate->toUnix();
			$item->publish_down = null;
			$item->images       = null;
			$item->urls         = null;
			$item->attribs      = null;
			$item->metadata     = null;
			$item->access       = $default_accesslevel;
			$item->state        = $default_state;
			$item->mask         = null;  // deprecated do not use
			$item->language     = $default_lang;
			$item->lang_parent_id = 0;
			$item->search_index = null;
			$item->parameters   = $this->_cparams;  // initialized to component + type parameters
			
			$query = 'SELECT title FROM #__viewlevels WHERE id = '. (int) $item->access;
			$this->_db->setQuery($query);
			$item->access_level = $this->_db->loadResult();
			
			$this->_item				= $item;
		}
		return true;
	}
	
	
	/**
	 * Stock method to auto-populate the model state.
	 *
	 * @return	void
	 * @since	1.6
	 */
	protected function populateState() {
		$app = JFactory::getApplication();
		
		// Initialise variables.
		$this->setState($this->getName().'.id', $this->_id);

		// Set global parameters: component + type parameters, ? UNUSED ? maybe used by parent class
		$this->setState('params', $this->_cparams);
	}
	
	
	/**
	 * Tests if the item is checked out
	 *
	 * @access	public
	 * @param	int	A user id
	 * @return	boolean	True if checked out
	 * @since	1.0
	 */
	function isCheckedOut( $uid=0 )
	{
		if ($this->_item || $this->_loadItem()) {
			if ($uid) {
				return ($this->_item->checked_out && $this->_item->checked_out != $uid);
			} else {
				return $this->_item->checked_out;
			}
		} elseif ($this->_id < 1) {
			return false;
		} else {
			JError::raiseWarning( 0, 'Unable to Load Data');
			return false;
		}
	}
	
	
	/**
	 * Method to checkin/unlock the item
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
			$tbl = JTable::getInstance('flexicontent_items', '');
			return $tbl->checkin($pk);
		}
		return false;
	}
	
	
	/**
	 * Method to checkout/lock the item
	 *
	 * @access	public
	 * @param	int	$uid	User ID of the user checking the item out
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function checkout($pk = null)   // UPDATED to match function signature of J1.6+ models
	{
		// Make sure we have a record id to checkout the record with
		if ( !$pk ) $pk = $this->_id;
		if ( !$pk ) return true;
		
		// Get current user
		$user	= JFactory::getUser();
		$uid	= $user->get('id');
		
		// Lets get table record and checkout the it
		$tbl = JTable::getInstance('flexicontent_items', '');
		if ( $tbl->checkout($uid, $this->_id) ) return true;
		
		// Reaching this points means checkout failed
		$this->setError( $tbl->getError() /* JText::_("FLEXI_ALERT_CHECKOUT_FAILED")*/ );
		return false;
	}
	
	
	/**
	 * Method to store the item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function store($data)
	{
		// ****************************
		// Initialize various variables
		// ****************************
		
		$db   = $this->_db;
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		$dispatcher = JDispatcher::getInstance();
		$cparams    = $this->_cparams;
		$nullDate   = $this->_db->getNullDate();
		$view = JRequest::getVar('view', false);
		JRequest::setVar("isflexicontent", "yes");
		$use_versioning = $cparams->get('use_versioning', 1);
		$print_logging_info = $cparams->get('print_logging_info');
		
		if ( $print_logging_info ) {
			global $fc_run_times;
			$start_microtime = microtime(true);
		}
		
		// Dates displayed in the item form, are in user timezone for J2.5, and in site's default timezone for J1.5
		$site_zone = $app->getCfg('offset');
		$user_zone = $user->getParam('timezone', $site_zone);
		$tz_offset = $user_zone;
		
		// Sanitize id and approval flag as integers
		$data['vstate'] = (int)$data['vstate'];
		$data['id']     = (int)$data['id'];
		$isnew = ! $data['id'];
		
		
		// *****************************************
		// Get an item object and load existing item
		// *****************************************
		
		// Get an empty item model (with default values)
		$item = $this->getTable('flexicontent_items', '');
		$item->_isnew = $isnew;  // Pass information, if item is new to the fields
		
		// ... existing items Load item GET some data
		if ( !$isnew ) {
			// Load existing item into the empty item model
			$item->load( $data['id'] );
			
			// Retrieve property: 'tags', that do not exist in the DB TABLE class, but are created by the ITEM model
			$query = 'SELECT DISTINCT tid FROM #__flexicontent_tags_item_relations WHERE itemid = ' . $item->id;
			$db->setQuery($query);
			$item->tags = $db->loadColumn();
			$item->tags = array_reverse($item->tags);
			
			// Retrieve property: 'categories', that do not exist in the DB TABLE class, but are created by the ITEM model
			$query = 'SELECT DISTINCT catid FROM #__flexicontent_cats_item_relations WHERE itemid = ' . $item->id;
			$db->setQuery($query);
			$item->categories = $db->loadColumn();
			
			// We need to convert FC item state TO a joomla's article state ... when triggering the before save content event
			$fc_state = $item->state;
			if ( in_array($fc_state, array(1,-5)) ) $jm_state = 1;           // published states
			else if ( in_array($fc_state, array(0,-3,-4)) ) $jm_state = 0;   // unpublished states
			else $jm_state = $fc_state;                                      // trashed & archive states
			
			// Frontend SECURITY concern: ONLY allow to set item type for new items !!! ... or for items without type ?!
			if( !$app->isAdmin() && $item->type_id ) 
				unset($data['type_id']);
		} else {
			// New ITEM: since we create new item only via DB TABLE object,
			// create default values for SOME properties that do not exist in the DB TABLE class, but are created by the ITEM model
			$item->categories = array();
			$item->tags = array();
		}
		$old_item = clone($item);
		
		
		// *********************************
		// Check and correct given item DATA
		// *********************************
		
		// tags and cats will need some manipulation so we retieve them
		$tags = $this->formatToArray( @ $data['tag'] );
		$cats = $this->formatToArray( @ $data['cid'] );
		$featured_cats = $this->formatToArray( @ $data['featured_cid'] );
		unset($data['tag']);  unset($data['cid']);  unset($data['featured_cid']);
		
		// Make tags unique
		$tags = array_keys(array_flip($tags));
		
		// Auto-assign a not set main category, to be the first out of secondary categories, 
		if ( empty($data['catid']) && !empty($cats[0]) ) {
			$data['catid'] = $cats[0];
		}
		
		$cats_indexed = array_flip($cats);
		// Add the primary cat to the array if it's not already in
		if ( @ $data['catid'] && !isset($cats_indexed[$data['catid']]) ) {
			$cats_indexed[$data['catid']] = 1;
		}
		
		// Add the featured cats to the array if it's not already in
		if ( !empty($featured_cats) ) foreach ( $featured_cats as $featured_cat ) {
			if (@ $featured_cat && !isset($cats_indexed[$featured_cat]) )  $cats_indexed[$featured_cat] = 1;
		}
		
		// Reassign (unique) categories back to the cats array
		$cats = array_keys($cats_indexed);
		
		
		// *****************************
		// Retrieve author configuration
		// *****************************
		$authorparams = flexicontent_db::getUserConfig($user->id);
		
		// At least one category needs to be assigned
		if (!is_array( $cats ) || count( $cats ) < 1) {
			
			$this->setError(JText::_('FLEXI_OPERATION_FAILED') .", ". JText::_('FLEXI_REASON') .": ". JText::_('FLEXI_SELECT_CATEGORY'));
			return false;
			
		// Check more than allowed categories
		} else {
			
			// Get author's maximum allowed categories per item and set js limitation
			$max_cat_assign = intval($authorparams->get('max_cat_assign',0));
			
			// Verify category limitation for current author
			if ( $max_cat_assign ) {
				if ( count($cats) > $max_cat_assign ) {
					if ( count($cats) <= count($item->categories) ) {
						$existing_only = true;
						// Maximum number of categories is exceeded, but do not abort if only using existing categories
						foreach ($cats as $newcat) {
							$existing_only = $existing_only && in_array($newcat, $item->categories);
						}
					} else {
						$existing_only = false;
					}
					if (!$existing_only) {
						$this->setError(JText::_('FLEXI_OPERATION_FAILED') .", ". JText::_('FLEXI_REASON') .": ". JText::_('FLEXI_TOO_MANY_ITEM_CATEGORIES').$max_cat_assign);
						return false;
					}
				}
			}
		}
		
		// Trim title, but allow not setting it ... to maintain current value (but we will also need to override 'required' during validation)
		if (isset($data['title']))
			$data['title'] = trim($data['title']);
		
		// Set back the altered categories and tags to the form data
		$data['categories']  = $cats;  // Set it to real name of field: 'categories' INSTEAD OF 'cid'
		$data['tags']        = $tags;  // Set it to real name of field: 'tags'       INSTEAD OF 'tag'
		
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
		
		
		// ***************************************************************************************
		// Handle Parameters: attribs & metadata, merging POST values into existing values,
		// IF these were not set at all then there will be no need to merge,
		// BUT part of them may have been displayed, so we use mergeAttributes() instead of bind()
		// Keys that are not set will not be set, thus the previous value is maintained
		// ***************************************************************************************
		
		// Retrieve (a) item parameters (array PARAMS or ATTRIBS ) and (b) item metadata (array METADATA or META )
		$params   = $this->formatToArray( @ $data['attribs'] );
		$metadata = $this->formatToArray( @ $data['metadata'] );
		unset($data['attribs']);
		unset($data['metadata']);
		
		// Merge (form posted) item attributes and metadata parameters INTO EXISTING DATA (see above for explanation)
		$this->mergeAttributes($item, $params, $metadata);
		
		
		// *******************************************************
		// Retrieve submit configuration for new items in frontend
		// *******************************************************
		if ( $app->isSite() && $isnew && !empty($data['submit_conf']) ) {
			$h = $data['submit_conf'];
			$session = JFactory::getSession();
			$item_submit_conf = $session->get('item_submit_conf', array(),'flexicontent');
			$submit_conf = @ $item_submit_conf[$h] ;
			
			$autopublished    = @ $submit_conf['autopublished'];     // Override flag for both TYPE and CATEGORY ACL
			$overridecatperms = @ $submit_conf['overridecatperms'];  // Override flag for CATEGORY ACL
			if ( $autopublished) {
				// Dates forced during autopublishing
				if ( @ $submit_conf['autopublished_up_interval'] ) {
					$publish_up_date = JFactory::getDate(); // Gives editor's timezone by default
					$publish_up_date->modify('+ '.$submit_conf['autopublished_up_interval'].' minutes');
					$publish_up_forced = $publish_up_date->toSql();
				}
				if ( @ $submit_conf['autopublished_down_interval'] ) {
					$publish_down_date = JFactory::getDate(); // Gives editor's timezone by default
					$publish_down_date->modify('+ '.$submit_conf['autopublished_down_interval'].' minutes');
					$publish_down_forced = $publish_down_date->toSql();
				}
			}
		} else {
			$autopublished    = 0;
			$overridecatperms = 0;
		}
		
		
		// ***********************************************************
		// SECURITY concern: Check form tampering of categories, of:
		// (a) menu overridden categories for frontent item submit
		// (b) or check user has 'create' privilege in item categories
		// ***********************************************************
		$allowed_cid = $overridecatperms ?
			@ $submit_conf['cids'] :
			FlexicontentHelperPerm::getAllowedCats($user, $actions_allowed = array('core.create'), $require_all=true) ;
		if ($overridecatperms && @ $submit_conf['maincatid']) $allowed_cid[] = $submit_conf['maincatid'];  // add the "default" main category to allowed categories
		
		// Force main category if main category selector (maincatid_show:1) was disabled (and default main category was configured)
		if ($overridecatperms && @ $submit_conf['maincatid_show'] == 1 && @ $submit_conf['maincatid']) $data['catid'] = $submit_conf['maincatid'];
		
		if ( !empty($allowed_cid) ) {
			// Add existing item's categories into the user allowed categories
			$allowed_cid = array_merge($allowed_cid, $item->categories);
			
			// Check main category tampering
			if ( !in_array($data['catid'], $allowed_cid) && $data['catid'] != $item->catid ) {
				$this->setError( 'main category is not in allowed list (form tampered ?)' );
				return false;
			}
			
			// Check multi category tampering
			$postcats = @ $submit_conf['postcats'];
			if ( !$isnew || !$overridecatperms || $postcats==2 )
				$data['categories'] = array_intersect ($data['categories'], $allowed_cid );
			else if ( $postcats==0 )
				$data['categories'] = $allowed_cid;
			else if ( $postcats==1 )
				$data['categories'] = array($data['catid']);
			// Make sure values are unique
			$data['categories'] = array_keys(array_flip($data['categories']));
		}
		
		
		// *****************************************************************
		// SECURITY concern: Check form tampering of state related variables
		// *****************************************************************
		
		// Save old main category & creator (owner)
		$old_created_by = $item->created_by;
		$old_catid      = $item->catid;
		
		// New or Existing item must use the current user + new main category to calculate 'Edit State' privelege
		$item->created_by = $user->get('id');
		$item->catid      = $data['catid'];
		$item->type_id    = isset($data['type_id']) ? $data['type_id'] : $item->type_id;
		$canEditState = $this->canEditState( $item, $check_cat_perm=true );
		
		// Restore old main category & creator (owner) (in case following code chooses to keep them)
		$item->created_by = $old_created_by;
		$item->catid      = $old_catid;
		
		// If cannot edit state prevent user from changing state related parameters
		if ( !$canEditState )
		{
			$AutoApproveChanges = $user->authorise('flexicontent.autoapprovechanges',	'com_flexicontent');
			$data['vstate'] = $AutoApproveChanges ? 2 : 1;
			unset( $data['featured'] );
			unset( $data['publish_up'] );
			unset( $data['publish_down'] );
			unset( $data['ordering'] );
			
			// Check for publish up/down dates forced during auto-publishing
			if ( @ $publish_up_forced )   $data['publish_up']   = $publish_up_forced;
			if ( @ $publish_down_forced ) $data['publish_down'] = $publish_down_forced;
			
			$pubished_state = 1;  $draft_state = -4;  $pending_approval_state = -3;
			
			if (!$isnew) {
				// Prevent changing state of existing items by users that cannot publish
				$catid_changed = $old_catid != $data['catid'];
				if ($catid_changed && !$use_versioning) {
					$data['state'] = $pending_approval_state;
					$app->enqueueMessage('You have changed category for this content item to be a category in which you cannot publish, you content item is now in "Pending Approval" State, you will have to wait for it to be re-approved', 'warning');
				} else {
					$data['state'] = $item->state;
				}
			}
			else if ($autopublished) {
				// Autopublishing new item via menu configuration
				$data['state'] = $pubished_state;
			}
			else {
				// The preselected forced state of -NEW- items for users that CANNOT publish, and autopublish via menu item is disabled
				if ( $app->isAdmin() ) {
					$data['state'] = $cparams->get('non_publishers_item_state', $draft_state);     // Use the configured setting for backend items
				} else {
					$data['state'] = $cparams->get('non_publishers_item_state_fe', $pending_approval_state);  // Use the configured setting for frontend items
				}
			}
			
		}
		$isSuperAdmin = $user->authorise('core.admin', 'root.1');
		
		// Prevent frontend user from changing the item owner and creation date unless they are super admin
		if ( $app->isSite() && !$isSuperAdmin )
		{
			if ($isnew)  $data['created_by'] = $user->get('id');
			else         unset( $data['created_by'] );
			if ( !$user->authorise('flexicontent.editcreationdate', 'com_flexicontent') )
				unset( $data['created'] );
			unset( $data['created_by_alias'] );
		}
		
		
		// ***********************************************************
		// SECURITY concern: Check form tampering of allowed languages
		// ***********************************************************
		$allowed_langs = $authorparams->get('langs_allowed',null);
		$allowed_langs = !$allowed_langs ? null : FLEXIUtilities::paramToArray($allowed_langs);
		if (!$isnew && $allowed_langs) $allowed_langs[] = $item->language;
		if ( $allowed_langs && isset($data['language']) && !in_array($data['language'], $allowed_langs) ) {
			$app->enqueueMessage('You are not allowed to assign language: '.$data['language'].' to Content Items', 'warning');
			unset($data['language']);
			if ($isnew) return false;
		}
		
		if ( $app->isSite() && !in_array($cparams->get('uselang_fe', 1), array(1,3)) && isset($data['language']) ) {
			$app->enqueueMessage('You are not allowed to set language to this content items', 'warning');
			unset($data['language']);
			if ($isnew) return false;
		}
		
		
		// *************************************************************************
		// Bind given (possibly modifed) item DATA and PARAMETERS to the item object
		// *************************************************************************
		
		if ( !$item->bind($data) ) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		
				
		// *************************************************************************************
		// Check and correct CORE item properties (some such work was done above before binding)
		// *************************************************************************************
			
		// -- Modification Date and Modifier, (a) new item gets null modification date and (b) existing item get the current date
		if ($isnew) {
			$item->modified    = $nullDate;
			$item->modified_by = 0;
		} else {
			$datenow = JFactory::getDate();
			$item->modified    = $datenow->toSql();
			$item->modified_by = $user->get('id');
		}
			
		// -- Creator, if this is not already set, will be the current user or administrator if current user is not logged
		if ( !$item->created_by ) {
			$item->created_by = $user->get('id') ? $user->get('id') : JFactory::getUser( 'admin' )->get('id');
		}
		
		// -- Creation Date
		if ($item->created && StringHelper::strlen(StringHelper::trim( $item->created )) <= 10) {
			$item->created 	.= ' 00:00:00';
		}
		$date = JFactory::getDate($item->created);
		$date->setTimeZone( new DateTimeZone( $tz_offset ) );    // J2.5: Date from form field is in user's timezone
		$item->created = $date->toSql();
			
		// -- Publish UP Date
		if ($item->publish_up && StringHelper::strlen(StringHelper::trim( $item->publish_up )) <= 10) {
			$item->publish_up 	.= ' 00:00:00';
		}
		$date = JFactory::getDate($item->publish_up);
		$date->setTimeZone( new DateTimeZone( $tz_offset ) );       // J2.5: Date from form field is in user's timezone
		$item->publish_up = $date->toSql();

		// -- Publish Down Date
		if (trim($item->publish_down) == JText::_('FLEXI_NEVER') || trim( $item->publish_down ) == '')
		{
			$item->publish_down = $nullDate;
		}
		else if ($item->publish_down != $nullDate)
		{
			if ( StringHelper::strlen(StringHelper::trim( $item->publish_down )) <= 10 ) {
				$item->publish_down .= ' 00:00:00';
			}
			$date = JFactory::getDate($item->publish_down);
			$date->setTimeZone( new DateTimeZone( $tz_offset ) );         // J2.5: Date from form field is in user's timezone
			$item->publish_down = $date->toSql();
		}
		
		// auto assign the section
		if (!FLEXI_J16GE)  $item->sectionid = FLEXI_SECTION;
		
		// For new items get next available ordering number
		if ($isnew) {
			if ( empty($item->ordering) || !$canEditState ) $item->ordering = $item->getNextOrder();
		}
		
		// Auto assign the default language if not set, (security of allowing language usage and of language in user's allowed languages was checked above)
		$item->language   = $item->language ? $item->language :
			($app->isSite() ? $cparams->get('default_language_fe', '*') : ('*' /*flexicontent_html::getSiteDefaultLang()*/));
		
		// Ignore language parent id, we are now using J3.x associations
		$item->lang_parent_id = 0;
		
		
		// ****************************************************************************************************************
		// Get version info, force version approval ON is versioning disabled, and decide new item's current version number
		// ****************************************************************************************************************
		$current_version = (int) FLEXIUtilities::getCurrentVersions($item->id, true);
		$last_version    = (int) FLEXIUtilities::getLastVersions($item->id, true);
		
		// (a) Force item approval when versioning disabled
		$data['vstate'] = ( !$use_versioning ) ? 2 : $data['vstate'];
		
		// (b) Force item approval when item is not yet visible (is in states (a) Draft or (b) Pending Approval)
		$data['vstate'] = ( $item->state==-3 || $item->state==-4 ) ? 2 : $data['vstate'];
		
		// Decide new current version for the item, this depends if versioning is ON and if versioned is approved
		if ( !$use_versioning ) {
			// not using versioning, increment current version numbering
			$item->version = $isnew ? 1 : $current_version+1;
		} else {
			// using versioning, increment last version numbering, or keep current version number if new version was not approved
			$item->version = $isnew ? 1 : ( $data['vstate']==2 ? $last_version+1 : $current_version);
		}
		// *** Item version should be zero when form was loaded with no type id,
		// *** thus next item form load will load default values of custom fields
		$item->version = ($isnew && !empty($data['type_id_not_set']) ) ? 0 : $item->version;
		
		if ( $print_logging_info ) @$fc_run_times['item_store_prepare'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		
		// *********************************************************************************************
		// Make sure we import flexicontent AND content plugins since we will be triggering their events
		// *********************************************************************************************
		JPluginHelper::importPlugin('flexicontent');
		JPluginHelper::importPlugin('content');
		
		
		// **************************************************************************************************
		// Trigger Event 'onBeforeSaveItem' of FLEXIcontent plugins (such plugin is the 'flexinotify' plugin)
		// **************************************************************************************************
		if ( $print_logging_info ) $start_microtime = microtime(true);
		$result = $dispatcher->trigger('onBeforeSaveItem', array(&$item, $isnew));
		if((count($result)>0) && in_array(false, $result, true)) return false;   // cancel item save
		if ( $print_logging_info ) $fc_run_times['onBeforeSaveItem_event'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		
		// ******************************************************************************************************
		// Trigger Event 'OnBeforeContentSave' (J1.5) or 'onContentBeforeSave' (J2.5) of Joomla's Content plugins
		// ******************************************************************************************************
		
		// Some compatibility steps
		if (!$isnew) { $db->setQuery( 'UPDATE #__content SET state = '. $jm_state .' WHERE id = '.$item->id );  $db->execute(); }
	  JRequest::setVar('view', 'article');	  JRequest::setVar('option', 'com_content');
		
		if ( $print_logging_info ) $start_microtime = microtime(true);
		$result = $dispatcher->trigger($this->event_before_save, array('com_content.article', &$item, $isnew));
		if ( $print_logging_info ) $fc_run_times['onContentBeforeSave_event'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		// Reverse compatibility steps
		if (!$isnew) { $db->setQuery( 'UPDATE #__content SET state = '. $fc_state .' WHERE id = '.$item->id );  $db->execute(); }
		JRequest::setVar('view', $view);	  JRequest::setVar('option', 'com_flexicontent');
		
		if (in_array(false, $result, true))	{ $this->setError($item->getError()); return false; }    // cancel item save
		
		
		
		// ************************************************************************************************************
		// IF new item, create it before saving the fields (and constructing the search_index out of searchable fields)
		// ************************************************************************************************************
		if ( $print_logging_info ) $start_microtime = microtime(true);
		if( $isnew )
		{
			// Only create the item not save the CUSTOM fields yet, no need to rebind this is already done above
			$this->applyCurrentVersion($item, $data, $createonly=true);
			if ($cparams->get('auto_title', 0))  // AUTOMATIC TITLE, set to item ID
			{
				$item->title = $item->id;
				$this->_db->setQuery('UPDATE #__content SET title=id, alias=id WHERE id=' . (int)$item->id);
				$this->_db->execute();
			}
		} else {
			// ??? Make sure the data of the model are correct  ??? ... maybe this no longer needed
			// e.g. a getForm() used to validate input data may have set an empty item and empty id
			// e.g. type_id of item may have been altered by authorized users
			$this->_id     = $item->id;
			$this->_item   = & $item;
			$this->_typeid = $item->type_id;
		}
		if ( $print_logging_info ) $fc_run_times['item_store_core'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		
		// ****************************************************************************
		// Save fields values to appropriate tables (versioning table or normal tables)
		// NOTE: This allow canceling of item save operation, if 'abort' is returned
		// ****************************************************************************
		
		// Do not try to load fields / save field values, if applying type
		$result = true;
		$task = JRequest::getCmd( 'task' );
		if ($task != 'apply_type')
		{
			if ( $print_logging_info ) $start_microtime = microtime(true);
			$files = JRequest::get( 'files', JREQUEST_ALLOWRAW );
			$core_data_via_events = null;
			$result = $this->saveFields($isnew, $item, $data, $files, $old_item, $core_data_via_events);
			if ( $print_logging_info ) $fc_run_times['item_store_custom'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			
			// Re-bind (possibly modified data) to the item
			$this->splitText($core_data_via_events); // split text to introtext, fulltext
			if ( !$item->bind($core_data_via_events) ) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
		}
		$version_approved = $isnew || $data['vstate']==2;
		if( $result==='abort' ) {
			if ($isnew) {
				$db->setQuery('DELETE FROM #__assets WHERE id = (SELECT asset_id FROM #__content WHERE id='.$item->id.')');
				$db->execute();
				$db->setQuery('DELETE FROM #__content WHERE id ='.$item->id);
				$db->execute();
				$db->setQuery('DELETE FROM #__flexicontent_items_ext WHERE item_id='.$item->id);
				$db->execute();
				$db->setQuery('DELETE FROM #__flexicontent_items_tmp WHERE id='.$item->id);
				$db->execute();
				
				$this->setId(0);
				$this->setError( $this->getError().' '.JText::_('FLEXI_NEW_ITEM_NOT_CREATED') );
			} else {
				$this->setError( $this->getError().' '.JText::_('FLEXI_EXISTING_ITEM_NOT_SAVED') );
			}
			
			// Return false this will indicate to the controller to abort saving
			// and set POSTED data into session so that form reloads them properly
			return false;
		}
		
		
		// ***************************************************************
		// ITEM DATA SAVED:  EITHER new, OR approving current item version
		// ***************************************************************
		if ( $version_approved )
		{
			// *****************************************************************************************************************************
			// Save -both- item CORE data AND custom fields, rebinding the CORE ITEM DATA since the onBeforeSaveField may have modified them
			// *****************************************************************************************************************************
			if ( $print_logging_info ) $start_microtime = microtime(true);
			if( !$this->applyCurrentVersion($item, $data, $createonly=false) ) return false;
			if ( $print_logging_info ) @$fc_run_times['item_store_core'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			//echo "<pre>"; var_dump($data); exit();
			
			
			// ***************************
			// Update Joomla Featured FLAG
			// ***************************
			$this->featured(array($item->id), $item->featured);
			
			
			// *****************************************************************************************************
			// Trigger Event 'onAfterContentSave' (J1.5) OR 'onContentAfterSave' (J2.5 ) of Joomla's Content plugins
			// *****************************************************************************************************
			if ( $print_logging_info ) $start_microtime = microtime(true);
			
			// Some compatibility steps
		  JRequest::setVar('view', 'article');
			JRequest::setVar('option', 'com_content');
		  
			$dispatcher->trigger($this->event_after_save, array('com_content.article', &$item, $isnew));
			
			// Reverse compatibility steps
			JRequest::setVar('view', $view);
			JRequest::setVar('option', 'com_flexicontent');
			
			if ( $print_logging_info ) @$fc_run_times['onContentAfterSave_event'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		}
		
		
		// *************************************************************************************************
		// Trigger Event 'onAfterSaveItem' of FLEXIcontent plugins (such plugin is the 'flexinotify' plugin)
		// *************************************************************************************************
		if ( $print_logging_info ) $start_microtime = microtime(true);
		$results = $dispatcher->trigger('onAfterSaveItem', array( &$item, &$data ));
		if ( $print_logging_info ) @$fc_run_times['onAfterSaveItem_event'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		
		// *********************************************************************
		// ITEM DATA NOT SAVED:  NEITHER new, NOR approving current item version
		// *********************************************************************
		if ( !$version_approved ) {
			if ( $app->isAdmin() || $cparams->get('approval_warning_aftersubmit_fe', 1) )
			{
				// Warn editor that his/her changes will need approval to before becoming active / visible
				if ( $canEditState )
					JError::raiseNotice(11, JText::_('FLEXI_SAVED_VERSION_WAS_NOT_APPROVED_NOTICE') );
				else
					JError::raiseNotice(10, JText::_('FLEXI_SAVED_VERSION_MUST_BE_APPROVED_NOTICE') );
			}
			// Set modifier and modification time (as if item has been saved), so that we can use this information for updating the versioning tables
			$datenow = JFactory::getDate();
			$item->modified			= $datenow->toSql();
			$item->modified_by	= $user->get('id');
		}
		
		
		// *********************************************
		// Create and store version METADATA information 
		// *********************************************
		if ( $print_logging_info ) $start_microtime = microtime(true);
		if ($use_versioning) {
			$v = new stdClass();
			$v->item_id			= (int)$item->id;
			$v->version_id	= ($isnew && !empty($data['type_id_not_set']) ) ? 0 : (int)$last_version+1;
			$v->created			= $item->created;
			$v->created_by	= $item->created_by;
			if ($item->modified != $nullDate) {
				// NOTE: We set modifier as creator of the version, and modication date as creation date of the version
				$v->created		 = $item->modified;
				$v->created_by = $item->modified_by;
			}
			$v->comment		= isset($data['versioncomment']) ? htmlspecialchars($data['versioncomment'], ENT_QUOTES) : '';
			$this->_db->insertObject('#__flexicontent_versions', $v);
		}
		
		
		// *************************************************************
		// Delete old versions that are above the limit of kept versions
		// *************************************************************
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
		
		
		// ****************************************************************************************************
		// Trigger Event 'onCompleteSaveItem' of FLEXIcontent plugins (such plugin is the 'flexinotify' plugin)
		// ****************************************************************************************************
		if ( $print_logging_info ) $start_microtime = microtime(true);
		$results = $dispatcher->trigger('onCompleteSaveItem', array( &$item, &$fields ));
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
	function saveFields($isnew, &$item, &$data, &$files, &$old_item=null, &$core_data_via_events=null)
	{
		if (!$old_item) $old_item = & $item;
		
		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();
		$dispatcher = JDispatcher::getInstance();
		$cparams    = $this->_cparams;
		$use_versioning = $cparams->get('use_versioning', 1);
		$print_logging_info = $cparams->get('print_logging_info');
		$last_version = (int) FLEXIUtilities::getLastVersions($item->id, true);
		$mval_query = true;
		
		if ( $print_logging_info ) global $fc_run_times;
		if ( $print_logging_info ) $start_microtime = microtime(true);
		
		
		// ********************************
		// Checks for untranslatable fields
		// ********************************
		
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
		
		
		// ***************************************************************************************************************************
		// Get item's fields ... and their values (for untranslatable fields the field values from original content item are retrieved
		// ***************************************************************************************************************************		
		$original_content_id = 0;
		if ($get_untraslatable_values)
		{
			foreach($langAssocs as $content_id => $_assoc) {
				if ($site_default == substr($_assoc->language, 0,2)) {
					$original_content_id = $content_id;
					break;
				}
			}
		}
		//JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): '.$original_content_id.' '.print_r($assoc_item_ids, true),'message');
		$fields = $this->getExtrafields($force=true, $original_content_id, $old_item);
		$item->fields = & $fields;
		$item->calculated_fieldvalues = array();
		
		
		// ******************************************************************************************************************
		// Loop through Fields triggering onBeforeSaveField Event handlers, this was seperated from the rest of the process
		// to give chance to ALL fields to check their DATA and cancel item saving process before saving any new field values
		// ******************************************************************************************************************
		$searchindex = array();
		//$qindex = array();
		$core_data_via_events = array();  // Extra validation for some core fields via onBeforeSaveField
		$postdata = array();
		if ($fields)
		{
			$core_via_post = array('title'=>1, 'text'=>1);
			foreach($fields as $field)
			{
				// Set vstate property into the field object to allow this to be changed be the before saving  field event handler
				$field->item_vstate = $data['vstate'];
				
				$is_editable = !$field->valueseditable || $user->authorise('flexicontent.editfieldvalues', 'com_flexicontent.field.' . $field->id);
				$maintain_dbval = false;
				
				// FORM HIDDEN FIELDS (FRONTEND/BACKEND) AND (ACL) UNEDITABLE FIELDS: maintain their DB value ...
				if (
					( $app->isSite() && ($field->formhidden==1 || $field->formhidden==3 || $field->parameters->get('frontend_hidden')) ) ||
					( $app->isAdmin() && ($field->formhidden==2 || $field->formhidden==3 || $field->parameters->get('backend_hidden')) ) ||
					!$is_editable
				) {
					$postdata[$field->name] = $field->value;
					$maintain_dbval = true;
					
				// UNTRANSLATABLE (CUSTOM) FIELDS: maintain their DB value ...
				/*} else if ( $get_untraslatable_values && $field->untranslatable ) {
					$postdata[$field->name] = $field->value;
					$maintain_dbval = true;*/
					
				} else if ($field->iscore) {
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

					// (not posted) CORE FIELDS: get current value
					} else {
						// Get value from the updated item instead of old data
						$postdata[$field->name] = $this->getCoreFieldValue($field, 0);
					}
				// OTHER CUSTOM FIELDS (not hidden and not untranslatable)
				} else {
					$postdata[$field->name] = @$data['custom'][$field->name];
				}
				
				// Unserialize values already serialized values, e.g. (a) if current values used are from DB or (b) are being imported from CSV file
				if ( !is_array($postdata[$field->name]) ) {
					$postdata[$field->name] = strlen($postdata[$field->name]) ? array($postdata[$field->name]) : array();
				}
				foreach ($postdata[$field->name] as $i => $postdata_val) {
					if ( @unserialize($postdata_val)!== false || $postdata_val === 'b:0;' ) {
						$postdata[$field->name][$i] = unserialize($postdata_val);
					}
				}
				
				// Trigger plugin Event 'onBeforeSaveField'
				if (!$field->iscore || isset($core_via_post[$field->name]))
				{
					$field_type = $field->iscore ? 'core' : $field->field_type;
					$file_data  = isset($files[$field->name]) ? $files[$field->name] : null;  // Pass a copy field's FILE data
					$result = FLEXIUtilities::call_FC_Field_Func($field_type, 'onBeforeSaveField', array( &$field, &$postdata[$field->name], &$file_data, &$item ));
					
					if ($result===false) {
						// Field requested to abort item saving
						$this->setError( JText::sprintf('FLEXI_FIELD_VALUE_IS_INVALID', $field->label) );
						return 'abort';
					}
					
					// For CORE field get the modified data, which will be used for storing in DB (these will be re-bind later)
					if ( isset($core_via_post[$field->name]) ) {
						$core_data_via_events[$field->name] = isset($postdata[$field->name][0]) ? $postdata[$field->name][0] : '';  // The validation may have skipped it !!
					}
					
				} else {
					// Currently other CORE fields, these are skipped we will not call onBeforeSaveField() on them, neither rebind them
				}
				
				//$qindex[$field->name] = NULL;
				//$file_data  = isset($files[$field->name]) ? $files[$field->name] : null;  // Pass a copy field's FILE data
				//$result = FLEXIUtilities::call_FC_Field_Func($field_type, 'onBeforeSaveField', array( &$field, &$postdata[$field->name], &$file_data, &$item, &$qindex[$field->name] ));
				//if ($result===false) { ... }
				
				// Get vstate property from the field object back to the data array ... in case it was modified, since some field may decide to prevent approval !
				$data['vstate'] = $field->item_vstate;
			}
			//echo "<pre>"; print_r($postdata); echo "</pre>"; exit;
			
			// Set values of other fields (e.g. this is used for "Properties as Fields" feature)
			foreach($item->calculated_fieldvalues as $fieldname => $fieldvalues)
			{
				if ( isset($fields[$fieldname]) ) $postdata[$fieldname] = $fieldvalues;
			}
			//echo "<pre>";  print_r($item->calculated_fieldvalues);  exit;
			unset($item->calculated_fieldvalues);
		}
		if ( $print_logging_info ) @$fc_run_times['fields_value_preparation'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		
		
		// **********************
		// Empty per field TABLES
		// **********************
		
		$filterables = FlexicontentFields::getSearchFields('id', $indexer='advanced', null, null, $_load_params=true, 0, $search_type='filter');
		$filterables = array_keys($filterables);
		$filterables = array_flip($filterables);
		
		$dbprefix = $app->getCfg('dbprefix');
		$dbname   = $app->getCfg('db');
		$tbl_prefix = $dbprefix.'flexicontent_advsearch_index_field_';
		$query = "SELECT TABLE_NAME
			FROM INFORMATION_SCHEMA.TABLES
			WHERE TABLE_SCHEMA = '".$dbname."' AND TABLE_NAME LIKE '".$tbl_prefix."%'
			";
		$this->_db->setQuery($query);
		$tbl_names = $this->_db->loadColumn();
		
		foreach($tbl_names as $tbl_name)
		{
			$_field_id = str_replace($tbl_prefix, '', $tbl_name);
			
			// Drop the table of no longer filterable field 
			if ( !isset($filterables[$_field_id]) )
				$this->_db->setQuery( 'DROP TABLE IF EXISTS '.$tbl_name );
			else {
				// Remove item's old advanced search index entries
				$query = "DELETE FROM ".$tbl_name." WHERE item_id=". $item->id;
				$this->_db->setQuery($query);
				$this->_db->execute();
			}
		}
		
		// VERIFY all search tables exist
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
		
		
		
		// ****************************************************************************************************************************
		// Loop through Fields triggering onIndexAdvSearch, onIndexSearch Event handlers, this was seperated from the before save field
		//  event, so that we will update search indexes only if the above has not canceled saving OR has not canceled version approval
		// ****************************************************************************************************************************
		if ( $print_logging_info ) $start_microtime = microtime(true);
		
		$ai_query_vals = array();
		$ai_query_vals_f = array();
		
		if ($fields)
		{
			foreach($fields as $field)
			{
				$field_type = $field->iscore ? 'core' : $field->field_type;
				
				if ( $data['vstate']==2 || $isnew)    // update (regardless of state!!) search indexes if document version is approved OR item is new
				{
					// Trigger plugin Event 'onIndexAdvSearch' to update field-item pair records in advanced search index
					FLEXIUtilities::call_FC_Field_Func($field_type, 'onIndexAdvSearch', array( &$field, &$postdata[$field->name], &$item ));
					if ( isset($field->ai_query_vals) ) {
						foreach ($field->ai_query_vals as $query_val) $ai_query_vals[] = $query_val;
						if ( isset($filterables[$field->id]) ) {  // Current for advanced index only
							foreach ($field->ai_query_vals as $query_val) $ai_query_vals_f[$field->id][] = $query_val;
						}
					}
					//echo $field->name .":".implode(",", @$field->ai_query_vals ? $field->ai_query_vals : array() )."<br/>";
					
					// Trigger plugin Event 'onIndexSearch' to update item 's (basic) search index record
					FLEXIUtilities::call_FC_Field_Func($field_type, 'onIndexSearch', array( &$field, &$postdata[$field->name], &$item ));
					if ( strlen(@$field->search[$item->id]) ) $searchindex[] = $field->search[$item->id];
					//echo $field->name .":".@$field->search[$item->id]."<br/>";
				}
			}
		}
		
		// Remove item's old advanced search index entries
		$query = "DELETE FROM #__flexicontent_advsearch_index WHERE item_id=". $item->id;
		$this->_db->setQuery($query);
		$this->_db->execute();
		
		// Store item's advanced search index entries
		$queries = array();
		if ( count($ai_query_vals) )  // check for zero search index records
		{
			$queries[] = "INSERT INTO #__flexicontent_advsearch_index "
				." (field_id,item_id,extraid,search_index,value_id) VALUES "
				.implode(",", $ai_query_vals);
			$this->_db->setQuery($query);
			$this->_db->execute();
			if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
		}
		foreach( $ai_query_vals_f as $_field_id => $_query_vals) {  // Current for advanced index only
			$queries[] = "INSERT INTO #__flexicontent_advsearch_index_field_".$_field_id
				." (field_id,item_id,extraid,search_index,value_id) VALUES "
				.implode(",", $_query_vals);
		}
		foreach( $queries as $query ) {
			$this->_db->setQuery($query);
			$this->_db->execute();
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
		
		
		if ( $print_logging_info ) $start_microtime = microtime(true);
		
		// **************************************************************************
		// IF new version is approved, remove old version values from the field table
		// **************************************************************************
		if($data['vstate']==2)
		{
			//echo "delete __flexicontent_fields_item_relations, item_id: " .$item->id;
			$query = 'DELETE FROM #__flexicontent_fields_item_relations WHERE item_id = '.$item->id;
			$this->_db->setQuery($query);
			$this->_db->execute();
			$query = 'DELETE FROM #__flexicontent_items_versions WHERE item_id='.$item->id.' AND version='.((int)$last_version+1);
			$this->_db->setQuery($query);
			$this->_db->execute();
			
			$untranslatable_fields = array();
			if ($fields) foreach($fields as $field)
			{
				if(	$field->iscore ) continue;
				if (count($assoc_item_ids) && $field->untranslatable)
				{
					// Delete field values in all translating items, if current field is untranslatable and current item version is approved
					// NOTE: item itself is not include in associated translations, no need to check for it and skip itit 
					if (! $mval_query) {
						$query = 'DELETE FROM #__flexicontent_fields_item_relations WHERE item_id IN ('.implode(',',$assoc_item_ids).') AND field_id='.$field->id;
						$this->_db->setQuery($query);
						$this->_db->execute();
					} else {
						$untranslatable_fields[] = $field->id;
					}
				}
			}
			if ( count($untranslatable_fields) ) {
				$query = 'DELETE FROM #__flexicontent_fields_item_relations WHERE item_id IN ('.implode(',',$assoc_item_ids).') AND field_id IN ('.implode(',',$untranslatable_fields) .')';
				$this->_db->setQuery($query);
				$this->_db->execute();
			}
		}
		
		
		// *******************************************
		// Loop through Fields saving the field values
		// *******************************************
		if ($fields)
		{
			// Do not save if versioning disabled or item has no type (version 0)
			$record_versioned_data = $use_versioning && $item->version;
			
			$ver_query_vals = array();
			$rel_query_vals = array();
			
			foreach($fields as $field)
			{
				// -- Add the new values to the database 
				$postvalues = $this->formatToArray( $postdata[$field->name] );
				//$qindex_values = $qindex[$field->name];
				$i = 1;
				
				foreach ($postvalues as $postvalue)
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
					if ( !empty($field->use_suborder) && is_array($postvalue) )
						$obj->value = null;
					else
						$obj->value = is_array($postvalue) ? serialize($postvalue) : $postvalue;
					//$obj->qindex01 = isset($qindex_values['qindex01']) ? $qindex_values['qindex01'] : NULL;
					//$obj->qindex02 = isset($qindex_values['qindex02']) ? $qindex_values['qindex02'] : NULL;
					//$obj->qindex03 = isset($qindex_values['qindex03']) ? $qindex_values['qindex03'] : NULL;
					
					// -- a. Add versioning values, but do not version the 'hits' or 'state' or 'voting' fields
					if ($record_versioned_data && $field->field_type!='hits' && $field->field_type!='state' && $field->field_type!='voting') {
						// Insert only if value non-empty
						if ( !empty($field->use_suborder) && is_array($postvalue) ) {
							$obj->suborder = 1;
							foreach ($postvalue as $v) {
								$obj->value = $v;
								if (! $mval_query) $this->_db->insertObject('#__flexicontent_items_versions', $obj);
								else $ver_query_vals[] = "("
									.$obj->field_id. "," .$obj->item_id. "," .$obj->valueorder. "," .$obj->suborder. "," .$obj->version. "," .$this->_db->Quote($obj->value)
								.")";
								$obj->suborder++;
							}
							unset($v);
						}
						else if ( isset($obj->value) && strlen(trim($obj->value)) )    // ISSET will also skip -null-, but valueorder will be incremented (e.g. we want this for fields in a field group)
						//else if ( isset($obj->value) && ($use_ingroup || strlen(trim($obj->value))) )
						{
							if (! $mval_query) $this->_db->insertObject('#__flexicontent_items_versions', $obj);
							else $ver_query_vals[] = "("
								.$obj->field_id. "," .$obj->item_id. "," .$obj->valueorder. "," .$obj->suborder. "," .$obj->version. "," .$this->_db->Quote($obj->value)
								//. "," .$this->_db->Quote($obj->qindex01) . "," .$this->_db->Quote($obj->qindex02) . "," .$this->_db->Quote($obj->qindex03)
							.")";
						}
					}
					//echo $field->field_type." - ".$field->name." - ".strlen(trim($obj->value))." ".$field->iscore."<br/>";
					
					// -- b. If item is new OR version is approved, AND field is not core (aka stored in the content table or in special table), then add field value to field values table
					if(	( $isnew || $data['vstate']==2 ) && !$field->iscore )
					{
						// UNSET version it it used only verion data table, and insert only if value non-empty
						unset($obj->version);
						if ( !empty($field->use_suborder) && is_array($postvalue) ) {
							$obj->suborder = 1;
							foreach ($postvalue as $v) {
								$obj->value = $v;
								if (! $mval_query) $this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
								else $rel_query_vals[] = "("
									.$obj->field_id. "," .$obj->item_id. "," .$obj->valueorder. "," .$obj->suborder. "," .$this->_db->Quote($obj->value)
								.")";
								$obj->suborder++;
							}
							unset($v);
						}
						else if ( isset($obj->value) && strlen(trim($obj->value)) )    // ISSET will also skip -null-, but valueorder will be incremented (e.g. we want this for fields in a field group)
						//else if ( isset($obj->value) && ($use_ingroup || strlen(trim($obj->value))) )
						{
							if (! $mval_query) $this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
							else $rel_query_vals[] = "("
								.$obj->field_id. "," .$obj->item_id. "," .$obj->valueorder. "," .$obj->suborder. "," .$this->_db->Quote($obj->value)
								//. "," .$this->_db->Quote($obj->qindex01) . "," .$this->_db->Quote($obj->qindex02) . "," .$this->_db->Quote($obj->qindex03)
							.")";
							
							// Save field value in all translating items, if current field is untranslatable
							// NOTE: item itself is not include in associated translations, no need to check for it and skip it
							if (count($assoc_item_ids) && $field->untranslatable) {
								foreach($assoc_item_ids as $t_item_id) {
									//echo "setting Untranslatable value for item_id: ".$t_item_id ." field_id: ".$field->id."<br/>";
									$obj->item_id = $t_item_id;
									if (! $mval_query) $this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
									else $rel_query_vals[] = "("
										.$obj->field_id. "," .$obj->item_id. "," .$obj->valueorder. "," .$obj->suborder. "," .$this->_db->Quote($obj->value)
										//. "," .$this->_db->Quote($obj->qindex01) . "," .$this->_db->Quote($obj->qindex02) . "," .$this->_db->Quote($obj->qindex03)
									.")";
								}
							}
						}
					}
					$i++;
				}
			}
			
			
			// *********************************************
			// Insert values in item fields versioning table
			// *********************************************
			
			if ( count($ver_query_vals) ) {
				$query = "INSERT INTO #__flexicontent_items_versions "
					." (field_id,item_id,valueorder,suborder,version,value"
					//.",qindex01,qindex02,qindex03"
					.") VALUES "
					."\n".implode(",\n", $ver_query_vals);
				$this->_db->setQuery($query);
				$this->_db->execute();
				if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
			}
			
			
			// *******************************************
			// Insert values in item fields relation table
			// *******************************************
			
			if ( count($rel_query_vals) ) {
				$query = "INSERT INTO #__flexicontent_fields_item_relations "
					." (field_id,item_id,valueorder,suborder,value"
					//.",qindex01,qindex02,qindex03"
					.") VALUES "
					."\n".implode(",\n", $rel_query_vals);
				$this->_db->setQuery($query);
				$this->_db->execute();
				if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
			}
			
			
			// **************************************************************
			// Save other versioned item data into the field versioning table
			// **************************************************************
			
			// a. Save a version of item properties that do not have a corresponding CORE Field
			if ( $record_versioned_data ) {
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
			
			// b. Finally save a version of the posted JoomFish translated data for J1.5, if such data are editted inside the item edit form
			/*if ( FLEXI_FISH && !empty($data['jfdata']) && $record_versioned_data )
			{
				$obj = new stdClass();
				$obj->field_id 		= -1;  // ID of Fake Field used to contain the Joomfish translated item data
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
		
		
		// ******************************
		// Trigger onAfterSaveField Event
		// ******************************
		if ( $fields )
		{
			if ( $print_logging_info ) $start_microtime = microtime(true);
			foreach($fields as $field)
			{
				$field_type = $field->iscore ? 'core' : $field->field_type;
				$file_data  = isset($files[$field->name]) ? $files[$field->name] : null;  // Pass a copy field's FILE data
				$result = FLEXIUtilities::call_FC_Field_Func($field_type, 'onAfterSaveField', array( &$field, &$postdata[$field->name], &$file_data, &$item ));
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
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		$cparams = $this->_cparams;
		$editjf_translations = $cparams->get('editjf_translations', 0);
		
		// ******************************
		// Check and store item in the db
		// ******************************
		
		// Make sure the data is valid
		if (!$item->check()) {
			$this->setError($item->getError());
			return false;
		}
		
		if (!$item->store()) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		
		// Set model properties
		$this->_id     = $item->id;
		$this->_item   = & $item;
		$this->_typeid = $item->type_id;
		
		
		// ****************************
		// Update language Associations
		// ****************************
		$this->saveAssociations($item, $data);
		
		
		// ***********************
		// Save access information
		// ***********************
		// Rules for J1.6+ are handled in the JTABLE class of the item with overriden JTable functions: bind() and store()
		
		
		// ***************************
		// If creating only return ...
		// ***************************
		if ($createonly) return true;
		
		
		// ****************************
		// Save joomfish data in the db
		// ****************************
		if ( FLEXI_FISH && $editjf_translations==2 )   // 0:disable with warning about found translations,  1:disable without warning about found translations,  2:edit-save translations, 
			$this->_saveJFdata( $data['jfdata'], $item );
		
		
		// ***********************************************
		// Delete old tag relations and Store the new ones
		// ***********************************************
		$tags = $data['tags'];
		$query = 'DELETE FROM #__flexicontent_tags_item_relations WHERE itemid = '.$item->id;
		$this->_db->setQuery($query);
		$this->_db->execute();
		foreach($tags as $tag)
		{
			$query = 'INSERT INTO #__flexicontent_tags_item_relations (`tid`, `itemid`) VALUES(' . $tag . ',' . $item->id . ')';
			$this->_db->setQuery($query);
			$this->_db->execute();
		}
		
		// ***********************************************************************************************************
		// Delete only category relations which are not part of the categories array anymore to avoid loosing ordering
		// ***********************************************************************************************************
		$cats = $data['categories'];
		$query 	= 'DELETE FROM #__flexicontent_cats_item_relations'
			. ' WHERE itemid = '.$item->id
			. ($cats ? ' AND catid NOT IN (' . implode(', ', $cats) . ')' : '')
			;
		$this->_db->setQuery($query);
		$this->_db->execute();

		// Get an array of the item's used categories (already assigned in DB)
		$query 	= 'SELECT catid'
			. ' FROM #__flexicontent_cats_item_relations'
			. ' WHERE itemid = '.$item->id
			;
		$this->_db->setQuery($query);
		$used = $this->_db->loadColumn();
		
		// Insert only the new records
		$cat_vals = array();
		foreach($cats as $cat)
		{
			if (!in_array($cat, $used))  $cat_vals[] = '('. $cat .','. $item->id .')';
		}
		if ( !empty($cat_vals) )
		{
			$query 	= 'INSERT INTO #__flexicontent_cats_item_relations (`catid`, `itemid`) VALUES ' . implode(",", $cat_vals);
			$this->_db->setQuery($query);
			$this->_db->execute();
			if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
		}
		
		return true;
	}
	
	
	/**
	 * Method to save Joomfish item translation data
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
			
			if ($dbtype == 'mysqli') {
				$result = mysqli_query( $db_connection , $query );
				if ($result===false) return JError::raiseError( 500, 'error '.__FUNCTION__.'():: '.mysqli_error($db_connection));
			} else if ($dbtype == 'mysql') {
				$result = mysql_query( $query, $db_connection  );
				if ($result===false) return JError::raiseError( 500, 'error '.__FUNCTION__.'():: '.mysql_error($db_connection));
			} else {
				$msg = 'unreachable code in '.__FUNCTION__.'(): direct db query, unsupported DB TYPE';
				throw new Exception($msg, 500);
			}
		}
		
		$modified = $item->modified ? $item->modified : $item->created;
		$modified_by = $item->modified_by ? $item->modified_by : $item->created_by;
		
		$langs	= FLEXIUtilities::getLanguages('shortcode');  // Get Joomfish active languages
		
		foreach($jfdata_arr as $shortcode => $jfdata)
		{
			//echo $shortcode." : "; print_r($jfdata);
			
			// Reconstruct (main)text field if it has splitted up e.g. to seperate editors per tab
			if (@$jfdata['text'] && is_array($jfdata['text'])) {
				// Force a readmore at the end of text[0] (=before TABs text) ... so that the before TABs text acts as introtext
				$jfdata['text'][0] .= (preg_match('#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i', $jfdata['text'][0]) == 0) ? ("\n".'<hr id="system-readmore" />') : "" ;
				$jfdata['text'] = implode('', $jfdata['text']);
			} else if ( empty($jfdata['text']) ) {
				$jfdata['text'] = '';
			}
			
			$jfdata['title'] = trim($jfdata['title']);
			$jfdata['alias'] = $jfdata['title'] ? $this->verifyAlias($jfdata['alias'], $jfdata['title'], $item) : '';
			
			// Search for the {readmore} tag and split the text up accordingly.
			$this->splitText($jfdata);
			
			// Delete existing Joom!Fish translation data for the current item
			$query  = "DELETE FROM  #__".$nn_content_tbl." WHERE language_id={$langs->$shortcode->id} AND reference_table='content' AND reference_id={$item->id}";
			$db->setQuery($query);
			$db->execute();
			
			// Apply new translation data
			$translated_fields = array('title','alias','introtext','fulltext','metadesc','metakey');
			foreach ($translated_fields as $fieldname) {
				if ( !strlen( @$jfdata[$fieldname] ) ) continue;
				//if ( !StringHelper::strlen(StringHelper::trim(str_replace("&nbsp;", "", strip_tags(@$jfdata[$fieldname])))) ) continue;   // skip empty content
				//echo "<br/><b>#__".$nn_content_tbl."($fieldname) :</b><br/>";
				$query = "INSERT INTO #__".$nn_content_tbl." (language_id, reference_id, reference_table, reference_field, value, original_value, original_text, modified, modified_by, published) ".
					"VALUES ( {$langs->$shortcode->id}, {$item->id}, 'content', '$fieldname', ".$db->Quote(@$jfdata[$fieldname]).", '".md5($item->{$fieldname})."', ".$db->Quote($item->{$fieldname}).", '$modified', '$modified_by', 1)";
				//echo $query."<br/>\n";
				$db->setQuery($query);
				$db->execute();
			}
		}
		
		return true;
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
		
		// *** NOTE: this->_item->tags may already contain a VERSIONED array of values !!!
		if( $this->_id == $item_id && !empty($this->_item->tags) ) {
			// Return existing tags of current item
			return $this->_item->tags;
		}
		else if ($item_id) {
			// Not current item, or current item's tags are not set
			$query = "SELECT tid FROM #__flexicontent_tags_item_relations WHERE itemid ='".$item_id."'";
			$this->_db->setQuery($query);
			$tags = $this->_db->loadColumn();
			if ($this->_id == $item_id) {
				// Retrieved tags of current item, set them
				$this->_item->tags = $tags;
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
		
		$query 	= 'SELECT *, t.id as tid FROM #__flexicontent_tags as t '
				. ' WHERE t.id IN (\'' . implode("','", $tagIds).'\')'
				. ' ORDER BY name ASC'
				;
		$this->_db->setQuery($query);
		$tagsData = $this->_db->loadObjectList('tid');

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
		$this->_db->setQuery($query);
		$tags = $this->_db->loadObjectlist();
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
		foreach ($versionrecords as $versionrecord) {
			if(!(int)$versionrecord->iscore)
				$this->_db->insertObject('#__flexicontent_fields_item_relations', $versionrecord);
		}
		$query = "UPDATE #__content SET version='$version' WHERE id='$id';";
		$this->_db->setQuery($query);
		$this->_db->execute($query);
		// handle the maintext not very elegant but functions properly
		$row = $this->getTable('flexicontent_items', '');
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
	 * @return object
	 * @since 1.0
	 */
	function gettags($mask="", $limit=100)
	{
		$escaped_mask = $this->_db->escape( $mask, true );
		$where = ($mask!="")?" name like ".$this->_db->Quote( '%'.$escaped_mask.'%', false )." AND":"";
		$query = 'SELECT * FROM #__flexicontent_tags WHERE '.$where.' published = 1 ORDER BY name LIMIT 0,'.((int)$limit);
		$this->_db->setQuery($query);
		$tags = $this->_db->loadObjectlist();
		return $tags;
	}
	
	
	/**
	 * Method to reset hits
	 * 
	 * @param int id
	 * @return int
	 * @since 1.0
	 */
	function resetHits($id)
	{
		$row = $this->getTable('flexicontent_items', '');
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
	function resetVotes($id)
	{
		// Delete main vote type
		$query = 'DELETE FROM #__content_rating WHERE content_id = '.$id;
		$this->_db->setQuery($query);
		$this->_db->execute();
		
		// Delete extra vote types
		$query = 'DELETE FROM #__flexicontent_items_extravote WHERE content_id = '.$id;
		$this->_db->setQuery($query);
		$this->_db->execute();
	}
	
	
	/**
	 * Method to get votes
	 * 
	 * @param int id
	 * @return object
	 * @since 1.0
	 */
	function getvotes($id=0)
	{
		$id = $id ? $id : $this->_id;
		
		$query = 'SELECT rating_sum, rating_count FROM #__content_rating WHERE content_id = '.(int)$id;
		$this->_db->setQuery($query);
		$votes = $this->_db->loadObjectlist();
		
		return $votes;
	}
	
	
	function getRatingDisplay($id=0)
	{
		$id = $id ? $id : $this->_id;
		
		$votes = $this->getvotes($id);
		$rating_resolution = $this->getVotingResolution($id);
		
		if ($votes) {
			$score	= round((((int)$votes[0]->rating_sum / (int)$votes[0]->rating_count) * (100 / $rating_resolution)), 2);
			$vote	= ((int)$votes[0]->rating_count > 1) ? (int)$votes[0]->rating_count . ' ' . JText::_( 'FLEXI_VOTES' ) : (int)$votes[0]->rating_count . ' ' . JText::_( 'FLEXI_VOTE' );
			$html = $score.'% | '.$vote;
		} else {
			$html = JText::_( 'FLEXI_NOT_RATED_YET' );
		}
		
		return $html;
	}
	
	
	function getVotingResolution($id=0)
	{
		static $rating_resolution = array();
		$id = $id ? $id : $this->_id;
		if ( empty($rating_resolution[$id]) ) {
			$this->_db->setQuery('SELECT * FROM #__flexicontent_fields WHERE field_type="voting"');
			$field = $this->_db->loadObject();
			$item = JTable::getInstance( $type = 'flexicontent_items', $prefix = '', $config = array() );
			$item->load( $id );
			FlexicontentFields::loadFieldConfig($field, $item);
			
			$_rating_resolution = (int)$field->parameters->get('rating_resolution', 5);
			$_rating_resolution = $_rating_resolution >= 5   ?  $_rating_resolution  :  5;
			$_rating_resolution = $_rating_resolution <= 100 ?  $_rating_resolution  :  100;
			$rating_resolution[$id] = $_rating_resolution;
		}
		return $rating_resolution[$id];
	}
	
	
	/**
	 * Method to get hits
	 * 
	 * @param int id
	 * @return int
	 * @since 1.0
	 */
	function gethits($id)
	{
		$query = 'SELECT hits FROM #__content WHERE id = '.(int)$id;
		$this->_db->setQuery($query);
		$hits = $this->_db->loadResult();
		
		return $hits;
	}
	
	
	
	/**
	 * Method to get subscriber count
	 * 
	 * @TODO add the notification as an option with a checkbox in the favourites screen
	 * @return object
	 * @since 1.5
	 */
	function getSubscribersCount()
	{
		static $subscribers = array();
		if ( isset($subscribers[$this->_id]) ) return $subscribers[$this->_id];
		
		$query	= 'SELECT COUNT(*)'
				.' FROM #__flexicontent_favourites AS f'
				.' LEFT JOIN #__users AS u'
				.' ON u.id = f.userid'
				.' WHERE f.itemid = ' . (int)$this->_id
				.'  AND u.block=0 ' //.' AND f.notify = 1'
				;
		$this->_db->setQuery($query);
		$subscribers[$this->_id] = $this->_db->loadResult();
		return $subscribers[$this->_id];
	}
	
	
	/**
	 * Decide item type id for existing or new item ... verifying that the type exists ...
	 * NOTE: for new items the value of 'typeid' variable out of the JRequest array is used
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getTypesselected($force = false)
	{
		static $typedata = array();
		
		if ( !$this->_id && !$this->_typeid) {
			$_typedata = new stdClass();
			$_typedata->id = 0;
			$_typedata->name = null;
		}
		
		if ( !$force && isset($typedata[$this->_typeid]) ) return $typedata[$this->_typeid];
		
		// Existing item, get its type
		if ($this->_id)
		{
			$query = 'SELECT ie.type_id as id,t.name FROM #__flexicontent_items_ext as ie'
				. ' JOIN #__flexicontent_types as t ON ie.type_id=t.id'
				. ' WHERE ie.item_id = ' . (int)$this->_id;
			$this->_db->setQuery($query);
			$_typedata = $this->_db->loadObject();
			if ($_typedata) $this->_typeid = $_typedata->id;
		}
		
		 // New item check type exists
		else if ( (int)$this->_typeid )
		{
			$query = 'SELECT t.id,t.name FROM #__flexicontent_types as t'
				. ' WHERE t.id = ' . (int)$this->_typeid;
			$this->_db->setQuery($query);
			$_typedata = $this->_db->loadObject();
			if (!$_typedata) $this->_typeid = 0;
		}
		
		// Create default type object, if type not specified or not found
		if (empty($_typedata)) {
			$_typedata = new stdClass();
			$_typedata->id = 0;
			$_typedata->name = null;
		}
		
		// Cache and return
		$typedata[$this->_typeid] = & $_typedata;
		return $typedata[$this->_typeid];
	}
	
	
	/**
	 * Method to get used categories when performing an edit action
	 * 
	 * @return array
	 * @since 1.0
	 */
	function getCatsselected($item_id=0)
	{
		// Allow retrieval of categories of any item
		$item_id = $item_id ? $item_id : $this->_id;
		
		// *** NOTE: this->_item->categories may already contain a VERSIONED array of values !!!
		if( $this->_id == $item_id && !empty($this->_item->categories) ) {
			// Return existing categories of current item
			return $this->_item->categories;
		}
		else if ($item_id) {
			// Not current item, or current item's categories are not set
			$query = "SELECT tid FROM #__flexicontent_cats_item_relations WHERE itemid ='".$item_id."'";
			$this->_db->setQuery($query);
			$categories = $this->_db->loadColumn();
			if ($this->_id == $item_id) {
				// Retrieved categories of current item, set them
				$this->_item->categories = & $categories;
				// 'cats' is alias of categories
				$this->_item->cats = & $this->_item->categories;  // possibly used by CORE plugin for displaying in frontend
			}
			return $categories;
		} else {
			return array();
		}
	}
	
	
	/**
	 * Method to get the type parameters of an item
	 * 
	 * @return string
	 * @since 1.5
	 */
	function getTypeparams($force = false)
	{
		static $typeparams = array();
		
		if ( !$this->_id && !$this->_typeid) return '';
		
		if ( !$force && isset($typeparams[$this->_typeid]) ) return $typeparams[$this->_typeid];
		
		if ( $this->_id || $this->_typeid)
		{
			$query	= 'SELECT t.id, t.attribs'
				. ' FROM #__flexicontent_types AS t'
				.( $this->_id ?
					' JOIN #__flexicontent_items_ext AS ie ON ie.type_id = t.id WHERE ie.item_id = ' . (int)$this->_id :
					' WHERE t.id = ' . (int)$this->_typeid
				);
			$this->_db->setQuery($query);
			if ( $data = $this->_db->loadObject() )
			{
				$this->_typeid = $data->id;
				$attribs = $data->attribs;
			}
		}
		
		// Cache and return
		$typeparams[$this->_typeid] = !empty($attribs) ? $attribs : '';
		return $typeparams[$this->_typeid];
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
	function getCoreFieldValue(&$field, $version = 0, &$old_item=null)
	{
		if ($old_item) {
			$item = & $old_item;
		} else if (isset($this->_item)) {
			$item = $this->_item;
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
			$value = StringHelper::strlen( StringHelper::trim($item->fulltext) ) ? $item->introtext . "<hr id=\"system-readmore\" />" . $item->fulltext : $item->introtext;
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
	 * (a) the current item or (b) the one specified in the URL (variable 'typeid')
	 *
	 * NOTE: Fields are skipped if (a) are not pubished OR (b) no longer belong to the item type
	 * NOTE: VERSIONED field values will be retrieved if version is set in the HTTP REQUEST !!!
	 * 
	 * @return object
	 * @since 1.5
	 */
	function getExtrafields($force = false, $lang_parent_id = 0, &$old_item=null)
	{
		static $fields;
		if ($fields && !$force) return $fields;
		
		$use_versioning = $this->_cparams->get('use_versioning', 1);
		$typeid = $this->get('type_id');   // Get item's type_id, loading item if neccessary
		$typeid = $typeid ? $typeid : JRequest::getVar('typeid', 0, '', 'int');
		$type_join = ' JOIN #__flexicontent_fields_type_relations AS ftrel ON ftrel.field_id = fi.id AND ftrel.type_id='.$typeid;
		
		$query = 'SELECT  fi.*'
				.' FROM #__flexicontent_fields AS fi'
				.($typeid ? $type_join : '')            // Require field belonging to item type
				.' WHERE fi.published = 1'              // Require field published
				.($typeid ? '' : ' AND fi.iscore = 1')  // Get CORE fields when typeid not set
				.' ORDER BY '. ($typeid ? 'ftrel.ordering, ' : '') .'fi.ordering, fi.name'
				;
		$this->_db->setQuery($query);
		$fields = $this->_db->loadObjectList('name');
		if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
		
		// Get values of CUSTOM fields for current item
		$custom_vals[$this->_id] = $this->getCustomFieldsValues($this->_id, $this->_version);
		
		// Get values of language parent item (if not loading a specific version) to use them for untranslatable fields
		if ( $lang_parent_id && !$this->_version) {
			$custom_vals[$lang_parent_id] = $this->getCustomFieldsValues($lang_parent_id, 0);
		}
		foreach ($fields as $field)
		{
			$field->item_id		= (int)$this->_id;
			// version number should be ZERO when versioning disabled, or when wanting to load the current version !!!
			if ( (!$this->_version || !$use_versioning) && $field->iscore) {
				// Load CURRENT (non-versioned) core field from properties of item object
				$field->value = $this->getCoreFieldValue($field, $this->_version, $old_item);
			} else {
				// Load non core field (versioned or non-versioned) OR core field (versioned only)
				// while checking if current field is using untranslatable value from original content item
				$item_id = ($lang_parent_id && @$field->untranslatable && !$this->_version) ? $lang_parent_id : $this->_id;
				$field->value = isset( $custom_vals[$item_id][$field->id] ) ? $custom_vals[$item_id][$field->id] : array();
				if( ( $field->name=='categories') || $field->name=='tags' ) {
					// categories and tags must have been serialized but some early versions did not do it, we will check before unserializing them
					$field->value = ($array = @unserialize($field->value[0]) ) ? $array : $field->value;
				}
			}
			
			//echo "Got ver($this->_version) id {$field->id}: ". $field->name .": ";  print_r($field->value); 	echo "<br/>";
			$field->parameters = new JRegistry($field->attribs);
		}
		
		return $fields;
	}
	
	
	/**
	 * Method to change the state of an item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function setitemstate($id, $state = 1)
	{
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		$dispatcher = JDispatcher::getInstance();
		JRequest::setVar("isflexicontent", "yes");
		static $event_failed_notice_added = false;
		
		if ( $id )
		{
			$v = FLEXIUtilities::getCurrentVersions((int)$id);
			
			$query = 'UPDATE #__content'
				. ' SET state = ' . (int)$state
				. ' WHERE id = '.(int)$id
				//. ' AND ( checked_out = 0 OR ( checked_out = ' . (int) $user->get('id'). ' ) )'
			;
			$this->_db->setQuery( $query );
			$this->_db->execute();
			if ( $this->_db->getErrorNum() )  throw new Exception($this->_db->getErrorMsg(), 500);
			
			$query = 'UPDATE #__flexicontent_items_tmp'
				. ' SET state = ' . (int)$state
				. ' WHERE id = '.(int)$id
				//. ' AND ( checked_out = 0 OR ( checked_out = ' . (int) $user->get('id'). ' ) )'
			;
			$this->_db->setQuery( $query );
			$this->_db->execute();
			if ( $this->_db->getErrorNum() )  throw new Exception($this->_db->getErrorMsg(), 500);
			
			$query = 'UPDATE #__flexicontent_items_versions'
				. ' SET value = ' . (int)$state
				. ' WHERE item_id = '.(int)$id
				. ' AND valueorder = 1'
				. ' AND field_id = 10'
				. ' AND version = ' .(int)$v['version']
				;
			$this->_db->setQuery( $query );
			$this->_db->execute();
			if ( $this->_db->getErrorNum() )  throw new Exception($this->_db->getErrorMsg(), 500);
		}
		
		
		// ****************************************************************
		// Trigger Event 'onContentChangeState' of Joomla's Content plugins
		// ****************************************************************
		// Make sure we import flexicontent AND content plugins since we will be triggering their events
		JPluginHelper::importPlugin('content');
		
		// PREPARE FOR TRIGGERING content events
		// We need to fake joomla's states ... when triggering events
		$fc_state = $state;
		if ( in_array($fc_state, array(1,-5)) ) $jm_state = 1;           // published states
		else if ( in_array($fc_state, array(0,-3,-4)) ) $jm_state = 0;   // unpublished states
		else $jm_state = $fc_state;                                      // trashed & archive states
		$fc_itemview = $app->isSite() ? FLEXI_ITEMVIEW : 'item';
		
		$item = new stdClass();
		
		// Compatibility steps (including Joomla compatible state),
		// so that 3rd party plugins using the change state event work properly
	  JRequest::setVar('view', 'article');	  JRequest::setVar('option', 'com_content');
		$item->state = $jm_state;
		
		$result = $dispatcher->trigger($this->event_change_state, array('com_content.article', (array) $id, $jm_state));
		
		// Revert compatibilty steps ... the $item->state is not used further regardless if it was changed,
		// besides the event_change_state using plugin should have updated DB state value anyway
		JRequest::setVar('view', $fc_itemview);	  JRequest::setVar('option', 'com_flexicontent');
		if ($item->state == $jm_state) $item->state = $fc_state;  // this check is redundant, item->state is not used further ...
		
		if (in_array(false, $result, true) && !$event_failed_notice_added) {
			JError::raiseNotice(10, JText::_('One of plugin event handler for onContentChangeState failed') );
			$event_failed_notice_added = true;
			return false;
		}
		
		return true;
	}
	
	
	/**
	 * Method to get the versionlist which belongs to the item
	 * 
	 * @return object
	 * @since 1.5
	 */
	function getVersionList($limitstart=0, $versionsperpage=0)
	{
		$query 	= 'SELECT v.version_id AS nr, v.created AS date, u.name AS modifier, v.comment AS comment'
				.' FROM #__flexicontent_versions AS v'
				.' LEFT JOIN #__users AS u ON v.created_by = u.id'
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
				.' LEFT JOIN #__users AS u ON v.created_by = u.id'
				.' WHERE item_id = ' . (int)$this->_id
				;
		$this->_db->setQuery($query);
		return $this->_db->loadResult();
	}
	
	/**
	 * Helper method to format a value as array
	 * 
	 * @return object
	 * @since 1.5
	 */
	function formatToArray($value)
	{
		$value = $value ? $value : array();
		$value = is_array($value) ? $value : array($value);
		return $value;
	}

	/**
	 * Helper method to bind form posted item parameters and and metadata to the item
	 * 
	 * @return object
	 * @since 1.5
	 */
	function mergeAttributes(&$item, &$params, &$metadata)
	{
		// Build item parameters INI string
		if (is_array($params))
		{
			$item->attribs = new JRegistry($item->attribs);
			
			
			$new_ilayout = isset($params['ilayout']) ? $params['ilayout'] : null;  // a non-set will return null, but let's make this cleaner
			$old_ilayout = $item->attribs->get('ilayout');
			
			//echo "new_ilayout: $new_ilayout,  old_ilayout: $old_ilayout <br/>";
			//echo "<pre>"; print_r($params); exit;
			
			
			// **************************************************************************************
			// THIS is costly if site has many templates but it will only happen if layout is changed
			// **************************************************************************************
			
			// WARNING: NULL layout means layout was not present in the FORM, aka do not clear parameters
			if (
				// (a) non-null but empty new ilayout, and (b) old layout was non empty: clear parameters of old layout, to allow proper heritage from content type (= aka use type's defaults for ilayout and its parameters)
				($new_ilayout!==null && $new_ilayout=='' && !empty($old_ilayout))  ||
				
				// (a) new ilayout was given and (b) is different than old ilayout, clear ilayout parameters, in case old parameters are have same name with of new ilayout parameters
				($new_ilayout!='' && $new_ilayout!=$old_ilayout)
			) {
				//JFactory::getApplication()->enqueueMessage('Layout changed, cleared old layout parameters', 'message');
				
				$themes = flexicontent_tmpl::getTemplates();
				foreach ($themes->items as $tmpl_name => $tmpl)
				{
					//if ( $tmpl_name == @$params['ilayout'] ) continue;
					
					$tmpl_params = $tmpl->params;
					$jform = new JForm('com_flexicontent.template.item', array('control' => 'jform', 'load_data' => true));
					$jform->load($tmpl_params);
					foreach ($jform->getGroup('attribs') as $field)
					{
						// !! Do not call empty() on a variable created by magic __get function
						if ( @ $field->fieldname ) $item->attribs->set($field->fieldname, null);
					}
				}
			}
			
			if ( isset($params['layouts']) ) {
				if (isset($params['layouts'][$new_ilayout])) {
					foreach ($params['layouts'][$new_ilayout] as $k => $v) {
						//echo "$k: $v <br/>";
						$item->attribs->set($k, $v);
					}
				}
				unset($params['layouts']);
			}
			foreach ($params as $k => $v) {
				//$v = is_array($v) ? implode('|', $v) : $v;
				$item->attribs->set($k, $v);
			}
			//echo "<pre>"; print_r($params); print_r($item->attribs); exit;
			$item->attribs = $item->attribs->toString();
		}
		
		// Build item metadata INI string
		if (is_array($metadata))
		{
			$item->metadata = new JRegistry($item->metadata);
			// NOTE: metadesc, metakey are directly under jform 'attribs' so they do not need special handling
			foreach ($metadata as $k => $v) {
				$item->metadata->set($k, $v);
			}
			$item->metadata = $item->metadata->toString();
		}
	}
	
	
	/*
	 * Method to retrieve the configuration for the Content Submit/Update notifications
	 */
	function & getNotificationsConf(&$params)
	{
		static $nConf = null;
		if ($nConf !== null) return $nConf;
		
		// (a) Check if notifications are not enabled
		if ( !$params->get('enable_notifications', 0) ) {
			$nConf = false;
			return $nConf;
		}
		
		$db = JFactory::getDBO();
		$nConf = new stdClass();
		
		// (b) Get Content Type specific notifications (that override global)
		$nConf->userlist_notify_new            = FLEXIUtilities::paramToArray( $params->get('userlist_notify_new'), $regex="/[\s]*,[\s]*/", $filterfunc="intval");
		$nConf->usergrps_notify_new            = FLEXIUtilities::paramToArray( $params->get('usergrps_notify_new', array()) );
		$nConf->userlist_notify_new_pending    = FLEXIUtilities::paramToArray( $params->get('userlist_notify_new_pending'), $regex="/[\s]*,[\s]*/", $filterfunc="intval");
		$nConf->usergrps_notify_new_pending    = FLEXIUtilities::paramToArray( $params->get('usergrps_notify_new_pending', array()) );
		
		$nConf->userlist_notify_existing             = FLEXIUtilities::paramToArray( $params->get('userlist_notify_existing'), $regex="/[\s]*,[\s]*/", $filterfunc="intval");
		$nConf->usergrps_notify_existing             = FLEXIUtilities::paramToArray( $params->get('usergrps_notify_existing', array()) );
		$nConf->userlist_notify_existing_reviewal    = FLEXIUtilities::paramToArray( $params->get('userlist_notify_existing_reviewal'), $regex="/[\s]*,[\s]*/", $filterfunc="intval");
		$nConf->usergrps_notify_existing_reviewal    = FLEXIUtilities::paramToArray( $params->get('usergrps_notify_existing_reviewal', array()) );
		
		// (c) Get category specific notifications
		if ( $params->get('nf_allow_cat_specific') ) 
		{
			$cats = $this->get('categories');
			$query = "SELECT params FROM #__categories WHERE id IN (".implode(',',$cats).")";
			$db->setQuery( $query );
			$mcats_params = $db->loadColumn();
			
			foreach ($mcats_params as $cat_params) {
				$cat_params = new JRegistry($cat_params);
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
				if ( $db->getErrorNum() ) echo $db->getErrorMsg();  // if ($ntype=='notify_new_pending') { echo "<pre>"; print_r($user_emails_ulist); exit; }
			}
			
			$user_emails_ugrps = array();
			if ( count( $nConf->{$ugrps[$ntype]} ) )
			{
				// emails for user groups
				$query = "SELECT DISTINCT email FROM #__users as u"
					." JOIN #__user_usergroup_map ugm ON u.id=ugm.user_id AND ugm.group_id IN (".implode(",",$nConf->{$ugrps[$ntype]}).")";
				
				$db->setQuery( $query );
				$user_emails_ugrps = $db->loadColumn();
				if ( $db->getErrorNum() ) echo $db->getErrorMsg();  // if ($ntype=='notify_new_pending') { print_r($user_emails_ugrps); exit; }
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
		
		$isnew         = $notify_vars->isnew;
		$notify_emails = $notify_vars->notify_emails;
		$notify_text   = $notify_vars->notify_text;
		$before_cats   = $notify_vars->before_cats;
		$after_cats    = $notify_vars->after_cats;
		$oitem         = $notify_vars->original_item;
		
		if ( !count($notify_emails) ) return true;
		
		$app     = JFactory::getApplication();
		$db      = JFactory::getDBO();
		$user    = JFactory::getUser();
		$use_versioning = $this->_cparams->get('use_versioning', 1);
		
		// Get category titles of categories add / removed from the item
		if ( !$isnew ) {
			$cats_added_ids = array_diff(array_keys($after_cats), array_keys($before_cats));
			foreach($cats_added_ids as $cats_added_id) {
				$cats_added_titles[] = $after_cats[$cats_added_id]->title;
			}
			
			$cats_removed_ids = array_diff(array_keys($before_cats), array_keys($after_cats));
			foreach($cats_removed_ids as $cats_removed_id) {
				$cats_removed_titles[] = $before_cats[$cats_removed_id]->title;
			}
			$cats_altered = count($cats_added_ids) + count($cats_removed_ids);
			$after_maincat = $this->get('catid');
		}
		
		// Get category titles in the case of new item or categories unchanged
		if ( $isnew || !$cats_altered) {
			foreach($after_cats as $after_cat) {
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
			$subject .= JText::_( $isnew? 'FLEXI_NF_NEW_CONTENT_SUBMITTED' : 'FLEXI_NF_EXISTING_CONTENT_UPDATED') . " ";
			
			// (b) ADD INFO about editor's name and username (or being guest)
			$subject .= !$user->id ? JText::sprintf('FLEXI_NF_BY_GUEST') : JText::sprintf('FLEXI_NF_BY_USER', $user->get('name'), $user->get('username'));
			
			// (c) (new items) ADD INFO for content needing publication approval
			if ($isnew) {
				$subject .= ": ";
				$subject .= JText::_( $needs_publication_approval ? 'FLEXI_NF_NEEDS_PUBLICATION_APPROVAL' : 'FLEXI_NF_NO_APPROVAL_NEEDED');
			}
			
			// (d) (existing items with versioning) ADD INFO for content needing version reviewal
			if ( !$isnew && $use_versioning) {
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
		$body  = '<u>'.JText::_( 'FLEXI_NF_CONTENT_TITLE' ) . "</u>: ";
		if ( !$isnew ) {
			$_changed = $oitem->title != $this->get('title');
			$body .= " [ ". JText::_($_changed ? 'FLEXI_NF_MODIFIED' : 'FLEXI_NF_UNCHANGED') . " ] : <br/>\r\n";
			$body .= !$_changed ? "" : $oitem->title . " &nbsp; ==> &nbsp; ";
		}
		$body .= $this->get('title'). "<br/>\r\n<br/>\r\n";
		
		// ADD INFO about state
		$state_names = array(1=>'FLEXI_PUBLISHED', -5=>'FLEXI_IN_PROGRESS', 0=>'FLEXI_UNPUBLISHED', -3=>'FLEXI_PENDING', -4=>'FLEXI_TO_WRITE', (FLEXI_J16GE ? 2:-1)=>'FLEXI_ARCHIVED', -2=>'FLEXI_TRASHED');
		
		$body .= '<u>'.JText::_( 'FLEXI_NF_CONTENT_STATE' ) . "</u>: ";
		if ( !$isnew ) {
			$_changed = $oitem->state != $this->get('state');
			$body .= " [ ". JText::_( $_changed ? 'FLEXI_NF_MODIFIED' : 'FLEXI_NF_UNCHANGED') . " ] : <br/>\r\n";
			$body .= !$_changed ? "" : JText::_( $state_names[$oitem->state] ) . " &nbsp; ==> &nbsp; ";
		}
		$body .= JText::_( $state_names[$this->get('state')] ) ."<br/><br/>\r\n";
		
		// ADD INFO for author / modifier
		if ( in_array('creator',$nf_extra_properties) )
		{
			$body .= '<u>'.JText::_( 'FLEXI_NF_CREATOR_LONG' ) . "</u>: ";
			if ( !$isnew ) {
				$_changed = $oitem->created_by != $this->get('created_by');
				$body .= " [ ". JText::_($_changed ? 'FLEXI_NF_MODIFIED' : 'FLEXI_NF_UNCHANGED') . " ] : <br/>\r\n";
				$body .= !$_changed ? "" : $oitem->creator . " &nbsp; ==> &nbsp; ";
			}
			$body .= $this->get('creator'). "<br/>\r\n";
		}
		if ( in_array('modifier',$nf_extra_properties) && !$isnew )
		{
			$body .= '<u>'.JText::_( 'FLEXI_NF_MODIFIER_LONG' ) . "</u>: ";
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
			
			$body .= '<u>'.JText::_( 'FLEXI_NF_CREATION_TIME' ) . "</u>: ";
			$body .= $date_created->format($format = 'D, d M Y H:i:s', $local = true);
			$body .= $tz_offset_str. "<br/>\r\n";
		}
		if ( in_array('modified',$nf_extra_properties) && !$isnew )
		{
			$date_modified = JFactory::getDate($this->get('modified'));
			$date_modified->setTimezone($tz);
			
			$body .= '<u>'.JText::_( 'FLEXI_NF_MODIFICATION_TIME' ) . "</u>: ";
			$body .= $date_modified->format($format = 'D, d M Y H:i:s', $local = true);
			$body .= $tz_offset_str. "<br/>\r\n";
		}
		$body .= "<br/>\r\n";
		
		// ADD INFO about category assignments
		$body .= '<u>'.JText::_( 'FLEXI_NF_CATEGORIES_ASSIGNMENTS').'</u>';
		if (!$isnew) {
			$body .= " [ ". JText::_( $cats_altered ? 'FLEXI_NF_MODIFIED' : 'FLEXI_NF_UNCHANGED') . " ] : <br/>\r\n";
		} else {
			$body .= " : <br/>\r\n";
		}
		foreach ($cats_titles as $i => $cats_title) {
			$body .= " &nbsp; ". ($i+1) .". ". $cats_title ."<br/>\r\n";
		}
		
		// ADD INFO for category assignments added or removed
		if ( !empty($cats_added_titles) && count($cats_added_titles) ) {
			$body .= '<u>'.JText::_( 'FLEXI_NF_ITEM_CATEGORIES_ADDED') . "</u> : <br/>\r\n";
			foreach ($cats_added_titles as $i => $cats_title) {
				$body .= " &nbsp; ". ($i+1) .". ". $cats_title ."<br/>\r\n";
			}
		}
		if ( !empty($cats_removed_titles) &&  count($cats_removed_titles) ) {
			$body .= '<u>'.JText::_( 'FLEXI_NF_ITEM_CATEGORIES_REMOVED') . "</u> : <br/>\r\n";
			foreach ($cats_removed_titles as $i => $cats_title) {
				$body .= " &nbsp; ". ($i+1) .". ". $cats_title ."<br/>\r\n";
			}
		}
		$body .= "<br/>\r\n<br/>\r\n";
		
		$lang = '&lang='. substr($this->get('language') ,0,2) ;
		
		// ADD INFO for custom notify text
		$subject .= ' '. JText::_( $notify_text );
		
		// ADD INFO for view/edit link
		if ( in_array('viewlink',$nf_extra_properties) )
		{
			$body .= '<u>'.JText::_( 'FLEXI_NF_VIEW_IN_FRONTEND' ) . "</u> : <br/>\r\n &nbsp; ";
			$link = JRoute::_( JURI::root(false).FlexicontentHelperRoute::getItemRoute($this->get('id'), $this->get('catid')) . $lang);
			$body .= '<a href="' . $link . '" target="_blank">' . $link . "</a><br/>\r\n<br/>\r\n";  // THIS IS BOGUS *** for unicode menu aliases
			//$body .= $link . "<br/>\r\n<br/>\r\n";
		}
		if ( in_array('editlinkfe',$nf_extra_properties) )
		{
			$body .= '<u>'.JText::_( 'FLEXI_NF_EDIT_IN_FRONTEND' ) . "</u> : <br/>\r\n &nbsp; ";
			$link = JRoute::_( JURI::root(false).'index.php?option=com_flexicontent&view='.FLEXI_ITEMVIEW.'&cid='.$this->get('catid').'&id='.$this->get('id').'&task=edit');
			$body .= '<a href="' . $link . '" target="_blank">' . $link . "</a><br/>\r\n<br/>\r\n";  // THIS IS BOGUS *** for unicode menu aliases
			//$body .= $link . "<br/>\r\n<br/>\r\n";
		}
		if ( in_array('editlinkbe',$nf_extra_properties) )
		{
			$body .= '<u>'.JText::_( 'FLEXI_NF_EDIT_IN_BACKEND' ) . "</u> : <br/>\r\n &nbsp; ";
			$fc_ctrl_task = FLEXI_J16GE ? 'task=items.edit' : 'controller=items&task=edit';
			$link = JRoute::_( JURI::root(false).'administrator/index.php?option=com_flexicontent&'.$fc_ctrl_task.'&cid='.$this->get('id'));
			$body .= '<a href="' . $link . '" target="_blank">' . $link . "</a><br/>\r\n<br/>\r\n";  // THIS IS BOGUS *** for unicode menu aliases
			//$body .= $link . "<br/>\r\n<br/>\r\n";
		}
		
		// ADD INFO for introtext/fulltext
		if ( $params->get('nf_add_introtext') )
		{
			//echo "<pre>"; print_r($this->_item); exit;
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
					//. ' AND ( c.checked_out = 0 OR ( c.checked_out = ' . (int) $user->get('id'). ' ) )'
					;
			$this->_db->setQuery( $query );
			$cids = $this->_db->loadObjectList();
			
			if (!$this->_db->execute()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
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
			$canEditState = $this->canEditState( $item, $check_cat_perm=true );
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
		
		// This may not be needed since the item was already in unpublished stated ??
		$cache = FLEXIUtilities::getCache($group='', 0); // backend
		$cache->clean('com_flexicontent_items');
		$cache->clean('com_flexicontent_filters');
		$cache = FLEXIUtilities::getCache($group='', 1); // frontend
		$cache->clean('com_flexicontent_items');
		$cache->clean('com_flexicontent_filters');
		
		return $msg;
	}
	
	
	function getLangAssocs($id=0)
	{
		static $translations = array();
		
		$id = !$id ? $this->_id : $id;
		if (!$id) return array();
		
		// Return cached
		if (isset($translations[$id])) return $translations[$id];
		
		// Start with empty array
		$translations[$id] = array();
		if ($id = $this->_id) $this->_translations = array();
		
		// Get associated translations
		$query = 'SELECT `key`'
			. ' FROM #__associations'
			. ' WHERE id = '. $this->_id .' AND context = "com_content.item"';
		$this->_db->setQuery($query);
		$assoc_key = $this->_db->loadResult();
		if (!$assoc_key) return $translations[$id];
		
		$query = 'SELECT i.id as id, i.title, i.created, i.modified, i.language as language, i.language as lang '
			. ' FROM #__content AS i '
			. ' JOIN #__associations AS a ON i.id=a.id '
			. ' WHERE a.context = "com_content.item" AND a.`key`= '.$this->_db->Quote($assoc_key);
		$this->_db->setQuery($query);
		$translations[$id] = $this->_db->loadObjectList('id');
		
		// Set this object translations if id is same
		if ($id = $this->_id) $this->_translations = $translations[$id];
		
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
	public function featured($pks, $value = 0)
	{
		// Sanitize the ids.
		$pks = (array) $pks;
		JArrayHelper::toInteger($pks);

		if (empty($pks)) {
			$this->setError(JText::_('NO item selected'));
			return false;
		}
		
		$table = $this->getTable('flexicontent_content_frontpage', '');

		try {
			$db = $this->getDbo();

			$db->setQuery(
				'UPDATE #__content' .
				' SET featured = '.(int) $value.
				' WHERE id IN ('.implode(',', $pks).')'
			);
			if (!$db->execute()) {
				throw new Exception($db->getErrorMsg());
			}

			if ((int)$value == 0) {
				// Adjust the mapping table.
				// Clear the existing features settings.
				$db->setQuery(
					'DELETE FROM #__content_frontpage' .
					' WHERE content_id IN ('.implode(',', $pks).')'
				);
				if (!$db->execute()) {
					throw new Exception($db->getErrorMsg());
				}
			} else {
				// first, we find out which of our new featured articles are already featured.
				$query = $db->getQuery(true);
				$query->select('f.content_id');
				$query->from('#__content_frontpage AS f');
				$query->where('content_id IN ('.implode(',', $pks).')');
				//echo $query;
				$db->setQuery($query);

				if (!is_array($old_featured = $db->loadColumn())) {
					throw new Exception($db->getErrorMsg());
				}

				// we diff the arrays to get a list of the articles that are newly featured
				$new_featured = array_diff($pks, $old_featured);

				// Featuring.
				$tuples = array();
				foreach ($new_featured as $pk) {
					$tuples[] = '('.$pk.', 0)';
				}
				if (count($tuples)) {
					$db->setQuery(
						'INSERT INTO #__content_frontpage ('.$db->quoteName('content_id').', '.$db->quoteName('ordering').')' .
						' VALUES '.implode(',', $tuples)
					);
					if (!$db->execute()) {
						$this->setError($db->getErrorMsg());
						return false;
					}
				}
			}

		} catch (Exception $e) {
			$this->setError($e->getMessage());
			return false;
		}

		$table->reorder();

		$this->cleanCache();

		return true;
	}
	
	
	function splitText(& $data)
	{
		// The text field is stored in the db as to seperate fields: introtext & fulltext
		// So we search for the {readmore} tag and split up the text field accordingly.
		$pattern = '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
		if (is_array($data))
		{
			$tagPos = preg_match($pattern, @ $data['text']);
			if ($tagPos == 0) {
				$data['introtext'] = @ $data['text'];
				$data['fulltext']  = '';
			} else {
				list($data['introtext'], $data['fulltext']) = preg_split($pattern, $data['text'], 2);
				$data['fulltext'] = StringHelper::strlen( StringHelper::trim($data['fulltext']) ) ? $data['fulltext'] : '';
			}
		}
		else {
			$item = & $data;
			$tagPos = preg_match($pattern, $item->text);
			if ($tagPos == 0) {
				$item->introtext = $item->text;
				$item->fulltext  = '';
			} else {
				list($item->introtext, $item->fulltext) = preg_split($pattern, $item->text, 2);
				$item->fulltext = StringHelper::strlen( StringHelper::trim($item->fulltext) ) ? $item->fulltext : '';
			}
		}
	}
	
	
	function verifyAlias($alias, $title, &$item)
	{
		$alias = trim($alias);
		if ( empty($alias) )
		{
			$alias = $title;
		}
		
		if ( !JFactory::getConfig()->get('unicodeslugs') )
		{
			$alias = $item->transliterate($alias, $item);
		}
		
		// Call the default conversion
		$alias = JApplicationHelper::stringURLSafe($alias);
		
		if(trim(str_replace('-','',$alias)) == '')
		{
			$alias = JFactory::getDate()->format('Y-m-d-H-i-s');
		}
		
		// Check for unique Alias
		$sub_q = 'SELECT catid FROM #__flexicontent_cats_item_relations WHERE itemid='.(int)$item->id;
		$query = 'SELECT COUNT(*) FROM #__flexicontent_items_tmp AS i '
			.' JOIN #__flexicontent_items_ext AS e ON i.id = e.item_id '
			.' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON i.id = rel.itemid '
			.' WHERE i.alias='.$this->_db->Quote($alias)
			.'  AND (i.catid='.(int)$item->id.' OR rel.catid IN ('.$sub_q.') )'
			.'  AND e.language = '.$this->_db->Quote($item->language)
			.'  AND i.id <> '.(int)$item->id
			;
		$this->_db->setQuery($query);
		$duplicate_aliases = (boolean) $this->_db->loadResult();
		
		if ($duplicate_aliases)
		{
			$alias = $alias.'_'.$item->id;
		}
		return $alias;
	}
	
	
	/**
	 * Method to save language associations
	 *
	 * @return  boolean True if successful
	 */
	function saveAssociations(&$item, &$data)
	{
		$item = $item ? $item: $this->_item;
		$context = 'com_content';
		
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
}