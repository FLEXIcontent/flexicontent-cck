<?php
defined("_JEXEC") or die("Restricted Access");

class plgFlexicontent_fieldsCoreprops extends JPlugin
{
	static $field_types = array('coreprops');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsCoreprops( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_coreprops', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$props_type = $field->parameters->get( 'props_type' ) ;
		
		$field->html = '';
	}
	
	
	// Method to create field's HTML display for frontend views
	public function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		static $all_langs = null;
		
		$props_type = $field->parameters->get('props_type');
		if ($props_type == 'language')
		{
			if ($all_langs===null) {
				$all_langs= FLEXIUtilities::getLanguages($hash='code');
			}
			$lang_data = $all_langs->{$item->language};
			
			$field->{$prop} = @$lang_data->title_native ? $lang_data->title_native : $lang_data->name;
		}
	}
	
	// *********************************
	// CATEGORY/SEARCH FILTERING METHODS
	// *********************************
	
	// Method to display a search filter for the advanced search view
	function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$props_type = $filter->parameters->get('props_type');
		//if ($props_type == 'language') {
		//	$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		//}
		
		//$indexed_elements = in_array($props_type, array('language'));
		
		$this->onDisplayFilter($filter, $value, $formName);
		//if ($props_type =='...') {
		//	plgFlexicontent_fieldsCore::onDisplayFilter($filter, $value, $formName);
		//} else {
		//	FlexicontentFields::createFilter($filter, $value, $formName, $indexed_elements);
		//}
	}
	
	
	

	// Method to display a category filter for the category view
	function onDisplayFilter(&$filter, $value='', $formName='adminForm')
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$db = JFactory::getDBO();
		$formfieldname = 'filter_'.$filter->id;
		
		$display_filter_as = $filter->parameters->get( 'display_filter_as', 0 );  // Filter Type of Display
		$filter_as_range = in_array($display_filter_as, array(2,3,)) ;
		
		// Create first prompt option of drop-down select
		$label_filter = $filter->parameters->get( 'display_label_filter', 2 ) ;
		$first_option_txt = $label_filter==2 ? $filter->label : JText::_('FLEXI_ALL');
		
		// Prepend Field's Label to filter HTML
		//$filter->html = $label_filter==1 ? $filter->label.': ' : '';
		$filter->html = '';
		
		$props_type = $filter->parameters->get('props_type');
		switch ($props_type)
		{
			case 'language':     // Authors
				// WARNING: we can not use column alias in from, join, where, group by, can use in having (mysql) and in order by
				// partial SQL clauses
				if (!FLEXI_J16GE) break;
				$filter->filter_valuesselect = ' i.language AS value, CONCAT_WS(\': \', lg.title, lg.title_native) AS text';
				$filter->filter_valuesjoin   = ' JOIN #__languages AS lg ON i.language = lg.lang_code';
				$filter->filter_valueswhere  = ' AND lg.published <> 0';
				// full SQL clauses
				$filter->filter_groupby = ' GROUP BY i.language ';
				$filter->filter_having  = null;   // this indicates to use default, space is use empty
				$filter->filter_orderby = ' ORDER BY lg.title ASC ';
				
				FlexicontentFields::createFilter($filter, $value, $formName);
			break;
			
			default:
				$filter->html	.= 'CORE property field of type: '.$props_type.' can not be used as search filter';
			break;
		}
		
		// a. If field filter has defined a custom SQL query to create filter (drop-down select) options, execute it and then create the options
		if ( !empty($query) ) {
			$db->setQuery($query);
			$lists = $db->loadObjectList();
			$options = array();
			$options[] = JHTML::_('select.option', '', '- '.$first_option_txt.' -');
			foreach ($lists as $list) $options[] = JHTML::_('select.option', $list->value, $list->text . ($count_column ? ' ('.$list->found.')' : '') );
		}
		
		// b. If field filter has defined drop-down select options the create the drop-down select form field
		if ( !empty($options) ) {
			// Make use of select2 lib
			flexicontent_html::loadFramework('select2');
			$classes  = " use_select2_lib". @ $extra_classes;
			$extra_param = '';
			
			// MULTI-select: special label and prompts
			if ($display_filter_as == 6) {
				$classes .= ' fc_label_internal fc_prompt_internal';
				// Add field's LABEL internally or click to select PROMPT (via js)
				$_inner_lb = $label_filter==2 ? $filter->label : JText::_('FLEXI_CLICK_TO_LIST');
				// Add type to filter PROMPT (via js)
				$extra_param  = ' data-fc_label_text="'.flexicontent_html::escapeJsText($_inner_lb,'s').'"';
				$extra_param .= ' data-fc_prompt_text="'.flexicontent_html::escapeJsText(JText::_('FLEXI_TYPE_TO_FILTER'),'s').'"';
			}
			
			// Create HTML tag attributes
			$attribs_str  = ' class="fc_field_filter'.$classes.'" '.$extra_param;
			$attribs_str .= $display_filter_as==6 ? ' multiple="multiple" size="20" ' : '';
			//$attribs_str .= ($display_filter_as==0 || $display_filter_as==6) ? ' onchange="document.getElementById(\''.$formName.'\').submit();"' : '';
			
			// Filter name and id
			$filter_ffname = 'filter_'.$filter->id;
			$filter_ffid   = $formName.'_'.$filter->id.'_val';
			
			// Create filter
			$filter->html	.= JHTML::_('select.genericlist', $options, $filter_ffname.'[]', $attribs_str, 'value', 'text', $value, $filter_ffid);
		}
		
		// Special CASE 'categories' filter, replace some tags in filter HTML ...
		//if ( $props_type == 'alias') $filter->html = str_replace('_', ' ', $filter->html);
	}
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for content lists e.g. category view, and not for search view
	function getFiltered(&$filter, $value)
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$props_type = $filter->parameters->get('props_type');
		switch ($props_type)
		{
			case 'language':     // Authors
				
				$filter->filter_colname    = 'language';
				$filter->filter_valuesjoin = ' ';   // ... a space, (indicates not needed)
				$filter->filter_valueformat = ' ';
				
				// Dates are given in user calendar convert them to valid SQL dates
				$sql = FlexicontentFields::getFiltered($filter, $value, $return_sql=true);
				break;
		}
		return $sql;
	}
}
?>