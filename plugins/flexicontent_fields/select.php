<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.select
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

class plgFlexicontent_fieldsSelect extends JPlugin
{
	function plgFlexicontent_fieldsSelect( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_select', JPATH_ADMINISTRATOR);
	}
	
	
	function onAdvSearchDisplayField(&$field, &$item)
	{
		if($field->field_type != 'select') return;
		plgFlexicontent_fieldsSelect::onDisplayField($field, $item);
	}
	
	
	function onDisplayField(&$field, &$item)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'select') return;
		
		$field->label = JText::_($field->label);

		// some parameter shortcuts
		$sql_mode				= $field->parameters->get( 'sql_mode', 0 ) ;
		$field_elements	= $field->parameters->get( 'field_elements' ) ;
		$default_value	= $field->parameters->get( 'default_value' ) ;
		
		$firstoptiontext	= $field->parameters->get( 'firstoptiontext', 'Please Select' ) ;
		$usefirstoption		= $field->parameters->get( 'usefirstoption', 1 ) ;
		
		$required 	= $field->parameters->get( 'required', 0 ) ;
		$required 	= $required ? ' required' : '';

		// initialise property
		if($item->version < 2 && $default_value) {
			$field->value = array();
			$field->value[0] = $default_value;
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
			$results = $db->loadObjectList();

			if (!$query || !is_array($results)) {
				$field->html = JText::_('FLEXI_FIELD_INVALID_QUERY');
			} else {
			
				$options = array();
				if ($usefirstoption) $options[] = JHTML::_('select.option', '', JText::_($firstoptiontext));
				foreach($results as $result) {
					$options[] = JHTML::_('select.option', $result->value, JText::_($result->text));
				}
				$field->html	= JHTML::_('select.genericlist', $options, $field->name, 'class="'.$required.'"', 'value', 'text', $field->value);
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
				$options[] = JHTML::_('select.option', $listarray[0], JText::_($listarray[1])); 
			}
			$field->html	= JHTML::_('select.genericlist', $options, $field->name, 'class="'.$required.'"', 'value', 'text', $field->value);
		}
	}


	function onBeforeSaveField( $field, &$post, &$file )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'select') return;
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
				
				foreach($results as $result) {
					if ($result->value == $post) {
						$searchindex	= $pretext . JText::_($result->text) . $posttext;
					}
				}
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

			foreach ($listarrays as $listarray) {
				if ($post == $listarray[0]) {
					$searchindex = $listarray[1];
				} 
			}
			$advsearchindex_values[] = $searchindex;
			$searchindex .= ' | ';
			$field->search = $field->issearch ? $searchindex : '';
		}
		
		if($field->isadvsearch && JRequest::getVar('vstate', 0)==2) {
			plgFlexicontent_fieldsSelect::onIndexAdvSearch($field, $advsearchindex_values);
		}
	}
	
	
	function onIndexAdvSearch(&$field, $post) {
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'select') return;
		$db = &JFactory::getDBO();
		$post = is_array($post)?$post:array($post);
		$query = "DELETE FROM #__flexicontent_advsearch_index WHERE field_id='{$field->id}' AND item_id='{$field->item_id}' AND extratable='select';";
		$db->setQuery($query);
		$db->query();
		$i = 0;
		foreach($post as $v) {
			$query = "INSERT INTO #__flexicontent_advsearch_index VALUES('{$field->id}','{$field->item_id}','select','{$i}', ".$db->Quote($v).");";
			$db->setQuery($query);
			$db->query();
			$i++;
		}
		return true;
	}


	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'select') return;

		$field->label = JText::_($field->label);
		
		$values = $values ? $values : $field->value;

		// some parameter shortcuts
		$remove_space		= $field->parameters->get( 'remove_space', 0 ) ;
		$pretext			= $field->parameters->get( 'pretext', '' ) ;
		$posttext			= $field->parameters->get( 'posttext', '' ) ;
		$field_elements		= $field->parameters->get( 'field_elements', '' ) ;
		$sql_mode			= $field->parameters->get( 'sql_mode', 0 ) ;
		$text_or_value		= $field->parameters->get( 'text_or_value', 1 ) ;
						
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

				if ($values) {
					foreach($results as $result) {
						if ($result->value == $values[0]) {
							$field->{$prop}	= $pretext . JText::_($text_or_value ? $result->text : $result->value) . $posttext;
						}
					}
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

			$display = "";
			if ($values) {
				foreach ($listarrays as $listarray) {
					if ($values[0] == $listarray[0]) {
						$display = $pretext . JText::_($text_or_value ? $listarray[1] : $listarray[0]) . $posttext;
					}
				}			
			}
			$field->{$prop}	= $display ? $display : '';
		}
	}


	function onDisplayFilter(&$filter, $value='')
	{
		// execute the code only if the field type match the plugin type
		if($filter->field_type != 'select') return;

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
		if($field->field_type!='select') return;
		$db = &JFactory::getDBO();
		$resultfields = array();
		foreach($fieldsearch as $fsearch) {
			$query = "SELECT ai.search_index, ai.item_id FROM #__flexicontent_advsearch_index as ai"
				." WHERE ai.field_id='{$field->id}' AND ai.extratable='select' AND ai.search_index like '%{$fsearch}%';";
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
