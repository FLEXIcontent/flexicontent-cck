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
defined('_JEXEC') or die();

/**
 * Renders a fields element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JElementSeparator extends JElement
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$_name = 'separator';
	
	function fetchElement($name, $value, &$node, $control_name)
	{
		$level = $node->attributes('level');
		if ($level == 'level2') {
			$style = 'padding: 4px 4px 4px 10px; background-color: #ccc; display: block; color: #000; font-weight: bold; margin-left:10px;';
		} else if ($level == 'level3') {
			$style = 'padding: 5px 4px 5px 5px; font-weight: bold;';
		} else {
			$style = 'padding: 5px 4px 5px 10px; background-color: #777; display: block; color: #fff; font-weight: bold;';
		}
		
		$class = ""; $title = "";
		if ($node->attributes('description')) {
			$class = "hasTip";
			$title = JText::_($value)."::".JText::_($node->attributes('description'));
		}
		return '<span style="'.$style.'" class="'.$class.'" title="'.$title.'" >'.JText::_($value).'</div>';
	}

}