<?php
/**
 * @version 1.5 stable $Id: fctag.php 1800 2013-11-01 04:30:57Z ggppdk $
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
 * Renders an FC Tag element
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */

class JFormFieldFctag extends JFormField
{
 /**
	* Element name
	*
	* @access	protected
	* @var		string
	*/
	var	$type = 'Fctag';

	function getInput()
	{
		$doc = JFactory::getDocument();
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}
		
		$value = FLEXI_J16GE ? $this->value : $value;
		if (FLEXI_J16GE) {
			$paramset = isset($attributes['paramset']) ? $attributes['paramset'] : 'request';
		}
		$required = isset($attributes['required']) ? $attributes['required'] : false;
		
		$fieldname = FLEXI_J16GE ? "jform[".$paramset."][".$this->element["name"]."]" : $control_name.'['.$name.']';
		$element_id = FLEXI_J16GE ? "jform_".$paramset."_".$node["name"] : "a_id";
		$prompt_str = JText::_( 'FLEXI_SELECT_TAG', true );

		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');

		$item = JTable::getInstance('flexicontent_tags', '');
		if ($value) {
			$item->load($value);
			$title = $item->name;
		} else {
			$title = "";
			$value = "";  // clear possible invalid value
		}
		
		$required_js = "";
		$required_param = "";
		
		// J1.5 does not have required, we implement it via custom JS
		if (!FLEXI_J16GE) {
			if ( @$attributes['required'] ) {
				$required_js ="
					$$('#toolbar-apply a.toolbar').setProperty('onclick',
						\" if ( $('a_id').getProperty('value') != '' ) { $('urlparams".$attributes["name"]."-lbl').setStyle('color',''); submitbutton('apply'); } else { alert('".$prompt_str."'); $('urlparams".$attributes["name"]."-lbl').setStyle('color','red'); } \"
					);

					$$('#toolbar-save a.toolbar').setProperty('onclick',
						\" if ( $('a_id').getProperty('value') != '' ) { $('urlparams".$attributes["name"]."-lbl').setStyle('color',''); submitbutton('save'); } else { alert('".$prompt_str."'); $('urlparams".$attributes["name"]."-lbl').setStyle('color','red'); } \"
					);
				";
			}
		}
		
		// J1.6+ does have required form capability, add a HTML tag parameter
		else {
			$required_param = $required ? ' required="required" class="required" aria-required="true" ' : '';
		}
		
		$js = "
		window.addEvent( 'domready', function()
		{
			$('remove').addEvent('click', function(){
				$('a_name').setProperty('value', '');
				$('".$element_id."').setProperty('value', '');
			});
			".$required_js."
		});

		function qfSelectTag(id, title) {
			document.getElementById('".$element_id."').value = id;
			document.getElementById('a_name').value = title;
			".(!FLEXI_J16GE ?
				"document.getElementById('sbox-window').close();" :
				"$('sbox-btn-close').fireEvent('click');"
			)."
		}";

		$link = 'index.php?option=com_flexicontent&amp;view=tagelement&amp;tmpl=component';
		$doc->addScriptDeclaration($js);

		JHTML::_('behavior.modal', 'a.modal');

		$html = "\n<div style=\"float: left;\"><input style=\"background: #ffffff;\" type=\"text\" id=\"a_name\" value=\"{$title}\" ".$required_param." readonly=\"readonly\" /></div>";
		$html .= "<div class=\"button2-left\"><div class=\"blank\"><a class=\"modal btn btn-small btn-success\" title=\"".JText::_( 'FLEXI_SELECT' )."\"  href=\"$link\" rel=\"{handler: 'iframe', size: {x: 800, y: 500}}\">".JText::_( 'FLEXI_SELECT' )."</a></div></div>\n";
		$html .= "\n<input type=\"hidden\" id=\"".$element_id."\" name=\"".$fieldname."\" ".$required_param." value=\"{$value}\" />";
		$html .= "<div class=\"button2-left\"><div class=\"blank\"><a class=\"btn btn-small btn-danger\" id=\"remove\" title=\"".JText::_( 'FLEXI_REMOVE_VALUE' )."\"  href=\"#\"\">".JText::_( 'FLEXI_REMOVE_VALUE' )."</a></div></div>\n";

		return $html;
	}
}
?>