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
		
		// (a) Check-in DB table records according to time limits set
		$this->checkinRecords();
		
		// (b) Autologin for frontend preview
		if (!empty($username) && !empty($password) && $fparams->get('autoflogin', 0)) {
			$result = $this->loginUser();
		}
		
		// (b) Route PDF format to HTML format for J1.6+
		$redirect_pdf_format = $this->params->get('redirect_pdf_format', 1);
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
		$fparams 	=& JComponentHelper::getParams('com_flexicontent');
		
		// Detect mobile devices, and set fc_use_mobile session flag
		if ($fparams->get('detect_mobile')) $this->detectMobileClient($fparams);
		
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
		
		// Let's Redirect/Reroute Joomla's article view & form to FLEXIcontent item view & form respectively !!
		// NOTE: we do not redirect/reroute Joomla's category views (blog,list,featured for J2.5 etc),
		//       thus site administrator can still utilize them
		if ( $option == 'com_content' && (
	      $view == 'article'       ||  // a. CASE :  com_content ARTICLE link that is redirected/rerouted to its corresponding flexicontent link
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
		      $view == 'article'   ||   // a. CASE :  com_content ARTICLE link that is rerouted to its corresponding flexicontent link
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
		
			if ( !empty($v->childrenarray) ) {
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
			$response = new stdClass();
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
		$db = JFactory::getDBO();
		$app      = JFactory::getApplication();
		$session 	= JFactory::getSession();
		
		// If this is reached we now that the code for setting screen cookie has been added
		if ( $session->get('screenSizeCookieToBeAdded', 0, 'flexicontent') ) {
			$session->set('screenSizeCookieTried', 1, 'flexicontent');
			$session->set('screenSizeCookieToBeAdded', 0, 'flexicontent');
		}

		// Only execute in administrator environment ?
		//if ($app->getName() == 'site' ) return true;
		
		return true;
	}
	
	
	/**
	 * Utility function to detect mobile browser and / or low resolution
	 *
	 * @param 	boolean 	$_is_lowres
	 * @param 	boolean 	$_is_mobile
	 * @param 	boolean 	$force_check_lowres
	 * @return 	void
	 * @since 1.5
	 */
	function detectMobileClient(& $fparams)
	{

		$app      = & JFactory::getApplication();
		$session 	= & JFactory::getSession();
		$fparams 	= & JComponentHelper::getParams('com_flexicontent');
		
		$debug_mobile = $fparams->get('debug_mobile');
		
		// Get session variables
		$use_mobile = $session->get('fc_use_mobile', null, 'flexicontent');
		$is_mobile  = $session->get('fc_is_mobile', null, 'flexicontent');
		$is_lowres  = $session->get('fc_is_lowres', null, 'flexicontent');
		
		// Decide conditions to require
		$mobile_conditions = $fparams->get('mobile_conditions');
		if ( empty($mobile_conditions) )						$mobile_conditions = array();
		else if ( ! is_array($mobile_conditions) )	$mobile_conditions = !FLEXI_J16GE ? array($mobile_conditions) : explode("|", $mobile_conditions);
		
		$require_mobile = in_array('mobile_browser', $mobile_conditions)  ||  (in_array('low_resolution', $mobile_conditions) && $fparams->get('check_lowres')==0);
		$require_lowres = in_array('low_resolution', $mobile_conditions);
		
		// Screen resolution is known after second reload
		if ( $is_lowres===null && isset($_COOKIE["fc_is_lowres"]) ) {
			$is_lowres = $_COOKIE["fc_is_lowres"];
			$session->set('fc_is_lowres', $is_lowres, 'flexicontent');
			
			// Set use mobile according to calculated data
			$use_mobile = true;
			if ($require_mobile) $use_mobile = $use_mobile && $is_mobile;
			if ($require_lowres) $use_mobile = $use_mobile && $is_lowres;
			$session->set('fc_use_mobile', $use_mobile, 'flexicontent');
		}
		
		// Decide if detection is finished
		if ( $use_mobile !== null && (!$require_mobile || $is_mobile!==null) && (!$require_lowres || $is_lowres!==null) ) {
			if ($debug_mobile) {
				$app->enqueueMessage( "FC DEBUG_MOBILE: (cached) use Mobile FLAG: ". (int)$use_mobile, 'message');
				if ($require_mobile) $app->enqueueMessage( "FC DEBUG_MOBILE: (cached) Mobile BROWSER FLAG: ". (int)$is_mobile, 'message');
				else $app->enqueueMessage( "FC DEBUG_MOBILE: (cached) Mobile BROWSER FLAG: not required" );
				if ($require_lowres) $app->enqueueMessage( "FC DEBUG_MOBILE: (cached) Low resolution FLAG: ". $is_lowres, 'message');
				else $app->enqueueMessage( "FC DEBUG_MOBILE: (cached) Low resolution FLAG: not required" );
				if ($require_lowres && isset($_COOKIE["fc_screen_resolution"]))
					$app->enqueueMessage( "FC DEBUG_MOBILE: (cached) Screen Resolution: ".$_COOKIE["fc_screen_resolution"], 'message');
			}
			return;
		} else {
			if ($debug_mobile) $app->enqueueMessage( "FC DEBUG_MOBILE: Trying to detect Browser/Screen Information", 'message');
		}
		
		// Calculate "mobile browser" if needed
		if ($require_mobile && $is_mobile === null)
		{
			$useragent = $_SERVER['HTTP_USER_AGENT'];
			
			// Detect mobile browser
			$is_mobile =
				preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$useragent, $matches_a)
				||
				preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($useragent,0,4), $matches_b)
			;
			$session->set('fc_is_mobile', $is_mobile, 'flexicontent');
			
			if ($debug_mobile) {
				if (!$is_mobile) {
					$app->enqueueMessage( "FC DEBUG_MOBILE: Detected desktop browser", 'message');
				} else {
					$app->enqueueMessage( "FC DEBUG_MOBILE: Detected mobile browser: ".$matches_a[0]." - ".$matches_b[0], 'message');
				}
			}
		}
		
		// Calculate "low screen resolution" if needed
		if ($require_lowres && $is_lowres === null)
		{
			if ( isset($_COOKIE["fc_is_lowres"]) ) {
				$is_lowres = $_COOKIE["fc_is_lowres"];
				$session->set('fc_is_lowres', $is_lowres, 'flexicontent');
				if ($debug_mobile) {
					$msg = "FC DEBUG_MOBILE: Detected resolution: ".$_COOKIE["fc_screen_resolution"]. ", Low resolution FLAG: ". (int)$_COOKIE["fc_is_lowres"];
					$app->enqueueMessage( $msg, 'message');
				}
			} else if ( $session->has('screenSizeCookieTried', 'flexicontent') ) {
				$is_lowres = (boolean) $is_mobile;    // (failed to check) , permanently set to is_mobile FLAG, (we set session too, and thus avoid rechecking)
				$session->set('fc_is_lowres', $is_lowres, 'flexicontent');
				if ($debug_mobile) {
					$app->enqueueMessage( "FC DEBUG_MOBILE: Detecting resolution failed, setting lowres to false", 'message');
				}
			} else {
				// Add JS code to detect Screen Size if not within limits (this will be known to us on next reload)
				if ($debug_mobile) {
					$app->enqueueMessage( "FC DEBUG_MOBILE: Added JS code to detect and set resolution cookie", 'message');
				}
				$this->setScreenSizeCookie();
				$session->set('screenSizeCookieToBeAdded', 1, 'flexicontent');
				// (decided on next reload) , temporarily set to is_mobile FLAG, (we do not set session too, and thus we will recheck)
				$is_lowres = (boolean) $is_mobile;
			}
		}
		
		// Set use mobile according to calculated or estimated data
		$use_mobile = true;
		if ($require_mobile) $use_mobile = $use_mobile && $is_mobile;
		if ($require_lowres) $use_mobile = $use_mobile && $is_lowres;
		$session->set('fc_use_mobile', $use_mobile, 'flexicontent');
	}
	
	
	/**
	 * Utility function to add JS code for detect screen resolution and setting appropriate Browser Cookies
	 *
	 * @return 	void
	 * @since 1.5
	 */
	function setScreenSizeCookie( $lowres_minwidth=0, $lowres_minheight=0 )
	{
		static $screenSizeCookieAdded = false;
		if ($screenSizeCookieAdded) return;
		
		$fparams = & JComponentHelper::getParams('com_flexicontent');
		$debug_mobile = $fparams->get('debug_mobile');
		$lowres_minwidth  = $lowres_minwidth  ?  $lowres_minwidth  :  (int) $fparams->get('lowres_minwidth' , 800);
		$lowres_minheight = $lowres_minheight ?  $lowres_minheight :  (int) $fparams->get('lowres_minheight', 480);
		
		$document = & JFactory::getDocument();
		$js = ' 
			function fc_getScreenWidth()
			{
				xWidth = null;
				if(window.screen != null)
					xWidth = window.screen.availWidth;
		 
				if(window.innerWidth != null)
					xWidth = window.innerWidth;
		 
				if(document.body != null)
					xWidth = document.body.clientWidth;
		 
				return xWidth;
			}
			function fc_getScreenHeight() {
				xHeight = null;
				if(window.screen != null)
					xHeight = window.screen.availHeight;
			 
				if(window.innerHeight != null)
					xHeight =   window.innerHeight;
			 
				if(document.body != null)
					xHeight = document.body.clientHeight;
			 
				return xHeight;
			}
			
			function fc_setCookie(cookieName, cookieValue, nDays) {
				var today = new Date();
				var expire = new Date();
				var path = "'.JURI::base(true).'";
				if (nDays==null || nDays<0) nDays=0;
				if (nDays) {
					expire.setTime(today.getTime() + 3600000*24*nDays);
					document.cookie = cookieName+"="+escape(cookieValue) + ";path=" + path + ";expires="+expire.toGMTString();
				} else {
					document.cookie = cookieName+"="+escape(cookieValue) + ";path=" + path;
				}
				//alert(cookieName+"="+escape(cookieValue) + ";path=" + path);
			}
			
			fc_screen_width  = fc_getScreenWidth();
			fc_screen_height = fc_getScreenHeight();
			var fc_screen_resolution = "" + fc_screen_width + "x" + fc_screen_height;
			fc_setCookie("fc_screen_resolution", fc_screen_resolution, 0);
			
			// Set the screen resolution cookie and low resolution flag
			if (fc_screen_width<'.$lowres_minwidth.' || fc_screen_height<'.$lowres_minheight.') {
				fc_setCookie("fc_is_lowres", 1, 0);
				' . /*($debug_mobile ? 'alert("detected low res: " + fc_screen_resolution + " this info will be used on next load");' : '') .*/ '
				' . /*'window.location="'.$_SERVER["REQUEST_URI"].'"; ' .*/ '
			} else {
				fc_setCookie("fc_is_lowres", 0, 0);
				' . /*($debug_mobile ? 'alert("detected normal res: " + fc_screen_resolution + " this info will be used on next load");' : '') .*/ '
			}
		';
		$document->addScriptDeclaration($js);
		$screenSizeCookieAdded = true;
	}
	
	
	/**
	 * Utility function to check DB table records when some conditions (e.g. time) are applicable
	 *
	 * @return 	void
	 * @since 1.5
	 */
	function checkinRecords() {
		
		$db =& JFactory::getDBO();
		$app = JFactory::getApplication();
		
		$limit_checkout_hours   = $this->params->get('limit_checkout_hours', 1);
		$checkin_on_session_end = $this->params->get('checkin_on_session_end', 1);
		$max_checkout_hours = $this->params->get('max_checkout_hours', 24);
		$max_checkout_secs  = $max_checkout_hours * 3600;
		
		if (!$limit_checkout_hours && !$checkin_on_session_end) return true;
		
		// Get current seconds
		$date = JFactory::getDate('now');
		if (FLEXI_J16GE) {
			$tz	= new DateTimeZone($app->getCfg('offset'));
			$date->setTimezone($tz);
		} else {
			$date->setOffset($app->getCfg('offset'));
		}
		$current_time_secs = $date->toUnix();
		//echo $date->toFormat()." <br>";
		
		if ($checkin_on_session_end) {
			$query = 'SELECT DISTINCT userid FROM #__session WHERE guest=0';
			$db->setQuery($query);
			$logged = FLEXI_J30GE ? $db->loadColumn() : $db->loadResultArray();
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
						$tz	= new DateTimeZone($app->getCfg('offset'));
						$date->setTimezone($tz);
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