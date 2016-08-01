<?php
/**
 * @version 1.5 stable $Id: qfcategory.php
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
 * Renders an Item element
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */

class JFormFieldQfcategory extends JFormField
{
	/**
	 * Element name
	 *
	 * @access	protected
	 * @var		string
	 */
	var	$type = 'Qfcategory';

	function getInput()
	{
		$allowEdit		= ((string) $this->element['edit'] == 'true') ? true : false;
		$allowClear		= ((string) $this->element['clear'] != 'false') ? true : false;
		
		$attributes = get_object_vars($this->element->attributes());
		$attributes = $attributes['@attributes'];
		//echo "<pre>"; print_r($attributes); exit;
		
		$paramset = isset($attributes['paramset']) ? $attributes['paramset'] : false; // : 'request';
		$required = isset($attributes['required']) ? $attributes['required'] : false;
		
		$value = $this->value;
		if ($paramset) {
			$fieldname = "jform[".$paramset."][".$this->element["name"]."]";
			$element_id = "jform_".$paramset."_".$this->element["name"];
		} else {
			$fieldname  = $this->name;
			$element_id = $this->id;
		}
		
		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
		
		$item = JTable::getInstance('flexicontent_categories', '');
		if ($value) {
			$item->load($value);
		} else {
			$item->title = '';
		}
		
		// J1.6+ does have required field capability, add a HTML tag parameter
		$required_param = $required ? ' required="required" class="required" aria-required="true" ' : '';
		
		static $js_added = false;
		if (!$js_added) {
			$js = "
			function fcClearSelectedCategory(element_id)
			{
				jQuery('#'+element_id+'_name').val('');
				jQuery('#'+element_id+'_name').attr('placeholder', '".JText::_( 'FLEXI_FORM_SELECT',true )."');
				jQuery('#'+element_id).val('');
				jQuery('#'+element_id + '_clear').addClass('hidden');
				jQuery('#'+element_id + '_edit').addClass('hidden');
				return false;
			};
			
			var fc_select_cat_element_id;
			function fcSelectCategory(id, title)
			{
				document.getElementById(fc_select_cat_element_id).value = id;
				document.getElementById(fc_select_cat_element_id+'_name').value = title;"
			.($allowEdit  ? "
				jQuery('#'+fc_select_cat_element_id+'_edit').removeClass('hidden');" : '')
			.($allowClear ? "
				jQuery('#'+fc_select_cat_element_id+'_clear').removeClass('hidden');" :'')
			."
				//$('sbox-btn-close').fireEvent('click');
				fc_field_dialog_handle_record.dialog('close');
			}
			";
			JFactory::getDocument()->addScriptDeclaration($js);
			//JHTML::_('behavior.modal', 'a.modal');
			flexicontent_html::loadFramework('flexi-lib');
		}
		
		$option = JRequest::getVar('option');
		$view   = JRequest::getVar('view');
		
		$assocs_id  = 0;
		$created_by = @$attributes['created_by'];
		$language   = @$attributes['language'];
		
		if ($language && $option=='com_flexicontent' && $view=='category')
		{
			$cid = JRequest::getVar( 'cid', array(0), $hash='default', 'array' );
			JArrayHelper::toInteger($cid, array(0));
			$assocs_id = $cid[0];
			if (!$assocs_id) {
				$id = JRequest::getVar( 'id', array(0), $hash='default', 'array' );
				JArrayHelper::toInteger($id, array(0));
				$assocs_id = $id[0];
			}
		}
		$link = 'index.php?option=com_flexicontent&amp;view=qfcategoryelement&amp;tmpl=component';
		$link .= $created_by ? '&amp;created_by='.$created_by : '';
		$link .= $language ? '&amp;language='.$language : '';
		$link .= ($language && $assocs_id) ? '&amp;assocs_id='.$assocs_id : '';
		
		//$rel = '{handler: \'iframe\', size: {x:((window.getSize().x<1100)?window.getSize().x-100:1000), y: window.getSize().y-100}}';
		$_select = JText::_('FLEXI_SELECT_CATEGORY', true);
		return '
		<span class="input-append">
			<input type="text" id="'.$element_id.'_name" placeholder="'.JText::_( 'FLEXI_FORM_SELECT',true ).'" value="'.$item->title.'" '.$required_param.' readonly="readonly" />
			'. //<a class="modal btn hasTooltip" onclick="fc_select_cat_element_id=\''.$element_id.'\'" href="'.$link.'" rel="'.$rel.'" title="'.$_select.'">
			'<a class="btn hasTooltip" onclick="fc_select_cat_element_id=\''.$element_id.'\'; var url = jQuery(this).attr(\'href\'); window.fc_field_dialog_handle_record = fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, 0, {title:\''.$_select.'\'}); return false;" href="'.$link.'" title="'.$_select.'" >
				'.JText::_( 'FLEXI_FORM_SELECT' ).'
			</a>
			'.($allowEdit ? '
			<a id="' .$element_id. '_edit" class="btn ' . ($value ? '' : ' hidden') . ' hasTooltip" href="index.php?option=com_flexicontent&amp;task=category.edit&amp;cid=' . $value . '" target="_blank" title="'.JText::_( 'FLEXI_EDIT_CATEGORY' ).'">
				<span class="icon-edit"></span>' . JText::_('FLEXI_FORM_EDIT') . '
			</a>' : '').'
			'.($allowClear ? '
			<button id="' .$element_id. '_clear" class="btn'.($value ? '' : ' hidden').'" onclick="return fcClearSelectedCategory(\''.$element_id . '\')">
				<span class="icon-remove"></span>
				'.JText::_('FLEXI_CLEAR').'
			</button>' : '').'
		</span>
		<input type="text" id="'.$element_id.'" name="'.$fieldname.'" value="'.$value.'" class="fc_hidden_value" />
		';
	}
}
?>
