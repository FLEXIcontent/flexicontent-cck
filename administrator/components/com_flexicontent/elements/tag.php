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
 * Renders an Tag element
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */

class JElementTag extends JElement
{
/**
	* Element name
	*
	* @access	protected
	* @var		string
	*/
	var	$_name = 'Tag';

	function fetchElement($name, $value, &$node, $control_name)
	{
		$doc 		=& JFactory::getDocument();
		$value = FLEXI_J16GE ? $this->value : $value;
		
		$fieldName	= $control_name.'['.$name.']';

		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');

		$item =& JTable::getInstance('flexicontent_tags', '');
		if ($value) {
			$item->load($value);
		} else {
			$item->name = JText::_( 'FLEXI_SELECT_TAG' );
		}

		$js = "
		function qfSelectTag(id, title) {
			document.getElementById('a_id').value = id;
			document.getElementById('a_name').value = title;
			document.getElementById('sbox-window').close();
		}";

		$link = 'index.php?option=com_flexicontent&amp;view=tagelement&amp;tmpl=component';
		$doc->addScriptDeclaration($js);

		JHTML::_('behavior.modal', 'a.modal');

		$html = "\n<div style=\"float: left;\"><input style=\"background: #ffffff;\" type=\"text\" id=\"a_name\" value=\"$item->name\" disabled=\"disabled\" /></div>";
		$html .= "<div class=\"button2-left\"><div class=\"blank\"><a class=\"modal\" title=\"".JText::_( 'FLEXI_SELECT' )."\"  href=\"$link\" rel=\"{handler: 'iframe', size: {x: 650, y: 375}}\">".JText::_( 'FLEXI_SELECT' )."</a></div></div>\n";
		$html .= "\n<input type=\"hidden\" id=\"a_id\" name=\"$fieldName\" value=\"$value\" />";

		return $html;
	}
}
?>