<?php
/**
 * @version 1.5 stable $Id: flexicontent.categories.php 1629 2013-01-19 08:45:07Z ggppdk $
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
	var $parentcats_ids  = array();  // ids of ancestors categories, populated by buildParentCats(), used by getParentCats()
	
	var $parentcats_data = array();  // data (id,title,categoryslug) of ancestors categories, populated by getParentCats() and accessed via getParentlist()
	
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
	function flexicontent_cats($cid)
	{
		$this->id = $cid;
		$this->buildParentCats($this->id);	// Retrieves ids of ancestors categories and set them in member array variable 'parentcats_ids'
		$this->getParentCats();							// Get basic data for ancestors (id,title,categoryslug) and set them in member array variable 'category'
	}
    
	/**
	 * Retrieves parent categories (anscestors) of category until the category
	 * and sets this parent in the member variable 'parentcats_ids'
	 *
	 */
	function getParentCats()
	{
		$db = JFactory::getDBO();
		global $globalnoroute;
		$globalnoroute = !is_array($globalnoroute) ? array() : $globalnoroute;
		
		$this->parentcats_ids = array_reverse($this->parentcats_ids);
				
		foreach($this->parentcats_ids as $cid) {
			
			$query = 'SELECT id, title,'
					.' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', id, alias) ELSE id END as categoryslug'
					.' FROM #__categories'
					.' WHERE id ='. $db->Quote((int)$cid)
					. (!FLEXI_J16GE ? ' AND section = '.FLEXI_SECTION : ' AND extension="'.FLEXI_CAT_EXTENSION.'" ' )
					.' AND published = 1'
					;
			$db->setQuery($query);
			$cat = $db->loadObject();
			if ($cat && !in_array($cat->id, $globalnoroute) )	$this->parentcats_data[] = $cat;
		}
	}
	
	/**
	 * Retrieves parent categories (anscestors) of category until the category
	 * and sets this parent in the member variable 'parentcats_ids'
	 *
	 */
	function buildParentCats($cid)
	{
		$db = JFactory::getDBO();
		
		$query = 'SELECT parent_id FROM #__categories WHERE id = '.(int)$cid
			. (!FLEXI_J16GE ? ' AND section = '.FLEXI_SECTION : ' AND extension="'.FLEXI_CAT_EXTENSION.'" ' );
		
		$db->setQuery( $query );
		$parents[$cid] = $db->loadResult();

		array_push($this->parentcats_ids, $cid);

		//if we still have results
		if ( (!FLEXI_J16GE && $parents[$cid] > 0 )  ||  (FLEXI_J16GE && $parents[$cid] > 1) ) {
			$this->buildParentCats($parents[$cid]);
		}
	}
	
	/*
	 * Returns the parent category array that was build by functions buildParentCats() and getParentCats(), which were by constructor for category cid
	 *
	 */
	function getParentlist()
	{
		return $this->parentcats_data;
	}
	
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
	function getCategoriesTree( $published_only=null )
	{
		$db = JFactory::getDBO();
		
		if ($published_only) {
			$where[] = 'published = 1';
		}
		$where[] = (!FLEXI_J16GE ? 'section = '.FLEXI_SECTION : 'extension="'.FLEXI_CAT_EXTENSION.'"' );

		$where 		= ( count( $where ) ? ' WHERE ' . implode( ' AND ', $where ) : '' );
		
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
		$levellimit = 99;
		
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
		$list = flexicontent_cats::treerecurse($ROOT_CATEGORY_ID, '', array(), $children, true, max(0, $levellimit-1));

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
	function treerecurse( $parent_id, $indent, $list, &$children, $title, $maxlevel=9999, $level=0, $type=1, $ancestors=null, $childs=null )
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
	 *
	 * @return a category form field element
	 */
	function buildcatselect($list, $name, $selected, $top,
		$class = 'class="inputbox"', $check_published = false, $check_perms = true,
		$actions_allowed=array('core.create', 'core.edit', 'core.edit.own'),   // For item edit this should be array('core.create')
		$require_all=true   // Require (or not) all privileges present to accept a category
	)
	{
		global $globalcats;
		$user = JFactory::getUser();
		$cid = JRequest::getVar('cid',  0);
		if (is_array($cid)) $cid = $cid[0];
		
		// Privilege of (a) viewing all categories (even if disabled) and (b) viewing as a tree
		require_once (JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'permission.php');
		$viewallcats	= FlexicontentHelperPerm::getPerm()->ViewAllCats;
		$viewtree			= FlexicontentHelperPerm::getPerm()->ViewTree;
		
		// Global parameter to force always displaying of categories as tree
		if (JComponentHelper::getParams('com_flexicontent')->get('cats_always_astree', 1)) {
			$viewtree = 1;
		}
		
		// Filter categories by user permissions
		if ($check_perms) {
			if (FLEXI_J16GE || FLEXI_ACCESS) {
				// Get user allowed categories, NOTE: if user (a) (J2.5) has 'core.admin' or (b) (J1.5) user is super admin (gid==25) then all cats are allowed
				$usercats 	= FlexicontentHelperPerm::getAllowedCats($user, $actions_allowed, $require_all, $check_published);
				// Add already selected categories to the category list
				$selectedcats = !is_array($selected) ? array($selected) : $selected;
				$usercats = array_unique(array_merge($selectedcats, $usercats));
			}
		}
		
		// Start category list by add appropriate prompt option at top
		$catlist 	= array();
		if($top == 1) {
			$catlist[] 	= JHTML::_( 'select.option', FLEXI_J16GE ? 1 : 0, JText::_( 'FLEXI_TOPLEVEL' ));
		} else if($top == 2) {
			$catlist[] 	= JHTML::_( 'select.option', '', JText::_( 'FLEXI_SELECT_CAT' ));
		}
		
		// Loop through categories to create the select option using user allowed categories (if filtering enabled)
		foreach ($list as $cat) {
			$cat->treename = str_replace("&nbsp;", " ", strip_tags($cat->treename));
			$cat_title = $viewtree ? $cat->treename : $cat->title;
			
			if ( !$check_published || $cat->published )
			{	
				// CASE 1. IN CATEGORY EDIT FORM while displaying FORM FIELD parent_id, using current category and its children should be disabled as disabled
				if ( JRequest::getVar('controller') == 'categories' && JRequest::getVar('task') == 'edit' && $cid
							&& $top==1 && ( $cid == $cat->id || in_array($cat->id, $globalcats[$cid]->descendantsarray ) )
				) {
					$catlist[] = JHTML::_( 'select.option', $cat->id, $cat_title, 'value', 'text', true );
				}
				
				// CASE 2: ADD only if user has permissions
				else if ($check_perms)
				{
					// a. Category NOT ALLOWED
					if (	( FLEXI_J16GE && !in_array($cat->id, $usercats) ) || ( FLEXI_ACCESS && !in_array($cat->id, $usercats) ) )
					{
						// Add current category to the select list as disabled if user can view all categories, OTHERWISE DO NOT ADD IT
						if ($viewallcats)
							$catlist[] = JHTML::_( 'select.option', $cat->id, $cat_title, 'value', 'text', $disabled = true );
					}
										
					// b. Category ALLOWED
					else
					{
						$catlist[] = JHTML::_( 'select.option', $cat->id, $cat_title );
					}
				}
				
				// CASE 3: ADD REGARDLESS of user permissions
				else
				{
					$catlist[] = JHTML::_( 'select.option', $cat->id, $cat_title );
				}
				
			}
		}
		
		// Finally create the HTML form element
		$replace_char = FLEXI_J16GE ? '_' : '';
		$idtag = preg_replace('/(\]|\[)+/', $replace_char, $name);
		$idtag = preg_replace('/_$/', '', $idtag);
		return JHTML::_('select.genericlist', $catlist, $name, $class, 'value', 'text', $selected, $idtag );
	}
	
	
	/**
	 * Find and return extra parent/children/etc categories of given categories
	 *
	 * @param string $cids		the category ids for field object used as filter
	 * @param string $force				controls whether to force only available values, ('all', 'limit', any other value uses the category configuration)
	 * @return array							the available values
	 */

	function getExtraCats($cids, $treeinclude, $curritemcats)
	{
		global $globalcats;
		
		if ( $treeinclude==0 ) {
			// Only given categories, nothing more to do 
			return $cids;
		} else if ( $treeinclude == 4 ) {
			// Also include current item's categories
			$all_cats = array_merge($all_cats, $curritemcats);
			return $all_cats;
		} else {
			// other cases, we will need to examine every given category
			$all_cats = $cids;
		}
		
		// Examine every given category, and include appropriate related categories
		foreach ($cids as $cid)
		{
			$cats = array();
			switch ($treeinclude) {
				case 1: // current category + children
					$cats = $globalcats[$cid]->descendantsarray;
					break;
				case 2: // current category + parents
					$cats = $globalcats[$cid]->ancestorsarray;
					break;
				case 3: // current category + children + parents
					$cats = array_unique(array_merge($globalcats[$cid]->descendantsarray, $globalcats[$cid]->ancestorsarray));						
					break;
				default: // other cases UNKNOWN cases, just do not add any other categories
					break;
			}
			$all_cats = array_merge($all_cats, $cats);
		}
		return array_unique($all_cats);
	}

}
?>