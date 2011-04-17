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

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

// Component Helper
jimport('joomla.application.component.helper');

/**
 * FLEXIcontent Component Route Helper
 *
 * @static
 * @package		Joomla
 * @subpackage	FLEXIcontent
 * @since 1.5
 */
class FlexicontentHelperRoute
{
	/**
	 * @param	int	The route of the item
	 */
	function getItemRoute($id, $catid = 0, $Itemid = 0)
	{
		$needles = array(
			'items'  => (int) $id,
			'category' => (int) $catid
		);

		//Create the link
		$link = 'index.php?option=com_flexicontent&view=items';

		if($catid) {
			$link .= '&cid='.$catid;
		}

		$link .= '&id='. $id;

		if($Itemid) {
			$link .= '&Itemid='.$Itemid;
		}elseif($item = FlexicontentHelperRoute::_findItem($needles)) {
			$link .= '&Itemid='.$item->id;
		}

		return $link;
	}

	function getCategoryRoute($catid, $Itemid = 0) {
		$needles = array(
			'category' => (int) $catid
		);

		//Create the link
		$link = 'index.php?option=com_flexicontent&view=category&cid='.$catid;

		if($Itemid) {
			$link .= '&Itemid='.$Itemid;
		} elseif($item = FlexicontentHelperRoute::_findCategory($needles)) {
			$link .= '&Itemid='.$item->id;
		}

		return $link;
	}
	
	function getTagRoute($id, $Itemid = 0)
	{
		$needles = array(
			'tags' => (int) $id
		);

		//Create the link
		$link = 'index.php?option=com_flexicontent&view=tags&id='.$id;

		if($Itemid) {
			$link .= '&Itemid='.$Itemid;
		} elseif($item = FlexicontentHelperRoute::_findTag($needles)) {
			$link .= '&Itemid='.$item->id;
		}

		return $link;
	}

	function _findItem($needles)
	{
		global $globalitems;
		
		$component =& JComponentHelper::getComponent('com_flexicontent');

		$menus	= &JApplication::getMenu('site', array());
		$items	= $menus->getItems('componentid', $component->id);
		$items = $items?$items:array();
		$match = null;

		foreach($items as $item)
		{								
			if ((@$item->query['view'] == 'items') && (@$item->query['id'] == $needles['items'])) {
				if ((@$item->query['view'] == 'items') && (@$item->query['cid'] == $needles['category'])) {
					$match = $item; // priority 1: item id+cid
					break;
				} else {
					$match = $item; // priority 2: item id
					//break;
				}
			} else if ( @$globalitems && @$item->query['id'] && (@$item->query['view'] == 'items') && (@$globalitems[$needles['category']]->id == @$item->query['id']) ) {
				$match = $item; // priority 3 advanced items routing (requires the system plugin)
				//break;
			} else if ((@$item->query['view'] == 'category') && (@$item->query['cid'] == $needles['category'])) {
				$match = $item; // priority 4 category cid
				//break;
			}
		}

		return $match;
	}

	function _findCategory($needles)
	{
		$component =& JComponentHelper::getComponent('com_flexicontent');

		$menus	= &JApplication::getMenu('site', array());
		$items	= $menus->getItems('componentid', $component->id);
		$items = $items?$items:array();
		$match = null;

		foreach($needles as $needle => $id)
		{
			foreach($items as $item)
			{
				if ( (@$item->query['view'] == $needle) && (@$item->query['cid'] == $id) ) {
					$match = $item;
					break;
				}
			}

			if(isset($match)) {
				break;
			}
		}

		return $match;
	}

	function _findTag($needles)
	{
		$component =& JComponentHelper::getComponent('com_flexicontent');

		$menus	= &JApplication::getMenu('site', array());
		$items	= $menus->getItems('componentid', $component->id);
		$items 	= $items ? $items : array();

		$match = null;

		foreach($needles as $needle => $id)
		{
			foreach($items as $item)
			{
				if ( (@$item->query['view'] == $needle) && (@$item->query['id'] == $id) ) {
					$match = $item;
					break;
				} else if (@$item->query['view'] == $needle) {
					$match = $item;
//					break;
				}
			}

			if(isset($match)) {
				break;
			}
		}

		return $match;
	}
}
?>