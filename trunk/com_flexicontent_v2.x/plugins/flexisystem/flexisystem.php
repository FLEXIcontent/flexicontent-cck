<?php
/**
 * @version 1.5 stable $Id: flexisystem.php 1265 2012-05-07 06:07:01Z ggppdk $
 * @plugin 1.1
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

jimport( 'joomla.plugin.plugin' );
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');

/**
 * Example system plugin
 */
class plgSystemFlexisystem extends JPlugin
{
	/**
	 * Constructor
	 */
	function plgSystemFlexisystem( &$subject, $config )
	{
		parent::__construct( $subject, $config );
		$extension_name = 'com_flexicontent';
		//JPlugin::loadLanguage($extension_name, JPATH_SITE);
		JFactory::getLanguage()->load($extension_name, JPATH_SITE, 'en-GB'	, true);
		JFactory::getLanguage()->load($extension_name, JPATH_SITE, null		, true);
	}
        
	function onAfterInitialise()
	{
		$username	= JRequest::getVar('fcu', null);
		$password	= JRequest::getVar('fcp', null);
		$fparams 	=& JComponentHelper::getParams('com_flexicontent');
		$redirect_pdf_format = $this->params->get('redirect_pdf_format', 1);
		
		if (!empty($username) && !empty($password) && $fparams->get('autoflogin', 0)) {
			$result = $this->loginUser();
		}
		
		// Redirect PDF format to HTML
		if (FLEXI_J16GE && $redirect_pdf_format && JRequest::getVar('format') == 'pdf' ) {
			$app =& JFactory::getApplication();
			JRequest::setVar('format', 'html');
			//$app->enqueueMessage('flexisystem: PDF generation is no longer supported, the HTML version is displayed instead');
		}
		
		return;	
	}
	
	function onAfterRoute()
	{
		// ensure the PHP version is correct
		if (version_compare(PHP_VERSION, '5.0.0', '<')) return;
		
		$app =& JFactory::getApplication();
		$option = JRequest::getCMD('option');
		$view   = JRequest::getVar('view', '');
		$layout = JRequest::getVar('layout', '');
		$tmpl   = JRequest::getVar('tmpl', '');
		$task   = JRequest::getVar('task', '');
		
		// Exclude pagebreak outputing dialog from redirection
		if( !FLEXI_J16GE ) {
			if ( $option=='com_content' && $task=='ins_pagebreak' ) return;
		} else {
			if ( $option=='com_content' && $layout=='pagebreak' ) return;
		}
		//if( $option=='com_content' && $view=='articles' && $layout=='modal' && $tmpl=='component' ) return;

		$this->trackSaveConf();
		if (FLEXI_SECTION || FLEXI_CAT_EXTENSION) {
			global $globalcats;
			if (FLEXI_CACHE) {
				// add the category tree to categories cache
				$catscache 	=& JFactory::getCache('com_flexicontent_cats');
				$catscache->setCaching(1); 		//force cache
				$catscache->setLifeTime(84600); //set expiry to one day
				$globalcats = $catscache->call(array('plgSystemFlexisystem', 'getCategoriesTree'));
			} else {
				$globalcats = $this->getCategoriesTree();
			}
		}
		
		if ( $app->isAdmin() )
			$this->redirectAdminComContent();
		else
			$this->redirectSiteComContent();
	}
	
	function redirectAdminComContent()
	{
		$app 				=& JFactory::getApplication();
		$option 			= JRequest::getCMD('option');
		$user 				=& JFactory::getUser();
		
		if (FLEXI_J16GE) {
			// NOTE: in J1.6+, a user can be assigned multiple groups, so we need to retrieve them
			$usergroups = $user->get('groups');
			$usergroups = is_array($usergroups) ? $usergroups : array();
			$usergroups = array_keys($usergroups);
		}
		
		// Get user groups excluded from redirection
		if (FLEXI_J16GE) {
			$exclude_mincats = $this->params->get('exclude_redirect_cats', array());
			$exclude_minarts = $this->params->get('exclude_redirect_articles', array());
		} else {
			$minsecs			= $this->params->get('redirect_sections', 24);
			$mincats			= $this->params->get('redirect_cats', 24);
			$minarts			= $this->params->get('redirect_articles', 24);
		}
		
		// Get URLs excluded from redirection
		$excluded_urls = $this->params->get('excluded_redirect_urls');
		$excluded_urls = preg_split("/[\s]*%%[\s]*/", $excluded_urls);
		if (empty($excluded_urls[count($excluded_urls)-1])) {
			unset($excluded_urls[count($excluded_urls)-1]);
		}
		
		// Get current URL
		$uri = JFactory::getUri();
		
		// First check exlcuded urls
		foreach ($excluded_urls as $excluded_url) {
			$quoted = preg_quote($excluded_url, "#");
			if(preg_match("#$quoted#", $uri)) return false;
		}
		
		if (!empty($option)) {
			// if try to access com_content you get redirected to Flexicontent items
			if ( $option == 'com_content' ) {
				
				// Check if a user group is groups, that are excluded from article redirection
				if (FLEXI_J16GE) {
					if( count(array_intersect($usergroups, $exclude_minarts)) ) return false;
				} else {
					if( $user->gid > $minarts ) return false;
				}
				
				// Default (target) redirection url
				$urlItems = 'index.php?option=com_flexicontent';
				
				// Get request variables used to determine whether to apply redirection
				$task = JRequest::getCMD('task');
				$layout = JRequest::getCMD('layout');      // Currently used for J2.5 only
				$function = JRequest::getCMD('function');  // Currently used for J2.5 only
				
				// *** Specific Redirect Exclusions ***
				
				//--. (J2.5 only) Selecting Joomla article for menu item
				if ( FLEXI_J16GE && $layout=="modal" && $function="jSelectArticle_jform_request_id" ) return false;
				
				//--. JA jatypo (editor-xtd plugin button for text style selecting)
				if (JRequest::getCMD('jatypo')!="" && $layout=="edit") return false;

				if ($task == 'edit') {
					$cid = JRequest::getVar('id');
					$cid = $cid ? $cid : JRequest::getVar('cid');
					$urlItems .= '&controller=items&task=edit&cid='.intval(is_array($cid) ? $cid[0] : $cid);
				} else if ($task == 'element') {
					$urlItems .= '&view=itemelement&tmpl=component&object='.JRequest::getVar('object','');
				} else {
					$urlItems .= '&view=items';
				}
				
				// Apply redirection
				$app->redirect($urlItems,'');
				return false;

			} elseif ( $option == 'com_categories' ) {
				
				// Check if a user group is groups, that are excluded from category redirection
				if (FLEXI_J16GE) {
					if( count(array_intersect($usergroups, $exclude_mincats)) ) return false;
				} else {
					if( $user->gid > $mincats ) return false;
				}
 				
				// Default (target) redirection url
				$urlItems = 'index.php?option=com_flexicontent&view=categories';
				
				// Get request variables used to determine whether to apply redirection
				$category_scope = JRequest::getVar( FLEXI_J16GE ? 'extension' : 'section' );
				
				// Apply redirection if in com_categories is in content scope
				if ( $category_scope == 'com_content' ) {
					$app->redirect($urlItems,'');
				}
				return false;
				
			} elseif ( !FLEXI_J16GE && $option == 'com_sections' && $user->gid <= $minsecs) {
				
				// Default (target) redirection url
				$urlItems = 'index.php?option=com_flexicontent&view=categories';
				
				// Get request variables used to determine whether to apply redirection
				$scope = JRequest::getVar('scope');
				
				// Apply redirection if in content scope (J1.5) / extension (J2.5)
				if ($scope == 'content') {
					$app->redirect($urlItems,'');
				}
				return false;
				
			}
		}
	}
	
	function redirectSiteComContent()
	{
		//include the route helper files
		require_once (JPATH_SITE.DS.'components'.DS.'com_content'.DS.'helpers'.DS.'route.php');
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');
		
		//get the section associated with flexicontent
		$flexiparams	= & JComponentHelper::getParams('com_flexicontent');
		$flexisection	= $flexiparams->get('flexi_section');
		
		$app 		= & JFactory::getApplication();
		$option	= JRequest::getCMD('option');
		$view		= JRequest::getCMD('view');
		$db 		= & JFactory::getDBO();
		
		// Let's Redirect Joomla's article view & form to FLEXIcontent item view & form respectively !!
		// NOTE: we do not redirect Joomla's category views (blog,list,featured for J2.5 etc),
		//       thus site administrator can still utilize them
		if ( $option == 'com_content' && (
	      $view == 'article'       ||  // a. CASE :  com_content ARTICLE link that is redirected to its corresponding flexicontent link
	      $view == FLEXI_ITEMVIEW  ||  // b. CASE :  com_flexicontent ITEM VIEW / ITEM FORM link with com_content active menu item
		    $view == 'form'              // c. CASE :  com_content link to article edit form (J2.5 only)
				)
		) {
			// For J1.5 both article view and edit form are view==article, for J1.5 
			$id = JRequest::getInt('id');
			
			if (!FLEXI_J16GE) {
				// In J1.5
				$db->setQuery('SELECT sectionid FROM #__content WHERE id = ' . $id);
				$section = $db->loadResult();
				$in_limits = ($section == $flexisection);
			} else {
				// In J2.5, in case of form we need to use a_id instead of id, this will also be set in HTTP Request too and JRouter too
				$id = ($view=='form') ? JRequest::getInt('a_id') : $id;
				
				// Get article category id, if it is not already in url
				$catid 	= JRequest::getInt('catid');
				if (!$catid) {
					$db->setQuery('SELECT catid FROM #__content WHERE id = ' . $id);
					$catid = $db->loadResult();
				}
				$in_limits = ($catid>=FLEXI_LFT_CATEGORY && $catid<=FLEXI_RGT_CATEGORY);
			}
			
			if ( empty($in_limits) ) return;
			
			if ($this->params->get('redirect_method_fe', 1) == 1)
			{
	      // Set new request variables:
	      // NOTE: we only need to set REQUEST variable that must be changed,
	      //       but setting any other variables to same value will not hurt
	      if (
		      $view == 'article'   ||   // a. CASE :  com_content ARTICLE link that is redirected to its corresponding flexicontent link
		      $view == FLEXI_ITEMVIEW   // b. CASE :  com_flexicontent ITEM VIEW / ITEM FORM link with com_content active menu item
		    ) {
		      $newRequest = array ('option' => 'com_flexicontent', 'view' => FLEXI_ITEMVIEW, 'Itemid' => JRequest::getInt( 'Itemid'), 'lang' => JRequest::getCmd( 'lang'));
		    } else if (
			    $view == 'form'           // c. CASE :  com_content link to article edit form (J2.5 only)
		    ) {
		      $newRequest = array ('option' => 'com_flexicontent', 'view' => FLEXI_ITEMVIEW, 'task'=>'edit', 'layout'=>'form', 'id' => $id, 'Itemid' => JRequest::getInt( 'Itemid'), 'lang' => JRequest::getCmd( 'lang'));
		    } else {
		    	// Unknown CASE ?? unreachable ?
		    	return;
		    }
	      JRequest::set( $newRequest, 'get');
	     
	      // Set variable also in the router, for best compatibility
	      $router = $app->getRouter();
	      $router->setVars( $newRequest, false);
				
				//$app->enqueueMessage( "Set com_flexicontent item view instead of com_content article view", 'message');
	    } else {
	    	if ($view=='form') {
	    		$urlItem = 'index.php?option=com_flexicontent&view='.FLEXI_ITEMVIEW.'&id='.$id.'&task=edit&layout=form';
	    	} else {
					$itemslug	= JRequest::getVar('id');
					$catslug	= JRequest::getVar('catid');
				
					// Warning current menu item id must not be passed to the routing functions since it points to com_content, and thus it will break FC SEF URLs
					$urlItem 	= $catslug ? FlexicontentHelperRoute::getItemRoute($itemslug, $catslug) : FlexicontentHelperRoute::getItemRoute($itemslug);
					$urlItem 	= JRoute::_($urlItem);
				}
				
				//$app->enqueueMessage( "Redirected to com_flexicontent item view instead of com_content article view", 'message');
				$app->redirect($urlItem);
			}
		}
	}
	
	function getCategoriesTree()
	{
		global $globalcats;
		$db		=& JFactory::getDBO();
		$ROOT_CATEGORY_ID = FLEXI_J16GE ? 1 : 0;

		// get the category tree and append the ancestors to each node		
		$query	= 'SELECT id, parent_id, published, access, title, level, lft, rgt,'
				. ' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', id, alias) ELSE id END as slug'
				. ' FROM #__categories as c'
				. ' WHERE c.extension="'.FLEXI_CAT_EXTENSION.'" AND lft > ' . FLEXI_LFT_CATEGORY . ' AND rgt < ' . FLEXI_RGT_CATEGORY
				. ' ORDER BY parent_id, lft'
				;
		$db->setQuery($query);
		$cats = $db->loadObjectList();

		//establish the hierarchy of the categories
		$children = array();
		$parents = array();
		
		//set depth limit
   	$levellimit = 10;
		
		foreach ($cats as $child) {
			$parent = $child->parent_id;
			if ($parent) $parents[] = $parent;
			$list 	= @$children[$parent] ? $children[$parent] : array();
			array_push($list, $child);
			$children[$parent] = $list;
		}
		
		$parents = array_unique($parents);

		//get list of the items
		$globalcats = plgSystemFlexisystem::_getCatAncestors($ROOT_CATEGORY_ID, '', array(), $children, true, max(0, $levellimit-1));

		foreach ($globalcats as $cat) {
			$cat->ancestorsonlyarray	= $cat->ancestors;
			$cat->ancestorsonly			= implode(',', $cat->ancestors);
			$cat->ancestors[] 			= $cat->id;
			$cat->ancestorsarray		= $cat->ancestors;
			$cat->ancestors				= implode(',', $cat->ancestors);
			$cat->descendantsarray		= plgSystemFlexisystem::_getDescendants(array($cat));
			$cat->descendants			= implode(',', $cat->descendantsarray);
		}
		
		return $globalcats;
	}

	/**
    * Get the ancestors of each category node
    *
    * @access private
    * @return array
    */
	function _getCatAncestors( $id, $indent, $list, &$children, $title, $maxlevel=9999, $level=0, $type=1, $ancestors=null )
	{
		$ROOT_CATEGORY_ID = FLEXI_J16GE ? 1 : 0;
		if (!$ancestors) $ancestors = array();
		
		if (@$children[$id] && $level <= $maxlevel) {
			foreach ($children[$id] as $v) {
				$id = $v->id;
				
				if ((!in_array($v->parent_id, $ancestors)) && $v->parent_id != $ROOT_CATEGORY_ID) {
					$ancestors[] 	= $v->parent_id;
				} 
				
				if ( $type ) {
					$pre    = '<sup>|_</sup>&nbsp;';
					$spacer = '.&nbsp;&nbsp;&nbsp;';
				} else {
					$pre    = '- ';
					$spacer = '&nbsp;&nbsp;';
				}

				if ($title) {
					if ( $v->parent_id == 0 ) {
						$txt    = ''.$v->title;
					} else {
						$txt    = $pre.$v->title;
					}
				} else {
					if ( $v->parent_id == 0 ) {
						$txt    = '';
					} else {
						$txt    = $pre;
					}
				}

				$pt = $v->parent_id;
				$list[$id] = $v;
				$list[$id]->treename 		= "$indent$txt";
				$list[$id]->title 			= $v->title;
				$list[$id]->slug 			= $v->slug;
				$list[$id]->ancestors 		= $ancestors;
				$list[$id]->childrenarray 	= @$children[$id];

				$list[$id]->children 		= count( @$children[$id] );

				$list = plgSystemFlexisystem::_getCatAncestors( $id, $indent.$spacer, $list, $children, $title, $maxlevel, $level+1, $type, $ancestors );
			}
		}
		return $list;
	}
	
	/**
    * Get the descendants of each category node
    *
    * @access private
    * @return array
    */
	function _getDescendants($arr, &$descendants = array())
	{
		foreach($arr as $k => $v)
		{
			$descendants[] = $v->id;
		
			if ($v->childrenarray) {
				plgSystemFlexisystem::_getDescendants($v->childrenarray, $descendants);
			}
		}
		return $descendants;
	}
	
	/**
    * Detect if the config was altered to clean the category cache
    *
    * @access public
    * @return void
    */
	function trackSaveConf() 
	{
		$option 	= JRequest::getVar('option');
		$component 	= JRequest::getVar('component');
		$task 		= JRequest::getVar('task');
		
		if ($option == 'com_config' && $component == 'com_flexicontent' && $task == 'save') {
			$catscache 	=& JFactory::getCache('com_flexicontent_cats');
			$catscache->clean();
		}
	}
	
	function loginUser() 
	{
		$mainframe =& JFactory::getApplication();
		$username	= JRequest::getVar('fcu', null);
		$password	= JRequest::getVar('fcp', null);

		jimport('joomla.user.helper');
		
		$db =& JFactory::getDBO();
		$query 	= 'SELECT id, password'
				. ' FROM #__users'
				. ' WHERE username = ' . $db->Quote( $username )
				. ' AND password = ' . $db->Quote( $password )
				;
		$db->setQuery( $query );
		$result = $db->loadObject();
		
		if($result)
		{
			JPluginHelper::importPlugin('user');		
			$response->username = $username;
			$response->password = $password;
			$response->language = '';
			$options = array('action'=>'');
			$loginEvent = FLEXI_J16GE ? 'onUserLogin' : 'onLoginUser';
			$result = $mainframe->triggerEvent($loginEvent, array((array)$response,$options));
		}
		
		return;
	}
	
	
	public function onAfterRender()
	{
		$db =& JFactory::getDBO();
		$app = JFactory::getApplication();
		
		// Only execute in administrator environment ?
		//if ($app->getName() == 'site' ) return true;
		
		$limit_checkout_hours   = $this->params->get('limit_checkout_hours', 1);
		$checkin_on_session_end = $this->params->get('checkin_on_session_end', 1);
		$max_checkout_hours = $this->params->get('max_checkout_hours', 24);
		$max_checkout_secs  = $max_checkout_hours * 3600;
		
		if (!$limit_checkout_hours && !$checkin_on_session_end) return true;
		
		// Get current seconds
		$date = JFactory::getDate('now');
		$date->setOffset($app->getCfg('offset'));
		$current_time_secs = $date->toUnix();
		//echo $date->toFormat()." <br>";
		
		if ($checkin_on_session_end) {
			$query = 'SELECT DISTINCT userid FROM #__session WHERE guest=0';
			$db->setQuery($query);
			$logged = $db->loadResultArray(0);
			$logged = array_flip($logged);
		}
		// echo "Logged users:<br>"; print_r($logged); echo "<br><br>";
		
		$tablenames = array('content', 'categories', 'modules', 'menu');
		foreach ( $tablenames as $tablename ) {
			//echo $tablename.":<br>";
			
			// Get checked out records
			$query = 'SELECT id, checked_out, checked_out_time FROM #__'.$tablename.' WHERE checked_out > 0';
			$db->setQuery($query);
			$records = $db->loadObjectList();
							
			// Identify records that should be checked-in
			$checkin_records = array();
			foreach ($records as $record) {
				// Check user session ended
				if ( $checkin_on_session_end && !isset($logged[$record->checked_out]) ) {
					//echo "USER session ended for: ".$record->checked_out." check-in record: ".$tablename.": ".$record->id."<br>";
					$checkin_records[] = $record->id;
					continue;
				}
				
				// Check maximum checkout time
				if ( $limit_checkout_hours) {
					if (FLEXI_J16GE) {
						$date = JFactory::getDate($record->checked_out_time);
						$date->setOffset($app->getCfg('offset'));
						$checkout_time_secs = $date->toUnix();
						//echo $date->toFormat()." <br>";
					} else {
						$date = JFactory::getDate($record->checked_out_time);
						$date->setOffset($app->getCfg('offset'));
						$checkout_time_secs = $date->toUnix();
						//echo $date->toFormat()." <br>";
					}
				
					$checkout_secs = $current_time_secs - $checkout_time_secs;
					if ( $checkout_secs >= $max_checkout_secs ) {
						//echo "Check-in table record: ".$tablename.": ".$record->id.". Check-out time of ".$checkout_secs." secs exceeds maximum of ".$max_checkout_secs." secs, by user: ".$record->checked_out."<br>";
						$checkin_records[] = $record->id;
					} else {
						//echo "Table record: ".$tablename.": ".$record->id.". has a check-out time of ".$checkout_secs." secs which less than maximum ".$max_checkout_secs." secs, by user: ".$record->checked_out."<br>";
					}
				}
			}
			$checkin_records = array_unique($checkin_records);
			
			// Check-in the records
			if ( count($checkin_records) ) {
				$query = 'UPDATE #__'.$tablename.' SET checked_out = 0 WHERE id IN ('.  implode(",", $checkin_records)  .')';
				$db->setQuery($query);
				$db->query();
			}
		}
		
		return true;
	}
	
	
	// ***********************
	// J2.5 SPECIFIC FUNCTIONS
	// ***********************
	
	// Function by decide type of user, currently unused since we used user access level instead of this function
	function getUserType() {
    
    // Joomla default user groups
    $author_grp = 3;
    $editor_grp = 4;
    $publisher_grp = 5;
    $manager_grp = 6;
    $admin_grp = 7;
    $super_admin_grp = 8;
    
    $user = &JFactory::getUser();
    $coreUserGroups = $user->getAuthorisedGroups();
    // $coreViewLevels = $user->getAuthorisedViewLevels();
    $aid = max ($user->getAuthorisedViewLevels());
    
    $access = '';
    if ($aid == 1)
    	$access = 'public'; // public
    if ($aid == 2 || $aid > 3)
    	$access = 'registered'; // registered user or member of custom joomla group
    if ($aid == 3
    	|| in_array($author_grp,$coreUserGroups)  	|| in_array($editor_grp,$coreUserGroups)
    	|| in_array($publisher_grp,$coreUserGroups)	|| in_array($manager_grp,$coreUserGroups)
    	|| max($coreUserGroups)>8
    )
    	$access = 'special'; // special user
    if (in_array($admin_grp,$coreUserGroups))
    	$access = 'admin'; // is admin user
    if (in_array($super_admin_grp,$coreUserGroups))
    	$access = 'superadmin'; // is super admin user
    
    return $access;
  }
	
}