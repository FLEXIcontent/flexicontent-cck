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

//jimport('joomla.plugin.plugin');
jimport('joomla.event.plugin');

class plgFlexicontent_fieldsDate extends JPlugin
{
	function plgFlexicontent_fieldsDate( &$subject, $params )
	{
		parent::__construct( $subject, $params );
        	JPlugin::loadLanguage('plg_flexicontent_fields_date', JPATH_ADMINISTRATOR);
	}
	function onAdvSearchDisplayField(&$field, &$item) {
		plgFlexicontent_fieldsDate::onDisplayField($field, $item);
	}
	function onDisplayField(&$field, &$item)
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'date') return;

		// some parameter shortcuts
		$multiple			= $field->parameters->get( 'allow_multiple', 1 ) ;
		$maxval				= $field->parameters->get( 'max_values', 0 ) ;
		$dateformat			= $field->parameters->get( 'date_format', '%Y-%m-%d' ) ;
		$required 			= $field->parameters->get( 'required', 0 ) ;
		$required 	= $required ? ' required' : '';

		// initialise property
		if (!$field->value) {
			$field->value = array();
			$field->value[0] = '';
		}
		
		if ($multiple) // handle multiple records
		{
			$document	= & JFactory::getDocument();

			//add the drag and drop sorting feature
			$js = "
			window.addEvent('domready', function(){
				new Sortables($('sortables_".$field->id."'), {
					'constrain': true,
					'clone': true,
					'handle': '.drag".$field->id."'
					});			
				});
			";
			$document->addScript( JURI::root().'administrator/components/com_flexicontent/assets/js/sortables.js' );
			$document->addScriptDeclaration($js);

			$js = "
			var uniqueRowNum".$field->id."	= ".count($field->value).";
			var curRowNum".$field->id."	= ".count($field->value).";
			var maxVal".$field->id."		= ".$maxval.";
			var test = 1;

			function addField".$field->id."(el) {
				if((curRowNum".$field->id." < maxVal".$field->id.") || (maxVal".$field->id." == 0)) {

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
						'handle': '.drag".$field->id."'
					});			

					fx.start({ 'opacity': 1 }).chain(function(){
						this.setOptions({duration: 600});
						this.start({ 'opacity': 0 });
						})
						.chain(function(){
							this.setOptions({duration: 300});
							this.start({ 'opacity': 1 });
						});

					curRowNum".$field->id."++;
					uniqueRowNum".$field->id."++;
					}
				}

			function deleteField".$field->id."(el) {
				if(curRowNum".$field->id." > 1) {

				var field	= $(el);
				var row		= field.getParent();
				var fx		= row.effects({duration: 300, transition: Fx.Transitions.linear});
				
				fx.start({
					'height': 0,
					'opacity': 0			
					}).chain(function(){
						row.remove();
					});
				curRowNum".$field->id."--;
				}
			}
			";
			$document->addScriptDeclaration($js);
			
			$css = '
			#sortables_'.$field->id.' { margin: 0px; padding: 0px; list-style: none; white-space: nowrap; }
			#sortables_'.$field->id.' li {
				list-style: none;
				height: 20px;
				width:500px;
				}
			#sortables_'.$field->id.' li.sortabledisabled {
				background : transparent url(components/com_flexicontent/assets/images/move3.png) no-repeat 0px 1px;
				}
			#sortables_'.$field->id.' li input { cursor: text;}
			#sortables_'.$field->id.' li input.fcbutton, .fcbutton { cursor: pointer; margin-left: 3px; }
			span.drag'.$field->id.' img {
				margin: -4px 8px;
				cursor: move;
				float: none;
				display: inline;
			}
			';
			$document->addStyleDeclaration($css);

			$move2 	= JHTML::image ( JURI::root().'administrator/components/com_flexicontent/assets/images/move3.png', JText::_( 'FLEXI_CLICK_TO_DRAG' ) );
			$n = 0;
			$field->html = '<ul id="sortables_'.$field->id.'">';

			foreach ($field->value as $value) {
				$field->html .= '<li>' . JHTML::_('calendar', $value, $field->name.'[]', $field->name.'_'.$n, '%Y-%m-%d', 'class="'.$required.'"') . '<input class="fcbutton" type="button" value="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);" /><span class="drag'.$field->id.'">'.$move2.'</span></li>';
				$n++;
			}
			$field->html .=	'</ul>';
			$field->html .= '<input type="button" id="add'.$field->name.'" onclick="addField'.$field->id.'(this);" value="'.JText::_( 'FLEXI_ADD_VALUE' ).'" />';

		} else { // handle single records
			$field->html	= '<div>' . JHTML::_('calendar', $field->value[0], $field->name.'[]', $field->name, '%Y-%m-%d', 'class="'.$required.'"') .'</div>';
		}
	}


	function onDisplayFilter(&$filter, $value='')
	{
		// execute the code only if the field type match the plugin type
		if($filter->field_type != 'date') return;
		
		global $globalcats;
		$db =& JFactory::getDBO();
		$cid = JRequest::getInt('cid', 0);
		$authorid = JRequest::getInt('authorid', 0);
		if (!$cid && !$authorid) {
			$filter->html = "Filter for : $field->label cannot be displayed, both cid and authorid not set<br />";
			return;
		}
		
		// some parameter shortcuts
		$label_filter 		= $filter->parameters->get( 'display_label_filter', 0 ) ;
		if ($label_filter == 2) $text_select = $filter->label; else $text_select = JText::_('All');
		$field->html = '';
		
		if ($authorid) $where[] = 'i.created_by ='.$authorid;
		$where[] = 'fi.field_id ='.$filter->id;
		
		if ($cid) {
			// Retrieve category parameters
			$query = 'SELECT params FROM #__categories WHERE id = ' . $cid;
			$db->setQuery($query);
			$catparams = $db->loadResult();
			$cparams = new JParameter($catparams);
			
			$display_subcats = $cparams->get('display_subcategories_items', 0);
			$_group_cats = array($cid);
			
			// Display items from (current and) immediate sub-categories (1-level)
			if ($display_subcats==1) {
				$db->setQuery('SELECT id FROM #__categories WHERE parent_id='.$cid);
				$results = $db->loadObjectList();
				if(is_array($results))
					foreach($results as $cat)
						$_group_cats[] = $cat->id;
			}
			// Display items from (current and) all sub-categories (any-level)
			if ($display_subcats==2) {
				// descendants also includes current category
				$_group_cats = array_map('trim',explode(",",$globalcats[$cid]->descendants));
			}
			
			$_group_cats = array_unique($_group_cats);
			$_group_cats = "'".implode("','", $_group_cats)."'";
			
			$where[] = ' ci.catid IN ('.$_group_cats.')';
		}
		
		$where = " WHERE " . implode(" AND ", $where);
		
		$query = 'SELECT DISTINCT fi.value as value, fi.value as text'
				.' FROM #__flexicontent_fields_item_relations as fi '
				.' LEFT JOIN #__flexicontent_cats_item_relations AS ci ON fi.item_id=ci.itemid'
				.($authorid  ? ' LEFT JOIN #__content as i ON i.id=ci.itemid' : '')
				.$where
				.' ORDER BY fi.value'
				;
		//echo $query;
		// Make sure there aren't any errors
		$db->setQuery($query);
		$results = $db->loadObjectList();
		if ($db->getErrorNum()) {
			JError::raiseWarning($db->getErrorNum(), $db->getErrorMsg(). "<br /><br />" .$query);
			$filter->html	 = "Filter for : $field->label cannot be displayed, error during db query<br />";
			return;
		}
		
		$options = array();
		$options[] = JHTML::_('select.option', '', '-'.$text_select.'-');
		foreach($results as $result) {
			$options[] = JHTML::_('select.option', $result->value, JText::_($result->text));
		}
		if ($label_filter == 1) $filter->html  .= $filter->label.': ';
		$filter->html	.= JHTML::_('select.genericlist', $options, 'filter_'.$filter->id, 'onchange="document.getElementById(\'adminForm\').submit();"', 'value', 'text', $value);
	}


	function onBeforeSaveField( $field, &$post, &$file )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'date') return;
		if(!$post) return;
		
		$newpost = array();
		$new = 0;
		
		if(!is_array($post)) $post = array ($post);
		foreach ($post as $n=>$v)
		{
			if ($post[$n] != '')
			{
				$newpost[$new] = $post[$n];
			}
			$new++;
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
		$values = $values ? $values : $field->value;

		// some parameter shortcuts
		$customdate			= $field->parameters->get( 'custom_date', '%Y-%m-%d' ) ; 
		$dateformat			= $field->parameters->get( 'date_format', $customdate ) ;
		$separatorf			= $field->parameters->get( 'separatorf', 1 ) ;

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

			default:
			$separatorf = '&nbsp;';
			break;
		}
								
		// initialise property
		$field->{$prop} 	= array();

		$n=0;
		foreach ($values as $value) {
			// We must use timezone offset ZERO, because the date(-time) value is stored in its final value
			// AND NOT as GMT-0 which would need to be converted to localtime, if not specified the JHTML-date
			// will convert to local time using a timezone offset, giving erroneous output
			$field->{$prop}[]	= $values[$n] ? JHTML::_('date', $values[$n], JText::_($dateformat), $timezone_offset=0 ) : JText::_( 'FLEXI_NO_VALUE' );
			$n++;
		}
		$field->{$prop} = implode($separatorf, $field->{$prop});	
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
			//echo "<pre>"; print_r($objs);echo "</pre>"; 
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
		//echo "<pre>"; print_r($resultfields);echo "</pre>"; 
		$field->results = $resultfields;
		//return $resultfields;
	}
}
