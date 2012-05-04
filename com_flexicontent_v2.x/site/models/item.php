<?php
/**
 * @version 1.5 stable $Id: item.php 1251 2012-04-16 02:36:00Z ggppdk $
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
class FlexicontentModelItem extends ParentClassItem
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
		$app =& JFactory::getApplication();
		$user	= & JFactory::getUser();
		$aid	= (int) $user->get('aid');
		$gid	= (int) $user->get('gid');
		$cid	= $this->_cid;
		$params = & $this->_item->parameters;
		$cparams = & $this->_cparams;
		
		// Create the return parameter
		$fcreturn = array (
			'id' 	=> @$this->_item->id,
			'cid'	=> $cid
		);
		$fcreturn = serialize($fcreturn);
		
		// Since we will check access for VIEW (=read) only, we skip checks if TASK Variable is set,
		// the edit() or add() or other controller task, will be responsible for checking permissions.
		if	(	@$this->_item->id  // not new item
				&& !JRequest::getVar('task', false) // skip various task checked at the controller
				&& JRequest::getVar('view')==FLEXI_ITEMVIEW		// must be in item(s) view
				)
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
			if ( $version && $version!=$current_version && !$canedititem)
			{
				// Terminate execution with a HTTP access denied / forbidden Server Error
				JError::raiseNotice(403,
					JText::_('FLEXI_ALERTNOTAUTH_PREVIEW_UNEDITABLE')."<br />".
					JText::_('FLEXI_ALERTNOTAUTH_TASK')."<br />".
					"Item id: ".$this->_item->id
				);
				$app->redirect(JRoute::_(FlexicontentHelperRoute::getItemRoute($this->_item->slug, $this->_item->categoryslug)));
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
				$itemcats = $this->getCatsselected();
				$cats_are_published = true;
				foreach ($itemcats as $catid) {
					$cats_are_published |= $globalcats[$catid]->published;
					if (FLEXI_J16GE) {  // For J1.6+ check all ancestor categories from current one to the root
						foreach($globalcats[$catid]->ancestorsarray as $pcid)    $cats_are_published |= $globalcats[$pcid]->published;
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
				$app->enqueueMessage(JText::_('FLEXI_CONTENT_ACCESS_ALLOWED_BECAUSE_EDITABLE'), 'notice');
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
					$app->redirect( $url );
				} else {
					if ($cparams->get('unauthorized_page', '')) {
						// (b) Redirect to unauthorized_page
						JError::raiseNotice( 403, JText::_("FLEXI_ALERTNOTAUTH_VIEW"));
						$app->redirect($cparams->get('unauthorized_page'));				
					} else {
						// (c) Raise 403 forbidden error
						JError::raiseError( 403, JText::_("FLEXI_ALERTNOTAUTH_VIEW"));
					}
				}
			} else {
				// User can read item and item is active, take no further action
			}
		}
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
