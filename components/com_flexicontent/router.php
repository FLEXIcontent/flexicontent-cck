<?php
/**
 * @version 1.5 stable $Id$
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
	
	$mview = (empty($menu->query['view'])) ? null : $menu->query['view'];
	$mcid  = (empty($menu->query['cid']))  ? null : $menu->query['cid'];
	$mid   = (empty($menu->query['id']))   ? null : $menu->query['id'];
	
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
	$view = isset($query['view']) ? $query['view'] : '';
	switch ($view) {	
	case FLEXI_ITEMVIEW:
		if ( isset($query['cid']) && ($mcid != (int)$query['cid']  ||  $mview != 'category') )	{  // cid EXISTs and doesnot much cid variable of current menu item
			// IMPLY view = FLEXI_ITEMVIEW when count($segments) == 2
			$segments[] = $query['cid'];
			$segments[] = @$query['id'];  // Required ... 
			unset($query['cid']);
			unset($query['id']);
		} else {
			// EXPLICIT view ('item' be contained in the url), but only if configured to add 'item'
			if ($add_item_sef_segment) {
				$segments[] = 'item';
				unset($query['view']);
			}
			$segments[] = @$query['id'];  // Required ...
			unset($query['cid']);
			unset($query['id']);
		}
		break;
	
	case 'category':
		if ( $mview != 'category' || ($mview == 'category' && !$add_item_sef_segment) ) {
			// Adding explicit view /item/ is disabled for item URLs, and current menu item is category and so is this URL,
			// so ... we explicitly declare than URL is a category URL, otherwise it will be wrongly interpreted as 'item' URL
			$segments[] = 'category';
			unset($query['view']);
		}
		// IMPLY view = 'category' when count($segments) == 1
		if ( (int)@$query['cid']!=$mcid || $mview!='category' ) $segments[] = @$query['cid'];  // Required ...
		unset($query['cid']);
		break;
	
	case 'tag':
		// EXPLICIT view (will be contained in the url)
		$segments[] = 'tag';
		$segments[] = @$query['id'];  // Required ...
		unset($query['id']);
		break;
	
	case 'flexicontent':    // (aka directory view)
		if (isset($query['rootcat'])) {
			$segments[] = 'catalog';
			$segments[] = $query['rootcat'];
			unset($query['rootcat']);
		} else {
			// IMPLY view = 'flexicontent' when count($segments) == 0
		}
		break;
	
	case 'favourites': case 'fileselement': case 'search': default:
		// EXPLICIT view (will be contained in the url)
		if($view!='') $segments[] = $view;
		if (isset($query['id'])) {
			// COMPATIBILITY with old code of router.php ...
			$segments[] = 'id';
			$segments[] = $query['id'];
			unset($query['id']);
		}
		// We leave remaining $query variables untouched, so that these variables will be inserted in the SEF URL as /index/value/ ...
		break;
	}
	
	// Unset view if it matches the given menu item, and was not already unset
	if ( isset($query['view']) && $mview==$query['view']) {
		unset($query['view']);
	}
	
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
	
	// 1. Get the active menu item
	$menu = JFactory::getApplication()->getMenu()->getActive();

	// 2. Count route segments
	$count = count($segments);
	
	// 3. TASKs (Explicitly provided)
	
	// 3.a 'download' task
	if($segments[0] == 'download') {
		$vars['task'] 	= 'download';
		$vars['id'] 	= @$segments[1];
		$vars['cid']	= @$segments[2];
		$vars['fid'] 	= @$segments[3];
		return $vars;
	}

	// 3.b 'weblink' task
	if($segments[0] == 'weblink') {
		$vars['task'] 	= 'weblink';
		$vars['fid'] 	= @$segments[1];
		$vars['cid']	= @$segments[2];
		$vars['ord'] 	= @$segments[3];
		return $vars;
	}
	
	// 3.c 'weblink' task
	if($segments[0] == 'itemelement') {
		$vars['view'] = 'itemelement';
		return $vars;
	}
	
	// 4. *** Cases that VIEW is provided (expicitly given) ***
	
	// 4.a 'item(s)' view
	if($segments[0] == 'item' || $segments[0] == 'items') {
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
	
	// 4.b 'category' view
	if($segments[0] == 'category') {
		$vars['view'] 	= 'category';
		$vars['cid'] 	= @ $segments[1];  // it is optional, some category view layouts do not use category id
		return $vars;
	}
	
	// 4.c 'tags' view
	if($segments[0] == 'tag' || $segments[0] == 'tags') {
		$vars['view'] = 'tags';
		$vars['id'] = $segments[2];
		return $vars;
	}
	
	// 4.d 'search' view
	if($segments[0] == 'search') {
		$vars['view'] = 'search';
		return $vars;
	}
	
	// 4.e 'flexicontent' view (aka directory view)
	if($segments[0] == 'catalog') {
		$vars['view'] = 'flexicontent';
		$vars['rootcat'] = $segments[1];
		return $vars;
	}
	
	// 4.f 'favourites' & 'fileselement' view
	if($segments[0] == 'favourites' || $segments[0] == 'fileselement') {
		$vars['view'] = $segments[0];
		return $vars;
	}
	
	// 5. *** Cases that VIEW is not provided (implied by length of segments) ***
	
	// 5.a Segments Length 0 is 'flexicontent' view (aka directory view)
	if($count == 0) {
		$vars['view'] = 'flexicontent';
		return $vars;
	}

	// 5.b Segments Length 1 is 'category' view, unless current menu item is a category menu item, and adding /item/ is not enabled
	// ... BUT detect bad 2-segments URL (Segments must be integer prefixed)
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

	// 5.c Segments Length 2 is 'item(s)' view
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
	
	// 6. *** OTHER explicit view, when Segments Length GREATER that 2 ***
	if($count > 2) { // COMPATIBILITY with old code of router.php
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
		// Compatibility with old bookmaked SEF URLs ??? ...
		$vars['id']   = $segments[2];
		return $vars;
	}
}
?>
