<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.weblink
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

class plgFlexicontent_fieldsWeblink extends JPlugin
{
	static $field_types = array('weblink');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsWeblink( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_weblink', JPATH_ADMINISTRATOR);
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
		
		// initialize framework objects and other variables
		$document  = JFactory::getDocument();
		
		// some parameter shortcuts
		$size       = (int) $field->parameters->get( 'size', 30 ) ;
		$multiple   = $field->parameters->get( 'allow_multiple', 1 ) ;
		$max_values = (int) $field->parameters->get( 'max_values', 0 ) ;
		$required   = $field->parameters->get( 'required', 0 ) ;
		$required   = $required ? ' required' : '';
		
		// This is field 's MAIN value property
		$link_usage   = $field->parameters->get( 'link_usage', 0 ) ;
		$default_link = ($item->version == 0 || $link_usage > 0) ? $field->parameters->get( 'default_value_link', '' ) : '';
		
		// Optional value properties
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($item->version == 0 || $title_usage > 0) ? JText::_($field->parameters->get( 'default_value_title', '' )) : '';
		$usetitle      = $field->parameters->get( 'use_title', 0 ) ;
		
		// Initialise property with default value
		if ( !$field->value ) {
			$field->value = array();
			$field->value[0]['link']  = JText::_($default_link);
			$field->value[0]['title'] = JText::_($default_title);
			$field->value[0]['hits']  = 0;
			$field->value[0] = serialize($field->value[0]);
		}
		
		// Field name and HTML TAG id
		$fieldname = FLEXI_J16GE ? 'custom['.$field->name.']' : $field->name;
		$elementid = FLEXI_J16GE ? 'custom_'.$field->name : $field->name;
		
		$js = "";
		
		if ($multiple) // handle multiple records
		{
			if (!FLEXI_J16GE) $document->addScript( JURI::root(true).'/components/com_flexicontent/assets/js/sortables.js' );
			
			// Add the drag and drop sorting feature
			$js .= "
			jQuery(document).ready(function(){
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag',
					containment: 'parent',
					tolerance: 'pointer'
				});
			});
			";
			
			if ($max_values) FLEXI_J16GE ? JText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true) : fcjsJText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true);
			$js .= "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id." = ".$max_values.";

			function addField".$field->id."(el) {
				if((rowCount".$field->id." >= maxValues".$field->id.") && (maxValues".$field->id." != 0)) {
					alert(Joomla.JText._('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + maxValues".$field->id.");
					return 'cancel';
				}
				
				var thisField 	 = jQuery(el).prev().children().last();
				var thisNewField = thisField.clone();
				
				jQuery(thisNewField).find('input.urllink').attr('value','".$default_link."');
				jQuery(thisNewField).find('input.urllink').attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][link]');
				jQuery(thisNewField).find('input.urllink').attr('id','".$elementid."_'+uniqueRowNum".$field->id.");
				";
				
			if ($usetitle) $js .= "
				jQuery(thisNewField).find('input.urltitle').attr('value','".$default_title."');
				jQuery(thisNewField).find('input.urltitle').attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][title]');
				";
				
			$js .= "
				jQuery(thisNewField).find('input.urlhits').attr('value','0');
				jQuery(thisNewField).find('input.urlhits').attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][hits]');
				
				// Set hits to zero for new row value
				jQuery(thisNewField).find('span span').html('0');
				jQuery(thisNewField).css('display', 'none');
				jQuery(thisNewField).insertAfter( jQuery(thisField) );
				
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag',
					containment: 'parent',
					tolerance: 'pointer'
				});
				
				jQuery(thisNewField).show('slideDown');
				
				rowCount".$field->id."++;       // incremented / decremented
				uniqueRowNum".$field->id."++;   // incremented only
			}

			function deleteField".$field->id."(el)
			{
				if(rowCount".$field->id." <= 1) return;
				var row = jQuery(el).closest('li');
				jQuery(row).hide('slideUp', function() { jQuery(this).remove(); } );
				rowCount".$field->id."--;
			}
			";
			
			$css = '
			#sortables_'.$field->id.' { float:left; margin: 0px; padding: 0px; list-style: none; white-space: nowrap; }
			#sortables_'.$field->id.' li {
				clear: both;
				display: block;
				list-style: none;
				height: auto;
				position: relative;
			}
			#sortables_'.$field->id.' li.sortabledisabled {
				background : transparent url(components/com_flexicontent/assets/images/move3.png) no-repeat 0px 1px;
			}
			#sortables_'.$field->id.' li input { cursor: text;}
			#add'.$field->name.' { margin-top: 5px; clear: both; display:block; }
			#sortables_'.$field->id.' li .admintable { text-align: left; }
			#sortables_'.$field->id.' li:only-child span.fcfield-drag, #sortables_'.$field->id.' li:only-child input.fcfield-button { display:none; }
			#sortables_'.$field->id.' label.label, #sortables_'.$field->id.' input.urllink, #sortables_'.$field->id.' input.urltitle, #sortables_'.$field->id.' input.fcfield-button {
				float: none!important;
				display: inline-block!important;
			}
			';
			
			$remove_button = '<input class="fcfield-button" type="button" value="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);" />';
			$move2 	= '<span class="fcfield-drag">'.JHTML::image( JURI::base().'components/com_flexicontent/assets/images/move2.png', JText::_( 'FLEXI_CLICK_TO_DRAG' ) ) .'</span>';
		} else {
			$remove_button = '';
			$move2 = '';
			$js = '';
			$css = '';
		}
		
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);
		
		$field->html = array();
		$n = 0;
		foreach ($field->value as $value)
		{
			if ( !strlen($value) ) continue;
			
			// Compatibility for old unserialized values
			if ( @unserialize($value)!== false || $value === 'b:0;' ) {
				$value = unserialize($value);
			} else {
				$value = array('link' => $value, 'title' => '', 'hits'=>0);
			}
			$fieldname_n = $fieldname.'['.$n.']';
			$elementid_n = $elementid.'_'.$n;
			
			$link = '
				<label class="label">'.JText::_( 'FLEXI_FIELD_URL' ).':</label>
				<input class="urllink fcfield_textval'.$required.'" name="'.$fieldname_n.'[link]" id="'.$elementid_n.'" type="text" size="'.$size.'" value="'.$value['link'].'" />
			';
			
			$title = '';
			if ($usetitle) $title = '
				<label class="label">'.JText::_( 'FLEXI_FIELD_URLTITLE' ).':</label>
				<input class="urltitle fcfield_textval" name="'.$fieldname_n.'[title]" type="text" size="'.$size.'" value="'.@$value['title'].'" />
			';
			
			$hits = (int) @ $value['hits'];
			$hits = '
					<input class="urlhits" name="'.$fieldname_n.'[hits]" type="hidden" value="'.$hits.'" />
					<span class="hits"><span class="hitcount">'.$hits.'</span> '.JText::_( 'FLEXI_FIELD_HITS' ).'</span>
			';
			
			$field->html[] = '
				'.$link.'
				'.$title.'
				'.$hits.'
				'.$move2.'
				'.$remove_button.'
				';
			
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		if ($multiple) { // handle multiple records
			$_list = "<li>". implode("</li>\n<li>", $field->html) ."</li>\n";
			$field->html = '
				<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$_list. '</ul>
				<input type="button" class="fcfield-addvalue" onclick="addField'.$field->id.'(this);" value="'.JText::_( 'FLEXI_ADD_VALUE' ).'" />
			';
		} else {  // handle single values
			$field->html = $field->html[0];
		}
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// Get isMobile / isTablet Flags
		static $isMobile = null;
		static $isTablet = null;
		static $useMobile = null;
		if ($useMobile===null) 
		{
			$cparams = JComponentHelper::getParams( 'com_flexicontent' );
			$force_desktop_layout = $cparams->get('force_desktop_layout', 0 );
			//$start_microtime = microtime(true);
			$mobileDetector = flexicontent_html::getMobileDetector();
			$isMobile = $mobileDetector->isMobile();
			$isTablet = $mobileDetector->isTablet();
			$useMobile = $force_desktop_layout  ?  $isMobile && !$isTablet  :  $isMobile;
			//$time_passed = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			//printf('<br/>-- [Detect Mobile: %.3f s] ', $time_passed/1000000);
		}
		
		$field->label = JText::_($field->label);
		
		// some parameter shortcuts
		$target = $field->parameters->get( 'targetblank', 0 );
		$target_param   = $target ? ' target="_blank"' : '';
		$display_hits = $field->parameters->get( 'display_hits', 0 ) ;
		$add_hits_img = $display_hits == 1 || $display_hits == 3;
		$add_hits_txt = $display_hits == 2 || $display_hits == 3 || $isMobile;
		$rel_nofollow = $field->parameters->get( 'add_rel_nofollow', 0 ) ? ' rel="nofollow"'    : '';
		
		// This is field 's MAIN value property
		$link_usage   = $field->parameters->get( 'link_usage', 0 ) ;
		$default_link = ($link_usage == 2) ? $field->parameters->get( 'default_value_link', '' ) : '';
		
		// Optional value properties
		$usetitle      = $field->parameters->get( 'use_title', 0 ) ;
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($title_usage == 2)  ?  JText::_($field->parameters->get( 'default_value_title', '' )) : '';
		
		// Get field values
		$values = $values ? $values : $field->value;
		// DO NOT terminate yet if value is empty since a default value on empty may have been defined
		
		// Handle default value loading, instead of empty value
		if ( empty($values) && !strlen($default_link) ) {
			$field->{$prop} = '';
			return;
		} else if ( empty($values) && strlen($default_link) ) {
			$values = array();
			$values[0]['link']  = JText::_($default_link);
			$values[0]['title'] = JText::_($default_title);
			$values[0]['hits']  = 0;
			$values[0] = serialize($values[0]);
		}
		
		// Value handling parameters
		$multiple       = $field->parameters->get( 'allow_multiple', 1 ) ;
		
		// Prefix - Suffix - Separator parameters, replacing other field values if found
		$remove_space = $field->parameters->get( 'remove_space', 0 ) ;
		$pretext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'pretext', '' ), 'pretext' );
		$posttext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'posttext', '' ), 'posttext' );
		$separatorf	= $field->parameters->get( 'separatorf', 1 ) ;
		$opentag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'opentag', '' ), 'opentag' );
		$closetag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'closetag', '' ), 'closetag' );
		
		if($pretext)  { $pretext  = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) { $posttext = $remove_space ? $posttext : ' ' . $posttext; }
		
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
		
		// Optimization, do some stuff outside the loop
		static $hits_icon = null;
		if ($hits_icon===null && ($display_hits==1 || $display_hits==3)) {
			$_attribs = $display_hits==1 ? 'class="hasTip" title=":: %s '.JText::_( 'FLEXI_HITS', true ).'"' : '';
			$hits_icon = FLEXI_J16GE ?
				JHTML::image('components/com_flexicontent/assets/images/'.'user.png', JText::_( 'FLEXI_HITS' ), $_attribs) :
				JHTML::_('image.site', 'user.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_HITS' ), $_attribs);
		}
		
		// needed for backend display
		//if ($display_hits)
		//	$isAdmin = JFactory::getApplication()->isAdmin();
		
		// initialise property
		$field->{$prop} = array();
		$n = 0;
		foreach ($values as $value)
		{
			if ( !strlen($value) ) continue;
			
			// Compatibility for old unserialized values
			if ( @unserialize($value)!== false || $value === 'b:0;' ) {
				$value = unserialize($value);
			} else {
				$value = array('link' => $value, 'title' => '', 'hits'=>0);
			}
			if ( empty($value['link']) ) continue;  // no link ...
			
			// If not using property or property is empty, then use default property value
			// NOTE: default property values have been cleared, if (propertyname_usage != 2)
			$title    = ($usetitle && @$value['title']   )  ?  $value['title']    : $default_title;
			$hits     = (int) @ $value['hits'];
			
			$link_params  = $title ? ' title="'.$title.'"' : '';
			$link_params .= $target_param;
			$link_params .= $rel_nofollow;
			
			if ( $field->parameters->get( 'use_direct_link', 0 ) )
				// Direct access to the web-link, hits counting not possible
				$href = $value['link'];
			else 
				// Indirect access to the web-link, via calling FLEXIcontent component, thus counting hits too
				$href = JRoute::_( 'index.php?option=com_flexicontent&fid='. $field->id .'&cid='.$field->item_id.'&ord='.($n+1).'&task=weblink' );
			
			// Create indirect link to web-link address with custom displayed text
			if( empty($linktext) )
				$linktext = $title ? $title: $this->cleanurl($value['link']);
			$field->{$prop}[$n] = $pretext. '<a href="' .$href. '" '.$link_params.'>' .$linktext. '</a>' .$posttext;
			
			// HITS: either as icon or as inline text or both
			$hits_html = '';
			if ($display_hits && $hits)
			{
				$hits_html = '<span class="fcweblink_hits">';
				if ( $add_hits_img && @ $hits_icon ) {
					$hits_html .= sprintf($hits_icon, $hits);
				}
				if ( $add_hits_txt ) {
					$hits_html .= '('.$hits.'&nbsp;'.JTEXT::_('FLEXI_HITS').')';
				}
				$hits_html .= '</span>';
				if ($prop == 'display_hitsonly')
					$field->{$prop}[$n] = $hits_html;
				else
					$field->{$prop}[$n] .= ' '. $hits_html;
			}
			
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		// Apply seperator and open/close tags
		if(count($field->{$prop})) {
			$field->{$prop} = implode($separatorf, $field->{$prop});
			$field->{$prop} = $opentag . $field->{$prop} . $closetag;
		} else {
			$field->{$prop} = '';
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
		if ( !is_array($post) && !strlen($post) ) return;
		
		$is_importcsv = JRequest::getVar('task') == 'importcsv';
		
		// Make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;
		
		// Reformat the posted data
		$newpost = array();
		$new = 0;
		foreach ($post as $n => $v)
		{
			// support for basic CSV import / export
			if ( $is_importcsv && !is_array($post[$n]) ) {
				if ( @unserialize($post[$n])!== false || $post[$n] === 'b:0;' ) {  // support for exported serialized data)
					$post[$n] = unserialize($post[$n]);
				} else {
					$post[$n] = array('link' => $post[$n], 'title' => '', 'id' => '', 'class' => '', 'linktext' => '', 'hits'=>0);
				}
			}
			
			if ($post[$n]['link'] !== '')
			{
				$newpost[$new] = $post[$n];
				$http_prefix = (!preg_match("#^http|^https|^ftp#i", $post[$n]['link'])) ? 'http://' : '';
				$newpost[$new]['link']    = $http_prefix.$post[$n]['link'];
				$newpost[$new]['title']   = strip_tags(@$post[$n]['title']);
				$newpost[$new]['hits']    = (int) @ $post[$n]['hits'];
				$new++;
			}
		}
		$post = $newpost;
		
		// Serialize multi-property data before storing them into the DB
		foreach($post as $i => $v) {
			$post[$i] = serialize($v);
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
	function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		
		// a. Each of the values of $values array will be added to the advanced search index as searchable text (column value)
		// b. Each of the indexes of $values will be added to the column 'value_id',
		//    and it is meant for fields that we want to be filterable via a drop-down select
		// c. If $values is null then only the column 'value' will be added to the search index after retrieving 
		//    the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   containts property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array('link','title'), $search_properties=array('title'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		// a. Each of the values of $values array will be added to the basic search index (one record per item)
		// b. If $values is null then the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   containts property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array('link','title'), $search_properties=array('title'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	
	// **********************
	// VARIOUS HELPER METHODS
	// **********************
	
	// Get a url without the protocol
	function cleanurl($url)
	{
		$prefix = array("http://", "https://", "ftp://");
		$cleanurl = str_replace($prefix, "", $url);
		return $cleanurl;
	}
	
}
