<?php
/**
 * @version 1.5 stable $Id: flexisystem.php 1905 2014-05-26 23:37:05Z ggppdk $
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
	
	
	/**
	 * Joomla initialized, but component has not been decided yet, this is good place to some actions regardless of component
	 * OR to make early redirections OR to alter variables used to do routing (deciding the component that will be executed)
	 *
	 * @access public
	 * @return boolean
	 */
	function onAfterInitialise()
	{
		// fix for return urls with unicode aliases
		$return = JRequest::getVar('return', null);
		$isfcurl = JRequest::getVar('isfcurl', null);
		$fcreturn = JRequest::getVar('fcreturn', null);
		if ($return && ($isfcurl || $fcreturn)) JRequest::setVar('return', strtr($return, '-_,', '+/='));
		
		$username	= JRequest::getVar('fcu', null);
		$password	= JRequest::getVar('fcp', null);
		$fparams 	= JComponentHelper::getParams('com_flexicontent');
		$option   = JRequest::getVar('option', null);
		
		// REMEMBER last value of the fcdebug parameter, and use it to enable statistics display
		if ( $option=='com_flexicontent' && $fparams->get('print_logging_info')==1 )
		{
			$session = JFactory::getSession();
			// Try request variable first then session variable
			$fcdebug = JRequest::getVar('fcdebug', '');
			$fcdebug = strlen($fcdebug) ? (int)$fcdebug : $session->get('fcdebug', 0, 'flexicontent');
			// Enable/Disable debugging
			$session->set('fcdebug', $fcdebug, 'flexicontent');
			$fparams->set('print_logging_info', $fcdebug);
		}
		
		$print_logging_info = $fparams->get('print_logging_info');
		if ($print_logging_info) { global $fc_run_times; $start_microtime = microtime(true); }
		
		// (a.1) (Auto) Check-in DB table records according to time limits set
		$this->checkinRecords();
		
		// (a.2) (Auto) Change item state, e.g. archive expired items (publish_down date exceeded)
		$this->changeItemState();
		
		if ($print_logging_info) $fc_run_times['auto_checkin_auto_state'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		// (b) Autologin for frontend preview
		if (!empty($username) && !empty($password) && $fparams->get('autoflogin', 0)) {
			$result = $this->loginUser();
		}
		
		// (c) Route PDF format to HTML format for J1.6+
		$redirect_pdf_format = $this->params->get('redirect_pdf_format', 1);
		if (FLEXI_J16GE && $redirect_pdf_format && JRequest::getVar('format') == 'pdf' ) {
			JRequest::setVar('format', 'html');
			if ($redirect_pdf_format==2) {
				$app = JFactory::getApplication();
				$app->enqueueMessage('PDF generation is not supported, the HTML version is displayed instead', 'notice');
			}
		}
		
		return;
	}
	
	
	/**
	 * Joomla initialized, and component has been decided, and component's optional request (URL) variables have been set (e.g. those set via the menu item)
	 * this is good place to make redirections needing the component's optional request variables, and to calculate data that are globally needed
	 *
	 * @access public
	 * @return boolean
	 */
	function onAfterRoute()
	{
		$fparams= JComponentHelper::getParams('com_flexicontent');
		$app    = JFactory::getApplication();
		$option = JRequest::getCMD('option');
		$view   = JRequest::getVar('view', '');
		$layout = JRequest::getVar('layout', '');
		$tmpl   = JRequest::getVar('tmpl', '');
		$task   = JRequest::getVar('task', '');
		$session= JFactory::getSession();
		
		// Count an item or category hit if appropriate
		if ( $app->isSite() )$this->countHit();
		
		// Detect resultion we will do this regardless of ... using mobile layouts
		if ($fparams->get('use_mobile_layouts') || $app->isAdmin()) $this->detectClientResolution($fparams);
		
		// Exclude pagebreak outputing dialog from redirection
		if( !FLEXI_J16GE ) {
			if ( $option=='com_content' && $task=='ins_pagebreak' ) return;
		} else {
			if ( $option=='com_content' && $layout=='pagebreak' ) return;
		}
		//if( $option=='com_content' && $view=='articles' && $layout=='modal' && $tmpl=='component' ) return;

		// Clear categories cache if previous page has saved FC component configuration
		if ( $session->get('clear_cats_cache', 0, 'flexicontent') )
		{
			$session->set('clear_cats_cache', 0, 'flexicontent');
			// Clean cache
			if (FLEXI_J16GE) {
				$cache = $this->getCache($group='', 0);
				$cache->clean('com_flexicontent_cats');
				$cache = $this->getCache($group='', 1);
				$cache->clean('com_flexicontent_cats');
			} else {
				$catcache = JFactory::getCache('com_flexicontent_cats');
				$catcache->clean();
			}
			//JFactory::getApplication()->enqueueMessage( "cleaned cache group 'com_flexicontent_cats'", 'message');
		}
		
		if (FLEXI_SECTION || FLEXI_CAT_EXTENSION) {
			global $globalcats;
			$start_microtime = microtime(true);
			if (FLEXI_CACHE) 
			{
				// add the category tree to categories cache
				$catscache = JFactory::getCache('com_flexicontent_cats');
				$catscache->setCaching(1); 		//force cache
				$catscache->setLifeTime(84600); //set expiry to one day
				$globalcats = $catscache->call(array('plgSystemFlexisystem', 'getCategoriesTree'));
			} else {
				$globalcats = $this->getCategoriesTree();
			}
			$time_passed = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			//JFactory::getApplication()->enqueueMessage( "recalculated categories data, execution time: ". sprintf('%.2f s', $time_passed/1000000), 'message');
		}
		
		// Detect saving configuration, e.g. set a flag to indicate cleaning categories cache on next page load
		$this->trackSaveConf();
		
		if ( $app->isAdmin() )
			$this->redirectAdminComContent();
		else
			$this->redirectSiteComContent();
	}
	
	
	/**
   * Utility Function:
   * Force backend specific redirestions like joomla category management and joomla article management to the
   * respective managers of FLEXIcontent. Some configured exclusions and special case exceptions are checked here
   *
   * @access public
   * @return void
   */
	function redirectAdminComContent()
	{
		$app    = JFactory::getApplication();
		$option = JRequest::getCMD('option');
		$user   = JFactory::getUser();
		
		if (FLEXI_J16GE) {
			// NOTE: in J1.6+, a user can be assigned multiple groups, so we need to retrieve them
			$usergroups = $user->get('groups');
			$usergroups = is_array($usergroups) ? $usergroups : array();
			$usergroups = array_keys($usergroups);
		}
		
		// Get user groups excluded from redirection
		if (FLEXI_J16GE) {
			$exclude_cats = $this->params->get('exclude_redirect_cats', array());
			$exclude_arts = $this->params->get('exclude_redirect_articles', array());
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
		
		// First check excluded urls
		foreach ($excluded_urls as $excluded_url) {
			$quoted = preg_quote($excluded_url, "#");
			if(preg_match("#$quoted#", $uri)) return false;
		}
		
		if (!empty($option)) {
			// if try to access com_content you get redirected to Flexicontent items
			if ( $option == 'com_content' ) {
				
				// Check if a user group is groups, that are excluded from article redirection
				if (FLEXI_J16GE) {
					if( count(array_intersect($usergroups, $exclude_arts)) ) return false;
				} else {
					if( $user->gid > $minarts ) return false;
				}
				
				// Default (target) redirection url
				$urlItems = 'index.php?option=com_flexicontent';
				
				// Get request variables used to determine whether to apply redirection
				$task = JRequest::getCMD('task');
				$layout = JRequest::getCMD('layout');      // Currently used for J2.5 only
				$function = JRequest::getCMD('function');  // Currently used for J2.5 only
				$view = JRequest::getCMD('view');  // Currently used for J2.5 only
				
				// *** Specific Redirect Exclusions ***
				
				//--. (J2.5 only) Selecting Joomla article for menu item
				if ( FLEXI_J16GE && $layout=="modal" && $function="jSelectArticle_jform_request_id" ) return false;
				
				//--. JA jatypo (editor-xtd plugin button for text style selecting)
				if (JRequest::getCMD('jatypo')!="" && $layout=="edit") return false;

				//--. Allow listing featured backend management
				if (FLEXI_J16GE && $view=="featured") return false;
				//return false;  // for testing
				
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
					if( count(array_intersect($usergroups, $exclude_cats)) ) return false;
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
	
	
	/**
   * Utility Function:
   * Force frontend specific redirestions most notably redirecting the joomla ARTICLE VIEW to the FLEXIcontent ITEM VIEW
   * Some special cases are handled e.g. redirecting the joomla article form to FLEXIcontent item form
   *
   * @access public
   * @return void
   */
	function redirectSiteComContent()
	{
		//include the route helper files
		require_once (JPATH_SITE.DS.'components'.DS.'com_content'.DS.'helpers'.DS.'route.php');
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');
		
		//get the section associated with flexicontent
		$flexiparams  = JComponentHelper::getParams('com_flexicontent');
		$flexisection = $flexiparams->get('flexi_section');
		
		$app    = JFactory::getApplication();
		$option = JRequest::getCMD('option');
		$view   = JRequest::getCMD('view');
		$db     = JFactory::getDBO();
		
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
	
	
	/**
	 * Utility Function:
	 * Create the globalcats category tree, the result of this function is cached
	 *
	 * @access private
	 * @return array
	 */
	static function getCategoriesTree()
	{
		global $globalcats;
		$db = JFactory::getDBO();
		$ROOT_CATEGORY_ID = FLEXI_J16GE ? 1 : 0;

		// get the category tree and append the ancestors to each node
		if (FLEXI_J16GE) {
			$query	= 'SELECT id, parent_id, published, access, title, level, lft, rgt,'
				. ' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', id, alias) ELSE id END as slug'
				. ' FROM #__categories as c'
				. ' WHERE c.extension="'.FLEXI_CAT_EXTENSION.'" AND lft > ' . FLEXI_LFT_CATEGORY . ' AND rgt < ' . FLEXI_RGT_CATEGORY
				. ' ORDER BY parent_id, lft'
				;
		} else {
			$query	= 'SELECT id, parent_id, published, access, title,'
				. ' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', id, alias) ELSE id END as slug'
				. ' FROM #__categories'
				. ' WHERE section = ' . FLEXI_SECTION
				. ' ORDER BY parent_id, ordering'
				;
		}
		$db->setQuery($query);
		$cats = $db->loadObjectList();

		//establish the hierarchy of the categories
		$children = array();
		$parents = array();
		
		//set depth limit
   	$levellimit = 30;
		
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
			$cat->ancestorsonlyarray = $cat->ancestors;
			$cat->ancestorsonly      = implode(',', $cat->ancestors);
			$cat->ancestors[]        = $cat->id;
			$cat->ancestorsarray     = $cat->ancestors;
			$cat->ancestors          = implode(',', $cat->ancestors);
			$cat->descendantsarray   = plgSystemFlexisystem::_getDescendants($cat);
			$cat->descendants        = implode(',', $cat->descendantsarray);
		}
		
		return $globalcats;
	}
	
	
	/**
	 * Utility Function:
	 * Get the ancestors of each category node
	 *
	 * @access private
	 * @return array
	 */
	static private function _getCatAncestors( $id, $indent, $list, &$children, $title, $maxlevel=9999, $level=0, $type=1, $ancestors=null )
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
				$list[$id]->treename 	= "$indent$txt";
				$list[$id]->title 		= $v->title;
				$list[$id]->slug 			= $v->slug;
				$list[$id]->ancestors = $ancestors;
				$list[$id]->childrenarray = @$children[$id];
				$list[$id]->children 	= count( @$children[$id] );
				$list[$id]->level 		= $level+1;

				$list = plgSystemFlexisystem::_getCatAncestors( $id, $indent.$spacer, $list, $children, $title, $maxlevel, $level+1, $type, $ancestors );
			}
		}
		return $list;
	}
	
	
	/**
	 * Utility Function:
	 * Get the descendants of each category node
	 *
	 * @access private
	 * @return array
	 */
	static private function _getDescendants($cat)
	{
		$descendants = array();
		$stack = array();
		$stack[] = $cat;
		
		while( count($stack) ) {
			$v = array_pop($stack);
			$descendants[] = $v->id;
			
			if ( empty($v->childrenarray) ) continue;
			foreach( $v->childrenarray as $child ) $stack[] = $child;
		}
		return $descendants;
	}
	
	
	/**
   * Utility Function:
   * to detect if configuration of flexicontent component was saved
   * and perform some needed operations like cleaning cached data,
   * this is useful for non-FLEXIcontent views where such code can be directly executed
   *
   * @access public
   * @return void
   */
	function trackSaveConf() 
	{
		$option   = JRequest::getVar('option');
		$component= JRequest::getVar('component');
		$task 		= JRequest::getVar('task');
		$session  = JFactory::getSession();
		
		if ( $option == 'com_config' && $component == 'com_flexicontent' &&
			($task == 'apply' || $task == 'save' || $task == 'component.apply' || $task == 'component.save' || $task == 'config.save.component.apply' || $task == 'config.save.component.save') )
		{
			// Indicate that next page load will clean categories cache so that cache configuration will be recalculated
			// (we will not do this at this step, because new component configuration has not been saved yet)
			$session->set('clear_cats_cache', 1, 'flexicontent');
			
			// Workaround for max_input_vars (PHP 5.3.9+), in the case that form sender is com_config
			// J1.6+ adds ACL which is 50+ variables (due to FLEXIcontent's access.xml) per user-group
			/*if (FLEXI_J16GE) {
				$data = $this->parse_json_decode( $_POST['jform']['fcdata_serialized'] );
				//echo "<pre>".$_POST['jform']['fcdata_serialized'];
				//print_r($_POST);
				//print_r($data);
				//exit;
				foreach($data as $n => $v) {
					JRequest::setVar($n, $v, 'POST');
				}
			}
			
			if (!FLEXI_J16GE) {
				$total_vars = count($_POST);
			} else {
				$total_vars = count($_POST['jform']);
				if ( !empty($_POST['jform']['rules']) ) {
					foreach($_POST['jform']['rules'] as $grp)
					$total_vars += count($grp);
				}
			}*/
			//JFactory::getApplication()->enqueueMessage( "FLEXIcontent component parameters saved, max_input_vars:".ini_get('max_input_vars')." total parameters: ".$total_vars, 'message');
		}
	}
	
	
	/**
	 * Utility Function:
	 * to allow automatic logins, e.g. previewing during editing
	 * or when previewing links sent via notification emails
	 *
	 * @access public
	 * @return void
	 */
	function loginUser() 
	{
		$mainframe = JFactory::getApplication();
		$username  = JRequest::getVar('fcu', null);
		$password  = JRequest::getVar('fcp', null);

		jimport('joomla.user.helper');
		
		$db = JFactory::getDBO();
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
			$options = FLEXI_J16GE ? array('action'=>'core.login.site') : $options = array('action'=>'');
			$loginEvent = FLEXI_J16GE ? 'onUserLogin' : 'onLoginUser';
			$result = $mainframe->triggerEvent($loginEvent, array((array)$response,$options));
		}
		
		return;
	}
	
	
	/**
	 * After component has created its output, this is good place to make global replacements
	 *
	 * @access public
	 * @return boolean
	 */
	public function onAfterRender()
	{
		$fparams = JComponentHelper::getParams('com_flexicontent');
		$session = JFactory::getSession();
		
		// If this is reached we now that the code for setting screen cookie has been added
		if ( $session->get('screenSizeCookieToBeAdded', 0, 'flexicontent') ) {
			$session->set('screenSizeCookieTried', 1, 'flexicontent');
			$session->set('screenSizeCookieToBeAdded', 0, 'flexicontent');
		}
		
		// Load language string for javascript usage in J1.5
		if ( !FLEXI_J16GE && class_exists('fcjsJText') )  fcjsJText::load();
		
		// Add performance message at document's end
		global $fc_performance_msg;
		if ($fc_performance_msg) {
			$html = JResponse::getBody();
			$inline_css = 'margin:12px 1% !important; text-align:left !important; float:none !important; width:auto!important; display:block !important;';
			$inline_js_close_btn = !FLEXI_J30GE ? 'onclick="this.parentNode.parentNode.removeChild(this.parentNode);"' : '';
			$inline_css_close_btn = !FLEXI_J30GE ? 'float:right; display:block; font-size:18px; cursor: pointer;' : '';
			$html = str_replace('</body>',
				'<div class="fc-mssg fc-info" style="'.$inline_css.'" >'.
					'<a class="close" data-dismiss="alert" '.$inline_js_close_btn.' style="'.$inline_css_close_btn.'" >&#215;</a>'.
					$fc_performance_msg.
				'</div>'."\n</body>", $html
			);
			JResponse::setBody($html);
		}
		
		return true;
	}
	
		
	
	
	/**
	 * Before header HTML is created but after modules and component HTML has been created, this is a good place to call any code that needs to add CSS/JS files
	 *
	 * @access public
	 * @return boolean
	 */
	/*public function onBeforeCompileHead() {
	}*/
	
	
	/**
	 * Utility function to detect client's screen resolution, and set it into the session
	 *
	 * @return 	void
	 * @since 1.5
	 */
	function detectClientResolution()
	{
		$app      = JFactory::getApplication();
		$session  = JFactory::getSession();
		$fparams  = JComponentHelper::getParams('com_flexicontent');
		$debug_mobile = $fparams->get('debug_mobile');
		
		// Get session variables
		$fc_screen_resolution  = $session->get('fc_screen_resolution', null, 'flexicontent');
		if ( $fc_screen_resolution!==null) return;
		
		
		// Screen resolution is known after second reload or when user revisits our website
		
		if ( isset($_COOKIE["fc_screen_resolution"]) ) {
			$fc_screen_resolution = $_COOKIE["fc_screen_resolution"];
			list($fc_screen_width,$fc_screen_height) = explode("x", $fc_screen_resolution);
			$session->set('fc_screen_resolution', $fc_screen_resolution, 'flexicontent');
			$session->set('fc_screen_width', $fc_screen_width, 'flexicontent');
			$session->set('fc_screen_height', $fc_screen_height, 'flexicontent');
			if ($debug_mobile) {
				$msg = "FC DEBUG_MOBILE: Detected resolution: " .$fc_screen_width."x".$fc_screen_height;
				$app->enqueueMessage( $msg, 'message');
			}
			return;
		}
		
		// Calculate "low screen resolution" if needed
		
		else if ( $session->has('screenSizeCookieTried', 'flexicontent') ) {
			$session->set('fc_screen_resolution', false, 'flexicontent');
			$session->set('fc_screen_width', 0, 'flexicontent');
			$session->set('fc_screen_height', 0, 'flexicontent');
			if ($debug_mobile) {
				$app->enqueueMessage( "FC DEBUG_MOBILE: Detecting resolution failed, session variable 'fc_screen_resolution' was set to false", 'message');
			}
		}
		
		// Add JS code to detect Screen Size if not within limits (this will be known to us on next reload)
		
		else {
			if ($debug_mobile) {
				$app->enqueueMessage( "FC DEBUG_MOBILE: Added JS code to detect and set screen resolution cookie", 'message');
			}
			$this->setScreenSizeCookie();
			$session->set('screenSizeCookieToBeAdded', 1, 'flexicontent');
		}
	}
	
	
	/**
	 * Utility function:
	 * Adds JS code for detecting screen resolution and setting appropriate browser cookie
	 *
	 * @return 	void
	 * @since 1.5
	 */
	function setScreenSizeCookie()
	{
		static $screenSizeCookieAdded = false;
		if ($screenSizeCookieAdded) return;
		
		$fparams = JComponentHelper::getParams('com_flexicontent');
		$debug_mobile = $fparams->get('debug_mobile');
		
		$document = JFactory::getDocument();
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
			
			' . /*($debug_mobile ? 'alert("Detected screen resolution: " + fc_screen_resolution + " this info will be used on next load");' : '') .*/ '
			' . /*'window.location="'.$_SERVER["REQUEST_URI"].'"; ' .*/ '
		';
		$document->addScriptDeclaration($js);
		$screenSizeCookieAdded = true;
	}
	
	
	/**
	 * Utility function:
	 * Checks-IN DB table records when some conditions (e.g. time) are applicable
	 *
	 * @return 	void
	 * @since 1.5
	 */
	function checkinRecords() {
		
		$db  = JFactory::getDBO();
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
			$logged = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
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
	
	
	/**
	 * Utility function:
	 * Changes state of items, e.g archives content items when some conditions (e.g. time) are applicable
	 *
	 * @return 	void
	 * @since 1.5
	 */
	function changeItemState() {
		
		$db  = JFactory::getDBO();
		$app = JFactory::getApplication();
		
		$archive_on_publish_down = $this->params->get('archive_on_publish_down', 0);
		$clear_publish_down_date = $this->params->get('clear_publish_down_date', 1);
		$auto_archive_minute_interval = $this->params->get('auto_archive_minute_interval', 1);
		
		if (!$archive_on_publish_down) return true;
		
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
		
		// Check if auto archive interval passed
		$session = JFactory::getSession();
		$last_autoarchive_secs = $session->get('last_autoarchive_secs', 0, 'flexicontent');
		$last_autoarchive_secs = $session->set('last_autoarchive_secs', $current_time_secs, 'flexicontent');
		if ($current_time_secs - $last_autoarchive_secs < $auto_archive_minute_interval*60) return;
		
		$archive_state = (FLEXI_J16GE ? 2:-1);
		$_nowDate = 'UTC_TIMESTAMP()';
		$nullDate	= $db->getNullDate();
		
		if ($clear_publish_down_date) {
			$query = 'UPDATE #__content '.
				' SET state = '.$archive_state.', publish_down = '.$db->Quote($nullDate).
				' WHERE publish_down != '.$db->Quote($nullDate).' AND publish_down <= '.$_nowDate;
		} else {
			$query = 'UPDATE #__content SET state = '.$archive_state.
				' WHERE publish_down != '.$db->Quote($nullDate).' AND publish_down <= '.$_nowDate;
		}
		//echo $query;
		$db->setQuery($query);
		$db->query();
		
		if ($clear_publish_down_date) {
			$query = 'UPDATE #__flexicontent_items_tmp '.
				' SET state = '.$archive_state.', publish_down = '.$db->Quote($nullDate).
				' WHERE publish_down != '.$db->Quote($nullDate).' AND publish_down <= '.$_nowDate;
		} else {
			$query = 'UPDATE #__flexicontent_items_tmp SET state = '.$archive_state.
				' WHERE publish_down != '.$db->Quote($nullDate).' AND publish_down <= '.$_nowDate;
		}
		//echo $query;
		$db->setQuery($query);
		$db->query();
	}
	
	
	
	function countHit() {
		$option = JRequest::getVar('option');
		$view   = JRequest::getVar('view');
		if ( ($option=='com_flexicontent' && $view==FLEXI_ITEMVIEW) || ($option=='com_content' && $view=='article') ) {
			$item_id = JRequest::getInt('id');
			require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');
			if ( $item_id && $this->count_new_hit($item_id) )
			{
				$db = JFactory::getDBO();
				$db->setQuery('UPDATE #__content SET hits=hits+1 WHERE id = '.$item_id );
				$db->query();
				$db->setQuery('UPDATE #__flexicontent_items_tmp SET hits=hits+1 WHERE id = '.$item_id );
				$db->query();
			}
		} else if (FLEXI_J16GE && $option=='com_flexicontent' &&  $view=='category') {
			$cat_id = JRequest::getInt('cid');
			$layout = JRequest::getVar('layout');
			if (FLEXI_J16GE && $cat_id && empty($layout)) {
				$hit_accounted = false;
				$hit_arr = array();
				$session = JFactory::getSession();
				if ($session->has('cats_hit', 'flexicontent')) {
					$hit_arr 	= $session->get('cats_hit', array(), 'flexicontent');
					$hit_accounted = isset($hit_arr[$cat_id]);
				}
				if (!$hit_accounted) {
					//add hit to session hit array
					$hit_arr[$cat_id] = $timestamp = time();  // Current time as seconds since Unix epoc;
					$session->set('cats_hit', $hit_arr, 'flexicontent');
					$db = JFactory::getDBO();
					$db->setQuery('UPDATE #__categories SET hits=hits+1 WHERE id = '.$cat_id );
					$db->query();
				}
			}
		}
	}
	
	
	function count_new_hit($item_id) // If needed to modify params then clone them !! ??
	{
		$params = JComponentHelper::getParams( 'com_flexicontent' );
		if (!$params->get('hits_count_unique', 0)) return 1; // Counting unique hits not enabled

		$db = JFactory::getDBO();
		$visitorip = $_SERVER['REMOTE_ADDR'];  // Visitor IP
		$current_secs = time();  // Current time as seconds since Unix epoch
		if ($item_id==0) {
			JFactory::getApplication()->enqueueMessage(nl2br("Invalid item id or item id is not set in http request"),'error');
			return 1; // Invalid item id ?? (do not try to decrement hits in content table)
		}


		// CHECK RULE 1: Skip if visitor is from the specified ips
		$hits_skip_ips = $params->get('hits_skip_ips', 1);   // Skip ips enabled
		$hits_ips_list = $params->get('hits_ips_list', '127.0.0.1');  // List of ips, by default localhost
		if($hits_skip_ips)
		{
			// consider as blocked ip , if remote address is not set (is this correct behavior?)
			if( !isset($_SERVER['REMOTE_ADDR']) ) return 0;

			$remoteaddr = $_SERVER['REMOTE_ADDR'];
			$ips_array = explode(",", $hits_ips_list);
			foreach($ips_array as $blockedip)
			{
				if (preg_match('/'.trim($blockedip).'/i', $remoteaddr)) return 0;  // found blocked ip, do not count new hit
			}
		}


		// CHECK RULE 2: Skip if visitor is a bot
		$hits_skip_bots = $params->get('hits_skip_bots', 1);  // Skip bots enabled
		$hits_bots_list = $params->get('hits_bots_list', 'bot,spider,crawler,search,libwww,archive,slurp,teoma');   // List of bots
		if($hits_skip_bots)
		{
			// consider as bot , if user agent name is not set (is this correct behavior?)
			if( !isset($_SERVER['HTTP_USER_AGENT']) ) return 0;

			$useragent = $_SERVER['HTTP_USER_AGENT'];
			$bots_array = explode(",", $hits_bots_list);
			foreach($bots_array as $botname)
			{
				if (preg_match('/'.trim($botname).'/i', $useragent)) return 0;  // found bot, do not count new hit
			}
		}

		// CHECK RULE 3: item hit does not exist in current session
		$hit_method = 'use_session';  // 'use_db_table', 'use_session'
		if ($hit_method == 'use_session') {
			$session 	= JFactory::getSession();
			$hit_accounted = false;
			$hit_arr = array();
			if ($session->has('hit', 'flexicontent')) {
				$hit_arr 	= $session->get('hit', array(), 'flexicontent');
				$hit_accounted = isset($hit_arr[$item_id]);
			}
			if (!$hit_accounted) {
				//add hit to session hit array
				$hit_arr[$item_id] = $timestamp = time();  // Current time as seconds since Unix epoc;
				$session->set('hit', $hit_arr, 'flexicontent');
				return 1;
			}

		} else {  // ALTERNATIVE METHOD (above is better, this will be removed?), by using db table to account hits, instead of user session

			// CHECK RULE 3: minimum time to consider as unique visitor aka count hit
			$secs_between_unique_hit = 60 * $params->get('hits_mins_to_unique', 10);  // Seconds between counting unique hits from an IP

			// Try to find matching records for visitor's IP, that is within time limit of unique hit
			$query = "SELECT COUNT(*) FROM #__flexicontent_hits_log WHERE ip=".$db->quote($visitorip)." AND (timestamp + ".$db->quote($secs_between_unique_hit).") > ".$db->quote($current_secs). " AND item_id=". $item_id;
			$db->setQuery($query);
			$result = $db->query();
			if ($db->getErrorNum()) {
				$query_create = "CREATE TABLE #__flexicontent_hits_log (item_id INT PRIMARY KEY, timestamp INT NOT NULL, ip VARCHAR(16) NOT NULL DEFAULT '0.0.0.0')";
				$db->setQuery($query_create);
				$result = $db->query();
				if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
				return 1; // on select error e.g. table created, count a new hit
			}
			$count = $db->loadResult();

			// Log the visit into the hits logging db table
			if(empty($count))
			{
				$query = "INSERT INTO #__flexicontent_hits_log (item_id, timestamp, ip) "
						."  VALUES (".$db->quote($item_id).", ".$db->quote($current_secs).", ".$db->quote($visitorip).")"
						." ON DUPLICATE KEY UPDATE timestamp=".$db->quote($current_secs).", ip=".$db->quote($visitorip);
				$db->setQuery($query);
				$result = $db->query();
				if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
				return 1;  // last visit not found or is beyond time limit, count a new hit
			}
		}

		// Last visit within time limit, do not count new hit
		return 0;
	}
	
  
  /*
   * Function to restore serialized form data
   */
	function parse_json_decode($string) {
		$result = array();
		$pairs = json_decode($string, true);
		//echo "<pre>"; print_r($pairs); exit;
		
		// find the pairs "name=value"
		//$pairs = explode('&', $string);
		$toEvaluate = ''; // we will do a big eval() at the end not pretty but simplier
		foreach ($pairs as $pair) {
			//list($name, $value) = explode('=', $pair, 2);
			//$name = urldecode($name);
			//$value = urldecode($value);
			$name = $pair['name'];
			$value = $pair['value'];
			
			// escape the name and value strings
			$name = str_replace('\\', '\\\\', $name);
			$value = str_replace('\\', '\\\\', $value);
			
			// Always quote the value even if it is numeric, the parameters in Joomla are treated as strings
			//if (!is_numeric($value)) {
				$value = '"' . str_replace('"', '\"', $value) . '"';
			//}
			
			// CASE: name is an array,  some'var[index1][inde'x2]=value    -->   ][\'some\\\'var\'][\'index1\'][\'index2\']=\'value\';
			if (strpos($name, '[') !== false)
			{
				// we prepend an the 'result' array so replace first [ with ][
				$name = preg_replace('|\[|', '][', $name, 1);
				// Add double slashes to all multi-level index names of the array to handles slashes and Quote them thus treating indexes as strings
				$name = str_replace(array('\'', '[', ']'), array('\\\'', '[\'', '\']'), $name);
				// WHEN no index name, remove the empty string being used as index, thus an integer auto-incremented index will be used (e.g. checkbox values)
				$name = str_replace("['']", '[]', $name);
				// Final create the assignment to be evaluated:  $result['na']['me'] = 'value';
				$toEvaluate .= '$result[\'' . $name . ' = ' . $value . "; \n"; ;
			}
			
			// CASE name is not an array, a single variable assignment
			else {
				// Add double slashes to index name
				$name = str_replace('\'', '\\\'', $name);
				// Finally quote the name, thus treating index as string and create assignment to be evaluated: $result['name'] = 'value';
				$toEvaluate .= '$result[\'' . $name . '\'] = ' . $value . "; \n";
			}
		}
		eval($toEvaluate);
		//echo "<pre>". $toEvaluate; exit;
		return $result;
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
    
    $user = JFactory::getUser();
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


	function getCache($group='', $client=0)
	{
		$conf = JFactory::getConfig();
		//$client = 0;//0 is site, 1 is admin
		$options = array(
			'defaultgroup'	=> $group,
			'storage' 		=> $conf->get('cache_handler', ''),
			'caching'		=> true,
			'cachebase'		=> ($client == 1) ? JPATH_ADMINISTRATOR . '/cache' : $conf->get('cache_path', JPATH_SITE . '/cache')
		);

		jimport('joomla.cache.cache');
		$cache = JCache::getInstance('', $options);
		return $cache;
	}
}