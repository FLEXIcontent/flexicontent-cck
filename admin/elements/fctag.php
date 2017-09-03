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

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // JHtmlSelect
jimport('joomla.form.field');  // JFormField

//jimport('joomla.form.helper'); // JFormHelper
//JFormHelper::loadFieldClass('...');   // JFormField...

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
		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];
		
		$value = $this->value;
		$paramset = isset($attributes['paramset']) ? $attributes['paramset'] : 'request';
		$required = isset($attributes['required']) ? $attributes['required'] : false;
		
		$fieldname = "jform[".$paramset."][".$this->element["name"]."]";
		$element_id = "jform_".$paramset."_".$node["name"];
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
		
		$required_param = $required ? ' required="required" class="required" aria-required="true" ' : '';
		
		static $js_added = false;
		if (!$js_added) {
			$js = "
			function qfClearSelectedTag(element_id)
			{
				jQuery('#'+element_id+'_name').val('');
				jQuery('#'+element_id+'_name').attr('placeholder', '".JText::_( 'FLEXI_FORM_SELECT',true )."');
				jQuery('#'+element_id).val('');
				jQuery('#'+element_id + '_clear').addClass('hidden');
				jQuery('#'+element_id + '_edit').addClass('hidden');
				return false;
			};
			
			var fc_select_tag_element_id;
			function qfSelectTag(id, title) {
				document.getElementById(fc_select_tag_element_id).value = id;
				document.getElementById(fc_select_tag_element_id+'_name').value = title;
				jQuery('#'+fc_select_tag_element_id+'_edit').removeClass('hidden');
				jQuery('#'+fc_select_tag_element_id+'_clear').removeClass('hidden');
				$('sbox-btn-close').fireEvent('click');
			}
			";
			$doc->addScriptDeclaration($js);
			JHtml::_('behavior.modal', 'a.modal');
		}

		$link = 'index.php?option=com_flexicontent&amp;view=tagelement&amp;tmpl=component';
		
		$rel = '{handler: \'iframe\', size: {x:((window.getSize().x<1100)?window.getSize().x-100:1000), y: window.getSize().y-100}}';
		return '
		<span class="input-append">
			<input type="text" id="'.$element_id.'_name" placeholder="'.JText::_( 'FLEXI_FORM_SELECT',true ).'" value="'.$title.'" '.$required_param.' readonly="readonly" />
			<a class="modal btn btn-success hasTooltip" onclick="fc_select_tag_element_id=\''.$element_id.'\'" href="'.$link.'" rel="'.$rel.'" title="'.JText::_( 'FLEXI_SELECT_TAG' ).'" >
				'.JText::_( 'FLEXI_SELECT' ).'
			</a>
			<button id="' .$element_id. '_clear" class="btn'.($value ? '' : ' hidden').'" onclick="return qfClearSelectedTag(\''.$element_id . '\')">
				<span class="icon-remove"></span>
				'.JText::_( 'FLEXI_CLEAR' ).'
			</button>
		</span>
		<input type="hidden" id="'.$element_id.'" name="'.$fieldname.'" '.$required_param.' value="'.$value.'" />
		';
	}
}
?>