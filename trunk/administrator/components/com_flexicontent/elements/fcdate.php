<?php
/**
 * @version 1.5 beta 4 $Id: fcdate.php 967 2011-11-21 00:01:36Z ggppdk $
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
if (FLEXI_J16GE) {
	jimport('joomla.html.html');
	jimport('joomla.form.formfield');
	jimport('joomla.form.helper');
	JFormHelper::loadFieldClass('list');
}

/**
 * Renders a date element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JElementFcdate extends JElement
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	
	var	$_name = 'Fcdate';

	function fetchElement($name, $value, &$node, $control_name)
	{
		$document = JFactory::getDocument();
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}
		
		$css  = '.calendar { vertical-align:middle; }';
		$document->addStyleDeclaration($css);
		
		$value = FLEXI_J16GE ? $this->value : $value;
		$fieldname	= FLEXI_J16GE ? $this->name : $control_name.'['.$name.']';
		$element_id = FLEXI_J16GE ? $this->id : $control_name.$name;
		$format = '%Y-%m-%d';
		$attribs = (@$attributes['size']) ? ' size="'.@$attributes['size'].'" ' : ' size="18" ';
		
 		//return JHTML::_('calendar', $value, $fieldname, $element_id, $format, $attribs);
 		return $this->calendar($value, $fieldname, $element_id, $format, $attribs);
	}
	
	
	function calendar($value, $name, $id, $format = '%Y-%m-%d', $attribs = null)
	{
		JHTML::_('behavior.calendar'); //load the calendar behavior

		if (is_array($attribs)) {
			$attribs = JArrayHelper::toString( $attribs );
		}
		$document = JFactory::getDocument();
		$document->addScriptDeclaration('window.addEvent(\'domready\', function() {Calendar.setup({
        inputField     :    "'.$id.'",     // id of the input field
        ifFormat       :    "'.$format.'",      // format of the input field
        button         :    "'.$id.'_img",  // trigger for the calendar (button ID)
        align          :    "Tl",           // alignment (defaults to "Bl")
        singleClick    :    true
    });});');

		return '<input type="text" name="'.$name.'" id="'.$id.'" value="'.htmlspecialchars($value, ENT_COMPAT, 'UTF-8').'" '.$attribs.' />'.
				 '<img class="calendar" src="'.JURI::root(true).'/templates/system/images/calendar.png" alt="calendar" id="'.$id.'_img" />';
	}

}