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
	static $field_types = array('date');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsDate( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_date', JPATH_ADMINISTRATOR);
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
		
		$date_source = $field->parameters->get('date_source', 0);
		if ( $date_source ) {
			$date_source_str = 'Automatic field (shows this content \'s %s publication date)';
			$date_source_str = sprintf($date_source_str, ($date_source == 1) ? '<b>start</b>' :  '<b>end</b>');
			$_value = ($date_source == 1) ? $item->publish_up : $item->publish_down;
			$field->html =
				 '<div style="float:left">'
				.' <div class="fc_mini_note_box">'.$date_source_str.'</div>'
				. $_value
				.'</div>';
			return;
		}
		
		// some parameter shortcuts
		$size				= $field->parameters->get( 'size', 30 ) ;
		$multiple		= $field->parameters->get( 'allow_multiple', 1 ) ;
		$maxval			= $field->parameters->get( 'max_values', 0 ) ;
		$required = $field->parameters->get( 'required', 0 ) ;
		$required = $required ? ' required' : '';
		
		$document	= JFactory::getDocument();
		$config 	= JFactory::getConfig();
		$app      = JFactory::getApplication();
		$user			= JFactory::getUser();
		
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
		
		$fieldname = FLEXI_J16GE ? 'custom['.$field->name.'][]' : $field->name.'[]';
		$elementid = FLEXI_J16GE ? 'custom_'.$field->name : $field->name;
		
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
					if (MooTools.version>='1.2.4') {
						var fx = new Fx.Morph(thisNewField, {duration: 0, transition: Fx.Transitions.linear});
					} else {
						var fx = thisNewField.effects({duration: 0, transition: Fx.Transitions.linear});
					}
					
					jQuery(thisNewField).find('input').first().val('');  /* First element is the value input field, second is e.g remove button */
					jQuery(thisNewField).insertAfter( jQuery(thisField) );

					var input = jQuery(thisNewField).find('input').first();
					input.attr('id', '".$elementid."_'+uniqueRowNum".$field->id.");
					var img = input.next();
					img.attr('id', '".$elementid."_' +uniqueRowNum".$field->id." +'_img');
					
					
					Calendar.setup({
        				inputField:	input.attr('id'),
        				ifFormat:		'%Y-%m-%d',
        				button:			img.attr('id'),
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
			}
			";
			$document->addScriptDeclaration($js);
			
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
			';
			
			$remove_button = '<input class="fcfield-button" type="button" value="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);" />';
			$move2 	= '<span class="fcfield-drag">'.JHTML::image ( JURI::root().'components/com_flexicontent/assets/images/move2.png', JText::_( 'FLEXI_CLICK_TO_DRAG' ) ) .'</span>';
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
			$calendar = FlexicontentFields::createCalendarField($value, $date_allowtime, $fieldname, $elementid.'_'.$n, $attribs_arr=array('class'=>$required), $skip_on_invalid=true, $timezone);
			if (!$calendar)  { $skipped_vals[] = $value; continue; }
			
			$field->html[] =
				$calendar.'
				'.$move2.'
				'.$remove_button.'
				';
			
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		if ($multiple) { // handle multiple records
			$field->html = '<li>'. implode('</li><li>', $field->html) .'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			$field->html .= '<input type="button" class="fcfield-addvalue" onclick="addField'.$field->id.'(this);" value="'.JText::_( 'FLEXI_ADD_VALUE' ).'" />';
		} else {  // handle single values
			$field->html = '<div>'.$field->html[0].'</div>';
		}
		
		$field->html =
			 '<div style="float:left">'
			.($show_usage ? ' <div class="fc_mini_note_box">'.$append_str.'</div>' : '')
			.  $field->html
			.'</div>';
			
		if ( count($skipped_vals) )
			$app->enqueueMessage( JText::sprintf('FLEXI_FIELD_EDIT_VALUES_SKIPPED', $field->label, implode(',',$skipped_vals)), 'notice' );
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		
		// Some variables
		$config = JFactory::getConfig();
		$user = JFactory::getUser();
		
		// Get field values
		$values = $values ? $values : $field->value;

		$date_source = $field->parameters->get('date_source', 0);
		if ( $date_source ) {
			$_value = ($date_source == 1) ? $item->publish_up : $item->publish_down;
			$values = array($_value);
		}
		
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
				$date = '';
			}
			
			$field->{$prop}[]	= $pretext.$date.$posttext;
			
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		$field->{$prop} = implode($separatorf, $field->{$prop});
		
		if ( !$field->{$prop} && $show_no_value )
			$field->{$prop} = JText::_($no_value_msg);
		else
			$field->{$prop} = $opentag . $field->{$prop} . $closetag;
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
		
		$config = JFactory::getConfig();
		$user = JFactory::getUser();
		
		$date_allowtime = $field->parameters->get( 'date_allowtime', 1 ) ;
		$use_editor_tz  = $field->parameters->get( 'use_editor_tz', 0 ) ;
		$use_editor_tz = $date_allowtime ? $use_editor_tz : 0;
		
		if ($use_editor_tz == 0) {
			// Raw date input, ignore timezone, NOTE: this is OLD BEHAVIOUR of this field
			$timezone = FLEXI_J16GE ? 'UTC' : 0;
		} else {
			// For logged users the date values are in user's time zone, (unlogged users will submit in site default timezone)
			$timezone = $user->getParam('timezone', $config->get('offset'));  // this is numeric offset in J1.5 and timezone STRING in J2.5
		}
		
		// Make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;
		
		// Reformat the posted data
		$newpost = array();
		$new = 0;
		foreach ($post as $n=>$v)
		{
			if ($post[$n] !== '')
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
					$date = new JDate($post[$n], $timezone);
					$newpost[$new] = FLEXI_J16GE ? $date->toSql() : $date->toMySQL();
				}
				$new++;
			}
		}
		$post = $newpost;
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
		
		self::onDisplayFilter($filter, $value, $formName);
	}
	
	
	// Method to display a category filter for the category view
	function onDisplayFilter(&$filter, $value='', $formName='adminForm')
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$date_filter_group = $filter->parameters->get('date_filter_group', 'month');
		if ($date_filter_group=='year') { $date_valformat='%Y'; $date_txtformat='%Y'; }
		else if ($date_filter_group=='month') { $date_valformat='%Y-%m'; $date_txtformat='%Y-%b'; }
		else { $date_valformat='%Y-%m-%d'; $date_txtformat='%Y-%b-%d'; }
		
		$date_source = $filter->parameters->get('date_source', 0);
		if ( ! $date_source ) {
			$valuecol = sprintf(' DATE_FORMAT(fi.value, "%s") ', $date_valformat);
			$textcol  = sprintf(' DATE_FORMAT(fi.value, "%s") ', $date_txtformat);
		} else {
			$_value_col = ($date_source == 1) ? 'i.publish_up' : 'i.publish_down';
			$valuecol = sprintf(' DATE_FORMAT(%s, "%s") ', $_value_col, $date_valformat);
			$textcol  = sprintf(' DATE_FORMAT(%s, "%s") ', $_value_col, $date_txtformat);
		}
		
		$display_filter_as = $filter->parameters->get( 'display_filter_as', 0 );  // Filter Type of Display
		$filter_as_range = in_array($display_filter_as, array(2,3,)) ;
		
		// WARNING: we can not use column alias in from, join, where, group by, can use in having (some DB e.g. mysql) and in order by
		// partial SQL clauses
		$filter->filter_valuesselect = ' '.$valuecol.' AS value, '.$textcol.' AS text';
		$filter->filter_valuesjoin   = null;  // use default
		$filter->filter_valueswhere  = null;  // use default
		// full SQL clauses
		$filter->filter_groupby = ' GROUP BY '.$valuecol;
		$filter->filter_having  = null;  // use default
		$filter->filter_orderby = ' ORDER BY '.$valuecol;
		FlexicontentFields::createFilter($filter, $value, $formName);
	}
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for content lists e.g. category view, and not for search view
	function getFiltered(&$filter, $value)
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$date_filter_group = $filter->parameters->get('date_filter_group', 'month');
		if ($date_filter_group=='year') { $date_valformat='%Y'; }
		else if ($date_filter_group=='month') { $date_valformat='%Y-%m';}
		else { $date_valformat='%Y-%m-%d'; }

		$date_source = $filter->parameters->get('date_source', 0);
		
		if ( ! $date_source ) {
			$filter->filter_colname    = sprintf(' DATE_FORMAT(rel.value, "%s") ', $date_valformat);
		} else {
			$_value_col = ($date_source == 1) ? 'c.publish_up' : 'c.publish_down';
			$filter->filter_colname    = sprintf(' DATE_FORMAT(%s, "%s") ', $_value_col, $date_valformat);
		}
		
		$filter->filter_colname    = sprintf(' DATE_FORMAT(rel.value, "%s") ', $date_valformat);
		$filter->filter_valuesjoin = null;   // use default
		$filter->filter_valueformat = sprintf(' DATE_FORMAT(__filtervalue__, "%s") ', $date_valformat);
		return FlexicontentFields::getFiltered($filter, $value, $return_sql=true);
	}
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$field, $value)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$date_source = $field->parameters->get('date_source', 0);
		if ( $date_source ) {
			JFactory::getApplication()->enqueueMessage( "Field: '".$field->label."' is using start/end publication dates and cannot be used as filter in search view" , 'notice' );
			return;
		}
		
		return FlexicontentFields::getFilteredSearch($field, $value, $return_sql=true);
	}
	
	
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item) {
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		
		$values = $this->_prepareForSearchIndexing($field, $post, $for_advsearch=1);
		
		// a. Each of the values of $values array will be added to the advanced search index as searchable text (column value)
		// b. Each of the indexes of $values will be added to the column 'value_id',
		//    and it is meant for fields that we want to be filterable via a drop-down select
		// c. If $values is null then only the column 'value' will be added to the search index after retrieving 
		//    the column value from table 'flexicontent_fields_item_relations' for current field / item pair
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   containts property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexAdvSearch($field, $values, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		$values = $this->_prepareForSearchIndexing($field, $post, $for_advsearch=0);
		
		// a. Each of the values of $values array will be added to the basic search index (one record per item)
		// b. If $values is null then the column value from table 'flexicontent_fields_item_relations' for current field / item pair
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   containts property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexSearch($field, $values, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	// Method to prepare for indexing, either preparing SQL query (if post is null) or formating/preparing given $post data for usage bu index
	function _prepareForSearchIndexing(&$field, &$post, $for_advsearch=0)
	{
		$date_filter_group = $field->parameters->get( $for_advsearch ? 'date_filter_group_s' : 'date_filter_group', 'month');
		if ($date_filter_group=='year') { $date_valformat='%Y'; $date_txtformat='%Y'; }
		else if ($date_filter_group=='month') { $date_valformat='%Y-%m'; $date_txtformat='%Y-%b'; }
		else { $date_valformat='%Y-%m-%d'; $date_txtformat='%Y-%b-%d'; }
		
		if ($post===null) {
			$valuecol = sprintf(' DATE_FORMAT(fi.value, "%s") ', $date_valformat);
			$textcol = sprintf(' DATE_FORMAT(fi.value, "%s") ', $date_txtformat);
			
			$field->field_valuesselect = ' '.$valuecol.' AS value_id, '.$textcol.' AS value';
			$field->field_groupby = ' GROUP BY '.$valuecol;
			$values = null;
		} else {
			$values = array();
			$db = &JFactory::getDBO();
			if ($post) foreach ($post as $v) {
				$valuecol = sprintf(' DATE_FORMAT("%s", "%s") ', $v, $date_valformat);
				$textcol = sprintf(' DATE_FORMAT("%s", "%s") ', $v, $date_txtformat);
				$query = 'SELECT  '.$valuecol.' AS value_id, '.$textcol.' AS value';
				$db->setQuery($query);
				$value = $db->loadObjectList();
				$values[$value[0]->value_id] = $value[0]->value;
			}
		}
		return $values;
	}
}
