<?php

//include constants file
require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');

defined( '_JEXEC' ) or die( 'Restricted access' );

class FlexicontentHelperPerm
{
	/*
	 * Calculates global component PERMISSIONS of the current USER
	 *
	 * @access	public
	 * @param	boolean		$force		Forces the recalculation of the PERMISSIONS
	 *
	 * @return array							The array of user PERMISSIONS 
	 * @since	2.0
	 * 
	 */
	static function getPerm($force = false)
	{
		static $permission = null;
		
		if(!$permission || $force)
		{
			// handle jcomments integration
			if (JPluginHelper::isEnabled('system', 'jcomments')) {
				$Comments_Enabled 	= 1;
				$destpath		= JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'plugins';
				$dest 			= $destpath.DS.'com_flexicontent.plugin.php';
				$source 		= JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'jcomments'.DS.'com_flexicontent.plugin.php';
				
				jimport('joomla.filesystem.file');
				if (!JFile::exists($dest)) {
					if (!JFolder::exists($destpath)) { 
						if (!JFolder::create($destpath)) { 
							JError::raiseWarning(100, JText::_('FLEXIcontent: Unable to create jComments plugin folder'));
						}
					}
					if (!JFile::copy($source, $dest)) {
						JError::raiseWarning(100, JText::_('FLEXIcontent: Unable to copy jComments plugin'));
					} else {
						$mainframe->enqueueMessage(JText::_('Copied FLEXIcontent jComments plugin'));
					}
				}
			} else {
				$Comments_Enabled 	= 0;
			}
			
			$user = JFactory::getUser();
			$permission = new stdClass;
			
			// !!! This is the Super User Privelege of GLOBAL Configuration		(==> (for J2.5) core.admin ACTION allowed on ROOT ASSET: 'root.1')
			$permission->SuperAdmin		= ($user->gid > 24);
			
			//!!! ALLOWs USERS to change component's CONFIGURATION						(==> (for J2.5) core.admin ACTION allowed on COMPONENT ASSET: e.g. 'com_flexicontent')
			$permission->CanConfig		= $permission->SuperAdmin;
					
			// No FLEXI ACCESS ..
			if (!FLEXI_ACCESS)
			{
				$permission->CanManage		= ($user->gid >= 23);  // At least J1.5 Manager
				
				$permission->CanAdd				= $user->authorize('com_content', 'add', 'content', 'all');  // ($user->gid >= 19);  // At least J1.5 Author
				$permission->CanEdit			= $user->authorize('com_content', 'edit', 'content', 'all');  // ($user->gid >= 20);  // At least J1.5 Editor
				$permission->CanEditOwn		= $user->authorize('com_content', 'edit', 'content', 'own');  // ($user->gid >= 20);  // At least J1.5 Editor
				$permission->CanPublish		= $user->authorize('com_content', 'publish', 'content', 'all');  // ($user->gid >= 21);  // At least J1.5 Publisher
				$permission->CanPublishOwn= $user->authorize('com_content', 'publish', 'content', 'own');  // ($user->gid >= 21);  // At least J1.5 Publisher
				$permission->CanDelete		= ($user->gid >= 23);  // At least J1.5 Manager
				$permission->CanDeleteOwn	= ($user->gid >= 23);  // At least J1.5 Manager
				
				$permission->CanRights		= ($user->gid >= 21);  // At least J1.5 Publisher
				
				// ITEMS: component controlled permissions
				$permission->DisplayAllItems = ($user->gid >= 23);  // At least J1.5 Manager
				$permission->CanCopy			= ($user->gid >= 23);  // At least J1.5 Manager
				$permission->CanOrder			= ($user->gid >= 23);  // At least J1.5 Manager
				$permission->CanParams		= ($user->gid >= 19);  // At least J1.5 Author
				$permission->CanVersion		= ($user->gid >= 19);  // At least J1.5 Author
				
				$permission->AssocAnyTrans		= ($user->gid >= 19);  // At least J1.5 Author
				//$permission->EditCreationDate	= ($user->gid >= 23);  // At least J1.5 Manager
				$permission->IgnoreViewState	= ($user->gid >= 20);  // At least J1.5 Editor
				
				// CATEGORIES: management tab and usage
				$permission->CanCats			= ($user->gid >= 23);  // At least J1.5 Manager
				$permission->ViewAllCats	= 1;
				$permission->ViewTree			= 1;
				$permission->MultiCat			= ($user->gid >= 19);  // At least J1.5 Author
				$permission->CanAddCats		= ($user->gid >= 23);  // At least J1.5 Manager
				
				// TAGS: management tab and usage
				$permission->CanTags			= ($user->gid >= 23);  // At least J1.5 Manager
				$permission->CanUseTags		= ($user->gid >= 19);  // At least J1.5 Author
				$permission->CanNewTags		= ($user->gid >= 19);  // At least J1.5 Author
				
				// VARIOUS management TABS: types, archives, statistics, templates, tags
				$permission->CanTypes			= ($user->gid >= 23);  // At least J1.5 Manager
				$permission->CanArchives	= ($user->gid >= 23);  // At least J1.5 Manager
				$permission->CanTemplates	= ($user->gid >= 23);  // At least J1.5 Manager
				$permission->CanStats			= ($user->gid >= 23);  // At least J1.5 Manager
				$permission->CanImport		= ($user->gid >= 23);  // At least J1.5 Manager
				
				// FIELDS: management tab
				$permission->CanFields			= ($user->gid >= 23);  // At least J1.5 Manager
				$permission->CanCopyFields	= ($user->gid >= 24);  // At least J1.5 Administrator
				$permission->CanOrderFields	= ($user->gid >= 24);  // At least J1.5 Administrator
				$permission->CanAddField		= ($user->gid >= 24);  // At least J1.5 Administrator
				$permission->CanEditField		= ($user->gid >= 24);  // At least J1.5 Administrator
				$permission->CanDeleteField	= ($user->gid >= 24);  // At least J1.5 Administrator
				$permission->CanPublishField= ($user->gid >= 24);  // At least J1.5 Administrator
				
				// FILES: management tab
				$permission->CanFiles				= ($user->gid >= 19);  // At least J1.5 Author
				$permission->CanUpload			= ($user->gid >= 19);  // At least J1.5 Author
				$permission->CanViewAllFiles= ($user->gid >= 23);  // At least J1.5 Manager
				
				// AUTHORS: management tab
				$permission->CanAuthors		= ($user->gid >= 24);  // At least J1.5 Administrator
				
				// SEARCH INDEX: management tab
				$permission->CanIndex			= ($user->gid >= 23);  // At least J1.5 Manager
				
				// OTHER components permissions
				$permission->CanPlugins		= ($user->gid >= 24);  // At least J1.5 Administrator
				$permission->CanComments	= ($user->gid >= 23);  // At least J1.5 Manager
				$permission->CanComments	=	$permission->CanComments && $Comments_Enabled;
				
				// Global parameter to force always displaying of categories as tree
				if (JComponentHelper::getParams('com_flexicontent')->get('cats_always_astree', 1)) {
					$permission->ViewTree = 1;
				}
				return $permission;
			}
			
			//!!! ALLOWs USERS in JOOMLA BACKEND : (not used in J1.5)
			//   (a) to view the FLEXIcontent menu item in Components Menu and
			//   (b) to access the FLEXIcontent component screens (whatever they are allowed to see by individual FLEXIcontent area permissions)
			//       NOTE: the initially installed permissions allows all areas to be managed for J2.5 and none (except for items) for J1.5
			$permission->CanManage		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'manage', 'users', $user->gmid) : 1;
			
			// ITEMS/CATEGORIES: category-inherited permissions, (NOTE: these are the global settings, so:)
			// *** 1. the action permissions of individual items are checked seperately per item
			// *** 2. the view permission is checked via the access level of each item
			$permission->CanAdd				= ($user->gid < 25) ? (FAccess::checkComponentAccess('com_content', 'submit', 'users', $user->gmid) || FAccess::checkAllContentAccess('com_content','add','users',$user->gmid,'content','all')) : 1;
			$permission->CanEdit			= ($user->gid < 25) ? FAccess::checkComponentAccess('com_content', 'edit', 'users', $user->gmid)		: 1;
			$permission->CanEditOwn		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_content', 'editown', 'users', $user->gmid)			: 1;
			$permission->CanPublish		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_content', 'publish', 'users', $user->gmid)	: 1;
			$permission->CanPublishOwn= ($user->gid < 25) ? FAccess::checkComponentAccess('com_content', 'publishown', 'users', $user->gmid)	: 1;
			$permission->CanDelete		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_content', 'delete', 'users', $user->gmid)	: 1;
			$permission->CanDeleteOwn	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_content', 'deleteown', 'users', $user->gmid)		: 1;
			
			// Permission for changing the access level of items and categories that user can edit
			// (a) In J1.5, this is the FLEXIaccess component access permission, and
			// (b) In J2.5, this is the FLEXIcontent component ACTION 'accesslevel'
			$permission->CanRights		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexiaccess', 'manage', 'users', $user->gmid) : 1;
			
			// ITEMS: component controlled permissions
			$permission->DisplayAllItems = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'displayallitems', 'users', $user->gmid) : 1; // (backend) List all items (otherwise only items that can be edited)
			$permission->CanCopy			= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'copyitems', 'users', $user->gmid)	: 1; // (backend) Item Copy Task
			$permission->CanOrder			= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'order', 'users', $user->gmid)			: 1; // (backend) Reorder items inside the category
			$permission->CanParams		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'paramsitems', 'users', $user->gmid) : 1; // (backend) Edit item parameters like meta data and template parameters
			$permission->CanVersion		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'versioning', 'users', $user->gmid) : 1; // (backend) Use item versioning
			
			$permission->AssocAnyTrans		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'assocanytrans', 'users', $user->gmid) : 1; // (item edit form) associate any translation
			//$permission->EditCreationDate	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'editcreationdate', 'users', $user->gmid) : 1; // (item edit form) edit creation date (frontend)
			$permission->IgnoreViewState	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'ignoreviewstate', 'users', $user->gmid) : 1; // (Frontend Content Lists) ignore view state
			
			// CATEGORIES: management tab and usage
			$permission->CanCats			= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'categories', 'users', $user->gmid) : 1; // (item edit form) view the categories which user cannot assign to items
			$permission->ViewAllCats	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'usercats', 'users', $user->gmid) : 1; // (item edit form) view the categories which user cannot assign to items
			$permission->ViewTree			= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'cattree', 'users', $user->gmid) : 1; // (item edit form) view categories as tree instead of flat list
			$permission->MultiCat			= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'multicat', 'users', $user->gmid) : 1; // (item edit form) allow user to assign each item to multiple categories
			$permission->CanAddCats		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'addcats', 'users', $user->gmid) : 1;
			
			// TAGS: management tab and usage
			$permission->CanTags			= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'tags', 'users', $user->gmid) : 1; // (backend) Allow management of Item Types
			$permission->CanUseTags		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'usetags', 'users', $user->gmid) : 1; // edit already assigned Tags of items
			$permission->CanNewTags		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'newtags', 'users', $user->gmid) : 1; // add new Tags to items
			
			// VARIOUS management TABS: types, archives, statistics, templates, tags
			$permission->CanTypes			= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'types', 'users', $user->gmid) : 1; // (backend) Allow management of Item Types
			$permission->CanArchives	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'archives', 'users', $user->gmid) : 1; // (backend) Allow management of Archives
			$permission->CanTemplates	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'templates', 'users', $user->gmid) : 1; // (backend) Allow management of Templates
			$permission->CanStats			= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'stats', 'users', $user->gmid) : 1; // (backend) Allow management of Statistics
			$permission->CanImport		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'import', 'users', $user->gmid) : 1; // (backend) Allow management of (Content) Import
			
			// FIELDS: management tab
			$permission->CanFields			= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'fields', 'users', $user->gmid) : 1; // (backend) Allow management of Fields
			$permission->CanCopyFields	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'copyfields', 'users', $user->gmid)	: 1; // (backend) Field Copy Task
			$permission->CanOrderFields	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'orderfields', 'users', $user->gmid) : 1; // (backend) Reorder fields inside each item type
			$permission->CanAddField		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'createfield', 'users', $user->gmid) : 1; // (backend) Create fields
			$permission->CanEditField		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'editfield', 'users', $user->gmid) : 1; // (backend) Edit fields
			$permission->CanDeleteField	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'deletefield', 'users', $user->gmid) : 1; // (backend) Delete fields
			$permission->CanPublishField= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'publishfield', 'users', $user->gmid) : 1; // (backend) Publish fields
			
			// FILES: management tab
			$permission->CanFiles				= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'files', 'users', $user->gmid) : 1;
			$permission->CanUpload			= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'uploadfiles', 'users', $user->gmid) : 1; // allow user to upload Files
			$permission->CanViewAllFiles= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'viewallfiles', 'users', $user->gmid) : 1; // allow user to view all Files
			
			// AUTHORS: management tab
			$permission->CanAuthors		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_users', 'manage', 'users', $user->gmid) : 1;
			$permission->CanGroups		= 0;//FLEXI_J16GE ? $permission->CanAuthors : 0;
			
			// SEARCH INDEX: management tab
			$permission->CanIndex			= $permission->CanFields && ($permission->CanAddField || $permission->CanEditField);
			
			// OTHER components permissions
			$permission->CanPlugins		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_plugins', 'manage', 'users', $user->gmid) : 1;
			$permission->CanComments	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_jcomments', 'manage', 'users', $user->gmid) : 1;
			$permission->CanComments	=	$permission->CanComments && $Comments_Enabled;
			
			// Global parameter to force always displaying of categories as tree
			if (JComponentHelper::getParams('com_flexicontent')->get('cats_always_astree', 1)) {
				$permission->ViewTree = 1;
			}
		}
		
		return $permission;
	}
	
	
	/**
	 * Lookups the categories (their IDs), that the user has access to perforn the specified action(s)
	 *
	 * @param object	$user             The user on which to check privileges
	 * @param array		$actions_allowed  The required actions
	 * @param bool		$require_all      True to require --all-- (Logical AND) or false to require --any-- (Logical OR)
	 * @param bool		$check_published  True to include only published categories
	 *
	 * @return array									The category IDs
	 * @since	2.0
	 */
	static function getAllowedCats( &$user, $actions_allowed=array('core.create', 'core.edit', 'core.edit.own'), $require_all=true, $check_published = false, $specific_catids=false, $find_first = false )
	{
		global $globalcats;
		$db = JFactory::getDBO();
		$usercats = array();
		
		if (FLEXI_J16GE)
		{
			// *** J1.6+ ***
			$query = 'SELECT c.id '
				. ' FROM #__categories AS c'
				. ' WHERE extension='.$db->Quote(FLEXI_CAT_EXTENSION)
				. ($check_published ? '  AND c.published = 1 ' : '')
				. ($specific_catids ? '  AND c.id IN ('.implode(",", $specific_catids).')' : '')
				;
			$db->setQuery($query);
			$allcats = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
			
			foreach ($allcats as $category_id)
			{
				// Construct asset name for the category
				$asset = 'com_content.category.'.$category_id;
				
				// Check all actions with Logical OR or Logical AND
				// We start with FALSE for OR and TRUE for AND
				$has_access = $require_all ? true : false;
				foreach ($actions_allowed as $action_name)
				{
					$has_access = $require_all ? ($has_access && $user->authorise($action_name, $asset)) : ($has_access || $user->authorise($action_name, $asset));
				}
				if ($has_access) {
					$usercats[] = $category_id;
					if ($find_first) break;  // J1.6+ performance consideration skip checking permissions of remaining categories
				}
			}
			return $usercats;
			
		} else if (!FLEXI_ACCESS || $user->gid == 25) {
			
			// *** J1.5 without FLEXIaccess or user is super admin, return all category ids ***
			
			if ($user->gid < 19) return array();  // Less than J1.5 Author
			return FlexicontentHelperPerm::returnAllCats ($check_published, $specific_catids);
			
		} else {
			
			// *** J1.5 with FLEXIaccess ***
			$aro_value = $user->gmid; // FLEXIaccess group
			
			// Create a limit for aco (ACTION privilege)
			$limit_aco = array();
			if ( in_array('core.create',$actions_allowed) )
				$limit_aco[] = 'aco = ' . $db->Quote('add');
			if ( in_array('core.edit',$actions_allowed) )
				$limit_aco[] = 'aco = ' . $db->Quote('edit');
			if ( in_array('core.edit.own',$actions_allowed) )
				$limit_aco[] = 'aco = ' . $db->Quote('editown');
			
			$oper = $require_all ? ' AND ' : ' OR ';
			if  (count($limit_aco) ) {
				$limit_aco = implode($oper, $limit_aco);
			} else {
				$limit_aco = 'aco = ' . $db->Quote('add') . $oper . ' aco = ' . $db->Quote('edit') . $oper . ' aco = ' . $db->Quote('editown');
			}
			
			// We will search for permission all on the given permission (add,edit,editown),
			// if found it means that there are no ACL limitations for given user, aka return all cats
			$query	= 'SELECT COUNT(*) FROM #__flexiaccess_acl'
					. ' WHERE acosection = ' . $db->Quote('com_content')
					. ' AND ( ' . $limit_aco . ' )'
					. ' AND arosection = ' . $db->Quote('users')
					. ' AND aro IN ( ' . $aro_value . ' )'
					. ' AND axosection = ' . $db->Quote('content')
					. ' AND axo = ' . $db->Quote('all')
					;
			$db->setQuery($query);
			
			
			// *** No limitations found, return all category ids ***
			
			if ($db->loadResult()) {
				return FlexicontentHelperPerm::returnAllCats ($check_published, $specific_catids);
			}
			
			// *** Limitations found, check and return category ids with 'create' permission ***
			
			// creating for -content- axosection is 'add' but for -category- axosection is 'submit'
			$limit_aco = str_replace('add', 'submit', $limit_aco);
			
			$query	= 'SELECT axo FROM #__flexiaccess_acl'
					. ' WHERE acosection = ' . $db->Quote('com_content')
					. ' AND ( ' . $limit_aco . ' )'
					. ' AND arosection = ' . $db->Quote('users')
					. ' AND aro IN ( ' . $aro_value . ' )'
					. ' AND axosection = ' . $db->Quote('category')
					. ($specific_catids ? '  AND axo IN ('.implode(",", $specific_catids).')' : '')
					;
			$db->setQuery($query);
			$allowedcats = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
			
			$allowedcats = $allowedcats ? $allowedcats : array();
			// we add all descendent to the array
			foreach ($allowedcats as $allowedcat) {
				$usercats[] = $allowedcat;
				if ($globalcats[$allowedcat]->children) {
					foreach ($globalcats[$allowedcat]->descendantsarray as $k => $v) {
						if(!$check_published || $globalcats[$v]->published) {
							$usercats[] = $v;
						}
					}
				}
			}
			$usercats = array_unique($usercats);
			return $usercats;
		}
	}
	
	
	/*
	 * Method to return all categories ids checking if published and if in specific subset
	 */
	function returnAllCats ($check_published, $specific_catids)
	{
		global $globalcats;
		$usercats = array();
		
		if ($specific_catids) {
			foreach ($specific_catids as $k) {
				if (!$check_published || $globalcats[$k]->published) {
					$usercats[] = $k;
				}
			}
		} else {
			foreach ($globalcats as $k => $v) {
				if(!$check_published || $v->published) {
					$usercats[] = $k;
				}
			}
		}
		$usercats = array_unique($usercats);
		return $usercats;
	}
	
}
?>