<?php
/**
 * @version 1.5 stable $Id: fccheckbox.php 967 2011-11-21 00:01:36Z ggppdk $
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
require_once JPATH_LIBRARIES.DS.'joomla'.DS.'html'.DS.'pane.php';
/**
 * Renders a multi element checkbox (array of checkboxes)
 */
class JElementFCcheckbox extends JElement
{

 /**
	* Element name
	*
	* @access	protected
	* @var		string
	*/
	var $_name = 'FCcheckbox';
	
	function fetchElement($name, $value, &$node, $control_name)
	{
		$split_char = ",";
		
		// Get options and values
		$checkoptions = explode($split_char, $node->attributes('checkoptions'));
		$checkvals = explode($split_char, $node->attributes('checkvals'));
		$defaultvals = explode($split_char, $node->attributes('defaultvals'));

		// Make value an array if value is not already array, also load defaults, if field parameter never saved
		if (!is_array($value) || count($value)==0)
			$value = (!is_array($value) && strlen($value)) ? array($value) : $defaultvals;

		// Sanity check
		if (count($checkoptions)!=count($checkvals))
			return "Number of check options not equal to number of check values";
		
		// Create checkboxes
		$fieldname = $control_name.'['.$name.'][]';
		$element_id = $control_name.$name;
		$html = "";
		foreach($checkoptions as $i => $o) {
			$curr_element_id = $element_id.$i;
			$html .= '<input id="'.$curr_element_id.'" type="checkbox"';
			$html .= in_array($checkvals[$i], $value) ? ' checked="checked"' : '' ;
			$html .= ' name="'.$fieldname.'" value="'.$checkvals[$i].'">';
			$html .= '<label for="'.$curr_element_id.'" >'.JText::_($checkoptions[$i]).'</label> &nbsp; ';
		}
		$html .= '<input id="'.$element_id.'9999" type="hidden"  name="'.$fieldname.'" value="__SAVED__" /> ';
		
		return $html;
	}
}

?>