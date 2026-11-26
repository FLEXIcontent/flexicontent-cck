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

require_once('base/base.php');

/**
 * FLEXIcontent Template Model
 *
 */
class FlexicontentModelTemplate extends FCModelAdmin
{
	/**
	 * Record name, (parent class property), this is used for: naming session data, XML file of class, etc
	 *
	 * @var string
	 */
	protected $name = 'template';

	/**
	 * Record database table
	 *
	 * @var string
	 */
	var $records_dbtbl = null;

	/**
	 * Record jtable name
	 *
	 * @var string
	 */
	var $records_jtable = null;

	/**
	 * Record primary key
	 *
	 * @var int
	 */
	var $_id = null;

	/**
	 * Record data
	 *
	 * @var object
	 */
	var $_record = null;

	/**
	 * Events context to use during model FORM events and diplay PREPARE events triggering
	 *
	 * @var object
	 */
	var $events_context = null;

	/**
	 * Flag to indicate adding new records with next available ordering (at the end),
	 * this is ignored if this record DB model does not have 'ordering'
	 *
	 * @var boolean
	 */
	var $useLastOrdering = false;

	/**
	 * Plugin group used to trigger events
	 *
	 * @var boolean
	 */
	var $plugins_group = null;

	/**
	 * Records real extension
	 *
	 * @var string
	 */
	var $extension_proxy = null;

	/**
	 * Use language associations
	 *
	 * @var string
	 */
	var $associations_context = false;

	/**
	 * Various record specific properties
	 *
	 */

	/**
	 * Layout data (XML schema, CSS/JS files, image, etc)
	 *
	 * @var object
	 */
	var $_layout = null;

	/**
	 * Layout type (Either: item -or- category -or- ...)
	 *
	 * @var object
	 */
	var $_type = null;

	/**
	 * Layout template folder (real folder in storage)
	 *
	 * @var object
	 */
	var $_folder = null;


	/**
	 * Layout configuration name
	 *
	 * @var object
	 */
	var $_cfgname = null;


	/**
	 * Layout configuration data (template parameters (attibutes) and positions)
	 *
	 * @var object
	 */
	var $_config = null;


	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		$jinput = \Joomla\CMS\Factory::getApplication()->input;

		$type 	= $jinput->getWord('type', 'items');
		$folder = $jinput->getCmd('folder', 'table');
		$cfgname = $jinput->getCmd('cfgname', '');

		$this->setId($type, $folder, $cfgname);

		$this->canManage = FlexicontentHelperPerm::getPerm()->CanTemplates;
	}


	/**
	 * Method to set the identifier
	 *
	 * @param	int item identifier
	 */
	public function setId($type, $folder = null, $cfgname = null)
	{
		// Set item id and wipe data
		$this->_layout  = null;
		$this->_config  = null;
		$this->_type    = $type;
		$this->_folder  = $folder;
		$this->_cfgname = $cfgname;
	}


	/**
	 * Method to set the Layout configuration data (template parameters (attibutes) and positions)
	 *
	 * @access	public
	 * @param	int item identifier
	 */
	function setConfig($positions, $attribs)
	{
		$this->_config['positions'] = $positions;
		$this->_config['attribs'] = $attribs;
	}


	/**
	 * Method to save the record
	 *
	 * @param   array  $data  The form data.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   3.2.0
	 */
	function save($data)
	{
		// Store field positions
		$this->storeFieldPositions($this->_folder, $this->_cfgname, $this->_type, $this->_config['positions'], $data);

		// Store Layout configurations (template parameters)
		$this->storeLayoutConf($this->_folder, $this->_cfgname, $this->_type, $this->_config['attribs']);

		// Prepare missing LESS sources only during a save, including CSS-only legacy templates.
		$this->check_less_files($this->_folder);

		// Store LESS configuration (less variables)
		$this->storeLessConf($this->_folder, $this->_cfgname, $this->_type, $this->_config['attribs']);
		flexicontent_tmpl::getTemplates($this->_folder, false);

		return true;
	}


	/**
	 * Method to check if the user can edit the record
	 *
	 * @return	boolean	True on success
	 *
	 * @since	3.2.0
	 */
	public function canEdit($record = null, $user = null)
	{
		if ($user)
		{
			throw new Exception(__FUNCTION__ . '(): Error model does not support checking ACL of specific user', 500);
		}

		$record = $record ?: $this->_record;
		$user   = $user ?: \Joomla\CMS\Factory::getApplication()->getIdentity();

		return $this->canManage;
	}


	/**
	 * Method to check if the user can edit record 's state
	 *
	 * @return	boolean	True on success
	 *
	 * @since	3.2.0
	 */
	public function canEditState($record = null, $user = null)
	{
		if ($user)
		{
			throw new Exception(__FUNCTION__ . '(): Error model does not support checking ACL of specific user', 500);
		}

		$record = $record ?: $this->_record;
		$user    = $user ?: \Joomla\CMS\Factory::getApplication()->getIdentity();

		return $this->canManage;
	}


	/**
	 * Method to check if the user can delete the record
	 *
	 * @return	boolean	True on success
	 *
	 * @since	3.2.0
	 */
	public function canDelete($record = null)
	{
		$record = $record ?: $this->_record;
		$user   = \Joomla\CMS\Factory::getApplication()->getIdentity();

		return $this->canManage;
	}


	/**
	 * Method to do some record / data preprocessing before call \Joomla\CMS\Table\Table::bind()
	 *
	 * Note. Typically called inside this MODEL 's store()
	 *
	 * @param   object     $record   The record object
	 * @param   array      $data     The new data array
	 *
	 * @since	3.2.0
	 */
	protected function _prepareBind($record, & $data)
	{
		// Call parent class bind preparation
		parent::_prepareBind($record, $data);
	}


	/**
	 * Method to do some work after record has been stored
	 *
	 * Note. Typically called inside this MODEL 's store()
	 *
	 * @param   object     $record   The record object
	 * @param   array      $data     The new data array
	 *
	 * @since	3.2.0
	 */
	protected function _afterStore($record, & $data)
	{
		parent::_afterStore($record, $data);
	}


	/**
	 * Method to do some work after record has been loaded via \Joomla\CMS\Table\Table::load()
	 *
	 * Note. Typically called inside this MODEL 's store()
	 *
	 * @param	object   $record   The record object
	 *
	 * @since	3.2.0
	 */
	protected function _afterLoad($record)
	{
		parent::_afterLoad($record);
	}


	/**
	 * START OF MODEL SPECIFIC METHODS
	 */


	/**
	 * Method to set the layout type ('items' or 'category')
	 * 'items': single item layout
	 * 'category': multi-item layout
	 *
	 * @access	public
	 * @param	int item identifier
	 */
	function setLayoutType($type)
	{
		$this->_type = $type;
	}


	/**
	 * Method to get the layout data (XML schema, CSS/JS files, image, etc)
	 *
	 * @access public
	 * @return array
	 */
	function getData()
	{
		// Lets load the layout object if it doesn't already exist
		if (empty($this->_layout))
		{
			$this->_layout = $this->_getLayout();
		}

		return $this->_layout;
	}


	/**
	 * Method to get the object
	 *
	 * @access	private
	 * @return	array
	 * @since	1.5
	 */
	function _getLayout()
	{
		// Get all templates re-parsing only XML/LESS files of a specific template
		$tmpl	= flexicontent_tmpl::getTemplates($this->_folder, $skip_less_compile=true);  // Will check less compiling later ...

		$layout = !isset($tmpl->{$this->_type}->{$this->_folder})  ?  false  :  $tmpl->{$this->_type}->{$this->_folder};

		return $layout;
	}


	/**
	 * Method to get all available fields
	 *
	 * @access public
	 * @return array
	 */
	function getFields()
	{
		$query  = 'SELECT f.*, GROUP_CONCAT(rel.type_id SEPARATOR ",") AS reltypes '
			. ' FROM #__flexicontent_fields as f '
			. ' LEFT JOIN #__flexicontent_fields_type_relations as rel ON rel.field_id=f.id '
			. ' WHERE f.published = 1 '
			. ' AND f.field_type <> "custom_form_html" '
			. ' GROUP BY f.id '
			. ' ORDER BY f.label ASC'
			;
		$this->_db->setQuery($query);
		$fields = $this->_db->loadObjectList('name');

		return $fields;
	}


	/**
	 * Method to get Layout configuration data (template parameters / attibutes)
	 *
	 * @access public
	 * @return array
	 */
	function getLayoutConf()
	{
		$query  = 'SELECT * '
			. ' FROM #__flexicontent_layouts_conf as f '
			. ' WHERE template = ' . $this->_db->Quote($this->_folder)
			. '  AND cfgname = ' . $this->_db->Quote($this->_cfgname)
			. '  AND layout = ' . $this->_db->Quote($this->_type)
			;
		$this->_db->setQuery($query);
		$layoutConf = $this->_db->loadObject();
		if ($layoutConf===false) {
			\Joomla\CMS\Factory::getApplication()->enqueueMessage($this->_db->getError(), 'warning');
		}
		if (!$layoutConf) {
			$layoutConf = new stdClass();
			$layoutConf->template = $this->_folder;
			$layoutConf->cfgname = $this->_cfgname;
			$layoutConf->attribs = '';
		}
		$layoutConf->attribs = new \Joomla\Registry\Registry($layoutConf->attribs);
		$layoutConf->attribs = $layoutConf->attribs->toArray();

		//echo "<pre>"; print_r($layoutConf); echo "</pre>";
		return $layoutConf;
	}

		/**
	 * Method to get Layout configuration data (template parameters / attibutes)
	 *
	 * @access public
	 * @return array
	 */
	function storeLayoutConf($folder, $cfgname, $layout, $attribs)
	{
		// delete old record
		$query 	= 'DELETE FROM #__flexicontent_layouts_conf'
			. ' WHERE template = ' . $this->_db->Quote($folder)
			. '  AND cfgname = ' . $this->_db->Quote($cfgname)
			. '  AND layout = ' . $this->_db->Quote($layout)
			;
		$this->_db->setQuery($query);
		$this->_db->execute();

		$attribs = json_encode($attribs);
		//echo "<pre>"; print_r($attribs); echo "</pre>";

		$query 	= 'INSERT INTO #__flexicontent_layouts_conf (`template`, `cfgname`, `layout`, `attribs`)'
			.' VALUES(' .
				$this->_db->Quote($folder) . ',' .
				$this->_db->Quote($cfgname) . ',' .
				$this->_db->Quote($layout) . ',' .
				$this->_db->Quote($attribs) .
			')'
			;
		$this->_db->setQuery($query);
		$this->_db->execute();

		return true;
	}


	/**
	 * Method to get types list
	 *
	 * @return array
	 * @since 1.5
	 */
	function getTypeslist ( $type_ids=false, $check_perms = false, $published=true )
	{
		return flexicontent_html::getTypesList( $type_ids, $check_perms, $published);
	}


	/**
	 * Method to get types list when performing an edit action
	 *
	 * @return array
	 * @since 1.5
	 */
	function getFieldTypesList()
	{
		$db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

		$query = 'SELECT element AS type_name, REPLACE(name, "FLEXIcontent - ", "") AS field_name '
		. ' FROM #__extensions'
		. ' WHERE enabled = 1'
		. '  AND `type`=' . $db->Quote('plugin')
		. '  AND `folder` = ' . $db->Quote('flexicontent_fields')
		. '  AND `element` <> ' . $db->Quote('core')
		. ' ORDER BY field_name ASC'
		;

		$db->setQuery($query);
		$field_types = $db->loadObjectList();

		// This should not be neccessary as, it was already done in DB query above
		foreach($field_types as $field_type) {
			$field_type->field_name = preg_replace("/FLEXIcontent[ \t]*-[ \t]*/i", "", $field_type->field_name);
			$field_arr[$field_type->field_name] = $field_type;
		}
		ksort( $field_arr, SORT_STRING );

		return $field_arr;
	}


	/**
	 * Method to get all available fields
	 *
	 * @access public
	 * @return array
	 */
	function getFieldsByPositions()
	{
		$query  = 'SELECT *'
			. ' FROM #__flexicontent_templates'
			. ' WHERE template = ' . $this->_db->Quote($this->_folder)
			. '  AND cfgname = ' . $this->_db->Quote($this->_cfgname)
			. '  AND layout = ' . $this->_db->Quote($this->_type)
			;
		$this->_db->setQuery($query);
		$positions = $this->_db->loadObjectList('position');

		// convert template positions to array
		$tmplpositions = array();
		if (isset($this->_layout->positions)) {
			foreach($this->_layout->positions as $p) {
				array_push($tmplpositions, $p);
			}
		}

		foreach ($positions as $pos) {
			if (!in_array($pos->position, $tmplpositions)) {
				$this->deletePosition($pos->position);
				unset($pos);
			} else {
				$pos->fields = explode(',', $pos->fields);
			}
		}
		return $positions;
	}


	/**
	 * Method cleanup template in case of removing positions
	 *
	 * @access public
	 * @return void
	 */
	function deletePosition($pos)
	{
		$query  = 'DELETE FROM #__flexicontent_templates'
			. ' WHERE template = ' . $this->_db->Quote($this->_folder)
			. '  AND cfgname = ' . $this->_db->Quote($this->_cfgname)
			. '  AND layout = ' . $this->_db->Quote($this->_type)
			. '  AND position = ' . $this->_db->Quote($pos)
			;
		$this->_db->setQuery( $query );
		$this->_db->execute();
	}

	/**
	 * Method to get all available fields
	 *
	 * @access public
	 * @return array
	 */
	function getUsedFields()
	{
		$positions = $this->getFieldsByPositions();

		$usedfields = array();

		foreach ($positions as $pos) {
			foreach ($pos->fields as $f) {
				$usedfields[] = $f;
			}
		}
		return ($usedfields ? array_unique($usedfields) : array());
	}


	/**
	 * Method to store a field positions
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	public function storeFieldPositions($folder, $cfgname, $type, $positions, $records)
	{
		$pos_quoted = array();
		$rec_vals = array();

		foreach ($positions as $pos)
		{
			$pos_quoted[$pos] = $this->_db->Quote($pos);

			if ($records[$pos])
			{
				$rec_vals[$pos] = '('.
					$this->_db->Quote($folder) . ',' .
					$this->_db->Quote($cfgname) . ',' .
					$this->_db->Quote($type) . ',' .
					$pos_quoted[$pos] . ',' .
					$this->_db->Quote($records[$pos]) .
				')';
			}
		}

		// Delete old records
		$query 	= 'DELETE FROM #__flexicontent_templates'
			. ' WHERE template = ' . $this->_db->Quote($folder)
			. '  AND cfgname = ' . $this->_db->Quote($cfgname)
			. '  AND layout = ' . $this->_db->Quote($type)
			. '  AND position IN (' . implode(',', $pos_quoted) . ')'
			;
		$this->_db->setQuery($query)->execute();

		if (count($rec_vals))
		{
			$query 	= 'INSERT INTO #__flexicontent_templates '
				. '(`template`, `cfgname`, `layout`, `position`, `fields`)'
				. '  VALUES '
				. implode(",\n", $rec_vals)
				;
			$this->_db->setQuery($query)->execute();
		}

		return true;
	}


	/**
	 * Method to store parameters as LESS variables
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function storeLessConf($folder, $cfgname, $layout, $attribs)
	{
		// Load the XML file into a \Joomla\CMS\Form\Form object
		$jform = new \Joomla\CMS\Form\Form('com_flexicontent.template', array('control' => 'jform', 'load_data' => false));
		$jform->load($this->_getLayout()->params);   // params is the XML file contents as a string

		$layout_type = $layout=='items' ? 'item' : 'category';
		$tmpldir = \Joomla\Filesystem\Path::clean(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'templates');
		$less_path = \Joomla\Filesystem\Path::clean($tmpldir.DS.$folder.DS.'less/include/config_auto_'.$layout_type.'.less');
		//echo "<pre>".$less_path."<br/>";

		$_FCLL = '@FC'. ($layout == 'items' ? 'I' : 'C').'_';

		// Get 'attribs' fieldset
		$fieldSets = $jform->getFieldsets($groupname = 'attribs');

		// Iterate though the form elements and only use parameters with cssprep="less"
		$less_data = "/* This is created automatically, do NOT edit this manually! \nThis is used by ".$layout_type." layout to save parameters as less variables. \nNOTE: Make sure that this is imported by 'config.less' \n to make a parameter be a LESS variable, edit parameter in ".$layout_type.".xml and add cssprep=\"less\" \n created parameters will be like: @FC".($layout=='items'? 'I' : 'C')."_parameter_name: value; */\n\n";
		foreach($jform->getFieldsets($groupname) as $fsname => $fieldSet)
		{
			foreach($jform->getFieldset($fsname) as $field)
			{
				if ($field->getAttribute('cssprep')!='less') continue;  // Only add parameters meant to be less variables
				$v = isset($attribs[$field->fieldname])  ?  $attribs[$field->fieldname] : '';
				if (is_array($v)) continue;  // array parameters not supported
				$v = trim($v);
				if ( !strlen($v) ) {
					$v = $field->getAttribute('default');
					if ( !strlen($v) ) continue;  // do not add empty parameters
				}
				$less_data .= $_FCLL.$field->fieldname.': '.$v.";\n";
			}
		}
		file_put_contents($less_path, $less_data);

		return true;
	}

	/*
	 * Check that the LESS layout file that stores parameter values exists and is up to date (its modification time after XML file modification)
	 */
	function check_xml_to_less($layout = null)
	{
		if (!$layout) return;
		$tmpldir = JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.$layout->name;

		// ****************************************************************************************************************************
		// Create / Update the "variable defaults" FILEs (using defaults from XML files): config_auto_item.less / config_auto_item.less
		// ****************************************************************************************************************************

		$view = $layout->view;
		$less_data = "/* This is created automatically, do NOT edit this manually! \nThis is used by _layout_type_ layout to save parameters as less variables. \nNOTE: Make sure that this is imported by 'config.less' \n to make a parameter be a LESS variable, edit parameter in _layout_type_.xml and add cssprep=\"less\" \n created parameters will be like: @FCLL_parameter_name: value; */\n\n";
		$_less_auto = false;
		if ( !file_exists($tmpldir . '/less/include/config_auto_'.$view.'.less') || filemtime($tmpldir . '/less/include/config_auto_'.$view.'.less') < filemtime($tmpldir . '/'.$view.'.xml') ) {
			$_less_auto = $tmpldir . '/less/include/config_auto_'.$view.'.less';
			file_put_contents($_less_auto, str_replace("FCLL_", "FCI_", str_replace("_layout_type_", $view, $less_data)));
		}
		if (!$_less_auto) return;

		$layout_type = $view === 'item' ? 'items' : 'category';
		$model = new FlexicontentModelTemplate();
		$model->setId($layout_type, $layout->name, '');
		$_layout = $model->getData();
		$_conf = $model->getLayoutConf();
		$_attribs = $_conf->attribs;
		$model->storeLessConf($layout->name, $cfgname='', $layout_type, $_attribs);
	}

	/*
	 * Check that less files for all layouts of current template (=folder) exist and are up-to-date
	 */
	function check_less_files($folder)
	{
		// **************************************************************************************
		// Get both single item ('items') and multi-item ('category') layouts of current template
		// 'folder' and apply Template Parameters values into the form fields structures
		// **************************************************************************************

		$tmpl = flexicontent_tmpl::getTemplates( $folder, $skip_less=true );

		$tmpldir = JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.$folder;

		// Create less folders if they do not exist already
		if ( !is_dir( $tmpldir . '/less' ) ) if ( !mkdir( $tmpldir . '/less') )  \Joomla\CMS\Factory::getApplication()->enqueueMessage(\Joomla\CMS\Language\Text::_('Unable to create "/less/" folder'), 'warning');

		// Abort if directory creation failed
		if ( ! is_dir( $tmpldir . '/less' ) ) return;


		// ***********************************************************************
		// Create CUSTOM config.less, that is include by item.less / category.less
		// ************************************************************ ***********

		if ( !is_dir( $tmpldir . '/less/include' ) ) if ( !mkdir( $tmpldir . '/less/include') )  \Joomla\CMS\Factory::getApplication()->enqueueMessage(\Joomla\CMS\Language\Text::_('Unable to create "/less/include" folder'), 'warning');
		if ( !file_exists($tmpldir . '/less/include/config.less') ) {
			file_put_contents($tmpldir . '/less/include/config.less', "/* Place your less variables, mixins, etc, here \n1. This is commonly imported by files: item.less and category.less, \n2. If you add extra less file imports, then place files \ninside same folder for automatic compiling to be triggered */\n\n@import 'config_auto_item.less';\n@import 'config_auto_category.less';\n");
		}


		// *************************************************************************
		// Create files item.less / category.less by COPYING item.css / category.css
		// *************************************************************************

		$less_files = array('/css/item.css'=>'/less/item.less', '/css/category.css'=>'/less/category.less');
		foreach($less_files as $css_name => $less_name) {
			if ( !file_exists($tmpldir . $css_name) )  continue;  // Do not try to copy CSS file that does not exist
			if ( !file_exists($tmpldir . $less_name) ) {
				if ( !\Joomla\Filesystem\File::copy($tmpldir.$css_name, $tmpldir.$less_name) ) {
					\Joomla\CMS\Factory::getApplication()->enqueueMessage(\Joomla\CMS\Language\Text::_('Unable to create file: "'.$tmpldir.$less_name.'"'), 'warning');
				} else {
					$file_data = "@import 'include/config.less';\n\n";
					$file_data .= preg_replace("/[ \t]*\*zoom[\s]*:[\s]*expression[^\r\n]+[\r\n]+/u", "", file_get_contents($tmpldir.$less_name));  // copy and replace old invalid code
					file_put_contents($tmpldir.$less_name, $file_data);
				}
			}
		}


		// Check that the LESS layout files that stores parameter values exist
		// and they are up to date (their modification time after XML file modification)

		$_layout = !isset($tmpl->items->$folder)  ?  false  :  $tmpl->items->$folder;
		$this->check_xml_to_less($_layout);

		$_layout = !isset($tmpl->category->$folder)  ?  false  :  $tmpl->category->$folder;
		$this->check_xml_to_less($_layout);
	}
}
