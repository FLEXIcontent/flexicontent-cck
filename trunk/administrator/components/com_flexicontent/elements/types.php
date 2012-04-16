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
 * Renders a types element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JElementTypes extends JElement
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$_name = 'Types';

	function fetchElement($name, $value, &$node, $control_name)
	{
		$doc	= & JFactory::getDocument();
		$db		= & JFactory::getDBO();
		
		$query = 'SELECT id AS value, name AS text'
		. ' FROM #__flexicontent_types'
		. ' WHERE published = 1'
		. ' ORDER BY name ASC, id ASC'
		;
		
		$db->setQuery($query);
		$types = $db->loadObjectList();
		
		$values			= FLEXI_J16GE ? $this->value : $value;
		if ( empty($values) )							$values = array();
		else if ( ! is_array($values) )		$values = !FLEXI_J16GE ? array($values) : explode("|", $values);
		
		$fieldname	= FLEXI_J16GE ? $this->name : $control_name.'['.$name.']';
		$element_id = FLEXI_J16GE ? $this->id : $control_name.'_'.$name;
		
		$attribs = 'style="float:left;"';
		if ($node->attributes('multiple') && $node->attributes('multiple')!='false' ) {
			$attribs .= ' multiple="true" ';
			$attribs .= ($node->attributes('size')) ? ' size="'.$node->attributes('size').'" ' : ' size="6" ';
			$fieldname .= !FLEXI_J16GE ? "[]" : "";
			$maximize_link = "<a style='display:inline-block;".(FLEXI_J16GE ? 'float:left; margin: 6px 0px 0px 18px;':'margin:0px 0px 6px 12px')."' href='javascript:;' onclick='$element_id = document.getElementById(\"$element_id\"); if ($element_id.size<16) { ${element_id}_oldsize=$element_id.size; $element_id.size=16;} else { $element_id.size=${element_id}_oldsize; } ' >Maximize/Minimize</a>";
		} else {
			array_unshift($types, JHTML::_('select.option', '', JText::_('FLEXI_PLEASE_SELECT')));
			$attribs .= 'class="inputbox"';
			$maximize_link = '';
		}

		$html = JHTML::_('select.genericlist', $types, $fieldname, $attribs, 'value', 'text', $values, $element_id);
		return $html.$maximize_link;
	}
}