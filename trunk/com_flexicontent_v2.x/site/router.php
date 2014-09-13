<?php
/**
 * @version 1.5 stable $Id: router.php 1927 2014-06-27 10:40:12Z enjoyman@gmail.com $
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

/**
 * Create the segments of the SEF URL (segments are SEF URL parts seperated by '/')
 * $query variables that will not be unset will be automatically inserted into the URL
 * as /index/value/
 */
function FLEXIcontentBuildRoute(&$query)
{
	$segments = array();
	
	$app = JFactory::getApplication();
	$params = JComponentHelper::getParams('com_flexicontent');
	$add_item_sef_segment = $params->get('add_item_sef_segment', 1);
	
	// 1. Get a menu item based on Itemid or currently active
	$menus = $app->getMenu();
	if (empty($query['Itemid'])) {
		//  USE current Active ID it is now handled in route.php and also add a global config option whether to enable this
		//$menu = &$menus->getActive();
		//$query['Itemid'] = @$menu->id;
	} else {
		$menu = $menus->getItem($query['Itemid']);
	}
	
	
	// 2. Try to match the variables against the variables of the menu item
	if ( !empty($menu) ) {
		$menuItem_matches = true;
		foreach($query as $index => $value) {
			// Skip URL query variable 'Itemid', since it does not exist PLUS we retrieve the menu item with the giveb Itemid
			if ($index=='Itemid') continue;
			
			// id and cid query variables can be contain 'slug', so we need to typecast them into integer
			$value = in_array($index, array('id','cid')) ? (int)$query[$index] : $query[$index];
			
			// Compare current query variable against the menu query variables
			if ( $value != @$menu->query[$index] ) {
				$menuItem_matches = false;
				break;
			}
		}
		
		// If exact menu match then unset ALL $query array, only the menu item segments will appear
		if ($menuItem_matches) {
			foreach($query as $index => $value) {
				if ( $index!="option" && $index!="Itemid")  // do not unset option and Itemid variables, these are needed
				{
					unset($query[$index]);
				}
			}
			return $segments;  // $segments is empty we will get variables from menuItem (it has them)
		}
	}
	
	
	// 3. Handle known 'task'(s) formulating segments of SEF URL appropriately
	if(isset($query['task']))	{
		if($query['task'] == 'download') {
			$segments[] = $query['task'];
			$segments[] = $query['id'];
			$segments[] = $query['cid'];
			$segments[] = $query['fid'];
			unset($query['task']);	// task
			unset($query['id']);	// file
			unset($query['cid']);	// content
			unset($query['fid']);	// field
		}
		else if($query['task'] == 'weblink') {
			$segments[] = $query['task'];
			$segments[] = $query['fid'];
			$segments[] = $query['cid'];
			$segments[] = $query['ord'];
			unset($query['task']);	// task
			unset($query['fid']);	// field
			unset($query['cid']);	// content
			unset($query['ord']);	// value order
		}
		return $segments;
	}
	
	
	// 4. Handle known views formulating segments of SEF URL appropriately
	$mview = @$menu->query['view'];
	$view  = @$query['view'];
	
	switch ($view) {	
	case FLEXI_ITEMVIEW:
		$mcid = @$menu->query['cid']; // not set returns null
		$cid  = !isset($query['cid']) ? null : (int)$query['cid'];
		if ( $cid && ($mcid != $cid  ||  $mview != 'category') )	{
			// We will make an item URL -with- CID ...
			// cid EXISTs and cid/view URL variables to do not match those in the menu item
			// IMPLY view = FLEXI_ITEMVIEW when count($segments) == 2
			$segments[] = $query['cid'];
		}
		
		else if ($add_item_sef_segment) {
			// We wiil make an item URL -without- CID ...
			// because cid is missing or matched cid/view URL variables matched those in menu item
			// EXPLICIT view ('item' be contained in the url), because according to configuration,
			// the 1-segment implies category view so we need to add /item/ segment (1st segment is view)
			$segments[] = 'item';
		}
		$segments[] = @$query['id'];  // suppress error since it may not be set e.g. for new item form
		unset($query['view']);
		unset($query['cid']);
		unset($query['id']);
		break;
	
	case 'category':
		$mcid = @$menu->query['cid']; // not set returns null
		$cid  = !isset($query['cid']) ? null : (int)$query['cid'];
		if ( $mview!=$view || !$add_item_sef_segment ) {
			// Adding explicit view /item/ is disabled for no-cid item URLs (thus they are 1-segment URLs),
			// or the given menu item is not of category view, so ... we need to explicitly declare
			// that this URL is a category URL, otherwise it will be wrongly interpreted as 'item' URL
			$segments[] = 'category';
		}
		if ( $mview!=$view || $mcid!=$cid) {
			// IMPLY view = 'category' when count($segments) == 1
			$segments[] = @$query['cid'];  // it is optional, some category view layouts do not use category id
		}
		unset($query['view']);
		unset($query['cid']);
		break;
	
	case 'tag':
		// EXPLICIT view (will be contained in the url)
		$segments[] = 'tag';
		$segments[] = @$query['id'];  // Required ...
		unset($query['view']);
		unset($query['id']);
		break;
	
	case 'flexicontent':    // (aka directory view)
		$mrootcat = @$menu->query['rootcat']; // not set returns null
		$rootcat  = !isset($query['rootcat']) ? null : (int)$query['rootcat'];
		if ( $mview!=$view || $mrootcat!=@$query['rootcat']) {
			// view/rootcat URL variables did not match add them as segments (but use 'catalog' as segment for view)
			$segments[] = 'catalog';
			$segments[] = isset($query['rootcat']) ? $query['rootcat'] : (FLEXI_J16GE ? 1:0);
		}
		unset($query['view']);
		unset($query['rootcat']);
		break;
	
	case 'search':
	case 'favourites':
	case 'fileselement':
	default:
		// EXPLICIT view (will be contained in the url)
		if ($view) {
			$segments[] = $view;
		}
		unset($query['view']);
		
		// Set remaining variables "/variable/value/" pairs
		foreach ($query as $variable => $value) {
			if ( $variable!="option" || $variable!="Itemid") continue; // skip 'option' and 'Itemid' variables !
			$segments[] = $variable;
			$segments[] = $value;
			unset($query[$variable]);
		}
		/*if (isset($query['id'])) {
			$segments[] = 'id';
			$segments[] = $query['id'];
			unset($query['id']);
		}*/
		break;
	}
	
	// *******************************************************
	// We leave remaining $query variables untouched,
	// so that these variables will be appended to the SEF URL
	// *******************************************************
	
	return $segments;
}

/**
 * Construct a proper URL request from the SEF url, we try to reverse what FLEXIcontentBuildRoute() DID
 */
function FLEXIcontentParseRoute($segments)
{
	$vars = array();
	
	$params = JComponentHelper::getParams('com_flexicontent');
	$add_item_sef_segment = $params->get('add_item_sef_segment', 1);
	
	// Get the active menu item
	$menu = JFactory::getApplication()->getMenu()->getActive();

	// Count route segments
	$count = count($segments);
	
	
	
	// *****************************************
	// 1. Cases that TASK is explicitly provided
	// *****************************************
	
	// 'download' task
	if ($segments[0] == 'download') {
		$vars['task'] 	= 'download';
		$vars['id'] 	= @$segments[1];
		$vars['cid']	= @$segments[2];
		$vars['fid'] 	= @$segments[3];
		return $vars;
	}

	// 'weblink' task
	if ($segments[0] == 'weblink') {
		$vars['task'] 	= 'weblink';
		$vars['fid'] 	= @$segments[1];
		$vars['cid']	= @$segments[2];
		$vars['ord'] 	= @$segments[3];
		return $vars;
	}
	
	
	
	// *****************************************
	// 2. Cases that VIEW is explicitly provided
	// *****************************************
	
	// 'item' view
	if ($segments[0] == 'item' || $segments[0] == 'items') {
		$vars['view'] = FLEXI_ITEMVIEW;
		if ($count==2) {  // no cid provided
			if (@$menu->query['view']=='category' && @$menu->query['cid']) {
				$vars['cid'] = (int)$menu->query['cid'];
			}
			$vars['id'] = $segments[1];
		} else if ($count==3) {  // also cid provided
			$vars['cid'] = $segments[1];
			$vars['id'] = $segments[2];
		}
		return $vars;
	}
	
	// 'category' view
	if ($segments[0] == 'category') {
		$vars['view'] 	= 'category';
		$vars['cid'] 	= @ $segments[1];  // it is optional, some category view layouts do not use category id
		return $vars;
	}
	
	// 'tags' view
	if ($segments[0] == 'tag' || $segments[0] == 'tags') {
		$vars['view'] = 'tags';
		$vars['id'] = $segments[2];
		return $vars;
	}
	
	// 'flexicontent' view (aka directory view)
	if ($segments[0] == 'catalog') {
		$vars['view'] = 'flexicontent';
		$vars['rootcat'] = $segments[1];
		return $vars;
	}
	
	// VIEWs with no extra variables 
	$view_only = array('favourites'=>1, 'fileselement'=>1, 'itemelement'=>1, 'search'=>1);
	if ( isset($view_only[$segments[0]]) ) {
		$vars['view'] = $segments[0];
		return $vars;
	}
	
	
	
	// ********************************************************************************
	// 3. Cases that VIEW is not provided (instead it is implied by length of segments)
	// ********************************************************************************
	
	// SEGMENT LENGTH 0: is 'flexicontent' view (aka directory view)
	if ($count == 0) {
		$vars['view'] = 'flexicontent';
		return $vars;
	}

	// SEGMENT LENGTH 1: is 'category' view (default), or 'item' view depending on configuration
	// NOTE:
	//  The 1 segment item URLs are possible only if (e.g.) menu item is a matching 'category' menu item,
	//  in this case the category URLs become explicit with /category/ segment after the category menu item
	//  so that they are not 1 segment of length
		// ... BUT detect bad 1-segments URL (Segments must be integer prefixed)
	if ($count == 1) {
		// Check for bad URLs, THIS is needed for bad URLs with invalid MENU ALIAS segments,
		// that are routed to FLEXIcontent because the PARENT menu is FLEXIcontent MENU ITEM
		$element_id_0 = (int) $segments[0];
		if ( !$element_id_0 ) {
			// Force article error page
			$vars['view'] = FLEXI_ITEMVIEW;
			$vars['cid']  = '0';
			$vars['id']   = '0';
			return $vars;
		}
		
		$iscat_menu_item = $menu && @$menu->query['view']=='category' && @$menu->query['cid'];
		if ($iscat_menu_item && !$add_item_sef_segment) {
			$vars['view'] = FLEXI_ITEMVIEW;
			$vars['cid']  = $menu->query['cid'];
			$vars['id']   = $segments[0];
			return $vars;
		} else {
			$vars['view'] = 'category';
			$vars['cid']  = $segments[0];
			return $vars;
		}
	}

	// SEGMENT LENGTH 2: is 'item' view
	// ... BUT detect bad 2-segments URL (Segments must be integer prefixed)
	if ($count == 2) {
		// Check for bad URLs, THIS is needed for bad URLs with invalid MENU ALIAS segments,
		// that are routed to FLEXIcontent because the PARENT menu is FLEXIcontent MENU ITEM
		$element_id_0 = (int) $segments[0];
		$element_id_1 = (int) $segments[1];
		if ( !$element_id_0 || !$element_id_1 ) {
			// Force article error page
			$vars['view'] = FLEXI_ITEMVIEW;
			$vars['cid']  = '0';
			$vars['id']   = '0';
			return $vars;
		}
		
		$vars['view'] = FLEXI_ITEMVIEW;
		$vars['cid'] 	= $segments[0];
		$vars['id'] 	= $segments[1];
		return $vars;
	}
	
	
	// ***********************************************************
	// 6. OTHER explicit view, when Segments Length GREATER that 2
	// ***********************************************************
	
	if ($count > 2) { // COMPATIBILITY with old code of router.php
		$flexi_view_paths = glob( JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'views'.DS.'[a-zA-Z_0-9]*', GLOB_ONLYDIR);
		foreach ($flexi_view_paths as $flexi_view_path) {
			$flexi_view = basename($flexi_view_path);
			if ($flexi_view == '.' || $flexi_view == '..') continue;
			$flexi_views[$flexi_view] = 1;
		}
		
		$explicit_view = $segments[0];
		if ( !isset( $flexi_views[$explicit_view]) ) {
			$msg = JText::sprintf('FLEXI_REQUESTED_CONTENT_OR_VIEW_NOT_FOUND', $explicit_view);
			JError::raiseError(404, $msg);  // Cannot throw exception here since it will not be caught
		}
		
		$vars['view'] = $segments[0];
		
		// Consider remaining segments as "/variable/value/" pairs
		for($i=1; $i<count($segments); $i=$i+2) {
			$vars[ $segments[$i] ] = $segments[$i];
		}
		return $vars;
	}
}
?>
