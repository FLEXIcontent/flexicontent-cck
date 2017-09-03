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

jimport('legacy.model.list');
use Joomla\String\StringHelper;

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
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');
		$view   = $jinput->get('view', '', 'cmd');
		$fcform = $jinput->get('fcform', 0, 'int');
		$p      = $option.'.'.$view.'.';
		
		
		
		// ****************************************
		// Ordering: filter_order, filter_order_Dir
		// ****************************************
		
		$default_order     = 't.name';
		$default_order_dir = 'ASC';
		
		$filter_order      = $fcform ? $jinput->get('filter_order',     $default_order,      'cmd')  :  $app->getUserStateFromRequest( $p.'filter_order',     'filter_order',     $default_order,      'cmd' );
		$filter_order_Dir  = $fcform ? $jinput->get('filter_order_Dir', $default_order_dir, 'word')  :  $app->getUserStateFromRequest( $p.'filter_order_Dir', 'filter_order_Dir', $default_order_dir, 'word' );
		
		if (!$filter_order)     $filter_order     = $default_order;
		if (!$filter_order_Dir) $filter_order_Dir = $default_order_dir;
		
		$this->setState('filter_order', $filter_order);
		$this->setState('filter_order_Dir', $filter_order_Dir);
		
		$app->setUserState($p.'filter_order', $filter_order);
		$app->setUserState($p.'filter_order_Dir', $filter_order_Dir);
		
		
		
		// **************
		// view's Filters
		// **************
		
		// Various filters
		$filter_state    = $fcform ? $jinput->get('filter_state',    '', 'string')  :  $app->getUserStateFromRequest( $p.'filter_state',    'filter_state',    '', 'string' );   // we may check for '*', so string filter
		$filter_access   = $fcform ? $jinput->get('filter_access',   '', 'int')     :  $app->getUserStateFromRequest( $p.'filter_access',   'filter_access',   '', 'int' );
		
		$this->setState('filter_state', $filter_state);
		$this->setState('filter_access', $filter_access);
		
		$app->setUserState($p.'filter_state', $filter_state);
		$app->setUserState($p.'filter_access', $filter_access);
		
		
		// Text search
		$search = $fcform ? $jinput->get('search', '', 'string')  :  $app->getUserStateFromRequest( $p.'search',  'search',  '',  'string' );
		$this->setState('search', $search);
		$app->setUserState($p.'search', $search);
		
		
		
		// *****************************
		// Pagination: limit, limitstart
		// *****************************
		
		$limit      = $fcform ? $jinput->get('limit', $app->getCfg('list_limit'), 'int')  :  $app->getUserStateFromRequest( $p.'limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart = $fcform ? $jinput->get('limitstart',                     0, 'int')  :  $app->getUserStateFromRequest( $p.'limitstart', 'limitstart', 0, 'int' );
		
		// In case limit has been changed, adjust limitstart accordingly
		$limitstart = ( $limit != 0 ? (floor($limitstart / $limit) * $limit) : 0 );
		$jinput->set( 'limitstart',	$limitstart );
		
		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
		
		$app->setUserState($p.'limit', $limit);
		$app->setUserState($p.'limitstart', $limitstart);
		
		
		// For some model function that use single id
		$array = $jinput->get('cid', array(0), 'array');
		$this->setId((int)$array[0]);
	}
	
	
	/**
	 * Method to set the Field identifier
	 *
	 * @access	public
	 * @param	int Field identifier
	 */
	function setId($id)
	{
		// Set id and wipe data
		$this->_id	 = $id;
		$this->_data = null;
		$this->_total= null;
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
		// Create a query with all its clauses: WHERE, HAVING and ORDER BY, etc
		$app  = JFactory::getApplication();
		$db   = JFactory::getDbo();
		$option = $app->input->get('option', '', 'CMD');
		$view   = $app->input->get('view', '', 'CMD');
		
		$filter_order     = $this->getState( 'filter_order' );
		$filter_order_Dir	= $this->getState( 'filter_order_Dir' );
		$filter_state     = $this->getState( 'filter_state' );
		$filter_access    = $this->getState( 'filter_access' );
		
		// text search
		$search  = $this->getState( 'search' );
		$search  = StringHelper::trim( StringHelper::strtolower( $search ) );
		
		// Create a new query object.
		$query = $db->getQuery(true);
		// Select the required fields from the table.
		$query->select(
			$this->getState(
				'list.select',
				't.*'
				.', u.name AS editor, CASE WHEN level.title IS NULL THEN CONCAT_WS(\'\', \'deleted:\', t.access) ELSE level.title END AS access_level'
				.', (SELECT COUNT(*) FROM #__flexicontent_items_ext AS i WHERE i.type_id = t.id) AS iassigned '
				.', COUNT(rel.type_id) AS fassigned, t.attribs AS config'
			)
		);
		
		$query->from('#__flexicontent_types AS t');
		$query->join('LEFT', '#__flexicontent_fields_type_relations AS rel ON t.id = rel.type_id');
		$query->join('LEFT', '#__viewlevels AS level ON level.id=t.access');
		$query->join('LEFT', '#__users AS u ON u.id = t.checked_out');
		$query->group('t.id');
		
		// Filter by state
		if ( $filter_state ) {
			if ( $filter_state == 'P' ) {
				$query->where('t.published = 1');
			} else if ($filter_state == 'U' ) {
				$query->where('t.published = 0');
			} // else ALL: published & unpublished (in future we may have more states, e.g. archived, trashed)
		}
		
		// Filter by access level
		if ( $filter_access ) {
			$query->where('t.access = '.(int) $filter_access);
		}
		
		// Filter by search word
		if (strlen($search)) {
			$query->where('LOWER(t.name) LIKE '.$this->_db->Quote( '%'.$this->_db->escape( $search, true ).'%', false ));
		}
		$query->order($filter_order.' '.$filter_order_Dir);
		//echo str_replace("#__", "jos_", $query->__toString());

		return $query;
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
			$this->_db->execute();
		}
		return true;
	}


	/**
	 * Method to check if given records can not be deleted e.g. due to assignments or due to being a CORE record
	 *
	 * @access	public
	 * @return	boolean
	 * @since	1.5
	 */
	function candelete($cid, & $cid_noauth=array(), & $cid_wassocs=array())
	{
		$cid_noauth = $cid_wassocs = array();

		if (!count($cid))
		{
			return false;
		}

		// Find ACL disallowed
		$cid_noauth = FlexicontentHelperPerm::getPerm()->CanTypes ? array() : $cid;

		// Find having assignments
		$cid_wassocs = $this->filterByAssignments($cid);

		return !count($cid_noauth) && !count($cid_wassocs);
	}


	/**
	 * Method to check if given records can not be unpublished e.g. due to assignments or due to being a CORE record
	 *
	 * @access	public
	 * @return	boolean
	 * @since	1.5
	 */
	function canunpublish($cid, & $cid_noauth=array(), & $cid_wassocs=array())
	{
		$cid_noauth = $cid_wassocs = array();

		if (!count($cid))
		{
			return false;
		}

		// Find ACL disallowed
		$cid_noauth = FlexicontentHelperPerm::getPerm()->CanTypes ? array() : $cid;

		// Find having assignments
		$cid_wassocs = $this->filterByAssignments($cid);

		return !count($cid_noauth) && !count($cid_wassocs);
	}


	// Find which records have assignments
	function filterByAssignments($cid = array())
	{
		JArrayHelper::toInteger($cid);
		$query = 'SELECT DISTINCT type_id'
			. ' FROM #__flexicontent_items_ext'
			. ' WHERE type_id IN ('. implode(',', $cid) .')'
			;
		$this->_db->setQuery( $query );
		return $this->_db->loadColumn();
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
			if(!$this->_db->execute()) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
			
			// delete fields_type relations
			$query = 'DELETE FROM #__flexicontent_fields_type_relations'
					. ' WHERE type_id IN ('. $cids .')'
					;
			$this->_db->setQuery( $query );
			if(!$this->_db->execute()) {
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
			foreach ($cid as $id)
			{
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
				
				foreach ($rels as $rel)
				{
					$query = 'INSERT INTO #__flexicontent_fields_type_relations (`field_id`, `type_id`, `ordering`) VALUES(' . (int)$rel->field_id . ',' . $type->id . ',' . (int)$rel->ordering . ')';
					$this->_db->setQuery($query);
					$this->_db->execute();
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
