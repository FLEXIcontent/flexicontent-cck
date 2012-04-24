<?php
/**
 * @version 1.5 stable $Id: types.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
jimport('joomla.html.html');
jimport('joomla.form.formfield');
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
class JFormFieldAlphaindex extends JFormField
{
/**
	* Element name
	*
	* @access	protected
	* @var		string
	*/
	var	$type = 'Alphaindex';

	function getInput()
	{
		$doc 		=& JFactory::getDocument();
		$db =& JFactory::getDBO();
		$node = &$this->element;
		$options = array();
		$options[0] = new stdClass();  $options[1] = new stdClass();  $options[2] = new stdClass();
		$options[0]->text=JTEXT::_("FLEXI_HIDE"); $options[0]->value=0;
		$options[1]->text=JTEXT::_("FLEXI_SHOW_ALPHA_USE_LANG_DEFAULT"); $options[1]->value=1;
		$options[2]->text=JTEXT::_("FLEXI_SHOW_ALPHA_USE_CUSTOM_CHARS"); $options[2]->value=2;
		
		$attribs = ' class="inputbox" onchange="updatealphafields(this.value);" ';
		
		$js = "
		window.addEvent( 'domready', function()
		{
			updatealphafields(".$this->value.");
		});

		function updatealphafields(val) {
			var aichars=document.getElementById('jform_params_alphacharacters');
			var aicharclasses=document.getElementById('jform_params_alphagrpcssclasses');
			//var aicharseparator=document.getElementById('jform_params_alphacharseparator');
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
		
		return JHTML::_('select.genericlist', $options, $this->name, $attribs, 'value', 'text', $this->value, $this->id);
	}
}
?>
