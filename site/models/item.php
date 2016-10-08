<?php
/**
 * @version 1.5 stable $Id: items.php 1904 2014-05-20 12:21:09Z ggppdk $
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

jimport('legacy.model.legacy');
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
	function _check_viewing_access($version=false)
	{
		global $globalcats;
		$app  = JFactory::getApplication();
		$user	= JFactory::getUser();
		$session = JFactory::getSession();
		$aid	= (int) $user->get('aid');
		$gid	= (int) $user->get('gid');
		$cid	= $this->_cid;
		$params = $this->_item->parameters;
		$cparams = $this->_cparams;
		
		$fcreturn = serialize( array('id'=>@$this->_item->id, 'cid'=>$cid) );      // a special url parameter, used by some SEF code
		$referer = @$_SERVER['HTTP_REFERER'];                                      // the previously viewed page (refer)
		if ( ! flexicontent_html::is_safe_url($referer) ) $referer = JURI::base(); // Ignore it if potentially non safe URL, e.g. non-internal
		
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
			$canedititem = $params->get('access-edit');
			$caneditstate = $params->get('access-edit-state');
			
			if (!$caneditstate) {
				// Item not editable, check if item is editable till logoff
				if ( $session->has('rendered_uneditable', 'flexicontent') ) {
					$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
					$canedititem = isset($rendered_uneditable[$this->_item->id]);
				}
			}
			
			// (c) Calculate read access ... also considering the access level of parent categories
			$_cid_ = $cid ? $cid : $this->_item->catid;
			if ( !isset($this->_item->ancestor_cats_accessible) )
			{
				$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
				$allowed_levels = array_flip($aid_arr);
				
				$catshelper = new flexicontent_cats($_cid_);
				$parents    = $catshelper->getParentlist($all_cols=false);
				
				$ancestor_cats_accessible = true;
				foreach($parents as $parent) if ( !isset($allowed_levels[$parent->access]) )
					{ $ancestor_cats_accessible = false; break; }
				$this->_item->ancestor_cats_accessible = $ancestor_cats_accessible;
			}
			$canviewitem = $params->get('access-view') && $this->_item->ancestor_cats_accessible;
			
			
			// *********************************************************************************************
			// STEP B: Calculate SOME ITEM PUBLICATION STATE FLAGS, used to decide if current item is active
			// FLAGS: item_is_published, item_is_scheduled, item_is_expired, ancestor_cats_published
			// *********************************************************************************************

			$item_is_published = $this->_item->state == 1 || $this->_item->state == -5 || $this->_item->state == (FLEXI_J16GE ? 2:-1);
			$item_is_scheduled = $this->_item->publication_scheduled;
			$item_is_expired   = $this->_item->publication_expired;
			if ( $cid )
			{
				// cid is set, check state of current item category only
				// NOTE:  J1.6+ all ancestor categories from current one to the root, for J1.5 only the current one ($cid)
				if ( !isset($this->_item->ancestor_cats_published) ) {
					$ancestor_cats_published = true;
					foreach($globalcats[$cid]->ancestorsarray as $pcid)    $ancestor_cats_published = $ancestor_cats_published && ($globalcats[$pcid]->published==1);
					$this->_item->ancestor_cats_published = $ancestor_cats_published;
				}
				$ancestor_cats_published = $this->_item->ancestor_cats_published;  //$this->_item->catpublished;
				$cats_np_err_mssg = JText::sprintf('FLEXI_CONTENT_UNAVAILABLE_ITEM_CURRCAT_UNPUBLISHED', $cid);
			}
			else
			{
				// cid is not set, we have no current category, the item is visible if it belongs to at one published category
				$itemcats = $this->_item->categories;
				$ancestor_cats_published = true;
				foreach ($itemcats as $catid)
				{
					if (!isset($globalcats[$catid])) continue;
					$ancestor_cats_published |= $globalcats[$catid]->published;
					
					// For J1.6+ check all ancestor categories from current one to the root
					foreach($globalcats[$catid]->ancestorsarray as $pcid)    $ancestor_cats_published = $ancestor_cats_published && ($globalcats[$pcid]->published==1);
				}
				$cats_np_err_mssg = JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_ALLCATS_UNPUBLISHED');
			}
			
			// Calculate if item is active ... and viewable is also it's (current or All) categories are published
			$item_active          = $item_is_published && !$item_is_scheduled && !$item_is_expired;
			$item_n_cat_active    = $item_active && $ancestor_cats_published;
			$previewing_and_unlogged = ($version && $user->guest); // this is a flag indicates to redirect to login instead of 404 error
			$ignore_publication   = $canedititem || $caneditstate || $isOwner || $previewing_and_unlogged;
			$inactive_notice_set = false;
			$item_state_pending   = $this->_item->state == -3;
			$item_state_draft			= $this->_item->state == -4;
			
			
			//***********************************************************************************************************************
			// STEP C: CHECK item state, if publication state is not ignored terminate with 404 NOT found, otherwise add a notice
			// NOTE: Asking all users to login when item is not active maybe wrong approach, so instead we raise 404 error, but we
			// will ask them to login only if previewing a latest or specific version (so ignore publication FLAG includes this case)
			// (a) Check that item is PUBLISHED (1,-5) or ARCHIVED (-1)
			// (b) Check that item has expired publication date
			// (c) Check that item has scheduled publication date
			// (d) Check that current item category or all items categories are published
			//***********************************************************************************************************************
			
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
			if ( !$ancestor_cats_published && !$ignore_publication ) {
				// Terminate execution with a HTTP not-found Server Error
				$msg = $cats_np_err_mssg . $title_str;
				if (FLEXI_J16GE) throw new Exception($msg, 404); else JError::raiseError(404, $msg);
			} else if( !$ancestor_cats_published && !$inactive_notice_set ) {
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
			
			// SPECIAL case when previewing an non-current version of an item, this is allowed only if user can edit the item
			$current_version = FLEXIUtilities::getCurrentVersions($this->_id, true); // Get current item version
			if ( $version && $version!=$current_version && !$canedititem && !$previewing_and_unlogged )
			{
				// (a) redirect user previewing a non-current item version, to either current item version or to refer if has no edit permission
				JError::raiseNotice(403, JText::_('FLEXI_ALERTNOTAUTH_PREVIEW_UNEDITABLE')."<br />". JText::_('FLEXI_ALERTNOTAUTH_TASK') );
				if ( $item_n_cat_active && $canviewitem ) {
					$app->redirect(JRoute::_(FlexicontentHelperRoute::getItemRoute($this->_item->slug, $this->_item->categoryslug, 0, $this->_item)));
				} else {
					$app->redirect($referer);  // Item not viewable OR no view access, redirect to refer page
				}
			}
			
			// SPECIAL cases for inactive item, but exclude preview+unlogged case (we will catch this below)
			else if ( !$item_n_cat_active && !$previewing_and_unlogged )
			{
				if ( !$caneditstate && ($item_state_pending || $item_state_draft) && $isOwner )
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
			
			// Cases for non-viewable and non-editable item
			else if ( ( !$canviewitem && !$canedititem ) || !$item_n_cat_active )
			{
				if($user->guest) {
					// (c) redirect unlogged user to login, so that user can possible login to privileged account
					$uri		= JFactory::getURI();
					$return		= $uri->toString();
					$com_users = FLEXI_J16GE ? 'com_users' : 'com_user';
					$url  = $cparams->get('login_page', 'index.php?option='.$com_users.'&view=login');
					$return = strtr(base64_encode($return), '+/=', '-_,');
					$url .= '&return='.$return;
					//$url .= '&return='.base64_encode($return);
					$url .= '&fcreturn='.base64_encode($fcreturn);
			
					JError::raiseWarning( 403, JText::sprintf("FLEXI_LOGIN_TO_ACCESS", $url));
					$app->redirect( $url );
				} else {
					$msg  = JText::_( 'FLEXI_ALERTNOTAUTH_VIEW');
					$msg .= $item->type_id && !$this->_item->has_type_access ? "<br/>".JText::_("FLEXI_ALERTNOTAUTH_VIEW_TYPE") : '';
					$msg .= $item->catid   && !$this->_item->has_mcat_access ? "<br/>".JText::_("FLEXI_ALERTNOTAUTH_VIEW_MCAT") : '';
					$msg .= $cid  && !$this->_item->ancestor_cats_accessible ? "<br/>".JText::_("FLEXI_ALERTNOTAUTH_VIEW_MCAT") : '';
					if ($cparams->get('unauthorized_page', '')) {
						// (d) redirect unauthorized logged user to the unauthorized page (if this is set)
						JError::raiseNotice( 403, $msg);
						$app->redirect($cparams->get('unauthorized_page'));				
					} else {
						// (e) finally raise a 403 forbidden Server Error if user is unauthorized to access item
						if (FLEXI_J16GE) throw new Exception($msg, 403); else JError::raiseError(403, $msg);
					}
				}
			}
			
			// User can view (or edit) the item and item is active, no further actions
			else {
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
		//
		// First thing we need to do is assert that the content article is the one
		// we are looking for and we have access to it.
		//
		$where = ' WHERE i.id = '. (int) $this->_id;
		
		return $where;
	}
	
	
	/**
	 * Method to decide which item layout to use
	 *
	 * @access	public
	 * @param	int item identifier
	 */
	function decideLayout(&$compParams, &$typeParams, &$itemParams)
	{
		$app = JFactory::getApplication();
		
		// Decide to use mobile or normal item template layout
		$useMobile = $compParams->get('use_mobile_layouts', 0 );
		if ($useMobile) {
			$force_desktop_layout = $compParams->get('force_desktop_layout', 0 );
			$mobileDetector = flexicontent_html::getMobileDetector();
			$isMobile = $mobileDetector->isMobile();
			$isTablet = $mobileDetector->isTablet();
			$useMobile = $force_desktop_layout  ?  $isMobile && !$isTablet  :  $isMobile;
		}
		$_ilayout = $useMobile ? 'ilayout_mobile' : 'ilayout';
		
		// Get item layout (... if not already set), from the configuration parameter (that was decided above)
		$ilayout = $this->_ilayout=='__request__' ? JRequest::getVar($_ilayout, false) : false;
		if (!$ilayout) {
			$desktop_ilayout = $itemParams->get('ilayout', $typeParams->get('ilayout', 'default'));
			$ilayout = !$useMobile ? $desktop_ilayout : $itemParams->get('ilayout_mobile', $typeParams->get('ilayout_mobile', $desktop_ilayout));
		}
		
		// Verify the layout is within allowed templates, that is Content Type 's default template OR Content Type allowed templates
		$allowed_tmpls = $typeParams->get('allowed_ilayouts');
		$type_default_layout = $typeParams->get('ilayout', 'default');
		if ( empty($allowed_tmpls) )							$allowed_tmpls = array();
		else if ( ! is_array($allowed_tmpls) )		$allowed_tmpls = explode("|", $allowed_tmpls);
		
		// Verify the item layout is within templates: Content Type default template OR Content Type allowed templates
		if ( $ilayout!=$type_default_layout && count($allowed_tmpls) && !in_array($ilayout,$allowed_tmpls) ) {
			$app->enqueueMessage("<small>Current item Layout (template) is '$ilayout':<br/>- This is neither the Content Type Default Template, nor does it belong to the Content Type allowed templates.<br/>- Please correct this in the URL or in Content Type configuration.<br/>- Using Content Type Default Template Layout: '$type_default_layout'</small>", 'notice');
			$ilayout = $type_default_layout;
		}
		
		// Get all templates from cache, (without loading any language file this will be done at the view)
		$themes = flexicontent_tmpl::getTemplates();
		
		// Verify the item layout exists
		if ( !isset($themes->items->{$ilayout}) ) {
			$fixed_ilayout = isset($themes->items->{$type_default_layout}) ? $type_default_layout : 'default';
			$app->enqueueMessage("<small>Current Item Layout Template is '$ilayout' does not exist<br/>- Please correct this in the URL or in Content Type configuration.<br/>- Using Template Layout: '$fixed_ilayout'</small>", 'notice');
			$ilayout = $fixed_ilayout;
			FLEXIUtilities::loadTemplateLanguageFile( $ilayout ); // Manually load Template-Specific language file of back fall ilayout
		}
		
		// Finally set the ilayout (template name) into model / item's parameters / HTTP Request
		$this->setItemLayout($ilayout);
		$itemParams->set('ilayout', $ilayout);
		JRequest::setVar('ilayout', $ilayout);
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
		if (!$force && !empty($this->_item->parameters)) return;
		
		$app = JFactory::getApplication();
		$menu = $app->getMenu()->getActive();  // Retrieve currently active menu item (NOTE: this applies when Itemid variable or menu item alias exists in the URL)
		$isnew = !$this->_id;
		
		
		// **********************************************************************
		// Retrieve RELATED parameters that will be merged into item's parameters
		// **********************************************************************
		
		// Retrieve COMPONENT parameters
		$compParams = JComponentHelper::getComponent('com_flexicontent')->params;
		
		// Retrieve parameters of current category (NOTE: this applies when cid variable exists in the URL)
		$catParams = "";
		if ( $this->_cid ) {
			$query = 'SELECT c.title, c.params FROM #__categories AS c WHERE c.id = ' . (int) $this->_cid;
			$this->_db->setQuery($query);
			$catData = $this->_db->loadObject();
			$catParams = $catData->params;
			$this->_item->category_title = $catData->title;
		}
		$catParams = new JRegistry($catParams);
		
		// Retrieve/Create item's Content Type parameters
		$typeParams = $this->getTypeparams();
		$typeParams = new JRegistry($typeParams);
		
		// Create item parameters
		if ( !is_object($this->_item->attribs) )
			$itemParams = new JRegistry($this->_item->attribs);
		else
			$itemParams = $this->_item->attribs;
		
		// Retrieve Layout's parameters, also deciding the layout
		$this->decideLayout($compParams, $typeParams, $itemParams);
		$layoutParams = $this->getLayoutparams();
		$layoutParams = new JRegistry($layoutParams);  //print_r($layoutParams);
		
		
		// **************************************************************************************************************
		// Start merging of parameters, OVERRIDE ORDER: layout(template-manager)/component/category/type/item/menu/access
		// **************************************************************************************************************
		
		// a0. Merge Layout parameters into the page configuration
		$params = new JRegistry();
		$params->merge($layoutParams);
		
		// a1. Start with empty registry, then merge COMPONENT parameters
		$params->merge($compParams);
		
		// b. Merge parameters from current category, but prevent some settings from propagating ... to the item, that are meant for
		//    category view only, these are legacy settings that were removed from category.xml, but may exist in saved configurations
		$catParams->set('show_title', '');
		$catParams->set('show_editbutton', '');
		$params->merge($catParams);
		
		// c. Merge TYPE parameters into the page configuration
		$params->merge($typeParams);

		// d. Merge ITEM parameters into the page configuration
		$params->merge($itemParams);
		
		// e. Merge ACCESS permissions into the page configuration
		$accessperms = $this->getItemAccess();
		$params->merge($accessperms);
		
		// d. Merge the active menu parameters, verify menu item points to current FLEXIcontent object
		if ( $menu && !empty($this->mergeMenuParams) ) {
			if (!empty($this->isForm)) {
				$this->menu_matches = false;
				$view_ok = FLEXI_ITEMVIEW          == @$menu->query['view'] || 'article' == @$menu->query['view'];
				$this->menu_matches = $view_ok;
			} else {
				$view_ok = FLEXI_ITEMVIEW          == @$menu->query['view'] || 'article' == @$menu->query['view'];
				$cid_ok  = JRequest::getInt('cid') == (int) @$menu->query['cid'];
				$id_ok   = JRequest::getInt('id')  == (int) @$menu->query['id'];
				$this->menu_matches = $view_ok /*&& $cid_ok*/ && $id_ok;
			}
		} else {
			$this->menu_matches = false;
		}
		
		// MENU ITEM matched, merge parameters and use its page heading (but use menu title if the former is not set)
		if ( $this->menu_matches ) {
			$params->merge($menu->params);
			$default_heading = $menu->title;
			
			// Cross set (show_) page_heading / page_title for compatibility of J2.5+ with J1.5 template (and for J1.5 with J2.5 template)
			$params->def('page_heading', $params->get('page_title',   $default_heading));
			$params->def('page_title',   $params->get('page_heading', $default_heading));
		  $params->def('show_page_heading', $params->get('show_page_title',   0));
		  $params->def('show_page_title',   $params->get('show_page_heading', 0));
		}
		
		// MENU ITEM did not match, clear page title (=browser window title) and page heading so that they are calculated below
		else {
			// Clear some menu parameters
			//$params->set('pageclass_sfx',	'');  // CSS class SUFFIX is behavior, so do not clear it ?
			
			// Calculate default page heading (=called page title in J1.5), which in turn will be document title below !! ...
			$default_heading = empty($this->isForm) ? $this->_item->title :
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
			if ($params->get('page_heading') == $this->_item->title) $params->set('show_page_heading', 0);
			if ($params->get('page_title')   == $this->_item->title) $params->set('show_page_title',   0);
		}
		
		// Also convert metadata property string to parameters object
		if ( !empty($this->_item->metadata) ) {
			$this->_item->metadata = new JRegistry($this->_item->metadata);
		} else {
			$this->_item->metadata = new JRegistry();
		}
		
		// Manually apply metadata from type parameters ... currently only 'robots' makes sense to exist per type
		if ( !$this->_item->metadata->get('robots') )   !$this->_item->metadata->set('robots', $typeParams->get('robots'));
		
		
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
	 * @return	integer on success
	 * @since	1.0
	 */
	function getFavourites()
	{
		return flexicontent_db::getFavourites($type=0, $this->_id);
	}
	
	
	/**
	 * Method to get the nr of favourites of an user
	 *
	 * @access	public
	 * @return	integer on success
	 * @since	1.0
	 */
	function getFavoured()
	{
		return flexicontent_db::getFavoured($type=0, $this->_id, JFactory::getUser()->id);
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
		return flexicontent_db::removefav($type=0, $this->_id, JFactory::getUser()->id);
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
		return flexicontent_db::addfav($type=0, $this->_id, JFactory::getUser()->id);
	}
	
}
?>
