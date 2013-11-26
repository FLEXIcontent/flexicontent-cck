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
	public function flexicontent_cats($cid)
	{
		$this->id = $cid;
	}
    
	/**
	 * Retrieves parent categories (anscestors) of category until the category
	 * and sets this parent in the member variable 'parentcats_ids'
	 *
	 */
	protected function getParentCats()
	{
		$db = JFactory::getDBO();
		
		$query = 'SELECT id, title, published,'
				.' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', id, alias) ELSE id END as categoryslug'
				.' FROM #__categories'
				.' WHERE id IN ('.implode($this->parentcats_ids, ',').')'
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
		$db = JFactory::getDBO();
		
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
		 .' ORDER BY cat.level ASC';
		
		$db->setQuery( $query );
		$this->parentcats_ids = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
		if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');*/
		
		global $globalcats;
		$this->parentcats_ids = $globalcats[$cid]->ancestorsarray;
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
		$db = JFactory::getDBO();
		
		if ($published_only) {
			$where[] = 'published = 1';
		}
		if ( $parent_id && isset($globalcats[(int)$parent_id]) ) {
			// Limit category list to those contain in the subtree of the choosen category
			$where[] = 'id IN (' . $globalcats[(int)$parent_id]->descendants . ')';
		}
		$where[] = !FLEXI_J16GE ? 'section = '.FLEXI_SECTION : 'extension='.$db->Quote(FLEXI_CAT_EXTENSION);
		
		$where = count($where) ? ' WHERE ' . implode( ' AND ', $where ) : '';
		
		$query = 'SELECT *, id AS value, title AS text'
				.' FROM #__categories'
				.$where
				.' ORDER BY parent_id '
				. ( !FLEXI_J16GE ? ', ordering' : ', lft' )
				;

		$db->setQuery($query);

		$rows = $db->loadObjectList();
		$rows = is_array($rows)?$rows:array();
		
		//set depth limit, no detect loop ?
		$level_limit = $depth_limit ? $depth_limit : 99;
		
		//get children
		$children = array();
		foreach ($rows as $child) {
			$parent = $child->parent_id;
			$list = @$children[$parent] ? $children[$parent] : array();
			array_push($list, $child);
			$children[$parent] = $list;
		}
		
		//get list of the items
		$ROOT_CATEGORY_ID = !FLEXI_J16GE ? 0 : 1;
		$list = flexicontent_cats::treerecurse($ROOT_CATEGORY_ID, '', array(), $children, true, max(0, $level_limit-1));

		return $list;
	}
	
	/**
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
	public static function treerecurse( $parent_id, $indent, $list, &$children, $title, $maxlevel=9999, $level=0, $type=1, $ancestors=null, $childs=null )
	{
		if (!$ancestors) $ancestors = array();
		$ROOT_CATEGORY_ID = !FLEXI_J16GE ? 0 : 1;
		
		if (@$children[$parent_id] && $level <= $maxlevel) {
			foreach ($children[$parent_id] as $v) {
				$id = $v->id;
				
				if ((!in_array($v->parent_id, $ancestors)) && $v->parent_id != 0) {
					$ancestors[] = $v->parent_id;
				} 
				
				if ( $type ) {
					$pre    = '<sup>|_</sup>&nbsp;';
					$spacer = '.&nbsp;&nbsp;&nbsp;';
				} else {
					$pre    = '- ';
					$spacer = '&nbsp;&nbsp;';
				}

				if ($title) {
					if ( $v->parent_id == $ROOT_CATEGORY_ID ) {
						$txt    = ''.$v->title;
					} else {
						$txt    = $pre.$v->title;
					}
				} else {
					if ( $v->parent_id == $ROOT_CATEGORY_ID ) {
						$txt    = '';
					} else {
						$txt    = $pre;
					}
				}
				$pt = $v->parent_id;
				$list[$id] = $v;
				$list[$id]->treename 		= "$indent$txt";
				$list[$id]->ancestors 		= $ancestors;
				$list[$id]->childrenarray 	= @$children[$id];
				$list[$id]->children 		= count( @$children[$id] );

				$list = flexicontent_cats::treerecurse( $id, $indent . $spacer, $list, $children, $title, $maxlevel, $level+1, $type, $ancestors, $childs );
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
	 *
	 * @return a category form field element
	 */
	public static function buildcatselect($list, $name, $selected, $top,
		$attribs = 'class="inputbox"', $check_published = false, $check_perms = true,
		$actions_allowed=array('core.create', 'core.edit', 'core.edit.own'),   // For item edit this should be array('core.create')
		$require_all=true,   // Require (or not) all privileges present to accept a category
		$skip_subtrees=array(), $disable_subtrees=array(), $custom_options=array()
	) {
		
		// ***************************
		// Initialize needed variables
		// ***************************
		
		global $globalcats;
		$cparams = JComponentHelper::getParams('com_flexicontent');
		$user = JFactory::getUser();
		$controller = JRequest::getVar('controller');
		$task = JRequest::getVar('task');
		
		$print_logging_info = $cparams->get('print_logging_info');
		if ( $print_logging_info ) { global $fc_run_times; $start_microtime = microtime(true); }
		
		// Privilege of (a) viewing all categories (even if disabled) and (b) viewing as a tree
		require_once (JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'permission.php');
		$viewallcats	= FlexicontentHelperPerm::getPerm()->ViewAllCats;
		$viewtree			= FlexicontentHelperPerm::getPerm()->ViewTree;
		
		// Global parameter to force always displaying of categories as tree
		if ($cparams->get('cats_always_astree', 1)) $viewtree = 1;
		
		
		// **************************************************************
		// Find user allowed categories to be used during Filtering below
		// **************************************************************
		
		if ($check_perms) {
			if (FLEXI_J16GE || FLEXI_ACCESS) {
				// Get user allowed categories, NOTE: if user (a) (J2.5) has 'core.admin' or (b) (J1.5) user is super admin (gid==25) then all cats are allowed
				$usercats 	= FlexicontentHelperPerm::getAllowedCats($user, $actions_allowed, $require_all, $check_published);
				// NOTE: already selected categories will be allowed to the user, add them to the category list
				$selectedcats = !is_array($selected) ? array($selected) : $selected;
				$usercats_indexed = array_flip($usercats);
				foreach ($selectedcats as $selectedcat) if ($selectedcat) $usercats_indexed[$selectedcat] = 1;
			}
		}
		
		
		// *********************************************************************************
		// Excluded subtrees e.g. featured categories subtree in item form
		// Disabled subtrees e.g. existing children subtree when selecting category's parent
		// *********************************************************************************
		
		$skip_cats_arr = array();
		if ( !empty($skip_subtrees) ) {
			foreach ($skip_subtrees as $subtree_rootid) {
				if ( $subtree_rootid && isset($globalcats[$subtree_rootid]->descendantsarray) ) {
					foreach($globalcats[$subtree_rootid]->descendantsarray as $_excluded)  $skip_cats_arr[$_excluded] = 1;
				}
			}
		}
		
		$disable_cats_arr = array();
		if ( !empty($disable_subtrees) ) {
			foreach ($disable_subtrees as $subtree_rootid) {
				if ( $subtree_rootid && isset($globalcats[$subtree_rootid]->descendantsarray) ) {
					foreach($globalcats[$subtree_rootid]->descendantsarray as $_excluded)  $disable_cats_arr[$_excluded] = 1;
				}
			}
		}
		
		
		// **************************************************************************
		// TOP parameter: defines the APPROPRIATE PROMPT option at top of select list
		// **************************************************************************
		
		$catlist 	= array();
		// A tree to select: e.g. a parent category
		if ($top == 1) {
			$catlist[] 	= JHTML::_( 'select.option', FLEXI_J16GE ? 1 : 0, JText::_( 'FLEXI_TOPLEVEL' ));
		}
		
		// A tree to select a category
		else if($top == 2) {
			$catlist[] 	= JHTML::_( 'select.option', '', JText::_( 'FLEXI_SELECT_CAT' ));
		}
		
		// A sub-tree where root category of the sub-tree should be excluded, in place of it a disabled prompt is added ... NOTE that:
		// a subtree should be given or else the first category out of top level category will be removed, which is of little sense
		else if($top == 3) {
			$first_item = reset($list); //$first_key = key($list);
			$_first_item_treename = $first_item->treename; $_first_item_title = $first_item->title; $_first_item_id = $first_item->id;
			$first_item->treename = $first_item->title = JText::_( 'FLEXI_SELECT_CAT' );
			$first_item->id = "";
		}
		
		// Extra custom options ... applies to all top parameters
		foreach ($custom_options as $custom_value => $custom_option) {
			$catlist[] 	= JHTML::_( 'select.option', $custom_value, '-- '.JText::_( $custom_option ).' --');
		}
		
		
		// ********************************************************************************************************
		// Loop through categories to create the select option using user allowed categories (if filtering enabled)
		// ********************************************************************************************************
		
		foreach ($list as $cat) {
			$cat->treename = str_replace("&nbsp;", " ", strip_tags($cat->treename));
			$cat_title = $viewtree ? $cat->treename : $cat->title;
			if (!$check_published && $cat->published!=1) $cat_title .= ' --U--';
			
			if ( !$check_published || $cat->published )
			{	
				// CASE 1: SKIPPED categories e.g. featured categories subtree in item form
				if ( isset($skip_cats_arr[$cat->id]) ) ;
				
				// CASE 2: ADD only if user has permissions
				else if ($check_perms)
				{
					// a. Category NOT ALLOWED
					if (	( FLEXI_J16GE || FLEXI_ACCESS) && !isset($usercats_indexed[$cat->id]) )
					{
						// Add current category to the select list as disabled if user can view all categories, OTHERWISE DO NOT ADD IT
						if ($viewallcats)
							$catlist[] = JHTML::_( 'select.option', $cat->id, $cat_title, 'value', 'text', $disabled = true );
					}
										
					// b. Category ALLOWED, but check if adding as disabled
					else
					{
						// CASE: DISABLED categories e.g. existing children subtree when selecting category's parent
						if ( isset($disable_cats_arr[$cat->id]) )
							$catlist[] = JHTML::_( 'select.option', $cat->id, $cat_title, 'value', 'text', $disabled = true );
						else
							$catlist[] = JHTML::_( 'select.option', $cat->id, $cat_title );
					}
				}
				
				// CASE 4: ADD REGARDLESS of user permissions
				else
				{
					$catlist[] = JHTML::_( 'select.option', $cat->id, $cat_title );
				}
				
			}
		}
		
		
		// ************************************
		// Finally create the HTML form element
		// ************************************
		
		$replace_char = FLEXI_J16GE ? '_' : '';
		$idtag = preg_replace('/(\]|\[)+/', $replace_char, $name);
		$idtag = preg_replace('/_$/', '', $idtag);
		$html = JHTML::_('select.genericlist', $catlist, $name, $attribs, 'value', 'text', $selected, $idtag );
		
		if ($top == 3) { // Restore first category element
			$first_item = reset($list); 
			$first_item->treename = $_first_item_treename; $first_item->title = $_first_item_title; $first_item->id = $_first_item_id ;
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
		
		$all_cats = $cids;
		foreach ($cids as $cid)
		{
			$cats = array();
			switch ($treeinclude) {
				// current category only
				case 0: default: 
					$cats = array($cid);
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
		
		// Select only categories that user has view access, if listing of unauthorized content is not enabled
		$joinaccess = '';
		$andaccess = '';
		if (!$show_noauth) {
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				$andaccess .= ' AND c.access IN ('.$aid_list.')';
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gc ON c.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"';
					$andaccess  .= ' AND (gc.aro IN ( '.$user->gmid.' ) OR c.access <= '. $aid . ')';
				} else {
					$andaccess  .= ' AND c.access <= '.$aid;
				}
			}
		}
		
		// Filter categories (check that are published and that have ACCESS Level that is assinged to current user)
		$db = JFactory::getDBO();
		$query = 'SELECT DISTINCT c.id'
			.' FROM #__categories AS c'
			.$joinaccess
			.' WHERE c.id IN ('.implode(',', $all_cats).') AND c.published = 1'
			.$andaccess
			;
		$db->setQuery($query);
		$published_cats = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
		if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
		
		return array_unique($published_cats);
	}

}
?>