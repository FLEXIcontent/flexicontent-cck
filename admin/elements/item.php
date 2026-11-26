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

use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Form\FormField;

// Load the helper classes
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once(JPATH_ROOT.'/components/com_flexicontent/classes/flexicontent.helper.php');



/**
 * Renders an Item element
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */

class JFormFieldItem extends FormField
{
	/**
	 * Element name
	 *
	 * @access	protected
	 * @var		string
	 */
	var	$type = 'Item';

	function getInput()
	{
		$allowEdit		= ((string) $this->element['edit'] == 'true') ? true : false;
		$allowClear		= ((string) $this->element['clear'] != 'false') ? true : false;

		$paramset = $this->element['paramset'];   // optional custom group for the form element instead of e.g. 'params'
		$required = $this->element['required'] && $this->element['required']!='false' ? true : false;
		$class    = (string) $this->element['class'];
		$hint     = (string) $this->element['hint'] ?: 'FLEXI_FORM_SELECT';

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

		require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/tables/flexicontent_items.php';
		$item = new flexicontent_items(\Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class));
		if ($this->value)
		{
			$item->load($this->value);
			$title = $item->title;
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
			function fcClearSelectedItem(element)
			{
				let el = typeof element === 'string' ? document.getElementById(element) : element.closest('.fcselectitem_box').nextElementSibling;
				let box = el.previousElementSibling;
				el.value = ''; FlexiForm.change(el);
				box.querySelector('.fcselectitem_name').value = '';
				box.querySelector('.fcselectitem_name').setAttribute('placeholder', '".Text::_( 'FLEXI_FORM_SELECT',true )."');
				box.querySelector('.fcselectitem_clear')?.classList.add('hidden');
				box.querySelector('.fcselectitem_edit')?.classList.add('hidden');
				return false;
			};

			var fc_select_element_id;
			function fcSelectItem(id, cid, title, selected_item, assign_all_assocs)
			{
				let el = document.getElementById(fc_select_element_id);
				let box = el.previousElementSibling;

				el.value = id;
				FlexiForm.change(el);
				box.querySelector('.fcselectitem_name').value = title;
				" .
				($allowEdit  ? "
					box.querySelector('.fcselectitem_edit')?.classList.remove('hidden');" : '') .
				($allowClear ? "
					box.querySelector('.fcselectitem_clear')?.classList.remove('hidden');" :'') .
				"

				if (!!assign_all_assocs)
				{
					try {
						const assocs_json = selected_item.getAttribute('data-assocs').replace(/_QUOTE_/g, '\"');
						var assocs = JSON.parse(assocs_json);
					}
					catch(err) {
						var assocs = [];
					}

					let assoc_element_id = fc_select_element_id.substring(0, fc_select_element_id.length - 6);

					for (const lang in assocs)
					{
						var assoc = assocs[lang];
						var element_id_lang = assoc_element_id + '_' + lang;
						var assoc_input = document.getElementById(element_id_lang);
						if (!!assoc_input)
						{
							document.getElementById(element_id_lang).value = assoc.id;
							document.getElementById(element_id_lang + '_name').value = assoc.title;
							" .
							($allowEdit  ? "
								document.getElementById(element_id_lang + '_edit')?.classList.remove('hidden');" : '') .
							($allowClear ? "
								document.getElementById(element_id_lang + '_clear')?.classList.remove('hidden');" :'') .
							"
						}
						else {}
					}
				}

				if (cid_field =	document.getElementById('jform_request_cid')) cid_field.value = cid;
				fc_field_dialog_handle_record.dialog('close');
			}
			";
			\Joomla\CMS\Factory::getApplication()->getDocument()->addScriptDeclaration($js);
			flexicontent_html::loadFramework('flexi-lib');
		}

		$app    = Factory::getApplication();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'CMD');
		$view   = $jinput->get('view', '', 'CMD');

		$assocs_id  = 0;
		$language   = $this->element['language'];

		if ($language && $option=='com_flexicontent' && $view=='item')
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

		$link = 'index.php?option=com_flexicontent&amp;view=itemelement&amp;tmpl=component';
		$link .= $this->element['type_id'] ? '&amp;type_id=' . $this->element['type_id'] : '';
		$link .= $this->element['created_by'] ? '&amp;created_by=' . $this->element['created_by'] : '';
		$link .= $language ? '&amp;item_lang=' . $language : '';
		$link .= ($language && $assocs_id) ? '&amp;assocs_id=' . $assocs_id : '';

		//$rel = '{handler: \'iframe\', size: {x:((window.getSize().x<1100)?window.getSize().x-100:1000), y: window.getSize().y-100}}';
		$_select = Text::_( 'FLEXI_SELECT_ITEM', true);
		return '
		<span class="input-append fcselectitem_box '.$class.'">
			<input type="text" class="fcselectitem_name" placeholder="'.Text::_( $hint, true ).'" value="'.htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8').'" '.$required_param.' readonly="readonly" />
			'. //<a class="modal btn hasTooltip" onclick="fc_select_element_id=\''.$element_id.'\'" href="'.$link.'" rel="'.$rel.'" title="'.$_select.'">
			'<a class="btn hasTooltip" onclick="fc_select_element_id = this.closest(\'.fcselectitem_box\').nextElementSibling.id; var url = this.href; window.fc_field_dialog_handle_record = fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, 0, {title:\''.$_select.'\'}); return false;" href="'.$link.'" title="'.$_select.'" >
				'.Text::_( 'FLEXI_FORM_SELECT' ).'
			</a>
			'.($allowEdit ? '
			<a class="fcselectitem_edit btn ' . ($this->value ? '' : ' hidden') . ' hasTooltip" href="index.php?option=com_flexicontent&amp;task=items.edit&amp;cid=' . $this->value . '" target="_blank" title="'.Text::_( 'FLEXI_EDIT_ITEM' ).'">
				<span class="icon-edit"></span>
			</a>
			' : '').'
			'.($allowClear ? '
			<button class="fcselectitem_clear btn'.($this->value ? '' : ' hidden').' hasTooltip" onclick="return fcClearSelectedItem(this)" title="'.Text::_('FLEXI_CLEAR', true).'">
				<span class="icon-remove"></span>
			</button>
			' : '').'
		</span>
		<input type="text" id="'.$element_id.'" name="'.$fieldname.'" value="'.$this->value.'" class="fcselectitem_value fc_hidden_value" style="display:none;" />
		';
	}
}
