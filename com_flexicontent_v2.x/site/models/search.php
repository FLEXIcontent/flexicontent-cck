<?php
/**
 * @version 1.5 stable $Id: favourites.php 1548 2012-11-13 02:24:26Z ggppdk $
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
 * @subpackage FLEXIcontent
 * @since		1.5
 */
class FLEXIcontentModelSearch extends JModelLegacy
{
	/**
	 * Item list data
	 *
	 * @var array
	 */
	var $_data = null;
	
	/**
	 * Items list total
	 *
	 * @var integer
	 */
	var $_total = null;

	/**
	 * Search areas
	 *
	 * @var integer
	 */
	var $_areas = null;
	
	/**
	 * Pagination object
	 *
	 * @var object
	 */
	var $_pagination = null;
	
	/**
	 * Search view parameters via menu item or via search module or ... via global configuration selected menu item
	 *
	 * @var object
	 */
	var $_params = null;
	
	/**
	 * Constructor
	 *
	 * @since 1.5
	 */
	function __construct()
	{
		parent::__construct();
		
		// Set id and load parameters
		$id = 0;  // no id used by this view
		$this->setId((int)$id);
		$params = & $this->_params;
		
		// Set the pagination variables into state (We get them from http request OR use default tags view parameters)
		$limit = JRequest::getVar('limit') ? JRequest::getVar('limit') : $params->get('limit');
		$limitstart = JRequest::getInt('limitstart');

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
		
		// Set the search parameters
		$keyword		= urldecode(JRequest::getString('searchword'));
		$match			= JRequest::getWord('searchphrase', 'all');
		$ordering		= JRequest::getWord('ordering', 'newest');
		$this->setSearch($keyword, $match, $ordering);

		//Set the search areas
		$areas = JRequest::getVar('areas');
		$this->setAreas($areas);
	}
	
	
	/**
	 * Method to set initialize data, setting an element id for the view
	 *
	 * @access	public
	 * @param	int
	 */
	function setId($id)
	{
		// Set new category ID, wipe member variables and load parameters
		//$this->_id      = $id;  // not used by current view
		$this->_data    = null;
		$this->_total   = null;
		$this->_pagination = null;
		$this->_params  = null;
		$this->_loadParams();
	}
	
	
	/**
	 * Method to set the search parameters
	 *
	 * @access	public
	 * @param string search string
 	 * @param string mathcing option, exact|any|all
 	 * @param string ordering option, newest|oldest|popular|alpha|category
	 */
	function setSearch($keyword, $match = 'all', $ordering = 'newest')
	{
		if(isset($keyword)) {
			$this->setState('keyword', $keyword);
		}

		if(isset($match)) {
			$this->setState('match', $match);
		}

		if(isset($ordering)) {
			$this->setState('ordering', $ordering);
		}
	}

	/**
	 * Method to set the search areas
	 *
	 * @access	public
	 * @param	array	Active areas
	 * @param	array	Search areas
	 */
	function setAreas($active = array(), $search = array())
	{
		$this->_areas['active'] = $active;
		$this->_areas['search'] = $search;
	}

	/**
	 * Method to get weblink item data for the category
	 *
	 * @access public
	 * @return array
	 */
	function getData()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{
			$areas = $this->getAreas();

			JPluginHelper::importPlugin( 'search');
			$dispatcher = JDispatcher::getInstance();
			$results = $dispatcher->trigger( FLEXI_J16GE ? 'onContentSearch' : 'onSearch', array(
				$this->getState('keyword'),
				$this->getState('match'),
				$this->getState('ordering'),
				$areas['active'])
			);

			$rows = array();
			foreach($results AS $result) {
				$rows = array_merge( (array) $rows, (array) $result);
			}

			$this->_total	= count($rows);
			if($this->getState('limit') > 0) {
				$this->_data    = array_splice($rows, $this->getState('limitstart'), $this->getState('limit'));
			} else {
				$this->_data = $rows;
			}
		}
		
		return $this->_data;
	}
	
	
	/**
	 * Method to get the total number of items
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		return $this->_total;
	}
	
	
	/**
	 * Method to get the pagination object
	 *
	 * @access	public
	 * @return	object
	 */
	public function getPagination() {
		// Load the content if it doesn't already exist
		if (empty($this->_pagination)) {
			//jimport('joomla.html.pagination');
			require_once (JPATH_COMPONENT.DS.'helpers'.DS.'pagination.php');
			$this->_pagination = new FCPagination($this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}
		return $this->_pagination;
	}
	
	
	/**
	 * Method to get the search areas
	 *
	 * @since 1.5
	 */
	function getAreas()
	{
		// Return already calculated search areas
		if ( !empty($this->_areas['search']) ) {
			return $this->_areas;
		}
		
		// Return (only) the area of advanced search plugin, when search areas selector is not shown
		$params = & $this->_params;
		if( !$params->get('show_searchareas', 0) ) {
			$this->_areas['search'] = array('flexicontent');
			return $this->_areas;
		}
		
		// Using other search areas, get all search
		JPluginHelper::importPlugin( 'search');
		$dispatcher = JDispatcher::getInstance();
		$searchareas = $dispatcher->trigger( FLEXI_J16GE ? 'onContentSearchAreas' : 'onSearchAreas' );
		$areas = array();
		foreach ($searchareas as $area) {
			$areas = array_merge( $areas, $area );
		}
		
		// DISABLE search area 'content' of Joomla articles search plugin
		unset($areas['content']);
		
		// DISABLE -FIELD- search areas of standard flexisearch plugin
		$unset_areas = array('FlexisearchTitle', 'FlexisearchDesc', 'FlexisearchFields', 'FlexisearchMeta', 'FlexisearchTags');
		foreach($unset_areas as $_unset_area) unset($areas[$_unset_area]);
		
		// DISABLE -CONTENT TYPES- search areas of standard flexisearch plugin
		foreach($areas as $_sindex => $_slabel) {
			if (strpos($_sindex, 'FlexisearchType') !== false)  unset($areas[$_sindex]);
		}
		
		// Cache search areas and return them
		$this->_areas['search'] = $areas;
		return $this->_areas;
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
		$menu = JSite::getMenu()->getActive();     // Retrieve active menu
		
		// a. Get the COMPONENT only parameters and merge current menu item parameters
		$params = clone( JComponentHelper::getParams('com_flexicontent') );
		if ($menu) {
			$menu_params = FLEXI_J16GE ? $menu->params : new JParameter($menu->params);
			$params->merge($menu_params);
		}
		
		// b. Merge module parameters overriding current configuration
		//   (this done when module id is present in the HTTP request) (search module include search view configuration)
		if ( JRequest::getInt('module', 0 ) )
		{
			// load by module name, not used
			//jimport( 'joomla.application.module.helper' );
			//$module_name = JRequest::getInt('module', 0 );
			//$module = & JModuleHelper::getModule('mymodulename');
			
			// load by module id
			$module_id = JRequest::getInt('module', 0 );
			$module = JTable::getInstance ( 'Module', 'JTable' );
			
			if ( $module->load($module_id) ) {
				$moduleParams = FLEXI_J16GE ? new JRegistry($module->params) : new JParameter($module->params);
				$params->merge($moduleParams);
			} else {
				JError::raiseNotice ( 500, $module->getError() );
			}
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
