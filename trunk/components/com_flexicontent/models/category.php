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
class FlexicontentModelCategory extends JModel{
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
	 * Group Categories
	 *
	 * @var integer
	 */
	var $_group_cats = array();

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
	function _buildQuery() {
		// Get the WHERE and ORDER BY clauses for the query
		$where			= $this->_buildItemWhere();
		$orderby		= $this->_buildItemOrderBy();

		$field_item = '';
		// Add sort items by custom field. Issue 126 => http://code.google.com/p/flexicontent/issues/detail?id=126#c0
		$params = $this->_category->parameters;
		if ($params->get('orderbycustomfieldid', 0) != 0) {
			$field_item = ' LEFT JOIN #__flexicontent_fields_item_relations AS f ON f.item_id = i.id';
			}
		
		$query = 'SELECT DISTINCT i.*, ie.*, u.name as author, ty.name AS typename,'
		. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
		. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
		. ' FROM #__content AS i'
		. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
		. ' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
		. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
		. $field_item
		. ' LEFT JOIN #__categories AS c ON c.id = i.catid'
		. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
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
				case 'modified' :
				$filter_order		= 'i.modified';
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
		// Add sort items by custom field. Issue 126 => http://code.google.com/p/flexicontent/issues/detail?id=126#c0
		if ($params->get('orderbycustomfieldid', 0) != 0)
			{
			if ($params->get('orderbycustomfieldint', 0) != 0) $int = ' + 0'; else $int ='';
			$filter_order		= 'f.value'.$int;
			$filter_order_dir	= $params->get('orderbycustomfielddir', 'ASC');
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
		global $mainframe, $option, $globalcats;
	    
		$user		= & JFactory::getUser();
		$gid		= (int) $user->get('aid');
		$now		= $mainframe->get('requestTime');
		$nullDate	= $this->_db->getNullDate();
		
		//Get active menu parameters.
		$menus		= & JSite::getMenu();
		$menu    	= $menus->getActive();

		// Get the category parameters
		$cparams 	= $this->_category->parameters;

		// display sub-categories
		$display_subcats = $cparams->get('display_subcategories_items', 0);
		
		// Display items from current category
		$_group_cats = array($this->_id);
		
		// Display items from (current and) immediate sub-categories (1-level)
		if ($display_subcats==1) {
			if(is_array($this->_childs))
				foreach($this->_childs as $ch)
					$_group_cats[] = $ch->id;
		}
		
		// Display items from (current and) all sub-categories (any-level)
		if ($display_subcats==2) {
			// descendants also includes current category
			$_group_cats = array_map('trim',explode(",",$globalcats[$this->_id]->descendants));
		}
		
		$_group_cats = array_unique($_group_cats);
		$this->_group_cats = $_group_cats;
		$_group_cats = "'".implode("','", $_group_cats)."'";
		
		// Get the site default language in case no language is set in the url
		$lang 		= JRequest::getWord('lang', '' );
		if(empty($lang)){
			$langFactory= JFactory::getLanguage();
			$tagLang = $langFactory->getTag();
			//Well, the substr is not even required as flexi saves the Joomla language tag... so we could have kept the $tagLang tag variable directly.
			$lang = substr($tagLang ,0,2);
		}

		// content language parameter UNUSED
		$filterlang = $cparams->get('language', '');
		$filtercat  = $cparams->get('filtercat', 0);
		// show unauthorized items
		$show_noauth = $cparams->get('show_noauth', 0);
		
		// First thing we need to do is to select only the requested items
		$where = ' WHERE rel.catid IN ('.$_group_cats.')';

		// Second is to only select items the user has access to
		$states = ((int)$user->get('gid') > 19) ? '1, -5, 0, -3, -4' : '1, -5';
		$where .= ' AND ( i.state IN ('.$states.') OR i.created_by = '.$user->id.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
		
		// is the content current?
		$where .= ' AND ( ( i.publish_up = '.$this->_db->Quote($nullDate).' OR i.publish_up <= '.$this->_db->Quote($now).' ) OR i.created_by = '.$user->id.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
		$where .= ' AND ( ( i.publish_down = '.$this->_db->Quote($nullDate).' OR i.publish_down >= '.$this->_db->Quote($now).' ) OR i.created_by = '.$user->id.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
		
		// Add sort items by custom field. Issue 126 => http://code.google.com/p/flexicontent/issues/detail?id=126#c0
		if ($cparams->get('orderbycustomfieldid', 0) != 0)
			{
			$where .= ' AND f.field_id = '.$cparams->get('orderbycustomfieldid');
			}
		
		// Filter the category view with the active active language
		if (FLEXI_FISH && $filtercat) {
			$where .= ' AND ie.language LIKE ' . $this->_db->Quote( $lang .'%' );
		}
		
		$where .= ' AND i.sectionid = ' . FLEXI_SECTION;

		// filter by permissions
		if (!$show_noauth) {
			if (FLEXI_ACCESS) {
				$readperms = FAccess::checkUserElementsAccess($user->gmid, 'read');
				if (isset($readperms['item'])) {
					$where .= ' AND ( ( i.access <= '.$gid.' OR i.id IN ('.implode(",", $readperms['item']).') OR i.created_by = '.$user->id.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) ) )';
			} else {
					$where .= ' AND ( i.access <= '.$gid.' OR i.created_by = '.$user->id.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
				}
			} else {
				$where .= ' AND ( i.access <= '.$gid.' OR i.created_by = '.$user->id.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
			}
		}

		/*
		 * If we have a filter, and this is enabled... lets tack the AND clause
		 * for the filter onto the WHERE clause of the item query.
		 */
		if ( $cparams->get('use_filters') || $cparams->get('use_search') )
		{
			$filter 		= JRequest::getString('filter', '', 'request');

			if ($filter)
			{
				// clean filter variables
				$filter			= $this->_db->getEscaped( trim(JString::strtolower( $filter ) ) );

				$where .= ' AND (';
				$filters = explode(' ', $filter);
				$i = 0;
				foreach ($filters as $pattern) {
					if ($i == 0) {
						$where .= 'LOWER( i.title ) LIKE '.$this->_db->Quote( '%'.$this->_db->getEscaped( $pattern, true ).'%', false);
						$i = 1;
					} else {
						$where .= ' AND LOWER( i.title ) LIKE '.$this->_db->Quote( '%'.$this->_db->getEscaped( $pattern, true ).'%', false);
					}
				}
				$where .= ') ';
			}
		}
		
		$filters = $this->getFilters();

		if ($filters)
		{
			foreach ($filters as $filtre)
			{
				$filtervalue 	= $mainframe->getUserStateFromRequest( $option.'.category'.$this->_id.'.filter_'.$filtre->id, 'filter_'.$filtre->id, '', 'cmd' );
				if ($filtervalue) {
					$where .= $this->_getFiltered($filtre->id, $filtervalue, $filtre->field_type);
				}
			}
		}
		$session  =& JFactory::getSession();
		$alpha = JRequest::getVar('letter');
		if($alpha===NULL) {
			$alpha =  $session->get($option.'.category.letter');
		} else {
			$session->set($option.'.category.letter', $alpha);
		}
		
		// WARNING DO THIS because utf8 is multibyte and MySQL regexp doesnot support so we cannot use [] with utf8
		$range = explode("-", $alpha);
		
		$regexp='';
		if (utf8_strlen($alpha)==0) {
			// nothing to do
		} else if (count($range) > 2) {
			echo "Error in Alpha Index please correct letter range: ".$alpha."<br>";
		} else if (count($range) == 1) {
			
			$regexp = '"^('.utf8_substr($alpha,0,1);
			for($i=1; $i<utf8_strlen($alpha); $i++) :
				$regexp .= '|'.utf8_substr($alpha,$i,1);
			endfor;
			$regexp .= ')"';
			
		} else if (count($range) == 2) {
			
			// Get range characters
			$startletter = $range[0];  $endletter = $range[1];
			
			// ERROR CHECK: Range START and END are single character strings
			if (utf8_strlen($startletter) != 1 || utf8_strlen($endletter) != 1) {
				echo "Error in Alpha Index<br>letter range: ".$alpha." start and end must be one character<br>";
				return $where;
			}
			
			// Get ord of characters and their rangle length
			$startord=FLEXIUtilities::uniord($startletter);
			$endord=FLEXIUtilities::uniord($endletter);
			$range_length = $endord - $startord;
			
			// ERROR CHECK: Character range has at least one character
			if ($range_length > 200 || $range_length < 1) {
				// A sanity check that the range is something logical and that 
				echo "Error in Alpha Index<br>letter range: ".$alpha.", is incorrect or contains more that 200 characters<br>";
				return $where;
			}
			
			// Check if any character out of the range characters exists
			// Meaning (There is at least on item title starting with one of the range characters)
			$regexp = '"^('.$startletter;
			for($uord=$startord+1; $uord<=$endord; $uord++) :
				$regexp .= '|'.FLEXIUtilities::unichr($uord);
			endfor;
			$regexp .= ')"';
			
		} else {
			echo "Error in Alpha Index<br>incorrect letter range: ".$alpha."<br>";
		}
		
		if ($regexp) {
			
			if ($alpha == '0') {
				$where .= ' AND ( CONVERT (( i.title ) USING BINARY) REGEXP CONVERT ('.$regexp.' USING BINARY) )' ;
				//$where .= ' AND LOWER( i.title ) RLIKE '.$this->_db->Quote( $this->_db->getEscaped( '^['.$alpha.']', true ), false );
			}		
			elseif (!empty($alpha)) {
				$where .= ' AND ( CONVERT (LOWER( i.title ) USING BINARY) REGEXP CONVERT ('.$regexp.' USING BINARY) )' ;
				//$where .= ' AND LOWER( i.title ) RLIKE '.$this->_db->Quote( $this->_db->getEscaped( '^['.$alpha.']', true ), false );
			}
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
		$andaccess = '';
		
		// filter by permissions
		if (!$show_noauth) {
			if (FLEXI_ACCESS) {
				$readperms = FAccess::checkUserElementsAccess($user->gmid, 'read');
				if (isset($readperms['category'])) {
					$andaccess = ' AND ( c.access <= '.$gid.' OR c.id IN ('.implode(",", $readperms['category']).') )';
				} else {
					$andaccess = ' AND c.access <= '.$gid;
				}
			} else {
				$andaccess = ' AND c.access <= '.$gid;
			}
		}

		$query = 'SELECT c.*,'
				. ' CASE WHEN CHAR_LENGTH( c.alias ) THEN CONCAT_WS( \':\', c.id, c.alias ) ELSE c.id END AS slug'
				. ' FROM #__categories AS c'
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
		// Get the site default language in case no language is set in the url
		$lang 		= JRequest::getWord('lang', '' );
		if(empty($lang)){
			$langFactory= JFactory::getLanguage();
			$tagLang = $langFactory->getTag();
			//Well, the substr is not even required as flexi saves the Joomla language tag... so we could have kept the $tagLang tag variable directly.
			$lang = substr($tagLang ,0,2);
		}
		// content language parameter UNUSED
		$filterlang = $cparams->get('language', '');
		$filtercat  = $cparams->get('filtercat', 0);
		// show unauthorized items
		$show_noauth = $cparams->get('show_noauth', 0);

		$joinaccess		= FLEXI_ACCESS ? ' LEFT JOIN #__flexiaccess_acl AS gc ON cc.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"' : '' ;
		$joinaccess2	= FLEXI_ACCESS ? ' LEFT JOIN #__flexiaccess_acl AS gi ON i.id = gi.axo AND gi.aco = "read" AND gi.axosection = "item"' : '' ;
		$where 			= ' WHERE cc.published = 1';

		// Filter the category view with the active active language
		if (FLEXI_FISH && $filtercat) {
			$where .= ' AND ie.language LIKE ' . $this->_db->Quote( $lang .'%' );
		}

		$states = ((int)$user->get('gid') > 19) ? '1, -5, 0, -3, -4' : '1, -5';
		$where .= ' AND i.state IN ('.$states.')';

		$where .= ' AND rel.catid IN ('. (@$globalcats[$id]->descendants?$globalcats[$id]->descendants:"''"). ')';
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
		$id = $this->_id;
		$k = 0;
		$count = count($this->_childs);
		for($i = 0; $i < $count; $i++) {
			$category =& $this->_childs[$i];
			
			$category->assigneditems	= $this->_getassigned( $category->id );
			$category->subcats			= $this->_getsubs( $category->id );
			$this->_id					= $category->id;
			//$category->items			= $this->getData();
			$this->_data				= null;
			$k = 1 - $k;
		}
		$this->_id = $id;
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
		
		if (FLEXI_ACCESS) {
			$readperms = FAccess::checkUserElementsAccess($user->gmid, 'read');
			if (isset($readperms['field'])) {
				$where = ' AND ( fi.access <= '.$gid.' OR fi.id IN ('.implode(",", $readperms['field']).') )';
			} else {
				$where = ' AND fi.access <= '.$gid;
			}
		} else {
			$where = ' AND fi.access <= '.$gid;
		}
		
		$query  = 'SELECT fi.*'
				. ' FROM #__flexicontent_fields AS fi'
				. ' WHERE fi.published = 1'
				. $where
				. $scope
				. ' ORDER BY fi.ordering, fi.name'
				;
			$this->_db->setQuery($query);
			$filters = $this->_db->loadObjectList('name');

		foreach ($filters as $filter) {
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
	function _getFiltered($field_id, $value, $field_type = '')
	{
		switch($field_type) 
		{
			case 'createdby':
				$filter_query = ' AND i.created_by = ' . $this->_db->Quote($value);
			break;

			case 'modifiedby':
				$filter_query = ' AND i.modified_by = ' . $this->_db->Quote($value);
			break;
			
			case 'type':
				$filter_query = ' AND ie.type_id = ' . $this->_db->Quote($value);
			break;
			
			case 'state':
				if ( $value == 'P' ) {
					$filter_query = ' AND i.state = 1';
				} else if ($value == 'U' ) {
					$filter_query = ' AND i.state = 0';
				} else if ($value == 'PE' ) {
					$filter_query = ' AND i.state = -3';
				} else if ($value == 'OQ' ) {
					$filter_query = ' AND i.state = -4';
				} else if ($value == 'IP' ) {
					$filter_query = ' AND i.state = -5';
				}
			break;
			
			case 'tags':
				$query  = 'SELECT itemid'
						. ' FROM #__flexicontent_tags_item_relations'
						. ' WHERE tid = ' . $this->_db->Quote($value)
						;
				$this->_db->setQuery($query);
				$filtered = $this->_db->loadResultArray();
				$filter_query = $filtered ? ' AND i.id IN (' . implode(',', $filtered) . ')' : ' AND i.id = 0';
			break;
			
			default:
		$query  = 'SELECT item_id'
				. ' FROM #__flexicontent_fields_item_relations'
				. ' WHERE field_id = ' . $field_id
				. ' AND value = ' . $this->_db->Quote($value)
				. ' GROUP BY item_id'
				;
		$this->_db->setQuery($query);
				$filtered = $this->_db->loadResultArray();
				$filter_query = $filtered ? ' AND i.id IN (' . implode(',', $filtered) . ')' : ' AND i.id = 0';
			break; 
		}
		
		return $filter_query;
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
		global $mainframe;
		
		$user      = & JFactory::getUser();
		$gid      = (int) $user->get('aid');

		// Get the site default language in case no language is set in the url
		$lang 		= JRequest::getWord('lang', '' );
		if(empty($lang)){
			$langFactory= JFactory::getLanguage();
			$tagLang = $langFactory->getTag();
			//Well, the substr is not even required as flexi saves the Joomla language tag... so we could have kept the $tagLang tag variable directly.
			$lang = substr($tagLang ,0,2);
		}
		
		$now = $mainframe->get('requestTime');
		$nullDate = $this->_db->getNullDate();
		
		// Get the category parameters
		$cparams    = $this->_category->parameters;
		// show unauthorized items
		$show_noauth = $cparams->get('show_noauth', 0);
		
		// Filter the category view with the active active language
		$and 	= FLEXI_FISH ? ' AND ie.language LIKE ' . $this->_db->Quote( $lang .'%' ) : '';
		$and2 = $show_noauth ? '' : ' AND c.access <= '.$gid.' AND i.access <= '.$gid;
		
		//Is the content current
		$and3 = ' AND ( i.publish_up = '.$this->_db->Quote($nullDate).' OR i.publish_up <= '.$this->_db->Quote($now).' )';
		$and3.= ' AND ( i.publish_down = '.$this->_db->Quote($nullDate).' OR i.publish_down >= '.$this->_db->Quote($now).' )';
		
		$_group_cats = implode("','", $this->_group_cats);
		$query   	= 'SELECT LOWER(SUBSTRING(i.title FROM 1 FOR 1)) AS alpha'
				. ' FROM #__content AS i'
				. ' LEFT JOIN #__flexicontent_items_ext AS ie ON i.id = ie.item_id'
				. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
				. ' LEFT JOIN #__categories AS c ON c.id IN (\''. $_group_cats .'\')'
				. ' WHERE rel.catid IN (\''. $_group_cats .'\')'
				. $and
				. ' AND i.state IN (1, -5)'
				. ' AND i.sectionid = '.FLEXI_SECTION
				. $and2
				. $and3
				. ' GROUP BY alpha'
				. ' ORDER BY alpha ASC'
				;
		$this->_db->setQuery($query);
		$alpha = $this->_db->loadResultArray();
		return $alpha;
   }
}
?>
