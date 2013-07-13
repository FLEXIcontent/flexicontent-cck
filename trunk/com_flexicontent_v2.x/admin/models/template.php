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

jimport('joomla.application.component.model');

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
	 * Layout data
	 *
	 * @var object
	 */
	var $_layout = null;

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
		$this->setId($type, $folder);
	}

	/**
	 * Method to set the identifier
	 *
	 * @access	public
	 * @param	int item identifier
	 */
	function setId($type, $folder)
	{
		// Set item id and wipe data
		$this->_type	    = $type;
		$this->_folder		= $folder;
	}

	/**
	 * Method to get templates data
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
		$tmpl	= flexicontent_tmpl::getTemplates();

		$layout = $tmpl->{$this->_type}->{$this->_folder};
		
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
	 * Method to get types list when performing an edit action
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getContentTypesList()
	{
		$query = 'SELECT id, name'
			. ' FROM #__flexicontent_types'
			. ' WHERE published = 1'
			. ' ORDER BY name ASC'
			;
		$this->_db->setQuery($query);
		$types = $this->_db->loadObjectList();
		return $types;	
	}
	
	
	/**
	 * Method to get types list when performing an edit action
	 * 
	 * @return array
	 * @since 1.5
	 */
	function getFieldTypesList()
	{
		$db = JFactory::getDBO();
		
		$query = 'SELECT element AS type_name, REPLACE(name, "FLEXIcontent - ", "") AS field_name '
		. ' FROM '.(FLEXI_J16GE ? '#__extensions' : '#__plugins')
		. ' WHERE '.(FLEXI_J16GE ? 'enabled = 1' : 'published = 1')
		. (FLEXI_J16GE ? ' AND `type`=' . $db->Quote('plugin') : '')
		. ' AND folder = ' . $db->Quote('flexicontent_fields')
		. ' AND element <> ' . $db->Quote('core')
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
				. ' AND layout = ' . $this->_db->Quote($this->_type)
				;				;
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
				. ' AND layout = ' . $this->_db->Quote($this->_type)
				. ' AND position = ' . $this->_db->Quote($pos)
				;
		$this->_db->setQuery( $query );
		if (!$this->_db->query()) {
			JError::raiseWarning( 500, $this->_db->getError() );
		}
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
	 * Method to store a field group
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function store($folder, $type, $p, $record)
	{
		// delete old record
		$query 	= 'DELETE FROM #__flexicontent_templates'
				. ' WHERE template = ' . $this->_db->Quote($folder)
				. ' AND layout = ' . $this->_db->Quote($type)
				. ' AND position = ' . $this->_db->Quote($p)
				;
		$this->_db->setQuery($query);
		$this->_db->query();
		
		if ($record != '') {
			$query 	= 'INSERT INTO #__flexicontent_templates (`template`, `layout`, `position`, `fields`)'
					.' VALUES(' . $this->_db->Quote($folder) . ',' . $this->_db->Quote($type) . ',' . $this->_db->Quote($p) . ',' . $this->_db->Quote($record) . ')'
					;
			$this->_db->setQuery($query);
			$this->_db->query();
			// don't forget to check if no field was prevouilsly altered
		}
		
		return true;
	}


}
?>