<?php
/**
 * @version 1.0 $Id: radioimage.php 890 2011-09-02 15:21:59Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.radioimage
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

class plgFlexicontent_fieldsRadioimage extends JPlugin
{
	function plgFlexicontent_fieldsRadioimage( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_radioimage', JPATH_ADMINISTRATOR);
	}
	
	function onAdvSearchDisplayField(&$field, &$item) {
		plgFlexicontent_fieldsRadioimage::onDisplayField($field, $item);
	}

	function onDisplayField(&$field, &$item)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'radioimage') return;
		
		$field->label = JText::_($field->label);
		$mainframe =& JFactory::getApplication();

		// Import the file system library
		jimport('joomla.filesystem.file');

		// some parameter shortcuts
		$field_elements		= $field->parameters->get( 'field_elements' ) ;
		$imagedir			= $field->parameters->get( 'imagedir' ) ;
		$imagedir 			= preg_replace('#^(/)*#', '', $imagedir);
		$separator			= $field->parameters->get( 'separator' ) ;
		$default_value		= $field->parameters->get( 'default_value' ) ;
								
		$required 			= $field->parameters->get( 'required', 0 ) ;
		$required 	= $required ? ' required validate-radio' : '';

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
		if ($item->getValue('version', NULL, 0) < 2 && $default_value) {
			$field->value = array();
			$field->value[0] = $default_value;
		} elseif ( !isset($field->value[0]) ) {
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
			if ($listarray[0] == $field->value[0]) {
				$checked = ' checked="checked"';
				}
			$img = '<img src="'.$imgsrc.'" alt="'.$listarray[1].'" />';
			$options .= '<label class="hasTip" title="'.$field->label.'::'.$listarray[1].'"><input type="radio" class="'.$required.'" name="custom['.$field->name.']" value="'.$listarray[0].'" id="'.$field->name.'_'.$i.'"'.$checked.' />'.$img.'</label>'.$separator;			 
			$i++;
		}
			
		$field->html 	= $options;
	}


	function onBeforeSaveField( $field, &$post, &$file )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'radioimage') return;
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
	
			foreach ($listarrays as $listarray) {
				if ($post == $listarray[0]) {
					$searchindex = $listarray[1];
				} 
			}
				
			$searchindex .= ' | ';
	
			$field->search = $searchindex;
		} else {
			$field->search = '';
		}
	}


	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'radioimage') return;
		$field->label = JText::_($field->label);
		
		$mainframe =& JFactory::getApplication();

		$values = $values ? $values : $field->value ;

		// Import the file system library
		jimport('joomla.filesystem.file');

		// some parameter shortcuts
		$field_elements		= $field->parameters->get( 'field_elements' ) ;
		$imagedir			= $field->parameters->get( 'imagedir' ) ;
		$imagedir 			= preg_replace('#^(/)*#', '', $imagedir);

		$listelements = explode("%% ", $field_elements);
		$listarrays = array();
		foreach ($listelements as $listelement) {
			$listarrays[] = explode("::", $listelement);
			}

		$i = 1;
		$options = '';
		$display = null;
		if ($values)
		{
		foreach ($listarrays as $listarray) {
			// get the image src
			$prefix = $mainframe->isAdmin() ? '../':'';
			$imgsrc =  $prefix . $imagedir . $listarray[2] ;

			if ($listarray[0] == $values[0]) {
				$display = '<img src="'.$imgsrc.'" class="hasTip" title="'.$field->label.'::'.$listarray[1].'" alt="'.$listarray[1].'" />';
				}
			$img = '<img src="'.$imgsrc.'" alt="'.$listarray[1].'" />';
			$i++;
			}			
		}	
		$field->{$prop}	= $display ? $display : '';
	}


	function onDisplayFilter(&$filter, $value='')
	{
		// execute the code only if the field type match the plugin type
		if($filter->field_type != 'radioimage') return;

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
