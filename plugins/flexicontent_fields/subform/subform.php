<?php
/**
 * @package         FLEXIcontent
 * @version         3.4
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2020, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

defined( '_JEXEC' ) or die( 'Restricted access' );
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');

class plgFlexicontent_fieldsSubform extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = null;  // Field's methods allowed to be called via AJAX

	// ***
	// *** CONSTRUCTOR
	// ***

	public function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}

	protected function getDefaultValues($isform = true, $translate = true, $split = '')
	{
		return [];
	}
	protected function createSubformField($field, $posted_values = null)
	{
		$multiple   = (int) $field->parameters->get( 'allow_multiple', 0 );
		$min_values = (int) $field->parameters->get('min_values', 0);
		$max_values = (int) $field->parameters->get('max_values', 0);

		$attribs          = $field->parameters->get( 'extra_attributes', '' ) ;
		$subform_form_xml = $field->parameters->get( 'subform_form_xml', '' );
		if (!$subform_form_xml)
		{
			return null;
		}

		// Get posted values or existing field values
		$value = $posted_values !== null ? $posted_values : $this->decodeValues($field);

		// Load the subform field if earlier than Joomla 4
		if (!FLEXI_J40GE)
		{
			jimport('joomla.form.helper'); // \Joomla\CMS\Form\FormHelper
			\Joomla\CMS\Form\FormHelper::loadFieldClass('subform');   // \Joomla\CMS\Form\Field\SubformField
		}

		// Warn user if "multiple" attribute is set in the "extra_attributes" parameter
		if (strpos($attribs, 'multiple="') !== false) {
			Factory::getApplication()->enqueueMessage('Please remove "multiple" attribute from "Extra attributes" parameter of the subform field: ' . $field->label . ' and set the respective parameter in field configuration' , 'warning');
			return null;
		}
		if (strpos($attribs, 'min="') !== false) {
			Factory::getApplication()->enqueueMessage('Please remove "min" attribute from "Extra attributes" parameter of the subform field: ' . $field->label . ' and set the respective parameter in field configuration' , 'warning');
			return null;
		}
		if (strpos($attribs, 'max="') !== false) {
			Factory::getApplication()->enqueueMessage('Please remove "max" attribute from "Extra attributes" parameter of the subform field: ' . $field->label . ' and set the respective parameter in field configuration' , 'warning');
			return null;
		}

		// Add layout attribute if not already set in the "extra_attributes" parameter
		if (strpos($attribs, 'layout="') === false) $attribs .= ' layout="joomla.form.field.subform.'. ($multiple ? 'repeatable' : 'default') . '"';
		// Add buttons attribute if not already set in the "extra_attributes" parameter
		if (strpos($attribs, 'buttons="') === false) $attribs .= ' buttons="add,remove,move" ';

		// Add multiple, min, max attributes
		$attribs .= ' multiple="' . ($multiple ? 'true' : 'false') . '"';
		if ($min_values) $attribs .= ' min="'. $min_values .'"';
		if ($max_values) $attribs .= ' max="'. $max_values .'"';

		// Add label attribute if not set in the "extra_attributes" parameter
		if (strpos($attribs, 'label') === false) $attribs .= ' label="'. $field->label .'"'; // Currently not used by item form

		// Create field's XML
		$xml_field = '<field name="'.$field->name.'"  type="subform" ' . $attribs . '>';
		$xml_field .= $subform_form_xml . '</field>';
		$xml_form = '<form><fields name="attribs"><fieldset name="attribs">'.$xml_field.'</fieldset></fields></form>';

		/**
		 * Create a new form object and load XML of (subform) fields
		 * NOTE: if values are posted it is best to use "jform" as form's control for better compatibility
		 */
		$jform = new \Joomla\CMS\Form\Form('flexicontent_field.subform', array(
			'control' => ($posted_values !== null ? 'jform' : 'custom'),
			'load_data' => true)
		);
		$jform->load($xml_form);

		// Instantiate the subform field
		$jfield = FLEXI_J40GE
			? new \Joomla\CMS\Form\Field\SubformField($jform)
			: new JFormFieldSubform($jform);

		// Initialize the subform field with the field values
		$jfield->setup(new SimpleXMLElement($xml_field), $value, '');

		return $jfield;
	}


	protected function decodeValues($field)
	{
		if (!empty($field->valuesDecoded)) return $field->value;
		$field->valuesDecoded = true;

		$value = is_array($field->value) ? $field->value : array($field->value);
		foreach($value as $i => $v)
		{
			$value[$i] = is_string($v) ? json_decode($v, true) : $v;
			if ($value[$i] === null) unset($value[$i]);
		}
		return $field->value = $value;
	}

	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	public function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = $field->parameters->get('label_form') ? Text::_($field->parameters->get('label_form')) : Text::_($field->label);

		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);

		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if (!isset($field->formhidden_grp)) $field->formhidden_grp = $field->formhidden;
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;

		/**
		 * Check if using 'auto_value_code', clear 'auto_value', if function not set
		 */
		$auto_value = (int) $field->parameters->get('auto_value', 0);
		if ($auto_value === 2)
		{
			$auto_value_code = $field->parameters->get('auto_value_code', '');
			$auto_value_code = preg_replace('/^<\?php(.*)(\?>)?$/s', '$1', $auto_value_code);
		}
		$auto_value = $auto_value === 2 && !$auto_value_code ? 0 : $auto_value;

		// Initialize framework objects and other variables
		$document = Factory::getApplication()->getDocument();
		$cparams  = \Joomla\CMS\Component\ComponentHelper::getParams( 'com_flexicontent' );

		$tooltip_class = 'hasTooltip';
		$add_on_class    = $cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
		$input_grp_class = $cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';
		$btn_item_class  = $cparams->get('bootstrap_ver', 2)==2  ?  'btn' : 'btn';
		$btn_group_class = $cparams->get('bootstrap_ver', 2)==2  ?  'btn-group' : 'btn-group';
		$form_font_icons = $cparams->get('form_font_icons', 1);
		$font_icon_class = $form_font_icons ? ' fcfont-icon' : '';
		$font_icon_class .= FLEXI_J40GE ? ' icon icon- ' : '';


		/**
		 * Number of values
		 */

		$multiple     = $use_ingroup || (int) $field->parameters->get('allow_multiple', 0);
		$max_values   = $use_ingroup ? 0 : (int) $field->parameters->get('max_values', 0);
		$required     = (int) $field->parameters->get('required', 0);
		$add_position = (int) $field->parameters->get('add_position', 3);

		// Classes for marking field required
		$required_class = $required ? ' required' : '';

		// If we are multi-value and not inside fieldgroup then add the control buttons (move, delete, add before/after)
		$add_ctrl_btns = !$use_ingroup && $multiple;
		$fields_box_placing = (int) $field->parameters->get('fields_box_placing', 1);


		/**
		 * Value handling
		 */

		// Default value(s)
		$default_values = $this->getDefaultValues($isform = true);
		$default_value  = reset($default_values);


		/**
		 * Form field display parameters
		 */

		$display_label_form = (int) $field->parameters->get( 'display_label_form', 1 ) ;

		// Create extra HTML TAG parameters for the form field
		$attribs = $field->parameters->get( 'extra_attributes', '' ) ;
		if ($auto_value) $attribs .= ' readonly="readonly" ';

		// Attribute for default value(s)
		if (!empty($default_values))
		{
			$attribs .= ' data-defvals="'.htmlspecialchars( implode('|||', $default_values), ENT_COMPAT, 'UTF-8' ).'" ';
		}

		// Custom HTML placed before / after form fields
		$pretext  = $field->parameters->get( 'pretext_form', '' ) ;
		$posttext = $field->parameters->get( 'posttext_form', '' ) ;

		// Initialise property with default value
		if (!$field->value || (count($field->value) === 1 && reset($field->value) === null))
		{
			$field->value = $default_values;
		}

		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		$value_classes .= $fields_box_placing ? ' floated' : '';

		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;

		// JS safe Field name
		$field_name_js = str_replace('-', '_', $field->name);

		// JS & CSS of current field
		$js = '';
		$css = '';

		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);

		$classes  = ' fcfield_textval' . $required_class;

		// Set field to 'Automatic' on successful validation'
		if ($auto_value)
		{
			$classes = ' fcfield_auto_value ';
		}


		/**
		 * Create field's HTML display for item form
		 */
		$jfield = $this->createSubformField($field, $posted_values = null);

		if (!$jfield)
		{
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">
				' . Text::_( 'FLEXI_SUBFORM_FORM_XML_MISSING_FROM_CONFIGURATION' ) . '
			</div>';
			return;
		}
		$jfield_html = $jfield->input;

		$field->html[] = $pretext . '
			' . $jfield_html . '
			' . $posttext
			;

		// Do not convert the array to string if field is in a group
		if ($use_ingroup) { $field->html = ['You cannot use a subform field inside a field group']; }

		// Handle single / multiple values (multiple values are handled by the subform field itself)
		else
		{
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">
				' . (isset($field->html[-1]) ? $field->html[-1] : '') . $field->html[0] . '
			</div>';
		}
	}


	// Method to create field's HTML display for frontend views
	public function onDisplayFieldValue(&$field, $item, $values = null, $prop = 'display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = Text::_($field->label);

		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);


		/**
		 * One time initialization
		 */

		static $initialized = null;
		static $app, $document, $option, $format, $realview;

		if ($initialized === null)
		{
			$initialized = 1;

			$app       = Factory::getApplication();
			$document  = Factory::getApplication()->getDocument();
			$option    = $app->input->getCmd('option', '');
			$format    = $app->input->getCmd('format', 'html');
			$realview  = $app->input->getCmd('view', '');
		}

		// Current view variable
		$view = $app->input->getCmd('flexi_callview', ($realview ?: 'item'));
		$sfx = $view === 'item' ? '' : '_cat';

		// Check if field should be rendered according to configuration
		if (!$this->checkRenderConds($prop, $view))
		{
			return;
		}

		// The current view is a full item view of the item
		$isMatchedItemView = static::$itemViewId === (int) $item->id;

		// Some variables
		$is_ingroup  = !empty($field->ingroup);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$multiple    = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;


		/**
		 * Get field values
		 */

		$values = $this->decodeValues($field);

		// Check for no values and no default value, and return empty display
		if (empty($values))
		{
			$values = $this->getDefaultValues($isform = false);

			if (!count($values))
			{
				$field->{$prop} = $is_ingroup ? array() : '';
				return;
			}
		}


		/**
		 * Get common parameters like: itemprop, value's prefix (pretext), suffix (posttext), separator, value list open/close text (opentag, closetag)
		 * This will replace other field values and item properties, if such are found inside the parameter texts
		 */
		$common_params_array = $this->getCommonParams();
		extract($common_params_array);


		// CSV export: Create a simpler output
		if ($prop === 'csv_export')
		{
			$separatorf = ', ';
			$itemprop = false;
		}


		// Get layout name
		$viewlayout = $field->parameters->get('viewlayout', '');
		$viewlayout = $viewlayout ? 'value_'.$viewlayout : 'value_default';

		// Create field's viewing HTML, using layout file
		$field->{$prop} = array();
		include(self::getViewPath($field->field_type, $viewlayout));

		// Do not convert the array to string if field is in a group, and do not add: FIELD's opentag, closetag, value separator
		if (!$is_ingroup)
		{
			// Apply values separator
			$field->{$prop} = implode($separatorf, $field->{$prop});

			if ($field->{$prop} !== '')
			{
				// Apply field 's opening / closing texts
				$field->{$prop} = $opentag . $field->{$prop} . $closetag;

				// Add microdata once for all values, if field -- is NOT -- in a field group
				if ($itemprop)
				{
					$field->{$prop} = '<div style="display:inline" itemprop="'.$itemprop.'" >' .$field->{$prop}. '</div>';
				}
			}
		}
	}



	// ***
	// *** METHODS HANDLING before & after saving / deleting field events
	// ***

	// Method to handle field's values before they are saved into the DB
	public function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ( !is_array($post) && !strlen($post) && !$use_ingroup ) return;

		// Server side validation
		$required   = (int) $field->parameters->get( 'required', 0 ) ;
		$validation = 'STRING';
		$maxlength  = 32;


		// ***
		// *** Reformat the posted data
		// ***

		// Make sure posted data is an array
		$post = !is_array($post) ? array($post) : $post;

		$subFormField = $this->createSubformField($field, $post);
		$subForm      = $subFormField->loadSubForm();

		/**
		 * Filter the subform posted data
		 */
		$post = $subFormField->filter($post);

		$newpost = array();
		$new = 0;

		// Preserve array KEY if not multiple !!!		
		$multiple = (int) $field->parameters->get( 'allow_multiple', 0 );
		if (!$multiple) $post = array($post);

		foreach ($post as $n => $v)
		{
			/**
			 * Validate every posted value of the subform (field)
			 */
			if ($subForm->validate($v) === false) {
				// Pass the first error that occurred on the subform validation.
				$errors = $subForm->getErrors();

				Factory::getApplication()->enqueueMessage(Text::sprintf('JLIB_FORM_VALIDATE_FIELD_INVALID', $field->label), 'error');
				if (!empty($errors[0]))
				{
					Factory::getApplication()->enqueueMessage($errors[0]->getMessage(), 'error');
				}

				$v = null;
			}

			// JSON Encode every posted value of the subform (field)
			$v = json_encode($v);
			$post[$n] = $v;

			// Skip empty value, but if in group increment the value position
			if (!strlen($post[$n]))
			{
				if ($use_ingroup) $newpost[$new++] = null;
				continue;
			}

			$newpost[$new] = $post[$n];
			$new++;
		}

		$post = $newpost;
	}


	// Method to take any actions/cleanups needed after field's values are saved into the DB
	public function onAfterSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}


	// Method called just before the item is deleted to remove custom item data related to the field
	public function onBeforeDeleteField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}



	// ***
	// *** CATEGORY/SEARCH FILTERING METHODS
	// ***

	// Method to display a search filter for the advanced search view
	public function onAdvSearchDisplayFilter(&$filter, $value = '', $formName = 'searchForm')
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$this->onDisplayFilter($filter, $value, $formName, $isSearchView=1);
	}


	// Method to display a category filter for the category view
	public function onDisplayFilter(&$filter, $value = '', $formName = 'adminForm', $isSearchView = 0)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		FlexicontentFields::createFilter($filter, $value, $formName);
	}


	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for content lists e.g. category view, and not for search view
	public function getFiltered(&$filter, $value, $return_sql = true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		return FlexicontentFields::getFiltered($filter, $value, $return_sql);
	}


	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	public function getFilteredSearch(&$filter, $value, $return_sql = true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		return FlexicontentFields::getFilteredSearch($filter, $value, $return_sql);
	}



	// ***
	// *** SEARCH INDEX METHODS
	// ***

	// Method to create (insert) advanced search index DB records for the field values
	public function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;

		// a. Each of the values of $values array will be added to the advanced search index as searchable text (column value)
		// b. Each of the indexes of $values will be added to the column 'value_id',
		//    and it is meant for fields that we want to be filterable via a drop-down select
		// c. If $values is null then only the column 'value' will be added to the search index after retrieving
		//    the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   contains property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func='strip_tags');
		return true;
	}


	// Method to create basic search index (added as the property field->search)
	public function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->issearch ) return;

		// a. Each of the values of $values array will be added to the basic search index (one record per item)
		// b. If $values is null then the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   contains property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func='strip_tags');
		return true;
	}



	// ***
	// *** VARIOUS HELPER METHODS
	// ***
}
