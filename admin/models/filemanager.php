<?php
/**
 * @version 1.5 stable $Id: filemanager.php 1750 2013-09-03 20:50:59Z ggppdk $
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

jimport('legacy.model.legacy');
jimport('joomla.filesystem.file');
jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.path');
use Joomla\String\StringHelper;

/**
 * FLEXIcontent Component Filemanager Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelFilemanager extends JModelLegacy
{
	var $records_dbtbl = 'flexicontent_files';
	var $records_jtable = 'flexicontent_files';

	/**
	 * file data
	 *
	 * @var object
	 */
	var $_data = null;
	var $_data_pending = null;

	/**
	 * file total
	 *
	 * @var integer
	 */
	var $_total = null;
	var $_total_pending = null;

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
	 * flags to indicate adding file - item assignments to session
	 *
	 * @var bool
	 */
	var $sess_assignments = null;

	
	/**
	 * flags to indicate return only the files pending to be assingned
	 *
	 * @var bool
	 */
	var $_pending = false;


	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	 
	/**
	 * uploaders
	 *
	 * @var object
	 */
	var $_users = null;

	function __construct()
	{
		parent::__construct();
		
		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');
		$view   = $jinput->get('view', '', 'cmd');
		$fcform = $jinput->get('fcform', 0, 'int');
		$p      = $option.'.'.$view.'.';
		
		$this->fieldid = $jinput->get('field', null, 'int');  // not yet used for filemanager view, only for fileselement views
		$this->viewid  = $view.$this->fieldid;
		$this->sess_assignments = true;



		// ***
		// *** Load backend language file if model gets loaded in frontend
		// ***
		if ( JFactory::getApplication()->isSite() )
		{
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);
		}		


		
		// **************
		// view's Filters
		// **************
		
		// Various filters
		// ...
		
		// Text search
		$search = $fcform ? $jinput->get('search', '', 'string')  :  $app->getUserStateFromRequest( $p.'search',  'search',  '',  'string' );
		$this->setState('search', $search);
		$app->setUserState($p.'search', $search);

		// Text search scope
		$scope  = $fcform ? $jinput->get('scope',  1,  'int')     :  $app->getUserStateFromRequest( $p.'scope',   'scope',   1,   'int' );
		$this->setState('scope', $scope);
		$app->setUserState($p.'scope', $scope);



		// ****************************************
		// Ordering: filter_order, filter_order_Dir
		// ****************************************
		
		$default_order     = 'f.uploaded'; //'f.id';
		$default_order_dir = 'DESC';
		
		$filter_order      = $fcform ? $jinput->get('filter_order',     $default_order,      'cmd')  :  $app->getUserStateFromRequest( $p.'filter_order',     'filter_order',     $default_order,      'cmd' );
		$filter_order_Dir  = $fcform ? $jinput->get('filter_order_Dir', $default_order_dir, 'word')  :  $app->getUserStateFromRequest( $p.'filter_order_Dir', 'filter_order_Dir', $default_order_dir, 'word' );
		
		if (!$filter_order)     $filter_order     = $default_order;
		if (!$filter_order_Dir) $filter_order_Dir = $default_order_dir;
		
		$this->setState('filter_order', $filter_order);
		$this->setState('filter_order_Dir', $filter_order_Dir);
		
		$app->setUserState($p.'filter_order', $filter_order);
		$app->setUserState($p.'filter_order_Dir', $filter_order_Dir);
		
		
		
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
	 * Method to set the Record identifier and clear record rows
	 *
	 * @access	public
	 * @param	int Record identifier
	 */
	function setId($id)
	{
		// Set id and wipe data
		$this->_id	 = $id;
		$this->_data = null;
		$this->_total= null;
		$this->_data_pending  = null;
		$this->_total_pending = null;
	}


	/**
	 * Method to get files data
	 *
	 * @access public
	 * @return array
	 */
	function getDataPending()
	{
		$this->_pending = true;

		// Get files pending to be assigned
		if ($this->_data_pending === null)
		{
			$query = $this->_buildQuery();

			if ($query === false)
			{
				$this->_data_pending = array();
				$this->_total_pending = 0;
				return $this->_data_pending;
			}

			$this->_data_pending = $this->_getList($query);
			$this->_db->setQuery("SELECT FOUND_ROWS()");
			$this->_total_pending = $this->_db->loadResult();
		}

		return $this->_data_pending;
	}



	/**
	 * Method to get files data
	 *
	 * @access public
	 * @return array
	 */
	function getData()
	{
		$this->_pending = false;

		// Get items using files VIA (single property) field types (that store file ids) by using main query
		$s_assigned_via_main = false;

		// Files usage my single / multi property Fields, 
		//  -- Single property field types: store file ids
		//  -- Multi property field types: store file id or filename via some property name

		$s_assigned_fields = array('file', 'minigallery');
		$m_assigned_fields = array('image');

		$m_assigned_props = array('image'=>'originalname');
		$m_assigned_vals = array('image'=>'filename');

		// Lets load the files if it doesn't already exist
		if ($this->_data === null)
		{
			$query = $s_assigned_via_main
				? $this->_buildQuery($s_assigned_fields)
				: $this->_buildQuery();

			if ($query === false)
			{
				$this->_data = array();
				$this->_total = 0;
				return $this->_data;
			}

			$this->_data = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));
			$this->_db->setQuery("SELECT FOUND_ROWS()");
			$this->_total = $this->_db->loadResult();
			
			$filter_order = $this->getState( 'filter_order' );
			$default_size_message = $filter_order != 'f.size' ? null : '
				<span class="hasTooltip" title="' . JText::sprintf('FLEXI_PLEASE_REINDEX_FILE_STATISTICS', JText::_('FLEXI_INDEX_FILE_STATISTICS')) . '">
					<span class="icon-warning"></span>
					<span class="icon-loop"></span>
				</span>';
			$this->_data = flexicontent_images::BuildIcons($this->_data, $default_size_message);
			
			// Single property fields, get file usage (# assignments), if not already done by main query
			if ( !$s_assigned_via_main && $s_assigned_fields)
			{
				foreach ($s_assigned_fields as $field_type)
				{
					$this->countFieldRelationsSingleProp($this->_data, $field_type);
				}
			}
			// Multi property fields, get file usage (# assignments)
			if ($m_assigned_fields)
			{
				foreach ($m_assigned_fields as $field_type)
				{
					$field_prop = $m_assigned_props[$field_type];
					$value_prop = $m_assigned_vals[$field_type];
					$this->countFieldRelationsMultiProp($this->_data, $value_prop, $field_prop, $field_type);
				}
			}

			// These can be used by the items manager without need to recalculate
			if ($this->sess_assignments)
			{
				$session = JFactory::getSession();

				$fileid_to_itemids = $session->get('fileid_to_itemids', array(),'flexicontent');
				foreach ($this->_data as $row)
				{
					// we have multiple item list indexed by field type, concatanate these
					$itemids_list = !empty($row->item_list)  ?  implode(',', $row->item_list)  :  '';
					// now create a item ids array that contains duplicates
					$itemids_dup = ($itemids_list=='') ? array() : explode(',', $itemids_list);
					// make an array of unique item ids
					$itemids = array();  $n = 0;
					foreach ($itemids_dup as $itemid) $itemids[$itemid] = $n++;
					$fileid_to_itemids[$row->id] = $row->itemids = array_flip($itemids);
				}

				$session->set('fileid_to_itemids', $fileid_to_itemids, 'flexicontent');
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
		if ($this->_pending)
		{
			if ( $this->_total_pending === null )
			{
				$query = $this->_buildQuery();

				if ($query === false)
				{
					return $this->_total_pending = 0;
				}

				$this->_getList($query, 0, 1);
				$this->_db->setQuery("SELECT FOUND_ROWS()");
				$this->_total_pending = $this->_db->loadResult();
			}

			return $this->_total_pending;
		}
		else
		{
			if ( $this->_total === null )
			{
				$query = $this->_buildQuery();

				if ($query === false)
				{
					return $this->_total = 0;
				}

				$this->_getList($query, 0, 1);
				$this->_db->setQuery("SELECT FOUND_ROWS()");
				$this->_total = $this->_db->loadResult();
			}

			return $this->_total;
		}
	}
	
	
	/**
	 * Method to get a pagination object for the files
	 *
	 * @access public
	 * @return integer
	 */
	function getPagination()
	{
		// Create pagination object if it doesn't already exist
		if (empty($this->_pagination))
		{
			require_once (JPATH_COMPONENT_SITE.DS.'helpers'.DS.'pagination.php');
			$this->_pagination = new FCPagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}

		return $this->_pagination;
	}


	/**
	 * Method to get remove old temporary folders of fields in "folder mode"
	 *
	 * @access public
	 * @return array
	 * @since 3.0.0
	 */
	function cleanUpFolderModeFolders($path)
	{
		// Get file list according to filtering
		$it = new RegexIterator(new IteratorIterator(new DirectoryIterator($path)), '#item__[0-9]{4}_[0-9]{2}_[0-9]{2}_(.*)#i');
		$it->rewind();
		
		$now_date = time();
	
		while($it->valid())
		{
			if ($it->isDot())
			{
				$it->next();
				continue;
			}
			$subpath = $it->getPathName();  // filename including the folder subpath
			$dirname = basename($subpath);
			$date_str = str_replace('_', '-', substr($dirname, 6, 10));

			$directory_date = strtotime($date_str);
			$date_diff = $now_date - $directory_date;
			$days_diff = floor($date_diff / (60 * 60 * 24));
			//echo $subpath . ' ---- ' . $date_str . ' ---- Days DIFF: ' . $days_diff . '<br/>';

			// Remove old folders
			if ($days_diff > 2)
			{
				JFolder::delete($subpath);
			}

			$it->next();
		}
	}


	/**
	 * Method to get files having the given extensions from a given folder
	 *
	 * @access public
	 * @return array
	 * @since 3.0.0
	 */
	function getFilesFromPath($itemid, $fieldid, $exts=null, $pending = false)
	{
		// Set pending FLAG
		$this->_pending = $pending;

		// Retrieving files from a folder , do not use pagination
		$this->_total_pending = 0;
		$this->_total = 0;

		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');
		$cparams = JComponentHelper::getParams('com_flexicontent');

		$exts = $exts ?: $cparams->get('upload_extensions', 'bmp,csv,doc,docx,gif,ico,jpg,jpeg,odg,odp,ods,odt,pdf,png,ppt,pptx,swf,txt,xcf,xls,xlsx,zip,ics');
		$imageexts = array('jpg','gif','png','bmp','jpeg');  // Common image extensions
		$options = array();
		$gallery_folder = $this->getFieldFolderPath($itemid, $fieldid, $options);
		//echo $gallery_folder ."<br />";

		// Create field's folder if it does not exist already
		if (!is_dir($gallery_folder))
		{
			mkdir($gallery_folder, $mode = 0755, $recursive=true);
		}

		// Get file list according to filtering
		$exts = preg_replace("/[\s]*,[\s]*/", '|', $exts);
		$it = new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($gallery_folder)), '#(.*\.)('.$exts.')#i');
		$it->rewind();

		if ($this->_pending)
		{
			if (!$itemid)
			{
				return array();
			}
			$upload_context = 'fc_upload_history.item_' . $itemid . '_field_' . $fieldid;
			$session_files = JFactory::getSession()->get($upload_context, array());

			$names_pending = isset($session_files['names_pending'])
				? $session_files['names_pending']
				: array();
			$names_pending = array_flip($names_pending);
		}

		if ( $options['image_source'] === 1 )
		{
			$this->cleanUpFolderModeFolders($options['base_path']);
		}

		// Get file information
		static $mime_icons = array();
		$rows = array();
		$i = 1;
		while($it->valid())
		{
			if ($it->isDot())
			{
				$it->next();
				continue;
			}
			$filesubpath = $it->getSubPathName();  // filename including the folder subpath
			$filepath = $it->key();
			$pinfo = pathinfo($filepath);
			$row = new stdClass();
			$row->ext = $pinfo['extension'];

			// Convert directory separators inside the subpath
			$row->filename = str_replace('\\', '/', $filesubpath);  //$pinfo['filename'].".".$pinfo['extension'];

			// Lets load the files if it doesn't already exist
			if ($this->_pending)
			{
				if ( !isset($names_pending[$row->filename]))
				{
					$it->next();
					continue;
				}
			}

			// Try to create a UTF8 filename
			$row->filename_original = iconv(mb_detect_encoding($row->filename, mb_detect_order(), true), "UTF-8", $row->filename);
			$row->filename_original = $row->filename_original ? $row->filename_original : $row->filename;
			$row->size = sprintf("%.0f KB", (filesize($filepath) / 1024) );
			$row->altname = $pinfo['filename'];
			$row->uploader = '-';
			$row->uploaded = date("F d Y H:i:s.", filectime($filepath) );
			$row->id = $i;
			
			if ( in_array(strtolower($row->ext), $imageexts))
			{
				$row->icon = JUri::root()."components/com_flexicontent/assets/images/mime-icon-16/image.png";
			}
			else
			{
				$exists = $row->ext && isset($mime_icons[$row->ext])
					? $mime_icons[$row->ext]
					: null;

				// Check exists only once
				if ($row->ext && $exists === null)
				{
					$exists = $mime_icons[$row->ext] = file_exists(JPATH_SITE . '/components/com_flexicontent/assets/images/mime-icon-16/' . $row->ext . '.png');
				}

				$row->icon = $exists
					? JUri::root() . 'components/com_flexicontent/assets/images/mime-icon-16/' . $row->ext . '.png'
					: JUri::root() . 'components/com_flexicontent/assets/images/mime-icon-16/unknown.png';
			}

			if ($this->_pending)
			{
				$rows[$row->filename] = $row;
			}
			else
			{
				$rows[] = $row;
			}

			$i++;
			$it->next();
		}

		// Lets load the files if it doesn't already exist
		if ($this->_pending)
		{
			$_rows = array();
			foreach($names_pending as $filename => $file)
			{
				$_rows[] = $rows[$filename];
			}
			$rows = $_rows;
		}

		return $rows;
	}


	/**
	 * Method to get the folder path defined in a field
	 *
	 * @access	public
	 */
	function getFieldFolderPath($itemid, $fieldid, & $options = array())
	{
		$field = $this->getField($fieldid);

		if (!$field)
		{
			die(__FUNCTION__.'(): Field for field id:' . $fieldid . ' was not found');
		}

		// Load XML file of field
		$plugin_path = JPATH_PLUGINS . DS . 'flexicontent_fields' . DS . $field->field_type . DS . $field->field_type . '.xml';
		$form = new JForm('com_flexicontent.field.' . $field->field_type, array('control' => 'jform', 'load_data' => false));
		$form->load(file_get_contents($plugin_path), false, '/extension/config');

		$image_source_exists = (bool) $form->getField('image_source', 'attribs');
		$options['image_source'] = $image_source = $image_source_exists ? (int) $field->parameters->get('image_source') : null;

		// Currently we only handle image_source '1'
		if ($image_source===1)
		{
			$gallery_path_arr = array(
				'item_' . $itemid,
				'field_' . $fieldid
			);
			$options['base_path'] = JPATH::clean(JPATH_SITE . DS . $field->parameters->get('dir', 'images/stories/flexicontent'));
			$gallery_path = $options['base_path'] . DS . implode('_', $gallery_path_arr) . DS . 'original' . DS;
		}
		else if ($image_source===0 || $image_source===null)
		{
			$gallery_path = !empty($options['secure']) ? COM_FLEXICONTENT_FILEPATH.DS : COM_FLEXICONTENT_MEDIAPATH.DS;
		}
		else
		{
			die(__FUNCTION__.'(): image_source : $image_source for field id:' . $fieldid . ' is not implemented');
		}

		return JPATH::clean($gallery_path);
	}


	/**
	 * Method to get field data
	 *
	 * @access public
	 * @return object
	 */
	function getField($fieldid=0, $itemid=0)
	{
		static $fields = array();
		
		// Return cached field data
		$fieldid = (int) ($fieldid ?: $this->fieldid);
		if (isset($fields[$fieldid]))
		{
			return $fields[$fieldid];
		}

		// Get field data from DB
		$fields[$fieldid] = false;
		if ($fieldid)
		{
			$this->_db->setQuery('SELECT * FROM #__flexicontent_fields WHERE id= ' . $fieldid);
			$fields[$fieldid] = $this->_db->loadObject();
		}

		// Parse field parameters and find currently active item id and verrify item is editable by current user
		if (!empty($fields[$fieldid]))
		{
			$fields[$fieldid]->parameters = new JRegistry($fields[$fieldid]->attribs);

			$app  = JFactory::getApplication();
			$user = JFactory::getUser();
			$option = $app->input->get('option', '', 'cmd');
			$view   = $app->input->get('view', '', 'cmd');
			$u_item_id = $itemid ?: $app->getUserStateFromRequest( $option.'.'.$view.'.u_item_id', 'u_item_id', 0, 'string' );
			if (is_numeric($u_item_id))
			{
				$u_item_id = (int) $u_item_id;

				JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
				$record = JTable::getInstance($type = 'flexicontent_items', $prefix = '', $config = array());
				if ($record->load($u_item_id))
				{
					$asset = 'com_content.article.' . $u_item_id;
					$has_edit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $record->created_by == $user->get('id'));
					$u_item_id = $has_edit ? $u_item_id : 0;
				}
				else
				{
					$u_item_id = 0;
				}
			}
			$fields[$fieldid]->item_id = $u_item_id;
		}
		return $fields[$fieldid];
	}


	/**
	 * Method to build the query for the files
	 *
	 * @access private
	 * @return integer
	 * @since 1.0
	 */
	function _buildQuery( $assigned_fields=array(), $ids_only=false, $u_item_id=0 )
	{
		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');
		
		// Get the WHERE, HAVING and ORDER BY clauses for the query
		$join    = $this->_buildContentJoin();
		$where   = $this->_pending
			? $this->_buildContentWherePending()
			: $this->_buildContentWhere();
		if ($where === false)
		{
			return 'SELECT 1 FROM #__flexicontent_files WHERE 1=0';
		}

		if ($ids_only)
		{
			$orderby = '';
		}
		else
		{
			$orderby = $this->_pending
				? $this->_buildContentOrderByPending()
				: $this->_buildContentOrderBy();
		}
		$having  = ''; //$this->_buildContentHaving();
		
		// If a non numeric item ID was given then we will not match any values from DB, force returning none files (set to -1)
		$u_item_id = strlen($u_item_id) && !is_numeric($u_item_id)
			? -1
			: (int) $u_item_id;

		$filter_item = $u_item_id ?: $app->getUserStateFromRequest( $option.'.'.$this->viewid.'.item_id',   'item_id',   '',   'int' );

		if ($filter_item)
		{
			$join	.= ' JOIN #__flexicontent_fields_item_relations AS rel ON rel.item_id = '. $filter_item .' AND f.id = rel.value ';
			$join	.= ' JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id AND fi.field_type = ' . $this->_db->Quote('file');
		}
		
		if ( !$ids_only )
		{
			$join .= ' LEFT JOIN #__viewlevels AS level ON level.id = f.access';
		}
		
		if ( $ids_only )
		{
			$columns[] = 'f.id';
		}
		else
		{
			$columns[] = 'SQL_CALC_FOUND_ROWS f.*, u.name AS uploader,'
				.' CASE WHEN f.filename_original<>"" THEN f.filename_original ELSE f.filename END AS filename_displayed ';
			if ( !empty($assigned_fields) )
			{
				foreach ($assigned_fields as $field_type)
				{
					// Field relation sub query for counting file assignment to this field type
					$assigned_query	= 'SELECT COUNT(value)'
						. ' FROM #__flexicontent_fields_item_relations AS rel'
						. ' JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id'
						. ' WHERE fi.field_type = ' . $this->_db->Quote($field_type)
						. ' AND value = f.id'
						;
					$columns[] = '('.$assigned_query.') AS assigned_'.$field_type;
				}
			}
			$columns[] = 'CASE WHEN level.title IS NULL THEN CONCAT_WS(\'\', \'deleted:\', f.access) ELSE level.title END AS access_level';
		}
		
		$query = 'SELECT '. implode(', ', $columns)
			. ' FROM #__flexicontent_files AS f'
			. $join
			. $where
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
	function getItemFiles($u_item_id=0)
	{
		if ($this->_pending)
		{
			return array();
		}

		$query = $this->_buildQuery($assigned_fields=array(), $ids_only=true, $u_item_id);

		$items = $query === false
			? array()
			: $this->_db->setQuery($query)->loadColumn();

		return $items ?: array();
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
		$filter_order     = $this->getState( 'filter_order' );
		$filter_order_Dir = $this->getState( 'filter_order_Dir' );
		
		if ($filter_order=='f.filename_displayed') $filter_order = ' CASE WHEN f.filename_original<>"" THEN f.filename_original ELSE f.filename END ';
		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_Dir.', f.filename';

		return $orderby;
	}


	/**
	 * Method to build the orderby clause of the query for the files
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildContentOrderByPending()
	{
		$field = $this->getField();

		if (!$field || !$field->item_id)
		{
			return '';
		}

		$upload_context = 'fc_upload_history.item_' . $field->item_id . '_field_' . $field->id;
		$session_files = JFactory::getSession()->get($upload_context, array());

		$file_ids = isset($session_files['ids_pending'])
			? $session_files['ids_pending']
			: array();

		$orderby 	= $file_ids
			? ' ORDER BY FIELD(f.id, '. implode(', ', $file_ids) .')'
			: '';
		return $orderby;
	}


	function _buildContentJoin()
	{
		$join = ' JOIN #__users AS u ON u.id = f.uploaded_by';
		return $join;
	}


	/**
	 * Method to build the where clause of the query for the records pending assignment
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildContentWherePending()
	{
		$field = $this->getField();

		if (!$field || !$field->item_id)
		{
			return false;
		}

		$upload_context = 'fc_upload_history.item_' . $field->item_id . '_field_' . $field->id;
		$session_files = JFactory::getSession()->get($upload_context, array());

		$file_ids = isset($session_files['ids_pending'])
			? $session_files['ids_pending']
			: array();

		return !$file_ids
			? false
			: ' WHERE f.id IN ('.implode(', ', $file_ids).')';
	}


	/**
	 * Method to build the where clause of the query for the records
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildContentWhere()
	{
		$app    = JFactory::getApplication();
		$user   = JFactory::getUser();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');

		$where = array();

		$field  = $this->getField();
		$params = $field ? $field->parameters : new JRegistry();

		// Limit listed files to specific uploader,  1: current user, 0: any user, and respect 'filter_uploader' URL variable
		$limit_by_uploader = 0;

		// Calculate a default value for limiting to 'media' or 'secure' folder,  0: media folder, 1: secure folder, 2: no folder limitation AND respect 'filter_secure' URL variable
		$default_dir = 2;
		if ($field)
		{
			if (in_array($field->field_type, array('file', 'image')))
				$default_dir = 1;  // 'secure' folder
			else if (in_array($field->field_type, array('minigallery')))
				$default_dir = 0;  // 'media' folder
		}
		$target_dir = $params->get('target_dir', $default_dir);
		
		// Handles special cases of fields, that have special rules for listing specific files only
		if ($field && $field->field_type =='image' && $params->get('image_source') == 0)
		{
			$limit_by_uploader = (int) $params->get('limit_by_uploader', 0);
			if ($params->get('list_all_media_files', 0))
			{
				$where[] = ' f.ext IN ("jpg","gif","png","jpeg") ';
			}
			else
			{
				$filesUsedByImageField = $this->getFilesUsedByImageField($field, $params);
				if ($filesUsedByImageField)
				{
					$where[] = $filesUsedByImageField;
				}
			}
		}

		$scope  = $this->getState( 'scope' );
		$search = $this->getState( 'search' );
		$search = StringHelper::trim( StringHelper::strtolower( $search ) );
		
		$filter_lang			= $app->getUserStateFromRequest(  $option.'.'.$this->viewid.'.filter_lang',      'filter_lang',      '',          'string' );
		$filter_uploader  = $app->getUserStateFromRequest(  $option.'.'.$this->viewid.'.filter_uploader',  'filter_uploader',  0,           'int' );
		$filter_url       = $app->getUserStateFromRequest(  $option.'.'.$this->viewid.'.filter_url',       'filter_url',       '',          'word' );
		$filter_secure    = $app->getUserStateFromRequest(  $option.'.'.$this->viewid.'.filter_secure',    'filter_secure',    '',          'word' );
		$filter_ext       = $app->getUserStateFromRequest(  $option.'.'.$this->viewid.'.filter_ext',       'filter_ext',       '',          'alnum' );
		
		
		$permission = FlexicontentHelperPerm::getPerm();
		$CanViewAllFiles = $permission->CanViewAllFiles;

		// Limit via parameter, 2: List any file and respect 'filter_secure' URL variable, 1: limit to secure, 0: limit to media
		if ( strlen($target_dir) && $target_dir!=2 )
		{
			$filter_secure = $target_dir ? 'S' : 'M';   // force secure / media
		}

		// Limit via parameter, 1: limit to current user as uploader, 0: list files from any uploader, and respect 'filter_uploader' URL variable
		if ($limit_by_uploader) {
			$where[] = ' uploaded_by = ' . $user->id;
		} else if ( !$CanViewAllFiles ) {
			$where[] = ' uploaded_by = ' . (int)$user->id;
		} else if ( $filter_uploader ) {
			$where[] = ' uploaded_by = ' . $filter_uploader;
		}
		
		if ( $filter_lang ) {
			$where[] = ' language = '. $this->_db->Quote( $filter_lang );
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
		
		if ($search)
		{
			$escaped_search = $this->_db->escape( $search, true );
			
			$search_where = array();
			if ($scope == 1 || $scope == 0) {
				$search_where[] = ' LOWER(f.filename) LIKE '.$this->_db->Quote( '%'.$escaped_search.'%', false );
				$search_where[] = ' LOWER(f.filename_original) LIKE '.$this->_db->Quote( '%'.$escaped_search.'%', false );
			}
			if ($scope == 2 || $scope == 0) {
				$search_where[] = ' LOWER(f.altname) LIKE '.$this->_db->Quote( '%'.$escaped_search.'%', false );
			}
			if ($scope == 3 || $scope == 0) {
				$search_where[] = ' LOWER(f.description) LIKE '.$this->_db->Quote( '%'.$escaped_search.'%', false );
			}
			$where[] = '( '. implode( ' OR ', $search_where ) .' )';
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
		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');
		
		$filter_assigned	= $app->getUserStateFromRequest(  $option.'.'.$this->viewid.'.filter_assigned', 'filter_assigned', '', 'word' );
		
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


	/**
	 * Method to build the query for file uploaders according to current filtering
	 *
	 * @access private
	 * @return integer
	 * @since 1.0
	 */
	function _buildQueryUsers()
	{
		// Get the WHERE and ORDER BY clauses for the query
		$where = $this->_pending
			? $this->_buildContentWherePending()
			: $this->_buildContentWhere();
		if ($where === false)
		{
			return 'SELECT 1 FROM #__users WHERE 1=0';
		}
		
		$query = 'SELECT u.id, u.name'
			. ' FROM #__flexicontent_files AS f'
			. ' LEFT JOIN #__users AS u ON u.id = f.uploaded_by'
			. $where
			. ' GROUP BY u.id'
			. ' ORDER BY u.name'
			;
		return $query;
	}


	/**
	 * Method to build find the (id of) files used by an image field
	 *
	 * @access public
	 * @return integer
	 * @since 3.2
	 */
	function getFilesUsedByImageField($field, $params)
	{
		// Get configuration parameters
		$target_dir = (int) $params->get('target_dir', 1);
		$securepath = JPath::clean(($target_dir ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH).DS);

		// Retrieve usage of images for the given field from the DB
		$query = 'SELECT value'
			. ' FROM #__flexicontent_fields_item_relations'
			. ' WHERE field_id = '. (int) $field->id .' AND value<>"" ';
		$this->_db->setQuery($query);
		$values = $this->_db->loadColumn();

		// Create original filenames array skipping any empty records
		$filenames = array();
		foreach ( $values as $value )
		{
			if ( empty($value) ) continue;
			$value = @ unserialize($value);

			if ( empty($value['originalname']) ) continue;
			$filenames[$value['originalname']] = 1;
		}
		$filenames = array_keys($filenames);

		// Eliminate records that have no original files
		$existing_files = array();
		foreach($filenames as $filename)
		{
			if (!$filename) continue;  // Skip empty values
			if (file_exists($securepath . $filename))
			{
				$existing_files[$this->_db->Quote($filename)] = 1;
			}
		}
		$filenames = $existing_files;

		if (!$filenames) return '';  // No files found

		$query = 'SELECT id'
			. ' FROM #__flexicontent_files'
			. ' WHERE '
			. '  filename IN ('.implode(',', array_keys($filenames)).')'
			. ($target_dir != 2 ? '  AND secure = '. (int)$target_dir : '');
		$this->_db->setQuery($query);
		$file_ids = $this->_db->loadColumn();

		// Also include files uploaded during current session for current field / item pair
		if ($field->item_id)
		{
			$upload_context = 'fc_upload_history.item_' . $field->item_id . '_field_' . $field->id;
			$session_files = JFactory::getSession()->get($upload_context, array());

			$new_file_ids = isset($session_files['ids'])
				? $session_files['ids']
				: array();

			$file_ids = array_merge($file_ids, $new_file_ids);
		}

		return !$file_ids ? '' : ' f.id IN ('.implode(', ', $file_ids).')';
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
			$this->_users = $query === false
				? array()
				: $this->_getList($query);
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
		// Some fields may not be using DB, create a limitation for them
		switch($field_type) {
			case 'image':
				$query = "SELECT id FROM #__flexicontent_fields WHERE field_type='image' AND attribs NOT LIKE '%image_source=1%'";
				$this->_db->setQuery($query);
				$field_ids = $this->_db->loadColumn();
				break;
			
			default:
				$field_ids = array();
				break;
		}
		return $field_ids;
	}
	
	
	/**
	 * Method to get items using files VIA (single property) field types that store file ids !
	 *
	 * @access public
	 * @return object
	 */
	function getItemsSingleprop( $field_types=array('file','minigallery'), $file_ids=array(), $count_items=false, $ignored=false )
	{
		$app    = JFactory::getApplication();
		$user   = JFactory::getUser();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');
		
		$filter_uploader  = $app->getUserStateFromRequest( $option.'.'.$this->viewid.'.filter_uploader',  'filter_uploader',  0,   'int' );
		
		$field_type_list = $this->_db->Quote( implode( "','", $field_types ), $escape=false );
		
		$where = array();
		
		$file_ids_list = '';
		if ( count($file_ids) ) {
			$file_ids_list = ' AND f.id IN (' . "'". implode("','", $file_ids)  ."')";
		} else {
			$permission = FlexicontentHelperPerm::getPerm();
			$CanViewAllFiles = $permission->CanViewAllFiles;
			
			if ( !$CanViewAllFiles ) {
				$where[] = ' f.uploaded_by = ' . (int)$user->id;
			} else if ( $filter_uploader ) {
				$where[] = ' f.uploaded_by = ' . $filter_uploader;
			}
		}
		
		if ( isset($ignored['item_id']) ) {
			$where[] = ' i.id!='. (int)$ignored['item_id'];
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

		$items = array();
		if ($_item_data) foreach ($_item_data as $item)
		{
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
	function getItemsMultiprop( $field_props=array('image'=>'originalname'), $value_props=array('image'=>'filename') , $file_ids=array(), $count_items=false, $ignored=false )
	{
		$app    = JFactory::getApplication();
		$user   = JFactory::getUser();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');
		
		$filter_uploader  = $app->getUserStateFromRequest( $option.'.'.$this->viewid.'.filter_uploader',  'filter_uploader',  0,   'int' );
		
		$where = array();
		
		$file_ids_list = '';
		if ( count($file_ids) ) {
			$file_ids_list = ' AND f.id IN (' . "'". implode("','", $file_ids)  ."')";
		} else {
			$permission = FlexicontentHelperPerm::getPerm();
			$CanViewAllFiles = $permission->CanViewAllFiles;
			
			if ( !$CanViewAllFiles ) {
				$where[] = ' f.uploaded_by = ' . (int)$user->id;
			} else if ( $filter_uploader ) {
				$where[] = ' f.uploaded_by = ' . $filter_uploader;
			}
		}
		
		if ( isset($ignored['item_id']) ) {
			$where[] = ' i.id!='. (int)$ignored['item_id'];
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
			$like_str = $this->_db->escape( 'f.'.$value_prop, false );
			$like_str = sprintf( $format_str, $field_prop, $like_str );
			
			// File field relation sub query
			$query = 'SELECT '. ($count_items  ?  'f.id as file_id, COUNT(i.id) as item_count'  :  'i.id as id, i.title')
				. ' FROM #__content AS i'
				. ' JOIN #__flexicontent_fields_item_relations AS rel ON rel.item_id = i.id'
				. ' JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id AND fi.field_type IN ('. $this->_db->Quote( $field_type ) .')' . $field_ids_list
				. ' JOIN #__flexicontent_files AS f ON rel.value LIKE ' . $like_str . ' AND f.'.$value_prop.'<>""' . $file_ids_list
				//. ' JOIN #__users AS u ON u.id = f.uploaded_by'
				. $where
				. $groupby
				. $orderby
				;
			//echo nl2br( "\n".$query."\n");
			$this->_db->setQuery( $query );
			$_item_data = $this->_db->loadObjectList($count_items ? 'file_id' : 'id');

			if ($_item_data) foreach ($_item_data as $item)
			{
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


	/**
	 * Method to check if given files can not be deleted e.g. due to assignments or due to being a CORE record
	 *
	 * @access	public
	 * @return	boolean
	 * @since	1.5
	 */
	function candelete( $cid = array(), $ignored=false, $s_field_types=array('file', 'minigallery'),
		$m_field_props=array('image'=>'originalname'), $m_value_props=array('image'=>'filename'), $checkACL = false)
	{
		if ($checkACL)
		{
			die(__FUNCTION__ . '() $checkACL = true is NOT supported');
		}
		if ( !count($cid) )
		{
			return false;
		}

		$allowed_cid = $this->getDeletable($cid, $ignored, $s_field_types, $m_field_props, $m_value_props);
		return count($cid) == count($allowed_cid);
	}


	/**
	 * Method to check if given records can not be unpublished e.g. due to assignments or due to being a CORE record
	 *
	 * @access	public
	 * @return	boolean
	 * @since	1.5
	 */
	function canunpublish($cid = array())
	{
		if ($checkACL)
		{
			die(__FUNCTION__ . '() $checkACL = true is NOT supported');
		}
		if ( !count($cid) )
		{
			return false;
		}

		return true;
	}


	/**
	 * Method to check if given files have assignments for the given field types
	 *
	 * @access	public
	 * @return	boolean	True if at least 1 file has 1 or more assignments
	 * @since	2.0
	 */
	function getDeletable( $cid = array(), $ignored=false, $s_field_types=array('file', 'minigallery'),
		$m_field_props=array('image'=>'originalname'), $m_value_props=array('image'=>'filename')
	) {
		if ( !count($cid) ) return array();
		
		$items_counts_s = $this->getItemsSingleprop( $s_field_types,  $cid, $count_items=true, $ignored);
		$items_counts_m = $this->getItemsMultiprop ( $m_field_props, $m_value_props, $cid, $count_items=true, $ignored);
		//echo "<pre>";  print_r($items_counts_s);  print_r($items_counts_m);  exit;
		
		$allowed_cid = array();
		foreach ($cid as $file_id)
		{
			if ( @ $items_counts_s[$file_id] > 0 || @ $items_counts_m[$file_id] > 0) continue;
			$allowed_cid[] = $file_id;
		}
		return $allowed_cid;
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
		if ( !count($cid) ) return false;
		
		jimport('joomla.filesystem.path');
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

		$this->_db->setQuery($query)->execute();

		return true;
	}


	/**
	 * Method to count the field relations (assignments) of a file in a multi-property field
	 *
	 * @access	public
	 * @return	string $msg
	 * @since	1.
	 */
	function countFieldRelationsMultiProp(&$rows, $value_prop, $field_prop, $field_type)
	{
		if (!$rows || !count($rows)) return array();  // No file records to check
		
		// Some fields may not be using DB, create a limitation for them
		$field_ids = $this->getFieldsUsingDBmode($field_type);
		$field_ids_list = !$field_ids  ?  ""  :  " AND fi.id IN ('". implode("','", $field_ids) ."')";
		
		$format_str = 'CONCAT("%%","\"%s\";s:%%:%%\"",%s,"\"%%")';
		$items = array();
		
		foreach ($rows as $row) $row_ids[] = $row->id;
		$file_ids_list = "'". implode("','", $row_ids) . "'";
		
		// Serialized values are like : "__field_propname__";s:33:"__value__"
		$format_str = 'CONCAT("%%","\"%s\";s:%%:%%\"",%s,"\"%%")';
		
		// Create a matching condition for the value depending on given configuration (property name of the field, and value property of file: either id or filename or ...)
		$like_str = $this->_db->escape( 'f.'.$value_prop, false );
		$like_str = sprintf( $format_str, $field_prop, $like_str );
		
		$query	= 'SELECT f.id as id, COUNT(rel.item_id) as count, GROUP_CONCAT(DISTINCT rel.item_id SEPARATOR  ",") AS item_list'
				. ' FROM #__flexicontent_fields_item_relations AS rel'
				. ' JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id AND fi.field_type = ' . $this->_db->Quote($field_type) . $field_ids_list
				. ' JOIN #__flexicontent_files AS f ON rel.value LIKE ' . $like_str . ' AND f.'.$value_prop.'<>""'
				. ' WHERE f.id IN('. $file_ids_list .')'
				. ' GROUP BY f.id'
				;
		$this->_db->setQuery($query);
		$assigned_data = $this->_db->loadObjectList('id');

		foreach($rows as $row)
		{
			$row->{'assigned_'.$field_type} = isset($assigned_data[$row->id]) ? (int) $assigned_data[$row->id]->count : 0;
			if (isset($assigned_data[$row->id]) && $assigned_data[$row->id]->item_list)
			{
				$row->item_list[$field_type] = $assigned_data[$row->id]->item_list;
				if (isset($row->total_usage))
				{
					$item_ids = explode(',', $assigned_data[$row->id]->item_list);
					$row->total_usage += count($item_ids);
				}
			}
		}
	}
	
	
	/**
	 * Method to count the field relations (assignments) of a file in a single-property field that stores file ids !
	 *
	 * @access	public
	 * @return	string $msg
	 * @since	1.
	 */
	function countFieldRelationsSingleProp(&$rows, $field_type)
	{
		if ( !count($rows) ) return;
		
		foreach ($rows as $row)
		{
			$file_id_arr[] = $row->id;
		}
		$query	= 'SELECT f.id as file_id, COUNT(rel.item_id) as count, GROUP_CONCAT(DISTINCT rel.item_id SEPARATOR  ",") AS item_list'
				. ' FROM #__flexicontent_files AS f'
				. ' JOIN #__flexicontent_fields_item_relations AS rel ON f.id = rel.value'
				. ' JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id AND fi.field_type = ' . $this->_db->Quote($field_type)
				. ' WHERE f.id IN ('. implode( ',', $file_id_arr ) .')'
				. ' GROUP BY f.id'
				;
		$this->_db->setQuery($query);
		$assigned_data = $this->_db->loadObjectList('file_id');

		foreach ($rows as $row)
		{
			$row->{'assigned_'.$field_type} = isset($assigned_data[$row->id]) ? (int) $assigned_data[$row->id]->count : 0;
			if (isset($assigned_data[$row->id]) && $assigned_data[$row->id]->item_list)
			{
				$row->item_list[$field_type] = $assigned_data[$row->id]->item_list;
				if (isset($row->total_usage))
				{
					$item_ids = explode(',', $assigned_data[$row->id]->item_list);
					$row->total_usage += count($item_ids);
				}
			}
		}
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
		$user = JFactory::getUser();

		if (count( $cid ))
		{
			$cids = implode( ',', $cid );

			$query = 'UPDATE #__' . $this->records_dbtbl
				. ' SET published = ' . (int) $publish
				. ' WHERE id IN ('. $cids .')'
				. ' AND ( checked_out = 0 OR ( checked_out = ' . (int) $user->get('id'). ' ) )'
			;
			$this->_db->setQuery( $query );
			$this->_db->execute();
		}
		return $cid;
	}
	
	
	/**
	 * Method to set the access level of the Types
	 *
	 * @access	public
	 * @param integer id of the category
	 * @param integer access level
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	function saveaccess($id, $access)
	{
		$row = JTable::getInstance($this->records_jtable, $prefix='');

		$row->load( $id );
		$row->id = $id;
		$row->access = $access;

		if ( !$row->check() )
		{
			$this->setError($this->_db->getErrorMsg());
			return false;
		}

		if ( !$row->store() )
		{
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		return true;
	}


	/**
	 * Method to get ids of all files
	 *
	 * @access	public
	 * @return	boolean	integer array on success
	 * @since	1.0
	 */
	function getFileIds($skip_urls=true)
	{
		$query = 'SELECT id '
			.' FROM #__flexicontent_files'
			.($skip_urls ? ' WHERE url=0 ' : '')
			;
		$this->_db->setQuery($query);
		$file_ids = $this->_db->loadColumn();
		return $file_ids;
	}
}
