<?php
/**
 * @version 1.5 stable $Id: fileselement.php 1342 2012-06-07 01:48:19Z ggppdk $
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
jimport('joomla.filesystem.file');

if ( JFactory::getApplication()->isSite() ) {
	// needed for frontend
	JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
	JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);
}

/**
 * FLEXIcontent Component Fileselement Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelFileselement extends JModel
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
	 
	 /**
	 * file users
	 *
	 * @var object
	 */
	var $_users = null;

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
	 * @return object
	 */
	function getData()
	{
		// Lets load the files if it doesn't already exist
		if (empty($this->_data))
		{
			$query = $this->_buildQuery();
			$this->_data = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));
			$db =& JFactory::getDBO();
			$db->setQuery("SELECT FOUND_ROWS()");
			$this->_total = $db->loadResult();

			$this->_data = flexicontent_images::BuildIcons($this->_data);

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
	function getPagination() {
		// Lets load the files if it doesn't already exist
		if (empty($this->_pagination))
		{
			jimport('joomla.html.pagination');
			$this->_pagination = new JPagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}

		return $this->_pagination;
	}

	/**
	 * Method to get files having the given extensions from a given folder
	 *
	 * @access private
	 * @return integer
	 * @since 1.0
	 */
	function getFilesFromPath($itemid, $fieldid, $append_item=1, $append_field=0, $folder_param_name='dir', $exts='jpg,jpeg,gif,png')
	{
		$app = JFactory::getApplication();
		$option = JRequest::getVar('option');
		
		$gallery_folder = $this->getFieldFolderPath($itemid, $fieldid, $append_item, $append_field, $folder_param_name);
		//echo $gallery_folder ."<br />";
		
		// Create folder for current language
		if (!is_dir($gallery_folder)) {
			mkdir($gallery_folder, $mode = 0755, $recursive=true);
		}
		
		// Get all image files with a .jpg extension.
		$images = glob($gallery_folder . "/*.{".$exts."}", GLOB_BRACE);
		
		// Get image names
		$rows = array();
		foreach($images as $i => $image) {
			$pinfo = pathinfo($image);
			//echo "<pre>"; print_r($pinfo); exit;
			$row = new stdClass();
			$row->ext = $pinfo['extension'];
			$row->filename = $pinfo['filename'].".".$pinfo['extension'];
			$row->size = sprintf("%.0f KB", (filesize($image) / 1024) );
			$row->altname = $pinfo['filename'];
			$row->uploader = '-';
			$row->uploaded = date("F d Y H:i:s.", filectime($image) );
			$row->id = $i;
			$rows[] = $row;
		}
		
		return $rows;
	}
	
	
	/**
	 * Method to get field parameters
	 *
	 * @access	public
	 * @param	int file identifier
	 */
	function & getFieldParams($fieldid)
	{
		static $field_params = null;
		if ($field_params) return $field_params;
		
		$field_name = $this->getFieldName($fieldid);
		
		$db =& JFactory::getDBO();
		$query = "SELECT attribs, published FROM #__flexicontent_fields WHERE name='".$field_name."'";
		$db->setQuery($query);
		$data = $db->loadObject();
		
		//print_r($data);
		
		if ($db->getErrorNum()) {
			echo $query."<br /><br />".$db->getErrorMsg()."<br />";
		}
		
		$field_params = new JParameter($data->attribs);
		return $field_params;
	}
	
	
	/**
	 * Method to get the folder path defined in a field
	 *
	 * @access	public
	 * @param	int file identifier
	 */
	function getFieldFolderPath($itemid, $fieldid, $append_item=1, $append_field=0, $folder_param_name='dir')
	{
		$field_params = & $this->getFieldParams($fieldid);
		$gallery_path = JPATH_SITE.DS.$field_params->get($folder_param_name, 'images/stories/flexicontent') . '/';
		if ($append_item) $gallery_path .= 'item_' . $itemid;
		if ($append_field) $gallery_path .= '_field_' . $fieldid;
		$gallery_path .= '/original';		
		return str_replace('\\','/', $gallery_path);
	}		
	


	/**
	 * Method to build the query for the files
	 *
	 * @access private
	 * @return integer
	 * @since 1.0
	 */
	function _buildQuery() {
		$mainframe = JFactory::getApplication();
		$option = JRequest::getVar('option');
		// Get the WHERE and ORDER BY clauses for the query
		$where		= $this->_buildContentWhere();
		$orderby	= $this->_buildContentOrderBy();
		$filter_item 		= $mainframe->getUserStateFromRequest( $option.'.fileselement.item_id', 'item_id', 0, 'int' );

		if($filter_item) 
		{
			$session	= JFactory::getSession();
			$files 		= $session->get('fileselement.'.$filter_item, null);
			$files 		= $files?$files:array();
			$files2 	= $this->getItemFiles($filter_item);
			$files 		= array_merge($files, $files2);
			$files 		= array_unique($files);
			$session->set('fileselement.'.$filter_item, $files);
			$files = "'".implode("','", $files)."'";
			$query = 'SELECT SQL_CALC_FOUND_ROWS f.*, u.name AS uploader'
			. ' FROM #__flexicontent_files AS f'
			. ' JOIN #__users AS u ON u.id = f.uploaded_by'
			. $where
			. ' AND f.id IN (' . $files . ')'
			. ' GROUP BY f.id'
			//. $having
			. $orderby
			;
		} else {
			$query = 'SELECT SQL_CALC_FOUND_ROWS f.*, u.name AS uploader'
			. ' FROM #__flexicontent_files AS f'
			. ' LEFT JOIN #__users AS u ON u.id = f.uploaded_by'
			. $where
			. ' GROUP BY f.id'
			. $orderby
			;
		}
		return $query;
	}
	
	function getItemFiles($filter_item=0) {
		$mainframe = JFactory::getApplication();
		$option = JRequest::getVar('option');
		//$filter_item 		= $mainframe->getUserStateFromRequest( $option.'.fileselement.item_id', 'item_id', 0, 'int' );
		if($filter_item) {
			$where		= $this->_buildContentWhere();
			$db = JFactory::getDBO();
			$query = 'SELECT f.id'
			. ' FROM #__flexicontent_files AS f'
			. ' JOIN #__flexicontent_fields_item_relations AS rel ON f.id = rel.value'
			. ' JOIN #__users AS u ON u.id = f.uploaded_by'
			. ' JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id'
			. $where
			. ' AND fi.field_type = ' . $this->_db->Quote('file')
			. ($filter_item?' AND rel.item_id=' . $filter_item : '')
			. ' GROUP BY f.id'
			;
			$db->setQuery($query);
			$items = $db->loadResultArray();
			$items = $items?$items:array();
			return $items;
		}
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

		$filter_order		= $mainframe->getUserStateFromRequest( $option.'.fileselement.filter_order', 		'filter_order', 	'f.filename', 'cmd' );
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( $option.'.fileselement.filter_order_Dir',	'filter_order_Dir',	'', 'word' );

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
	function _buildContentWhere()
	{
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');

		$search 			= $mainframe->getUserStateFromRequest( $option.'.fileselement.search', 'search', '', 'string' );
		$filter 			= $mainframe->getUserStateFromRequest( $option.'.fileselement.filter', 'filter', 1, 'int' );
		$search 			= $this->_db->getEscaped( trim(JString::strtolower( $search ) ) );
		$filter_uploader	= $mainframe->getUserStateFromRequest( $option.'.fileselement.filter_uploader', 'filter_uploader', 0, 'int' );
		$filter_url			= $mainframe->getUserStateFromRequest( $option.'.fileselement.filter_url', 'filter_url', '', 'word' );
		$filter_secure		= $mainframe->getUserStateFromRequest( $option.'.fileselement.filter_secure', 'filter_secure', '', 'word' );
		$filter_ext			= $mainframe->getUserStateFromRequest( $option.'.fileselement.filter_ext', 'filter_ext', '', 'alnum' );
		$user				= & JFactory::getUser();

		$where = array();
		
		if (FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$CanViewAllFiles = $permission->CanViewAllFiles;
		} else if (FLEXI_ACCESS) {
			$CanViewAllFiles	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'viewallfiles', 'users', $user->gmid) : 1;
		} else {
			$CanViewAllFiles	= 1;
		}
		
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
	
	function getItems() {
		// FC items list sub query
		$query	= 'SELECT i.id, i.title'
				. ' FROM #__content AS i '
				. (!FLEXI_J16GE ? ' WHERE i.sectionid = ' . FLEXI_SECTION : '')
				. ' ORDER BY i.title ASC'
				;
		$this->_db->setQuery( $query );
		$lists = $this->_db->loadObjectList();
		return $lists?$lists:array();
	}
	
	function getFieldName($fieldid) {
		$db = &JFactory::getDBO();
		$query = "SELECT name FROM #__flexicontent_fields WHERE id='{$fieldid}';";
		$db->setQuery($query);
		return $db->loadResult();
	}
}
?>