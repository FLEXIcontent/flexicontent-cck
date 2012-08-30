<?php
/**
 * @version 1.0 $Id: textarea.php 1279 2012-05-10 02:07:42Z ggppdk $
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

//jimport('joomla.plugin.plugin');
jimport('joomla.event.plugin');

class plgFlexicontent_fieldsTextarea extends JPlugin
{
	function plgFlexicontent_fieldsTextarea( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_textarea', JPATH_ADMINISTRATOR);
	}
	
	function onAdvSearchDisplayField(&$field, &$item) {
		if($field->field_type != 'textarea') return;
		$field_type = $field->field_type;
		$field->field_type =  'text';
		$field->parameters->set( 'size', $field->parameters->get( 'adv_size', 30 ) );
		plgFlexicontent_fieldsText::onDisplayField($field, $item);
		$field->field_type =  'textarea';
	}
	
	
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
	
	function onDisplayField(&$field, &$item)
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'textarea' && $field->field_type != 'maintext') return;

		$editor 	= & JFactory::getEditor();
		
		// some parameter shortcuts
		$cols      = $field->parameters->get( 'cols', 75 ) ;
		$rows      = $field->parameters->get( 'rows', 20 ) ;
		$height    = $field->parameters->get( 'height', 400 ) ;
		$use_html  = $field->parameters->get( 'use_html', 0 ) ;
		$required  = $field->parameters->get( 'required', 0 ) ;
		$required  = $required ? ' required' : '';
		
		$default_value = $field->parameters->get( 'default_value' ) ;
		
		// tabbing parameters
		$editorarea_per_tab = $field->parameters->get('editorarea_per_tab', 0);
		$allow_tabs_code_editing = $field->parameters->get('allow_tabs_code_editing', 0);
		$merge_tabs_code_editor = $field->parameters->get('merge_tabs_code_editor', 1);
		$force_beforetabs = $field->parameters->get('force_beforetabs');
		$force_aftertabs = $field->parameters->get('force_aftertabs');
		
		
		// initialise property
		if($field->field_type == 'textarea') {
			if($item->version < 2 && $default_value) {
				$field->value = array();
				$field->value[0] = JText::_($default_value);
			} elseif (!$field->value) {
				$field->value = array();
				$field->value[0] = '';
			}
			$field_name = 'custom['.$field->name.']';
			$field_idtag = 'custom_'.$field->name;
			$skip_buttons_arr = array('pagebreak', 'readmore');
		} else if ($field->field_type == 'maintext') {
			if ( !is_array($field->name) ) {
				$field_name = 'jform['.$field->name.']';
				$field_idtag = 'jform_'.$field->name;
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
			$skip_buttons_arr = array();
		}
		$field_value = & $field->value[0];
		
		// Try to parse tabs
		$this->parseTabs($field, $item);
		
		// Create textarea(s) or editor area(s) ... multiple will be created if tabs are detected and 'editorarea per tab' is enabled
		if ( !$editorarea_per_tab || !$field->tabs_detected )
		{
			$field->tab_names[0] = $field_name;//.'[0]';
			$field->tab_labels[0] = $field->label;
			if (!$use_html) {
				$field->html[0]	 = '<textarea name="' . $field->tab_names[0] . '" cols="'.$cols.'" rows="'.$rows.'" class="'.$required.'">'.$field_value.'</textarea>'."\n";
			} else {
				$field_value = htmlspecialchars( $field_value, ENT_NOQUOTES, 'UTF-8' );
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
					$field->html[$ta_count]	 = '<textarea name="' . $field->tab_names[$ta_count] . '" cols="'.$cols.'" rows="'.$rows.'" class="'.$required.'">'.$ti->beforetabs.'</textarea>'."\n";
				} else {
					$ti->beforetabs = htmlspecialchars( $ti->beforetabs, ENT_NOQUOTES, 'UTF-8' );
					$field->html[$ta_count] = $editor->display( $field->tab_names[$ta_count], $ti->beforetabs, '100%', $height, $cols, $rows, $skip_buttons_arr );
				}
				$ta_count++;
			}
			
			// 2. START OF TABS
			$field->tab_names[$ta_count] = $field_name.'['.($ta_count).']';
			if ($allow_tabs_code_editing) $field->tab_labels[$ta_count] = !$merge_tabs_code_editor ? 'TabBegin' : 'T';
			if (!$merge_tabs_code_editor) {
				$field->html[$ta_count] = '<textarea name="' . $field->tab_names[$ta_count] .'" style="display:block!important;" cols="70" rows="3">'. $ti->tabs_start .'</textarea>'."\n";
				$ta_count++;
			} else {
				$field->html[$ta_count] = $ti->tabs_start;
			}
			
			foreach ($ti->tab_contents as $i => $tab_content) {
				// START OF TAB
				$field->tab_names[$ta_count] = $field_name.'['.($ta_count).']';
				if ($allow_tabs_code_editing) $field->tab_labels[$ta_count] = 'T';//'Start of tab: '. $ti->tab_titles[$i]; 
				$field->html[$ta_count] = '<textarea name="' . $field->tab_names[$ta_count] .'" style="display:block!important;" cols="70" rows="3">'. $field->html[$ta_count]."\n".$ti->tab_startings[$i] .'</textarea>'."\n";
				$ta_count++;

				$field->tab_names[$ta_count] = $field_name.'['.($ta_count).']';
				$field->tab_labels[$ta_count] = /*$field->label.'<br />'.*/ $ti->tab_titles[$i]; 
				
				if (!$use_html) {
					$field->html[$ta_count]	 = '<textarea name="' . $field->tab_names[$ta_count] . '" cols="'.$cols.'" rows="'.$rows.'" class="'.$required.'">'.$tab_content.'</textarea>'."\n";
				} else {
					$tab_content = htmlspecialchars( $tab_content, ENT_NOQUOTES, 'UTF-8' );
					$field->html[$ta_count] = $editor->display( $field->tab_names[$ta_count], $tab_content, '100%', $height, $cols, $rows, $skip_buttons_arr );
				}
				$ta_count++;
				
				// END OF TAB
				$field->tab_names[$ta_count] = $field_name.'['.($ta_count).']';
				if ($allow_tabs_code_editing) $field->tab_labels[$ta_count] = 'T';//'End of tab: '. $ti->tab_titles[$i]; 
				if (!$merge_tabs_code_editor) {
					$field->html[$ta_count] = '<textarea name="' . $field->tab_names[$ta_count] .'" style="display:block!important;" cols="70" rows="3">'. $ti->tab_endings[$i] .'</textarea>'."\n";
					$ta_count++;
				} else {
					$field->html[$ta_count] = $ti->tab_endings[$i];
				}
			}
			
			// 2. END OF TABS
			$field->tab_names[$ta_count] = $field_name.'['.($ta_count).']';
			if ($allow_tabs_code_editing) $field->tab_labels[$ta_count] =  !$merge_tabs_code_editor ? 'TabEnd' : 'T';
			$field->html[$ta_count] = '<textarea name="' . $field->tab_names[$ta_count] .'" style="display:block!important;" cols="70" rows="3">'. $field->html[$ta_count]."\n".$ti->tabs_end .'</textarea>'."\n";
			$ta_count++;
			
			if ( $force_aftertabs == 1  ||  ($ti->aftertabs && trim(strip_tags($ti->aftertabs))) ) {
				$field->tab_names[$ta_count] = $field_name.'['.($ta_count).']';
				$field->tab_labels[$ta_count] = /*$field->label.'<br />'.*/ 'Foot Text' ;
				
				if (!$use_html) {
					$field->html[$ta_count]	 = '<textarea name="' . $field->tab_names[$ta_count] . '" cols="'.$cols.'" rows="'.$rows.'" class="'.$required.'">'.$ti->aftertabs.'</textarea>'."\n";
				} else {
					$ti->aftertabs = htmlspecialchars( $ti->aftertabs, ENT_NOQUOTES, 'UTF-8' );
					$field->html[$ta_count] = $editor->display( $field->tab_names[$ta_count], $ti->aftertabs, '100%', $height, $cols, $rows, $skip_buttons_arr );
				}
				$ta_count++;
			}
		}
		
		/*if ($use_html) {
			$field->value[0] = htmlspecialchars( $field->value[0], ENT_NOQUOTES, 'UTF-8' );
			$field->html	 = $editor->display( 'custom['.$field->name.']', $field->value[0], '100%', $height, $cols, $rows, array('pagebreak', 'readmore') );
		} else {
			$field->html	 = '<textarea name="custom['.$field->name.']" cols="'.$cols.'" rows="'.$rows.'" class="'.$required.'">';
			$field->html	.= $field->value[0];
			$field->html	.= '</textarea>';
		}*/
	}


	function onBeforeSaveField( $field, &$post, &$file ) {
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'textarea') return;
		if ( !FLEXI_J16GE && $field->parameters->get( 'use_html', 0 ) ) {
			$rawdata = JRequest::getVar($field->name, '', 'post', 'string', JREQUEST_ALLOWRAW);
			if ($rawdata) $post = $rawdata;
		}
		if(!$post) return;

		// Reconstruct value if it has splitted up e.g. to tabs
		if (is_array($post)) {
			$tabs_text = '';
			foreach($post as $tab_text) {
				$tabs_text .= $tab_text;
			}
			$post = & $tabs_text;
		}
		//print_r($post); exit();
		
		// create the fulltext search index
		$searchindex = flexicontent_html::striptagsandcut($post) . ' | ';		
		$field->search = $field->issearch ? $searchindex : '';
		
		$data	= JRequest::getVar('jform', array(), 'post', 'array');
		if($field->isadvsearch && $data['vstate']==2) {
			$this->onIndexAdvSearch($field, $post);
		}
	}
	
	function onIndexAdvSearch(&$field, $post) {
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'textarea') return;
		$db = &JFactory::getDBO();
		$query = "DELETE FROM #__flexicontent_advsearch_index WHERE field_id='{$field->id}' AND item_id='{$field->item_id}' AND extratable='textarea';";
		$db->setQuery($query);
		$db->query();
		$query = "INSERT INTO #__flexicontent_advsearch_index VALUES('{$field->id}','{$field->item_id}','textarea','0', ".$db->Quote($post).");";
		$db->setQuery($query);
		$db->query();
		return true;
	}


	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display') {
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'textarea') return;
		
		// some parameter shortcuts
		$use_html			= $field->parameters->get( 'use_html', 0 ) ;
		$opentag			= $field->parameters->get( 'opentag', '' ) ;
		$closetag			= $field->parameters->get( 'closetag', '' ) ;

		$values = $values ? $values : $field->value ;

		if ($values) {
			$field->{$prop}	 = $opentag;
			$field->{$prop}	.= $values ? ($use_html ? $values[0] : nl2br($values[0])) : '';
			$field->{$prop}	.= $closetag;
		} else {
			$field->{$prop}	 = '';
		}

		// Some variables
		$document	= & JFactory::getDocument();
		$view = JRequest::setVar('view', JRequest::getVar('view', FLEXI_ITEMVIEW));
		
		// Get ogp configuration
		$useogp     = $field->parameters->get('useogp', 0);
		$ogpinview  = $field->parameters->get('ogpinview', array());
		$ogpinview  = FLEXIUtilities::paramToArray($ogpinview);
		$ogpmaxlen  = $field->parameters->get('ogpmaxlen', 300);
		$ogpusage   = $field->parameters->get('ogpusage', 0);
		
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
	
	function onFLEXIAdvSearch(&$field, $fieldsearch) {
		if($field->field_type!='textarea') return;
		$db = &JFactory::getDBO();
		$resultfields = array();
		foreach($fieldsearch as $fsearch) {
			$query = "SELECT ai.search_index, ai.item_id FROM #__flexicontent_advsearch_index as ai"
				." WHERE ai.field_id='{$field->id}' AND ai.extratable='textarea' AND ai.search_index like '%{$fsearch}%';";
			$db->setQuery($query);
			$objs = $db->loadObjectList();
			if ($objs===false) continue;
			$objs = is_array($objs)?$objs:array($objs);
			foreach($objs as $o) {
				$obj = new stdClass;
				$obj->item_id = $o->item_id;
				$obj->label = $field->label;
				$obj->value = $fsearch;
				$resultfields[] = $obj;
			}
		}
		$field->results = $resultfields;
		//return $resultfields;
	}
}
