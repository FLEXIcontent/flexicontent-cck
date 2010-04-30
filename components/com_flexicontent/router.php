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

function FLEXIcontentBuildRoute(&$query)
{
	$segments = array();

	// get a menu item based on Itemid or currently active
	$menu = &JSite::getMenu();
	if (empty($query['Itemid'])) {
		$menuItem = &$menu->getActive();
	} else {
		$menuItem = &$menu->getItem($query['Itemid']);
	}
	$mView	= (empty($menuItem->query['view'])) ? null : $menuItem->query['view'];
	$mCatid	= (empty($menuItem->query['cid'])) 	? null : $menuItem->query['cid'];
	$mId	= (empty($menuItem->query['id'])) 	? null : $menuItem->query['id'];

	if(isset($query['task']))
	{
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
	};

	// are we dealing with a content that is attached to a menu item?
	if (($mView == 'items') and (isset($query['id'])) and ($mId == intval($query['id']))) {
		unset($query['view']);
		unset($query['cid']);
		unset($query['id']);
	}

	if(isset($query['view']))
	{
		$view = $query['view'];
		if(empty($query['Itemid'])) {
			$segments[] = $query['view'];
		}
		
		if($query['view'] == 'tags') {
			$segments[] = 'tag';
			if(isset($query['id'])) {
				$segments[] = $query['id'];
				unset($query['id']);
			}
			unset($query['view']);
			return $segments;
		}
		
		if($query['view'] == 'favourites') {
			$segments[] = $query['view'];
		}
		if($query['view'] == 'fileselement') {
			$segments[] = $query['view'];
		}
		unset($query['view']);
	};

	if (isset($query['cid'])) {
		// if we are routing an article or category where the category id matches the menu catid, don't include the category segment
		if (($view == 'category') and ($mView == 'category') and ($mCatid != intval($query['cid']))) {
			$segments[] = $query['cid'];
		} else if (($view == 'category') and ($mView == 'flexicontent')) {
			$segments[] = $query['cid'];
		}
		unset($query['cid']);
	};


	if(isset($query['id']))
	{
		$segments[] = 'item';
		$segments[] = $query['id'];
		unset($query['id']);
	};

	return $segments;
}

function FLEXIcontentParseRoute($segments)
{
	$vars = array();

	//Get the active menu item
	$menu =& JSite::getMenu();
	$item =& $menu->getActive();

	// Count route segments
	$count = count($segments);

	if($segments[0] == 'tag') {
		$vars['view'] = 'tags';
		$vars['id'] = $segments[$count-1];
		return $vars;
	}
	
	if($segments[0] == 'favourites') {
		$vars['view'] = 'favourites';
		return $vars;
	}

	if($segments[0] == 'download') {
		$vars['task'] 	= 'download';
		$vars['id'] 	= $segments[1];
		$vars['cid']	= $segments[2];
		$vars['fid'] 	= $segments[3];
		return $vars;
	}

	if($segments[0] == 'weblink') {
		$vars['task'] 	= 'weblink';
		$vars['fid'] 	= $segments[1];
		$vars['cid']	= $segments[2];
		$vars['ord'] 	= $segments[3];
		return $vars;
	}
	
	if($segments[0] == 'fileselement') {
		$vars['view'] 	= 'fileselement';
		return $vars;
	}
	
	if($count == 0) {
		$vars['view'] 	= 'flexicontent';
		return $vars;
	}

	if($count == 1) {
		$vars['view'] 	= 'category';
		$vars['cid'] 	= $segments[$count-1];
		return $vars;
		}

	if($count == 2) {
		$vars['view'] 	= 'items';
		$vars['cid'] 	= $segments[$count-2];
		$vars['id'] 	= $segments[$count-1];
		return $vars;
	}
	
	if($count == 3) {
		$vars['view'] 	= $segments[0];
		//$vars['cid'] 	= $segments[$count-2];
		$vars['id'] 	= $segments[2];
		return $vars;
	}
}
?>