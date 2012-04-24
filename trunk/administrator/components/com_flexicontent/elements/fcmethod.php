<?php
/**
 * @version 1.5 stable $Id: fcmethod.php 967 2011-11-21 00:01:36Z ggppdk $
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
 * Renders a selcet method radio element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JElementFcmethod extends JElement
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	
	var	$_name = 'Fcmethod';

	function fetchElement($name, $value, &$node, $control_name)
	{
		$doc 	=& JFactory::getDocument();
		$js 	= "
function filterCategories(method) {
	var cats = $('paramscatids');
	var options = cats.getElements('option');
	if (method == 1) {
		cats.setProperty('disabled', '');
		options.each(function(el){
    		el.setProperty('selected', 'selected');
		});
	} else {
		cats.setProperty('disabled', '');
	}
}
window.addEvent('domready', function(){
	filterCategories('".$value."');			
});
		";
//		$doc->addScriptDeclaration($js);

//		$class 	= 'class="inputbox" onchange="filterCategories(this.value);"';
		$class 	= 'class="inputbox"';
		
		// prepare the options 
		$options = array(); 
		$options[] = JHTML::_('select.option', '1', JText::_('FLEXI_ALL')); 
		$options[] = JHTML::_('select.option', '2', JText::_('FLEXI_EXCLUDE')); 
		$options[] = JHTML::_('select.option', '3', JText::_('FLEXI_INCLUDE')); 

		return JHTML::_('select.radiolist', $options, $control_name.'['.$name.']', $class, 'value', 'text', $value, $control_name.$name);
	}
}