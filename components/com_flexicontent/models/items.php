<?php
/**
 * @version 1.5 stable $Id$
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
require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'parentclassitem.php');

/**
 * FLEXIcontent Component Item Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelItems extends ParentClassItem
{
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
	 * Method to CHECK item's -VIEWING- ACCESS, this could be moved to the controller,
	 * if we do this, then we must check the view variable, because DISPLAY() CONTROLLER TASK
	 * is shared among all views ... or create a separate FRONTEND controller for the ITEM VIEW
	 *
	 * @access	private
	 * @return	array
	 * @since	1.5
	 */
	function _check_viewing_access()
	{
		global $globalcats;
		$app  = JFactory::getApplication();
		$user	= JFactory::getUser();
		$aid	= (int) $user->get('aid');
		$gid	= (int) $user->get('gid');
		$cid	= $this->_cid;
		$params = $this->_item->parameters;
		$cparams = $this->_cparams;
		
		$fcreturn = serialize( array('id'=>@$this->_item->id, 'cid'=>$cid) );     // a special url parameter, used by some SEF code
		$referer = @$_SERVER['HTTP_REFERER'];                                      // the previously viewed page (refer)
		// a basic item title string
		$title_str = "<br />". JText::_('FLEXI_TITLE').": ".$this->_item->title.'[id: '.$this->_item->id.']';
		
		// Since we will check access for VIEW (=read) only, we skip checks if TASK Variable is set,
		// the edit() or add() or other controller task, will be responsible for checking permissions.
		if	(	@$this->_item->id  // not new item
				&& !JRequest::getVar('task', false) // skip various task checked at the controller
				&& JRequest::getVar('view')==FLEXI_ITEMVIEW		// must be in item(s) view
				)
		{
			//*************************************************************
			// STEP A: Calculate ownership, edit permission and read access
			// (a) isOwner, (b) canedititem, (c) canviewitem
			//*************************************************************
			
			// (a) Calculate if owned by current user
			$isOwner = $this->_item->created_by== $user->get('id');
			
			// (b) Calculate edit access ... 
			// NOTE: we will allow view access if current user can edit the item (but set a warning message about it, see bellow)
			if (FLEXI_J16GE) {
				$canedititem = $params->get('access-edit');
				$caneditstate = $params->get('access-edit-state');
			} else if ($user->gid >= 25) {
				$canedititem = true;
				$caneditstate = true;
			} else if (FLEXI_ACCESS) {
				$rights = FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $this->_item->id, $this->_item->catid );
				$canedititem = in_array('edit', $rights) || (in_array('editown', $rights) && $isOwner);
				$caneditstate = in_array('publish', $rights) || (in_array('publish', $rights) && $isOwner);
			} else {
				$canedititem = $user->authorize('com_content', 'edit', 'content', 'all') || ($user->authorize('com_content', 'edit', 'content', 'own') && $isOwner);
				$caneditstate = $user->authorize('com_content', 'publish', 'content', 'all');
			}
			
			// (c) Calculate read access ... 
			if (FLEXI_J16GE) {
				$canviewitem = $params->get('access-view');
			} else if ($user->gid >= 25) {
				$canviewitem = true;
			} else {
				//$has_item_access = FLEXI_ACCESS ? FAccess::checkAllItemReadAccess('com_content', 'read', 'users', $user->gmid, 'item', $this->_item->id) : $this->_item->access <= $aid;
				//$has_mcat_access = FLEXI_ACCESS ? FAccess::checkAllItemReadAccess('com_content', 'read', 'users', $user->gmid, 'category', $this->_item->catid) : $this->_item->category_access <= $aid;
				//$has_type_access = ... must do SQL query, because No FLEXIaccess support via checkAllItemReadAccess() function
				//$canviewitem = $has_item_access && $has_type_access && $has_mcat_access;
				$canviewitem = $item->has_item_access && (!$item->catid || $item->has_mcat_access) && (!$item->type_id || $item->has_type_access);
			}
			
			
			// *********************************************************************************
			// STEP B: Calculate SOME ITEM PUBLICATION STATE FLAGS, used to decide if current item is active
			// FLAGS: item_is_published, item_is_scheduled, item_is_expired, cats_are_published
			// *********************************************************************************
			$item_is_published = $this->_item->state == 1 || $this->_item->state == -5 || $this->_item->state == -1;
			$item_is_scheduled = $this->_item->publication_scheduled;
			$item_is_expired   = $this->_item->publication_expired;
			if ( $cid )
			{
				// cid is set, check state of current item category only
				// NOTE:  J1.6+ all ancestor categories from current one to the root, for J1.5 only the current one ($cid)
				$cats_are_published = FLEXI_J16GE ? $this->_item->ancestor_cats_published : $this->_item->catpublished;
				$cats_np_err_mssg = JText::sprintf('FLEXI_CONTENT_UNAVAILABLE_ITEM_CURRCAT_UNPUBLISHED', $cid);
			}
			else
			{
				// cid is not set, we have no current category, the item is visible if it belongs to at one published category
				$itemcats = $this->_item->categories;
				$cats_are_published = true;
				foreach ($itemcats as $catid) {
					$cats_are_published |= $globalcats[$catid]->published;
					if (FLEXI_J16GE) {  // For J1.6+ check all ancestor categories from current one to the root
						foreach($globalcats[$catid]->ancestorsarray as $pcid)    $cats_are_published |= $globalcats[$pcid]->published;
					}
				}
				$cats_np_err_mssg = JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_ALLCATS_UNPUBLISHED');
			}
			
			// Calculate if item is active ... and viewable is also it's (current or All) categories are published
			$item_active          = $item_is_published && !$item_is_scheduled && !$item_is_expired;
			$item_n_cat_active    = $item_active && $cats_are_published;
			$ignore_publication   = $canedititem || $caneditstate|| $isOwner;
			$inactive_notice_set = false;
			$item_state_pending   = $this->_item->state == -3;
			$item_state_draft			= $this->_item->state == -4;
			
			
			//*************************************************************************************************************************
			// STEP C: CHECK item state and ( UNLESS user is owner or cane dit the item ), do RAISE 404 (not found) HTTP Server Errors,
			// NOTE: Asking all users to login when item is not active maybe wrong approach, so instead we raise 404 error
			// (a) Check that item is PUBLISHED (1,-5) or ARCHIVED (-1)
			// (b) Check that item has expired publication date
			// (c) Check that item has scheduled publication date
			// (d) Check that current item category or all items categories are published
			//*************************************************************************************************************************
			
			// (a) Check that item is PUBLISHED (1,-5) or ARCHIVED (-1)
			if ( !$caneditstate && ($item_state_pending || $item_state_draft) && $isOwner ) {
				// SPECIAL workflow case, regardless of (view/edit privilege), allow users to view unpublished owned content, (a) if waiting for approval, or (b) if can request approval
				$inactive_notice_set = true;
			} else if ( !$item_is_published && !$ignore_publication ) {
				// Raise error that the item is unpublished
				$msg = JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_UNPUBLISHED') . $title_str;
				if (FLEXI_J16GE) throw new Exception($msg, 404); else JError::raiseError(404, $msg);
			} else if ( !$item_is_published && !$inactive_notice_set ) {
				// Item edittable, set warning that ...
				JError::raiseNotice( 404, JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_UNPUBLISHED') );
				$inactive_notice_set = true;
			}
			
			// NOTE: First, we check for expired publication, since if item expired, scheduled publication is meaningless
			
			// (b) Check that item has expired publication date
			if ( $item_is_expired && !$ignore_publication ) {
				// Raise error that the item is scheduled for publication
				$msg = JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_EXPIRED') . $title_str;
				if (FLEXI_J16GE) throw new Exception($msg, 404); else JError::raiseError(404, $msg);
			} else if ( $item_is_expired && !$inactive_notice_set ) {
				// Item edittable, set warning that ...
				JError::raiseNotice( 404, JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_EXPIRED') );
				$inactive_notice_set = true;
			}
			
			// (c) Check that item has scheduled publication date
			if ( $item_is_scheduled && !$ignore_publication ) {
				// Raise error that the item is scheduled for publication
				$msg = JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_SCHEDULED') . $title_str;
				if (FLEXI_J16GE) throw new Exception($msg, 404); else JError::raiseError(404, $msg);
			} else if ( $item_is_scheduled && !$inactive_notice_set ) {
				// Item edittable, set warning that ...
				JError::raiseNotice( 404, JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_SCHEDULED') );
				$inactive_notice_set = true;
			}
			
			// (d) Check that current item category or all items categories are published
			if ( !$cats_are_published && !$ignore_publication ) {
				// Terminate execution with a HTTP not-found Server Error
				$msg = $cats_np_err_mssg . $title_str;
				if (FLEXI_J16GE) throw new Exception($msg, 404); else JError::raiseError(404, $msg);
			} else if( !$cats_are_published && !$inactive_notice_set ) {
				// Item edittable, set warning that item's (ancestor) category is unpublished
				JError::raiseNotice( 404, $cats_np_err_mssg );
				$inactive_notice_set = true;
			}
			
			
			//*******************************************************************************************
			// STEP D: CHECK viewing access in relation to if user being logged and being owner / editor
			// (a) redirect user previewing a non-current item version, to either current item version or to refer if has no edit permission
			// (b) redirect item owner to previous page if user has no access (read/edit) to the item
			// (c) redirect unlogged user to login, so that user can possible login to privileged account
			// (d) redirect unauthorized logged user to the unauthorized page (if this is set)
			// (e) finally raise a 403 forbidden Server Error if user is unauthorized to access item
			//*******************************************************************************************
			
			// SPECIAL case when previewing an non-current version of an item
			$version = JRequest::getVar('version', 0, 'request', 'int' );            // Get item version to load
			$current_version = FLEXIUtilities::getCurrentVersions($this->_id, true); // Get current item version
			if ( $version && $version!=$current_version && !$canedititem )
			{
				// (a) redirect user previewing a non-current item version, to either current item version or to refer if has no edit permission
				JError::raiseNotice(403, JText::_('FLEXI_ALERTNOTAUTH_PREVIEW_UNEDITABLE')."<br />". JText::_('FLEXI_ALERTNOTAUTH_TASK') );
				if ( $item_n_cat_active && $canviewitem ) {
					$app->redirect(JRoute::_(FlexicontentHelperRoute::getItemRoute($this->_item->slug, $this->_item->categoryslug)));
				} else {
					$app->redirect($referer);  // Item not viewable OR no view access, redirect to refer page
				}
			}
			
			// SPECIAL cases for inactive item
			if ( !$item_n_cat_active ) {
				if ( !$caneditstate && ($item_state_pending || item_state_draft) && $isOwner )
				{
					// no redirect, SET message to owners, to wait for approval or to request approval of their content
					$app->enqueueMessage(JText::_( $item_state_pending ? 'FLEXI_ALERT_VIEW_OWN_PENDING_STATE' : 'FLEXI_ALERT_VIEW_OWN_DRAFT_STATE' ), 'notice');
				}
				else if ( !$canedititem && !$caneditstate && $isOwner )
				{
					// (b) redirect item owner to previous page if user cannot access (read/edit) the item
					JError::raiseNotice(403, JText::_( $item_state_pending ? 'FLEXI_ALERTNOTAUTH_VIEW_OWN_PENDING' : 'FLEXI_ALERTNOTAUTH_VIEW_OWN_UNPUBLISHED' ) );
					$app->redirect($referer);
				}
				else if ( $canedititem || $caneditstate )
				{
					// no redirect, SET notice to the editors, that they are viewing unreadable content because they can edit the item
					$app->enqueueMessage(JText::_('FLEXI_CONTENT_ACCESS_ALLOWED_BECAUSE_EDITABLE_PUBLISHABLE'), 'notice');
				} else {
					$app->enqueueMessage( 'INTERNAL ERROR: item inactive but checks were ignored despite current user not begin item owner or item assigned editor', 'notice');
					$app->redirect($referer);
				}
			}
			else if ( !$canviewitem && !$canedititem )
			{
				if($user->guest) {
					// (c) redirect unlogged user to login, so that user can possible login to privileged account
					$uri		= JFactory::getURI();
					$return		= $uri->toString();
					$com_users = FLEXI_J16GE ? 'com_users' : 'com_user';
					$url  = $cparams->get('login_page', 'index.php?option='.$com_users.'&view=login');
					$url .= '&return='.base64_encode($return);
					$url .= '&fcreturn='.base64_encode($fcreturn);
			
					JError::raiseWarning( 403, JText::sprintf("FLEXI_LOGIN_TO_ACCESS", $url));
					$app->redirect( $url );
				} else {
					$msg  = JText::_( 'FLEXI_ALERTNOTAUTH_VIEW');
					$msg .= $item->type_id && !$item->has_type_access ? "<br/>".JText::_("FLEXI_ALERTNOTAUTH_VIEW_TYPE") : '';
					$msg .= $item->catid   && !$item->has_mcat_access ? "<br/>".JText::_("FLEXI_ALERTNOTAUTH_VIEW_MCAT") : '';
					if ($cparams->get('unauthorized_page', '')) {
						// (d) redirect unauthorized logged user to the unauthorized page (if this is set)
						JError::raiseNotice( 403, $msg);
						$app->redirect($cparams->get('unauthorized_page'));				
					} else {
						// (e) finally raise a 403 forbidden Server Error if user is unauthorized to access item
						if (FLEXI_J16GE) throw new Exception($msg, 403); else JError::raiseError(403, $msg);
					}
				}
			} else {
				// User can read item and item is active, no further actions
			}
			
		} // End of Existing item (not new)
		
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
		$user		= JFactory::getUser();
		$aid		= !FLEXI_J16GE ? (int) $user->get('aid', 0) : max ($user->getAuthorisedViewLevels()) ;

		$jnow		= JFactory::getDate();
		$now		= FLEXI_J16GE ? $jnow->toSql() : $jnow->toMySQL();
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
		
		$app = JFactory::getApplication();
		$menu = JSite::getMenu()->getActive();  // Retrieve currently active menu item (NOTE: this applies when Itemid variable or menu item alias exists in the URL)
		jimport('joomla.html.parameter');
		
		
		// **********************************************************************
		// Retrieve RELATED parameters that will be merged into item's parameters
		// **********************************************************************
		
		// Retrieve parameters of current category (NOTE: this applies when cid variable exists in the URL)
		$catParams = "";
		if ( $this->_cid ) {
			$query = 'SELECT c.params'
					. ' FROM #__categories AS c'
					. ' WHERE c.id = ' . (int) $this->_cid
					;
			$this->_db->setQuery($query);
			$catParams = $this->_db->loadResult();
		}
		
		// Retrieve item's Content Type parameters
		$query = 'SELECT t.attribs'
				. ' FROM #__flexicontent_types AS t'
				. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.type_id = t.id'
				. ' WHERE ie.item_id = ' . (int)$this->_id
				;
		$this->_db->setQuery($query);
		$typeParams = $this->_db->loadResult();
		
		
		// ***************************************************************************************************
		// Merge parameters in order: component, menu, (item 's) current category, (item's) content type, item
		// ***************************************************************************************************
		
		// a. Get the COMPONENT only parameters and merge current menu item parameters
		$params = clone( JComponentHelper::getParams('com_flexicontent') );
		if ($menu) {
			$menu_params = FLEXI_J16GE ? $menu->params : new JParameter($menu->params);
			$params->merge($menu_params);
		}
		
		// b. Merge parameters from current category
		$catParams = FLEXI_J16GE ? new JRegistry($catParams) : new JParameter($catParams);
		$catParams->set('show_title', '');       // Prevent show_title from propagating ... to the item, it is meant for category view only
		$catParams->set('title_linkable', '');   // Prevent title_linkable from propagating ... to the item, it is meant for category view only
		$params->merge($catParams);
		
		// c. Merge TYPE parameters into the page configuration
		$typeParams = FLEXI_J16GE ? new JRegistry($typeParams) : new JParameter($typeParams);
		$params->merge($typeParams);

		// d. Merge ITEM parameters into the page configuration
		if ( is_string($this->_item->attribs) ) {
			$itemparams = FLEXI_J16GE ? new JRegistry($this->_item->attribs) : new JParameter($this->_item->attribs);
		} else {
			$itemparams = $this->_item->attribs;
		}
		$params->merge($itemparams);

		// e. Merge ACCESS permissions into the page configuration
		if (FLEXI_J16GE) {
			$accessperms = $this->getItemAccess();
			$params->merge($accessperms);
		}
		
		// Covert metadata property string to parameters object
		if ( !empty($this->_item->metadata) ) {
			$this->_item->metadata = FLEXI_J16GE ? new JRegistry($this->_item->metadata) : new JParameter($this->_item->metadata);
		} else {
			$this->_item->metadata = FLEXI_J16GE ? new JRegistry() : new JParameter("");
		}
		
		// *********************************************
		// Finally set 'parameters' property of the item
		// *********************************************
		
		$this->_item->parameters = $params;
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
			$item = JTable::getInstance('flexicontent_items', '');
			$item->hit($this->_id);
			return true;
		}
		return false;
	}
	
	
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
	
}
?>
