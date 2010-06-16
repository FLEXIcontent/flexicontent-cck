<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.list
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

class plgFlexicontent_fieldsLinkslist extends JPlugin
{
	
	/**
	 * Default attributes
	 *
	 * @var array
	 */
	protected $_attribs = array();

	function plgFlexicontent_fieldsLinkslist( &$subject, $params )
	{
		parent::__construct( $subject, $params );
        JPlugin::loadLanguage('plg_flexicontent_fields_linkslist', JPATH_ADMINISTRATOR);
	}

	function onDisplayField(&$field, $item)
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'linkslist') return;

		// some parameter shortcuts
		$required 			= $field->parameters->get( 'required', 0 ) ;
		$field_elements		= $field->parameters->get( 'field_elements' ) ;
		$separator			= $field->parameters->get( 'separator' ) ;
		$default_values		= $field->parameters->get( 'default_values', '' ) ;

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
		if($item->version == 1 && $default_values) {
			$field->value = explode(",", $default_values);
			}

		if(strlen($field_elements) === 0) return $field->html = '<div id="fc-change-error" class="fc-error">Please enter at least one item. Example: <pre style="display:inline-block; margin:0">{"item1":{"name":"Item1"},"item2":{"name":"Item2"}}</pre></div>';
		
		$items = $this->prepare($field_elements);

		$options  = array();
		foreach ($items as $id => $item)
		{
			$checked  = in_array($id, $field->value) ? ' checked="checked"' : null;
			$options[] = '<label><input type="checkbox" name="'.$field->name.'[]" value="'.$id.'" id="'.$field->name.'_'.$i.'"'.$checked.' />'.$id.'</label>';			 
		}			
			
		$field->html = implode($separator, $options);
	}
	
	private function prepare($field_elements)
	{
		$listelements = array_map('trim', explode('::', $field_elements));
		$items = $matches = array();
		foreach($listelements as $listelement)
		{
			preg_match("/\[(.*)\]/i", $listelement, $matches);
			$name = trim(preg_replace("/\[(.*)\]/i", '', $listelement));
			if(isset($matches[1]))
			{
				$attribs	  = array();
				$parts  	  = explode('"', str_replace('="', '"', $matches[1]));
				$length		  = count($parts);
				$range		  = range(0, $length, 2);
				foreach($range as $i)
				{
					if(!isset($parts[$i+1])) continue;
					$attribs[trim($parts[$i])] = $parts[$i+1];
				}
				$items[$name] = array_merge($this->_attribs, $attribs);
			}
			else
			{
				$items[$name] = $this->_attribs;
			}
		}
		
		return $items;
	}


	function onBeforeSaveField( $field, &$post, &$file )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'linkslist') return;
		if(!$post) return;
		
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
	}

	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'linkslist') return;

		$values = $values ? $values : $field->value;

		// some parameter shortcuts
		$field_elements		= $field->parameters->get( 'field_elements', '' ) ;
		$type				= $field->parameters->get( 'list_type', 'ul' ) ;
		$class				= $field->parameters->get( 'list_class', false ) ;
		if($class) $class	= ' class="'.$class.'"';
		$id					= $field->parameters->get( 'list_id', false ) ;
		if($id) $id			= ' id="'.$id.'"';

		$elements = $this->prepare($field_elements);
		$items = array('<'.$type.$class.$id.'>');
		foreach($elements as $name => $item)
		{
			if(!in_array($name, $values)) continue;
			$attr = $item;
			$prefix = '';
			$suffix = '';
			if(isset($attr['href']))
			{
				$prefix = '<a href="'.$attr['href'].'">';
				$suffix = '</a>';
				unset($attr['href']);
			}
			array_walk($attr, array($this, 'walk'), $name);
			$attr = $attr ? ' '.implode(' ', $attr) : null;
			$items[$name] = '<li'.$attr.'>'.$prefix.$name.$suffix.'</li>';
		}
		$items[] = '</'.$type.'>';
		return $field->{$prop} = implode($items);
		$field->{$prop} = implode($separatorf, $items);
	}
	
	function walk(&$value, $key)
	{
		if($key == 'href') $value = false;
		$value = $key.'="'.$value.'"';
	}


	function onDisplayFilter(&$filter, $value='')
	{
		// execute the code only if the field type match the plugin type
		if($filter->field_type != 'linkslist') return;

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