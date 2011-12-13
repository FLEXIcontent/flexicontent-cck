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

		$db =& JFactory::getDBO();
		
		$and = ($node->attributes('isnotcore')) ? ' AND iscore = 0' : '';
		if ($node->attributes('fieldnameastext')) {
			$text = 'CONCAT(label, \'(\', `name`, \')\')';
		}else{
			$text = 'label';
		}
		if ($node->attributes('fieldnameasvalue')) {
			$ovalue = '`name`';
		}else{
			$ovalue = 'id';  // ELSE should always be THIS , otherwise we break compatiblity with all previous FC versions
		}
		$isadvsearch = $node->attributes('isadvsearch');
		if($isadvsearch!==NULL) {
			$and .= " AND isadvsearch='{$isadvsearch}'";
		}
		$query = 'SELECT '.$ovalue.' AS value, '.$text.' AS text'
		. ' FROM #__flexicontent_fields'
		. ' WHERE published = 1'
		. $and
		. ' ORDER BY label ASC, id ASC'
		;
		
		$db->setQuery($query);
		$fields = $db->loadObjectList();

		$attribs = "";
		if ($node->attributes('multiple')) {
			$attribs .= 'multiple="true" size="10"';
			$fieldname = $control_name.'['.$name.'][]';
		} else {
			array_unshift($fields, JHTML::_('select.option', '', JText::_('FLEXI_PLEASE_SELECT')));
			$attribs .= 'class="inputbox"';
			$fieldname = $control_name.'['.$name.']';
		}
		if ($onchange = $node->attributes('onchange')) {
			$attribs .= ' onchange="'.$onchange.'"';
		}

		return JHTML::_('select.genericlist', $fields, $fieldname, $attribs, 'value', 'text', $value, $control_name.$name);
	}
}