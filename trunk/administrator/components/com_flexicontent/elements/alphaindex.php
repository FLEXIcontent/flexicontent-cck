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
defined('_JEXEC') or die();

/**
 * Renders a fields element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JElementTypes extends JElement
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$_name = 'Types';

	function fetchElement($name, $value, &$node, $control_name)
	{
		$db =& JFactory::getDBO();
		
		$query = 'SELECT id AS value, name AS text'
		. ' FROM #__flexicontent_types'
		. ' WHERE published = 1'
		. ' ORDER BY name ASC, id ASC'
		;
		
		$db->setQuery($query);
		$types = $db->loadObjectList();

		$attribs = "";
		if ($node->attributes('multiple')) {
			$attribs .= 'multiple="true" size="10"';
			$fieldname = $control_name.'['.$name.'][]';
		} else {
			array_unshift($types, JHTML::_('select.option', '', JText::_('FLEXI_PLEASE_SELECT')));
			$attribs .= 'class="inputbox"';
			$fieldname = $control_name.'['.$name.']';
		}
		return JHTML::_('select.genericlist', $types, $fieldname, $attribs, 'value', 'text', $value, $control_name.$name);
	}
}
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
		$db =& JFactory::getDBO();
		
		$doc 		=& JFactory::getDocument();
		$options = array();
		$options[0] = new stdClass();  $options[1] = new stdClass();  $options[2] = new stdClass();
		$options[0]->text=JTEXT::_("FLEXI_HIDE"); $options[0]->value=0;
		$options[1]->text=JTEXT::_("FLEXI_SHOW_ALPHA_USE_LANG_DEFAULT"); $options[1]->value=1;
		$options[2]->text=JTEXT::_("FLEXI_SHOW_ALPHA_USE_CUSTOM_CHARS"); $options[2]->value=2;
		
		$attribs = ' class="inputbox" onchange="updatealphafields(this.value);" ';
		
		$js = "
		window.addEvent( 'domready', function()
		{
			updatealphafields(".$value.");
		});

		function updatealphafields(val) {
			var aichars=document.getElementById('".$control_name."alphacharacters');
			var aicharclasses=document.getElementById('".$control_name."alphagrpcssclasses');
			//var aicharseparator=document.getElementById('".$control_name."alphacharseparator');
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
		
		$fieldname = $control_name.'['.$name.']';
		
		return JHTML::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $value, $control_name.$name);
	}
}
?>
