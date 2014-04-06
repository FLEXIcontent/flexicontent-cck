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

class JElementMultiList extends JElement
{
	/**
	* Element name
	*
	* @access       protected
	* @var          string
	*/
	var	$_name = 'MultiList';

	function fetchElement($name, $value, &$node, $control_name)
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
		
		$attribs = ' style="float:left;" ';
		if (@$attributes['multiple']=='multiple' || @$attributes['multiple']=='true' ) {
			$attribs .= ' multiple="true" ';
			$attribs .= (@$attributes['size']) ? ' size="'.$attributes['size'].'" ' : ' size="6" ';
			$fieldname .= !FLEXI_J16GE ? "[]" : "";  // NOTE: this added automatically in J2.5
			$maximize_link = "<a style='display:inline-block;".(FLEXI_J16GE ? 'float:left; margin: 6px 0px 0px 18px;':'margin:0px 0px 6px 12px')."' href='javascript:;' onclick='$element_id = document.getElementById(\"$element_id\"); if ($element_id.size<16) { ${element_id}_oldsize=$element_id.size; $element_id.size=16;} else { $element_id.size=${element_id}_oldsize; } ' >Maximize/Minimize</a>";
		} else {
			$maximize_link = '';
		}
		
		
		// HTML Tag parameters
		if ($onchange = @$attributes['onchange']) {
			$onchange = str_replace('{control_name}', $control_name, $onchange);
			$attribs .= ' onchange="'.$onchange.'"';
		}
		if ($class = @$attributes['class']) {
			$attribs .= ' class="'.$class.'"';
		}
		
		
		// Construct an array of the HTML OPTION statements.
		$options = array ();
		foreach ($node->children() as $option)
		{
			$val  = FLEXI_J16GE ? $option->attributes()->value : $option->attributes('value');
			$text = $option->data();
			$name = FLEXI_J16GE ? $option->name() : $option->_name;
			//echo "<pre>"; print_r($option); echo "</pre>"; exit;
			if ($name=="group") {
				$group_label = FLEXI_J16GE ? $option->attributes()->label : $option->attributes('label');
				$options[] = JHTML::_('select.optgroup', $group_label );
				foreach ($option->children() as $sub_option)
				{
					$val    = FLEXI_J16GE ? $sub_option->attributes()->value : $sub_option->attributes('value');
					$text   = $sub_option->data();
					$options[] = JHTML::_('select.option', $val, JText::_($text));
				}
			}
			else
				$options[] = JHTML::_('select.option', $val, JText::_($text));
		}
		
		$html = JHTML::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $values, $element_id);
		return $html.$maximize_link;
	}
}