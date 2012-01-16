<?php
/**
 * @version 1.5 stable $Id: tags.php 967 2011-11-21 00:01:36Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @author ggppdk
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
 * Renders a tags element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JElementTags extends JElement
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$_name = 'Tags';

	function fetchElement($name, $value, &$node, $control_name)
	{
		$db =& JFactory::getDBO();
		
		$query = 'SELECT id AS value, name AS text'
		. ' FROM #__flexicontent_tags'
		. ' WHERE published = 1'
		. ' ORDER BY name ASC, id ASC'
		;
		
		$db->setQuery($query);
		$tags = $db->loadObjectList();

		$attribs = "";
		if ($node->attributes('multiple')) {
			$attribs .= ' multiple="true" ';
			$attribs .= ($node->attributes('size')) ? ' size="'.$node->attributes('size').'" ' : ' size="6" ';
			$fieldname = $control_name.'['.$name.'][]';
		} else {
			array_unshift($types, JHTML::_('select.option', '', JText::_('FLEXI_PLEASE_SELECT')));
			$attribs .= 'class="inputbox"';
			$fieldname = $control_name.'['.$name.']';
		}
		$values = $value;
		$element_id = $control_name.$name;

		return JHTML::_('select.genericlist', $tags, $fieldname, $attribs, 'value', 'text', $values, $element_id);
	}
}