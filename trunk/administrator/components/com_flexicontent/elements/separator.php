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
			$style = 'padding: 2px 0% 2px 4%; display: block; background-color: #ccc; color: #000; font-weight: bold; margin: 0px 2% 2px 6%; width:84%; display: block; float: left; text-align: center; border: 1px outset #E9E9E9;';
		} else if ($level == 'level3') {
			$style = 'padding: 4px 6% 4px 6%; font-weight: bold; clear:both; width:100%; display: block; float: left;';
		} else {
			$style = 'padding: 4px 2% 4px 2%; display: block; background-color: #333333; color: #fff; font-weight: bold; margin: 2px 0% 2px 0%; width:96%; display: block; float: left; border: 1px outset #E9E9E9; font-family:tahoma; font-size:12px;';
		}
		
		$class = ""; $title = "";
		if ($node->attributes('description')) {
			$class = "hasTip";
			$title = JText::_($value)."::".JText::_($node->attributes('description'));
		}
		return '<span style="'.$style.'" class="'.$class.'" title="'.$title.'" >'.JText::_($value).'</div>';
	}

}