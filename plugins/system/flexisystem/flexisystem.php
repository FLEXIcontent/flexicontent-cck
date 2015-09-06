<?php
/**
 * @version 1.5 stable $Id$
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
	var $extension_name;  // Component name
	var $cparams;   // Component parameters
	
	/**
	 * Constructor
	 */
	function plgSystemFlexisystem( &$subject, $config )
	{
		parent::__construct( $subject, $config );
		$this->extension = 'com_flexicontent';
		$this->cparams  = JComponentHelper::getParams($this->extension);
		
		//JPlugin::loadLanguage($extension_name, JPATH_SITE);
		JFactory::getLanguage()->load($this->extension, JPATH_SITE, 'en-GB'	, true);
		JFactory::getLanguage()->load($this->extension, JPATH_SITE, null		, true);
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
		if (JFactory::getApplication()->isAdmin()) $this->handleSerialized();
		
		// fix for return urls with unicode aliases
		$return = JRequest::getVar('return', null);
		$isfcurl = JRequest::getVar('isfcurl', null);
		$fcreturn = JRequest::getVar('fcreturn', null);
		if ($return && ($isfcurl || $fcreturn)) JRequest::setVar('return', strtr($return, '-_,', '+/='));
		
		$username	= JRequest::getVar('fcu', null);
		$password	= JRequest::getVar('fcp', null);
		$option   = JRequest::getVar('option', null);
		$session = JFactory::getSession();
		
		
		// Clear categories cache if previous page has saved FC component configuration
		if ( $session->get('clear_cats_cache', 0, 'flexicontent') )
		{
			$session->set('clear_cats_cache', 0, 'flexicontent');
			// Clean cache
			$cache = $this->getCache($group='', 0);
			$cache->clean('com_flexicontent_cats');
			$cache = $this->getCache($group='', 1);
			$cache->clean('com_flexicontent_cats');
			//JFactory::getApplication()->enqueueMessage( "cleaned cache group 'com_flexicontent_cats'", 'message');
		}
		
		if (FLEXI_SECTION || FLEXI_CAT_EXTENSION) {
			global $globalcats;
			$start_microtime = microtime(true);
			if (FLEXI_CACHE) 
			{
				// add the category tree to categories cache
				$catscache = JFactory::getCache('com_flexicontent_cats');
				$catscache->setCaching(1);                  // Force cache ON
				$catscache->setLifeTime(FLEXI_CACHE_TIME);  // Set expire time (default is 1 hour)
				$globalcats = $catscache->call(array('plgSystemFlexisystem', 'getCategoriesTree'));
			} else {
				$globalcats = $this->getCategoriesTree();
			}
			$time_passed = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			//JFactory::getApplication()->enqueueMessage( "recalculated categories data, execution time: ". sprintf('%.2f s', $time_passed/1000000), 'message');
		}
		
		// REMEMBER last value of the fcdebug parameter, and use it to enable statistics display
		if ( $option==$this->extension && $this->cparams->get('print_logging_info')==1 )
		{
			// Try request variable first then session variable
			$fcdebug = JRequest::getVar('fcdebug', '');
			$fcdebug = strlen($fcdebug) ? (int)$fcdebug : $session->get('fcdebug', 0, 'flexicontent');
			// Enable/Disable debugging
			$session->set('fcdebug', $fcdebug, 'flexicontent');
			$this->cparams->set('print_logging_info', $fcdebug);
		}
		
		$print_logging_info = $this->cparams->get('print_logging_info');
		if ($print_logging_info) { global $fc_run_times; $start_microtime = microtime(true); }
		
		// (a.1) (Auto) Check-in DB table records according to time limits set
		$this->checkinRecords();
		
		// (a.2) (Auto) Change item state, e.g. archive expired items (publish_down date exceeded)
		$this->changeItemState();
		
		if ($print_logging_info) $fc_run_times['auto_checkin_auto_state'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		// (b) Autologin for frontend preview
		if (!empty($username) && !empty($password) && $this->cparams->get('autoflogin', 0)) {
			$result = $this->loginUser();
		}
		
		// (c) Route PDF format to HTML format for J1.6+
		$redirect_pdf_format = $this->params->get('redirect_pdf_format', 1);
		if ($redirect_pdf_format && JRequest::getVar('format') == 'pdf' ) {
			JRequest::setVar('format', 'html');
			if ($redirect_pdf_format==2) {
				JFactory::getApplication()->enqueueMessage('PDF generation is not supported, the HTML version is displayed instead', 'notice');
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
		$jinput   = JFactory::getApplication()->input;
		$app      = JFactory::getApplication();
		$session  = JFactory::getSession();
		$document = JFactory::getDocument();
		
		$option = $jinput->get('option', '', 'cmd');
		$view   = $jinput->get('view', '', 'cmd');
		$controller = $jinput->get('controller', '', 'cmd');
		$component  = $jinput->get('component', '', 'cmd');
		
		$layout = $jinput->get('layout', '', 'string');
		$tmpl   = $jinput->get('tmpl', '', 'string');
		$task   = $jinput->get('task', '', 'string');
		
		$js = '';
			
		if ( JFactory::getApplication()->isAdmin() && (
			($option=='com_config' && ($view == 'component' || $controller='component') && $component == 'com_flexicontent') ||
			(($option=='com_modules' || $option=='com_advancedmodules') && $view == 'module') ||
			($option=='com_flexicontent' && ($view == 'category' || $view == 'item'))
		) ) {
			// WORKAROUNDs of for 2 issues in com_config: slow chosen JS and PHP 5.3.9+ 'max_input_vars' limit
			if (FLEXI_J30GE && ($option=='com_config' && ($view == 'component' || $controller='component') && $component == 'com_flexicontent')) {
				// Make sure chosen JS file is loaded before our code
				JHtml::_('formbehavior.chosen', '#_some_iiidddd_');
				// replace chosen function
				/*$js .= "
					jQuery.fn.chosen = function(){};
				";*/
			}
			$js .= "
				jQuery(document).ready(function() {
					jQuery(document.forms['adminForm']).attr('data-fc_doserialized_submit', '1');
					//if ('".$option."'=='com_flexicontent') jQuery(document.forms['adminForm']).attr('data-fc_doajax_submit', '1');
				});
				var fc_max_input_vars = ".ini_get('max_input_vars').";
			";
		}
		if ($js) $document->addScriptDeclaration($js);
		

		// Detect resultion we will do this regardless of ... using mobile layouts
		if ($this->cparams->get('use_mobile_layouts') || $app->isAdmin()) $this->detectClientResolution($this->cparams);
		
		// Exclude pagebreak outputing dialog from redirection
		if ( $option=='com_content' && $layout=='pagebreak' ) return;
		//if( $option=='com_content' && $view=='articles' && $layout=='modal' && $tmpl=='component' ) return;
		
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
		$jinput = JFactory::getApplication()->input;
		$app    = JFactory::getApplication();
		$user   = JFactory::getUser();
		
		$option = $jinput->get('option', '', 'cmd');
		$view   = $jinput->get('view', '', 'cmd');
		$task   = $jinput->get('task', '', 'string');
		
		$_ct = explode('.', $task);
		$task = $_ct[ count($_ct) - 1];
		if (count($_ct) > 1) $controller = $_ct[0];
		
		// NOTE: in J1.6+, a user can be assigned multiple groups, so we need to retrieve them
		$usergroups = $user->get('groups');
		$usergroups = is_array($usergroups) ? $usergroups : array();
		$usergroups = array_keys($usergroups);
		
		// Get user groups excluded from redirection
		$exclude_cats = $this->params->get('exclude_redirect_cats', array());
		$exclude_arts = $this->params->get('exclude_redirect_articles', array());
		
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
				if( count(array_intersect($usergroups, $exclude_arts)) ) return false;
				
				// Default (target) redirection url
				$redirectURL = 'index.php?option='.$this->extension;
				
				// Get request variables used to determine whether to apply redirection
				$layout   = $jinput->get('layout', '', 'cmd');
				$function = $jinput->get('function', '', 'cmd');
				
				// *** Specific Redirect Exclusions ***
				
				//--. (J2.5 only) Selecting Joomla article for menu item
				if ( $layout=="modal" && $function="jSelectArticle_jform_request_id" ) return false;
				
				//--. JA jatypo (editor-xtd plugin button for text style selecting)
				if ($jinput->get('jatypo', '', 'cmd')!="" && $layout=="edit") return false;

				//--. Allow listing featured backend management
				if ($view=="featured") return false;
				//return false;  // for testing
				
				if ($task == 'add') {
					$redirectURL .= '&task=items.add';
				} else if ($task == 'edit') {
					$cid = JRequest::getVar('id');
					$cid = $cid ? $cid : JRequest::getVar('cid');
					$redirectURL .= '&task=items.edit&cid='.intval(is_array($cid) ? $cid[0] : $cid);
				} else if ($task == 'element') {
					$redirectURL .= '&view=itemelement&tmpl=component&object='.JRequest::getVar('object','');
				} else {
					$redirectURL .= '&view=items';
				}
				
				// Apply redirection
				$app->redirect($redirectURL,'');
				return false;

			} elseif ( $option == 'com_categories' ) {
				
				// Check if a user group is groups, that are excluded from category redirection
				if( count(array_intersect($usergroups, $exclude_cats)) ) return false;
				
				// Get request variables used to determine whether to apply redirection
				$category_scope = JRequest::getVar( 'extension' );
				
				// Apply redirection if in com_categories is in content scope
				if ( $category_scope == 'com_content' )
				{
					if ($task == 'add') {
						$redirectURL .= 'index.php?option='.$this->extension.'&task=category.add&extension='.$this->extension;
					} else if ($task == 'edit') {
						$cid = JRequest::getVar('id');
						$cid = $cid ? $cid : JRequest::getVar('cid');
						$redirectURL .= 'index.php?option='.$this->extension.'&task=category.edit&cid='.intval(is_array($cid) ? $cid[0] : $cid);
					} else {
						$redirectURL = 'index.php?option='.$this->extension.'&view=categories';
					}
					$app->redirect($redirectURL,'');
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
			// In J2.5, in case of form we need to use a_id instead of id, this will also be set in HTTP Request too and JRouter too
			$id = JRequest::getInt('id');
			$id = ($view=='form') ? JRequest::getInt('a_id') : $id;
			
			// Get article category id, if it is not already in url
			$catid 	= JRequest::getInt('catid');
			if (!$catid) {
				$db->setQuery('SELECT catid FROM #__content WHERE id = ' . $id);
				$catid = $db->loadResult();
			}
			$in_limits = ($catid>=FLEXI_LFT_CATEGORY && $catid<=FLEXI_RGT_CATEGORY);
			
			// Allow Joomla article view for non-bound items or for specific content types
			if ($in_limits && $view == 'article') {
				$db->setQuery('SELECT	attribs'
				. ' FROM #__flexicontent_types AS ty '
				. ' JOIN #__flexicontent_items_ext AS ie ON ie.type_id = ty.id '
				. ' WHERE ie.item_id = ' . $id);
				$type_params = $db->loadResult();
				if (!$type_params) $in_limits = false; // article not bound to FLEXIcontent yet
				else {
					$type_params = new JRegistry($type_params);
					$in_limits = $type_params->get('allow_jview') == 0;  // Allow viewing by article view, if so configured
				}
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
					$newRequest = array ('option' => $this->extension, 'view' => FLEXI_ITEMVIEW, 'Itemid' => JRequest::getInt( 'Itemid'), 'lang' => JRequest::getCmd( 'lang'));
				} else if (
					$view == 'form'           // c. CASE :  com_content link to article edit form (J2.5 only)
				) {
					$newRequest = array ('option' => $this->extension, 'view' => FLEXI_ITEMVIEW, 'task'=>'edit', 'layout'=>'form', 'id' => $id, 'Itemid' => JRequest::getInt( 'Itemid'), 'lang' => JRequest::getCmd( 'lang'));
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
					$urlItem = 'index.php?option='.$this->extension.'&view='.FLEXI_ITEMVIEW.'&id='.$id.'&task=edit&layout=form';
				} else {
					// Include the route helper files
					require_once (JPATH_SITE.DS.'components'.DS.'com_content'.DS.'helpers'.DS.'route.php');
					require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');
					
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
		$ROOT_CATEGORY_ID = 1;
		$_nowDate = 'UTC_TIMESTAMP()';
		$nullDate	= $db->getNullDate();
		
		// get the category tree and append the ancestors to each node
		$query	= 'SELECT c.id, c.parent_id, c.published, c.access, c.title, c.level, c.lft, c.rgt, c.language,'
			. '  CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END AS slug,'
			. '  COUNT(rel.itemid) AS numitems'
			. ' FROM #__categories as c'
			. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON c.id=rel.catid'
			. ' LEFT JOIN #__content AS i ON rel.itemid=i.id '
			. '  AND i.state IN (1,-5) '
			. '  AND ( i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= '.$_nowDate.' )'
			. '  AND ( i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= '.$_nowDate.' )'
			. " WHERE c.extension='".FLEXI_CAT_EXTENSION."' AND c.lft > '" . FLEXI_LFT_CATEGORY . "' AND c.rgt < '" . FLEXI_RGT_CATEGORY . "'"
			. ' GROUP BY c.id'
			. ' ORDER BY c.parent_id, c.lft'
			;
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
			$list = @$children[$parent] ? $children[$parent] : array();
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
			$cat->totalitems         = plgSystemFlexisystem::_getItemCounts($cat);
			$cat->descendants        = implode(',', $cat->descendantsarray);
			$cat->language           = isset($cat->language) ? $cat->language : '';
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
		$ROOT_CATEGORY_ID = 1;
		if (!$ancestors) $ancestors = array();
		
		if (@$children[$id] && $level <= $maxlevel) {
			foreach ($children[$id] as $v) {
				$id = $v->id;
				
				if ((!in_array($v->parent_id, $ancestors)) && $v->parent_id != $ROOT_CATEGORY_ID) {
					$ancestors[] 	= $v->parent_id;
				} 
				
				if ($v->parent_id==1) {  // Top level category ( a child of ROOT)
					$pre    = '';
					$spacer = '&nbsp;.&nbsp;';
				} else if ( $type ) {
					$pre    = '<sup>|_</sup>&nbsp;';
					$spacer = '&nbsp;.&nbsp;';
				} else {
					$pre    = '-&nbsp;';
					$spacer = '&nbsp;.&nbsp;';
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
	 * Get the total number of items of each category node
	 *
	 * @access private
	 * @return array
	 */
	static private function _getItemCounts($cat)
	{
		$totalItems = 0;
		$stack = array();
		$stack[] = $cat;
		
		while( count($stack) ) {
			$v = array_pop($stack);
			$totalItems += $v->numitems;
			
			if ( empty($v->childrenarray) ) continue;
			foreach( $v->childrenarray as $child ) $stack[] = $child;
		}
		return $totalItems;
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
		
		if ( $option == 'com_config' && $component == $this->extension &&
			($task == 'apply' || $task == 'save' || $task == 'component.apply' || $task == 'component.save' || $task == 'config.save.component.apply' || $task == 'config.save.component.save') )
		{
			// Indicate that next page load will clean categories cache so that cache configuration will be recalculated
			// (we will not do this at this step, because new component configuration has not been saved yet)
			$session->set('clear_cats_cache', 1, 'flexicontent');
			
		}
	}
	
	
	function handleSerialized()
	{
		// Workaround for max_input_vars (PHP 5.3.9+), in the case that form sender is com_config
		// J1.6+ adds ACL which is 50+ variables (due to FLEXIcontent's access.xml) per user-group
		if ( !empty($_POST['fcdata_serialized']) )
		{
			//print_r($_REQUEST); exit;
			//echo count($_REQUEST, COUNT_RECURSIVE); exit;
			
			$form_data = $this->parse_json_decode( $_POST['fcdata_serialized'] );
			//parse_str($_POST['fcdata_serialized'], $form_data);  // Combined with "jQuery.serialize()", but cannot be used to overcome 'max_input_vars'
			foreach($form_data as $n => $v)  JRequest::setVar($n, $v, 'POST');
			
			/*foreach($_GET as $var => $val) {
				if ( !isset($_POST[$var]) ) JFactory::getApplication()->enqueueMessage( "GET variable: ".$var . " is not set in the POST ARRAY", 'message');
			}*/
			
			$total_vars = 0;
			foreach($_REQUEST as $var_1) {   // Level 1
				if (!is_array($var_1)) $total_vars++;
				else foreach($var_1 as $var_2) {     // Level 2
					if (!is_array($var_2)) $total_vars++;
					else foreach($var_2 as $var_3) {   // Level 3
						$total_vars += !is_array($var_3) ? 1 : count($var_3);
					}
				}
			}
			//echo $total_vars." - ".count($_REQUEST, COUNT_RECURSIVE); exit;
			
			/*JFactory::getApplication()->enqueueMessage(
				"Form data were serialized, ".
				'<b class="label">PHP max_input_vars</b> <span class="badge badge-info">'.ini_get('max_input_vars').'</span> '.
				'<b class="label">Estimated / Actual FORM variables</b>'.
				'<span class="badge badge-warning">'.$_POST['fcdata_serialized_count'].'</span> / <span class="badge">'.$total_vars.'</span> ',
				'message'
			);*/
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
		$this->set_cache_control();  // Avoid expiration messages by the browser when browser's back/forward buttons are clicked
		
		$app      = JFactory::getApplication();
		$session  = JFactory::getSession();
		
		// Count an item or category hit if appropriate
		if ( $app->isSite() )$this->countHit();
		
		// If this is reached we now that the code for setting screen cookie has been added
		if ( $session->get('screenSizeCookieToBeAdded', 0, 'flexicontent') ) {
			$session->set('screenSizeCookieTried', 1, 'flexicontent');
			$session->set('screenSizeCookieToBeAdded', 0, 'flexicontent');
		}
		
		// Add performance message at document's end
		global $fc_performance_msg;
		if ($fc_performance_msg) {
			$html = JResponse::getBody();
			$inline_css = 'margin:12px 1% 48px !important; text-align:left !important; float:none !important; width:auto!important; display:block !important;';
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
	public function set_cache_control()
	{
		$option = JRequest::getVar('option');
		$fc_cachable = JFactory::getSession()->get('fc_cachable', null, 'flexicontent');
		if ($option==$this->extension && $fc_cachable!==null) {
			// Try to avoid browser warning message "Page has expired or similar"
			// This should turning off the 'must-revalidate' directive in the 'Cache-Control' header
			JResponse::allowCache(false);
			JResponse::setHeader('Pragma', 'no-cache');
			
			// Ask intermediary (proxy, etc) caches not to cache the response
			JResponse::setHeader('Cache-Control', 'private');
		}
	}
	
	
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
		$debug_mobile = $this->cparams->get('debug_mobile');
		
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
		
		$debug_mobile = $this->cparams->get('debug_mobile');
		
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
		$tz	= new DateTimeZone($app->getCfg('offset'));
		$date->setTimezone($tz);
		$current_time_secs = $date->toUnix();
		//echo $date->toFormat()." <br>";
		
		if ($checkin_on_session_end) {
			$query = 'SELECT DISTINCT userid FROM #__session WHERE guest=0';
			$db->setQuery($query);
			$logged = $db->loadColumn();
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
					$date = JFactory::getDate($record->checked_out_time);
					$tz	= new DateTimeZone($app->getCfg('offset'));
					$date->setTimezone($tz);
					$checkout_time_secs = $date->toUnix();
					//echo $date->toFormat()." <br>";
					
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
		$tz	= new DateTimeZone($app->getCfg('offset'));
		$date->setTimezone($tz);
		$current_time_secs = $date->toUnix();
		//echo $date->toFormat()." <br>";
		
		// Check if auto archive interval passed
		$session = JFactory::getSession();
		$last_autoarchive_secs = $session->get('last_autoarchive_secs', 0, 'flexicontent');
		$last_autoarchive_secs = $session->set('last_autoarchive_secs', $current_time_secs, 'flexicontent');
		if ($current_time_secs - $last_autoarchive_secs < $auto_archive_minute_interval*60) return;
		
		$new_state = $archive_on_publish_down==1 ? 2 : 0;
		$_nowDate = 'UTC_TIMESTAMP()';
		$nullDate	= $db->getNullDate();
		
		$query = 'UPDATE #__content SET state = '.$new_state.
			($clear_publish_down_date ? ', publish_down = '.$db->Quote($nullDate) : '').
			' WHERE publish_down != '.$db->Quote($nullDate).' AND publish_down <= '.$_nowDate;
		//echo $query;
		$db->setQuery($query);
		$db->query();
		
		$query = 'UPDATE #__flexicontent_items_tmp SET state = '.$new_state.
			($clear_publish_down_date ? ', publish_down = '.$db->Quote($nullDate) : '').
			' WHERE publish_down != '.$db->Quote($nullDate).' AND publish_down <= '.$_nowDate;
		//echo $query;
		$db->setQuery($query);
		$db->query();
	}
	
	
	/* Increment item / category hits counters, according to configuration */
	function countHit()
	{
		$option = JRequest::getVar('option');
		$view   = JRequest::getVar('view');
		if ( ($option==$this->extension && $view==FLEXI_ITEMVIEW) ) {
			$item_id = JRequest::getInt('id');
			if ( $item_id && $this->count_new_hit($item_id) )
			{
				$db = JFactory::getDBO();
				$db->setQuery('UPDATE #__content SET hits=hits+1 WHERE id = '.$item_id );
				$db->query();
				$db->setQuery('UPDATE #__flexicontent_items_tmp SET hits=hits+1 WHERE id = '.$item_id );
				$db->query();
			}
		} else if ($option=='com_content' && $view=='article') {
			// Always increment if non FLEXIcontent view
			$item_id = JRequest::getInt('id');
			if ( $item_id )
			{
				$db = JFactory::getDBO();
				$db->setQuery('
					UPDATE #__flexicontent_items_tmp AS t
					JOIN #__content AS i ON i.id=t.id
					SET t.hits=i.hits
					WHERE t.id = '.$item_id
				);
				$db->query();
			}
		} else if ($option==$this->extension &&  $view=='category') {
			$cat_id = JRequest::getInt('cid');
			$layout = JRequest::getVar('layout');
			if ($cat_id && empty($layout)) {
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
	
	
	/* Decide about incrementing item / category hits counter according to configuration */
	function count_new_hit($item_id) // If needed to modify params then clone them !! ??
	{
		if (!$this->cparams->get('hits_count_unique', 0)) return 1; // Counting unique hits not enabled

		$db = JFactory::getDBO();
		$visitorip = $_SERVER['REMOTE_ADDR'];  // Visitor IP
		$current_secs = time();  // Current time as seconds since Unix epoch
		if ($item_id==0) {
			JFactory::getApplication()->enqueueMessage(nl2br("Invalid item id or item id is not set in http request"),'error');
			return 1; // Invalid item id ?? (do not try to decrement hits in content table)
		}


		// CHECK RULE 1: Skip if visitor is from the specified ips
		$hits_skip_ips = $this->cparams->get('hits_skip_ips', 1);   // Skip ips enabled
		$hits_ips_list = $this->cparams->get('hits_ips_list', '127.0.0.1');  // List of ips, by default localhost
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
		$hits_skip_bots = $this->cparams->get('hits_skip_bots', 1);  // Skip bots enabled
		$hits_bots_list = $this->cparams->get('hits_bots_list', 'bot,spider,crawler,search,libwww,archive,slurp,teoma');   // List of bots
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
			$secs_between_unique_hit = 60 * $this->cparams->get('hits_mins_to_unique', 10);  // Seconds between counting unique hits from an IP

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
	 * Function to restore serialized form data with:  JSON.stringify( jform.serializeArray() )
	 * This is currently UNUSED, we use parse_str instead
	 */
	private function parse_json_decode($string) {
		$result = array();
		$pairs = json_decode($string, true);
		//echo "<pre>"; print_r($pairs); exit;
		
		// find the pairs "name=value"
		$toEvaluate = ''; // we will do a big eval() at the end not pretty but simplier
		foreach ($pairs as $pair) {
			$name = $pair['name'];
			$value = $pair['value'];
			
			// Escape name and value strings
			$name = str_replace('\\', '\\\\', $name);
			$value = str_replace('\\', '\\\\', $value);
			
			// Always quote the value even if it is numeric, this is proper as parameters in Joomla are treated as strings
			$value = '"' . str_replace('"', '\"', $value) . '"';
			
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
				//$toEvaluate .= '$result[\'' . $name . ' = ' . $value . "; \n";
				eval('$result[\'' . $name . ' = ' . $value . "; \n");
			}
			
			// CASE name is not an array, a single variable assignment
			else {
				// Add double slashes to index name
				$name = str_replace('\'', '\\\'', $name);
				// Finally quote the name, thus treating index as string and create assignment to be evaluated: $result['name'] = 'value';
				//$toEvaluate .= '$result[\'' . $name . '\'] = ' . $value . "; \n";
				eval('$result[\'' . $name . '\'] = ' . $value . "; \n");
			}
		}
		//eval($toEvaluate);
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
	
	
	/*
	 * Add custom LAYOUT parameters to components that clear them during validation e.g. modules, menus
	 * DONE modules, TODO: menus
	 */
	function onExtensionBeforeSave($context, $table, $isNew)
	{
		// *******************************
		// TODO: add support for com_menus
		// *******************************
		
		// Check for com_modules context
		if ($context=='com_modules.module' || $context=='com_advancedmodules.module' || substr($context, 0, 10) === "com_falang")
		{
			// Check for non-empty layout parameter
			$layout = $_POST['jform']['params']['layout'];
			if (empty($layout)) return;
			
			// Check for currently supported cases, !!! TODO add case of MENUS
			if (empty($table->module)) return;
			
			// Check if layout XML parameter file exists
			$client = JApplicationHelper::getClientInfo($table->client_id);
			$layoutpath = JPath::clean($client->path . '/modules/' . $table->module . '/tmpl/' . $layout .'.xml');
			if (!file_exists($layoutpath))
			{
				$layoutpath = JPath::clean($client->path . '/modules/' . $table->module . '/tmpl/_fallback/_fallback.xml');
				if (!file_exists($layoutpath)) return;
			}
			
			// Load XML file
			if (FLEXI_J30GE) {
				$xml = simplexml_load_file($layoutpath);
				$xmldoc = & $xml;
			} else {
				$xml = JFactory::getXMLParser('Simple');
				$xml->loadFile($layoutpath);
				$xmldoc = & $xml->document;
			}
			//echo "<pre>"; print_r($xmldoc); echo "</pre>";
			
			// Create form object loading the , (form name seems not to cause any problem)
			$jform = new JForm('com_flexicontent.template.item', array('control' => 'jform', 'load_data' => true));
			$tmpl_params = FLEXI_J30GE ? $xmldoc->asXML() : $xmldoc->toString();
			$jform->load($tmpl_params);
			
			// Set cleared layout parameters
			$_post = & $_POST['jform']['params'];  //echo "<pre>"; print_r($_post); echo "</pre>";
			$params = new JRegistry($table->params);
			$grpname = 'params';
			
			$isValid = !$jform->validate($_post, $grpname);
			if ($isValid) {
				JFactory::getApplication()->enqueueMessage('Error validating layout posted parameters. Layout parameters were not saved', 'error');
				return;
			}
			
			foreach ($jform->getGroup($grpname) as $field) {
				$fieldname =  $field->__get('fieldname');
				if (substr($fieldname, 0, 2)=="__") continue;
				$value = $_post[$fieldname];
				$params->set($fieldname, $value);
			}
			
			// Set parameters back to module's DB table object
			$table->params = $params->toString();
			//echo "<pre>"; print_r($table->params); echo "</pre>";
			//die('onExtensionBeforeSave: '. $layoutpath);
		}
	}
	
	
	
	function renderFields($context, &$row, &$params, $page=0)
	{
		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
		
		require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.fields.php');
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'permission.php');
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.FLEXI_ITEMVIEW.'.php');
		
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		$aid = JAccess::getAuthorisedViewLevels($user->id);
		
		$itemmodel = new FlexicontentModelItem();
		$item = $itemmodel->getItem($row->id, $check_view_access=false);
		
		$view = 'com_content.article' ? FLEXI_ITEMVIEW : 'category';
		$items = FlexicontentFields::getFields($item, $view, $_item_params = null, $aid = null, $use_tmpl = false);  // $_item_params == null means only retrieve fields
		
		// Only Render custom fields
		$displayed_fields = array();
		foreach ($item->fields as $field) {
			if ($field->iscore) continue;
			
			$displayed_fields[$field->name] = $field;
			$values = isset($item->fieldvalues[$field->id]) ? $item->fieldvalues[$field->id] : array();
			FlexicontentFields::renderField($item, $field, $values, $method='display', $view);
		}
		
		if (!count($displayed_fields)) return null;
		
		// Render the list of groups
		$field_html = array();
		foreach($displayed_fields as $field_name => $field) {
			$_values = null;
			if ( !isset($field->display) ) continue;
			$field_html[] = '
				<div class="fc-field-box">
					'.($field->parameters->get('display_label') ? '
					<span class="flexi label">'.$field->label.'</span>' : '').'
					<div class="flexi value">'.$field->display.'</div>
				</div>
				';
		}
		$_display = '<div class="fc-custom-fields-box">'.implode('', $field_html).'</div>';
		
		return $_display;
	}
	
	
	
	function onContentAfterTitle($context, &$row, &$params, $page=0)
	{
		if ( $context!='com_content.article' ) return;  // This is meant for Joomla article view
		if ( JRequest::getVar('option')==$this->extension || JRequest::getVar('isflexicontent') ) return;  // This is meant for non-FLEXIcontent views
		if ($this->cparams->get('article_jview_fields_placement', 1)!=0) return;
		
		return $this->renderFields($context, $row, $params, $page);
	}
	
	function onContentBeforeDisplay($context, &$row, &$params, $page=0)
	{
		if ( $context!='com_content.article' ) return;  // This is meant for Joomla article view
		if ( JRequest::getVar('option')==$this->extension || JRequest::getVar('isflexicontent') ) return;  // This is meant for non-FLEXIcontent views
		if ($this->cparams->get('article_jview_fields_placement', 1)!=1) return;
		
		return $this->renderFields($context, $row, $params, $page);
	}
	
	function onContentAfterDisplay($context, &$row, &$params, $page=0)
	{
		if ( $context!='com_content.article' ) return;  // This is meant for Joomla article view
		if ( JRequest::getVar('option')==$this->extension || JRequest::getVar('isflexicontent') ) return;  // This is meant for non-FLEXIcontent views
		if ($this->cparams->get('article_jview_fields_placement', 1)!=2) return;
		
		return $this->renderFields($context, $row, $params, $page);
	}
}