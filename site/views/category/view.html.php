<?php
/**
 * @version 1.5 stable $Id: view.html.php 1959 2014-09-18 00:15:15Z ggppdk $
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

jimport('legacy.view.legacy');
jimport('joomla.filesystem.file');
use Joomla\String\StringHelper;

/**
 * HTML View class for the Category View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewCategory extends JViewLegacy
{
	/**
	 * Creates the page's display
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		// Get Non-routing Categories, and Category Tree
		global $globalnoroute, $globalcats;
		if (!is_array($globalnoroute)) $globalnoroute = array();
		
		//initialize variables
		$dispatcher = JDispatcher::getInstance();
		$app      = JFactory::getApplication();
		$jinput   = JFactory::getApplication()->input;
		$session  = JFactory::getSession();
		$option   = JRequest::getVar('option');
		$format   = JRequest::getCmd('format', 'html');
		$print    = JRequest::getCmd('print');
		$document = JFactory::getDocument();
		
		// Check for Joomla issue with system plugins creating JDocument in early events forcing it to be wrong type, when format as url suffix is enabled
		if ($format && $document->getType() != strtolower($format))
		{
			echo '<div class="alert">WARNING: &nbsp; Document format should be: <b>'.$format.'</b> but current document is: <b>'. $document->getType().'</b> <br/>Some system plugin may have forced current document type</div>';
		}
		
		$menus    = $app->getMenu();
		$menu     = $menus->getActive();
		$uri      = JFactory::getURI();
		$user     = JFactory::getUser();
		$aid      = JAccess::getAuthorisedViewLevels($user->id);
		
		// Get model
		$model  = $this->getModel();
		
		// Allow clayout from HTTP request, this will be checked during loading category parameters
		$model->setCatLayout('__request__');
		// Indicate to model to merge menu parameters if menu matches
		$model->mergeMenuParams = true;
		
		// Get the category, loading category data and doing parameters merging
		$category = $this->get('Category');
		
		// Get category parameters as VIEW's parameters (category parameters are merged parameters in order: layout(template-manager)/component/ancestors-cats/category/author/menu)
		$params   = $category->parameters;
		
		if ($category->id)
			$meta_params = new JRegistry($category->metadata);
		
		
		// ***********************
		// Get data from the model
		// ***********************
		
		$categories = $this->get('Childs'); // this will also count sub-category items is if  'show_itemcount'  is enabled
		$peercats   = $this->get('Peers');  // this will also count sub-category items is if  'show_subcatcount_peercat'  is enabled
		$items   = $this->get('Data');
		$total   = $this->get('Total');
		$filters = !$print ? $this->get('Filters') : array();
		$comments= !$print && $params->get('show_comments_count', 0)  ?  $this->get('CommentsInfo')  :  null;
		$alpha   = !$print && $params->get('show_alpha', 1)  ?  $this->get('Alphaindex')  :  array();  // This is somewhat expensive so calculate it only if required
		
		// Request variables, WARNING, must be loaded after retrieving items, because limitstart may have been modified
		$limitstart = JRequest::getInt('limitstart');
		
		
		// ************************
		// CATEGORY LAYOUT handling
		// ************************
		
		// Get category 's layout as this may have been altered by model's decideLayout()
		$clayout = $params->get('clayout');
		
		// Get cached template data, re-parsing XML/LESS files, also loading any template language files of a specific template
		$themes = flexicontent_tmpl::getTemplates(  array($clayout) );
		
		
		// Get URL variables
		$layout_vars = flexicontent_html::getCatViewLayoutVars($model);
		$layout   = $layout_vars['layout'];
		$authorid = $layout_vars['authorid'];
		$tagid    = $layout_vars['tagid'];
		$cids = $layout_vars['cids'];
		$cid  = $layout_vars['cid'];
		
		// Get Tag data if current layout is 'tags'
		if ($tagid) $tag = $this->get('Tag');
		
		$authordescr_item = false;
		if ($authorid && $params->get('authordescr_itemid') && $format != 'feed') {
			$authordescr_itemid = $params->get('authordescr_itemid');
		}
		
		//Set layout
		$this->setLayout('category');
		
		
		// ********************************
		// Load needed JS libs & CSS styles
		// ********************************
		
		JHtml::_('behavior.framework', true);
		flexicontent_html::loadFramework('jQuery');
		flexicontent_html::loadFramework('flexi_tmpl_common');
		
		// Add css files to the document <head> section (also load CSS joomla template override)
		if (!$params->get('disablecss', '')) {
			$document->addStyleSheetVersion($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css', FLEXI_VHASH);
			//$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext {zoom:1;}</style><![endif]-->');
		}
		if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css')) {
			$document->addStyleSheetVersion($this->baseurl.'/templates/'.$app->getTemplate().'/css/flexicontent.css', FLEXI_VHASH);
		}
		
		
		// ********************************************************************************************
		// Create pathway, if automatic pathways is enabled, then path will be cleared before populated
		// ********************************************************************************************
		
		// Get category titles needed by pathway, this will allow Falang to translate them
		$catshelper = new flexicontent_cats($cid);
		$parents    = $catshelper->getParentlist($all_cols=false);
		$rootcat = (int) $params->get('rootcat');
		if ($rootcat) $root_parents = $globalcats[$rootcat]->ancestorsarray;
		
		// Get current pathway
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
		while ( $p < count($parents) )
		{
			// Do not add the directory root category or its parents (this when coming from a directory view)
			if ( !empty($root_parents) && in_array($parents[$p]->id, $root_parents) )  { $p++; continue; }
			
			// Do not add to pathway unroutable categories
			if ( in_array($parents[$p]->id, $globalnoroute) )  { $p++; continue; }
			
			// Add current parent category
			$pathway->addItem( $this->escape($parents[$p]->title), JRoute::_( FlexicontentHelperRoute::getCategoryRoute($parents[$p]->slug) ) );
			$p++;
		}
		//echo "<pre>"; print_r($pathway); echo "</pre>";
		
		
		// *******************************************************************************************************************
		// Bind Fields to items and RENDER their display HTML, but check for document type, due to Joomla issue with system
		// plugins creating JDocument in early events forcing it to be wrong type, when format as url suffix is enabled
		// *******************************************************************************************************************
		
		if ($format != 'feed') {
			$items 	= FlexicontentFields::getFields($items, 'category', $params, $aid);
		}
		
		
		// ************************************************************************
		// Calculate CSS classes needed to add special styling markups to the items
		// ************************************************************************
		
		flexicontent_html::calculateItemMarkups($items, $params);
		
		
		// **********************************************************
		// Calculate a (browser window) page title and a page heading
		// **********************************************************
		
		// Verify menu item points to current FLEXIcontent object
		if ( $menu ) {
			$view_ok     = 'category' == @$menu->query['view'];
			$cid_ok      = $cid       == (int) @$menu->query['cid'];
			$layout_ok   = $layout    == @$menu->query['layout'];   // null is equal to empty string
			$authorid_ok = ($layout!='author') || ($authorid  == (int) @$menu->query['authorid']); // null is equal to zero
			$tagid_ok    = ($layout!='tags')   || ($tagid     == (int) @$menu->query['tagid']); // null is equal to zero
			$menu_matches = $view_ok && $cid_ok && $layout_ok && $authorid_ok && $tagid_ok;
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
			switch($layout) {
				case ''        :  $default_heading = $category->title;  break;
				case 'myitems' :  $default_heading = JText::_('FLEXI_MY_CONTENT');  break;
				case 'author'  :  $default_heading = JText::_('FLEXI_CONTENT_BY_AUTHOR')  .': '. JFactory::getUser($authorid)->get('name');  break;
				case 'tags'    :  $default_heading = JText::_('FLEXI_ITEMS_WITH_TAG') .': '. $tag->name;  break;
				case 'favs'    :  $default_heading = JText::_('FLEXI_YOUR_FAVOURED_ITEMS');  break;
				default        :  $default_heading = JText::_('FLEXI_CONTENT_IN_CATEGORY');
			}
			if ($layout && $cid) { // Non-single category listings, limited to a specific category
				$default_heading .= ', '.JText::_('FLEXI_IN_CATEGORY').': '.$category->title;
			}
			
			// Decide to show page heading (=J1.5 page title) only if a custom layout is used (=not a single category layout)
			$show_default_heading = $layout ? 1 : 0;
			
			// Set both (show_) page_heading / page_title for compatibility of J2.5+ with J1.5 template (and for J1.5 with J2.5 template)
			$params->set('page_title',   $default_heading);
			$params->set('page_heading', $default_heading);
		  $params->set('show_page_heading', $show_default_heading);
			$params->set('show_page_title',   $show_default_heading);
		}
		
		// Prevent showing the page heading if (a) IT IS same as category title and (b) category title is already configured to be shown
		if ( $params->get('show_cat_title', 1) ) {
			if ($params->get('page_heading') == $category->title) $params->set('show_page_heading', 0);
			if ($params->get('page_title')   == $category->title) $params->set('show_page_title',   0);
		}
		
		
		
		// ************************************************************
		// Create the document title, by from page title and other data
		// ************************************************************
		
		// Use the page heading as document title, (already calculated above via 'appropriate' logic ...)
		// or the overriden custom <title> ... set via parameter
		$doc_title  =  empty($meta_params)  ?  $params->get( 'page_title' )  :  $meta_params->get('page_title', $params->get( 'page_title' ));
		
		// Check and prepend or append site name to page title
		if ( $doc_title != $app->getCfg('sitename') ) {
			if ($app->getCfg('sitename_pagetitles', 0) == 1) {
				$doc_title = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $doc_title);
			}
			elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
				$doc_title = JText::sprintf('JPAGETITLE', $doc_title, $app->getCfg('sitename'));
			}
		}
		
		// Finally, set document title
		$document->setTitle($doc_title);
		
		
		// ************************
		// Set document's META tags
		// ************************
		
		// Workaround for Joomla not setting the default value for 'robots', so component must do it
		$app_params = $app->getParams();
		if (($_mp=$app_params->get('robots')))    $document->setMetadata('robots', $_mp);
		
		if ($category->id) {   // possibly not set for author items OR my items
			if ($category->metadesc) $document->setDescription( $category->metadesc );
			if ($category->metakey)  $document->setMetadata('keywords', $category->metakey);
			
			// meta_params are always set if J1.6+ and category id is set
			if ( $meta_params->get('robots') )  $document->setMetadata('robots', $meta_params->get('robots'));
			
			// ?? Deprecated <title> tag is used instead by search engines
			if ($app->getCfg('MetaTitle') == '1') {
				$meta_title = $meta_params->get('page_title') ? $meta_params->get('page_title') : $category->title;
				$document->setMetaData('title', $meta_title);
			}
			
			if ($app->getCfg('MetaAuthor') == '1') {
				if ( $meta_params->get('author') ) {
					$meta_author = $meta_params->get('author');
				} else {
					$table = JUser::getTable();
					$meta_author = $table->load( $category->created_user_id ) ? $table->name : '';
				}
				$document->setMetaData('author', $meta_author);
			}
		}
		
		// Overwrite with menu META data if menu matched
		if ($menu_matches) {
			if (($_mp=$menu->params->get('menu-meta_description')))  $document->setDescription( $_mp );
			if (($_mp=$menu->params->get('menu-meta_keywords')))     $document->setMetadata('keywords', $_mp);
			if (($_mp=$menu->params->get('robots')))                 $document->setMetadata('robots', $_mp);
			if (($_mp=$menu->params->get('secure')))                 $document->setMetadata('secure', $_mp);
		}
		
		
		// *********************************************************************
		// Create category link, but also consider current 'layout', and use the 
		// layout specific variables so that filtering form will work properly
		// *********************************************************************
		
		$non_sef_link = null;
		$category_link = flexicontent_html::createCatLink($category->slug, $non_sef_link, $model);
		
		
		// **********************************************************************************************************
		// Add canonical link (if needed and different than current URL), also preventing Joomla default (SEF plugin)
		// **********************************************************************************************************
		
		if ($params->get('add_canonical'))
		{
			// Create desired REL canonical URL
			$start = JRequest::getInt('start', '');
			$ucanonical = JRoute::_(FlexicontentHelperRoute::getCategoryRoute($category->slug, 0, $layout_vars).($start ? "&start=".$start : ''));
			flexicontent_html::setRelCanonical($ucanonical);
		}
		
		if ($params->get('show_feed_link', 1) == 1) {
			//add alternate feed link
			$link	= $non_sef_link.'&format=feed';
			$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
			$document->addHeadLink(JRoute::_($link.'&type=rss'), 'alternate', 'rel', $attribs);
			$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
			$document->addHeadLink(JRoute::_($link.'&type=atom'), 'alternate', 'rel', $attribs);
		}
		
		
		// *********************
		// Author "profile" item
		// *********************
		
		$authordescr_item_html = false;
		if ($authordescr_item) {
			$flexi_html_helper = new flexicontent_html();
			$authordescr_item_html = $flexi_html_helper->renderItem($authordescr_itemid);
		}
		//echo $authordescr_item_html; exit();
		
		
		// ***************************************************
		// Load template css/js and set template data variable
		// ***************************************************
		
		if ($clayout) {
			// Add the templates css files if availables
			if (isset($themes->category->{$clayout}->css)) {
				foreach ($themes->category->{$clayout}->css as $css) {
					$document->addStyleSheet($this->baseurl.'/'.$css);
				}
			}
			// Add the templates js files if availables
			if (isset($themes->category->{$clayout}->js)) {
				foreach ($themes->category->{$clayout}->js as $js) {
					$document->addScript($this->baseurl.'/'.$js);
				}
			}
			// Set the template var
			$tmpl = $themes->category->{$clayout}->tmplvar;
		} else {
			$tmpl = '.category.default';
		}
		
		// @TODO trigger the plugin selectively
		// and delete the plugins tags if not active
		if ($params->get('trigger_onprepare_content_cat')) // just check if the parameter is active
		{
			JPluginHelper::importPlugin('content');
	
			// Allow to trigger content plugins on category description
			// NOTE: for J2.5, we will trigger the plugins as if description text was an article text, using ... 'com_content.article'
			$category->text = $category->description;
			$results = $dispatcher->trigger('onContentPrepare', array ('com_content.article', &$category, &$params, 0));
			JRequest::setVar('layout', $layout);  // Restore LAYOUT variable should some plugin have modified it
			
			$category->description 	= $category->text;
		}

		// Maybe here not to import all plugins but just those for description field or add a parameter for this
		// Anyway these events are usually not very time consuming as is the the event onPrepareContent(J1.5)/onContentPrepare(J1.6+) 
		JPluginHelper::importPlugin('content');
		
		$noroute_cats = array_flip($globalnoroute);
		
		$type_attribs = flexicontent_db::getTypeAttribs($force=true, $typeid=0);
		$type_params = array();
		foreach ($items as $item)
		{
			$item->event 	= new stdClass();
			
			if ( !isset($type_params[$item->type_id]) )
			{
				$type_params[$item->type_id] = new JRegistry($type_attribs[$item->type_id]);
			}
			$item->params = clone($type_params[$item->type_id]);
			$item->params->merge( new JRegistry($item->attribs) );
			
			//$item->cats = isset($item->cats) ? $item->cats : array();
			
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
			if ( $item_in_category && !isset($noroute_cats[$category->id]) ) {
				// 1. CATEGORY SLUG: CURRENT category
				// Current category IS a category of the item and ALSO routing (creating links) to this category is allowed
				$item->categoryslug = $category->slug;
			} else if ( !isset($noroute_cats[$item->catid]) ) {
				// 2. CATEGORY SLUG: ITEM's MAIN category   (already SET, ... no assignment needed)
				// Since we cannot use current category (above), we will use item's MAIN category 
				// ALSO routing (creating links) to this category is allowed
			} else {
				// 3. CATEGORY SLUG: ANY ITEM's category
				// We will use the first for which routing (creating links) to the category is allowed
				$allcats = array();
				foreach ($item->cats as $cat) {
					if ( !isset($noroute_cats[$cat->id]) ) {
						$item->categoryslug = $globalcats[$cat->id]->slug;
						break;
					}
				}
			}
			
			// Just put item's text (description field) inside property 'text' in case the events modify the given text,
			$item->text = isset($item->fields['text']->display) ? $item->fields['text']->display : '';
			
			// Set the view and option to 'category' and 'com_content'  (actually view is already called category)
			JRequest::setVar('option', 'com_content');
			JRequest::setVar('isflexicontent', 'yes');
			$jinput->set('option', 'com_content');
			$jinput->set('isflexicontent', 'yes');
			
			// These events return text that could be displayed at appropriate positions by our templates
			$item->event = new stdClass();
			
			$results = $dispatcher->trigger('onContentAfterTitle', array('com_content.category', &$item, &$params, 0));
			$item->event->afterDisplayTitle = trim(implode("\n", $results));
	
			$results = $dispatcher->trigger('onContentBeforeDisplay', array('com_content.category', &$item, &$params, 0));
			$item->event->beforeDisplayContent = trim(implode("\n", $results));
	
			$results = $dispatcher->trigger('onContentAfterDisplay', array('com_content.category', &$item, &$params, 0));
			$item->event->afterDisplayContent = trim(implode("\n", $results));
							
			// Set the option back to 'com_flexicontent'
			JRequest::setVar('option', 'com_flexicontent');
			$jinput->set('option', 'com_flexicontent');
			
			// Put text back into the description field, THESE events SHOULD NOT modify the item text, but some plugins may do it anyway... , so we assign text back for compatibility
			$item->fields['text']->display = & $item->text;
			
		}
		
		
		// *****************************************************
		// Remove unroutable categories from sub/peer categories
		// *****************************************************
		
		// sub-cats
		$_categories = array();
		foreach ($categories as $i => $cat) {
			if (in_array($cat->id, $globalnoroute)) continue;
			$_categories[] = $categories[$i];
		}
		$categories = $_categories;
		
		// peer-cats
		$_categories = array();
		foreach ($peercats as $i => $cat) {
			if (in_array($cat->id, $globalnoroute)) continue;
			$_categories[] = $peercats[$i];
		}
		$peercats = $_categories;
		
		
		
		// ************************************
		// Get some variables needed for images
		// ************************************
		
		$joomla_image_path = $app->getCfg('image_path', '');
		$joomla_image_url  = str_replace (DS, '/', $joomla_image_path);
		$joomla_image_path = $joomla_image_path ? $joomla_image_path.DS : '';
		$joomla_image_url  = $joomla_image_url  ? $joomla_image_url.'/' : '';
		$phpThumbURL = $this->baseurl.'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=';
		
		
		
		// **************
		// CATEGORY IMAGE
		// **************
		
		// category image params
		$show_cat_image = $params->get('show_description_image', 0);  // we use different name for variable
		$cat_image_source = $params->get('cat_image_source', 2); // 0: extract, 1: use param, 2: use both
		$cat_link_image = $params->get('cat_link_image', 1);
		$cat_image_method = $params->get('cat_image_method', 1);
		$cat_image_width = $params->get('cat_image_width', 80);
		$cat_image_height = $params->get('cat_image_height', 80);
		$cat_default_image = $params->get('cat_default_image', '');
		
		if ($show_cat_image) {
			$h		= '&amp;h=' . $cat_image_height;
			$w		= '&amp;w=' . $cat_image_width;
			$aoe	= '&amp;aoe=1';
			$q		= '&amp;q=95';
			$ar 	= '&amp;ar=x';
			$zc		= $cat_image_method ? '&amp;zc=' . $cat_image_method : '';
		}
		
		if ($cat_default_image)
		{
			$src = $this->baseurl ."/". $joomla_image_url . $cat_default_image;
			
			$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
			$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
			$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;
			
			$default_image = $phpThumbURL.$src.$conf;
			$default_image = '<img class="fccat_image" style="float:'.$cat_image_float.'" src="'.$default_image.'" alt="%s" title="%s"/>';
		} else {
			$default_image = '';
		}
		
		
		// Create category image/description/etc data 
		$cat = $category;
		$image = "";
		if ($cat) {
			if ($cat->id && $show_cat_image) {
				$cat->image = $params->get('image');
				$cat->introtext = & $cat->description;
				$cat->fulltext = "";
				
				if ( $cat_image_source && $cat->image && JFile::exists( JPATH_SITE .DS. $joomla_image_path . $cat->image ) ) {
					$src = $this->baseurl ."/". $joomla_image_url . $cat->image;
					
					$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
					$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
					$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;
					
					$image = $phpThumbURL.$src.$conf;
				} else if ( $cat_image_source!=1 && $src = flexicontent_html::extractimagesrc($cat) ) {
					
					$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
					$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
					$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;
					
					$base_url = (!preg_match("#^http|^https|^ftp|^/#i", $src)) ?  $this->baseurl.'/' : '';
					$src = $base_url.$src;
					
					$image = $phpThumbURL.$src.$conf;
				}
				$cat->image_src = @$src;  // Also add image category URL for developers
				
				if ($image) {
					$image = '<img class="fccat_image" src="'.$image.'" alt="'.$this->escape($cat->title).'" title="'.$this->escape($cat->title).'"/>';
				} else if ($default_image){
					$image = sprintf($default_image, $cat->title, $cat->title);
				}
				if ($cat_link_image && $image) {
					$image = '<a href="'.JRoute::_( FlexicontentHelperRoute::getCategoryRoute($cat->slug) ).'">'.$image.'</a>';
				}
			}
			$cat->image = $image;
		}
		
		
		// ******************************
		// SUBCATEGORIES (some templates)
		// ******************************
		
		// sub-category image params
		$show_cat_image = $params->get('show_description_image_subcat', 1);  // we use different name for variable
		$cat_image_source = $params->get('subcat_image_source', 2); // 0: extract, 1: use param, 2: use both
		$cat_link_image = $params->get('subcat_link_image', 1);
		$cat_image_method = $params->get('subcat_image_method', 1);
		$cat_image_width = $params->get('subcat_image_width', 24);
		$cat_image_height = $params->get('subcat_image_height', 24);
		$cat_default_image = $params->get('subcat_default_image', '');
		
		if ($show_cat_image) {
			$h		= '&amp;h=' . $cat_image_height;
			$w		= '&amp;w=' . $cat_image_width;
			$aoe	= '&amp;aoe=1';
			$q		= '&amp;q=95';
			$ar 	= '&amp;ar=x';
			$zc		= $cat_image_method ? '&amp;zc=' . $cat_image_method : '';
		}
		
		if ($cat_default_image)
		{
			$src = $this->baseurl ."/". $joomla_image_url . $cat_default_image;
			
			$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
			$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
			$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;
			
			$default_image = $phpThumbURL.$src.$conf;
			$default_image = '<img class="fccat_image" style="float:'.$cat_image_float.'" src="'.$default_image.'" alt="%s" title="%s"/>';
		} else {
			$default_image = '';
		}
		
		
		// Create sub-category image/description/etc data 
		foreach ($categories as $cat) {
			$image = "";
			if ($show_cat_image)  {
				if (!is_object($cat->params)) {
					$cat->params = new JRegistry($cat->params);
				}
				
				$cat->image = $cat->params->get('image');
				$cat->introtext = & $cat->description;
				$cat->fulltext = "";
				
				if ( $cat_image_source && $cat->image && JFile::exists( JPATH_SITE .DS. $joomla_image_path . $cat->image ) ) {
					$src = $this->baseurl ."/". $joomla_image_url . $cat->image;
					
					$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
					$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
					$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;
					
					$image = $phpThumbURL.$src.$conf;
				} else if ( $cat_image_source!=1 && $src = flexicontent_html::extractimagesrc($cat) ) {
					
					$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
					$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
					$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;
					
					$base_url = (!preg_match("#^http|^https|^ftp|^/#i", $src)) ?  $this->baseurl.'/' : '';
					$src = $base_url.$src;
					
					$image = $phpThumbURL.$src.$conf;
				}
				$cat->image_src = @$src;  // Also add image category URL for developers
				
				if ($image) {
					$image = '<img class="fccat_image" src="'.$image.'" alt="'.$this->escape($cat->title).'" title="'.$this->escape($cat->title).'"/>';
				} else if ($default_image){
					$image = sprintf($default_image, $cat->title, $cat->title);
				}
				if ($cat_link_image && $image) {
					$image = '<a href="'.JRoute::_( FlexicontentHelperRoute::getCategoryRoute($cat->slug) ).'">'.$image.'</a>';
				}
			}
			$cat->image = $image;
		}
		
		
		
		// *******************************
		// PEERCATEGORIES (some templates)
		// *******************************
		
		// peer-category image params
		$show_cat_image = $params->get('show_description_image_peercat', 1);  // we use different name for variable
		$cat_image_source = $params->get('peercat_image_source', 2); // 0: extract, 1: use param, 2: use both
		$cat_link_image = $params->get('peercat_link_image', 1);
		$cat_image_method = $params->get('peercat_image_method', 1);
		$cat_image_width = $params->get('peercat_image_width', 24);
		$cat_image_height = $params->get('peercat_image_height', 24);
		$cat_default_image = $params->get('peercat_default_image', '');
		
		if ($show_cat_image) {
			$h		= '&amp;h=' . $cat_image_height;
			$w		= '&amp;w=' . $cat_image_width;
			$aoe	= '&amp;aoe=1';
			$q		= '&amp;q=95';
			$ar 	= '&amp;ar=x';
			$zc		= $cat_image_method ? '&amp;zc=' . $cat_image_method : '';
		}
		
		if ($cat_default_image)
		{
			$src = $this->baseurl ."/". $joomla_image_url . $cat_default_image;
			
			$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
			$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
			$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;
			
			$default_image = $phpThumbURL.$src.$conf;
			$default_image = '<img class="fccat_image" style="float:'.$cat_image_float.'" src="'.$default_image.'" alt="%s" title="%s"/>';
		} else {
			$default_image = '';
		}
		
		// Create peer-category image/description/etc data 
		foreach ($peercats as $cat) {
			$image = "";
			if ($show_cat_image)  {
				if (!is_object($cat->params)) {
					$cat->params = new JRegistry($cat->params);
				}
				
				$cat->image = $cat->params->get('image');
				$cat->introtext = & $cat->description;
				$cat->fulltext = "";
				
				if ( $cat_image_source && $cat->image && JFile::exists( JPATH_SITE .DS. $joomla_image_path . $cat->image ) ) {
					$src = $this->baseurl ."/". $joomla_image_url . $cat->image;
					
					$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
					$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
					$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;
					
					$image = $phpThumbURL.$src.$conf;
				} else if ( $cat_image_source!=1 && $src = flexicontent_html::extractimagesrc($cat) ) {
					
					$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
					$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
					$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;
					
					$base_url = (!preg_match("#^http|^https|^ftp|^/#i", $src)) ?  $this->baseurl.'/' : '';
					$src = $base_url.$src;
					
					$image = $phpThumbURL.$src.$conf;
				}
				$cat->image_src = @$src;  // Also add image category URL for developers
				
				if ($image) {
					$image = '<img class="fccat_image" src="'.$image.'" alt="'.$this->escape($cat->title).'" title="'.$this->escape($cat->title).'"/>';
				} else if ($default_image){
					$image = sprintf($default_image, $cat->title, $cat->title);
				}
				if ($cat_link_image && $image) {
					$image = '<a href="'.JRoute::_( FlexicontentHelperRoute::getCategoryRoute($cat->slug) ).'">'.$image.'</a>';
				}
			}
			$cat->image = $image;
		}
		
		
		
		// remove previous alpha index filter
		//$uri->delVar('letter');
		
		// remove filter variables (includes search box and sort order)
		preg_match_all('/filter[^=]*/', $uri->toString(), $matches);
		foreach($matches[0] as $match)
		{
			//$uri->delVar($match);
		}
		
		// Build Lists
		$lists = array();
		
		//ordering
		$lists['filter_order']     = JRequest::getCmd('filter_order', 'i.title', 'default');
		$lists['filter_order_Dir'] = JRequest::getCmd('filter_order_Dir', 'ASC', 'default');
		$lists['filter']           = JRequest::getString('filter', '', 'default');
		
		// Add html to filter objects
		$form_name = 'adminForm';
		if ($filters) {
			FlexicontentFields::renderFilters( $params, $filters, $form_name );
		}
		
		
		// ****************************
		// Create the pagination object
		// ****************************
		
		$pageNav = $this->get('pagination');
		
		// URL-encode filter values
		$_revert = array('%21'=>'!', '%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')');
		foreach($_GET as $i => $v) {
			if (substr($i, 0, 6) === "filter") {
				if (is_array($v)) {
					foreach($v as $ii => &$vv) {
						$vv = str_replace('&', '__amp__', $vv);
						$vv = strtr(rawurlencode($vv), $_revert);
						$pageNav->setAdditionalUrlParam($i.'['.$ii.']', $vv);
					}
					unset($vv);
				} else {
					$v = str_replace('&', '__amp__', $v);
					$v = strtr(rawurlencode($v), $_revert);
					$pageNav->setAdditionalUrlParam($i, $v);
				}
			}
		}
		$resultsCounter = $pageNav->getResultsCounter();  // for overriding model's result counter
		
		$_sh404sef = defined('SH404SEF_IS_RUNNING') && JFactory::getConfig()->get('sef');
		if ($_sh404sef) $pageNav->setAdditionalUrlParam('limit', $model->getState('limit'));
		
		
		// **********************************************************************
		// Print link ... must include layout and current filtering url vars, etc
		// **********************************************************************
		
		$curr_url   = str_replace('&', '&amp;', $_SERVER['REQUEST_URI']);
		$print_link = $curr_url .(strstr($curr_url, '?') ? '&amp;'  : '?').'pop=1&amp;tmpl=component&amp;print=1';
		$pageclass_sfx = htmlspecialchars($params->get('pageclass_sfx'));
		
		$this->assignRef('layout_vars',$layout_vars);
		$this->assignRef('action',     $category_link);
		$this->assignRef('print_link', $print_link);
		$this->assignRef('category',   $category);
		$this->assignRef('categories',  $categories);
		$this->assignRef('peercats',   $peercats);
		$this->assignRef('items',      $items);
		$this->assignRef('authordescr_item_html', $authordescr_item_html);
		$this->assignRef('lists',      $lists);
		$this->assignRef('params',     $params);
		$this->assignRef('pageNav',    $pageNav);
		$this->assignRef('pageclass_sfx', $pageclass_sfx);
		
		$this->assignRef('pagination',    $pageNav);  // compatibility Alias for old templates
		$this->assignRef('resultsCounter',$resultsCounter);  // for overriding model's result counter
		$this->assignRef('limitstart',    $limitstart); // compatibility shortcut
		
		$this->assignRef('filters',   $filters);
		$this->assignRef('comments',  $comments);
		$this->assignRef('alpha',     $alpha);
		$this->assignRef('tmpl',      $tmpl);
		
		
		// NOTE: Moved decision of layout into the model, function decideLayout() layout variable should never be empty
		// It will consider things like: template exists, is allowed, client is mobile, current frontend user override, etc
		
		// !!! The following method of loading layouts, is Joomla legacy view loading of layouts
		// TODO: EXAMINE IF NEEDED to re-use these layouts, and use JLayout ??
		
		// Despite layout variable not being empty, there may be missing some sub-layout files,
		// e.g. category_somefilename.php for this reason we will use a fallback layout that surely has these files
		$fallback_layout = $params->get('category_fallback_layout', 'blog');  // parameter does not exist yet
		if ($clayout != $fallback_layout) {
			$this->addTemplatePath(JPATH_COMPONENT.DS.'templates'.DS.$fallback_layout);
			$this->addTemplatePath(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates'.DS.$fallback_layout);
		}
		
		$this->addTemplatePath(JPATH_COMPONENT.DS.'templates'.DS.$clayout);
		$this->addTemplatePath(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates'.DS.$clayout);
		
		
		// **************************************************
		// increment the hit counter ONLY once per user visit
		// **************************************************
		// MOVED to flexisystem plugin due to ...
		
		$print_logging_info = $params->get('print_logging_info');
		if ( $print_logging_info ) { global $fc_run_times; $start_microtime = microtime(true); }
		
		parent::display($tpl);
		
		if ( $print_logging_info ) @$fc_run_times['template_render'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	}
}
?>
