<?php
/**
 * @version		$Id: search.php 14401 2010-01-26 14:10:00Z louis $
 * @package		Joomla
 * @subpackage	Search
 * @copyright	Copyright (C) 2005 - 2010 Open Source Matters. All rights reserved.
 * @license		GNU/GPL, see LICENSE.php
 * Joomla! is free software. This version may have been modified pursuant to the
 * GNU General Public License, and as distributed it includes or is derivative
 * of works licensed under the GNU General Public License or other free or open
 * source software licenses. See COPYRIGHT.php for copyright notices and
 * details.
 */

// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.application.component.model');
class FLEXIcontentModelSearch extends JModel
{
	/**
	 * Sezrch data array
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * Search total
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
	 * Constructor
	 *
	 * @since 1.5
	 */
	function __construct() {
		parent::__construct();
		$option = 'com_flexicontent';
		$mainframe = &JFactory::getApplication();
		$limit		= $mainframe->getUserStateFromRequest( $option.'.search.limit', 'limit', $mainframe->getCfg('list_limit'), 'int');
		$limitstart = $mainframe->getUserStateFromRequest( $option.'.search.limitstart', 'limitstart', 0, 'int' );

		// In case limit has been changed, adjust limitstart accordingly
		$limitstart = ( $limit != 0 ? (floor($limitstart / $limit) * $limit) : 0 );

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}
	
	
	function getData() {
		if(empty($this->_data)) {
			$query = $this->_buildQuery();
			$this->_data = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));
			$db =& JFactory::getDBO();
			$db->setQuery("SELECT FOUND_ROWS()");
			$this->_total = $db->loadResult();
		}
		return $this->_data;
	}
	
	/**
	 * Method to get the number of relevant links.
	 *
	 * @access	public
	 * @return	mixed	False on failure, integer on success.
	 * @since	1.0
	 */
	public function getCount() {
		// Lets load the Items if it doesn't already exist
		if (empty($this->_total)) {
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}
		return $this->_total;
	}
	
	function _buildQuery() {
		$query = "SELECT SQL_CALC_FOUND_ROWS f.label,ai.*,a.title FROM #__flexicontent_advsearch_index as ai"
			." JOIN #__flexicontent_fields as f ON ai.field_id=f.id"
			." JOIN #__content as a ON ai.item_id=a.id"
		;
		return $query;
	}
	
	/**
	 * Method to get a list pagination object.
	 *
	 * @access	public
	 * @return	object	A JPagination object.
	 * @since	1.0
	 */
	public function getPagination() {
		if (!empty($this->_pagination)) {
			return $this->_pagination;
		}
		jimport('joomla.html.pagination');
		$this->_pagination = new JPagination($this->getCount(), $this->getState('limitstart'), $this->getState('limit'));

		return $this->_pagination;
	}
	function getLimitStart() {
		$mainframe = &JFactory::getApplication();
		return $this->getState('limitstart', $mainframe->getCfg('list_limit'));
	}
	function purge() {
		$db = &JFactory::getDBO();
		$query = "TRUNCATE TABLE `#__flexicontent_advsearch_index`;";
		$db->setQuery($query);
		$db->query();
	}
}
