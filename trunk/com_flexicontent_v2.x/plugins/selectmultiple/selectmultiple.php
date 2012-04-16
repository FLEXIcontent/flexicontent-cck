<?php
/**
 * @version 1.0 $Id: selectmultiple.php 1059 2011-12-20 07:18:32Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.selectmultiple
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

class plgFlexicontent_fieldsSelectmultiple extends JPlugin
{
	function plgFlexicontent_fieldsSelectmultiple( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_selectmultiple', JPATH_ADMINISTRATOR);
	}
	
	
	function onAdvSearchDisplayField(&$field, &$item)
	{
		if($field->field_type != 'selectmultiple') return;
		plgFlexicontent_fieldsSelectmultiple::onDisplayField($field, $item);
	}
	
	
	function onDisplayField(&$field, &$item)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'selectmultiple') return;
		
		$field->label = JText::_($field->label);

		// some parameter shortcuts
		$sql_mode				= $field->parameters->get( 'sql_mode', 0 ) ;
		$field_elements	= $field->parameters->get( 'field_elements' ) ;
		$default_values	= $field->parameters->get( 'default_values', '' ) ;
		
		$firstoptiontext	= $field->parameters->get( 'firstoptiontext', 'Please Select' ) ;
		$usefirstoption		= $field->parameters->get( 'usefirstoption', 1 ) ;
		
		$required 	= $field->parameters->get( 'required', 0 ) ;
		$required 	= $required ? ' required' : '';
		$size		= $field->parameters->get( 'size', 6 ) ;
		$size	 	= $size ? ' size="'.$size.'"' : '';

		// initialise property
		if($item->getValue('version', NULL, 0) < 2 && $default_values) {
			$field->value = explode(",", $default_values);
		} else if (!$field->value) {
			$field->value = array();
			$field->value[0] = '';
		}

		if ($sql_mode) { // SQL mode
			
			$db =& JFactory::getDBO();
			$jAp=& JFactory::getApplication();
			
			$query = preg_match('#^select#i', $field_elements) ? $field_elements : '';
			preg_match_all("/{item->[^}]+}/", $query, $matches);
			foreach ($matches[0] as $replacement_tag) {
				$replacement_value = '$'.substr($replacement_tag, 1, -1);
				eval ("\$replacement_value = \" $replacement_value\";");
				$query = str_replace($replacement_tag, $replacement_value, $query);
			}
			
			$db->setQuery($query);
			$options = $db->loadObjectList();
			if ($usefirstoption) {
				$first_option = new stdClass();  $first_option->value = '';  $first_option->text = JText::_($firstoptiontext);
				array_unshift($options, $first_option);
			}

			if (!$query || !is_array($options)) {
				$field->html = JText::_('FLEXI_FIELD_INVALID_QUERY');
			} else {
				$field->html	= JHTML::_('select.genericlist', $options, 'custom['.$field->name.'][]', 'multiple="multiple" class="'.$required.'"'.$size, 'value', 'text', $field->value);
			}

		} else { // Elements mode
		
			$listelements = preg_split("/[\s]*%%[\s]*/", $field_elements);
			if (empty($listelements[count($listelements)-1])) {
				unset($listelements[count($listelements)-1]);
			}

			$listarrays = array();
			foreach ($listelements as $listelement) {
				$listarrays[] = explode("::", $listelement);
			}

			$options = array(); 
			if($usefirstoption) $options[] = JHTML::_('select.option', '', JText::_($firstoptiontext));
			foreach ($listarrays as $listarray) {
				$options[] = JHTML::_('select.option', $listarray[0], $listarray[1]); 
			}
			$field->html	= JHTML::_('select.genericlist', $options, 'custom['.$field->name.'][]', 'multiple="multiple" class="'.$required.'"'.$size, 'value', 'text', $field->value);
		}
	}


	function onBeforeSaveField( $field, &$post, &$file, &$item )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'selectmultiple') return;
		if(!$post) return;
		
		// create the fulltext search index
		$searchindex = '';
		
		$pretext			= $field->parameters->get( 'pretext', '' ) ;
		$posttext			= $field->parameters->get( 'posttext', '' ) ;
		$field_elements		= $field->parameters->get( 'field_elements', '' ) ;
		$sql_mode			= $field->parameters->get( 'sql_mode', 0 ) ;
		$remove_space		= $field->parameters->get( 'remove_space', 0 ) ;

		if($pretext) 	{ $pretext 	= $remove_space ? '' : $pretext . ' '; }
		if($posttext) 	{ $posttext	= $remove_space ? '' : ' ' . $posttext; }

		$advsearchindex_values = array();
		if ($sql_mode) { // SQL mode
			
			$db =& JFactory::getDBO();
			$jAp=& JFactory::getApplication();
			
			$query = preg_match('#^select#i', $field_elements) ? $field_elements : '';
			preg_match_all("/{item->[^}]+}/", $query, $matches);
			foreach ($matches[0] as $replacement_tag) {
				$replacement_value = '$'.substr($replacement_tag, 1, -1);
				eval ("\$replacement_value = \" $replacement_value\";");
				$query = str_replace($replacement_tag, $replacement_value, $query);
			}
			
			$db->setQuery($query);
			$results = $db->loadObjectList();
			
			$advsearchindex_values = array();
			
			if ($results) {
				
				$display = array();
				foreach($results as $result) {
					for($n=0, $c=count($post); $n<$c; $n++) {
						if ($result->value == $post[$n]) {
							$display[] = $pretext . $result->text . $posttext;
						}
					}
				}
				$searchindex  = implode(' ', $display);
				$advsearchindex_values[] = $searchindex;
				$searchindex .= ' | ';

				$field->search = $field->issearch ? $searchindex : '';
				
			}

		} else { // Elements mode

			$listelements = preg_split("/[\s]*%%[\s]*/", $field_elements);
			if (empty($listelements[count($listelements)-1])) {
				unset($listelements[count($listelements)-1]);
			}

			$listarrays = array();
		
			foreach ($listelements as $listelement) {
				$listarrays[] = explode("::", $listelement);
			}

			$display = array();
			foreach ($listarrays as $listarray) {
				for($n=0, $c=count($post); $n<$c; $n++) {
					if ($post[$n] == $listarray[0]) {
						$display[] = $pretext . $listarray[1] . $posttext;
					}
				} 
			}
			$searchindex  = implode(' ', $display);
			$advsearchindex_values[] = $searchindex;
			$searchindex .= ' | ';
			$field->search = $field->issearch ? $searchindex : '';
		}
		
		$data	= JRequest::getVar('jform', array(), 'post', 'array');
		if($field->isadvsearch && $data['vstate']==2) {
			plgFlexicontent_fieldsSelectmultiple::onIndexAdvSearch($field, $advsearchindex_values);
		}
	}
	
	
	function onIndexAdvSearch(&$field, $post) {
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'selectmultiple') return;
		$db = &JFactory::getDBO();
		$post = is_array($post)?$post:array($post);
		$query = "DELETE FROM #__flexicontent_advsearch_index WHERE field_id='{$field->id}' AND item_id='{$field->item_id}' AND extratable='selectmultiple';";
		$db->setQuery($query);
		$db->query();
		$i = 0;
		foreach($post as $v) {
			$query = "INSERT INTO #__flexicontent_advsearch_index VALUES('{$field->id}','{$field->item_id}','selectmultiple','{$i}', ".$db->Quote($v).");";
			$db->setQuery($query);
			$db->query();
			$i++;
		}
		return true;
	}


	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'selectmultiple') return;

		$field->label = JText::_($field->label);
		
		$values = $values ? $values : $field->value;

		// some parameter shortcuts
		$remove_space		= $field->parameters->get( 'remove_space', 0 ) ;
		$pretext			= $field->parameters->get( 'pretext', '' ) ;
		$posttext			= $field->parameters->get( 'posttext', '' ) ;
		$field_elements		= $field->parameters->get( 'field_elements', '' ) ;
		$sql_mode			= $field->parameters->get( 'sql_mode', 0 ) ;
		$separatorf			= $field->parameters->get( 'separatorf' ) ;
		$opentag			= $field->parameters->get( 'opentag', '' ) ;
		$closetag			= $field->parameters->get( 'closetag', '' ) ;
		$text_or_value		= $field->parameters->get( 'text_or_value', 1 ) ;
						
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
		
		if($pretext) 	{ $pretext 	= $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) 	{ $posttext	= $remove_space ? $posttext : ' ' . $posttext; }

		if ($sql_mode) { // SQL mode
			
			$db =& JFactory::getDBO();
			$jAp=& JFactory::getApplication();
			
			$query = preg_match('#^select#i', $field_elements) ? $field_elements : '';
			preg_match_all("/{item->[^}]+}/", $query, $matches);
			foreach ($matches[0] as $replacement_tag) {
				$replacement_value = '$'.substr($replacement_tag, 1, -1);
				eval ("\$replacement_value = \" $replacement_value\";");
				$query = str_replace($replacement_tag, $replacement_value, $query);
			}
			
			$db->setQuery($query);
			$results = $db->loadObjectList();
			
			if (!$results) {
				$field->{$prop} = '';
			
			} else {

				$display = array();
				foreach($results as $result) {
					for($n=0, $c=count($values); $n<$c; $n++) {
						if ($result->value == $values[$n]) {
							$display[] = $pretext . JText::_($text_or_value ? $result->text : $result->value) . $posttext;
						}
					}
				}
				if ($values) {
					$field->{$prop} = implode($separatorf, $display);
					$field->{$prop} = $opentag . $field->{$prop} . $closetag;
				} else {
					$field->{$prop} = '';
				}
			}

		} else { // Elements mode

			// initialise property
			$listelements = preg_split("/[\s]*%%[\s]*/", $field_elements);
			if (empty($listelements[count($listelements)-1])) {
				unset($listelements[count($listelements)-1]);
			}

			$listarrays = array();

			foreach ($listelements as $listelement) {
				$listarrays[] = explode("::", $listelement);
			}

			$display = array();
			foreach ($listarrays as $listarray) {
				for($n=0, $c=count($values); $n<$c; $n++) {
					if ($values[$n] == $listarray[0]) {
						$display[] = JText::_($text_or_value ? $listarray[1] : $listarray[0]);
					}
				}
			}			
			if ($values) {
				$field->{$prop} = implode($separatorf, $display);
				$field->{$prop} = $opentag . $field->{$prop} . $closetag;
			} else {
				$field->{$prop} = '';
			}
		}
	}


	function onDisplayFilter(&$filter, $value='')
	{
		// execute the code only if the field type match the plugin type
		if($filter->field_type != 'selectmultiple') return;

		// ** some parameter shortcuts
		$field_elements		= $filter->parameters->get( 'field_elements' ) ;
		$sql_mode			= $filter->parameters->get( 'sql_mode', 0 ) ;
		$label_filter 		= $filter->parameters->get( 'display_label_filter', 0 ) ;
		if ($label_filter == 2) $text_select = $filter->label; else $text_select = JText::_('All');
		$field->html = '';
		
		
		// *** Retrieve values
		if ($sql_mode) {  // CASE 1: SQL mode
			
			$db =& JFactory::getDBO();
			$jAp=& JFactory::getApplication();
			
			// !! CHECK: The field depends on item data so it cannot be used as filter in category
			$query = preg_match('#^select#i', $field_elements) ? $field_elements : '';
			preg_match_all("/{item->[^}]+}/", $query, $matches);
			if (count($matches[0])) {
				$filter->html = sprintf( JText::_('FLEXI_WARNING_ITEM_SPECIFIC_AS_CATEGORY_FILTER'), $filter->label );
				return;
			}
			
			// Execute SQL query to retrieve the field value - label pair
			$db->setQuery($query);
			$results = $db->loadObjectList('value');
			
			// !! CHECK: DB query had an error, set a message to warn the user
			if ($db->getErrorNum()) {
				JError::raiseWarning($db->getErrorNum(), $db->getErrorMsg(). "<br />".$query."<br />");
				$filter->html	 = "<br />Filter for : $field->label cannot be displayed, error during db query, please correct field configuration<br />";
				return;
			}
			
			// !! CHECK: DB query produced no data, do not create the filter
			if (!$results) {
				$field->html = '';
				return;
			}

		} else { // CASE 2: Elements mode

			$listelements = preg_split("/[\s]*%%[\s]*/", $field_elements);
			if (empty($listelements[count($listelements)-1])) {
				unset($listelements[count($listelements)-1]);
			}

			$listarrays = array();
			foreach ($listelements as $listelement) {
				list($val, $label) = explode("::", $listelement);
				$results[$val] = new stdClass();
				$results[$val]->value = $val;
				$results[$val]->text = $label;
			}
			
		}
		
		
		// *** Limit values, show only allowed values according to category configuration parameter 'limit_filter_values'
		$results = array_intersect_key($results, flexicontent_cats::getFilterValues($filter));
		
		
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
		if($field->field_type!='selectmultiple') return;
		$db = &JFactory::getDBO();
		$resultfields = array();
		foreach($fieldsearch as $fsearch) {
			$query = "SELECT ai.search_index, ai.item_id FROM #__flexicontent_advsearch_index as ai"
				." WHERE ai.field_id='{$field->id}' AND ai.extratable='selectmultiple' AND ai.search_index like '%{$fsearch}%';";
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
