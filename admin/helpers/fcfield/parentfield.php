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

defined( '_JEXEC' ) or die( 'Restricted access' );

class FCField extends JPlugin{
	// ***********
	// ATTRIBUTES
	// ***********
	static $field_types = array('fcfield');
	protected $fieldtypes = NULL;
	protected $field = NULL;
	protected $item = NULL;
	protected $vars = NULL;
	
	// ***********
	// CONSTRUCTOR
	// ***********
	public function __construct(&$subject, $params) {
		parent::__construct( $subject, $params );
		if(!$this->fieldtypes)
			$this->fieldtypes = self::$field_types;
		$class = strtolower(get_class($this));
		$fieldtype = str_replace('plgflexicontent_fields', '', $class);
		self::$field_types = array_merge(array($fieldtype), self::$field_types);
		$this->fieldtypes = array_merge(array($fieldtype), $this->fieldtypes);
		foreach($this->fieldtypes as $ft) {
			JPlugin::loadLanguage('plg_flexicontent_fields_'.$fieldtype, JPATH_ADMINISTRATOR);
		}
	}
	
	public function setField(&$field) {
		$this->field = $field;
	}
	
	public function &getField() {
		return $this->field;
	}
	
	public function setItem(&$item) {
		$this->item = $item;
	}
	
	public function &getItem() {
		return $this->item;
	}
	
	protected function getSeparatorF($opentag, $closetag)
	{
		if(!$this->field) return;
		$separatorf = $this->field->parameters->get( 'separatorf', 1 ) ;
		switch($separatorf)
		{
			case 0:
			$separatorf = '&nbsp;';
			break;

			case 1:
			$separatorf = '<br />';
			break;

			case 2:
			$separatorf = '&nbsp;|&nbsp;';
			break;

			case 3:
			$separatorf = ',&nbsp;';
			break;

			case 4:
			$separatorf = $closetag . $opentag;
			break;

			case 5:
			$separatorf = '';
			break;

			default:
			$separatorf = '&nbsp;';
			break;
		}
		return $separatorf;
	}
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	public function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		$field->label = JText::_($field->label);
		
		$this->setField($field);
		$this->setItem($item);
		$this->values = $this->parseValues($this->field->value);
		
		$this->displayField();
	}
	
	
	// Method to create field's HTML display for frontend views
	public function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		$field->label = JText::_($field->label);
		
		$this->setField($field);
		$this->setItem($item);
		$this->values = $this->parseValues($this->field->value);
		
		$this->display($values, $prop);
	}
	
	
	// Method to handle field's values before they are saved into the DB
	public function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !is_array($post) && !strlen($post) ) return;
	}
	
	
	// Method to take any actions/cleanups needed after field's values are saved into the DB
	public function onAfterSaveField( &$field, &$post, &$file, &$item ) {
	}
	
	
	// Method called just before the item is deleted to remove custom item data related to the field
	public function onBeforeDeleteField(&$field, &$item) {
	}
	
	
	
	// *********************************
	// CATEGORY/SEARCH FILTERING METHODS
	// *********************************
	
	// Method to display a search filter for the advanced search view
	public function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm') {
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		FlexicontentFields::createFilter($filter, $value, $formName);
	}
	
	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	public function getFilteredSearch(&$field, $value) {
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		return FlexicontentFields::getFilteredSearch($field, $value, $return_sql=true);
	}
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	public function onIndexAdvSearch(&$field, &$post, &$item) {
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array('originalname'), $search_properties=array('title','desc'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	// Method to create basic search index (added as the property field->search)
	public function onIndexSearch(&$field, &$post, &$item) {
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array('originalname'), $search_properties=array('title','desc'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	/**
	 * Get the path to a layout for a field
	 *
	 * @param   string  $plg  The name of the field
	 * @param   string  $layout  The name of the field layout. If alternative layout, in the form template:filename.
	 *
	 * @return  string  The path to the field layout
	 *
	 * @since   1.5
	 */
	public static function getLayoutPath($plg, $layout = 'field')
	{
		$template = JFactory::getApplication('site')->getTemplate();
		$defaultLayout = $layout;

		if (strpos($layout, ':') !== false) {
			// Get the template and file name from the string
			$temp = explode(':', $layout);
			$template = ($temp[0] == '_') ? $template : $temp[0];
			$layout = $temp[1];
			$defaultLayout = ($temp[1]) ? $temp[1] : 'field';
		}

		// Build the template and base path for the layout
		$tPath = JPATH_ROOT . '/templates/' . $template . '/html/fcfields/' . $plg . '/' . $layout . '.php';
		$bPath = JPATH_ROOT . '/plugins/flexicontent_fields/' . $plg . '/tmpl/' . $defaultLayout . '.php';
		$dPath = JPATH_ROOT . '/plugins/flexicontent_fields/' . $plg . '/tmpl/field.php';

		// If the template has a layout override use it
		/*if (file_exists($tPath)) {
			return $tPath;
		} elseif (file_exists($bPath)) {
			return $bPath;
		} else {
			return $dPath;
		}*/
		
		if (file_exists($bPath)) {
			return $bPath;
		} else {
			return $dPath;
		}
	}
	
	public function getFormPath($plg, $layout = 'field') {
		return $this->getLayoutPath($plg, $layout);
	}
	
	public function getViewPath($plg, $layout = 'value') {
		return $this->getLayoutPath($plg, $layout);
	}
	
	protected function getOpenTag() {
		return FlexicontentFields::replaceFieldValue( $this->field, $this->item, $this->field->parameters->get( 'opentag', '' ), 'opentag' );
	}
	
	protected function getCloseTag() {
		return FlexicontentFields::replaceFieldValue( $this->field, $this->item, $this->field->parameters->get( 'closetag', '' ), 'closetag' );
	}
	
	public function displayField()
	{
		// Prepare variables
		$use_ingroup = 0;
		$multiple = 0;
		$field  = $this->getField();
		$item   = $this->getItem();
		$values = & $this->values;
		
		$field->html = array();
		
		// Include template file: EDIT LAYOUT 
		include(self::getLayoutPath($this->fieldtypes[0]));
		
		if ($use_ingroup) { // do not convert the array to string if field is in a group
		} else if ($multiple) { // handle multiple records
			$field->html =
				'<li class="'.$value_classes.'">'.
					implode('</li><li class="'.$value_classes.'">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			if (!$add_position) $field->html .= '<span class="fcfield-addvalue fccleared" onclick="addField'.$field->id.'(this);" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'"></span>';
		} else {  // handle single values
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . $field->html[0] .'</div>';
		}
	}
	
	public function display(&$values=null, $prop='display')
	{
		// Prepare variables
		$use_ingroup = 0;
		$field  = $this->getField();
		$item   = $this->getItem();
		$values = & $this->values;
		
		$opentag	= $this->getOpenTag();
		$closetag	= $this->getCloseTag();
		$separatorf	= $this->getSeparatorF($opentag, $closetag);
		
		$this->field->{$prop} = array();
		
		// Execute template file: VALUE VIEWING
		include(self::getViewPath($this->fieldtypes[0]));
		
		// Apply separator and open/close tags
		if (!$use_ingroup)  // do not convert the array to string if field is in a group
		{
			// Apply separator and open/close tags
			$field->{$prop} = implode($separatorf, $field->{$prop});
			if ( $field->{$prop}!=='' ) {
				$field->{$prop} = $opentag . $field->{$prop} . $closetag;
			} else {
				$field->{$prop} = '';
			}
		}
	}
	
	
	public function & parseValues(&$values)
	{
		$vals = array();
		if (!empty($values)) foreach($values as $value) {
			$v = !empty($value) ? @unserialize($value) : false;
			if ( $v !== false || $v === 'b:0;' ) {
				$vals[] = $v;
			} else {
				$vals[] = $value;
			}
		}
		return $vals;
	}
}