<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // \Joomla\CMS\HTML\Helpers\Select
jimport('joomla.form.field');  // \Joomla\CMS\Form\FormField

//jimport('joomla.form.helper'); // \Joomla\CMS\Form\FormHelper
//\Joomla\CMS\Form\FormHelper::loadFieldClass('...');   // \Joomla\CMS\Form\FormField...

// Load the helper classes
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

\Joomla\CMS\Table\Table::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');


/**
 * Renders an FC Tag element
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */

class JFormFieldFctag extends \Joomla\CMS\Form\FormField
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

		$item = \Joomla\CMS\Table\Table::getInstance('flexicontent_tags', '');
		if ($this->value)
		{
			$item->load($this->value);
			$title = $item->name;
		}
		else
		{
			$title = '';
			$this->value = '';  // Clear possible invalid value
		}

		// HTML tag parameters for required field
		$required_param = $required ? ' required="required" class="required" aria-required="true" ' : '';

		static $js_added = false;
		if (!$js_added)
		{
			$js_added = true;
			$js = "
			function qfClearSelectedTag(element_id)
			{
				jQuery('#'+element_id+'_name').val('');
				jQuery('#'+element_id+'_name').attr('placeholder', '".\Joomla\CMS\Language\Text::_( 'FLEXI_FORM_SELECT',true )."');
				jQuery('#'+element_id).val('');
				jQuery('#'+element_id + '_clear').addClass('hidden');
				jQuery('#'+element_id + '_edit').addClass('hidden');
				return false;
			};

			var fc_select_tag_element_id;
			function qfSelectTag(id, title)
			{
				document.getElementById(fc_select_tag_element_id).value = id;
				document.getElementById(fc_select_tag_element_id+'_name').value = title;"
			.($allowEdit  ? "
				jQuery('#'+fc_select_tag_element_id+'_edit').removeClass('hidden');" : '')
			.($allowClear ? "
				jQuery('#'+fc_select_tag_element_id+'_clear').removeClass('hidden');" : '')
			."
				//$('sbox-btn-close').fireEvent('click');
				fc_field_dialog_handle_record.dialog('close');
			}
			";
			\Joomla\CMS\Factory::getApplication()->getDocument()->addScriptDeclaration($js);
			flexicontent_html::loadFramework('flexi-lib');
		}

		$app    = \Joomla\CMS\Factory::getApplication();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'CMD');
		$view   = $jinput->get('view', '', 'CMD');

		$assocs_id  = 0;
		$language   = $this->element['language'];

		if ($language && $option=='com_flexicontent' && $view=='category')
		{
			$id = $jinput->get('id', array(0), 'array');
			$id = ArrayHelper::toInteger($id);
			$assocs_id = (int) $id[0];

			if (!$assocs_id)
			{
				$cid = $jinput->get('cid', array(0), 'array');
				$cid = ArrayHelper::toInteger($cid);
				$assocs_id = (int) $cid[0];
			}
		}

		$link = 'index.php?option=com_flexicontent&amp;view=tagelement&amp;tmpl=component';
		$link .= $this->element['created_by'] ? '&amp;created_by=' . $this->element['created_by'] : '';
		$link .= $language ? '&amp;item_lang=' . $language : '';
		$link .= ($language && $assocs_id) ? '&amp;assocs_id=' . $assocs_id : '';

		//$rel = '{handler: \'iframe\', size: {x:((window.getSize().x<1100)?window.getSize().x-100:1000), y: window.getSize().y-100}}';
		$_select = \Joomla\CMS\Language\Text::_('FLEXI_SELECT_TAG', true);
		return '
		<span class="input-append">
			<input type="text" id="'.$element_id.'_name" placeholder="'.\Joomla\CMS\Language\Text::_( 'FLEXI_FORM_SELECT',true ).'" value="'.$title.'" '.$required_param.' readonly="readonly" />
			'. //<a class="modal btn hasTooltip" onclick="fc_select_tag_element_id=\''.$element_id.'\'" href="'.$link.'" rel="'.$rel.'" title="'.$_select.'">
			'<a class="btn hasTooltip" onclick="fc_select_tag_element_id=\''.$element_id.'\'; var url = jQuery(this).attr(\'href\'); window.fc_field_dialog_handle_record = fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, 0, {title:\''.$_select.'\'}); return false;" href="'.$link.'" title="'.$_select.'" >
				'.\Joomla\CMS\Language\Text::_( 'FLEXI_FORM_SELECT' ).'
			</a>
			'.($allowEdit ? '
			<a id="' .$element_id. '_edit" class="btn ' . ($this->value ? '' : ' hidden') . ' hasTooltip" href="index.php?option=com_flexicontent&amp;task=tag.edit&amp;cid=' . $this->value . '" target="_blank" title="'.\Joomla\CMS\Language\Text::_( 'FLEXI_EDIT_TAG' ).'">
				<span class="icon-edit"></span>' . \Joomla\CMS\Language\Text::_('FLEXI_FORM_EDIT') . '
			</a>
			' : '').'
			'.($allowClear ? '
			<button id="' .$element_id. '_clear" class="btn'.($this->value ? '' : ' hidden').'" onclick="return qfClearSelectedTag(\''.$element_id . '\')">
				<span class="icon-remove"></span>
				'.\Joomla\CMS\Language\Text::_('FLEXI_CLEAR').'
			</button>
			' : '').'
		</span>
		<input type="text" id="'.$element_id.'" name="'.$fieldname.'" value="'.$this->value.'" class="fc_hidden_value" />
		';
	}
}
