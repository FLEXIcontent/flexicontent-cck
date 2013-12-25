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

jimport('joomla.application.component.model');
jimport( 'joomla.html.parameter' );
/**
 * FLEXIcontent Component Item Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class ParentClassItem extends JModelLegacy
{
	var $_name = 'ParentClassItem';
	
	/**
	 * Component parameters
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
		
		// --. Get component parameters , merge (for frontend) with current menu item parameters
		$this->_cparams = clone( JComponentHelper::getParams('com_flexicontent') );
		if (!$app->isAdmin()) {
			$menu = $app->getMenu()->getActive();
			if ($menu) {
				$menu_params = FLEXI_J16GE ? $menu->params : new JParameter($menu->params);
				$this->_cparams->merge($menu_params);
			}
		}
		
		// --. Get & Set ITEM's primary key (pk) and (for frontend) the current category
		if (!$app->isAdmin()) {
			// FRONTEND, use "id" from request
			$pk = JRequest::getVar('id', 0, 'default', 'int');
			if ( !JRequest::getVar('task') )
				$curcatid = JRequest::getVar('cid', 0, $hash='default', 'int');
			else
				$curcatid = 0;
		}
		else
		{
			// BACKEND, use "cid" array from request, but check first for a POST 'id' variable
			// this is a correction for problematic name of categories AS cid in item edit form ...
			$data = JRequest::get( 'post' );
			
			// Must check if id is SET and if it is non-ZERO !
			if ( FLEXI_J16GE ? isset($data['jform']['id']) : isset($data['id']) ) {
				$pk = FLEXI_J16GE ? $data['jform']['id'] : $data['id'];
			} else {
				$cid = JRequest::getVar( 'cid', array(0), $hash='default', 'array' );
				JArrayHelper::toInteger($cid, array(0));
				$pk = $cid[0];
			}
			$curcatid = 0;
		}
		$this->setId($pk, $curcatid);  // NOTE: when setting $pk to a new value the $this->_item is cleared
		
		$this->populateState();
	}

	/**
	 * Method to set the identifier
	 *
	 * @access	public
	 * @param	int item identifier
	 */
	function setId($id, $currcatid=0)
	{
		// Set a new item id and wipe data
		if ($this->_id != $id) {
			$this->_item = null;
		}
		$this->_id = (int) $id;
		
		// Set current category, but verify item is assigned to this category, (SECURITY concern)
		$this->_cid = (int) $currcatid;
		if ($this->_cid) {
			$q = "SELECT catid FROM #__flexicontent_cats_item_relations WHERE itemid =". (int)$this->_id ." AND catid = ". (int)$this->_cid;
			$this->_db->setQuery($q);
			$result = $this->_db->loadResult();
			$this->_cid = $result ? $this->_cid : 0;  // Clear cid, if category not assigned to the item
		}
	}
	
	
	/**
	 * Method to get the identifier
	 *
	 * @access	public
	 * @return	int item identifier
	 */
	function getId()
	{
		return $this->_id;
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
		if ( $this->_loadItem() ) {
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
	function &getItem($pk=null, $check_view_access=true, $no_cache=false, $force_version=false)
	{
		$app     = JFactory::getApplication();
		$cparams = $this->_cparams;
		$preview = JRequest::getVar('preview');
		
		// View access done is meant only for FRONTEND !!! ... force it to false
		if ( $app->isAdmin() ) $check_view_access = false;
		
		// Initialise and set primary if it was not given already
		$pk = !empty($pk) ? $pk : $this->_id;
		if (FLEXI_J16GE) {
			$pk = !empty($pk) ? $pk : (int) $this->getState($this->getName().'.id');
		}
		
		// Set new item id, clearing item data, ONLY IF DIFFERENT than existing primary key
		if ($pk != $this->_id) {
			$this->setId($pk);
		}
		
		// --. Try to load existing item
		if ( $pk && $this->_loadItem($no_cache, $force_version) )
		{
			// Successfully loaded existing item, do some extra manipulation of the loaded item ...
			// Extra Steps for Frontend
			if ( !$app->isAdmin() )  {
				// Load item parameters with heritage
				$this->_loadItemParams();
				// Check item viewing access
				if ( $check_view_access ) $this->_check_viewing_access();
			}
		}
		
		// --. Failed to load existing item, or check_view_access indicates not to create a new item object
		else if ( $pk || $check_view_access===2 )
		{
			$msg = JText::sprintf('FLEXI_CONTENT_UNAVAILABLE_ITEM_NOT_FOUND', $pk);
			if (FLEXI_J16GE) throw new Exception($msg, 404); else JError::raiseError(404, $msg);
		}
		
		// --. Initialize new item, currently this succeeds always
		else
		{
			$this->_initItem();
		}
		
		// Extra Steps for Backend
		if ( $app->isAdmin() )  {
			// Set item type id for existing or new item ('typeid' of the JRequest array) ... verifying that the type exists ...
			$this->_item->type_id = $this->getTypesselected()->id;
		}
		
		return $this->_item;
	}
	
	
	/**
	 * Method to load item data
	 *
	 * @access	private
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function _loadItem( $no_cache=false, $force_version=false )
	{
		if(!$this->_id) return false;  // Only try to load existing item
		
		// Cache items retrieved, we can retrieve multiple items, for this purpose 
		// (a) temporarily set JRequest variable -version- to specify loaded version (set to zero to use latest )
		// (b1) use member function function setId($id, $currcatid=0) to change primary key and then call getItem()
		// (b2) or call getItem($pk, $check_view_access=true) passing the item id and maybe also disabling read access checkings, to avoid unwanted messages/errors
		static $items = array();
		if ( $no_cache ) {
			// Clear item to make sure it is reloaded
			$this->_item = null;
		}
		else if ( isset($items[$this->_id]) ) {
			$this->_item = & $items[$this->_id];
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
		$allow_current_version = true;
		$editjf_translations = $cparams->get('editjf_translations', 0);
		
		// *********************************************************************************************************
		// Retrieve item if not already retrieved, null indicates cleared item data, e.g. because of changed item id
		// *********************************************************************************************************
		if ( $this->_item === null ) {
			
			//*****************************************************
			// DECIDE VERSION and GENERATE VERSION RELATED MESSAGES
			//*****************************************************
			
			// Variables controlling the version loading logic
			$loadcurrent = JRequest::getVar('loadcurrent', false, 'request', 'boolean');  // loadcurrent request flag, ignored if version specified
			$preview = JRequest::getVar('preview', false, 'request', 'boolean');   // preview request flag for viewing unapproved version in frontend
			$version = JRequest::getVar('version', 0, 'request', 'int' );          // the item version to load
		
			// -- Decide the version to load: (a) the one specified by request or (b) the current one or (c) the latest one
			$current_version = FLEXIUtilities::getCurrentVersions($this->_id, true, $force=true); // Get current item version
			$last_version    = FLEXIUtilities::getLastVersions($this->_id, true, $force=true);    // Get last version (=latest one saved, highest version id),
		
			// NOTE: Setting version to zero indicates to load the current version from the normal tables and not the versioning table
			if ( !$use_versioning ) {
				// Force version to zero (load current version), when not using versioning mode
				$version = 0;
			} else if ($force_version !== false) {
				$version = $force_version==-1 ? $last_version : $force_version;
			} else if ($version == 0) {
				// version request variable was NOT SET ... We need to decide to load current (version zero) or latest
				
				if ( $app->isAdmin() || ($task=='edit' && $option=='com_flexicontent') ) {
					// Catch cases (a) when we enable versioning mode after an item has been saved in unversioning mode, (b) loadcurrent flag is set
					// in these case we will load CURRENT version instead of the default for the item edit form which is the LATEST (for backend/fontend)
					$version = ($current_version >= $last_version || $loadcurrent) ? 0 : $last_version;
				} else {
					// In frontend item display the current version must be shown unless preview flag is set
					$version = !$preview ? 0 : $last_version;
				}
			} else if ($version == $current_version) {
				// Current version number given, the data from the versioning table should be the same as the data from normal tables
				// we do not force $version to ZERO to allow testing the field data of current version from the versioning table
				if (!$allow_current_version) $version = 0;   // Force zero to retrieve unversioned data
			}
			
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
			
			try
			{
				if ( $app->isAdmin() )
				{
					// **********************
					// Item Retrieval BACKEND
					// **********************
					$item   = $this->getTable('flexicontent_items', '');
					$result = $item->load($this->_id);  // try loading existing item data
					if ($result===false) return false;
				}
				else
				{
					// ***********************
					// Item Retrieval FRONTEND
					// ***********************
					
					// Tables needed to be joined for calculating access
					$joinaccess = '';
					// Extra access columns for main category and content type (item access will be added as 'access')
					$select_access = 'mc.access as category_access, ty.access as type_access';
					
					// Access Flags for: content type, main category, item
					if (FLEXI_J16GE) {
						$aid_arr = $user->getAuthorisedViewLevels();
						$aid_list = implode(",", $aid_arr);
						$select_access .= ', CASE WHEN ty.access IN (0,'.$aid_list.') THEN 1 ELSE 0 END AS has_type_access';
						$select_access .= ', CASE WHEN mc.access IN (0,'.$aid_list.') THEN 1 ELSE 0 END AS has_mcat_access';
						$select_access .= ', CASE WHEN  i.access IN (0,'.$aid_list.') THEN 1 ELSE 0 END AS has_item_access';
					} else {
						$aid = (int) $user->get('aid');
						if (FLEXI_ACCESS) {
							$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gt ON ty.id = gt.axo AND gt.aco = "read" AND gt.axosection = "type"';
							$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gc ON mc.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"';
							$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gi ON  i.id = gi.axo AND gi.aco = "read" AND gi.axosection = "item"';
							$select_access .= ', CASE WHEN (gt.aro IN ( '.$user->gmid.' ) OR ty.access <= '. (int) $aid . ') THEN 1 ELSE 0 END AS has_type_access';
							$select_access .= ', CASE WHEN (gc.aro IN ( '.$user->gmid.' ) OR mc.access <= '. (int) $aid . ') THEN 1 ELSE 0 END AS has_mcat_access';
							$select_access .= ', CASE WHEN (gi.aro IN ( '.$user->gmid.' ) OR  i.access <= '. (int) $aid . ') THEN 1 ELSE 0 END AS has_item_access';
						} else {
							$select_access .= ', CASE WHEN (ty.access <= '. (int) $aid . ') THEN 1 ELSE 0 END AS has_type_access';
							$select_access .= ', CASE WHEN (mc.access <= '. (int) $aid . ') THEN 1 ELSE 0 END AS has_mcat_access';
							$select_access .= ', CASE WHEN ( i.access <= '. (int) $aid . ') THEN 1 ELSE 0 END AS has_item_access';
						}
						$select_access .= ', ';
					}
					
					// SQL date strings, current date and null date
					$nowDate = $db->Quote( FLEXI_J16GE ? JFactory::getDate()->toSql() : JFactory::getDate()->toMySQL() );
					$nullDate	= $db->Quote($db->getNullDate());
					
					// Decide to limit to CURRENT CATEGORY
					$limit_to_cid = $this->_cid ? ' AND rel.catid = '. (int) $this->_cid : ' AND rel.catid = i.catid';
					
					if (FLEXI_J16GE)
					{
						// Initialize query
						$query = $db->getQuery(true);
						
						$query->select('i.*, ie.*');                              // Item basic and extended data
						$query->select($select_access);                              // Access Columns and Access Flags for: content type, main category, item
						if ($version) $query->select('ver.version_id');           // Versioned item viewing
						$query->select('c.id AS catid, i.catid as maincatid');    // Current category id and Main category id
						$query->select(
							'c.title AS category_title, c.alias AS category_alias, c.lft,c.rgt');   // Current category data
						$query->select('ty.name AS typename, ty.alias as typealias');             // Content Type data, and author data
						$query->select('u.name AS author');                                       // Author data
						
						// Rating count, Rating & Score
						$query->select('v.rating_count as rating_count, ROUND( v.rating_sum / v.rating_count ) AS rating, ((v.rating_sum / v.rating_count)*20) as score');
						
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
						$query->select('CASE WHEN badcats.id is null THEN 1 ELSE 0 END AS ancestor_cats_published');
						$subquery = ' (SELECT cat.id as id FROM #__categories AS cat JOIN #__categories AS parent ';
						$subquery .= 'ON cat.lft BETWEEN parent.lft AND parent.rgt ';
						$subquery .= 'WHERE parent.extension = ' . $db->Quote('com_content');
						$subquery .= ' AND parent.published <= 0 GROUP BY cat.id)';
						$query->join('LEFT', $subquery . ' AS badcats ON badcats.id = c.id');
						
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
					}
					else
					{
						// NOTE: version_id is used by field helper file to load the specified version, the reason for left join here is to verify that the version exists
						$version_join =  $version ? ' LEFT JOIN #__flexicontent_versions AS ver ON ver.item_id = i.id AND ver.version_id = '. $db->Quote($version) : '';
						$where	= $this->_buildItemWhere();
						
						$query = 'SELECT i.*, ie.*, '                                   // Item basic and extended data
						. $select_access                                                // Access Columns and Access Flags for: content type, main category, item
						. ($version ? 'ver.version_id,'  : '')                          // Versioned item viewing
						. ' c.id AS catid, i.catid as maincatid,'                       // Current category id and Main category id
						. ' c.published AS catpublished,'                               // Current category published (in J1.6+ this includes all ancestor categories)
						. ' c.title AS category_title, c.alias AS category_alias,'      // Current category data
						. ' ty.name as typename, ty.alias as typealias,'                // Content Type data
						. ' u.name AS author, u.usertype,'                              // Author data
						
						// Rating count, Rating & Score
						. ' v.rating_count as rating_count, ROUND( v.rating_sum / v.rating_count ) AS rating, ((v.rating_sum / v.rating_count)*20) as score,'
						
						// Item and Current Category slugs (for URL)
						. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
						. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug,'
						
						// Publication Scheduled / Expired Flags
						. ' CASE WHEN i.publish_up = '.$nullDate.' OR i.publish_up <= '.$nowDate.' THEN 0 ELSE 1 END as publication_scheduled,'
						. ' CASE WHEN i.publish_down = '.$nullDate.' OR i.publish_down >= '.$nowDate.' THEN 0 ELSE 1 END as publication_expired'
						
						. ' FROM #__content AS i'
						. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
						. ' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
						. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id' . $limit_to_cid
						. ' LEFT JOIN #__categories AS c ON c.id = rel.catid'
						. ' LEFT JOIN #__categories AS mc ON mc.id = i.catid'
						. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
						. ' LEFT JOIN #__content_rating AS v ON i.id = v.content_id'
						. $joinaccess
						. $version_join 
						. $where
						;
					}
					
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
					if ( $db->getErrorNum() ) {
						if (FLEXI_J16GE) throw new Exception($db->getErrorMsg(), 500); else JError::raiseError(500, $db->getErrorMsg());
					}
					//print_r($data); exit;
					
					if(!$data) return false; // item not found, return				
					
					if ($version && !$data->version_id) {
						JError::raiseNotice(10, JText::sprintf('NOTICE: Requested item version %d was not found', $version) );
					}
					
					$item = & $data;
				}
				
				// -- Create the description field called 'text' by appending introtext + readmore + fulltext
				$item->text = $item->introtext;
				$item->text .= JString::strlen( trim($item->fulltext) ) ? '<hr id="system-readmore" />' . $item->fulltext : "";
				
				//echo "<br/>Current version (Frontend Active): " . $item->version;
				//echo "<br/>Version to load: ".$version;
				//echo "<br/><b> *** db title:</b> ".$item->title;
				//echo "<br/><b> *** db text:</b> ".$item->text;
				//echo "<pre>*** item data: "; print_r($item); echo "</pre>"; exit;
				
				// Set number of loaded version, IMPORTANT: zero means load unversioned data
				JRequest::setVar( 'version', $version );
				
				
				// *************************************************************************************************
				// -- Retrieve all active site languages, and create empty item translation objects for each of them
				// *************************************************************************************************
				$nn_content_tbl = FLEXI_J16GE ? 'falang_content' : 'jf_content';
				
				if ( FLEXI_FISH /*|| FLEXI_J16GE*/ )
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
				if ( (FLEXI_FISH /*|| FLEXI_J16GE*/) && $task=='edit' && $option=='com_flexicontent' && $editjf_translations > 0 )
				{
					// -- Try to retrieve all joomfish data for the current item
					$query = "SELECT jfc.language_id, jfc.reference_field, jfc.value, jfc.published "
							." FROM #__".$nn_content_tbl." as jfc "
							." WHERE jfc.reference_table='content' AND jfc.reference_id = {$this->_id} ";
					$db->setQuery($query);
					$translated_fields = $db->loadObjectList();
					
					if ( $editjf_translations < 2 && $translated_fields ) {
						$app->enqueueMessage("Third party Joom!Fish translations detected for current content, but editting Joom!Fish translations is disabled in global configuration", 'message' );
						$app->enqueueMessage("You can enable Joom!Fish translations editting or disable this warning in Global configuration",'message');
					} else {
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
							if ( JString::strlen( trim(@$translation_data->fields->fulltext->value) ) ) {
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
				$item->last_version = $last_version; 
				if ($use_versioning && $version) 
				{
					// Overcome possible group concat limitation
					$query="SET SESSION group_concat_max_len = 9999999";
					$db->setQuery($query);
					$db->query();
					
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
					$pattern = '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
					$tagPos	= preg_match($pattern, $item->text);
					if ($tagPos == 0)	{
						$item->introtext = $item->text;
						$item->fulltext  = '';
					} else 	{
						list($item->introtext, $item->fulltext) = preg_split($pattern, $item->text, 2);
						$item->fulltext = JString::strlen( trim($item->fulltext) ) ? $item->fulltext : '';
					}
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
					$item->tags = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
				}
				
				// -- Retrieve categories field value (if not using versioning)
				if ( $use_versioning && $version ) {
					// Check version value was found, and is valid (above code should have produced an array)
					if ( !isset($item->categories) || !is_array($item->categories) )
						$item->categories = array();
				} else {
					$query = 'SELECT DISTINCT catid FROM #__flexicontent_cats_item_relations WHERE itemid = ' . (int)$this->_id;
					$db->setQuery($query);
					$item->categories = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
				}
				
				// Make sure catid is in categories array
				if ( !in_array($item->catid, $item->categories) ) $item->categories[] = $item->catid;
				
				// 'cats' is an alias of categories
				$item->cats = & $item->categories;
				
				// *********************************************************
				// Retrieve item properties not defined in the model's CLASS
				// *********************************************************
				
				// Category access is retrieved here for J1.6+, for J1.5 we use FLEXIaccess
				if (FLEXI_J16GE) {
					// Get category access for the item's main category, used later to determine viewing of the item
					$query = 'SELECT access FROM #__categories WHERE id = '. (int) $item->catid;
					$db->setQuery($query);
					$item->category_access = $db->loadResult();
				}
				
				// Typecast some properties in case LEFT JOIN returned nulls
				if ( !isset($item->type_access) ) {
					$public_acclevel = !FLEXI_J16GE ? 0 : 1;
					$item->type_access = $public_acclevel;
				}
				$item->typename     = (string) @ $item->typename;
				$item->typealias    = (string) @ $item->typealias;
				$item->rating_count = (int) @ $item->rating_count;
				$item->score        = (int) @ $item->score;
				
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
				
				// ********************************************************
				// Assign to the item data member variable and cache it too
				// ********************************************************
				$this->_item = & $item;
				$items[$this->_id] = & $this->_item;
				
				// ******************************************************************************************************
				// Detect if current version doesnot exist in version table and add it !!! e.g. after enabling versioning 
				// ******************************************************************************************************
				if ( $use_versioning && $current_version > $last_version ) {
					require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'flexicontent.php');
					$fleximodel = new FlexicontentModelFlexicontent();
					$fleximodel->addCurrentVersionData($item->id);
				}
				
				// return true if item was loaded successfully
				return (boolean) $this->_item;
			}
			catch (JException $e)
			{
				
				if ($e->getCode() == 404) {
					// Need to go thru the error handler to allow Redirect to work.
					$msg = $e->getMessage();
					if (FLEXI_J16GE) throw new Exception($msg, 404); else JError::raiseError(404, $msg);
				}
				else {
					$this->setError($e);
					$this->_item = false;
				}
			}
		} else {
			$items[$this->_id] = & $this->_item;
		}
		
		/*$session = JFactory::getSession();
		$postdata = $session->get('item_edit_postdata', array(), 'flexicontent');
		if (count($postdata)) {
			$session->set('item_edit_postdata', null, 'flexicontent');
			// ...
		}*/

		return true;
	}

	
//*************
// BOF of J1.6+
//*************

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
		$this->getItem();
		
		// *********************************************************
		// Prepare item data for being loaded into the form:
		// (a) Convert parameters 'attribs' & 'metadata' to an array
		// (b) Set property 'cid' (form field categories)
		// *********************************************************
		
		$this->_item->itemparams = FLEXI_J16GE ? new JRegistry() : new JParameter("");
		
		if ($this->_id) {
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
			$attribs = $metadata = '';
			$this->_item->attribs = array();
			$this->_item->metadata = array();
		}
		
		// Set item property 'cid' (form field categories is named cid)
		$this->_item->cid = $this->_item->categories;
		
		// ****************************************************************************
		// Load item data into the form and restore the changes done above to item data
		// ****************************************************************************
		$form = $this->loadForm('com_flexicontent.item', 'item', array('control' => 'jform', 'load_data' => $loadData));
		if (empty($form)) {
			return false;
		}
		$this->_item->attribs = $attribs;
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
		if ( !$this->canEditState( (object)$data ) )
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
				// skip new items in frontend to allow override via menu (auto-publish), menu override must be check during store
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

		return $form;
	}
	
	
	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return	mixed	The data for the form.
	 * @since	1.6
	 */
	protected function loadFormData() {
		// Check the session for previously entered form data.
		$data = JFactory::getApplication()->getUserState('com_flexicontent.edit.'.$this->getName().'.data', array());

		if (empty($data)) {
			$data = $this->getItem();
		}

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
		$asset	= 'com_content.article.'.$this->_id;
		$permission	= FlexicontentHelperPerm::getPerm();  // Global component permissions
		
		// NOTE, technically in J1.6+ a guest may edit able to edit/delete an item, so we commented out the guest check bellow,
		// this applies for creating item, but flexicontent already allows create to guests via menu item too, so no check there too
		
		// Compute CREATE access permissions.
		if ( !$this->_id ) {
			
			// Check if general create permission is missing, NOTE: THIS CAN BE SOFT DENY
			// ... so we do need to check category 'create' privilege for all categories !!
			/*if ( !$user->authorise('core.create', 'com_flexicontent') ) {
				$iparams_extra->set('access-create', false);
				return $iparams_extra;  // New item, so do not calculate EDIT, DELETE and VIEW access
			}*/
			
			// Check that user can create item in at least one category ... this check is not wasted,
			// since joomla will cache it and use it later during creation of allowed Category Tree
			$canCreate = $user->authorise('core.create', 'com_flexicontent');
			if ($canCreate === NULL) {
				$allowedcats = FlexicontentHelperPerm::getAllowedCats($user, array('core.create')
					, $require_all = true, $check_published = true, $specific_catids = false, $find_first = true
				);
				$canCreate = count($allowedcats) > 0;
			}
			$iparams_extra->set('access-create', canCreate);
			return $iparams_extra;  // New item, so do not calculate EDIT, DELETE and VIEW access
		}
		
		// Not a new item retrieve item if not already done
		if ( empty($this->_item) ) {
			$this->_item = $this->getItem();
		}

		// Compute EDIT access permissions.
		if ( $this->_id ) {
			// first check edit permission on the item
			if ($user->authorise('core.edit', $asset)) {
				$iparams_extra->set('access-edit', true);
			}
			// no edit permission, check if edit.own is available for this item
			else if ( $user->authorise('core.edit.own', $asset) && $user->get('id') == $this->_item->created_by  /* && !$user->get('guest') */ )
			{
				$iparams_extra->set('access-edit', true);
			}
		}

		// Compute EDIT STATE access permissions.
		if ( $this->_id ) {
			// first check edit.state permission on the item
			if ($user->authorise('core.edit.state', $asset)) {
				$iparams_extra->set('access-edit-state', true);
			}
			// no edit.state permission, check if edit.state.own is available for this item
			else if ( $user->authorise('core.edit.state.own', $asset) && $user->get('id') == $this->_item->created_by  /* && !$user->get('guest') */ )
			{
				$iparams_extra->set('access-edit-state', true);
			}
		}
		
		// Compute DELETE access permissions.
		if ( $this->_id ) {
		
			// first check delete permission on the item
			if ($user->authorise('core.delete', $asset)) {
				$iparams_extra->set('access-delete', true);
			}
			// no delete permission, chekc delete.own permission if the item is owned by the user
			else if ( $user->authorise('core.delete.own', $asset) && $user->get('id') == $this->_item->created_by  /* && !$user->get('guest') */ )
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
			$groups = $user->getAuthorisedViewLevels();
			
			if ( !isset($this->_item->has_item_access) ) {
				$this->_item->has_item_access = in_array($this->_item->access, $groups);
			}
			if ( !isset($this->_item->has_mcat_access) ) {
				$no_mcat_info = $this->_item->catid == 0 || !isset($this->_item->category_access) || $this->_item->category_access === null;
				$this->_item->has_mcat_access = $no_mcat_info || in_array($this->_item->category_access, $groups);
			}
			if ( !isset($this->_item->has_type_access) ) {
				$no_type_info = $this->_item->type_id == 0 || !isset($this->_item->type_access) || $this->_item->type_access === null;
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

//*************
// EOF of J1.6+
//*************

//*************
// BOF of J1.5
//*************

	/**
	 * Method (for J1.5) to check if the user can add an item anywhere
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function canAdd()
	{
		$user	= JFactory::getUser();

		if (FLEXI_ACCESS && ($user->gid < 25))
		{
			$canSubmit = FAccess::checkComponentAccess('com_content', 'submit', 'users', $user->gmid);
			$canAdd = FAccess::checkAllContentAccess('com_content','add','users',$user->gmid,'content','all');
			if 	(!$canSubmit && !$canAdd) return false;
		} else {
			$canAdd	= $user->authorize('com_content', 'add', 'content', 'all');
			if (!$canAdd) return false;
		}
		return true;
	}

	/**
	 * Method (for J1.5) to check if the user can edit the item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function canEdit()
	{
		$user	= JFactory::getUser();
		
		if (!$this->_loadItem() || $user->gid >= 25) {
			return true;
		} else if (FLEXI_ACCESS) {
			// This should not be used, as it bypasses individual item rights
			//$canEditAll			= FAccess::checkAllContentAccess('com_content','edit','users',$user->gmid,'content','all');
			//$canEditOwnAll	= FAccess::checkAllContentAccess('com_content','editown','users',$user->gmid,'content','all');
			if ($this->_item->id && $this->_item->catid)
			{
				$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $this->_item->id, $this->_item->catid);
				$canEdit 	= in_array('edit', $rights) /*|| $canEditAll*/;
				$canEditOwn	= ( in_array('editown', $rights) /*|| $canEditOwnAll*/ ) && $this->_item->created_by == $user->get('id');
				if (!$canEdit && !$canEditOwn) return false;
			}
		} else {
			$canEdit= $user->authorize('com_content', 'edit', 'content', 'all');
			if (!$canEdit) return false;
		}
		return true;
	}
	
	
//*************
// EOF of J1.5
//*************
	
	
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
			if (FLEXI_J16GE)
				$type->allowed = ! $type->itemscreatable || $user->authorise('core.create', 'com_flexicontent.type.' . $type->id);
			else if (FLEXI_ACCESS && $user->gid < 25)
				$type->allowed = ! $type->itemscreatable || FAccess::checkAllContentAccess('com_content','submit','users', $user->gmid, 'type', $type->id);
			else
				$type->allowed = 1;
			
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
		if ( empty($item) ) $item = & $this->_item;
		$user = JFactory::getUser();
		$isOwner = !empty($item->created_by) && ( $item->created_by == $user->get('id') );
		
		if (FLEXI_J16GE)
		{
			if ( !empty($item->id) )
			{
				// Existing item, use item specific permissions
				$asset = 'com_content.article.' . $item->id;
				return $user->authorise('core.edit.state', $asset) || ($user->authorise('core.edit.state.own', $asset) && $isOwner);
			}
			elseif ( $check_cat_perm && !empty($item->catid) )
			{
				// *** New item *** with main category set
				$cat_asset = 'com_content.category.' . (int)@ $item->catid;
				return $user->authorise('core.edit.state', $cat_asset) || ($user->authorise('core.edit.state.own', $cat_asset) && $isOwner);
			}
			else
			{
				// *** New item *** get general edit/publish/delete permissions
				return $user->authorise('core.edit.state', 'com_flexicontent') || $user->authorise('core.edit.state.own', 'com_flexicontent');
			}
		}
		else if (FLEXI_ACCESS)
		{
			if ( !empty($item->id) )
			{
				// Existing item, use item specific permissions
				$rights = FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
				return ($user->gid < 25) ? ( (in_array('publishown', $rights) && $isOwner) || (in_array('publish', $rights)) ) : 1;
			}
			elseif ( $check_cat_perm && !empty($item->catid) )
			{
				// *** New item *** with main category set
				$rights = FAccess::checkAllCategoryAccess('com_content', 'users', $user->gmid, $item->catid);
				return ($user->gid < 25) ? ( (in_array('publishown', $rights) && $isOwner) || (in_array('publish', $rights)) ) : 1;
			}
			else
			{
				// *** New item *** get general edit/publish/delete permissions
				$canPublishAll 		= FAccess::checkAllContentAccess('com_content','publish','users',$user->gmid,'content','all');
				$canPublishOwnAll	= FAccess::checkAllContentAccess('com_content','publishown','users',$user->gmid,'content','all');
				return ($user->gid < 25) ? $canPublishAll || $canPublishOwnAll : 1;
			}
		}
		else
		{
			// J1.5 permissions with no FLEXIaccess are only general, no item specific permissions
			return ($user->gid >= 21);
		}
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
			
			$public_accesslevel  = !FLEXI_J16GE ? 0 : 1;
			$default_accesslevel = FLEXI_J16GE ? $app->getCfg( 'access', $public_accesslevel ) : $public_accesslevel;
			
			// Decide default publication state. NOTE this will only be used if user has publish privilege, otherwise items
			// will be forced to (a) pending_approval state for NEW ITEMS and (b) to item's current state for EXISTING ITEMS
			$pubished_state = 1;  $draft_state = -4;  $pending_approval_state = -3;
			if ( $app->isAdmin() ) {
				$default_state = $cparams->get('new_item_state', $pubished_state);     // Use the configured setting for backend items
			} else {
				$default_state = $cparams->get('new_item_state_fe', $pubished_state);  // Use the configured setting for frontend items
			}
			
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
			$item->type_id      = JRequest::getVar('typeid', 0, '', 'int');  // Get default type from HTTP request
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
			$item->attribs      = null;
			$item->metadata     = null;
			$item->access       = $default_accesslevel;
			$item->state        = $default_state;
			$item->mask         = null;  // deprecated do not use
			$item->images       = null;
			$item->urls         = null;
			$item->language     = FLEXI_J16GE ? '*' : flexicontent_html::getSiteDefaultLang();
			$item->lang_parent_id = 0;
			$item->search_index = null;
			$item->parameters   = clone ($cparams);   // Assign component parameters, merge with menu item (for frontend)
			
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

		// Load global parameters
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
		if ( $this->_loadItem() )
		{
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
		if ($pk) {
			$item = JTable::getInstance('flexicontent_items', '');
			return $item->checkin($pk);
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
		$this->setError( FLEXI_J16GE ? $tbl->getError() : JText::_("FLEXI_ALERT_CHECKOUT_FAILED") );
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
		// Check for request forgeries
		if ( !JFactory::getApplication()->isAdmin() ) {
			// For flexible usage, e.g. when it is called by the new IMPORT TASK
			JRequest::checkToken() or jexit( 'Invalid Token' );
		}
		
		
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
		$tz_offset = FLEXI_J16GE ? $user_zone : $site_zone ;
		
		// Sanitize id and approval flag as integers
		$data['vstate'] = (int)$data['vstate'];
		$data['id']     = (int)$data['id'];
		$isnew = ! $data['id'];
		
		
		// *****************************************
		// Get an item object and load existing item
		// *****************************************
		
		// Get an empty item model (with default values)
		$item = $this->getTable('flexicontent_items', '');
		
		// ... existing items
		if ( !$isnew ) {
			// Load existing item into the empty item model
			$item->load( $data['id'] );
			
			// Get item's assigned categories
			$query = 'SELECT DISTINCT catid FROM #__flexicontent_cats_item_relations WHERE itemid = ' . (int)$this->_id;
			$db->setQuery($query);
			$item->categories = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
			
			// We need to fake joomla's states ... when triggering the before save content event
			$fc_state = $item->state;
			if ( in_array($fc_state, array(1,-5)) ) $jm_state = 1;           // published states
			else if ( in_array($fc_state, array(0,-3,-4)) ) $jm_state = 0;   // unpublished states
			else $jm_state = $fc_state;                                      // trashed & archive states
			
			// Frontend SECURITY concern: ONLY allow to set item type for new items !!!
			if( !$app->isAdmin() ) 
				unset($data['type_id']);
		} else {
			$item->categories = array();
		}
		
		
		// *********************************
		// Check and correct given item DATA
		// *********************************
		
		// tags and cats will need some manipulation so we retieve them
		$tags = $this->formatToArray( @ $data['tag'] );
		$cats = $this->formatToArray( @ $data['cid'] );
		$featured_cats = $this->formatToArray( @ $data['featured_cid'] );
		unset($data['tag']);  unset($data['cid']);  unset($data['featured_cid']);
		
		// Make tags unique
		$tags = array_unique($tags);
		
		// Auto-assign a not set main category, to be the first out of secondary categories, 
		if ( empty($data['catid']) && !empty($cats[0]) ) {
			$data['catid'] = $cats[0];
		}
		
		$cats_indexed = array_flip($cats);
		// Add the primary cat to the array if it's not already in
		if ( @ $data['catid'] && !isset($cats_indexed[$data['catid']]) ) {
			$cats[] = $data['catid'];
		}
		
		// Add the primary cat to the array if it's not already in
		if ( !empty($featured_cats) ) foreach ( $featured_cats as $featured_cat ) {
			if (@ $featured_cat && !isset($cats_indexed[$featured_cat]) )  $cats[] = $featured_cat;
		}
		
		
		// *****************************
		// Retrieve author configuration
		// *****************************
		$db->setQuery('SELECT author_basicparams FROM #__flexicontent_authors_ext WHERE user_id = ' . $user->id);
		if ( $authorparams = $db->loadResult() )
			$authorparams = FLEXI_J16GE ? new JRegistry($authorparams) : new JParameter($authorparams);
		
		// At least one category needs to be assigned
		if (!is_array( $cats ) || count( $cats ) < 1) {
			
			$this->setError(JText::_('FLEXI_OPERATION_FAILED') .", ". JText::_('FLEXI_REASON') .": ". JText::_('FLEXI_SELECT_CATEGORY'));
			return false;
			
		// Check more than allowed categories
		} else {
			
			// Get author's maximum allowed categories per item and set js limitation
			$max_cat_assign = !$authorparams ? 0 : intval($authorparams->get('max_cat_assign',0));
			
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
		
		// Set back the altered categories and tags to the form data
		$data['categories']  = $cats;  // Set it to real name of field: 'categories' INSTEAD OF 'cid'
		$data['tags']        = $tags;  // Set it to real name of field: 'tags'       INSTEAD OF 'tag'
		
		// Reconstruct (main)text field if it has splitted up e.g. to seperate editors per tab
		if (@$data['text'] && is_array($data['text'])) {
			$data['text'][0] .= (preg_match('#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i', $data['text'][0]) == 0) ? ("\n".'<hr id="system-readmore" />') : "" ;
			$tabs_text = '';
			foreach($data['text'] as $tab_text) {
				$tabs_text .= $tab_text;
			}
			$data['text'] = & $tabs_text;
		}
		
		// The text field is stored in the db as to seperate fields: introtext & fulltext
		// So we search for the {readmore} tag and split up the text field accordingly.
		$pattern = '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
		$tagPos	= preg_match($pattern, @ $data['text']);
		if ($tagPos == 0)	{
			$data['introtext'] = @ $data['text'];
			$data['fulltext']  = '';
		} else 	{
			list($data['introtext'], $data['fulltext']) = preg_split($pattern, $data['text'], 2);
			$data['fulltext'] = JString::strlen( trim($data['fulltext']) ) ? $data['fulltext'] : '';
		}
		
		
		// *******************************************************************************
		// Handle Parameters: attribs & metadata, merging POST values into existing values
		// Keys that are not set will not be set, thus the previous value is maintained
		// *******************************************************************************
		
		// Retrieve (a) item parameters (array PARAMS or ATTRIBS ) and (b) item metadata (array METADATA or META )
		if ( !FLEXI_J16GE ) {
			$params   = $this->formatToArray( @ $data['params'] );
			$metadata = $this->formatToArray( @ $data['meta'] );
			unset($data['params']);
			unset($data['meta']);
		} else {
			$params   = $this->formatToArray( @ $data['attribs'] );
			$metadata = $this->formatToArray( @ $data['metadata'] );
			unset($data['attribs']);
			unset($data['metadata']);
		}
		
		// Merge  (form posted)  item attributes and metadata parameters
		$this->mergeAttributes($item, $params, $metadata);
		
		
		// *******************************************************
		// Retrieve submit configuration for new items in frontend
		// *******************************************************
		if ( $app->isSite() && $isnew && !empty($data['submit_conf']) ) {
			$h = $data['submit_conf'];
			$session = JFactory::getSession();
			$item_submit_conf = $session->get('item_submit_conf', array(),'flexicontent');
			$submit_conf = @ $item_submit_conf[$h] ;
			
			$autopublished    = @ $submit_conf['autopublished'];
			$overridecatperms = @ $submit_conf['overridecatperms'];
			if ( $autopublished) {
				// Dates forced during autopublishing
				if ( @ $submit_conf['autopublished_up_interval'] ) {
					if (FLEXI_J16GE) {
						$publish_up_date = JFactory::getDate(); // Gives editor's timezone by default
						$publish_up_date->modify('+ '.$submit_conf['autopublished_up_interval'].' minutes');
						$publish_up_forced = $publish_up_date->toSql();
					} else {
						$publish_up_date = new DateTime(JHTML::_('date', JFactory::getDate()->toFormat(), '%Y-%m-%d %H:%M:%S'));
						$publish_up_date->modify('+ '.$submit_conf['autopublished_up_interval'].' minutes');
						$publish_up_forced = $publish_up_date->format('Y-m-d H:i:s');
					}
				}
				if ( @ $submit_conf['autopublished_down_interval'] ) {
					if (FLEXI_J16GE) {
						$publish_down_date = JFactory::getDate(); // Gives editor's timezone by default
						$publish_down_date->modify('+ '.$submit_conf['autopublished_down_interval'].' minutes');
						$publish_down_forced = $publish_down_date->toSql();
					} else {
						$publish_down_date = new DateTime(JHTML::_('date', JFactory::getDate()->toFormat(), '%Y-%m-%d %H:%M:%S'));
						$publish_down_date->modify('+ '.$submit_conf['autopublished_down_interval'].' minutes');
						$publish_down_forced = $publish_down_date->format('Y-m-d H:i:s');
					}
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
		if ($overridecatperms)
		{
			$allowed_cid = @ $submit_conf['cids'];
		}
		else
		{
			if (FLEXI_J16GE || FLEXI_ACCESS) {
				$allowed_cid 	= FlexicontentHelperPerm::getAllowedCats($user, $actions_allowed = array('core.create'), $require_all=true);
			}
		}
		
		if ( isset($allowed_cid) ) {
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
		$canEditState = $this->canEditState( $item, $check_cat_perm=true );
		
		// Restore old main category & creator (owner) (in case following code chooses to keep them)
		$item->created_by = $old_created_by;
		$item->catid      = $old_catid;
		
		// If cannot edit state prevent user from changing state related parameters
		if ( !$canEditState )
		{
			$data['vstate'] = 1;
			if (!FLEXI_J16GE) {
				// Behaviour is different in J1.5, it requires edit instead of edit state
				//unset( $data['details']['publish_up'] );
				//unset( $data['details']['publish_down'] );
				//unset( $data['ordering'] );
			} else {
				unset( $data['featured'] );
				unset( $data['publish_up'] );
				unset( $data['publish_down'] );
				unset( $data['ordering'] );
			}
			
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
		$isSuperAdmin = FLEXI_J16GE ? $user->authorise('core.admin', 'root.1') : ($user->gid >= 25);
		
		// Prevent frontend user from changing the item owner and creation date unless they are super admin
		if ( $app->isSite() && !$isSuperAdmin )
		{
			if (!FLEXI_J16GE) {
				if ($isnew)  $data['details']['created_by'] = $user->get('id');
				else         unset( $data['details']['created_by'] );
				unset( $data['details']['created'] );
				unset( $data['details']['created_by_alias'] );
			} else {
				if ($isnew)  $data['created_by'] = $user->get('id');
				else         unset( $data['created_by'] );
				if ( !$user->authorise('flexicontent.editcreationdate', 'com_flexicontent') )
					unset( $data['created'] );
				unset( $data['created_by_alias'] );
			}
		}
		
		
		// ***********************************************************
		// SECURITY concern: Check form tampering of allowed languages
		// ***********************************************************
		$allowed_langs = !$authorparams ? null : $authorparams->get('langs_allowed',null);
		$allowed_langs = !$allowed_langs ? null : FLEXIUtilities::paramToArray($allowed_langs);
		if (!$isnew && $allowed_langs) $allowed_langs[] = $item->language;
		if ( $allowed_langs && isset($data['language']) && !in_array($data['language'], $allowed_langs) ) {
			$app->enqueueMessage('You are not allowed to assign language: '.$data['language'].' to Content Items', 'warning');
			unset($data['language']);
			if ($isnew) return false;
		}
		
		
		// ************************************************
		// Bind given item DATA and PARAMETERS to the model
		// ************************************************
		
		// Bind the given data to the items
		if ( !$item->bind($data) ) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		
		// Bind posted publication details (parameters) to the model for J1.5
		if (!FLEXI_J16GE) {
			$details = $this->formatToArray( @ $data['details'] );
			$item->bind($details);
		}
		
		
		// **************************************
		// Check and correct core item properties
		// **************************************
			
		// -- Modification Date and Modifier, (a) new item gets null modification date and (b) existing item get the current date
		if ($isnew) {
			$item->modified    = $nullDate;
			$item->modified_by = 0;
		} else {
			$datenow = JFactory::getDate();
			$item->modified    = FLEXI_J16GE ? $datenow->toSql() : $datenow->toMySQL();
			$item->modified_by = $user->get('id');
		}
			
		// -- Creator, if this is not already set, will be the current user or administrator if current user is not logged
		if ( !$item->created_by ) {
			$item->created_by = $user->get('id') ? $user->get('id') : JFactory::getUser( 'admin' )->get('id');
		}
		
		// -- Creation Date
		if ($item->created && JString::strlen(trim( $item->created )) <= 10) {
			$item->created 	.= ' 00:00:00';
		}
		if (FLEXI_J16GE) {
			$date = JFactory::getDate($item->created);
			$date->setTimeZone( new DateTimeZone( $tz_offset ) );    // J2.5: Date from form field is in user's timezone
		} else {
			$date = JFactory::getDate($item->created, $tz_offset);  // J1.5: Date from form field is in site's default timezone
		}
		$item->created = FLEXI_J16GE ? $date->toSql() : $date->toMySQL();
			
		// -- Publish UP Date
		if ($item->publish_up && JString::strlen(trim( $item->publish_up )) <= 10) {
			$item->publish_up 	.= ' 00:00:00';
		}
		if (FLEXI_J16GE) {
			$date = JFactory::getDate($item->publish_up);
			$date->setTimeZone( new DateTimeZone( $tz_offset ) );       // J2.5: Date from form field is in user's timezone
		} else {
			$date = JFactory::getDate($item->publish_up, $tz_offset);  // J1.5: Date from form field is in site's default timezone
		}
		$item->publish_up = FLEXI_J16GE ? $date->toSql() : $date->toMySQL();

		// -- Publish Down Date
		if (trim($item->publish_down) == JText::_('FLEXI_NEVER') || trim( $item->publish_down ) == '')
		{
			$item->publish_down = $nullDate;
		}
		else if ($item->publish_down != $nullDate)
		{
			if ( JString::strlen(trim( $item->publish_down )) <= 10 ) {
				$item->publish_down .= ' 00:00:00';
			}
			if (FLEXI_J16GE) {
				$date = JFactory::getDate($item->publish_down);
				$date->setTimeZone( new DateTimeZone( $tz_offset ) );         // J2.5: Date from form field is in user's timezone
			} else {
				$date = JFactory::getDate($item->publish_down, $tz_offset);  // J1.5: Date from form field is in site's default timezone
			}
			$item->publish_down = FLEXI_J16GE ? $date->toSql() : $date->toMySQL();
		}
		
		// auto assign the section
		if (!FLEXI_J16GE)  $item->sectionid = FLEXI_SECTION;
		
		// For new items get next available ordering number
		if ($isnew) {
			$item->ordering = $item->getNextOrder();
		}
		
		// auto assign the default language if not set
		$default_language = FLEXI_J16GE ? '*' : flexicontent_html::getSiteDefaultLang() ;
		$item->language   = $item->language ? $item->language : $default_language ;
		
		// Ignore language parent id if item language is site's (content) default language, and for language 'ALL'
		if ( substr($item->language, 0,2) == substr(flexicontent_html::getSiteDefaultLang(), 0,2) || $item->language=='*' ) {
			$lang_parent_id = $item->lang_parent_id;
			$item->lang_parent_id = $isnew ? 0 : $item->id;
			if ( $item->lang_parent_id != $lang_parent_id && $lang_parent_id ) {
				$app->enqueueMessage(JText::_('FLEXI_ORIGINAL_CONTENT_WAS_IGNORED'), 'message' );
			}
		}
		
		
		// ****************************************************************************************************************
		// Get version info, force version approval ON is versioning disabled, and decide new item's current version number
		// ****************************************************************************************************************
		$last_version = FLEXIUtilities::getLastVersions($item->id, true);
		$current_version = FLEXIUtilities::getCurrentVersions($item->id, true);
		
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
		if (!$isnew) { $db->setQuery( 'UPDATE #__content SET state = '. $jm_state .' WHERE id = '.$item->id );  $db->query(); }
	  JRequest::setVar('view', 'article');	  JRequest::setVar('option', 'com_content');
		
		if ( $print_logging_info ) $start_microtime = microtime(true);
		if (FLEXI_J16GE) $result = $dispatcher->trigger($this->event_before_save, array('com_content.article', &$item, $isnew));
		else             $result = $dispatcher->trigger('onBeforeContentSave', array(&$item, $isnew));
		if ( $print_logging_info ) $fc_run_times['onContentBeforeSave_event'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		// Reverse compatibility steps
		if (!$isnew) { $db->setQuery( 'UPDATE #__content SET state = '. $fc_state .' WHERE id = '.$item->id );  $db->query(); }
		JRequest::setVar('view', $view);	  JRequest::setVar('option', 'com_flexicontent');
		
		if (in_array(false, $result, true))	{ $this->setError($item->getError()); return false; }    // cancel item save
		
		
		
		// ************************************************************************************************************
		// IF new item, create it before saving the fields (and constructing the search_index out of searchable fields)
		// ************************************************************************************************************
		if ( $print_logging_info ) $start_microtime = microtime(true);
		if( $isnew )
		{
			$this->applyCurrentVersion($item, $data, $createonly=true);
		} else {
			// Make sure the data of the model are correct,
			// e.g. a getForm() used to validate input data may have set an empty item and empty id
			// e.g. type_id of item may have been altered by authorized users
			$this->_id   = $item->id;
			$this->_item = & $item;
		}
		if ( $print_logging_info ) $fc_run_times['item_store_core'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		
		// ****************************************************************************
		// Save fields values to appropriate tables (versioning table or normal tables)
		// NOTE: This allow canceling of item save operation, if 'abort' is returned
		// ****************************************************************************
		$files  = JRequest::get( 'files', JREQUEST_ALLOWRAW );
		$result = $this->saveFields($isnew, $item, $data, $files);
		$version_approved = $isnew || $data['vstate']==2;
		if( $result==='abort' ) {
			if ($isnew) {
				if (FLEXI_J16GE) {
					$db->setQuery('DELETE FROM #__assets WHERE id = (SELECT asset_id FROM #__content WHERE id='.$item->id.')');
					$db->query();
				} else if (FLEXI_ACCESS) {
					$db->setQuery('DELETE FROM #__flexiaccess_acl WHERE acosection = `com_content` AND axosection = `item` AND axo ='.$item->id);
					$db->query();
				}
				$db->setQuery('DELETE FROM #__content WHERE id ='.$item->id);
				$db->query();
				$db->setQuery('DELETE FROM #__flexicontent_items_ext WHERE item_id='.$item->id);
				$db->query();
				
				$this->setId(0);
				$this->setError( $this->getError().' '.JText::_('FLEXI_NEW_ITEM_NOT_CREATED') );
			} else {
				$this->setError( $this->getError().' '.JText::_('FLEXI_EXISTING_ITEM_NOT_SAVED') );
			}
			
			// Set form to reload posted data
			/*$session = JFactory::getSession();
			$session->set('item_edit_postdata', $data, 'flexicontent');*/
			
			return false;
		}
		
		
		// ***************************************************************
		// ITEM DATA SAVED:  EITHER new, OR approving current item version
		// ***************************************************************
		if ( $version_approved )
		{
			// *****************************
			// Save item to #__content table
			// *****************************
			if ( $print_logging_info ) $start_microtime = microtime(true);
			if( !$this->applyCurrentVersion($item, $data) ) return false;
			if ( $print_logging_info ) @$fc_run_times['item_store_core'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			//echo "<pre>"; var_dump($data); exit();
			
			
			// ***************************
			// Update Joomla Featured FLAG
			// ***************************
			if (FLEXI_J16GE) $this->featured(array($item->id), $item->featured);
			
			
			// *****************************************************************************************************
			// Trigger Event 'onAfterContentSave' (J1.5) OR 'onContentAfterSave' (J2.5 ) of Joomla's Content plugins
			// *****************************************************************************************************
			if ( $print_logging_info ) $start_microtime = microtime(true);
			
			// Some compatibility steps
		  JRequest::setVar('view', 'article');
			JRequest::setVar('option', 'com_content');
		  
			if (FLEXI_J16GE) $dispatcher->trigger($this->event_after_save, array('com_content.article', &$item, $isnew));
			else             $dispatcher->trigger('onAfterContentSave', array(&$item, $isnew));
			
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
			// Warn editor that his/her changes will need approval to before becoming visible
			if ( $canEditState )
				JError::raiseNotice(11, JText::_('FLEXI_SAVED_VERSION_WAS_NOT_APPROVED_NOTICE') );
			else
				JError::raiseNotice(10, JText::_('FLEXI_SAVED_VERSION_MUST_BE_APPROVED_NOTICE') );
			
			// Set modifier and modification time (as if item has been saved), so that we can use this information for updating the versioning tables
			$datenow = JFactory::getDate();
			$item->modified			= FLEXI_J16GE ? $datenow->toSql() : $datenow->toMySQL();
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

		if ($vcount > ($vmax+1)) {
			$deleted_version = FLEXIUtilities::getFirstVersion($item->id, $vmax, $current_version);
			$query = 'DELETE'
					.' FROM #__flexicontent_items_versions'
					.' WHERE item_id = ' . (int)$item->id
					.' AND version <' . $deleted_version
					.' AND version!=' . (int)$current_version
					;
			$this->_db->setQuery($query);
			$this->_db->query();

			$query = 'DELETE'
					.' FROM #__flexicontent_versions'
					.' WHERE item_id = ' . (int)$item->id
					.' AND version_id <' . $deleted_version
					.' AND version_id!=' . (int)$current_version
					;
			$this->_db->setQuery($query);
			$this->_db->query();
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
	function saveFields($isnew, &$item, &$data, &$files)
	{
		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();
		$dispatcher = JDispatcher::getInstance();
		$cparams    = $this->_cparams;
		$use_versioning = $cparams->get('use_versioning', 1);
		$print_logging_info = $cparams->get('print_logging_info');
		$last_version = FLEXIUtilities::getLastVersions($item->id, true);
		$mval_query = true;
		
		if ( $print_logging_info ) global $fc_run_times;
		if ( $print_logging_info ) $start_microtime = microtime(true);
		
		
		//*********************************
		// Checks for untranslatable fields
		//*********************************
		
		// CASE 1. Check if saving an item that translates an original content in site's default language
		// ... Decide whether to retrieve field values of untranslatable fields from the original content item
		$enable_translation_groups = $cparams->get('enable_translation_groups');
		$is_content_default_lang = substr(flexicontent_html::getSiteDefaultLang(), 0,2) == substr($item->language, 0,2);
		$get_untraslatable_values = $enable_translation_groups && !$is_content_default_lang && $item->lang_parent_id && $item->lang_parent_id!=$item->id;
		
		// CASE 2. Check if saving an original content item (item's language is site default language)
		// ... Get item ids of translating items
		if ($is_content_default_lang && $this->_id) {
			$query = 'SELECT ie.item_id'
				.' FROM #__flexicontent_items_ext as ie'
				.' WHERE ie.lang_parent_id = ' . (int)$this->_id
				.'  AND ie.item_id <> '.(int)$this->_id; // DO NOT include the item itself in associated translations !!
			$this->_db->setQuery($query);
			$assoc_item_ids = FLEXI_J16GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();
		}
		if (empty($assoc_item_ids)) $assoc_item_ids = array();
		
		
		// ***************************************************************************************************************************
		// Get item's fields ... and their values (for untranslatable fields the field values from original content item are retrieved
		// ***************************************************************************************************************************		
		$fields = $this->getExtrafields($force=true, $get_untraslatable_values ? $item->lang_parent_id : 0);
		
		
		// ******************************************************************************************************************
		// Loop through Fields triggering onBeforeSaveField Event handlers, this was seperated from the rest of the process
		// to give chance to ALL fields to check their DATA and cancel item saving process before saving any new field values
		// ******************************************************************************************************************
		$searchindex = array();
		if ($fields)
		{
			foreach($fields as $field)
			{
				// Set vstate property into the field object to allow this to be changed be the before saving  field event handler
				$field->item_vstate = $data['vstate'];
				
				if (FLEXI_J16GE)
					$is_editable = !$field->valueseditable || $user->authorise('flexicontent.editfieldvalues', 'com_flexicontent.field.' . $field->id);
				else if (FLEXI_ACCESS && $user->gid < 25)
					$is_editable = !$field->valueseditable || FAccess::checkAllContentAccess('com_content','submit','users', $user->gmid, 'field', $field->id);
				else
					$is_editable = 1;
				
				// FORM HIDDEN FIELDS (FRONTEND/BACKEND) AND (ACL) UNEDITABLE FIELDS: maintain their DB value ...
				if (
					( $app->isSite() && ($field->formhidden==1 || $field->formhidden==3 || $field->parameters->get('frontend_hidden')) ) ||
					( $app->isAdmin() && ($field->formhidden==2 || $field->formhidden==3 || $field->parameters->get('backend_hidden')) ) ||
					!$is_editable
				) {
					$postdata[$field->name] = $field->value;
					
				// UNTRANSLATABLE (CUSTOM) FIELDS: maintain their DB value ...
				} else if ( $get_untraslatable_values && $field->untranslatable ) {
					$postdata[$field->name] = $field->value;
					
				// CORE FIELDS: if not set maintain their DB value ...
				} else if ($field->iscore) {
					$postdata[$field->name] = @$data[$field->name];
					if ( is_array($postdata[$field->name]) && !count($postdata[$field->name])  ||  !is_array($postdata[$field->name]) && !strlen(trim($postdata[$field->name])) ) {
						$postdata[$field->name] = $field->value;
					}
					
				// OTHER CUSTOM FIELDS (not hidden and not untranslatable)
				} else {
					$postdata[$field->name] = !FLEXI_J16GE  ?   @$data[$field->name]  :  @$data['custom'][$field->name];
				}
				
				// Unserialize values already serialized values, e.g. (a) if current values used are from DB or (b) are being imported from CSV file
				$postdata[$field->name] = !is_array($postdata[$field->name]) ? array($postdata[$field->name]) : $postdata[$field->name];
				//echo "<b>{$field->field_type}</b>: <br/> <pre>".print_r($postdata[$field->name], true)."</pre>\n";
				foreach ($postdata[$field->name] as $i => $postdata_val) {
					if ( @unserialize($postdata_val)!== false || $postdata_val === 'b:0;' ) {
						$postdata[$field->name][$i] = unserialize($postdata_val);
					}
				}
				
				// Trigger plugin Event 'onBeforeSaveField'
				$fieldname = $field->iscore ? 'core' : $field->field_type;
				$result = FLEXIUtilities::call_FC_Field_Func($fieldname, 'onBeforeSaveField', array( &$field, &$postdata[$field->name], &$files[$field->name], &$item ));
				if ($result===false) {
					// Field requested to abort item saving
					$this->setError( JText::sprintf('FLEXI_FIELD_VALUE_IS_INVALID', $field->label) );
					return 'abort';
				}
				
				// Get vstate property from the field object back to the data array
				$data['vstate'] = $field->item_vstate;
			}
			//echo "<pre>"; print_r($postdata); echo "</pre>"; exit;
			
		}
		if ( $print_logging_info ) @$fc_run_times['fields_value_preparation'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		
		// ****************************************************************************************************************************
		// Loop through Fields triggering onIndexAdvSearch, onIndexSearch Event handlers, this was seperated from the before save field
		//  event, so that we will update search indexes only if the above has not canceled saving OR has not canceled version approval
		// ****************************************************************************************************************************
		if ( $print_logging_info ) $start_microtime = microtime(true);
		if ($fields) 
		{
			
			$ai_query_vals = array();
			foreach($fields as $field)
			{
				$fieldname = $field->iscore ? 'core' : $field->field_type;
				
				if ( $data['vstate']==2 || $isnew)    // update (regardless of state!!) search indexes if document version is approved OR item is new
				{
					// Trigger plugin Event 'onIndexAdvSearch' to update field-item pair records in advanced search index
					FLEXIUtilities::call_FC_Field_Func($fieldname, 'onIndexAdvSearch', array( &$field, &$postdata[$field->name], &$item ));
					if ( isset($field->ai_query_vals) ) foreach ($field->ai_query_vals as $query_val) $ai_query_vals[] = $query_val;
					//echo $field->name .":".implode(",", @$field->ai_query_vals ? $field->ai_query_vals : array() )."<br/>";
					
					// Trigger plugin Event 'onIndexSearch' to update item 's (basic) search index record
					FLEXIUtilities::call_FC_Field_Func($fieldname, 'onIndexSearch', array( &$field, &$postdata[$field->name], &$item ));
					if ( strlen(@$field->search[$item->id]) ) $searchindex[] = $field->search[$item->id];
					//echo $field->name .":".@$field->search[$item->id]."<br/>";
				}
			}
		}
		
		// Remove item's old advanced search index entries
		$query = "DELETE FROM #__flexicontent_advsearch_index WHERE item_id=". $item->id;
		$this->_db->setQuery($query);
		$this->_db->query();
		
		// Store item's advanced search index entries
		if ( !empty($ai_query_vals) ) {
			$query = "INSERT INTO #__flexicontent_advsearch_index "
				." (field_id,item_id,extraid,search_index,value_id) VALUES "
				.implode(",", $ai_query_vals);
			$this->_db->setQuery($query);
			$this->_db->query();
			if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
		}
		
		// Assigned created basic search index into item object
		$item->search_index = implode(' | ', $searchindex);
		
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
			$this->_db->query();
			$query = 'DELETE FROM #__flexicontent_items_versions WHERE item_id='.$item->id.' AND version='.((int)$last_version+1);
			$this->_db->setQuery($query);
			$this->_db->query();
			
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
						$this->_db->query();
					} else {
						$untranslatable_fields[] = $field->id;
					}
				}
			}
			if ( count($untranslatable_fields) ) {
				$query = 'DELETE FROM #__flexicontent_fields_item_relations WHERE item_id IN ('.implode(',',$assoc_item_ids).') AND field_id IN ('.implode(',',$untranslatable_fields) .')';
				$this->_db->setQuery($query);
				$this->_db->query();
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
				$i = 1;
				
				foreach ($postvalues as $postvalue)
				{
					// Create field obj for DB insertion
					$obj = new stdClass();
					$obj->field_id 		= $field->id;
					$obj->item_id 		= $item->id;
					$obj->valueorder	= $i;
					$obj->version			= (int)$last_version+1;
					
					// Serialize the properties of the value, normally this is redudant, since the field must have had serialized the parameters of each value already
					$obj->value = is_array($postvalue) ? serialize($postvalue) : $postvalue;
					
					// -- a. Add versioning values, but do not version the 'hits' or 'state' or 'voting' fields
					if ($record_versioned_data && $field->field_type!='hits' && $field->field_type!='state' && $field->field_type!='voting') {
						// Insert only if value non-empty
						if ( isset($obj->value) && JString::strlen(trim($obj->value)) )
						{
							if (! $mval_query) $this->_db->insertObject('#__flexicontent_items_versions', $obj);
							else $ver_query_vals[] = "(".$obj->field_id. "," .$obj->item_id. "," .$obj->valueorder. "," .$obj->version."," .$this->_db->Quote($obj->value).")";
						}
					}
					//echo $field->field_type." - ".$field->name." - ".JString::strlen(trim($obj->value))." ".$field->iscore."<br/>";
					
					// -- b. If item is new OR version is approved, AND field is not core (aka stored in the content table or in special table), then add field value to field values table
					if(	( $isnew || $data['vstate']==2 ) && !$field->iscore )
					{
						// UNSET version it it used only verion data table, and insert only if value non-empty
						unset($obj->version);
						if ( isset($obj->value) && JString::strlen(trim($obj->value)) )
						{
							if (! $mval_query) $this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
							else $rel_query_vals[] = "(".$obj->field_id. "," .$obj->item_id. "," .$obj->valueorder. "," .$this->_db->Quote($obj->value).")";
							
							// Save field value in all translating items, if current field is untranslatable
							// NOTE: item itself is not include in associated translations, no need to check for it and skip it
							if (count($assoc_item_ids) && $field->untranslatable) {
								foreach($assoc_item_ids as $t_item_id) {
									$obj->item_id = $t_item_id;
									if (! $mval_query) $this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
									else $rel_query_vals[] = "(".$obj->field_id. "," .$obj->item_id. "," .$obj->valueorder. "," .$this->_db->Quote($obj->value).")";
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
					." (field_id,item_id,valueorder,version,value) VALUES "
					."\n".implode(",\n", $ver_query_vals);
				$this->_db->setQuery($query);
				$this->_db->query();
				if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
			}
			
			
			// *******************************************
			// Insert values in item fields relation table
			// *******************************************
			
			if ( count($rel_query_vals) ) {
				$query = "INSERT INTO #__flexicontent_fields_item_relations "
					." (field_id,item_id,valueorder,value) VALUES "
					."\n".implode(",\n", $rel_query_vals);
				$this->_db->setQuery($query);
				$this->_db->query();
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
				$obj->version			= (int)$last_version+1;
				
				$item_data = array();
				$iproperties = array('alias', 'catid', 'metadesc', 'metakey', 'metadata', 'attribs');
				if (FLEXI_J16GE) {
					$j16ge_iproperties = array();
					$iproperties = array_merge($iproperties, $j16ge_iproperties);
				}
				foreach ( $iproperties as $iproperty) $item_data[$iproperty] = $item->{$iproperty};
				
				$obj->value = serialize( $item_data );
				$this->_db->insertObject('#__flexicontent_items_versions', $obj);
			}
			
			// b. Finally save a version of the posted JoomFish translated data for J1.5, if such data are editted inside the item edit form
			if ( FLEXI_FISH && !empty($data['jfdata']) && $record_versioned_data )
			{
				$obj = new stdClass();
				$obj->field_id 		= -1;  // ID of Fake Field used to contain the Joomfish translated item data
				$obj->item_id 		= $item->id;
				$obj->valueorder	= 1;
				$obj->version			= (int)$last_version+1;
				
				$item_lang = substr($item->language ,0,2);
				$data['jfdata'][$item_lang]['alias'] = $item->alias;
				$data['jfdata'][$item_lang]['metadesc'] = $item->metadesc;
				$data['jfdata'][$item_lang]['metakey'] = $item->metakey;
				$obj->value = serialize($data['jfdata']);
				$this->_db->insertObject('#__flexicontent_items_versions', $obj);
			}
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
				$fieldname = $field->iscore ? 'core' : $field->field_type;
				$result = FLEXIUtilities::call_FC_Field_Func($fieldname, 'onAfterSaveField', array( &$field, &$postdata[$field->name], &$files[$field->name], &$item ));
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
		$this->_id   = $item->id;
		$this->_item = & $item;
		
		
		// ***********************
		// Save access information
		// ***********************
		if (FLEXI_ACCESS) {
			$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
			$canRight 	= (in_array('right', $rights) || $user->gid > 24);
			if ($canRight) FAccess::saveaccess( $item, 'item' );
		} else if (FLEXI_J16GE) {
			// Rules for J1.6+ are handled in the JTABLE class of the item with overriden JTable functions: bind() and store()
		}
		
		
		// ***************************
		// If creating only return ...
		// ***************************
		if ($createonly) return true;
		
		
		// ****************************
		// Save joomfish data in the db
		// ****************************
		if ( (FLEXI_FISH /*|| FLEXI_J16GE*/) && $editjf_translations )
			$this->_saveJFdata( $data['jfdata'], $item );
		
		
		// ***********************************************
		// Delete old tag relations and Store the new ones
		// ***********************************************
		$tags = $data['tags'];
		$query = 'DELETE FROM #__flexicontent_tags_item_relations WHERE itemid = '.$item->id;
		$this->_db->setQuery($query);
		$this->_db->query();
		foreach($tags as $tag)
		{
			$query = 'INSERT INTO #__flexicontent_tags_item_relations (`tid`, `itemid`) VALUES(' . $tag . ',' . $item->id . ')';
			$this->_db->setQuery($query);
			$this->_db->query();
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
		$this->_db->query();

		// Get an array of the item's used categories (already assigned in DB)
		$query 	= 'SELECT catid'
			. ' FROM #__flexicontent_cats_item_relations'
			. ' WHERE itemid = '.$item->id
			;
		$this->_db->setQuery($query);
		$used = FLEXI_J16GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();

		foreach($cats as $cat) {
			// insert only the new records
			if (!in_array($cat, $used)) {
				$query 	= 'INSERT INTO #__flexicontent_cats_item_relations (`catid`, `itemid`)'
					.' VALUES(' . $cat . ',' . $item->id . ')'
					;
				$this->_db->setQuery($query);
				$this->_db->query();
			}
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
		$nn_content_tbl = FLEXI_J16GE ? 'falang_content' : 'jf_content';
		
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
			if (FLEXI_J16GE) {
				//$query = $db->replacePrefix($query);
				$query = str_replace("#__", $dbprefix, $query);
				$db_connection = $db->getConnection();
			} else {
				$query = str_replace("#__", $dbprefix, $query);
				$db_connection = & $db->_resource;
			}
			//echo "<pre>"; print_r($query); echo "\n\n";
			
			if ($dbtype == 'mysqli') {
				$result = mysqli_query( $db_connection , $query );
				if ($result===false) {echo mysqli_error($db_connection); return JError::raiseWarning( 500, "error _saveJFdata():: ".mysqli_error($db_connection));}
			} else if ($dbtype == 'mysql') {
				$result = mysql_query( $query, $db_connection  );
				if ($result===false) return JError::raiseWarning( 500, "error _saveJFdata():: ".mysql_error($db_connection));
			} else {
				$msg = 'unreachable code in _saveJFdata(): direct db query, unsupported DB TYPE';
				if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
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
				$jfdata['text'][0] .= (preg_match('#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i', $jfdata['text'][0]) == 0) ? ("\n".'<hr id="system-readmore" />') : "" ;
				$tabs_text = '';
				foreach($jfdata['text'] as $tab_text) {
					$tabs_text .= $tab_text;
				}
				$jfdata['text'] = & $tabs_text;
			} else if ( empty($jfdata['text']) ) {
				$jfdata['text'] = '';
			}
			
			// Search for the {readmore} tag and split the text up accordingly.
			$pattern = '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
			$tagPos	= preg_match($pattern, $jfdata['text']);
			if ($tagPos == 0)	{
				$jfdata['introtext']	= $jfdata['text'];
				$jfdata['fulltext']	= '';
			} else 	{
				list($jfdata['introtext'], $jfdata['fulltext']) = preg_split($pattern, $jfdata['text'], 2);
				$jfdata['fulltext'] = JString::strlen( trim($jfdata['fulltext']) ) ? $jfdata['fulltext'] : '';
			}
			
			// Delete existing Joom!Fish translation data for the current item
			$query  = "DELETE FROM  #__".$nn_content_tbl." WHERE language_id={$langs->$shortcode->id} AND reference_table='content' AND reference_id={$item->id}";
			$db->setQuery($query);
			$db->query();
			
			// Apply new translation data
			$translated_fields = array('title','alias','introtext','fulltext','metadesc','metakey');
			foreach ($translated_fields as $fieldname) {
				if ( !strlen( @$jfdata[$fieldname] ) ) continue;
				//if ( !JString::strlen(trim(str_replace("&nbsp;", "", strip_tags(@$jfdata[$fieldname])))) ) continue;   // skip empty content
				//echo "<br/><b>#__".$nn_content_tbl."($fieldname) :</b><br/>";
				$query = "INSERT INTO #__".$nn_content_tbl." (language_id, reference_id, reference_table, reference_field, value, original_value, original_text, modified, modified_by, published) ".
					"VALUES ( {$langs->$shortcode->id}, {$item->id}, 'content', '$fieldname', ".$db->Quote(@$jfdata[$fieldname]).", '".md5($item->{$fieldname})."', ".$db->Quote($item->{$fieldname}).", '$modified', '$modified_by', 1)";
				//echo $query."<br/>\n";
				$db->setQuery($query);
				$db->query();
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
			$tags = FLEXI_J16GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();
			if ($this->_id == $item_id) {
				// Retrieved tags of current item, set them
				$this->_item->tags = $tags;
			}
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
	 * @since 	1.5.2
	 */
	function getUsedtagsData($tagIds)
	{
		if (empty($tagIds)) {
			return array();
		}
		
		$query 	= 'SELECT *,t.id as tid FROM #__flexicontent_tags as t '
				. ' WHERE t.id IN (\'' . implode("','", $tagIds).'\')'
				. ' ORDER BY name ASC'
				;
		$this->_db->setQuery($query);
		$used = $this->_db->loadObjectList();
		return $used;
	}
	
	
	/**
	 * Method to get a list of all available tags Data
	 * 
	 * @param 	array
	 * @return 	array
	 * @since 	1.5.2
	 */
	function getAlltags() {
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
		$this->_db->query();
		
		// load field values from the version to restore
		$query 	= 'SELECT item_id, field_id, value, valueorder, iscore'
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
		$this->_db->query($query);
		// handle the maintext not very elegant but functions properly
		$row = $this->getTable('flexicontent_items', '');
		$row->load($id);

		if (@$versionrecords[0]->value) {
			// Search for the {readmore} tag and split the text up accordingly.
			$text 		= $versionrecords[0]->value;
			$pattern 	= '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
			$tagPos		= preg_match($pattern, $text);

			if ($tagPos == 0) {
				$row->introtext	= $text;
				$row->fulltext  = '';
			} else 	{
				list($row->introtext, $row->fulltext) = preg_split($pattern, $text, 2);
				$row->fulltext = JString::strlen( trim($row->fulltext) ) ? $row->fulltext : '';
			}
		}
		//$row->store();
	}
	
	
	/**
	 * Method to fetch tags according to a given mask
	 * 
	 * @return object
	 * @since 1.0
	 */
	function gettags($mask="")
	{
		$escaped_mask = FLEXI_J16GE ? $this->_db->escape( $mask, true ) : $this->_db->getEscaped( $mask, true );
		$where = ($mask!="")?" name like ".$this->_db->Quote( '%'.$escaped_mask.'%', false )." AND":"";
		$query = 'SELECT * FROM #__flexicontent_tags WHERE '.$where.' published = 1 ORDER BY name';
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
		$this->_db->query();
		
		// Delete extra vote types
		$query = 'DELETE FROM #__flexicontent_items_extravote WHERE content_id = '.$id;
		$this->_db->setQuery($query);
		$this->_db->query();
	}
	
	
	/**
	 * Method to get votes
	 * 
	 * @param int id
	 * @return object
	 * @since 1.0
	 */
	function getvotes($id)
	{
		$query = 'SELECT rating_sum, rating_count FROM #__content_rating WHERE content_id = '.(int)$id;
		$this->_db->setQuery($query);
		$votes = $this->_db->loadObjectlist();

		return $votes;
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
		static $used = null;
		if (!$used || $force) {
			if ($this->_id) {
				$query = 'SELECT ie.type_id as id,t.name FROM #__flexicontent_items_ext as ie'
					. ' JOIN #__flexicontent_types as t ON ie.type_id=t.id'
					. ' WHERE ie.item_id = ' . (int)$this->_id;
				$this->_db->setQuery($query);
				$used = $this->_db->loadObject();
			} else {
				$typeid = (int)JRequest::getInt('typeid', 0);
				$query = 'SELECT t.id,t.name FROM #__flexicontent_types as t'
					. ' WHERE t.id = ' . (int)$typeid;
				$this->_db->setQuery($query);
				$used = $this->_db->loadObject();
			}
			if (!$used) {
				$used = new stdClass();
				$used->id = 0;
				$used->name = null;
			}
		}
		return $used;
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
			$categories = FLEXI_J16GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();
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
	function getTypeparams ()
	{
		$query	= 'SELECT t.attribs'
				. ' FROM #__flexicontent_types AS t';

		if ( !$this->_id ) {
			$type_id = JRequest::getInt('typeid', 0);
			$query .= ' WHERE t.id = ' . (int)$type_id;
		} else {
			$query .= ' JOIN #__flexicontent_items_ext AS ie ON ie.type_id = t.id'
				. ' WHERE ie.item_id = ' . (int)$this->_id
				;
		}
		$this->_db->setQuery($query);
		$tparams = $this->_db->loadResult();
		return $tparams ? $tparams : '';
	}
	
	
	/**
	 * Method to get types list when performing an edit action or e.g. checking 'create' ACCESS for the types
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getTypeslist ( $type_ids=false, $check_perms = false )
	{
		if ( !empty($type_ids) && is_array($type_ids) ) {
			foreach ($type_ids as $i => $type_id)
				$type_ids[$i] = (int) $type_id;
			$type_ids_list = implode(',', $type_ids);
		}
		$query = 'SELECT * '
				. ' FROM #__flexicontent_types'
				. ' WHERE published = 1 '. ( @ $type_ids_list ? ' AND id IN ('. $type_ids_list .' ) ' : '' )
				. ' ORDER BY name ASC'
				;
		$this->_db->setQuery($query);
		$types = $this->_db->loadObjectList('id');
		if ($check_perms)
		{
			$user = JFactory::getUser();
			$_types = array();
			foreach ($types as $type) {
				if (FLEXI_J16GE)
					$allowed = ! $type->itemscreatable || $user->authorise('core.create', 'com_flexicontent.type.' . $type->id);
				else if (FLEXI_ACCESS && $user->gid < 25)
					$allowed = ! $type->itemscreatable || FAccess::checkAllContentAccess('com_content','submit','users', $user->gmid, 'type', $type->id);
				else
					$allowed = 1;
				if ( $allowed ) $_types[] = $type;
			}
			$types = $_types;
		}
		
		return $types;
	}
	
	
	/**
	 * Method to retrieve the value of a core field for a specified item version
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getCoreFieldValue(&$field, $version = 0)
	{
		if(isset($this->_item)) {
			$item = & $this->_item;
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
			$value = JString::strlen( trim($item->fulltext) ) ? $item->introtext . "<hr id=\"system-readmore\" />" . $item->fulltext : $item->introtext;
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
		
		$query = 'SELECT field_id, value'
			.( ($version<=0 || !$use_versioning) ? ' FROM #__flexicontent_fields_item_relations AS fv' : ' FROM #__flexicontent_items_versions AS fv' )
			.' WHERE fv.item_id = ' . (int)$item_id
			.( ($version>0 && $use_versioning) ? ' AND fv.version='.((int)$version) : '')
			.' ORDER BY field_id, valueorder'
			;
		$this->_db->setQuery($query);
		$rows = $this->_db->loadObjectList();
		
		// Add values to cached array
		$field_values[$item_id][$version] = array();
		foreach ($rows as $row) {
			$field_values[$item_id][$version][$row->field_id][] = $row->value;
		}
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
	function getExtrafields($force = false, $lang_parent_id = 0)
	{
		static $fields;
		if(!$fields || $force) {
			jimport('joomla.html.parameter');
			$use_versioning = $this->_cparams->get('use_versioning', 1);
			$typeid = $this->get('type_id');   // Get item's type_id, loading item if neccessary
			$typeid = $typeid ? $typeid : JRequest::getVar('typeid', 0, '', 'int');
			$type_join = ' JOIN #__flexicontent_fields_type_relations AS ftrel ON ftrel.field_id = fi.id AND ftrel.type_id='.$typeid;
			
			$version = JRequest::getVar( 'version', 0, 'request', 'int' );
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
			
			$custom_vals[$this->_id] = $this->getCustomFieldsValues($this->_id, $version);
			if ( $lang_parent_id && !$version) {  // Language parent item is used only when loading non-versioned item
				$custom_vals[$lang_parent_id] = $this->getCustomFieldsValues($lang_parent_id, 0);
			}
			foreach ($fields as $field)
			{
				$field->item_id		= (int)$this->_id;
				// $version should be ZERO when versioning disabled, or when wanting to load the current version !!!
				if ( (!$version || !$use_versioning) && $field->iscore) {
					// Load CURRENT (non-versioned) core field from properties of item object
					$field->value = $this->getCoreFieldValue($field, $version);
				} else {
					// Load non core field (versioned or non-versioned) OR core field (versioned only)
					// while checking if current field is using untranslatable value from original content item
					$item_id = ($lang_parent_id && @$field->untranslatable && !$version) ? $lang_parent_id : $this->_id;
					$field->value = isset( $custom_vals[$item_id][$field->id] ) ? $custom_vals[$item_id][$field->id] : array();
					if( ( $field->name=='categories') || $field->name=='tags' ) {
						// categories and tags must have been serialized but some early versions did not do it, we will check before unserializing them
						$field->value = ($array = @unserialize($field->value[0]) ) ? $array : $field->value;
					}
				}
				
				//echo "Got ver($version) id {$field->id}: ". $field->name .": ";  print_r($field->value); 	echo "<br/>";
				$field->parameters = FLEXI_J16GE ? new JRegistry($field->attribs) : new JParameter($field->attribs);
			}
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
				. ' AND ( checked_out = 0 OR ( checked_out = ' . (int) $user->get('id'). ' ) )'
			;
			$this->_db->setQuery( $query );
			$this->_db->query();
			if ( $this->_db->getErrorNum() )  if (FLEXI_J16GE) throw new Exception($this->_db->getErrorMsg(), 500); else JError::raiseError(500, $this->_db->getErrorMsg());
			
			$query = 'UPDATE #__flexicontent_items_versions'
				. ' SET value = ' . (int)$state
				. ' WHERE item_id = '.(int)$id
				. ' AND valueorder = 1'
				. ' AND field_id = 10'
				. ' AND version = ' .(int)$v['version']
				;
			$this->_db->setQuery( $query );
			$this->_db->query();
			if ( $this->_db->getErrorNum() )  if (FLEXI_J16GE) throw new Exception($this->_db->getErrorMsg(), 500); else JError::raiseError(500, $this->_db->getErrorMsg());
		}
		
		
		// ****************************************************************
		// Trigger Event 'onContentChangeState' of Joomla's Content plugins
		// ****************************************************************
		if (FLEXI_J16GE) {
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
			$item->attribs = FLEXI_J16GE ? new JRegistry($item->attribs) : new JParameter($item->attribs);
			foreach ($params as $k => $v) {
				//$v = is_array($v) ? implode('|', $v) : $v;
				$item->attribs->set($k, $v);
			}
			
			// Clear any old parameters of all item template layouts, except the currently used one
			$themes = flexicontent_tmpl::getTemplates();
			foreach ($themes->items as $tmpl_name => $tmpl)
			{
				if ( $tmpl_name == @$params['ilayout'] ) continue;
				
				$tmpl_params = $tmpl->params;
				if (FLEXI_J16GE) {
					$jform = new JForm('com_flexicontent.template.item', array('control' => 'jform', 'load_data' => true));
					$jform->load($tmpl_params);
					foreach ($jform->getGroup('attribs') as $p) {
						if (!empty($p->fieldname))
							$item->attribs->set($p->fieldname, null);
					}
				} else {
					if ( !empty($tmpl_params->_xml['_default']) )  // check if parameters group is empty
					{
						foreach ( $tmpl_params->_xml['_default']->children() as $p ) {
							if (!empty($p->_attributes['name']))
								$item->attribs->set($p->_attributes['name'], null);
						}
					}
				}
			}
			$item->attribs = $item->attribs->toString();
		}
		
		// Build item metadata INI string
		if (is_array($metadata))
		{
			$item->metadata = FLEXI_J16GE ? new JRegistry($item->metadata) : new JParameter($item->metadata);
			foreach ($metadata as $k => $v) {
				if ( $k == 'description' && !FLEXI_J16GE ) {  // is jform field in J1.6+
					$item->metadesc = $v;
				} elseif ( $k == 'keywords' && !FLEXI_J16GE ) {  // is jform field in J1.6+
					$item->metakey = $v;
				} else {
					$item->metadata->set($k, $v);
				}
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
		$nConf->usergrps_notify_new_fa         = FLEXIUtilities::paramToArray( $params->get('usergrps_notify_new_fa', array()) );
		$nConf->userlist_notify_new_pending    = FLEXIUtilities::paramToArray( $params->get('userlist_notify_new_pending'), $regex="/[\s]*,[\s]*/", $filterfunc="intval");
		$nConf->usergrps_notify_new_pending    = FLEXIUtilities::paramToArray( $params->get('usergrps_notify_new_pending', array()) );
		$nConf->usergrps_notify_new_pending_fa = FLEXIUtilities::paramToArray( $params->get('usergrps_notify_new_pending_fa', array()) );
		
		$nConf->userlist_notify_existing             = FLEXIUtilities::paramToArray( $params->get('userlist_notify_existing'), $regex="/[\s]*,[\s]*/", $filterfunc="intval");
		$nConf->usergrps_notify_existing             = FLEXIUtilities::paramToArray( $params->get('usergrps_notify_existing', array()) );
		$nConf->usergrps_notify_existing_fa          = FLEXIUtilities::paramToArray( $params->get('usergrps_notify_existing_fa', array()) );
		$nConf->userlist_notify_existing_reviewal    = FLEXIUtilities::paramToArray( $params->get('userlist_notify_existing_reviewal'), $regex="/[\s]*,[\s]*/", $filterfunc="intval");
		$nConf->usergrps_notify_existing_reviewal    = FLEXIUtilities::paramToArray( $params->get('usergrps_notify_existing_reviewal', array()) );
		$nConf->usergrps_notify_existing_reviewal_fa = FLEXIUtilities::paramToArray( $params->get('usergrps_notify_existing_reviewal_fa', array()) );
		
		// (c) Get category specific notifications
		if ( $params->get('nf_allow_cat_specific') ) 
		{
			$cats = $this->get('categories');
			$query = "SELECT params FROM #__categories WHERE id IN (".implode(',',$cats).")";
			$db->setQuery( $query );
			$mcats_params = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
			
			foreach ($mcats_params as $cat_params) {
				$cat_params = FLEXI_J16GE ? new JRegistry($cat_params) : new JParameter($cat_params);
				if ( ! $cat_params->get('cats_enable_notifications', 0) ) continue;  // Skip this category if category-specific notifications are not enabled for this category
				
				$cats_userlist_notify_new            = FLEXIUtilities::paramToArray( $cat_params->get('cats_userlist_notify_new'), $regex="/[\s]*,[\s]*/", $filterfunc="intval");
				$cats_usergrps_notify_new            = FLEXIUtilities::paramToArray( $cat_params->get('cats_usergrps_notify_new', array()) );
				$cats_usergrps_notify_new_fa         = FLEXIUtilities::paramToArray( $cat_params->get('cats_usergrps_notify_new_fa', array()) );
				$cats_userlist_notify_new_pending    = FLEXIUtilities::paramToArray( $cat_params->get('cats_userlist_notify_new_pending'), $regex="/[\s]*,[\s]*/", $filterfunc="intval");
				$cats_usergrps_notify_new_pending    = FLEXIUtilities::paramToArray( $cat_params->get('cats_usergrps_notify_new_pending', array()) );
				$cats_usergrps_notify_new_pending_fa = FLEXIUtilities::paramToArray( $cat_params->get('cats_usergrps_notify_new_pending_fa', array()) );
				
				$cats_userlist_notify_existing             = FLEXIUtilities::paramToArray( $cat_params->get('cats_userlist_notify_existing'), $regex="/[\s]*,[\s]*/", $filterfunc="intval");
				$cats_usergrps_notify_existing             = FLEXIUtilities::paramToArray( $cat_params->get('cats_usergrps_notify_existing', array()) );
				$cats_usergrps_notify_existing_fa          = FLEXIUtilities::paramToArray( $cat_params->get('cats_usergrps_notify_existing_fa', array()) );
				$cats_userlist_notify_existing_reviewal    = FLEXIUtilities::paramToArray( $cat_params->get('cats_userlist_notify_existing_reviewal'), $regex="/[\s]*,[\s]*/", $filterfunc="intval");
				$cats_usergrps_notify_existing_reviewal    = FLEXIUtilities::paramToArray( $cat_params->get('cats_usergrps_notify_existing_reviewal', array()) );
				$cats_usergrps_notify_existing_reviewal_fa = FLEXIUtilities::paramToArray( $cat_params->get('cats_usergrps_notify_existing_reviewal_fa', array()) );
				
				$nConf->userlist_notify_new            = array_unique(array_merge($nConf->userlist_notify_new,            $cats_userlist_notify_new));
				$nConf->usergrps_notify_new            = array_unique(array_merge($nConf->usergrps_notify_new,            $cats_usergrps_notify_new));
				$nConf->usergrps_notify_new_fa         = array_unique(array_merge($nConf->usergrps_notify_new_fa,         $cats_usergrps_notify_new_fa));
				$nConf->userlist_notify_new_pending    = array_unique(array_merge($nConf->userlist_notify_new_pending,    $cats_userlist_notify_new_pending));
				$nConf->usergrps_notify_new_pending    = array_unique(array_merge($nConf->usergrps_notify_new_pending,    $cats_usergrps_notify_new_pending));
				$nConf->usergrps_notify_new_pending_fa = array_unique(array_merge($nConf->usergrps_notify_new_pending_fa, $cats_usergrps_notify_new_pending_fa));
				
				$nConf->userlist_notify_existing             = array_unique(array_merge($nConf->userlist_notify_existing,             $cats_userlist_notify_existing));
				$nConf->usergrps_notify_existing             = array_unique(array_merge($nConf->usergrps_notify_existing,             $cats_usergrps_notify_existing));
				$nConf->usergrps_notify_existing_fa          = array_unique(array_merge($nConf->usergrps_notify_existing_fa,          $cats_usergrps_notify_existing_fa));
				$nConf->userlist_notify_existing_reviewal    = array_unique(array_merge($nConf->userlist_notify_existing_reviewal,    $cats_userlist_notify_existing_reviewal));
				$nConf->usergrps_notify_existing_reviewal    = array_unique(array_merge($nConf->usergrps_notify_existing_reviewal,    $cats_usergrps_notify_existing_reviewal));
				$nConf->usergrps_notify_existing_reviewal_fa = array_unique(array_merge($nConf->usergrps_notify_existing_reviewal_fa, $cats_usergrps_notify_existing_reviewal_fa));
			}
		}
		//echo "<pre>"; print_r($nConf); exit;
		
		// Construct configuation parameter names
		$nConf_emails = new stdClass();
		$notify_types = array('notify_new', 'notify_new_pending', 'notify_existing', 'notify_existing_reviewal');
		foreach ($notify_types as $ntype) {
			$ugrps_fa[$ntype] = 'usergrps_'.$ntype.'_fa';
			$ugrps   [$ntype] = 'usergrps_'.$ntype;
			$ulist   [$ntype] = 'userlist_'.$ntype;
		}
		
		// (e) Get emails, but first convert user groups to user ids
		foreach ($notify_types as $ntype)
		{
			$user_emails = array();
			
			// emails for user ids
			$user_emails_ulist = array();
			if ( count( $nConf->{$ulist[$ntype]} ) )
			{
				$query = "SELECT DISTINCT email FROM #__users WHERE id IN (".implode(",",$nConf->{$ulist[$ntype]}).")";
				$db->setQuery( $query );
				$user_emails_ulist = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
				if ( $db->getErrorNum() ) echo $db->getErrorMsg();  // if ($ntype=='notify_new_pending') { echo "<pre>"; print_r($user_emails_ulist); exit; }
			}
			
			$user_emails_ugrps = array();
			if ( count( $nConf->{$ugrps[$ntype]} ) )
			{
				// emails for user groups
				if (!FLEXI_J16GE) {
					$query = "SELECT DISTINCT email FROM #__users WHERE gid IN (".implode(",",$nConf->{$ugrps[$ntype]}).")";
				} else {
					$query = "SELECT DISTINCT email FROM #__users as u"
						." JOIN #__user_usergroup_map ugm ON u.id=ugm.user_id AND ugm.group_id IN (".implode(",",$nConf->{$ugrps[$ntype]}).")";
				}
				$db->setQuery( $query );
				$user_emails_ugrps = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
				if ( $db->getErrorNum() ) echo $db->getErrorMsg();  // if ($ntype=='notify_new_pending') { print_r($user_emails_ugrps); exit; }
			}
			
			$user_emails_ugrps_fa = array();
			if ( FLEXI_ACCESS && count( $nConf->{$ugrps_fa[$ntype]} ) )
			{
				$final_groups = array();
				foreach ( $nConf->{$ugrps_fa[$ntype]} as $fagrpid ) {
					$curr_groups = FAccess::mgenfant( $fagrpid );
					$final_groups = array_unique( array_merge ($final_groups,$curr_groups) );
				}
				//print_r($final_groups); exit;
				
				// emails for flexiaccess user groups
				$query = "SELECT DISTINCT email FROM #__users as u"
					." JOIN #__flexiaccess_groups ugm ON u.username=ugm.name AND ugm.type=2 AND ugm.id IN (".implode(",",$final_groups).")";
				$db->setQuery( $query );
				$user_emails_ugrps_fa_individual = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
				if ( $db->getErrorNum() ) echo $db->getErrorMsg();
				
				
				// emails for flexiaccess user groups
				$query = "SELECT DISTINCT email FROM #__users as u"
					." JOIN #__flexiaccess_members ugm ON u.id=ugm.member_id AND ugm.group_id IN (".implode(",",$final_groups).")";
				$db->setQuery( $query );
				$user_emails_ugrps_fa_collective = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
				if ( $db->getErrorNum() ) echo $db->getErrorMsg();
				
				$user_emails_ugrps_fa = array_unique( array_merge ($user_emails_ugrps_fa_individual, $user_emails_ugrps_fa_collective) );
				// if ($ntype=='notify_new_pending') { print_r($user_emails_ugrps_fa); exit; }
			}
			
			// merge them
			$user_emails = array_unique( array_merge($user_emails_ulist, $user_emails_ugrps, $user_emails_ugrps_fa) );
			
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
		if (FLEXI_J16GE) $tz = new DateTimeZone($tz_offset);
		$tz_offset_str = FLEXI_J16GE ? ($tz->getOffset(new JDate()) / 3600)  :  $tz_offset;
		$tz_offset_str = ' &nbsp; (UTC+'.$tz_offset_str.') ';
		
		if ( in_array('created',$nf_extra_properties) )
		{
			$date_created = JFactory::getDate($this->get('created'));
			if (FLEXI_J16GE) {
				$date_created->setTimezone($tz);
			} else {
				$date_created->setOffset($tz_offset);
			}
			$body .= '<u>'.JText::_( 'FLEXI_NF_CREATION_TIME' ) . "</u>: ";
			$body .= FLEXI_J16GE ?
				$date_created->format($format = 'D, d M Y H:i:s', $local = true) :
				$date_created->toFormat($format = '%Y-%m-%d %H:%M:%S');
			$body .= $tz_offset_str. "<br/>\r\n";
		}
		if ( in_array('modified',$nf_extra_properties) && !$isnew )
		{
			$date_modified = JFactory::getDate($this->get('modified'));
			if (FLEXI_J16GE) {
				$date_modified->setTimezone($tz);
			} else {
				$date_modified->setOffset($tz_offset);
			}
			$body .= '<u>'.JText::_( 'FLEXI_NF_MODIFICATION_TIME' ) . "</u>: ";
			$body .= FLEXI_J16GE ?
				$date_modified->format($format = 'D, d M Y H:i:s', $local = true) :
				$date_modified->toFormat($format = '%Y-%m-%d %H:%M:%S');
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
			$body .= $link . "<br/>\r\n<br/>\r\n";
		}
		if ( in_array('editlinkfe',$nf_extra_properties) )
		{
			$body .= '<u>'.JText::_( 'FLEXI_NF_EDIT_IN_FRONTEND' ) . "</u> : <br/>\r\n &nbsp; ";
			$link = JRoute::_( JURI::root(false).'index.php?option=com_flexicontent&view='.FLEXI_ITEMVIEW.'&cid='.$this->get('catid').'&id='.$this->get('id').'&task=edit');
			$body .= $link . "<br/>\r\n<br/>\r\n";
		}
		if ( in_array('editlinkbe',$nf_extra_properties) )
		{
			$body .= '<u>'.JText::_( 'FLEXI_NF_EDIT_IN_BACKEND' ) . "</u> : <br/>\r\n &nbsp; ";
			$fc_ctrl_task = FLEXI_J16GE ? 'task=items.edit' : 'controller=items&task=edit';
			$link = JRoute::_( JURI::root(false).'administrator/index.php?option=com_flexicontent&'.$fc_ctrl_task.'&cid='.$this->get('id'));
			$body .= $link . "<br/>\r\n<br/>\r\n";
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
		
		if (!FLEXI_J16GE) jimport( 'joomla.utilities.utility' );
		$send_result = FLEXI_J16GE ?
			JFactory::getMailer()->sendMail( $from, $fromname, $recipient, $subject, $body, $html_mode, $cc, $bcc, $attachment, $replyto, $replytoname ) :
			JUtility::sendMail( $from, $fromname, $recipient, $subject, $body, $html_mode, $cc, $bcc, $attachment, $replyto, $replytoname );
		
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
			$query 	= 'SELECT c.id, c.catid, c.created_by, c.title, cat.title AS cattitle from #__content AS c'
					. ' LEFT JOIN #__categories AS cat on cat.id = c.catid'
					. ' WHERE c.state = -4'
					. ' AND c.created_by = ' . (int) $user->get('id')
					. (FLEXI_J16GE ? ' AND cat.extension="'.FLEXI_CAT_EXTENSION.'"' : '')
					. ' AND c.id IN ( '. implode(',', $cid).' )'
					. ' AND ( c.checked_out = 0 OR ( c.checked_out = ' . (int) $user->get('id'). ' ) )'
					;
			$this->_db->setQuery( $query );
			$cids = $this->_db->loadObjectList();
			
			if (!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
		}

		return $cids;
	}
	
	
	/**
	 * Method to find validators for an item
	 *
	 * @access	public
	 * @params	int			the id of the item
	 * @params	int			the catid of the item
	 * @return	object		the validators object
	 * @since	1.5
	 */
	function getApprovalRequestReceivers($id, $catid)
	{
		$validators = new stdClass();
		/*if ( FLEXI_ACCESS ) {   // Compatibility with previous flexi versions
			global $globalcats;
		
			$query	= 'SELECT DISTINCT aro from #__flexiaccess_acl'
					. ' WHERE acosection = ' . $this->_db->Quote('com_content')
					. ' AND aco = ' . $this->_db->Quote('publish')
					// first step : get all groups that can publish everything
					. ' AND ( ( axosection = ' . $this->_db->Quote('content') . ' AND axo = ' . $this->_db->Quote('all') . ' )'
					// second step : get all groups that can publish in the item's cats (main cat and ancestors)
					. ' OR 	( axosection = ' . $this->_db->Quote('category') . ' AND axo IN ( ' . $globalcats[$catid]->ancestors . ') )'
					// third step : get all groups that can publish this specific item
					. ' OR 	( axosection = ' . $this->_db->Quote('item') . ' AND axo = ' . $id . ' ) )'
					;
			$this->_db->setQuery($query);
			$publishers = FLEXI_J16GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();
		
			// find all nested groups
			if ($publishers) {
				$users = $publishers;
				foreach ($publishers as $publisher) {
					$validators = FAccess::mgenfant($publisher);
					$users = array_merge($users, $validators);
				}
			}
			
			// get all users from these groups that wants to receive system emails
			$query	= 'SELECT DISTINCT u.email from #__flexiaccess_members AS m'
					. ' LEFT JOIN #__users AS u ON u.id = m.member_id'
					. ' WHERE m.group_id IN ( ' . implode(',', $users) . ' )'
					. ' AND u.sendEmail = 1'
					;		
			$this->_db->setQuery($query);
			$validators->notify_emails = FLEXI_J16GE ? $this->_db->loadColumn() : $this->_db->loadResultArray();
			$validators->notify_text = '';
		} else {*/
			// J1.5 with no FLEXIaccess or J2.5+
			
			// Get component parameters and them merge into them the type parameters
			$params  = FLEXI_J16GE ? new JRegistry() : new JParameter("");
			$cparams = JComponentHelper::getParams('com_flexicontent');
			$params->merge($cparams);
		
			$tparams = $this->getTypeparams();
			$tparams = FLEXI_J16GE ? new JRegistry($tparams) : new JParameter($tparams);
			$params->merge($tparams);
			
			// Get notifications configuration and select appropriate emails for current saving case
			$nConf = & $this->getNotificationsConf($params);
		
			$validators->notify_emails = $nConf->emails->notify_new_pending;
		
			$validators->notify_text = '';//$params->get('text_notify_new_pending');
		//}
		//print_r($validators); exit;
		
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
		$approvables = $this->isUserDraft($cid);
		
		$submitted = 0;
		$publishable = array();
		foreach ($approvables as $approvable) {
			// Get item setting it into the model, and get publish privilege
			$item = $this->getItem($approvable->id, $check_view_access=false, $no_cache=true);
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
					
			// Get component parameters and them merge into them the type parameters
			$params  = FLEXI_J16GE ? new JRegistry() : new JParameter("");
			$cparams = JComponentHelper::getParams('com_flexicontent');
			$params->merge($cparams);
				
			$tparams = $this->getTypeparams();
			$tparams = FLEXI_J16GE ? new JRegistry($tparams) : new JParameter($tparams);
			$params->merge($tparams);
					
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
					
			$this->sendNotificationEmails($notify_vars, $params, $manual_approval_request=1);
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
		
		// Message about excluded publishable items, that can be published by the owner
		if ( count($publishable) ) {
			$publishable_str = '"'. implode('" , "', $publishable) .'"';
			$msg .= '<div>'.JText::sprintf('FLEXI_APPROVAL_PUBLISHABLE_EXCLUDED', $publishable_str).'</div>';
		}
		
		// This may not be needed since the item was already in unpublished stated ??
		if (FLEXI_J16GE) {
			$cache = FLEXIUtilities::getCache();
			$cache->clean('com_flexicontent_items');
			$cache->clean('com_flexicontent_filters');
		} else {
			$itemcache = JFactory::getCache('com_flexicontent_items');
			$itemcache->clean();
			$filtercache = JFactory::getCache('com_flexicontent_filters');
			$filtercache->clean();
		}
		
		return $msg;
	}
	
	
	function getLangAssocs()
	{
		if ($this->_translations!==null) return $this->_translations;
		$this->_translations = array();
		
		// Make sure we item list is populased and non-empty
		if ( empty($this->_item) )  return $this->_translations;
		
		// Get associated translations
		if ( empty($this->_item->lang_parent_id) )  return $this->_translations;
		
		$query = 'SELECT i.id, i.title, i.created, i.modified, ie.lang_parent_id, ie.language as language, ie.language as lang '
			//. ', CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug '
			//. ', CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug '
		  . ' FROM #__content AS i '
		  . ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id '
		  . ' WHERE ie.lang_parent_id IN ('.(int)$this->_item->lang_parent_id.')'
		  ;
		$this->_db->setQuery($query);
		$this->_translations = $this->_db->loadObjectList();
		if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
		
		if ( empty($this->_translations) )  return $this->_translations;
		
		return $this->_translations;
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
			if (!$db->query()) {
				throw new Exception($db->getErrorMsg());
			}

			if ((int)$value == 0) {
				// Adjust the mapping table.
				// Clear the existing features settings.
				$db->setQuery(
					'DELETE FROM #__content_frontpage' .
					' WHERE content_id IN ('.implode(',', $pks).')'
				);
				if (!$db->query()) {
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
					if (!$db->query()) {
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

	
}
?>
