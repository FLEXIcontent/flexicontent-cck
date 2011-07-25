<?php
/**
 * @version 1.0 $Id: radio.php 341 2010-06-27 09:14:47Z emmanuel.danan $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.radio
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

class plgFlexicontent_fieldsRadio extends JPlugin
{
	function plgFlexicontent_fieldsRadio( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_radio', JPATH_ADMINISTRATOR);
	}

	function onDisplayField(&$field, $item)
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'radio') return;

		// some parameter shortcuts
		$required 			= $field->parameters->get( 'required', 0 ) ;
		$field_elements		= $field->parameters->get( 'field_elements' ) ;
		$separator			= $field->parameters->get( 'separator' ) ;
		$default_value		= $field->parameters->get( 'default_value', '' ) ;
						
		$required 	= $required ? ' class="required"' : '';

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
		if($item->version < 2 && $default_value) {
			$field->value = array();
			$field->value[] = $default_value;
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
		$options  = "";
		foreach ($listarrays as $listarray) {
			$checked  = "";
			if ($field->value[0] && $field->value[0] == $listarray[0]) {
				$checked = ' checked="checked"';
				} 
			$options .= '<label><input type="radio" name="'.$field->name.'" value="'.$listarray[0].'" id="'.$field->name.'_'.$i.'"'.$checked.' />'.JText::_($listarray[1]).'</label>'.$separator;			 
			$i++;
			}			
			
		$field->html	= $options;
	}


	function onBeforeSaveField( $field, &$post, &$file )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'radio') return;
		if(!$post) return;
		
		// create the fulltext search index
		$searchindex = '';
		
		$field_elements		= $field->parameters->get( 'field_elements', '' ) ;

		$listelements = explode("%% ", $field_elements);
		$listarrays = array();
		
		foreach ($listelements as $listelement) {
			$listarrays[] = explode("::", $listelement);
			}

		foreach ($listarrays as $listarray) {
			if ($post == $listarray[0]) {
				$searchindex = $listarray[1];
			} 
		}
			
		$searchindex .= ' | ';

		$field->search = $searchindex;
	}


	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'radio') return;
		
		$values = $values ? $values : $field->value ;

		// some parameter shortcuts
		$field_elements		= $field->parameters->get( 'field_elements' ) ;
		
		// initialise property
		$listarrays = array();

		$listelements = explode("%% ", $field_elements);
		foreach ($listelements as $listelement) {
			$listarrays[] = explode("::", $listelement);
			}

		$i = 1;
		$display = '';
		if ($values) {
			foreach ($listarrays as $listarray) {
				if ($values[0] == $listarray[0]) {
					$display = JText::_($listarray[1]);
					} 
				$i++;
				}
			}			
		$field->{$prop}	= $display ? $display : '';
	}


	function onDisplayFilter(&$filter, $value='')
	{
		// execute the code only if the field type match the plugin type
		if($filter->field_type != 'radio') return;

		// some parameter shortcuts
		$field_elements		= $filter->parameters->get( 'field_elements' ) ;
						
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