<?php
/**
 * @version 1.5 stable $Id: view.html.php 1295 2012-05-12 08:50:13Z ggppdk $
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

jimport( 'joomla.application.component.view');

/**
 * HTML View class for the FLEXIcontent View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewCategory extends JView
{
	/**
	 * Creates the Forms for the View
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		global $globalnoroute, $globalcats;
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');
		if (!is_array($globalnoroute))	$globalnoroute	= array();

		JHTML::_('behavior.tooltip');

		//initialize variables
		$document 	= & JFactory::getDocument();
		$menus		= & JSite::getMenu();
		$menu    	= $menus->getActive();
		$uri 		= & JFactory::getURI();
		$dispatcher	= & JDispatcher::getInstance();
		$user		= & JFactory::getUser();
		$aid		= FLEXI_J16GE ? $user->getAuthorisedViewLevels() : (int) $user->get('aid');

		// Get the PAGE/COMPONENT parameters (WARNING: merges current menu item parameters in J1.5 but not in J1.6+)
		$params = clone($mainframe->getParams('com_flexicontent'));
		
		// In J1.6+ the above function does not merge current menu item parameters, it behaves like JComponentHelper::getParams('com_flexicontent') was called
		if (FLEXI_J16GE && $menu) {
			$menuParams = new JRegistry;
			$menuParams->loadJSON($menu->params);
			$params->merge($menuParams);
		}
		
		//add css file
		if (!$params->get('disablecss', '')) {
			$document->addStyleSheet($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css');
			$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext {zoom:1;}</style><![endif]-->');
		}
		//allow css override
		if (file_exists(JPATH_SITE.DS.'templates'.DS.$mainframe->getTemplate().DS.'css'.DS.'flexicontent.css')) {
			$document->addStyleSheet($this->baseurl.'/templates/'.$mainframe->getTemplate().'/css/flexicontent.css');
		}
		
		// Get data from the model		
		$category   = & $this->get('Category');
		$categories = & $this->get('Childs');
		$items    = & $this->get('Data');
		$total    = & $this->get('Total');
		$filters  = & $this->get('Filters');
		$alpha    = & $this->get('Alphaindex');
		$model    = & $this->getModel();
		
		// Request variables, WARNING, must be loaded after retrieving items, because limitstart may have been modified
		$limitstart = JRequest::getInt('limitstart');
		$format     = JRequest::getVar('format', null);
		
		// Set category parameters as VIEW's parameters (category parameters are merged with component/page/author parameters already)
		$params	= & $category->parameters;
		
		// Load Template-Specific language file to override or add new language strings
		FLEXIUtilities::loadTemplateLanguageFile( $params->get('clayout') );
		
		$authorid = JRequest::getInt('authorid', 0);
		$layout = JRequest::getInt('layout', 0);
		$authordescr_item = false;
		if ($authorid && $params->get('authordescr_itemid') && $format != 'feed') {
			$authordescr_item = & $this->get('AuthorDescrItem');
			list($authordescr_item) = FlexicontentFields::getFields($authordescr_item, 'items', $params, $aid);
		}
		
		// Bind Fields
		if ($format != 'feed') {
			$items 	= & FlexicontentFields::getFields($items, 'category', $params, $aid);
		}

		//Set layout
		$this->setLayout('category');

		$limit		= $mainframe->getUserStateFromRequest('com_flexicontent'.$category->id.'.category.limit', 'limit', $params->def('limit', 0), 'int');

		$cats		= new flexicontent_cats((int)$category->id);
		$parents	= $cats->getParentlist();
		
		// We can probaly optimize this part later
		if ($params->get('rootcat')) {
			$rootcats	= new flexicontent_cats((int)$params->get('rootcat'));
			$allroots	= $rootcats->getParentlist();
			$roots		= array();
			foreach ($allroots as $root) {
				array_push($roots, $root->id);
			}
		}
		
		// Because the application sets a default page title, we need to get title right from the menu item itself
		if (is_object( $menu )) {
			$menu_params = new JParameter( $menu->params );		
			
			if (!$menu_params->get( 'page_title')) {
				$params->set('page_title',	$category->title);
			}
		} else {
			$params->set('page_title',	$category->title);
		}
		
		if (FLEXI_J16GE) {  // Not available in J1.5
			// Add Site Name to page title
			if ($mainframe->getCfg('sitename_pagetitles', 0) == 1) {
				$params->set('page_title', $mainframe->getCfg('sitename') ." - ". $params->get( 'page_title' ));
			}
			elseif ($mainframe->getCfg('sitename_pagetitles', 0) == 2) {
				$params->set('page_title', $params->get( 'page_title' ) ." - ". $mainframe->getCfg('sitename'));
			}
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
		
		// Respect menut item depth, defined in menu item
		for ($p=$item_depth; $p<count($parents); $p++) {
			// Do not add the above and root categories when coming from a directory view
			if (isset($allroots)) {
				if (!in_array($parents[$p]->id, $roots)) {
					$pathway->addItem( $this->escape($parents[$p]->title), JRoute::_( FlexicontentHelperRoute::getCategoryRoute($parents[$p]->categoryslug) ) );
				}
			} else {
				$pathway->addItem( $this->escape($parents[$p]->title), JRoute::_( FlexicontentHelperRoute::getCategoryRoute($parents[$p]->categoryslug) ) );
			}
		}

		$document->setTitle( $params->get( 'page_title' ) );

		// @TODO check that as it seems to be dirty :(
		$uri  			=& JFactory::getURI();
		$base 			= $uri->getScheme() . '://' . $uri->getHost();
		$start = JRequest::getVar('start', '');
		$start = $start ? "&start=".$start : "";
		$ucanonical 	= $base . '/'. JRoute::_(FlexicontentHelperRoute::getCategoryRoute($category->slug).$start);
		if ($params->get('add_canonical')) {
			$document->addHeadLink( $ucanonical, 'canonical', 'rel', '' );
		}
		
		if ($category->id) {   // possibly not set for author items OR my items
			if (FLEXI_J16GE) {
				if ($category->metadesc) {
					$document->setDescription( $category->metadesc );
				}
				
				if ($category->metakey) {
					$document->setMetadata('keywords', $category->metakey);
				}
				
				$meta_params = new JParameter($category->metadata);
				
				if ($mainframe->getCfg('MetaTitle') == '1') {
					$meta_title = $meta_params->get('page_title') ? $meta_params->get('page_title') : $category->title;
					$document->setMetaData('title', $meta_title);
				}
				
				if ($mainframe->getCfg('MetaAuthor') == '1') {
					$meta_author = $meta_params->get('author') ? $meta_params->get('author') : JFactory::getUser($category->created_user_id)->name;
					$document->setMetaData('author', $meta_author);
				}
			} else {
				if ($mainframe->getCfg('MetaTitle') == '1') {
					$document->setMetaData('title', $category->title);
				}
			}
		}
		
		if ($params->get('show_feed_link', 1) == 1) {
			//add alternate feed link
			$link	= '&format=feed';
			$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
			$document->addHeadLink(JRoute::_($link.'&type=rss'), 'alternate', 'rel', $attribs);
			$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
			$document->addHeadLink(JRoute::_($link.'&type=atom'), 'alternate', 'rel', $attribs);
		}
		
		$themes		= flexicontent_tmpl::getTemplates();
		
		$authordescr_item_html = false;
		if ($authordescr_item) {
			
			//echo "<pre>"; print_r($authordescr_item);exit();
			$ilayout = $authordescr_item->params->get('ilayout', '');
			if ($ilayout==='') {
				$type = & JTable::getInstance('flexicontent_types', '');
				$type->id = $authordescr_item->type_id;
				$type->load();
				$type->params = new JParameter($type->attribs);
				$ilayout = $type->params->get('ilayout', 'default');
				//echo "<pre>"; print_r($type);exit();
			}
			
			// start capturing output into a buffer
			$this->item = & $authordescr_item; 
			$this->params_saved = $this->params;
			$this->params = & $authordescr_item->params;
			$this->tmpl = '.item.'.$ilayout;
			$this->print_link = JRoute::_('index.php?view=items&id='.$authordescr_item->slug.'&pop=1&tmpl=component');
			
			ob_start();
			// include the requested template filename in the local scope
			// (this will execute the view logic).
			if ( file_exists(JPATH_SITE.DS.'templates'.DS.$mainframe->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates'.DS.$ilayout) )
				include JPATH_SITE.DS.'templates'.DS.$mainframe->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates'.DS.$ilayout.DS.'item.php';
			else if (file_exists(JPATH_COMPONENT.DS.'templates'.DS.$ilayout))
				include JPATH_COMPONENT.DS.'templates'.DS.$ilayout.DS.'item.php';
			else
				include JPATH_COMPONENT.DS.'templates'.DS.'default'.DS.'item.php';
			
			// done with the requested template; get the buffer and
			// clear it.
			$authordescr_item_html = ob_get_contents();
			ob_end_clean();
			$this->params = $this->params_saved;
		}
		//echo $authordescr_item_html; exit();
		
		if ($params->get('clayout')) {
			// Add the templates css files if availables
			if (isset($themes->category->{$params->get('clayout')}->css)) {
				foreach ($themes->category->{$params->get('clayout')}->css as $css) {
					$document->addStyleSheet($this->baseurl.'/'.$css);
				}
			}
			// Add the templates js files if availables
			if (isset($themes->category->{$params->get('clayout')}->js)) {
				foreach ($themes->category->{$params->get('clayout')}->js as $js) {
					$document->addScript($this->baseurl.'/'.$js);
				}
			}
			// Set the template var
			$tmpl = $themes->category->{$params->get('clayout')}->tmplvar;
		} else {
			$tmpl = '.category.default';
		}
		
		// @TODO trigger the plugin selectively
		// and delete the plugins tags if not active
		if ($params->get('trigger_onprepare_content_cat')) // just check if the parmeter is active
		{
			JPluginHelper::importPlugin('content');
	
			// Allow to trigger content plugins on category description
			$category->text			= $category->description;
			if (FLEXI_J16GE) {
				$results = $dispatcher->trigger('onContentPrepare', array ('com_content.category', &$category, &$params, 0));
			} else {
				$results = $dispatcher->trigger('onPrepareContent', array (& $category, & $params, 0));
			}
			
			$category->description 	= $category->text;
		}

		// Maybe here not to import all plugins but just those for description field ???
		// Anyway these events are usually not very time consuming, so lets trigger all of them ???
		JPluginHelper::importPlugin('content');
		
		foreach ($items as $item) 
		{
			$item->event 	= new stdClass();
			$item->params 	= new JParameter($item->attribs);
			
			// !!! The triggering of the event onPrepareContent(J1.5)/onContentPrepare(J1.6+) of content plugins
			// !!! for description field (maintext) along with all other flexicontent
			// !!! fields is handled by flexicontent.fields.php
			// !!! Had serious performance impact
			// CODE REMOVED
			
			// We must check if the current category is in the categories of the item ..
			$item_in_category=false;
			if ($item->catid == $category->id) {
				$item_in_category=true;
			} else {
				foreach ($item->cats as $cat) {
					if ($cat->id == $category->id) {  $item_in_category=true;  break;  }
				}
			}
			
			// ADVANCED CATEGORY ROUTING (=set the most appropriate category for the item ...)
			// CHOOSE APPROPRIATE category-slug FOR THE ITEM !!! ( )
			if ($item_in_category && !in_array($category->id, $globalnoroute)) {
				// 1. CATEGORY SLUG: CURRENT category
				// Current category IS a category of the item and ALSO routing (creating links) to this category is allowed
				$item->categoryslug = $category->slug;
			} else if (!in_array($item->catid, $globalnoroute)) {
				// 2. CATEGORY SLUG: ITEM's MAIN category   (alread SET, ... no assignment needed)
				// Since we cannot use current category (above), we will use item's MAIN category 
				// ALSO routing (creating links) to this category is allowed
			} else {
				// 3. CATEGORY SLUG: ANY ITEM's category
				// We will use the first for which routing (creating links) to the category is allowed
				$allcats = array();
				$item->cats = $item->cats?$item->cats:array();
				foreach ($item->cats as $cat) {
					if (!in_array($cat->id, $globalnoroute)) {
						$item->categoryslug = $globalcats[$cat->id]->slug;
						break;
					}
				}
			}
			
			// Just put item's text (description field) inside property 'text' in case the events modify the given text,
			$item->text = isset($item->fields['text']->display) ? $item->fields['text']->display : '';
			
			// Maybe here not to import all plugins but just those for description field ???
			// Anyway these events are usually not very time consuming, so lets trigger all of them ???
			JPluginHelper::importPlugin('content');
			
			// Set the view and option to 'category' and 'com_content'  (actually view is already called category)
			JRequest::setVar('option', 'com_content');
			JRequest::setVar("isflexicontent", "yes");
			
			// These events return text that could be displayed at appropriate positions by our templates
			$item->event = new stdClass();
			
			$results = $dispatcher->trigger('onAfterDisplayTitle', array (&$item, &$params, $limitstart));
			$item->event->afterDisplayTitle = trim(implode("\n", $results));
	
			$results = $dispatcher->trigger('onBeforeDisplayContent', array (& $item, & $params, $limitstart));
			$item->event->beforeDisplayContent = trim(implode("\n", $results));
	
			$results = $dispatcher->trigger('onAfterDisplayContent', array (& $item, & $params, $limitstart));
			$item->event->afterDisplayContent = trim(implode("\n", $results));
							
			// Set the option back to 'com_flexicontent'
		  JRequest::setVar('option', 'com_flexicontent');
		  
			// Put text back into the description field, THESE events SHOULD NOT modify the item text, but some plugins may do it anyway... , so we assign text back for compatibility
			$item->fields['text']->display = & $item->text;
			
		}
		
		// category image
		$show_cat_image = $params->get('show_description_image', 0);  // bad named maybe
		$cat_image_source = $params->get('cat_image_source', 0);
		$cat_link_image = $params->get('cat_link_image', 1);
		$cat_image_method = $params->get('cat_image_method', 1);
		$cat_image_width = $params->get('cat_image_width', 80);
		$cat_image_height = $params->get('cat_image_height', 80);
		
		// category image
		$cat 		= & $category;
		$config	=& JFactory::getConfig();
		$joomla_image_path 	= FLEXI_J16GE ? $config->getValue('config.image_path', '') : $config->getValue('config.image_path', 'images'.DS.'stories');
		
		$image = "";
		if ($show_cat_image) {
			$cat->image = FLEXI_J16GE ? $params->get('image') : $cat->image;
			$image = "";
			$cat->introtext = & $cat->description;
			$cat->fulltext = "";
			
			if ( $cat_image_source && $cat->image && JFile::exists( JPATH_SITE .DS. $joomla_image_path .DS. $cat->image ) ) {
				$src = JURI::base(true)."/".$joomla_image_path."/".$cat->image;
		
				$h		= '&amp;h=' . $cat_image_height;
				$w		= '&amp;w=' . $cat_image_width;
				$aoe	= '&amp;aoe=1';
				$q		= '&amp;q=95';
				$zc		= $cat_image_method ? '&amp;zc=' . $cat_image_method : '';
				$ext = pathinfo($src, PATHINFO_EXTENSION);
				$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
				$conf	= $w . $h . $aoe . $q . $zc . $f;
		
				$image = JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$src.$conf;
			} else if ( $cat_image_source!=1 && $src = flexicontent_html::extractimagesrc($cat) ) {
	
				$h		= '&amp;h=' . $cat_image_height;
				$w		= '&amp;w=' . $cat_image_width;
				$aoe	= '&amp;aoe=1';
				$q		= '&amp;q=95';
				$zc		= $cat_image_method ? '&amp;zc=' . $cat_image_method : '';
				$ext = pathinfo($src, PATHINFO_EXTENSION);
				$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
				$conf	= $w . $h . $aoe . $q . $zc . $f;
	
				$base_url = (!preg_match("#^http|^https|^ftp#i", $src)) ?  JURI::base(true).'/' : '';
				$image = JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
			}
			
			if ($image) {
				$image = '<img class="fccat_image" src="'.$image.'" alt="'.$this->escape($cat->title).'" title="'.$this->escape($cat->title).'"/>';
			} else {
				//$image = '<div class="fccat_image" style="height:'.$cat_image_height.'px;width:'.$cat_image_width.'px;" ></div>';
			}
			if ($cat_link_image && $image) {
				$image = '<a href="'.JRoute::_( FlexicontentHelperRoute::getCategoryRoute($cat->slug) ).'">'.$image.'</a>';
			}
		}
		$cat->image = $image;
		
		// remove previous alpha index filter
		$uri->delVar('letter');
		
		// remove filter variables (includes search box and sort order)
		preg_match_all('/filter[^=]*/', $uri->toString(), $matches);
		foreach($matches[0] as $match)
		{
			$uri->delVar($match);
		}

		//ordering
		$filter_order		= JRequest::getCmd('filter_order', 'i.title');
		$filter_order_Dir	= JRequest::getCmd('filter_order_Dir', 'ASC');
		$filter				= JRequest::getString('filter');
		
		$lists						= array();
		$lists['filter_order']		= $filter_order;
		$lists['filter_order_Dir'] 	= $filter_order_Dir;
		$lists['filter']			= $filter;

		// Add html to filter object
		if ($filters)
		{
			// Make the filter compatible with Joomla standard cache
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();

			foreach ($filters as $filtre)
			{
				//$value		= $mainframe->getUserStateFromRequest( $option.'.category'.$category->id.'.filter_'.$filtre->id, 'filter_'.$filtre->id, '', 'string' );
				$value 	= JRequest::getString('filter_'.$filtre->id, '', 'request');
				//$results 	= $dispatcher->trigger('onDisplayFilter', array( &$filtre, $value ));
				$fieldname = $filtre->iscore ? 'core' : $filtre->field_type;
				FLEXIUtilities::call_FC_Field_Func($fieldname, 'onDisplayFilter', array( &$filtre, $value ) );
				$lists['filter_' . $filtre->id] = $value;
			}
		}

		// Create the pagination object
		$pageNav = $model->getPagination();
		$resultsCounter = $model->getResultsCounter();

		$this->assign('action', 			$uri->toString());

		$print_link = JRoute::_('index.php?view=category&cid='.$category->slug.($authorid?"&authorid=$authorid&layout=author":"").'&pop=1&tmpl=component');
		
		$this->assignRef('params' , 		$params);
		$this->assignRef('categories' , $categories);
		$this->assignRef('items' , 			$items);
		$this->assignRef('authordescr_item_html' , $authordescr_item_html);
		$this->assignRef('category' , 	$category);
		$this->assignRef('limitstart' , $limitstart);
		$this->assignRef('pageNav' , 		$pageNav);
		$this->assignRef('resultsCounter' ,	$resultsCounter);
		$this->assignRef('filters' ,	 	$filters);
		$this->assignRef('lists' ,	 		$lists);
		$this->assignRef('alpha' ,	 		$alpha);
		$this->assignRef('tmpl' ,				$tmpl);
		$this->assignRef('print_link' ,	$print_link);

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
		if ($params->get('clayout')) {
			$this->addTemplatePath(JPATH_COMPONENT.DS.'templates'.DS.$params->get('clayout'));
			$this->addTemplatePath(JPATH_SITE.DS.'templates'.DS.$mainframe->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates'.DS.$params->get('clayout'));
		}

		parent::display($tpl);

	}
}
?>
