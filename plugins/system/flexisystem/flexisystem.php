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

jimport('cms.plugin.plugin');
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);

require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');

/**
 * Example system plugin
 */
class plgSystemFlexisystem extends JPlugin
{
	var $extension;  // Component name
	var $cparams;    // Component parameters
	var $autoloadLanguage = false;
	
	/**
	 * Constructor
	 */
	function __construct( &$subject, $config )
	{
		parent::__construct( $subject, $config );

		static $language_loaded = null;
		if (!$this->autoloadLanguage && $language_loaded === null) $language_loaded = JPlugin::loadLanguage('plg_system_flexisystem', JPATH_ADMINISTRATOR);

		$this->extension = 'com_flexicontent';
		$this->cparams = JComponentHelper::getParams($this->extension);

		// Temporary workaround until code is updated
		if (FLEXI_J40GE) JFactory::getDbo()->setQuery("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))")->execute();
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

		$app  = JFactory::getApplication();
		$task = $app->input->get('task', '', 'string');  // NOTE during this event 'task' is (controller.task), thus we use filtering 'string'

		if ( $task=='config.store' )
		{
			$comp = $app->input->get('comp', '', 'cmd');
			$comp = str_replace('com_flexicontent.category.', 'com_content.category.', $comp);
			$comp = str_replace('com_flexicontent.item.', 'com_content.article.', $comp);
			$app->input->set('comp', $comp);

			if ( $comp == 'com_content' || $comp == 'com_flexicontent' )
			{
				$skip_arr = array('core.admin'=>1, 'core.options'=>1, 'core.manage'=>1);
				$action = $app->input->get('action');
				if ( substr($action, 0, 5) == 'core.' && !isset($skip_arr[$action]) )
				{
					$comp_other = $comp == 'com_content'  ?  'com_flexicontent'  :  'com_content';
					$permissions = array(
						'component' => $comp_other,
						'action'    => $app->input->get('action', '', 'cmd'),
						'rule'      => $app->input->get('rule', '', 'cmd'),
						'value'     => $app->input->get('value', '', 'cmd'),
						'title'     => $app->input->get('title', '', 'string')
					);
					
					JLoader::register('ConfigModelApplication', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_config'.DS.'model'.DS.'application.php');
					JLoader::register('ConfigModelForm', JPATH_SITE.DS.'components'.DS.'com_config'.DS.'model'.DS.'form.php');
					JLoader::register('ConfigModelCms', JPATH_SITE.DS.'components'.DS.'com_config'.DS.'model'.DS.'cms.php');
					
					//require_once( JPATH_ADMINISTRATOR.DS.'components'.DS.'com_config'.DS.'models'.DS.'application.php');					
					if ( !(substr($permissions['component'], -6) == '.false') )
					{
						// Load Permissions from Session and send to Model
						$model    = new ConfigModelApplication;
						$response = $model->storePermissions($permissions);
						//echo new JResponseJson(json_encode($response));
					}
				}
			}
		}
		
		
		// Fix for return urls with unicode aliases
		$return   = $app->input->get('return', null);
		$isfcurl  = $app->input->get('isfcurl', null);
		$fcreturn = $app->input->get('fcreturn', null);
		if ($return && ($isfcurl || $fcreturn)) $app->input->set('return', strtr($return, '-_,', '+/='));
		
		$username = $app->input->get('fcu', null);
		$password = $app->input->get('fcp', null);
		$option   = $app->input->get('option', null);
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
		
		if (FLEXI_SECTION || FLEXI_CAT_EXTENSION)
		{
			global $globalcats;
			$start_microtime = microtime(true);
			if (FLEXI_CACHE) 
			{
				// Add the category tree to categories cache
				$catscache = JFactory::getCache('com_flexicontent_cats');
				$catscache->setCaching(1);                  // Force cache ON
				$catscache->setLifeTime(FLEXI_CACHE_TIME);  // Set expire time (default is 1 hour)
				$globalcats = $catscache->get(array($this, 'getCategoriesTree'), array());
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
			$fcdebug = $app->input->get('fcdebug', '', 'cmd');
			$fcdebug = strlen($fcdebug) ? (int)$fcdebug : $session->get('fcdebug', 0, 'flexicontent');

			// Enable/Disable debugging
			$session->set('fcdebug', ($fcdebug ? 1 : 0), 'flexicontent');
			$this->cparams->set('print_logging_info', ($fcdebug ? 2 : 0));
		}
		
		$print_logging_info = $this->cparams->get('print_logging_info');
		if ($print_logging_info) { global $fc_run_times; $start_microtime = microtime(true); }
		
		// (a.1) (Auto) Check-in DB table records according to time limits set
		$this->checkinRecords();
		
		// (a.2) (Auto) Change item state, e.g. archive expired items (publish_down date exceeded)
		$this->handleExpiredItems();
		
		if ($print_logging_info) $fc_run_times['auto_checkin_auto_state'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		// (b) Autologin for frontend preview
		if (!empty($username) && !empty($password) && $this->cparams->get('autoflogin', 0)) {
			$result = $this->loginUser();
		}
		
		// (c) Route PDF format to HTML format for J1.6+
		$redirect_pdf_format = $this->params->get('redirect_pdf_format', 1);
		if ($redirect_pdf_format && $app->input->get('format', 'html', 'cmd') == 'pdf')
		{
			$app->input->set('format', 'html');
			if ($redirect_pdf_format==2)
			{
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
		// Detect saving component configuration, e.g. set a flag to indicate cleaning categories cache on next page load
		// We place this above format check, because maybe, saving will be AJAX based (? format=raw ?)
		$this->trackSaveConf();
		
		$format = JFactory::getApplication()->input->get('format', 'html', 'cmd');
		if ($format != 'html') return;
		
		$app      = JFactory::getApplication();
		$session  = JFactory::getSession();
		$document = JFactory::getDocument();
		
		$option = $app->input->get('option', '', 'cmd');
		$view   = $app->input->get('view', '', 'cmd');
		$controller = $app->input->get('controller', '', 'cmd');
		$component  = $app->input->get('component', '', 'cmd');
		
		$layout = $app->input->get('layout', '', 'string');
		$tmpl   = $app->input->get('tmpl', '', 'string');
		$task   = $app->input->get('task', '', 'string');  // NOTE during this event 'task' is (controller.task), thus we use filtering 'string'
		
		$fcdebug = $this->cparams->get('print_logging_info')==2  ?  2  :  $session->get('fcdebug', 0, 'flexicontent');
		$isAdmin = JFactory::getApplication()->isAdmin();

		$isFC_Config = $isAdmin ? ($option=='com_config' && ($view == 'component' || $controller='component') && $component == 'com_flexicontent')  :  false;
		$isBE_Module_Edit = $isAdmin ? (($option=='com_modules' || $option=='com_advancedmodules') && $view == 'module')  :  false;
		$isBE_Menu_Edit   = $isAdmin ? ($option=='com_menus' && $view == 'item' && $layout=='edit')  :  false;

		$js = '';

		if ( $isBE_Module_Edit || $isBE_Menu_Edit )
		{
			JFactory::getLanguage()->load($this->extension, JPATH_ADMINISTRATOR, 'en-GB'	, $_force_reload = false);
			JFactory::getLanguage()->load($this->extension, JPATH_ADMINISTRATOR, null		, $_force_reload = false);
		}

		if (
			$isAdmin && ($isFC_Config || $isBE_Module_Edit) || ($option=='com_flexicontent' && ($isAdmin || $task == 'edit'))  // frontend task does not include 'controller.'
		) {
			// WORKAROUNDs for slow chosen JS in component configuration form
			if ($isFC_Config)
			{
				// Make sure chosen JS file is loaded before our code, but do not attach it to any elements (YET)
				JHtml::_('formbehavior.chosen', '#_some_iiidddd_');
				//$js .= "\n"."jQuery.fn.chosen = function(){};"."\n";  // Suppress chosen function completely, (commented out ... we will allow it)
			}
			
			// Add information for PHP 5.3.9+ 'max_input_vars' limit
			$max_input_vars = ini_get('max_input_vars');
			if (extension_loaded('suhosin'))
			{
				$suhosin_lim = ini_get('suhosin.post.max_vars');
				if ($suhosin_lim < $max_input_vars) $max_input_vars = $suhosin_lim;
				$suhosin_lim = ini_get('suhosin.request.max_vars');
				if ($suhosin_lim < $max_input_vars) $max_input_vars = $suhosin_lim;
			}
			$js .= "
				jQuery(document).ready(function() {
					Joomla.fc_max_input_vars = ".$max_input_vars.";
					".((JDEBUG || $fcdebug) ? 'Joomla.fc_debug = 1;' : '')."
					jQuery(document.forms['adminForm']).attr('data-fc_doserialized_submit', '1');
					". /*(($option=='com_flexicontent' && $view='category') ? "jQuery(document.forms['adminForm']).attr('data-fc_force_apply_ajax', '1');" : "") .*/"
				});
			";
		}
		
		// Hide Joomla administration menus in FC modals
		if (
			$isAdmin && (
				($option=='com_users' && ($view == 'user'))
			)
		)
			$js .= "
				jQuery(document).ready(function() {
					var el = parent.document.getElementById('fc_modal_popup_container');
					if (el) {
						jQuery('.navbar').hide();
						jQuery('body').css('padding-top', 0);
					}
				});
			";
		if ($js) $document->addScriptDeclaration($js);
		
		
		// Detect resolution we will do this regardless of ... using mobile layouts
		if ($this->cparams->get('use_mobile_layouts') || $app->isAdmin()) $this->detectClientResolution($this->cparams);
		
		// Exclude pagebreak outputing dialog from redirection
		if ( $option=='com_content' && ($layout=='pagebreak' || $layout=='modal') ) return;
		
		// Redirect backend article / category management, and frontend article view
		$app->isAdmin() ?
			$this->redirectAdminComContent() :
			$this->redirectSiteComContent() ;
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
		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();
		$option = $app->input->get('option', '', 'cmd');
		
		// Skip other components
		if (empty($option) || ($option !== 'com_content'  && $option !== 'com_categories'))
		{
			return;
		}

		$view   = $app->input->get('view', '', 'cmd');
		$task   = $app->input->get('task', '', 'string');  // NOTE during this event 'task' is (controller.task), thus we use filtering 'string'

		// Split the task into 'controller' and task
		$_ct = explode('.', $task);
		$task = $_ct[ count($_ct) - 1];
		if (count($_ct) > 1) $controller = $_ct[0];
		
		// Get user groups of current user
		$usergroups = $user->getAuthorisedGroups();
		
		// Get user groups excluded from redirection
		$exclude_cats = $this->params->get('exclude_redirect_cats', array());
		$exclude_arts = $this->params->get('exclude_redirect_articles', array());
		
		// Get URLs excluded from redirection
		$excluded_urls = $this->params->get('excluded_redirect_urls');
		$excluded_urls = preg_split("/[\s]*%%[\s]*/", $excluded_urls);
		if (empty($excluded_urls[count($excluded_urls)-1]))
		{
			unset($excluded_urls[count($excluded_urls)-1]);
		}
		
		// Get current URL
		$uri = JUri::getInstance();


		// First check excluded urls
		foreach ($excluded_urls as $excluded_url)
		{
			$quoted = preg_quote($excluded_url, "#");
			if(preg_match("#$quoted#", $uri)) return;
		}

		// Get request variables used to determine whether to apply redirection
		$layout   = $app->input->get('layout', '', 'cmd');
		//$function = $app->input->get('function', '', 'cmd');
		//$editor   = $app->input->get('editor', '', 'cmd');

		// Selecting Joomla article / category from a modal e.g. from a menu item, or from an editor
		if ($layout=="modal")
		{
			return;
		}


		// if try to access com_content you get redirected to Flexicontent items
		if ( $option === 'com_content' )
		{
			// Check if a user group is groups, that are excluded from article redirection
			if( count(array_intersect($usergroups, $exclude_arts)) ) return;

			// *** Specific Redirect Exclusions ***

			//--. JA jatypo (editor-xtd plugin button for text style selecting)
			if ($app->input->get('jatypo', '', 'cmd') && $layout=="edit") return;

			//--. Allow listing featured backend management
			if ($view=="featured") return;

			switch ($task)
			{
				case 'add':
					$redirectURL = 'index.php?option=' . $this->extension . '&task=items.add';
					break;
				case 'edit':
					$cid = $app->input->get('id', $app->input->get('cid', 0));
					$redirectURL = 'index.php?option=' . $this->extension . '&task=items.edit&cid=' . intval(is_array($cid) ? $cid[0] : $cid);
					break;
				case 'element':
					$redirectURL = 'index.php?option=' . $this->extension . '&view=itemelement&tmpl=component&object=' . $app->input->get('object', '');
					break;
				default:
					if (!$task)
					{
						$redirectURL = 'index.php?option=' . $this->extension . '&view=items';
					}
					break;
			}
		}

		elseif ( $option === 'com_categories' )
		{
			// Check if a user group is groups, that are excluded from category redirection
			if( count(array_intersect($usergroups, $exclude_cats)) ) return;

			// Get request variables used to determine whether to apply redirection
			$category_scope = $app->input->get('extension', '', 'cmd');

			// Apply redirection only if in com_categories is in content scope
			if ( $category_scope !== 'com_content' ) return;

			switch ($task)
			{
				case 'add':
					$redirectURL = 'index.php?option=' . $this->extension . '&task=category.add&extension='.$this->extension;
					break;
				case 'edit':
					$cid = $app->input->get('id', $app->input->get('cid', 0));
					$redirectURL = 'index.php?option=' . $this->extension . '&task=category.edit&cid='.intval(is_array($cid) ? $cid[0] : $cid);
					break;
				default:
					if (!$task)
					{
						$redirectURL = 'index.php?option=' . $this->extension . '&view=categories';
					}
					break;
			}
		}

		// Apply redirection
		if (!empty($redirectURL))
		{
			$app->redirect($redirectURL, '');
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
		$db     = JFactory::getDbo();

		$option = $app->input->get('option', '', 'cmd');
		$view   = $app->input->get('view', '', 'cmd');

		//***
		//*** Let's Redirect/Reroute Joomla's article view & form to FLEXIcontent item view & form respectively !!
		//*** NOTE: we do not redirect / reroute Joomla's category views (blog, list, featured etc), thus site administrator can still utilize them
		//***

		$check_redirect = $option === 'com_content' && (
			$view === 'article'  ||  // a. CASE :  com_content ARTICLE VIEW
			$view === 'item'     ||  // b. CASE :  com_flexicontent ITEM VIEW / ITEM FORM link with com_content active menu item
			$view === 'form'         // c. CASE :  com_content ARTICLE FORM
		);

		if (!$check_redirect)
		{
			return;
		}


		//***
		//*** Get article / category IDs
		//***

		// In case of form we need to use a_id instead of id, this will also be set in HTTP Request too and JRouter too
		$id = $app->input->get('id', 0, 'int');
		$id = ($view=='form') ? $app->input->get('a_id', 0, 'int') : $id;

		// Get article category id, if it is not already in url
		$catid = $app->input->get('catid', 0, 'int');
		if (!$catid && $id)
		{
			$db->setQuery('SELECT catid FROM #__content WHERE id = ' . $id);
			$catid = $db->loadResult();
		}


		//***
		//*** First Check if within 'FLEXIcontent' category subtree)
		//***

		$in_limits = !$catid || ($catid >= FLEXI_LFT_CATEGORY && $catid <= FLEXI_RGT_CATEGORY);


		// ***
		// *** Allow Joomla article view for non-bound items or for specific content types (also 
		// ***

		if ($in_limits)
		{
			$db->setQuery('SELECT attribs'
				. ' FROM #__flexicontent_types AS ty '
				. ' JOIN #__flexicontent_items_ext AS ie ON ie.type_id = ty.id '
				. ' WHERE ie.item_id = ' . $id);
			$type_params = $db->loadResult();

			// If not found then article is not bound to a FLEXIcontent type yet
			if ($type_params)
			{
				$type_params = new JRegistry($type_params);
			}
		}

		// Allow viewing by Joomla article view, if so configured
		// CASES--  0: Reroute, 1: Allow, 2: Redirect
		$allow_jview = $view === 'item' || empty($type_params)   // NOTE case: view === 'item' (flexicontent view with com_content MENU item)
			? 0  // Force reroute
			: (int) $type_params->get('allow_jview', 0);


		// ***
		// *** Joomla article view / form allowed
		// ***

		if ($allow_jview === 1)
		{
			return;
		}


		// ***
		// *** Do re-routing (no page reloading)
		// ***

		elseif ($allow_jview === 0)
		{
			// Set new request variables
			// NOTE: we only need to set HTTP request variable that must be changed, but setting any other variables to same value will not hurt
			switch ($view)
			{
				case 'article':  // a. CASE :  com_content ARTICLE link that is rerouted to its corresponding flexicontent link
				case 'item':     // b. CASE :  com_flexicontent ITEM VIEW / ITEM FORM link with com_content active menu item
					$newRequest = array('option' => $this->extension, 'view' => 'item', 'Itemid' => $app->input->get('Itemid', null, 'int'), 'lang' => $app->input->get('lang', null, 'cmd'));
					break;
				case 'form':     // c. CASE :  com_content link to article edit form
					$newRequest = array ('option' => $this->extension, 'view' => 'item', 'task'=>'edit', 'layout'=>'form', 'id' => $id, 'Itemid' => $app->input->get('Itemid', null, 'int'), 'lang' => $app->input->get('lang', null, 'cmd'));
					break;
				default:
					// Unknown CASE ?? unreachable ?
					return;
			}
			//$app->enqueueMessage( "Set com_flexicontent item view instead of com_content article view", 'message');

			// Set variables in the HTTP request
			foreach($newRequest as $k => $v)
			{
				$app->input->set($k, $v);
			}

			// Set variables in the router too
			$app->getRouter()->setVars($newRequest, false);
		}


		// ***
		// *** Do redirection (using page reloading)
		// ***

		else  // $allow_jview === 2
		{
			if ($view === 'form')
			{
				$urlItem = 'index.php?option=' . $this->extension . '&view=item&id='.$id.'&task=edit';
			}

			else
			{
				// Include the route helper files
				require_once (JPATH_SITE.DS.'components'.DS.'com_content'.DS.'helpers'.DS.'route.php');
				require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');

				$itemslug	= $app->input->get('id', '', 'string');
				$catslug	= $app->input->get('catid', '', 'string');

				// Warning current menu item id must not be passed to the routing functions since it points to com_content, and thus it will break FC SEF URLs
				$urlItem 	= $catslug ? FlexicontentHelperRoute::getItemRoute($itemslug, $catslug) : FlexicontentHelperRoute::getItemRoute($itemslug);
				$urlItem 	= JRoute::_($urlItem);
			}
			//$app->enqueueMessage( "Redirected to com_flexicontent item view instead of com_content article view", 'message');

			// Do the redirection
			$app->redirect($urlItem);
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
		$db = JFactory::getDbo();
		$ROOT_CATEGORY_ID = 1;
		$_nowDate = 'UTC_TIMESTAMP()';
		$nullDate	= $db->getNullDate();
		
		// Get the category tree
		$query	= 'SELECT c.id, c.parent_id, c.published, c.access, c.title, c.level, c.lft, c.rgt, c.language,'
			. '  CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END AS slug, 0 AS numitems'
			. ' FROM #__categories as c'
			. ' WHERE c.extension=' . $db->Quote(FLEXI_CAT_EXTENSION) . ' AND c.lft > ' . $db->Quote(FLEXI_LFT_CATEGORY) . ' AND c.rgt < ' . $db->Quote(FLEXI_RGT_CATEGORY)
			. ' ORDER BY c.parent_id, c.lft'
			;
		$cats = $db->setQuery($query)->loadObjectList('id');

		// Get total active items for every category
		$query	= 'SELECT c.id, COUNT(rel.itemid) AS numitems'
			. ' FROM #__categories as c'
			. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON c.id=rel.catid'
			. ' LEFT JOIN #__content AS i ON rel.itemid=i.id '
			. '  AND i.state IN (1,-5) '
			. '  AND ( i.publish_up = ' . $db->Quote($nullDate) . ' OR i.publish_up <= ' . $_nowDate . ' )'
			. '  AND ( i.publish_down = ' . $db->Quote($nullDate) . ' OR i.publish_down >= ' . $_nowDate . ' )'
			. ' WHERE c.extension=' . $db->Quote(FLEXI_CAT_EXTENSION) . ' AND c.lft > ' . $db->Quote(FLEXI_LFT_CATEGORY) . ' AND c.rgt < ' . $db->Quote(FLEXI_RGT_CATEGORY)
			. ' GROUP BY c.id'
			;
		$cat_totals = $db->setQuery($query)->loadObjectList('id');
		foreach($cat_totals as $cat_id => $cat_total)
		{
			$cats[$cat_id]->numitems = $cat_total->numitems;
		}

		//establish the hierarchy of the categories
		$children = array();
		$parents = array();
		
		//set depth limit
		$levellimit = 30;
		
		foreach ($cats as $child)
		{
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
				$list[$id]->access		= $v->access;
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
			foreach( array_reverse($v->childrenarray) as $child ) $stack[] = $child;
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
		$app     = JFactory::getApplication();
		$session = JFactory::getSession();

		$option    = $app->input->get('option', '', 'cmd');
		$component = $app->input->get('component', '', 'cmd');
		$task      = $app->input->get('task', '', 'cmd');

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
		//echo "<pre>"; print_r($_POST); exit;
		//echo "<pre>"; print_r($_REQUEST); exit;
		//echo count($_REQUEST, COUNT_RECURSIVE); exit;
		
		// Workaround for max_input_vars limitation (PHP 5.3.9+)
		if ( !empty($_POST['fcdata_serialized']) )
		{
			$app     = JFactory::getApplication();

			//parse_str($_POST['fcdata_serialized'], $form_data);  // Combined with "jQuery.serialize()", but cannot be used to overcome 'max_input_vars'
			
			//$total_vars_e = null;
			//$form_data_e = $this->parse_json_decode_eval( $_POST['fcdata_serialized'], $total_vars_e );
			
			$total_vars = null;
			$form_data = $this->parse_json_decode( $_POST['fcdata_serialized'], $total_vars );
			
			//echo "<pre>"; print_r( $this->array_diff_recursive($form_data_e, $form_data) );  echo "</pre>"; exit;
			
			foreach($form_data as $n => $v)
			{
				$_POST[$n] = $v;
				$_REQUEST[$n] = $v;
				$app->input->post->set($n, $v);
				$app->input->set($n, $v);
			}
			
			/*foreach($_GET as $var => $val) {
				if ( !isset($_POST[$var]) ) JFactory::getApplication()->enqueueMessage( "GET variable: ".$var . " is not set in the POST ARRAY", 'message');
			}*/
			
			if (JDEBUG) JFactory::getApplication()->enqueueMessage(
				"Form data were serialized, ".
				'<b class="label">PHP max_input_vars</b> <span class="badge badge-info">'.ini_get('max_input_vars').'</span> '.
				'<b class="label">Estimated / Actual FORM variables</b>'.
				'<span class="badge badge-warning">'.$_POST['fcdata_serialized_count'].'</span> / <span class="badge">'.$total_vars.'</span> ',
				'message'
			);
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
		$app = JFactory::getApplication();

		$username  = $app->input->get('fcu', null);
		$password  = $app->input->get('fcp', null);

		jimport('joomla.user.helper');
		
		$db = JFactory::getDbo();
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
			$result = $app->triggerEvent($loginEvent, array((array)$response,$options));
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
		
		$app     = JFactory::getApplication();
		$session = JFactory::getSession();
		
		// Count an item or category hit if appropriate
		if ( $app->isSite() ) $this->countHit();
		
		// CSS CLASSES for body TAG
		if ( $app->isSite() )
		{
			$start_microtime = microtime(true);
			$css = array();

			$view = $app->input->get('view', '', 'cmd');

			if ($view=='item')
			{
				if ($id = $app->input->get('id', 0, 'int'))            $css[] = "item-id-".$id;  // Item's id
				if ($cid = $app->input->get('cid', 0, 'int'))          $css[] = "item-catid-".$cid;  // Item's category id
				if ($id)
				{
					$db = JFactory::getDbo();
					$query 	= 'SELECT t.id, t.alias'
						. ' FROM #__flexicontent_items_ext AS e'
						. ' JOIN #__flexicontent_types AS t ON e.type_id = t.id'
						. ' WHERE e.item_id='.(int)$id
						;
					$db->setQuery( $query );
					$type = $db->loadObject();
					if ($type)
					{
						$css[] = "type-id-".$type->id;        // Type's id
						$css[] = "type-alias-".$type->alias;  // Type's alias
					}
				}
			}
			
			else if ($view=='category')
			{
				if ($cid = $app->input->get('cid', 0, 'int'))            $css[] = "catid-".$cid;  // Category id
				if ($authorid = $app->input->get('authorid', 0, 'int'))  $css[] = "authorid-".$authorid; // Author id
				if ($tagid = $app->input->get('tagid', 0, 'int'))        $css[] = "tagid-".$tagid;  // Tag id
				if ($layout = $app->input->get('layout', '', 'cmd'))     $css[] = "cat-layout-".$layout;   // Category 'layout': tags, favs, author, myitems, mcats
			}
			
			$html = JResponse::getBody();
			$html = preg_replace('#<body([^>]*)class="#', '<body\1class="'.implode(' ', $css).' ', $html, 1);  // limit to ONCE !!
			JResponse::setBody($html);
			$body_css_time = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		}
		
		// If this is reached we now that the code for setting screen cookie has been added
		if ($session->get('screenSizeCookieToBeAdded', 0, 'flexicontent'))
		{
			$session->set('screenSizeCookieTried', 1, 'flexicontent');
			$session->set('screenSizeCookieToBeAdded', 0, 'flexicontent');
		}
		
		// Add performance message at document's end
		global $fc_performance_msg;
		if ($fc_performance_msg) {
			$html = JResponse::getBody();
			$inline_js_close_btn = !FLEXI_J30GE ? 'onclick="this.parentNode.parentNode.removeChild(this.parentNode);"' : '';
			$inline_css_close_btn = !FLEXI_J30GE ? 'float:right; display:block; font-size:18px; cursor: pointer;' : '';
			$_replace_ = strpos($html, '<!-- fc_perf -->') ? '<!-- fc_perf -->' : '</body>';
			$html = str_replace($_replace_,
				'<div id="fc_perf_box" class="fc-mssg fc-info">'.
					'<a class="close" data-dismiss="alert" '.$inline_js_close_btn.' style="'.$inline_css_close_btn.'" >&#215;</a>'.
					(!empty($body_css_time) ? sprintf('** [Flexisystem PLG: Adding css classes to BODY: %.3f s]<br/>', $body_css_time/1000000) : '').
					$fc_performance_msg.
				'</div>'."\n".$_replace_, $html
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
	public function onBeforeCompileHead()
	{
		if (!JFactory::getApplication()->isAdmin()) return;

		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'permission.php');
		$perms = FlexicontentHelperPerm::getPerm();
		JFactory::getDocument()->addScriptDeclaration("
			jQuery(document).ready(function(){
				".(!$perms->CanReviews ? 'jQuery(\'#menu-com-flexicontent a[href="index.php?option=com_flexicontent&view=reviews"]\').parent().remove();' : '')."
				".(!$perms->CanCats    ? 'jQuery(\'#menu-com-flexicontent a[href="index.php?option=com_flexicontent&view=categories"]\').parent().remove();' : '')."
				".(!$perms->CanTypes   ? 'jQuery(\'#menu-com-flexicontent a[href="index.php?option=com_flexicontent&view=types"]\').parent().remove();' : '')."
				".(!$perms->CanFields  ? 'jQuery(\'#menu-com-flexicontent a[href="index.php?option=com_flexicontent&view=fields"]\').parent().remove();' : '')."
				".(!$perms->CanTags    ? 'jQuery(\'#menu-com-flexicontent a[href="index.php?option=com_flexicontent&view=tags"]\').parent().remove();' : '')."
				".(!$perms->CanTemplates ? 'jQuery(\'#menu-com-flexicontent a[href="index.php?option=com_flexicontent&view=templates"]\').parent().remove();' : '')."
				".(!$perms->CanAuthors ? 'jQuery(\'#menu-com-flexicontent a[href="index.php?option=com_flexicontent&view=users"]\').parent().remove();' : '')."
				".(!$perms->CanGroups  ? 'jQuery(\'#menu-com-flexicontent a[href="index.php?option=com_flexicontent&view=groups"]\').parent().remove();' : '')."
				".(!$perms->CanFiles   ? 'jQuery(\'#menu-com-flexicontent a[href="index.php?option=com_flexicontent&view=filemanager"]\').parent().remove();' : '')."
				".(!$perms->CanImport  ? 'jQuery(\'#menu-com-flexicontent a[href="index.php?option=com_flexicontent&view=import"]\').parent().remove();' : '')."
				".(!$perms->CanStats   ? 'jQuery(\'#menu-com-flexicontent a[href="index.php?option=com_flexicontent&view=stats"]\').parent().remove();' : '')."
				".(!$perms->CanConfig  ? 'jQuery(\'#menu-com-flexicontent a[href="index.php?option=com_config&view=component&component=com_flexicontent"]\').parent().remove();' : '')."
			});
		");
	}


	public function set_cache_control()
	{
		$app = JFactory::getApplication();

		$option = $app->input->get('option', '', 'cmd');
		$browser_cachable = $app->input->get('browser_cachable', null);

		if ($option==$this->extension && $browser_cachable!==null)
		{
			// Use 1/4 of Joomla cache time for the browser caching
			$cachetime = (int) JFactory::getConfig()->get('cachetime', 15);
			$cachetime = $cachetime > 60 ? 60 : ($cachetime < 15 ? 15 : $cachetime);
			$cachetime = $cachetime * 60;
			
			// Try to avoid browser warning message "Page has expired or similar"
			// This should turning off the 'must-revalidate' directive in the 'Cache-Control' header
			JResponse::allowCache($browser_cachable ? true : false);
			JResponse::setHeader('Pragma', $browser_cachable ? '' :'no-cache');
			
			// CONTROL INTERMEDIARY CACHES (PROXY, ETC)
			// 1:  public content (unlogged user),   2:  private content (logged user)
			// BUT WE FORCE 'private' to avoid problems with 3rd party plugins and modules, that do cookie-based per visitor content for guests (unlogged users)
			$cacheControl  = 'private';  // $browser_cachable == 1 ? 'public' : 'private';
			
			// SET MAX-AGE, to allow modern browsers to cache the page, despite expires header in the past
			$cacheControl .= ', max-age=300';
			JResponse::setHeader('Cache-Control', $cacheControl );
			
			// Make sure no legacy proxies any caching !
			JResponse::setHeader('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');
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
				var path = "'.JUri::base(true).'";
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
	function checkinRecords()
	{
		$limit_checkout_hours   = $this->params->get('limit_checkout_hours', 1);
		$checkin_on_session_end = $this->params->get('checkin_on_session_end', 1);
		if (!$limit_checkout_hours && !$checkin_on_session_end) return true;

		// Get last execution time from cache
		$cache = JFactory::getCache('plg_'.$this->_name.'_'.__FUNCTION__);
		$cache->setCaching(1);      // Force cache ON
		$cache->setLifeTime(3600);  // Set expire time (default is 1 hour)
		$last_check_time = $cache->get(array($this, '_getLastCheckTime'), array(__FUNCTION__) );

		// Execute every 15 minutes
		$elapsed_time = time() - $last_check_time;  //JFactory::getApplication()->enqueueMessage('plg_'.$this->_name.'::'.__FUNCTION__.'() elapsed_time: ' . $elapsed_time . '<br/>');
		if ($elapsed_time < 15*60) return;  //JFactory::getApplication()->enqueueMessage('EXECUTING: '.'plg_'.$this->_name.'::'.__FUNCTION__.'()<br/>');

		// Clear cache and call method again to restart the counter
		$cache->clean('plg_'.$this->_name.'_'.__FUNCTION__);
		$last_check_time = $cache->get(array($this, '_getLastCheckTime'), array(__FUNCTION__) );

		$db  = JFactory::getDbo();
		$app = JFactory::getApplication();

		$max_checkout_hours = $this->params->get('max_checkout_hours', 24);
		$max_checkout_secs  = $max_checkout_hours * 3600;

		// Get current seconds
		$date = JFactory::getDate('now');
		$tz	= new DateTimeZone($app->getCfg('offset'));
		$date->setTimezone($tz);
		$current_time_secs = $date->toUnix();
		//echo $date->toFormat()." <br>";

		if ($checkin_on_session_end)
		{
			$query = 'SELECT DISTINCT userid FROM #__session WHERE guest=0';
			$db->setQuery($query);
			$logged = $db->loadColumn();
			$logged = array_flip($logged);
		}
		// echo "Logged users:<br>"; print_r($logged); echo "<br><br>";

		$tablenames = array('content', 'categories', 'modules', 'menu');
		foreach ( $tablenames as $tablename )
		{
			//echo $tablename.":<br>";

			// Get checked out records
			$query = 'SELECT id, checked_out, checked_out_time FROM #__'.$tablename.' WHERE checked_out > 0';
			$db->setQuery($query);
			$records = $db->loadObjectList();

			if ( !count($records) ) continue;
			$tz	= new DateTimeZone($app->getCfg('offset'));

			// Identify records that should be checked-in
			$checkin_records = array();
			foreach ($records as $record)
			{
				// Check user session ended
				if ( $checkin_on_session_end && !isset($logged[$record->checked_out]) )
				{
					//echo "USER session ended for: ".$record->checked_out." check-in record: ".$tablename.": ".$record->id."<br>";
					$checkin_records[] = $record->id;
					continue;
				}

				// Check maximum checkout time
				if ( $limit_checkout_hours)
				{
					$date = JFactory::getDate($record->checked_out_time);
					$date->setTimezone($tz);
					$checkout_time_secs = $date->toUnix();
					//echo $date->toFormat()." <br>";
					
					$checkout_secs = $current_time_secs - $checkout_time_secs;
					if ( $checkout_secs >= $max_checkout_secs )
					{
						//echo "Check-in table record: ".$tablename.": ".$record->id.". Check-out time of ".$checkout_secs." secs exceeds maximum of ".$max_checkout_secs." secs, by user: ".$record->checked_out."<br>";
						$checkin_records[] = $record->id;
					}
				}
			}
			$checkin_records = array_unique($checkin_records);

			// Check-in the records
			if ( count($checkin_records) )
			{
				$query = 'UPDATE #__'.$tablename.' SET checked_out = 0 WHERE id IN ('.  implode(",", $checkin_records)  .')';
				$db->setQuery($query);
				$db->execute();
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
	function handleExpiredItems()
	{
		$archive_on_publish_down = $this->params->get('archive_on_publish_down', 0);
		if (!$archive_on_publish_down) return true;

		// Get last execution time from cache
		$cache = JFactory::getCache('plg_'.$this->_name.'_'.__FUNCTION__);
		$cache->setCaching(1);      // Force cache ON
		$cache->setLifeTime(3600);  // Set expire time (default is 1 hour)
		$last_check_time = $cache->get(array($this, '_getLastCheckTime'), array(__FUNCTION__) );

		// Execute every 15 minutes
		$elapsed_time = time() - $last_check_time;  //JFactory::getApplication()->enqueueMessage('plg_'.$this->_name.'::'.__FUNCTION__.'() elapsed_time: ' . $elapsed_time . '<br/>');
		if ($elapsed_time < 15*60) return;  //JFactory::getApplication()->enqueueMessage('EXECUTING: '.'plg_'.$this->_name.'::'.__FUNCTION__.'()<br/>');

		// Clear cache and call method again to restart the counter
		$cache->clean('plg_'.$this->_name.'_'.__FUNCTION__);
		$last_check_time = $cache->get(array($this, '_getLastCheckTime'), array(__FUNCTION__) );

		$db  = JFactory::getDbo();
		$app = JFactory::getApplication();
		
		// Get current seconds
		$date = JFactory::getDate('now');
		$tz	= new DateTimeZone($app->getCfg('offset'));
		$date->setTimezone($tz);
		$current_time_secs = $date->toUnix();
		//echo $date->toFormat()." <br>";

		$clear_publish_down_date = $this->params->get('clear_publish_down_date', 1);
		$new_state = $archive_on_publish_down==1 ? 2 : 0;

		$_nowDate = 'UTC_TIMESTAMP()';
		$nullDate	= $db->getNullDate();
		
		$query = 'UPDATE #__content SET state = '.$new_state.
			($clear_publish_down_date ? ', publish_down = '.$db->Quote($nullDate) : '').
			' WHERE publish_down != '.$db->Quote($nullDate).' AND publish_down <= '.$_nowDate;
		//echo $query;
		$db->setQuery($query);
		$db->execute();
		
		$query = 'UPDATE #__flexicontent_items_tmp SET state = '.$new_state.
			($clear_publish_down_date ? ', publish_down = '.$db->Quote($nullDate) : '').
			' WHERE publish_down != '.$db->Quote($nullDate).' AND publish_down <= '.$_nowDate;
		//echo $query;
		$db->setQuery($query);
		$db->execute();
	}
	
	
	/* Increment item / category hits counters, according to configuration */
	function countHit()
	{
		$app    = JFactory::getApplication();
		$option = $app->input->get('option', '', 'cmd');
		$view   = $app->input->get('view', '', 'cmd');

		if ($option==$this->extension && $view=='item')
		{
			$item_id = $app->input->get('id', 0, 'int');
			if ( $item_id && $this->count_new_hit($item_id) )
			{
				$db = JFactory::getDbo();
				$db->setQuery('UPDATE #__content SET hits=hits+1 WHERE id = '.$item_id );
				$db->execute();
				$db->setQuery('UPDATE #__flexicontent_items_tmp SET hits=hits+1 WHERE id = '.$item_id );
				$db->execute();
			}
		}

		else if ($option=='com_content' && $view=='article')
		{
			// Always increment if non FLEXIcontent view
			$item_id = $app->input->get('id', 0, 'int');
			if ( $item_id )
			{
				$db = JFactory::getDbo();
				$db->setQuery('
					UPDATE #__flexicontent_items_tmp AS t
					JOIN #__content AS i ON i.id=t.id
					SET t.hits=i.hits
					WHERE t.id = '.$item_id
				);
				$db->execute();
			}
		}

		else if ($option==$this->extension &&  $view=='category')
		{
			$cat_id = $app->input->get('cid', 0, 'int');
			$layout = $app->input->get('layout', '', 'cmd');

			if ($cat_id && empty($layout))
			{
				$hit_accounted = false;
				$hit_arr = array();
				$session = JFactory::getSession();

				if ($session->has('cats_hit', 'flexicontent'))
				{
					$hit_arr = $session->get('cats_hit', array(), 'flexicontent');
					$hit_accounted = isset($hit_arr[$cat_id]);
				}

				// Add hit to session hit array
				if (!$hit_accounted)
				{
					$hit_arr[$cat_id] = $timestamp = time();  // Current time as seconds since Unix epoc;
					$session->set('cats_hit', $hit_arr, 'flexicontent');
					$db = JFactory::getDbo();
					$db->setQuery('UPDATE #__categories SET hits=hits+1 WHERE id = '.$cat_id );
					$db->execute();
				}
			}
		}
	}
	
	
	/* Decide about incrementing item / category hits counter according to configuration */
	function count_new_hit($item_id) // If needed to modify params then clone them !! ??
	{
		if (!$this->cparams->get('hits_count_unique', 0)) return 1; // Counting unique hits not enabled

		$db = JFactory::getDbo();
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
			$result = $db->execute();
			if ($db->getErrorNum()) {
				$query_create = "CREATE TABLE #__flexicontent_hits_log (item_id INT PRIMARY KEY, timestamp INT NOT NULL, ip VARCHAR(16) NOT NULL DEFAULT '0.0.0.0')";
				$db->setQuery($query_create);
				$result = $db->execute();
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
				$result = $db->execute();
				if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
				return 1;  // last visit not found or is beyond time limit, count a new hit
			}
		}

		// Last visit within time limit, do not count new hit
		return 0;
	}
	
	
	/*
	 * Function to restore serialized form data with:  JSON.stringify( jform.serializeArray() )
	 * This is currently UNUSED, because we use an alternative without eval ...
	 */
	private function parse_json_decode_eval($string, & $count)
	{
		$parsed = array();    // Decompressed data to be returned
		
		$pairs = json_decode($string, true);
		$count = count($pairs);
		//echo "<pre>"; print_r($pairs); exit;
		
		foreach ($pairs as $pair)
		{
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
				// Final create the assignment to be evaluated:  $parsed['na']['me'] = 'value';
				eval('$parsed[\'' . $name . ' = ' . $value . "; \n");
			}
			
			// CASE name is not an array, a single variable assignment
			else {
				// Add double slashes to index name
				$name = str_replace('\'', '\\\'', $name);
				// Finally quote the name, thus treating index as string and create assignment to be evaluated: $parsed['name'] = 'value';
				eval('$parsed[\'' . $name . '\'] = ' . $value . "; \n");
			}
		}
		//echo "<pre>"; print_r($parsed);  echo "</pre>"; exit;
		return $parsed;
	}
	
	
	/*
	 * Function to restore serialized form data with:  JSON.stringify( jform.serializeArray() )
	 */
	private function parse_json_decode($string, & $count)
	{
		$name_cnt = array();  // Empty index counters
		$parsed = array();    // Decompressed data to be returned
		
		$pairs = json_decode($string, true);
		$count = count($pairs);
		//echo "<pre>"; print_r($pairs); echo "</pre>";
		
		foreach ($pairs as $pair)
		{
			$name = $pair['name'];
			$value = $pair['value'];
			
			$name_cnt[$name] = isset($name_cnt[$name]) ? $name_cnt[$name] + 1 : 0;
			$indexes = preg_split('/[\[]+/', $name);
			
			$point = & $parsed;
			foreach($indexes as $n => &$index)
			{
				$index = trim($index, ']');
				$index = $index === '' ? (string) $name_cnt[$name] : $index;
				
				if ($n+1 == count($indexes))
				{
					break;
				}
				
				if ( !isset($point[$index]) )
				{
					$point[$index] = array();
				}
				
				$point = & $point[$index];
			}
			
			// Assign value and ... !! UNSET ARRAY REFERENCE, BEWARE !!
			//if ($value=='__SAVED__') $value .= 'test';
			$point[$index] = $value;
			unset($index);
		}
		
		//echo "<pre>"; print_r($parsed);  echo "</pre>"; exit;
		return $parsed;
	}
	
	
	
	function array_diff_recursive($arr1, $arr2)
	{
		$diff = array();
		
		foreach ($arr1 as $i => $v)
		{
			if (array_key_exists($i, $arr2))
			{
				if (is_array($v))
				{
					$diff_rec = $this->array_diff_recursive($v, $arr2[$i]);
					if (count($diff_rec)) $diff[$i] = $diff_rec;
				}
				
				else if ($v != $arr2[$i]) {
					$diff[$i] = $v;
				}
			}
			
			else {
				$diff[$i] = $v;
			}
		}
		
		return $diff;
	}
	
	
	
	
	// ***
	// *** Utility methods
	// ***
	
	// Function by decide type of user, currently unused since we used user access level instead of this function
	function getUserType()
	{
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
	
	
	/**
	 * Event method onExtensionBeforeSave
	 *
	 * @param   string  $context  Current context
	 * @param   JTable  $table    JTable instance
	 * @param   bool    $isNew    Flag to determine whether this is a new extension
	 *
	 * @return void
	 */
	public function onExtensionBeforeSave($context, $table, $isNew)
	{
		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();
		$option = $app->input->get('component', '', 'cmd');
		
		
		// ***
		// *** Handle syncing permissions between com_content and com_flexicontent assets
		// ***

		if ( $context=='com_config.component' && ($option == 'com_content' || $option == 'com_flexicontent') )
		{
			$rules_arr = @ $_POST['jform']['rules'];
			$option_other = $option == 'com_content'  ?  'com_flexicontent'  :  'com_content';
			
			// Only save permissions rules, if user is allowed to edit them
			// and if rules exists (in J3.5+ they are saved via AJAX thus code would normally be triggered only in J3.2 - J3.4)
			if ( $rules_arr!= null && $user->authorise('core.admin', $option) )
			{
				// Get asset of the other component
				$asset = JTable::getInstance('asset');
				if (!$asset->loadByName($option_other))
				{
					$root = JTable::getInstance('asset');
					$root->loadByName('root.1');
					$asset->name = $option_other;
					$asset->title = $option_other;
					$asset->setLocation($root->id, 'last-child');
				}
				
				// Get existing asset rules as an array
				$asset_rules = json_decode($asset->rules, true);
				
				// Copy rules, clearing empty ones
				foreach($rules_arr as $rule_name => $rule_data)
				{
					if ( $option_other=='com_content' && substr($rule_name, 0, 5) != 'core.' )
					{
						continue;
					}
					foreach($rule_data as $grp_id => $v)
					{
						if ( !strlen($v) ) unset($rules_arr[$rule_name][$grp_id]);
					}
					$asset_rules[$rule_name] = $rules_arr[$rule_name];
				}
				
				// If com_content configuration was saved then restore cleared *.own rules, and re-save com_content asset
				if ($option == 'com_content')
				{
					$com_content_asset = JTable::getInstance('asset');
					if ( $com_content_asset->loadByName('com_content') )
					{
						$com_content_rules = json_decode($com_content_asset->rules, true);
						$com_content_rules['core.delete.own'] = isset($asset_rules['core.delete.own']) ? $asset_rules['core.delete.own'] : '';
						$com_content_rules['core.edit.state.own'] = isset($asset_rules['core.edit.state.own']) ? $asset_rules['core.edit.state.own'] : '';
						$rules = new JAccessRules($com_content_rules);
						$com_content_asset->rules = (string) $rules;
						if (!$com_content_asset->check() || !$com_content_asset->store())
						{
							throw new RuntimeException($com_content_asset->getError());
						}
					}
				}
				
				// Save asset rules of the other component
				$rules = new JAccessRules($asset_rules);
				$asset->rules = (string) $rules;
				
				if (!$asset->check() || !$asset->store())
				{
					throw new RuntimeException($asset->getError());
				}
			}
		}


		// ***
		// *** Add custom LAYOUT parameters to non-FC components (cleared during their validation)
		// *** DONE modules, TODO: add support to menus,
		// *** NOTE: We do validate (filter) submitted values according to XML files of the layout file
		// ***

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
			
			// Attempt to parse the XML file
			$xml = simplexml_load_file($layoutpath);
			if (!$xml)
			{
				JFactory::getApplication()->enqueueMessage('Error parsing layout file of "'.$new_ilayout.'". Layout parameters were not saved', 'warning');
				return;
			}
			
			// Create form object loading the , (form name seems not to cause any problem)
			$jform = new JForm('com_flexicontent.layout', array('control' => 'jform', 'load_data' => false));
			$tmpl_params = $xml->asXML();
			$jform->load($tmpl_params);
			
			// Set cleared layout parameters
			$fset = 'params';
			$layout_post = array();
			$layout_post[$fset] = & $_POST['jform'][$fset];

			//foreach ($jform->getGroup($fset) as $field) { if ( !empty($field->getAttribute('filter')) ) echo $field->fieldname . $field->getAttribute('filter') . "<br/>"; } exit;

			// Filter and validate the resulting data
			$layout_post = $jform->filter($layout_post);   //echo "<pre>"; print_r($layout_post); echo "</pre>"; exit();
			$isValid = $jform->validate($layout_post, $fset);

			if (!$isValid)
			{
				JFactory::getApplication()->enqueueMessage('Error validating layout posted parameters. Layout parameters were not saved', 'error');
				return;
			}
			
			$params = new JRegistry($table->params);
			foreach ($jform->getGroup($fset) as $field)
			{
				$fieldname = $field->fieldname;
				if (substr($fieldname, 0, 2)=="__") continue;   // Skip field that start with __
				$value = isset($layout_post[$fset][$fieldname]) ? $layout_post[$fset][$fieldname] : null;
				$params->set($fieldname, $value);
			}
			
			// Set parameters back to module's DB table object
			$table->params = $params->toString();
		}
	}


	/**
	 * Event method onExtensionAfterSave
	 *
	 * @param   string  $context  Current context
	 * @param   JTable  $table    JTable instance
	 * @param   bool    $isNew    Flag to determine whether this is a new extension
	 *
	 * @return void
	 */
	public function onExtensionAfterSave($context, $table, $isNew)
	{
	}


	/**
	 * Prepare form.
	 *
	 * @param   JForm  $form  The form to be altered.
	 * @param   mixed  $data  The associated data for the form.
	 *
	 * @return  boolean
	 *
	 * @since	2.5
	 */
	public function onContentPrepareForm($form, $data)
	{
		// Check we have a form.
		if (!($form instanceof JForm))
		{
			$this->_subject->setError('JERROR_NOT_A_FORM');

			return false;
		}

		$app        = JFactory::getApplication();
		$document   = JFactory::getDocument();
		$user       = JFactory::getUser();

		// Check we are manipulating the languagecode plugin.
		if ($form->getName() !== 'com_content.article' || JFactory::getApplication()->input->get('option', '', 'CMD')==='com_flexicontent')
		{
			return true;
		}

		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
		require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.fields.php');
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.categories.php');
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'permission.php');
		JLoader::register('FlexicontentModelItem', JPATH_BASE.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'item.php');
		JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR);


		// ***
		// *** Load item and its fields and its type parameters
		// ***

		$cparams = JComponentHelper::getParams('com_flexicontent');
		$default_type_id = $cparams->get('jarticle_form_typeid', 1);

		// Get current type_id of the item
		if ($data->id)
		{
			$record = JTable::getInstance($type = 'flexicontent_items', $prefix = '', $config = array());
			$record->load($data->id);
		}
		$data->type_id = !empty($record) && $record->type_id ? $record->type_id : 0;

		// Get model and set default type if type not set already (new item or existing item with no type)
		$model = new FlexicontentModelItem();
		if (!$data->type_id)
		{
			$types = flexicontent_html::getTypesList($type_ids=false, $check_perms = true, $published=true);		
			$default_type = isset($types[$default_type_id])
				? $types[$default_type_id]
				: reset($types);
			$data->type_id = $default_type->id;
			$model->setId($data->id, $data->catid, $data->type_id);
		}

		// Get the item
		$item = $model->getItem($data->id, $check_view_access=false);

		// Get the item's fields
		$fields = $model->getExtrafields();
		$item->fields = & $fields;

		// Get type parameters
		$tparams = $model->getTypeparams();
		$tparams = new JRegistry($tparams);
		$item->tparams = & $tparams;

		// Set component + type as item parameters
		$item->params = new JRegistry();
		$item->params->merge($cparams);
		$item->params->merge($item->tparams);


		// ***
		// *** Load CSS files
		// ***
		
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::root(true).'/components/com_flexicontent/assets/css/flexi_form.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::root(true).'/components/com_flexicontent/assets/css/flexi_form_rtl.css', FLEXI_VHASH);

		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::root(true).'/components/com_flexicontent/assets/css/flexi_containers.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::root(true).'/components/com_flexicontent/assets/css/flexi_containers_rtl.css', FLEXI_VHASH);

		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::root(true).'/components/com_flexicontent/assets/css/flexi_shared.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::root(true).'/components/com_flexicontent/assets/css/flexi_shared_rtl.css', FLEXI_VHASH);

		// Fields common CSS
		$document->addStyleSheetVersion(JUri::root(true).'/components/com_flexicontent/assets/css/flexi_form_fields.css', FLEXI_VHASH);


		// ***
		// *** Load JS libraries
		// ***

		$has_J2S = JPluginHelper::isEnabled('content', 'j2store');
		if (!$has_J2S) foreach ($fields as $field)
		{
			$has_J2S = $has_J2S || $field->field_type == 'j2store';
			if ($has_J2S) break;
		}
		$_params = new JRegistry();
		$_params->set('load-ui-dialog', 1);
		$_params->set('load-ui-menu', $has_J2S ? 0 : 1);
		$_params->set('load-ui-autocomplete', $has_J2S ? 0 : 1);
		
		flexicontent_html::loadJQuery( $add_jquery = 1, $add_jquery_ui = 1, $add_jquery_ui_css = 1, $add_remote = 1, $_params);   //flexicontent_html::loadFramework('jQuery');
		flexicontent_html::loadFramework('select2');
		flexicontent_html::loadFramework('touch-punch');
		flexicontent_html::loadFramework('prettyCheckable');
		flexicontent_html::loadFramework('flexi-lib');
		flexicontent_html::loadFramework('flexi-lib-form');
		
		// Add js function to overload the joomla submitform validation
		JHtml::_('behavior.formvalidation');  // load default validation JS to make sure it is overriden
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);
		
		// Add js function for custom code used by FLEXIcontent item form
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/itemscreen.js', FLEXI_VHASH);


		// ***
		// *** Load field values from session (typically during a form reload after a servers-side form validation failure)
		// *** NOTE: Because of fieldgroup rendering other fields, this step must be done in seperate loop, placed before FIELD HTML creation
		// *** 

		$jcustom = $app->getUserState('com_flexicontent.edit.item.custom');
		foreach ($fields as $field)
		{
			if (!$field->iscore)
			{
				if ( isset($jcustom[$field->name]) )
				{
					$field->value = array();
					foreach ($jcustom[$field->name] as $i => $_val)  $field->value[$i] = $_val;
				}
			}
		}


		// ***
		// *** (a) Apply Content Type Customization to CORE fields (label, description, etc)
		// *** (b) Create the edit html of the CUSTOM fields by triggering 'onDisplayField'
		// ***

		foreach ($fields as $field)
		{
			FlexicontentFields::getFieldFormDisplay($field, $item, $user);
		}
		
		global $form_fcitem; // TODO remove this global
		$form_fcitem = $item;

		// Get flexicontent fields
		$form->load('
			<form>
				<fields name="attribs">
					<fieldset
						name="fcfields"
						label="' . ( $item->typename
							? JText::_('FLEXI_TYPE_NAME') . ' : ' . JText::_($item->typename)
							: JText::_('FLEXI_TYPE_NOT_DEFINED')
						) . '"
						description=""
						addfieldpath="/administrator/components/com_flexicontent/models/fields"
					>
						<field
							name="fcfields"
							type="fcfieldwrapper"
							description="' . htmlspecialchars('TEST desc', ENT_COMPAT, 'UTF-8') . '"
							translate_description="false"
							label="fcfields"
							translate_label="false"
							filter="cmd"
							item_id="' . (int) $data->id . '"
						/>
					</fieldset>
				</fields>
			</form>
		');

		return true;
	}


	function renderFields($context, &$row, &$params, $page=0, $eventName='')
	{
		// This is meant for Joomla article view
		if ( $context!='com_content.article' ) return;
		
		$app = JFactory::getApplication();
		if (
			$app->input->get('option', '', 'CMD')!='com_content' ||
			$app->input->get('view', '', 'CMD')!='article' ||
			$app->input->get('isflexicontent', false, 'CMD')
		) return;
		
		
		static $fields_added = array();
		static $items = array();
		if ( !empty($fields_added[$row->id]) ) return;
		
		
		static $init = null;
		if (!$init)
		{
			JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
			
			require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');
			require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.fields.php');
			require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');
			require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'permission.php');
			require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'item'.'.php');
		}
		
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		$aid = JAccess::getAuthorisedViewLevels($user->id);
		
		$model = new FlexicontentModelItem();
		if ( !isset($items[$row->id]) )
		{
			$items[$row->id] = $model->getItem($row->id, $check_view_access=false);
		}
		$item = $items[$row->id];
		if (!$item) return;  // Item retrieval failed avoid fatal error
		
		// Check placement and abort adding the fields
		$placements_arr = array(
			1=>'beforeContent',
			2=>'afterContent'
		);
		$allow_jview = $item->parameters->get('allow_jview', 0);
		$placement = $item->parameters->get('jview_fields_placement', 1);
		
		if ( $allow_jview != 1 || !$placement || !isset($placements_arr[$placement]) ) return;   //Disabled
		if ( $placements_arr[$placement] != $eventName ) return;  // Not current event
		
		$fields_added[$row->id] = true; // Only add once
		$view = 'com_content.article' ? 'item' : 'category';
		$items = FlexicontentFields::getFields($item, $view, $_item_params = null, $aid = null, $use_tmpl = false);  // $_item_params == null means only retrieve fields
		
		// Only Render custom fields
		$displayed_fields = array();
		foreach ($item->fields as $field)
		{
			if ($field->iscore) continue;
			
			$displayed_fields[$field->name] = $field;
			$values = isset($item->fieldvalues[$field->id]) ? $item->fieldvalues[$field->id] : array();
			FlexicontentFields::renderField($item, $field, $values, $method='display', $view);
		}
		
		if (!count($displayed_fields)) return null;
		
		// Render the list of groups
		$field_html = array();
		foreach($displayed_fields as $field_name => $field)
		{
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



	function onContentBeforeDisplay($context, &$row, &$params, $page=0)
	{
		return $this->renderFields($context, $row, $params, $page, 'beforeContent');
	}

	function onContentAfterDisplay($context, &$row, &$params, $page=0)
	{
		return $this->renderFields($context, $row, $params, $page, 'afterContent');
	}



	// AFTER LOGIN
	public function onUserAfterLogin($options)
	{
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		$db   = JFactory::getDbo();
		$jcookie = $app->input->cookie;

		// Set id for client-side (browser) caching via unique URLs (logged users)
		$jcookie->set( 'fc_uid', JUserHelper::getShortHashedUserAgent(), 0);

		// Add favourites via cookie to the DB
		$fcfavs = flexicontent_favs::getCookieFavs();

		$types = array('item' => 0, 'category' => 1);
		foreach($types as $type => $type_id)
		{
			$favs = $fcfavs && isset($fcfavs->$type) ? $fcfavs->$type : array();

			// Favourites via DB
			$query 	= 'SELECT DISTINCT itemid, 1 AS fav'
				. ' FROM #__flexicontent_favourites'
				. ' WHERE type = ' . $type_id . ' AND userid = ' . ((int)$user->id)
				;
			$db->setQuery($query);
			$favoured = $db->loadObjectList('itemid');

			// Collect ids favoured via Cookie but not already added as favoured via DB
			$item_ids = array();
			foreach($favs as $item_id)
			{
				if (!isset($favoured[$item_id]))
				{
					$item_ids[] = $item_id;
				}
			}

			// Add to DB
			$this->_addfavs($type, $item_ids, $user->id);
		}

		// Clear cookie
		$jcookie->set('fcfavs', '{}', 0);
	}



	// AFTER LOGOUT
	public function onUserAfterLogout($options)
	{
		$jcookie = JFactory::getApplication()->input->cookie;
		$jcookie->set( 'fc_uid', 'p', 0);
	}


	/**
	 * Before save event.
	 *
	 * @param   string   $context  The context
	 * @param   JTable   $item     The table
	 * @param   boolean  $isNew    Is new item
	 * @param   array    $data     The validated data
	 *
	 * @return  boolean
	 *
	 * @since   3.2.0
	 */
	public function onContentBeforeSave($context, $item, $isNew, $data = array())
	{
		if ($context != 'com_content.article' || JFactory::getApplication()->input->get('isflexicontent', false, 'CMD'))
		{
			return true;
		}

		//*** 
		//*** Maintain flexicontent-specific article parameters
		//*** 

		JLoader::register('FlexicontentModelItem', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'item.php');
		$model = new FlexicontentModelItem();

		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
		$record = JTable::getInstance($type = 'flexicontent_items', $prefix = '', $config = array());
		$record->load($item->id);

		$mergeProperties = array('attribs', 'metadata');
		$mergeOptions = array('params_fset' => 'attribs', 'layout_type' => 'item', 'model_names' => array('com_flexicontent' => 'item', 'com_content' => 'article'));
		$model->mergeAttributes($record, $data, $mergeProperties, $mergeOptions);

		$item_data = array();
		foreach($mergeProperties as $prop)
		{
			$item_data[$prop] = isset($record->$prop) ? $record->$prop : null;
		}
		JFactory::getSession()->set('flexicontent.item.data', $item_data, 'flexicontent');

		return true;
	}


	/**
	 * After save event.
	 *
	 * @param   string   $context  The context
	 * @param   JTable   $item     The table
	 * @param   boolean  $isNew    Is new item
	 * @param   array    $data     The validated data
	 *
	 * @return  boolean
	 *
	 * @since   3.2.0
	 */
	public function onContentAfterSave($context, $item, $isNew, $data = array())
	{
		if ($context != 'com_content.article' || JFactory::getApplication()->input->get('isflexicontent', false, 'CMD'))
		{
			return true;
		}


		//***
		//*** Call 'flexicontent' items model to update flexicontent item data: fields, version data, temporary data
		//***

		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
		require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.fields.php');
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.categories.php');
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'permission.php');
		JLoader::register('FlexicontentModelItem', JPATH_BASE.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'item.php');
		JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR);

		// Needed for new items, since now an item has been created
		$data['id'] = $isNew ? $item->id : $data['id'];
		// Approve new version by default, Note: this is just the default value , ACL will decide real value
		$data['vstate'] = 2;
		// RAW (flexicontent) Custom fields data, validation will be done by each field
		$data['custom'] = isset($_POST['custom']) ? $_POST['custom'] : array();

		$model = new FlexicontentModelItem();
		$model->store($data);

		// Revert changes to data
		unset($data['vstate']);
		unset($data['custom']);
		$data['id'] = $isNew ? 0 : $data['id'];  // restore ID to zero for new items


		//***
		//*** Call backend 'flexicontent' items model to update flexicontent temporary data
		//***

		JLoader::register('FlexicontentModelItems', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'items.php');
		$items_model = new FlexicontentModelItems();
		$items_model->updateItemCountingData(array($item));


		//***
		//*** Maintain flexicontent-specific article parameters
		//***

		$item_params = JFactory::getSession()->get('flexicontent.item.data', null, 'flexicontent');
		if ($item_params)
		{
			JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
			$record = JTable::getInstance($type = 'flexicontent_items', $prefix = '', $config = array());
			$record->load($item->id);
			$record->bind($item_params);
			$record->store();
		}

		return true;
	}



	// ***
	// *** UTILITY FUNCTIONS
	// ***

	public function _getLastCheckTime($workname = '')
	{
		return time();
	}


	private function _addfavs($type, $item_ids, $user_id)
	{
		$db = JFactory::getDbo();
		
		if (!is_array($item_ids))
		{
			$obj = new stdClass();
			$obj->itemid = (int)$item_ids;
			$obj->userid = (int)$user_id;
			$obj->type   = (int)$type;

			return $db->insertObject('#__flexicontent_favourites', $obj);
		}
		else if (!empty($item_ids))
		{
			$vals = array();
			foreach($item_ids as $item_id) $vals[]= ''
				. '('
				. ((int)$item_id)  . ', '
				. ((int)$user_id)  . ', '
				. ((int)$type)
				. ')';
			$query = 'INSERT INTO #__flexicontent_favourites'
				. ' (itemid, userid, type) VALUES ' . implode($vals, ',');
			$db->setQuery($query);
			try {
				$db->execute();
			}
			catch (Exception $e) {
				JError::raiseWarning( 500, $e->getMessage() );
				return false;
			}
		}
	}
}