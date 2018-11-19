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

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');

class plgFlexicontent_fieldsCoreprops extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = null;  // Field's methods allowed to be called via AJAX

	// ***
	// *** CONSTRUCTOR
	// ***

	function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}



	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$props_type = $field->parameters->get( 'props_type' ) ;

		$field->html = '';
	}


	// Method to create field's HTML display for frontend views
	public function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		static $all_langs = null;
		static $cat_links = array();
		static $acclvl_names = null;

		$remove_space = $field->parameters->get( 'remove_space', 0 ) ;
		$pretext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'pretext', '' ), 'pretext' );
		$posttext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'posttext', '' ), 'posttext' );
		$separatorf	= $field->parameters->get( 'separatorf', 1 ) ;
		$opentag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'opentag', '' ), 'opentag' );
		$closetag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'closetag', '' ), 'closetag' );

		// Microdata (classify the field values for search engines)
		$itemprop    = $field->parameters->get('microdata_itemprop');

		if($pretext)  { $pretext  = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) { $posttext = $remove_space ? $posttext : ' ' . $posttext; }

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

		// Cleaner output for CSV export
		if ($prop === 'csv_export')
		{
			$separatorf = ', ';
			$itemprop = false;
		}

		$props_type = $field->parameters->get('props_type');
		switch($props_type)
		{
			case 'lang':
			case 'language':
				if ($all_langs===null)
				{
					$all_langs= FLEXIUtilities::getLanguages($hash='code');
				}
				$lang_data = $all_langs->{$item->language};

				$field->{$prop} = $lang_data && $lang_data->code !== '*' && $lang_data->title_native ? $lang_data->title_native : $lang_data->name;
				break;

			case 'category':
				$link_maincat = $prop === 'csv_export' ? 0 : (int) $field->parameters->get('link_maincat', 1);

				if ($link_maincat)
				{
					$maincatid = isset($item->maincatid) ? $item->maincatid : $item->catid;   // maincatid is used by item view
					if ( !isset($cat_links[$maincatid]) )
					{
						$maincat_slug = $item->maincatid  ?  $item->maincatid.':'.$item->maincat_alias : $item->catid;
						$cat_links[$maincatid] = JRoute::_(FlexicontentHelperRoute::getCategoryRoute($maincat_slug));
					}
				}

				$maincat_title =  !empty($item->maincat_title) ? $item->maincat_title : $item->categoryslug;
				$field->{$prop} = $link_maincat
					? '<a class="fc_coreprop fc_maincat link_' .$field->name. '" href="' . $cat_links[$maincatid] . '">' . $maincat_title . '</a>'
					: $maincat_title;
				break;

			case 'access':
				if ($acclvl_names===null)
				{
					$acclvl_names = flexicontent_db::getAccessNames();
				}
				$field->{$prop} = isset($acclvl_names[$item->access])  ?  $acclvl_names[$item->access]  :  'unknown access level id: '.$item->access;
				break;

			/**
			 * Try to use the item property as display, and if not found,
			 * then indicate NOT IMPLEMENTED by using property name as field DISPLAY
			 */
			default:
				$field->{$prop} = isset($item->{$props_type}) ? $item->{$props_type} : $props_type;
				break;
		}

		if (strlen($field->{$prop})) {
			$field->{$prop} = $opentag.$pretext. $field->{$prop} .$posttext.$closetag;
		}
	}



	// ***
	// *** CATEGORY/SEARCH FILTERING METHODS
	// ***

	// Method to display a search filter for the advanced search view
	function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$props_type = $filter->parameters->get('props_type');
		//if ($props_type == 'language') {
		//	$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		//}

		//$indexed_elements = in_array($props_type, array('language'));

		$this->onDisplayFilter($filter, $value, $formName, $isSearchView=1);
		//if ($props_type =='...') {
		//	$this->onDisplayFilter($filter, $value, $formName, $isSearchView=1);
		//} else {
		//	FlexicontentFields::createFilter($filter, $value, $formName, $indexed_elements);
		//}
	}




	// Method to display a category filter for the category view
	function onDisplayFilter(&$filter, $value='', $formName='adminForm', $isSearchView=0)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$db = JFactory::getDbo();
		$formfieldname = 'filter_'.$filter->id;

		$_s = $isSearchView ? '_s' : '';
		$display_filter_as = $filter->parameters->get( 'display_filter_as'.$_s, 0 );  // Filter Type of Display
		$filter_as_range = in_array($display_filter_as, array(2,3,8)) ;

		// Create first prompt option of drop-down select
		$label_filter = $filter->parameters->get( 'display_label_filter'.$_s, 2 ) ;
		$first_option_txt = $label_filter==2 ? $filter->label : JText::_('FLEXI_ALL');

		// Prepend Field's Label to filter HTML
		//$filter->html = $label_filter==1 ? $filter->label.': ' : '';
		$filter->html = '';

		$props_type = $filter->parameters->get('props_type');
		switch ($props_type)
		{
			case 'id':
				// WARNING: we can not use column alias in from, join, where, group by, can use in having (some DB e.g. mysql) and in order-by
				// partial SQL clauses
				$filter->filter_valuesselect = ' i.id AS value, i.id as text';
				$filter->filter_valuesfrom   = ' FROM #__content AS i ';
				$filter->filter_valuesjoin   = ' ';  // null indicates to use default (join with field values TABLE), space is use empty
				$filter->filter_valueswhere  = ' ';  // empty, NOTE: this extra to the always used 'value' = ...
				// full SQL clauses
				$filter->filter_groupby = ' GROUP BY i.id ';    // use empty, since 'id' is unique, and query should not produce duplicate rows
				$filter->filter_having  = null;   // use default, null indicates to use default, space is use empty
				$filter->filter_orderby = null;   // use default, no ordering done to improve speed, it will be done inside PHP code

				FlexicontentFields::createFilter($filter, $value, $formName);
			break;

			case 'language':
				// WARNING: we can not use column alias in from, join, where, group by, can use in having (some DB e.g. mysql) and in order-by
				// partial SQL clauses
				$filter->filter_valuesselect =
					'CASE WHEN i.language IS NULL THEN ' . $db->Quote('*') . ' ELSE i.language END AS value, ' .
					'CASE WHEN CHAR_LENGTH(lg.title_native) THEN lg.title_native ELSE ' .
						'(CASE WHEN lg.title IS NULL THEN ' . $db->Quote(JText::_('JALL')) . ' ELSE lg.title END) ' .
					'END as text';
				$filter->filter_valuesfrom   = ' FROM #__content AS i ';
				$filter->filter_valuesjoin   =
					' LEFT JOIN #__languages AS lg ON i.language = lg.lang_code'.
					' JOIN #__flexicontent_fields_item_relations as fi ON i.id=fi.item_id';
				$filter->filter_valueswhere  = ' AND (lg.published <> 0 OR i.language = ' . $db->Quote('*') . ')';  // NOTE: this extra to the always used 'value' = ...
				// full SQL clauses
				$filter->filter_groupby = ' GROUP BY i.language ';
				$filter->filter_having  = null;   // use default, null indicates to use default, space is use empty
				$filter->filter_orderby = null;   // use default, no ordering done to improve speed, it will be done inside PHP code

				FlexicontentFields::createFilter($filter, $value, $formName);
			break;

			default:
				$filter->html	.= 'CORE property field of type: '.$props_type.' can not be used as search filter';
			break;
		}

		// a. If field filter has defined a custom SQL query to create filter (drop-down select) options, execute it and then create the options
		if ( !empty($query) )
		{
			$db->setQuery($query);
			$lists = $db->loadObjectList();

			// Add the options
			$options = array();
			$_inner_lb = $label_filter==2 ? $filter->label : JText::_('FLEXI_CLICK_TO_LIST');
			$_inner_lb = htmlspecialchars($_inner_lb, ENT_COMPAT, 'UTF-8');
			if ($display_filter_as == 6)
			{
				if ($label_filter==2)
				{
					$options[] = JHtml::_('select.option', '', $_inner_lb, 'value', 'text', $_disabled = true);
				}
			}
			else
				$options[] = JHtml::_('select.option', '', '- '.$first_option_txt.' -');
			foreach ($lists as $list) $options[] = JHtml::_('select.option', $list->value, $list->text . ($count_column ? ' ('.$list->found.')' : '') );
		}

		// b. If field filter has defined drop-down select options the create the drop-down select form field
		if ( !empty($options) )
		{
			// Make use of select2 lib
			flexicontent_html::loadFramework('select2');
			$classes  = " use_select2_lib". @ $extra_classes;
			$extra_param = '';

			// MULTI-select: special label and prompts
			if ($display_filter_as == 6)
			{
				$classes .= ' fc_prompt_internal fc_is_selmultiple';

				// Add field's LABEL internally or click to select PROMPT (via js)
				$extra_param = ' data-placeholder="'.$_inner_lb.'"';

				// Add type to filter PROMPT (via js)
				$extra_param .= ' data-fc_prompt_text="'.htmlspecialchars(JText::_('FLEXI_TYPE_TO_FILTER'), ENT_QUOTES, 'UTF-8').'"';
			}

			// Create HTML tag attributes
			$attribs_str  = ' class="fc_field_filter'.$classes.'" '.$extra_param;
			$attribs_str .= $display_filter_as==6 ? ' multiple="multiple" size="5" ' : '';
			//$attribs_str .= ($display_filter_as==0 || $display_filter_as==6) ? ' onchange="document.getElementById(\''.$formName.'\').submit();"' : '';

			// Filter name and id
			$filter_ffname = 'filter_'.$filter->id;
			$filter_ffid   = $formName.'_'.$filter->id.'_val';

			if ( !is_array($value) )  $value = array($value);
			if ( count($value==1) && !strlen( reset($value) ) )  $value = array();

			// Calculate if field has value
			$has_value = (!is_array($value) && strlen($value)) || (is_array($value) && count($value));
			$filter->html	.= $label_filter==2 && $has_value
				? ' <span class="badge fc_mobile_label" style="display:none;">'.JText::_($filter->label).'</span> '
				: '';

			// Create filter
			// Need selected values: array('') instead of array(), to force selecting the "field's prompt option" (e.g. field label) thus avoid "0 selected" display in mobiles
			$filter->html	.= $display_filter_as != 6
				? JHtml::_('select.genericlist', $options, $filter_ffname.'[]', $attribs_str, 'value', 'text', $value, $filter_ffid)
				: JHtml::_('select.genericlist', $options, $filter_ffname.'[]', $attribs_str, 'value', 'text', ($label_filter==2 && !count($value) ? array('') : $value), $filter_ffid);
		}

		// Special CASE for some filters, do some replacements
		//if ( $props_type == 'alias') $filter->html = str_replace('_', ' ', $filter->html);
	}


 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for content lists e.g. category view, and not for search view
	function getFiltered(&$filter, $value, $return_sql=true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$db = JFactory::getDbo();
		$value_quoted = array();
		foreach ($value as $i => $v)
		{
			$value_quoted[$i] = $db->Quote($v);
		}

		$props_type = $filter->parameters->get('props_type');
		switch ($props_type)
		{
			case 'id':
			case 'language':
				if ($props_type=='id')
				{
					$value = ArrayHelper::toInteger($value);  // Sanitize filter values as integers
				}

				$filter->filter_colname     = $props_type;
				$filter->filter_valuesjoin  = ' ';   // ... a space, (indicates not needed)
				$filter->filter_valueexact = true;  // Match exactly even if field display is text input

				//$query = ' AND i.' . $props_type . ' IN (' . implode(',', $value_quoted) . ')';
				return FlexicontentFields::getFiltered($filter, $value, $return_sql);
				break;

			default:
				return $return_sql ? ' AND i.id IN (0) ' : array(0);
				break;
		}

		if ($return_sql)
		{
			return $query;
		}

		//echo "<br>plgFlexicontent_fieldsCoreprops::getFiltered() -- [".$filter->name."]  doing: <br>". $query."<br><br>\n";
		$db->setQuery($query);
		$filtered = $db->loadColumn();
		return $filtered;
	}


	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$filter, $value, $return_sql=true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$db = JFactory::getDbo();
		$value_quoted = array();
		foreach ($value as $i => $v)
		{
			$value_quoted[$i] = $db->Quote($v);
		}

		$props_type = $filter->parameters->get('props_type');
		switch ($props_type)
		{
			case 'id':
			case 'language':
				$query = 'SELECT DISTINCT c.id '
					. ' FROM #__content AS c '
					. ' WHERE c.' . $props_type . ' IN (' . implode(',', $value_quoted) . ')';
				break;

			default:
				return $return_sql ? ' AND i.id IN (0) ' : array(0);
				break;
		}

		if ($return_sql)
		{
			return ' AND i.id IN ('. $query .')';
		}

		//echo "<br>plgFlexicontent_fieldsCoreprops::getFiltered() -- [".$filter->name."]  doing: <br>". $query."<br><br>\n";
		$db->setQuery($query);
		$filtered = $db->loadColumn();
		return $filtered;
	}
}
?>