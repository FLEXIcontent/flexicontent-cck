<?php
/**
 * @package         FLEXIcontent
 * @version         3.2
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2017, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
jimport('cms.plugin.plugin');

class FCField extends JPlugin
{
	// ***
	// *** ATTRIBUTES
	// ***

	protected $fieldtypes = null;
	protected $field = null;
	protected $item = null;
	protected $vars = null;
	protected $autoloadLanguage = false;


	// ***
	// *** CONSTRUCTOR
	// ***

	public function __construct(&$subject, $params)
	{
		parent::__construct( $subject, $params );

		$class = strtolower(get_class($this));
		$fieldtype = str_replace('plgflexicontent_fields', '', $class);

		static::$field_types = static::$field_types ?: array($fieldtype);
		if (empty($this->fieldtypes))
		{
			$this->fieldtypes = static::$field_types;
		}

		// Load extra field types and their language files if these have not be loaded already
		static $initialized = array();
		foreach(static::$field_types as $ft)
		{
			if ( isset($initialized[$ft]) ) continue;
			$initialized[$ft] = true;

			// Because 'site-default' language file may not have all needed language strings, or it may be syntactically broken
			// we load the ENGLISH language file (without forcing it, to avoid overwritting site-default), and then current language file
			$extension_name = 'plg_flexicontent_fields_'.$ft;
			JFactory::getLanguage()->load($extension_name, JPATH_ADMINISTRATOR, 'en-GB', $force_reload = false, $load_default = true);  // force_reload OFF
			JFactory::getLanguage()->load($extension_name, JPATH_ADMINISTRATOR, null, $force_reload = true, $load_default = true);  // force_reload ON

			// Import field type if not already imported
			$class_name = 'plgFlexicontent_fields' . ucfirst($ft);
			if (! class_exists($class_name))
			{
				JPluginHelper::importPlugin('flexicontent_fields', $ft);
			}
		}
	}



	// ***
	// *** Accessor functions
	// ***

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
		if ( !in_array($field->field_type, static::$field_types) ) return;
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
		if ( !in_array($field->field_type, static::$field_types) ) return;
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
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !is_array($post) && !strlen($post) ) return;
	}


	// Method to take any actions/cleanups needed after field's values are saved into the DB
	public function onAfterSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !is_array($post) && !strlen($post) ) return;
	}


	// Method called just before the item is deleted to remove custom item data related to the field
	public function onBeforeDeleteField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}



	// *********************************
	// CATEGORY/SEARCH FILTERING METHODS
	// *********************************

	// Method to display a search filter for the advanced search view
	public function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		FlexicontentFields::createFilter($filter, $value, $formName);
	}

	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	public function getFilteredSearch(&$field, $value)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		return FlexicontentFields::getFilteredSearch($field, $value, $return_sql=true);
	}



	// *************************
	// SEARCH / INDEXING METHODS
	// *************************

	// Method to create (insert) advanced search index DB records for the field values
	public function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;

		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array('originalname'), $search_properties=array('title','desc'), $properties_spacer=' ', $filter_func=null);
		return true;
	}


	// Method to create basic search index (added as the property field->search)
	public function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->issearch ) return;

		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array('originalname'), $search_properties=array('title','desc'), $properties_spacer=' ', $filter_func=null);
		return true;
	}



	// ************************************************
	// Calculate and return the layout path for a field
	// ************************************************

	public static function getLayoutPath($plg, $layout = 'field', $plg_subtype='', $default_layout='field')
	{
		static $paths = array();
		static $template = null;

		if ( isset($paths[$plg][$layout][$plg_subtype]) )  // return cached
		{
			return $paths[$plg][$layout][$plg_subtype];
		}

		if ($template === null)
		{
			$template = JFactory::getApplication('site')->getTemplate();
		}

		// Get the template and layout name from the string if string contains ':' (aka folder seperators)
		if (strpos($layout, ':') !== false)
		{
			$temp = explode(':', $layout);
			$template = ($temp[0] == '_') ? $template : $temp[0];
			$layout = $temp[1];
		}

		// ***
		// *** Build the template and base path for the layout, and check it exists, returing it, in order of priority
		// ***

		// Layout via Joomla template override, in /templates/ folder
		if (file_exists(($tPath = JPATH_ROOT . '/templates/' . $template . '/html/flexicontent_fields/' . $plg . '/' . ($plg_subtype ? $plg_subtype.'/' : '') . $layout . '.php')))
			$return = $tPath;

		// Layout inside field folder
		elseif (file_exists($fPath = JPATH_ROOT . '/plugins/flexicontent_fields/' . $plg . '/tmpl/' . ($plg_subtype ? $plg_subtype.'/' : '') . $layout . '.php'))
			$return = $fPath;

		// Default fallback
		elseif (file_exists($dPath = JPATH_ROOT . '/plugins/flexicontent_fields/' . $plg . '/tmpl/' . ($plg_subtype ? $plg_subtype.'/' : '') . $default_layout . '.php'))
			$return = $dPath;

		// Default fallback (strip '_default')
		else
			$return = $dPath = JPATH_ROOT . '/plugins/flexicontent_fields/' . $plg . '/tmpl/' . ($plg_subtype ? $plg_subtype.'/' : '') . str_replace('_default', '', $default_layout) . '.php';

		$paths[$plg][$layout][$plg_subtype] = $return;  // Cache result
		return $return;
	}


	// Get Layout paths for editing, this is a wrapper to getLayoutPath()
	public function getFormPath($plg, $layout, $plg_subtype='')
	{
		return $this->getLayoutPath($plg, $layout ?: 'field', $plg_subtype, $default='field_default');
	}

	// Get Layout paths for viewing, this is a wrapper to getLayoutPath()
	public function getViewPath($plg, $layout, $plg_subtype='')
	{
		return $this->getLayoutPath($plg, $layout ?: 'value', $plg_subtype, $default_layout='value_default');
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

		$opentag   = FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'opentag', '' ), 'opentag' );
		$closetag  = FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'closetag', '' ), 'closetag' );
		$separatorf	= $this->getSeparatorF($opentag, $closetag, 1);

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



	// ***
	// *** Functions to execute common value preparation
	// ***

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
	protected function parseValues($values)
	{
		$vals = array();

		// Check if empty
		if (empty($values))
		{
			return $vals;
		}

		// Make sure we have an array of values
		$values = is_array($values) ? $values : array($values);

		foreach($values as $value)
		{
			// Compatibility for non-serialized values or for NULL values in a field group
			if (!is_array($value))
			{
				$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
				$vals[] = $array ?: array();
			}
		}

		return $vals;
	}


	// Get Prefix - Suffix - Separator parameters and other common parameters
	protected function & getCommonParams($sep_default = 1)
	{
		static $conf = array();
		if (isset($conf[$this->field->id]))
		{
			return $conf[$this->field->id];
		}

		// Prefix - Suffix - Separator parameters, replacing other field values if found
		$arr = array();
		$arr['remove_space'] = $this->field->parameters->get( 'remove_space', 0 ) ;
		$arr['pretext']   = FlexicontentFields::replaceFieldValue( $this->field, $this->item, $this->field->parameters->get( 'pretext', '' ), 'pretext' );
		$arr['posttext']  = FlexicontentFields::replaceFieldValue( $this->field, $this->item, $this->field->parameters->get( 'posttext', '' ), 'posttext' );
		$arr['opentag']   = FlexicontentFields::replaceFieldValue( $this->field, $this->item, $this->field->parameters->get( 'opentag', '' ), 'opentag' );
		$arr['closetag']  = FlexicontentFields::replaceFieldValue( $this->field, $this->item, $this->field->parameters->get( 'closetag', '' ), 'closetag' );

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


	// Create once the options for every field property that has specific selection options (e.g. drop down-selection)
	function getPropertyOptions($choices, $default_option = null)
	{
		// Parse the elements used by field unsetting last element if empty
		$choices = preg_split("/[\s]*%%[\s]*/", $choices);
		if ( empty($choices[count($choices)-1]) )
		{
			unset($choices[count($choices)-1]);
		}

		// Split elements into their properties: value, label, extra_prop1, extra_prop2
		$elements = array();
		$k = 0;
		foreach ($choices as $choice)
		{
			$choice_props  = preg_split("/[\s]*::[\s]*/", $choice);
			if (count($choice_props) < 2)
			{
				echo "Error in field: ".$field->label.
					" while splitting class element: ".$choice.
					" properties needed: ".$props_needed.
					" properties found: ".count($choice_props);
				continue;
			}
			$elements[$k] = new stdClass();
			$elements[$k]->value = $choice_props[0];
			$elements[$k]->text  = $choice_props[1];
			$k++;
		}

		// Create the options for select drop down
		$options = array();
		if ($default_option === null)
		{
			$options[] = JHtml::_('select.option', '', '-');
		}
		else
		{
			foreach ($elements as $element)
			{
				$default_label = $default_option->label;
				if ($element->value === $default_option->value)
				{
					$default_label .= ' - (' . JText::_($element->text) . ')';
				}
			}
			$options[] = JHtml::_('select.option', '', $default_label);
		}
		foreach ($elements as $element)
		{
			$options[] = JHtml::_('select.option', $element->value, JText::_($element->text));
		}
		return $options;
	}


	// Get existing field values
	function getExistingFieldValues()
	{
		$db = JFactory::getDbo();
		$query = 'SELECT value '
			. ' FROM #__flexicontent_fields_item_relations '
			. ' WHERE '
			. '  field_id='. $db->Quote($this->field->id)
			. '  AND item_id='. $db->Quote($this->item->id)
			. ' ORDER BY valueorder'
			;
		$db->setQuery($query);
		$values = $db->loadColumn();
		return $values;
	}


	// Get existing field values
	function renameLegacyFieldParameters($map)
	{
		// Load parameters directly from DB
		$db = JFactory::getDbo();
		$query = 'SELECT attribs'
			. ' FROM #__flexicontent_fields'
			. ' WHERE '
			. '  id='. $db->Quote($this->field->id)
			;
		$db->setQuery($query);
		$attribs = $db->loadResult();

		// Decode parameters
		$_attribs = json_decode($attribs);

		// Set old parameter values into new parameters, removing the old parameter values
		foreach($map as $old => $new)
		{
			if (isset($_attribs->$old))
			{
				// Set new parameter value and remove legacy parameter value
				$_attribs->$new = $_attribs->$old;
				unset($_attribs->$old);

				// Update existing parameters object, to avoid reload field parameters
				$this->field->parameters->set($new, $_attribs->$new);
			}
		}

		// Re-encode parameters
		$attribs = json_encode($_attribs);

		// Store field parameter back to the DB
		$query = 'UPDATE #__flexicontent_fields'
			.' SET attribs=' . $db->Quote($attribs)
			.' WHERE id = ' . $this->field->id;
		$db->setQuery($query);
		$db->execute();
	}
}