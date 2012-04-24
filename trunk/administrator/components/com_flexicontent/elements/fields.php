<?php
/**
 * @version 1.5 stable $Id$
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

/**
 * Renders a fields element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JElementFields extends JElement
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$_name = 'Fields';

	function fetchElement($name, $value, &$node, $control_name)
	{
		$doc	= & JFactory::getDocument();
		$db		= & JFactory::getDBO();
		
		$and = ((boolean)$node->attributes('isnotcore')) ? ' AND iscore = 0' : '';
		if ((boolean)$node->attributes('fieldnameastext')) {
			$text = 'CONCAT(label, \'(\', `name`, \')\')';
		} else {
			$text = 'label';
		}
		if ((boolean)$node->attributes('fieldnameasvalue')) {
			$ovalue = '`name`';
		} else {
			$ovalue = 'id';  // ELSE should always be THIS , otherwise we break compatiblity with all previous FC versions
		}
		
		$isadvsearch = $node->attributes('isadvsearch');
		if($isadvsearch) {
			$and .= " AND isadvsearch='{$isadvsearch}'";
		}
		
		$field_type = $node->attributes('field_type');
		if($field_type) {
			$field_type = explode(",", $field_type);
			$and .= " AND field_type IN ('". implode("','", $field_type)."')";
		}
		
		$exclude_field_type = $node->attributes('exclude_field_type');
		if($exclude_field_type) {
			$exclude_field_type = explode(",", $exclude_field_type);
			$and .= " AND field_type NOT IN ('". implode("','", $exclude_field_type)."')";
		}		
		
		$query = 'SELECT '.$ovalue.' AS value, '.$text.' AS text'
		. ' FROM #__flexicontent_fields'
		. ' WHERE published = 1'
		. $and
		. ' ORDER BY label ASC, id ASC'
		;
		
		$db->setQuery($query);
		$fields = $db->loadObjectList();
		
		$values			= FLEXI_J16GE ? $this->value : $value;
		if ( empty($values) )							$values = array();
		else if ( ! is_array($values) )		$values = !FLEXI_J16GE ? array($values) : explode("|", $values);
		
		$fieldname	= FLEXI_J16GE ? $this->name : $control_name.'['.$name.']';
		$element_id = FLEXI_J16GE ? $this->id : $control_name.'_'.$name;
		
		$attribs = ' style="float:left;" ';
		if ($node->attributes('multiple') && $node->attributes('multiple')!='false' ) {
			$attribs .= ' multiple="true" ';
			$attribs .= ($node->attributes('size')) ? ' size="'.$node->attributes('size').'" ' : ' size="6" ';
			$fieldname .= !FLEXI_J16GE ? "[]" : "";
			$maximize_link = "<a style='display:inline-block;".(FLEXI_J16GE ? 'float:left; margin: 6px 0px 0px 18px;':'margin:0px 0px 6px 12px')."' href='javascript:;' onclick='$element_id = document.getElementById(\"$element_id\"); if ($element_id.size<16) { ${element_id}_oldsize=$element_id.size; $element_id.size=16;} else { $element_id.size=${element_id}_oldsize; } ' >Maximize/Minimize</a>";
		} else {
			array_unshift($fields, JHTML::_('select.option', '', JText::_('FLEXI_PLEASE_SELECT')));
			$attribs .= 'class="inputbox"';
			$maximize_link = '';
		}
		if ($onchange = $node->attributes('onchange')) {
			$attribs .= ' onchange="'.$onchange.'"';
		}

		$html = JHTML::_('select.genericlist', $fields, $fieldname, $attribs, 'value', 'text', $values, $element_id);
		return $html.$maximize_link;
	}
}