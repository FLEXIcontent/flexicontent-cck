<?php
/**
 * @version 1.5 stable $Id: filemanager.php 350 2010-06-29 08:47:01Z emmanuel.danan $
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

jimport('joomla.application.component.model');

/**
 * FLEXIcontent Component Filemanager Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelFilemanager extends JModel
{
	/**
	 * file data
	 *
	 * @var object
	 */
	var $_data = null;

	/**
	 * file total
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
	 * file id
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
		$limitstart = $mainframe->getUserStateFromRequest( $option.'.files.limitstart', 'limitstart', 0, 'int' );

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

		$array = JRequest::getVar('cid',  0, '', 'array');
		$this->setId((int)$array[0]);

	}

	/**
	 * Method to set the files identifier
	 *
	 * @access	public
	 * @param	int file identifier
	 */
	function setId($id)
	{
		// Set id and wipe data
		$this->_id	 = $id;
		$this->_data = null;
	}

	/**
	 * Method to get files data
	 *
	 * @access public
	 * @return array
	 */
	function getData()
	{
		// Lets load the files if it doesn't already exist
		if (empty($this->_data))
		{
			$query = $this->_buildQuery();
			$this->_data = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));

			$this->_data = flexicontent_images::BuildIcons($this->_data);
			$this->_data = $this->countImageRelations($this->_data);
		}
		return $this->_data;
	}

	/**
	 * Method to get the total nr of the files
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		// Lets load the files if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}

	/**
	 * Method to get a pagination object for the files
	 *
	 * @access public
	 * @return integer
	 */
	function getPagination()
	{
		// Lets load the files if it doesn't already exist
		if (empty($this->_pagination))
		{
			jimport('joomla.html.pagination');
			$this->_pagination = new JPagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}

		return $this->_pagination;
	}

	/**
	 * Method to build the query for the files
	 *
	 * @access private
	 * @return integer
	 * @since 1.0
	 */
	function _buildQuery()
	{
		// Get the WHERE, HAVING and ORDER BY clauses for the query
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');
		$where		= $this->_buildContentWhere();
		$orderby	= $this->_buildContentOrderBy();
		$having		= $this->_buildContentHaving();
		$filter_item 		= $mainframe->getUserStateFromRequest( $option.'.filemanager.items', 			'items', 			'', 'int' );
		
		// File field relation sub query
		$subf	= 'SELECT COUNT(value)'
			. ' FROM #__flexicontent_fields_item_relations AS rel'
			. ' JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id'
			. ' WHERE fi.field_type = ' . $this->_db->Quote('file')
			. ' AND value = f.id'
			;
			
		if ($filter_item) {
			$query = 'SELECT f.*, u.name AS uploader, ('.$subf.') AS nrassigned'
				. ' FROM #__flexicontent_files AS f'
				. ' JOIN #__flexicontent_fields_item_relations AS rel ON f.id = rel.value'
				. ' JOIN #__users AS u ON u.id = f.uploaded_by'
				. ' JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id'
				. $where
				. ' AND fi.field_type = ' . $this->_db->Quote('file')
				. ' AND rel.item_id=' . $filter_item
				. ' GROUP BY f.id'
				//. $having
				. $orderby
				;
		} else {
			$query = 'SELECT f.*, u.name AS uploader, ('.$subf.') AS nrassigned'
				. ' FROM #__flexicontent_files AS f'
				. ' JOIN #__users AS u ON u.id = f.uploaded_by'
				. $where
				. ' GROUP BY f.id'
				//. $having
				. $orderby
				;
		}
		return $query;
	}

	/**
	 * Method to build the orderby clause of the query for the files
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildContentOrderBy()
	{
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');

		$filter_order		= $mainframe->getUserStateFromRequest( $option.'.filemanager.filter_order', 		'filter_order', 	'f.filename', 'cmd' );
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( $option.'.filemanager.filter_order_Dir',	'filter_order_Dir',	'', 'word' );

		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_Dir.', f.filename';

		return $orderby;
	}

	/**
	 * Method to build the where clause of the query for the files
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildContentWhere() {
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');
		$permission = FlexicontentHelperPerm::getPerm();

		$search 			= $mainframe->getUserStateFromRequest( $option.'.filemanager.search', 'search', '', 'string' );
		$filter 			= $mainframe->getUserStateFromRequest( $option.'.filemanager.filter', 'filter', '', 'int' );
		$search 			= $this->_db->getEscaped( trim(JString::strtolower( $search ) ) );
		$filter_uploader	= $mainframe->getUserStateFromRequest( $option.'.filemanager.filter_uploader', 'filter_uploader', '', 'int' );
		$filter_url			= $mainframe->getUserStateFromRequest( $option.'.filemanager.filter_url', 'filter_url', '', 'word' );
		$filter_secure		= $mainframe->getUserStateFromRequest( $option.'.filemanager.filter_secure', 'filter_secure', '', 'word' );
		$filter_ext			= $mainframe->getUserStateFromRequest( $option.'.filemanager.filter_ext', 'filter_ext', '', 'alnum' );
		$user				= & JFactory::getUser();

		$where = array();
		
		$CanViewAllFiles = $permission->CanViewAllFiles;
		
		if ( !$CanViewAllFiles ) {
			$where[] = ' uploaded_by = ' . (int)$user->id;
		}
		
		if ( $filter_uploader ) {
			$where[] = ' uploaded_by = ' . $filter_uploader;
		}

		if ( $filter_url ) {
			if ( $filter_url == 'F' ) {
				$where[] = ' url = 0';
			} else if ($filter_url == 'U' ) {
				$where[] = ' url = 1';
			}
		}

		if ( $filter_secure ) {
			if ( $filter_secure == 'M' ) {
				$where[] = ' secure = 0';
			} else if ($filter_secure == 'S' ) {
				$where[] = ' secure = 1';
			}
		}

		if ( $filter_ext ) {
			$where[] = ' ext = ' . $this->_db->Quote( $filter_ext );
		}

		if ($search && $filter == 1) {
			$where[] = ' LOWER(f.filename) LIKE '.$this->_db->Quote( '%'.$this->_db->getEscaped( $search, true ).'%', false );
		}

		if ($search && $filter == 2) {
			$where[] = ' LOWER(f.altname) LIKE '.$this->_db->Quote( '%'.$this->_db->getEscaped( $search, true ).'%', false );
		}

		$where 		= ( count( $where ) ? ' WHERE ' . implode( ' AND ', $where ) : '' );

		return $where;
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
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');
		
		$filter_assigned	= $mainframe->getUserStateFromRequest( $option.'.filemanager.filter_assigned', 'filter_assigned', '', 'word' );
		
		$having = '';
		
		if ( $filter_assigned ) {
			if ( $filter_assigned == 'O' ) {
				$having = ' HAVING COUNT(rel.fileid) = 0';
			} else if ($filter_assigned == 'A' ) {
				$having = ' HAVING COUNT(rel.fileid) > 0';
			}
		}
		
		return $having;
	}
	
	function _buildQueryUsers() {
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');
		// Get the WHERE and ORDER BY clauses for the query
		$where		= $this->_buildContentWhere();
		$orderby	= $this->_buildContentOrderBy();

		$query = 'SELECT u.id,u.name'
		. ' FROM #__flexicontent_files AS f'
		. ' LEFT JOIN #__users AS u ON u.id = f.uploaded_by'
		. $where
		. ' GROUP BY u.id'
		. $orderby
		;
		return $query;
	}

	/**
	 * Method to get files users
	 *
	 * @access public
	 * @return object
	 */
	function getUsers()
	{
		// Lets load the files if it doesn't already exist
		if (empty($this->_users))
		{
			$query = $this->_buildQueryUsers();
			$this->_users = $this->_getList($query);
		}

		return $this->_users;
	}
	
	/**
	 * Method to check if we can remove a file
	 * return false if the files are associated fields
	 * only check file fields
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
			$query	= 'SELECT COUNT(value)'
					. ' FROM #__flexicontent_fields_item_relations AS rel'
					. ' LEFT JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id'
					. ' WHERE fi.field_type = ' . $this->_db->Quote('file')
					. ' AND value = ' . (int) $cid[$i]
					;
			$this->_db->setQuery( $query );
			$countf = $this->_db->loadResult();
			
			// retrieve the filename waiting for better method
			$query	= 'SELECT filename'
					. ' FROM #__flexicontent_files'
					. ' WHERE id = ' . (int) $cid[$i]
					;
			$this->_db->setQuery( $query );
			$name = $this->_db->loadResult();

			// Image field relation sub query
			$query	= 'SELECT COUNT(value)'
					. ' FROM #__flexicontent_fields_item_relations AS rel'
					. ' LEFT JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id'
					. ' WHERE fi.field_type = ' . $this->_db->Quote('image')
					. ' AND value LIKE ' . $this->_db->Quote( '%'.$this->_db->getEscaped( $name, true ).'%', false );
					;
			$this->_db->setQuery( $query );
			$counti = $this->_db->loadResult();

			if ($counti > 0 || $countf > 0) {
				return false;
				}
			}
		return true;
		}
	}

	/**
	 * Method to remove a file
	 *
	 * @access	public
	 * @return	string $msg
	 * @since	1.
	 */
	function delete($cid)
	{
		if (count( $cid ))
		{
			jimport('joomla.filesystem.file');
		
			$cids = implode( ',', $cid );
		
			$query = 'SELECT f.filename, f.url, f.secure'
					. ' FROM #__flexicontent_files AS f'
					. ' WHERE f.id IN ('. $cids .')';
		
			$this->_db->setQuery( $query );
			$files = $this->_db->loadObjectList();
			
			foreach($files as $file)
			{
				if ($file->url != 1)
				{
					$basepath	= $file->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;
					$path 		= JPath::clean($basepath.DS.DS.$file->filename);
					if (!JFile::delete($path)) {
						JError::raiseWarning(100, JText::_( 'FLEXI_UNABLE_TO_DELETE' ).$path);
					}
				}
			}
		
			$query = 'DELETE FROM #__flexicontent_files'
			. ' WHERE id IN ('. $cids .')';

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
	 * Method to count the image relation of a file
	 *
	 * @access	public
	 * @return	string $msg
	 * @since	1.
	 */
	function countImageRelations(&$rows)
	{
		//for ($i=0; $i<count($rows); $i++)
		foreach ($rows as $row)
		{
			$query	= 'SELECT COUNT(value)'
					. ' FROM #__flexicontent_fields_item_relations AS rel'
					. ' LEFT JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id'
					. ' WHERE fi.field_type = ' . $this->_db->Quote('image')
					. ' AND value LIKE ' . $this->_db->Quote( '%'.$this->_db->getEscaped( $row->filename, true ).'%', false );
					;
			$this->_db->setQuery($query);
			$row->iassigned = $this->_db->loadResult();
		}
		return $rows;	
	}
	
	/**
	 * Method to (un)publish a file
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function publish($cid = array(), $publish = 1)
	{
		$user 	=& JFactory::getUser();

		if (count( $cid )) {
			$cids = implode( ',', $cid );

			$query = 'UPDATE #__flexicontent_files'
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
		return $cid;
	}
	
	function getItems() {
		// File field relation sub query
		$query = 'SELECT i.id,i.title'
			. ' FROM #__flexicontent_fields_item_relations AS rel'
			. ' JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id'
			. ' JOIN #__content AS i ON i.id = rel.item_id'
			. ' WHERE fi.field_type = ' . $this->_db->Quote('file')
			. ' GROUP BY i.id'
			;
		$this->_db->setQuery( $query );
		$lists = $this->_db->loadObjectList();echo mysql_error();
		return $lists?$lists:array();
	}
}
?>