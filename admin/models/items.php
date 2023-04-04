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

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Table\Table;

require_once('base/baselist.php');

/**
 * FLEXIcontent Component Items Model
 *
 */
class FlexicontentModelItems extends FCModelAdminList
{
	/**
	 * Record database table
	 *
	 * @var string
	 */
	var $records_dbtbl = 'content';

	/**
	 * Record jtable name
	 *
	 * @var string
	 */
	var $records_jtable = 'flexicontent_items';

	/**
	 * Column names
	 */
	var $state_col      = 'state';
	var $name_col       = 'title';
	var $parent_col     = 'catid';
	var $created_by_col = 'created_by';

	/**
	 * (Default) Behaviour Flags
	 */
	protected $listViaAccess = false;
	protected $copyRelations = false;

	/**
	 * Events
	 *
	 * @var string
	 */
	var $event_context = 'com_content.article';
	var $event_before_delete = 'onContentBeforeDelete';
	var $event_after_delete = 'onContentAfterDelete';

	/**
	 * Search and ordering columns
	 */
	var $search_cols = array(
		'FLEXI_TITLE' => 'title',
		'FLEXI_ALIAS' => 'alias',
		'FLEXI_NOTES' => 'note',
	);
	var $default_order     = 'a.id';
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

	/**
	 * Rows total
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
	 * Extra field columns to display for every listed item
	 *
	 * @var array
	 */
	var $_extra_cols = null;

	/**
	 * Extra (custom) filters to display
	 *
	 * @var array
	 */
	var $_custom_filters = null;

	/**
	 * Category data of listed items
	 *
	 * @var array
	 */
	var $_cats = null;


	/**
	 * Tag Data of listed items
	 *
	 * @var array
	 */
	var $_tags = null;

	/**
	 * Associated record translations
	 *
	 * @var array
	 */
	var $_translations = null;


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
		//$this->view_id = $view . '_' . $layout;

		// Call parent after setting ... $this->view_id
		parent::__construct($config);

		$p = $this->ovid;


		/**
		 * View's Filters
		 * Inherited filters : scope, search
		 */

		global $globalcats;

		// Category filtering
		$filter_cats        = $fcform ? $jinput->get('filter_cats',        '', 'int')  :  $app->getUserStateFromRequest( $p.'filter_cats',        'filter_cats',       '',  'int' );
		$filter_subcats     = $fcform ? $jinput->get('filter_subcats',     0,  'int')  :  $app->getUserStateFromRequest( $p.'filter_subcats',     'filter_subcats',     1,  'int' );
		$filter_catsinstate = $fcform ? $jinput->get('filter_catsinstate', 1,  'int')  :  $app->getUserStateFromRequest( $p.'filter_catsinstate', 'filter_catsinstate', 1,  'int' );

		if ($this->getState('filter_order') === 'a.ordering' || $this->getState('filter_order') === 'catsordering')
		{
			if (!$filter_cats)
			{
				$cat = reset($globalcats);
				$filter_cats = $cat->id;
			}
			if ($this->getState('filter_order_type') && $filter_cats)
			{
				$jinput->set( 'filter_subcats',	0 );
				$filter_subcats = 0;
			}
		}

		if ($filter_cats && !isset($globalcats[$filter_cats]))
		{
			// Clear currently filtered category if this does not exist anymore
			$jinput->set( 'filter_cats', '' );
			$filter_cats = '';
		}

		// Featured filtering, get as string to detect empty
		$filter_featured    = $fcform ? $jinput->get('filter_featured',    '', 'cmd')  :  $app->getUserStateFromRequest( $p.'filter_featured',    'filter_featured',   '',  'cmd' );

		if (strlen($filter_featured))
		{
			$filter_featured = (int) $filter_featured ? 1 : 0;
		}

		$this->setState('filter_cats', $filter_cats);
		$this->setState('filter_subcats', $filter_subcats);
		$this->setState('filter_catsinstate', $filter_catsinstate);
		$this->setState('filter_featured', $filter_featured);

		$app->setUserState($p.'filter_cats', $filter_cats);
		$app->setUserState($p.'filter_subcats', $filter_subcats);
		$app->setUserState($p.'filter_catsinstate', $filter_catsinstate);
		$app->setUserState($p.'filter_featured', $filter_featured);


		// Various filters
		$filter_tag     = $fcform ? $jinput->get('filter_tag',     false, 'array')  :  $app->getUserStateFromRequest( $p.'filter_tag',     'filter_tag',     false, 'array');
		$filter_lang	  = $fcform ? $jinput->get('filter_lang',    false, 'array')  :  $app->getUserStateFromRequest( $p.'filter_lang',    'filter_lang',    false, 'array');
		$filter_type    = $fcform ? $jinput->get('filter_type',    false, 'array')  :  $app->getUserStateFromRequest( $p.'filter_type',    'filter_type',    false, 'array');
		$filter_author  = $fcform ? $jinput->get('filter_author',  false, 'array')  :  $app->getUserStateFromRequest( $p.'filter_author',  'filter_author',  false, 'array');
		$filter_state   = $fcform ? $jinput->get('filter_state',   false, 'array')  :  $app->getUserStateFromRequest( $p.'filter_state',   'filter_state',   false, 'array');
		$filter_access  = $fcform ? $jinput->get('filter_access',  false, 'array')  :  $app->getUserStateFromRequest( $p.'filter_access',  'filter_access',  false, 'array');
		$filter_meta    = $fcform ? $jinput->get('filter_meta',    '',    'int')    :  $app->getUserStateFromRequest( $p.'filter_meta',    'filter_meta',    '',    'int');
		$csv_header     = $fcform ? $jinput->get('csv_header',     '',    'int')    :  $app->getUserStateFromRequest( $p.'csv_header',     'csv_header',     '',    'int');
		$csv_raw_export = $fcform ? $jinput->get('csv_raw_export', '',    'int')    :  $app->getUserStateFromRequest( $p.'csv_raw_export', 'csv_raw_export', '',    'int');
		$csv_all_fields = $fcform ? $jinput->get('csv_all_fields', '1',    'int')    :  $app->getUserStateFromRequest( $p.'csv_all_fields', 'csv_all_fields', '1',    'int');


		if (!is_array($filter_tag))    $filter_tag    = strlen($filter_tag)    ? array($filter_tag)    : array();
		if (!is_array($filter_lang))   $filter_lang   = strlen($filter_lang)   ? array($filter_lang)   : array();
		if (!is_array($filter_type))   $filter_type   = strlen($filter_type)   ? array($filter_type)   : array();
		if (!is_array($filter_author)) $filter_author = strlen($filter_author) ? array($filter_author) : array(); // Support for ZERO author id
		if (!is_array($filter_state))  $filter_state  = strlen($filter_state)  ? array($filter_state)  : array();
		if (!is_array($filter_access)) $filter_access = strlen($filter_access) ? array($filter_access) : array();
		//if (!is_array($filter_meta))   $filter_meta   = strlen($filter_meta)   ? array($filter_meta)   : array();

		$this->setState('filter_tag', $filter_tag);
		$this->setState('filter_lang', $filter_lang);
		$this->setState('filter_type', $filter_type);
		$this->setState('filter_author', $filter_author);
		$this->setState('filter_state', $filter_state);
		$this->setState('filter_access', $filter_access);
		$this->setState('filter_meta', $filter_meta);
		$this->setState('csv_header', $csv_header);
		$this->setState('csv_raw_export', $csv_raw_export);
		$this->setState('csv_all_fields', $csv_all_fields);

		$app->setUserState($p . 'filter_tag', $filter_tag);
		$app->setUserState($p . 'filter_lang', $filter_lang);
		$app->setUserState($p . 'filter_type', $filter_type);
		$app->setUserState($p . 'filter_author', $filter_author);
		$app->setUserState($p . 'filter_state', $filter_state);
		$app->setUserState($p . 'filter_access', $filter_access);
		$app->setUserState($p . 'filter_meta', $filter_meta);
		$app->setUserState($p . 'csv_header', $csv_header);
		$app->setUserState($p . 'csv_raw_export', $csv_raw_export);
		$app->setUserState($p . 'csv_all_fields', $csv_all_fields);


		// Date filters
		$date	 				= $fcform ? $jinput->get('date',      1,  'int')  :  $app->getUserStateFromRequest( $p.'date',      'date',      1,   'int' );
		$startdate	 	= $fcform ? $jinput->get('startdate', '', 'cmd')  :  $app->getUserStateFromRequest( $p.'startdate', 'startdate', '',  'cmd' );
		$enddate	 		= $fcform ? $jinput->get('enddate',   '', 'cmd')  :  $app->getUserStateFromRequest( $p.'enddate',   'enddate',   '',  'cmd' );

		$this->setState('date', $date);
		$this->setState('startdate', $startdate);
		$this->setState('enddate', $enddate);

		$app->setUserState($p.'date', $date);
		$app->setUserState($p.'startdate', $startdate);
		$app->setUserState($p.'enddate', $enddate);


		// Record ID filter
		$filter_id = $fcform ? $jinput->get('filter_id', '', 'int') : $app->getUserStateFromRequest($p . 'filter_id', 'filter_id', '', 'int');
		$filter_id = $filter_id ? $filter_id : '';  // needed to make text input field be empty

		$this->setState('filter_id', $filter_id);
		$app->setUserState($p . 'filter_id', $filter_id);


		// File ID filter
		$filter_fileid  = $fcform ? $jinput->get('filter_fileid', 0, 'int')  :  $app->getUserStateFromRequest( $p.'filter_fileid',  'filter_fileid',  0,  'int' );

		$this->setState('filter_fileid', $filter_fileid);
		$app->setUserState($p.'filter_fileid', $filter_fileid);

		// Association KEY filter
		$filter_assockey = $fcform ? $jinput->get('filter_assockey', 0, 'cmd')  :  $app->getUserStateFromRequest( $p.'filter_assockey',  'filter_assockey',  0,  'cmd' );

		$this->setState('filter_assockey', $filter_assockey);
		$app->setUserState($p.'filter_assockey', $filter_assockey);

		// Manage view permission
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanManage;
	}


	/**
	 * Method to set the record identifier (for singular operations) and clear record rows
	 *
	 * @param		int	    $id        record identifier
	 *
	 * @since   3.3.0
	 */
	public function setId($id)
	{
		// Set record id and wipe data, if setting a different ID
		if ($this->_id != $id)
		{
			parent:: setId($id);

			$this->_extra_cols = null;
		}
	}


	/**
	 * Method to set which record identifier that should be loaded when getItems() is called
	 *
	 * @param		array			$cid          array of record ids to load
	 *
	 * @since	  3.3.0
	 */
	public function setIds($cid)
	{
		parent:: setIds($cid);

		$this->_extra_cols = null;
	}


	/**
	 * Method to get records data
	 *
	 * @return array
	 *
	 * @since	3.3.0
	 */
	public function getItems()
	{
		static $tconfig = array();

		$print_logging_info = $this->cparams->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;

		// Lets load the records if it doesn't already exist
		if ($this->_data === null)
		{
			if (!empty($this->_ids))
			{
				$query_ids = $this->_ids;
			}
			else
			{
				// 1, get filtered, limited, ordered items
				$query = $this->_buildQuery();

				if ( $print_logging_info )  $start_microtime = microtime(true);
				$this->_db->setQuery($query, $this->getState('limitstart'), $this->getState('limit'));
				$rows = $this->_db->loadObjectList();
				if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

				// 2, get current items total for pagination
				$this->_db->setQuery("SELECT FOUND_ROWS()");
				$this->_total = $this->_db->loadResult();

				// 3, get item ids
				$query_ids = array();
				foreach ($rows as $row)
				{
					$query_ids[] = $row->id;
				}
			}

			// 4, get item data
			if (count($query_ids)) $query = $this->_buildQuery($query_ids);
			if ( $print_logging_info )  $start_microtime = microtime(true);
			$_data = array();
			if (count($query_ids))
			{
				$_data = $this->_db->setQuery($query)->loadObjectList('item_id');
			}
			if ( $print_logging_info ) @$fc_run_times['execute_sec_queries'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

			// 5, reorder items and get cat ids
			$this->_data = array();
			$this->_catids = array();
			$this->_tagids = array();
			foreach($query_ids as $item_id)
			{
				$item = $_data[$item_id];

				$item->catids = $item->relcats ? preg_split("/[\s]*,[\s]*/", $item->relcats) : array();
				foreach ($item->catids as $item_cat)
				{
					if ($item_cat) $this->_catids[$item_cat] = 1;
				}

				$item->tagids = $item->taglist ? array_reverse(preg_split("/[\s]*,[\s]*/", $item->taglist)) : array();
				foreach ($item->tagids as $item_tag)
				{
					if ($item_tag) $this->_tagids[$item_tag] = 1;
				}

				$this->_data[] = $item;
			}
			$this->_catids = array_keys($this->_catids);
			$this->_tagids = array_keys($this->_tagids);

			// 6, get other item data
			$k = 0;
			foreach ($this->_data as $item)
			{
				// Parse item configuration for every row
				try
				{
					$item->config = new JRegistry($item->config);
				}
				catch (Exception $e)
				{
					$item->config = flexicontent_db::check_fix_JSON_column('attribs', 'content', 'id', $item->id);
				}

				// Parse item's TYPE configuration if not already parsed
				if ( isset($tconfig[$item->type_name]) )
				{
		   		$item->tconfig = &$tconfig[$item->type_name];
					continue;
				}
				$tconfig[$item->type_name] = new JRegistry($item->tconfig);
				$item->tconfig = $tconfig[$item->type_name];
			}
			$k = 1 - $k;
		}

		// Get Original content ids for creating some untranslatable fields that have share data (like shared folders)
		flexicontent_db::getOriginalContentItemids($this->_data);

		return $this->_data;
	}


	/**
	 * Method to get fields used as extra columns of the item list
	 *
	 * @access public
	 * @return object
	 */
	function getExtraCols()
	{
		$user   = JFactory::getUser();

		// Check if extra columns already calculated
		if ( $this->_extra_cols !== null) return $this->_extra_cols;
		$this->_extra_cols = array();

		// Get the extra fields from COMPONENT then override by type setting if TYPE FILTER is active
		$im_extra_fields = $this->cparams->get('items_manager_extra_fields', '');
		$item_instance = null;

		$types = $this->getTypesFromFilter();
		if ( count($types)==1 )
		{
			$type = reset($types);
			$im_extra_fields = $type->params->get('items_manager_extra_fields', $im_extra_fields);

			$item_instance = new stdClass();
			$item_instance->type_id = $type->id;
		}

		$im_extra_fields = trim($im_extra_fields);
		if (!$im_extra_fields) return array();
		$im_extra_fields = preg_split("/[\s]*,[\s]*/", $im_extra_fields);

		foreach($im_extra_fields as $im_extra_field)
		{
			@list($fieldname,$methodname) = preg_split("/[\s]*:[\s]*/", $im_extra_field);
			$methodnames[$fieldname] = empty($methodname) ? 'display' : $methodname;
		}

		// Field's has_access flag
		$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
		$aid_list = implode(",", $aid_arr);

		// Column of has_access flag
		$select_access = ', CASE WHEN fi.access IN (0,'.$aid_list.') THEN 1 ELSE 0 END AS has_access';

		$query = ' SELECT fi.*'
			. $select_access
			.' FROM #__flexicontent_fields AS fi'
			.' WHERE fi.name IN ("' . implode('","',array_keys($methodnames)) . '")'
			.' ORDER BY FIELD(fi.name, "'. implode('","',array_keys($methodnames)) . '" )';
		$extra_fields = $this->_db->setQuery($query)->loadObjectList('id');

		$not_found_fields = $methodnames;
		foreach($extra_fields as $field)
		{
			$field->methodname = $methodnames[$field->name];
			FlexicontentFields::loadFieldConfig($field, $item_instance);
			unset($not_found_fields[$field->name]);
		}

		if ( count($not_found_fields) )
		{
			$filter_type = $this->getState('filter_type');
			JFactory::getApplication()->enqueueMessage('Extra column fieldnames: '. implode(', ',array_keys($not_found_fields)) .(!empty($filter_type) ? ' for current type ' : ''). ' were not found, please remove from '.(!empty($filter_type) ? ' type ' : ' component ').' configuration', 'warning');
		}

		$this->_extra_cols = & $extra_fields;
		$this->getExtraColValues();
		return $this->_extra_cols;
	}


	function getCustomFilts()
	{
		$app     = JFactory::getApplication();
		$user    = JFactory::getUser();
		$jinput  = $app->input;
		$option  = $jinput->get('option', '', 'cmd');
		$view    = $jinput->get('view', '', 'cmd');
		$fcform  = $jinput->get('fcform', 0, 'int');

		$p = $option.'.'.$view.'.';

		// Check if custom filters were already calculated
		if ($this->_custom_filters !== null)
		{
			return $this->_custom_filters;
		}

		$this->_custom_filters = array();

		// Get the extra fields from COMPONENT OR per type if TYPE FILTER is active
		$types = $this->getTypesFromFilter();

		if (count($types) === 1)
		{
			$type = reset($types);
			$im_custom_filters = $type->params->get("items_manager_custom_filters", '');

			$item_instance = new stdClass();
			$item_instance->type_id = $type->id;
		}
		else
		{
			$im_custom_filters = $this->cparams->get("items_manager_custom_filters", '');
			$item_instance = null;
		}

		$im_custom_filters = trim($im_custom_filters);

		if (!$im_custom_filters)
		{
			return array();
		}

		$im_custom_filters = preg_split("/[\s]*,[\s]*/", $im_custom_filters);
		$im_custom_filters = ArrayHelper::toInteger($im_custom_filters);

		// Field's has_access flag
		$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
		$aid_list = implode(",", $aid_arr);

		// Column of has_access flag
		$select_access = ', CASE WHEN fi.access IN (0,'.$aid_list.') THEN 1 ELSE 0 END AS has_access';

		$query = ' SELECT fi.*'
			. $select_access
			.' FROM #__flexicontent_fields AS fi'
			.' WHERE fi.id IN ("' . implode('","', $im_custom_filters) . '")'
			.' ORDER BY FIELD(fi.id, "'. implode('","', $im_custom_filters) . '")';
		$this->_db->setQuery($query);
		$custom_filters = $this->_db->loadObjectList('id');

		$allowed_field_types = array_flip(array('select', 'selectmultiple', 'radio', 'radioimage', 'checkbox', 'checkboximage'));
		foreach($custom_filters as $filter)
		{
			if ( !isset($allowed_field_types[$filter->field_type]) )
			{
				continue;
			}
			FlexicontentFields::loadFieldConfig($filter, $item_instance);

			// Since the filter values, may or may not be an array, we need to use 'array' as filter
			$filter->value = $fcform ? $jinput->get('filter_'.$filter->id, null, 'array')  :  $app->getUserStateFromRequest( $p.'filter_'.$filter->id,	'filter_'.$filter->id, null, 'array');

			// Force value to be array
			if ( !is_array($filter->value) )  $filter->value = strlen($filter->value) ? array($filter->value) : array();
			// Convert array having a single zero length string, to array()
			if ( count($filter->value)==1 && !strlen(reset($filter->value)) )  $filter->value = array();

			$this->setState('filter_'.$filter->id, $filter->value);
			$app->setUserState($p.'filter_'.$filter->id, $filter->value);
		}

		$this->_custom_filters = & $custom_filters;
		$this->renderFiltersHTML();

		return $this->_custom_filters;
	}


	/**
	 * Method to get fields values of the fields used as extra columns of the item list
	 *
	 * @access public
	 * @return object
	 */
	function renderFiltersHTML()
	{
		$app    = JFactory::getApplication();
		$jinput = $app->input;

		$allowed_field_types = array_flip(array('select', 'selectmultiple', 'radio', 'radioimage', 'checkbox', 'checkboximage'));
		$formName ='adminForm';

		// Set view to category before rendering the filters HTML
		$view   = $jinput->get('view', '', 'cmd');
		$jinput->set('view', 'category');

		foreach($this->_custom_filters as $filter)
		{
			if (!isset($allowed_field_types[$filter->field_type]))
			{
				JFactory::getApplication()->enqueueMessage('Filter: '. $field->name .' is of type '. $field->field_type .' , allowed types for backend custom filters are: '. implode(', ', array_keys($allowed_field_types)), 'warning');
				$filter->html = '';
				continue;
			}

			$item_pros = false;
			$extra_props = ($filter->field_type == 'radioimage' || $filter->field_type == 'checkboximage') ? array('image', 'valgrp', 'state') : array();
			$elements = FlexicontentFields::indexedField_getElements($filter, $item=null, $extra_props, $item_pros, $is_filter=true);

			$filter->isfilter = 1;

			$filter->parameters->set('faceted_filter', 0);
			$filter->parameters->set('display_filter_as', 0);
			$filter->parameters->set('display_label_filter', 0);
			$filter->parameters->set('label_filter_css', '');
			//$filter->parameters->set('display_label_filter', -1);
			//$filter->parameters->set('label_filter_css', 'add-on');
			$filter->parameters->set( 'filter_extra_attribs', ' onchange="if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform()" ' );

			// Check for error during getting indexed field elements
			if (!$elements)
			{
				$filter->html = '';

				// Must retrieve variable here, and not before retrieving elements !
				$sql_mode = $filter->parameters->get( 'sql_mode', 0 );

				if ($sql_mode && $item_pros > 0)
				{
					$filter->html = sprintf( JText::_('FLEXI_FIELD_ITEM_SPECIFIC_AS_FILTERABLE'), $filter->label );
				}
				else
				{
					$filter->html = $sql_mode
						? JText::_('FLEXI_FIELD_INVALID_QUERY')
						: JText::_('FLEXI_FIELD_INVALID_ELEMENTS');
				}
				continue;
			}

			FlexicontentFields::createFilter($filter, $filter->value, $formName, $elements);
		}

		// Restore view
		$jinput->set('view', $view);
	}


	/**
	 * Method to get fields values of the fields used as extra columns of the item list
	 *
	 * @access public
	 * @return object
	 */
	function getExtraColValues()
	{
		if ( $this->_extra_cols==null) $this->getExtraCols();

		if ( empty($this->_extra_cols) ) return;
		if ( empty($this->_data) ) return;

		$item_ids = array();
		foreach($this->_data as $item) $item_ids[] = $item->id;
		$field_ids = array_keys($this->_extra_cols);

		$query = 'SELECT field_id, value, item_id, valueorder, suborder'
				.' FROM #__flexicontent_fields_item_relations'
				.' WHERE item_id IN (' . implode(',', $item_ids) .')'
				.' AND field_id IN (' . implode(',', $field_ids) . ')'
				.' AND value > "" '
				.' ORDER BY item_id, field_id, valueorder, suborder'  // first 2 parts are not needed ...
				;
		$values = $this->_db->setQuery($query)->loadObjectList();

		$fieldvalues = array();

		foreach ($values as $v)
		{
			//$field_name = $this->_extra_cols[$v->field_id]->name;
			$fieldvalues[$v->item_id][ $v->field_id ][$v->valueorder - 1][$v->suborder - 1] = $v->value;
		}

		// Rearrange and assign the field values
		foreach ($this->_data as & $item) {
			if (!isset($fieldvalues[$item->id])) continue;
			foreach ($fieldvalues[$item->id] as & $fieldvalue) {
				foreach ($fieldvalue as & $mainordered_value) {
					if (count($mainordered_value) == 1) $mainordered_value = reset($mainordered_value);
				}
				unset($mainordered_value);
			}
			unset($fieldvalue);
			$item->fieldvalues = $fieldvalues[$item->id];
		}
		unset($item);
	}


	/**
	 * Method to set the default site language to an item with no language
	 *
	 * @return boolean
	 * @since 1.5
	 */
	function setSiteDefaultLang($id)
	{
		$lang = flexicontent_html::getSiteDefaultLang();
		$query 	= 'UPDATE #__flexicontent_items_ext'
				. ' SET language = ' . $this->_db->Quote($lang)
				. ' WHERE item_id = ' . (int)$id
				;
		$this->_db->setQuery($query);
		$this->_db->execute();

		return $lang;
	}


	/**
	 * Method to get items not having extended data associations
	 *
	 * @return array
	 * @since 1.5
	 */
	function getUnboundedItems($limit=25, $count_only=true, $checkNoExtData=true, $checkInvalidCat=true, $noCache=false)
	{
		if (!$checkNoExtData && !$checkInvalidCat) return $count_only ? 0 : array();

		$session = JFactory::getSession();
		$configured = FLEXI_J16GE ? FLEXI_CAT_EXTENSION : FLEXI_SECTION;
		if ( !$configured ) return null;

		$print_logging_info = $this->cparams->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		if ( $print_logging_info )  $start_microtime = microtime(true);

		$done = true;
		if ($checkNoExtData)  $done = $done && ($session->get('unbounded_noext',  false, 'flexicontent') === 0);
		if ($checkInvalidCat) $done = $done && ($session->get('unbounded_badcat', false, 'flexicontent') === 0);

		if ( !$noCache && $done ) return $count_only ? 0 : array();

		$match_rules = array();
		if ($checkNoExtData)  $match_rules[] = 'ie.item_id IS NULL';
		if ($checkInvalidCat) $match_rules[] = 'cat.id IS NULL';
		if ( empty($match_rules) ) return $count_only ? 0 : array();
		$query 	= 'SELECT '. ($count_only ? 'COUNT(*)' : 'c.id, c.title, c.introtext, c.`fulltext`, c.catid, c.created, c.created_by, c.modified, c.modified_by, c.version, c.state, c.language')
			. ' FROM #__content as c'
			. ($checkNoExtData  ? ' LEFT JOIN #__flexicontent_items_ext as ie ON c.id=ie.item_id' : '')
			. ($checkInvalidCat ? ' LEFT JOIN #__categories as cat ON c.catid=cat.id' : '')
			. (!FLEXI_J16GE ? ' WHERE sectionid = ' . (int)FLEXI_SECTION : ' WHERE 1')
			. ' AND ('.implode(' OR ',$match_rules).')'
			;
		$this->_db->setQuery($query, 0, $limit);

		if ($count_only) {
			$unbounded_count = (int) $this->_db->loadResult();
			if ($checkNoExtData)  $session->set('unbounded_noext', $unbounded_count, 'flexicontent');
			if ($checkInvalidCat) $session->set('unbounded_badcat', $unbounded_count, 'flexicontent');
		} else {
			$unbounded = $this->_db->loadObjectList();
		}

		if ( $print_logging_info ) @$fc_run_times['unassoc_items_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

		return $count_only ? $unbounded_count : $unbounded;
	}



	function fixMainCat($default_cat)
	{
		// Correct non-existent main category in content table
		$query = 'UPDATE #__content as c '
					.' LEFT JOIN #__categories as cat ON c.catid=cat.id'
					.' SET c.catid=' .$default_cat
					.' WHERE cat.id IS NULL';
		$this->_db->setQuery($query);
		$this->_db->execute();

		// Correct non-existent main category in content table
		$query = 'UPDATE #__flexicontent_items_tmp as c '
					.' LEFT JOIN #__categories as cat ON c.catid=cat.id'
					.' SET c.catid=' .$default_cat
					.' WHERE cat.id IS NULL';
		$this->_db->setQuery($query);
		$this->_db->execute();
	}


	/**
	 * Method to add flexi extended datas to standard content
	 *
	 * @params object	the unassociated items rows
	 * @params boolean	add the records from the items_ext table
	 * @return boolean
	 * @since 1.5
	 */
	function bindExtData($rows)
	{
		if (!$rows || !count($rows)) return;

		$app     = JFactory::getApplication();
		$jinput  = $app->input;

		$search_prefix = $this->cparams->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix

		$typeid       = $jinput->get('typeid', 1, 'int');
		$default_cat  = $jinput->get('default_cat', 0, 'int');
		$default_lang = flexicontent_html::getSiteDefaultLang();

		// Get invalid cats, to avoid using them during binding, this is only done once
		$session = JFactory::getSession();
		$badcats_fixed = $session->get('badcats', null, 'flexicontent');
		if ( $badcats_fixed === null ) {
			// Correct non-existent main category in content table
			$query = 'UPDATE #__content as c '
						.' LEFT JOIN #__categories as cat ON c.catid=cat.id'
						.' SET c.catid=' .$default_cat
						.' WHERE cat.id IS NULL';
			$this->_db->setQuery($query);
			$this->_db->execute();
			$session->set('badcats_fixed', 1, 'flexicontent');
		}

		// Calculate item data to be used for current bind STEP
		$catrel = array();
		foreach ($rows as $row) {
			$row_catid = (int)$row->catid;
			$catrel[] = '('.$row_catid.', '.(int)$row->id.')';
			// append the text property to the object
			if (StringHelper::strlen($row->fulltext) > 1) {
				$row->text_stripped = $row->introtext . '<hr id="system-readmore" />' . $row->fulltext;
			} else {
				$row->text_stripped = flexicontent_html::striptagsandcut($row->introtext);
			}
		}

		// Insert main category-item relation via single query
		$catrel = implode(', ', $catrel);
		$query = "INSERT INTO #__flexicontent_cats_item_relations (`catid`, `itemid`) "
				."  VALUES ".$catrel
				." ON DUPLICATE KEY UPDATE ordering=ordering";
		$this->_db->setQuery($query);
		$this->_db->execute();


		$query = "SHOW VARIABLES LIKE 'max_allowed_packet'";
		$this->_db->setQuery($query);
		$_dbvariable = $this->_db->loadObject();
		$max_allowed_packet = flexicontent_upload::parseByteLimit(@ $_dbvariable->Value);
		$max_allowed_packet = $max_allowed_packet ? $max_allowed_packet : 256*1024;
		$query_lim = (int) (3 * $max_allowed_packet / 4);

		// Insert items_ext datas,
		// NOTE: we will not use a single query for creating multiple records, instead we will create only e.g. 100 at once,
		// because of the column search_index which can be quite long
		$itemext = array();
		$id_arr  = array();
		$row_count = count($rows);
		$n = 0; $i = 0;
		$query_len = 0;
		foreach ($rows as $row)
		{
			$ilang = $row->language ? $row->language : $default_lang;

			if ($search_prefix)
				$_search_index = preg_replace('/(\b[^\s,\.]+\b)/u', $search_prefix.'$0', $row->title.' | '.$row->text_stripped);
			else
				$_search_index = $row->title.' | '.$row->text_stripped;

			$itemext[$i] = '('.(int)$row->id.', '. $typeid .', '.$this->_db->Quote($ilang).', 0, "", "", "", '.$this->_db->Quote($_search_index) .')';
			$id_arr[$i] = (int)$row->id;
			$query_len += strlen($itemext[$i]) + 2;  // Sum of query length so far
			$n++; $i++;
			if ( ($n%101 == 0) || ($n==$row_count) || ($query_len > $query_lim ))
			{
				$itemext_list = implode(', ', $itemext);
				$query = "INSERT INTO #__flexicontent_items_ext (`item_id`, `type_id`, `language`, `lang_parent_id`, `sub_items`, `sub_categories`, `related_items`, `search_index`)"
						." VALUES " . $itemext_list
						." ON DUPLICATE KEY UPDATE type_id=VALUES(type_id), language=VALUES(language), search_index=VALUES(search_index)";
				$this->_db->setQuery($query);
				$this->_db->execute();
				// reset the item array
				$itemext = array();

				$query = "UPDATE #__flexicontent_items_tmp"
					." SET type_id=".$typeid
					." WHERE id IN(".implode(',',$id_arr).")";
				$this->_db->setQuery($query);
				$this->_db->execute();
				// reset the item id array
				$id_arr = array();

				$i = 0; // reset sub-counter, and query length
				$query_len = 0;
			}
		}

		// Update temporary item data
		$this->updateItemCountingData($rows);
	}


	function updateItemCountingData($rows = false, $catid = 0)
	{
		$app = JFactory::getApplication();

		$cache_tbl = "#__flexicontent_items_tmp";
		$tbls = array($cache_tbl);

		foreach ($tbls as $tbl)
		{
			$tbl_fields[$tbl] = $this->_db->getTableColumns($tbl);
		}

		// Get the column names
		$tbl_fields = array_keys($tbl_fields[$cache_tbl]);
		$tbl_fields_sel = array();
		foreach ($tbl_fields as $tbl_field)
		{
			$tbl_fields_sel[] = $tbl_field=='language' || $tbl_field=='type_id' || $tbl_field=='lang_parent_id'
				? 'ie.'.$tbl_field
				: 'c.'.$tbl_field;
		}

		$row_ids = array();
		if (!empty($rows))
		{
			foreach ($rows as $row) $row_ids[] = $row->id;
		}

		// Copy data into it
		$query 	= ($row_ids || $catid ? 'REPLACE' : 'INSERT') . ' INTO ' . $cache_tbl . ' (';
		$query .= "`".implode("`, `", $tbl_fields)."`";
		$query .= ") SELECT ";

		$cols_select = array();
		$query .= implode(", ", $tbl_fields_sel);
		$query .= " FROM #__content AS c";
		$query .= " JOIN #__flexicontent_items_ext AS ie ON c.id=ie.item_id";
		$query .= $row_ids ? ' WHERE c.id IN (' . implode(',', $row_ids) . ')' : '';
		$query .= $catid ? ' WHERE c.catid = ' . $catid : '';

		try {
			$result = $this->_db->setQuery($query)->execute();
		}
		catch (Exception $e) {
			$result = false;
			echo $e->getMessage();
		}

		return $result;
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
		// Lets load the records if it was not calculated already via using SQL_CALC_FOUND_ROWS + 'SELECT FOUND_ROWS()'
		if ($this->_total === null)
		{
			$this->_total = (int) $this->_getListCount($this->_buildQuery());
		}

		return $this->_total;
	}




	/**
	 * Method to build the query for the records
	 *
	 * @return  JDatabaseQuery   The DB Query object
	 *
	 * @since   3.3.0
	 */
	protected function _buildQuery($query_ids = false)
	{
		$use_versioning = $this->cparams->get('use_versioning', 1);
		$lang  = 'ie.language AS lang, ie.lang_parent_id, ';
		$lang .= 'CASE WHEN ie.lang_parent_id=0 THEN a.id ELSE ie.lang_parent_id END AS lang_parent_id, ';

		$filter_tag 		= $this->getState('filter_tag');
		$filter_state   = $this->getState('filter_state');
		$filter_order   = $this->getState('filter_order');
		$filter_meta    = $this->getState('filter_meta');

		$filter_cats        = $this->getState('filter_cats');
		$filter_subcats     = $this->getState('filter_subcats');
		$filter_catsinstate = $this->getState('filter_catsinstate');

		$nullDate = $this->_db->Quote($this->_db->getNullDate());
		$nowDate  = $this->_db->Quote( JFactory::getDate()->toSql() );

		$filter_tag   = ArrayHelper::toInteger($filter_tag);
		$filter_state = empty($filter_state) ? array() :
			(!is_array($filter_state) ? array($filter_state) : $filter_state);

		$subquery 	= 'SELECT name FROM #__users WHERE id = a.created_by';

		if (!$query_ids)
		{
			$customFilts = $this->getCustomFilts();
			$customFiltsActive = array();
			foreach($customFilts as $filter)
			{
				if (!count($filter->value)) continue;
				$customFiltsActive[$filter->id] = $filter->value;
			}
		}

		if ( $query_ids || in_array($filter_order, array('rating_count','rating')) )
		{
			$rating_join = null;
			$ratings_col = ', ' . flexicontent_db::buildRatingOrderingColumn($rating_join, $colname = 'rating', 'a');
		}

		if ( !$query_ids )
		{
			$query = 'SELECT SQL_CALC_FOUND_ROWS a.id '
				. ( in_array($filter_order, array('rating_count','rating')) ?
					', cr.rating_count AS rating_count' . $ratings_col : ''
					)
				. ( count($customFiltsActive) ?
					', COUNT(DISTINCT fi.field_id) AS matched_custom ' : ''
					)
				. ( in_array('RV', $filter_state) ?
					', a.version' : ''
					)
				. ( in_array($filter_order, array('a.ordering','catsordering')) ?
					', CASE WHEN a.state IN (1,-5) THEN 0 ELSE (CASE WHEN a.state IN (0,-3,-4) THEN 1 ELSE (CASE WHEN a.state IN (2) THEN 2 ELSE (CASE WHEN a.state IN (-2) THEN 3 ELSE 4 END) END) END) END as state_order ' : ''
					)
				;
		}
		else
		{
			$query =
				'SELECT a.*, ie.item_id as item_id, ie.search_index AS search_index, ie.type_id, '. $lang .' cousr.name AS editor'
				. ($filter_cats || $filter_catsinstate != 99 ?
					', rel.catid as rel_catid' : ''
					)
				. ', cr.rating_count AS rating_count' . $ratings_col
				. ', CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
				. ', c.title AS maincat_title'
				. ', GROUP_CONCAT(DISTINCT icats.catid SEPARATOR  ",") AS relcats'
				. ', GROUP_CONCAT(DISTINCT tg.tid    SEPARATOR  ",") AS taglist'
				. ', CASE WHEN level.title IS NULL THEN CONCAT_WS(\'\', \'deleted:\', a.access) ELSE level.title END AS access_level'
				. ( in_array($filter_order, array('a.ordering', 'catsordering')) ?
					', CASE WHEN a.state IN (1,-5) THEN 0 ELSE (CASE WHEN a.state IN (0,-3,-4) THEN 1 ELSE (CASE WHEN a.state IN (2) THEN 2 ELSE (CASE WHEN a.state IN (-2) THEN 3 ELSE 4 END) END) END) END as state_order' : ''
					)
				. ($filter_cats && !$filter_subcats ?
					', rel.ordering as catsordering' : ', \'\' as catsordering'
					)
				. ', CASE WHEN a.publish_up IS NULL OR a.publish_up = '.$nullDate.' OR a.publish_up <= '.$nowDate.' THEN 0 ELSE 1 END as publication_scheduled'
				. ', CASE WHEN a.publish_down IS NULL OR a.publish_down = '.$nullDate.' OR a.publish_down >= '.$nowDate.' THEN 0 ELSE 1 END as publication_expired'
				. ', t.name AS type_name, (' . $subquery . ') AS author, a.attribs AS config, t.attribs as tconfig'
				. ($use_versioning ? ', CASE WHEN a.version = MAX(fv.version_id) THEN 0 ELSE MAX(fv.version_id) END as unapproved_version ' : ', 0 as unapproved_version')

				. (FLEXI_J40GE
					? ', wa.stage_id AS stage_id' .
						', ws.title AS stage_title' .
						', ws.workflow_id AS workflow_id' .
						', w.title AS workflow_title'
					: '')
				;
		}

		$scope  = $this->getState('scope');
		$search = $this->getState('search');

		$use_tmp = !$query_ids && !$filter_meta && (!$search || !in_array($scope, array('_desc_', '_meta_', 'a.metadesc', 'a.metakey')));
		$tmp_only = $use_tmp && (!$search || $scope !== 'ie.search_index');

		// Get the WHERE and ORDER BY clauses for the query
		$extra_joins = '';
		if (!$query_ids)
		{
			$where		= $this->_buildContentJoinsWhere($extra_joins, $tmp_only);
			$orderby	= $this->_buildContentOrderBy();
		}

		$query .= ''
				. ($use_tmp
					? ' FROM #__flexicontent_items_tmp AS a'
					: ' FROM #__' . $this->records_dbtbl . ' AS a')

				. (!$tmp_only
					? ' JOIN #__flexicontent_items_ext AS ie ON ie.item_id = a.id'
					: '')

				. ' JOIN #__categories AS c ON c.id = a.catid'

				. (!$query_ids && count($customFiltsActive)
					? ' JOIN #__flexicontent_fields_item_relations as fi ON a.id=fi.item_id'
					: '')

				. ' LEFT JOIN #__flexicontent_tags_item_relations AS tg ON a.id=tg.itemid'

				. ($query_ids || in_array($filter_order, array('rating_count', 'rating'))
					? ' LEFT JOIN ' . $rating_join
					: '')

				. (!$query_ids && in_array('RV', $filter_state)
					? ' JOIN #__flexicontent_versions AS fv ON a.id=fv.item_id'
					: '')

				. ($query_ids && $use_versioning
					? ' LEFT JOIN #__flexicontent_versions AS fv ON a.id=fv.item_id'
					: '')

				// Used to get list of the assigned categories (left join and not inner join, needed to INCLUDE items do not have records in the multi-cats-items TABLE)
				. ($query_ids
					? ' LEFT JOIN #__flexicontent_cats_item_relations AS icats ON icats.itemid = a.id'
					: '')

				// Limit to items according to their assigned categories (left join and not inner join, needed to INCLUDE items do not have records in the multi-cats-items TABLE)
				. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = a.id'
					. ($filter_cats && !$filter_subcats ? ' AND rel.catid=' . $filter_cats : '') // Force rel.catid to be of the specific filtered category (rel.catid is used by ordering code)

				// Get type info, (left join and not inner join, needed to INCLUDE items without type !!)
				. ' LEFT JOIN #__flexicontent_types AS t ON t.id = ' . ($tmp_only ? 'a.' : 'ie.') . 'type_id'

				// Get user info of that checkout the item, left join and not inner join, needed to INCLUDE items checkedout by a deleted user
				. ($query_ids
					? ' LEFT JOIN #__users AS cousr ON cousr.id = a.checked_out'
					: '')

				// Get access level info, (left join and not inner join, needed to INCLUDE items with bad access levels)
				. ($query_ids
					? ' LEFT JOIN #__viewlevels AS level ON level.id = a.access'
					: '')

				// Workflows
				. (FLEXI_J40GE
					? ' LEFT JOIN #__workflow_associations AS wa ON wa.item_id = a.id AND wa.extension = "com_content.article"' .
						' LEFT JOIN #__workflow_stages AS ws ON ws.id = wa.stage_id' .
						' LEFT JOIN #__workflows AS w ON w.id = ws.workflow_id'
					: '')

				// ...
				. $extra_joins
				;

		if ( !$query_ids )
		{
			$having = array();

			if (in_array('RV', $filter_state))
			{
				$having[] = ' a.version<>MAX(fv.version_id) ';
			}

			if (count($customFiltsActive))
			{
				$having[] = ' matched_custom = '.count($customFiltsActive);
			}

			// We will use GROUP BY to be able to calculate the aggregated column 'matched_custom' (aggregate function COUNT)
			$query .= ''
				. $where
				. ' GROUP BY a.id'
				. (count($having) ? ' HAVING ' . implode(' AND ', $having) : '')
				. $orderby
				;
		}
		else
		{
			// We will use GROUP BY to be able to calculate the aggregated columns 'relcats' and 'taglist' (aggregate function GROUP_CONCAT)
			$query .= ''
				. ' WHERE a.id IN (' . implode(',', $query_ids) . ')'
				. ' GROUP BY a.id'
				;
		}

		//echo $query ."<br/><br/>";
		return $query;
	}


	/**
	 * Method to build the orderby clause of the query for the records
	 *
	 * @param		JDatabaseQuery|bool   $q   DB Query object or bool to indicate returning an array or rendering the clause
	 *
	 * @return  JDatabaseQuery|array
	 *
	 * @since   3.3.0
	 */
	protected function _buildContentOrderBy($q = false)
	{
		$filter_order_type= $this->getState('filter_order_type');
		$filter_order     = $this->getState('filter_order');
		$filter_order_Dir = $this->getState('filter_order_Dir');

		switch ($filter_order)
		{
			case 'type_name':
				$_filter_order = 't.name';
				$orderby 	= ' ORDER BY ' . $_filter_order . ' ' . $filter_order_Dir . ', a.id ASC';
				break;
			case 'a.ordering':
				$orderby 	= ' ORDER BY a.catid, state_order, a.language, ' . $filter_order . ' ' . $filter_order_Dir . ', a.id DESC';
				break;
			case 'catsordering':
				$_filter_order = 'rel.ordering';
				$orderby 	= ' ORDER BY rel.catid, state_order, a.language, ' . $_filter_order . ' ' . $filter_order_Dir . ', a.id DESC';
				break;
			case 'a.modified':
				$orderby 	= ' ORDER BY ' . $filter_order . ' ' . $filter_order_Dir . ', a.created ' . ' ' . $filter_order_Dir;
				break;
			case 'rating':
				$orderby 	= ' ORDER BY ' . $filter_order . ' ' . $filter_order_Dir . ', cr.rating_count ' . ' ' . $filter_order_Dir;
				break;
			default:
				$orderby 	= empty($filter_order) ? '' : ' ORDER BY ' . $filter_order . ' ' . $filter_order_Dir;
				break;
		}

		return $orderby;
	}


	/**
	 * Method to build the where clause of the query for the Items
	 *
	 * @access private
	 * @return string
	 * @since 1.0
	 */
	function _buildContentJoinsWhere(& $extra_joins = '', $tmp_only = false)
	{
		$session = JFactory::getSession();
		$user    = JFactory::getUser();
		$perms   = FlexicontentHelperPerm::getPerm();


		/**
		 * FLAGs to decide which items to list
		 */

		// Note 'all items' is already granted to super admins, so no need to check the is-super-admin ('core.admin') separately
		$allitems	= $perms->DisplayAllItems;
		$viewable_items = $this->cparams->get('iman_viewable_items', 1);
		$editable_items = $this->cparams->get('iman_editable_items', 0);


		/**
		 * SPECIAL item listing CASES, item ids are already calculated and provided,
		 * in such a case WHERE clause limits to the given item ids
		 */

		// CASE 1: listing items using a file
		$filter_fileid = $this->getState('filter_fileid');

		if ($filter_fileid)
		{
			$fileid_to_itemids = $session->get('fileid_to_itemids', array(),'flexicontent');
			$itemids =  $fileid_to_itemids[$filter_fileid];

			return empty($itemids)
				? ' WHERE 0 '
				: ' WHERE a.id IN ('. implode(',', $itemids) .') ';
		}


		/**
		 * Get item list filters
		 */

		// various filters (mostly multi-value)
		$filter_tag 		= $this->getState('filter_tag');
		$filter_lang    = $this->getState('filter_lang');
		$filter_type 		= $this->getState('filter_type');
		$filter_author	= $this->getState('filter_author');
		$filter_state   = $this->getState('filter_state');
		$filter_access  = $this->getState('filter_access');
		$filter_meta    = $this->getState('filter_meta');

		// category related filters
		$filter_cats        = $this->getState('filter_cats');
		$filter_subcats     = $this->getState('filter_subcats');
		$filter_catsinstate = $this->getState('filter_catsinstate');
		$filter_featured    = $this->getState('filter_featured');

		// filter id
		$filter_id = $this->getState('filter_id');

		// text search and search scope
		$scope  = $this->getState('scope');
		$search = $this->getState('search');
		$search = StringHelper::trim( StringHelper::strtolower( $search ) );

		// date filters
		$date      = $this->getState('date');
		$startdate = $this->getState('startdate');
		$enddate   = $this->getState('enddate');

		$startdate = StringHelper::trim( StringHelper::strtolower( $startdate ) );
		$enddate   = StringHelper::trim( StringHelper::strtolower( $enddate ) );


		/**
		 * Start building the AND parts of where clause
		 */

		$where = array();

		// Limit items to the children of the FLEXI_CATEGORY, currently FLEXI_CATEGORY is root category (id:1) ...
		//$where[] = ' (cat.lft > ' . $this->_db->Quote(FLEXI_LFT_CATEGORY) . ' AND cat.rgt < ' . $this->_db->Quote(FLEXI_RGT_CATEGORY) . ')';
		//$where[] = ' cat.extension = ' . $this->_db->Quote(FLEXI_CAT_EXTENSION);


		/**
		 * IF items viewable: default is enabled
		 */

		$joinaccess = '';

		if (!$allitems && $viewable_items)
		{
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$where[] = ' t.access IN (0,'.$aid_list.')';
			$where[] = ' c.access IN (0,'.$aid_list.')';
			$where[] = ' a.access IN (0,'.$aid_list.')';
		}

		$extra_joins .= $joinaccess;


		/**
		 * IF items in an editable (main) category: default is disabled
		 */

		$allowedcats = false;
		$allowedcats_own = false;

		if (!$allitems && $editable_items)
		{
			$allowedcats = FlexicontentHelperPerm::getAllowedCats( $user, $actions_allowed=array('core.edit'), $require_all=true, $check_published = false, false, $find_first = false);
			$allowedcats_own = FlexicontentHelperPerm::getAllowedCats( $user, $actions_allowed=array('core.edit.own'), $require_all=true, $check_published = false, false, $find_first = false);

			if ($allowedcats || $allowedcats_own)
			{
				$_edit_where = '( ';

				if ($allowedcats)
				{
					$_edit_where .= '( a.catid IN (' . implode( ', ', $allowedcats ) . ') )';
				}

				if ($allowedcats && $allowedcats_own)
				{
					$_edit_where .= ' OR ';
				}

				if ($allowedcats_own)
				{
					$_edit_where .= '( a.catid IN (' . implode( ', ', $allowedcats_own ) . ') AND a.created_by='.$user->id.')';
				}

				$where[] = $_edit_where .' )';
			}
		}


		/**
		 * Limit using the category filter
		 */

		if ($filter_cats)
		{
			// CURRENTLY in main or secondary category.  -TODO-  maybe add limiting by main category, if ... needed

			$cat_type = !$this->getState('filter_order_type') && $this->getState('filter_order') === 'a.ordering'
				? 'a.catid'
				: 'rel.catid';

			if ($filter_subcats)
			{
				global $globalcats;

				if ($filter_catsinstate == 99)
				{
					$_sub_cids = $globalcats[$filter_cats]->descendantsarray;
				}
				else
				{
					$_sub_cids = array();

					foreach( $globalcats[$filter_cats]->descendantsarray as $_dcatid)
					{
						if ($globalcats[$_dcatid]->published==$filter_catsinstate) $_sub_cids[] = $_dcatid;
					}
				}

				$where[] = empty($_sub_cids)
					? ' FALSE '
					: '(' .
						$cat_type . ' IN (' . implode(', ', $_sub_cids) . ')' .
						' OR ' .
						'c.id IN (' . implode(', ', $_sub_cids) . ')' .
						')';
			}
			else
			{
				$where[] = $cat_type.' = ' . $filter_cats;
			}
		}

		else
		{
			if ($this->getState('filter_order_type') && $this->getState('filter_order') === 'catsordering')
			{
				$where[] = ' FALSE  ';  // Force no items
				//$this->setState('ordering_msg', array('warning' => JText::_('FLEXI_FCORDER_FC_ORDER_PLEASE_SET_CATEGORY_FILTER')));
			}

			if ($filter_catsinstate != 99)  // if not showing items in any category state
			{
				$where[] = '(rel.catid IN ( SELECT id FROM #__categories WHERE published='.$filter_catsinstate.' )' .' OR '. 'c.published = '.$filter_catsinstate.')';
			}
		}


		/**
		 * Limit using the featured filter
		 */

		if (strlen($filter_featured))
		{
			$where[] = 'a.featured = ' . $filter_featured;
		}


		/**
		 * Limit using state or group of states (e.g. published states)
		 */

		if (empty($filter_state))
		{
			$where[] = 'a.state <> -2';
			$where[] = 'a.state <> 2';
		}

		else
		{
			$filter_state = empty($filter_state)
				? array()
				: (!is_array($filter_state) ? array($filter_state) : $filter_state);

			$FS = array_flip($filter_state);
			$states = array();

			// No limitations, and clear any other flags in the array
			if (isset($FS['ALL']))
			{
				$filter_state = array('ALL');
				$FS = array('ALL'=>0);
				$filter_state = $this->setState('filter_state', $filter_state);
			}
			elseif (isset($FS['ORPHAN']))
			{
				$where[] = 'a.state NOT IN(2,-2,1,0,-3,-4,-5)';
			}
			else
			{
				isset($FS['ALL_P']) ? array_push($states, 1,-5) : null;
				isset($FS['ALL_U']) ? array_push($states, 0,-3,-4) : null;
				isset($FS['P']) ? array_push($states, 1) : null;
				isset($FS['U']) ? array_push($states, 0) : null;
				isset($FS['PE']) ? array_push($states, -3) : null;
				isset($FS['OQ']) ? array_push($states, -4) : null;
				isset($FS['IP']) ? array_push($states, -5) : null;
				isset($FS['RV']) ? array_push($states, 1,-5) : null;
				isset($FS['A']) ? array_push($states, 2) : null;
				isset($FS['T']) ? array_push($states, -2) : null;

				$states = array_unique($states, SORT_REGULAR);

				if (!empty($states))
				{
					$where[] = 'a.state IN ('.implode(',', $states).')';
				}
			}
		}


		/**
		 * Limit using simpler filtering, (item) type, author, (item) id, language, access
		 */

		if (!empty($filter_tag))
		{
			$filter_tag = ArrayHelper::toInteger($filter_tag);
			$where[] = 'tg.tid IN (' . implode( ',', $filter_tag) .')';
		}

		if (!empty($filter_type))
		{
			$filter_type = ArrayHelper::toInteger($filter_type);
			$where[] = ($tmp_only ? 'a.' : 'ie.') . 'type_id IN (' . implode( ',', $filter_type) .')';
		}

		if (!empty($filter_author))
		{
			$filter_author = ArrayHelper::toInteger($filter_author);
			$where[] = 'a.created_by IN (' . implode( ',', $filter_author) .')';
		}

		if ($filter_id)
		{
			$where[] = 'a.id = ' . $filter_id;
		}

		if (!empty($filter_lang))
		{
			if (!is_array($filter_lang))
			{
				$filter_langs[] = $this->_db->Quote($filter_lang);
			}
			else
			{
				foreach($filter_lang as $val)
				{
					$filter_langs[] = $this->_db->Quote($val);
				}
			}

			$where[] = 'a.language IN (' . implode( ',', $filter_langs) .')';
		}

		if (!empty($filter_access))
		{
			$filter_access = ArrayHelper::toInteger($filter_access);
			$where[] = 'a.access IN (' . implode( ',', $filter_access) .')';
		}


		if (!empty($filter_meta))
		{
			switch($filter_meta)
			{
				case 1: 
					$where[] = 'a.metakey = ' . $this->_db->Quote('');
					break;
				case 2: 
					$where[] = 'a.metadesc = ' . $this->_db->Quote('');
					break;
				case 3: 
					$where[] = '(a.metakey = ' . $this->_db->Quote('') . ' OR a.metadesc = ' . $this->_db->Quote('') . ')';
					break;
			}
		}


		/**
		 * Listing associated items
		 */
		$filter_assockey = $this->getState('filter_assockey');

		if ($filter_assockey)
		{
			$extra_joins .= ' JOIN #__associations AS assoc ON a.id = assoc.id AND assoc.context = ' . $this->_db->quote('com_content.item');
			$where[] = 'assoc.key = ' . $this->_db->quote($filter_assockey);
		}


		/**
		 * CUSTOM filters
		 */

		$customFilts = $this->getCustomFilts();
		$_filts_vals_clause =  array();

		foreach($customFilts as $filter)
		{
			if (!count($filter->value))
			{
				continue;
			}

			$_filts_vals_clause[] = ' (fi.field_id='.$filter->id.' AND fi.value='.$this->_db->Quote($filter->value[0]).')';
		}

		if (count($_filts_vals_clause))
		{
			$where[] = ' (' . implode(' OR ', $_filts_vals_clause).' )';
		}


		/**
		 * Filter according to search text and search scope
		 */
		$textwhere = $this->_getTextSearch();

		if ($textwhere)
		{
			$where[] = '(' . implode(' OR ', $textwhere) . ')';
		}


		/**
		 * Date range filtering (creation and/or modification)
		 */

		$nullDate = $this->_db->getNullDate();

		if ($startdate || $enddate)
		{
			$_where = array();

			switch($date)
			{
				case 1:
					if ($startdate) $_where[] = ' a.created >= ' . $this->_db->Quote($startdate);
					if ($enddate)   $_where[] = ' a.created <= ' . $this->_db->Quote($enddate);
					$where[] = '( ' . implode(' AND ', $_where) . ' )';
					break;

				case 2:
					if ($startdate)  $_where[] = '( a.modified >= ' . $this->_db->Quote($startdate) . ' OR ( a.modified = ' . $this->_db->Quote($nullDate) . ' AND a.created >= ' . $this->_db->Quote($startdate) . '))';
					if ($enddate)    $_where[] = '( a.modified <= ' . $this->_db->Quote($enddate)   . ' OR ( a.modified = ' . $this->_db->Quote($nullDate) . ' AND a.created <= ' . $this->_db->Quote($enddate) . '))';
					$where[] = '( ' . implode(' AND ', $_where) . ' )';
					break;

				case 3:
					if ($startdate) $_where[] = '( a.publish_up >= ' . $this->_db->Quote($startdate) . ' OR ( (a.publish_up IS NULL OR a.publish_up = ' . $this->_db->Quote($nullDate) . ') AND a.created >= ' . $this->_db->Quote($startdate) . '))';
					if ($enddate)   $_where[] = '( a.publish_up <= ' . $this->_db->Quote($enddate) . ' OR ( (a.publish_up IS NULL OR a.publish_up = ' . $this->_db->Quote($nullDate) . ') AND a.created >= ' . $this->_db->Quote($startdate) . '))';
					$where[] = '( ' . implode(' AND ', $_where) . ' )';
					break;

				case 4:
					// DO NOT include NULL dates !! we are 'filtering', aka looking for publish down in specific date range
					if ($startdate) $_where[] = ' a.publish_down >= ' . $this->_db->Quote($startdate);
					if ($enddate)   $_where[] = ' a.publish_down <= ' . $this->_db->Quote($enddate);
					$where[] = '( ' . implode(' AND ', $_where) . ' )';
					break;
			}
		}


		/**
		 * Finally create the AND clause of the WHERE clause
		 */

		$where = count($where)
			? ' WHERE ' . implode(' AND ', $where)
			: '';

		return $where;
	}


	/**
	 * Method to copy items
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function copyitems($cid, $keeptags = 1, $prefix = '', $suffix = '', $copynr = 1, $lang_arr = null, $state = null, $method = 1, $maincat = null, $seccats = null, $type_id = null, $access = null)
	{
		$app    = JFactory::getApplication();
		$jinput = $app->input;

		$dbprefix = $app->getCfg('dbprefix');

		$use_versioning = $this->cparams->get('use_versioning', 1);


		/**
		 * Try to find Falang/Joomfish, to import translation data, if so requested
		 */

		$_FALANG = false;
		$this->_db->setQuery('SHOW TABLES LIKE "'.$dbprefix.'falang_content"');
		$_FALANG = (boolean) count($this->_db->loadObjectList());

		// Try to find old joomfish tables (with current DB prefix)
		$this->_db->setQuery('SHOW TABLES LIKE "'.$dbprefix.'jf_content"');
		$_FISH = (boolean) count($this->_db->loadObjectList());

		// Try to find old joomfish tables (with J1.5 jos prefix)
		if (!$_FISH)
		{
			$this->_db->setQuery('SHOW TABLES LIKE "jos_jf_content"');

			if (count($this->_db->loadObjectList()))
			{
				$_FISH = true;
				$dbprefix = 'jos_';
			}
		}

		// Detect version of joomfish tables
		$_FISH22GE = false;
		if ($_FISH)
		{
			$this->_db->setQuery('SHOW TABLES LIKE "'.$dbprefix.'jf_languages_ext"');
			$_FISH22GE = (boolean) count($this->_db->loadObjectList());
		}

		$_NEW_LANG_TBL = FLEXI_J16GE || $_FISH22GE;


		// Get if translation is to be performed, 1: FLEXI_DUPLICATEORIGINAL,  2: FLEXI_USE_JF_DATA,  3: FLEXI_AUTO_TRANSLATION,  4: FLEXI_FIRST_JF_THEN_AUTO
		$translate_method = $method == 99
			? $jinput->getInt('translate_method', 1)
			: $translate_method = 0;

		// If translation method import the translator class
		if ($translate_method==3 || $translate_method==4)
		{
			require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'translator.php');
		}

		// If translation method load description field to allow some parsing according to parameters
		if ($translate_method==3 || $translate_method==4)
		{
			$this->_db->setQuery('SELECT id FROM #__flexicontent_fields WHERE name = "text" ');
			$desc_field_id = $this->_db->loadResult();
			$desc_field = JTable::getInstance('flexicontent_fields', '');
			$desc_field->load($desc_field_id);
		}


		/**
		 * Loop through the items, copying, moving, or translating them
		 */
		$cid_reverse = array_reverse($cid);
		$total_cnt = 0;
		global $globalcats;

		foreach ($cid_reverse as $itemid)
		{
			// Associations added to current item
			$assoc_data = null;
			$cat_assoc_ids = null;

			// (a) Get existing item
			$item = JTable::getInstance('flexicontent_items', '');
			$item->load($itemid);

			// Note: an empty $lang_arr means maintain item language
			$langs = $lang_arr ?: array($item->language);

			foreach($langs as $lang)
			{
				for( $nr=0; $nr < $copynr; $nr++ )  // Number of copies to create, meaningful only when copying without TRANSLATING items
				{
					// Some shortcuts
					$sourceid 	= (int)$item->id;
					$curversion = (int)$item->version;

					// (b) We create copy so that the original data are always available
					$row = clone($item);

					// (c) Force creation & assigning of new records by cleaning the primary keys
					$row->id 				= null;    // force creation of new record in _content DB table
					$row->item_id 	= null;    // force creation of new record in _flexicontent_ext DB table
					$row->asset_id 	= null;  // force creation of new record in _assets DB table

					// (d) Start altering the properties of the cloned item
					$row->title 		=
						($prefix ? str_replace('_lang_code_', $lang, $prefix) . ' ' : '') .
						$item->title .
						($suffix ? ' ' . str_replace('_lang_code_', $lang, $suffix) : '');
					$row->alias			= !$prefix && !$suffix ? $row->alias : '';
					$row->hits 			= 0;
					if (!$translate_method)  // cleared featured flag if not translating
						$row->featured = 0;
					$row->version 	= 1;
					$datenow 				= JFactory::getDate();
					$row->created 		= $method == 99 ? $item->created : $datenow->toSql();
					$row->publish_up	= $method == 99 ? $item->publish_up : $datenow->toSql();
					$row->modified 		= $nullDate = $this->_db->getNullDate();
					$lang_from			= substr($row->language,0,2);
					$row->language	= $lang ? $lang : $row->language;
					$lang_to				= substr($row->language,0,2);
					$row->state			= strlen($state) ? $state : $row->state;  // keep original if: null, ''
					$row->type_id		= $type_id ? $type_id : $row->type_id;    // keep original if: null, zero, ''
					$row->access		= $access ? $access : $row->access;       // keep original if: null, zero, ''

					$doauto['title'] = $doauto['introtext'] = $doauto['fulltext'] = $doauto['metakey'] = $doauto['metadesc'] = true;    // In case JF data is missing
					if ($translate_method == 2 || $translate_method == 4)
					{
						// a. Try to get joomfish/falang translation from the item
						$jfitemfields = false;

						if ($_FALANG) {
							$query = "SELECT c.* FROM `#__falang_content` AS c "
								." LEFT JOIN #__languages AS lg ON c.language_id=lg.lang_id"
								." WHERE c.reference_table = 'content' AND lg.lang_code='".$row->language."' AND c.reference_id = ". $sourceid;
							$this->_db->setQuery($query);
							$jfitemfields = $this->_db->loadObjectList();
						}

						if ( !$jfitemfields && $_FISH) {
							$query = "SELECT c.* FROM `".$dbprefix."jf_content` AS c "
								." LEFT JOIN #__languages AS lg ON c.language_id=".($_NEW_LANG_TBL ? "lg.lang_id" : "lg.id")
								." WHERE c.reference_table = 'content' AND ".($_NEW_LANG_TBL ? "lg.lang_code" : "lg.code")."='".$row->language."' AND c.reference_id = ". $sourceid;
							$this->_db->setQuery($query);
							$jfitemfields = $this->_db->loadObjectList();
						}

						// b. if joomfish translation found set for the new item
						if($jfitemfields) {
							$jfitemdata = new stdClass();
							foreach($jfitemfields as $jfitemfield) {
								$jfitemdata->{$jfitemfield->reference_field} = $jfitemfield->value;
							}

							if (isset($jfitemdata->title) && StringHelper::strlen($jfitemdata->title)>0){
								$row->title = $jfitemdata->title;
								$doauto['title'] = false;
							}

							if (isset($jfitemdata->alias) && StringHelper::strlen($jfitemdata->alias)>0) {
								$row->alias = $jfitemdata->alias;
							}

							if (isset($jfitemdata->introtext) && StringHelper::strlen(strip_tags($jfitemdata->introtext))>0) {
								$row->introtext = $jfitemdata->introtext;
								$doauto['introtext'] = false;
							}

							if (isset($jfitemdata->fulltext) && StringHelper::strlen(strip_tags($jfitemdata->fulltext))>0) {
								$row->fulltext = $jfitemdata->fulltext;
								$doauto['fulltext'] = false;
							}

							if (isset($jfitemdata->metakey) && StringHelper::strlen($jfitemdata->metakey)>0) {
								$row->metakey = $jfitemdata->metakey;
								$doauto['metakey'] = false;
							}

							if (isset($jfitemdata->metadesc) && StringHelper::strlen($jfitemdata->metadesc)>0) {
								$row->metadesc = $jfitemdata->metadesc;
								$doauto['metadesc'] = false;
							}
						}
					}


					// Try to do automatic translation from the item, if autotranslate is SET and --NOT found-- or --NOT using-- JoomFish Data
					if ($translate_method == 3 || $translate_method == 4)
					{
						// Translate fulltext item property, using the function for which handles custom fields TYPES: text, textarea, ETC
						if ($doauto['fulltext'])
						{
							$desc_field->value = $row->fulltext;
							$fields = array( &$desc_field );
							$this->translateFieldValues( $fields, $row, $lang_from, $lang_to);
							$row->fulltext = $desc_field->value;
						}

						// TRANSLATE basic item properties (if not already imported via Joomfish)
						$translatables = array('title', 'introtext', 'metakey', 'metadesc');

						$fieldnames_arr = array();
						$fieldvalues_arr = array();
						foreach($translatables as $translatable)
						{
							if ( !$doauto[$translatable] ) continue;

							$fieldnames_arr[] = $translatable;
							$translatable_obj = new stdClass();
							$translatable_obj->originalValue = $row->{$translatable};
							$translatable_obj->noTranslate = false;
							$fieldvalues_arr[] = $translatable_obj;
						}

						if (count($fieldvalues_arr))
						{
							$result = autoTranslator::translateItem($fieldnames_arr, $fieldvalues_arr, $lang_from, $lang_to);

							if (intval($result))
							{
								$n = 0;
								foreach($fieldnames_arr as $fieldname)
								{
									$row->{$fieldname} = $fieldvalues_arr[$n]->translationValue;
									$n++;
								}
							}
						}
					}
					//print_r($row->fulltext); exit;

					// Check new item
					$row->check();

					// Create a new item in the content fc_items_ext table
					$row->store();

					// Not doing a translation, we start a new language group for the new item
					if ($translate_method == 0)
					{
						$row->lang_parent_id = 0; //$row->id;
						$row->store();
					}


					/**
					 * Copy custom fields, translating the fields if so configured
					 */

					$doTranslation = $translate_method == 3 || $translate_method == 4;
					$query 	= 'SELECT fir.*, f.* '
							. ' FROM #__flexicontent_fields_item_relations as fir'
							. ' LEFT JOIN #__flexicontent_fields as f ON f.id=fir.field_id'
							. ' WHERE item_id = '. $sourceid
							;
					$this->_db->setQuery($query);
					$fields = $this->_db->loadObjectList();
					//echo "<pre>"; print_r($fields); exit;

					if ($doTranslation)  $this->translateFieldValues( $fields, $row, $lang_from, $lang_to);
					//foreach ($fields as $field)  if ($field->field_type!='text' && $field->field_type!='textarea') { print_r($field->value); echo "<br><br>"; }

					foreach($fields as $field)
					{
						if (strlen($field->value))
						{
							$field->item_id = $row->id;
							$query 	= 'INSERT INTO #__flexicontent_fields_item_relations (`field_id`, `item_id`, `valueorder`, `suborder`, `value`)'
								. ' VALUES(' . $field->field_id . ', ' . $field->item_id . ', ' . $field->valueorder . ', ' . $field->suborder . ', ' . $this->_db->Quote($field->value)
								. ')'
								;
							$this->_db->setQuery($query)->execute();
							flexicontent_db::setValues_commonDataTypes($field);
						}
					}

					if ($use_versioning)
					{
						$v = new stdClass();
						$v->item_id    = (int)$item->id;
						$v->version_id = 1;
						$v->created    = $item->created;
						$v->created_by = $item->created_by;
						$v->comment    = ''; //'copy version.';
						$this->_db->insertObject('#__flexicontent_versions', $v);
					}

					// Get the items versions
					$query 	= 'SELECT *'
							. ' FROM #__flexicontent_items_versions'
							. ' WHERE item_id = '. $sourceid
							. ' AND version = ' . $curversion
							;
					$curversions = $this->_db->setQuery($query)->loadObjectList();

					foreach ($curversions as $cv)
					{
						$query 	= 'INSERT INTO #__flexicontent_items_versions (`version`, `field_id`, `item_id`, `valueorder`, `suborder`, `value`)'
							. ' VALUES(1 ,'  . $cv->field_id . ', ' . $row->id . ', ' . $cv->valueorder . ', ' . $cv->suborder . ', ' . $this->_db->Quote($cv->value)
							. ')'
							;
						$this->_db->setQuery($query)->execute();
					}

					// Get the item categories
					$query 	= 'SELECT catid'
							. ' FROM #__flexicontent_cats_item_relations'
							. ' WHERE itemid = '. $sourceid
							;
					$cats = $this->_db->setQuery($query)->loadColumn();

					foreach($cats as $cat)
					{
						$query 	= 'INSERT INTO #__flexicontent_cats_item_relations (`catid`, `itemid`)'
								.' VALUES(' . $cat . ',' . $row->id . ')'
								;
						$this->_db->setQuery($query)->execute();
					}

					if ($keeptags)
					{
						// get the item tags
						$query 	= 'SELECT tid'
								. ' FROM #__flexicontent_tags_item_relations'
								. ' WHERE itemid = '. $sourceid
								;
						$tags = $this->_db->setQuery($query)->loadColumn();

						foreach($tags as $tag)
						{
							$query 	= 'INSERT INTO #__flexicontent_tags_item_relations (`tid`, `itemid`)'
									.' VALUES(' . $tag . ',' . $row->id . ')'
									;
							$this->_db->setQuery($query)->execute();
						}
					}

					if ($method == 3)
					{
						$this->moveitem($row->id, $maincat, $seccats);
					}
					elseif ($method == 99 && ($maincat || $seccats))
					{
						/**
						 * Auto-selected associated category or use same category (of language ALL) as the original item
						 * NOTE: the quicktranslate task should prevent selecting a language that has no associated category
						 * No check is done here if a language has indeed ab associated category. If not the item will be created
						 * in the same category as the original item
						 */
						if ($maincat == -99)
						{
							// Get associations of item that was duplicated
							if ($cat_assoc_ids === null)
							{
								$cat_assoc_ids = array();

								// Existing associations of the item's category
								if (isset($globalcats[$item->catid]) && $globalcats[$item->catid]->language !== '*')
								{
									$cat_associations = JLanguageAssociations::getAssociations('com_content', '#__categories', 'com_categories.item', $item->catid, 'id', 'alias', '');
									foreach ($cat_associations as $tag => $cat_association)
									{
										if (isset($globalcats[(int)$cat_association->id]))
										{
											$cat_assoc_ids[$tag] = (int)$cat_association->id;
										}
									}
								}
							}

							if (isset($cat_assoc_ids[$row->language]))
							{
								$row->catid = $cat_assoc_ids[$row->language];
							}
						}
						else
						{
							$row->catid = $maincat ? $maincat : $row->catid;
						}

						$this->moveitem($row->id, $row->catid, $seccats);
					}

					/**
					 * If new item is a tranlation, load the language associations of item
					 * that was copied, and save the associations, adding the new item to them
					 */
					if ($method == 99 && $item->language!='*' && $row->language!='*' && flexicontent_db::useAssociations())
					{
						// Get associations of item that was duplicated
						if ($assoc_data === null)
						{
							// Note: JLanguageAssociations::getAssociations returns cached data
							$associations = JLanguageAssociations::getAssociations('com_content', '#__content', 'com_content.item', $item->id);
							$assoc_data = array();
							foreach ($associations as $tag => $association)
							{
								$assoc_data['associations'][$tag] = (int)$association->id;
							}
						}
						$assoc_data['associations'][$row->language]  = $row->id;  // Add new item itself
						$assoc_data['associations'][$item->language] = $item->id; // Add current item (needed if association group is empty)
					}
					$total_cnt++;
				}
			}

			// Save new associations for current item
			if ($assoc_data)
			{
				flexicontent_db::saveAssociations($item, $assoc_data, $_context = 'com_content.item');
			}
		}
		return $total_cnt;
	}


	function translateFieldValues( &$fields, &$row, $lang_from, $lang_to )
	{
		// Translate 'text' TYPE fields
		$fieldnames_arr = array();
		$fieldvalues_arr = array();
		foreach($fields as $field_index => $field)
		{
			if ( $field->field_type!='text' ) continue;
			$fieldnames_arr[] = 'field_value'.$field_index;
			$translatable_obj = new stdClass();
			$translatable_obj->originalValue = $field->value;
			$translatable_obj->noTranslate = false;
			$fieldvalues_arr[] = $translatable_obj;
		}

		if (count($fieldvalues_arr)) {
			$result = autoTranslator::translateItem($fieldnames_arr, $fieldvalues_arr, $lang_from, $lang_to);

			if (intval($result)) {
				foreach($fieldnames_arr as $index => $fieldname) {
					$field_index = str_replace('field_value', '', $fieldname);
					$fields[$field_index]->value = $fieldvalues_arr[$index]->translationValue;
				}
			}
		}

		// Translate 'textarea' TYPE fields
		$fieldnames_arr = array();
		$fieldvalues_arr = array();
		foreach($fields as $field_index => $field)
		{
			if ( $field->field_type!='textarea' && $field->field_type!='maintext' ) continue;
			if ( !is_array($field->value) ) $field->value = array($field->value);

			// Load field parameters
			FlexicontentFields::loadFieldConfig($field, $row);

			// Parse fulltext field into tabs to avoid destroying them during translation
			FLEXIUtilities::call_FC_Field_Func('textarea', 'parseTabs', array(&$field, &$row) );
			$dti = & $field->tab_info;

			if ( !$field->tabs_detected ) {
				$fieldnames_arr[] = $field->name;
				$translatable_obj = new stdClass();
				$translatable_obj->originalValue = $field->value[0];
				$translatable_obj->noTranslate = false;
				$fieldvalues_arr[] = $translatable_obj;
			} else {
				// BEFORE tabs
				$fieldnames_arr[] = 'beforetabs';
				$translatable_obj = new stdClass();
				$translatable_obj->originalValue = $dti->beforetabs;
				$translatable_obj->noTranslate = false;
				$fieldvalues_arr[] = $translatable_obj;

				// AFTER tabs
				$fieldnames_arr[] = 'aftertabs';
				$translatable_obj = new stdClass();
				$translatable_obj->originalValue = $dti->aftertabs;
				$translatable_obj->noTranslate = false;
				$fieldvalues_arr[] = $translatable_obj;

				// TAB titles
				foreach($dti->tab_titles as $i => $tab_title) {
					$fieldnames_arr[] = 'tab_titles_'.$i;
					$translatable_obj = new stdClass();
					$translatable_obj->originalValue = $tab_title;
					$translatable_obj->noTranslate = false;
					$fieldvalues_arr[] = $translatable_obj;
				}

				// TAB contents
				foreach($dti->tab_contents as $i => $tab_content) {
					$fieldnames_arr[] = 'tab_contents_'.$i;
					$translatable_obj = new stdClass();
					$translatable_obj->originalValue = $tab_content;
					$translatable_obj->noTranslate = false;
					$fieldvalues_arr[] = $translatable_obj;
				}
			}

			// Do Google Translation
			unset($translated_parts);
			if (count($fieldvalues_arr)) {
				$result = autoTranslator::translateItem($fieldnames_arr, $fieldvalues_arr, $lang_from, $lang_to);

				if (intval($result)) {
					$translated_parts = new stdClass();
					foreach($fieldnames_arr as $index => $fieldname) {
						$translated_parts->{$fieldname} = $fieldvalues_arr[$index]->translationValue;
					}
				}
			}
			//echo "<pre>"; print_r($translated_parts);

			// Reconstruct field value out of the translated tabs code and assign it back to the field
			if (isset($translated_parts)) {
				if (!$field->tabs_detected ) {
					$fields[$field_index]->value = $translated_parts->{$field->name};
				} else {
					$translated_value  = $translated_parts->beforetabs;
					$translated_value .= $dti->tabs_start;
					foreach ( $dti->tab_titles as $i => $tab_title ) {
						$translated_value .= str_replace( $tab_title, $translated_parts->{'tab_titles_'.$i}, $dti->tab_startings[$i]);
						$translated_value .= $translated_parts->{'tab_contents_'.$i};
						$translated_value .= $dti->tab_endings[$i];
					}
					$translated_value .= $dti->tabs_end;
					$translated_value .= $translated_parts->aftertabs;

					// Assign translated value back to the field
					$fields[$field_index]->value = $translated_value;
				}
			} else {
				// no translation performed, or translation unsuccessful
				$field->value = $field->value[0];
			}
		}

	}



	/**
	 * Method to copy items
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function moveitem($itemid, $maincat, $seccats = null, $lang = null, $state = null, $type_id = 0, $access = null)
	{
		$item = $this->getTable($this->records_jtable, '');
		$item->load($itemid);

		// Keep original if: null, zero, ''
		$item->catid    = $maincat ?: $item->catid;
		$item->language = $lang ?: $item->language;
		$item->type_id  = $type_id ?: $item->type_id;
		$item->access   = $access ?: $item->access;

		// keep original if: null, ''
		$item->state = strlen($state) ? $state : $item->state;

		$item->store();

		if ($seccats === null)
		{
			// draw an array of the item categories
			$query 	= 'SELECT catid'
					. ' FROM #__flexicontent_cats_item_relations'
					. ' WHERE itemid = '.$itemid
					;
			$this->_db->setQuery($query);
			$seccats = $this->_db->loadColumn();
		}

		// Add the primary cat to the array if it's not already in
		if (!in_array($item->catid, $seccats))
		{
			$seccats[] = $item->catid;
		}

		//At least one category needs to be assigned
		if (!is_array( $seccats ) || count( $seccats ) < 1)
		{
			$this->setError(JText::_('FLEXI_SELECT_CATEGORY'));
			return false;
		}

		// delete old relations
		$query 	= 'DELETE FROM #__flexicontent_cats_item_relations'
				. ' WHERE itemid = '.$itemid
				;
		$this->_db->setQuery($query);
		$this->_db->execute();

		foreach($seccats as $cat)
		{
			$query 	= 'INSERT INTO #__flexicontent_cats_item_relations (`catid`, `itemid`)'
					.' VALUES(' . $cat . ',' . $itemid . ')'
					;
			$this->_db->setQuery($query);
			$this->_db->execute();
		}

		// update version table
		if ($seccats) {
			$query 	= 'UPDATE #__flexicontent_items_versions SET value = ' . $this->_db->Quote(serialize($seccats))
					. ' WHERE version = 1'
					. ' AND item_id = ' . (int)$itemid
					. ' AND field_id = 13'
					. ' AND valueorder = 1'
					. ' AND suborder = 1'
					;
			$this->_db->setQuery($query);
			$this->_db->execute();
		}

		return true;
	}


	/**
	 * Method to notification to the validators for an item
	 *
	 * @access	public
	 * @params	object		the user object
	 * @params	object		the item object
	 * @return	boolean		true on success
	 * @since	1.5
	 */
	function sendNotification($users, $item)
	{
		$sender = JFactory::getUser();

		// messaging for new items
		require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_messages'.DS.'tables'.DS.'message.php');

		// load language for messaging
		$lang = JFactory::getLanguage();
		$lang->load('com_messages');

		$ctrl_task = FLEXI_J16GE ? '&task=items.edit' : '&controller=items&task=edit';
		$item->url = JUri::base(true) . '/index.php?option=com_flexicontent'.$ctrl_task .'&cid[]=' . $item->id;

		foreach ($users as $user)
		{
			$msg = new TableMessage($this->_db);
			$msg->send($sender->get('id'), $user->member_id, JText::_('FLEXI_APPROVAL_REQUEST'), JText::sprintf('FLEXI_APPROVAL_MESSAGE', $user->name, $sender->get('name'), $sender->get('username'), $item->id, $item->title, $item->cattitle, $item->url));
		}
		return true;
	}


	/**
	 * Method to move a record upwards or downwards
	 *
	 * @param   integer   $direction   A value of 1  or -1 to indicate moving up or down respectively
	 * @param   integer   $catid       The ID of the category being reorder, needed as items are assigned to multiple categories
	 *
	 * @return	boolean	  True on success
	 *
	 * @since	3.3.0
	 */
	public function move($direction, $catid)
	{
		// Load the moved record
		$table = $this->getTable($this->records_jtable, '');

		if (!$table->load($this->_id))
		{
			$this->setError($table->getError());
			return false;
		}

		$stategrps = array(
			 1 => 'published',
			 0 => 'unpublished',
			-2 => 'trashed',
			-3 => 'unpublished',
			-4 => 'unpublished',
			-5 => 'published',
		);
		$row_stategrp = isset($stategrps[$table->state])
			? $stategrps[$table->state]
			: null;

		// Every state group has different ordering
		switch ($row_stategrp)
		{
			case 'published':
				$item_states = JText::_('FLEXI_GRP_PUBLISHED');
				$state_where = 'state IN (1,-5)';
				break;
			case 'unpublished':
				$item_states = JText::_('FLEXI_GRP_UNPUBLISHED');
				$state_where = 'state IN (0,-3,-4)';
				break;
			case 'trashed':
				$item_states = JText::_('FLEXI_GRP_TRASHED');
				$state_where = 'state = -2';
				break;
			case 'archived':
				$item_states = JText::_('FLEXI_GRP_ARCHIVED');
				$state_where = 'state = 2';
				break;
			default:
				$this->setError('Item state seems to be unknown. Ordering groups include items in state groups: (a) published (b) unpublished (c) archived (d) trashed');
				return false;
		}

		$filter_order_type = $this->getState('filter_order_type');
		$multi_assigns_ordering = (boolean) $filter_order_type;

		// Correct direction according to current value of the 'direction' filter
		$direction = strtolower($this->getState('filter_order_Dir')) == 'desc' ? - $direction : $direction;


		/**
		 * CASE 1
		 *
		 * Joomla ordering (main category only), use ordering column inside DB table itself
		 * do reordering via JTable calls
		 * but instead of (catid) only ordering groups, we will use (catid, state-group, language)
		 */

		if (!$multi_assigns_ordering)
		{
			// Ignore passed $catid as NA
			$catid = $table->catid;

			$where = array(
				'catid = ' . (int) $catid,
				$state_where,
				'language = ' . $this->_db->Quote($table->language),
			);

			// Access check must done at the controller code for component level ACL 'flexicontent.orderitems'
			if (!$table->move($direction, $where))
			{
				$this->setError($table->getError());
				return false;
			}

			return true;
		}


		/**
		 * CASE 2
		 *
		 * FC per category ordering (multi-cats assignments), use ordering column at the item-categories relation DB table
		 * we will not use JTable calls for changing order,
		 * instead we will use custom optimized queries to update multiple records at once
		 */

		else
		{
			/**
			 * Verify currently moved item exists in given category and also get its current ordering value
			 */
			$catid = $catid ?: $table->catid;

			$query = $this->_db->getQuery(true)
				->select('rel.itemid, rel.ordering, a.state, a.language')
				->from('#__flexicontent_cats_item_relations AS rel')
				->innerJoin('#__content AS a ON a.id = rel.itemid')
				->where('rel.catid = ' . (int) $catid)
				->where('rel.itemid = ' . (int) $this->_id)
			;
			$origin = $this->_db->setQuery($query, 0, 1)->loadObject();

			if (!$origin)
			{
				$this->setError('Item to move is not assigned to given category');
				return false;
			}

			/**
			 * Find the NEXT or PREVIOUS item having same (catid, state group, language), to use it for swapping the ordering numbers
			 */

			$query
				->clear('where')
				->where(array(
					'rel.catid = ' . $catid,
					$state_where,
					'a.language = ' . $this->_db->Quote($table->language),
				));

			if ($direction < 0)
			{
				$query
					->where('rel.ordering >= 0 AND rel.ordering < ' . (int) $origin->ordering)
					->order('ordering DESC');
			}
			elseif ($direction > 0)
			{
				$query
					->where('rel.ordering >= 0 AND rel.ordering > ' . (int) $origin->ordering)
					->order('ordering ASC');
			}

			$row = $this->_db->setQuery($query, 0, 1)->loadObject();

			/**
			 * NEXT or PREVIOUS record found, swap its order with currently moved record
			 */
			if (isset($row))
			{
				$query = $this->_db->getQuery(true)
					->update('#__flexicontent_cats_item_relations')
					->set('ordering = ' . (int) $row->ordering)
					->where('itemid = ' . (int) $origin->itemid)
					->where('catid = ' . $catid)
				;
				$this->_db->setQuery($query)->execute();

				$query = $this->_db->getQuery(true)
					->update('#__flexicontent_cats_item_relations')
					->set('ordering = ' . (int) $origin->ordering)
					->where('itemid = ' . (int) $row->itemid)
					->where('catid = ' . $catid)
				;
				$this->_db->setQuery($query)->execute();
			}

			/**
			 * NEXT or PREVIOUS record NOT found, raise a notice
			 */
			else
			{
				JFactory::getApplication()->enqueueMessage(
					JText::_('Previous/Next record was not found or has same ordering, trying saving ordering once to create incrementing unique ordering numbers'),
					'notice'
				);
			}

			return true;
		}
	}


	/**
	 * Saves the manually set order of records.
	 *
	 * @param   array     $pks        An array of primary key ids
	 * @param   array     $order      An array of new ordering values
	 * @param   integer   $catid      The ID of the category being reorder, needed as items are assigned to multiple categories
	 *
	 * @return  boolean   Boolean true on success, false on failure
	 *
	 * @since   3.3.0
	 */
	public function saveorder($pks, $order, $catid = 0)
	{
		$app = JFactory::getApplication();

		$filter_order_type = $this->getState('filter_order_type');

		$state_grp_arr = array(
			 1 => 'published',
			 0 => 'unpublished',
			 2 => 'archived',
			-2 => 'trashed',
			-3 => 'unpublished',
			-4 => 'unpublished',
			-5 => 'published',
		);

		$state_where_clauses = array(
			'published'   => 'state IN (1, -5)',
			'unpublished' => 'state IN (0,-3,-4)',
			'trashed'     => 'state = -2',
			'archived'    => 'state = 2',
			''            => 'state NOT IN (2, 1, 0, -2, -3, -4, -5)',
		);


		/**
		 * CASE 1
		 *
		 * Joomla ordering (main category only), use ordering column inside DB table itself
		 * do reordering via JTable calls
		 * but instead of (catid) only ordering groups, we will use (catid, state-group, language)
		 */

		if (!$filter_order_type)
		{
			// Access check must done at the controller code for component level ACL 'flexicontent.orderitems'
			$table = $this->getTable($this->records_jtable, '');

			// Update ordering values
			$recompact_grps = array();
			$ord_count      = array();

			for ($i = 0; $i < count($pks); $i++)
			{
				$table->load((int) $pks[$i]);

				$row_stategrp = isset($state_grp_arr[$table->state])
					? $state_grp_arr[$table->state]
					: null;

				// Save JTable record only if ordering differs
				if ($table->ordering != $order[$i])
				{
					$recompact_grps[$table->catid][$row_stategrp][$table->language] = 1;
					$table->ordering = $order[$i];

					if (!$table->store())
					{
						$this->setError($table->getError());

						return false;
					}
				}

				/**
				 * Detect group with duplicate orderings, to force a JTable:reorder() call
				 */
				else
				{
					$_cid = $table->catid;
					$_ord = $table->ordering;

					if (isset($ord_count[$_cid][$row_stategrp][$table->language][$_ord]))
					{
						$recompact_grps[$_cid][$row_stategrp][$table->language] = 1;
						$ord_count[$_cid][$row_stategrp][$table->language][$_ord] ++;
					}
					else
					{
						$ord_count[$_cid][$row_stategrp][$table->language][$_ord] = 1;
					}
				}
			}

			/**
			 * Compact the ordering numbers
			 */
			foreach ($recompact_grps as $reorder_catid => $state_groups)
			{
				foreach ($state_groups as $state_group => $lang_groups)
				{
					foreach ($lang_groups as $lang_group => $ignore)
					{
						$table->reorder(array(
							'catid = ' . $reorder_catid,
							$state_where_clauses[$state_group],
							'language = ' . $this->_db->Quote($lang_group),
						));

						// Note: 'FLEXI_ITEM_REORDER_GROUP_RESULTS' should be used if grouping with where-language is not included
						$app->enqueueMessage(JText::sprintf('FLEXI_ITEM_REORDER_GROUP_RESULTS_LANG', JText::_('FLEXI_ORDER_JOOMLA_GLOBAL'), $state_group, $lang_group, $reorder_catid), 'message');
					}
				}
			}

			return true;
		}


		/**
		 * CASE 2
		 *
		 * FC per category ordering (multi-cats assignments), use ordering column at the item-categories relation DB table
		 * we will not use JTable calls for changing order,
		 * instead we will use custom optimized queries to update multiple records at once
		 */

		else
		{
			$recompact_grps = array();
			$ord_count      = array();

			/**
			 * Update the record-to-group relations tableusing the given ordering numbers
			 * TODO: merge this with the re-compacting (reordering) code ...
			 */
			$new_order_wheres = array();

			for ($i = 0; $i < count($pks); $i++)
			{
				$query = $this->_db->getQuery(true)
					->select('rel.itemid, rel.ordering, a.state, a.language')
					->from('#__flexicontent_cats_item_relations AS rel')
					->innerJoin('#__content AS a ON a.id = rel.itemid')
					->where('rel.ordering >= 0')
					->where('rel.catid = ' . (int) $catid)
					->where('rel.itemid = ' . (int) $pks[$i])
				;
				$table = $this->_db->setQuery($query, 0, 1)->loadObject();

				$row_stategrp = isset($state_grp_arr[$table->state])
					? $state_grp_arr[$table->state]
					: null;

				if ($table->ordering != $order[$i])
				{
					$where_case = 'catid = ' . (int) $catid . ' AND itemid = ' . (int) $pks[$i];
					$new_order_wheres[$where_case] = ' WHEN ' . $where_case . ' THEN ' .  (int) $order[$i];

					$recompact_grps[$catid][$row_stategrp][$table->language] = 1;
				}

				// Detect columns with duplicate orderings, to force reordering them
				else
				{
					$_cid = $catid;
					$_ord = $table->ordering;

					if (isset($ord_count[$_cid][$row_stategrp][$table->language][$_ord]))
					{
						$recompact_grps[$_cid][$row_stategrp][$table->language] = 1;
						$ord_count[$_cid][$row_stategrp][$table->language][$_ord] ++;
					}
					else
					{
						$ord_count[$_cid][$row_stategrp][$table->language][$_ord] = 1;
					}
				}
			}

			if (count($new_order_wheres))
			{
				$query = $this->_db->getQuery(true)
					->update('#__flexicontent_cats_item_relations')
					->set('ordering = CASE ' . implode(' ', $new_order_wheres) . ' END ')
					->where('(' . implode(') OR (', array_keys($new_order_wheres)) . ')');
				$this->_db->setQuery($query)->execute();
			}

			/**
			 * Do 1 query per ordering group to find groups that need to compated (reordered)
			 * we only have 1 category with a few states and languages, so this is only a small number of queries
			 */
			$new_order_wheres = array();

			foreach ($recompact_grps as $reorder_catid => $state_groups)
			{
				foreach ($state_groups as $state_group => $lang_groups)
				{
					foreach ($lang_groups as $lang_group => $ignore)
					{
						// Specific reorder procedure because the relations table has a composite primary key
						$query = $this->_db->getQuery(true)
							->select('rel.itemid, rel.ordering, a.state, a.language')
							->from('#__flexicontent_cats_item_relations AS rel')
							->innerJoin('#__content AS a ON a.id = rel.itemid')
							->where('rel.ordering >= 0')
							->where('rel.catid = ' . (int) $reorder_catid)
							->where($state_where_clauses[$state_group])
							->where('a.language = ' . $this->_db->Quote($lang_group))
							->order('rel.ordering')
						;
						$rows = $this->_db->setQuery($query)->loadObjectList();

						// Compact the ordering numbers
						$n = 0;

						foreach ($rows as $table)
						{
							if ($table->ordering >= 0)
							{
								if ($table->ordering != $n)
								{
									$table->ordering = $n;

									$where_case = 'catid = ' . (int) $reorder_catid . ' AND itemid = ' . (int) $table->itemid;
									$new_order_wheres[$where_case] = ' WHEN ' . $where_case . ' THEN ' .  (int) $table->ordering;
								}

								$n++;
							}
						}

						// Note: 'FLEXI_ITEM_REORDER_GROUP_RESULTS' should be used if grouping with where-language is not included
						$lang = $lang_group === '*'
							? JText::_('FLEXI_ALL')
							: $lang_group;
						$app->enqueueMessage(JText::sprintf('FLEXI_ITEM_REORDER_GROUP_RESULTS_LANG', JText::_('FLEXI_ORDER_FC_PER_CATEGORY'), $state_group, $lang, $reorder_catid), 'message');
					}
				}
			}

			if (count($new_order_wheres))
			{
				$query = $this->_db->getQuery(true)
					->update('#__flexicontent_cats_item_relations')
					->set('ordering = CASE ' . implode(' ', $new_order_wheres) . ' END ')
					->where('(' . implode(') OR (', array_keys($new_order_wheres)) . ')')
				;
				$this->_db->setQuery($query)->execute();
			}

			return true;
		}
	}




	/**
	 * Method to remove records
	 *
	 * @param		array			$cid          array of record ids to delete
	 *
	 * @return	boolean	True on success
	 *
	 * @since	1.0
	 */
	public function delete($cid, $model = null)
	{
		if ( !count( $cid ) ) return false;

		$cid = ArrayHelper::toInteger($cid);
		$cid_list = implode( ',', $cid );

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
				$record = $model->getItem($record_id);
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

			// Trigger Event 'onBeforeDeleteField' to allow fields to cleanup any custom data
			$fields = $model->getExtrafields($force=true);
			foreach ($fields as $field)
			{
				$field_type = $field->iscore ? 'core' : $field->field_type;
				FLEXIUtilities::call_FC_Field_Func($field_type, 'onBeforeDeleteField', array( &$field, &$record ));
			}
		}

		/**
		 * Delete language association, before deleting the items
		 */
		foreach($record_arr as $record)
		{
			$query = $this->_db->getQuery(true)
				->delete('#__associations')
				->where($this->_db->quoteName('context') . ' = ' . $this->_db->quote('com_content.item'))
				->where($this->_db->quoteName('id') . ' = ' . $this->_db->quote($record->id));
			$this->_db->setQuery($query)->execute();
		}


		// ***
		// *** Retrieve asset before deleting the items
		// ***
		$query = $this->_db->getQuery(true)
			->select('asset_id')
			->from('#__content')
			->where('id IN (' . $cid_list . ')');

		$assetids = $this->_db->setQuery($query)->loadColumn();
		$assetidslist = implode(',', $assetids );


		// ***
		// *** Remove basic item data
		// ***
		$query = 'DELETE FROM #__content'
				. ' WHERE id IN ('. $cid_list .')'
				;
		$this->_db->setQuery($query)->execute();


		// ***
		// *** Remove extended item data
		// ***
		$query = 'DELETE FROM #__flexicontent_items_ext'
				. ' WHERE item_id IN ('. $cid_list .')'
				;
		$this->_db->setQuery($query)->execute();


		// ***
		// *** Remove temporary item data
		// ***
		$query = 'DELETE FROM #__flexicontent_items_tmp'
				. ' WHERE id IN ('. $cid_list .')'
				;
		$this->_db->setQuery($query)->execute();


		// ***
		// *** Remove assigned tag references
		// ***
		$query = 'DELETE FROM #__flexicontent_tags_item_relations'
				.' WHERE itemid IN ('. $cid_list .')'
				;
		$this->_db->setQuery($query)->execute();


		// ***
		// *** Remove Joomla Tag assignments
		// ***
		$query = 'DELETE FROM #__contentitem_tag_map'
				. ' WHERE content_item_id IN ('. $cid_list .')'
				. '	  AND type_alias = ' . $this->_db->Quote('com_content.article')
				;
		$this->_db->setQuery($query)->execute();


		// ***
		// *** Remove assigned category references
		// ***
		$query = 'DELETE FROM #__flexicontent_cats_item_relations'
				.' WHERE itemid IN ('. $cid_list .')'
				;
		$this->_db->setQuery($query)->execute();


		// ***
		// *** Delete field data in flexicontent_fields_item_relations DB Table
		// ***
		$query = 'DELETE FROM #__flexicontent_fields_item_relations'
				. ' WHERE item_id IN ('. $cid_list .')'
				;
		$this->_db->setQuery($query)->execute();


		// ***
		// *** Delete VERSIONED field data in flexicontent_fields_item_relations DB Table
		// ***
		$query = 'DELETE FROM #__flexicontent_items_versions'
				. ' WHERE item_id IN ('. $cid_list .')'
				;
		$this->_db->setQuery($query)->execute();


		// ***
		// *** Delete item version METADATA
		// ***
		$query = 'DELETE FROM #__flexicontent_versions'
				. ' WHERE item_id IN ('. $cid_list .')'
				;
		$this->_db->setQuery($query)->execute();


		// ***
		// *** Delete favoured records of the item
		// ***
		$query = 'DELETE FROM #__flexicontent_favourites'
				. ' WHERE itemid IN ('. $cid_list .')'
				;
		$this->_db->setQuery($query)->execute();


		// ***
		// *** Delete item asset/ACL records
		// ***
		$query 	= 'DELETE FROM #__assets'
			. ' WHERE id in ('.$assetidslist.')'
			;
		//$this->_db->setQuery($query)->execute();


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
	 * Method to fetch the assigned categories
	 *
	 * @access	public
	 * @return	object
	 * @since	1.0
	 */
	function getItemCats($catids=null)
	{
		if ($this->_cats !== null) return $this->_cats;

		if (empty($catids)) $catids = $this->_catids;
		if (empty($catids)) return array();

		$query = 'SELECT DISTINCT c.id, c.title'
				. ' FROM #__categories AS c'
				. ' WHERE c.id IN ('. implode(',', $catids) .') '
				. (FLEXI_J16GE ? ' AND c.extension="'.FLEXI_CAT_EXTENSION.'"' : '')
				;
		$this->_db->setQuery( $query );
		$this->_cats = $this->_db->loadObjectList('id');
		//print_r($this->_cats);

		return $this->_cats;
	}


	/**
	 * Method to fetch the assigned categories
	 *
	 * @access	public
	 * @return	object
	 * @since	1.0
	 */
	function getItemTags($tagids=null)
	{
		if ($this->_tags !== null) return $this->_tags;

		if (empty($tagids)) $tagids = $this->_tagids;
		if (empty($tagids)) return array();

		$query = 'SELECT DISTINCT t.*'
				. ' FROM #__flexicontent_tags AS t'
				. ' WHERE t.id IN ('. implode(',', $tagids) .') '
				;
		$this->_db->setQuery( $query );
		$this->_tags = $this->_db->loadObjectList('id');
		//print_r($this->_cats);

		return $this->_tags;
	}


	/**
	 * Method to get ids of all files
	 *
	 * @access	public
	 * @return	boolean	integer array on success
	 * @since	1.0
	 */
	function getItemsWithTags(&$total=null, $start=0, $limit=100000)
	{
		$query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT a.id '
			. ' FROM #__content AS a'
			. ' JOIN #__flexicontent_tags_item_relations AS tg ON a.id = tg.itemid'
			. ($limit ? ' LIMIT ' . (int) $start . ', ' . (int) $limit : '')
			;
		$item_ids = $this->_db->setQuery($query)->loadColumn();

		// Get items total
		$total = $this->_db->setQuery('SELECT FOUND_ROWS()')->loadResult();

		return $item_ids;
	}



	/**
	 * Method to get ids of all files
	 *
	 * @access	public
	 * @return	boolean	integer array on success
	 * @since	1.0
	 */
	function getAllItems(&$total=null, $start=0, $limit=100000)
	{
		$query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT a.id '
			. ' FROM #__content AS a'
			. ($limit ? ' LIMIT ' . (int) $start . ', ' . (int) $limit : '')
			;
		$item_ids = $this->_db->setQuery($query)->loadColumn();

		// Get items total
		$total = $this->_db->setQuery('SELECT FOUND_ROWS()')->loadResult();

		return $item_ids;
	}


	/**
	 * Method to get the name of the author of an item
	 *
	 * @access	public
	 * @return	object
	 * @since	1.5
	 */
	function getAuthor($createdby)
	{
		$query = 'SELECT u.name'
				. ' FROM #__users AS u'
				. ' WHERE u.id = '.(int) $createdby
				;

		$this->_db->setQuery( $query );

		return $this->_db->loadResult();
	}


	/**
	 * Method to get types list
	 *
	 * @return array
	 * @since 1.5
	 */
	function getTypeslist ($type_ids = false, $check_perms = false, $published = true)
	{
		return flexicontent_html::getTypesList($type_ids, $check_perms, $published);
	}


	/**
	 * Method to get attributes and other data of types in types filter
	 *
	 * @return array
	 * @since 1.5
	 */
	function getTypesFromFilter()
	{
		static $types = null;
		if ($types !== null) return $types;

		$filter_type = $this->getState('filter_type');
		$filter_type = ArrayHelper::toInteger($filter_type);

		if ( empty($filter_type) ) return array();

		// Get the extra fields from COMPONENT OR per type if TYPE FILTER is active
		$types = flexicontent_html::getTypesList( $filter_type, $check_perms=false, $published=false);
		foreach($types as $type_id => $type)
		{
			$types[$type_id]->params = new JRegistry($type->attribs);
		}

		return $types;
	}



	/**
	 * Method to get author list for filtering
	 *
	 * @return array
	 * @since 1.5
	 */
	function getAuthorslist ()
	{
		$query = 'SELECT a.created_by AS id, ua.name AS name'
				. ' FROM #__content AS a'
				. ' LEFT JOIN #__users AS ua ON ua.id = a.created_by'
				. ' GROUP BY a.created_by'
				. ' ORDER BY ua.name'
				;
		$this->_db->setQuery($query);

		return $this->_db->loadObjectList();
	}


	/**
	 * Method to import Joomla! com_content datas and structure
	 * this is UNUSED in J2.5+, it may be used in the future
	 *
	 * @return boolean true on success
	 * @since 1.5
	 */

	function import()
	{
		jimport('joomla.utilities.simplexml');  // Deprecated J2.5, removed J3.x
		// Get the site default language
		$lang = flexicontent_html::getSiteDefaultLang();

		if (!FLEXI_J16GE) {
			// Get all Joomla sections
			$query = 'SELECT * FROM #__sections';
			$this->_db->setQuery($query);
			$sections = $this->_db->loadObjectList();
		}

		$logs = new stdClass();
		if (!FLEXI_J16GE) $logs->sec = 0;
		$logs->cat = 0;
		$logs->art = 0;
		//$logs->err = new stdClass();

		// Create the new category for the flexicontent items
		$topcat = JTable::getInstance('flexicontent_categories','');
		$topcat->parent_id	= 1;
		$topcat->level			= 0;
		$topcat->extension	= "com_content";
		$topcat->title			= 'FLEXIcontent';
		$topcat->alias			= 'flexicontent';
		$topcat->lft				= null;
		$topcat->rgt				= null;
		$topcat->level			= 0;
		$topcat->published	= 1;
		$topcat->access			= 1;
		$topcat->language		= "*";
		$topcat->setLocation(0, 'last-child');
		$topcat->check();
		$topcat->store();
		$topcat->rebuildPath($topcat->id);

		// Get the category default parameters in a string
		$xml = new JSimpleXML;
		$xml->loadFile(JPATH_COMPONENT.DS.'models'.DS.'category.xml');
		$catparams = new JRegistry();

		foreach ($xml->document->params as $paramGroup) {
			foreach ($paramGroup->param as $param) {
				if (!$param->attributes('name')) continue;  // FIX for empty name e.g. seperator fields
				$catparams->set($param->attributes('name'), $param->attributes('default'));
			}
		}
		$catparams_str = $catparams->toString();

		// Loop through the top category object and create cat -> subcat -> items -> fields
		$k = 0;
		if ($topcat->id)
		{

			// Get the sub-categories of the root category that belong to com_content
			$query = "SELECT * FROM #__categories as c WHERE c.extension='com_content' ";
			$this->_db->setQuery($query);
			$categories = $this->_db->loadObjectList();
			/*//get children
			$children = array();
			foreach ($categories as $child) {
				$parent = $child->parent_id;
				$list = @$children[$parent] ? $children[$parent] : array();
				array_push($list, $child);
				$children[$parent] = $list;
			}
			$categories = $children;
			//unset($children);
			*/
			$map_old_new = array();
			$map_old_new[1] = $topcat->id;
			// Loop throught the categories of the created section
			foreach ($categories as $category)
			{
				$subcat = JTable::getInstance('flexicontent_categories','');
				$subcat->load($category->id);
				$subcat->id			= 0;
				$subcat->lft		= null;
				$subcat->rgt		= null;
				$subcat->level	= null;
				$subcat->parent_id	= isset($map_old_new[$category->parent_id])?$map_old_new[$category->parent_id]:$topcat->id;
				$subcat->setLocation($subcat->parent_id, 'last-child');
				$subcat->params		= $category->params;
				$k++;
				$subcat->check();
				if ($subcat->store()) {
					$logs->cat++;
				} else {
					$logs->err->$k->type 	= JText::_( 'FLEXI_IMPORT_CATEGORY' ) . ' id';
					$logs->err->$k->id 		= $category->id;
					$logs->err->$k->title 	= $category->title;
				}
				$subcat->rebuildPath($subcat->id);
				$map_old_new[$category->id] = $subcat->id;

				// Get the articles of the created category
				$query = 'SELECT * FROM #__content WHERE catid = ' . $category->id;
				$this->_db->setQuery($query);
				$articles = $this->_db->loadObjectList();

				// Loop throught the articles of the created category
				foreach ($articles as $article)
				{
					$item = JTable::getInstance('content');
					$item->load($article->id);
					$item->id					= 0;
					$item->catid			= $subcat->id;
					$k++;
					$item->check();
					if ($item->store()) {
						$logs->art++;
					} else {
						$logs->err->$k->type 	= JText::_( 'FLEXI_IMPORT_ARTICLE' ) . ' id';
						$logs->err->$k->id 		= $article->id;
						$logs->err->$k->title 	= $article->title;
					}
				} // end articles loop
			} // end categories loop
		} // end if

		foreach ($categories as $category) {
			$subcat = JTable::getInstance('flexicontent_categories','');
			$subcat->load($map_old_new[$category->id]);
			$subcat->lft			= null;
			$subcat->rgt			= null;
			$subcat->level		= null;
			$subcat->parent_id	= isset($map_old_new[$category->parent_id])?$map_old_new[$category->parent_id]:$topcat->id;
			$subcat->setLocation($subcat->parent_id, 'last-child');
			$subcat->check();
			$subcat->store();
		}
		unset($map_old_new);

		// Save the created top category as the flexi_top_category for the component
		$this->cparams->set('flexi_top_category', $topcat->id);
		$cparams_str = $this->cparams->toString();

		$flexi = JComponentHelper::getComponent('com_flexicontent');
		$query = 'UPDATE '. (FLEXI_J16GE ? '#__extensions' : '#__components')
			. ' SET params = ' . $this->_db->Quote($cparams_str)
			. ' WHERE '. (FLEXI_J16GE ? 'extension_id' : 'id') .'='. $flexi->id
			;
		$this->_db->setQuery($query);
		$this->_db->execute();
		return $logs;
	}


	/**
	 * Method to get a list of items (ids) that have value for the given fields
	 *
	 * @since 1.5
	 */
	function getFieldsItems($fields=null, &$total=null, $start=0, $limit=100000)
	{
		if ($fields === null)
		{
			$use_all_items = true;
		}

		elseif (!count($fields))
		{
			return array();
		}

		// Get field data, so that we can identify the fields and take special action for each of them
		else
		{
			$fields = ArrayHelper::toInteger($fields);

			$query = 'SELECT *'
				. ' FROM #__flexicontent_fields'
				. ' WHERE id IN (' . implode(',', $fields) . ')';
			$field_data = $this->_db->setQuery($query)->loadObjectList();

			// Check the type of fields
			$use_all_items = false;
			$check_items_for_tags = false;
			$non_core_fields = array();

			foreach ($field_data as $field)
			{
				// tags
				if ($field->field_type === 'tags')
				{
					$get_items_with_tags = true;
					continue;
				}

				// other core fields
				if ($field->iscore)
				{
					$use_all_items = true;
					break;
				}

				// non core fields
				$non_core_fields[] = $field->id;
			}
		}


		// NOTE: Must include all items regardless of state to avoid problems when
		// (a) item changes state and (b) to allow privileged users to search any item


		// Return all items, since we included a core field other than tag
		if ($use_all_items == true)
		{
			$query = 'SELECT SQL_CALC_FOUND_ROWS id'
				. ' FROM #__content'
				. ($limit ? ' LIMIT ' . (int) $start . ', ' . (int) $limit : '')
				;
			$item_list = $this->_db->setQuery($query)->loadColumn();

			// Get items total
			$total = $this->_db->setQuery('SELECT FOUND_ROWS()')->loadResult();

			return $item_list;
		}

		// Queries used to get the item IDs of items having values for the fields
		$queries = array();

		// Find item having tags
		if (!empty($get_items_with_tags))
		{
			$queries[] = "SELECT DISTINCT t.itemid "
				." FROM #__flexicontent_tags_item_relations AS t"
				//." JOIN #__content AS a ON a.id=t.item_id AND a.state IN (1, -5)"
				;
		}

		// Find items having values for non core fields
		if (count($non_core_fields))
		{
			$non_core_fields_list = "'".implode("','", $non_core_fields)."'";
			$queries[] = "SELECT DISTINCT r.item_id "
				." FROM #__flexicontent_fields_item_relations as r"
				//." JOIN #__content AS a ON a.id=r.item_id AND a.state IN (1, -5)"
				." WHERE r.field_id IN ({$non_core_fields_list})"
				;
		}

		$query = 'SELECT SQL_CALC_FOUND_ROWS a.*'
			. ' FROM (('. implode(') UNION ( ', $queries) . ')) AS a'
			. ($limit ? ' LIMIT ' . (int) $start . ', ' . (int) $limit : '')
			;
		$item_list = $this->_db->setQuery($query)->loadColumn();

		// Get items total
		$total = $this->_db->setQuery('SELECT FOUND_ROWS()')->loadResult();

		/**
		 * NOTE: array_unique() creates gaps in the index of the array,
		 * and if passed to json_encode it will output object !!! so we use array_values()
		 */
		return array_values(array_unique($item_list));
	}


	/**
	 * Method to get an array of DB file data for the given file ids
	 *
	 * @since 1.5
	 */
	function getFileData($fileids) {
		$query = 'SELECT * '
			. ' FROM #__flexicontent_files AS f'
			. ' WHERE f.id IN ('.implode(',', $fileids).')'
			;
		$this->_db->setQuery($query);
		$filedata= $this->_db->loadObjectList();
		return $filedata;
	}


	/**
	 * Method to find which records are not authorized
	 *
	 * @param   array        $cid      Array of record ids to check
	 * @param		int|string   $action   Either an ACL rule action, or a new state
	 *
	 * @return	array     The records having assignments
	 *
	 * @since	3.3.0
	 */
	public function filterByPermission($cid, $action)
	{
		// State -3, -4 are automatic workflow state and manual change is allowed only to configuration managers
		if (in_array($action, array(-3, -4)) && !FlexicontentHelperPerm::getPerm()->SuperAdmin)
		{
			return $cid;
		}

		// State 2 is archived and setting items to it, requires a special privilege (besides also required edit.state.*)
		if (in_array($action, array(2)) && !FlexicontentHelperPerm::getPerm()->CanArchives)
		{
			return $cid;
		}

		return parent::filterByPermission($cid, $action);
	}


	/**
	 * Method to find which records having assignments blocking a state change
	 *
	 * @param		array        $cid      Array of record ids to check
	 * @param		int|string   $action   Either an ACL rule action, or a new state
	 *
	 * @return	array     The records having assignments
	 *
	 * @since   3.3.0
	 */
	public function filterByAssignments($cid = array(), $action = -2)
	{
		$cid = ArrayHelper::toInteger($cid);
		$cid_wassocs = array();

		switch ((string)$action)
		{
			// Delete
			case 'core.delete':
				break;

			// Trash, Unpublish
			case -2:
			case 0:
				break;
		}

		return $cid_wassocs;
	}


	/**
	 * Method to set order into state
	 *
	 * @return	void
	 *
	 * @since 3.3.0
	 */
	protected function _setStateOrder()
	{
		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$view   = $jinput->getCmd('view', '');
		$fcform = $jinput->getInt('fcform', 0);
		$p      = $this->ovid;

		// Order type
		$filter_order_type = $fcform ? $jinput->get('filter_order_type', 1, 'int') : $app->getUserStateFromRequest($p . 'filter_order_type', 'filter_order_type', 1, 'int' );
		$this->setState('filter_order_type', $filter_order_type);
		$app->setUserState($p.'filter_order_type', $filter_order_type);

		// Use ordering parameter from component configuration if these exist and are set
		$default_order     = str_replace('i.', 'a.', $this->cparams->get($view . '_manager_order', $this->default_order));
		$default_order_dir = $this->cparams->get($view . '_manager_order_dir', $this->default_order_dir);
		$default_order = $default_order !== '1' ? $default_order : '';   // '1' is 'unordered'

		$filter_order     = $fcform ? $jinput->get('filter_order', $default_order, 'cmd') : $app->getUserStateFromRequest($p . 'filter_order', 'filter_order', $default_order, 'cmd');
		$filter_order_Dir = $fcform ? $jinput->get('filter_order_Dir', $default_order_dir, 'word') : $app->getUserStateFromRequest($p . 'filter_order_Dir', 'filter_order_Dir', $default_order_dir, 'word');

		if (!$filter_order)
		{
			$filter_order = $default_order;
		}

		if (!$filter_order_Dir)
		{
			$filter_order_Dir = $default_order_dir;
		}

		// Filter order is selected via current setting of filter_order_type selector
		$filter_order	= ($filter_order_type && ($filter_order == 'a.ordering')) ? 'catsordering' : $filter_order;
		$filter_order	= (!$filter_order_type && ($filter_order == 'catsordering')) ? 'a.ordering' : $filter_order;
		$jinput->set( 'filter_order', $filter_order );
		$jinput->set( 'filter_order_Dir', $filter_order_Dir );

		$this->setState('filter_order', $filter_order);
		$this->setState('filter_order_Dir', $filter_order_Dir);

		$app->setUserState($p . 'filter_order', $filter_order);
		$app->setUserState($p . 'filter_order_Dir', $filter_order_Dir);
	}


	/**
	 * Method to get Text Search clause according to search scope
	 *
	 * @return	void
	 *
	 * @since 3.3.0
	 */
	protected function _getTextSearch()
	{
		// Text search and search scope
		$scope  = $this->getState('scope');
		$search = $this->getState('search');
		$search = StringHelper::trim(StringHelper::strtolower($search));

		// Create the text search clauses
		$textwhere = array();

		$search_prefix = JComponentHelper::getParams( 'com_flexicontent' )->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix

		if ($search)
		{
			$escaped_search = str_replace(' ', '%', $this->_db->escape(trim($search), true));
			$search_quoted  = $this->_db->Quote('%' . $escaped_search . '%', false);

			switch($scope)
			{
				case 'a.metadesc':
				case 'a.metakey':
				case 'a.' . $this->name_col:
					$textwhere[] = ' LOWER(' . $scope . ') LIKE ' . $search_quoted;
					break;

				case '_meta_':
					$textwhere[] = 'LOWER(a.metadesc) LIKE ' . $search_quoted;
					$textwhere[] = 'LOWER(a.metakey)  LIKE ' . $search_quoted;
					break;

				case '_desc_':
					$textwhere[] = 'LOWER(a.introtext) LIKE ' . $search_quoted;
					$textwhere[] = 'LOWER(a.fulltext)  LIKE ' . $search_quoted;
					break;

				case 'ie.search_index':
					$textwhere[] = ' MATCH (ie.search_index) AGAINST (' . $this->_db->Quote($search_prefix . $escaped_search . '*', false ).' IN BOOLEAN MODE)';
					break;
			}
		}

		return $textwhere;
	}


	/**
	 * Method to get item (language) associations
	 *
	 * @param		array   $ids       An array of records is
	 * @param		object  $config    An object with configuration for getting associations
	 *
	 * @return	array   An array with associations of the records list
	 *
	 * @since   3.3.0
	 */
	public function getLangAssocs($ids = null, $config = null)
	{
		$config = $config ?: (object) array(
			'table'       => $this->records_dbtbl,
			'table_ext'   => 'flexicontent_items_ext',
			'ext_id'      => 'item_id',
			'context'     => 'com_content.item',
			'created'     => 'created',
			'modified'    => 'modified',
			'state'       => 'state',
			'catid'       => 'catid',
			'is_uptodate' =>'is_uptodate',
		);

		return parent::getLangAssocs($ids, $config);
	}


	/**
	 * START OF MODEL SPECIFIC METHODS
	 */

}
