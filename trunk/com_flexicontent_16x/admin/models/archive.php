<?php
/**
 * @version 1.5 stable $Id: archive.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.modellist');

/**
 * FLEXIcontent Component Items Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelArchive extends JModelList{
	/**
	 * Items data
	 *
	 * @var object
	 */
	var $_data = null;
	
	/**
	 * Category data
	 *
	 * @var object
	 */
	var $_cats = null;

	/**
	 * Category total
	 *
	 * @var integer
	 */
	var $_total = null;

	/**
	 * Pagination object
	 *
	 * @var object
	 */
	var $_pagination = null;

	/**
	 * Category id
	 *
	 * @var int
	 */
	var $_id = null;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');

		$limit		= $mainframe->getUserStateFromRequest( $option.'.limit', 'limit', $mainframe->getCfg('list_limit'), 'int');
		$limitstart = $mainframe->getUserStateFromRequest( $option.'.limitstart', 'limitstart', 0, 'int' );

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

		$array = JRequest::getVar('cid',  0, '', 'array');
		$this->setId((int)$array[0]);

	}

	/**
	 * Method to set the item identifier
	 *
	 * @access	public
	 * @param	int identifier
	 */
	function setId($id)
	{
		// Set id and wipe data
		$this->_id	 = $id;
		$this->_data = null;
		$this->_cats = null;
	}

	/**
	 * Method to get item data
	 *
	 * @access public
	 * @return array
	 */
	function getData()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{
			$query = $this->_buildQuery();
			$this->_data = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));
			
			$k = 0;
			$count = count($this->_data);
			for($i = 0; $i < $count; $i++)
			{
				$item =& $this->_data[$i];
				$item->categories = $this->getCategories($item->id);
				$k = 1 - $k;
			}
			
		}
		
		return $this->_data;
	}


	/**
	 * Method to build the query for the categories
	 *
	 * @access private
	 * @return integer
	 * @since 1.0
	 */
	function getListQuery() {
		// Get the WHERE and ORDER BY clauses for the query
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');
		$query = $this->_db->getQuery(true);

		$search 			= $mainframe->getUserStateFromRequest( $option.'.archive.search', 'search', '', 'string' );
		$search 			= $this->_db->getEscaped( trim(JString::strtolower( $search ) ) );

		$filter_order		= $mainframe->getUserStateFromRequest( $option.'.archive.filter_order', 		'filter_order', 	'i.ordering', 'cmd' );
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( $option.'.archive.filter_order_Dir',	'filter_order_Dir',	'', 'word' );
		
		$query->select('DISTINCT rel.itemid, i.*, u.name AS editor');
		$query->from('#__content AS i');
		$query->join('LEFT', '#__categories as c ON i.catid=c.id');
		$query->join('LEFT', '#__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id');
		$query->join('LEFT', '#__users AS u ON u.id = i.checked_out');
		$query->where('c.lft >= ' . $this->_db->Quote(FLEXI_CATEGORY_LFT) . ' AND c.rgt <= ' . $this->_db->Quote(FLEXI_CATEGORY_RGT));
		$query->where('i.state = -1');
		$query->where('LOWER(i.title) LIKE '.$this->_db->Quote( '%'.$this->_db->getEscaped( $search, true ).'%', false ));
		$query->order($filter_order.' '.$filter_order_Dir.', i.ordering');

		//echo str_replace('#__', 'jos_', $query->__toString());
		return $query;
	}

	/**
	 * Method to remove an item
	 *
	 * @access	public
	 * @return	string $msg
	 * @since	1.0
	 */
	function delete($cid)
	{
		if (count( $cid ))
		{
			$cids = implode( ',', $cid );
			$query = 'DELETE FROM #__content'
					. ' WHERE id IN ('. $cids .')'
					;

			$this->_db->setQuery( $query );
			
			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			// remove items extended
			$query = 'DELETE FROM #__flexicontent_items_ext'
					. ' WHERE item_id IN ('. $cids .')'
					;

			$this->_db->setQuery( $query );
			
			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}

			//remove assigned tag references
			$query = 'DELETE FROM #__flexicontent_tags_item_relations'
					.' WHERE itemid IN ('. $cids .')'
					;
			$this->_db->setQuery($query);

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			//remove assigned category references
			$query = 'DELETE FROM #__flexicontent_cats_item_relations'
					.' WHERE itemid IN ('. $cids .')'
					;
			$this->_db->setQuery($query);

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			//remove assigned file references
			$query = 'DELETE FROM #__flexicontent_files_item_relations'
					.' WHERE itemid IN ('. $cids .')'
					;
			$this->_db->setQuery($query);

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			// delete also field in fields_item relation
			$query = 'DELETE FROM #__flexicontent_fields_item_relations'
					. ' WHERE item_id IN ('. $cids .')'
					;

			$this->_db->setQuery( $query );

			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}

			return true;
		}
		
		return false;
	}
	
	/**
	 * Method to fetch the assigned categories
	 *
	 * @access	public
	 * @return	object
	 * @since	1.0
	 */
	function getCategories($id) {
		$query = 'SELECT DISTINCT c.id, c.title'
				. ' FROM #__categories AS c'
				. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.catid = c.id'
				. ' WHERE rel.itemid = '.(int)$id
				;
	
		$this->_db->setQuery( $query );

		$this->_cats = $this->_db->loadObjectList();

		return $this->_cats;
	}
	
	/**
	 * Method to unarchive an item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function unarchive($cid = array())
	{
		$user 	= & JFactory::getUser();
		$userid	= (int) $user->get('id');

		if (count( $cid ))
		{
			$cids = implode( ',', $cid );

			$query = 'UPDATE #__content'
					. ' SET state = 0'
					. ' WHERE id IN ('. $cids .')'
					. ' AND ( checked_out = 0 OR ( checked_out = ' .$userid. ' ) )'
			;
			$this->_db->setQuery( $query );
			if (!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
		
			return true;
		}
		
		return false;
	}
}
?>
