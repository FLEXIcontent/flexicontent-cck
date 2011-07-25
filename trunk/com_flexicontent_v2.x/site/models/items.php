<?php
/**
 * @version 1.5 stable $Id: items.php 352 2010-06-29 11:52:33Z emmanuel.danan $
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

jimport('joomla.application.component.modellist');

/**
 * FLEXIcontent Component Item Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelItems extends JModelList{
	/**
	 * Details data in details array
	 *
	 * @var array
	 */
	var $_item = null;


	/**
	 * tags in array
	 *
	 * @var array
	 */
	var $_tags = null;

	/**
	 * id
	 *
	 * @var array
	 */
	var $_id = null;
	
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	public function __construct($config = array())
	{
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
			);
		}

		parent::__construct($config);
	}

	/**
	 * Method to set the item id
	 *
	 * @access	public
	 * @param	int	faq ID number
	 */

	function setId($id)
	{
		// Set new item id
		$this->_id			= $id;
		$this->_item		= null;
	}

	/**
	 * Overridden get method to get properties from the item
	 *
	 * @access	public
	 * @param	string	$property	The name of the property
	 * @param	mixed	$value		The value of the property to set
	 * @return 	mixed 				The value of the property
	 * @since	1.5
	 */
	function get($property, $default=null)
	{
		if ($this->_loadItem()) {
			if(isset($this->_item->$property)) {
				return $this->_item->$property;
			}
		}
		return $default;
	}
	
	/**
	 * Overridden set method to pass properties on to the item
	 *
	 * @access	public
	 * @param	string	$property	The name of the property
	 * @param	mixed	$value		The value of the property to set
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function set( $property, $value=null )
	{
		if ($this->_loadItem()) {
			$this->_item->$property = $value;
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @return	void
	 * @since	1.6
	 */
	protected function populateState($ordering = 'ordering', $direction = 'ASC') {
		$app = JFactory::getApplication();

		// List state information
		//$value = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'));
		$value = JRequest::getInt('limit', $app->getCfg('list_limit', 0));
		$this->setState('list.limit', $value);

		//$value = $app->getUserStateFromRequest($this->context.'.limitstart', 'limitstart', 0);
		$value = JRequest::getInt('limitstart', 0);
		$this->setState('list.start', $value);

		$orderCol	= JRequest::getCmd('filter_order', 'a.ordering');
		if (!in_array($orderCol, $this->filter_fields)) {
			$orderCol = 'a.ordering';
		}
		$this->setState('list.ordering', $orderCol);

		$listOrder	=  JRequest::getCmd('filter_order_Dir', 'ASC');
		if (!in_array(strtoupper($listOrder), array('ASC', 'DESC', ''))) {
			$listOrder = 'ASC';
		}
		$this->setState('list.direction', $listOrder);

		$params = $app->getParams();
		$this->setState('params', $params);
		$user		= JFactory::getUser();

		if ((!$user->authorise('core.edit.state', 'com_content')) &&  (!$user->authorise('core.edit', 'com_content'))){
			// filter on published for those who do not have edit or edit.state rights.
			$this->setState('filter.published', 1);
		}

		$this->setState('filter.language',$app->getLanguageFilter());

		// process show_noauth parameter
		if (!$params->get('show_noauth')) {
			$this->setState('filter.access', true);
		} else {
			$this->setState('filter.access', false);
		}

		$this->setState('layout', JRequest::getCmd('layout'));
	}
	/**
	 * Method to get a list of articles.
	 *
	 * Overriden to inject convert the attribs field into a JParameter object.
	 *
	 * @return	mixed	An array of objects on success, false on failure.
	 * @since	1.6
	 */
	public function getItems() {
		$items	= parent::getItems();
		$user	= JFactory::getUser();
		$userId	= $user->get('id');
		$guest	= $user->get('guest');
		$groups	= $user->getAuthorisedViewLevels();

		// Get the global params
		$globalParams = JComponentHelper::getParams('com_flexicontent', true);

		// Convert the parameter fields into objects.
		foreach($items as &$item) {
			$articleParams = new JRegistry;
			$articleParams->loadJSON($item->attribs);

			// Unpack readmore and layout params
			$item->alternative_readmore = $articleParams->get('alternative_readmore');
			$item->layout = $articleParams->get('layout');

			$item->params = clone $this->getState('params');

			// For blogs, article params override menu item params only if menu param = 'use_article'
			// Otherwise, menu item params control the layout
			// If menu item is 'use_article' and there is no article param, use global
			if ((JRequest::getString('layout') == 'blog') || (JRequest::getString('view') == 'featured')
				|| ($this->getState('params')->get('layout_type') == 'blog')) {
				// create an array of just the params set to 'use_article'
				$menuParamsArray = $this->getState('params')->toArray();
				$articleArray = array();

				foreach ($menuParamsArray as $key => $value)
				{
					if ($value === 'use_article') {
						// if the article has a value, use it
						if ($articleParams->get($key) != '') {
							// get the value from the article
							$articleArray[$key] = $articleParams->get($key);
						}
						else {
							// otherwise, use the global value
							$articleArray[$key] = $globalParams->get($key);
						}
					}
				}

				// merge the selected article params
				if (count($articleArray) > 0) {
					$articleParams = new JRegistry;
					$articleParams->loadArray($articleArray);
					$item->params->merge($articleParams);
				}
			}
			else {
				// For non-blog layouts, merge all of the article params
				$item->params->merge($articleParams);
			}

			// get display date
			switch ($item->params->get('show_date'))
			{
				case 'modified':
					$item->displayDate = $item->modified;
					break;

				case 'published':
					$item->displayDate = ($item->publish_up == 0) ? $item->created : $item->publish_up;
					break;

				default:
				case 'created':
					$item->displayDate = $item->created;
					break;
			}

			// Compute the asset access permissions.
			// Technically guest could edit an article, but lets not check that to improve performance a little.
			if (!$guest) {
				$asset	= 'com_content.article.'.$item->id;

				// Check general edit permission first.
				if ($user->authorise('core.edit', $asset)) {
					$item->params->set('access-edit', true);
				}
				// Now check if edit.own is available.
				else if (!empty($userId) && $user->authorise('core.edit.own', $asset)) {
					// Check for a valid user and that they are the owner.
					if ($userId == $item->created_by) {
						$item->params->set('access-edit', true);
					}
				}
			}

			$access = $this->getState('filter.access');

			if ($access) {
				// If the access filter has been set, we already have only the articles this user can view.
				$item->params->set('access-view', true);
			}
			else {
				// If no access filter is set, the layout takes some responsibility for display of limited information.
				if ($item->catid == 0 || $item->category_access === null) {
					$item->params->set('access-view', in_array($item->access, $groups));
				}
				else {
					$item->params->set('access-view', in_array($item->access, $groups) && in_array($item->category_access, $groups));
				}
			}
		}
		return $items;
	}
	
	/**
	 * Get the master query for retrieving a list of articles subject to the model state.
	 *
	 * @return	JDatabaseQuery
	 * @since	1.6
	 */
	function getListQuery() {
		// Create a new query object.
		$db = $this->getDbo();
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select(
			$this->getState(
				'list.select',
				'a.*, ie.*,c.lft,c.rgt,ua.name as author, ty.name AS typename,'
				. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug'
			)
		);
		// Process an Archived Article layout
		if ($this->getState('filter.published') == 2) {
			// If badcats is not null, this means that the article is inside an archived category
			// In this case, the state is set to 2 to indicate Archived (even if the article state is Published)
			$query->select($this->getState('list.select','CASE WHEN badcats.id is null THEN a.state ELSE 2 END AS state'));
		}
		else {
			// Process non-archived layout
			// If badcats is not null, this means that the article is inside an unpublished category
			// In this case, the state is set to 0 to indicate Unpublished (even if the article state is Published)
			$query->select($this->getState('list.select','CASE WHEN badcats.id is not null THEN 0 ELSE a.state END AS state'));
		}

		$query->from('#__content AS a');

		// Join over the frontpage articles.
		//if ($this->context != 'com_content.featured') {
		//	$query->join('LEFT', '#__content_frontpage AS fp ON fp.content_id = a.id');
		//}

		$this->_buildQueryJoin($query);

		// Join to check for category published state in parent categories up the tree
		$query->select('c.published, CASE WHEN badcats.id is null THEN c.published ELSE 0 END AS parents_published');
		$subquery = 'SELECT cat.id as id FROM #__categories AS cat JOIN #__categories AS parent ';
		$subquery .= 'ON cat.lft BETWEEN parent.lft AND parent.rgt ';
		$subquery .= 'WHERE parent.extension = ' . $db->quote('com_content');

		if ($this->getState('filter.published') == 2) {
			// Find any up-path categories that are archived
			// If any up-path categories are archived, include all children in archived layout
			$subquery .= ' AND parent.published = 2 GROUP BY cat.id ';
			// Set effective state to archived if up-path category is archived
			$publishedWhere = 'CASE WHEN badcats.id is null THEN a.state ELSE 2 END';
		}
		else {
			// Find any up-path categories that are not published
			// If all categories are published, badcats.id will be null, and we just use the article state
			$subquery .= ' AND parent.published != 1 GROUP BY cat.id ';
			// Select state to unpublished if up-path category is unpublished
			$publishedWhere = 'CASE WHEN badcats.id is null THEN a.state ELSE 0 END';
		}
		$query->join('LEFT OUTER', '(' . $subquery . ') AS badcats ON badcats.id = c.id');
		$user	= JFactory::getUser();
		// Filter by access level.
		if ($access = $this->getState('filter.access')) {
			$groups	= implode(',', $user->getAuthorisedViewLevels());
			$query->where('a.access IN ('.$groups.')');
		}

		// Filter by published state
		$published = $this->getState('filter.published');

		if (is_numeric($published)) {
			// Use article state if badcats.id is null, otherwise, force 0 for unpublished
			$query->where($publishedWhere . ' = ' . (int) $published);
		}
		else if (is_array($published)) {
			JArrayHelper::toInteger($published);
			$published = implode(',', $published);
			// Use article state if badcats.id is null, otherwise, force 0 for unpublished
			$query->where($publishedWhere . ' IN ('.$published.')');
		}

		// Filter by featured state
		$featured = $this->getState('filter.featured');
		switch ($featured)
		{
			case 'hide':
				$query->where('a.featured = 0');
				break;

			case 'only':
				$query->where('a.featured = 1');
				break;

			case 'show':
			default:
				// Normally we do not discriminate
				// between featured/unfeatured items.
				break;
		}

		// Filter by a single or group of articles.
		$articleId = $this->getState('filter.article_id');

		if (is_numeric($articleId)) {
			$type = $this->getState('filter.article_id.include', true) ? '= ' : '<> ';
			$query->where('a.id '.$type.(int) $articleId);
		}
		else if (is_array($articleId)) {
			JArrayHelper::toInteger($articleId);
			$articleId = implode(',', $articleId);
			$type = $this->getState('filter.article_id.include', true) ? 'IN' : 'NOT IN';
			$query->where('a.id '.$type.' ('.$articleId.')');
		}

		// Filter by a single or group of categories
		$categoryId = $this->getState('filter.category_id');

		if (is_numeric($categoryId)) {
			$type = $this->getState('filter.category_id.include', true) ? '= ' : '<> ';

			// Add subcategory check
			$includeSubcategories = $this->getState('filter.subcategories', false);
			$categoryEquals = 'a.catid '.$type.(int) $categoryId;

			if ($includeSubcategories) {
				$levels = (int) $this->getState('filter.max_category_levels', '1');
				// Create a subquery for the subcategory list
				$subQuery = $db->getQuery(true);
				$subQuery->select('sub.id');
				$subQuery->from('#__categories as sub');
				$subQuery->join('INNER', '#__categories as this ON sub.lft > this.lft AND sub.rgt < this.rgt');
				$subQuery->where('this.id = '.(int) $categoryId);
				if ($levels >= 0) {
					$subQuery->where('sub.level <= this.level + '.$levels);
				}

				// Add the subquery to the main query
				$query->where('('.$categoryEquals.' OR a.catid IN ('.$subQuery->__toString().'))');
			}
			else {
				$query->where($categoryEquals);
			}
		}
		else if (is_array($categoryId) && (count($categoryId) > 0)) {
			JArrayHelper::toInteger($categoryId);
			$categoryId = implode(',', $categoryId);
			if (!empty($categoryId)) {
				$type = $this->getState('filter.category_id.include', true) ? 'IN' : 'NOT IN';
				$query->where('a.catid '.$type.' ('.$categoryId.')');
			}
		}

		// Filter by author
		$authorId = $this->getState('filter.author_id');
		$authorWhere = '';

		if (is_numeric($authorId)) {
			$type = $this->getState('filter.author_id.include', true) ? '= ' : '<> ';
			$authorWhere = 'a.created_by '.$type.(int) $authorId;
		}
		else if (is_array($authorId)) {
			JArrayHelper::toInteger($authorId);
			$authorId = implode(',', $authorId);

			if ($authorId) {
				$type = $this->getState('filter.author_id.include', true) ? 'IN' : 'NOT IN';
				$authorWhere = 'a.created_by '.$type.' ('.$authorId.')';
			}
		}

		// Filter by author alias
		$authorAlias = $this->getState('filter.author_alias');
		$authorAliasWhere = '';

		if (is_string($authorAlias)) {
			$type = $this->getState('filter.author_alias.include', true) ? '= ' : '<> ';
			$authorAliasWhere = 'a.created_by_alias '.$type.$db->Quote($authorAlias);
		}
		else if (is_array($authorAlias)) {
			$first = current($authorAlias);

			if (!empty($first)) {
				JArrayHelper::toString($authorAlias);

				foreach ($authorAlias as $key => $alias)
				{
					$authorAlias[$key] = $db->Quote($alias);
				}

				$authorAlias = implode(',', $authorAlias);

				if ($authorAlias) {
					$type = $this->getState('filter.author_alias.include', true) ? 'IN' : 'NOT IN';
					$authorAliasWhere = 'a.created_by_alias '.$type.' ('.$authorAlias .
						')';
				}
			}
		}

		if (!empty($authorWhere) && !empty($authorAliasWhere)) {
			$query->where('('.$authorWhere.' OR '.$authorAliasWhere.')');
		}
		else if (empty($authorWhere) && empty($authorAliasWhere)) {
			// If both are empty we don't want to add to the query
		}
		else {
			// One of these is empty, the other is not so we just add both
			$query->where($authorWhere.$authorAliasWhere);
		}

		// Filter by start and end dates.
		//$nullDate	= $db->Quote($db->getNullDate());
		//$nowDate	= $db->Quote(JFactory::getDate()->toMySQL());

		//$query->where('( a.publish_up = '.$this->_db->Quote($nullDate).' OR a.publish_up <= '.$this->_db->Quote($nowDate).' )');
		//$query->where('( a.publish_down = '.$this->_db->Quote($nullDate).' OR a.publish_down >= '.$this->_db->Quote($nowDate).' )');

		// Filter by Date Range or Relative Date
		$dateFiltering = $this->getState('filter.date_filtering', 'off');
		$dateField = $this->getState('filter.date_field', 'a.created');

		switch ($dateFiltering)
		{
			case 'range':
				$startDateRange = $db->Quote($this->getState('filter.start_date_range', $nullDate));
				$endDateRange = $db->Quote($this->getState('filter.end_date_range', $nullDate));
				$query->where('('.$dateField.' >= '.$startDateRange.' AND '.$dateField .
					' <= '.$endDateRange.')');
				break;

			case 'relative':
				$relativeDate = (int) $this->getState('filter.relative_date', 0);
				$query->where($dateField.' >= DATE_SUB('.$nowDate.', INTERVAL ' .
					$relativeDate.' DAY)');
				break;

			case 'off':
			default:
				break;
		}

		// process the filter for list views with user-entered filters
		$params = $this->getState('params');

		if ((is_object($params)) && ($params->get('filter_field') != 'hide') && ($filter = $this->getState('list.filter'))) {
			// clean filter variable
			$filter = JString::strtolower($filter);
			$hitsFilter = intval($filter);
			$filter = $db->Quote('%'.$db->getEscaped($filter, true).'%', false);

			switch ($params->get('filter_field')) {
				case 'author':
					$query->where(
						'LOWER( CASE WHEN a.created_by_alias > '.$db->quote(' ').
						' THEN a.created_by_alias ELSE ua.name END ) LIKE '.$filter.' '
					);
					break;

				case 'hits':
					$query->where('a.hits >= '.$hitsFilter.' ');
					break;

				case 'title':
				default: // default to 'title' if parameter is not valid
					$query->where('LOWER( a.title ) LIKE '.$filter);
					break;
			}
		}
		$cid = JRequest::getInt('cid');
		$this->_buildQueryWhere($cid, $query);
		//from flexicontent
		// Second is to only select items the user has access to
		//$states = ((int)$user->get('gid') > 19) ? '1, -5, 0, -3, -4' : '1, -5';
		//$query->where('i.state IN ('.$states.')');

		// First thing we need to do is to select only the requested items
		//$query->where('rel.catid IN ('.$_group_cats.')';

		//$cid = JRequest::getInt('cid');
		//$_group_cats = array($cid);

		// Add the list ordering clause.
		$query->order($this->getState('list.ordering', 'a.ordering').' '.$this->getState('list.direction', 'ASC'));
		//echo "<xmp>";var_dump($query->__toString());echo "</xmp>";exit;
		//echo $query->__toString()."<br /><br />";
		return $query;
	}
	
	function _buildQueryJoin(&$query) {
		// Join over the categories.
		$query->select('c.title AS category_title, c.path AS category_route, c.access AS category_access, c.alias AS category_alias');
		$query->join('LEFT', '#__categories AS c ON c.id = a.catid');

		// Join over the users for the author and modified_by names.
		$query->select("CASE WHEN a.created_by_alias > ' ' THEN a.created_by_alias ELSE ua.name END AS author");
		$query->select("ua.email AS author_email");

		$query->join('LEFT', '#__users AS ua ON ua.id = a.created_by');
		$query->join('LEFT', '#__users AS uam ON uam.id = a.modified_by');

		// Join on contact table
		$query->select('contact.id as contactid' ) ;
		$query->join('LEFT','#__contact_details AS contact on contact.user_id = a.created_by');

		// Join over the categories to get parent category titles
		$query->select('parent.title as parent_title, parent.id as parent_id, parent.path as parent_route, parent.alias as parent_alias');
		$query->join('LEFT', '#__categories as parent ON parent.id = c.parent_id');

		// Join on voting table
		$query->select('ROUND( v.rating_sum / v.rating_count ) AS rating, v.rating_count as rating_count');
		$query->join('LEFT', '#__content_rating AS v ON a.id = v.content_id');

		//$query->join('LEFT', '#__flexicontent_items_ext AS ie ON ie.item_id = i.id');
		$query->join('LEFT', '#__flexicontent_items_ext AS ie ON ie.item_id = a.id');
		$query->join('LEFT', '#__flexicontent_types AS ty ON ie.type_id = ty.id');
		$query->join('LEFT', '#__flexicontent_cats_item_relations AS rel ON rel.itemid = a.id');
		////. ' LEFT JOIN #__categories AS c ON c.id = '. $this->_id
		//. ' LEFT JOIN #__categories AS c ON c.id = i.catid'
		//. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
	}
	
	/**
	 * Method to build the WHERE clause
	 *
	 * @access private
	 * @return array
	 */
	function _buildQueryWhere($cid, &$query) {
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');

		$user		= & JFactory::getUser();
		$gid		= (int) $user->get('aid');
		$now		= $mainframe->get('requestTime');
		$nullDate	= $this->_db->getNullDate();
		
		$_group_cats = array($cid);
		//Get active menu parameters.
		$menus		= & JSite::getMenu();
		$menu    	= $menus->getActive();

		// Get the category parameters
		$cparams 	= $this->_category_parameters;

		// display sub-categories
		$display_subcats = $cparams->get('display_subcategories_items', 0);
		if($display_subcats) {
			if(is_array($this->_childs))
				foreach($this->_childs as $ch)
					$_group_cats[] = $ch->id;
		}
		$_group_cats = array_unique($_group_cats);
		$this->_group_cats = $_group_cats;
		$_group_cats = "'".implode("','", $_group_cats)."'";
		
		// shortcode of the site active language (joomfish)
		$lang 		= JRequest::getWord('lang', '' );
		// content language parameter UNUSED
		$filterlang = $cparams->get('language', '');
		$filtercat  = $cparams->get('filtercat', 0);
		// show unauthorized items
		$show_noauth = $cparams->get('show_noauth', 0);
		
		// First thing we need to do is to select only the requested items
		$query->where('rel.catid IN ('.$_group_cats.')');

		// Second is to only select items the user has access to
		$states = ((int)$user->get('gid') > 19) ? '1, -5, 0, -3, -4' : '1, -5';
		$query->where('a.state IN ('.$states.')');
		
		// is the content current?
		$query->where('( a.publish_up = '.$this->_db->Quote($nullDate).' OR a.publish_up <= '.$this->_db->Quote($now).' )');
		$query->where('( a.publish_down = '.$this->_db->Quote($nullDate).' OR a.publish_down >= '.$this->_db->Quote($now).' )');


		// Filter the category view with the active active language
		/*if (FLEXI_FISH && $filtercat) {
			$where .= ' AND ie.language LIKE ' . $this->_db->Quote( $lang .'%' );
		}*/
		
		$query->where('c.lft >= ' . $this->_db->Quote(FLEXI_CATEGORY_LFT) .' AND c.rgt<= ' . $this->_db->Quote(FLEXI_CATEGORY_RGT));

		// Select only items user has access to if he is not allowed to show unauthorized items
		if (!$show_noauth) {
			if (FLEXI_ACCESS) {
				$query->where('(gi.aro IN ( '.$user->gmid.' ) OR a.access <= '. (int) $gid . ')');
			} else {
				//$where .= ' AND i.access <= '.$gid;
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

				$query->where('LOWER( a.title ) LIKE '.$this->_db->Quote( '%'.$this->_db->getEscaped( $filter, true ).'%', false ));
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
		 				$query->where('a.id IN (' . $ids . ')');
					} else {
		 				$query->where('a.id = 0');
					}
				}
			}
		}
		$session  =& JFactory::getSession();
		$alpha = JRequest::getVar('letter');
		if($alpha===NULL) {
			$alpha =  $session->get($option.'.category.letter');
		}else{
			$session->set($option.'.category.letter', $alpha);
		}
		if ($alpha == '0') {
			$query->where('LOWER( a.title ) LIKE '.$this->_db->Quote( $this->_db->getEscaped( $alpha, true ).'%', false ));
		}		
		elseif (!empty($alpha)) {
			$query->where('LOWER( a.title ) LIKE '.$this->_db->Quote( $this->_db->getEscaped( $alpha, true ).'%', false ));
		}
		
		// Filter by language
		if ($this->getState('filter.language')) {
			$query->where('a.language in ('.$db->quote(JFactory::getLanguage()->getTag()).','.$db->quote('*').')');
			$query->where('(contact.language in ('.$db->quote(JFactory::getLanguage()->getTag()).','.$db->quote('*').') OR contact.language IS NULL)');
		}
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
		$gid		= (int) $user->get('aid');
		//$params 	= $this->_loadCategoryParams($this->_id);
		$params 	= $this->_category_parameters;
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
	 * Method to load required data
	 *
	 * @access	private
	 * @return	array
	 * @since	1.0
	 */
	function _loadItem() {
		$loadcurrent = JRequest::getVar('loadcurrent', false, 'request', 'boolean');
		// Lets load the item if it doesn't already exist
		if (empty($this->_item)) {
			$cparams =& JComponentHelper::getParams( 'com_flexicontent' );
			$use_versioning = $cparams->get('use_versioning', 1);

			$where	= $this->_buildItemWhere();
			$query = 'SELECT i.*, ie.*, c.access AS cataccess, c.id AS catid, c.published AS catpublished,'
			. ' u.name AS author, u.usertype, ty.name AS typename,'
			. ' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
			. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
			. ' FROM #__content AS i'
			. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
			. ' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
			. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
			. ' LEFT JOIN #__categories AS c ON c.id = rel.catid'
			. ' LEFT JOIN #__users AS u ON u.id = i.created_by'
			. $where
			;
			$this->_db->setQuery($query);
			$item = $this->_db->loadObject();
			if(!$item) {
				return false;
			}

			$isnew = (($this->_id <= 0) || !$this->_id);
			$current_version = isset($item->version)?$item->version:0;
			$version = JRequest::getVar( 'version', 0, 'request', 'int' );
			//$lastversion = $use_versioning?FLEXIUtilities::getLastVersions($this->_id, true, true):$current_version;
			$lastversion = $use_versioning?FLEXIUtilities::getLastVersions($this->_id, true):$current_version;
			if($version==0) 
				JRequest::setVar( 'version', $version = ($loadcurrent?$current_version:$lastversion));
			if($use_versioning) {
				$query = "SELECT f.id,iv.value,f.field_type,f.name FROM #__flexicontent_items_versions as iv "
					." LEFT JOIN #__flexicontent_fields as f on f.id=iv.field_id "
					." WHERE iv.version='".$version."' AND iv.item_id='".$this->_id."';";
			}else{
				$query = "SELECT f.id,iv.value,f.field_type,f.name FROM #__flexicontent_fields_item_relations as iv "
					." LEFT JOIN #__flexicontent_fields as f on f.id=iv.field_id "
					." WHERE iv.item_id='".$this->_id."';";
			}
			$this->_db->setQuery($query);
			$fields = $this->_db->loadObjectList();
			$fields = $fields?$fields:array();

			foreach($fields as $f) {
				$fieldname = $f->name;
				if( (($f->field_type=='categories') && ($f->name=='categories')) || (($f->field_type=='tags') && ($f->name=='tags')) ) {
					$item->$fieldname = unserialize($f->value);
				}else{
					$item->$fieldname = $f->value;
				}
			}
			
			if(!isset($item->tags)||!is_array($item->tags)) {
				$query = 'SELECT DISTINCT tid FROM #__flexicontent_tags_item_relations WHERE itemid = ' . (int)$this->_id;
				$this->_db->setQuery($query);
				$item->tags = $this->_db->loadResultArray();
			}
			if(!isset($item->categories)||!is_array($item->categories)) {
				$query = 'SELECT DISTINCT catid FROM #__flexicontent_cats_item_relations WHERE itemid = ' . (int)$this->_id;
				$this->_db->setQuery($query);
				$item->categories = $this->_db->loadResultArray();
			}
			$item->id = $this->_id;

			$query = "SELECT t.name as typename, cr.rating_count, ((cr.rating_sum / cr.rating_count)*20) as score"
					." FROM #__flexicontent_items_ext as ie "
					. " LEFT JOIN #__content_rating AS cr ON cr.content_id = ie.item_id"
					." LEFT JOIN #__flexicontent_types AS t ON ie.type_id = t.id"
					." WHERE ie.item_id='".$this->_id."';";
			$this->_db->setQuery($query);
			$type = $this->_db->loadObject();
			if($type) {
				$item->typename = $type->typename;
				$item->rating_count = $type->rating_count;
				$item->score = $type->score;
				$item->version = $current_version;
			}else{
				$item->version = 0;
				$item->score = 0;
			}
			if($isnew) {
				$createdate = & JFactory::getDate();
				$nullDate	= $this->_db->getNullDate();
				$item->created 		= $createdate->toUnix();
				$item->modified 	= $nullDate;
				$item->publish_up 	= $createdate->toUnix();
				$item->publish_down = JText::_( 'FLEXI_NEVER' );
				$item->state 		= -4;
			}

			if($version == $current_version) {
				//$item->text = $item->introtext;
				$item->text = $item->introtext . '<hr id="system-readmore" />' . $item->fulltext;
			}
			$this->_item = &$item;
			if(!$isnew && $use_versioning && ($current_version>$lastversion) ) {//add current version.
				$mainframe = &JFactory::getApplication();
				$query = "SELECT f.id,fir.value,f.field_type,f.name,fir.valueorder "
						." FROM #__flexicontent_fields_item_relations as fir"
						//." LEFT JOIN #__flexicontent_items_versions as iv ON iv.field_id="
						." LEFT JOIN #__flexicontent_fields as f on f.id=fir.field_id "
						." WHERE fir.item_id='".$this->_id."';";
				$this->_db->setQuery($query);
				$fields = $this->_db->loadObjectList();
				$jcorefields = flexicontent_html::getJCoreFields();
				$catflag = false;
				$tagflag = false;
				$clean_database = true;
				if(!$clean_database && $fields) {
					$query = 'DELETE FROM #__flexicontent_fields_item_relations WHERE item_id = '.$this->_id;
					$this->_db->setQuery($query);
					$this->_db->query();
				}
				foreach($fields as $field) {
					// process field mambots onBeforeSaveField
					//$results = $mainframe->triggerEvent('onBeforeSaveField', array( $field, &$post[$field->name], &$files[$field->name] ));

					// add the new values to the database 
					$obj = new stdClass();
					$obj->field_id 		= $field->id;
					$obj->item_id 		= $this->_id;
					$obj->valueorder	= $field->valueorder;;
					$obj->version		= (int)$current_version;
					// @TODO : move in the plugin code
					if( ($field->field_type=='categories') && ($field->name=='categories') ) {
						continue;
					}elseif( ($field->field_type=='tags') && ($field->name=='tags') ) {
						continue;
					}else{
						$obj->value			= $field->value;
					}
					$this->_db->insertObject('#__flexicontent_items_versions', $obj);
					if( !$clean_database && !isset($jcorefields[$field->name]) && !in_array($field->field_type, $jcorefields)) {
						unset($obj->version);
						$this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
					}
					// process field mambots onAfterSaveField
					//$results		 = $dispatcher->trigger('onAfterSaveField', array( $field, &$post[$field->name], &$files[$field->name] ));
					//$searchindex 	.= @$field->search;
				}
				if(!$catflag) {
					$obj = new stdClass();
					$obj->field_id 		= 13;
					$obj->item_id 		= $this->_id;
					$obj->valueorder	= 1;
					$obj->version		= (int)$current_version;
					$obj->value		= serialize($item->categories);
					$this->_db->insertObject('#__flexicontent_items_versions', $obj);
				}
				if(!$tagflag) {
					$obj = new stdClass();
					$obj->field_id 		= 14;
					$obj->item_id 		= $this->_id;
					$obj->valueorder	= 1;
					$obj->version		= (int)$current_version;
					$obj->value		= serialize($item->tags);
					$this->_db->insertObject('#__flexicontent_items_versions', $obj);
				}
				$v = new stdClass();
				$v->item_id 		= (int)$item->id;
				$v->version_id		= (int)$current_version;
				$v->created 	= $item->created;
				$v->created_by 	= $item->created_by;
				//$v->comment		= 'kept current version to version table.';
				$this->_db->insertObject('#__flexicontent_versions', $v);
			}
			return (boolean) $this->_item;
		}
		return true;
	}
	
	/**
	 * Method to build the WHERE clause of the query to select a content item
	 *
	 * @access	private
	 * @return	string	WHERE clause
	 * @since	1.5
	 */
	function _buildItemWhere()
	{
		global $mainframe;

		$user		=& JFactory::getUser();
		$aid		= (int) $user->get('aid', 0);

		$jnow		=& JFactory::getDate();
		$now		= $jnow->toMySQL();
		$nullDate	= $this->_db->getNullDate();

		/*
		 * First thing we need to do is assert that the content article is the one
		 * we are looking for and we have access to it.
		 */
		$where = ' WHERE i.id = '. (int) $this->_id;

		if ($aid < 2)
		{
			$where .= ' AND ( i.publish_up = '.$this->_db->Quote($nullDate).' OR i.publish_up <= '.$this->_db->Quote($now).' )';
			$where .= ' AND ( i.publish_down = '.$this->_db->Quote($nullDate).' OR i.publish_down >= '.$this->_db->Quote($now).' )';
		}

		return $where;
	}

	/**
	 * Method to load content article parameters
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	function _loadItemParams()
	{
		global $mainframe;

		// Get the page/component configuration
		$params = clone($mainframe->getParams('com_flexicontent'));

		$query = 'SELECT t.attribs'
				. ' FROM #__flexicontent_types AS t'
				. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.type_id = t.id'
				. ' WHERE ie.item_id = ' . (int)$this->_id
				;
		$this->_db->setQuery($query);
		$tparams = $this->_db->loadResult();
		
		// Merge type parameters into the page configuration
		$tparams = new JParameter($tparams);
		$params->merge($tparams);

		// Merge item parameters into the page configuration
		$iparams = new JParameter($this->_item->attribs);
		$params->merge($iparams);

/*
		// Set the popup configuration option based on the request
		$pop = JRequest::getVar('pop', 0, '', 'int');
		$params->set('popup', $pop);

		// Are we showing introtext with the article
		if (!$params->get('show_intro') && !empty($this->_article->fulltext)) {
			$this->_article->text = $this->_article->fulltext;
		} else {
			$this->_article->text = $this->_article->introtext . chr(13).chr(13) . $this->_article->fulltext;
		}
*/

		// Set the article object's parameters
		$this->_item->parameters = & $params;
	}

	/**
	 * Method to get the tags
	 *
	 * @access	public
	 * @return	object
	 * @since	1.0
	 */
	function getTagsX() {
		$query = 'SELECT DISTINCT t.name,'
		. ' CASE WHEN CHAR_LENGTH(t.alias) THEN CONCAT_WS(\':\', t.id, t.alias) ELSE t.id END as slug'
		. ' FROM #__flexicontent_tags AS t'
		. ' LEFT JOIN #__flexicontent_tags_item_relations AS i ON i.tid = t.id'
		. ' WHERE i.itemid = ' . (int) $this->_id
		. ' AND t.published = 1'
		. ' ORDER BY t.name'
		;

		$this->_db->setQuery( $query );

		$this->_tags = $this->_db->loadObjectList();

		return $this->_tags;
	}
	
	/**
	 * Method to fetch tags
	 * 
	 * @return object
	 * @since 1.0
	 */
	function gettags($mask="") {
		$where = ($mask!="")?" name like '%$mask%' AND":"";
		$query = 'SELECT * FROM #__flexicontent_tags WHERE '.$where.' published = 1 ORDER BY name';
		$this->_db->setQuery($query);
		$tags = $this->_db->loadObjectlist();
		return $tags;
	}

	/**
	 * Method to get the categories
	 *
	 * @access	public
	 * @return	object
	 * @since	1.0
	 */
	function getCategories()
	{
		$query = 'SELECT DISTINCT c.id, c.title,'
		. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as slug'
		. ' FROM #__categories AS c'
		. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.catid = c.id'
		. ' WHERE rel.itemid = '.$this->_id
		;

		$this->_db->setQuery( $query );

		$this->_cats = $this->_db->loadObjectList();
		return $this->_cats;
	}

	/**
	 * Method to increment the hit counter for the item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function hit() {
		global $mainframe;

		if ($this->_id)
		{
			$item = & JTable::getInstance('flexicontent_items', '');
			$item->hit($this->_id);
			return true;
		}
		return false;
	}

	function getAlltags() {
		$query = 'SELECT * FROM #__flexicontent_tags ORDER BY name';
		$this->_db->setQuery($query);
		$tags = $this->_db->loadObjectlist();
		return $tags;
	}

	function getUsedtags() {
		if(!@$this->_id) $this->_item->tags = array();
		if(@$this->_id && !@$this->_item->tags) {
			$query = 'SELECT tid FROM #__flexicontent_tags_item_relations WHERE itemid = ' . (int)$this->_id;
			$this->_db->setQuery($query);
			$this->_item->tags = $this->_db->loadResultArray();
		}
		return $this->_item->tags;
	}

	/**
	 * Tests if item is checked out
	 *
	 * @access	public
	 * @param	int	A user id
	 * @return	boolean	True if checked out
	 * @since	1.0
	 */
	function isCheckedOut( $uid=0 )
	{
		if ($this->_loadItem())
		{
			if ($uid) {
				return ($this->_item->checked_out && $this->_item->checked_out != $uid);
			} else {
				return $this->_item->checked_out;
			}
		} elseif ($this->_id < 1) {
			return false;
		} else {
			JError::raiseWarning( 0, 'Unable to Load Data');
			return false;
		}
	}

	/**
	 * Method to checkin/unlock the item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function checkin()
	{
		if ($this->_id)
		{
			$item = & JTable::getInstance('flexicontent_items', '');
			return $item->checkin($this->_id);
		}
		return false;
	}

	/**
	 * Method to checkout/lock the item
	 *
	 * @access	public
	 * @param	int	$uid	User ID of the user checking the item out
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function checkout($uid = null)
	{
		if ($this->_id)
		{
			// Make sure we have a user id to checkout the item with
			if (is_null($uid)) {
				$user	=& JFactory::getUser();
				$uid	= $user->get('id');
			}
			// Lets get to it and checkout the thing...
			$item = & JTable::getInstance('flexicontent_items', '');
			return $item->checkout($uid, $this->_id);
		}
		return false;
	}
	
	/**
	 * Method to store the item
	 *
	 * @access	public
	 * @since	1.0
	 */
	function store($data) {
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$mainframe = &JFactory::getApplication();
		$item  	=& $this->getTable('flexicontent_items', '');
		$user	=& JFactory::getUser();
		
		//$details		= JRequest::getVar( 'details', array(), 'post', 'array');
		$details 		= array();
		$tags 			= JRequest::getVar( 'tag', array(), 'post', 'array');
		$cats 			= JRequest::getVar( 'cid', array(), 'post', 'array');
		$post 			= JRequest::get( 'post', JREQUEST_ALLOWRAW );
		$post['vstate'] = (int)$post['vstate'];
		$typeid 		= JRequest::getVar('typeid', 0, '', 'int');

		// bind it to the table
		if (!$item->bind($data)) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		$item->bind($details);

		// sanitise id field
		$item->id = (int) $item->id;
		
		$nullDate	= $this->_db->getNullDate();

		$version = FLEXIUtilities::getLastVersions($item->id, true);
		$version = is_array($version)?0:$version;
		$current_version = FLEXIUtilities::getCurrentVersions($item->id, true);
		$isnew = false;
		$tags = array_unique($tags);
		$cparams =& JComponentHelper::getParams( 'com_flexicontent' );
		$use_versioning = $cparams->get('use_versioning', 1);
		
		if( ($isnew = !$item->id) || ($post['vstate']==2) ) {

			$config =& JFactory::getConfig();
			$tzoffset = $config->getValue('config.offset');

			if ($isnew = !$item->id) {
				$item->modified 	= $nullDate;
				$item->modified_by 	= 0;
			} else {
				$mdate =& JFactory::getDate();
				$item->modified 	= $mdate->toMySQL();
				$item->modified_by 	= (int)$user->id;
			}
			// Are we saving from an item edit?
			// This code comes directly from the com_content

			$item->created_by 	= $item->created_by ? $item->created_by : $user->get('id');

			if ($item->created && strlen(trim( $item->created )) <= 10) {
				$item->created 	.= ' 00:00:00';
			}

			if ($isnew) {
				$date =& JFactory::getDate($item->created, $tzoffset);
				$item->created = $date->toMySQL();
			}

			// Append time if not added to publish date
			if (strlen(trim($item->publish_up)) <= 10) {
				$item->publish_up .= ' 00:00:00';
			}

			$date =& JFactory::getDate($item->publish_up, $tzoffset);
			$item->publish_up = $date->toMySQL();

			// Handle never unpublish date
			if (trim($item->publish_down) == JText::_('Never') || trim( $item->publish_down ) == '')
			{
				$item->publish_down = $nullDate;
			}
			else
			{
				if (strlen(trim( $item->publish_down )) <= 10) {
					$item->publish_down .= ' 00:00:00';
				}
				$date =& JFactory::getDate($item->publish_down, $tzoffset);
				$item->publish_down = $date->toMySQL();
			}


			// auto assign the main category if none selected
			if (!$item->catid) {
				$item->catid 		= $cats[0];
			}
			
			// auto assign the section
			$item->sectionid 	= FLEXI_CATEGORY;

			// set type and language
 			$item->type_id 		= (int)$typeid;
 			$item->language		= flexicontent_html::getSiteDefaultLang();			
			
			// Get a state and parameter variables from the request
			$item->state	= JRequest::getVar( 'state', 0, '', 'int' );
			$oldstate		= JRequest::getVar( 'oldstate', 0, '', 'int' );
			$params			= JRequest::getVar( 'params', null, 'post', 'array' );

			// Build parameter INI string
			if (is_array($params))
			{
				$txt = array ();
				foreach ($params as $k => $v) {
					if (is_array($v)) {
						$v = implode('|', $v);
					}
					$txt[] = "$k=$v";
				}
				$item->attribs = implode("\n", $txt);
			}

			// Get metadata string
			$metadata = JRequest::getVar( 'meta', null, 'post', 'array');
			if (is_array($params))
			{
				$txt = array();
				foreach ($metadata as $k => $v) {
					if ($k == 'description') {
						$item->metadesc = $v;
					} elseif ($k == 'keywords') {
						$item->metakey = $v;
					} else {
						$txt[] = "$k=$v";
					}
				}
				$item->metadata = implode("\n", $txt);
			}
					
			// Clean text for xhtml transitional compliance
			$text = str_replace('<br>', '<br />', $data['text']);
			
			// Search for the {readmore} tag and split the text up accordingly.
			$pattern = '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
			$tagPos	= preg_match($pattern, $text);

			if ($tagPos == 0)	{
				$item->introtext	= $text;
			} else 	{
				list($item->introtext, $item->fulltext) = preg_split($pattern, $text, 2);
			}
			
			if (!$item->id) {
				$item->ordering = $item->getNextOrder();
			}
			
			// Make sure the data is valid
			if (!$item->check()) {
				$this->setError($item->getError());
				return false;
			}
			if(!$use_versioning) {
				$item->version = $isnew?1:($current_version+1);
			}else{
				$item->version = $isnew?1:(($post['vstate']==2)?($version+1):$current_version);
			}
			// Store it in the db
			if (!$item->store()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			if (FLEXI_ACCESS) {
				$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
				$canRight 	= (in_array('right', $rights) || $user->gid >= 24);
				if ($canRight) FAccess::saveaccess( $item, 'item' );
			}

			$this->_item	=& $item;

			//if($post['vstate']==2) {
				//store tag relation
				$query = 'DELETE FROM #__flexicontent_tags_item_relations WHERE itemid = '.$item->id;
				$this->_db->setQuery($query);
				$this->_db->query();
				foreach($tags as $tag)
				{
					$query = 'INSERT INTO #__flexicontent_tags_item_relations (`tid`, `itemid`) VALUES(' . $tag . ',' . $item->id . ')';
					$this->_db->setQuery($query);
					$this->_db->query();
				}
			//}

			// Store categories to item relations

			// Add the primary cat to the array if it's not already in
			if (!in_array($item->catid, $cats)) {
				$cats[] = $item->catid;
			}

			//At least one category needs to be assigned
			if (!is_array( $cats ) || count( $cats ) < 1) {
				$this->setError('FLEXI_SELECT_CATEGORY');
				return false;
			}

			//if($isnew || $post['vstate']==2) {
			// delete only relations which are not part of the categories array anymore to avoid loosing ordering
			$query 	= 'DELETE FROM #__flexicontent_cats_item_relations'
					. ' WHERE itemid = '.$item->id
					. ($cats ? ' AND catid NOT IN (' . implode(', ', $cats) . ')' : '')
					;
			$this->_db->setQuery($query);
			$this->_db->query();

			// draw an array of the used categories
			$query 	= 'SELECT catid'
					. ' FROM #__flexicontent_cats_item_relations'
					. ' WHERE itemid = '.$item->id
					;
			$this->_db->setQuery($query);
			$used = $this->_db->loadResultArray();

			foreach($cats as $cat) {
				// insert only the new records
				if (!in_array($cat, $used)) {
					$query 	= 'INSERT INTO #__flexicontent_cats_item_relations (`catid`, `itemid`)'
							.' VALUES(' . $cat . ',' . $item->id . ')'
							;
					$this->_db->setQuery($query);
					$this->_db->query();
				}
			}
			//}
		} else {
			$datenow =& JFactory::getDate();
			$item->modified 		= $datenow->toMySQL();
			$item->modified_by 		= $user->get('id');
			// Add the primary cat to the array if it's not already in
			if (!in_array($item->catid, $cats)) {
				$cats[] = $item->catid;
			}

			//At least one category needs to be assigned
			if (!is_array( $cats ) || count( $cats ) < 1) {
				$this->setError('FLEXI_SELECT_CATEGORY');
				return false;
			}
		}
		$post['categories'][0] = $cats;
		$post['tags'][0] = $tags;
		///////////////////////////////
		// store extra fields values //
		///////////////////////////////
		
		// get the field object
		$this->_id 	= $item->id;	
		$fields		= $this->getExtrafields();
		$dispatcher = & JDispatcher::getInstance();
		
		// NOTE: This event isn't used yet but may be useful in a near future
		$results = $dispatcher->trigger('onAfterSaveItem', array( $item ));
		
		// versioning backup procedure
		// first see if versioning feature is enabled
		if ($use_versioning) {
			$v = new stdClass();
			$v->item_id 		= (int)$item->id;
			$v->version_id		= (int)$version+1;
			$v->modified		= $item->modified;
			$v->modified_by		= $item->modified_by;
			$v->created 		= $item->created;
			$v->created_by 		= $item->created_by;
		}
		if($post['vstate']==2) {
			$query = 'DELETE FROM #__flexicontent_fields_item_relations WHERE item_id = '.$item->id;
			$this->_db->setQuery($query);
			$this->_db->query();
		}
		if ($fields) {
			$files	= JRequest::get( 'files', JREQUEST_ALLOWRAW );
			$searchindex = '';
			$jcorefields = flexicontent_html::getJCoreFields();
			foreach($fields as $field) {
				// process field mambots onBeforeSaveField
				$results = $mainframe->triggerEvent('onBeforeSaveField', array( $field, &$post[$field->name], &$files[$field->name] ));

				// add the new values to the database 
				if (is_array($post[$field->name])) {
					$postvalues = $post[$field->name];
					$i = 1;
					foreach ($postvalues as $postvalue) {
						$obj = new stdClass();
						$obj->field_id 		= $field->id;
						$obj->item_id 		= $item->id;
						$obj->valueorder	= $i;
						$obj->version		= (int)$version+1;
						// @TODO : move to the plugin code
						if (is_array($postvalue)) {
							$obj->value			= serialize($postvalue);
						} else {
							$obj->value			= $postvalue;
						}
						if ($use_versioning)
							$this->_db->insertObject('#__flexicontent_items_versions', $obj);
						if(
							($isnew || ($post['vstate']==2) )
							&& !isset($jcorefields[$field->name])
							&& !in_array($field->field_type, $jcorefields)
							&& ( ($field->field_type!='categories') || ($field->name!='categories') )
							&& ( ($field->field_type!='tags') || ($field->name!='tags') )
						) {
							unset($obj->version);
							$this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
						}
						$i++;
					}
				} else if ($post[$field->name]) {
					$obj = new stdClass();
					$obj->field_id 		= $field->id;
					$obj->item_id 		= $item->id;
					$obj->valueorder	= 1;
					$obj->version		= (int)$version+1;
					// @TODO : move in the plugin code
					if (is_array($post[$field->name])) {
						$obj->value			= serialize($post[$field->name]);
					} else {
						$obj->value			= $post[$field->name];
					}
					if($use_versioning)
						$this->_db->insertObject('#__flexicontent_items_versions', $obj);
					if(
						($isnew || ($post['vstate']==2) )
						&& !isset($jcorefields[$field->name])
						&& !in_array($field->field_type, $jcorefields)
						&& ( ($field->field_type!='categories') || ($field->name!='categories') )
						&& ( ($field->field_type!='tags') || ($field->name!='tags') )
					) {
						unset($obj->version);
						$this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
					}
				}
				// process field mambots onAfterSaveField
				$results		 = $dispatcher->trigger('onAfterSaveField', array( $field, &$post[$field->name], &$files[$field->name] ));
				$searchindex 	.= @$field->search;
			}
	
			// store the extended data if the version is approved
			if( ($isnew = !$item->id) || ($post['vstate']==2) ) {
				$item->search_index = $searchindex;
				// Make sure the data is valid
				if (!$item->check()) {
					$this->setError($item->getError());
					return false;
				}
				// Store it in the db
				if (!$item->store()) {
					$this->setError($this->_db->getErrorMsg());
					return false;
				}
			}
		}
		if ($use_versioning) {
			if ($v->modified != $nullDate) {
				$v->created 	= $v->modified;
				$v->created_by 	= $v->modified_by;
			}
			
			$v->comment		= isset($post['versioncomment'])?htmlspecialchars($post['versioncomment'], ENT_QUOTES):'';
			unset($v->modified);
			unset($v->modified_by);
			$this->_db->insertObject('#__flexicontent_versions', $v);
		}
		
		// delete old versions
		$vcount	= FLEXIUtilities::getVersionsCount($item->id);
		$vmax	= $cparams->get('nr_versions', 10);

		if ($vcount > ($vmax+1)) {
			$deleted_version = FLEXIUtilities::getFirstVersion($this->_id, $vmax, $current_version);
			// on efface les versions en trop
			$query = 'DELETE'
					.' FROM #__flexicontent_items_versions'
					.' WHERE item_id = ' . (int)$this->_id
					.' AND version <' . $deleted_version
					.' AND version!=' . (int)$current_version
					;
			$this->_db->setQuery($query);
			$this->_db->query();

			$query = 'DELETE'
					.' FROM #__flexicontent_versions'
					.' WHERE item_id = ' . (int)$this->_id
					.' AND version_id <' . $deleted_version
					.' AND version_id!=' . (int)$current_version
					;
			$this->_db->setQuery($query);
			$this->_db->query();
		}
		return true;
	}

	/**
	 * Method to store a vote
	 * Deprecated
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function storevote($id, $vote)
	{
		if ($vote == 1) {
			$target = 'plus';
		} elseif ($vote == 0) {
			$target = 'minus';
		} else {
			return false;
		}

		$query = 'UPDATE #__flexicontent_items_ext'
		.' SET '.$target.' = ( '.$target.' + 1 )'
		.' WHERE item_id = '.(int)$id
		;
		$this->_db->setQuery($query);
		$this->_db->query();

		return true;
	}

	/**
	 * Method to get the categories an item is assigned to
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function getCatsselected()
	{
		if(!@$this->_item->categories) {
			$query = 'SELECT DISTINCT catid FROM #__flexicontent_cats_item_relations WHERE itemid = ' . (int)$this->_id;
			$this->_db->setQuery($query);
			$this->_item->categories = $this->_db->loadResultArray();
		}
		return $this->_item->categories;
	}

	/**
	 * Method to store the tag
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function storetag($data)
	{
		$row  =& $this->getTable('flexicontent_tags', '');

		// bind it to the table
		if (!$row->bind($data)) {
			JError::raiseError(500, $this->_db->getErrorMsg() );
			return false;
		}

		// Make sure the data is valid
		if (!$row->check()) {
			$this->setError($row->getError());
			return false;
		}

		// Store it in the db
		if (!$row->store()) {
			JError::raiseError(500, $this->_db->getErrorMsg() );
			return false;
		}
		$this->_tag = &$row;
		return $row->id;
	}

	/**
	 * Method to add a tag
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function addtag($name) {
		$obj = new stdClass();
		$obj->name	 	= $name;
		$obj->published	= 1;

		//$this->storetag($obj);
		if($this->storetag($obj)) {
			return true;
		}
		return false;
	}

	/**
	 * Method to get the nr of favourites of anitem
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function getFavourites()
	{
		$query = 'SELECT COUNT(id) AS favs FROM #__flexicontent_favourites WHERE itemid = '.(int)$this->_id;
		$this->_db->setQuery($query);
		$favs = $this->_db->loadResult();
		return $favs;
	}

	/**
	 * Method to get the nr of favourites of an user
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function getFavoured()
	{
		$user = JFactory::getUser();

		$query = 'SELECT COUNT(id) AS fav FROM #__flexicontent_favourites WHERE itemid = '.(int)$this->_id.' AND userid= '.(int)$user->id;
		$this->_db->setQuery($query);
		$fav = $this->_db->loadResult();
		return $fav;
	}
	
	/**
	 * Method to remove a favourite
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function removefav()
	{
		$user = JFactory::getUser();

		$query = 'DELETE FROM #__flexicontent_favourites WHERE itemid = '.(int)$this->_id.' AND userid = '.(int)$user->id;
		$this->_db->setQuery($query);
		$remfav = $this->_db->query();
		return $remfav;
	}
	
	/**
	 * Method to add a favourite
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function addfav()
	{
		$user = JFactory::getUser();

		$obj = new stdClass();
		$obj->itemid 	= $this->_id;
		$obj->userid	= $user->id;

		$addfav = $this->_db->insertObject('#__flexicontent_favourites', $obj);
		return $addfav;
	}
	
	/**
	 * Method to change the state of an item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function setitemstate($id, $state = 1)
	{
		$user 	=& JFactory::getUser();
		
		if ( $id )
		{

			$query = 'UPDATE #__content'
				. ' SET state = ' . (int)$state
				. ' WHERE id = '.(int)$id
				. ' AND ( checked_out = 0 OR ( checked_out = ' . (int) $user->get('id'). ' ) )'
			;
			$this->_db->setQuery( $query );
			if (!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
		}
		return true;
	}

	/**
	 * Method to get the type parameters of an item
	 * 
	 * @return string
	 * @since 1.5
	 */
	function getTypeparams ()
	{
		$query	= 'SELECT t.attribs'
				. ' FROM #__flexicontent_types AS t';

		if ($this->_id == null) {
			$type_id = JRequest::getInt('typeid', 0);
			$query .= ' WHERE t.id = ' . (int)$type_id;
		} else {
			$query .= ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.type_id = t.id'
					. ' WHERE ie.item_id = ' . (int)$this->_id
					;
		}
		$this->_db->setQuery($query);
		$tparams = $this->_db->loadResult();
		return $tparams;
	}

	
	/**
	 * Method to get the values of an extrafield
	 * 
	 * @return object
	 * @since 1.5
	 * @todo move in a specific class and add the parameter $itemid
	 */
	function getExtrafieldvalue($fieldid, $version = 0)
	{
		$id = (int)$this->_id;
		if(!$id) return array();
		$cparams =& JComponentHelper::getParams( 'com_flexicontent' );
		$use_versioning = $cparams->get('use_versioning', 1);
		$query = 'SELECT value'
			.((($version<=0) || !$use_versioning)?' FROM #__flexicontent_fields_item_relations AS fv':' FROM #__flexicontent_items_versions AS fv')
			.' WHERE fv.item_id = ' . (int)$this->_id
			.' AND fv.field_id = ' . (int)$fieldid
			.((($version>0) && $use_versioning)?' AND fv.version='.((int)$version):'')
			.' ORDER BY valueorder'
			;
		$this->_db->setQuery($query);
		$field_value = $this->_db->loadResultArray();
		return $field_value;
	}
	
	/**
	 * Method to get extrafields which belongs to the item type
	 * 
	 * @return object
	 * @since 1.5
	 */
	function getExtrafields() {
		$typeid = intval(@$this->_item->type_id);
		$version = (int)FLEXIUtilities::getLastVersions($this->_id, true);
		$where = $typeid?' WHERE ftrel.type_id='.(int)$typeid:' WHERE ie.item_id = ' . (int)$this->_id;
		$query = 'SELECT fi.*'
			.' FROM #__flexicontent_fields AS fi'
			.' LEFT JOIN #__flexicontent_fields_type_relations AS ftrel ON ftrel.field_id = fi.id'
			.' LEFT JOIN #__flexicontent_items_ext AS ie ON ftrel.type_id = ie.type_id'
			.$where
			.' AND fi.published = 1'
			.' GROUP BY fi.id'
			.' ORDER BY ftrel.ordering, fi.ordering, fi.name'
			;
		$this->_db->setQuery($query);
		$fields = $this->_db->loadObjectList('name');
		foreach ($fields as $field) {
			$field->item_id		= (int)$this->_id;
			$field->value 		= $this->getExtrafieldvalue($field->id, $version);
			$field->parameters 	= new JParameter($field->attribs);
		}
		return $fields;
	}
}
?>
