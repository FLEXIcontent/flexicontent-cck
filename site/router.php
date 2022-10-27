<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );


function FLEXIcontentBuildRoute(&$query)
{
	static $router = null;

	if ($router === null)
	{
		$router = new _FlexicontentSiteRouter();
	}

	return $router->FLEXIcontentBuildRoute($query);
}


function FLEXIcontentParseRoute(& $segments)
{
	static $router = null;

	if ($router === null)
	{
		$router = new _FlexicontentSiteRouter();
	}

	return $router->FLEXIcontentParseRoute($segments);
}



class _FlexicontentSiteRouter
{
	/**
	 * Create the segments of the SEF URL (segments are SEF URL parts seperated by '/')
	 * $query variables that will not be unset will be automatically inserted into the URL
	 * as /index/value/
	 */
	public function FLEXIcontentBuildRoute(&$query)
	{
		$segments = array();

		// If both 'start' and 'limitstart' variables are set then prefer 'limitstart' and also remove 'start' variable from URL
		if (isset($query['start']) && isset($query['limitstart']))
		{
			$query['start'] = $query['limitstart'];
			unset($query['start']);
		}

		$app = JFactory::getApplication();
		$params = JComponentHelper::getParams('com_flexicontent');

		$add_item_sef_segment = (int) $params->get('add_item_sef_segment', 1);
		$remove_ids = (int) $params->get('sef_ids', 0);

		// 1. Get a menu item based on Itemid or currently active
		$menus = $app->getMenu('site', array());

		if (empty($query['Itemid']))
		{
			//  USE current Active ID it is now handled in route.php and also add a global config option whether to enable this
			//$menu = $menus->getActive();
			//$query['Itemid'] = $menu ? $menu->id : 0;
		}
		else
		{
			$menu = $menus->getItem($query['Itemid']);
		}


		// 2. Try to match the variables against the variables of the menu item
		if (!empty($menu))
		{
			$menuItem_matches = true;

			foreach($query as $index => $value)
			{
				// Skip URL query variable 'Itemid', since it does not exist PLUS we retrieve the menu item with the given Itemid
				if ($index === 'Itemid')
				{
					continue;
				}

				// Allow alternative display formats to match a given item
				if ($index === 'format' || $index === 'type')
				{
					continue;
				}

				// id and cid query variables can be contain 'slug', so we need to typecast them into integer
				if (in_array($index, array('id', 'cid')))
				{
					$value  = (int) $value;
				}

				// Compare current query variable against the menu query variables
				if ($value != (isset($menu->query[$index]) ? $menu->query[$index] : null))
				{
					$menuItem_matches = false;
					break;
				}
			}

			// If exact menu match then unset ALL $query array, only the menu item segments will appear
			if ($menuItem_matches)
			{
				foreach($query as $index => $value)
				{
					// Do not unset option, Itemid and format variables, as these are needed / handled by JRoute
					if ($index === 'option' || $index === 'Itemid' || $index === 'format')
					{
						continue;
					}

					// Other variables to unset only if they match
					if ($index === 'type' && $value != (isset($menu->query[$index]) ? $menu->query[$index] : null))
					{
						continue;
					}

					unset($query[$index]);
				}

				// $segments is empty we will get variables from menuItem (it has them)
				return $segments;
			}
		}


		// 3. Handle known 'task'(s) formulating segments of SEF URL appropriately
		if (isset($query['task']))
		{
			if ($query['task'] === 'download')
			{
				$segments[] = $query['task'];
				$segments[] = $query['id'];
				$segments[] = $query['cid'];
				$segments[] = $query['fid'];

				unset($query['task']);	// task
				unset($query['id']);	// file
				unset($query['cid']);	// content
				unset($query['fid']);	// field
			}

			elseif ($query['task'] === 'download_file')
			{
				$segments[] = $query['task'];
				$segments[] = $query['id'];

				unset($query['task']);	// task
				unset($query['id']);	// file
			}

			elseif ($query['task'] === 'weblink')
			{
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
		$mview = !empty($menu->query['view']) ? $menu->query['view'] : null;
		$view  = !empty($query['view']) ? $query['view'] : null;

		switch ($view)
		{
			case 'item':
				// Menu item is of view 'flexicontent' (directory) then use 'rootcatid' as category id
				if ($mview === 'flexicontent')
				{
					$mcid = !isset($menu->query['rootcatid']) ? null : (int) $menu->query['rootcatid'];
					$mid  = null;
				}

				// Menu item is of view 'category' or 'item' or other try 'cid' and 'id'
				else
				{
					$mcid = !isset($menu->query['cid']) ? null : (int) $menu->query['cid'];
					$mid  = !isset($menu->query['id']) ? null : (int) $menu->query['id'];
				}

				// For new item form, Item ID is empty and possibly Category ID is too, so check that these are set
				$cid  = !isset($query['cid']) ? null : (int) $query['cid'];
				$id   = !isset($query['id']) ? null : (int) $query['id'];

				if ($remove_ids)
				{
					/**
					 * Create segments defining the category, this is an array of Category ALIASES starting from the category of the menu item
					 * If adding 0 category segments, and if single segment is configured as category URL, then this call will prepend a 'category' segment
					 */

					// Add no segments, even if CIDs of menu item is not an exact match
					if ($mview === 'item' && $mid === $id)
					{
					}

					else
					{
						$this->_fc_route_buildCatPath($segments, $id, $cid, $mcid, $view);

						/**
						 * Create segment defining the item, this only ALIAS and not SLUG (id:alias)
						 */
						$alias = $id !== null ? substr($query['id'], strlen($id) + 1) : null;
						$alias = $id !== null && !strlen($alias) ? $query['id'] : $alias;
						$segments[] = $alias;
					}
				}

				else
				{
					// Create segment defining the item, this is SLUG (id:alias) since we are not removing IDs
					$slug = $id !== null ? $query['id'] : null;

					// Add no segments, even if CIDs of menu item is not an exact match
					if ($mview === 'item' && $mid === $id)
					{
					}

					// Add 'item' segment if using a category view menu item with a non-empty 'layout', needed since segment length has different meaning for such items
					elseif ($mview === 'category' && !empty($menu->query['layout']))
					{
						$segments[] = 'item';
						$segments[] = $slug;
					}


					// Add 'item' segment if menu item view is not 'category', e.g. it is 'flexicontent' (directory)
					elseif ($mview !== 'category')
					{
						$segments[] = 'item';
						$segments[] = $slug;
					}

					// Menu item view is 'category', avoid 'item' segment if so configured
					else
					{
						/**
						 * We will make an item URL -with- CID ...
						 * cid EXISTs and cid/view URL variables to do not match those in the menu item
						 * IMPLY view = 'item' when count($segments) == 2
						 */
						if ($cid && $mcid !== $cid)
						{
							$catslug = $cid !== null ? $query['cid'] : null;
							$segments[] = $catslug;
						}

						/**
						 * We will make an item URL -without- CID ...
						 * because cid is missing or matched cid/view URL variables matched those in menu item
						 */

						// EXPLICIT view ('item' be contained in the url), because according to configuration,
						// the 1-segment implies --category-- view so we need to add /item/ segment (1st segment is view)
						elseif ($add_item_sef_segment === 1)
						{
							$segments[] = 'item';
						}

						// IMPLICIT view ('item' NOT contained in the url), because according to configuration,
						// the 1-segment implies --item-- view so we do not need to add /item/ segment (1st segment is view)
						else
						{
						}

						$segments[] = $slug;
					}
				}

				unset($query['view']);
				unset($query['cid']);
				unset($query['id']);
				break;

			case 'category':
				$mcid = !isset($menu->query['cid']) ? null : (int) $menu->query['cid'];
				$cid  = !isset($query['cid']) ? null : (int) $query['cid'];

				if ($remove_ids)
				{
					// Most URLs will not use this and instead will use the full path by calling _fc_route_buildCatPath()
					$catalias = $cid !== null ? substr($query['cid'], strlen($cid . '-')) : null;
					$catalias = $cid !== null && !strlen($catalias) ? $query['cid'] : $catalias;
				}
				else
				{
					$catslug = $cid !== null ? $query['cid'] : null;
				}

				$mlayout = !empty($menu->query['layout']) ? $menu->query['layout'] : null;
				$layout  = !empty($query['layout']) ? $query['layout'] : null;

				$keep_view_layout = false;

				// Handle layout
				switch ($layout)
				{
					case 'tags':
						$mtagid = !empty($menu->query['tagid']) ? (int) $menu->query['tagid'] : null;
						$tagid  = !empty($query['tagid']) ? (int) $query['tagid'] : null;

						0  // TODO use here $remove_ids
							// TODO: Use alias path from TAGs Tree, create new method for tis: _fc_route_buildTagPath()
							? ($tagalias = $tagid !== null ? substr($query['tagid'], strlen($tagid) + 1) : null)
							: ($tagslug = $tagid !== null ? $query['tagid'] : null);

						if (0 /*$remove_ids*/ && $tagid !== null && !strlen($tagalias))
						{
							$tagalias = $query['tagid'];
						}

						if ($mview !== $view || $mlayout !== $layout)
						{
							$segments[] = 'tagged';
						}

						if ($mtagid !== $tagid)
						{
							$segments[] = 0  // TODO use here $remove_ids
								? $tagalias  // TODO: Use alias path from TAGs Tree
								: $tagslug;
						}

						if ($cid && $mcid !== $cid)
						{
							$remove_ids
								? $this->_fc_route_buildCatPath($segments, 0, $cid, $mcid, $view)
								: $segments[] = $catslug;
						}

						unset($query['tagid']);
						unset($query['cid']);
						break;

					case 'author':
						$mauthorid = isset($menu->query['authorid']) ? (int) $menu->query['authorid'] : null;
						$authorid  = isset($query['authorid']) ? (int) $query['authorid'] : null;

						$remove_ids
							? ($authoralias = $authorid !== null ? substr($query['authorid'], strlen($authorid) + 1) : null)
							: ($authorslug = $authorid !== null ? $query['authorid'] : null);

						if ($remove_ids && $authorid !== null && !strlen($authoralias))
						{
							$authoralias = $query['authorid'];
						}

						if ($mview !== $view || $mlayout !== $layout)
						{
							$segments[] = 'authored';
						}

						if ($mauthorid !== $authorid)
						{
							$segments[] = $remove_ids
								? $authoralias
								: $authorslug;
						}

						if ($cid && $mcid !== $cid)
						{
							$remove_ids
								? $this->_fc_route_buildCatPath($segments, 0, $cid, $mcid, $view)
								: $segments[] = $catslug;
						}

						unset($query['authorid']);
						unset($query['cid']);
						break;

					case 'favs':
						if ($mview !== $view || $mlayout !== $layout)
						{
							$segments[] = 'favoured';
						}

						if ($cid && $mcid !== $cid)
						{
							$remove_ids
								? $this->_fc_route_buildCatPath($segments, 0, $cid, $mcid, $view)
								: $segments[] = $catslug;
						}

						unset($query['cid']);
						break;

					case 'myitems':
						if ($mview !== $view || $mlayout !== $layout)
						{
							$segments[] = 'myitems';
						}

						if ($cid && $mcid !== $cid)
						{
							$remove_ids
								? $this->_fc_route_buildCatPath($segments, 0, $cid, $mcid, $view)
								: $segments[] = $catslug;
						}

						unset($query['cid']);

						// Author ID should not be in the SEF URL even if it is set, as it is ignored (registered view effected by current user)
						unset($query['authorid']);
						break;

					case 'mcats':
						$mcids = isset($menu->query['cids']) ? $menu->query['cids'] : null;
						$cids  = isset($query['cids']) ? $query['cids'] : null;

						$mcids = $mcids ?: array();
						$cids  = $cids ?: array();

						$mcids = !is_array($mcids) ? explode(',', $mcids) : $mcids;
						$cids  = !is_array($cids) ? explode(',', $cids) : $cids;

						if ($mview === $view && $mlayout === $layout)
						{
							if (array_values($mcids) == array_values($cids))
							{
								unset($query['cids']);
							}
						}
						else
						{
							$segments[] = 'categories';
							$segments[] = is_array($cids) ? implode(',', $cids) : $cids;

							unset($query['cids']);
						}

						break;

					default:  // Unhandled
						$keep_view_layout = $layout ? true : false;
						break;
				}

				// Handle adding category ID and view if not already handled above
				if (!$layout && !count($segments) && $cid && ($mview !== $view || $mcid !== $cid))
				{
					if ($remove_ids)
					{
						/**
						 * Create segments defining the category, this is an array of Category ALIASES starting from the category of the menu item
						 * If adding 1 category segment, and if single segment is configured as item view, then this call will prepend a 'category' segment
						 */
						$this->_fc_route_buildCatPath($segments, 0, $cid, $mcid, $view);
					}
					else
					{
						/**
						 * If adding explicit view /item/ is disabled for no-cid item URLs (thus they are 1-segment URLs),
						 * or the given menu item is not of category view, then ... we need to explicitly declare
						 * that this URL is a category URL, otherwise it will be wrongly interpreted as 'item' URL
						 */
						if ($add_item_sef_segment === 0 && $mview === 'category')
						{
							$segments[] = 'category';
						}

						/**
						 * IMPLY view = 'category' when count($segments) == 1
						 * Check cid is set as it is optional, some category view layouts do not use category id
						 */
						if ($cid)
						{
							$segments[] = $catslug;
						}
					}

					unset($query['cid']);
				}

				// 1. Unset 'layout' if not needed
				if (!$keep_view_layout)
				{
					unset($query['layout']);
				}

				// 2. Unset 'view' always, since this should be handled above in ALL cases
				unset($query['view']);

				// 3. Unset 'cid' if it matches the menu item, this may have be done above explicitely in every above case, otherwise it will remain in the URL
				if ($mcid === $cid)
				{
					unset($query['cid']);
				}
				break;

			case 'tags':
			case 'tag':  // legacy 'tags' view
				// EXPLICIT view (will be contained in the url)
				$segments[] = 'tag';
				$segments[] = isset($query['id']) ? $query['id'] : null;  // Required ...

				unset($query['view']);
				unset($query['id']);
				break;

			case 'flexicontent':    // (aka directory view)
				$mrootcat = !isset($menu->query['rootcat']) ? 1 : (int) $menu->query['rootcat'];
				$rootcat  = !isset($query['rootcat']) ? 1 : (int) $query['rootcat'];

				if ($mview !== $view || $mrootcat !== $rootcat)
				{
					// view/rootcat URL variables did not match add them as segments (but use 'catalog' as segment for view)
					$segments[] = 'catalog';
					$segments[] = isset($query['rootcat']) ? $query['rootcat'] : 1;
				}

				unset($query['view']);
				unset($query['rootcat']);
				break;

			case 'search':
			case 'favourites':    // legacy 'favourites' view
			case 'fileselement':
			default:
				// EXPLICIT view (will be contained in the url)
				if ($view)
				{
					$segments[] = $view;
				}

				unset($query['view']);

				// Set remaining variables "/variable/value/" pairs
				foreach ($query as $variable => $value)
				{
					if ( $variable!="option" || $variable!="Itemid") continue; // skip 'option' and 'Itemid' variables !
					$segments[] = $variable;
					$segments[] = $value;
					unset($query[$variable]);
				}

				/*
				if (isset($query['id']))
				{
					$segments[] = 'id';
					$segments[] = $query['id'];
					unset($query['id']);
				}
				*/
				break;
		}

		/**
		 * We leave remaining $query variables untouched,
		 * so that these variables will be appended to the SEF URL
		 */

		return $segments;
	}



	/**
	 * Construct a proper URL request from the SEF url, we try to reverse what FLEXIcontentBuildRoute() DID
	 */
	public function FLEXIcontentParseRoute(& $_segments)
	{
		/**
		 * Set segments array to empty array, to indicate to Joomla parser that all segments have been consumed
		 * otherwise J4 router will assume that routing has failed.
		 * If we fail to parse a valid URL we will restore the segments array before terminating this method
		 */
		$segments = array_merge(array(), $_segments);
		$_segments = array();

		$vars = array();
		$_tbl = null;

		$params = JComponentHelper::getParams('com_flexicontent');

		$add_item_sef_segment = (int) $params->get('add_item_sef_segment', 1);
		$remove_ids = (int) $params->get('sef_ids', 0);

		// Get the active menu item
		$menu = JFactory::getApplication()->getMenu('site', array())->getActive();

		// Count route segments
		$count = count($segments);


		/**
		 * 1. Cases that TASK is explicitly provided
		 */

		// 'download' task
		if ($segments[0] === 'download')
		{
			$vars['task'] = 'download';
			$vars['id']   = isset($segments[1]) ? $segments[1] : null;
			$vars['cid']  = isset($segments[2]) ? $segments[2] : null;
			$vars['fid']  = isset($segments[3]) ? $segments[3] : null;

			return $vars;
		}

		// 'download_file' task
		if ($segments[0] === 'download_file')
		{
			$vars['task'] = 'download_file';
			$vars['id']   = isset($segments[1]) ? $segments[1] : null;

			return $vars;
		}

		// 'weblink' task
		if ($segments[0] === 'weblink')
		{
			$vars['task'] = 'weblink';
			$vars['fid']  = isset($segments[1]) ? $segments[1] : null;
			$vars['cid']  = isset($segments[2]) ? $segments[2] : null;
			$vars['ord']  = isset($segments[3]) ? $segments[3] : null;

			return $vars;
		}


		// Menu view, menu category layout (if this applicable) and menu category id (if this applicable)
		$mview   = !empty($menu->query['view']) ? $menu->query['view'] : null;
		$mlayout = !empty($menu->query['layout']) ? $menu->query['layout'] : null;
		$mcid = $mview === 'flexicontent'
			? (!empty($menu->query['rootcatid']) ? (int) $menu->query['rootcatid'] : null)
			: (!empty($menu->query['cid']) ? (int) $menu->query['cid'] : null);

		// Start of category is path (if zero then category path should start at top level)
		$parent_id = in_array($mview, array('flexicontent', 'category'))
			? $mcid
			: 0;
		$parent_id_layoutvar = null;


		/**
		 * 2. Cases that VIEW is explicitly provided
		 */

		// 'item' view
		if ($segments[0] === 'item' || $segments[0] === 'items')
		{
			$vars['view'] = 'item';

			// No cid provided
			if ($count === 2)
			{
				if (@$menu->query['view'] === 'category' && @$menu->query['cid'])
				{
					$vars['cid'] = (int) $menu->query['cid'];
				}

				$vars['id'] = $this->_fc_route_addRecordIdPrefix($segments, 1, '#__content', $_tbl, $parent_id);
			}

			// Also cid provided
			elseif ($count === 3)
			{
				$vars['cid'] = $this->_fc_route_addRecordIdPrefix($segments, 1, '#__categories', $_tbl, $parent_id);
				$vars['id']  = $this->_fc_route_addRecordIdPrefix($segments, 2, '#__content', $_tbl, $parent_id);
			}

			return $vars;
		}

		// 'category' view
		if ($segments[0] === 'category')
		{
			$vars['view'] = 'category';

			// optional, some category view layouts do not use category id
			$vars['cid'] = $this->_fc_route_addRecordIdPrefix($segments, 1, '#__categories', $_tbl, $parent_id);

			return $vars;
		}

		// 'tags' view
		if ($segments[0] === 'tag' || $segments[0] === 'tags')
		{
			$vars['view'] = 'tags';
			$vars['id'] = $segments[1];

			return $vars;
		}

		// 'tags' via category view
		if ($segments[0] === 'tagged')
		{
			$vars['view'] = 'category';
			$vars['layout'] = 'tags';

			// TODO add full alias path of the tag
			$vars['tagid'] = $this->_fc_route_addRecordIdPrefix($segments, 1, '#__flexicontent_tags', $_tbl, $parent_id_layoutvar);
			$vars['cid'] = $this->_fc_route_parseCatPath($segments, $expected_view = 'tagged', $start = 2, $parent_id);

			return $vars;
		}

		// 'author' via category view
		if ($segments[0] === 'authored')
		{
			$vars['view'] = 'category';
			$vars['layout'] = 'author';
			$vars['authorid'] = $this->_fc_route_addRecordIdPrefix($segments, 1, '#__users', $_tbl, $parent_id_layoutvar, null, 'username');
			$vars['cid'] = $this->_fc_route_parseCatPath($segments, $expected_view = 'authored', $start = 2, $parent_id);

			return $vars;
		}

		// 'favourites' via category view
		if ($segments[0] === 'favoured')
		{
			$vars['view'] = 'category';
			$vars['layout'] = 'favs';
			$vars['cid'] = $this->_fc_route_parseCatPath($segments, $expected_view = 'favoured', $start = 1, $parent_id);

			return $vars;
		}

		// 'myitems' via category view
		if ($segments[0] === 'myitems')
		{
			$vars['view'] = 'category';
			$vars['layout'] = 'myitems';
			$vars['cid'] = $this->_fc_route_parseCatPath($segments, $expected_view = 'myitems', $start = 1, $parent_id);

			return $vars;
		}

		// 'mcats' via category view
		if ($segments[0] === 'categories')
		{
			$vars['view'] = 'category';
			$vars['layout'] = 'mcats';
			$vars['cids'] = isset($segments[1]) ? $segments[1] : null;  // optional

			return $vars;
		}

		// 'flexicontent' view (aka directory view)
		if ($segments[0] === 'catalog')
		{
			$vars['view'] = 'flexicontent';
			$vars['rootcat'] = $segments[1];

			return $vars;
		}

		// VIEWs with no extra variables
		$view_only = array(
			'favourites' => 1,
			'fileselement' => 1,
			'itemelement' => 1,
			'search' => 1
		);

		if (isset($view_only[$segments[0]]))
		{
			$vars['view'] = $segments[0];

			return $vars;
		}


		/**
		 * 3. Cases that current menu item is a category-layout menu, do not use any segment 'length' rules (case 4)
		 */

		if ($mview === 'category' && $mlayout)
		{
			switch ($mlayout)
			{
				case 'tags':
					$vars['view']   = 'category';
					$vars['layout'] = 'tags';
					$vars['tagid']  = $this->_fc_route_addRecordIdPrefix($segments, 0, '#__flexicontent_tags', $_tbl, $parent_id_layoutvar);

					// Optional, Small B/C change, throw 404 if unmatched segments ...
					$vars['cid'] = count($segments) > 1
						? $this->_fc_route_parseCatPath($segments, $expected_view = $mlayout, $start = 1, $parent_id)
						: null;

					return $vars;

				case 'author':
					$vars['view']     = 'category';
					$vars['layout']   = 'author';
					$vars['authorid'] = $this->_fc_route_addRecordIdPrefix($segments, 0, '#__users', $_tbl, $parent_id_layoutvar, null, 'username');

					// Optional, Small B/C change, throw 404 if unmatched segments ...
					$vars['cid'] = count($segments) > 1
						? $this->_fc_route_parseCatPath($segments, $expected_view = $mlayout, $start = 1, $parent_id)
						: null;

					return $vars;

				case 'favs':
					$vars['view']   = 'category';
					$vars['layout'] = 'favs';

					// Optional, Small B/C change, throw 404 if unmatched segments ...
					$vars['cid'] = count($segments) > 0
						? $this->_fc_route_parseCatPath($segments, $expected_view = $mlayout, $start = 0, $parent_id)
						: null;

					return $vars;

				case 'myitems':
					$vars['view']   = 'category';
					$vars['layout'] = 'myitems';

					// Optional, Small B/C change, throw 404 if unmatched segments ...
					$vars['cid'] = count($segments) > 0
						? $this->_fc_route_parseCatPath($segments, $expected_view = $mlayout, $start = 0, $parent_id)
						: null;

					return $vars;
			}

			// Failed to parse, restore segments array ...
			$_segments = $segments;
		}


		/**
		 * 4. Cases that VIEW is not provided (instead it is implied by length of segments)
		 */

		// Count is integer aka safe to use swith to compare with zero ...
		switch ($count)
		{
			// SEGMENT LENGTH 0: is 'flexicontent' view (aka directory view)
			case 0:
				$vars['view'] = 'flexicontent';

				return $vars;

			/**
			 * SEGMENT LENGTH 1
			 *  Is 'category' view (default), or 'item' view depending on configuration
			 *  The 1 segment item URLs are possible only if (e.g.) menu item is a matching 'category' menu item,
			 *  in this case the category URLs become explicit with /category/ segment after the category menu item
			 *  so that they are not 1 segment of length ... BUT detect bad 1-segments URL (Segments must be integer prefixed)
			 */
			case 1:
				if ($add_item_sef_segment === 2 && in_array($mview, array('category')))
				{
					$segments[0] = $this->_fc_route_addRecordIdPrefix($segments, 0, array('#__content', '#__categories'), $_tbl, $parent_id);
					$is_item_view = $_tbl === '#__content';
				}
				else
				{
					$is_item_view = $add_item_sef_segment === 0 && in_array($mview, array('category'));
					$segments[0] = $is_item_view
							? $this->_fc_route_addRecordIdPrefix($segments, 0, '#__content', $_tbl, $parent_id)
							: $this->_fc_route_addRecordIdPrefix($segments, 0, '#__categories', $_tbl, $parent_id);
				}

				/**
				 * First check for bad URLs, this is needed for bad URLs with invalid MENU ALIAS segments,
				 * that are routed to FLEXIcontent because the PARENT menu is FLEXIcontent MENU ITEM
				 */
				$element_id_0 = (int) $segments[0];

				// Force article error page
				if (!$element_id_0)
				{
					$vars['view'] = 'item';
					$vars['cid']  = '0';
					$vars['id']   = '0';
				}

				// Item view
				elseif ($is_item_view)
				{
					$vars['view'] = 'item';
					$vars['cid']  = $menu->query['cid'];
					$vars['id']   = $segments[0];
				}

				// Category view
				else
				{
					$vars['view'] = 'category';
					$vars['cid']  = $segments[0];
				}

				return $vars;

			/**
			 * SEGMENT LENGTH 2
			 *  Is 'item' view, BUT detect bad 2-segments URL (Segments must be integer prefixed)
			 */
			case 2:
				$segments[0] = $this->_fc_route_addRecordIdPrefix($segments, 0, '#__categories', $_tbl, $parent_id);
				$segments[1] = $this->_fc_route_addRecordIdPrefix($segments, 1, array('#__content', '#__categories'), $_tbl, $parent_id);

				/**
				 * Check for bad URLs, THIS is needed for bad URLs with invalid MENU ALIAS segments,
				 * that are routed to FLEXIcontent because the PARENT menu is FLEXIcontent MENU ITEM
				 */
				$element_id_0 = (int) $segments[0];
				$element_id_1 = (int) $segments[1];

				if (!$element_id_0 || !$element_id_1)
				{
					// Force article error page
					$vars['view'] = 'item';
					$vars['cid']  = '0';
					$vars['id']   = '0';

					return $vars;
				}

				/**
				 * With legacy code this would always be item view, but with remove IDs option
				 * the 2-segment URLs maybe also be a case of 2 segment category alias path
				 */
				if (!$_tbl || $_tbl === '#__content')
				{
					$vars['view'] = 'item';
					$vars['cid'] 	= $segments[0];
					$vars['id'] 	= $segments[1];
				}
				else
				{
					$vars['view'] = 'category';
					$vars['cid'] 	= $segments[1];
				}

				return $vars;


			/**
			 * SEGMENT LENGTH > 2
			 *  Explicit view at segment 0, and handle segments 1 and higher, as "/variable/value/" pairs
			 */
			default:
				static $flexi_views = null;

				/**
				 * 0. Find out component views that exist on disk
				 */
				if ($flexi_views === null)
				{
					$flexi_view_paths = glob(JPATH_SITE . DS . 'components' . DS . 'com_flexicontent' . DS . 'views' . DS . '[a-zA-Z_0-9]*', GLOB_ONLYDIR);

					foreach ($flexi_view_paths as $flexi_view_path)
					{
						$flexi_view = basename($flexi_view_path);

						if ($flexi_view !== '.' && $flexi_view !== '..')
						{
							$flexi_views[$flexi_view] = 1;
						}
					}
				}

				/**
				 * Check if given an explicit view (view exists in the disk)
				 */
				$explicit_view = $segments[0];
				$is_explicit_view = isset($flexi_views[$explicit_view]);

				/**
				 * A. View not given explicitly, try to find it by checking IDs and Aliases
				 */
				if (!$is_explicit_view)
				{
					$detected_view = 'category';

					for ($i = 0; $i < count($segments); $i++)
					{
						$record_id = $this->_fc_route_getRecordIdByAlias(str_replace(':', '-', $segments[$i]), $parent_id, $language = null, $tbl = '#__categories');

						if (!$record_id && $i === count($segments) - 1)
						{
							$detected_view = 'item';
							$record_id = $this->_fc_route_getRecordIdByAlias(str_replace(':', '-', $segments[$i]), $parent_id, $language = null, $tbl = '#__content');
						}

						$record_id = $record_id ?: (int) $segments[$i];

						// Throw non found view error if not found
						if (!$record_id)
						{
							// Make sure our language file has been loaded
							JFactory::getLanguage()->load('com_flexicontent', JPATH_SITE, 'en-GB', true);
							JFactory::getLanguage()->load('com_flexicontent', JPATH_SITE, null, true);

							throw new Exception(JText::sprintf('FLEXI_REQUESTED_CONTENT_OR_VIEW_NOT_FOUND', $explicit_view), 404);
						}

						$segments[$i] = $record_id . ':' . str_replace(':', '-', $segments[$i]);
						$parent_id = $record_id;
					}

					$vars['view'] = $detected_view;
					$detected_view === 'item'
						? $vars['id']  = end($segments)
						: $vars['cid'] = end($segments);
				}

				/**
				 * B. View given explicitly
				 */
				else
				{
					/**
					 * 1. First segment is an explicitely provided view
					 */
					$vars['view'] = $explicit_view;

					/**
					 * 2. Consider remaining segments as "/variable/value/" pairs
					 */
					for ($i = 1; $i < count($segments); $i = $i + 2)
					{
						$vars[$segments[$i]] = $segments[$i];
					}
				}

				return $vars;
		}
	}


	/**
	 * Get the ID of a record when given the record's alias and table name
	 */
	private function _fc_route_getRecordIdByAlias(
		$alias, $parent_id = null, $language = null,
		$tbl = '#__categories', $alias_col = 'alias'
	)
	{
		$language = !$language /*&& !$parent_id*/ ? JFactory::getLanguage()->getTag() : $language;

		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select('i.id')
			->from($db->QuoteName($tbl) . ' AS i')
			->where('i.' . $db->QuoteName($alias_col) . ' = ' . $db->Quote($alias));

		/**
		 * Limit query to categories of com_content extension !
		 */
		if ($tbl === '#__categories')
		{
			$query->where($db->QuoteName('extension') . ' = ' . $db->Quote('com_content'));
		}

		/**
		 * Limit to parent category (or parent record), because same record alias may exist in for records in other categories
		 */
		if ($parent_id)
		{
			if ($tbl === '#__content')
			{
				$query->join('INNER', '#__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id')->where('rel.catid = ' . (int) $parent_id);
			}
			elseif ($tbl === '#__categories')
			{
				$query->where('i.' . $db->QuoteName('parent_id') . ' = ' . (int) $parent_id);
			}
			else
			{
				$query->select('i.parent_id');
				//$query->where('i.' . $db->QuoteName('parent_id') . ' = ' . (int) $parent_id);
			}
		}

		if ($language)
		{
			if ($tbl === '#__content')  // || $tbl === '#__categories'
			{
				$query->where('(i.language = ' . $db->Quote($language) . ' OR i.language = ' . $db->Quote('*') . ')');
			}

			if ($tbl === '#__content' || $tbl === '#__categories')
			{
				$query->select('i.language');
			}
		}

		//echo $alias . ' -- ' . $query . '<br>';
		$records = $db->setQuery($query)->loadObjectList();
		

		/**
		 * Multiple records with same alias were found, try to filter out records with non-matching language
		 * (if not done by query already, e.g. not done for #__categories) 
		 */
		if (count($records) > 1 && $language)
		{
			foreach($records as $k => $record)
			{
				if ($record->language != $language && $record->language != '*')
				{
					if (count($records) > 1) unset($records[$k]);
				}
			}
		}

		/*
		 * We still have multiple records with same alias, try to filter out records with non-matching parent_id
		 * (if not done by query already, e.g. not done for #__categories) 
		 */
		if (count($records) > 1 && $parent_id)
		{
			foreach($records as $record)
			{
				if ((int) $record->parent_id !== (int) $parent_id)
				{
					if (count($records) > 1) unset($records[$k]);
				}
			}
		}

		/**
		 * If we now have 1 record, return
		 * But for multiple content items with same alias just return the first one
		 * We do this for compatibility as MVC should make sure that this does not happen
		 */
		if (count($records) === 1 || (count($records) && $tbl === '#__content'))
		{
			$record = reset($records);
			return (int) $record->id;
		}

		if (count($records) > 1)
		{
			// Make sure our language file has been loaded
			JFactory::getLanguage()->load('com_flexicontent', JPATH_SITE, 'en-GB', true);
			JFactory::getLanguage()->load('com_flexicontent', JPATH_SITE, null, true);

			throw new Exception(JText::sprintf('FLEXI_DUPLICATE_ALIAS_FAILED_TO_FIND_UNIQUE_PAGE', ($tbl === '#__categories' ? 'category' : 'item'), $alias), 404);
		}

		return 0;
	}


	/**
	 * Prepend record ID to a SEF URL segment $segment[$i], if id is not already present
	 */
	private function _fc_route_addRecordIdPrefix(
		$segments, $i, $tbls, & $tbl = null,
		& $parent_id = null, $language = null, $alias_col = 'alias'
	)
	{
		// None DB table tried
		$tbl  = null;
		$tbls = is_array($tbls) ? $tbls : array($tbls);

		if (!isset($segments[$i]))
		{
			return null;
		}

		$params = JComponentHelper::getParams('com_flexicontent');

		$add_item_sef_segment = (int) $params->get('add_item_sef_segment', 1);
		$remove_ids = (int) $params->get('sef_ids', 0);

		if (!$remove_ids)
		{
			if ($add_item_sef_segment !== 2)
			{
				return $segments[$i];
			}
			else
			{
				$slug = str_replace(':', '-', $segments[$i]);
				$record_alias_from_url = substr($slug, stripos($slug, '-') + 1);
				$record_id_from_url    = (int) substr($slug, 0, stripos($slug, '-'));
			}
		}
		else
		{
			$record_alias_from_url = str_replace(':', '-', $segments[$i]);
			$record_id_from_url    = 0;
		}

		/**
		 * Try finding the alias in the DB tables in the order they were given
		 */
		foreach ($tbls as $_tbl)
		{
			// Get record ID from record's alias
			$record_id_from_alias = $this->_fc_route_getRecordIdByAlias(
				$record_alias_from_url,
				$parent_id,
				$language,
				$_tbl,
				$alias_col
			);

			// Only prepend record ID if it was found, otherwise prepend nothing allowing legacy SEF URLs that contain IDs to work
			if ($record_id_from_alias && (!$record_id_from_url || $record_id_from_url === $record_id_from_alias))
			{
				$tbl          = $_tbl;
				$segments[$i] = $record_id_from_alias . '-' . $record_alias_from_url;
				break;
			}
		}

		/**
		 * Compatibility: If we fail to find the alias, then check if segment is in format id-alias
		 *  (Removing IDs from URLs was enabled in websites that used to have legacy URLs with ids)
		 *  (These legacy URLs have been indexed by search engines and bookmarked by visitors)
		 */
		if (!$tbl && $remove_ids)
		{
			$slug = str_replace(':', '-', $segments[$i]);
			$record_alias_from_url = substr($slug, stripos($slug, '-') + 1);
			$record_id_from_url    = (int) substr($slug, 0, stripos($slug, '-'));

			if ($record_id_from_url)
			{
				foreach ($tbls as $_tbl)
				{
					// Get record ID from record's alias
					$record_id_from_alias = $this->_fc_route_getRecordIdByAlias(
						$record_alias_from_url,
						$parent_id,
						$language,
						$_tbl,
						$alias_col
					);

					// Only prepend record ID if it was found, otherwise prepend nothing allowing legacy SEF URLs that contain IDs to work
					if ($record_id_from_alias && (!$record_id_from_url || $record_id_from_url === $record_id_from_alias))
					{
						$tbl          = $_tbl;
						$segments[$i] = $record_id_from_alias . '-' . $record_alias_from_url;
						break;
					}
				}
			}
		}

		// Set found record ID as parent ID
		$parent_id = $record_id_from_alias;

		// Return the target segment that was (possibly) prepended with the record ID
		return $segments[$i];
	}


	private function _fc_route_getItemCategoryPath($id, $cid, $menu_catid)
	{
		global $globalcats;

		// If current category id not given then use item's main category
		if (!isset($globalcats[$cid]))
		{
			$db = JFactory::getDbo();

			$query = $db->getQuery(true)
				->select('i.catid')
				->from($db->QuoteName('#__content') . ' AS i')
				->where('i.id = ' . (int) $id);

			$cid = (int) $db->setQuery($query)->loadResult();
		}

		$catid = $cid;
		$segs = array();

		do
		{
			if (!isset($globalcats[$catid]) || (int) $catid === (int) $menu_catid)
			{
				break;
			}

			$cat = $globalcats[$catid];
			$segs[] = substr($cat->slug, strlen($catid) + 1);
			$catid = $cat->parent_id;
		}
		while (true);

		return array_reverse($segs);
	}


	/**
	 * Build URL segments for the given category (and optionally item)
	 * appeding them to the given $segments array
	 */
	function _fc_route_buildCatPath(&$segments, $item_id, $cat_id, $menu_catid, $view)
	{
		static $add_item_sef_segment = null;

		if ($add_item_sef_segment === null)
		{
			$add_item_sef_segment = (int) JComponentHelper::getParams('com_flexicontent')->get('add_item_sef_segment', 1);
		}

		/**
		 * Create segments defining the category, this is an array of [category aliases] starting from the category of the menu item
		 */
		$catpath = $this->_fc_route_getItemCategoryPath($item_id, $cat_id, $menu_catid);

		/**
		 * Distinguish item view from category view when having 2 segments URLs
		 * by adding the view explicitely according to configuration
		 */
		if (count($segments) === 0)
		{
			if (count($catpath) === 1 && $add_item_sef_segment === 0 && $view === 'category')
			{
				$segments[] = 'category';
			}
			elseif (count($catpath) === 0 && $add_item_sef_segment === 1 && $view === 'item')
			{
				$segments[] = 'item';
			}
		}

		/**
		 * Finally add the category aliases segments
		 */
		foreach($catpath as $seg)
		{
			$segments[] = $seg;
		}
	}


	/**
	 * Parse URL segments as a path of category aliases
	 *
	 * return Integer  The category having the last alias of the path
	 */
	private function _fc_route_parseCatPath(
		&$segments, $expected_view, $start, $parent_id,
		$tbl = '#__categories', $alias_col = 'alias'
	)
	{
		// Segment missing
		if (!isset($segments[$start]))
		{
			return null;
		}

		for ($i = $start; $i < count($segments); $i++)
		{
			$record_id = $this->_fc_route_getRecordIdByAlias(str_replace(':', '-', $segments[$i]), $parent_id, $language = null, $tbl, $alias_col);
			$record_id = $record_id ?: (int) $segments[$i];

			// Throw non found view error if not found
			if (!$record_id)
			{
				// Make sure our language file has been loaded
				JFactory::getLanguage()->load('com_flexicontent', JPATH_SITE, 'en-GB', true);
				JFactory::getLanguage()->load('com_flexicontent', JPATH_SITE, null, true);

				throw new Exception(JText::sprintf('FLEXI_REQUESTED_CONTENT_OR_VIEW_NOT_FOUND', $expected_view), 404);
			}

			$segments[$i] = $record_id . ':' . str_replace(':', '-', $segments[$i]);
			$parent_id = $record_id;
		}

		return $record_id;
	}
}
