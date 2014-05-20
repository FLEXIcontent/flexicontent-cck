<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.textarea
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.event.plugin');

class plgFlexicontent_fieldsTextarea extends JPlugin
{
	static $field_types = array('textarea', 'maintext');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsTextarea( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_textarea', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		$editor_name = $user->getParam('editor', $app->getCfg('editor'));
		$editor = JFactory::getEditor($editor_name);
		
		// some parameter shortcuts
		$default_value_use = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_value     = ($item->version == 0 || $default_value_use > 0) ? $field->parameters->get( 'default_value', '' ) : '';
		$cols         = $field->parameters->get( 'cols', 75 ) ;
		$rows         = $field->parameters->get( 'rows', 20 ) ;
		$required = $field->parameters->get( 'required', 0 ) ;
		$required = $required ? ' required' : '';
		
		$use_html     = $field->parameters->get( 'use_html', !$field->parameters->get( 'hide_html', 0) ) ;   // maintext has no 'use_html' instead it has 'hide_html'
		$height       = $field->parameters->get( 'height', ($field->field_type == 'textarea') ? '300px' : '400px' ) ;
		if ($height != (int)$height) $height .= 'px';
		
		$show_buttons = $field->parameters->get( 'show_buttons', 1 ) ;
		$skip_buttons = $field->parameters->get( 'skip_buttons', '' ) ;
		
		if (FLEXI_J16GE) {
			$skip_buttons = is_array($skip_buttons) ? $skip_buttons : explode('|',$skip_buttons);
		} else if ( !is_array($skip_buttons) ) {
			$skip_buttons = array($skip_buttons);
		}
		if ($field->field_type == 'textarea') {
			if ( !in_array('pagebreak', $skip_buttons) ) $skip_buttons[] = 'pagebreak';
			if ( !in_array('readmore',  $skip_buttons) )  $skip_buttons[] = 'readmore';
		}
		$skip_buttons_arr = ($show_buttons && $editor_name=='jce' && count($skip_buttons)) ? $skip_buttons : (boolean) $show_buttons;   // JCE supports skipping buttons
		
		// tabbing parameters
		$editorarea_per_tab = $field->parameters->get('editorarea_per_tab', 0);
		$allow_tabs_code_editing = $field->parameters->get('allow_tabs_code_editing', 0);
		$merge_tabs_code_editor = $field->parameters->get('merge_tabs_code_editor', 1);
		$force_beforetabs = $field->parameters->get('force_beforetabs');
		$force_aftertabs = $field->parameters->get('force_aftertabs');
		
		
		// initialise property
		if (!$field->value) {
			$field->value = array();
			$field->value[0] = JText::_($default_value);
		}
		if ($field->field_type == 'textarea')
		{
			$field_name = FLEXI_J16GE ? 'custom['.$field->name.']' : $field->name;
			$field_idtag = FLEXI_J16GE ? 'custom_'.$field->name :  $field->name;
		}
		else if ($field->field_type == 'maintext')
		{
			if ( !is_array($field->name) ) {
				$field_name = FLEXI_J16GE ? 'jform['.$field->name.']' : $field->name;
				$field_idtag = FLEXI_J16GE ? 'jform_'.$field->name : $field->name;
			} else {
				foreach ( $field->name as $i => $ffname) {
					if ($i==0) {
						$field_name = $field_idtag = $ffname;
					} else {
						$field_name .= '['.$ffname.']';
						$field_idtag .= '_'.$ffname;
					}
				}
			}
			$required = '';
		}
		$field_value = & $field->value[0];
		// Convert ampersands, to allow editing of tags inside the textarea, this effects editing and not saving ...
		$field_value = str_replace('&', '&amp;', $field_value);
		
		// Try to parse tabs
		$this->parseTabs($field, $item);
		
		// Pretend to be com_content, could this be needed ? or will it cause problems?
	  //JRequest::setVar('view', 'article');
	  //JRequest::setVar('option', 'com_content');
		//JRequest::setVar("isflexicontent", "yes");
		
		// Create textarea(s) or editor area(s) ... multiple will be created if tabs are detected and 'editorarea per tab' is enabled
		if ( !$editorarea_per_tab || !$field->tabs_detected )
		{
			$field->tab_names[0] = $field_name;//.'[0]';
			$field->tab_labels[0] = $field->label;
			
			//$field_value = htmlspecialchars( $field_value, ENT_COMPAT, 'UTF-8' );
			if (!$use_html) {
				$field->html[0]	 = '<textarea id="'.$field_idtag.'_0" name="' . $field->tab_names[0] . '" cols="'.$cols.'" rows="'.$rows.'" class="'.$required.'">'.$field_value.'</textarea>'."\n";
			} else {
				$field->html[0] = $editor->display( $field->tab_names[0], $field_value, '100%', $height, $cols, $rows, $skip_buttons_arr );
			}
			$field->html = $field->html[0];
		}
		else
		{
			$ta_count = 0;
			$ti = & $field->tab_info;
			
			// 1. BEFORE TABS
			if ( $force_beforetabs == 1  ||  ($ti->beforetabs && trim(strip_tags($ti->beforetabs))) ) {
				$field->tab_names[$ta_count] = $field_name.'['.($ta_count).']';
				$field->tab_labels[$ta_count] = /*$field->label.'<br />'.*/ 'Intro Text';
				
				if (!$use_html) {
					$field->html[$ta_count]	 = '<textarea id="'.$field_idtag.'_'.$ta_count.'" name="' . $field->tab_names[$ta_count] . '" cols="'.$cols.'" rows="'.$rows.'" class="'.$required.'">'.$ti->beforetabs.'</textarea>'."\n";
				} else {
					//$ti->beforetabs = htmlspecialchars( $ti->beforetabs, ENT_NOQUOTES, 'UTF-8' );
					$field->html[$ta_count] = $editor->display( $field->tab_names[$ta_count], $ti->beforetabs, '100%', $height, $cols, $rows, $skip_buttons_arr );
				}
				$ta_count++;
			}
			
			// 2. START OF TABS
			$field->tab_names[$ta_count] = $field_name.'['.($ta_count).']';
			if ($allow_tabs_code_editing) $field->tab_labels[$ta_count] = !$merge_tabs_code_editor ? 'TabBegin' : 'T';
			if (!$merge_tabs_code_editor) {
				$field->html[$ta_count] = '<textarea id="'.$field_idtag.'_'.$ta_count.'" name="' . $field->tab_names[$ta_count] .'" style="display:block!important;" cols="70" rows="3">'. $ti->tabs_start .'</textarea>'."\n";
				$ta_count++;
			} else {
				$field->html[$ta_count] = $ti->tabs_start;
			}
			
			foreach ($ti->tab_contents as $i => $tab_content) {
				// START OF TAB
				$field->tab_names[$ta_count] = $field_name.'['.($ta_count).']';
				if ($allow_tabs_code_editing) $field->tab_labels[$ta_count] = 'T';//'Start of tab: '. $ti->tab_titles[$i]; 
				$field->html[$ta_count] = '<textarea id="'.$field_idtag.'_'.$ta_count.'" name="' . $field->tab_names[$ta_count] .'" style="display:block!important;" cols="70" rows="3">'. $field->html[$ta_count]."\n".$ti->tab_startings[$i] .'</textarea>'."\n";
				$ta_count++;

				$field->tab_names[$ta_count] = $field_name.'['.($ta_count).']';
				$field->tab_labels[$ta_count] = /*$field->label.'<br />'.*/ $ti->tab_titles[$i]; 
				
				if (!$use_html) {
					$field->html[$ta_count]	 = '<textarea id="'.$field_idtag.'_'.$ta_count.'" name="' . $field->tab_names[$ta_count] . '" cols="'.$cols.'" rows="'.$rows.'" class="'.$required.'">'.$tab_content.'</textarea>'."\n";
				} else {
					//$tab_content = htmlspecialchars( $tab_content, ENT_NOQUOTES, 'UTF-8' );
					$field->html[$ta_count] = $editor->display( $field->tab_names[$ta_count], $tab_content, '100%', $height, $cols, $rows, $skip_buttons_arr );
				}
				$ta_count++;
				
				// END OF TAB
				$field->tab_names[$ta_count] = $field_name.'['.($ta_count).']';
				if ($allow_tabs_code_editing) $field->tab_labels[$ta_count] = 'T';//'End of tab: '. $ti->tab_titles[$i]; 
				if (!$merge_tabs_code_editor) {
					$field->html[$ta_count] = '<textarea id="'.$field_idtag.'_'.$ta_count.'" name="' . $field->tab_names[$ta_count] .'" style="display:block!important;" cols="70" rows="3">'. $ti->tab_endings[$i] .'</textarea>'."\n";
					$ta_count++;
				} else {
					$field->html[$ta_count] = $ti->tab_endings[$i];
				}
			}
			
			// 2. END OF TABS
			$field->tab_names[$ta_count] = $field_name.'['.($ta_count).']';
			if ($allow_tabs_code_editing) $field->tab_labels[$ta_count] =  !$merge_tabs_code_editor ? 'TabEnd' : 'T';
			$field->html[$ta_count] = '<textarea id="'.$field_idtag.'_'.$ta_count.'" name="' . $field->tab_names[$ta_count] .'" style="display:block!important;" cols="70" rows="3">'. $field->html[$ta_count]."\n".$ti->tabs_end .'</textarea>'."\n";
			$ta_count++;
			
			if ( $force_aftertabs == 1  ||  ($ti->aftertabs && trim(strip_tags($ti->aftertabs))) ) {
				$field->tab_names[$ta_count] = $field_name.'['.($ta_count).']';
				$field->tab_labels[$ta_count] = /*$field->label.'<br />'.*/ 'Foot Text' ;
				
				if (!$use_html) {
					$field->html[$ta_count]	 = '<textarea id="'.$field_idtag.'_'.$ta_count.'" name="' . $field->tab_names[$ta_count] . '" cols="'.$cols.'" rows="'.$rows.'" class="'.$required.'">'.$ti->aftertabs.'</textarea>'."\n";
				} else {
					//$ti->aftertabs = htmlspecialchars( $ti->aftertabs, ENT_NOQUOTES, 'UTF-8' );
					$field->html[$ta_count] = $editor->display( $field->tab_names[$ta_count], $ti->aftertabs, '100%', $height, $cols, $rows, $skip_buttons_arr );
				}
				$ta_count++;
			}
		}
		
		// Restore HTTP Request variables
	  //JRequest::setVar('view', FLEXI_ITEMVIEW);
	  //JRequest::setVar('option', 'com_flexicontent');
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		
		// Some variables
		$document = JFactory::getDocument();
		$view = JRequest::setVar('view', JRequest::getVar('view', FLEXI_ITEMVIEW));
		
		// Get field values
		$values = $values ? $values : $field->value;
		// DO NOT terminate yet if value is empty since a default value on empty may have been defined
		
		// Handle default value loading, instead of empty value
		$default_value_use= $field->parameters->get( 'default_value_use', 0 ) ;
		$default_value		= ($default_value_use == 2) ? $field->parameters->get( 'default_value', '' ) : '';
		if ( empty($values) && !strlen($default_value) ) {
			$field->{$prop} = '';
			return;
		} else if ( empty($values) && strlen($default_value) ) {
			$values = array($default_value);
		}
		
		// Prefix - Suffix - Separator parameters, replacing other field values if found
		$opentag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'opentag', '' ), 'opentag' );
		$closetag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'closetag', '' ), 'closetag' );
		
		// some parameter shortcuts
		$use_html			= $field->parameters->get( 'use_html', 0 ) ;
		
		// Get ogp configuration
		$useogp     = $field->parameters->get('useogp', 0);
		$ogpinview  = $field->parameters->get('ogpinview', array());
		$ogpinview  = FLEXIUtilities::paramToArray($ogpinview);
		$ogpmaxlen  = $field->parameters->get('ogpmaxlen', 300);
		$ogpusage   = $field->parameters->get('ogpusage', 0);
		
		// Apply seperator and open/close tags
		if ($values) {
			$field->{$prop} = $use_html ? $values[0] : nl2br($values[0]);
			$field->{$prop} = $opentag . $field->{$prop} . $closetag;
		} else {
			$field->{$prop} = '';
		}
		
		if ($useogp && $field->{$prop}) {
			if ( in_array($view, $ogpinview) ) {
				switch ($ogpusage)
				{
					case 1: $usagetype = 'title'; break;
					case 2: $usagetype = 'description'; break;
					default: $usagetype = ''; break;
				}
				if ($usagetype) {
					$content_val = flexicontent_html::striptagsandcut($field->{$prop}, $ogpmaxlen);
					$document->addCustomTag('<meta property="og:'.$usagetype.'" content="'.$content_val.'" />');
				}
			}
		}
	}
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !FLEXI_J16GE && $field->parameters->get( 'use_html', 0 ) ) {
			$rawdata = JRequest::getVar($field->name, '', 'post', 'string', JREQUEST_ALLOWRAW);
			if ($rawdata) $post = $rawdata;
		}
		if ( !is_array($post) && !strlen($post) ) return;
		
		// Reconstruct value if it has splitted up e.g. to tabs
		if (is_array($post)) {
			$tabs_text = '';
			foreach($post as $tab_text) {
				$tabs_text .= $tab_text;
			}
			$post = & $tabs_text;
		}
	}
	
	
	// Method to take any actions/cleanups needed after field's values are saved into the DB
	function onAfterSaveField( &$field, &$post, &$file, &$item ) {
	}
	
	
	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
	}
	
	
	
	// *********************************
	// CATEGORY/SEARCH FILTERING METHODS
	// *********************************
	
	// Method to display a search filter for the advanced search view
	function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		FlexicontentFields::createFilter($filter, $value, $formName);
	}
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$field, $value)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		return FlexicontentFields::getFilteredSearch($field, $value, $return_sql=true);
	}
	
	
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item) {
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func='strip_tags');
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func='strip_tags');
		return true;
	}
	
	
	
	// **********************
	// VARIOUS HELPER METHODS
	// **********************
	
	// Method to parse a given text for tabbing code
	function parseTabs(&$field, &$item) {
		$editorarea_per_tab = $field->parameters->get('editorarea_per_tab', 0);

		$start_of_tabs_pattern = $field->parameters->get('start_of_tabs_pattern');
		$end_of_tabs_pattern = $field->parameters->get('end_of_tabs_pattern');
		
		$start_of_tabs_default_text = $field->parameters->get('start_of_tabs_default_text');  // Currently unused
		$default_tab_list = $field->parameters->get('default_tab_list');                      // Currently unused
		
		$title_tab_pattern = $field->parameters->get('title_tab_pattern');
		$start_of_tab_pattern = $field->parameters->get('start_of_tab_pattern');
		$end_of_tab_pattern = $field->parameters->get('end_of_tab_pattern');
		
		$field_value = & $field->value[0];
		$field->tabs_detected = false;
		
		// MAKE MAIN TEXT FIELD OR TEXTAREAS TABBED
		if ( $editorarea_per_tab ) {
			
			//echo 'tabs start: ' . preg_match_all('/'.$start_of_tabs_pattern.'/u', $field_value ,$matches) . "<br />";
			//print_r ($matches); echo "<br />";
			
			//echo 'tabs end: ' . preg_match_all('/'.$end_of_tabs_pattern.'/u', $field_value ,$matches) . "<br />";
			//print_r ($matches); echo "<br />";
			
			$field->tabs_detected = preg_match('/' .'(.*)('.$start_of_tabs_pattern .')(.*)(' .$end_of_tabs_pattern .')(.*)'. '/su', $field_value ,$matches);
			
			if ($field->tabs_detected) {
				$field->tab_info = new stdClass();
				$field->tab_info->beforetabs = $matches[1];
				$field->tab_info->tabs_start = $matches[2];
				$insidetabs = $matches[3];
				$field->tab_info->tabs_end   = $matches[4];
				$field->tab_info->aftertabs  = $matches[5];
				
				//echo 'tab start: ' . preg_match_all('/'.$start_of_tab_pattern.'/u', $insidetabs ,$matches) . "<br />";
				//echo "<pre>"; print_r ($matches); echo "</pre><br />";									
				
				//echo 'tab end: ' . preg_match_all('/'.$end_of_tab_pattern.'/u', $insidetabs ,$matches) . "<br />";
				//print_r ($matches); echo "<br />";
				
				$tabs_count = preg_match_all('/('.$start_of_tab_pattern .')(.*?)(' .$end_of_tab_pattern .')/su', $insidetabs ,$matches) . "<br />";
				
				if ($tabs_count) {
					$tab_startings = $matches[1];
					
					foreach ($tab_startings as $i => $v) {
						$title_matched = preg_match('/'.$title_tab_pattern.'/su', $tab_startings[$i] ,$title_matches) . "<br />";
						//echo "<pre>"; print_r($title_matches); echo "</pre>";
						$tab_titles[$i] = $title_matches[1];
					}
					
					$tab_contents = $matches[2];
					$tab_endings = $matches[3];
					//foreach ($tab_titles as $tab_title) echo "$tab_title &nbsp; &nbsp; &nbsp;";
				} else {
					echo "FALIED while parsing tabs<br />";
					$field->tabs_detected = 0;
				}
				
				$field->tab_info->tab_startings = & $tab_startings;
				$field->tab_info->tab_titles    = & $tab_titles;
				$field->tab_info->tab_contents  = & $tab_contents;
				$field->tab_info->tab_endings   = & $tab_endings;
			}
		}
	}
}
