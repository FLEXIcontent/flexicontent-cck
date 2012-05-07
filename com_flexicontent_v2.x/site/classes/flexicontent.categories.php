<?php
/**
 * @version 1.5 stable $Id: flexicontent.categories.php 1169 2012-03-09 04:17:19Z ggppdk $
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
		$db			=& JFactory::getDBO();
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
		$db 		=& JFactory::getDBO();

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
	function getCategoriesTree( $published=null )
	{
		$db			=& JFactory::getDBO();
		
		if ($published) {
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
		
		//set depth limit
		$levellimit = 10;
		
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
	 * @param string $class
	 * @return void
	 */
	function buildcatselect($list, $name, $selected, $top,
		$class = 'class="inputbox"', $published = false, $filter = true,
		$actions_allowed=array('core.create', 'core.edit', 'core.edit.own')   // For item edit this should be array('core.create')
	)
	{
		$user =& JFactory::getUser();
		$cid = JRequest::getVar('cid');
		
		if (FLEXI_J16GE) {
			require_once (JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'permission.php');
			$usercats 		= FlexicontentHelperPerm::getCats($actions_allowed, $require_all=true);
			$viewallcats	= FlexicontentHelperPerm::getPerm()->CanUserCats;
			$viewtree			= FlexicontentHelperPerm::getPerm()->CanViewTree;
		} else if (FLEXI_ACCESS) {
			$usercats 		= FAccess::checkUserCats($user->gmid);
			$viewallcats 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'usercats', 'users', $user->gmid) : 1;
			$viewtree 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'cattree', 'users', $user->gmid) : 1;
		} else {
			$viewallcats	= 1;
			$viewtree		= 1;
		}

		$catlist 	= array();
		
		if($top == 1) {
			$catlist[] 	= JHTML::_( 'select.option', '', JText::_( 'FLEXI_TOPLEVEL' ));
		} else if($top == 2) {
			$catlist[] 	= JHTML::_( 'select.option', '', JText::_( 'FLEXI_SELECT_CAT' ));
		}
		
		foreach ($list as $item) {
			$item->treename = str_replace("&nbsp;", " ", strip_tags($item->treename));
			if ((!$published) || ($published && $item->published)) {
				if ((JRequest::getVar('controller') == 'categories') && (JRequest::getVar('task') == 'edit') && ($cid[0] == $item->id)) {
					$catlist[] = JHTML::_( 'select.option', $item->id, $item->treename, 'value', 'text', true );
				} else if ($filter) {
					$asset = 'com_content.category.'.$item->id;
					if (
					(FLEXI_J16GE && !in_array($item->id, $usercats) ) ||  // if user has 'core.admin' then all cats are allowed
					(FLEXI_ACCESS && !in_array($item->id, $usercats) && ($user->gid < 25))
					) {
						if ($viewallcats) { // only disable cats in the list else don't show them at all
							$catlist[] = JHTML::_( 'select.option', $item->id, $item->treename, 'value', 'text', true );
						}
					} else {
						// FLEXIaccess rule $viewtree enables tree view
						$catlist[] = JHTML::_( 'select.option', $item->id, ($viewtree ? $item->treename : $item->title) );
					}
				} else {
					$catlist[] = JHTML::_( 'select.option', $item->id, $item->treename );
				}
			}
		}
		$idtag = preg_replace('/(\]|\[)+/', '_', $name);
		$idtag = preg_replace('/_$/', '', $idtag);
		return JHTML::_('select.genericlist', $catlist, $name, $class, 'value', 'text', $selected, $idtag );
	}
	
	
	/**
	 * Retrieves all available values of the given field,
	 * that are used by any item visible via current category filtering (search box, alpha-index, filters, language, etc)
	 *
	 *
	 * @param string $filter			the field object used as filter
	 * @param string $force				controls whether to force only available values, ('all', 'limit', any other value uses the category configuration)
	 * @return array							the available values
	 */
	function getFilterValues (&$filter, $force='default') {
		
		global $currcat_data;
		$db =& JFactory::getDBO();
		
		if ($force=='all') $limit_filter_values=0;
		else if ($force=='limit') $limit_filter_values=1;
		else $limit_filter_values = $currcat_data['params']->get('limit_filter_values', 0);
		
		$query = 'SELECT DISTINCT fi.value as value, fi.value as text'
		. ' FROM #__content AS i'
		. ' LEFT JOIN #__flexicontent_fields_item_relations AS fi ON i.id = fi.item_id'
		. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
		. ' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
		. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
		. ' LEFT JOIN #__categories AS c ON c.id = i.catid'
		. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
		. ($limit_filter_values ? $currcat_data['where'] : ' WHERE 1=1 ')
		. ' AND fi.field_id ='.$filter->id;
		;
		
		//echo $query;
		// Make sure there aren't any errors
		$db->setQuery($query);
		$results = $db->loadObjectList('value');
		if ($db->getErrorNum()) {
			JError::raiseWarning($db->getErrorNum(), $db->getErrorMsg(). "<br /><br />" .$query);
			$filter->html	 = "Filter for : $field->label cannot be displayed, error during db query<br />";
			return array();
		}
		
		return $results;
	}
}
?>