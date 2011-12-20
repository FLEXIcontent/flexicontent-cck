<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.checkboximage
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

class plgFlexicontent_fieldsCheckboximage extends JPlugin
{
	function plgFlexicontent_fieldsCheckboximage( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_checkboximage', JPATH_ADMINISTRATOR);
	}
	
	
	function onAdvSearchDisplayField(&$field, &$item)
	{
		if($field->field_type != 'checkboximage') return;
		plgFlexicontent_fieldsCheckboximage::onDisplayField($field, $item);
	}
	
	
	function onDisplayField(&$field, &$item)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'checkboximage') return;
		
		$field->label = JText::_($field->label);

		$mainframe =& JFactory::getApplication();

		// Import the file system library
		jimport('joomla.filesystem.file');

		// some parameter shortcuts
		$field_elements		= $field->parameters->get( 'field_elements' ) ;
		$imagedir			= $field->parameters->get( 'imagedir' ) ;
		$imagedir 			= preg_replace('#^(/)*#', '', $imagedir);
		$separator			= $field->parameters->get( 'separator' ) ;
		$default_values		= $field->parameters->get( 'default_values', '' ) ;
						
		$required 			= $field->parameters->get( 'required', 0 ) ;
		$required 	= $required ? ' required validate-checkbox' : '';

		switch($separator)
		{
			case 0:
			$separator = '&nbsp;';
			break;

			case 1:
			$separator = '<br />';
			break;

			case 2:
			$separator = '&nbsp;|&nbsp;';
			break;

			case 3:
			$separator = ',&nbsp;';
			break;

			default:
			$separator = '&nbsp;';
			break;
		}

		// initialise property
		if($item->version < 2 && $default_values) {
			$field->value = explode(",", $default_values);
		} else if (!$field->value) {
			$field->value = array();
			$field->value[0] = '';
		}

		$listelements = explode("%% ", $field_elements);
		$listarrays = array();
		foreach ($listelements as $listelement) {
			$listarrays[] = explode("::", $listelement);
		}

		$i = 0;
		$options  = "";
		foreach ($listarrays as $listarray) {
			// get the image src
			$prefix = $mainframe->isAdmin() ? '../':'';
			$imgsrc =  $prefix . $imagedir . $listarray[2] ;
			$checked  = "";
			for($n=0, $c=count($field->value); $n<$c; $n++) {
				if ($field->value[$n] == $listarray[0]) {
					$checked = ' checked="checked"';
				}
			}
			$img = '<img src="'.$imgsrc.'" alt="'.$listarray[1].'" />';
			$options .= '<label class="hasTip" title="'.$field->label.'::'.JText::_($listarray[1]).'"><input type="checkbox" name="'.$field->name.'[]" class="'.$required.'" value="'.$listarray[0].'" id="'.$field->name.'_'.$i.'"'.$checked.' />'.$img.'</label>'.$separator;			 
			$i++;
		}
		$field->html	= $options;
	}


	function onBeforeSaveField( $field, &$post, &$file )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'checkboximage') return;
		if(!$post) return;
		
		// create the fulltext search index
		if ($field->issearch) {
			$searchindex = '';
			
			$field_elements		= $field->parameters->get( 'field_elements', '' ) ;
	
			$listelements = explode("%% ", $field_elements);
			$listarrays = array();
			foreach ($listelements as $listelement) {
				$listarrays[] = explode("::", $listelement);
				}
	
			$display = array();
			foreach ($listarrays as $listarray) {
				for($n=0, $c=count($post); $n<$c; $n++) {
					if ($post[$n] == $listarray[0]) {
						$display[] = $listarray[1];
						}
					} 
				}			
				
			$searchindex  = implode(' ', $display);
			$advsearchindex_values[] = $searchindex;
			$searchindex .= ' | ';
	
			$field->search = $searchindex;
		} else {
			$field->search = '';
		}
		
		if($field->isadvsearch && JRequest::getVar('vstate', 0)==2) {
			plgFlexicontent_fieldsCheckboximage::onIndexAdvSearch($field, $advsearchindex_values);
		}
	}
	
	
	function onIndexAdvSearch(&$field, $post) {
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'checkboximage') return;
		$db = &JFactory::getDBO();
		$post = is_array($post)?$post:array($post);
		$query = "DELETE FROM #__flexicontent_advsearch_index WHERE field_id='{$field->id}' AND item_id='{$field->item_id}' AND extratable='checkboximage';";
		$db->setQuery($query);
		$db->query();
		$i = 0;
		foreach($post as $v) {
			$query = "INSERT INTO #__flexicontent_advsearch_index VALUES('{$field->id}','{$field->item_id}','checkboximage','{$i}', ".$db->Quote($v).");";
			$db->setQuery($query);
			$db->query();
			$i++;
		}
		return true;
	}


	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'checkboximage') return;

		$field->label = JText::_($field->label);
		
		$values = $values ? $values : $field->value;

		$mainframe =& JFactory::getApplication();

		// some parameter shortcuts
		$field_elements		= $field->parameters->get( 'field_elements' ) ;
		$imagedir			= $field->parameters->get( 'imagedir' ) ;
		$separatorf			= $field->parameters->get( 'separatorf' ) ;
		$default_values		= $field->parameters->get( 'default_values', '' ) ;
						
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

		$listelements = explode("%% ", $field_elements);
		$listarrays = array();
		foreach ($listelements as $listelement) {
			$listarrays[] = explode("::", $listelement);
			}

		$display = array();
		foreach ($listarrays as $listarray) {
			// get the image src
			$prefix = $mainframe->isAdmin() ? '../':'';
			$imgsrc =  $prefix . $imagedir . $listarray[2] ;
			for($n=0, $c=count($values); $n<$c; $n++) {
				if ($values[$n] == $listarray[0]) {
					$display[] = '<img src="'.$imgsrc.'" class="hasTip" title="'.$field->label.'::'.$listarray[1].'" alt="'.$listarray[1].'" />';
					}
				} 
			$img = '<img src="'.$imgsrc.'" alt="'.$listarray[1].'" />';
			}
			
		$field->{$prop} = implode($separatorf, $display);
	}


	function onDisplayFilter(&$filter, $value='')
	{
		// execute the code only if the field type match the plugin type
		if($filter->field_type != 'checkboximage') return;

		// ** some parameter shortcuts
		$field_elements		= $filter->parameters->get( 'field_elements' ) ;
		$label_filter 		= $filter->parameters->get( 'display_label_filter', 0 ) ;
		if ($label_filter == 2) $text_select = $filter->label; else $text_select = JText::_('All');
		$field->html = '';
		
		
		// *** Retrieve values
		$listelements = explode("%% ", $field_elements);
		$listarrays = array();
		foreach ($listelements as $listelement) {
			list($val, $label, $image) = explode("::", $listelement);
			$results[$val] = new stdClass();
			$results[$val]->value = $val;
			$results[$val]->text = $label;
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
		if($field->field_type!='checkboximage') return;
		$db = &JFactory::getDBO();
		$resultfields = array();
		foreach($fieldsearch as $fsearch) {
			$query = "SELECT ai.search_index, ai.item_id FROM #__flexicontent_advsearch_index as ai"
				." WHERE ai.field_id='{$field->id}' AND ai.extratable='checkboximage' AND ai.search_index like '%{$fsearch}%';";
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
