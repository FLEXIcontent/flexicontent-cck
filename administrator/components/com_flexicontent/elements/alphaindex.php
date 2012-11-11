<?php
/**
 * @version 1.5 stable $Id: types.php 806 2011-08-12 16:50:53Z ggppdk $
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
}

/**
 * Renders an alphaindex element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */

/**
 * Renders an alphaindex element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JElementAlphaindex extends JElement
{
/**
	* Element name
	*
	* @access	protected
	* @var		string
	*/
	var	$_name = 'Alphaindex';

	function fetchElement($name, $value, &$node, $control_name)
	{
		$doc 		=& JFactory::getDocument();
		$db =& JFactory::getDBO();
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}
		
		$options = array();
		$i=-1;
		if (@$attributes['use_global']) {
			$options[++$i] = new stdClass();
			$options[$i]->text=JTEXT::_("FLEXI_USE_GLOBAL"); $options[$i]->value='';
		}
		$options[++$i] = new stdClass(); $options[$i]->text=JTEXT::_("FLEXI_HIDE"); $options[$i]->value=0;
		$options[++$i] = new stdClass(); $options[$i]->text=JTEXT::_("FLEXI_SHOW_ALPHA_USE_LANG_DEFAULT"); $options[$i]->value=1;
		$options[++$i] = new stdClass(); $options[$i]->text=JTEXT::_("FLEXI_SHOW_ALPHA_USE_CUSTOM_CHARS"); $options[$i]->value=2;
		
		$value  = FLEXI_J16GE ? $this->value : $value;
		
		$fieldname	= FLEXI_J16GE ? $this->name : $control_name.'['.$name.']';
		$element_id = FLEXI_J16GE ? $this->id : $control_name.$name;
		$element_name = FLEXI_J16GE ? $this->fieldname : $name;
		
		$attribs = ' class="inputbox" onchange="updatealphafields(this.value);" ';
		
		$js = "
		window.addEvent( 'domready', function()
		{
			updatealphafields(".$value.");
		});

		function updatealphafields(val) {
			var aichars=document.getElementById('".str_replace($element_name, 'alphacharacters', $element_id)."');
			var aicharclasses=document.getElementById('".str_replace($element_name, 'alphagrpcssclasses', $element_id)."');
			//var aicharseparator=document.getElementById('".str_replace($element_name, 'alphacharseparator', $element_id)."');
			if(val!=2) {
				aichars.disabled=1;          aichars.style.backgroundColor='#D4D0C8';
				aicharclasses.disabled=1;    aicharclasses.style.backgroundColor='#D4D0C88';
				//aicharseparator.disabled=1;  aicharseparator.style.backgroundColor='#D4D0C8';
			} else {
				aichars.disabled=0;          aichars.style.backgroundColor='';
				aicharclasses.disabled=0;    aicharclasses.style.backgroundColor='';
				//aicharseparator.disabled=0;  aicharseparator.style.backgroundColor='';
			}
		}";

		$doc->addScriptDeclaration($js);
		
		return JHTML::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $value, $element_id);
	}
}
?>
