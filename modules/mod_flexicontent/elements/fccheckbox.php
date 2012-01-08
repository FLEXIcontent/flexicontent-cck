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
		// Make value an array if value is not already array
		if (!is_array($value))
			$value = strlen($value) ? array($value) : array();
			
		// Get options and values
		$checkoptions = explode(",", $node->attributes('checkoptions'));
		$checkvals = explode(",", $node->attributes('checkvals'));
		
		// Sanity check
		if (count($checkoptions)!=count($checkvals))
			return "Number of check options not equal to number of check values";
		
		// Create checkboxes
		$html = "";
		foreach($checkoptions as $i => $o) {
			$element_id = $control_name.$name.$i;
			$fieldname = $control_name.'['.$name.'][]';
			$html .= '<input id="'.$control_name.$name.$i.'" type="checkbox"';
			$html .= in_array($checkvals[$i], $value) ? ' checked="checked"' : '' ;
			$html .= ' name="'.$fieldname.'" value="'.$checkvals[$i].'">'.JText::_($checkoptions[$i]).' &nbsp; ';
		}
		
		return $html;
	}
}

?>