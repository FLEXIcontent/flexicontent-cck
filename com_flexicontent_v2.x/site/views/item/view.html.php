<?php
/**
 * @version 1.5 stable $Id: view.html.php 1319 2012-05-26 19:27:51Z ggppdk $
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

defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.application.component.view');

/**
 * HTML View class for the Items View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewItem extends JViewLegacy
{
	var $_type = '';
	var $_name = FLEXI_ITEMVIEW;
	
	/**
	 * Creates the item page
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		jimport( 'joomla.html.parameter' );
		
		// check for form layout
		if($this->getLayout() == 'form' || in_array(JRequest::getVar('task'), array('add','edit')) ) {
			$this->_displayForm($tpl);
			return;
		} else {
			$this->setLayout('item');
		}
		
		// Get Content Types with no category links in item view pathways
		global $globalnopath;
		if (!is_array($globalnopath))     $globalnopath	= array();
		
		// Initialize variables
		$mainframe  = &JFactory::getApplication();
		$session    = & JFactory::getSession();
		$dispatcher = & JDispatcher::getInstance();
		$document   = & JFactory::getDocument();
		$user       = & JFactory::getUser();
		$db         = & JFactory::getDBO();
		$nullDate   = $db->getNullDate();
		
		$menu			= JSite::getMenu()->getActive();
		$aid			= !FLEXI_J16GE ? (int) $user->get('aid') : $user->getAuthorisedViewLevels();
		$model		= & $this->getModel();
		$limitstart	= JRequest::getVar('limitstart', 0, '', 'int');
		$cid			= $model->_cid ? $model->_cid : $model->get('catid');  // Get current category id, verifying it really belongs to current item
		
		$Itemid		= JRequest::getInt('Itemid', 0);
		
		// Get the PAGE/COMPONENT parameters (WARNING: merges current menu item parameters in J1.5 but not in J1.6+)
		$cparams = clone($mainframe->getParams('com_flexicontent'));
		
		if ($menu) {
			$menuParams = new JParameter($menu->params);
			// In J1.6+ the above function does not merge current menu item parameters,
			// it behaves like JComponentHelper::getParams('com_flexicontent') was called
			if (FLEXI_J16GE) $cparams->merge($menuParams);
		}
		
		//add css file
		if (!$cparams->get('disablecss', '')) {
			$document->addStyleSheet($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css');
			$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext {zoom:1;}</style><![endif]-->');
		}
		
		//allow css override
		if (file_exists(JPATH_SITE.DS.'templates'.DS.$mainframe->getTemplate().DS.'css'.DS.'flexicontent.css')) {
			$document->addStyleSheet($this->baseurl.'/templates/'.$mainframe->getTemplate().'/css/flexicontent.css');
		}
		//special to hide the joomfish language selector on item views
		$css = '#jflanguageselection { visibility:hidden; }'; 
		if ($cparams->get('disable_lang_select', 1)) {
			$document->addStyleDeclaration($css);
		}

		// we are in display() task, so we will load the current item version by default
		// 'preview' request variable will force last, and finally 'version' request variable will force specific
		// NOTE: preview and version variables cannot be used by users that cannot edit the item
		JRequest::setVar('loadcurrent', true);
		
		// Try to load existing item, an 404 error will be raised if item is not found. Also value 2 for check_view_access
		// indicates to raise 404 error for ZERO primary key too, instead of creating and returning a new item object
		$item = & $model->getItem(null, $check_view_access=2);
		
		// Set item parameters as VIEW's parameters (item parameters are merged with component/page/type/current category/access parameters already)
		$params = & $item->parameters;
		
		// ********************
		// ITEM LAYOUT handling
		// ********************
		
		// (a) Decide to use mobile or normal item template layout
		$use_mobile = $params->get('detect_mobile') && $session->get('fc_use_mobile', false, 'flexicontent');
		$_ilayout = $use_mobile ? 'ilayout_mobile' : 'ilayout';
		
		// (b) Get from item parameters, allowing URL override
		$ilayout = JRequest::getVar($_ilayout, false);
		$ilayout = $ilayout ? $ilayout : $params->get($_ilayout, 'default');
		
		// (c) Create the type parameters
		$tparams = & $this->get( 'Typeparams' );
		$tparams = new JParameter($tparams);
		
		// (d) Verify the layout is within templates, Content Type default template OR Content Type allowed templates
		$allowed_tmpls = $tparams->get('allowed_ilayouts');
		$type_default_layout = $tparams->get('ilayout', 'default');
		if ( empty($allowed_tmpls) )							$allowed_tmpls = array();
		else if ( ! is_array($allowed_tmpls) )		$allowed_tmpls = !FLEXI_J16GE ? array($allowed_tmpls) : explode("|", $allowed_tmpls);
		
		// (e) Verify the item layout is within templates: Content Type default template OR Content Type allowed templates
		if ( $ilayout!=$type_default_layout && count($allowed_tmpls) && !in_array($ilayout,$allowed_tmpls) ) {
			$mainframe->enqueueMessage("<small>Current Item Layout Template is '$ilayout':<br>- This is neither the Content Type Default Template, nor does it belong to the Content Type allowed templates.<br>- Please correct this in the URL or in Content Type configuration.<br>- Using Content Type Default Template Layout: '$type_default_layout'</small>", 'notice');
			$ilayout = $type_default_layout;
		}
		
		// (f) Get cached template data
		$themes = flexicontent_tmpl::getTemplates( $lang_files = array($ilayout) );
		
		// (g) Verify the item layout exists
		if ( !isset($themes->items->{$ilayout}) ) {
			$fixed_ilayout = isset($themes->items->{$type_default_layout}) ? $type_default_layout : 'default';
			$mainframe->enqueueMessage("<small>Current Item Layout Template is '$ilayout' does not exist<br>- Please correct this in the URL or in Content Type configuration.<br>- Using Template Layout: '$fixed_ilayout'</small>", 'notice');
			$ilayout = $fixed_ilayout;
			if (FLEXI_FISH || FLEXI_J16GE) FLEXIUtilities::loadTemplateLanguageFile( $ilayout ); // Manually load Template-Specific language file of back fall ilayout
		}
		
		// (h) finally set the template name back into the item's parameters
		$params->set('ilayout', $ilayout);		
		
		// Bind Fields
		$item 	= FlexicontentFields::getFields($item, FLEXI_ITEMVIEW, $params, $aid);
		$item	= $item[0];
		
		// Note : This parameter doesn't exist yet but it will be used by the future gallery template
		if ($params->get('use_panes', 1)) {
			jimport('joomla.html.pane');
			$pane = & JPane::getInstance('Tabs');
			$this->assignRef('pane', $pane);
		}
		
		$fields		=& $item->fields;

		// Pathway need to be improved
		$cats		= new flexicontent_cats($cid);
		$parents	= $cats->getParentlist();
		$depth		= $params->get('item_depth', 0);
		
		
		// **********************
		// Calculate a page title
		// **********************
		$m_id  = (int) @$menu->query['id'] ;
		$m_cid = (int) @$menu->query['cid'] ;
		
		// Verify menu item points to current FLEXIcontent object, IF NOT then overwrite page title and clear page class sufix
		if ( $menu && ($menu->query['view'] != FLEXI_ITEMVIEW || $m_cid != JRequest::getInt('cid') || JRequest::getInt('id') ) ) {
			$params->set('page_title',	$item->title);
			$params->set('pageclass_sfx',	'');
		}
		
		if ($params->get('override_title', 0)) {
			if ($params->get('custom_ititle', '')) {
				$params->set('page_title',	$params->get('custom_ititle'));				
			} else {
				$params->set('page_title',	$item->title);
			}
		}
		
		
		// *******************
		// Create page heading
		// *******************
		
		if ( !FLEXI_J16GE )
			$params->def('show_page_heading', $params->get('show_page_title'));  // J1.5: parameter name was show_page_title instead of show_page_heading
		else
			$params->def('show_page_title', $params->get('show_page_heading'));  // J2.5: to offer compatibility with old custom templates or template overrides
			
		// if above did not set the parameter, then default to NOT showing page heading (title)
		$params->def('show_page_heading', 0);
		$params->def('show_page_title', 0);
		
		// ... the page heading text
		$params->def('page_heading', $params->get('page_title'));    // J1.5: parameter name was show_page_title instead of show_page_heading
		$params->def('page_title', $params->get('page_heading'));    // J2.5: to offer compatibility with old custom templates or template overrides
		
		
		// ************************************************************
		// Create the document title, by from page title and other data
		// ************************************************************
		
		// Check and prepend category title
		if($cid && $params->get('addcat_title', 1) && (count($parents)>0)) {
			$parentcat = end($parents);
			$doc_title = (isset($parentcat->title) ? $parentcat->title.' - ':"") .$params->get( 'page_title' );
		} else {
			$doc_title = $params->get( 'page_title' );
		}
		
		// Check and prepend or append site name
		if (FLEXI_J16GE) {  // Not available in J1.5
			// Add Site Name to page title
			if ($mainframe->getCfg('sitename_pagetitles', 0) == 1) {
				$doc_title = $mainframe->getCfg('sitename') ." - ". $doc_title ;
			}
			elseif ($mainframe->getCfg('sitename_pagetitles', 0) == 2) {
				$doc_title = $doc_title ." - ". $mainframe->getCfg('sitename') ;
			}
		}
		
		// Finally, set document title
		$document->setTitle($doc_title);
		
		
		
		// @TODO check that as it seems to be dirty :(
		$uri  			=& JFactory::getURI();
		$base 			= $uri->getScheme() . '://' . $uri->getHost();
		$ucanonical 	= $base . JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug));
		if ($params->get('add_canonical')) {
			$document->addHeadLink( $ucanonical, 'canonical', 'rel', '' );
		}
		
		$limitstart	= JRequest::getVar('limitstart', 0, '', 'int');
		
		// increment the hit counter
		if ( $limitstart == 0 && FLEXIUtilities::count_new_hit($item) ) {
			$model->hit();
		}
		
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
		$pathway 	=& $mainframe->getPathWay();
		
		// Clear pathway, if automatic pathways are enabled
		if ( $params->get('automatic_pathways', 0) ) {
			$pathway_arr = $pathway->getPathway();
			$pathway->setPathway( array() );
			$pathway->set('_count', 0);
			$item_depth = 0;  // menu item depth is now irrelevant ???, ignore it
		} else {
			$item_depth = $params->get('item_depth', 0);
		}
		
		// Respect menu item depth, defined in menu item
		$p = $item_depth;
		while ( $p < count($parents) ) {
			// For some Content Types the pathway should not be populated with category links
			if ( in_array($item->type_id, $globalnopath) )  break;
			
			// Add current parent category
			$pathway->addItem( $this->escape($parents[$p]->title), JRoute::_( FlexicontentHelperRoute::getCategoryRoute($parents[$p]->categoryslug) ) );
			$p++;
		}
		if ($params->get('add_item_pathway', 1)) {
			$pathway->addItem( $this->escape($item->title), JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug)) );
		}
		
		
		// ************************
		// Set document's META tags
		// ************************
		
		// Set item's META data: desc, keyword, title, author
		if ($item->metadesc)		$document->setDescription( $item->metadesc );
		if ($item->metakey)			$document->setMetadata('keywords', $item->metakey);
		if ($mainframe->getCfg('MetaTitle') == '1')		$document->setMetaData('title', $item->title);
		if ($mainframe->getCfg('MetaAuthor') == '1')	$document->setMetaData('author', $item->author);
		
		// Set remaining META keys
		//$mdata = new JParameter($item->metadata);
		//$mdata = $mdata->toArray();
		$mdata = $item->metadata->toArray();
		foreach ($mdata as $k => $v)
		{
			if ($v)  $document->setMetadata($k, $v);
		}
		
		$print_link = JRoute::_('index.php?view='.FLEXI_ITEMVIEW.'&cid='.$item->categoryslug.'&id='.$item->slug.'&pop=1&tmpl=component&print=1');
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
		$this->addTemplatePath(JPATH_SITE.DS.'templates'.DS.$mainframe->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates');
		$this->addTemplatePath(JPATH_COMPONENT.DS.'templates'.DS.'default');
		$this->addTemplatePath(JPATH_SITE.DS.'templates'.DS.$mainframe->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates'.DS.'default');
		if ($ilayout) {
			$this->addTemplatePath(JPATH_COMPONENT.DS.'templates'.DS.$ilayout);
			$this->addTemplatePath(JPATH_SITE.DS.'templates'.DS.$mainframe->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates'.DS.$ilayout);
		}

		parent::display($tpl);

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

		// Initialize variables
		$mainframe	= & JFactory::getApplication();
		$cparams    = & JComponentHelper::getParams('com_flexicontent');
		$dispatcher	= & JDispatcher::getInstance();
		$document		= & JFactory::getDocument();
		$user				= & JFactory::getUser();
		$uri				= & JFactory::getURI();
		$db					= &JFactory::getDBO();
		$nullDate		= $db->getNullDate();
		$menu				= JSite::getMenu()->getActive();
		if ($menu) {
			if (FLEXI_J16GE) {
				$menuParams = new JRegistry;
				$menuParams->loadJSON($menu->params);
			} else {
				$menuParams = new JParameter($menu->params);
			}
		}

		// Get the PAGE/COMPONENT parameters (WARNING: merges current menu item parameters in J1.5 but not in J1.6+)
		$params = clone($mainframe->getParams('com_flexicontent'));
		
		// In J1.6+ the above function does not merge current menu item parameters, it behaves like JComponentHelper::getParams('com_flexicontent') was called
		if (FLEXI_J16GE && $menu) {
			$params->merge($menuParams);
		}
		
		// Initialiaze JRequest variables
		JRequest::setVar('loadcurrent', false);  // we are in edit() task, so we load the last item version by default
		if ( isset($menu->query['typeid']) )
			JRequest::setVar('typeid', $menu->query['typeid']);  // This also forces zero if value not set
		$typeid = JRequest::getVar('typeid', 0);
		
		// Get item and model
		$model = & $this->getModel();
		$item = & $this->get('Item');
		if (FLEXI_J16GE) {
			//$model->setId(0); // Clear $model->_item, to force recalculation of the item data
			//$item = & $model->getItem($model->getId(), false);
			$form = & $this->get('Form');
		}
		
		// Load Template-Specific language file to override or add new language strings
		if (FLEXI_FISH || FLEXI_J16GE)
			FLEXIUtilities::loadTemplateLanguageFile( $item->parameters->get('ilayout', 'default') );
		
		// ****************************************************************************************
		// CHECK EDIT / CREATE PERMISSIONS (this is duplicate since it also done at the controller)
		// ****************************************************************************************
		
		// new item and ownership variables
		$isnew = !$item->id;
		$isOwner = ( $item->created_by == $user->get('id') );
		// A unique item id
		JRequest::setVar( 'unique_tmp_itemid', $item->id ? $item->id : date('_Y_m_d_h_i_s_', time()) . uniqid(true) );
		
		if (!$isnew) {
			// EDIT action
			if (FLEXI_J16GE) {
				$asset = 'com_content.article.' . $item->id;
				$has_edit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $isOwner);
				// ALTERNATIVE 1
				//$has_edit = $model->getItemAccess()->get('access-edit'); // includes privileges edit and edit-own
				// ALTERNATIVE 2
				//$rights = FlexicontentHelperPerm::checkAllItemAccess($user->get('id'), 'item', $item->id);
				//$has_edit = in_array('edit', $rights) || (in_array('edit.own', $rights) && $isOwner) ;
			} else if ($user->gid >= 25) {
				$has_edit = true;
			} else if (FLEXI_ACCESS) {
				$rights		= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
				$has_edit = in_array('edit', $rights) || (in_array('editown', $rights) && $isOwner);
			} else {
				$has_edit = $user->authorize('com_content', 'edit', 'content', 'all') || ($user->authorize('com_content', 'edit', 'content', 'own') && $isOwner);
			}

			if (!$has_edit) {
				// user isn't authorize to edit THIS item
				JError::raiseError( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
			}
		} else {
			// CREATE action
			if (FLEXI_J16GE) {
				$can_create = $model->getItemAccess()->get('access-create');
				// ALTERNATIVE 1
				//$allowed_cats = FlexicontentHelperPerm::checkUserElementsAccess($user->get('id'), 'core.create', 'category');
				//$can_create = count($allowed_cats) > 1;
			} else if ($user->gid >= 25) {
				$can_create = 1;
			} else if (FLEXI_ACCESS) {
				$canAdd = FAccess::checkUserElementsAccess($user->gmid, 'submit');
				$can_create = @$canAdd['content'] || @$canAdd['category'];
			} else {
				$can_create	= $user->authorize('com_content', 'add', 'content', 'all');
			}
			
			$allowunauthorize = $params->get('allowunauthorize', 0); // MENU ITEM PARAMETER TO OVERRIDE CREATE PERMISSION !!! 
			
			if(!$can_create && !$allowunauthorize) {
				// user isn't authorize to create NEW item
				
				$notauth_itemid = $params->get('notauthurl');
				if (!$notauth_itemid) {
					// no custom unauthorized page is set in menu item parameters
					JError::raiseError( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
				} else {
					// custom unauthorized page is set in menu item parameters, retrieve url and redirect
					$notauth_menu = JSite::getMenu()->getItem($notauth_itemid);
					$internal_link_vars = $notauth_menu->component ? '&Itemid='.$notauth_itemid.'&option='.$notauth_menu->component : '';
					$notauthurl = JRoute::_($notauth_menu->link.$internal_link_vars, false);
					$mainframe->redirect($notauthurl, JText::_( 'FLEXI_ALERTNOTAUTH' ));
				}
			}
		}
		
		// *********************************************
		// Get more variables to push into the FORM view
		// *********************************************
		
		// Create the type parameters
		$tparams = & $this->get( 'Typeparams' );
		$tparams = new JParameter($tparams);
		
		// Merge item parameters, or type/menu parameters for new item
		if ( $isnew ) {
			if ( $typeid ) $params->merge($tparams);     // Apply type configuration if it type is set
			if ( $menu )   $params->merge($menuParams);  // Apply menu configuration if it menu is set, to override type configuration
		} else {
			$params->merge($item->parameters);
		}
		
		// Check if saving an item that translates an original content in site's default language
		$enable_translation_groups = $cparams->get('enable_translation_groups');
		$is_content_default_lang = substr(flexicontent_html::getSiteDefaultLang(), 0,2) == substr($item->language, 0,2);
		$modify_untraslatable_values = $enable_translation_groups && !$is_content_default_lang && $item->lang_parent_id && $item->lang_parent_id!=$item->id;
		
		// *****************************************************************************
		// Get (CORE & CUSTOM) fields and their VERSIONED values and then
		// (a) Apply Content Type Customization to CORE fields (label, description, etc) 
		// (b) Create the edit html of the CUSTOM fields by triggering 'onDisplayField' 
		// *****************************************************************************
		$fields = & $this->get( 'Extrafields' );
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
					$field->html = '<div class="fc_mini_note_box">'. JText::_('FLEXI_NO_ACCESS_LEVEL_TO_EDIT_FIELD') . '</div>';
				} else if ($modify_untraslatable_values && $field->untranslatable) {
					$field->html = '<div class="fc_mini_note_box">'. JText::_('FLEXI_FIELD_VALUE_IS_UNTRANSLATABLE') . '</div>';
				} else {
					FLEXIUtilities::call_FC_Field_Func($field->field_type, 'onDisplayField', array( &$field, &$item ));
				}
			}
			
			// c. Create main text field, via calling the display function of the textarea field (will also check for tabs)
			// NOTE: We use the text created by the model and not the text retrieved by the CORE plugin code, which maybe overwritten with JoomFish data
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
				$field->value[0] = html_entity_decode(FLEXI_J16GE ? $item->text: $item->text, ENT_QUOTES, 'UTF-8');
				FLEXIUtilities::call_FC_Field_Func('textarea', 'onDisplayField', array(&$field, &$item) );
			}
		}
		
		// Tags used by the item
		$usedtagsids  = & $this->get( 'UsedtagsIds' );  // NOTE: This will normally return the already set versioned value of tags ($item->tags)
		//$usedtagsIds 	= $isnew ? array() : $fields['tags']->value;
		$usedtagsdata = $model->getUsedtagsData($usedtagsids);
		//echo "<br>usedtagsIds: "; print_r($usedtagsids);
		//echo "<br>usedtags (data): "; print_r($usedtagsdata);
		
		// Compatibility for old overriden templates ...
		if (!FLEXI_J16GE) {
			$tags			= & $this->get('Alltags');
			$usedtags	= & $this->get('UsedtagsIds');
		}
		
		// Load permissions (used by form template)
		$perms = $this->_getItemPerms($item);

		// Get the edit lists
		$lists = $this->_buildEditLists($perms, $params);
		
		// Get number of subscribers
		$subscribers 	= & $this->get( 'SubscribersCount' );
		
		// Get menu overridden categories/main category fields
		$menuCats = $this->_getMenuCats($item, $perms, $params);
		
		// Create and submit configuration (for new items) into the session
		$submitConf = $this->_createSubmitConf($item, $perms, $params);
		
		// Item language related vars
		if (FLEXI_FISH || FLEXI_J16GE) {
			$languages = FLEXIUtilities::getLanguages();
			$itemlang = new stdClass();
			$itemlang->shortcode = substr($item->language ,0,2);
			$itemlang->name = $languages->{$item->language}->name;
			$itemlang->image = '<img src="'.@$languages->{$item->language}->imgsrc.'" alt="'.$languages->{$item->language}->name.'" />';
		}
		
		//Load the JEditor object
		$editor =& JFactory::getEditor();
		
		// Add the js files to the document <head> section
		//JHTML::_('behavior.formvalidation'); // Commented out, custom overloaded validator class loaded inside form template file
		JHTML::_('behavior.tooltip');
		JHTML::_('script', 'joomla.javascript.js', 'includes/js/');

		// Add css files to the document <head> section
		$document->addStyleSheet($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css');
		if (!FLEXI_J16GE) {
			$document->addStyleSheet($this->baseurl.'/administrator/templates/khepri/css/general.css');
		}
		$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #flexicontent dd { height: 1%; }</style><![endif]-->');
		
		// Set page title
		$title = !$isnew ? JText::_( 'FLEXI_EDIT' ) : JText::_( 'FLEXI_NEW' );
		$document->setTitle($title);
		
		// Add title to pathway
		$pathway =& $mainframe->getPathWay();
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
		$this->assignRef('itemlang',   $itemlang);
		$this->assignRef('pageclass_sfx', $pageclass_sfx);
		
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
				if ( isset($child->_attributes['enableparam']) && !$cparams->get($child->_attributes['enableparam']) ) {
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
			$pane = & JPane::getInstance('sliders');
			$this->assignRef('pane'				, $pane);
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
		
		parent::display($tpl);
	}
	
	/**
	 * Creates the HTML of various form fields used in the item edit form
	 *
	 * @since 1.0
	 */
	function _buildEditLists(& $perms, & $params)
	{
		global $globalcats;
		$lists = array();
		
		$db       = & JFactory::getDBO();
		$user     = & JFactory::getUser();	// get current user
		$item     = & $this->get('Item');		// get the item from the model
		$document = & JFactory::getDocument();
		
		$categories = $globalcats;			// get the categories tree
		$selectedcats = & $this->get( 'Catsselected' );		// get category ids, NOTE: This will normally return the already set versioned value of categories ($item->categories)
		$actions_allowed = array('core.create');					// user actions allowed for categories
		$types = & $this->get( 'Typeslist' );
		$typesselected = '';
		$isnew = !$item->id;
		
		// Retrieve author configuration
		$db->setQuery('SELECT author_basicparams FROM #__flexicontent_authors_ext WHERE user_id = ' . $user->id);
		if ( $authorparams = $db->loadResult() )
			$authorparams = new JParameter($authorparams);
		//echo "<pre>"; print_r($authorparams); exit;
		
		if ( $perms['canpublish'] && !$item->id ) $item->state = 1;
		
		// Multi-category form field, for user allowed to use multiple categories
		$lists['cid'] = '';
		if ($perms['multicat'])
		{
			// Get author's maximum allowed categories per item and set js limitation
			$max_cat_assign = !$authorparams ? 0 : intval($authorparams->get('max_cat_assign',0));
			$document->addScriptDeclaration('
				max_cat_assign_fc = '.$max_cat_assign.';
				existing_cats_fc  = ["'.implode('","',$selectedcats).'"];
				max_cat_overlimit_msg_fc = "'.JText::_('FLEXI_TOO_MANY_ITEM_CATEGORIES').'";
			');
			
			$class = $max_cat_assign ? "mcat validate-fccats" : "validate mcat";
			$attribs = 'class="'.$class.'" multiple="multiple" size="8"';
			if (FLEXI_J16GE) {
				$lists['cid'] = flexicontent_cats::buildcatselect($categories, 'jform[cid][]', $selectedcats, false, $attribs, true, true,	$actions_allowed);
			} else {
				$lists['cid'] = flexicontent_cats::buildcatselect($categories, 'cid[]', $selectedcats, false, $attribs, true, true,	$actions_allowed);
			}
		}
		
		//buid types selectlist
		if (FLEXI_J16GE) {
			$lists['type'] = flexicontent_html::buildtypesselect($types, 'jform[type_id]', $typesselected, 1, 'class="required"', true );
		} else {
			$lists['type'] = flexicontent_html::buildtypesselect($types, 'type_id', $typesselected, 1, 'class="required"', true );
		}
		
		// Main category form field
		$class = 'scat';
		if ($perms['multicat']) {
			$class .= ' validate-catid';
		} else {
			$class .= ' required';
		}
		$attribs = 'class="'.$class.'"';
		if (FLEXI_J16GE) {
			$lists['catid'] = flexicontent_cats::buildcatselect($categories,'jform[catid]', $item->catid, 2, $attribs, true, true, $actions_allowed);
		} else {
			$lists['catid'] = flexicontent_cats::buildcatselect($categories,'catid', $item->catid, 2, $attribs, true, true, $actions_allowed);
		}
		
		if (!FLEXI_J16GE) {
			// build state list
			$state = array();
			$state[] = JHTML::_('select.option',  1, JText::_( 'FLEXI_PUBLISHED' ) );
			$state[] = JHTML::_('select.option',  0, JText::_( 'FLEXI_UNPUBLISHED' ) );
			$state[] = JHTML::_('select.option',  -3, JText::_( 'FLEXI_PENDING' ) );
			$state[] = JHTML::_('select.option',  -4, JText::_( 'FLEXI_TO_WRITE' ) );
			$state[] = JHTML::_('select.option',  -5, JText::_( 'FLEXI_IN_PROGRESS' ) );
			$state[] = JHTML::_('select.option',  FLEXI_J16GE ? 2:-1, JText::_( 'FLEXI_ARCHIVED' ) );
			$lists['state'] = JHTML::_('select.genericlist', $state, 'state', '', 'value', 'text', $item->state );
			
			// build version approval list
			$vstate = array();
			$vstate[] = JHTML::_('select.option',  1, JText::_( 'FLEXI_NO' ) );
			$vstate[] = JHTML::_('select.option',  2, JText::_( 'FLEXI_YES' ) );
			
			$fieldname = FLEXI_J16GE ? 'jform[vstate]' : 'vstate';
			$elementid = FLEXI_J16GE ? 'jform_vstate' : 'vstate';
			$attribs = ' style ="float:left!important;" ';
			$lists['vstate'] = JHTML::_('select.radiolist', $vstate, $fieldname, $attribs, 'value', 'text', 2, $elementid);
		}
		
		$disable_comments = array();
		$disable_comments[] = JHTML::_('select.option', '', JText::_( 'FLEXI_DEFAULT_BEHAVIOR' ) );
		$disable_comments[] = JHTML::_('select.option',  0, JText::_( 'FLEXI_DISABLE' ) );
		
		if ( $params->get('allowdisablingcomments_fe') )
		{
			// Set to zero if disabled or to "" (aka use default) for any other value.  THIS WILL FORCE comment field use default Global/Category/Content Type setting or disable it,
			// thus a per item commenting system cannot be selected. This is OK because it makes sense to have a different commenting system per CONTENT TYPE by not per Content Item
			$isdisabled = !$params->get('comments') && strlen($params->get('comments'));
			$fieldvalue = $isdisabled ? "0" : "";
			
			$fieldname = FLEXI_J16GE ? 'jform[attribs][comments]' : 'params[comments]';
			$elementid = FLEXI_J16GE ? 'jform_attribs_comments' : 'params_comments';
			$attribs = FLEXI_J16GE ? ' style ="float:left!important;" ' : '';
			$lists['disable_comments'] = JHTML::_('select.radiolist', $disable_comments, $fieldname, $attribs, 'value', 'text', $fieldvalue, $elementid);
		}
		
		// build granular access list
		if (FLEXI_ACCESS) {
			if (isset($user->level)) {
				$lists['access'] = FAccess::TabGmaccess( $item, 'item', 1, 0, 0, 1, 0, 1, 0, 1, 1 );
			} else {
				$lists['access'] = JText::_('Your profile has been changed, please logout to access to the permissions');
			}
		} else {
			$lists['access'] = JHTML::_('list.accesslevel', $item);
		}

		// Build languages list
		$site_default_lang = flexicontent_html::getSiteDefaultLang();
		$allowed_langs = !$authorparams ? null : $authorparams->get('langs_allowed',null);
		$allowed_langs = !$allowed_langs ? null : FLEXIUtilities::paramToArray($allowed_langs);
		if (!$isnew && $allowed_langs) $allowed_langs[] = $item->language;
		if (FLEXI_J16GE) {
			$item_lang = $isnew ? $site_default_lang : $item->language;
			$lists['languages'] = flexicontent_html::buildlanguageslist('jform[language]', '', $item_lang, 3, $allowed_langs);
		} else if (FLEXI_FISH) {
			$item_lang = $isnew ? $site_default_lang : $item->language;
			$lists['languages'] = flexicontent_html::buildlanguageslist('language', '', $item_lang, 3, $allowed_langs);
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
	function _getItemPerms( &$item)
	{
		$user = & JFactory::getUser();	// get current user\
		$isOwner = ( $item->created_by == $user->get('id') );
		
		$perms 	= array();
		if (FLEXI_J16GE) {
			$perms['isSuperAdmin']= $user->authorise('core.admin', 'root.1');
			$permission = FlexicontentHelperPerm::getPerm();
			$perms['multicat'] = $permission->MultiCat;
			$perms['cantags'] = $permission->CanUseTags;
			$perms['canparams'] = $permission->CanParams;
			$perms['cantemplates']= $permission->CanTemplates;
			
			//item specific permissions
			if ( $item->id ) {
				$asset = 'com_content.article.' . $item->id;
				$perms['canedit']			= $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $isOwner);
				$perms['canpublish']	= $user->authorise('core.edit.state', $asset) || ($user->authorise('core.edit.state.own', $asset) && $isOwner);
				$perms['candelete']		= $user->authorise('core.delete', $asset) || ($user->authorise('core.delete.own', $asset) && $isOwner);
			} else {
				// *** New item *** get general edit/publish/delete permissions
				$perms['canedit']			= $user->authorise('core.edit', 'com_flexicontent') || $user->authorise('core.edit.own', 'com_flexicontent');
				$perms['canpublish']	= $user->authorise('core.edit.state', 'com_flexicontent') || $user->authorise('core.edit.state.own', 'com_flexicontent');
				$perms['candelete']		= $user->authorise('core.delete', 'com_flexicontent') || $user->authorise('core.delete.own', 'com_flexicontent');
			}
			$perms['editcreationdate'] = $user->authorise('flexicontent.editcreationdate', 'com_flexicontent');
			$perms['canright']		= $permission->CanConfig;
		} else if (FLEXI_ACCESS) {
			$perms['isSuperAdmin']= $user->gid >= 25;
			$perms['multicat'] 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'multicat', 'users', $user->gmid) : 1;
			$perms['cantags'] 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'usetags', 'users', $user->gmid) : 1;
			$perms['canparams'] 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'paramsitems', 'users', $user->gmid) : 1;
			$perms['cantemplates']= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'templates', 'users', $user->gmid) : 1;

			if ($item->id) {
				$rights = FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
				$perms['canedit']			= ($user->gid < 25) ? ( (in_array('editown', $rights) && $isOwner) || (in_array('edit', $rights)) ) : 1;
				$perms['canpublish']	= ($user->gid < 25) ? ( (in_array('publishown', $rights) && $isOwner) || (in_array('publish', $rights)) ) : 1;
				$perms['candelete']		= ($user->gid < 25) ? ( (in_array('deleteown', $rights) && $isOwner) || (in_array('delete', $rights)) ) : 1;
				$perms['canright']		= ($user->gid < 25) ? ( (in_array('right', $rights)) ) : 1;
			} else {
				// *** New item *** get general edit/publish/delete permissions
				$canEditAll 			= FAccess::checkAllContentAccess('com_content','edit','users',$user->gmid,'content','all');
				$canEditOwnAll		= FAccess::checkAllContentAccess('com_content','editown','users',$user->gmid,'content','all');
				$perms['canedit']			= ($user->gid < 25) ? $canEditAll || $canEditOwnAll : 1;
				$canPublishAll 		= FAccess::checkAllContentAccess('com_content','publish','users',$user->gmid,'content','all');
				$canPublishOwnAll	= FAccess::checkAllContentAccess('com_content','publishown','users',$user->gmid,'content','all');
				$perms['canpublish']	= ($user->gid < 25) ? $canPublishAll || $canPublishOwnAll : 1;
				$canDeletehAll 		= FAccess::checkAllContentAccess('com_content','delete','users',$user->gmid,'content','all');
				$canDeleteOwnAll	= FAccess::checkAllContentAccess('com_content','deleteown','users',$user->gmid,'content','all');
				$perms['candelete']		= ($user->gid < 25) ? $canDeletehAll || $canDeleteOwnAll : 1;
				$perms['canright']		= ($user->gid < 25) ? 0 : 1;
			}
			//$perms['editcreationdate'] = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'editcreationdate', 'users', $user->gmid) : 1;
		} else {
			// J1.5 permissions with no FLEXIaccess are only general, no item specific permissions
			$perms['isSuperAdmin']= $user->gid >= 25;
			$perms['multicat']		= 1;
			$perms['cantags'] 		= 1;
			$perms['canparams'] 	= 1;
			$perms['cantemplates']= $user->gid >= 25;
			$perms['canedit']			= ($user->gid >= 20);
			$perms['canpublish']	= ($user->gid >= 21);
			$perms['candelete']		= ($user->gid >= 21);
			$perms['canright']		= ($user->gid >= 21);
			//$perms['editcreationdate'] = ($user->gid >= 25);
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
				break;
			case 1:  // submit to a single category, selecting from a MENU SPECIFIED categories subset
				$mo_cats = false;
				$mo_maincat = flexicontent_cats::buildcatselect($categories, $catid_form_fieldname, $maincatid, 2, ' class="required" ', $check_published=true, $check_perms=false);
				break;
			case 2:  // submit to multiple categories, selecting from a MENU SPECIFIED categories subset
				$attribs = 'class="validate" multiple="multiple" size="8"';
				$mo_cats = flexicontent_cats::buildcatselect($categories, $cid_form_fieldname, array(), false, $attribs, $check_published=true, $check_perms=false);
				$mo_maincat = flexicontent_cats::buildcatselect($categories, $catid_form_fieldname, $maincatid, 2, ' class="validate-catid" ', $check_published=true, $check_perms=false);
				break;
		}
		$menuCats = new stdClass();
		$menuCats->cid   = $mo_cats;
		$menuCats->catid = $mo_maincat;
		
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
			'autopublished'   => $params->get('autopublished', 0)  // Publish the item
		);
		$submit_conf_hash = md5(serialize($submit_conf));
		
		$session 	=& JFactory::getSession();
		$item_submit_conf = $session->get('item_submit_conf', array(),'flexicontent');
		$item_submit_conf[$submit_conf_hash] = $submit_conf;
		$session->set('item_submit_conf', $item_submit_conf, 'flexicontent');
		
		if (FLEXI_J16GE)
			return '<input type="hidden" name="jform[submit_conf]" value="'.$submit_conf_hash.'" >';
		else
			return '<input type="hidden" name="submit_conf" value="'.$submit_conf_hash.'" >';
	}
}
?>
