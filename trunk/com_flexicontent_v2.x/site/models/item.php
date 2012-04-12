<?php
/**
 * @version 1.5 stable $Id: items.php 1222 2012-03-27 20:27:49Z ggppdk $
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

defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.application.component.model');
if (FLEXI_J16GE) {
	require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'parentclassitem.php');
}

/**
 * FLEXIcontent Component Item Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelItem extends ParentClassItem
{
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
	 * Item tags
	 *
	 * @var array
	 */
	var $_tags = null;

	/**
	 * Item's current category
	 *
	 * @var int
	 */
	var $_cid = null;
	
	/**
	 * Item primary key
	 *
	 * @var int
	 */
	var $_id = null;
	
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();

		$id = JRequest::getVar('id', 0, '', 'int');
		$this->setId((int)$id);
		$this->_cparams = clone( JComponentHelper::getParams( 'com_flexicontent' ) );
		
		// In J1.6+ the above function does not merge current menu item parameters,
		// it behaves like JComponentHelper::getParams('com_flexicontent') was called
		if (FLEXI_J16GE) {
			if ($menu = JSite::getMenu()->getActive()) {
				$menuParams = new JRegistry;
				$menuParams->loadJSON($menu->params);
				$this->_cparams->merge($menuParams);
			}
		}
	}

	/**
	 * Method to set the item id
	 *
	 * @access	public
	 * @param	int	faq ID number
	 */

	function setId($id)
	{
		// Set new item id and wipe data
		if ($this->_id != $id) {
			$this->_item = null;
		}
		$this->_id = $id;

		// Set current category, but verify item is assigned to this category
		$this->_cid	= JRequest::getInt('cid');
		if ($this->_cid) {
			$q = "SELECT catid FROM #__flexicontent_cats_item_relations WHERE itemid =". (int)$this->_id ." AND catid = ". (int)$this->_cid;
			$this->_db->setQuery($q);
			$result = $this->_db->loadResult();
			$this->_cid = $result ? $this->_cid : 0;  // Clear cid
		}
	}
	
	
	
	/**
	 * Method to load required data
	 *
	 * @access	private
	 * @return	array
	 * @since	1.0
	 */
	function _loadItem() {
		return !FLEXI_J16GE ? $this->_loadItem_j15() : $this->_loadItem_j16ge() ;
	}


	/**
	 * Method to get data for the itemview
	 *
	 * @access public
	 * @return array
	 * @since 1.0
	 */
	function &getItem($pk=null, $isform=true, $skip_read_access_check=false) {
		global $globalcats;
		$mainframe =& JFactory::getApplication();
		$cparams   =& $this->_cparams;
		$preview = JRequest::getVar('preview');
		
		if (FLEXI_J16GE && $isform) { // Get item in parent class (it is used only for item edit form)
			return parent::getItem($pk);
		}
		
		// Cache items retrieved, we can retrieve multiple items, for this purpose 
		// (a) temporarily set JRequest variable -cid-  to specify the item's current category (set to zero to use main category)
		// (b) temporarily set JRequest variable -version- to specify loaded version (set to zero to use latest )
		// (c1) use member function setId($pk) to change primary key and then call getItem()
		// (c2) or call getItem passing the item id and maybe also disabling read access checkings, to avoid unwanted messages/errors
		static $items = array();
		if (isset ($items[@$this->_item->id])) {
			return $items[@$this->_item->id];
		}
		
		// Initialise variables.
		$pk = (!empty($pk)) ? $pk : $this->_id;
		if (FLEXI_J16GE) {
			$pk = (!empty($pk)) ? $pk : (int) $this->getState($this->getName().'.id');
		}
		if ($pk != $this->_id) {
			$this->setId($pk);   // Set new item id, clearing item data, and also setting current category
		}
		
		/*
		* Load the Item data
		*/
		if ($this->_id && $this->_loadItem()) {
			// Cache the retrieved item
			$items[@$this->_item->id] = & $this->_item;
			
			// ********************************************************************************************
			// CHECK item's -VIEWING- ACCESS, this could be moved to the controller, if we do this, then we
			// must check the view variable, because DISPLAY() CONTROLLER TASK is shared among all views)
			// ... or create a separate FRONTEND controller for the ITEM VIEW
			// ********************************************************************************************
			
			$user	= & JFactory::getUser();
			$aid	= (int) $user->get('aid');
			$gid	= (int) $user->get('gid');
			$cid	= $this->_cid;
			$params = & $this->_item->parameters;
		
			// Create the return parameter
			$fcreturn = array (
				'id' 	=> @$this->_item->id,
				'cid'	=> $cid
			);
			$fcreturn = serialize($fcreturn);
			
			// Since we will check access for VIEW (=read) only, we skip checks if TASK Variable is set,
			// the edit() or add() or other controller task, will be responsible for checking permissions.
			if (@$this->_item->id && !JRequest::getVar('task', false) && !$skip_read_access_check )
			{
				//**************************************************
				// STEP a: Calculate read access and edit permission
				//**************************************************
				
				// a1. Calculate edit access ... 
				// NOTE: we will allow view access if current user can edit the item (but set a warning message about it, see bellow)
				if (FLEXI_J16GE) {
					$canedititem = $params->get('access-edit');
				} else if ($user->gid >= 25) {
					$canedititem = true;
				} else if (FLEXI_ACCESS) {
					$rights = FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $this->_item->id, $this->_item->catid );
					$canedititem = in_array('edit', $rights) || (in_array('editown', $rights) && $this->_item->created_by == $user->get('id'));
				} else {
					$canedititem = $user->authorize('com_content', 'edit', 'content', 'all') || ($user->authorize('com_content', 'edit', 'content', 'own') && $model->get('created_by') == $user->get('id'));
				}
				
				// a2. Calculate read access ... 
				if (FLEXI_J16GE) {
					$canreaditem = $params->get('access-view');
				} else {
					$canreaditem = FLEXI_ACCESS ? FAccess::checkAllItemReadAccess('com_content', 'read', 'users', $user->gmid, 'item', $this->_item->id) : $this->_item->access <= $aid;
				}
				
				//**********************************************************************************************
				// STEP b: Error that always terminate causing: 403 (forbidden) or 404 (not found) Server errors
				//**********************************************************************************************
				
				// b1. UNLESS the user can edit the item, do not allow item's VERSION PREVIEWING 
				$version = JRequest::getVar('version', 0, 'request', 'int' );          // the item version to load
				$current_version = FLEXIUtilities::getCurrentVersions($this->_id, true); // Get current item version
				if (($version!=$current_version) && !$canedititem)
				{
					// Terminate execution with a HTTP access denied / forbidden Server Error
					JError::raiseNotice(403,
						JText::_('FLEXI_ALERTNOTAUTH_PREVIEW_UNEDITABLE')."<br />".
						JText::_('FLEXI_ALERTNOTAUTH_TASK')."<br />".
						"Item id: ".$this->_item->id
					);
					$mainframe->redirect(JRoute::_(FlexicontentHelperRoute::getItemRoute($this->_item->slug, $this->_item->categoryslug)));
				}
				
				// b2. Check that item is PUBLISHED (1,-5) or ARCHIVED (-1), if we are not editing the item, we raise 404 error
				// NOTE: since item is unpublished, asking all users to login maybe wrong approach, so instead we raise 404 error
				$item_is_published = $this->_item->state == 1 || $this->_item->state == -5 || $this->_item->state == -1;
				if ( !$item_is_published && !$canedititem) {
					// Raise error that the item is unpublished
					JError::raiseError( 404, JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_UNPUBLISHED') );
				} else if ( !$item_is_published ) {
					// Item edittable, set warning that ...
					JError::raiseWarning( 404, JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_UNPUBLISHED') );
				}
				
				// b3. Check that item has scheduled or expired publication dates, if we are not editing the item, we raise 404 error
				// NOTE: since item is unpublished, asking all users to login maybe wrong approach, so instead we raise 404 error
				$item_is_scheduled = $this->_item->publication_scheduled;
				$item_is_expired   = $this->_item->publication_expired;
				if ( $item_is_published && ($item_is_scheduled && !$item_is_expired) && !$canedititem) {
					// Raise error that the item is scheduled for publication
					JError::raiseError( 404, JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_SCHEDULED') );
				} else if ( $item_is_published && ($item_is_scheduled && !$item_is_expired) ) {
					// Item edittable, set warning that ...
					JError::raiseWarning( 404, JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_SCHEDULED') );
				}
				if ( $item_is_published && $item_is_expired && !$canedititem) {
					// Raise error that the item is scheduled for publication
					JError::raiseError( 404, JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_EXPIRED') );
				} else if ( $item_is_published && $item_is_expired) {
					// Item edittable, set warning that ...
					JError::raiseWarning( 404, JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_EXPIRED') );
				}
				
				// Calculate if item is active, if not active and we have reached this point, it means that item is editable
				$item_active = $item_is_published && !$item_is_scheduled && !$item_is_expired;
				
				// b4. Check that current category is published (J1.5), for J1.6+ requires all ancestor categories from current one to the root,
				if ( $cid )
				{
					// NOTE:  J1.6+ all ancestor categories from current one to the root, for J1.5 only the current one ($cid)
					$cats_are_published = FLEXI_J16GE ? $this->_item->ancestor_cats_published : $globalcats[$cid]->published;
					$cats_np_err_mssg = JText::sprintf('FLEXI_CONTENT_UNAVAILABLE_ITEM_CURRCAT_UNPUBLISHED', $cid);
				}
				else
				{
					// cid is not set, we have no current category, the item is visible if it belongs to at one published category
					$itemcats = FLEXI_J16GE ? $this->getCatsselected() : $this->_item->categories;
					$cats_are_published = true;
					foreach ($itemcats as $catid) {
						$cats_are_published &= $globalcats[$catid]->published;
						if (FLEXI_J16GE) {  // For J1.6+ check all ancestor categories from current one to the root
							foreach($globalcats[$catid]->ancestorsarray as $pcid)    $cats_are_published &= $globalcats[$pcid]->published;
						}
					}
					$cats_np_err_mssg = JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_ALLCATS_UNPUBLISHED');
					
				}
				
				
				// if item 's categories are unpublished (and the user can edit the item), then TERMINATE execution with a HTTP not-found Server Error, 
				if (!$cats_are_published)
				{	
					if (!$cats_are_published && !$canedititem) {
						// Terminate execution with a HTTP not-found Server Error
						JError::raiseError( 404, $cats_np_err_mssg );
					} else if(!$cats_are_published && $item_active) {
						// Item edittable, set warning that item's (ancestor) category is unpublished
						JError::raiseWarning( 404, $cats_np_err_mssg );
					}
				}
				
				// Add current category state infor to the item active flag
				$item_active = $cats_are_published && $item_active;
				
				//***********************************************************************************
				// STEP c: CHECK READ access in relation to if user can edit and if the user is logged.
				// (a) allow access if user can edit item (b) redirect to login if user is unlogged
				// (c) redirect to the unauthorized page  (d) raise a 403 forbidden Server Error 
				//***********************************************************************************
				
				if ( !$item_active || (!$canreaditem && $canedititem) )  // if not active and we have reached this point, it means that item is editable
				{
					// (d) be allowed access because user can edit item, but set notice to the editors, that they are viewing unpublished/unreadable content
					$mainframe->enqueueMessage(JText::_('FLEXI_CONTENT_ACCESS_ALLOWED_BECAUSE_EDITABLE'), 'notice');
				}
				else if ( !$canreaditem && !$canedititem)
				{
					if($user->guest) {
						// (a) Redirect unlogged users to login
						$uri		= JFactory::getURI();
						$return		= $uri->toString();
						$com_users = FLEXI_J16GE ? 'com_users' : 'com_user';
						$url  = $cparams->get('login_page', 'index.php?option='.$com_users.'&view=login');
						$url .= '&return='.base64_encode($return);
						$url .= '&fcreturn='.base64_encode($fcreturn);
			
						JError::raiseWarning( 403, JText::sprintf("FLEXI_LOGIN_TO_ACCESS", $url));
						$mainframe->redirect( $url );
					} else {
						if ($cparams->get('unauthorized_page', '')) {
							// (b) Redirect to unauthorized_page
							JError::raiseNotice( 403, JText::_("FLEXI_ALERTNOTAUTH_VIEW"));
							$mainframe->redirect($cparams->get('unauthorized_page'));				
						} else {
							// (c) Raise 403 forbidden error
							JError::raiseError( 403, JText::_("FLEXI_ALERTNOTAUTH_VIEW"));
						}
					}
				} else {
					// User can read item and item is active, take no further action
				}
			}
			
			// Get the author email in order to display the gravatar
			$query = 'SELECT email'
			. ' FROM #__users'
			. ' WHERE id = '. (int) $this->_item->created_by
			;
			$this->_db->setQuery($query);
			$this->_item->creatoremail = $this->_db->loadResult();
			
			// Get fullname of creator
			if ($this->_item->created_by_alias) {
				$this->_item->creator = $this->_item->created_by_alias;
			} else {
				$query = 'SELECT name'
				. ' FROM #__users'
				. ' WHERE id = '. (int) $this->_item->created_by
				;
				$this->_db->setQuery($query);
				$this->_item->creator = $this->_db->loadResult();
			}

			// Get fullname of modifier
			if ($this->_item->created_by == $this->_item->modified_by) {
				$this->_item->modifier = $this->_item->creator;
			} else {
				$query = 'SELECT name'
				. ' FROM #__users'
				. ' WHERE id = '. (int) $this->_item->modified_by
				;
				$this->_db->setQuery($query);
				$this->_item->modifier = $this->_db->loadResult();
			}

			if ($this->_item->modified == $this->_db->getNulldate()) {
				$this->_item->modified = null;
			}
			
		}
		else
		{
			$user =& JFactory::getUser();
			$item =& JTable::getInstance('flexicontent_items', '');
			if ($user->authorize('com_flexicontent', 'state'))	{
				$item->state = 1;
			}
			$item->id						= 0;
			$item->author				= null;
			$item->created_by		= $user->get('id');
			$item->text					= '';
			$item->title				= null;
			$item->metadesc			= '';
			$item->metakey			= '';
			$item->type_id			= JRequest::getVar('typeid', 0, '', 'int');
			$item->typename			= null;
			$item->search_index		= '';
			$item->lang_parent_id = 0;
			$this->_item					= $item;
		}
		return $this->_item;
	}


	/**
	 * Method to load a specific unapproved (=non-current) item version, merging the loaded version into the given item object
	 * @param	object	$data				The item object into which we will insert the loaded version data
	 * @param	int			$version		The version number of the item version to load
	 * @return   object 					The altered item object 
	 */
	function &loadUnapprovedVersion(&$data, $version) {
		$db = &JFactory::getDBO();
		$jfields = flexicontent_html::getJCoreFields(NULL, true, true);
		$query = "SELECT f.field_type,f.name,ftr.field_id, iv.valueorder, value FROM #__flexicontent_items_versions as iv"
			. " JOIN #__flexicontent_fields_type_relations as ftr ON iv.field_id=ftr.field_id"
			. " JOIN #__flexicontent_fields as f ON iv.field_id=f.id"
			. " WHERE ftr.type_id='{$data->type_id}' AND iv.version='{$version}' AND iv.item_id='{$data->id}'"
			. " AND published='1' AND iscore='1'"
			;
		$db->setQuery($query);
		$objs = $db->loadObjectList();
		$objs = $objs ? $objs : array();
		foreach($objs as $obj) {
			$v = @json_decode($obj->value);
			if($v) {
				$obj->value = $v;
			}
			if(isset($jfields[$obj->field_type]) && isset($data->$jfields[$obj->field_type])) {
				if(@json_decode($obj->value)) {
					$data->$jfields[$obj->field_type] = json_decode($obj->value);
				}else{
					$data->$jfields[$obj->field_type] = $obj->value;
				}
			}
		}
		return $data;
	}


	/**
	 * Method to load required data for J1.6+
	 *
	 * @access	private
	 * @return	array
	 * @since	1.0
	 */
	function _loadItem_j16ge()
	{
		static $unapproved_version_notice;
		$mainframe = & JFactory::getApplication();
		$itemid  = JRequest::getInt('id', 0);
		$task    = JRequest::getVar('task', false);
		$view    = JRequest::getVar('view', false);
		$option  = JRequest::getVar('option', false);
		$cparams =& $this->_cparams;
		$use_versioning = $cparams->get('use_versioning', 1);
		
		// Retrieve item if not already retrieved, null indicates cleared item data, e.g. because of changed item id
		if ( $this->_item === null ) {
			
			// Variables controlling the version loading logic
			$loadcurrent = JRequest::getVar('loadcurrent', false, 'request', 'boolean');  // loadcurrent request flag, ignored if version specified
			$preview = JRequest::getVar('preview', false, 'request', 'boolean');   // preview request flag for viewing unapproved version in frontend
			$version = JRequest::getVar('version', 0, 'request', 'int' );          // the item version to load
			
			// -- Decide the version to load: (a) the one specified by request or (b) the current one or (c) the last one
			$current_version = FLEXIUtilities::getCurrentVersions($this->_id, true); // Get current item version
			$last_version    = FLEXIUtilities::getLastVersions($this->_id, true);    // Get last version (=latest one saved, highest version id), 
			$last_version = $use_versioning ? $last_version : $current_version;  // if not using versioning the current one is forced as last
						
			// check that version to load was given in URL, we will use $loadcurrent variable to set it to either current one or last (latest) one
			if ( $version==0 ) {
				// version to load is not set in request URL, set it as described above and also set in the url
				$version = ($loadcurrent && !$preview) ? $current_version : $last_version;
			}
			JRequest::setVar( 'version', $version );
			
			// check if not loading the current version while we are in edit form, and raise a notice to inform the user
			if ($current_version != $version && $task=='edit' && $option=='com_flexicontent' && !$unapproved_version_notice) {
				$unapproved_version_notice = 1;
				JError::raiseNotice(10,
					JText::_('FLEXI_LOADING_UNAPPROVED_VERSION_NOTICE') . ' :: ' .
					JText::sprintf('FLEXI_LOADED_VERSION_INFO_NOTICE', $version, $current_version)
				);
			}
			
			try {
				
				$db = $this->getDbo();
				$query = $db->getQuery(true);
				$cid	= $this->_cid;  // CURRENT CATEGORY
				$limit_to_cid = $this->_cid ? ' AND rel.catid = '. (int) $this->_cid : ' AND rel.catid = a.catid';

				$query->select($this->getState(
					'item.select', 'a.id, a.asset_id, a.title, a.alias, a.title_alias, a.introtext, a.fulltext, ' .
					// If badcats.id is not null, this means that the item is inside in an unpublished ancestor category
					'a.state, CASE WHEN badcats.id is null THEN 1 ELSE 0 END AS ancestor_cats_published, ' .
					'a.mask, a.catid, a.created, a.created_by, a.created_by_alias, ' .
					'a.modified, a.modified_by, a.checked_out, a.checked_out_time, a.publish_up, a.publish_down, ' .
					'a.images, a.urls, a.attribs, a.version, a.parentid, a.ordering, ' .
					'a.metakey, a.metadesc, a.access, a.hits, a.metadata, a.featured, a.language, a.xreference'.(($version!=$current_version)?',ver.version_id':'')
					)
				);
				$query->from('#__content AS a');
				
				$query->select('ie.*, ty.name AS typename, ty.alias as typealias, c.lft,c.rgt');
				$query->join('LEFT', '#__flexicontent_items_ext AS ie ON ie.item_id = a.id');
				$query->join('LEFT', '#__flexicontent_types AS ty ON ie.type_id = ty.id');
				$query->join('LEFT', '#__flexicontent_cats_item_relations AS rel ON rel.itemid = a.id' . $limit_to_cid);

				$nullDate = $db->Quote($db->getNullDate());
				$nowDate = $db->Quote(JFactory::getDate()->toMySQL());
				
				// Join on category table.
				$query->select('c.title AS category_title, c.alias AS category_alias, c.access AS category_access');
				$query->select( 'CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug');
				$query->select( 'CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug');
				$query->select( 'CASE WHEN a.publish_up = '.$nullDate.' OR a.publish_up <= '.$nowDate.' THEN 0 ELSE 1 END as publication_scheduled');
				$query->select( 'CASE WHEN a.publish_down = '.$nullDate.' OR a.publish_down >= '.$nowDate.' THEN 0 ELSE 1 END as publication_expired' );
				
				$query->join('LEFT', '#__categories AS c on c.id = rel.catid');
				//$query->join('LEFT', '#__categories AS c on c.id = a.catid');

				// Join on user table.
				$query->select('u.name AS author');
				$query->join('LEFT', '#__users AS u on u.id = a.created_by');

				// Join on contact table
				$query->select('contact.id as contactid' ) ;
				$query->join('LEFT','#__contact_details AS contact on contact.user_id = a.created_by');

				// Join over the categories to get parent category titles
				$query->select('parent.title as parent_title, parent.id as parent_id, parent.path as parent_route, parent.alias as parent_alias');
				$query->join('LEFT', '#__categories as parent ON parent.id = c.parent_id');

				// Join on voting table
				$query->select('ROUND( v.rating_sum / v.rating_count ) AS rating, v.rating_count as rating_count');
				$query->join('LEFT', '#__content_rating AS v ON a.id = v.content_id');

				$query->where('a.id = ' . (int) $this->_id);

				// Filter by start and end dates.
				//$query->where('(a.publish_up = ' . $nullDate . ' OR a.publish_up <= ' . $nowDate . ')');
				//$query->where('(a.publish_down = ' . $nullDate . ' OR a.publish_down >= ' . $nowDate . ')');

				// Join to check for category published state in parent categories up the tree
				// If all categories are published, badcats.id will be null, and we just use the article state
				$subquery = ' (SELECT cat.id as id FROM #__categories AS cat JOIN #__categories AS parent ';
				$subquery .= 'ON cat.lft BETWEEN parent.lft AND parent.rgt ';
				$subquery .= 'WHERE parent.extension = ' . $db->quote('com_content');
				$subquery .= ' AND parent.published <= 0 GROUP BY cat.id)';
				$query->join('LEFT OUTER', $subquery . ' AS badcats ON badcats.id = c.id');
				
				//preview mode
				if($version!=$current_version) {
					$query->join('LEFT', '#__flexicontent_versions AS ver ON ver.item_id = a.id AND ver.version_id = '. $db->Quote($version) );
				}

				// !!! COMMENTED OUT we check publication state after loading the item, in order to provide BETTER messages
				/*
				// Filter by published state.
				$published = $this->getState('filter.published');
				$archived = $this->getState('filter.archived');

				if (is_numeric($published)) {
					$query->where('(a.state = ' . (int) $published . ' OR a.state =' . (int) $archived . ')');
				}
				*/

				// Execute query and load the data as an object
				$db->setQuery($query);
				$item = $db->loadObject();
				
				// Check for SQL error
				if ($error = $db->getErrorMsg()) {
					throw new Exception( nl2br($query."\n".$error()."\n") );
				}
				
				if(!$item) return false; // item not found, return				
				
				// When previewing load the specified item version
				if ($version!=$current_version) {
					$item = $this->loadUnapprovedVersion($item, $version);
				}

				// Check for empty data despite item id being set, and raise 404 not found Server Error
				if ( empty($item) && @$this->_id ) {
					JError::raiseError(404, JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_NOT_FOUND')."<br />"."Item id: ".@$this->_id);
				}

				// !!! COMMENTED OUT, we check publication state in with a more detailed mechanism,
				// because we have more states and also want to provide better messages
				/*
				// Check for published state if filter set.
				if (((is_numeric($published)) || (is_numeric($archived))) && (($data->state != $published) && ($data->state != $archived))) {
					return JError::raiseError(404,JText::_('The item is not published'));
				}
				*/
				
				// Convert metadata string to parameters (object)
				$registry = new JRegistry;
				$registry->loadString($item->metadata);
				$item->metadata = $registry;

				// Assign to the item data member variable
				$this->_item = &$item;
				
				// Finally load parameters and merge them with global, content type, category, etc
				$this->_loadItemParams();
				
				// return true if item was loaded successfully
				return (boolean) $this->_item;
			}
			catch (JException $e)
			{
				
				if ($e->getCode() == 404) {
					// Need to go thru the error handler to allow Redirect to work.
					JError::raiseError(404, $e->getMessage());
				}
				else {
					$this->setError($e);
					$this->_item = false;
				}
				
			}
		}
		return true;
	}


	/**
	 * Method to load required data for J1.5
	 *
	 * @access	private
	 * @return	array
	 * @since	1.0
	 */
	function _loadItem_j15()
	{
		static $unapproved_version_notice;
		$mainframe = & JFactory::getApplication();
		$itemid  = JRequest::getInt('id', 0);
		$task    = JRequest::getVar('task', false);
		$view    = JRequest::getVar('view', false);
		$option  = JRequest::getVar('option', false);
		$cparams =& $this->_cparams;
		$use_versioning = $cparams->get('use_versioning', 1);
		
		// Retrieve item if not already retrieved, null indicates cleared item data, e.g. because of changed item id
		if ( $this->_item === null ) {
			
			// Variables controlling the version loading logic
			$loadcurrent = JRequest::getVar('loadcurrent', false, 'request', 'boolean');  // loadcurrent request flag, ignored if version specified
			$preview = JRequest::getVar('preview', false, 'request', 'boolean');   // preview request flag for viewing unapproved version in frontend
			$version = JRequest::getVar('version', 0, 'request', 'int' );          // the item version to load
			
			// -- Decide the version to load: (a) the one specified by request or (b) the current one or (c) the last one
			$current_version = FLEXIUtilities::getCurrentVersions($this->_id, true); // Get current item version
			$last_version    = FLEXIUtilities::getLastVersions($this->_id, true);    // Get last version (=latest one saved, highest version id), 
			$last_version = $use_versioning ? $last_version : $current_version;  // if not using versioning the current one is forced as last
						
			// check that version to load was given in URL, we will use $loadcurrent variable to set it to either current one or last (latest) one
			if ( $version==0 ) {
				// version to load is not set in request URL, set it as described above and also set in the url
				$loadcurrent ." ? ". $current_version. " : ". $last_version."<br>";
				$version = ($loadcurrent && !$preview) ? $current_version : $last_version;
			}
			JRequest::setVar( 'version', $version );
			
			// check if not loading the current version while we are in edit form, and raise a notice to inform the user
			if ($current_version != $version && $task=='edit' && $option=='com_flexicontent' && !$unapproved_version_notice) {
				$unapproved_version_notice = 1;
				JError::raiseNotice(10,
					JText::_('FLEXI_LOADING_UNAPPROVED_VERSION_NOTICE') . ' :: ' .
					JText::sprintf('FLEXI_LOADED_VERSION_INFO_NOTICE', $version, $current_version)
				);
			}
			
			try {
								
				$jnow		=& JFactory::getDate();
				$now		= $jnow->toMySQL();
				$nullDate	= $this->_db->getNullDate();
				$version_join =  ($version!=$current_version) ? ' LEFT JOIN #__flexicontent_versions AS ver ON ver.item_id = i.id AND ver.version_id = '. $this->_db->Quote($version) : '';
				$limit_to_cid = $this->_cid ? ' AND rel.catid = '. (int) $this->_cid : ' AND rel.catid = i.catid';
				$where	= $this->_buildItemWhere();
				
				$query = 'SELECT i.*, ie.*, c.access AS cataccess, c.id AS catid, c.published AS catpublished,'
				. ' u.name AS author, u.usertype, ty.name AS typename,'
				. ' CASE WHEN i.publish_up = '.$this->_db->Quote($nullDate).' OR i.publish_up <= '.$this->_db->Quote($now).' THEN 0 ELSE 1 END as publication_scheduled,'
				. ' CASE WHEN i.publish_down = '.$this->_db->Quote($nullDate).' OR i.publish_down >= '.$this->_db->Quote($now).' THEN 0 ELSE 1 END as publication_expired,'
				. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
				. (($version!=$current_version) ? ',ver.version_id' : '')
				. ' FROM #__content AS i'
				. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
				. ' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
				. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id' . $limit_to_cid
				. ' LEFT JOIN #__categories AS c ON c.id = rel.catid'
				. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
				. $version_join 
				. $where
				;
				
				// Execute query and load the data as an object
				$this->_db->setQuery($query);
				$data = $this->_db->loadObject();
				
				// Check for SQL error
				if ($error = $this->_db->getErrorMsg()) {
					throw new Exception( nl2br($query."\n".$error()."\n") );
				}
				
				if(!$data) return false; // item not found, return				
				
				// When previewing load the specified item version
				if ($version!=$current_version) {
					$data = $this->loadUnapprovedVersion($data, $version);
				}
				
				// Check for empty data despite item id being set, and raise 404 not found Server Error
				if ( empty($data) && @$this->_id ) {
					JError::raiseError(404, JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_NOT_FOUND')."<br />"."Item id: ".@$this->_id);
				}
				
				$item = & $data;
				//$item->introtext=""; $item->fulltext=""; $item->search_index=""; echo "<pre>"; print_r($item); echo "</pre>"; exit;
				
				// -- Get by (a) the table that contains versioned data, or by (b) the normal table (current version data only)
				if ($use_versioning) 
				{
					$query = "SELECT f.id,iv.value,f.field_type,f.name FROM #__flexicontent_items_versions as iv "
						." LEFT JOIN #__flexicontent_fields as f on f.id=iv.field_id "
						." WHERE iv.version='".$version."' AND iv.item_id='".$this->_id."';";
				}
				else
				{
					$query = "SELECT f.id,iv.value,f.field_type,f.name FROM #__flexicontent_fields_item_relations as iv "
						." LEFT JOIN #__flexicontent_fields as f on f.id=iv.field_id "
						." WHERE iv.item_id='".$this->_id."';";
				}
				$this->_db->setQuery($query);
				$fields = $this->_db->loadObjectList();
				$fields = $fields?$fields:array();
				
				// -- Create the description field called 'text' by appending introtext + readmore + fulltext
				$item->text = $item->introtext;
				if (JString::strlen($item->fulltext) > 1) {
					$item->text .= '<hr id="system-readmore" />' . $item->fulltext;
				}
				
				// (Fix for issue 261), not overwrite joomfish data with versioned data
				
				// -- Retrieve joomfish data for current language if it exists (we will use them on next step instead of versioned data)
				if (FLEXI_FISH) {
					// a. Find if item language is different than current language 
					$currlang = JRequest::getWord('lang', '' );
					if(empty($currlang)){
						$langFactory= JFactory::getLanguage();
						$tagLang = $langFactory->getTag();
						// This more compatible than using the full lenght language tag since its second part maybe some non-standard country
						$currlang = substr($tagLang ,0,2);
					}
					$itemlang = substr($item->language ,0,2);
					$langdiffers = ( $currlang != $itemlang );
					
					// b. Retrieve joomfish data so that if they exist we will not overwrite with versioned data
					if ($langdiffers) {
						$query = "SELECT jfc.* FROM #__jf_content as jfc "
								." LEFT JOIN #__languages as jfl ON jfc.language_id = jfl.id"
								." WHERE jfc.reference_table='content' AND jfc.reference_id = {$this->_id} "
								." AND jfc.published=1 AND jfl.shortcode=".$this->_db->Quote($currlang);
						$this->_db->setQuery($query);
						$jf_data = $this->_db->loadObjectList('reference_field');
						if ($jf_data===false) {
						 die('Error while trying to retrieve (if the exist) item\'s joomfish for current language'.$this->_db->getErrorMsg());
						}
					}
				}
				
				// -- Overwrite item fields with the requested VERSION data, !! we do not overwrite fields that must be translated by joomfish
				foreach($fields as $f) {
					
					// Skip using versioned data for fields that must be translated by joomfish, we ONLY skip if joomfish data exists (Fix for issue 261)
					if (FLEXI_FISH) {
						$jf_translated_fields = array('title', 'text', 'introtext', 'fulltext' );
						if ( $task != 'edit' && $langdiffers && in_array($f->name, $jf_translated_fields) ) {
							// if joomfish translation exists for this field, then skip the versioned value and use joomfish value
							if ( !isset($jf_data->{$f->name}) ) continue;
						}
					}
					
					// Use versioned data, by overwriting the item data 
					$fieldname = $f->name;
					if( (($f->field_type=='categories') && ($f->name=='categories')) || (($f->field_type=='tags') && ($f->name=='tags')) ) {
						$item->$fieldname = unserialize($f->value);
					} else if ($fieldname) {
						$item->$fieldname = $f->value;
					}
				}
				
				// -- Retrieve tags (THESE ARE NOT VERSIONED ??? why are they in FC v2.x ?)
				if (!isset($item->tags) || !is_array($item->tags)) {
					$query = 'SELECT DISTINCT tid FROM #__flexicontent_tags_item_relations WHERE itemid = ' . (int)$this->_id;
					$this->_db->setQuery($query);
					$item->tags = $this->_db->loadResultArray();
				}
				
				// -- Retrieve categories (THESE ARE NOT VERSIONED)
				if (!isset($item->categories) || !is_array($item->categories)) {
					$query = 'SELECT DISTINCT catid FROM #__flexicontent_cats_item_relations WHERE itemid = ' . (int)$this->_id;
					$this->_db->setQuery($query);
					$item->categories = $this->_db->loadResultArray();
				}
				$item->id = $this->_id;
				
				// -- Retrieve item TYPE parameters, and ITEM ratings (THESE ARE NOT VERSIONED)
				$query = "SELECT t.name as typename, t.alias as typealias, cr.rating_count, ((cr.rating_sum / cr.rating_count)*20) as score"
						." FROM #__flexicontent_items_ext as ie "
						. " LEFT JOIN #__content_rating AS cr ON cr.content_id = ie.item_id"
						." LEFT JOIN #__flexicontent_types AS t ON ie.type_id = t.id"
						." WHERE ie.item_id='".$this->_id."';";
				$this->_db->setQuery($query);
				$type = $this->_db->loadObject();
				
				if ($type) {
					$item->typename = $type->typename;
					$item->typealias = $type->typealias;
					$item->rating_count = $type->rating_count;
					$item->score = $type->score;
					$item->version = $current_version;
				} else {
					$item->version = 0;
					$item->score = 0;
				}
				
				// Assign to the item data member variable
				$this->_item = &$item;
				
				// -- Detect if current version doesnot exist in version table and add it !!!
				if ( $use_versioning && $current_version > $last_version ) {
					// Add current version.
					$mainframe = &JFactory::getApplication();
					$query = "SELECT f.id,fir.value,f.field_type,f.name,fir.valueorder "
							." FROM #__flexicontent_fields_item_relations as fir"
							//." LEFT JOIN #__flexicontent_items_versions as iv ON iv.field_id="
							." LEFT JOIN #__flexicontent_fields as f on f.id=fir.field_id "
							." WHERE fir.item_id='".$this->_id."';";
					$this->_db->setQuery($query);
					$fields = $this->_db->loadObjectList();
					$jcorefields = flexicontent_html::getJCoreFields();
					
					$catflag = false;
					$tagflag = false;
					$clean_database = true;
					
					// Delete from db, the field values of old current version
					if(!$clean_database && $fields) {
						$query = 'DELETE FROM #__flexicontent_fields_item_relations WHERE item_id = '.$this->_id;
						$this->_db->setQuery($query);
						$this->_db->query();
					}
					
					// Add to db, the field values of new current version
					foreach($fields as $field) {
						$obj = new stdClass();
						$obj->field_id 		= $field->id;
						$obj->item_id 		= $this->_id;
						$obj->valueorder	= $field->valueorder;
						$obj->version		= (int)$current_version;
						// @TODO : move in the plugin code
						if( ($field->field_type=='categories') && ($field->name=='categories') ) {
							continue;
						}elseif( ($field->field_type=='tags') && ($field->name=='tags') ) {
							continue;
						}else{
							$obj->value			= $field->value;
						}
						$this->_db->insertObject('#__flexicontent_items_versions', $obj);
						if( !$clean_database && !isset($jcorefields[$field->name]) && !in_array($field->field_type, $jcorefields)) {
							unset($obj->version);
							$this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
						}
						//$searchindex 	.= @$field->search;
					}
					
					// Special handling for saving values of field type 'categories'
					if(!$catflag) {
						$obj = new stdClass();
						$obj->field_id 		= 13;
						$obj->item_id 		= $this->_id;
						$obj->valueorder	= 1;
						$obj->version		= (int)$current_version;
						$obj->value		= serialize($item->categories);
						$this->_db->insertObject('#__flexicontent_items_versions', $obj);
					}
					
					// Special handling for saving values of field type 'tags'
					if(!$tagflag) {
						$obj = new stdClass();
						$obj->field_id 		= 14;
						$obj->item_id 		= $this->_id;
						$obj->valueorder	= 1;
						$obj->version		= (int)$current_version;
						$obj->value		= serialize($item->tags);
						$this->_db->insertObject('#__flexicontent_items_versions', $obj);
					}
					
					// Save meta data of the new item version
					$v = new stdClass();
					$v->item_id 		= (int)$item->id;
					$v->version_id	= (int)$current_version;
					$v->created 	= $item->created;
					$v->created_by 	= $item->created_by;
					//$v->comment		= 'kept current version to version table.';
					$this->_db->insertObject('#__flexicontent_versions', $v);
				}
				
				// Finally load parameters and merge them with global, content type, category, etc
				$this->_loadItemParams();
				
				// return true if item was loaded successfully
				return (boolean) $this->_item;
			}
			catch (JException $e)
			{
				
				if ($e->getCode() == 404) {
					// Need to go thru the error handler to allow Redirect to work.
					JError::raiseError(404, $e->getMessage());
				}
				else {
					$this->setError($e);
					$this->_item = false;
				}
			}
		}
		return true;
	}
	
	/**
	 * Method to build the WHERE clause of the query to select a content item
	 *
	 * @access	private
	 * @return	string	WHERE clause
	 * @since	1.5
	 */
	function _buildItemWhere()
	{
		$mainframe =& JFactory::getApplication();

		$user		=& JFactory::getUser();
		$aid		= !FLEXI_J16GE ? (int) $user->get('aid', 0) : max ($user->getAuthorisedViewLevels()) ;

		$jnow		=& JFactory::getDate();
		$now		= $jnow->toMySQL();
		$nullDate	= $this->_db->getNullDate();

		//
		// First thing we need to do is assert that the content article is the one
		// we are looking for and we have access to it.
		//
		$where = ' WHERE i.id = '. (int) $this->_id;
		
		// Commented out to allow retrieval of the item and set appropriate 404 Server Error for expired/scheduled items
		//if ($aid < 2)
		//{
		//	$where .= ' AND ( i.publish_up = '.$this->_db->Quote($nullDate).' OR i.publish_up <= '.$this->_db->Quote($now).' )';
		//	$where .= ' AND ( i.publish_down = '.$this->_db->Quote($nullDate).' OR i.publish_down >= '.$this->_db->Quote($now).' )';
		//}

		return $where;
	}


	/**
	 * Method to load content article parameters
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	function _loadItemParams()
	{
		if (!empty($this->_item->parameters)) return;
		
		$mainframe = & JFactory::getApplication();
		jimport('joomla.html.parameter');

		// Get the page/component configuration (Priority 4) (WARNING: merges menu parameters in J1.5 but not in J1.6+)
		$cparams = clone($mainframe->getParams('com_flexicontent'));
		$params = & $cparams;
		
		// In J1.6+ the above function does not merge current menu item parameters, it behaves like JComponentHelper::getParams('com_flexicontent') was called
		if (FLEXI_J16GE) {
			if ($menu = JSite::getMenu()->getActive()) {
				$menuParams = new JRegistry;
				$menuParams->loadJSON($menu->params);
				$params->merge($menuParams);
				//echo "Menu params: ".$menuParams->get('addcat_title', 'not set')."<br>";
			}
		}
		//echo "Component/menu params: ".$params->get('addcat_title', 'not set')."<br>";

		// Merge parameters from current category (Priority 3)
		if ( $this->_cid ) {
			// Retrieve ...
			$query = 'SELECT c.params'
					. ' FROM #__categories AS c'
					. ' WHERE c.id = ' . (int) $this->_cid
					;
			$this->_db->setQuery($query);
			$catparams = $this->_db->loadResult();
			$catparams = new JParameter($catparams);
			
			// Prevent some params from propagating ...
			$catparams->set('show_title', '');
			
			// Merge ...
			$params->merge($catparams);
			//echo "Cat params: ".$catparams->get('addcat_title', 'not set')."<br>";
		}
		
		$query = 'SELECT t.attribs'
				. ' FROM #__flexicontent_types AS t'
				. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.type_id = t.id'
				. ' WHERE ie.item_id = ' . (int)$this->_id
				;
		$this->_db->setQuery($query);
		$typeparams = $this->_db->loadResult();
		
		// Merge TYPE parameters into the page configuration (Priority 2)
		$typeparams = new JParameter($typeparams);
		$params->merge($typeparams);
		//echo "Type params: ".$typeparams->get('addcat_title', 'not set')."<br>";

		// Merge ITEM parameters into the page configuration (Priority 1)
		if ( is_string($this->_item->attribs) ) {
			$itemparams = new JParameter($this->_item->attribs);
		} else {
			$itemparams = & $this->_item->attribs;
		}
		$params->merge($itemparams);
		//echo "Item params: ".$itemparams->get('addcat_title', 'not set')."<br>";
		//echo "Item MERGED params: ".$params->get('addcat_title', 'not set')."<br>";

		// Merge ACCESS permissions into the page configuration (Priority 0)
		if (FLEXI_J16GE) {
			$accessperms = $this->getItemAccess();
			$params->merge($accessperms);
		}
		
		// Set the article object's parameters
		$this->_item->parameters = & $params;
	}
	

	/**
	 * Method to increment the hit counter for the item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function hit() {
		if ( $this->_id )
		{
			$item = & JTable::getInstance('flexicontent_items', '');
			$item->hit($this->_id);
			return true;
		}
		return false;
	}

	function getAlltags() {
		$query = 'SELECT * FROM #__flexicontent_tags ORDER BY name';
		$this->_db->setQuery($query);
		$tags = $this->_db->loadObjectlist();
		return $tags;
	}

	
	/**
	 * Method to get the tags
	 *
	 * @access	public
	 * @return	object
	 * @since	1.0
	 */
	/*function getTagsX() {
		$query = 'SELECT DISTINCT t.name,'
		. ' CASE WHEN CHAR_LENGTH(t.alias) THEN CONCAT_WS(\':\', t.id, t.alias) ELSE t.id END as slug'
		. ' FROM #__flexicontent_tags AS t'
		. ' LEFT JOIN #__flexicontent_tags_item_relations AS i ON i.tid = t.id'
		. ' WHERE i.itemid = ' . (int) $this->_id
		. ' AND t.published = 1'
		. ' ORDER BY t.name'
		;

		$this->_db->setQuery( $query );

		$this->_tags = $this->_db->loadObjectList();

		return $this->_tags;
	}*/
	
	/**
	 * Method to fetch tags
	 * 
	 * @return object
	 * @since 1.0
	 */
	/*function gettags($mask="")
	{
		$where = ($mask!="")?" name like '%$mask%' AND":"";
		$query = 'SELECT * FROM #__flexicontent_tags WHERE '.$where.' published = 1 ORDER BY name';
		$this->_db->setQuery($query);
		$tags = $this->_db->loadObjectlist();
		return $tags;
	}*/
	
	
	/**
	 * Method to get the categories
	 *
	 * @access	public
	 * @return	object
	 * @since	1.0
	 */
	/*function getCategories()
	{
		$query = 'SELECT DISTINCT c.id, c.title,'
		. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as slug'
		. ' FROM #__categories AS c'
		. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.catid = c.id'
		. ' WHERE rel.itemid = '.$this->_id
		;

		$this->_db->setQuery( $query );

		$this->_cats = $this->_db->loadObjectList();
		return $this->_cats;
	}*/

	/**
	 * Method to store a vote
	 * Deprecated
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	/*function storevote($id, $vote)
	{
		if ($vote == 1) {
			$target = 'plus';
		} elseif ($vote == 0) {
			$target = 'minus';
		} else {
			return false;
		}

		$query = 'UPDATE #__flexicontent_items_ext'
		.' SET '.$target.' = ( '.$target.' + 1 )'
		.' WHERE item_id = '.(int)$id
		;
		$this->_db->setQuery($query);
		$this->_db->query();

		return true;
	}*/



	/**
	 * Method to store the tag
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	/*function storetag($data)
	{
		$row  =& $this->getTable('flexicontent_tags', '');

		// bind it to the table
		if (!$row->bind($data)) {
			JError::raiseError(500, $this->_db->getErrorMsg() );
			return false;
		}

		// Make sure the data is valid
		if (!$row->check()) {
			$this->setError($row->getError());
			return false;
		}

		// Store it in the db
		if (!$row->store()) {
			JError::raiseError(500, $this->_db->getErrorMsg() );
			return false;
		}
		$this->_tag = &$row;
		return $row->id;
	}*/

	/**
	 * Method to add a tag
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	/*function addtag($name) {
		$obj = new stdClass();
		$obj->name	 	= $name;
		$obj->published	= 1;

		//$this->storetag($obj);
		if($this->storetag($obj)) {
			return true;
		}
		return false;
	}*/

	/**
	 * Method to get the nr of favourites of anitem
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function getFavourites()
	{
		$query = 'SELECT COUNT(id) AS favs FROM #__flexicontent_favourites WHERE itemid = '.(int)$this->_id;
		$this->_db->setQuery($query);
		$favs = $this->_db->loadResult();
		return $favs;
	}

	/**
	 * Method to get the nr of favourites of an user
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function getFavoured()
	{
		$user = JFactory::getUser();

		$query = 'SELECT COUNT(id) AS fav FROM #__flexicontent_favourites WHERE itemid = '.(int)$this->_id.' AND userid= '.(int)$user->id;
		$this->_db->setQuery($query);
		$fav = $this->_db->loadResult();
		return $fav;
	}
	
	/**
	 * Method to remove a favourite
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function removefav()
	{
		$user = JFactory::getUser();

		$query = 'DELETE FROM #__flexicontent_favourites WHERE itemid = '.(int)$this->_id.' AND userid = '.(int)$user->id;
		$this->_db->setQuery($query);
		$remfav = $this->_db->query();
		return $remfav;
	}
	
	/**
	 * Method to add a favourite
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function addfav()
	{
		$user = JFactory::getUser();

		$obj = new stdClass();
		$obj->itemid 	= $this->_id;
		$obj->userid	= $user->id;

		$addfav = $this->_db->insertObject('#__flexicontent_favourites', $obj);
		return $addfav;
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
		$user 	=& JFactory::getUser();

		if ( $id )
		{
			$v = FLEXIUtilities::getCurrentVersions((int)$id);
			
			$query = 'UPDATE #__content'
				. ' SET state = ' . (int)$state
				. ' WHERE id = '.(int)$id
				. ' AND ( checked_out = 0 OR ( checked_out = ' . (int) $user->get('id'). ' ) )'
			;
			$this->_db->setQuery( $query );
			if (!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}

			$query = 'UPDATE #__flexicontent_items_versions'
				. ' SET value = ' . (int)$state
				. ' WHERE item_id = '.(int)$id
				. ' AND valueorder = 1'
				. ' AND field_id = 10'
				. ' AND version = ' . $v['version']
				;
			$this->_db->setQuery( $query );
			if (!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
		}
		return true;
	}


	
	/**
	 * Method to get advanced search fields which belongs to the item type
	 * 
	 * @return object
	 * @since 1.5
	 */
	function getAdvSearchFields($search_fields) {
		$where = " WHERE `name` IN ({$search_fields}) AND fi.isadvsearch='1'";
		$query = 'SELECT fi.*'
			.' FROM #__flexicontent_fields AS fi'
			.' LEFT JOIN #__flexicontent_fields_type_relations AS ftrel ON ftrel.field_id = fi.id'
			//.' LEFT JOIN #__flexicontent_items_ext AS ie ON ftrel.type_id = ie.type_id'
			.$where
			.' AND fi.published = 1'
			.' GROUP BY fi.id'
			.' ORDER BY ftrel.ordering, fi.ordering, fi.name'
		;
		$this->_db->setQuery($query);
		$fields = $this->_db->loadObjectList('name');
		foreach ($fields as $field) {
			$field->item_id		= 0;
			$field->value 		= $this->getExtrafieldvalue($field->id, 0);
			$path = JPATH_ROOT.DS.'plugins'.DS.'flexicontent_fields'.DS.$field->field_type.'.xml';
			$field->parameters 	= new JParameter($field->attribs, $path);
		}
		return $fields;
	}
}
?>
