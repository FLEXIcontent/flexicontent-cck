<?php
/**
 * @version 1.5 stable $Id: types.php 1223 2012-03-30 08:34:34Z ggppdk $
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
 * FLEXIcontent Component types Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelTypes extends JModelList
{
	/**
	 * Type data
	 *
	 * @var object
	 */
	var $_data = null;

	/**
	 * Type total
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
	 * Type id
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

		$app    = JFactory::getApplication();
		$option = JRequest::getVar('option');

		$limit      = $app->getUserStateFromRequest( $option.'.types.limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart = $app->getUserStateFromRequest( $option.'.types.limitstart', 'limitstart', 0, 'int' );

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}
	
	
	/**
	 * Method to build the query for the types
	 *
	 * @access private
	 * @return integer
	 * @since 1.0
	 */
	function getListQuery()
	{
		// Get the WHERE, HAVING and ORDER BY clauses for the query
		$app    = JFactory::getApplication();
		$option = JRequest::getVar('option');
		$db = JFactory::getDBO();
		$query = $db->getQuery(true);

		$filter_state = $app->getUserStateFromRequest( $option.'.types.filter_state', 'filter_state', '', 'word' );
		$search = $app->getUserStateFromRequest( $option.'.types.search', 'search', '', 'string' );
		$search = trim( JString::strtolower( $search ) );
		
		$filter_order     = $app->getUserStateFromRequest( $option.'.types.filter_order', 		'filter_order', 	't.name', 'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest( $option.'.types.filter_order_Dir',	'filter_order_Dir',	'ASC', 'word' );

		// Select the required fields from the table.
		$query->select(
			$this->getState(
				'list.select',
				't.*'
				.', u.name AS editor, level.title AS access_level'
				.', (SELECT COUNT(*) FROM #__flexicontent_items_ext AS i WHERE i.type_id = t.id) AS iassigned '
				.', COUNT(rel.type_id) AS fassigned, t.attribs AS config'
			)
		);
		
		$query->from('#__flexicontent_types AS t');
		$query->join('LEFT', '#__flexicontent_fields_type_relations AS rel ON t.id = rel.type_id');
		$query->join('LEFT', '#__viewlevels AS level ON level.id=t.access');
		$query->join('LEFT', '#__users AS u ON u.id = t.checked_out');
		$query->group('t.id');
		
		if ( $filter_state ) {
			if ( $filter_state == 'P' ) {
				$query->where('t.published = 1');
			} else if ($filter_state == 'U' ) {
				$query->where('t.published = 0');
			}
		}

		if ($search) {
			$query->where('LOWER(t.name) LIKE '.$this->_db->Quote( '%'.$this->_db->escape( $search, true ).'%', false ));
		}
		$query->order($filter_order.' '.$filter_order_Dir);
		//echo str_replace("#__", "jos_", $query->__toString());

		return $query;
	}
	
	/**
	 * Method to build the having clause of the query for the files
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildContentHaving()
	{
		$app    = JFactory::getApplication();
		$option = JRequest::getVar('option');
		
		$filter_assigned = $app->getUserStateFromRequest( $option.'.types.filter_assigned', 'filter_assigned', '', 'word' );
		
		$having = '';
		
		if ( $filter_assigned ) {
			if ( $filter_assigned == 'O' ) {
				$having = ' HAVING COUNT(rel.tid) = 0';
			} else if ($filter_assigned == 'A' ) {
				$having = ' HAVING COUNT(rel.tid) > 0';
			}
		}
		
		return $having;
	}

	/**
	 * Method to (un)publish a type
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function publish($cid = array(), $publish = 1)
	{
		$user = JFactory::getUser();

		if (count( $cid ))
		{
			$cids = implode( ',', $cid );

			$query = 'UPDATE #__flexicontent_types'
				. ' SET published = ' . (int) $publish
				. ' WHERE id IN ('. $cids .')'
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
	 * Method to check if we can remove a type
	 * return false if there are items associated
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function candelete($cid = array())
	{
		$n		= count( $cid );
		if (count( $cid ))
		{
			for ($i = 0; $i < $n; $i++)
			{
			$query = 'SELECT COUNT( type_id )'
			. ' FROM #__flexicontent_items_ext'
			. ' WHERE type_id = '. (int) $cid[$i]
			;
			$this->_db->setQuery( $query );
			$count = $this->_db->loadResult();
			
			if ($count > 0) {
				return false;
				}
			}
			return true;
		}
	}

	/**
	 * Method to remove a type
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function delete($cid = array())
	{
		$result = false;

		if (count( $cid ))
		{
			$cids = implode( ',', $cid );
			$query = 'DELETE FROM #__flexicontent_types'
					. ' WHERE id IN ('. $cids .')'
					;
			$this->_db->setQuery( $query );
			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			// delete fields_type relations
			$query = 'DELETE FROM #__flexicontent_fields_type_relations'
					. ' WHERE type_id IN ('. $cids .')'
					;
			$this->_db->setQuery( $query );
			if(!$this->_db->query()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}

		}

		return true;
	}

	/**
	 * Method to copy types
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function copy($cid = array())
	{
		if (count( $cid ))
		{
			foreach ($cid as $id) {
				$type = $this->getTable('flexicontent_types', '');
				$type->load($id);
				$type->id = 0;
				$type->name = $type->name . ' [copy]';
				$type->alias = JFilterOutput::stringURLSafe($type->name);
				$type->check();
				$type->store();
				
				$query 	= 'SELECT * FROM #__flexicontent_fields_type_relations'
						. ' WHERE type_id = ' . (int)$id
						;
				$this->_db->setQuery($query);
				$rels = $this->_db->loadObjectList();
				
				foreach ($rels as $rel) {
					$query = 'INSERT INTO #__flexicontent_fields_type_relations (`field_id`, `type_id`, `ordering`) VALUES(' . (int)$rel->field_id . ',' . $type->id . ',' . (int)$rel->ordering . ')';
					$this->_db->setQuery($query);
					$this->_db->query();
				}
			}
			return true;
		}
		return false;
	}

	/**
	 * Method to set the access level of the Types
	 *
	 * @access	public
	 * @param 	integer id of the category
	 * @param 	integer access level
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function saveaccess($id, $access)
	{
		$row = JTable::getInstance('flexicontent_types', '');

		$row->load( $id );
		$row->id = $id;
		$row->access = $access;

		if ( !$row->check() ) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		if ( !$row->store() ) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		return true;
	}
}
?>
