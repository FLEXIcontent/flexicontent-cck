<?php
/**
 * @version 1.5 stable $Id: category.php 313 2010-06-19 08:32:09Z emmanuel.danan $
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

jimport('joomla.application.component.modellist');

/**
 * FLEXIcontent Component Model
 *
 * @package Joomla
 * @subpackage Flexicontent
 * @since		1.0
 */
class FlexicontentModelCategory extends JModelList{
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
	 * Constructor.
	 *
	 * @param	array	An optional associative array of configuration settings.
	 * @see		JController
	 * @since	1.6
	 */
	public function __construct($config = array()) {
		if (empty($config['filter_fields'])) {
			$config['filter_fields'] = array(
				'id', 'a.id',
				'title', 'a.title',
				'alias', 'a.alias',
				'checked_out', 'a.checked_out',
				'checked_out_time', 'a.checked_out_time',
				'catid', 'a.catid', 'category_title',
				'state', 'a.state',
				'access', 'a.access', 'access_level',
				'created', 'a.created',
				'created_by', 'a.created_by',
				'ordering', 'a.ordering',
				'featured', 'a.featured',
				'language', 'a.language',
				'hits', 'a.hits',
				'publish_up', 'a.publish_up',
				'publish_down', 'a.publish_down',
				'author', 'a.author'
			);
		}
		
		// Get category id and call constrcuctor
		$mainframe = &JFactory::getApplication();
		$cid			= JRequest::getInt('cid', 0);
		$this->setId((int)$cid);

		parent::__construct($config);
				
		// we need to merge parameters here to get the correct page limit value
		$params = $this->_loadCategoryParams($cid);

		//get the number of entries from session
		$limit			= $mainframe->getUserStateFromRequest('com_flexicontent.category'.$cid.'.limit', 'limit', $params->def('limit', 0), 'int');
		$limitstart		= JRequest::getInt('limitstart');
		
		// set pagination limit variables
		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

		// set filter order variables
		$this->setState('filter_order', 	JRequest::getCmd('filter_order', 'title'));
		$this->setState('filter_order_dir', JRequest::getCmd('filter_order_Dir', 'ASC'));
	}
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	/*function __construct() {
		parent::__construct();
		$mainframe = &JFactory::getApplication();
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
	}*/
	
	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * return	void
	 * @since	1.6
	 */
	protected function populateState($ordering = null, $direction = null) {
		// Initiliase variables.
		$app	= JFactory::getApplication('site');
		$pk		= JRequest::getInt('cid');

		$this->setState('category.id', $pk);

		// Load the parameters. Merge Global and Menu Item params into new object
		//$params = $app->getParams();//core joomla
		// we need to merge parameters here to get the correct page limit value
		$params = $this->_loadCategoryParams($pk);//flexicontent
		$menuParams = new JRegistry;

		if ($menu = $app->getMenu()->getActive()) {
			$menuParams->loadJSON($menu->params);
		}

		$mergedParams = clone $menuParams;
		$mergedParams->merge($params);

		$this->setState('params', $mergedParams);
		$user		= JFactory::getUser();
				// Create a new query object.
		$db		= $this->getDbo();
		$query	= $db->getQuery(true);
		$groups	= implode(',', $user->getAuthorisedViewLevels());

		if ((!$user->authorise('core.edit.state', 'com_flexicontent')) &&  (!$user->authorise('core.edit', 'com_flexicontent'))){
			// limit to published for people who can't edit or edit.state.
			$this->setState('filter.published', 1);
			// Filter by start and end dates.
			$nullDate = $db->Quote($db->getNullDate());
			$nowDate = $db->Quote(JFactory::getDate()->toMySQL());

			$query->where('(a.publish_up = ' . $nullDate . ' OR a.publish_up <= ' . $nowDate . ')');
			$query->where('(a.publish_down = ' . $nullDate . ' OR a.publish_down >= ' . $nowDate . ')');
		}

		// process show_noauth parameter
		if (!$params->get('show_noauth')) {
			$this->setState('filter.access', true);
		} else {
			$this->setState('filter.access', false);
		}

		// Optional filter text
		$this->setState('list.filter', JRequest::getString('filter-search'));

		// filter.order
		$itemid = JRequest::getInt('cid', 0) . ':' . JRequest::getInt('Itemid', 0);
		$orderCol = $app->getUserStateFromRequest('com_flexicontent.category.list.' . $itemid . '.filter_order', 'filter_order', 'a.ordering', 'string');
		// Get the filter request variables
		//$this->setState('filter_order', 	JRequest::getCmd('filter_order', 'i.title'));
		//$this->setState('filter_order_dir', JRequest::getCmd('filter_order_Dir', 'ASC'));
		if (!in_array($orderCol, $this->filter_fields)) {
			$orderCol = 'a.ordering';
		}
		$this->setState('list.ordering', $orderCol);

		$listOrder = $app->getUserStateFromRequest('com_flexicontent.category.list.' . $itemid . '.filter_order_Dir',
			'filter_order_Dir', 'ASC', 'cmd');
		if (!in_array(strtoupper($listOrder), array('ASC', 'DESC', ''))) {
			$listOrder = 'ASC';
		}
		$this->setState('list.direction', $listOrder);

		$this->setState('list.start', JRequest::getVar('limitstart', 0, '', 'int'));
		
		// set limit for query. If list, use parameter. If blog, add blog parameters for limit.
		/*if ((JRequest::getCmd('layout') == 'blog') || $params->get('layout_type') == 'blog') {
			$limit = $params->get('num_leading_articles') + $params->get('num_intro_articles') + $params->get('num_links');
			$this->setState('list.links', $params->get('num_links'));
		}
		else {*/
			//$limit = $app->getUserStateFromRequest('com_content.category.list.' . $itemid . '.limit', 'limit', $params->get('display_num'));
			$limit = $app->getUserStateFromRequest('com_flexicontent.category'.$pk.'.limit', 'limit', $params->def('limit', 0), 'int');
		//}

		$limitstart = JRequest::getInt('limitstart');
		$this->setId((int)$pk);
		/*$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
		$this->setState('filter_order', 	JRequest::getCmd('filter_order', 'i.title'));
		$this->setState('filter_order_dir', JRequest::getCmd('filter_order_Dir', 'ASC'));*/

		// set the depth of the category query based on parameter
		//$showSubcategories = $params->get('show_subcategory_content', '0');

		/*if ($showSubcategories) {
			$this->setState('filter.max_category_levels', $params->get('show_subcategory_content', '1'));
			$this->setState('filter.subcategories', true);
		}*/
		$this->setState('filter.language',$app->getLanguageFilter());
		$this->setState('layout', JRequest::getCmd('layout'));
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
	 * Get the articles in the category
	 *
	 * @return	mixed	An array of articles or false if an error occurs.
	 * @since	1.5
	 */
	function getItems() {
		$params = $this->getState()->get('params');
		$limit = $this->getState('list.limit');

		if ($this->_data === null && $category = $this->getCategory()) {
			$model = JModel::getInstance('Items', 'FlexicontentModel', array('ignore_request' => true));
			$model->setState('params', $this->_loadCategoryParams(JRequest::getInt('cid')));
			$model->setState('filter.category_id', $category->id);
			$model->setState('filter.published', $this->getState('filter.published'));
			$model->setState('filter.access', $this->getState('filter.access'));
			$model->setState('filter.language', $this->getState('filter.language'));
			//$model->setState('list.ordering', $this->_buildContentOrderBy());
			$model->setState('list.start', $this->getState('list.start'));
			$model->setState('list.limit', $limit);
			$model->setState('list.direction', $this->getState('list.direction'));
			$model->setState('list.filter', $this->getState('list.filter'));
			// filter.subcategories indicates whether to include articles from subcategories in the list or blog
			$model->setState('filter.subcategories', $this->getState('filter.subcategories'));
			$model->setState('filter.max_category_levels', $this->setState('filter.max_category_levels'));
			//$model->setState('list.links', $this->getState('list.links'));
			//$model->setState('filter_order', $this->getState('filter_order'));
			//$model->setState('filter_order_dir', $this->getState('filter_order_dir'));

			if ($limit >= 0) {
				/*$cparams 	= $this->_category->parameters;
				$config = array();
				$config['display_subcategories_items'] = $cparams->get('display_subcategories_items', 0);
				// content language parameter UNUSED
				$config['language'] = $cparams->get('language', '');
				$config['filtercat']  = $cparams->get('filtercat', 0);
				// show unauthorized items
				$config['show_noauth'] = $cparams->get('show_noauth', 0);
				$config['use_filters'] = $cparams->get('use_filters');*/
				$model->_category_parameters = &$this->_category->parameters;
				$this->_data = $model->getItems();

				if ($this->_data === false) {
					$this->setError($model->getError());
				}
			}
			else {
				$this->_data=array();
			}

			$this->_pagination = $model->getPagination();
		}

		return $this->_data;
	}
	
	public function getPagination() {
		if (empty($this->_pagination)) {
			return null;
		}
		return $this->_pagination;
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
		if (empty($this->_data)) {
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
	function getTotal() {
		// Lets load the total nr if it doesn't already exist
		if (empty($this->_total)) {
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
		static $query;
		if(!$query) {
			// Get the WHERE and ORDER BY clauses for the query
			$where			= $this->_buildItemWhere();
			$orderby		= $this->_buildItemOrderBy();

			$field_item = '';
			// Add sort items by custom field. Issue 126 => http://code.google.com/p/flexicontent/issues/detail?id=126#c0
			$params = $this->_category->parameters;
			if ($params->get('orderbycustomfieldid', 0) != 0) {
				$field_item = ' LEFT JOIN #__flexicontent_fields_item_relations AS f ON f.item_id = i.id';
			}
		
			//$joinaccess	= FLEXI_ACCESS ? ' LEFT JOIN #__flexiaccess_acl AS gi ON i.id = gi.axo AND gi.aco = "read" AND gi.axosection = "item"' : '' ;
			$query = 'SELECT DISTINCT i.*, ie.*,c.lft,c.rgt,u.name as author, ty.name AS typename,'
			. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug'
			. ' FROM #__content AS i'
			. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
			. ' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
			. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
			. $field_item
			//. ' LEFT JOIN #__categories AS c ON c.id = '. $this->_id
			. ' LEFT JOIN #__categories AS c ON c.id = i.catid'
			. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
			. $where
			. $orderby
			;
		}
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
		global $globalcats;
		$mainframe = &JFactory::getApplication();
		
		$option = JRequest::getVar('option');

		$user		= & JFactory::getUser();
		$gid		= max ($user->getAuthorisedViewLevels());
		$now		= $mainframe->get('requestTime');
		$nullDate	= $this->_db->getNullDate();
		
		$_group_cats = array($this->_id);
		//Get active menu parameters.
		$menus		= & JSite::getMenu();
		$menu    	= $menus->getActive();
		

		// Get the category parameters
		$cparams 	= $this->_category->parameters;
		$owneritems = $cparams->get('owneritems', '0');

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
		$where .= (!$owneritems)?' AND i.state IN ('.$states.')':'';
		
		// is the content current?
		if(!$owneritems) {
			$where .= ' AND ( i.publish_up = '.$this->_db->Quote($nullDate).' OR i.publish_up <= '.$this->_db->Quote($now).' )';
			$where .= ' AND ( i.publish_down = '.$this->_db->Quote($nullDate).' OR i.publish_down >= '.$this->_db->Quote($now).' )';
		}


		// Filter the category view with the active active language
		if (FLEXI_FISH && $filtercat) {
			$where .= ' AND ie.language LIKE ' . $this->_db->Quote( $lang .'%' );
		}
		
		//$where .= ' AND c.lft >= ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) .' AND c.rgt<= ' . $this->_db->Quote(FLEXI_RGT_CATEGORY);

		// Select only items user has access to if he is not allowed to show unauthorized items
		if (!$show_noauth) {
			$groups	= "'".implode("','", $user->getAuthorisedViewLevels())."'";
			$where .= " AND i.access IN ($groups)";
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
		
		//Display all items or owner items
		if($owneritems) {
			$where .= " AND i.created_by='".$user->get('id')."'";
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
		if (JString::strlen($alpha)==0) {
			// nothing to do
		} else if (count($range) > 2) {
			echo "Error in Alpha Index please correct letter range: ".$alpha."<br>";
		} else if (count($range) == 1) {
			
			$regexp = '"^('.JString::substr($alpha,0,1);
			for($i=1; $i<JString::strlen($alpha); $i++) :
				$regexp .= '|'.JString::substr($alpha,$i,1);
			endfor;
			$regexp .= ')"';
			
		} else if (count($range) == 2) {
			
			// Get range characters
			$startletter = $range[0];  $endletter = $range[1];
			
			// ERROR CHECK: Range START and END are single character strings
			if (JString::strlen($startletter) != 1 || JString::strlen($endletter) != 1) {
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
		$gid		= max ($user->getAuthorisedViewLevels());
		$ordering	= 'lft ASC';

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
		$gid		= max ($user->getAuthorisedViewLevels());

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
		$gid		= max ($user->getAuthorisedViewLevels());
		$ordering	= 'lft ASC';

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

	function getChilds() {
		$query = $this->_buildChildsquery();
		$this->_childs = $this->_getList($query);
//		$this->_childs = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));
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
		$mainframe = &JFactory::getApplication();
		
		//initialize some vars
		$user		= & JFactory::getUser();
		$aid		= max ($user->getAuthorisedViewLevels());

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
		//$canread 	= FLEXI_ACCESS ? FAccess::checkAllItemReadAccess('com_content', 'read', 'users', $user->gmid, 'category', $this->_category->id) : $this->_category->access <= $aid;
		$canread 	= true;//---> wait for change.
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
	function _loadCategoryParams($cid) {
		jimport("joomla.html.parameter");
		$mainframe = &JFactory::getApplication();

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
		$mainframe = &JFactory::getApplication();
		
		$user 		= &JFactory::getUser();
		$gid		= max ($user->getAuthorisedViewLevels());
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
 	function getAlphaindex() {
		$user		= & JFactory::getUser();
		$gid		= max ($user->getAuthorisedViewLevels());
		$lang 		= JRequest::getWord('lang', '' );
		// Get the category parameters
		$cparams 	= $this->_category->parameters;
		$gid_a		= $user->getAuthorisedViewLevels();
		$gids		= "'".implode("','", $gid_a)."'";
		// show unauthorized items
		$show_noauth = $cparams->get('show_noauth', 0);
		$owneritems = $cparams->get('owneritems', '0');
		
		// Filter the category view with the active active language
		$and = FLEXI_FISH ? ' AND ie.language LIKE ' . $this->_db->Quote( $lang .'%' ) : '';
		//Display all items or owner items
		$and2 = '';
		$and3 = '';
		if($owneritems) {
			$and2 .= " AND i.created_by='".$user->get('id')."'";
		}else{
			$and3 = ' AND i.state IN (1, -5)';
		}
		
		$and4 = $show_noauth ? '' : ' AND c.access IN ('.$gids.') AND i.access IN ('.$gids.')';
		
		$_group_cats = implode("','", $this->_group_cats);
		$query	= 'SELECT LOWER(SUBSTRING(i.title FROM 1 FOR 1)) AS alpha'
			. ' FROM #__content AS i'
			. ' LEFT JOIN #__flexicontent_items_ext AS ie ON i.id = ie.item_id'
			. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
			. ' LEFT JOIN #__categories AS c ON c.id IN (\''. $_group_cats .'\')'
			. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
			. ' WHERE rel.catid IN (\''. $_group_cats .'\')'
			. $and
			//. ' AND i.state IN (1, -5)'
			. $and2
			//. ' AND c.lft >= '.FLEXI_LFT_CATEGORY.' AND c.rgt<='.FLEXI_RGT_CATEGORY
			. $and3
			. $and4
			. ' GROUP BY alpha'
			. ' ORDER BY alpha ASC';
		;
		$this->_db->setQuery($query);
		$alpha = $this->_db->loadResultArray();
		return $alpha;
	}
}
?>
