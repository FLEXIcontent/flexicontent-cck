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

defined( '_JEXEC' ) or die( 'Restricted access' );
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');

class plgFlexicontent_fieldsLinkslist extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = null;  // Field's methods allowed to be called via AJAX
	protected $_attribs = array();

	// ***
	// *** CONSTRUCTOR
	// ***

	public function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}



	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	public function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = $field->parameters->get('label_form') ? JText::_($field->parameters->get('label_form')) : JText::_($field->label);

		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);

		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if (!isset($field->formhidden_grp)) $field->formhidden_grp = $field->formhidden;
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;

		/**
		 * Number of values
		 */

		$required     = (int) $field->parameters->get('required', 0);

		// Classes for marking field required
		$required_class = $required ? ' required' : '';

		// Field's elements
		$field_elements	= $field->parameters->get('field_elements');

		// Default value(s)
		$default_values	= $field->parameters->get('default_values', '');


		/**
		 * Prefix - Suffix - Separator (item FORM) parameters
		 */

		// Custom HTML placed before / after list elements
		$pretext  = $field->parameters->get( 'pretext_form', '' ) ;
		$posttext = $field->parameters->get( 'posttext_form', '' ) ;

		// List elements separator and list's prefix and suffix ()
		$separator		= $field->parameters->get( 'separator', 0 ) ;
		$opentag			= $field->parameters->get( 'opentag_form', '' ) ;
		$closetag			= $field->parameters->get( 'closetag_form', '' ) ;

		switch($separator)
		{
			case 0:
			$separator = '&nbsp;';
			break;

			case 1:
			$separator = '<br />';
			break;

			case 2:
			$separator = '&nbsp;|&nbsp;';
			break;

			case 3:
			$separator = ',&nbsp;';
			break;

			case 4:
			$separator = $closetag . $opentag;
			break;

			default:
			$separator = '&nbsp;';
			break;
		}

		// Initialise default values
		if ($item->version == 0 && $default_values)
		{
			$field->value = explode(',', $default_values);
		}
		elseif (!$field->value || (count($field->value) === 1 && reset($field->value) === null))
		{
			$field->value = array();
			$field->value[0] = '';
		}

		if (strlen($field_elements) === 0)
		{
			return $field->html = '
				<div id="fc-change-error" class="fc-error">
					Please enter at least one item. Example:
					<pre style="display:inline-block; margin:0">
						{"item1":{"name":"Item1"},"item2":{"name":"Item2"}}
					</pre>
				</div>';
		}

		/**
		 * Parse list elements, and create HTML of list elements
		 */
		$elements = $this->parseElements($field, $field_elements);

		$fieldname = 'custom['.$field->name.'][]';
		$elementid = 'custom_'.$field->name;

		$options = array();

		// Render as multi-select form field
		if ( $field->parameters->get( 'editform_field_type', 1 ) == 2 )
		{
			foreach ($elements as $li_title => $li_params)
			{
				$options[] = JHtml::_('select.option', $li_title, $li_title);
			}
			$field->html	= JHtml::_('select.genericlist', $options, $fieldname, 'class="use_select2_lib' . $required_class . '" multiple="multiple"', 'value', 'text', $field->value, $elementid);
		}

		// Render as checkboxes
		else
		{
			$n = 0;
			foreach ($elements as $li_title => $li_title)
			{
				$checked  = in_array($li_title, $field->value) ? ' checked="checked"' : null;
				$options[] = ''
					.$pretext
					.'<input type="checkbox" class="'.$required.'" name="'.$fieldname.'" value="'.htmlspecialchars($li_title, ENT_COMPAT, 'UTF-8').'" id="'.$elementid.'_'.$n.'"'.$checked.' />'
					.'<label for="'.$elementid.'_'.$n.'">'.$li_title.'</label>'
					.$posttext
					;
				$n++;
			}

			// Apply values separator
			$field->html = implode($separator, $options);

			// Apply field 's opening / closing texts
			if ($field->html)
			{
				$field->html = $opentag . $field->html . $closetag;
			}
		}

	}


	// Method to create field's HTML display for frontend views
	public function onDisplayFieldValue(&$field, $item, $values = null, $prop = 'display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = JText::_($field->label);

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

			$app       = JFactory::getApplication();
			$document  = JFactory::getDocument();
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
		$add_non_selected = (int) $field->parameters->get('add_non_selected', 0);
		$field_elements = $field->parameters->get('field_elements', '');



		// Get list type and its list TAG parameters
		$list_type  = $field->parameters->get('list_type', 'ul');
		$list_class = $field->parameters->get('list_class', '');
		$list_id    = $field->parameters->get('list_id', '');

		/**
		 * Get field values
		 */

		$values = $values ? $values : $field->value;

		/**
		 * Parse list elements, and create HTML of list elements
		 */
		$elements       = $this->parseElements($field, $field_elements);

		/**
		 * Create TAG parameters of the LIST tag
		 */
		$list_params = '';

		if ($list_class)
		{
			$list_params .= ' class="' . $list_class . '"' ;
		}
		if ($list_id)
		{
			$list_params .= ' id="' . $list_id . '"' ;
		}


		/**
		 * Create HTML of list elements
		 */
		$options = array();
		foreach($elements as $li_title => $li_params)
		{
			$is_selected = in_array($li_title, $values);
			if ( !$add_non_selected && !$is_selected ) continue;

			$prefix = $suffix = '';
			if ($is_selected)
			{
				if (isset($li_params['link']))
				{
					$prefix = '<a href="'.$li_params['link'].'">';
					$suffix = '</a>';
				} else {
					$prefix = '<span class="fc_linklist_text_only" >';
					$suffix = '</span>';
				}
			}
			else
			{
				$prefix = '<span class="fc_linklist_non_selected" >';
				$suffix = '</span>';
			}

			unset($li_params['link']);

			array_walk($li_params, array($this, 'walk'), $li_title);
			$li_params = $li_params ? ' '.implode(' ', $li_params) : null;
			$options[] = '<li'.$li_params.'>'.$prefix.$li_title.$suffix.'</li>';
		}

		static $js_code_added = null;

		if ($js_code_added === null)
		{
			$js_code = $field->parameters->get( 'js_code', '' ) ;
			if ($js_code)  JFactory::getDocument()->addScriptDeclaration($js_code);
			$js_code_added = true;
		}

		static $css_code_added = null;

		if ($css_code_added === null)
		{
			$css_code = $field->parameters->get( 'css_code', '' ) ;
			if ($css_code) JFactory::getDocument()->addStyleDeclaration($css_code);
			$css_code_added = true;
		}

		// Create the HTML of the list
		if (!count($options)) return $field->{$prop} = '';
		return $field->{$prop} = '
			<' . $list_type . $list_params . '>
				' . implode($options) . '
			</'.$list_type.'>
		';
	}



	// ***
	// *** METHODS HANDLING before & after saving / deleting field events
	// ***

	// Method to handle field's values before they are saved into the DB
	public function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if(!is_array($post) && !strlen($post)) return;

		// create the fulltext search index
		if ($field->issearch) {
			$searchindex = '';

			$field_elements = $field->parameters->get( 'field_elements', '' ) ;
			$elements = $this->parseElements($field, $field_elements);

			$searchindex  = implode(' ', array_keys($elements));
			$searchindex .= ' | ';

			$field->search = $searchindex;
		} else {
			$field->search = '';
		}
	}


	// Method to take any actions/cleanups needed after field's values are saved into the DB
	public function onAfterSaveField( &$field, &$post, &$file, &$item )
	{
	}


	// Method called just before the item is deleted to remove custom item data related to the field
	public function onBeforeDeleteField(&$field, &$item)
	{
	}



	// ***
	// *** CATEGORY/SEARCH FILTERING METHODS
	// ***

	// Method to display a search filter for the advanced search view
	/*function onAdvSearchDisplayFilter(&$filter, $value = '', $formName = 'searchForm')
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$size = (int)$filter->parameters->get( 'size', 30 );
		$filter->html	='<input name="filter_'.$filter->id.'" class="fc_field_filter" type="text" size="'.$size.'" value="'.htmlspecialchars($value, ENT_COMPAT, 'UTF-8').'" />';
	}*/


	// Method to display a category filter for the category view
	public function onDisplayFilter(&$filter, $value = '', $formName = 'adminForm', $isSearchView = 0)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		// some parameter shortcuts
		$field_elements = $filter->parameters->get( 'field_elements' ) ;
		$elements = $this->parseElements($filter, $field_elements);

		$options = array();
		$options[] = JHtml::_('select.option', '', '-'.JText::_('FLEXI_ALL').'-');
		foreach ($elements as $title => $val)
		{
			$options[] = JHtml::_('select.option', $title, $title);
		}

		$filter->html	= JHtml::_('select.genericlist', $options, 'filter_'.$filter->id, ' class="fc_field_filter" onchange="document.getElementById(\''.$formName.'\').submit();"', 'value', 'text', $value);
	}



	// ***
	// *** VARIOUS HELPER METHODS
	// ***

	private function parseElements(&$field, &$field_elements)
	{
		static $elements_arr = array();
		if (isset($elements_arr[$field->id])) return $elements_arr[$field->id];

		$listelements = array_map('trim', preg_split('/\s*::\s*/', $field_elements));
		$elements = $matches = array();
		foreach($listelements as $listelement)
		{
			preg_match("/\[(.*)\]/i", $listelement, $matches);
			$name = trim(preg_replace("/\s*\[(.*)\]\s*/i", '', $listelement));
			if (isset($matches[1]))
			{
				$attribs	  = array();
				$parts  	  = explode('"', str_replace('="', '"', $matches[1]));
				$length		  = count($parts);
				$range		  = range(0, $length, 2);
				foreach($range as $i)
				{
					if(!isset($parts[$i+1])) continue;
					$attribs[trim($parts[$i])] = $parts[$i+1];
				}
				$elements[$name] = array_merge($this->_attribs, $attribs);
			}
			else
			{
				$elements[$name] = $this->_attribs;
			}
		}

		$elements_arr[$field->id] = $elements;
		return $elements;
	}


	function walk(&$value, $key)
	{
		if($key == 'href') $value = false;
		$value = $key.'="'.$value.'"';
	}

}
