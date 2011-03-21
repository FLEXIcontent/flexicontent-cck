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

//jimport('joomla.plugin.plugin');
jimport('joomla.event.plugin');

class plgFlexicontent_fieldsCheckboximage extends JPlugin
{
	function plgFlexicontent_fieldsCheckboximage( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_checkboximage', JPATH_ADMINISTRATOR);
	}

	function onDisplayField(&$field, $item)
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'checkboximage') return;

		global $mainframe;

		// Import the file system library
		jimport('joomla.filesystem.file');

		// some parameter shortcuts
		$field_elements		= $field->parameters->get( 'field_elements' ) ;
		$imagedir			= $field->parameters->get( 'imagedir' ) ;
		$imagedir 			= preg_replace('#^(/)*#', '', $imagedir);
		$separator			= $field->parameters->get( 'separator' ) ;
		$default_values		= $field->parameters->get( 'default_values', '' ) ;
		$required 			= $field->parameters->get( 'required', 0 ) ;
		$required 	= $required ? ' required' : '';

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

		$i = 1;
		$options = "";
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
			
		$field->html 	= $options;
	}


	function onBeforeSaveField( $field, &$post, &$file )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'checkboximage') return;
		if(!$post) return;
		
		if ($field->issearch) {
			// create the fulltext search index
			$searchindex = '';
			
			$field_elements		= $field->parameters->get( 'field_elements', '' ) ;
	
			$listelements = explode("%% ", $field_elements);
			$listarrays = array();
			foreach ($listelements as $listelement) {
				$listarrays[] = explode("::", $listelement);
				}
	
			$i = 0;
			$display = array();
			foreach ($listarrays as $listarray) {
				for($n=0, $c=count($post); $n<$c; $n++) {
					if ($post[$n] == $listarray[0]) {
						$display[] = $listarray[1];
						}
					} 
				$i++;
				}			
				
			$searchindex  = implode(' ', $display);
			$searchindex .= ' | ';
	
			$field->search = $searchindex;
		} else {
			$field->search = '';
		}
	}


	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'checkboximage') return;

		$values = $values ? $values : $field->value;

		global $mainframe;

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
		
		// initialise property
/*
		if(!$values || $values[0] == '') {
			$values = explode(",", $default_values);
			}
*/
		$listelements = explode("%% ", $field_elements);
		$listarrays = array();
		foreach ($listelements as $listelement) {
			$listarrays[] = explode("::", $listelement);
			}

		$i = 1;
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
			$i++;
			}
		$field->{$prop} = implode($separatorf, $display);
	}


	function onDisplayFilter(&$filter, $value='')
	{
		// execute the code only if the field type match the plugin type
		if($filter->field_type != 'checkboximage') return;

		// some parameter shortcuts
		$field_elements		= $filter->parameters->get( 'field_elements' ) ;
		$label_filter 		= $filter->parameters->get( 'display_label_filter', 0 ) ;
		if ($label_filter == 2) $text_select = $filter->label; else $text_select = JText::_('All');
		$field->html = '';
						
		$listelements = explode("%% ", $field_elements);
		$listarrays = array();
		foreach ($listelements as $listelement) {
			$listarrays[] = explode("::", $listelement);
			}

		$options = array(); 
		$options[] = JHTML::_('select.option', '', '-'.$text_select.'-');
		foreach ($listarrays as $listarray) {
			$options[] = JHTML::_('select.option', $listarray[0], $listarray[1]); 
			}			
		if ($label_filter == 1) $filter->html  .= $filter->label.': ';		
		$filter->html	.= JHTML::_('select.genericlist', $options, 'filter_'.$filter->id, 'onchange="document.getElementById(\'adminForm\').submit();"', 'value', 'text', $value);
	}
}
