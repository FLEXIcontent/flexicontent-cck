<?php
/**
 * @version 1.5 stable $Id$
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
class FlexicontentModelFileselement extends JModelLegacy
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

		$app    = JFactory::getApplication();
		$option = JRequest::getVar('option');

		$limit      = $app->getUserStateFromRequest( $option.'.fileselement.limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart = $app->getUserStateFromRequest( $option.'.fileselement.limitstart', 'limitstart', 0, 'int' );

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
		// Get items using files VIA (single property) field types (that store file ids) by using main query
		$s_assigned_via_main = false;
			
		$s_assigned_fields = false; //array('file', 'minigallery');
		$m_assigned_fields = false; //array('image');
		
		$m_assigned_props = array('image'=>'originalname');
		$m_assigned_vals = array('image'=>'filename');
		
		// Lets load the files if it doesn't already exist
		if (empty($this->_data))
		{
			$query = $s_assigned_via_main  ?  $this->_buildQuery($s_assigned_fields)  :  $this->_buildQuery();
			
			$this->_data = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));
			if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
			
			$this->_db->setQuery("SELECT FOUND_ROWS()");
			$this->_total = $this->_db->loadResult();
			
			$this->_data = flexicontent_images::BuildIcons($this->_data);
			
			// Single property fields, get file usage (# assignments), if not already done by main query
			if ( !$s_assigned_via_main && $s_assigned_fields) {
				foreach ($s_assigned_fields as $field_type) {
					$this->countFieldRelationsSingleProp( $this->_data, $field_type );
				}
			}
			// Multi property fields, get file usage (# assignments)
			if ($m_assigned_fields) {
				foreach ($m_assigned_fields as $field_type) {
					$field_prop = $m_assigned_props[$field_type];
					$value_prop = $m_assigned_vals[$field_type];
					$this->countFieldRelationsMultiProp($this->_data, $value_prop, $field_prop, $field_type='image');
				}
			}
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
			$pinfo['filename'] = !isset($pinfo['filename']) ?   			// workaround for 'filename' added in PHP 5.2+
				str_replace('.'.$pinfo['extension'], '', $pinfo['basename']) : $pinfo['filename'];
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
		
		$db = JFactory::getDBO();
		$query = "SELECT attribs, published FROM #__flexicontent_fields WHERE name='".$field_name."'";
		$db->setQuery($query);
		$data = $db->loadObject();
		if ($db->getErrorNum())  echo $query."<br /><br />".$db->getErrorMsg()."<br />";
		
		$field_params = FLEXI_J16GE ? new JRegistry($data->attribs) : new JParameter($data->attribs);
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
		$field_params = $this->getFieldParams($fieldid);
		$gallery_path = JPATH_SITE.DS.$field_params->get($folder_param_name, 'images/stories/flexicontent') . '/';
		if ($append_item) $gallery_path .= 'item_' . $itemid;
		if ($append_field) $gallery_path .= '_field_' . $fieldid;
		$gallery_path .= '/original';		
		return str_replace('\\','/', $gallery_path);
	}
	
	
	/**
	 * Method to get the field name when given fieldid
	 *
	 * @access	public
	 * @param	int file identifier
	 */
	function getFieldName($fieldid) {
		$db = JFactory::getDBO();
		$query = "SELECT name FROM #__flexicontent_fields WHERE id='{$fieldid}';";
		$db->setQuery($query);
		return $db->loadResult();
	}
	
	
	/**
	 * Method to build the query for the files
	 *
	 * @access private
	 * @return integer
	 * @since 1.0
	 */
	function _buildQuery( $assigned_fields=array(), $item_id=0, $ids_only=false )
	{
		$app    = JFactory::getApplication();
		$option = JRequest::getVar('option');
		
		// Get the WHERE, HAVING and ORDER BY clauses for the query
		$where = $this->_buildContentWhere();
		if (!$ids_only) {
			$orderby = $this->_buildContentOrderBy();
		}
		//$having = $this->_buildContentHaving();
		
		$filter_item = $item_id  ?  $item_id  :  $app->getUserStateFromRequest( $option.'.fileselement.item_id', 'item_id', 0, 'int' );
		
		$extra_join = '';
		$extra_where = '';
		
		if ($filter_item) {
			$extra_join	.= ' JOIN #__flexicontent_fields_item_relations AS rel ON rel.item_id = '. $filter_item .' AND f.id = rel.value ';
			$extra_join	.= ' JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id AND fi.field_type = ' . $this->_db->Quote('file');
		}
		
		if ( !$ids_only ) {
			if (FLEXI_J16GE) {
				$extra_join .= ' LEFT JOIN #__viewlevels as level ON level.id=f.access';
				$extra_join .= ' LEFT JOIN #__usergroups AS g ON g.id = f.access';
			} else {
				$extra_join .= ' LEFT JOIN #__groups AS g ON g.id = f.access';
			}
		}
		
		if ( $ids_only ) {
			$columns = ' f.id ';
		} else {
			$columns = ' SQL_CALC_FOUND_ROWS f.*, u.name AS uploader, ';
			if ( $assigned_fields && count($assigned_fields) ) {
				foreach ($assigned_fields as $field_type) {
					// Field relation sub query for counting file assignment to this field type
					$assigned_query	= 'SELECT COUNT(value)'
						. ' FROM #__flexicontent_fields_item_relations AS rel'
						. ' JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id'
						. ' WHERE fi.field_type = ' . $this->_db->Quote($field_type)
						. ' AND value = f.id'
						;
					$columns .= '('.$assigned_query.') AS assigned_'.$field_type.', ';
				}
			}
			$columns .= (FLEXI_J16GE ? 'level.title as access_level, g.title AS groupname ' : 'g.name AS groupname ');
		}
		
		$query = 'SELECT '. $columns
			. ' FROM #__flexicontent_files AS f'
			. ' JOIN #__users AS u ON u.id = f.uploaded_by'
			. $extra_join
			. $where
			. $extra_where
			//. ' GROUP BY f.id'
			//. $having
			. (!$ids_only ? $orderby : '')
			;
		
		return $query;
	}
	
	
	/**
	 * Method to build files used by a given item 
	 *
	 * @access private
	 * @return integer
	 * @since 1.0
	 */
	function getItemFiles($item_id=0)
	{
		$db = JFactory::getDBO();
		$query = $this->_buildQuery( $assigned_fields=array(), $ids_only=true, $item_id );
		$db->setQuery($query);
		$items = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
		$items = $items?$items:array();
		return $items;
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
		$app    = JFactory::getApplication();
		$option = JRequest::getVar('option');

		$filter_order     = $app->getUserStateFromRequest( $option.'.fileselement.filter_order', 		'filter_order', 	'f.filename', 'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest( $option.'.fileselement.filter_order_Dir',	'filter_order_Dir',	'', 'word' );

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
		$app    = JFactory::getApplication();
		$user   = JFactory::getUser();
		$option = JRequest::getVar('option');

		$filter 	= $app->getUserStateFromRequest( $option.'.fileselement.filter', 'filter', 1, 'int' );
		$search 	= $app->getUserStateFromRequest( $option.'.fileselement.search', 'search', '', 'string' );
		$search 	= trim( JString::strtolower( $search ) );
		$filter_uploader= $app->getUserStateFromRequest( $option.'.fileselement.filter_uploader', 'filter_uploader', 0, 'int' );
		$filter_url			= $app->getUserStateFromRequest( $option.'.fileselement.filter_url', 'filter_url', '', 'word' );
		$filter_secure	= $app->getUserStateFromRequest( $option.'.fileselement.filter_secure', 'filter_secure', '', 'word' );
		$filter_ext			= $app->getUserStateFromRequest( $option.'.fileselement.filter_ext', 'filter_ext', '', 'alnum' );

		$where = array();
		
		if (FLEXI_J16GE || FLEXI_ACCESS) {
			$permission = FlexicontentHelperPerm::getPerm();
			$CanViewAllFiles = $permission->CanViewAllFiles;
		} else {
			$CanViewAllFiles	= 1;
		}
		
		if ( !$CanViewAllFiles ) {
			$where[] = ' uploaded_by = ' . (int)$user->id;
		} else if ( $filter_uploader ) {
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
			$search_escaped = FLEXI_J16GE ? $this->_db->escape( $search, true ) : $this->_db->getEscaped( $search, true );
			$where[] = ' LOWER(f.filename) LIKE '.$this->_db->Quote( '%'.$search_escaped.'%', false );
		}

		if ($search && $filter == 2) {
			$search_escaped = FLEXI_J16GE ? $this->_db->escape( $search, true ) : $this->_db->getEscaped( $search, true );
			$where[] = ' LOWER(f.altname) LIKE '.$this->_db->Quote( '%'.$search_escaped.'%', false );
		}

		$where 		= ( count( $where ) ? ' WHERE ' . implode( ' AND ', $where ) : '' );

		return $where;
	}
	
	
	/**
	 * Method to build the query for file uploaders according to current filtering
	 *
	 * @access private
	 * @return integer
	 * @since 1.0
	 */
	function _buildQueryUsers() {
		// Get the WHERE and ORDER BY clauses for the query
		$where = $this->_buildContentWhere();
		
		$query = 'SELECT u.id,u.name'
		. ' FROM #__flexicontent_files AS f'
		. ' LEFT JOIN #__users AS u ON u.id = f.uploaded_by'
		. $where
		. ' GROUP BY u.id'
		. ' ORDER BY u.name'
		;
		return $query;
	}
	
	
	/**
	 * Method to get file uploaders according to current filtering (Currently not used ?)
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
	 * Method to find fields using DB mode when given a field type,
	 * this is meant for field types that may or may not use files from the DB
	 *
	 * @access	public
	 * @return	string $msg
	 * @since	1.
	 */
	function getFieldsUsingDBmode($field_type)
	{
		$db = JFactory::getDBO();
		// Some fields may not be using DB, create a limitation for them
		switch($field_type) {
			case 'image':
				$query = "SELECT id FROM #__flexicontent_fields WHERE field_type='image' AND attribs NOT LIKE '%image_source=1%'";
				$this->_db->setQuery($query);
				$field_ids = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
				if ($field_ids) {
					$field_ids_list = " AND fi.id IN ('". implode("','", $field_ids) ."')";
				}
				break;
			
			default:
				$field_ids_list = '';
				break;
		}
	}
	
	
	/**
	 * Method to get items using files VIA (single property) field types that store file ids !
	 *
	 * @access public
	 * @return object
	 */
	function getItemsSingleprop( $field_types=array('file','minigallery'), $file_ids=array(), $count_items=false)
	{
		$app    = JFactory::getApplication();
		$user   = JFactory::getUser();
		$option = JRequest::getVar('option');
		
		$filter_uploader	= $app->getUserStateFromRequest( $option.'.fileselement.filter_uploader', 'filter_uploader', 0, 'int' );
		
		$field_type_list = $this->_db->Quote( implode( "','", $field_types ), $escape=false );
		
		$where = array();
		
		if (FLEXI_J16GE || FLEXI_ACCESS) {
			$permission = FlexicontentHelperPerm::getPerm();
			$CanViewAllFiles = $permission->CanViewAllFiles;
		} else {
			$CanViewAllFiles	= 1;
		}
		
		$file_ids_list = '';
		if ( count($file_ids) ) {
			$file_ids_list = ' AND f.id IN (' . "'". implode("','", $file_ids)  ."')";
		} else if ( !$CanViewAllFiles ) {
			$where[] = ' f.uploaded_by = ' . (int)$user->id;
		} else if ( $filter_uploader ) {
			$where[] = ' f.uploaded_by = ' . $filter_uploader;
		}
		
		$where = ( count( $where ) ? ' WHERE ' . implode( ' AND ', $where ) : '' );
		$groupby = !$count_items  ?  ' GROUP BY i.id'  :  ' GROUP BY f.id';   // file maybe used in more than one fields or ? in more than one values for same field
		$orderby = !$count_items  ?  ' ORDER BY i.title ASC'  :  '';
		
		// File field relation sub query
		$query = 'SELECT '. ($count_items  ?  'f.id as file_id, COUNT(i.id) as item_count'  :  'i.id as id, i.title')
			. ' FROM #__content AS i'
			. ' JOIN #__flexicontent_fields_item_relations AS rel ON rel.item_id = i.id'
			. ' JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id AND fi.field_type IN ('. $field_type_list .')'
			. ' JOIN #__flexicontent_files AS f ON f.id=rel.value '. $file_ids_list
			//. ' JOIN #__users AS u ON u.id = f.uploaded_by'
			. $where
			. $groupby
			. $orderby
			;
		//echo nl2br( "\n".$query."\n");
		$this->_db->setQuery( $query );
		$_item_data = $this->_db->loadObjectList($count_items ? 'file_id' : 'id');
		if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
		
		$items = array();
		if ($_item_data) foreach ($_item_data as $item) {
			if ($count_items) {
				$items[$item->file_id] = ((int) @ $items[$item->file_id]) + $item->item_count;
			} else {
				$items[$item->title] = $item;
			}
		}
		
		//echo "<pre>"; print_r($items); exit;
		return $items;
	}
	
	
	/**
	 * Method to get items using files VIA (multi property) field types that store file as as property either file id or filename!
	 *
	 * @access public
	 * @return object
	 */
	function getItemsMultiprop( $field_props=array('image'=>'originalname'), $value_props=array('image'=>'filename') , $file_ids=array(), $count_items=false)
	{
		$app    = JFactory::getApplication();
		$user   = JFactory::getUser();
		$option = JRequest::getVar('option');
		
		$filter_uploader	= $app->getUserStateFromRequest( $option.'.fileselement.filter_uploader', 'filter_uploader', 0, 'int' );
		
		$where = array();
		
		if (FLEXI_J16GE || FLEXI_ACCESS) {
			$permission = FlexicontentHelperPerm::getPerm();
			$CanViewAllFiles = $permission->CanViewAllFiles;
		} else {
			$CanViewAllFiles	= 1;
		}
		
		$file_ids_list = '';
		if ( count($file_ids) ) {
			$file_ids_list = ' AND f.id IN (' . "'". implode("','", $file_ids)  ."')";
		} else if ( !$CanViewAllFiles ) {
			$where[] = ' f.uploaded_by = ' . (int)$user->id;
		} else if ( $filter_uploader ) {
			$where[] = ' f.uploaded_by = ' . $filter_uploader;
		}
		
		$where 		= ( count( $where ) ? ' WHERE ' . implode( ' AND ', $where ) : '' );
		$groupby = !$count_items  ?  ' GROUP BY i.id'  :  ' GROUP BY f.id';   // file maybe used in more than one fields or ? in more than one values for same field
		$orderby = !$count_items  ?  ' ORDER BY i.title ASC'  :  '';
		
		// Serialized values are like : "__field_propname__";s:33:"__value__"
		$format_str = 'CONCAT("%%","\"%s\";s:%%:%%\"",%s,"\"%%")';
		$items = array();
		$files = array();
		
		foreach ($field_props as $field_type => $field_prop)
		{
			// Some fields may not be using DB, create a limitation for them
			$field_ids = $this->getFieldsUsingDBmode($field_type);
			$field_ids_list = !$field_ids  ?  ""  :  " AND fi.id IN ('". implode("','", $field_ids) ."')";
			
			// Create a matching condition for the value depending on given configuration (property name of the field, and value property of file: either id or filename or ...)
			$value_prop = $value_props[$field_type];
			$like_str = FLEXI_J16GE ? $this->_db->escape( 'f.'.$value_prop, false ) : $this->_db->getEscaped( 'f.'.$value_prop, false );
			$like_str = sprintf( $format_str, $field_prop, $like_str );
			
			// File field relation sub query
			$query = 'SELECT '. ($count_items  ?  'f.id as file_id, COUNT(i.id) as item_count'  :  'i.id as id, i.title')
				. ' FROM #__content AS i'
				. ' JOIN #__flexicontent_fields_item_relations AS rel ON rel.item_id = i.id'
				. ' JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id AND fi.field_type IN ('. $this->_db->Quote( $field_type ) .')' . $field_ids_list
				. ' JOIN #__flexicontent_files AS f ON rel.value LIKE '. $like_str . $file_ids_list
				//. ' JOIN #__users AS u ON u.id = f.uploaded_by'
				. $where
				. $groupby
				. $orderby
				;
			//echo nl2br( "\n".$query."\n");
			$this->_db->setQuery( $query );
			$_item_data = $this->_db->loadObjectList($count_items ? 'file_id' : 'id');
			if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
			
			if ($_item_data) foreach ($_item_data as $item) {
				if ($count_items) {
					$items[$item->file_id] = ((int) @ $items[$item->file_id]) + $item->item_count;
				} else {
					$items[$item->title] = $item;
				}
			}
			//echo "<pre>"; print_r($_item_data); exit;
		}
		
		//echo "<pre>"; print_r($items); exit;
		return $items;
	}
	
}
?>