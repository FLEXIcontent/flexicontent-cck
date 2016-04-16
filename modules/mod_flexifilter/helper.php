<?php
/**
 * @version 1.2 $Id: helper.php 1536 2012-11-03 09:08:46Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent Filter Module
 * @copyright (C) 2012 George Papadakis - www.flexicontent.org
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
defined('_JEXEC') or die('Restricted access');

if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once (JPATH_SITE.DS.'administrator'.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');
//require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');
//JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
//require_once (JPATH_SITE.DS.'modules'.DS.'mod_flexicontent'.DS.'classes'.DS.'datetime.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.fields.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.categories.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'category.php');


class modFlexifilterHelper
{

	public static function decideCats( &$params )
	{
		global $globalcats;
		
		$display_cat_list = $params->get('display_cat_list', 0);
		$catids = $params->get('catids', array());
		$usesubcats = $params->get('usesubcats', 0 );
		
		// Find descendants of the categories
		if ($usesubcats)
		{
			$subcats = array();
			foreach ($catids as $catid) {
				$subcats = array_merge($subcats, array_map('trim',explode(",",$globalcats[$catid]->descendants)) );
			}
			$catids = array_unique($subcats);
		}
    
    
		// Find categories to display
		$tree = flexicontent_cats::getCategoriesTree();
		
		if ( $display_cat_list == 1 )  // include method
		{
			foreach ($catids as $catid)  $allowedtree[$catid] = $tree[$catid];
		}
		
		else if ( $display_cat_list == 2 )  // exclude method
		{
			foreach ($catids as $catid)  unset($tree[$catid]);
			$allowedtree = & $tree;
		}
		
		else
		{
			$allowedtree = & $tree;
		}
		
		return $allowedtree;
	}
	
}
?>