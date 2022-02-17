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

use Joomla\Utilities\ArrayHelper;

//include constants file
require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'permission.php');

class flexicontent_cats
{
	/**
	 * Parent Categories
	 *
	 * @var array
	 */
	var $id = null;

	/**
	 * Parent Categories
	 *
	 * @var array
	 */
	var $parentcats_ids  = null;  // ids of ancestors categories, populated by buildParentCats(), used by getParentCats()

	var $parentcats_data = null;  // data (id,title,categoryslug) of ancestors categories, populated by getParentCats() and accessed via getParentlist()

	/**
	 * Constructor
	 *
	 * the constructor will build the parent category array (ancestor of given category up to the root category) by calling:
	 * a. buildParentCats()
	 * b. getParentCats()
	 * so that it can be later retrieved via getParentlist()
	 *
	 * @param int $cid
	 * @return flexicontent_categories
	 */
	public function __construct($cid)
	{
		$this->id = (int) $cid;
	}

	/**
	 * Retrieves parent categories (anscestors) of category until the category
	 * and sets this parent in the member variable 'parentcats_ids'
	 *
	 */
	protected function getParentCats($all_cols=false)
	{
		$db = JFactory::getDbo();

		$this->parentcats_data = array();
		if (empty($this->parentcats_ids)) return;

		$query = 'SELECT ' .($all_cols ? '*,' : 'id, title, published, access,')
				.' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', id, alias) ELSE id END as slug'
				.' FROM #__categories'
				.' WHERE id IN ('.implode(',', $this->parentcats_ids).')'
				. (!FLEXI_J16GE ? ' AND section = '.FLEXI_SECTION : ' AND extension="'.FLEXI_CAT_EXTENSION.'" ' )
				;
		$db->setQuery($query);
		$cats = $db->loadObjectList('id');
		foreach($this->parentcats_ids as $cid) {
			if ( isset($cats[$cid]) )	$this->parentcats_data[] = $cats[$cid];
		}
	}

	/**
	 * Retrieves parent categories (anscestors) of category until the category
	 * and sets this parent in the member variable 'parentcats_ids'
	 *
	 */
	protected function buildParentCats($cid)
	{
		$db = JFactory::getDbo();

		// ALTERNATIVE 1
		/*$currcat = JCategories::getInstance('Content')->get($cid);
		while ($currcat->id != 'root') {
			$this->parentcats_ids[] = $currcat->id;
			$currcat = $currcat->getParent();
		}*/

		// ALTERNATIVE 2
		/*$query = ' SELECT cat.id as id '
		 .' FROM #__categories AS cat '
		 .' WHERE cat.extension = ' . $db->Quote('com_content') .' AND (SELECT lft FROM #__categories WHERE id='.(int)$cid.' ) BETWEEN cat.lft AND cat.rgt'
		 .' GROUP BY cat.id '
		 .' ORDER BY cat.level ASC'
		 ;
		$this->parentcats_ids = $db->setQuery($query)->loadColumn();*/

		global $globalcats;
		$this->parentcats_ids = isset($globalcats[$cid]) ? $globalcats[$cid]->ancestorsarray : array();
		//echo "<pre>" . print_r($this->parentcats_ids, true) ."</pre>";
	}

	/*
	 * Returns the parent category array that is build by functions buildParentCats() and getParentCats()
	 *
	 */
	public function getParentlist()
	{
		if ($this->parentcats_data===null) {
			$this->buildParentCats($this->id);	// Retrieves ids of ancestors categories and set them in member array variable 'parentcats_ids'
			$this->getParentCats();							// Get basic data for ancestors (id,title,slug, etc) and set them in member array variable 'category'
		}
		return $this->parentcats_data;
	}



	/*************************/
	/* STATIC FUNCTION CALLS */
	/*************************/

	/**
    * Get the category tree (a sorted and padded array) but without any filtering or disabling data
    *
    * a. A children array is constructed for every category
    * b. Then treerecurse() is called to sort the category array according to each category parent and also to pad the category titles
    *
    * NOTE: the final category array has no filtering or disabling data ( this is done by buildcatselect() )
    * NOTE: the output of this function can be given to buildcatselect() for the purpose of building a select form field (aka category tree)
    *       that has filtering (via FLEXIaccess or via J1.6+ permission) and disabling of specific categories !!!
    *
    * @return array
    */
	public static function getCategoriesTree( $published_only = false, $parent_id = 0, $depth_limit=0 )
	{
		global $globalcats;
		$db = JFactory::getDbo();
		
		$allowed_catstates = is_array($published_only) ? $published_only : array();

		if ($allowed_catstates)
		{
			$where[] = 'published IN (' . implode(',', ArrayHelper::toInteger($allowed_catstates)) . ')';
		}
		elseif ($published_only)
		{
			$where[] = 'published = 1';
		}

		// Limit category list to those contain in the subtree of the choosen category
		$parent_id = isset($globalcats[(int)$parent_id]) ? (int)$parent_id : 0;
		if ( $parent_id )
		{
			$where[] = 'id IN (' . $globalcats[(int)$parent_id]->descendants . ')';
		}

		$where[] = !FLEXI_J16GE
			? 'section = ' . FLEXI_SECTION
			: 'extension = ' . $db->Quote(FLEXI_CAT_EXTENSION);

		$query = 'SELECT *'
			//. ', CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END AS slug'
			. ', id AS value, title AS text'
			. ' FROM #__categories AS c'
			. (count($where) ? ' WHERE ' . implode( ' AND ', $where ) : '')
			. ' ORDER BY parent_id, lft';

		$db->setQuery($query);

		$rows = $db->loadObjectList();
		$rows = is_array($rows) ? $rows : array();

		//set depth limit, no detect loop ?
		$level_limit = $depth_limit ? $depth_limit : 99;

		//get children
		$children = array();
		foreach ($rows as $child)
		{
			$parent = $child->parent_id;
			$list = !empty($children[$parent])
				? $children[$parent]
				: array();
			array_push($list, $child);
			$children[$parent] = $list;
		}

		//get list of the items
		$root_catid = $parent_id ? $globalcats[$parent_id]->parent_id : 1;
		$list = flexicontent_cats::treerecurse($root_catid, '', array(), $children, true, max(0, $level_limit-1));

		return $list;
	}

	/**
	 * Utility Function:
    * Sorts and pads (indents) given categories according to their parent, thus creating a category tree by using recursion.
    * The sorting of categories is done by:
    * a. looping through all categories  v  in given children array padding all of category v with same padding
    * b. but for every category v that has a children array, it calling itself (recursion) in order to inject the children categories just bellow category v
    *
    * This function is based on the joomla 1.0 treerecurse
    *
    * @access public
    * @return array
    */
	static public function treerecurse( $parent_id, $indent, $list, &$children, $title, $maxlevel=9999, $level=0, $type=1, $ancestors=null )
	{
		$ROOT_CATEGORY_ID = 1;
		if (!$ancestors) $ancestors = array();

		if (!empty($children[$parent_id]) && $level <= $maxlevel)
		{
			foreach ($children[$parent_id] as $v)
			{
				$id = $v->id;

				if ((!in_array($v->parent_id, $ancestors)) && $v->parent_id != $ROOT_CATEGORY_ID)
				{
					$ancestors[] = $v->parent_id;
				}

				// Top level category (a child of ROOT)
				if (0)  // NOT needed ?
				{
					$pre    = '';
					$spacer = ' . ';
				}
				elseif ($type)
				{
					$pre    = '<sup>|_</sup> ';
					$spacer = ' . ';
				}
				else
				{
					$pre    = '- ';
					$spacer = ' . ';
				}

				if ($title)
				{
					$txt = $v->parent_id == $ROOT_CATEGORY_ID
						? '' . $v->title
						: $pre . $v->title;
				}
				else
				{
					$txt = $v->parent_id == $ROOT_CATEGORY_ID
						? ''
						: $pre;
				}

				$pt = $v->parent_id;
				$list[$id] = $v;
				$list[$id]->treename  = "$indent$txt";
				$list[$id]->title     = $v->title;
				$list[$id]->description    = $v->description;

				//$list[$id]->slug      = $v->slug;
				//$list[$id]->access    = $v->access;
				$list[$id]->ancestors = $ancestors;
				//$list[$id]->level     = $level + 1;
				$list[$id]->children  = !empty($children[$id]) ? count($children[$id]) : 0;
				$list[$id]->childrenarray = !empty($children[$id]) ? $children[$id] : null;

				$parent_id = $id;
				$list = flexicontent_cats::treerecurse(
					$parent_id, $indent . $spacer, $list, $children, $title, $maxlevel, $level+1, $type, $ancestors
				);
			}
		}
		return $list;
	}



	/**
	 * Build a html select form field that displays a Category Tree
	 *
	 * The output is filtered (via FLEXIaccess or via J1.6+ permission) and has disabled specific categories
	 * About Disabled categories:
	 * - currently edited category is disabled
	 * - if the user can view all categories then categories he has no permission are disabled !!!
	 *
	 * @param array $list
	 * @param string $name
	 * @param array $selected
	 * @param bool $top
	 * @param string $check_published
	 * @param string $check_perms
	 * @param string $require_all
	 * @param array $skip_subtrees
	 * @param array $disable_subtrees
	 * @param array $custom_options
	 * @param array $disable_specific_cats
	 * @param string $empty_errmsg
	 * @param boolean $show_viewable
	 * @param array $allowed_langs
	 *
	 * @return a category form field element
	 */
	public static function buildcatselect($list, $name, $selected, $top,
		$attribs = 'class="inputbox"', $check_published = false, $check_perms = true,
		$actions_allowed=array('core.create', 'core.edit', 'core.edit.own'),   // For item edit this should be array('core.create')
		$require_all=true,   // Require (or not) all privileges present to accept a category
		$skip_subtrees=array(), $disable_subtrees=array(), $custom_options=array(),
		$disable_specific_cats = array(), $empty_errmsg = false, $show_viewable = false,
		$allowed_langs = array()  // Filter by languages
	) {

		// ***
		// *** Initialize needed variables
		// ***

		global $globalcats;
		if (!$globalcats) $globalcats = array();

		$cparams = JComponentHelper::getParams('com_flexicontent');
		$user = JFactory::getUser();
		$app  = JFactory::getApplication();
		$controller = $app->input->get('controller', '', 'cmd');
		$task = $app->input->get('task', '', 'cmd');

		$print_logging_info = $cparams->get('print_logging_info');
		if ( $print_logging_info ) { global $fc_run_times; $start_microtime = microtime(true); }

		require_once (JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'permission.php');

		// Privilege of viewing all categories (even if not allowed to be used)
		$viewallcats	= FlexicontentHelperPerm::getPerm()->ViewAllCats;

		// Privelege of Super Admin
		$isSuperAdmin = $user->authorise('core.admin', 'root.1');

		// Access levels granted to current user
		$user_levels = array_flip(JAccess::getAuthorisedViewLevels($user->id));

		// Allowed category languages and allowed category states
		$langs_allowed     = array_flip($allowed_langs);
		$allowed_catstates = is_array($check_published) ? array_flip($check_published) : array();

		// Re-calculate check published flag (TODO remove ??)
		if (count($allowed_catstates) === 1 && isset($allowed_catstates[1]))
		{
			$allowed_catstates = array();
			$check_published   = true;
		}
		else
		{
			$check_published = !is_array($check_published) ? $check_published : false;
		}


		// ***
		// *** Find user allowed categories to be used during Filtering below
		// ***

		if ($check_perms)
		{
			// Get user allowed categories, NOTE: if user (a) (J2.5) has 'core.admin' or (b) (J1.5) user is super admin (gid==25) then all cats are allowed
			$usercats 	= FlexicontentHelperPerm::getAllowedCats($user, $actions_allowed, $require_all, $check_published);

			// NOTE: already selected categories will be allowed to the user, add them to the category list
			$selectedcats = !is_array($selected) ? array($selected) : $selected;
			$usercats_indexed = array_flip($usercats);

			if ($check_perms === 'edit')
			{
				foreach ($selectedcats as $selectedcat)
				{
					if ($selectedcat)
					{
						$usercats_indexed[$selectedcat] = 1;
					}
				}
			}
		}

		/**
		 * A reverse index of already used categories, so that we do not skip them
		 * when skipping categories based on their language and / or their state
		 */
		$selectedcats_indexed = array_flip(!is_array($selected) ? array($selected === NULL ? '' : $selected) : $selected);


		// ***
		// *** Excluded subtrees e.g. featured categories subtree in item form
		// *** Disabled subtrees e.g. existing children subtree when selecting category's parent
		// ***

		$skip_cats_arr = array();
		if ( !empty($skip_subtrees) )
		{
			foreach ($skip_subtrees as $subtree_rootid)
			{
				if ( $subtree_rootid && isset($globalcats[$subtree_rootid]->descendantsarray) )
				{
					foreach($globalcats[$subtree_rootid]->descendantsarray as $_excluded)  $skip_cats_arr[$_excluded] = 1;
				}
			}
		}

		$disable_cats_arr = array();
		if ( !empty($disable_subtrees) )
		{
			foreach ($disable_subtrees as $subtree_rootid)
			{
				if ( $subtree_rootid && isset($globalcats[$subtree_rootid]->descendantsarray) )
				{
					foreach($globalcats[$subtree_rootid]->descendantsarray as $_excluded)  $disable_cats_arr[$_excluded] = 1;
				}
			}
		}

		// Disable specific categories
		if ( !empty($disable_specific_cats) )
		{
			foreach ($disable_specific_cats as $_excluded)
			{
				$disable_cats_arr[$_excluded] = 1;
			}
		}


		// ***
		// *** TOP parameter: defines the APPROPRIATE PROMPT option at top of select list
		// ***

		$cats_count = 0;
		$catlist 	= array();

		// A tree to select: e.g. a parent category
		if (!is_numeric($top) && strlen($top))
		{
			$catlist[] 	= JHtml::_( 'select.option', '', $top );
		}

		else if ($top == 1)
		{
			$catlist[] 	= JHtml::_( 'select.option', 1, JText::_( 'FLEXI_TOPLEVEL' ));
		}

		// A tree to select a category
		else if($top == 2 || $top == -1)
		{
			$catlist[] 	= JHtml::_( 'select.option', '', JText::_( $top==-1 ? '' : 'FLEXI_SELECT_CATEGORY' ));
		}

		else if($top == 4)
		{
			$catlist[] 	= JHtml::_( 'select.option', '', '- ' . JText::_( 'FLEXI_DO_NOT_CHANGE' ) . ' -');
		}

		else if($top == 5)
		{
			$catlist[] 	= JHtml::_( 'select.option', '-99', '- ' . JText::_( 'FLEXI_TRY_ASSOCIATED_CATEGORY' ) . ' -');
		}

		// A sub-tree where root category of the sub-tree should be excluded, in place of it a disabled prompt is added ... NOTE that:
		// a subtree should be given or else the first category out of top level category will be removed, which is of little sense
		else if($top == 3)
		{
			// Backup first element's modified properties, so that we restore them later
			$first_item = reset($list); //$first_key = key($list);
			$_first_item_treename = $first_item->treename; $_first_item_title = $first_item->title; $_first_item_id = $first_item->id;
			$first_item->treename = $first_item->title = JText::_( 'FLEXI_SELECT_CATEGORY' );
			$first_item->id = "";
		}

		// Extra custom options ... applies to all top parameters
		foreach ($custom_options as $custom_value => $custom_option)
		{
			$catlist[] 	= JHtml::_( 'select.option', $custom_value, '-- '.JText::_( $custom_option ).' --');
		}


		/*
		 * Loop through categories to create the select option using user allowed categories (if filtering enabled)
		 */

		$cats_allowed = array();
		$cats_incestors_ancestors = array();

		foreach ($list as $cat)
		{
			if ( !$check_published || $cat->published )
			{
				// Check for SKIPPED categories e.g. featured categories subtree in item form
				if ( isset($skip_cats_arr[$cat->id]) )
				{
					continue;
				}

				// Category is ALLOWED (a) if it is a 'placeholder' entry (has empty CAT ID) or (b) if is Super Admin or (c) if NOT checking ACL or (d) if user has ACL permission or
				$allowed =
					!$cat->id ||      // (a) if it is a 'placeholder' entry (has empty CAT ID)
					$isSuperAdmin ||  // or (b) if is Super Admin
					!$check_perms ||  // or (c) if NOT checking ACL or (d) if user has ACL permission or
					isset( $usercats_indexed[$cat->id] );

				// If not allowed then check to DISABLE OR SKIP if user not having the view access level of the category
				$skipped = false;
				if ( !$allowed && !$viewallcats )
				{
					// Skip if category not viewable -OR- not showing viewable categories either
					if ( !isset($user_levels[$cat->access]) || !$show_viewable )
					{
						$skipped = true;
					}
				}

				// Check for DISABLED categories e.g. existing children subtree when selecting category's parent
				if ( isset($disable_cats_arr[$cat->id]) )
				{
					// NOTE: we did NOT add this CHECK to the ALLOWED check above !! If had added above then VIEW LEVEL checking may have skipped this category !!
					$allowed = false;
				}

				// Check for skipping categories not in allowed languages
				if ($langs_allowed && !isset($langs_allowed[$cat->language]) && !isset( $selectedcats_indexed[$cat->id] ))
				{
					$skipped = true;
					$allowed = false;
				}

				// Check for skipping categories not in allowed states
				if ($allowed_catstates && !isset($allowed_catstates[$cat->published]) && !isset( $selectedcats_indexed[$cat->id] ))
				{
					$skipped = true;
					$allowed = false;
				}

				$cats_allowed[$cat->id] = $allowed;
				if (!$skipped)
				{
					if (!empty($globalcats[$cat->id]->ancestorsarray))
					{
						foreach($globalcats[$cat->id]->ancestorsarray as $parent)
						{
							$cats_incestors_ancestors[$parent] = 1;
						}
					}
					$cats_incestors_ancestors[$cat->id] = 1;
				}
			}

			else
			{
				$cats_allowed[$cat->id] = false;
			}
		}

		// Category state suffixes
		$state_sfxs = array(1 => ' -P-', 0 => ' -U-', 2 => ' -A-', -2 => ' -T-', );

		if ($globalcats) foreach ($list as $cat)
		{
			if (isset($cats_incestors_ancestors[$cat->id]))
			{
				$cat_treename = str_replace("&nbsp;", " ", strip_tags($cat->treename));
				$cat_title = $cat_treename;

				// Add state suffix
				$cat_title .= (!$check_published && $cat->published != 1)
					? $state_sfxs[$cat->published]
					: '';

				// Add language suffix
				$cat_title .= $cat->language !== '*' && (
					count($langs_allowed) > 2 ||
					empty($langs_allowed) ||
					(count($langs_allowed) === 1 && !isset($allowed_catstates['*']))
				)
					? ' [' . $cat->language . ']'
					: '';

				$allowed = $cats_allowed[$cat->id];

				// Finally if category was not skipped ADD it as enabled or as disabled
				$parent_title = false;
				if ($cat->id)
				{
					$arr = $globalcats[$cat->id]->ancestorsarray;
					if (!empty($cat->description))
					{
						$parent_title = flexicontent_html::striptagsandcut($cat->description, 200);
					}
					else
					{
						$parent_title = count($arr) > 1 ? $globalcats[$arr[count($arr) - 2]]->title . '/' . $cat->title : '';
					}
					$parent_title = htmlspecialchars($parent_title, ENT_COMPAT, 'UTF-8');
				}

				//$catlist[] = JHtml::_( 'select.option', $cat->id, $cat_title, array( 'attr' => array('title' => $parent_title), 'disable' => false) );
				$catlist[] = (object) array(
					'value' => $cat->id,
					'text'  => $cat_title,
					'attr'  => ($parent_title ? array('data-title' => $parent_title) : null),
					'disable' => !$allowed
				);

				$cats_count++;
			}
		}


		/*
		 * Finally create the HTML form element
		 */

		$idtag = preg_replace('/(\]|\[)+/', '_', $name);
		$idtag = preg_replace('/_$/', '', $idtag);

		$sg_options = array(
			'id' => $idtag,
			'list.attr' => $attribs,
			'list.translate' => false,
			'option.key' => 'value',
			'option.text' => 'text',
			'option.attr' => 'attr',
			'list.select' => $selected
		);

		$html = $empty_errmsg && $cats_count==0 ?
			'<div class="alert alert-error">'.$empty_errmsg.'</div>' :
			JHtml::_('select.genericlist', $catlist, $name, $sg_options )  // $catlist, $name, $attribs, 'value', 'text', $selected, $idtag )
			;
		//echo '<pre>'; print_r($catlist); exit;

		// Restore first category element
		if ($top == 3)
		{
			$first_item = reset($list);
			$first_item->treename = $_first_item_treename;
			$first_item->title = $_first_item_title;
			$first_item->id = $_first_item_id ;
		}

		if ( $print_logging_info ) @$fc_run_times['render_categories_select'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		return $html;
	}


	/**
	 * Find and return extra parent/children/etc categories based on given criteria
	 *
	 * @param string $cids           the category ids for field object used as filter
	 * @param string $treeinclude    which categories to include
	 * @param string $curritemcats   categories of current item
	 * @return array                 an array of category ids
	 */
	public static function getExtraCats($cids, $treeinclude, $curritemcats)
	{
		global $globalcats;
		$app     = JFactory::getApplication();
		$user    = JFactory::getUser();
		$fparams = $app->getParams('com_flexicontent');
		$show_noauth = $fparams->get('show_noauth', 0);

		$all_cats = array();
		if ($treeinclude!=5) foreach ($cids as $cid)
		{
			if ($cid) $all_cats[] = $cid;
		}

		foreach ($cids as $cid)
		{
			if (!$cid) continue;
			$cats = array();
			switch ($treeinclude) {
				// current category only
				case 0: default:
					$cats = array($cid);
				break;
				case 5: // children only
					$cats = $globalcats[$cid]->descendantsarray;
					array_shift($cats);  // First category is the category itself
				break;
				case 1: // current category + children
					$cats = $globalcats[$cid]->descendantsarray;
				break;
				case 2: // current category + parents
					$cats = $globalcats[$cid]->ancestorsarray;
				break;
				case 3: // current category + children + parents
					$cats = array_unique(array_merge($globalcats[$cid]->descendantsarray, $globalcats[$cid]->ancestorsarray));
				break;
				case 4: // all item's categories
					$cats = $curritemcats;
				break;
			}
			$all_cats = array_merge($all_cats, $cats);
		}
		if ( empty($all_cats) ) return array();

		// Select only categories that user has view access, if listing of unauthorized content is not enabled
		$joinaccess = '';
		$andaccess = '';
		if (!$show_noauth) {
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$andaccess .= ' AND c.access IN ('.$aid_list.')';
		}

		// Filter categories (check that are published and that have ACCESS Level that is assinged to current user)
		$db = JFactory::getDbo();
		$query = 'SELECT DISTINCT c.id'
			.' FROM #__categories AS c'
			.$joinaccess
			.' WHERE c.id IN ('.implode(',', $all_cats).') AND c.published = 1'
			.$andaccess
			;

		$published_cats = $db->setQuery($query)->loadColumn();

		return array_unique($published_cats);
	}

}
?>