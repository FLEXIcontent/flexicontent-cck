<?php
/**
 * @version 1.5 stable $Id: template.php 1577 2012-12-02 15:10:44Z ggppdk $
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

/**
 * FLEXIcontent Component Template Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.5
 */
class FlexicontentModelTemplate extends JModelLegacy
{
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
	 * Layout configuration data (template parameters / attibutes)
	 *
	 * @var object
	 */
	var $_config = null;
	
	
	
	
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();
		
		$type 	= JRequest::getVar('type',  'items', '', 'word');
		$folder = JRequest::getVar('folder',  'default', '', 'cmd');
		$cfgname = JRequest::getVar('cfgname',  '', '', 'cmd');
		$this->setId($type, $folder, $cfgname);
	}
	
	
	/**
	 * Method to set the identifier
	 *
	 * @access	public
	 * @param	int item identifier
	 */
	function setId($type, $folder, $cfgname)
	{
		// Set item id and wipe data
		$this->_layout  = null;
		$this->_config  = null;
		$this->_type    = $type;
		$this->_folder  = $folder;
		$this->_cfgname = $cfgname;
	}
	
	
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
		$this->_type    = $type;
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
			. ' AND f.field_type <> "groupmarker" '
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
			JError::raiseWarning( 500, $this->_db->getError() );
		}
		if (!$layoutConf) {
			$layoutConf = new stdClass();
			$layoutConf->template = $this->_folder;
			$layoutConf->cfgname = $this->_cfgname;
			$layoutConf->attribs = '';
		}
		$layoutConf->attribs = new JRegistry($layoutConf->attribs);
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
		$db = JFactory::getDbo();
		
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
	function storeFieldPositions($folder, $cfgname, $type, &$positions, &$records)
	{
		$folder_quoted  = $this->_db->Quote($folder);
		$cfgname_quoted = $this->_db->Quote($cfgname);
		$type_quoted    = $this->_db->Quote($type);
		
		$pos_quoted = array();
		$rec_vals = array();
		foreach ($positions as $pos) {
			$pos_quoted[$pos] = $this->_db->Quote($pos);
			if ($records[$pos] != '')
				$rec_vals[$pos] = '('. $folder_quoted. ',' .$cfgname_quoted. ',' .$type_quoted. ',' .$pos_quoted[$pos]. ',' .$this->_db->Quote($records[$pos]) .')';
		}
		
		// Delete old records
		$query 	= 'DELETE FROM #__flexicontent_templates'
			. ' WHERE template = ' . $this->_db->Quote($folder)
			. '  AND cfgname = ' . $this->_db->Quote($cfgname)
			. '  AND layout = ' . $this->_db->Quote($type)
			. '  AND position IN (' . implode(',', $pos_quoted) . ')'
			;
		$this->_db->setQuery($query);
		$this->_db->execute();
		
		if ( count($rec_vals) ) {
			$query 	= 'INSERT INTO #__flexicontent_templates '.
				'(`template`, `cfgname`, `layout`, `position`, `fields`)'
				.'  VALUES '
				.implode(",\n", $rec_vals);
			$this->_db->setQuery($query);
			$this->_db->execute();
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
		// Load the XML file into a JForm object
		$jform = new JForm('com_flexicontent.template', array('control' => 'jform', 'load_data' => false));
		$jform->load($this->_getLayout()->params);   // params is the XML file contents as a string
		
		$layout_type = $layout=='items' ? 'item' : 'category';
		$tmpldir = JPath::clean(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'templates');
		$less_path = JPath::clean($tmpldir.DS.$folder.DS.'less/include/config_auto_'.$layout_type.'.less');
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
}
