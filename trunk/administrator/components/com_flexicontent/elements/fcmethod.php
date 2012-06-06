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
if (FLEXI_J16GE) {
	jimport('joomla.form.helper');
	JFormHelper::loadFieldClass('radio');
}

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
		
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}
		$split_char = ",";
		
		$value = FLEXI_J16GE ? $this->value : $value;
		
		$fieldname	= FLEXI_J16GE ? $this->name : $control_name.'['.$name.']';
		$element_id = FLEXI_J16GE ? $this->id : $control_name.$name;
		
		//$disabled_ff = explode($split_char, @$attributes['disabled_ff']);
		$disabled_ff = @$attributes['disabled_ff'];
		
		if ($disabled_ff) {
			$dff_idtag = FLEXI_J16GE ? 'jform_params_'.$disabled_ff : 'params'.$disabled_ff;
			$js 	= "
function filterCategories_".$disabled_ff."(method) {
	var cats = $('".$dff_idtag."');
	var options = cats.getElements('option');
	if (method == 1) {
		cats.setProperty('disabled', 'disabled');
		/*options.each(function(el){
    		el.setProperty('selected', 'selected');
		});*/
	} else {
		cats.setProperty('disabled', '');
	}
}
window.addEvent('domready', function(){
	filterCategories_".$disabled_ff."('".$value."');			
});
		";
			$doc->addScriptDeclaration($js);
			
			$class = 'class="inputbox" onchange="filterCategories_'.$disabled_ff.'(this.value);"';
		} else {
			$class = 'class="inputbox"';
		}
		
		// prepare the options 
		$options = array(); 
		$options[] = JHTML::_('select.option', '1', JText::_('FLEXI_ALL')); 
		$options[] = JHTML::_('select.option', '2', JText::_('FLEXI_EXCLUDE')); 
		$options[] = JHTML::_('select.option', '3', JText::_('FLEXI_INCLUDE')); 
		
		$html = JHTML::_('select.radiolist', $options, $fieldname, $class, 'value', 'text', $value, $element_id);
		if (FLEXI_J16GE) {
			$html = '<fieldset id="'.$element_id.'" class="radio">'.$html.'</fieldset>';
		}
		return $html;
	}
}