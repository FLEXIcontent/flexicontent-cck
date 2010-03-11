<?php
/**
 * @version 1.5 beta 5 $Id: category.php 188 2009-11-20 11:27:41Z vistamedia $
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
class FlexicontentModelCategory extends JModel
{
	/**
	 * Category id
	 *
	 * @var int
	 */
	var $_id = null;
	
	/**
	 * Categories items Data
	 *
	 * @var mixed
	 */
	var $_data = null;

	/**
	 * Childs
	 *
	 * @var mixed
	 */
	var $_childs = null;
	
	/**
	 * Category data
	 *
	 * @var object
	 */
	var $_category = null;

	/**
	 * Categories total
	 *
	 * @var integer
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

		$cid			= JRequest::getInt('cid', 0);
		
		// we need to merge parameters here to get the correct page limit value
		$params = $this->_loadCategoryParams($cid);

		//get the number of entries from session
		$limit			= $mainframe->getUserStateFromRequest('com_flexicontent.category'.$cid.'.limit', 'limit', $params->def('limit', 0), 'int');		
		$limitstart		= JRequest::getInt('limitstart');
		
		$this->setId((int)$cid);

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

		// Get the filter request variables
		$this->setState('filter_order', 	JRequest::getCmd('filter_order', 'i.title'));
		$this->setState('filter_order_dir', JRequest::getCmd('filter_order_Dir', 'ASC'));
	}

	/**
	 * Method to set the category id
	 *
	 * @access	public
	 * @param	int	category ID number
	 */
	function setId($cid)
	{
		// Set new category ID and wipe data
		$this->_id			= $cid;
		//$this->_data		= null;
	}		


	/**
	 * Method to get Data
	 *
	 * @access public
	 * @return mixed
	 */
	function getData()
	{
		$format	= JRequest::getVar('format', null);
		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{
			$query = $this->_buildQuery();

			$this->_total = $this->_getListCount($query);

			if ((int)$this->getState('limitstart') < (int)$this->_total) {
				$this->_data = $this->_getList( $query, $this->getState('limitstart'), $this->getState('limit') );
			} else {
				$this->_data = $this->_getList( $query, 0, $this->getState('limit') );
			}
		}

		return $this->_data;
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
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}

	/**
	 * Builds the query
	 *
	 * @access public
	 * @return string
	 */
	function _buildQuery()
	{
		// Get the WHERE and ORDER BY clauses for the query
		$where			= $this->_buildItemWhere();
		$orderby		= $this->_buildItemOrderBy();

		$joinaccess	= FLEXI_ACCESS ? ' LEFT JOIN #__flexiaccess_acl AS gi ON i.id = gi.axo AND gi.aco = "read" AND gi.axosection = "item"' : '' ;

		$query = 'SELECT DISTINCT i.*, ie.*, u.name as author, ty.name AS typename,'
		. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug'
		. ' FROM #__content AS i'
		. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
		. ' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
		. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
		. ' LEFT JOIN #__categories AS c ON c.id = '. $this->_id
		. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
		. $joinaccess
		. $where
		. $orderby
		;

		return $query;
	}

	/**
	 * Build the order clause
	 *
	 * @access private
	 * @return string
	 */
	function _buildItemOrderBy()
	{
		$params = $this->_category->parameters;
		
		$filter_order		= $this->getState('filter_order');
		$filter_order_dir	= $this->getState('filter_order_dir');

		if ($params->get('orderby')) {
			$order = $params->get('orderby');
			
			switch ($order) {
				case 'date' :
				$filter_order		= 'i.created';
				$filter_order_dir	= 'ASC';
				break;
				case 'rdate' :
				$filter_order		= 'i.created';
				$filter_order_dir	= 'DESC';
				break;
				case 'alpha' :
				$filter_order		= 'i.title';
				$filter_order_dir	= 'ASC';
				break;
				case 'ralpha' :
				$filter_order		= 'i.title';
				$filter_order_dir	= 'DESC';
				break;
				case 'author' :
				$filter_order		= 'u.name';
				$filter_order_dir	= 'ASC';
				break;
				case 'rauthor' :
				$filter_order		= 'u.name';
				$filter_order_dir	= 'DESC';
				break;
				case 'hits' :
				$filter_order		= 'i.hits';
				$filter_order_dir	= 'ASC';
				break;
				case 'rhits' :
				$filter_order		= 'i.hits';
				$filter_order_dir	= 'DESC';
				break;
				case 'order' :
				$filter_order		= 'rel.ordering';
				$filter_order_dir	= 'ASC';
				break;
			}
			
		}
		
		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_dir.', i.title';

		return $orderby;
	}

	/**
	 * Method to build the WHERE clause
	 *
	 * @access private
	 * @return array
	 */
	function _buildItemWhere( )
	{
		global $mainframe, $option;

		$user		= & JFactory::getUser();
		$gid		= (int) $user->get('aid');
		$now		= $mainframe->get('requestTime');
		$nullDate	= $this->_db->getNullDate();

		// Get the category parameters
		$cparams 	= $this->_category->parameters;
		// shortcode of the site active language (joomfish)
		$lang 		= JRequest::getWord('lang', '' );
		// content language parameter UNUSED
		$filterlang = $cparams->get('language', '');
		$filtercat  = $cparams->get('filtercat', 0);
		// show unauthorized items
		$show_noauth = $cparams->get('show_noauth', 0);

		// First thing we need to do is to select only the requested items
		$where = ' WHERE rel.catid = '.$this->_id;

		// Second is to only select items the user has access to
		$states = ((int)$user->get('gid') > 19) ? '1, -5, 0, -3, -4' : '1, -5';
		$where .= ' AND i.state IN ('.$states.')';
		
		// is the content current?
		$where .= ' AND ( i.publish_up = '.$this->_db->Quote($nullDate).' OR i.publish_up <= '.$this->_db->Quote($now).' )';
		$where .= ' AND ( i.publish_down = '.$this->_db->Quote($nullDate).' OR i.publish_down >= '.$this->_db->Quote($now).' )';


		// Filter the category view with the active active language
		if (FLEXI_FISH && $filtercat) {
			$where .= ' AND ie.language LIKE ' . $this->_db->Quote( $lang .'%' );
		}
		
		$where .= ' AND i.sectionid = ' . FLEXI_SECTION;

		// Select only items user has access to if he is not allowed to show unauthorized items
		if (!$show_noauth) {
			if (FLEXI_ACCESS) {
				$where .= ' AND (gi.aro IN ( '.$user->gmid.' ) OR i.access <= '. (int) $gid . ')';
			} else {
				$where .= ' AND i.access <= '.$gid;
			}
		}

		/*
		 * If we have a filter, and this is enabled... lets tack the AND clause
		 * for the filter onto the WHERE clause of the item query.
		 */
		if ($cparams->get('use_filters'))
		{
			$filter 		= JRequest::getString('filter', '', 'request');

			if ($filter)
			{
				// clean filter variables
				$filter			= $this->_db->getEscaped( trim(JString::strtolower( $filter ) ) );

				$where .= ' AND LOWER( i.title ) LIKE '.$this->_db->Quote( '%'.$this->_db->getEscaped( $filter, true ).'%', false );
			}
		}
		
		$filters = $this->getFilters();

		if ($filters)
		{
			foreach ($filters as $filtre)
			{
				$setfilter 	= $mainframe->getUserStateFromRequest( $option.'.category'.$this->_id.'.filter_'.$filtre->id, 'filter_'.$filtre->id, '', 'cmd' );
				if ($setfilter) {
					$ids 	= $this->_getFiltered($filtre->id, $setfilter);
					if ($ids)
					{
		 				$where .= ' AND i.id IN (' . $ids . ')';
					} else {
		 				$where .= ' AND i.id = 0';
					}
				}
			}
		}		
		
		$alpha 	= $mainframe->getUserStateFromRequest( $option.'.category.letter', 'letter', '', 'cmd' );

		if ($alpha == '0') {
			$where .= ' AND LOWER( i.title ) LIKE '.$this->_db->Quote( $this->_db->getEscaped( $alpha, true ).'%', false );
		}		
		elseif (!empty($alpha)) {
			$where .= ' AND LOWER( i.title ) LIKE '.$this->_db->Quote( $this->_db->getEscaped( $alpha, true ).'%', false );
		}		
		
		return $where;
	}

	/**
	 * Method to build the Categories query
	 *
	 * @access private
	 * @return string
	 */
	function _buildChildsquery()
	{
		$user 		= &JFactory::getUser();
		$gid		= (int) $user->get('aid');
		$ordering	= 'ordering ASC';

		// Get the category parameters
		$cparams 	= $this->_category->parameters;
		// show unauthorized items
		$show_noauth = $cparams->get('show_noauth', 0);
		
		$andaccess 		= $show_noauth ? '' : (FLEXI_ACCESS ? ' AND (gi.aro IN ( '.$user->gmid.' ) OR c.access <= '. (int) $gid . ')' : ' AND c.access <= '.$gid) ;
		$joinaccess		= FLEXI_ACCESS ? ' LEFT JOIN #__flexiaccess_acl AS gi ON c.id = gi.axo AND gi.aco = "read" AND gi.axosection = "category"' : '' ;

		$query = 'SELECT c.*,'
				. ' CASE WHEN CHAR_LENGTH( c.alias ) THEN CONCAT_WS( \':\', c.id, c.alias ) ELSE c.id END AS slug'
				. ' FROM #__categories AS c'
				. $joinaccess
				. ' WHERE c.published = 1'
				. ' AND c.parent_id = '. $this->_id
				. $andaccess
				. ' ORDER BY '.$ordering
				;
		return $query;
	}

	/**
	 * Method to get the assigned items for a category
	 *
	 * @access private
	 * @return int
	 */
	function _getassigned($id)
	{
		global $mainframe, $globalcats;

		$user 		= &JFactory::getUser();
		$gid		= (int) $user->get('aid');

		// Get the category parameters
		$cparams 	= $this->_category->parameters;
		// shortcode of the site active language (joomfish)
		$lang 		= JRequest::getWord('lang', '' );
		// content language parameter UNUSED
		$filterlang = $cparams->get('language', '');
		$filtercat  = $cparams->get('filtercat', 0);
		// show unauthorized items
		$show_noauth = $cparams->get('show_noauth', 0);

		// shortcode of the site active language (joomfish)
		$lang 		= JRequest::getWord('lang', '' );

		$joinaccess		= FLEXI_ACCESS ? ' LEFT JOIN #__flexiaccess_acl AS gc ON cc.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"' : '' ;
		$joinaccess2	= FLEXI_ACCESS ? ' LEFT JOIN #__flexiaccess_acl AS gi ON i.id = gi.axo AND gi.aco = "read" AND gi.axosection = "item"' : '' ;
		$where 			= ' WHERE cc.published = 1';

		// Filter the category view with the active active language
		if (FLEXI_FISH && $filtercat) {
			$where .= ' AND ie.language LIKE ' . $this->_db->Quote( $lang .'%' );
		}

		$states = ((int)$user->get('gid') > 19) ? '1, -5, 0, -3, -4' : '1, -5';
		$where .= ' AND i.state IN ('.$states.')';

		$where .= ' AND rel.catid IN ('. $globalcats[$id]->descendants. ')';
		// Select only items user has access to if he is not allowed to show unauthorized items
		if (!$show_noauth) {
			if (FLEXI_ACCESS) {
				$where .= ' AND (gc.aro IN ( '.$user->gmid.' ) OR cc.access <= '. (int) $gid . ')';
				$where .= ' AND (gi.aro IN ( '.$user->gmid.' ) OR i.access <= '. (int) $gid . ')';
			} else {
				$where .= ' AND cc.access <= '.$gid;
				$where .= ' AND i.access <= '.$gid;
			}
		}

		$query 	= 'SELECT DISTINCT itemid'
				. ' FROM #__flexicontent_cats_item_relations AS rel'
				. ' LEFT JOIN #__content AS i ON rel.itemid = i.id'
				. ' LEFT JOIN #__flexicontent_items_ext AS ie ON i.id = ie.item_id'
				. ' LEFT JOIN #__categories AS cc ON cc.id = rel.catid'
				. $joinaccess
				. $joinaccess2
				. $where
				;
		
		$this->_db->setQuery($query);
		$assigneditems = count($this->_db->loadResultArray());

		return $assigneditems;
	}

	/**
	 * Method to build the Categories query
	 * todo: see above and merge
	 *
	 * @access private
	 * @return array
	 */
	function _getsubs($id)
	{
		$user 		= &JFactory::getUser();
		$gid		= (int) $user->get('aid');
		$ordering	= 'ordering ASC';

		if (FLEXI_ACCESS) {
		$query = 'SELECT DISTINCTROW sc.*,'
				. ' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', sc.id, sc.alias) ELSE sc.id END as slug'
				. ' FROM #__categories as sc'
				. ' LEFT JOIN #__flexiaccess_acl AS gi ON sc.id = gi.axo AND gi.aco = "read" AND gi.axosection = "category"'
				. ' WHERE sc.published = 1'
				. ' AND sc.parent_id = '. (int)$id
				. ' AND (gi.aro IN ( '.$user->gmid.' ) OR sc.access <= '. (int) $gid . ')'
				. ' ORDER BY '.$ordering
				;
		} else {
		$query = 'SELECT *,'
				. ' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', id, alias) ELSE id END as slug'
				. ' FROM #__categories'
				. ' WHERE published = 1'
				. ' AND parent_id = '. (int)$id
				. ' AND access <= '.$gid
				. ' ORDER BY '.$ordering
				;
		}

		$this->_db->setQuery($query);
		$this->_subs = $this->_db->loadObjectList();

		return $this->_subs;
	}

	/**
	 * Method to get the children of a category
	 *
	 * @access private
	 * @return array
	 */

	function getChilds()
	{
		$query = $this->_buildChildsquery();
		$this->_childs = $this->_getList($query);
//		$this->_childs = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));

		$k = 0;
		$count = count($this->_childs);
		for($i = 0; $i < $count; $i++)
		{
			$category =& $this->_childs[$i];
			
			$category->assigneditems 	= $this->_getassigned( $category->id );
			$category->subcats			= $this->_getsubs( $category->id );

			$k = 1 - $k;
		}

		return $this->_childs;
	}

	/**
	 * Method to load the Category
	 *
	 * @access public
	 * @return array
	 */
	function getCategory()
	{
		global $mainframe;
		
		//initialize some vars
		$user		= & JFactory::getUser();
		$aid		= (int) $user->get('aid');

		//get categories
		$query 	= 'SELECT c.*,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as slug'
				. ' FROM #__categories AS c'
				. ' WHERE c.id = '.$this->_id
				. ' AND c.published = 1'
				;

		$this->_db->setQuery($query);
		$this->_category = $this->_db->loadObject();
		
		//Make sure the category is published
		if (!$this->_category->published)
		{
			JError::raiseError(404, JText::sprintf( 'CATEGORY #%d NOT FOUND', $this->_id ));
			return false;
		}
		
		$this->_category->parameters =& $this->_loadCategoryParams($this->_category->id);
		$cparams = $this->_category->parameters;

		//check whether category access level allows access
		$canread 	= FLEXI_ACCESS ? FAccess::checkAllItemReadAccess('com_content', 'read', 'users', $user->gmid, 'category', $this->_category->id) : $this->_category->access <= $aid;
		if (!$canread)
		{
			if (!$aid) {
				// Redirect to login
				$uri		= JFactory::getURI();
				$return		= $uri->toString();

				$url  = $cparams->get('login_page', 'index.php?option=com_user&view=login');
				$url .= '&return='.base64_encode($return);

				$mainframe->redirect($url, JText::_('You must login first') );
			} else {
				if ($cparams->get('unauthorized_page', '')) {
					$mainframe->redirect($cparams->get('unauthorized_page'));				
				} else {
					JError::raiseError(403, JText::_("ALERTNOTAUTH"));
					return false;
				}
			}
		}

		return $this->_category;
	}

	/**
	 * Method to load content article parameters
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	function _loadCategoryParams($cid)
	{
		global $mainframe;

		$query = 'SELECT params FROM #__categories WHERE id = ' . $cid;
		$this->_db->setQuery($query);
		$catparams = $this->_db->loadResult();

		// Get the page/component configuration
		$params = clone($mainframe->getParams('com_flexicontent'));

		// Merge category parameters into the page configuration
		$cparams = new JParameter($catparams);
		$params->merge($cparams);

		return $params;
	}

	/**
	 * Method to get the filter
	 * 
	 * @access public
	 * @return object
	 * @since 1.5
	 */
	function getFilters()
	{
		global $mainframe;
		
		$user 		= &JFactory::getUser();
		$gid		= (int) $user->get('aid');
		$params 	= $this->_loadCategoryParams($this->_id);
		$scope		= $params->get('filters') ? ( is_array($params->get('filters')) ? ' AND fi.id IN (' . implode(',', $params->get('filters')) . ')' : ' AND fi.id = ' . $params->get('filters') ) : null;
		$filters	= null;
		
		$query  = 'SELECT fi.*'
				. ' FROM #__flexicontent_fields AS fi'
				. ' WHERE fi.access <= '.$gid
				. $scope
				. ' AND fi.published = 1'
				. ' AND fi.isfilter = 1'
				. ' ORDER BY fi.ordering, fi.name'
				;
			$this->_db->setQuery($query);
			$filters = $this->_db->loadObjectList('name');

		foreach ($filters as $filter)
		{
			$filter->parameters = new JParameter($filter->attribs);
		}
		
		return $filters;
	}

	/**
	 * Method to get the active filter result
	 * 
	 * @access private
	 * @return string
	 * @since 1.5
	 */
	function _getFiltered($field_id, $value)
	{
		$query  = 'SELECT item_id'
				. ' FROM #__flexicontent_fields_item_relations'
				. ' WHERE field_id = ' . $field_id
				. ' AND value = ' . $this->_db->Quote($value)
				. ' GROUP BY item_id'
				;
		$this->_db->setQuery($query);
		$filters = $this->_db->loadResultArray();
		$filters = implode(',', $filters);
		
		return $filters;
	}

	/**
	 * Method to build the alphabetical index
	 * 
	 * @access public
	 * @return string
	 * @since 1.5
	 */
	function getAlphaindex()
	{
		$user		= & JFactory::getUser();
		$gid		= (int) $user->get('aid');
		$lang 		= JRequest::getWord('lang', '' );
		// Get the category parameters
		$cparams 	= $this->_category->parameters;
		// show unauthorized items
		$show_noauth = $cparams->get('show_noauth', 0);

		// Filter the category view with the active active language
		$and = FLEXI_FISH ? ' AND ie.language LIKE ' . $this->_db->Quote( $lang .'%' ) : '';
		$and2 = $show_noauth ? '' : ' AND c.access <= '.$gid.' AND i.access <= '.$gid;

		$query 	= 'SELECT DISTINCT i.title'
				. ' FROM #__content AS i'
				. ' LEFT JOIN #__flexicontent_items_ext AS ie ON i.id = ie.item_id'
				. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
				. ' LEFT JOIN #__categories AS c ON c.id = '. $this->_id
				. ' WHERE rel.catid = '.$this->_id
				. $and
				. ' AND i.state IN (1, -5)'
				. ' AND i.sectionid = '.FLEXI_SECTION
				. $and2
				;
		
		$this->_db->setQuery($query);
		$titles = $this->_db->loadResultArray();

		$alpha		= array();
		foreach ($titles as $title)
		{
			$alpha[] = strtolower(substr($title, 0, 1));
		}
		//asort($alpha);
		$alpha = array_unique($alpha);
		return $alpha;
	}

}
?>