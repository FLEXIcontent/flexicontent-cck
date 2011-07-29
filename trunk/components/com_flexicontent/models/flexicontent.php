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

jimport('joomla.application.component.model');

/**
 * FLEXIcontent Component Model
 *
 * @package Joomla
 * @subpackage Flexicontent
 * @since		1.0
 */
class FlexicontentModelFlexicontent extends JModel
{
	/**
	 * Data
	 *
	 * @var object
	 */
	var $_data = null;
	
	/**
	 * total
	 *
	 * @var int
	 */
	var $_total = null;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();

		global $mainframe;

		// Get the paramaters of the active menu item
		$params = & $mainframe->getParams('com_flexicontent');
		
		//set limits
		$limit 			= $params->def('catlimit', 5);
		$limitstart		= JRequest::getInt('limitstart');

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

	}
	
	/**
	 * Method to get Data
	 *
	 * @access public
	 * @return object
	 */
	function getData()
	{
		// Lets load the categories if it doesn't already exist
		if (empty($this->_data))
		{
			//get data
			$this->_data = $this->_getList( $this->_buildQuery(), $this->getState('limitstart'), $this->getState('limit') );
			
			//add childs of each category
			$k = 0;
			$count = count($this->_data);
			for($i = 0; $i < $count; $i++)
			{
				$category 			=& $this->_data[$i];
				$category->subcats	= $this->_getsubs( $category->id );

				$k = 1 - $k;
			}
		}

		return $this->_data;
	}
	
	/**
	 * Method to build the Categories query
	 * This method creates the first level categories and their assigned item count
	 *
	 * @access private
	 * @return string
	 */
	function _buildQuery()
	{
		global $mainframe;

		$user 		= &JFactory::getUser();
		$gid		= (int) $user->get('aid');
		$ordering	= 'c.ordering ASC';
		// Get the components parameters
		$params 	=& $mainframe->getParams('com_flexicontent');
		// Get the root category from this directory
		$rootcat = JRequest::getVar('rootcat',false);
		if(!$rootcat) $rootcat = $params->get('rootcat',0);
		// Shortcode of the site active language (joomfish)
		$lang 		= JRequest::getWord('lang', '' );
		if(empty($lang)){
			$langFactory= JFactory::getLanguage();
			$tagLang = $langFactory->getTag();
			//Well, the substr is not even required as flexi saves the Joomla language tag... so we could have kept the $tagLang tag variable directly.
			$lang = substr($tagLang ,0,2);
		}

		// Do we filter the categories
		$filtercat  = $params->get('filtercat', 0);
		// show unauthorized items
		$show_noauth = $params->get('show_noauth', 0);

		// Build where clause
		$where  = ' WHERE cc.published = 1';
		$where .= ' AND c.id = cc.id';
		// Filter the category view with the active active language
		if (FLEXI_FISH && $filtercat) {
			$where .= ' AND ie.language LIKE ' . $this->_db->Quote( $lang .'%' );
		}

		$states = ((int)$user->get('gid') > 19) ? '1, -5, 0, -3, -4' : '1, -5';
		$where .= ' AND i.state IN ('.$states.')';
		
		// Select only items user has access to if he is not allowed to show unauthorized items
		$subjoin 	= '';
		$suband 	= '';
		$join 		= '';
		$and 		= '';
		if (!$show_noauth) {
			if (FLEXI_ACCESS) {
				$subjoin  = ' LEFT JOIN #__flexiaccess_acl AS sgc ON cc.id = sgc.axo AND sgc.aco = "read" AND sgc.axosection = "category"';
				$subjoin .= ' LEFT JOIN #__flexiaccess_acl AS sgi ON i.id = sgi.axo AND sgi.aco = "read" AND sgi.axosection = "item"';
				$suband	  = ' AND (sgc.aro IN ( '.$user->gmid.' ) OR cc.access <= '. (int)$gid . ')';
				$suband  .= ' AND (sgi.aro IN ( '.$user->gmid.' ) OR i.access <= '. (int)$gid . ')';
				$join  	  = ' LEFT JOIN #__flexiaccess_acl AS gc ON c.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"';
				$and	  = ' AND (gc.aro IN ( '.$user->gmid.' ) OR c.access <= '. (int)$gid . ')';
			} else {
				$subjoin  = '';
				$suband   = ' AND cc.access <= '.(int)$gid;
				$suband  .= ' AND i.access <= '.(int)$gid;
				$join	  = '';
				$and   	  = ' AND c.access <= '.(int)$gid;
			}
		}

		$query = 'SELECT c.*,'
				. ' CASE WHEN CHAR_LENGTH( c.alias ) THEN CONCAT_WS( \':\', c.id, c.alias ) ELSE c.id END AS slug,'
					. ' ('
					. ' SELECT COUNT( DISTINCT i.id )'
					. ' FROM #__content AS i'
					. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
					. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
					. ' LEFT JOIN #__categories AS cc ON cc.id = rel.catid'
					. $subjoin
					. $where
					. $suband
					. ')' 
					. ' AS assigneditems'
				. ' FROM #__categories AS c'
				. $join
				. ' WHERE c.published = 1'
				. ' AND c.section = '.FLEXI_SECTION
				. ' AND c.parent_id = '.$rootcat
				. $and
				. ' ORDER BY '.$ordering
				;
		return $query;
	}

	/**
	 * Method to build the Categories query without subselect
	 * That's enough to get the total value.
	 *
	 * @access private
	 * @return string
	 */
	function _buildQueryTotal()
	{
		global $mainframe;

		$user 		= &JFactory::getUser();
		$gid		= (int) $user->get('aid');
		// Get the components parameters
		$params 	=& $mainframe->getParams('com_flexicontent');
		// Get the root category from this directory
		$rootcat = JRequest::getVar('rootcat',false);
		if(!$rootcat) $rootcat = $params->get('rootcat',0);
		// show unauthorized items
		$show_noauth = $params->get('show_noauth', 0);
		
		// Select only items user has access to if he is not allowed to show unauthorized items
		$join 		= '';
		$and 		= '';
		if (!$show_noauth) {
			if (FLEXI_ACCESS) {
				$join  	  = ' LEFT JOIN #__flexiaccess_acl AS gc ON c.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"';
				$and	  = ' AND (gc.aro IN ( '.$user->gmid.' ) OR c.access <= '. (int)$gid . ')';
			} else {
				$join	  = '';
				$and   	  = ' AND c.access <= '.(int)$gid;
			}
		}

		$query 	= 'SELECT c.id'
				. ' FROM #__categories AS c'
				. $join
				. ' WHERE c.published = 1'
				. ' AND c.section = '.FLEXI_SECTION
				. ' AND c.parent_id = ' . $rootcat
				. $and
				;

		return $query;
	}
	
	/**
	 * Total nr of Categories
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		// Lets load the total nr if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQueryTotal();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}
	
	/**
	 * Method to fetch the subcategories
	 *
	 * @access private
	 * @return object
	 */
	function _getsubs($id)
	{
		global $mainframe;

		$user 		= &JFactory::getUser();
		$gid		= (int) $user->get('aid');
		$ordering	= 'c.ordering ASC';
		// Get the components parameters
		$params 	=& $mainframe->getParams('com_flexicontent');
		// Shortcode of the site active language (joomfish)
		$lang 		= JRequest::getWord('lang', '' );
		if(empty($lang)){
			$langFactory= JFactory::getLanguage();
			$tagLang = $langFactory->getTag();
			//Well, the substr is not even required as flexi saves the Joomla language tag... so we could have kept the $tagLang tag variable directly.
			$lang = substr($tagLang ,0,2);
		}
		// Do we filter the categories
		$filtercat  = $params->get('filtercat', 0);
		// show unauthorized items
		$show_noauth = $params->get('show_noauth', 0);

		// Build where clause
		$where  = ' WHERE cc.published = 1';
		$where .= ' AND c.id = cc.id';
		// Filter the category view with the active active language
		if (FLEXI_FISH && $filtercat) {
			$where .= ' AND ie.language LIKE ' . $this->_db->Quote( $lang .'%' );
		}

		$states = ((int)$user->get('gid') > 19) ? '1, -5, 0, -3, -4' : '1, -5';
		$where .= ' AND i.state IN ('.$states.')';
		
		// Select only items user has access to if he is not allowed to show unauthorized items
		$subjoin 	= '';
		$suband 	= '';
		$join 		= '';
		$and 		= '';
		if (!$show_noauth) {
			if (FLEXI_ACCESS) {
				$subjoin  = ' LEFT JOIN #__flexiaccess_acl AS sgc ON cc.id = sgc.axo AND sgc.aco = "read" AND sgc.axosection = "category"';
				$subjoin .= ' LEFT JOIN #__flexiaccess_acl AS sgi ON i.id = sgi.axo AND sgi.aco = "read" AND sgi.axosection = "item"';
				$suband	  = ' AND (sgc.aro IN ( '.$user->gmid.' ) OR cc.access <= '. (int)$gid . ')';
				$suband  .= ' AND (sgi.aro IN ( '.$user->gmid.' ) OR i.access <= '. (int)$gid . ')';
				$join  	  = ' LEFT JOIN #__flexiaccess_acl AS gc ON c.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"';
				$and	  = ' AND (gc.aro IN ( '.$user->gmid.' ) OR c.access <= '. (int)$gid . ')';
			} else {
				$suband   = ' AND cc.access <= '.(int)$gid;
				$suband  .= ' AND i.access <= '.(int)$gid;
				$and   	  = ' AND c.access <= '.(int)$gid;
			}
		}

		$query = 'SELECT c.*,'
				. ' CASE WHEN CHAR_LENGTH( c.alias ) THEN CONCAT_WS( \':\', c.id, c.alias ) ELSE c.id END AS slug,'
					. ' ('
					. ' SELECT COUNT( DISTINCT i.id )'
					. ' FROM #__content AS i'
					. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
					. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
					. ' LEFT JOIN #__categories AS cc ON cc.id = rel.catid'
					. $subjoin
					. $where
					. $suband
					. ')' 
					. ' AS assignedsubitems,'
					. ' ('
					. ' SELECT COUNT( sc.id )'
					. ' FROM #__categories AS sc'
					. ' WHERE c.id = sc.parent_id'
					. ' AND sc.published = 1'
					. ')' 
					. ' AS assignedcats'
				. ' FROM #__categories AS c'
				. $join
				. ' WHERE c.published = 1'
				. ' AND c.section = '.FLEXI_SECTION
				. ' AND c.parent_id = '.(int)$id
				. $and
				. ' ORDER BY '.$ordering
				;

		$this->_db->setQuery($query);
		$this->_subs = $this->_db->loadObjectList();

		return $this->_subs;
	}
	
	/**
	 * Get the feed data
	 *
	 * @access public
	 * @return object
	 */
	function getFeed()
	{
		global $mainframe;

		$user 		= &JFactory::getUser();
		$gid		= (int) $user->get('aid');
		$limit 		= JRequest::getVar('limit', 10);
		// Get the components parameters
		$params 	=& $mainframe->getParams('com_flexicontent');
		// shortcode of the site active language (joomfish)
		$lang 		= JRequest::getWord('lang', '' );
		if(empty($lang)){
			$langFactory= JFactory::getLanguage();
			$tagLang = $langFactory->getTag();
			//Well, the substr is not even required as flexi saves the Joomla language tag... so we could have kept the $tagLang tag variable directly.
			$lang = substr($tagLang ,0,2);
		}
		// Do we filter the categories
		$filtercat  = $params->get('filtercat', 0);

		// Filter the category view with the active active language
		if (FLEXI_FISH && $filtercat) {
			$and = ' AND ie.language LIKE ' . $this->_db->Quote( $lang .'%' );
		}

		$query 	= 'SELECT DISTINCT i.*, ie.*, c.title AS cattitle,'
				. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
				. ' FROM #__flexicontent_items AS i'
				. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
				. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
				. ' LEFT JOIN #__categories AS c ON c.id = rel.catid'
				. ' WHERE c.published = 1'
				. ' AND c.section = '.FLEXI_SECTION
				. ' AND c.access <= '.$gid
				. $and
				. ' AND i.state IN (1, -5)'
				. ' LIMIT '. $limit
				;
		
		$this->_db->setQuery($query);
		$feed = $this->_db->loadObjectList();

		return $feed;
	}
}
?>