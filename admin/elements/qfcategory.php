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

// Load the helper classes
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');


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

		$paramset = $this->element['paramset'];   // optional custom group for the form element instead of e.g. 'params'
		$required = $this->element['required'] && $this->element['required']!='false' ? true : false;

		if ($paramset)
		{
			$fieldname = "jform[".$paramset."][".$this->element["name"]."]";
			$element_id = "jform_".$paramset."_".$this->element["name"];
		}
		else
		{
			$fieldname  = $this->name;
			$element_id = $this->id;
		}

		$item = JTable::getInstance('flexicontent_categories', '');
		if ($this->value)
		{
			$item->load($this->value);
		}
		else
		{
			$item->title = '';
		}

		// HTML tag parameters for required field
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
			//JHtml::_('behavior.modal', 'a.modal');
			flexicontent_html::loadFramework('flexi-lib');
		}
		
		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'CMD');
		$view   = $jinput->get('view', '', 'CMD');
		
		$assocs_id  = 0;
		$language   = $this->element['language'];
		
		if ($language && $option=='com_flexicontent' && $view=='category')
		{
			$id = $jinput->get('id', array(0), 'array');
			JArrayHelper::toInteger($id);
			$assocs_id = (int) $id[0];
			
			if (!$assocs_id)
			{
				$cid = $jinput->get('cid', array(0), 'array');
				JArrayHelper::toInteger($cid);
				$assocs_id = (int) $cid[0];
			}
		}

		$link = 'index.php?option=com_flexicontent&amp;view=qfcategoryelement&amp;tmpl=component';
		$link .= $this->element['created_by'] ? '&amp;created_by=' . $this->element['created_by'] : '';
		$link .= $language ? '&amp;language=' . $language : '';
		$link .= ($language && $assocs_id) ? '&amp;assocs_id=' . $assocs_id : '';
		
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
			<a id="' .$element_id. '_edit" class="btn ' . ($this->value ? '' : ' hidden') . ' hasTooltip" href="index.php?option=com_flexicontent&amp;task=category.edit&amp;cid=' . $this->value . '" target="_blank" title="'.JText::_( 'FLEXI_EDIT_CATEGORY' ).'">
				<span class="icon-edit"></span>' . JText::_('FLEXI_FORM_EDIT') . '
			</a>' : '').'
			'.($allowClear ? '
			<button id="' .$element_id. '_clear" class="btn'.($this->value ? '' : ' hidden').'" onclick="return fcClearSelectedCategory(\''.$element_id . '\')">
				<span class="icon-remove"></span>
				'.JText::_('FLEXI_CLEAR').'
			</button>' : '').'
		</span>
		<input type="text" id="'.$element_id.'" name="'.$fieldname.'" value="'.$this->value.'" class="fc_hidden_value" />
		';
	}
}
?>
