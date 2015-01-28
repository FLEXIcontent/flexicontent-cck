<?php
/**
* @version 1.5 stable $Id: types.php 1340 2012-06-06 02:30:49Z ggppdk $
* @package Joomla
* @subpackage FLEXIcontent
* @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
* @license GNU/GPL v2
*
* FLEXIcontent is a derivative work of the excellent QuickFAQ component
* @copyright (C) 2008 Christoph Lukes
* see www.schlu.net for more information
*
* FLEXIcontent is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*/

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');
if (FLEXI_J16GE) {
	jimport('joomla.html.html');
	jimport('joomla.form.formfield');
}

/**
* Renders a multiple select element
*
*/

class JFormFieldMultiList extends JFormField
{
	/**
	* Element name
	*
	* @access       protected
	* @var          string
	*/
	var	$type = 'MultiList';

	function getInput()
	{
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}
		
		$values			= FLEXI_J16GE ? $this->value : $value;
		if ( empty($values) )							$values = array();
		else if ( ! is_array($values) )		$values = !FLEXI_J16GE ? array($values) : explode("|", $values);
		
		$fieldname	= FLEXI_J16GE ? $this->name : $control_name.'['.$name.']';
		$element_id = FLEXI_J16GE ? $this->id : $control_name.$name;
		
		$name = FLEXI_J16GE ? $attributes['name'] : $name;
		$control_name = FLEXI_J16GE ? str_replace($name, '', $element_id) : $control_name;
		
		//$attribs = ' style="float:left;" ';
		$attribs = array(
	    'id' => $element_id, // HTML id for select field
	    'list.attr' => array( // additional HTML attributes for select field
	    ),
	    'list.translate'=>false, // true to translate
	    'option.key'=>'value', // key name for value in data array
	    'option.text'=>'text', // key name for text in data array
	    'option.attr'=>'attr', // key name for attr in data array
	    'list.select'=>$element_id, // value of the SELECTED field
		);
		
		if (@$attributes['multiple']=='multiple' || @$attributes['multiple']=='true' ) {
			$attribs['list.attr']['multiple'] = 'multiple';
			$attribs['list.attr']['size'] = @$attributes['size'] ? $attributes['size'] : "6";
			$fieldname .= !FLEXI_J16GE ? "[]" : "";  // NOTE: this added automatically in J2.5
			$maximize_link = "<a style='display:inline-block;".(FLEXI_J16GE ? 'float:left; margin: 6px 0px 0px 18px;':'margin:0px 0px 6px 12px')."' href='javascript:;' onclick='$element_id = document.getElementById(\"$element_id\"); if ($element_id.size<16) { ${element_id}_oldsize=$element_id.size; $element_id.size=16;} else { $element_id.size=${element_id}_oldsize; } ' >Maximize/Minimize</a>";
		} else {
			$maximize_link = '';
		}
		
		
		// HTML Tag parameters
		if ($onchange = @$attributes['onchange']) {
			$onchange = str_replace('{control_name}', $control_name, $onchange);
			$attribs['list.attr']['onchange'] = $onchange;
		}
		
		$attribs['list.attr']['class'] = array();
		if ($class = @$attributes['class']) {
			$attribs['list.attr']['class'][] = $class;
		}
		
		if (@$attributes['toggle_related']) {
			$attribs['list.attr']['class'][] = 'fcform_toggler_element';
		}
		$attribs['list.attr']['class'] = implode($attribs['list.attr']['class'], ' ');
		
		// Construct an array of the HTML OPTION statements.
		$options = array ();
		foreach ($node->children() as $option)
		{
			$val  = $option->attributes()->value;
			$text = FLEXI_J30GE ? $option->__toString() : $option->data();
			$name = FLEXI_J30GE ? $option->getName() : $option->name();
			//echo "<pre>"; print_r($option); echo "</pre>"; exit;
			if ($name=="group") {
				$group_label = FLEXI_J16GE ? $option->attributes()->label : $option->attributes('label');
				$options[] = JHTML::_('select.optgroup', JText::_($group_label) );
				foreach ($option->children() as $sub_option)
				{
					$val    = $sub_option->attributes()->value;
					$text   = FLEXI_J30GE ? $sub_option->__toString() : $sub_option->data();
					//$options[] = JHTML::_('select.option', $val, JText::_($text));
					$options[] = array(
						'value' => $val, 'text' => JText::_($text),
						'attr' => array(
							'show_list'=>$sub_option->attributes()->show_list,
							'hide_list'=>$sub_option->attributes()->hide_list
						)
					);
				}
				$options[] = JHTML::_('select.optgroup', '' );
			}
			else {
				//$options[] = JHTML::_('select.option', $val, JText::_($text));
				$options[] = array(
					'value' => $val, 'text' => JText::_($text),
					'attr' => array(
						'show_list'=>$option->attributes()->show_list,
						'hide_list'=>$option->attributes()->hide_list
					)
				);
			}
		}
		
		$html = JHTML::_('select.genericlist', $options, $fieldname, $attribs);
		if (!FLEXI_J16GE) $html = str_replace('<optgroup label="">', '</optgroup>', $html);
		return $html.$maximize_link;
	}
}