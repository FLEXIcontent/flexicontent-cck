<?php
/**
 * @version 1.5 stable $Id: router.php 1147 2012-02-22 08:24:48Z ggppdk $
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

	// 1. Get a menu item based on Itemid or currently active
	$menu = &JSite::getMenu();
	if (empty($query['Itemid'])) {
		//  USE current Active ID it is now handled in route.php and also add a global config option whether to enable this
		//$menuItem = &$menu->getActive();
		//$query['Itemid'] = @$menuItem->id;
	} else {
		$menuItem = &$menu->getItem($query['Itemid']);
	}
	
	// 2. Try to match the variables against the variables of the menuItem
	$menuItem_matches = true;
	foreach($query as $index => $value) {
		if (!isset($menuItem->query[$index])) {
			$menuItem_matches = false;
			break;
		}
		if ($query[$index] != $menuItem->query[$index]) {
			$menuItem_matches = false;
			break;
		}
		$query_backup[$index] = $value;
	}
	if ($menuItem_matches) {
		foreach($query as $index => $value) unset($query[$index]);
		return $segments;  // $segments is empty we will get variables from menuItem (it has them)
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
	/*case 'search':
		//TODO something if needed
		break;*/
	case FLEXI_ITEMVIEW:
		if (isset($query['cid']))	{
			// IMPLY view = FLEXI_ITEMVIEW when count($segments) == 2
			$segments[] = $query['cid'];
			$segments[] = @$query['id'];  // Required ... 
			unset($query['cid']);
			unset($query['id']);
		} else {
			// EXPLICIT view (will be contained in the url)
			$segments[] = 'item';
			$segments[] = @$query['id'];  // Required ...
			unset($query['id']);
		}
		break;
	case 'category':
		// IMPLY view = 'category' when count($segments) == 1
		$segments[] = @$query['cid'];  // Required ...
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
		if($view!=='') $segments[] = $view;
		if (isset($query['id'])) {
			// COMPATIBILITY with old code of router.php ...
			$segments[] = 'id';
			$segments[] = $query['id'];
			unset($query['id']);
		}
		// We leave $query array untouched, so that its variables will be inserted in the SEF URL as /index/value/ ...
		break;
	}
	unset($query['view']);
	return $segments;
}

/**
 * Construct a proper URL request from the SEF url, we try to reverse what FLEXIcontentBuildRoute() DID
 */
function FLEXIcontentParseRoute($segments)
{
	$vars = array();

	// 1. Get the active menu item
	$menu =& JSite::getMenu();
	$item =& $menu->getActive();

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
	
	// 4. *** Cases that VIEW is provided (expicitly given) ***
	
	// 4.a 'item(s)' view
	if($segments[0] == 'item' || $segments[0] == 'items') {
		$vars['view'] = FLEXI_ITEMVIEW;
		if ($count==2) {  // no cid provided
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
		$vars['cid'] 	= $segments[1];
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

	// 5.b Segments Length 1 is 'category' view
	// OR A --MENU ITEM LINK-- (NOTE:) THIS CODE IS NOT REACHABLE FOR J1.5 ??
	if($count == 1) {
		$value = (int)$segments[0];
		if( $value != 0 ) {
			$vars['view'] = 'category';
			$vars['cid'] = $value;
		} else {
			// Nothing MORE to do this is a menu item link, so joomla should
			// get the menu itemid from the menu alias ... and do the rest
		}
		return $vars;
	}

	// 5.c Segments Length 2 is 'item(s)' view
	if($count == 2) {
		$vars['view'] = FLEXI_ITEMVIEW;
		$vars['cid'] 	= $segments[0];
		$vars['id'] 	= $segments[1];
		return $vars;
	}
	
	// 6. *** OTHER explicit view, when Segments Length GREATER that 2 ***
	if($count > 2) { // COMPATIBILITY with old code of router.php
		$vars['view'] 	= $segments[0];
		// $segments[1] is id with new code, with old bookmaked SEF URLs is ??? ...
		$vars['id'] 	= $segments[2];
		return $vars;
	}
}
?>
