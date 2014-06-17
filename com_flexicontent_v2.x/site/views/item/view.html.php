<?php
/**
 * @version 1.5 stable $Id: view.html.php 1911 2014-06-12 01:55:58Z ggppdk $
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

jimport('joomla.application.component.view');

/**
 * HTML View class for the Item View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewItem  extends JViewLegacy
{
	var $_type = '';
	var $_name = FLEXI_ITEMVIEW;

	/**
	 * Creates the page's display
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		// check for form layout
		if($this->getLayout() == 'form' || in_array(JRequest::getVar('task'), array('add','edit')) ) {
			// Important set layout to be form since various category view SEF links have this variable set
			$this->setLayout('form');
			$this->_displayForm($tpl);
			return;
		} else {
			$this->setLayout('item');
		}

		// Get Content Types with no category links in item view pathways, and for unroutable (non-linkable) categories
		global $globalnoroute, $globalnopath, $globalcats;
		if (!is_array($globalnopath))  $globalnopath  = array();
		if (!is_array($globalnoroute)) $globalnoroute = array();
		
		//initialize variables
		$dispatcher = JDispatcher::getInstance();
		$app      = JFactory::getApplication();
		$session  = JFactory::getSession();
		$document = JFactory::getDocument();
		$menus = $app->getMenu();
		$menu  = $menus->getActive();
		$uri   = JFactory::getURI();
		$user  = JFactory::getUser();
		$aid   = FLEXI_J16GE ? $user->getAuthorisedViewLevels() : (int) $user->get('aid');
		$db    = JFactory::getDBO();
		$nullDate = $db->getNullDate();
		
		
		// ******************************************************
		// Get item, model and create form (that loads item data)
		// ******************************************************
		
		// Get various data from the model
		$model  = $this->getModel();
		$cid    = $model->_cid ? $model->_cid : $model->get('catid');  // Get current category id
		
		// we are in display() task, so we will load the current item version by default
		// 'preview' request variable will force last, and finally 'version' request variable will force specific
		// NOTE: preview and version variables cannot be used by users that cannot edit the item
		JRequest::setVar('loadcurrent', true);
		
		// Try to load existing item, an 404 error will be raised if item is not found. Also value 2 for check_view_access
		// indicates to raise 404 error for ZERO primary key too, instead of creating and returning a new item object
		$start_microtime = microtime(true);
		$item = $model->getItem(null, $check_view_access=2);
		$_run_time = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		
		// Set item parameters as VIEW's parameters (item parameters are merged with component/page/type/current category/access parameters already)
		$params = $item->parameters;
		
		$print_logging_info = $params->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		if ( $print_logging_info ) $fc_run_times['get_item_data'] = $_run_time;
		
		
		// ********************************
		// Load needed JS libs & CSS styles
		// ********************************
		
		//add css file
		if (!$params->get('disablecss', '')) {
			$document->addStyleSheet($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css');
			$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext {zoom:1;}</style><![endif]-->');
		}
		
		//allow css override
		if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css')) {
			$document->addStyleSheet($this->baseurl.'/templates/'.$app->getTemplate().'/css/flexicontent.css');
		}
		//special to hide the joomfish language selector on item views
		if ($params->get('disable_lang_select', 0)) {
			$css = '#jflanguageselection { visibility:hidden; }';
			$document->addStyleDeclaration($css);
		}
		
		
		// ********************
		// ITEM LAYOUT handling
		// ********************

		// (a) Decide to use mobile or normal item template layout
		$useMobile = $params->get('use_mobile_layouts', 0 );
		if ($useMobile) {
			$force_desktop_layout = $params->get('force_desktop_layout', 0 );
			$mobileDetector = flexicontent_html::getMobileDetector();
			$isMobile = $mobileDetector->isMobile();
			$isTablet = $mobileDetector->isTablet();
			$useMobile = $force_desktop_layout  ?  $isMobile && !$isTablet  :  $isMobile;
		}
		$_ilayout = $useMobile ? 'ilayout_mobile' : 'ilayout';

		// (b) Get from item parameters, allowing URL override
		$ilayout = JRequest::getVar($_ilayout, false);
		if (!$ilayout) {
			$desktop_ilayout = $params->get('ilayout', 'default');
			$ilayout = !$useMobile ? $desktop_ilayout : $params->get('ilayout_mobile', $desktop_ilayout);
		}
		
		// (c) Create the type parameters
		$tparams = $this->get( 'Typeparams' );
		$tparams = FLEXI_J16GE ? new JRegistry($tparams) : new JParameter($tparams);

		// (d) Verify the layout is within templates, Content Type default template OR Content Type allowed templates
		$allowed_tmpls = $tparams->get('allowed_ilayouts');
		$type_default_layout = $tparams->get('ilayout', 'default');
		if ( empty($allowed_tmpls) )							$allowed_tmpls = array();
		else if ( ! is_array($allowed_tmpls) )		$allowed_tmpls = !FLEXI_J16GE ? array($allowed_tmpls) : explode("|", $allowed_tmpls);

		// (e) Verify the item layout is within templates: Content Type default template OR Content Type allowed templates
		if ( $ilayout!=$type_default_layout && count($allowed_tmpls) && !in_array($ilayout,$allowed_tmpls) ) {
			$app->enqueueMessage("<small>Current Item Layout Template is '$ilayout':<br/>- This is neither the Content Type Default Template, nor does it belong to the Content Type allowed templates.<br/>- Please correct this in the URL or in Content Type configuration.<br/>- Using Content Type Default Template Layout: '$type_default_layout'</small>", 'notice');
			$ilayout = $type_default_layout;
		}

		// (f) Get cached template data
		$themes = flexicontent_tmpl::getTemplates( $lang_files = array($ilayout) );

		// (g) Verify the item layout exists
		if ( !isset($themes->items->{$ilayout}) ) {
			$fixed_ilayout = isset($themes->items->{$type_default_layout}) ? $type_default_layout : 'default';
			$app->enqueueMessage("<small>Current Item Layout Template is '$ilayout' does not exist<br/>- Please correct this in the URL or in Content Type configuration.<br/>- Using Template Layout: '$fixed_ilayout'</small>", 'notice');
			$ilayout = $fixed_ilayout;
			if (FLEXI_FISH || FLEXI_J16GE) FLEXIUtilities::loadTemplateLanguageFile( $ilayout ); // Manually load Template-Specific language file of back fall ilayout
		}

		// (h) finally set the template name back into the item's parameters
		$params->set('ilayout', $ilayout);

		// Bind Fields
		$_items = array(&$item);
		FlexicontentFields::getFields($_items, FLEXI_ITEMVIEW, $params, $aid);

		// Note : This parameter doesn't exist yet but it will be used by the future gallery template
		/*if ($params->get('use_panes', 1)) {
			jimport('joomla.html.pane');
			$pane = JPane::getInstance('Tabs');
			$this->assignRef('pane', $pane);
		}*/

		$fields = $item->fields;
		
		// Pathway needed variables
		//$catshelper = new flexicontent_cats($cid);
		//$parents    = $catshelper->getParentlist();
		//echo "<pre>".print_r($parents,true)."</pre>";
		$parents = array();
		if ( $cid && isset($globalcats[$cid]->ancestorsarray) ) {
			$parent_ids = $globalcats[$cid]->ancestorsarray;
			foreach ($parent_ids as $parent_id) $parents[] = $globalcats[$parent_id];
		}
		
		
		
		// **********************************************************
		// Calculate a (browser window) page title and a page heading
		// **********************************************************
		
		// Verify menu item points to current FLEXIcontent object
		if ( $menu ) {
			$view_ok = FLEXI_ITEMVIEW          == @$menu->query['view'] || 'article' == @$menu->query['view'];
			$cid_ok  = JRequest::getInt('cid') == (int) @$menu->query['cid'];
			$id_ok   = JRequest::getInt('id')  == (int) @$menu->query['id'];
			$menu_matches = $view_ok /*&& $cid_ok*/ && $id_ok;
			//$menu_params = FLEXI_J16GE ? $menu->params : new JParameter($menu->params);  // Get active menu item parameters
		} else {
			$menu_matches = false;
		}
		
		// MENU ITEM matched, use its page heading (but use menu title if the former is not set)
		if ( $menu_matches ) {
			$default_heading = FLEXI_J16GE ? $menu->title : $menu->name;
			
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
			$default_heading = $item->title;
			
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
			if ($params->get('page_heading') == $item->title) $params->set('show_page_heading', 0);
			if ($params->get('page_title')   == $item->title) $params->set('show_page_title',   0);
		}
		
		
		
		
		// ************************************************************
		// Create the document title, by from page title and other data
		// ************************************************************
		
		// Use the page heading as document title, (already calculated above via 'appropriate' logic ...)
		// or the overriden custom <title> ... set via parameter
		$doc_title  =  !$params->get('override_title', 0)  ?  $params->get( 'page_title' )  :  $params->get( 'custom_ititle', $item->title);
		
		// Check and prepend category title
		if ( $params->get('addcat_title', 1) && count($parents) ) {
			$parentcat = end($parents);
			$doc_title = (isset($parentcat->title) ? $parentcat->title.' - ' : '') . $doc_title;
		}
		
		// Check and prepend or append site name
		if (FLEXI_J16GE) {  // Not available in J1.5
			// Add Site Name to page title
			if ($app->getCfg('sitename_pagetitles', 0) == 1) {
				$doc_title = $app->getCfg('sitename') ." - ". $doc_title ;
			}
			elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
				$doc_title = $doc_title ." - ". $app->getCfg('sitename') ;
			}
		}
		
		// Finally, set document title
		$document->setTitle($doc_title);
		
		
		// ************************
		// Set document's META tags
		// ************************

		// Set item's META data: desc, keyword, title, author
		if ($item->metadesc)		$document->setDescription( $item->metadesc );
		if ($item->metakey)			$document->setMetadata('keywords', $item->metakey);
		// ?? Deprecated <title> tag is used instead by search engines
		if ($app->getCfg('MetaTitle') == '1')		$document->setMetaData('title', $item->title);
		if ($app->getCfg('MetaAuthor') == '1')	$document->setMetaData('author', $item->author);

		// Set remaining META keys
		$mdata = $item->metadata->toArray();
		foreach ($mdata as $k => $v)
		{
			if ($v)  $document->setMetadata($k, $v);
		}
		
		// Overwrite with menu META data if menu matched
		if (FLEXI_J16GE) {
			if ($menu_matches) {
				if (($_mp=$menu->params->get('menu-meta_description')))  $document->setDescription( $_mp );
				if (($_mp=$menu->params->get('menu-meta_keywords')))     $document->setMetadata('keywords', $_mp);
				if (($_mp=$menu->params->get('robots')))                 $document->setMetadata('robots', $_mp);
				if (($_mp=$menu->params->get('secure')))                 $document->setMetadata('secure', $_mp);
			}
		}
		
		
		// ************************************
		// Add rel canonical html head link tag (TODO: improve multi-page handing)
		// ************************************
		
		$base  = $uri->getScheme() . '://' . $uri->getHost();
		$ucanonical = $base . JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $globalcats[$item->maincatid]->slug, 0, $item));  // $item->categoryslug
		if ($params->get('add_canonical')) {
			$document->addHeadLink( $ucanonical, 'canonical', 'rel', '' );
		}
		
		
		// *************************
		// increment the hit counter
		// *************************
		// MOVED to flexisystem plugin due to ...
		/*if (FLEXIUtilities::count_new_hit($item->id) ) {
			$model->hit();
		}*/

		// Load template css/js and set template data variable
		$tmplvar	= $themes->items->{$ilayout}->tmplvar;
		if ($ilayout) {
			// Add the templates css files if availables
			if (isset($themes->items->{$ilayout}->css)) {
				foreach ($themes->items->{$ilayout}->css as $css) {
					$document->addStyleSheet($this->baseurl.'/'.$css);
				}
			}
			// Add the templates js files if availables
			if (isset($themes->items->{$ilayout}->js)) {
				foreach ($themes->items->{$ilayout}->js as $js) {
					$document->addScript($this->baseurl.'/'.$js);
				}
			}
			// Set the template var
			$tmpl = $themes->items->{$ilayout}->tmplvar;
		} else {
			$tmpl = '.items.default';
		}

		// Just put item's text (description field) inside property 'text' in case the events modify the given text,
		$item->text = isset($item->fields['text']->display) ? $item->fields['text']->display : '';

		// Maybe here not to import all plugins but just those for description field ???
		// Anyway these events are usually not very time consuming, so lets trigger all of them ???
		JPluginHelper::importPlugin('content');

		// Suppress some plugins from triggering for compatibility reasons, e.g.
		// (a) jcomments, jom_comment_bot plugins, because we will get comments HTML manually inside the template files
		$suppress_arr = array('jcomments', 'jom_comment_bot');
		FLEXIUtilities::suppressPlugins($suppress_arr, 'suppress' );

		// Do some compatibility steps, Set the view and option to 'article' and 'com_content'
		JRequest::setVar('view', 'article');
		JRequest::setVar('option', 'com_content');
		JRequest::setVar("isflexicontent", "yes");
		$limitstart = JRequest::getVar('limitstart', 0, '', 'int');

		// These events return text that could be displayed at appropriate positions by our templates
		$item->event = new stdClass();

		if (FLEXI_J16GE)  $results = $dispatcher->trigger('onContentAfterTitle', array('com_content.article', &$item, &$params, 0));
		else              $results = $dispatcher->trigger('onAfterDisplayTitle', array (&$item, &$params, $limitstart));
		$item->event->afterDisplayTitle = trim(implode("\n", $results));

		if (FLEXI_J16GE)  $results = $dispatcher->trigger('onContentBeforeDisplay', array('com_content.article', &$item, &$params, 0));
		else              $results = $dispatcher->trigger('onBeforeDisplayContent', array (&$item, &$params, $limitstart));
		$item->event->beforeDisplayContent = trim(implode("\n", $results));

		if (FLEXI_J16GE)  $results = $dispatcher->trigger('onContentAfterDisplay', array('com_content.article', &$item, &$params, 0));
		else              $results = $dispatcher->trigger('onAfterDisplayContent', array (&$item, &$params, $limitstart));
		$item->event->afterDisplayContent = trim(implode("\n", $results));

		// Reverse the compatibility steps, set the view and option back to 'items' and 'com_flexicontent'
		JRequest::setVar('view', FLEXI_ITEMVIEW);
		JRequest::setVar('option', 'com_flexicontent');

		// Restore suppressed plugins
		FLEXIUtilities::suppressPlugins($suppress_arr, 'restore' );

		// Put text back into the description field, THESE events SHOULD NOT modify the item text, but some plugins may do it anyway... , so we assign text back for compatibility
		if ( !empty($item->positions) ) {
			foreach($item->positions as $pos_fields) {
				foreach($pos_fields as $pos_field) {
					if ($pos_field->name!=='text') continue;
					$pos_field->display = & $item->text;
				}
			}
		}
		$item->fields['text']->display = & $item->text;

		// (TOC) TABLE OF Contents has been created inside description field (named 'text') by
		// the pagination plugin, this should be assigned to item as a property with same name
		if(isset($item->fields['text']->toc)) {
			$item->toc = &$item->fields['text']->toc;
		}
		
		// ********************************************************************************************
		// Create pathway, if automatic pathways is enabled, then path will be cleared before populated
		// ********************************************************************************************
		$pathway = $app->getPathWay();
		
		// Clear pathway, if automatic pathways are enabled
		if ( $params->get('automatic_pathways', 0) ) {
			$pathway_arr = $pathway->getPathway();
			$pathway->setPathway( array() );
			//$pathway->set('_count', 0);  // not needed ??
			$item_depth = 0;  // menu item depth is now irrelevant ???, ignore it
		} else {
			$item_depth = $params->get('item_depth', 0);
		}
		
		// Respect menu item depth, defined in menu item
		$p = $item_depth;
		while ( $p < count($parents) ) {
			// For some Content Types the pathway should not be populated with category links
			if ( in_array($item->type_id, $globalnopath) )  break;
			
			// Do not add to pathway unroutable categories
			if ( in_array($parents[$p]->id, $globalnoroute) )  { $p++; continue; }
			
			// Add current parent category
			$pathway->addItem( $this->escape($parents[$p]->title), JRoute::_( FlexicontentHelperRoute::getCategoryRoute($parents[$p]->slug) ) );
			$p++;
		}
		if ($params->get('add_item_pathway', 1)) {
			$pathway->addItem( $this->escape($item->title), JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, 0, $item)) );
		}
		
		// **********************************************************************
		// Print link ... must include layout and current filtering url vars, etc
		// **********************************************************************
		
    $curr_url = $_SERVER['REQUEST_URI'];
    $print_link = $curr_url .(strstr($curr_url, '?') ? '&amp;'  : '?').'pop=1&amp;tmpl=component&amp;print=1';//.(count($filt_vars)? '&amp;' : '').implode("&amp;", $filt_vars);
		//$print_link = JRoute::_('index.php?view='.FLEXI_ITEMVIEW.'&cid='.$item->categoryslug.'&id='.$item->slug.'&pop=1&tmpl=component&print=1');
		$pageclass_sfx = htmlspecialchars($params->get('pageclass_sfx'));
		
		$this->assignRef('item' , 				$item);
		$this->assignRef('user' , 				$user);
		$this->assignRef('params' , 			$params);
		$this->assignRef('print_link' , 	$print_link);
		$this->assignRef('pageclass_sfx' ,$pageclass_sfx);
		$this->assignRef('parentcat',			$parentcat);
		$this->assignRef('fields',				$item->fields);
		$this->assignRef('tmpl' ,					$tmpl);

		/*
		 * Set template paths : this procedure is issued from K2 component
		 *
		 * "K2" Component by JoomlaWorks for Joomla! 1.5.x - Version 2.1
		 * Copyright (c) 2006 - 2009 JoomlaWorks Ltd. All rights reserved.
		 * Released under the GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
		 * More info at http://www.joomlaworks.gr and http://k2.joomlaworks.gr
		 * Designed and developed by the JoomlaWorks team
		 */
		$this->addTemplatePath(JPATH_COMPONENT.DS.'templates');
		$this->addTemplatePath(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates');
		$this->addTemplatePath(JPATH_COMPONENT.DS.'templates'.DS.'default');
		$this->addTemplatePath(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates'.DS.'default');
		if ($ilayout) {
			$this->addTemplatePath(JPATH_COMPONENT.DS.'templates'.DS.$ilayout);
			$this->addTemplatePath(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates'.DS.$ilayout);
		}

		if ( $print_logging_info ) $start_microtime = microtime(true);
		parent::display($tpl);
		if ( $print_logging_info ) $fc_run_times['template_render'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	}

	/**
	 * Creates the item submit form
	 *
	 * @since 1.0
	 */
	function _displayForm($tpl)
	{
		jimport( 'joomla.html.parameter' );

		// ... we use some strings from administrator part
		// load english language file for 'com_content' component then override with current language file
		JFactory::getLanguage()->load('com_content', JPATH_ADMINISTRATOR, 'en-GB', true);
		JFactory::getLanguage()->load('com_content', JPATH_ADMINISTRATOR, null, true);
		// load english language file for 'com_flexicontent' component then override with current language file
		JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
		JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);

		// ********************************
		// Initialize variables, flags, etc
		// ********************************
		$app        = JFactory::getApplication();
		$dispatcher = JDispatcher::getInstance();
		$document   = JFactory::getDocument();
		$session    = JFactory::getSession();
		$user       = JFactory::getUser();
		$db         = JFactory::getDBO();
		$uri        = JFactory::getURI();
		$nullDate   = $db->getNullDate();
		$menu				= $app->getMenu()->getActive();
		
		// Get the COMPONENT only parameters, then merge the menu parameters
		$comp_params = JComponentHelper::getComponent('com_flexicontent')->params;
		$params = FLEXI_J16GE ? clone ($comp_params) : new JParameter( $comp_params ); // clone( JComponentHelper::getParams('com_flexicontent') );
		if ($menu) {
			$menu_params = FLEXI_J16GE ? $menu->params : new JParameter($menu->params);
			$params->merge($menu_params);
		}
		
		// Some flags
		$enable_translation_groups = $params->get("enable_translation_groups") && ( FLEXI_J16GE || FLEXI_FISH ) ;
		$print_logging_info = $params->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		
		
		// *****************
		// Load JS/CSS files
		// *****************
		
		FLEXI_J30GE ? JHtml::_('behavior.framework', true) : JHTML::_('behavior.mootools');
		flexicontent_html::loadFramework('jQuery');
		flexicontent_html::loadFramework('select2');
		
		// Load custom behaviours: form validation, popup tooltips
		//JHTML::_('behavior.formvalidation');
		JHTML::_('behavior.tooltip');
		//JHTML::_('script', 'joomla.javascript.js', 'includes/js/');

		// Add css files to the document <head> section (also load CSS joomla template override)
		$document->addStyleSheet( JURI::base(true).'/components/com_flexicontent/assets/css/flexicontent.css' );
		if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css')) {
			$document->addStyleSheet(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css');
		}
		if (!FLEXI_J16GE) {
			$document->addStyleSheet($this->baseurl.'/administrator/templates/khepri/css/general.css');
		}
		//$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #flexicontent dd { height: 1%; }</style><![endif]-->');
		
		// Load backend / frontend shared and Joomla version specific CSS (different for frontend / backend)
		$document->addStyleSheet( JURI::base(true).'/components/com_flexicontent/assets/css/flexi_shared.css' );  // NOTE: this is imported by main Frontend CSS file
		if      (FLEXI_J30GE) $document->addStyleSheet( JURI::base(true).'/components/com_flexicontent/assets/css/j3x.css' );
		else if (FLEXI_J16GE) $document->addStyleSheet( JURI::base(true).'/components/com_flexicontent/assets/css/j25.css' );
		else                  $document->addStyleSheet( JURI::base(true).'/components/com_flexicontent/assets/css/j15.css' );
		
		// Add js function to overload the joomla submitform
		$document->addScript(JURI::base(true).'/components/com_flexicontent/assets/js/admin.js');
		$document->addScript(JURI::base(true).'/components/com_flexicontent/assets/js/validate.js');
		
		// Add js function for custom code used by FLEXIcontent item form
		$document->addScript( JURI::base(true).'/components/com_flexicontent/assets/js/itemscreen.js' );
		
		
		// ***********************************************
		// Get item and create form (that loads item data)
		// ***********************************************

		if ( $print_logging_info )  $start_microtime = microtime(true);

		$model = $this->getModel();
		
		// ** WE NEED TO get OR decide the Content Type, before we call the getItem
		// ** We rely on typeid Request variable to decide type for new items so make sure this is set,
		// ZERO means allow user to select type, but if user is only allowed a single type, then autoselect it!
		if ( $menu && isset($menu->query['typeid']) )
		{
			JRequest::setVar('typeid', (int)$menu->query['typeid']);  // This also forces zero if value not set
		}
		$new_typeid = JRequest::getVar('typeid', 0, '', 'int');
		if ( !$new_typeid )
		{
			$types = $model->getTypeslist($type_ids_arr = false, $check_perms = true);
			if ( $types && count($types)==1 ) $new_typeid = $types[0]->id;
			JRequest::setVar('typeid', $new_typeid);
			$canCreateType = true;
		}
		
		$item = $this->get('Item');
		if (FLEXI_J16GE) {
			$form = $this->get('Form');
		}
		
		if ( $print_logging_info ) $fc_run_times['get_item_data'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;


		// *********************************************************************************************************
		// Get language stuff, and also load Template-Specific language file to override or add new language strings
		// *********************************************************************************************************
		if ($enable_translation_groups)  $langAssocs = $this->get( 'LangAssocs' );
		if (FLEXI_FISH || FLEXI_J16GE)   $langs = FLEXIUtilities::getLanguages('code');

		if (FLEXI_FISH || FLEXI_J16GE)
			FLEXIUtilities::loadTemplateLanguageFile( $item->parameters->get('ilayout', 'default') );



		// ****************************************************************************************
		// CHECK EDIT / CREATE PERMISSIONS (this is duplicate since it also done at the controller)
		// ****************************************************************************************

		// new item and ownership variables
		$isnew = !$item->id;
		$isOwner = ( $item->created_by == $user->get('id') );
		
		// create and set (into HTTP request) a unique item id for plugins that needed it
		JRequest::setVar( 'unique_tmp_itemid', $item->id ? $item->id : date('_Y_m_d_h_i_s_', time()) . uniqid(true) );
		
		// Component / Menu Item parameters
		$allowunauthorize   = $params->get('allowunauthorize', 0);     // allow unauthorised user to submit new content
		$unauthorized_page  = $params->get('unauthorized_page', '');   // page URL for unauthorized users (via global configuration)
		$notauth_itemid     = $params->get('notauthurl', '');          // menu itemid (to redirect) when user is not authorized to create content
		
		// Create captcha field or messages
		if (FLEXI_J16GE) {
			$use_captcha    = $params->get('use_captcha', 1);     // 1 for guests, 2 for any user
			$captcha_formop = $params->get('captcha_formop', 0);  // 0 for submit, 1 for submit/edit (aka always)
			$display_captcha = $use_captcha >= 2 || ( $use_captcha == 1 &&  $user->guest );
			$display_captcha = $display_captcha && ($isnew || $captcha_formop);
			
			// Force using recaptcha
			if ($display_captcha) {
				// Try to force the use of recaptcha plugin
				JFactory::getConfig()->set('captcha', 'recaptcha');
				
				if ( !$app->getCfg('captcha') ) {
					$captcha_errmsg  = '-- Please select <b>CAPTCHA Type</b> at global Joomla parameters';
				} else if ($app->getCfg('captcha') != 'recaptcha') {
					$captcha_errmsg  = '-- Captcha Type: <b>'.$app->getCfg('captcha').'</b> not supported';
				} else if ( ! JPluginHelper::isEnabled('captcha', 'recaptcha') ) {
					$captcha_errmsg  = '-- Please enable & configure the Joomla <b>ReCaptcha Plugin</b>';
				} else {
					$captcha_errmsg  = '';
					
					JPluginHelper::importPlugin('captcha');
					$dispatcher->trigger('onInit','dynamic_recaptcha_1');
					
					$field_description = JText::_( 'FLEXI_CAPTCHA_ENTER_CODE_DESC' );
					$label_tooltip = 'class="hasTip flexi_label" title="'.'::'.htmlspecialchars($field_description, ENT_COMPAT, 'UTF-8').'"';
					
					$captcha_field = '
						<label id="recaptcha_response_field-lbl" for="recaptcha_response_field" '.$label_tooltip.' >
						'. JText::_( 'FLEXI_CAPTCHA_ENTER_CODE' ).'
						</label>
						<div class="container_fcfield container_fcfield_name_captcha">
							<div id="dynamic_recaptcha_1"></div>
						</div>
						';
				}
			}
		}
		
		// User Group / Author parameters
		$db->setQuery('SELECT author_basicparams FROM #__flexicontent_authors_ext WHERE user_id = ' . $user->id);
		$authorparams = $db->loadResult();
		$authorparams = FLEXI_J16GE ? new JRegistry($authorparams) : new JParameter($authorparams);
		$max_auth_limit = $authorparams->get('max_auth_limit', 0);  // maximum number of content items the user can create

		if (!$isnew)
		{
			// EDIT action

			// Finally check if item is currently being checked-out (currently being edited)
			if ($model->isCheckedOut($user->get('id')))
			{
				$msg = JText::sprintf('FLEXI_DESCBEINGEDITTED', $model->get('title'));
				$app->redirect(JRoute::_('index.php?view='.FLEXI_ITEMVIEW.'&cid='.$model->get('catid').'&id='.$model->get('id'), false), $msg);
			}

			//Checkout the item
			$model->checkout();

			if (FLEXI_J16GE) {
				$canEdit = $model->getItemAccess()->get('access-edit'); // includes privileges edit and edit-own
				// ALTERNATIVE 1
				//$asset = 'com_content.article.' . $model->get('id');
				//$canEdit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $model->get('created_by') == $user->get('id'));
				// ALTERNATIVE 2
				//$rights = FlexicontentHelperPerm::checkAllItemAccess($user->get('id'), 'item', $model->get('id'));
				//$canEdit = in_array('edit', $rights) || (in_array('edit.own', $rights) && $model->get('created_by') == $user->get('id')) ;
			} else if ($user->gid >= 25) {
				$canEdit = true;
			} else if (FLEXI_ACCESS) {
				$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $model->get('id'), $model->get('catid'));
				$canEdit = in_array('edit', $rights) || (in_array('editown', $rights) && $model->get('created_by') == $user->get('id')) ;
			} else {
				$canEdit = $user->authorize('com_content', 'edit', 'content', 'all') || ($user->authorize('com_content', 'edit', 'content', 'own') && $model->get('created_by') == $user->get('id'));
				//$canEdit = ($user->gid >= 20);  // At least J1.5 Editor
			}

			if ( !$canEdit ) {
				// No edit privilege, check if item is editable till logoff
				if ($session->has('rendered_uneditable', 'flexicontent')) {
					$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
					$canEdit = isset($rendered_uneditable[$model->get('id')]) && $rendered_uneditable[$model->get('id')];
				}
			}
			
			if (!$canEdit) {
				if ($user->guest) {
					$uri		= JFactory::getURI();
					$return		= $uri->toString();
					$fcreturn = serialize( array('id'=>@$this->_item->id, 'cid'=>$cid) );     // a special url parameter, used by some SEF code
					$com_users = FLEXI_J16GE ? 'com_users' : 'com_user';
					$url  = $params->get('login_page', 'index.php?option='.$com_users.'&view=login');
					$return = strtr(base64_encode($return), '+/=', '-_,');
					$url .= '&return='.$return;
					//$url .= '&return='.urlencode(base64_encode($return));
					$url .= '&fcreturn='.base64_encode($fcreturn);

					JError::raiseWarning( 403, JText::sprintf("FLEXI_LOGIN_TO_ACCESS", $url));
					$app->redirect( $url );
				} else if ($unauthorized_page) {
					//  unauthorized page via global configuration
					JError::raiseNotice( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
					$app->redirect($unauthorized_page);
				} else {
					// user isn't authorize to edit this content
					$msg = JText::_( 'FLEXI_ALERTNOTAUTH_TASK' );
					if (FLEXI_J16GE) throw new Exception($msg, 403); else JError::raiseError(403, $msg);
				}
			}

		} else {
			// CREATE action

			if (FLEXI_J16GE) {
				$canAdd = $model->getItemAccess()->get('access-create'); // includes check of creating in at least one category
				$not_authorised = !$canAdd;
			} else if ($user->gid >= 25) {
				$not_authorised = 0;
			} else if (FLEXI_ACCESS) {
				$canAdd = FAccess::checkUserElementsAccess($user->gmid, 'submit');
				$not_authorised = ! ( @$canAdd['content'] || @$canAdd['category'] );
			} else {
				$canAdd	= $user->authorize('com_content', 'add', 'content', 'all');
				//$canAdd = ($user->gid >= 19);  // At least J1.5 Author
				$not_authorised = ! $canAdd;
			}
			
			// Check if Content Type can be created by current user
			if ( empty($canCreateType) ) {
				if ($new_typeid) {
					$canCreateType = $model->canCreateType( array($new_typeid) );  // Can create given Content Type
				} else {
					$canCreateType = $model->canCreateType( );  // Can create at least one Content Type
				}
			}
			$not_authorised = $not_authorised || !$canCreateType;
			
			// Allow item submission by unauthorized users, ... even guests ...
			if ($allowunauthorize == 2) $allowunauthorize = ! $user->guest;

			if ($not_authorised && !$allowunauthorize)
			{
				if ( !$canCreateType ) {
					$type_name = isset($types[$new_typeid]) ? '"'.JText::_($types[$new_typeid]->name).'"' : JText::_('FLEXI_ANY');
					$msg = JText::sprintf( 'FLEXI_NO_ACCESS_CREATE_CONTENT_OF_TYPE', $type_name );
				} else {
					$msg = JText::_( 'FLEXI_ALERTNOTAUTH_CREATE' );
				}
			} else if ($max_auth_limit) {
				$db->setQuery('SELECT COUNT(id) FROM #__content WHERE created_by = ' . $user->id);
				$authored_count = $db->loadResult();
				$content_is_limited = $authored_count >= $max_auth_limit;
				$msg = $content_is_limited ? JText::sprintf( 'FLEXI_ALERTNOTAUTH_CREATE_MORE', $max_auth_limit ) : '';
			}
			
			if ( ($not_authorised && !$allowunauthorize) || @ $content_is_limited ) {
				// User isn't authorize to add ANY content
				if ( $notauth_menu = $app->getMenu()->getItem($notauth_itemid) ) {
					// a. custom unauthorized submission page via menu item
					$internal_link_vars = @ $notauth_menu->component ? '&Itemid='.$notauth_itemid.'&option='.$notauth_menu->component : '';
					$notauthurl = JRoute::_($notauth_menu->link.$internal_link_vars, false);
					JError::raiseNotice( 403, $msg );
					$app->redirect($notauthurl);
				} else if ($unauthorized_page) {
					// b. General unauthorized page via global configuration
					JError::raiseNotice( 403, $msg );
					$app->redirect($unauthorized_page);
				} else {
					// c. Finally fallback to raising a 403 Exception/Error that will redirect to site's default 403 unauthorized page
					if (FLEXI_J16GE) throw new Exception($msg, 403); else JError::raiseError(403, $msg);
				}
			}

		}


		// *********************************************
		// Get more variables to push into the FORM view
		// *********************************************

		// Create the type parameters
		$tparams = $this->get( 'Typeparams' );
		$tparams = FLEXI_J16GE ? new JRegistry($tparams) : new JParameter($tparams);

		// Merge item parameters, or type/menu parameters for new item
		if ( $isnew ) {
			if ( $new_typeid ) $params->merge($tparams);       // Apply type configuration if it type is set
			if ( $menu )   $params->merge($menu_params);  // Apply menu configuration if it menu is set, to override type configuration
		} else {
			$params = $item->parameters;
		}
		
		
		// Check if saving an item that translates an original content in site's default language
		$is_content_default_lang = substr(flexicontent_html::getSiteDefaultLang(), 0,2) == substr($item->language, 0,2);
		$modify_untraslatable_values = $enable_translation_groups && !$is_content_default_lang && $item->lang_parent_id && $item->lang_parent_id!=$item->id;
		
		// *****************************************************************************
		// Get (CORE & CUSTOM) fields and their VERSIONED values and then
		// (a) Apply Content Type Customization to CORE fields (label, description, etc)
		// (b) Create the edit html of the CUSTOM fields by triggering 'onDisplayField'
		// *****************************************************************************
		
		if ( $print_logging_info )  $start_microtime = microtime(true);
		$fields = $this->get( 'Extrafields' );
		if ( $print_logging_info ) $fc_run_times['get_field_vals'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

		if ( $print_logging_info )  $start_microtime = microtime(true);
		foreach ($fields as $field)
		{
			// a. Apply CONTENT TYPE customizations to CORE FIELDS, e.g a type specific label & description
			// NOTE: the field parameters are already created so there is not need to call this for CUSTOM fields, which do not have CONTENT TYPE customizations
			if ($field->iscore) {
				FlexicontentFields::loadFieldConfig($field, $item);
			}

			// b. Create field 's editing HTML (the form field)
			// NOTE: this is DONE only for CUSTOM fields, since form field html is created by the form for all CORE fields, EXCEPTION is the 'text' field (see bellow)
			if (!$field->iscore)
			{
				if (FLEXI_J16GE)
					$is_editable = !$field->valueseditable || $user->authorise('flexicontent.editfieldvalues', 'com_flexicontent.field.' . $field->id);
				else if (FLEXI_ACCESS && $user->gid < 25)
					$is_editable = !$field->valueseditable || FAccess::checkAllContentAccess('com_content','submit','users', $user->gmid, 'field', $field->id);
				else
					$is_editable = 1;

				if ( !$is_editable ) {
					$field->html = '<div class="fc-mssg fc-warning">'. JText::_('FLEXI_NO_ACCESS_LEVEL_TO_EDIT_FIELD') . '</div>';
				} else if ($modify_untraslatable_values && $field->untranslatable) {
					$field->html = '<div class="fc-mssg fc-note">'. JText::_('FLEXI_FIELD_VALUE_IS_UNTRANSLATABLE') . '</div>';
				} else {
					FLEXIUtilities::call_FC_Field_Func($field->field_type, 'onDisplayField', array( &$field, &$item ));
				}
			}

			// c. Create main text field, via calling the display function of the textarea field (will also check for tabs)
			if ($field->field_type == 'maintext')
			{
				if ( isset($item->item_translations) ) {
					$shortcode = substr($item->language ,0,2);
					foreach ($item->item_translations as $lang_id => $t)	{
						if ($shortcode == $t->shortcode) continue;
						$field->name = array('jfdata',$t->shortcode,'text');
						$field->value[0] = html_entity_decode($t->fields->text->value, ENT_QUOTES, 'UTF-8');
						FLEXIUtilities::call_FC_Field_Func('textarea', 'onDisplayField', array(&$field, &$item) );
						$t->fields->text->tab_labels = $field->tab_labels;
						$t->fields->text->html = $field->html;
						unset( $field->tab_labels );
						unset( $field->html );
					}
				}
				$field->name = 'text';
				// NOTE: We use the text created by the model and not the text retrieved by the CORE plugin code, which maybe overwritten with JoomFish/Falang data
				$field->value[0] = $item->text; // do not decode special characters this was handled during saving !
				// Render the field's (form) HTML
				FLEXIUtilities::call_FC_Field_Func('textarea', 'onDisplayField', array(&$field, &$item) );
			}
		}
		if ( $print_logging_info ) $fc_run_times['render_field_html'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

		// Tags used by the item
		$usedtagsids  = $this->get( 'UsedtagsIds' );  // NOTE: This will normally return the already set versioned value of tags ($item->tags)
		//$usedtagsIds 	= $isnew ? array() : $fields['tags']->value;
		$usedtagsdata = $model->getUsedtagsData($usedtagsids);
		//echo "<br/>usedtagsIds: "; print_r($usedtagsids);
		//echo "<br/>usedtags (data): "; print_r($usedtagsdata);

		// Compatibility for old overriden templates ...
		if (!FLEXI_J16GE) {
			$tags			= $this->get('Alltags');
			$usedtags	= $this->get('UsedtagsIds');
		}

		// Load permissions (used by form template)
		$perms = $this->_getItemPerms($item);

		// Get the edit lists
		$lists = $this->_buildEditLists($perms, $params, $authorparams);

		// Get number of subscribers
		$subscribers = $this->get( 'SubscribersCount' );

		// Get menu overridden categories/main category fields
		$menuCats = $this->_getMenuCats($item, $perms, $params);

		// Create submit configuration (for new items) into the session
		$submitConf = $this->_createSubmitConf($item, $perms, $params);
		
		// Create placement configuration for CORE properties
		$placementConf = $this->_createPlacementConf($fields, $params, $item);
		
		// Item language related vars
		if (FLEXI_FISH || FLEXI_J16GE) {
			$languages = FLEXIUtilities::getLanguages();
			$itemlang = new stdClass();
			$itemlang->shortcode = substr($item->language ,0,2);
			$itemlang->name = $languages->{$item->language}->name;
			$itemlang->image = '<img src="'.@$languages->{$item->language}->imgsrc.'" alt="'.$languages->{$item->language}->name.'" />';
		}

		//Load the JEditor object
		$editor = JFactory::getEditor();
		
		// Set page title
		$title = !$isnew ? JText::_( 'FLEXI_EDIT' ) : JText::_( 'FLEXI_NEW' );
		$document->setTitle($title);

		// Add title to pathway
		$pathway = $app->getPathWay();
		$pathway->addItem($title, '');

		// Get pageclass suffix
		$pageclass_sfx = htmlspecialchars($params->get('pageclass_sfx'));

		// Ensure the row data is safe html
		// @TODO: check if this is really required as it conflicts with the escape function in the tmpl
		//JFilterOutput::objectHTMLSafe( $item );

		$this->assign('action',				$uri->toString());
		$this->assignRef('item',			$item);
		if (FLEXI_J16GE) {  // most core field are created via calling methods of the form (J2.5)
			$this->assignRef('form',		$form);
		}
		if ($enable_translation_groups)  $this->assignRef('lang_assocs', $langAssocs);
		if (FLEXI_FISH || FLEXI_J16GE)   $this->assignRef('langs', $langs);
		$this->assignRef('params',		$params);
		$this->assignRef('lists',			$lists);
		$this->assignRef('subscribers', $subscribers);
		$this->assignRef('editor',		$editor);
		$this->assignRef('user',			$user);
		if (!FLEXI_J16GE) {  // compatibility old templates
			$this->assignRef('tags',		$tags);
			$this->assignRef('usedtags',	$usedtags);
		}
		$this->assignRef('usedtagsdata', $usedtagsdata);
		$this->assignRef('fields',     $fields);
		$this->assignRef('tparams',    $tparams);
		$this->assignRef('perms',      $perms);
		$this->assignRef('document',   $document);
		$this->assignRef('nullDate',   $nullDate);
		$this->assignRef('menuCats',   $menuCats);
		$this->assignRef('submitConf', $submitConf);
		$this->assignRef('placementConf', $placementConf);
		$this->assignRef('itemlang',   $itemlang);
		$this->assignRef('pageclass_sfx', $pageclass_sfx);
		$this->assign('captcha_errmsg', @ $captcha_errmsg);
		$this->assign('captcha_field',  @ $captcha_field);
		
		
		// **************************************************************************************
		// Load a different template file for parameters depending on whether we use FLEXI_ACCESS
		// **************************************************************************************

		if (!FLEXI_J16GE) {
			if (FLEXI_ACCESS) {
				$formparams = new JParameter('', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'item2.xml');
			} else {
				$formparams = new JParameter('', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'item.xml');
			}
		}


		// ****************************************************************
		// SET INTO THE FORM, parameter values for various parameter groups
		// ****************************************************************

		if (!FLEXI_J16GE) {
			// Permissions (Access) Group
			if (!FLEXI_ACCESS) {
				$formparams->set('access', $item->access);
			}

			// Set: (Publication) Details Group
			$created_by = (intval($item->created_by) ? intval($item->created_by) : $user->get('id'));
			$formparams->set('created_by', $created_by);
			$formparams->set('created_by_alias', $item->created_by_alias);
			$formparams->set('created', JHTML::_('date', $item->created, '%Y-%m-%d %H:%M:%S'));
			$formparams->set('publish_up', JHTML::_('date', $item->publish_up, '%Y-%m-%d %H:%M:%S'));
			if (JHTML::_('date', $item->publish_down, '%Y') <= 1969 || $item->publish_down == $nullDate || empty($item->publish_down)) {
				$formparams->set('publish_down', JText::_( 'FLEXI_NEVER' ));
			} else {
				$formparams->set('publish_down', JHTML::_('date', $item->publish_down, '%Y-%m-%d %H:%M:%S'));
			}

			// Set:  Attributes (parameters) Group, (these are retrieved from the item table column 'attribs')
			// (also contains templates parameters, but we will use these individual for every template ... see below)
			$formparams->loadINI($item->attribs);

			//echo "<pre>"; print_r($formparams->_xml['themes']->_children[0]);  echo "<pre>"; print_r($formparams->_xml['themes']->param[0]); exit;
			foreach($formparams->_xml['themes']->_children as $i => $child) {
				if ( isset($child->_attributes['enableparam']) && !$params->get($child->_attributes['enableparam']) ) {
					unset($formparams->_xml['themes']->_children[$i]);
					unset($formparams->_xml['themes']->param[$i]);
				}
			}

			// Set: Metadata (parameters) Group
			// NOTE: (2 params from 2 item table columns, and then multiple params from item table column 'metadata')
			$formparams->set('description', $item->metadesc);
			$formparams->set('keywords', $item->metakey);
			if ( !empty($item->metadata) )
				$formparams->loadINI($item->metadata->toString());

			// Now create the sliders object,
			// And also push the Form Parameters object into the template (Template Parameters object is seperate)
			jimport('joomla.html.pane');
			$pane = JPane::getInstance('Sliders');
			//$tabs_pane = JPane::getInstance('Tabs');
			$this->assignRef('pane'				, $pane);
			//$this->assignRef('tabs_pane'	, $tabs_pane);
			$this->assignRef('formparams'	, $formparams);
		} else {
			if ( JHTML::_('date', $item->publish_down , 'Y') <= 1969 || $item->publish_down == $nullDate ) {
				$item->publish_down= JText::_( 'FLEXI_NEVER' );
			}
		}
		
		
		// ****************************
		// Handle Template related work
		// ****************************

		// (a) Get the templates structures used to create form fields for template parameters
		$themes			= flexicontent_tmpl::getTemplates();
		$tmpls_all	= $themes->items;

		// (b) Get Content Type allowed templates
		$allowed_tmpls = $tparams->get('allowed_ilayouts');
		$type_default_layout = $tparams->get('ilayout', 'default');
		if ( empty($allowed_tmpls) )							$allowed_tmpls = array();
		else if ( ! is_array($allowed_tmpls) )		$allowed_tmpls = !FLEXI_J16GE ? array($allowed_tmpls) : explode("|", $allowed_tmpls);

		// (c) Add default layout, unless all templates allowed (=array is empty)
		if ( count ($allowed_tmpls) && !in_array( $type_default_layout, $allowed_tmpls ) ) $allowed_tmpls[] = $type_default_layout;

		// (d) Create array of template data according to the allowed templates for current content type
		if ( count($allowed_tmpls) ) {
			foreach ($tmpls_all as $tmpl) {
				if (in_array($tmpl->name, $allowed_tmpls) ) {
					$tmpls[]= $tmpl;
				}
			}
		} else {
			$tmpls= $tmpls_all;
		}

		// (e) Apply Template Parameters values into the form fields structures
		foreach ($tmpls as $tmpl) {
			if (FLEXI_J16GE) {
				$jform = new JForm('com_flexicontent.template.item', array('control' => 'jform', 'load_data' => true));
				$jform->load($tmpl->params);
				$tmpl->params = $jform;
				foreach ($tmpl->params->getGroup('attribs') as $field) {
					$fieldname =  $field->__get('fieldname');
					$value = $item->itemparams->get($fieldname);
					if (strlen($value)) $tmpl->params->setValue($fieldname, 'attribs', $value);
				}
			} else {
				$tmpl->params->loadINI($item->attribs);
			}
		}

		$this->assignRef('tmpls',		$tmpls);
		
		if ( $print_logging_info )  $start_microtime = microtime(true);
		
		parent::display($tpl);
		
		if ( $print_logging_info ) $fc_run_times['form_rendering'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	}

	/**
	 * Creates the HTML of various form fields used in the item edit form
	 *
	 * @since 1.0
	 */
	function _buildEditLists(&$perms, &$params, &$authorparams)
	{
		$db       = JFactory::getDBO();
		$user     = JFactory::getUser();	// get current user
		$item     = $this->get('Item');		// get the item from the model
		$document = JFactory::getDocument();
		$session  = JFactory::getSession();

		global $globalcats;
		$categories = $globalcats;			// get the categories tree
		$types = $this->get( 'Typeslist' );
		$subscribers = $this->get( 'SubscribersCount' );
		$typesselected = new stdClass();
		$typesselected->id = 0;
		$isnew = !$item->id;

		// *******************************
		// Get categories used by the item
		// *******************************
		
		if ($isnew) {
			// Case for preselected main category for new items
			$maincat = JRequest::getInt('maincat', 0);
			if ($maincat) {
				$selectedcats = array($maincat);
				$item->catid = $maincat;
			} else {
				$selectedcats = array();
			}
		} else {
			// NOTE: This will normally return the already set versioned value of categories ($item->categories)
			$selectedcats = $this->get( 'Catsselected' );
		}
		
		
		
		// *********************************************************************************************
		// Build select lists for the form field. Only few of them are used in J1.6+, since we will use:
		// (a) form XML file to declare them and then (b) getInput() method form field to create them
		// *********************************************************************************************
		
		// First clean form data, we do this after creating the description field which may contain HTML
		JFilterOutput::objectHTMLSafe( $item, ENT_QUOTES );
		
		flexicontent_html::loadFramework('select2');
		$prettycheckable_added = flexicontent_html::loadFramework('prettyCheckable');
		$lists = array();
		
		// build granular access list
		if (!FLEXI_J16GE) {
			if (FLEXI_ACCESS) {
				if (isset($user->level)) {
					$lists['access'] = FAccess::TabGmaccess( $item, 'item', 1, 0, 0, 1, 0, 1, 0, 1, 1 );
				} else {
					$lists['access'] = JText::_('Your profile has been changed, please logout to access to the permissions');
				}
			} else {
				$lists['access'] = JHTML::_('list.accesslevel', $item); // created but not used in J1.5 backend form
			}
		}
		
		
		// build state list
		$_arc_ = FLEXI_J16GE ? 2:-1;
		$non_publishers_stategrp    = $perms['isSuperAdmin'] || $item->state==-3 || $item->state==-4 ;
		$special_privelege_stategrp = ($item->state==$_arc_ || $perms['canarchive']) || ($item->state==-2 || $perms['candelete']) ;
		
		$state = array();
		// Using <select> groups
		if ($non_publishers_stategrp || $special_privelege_stategrp)
			$state[] = JHTML::_('select.optgroup', JText::_( 'FLEXI_PUBLISHERS_WORKFLOW_STATES' ) );
			
		$state[] = JHTML::_('select.option',  1,  JText::_( 'FLEXI_PUBLISHED' ) );
		$state[] = JHTML::_('select.option',  0,  JText::_( 'FLEXI_UNPUBLISHED' ) );
		$state[] = JHTML::_('select.option',  -5, JText::_( 'FLEXI_IN_PROGRESS' ) );
		
		// States reserved for workflow
		if ( $non_publishers_stategrp ) {
			$state[] = JHTML::_('select.optgroup', '' );
			$state[] = JHTML::_('select.optgroup', JText::_( 'FLEXI_NON_PUBLISHERS_WORKFLOW_STATES' ) );
		}
		if ($item->state==-3 || $perms['isSuperAdmin'])  $state[] = JHTML::_('select.option',  -3, JText::_( 'FLEXI_PENDING' ) );
		if ($item->state==-4 || $perms['isSuperAdmin'])  $state[] = JHTML::_('select.option',  -4, JText::_( 'FLEXI_TO_WRITE' ) );
		
		// Special access states
		if ( $special_privelege_stategrp ) {
			$state[] = JHTML::_('select.optgroup', '' );
			$state[] = JHTML::_('select.optgroup', JText::_( 'FLEXI_SPECIAL_ACTION_STATES' ) );
		}
		if ($item->state==$_arc_ || $perms['canarchive']) $state[] = JHTML::_('select.option',  $_arc_, JText::_( 'FLEXI_ARCHIVED' ) );
		if ($item->state==-2     || $perms['candelete'])  $state[] = JHTML::_('select.option',  -2,     JText::_( 'FLEXI_TRASHED' ) );
		
		// Close last <select> group
		if ($non_publishers_stategrp || $special_privelege_stategrp)
			$state[] = JHTML::_('select.optgroup', '');
		
		$fieldname = FLEXI_J16GE ? 'jform[state]' : 'state';
		$elementid = FLEXI_J16GE ? 'jform_state'  : 'state';
		$class = 'use_select2_lib';
		$attribs = 'class="'.$class.'"';
		$lists['state'] = JHTML::_('select.genericlist', $state, $fieldname, $attribs, 'value', 'text', $item->state, $elementid );
		if (!FLEXI_J16GE) $lists['state'] = str_replace('<optgroup label="">', '</optgroup>', $lists['state']);
		
		// *** BOF: J2.5 SPECIFIC SELECT LISTS
		if (FLEXI_J16GE)
		{
		}
		// *** EOF: J1.5 SPECIFIC SELECT LISTS
		
		// build version approval list
		$fieldname = FLEXI_J16GE ? 'jform[vstate]' : 'vstate';
		$elementid = FLEXI_J16GE ? 'jform_vstate' : 'vstate';
		/*
		$options = array();
		$options[] = JHTML::_('select.option',  1, JText::_( 'FLEXI_NO' ) );
		$options[] = JHTML::_('select.option',  2, JText::_( 'FLEXI_YES' ) );
		$attribs = FLEXI_J16GE ? ' style ="float:left!important;" '  :  '';   // this is not right for J1.5' style ="float:left!important;" ';
		$lists['vstate'] = JHTML::_('select.radiolist', $options, $fieldname, $attribs, 'value', 'text', 2, $elementid);
		*/
		$classes = !$prettycheckable_added ? '' : ' use_prettycheckable ';
		$attribs = ' class="'.$classes.'" ';
		$i = 1;
		$options = array(1=>JText::_( 'FLEXI_NO' ), 2=>JText::_( 'FLEXI_YES' ) );
		$lists['vstate'] = '';
		foreach ($options as $option_id => $option_label) {
			$checked = $option_id==2 ? ' checked="checked"' : '';
			$elementid_no = $elementid.'_'.$i;
			if (!$prettycheckable_added) $lists['vstate'] .= '<label class="fccheckradio_lbl" for="'.$elementid_no.'">';
			$extra_params = !$prettycheckable_added ? '' : ' data-label="'.JText::_($option_label).'" data-labelPosition="right" data-customClass="fcradiocheck"';
			$lists['vstate'] .= ' <input type="radio" id="'.$elementid_no.'" element_group_id="'.$elementid
				.'" name="'.$fieldname.'" '.$attribs.' value="'.$option_id.'" '.$checked.$extra_params.' />';
			if (!$prettycheckable_added) $lists['vstate'] .= '&nbsp;'.JText::_($option_label).'</label>';
			$i++;
		}
		
		
		// build field for notifying subscribers
		if ( !$subscribers )
		{
			$lists['notify'] = !$isnew ? JText::_('FLEXI_NO_SUBSCRIBERS_EXIST') : '';
		} else {
			// b. Check if notification emails to subscribers , were already sent during current session
			$subscribers_notified = $session->get('subscribers_notified', array(),'flexicontent');
			if ( !empty($subscribers_notified[$item->id]) ) {
				$lists['notify'] = JText::_('FLEXI_SUBSCRIBERS_ALREADY_NOTIFIED');
			} else {
				// build favs notify field
				$fieldname = FLEXI_J16GE ? 'jform[notify]' : 'notify';
				$elementid = FLEXI_J16GE ? 'jform_notify' : 'notify';
				/*
				$attribs = FLEXI_J16GE ? ' style ="float:none!important;" '  :  '';   // this is not right for J1.5' style ="float:left!important;" ';
				$lists['notify'] = '<input type="checkbox" name="jform[notify]" id="jform_notify" '.$attribs.' /> '. $lbltxt;
				*/
				$classes = !$prettycheckable_added ? '' : ' use_prettycheckable ';
				$attribs = ' class="'.$classes.'" ';
				$lbltxt = $subscribers .' '. JText::_( $subscribers>1 ? 'FLEXI_SUBSCRIBERS' : 'FLEXI_SUBSCRIBER' );
				if (!$prettycheckable_added) $lists['notify'] .= '<label class="fccheckradio_lbl" for="'.$elementid.'">';
				$extra_params = !$prettycheckable_added ? '' : ' data-label="'.$lbltxt.'" data-labelPosition="right" data-customClass="fcradiocheck"';
				$lists['notify'] = ' <input type="checkbox" id="'.$elementid.'" element_group_id="'.$elementid
					.'" name="'.$fieldname.'" '.$attribs.' value="1" '.$extra_params.' checked="checked" />';
				if (!$prettycheckable_added) $lists['notify'] .= '&nbsp;'.$lbltxt.'</label>';
			}
		}
		

		// Get author's maximum allowed categories per item and set js limitation
		$max_cat_assign = !$authorparams ? 0 : intval($authorparams->get('max_cat_assign',0));
		$document->addScriptDeclaration('
			max_cat_assign_fc = '.$max_cat_assign.';
			existing_cats_fc  = ["'.implode('","',$selectedcats).'"];
			max_cat_overlimit_msg_fc = "'.JText::_('FLEXI_TOO_MANY_ITEM_CATEGORIES',true).'";
		');
		
		
		// Creating categorories tree for item assignment, we use the 'create' privelege
		$actions_allowed = array('core.create');

		// Featured categories form field
		$featured_cats_parent = $params->get('featured_cats_parent', 0);
		$featured_cats = array();
		$enable_featured_cid_selector = $perms['multicat'] && ($isnew || $perms['canchange_featcat']);
		if ( $featured_cats_parent )
		{
			$featured_tree = flexicontent_cats::getCategoriesTree($published_only=1, $parent_id=$featured_cats_parent, $depth_limit=0);
			$featured_sel = array();
			foreach($selectedcats as $featured_cat) if (isset($featured_tree[$featured_cat])) $featured_sel[] = $featured_cat;
			
			$class  = "use_select2_lib select2_list_selected";
			$attribs  = 'class="'.$class.'" multiple="multiple" size="8"';
			$attribs .= $enable_featured_cid_selector ? '' : ' disabled="disabled"';
			
			$fieldname = FLEXI_J16GE ? 'jform[featured_cid][]' : 'featured_cid[]';
			$lists['featured_cid'] = ($enable_featured_cid_selector ? '' : '<label class="label" style="float:none; margin:0 6px 0 0 !important;">locked</label>').
				flexicontent_cats::buildcatselect($featured_tree, $fieldname, $featured_sel, 3, $attribs, true, true,	$actions_allowed);
		}
		else{
			// Do not display, if not configured or not allowed to the user
			$lists['featured_cid'] = false;
		}
		
		
		// Multi-category form field, for user allowed to use multiple categories
		$lists['cid'] = '';
		$enable_cid_selector = $perms['multicat'] && ($isnew || $perms['canchange_seccat']);
		if ( 1 )
		{
			// Get author's maximum allowed categories per item and set js limitation
			$max_cat_assign = !$authorparams ? 0 : intval($authorparams->get('max_cat_assign',0));
			$document->addScriptDeclaration('
				max_cat_assign_fc = '.$max_cat_assign.';
				existing_cats_fc  = ["'.implode('","',$selectedcats).'"];
				max_cat_overlimit_msg_fc = "'.JText::_('FLEXI_TOO_MANY_ITEM_CATEGORIES',true).'";
			');
			
			$class  = "mcat use_select2_lib select2_list_selected";
			$class .= $max_cat_assign ? " validate-fccats" : " validate";
			
			$attribs  = 'class="'.$class.'" multiple="multiple" size="20"';
			$attribs .= $enable_cid_selector ? '' : ' disabled="disabled"';
			
			$fieldname = FLEXI_J16GE ? 'jform[cid][]' : 'cid[]';
			$skip_subtrees = $featured_cats_parent ? array($featured_cats_parent) : array();
			$lists['cid'] = ($enable_cid_selector ? '' : '<label class="label" style="float:none; margin:0 6px 0 0 !important;">locked</label>').
				flexicontent_cats::buildcatselect($categories, $fieldname, $selectedcats, false, $attribs, true, true,
				$actions_allowed, $require_all=true, $skip_subtrees, $disable_subtrees=array());
		}
		else {
			if ( count($selectedcats)>1 ) {
				foreach ($selectedcats as $catid) {
					$cat_titles[$catid] = $globalcats[$catid]->title;
				}
				$lists['cid'] .= implode(', ', $cat_titles);
			} else {
				$lists['cid'] = false;
			}
		}
		
		
		// Main category form field
		$class = 'scat use_select2_lib';
		if ($perms['multicat']) {
			$class .= ' validate-catid';
		} else {
			$class .= ' required';
		}
		$attribs = 'class="'.$class.'"';
		$fieldname = FLEXI_J16GE ? 'jform[catid]' : 'catid';
		
		$enable_catid_selector =  $isnew || $perms['canchange_cat'];
		if ( 1 ) {
			$disabled = $enable_catid_selector ? '' : ' disabled="disabled"';
			$attribs .= $disabled;
			$lists['catid'] = ($enable_catid_selector ? '' : '<label class="label" style="float:none; margin:0 6px 0 0 !important;">locked</label>').
				flexicontent_cats::buildcatselect($categories, $fieldname, $item->catid, 2, $attribs, true, true, $actions_allowed);
		} else {
			$lists['catid'] = $globalcats[$item->catid]->title;
		}
		
		
		//buid types selectlist
		$class   = 'required use_select2_lib';
		$attribs = 'class="'.$class.'"';
		$fieldname = FLEXI_J16GE ? 'jform[type_id]' : 'type_id';
		$elementid = FLEXI_J16GE ? 'jform_type_id'  : 'type_id';
		$lists['type'] = flexicontent_html::buildtypesselect($types, $fieldname, $typesselected->id, 1, $attribs, $elementid, $check_perms=true );
		
		
		// build version approval list
		if ( $params->get('allowdisablingcomments_fe') )
		{
			// Set to zero if disabled or to "" (aka use default) for any other value.  THIS WILL FORCE comment field use default Global/Category/Content Type setting or disable it,
			// thus a per item commenting system cannot be selected. This is OK because it makes sense to have a different commenting system per CONTENT TYPE by not per Content Item
			$isdisabled = !$params->get('comments') && strlen($params->get('comments'));
			$fieldvalue = $isdisabled ? 0 : "";

			$fieldname = FLEXI_J16GE ? 'jform[attribs][comments]' : 'params[comments]';
			$elementid = FLEXI_J16GE ? 'jform_attribs_comments' : 'params_comments';
			/*
			$options = array();
			$options[] = JHTML::_('select.option', "",  JText::_( 'FLEXI_DEFAULT_BEHAVIOR' ) );
			$options[] = JHTML::_('select.option', 0, JText::_( 'FLEXI_DISABLE' ) );
			$attribs = FLEXI_J16GE ? ' style ="float:none!important;" ' : '';
			$lists['disable_comments'] = JHTML::_('select.radiolist', $options, $fieldname, $attribs, 'value', 'text', $fieldvalue, $elementid);
			*/
			$classes = !$prettycheckable_added ? '' : ' use_prettycheckable ';
			$attribs = ' class="'.$classes.'" ';
			$i = 1;
			$options = array(""=>JText::_( 'FLEXI_DEFAULT_BEHAVIOR' ), 0=>JText::_( 'FLEXI_DISABLE' ) );
			$lists['disable_comments'] = '';
			foreach ($options as $option_id => $option_label) {
				$checked = $option_id===$fieldvalue ? ' checked="checked"' : '';
				$elementid_no = $elementid.'_'.$i;
				if (!$prettycheckable_added) $lists['disable_comments'] .= '<label class="fccheckradio_lbl" for="'.$elementid_no.'">';
				$extra_params = !$prettycheckable_added ? '' : ' data-label="'.JText::_($option_label).'" data-labelPosition="right" data-customClass="fcradiocheck"';
				$lists['disable_comments'] .= ' <input type="radio" id="'.$elementid_no.'" element_group_id="'.$elementid
					.'" name="'.$fieldname.'" '.$attribs.' value="'.$option_id.'" '.$checked.$extra_params.' />';
				if (!$prettycheckable_added) $lists['disable_comments'] .= '&nbsp;'.JText::_($option_label).'</label>';
				$i++;
			}
		}
		
		
		// find user's allowed languages
		$site_default_lang = flexicontent_html::getSiteDefaultLang();
		$allowed_langs = !$authorparams ? null : $authorparams->get('langs_allowed',null);
		$allowed_langs = !$allowed_langs ? null : FLEXIUtilities::paramToArray($allowed_langs);
		if (!$isnew && $allowed_langs) $allowed_langs[] = $item->language;
		
		// find globaly or per content type disabled languages
		$disable_langs = $params->get('disable_languages_fe', array());
		
		// Build languages list
		if (FLEXI_J16GE || FLEXI_FISH) {
			$item_lang = $isnew ? $site_default_lang : $item->language;
			$langdisplay = $params->get('langdisplay_fe', 3);
			$langconf = array();
			$langconf['flags'] = $params->get('langdisplay_flags_fe', 1);
			$langconf['texts'] = $params->get('langdisplay_texts_fe', 1);
			$field_attribs = $langdisplay==2 ? 'class="use_select2_lib"' : '';
			$lists['languages'] = flexicontent_html::buildlanguageslist( (FLEXI_J16GE ? 'jform[language]' : 'language') , $field_attribs, $item_lang, $langdisplay, $allowed_langs, $published_only=1, $disable_langs, $add_all=true, $langconf);
		} else {
			$item->language = $site_default_lang;
		}

		return $lists;
	}
	
	
	/**
	 * Calculates the user permission on the given item
	 *
	 * @since 1.0
	 */
	function _getItemPerms( &$item )
	{
		$user = JFactory::getUser();	// get current user\
		$isOwner = ( $item->created_by == $user->get('id') );

		$perms 	= array();

		$permission = FlexicontentHelperPerm::getPerm();
		$perms['isSuperAdmin'] = $permission->SuperAdmin;
		$perms['multicat']     = $permission->MultiCat;
		$perms['cantags']      = $permission->CanUseTags;
		$perms['canparams']    = $permission->CanParams;
		$perms['cantemplates'] = $permission->CanTemplates;
		$perms['canarchive']   = $permission->CanArchives;
		$perms['canright']     = $permission->CanRights;
		$perms['canacclvl']    = $permission->CanAccLvl;
		$perms['canversion']   = $permission->CanVersion;
		
		// J2.5+ specific
		if (FLEXI_J16GE) $perms['editcreationdate'] = $permission->EditCreationDate;
		//else if (FLEXI_ACCESS) $perms['editcreationdate'] = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'editcreationdate', 'users', $user->gmid) : 1;
		//else $perms['editcreationdate'] = ($user->gid >= 25);
		
		// Get general edit/publish/delete permissions (we will override these for existing items)
		$perms['canedit']    = $permission->CanEdit    || $permission->CanEditOwn;
		$perms['canpublish'] = $permission->CanPublish || $permission->CanPublishOwn;
		$perms['candelete']  = $permission->CanDelete  || $permission->CanDeleteOwn;
		$perms['canchange_cat'] = $permission->CanChangeCat;
		$perms['canchange_seccat'] = $permission->CanChangeSecCat;
		$perms['canchange_featcat'] = $permission->CanChangeFeatCat;
		
		// OVERRIDE global with existing item's atomic settings
		if ( $item->id )
		{
			if (FLEXI_J16GE) {
				$asset = 'com_content.article.' . $item->id;
				$perms['canedit']			= $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $isOwner);
				$perms['canpublish']	= $user->authorise('core.edit.state', $asset) || ($user->authorise('core.edit.state.own', $asset) && $isOwner);
				$perms['candelete']		= $user->authorise('core.delete', $asset) || ($user->authorise('core.delete.own', $asset) && $isOwner);
			}
			else if (FLEXI_ACCESS) {
				$rights = FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
				$perms['canedit']			= ($user->gid < 25) ? ( (in_array('editown', $rights) && $isOwner) || (in_array('edit', $rights)) ) : 1;
				$perms['canpublish']	= ($user->gid < 25) ? ( (in_array('publishown', $rights) && $isOwner) || (in_array('publish', $rights)) ) : 1;
				$perms['candelete']		= ($user->gid < 25) ? ( (in_array('deleteown', $rights) && $isOwner) || (in_array('delete', $rights)) ) : 1;
				// Only FLEXI_ACCESS has per item rights permission
				$perms['canright']		= ($user->gid < 25) ? ( (in_array('right', $rights)) ) : 1;
			}
			else {
				// J1.5 permissions with no FLEXIaccess are only general, no item specific permissions
			}
		}
		
		return $perms;
	}

	/**
	 * Creates the (menu-overridden) categories/main category form fields for NEW item submission form
	 *
	 * @since 1.0
	 */
	function _getMenuCats(&$item, $perms, $params)  // menu
	{
		global $globalcats;

		$isnew = !$item->id;

		// Get menu parameters related to category overriding
		$cid       = $params->get("cid");              // Overriden categories list
		$maincatid = $params->get("maincatid");        // Default main category out of the overriden categories
		$postcats  = $params->get("postcats", 0);      // Behavior of override, submit to ONE Or MULTIPLE or to FIXED categories
		$override  = $params->get("overridecatperms", 1);   // Default to 1 for compatibilty with previous-version saved menu items

		// Check if item is new and overridden cats defined and cat overriding enabled
		if ( !$isnew || empty($cid) || !$override ) return false;

		// DO NOT override user's permission for submitting to multiple categories
		if ( !$perms['multicat'] && $postcats==2 ) $postcats = 1;

		// OVERRIDE item categories, using the ones specified specified by the MENU item, instead of categories that user has CREATE (=add) Permission
		$cids = !is_array($cid) ? explode(",", $cid) : $cid;

		// Add default main category to the overridden category list if not already there
		if ($maincatid && !in_array($maincatid, $cids)) $cids[] = $maincatid;

		// Create 2 arrays with category info used for creating the of select list of (a) multi-categories select field (b) main category select field
		$categories = array();
		$options 	= array();
		foreach ($cids as $catid) {
			$categories[] = $globalcats[$catid];
		}

		// Field names for (a) multi-categories field and (b) main category field
		$cid_form_fieldname   = FLEXI_J16GE ? 'jform[cid][]' : 'cid[]';
		$catid_form_fieldname = FLEXI_J16GE ? 'jform[catid]' : 'catid';
		$catid_form_tagid   = FLEXI_J16GE ? 'jform_catid' : 'catid';

		// Create form field HTML for the menu-overridden categories fields
		switch($postcats)
		{
			case 0:  // no categories selection, submit to a MENU SPECIFIED categories list
			default:
				// Do not create multi-category field if only one category was selected
				if ( count($cids)>1 ) {
					foreach ($cids as $catid) {
						$cat_titles[$catid] = $globalcats[$catid]->title;
						$mo_cats .= '<input type="hidden" name="'.$cid_form_fieldname.'" value="'.$catid.'" />';
					}
					$mo_cats .= implode(', ', $cat_titles);
				} else {
					$mo_cats = false;
				}

				$mo_maincat = $globalcats[$maincatid]->title;
				$mo_maincat .= '<input type="hidden" name="'.$catid_form_fieldname.'" value="'.$maincatid.'" />';
				$mo_cancid  = false;
				break;
			case 1:  // submit to a single category, selecting from a MENU SPECIFIED categories subset
				$mo_cats    = false;
				$mo_maincat = flexicontent_cats::buildcatselect($categories, $catid_form_fieldname, $maincatid, 2, ' class="scat use_select2_lib required" ', $check_published=true, $check_perms=false);
				$mo_cancid  = false;
				break;
			case 2:  // submit to multiple categories, selecting from a MENU SPECIFIED categories subset
				$attribs = 'class="validate use_select2_lib select2_list_selected" multiple="multiple" size="8"';
				$mo_cats    = flexicontent_cats::buildcatselect($categories, $cid_form_fieldname, array(), false, $attribs, $check_published=true, $check_perms=false);
				$mo_maincat = flexicontent_cats::buildcatselect($categories, $catid_form_fieldname, $maincatid, 2, ' class="scat use_select2_lib validate-catid" ', $check_published=true, $check_perms=false);
				$mo_cancid  = true;
				break;
		}
		$menuCats = new stdClass();
		$menuCats->cid    = $mo_cats;
		$menuCats->catid  = $mo_maincat;
		$menuCats->cancid = $mo_cancid;

		return $menuCats;
	}


	function _createSubmitConf( &$item, $perms, $params)
	{
		if ( $item->id ) return '';

		// Overriden categories list
		$cid = $params->get("cid");
		$cids = !is_array($cid) ? explode(",", $cid) : $cid;

		// Behavior of override, submit to ONE Or MULTIPLE or to FIXED categories
		$postcats = $params->get("postcats");
		if ( !$perms['multicat'] && $postcats==2 ) $postcats = 1;

		// Default to 1 for compatibilty with previous-version saved menu items
		$overridecatperms  = $params->get("overridecatperms", 1);
		if ( empty($cid) ) $overridecatperms = 0;

		// Get menu parameters override parameters
		$submit_conf = array(
			'cids'            => $cids,
			'maincatid'       => $params->get("maincatid"),        // Default main category out of the overriden categories
			'postcats'        => $postcats,
			'overridecatperms'=> $overridecatperms,
			'autopublished'   => $params->get('autopublished', 0),  // Publish the item
			'autopublished_up_interval'   => $params->get('autopublished_up_interval', 0),
			'autopublished_down_interval' => $params->get('autopublished_down_interval', 0)
		);
		$submit_conf_hash = md5(serialize($submit_conf));

		$session = JFactory::getSession();
		$item_submit_conf = $session->get('item_submit_conf', array(),'flexicontent');
		$item_submit_conf[$submit_conf_hash] = $submit_conf;
		$session->set('item_submit_conf', $item_submit_conf, 'flexicontent');

		if (FLEXI_J16GE)
			return '<input type="hidden" name="jform[submit_conf]" value="'.$submit_conf_hash.'" >';
		else
			return '<input type="hidden" name="submit_conf" value="'.$submit_conf_hash.'" >';
	}


	function _createPlacementConf(&$fields, &$params, &$item) {
		// 1. Find core placer fields (of type 'coreprops')
		$core_placers = array();
		foreach($fields as $field) {
			if ($field->field_type=='coreprops')
			{
				$core_placers[$field->parameters->get('props_type')] = $field;
			}
		}
		
		
		// 2. Field name arrays:  (a) placeable and  (b) placeable via placer  (c) above tabs fields
		$via_core_field  = array(
			'title'=>1, 'type_id'=>1, 'state'=>1, 'cats'=>1, 'tags'=>1, 'maintext'=>1
		);
		$via_core_field = array_merge($via_core_field, FLEXI_J16GE ?
			array('created'=>1, 'created_by'=>1, 'modified'=>1, 'modified_by'=>1) :
			array()
		);
		
		$via_core_prop = array(
			'alias'=>1, 'disable_comments'=>1, 'notify_subscribers'=>1, 'language'=>1, 'perms'=>1,
			'metadata'=>1, 'seoconf'=>1, 'display_params'=>1, 'layout_selection'=>1, 'layout_params'=>1
		);
		$via_core_prop = array_merge($via_core_prop, FLEXI_J16GE ?
			array('timezone_info'=>1, 'created_by_alias'=>1, 'publish_up'=>1, 'publish_down'=>1, 'access'=>1) :
			array('timezone_info'=>1, 'publication_details'=>1)
		);
		
		$placeable_fields = array_merge($via_core_field, $via_core_prop);
		
		
		// 3. Decide placement of CORE properties / fields
		$tab_fields['above'] = $params->get('form_tabs_above',    'title, alias, type, state, disable_comments, notify_subscribers');
		
		$tab_fields['tab01'] = $params->get('form_tab01_fields',  'categories, tags, language, perms');
		$tab_fields['tab02'] = $params->get('form_tab02_fields',  'maintext');
		$tab_fields['tab03'] = $params->get('form_tab03_fields',  'fields_manager');
		$tab_fields['tab04'] = $params->get('form_tab04_fields',  (!FLEXI_J16GE ? 'timezone_info, publication_details' : 'timezone_info, created, createdby, created_by_alias, publish_up, publish_down, access'));
		$tab_fields['tab05'] = $params->get('form_tab05_fields',  'metadata, seoconf');
		$tab_fields['tab06'] = $params->get('form_tab06_fields',  'display_params');
		$tab_fields['tab07'] = $params->get('form_tab07_fields',  'layout_selection, layout_params');
		
		$tab_fields['fman']  = $params->get('form_tabs_fieldsman','');
		$tab_fields['below'] = $params->get('form_tabs_below',    '');
		
		// fix aliases
		foreach($tab_fields as $tab_name => $field_list) {
			$field_list = str_replace('created_by', 'createdby', $field_list);
			$field_list = str_replace('createdby_alias', 'created_by_alias', $field_list);
			$tab_fields[$tab_name] = $field_list;
		}
		//echo "<pre>"; print_r($tab_fields); echo "</pre>";
		
		// Split field lists
		$all_tab_fields = array();
		foreach($tab_fields as $i => $field_list)
		{
			$tab_fields[$i] = (empty($tab_fields[$i]) || $tab_fields[$i]=='_skip_')  ?  array()  :  array_flip( preg_split("/[\s]*,[\s]*/", $field_list ) );
			foreach ($tab_fields[$i] as $tbl_name => $ignore) {
				$all_tab_fields[$tbl_name] = 1;
			}
			//$all_tab_fields = array_merge($all_tab_fields, $tab_fields[$i]);
		}
		// Find fields missing from configuration, and place them below the tabs
		foreach($placeable_fields as $fn => $i)
		{
			if ( !isset($all_tab_fields[$fn]) )
			{
				$tab_fields['below'][$fn] = 1;
			}
		}
		
		// get TAB titles
		$_tmp = $params->get('form_tab_titles', '1:FLEXI_BASIC, 2:FLEXI_DESCRIPTION, 3:__TYPE_NAME__, 4:FLEXI_PUBLISHING, 5:FLEXI_META_SEO, 6:FLEXI_DISPLAYING, 7:FLEXI_TEMPLATE');
		
		// Create title of the custom fields default TAB (field manager TAB)
		if ($item->type_id) {
			$types_arr = flexicontent_html::getTypesList();
			$typename = @ $types_arr[$item->type_id]['name'];
			$typename = $typename ? JText::_($typename) : JText::_('FLEXI_CONTENT_TYPE');
			$typename = $typename .' ('. JText::_('FLEXI_DETAILS') .')';
		} else {
			$typename = JText::_('FLEXI_TYPE_NOT_DEFINED');
		}
		
		
		// Split title of default tabs and language filter the titles
		$_tmp = preg_split("/[\s]*,[\s]*/", $_tmp);
		$tab_titles = array();
		foreach($_tmp as $_data) {
			list($tab_no, $tab_title) = preg_split("/[\s]*:[\s]*/", $_data);
			if ($tab_title == '__TYPE_NAME__')
				$tab_titles['tab0'.$tab_no] = $typename;
			else
				$tab_titles['tab0'.$tab_no] = JText::_($tab_title);
		}
		
		
		// 4. find if some fields are missing placement field
		$coreprop_missing = array();
		foreach($via_core_prop as $fn => $i)
		{
			// -EITHER- configured to be shown at default position -OR- 
			if ( isset($tab_fields['fman'][$fn])  &&  !isset($core_placers[$fn]) ) {
				$coreprop_missing[$fn] = true;
				unset($tab_fields['fman'][$fn]);
				$tab_fields['below'][$fn] = 1;
			}
		}
		
		$placementConf['via_core_field']   = $via_core_field;
		$placementConf['via_core_prop']    = $via_core_prop;
		$placementConf['placeable_fields'] = $placeable_fields;
		$placementConf['tab_fields']       = $tab_fields;
		$placementConf['tab_titles']       = $tab_titles;
		$placementConf['all_tab_fields']   = $all_tab_fields;
		$placementConf['coreprop_missing'] = $coreprop_missing;
		
		return $placementConf;
	}
}