<?php
/**
 * @version 1.0 $Id: selectmultiple.php 175 2009-11-07 10:24:30Z vistamedia $
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

//jimport('joomla.plugin.plugin');
jimport('joomla.event.plugin');

class plgFlexicontent_fieldsSelectmultiple extends JPlugin
{
	function plgFlexicontent_fieldsSelectmultiple( &$subject, $params )
	{
		parent::__construct( $subject, $params );
        JPlugin::loadLanguage('plg_flexicontent_fields_selectmultiple', JPATH_ADMINISTRATOR);
	}

	function onDisplayField(&$field, $item)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'selectmultiple') return;

		// some parameter shortcuts
		$required 			= $field->parameters->get( 'required', 0 ) ;
		$sql_mode			= $field->parameters->get( 'sql_mode', 0 ) ;
		$field_elements		= $field->parameters->get( 'field_elements' ) ;
		$size				= $field->parameters->get( 'size', 6 ) ;
		$default_values		= $field->parameters->get( 'default_values', '' ) ;
						
		$required 	= $required ? ' class="required"' : '';
		$size	 	= $size ? ' size="'.$size.'"' : '';
		
		// initialise property
		if($item->version == 1 && $default_values) {
			$field->value = explode(",", $default_values);
		}

		if ($sql_mode) { // SQL mode
			
			$db =& JFactory::getDBO();
		
			$query = preg_match('#^select#i', $field_elements) ? $field_elements : '';
			$db->setQuery($query);
			$options = $db->loadObjectList();
			
			if (!$options) {
				$field->html = JText::_('FLEXI_FIELD_INVALID_QUERY');
			
			} else {
			
				$field->html	= JHTML::_('select.genericlist', $options, $field->name.'[]', 'multiple="multiple"'.$required.$size, 'value', 'text', $field->value);
			}

		} else { // Elements mode
		
			$listelements = explode("%% ", $field_elements);
			$listarrays = array();
			foreach ($listelements as $listelement) {
				$listarrays[] = explode("::", $listelement);
			}

			$options = array(); 
			$display = array();
			foreach ($listarrays as $listarray) {
				$options[] = JHTML::_('select.option', $listarray[0], $listarray[1]); 
				for($n=0, $c=count($field->value); $n<$c; $n++) {
					if ($field->value[$n] == $listarray[0]) {
						$display[] = $listarray[1];
					}
				}
			}			
			
		$field->html	= JHTML::_('select.genericlist', $options, $field->name.'[]', 'multiple="multiple"'.$required.$size, 'value', 'text', $field->value);
		
		}
	}


	function onBeforeSaveField( $field, &$post, &$file )
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

		if ($sql_mode) { // SQL mode
			
			$db =& JFactory::getDBO();
		
			$query = preg_match('#^select#i', $field_elements) ? $field_elements : '';
			$db->setQuery($query);
			$results = $db->loadObjectList();
			
			if (!$results) {
				$display = '';
			
			} else {

				$display = array();
				foreach($results as $result) {
					for($n=0, $c=count($post); $n<$c; $n++) {
						if ($result->value == $post[$n]) {
							$display[] = $pretext . $result->text . $posttext;
						}
					}
				}
				$searchindex	 = implode(' ', $display);
				$searchindex	.= ' | ';
				$field->search = $field->issearch ? $searchindex : '';
			}

		} else { // Elements mode

			$listelements = explode("%% ", $field_elements);
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
			$searchindex	 = implode(' ', $display);
			$searchindex	.= ' | ';
			$field->search = $field->issearch ? $searchindex : '';
		}			
	}


	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'selectmultiple') return;
		
		$values = $values ? $values : $field->value ;

		// some parameter shortcuts
		$remove_space		= $field->parameters->get( 'remove_space', 0 ) ;
		$pretext			= $field->parameters->get( 'pretext', '' ) ;
		$posttext			= $field->parameters->get( 'posttext', '' ) ;
		$field_elements		= $field->parameters->get( 'field_elements', '' ) ;
		$sql_mode			= $field->parameters->get( 'sql_mode', 0 ) ;
		$separatorf			= $field->parameters->get( 'separatorf' ) ;
		$opentag			= $field->parameters->get( 'opentag', '' ) ;
		$closetag			= $field->parameters->get( 'closetag', '' ) ;
						
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
		
			$query = preg_match('#^select#i', $field_elements) ? $field_elements : '';
			$db->setQuery($query);
			$results = $db->loadObjectList();
			
			if (!$results) {
				$field->{$prop} = '';
			
			} else {

				$display = array();
				foreach($results as $result) {
					for($n=0, $c=count($values); $n<$c; $n++) {
						if ($result->value == $values[$n]) {
							$display[] = $pretext . $result->text . $posttext;
						}
					}
				}
			$field->{$prop} = implode($separatorf, $display);
			$field->{$prop} = $opentag . $field->{$prop} . $closetag;
			}

		} else { // Elements mode

			// initialise property
			$listelements = explode("%% ", $field_elements);
			$listarrays = array();
			foreach ($listelements as $listelement) {
				$listarrays[] = explode("::", $listelement);
			}

			$display = array();
			foreach ($listarrays as $listarray) {
				for($n=0, $c=count($values); $n<$c; $n++) {
					if ($values[$n] == $listarray[0]) {
						$display[] = $listarray[1];
					}
				}
			}			
		$field->{$prop} = implode($separatorf, $display);
		$field->{$prop} = $opentag . $field->{$prop} . $closetag;
		}
	}


	function onDisplayFilter(&$filter, $value='')
	{
		// execute the code only if the field type match the plugin type
		if($filter->field_type != 'selectmultiple') return;

		// some parameter shortcuts
		$field_elements		= $filter->parameters->get( 'field_elements' ) ;
		$sql_mode			= $filter->parameters->get( 'sql_mode', 0 ) ;
						
		if ($sql_mode) { // SQL mode
			
			$db =& JFactory::getDBO();
		
			$query = preg_match('#^select#i', $field_elements) ? $field_elements : '';
			$db->setQuery($query);
			$results = $db->loadObjectList();
			
			if (!$results) {
				$field->html = '';
			
			} else {
			
				$options = array();
				$options[] = JHTML::_('select.option', '', '-'.JText::_('All').'-');
				foreach($results as $result) {
					$options[] = JHTML::_('select.option', $result->value, $result->text);
				}
				$filter->html	= JHTML::_('select.genericlist', $options, 'filter_'.$filter->id, 'onchange="document.getElementById(\'adminForm\').submit();"', 'value', 'text', $value);
			}

		} else { // Elements mode

			$listelements = explode("%% ", $field_elements);
			$listarrays = array();
			foreach ($listelements as $listelement) {
				$listarrays[] = explode("::", $listelement);
			}

			$options = array(); 
			$options[] = JHTML::_('select.option', '', '-'.JText::_('All').'-');
			foreach ($listarrays as $listarray) {
				$options[] = JHTML::_('select.option', $listarray[0], $listarray[1]); 
			}			
			
			$filter->html	= JHTML::_('select.genericlist', $options, 'filter_'.$filter->id, 'onchange="document.getElementById(\'adminForm\').submit();"', 'value', 'text', $value);
		}
	}
}