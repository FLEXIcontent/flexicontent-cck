<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.date
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

class plgFlexicontent_fieldsDate extends JPlugin
{
	function plgFlexicontent_fieldsDate( &$subject, $params )
	{
		parent::__construct( $subject, $params );
        	JPlugin::loadLanguage('plg_flexicontent_fields_date', JPATH_ADMINISTRATOR);
	}
	
	
	function onAdvSearchDisplayField(&$field, &$item)
	{
		plgFlexicontent_fieldsDate::onDisplayField($field, $item);
	}
	
	
	function onDisplayField(&$field, &$item)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'date') return;
		
		$field->label = JText::_($field->label);
		
		// some parameter shortcuts
		$multiple			= $field->parameters->get( 'allow_multiple', 1 ) ;
		$maxval				= $field->parameters->get( 'max_values', 0 ) ;
		$required 		= $field->parameters->get( 'required', 0 ) ;
		$required 		= $required ? ' required' : '';
		
		$config = JFactory::getConfig();
		$user = JFactory::getUser();
		$app      = & JFactory::getApplication();
		$document	= & JFactory::getDocument();
		
		$show_usage = $field->parameters->get( 'show_usage', 0 ) ;
		$date_allowtime = $field->parameters->get( 'date_allowtime', 1 ) ;
		$use_editor_tz  = $field->parameters->get( 'use_editor_tz', 0 ) ;
		$use_editor_tz  = $date_allowtime ? $use_editor_tz : 0;
		
		$append_str = '';
		if ($date_allowtime)
		{
			$append_str = JText::_('FLEXI_DATE_CAN_ENTER_TIME');
			$append_str .= ($date_allowtime==2) ? '<br/>'.JText::_('FLEXI_DATE_USE_ZERO_TIME_ON_EMPTY') : '';
			
			if ($use_editor_tz == 0)
			{
				// Raw date storing, ignoring timezone. NOTE: this is OLD BEHAVIOUR
				$timezone = FLEXI_J16GE ? 'UTC' : 0;
				$append_str .= '<br/>'.JText::_('FLEXI_DATE_TIMEZONE_USAGE_DISABLED');
			}
			else
			{
				$append_str .= '<br/>'.JText::_('FLEXI_DATE_TIMEZONE_USAGE_ENABLED');
				// Use timezone of editor, unlogged editor will use site's default timezone
				$timezone = $user->getParam('timezone', $config->get('offset'));
				if (FLEXI_J16GE) {
					$tz = new DateTimeZone($timezone);
					$tz_offset = $tz->getOffset(new JDate()) / 3600;
				} else {
					$tz_offset = $timezone;
				}
				$tz_info =  $tz_offset > 0 ? ' UTC +'.$tz_offset : ' UTC '.$tz_offset;

				$append_str .= '<br/>'.JText::_($user->id ? 'FLEXI_DATE_ENTER_HOURS_IN_YOUR_TIMEZONE' : 'FLEXI_DATE_ENTER_HOURS_IN_TIMEZONE').': '.$tz_info;
			}
		}
		$append_str = $append_str ? '<b>'.JText::_('FLEXI_NOTES').'</b>: '.$append_str : '';
		
		// initialise property
		if (!$field->value) {
			$field->value = array();
			$field->value[0] = '';
		}
		
		if ($multiple) // handle multiple records
		{
			//add the drag and drop sorting feature
			$js = "
			window.addEvent('domready', function(){
				new Sortables($('sortables_".$field->id."'), {
					'constrain': true,
					'clone': true,
					'handle': '.fcfield-drag'
					});			
				});
			";
			if (!FLEXI_J16GE) $document->addScript( JURI::root().'administrator/components/com_flexicontent/assets/js/sortables.js' );
			$document->addScriptDeclaration($js);

			$js = "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxVal".$field->id."		= ".$maxval.";

			function addField".$field->id."(el) {
				if((rowCount".$field->id." < maxVal".$field->id.") || (maxVal".$field->id." == 0)) {

					var thisField 	 = $(el).getPrevious().getLast();
					var thisNewField = thisField.clone();
					var fx			 = thisNewField.effects({duration: 0, transition: Fx.Transitions.linear});
					thisNewField.getFirst().setProperty('value','');

					thisNewField.injectAfter(thisField);

					var input = thisNewField.getFirst();
					input.id = '".$field->name."_'+uniqueRowNum".$field->id.";
					var img = input.getNext();
					img.id = '".$field->name."_' +uniqueRowNum".$field->id." +'_img';
		
					Calendar.setup({
        				inputField:		'".$field->name."_'+uniqueRowNum".$field->id.",
        				ifFormat:		'%Y-%m-%d',
        				button:			'".$field->name."_' +uniqueRowNum".$field->id." +'_img',
        				align:			'Tl',
        				singleClick:	true
					});
					
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
				if(rowCount".$field->id." > 1)
				{
					var field	= $(el);
					var row		= field.getParent();
					var fx		= row.effects({duration: 300, transition: Fx.Transitions.linear});
					
					fx.start({
						'height': 0,
						'opacity': 0
						}).chain(function(){
							row.remove();
						});
					rowCount".$field->id."--;
				}
			}
			";
			$document->addScriptDeclaration($js);
			
			$css = '
			#sortables_'.$field->id.' { float:left; margin: 0px; padding: 0px; list-style: none; white-space: nowrap; }
			#sortables_'.$field->id.' li {
				clear: both;
				display: block;
				list-style: none;
				position: relative;
				height:20px;
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
			$move2 	= '<span class="fcfield-drag">'.JHTML::image ( JURI::root().'administrator/components/com_flexicontent/assets/images/move3.png', JText::_( 'FLEXI_CLICK_TO_DRAG' ) ) .'</span>';
		} else {
			$remove_button = '';
			$move2 = '';
			$css = '';
		}
		
		$document->addStyleDeclaration($css);
		
		$field->html = array();
		$n = 0;
		$skipped_vals = array();
		foreach ($field->value as $value)
		{
			@list($date, $time) = preg_split('#\s+#', $value, $limit=2);
			$time = ($date_allowtime==2 && !$time) ? '00:00' : $time;
			
			$valid_date = true;
			try {
				if ( !$value) {
					$date = '';
				} else if (!$date_allowtime || !$time) {
					$date = JHTML::_('date',  $date, JText::_( FLEXI_J16GE ? 'Y-m-d' : '%Y-%m-%d' ));
				} else {
					$date = JHTML::_('date',  $value, JText::_( FLEXI_J16GE ? 'Y-m-d H:i' : '%Y-%m-%d %H:%M' ));
				}
			} catch ( Exception $e ) {
				if ($value) $skipped_vals[] = $value;
				$date = '';
			}
			$calendar = JHTML::_('calendar', $date, $field->name.'[]', $field->name.'_'.$n, '%Y-%m-%d', 'class="'.$required.'"');
			
			$field->html[] =
				$calendar.'
				'.$remove_button.'
				'.$move2.'
				';
			
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		if ($multiple) { // handle multiple records
			$field->html = '<li>'. implode('</li><li>', $field->html) .'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			$field->html .= '<input type="button" class="fcfield-addvalue" onclick="addField'.$field->id.'(this);" value="'.JText::_( 'FLEXI_ADD_VALUE' ).'" />';
		} else {  // handle single values
			$field->html = '<div>' . $field->html[0] . '</div>';
		}
		
		$field->html =
			 '<div style="float:left">'
			.($show_usage ? ' <div class="fc_mini_note_box">'.$append_str.'</div>' : '')
			.  $field->html
			.'</div>';
			
		if ( count($skipped_vals) )
			$app->enqueueMessage( JText::sprintf('FLEXI_FIELD_EDIT_VALUES_SKIPPED', $field->label, implode(',',$skipped_vals)), 'notice' );
	}


	function onBeforeSaveField( $field, &$post, &$file )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'date') return;
		if(!$post) return;
		
		$config = JFactory::getConfig();
		$user = JFactory::getUser();
		
		$date_allowtime = $field->parameters->get( 'date_allowtime', 1 ) ;
		$use_editor_tz  = $field->parameters->get( 'use_editor_tz', 0 ) ;
		$use_editor_tz = $date_allowtime ? $use_editor_tz : 0;
		
		if ($use_editor_tz == 0) {
			// Raw date input, ignore timezone, NOTE: this is OLD BEHAVIOUR of this field
			$tz_offset = 0;
		} else {
			// For logged users the date values are in user's time zone, (unlogged users will submit in site default timezone)
			$timezone = $user->getParam('timezone', $config->get('offset'));
			if (FLEXI_J16GE) {
				$tz = new DateTimeZone($timezone);
				$tz_offset = $tz->getOffset(new JDate()) / 3600;
			} else {
				$tz_offset = $timezone;
			}
		}
		
		$newpost = array();
		$new = 0;
		
		if(!is_array($post)) $post = array ($post);
		foreach ($post as $n=>$v)
		{
			if ($post[$n] != '')
			{
				// Check if dates are allowed to have time part
				@list($date, $time) = preg_split('#\s+#', $post[$n], $limit=2);
				$time = ($date_allowtime==2 && !$time) ? '00:00' : $time;
				
				if (!$date_allowtime)
				{
					// Time part not allowed
					$post[$n] = $date;
				}
				else if ($time)
				{
					// Time part exists
					$post[$n] = $date.' '.$time;
				}
				
				if (!$use_editor_tz || !$time)
				{
					// Dates have no timezone information, because either :
					// (a) ignoring timezone OR (b) no time given
					$newpost[$new] = $post[$n];
				}
				else
				{
					// Dates are in user's timezone, convert to UTC+0
					$date = new JDate($post[$n], $tz_offset);
					$newpost[$new] = FLEXI_J16GE ? $date->toSql() : $date->toMySQL();
				}
				$new++;
			}
		}
		$post = $newpost;
		
		// create the fulltext search index
		$searchindex = '';
		
		foreach ($post as $v)
		{
			$searchindex .= $v;
			$searchindex .= ' ';
		}

		$searchindex .= ' | ';

		$field->search = $field->issearch ? $searchindex : '';

		if($field->isadvsearch && JRequest::getVar('vstate', 0)==2) {
			plgFlexicontent_fieldsDate::onIndexAdvSearch($field, $post);
		}
	}
	
	
	function onIndexAdvSearch(&$field, $post) {
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'date') return;
		$db = &JFactory::getDBO();
		$post = is_array($post)?$post:array($post);
		$query = "DELETE FROM #__flexicontent_advsearch_index WHERE field_id='{$field->id}' AND item_id='{$field->item_id}' AND extratable='date';";
		$db->setQuery($query);
		$db->query();
		$i = 0;
		foreach($post as $v) {
			$query = "INSERT INTO #__flexicontent_advsearch_index VALUES('{$field->id}','{$field->item_id}','date','{$i}', ".$db->Quote($v).");";
			$db->setQuery($query);
			$db->query();
			$i++;
		}
		return true;
	}


	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'date') return;

		$field->label = JText::_($field->label);
		
		$config = JFactory::getConfig();
		$user = JFactory::getUser();
		
		$values = $values ? $values : $field->value;

		// Value handling parameters
		$multiple       = $field->parameters->get( 'allow_multiple', 1 ) ;
		$date_allowtime = $field->parameters->get( 'date_allowtime', 1 ) ;
		$use_editor_tz  = $field->parameters->get( 'use_editor_tz', 0 ) ;
		$use_editor_tz  = $date_allowtime ? $use_editor_tz : 0;
		$customdate     = $field->parameters->get( 'custom_date', FLEXI_J16GE ? 'Y-m-d' : '%Y-%m-%d' ) ;
		$dateformat     = $field->parameters->get( 'date_format', $customdate ) ;
		$show_no_value  = $field->parameters->get( 'show_no_value', 0) ;
		$no_value_msg   = $field->parameters->get( 'no_value_msg', 'FLEXI_NO_VALUE') ;
		
		$display_tz_logged   = $field->parameters->get( 'display_tz_logged', 2) ;
		$display_tz_guests   = $field->parameters->get( 'display_tz_guests', 2) ;
		$display_tz_suffix   = $field->parameters->get( 'display_tz_suffix', 1) ;
		
		// Prefix - Suffix - Separator parameters
		$pretext			= $field->parameters->get( 'pretext', '' ) ;
		$posttext			= $field->parameters->get( 'posttext', '' ) ;
		$separatorf		= $field->parameters->get( 'separatorf', 1 ) ;
		$opentag			= $field->parameters->get( 'opentag', '' ) ;
		$closetag			= $field->parameters->get( 'closetag', '' ) ;
		$remove_space		= $field->parameters->get( 'remove_space', 0 ) ;
		
		if($pretext) { $pretext = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) {	$posttext = $remove_space ? $posttext : ' ' . $posttext; }
		
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

			default:
			$separatorf = '&nbsp;';
			break;
		}
		
		// Get timezone to use for displaying the date,  this is a string for J2.5 and an (offset) number for J1.5
		if ( !$use_editor_tz ) {
			// Raw date output, ignore timezone (no timezone info is printed), NOTE: this is OLD BEHAVIOUR of this field
			$tz_suffix_type = -1;
		} else if ($user->id) {
			$tz_suffix_type = $display_tz_logged;
		} else {
			$tz_suffix_type = $display_tz_guests;
		}
		
		$tz_info = '';
		switch ($tz_suffix_type)
		{
		default: // including value -1 for raw for output, see above
		case 0:
			$timezone = FLEXI_J16GE ? 'UTC' : 0;
			//$tz_info = '';
			break;
		case 1:
			$timezone = FLEXI_J16GE ? 'UTC' : 0;
			//$tz_info = ' UTC+0';
			break;
		case 2:
			$timezone = $config->get('offset');
			//$tz_info = ' (site's timezone)';
			break;
		case 3: 
			$timezone = $user->getParam('timezone' );
			//$tz_info = ' (local time)';
			break;
		}
		
		// display timezone suffix if this is enabled
		if ($display_tz_suffix && $tz_suffix_type > 0) {
			if (FLEXI_J16GE) {
				$tz = new DateTimeZone($timezone);
				$tz_offset = $tz->getOffset(new JDate()) / 3600;
			} else {
				// Raw date output  // FLEXI_J16GE ? 'UTC' : 0
				$tz_offset = $timezone;
			}
			$tz_info =  $tz_offset > 0 ? ' UTC +'.$tz_offset : ' UTC '.$tz_offset;
		}
		
		// initialise property
		$field->{$prop} = array();
		
		$n = 0;
		foreach ($values as $value) {
			if ( !strlen($value) ) continue;
			
			// Check if dates are allowed to have time part
			if ($date_allowtime) $date = $value;
			else @list($date, $time) = preg_split('#\s+#', $value, $limit=2);
			
			if ( empty($date) ) continue;
			
			try {
				$date = JHTML::_('date', $date, JText::_($dateformat), $timezone ).$tz_info;
			} catch ( Exception $e ) {
				$valid_date = false;
				$date = '';
			}
			
			$field->{$prop}[]	= $pretext.$date.$posttext;
			
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		$field->{$prop} = implode($separatorf, $field->{$prop});
		
		if ( !$field->{$prop} && $show_no_value ) $field->{$prop} = JText::_($no_value_msg);
	}


	function onDisplayFilter(&$filter, $value='')
	{
		// execute the code only if the field type match the plugin type
		if($filter->field_type != 'date') return;

		// ** some parameter shortcuts
		$label_filter 		= $filter->parameters->get( 'display_label_filter', 0 ) ;
		if ($label_filter == 2) $text_select = $filter->label; else $text_select = JText::_('FLEXI_ALL');
		$field->html = '';
		
		
		// *** Retrieve values
		// *** Limit values, show only allowed values according to category configuration parameter 'limit_filter_values'
		$results = flexicontent_cats::getFilterValues($filter);
		
		
		// *** Create the select form field used for filtering
		$options = array();
		$options[] = JHTML::_('select.option', '', '-'.$text_select.'-');
		
		foreach($results as $result) {
			if (!trim($result->value)) continue;
			$options[] = JHTML::_('select.option', $result->value, JText::_($result->text));
		}
		if ($label_filter == 1) $filter->html  .= $filter->label.': ';
		$filter->html	.= JHTML::_('select.genericlist', $options, 'filter_'.$filter->id, 'onchange="document.getElementById(\'adminForm\').submit();"', 'value', 'text', $value);
		
	}
	
	
	function onFLEXIAdvSearch(&$field, $fieldsearch) {
		if($field->field_type!='date') return;
		$db = &JFactory::getDBO();
		$resultfields = array();
		foreach($fieldsearch as $fsearch) {
			$query = "SELECT ai.search_index, ai.item_id FROM #__flexicontent_advsearch_index as ai"
				." WHERE ai.field_id='{$field->id}' AND ai.extratable='date' AND ai.search_index like '%{$fsearch}%';";
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
	}

}
