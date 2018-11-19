<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2017, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

/**
 * FLEXIcontent plugin for sh404SEF
 */

defined('_JEXEC') or die('Direct Access to this location is not allowed.');

if ( !class_exists('sh404_fc_helper') )
{
	class sh404_fc_helper
	{
		function getCats($ids = false)
		{
			$db = JFactory::getDbo();
			$query = 'SELECT id, title, alias'
				. ' FROM #__categories'
				. (empty($ids) ? '' : ' WHERE id IN (' . implode(', ', $ids) . ')');
			$cats = $db->setQuery($query)->loadObjectList('id');
			return $cats;
		}
	}
}

// ------------------ standard plugin initialize function - don't change ---------------------------
global $sh_LANG;
$sefConfig = Sh404sefFactory::getConfig();
$shLangName = '';
$shLangIso = '';
$title = array();
$shItemidString = '';
$dosef = shInitializePlugin($lang, $shLangName, $shLangIso, $option);
if ($dosef == false)
{
	return;
}
// ------------------ standard plugin initialize function - don't change ---------------------------

// ------------------ load language file - adjust as needed ----------------------------------------
$shLangIso = shLoadPluginLanguage('com_flexicontent', $shLangIso, '_SH404SEF_FLEXICONTENT_ADD', JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'sef_ext'.DS.'lang'.DS );
// ------------------ load language file - adjust as needed ----------------------------------------

// Get DB, Application, etc
$database = ShlDbHelper::getDb();
$app = JFactory::getApplication();

// CHECK for if the given URL is the home page, if so we must return an empty string
$shHomePageFlag = false;
$shHomePageFlag = ! $shHomePageFlag ? shIsHomepage($string) : $shHomePageFlag;

if ($shHomePageFlag)
{ // this is homepage (optionally multipaged)
	$title[] = '/';
	$string = sef_404::sefGetLocation(
		$string, $title, null,
		(isset($limit) ? $limit : null),
		(isset($limitstart) ? $limitstart : null),
		(isset($shLangName) ? $shLangName : null),
		(isset($showall) ? $showall : null)    // currently ignored for non com_content components ?
	);
	return;
}


static $FC_sh404sef_init = null;
static $IS_FISH_SITE = null;
static $compName = array();
static $view2seg = array(
	'search'=>'_SH404SEF_FLEXICONTENT_SEARCH',
	'favourites'=>'_SH404SEF_FLEXICONTENT_FAVOURITES',
	'flexicontent'=>'_SH404SEF_FLEXICONTENT_DIRECTORY',
	'item'=>'_SH404SEF_FLEXICONTENT_ITEM'
);
static $ins_ArticleId, $ins_NumericalId, $ins_Date;
static $cats_ArticleId, $cats_NumericalId, $cats_Date;

if (!$FC_sh404sef_init)
{
	$FC_sh404sef_init = true;

	// Include FC constants file
	require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');
	$IS_FISH_SITE = FLEXI_FISH && $app->isSite();

	// Make sure that the global FC vars are arrays
	global $fc_list_items;
	if (!is_array($fc_list_items))    $fc_list_items    = array();
	global $globalcats, $globalnopath, $globalnoroute;
	if (!is_array($globalcats))    $globalcats    = array();
	if (!is_array($globalnopath))  $globalnopath  = array();
	if (!is_array($globalnoroute)) $globalnoroute = array();

	$ins_ArticleId   = $sefConfig->ContentTitleInsertArticleId;
	$ins_NumericalId = $sefConfig->shInsertNumericalId;
	$ins_Date        = $sefConfig->insertDate;

	$cats_ArticleId   = $ins_ArticleId && !empty($sefConfig->shInsertContentArticleIdCatList) ? $sefConfig->shInsertContentArticleIdCatList : array();
	$cats_ArticleId   = !empty($cats_ArticleId) && $cats_ArticleId[0] == '' ? true : array_flip($cats_ArticleId);

	$cats_NumericalId = $ins_NumericalId && !empty($sefConfig->shInsertNumericalIdCatList) ? $sefConfig->shInsertNumericalIdCatList : array();
	$cats_NumericalId = !empty($cats_NumericalId) && $cats_NumericalId[0] == '' ? true : array_flip($cats_NumericalId);

	$cats_Date        = $ins_Date && !empty($sefConfig->insertDateCatList) ? $sefConfig->insertDateCatList : array();
	$cats_Date        = !empty($cats_Date) && $cats_Date[0] == '' ? true : array_flip($cats_Date);

	// Falang installed, do SQL query to allow translating category title
	if (FLEXI_FISH)
	{
		// Get template XML data from cache
		$_cache = JFactory::getCache('com_flexicontent_cats');  // Get Joomla Cache
		//$_cache->setCaching(1); 		              // Force cache ON
		//$_cache->setLifeTime(FLEXI_CACHE_TIME); 	// Set expire time (default is 1 hour)
		$_helper = new sh404_fc_helper();
		$_cats = $_cache->get(
			array($_helper, 'getCats'),
			array(false)
		);
	}

	else
	{
		$_cats = & $globalcats;
	}
}

// Segment to identify COMPONENT in some URLs that might need this, e.g. they need menu item, but a menu item was not found
if ( !isset($compName[$shLangName]) )
{
	$compName[$shLangName] = shGetComponentPrefix($option);
	$compName[$shLangName] = (empty($compName[$shLangName]) || $compName[$shLangName] == '/') ? $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_CONTENT_PAGE'] : $compName[$shLangName];
}

// Avoid PHP not set notices, by setting variables to null, also null will not break isset behaviour
$Itemid   = isset($Itemid)  ? $Itemid : null;
$view     = isset($view)    ? $view   : null;
$layout   = isset($layout)  ? $layout : null;
$task     = isset($task)    ? $task   : null;
$format   = isset($format)  ? $format : null;
//$cid      = isset($cid)     ? (int) $cid : 0;


// Itemid NOT found inside the non-sef URL
$Itemid_exists_in_URL = preg_match('/Itemid=[0-9]+/iu', $string);
if ( !$Itemid_exists_in_URL )
{
  // V 1.2.4.t moved back here
	if ($sefConfig->shInsertGlobalItemidIfNone && ! empty($shCurrentItemid))
	{
		$string .= '&Itemid=' . $shCurrentItemid; // append current Itemid
		$Itemid = $shCurrentItemid;
		shAddToGETVarsList('Itemid', $Itemid); // V 1.2.4.m
	}

	if ($sefConfig->shInsertTitleIfNoItemid)
	{
		$title[] = $sefConfig->shDefaultMenuItemName ?
			$sefConfig->shDefaultMenuItemName :
			getMenuTitle($option, (isset($view) ? $view : null), $shCurrentItemid, null, $shLangName);
	}
	$shItemidString = '';
	if ($sefConfig->shAlwaysInsertItemid && (! empty($Itemid) || ! empty($shCurrentItemid)))
	{
		$shItemidString = JText::_('COM_SH404SEF_ALWAYS_INSERT_ITEMID_PREFIX')
			. $sefConfig->replacement . (empty($Itemid) ? $shCurrentItemid : $Itemid);
	}
}

// Itemid found inside the non-sef URL
else
{
	$shItemidString = $sefConfig->shAlwaysInsertItemid ?
		JText::_('COM_SH404SEF_ALWAYS_INSERT_ITEMID_PREFIX') . $sefConfig->replacement . $Itemid : '';
	if ($sefConfig->shAlwaysInsertMenuTitle)
	{
		// global $Itemid; V 1.2.4.g we want the string option, not current page !
		if ($sefConfig->shDefaultMenuItemName)
			$title[] = $sefConfig->shDefaultMenuItemName; // V 1.2.4.q added
				                                              // force language
		elseif ($menuTitle = getMenuTitle($option, (isset($view) ? $view : null), $Itemid, '', $shLangName))
		{
			if ($menuTitle != '/')
				$title[] = $menuTitle;
		}
	}
}
// V 1.2.4.m
// Remove common URL variables from GET vars list, so that they don't show up as query string in the URL
shRemoveFromGETVarsList('option');
shRemoveFromGETVarsList('lang');
if (! empty($Itemid))   shRemoveFromGETVarsList('Itemid');

// Warning remove the limit variable from URL, ONLY if the variable is not present in the URL (frontend filtering form allows overriding the value)
if (empty($_GET['limit']))
{
	shRemoveFromGETVarsList('limit');
}

// Variables 'limitstart', 'start', 'showall' can be zero or empty string, so use isset
if (isset($limitstart))  shRemoveFromGETVarsList('limitstart');
if (isset($start))       shRemoveFromGETVarsList('start');
//if (isset($showall))     shRemoveFromGETVarsList('showall');  // Old SH404SEF version only use this for com_content, DO NOT unset this
if (empty($showall))     shRemoveFromGETVarsList('showall');  // only unset on zero or zero-length variables

// Preview feature (login via URL (normally disabled for security reasons)), do not add such URLS to SH404SEF URLs !!
if (! empty($fcu) || ! empty($fcp)) return;


// Get Depth of parent category segmenets to use for item view
$item_segs = array(5=>0, 1=>-1, 2=>1, 3=>-2, 4=>2, 0=>999);
$cats_in_itemlnk = (isset($item_segs[$sefConfig->includeContentCat])) ? $item_segs[$sefConfig->includeContentCat] : 999;

// Get Depth of parent category segmenets to use for category view
$cat_segs = array(1=>-1, 2=>1, 3=>-2, 4=>2, 0=>999);
$cats_in_catlnk = (isset($cat_segs[$sefConfig->includeContentCatCategories])) ? $cat_segs[$sefConfig->includeContentCatCategories] : 999;


/**
 * Some FLEXIcontent views may only set task variable ... in this case set task variable as view
 */
if (!$view && $task === 'search')
{
	$view = 'search';
	shRemoveFromGETVarsList('task');
}


/**
 * Do not convert to SEF url non-html URLs, like 'raw' and 'json' URLs
 */
if ($format && strtolower($format) !== 'html')
{
	return;
}



switch ($view)
{
	case 'item' :

		if ( empty($id) || $task == 'add' )  // New item form URL (TASK: add  -or-  empty ID)
		{
			$menu_matches = false;

			if ($Itemid && ($menu = $app->getMenu()->getItem($Itemid)))
			{
				$menu_matches =
					@ $menu->query['view'] == $view
					&& @ $menu->query['task'] == $task
					&& @ $menu->query['layout'] == $layout
					&& @ $menu->query['typeid'] == @ $typeid;
			}

			if ($menu_matches)
			{
				foreach($menu->tree as $mID)
				{
					$menuTitle = $Itemid
						? getMenuTitle($option, (isset($view) ? $view : null), $mID, null, $shLangName)
						: false;
					$title[] = $menuTitle;
				}
			}

			// New item form URL without menu item, add type name to the URL
			elseif (!empty($typeid))
			{
				$title[] = $compName[$shLangName] . '/';
				$title[] = $sh_LANG[$shLangIso][ $view2seg[$view] ] . '/';
				$title[] = ($task === 'add' ? $sh_LANG[$shLangIso][ '_SH404SEF_FLEXICONTENT_ADD' ] : $task) . '/';

				$query 	= 'SELECT id, name FROM #__flexicontent_types WHERE id = ' . (int) $typeid;
				$row = $database->setQuery($query)->loadObject();

				if ($row)
				{
					$title[] = $row->name;
				}
			}

			// Remove the vars from the url
			shRemoveFromGETVarsList('task');
			shRemoveFromGETVarsList('view');

			// Remove some unneed variables from the URL, if they exist in the menu item
			if (!empty($typeid) && $menu && @ $menu->query['typeid'] == $typeid)
			{
				shRemoveFromGETVarsList('typeid');
			}
			if (!empty($layout) && $menu && @ $menu->query['layout'] == $layout)
			{
				shRemoveFromGETVarsList('layout');
			}
		}

		else   // Item viewing  -or-  TASK: edit  -or-  TASK: *
		{
			// Try to use cached item data of current run
			if (isset($fc_list_items[$id]))
			{
				$row = $fc_list_items[ $id ];
			}

			else
			{
				$query	= 'SELECT i.id, i.title, i.alias, i.catid, i.created, ie.type_id' //.', c.title AS cattitle, ty.alias AS typealias'
						. ' FROM #__content AS i'
						. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
						//. ' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
						//. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
						//. ' LEFT JOIN #__categories AS c ON c.id = rel.catid'
						. ' WHERE i.id = ' . ( int ) $id;
				$database->setQuery($query);

				// Do not translate the items url (Falang extended Database class and overrides the method)
				$row = !$IS_FISH_SITE  ?  $database->loadObject()  :  $database->loadObject('stdClass', $_translate=false, $_language=null);
			}

			if ($row)
			{
				// Use item's main category if none is specified in the query string
				$catid = empty($cid) ? $row->catid : $cid;
				$contentTitle = array();
				$title = array();

				if (isset($globalcats[$catid]->ancestorsarray))
				{
					$ancestors = $globalcats[$catid]->ancestorsarray;
					$cat_titles = array();
					foreach ($ancestors as $ancestor)
					{
						if (in_array($ancestor, $globalnoroute)) continue;

						if (isset($_cats[$ancestor]) && ($cat = $_cats[$ancestor]))
						{
							if ($sefConfig->useCatAlias && !isset($cat->alias) && isset($cat->slug))
							{
								list($_cat_id, $cat->alias) = explode(':', $cat->slug);
							}
							$cat_titles[] = ($sefConfig->useCatAlias ? $cat->alias : $cat->title) . '/';
						}
						else
						{
							$cat_titles[] = $ancestor . '/';
						}
					}

					$first_url_cat = ($cats_in_itemlnk >= 0) ? count($cat_titles) - $cats_in_itemlnk : 0;
					$first_url_cat = ($first_url_cat < 0) ? 0 : $first_url_cat;

					$last_url_cat  = ($cats_in_itemlnk >= 0) ? count($cat_titles)-1 : -($cats_in_itemlnk + 1);
					$last_url_cat  = ($last_url_cat > count($cat_titles)-1) ? count($cat_titles)-1 : $last_url_cat;
					for($ccnt = $first_url_cat; $ccnt <= $last_url_cat; $ccnt++ )
					{
						$contentTitle[] = $cat_titles[$ccnt];
					}
				}

				// Create item title as URL segment, using either alias or title
				$row_title  = $sefConfig->UseAlias ? $row->alias : $row->title;

				// Add article id if adding for all categories (cats-list===true) or if item's category id is in cats-list
				if ($cats_ArticleId === true || isset($cats_ArticleId[$row->catid]))
				{
					$contentTitle[] = $ins_ArticleId == 1  ?  $row->id .'-'. $row_title  :  $row_title .'-'. $row->id;
				}
				else
				{
					$contentTitle[] = $row_title;
				}

				// Add numerical ID, if adding for all categories (cats-list===true) or if item's category id is in cats-list
				if ( $cats_NumericalId === true || isset($cats_NumericalId[$row->catid]) )
				{
					$shTemp = explode(' ', $row->created);
					$title[] = str_replace('-', '', $shTemp[0]) . $row->id;
				}

				// Add date segments, if adding for all categories (cats-list===true) or if item's category id is in cats-list
				else if ( $cats_Date === true || isset($cats_Date[$row->catid]) )
				{
					$creationDate = new JDate($row->created);
					$title[] = $creationDate->year;
					$title[] = $creationDate->month;
					$title[] = $creationDate->day;
				}
				$title = array_merge($title, $contentTitle);

				// Remove the vars from the url
				shRemoveFromGETVarsList('id');
				shRemoveFromGETVarsList('cid');
				shRemoveFromGETVarsList('view');

				// We will just let ?task=edit into the URL !, no need to make this SEF segment
				/*if ($task == 'edit')
				{
					$title[] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_EDIT'];
					shRemoveFromGETVarsList('task');
				}*/
			}
		}

		// Remove 'ilayout' if empty
		if (empty($ilayout)) shRemoveFromGETVarsList('ilayout');

		// Create page id
		//shMustCreatePageId('set', true);
	break;


	case 'category' :

		$cid = empty($cid) ? 0 : $cid;
		$menu_matches = false;

		// Check menu item for custom configuration
		if ($Itemid && ($menu = $app->getMenu()->getItem($Itemid)))
		{
			// null is equal to zero and to zero length string, we use == comparison
			$view_ok     = 'category' == (isset($menu->query['view']) ? $menu->query['view'] : '');
			$cid_ok      = (int) $cid == (int) (isset($menu->query['cid']) ? $menu->query['cid'] : 0);
			$layout_ok   = $layout    == (isset($menu->query['layout']) ? $menu->query['layout'] : '');
			$authorid_ok = ($layout !== 'author') || ((int) $authorid  == (int) (isset($menu->query['authorid']) ? $menu->query['authorid'] : 0));
			$tagid_ok    = ($layout !== 'tags')   || ((int) $tagid     == (int) (isset($menu->query['tagid']) ? $menu->query['tagid'] : 0));

			// (a) override is enabled in the menu or (b) category Layout is 'myitems' or 'favs' or 'tags' or 'mcats' which has no default parameters
			$overrideconf = $menu->params->get('override_defaultconf', 0) || in_array($layout, array('myitems', 'favs', 'mcats', 'tags'));
			$menu_matches = $view_ok && $cid_ok && $layout_ok && $authorid_ok && $tagid_ok;

			if ($menu_matches && $layout === 'mcats')
			{
				$cids = !empty($cids) ? $cids : array();
				$cids  = is_array($cids) ? $cids : preg_split("/[\s]*,[\s]*/", $cids);

				$mcids = !empty($menu->query['cids']) ? $menu->query['cids'] : array();
				$mcids = is_array($mcids) ? $mcids : preg_split("/[\s]*,[\s]*/", $mcids);

				$menu_matches = count(array_diff(array_merge($cids, $mcids), array_intersect($cids, $mcids))) === 0;
			}
		}

		/**
		 * Use menu aliases path:
		 * Either if it is a direct menu item match
		 * Or if using MENU ITEM configuration (^1)
		 *
		 * (^1) avoid multiple menu items pointing to the same content showing the same page
		 */
		if ($menu_matches && (!$layout || $overrideconf))
		{
			foreach($menu->tree as $mID)
			{
				$menuTitle = $Itemid
					? getMenuTitle($option, (isset($view) ? $view : null), $mID, null, $shLangName)
					: false;
				$title[] = $menuTitle;
			}

			// Remove the vars from the url
			shRemoveFromGETVarsList('cid');
			shRemoveFromGETVarsList('view');
		}

		/**
		 * Use category structure: Single category URL with or without a specific 'layout'
		 */
		elseif (!empty($cid))
		{
			if (isset($globalcats[$cid]->ancestorsarray))
			{
				$ancestors = $globalcats[$cid]->ancestorsarray;
				$cat_titles = array();

				foreach ($ancestors as $ancestor)
				{
					if (in_array($ancestor, $globalnoroute)) continue;

					// Falang installed, do SQL query to allow translating category title
					if (FLEXI_FISH)
					{
						$query	= 'SELECT id, title, alias FROM #__categories WHERE id = ' . $ancestor;
						$database->setQuery ( $query );
						$row_cat = $database->loadObject();
						if (!$row_cat) { $cat_titles[] = $ancestor; continue; }
						$cat_titles[] = ($sefConfig->useCatAlias ? $row_cat->alias : $row_cat->title) . '/';
					}

					// FALANG not installed, no need for SQL query
					elseif (isset($globalcats[$ancestor]))
					{
						list($_cat_id, $_cat_alias) = explode( ":", $globalcats[$ancestor]->slug );
						$cat_titles[] = ($sefConfig->useCatAlias ? $_cat_alias : $globalcats[$ancestor]->title) . '/';
					}
					else $cat_titles[] = $ancestor . '/';
				}

				$curr_cat_title = count($cat_titles) ? array_pop($cat_titles) : null;

				$first_url_cat = ($cats_in_catlnk >= 0) ? count($cat_titles) - $cats_in_catlnk : 0;
				$first_url_cat = ($first_url_cat < 0) ? 0 : $first_url_cat;

				$last_url_cat  = ($cats_in_catlnk >= 0) ? count($cat_titles)-1 : -($cats_in_catlnk + 1);
				$last_url_cat  = ($last_url_cat > count($cat_titles)-1) ? count($cat_titles)-1 : $last_url_cat;

				for ($ccnt = $first_url_cat; $ccnt <= $last_url_cat; $ccnt++)
				{
					$title[] = $cat_titles[$ccnt];
				}

				if ($curr_cat_title)
				{
					$title[] = $curr_cat_title;
				}
			}
			else
			{
				$title[] = '/';
			}

			// Remove the vars from the url
			shRemoveFromGETVarsList('cid');
			shRemoveFromGETVarsList('view');
		}

		/**
		 * Use component-based name segment (e.g. /content_page/): No category ID
		 */
		else
		{
			$title[] = $compName[$shLangName] . '/';

			// Remove the vars from the url
			shRemoveFromGETVarsList('cid');
		}


		// HANDLE 'tags' layout of category view
		switch ($layout)
		{
			case 'tags':
				if (!$menu_matches)
				{
					$title[] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_TAGGED'] . '/';

					if (!empty($tagid))
					{
						$title[] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_TAGGED'] . '/';

						$query 	= 'SELECT id, name FROM #__flexicontent_tags WHERE id = ' . ( int ) $tagid;
						$database->setQuery ( $query );
						$row = $database->loadObject();

						if ($row)  $title[] = $row->name;
					}
				}

				shRemoveFromGETVarsList('view');
				shRemoveFromGETVarsList('tagid');
				shRemoveFromGETVarsList('layout');
				break;


			// HANDLE 'author' layout of category view
			case 'author':
				if (!$menu_matches)
				{
					$title[] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_AUTHORED'] . '/';

					if (!empty($authorid))
					{
						$query 	= 'SELECT id, name FROM #__users WHERE id = ' . ( int ) $authorid;
						$database->setQuery ( $query );
						$row = $database->loadObject ();

						if ($row)
						{
							$title[] = $row->name;
						}
					}
				}

				shRemoveFromGETVarsList('view');
				shRemoveFromGETVarsList('authorid');
				shRemoveFromGETVarsList('layout');
				break;


			// HANDLE 'myitems' layout of category view
			case 'myitems':
				if (!$menu_matches)
				{
					$title[] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_MYITEMS']. '/';
				}

				shRemoveFromGETVarsList('view');
				shRemoveFromGETVarsList('layout');
				break;


			// HANDLE 'favs' layout of category view
			case 'favs':
				if (!$menu_matches)
				{
					$title[] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_FAVOURED']. '/';
				}

				shRemoveFromGETVarsList('view');
				shRemoveFromGETVarsList('layout');
				break;


			// HANDLE 'mcats' layout of category view
			case 'mcats':
				if (!$menu_matches)
				{
					$title[] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_MCATS']. '/';

					if (!empty($cids))
					{
						$cids = is_array($cids) ? $cids : preg_split("/[\s]*,[\s]*/", $cids);

						foreach ($cids as $mcats_cid)
						{
							if (isset($_cats[$mcats_cid]) && ($cat = $_cats[$mcats_cid]))
							{
								$title[] = ($sefConfig->useCatAlias ? $cat->alias : $cat->title) . '/';
							}
							else
							{
								$title[] = $mcats_cid . '/';
							}
						}
					}
				}

				shRemoveFromGETVarsList('cids');
				shRemoveFromGETVarsList('view');
				shRemoveFromGETVarsList('layout');
				break;


			default:
				// Unhandled layout case
				if ($layout)
				{
					$title[] = 'layout/';
					$title[] = $layout . '/';
				}

				// Empty layout, single category view, nothing more to do here
				else ;

				break;
		}

		// Remove 'clayout' if empty
		if (empty($clayout))
		{
			shRemoveFromGETVarsList('clayout');
		}

		// Create page id
		//shMustCreatePageId('set', true);
	break;


	// LEGACY tags view
	case 'tags' :
		if (!empty($id))
		{
			$query 	= 'SELECT id, name FROM #__flexicontent_tags'
					.' WHERE id = ' . (int) $id;
			$row = $database->setQuery($query)->loadObject();

			if ($row)
			{
				$title[] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_TAGS'] . '/';
				$title[] = $row->name;
			}
		}
		else
		{
			$title[] = '/';
		}

		// Remove the vars from the url
		shRemoveFromGETVarsList('id');
		shRemoveFromGETVarsList('view');

		// Create page id
		//shMustCreatePageId('set', true);
	break;


	// Views that will keep menu structure if meny matches, since these menu contain configuration, (usually these should be top-level menu items)
	case 'search' :
	case 'favourites' :  // LEGACY favourites view
	case 'flexicontent' :
		$menu_matches = false;

		if ($Itemid && ($menu = $app->getMenu()->getItem($Itemid)))
		{
			$menu_matches = $view === (isset($menu->query['view']) ? $menu->query['view'] : '');

			if ($view === 'flexicontent')
			{
				$rootcat = empty($rootcat) ? 0 : $rootcat;
				$menu_matches = $menu_matches && (int) $rootcat === (int) (isset($menu->query['rootcat']) ? $menu->query['rootcat'] : 0);
			}
		}

		// Direct menu item match, create SEF URL using menu-aliases path
		if ($menu_matches)
		{
			foreach($menu->tree as $mID)
			{
				$menuTitle = !$Itemid ? false : getMenuTitle($option, (isset($view) ? $view : null), $mID, null, $shLangName);
				$title[] = $menuTitle;
			}
		}

		else
		{
			$title[] = $compName[$shLangName] . '/';
			$title[] = $sh_LANG[$shLangIso][ $view2seg[$view] ] . '/';

			// Special case for directory view with root category
			if ($view === 'flexicontent' && !empty($rootcat))
			{
				// Falang installed, do SQL query to allow translating category title
				if (FLEXI_FISH)
				{
					$query = 'SELECT id, title, alias FROM #__categories WHERE id = ' . (int) $rootcat;
					$row_cat = $database->setQuery($query)->loadObject();

					if ($row_cat)
					{
						$rootcat_title = ($sefConfig->useCatAlias ? $row_cat->alias : $row_cat->title) . '/';
					}
				}

				// FALANG not installed, no need for SQL query
				elseif (isset($globalcats[$ancestor]))
				{
					list($_cat_id, $_cat_alias) = explode(':', $globalcats[$ancestor]->slug);
					$rootcat_title = ($sefConfig->useCatAlias ? $_cat_alias : $globalcats[$ancestor]->title) . '/';
				}

				$title[] = !empty($rootcat_title)
					? $rootcat_title . '/'
					: $rootcat . '/';
			}

		}

		// Remove the vars from the url
		shRemoveFromGETVarsList('view');

		if ($view === 'flexicontent')
		{
			shRemoveFromGETVarsList('rootcat');
		}

		// Create page id
		//shMustCreatePageId('set', true);
	break;


	// not handled cases or no SEF needed
	case 'fileselement' :
	case 'itemelement' :
	default:
		$dosef = false;
	break;
}


if ($task === 'download')
{
	$title[] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_DOWNLOAD'] . '/';

	// TODO more, e.g. include "title" segments: /item_title/fieldname/file_title/
	// Currently we will let the QUERY variable appear in the URL

	/*$title[] = $id . '/';  // file ID
	$title[] = $cid . '/';  // content item ID
	$title[] = $fid;  // field ID

	shRemoveFromGETVarsList('task');
	shRemoveFromGETVarsList('id');
	shRemoveFromGETVarsList('cid');
	shRemoveFromGETVarsList('fid');*/
	$dosef = true;
}

else if ($task === 'weblink')
{
	$title[] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_WEBLINK'] . '/';

	// TODO more, e.g. include "title" segments: /item_title/fieldname/value_order/
	// Currently we will let the QUERY variable appear in the URL

	/*$title[] = $fid . '/';  // field ID
	$title[] = $cid . '/';  // content item ID
	$title[] = $ord;   // value order

	shRemoveFromGETVarsList('task');
	shRemoveFromGETVarsList('fid');
	shRemoveFromGETVarsList('cid');
	shRemoveFromGETVarsList('ord');*/
	$dosef = true;
}

/**
 * Some special handling for pagination
 */

// For item view limit needs to be 1, so that page numbers are calculated correctly
if ($view === 'item')
{
	$limit = 1;
}

// Use 'start' if 'limitstart' is not set
if (!isset($limitstart) &&  isset($start))
{
	$limitstart = $start;
}

// The following are done per case, to make sure that non-handled case will not unset the variables, and thus breaking the URL !!
//if (!empty($task))   shRemoveFromGETVarsList('task');
//if (!empty($view))   shRemoveFromGETVarsList('view');

// Never unset return URL
//if (!empty($return))   shRemoveFromGETVarsList('return');


// ------------------ standard plugin finalize function - don't change ---------------------------
if ($dosef)
{
	$string = shFinalizePlugin(
		$string, $title, $shAppendString, $shItemidString,
		(isset($limit) ? $limit : null),
		(isset($limitstart) ? $limitstart : null),
		(isset($shLangName) ? $shLangName : null),
		(isset($showall) ? $showall : null)    // currently ignored for non com_content components ?
	);
}
// ------------------ standard plugin finalize function - don't change ---------------------------
