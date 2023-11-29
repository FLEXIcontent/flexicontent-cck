<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

jimport('joomla.filesystem.file');
jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.path');
use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Table\Table;

require_once('base/baselist.php');

/**
 * FLEXIcontent Component Filemanager Model
 *
 */
class FlexicontentModelFilemanager extends FCModelAdminList
{
	/**
	 * Record database table
	 *
	 * @var string
	 */
	var $records_dbtbl = 'flexicontent_files';

	/**
	 * Record jtable name
	 *
	 * @var string
	 */
	var $records_jtable = 'flexicontent_files';

	/**
	 * Column names
	 */
	var $state_col      = 'published';
	var $name_col       = 'filename';
	var $parent_col     = null;
	var $created_by_col = 'uploaded_by';

	/**
	 * (Default) Behaviour Flags
	 */
	protected $listViaAccess = false;
	protected $copyRelations = false;

	/**
	 * Search and ordering columns
	 */
	var $search_cols = array(
		'FLEXI_FILENAME' => 'filename',
		0 => 'filename_original',
		'FLEXI_FILE_DISPLAY_TITLE' => 'altname',
		'FLEXI_DESCRIPTION'=> 'description',
	);
	var $default_order     = 'a.uploaded';
	var $default_order_dir = 'DESC';

	/**
	 * List filters that are always applied
	 */
	var $hard_filters = array();

	/**
	 * Record rows
	 *
	 * @var array
	 */
	var $_data = null;
	var $_data_pending = null;

	/**
	 * Rows total
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
	 * uploaders
	 *
	 * @var object
	 */
	var $_users = null;


	/**
	 * Constructor
	 *
	 * @since 3.3.0
	 */
	public function __construct($config = array())
	{
		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$option = $jinput->getCmd('option', '');
		$view   = $jinput->getCmd('view', '');
		$layout = $jinput->getString('layout', 'default');
		$fcform = $jinput->getInt('fcform', 0);

		// Make session index more specific ... (if needed by this model)
		$this->fieldid = $jinput->getInt('field', null);
		$this->view_id = $view . '_' . $layout . ($this->fieldid ? '_' . $this->fieldid : '');

		// Call parent after setting ... $this->view_id
		parent::__construct($config);

		$p = $this->ovid;

		$this->sess_assignments = true;


		/**
		 * Load backend language file if model gets loaded in frontend
		 */
		if ($app->isClient('site'))
		{
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);
		}


		/**
		 * View's Filters
		 * Inherited filters : filter_state, filter_access, scope, search
		 */

		// Various filters
		$filter_lang     = $fcform ? $jinput->get('filter_lang', '', 'string') : $app->getUserStateFromRequest($p . 'filter_lang', 'filter_lang', '', 'string');
		$filter_uploader = $fcform ? $jinput->get('filter_uploader', 0, 'int') : $app->getUserStateFromRequest($p . 'filter_uploader', 'filter_uploader', 0, 'int');
		$filter_secure   = $fcform ? $jinput->get('filter_secure', '', 'alnum') : $app->getUserStateFromRequest($p . 'filter_secure', 'filter_secure', '', 'alnum');
		$filter_stamp    = $fcform ? $jinput->get('filter_stamp', '', 'alnum') : $app->getUserStateFromRequest($p . 'filter_stamp', 'filter_stamp', '', 'alnum');
		$filter_url      = $fcform ? $jinput->get('filter_url', '', 'alnum') : $app->getUserStateFromRequest($p . 'filter_url', 'filter_url', '', 'alnum');
		$filter_ext      = $fcform ? $jinput->get('filter_ext', '', 'alnum') : $app->getUserStateFromRequest($p . 'filter_ext', 'filter_ext', '', 'alnum');
		$filter_item     = $fcform ? $jinput->get('item_id', 0, 'int') : $app->getUserStateFromRequest($p . 'item_id', 'item_id', 0, 'int');

		$this->setState('filter_lang', $filter_lang);
		$this->setState('filter_uploader', $filter_uploader ?: '');
		$this->setState('filter_secure', $filter_secure);
		$this->setState('filter_stamp', $filter_stamp);
		$this->setState('filter_url', $filter_url);
		$this->setState('filter_ext', $filter_ext);
		$this->setState('filter_item', $filter_item ?: '');

		$app->setUserState($p . 'filter_lang', $filter_lang);
		$app->setUserState($p . 'filter_uploader', $filter_uploader ?: '');
		$app->setUserState($p . 'filter_secure', $filter_secure);
		$app->setUserState($p . 'filter_stamp', $filter_stamp);
		$app->setUserState($p . 'filter_url', $filter_url);
		$app->setUserState($p . 'filter_ext', $filter_ext);
		$app->setUserState($p . 'filter_item', $filter_item ?: '');

		// TODO add this filter to view-template files, as it is missing
		$filter_assigned = $fcform ? $jinput->get('filter_assigned', '', 'alnum') : $app->getUserStateFromRequest($p . 'filter_assigned', 'filter_assigned', '', 'alnum');
		$this->setState('filter_assigned', $filter_assigned);
		$app->setUserState($p . 'filter_assigned', $filter_assigned);

		// Manage view permission
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanFiles;
	}


	/**
	 * Method to set the record identifier (for singular operations) and clear record rows
	 *
	 * @param		int	    $id        record identifier
	 *
	 * @since	3.3.0
	 */
	public function setId($id)
	{
		// Set record id and wipe data, if setting a different ID
		if ($this->_id != $id)
		{
			$this->_id    = $id;
			$this->_data  = null;
			$this->_total = null;

			$this->_data_pending  = null;
			$this->_total_pending = null;
		}
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
			$this->_db->setQuery('SELECT FOUND_ROWS()');
			$this->_total_pending = $this->_db->loadResult();
		}

		return $this->_data_pending;
	}


	/**
	 * Method to get files data
	 *
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

		$s_assigned_fields = array('file', 'mediafile');
		$m_assigned_fields = array('image');

		$m_assigned_props = array('image'=>array('originalname', 'existingname'));
		$m_assigned_vals = array('image'=>array('filename', 'filename'));

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

			$filter_order = $this->getState('filter_order');
			$default_size_message = $filter_order != 'a.size' ? null : '
				<span class="hasTooltip" title="' . JText::sprintf('FLEXI_PLEASE_REINDEX_FILE_STATISTICS', JText::_('FLEXI_INDEX_FILE_STATISTICS')) . '">
					<span class="icon-warning"></span>
					<span class="icon-loop"></span>
				</span>';
			$this->_data = flexicontent_images::BuildIcons($this->_data, $default_size_message);


			/**
			 * Single property fields, get file usage (# assignments), if not already done by main query
			 */			
			if ( !$s_assigned_via_main && $s_assigned_fields)
			{
				foreach ($s_assigned_fields as $field_type)
				{
					$this->countFieldRelationsSingleProp($this->_data, $field_type);
				}
			}

			
			/**
			 * Multi property fields, get file usage (# assignments)
			 */
			if ($m_assigned_fields)
			{
				foreach ($m_assigned_fields as $field_type)
				{
					$field_prop_arr = $m_assigned_props[$field_type];
					$value_prop_arr = $m_assigned_vals[$field_type];
					$this->countFieldRelationsMultiProp($this->_data, $value_prop_arr, $field_prop_arr, $field_type);
				}
			}


			/**
			 * Files in download links created via the XTD-editor file button, get file usage (# of download links)
			 */
			$this->countUsage_FcFileBtn_DownloadLinks($this->_data);


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
	 * Method to get the total nr of the records
	 *
	 * @return integer
	 *
	 * @since	1.5
	 */
	public function getTotal()
	{
		// Lets load the 'pending' records of the view instead of the existing records list
		if ($this->_pending)
		{
			if ($this->_total_pending === null)
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

		// Lets load the records if it doesn't already exist
		elseif ($this->_total === null)
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


	/**
	 * Method to get a pagination object for the records
	 *
	 * @return object
	 *
	 * @since	1.5
	 */
	public function getPagination()
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

		$exts = $exts ?: $cparams->get('upload_extensions', 'bmp,wbmp,csv,doc,docx,webp,gif,ico,jpg,jpeg,odg,odp,ods,odt,pdf,png,ppt,pptx,txt,xcf,xls,xlsx,zip,ics');
		$imageexts = array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico');  // Common image extensions
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

			$row->size    = sprintf("%.0f KB", (filesize($filepath) / 1024) );
			$row->altname = $pinfo['filename'];

			$row->uploader = '-';
			$row->uploaded = date("F d Y H:i:s.", filectime($filepath) );

			$row->url = 0;
			$row->id  = $i;

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
			$gallery_path = (!empty($options['secure']) ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH) . DS;
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

			$app    = JFactory::getApplication();
			$jinput = $app->input;
			$user   = JFactory::getUser();
			$option = $jinput->get('option', '', 'cmd');
			$view   = $jinput->get('view', '', 'cmd');
			$p      = $this->ovid;

			$u_item_id = $itemid ?: $app->getUserStateFromRequest($p . 'u_item_id', 'u_item_id', 0, 'string');

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
	function _buildQuery($assigned_fields = array(), $ids_only = false, $u_item_id = 0)
	{
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

		$filter_item = $u_item_id ?: (int) $this->getState('filter_item');

		if ($filter_item)
		{
			$join	.= ' JOIN #__flexicontent_fields_item_relations AS rel ON rel.item_id = ' . (int) $filter_item . ' AND a.id = rel.value ';
			$join	.= ' JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id AND fi.field_type = ' . $this->_db->Quote('file');
		}

		if (!$ids_only)
		{
			$join .= ' LEFT JOIN #__viewlevels AS level ON level.id = a.access';
		}

		if ($ids_only)
		{
			$columns[] = 'a.id';
		}
		else
		{
			$columns[] = 'SQL_CALC_FOUND_ROWS a.*, '
				. ' u.name AS editor, '
				. ' ua.name AS uploader, '
				. ' mm.id AS mm_id, '
				. ' CASE WHEN a.filename_original<>"" THEN a.filename_original ELSE a.filename END AS filename_displayed ';

			foreach ($assigned_fields as $field_type)
			{
				// Field relation sub query for counting file assignment to this field type
				$assigned_query	= 'SELECT COUNT(value)'
					. ' FROM #__flexicontent_fields_item_relations AS rel'
					. ' JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id'
					. ' WHERE fi.field_type = ' . $this->_db->Quote($field_type)
					. ' AND value = a.id'
					;
				$columns[] = '('.$assigned_query.') AS assigned_'.$field_type;
			}

			$columns[] = 'CASE WHEN level.title IS NULL THEN CONCAT_WS(\'\', \'deleted:\', a.access) ELSE level.title END AS access_level';
		}

		$query = 'SELECT '. implode(', ', $columns)
			. ' FROM #__flexicontent_files AS a'
			. $join
			. $where
			//. ' GROUP BY a.id'
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
	function _buildContentOrderBy($q = false)
	{
		$filter_order     = $this->getState('filter_order');
		$filter_order_Dir = $this->getState('filter_order_Dir');

		if ($filter_order=='a.filename_displayed') $filter_order = ' CASE WHEN a.filename_original<>"" THEN a.filename_original ELSE a.filename END ';
		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_Dir.', a.filename';

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

		$orderby = $file_ids
			? ' ORDER BY FIELD(a.id, ' . implode(', ', ArrayHelper::toInteger($file_ids)) . ')'
			: '';
		return $orderby;
	}


	function _buildContentJoin()
	{
		$join = '';
		$join .= ' LEFT JOIN #__users AS u ON u.id = a.checked_out';
		$join .= ' JOIN #__users AS ua ON ua.id = a.uploaded_by';
		$join .= ' LEFT JOIN #__flexicontent_mediadatas AS mm ON mm.file_id = a.id';

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

		return $file_ids
			? ' WHERE a.id IN (' . implode(',', ArrayHelper::toInteger($file_ids)) . ')'
			: false;
	}


	/**
	 * Method to build the where clause of the query for the records
	 *
	 * @param		JDatabaseQuery|bool   $q   DB Query object or bool to indicate returning an array or rendering the clause
	 *
	 * @return  JDatabaseQuery|array
	 *
	 * @since   3.3.0
	 */
	protected function _buildContentWhere($q = true)
	{
		$table = $this->getTable($this->records_jtable, '');

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
			elseif (in_array($field->field_type, array('minigallery', 'mediafile')))
				$default_dir = 0;  // 'media' folder
		}
		$target_dir = $params->get('target_dir', $default_dir);

		// Handles special cases of fields, that have special rules for listing specific files only
		if ($field && $field->field_type =='image' && $params->get('image_source') == 0)
		{
			$limit_by_uploader = (int) $params->get('limit_by_uploader', 0);
			if ($params->get('list_all_media_files', 0))
			{
				$where[] = ' a.ext IN ("jpg","gif","png","jpeg") ';
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

		// Text search and search scope
		$scope  = $this->getState('scope');
		$search = $this->getState('search');
		$search = StringHelper::trim(StringHelper::strtolower($search));

		$filter_state     = $this->getState('filter_state');
		$filter_access    = $this->getState('filter_access');
		$filter_lang			= $this->getState('filter_lang');
		$filter_uploader  = $this->getState('filter_uploader');
		$filter_secure    = $this->getState('filter_secure');
		$filter_stamp     = $this->getState('filter_stamp');
		$filter_url       = $this->getState('filter_url');
		$filter_ext       = $this->getState('filter_ext');


		$permission = FlexicontentHelperPerm::getPerm();
		$CanViewAllFiles = $permission->CanViewAllFiles;

		// Filter by state
		if (property_exists($table, $this->state_col ?? ''))
		{
			switch ($filter_state)
			{
				case 'P':
					$where[] = 'a.' . $this->state_col . ' = 1';
					break;

				case 'U':
					$where[] = 'a.' . $this->state_col . ' = 0';
					break;

				case 'A':
					$where[] = 'a.' . $this->state_col . ' = 2';
					break;

				case 'T':
					$where[] = 'a.' . $this->state_col . ' = -2';
					break;

				default:
					// ALL: published & unpublished, but exclude archived, trashed
					if (!strlen($filter_state))
					{
						$where[] = 'a.' . $this->state_col . ' <> -2';
						$where[] = 'a.' . $this->state_col . ' <> 2';
					}
					elseif (is_numeric($filter_state))
					{
						$where[] = 'a.' . $this->state_col . ' = ' . (int) $filter_state;
					}
			}
		}

		// Filter by language
		if (strlen($filter_lang))
		{
			$where[] = 'a.language = ' . $this->_db->Quote($filter_lang);
		}

		/**
		 * Limit via parameter,
		 * 1: limit to current user as uploader,
		 * 0: list files from any uploader, and respect 'filter_uploader' URL variable
		 */

		// Limit to current user
		if ($limit_by_uploader || !$CanViewAllFiles)
		{
			$where[] = 'a.' . $this->created_by_col . ' = ' . (int) $user->id;
		}

		// Filter by uploader
		elseif (strlen($filter_uploader))
		{
			$where[] = 'a.' . $this->created_by_col . ' = ' . (int) $filter_uploader;
		}

		// Filter by access level
		if (property_exists($table, 'access'))
		{
			if (strlen($filter_access))
			{
				$where[] = 'a.access = ' . (int) $filter_access;
			}

			// Filter via View Level Access, if user is not super-admin
			if (!JFactory::getUser()->authorise('core.admin') && (JFactory::getApplication()->isClient('site') || $this->listViaAccess))
			{
				$groups  = implode(',', JAccess::getAuthorisedViewLevels($user->id));
				$where[] = 'a.access IN (' . $groups . ')';
			}
		}

		// Limit via parameter, 2: List any file and respect 'filter_secure' URL variable, 1: limit to secure, 0: limit to media
		if (strlen($target_dir) && $target_dir != 2)
		{
			$filter_secure = $target_dir ? 'S' : 'M';   // force secure / media
		}

		if ($filter_url === 'F')
		{
			$where[] = ' url = 0';
		}
		elseif ($filter_url === 'U')
		{
			$where[] = ' url = 1';
		}

		if ($filter_stamp === 'Y')
		{
			$where[] = ' stamp = 1';
		}
		elseif ($filter_stamp === 'N')
		{
			$where[] = ' stamp = 0';
		}

		if ($filter_secure === 'M')
		{
			$where[] = ' secure = 0';
		}
		elseif ($filter_secure === 'S')
		{
			$where[] = ' secure = 1';
		}

		if (strlen($filter_ext))
		{
			$where[] = ' ext = ' . $this->_db->Quote($filter_ext);
		}

		if (!empty($this->search_cols) && strlen($search))
		{
			if (stripos($search, 'id:') === 0)
			{
				$where[] = 'a.id = ' . (int) substr($search, 3);
			}
			elseif (stripos($search, 'author:') === 0)
			{
				$search_quoted = $this->_db->Quote('%' . $this->_db->escape(substr($search, 7), true) . '%');
				$where[] = '(ua.name LIKE ' . $search_quoted . ' OR ua.username LIKE ' . $search_quoted . ')';
			}
			else
			{
				$escaped_search = str_replace(' ', '%', $this->_db->escape(trim($search), true));
				$search_quoted  = $this->_db->Quote('%' . $escaped_search . '%', false);

				$table     = $this->getTable($this->records_jtable, '');
				$textwhere = array();
				$col_name  = str_replace('a.', '', $scope);

				if ($scope === '-1')
				{
					foreach ($this->search_cols as $search_col)
					{
						if (property_exists($table, $search_col))
						{
							$textwhere[] = 'LOWER(a.' . $search_col . ') LIKE ' . $search_quoted;
						}
					}
				}
				elseif ($scope === 'a.filename')
				{
					$textwhere[] = ' LOWER(a.filename) LIKE ' . $search_quoted;
					$textwhere[] = ' LOWER(a.filename_original) LIKE ' . $search_quoted;
				}
				elseif (in_array($col_name, $this->search_cols) && property_exists($table, $col_name))
				{
					// Scope is user input, was filtered as CMD but also use quoteName() on the column
					$textwhere[] = 'LOWER(' . $this->_db->quoteName($scope) . ') LIKE ' . $search_quoted;
				}
				else
				{
					JFactory::getApplication()->enqueueMessage('Text search scope ' . $scope . ' is unknown, search failed', 'warning');
				}

				if ($textwhere)
				{
					$where[] = '(' . implode(' OR ', $textwhere) . ')';
				}
			}
		}

		if ($q instanceof \JDatabaseQuery)
		{
			return $where ? $q->where($where) : $q;
		}

		return $q
			? ' WHERE ' . (count($where) ? implode(' AND ', $where) : ' 1 ')
			: $where;
	}


	/**
	 * Method to build the having clause of the query for the files
	 *
	 * @param		JDatabaseQuery|bool   $q   DB Query object or bool to indicate returning an array or rendering the clause
	 *
	 * @return  JDatabaseQuery|array
	 *
	 * @since 1.0
	 */
	protected function _buildContentHaving($q = true)
	{
		$filter_assigned = $this->getState('filter_assigned');

		$having = array();

		if ($filter_assigned === 'O')
		{
			$having[] = 'COUNT(rel.fileid) = 0';
		}
		elseif ($filter_assigned === 'A')
		{
			$having[] = 'COUNT(rel.fileid) > 0';
		}

		if ($q instanceof \JDatabaseQuery)
		{
			return $having ? $q->having($having) : $q;
		}

		return $q
			? ' HAVING ' . (count($having) ? implode(' AND ', $having) : ' 1 ')
			: $having;
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

		$query = 'SELECT ua.id, ua.name'
			. ' FROM #__flexicontent_files AS a'
			. ' LEFT JOIN #__users AS ua ON ua.id = a.uploaded_by'
			. $where
			. ' GROUP BY ua.id'
			. ' ORDER BY ua.name'
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
			. ' WHERE field_id = '. (int) $field->id .' AND value<>"" '
		;
		$values = $this->_db->setQuery($query)->loadColumn();

		// Create original filenames array skipping any empty records
		$filenames = array();
		foreach ( $values as $value )
		{
			if (empty($value))
			{
				continue;
			}

			$value = @ unserialize($value);

			if (!empty($value['originalname']))
			{
				$filenames[$value['originalname']] = 1;
			}
			elseif (!empty($value['existingname']))
			{
				$filenames[$value['existingname']] = 1;
			}
			else
			{
				continue;
			}
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
			. '  filename IN (' . implode(',', array_keys($filenames)) . ')'
			. ($target_dir != 2 ? '  AND secure = ' . (int) $target_dir : '')
		;
		$file_ids = $this->_db->setQuery($query)->loadColumn();

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

		return $file_ids
			? ' a.id IN (' . implode(', ', ArrayHelper::toInteger($file_ids)) . ')'
			: '';
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
				$query = $this->_db->getQuery(true)
					->select('id')
					->from('#__flexicontent_fields')
					->where('field_type = ' . $this->_db->Quote('image'))
					->where('attribs LIKE ' . $this->_db->Quote('%"image_source":"0"%'))
					;
				$field_ids = $this->_db->setQuery($query)->loadColumn();
				break;

			default:
				$field_ids = array();
				break;
		}
		return $field_ids;
	}


	/**
	 * Method to get usage for download links added to DB tables via Fcfile XTD button
	 *
	 * @access public
	 * @return object
	 */
	function getCustomLinksFileUsage($file_ids=array(), $count_items=false, $ignored=false)
	{
		$app    = JFactory::getApplication();
		$user   = JFactory::getUser();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');

		$filter_uploader = $this->getState('filter_uploader');

		$where = array();

		if ( count($file_ids) )
		{
			$file_ids_list = ' AND a.id IN (' . "'". implode("','", $file_ids)  ."')";
		}
		else
		{
			$file_ids_list = '';

			$permission = FlexicontentHelperPerm::getPerm();
			$CanViewAllFiles = $permission->CanViewAllFiles;

			if (!$CanViewAllFiles)
			{
				$where[] = ' a.uploaded_by = ' . (int) $user->id;
			}
			elseif ($filter_uploader)
			{
				$where[] = ' a.uploaded_by = ' . (int) $filter_uploader;
			}
		}

		if (!empty($ignored))
		{
			$where[] = ' (i.id NOT IN (' . implode(',', ArrayHelper::toInteger($ignored)) . ') AND i.context = \'com_content.article\')';
		}

		$where = count($where)
			? ' WHERE ' . implode(' AND ', $where)
			: '';

		// Group by since file maybe used in more than one fields or ? in more than one values for same field
		$groupby = !$count_items ? ' GROUP BY i.id'  :  ' GROUP BY a.id';
		$orderby = ''; //!$count_items ? ' ORDER BY i.title ASC'  :  '';

		// File field relation sub query
		$query = 'SELECT '. ($count_items  ?  'a.id as file_id, COUNT(i.id) as item_count'  :  'i.id as id, i.id as title')
			. ' FROM #__flexicontent_file_usage AS i'
			. ' JOIN #__flexicontent_files AS a ON a.id=i.file_id '. $file_ids_list
			//. ' JOIN #__users AS ua ON ua.id = a.uploaded_by'
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
			if ($count_items)
			{
				$items[$item->file_id] = isset($items[$item->file_id])
					? (int) $items[$item->file_id] + (int) $item->item_count
					: (int) $item->item_count;
			}
			else
			{
				$items[$item->title] = $item;
			}
		}

		//echo "<pre>"; print_r($items); exit;
		return $items;
	}


	/**
	 * Method to get items using files VIA (single property) field types that store file ids !
	 *
	 * @access public
	 * @return object
	 */
	function getItemsSingleprop( $field_types=array('file', 'mediafile'), $file_ids=array(), $count_items=false, $ignored=false )
	{
		$app    = JFactory::getApplication();
		$user   = JFactory::getUser();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');

		$filter_uploader = $this->getState('filter_uploader');

		$field_type_list = $this->_db->Quote( implode( "','", $field_types ), $escape=false );

		$where = array();

		if ( count($file_ids) )
		{
			$file_ids_list = ' AND a.id IN (' . "'". implode("','", $file_ids)  ."')";
		}
		else
		{
			$file_ids_list = '';

			$permission = FlexicontentHelperPerm::getPerm();
			$CanViewAllFiles = $permission->CanViewAllFiles;

			if (!$CanViewAllFiles)
			{
				$where[] = ' a.uploaded_by = ' . (int) $user->id;
			}
			elseif ($filter_uploader)
			{
				$where[] = ' a.uploaded_by = ' . (int) $filter_uploader;
			}
		}

		if (!empty($ignored))
		{
			$where[] = ' i.id NOT IN (' . implode(',', ArrayHelper::toInteger($ignored)) . ')';
		}

		$where = count($where)
			? ' WHERE ' . implode(' AND ', $where)
			: '';

		// Group by since file maybe used in more than one fields or ? in more than one values for same field
		$groupby = !$count_items ? ' GROUP BY i.id'  :  ' GROUP BY a.id';
		$orderby = !$count_items ? ' ORDER BY i.title ASC'  :  '';

		// File field relation sub query
		$query = 'SELECT '. ($count_items  ?  'a.id as file_id, COUNT(i.id) as item_count'  :  'i.id as id, i.title')
			. ' FROM #__content AS i'
			. ' JOIN #__flexicontent_fields_item_relations AS rel ON rel.item_id = i.id'
			. ' JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id AND fi.field_type IN ('. $field_type_list .')'
			. ' JOIN #__flexicontent_files AS a ON a.id=rel.value '. $file_ids_list
			//. ' JOIN #__users AS ua ON ua.id = a.uploaded_by'
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
			if ($count_items)
			{
				$items[$item->file_id] = isset($items[$item->file_id])
					? (int) $items[$item->file_id] + (int) $item->item_count
					: (int) $item->item_count;
			}
			else
			{
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
	function getItemsMultiprop( $field_props=array('image'=>array('originalname', 'existingname')), $value_props=array('image'=>array('filename', 'filename')) , $file_ids=array(), $count_items=false, $ignored=false )
	{
		$app    = JFactory::getApplication();
		$user   = JFactory::getUser();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');

		$filter_uploader = $this->getState('filter_uploader');

		$where = array();

		$file_ids_list = '';

		if (count($file_ids))
		{
			$file_ids_list = ' AND a.id IN (' . implode(',', ArrayHelper::toInteger($file_ids)) . ')';
		}
		else
		{
			$permission = FlexicontentHelperPerm::getPerm();
			$CanViewAllFiles = $permission->CanViewAllFiles;

			if (!$CanViewAllFiles)
			{
				$where[] = ' a.uploaded_by = ' . (int) $user->id;
			}
			elseif ($filter_uploader)
			{
				$where[] = ' a.uploaded_by = ' . (int) $filter_uploader;
			}
		}

		if (!empty($ignored))
		{
			$where[] = ' i.id NOT IN (' . implode(',', ArrayHelper::toInteger($ignored)) . ')';
		}

		$where = count($where)
			? ' WHERE ' . implode(' AND ', $where)
			: '';

		// Group by since file maybe used in more than one fields or ? in more than one values for same field
		$groupby = !$count_items ? ' GROUP BY i.id'  :  ' GROUP BY a.id';
		$orderby = !$count_items ? ' ORDER BY i.title ASC'  :  '';

		// Serialized values are like : "__field_propname__";s:33:"__value__"
		$format_str = 'CONCAT("%%","\"%s\";s:%%:%%\"",%s,"\"%%")';
		$items = array();
		$files = array();

		foreach ($field_props as $field_type => $field_prop_arr)
		{
			if (!is_array($field_prop_arr))
			{
				$field_prop_arr  = array($field_prop_arr);
			}

			if (!is_array($value_props[$field_type]))
			{
				$value_props[$field_type] = array($value_props[$field_type]);
			}

			foreach($field_prop_arr as $i => $field_prop)
			{
				// Some fields may not be using DB, create a limitation for them
				$field_ids = $this->getFieldsUsingDBmode($field_type);
				$field_ids_list = !$field_ids  ?  ""  :  " AND fi.id IN ('". implode("','", $field_ids) ."')";

				// Create a matching condition for the value depending on given configuration (property name of the field, and value property of file: either id or filename or ...)
				$value_prop = $value_props[$field_type][$i];
				$like_str = $this->_db->escape( 'a.'.$value_prop, false );
				$like_str = sprintf( $format_str, $field_prop, $like_str );

				// File field relation sub query
				$query = 'SELECT '. ($count_items  ?  'a.id as file_id, COUNT(i.id) as item_count'  :  'i.id as id, i.title')
					. ' FROM #__content AS i'
					. ' JOIN #__flexicontent_fields_item_relations AS rel ON rel.item_id = i.id'
					. ' JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id AND fi.field_type IN ('. $this->_db->Quote( $field_type ) .')' . $field_ids_list
					. ' JOIN #__flexicontent_files AS a ON rel.value LIKE ' . $like_str . ' AND a.'.$value_prop.'<>""' . $file_ids_list
					//. ' JOIN #__users AS ua ON ua.id = a.uploaded_by'
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
		}

		//echo "<pre>"; print_r($items); exit;
		return $items;
	}


	/**
	 * Method to check if given records can not be deleted due to assignments or due to permissions
	 *
	 * @param   array       $cid          array of record ids to check
	 * @param   array       $cid_noauth   (variable by reference), pass authorizing -ignored- IDs and return an array of non-authorized record ids
	 * @param   array       $cid_wassocs  (variable by reference), pass assignments -ignored- IDs and return an array of 'locked' record ids
	 *
	 * @return	boolean	  True when at least 1 deleteable record found
	 *
	 * @since   3.3.0
	 */
	public function candelete($cid, & $cid_noauth = null, & $cid_wassocs = null)
	{
		$authorizing_ignored = $cid_noauth ? array_flip($cid_noauth) : array();
		$assignments_ignored = $cid_wassocs ? array_flip($cid_wassocs) : array();

		$cid_noauth  = array();
		$cid_wassocs = array();

		if (!count($cid))
		{
			return false;
		}

		$s_field_types = array('file', 'mediafile');
		$m_field_props = array('image' => array('originalname', 'existingname'));
		$m_value_props = array('image' => array('filename', 'filename'));

		$allowed_cid = $this->getDeletable($cid, $authorizing_ignored, $s_field_types, $m_field_props, $m_value_props);

		$r_allowed_cid = array_flip($allowed_cid);

		foreach($cid as $id)
		{
			if (!isset($r_allowed_cid[$id]))
			{
				$cid_noauth[] = $id;
			}
		}

		return !count($cid_noauth) && !count($cid_wassocs);
	}


	/**
	 * Method to check if given records can not be unpublished due to assignments or due to permissions
	 *
	 * @param		array			$cid          array of record ids to check
	 * @param		array			$cid_noauth   (variable by reference), pass authorizing -ignored- IDs and return an array of non-authorized record ids
	 * @param		array			$cid_wassocs  (variable by reference), pass assignments -ignored- IDs and return an array of 'locked' record ids
	 *
	 * @return	boolean	  True when at least 1 publishable record found
	 *
	 * @since   3.3.0
	 */
	public function canunpublish($cid, & $cid_noauth = null, & $cid_wassocs = null)
	{
		if ($checkACL)
		{
			die(__FUNCTION__ . '() $checkACL = true is NOT supported');
		}

		if (!count($cid))
		{
			return false;
		}

		return true;
	}


	/**
	 * Method to check if given files have assignments for the given field types
	 *
	 * @param		array			$cid            array of record ids to check
	 * @param		array			$ignored        array of record ids to ignore during checks
	 * @param		array			$s_field_types  array of single property field types that stored file IDs
	 * @param		array			$m_field_types  array of field value properties storing file references (indexed by field types)
	 * @param		array			$m_value_props  array of file properties to compare the given field value properties (indexed by field types)
	 *
	 * @return	array     An array of file IDs that can be safely deleted
	 *
	 * @since	2.0
	 */
	function getDeletable($cid = array(), $ignored = false,
		$s_field_types = array('file', 'mediafile'),
		$m_field_props = array('image' => array('originalname', 'existingname')),
		$m_value_props = array('image' => array('filename', 'filename'))
	) {

		if (!count($cid))
		{
			return array();
		}

		$items_counts_s = $this->getItemsSingleprop($s_field_types,  $cid, $count_items = true, $ignored);
		$items_counts_m = $this->getItemsMultiprop($m_field_props, $m_value_props, $cid, $count_items = true, $ignored);
		$items_counts_u = $this->getCustomLinksFileUsage($cid, $count_items = true, $ignored);
		//echo "<pre>";  print_r($items_counts_s);  print_r($items_counts_m);  print_r($items_counts_u);  exit;

		$allowed_cid = array();

		foreach ($cid as $file_id)
		{
			if (!empty($items_counts_s[$file_id]) || !empty($items_counts_m[$file_id]) || !empty($items_counts_u[$file_id]))
			{
				continue;
			}

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
	public function delete($cid, $model = null)
	{
		if ( !count( $cid ) ) return false;

		jimport('joomla.filesystem.path');
		jimport('joomla.filesystem.file');

		// This is already done by controller task / caller but redo
		$cid = ArrayHelper::toInteger($cid);
		$cid_list = implode(',', $cid);

		$query = 'SELECT a.filename, a.url, a.secure'
				. ' FROM #__flexicontent_files AS a'
				. ' WHERE a.id IN ('. $cid_list .')';

		$files = $this->_db->setQuery($query)->loadObjectList();

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


		/**
		 * Get records using the model, we need an array of records to use for calling the events
		 */
		$record_arr = array();

		if ($model && $this->event_context && ($this->event_before_delete || $this->event_after_delete))
		{
			$app = JFactory::getApplication();
			$dispatcher = JEventDispatcher::getInstance();

			// Load all content and flexicontent plugins for triggering their delete events
			if ($this->event_context === 'com_content.article')
			{
				JPluginHelper::importPlugin('content');
			}
			JPluginHelper::importPlugin('flexicontent');

			foreach ($cid as $record_id)
			{
				$record = $model->getRecord($record_id);
				$record_arr[] = $record;
			}
		}

		/**
		 * Trigger onBeforeDelete event(s)
		 */
		if ($this->event_before_delete) foreach($record_arr as $record)
		{
			// Trigger Event 'event_before_delete' e.g. 'onContentBeforeDelete' of flexicontent or content plugins
			FLEXI_J40GE
				? $app->triggerEvent($this->event_before_delete, array($this->event_context, $record))
				: $dispatcher->trigger($this->event_before_delete, array($this->event_context, $record));
		}


		$query = 'DELETE FROM #__flexicontent_files'
		. ' WHERE id IN ('. $cid_list .')';

		$this->_db->setQuery($query)->execute();

		/**
		 * Trigger onAfterDelete event
		 */
		if ($this->event_after_delete) foreach($record_arr as $record)
		{
			// Trigger Event 'event_before_delete' e.g. 'onContentAfterDelete' of flexicontent or content plugins
			FLEXI_J40GE
				? $app->triggerEvent($this->event_after_delete, array($this->event_context, $record))
				: $dispatcher->trigger($this->event_after_delete, array($this->event_context, $record));
		}

		return true;
	}


	/**
	 * Method to count the field relations (assignments) of a file in a multi-property field
	 *
	 * @access	public
	 * @return	string $msg
	 * @since	1.
	 */
	function countFieldRelationsMultiProp(&$rows, $value_prop_arr, $field_prop_arr, $field_type)
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

		if (!is_array($field_prop_arr)) $field_prop_arr = array($field_prop_arr);
		if (!is_array($value_prop_arr)) $value_prop_arr = array($value_prop_arr);

		foreach($field_prop_arr as $i => $field_prop)
		{
			$value_prop = $value_prop_arr[$i];

			// Create a matching condition for the value depending on given configuration (property name of the field, and value property of file: either id or filename or ...)
			$like_str = $this->_db->escape( 'a.'.$value_prop, false );
			$like_str = sprintf( $format_str, $field_prop, $like_str );
			
			$query	= 'SELECT a.id as id, COUNT(rel.item_id) as count, GROUP_CONCAT(DISTINCT rel.item_id SEPARATOR  ",") AS item_list'
					. ' FROM #__flexicontent_fields_item_relations AS rel'
					. ' JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id AND fi.field_type = ' . $this->_db->Quote($field_type) . $field_ids_list
					. ' JOIN #__flexicontent_files AS a ON rel.value LIKE ' . $like_str . ' AND a.'.$value_prop.'<>""'
					. ' WHERE a.id IN(' . $file_ids_list . ')'
					. ' GROUP BY a.id'
					;
			$this->_db->setQuery($query);
			$assigned_data = $this->_db->loadObjectList('id');

			foreach($rows as $row)
			{
				$row->{'assigned_'.$field_type} = isset($assigned_data[$row->id]) ? (int) $assigned_data[$row->id]->count : 0;
				if (isset($assigned_data[$row->id]) && $assigned_data[$row->id]->item_list)
				{
					$row->item_list[$field_type] = $assigned_data[$row->id]->item_list;
					$row->total_usage = isset($row->total_usage) ? $row->total_usage : 0;

					$item_ids = explode(',', $assigned_data[$row->id]->item_list);
					$row->total_usage += count($item_ids);
				}
			}
		}
	}



	/**
	 * Method to count the usage of files in download links created via the XTD-editor file button
	 *
	 * @access	public
	 * @return	int
	 * @since	  4.1
	 */
	function countUsage_FcFileBtn_DownloadLinks(&$rows)
	{
		if ( !count($rows) ) return;

		foreach ($rows as $row)
		{
			$file_id_arr[] = $row->id;
		}
		$query	= 'SELECT a.id as file_id, COUNT(a.id) as count'
				. ' FROM #__flexicontent_files AS a'
				. ' JOIN #__flexicontent_file_usage AS u ON u.file_id = a.id'
				. ' WHERE a.id IN (' . implode(',', $file_id_arr) . ')'
				. ' GROUP BY a.id'
				;
		$assigned_data = $this->_db->setQuery($query)->loadObjectList('file_id');

		foreach ($rows as $row)
		{
			$row->total_usage = isset($row->total_usage) ? $row->total_usage : 0;
			$download_links_count = isset($assigned_data[$row->id]) ? (int) $assigned_data[$row->id]->count : 0;
			$row->total_usage += $download_links_count;
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
		$query	= 'SELECT a.id as file_id, COUNT(rel.item_id) as count, GROUP_CONCAT(DISTINCT rel.item_id SEPARATOR  ",") AS item_list'
				. ' FROM #__flexicontent_files AS a'
				. ' JOIN #__flexicontent_fields_item_relations AS rel ON a.id = rel.value'
				. ' JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id AND fi.field_type = ' . $this->_db->Quote($field_type)
				. ' WHERE a.id IN (' . implode(',', $file_id_arr) . ')'
				. ' GROUP BY a.id'
				;
		$this->_db->setQuery($query);
		$assigned_data = $this->_db->loadObjectList('file_id');

		foreach ($rows as $row)
		{
			$row->{'assigned_'.$field_type} = isset($assigned_data[$row->id]) ? (int) $assigned_data[$row->id]->count : 0;
			if (isset($assigned_data[$row->id]) && $assigned_data[$row->id]->item_list)
			{
				$row->item_list[$field_type] = $assigned_data[$row->id]->item_list;
				$row->total_usage = isset($row->total_usage) ? $row->total_usage : 0;

				$item_ids = explode(',', $assigned_data[$row->id]->item_list);
				$row->total_usage += count($item_ids);
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
			// This is already done by controller task / caller but redo
			$cid = ArrayHelper::toInteger($cid);
			$cid_list = implode(',', $cid);

			$query = 'UPDATE #__' . $this->records_dbtbl
				. ' SET published = ' . (int) $publish
				. ' WHERE id IN ('. $cid_list .')'
				. ' AND ( checked_out = 0 OR checked_out IS NULL OR ( checked_out = ' . (int) $user->get('id'). ' ) )'
			;
			$this->_db->setQuery( $query );
			$this->_db->execute();
		}
		return $cid;
	}


	/**
	 * Method to get ids of all files
	 *
	 * @access	public
	 * @return	boolean	integer array on success
	 * @since	1.0
	 */
	function getFileIds($skip_urls=true, &$total=null, $start=0, $limit=5000)
	{
		$query = 'SELECT SQL_CALC_FOUND_ROWS id '
			. ' FROM #__flexicontent_files'
			. ($skip_urls ? ' WHERE url = 0 ' : '')
			. ($limit ? ' LIMIT ' . (int) $start . ', ' . (int) $limit : '')
			;
		$file_ids = $this->_db->setQuery($query)->loadColumn();

		// Get items total
		$total = $this->_db->setQuery('SELECT FOUND_ROWS()')->loadResult();

		return $file_ids;
	}


	/**
	 * Method to get ids of all files
	 *
	 * @access	public
	 * @return	boolean	integer array on success
	 * @since	1.0
	 */
	public function areAssignedToField($file_ids, $field_id)
	{
		$query = 'SELECT DISTINCT value '
			. ' FROM #__flexicontent_fields_item_relations '
			. ' WHERE field_id =' . (int) $field_id . ' AND value IN (' . implode(',', ArrayHelper::toInteger($file_ids)) . ')'
			;
		$values = $this->_db->setQuery($query)->loadColumn();

		return array_flip($values);
	}


	/**
	 * 1. Create a smaller audio file for preview playback
	 * 2. Precalculate the audio waveform peaks to avoid download the full file before preview playback starts
	 */
	public function createAudioPreview($field, $file, $logger = null)
	{
		// Setup Logger if not registered already done
		if (!$logger)
		{
			$this->_setUpLogger($logger);
		}

		$disabled_funcs = [];
		if (FLEXIUtilities::funcIsDisabled('exec')) $disabled_funcs[] = "exec";

		if ($disabled_funcs)
		{
			$error_mssg = "Cannot create audio preview file. Function(s): " . implode(', ', $disabled_funcs) . " are disabled. \n";
			$this->setError($error_mssg);
			JLog::add($error_mssg, JLog::ERROR, $logger->namespace);
			return false;
		}

		// Absolute file paths (or URLs ?)
		$full_path     = $file->full_path;
		$full_path_prw = isset($file->full_path_prw) ? $file->full_path_prw : '';

		$full_path     = str_replace('\\', '/', $full_path);
		$full_path_prw = str_replace('\\', '/', $full_path_prw);

		// Get the extension to record it in the DB
		$ext = strtolower(flexicontent_upload::getExt($full_path));

		$create_preview     = (int) $field->parameters->get('mm_create_preview', 1);
		$preview_bitrate    = (int) $field->parameters->get('mm_preview_bitrate', 96);
		$wf_zoom            = (int) $field->parameters->get('mm_wf_zoom', 256);


		/**
		 * Get and check file path of ffmpeg and audiowaveform
		 */
		$ffmpeg_path        = $field->parameters->get('mm_ffmpeg_path', '/usr/bin/ffmpeg');
		$audiowaveform_path = $field->parameters->get('mm_audiowaveform_path', '/usr/bin/audiowaveform');

		if ($ffmpeg_path && !file_exists($ffmpeg_path))
		{
			$error_mssg = $file->filename . ' : Failed to open ffmpeg path: ' . $ffmpeg_path;
			$this->setError($error_mssg);
			JLog::add($error_mssg, JLog::ERROR, $logger->namespace);
			$ffmpeg_path = '';
		}

		if ($audiowaveform_path && !file_exists($audiowaveform_path))
		{
			$error_mssg = $file->filename . ' : Failed to open audiowaveform path: ' . $audiowaveform_path;
			$this->setError($error_mssg);
			JLog::add($error_mssg, JLog::ERROR, $logger->namespace);
			$audiowaveform_path = '';
		}

		if ($file->mediaData->duration > 30)
		{
			$wf_zoom = (int) ($wf_zoom * ($file->mediaData->duration / 30));
		}

		if ($create_preview && $ffmpeg_path && in_array($ext, array('wav', 'mp3', 'aiff', 'mp4', 'mpg', 'mpeg', 'avi')))
		{
			$prv_path = $full_path_prw ? dirname($full_path_prw) : dirname($full_path) . '/audio_preview';
			$filename = str_ireplace('.' . $ext, '', basename($full_path));

			// Check preview folder
			if (!JFolder::exists($prv_path) && !JFolder::create($prv_path))
			{
				$error_mssg = $file->filename . ' : Failed to create preview folder: ' . $prv_path;
				$this->setError($error_mssg);
				JLog::add($error_mssg, JLog::ERROR, $logger->namespace);
				return false;
			}

			// Create audio preview file
			if (!$full_path_prw)
			{
				if ($file->url == 1)
				{
					return false;
					$cmd = 'wget -O - ' . escapeshellarg($file->filename) . ' | ' .
						$ffmpeg_path . " -codec:a libmp3lame -b:a " . $preview_bitrate . "k \"" . $prv_path . '/' . $filename . ".mp3\"";

					$full_path_prw =  $prv_path . '/' . $filename . ".mp3";
				}
				else
				{
					$cmd = $ffmpeg_path . " -i \"" . $full_path . "\" -codec:a libmp3lame -b:a " . $preview_bitrate . "k \"" . $prv_path . '/' . $filename . ".mp3\"";
				}
				exec($cmd);
			}

			// Create waveform peaks of audio preview file
			if ($audiowaveform_path)
			{
				/*if (!$full_path_prw && $file->url == 1)
				{
					$cmd = 'wget -O - ' . escapeshellarg($file->filename) . ' | ' .
						$audiowaveform_path . ' -b 8 -input-format ' . $ext .
						" -o \"" . $prv_path . '/' . $filename . ".json\"" .
						($wf_zoom ? ' --zoom ' . $wf_zoom : '')
						;
				}
				else
				{*/
					$cmd = $audiowaveform_path . " -b 8 " .
						" -i \"" . $prv_path . '/' . $filename . ".mp3\"" .
						" -o \"" . $prv_path . '/' . $filename . ".json\"" .
						($wf_zoom ? ' --zoom ' . $wf_zoom : '')
						;
				//}
				exec($cmd);

				JLog::add($file->filename . "\nCreating waveform peaks (JSON file):\n" . str_replace(JPATH_ROOT, '', $cmd) . "\n", JLog::INFO, $logger->namespace);
			}
		}

		return true;
	}

	/**
	 * Proobe using ffprobe the media data properties (duration, codec, etc) of the media file and store it in the database
	 */
	public function getDbMediaData($file)
	{
		$query = $this->_db->getQuery(true)
			->select('*')
			->from('#__flexicontent_mediadatas')
			->where('file_id = ' . (int) $file->id)
			;
		return $this->_db->setQuery($query)->loadOBject();
	}

	/**
	 * Proobe using ffprobe the media data properties (duration, codec, etc) of the media file and store it in the database
	 */
	public function createMediaData($field, $file, $logger = null)
	{
		// Setup Logger if not registered already done
		if (!$logger)
		{
			$this->_setUpLogger($logger);
		}

		// Absolute file path (or URL ?)
		$full_path = $file->full_path;

		// Get the extension to record it in the DB
		$ext = strtolower(flexicontent_upload::getExt($full_path));


		/**
		 * Get and check file path of ffprobe
		 */
		$ffprobe_path = $field->parameters->get('mm_ffprobe_path', '');

		if ($ffprobe_path && !file_exists($ffprobe_path))
		{
			$error_mssg = $file->filename . ' : Failed to open ffprobe path: ' . $ffprobe_path;
			$this->setError($error_mssg);
			JLog::add($error_mssg, JLog::ERROR, $logger->namespace);
			$ffprobe_path = '';
		}


		/**
		 * Create audio preview file
		 */
		if ($ffprobe_path && in_array($ext, array('wav', 'mp3', 'aiff', 'mp4', 'mpg', 'mpeg', 'avi')))
		{
			$disabled_funcs = [];
			if (FLEXIUtilities::funcIsDisabled('escapeshellarg')) $disabled_funcs[] = "escapeshellarg";
			if (FLEXIUtilities::funcIsDisabled('shell_exec')) $disabled_funcs[] = "shell_exec";

			if ($disabled_funcs)
			{
				$error_mssg = "Cannot detect audio properties. Function(s): " . implode(', ', $disabled_funcs) . " are disabled. \n";
				$this->setError($error_mssg);
				JLog::add($error_mssg, JLog::ERROR, $logger->namespace);
				return false;
			}

			// Default options
			$options = '-loglevel quiet -show_format -show_streams -print_format json';
			//$options .= ' -pretty';

			// Avoid escapeshellarg() issues with UTF-8 filenames
			setlocale(LC_CTYPE, 'en_US.UTF-8');

			// Run the ffprobe, save the JSON output then decode
			//ffprobe -v error -show_format -show_streams input.mp4
			$json_cmd  = sprintf($ffprobe_path.' %s %s', $options, escapeshellarg($full_path));
			$json_data = shell_exec($json_cmd);
			$json = json_decode($json_data);

			if (!isset($json->format))
			{
				$error_mssg = "Unsupported file type. Cannot detect audio properties.\nOR bad output";
				$this->setError($error_mssg);
				JLog::add($error_mssg	. "\nCommand: " . $json_cmd . "\nCommand output is:\n". $json_data, JLog::ERROR, $logger->namespace);
				return false;
			}
			else
			{
				//  `media_type` int(11) NOT NULL default 0, /* 0: audio , 1: video */
				//  `media_format` varchar(255) NULL, /* e.g 'video', 'wav', 'audio' */
				//  `codec_type` varchar(255) NULL, /* e.g 'audio' */
				//  `codec_name` varchar(255) NULL, /* e.g 'mp3', 'pcm_s24le' */
				//  `codec_long_name` varchar(255) NULL, /* e.g 'PCM signed 24-bit little-endian' , 'MP3 (MPEG audio layer 3)' */
				//  `resolution` varchar(255) NULL, /* e.g. 1280x720, 1920x1080 */
				//  `fps` double NULL, /* e.g. 50 (frames per second) */
				//  `bit_rate` int(11) NULL, /* e.g. 256000 , 320000 (bps) */
				//  `bits_per_sample` int(11) NULL, /* e.g. 16, 24, 32 (# bits) */
				//  `sample_rate` int(11) NULL, /* e.g. 44100 (HZ) */
				//  `duration` int(11) NOT NULL, /* e.g. 410 (seconds) */
				//  `channels` varchar(255) NULL, /* e.g. 1, 2, 4 (number of channels) */
				//  `channel_layout` varchar(255) NULL, /* e.g. 'stereo', 'mono' */

				$md_obj = new stdClass;
				$md_obj->id              = 0;
				$md_obj->file_id         = $file->id;
				$md_obj->state           = 1;

				$md_obj->media_type      = $json->streams[0]->codec_type === 'video' ? 1 : 0; //media_type, 0: audio , 1: video
				$md_obj->media_format    = $json->streams[0]->codec_type === 'video' ? 'video' : ($json->streams[0]->bits_per_sample ? 'wav' : 'mp3');
				$md_obj->media_format    = in_array($json->streams[0]->codec_name, array('pcm_s16be', 'pcm_s24be')) ? 'aiff' : $md_obj->media_format;

				$md_obj->codec_type      = $json->streams[0]->codec_type;
				$md_obj->codec_name      = $json->streams[0]->codec_name;
				$md_obj->codec_long_name = $json->streams[0]->codec_long_name;

				$md_obj->duration        = isset($json->streams[0]->duration)        ? ceil($json->streams[0]->duration) : 0;
				$md_obj->resolution      = 0;    // TODO
				$md_obj->fps             = 0;    // TODO

				$md_obj->bit_rate        = isset($json->streams[0]->bit_rate)        ? $json->streams[0]->bit_rate : 0;
				$md_obj->bits_per_sample = isset($json->streams[0]->bits_per_sample) ? $json->streams[0]->bits_per_sample : 0;
				$md_obj->sample_rate     = isset($json->streams[0]->sample_rate)     ? $json->streams[0]->sample_rate : 0;

				$md_obj->channels        = isset($json->streams[0]->channels)        ? $json->streams[0]->channels : 0;
				$md_obj->channel_layout  = isset($json->streams[0]->channel_layout)  ? $json->streams[0]->channel_layout : '';

				// Insert file record in DB
				$this->_db->insertObject('#__flexicontent_mediadatas', $md_obj);

				// Get id of new file record
				$md_obj->id = (int) $this->_db->insertid();

				// Reference the media data object, maybe useful during preview file creation
				$file->mediaData = $md_obj;

				//print_r($json, true);
				$logger->detailed_log
					? JLog::add($full_path . "\n" . print_r($json->streams[0], true), JLog::INFO, $logger->namespace)
					: JLog::add($full_path . "\n" .
						'media_type: ' . $md_obj->media_type . ', media_format: ' . $md_obj->media_format . ', channels: ' . $md_obj->channels . ', channel_layout: ' . $md_obj->channel_layout . "\n" .
						'codec_name: ' . $md_obj->codec_name . ', codec_name: ' . $md_obj->codec_name . ', codec_long_name: ' . $md_obj->codec_long_name . "\n" .
						'duration: ' . $md_obj->duration . ', resolution: ' . $md_obj->resolution . ', fps: ' . $md_obj->fps . "\n" .
						'bit_rate: ' . $md_obj->bit_rate . ', bits_per_sample: ' . $md_obj->bits_per_sample . ', sample_rate: ' . $md_obj->sample_rate  . "\n"
					, JLog::INFO, $logger->namespace);
			}
		}

		return true;
	}


	/**
	 * Register a Logger [filename, namespace, format]
	 */
	private function _setUpLogger(&$logger = null)
	{
		static $_names = array();

		if (!$logger)
		{
			$logger = (object) array(
				'namespace' => 'com_flexicontent.filemanager.uploader',
				'filename' => 'mediadata_log.php',
				'detailed_log' => false,
			);
		}

		if (!isset($_names[$logger->namespace]))
		{
			$_names[$logger->namespace] = 1;
		}
		else
		{
			return;
		}

		jimport('joomla.log.log');
		JLog::addLogger(
			array(
				'text_file' => $logger->filename,  // Sets the target log file
				'text_entry_format' => '{DATE} {TIME} {PRIORITY} {MESSAGE}'  // Sets the format of each line
			),
			JLog::ALL,  // Sets messages of all log levels to be sent to the file
			array($logger->namespace)  // category of logged messages
		);
	}
}
