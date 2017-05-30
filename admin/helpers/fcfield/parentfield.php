<?php
/**
 * @version 1.5 stable $Id: flexicontent.fields.php 1990 2014-10-14 02:17:49Z ggppdk $
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
if (!defined('_FC_CONTINUE_'))  define('_FC_CONTINUE_', 0);
if (!defined('_FC_BREAK_'))     define('_FC_BREAK_', -1);

class FCField extends JPlugin
{
	// ***********
	// ATTRIBUTES
	// ***********
	static $field_types = array('fcfield');
	protected $fieldtypes = null;
	protected $field = null;
	protected $item = null;
	protected $vars = null;
	protected $autoloadLanguage = false;
	
	
	// ***********
	// CONSTRUCTOR
	// ***********
	public function __construct(&$subject, $params)
	{
		parent::__construct( $subject, $params );

		if (!$this->fieldtypes)
		{
			$this->fieldtypes = self::$field_types;
		}

		$class = strtolower(get_class($this));
		$fieldtype = str_replace('plgflexicontent_fields', '', $class);

		self::$field_types = array_merge(array($fieldtype), self::$field_types);
		$this->fieldtypes = array_merge(array($fieldtype), $this->fieldtypes);

		static $initialized = array();
		foreach($this->fieldtypes as $ft)
		{
			if ( isset($initialized[$ft]) ) continue;
			$initialized[$ft] = true;

			// Because 'site-default' language file may not have all needed language strings, or it may be syntactically broken
			// we load the ENGLISH language file (without forcing it, to avoid overwritting site-default), and then current language file
			$extension_name = 'plg_flexicontent_fields_'.$ft;
			JFactory::getLanguage()->load($extension_name, JPATH_ADMINISTRATOR, 'en-GB', $force_reload = false, $load_default = true);  // force_reload OFF
			JFactory::getLanguage()->load($extension_name, JPATH_ADMINISTRATOR, null, $force_reload = true, $load_default = true);
			//JPlugin::loadLanguage('plg_flexicontent_fields_'.$fieldtype, JPATH_ADMINISTRATOR);
		}
	}
	
	
	
	// ******************
	// Accessor functions
	// ******************
	
	public function setField(&$field) { $this->field = $field; }
	public function setItem(&$item)   { $this->item = $item; }
	public function &getField() { return $this->field; }
	public function &getItem()  { return $this->item; }
	
	
	protected function getSeparatorF($opentag, $closetag, $sep_default = 1)
	{
		if(!$this->field) return false;
		$separatorf = $this->field->parameters->get('separatorf', $sep_default);
		switch($separatorf)
		{
			case 0:
			$separatorf = '&nbsp;';
			break;

			case 1:
			$separatorf = '<br class="fcclear" />';
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
		
		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);
		
		// Parse field values
		$this->values = $this->parseValues($this->field->value);
		
		// Optionally, get default field values array is empty
		$this->values = count($this->values) ? $this->values : $this->getDefaultValues();
		
		// Call before display method, for optionally work
		$this->beforeDisplayField();
		
		$formlayout = $field->parameters->get('formlayout', '');
		$formlayout = $formlayout && $formlayout != 'field' ? 'field_'.$formlayout : 'field';

		// Display the form field
		$this->displayField($formlayout);
	}
	
	
	// Method to create field's HTML display for frontend views
	public function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		$field->label = JText::_($field->label);
		
		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);
		
		// Use the custom field values, if these were provided
		$values = $values !== null ? $values : $this->field->value;
		
		// Parse field values
		$this->values = $this->parseValues($values);
		
		// Get choosen display layout
		$viewlayout = $field->parameters->get('viewlayout', '');
		$viewlayout = $viewlayout && $viewlayout != 'value' ? 'value_'.$viewlayout : 'value';

		// Create field's display
		$this->displayFieldValue($prop, $viewlayout);
	}
	
	
	// Method to handle field's values before they are saved into the DB
	public function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !is_array($post) && !strlen($post) ) return;
	}
	
	
	// Method to take any actions/cleanups needed after field's values are saved into the DB
	public function onAfterSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !is_array($post) && !strlen($post) ) return;
	}
	
	
	// Method called just before the item is deleted to remove custom item data related to the field
	public function onBeforeDeleteField(&$field, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !is_array($post) && !strlen($post) ) return;
	}
	
	
	
	// *********************************
	// CATEGORY/SEARCH FILTERING METHODS
	// *********************************
	
	// Method to display a search filter for the advanced search view
	public function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		FlexicontentFields::createFilter($filter, $value, $formName);
	}
	
	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	public function getFilteredSearch(&$field, $value)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		return FlexicontentFields::getFilteredSearch($field, $value, $return_sql=true);
	}
	
	
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	public function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array('originalname'), $search_properties=array('title','desc'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	public function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array('originalname'), $search_properties=array('title','desc'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	
	// ************************************************
	// Calculate and return the layout path for a field
	// ************************************************
	
	public static function getLayoutPath($plg, $layout = 'field', $plg_subtype='')
	{
		static $paths = array();
		if ( isset($paths[$plg][$layout][$plg_subtype]) )  // return cached
		{
			return $paths[$plg][$layout][$plg_subtype];
		}
		
		$template = JFactory::getApplication('site')->getTemplate();
		$defaultLayout = $layout;

		if (strpos($layout, ':') !== false)
		{
			// Get the template and file name from the string
			$temp = explode(':', $layout);
			$template = ($temp[0] == '_') ? $template : $temp[0];
			$layout = $temp[1];
			$defaultLayout = ($temp[1]) ? $temp[1] : 'field';
		}

		// Build the template and base path for the layout
		$tPath = JPATH_ROOT . '/templates/' . $template . '/html/flexicontent_fields/' . $plg . '/' . ($plg_subtype ? $plg_subtype.'/' : '') . $layout . '.php';
		$fPath = JPATH_ROOT . '/plugins/flexicontent_fields/' . $plg . '/tmpl/' . ($plg_subtype ? $plg_subtype.'/' : '') . $defaultLayout . '.php';
		$dPath = JPATH_ROOT . '/plugins/flexicontent_fields/' . $plg . '/tmpl/' . ($plg_subtype ? $plg_subtype.'/' : '') . 'field.php';

		if (file_exists($tPath))
			// Layout via Joomla template override, in /templates/ folder
			$return = $tPath;
		
		elseif (file_exists($fPath))
			// Layout inside field folder
			$return = $fPath;
		
		else
			// Default fallback
			$return = $dPath;
		
		$paths[$plg][$layout][$plg_subtype] = $return;  // Cache result
		return $return;
	}
	
	
	// Get Layout paths for editing, this is a wrapper to getLayoutPath()
	public function getFormPath($plg, $layout, $plg_subtype='')
	{
		return $this->getLayoutPath($plg, $layout, $plg_subtype);
	}
	
	// Get Layout paths for viewing, this is a wrapper to getLayoutPath()
	public function getViewPath($plg, $layout, $plg_subtype='')
	{
		return $this->getLayoutPath($plg, $layout, $plg_subtype);
	}
	
	
	
	// ****************************************************************************************
	// Include a layout file, setting some variables, for compatibility: $field, $item, $values
	// ****************************************************************************************
	
	protected function includePath($path, $prop='display')
	{
		if (!file_exists($path)) return false;
		$field  = $this->getField();
		$item   = $this->getItem();
		$values = & $this->values;
		include($path);
		return true;
	}
	
	
	
	// *********************************************
	// Function to create field's HTML for edit form
	// *********************************************
	
	protected function displayField($layout = 'field')
	{
		// Prepare variables
		$use_ingroup = 0;
		$multiple = 0;
		$field  = $this->getField();
		$item   = $this->getItem();
		$values = & $this->values;
		
		$field->html = array();
		
		// Include template file: EDIT LAYOUT
		$this->includePath(self::getFormPath($this->fieldtypes[0], $layout), 'html');
		
		if ($use_ingroup) { // do not convert the array to string if field is in a group
		} else if ($multiple) { // handle multiple records
			$field->html = !count($field->html) ? '' :
				'<li class="'.$value_classes.'">'.
					implode('</li><li class="'.$value_classes.'">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			if (!$add_position) $field->html .= '<span class="fcfield-addvalue fccleared" onclick="addField'.$field->id.'(this);" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'"></span>';
		} else {  // handle single values
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . $field->html[0] . (!$use_ingroup && isset($field->html[-1]) ? $field->html[-1] : '') . '</div>';
		}
	}
	
	
	
	// *****************************************************
	// Function to create field's HTML display (for viewing)
	// *****************************************************
	
	protected function displayFieldValue($prop='display', $layout = 'value')
	{
		// Prepare variables
		$use_ingroup = 0;
		$field  = $this->getField();
		$item   = $this->getItem();
		
		$opentag	= $this->getOpenTag();
		$closetag	= $this->getCloseTag();
		$separatorf	= $this->getSeparatorF($opentag, $closetag);
		
		$field->{$prop} = array();
		
		// Execute template file: VALUE VIEWING
		$this->includePath(self::getViewPath($this->fieldtypes[0], $layout), $prop);
		
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
	
	
	
	// *********************************************
	// Functions to execute common value preparation
	// *********************************************
	
	protected function getOpenTag()
	{
		return FlexicontentFields::replaceFieldValue( $this->field, $this->item, $this->field->parameters->get( 'opentag', '' ), 'opentag' );
	}
	protected function getCloseTag()
	{
		return FlexicontentFields::replaceFieldValue( $this->field, $this->item, $this->field->parameters->get( 'closetag', '' ), 'closetag' );
	}
	
	// Do something before creating field's HTML for edit form
	protected function beforeDisplayField() {}
	
	// do something after creating field's HTML for edit form
	protected function afterDisplayField() {}
	
	// Create and returns a default value
	protected function getDefaultValues()
	{
		return array('');
	}
	
	// Parses and returns fields values, unserializing them if serialized
	protected function & parseValues(&$_values)
	{
		$vals = array();
		
		// Check if empty
		if ( empty($_values) ) return $vals;
		
		// Check if already a value array
		$values = isset($_values[0]) ? $_values : array(0 => $_values);
		
		foreach($values as $value)
		{
			$v = !empty($value) ? @unserialize($value) : false;
			if ( $v !== false || $v === 'b:0;' ) {
				$vals[] = $v;
			} else {
				$vals[] = $value;
			}
		}
		return $vals;
	}


	// Get Prefix - Suffix - Separator parameters and other common parameters
	protected function & getCommonParams(& $field, $item, $sep_default = 1)
	{
		static $conf = array();
		if (isset($conf[$field->id]))
		{
			return $conf[$field->id];
		}
		
		// Prefix - Suffix - Separator parameters, replacing other field values if found
		$arr = array();
		$arr['remove_space'] = $field->parameters->get( 'remove_space', 0 ) ;
		$arr['pretext']   = FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'pretext', '' ), 'pretext' );
		$arr['posttext']  = FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'posttext', '' ), 'posttext' );
		$arr['opentag']   = $this->getOpenTag();
		$arr['closetag']  = $this->getCloseTag();
		
		if ($arr['pretext'])
		{
			$arr['pretext']  = $arr['remove_space'] ? $arr['pretext'] : $arr['pretext'] . ' ';
		}
		if ($arr['posttext'])
		{
			$arr['posttext'] = $arr['remove_space'] ? $arr['posttext'] : ' ' . $arr['posttext'];
		}

		$arr['separatorf'] = $this->getSeparatorF($arr['opentag'], $arr['closetag'], $sep_default);
		
		return $arr;
	}


	// Unserialize array from string but abort if objects are detected
	function unserialize_array($v, $force_array=false, $force_value = true)
	{
		return flexicontent_db::unserialize_array($v, $force_array, $force_value);
	}
}