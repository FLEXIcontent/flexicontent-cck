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
use Joomla\Database\DatabaseInterface;

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // \Joomla\CMS\HTML\Helpers\Select
jimport('joomla.form.field');  // \Joomla\CMS\Form\FormField

//jimport('joomla.form.helper'); // \Joomla\CMS\Form\FormHelper
//\Joomla\CMS\Form\FormHelper::loadFieldClass('...');   // \Joomla\CMS\Form\FormField...

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
class JFormFieldAlphaindex extends \Joomla\CMS\Form\FormField
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
		$doc  = \Joomla\CMS\Factory::getApplication()->getDocument();
		$db   = \Joomla\CMS\Factory::getContainer()->get(DatabaseInterface::class);

		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];

		$options = array();
		$i=-1;
		if (@ $attributes['use_global'])
		{
			$options[++$i] = new stdClass(); $options[$i]->text=\Joomla\CMS\Language\Text::_("FLEXI_USE_GLOBAL"); $options[$i]->value='';
		}
		$options[++$i] = new stdClass(); $options[$i]->text=\Joomla\CMS\Language\Text::_("FLEXI_HIDE"); $options[$i]->value=0;
		$options[++$i] = new stdClass(); $options[$i]->text=\Joomla\CMS\Language\Text::_("FLEXI_SHOW_ALPHA_USE_LANG_DEFAULT"); $options[$i]->value=1;
		$options[++$i] = new stdClass(); $options[$i]->text=\Joomla\CMS\Language\Text::_("FLEXI_SHOW_ALPHA_USE_CUSTOM_CHARS"); $options[$i]->value=2;
		
		$value = $this->value;
		
		$fieldname  = $this->name;
		$element_id = $this->id;
		$element_name = $this->fieldname;
		
		$attribs = ' class="inputbox" onchange="updatealphafields(this.value);" ';
		
		$js = "
		document.addEventListener('DOMContentLoaded', function()
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
		
		return \Joomla\CMS\HTML\HTMLHelper::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $value, $element_id);
	}
}