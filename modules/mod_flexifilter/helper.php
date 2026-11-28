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

class modFlexifilterHelper
{

	public static function decideCats(& $params)
	{
		global $globalcats;
		
		$display_cat_list = (int) $params->get('display_cat_list', 0);
		$catids = $params->get('catids', array());
		$usesubcats = (int) $params->get('usesubcats', 0);

		// Get user's allowed categories
		if ($display_cat_list)
		{
			$tree = flexicontent_cats::getCategoriesTree();

			// (only) For include mode, use all categories if no cats were selected via configuration
			if ($display_cat_list === 1 && empty($catids))
			{
				global $globalcats;
				$catids = array_keys($globalcats);
			}
		}

		// Find descendants of the categories
		if ($usesubcats)
		{
			$subcats = array();

			foreach ($catids as $catid)
			{
				$subcats = array_merge($subcats, array_map('trim', explode(',', $globalcats[$catid]->descendants)) );
			}

			$catids = array_unique($subcats);
		}
		
		switch ($display_cat_list)
		{
			// Include method
			case 1:
				$allowedtree = array();

				foreach ($catids as $catid)
				{
					$allowedtree[$catid] = $tree[$catid];
				}
				break;

			// Exclude method
			case 2:
				foreach ($catids as $catid)
				{
					unset($tree[$catid]);
				}
				$allowedtree = & $tree;
				break;

			// Not using category selector
			case 0:
			default:
				$allowedtree = array();
				break;
		}

		// The selector shows titles and description excerpts: skip categories whose access level the visitor lacks,
		// unless the listing of unauthorised content is enabled
		if ($allowedtree && !(int) \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent')->get('show_noauth', 0))
		{
			$user   = \Joomla\CMS\Factory::getApplication()->getIdentity();
			$levels = array_flip(array_map('intval', \Joomla\CMS\Access\Access::getAuthorisedViewLevels($user ? (int) $user->id : 0)));
			$viewable = array();

			foreach ($allowedtree as $catid => $cat)
			{
				if (!is_object($cat) || !isset($cat->access) || isset($levels[(int) $cat->access]))
				{
					$viewable[$catid] = $cat;
				}
			}

			return $viewable;
		}

		return $allowedtree;
	}
}