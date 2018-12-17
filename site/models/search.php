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

jimport('legacy.model.legacy');

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

		// ***
		// *** Set id and load parameters
		// ***
		$id = 0;  // no id used by this view
		$this->setId((int)$id);
		$params = $this->_params;
		$app = JFactory::getApplication();

		// Get limits & set the pagination variables into state (We get them from http request OR use default search view parameters)
		$limit = strlen($app->input->getString('limit')) ? $app->input->getInt('limit') : $this->_params->get('limit');
		$limitstart	= $app->input->getInt('limitstart', $app->input->getInt('start', 0));

		// Make sure limitstart is set
		$app->input->set('limitstart', $limitstart);
		$app->input->set('start', $limitstart);

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);


		// *************************
		// Set the search parameters
		// *************************
		$keyword  = urldecode( $app->input->getString('searchword', $app->input->getString('q')) );

		$default_searchphrase = $params->get('default_searchphrase', 'all');
		$match = $app->input->getWord('searchphrase', $app->input->getWord('p', $default_searchphrase));

		$default_searchordering = $params->get('default_searchordering', 'newest');
		$ordering = $app->input->getWord('ordering', $app->input->getWord('o', $default_searchordering));

		$this->setSearch($keyword, $match, $ordering);


		/**
		 * Set the search areas
		 */
		$areas = $app->input->get('areas', null, 'array');

		if ($areas)
		{
			foreach ($areas as $i => $area)
			{
				$areas[$i] = JFilterInput::getInstance()->clean($area, 'cmd');
			}
		}

		$this->setAreas($areas);


		/**
		 * Get minimum word search length
		 */
		$option = $app->input->getCmd('option');

		//if ( !$app->getUserState( $option.'.min_word_len', 0 ) ) {  // Do not cache to allow configuration changes
			$db = JFactory::getDbo();
			$db->setQuery("SHOW VARIABLES LIKE '%ft_min_word_len%'");
			$_dbvariable = $db->loadObject();
			$min_word_len = (int) @ $_dbvariable->Value;
			$minchars = $params->get('minchars', 3);
			$search_prefix = JComponentHelper::getParams( 'com_flexicontent' )->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix
			$min_word_len = !$search_prefix && $min_word_len > $minchars  ?  $min_word_len : $minchars;
			$app->setUserState($option.'.min_word_len', $min_word_len);
		//}
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
			// Trigger search event to get the content items
			$areas = $this->getAreas();

			JPluginHelper::importPlugin( 'search');
			$dispatcher = JEventDispatcher::getInstance();
			$results = $dispatcher->trigger( 'onContentSearch',
				array(
					$this->getState('keyword'),
					$this->getState('match'),
					$this->getState('ordering'),
					(!empty($areas['active']) ? $areas['active'] : array_keys($areas['search']))
				)
			);

			$rows = array();
			foreach($results AS $result) {
				$rows = array_merge( (array) $rows, (array) $result);
			}

			$this->_total	= count($rows);

			$this->_data = $this->getState('limit') > 0
				? array_splice($rows, $this->getState('limitstart'), $this->getState('limit'))
				: $rows;
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
			$this->_areas['search'] = array('flexicontent' => 'FLEXICONTENT');
			return $this->_areas;
		}

		// Using other search areas, get all search
		JPluginHelper::importPlugin( 'search');
		$dispatcher = JEventDispatcher::getInstance();
		$searchareas = $dispatcher->trigger( 'onContentSearchAreas' );
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