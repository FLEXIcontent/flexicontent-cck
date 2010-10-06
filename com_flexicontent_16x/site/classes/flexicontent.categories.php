<?php
/**
 * @version 1.5 stable $Id: flexicontent.categories.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
	var $parentcats = array();
	
	var $category = array();
	
	/**
	 * Constructor
	 *
	 * @param int $cid
	 * @return flexicontent_categories
	 */
	function flexicontent_cats($cid)
	{
		$this->id = $cid;
		$this->buildParentCats($this->id);
		$this->getParentCats();
	}
    
	function getParentCats()
	{
		$db			=& JFactory::getDBO();
		
		$this->parentcats = array_reverse($this->parentcats);
				
		foreach($this->parentcats as $cid) {
			
			$query = 'SELECT id, title,'
					.' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', id, alias) ELSE id END as categoryslug'
					.' FROM #__categories'
					.' WHERE id ='. (int)$cid 
					.' AND section = ' . FLEXI_SECTION
					.' AND published = 1'
					;
			$db->setQuery($query);
			$this->category[] 	= $db->loadObject();
		}
	}
	
	function buildParentCats($cid)
	{
		$db 		=& JFactory::getDBO();
		
		$query = 'SELECT parent_id FROM #__categories WHERE id = '.(int)$cid. ' AND section = ' . FLEXI_SECTION;
		$db->setQuery( $query );

		if($cid != 0) {
			array_push($this->parentcats, $cid);
		}

		//if we still have results
		if(sizeof($db->loadResult()) != 0) {
			$this->buildParentCats($db->loadResult());
		}
	}
	
	function getParentlist()
	{
		return $this->category;
	}
	
	/**
    * Get the categorie tree
    *
    * @return array
    */
	function getCategoriesTree( $published=null )
	{
		$db			=& JFactory::getDBO();
		
		if ($published) {
			$where[] = 'published = 1';
			$where[] = 'section = ' . FLEXI_SECTION;
		} else {
			$where[] = 'section = ' . FLEXI_SECTION;
		}

		$where 		= ( count( $where ) ? ' WHERE ' . implode( ' AND ', $where ) : '' );
		
		$query = 'SELECT *, id AS value, title AS text'
				.' FROM #__categories'
				.$where
				.' ORDER BY parent_id, ordering'
				;

		$db->setQuery($query);

		$rows = $db->loadObjectList();
		
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
    	$list = flexicontent_cats::treerecurse(0, '', array(), $children, true, max(0, $levellimit-1));

		return $list;
	}
	
	/**
    * Get the categorie tree
    * based on the joomla 1.0 treerecurse 
    *
    * @access public
    * @return array
    */
	function treerecurse( $id, $indent, $list, &$children, $title, $maxlevel=9999, $level=0, $type=1, $ancestors=null, $childs=null )
	{
		if (!$ancestors) $ancestors = array();
		
		if (@$children[$id] && $level <= $maxlevel) {
			foreach ($children[$id] as $v) {
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
					if ( $v->parent_id == 0 ) {
						$txt    = ''.$v->title;
					} else {
						$txt    = $pre.$v->title;
					}
				} else {
					if ( $v->parent_id == 0 ) {
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
	 * Build Categories select list
	 *
	 * @param array $list
	 * @param string $name
	 * @param array $selected
	 * @param bool $top
	 * @param string $class
	 * @return void
	 */
	function buildcatselect($list, $name, $selected, $top, $class = 'class="inputbox"', $published = false, $filter = true)
	{
		$user =& JFactory::getUser();
		$cid = JRequest::getVar('cid');

		if (FLEXI_ACCESS) {
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
			if ((!$published) || ($published && $item->published)) {
				if ((JRequest::getVar('controller') == 'categories') && (JRequest::getVar('task') == 'edit') && ($cid[0] == $item->id)) {
					$catlist[] = JHTML::_( 'select.option', $item->id, $item->treename, 'value', 'text', true );
				} else if ($filter) {
					if (FLEXI_ACCESS && (!in_array($item->id, $usercats)) && ($user->gid < 25)) {
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
		return JHTML::_('select.genericlist', $catlist, $name, $class, 'value', 'text', $selected );
	}
}
?>