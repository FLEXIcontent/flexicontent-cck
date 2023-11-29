<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Registry\Registry;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Filesystem\Path;
use Joomla\CMS\Table\Asset;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Cache\Cache;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\User\UserHelper;
use Joomla\CMS\Access\Rules;
use Joomla\CMS\Router\Route;

if (!defined('DS'))  define('DS', DIRECTORY_SEPARATOR);
require_once (JPATH_ADMINISTRATOR.'/components/com_flexicontent/defineconstants.php');
JLoader::register('JHtmlFclayoutbuilder', JPATH_ROOT . '/components/com_flexicontent/helpers/html/fclayoutbuilder.php');

/**
 * Example system plugin
 */
class plgSystemFlexisystem extends CMSPlugin
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

		if (!$language_loaded)
		{
			Factory::getApplication()->isClient('administrator')
				? CMSPlugin::loadLanguage('plg_system_flexisystem_common_be', JPATH_ADMINISTRATOR)
				: CMSPlugin::loadLanguage('plg_system_flexisystem_common_fe', JPATH_ADMINISTRATOR);
		}

		if (!$this->autoloadLanguage && $language_loaded === null)
		{
			$language_loaded = CMSPlugin::loadLanguage('plg_system_flexisystem', JPATH_ADMINISTRATOR);
		}

		$this->extension = 'com_flexicontent';
		$this->cparams = ComponentHelper::getParams($this->extension);

		// Temporary workaround until code is updated
		if (FLEXI_J40GE)
		{
			Factory::getDbo()->setQuery(
				"SET sql_mode=(SELECT REPLACE(REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''),'STRICT_TRANS_TABLES',''))"
			)->execute();
		}
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
		if (Factory::getApplication()->isClient('administrator')) $this->handleSerialized();

		$app  = Factory::getApplication();
		$task = $app->input->get('task', '', 'string');  // NOTE during this event 'task' is (controller.task), thus we use filtering 'string'

		/**
		 * Call 'flexicontent' items model to update the featured FLAG, thus updating temporary data too
		 */

		if ($task === 'articles.featured' || $task === 'articles.unfeatured')
		{
			$this->_loadFcHelpersAndLanguage();

			// Load the FLEXIcontent item
			$cid = $app->input->get('cid', array(), 'array');
			$cid = (int) reset($cid);

			// Update featured flag (model will also handle cache cleaning)
			$itemmodel = new FlexicontentModelItem();
			$itemmodel->featured($cid, $task === 'articles.featured' ? 1 : 0);
		}

		/**
		 * Call joomla configuration model to update to sync the permission between com_flexicontent and com_content
		 */

		elseif ($task === 'config.store')
		{
			$comp = $app->input->get('comp', '', 'cmd');
			$comp = str_replace('com_flexicontent.category.', 'com_content.category.', $comp);
			$comp = str_replace('com_flexicontent.item.', 'com_content.article.', $comp);
			$app->input->set('comp', $comp);

			if ($comp === 'com_content' || $comp === 'com_flexicontent')
			{
				$skip_arr = array('core.admin'=>1, 'core.options'=>1, 'core.manage'=>1);
				$action = $app->input->get('action');

				if (substr($action, 0, 5) == 'core.' && !isset($skip_arr[$action]))
				{
					$comp_other = $comp == 'com_content'  ?  'com_flexicontent'  :  'com_content';
					$permissions = array(
						'component' => $comp_other,
						'action'    => $app->input->get('action', '', 'cmd'),
						'rule'      => $app->input->get('rule', '', 'cmd'),
						'value'     => $app->input->get('value', '', 'cmd'),
						'title'     => $app->input->get('title', '', 'string')
					);

					JLoader::register('ConfigModelApplication', JPATH_ADMINISTRATOR.'/components/com_config/model/application.php');
					JLoader::register('ConfigModelForm', JPATH_SITE.'/components/com_config/model/form.php');
					JLoader::register('ConfigModelCms', JPATH_SITE.'/components/com_config/model/cms.php');

					if (!(substr($permissions['component'], -6) === '.false'))
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
		$session = Factory::getSession();


		// Clear categories cache if previous page has saved FC component configuration
		if ( $session->get('clear_cats_cache', 0, 'flexicontent') )
		{
			$session->set('clear_cats_cache', 0, 'flexicontent');

			// Clean cache
			$cache = $this->getCache($group='', 0);
			$cache->clean('com_flexicontent');

			$cache = $this->getCache($group='', 1);
			$cache->clean('com_flexicontent');

			$cache = $this->getCache($group='', 0);
			$cache->clean('com_flexicontent_cats');

			$cache = $this->getCache($group='', 1);
			$cache->clean('com_flexicontent_cats');

			//Factory::getApplication()->enqueueMessage( "Cleaned CACHE groups: <b>com_flexicontent</b>, <b>com_flexicontent_cats</b>", 'message');
		}

		if (FLEXI_SECTION || FLEXI_CAT_EXTENSION)
		{
			global $globalcats;
			$start_microtime = microtime(true);
			if (FLEXI_CACHE)
			{
				// Add the category tree to categories cache
				$catscache = Factory::getCache('com_flexicontent_cats');
				$catscache->setCaching(1);                  // Force cache ON
				$catscache->setLifeTime(FLEXI_CACHE_TIME);  // Set expire time (default is 1 hour)
				$globalcats = $catscache->get(array($this, 'getCategoriesTree'), array());
			} else {
				$globalcats = $this->getCategoriesTree();
			}
			$time_passed = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			//Factory::getApplication()->enqueueMessage( "recalculated categories data, execution time: ". sprintf('%.2f s', $time_passed/1000000), 'message');
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

		// (a.3) (Auto) Transfer files to external servers
		$this->handleFileTranfers();

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
				Factory::getApplication()->enqueueMessage('PDF generation is not supported, the HTML version is displayed instead', 'notice');
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

		$hasTemplates = Factory::getUser()->authorise('core.admin', 'com_templates');
		if (!$hasTemplates)
		{
			unset($_POST['jform']['params']['php_rule']);
			unset($_REQUEST['jform']['params']['php_rule']);
		}

		$format = Factory::getApplication()->input->get('format', 'html', 'cmd');
		if ($format != 'html') return;

		$app      = Factory::getApplication();
		$session  = Factory::getSession();
		$document = Factory::getDocument();

		$option = $app->input->get('option', '', 'cmd');
		$view   = $app->input->get('view', '', 'cmd');
		$controller = $app->input->get('controller', '', 'cmd');
		$component  = $app->input->get('component', '', 'cmd');

		$layout = $app->input->get('layout', '', 'string');
		$tmpl   = $app->input->get('tmpl', '', 'string');
		$task   = $app->input->get('task', '', 'string');  // NOTE during this event 'task' is (controller.task), thus we use filtering 'string'

		$fcdebug = $this->cparams->get('print_logging_info')==2  ?  2  :  $session->get('fcdebug', 0, 'flexicontent');
		$isAdmin = Factory::getApplication()->isClient('administrator');

		$isFC_Config = $isAdmin ? ($option=='com_config' && ($view == 'component' || $controller =='component') && $component == 'com_flexicontent')  :  false;
		$isBE_Module_Edit = $isAdmin ? (($option =='com_modules' || $option =='com_advancedmodules') && $view == 'module')  :  false;
		$isBE_Menu_Edit   = $isAdmin ? ($option =='com_menus' && $view == 'item' && $layout =='edit')  :  false;

		$js = '';

		if ( $isBE_Module_Edit || $isBE_Menu_Edit )
		{
			Factory::getLanguage()->load($this->extension, JPATH_ADMINISTRATOR, 'en-GB'	, $_force_reload = false);
			Factory::getLanguage()->load($this->extension, JPATH_ADMINISTRATOR, null		, $_force_reload = false);
		}

		if (
			$isAdmin && ($isFC_Config || $isBE_Module_Edit) || ($option=='com_flexicontent' && ($isAdmin || $task == 'edit'))  // frontend task does not include 'controller.'
		) {
			// WORKAROUNDs for slow chosen JS in component configuration form
			if ($isFC_Config)
			{
				// Make sure chosen JS file is loaded before our code, but do not attach it to any elements (YET)
				if (!FLEXI_J40GE)
				{
					// Do not run this in J4 , JDocument not yet available, but chosen JS was replaced
					HTMLHelper::_('formbehavior.chosen', '#_some_iiidddd_');
				}
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
		if ($this->cparams->get('use_mobile_layouts') || $app->isClient('administrator'))
		{
			$this->detectClientResolution($this->cparams);
		}

		// Redirect backend article / category management, and frontend article view
		$app->isClient('administrator')
			? $this->redirectAdminComContent()
			: $this->redirectSiteComContent();
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
		$app   = Factory::getApplication();
		$user  = Factory::getUser();
		$option = $app->input->get('option', '', 'cmd');

		// Skip other components
		if (empty($option) || ($option !== 'com_content'  && $option !== 'com_categories'))
		{
			return;
		}

		// Get request variables used to determine whether to apply redirection
		$view   = $app->input->get('view', '', 'cmd');
		$task   = $app->input->get('task', '', 'string');  // NOTE during this event 'task' is (controller.task), thus we use filtering 'string'
		$layout = $app->input->get('layout', '', 'cmd');

		/**
		 * Exclude 'pagebreak' outputing dialog and Joomla article / category selection from a modal e.g. from a menu item, or from an editor
		 */
		if ($layout === 'pagebreak' || $layout === 'modal')
		{
			return;
		}

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
		$excluded_urls = $this->params->get('excluded_redirect_urls', '');
		$excluded_urls = preg_split("/[\s]*%%[\s]*/", $excluded_urls);

		if (empty($excluded_urls[count($excluded_urls)-1]))
		{
			unset($excluded_urls[count($excluded_urls)-1]);
		}

		// Get current URL
		$uri = Uri::getInstance();


		// First check excluded urls
		foreach ($excluded_urls as $excluded_url)
		{
			$quoted = preg_quote($excluded_url, "#");
			if (preg_match("#$quoted#", $uri))
			{
				return;
			}
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
			$app->redirect($redirectURL);
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
		$app    = Factory::getApplication();
		$db     = Factory::getDbo();

		$option = $app->input->get('option', '', 'cmd');
		$view   = $app->input->get('view', '', 'cmd');
		$task   = $app->input->get('task', '', 'cmd');


		//***
		//*** Let's Redirect/Reroute Joomla's article view & form to FLEXIcontent item view & form respectively !!
		//*** NOTE: we do not redirect / reroute Joomla's category views (blog, list, featured etc), thus site administrator can still utilize them
		//***

		$check_redirect = $option === 'com_content' && !$task && (
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


		// Allow new article form if so configured
		if (!$id && $view !== 'item')
		{
			if ($this->cparams->get('jarticle_allow_new_form', 1))
			{
				return;
			}
		}

		// Get article category id, if it is not already in url
		$catid = $app->input->get('catid', 0, 'int');


		//***
		//*** First Check if within 'FLEXIcontent' category subtree
		//***

		if ($catid)
		{
			$db->setQuery('SELECT lft, rgt FROM #__categories WHERE id = ' . $catid);
			$cat_info = $db->loadObject();

			if ($cat_info)
			{
				$cat_lft = $cat_info->lft;
				$cat_rgt = $cat_info->rgt;
			}

			elseif ($id)
			{
				$db->setQuery('SELECT catid FROM #__content WHERE id = ' . $id);
				$main_catid = $db->loadResult();

				$db->setQuery('SELECT lft, rgt FROM #__categories WHERE id = ' . $main_catid);
				$cat_info = $db->loadObject();

				if ($cat_info)
				{
					$cat_lft = $cat_info->lft;
					$cat_rgt = $cat_info->rgt;
					$catid = $main_catid;
				}
				else
				{
					$catid = 0;
				}
			}
		}

		$in_limits = !$catid || ($cat_lft >= FLEXI_LFT_CATEGORY && $cat_rgt <= FLEXI_RGT_CATEGORY);


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
				$type_params = new Registry($type_params);
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
				FLEXI_J40GE
					? require_once (JPATH_SITE.'/components/com_content/src/Helper/RouteHelper.php')
					: require_once (JPATH_SITE.'/components/com_content/helpers/route.php');
				require_once (JPATH_SITE.'/components/com_flexicontent/helpers/route.php');

				$itemslug	= $app->input->get('id', '', 'string');
				$catslug	= $app->input->get('catid', '', 'string');

				// Warning current menu item id must not be passed to the routing functions since it points to com_content, and thus it will break FC SEF URLs
				$urlItem 	= $catslug ? FlexicontentHelperRoute::getItemRoute($itemslug, $catslug) : FlexicontentHelperRoute::getItemRoute($itemslug);
				$urlItem 	= Route::_($urlItem);
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
		$db = Factory::getDbo();
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
			. ' JOIN #__flexicontent_cats_item_relations AS rel ON c.id=rel.catid'
			. ' JOIN #__content AS i ON rel.itemid=i.id '
			. '  AND i.state IN (1,-5) '
			. '  AND ( i.publish_up IS NULL OR i.publish_up = ' . $db->Quote($nullDate) . ' OR i.publish_up <= ' . $_nowDate . ' )'
			. '  AND ( i.publish_down IS NULL OR i.publish_down = ' . $db->Quote($nullDate) . ' OR i.publish_down >= ' . $_nowDate . ' )'
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

		foreach ($globalcats as $cat)
		{
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
    * Sorts and pads (indents) given categories according to their parent, thus creating a category tree by using recursion.
    * The sorting of categories is done by:
    * a. looping through all categories  v  in given children array padding all of category v with same padding
    * b. but for every category v that has a children array, it calling itself (recursion) in order to inject the children categories just bellow category v
    *
    * This function is based on the joomla 1.0 treerecurse
    *
    * @access private
    * @return array
    */
	static private function _getCatAncestors( $parent_id, $indent, $list, &$children, $title, $maxlevel=9999, $level=0, $type=1, $ancestors=null )
	{
		$ROOT_CATEGORY_ID = 1;
		if (!$ancestors) $ancestors = array();

		if (!empty($children[$parent_id]) && $level <= $maxlevel)
		{
			foreach ($children[$parent_id] as $v)
			{
				$id = $v->id;

				if ((!in_array($v->parent_id, $ancestors)) && $v->parent_id != $ROOT_CATEGORY_ID)
				{
					$ancestors[] = $v->parent_id;
				}

				// Top level category (a child of ROOT)
				if ($v->parent_id==1)
				{
					$pre    = '';
					$spacer = ' . ';
				}
				elseif ($type)
				{
					$pre    = '<sup>|_</sup> ';
					$spacer = ' . ';
				}
				else
				{
					$pre    = '- ';
					$spacer = ' . ';
				}

				if ($title)
				{
					$txt = $v->parent_id == $ROOT_CATEGORY_ID
						? '' . $v->title
						: $pre . $v->title;
				}
				else
				{
					$txt = $v->parent_id == $ROOT_CATEGORY_ID
						? ''
						: $pre;
				}

				$pt = $v->parent_id;
				$list[$id] = $v;
				$list[$id]->treename  = "$indent$txt";
				$list[$id]->title     = $v->title;
				$list[$id]->slug      = $v->slug;
				$list[$id]->access    = $v->access;
				$list[$id]->ancestors = $ancestors;
				$list[$id]->level     = $level + 1;
				$list[$id]->children  = !empty($children[$id]) ? count($children[$id]) : 0;
				$list[$id]->childrenarray = !empty($children[$id]) ? $children[$id] : null;

				$parent_id = $id;
				$list = plgSystemFlexisystem::_getCatAncestors(
					$parent_id, $indent . $spacer, $list, $children, $title, $maxlevel, $level+1, $type, $ancestors
				);
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

		while( count($stack) )
		{
			$v = array_pop($stack);
			$descendants[] = $v->id;

			if ( empty($v->childrenarray) )
			{
				continue;
			}
			foreach( array_reverse($v->childrenarray) as $child )
			{
				$stack[] = $child;
			}
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

		while( count($stack) )
		{
			$v = array_pop($stack);
			$totalItems += $v->numitems;

			if ( empty($v->childrenarray) )
			{
				continue;
			}
			foreach( $v->childrenarray as $child )
			{
				$stack[] = $child;
			}
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
		$app     = Factory::getApplication();
		$session = Factory::getSession();

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
			$app     = Factory::getApplication();

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
				if ( !isset($_POST[$var]) ) Factory::getApplication()->enqueueMessage( "GET variable: ".$var . " is not set in the POST ARRAY", 'message');
			}*/

			if (JDEBUG) Factory::getApplication()->enqueueMessage(
				"Form data were serialized, ".
				'<b class="label">PHP max_input_vars</b> <span class="badge bg-info badge-info">'.ini_get('max_input_vars').'</span> '.
				'<b class="label">Estimated / Actual FORM variables</b>'.
				'<span class="badge bg-warning badge-warning">'.$_POST['fcdata_serialized_count'].'</span> / <span class="badge">'.$total_vars.'</span> ',
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
		$app = Factory::getApplication();

		$username  = $app->input->get('fcu', null);
		$password  = $app->input->get('fcp', null);

		jimport('joomla.user.helper');

		$db = Factory::getDbo();
		$query 	= 'SELECT id, password'
				. ' FROM #__users'
				. ' WHERE username = ' . $db->Quote( $username )
				. ' AND password = ' . $db->Quote( $password )
				;
		$db->setQuery( $query );
		$result = $db->loadObject();

		if($result)
		{
			PluginHelper::importPlugin('user');
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

		$app     = Factory::getApplication();
		$session = Factory::getSession();
		$format  = $app->input->getCmd('format', 'html');

		if ($app->isClient('site') && $format === 'html')
		{
			// Count an item or category hit if appropriate
			$this->countHit();

			/**
			 * CSS CLASSES for body TAG
			 */

			$start_microtime = microtime(true);
			$css = array();

			$view = $app->input->getCmd('view');

			if ($view === 'item')
			{
				if ($id = $app->input->get('id', 0, 'int'))            $css[] = "item-id-".$id;  // Item's id
				if ($cid = $app->input->get('cid', 0, 'int'))          $css[] = "item-catid-".$cid;  // Item's category id
				if ($id)
				{
					$db = Factory::getDbo();
					$query 	= 'SELECT t.id, t.alias'
						. ' FROM #__flexicontent_items_ext AS e'
						. ' JOIN #__flexicontent_types AS t ON e.type_id = t.id'
						. ' WHERE e.item_id=' . (int) $id
					;
					$type = $db->setQuery($query)->loadObject();
					if ($type)
					{
						$css[] = "type-id-".$type->id;        // Type's id
						$css[] = "type-alias-".$type->alias;  // Type's alias
					}
				}
			}

			elseif ($view === 'category')
			{
				// Category id
				if ($cid = $app->input->get('cid', 0, 'int'))
				{
					$cid = is_array($cid) ? (int) reset($cid) : (int) $cid;

					if ($cid)
					{
						$css[] = 'catid-' . $cid;
					}
				}
				elseif ($cids = $app->input->get('cids', array(), 'array'))
				{
					$catids = !is_array($cids)
						? preg_split("/[\s]*,[\s]*/", $cids)
						: $cids;
					$catids = ArrayHelper::toInteger($catids);

					$css[] = 'mcats-' . implode(' mcats-', $catids);
				}

				if ($authorid = $app->input->get('authorid', 0, 'int'))  $css[] = "authorid-".$authorid; // Author id
				if ($tagid = $app->input->get('tagid', 0, 'int'))        $css[] = "tagid-".$tagid;  // Tag id
				if ($layout = $app->input->get('layout', '', 'cmd'))     $css[] = "cat-layout-".$layout;   // Category 'layout': tags, favs, author, myitems, mcats
			}

			$html = $app->getBody();
			$html = preg_replace('#<body([^>]*)class="#', '<body\1class="'.implode(' ', $css).' ', $html, 1);  // limit to ONCE !!
			$app->setBody($html);
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

		if ($fc_performance_msg && $format === 'html')
		{
			$html = $app->getBody();

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

			$app->setBody($html);
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
		$app = Factory::getApplication();
		$format = $app->input->get('format', 'html', 'cmd');

		if (!$app->isClient('administrator') || !Factory::getUser()->id || $format !== 'html')
		{
			return;
		}

		require_once (JPATH_SITE.'/components/com_flexicontent/helpers/permission.php');
		$perms = FlexicontentHelperPerm::getPerm();
		HTMLHelper::_('jquery.framework');

		Factory::getDocument()->addScriptDeclaration("
			jQuery(document).ready(function(){
				".(!$perms->CanReviews ? 'jQuery(\'#menu a[href="index.php?option=com_flexicontent&view=reviews"]\').parent().remove();' : '')."
				".(!$perms->CanCats    ? 'jQuery(\'#menu a[href="index.php?option=com_flexicontent&view=categories"]\').parent().remove();' : '')."
				".(!$perms->CanTypes   ? 'jQuery(\'#menu a[href="index.php?option=com_flexicontent&view=types"]\').parent().remove();' : '')."
				".(!$perms->CanFields  ? 'jQuery(\'#menu a[href="index.php?option=com_flexicontent&view=fields"]\').parent().remove();' : '')."
				".(!$perms->CanTags    ? 'jQuery(\'#menu a[href="index.php?option=com_flexicontent&view=tags"]\').parent().remove();' : '')."
				".(!$perms->CanTemplates ? 'jQuery(\'#menu a[href="index.php?option=com_flexicontent&view=templates"]\').parent().remove();' : '')."
				".(!$perms->CanAuthors ? 'jQuery(\'#menu a[href="index.php?option=com_flexicontent&view=users"]\').parent().remove();' : '')."
				".(!$perms->CanGroups  ? 'jQuery(\'#menu a[href="index.php?option=com_flexicontent&view=groups"]\').parent().remove();' : '')."
				".(!$perms->CanFiles   ? 'jQuery(\'#menu a[href="index.php?option=com_flexicontent&view=filemanager"]\').parent().remove();' : '')."
				".(!$perms->CanImport  ? 'jQuery(\'#menu a[href="index.php?option=com_flexicontent&view=import"]\').parent().remove();' : '')."
				".(!$perms->CanStats   ? 'jQuery(\'#menu a[href="index.php?option=com_flexicontent&view=stats"]\').parent().remove();' : '')."
				".(!$perms->CanConfig  ? 'jQuery(\'#menu a[href="index.php?option=com_config&view=component&component=com_flexicontent"]\').parent().remove();' : '')."
			});
		");
	}


	public function set_cache_control()
	{
		$app = Factory::getApplication();

		$option = $app->input->get('option', '', 'cmd');
		$browser_cachable = $app->input->get('browser_cachable', null);

		if ($option==$this->extension && $browser_cachable!==null)
		{
			// Use 1/4 of Joomla cache time for the browser caching
			$cachetime = (int) Factory::getConfig()->get('cachetime', 15);
			$cachetime = $cachetime > 60 ? 60 : ($cachetime < 15 ? 15 : $cachetime);
			$cachetime = $cachetime * 60;

			// Try to avoid browser warning message "Page has expired or similar"
			// This should turning off the 'must-revalidate' directive in the 'Cache-Control' header
			$app->allowCache($browser_cachable ? true : false);
			$app->setHeader('Pragma', $browser_cachable ? '' :'no-cache');

			// CONTROL INTERMEDIARY CACHES (PROXY, ETC)
			// 1:  public content (unlogged user),   2:  private content (logged user)
			// BUT WE FORCE 'private' to avoid problems with 3rd party plugins and modules, that do cookie-based per visitor content for guests (unlogged users)
			$cacheControl  = 'private';  // $browser_cachable == 1 ? 'public' : 'private';

			// SET MAX-AGE, to allow modern browsers to cache the page, despite expires header in the past
			$cacheControl .= ', max-age=300';
			$app->setHeader('Cache-Control', $cacheControl );

			// Make sure no legacy proxies any caching !
			$app->setHeader('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');
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
		$app      = Factory::getApplication();
		$session  = Factory::getSession();
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

		$document = Factory::getDocument();
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

			function fc_setCookie(cookieName, cookieValue, nDays, samesite="lax") {
				var today = new Date();
				var expire = new Date();
				var path = "'.Uri::base(true).'";
				if (nDays==null || nDays<0) nDays=0;
				if (nDays) {
					expire.setTime(today.getTime() + 3600000*24*nDays);
					document.cookie = cookieName+"="+escape(cookieValue) + ";samesite=" + samesite + ";path=" + path + ";expires=" + expire.toGMTString();
				} else {
					document.cookie = cookieName+"="+escape(cookieValue) + ";samesite=" + samesite + ";path=" + path;
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
		$cache = Factory::getCache('plg_'.$this->_name.'_'.__FUNCTION__);
		$cache->setCaching(1);      // Force cache ON
		$cache->setLifeTime(3600);  // Set expire time (default is 1 hour)
		$last_check_time = $cache->get(array($this, '_getLastCheckTime'), array(__FUNCTION__) );

		// Execute every 15 minutes
		$elapsed_time = time() - $last_check_time;  //Factory::getApplication()->enqueueMessage('plg_'.$this->_name.'::'.__FUNCTION__.'() elapsed_time: ' . $elapsed_time . '<br/>');
		if ($elapsed_time < 15*60) return;  //Factory::getApplication()->enqueueMessage('EXECUTING: '.'plg_'.$this->_name.'::'.__FUNCTION__.'()<br/>');

		// Clear cache and call method again to restart the counter
		$cache->clean('plg_'.$this->_name.'_'.__FUNCTION__);
		$last_check_time = $cache->get(array($this, '_getLastCheckTime'), array(__FUNCTION__) );

		$db  = Factory::getDbo();
		$app = Factory::getApplication();

		$max_checkout_hours = $this->params->get('max_checkout_hours', 24);
		$max_checkout_secs  = $max_checkout_hours * 3600;

		// Get current seconds
		$date = Factory::getDate('now');
		$tz	= new DateTimeZone(Factory::getConfig()->get('offset'));
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

		$tablenames = array('content', 'categories', 'modules', 'menu', 'flexicontent_files', 'flexicontent_fields', 'flexicontent_types');
		foreach ( $tablenames as $tablename )
		{
			//echo $tablename.":<br>";

			// Get checked out records
			$query = 'SELECT id, checked_out, checked_out_time FROM #__'.$tablename.' WHERE checked_out > 0';
			$db->setQuery($query);
			$records = $db->loadObjectList();

			if ( !count($records) ) continue;
			$tz	= new DateTimeZone(Factory::getConfig()->get('offset'));

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
					$date = Factory::getDate($record->checked_out_time);
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
				// NOTE in J4 the default is NULL ...
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
		$cache = Factory::getCache('plg_'.$this->_name.'_'.__FUNCTION__);
		$cache->setCaching(1);      // Force cache ON
		$cache->setLifeTime(3600);  // Set expire time (default is 1 hour)
		$last_check_time = $cache->get(array($this, '_getLastCheckTime'), array(__FUNCTION__) );

		// Execute every 15 minutes
		$elapsed_time = time() - $last_check_time;  //Factory::getApplication()->enqueueMessage('plg_'.$this->_name.'::'.__FUNCTION__.'() elapsed_time: ' . $elapsed_time . '<br/>');
		if ($elapsed_time < 15*60) return;  //Factory::getApplication()->enqueueMessage('EXECUTING: '.'plg_'.$this->_name.'::'.__FUNCTION__.'()<br/>');

		// Clear cache and call method again to restart the counter
		$cache->clean('plg_'.$this->_name.'_'.__FUNCTION__);
		$last_check_time = $cache->get(array($this, '_getLastCheckTime'), array(__FUNCTION__) );

		$db  = Factory::getDbo();
		$app = Factory::getApplication();

		// Get current seconds
		$date = Factory::getDate('now');
		$tz	= new DateTimeZone(Factory::getConfig()->get('offset'));
		$date->setTimezone($tz);
		$current_time_secs = $date->toUnix();
		//echo $date->toFormat()." <br>";

		$clear_publish_down_date = $this->params->get('clear_publish_down_date', 1);
		$new_state = $archive_on_publish_down==1 ? 2 : 0;

		$_nowDate = 'UTC_TIMESTAMP()';
		$nullDate	= $db->getNullDate();

		$query = 'UPDATE #__content SET state = '.$new_state.
			($clear_publish_down_date ? ', publish_down = '.$db->Quote($nullDate) : '').
			' WHERE publish_down IS NOT NULL AND publish_down != '.$db->Quote($nullDate).' AND publish_down <= '.$_nowDate;
		//echo $query;
		$db->setQuery($query);
		$db->execute();

		$query = 'UPDATE #__flexicontent_items_tmp SET state = '.$new_state.
			($clear_publish_down_date ? ', publish_down = '.$db->Quote($nullDate) : '').
			' WHERE publish_down IS NOT NULL AND publish_down != '.$db->Quote($nullDate).' AND publish_down <= '.$_nowDate;
		//echo $query;
		$db->setQuery($query);
		$db->execute();
	}



	/**
	 * Utility function:
	 * (Auto) Transfer files to external servers
	 *
	 * @return 	void
	 * @since 1.5
	 */
	function handleFileTranfers()
	{
		// EFS Configuration : (External File Server)
		$cparams = ComponentHelper::getParams('com_flexicontent');

		// Get last execution time from cache
		$cache = Factory::getCache('plg_'.$this->_name.'_'.__FUNCTION__);
		$cache->setCaching(1);      // Force cache ON
		$cache->setLifeTime(3600);  // Set expire time (default is 1 hour)
		$last_check_time = $cache->get(array($this, '_getLastCheckTime'), array(__FUNCTION__) );

		// Execute every 15 minutes
		$elapsed_time = time() - $last_check_time;
		//Factory::getApplication()->enqueueMessage('plg_'.$this->_name.'::'.__FUNCTION__.'() elapsed_time: ' . $elapsed_time . '<br/>');
		
		if ($elapsed_time < 1*60) return;
		//Factory::getApplication()->enqueueMessage('EXECUTING: '.'plg_'.$this->_name.'::'.__FUNCTION__.'()<br/>');

		// Clear cache and call method again to restart the counter
		$cache->clean('plg_'.$this->_name.'_'.__FUNCTION__);
		$last_check_time = $cache->get(array($this, '_getLastCheckTime'), array(__FUNCTION__) );


		// Try to allow using longer execution time and more memory
		//$this->_setExecConfig();

		$shell_exec_enabled = is_callable('shell_exec') && false === stripos(ini_get('disable_functions'), 'shell_exec');
		if ($shell_exec_enabled)
		{
			//echo $output = shell_exec('php ' . JPATH_ROOT . '/components/com_flexicontent/tasks/estorage.php > /dev/null 2>/dev/null &');
			$output = shell_exec('php ' . JPATH_ROOT . '/components/com_flexicontent/tasks/estorage.php 2>&1');

			if ($output)
			{
				$log_filename = 'cron_estorage.php';
				jimport('joomla.log.log');
				Log::addLogger(
					array(
						'text_file' => $log_filename,  // Sets the target log file
						'text_entry_format' => '{DATE} {TIME} {PRIORITY} {MESSAGE}'  // Sets the format of each line
					),
					Log::ALL,  // Sets messages of all log levels to be sent to the file
					array('com_flexicontent.estorage')  // category of logged messages
				);
				Log::add($output, Log::INFO, 'com_flexicontent.estorage');
			}
		}
	}


	/* Increment item / category hits counters, according to configuration */
	function countHit()
	{
		$app    = Factory::getApplication();
		$option = $app->input->get('option', '', 'cmd');
		$view   = $app->input->get('view', '', 'cmd');

		if ($option==$this->extension && $view=='item')
		{
			$item_id = $app->input->get('id', 0, 'int');
			if ( $item_id && $this->count_new_hit($item_id) )
			{
				$db = Factory::getDbo();
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
				$db = Factory::getDbo();
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
				$session = Factory::getSession();

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
					$db = Factory::getDbo();
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

		$db = Factory::getDbo();
		$visitorip = $_SERVER['REMOTE_ADDR'];  // Visitor IP
		$current_secs = time();  // Current time as seconds since Unix epoch
		if ($item_id==0) {
			Factory::getApplication()->enqueueMessage(nl2br("Invalid item id or item id is not set in http request"),'error');
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
			$session 	= Factory::getSession();
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

			try
			{
				$result = $db->setQuery($query)->execute();
			}
			catch (Exception $e)
			{
				$query_create = "CREATE TABLE #__flexicontent_hits_log (item_id INT PRIMARY KEY, timestamp INT NOT NULL, ip VARCHAR(16) NOT NULL DEFAULT '0.0.0.0')";
				$result = $db->setQuery($query_create)->execute();

				// On select error , aka missing table created, count a new hit
				return 1;
			}

			$count = $db->loadResult();

			// Log the visit into the hits logging db table
			if (empty($count))
			{
				$query = "INSERT INTO #__flexicontent_hits_log (item_id, timestamp, ip) "
						."  VALUES (".$db->quote($item_id).", ".$db->quote($current_secs).", ".$db->quote($visitorip).")"
						." ON DUPLICATE KEY UPDATE timestamp=".$db->quote($current_secs).", ip=".$db->quote($visitorip);
				$result = $db->setQuery($query)->execute();

				// Last visit not found or is beyond time limit, count a new hit
				return 1;
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

		$user = Factory::getUser();
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
		$conf = Factory::getConfig();
		//$client = 0;//0 is site, 1 is admin
		$options = array(
			'defaultgroup'	=> $group,
			'storage' 		=> $conf->get('cache_handler', ''),
			'caching'		=> true,
			'cachebase'		=> ($client == 1) ? JPATH_ADMINISTRATOR . '/cache' : $conf->get('cache_path', JPATH_SITE . '/cache')
		);

		jimport('joomla.cache.cache');
		$cache = Cache::getInstance('', $options);
		return $cache;
	}


	private function _storeLessConf($table)
	{
		$xml_path  = JPath::clean(JPATH_ADMINISTRATOR.'/components/com_flexicontent/config.xml');
		$less_path = JPath::clean(JPATH_ROOT.'/components/com_flexicontent/assets/less/include/mixins.less');


		/**
		 * Load the XML file into a JForm object
		 */
		$_options = array('control' => 'jform', 'load_data' => false);
		$jform = \JForm::getInstance(
			'com_config.component', // Exception name, if an error occurs.
			'config',               // The name of an XML file or string to load as the form definition.
			$_options,              // An array of form options.
			$_replace = false,      // Flag to toggle whether form fields should be replaced if a field already exists with the same group/name.
			$_xpath = '/config'     // An optional xpath to search for the fields.
		);
		$jform->load(file_get_contents($xml_path));


		/**
		 * Iterate though the form elements and only use parameters with cssprep="less"
		 *
		 * Only look into some fieldsets, for all use:  $fieldSetNames = array_keys( $jform->getFieldsets());
		 */
		$fieldSetNames = array('component');  
		$less_data = "/* This is created automatically, do NOT edit this manually! \nModify these in component configuration. */\n\n";

		foreach($fieldSetNames as $fsname)
		{
			foreach($jform->getFieldset($fsname) as $field)
			{
				if ($field->getAttribute('cssprep')!='less') continue;  // Only add parameters meant to be less variables
				$v = $table->params->get($field->fieldname);
				if (is_array($v)) continue;  // array parameters not supported
				$v = trim($v);
				if ( !strlen($v) ) {
					$v = $field->getAttribute('default');
					if ( !strlen($v) ) continue;  // do not add empty parameters
				}
				$less_data .= '@' . $field->fieldname . ': ' . $v . ";\n";
			}
		}


		/**
		 * Write the less file with the CSS variable using the found cssprep parameters ...
		 */
		file_put_contents($less_path, $less_data);

		return true;
	}


	/**
	 * Event method onExtensionBeforeSave
	 *
	 * @param   string  $context  Current context
	 * @param   Table   $table    Table instance
	 * @param   bool    $isNew    Flag to determine whether this is a new extension
	 *
	 * @return void
	 */
	public function onExtensionBeforeSave($context, $table, $isNew)
	{
		$app   = Factory::getApplication();
		$user  = Factory::getUser();
		$option = $app->input->get('component', '', 'cmd');

		/**
		 * Handle saving the parameters of 2 (1 frontend, 1 backend) item form default layouts
		 */

		if ($context === 'com_config.component' && $table->type === 'component' && $table->element === 'com_flexicontent')
		{
			if (Factory::getApplication()->isClient('administrator'))
			{
				$raw_data = Factory::getApplication()->input->post->get('jform', array(), 'array');

				$table->params = new Registry($table->params);
				$iflayout_params = !empty($raw_data['iflayout']) ? $raw_data['iflayout'] : array();
				foreach($iflayout_params as $i => $v)
				{
					$table->params[$i] = $v;
				}

				$this->_storeLessConf($table);
				$table->params = $table->params->toString();
			}
		}


		/**
		 * Handle syncing permissions between com_content and com_flexicontent assets
		 */

		if ($context === 'com_config.component' && ($option === 'com_content' || $option === 'com_flexicontent'))
		{
			$rules_arr = @ $_POST['jform']['rules'];
			$option_other = $option == 'com_content'  ?  'com_flexicontent'  :  'com_content';

			// Only save permissions rules, if user is allowed to edit them
			// and if rules exists (in J3.5+ they are saved via AJAX thus code would normally be triggered only in J3.2 - J3.4)
			if ( $rules_arr!= null && $user->authorise('core.admin', $option) )
			{
				// Get asset of the other component
				$asset = Table::getInstance('asset');
				if (!$asset->loadByName($option_other))
				{
					$root = Table::getInstance('asset');
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
					$com_content_asset = Table::getInstance('asset');
					if ( $com_content_asset->loadByName('com_content') )
					{
						$com_content_rules = json_decode($com_content_asset->rules, true);
						$com_content_rules['core.delete.own'] = isset($asset_rules['core.delete.own']) ? $asset_rules['core.delete.own'] : '';
						$com_content_rules['core.edit.state.own'] = isset($asset_rules['core.edit.state.own']) ? $asset_rules['core.edit.state.own'] : '';
						$rules = new Rules($com_content_rules);
						$com_content_asset->rules = (string) $rules;
						if (!$com_content_asset->check() || !$com_content_asset->store())
						{
							throw new RuntimeException($com_content_asset->getError());
						}
					}
				}

				// Save asset rules of the other component
				$rules = new Rules($asset_rules);
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
		if ($context === 'com_modules.module' || $context === 'com_advancedmodules.module' || substr($context, 0, 10) === 'com_falang')
		{
			// Check for non-empty layout parameter
			$layout = $_POST['jform']['params']['layout'];
			if (empty($layout)) return;

			// Check for currently supported cases, !!! TODO add case of MENUS
			if (empty($table->module)) return;

			$layout_names = explode(':', $layout);
			// Check if layout XML parameter file exists
			$client = ApplicationHelper::getClientInfo($table->client_id);
			$layoutpath = '';
			if(count($layout_names)>1)
				$layoutpath = Path::clean($client->path . '/templates/' . $layout_names[0] . '/html/' . $table->module .'/'.$layout_names[1].'.xml');
			else if(count($layout_names)==1)
				$layoutpath = Path::clean($client->path . '/modules/' . $table->module . '/tmpl/' . $layout .'.xml');
			if (!$layoutpath || !file_exists($layoutpath))
			{
				$layoutpath = Path::clean($client->path . '/modules/' . $table->module . '/tmpl/_fallback/_fallback.xml');
				if (!file_exists($layoutpath)) return;
			}

			// Attempt to parse the XML file
			$xml = simplexml_load_file($layoutpath);
			if (!$xml)
			{
				Factory::getApplication()->enqueueMessage('Error parsing layout file of "'.$layoutpath.'". Layout parameters were not saved', 'warning');
				return;
			}

			// Create form object loading the , (form name seems not to cause any problem)
			$jform = new Form('com_flexicontent.layout', array('control' => 'jform', 'load_data' => false));
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
				Factory::getApplication()->enqueueMessage('Error validating layout posted parameters. Layout parameters were not saved', 'error');
				return;
			}

			$params = new Registry($table->params);
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
	 * @param   Table   $table    Table instance
	 * @param   bool    $isNew    Flag to determine whether this is a new extension
	 *
	 * @return void
	 */
	public function onExtensionAfterSave($context, $table, $isNew)
	{
		//Factory::getApplication()->enqueueMessage("onExtensionAfterSave -- context: $context -- table: <pre>" . print_r($table, true) . '</pre>');

		/**
		 * Various tables. Update usage of Files in download links created via the XTD-editor file button
		 */
		$this->_updateFileUsage_FcFileBtn_DownloadLinks($context, $table, $isNew, $table);
	}


	/**
	 * Prepare form.
	 *
	 * @param   Form   $form  The form to be altered.
	 * @param   mixed  $data  The associated data for the form.
	 *
	 * @return  boolean
	 *
	 * @since	2.5
	 */
	public function onContentPrepareForm($form, $data)
	{
		// Check we have a form.
		if (!($form instanceof Form))
		{
			$this->_subject->setError('JERROR_NOT_A_FORM');

			return false;
		}

		$app        = Factory::getApplication();
		$document   = Factory::getDocument();
		$user       = Factory::getUser();

		// Check we are loading the com_content article form
		if ($form->getName() !== 'com_content.article' || Factory::getApplication()->input->get('option', '', 'CMD')!=='com_content')
		{
			return true;
		}

		// Check for empty data, create empty object
		if (!$data)
		{
			$data = new stdClass();
			$data->id = 0;
			$data->catid = 0;
		}

		// Check for array data, convert to object
		if (is_array($data))
		{
			$data = (object) $data;
		}

		if (Factory::getApplication()->input->getInt('a_id', 0))
		{
			$_id = Factory::getApplication()->input->getInt('a_id', 0);
			$data->id = $_id;
			Factory::getApplication()->input->set('id', $_id);
		}

		$this->_loadFcHelpersAndLanguage();


		// ***
		// *** Load item and its fields and its type parameters
		// ***

		$cparams = ComponentHelper::getParams('com_flexicontent');
		$default_type_id = $cparams->get('jarticle_form_typeid', 1);

		// Get current type_id of the item
		if ($data->id)
		{
			$record = Table::getInstance($type = 'flexicontent_items', $prefix = '', $config = array());
			$record->load($data->id);
		}
		$data->type_id = !empty($record) && $record->type_id ? $record->type_id : 0;

		// Get model and set default type if type not set already (new item or existing item with no type)
		$model = new FlexicontentModelItem();

		if (empty($data->type_id))
		{
			$types = flexicontent_html::getTypesList($type_ids=false, $check_perms = true, $published=true);
			$default_type = isset($types[$default_type_id])
				? $types[$default_type_id]
				: reset($types);
			$data->type_id = $default_type->id;
			$model->setId($data->id, $data->catid, $data->type_id);
		}

		// Get the item, clone it to avoid setting to it extra Registry / Array properties
		// like fields, parameters that will slow down or cause recursion during (J)Form operations like bind
		$fcform_item = clone($model->getItem($data->id, $check_view_access=false));

		// Get the item's fields
		$fcform_item->fields = $model->getExtrafields();

		// Get type parameters
		$fcform_item->tparams = new Registry($model->getTypeparams());

		// Set component + type as item parameters
		$fcform_item->parameters = $model->getComponentTypeParams();


		// ***
		// *** Load CSS files
		// ***

		!Factory::getLanguage()->isRtl()
			? $document->addStyleSheet(Uri::root(true).'/components/com_flexicontent/assets/css/flexi_form.css', array('version' => FLEXI_VHASH))
			: $document->addStyleSheet(Uri::root(true).'/components/com_flexicontent/assets/css/flexi_form_rtl.css', array('version' => FLEXI_VHASH));

		!Factory::getLanguage()->isRtl()
			? $document->addStyleSheet(Uri::root(true).'/components/com_flexicontent/assets/css/flexi_containers.css', array('version' => FLEXI_VHASH))
			: $document->addStyleSheet(Uri::root(true).'/components/com_flexicontent/assets/css/flexi_containers_rtl.css', array('version' => FLEXI_VHASH));

		!Factory::getLanguage()->isRtl()
			? $document->addStyleSheet(Uri::root(true).'/components/com_flexicontent/assets/css/flexi_shared.css', array('version' => FLEXI_VHASH))
			: $document->addStyleSheet(Uri::root(true).'/components/com_flexicontent/assets/css/flexi_shared_rtl.css', array('version' => FLEXI_VHASH));

		// Fields common CSS
		$document->addStyleSheet(Uri::root(true).'/components/com_flexicontent/assets/css/flexi_form_fields.css', array('version' => FLEXI_VHASH));


		// ***
		// *** Load JS libraries
		// ***

		flexicontent_html::loadFramework('jQuery');
		flexicontent_html::loadFramework('select2');
		flexicontent_html::loadFramework('touch-punch');
		flexicontent_html::loadFramework('prettyCheckable');
		flexicontent_html::loadFramework('flexi-lib');
		flexicontent_html::loadFramework('flexi-lib-form');

		// Add js function to overload the joomla submitform validation
		HTMLHelper::_('behavior.formvalidator');  // load default validation JS to make sure it is overriden
		$document->addScript(Uri::root(true).'/components/com_flexicontent/assets/js/admin.js', array('version' => FLEXI_VHASH));
		$document->addScript(Uri::root(true).'/components/com_flexicontent/assets/js/validate.js', array('version' => FLEXI_VHASH));

		// Add js function for custom code used by FLEXIcontent item form
		$document->addScript(Uri::root(true).'/components/com_flexicontent/assets/js/itemscreen.js', array('version' => FLEXI_VHASH));


		// ***
		// *** Load field values from session (typically during a form reload after a servers-side form validation failure)
		// *** NOTE: Because of fieldgroup rendering other fields, this step must be done in seperate loop, placed before FIELD HTML creation
		// ***

		$jcustom = $app->getUserState('com_flexicontent.edit.item.custom');
		foreach ($fcform_item->fields as $field)
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

		foreach ($fcform_item->fields as $field)
		{
			FlexicontentFields::getFieldFormDisplay($field, $fcform_item, $user);
		}

		// Set item for rendering flexicontent fields
		require_once(Path::clean(JPATH_ROOT.'/administrator/components/com_flexicontent/models/fields/fcfieldwrapper.php'));
		JFormFieldFCFieldWrapper::$fcform_item = $fcform_item;

		// Get flexicontent fields
		$form->load('
			<form>
				<fields name="attribs">
					<fieldset
						name="fcfields"
						label="' . ( $fcform_item->typename
							? Text::_('FLEXI_TYPE_NAME') . ' : ' . Text::_($fcform_item->typename)
							: Text::_('FLEXI_TYPE_NOT_DEFINED')
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

		$app = Factory::getApplication();
		if (
			$app->input->get('option', '', 'CMD')!='com_content' ||
			$app->input->get('view', '', 'CMD')!='article' ||
			$app->input->get('isflexicontent', false, 'CMD')
		) return;


		static $fields_added = array();
		static $items = array();
		if (!empty($fields_added[$row->id]))
		{
			return;
		}


		$this->_loadFcHelpersAndLanguage();

		if (!isset($items[$row->id]))
		{
			$model = new FlexicontentModelItem();
			$items[$row->id] = $model->getItem($row->id, $check_view_access=false);
		}

		// Get a copy of the item
		$item = $items[$row->id]
			? clone($items[$row->id])
			: false;

		// Item retrieval failed avoid fatal error
		if (!$item)
		{
			return;
		}


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
		FlexicontentFields::getFields($item, $view, $_item_params = null, $aid = null, $use_tmpl = false);  // $_item_params == null means only retrieve fields

		// Only Render custom fields
		$displayed_fields = array();
		foreach ($item->fields as $field)
		{
			// Prevent rendering of core fields
			if ($field->iscore) continue;
			// Prevent rendering of fields meant for form placement
			if (substr($field->name, 0, 5) === 'form_') continue;

			$displayed_fields[$field->name] = $field;
			$values = isset($item->fieldvalues[$field->id]) ? $item->fieldvalues[$field->id] : array();
			FlexicontentFields::renderField($item, $field, $values, $method='display', $view, false, $row);
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
		require_once (JPATH_SITE.'/components/com_flexicontent/classes/flexicontent.helper.php');

		$app  = Factory::getApplication();
		$user = Factory::getUser();
		$db   = Factory::getDbo();
		$jcookie = $app->input->cookie;

		// Set id for client-side (browser) caching via unique URLs (logged users)
		$jcookie->set( 'fc_uid', UserHelper::getShortHashedUserAgent(), 0);

		// Add favourites via cookie to the DB
		$fcfavs = flexicontent_favs::getInstance()->getRecords();

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
		$jcookie = Factory::getApplication()->input->cookie;
		$jcookie->set( 'fc_uid', 'p', 0);
	}


	/**
	 * Change the state in core_content if the state in a table is changed
	 *
	 * @param   string   $context  The context for the content passed to the plugin.
	 * @param   array    $pks      A list of primary key ids of the content that has changed state.
	 * @param   integer  $value    The value of the state that the content has been changed to.
	 *
	 * @return  boolean
	 *
	 * @since   3.2.1.9
	 */
	public function onContentChangeState($context, $pks, $value)
	{
		if ($context != 'com_content.article' || Factory::getApplication()->input->get('isflexicontent', false, 'CMD'))
		{
			return true;
		}

		$this->_loadFcHelpersAndLanguage();

		//***
		//*** Call backend 'flexicontent' items model to update flexicontent temporary data
		//***

		// Load the FLEXIcontent item
		$app  = Factory::getApplication();
		$cid = $app->input->get('cid', array(), 'array');
		$cid = (int) reset($cid);

		// Update temporary date by calling model's respective method
		// NOTE 1: since using controller task to trigger temporary data updating will not work because the DB tables have not been updated yet
		// NOTE 2: we will skip the change state event triggering since com_content 'articles' model will do this
		$itemmodel = new FlexicontentModelItem();

		// Get item setting it into the model (ITEM DATE: _id, _type_id, _params, etc will be updated)
		$item = $itemmodel->getItem($cid, $check_view_access=false, $no_cache=true);

		// Load backend 'flexicontent' items model and use it to update flexicontent temporary data
		JLoader::register('FlexicontentModelItems', JPATH_ADMINISTRATOR.'/components/com_flexicontent/models/items.php');
		$items_model = new FlexicontentModelItems();
		$items_model->updateItemCountingData(array($item));

		return true;
	}


	/**
	 * Before save event.
	 *
	 * @param   string   $context  The context
	 * @param   Table    $item     The table
	 * @param   boolean  $isNew    Is new item
	 * @param   array    $data     The validated data
	 *
	 * @return  boolean
	 *
	 * @since   3.2.0
	 */
	public function onContentBeforeSave($context, $item, $isNew, $data = array())
	{
		// Workaround for wrong event triggering bug in Advanced module manager 7.x (up to at least version 7.9.2)
		if ($context === 'com_advancedmodules.module')
		{
			return $this->onExtensionBeforeSave($context, $item, $isNew);
		}

		if (($context !== 'com_content.article' && $context !== 'com_content.form') || Factory::getApplication()->input->get('isflexicontent', false, 'CMD'))
		{
			return true;
		}

		if (Factory::getApplication()->input->getInt('a_id', 0))
		{
			$_id = Factory::getApplication()->input->getInt('a_id', 0);
			Factory::getApplication()->input->set('id', $_id);
			$item->id = $_id;

			if (is_object($data))
			{
				$data->id = $_id;
			}
			elseif (is_array($data))
			{
				$data['id'] = $_id;
			}
		}

		//***
		//*** Maintain flexicontent-specific article parameters
		//***

		$this->_loadFcHelpersAndLanguage();
		$model = new FlexicontentModelItem();

		$record = Table::getInstance($type = 'flexicontent_items', $prefix = '', $config = array());
		$record->load($item->id);

		$mergeProperties = array('attribs', 'metadata');
		$mergeOptions = array(
			'params_fset'  => 'attribs',
			'layout_type'  => 'item',
			'model_names'  => array('com_flexicontent' => 'item', 'com_content' => 'article'),
			'cssprep_save' => false,
		);
		$model->mergeAttributes($record, $data, $mergeProperties, $mergeOptions);

		$item_data = array();
		foreach($mergeProperties as $prop)
		{
			$item_data[$prop] = isset($record->$prop) ? $record->$prop : null;
		}
		Factory::getSession()->set('flexicontent.item.data', $item_data, 'flexicontent');

		return true;
	}



		/**
		 * Various tables. Update usage of Files in download links created via the XTD-editor file button
		 */
	private function _updateFileUsage_FcFileBtn_DownloadLinks($context, $item, $isNew, $propValues, $path = '', $depth = 0)
	{
		$db   = Factory::getDbo();

		// Do not know how to handle this case
		if (!isset($item->id)) return;

		//Factory::getApplication()->enqueueMessage("context: $context");
		if ($depth === 0)
		{
			// First delete existing usage
			$query = $db->getQuery(true)
				->delete($db->qn('#__flexicontent_file_usage'))
				->where($db->qn('id') . ' = ' . $db->q($item->id))
				->where($db->qn('context') . ' = ' . $db->q($context));
			$db->setQuery($query)->execute();
			//Factory::getApplication()->enqueueMessage($query);
		}

		if ($propValues) foreach($propValues as $prop => $value)
		{
			// Do not know how to handle / cannot handle these cases
			if (!isset($item->id) || !is_string($prop))
			{
				continue;
			}
			//Factory::getApplication()->enqueueMessage($path . $prop . '<br>');

			// Do not search text property, instead we will search text and introtext
			if ($context === 'com_content.article' && $prop === 'text')
			{
				continue;
			}
			
			if (!is_string($value))
			{
				if (is_array($value) && $depth <= 3)
				{
					$this->_updateFileUsage_FcFileBtn_DownloadLinks($context, $item, $isNew, $value, $path . $prop . '.', $depth + 1);
				}
				continue;
			}

			$matches = array();
			$regexp_PHP_NONSEF = "[ \t\n\r]+href=\"((http:\/\/|https:\/\/)?([^\"]*)index.php\?([^\"]*))\"";
			preg_match_all('/'. $regexp_PHP_NONSEF . '/', $value, $matches);

			$cnt = $matches ? count($matches[0]) : 0;
			if ($cnt)
			{
				//Factory::getApplication()->enqueueMessage('<pre>'.htmlspecialchars(print_r($matches, true), ENT_COMPAT, 'UTF-8').'</pre>');

				for ($i=0; $i<$cnt; $i++)
				{
					parse_str(html_entity_decode($matches[4][$i]), $vars);

					if (
						!empty($vars['option']) && !empty($vars['task']) && !empty($vars['id']) &&
						$vars['option'] === 'com_flexicontent' && $vars['task'] === 'download_file'
					)
					{
						//Factory::getApplication()->enqueueMessage('FOUND');
						$query = $db->getQuery(true)
							->insert($db->qn('#__flexicontent_file_usage'))
							->columns(array(
								$db->qn('id'),
								$db->qn('context'),
								$db->qn('file_id'),
								$db->qn('prop'),
							))
							->values(
								$db->q($item->id) . ', ' .
								$db->q($context) . ', ' .
								$db->q($vars['id']) . ', ' .
								$db->q($path . $prop)
							);

						try {
							$db->setQuery($query)->execute();
						}
						catch (Exception $e)
						{
							continue;
						}
					}
					//Factory::getApplication()->enqueueMessage('<pre>'.htmlspecialchars(print_r($vars, true), ENT_COMPAT, 'UTF-8').'</pre>');
				}
			}
		}
	}
	
	
	/**
	 * After save event.
	 *
	 * @param   string   $context  The context
	 * @param   Table    $item     The table
	 * @param   boolean  $isNew    Is new item
	 * @param   array    $data     The validated data
	 *
	 * @return  boolean
	 *
	 * @since   3.2.0
	 */
	public function onContentAfterSave($context, $item, $isNew, $data = array())
	{
		// Workaround for wrong event triggering bug in Advanced module manager 7.x (up to at least version 7.9.2)
		if ($context === 'com_advancedmodules.module')
		{
			return $this->onExtensionAfterSave($context, $item, $isNew);
		}


		/**
		 * Various tables. Update usage of Files in download links created via the XTD-editor file button
		 */
		$this->_updateFileUsage_FcFileBtn_DownloadLinks($context, $item, $isNew, $data);

		if (($context !== 'com_content.article' && $context !== 'com_content.form') || Factory::getApplication()->input->get('isflexicontent', false, 'CMD'))
		{
			return true;
		}

		/**
		 * Call 'flexicontent' items model to update flexicontent item data: fields, version data, temporary data
		 */

		$this->_loadFcHelpersAndLanguage();

		$app  = Factory::getApplication();

		// Needed for new items, since now an item has been created
		$data['id'] = $isNew ? $item->id : $data['id'];

		// Approve new version by default, Note: this is just the default value , ACL will decide real value
		$data['vstate'] = 2;

		// RAW (flexicontent) Custom fields data, validation will be done by each field
		$data['custom']= $app->input->post->get('custom', array(), 'array');


		// ***
		// *** Load item and its fields and its type parameters
		// ***

		$cparams = ComponentHelper::getParams('com_flexicontent');
		$default_type_id = $cparams->get('jarticle_form_typeid', 1);

		// Get current type_id of the item
		if (!$isNew)
		{
			$record = Table::getInstance($type = 'flexicontent_items', $prefix = '', $config = array());
			$record->load($data['id']);
			$data['type_id'] = $record->type_id;
		}

		// Get model and set default type if type not set already (new item or existing item with no type)
		$model = new FlexicontentModelItem();

		if (empty($data['type_id']))
		{
			$types = flexicontent_html::getTypesList($type_ids=false, $check_perms = true, $published=true);
			$default_type = isset($types[$default_type_id])
				? $types[$default_type_id]
				: reset($types);
			$data['type_id'] = $default_type->id;
			$model->setId($data['id'], $data['catid'], $data['type_id']);
		}

		// These are joomla tag ids, and not fc tag ids, so unset them, until we get a more complete solution
		$tags_tmp = isset($data['tags']) ? $data['tags'] : null;
		unset($data['tags']);

		// Save FLEXIcontent item, using the provided data
		$model->store($data);

		// Replace FC tag assignments with Joomla tag assignments
		$model->mergeJTagsAssignments($_item = null, $_jtags = null, $_replaceTags = true);

		// Revert changes to data
		unset($data['vstate']);
		unset($data['custom']);
		unset($data['type_id']);
		$data['id'] = $isNew ? 0 : $data['id'];  // restore ID to zero for new items
		$data['tags'] = $tags_tmp;   // restore joomla tag ids

		//***
		//*** Call backend 'flexicontent' items model to update flexicontent temporary data
		//***

		JLoader::register('FlexicontentModelItems', JPATH_ADMINISTRATOR.'/components/com_flexicontent/models/items.php');
		$items_model = new FlexicontentModelItems();
		$items_model->updateItemCountingData(false, $item->catid);


		//***
		//*** Maintain flexicontent-specific article parameters
		//***

		$item_params = Factory::getSession()->get('flexicontent.item.data', null, 'flexicontent');

		if ($item_params)
		{
			$record = Table::getInstance($type = 'flexicontent_items', $prefix = '', $config = array());
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


	private function _loadFcHelpersAndLanguage()
	{
		Factory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR);
		Table::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_flexicontent/tables');

		require_once (JPATH_ADMINISTRATOR.'/components/com_flexicontent/defineconstants.php');
		require_once (JPATH_SITE.'/components/com_flexicontent/classes/flexicontent.fields.php');
		require_once (JPATH_SITE.'/components/com_flexicontent/classes/flexicontent.helper.php');
		require_once (JPATH_SITE.'/components/com_flexicontent/classes/flexicontent.categories.php');
		require_once (JPATH_SITE.'/components/com_flexicontent/helpers/permission.php');

		JLoader::register('FlexicontentModelItem', JPATH_BASE.'/components/com_flexicontent/models/item.php');
	}


	private function _addfavs($type, $item_ids, $user_id)
	{
		$db = Factory::getDbo();

		if (!is_array($item_ids))
		{
			$obj = new stdClass();
			$obj->itemid = (int)$item_ids;
			$obj->userid = (int)$user_id;
			$obj->type   = (int)$type;

			return $db->insertObject('#__flexicontent_favourites', $obj);
		}
		elseif (!empty($item_ids))
		{
			$vals = array();
			foreach($item_ids as $item_id) $vals[]= ''
				. '('
				. ((int)$item_id)  . ', '
				. ((int)$user_id)  . ', '
				. ((int)$type)
				. ')';
			$query = 'INSERT INTO #__flexicontent_favourites'
				. ' (itemid, userid, type) VALUES ' . implode(',', $vals);
			$db->setQuery($query)->execute();
		}
	}


	// Method to execute a task when an action on a value is performed
	public function onFieldValueAction_FC(&$field, $item, $value_order, $config)
	{
		$handled_types = array('file', 'weblink');

		if (!in_array($field->field_type, $handled_types))
		{
			return;
		}

		//echo '<pre>' . get_class($this) . '::' . __FUNCTION__ . "()\n\n"; print_r($config); echo '</pre>'; die('TEST code reached exiting');

		/**
		 * false is failure, indicates abort further actions
		 * true is success
		 * null is no work done
		 */
		return null;
	}


	private function _setExecConfig()
	{
		// Display fatal errors, warnings, notices
		error_reporting(E_ERROR || E_WARNING || E_NOTICE);
		ini_set('display_errors',1);

		// Try to increment some limits
		@ set_time_limit( 3600 );   // try to set execution time 60 minutes
		ignore_user_abort( true ); // continue execution if client disconnects

		// Try to increment memory limits
		$memory_limit	= trim( @ ini_get( 'memory_limit' ) );
		if ( $memory_limit )
		{
			switch (strtolower(substr($memory_limit, -1)))
			{
				case 'm': $memory_limit = (int)substr($memory_limit, 0, -1) * 1048576; break;
				case 'k': $memory_limit = (int)substr($memory_limit, 0, -1) * 1024; break;
				case 'g': $memory_limit = (int)substr($memory_limit, 0, -1) * 1073741824; break;
				case 'b':
				switch (strtolower(substr($memory_limit, -2, 1)))
				{
					case 'm': $memory_limit = (int)substr($memory_limit, 0, -2) * 1048576; break;
					case 'k': $memory_limit = (int)substr($memory_limit, 0, -2) * 1024; break;
					case 'g': $memory_limit = (int)substr($memory_limit, 0, -2) * 1073741824; break;
					default : break;
				} break;
				default: break;
			}
			if ( $memory_limit < 16 * 1024 * 1024 ) @ ini_set( 'memory_limit', '16M' );
			if ( $memory_limit < 32 * 1024 * 1024 ) @ ini_set( 'memory_limit', '32M' );
			if ( $memory_limit < 64 * 1024 * 1024 ) @ ini_set( 'memory_limit', '64M' );
			if ( $memory_limit < 128 * 1024 * 1024 ) @ ini_set( 'memory_limit', '128M' );
			if ( $memory_limit < 256 * 1024 * 1024 ) @ ini_set( 'memory_limit', '256M' );
		}
	}
}
