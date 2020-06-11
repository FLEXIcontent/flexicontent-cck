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
JLoader::register('ParentClassItem', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'parentclassitem.php');

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
		// Sanity check
		if	(	!$this->_record->id )  die('_check_viewing_access() should be called only on EXISTING items, item id is empty');
		//echo "<pre>"; debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS); echo "</pre>";

		global $globalcats;
		$app  = JFactory::getApplication();
		$user	= JFactory::getUser();
		$session = JFactory::getSession();
		$uri  = JUri::getInstance();
		$aid	= (int) $user->get('aid');
		$gid	= (int) $user->get('gid');
		$cid	= $this->_cid;
		$params = $this->_record->parameters;
		$cparams = $this->_cparams;

		$referer  = @ $_SERVER['HTTP_REFERER'];                                    // The previously viewed page (refer)
		if ( ! flexicontent_html::is_safe_url($referer) ) $referer = JUri::base(); // Ignore it if potentially non safe URL, e.g. non-internal

		// a basic item title string
		$title_str = ' ' . JText::_('FLEXI_ID') .' : '.$this->_record->id;



		//*************************************************************
		// STEP A: Calculate ownership, edit permission and read access
		// (a) isOwner, (b) canedititem, (c) canviewitem
		//*************************************************************

		// (a) Calculate if owned by current user
		$isOwner = $this->_record->created_by== $user->get('id');

		// (b) Calculate edit access ...
		// NOTE: we will allow view access if current user can edit the item (but set a warning message about it, see bellow)
		$canedititem = $params->get('access-edit');
		$caneditstate = $params->get('access-edit-state');

		if (!$caneditstate) {
			// Item not editable, check if item is editable till logoff
			if ( $session->has('rendered_uneditable', 'flexicontent') ) {
				$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
				$canedititem = isset($rendered_uneditable[$this->_record->id]);
			}
		}

		// (c) Calculate read access ... also considering the access level of parent categories
		$_cid_ = $cid ? $cid : $this->_record->catid;
		if ( !isset($this->_record->ancestor_cats_accessible) )
		{
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$allowed_levels = array_flip($aid_arr);

			$catshelper = new flexicontent_cats($_cid_);
			$parents    = $catshelper->getParentlist($all_cols=false);

			$ancestor_cats_accessible = true;
			foreach($parents as $parent) if ( !isset($allowed_levels[$parent->access]) )
				{ $ancestor_cats_accessible = false; break; }
			$this->_record->ancestor_cats_accessible = $ancestor_cats_accessible;
		}
		$canviewitem = $params->get('access-view') && $this->_record->ancestor_cats_accessible;


		// *********************************************************************************************
		// STEP B: Calculate SOME ITEM PUBLICATION STATE FLAGS, used to decide if current item is active
		// FLAGS: item_is_published, item_is_scheduled, item_is_expired, ancestor_cats_published
		// *********************************************************************************************

		$item_is_published = $this->_record->state == 1 || $this->_record->state == -5 || $this->_record->state == 2;
		$item_is_scheduled = $this->_record->publication_scheduled;
		$item_is_expired   = $this->_record->publication_expired;
		if ( $cid )
		{
			// cid is set, check state of current item category only
			// NOTE:  J1.6+ all ancestor categories from current one to the root, for J1.5 only the current one ($cid)
			if ( !isset($this->_record->ancestor_cats_published) ) {
				$ancestor_cats_published = true;
				foreach($globalcats[$cid]->ancestorsarray as $pcid)    $ancestor_cats_published = $ancestor_cats_published && ($globalcats[$pcid]->published==1);
				$this->_record->ancestor_cats_published = $ancestor_cats_published;
			}
			$ancestor_cats_published = $this->_record->ancestor_cats_published;  //$this->_record->catpublished;
			$cats_np_err_mssg = JText::sprintf('FLEXI_CONTENT_UNAVAILABLE_ITEM_CURRCAT_UNPUBLISHED', $cid);
		}
		else
		{
			// cid is not set, we have no current category, the item is visible if it belongs to at one published category
			$itemcats = $this->_record->categories;
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
		$item_state_pending   = $this->_record->state == -3;
		$item_state_draft			= $this->_record->state == -4;


		//***********************************************************************************************************************
		// STEP C: CHECK item state, if publication state is not ignored terminate with 404 NOT found, otherwise add a notice
		// NOTE: Asking all users to login when item is not active maybe wrong approach, so instead we raise 404 error, but we
		// will ask them to login only if previewing a latest or specific version (so ignore publication FLAG includes this case)
		// (a) Check that item is PUBLISHED (1,-5) or ARCHIVED (2)
		// (b) Check that item has expired publication date
		// (c) Check that item has scheduled publication date
		// (d) Check that current item category or all items categories are published
		//***********************************************************************************************************************

		// (a) Check that item is PUBLISHED (1,-5) or ARCHIVED (2)

		// SPECIAL workflow case, regardless of (view/edit privilege), allow users to view unpublished owned content, (a) if waiting for approval, or (b) if can request approval
		if ( !$caneditstate && ($item_state_pending || $item_state_draft) && $isOwner )
		{
			$inactive_notice_set = true;
		}

		// Raise error that the item is unpublished
		else if ( !$item_is_published && !$ignore_publication )
		{
			$msg = JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_UNPUBLISHED') . $title_str;
			throw new Exception($msg, 404);
		}

		// Item edittable, set warning that ...
		else if ( !$item_is_published && !$inactive_notice_set )
		{
			$app->enqueueMessage(JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_UNPUBLISHED'), 'notice');  // 404
			$inactive_notice_set = true;
		}

		// NOTE: First, we check for expired publication, since if item expired, scheduled publication is meaningless

		// (b) Check that item has expired publication date

		// Raise error that the item is scheduled for publication
		if ( $item_is_expired && !$ignore_publication )
		{
			$msg = JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_EXPIRED') . $title_str;
			throw new Exception($msg, 404);
		}

		// Item edittable, set warning that ...
		else if ( $item_is_expired && !$inactive_notice_set )
		{
			$app->enqueueMessage(JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_EXPIRED'), 'notice');  // 404
			$inactive_notice_set = true;
		}

		// (c) Check that item has scheduled publication date

		// Raise error that the item is scheduled for publication
		if ( $item_is_scheduled && !$ignore_publication )
		{
			$msg = JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_SCHEDULED') . $title_str;
			throw new Exception($msg, 404);
		}

		// Item edittable, set warning that ...
		else if ( $item_is_scheduled && !$inactive_notice_set )
		{
			$app->enqueueMessage(JText::_('FLEXI_CONTENT_UNAVAILABLE_ITEM_SCHEDULED'), 'notice');  // 404
			$inactive_notice_set = true;
		}

		// (d) Check that current item category or all items categories are published

		// Terminate execution with a HTTP not-found Server Error
		if ( !$ancestor_cats_published && !$ignore_publication )
		{
			$msg = $cats_np_err_mssg . $title_str;
			throw new Exception($msg, 404);
		}

		// Item edittable, set warning that item's (ancestor) category is unpublished
		else if( !$ancestor_cats_published && !$inactive_notice_set )
		{
			$app->enqueueMessage($cats_np_err_mssg, 'notice');  //404
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
		$last_version    = FLEXIUtilities::getLastVersions($this->_id, true);    // Get last version (=latest one saved, highest version id),
		if ( !$canedititem
			&& (($version > 0 && $version != $current_version) || ($version < 0 && $last_version != $current_version))
		){
			$return   = strtr(base64_encode($uri->toString()), '+/=', '-_,');           // Current URL as return URL (but we will for id / cid)
			$fcreturn = serialize( array('id' => $this->_record->id, 'cid' => $cid) );  // A special url parameter, used by some SEF code
			$url = $cparams->get('login_page', 'index.php?option=com_users&view=login')
				. '&return='.$return
				. '&fcreturn='.base64_encode($fcreturn);

			// (a) redirect user previewing a non-current item version, to either current item version or to refer if has no edit or view access
			$app->enqueueMessage(
				JText::_('FLEXI_ALERTNOTAUTH_PREVIEW_UNEDITABLE') . '<br />' .
				($user->guest ? JText::sprintf('FLEXI_LOGIN_TO_ACCESS', $url) : JText::_('FLEXI_ALERTNOTAUTH_TASK')) , 'warning'
			);  // 403
			$item_n_cat_active && $canviewitem
				? $app->redirect(JRoute::_(FlexicontentHelperRoute::getItemRoute($this->_record->slug, $this->_record->categoryslug, 0, $this->_record)))
				: $app->redirect($referer);  // Item not viewable OR no view access, redirect to refer page
		}

		// SPECIAL cases for inactive item, but exclude preview+unlogged case (we will catch this below)
		else if ( !$item_n_cat_active && !$previewing_and_unlogged )
		{
			// no redirect, SET message to owners, to wait for approval or to request approval of their content
			if ( !$caneditstate && ($item_state_pending || $item_state_draft) && $isOwner )
			{
				$app->enqueueMessage(JText::_( $item_state_pending ? 'FLEXI_ALERT_VIEW_OWN_PENDING_STATE' : 'FLEXI_ALERT_VIEW_OWN_DRAFT_STATE' ), 'notice');
			}

			// (b) redirect item owner to previous page if user cannot access (read/edit) the item
			else if ( !$canedititem && !$caneditstate && $isOwner )
			{
				$app->enqueueMessage(JText::_($item_state_pending ? 'FLEXI_ALERTNOTAUTH_VIEW_OWN_PENDING' : 'FLEXI_ALERTNOTAUTH_VIEW_OWN_UNPUBLISHED'), 'notice');  // 403
				$app->redirect($referer);
			}

			// no redirect, SET notice to the editors, that they are viewing unreadable content because they can edit the item
			else if ( $canedititem || $caneditstate )
			{
				$app->enqueueMessage(JText::_('FLEXI_CONTENT_ACCESS_ALLOWED_BECAUSE_EDITABLE_PUBLISHABLE'), 'notice');
			}

			// Internal error in our code
			else
			{
				$app->enqueueMessage( 'INTERNAL ERROR: item inactive but checks were ignored despite current user not begin item owner or item assigned editor', 'notice');
				$app->redirect($referer);
			}
		}

		// Cases for non-viewable and non-editable item
		else if ( ( !$canviewitem && !$canedititem ) || !$item_n_cat_active )
		{
			// (c) redirect unlogged user to login, so that user can possible login to privileged account
			if ($user->guest)
			{
				$return   = strtr(base64_encode($uri->toString()), '+/=', '-_,');           // Current URL as return URL (but we will for id / cid)
				$fcreturn = serialize( array('id' => $this->_record->id, 'cid' => $cid) );  // A special url parameter, used by some SEF code
				$url = $cparams->get('login_page', 'index.php?option=com_users&view=login')
					. '&return='.$return
					. '&fcreturn='.base64_encode($fcreturn);

				$app->setHeader('status', 403);
				$app->enqueueMessage(JText::sprintf('FLEXI_LOGIN_TO_ACCESS', $url), 'warning');
				$app->redirect( $url );
			}

			else
			{
				$msg  = JText::_( 'FLEXI_ALERTNOTAUTH_VIEW');
				$msg .= $item->type_id && !$this->_record->has_type_access ? "<br/>".JText::_("FLEXI_ALERTNOTAUTH_VIEW_TYPE") : '';
				$msg .= $item->catid   && !$this->_record->has_mcat_access ? "<br/>".JText::_("FLEXI_ALERTNOTAUTH_VIEW_MCAT") : '';
				$msg .= $cid  && !$this->_record->ancestor_cats_accessible ? "<br/>".JText::_("FLEXI_ALERTNOTAUTH_VIEW_MCAT") : '';

				// (d) redirect unauthorized logged user to the unauthorized page (if this is set)
				if ($cparams->get('unauthorized_page', ''))
				{
					$app->enqueueMessage($msg, 'notice');  // 403
					$app->redirect($cparams->get('unauthorized_page'));
				}

				// (e) finally raise a 403 forbidden Server Error if user is unauthorized to access item
				else
				{
					throw new Exception($msg, 403);
				}
			}
		}

		// User can view (or edit) the item and item is active, no further actions
		else {
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
	function decideLayout($compParams, $typeParams, $itemParams, $catParams)
	{
		$fallback = 'grid';
		$app      = JFactory::getApplication();

		// Decide to use MOBILE or DESKTOP item template layout
		$useMobile = (int) $compParams->get('use_mobile_layouts', 0);

		if ($useMobile)
		{
			$force_desktop_layout = (int) $compParams->get('force_desktop_layout', 0);
			$mobileDetector       = flexicontent_html::getMobileDetector();

			$isMobile  = $mobileDetector->isMobile();
			$isTablet  = $mobileDetector->isTablet();
			$useMobile = $force_desktop_layout  ?  $isMobile && !$isTablet  :  $isMobile;
		}

		$_ilayout = $useMobile ? 'ilayout_mobile' : 'ilayout';

		// A. Get item layout from HTTP request
		$ilayout = $this->_ilayout === '__request__'
			? $app->input->getCmd($_ilayout, false)
			: false;

		// B. Get item layout from the -- configuration parameter name -- (that was decided above)
		if (!$ilayout)
		{
			$desktop_ilayout = $itemParams->get('ilayout') ?: ($catParams->get('ilayout') ?: ($typeParams->get('ilayout') ?: $fallback));
			$mobile_ilayout  = $itemParams->get('ilayout_mobile') ?:  ($catParams->get('ilayout_mobile') ?: ($typeParams->get('ilayout_mobile') ?: $desktop_ilayout));

			$ilayout = !$useMobile ? $desktop_ilayout : $mobile_ilayout;
		}

		// Verify the layout is within allowed templates, that is Content Type 's default template OR Content Type allowed templates
		$allowed_tmpls = $typeParams->get('allowed_ilayouts');
		$type_default_layout = $typeParams->get('ilayout') ?: $fallback;

		$allowed_tmpls = empty($allowed_tmpls)
			? array()
			: $allowed_tmpls;
		$allowed_tmpls = !is_array($allowed_tmpls)
			? explode('|', $allowed_tmpls)
			: $allowed_tmpls;

		// Verify the item layout is within templates: Content Type default template OR Content Type allowed templates
		if ($ilayout != $type_default_layout && count($allowed_tmpls) && !in_array($ilayout, $allowed_tmpls))
		{
			//echo "<pre>"; debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS); echo "</pre>";
			$app->enqueueMessage('
				Current item Layout (template) is \'' . $ilayout . '\'<br/>
				- This is neither the Content Type Default Template, nor does it belong to the Content Type allowed templates.<br/>
				- Please correct this in the URL or in Content Type configuration.<br/>
				- Using Content Type Default Template Layout: \'' . $type_default_layout . '\'
			', 'notice');
			$ilayout = $type_default_layout;
		}

		// Get all templates from cache, (without loading any language file this will be done at the view)
		$themes = flexicontent_tmpl::getTemplates();

		// Verify the item layout exists
		if (!isset($themes->items->{$ilayout}))
		{
			//echo "<pre>"; debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS); echo "</pre>";
			$fixed_ilayout = isset($themes->items->{$type_default_layout}) ? $type_default_layout : $fallback;
			$app->enqueueMessage('
				Current Item Layout Template is \'' . $ilayout . '\' does not exist<br/>
				- Please correct this in the URL or in Content Type configuration.<br/>
				- Using Template Layout: \'' . $fixed_ilayout . '\'
			', 'notice');
			$ilayout = $fixed_ilayout;

			// Manually load Template-Specific language file of back fall ilayout
			FLEXIUtilities::loadTemplateLanguageFile($ilayout);
		}

		// Finally set the ilayout (template name) into model / item's parameters / HTTP Request
		$this->setItemLayout($ilayout);
		$itemParams->set('ilayout', $ilayout);
		$app->input->set('ilayout', $ilayout);
	}


	/**
	 * Method to increment the hit counter for the item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function hit()
	{
		if ( !$this->_id )
		{
			return false;
		}

		$item = JTable::getInstance('flexicontent_items', '');
		$item->hit($this->_id);

		return true;
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
