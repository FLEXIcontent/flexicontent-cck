<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.extendedweblink
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

class plgFlexicontent_fieldsExtendedWeblink extends JPlugin
{
	static $field_types = array('extendedweblink');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsExtendedWeblink( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_extendedweblink', JPATH_ADMINISTRATOR);
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
		
		// some parameter shortcuts
		$document  = JFactory::getDocument();
		$size      = $field->parameters->get( 'size', 30 ) ;
		$multiple  = $field->parameters->get( 'allow_multiple', 1 ) ;
		$maxval    = $field->parameters->get( 'max_values', 0 ) ;
		$allow_relative_addrs = $field->parameters->get( 'allow_relative_addrs', 0 ) ;
		
		$default_link_usage = $field->parameters->get( 'default_link_usage', 0 ) ;
		$default_link       = ($item->version == 0 || $default_link_usage > 0) ? $field->parameters->get( 'default_link', '' ) : '';
		
		$title_usage = $field->parameters->get( 'title_usage', 0 ) ;
		$text_usage  = $field->parameters->get( 'text_usage', 0 ) ;
		$class_usage = $field->parameters->get( 'class_usage', 0 ) ;
		$id_usage    = $field->parameters->get( 'id_usage', 0 ) ;
		
		$default_title  = ($item->version == 0 || $title_usage > 0) ? JText::_($field->parameters->get( 'default_title', '' )) : '';
		$default_text   = ($item->version == 0 || $text_usage > 0) ? $field->parameters->get( 'default_text', '' ) : '';
		$default_class  = ($item->version == 0 || $class_usage > 0) ? $field->parameters->get( 'default_class', '' ) : '';
		$default_id     = ($item->version == 0 || $id_usage > 0) ? $field->parameters->get( 'default_id', '' ) : '';
		
		$usetitle  = $field->parameters->get( 'use_title', 0 ) ;
		$usetext   = $field->parameters->get( 'use_text', 0 ) ;
		$useclass  = $field->parameters->get( 'use_class', 0 ) ;
		$useid     = $field->parameters->get( 'use_id', 0 ) ;
		
		$class_choices = $field->parameters->get( 'class_choices', '') ;
		
		$required   = $field->parameters->get( 'required', 0 ) ;
		$required   = $required ? ' required' : '';
		
		// Initialise property with default value
		if ( !$field->value ) {
			$field->value = array();
			$field->value[0]['link']  = JText::_($default_link);
			$field->value[0] = serialize($field->value[0]);
		}
		
		$js = "";
		
		if ($multiple) // handle multiple records
		{
			if (!FLEXI_J16GE) $document->addScript( JURI::root().'administrator/components/com_flexicontent/assets/js/sortables.js' );
			
			// Add the drag and drop sorting feature
			$js .= "
			window.addEvent('domready', function(){
				new Sortables($('sortables_".$field->id."'), {
					'constrain': true,
					'clone': true,
					'handle': '.fcfield-drag'
					});
				});
			";
			
			$fieldname = FLEXI_J16GE ? 'custom['.$field->name.']' : $field->name;
			$elementid = FLEXI_J16GE ? 'custom_'.$field->name : $field->name;
			
			$js .= "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxVal".$field->id."		= ".$maxval.";

			function addField".$field->id."(el) {
				if((rowCount".$field->id." < maxVal".$field->id.") || (maxVal".$field->id." == 0)) {

					var thisField 	 = $(el).getPrevious().getLast();
					var thisNewField = thisField.clone();
					if (MooTools.version>='1.2.4') {
						var fx = new Fx.Morph(thisNewField, {duration: 0, transition: Fx.Transitions.linear});
					} else {
						var fx = thisNewField.effects({duration: 0, transition: Fx.Transitions.linear});
					}
					
					thisNewField.getElements('input.urllink').setProperty('value','".$default_link."');
					thisNewField.getElements('input.urllink').setProperty('name','".$fieldname."['+uniqueRowNum".$field->id."+'][link]');
					thisNewField.getElements('input.urllink').setProperty('id','".$elementid."_'+uniqueRowNum".$field->id.");
					";
					
			if ($usetitle) $js .= "
					thisNewField.getElements('input.urltitle').setProperty('value','".$default_title."');
					thisNewField.getElements('input.urltitle').setProperty('name','".$fieldname."['+uniqueRowNum".$field->id."+'][title]');
					";
					
			if ($usetext) $js .= "
					thisNewField.getElements('input.urllinktext').setProperty('value','".$default_text."');
					thisNewField.getElements('input.urllinktext').setProperty('name','".$fieldname."['+uniqueRowNum".$field->id."+'][linktext]');
					";
					
			if ($useclass) $js .= "
					thisNewField.getElements('.urlclass').setProperty('value','".$default_class."');
					thisNewField.getElements('.urlclass').setProperty('name','".$fieldname."['+uniqueRowNum".$field->id."+'][class]');
					";
					
			if ($useid) $js .= "
					thisNewField.getElements('input.urlid').setProperty('value','".$default_id."');
					thisNewField.getElements('input.urlid').setProperty('name','".$fieldname."['+uniqueRowNum".$field->id."+'][id]');
					";
					
			$js .= "
					thisNewField.getElements('input.urlhits').setProperty('value','0');
					thisNewField.getElements('input.urlhits').setProperty('name','".$fieldname."['+uniqueRowNum".$field->id."+'][hits]');
					
					// Set hits to zero for new row value
					if (MooTools.version>='1.2.4') {
						thisNewField.getElements('span span').set('html','0');
					} else {
						thisNewField.getElements('span span').setHTML('0');
					}

					jQuery(thisNewField).insertAfter( jQuery(thisField) );
		
					new Sortables($('sortables_".$field->id."'), {
						'constrain': true,
						'clone': true,
						'handle': '.fcfield-drag'
					});			

					fx.start({ 'opacity': 1 }).chain(function(){
						this.setOptions({duration: 600});
						this.start({ 'opacity': 0 });
						})
						.chain(function(){
							this.setOptions({duration: 300});
							this.start({ 'opacity': 1 });
						});

					rowCount".$field->id."++;       // incremented / decremented
					uniqueRowNum".$field->id."++;   // incremented only
				}
			}

			function deleteField".$field->id."(el)
			{
				if(rowCount".$field->id." <= 1) return;
				var field	= $(el);
				var row		= field.getParent();
				if (MooTools.version>='1.2.4') {
					var fx = new Fx.Morph(row, {duration: 300, transition: Fx.Transitions.linear});
				} else {
					var fx = row.effects({duration: 300, transition: Fx.Transitions.linear});
				}
				
				fx.start({
					'height': 0,
					'opacity': 0
				}).chain(function(){
					(MooTools.version>='1.2.4')  ?  row.destroy()  :  row.remove();
				});
				rowCount".$field->id."--;
			}
			";
			
			$css = '
			#sortables_'.$field->id.' { float:left; margin: 0px; padding: 0px; list-style: none; white-space: nowrap; }
			#sortables_'.$field->id.' li {
				clear: both;
				display: block;
				list-style: none;
				position: relative;
			}
			#sortables_'.$field->id.' li.sortabledisabled {
				background : transparent url(components/com_flexicontent/assets/images/move3.png) no-repeat 0px 1px;
			}
			#sortables_'.$field->id.' li input { cursor: text;}
			#add'.$field->name.' { margin-top: 5px; clear: both; display:block; }
			#sortables_'.$field->id.' li .admintable { text-align: left; }
			#sortables_'.$field->id.' li:only-child span.fcfield-drag, #sortables_'.$field->id.' li:only-child input.fcfield-button { display:none; }
			';
			
			$remove_button = '<input class="fcfield-button" type="button" value="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);" />';
			$move2 	= '<span class="fcfield-drag">'.JHTML::image ( JURI::root().'components/com_flexicontent/assets/images/move2.png', JText::_( 'FLEXI_CLICK_TO_DRAG' ) ) .'</span>';
		} else {
			$remove_button = '';
			$move2 = '';
			$js = '';
			$css = '';
		}
		
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);
		
		
		
		// **************************************************************************
		// Create once the options for field properties that have drop down-selection
		// **************************************************************************
		if ($useclass==2) {
			// Parse the elements used by field unsetting last element if empty
			$choices = preg_split("/[\s]*%%[\s]*/", $class_choices);
			if ( empty($choices[count($choices)-1]) )	unset($choices[count($choices)-1]);
			
			// Split elements into their properties: value, label, extra_prop1, extra_prop2
			$elements = array();
			$k = 0;
			foreach ($choices as $choice) {
				$choice_props  = preg_split("/[\s]*::[\s]*/", $choice);
				if (count($choice_props) < 2) {
					echo "Error in field: ".$field->label.
						" while splitting class element: ".$choice.
						" properties needed: ".$props_needed.
						" properties found: ".count($choice_props);
					continue;
				}
				$class_elements[$k] = new stdClass();
				$class_elements[$k]->value = $choice_props[0];
				$class_elements[$k]->text  = $choice_props[1];
				$k++;
			}
			
			// Create the options for select drop down
			$class_options = array();
			$class_options[] = JHTML::_('select.option', '', '-');
			foreach ($class_elements as $element) {
				$class_options[] = JHTML::_('select.option', $element->value, JText::_($element->text));
			}
		}
		
		
		
		$field->html = array();
		$n = 0;
		foreach ($field->value as $value) {
			if ( empty($value) ) continue;
			$value = unserialize($value);
			
			$has_prefix = preg_match("#^http|^https|^ftp#i", $value['link']);
			
			$fieldname = FLEXI_J16GE ? 'custom['.$field->name.']['.$n.']' : $field->name.'['.$n.']';
			$elementid = FLEXI_J16GE ? 'custom_'.$field->name : $field->name;
			
			$link = '<tr><td class="key">'.JText::_( 'FLEXI_FIELD_URL' ).':</td><td><input class="urllink '.$required.'" id="'.$elementid.'_'.$n.'" name="'.$fieldname.'[link]" type="text" size="'.$size.'" value="'.$value['link'].'" /></td></tr>';
			
			if ($allow_relative_addrs==2) $autoprefix =
				'<tr><td class="key">'.JText::_( 'FLEXI_EXTWL_AUTOPREFIX' ).  ':</td><td>
					<input class="autoprefix" name="'.$fieldname.'[autoprefix]" type="radio" value="0" '.( !$has_prefix ? 'checked="checked"' : '' ).'/><label class="label" for="'.$fieldname.'[autoprefix]">'.JText::_('FLEXI_NO').'</label>
					<input class="autoprefix" name="'.$fieldname.'[autoprefix]" type="radio" value="1" '.( $has_prefix ? 'checked="checked"' : '' ).'/><label class="label" for="'.$fieldname.'[autoprefix]">'.JText::_('FLEXI_YES').'</label>
				</td></tr>';
			
			if ($usetitle) $title =
				'<tr><td class="key">'.JText::_( 'FLEXI_EXTWL_URLTITLE' ).    ':</td><td><input class="urltitle" name="'.$fieldname.'[title]" type="text" size="'.$size.'" value="'.(@$value['title'] ? $value['title'] : $default_title).'" /></td></tr>';

			if ($usetext) $linktext =
				'<tr><td class="key">'.JText::_( 'FLEXI_EXTWL_URLLINK_TEXT' ).':</td><td><input class="urllinktext" name="'.$fieldname.'[linktext]" type="text" size="'.$size.'" value="'.(@$value['linktext'] ? $value['linktext'] : $default_text).'" /></td></tr>';
			
			if ($useclass==1) $class =
				'<tr><td class="key">'.JText::_( 'FLEXI_EXTWL_URLCLASS' ).    ':</td><td><input class="urlclass" name="'.$fieldname.'[class]" type="text" size="'.$size.'" value="'.(@$value['class'] ? $value['class'] : $default_class).'" /></td></tr>';
			else if ($useclass==2) {
				$class_value = (@ $value['class'] ? $value['class'] : $default_class);
				$class_attribs = ' class="urlclass" ';
				$class =
					'<tr><td class="key">'.JText::_( 'FLEXI_EXTWL_URLCLASS' ).    ':</td><td>'.
					JHTML::_('select.genericlist', $class_options, $fieldname.'[class]', $class_attribs, 'value', 'text', $class_value, $class_elementid = '').
					'</td></tr>';
				//'<input class="urlclass" name="'.$fieldname.'[class]" type="text" size="'.$size.'" value="'.(@ $value['class'] ? $value['class'] : $default_class).'" />
			}
			//class_choices

			if ($useid) $id =
				'<tr><td class="key">'.JText::_( 'FLEXI_EXTWL_URLID' ).       ':</td><td><input class="urlid" name="'.$fieldname.'[id]" type="text" size="'.$size.'" value="'.(@$value['id'] ? $value['id'] : $default_id).'" /></td></tr>';
			
			$hits = @$value['hits'] ? $value['hits'] : 0;
			
			$field->html[] = '
				<table class="admintable"><tbody>
					'.$link.'
					'.@$autoprefix.'
					'.@$title.'
					'.@$linktext.'
					'.@$class.'
					'.@$id.'
				</tbody></table>
				'.$remove_button.'
				'.$move2.'
				<input class="urlhits" name="'.$fieldname.'[hits]" type="hidden" value="'.$hits.'" />
				<span class="hits"><span class="hitcount">'.$hits.'</span> '.JText::_( 'FLEXI_FIELD_HITS' ).'</span>
				';
			
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		if ($multiple) { // handle multiple records
			$field->html = '<li>'. implode('</li><li>', $field->html) .'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			$field->html .= '<input type="button" class="fcfield-addvalue" style="clear:both;" onclick="addField'.$field->id.'(this);" value="'.JText::_( 'FLEXI_ADD_WEBLINK' ).'" />';
		} else {  // handle single values
			$field->html = $field->html[0];
		}
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		
		// Get field values
		$values = $values ? $values : $field->value;
		if ( empty($values) ) { $field->{$prop} = ''; return; }
		
		// Prefix - Suffix - Separator parameters, replacing other field values if found
		$remove_space = $field->parameters->get( 'remove_space', 0 ) ;
		$pretext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'pretext', '' ), 'pretext' );
		$posttext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'posttext', '' ), 'posttext' );
		$separatorf	= $field->parameters->get( 'separatorf', 1 ) ;
		$opentag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'opentag', '' ), 'opentag' );
		$closetag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'closetag', '' ), 'closetag' );
		
		if($pretext)  { $pretext  = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) { $posttext = $remove_space ? $posttext : ' ' . $posttext; }
		
		// some parameter shortcuts
		$usetitle      = $field->parameters->get( 'use_title', 0 ) ;
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($title_usage == 2)  ?  JText::_($field->parameters->get( 'default_title', '' )) : '';
		
		$usetext      = $field->parameters->get( 'use_text', 0 ) ;
		$text_usage   = $field->parameters->get( 'text_usage', 0 ) ;
		$default_text = ($text_usage == 2)  ?  $field->parameters->get( 'default_text', '' ) : '';
		
		$useclass      = $field->parameters->get( 'use_class', 0 ) ;
		$class_usage   = $field->parameters->get( 'class_usage', 0 ) ;
		$default_class = ($class_usage == 2)  ?  $field->parameters->get( 'default_class', '' ) : '';
		
		$useid      = $field->parameters->get( 'use_id', 0 ) ;
		$id_usage	  = $field->parameters->get( 'id_usage', 0 ) ;
		$default_id = ($id_usage == 2)  ?  $field->parameters->get( 'default_id', '' ) : '';
		
		$target         = $field->parameters->get( 'target', '' );
		$target_param   = $target ? ' target="'.$target.'"' : '';
		
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
		
		// initialise property
		$field->{$prop} = array();
		$n = 0;
		foreach ($values as $value)
		{
			if ( empty($value) ) continue;
			$value  = unserialize($value);
			
			// If not using property or property is empty, then use default property value
			// NOTE: default property values have been cleared, if (propertyname_usage != 2)
			$title    = ($usetitle && @$value['title']   )  ?  $value['title']    : $default_title;
			$linktext = ($usetext  && @$value['linktext'])  ?  $value['linktext'] : $default_text;
			$class    = ($useclass && @$value['class']   )  ?  $value['class']    : $default_class;
			$id       = ($useid    && @$value['id']      )  ?  $value['id']       : $default_id;
			
			$link_params  = $title ? ' title="'.$title.'"' : '';
			$link_params .= $class ? ' class="'.$class.'"' : '';
			$link_params .= $id    ? ' id="'   .$id.'"'    : '';
			$link_params .= $target_param;
			
			// Set a displayed text for the link if one was not given and default value has not been set
			if( !$linktext )
				$linktext = $title ? $title: $this->cleanurl($value['link']);
			
			if ( $field->parameters->get( 'use_direct_link', 0 ) )
				// Direct access to the web-link, hits counting not possible
				$href = $value['link'];
			else 
				// Indirect access to the web-link, via calling FLEXIcontent component, thus counting hits too
				$href = JRoute::_( 'index.php?option=com_flexicontent&fid='. $field->id .'&cid='.$field->item_id.'&ord='.($n+1).'&task=weblink' );
			
			// Create indirect link to web-link address with custom displayed text
			$field->{$prop}[] = $pretext. '<a href="'.$href.'" '.$link_params.'>'. $linktext .'</a>' .$posttext;
			
			$n++;
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
		
		$allow_relative_addrs = $field->parameters->get( 'allow_relative_addrs', 0 ) ;
		$is_importcsv = JRequest::getVar('task') == 'importcsv';
		
		// Make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;
		
		// Reformat the posted data
		$newpost = array();
		$new = 0;
		foreach ($post as $n => $v)
		{
			// support for basic CSV import / export,  TO BE REMOVED added to the 'store' function of the model
			if ( $is_importcsv && !is_array($post[$n]) ) {
				if ( @unserialize($post[$n])!== false || $post[$n] === 'b:0;' ) {  // support for exported serialized data)
					$post[$n] = unserialize($post[$n]);
				} else {
					$post[$n] = array('link' => $post[$n], 'title' => '', 'id' => '', 'class' => '', 'linktext' => '', 'hits'=>0);
				}
			}
			
			if ($post[$n]['link'] !== '')
			{
				if ( $allow_relative_addrs ==1 || ($allow_relative_addrs==2 && !@$post[$n]['autoprefix']) ) {
					$http_prefix = '';
				} else {
					$http_prefix = (!preg_match("#^http|^https|^ftp#i", $post[$n]['link'])) ? 'http://' : '';
				}
				$newpost[$new]['link']    = $http_prefix.$post[$n]['link'];
				$newpost[$new]['title']   = strip_tags(@$post[$n]['title']);
				$newpost[$new]['id']      = strip_tags(@$post[$n]['id']);
				$newpost[$new]['class']   = strip_tags(@$post[$n]['class']);
				$newpost[$new]['linktext']= strip_tags(@$post[$n]['linktext']);
				$newpost[$new]['hits']    = (int) $post[$n]['hits'];
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
		
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array('link','title'), $search_properties=array('title'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array('link','title'), $search_properties=array('title'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	
	// **********************
	// VARIOUS HELPER METHODS
	// **********************
	
	function cleanurl($url)
	{
		$prefix = array("http://", "https://", "ftp://");
		$cleanurl = str_replace($prefix, "", $url);
		return $cleanurl;
	}
	
}
