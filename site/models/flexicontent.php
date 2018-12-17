<?php
/**
 * @version 1.5 stable $Id: flexicontent.php 1876 2014-03-24 03:24:41Z ggppdk $
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

jimport('legacy.model.legacy');

/**
 * FLEXIcontent Component Model
 *
 * @package Joomla
 * @subpackage Flexicontent
 * @since		1.0
 */
class FlexicontentModelFlexicontent extends JModelLegacy
{
	/**
	 * Root category from this directory
	 *
	 * @var int
	 */
	var $_rootcat = null;
	
	/**
	 * data
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
	 * Pagination object
	 *
	 * @var object
	 */
	var $_pagination = null;

	/**
	 * parameters
	 *
	 * @var object
	 */
	var $_params = null;
	
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();
		
		// Set id and load parameters
		$id = 0;  // no id used by this view
		$this->setId((int)$id);
		$params = $this->_params;
		$app    = JFactory::getApplication();
		
		// Get the root category of the directory
		$this->_rootcat = (int) $app->input->getInt('rootcat', 0);

		// Compatibility of old saved menu items, the value is inside params instead of being URL query variable
		if ( !$this->_rootcat)
		{
			$this->_rootcat = $params->get('rootcat', 1);
		}
		else
		{
			$params->set('rootcat', $this->_rootcat);
		}

		// Get limits & set the pagination variables into state (We get them from http request OR use default search view parameters)
		$limit 			= $params->def('catlimit', 5);
		$limitstart	= $app->input->getInt('limitstart', $app->input->getInt('start', 0));

		// Make sure limitstart is set
		$app->input->set('limitstart', $limitstart);
		$app->input->set('start', $limitstart);
		
		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}
	
	
	/**
	 * Method to set initialize data, setting an element id for the view
	 *
	 * @access	public
	 * @param	int
	 */
	function setId($id)
	{
		//$this->_id      = $id;  // not used by current view
		$this->_rootcat = null;
		$this->_data    = null;
		$this->_total   = null;
		$this->_pagination = null;
		$this->_params  = null;
		$this->_loadParams();
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
	 * Build the order clause
	 *
	 * @access private
	 * @return string
	 */
	function _buildCatOrderBy($prefix)
	{
		$request_var = '';
		$config_param = $prefix.'orderby';
		$default_order = $this->getState('filter_order', 'c.title');
		$default_order_dir = $this->getState('filter_order_dir', 'ASC');
		
		// Precedence: $request_var ==> $order ==> $config_param ==> $default_order
		return flexicontent_db::buildCatOrderBy(
			$this->_params,
			$order='', $request_var, $config_param,
			$cat_tbl_alias = 'c', $user_tbl_alias = 'u',
			$default_order, $default_order_dir
		);
	}
	
	
	/**
	 * Method to build the Categories query
	 * This method creates the first level categories and their assigned item count
	 *
	 * @access private
	 * @return string
	 */
	function _buildQuery($type='')
	{
		$params = $this->_params;
		$use_tmp = true;
		
		$user = JFactory::getUser();
		$db   = JFactory::getDbo();
		$orderby = $this->_buildCatOrderBy('cat_');
		
		// Date-Times are stored as UTC, we should use current UTC time to compare and not user time (requestTime),
		// thus the items are published globally at the time the author specified in his/her local clock
		//$now  = JFactory::getApplication()->requestTime;   // NOT correct behavior it should be UTC (below)
		//$now  = JFactory::getDate()->toSql();              // NOT good if string passed to function that will be cached, because string continuesly different
		$_nowDate = 'UTC_TIMESTAMP()'; //$db->Quote($now);
		$nullDate = $db->getNullDate();
		
		// Get some parameters and other info
		$catlang = $params->get('language', '');          // Category language (currently UNUSED)
		$lang = flexicontent_html::getUserCurrentLang();  // Get user current language
		$filtercat  = $params->get('filtercat', 0);       // Filter items using currently selected language
		$show_noauth = $params->get('show_noauth', 0);    // Show unauthorized items
		
		// Build where clause
		$where  = ' WHERE cc.published = 1';
		$where .= ' AND c.id = cc.id';
		
		// Filter the category view with the current user language
		if ($filtercat)
		{
			$lta = $use_tmp ? 'i': 'ie';
			//$where .= ' AND ( '.$lta.'.language LIKE ' . $db->Quote( $lang .'%' ) . ' OR '.$lta.'.language="*" ) ';
			$where .= ' AND (' . $lta . ' .language = ' . $db->Quote(JFactory::getLanguage()->getTag()) . ' OR ' . $lta . '.language = ' . $db->Quote('*') . ')';
		}
		
		// Get privilege to view non viewable items (upublished, archived, trashed, expired, scheduled).
		// NOTE:  ACL view level is checked at a different place
		$ignoreState = $user->authorise('flexicontent.ignoreviewstate', 'com_flexicontent');
		
		if (!$ignoreState)
		{
			// Limit by publication state. Exception: when displaying personal user items or items modified by the user
			$where .= ' AND ( i.state IN (1, -5) OR ( i.created_by = '.$user->id.' AND i.created_by != 0 ) )';   //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
			
			// Limit by publish up/down dates. Exception: when displaying personal user items or items modified by the user
			$where .= ' AND ( ( i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= '.$_nowDate.' ) OR ( i.created_by = '.$user->id.' AND i.created_by != 0 ) )';       //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
			$where .= ' AND ( ( i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= '.$_nowDate.' ) OR ( i.created_by = '.$user->id.' AND i.created_by != 0 ) )';   //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
		}
		
		// Select only items that user has view access, checking item, category, content type access level
		$and = $asscat_and = '';

		if (!$show_noauth)
		{
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$where .= ' AND ty.access IN (0,'.$aid_list.')';
			$where .= ' AND cc.access IN (0,'.$aid_list.')';
			$where .= ' AND  i.access IN (0,'.$aid_list.')';
			$and       .= ' AND  c.access IN (0,'.$aid_list.')';
		}
		
		if ($type=='feed') {
		}
		$query = 'SELECT c.*,'
			. (FLEXI_J16GE ? ' u.name as author,' : '')
			. ' CASE WHEN CHAR_LENGTH( c.alias ) THEN CONCAT_WS( \':\', c.id, c.alias ) ELSE c.id END AS slug,'
			
			. ' ('
			. ' SELECT COUNT( DISTINCT rel.itemid )'
			. ' FROM #__flexicontent_cats_item_relations AS rel'
			. (!$use_tmp ?
				' JOIN #__content AS i ON rel.itemid = i.id' :
				' JOIN #__flexicontent_items_tmp AS i ON rel.itemid = i.id')
			. (!$use_tmp ? ' JOIN #__flexicontent_items_ext AS ie ON rel.itemid = ie.item_id' : '')
			. ' JOIN #__flexicontent_types AS ty ON '. (!$use_tmp ? 'ie' : 'i'). '.type_id = ty.id'
			. ' JOIN #__categories AS cc ON cc.id = rel.catid'
			. $where
			. ' ) AS assigneditems'
			;
		
		$query .= ' FROM #__categories AS c'
			. ' LEFT JOIN #__users AS u ON u.id = c.created_user_id'
			. ' WHERE c.published = 1'
			. (!FLEXI_J16GE ? ' AND c.section = '.FLEXI_SECTION : ' AND c.extension="'.FLEXI_CAT_EXTENSION.'" ' )
			. (!$this->_rootcat ? ' AND c.parent_id = '.(FLEXI_J16GE ? 1 : 0) : ' AND c.parent_id = '. (int)$this->_rootcat)
			. $and
			. $orderby
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
		$params = $this->_params;

		$user = JFactory::getUser();
		
		// show unauthorized items
		$show_noauth = $params->get('show_noauth', 0);
		
		// Select only items user has access to if he is not allowed to show unauthorized items
		$join = $and = '';
		if (!$show_noauth) {
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$and		= ' AND c.access IN (0,'.$aid_list.')';
		}

		$query 	= 'SELECT c.id'
				. ' FROM #__categories AS c'
				. $join
				. ' WHERE c.published = 1'
				. (!FLEXI_J16GE ? ' AND c.section = '.FLEXI_SECTION : ' AND c.extension="'.FLEXI_CAT_EXTENSION.'" ' )
				. (!$this->_rootcat ? ' AND c.parent_id = '.(FLEXI_J16GE ? 1 : 0) : ' AND c.parent_id = '. (int)$this->_rootcat)
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
	 * Method to get a pagination object
	 *
	 * @access public
	 * @return integer
	 */
	public function getPagination()
	{
		// Load the content if it doesn't already exist
		if (empty($this->_pagination))
		{
			//jimport('cms.pagination.pagination');
			require_once (JPATH_COMPONENT.DS.'helpers'.DS.'pagination.php');
			$this->_pagination = new FCPagination($this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}
		return $this->_pagination;
	}
	
	
	/**
	 * Method to fetch the subcategories
	 *
	 * @access private
	 * @return object
	 */
	function _getsubs($id)
	{
		$params = $this->_params;
		$use_tmp = true;
		
		$user = JFactory::getUser();
		$db   = JFactory::getDbo();
		$cat_orderby = $this->_buildCatOrderBy('subcat_');
		
		// Date-Times are stored as UTC, we should use current UTC time to compare and not user time (requestTime),
		// thus the items are published globally at the time the author specified in his/her local clock
		//$now  = JFactory::getApplication()->requestTime;   // NOT correct behavior it should be UTC (below)
		//$now  = JFactory::getDate()->toSql();              // NOT good if string passed to function that will be cached, because string continuesly different
		$_nowDate = 'UTC_TIMESTAMP()'; //$db->Quote($now);
		$nullDate = $db->getNullDate();
		
		// Get some parameters and other info
		$catlang = $params->get('language', '');          // Category language (currently UNUSED)
		$lang = flexicontent_html::getUserCurrentLang();  // Get user current language
		$filtercat  = $params->get('filtercat', 0);       // Filter items using currently selected language
		$show_noauth = $params->get('show_noauth', 0);    // Show unauthorized items
		
		// Build where clause
		$where  = ' WHERE cc.published = 1';
		$where .= ' AND c.id = cc.id';
		
		// Filter the category view with the current user language
		if ($filtercat)
		{
			$lta = $use_tmp ? 'i': 'ie';
			//$where .= ' AND ( '.$lta.'.language LIKE ' . $db->Quote( $lang .'%' ) . ' OR '.$lta.'.language="*" ) ';
			$where .= ' AND (' . $lta . ' .language = ' . $db->Quote(JFactory::getLanguage()->getTag()) . ' OR ' . $lta . '.language = ' . $db->Quote('*') . ')';
		}
		
		// Get privilege to view non viewable items (upublished, archived, trashed, expired, scheduled).
		// NOTE:  ACL view level is checked at a different place
		$ignoreState = $user->authorise('flexicontent.ignoreviewstate', 'com_flexicontent');
		
		if (!$ignoreState) {
			// Limit by publication state. Exception: when displaying personal user items or items modified by the user
			$where .= ' AND ( i.state IN (1, -5) OR ( i.created_by = '.$user->id.' AND i.created_by != 0 ) )';   //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
			
			// Limit by publish up/down dates. Exception: when displaying personal user items or items modified by the user
			$where .= ' AND ( ( i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= '.$_nowDate.' ) OR ( i.created_by = '.$user->id.' AND i.created_by != 0 ) )';       //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
			$where .= ' AND ( ( i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= '.$_nowDate.' ) OR ( i.created_by = '.$user->id.' AND i.created_by != 0 ) )';   //.' OR ( i.modified_by = '.$user->id.' AND i.modified_by != 0 ) )';
		}
		
		// Select only items that user has view access, checking item, category, content type access level
		$and = $asscat_and = '';
		if (!$show_noauth) {
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$where .= ' AND ty.access IN (0,'.$aid_list.')';
			$where .= ' AND cc.access IN (0,'.$aid_list.')';
			$where .= ' AND  i.access IN (0,'.$aid_list.')';
			$and       .= ' AND  c.access IN (0,'.$aid_list.')';
			$asscat_and.= ' AND sc.access IN (0,'.$aid_list.')';
		}
		
		$query = 'SELECT c.*,'
			. ' CASE WHEN CHAR_LENGTH( c.alias ) THEN CONCAT_WS( \':\', c.id, c.alias ) ELSE c.id END AS slug,'
			
			. ' ('
			. ' SELECT COUNT( DISTINCT rel.itemid )'
			. ' FROM #__flexicontent_cats_item_relations AS rel'
			. (!$use_tmp ?
				' JOIN #__content AS i ON rel.itemid = i.id' :
				' JOIN #__flexicontent_items_tmp AS i ON rel.itemid = i.id' )
			. (!$use_tmp ? ' JOIN #__flexicontent_items_ext AS ie ON rel.itemid = ie.item_id' : '')
			. ' JOIN #__flexicontent_types AS ty ON '. (!$use_tmp ? 'ie' : 'i'). '.type_id = ty.id'
			. ' JOIN #__categories AS cc ON cc.id = rel.catid'
			. $where
			. ' ) AS assignedsubitems,'
			
			. ' ('
			. ' SELECT COUNT( sc.id )'
			. ' FROM #__categories AS sc'
			. ' WHERE c.id = sc.parent_id'
			. ' AND sc.published = 1'
			. $asscat_and
			. ' ) AS assignedcats'
			
			. ' FROM #__categories AS c'
			. ' WHERE c.published = 1'
			. (!FLEXI_J16GE ? ' AND c.section = '.FLEXI_SECTION : ' AND c.extension="'.FLEXI_CAT_EXTENSION.'" ' )
			. ' AND c.parent_id = '.(int)$id
			. $and
			. $cat_orderby
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
		$feed = $this->_getList( $this->_buildQuery('feed'), $this->getState('limitstart'), $this->getState('limit') );
		if ($this->_db->getErrorNum()) {
			echo __FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error';
			exit;
		}
		
		return $feed;
	}
	
	
	/**
	 * Method to load parameters
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	function _loadParams()
	{
		if ( $this->_params !== NULL ) return;
		
		$app  = JFactory::getApplication();
		$menu = $app->getMenu()->getActive();     // Retrieve active menu
		
		// Get the COMPONENT only parameter
		$params  = new JRegistry();
		$cparams = JComponentHelper::getParams('com_flexicontent');
		$params->merge($cparams);
		
		// Merge the active menu parameters
		if ($menu)
		{
			$params->merge($menu->params);
		}
		
		$this->_params = $params;
	}
	
	
	/**
	 * Method to get view's parameters
	 *
	 * @access public
	 * @return object
	 */
	function &getParams()
	{
		return $this->_params;
	}
}
?>