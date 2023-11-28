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

// Load the helper classes
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // JHtmlSelect
jimport('joomla.form.field');  // JFormField

//jimport('joomla.form.helper'); // JFormHelper
//JFormHelper::loadFieldClass('...');   // JFormField...

/**
* Renders a multiple select element
*
*/
class JFormFieldFCFieldWrapper extends JFormField
{
	protected $_options;
	protected $_inherited;

	static $css_js_added = null;
	static $fcform_item = null;

	/**
	* Element name
	*
	* @access       protected
	* @var          string
	*/
	var	$type = 'FCFieldWrapper';

	function getInput()
	{
		//$attributes = get_object_vars($this->element->attributes());
		//$attributes = $attributes['@attributes'];

		$values = is_array($this->value) ? $this->value : explode("|", $this->value);

		$fieldname	= $this->name;
		$element_id = $this->id;

		$name = $this->element['name'];
		$control_name = str_replace($name, '', $element_id);

		$app = JFactory::getApplication();
		$option = $app->input->get('option');
		if (!empty($this->element['item_id']))
		{
			$unique_tmp_itemid = $this->element['item_id'];
		}
		else
		{
			$unique_tmp_itemid = $app->getUserState($option.'.edit.item.unique_tmp_itemid');
			$unique_tmp_itemid = $unique_tmp_itemid ? $unique_tmp_itemid : date('_Y_m_d_h_i_s_', time()) . uniqid(true);
		}
		$unique_tmp_itemid = substr($unique_tmp_itemid, 0, 1000);
		$app->input->set('unique_tmp_itemid', $unique_tmp_itemid);
		$app->setUserState($option.'.edit.item.unique_tmp_itemid', $unique_tmp_itemid);  // Save temporary unique item id into the session

		// Create containers for the already rendered the fields
		$html = $this->renderFieldsForm(static::$fcform_item);
		$html = $html ?: '<span class="alert alert-info">' . JText::_( 'FLEXI_NO_FIELDS_TO_TYPE' ) .' </span>';
		return '</div></div>
		<div id="flexicontent" class="flexicontent">'
			. $html . '
			<input type="hidden" name="unique_tmp_itemid" value="' . $unique_tmp_itemid . '" />
		</div>
		<div><div>';
	}

	function getLabel()
	{
		// Valid HTML ... you can not have for LABEL attribute for fieldset
		return '';
	}


	/*
	* Create editing HTML of a field
	*/
	function renderFieldsForm($item)
	{
		$noplugin = '<div class="fc-mssg-inline fc-warning" style="margin:0 2px 6px 2px; max-width: unset;">'.JText::_( 'FLEXI_PLEASE_PUBLISH_THIS_PLUGIN' ).'</div>';
		$hide_ifempty_fields = array('fcloadmodule', 'fcpagenav', 'toolbar', 'comments');
		$row_k = 0;

		$lbl_class = ' ' . $item->parameters->get(JFactory::getApplication()->isClient('administrator') ? 'form_lbl_class_be' : 'form_lbl_class_fe');
		$tip_class = ' hasTooltip';

		$FC_jfields_html['images'] = '<span class="alert alert-info">Edit in \'Image and links\' TABs</span>';
		$FC_jfields_html['urls'] = '<span class="alert alert-info">Edit in \'Image and links\' TABs</span>';
		ob_start();
		foreach ($item->fields as $field)
		{
			if (
				// SKIP backend hidden fields from this listing
				($field->iscore && $field->field_type!='maintext')   ||   $field->parameters->get('backend_hidden')  ||   in_array($field->formhidden, array(2,3))   ||

				// Skip hide-if-empty fields from this listing
				( empty($field->html) && ($field->formhidden==4 || in_array($field->field_type, $hide_ifempty_fields)) )
			) continue;

			// check to SKIP (hide) field e.g. description field ('maintext'), alias field etc
			if ( $item->tparams->get('hide_'.$field->field_type) ) continue;

			$not_in_tabs = "";
			if ($field->field_type=='custom_form_html')
			{
				echo $field->html;
				continue;
			}


			else if ($field->field_type=='coreprops')
			{
				// not used in backend (yet?)
				continue;
			}


			else if ($field->field_type=='maintext')
			{
				// placed in separate TAB
				continue;
			}


			else if ($field->field_type=='image')
			{
				if ($field->parameters->get('image_source')==-1)
				{
					$replace_txt = !empty($FC_jfields_html['images']) ? $FC_jfields_html['images'] : '<span class="alert alert-warning fc-small fc-iblock">'.JText::_('FLEXI_ENABLE_INTRO_FULL_IMAGES_IN_TYPE_CONFIGURATION').'</span>';
					unset($FC_jfields_html['images']);
					$field->html = str_replace('_INTRO_FULL_IMAGES_HTML_', $replace_txt, $field->html);
				}
			}


			else if ($field->field_type=='weblink')
			{
				if ($field->parameters->get('link_source')==-1)
				{
					$replace_txt = !empty($FC_jfields_html['urls']) ? $FC_jfields_html['urls'] : '<span class="alert alert-warning">'.JText::_('FLEXI_ENABLE_LINKS_IN_TYPE_CONFIGURATION').'</span>';
					unset($FC_jfields_html['urls']);
					$field->html = str_replace('_JOOMLA_ARTICLE_LINKS_HTML_', $replace_txt, $field->html);
				}
			}


			// field has tooltip
			$edithelp = $field->edithelp ? $field->edithelp : 1;
			//$label_class = "pull-left label-fcinner label-toplevel";
			$label_class = "control";
			if ( $field->description && ($edithelp==1 || $edithelp==2) )
			{
				$label_attrs = 'class="' . $tip_class . ($edithelp==2 ? ' fc_tooltip_icon' : '') . $lbl_class . ' ' . $label_class . '" title="'.flexicontent_html::getToolTip(null, $field->description, 0, 1).'"';
			}
			else
			{
				$label_attrs = 'class="' . $lbl_class . ' ' . $label_class . '"';
			}

			// Some fields may force a container width ?
			$display_label_form = $field->parameters->get('display_label_form', 1);
			$row_k = 1 - $row_k;
			$full_width = $display_label_form==0 || $display_label_form==2 || $display_label_form==-1;
			$width = $field->parameters->get('container_width', ($full_width ? '100%!important;' : false) );
			$container_width = empty($width) ? '' : 'width:' .$width. ($width != (int)$width ? 'px!important;' : '');
			//$container_class = "fcfield_row".$row_k." container_fcfield container_fcfield_id_".$field->id." container_fcfield_name_".$field->name;
			$container_class = "controls container_fcfield";
			?>

		<div class="control-group">
			<div class="control-label">
				<!--span class="label-fcouter" id="label_outer_fcfield_<?php echo $field->id; ?>" style="<?php echo $display_label_form < 1 ? 'display:none;' : '' ?>" -->
				<label id="label_fcfield_<?php echo $field->id; ?>" data-for="<?php echo 'custom_'.$field->name;?>" <?php echo $label_attrs;?> >
					<?php echo $field->label; ?>
				</label>
				<!--/span-->
			</div>

			<div style="<?php echo $container_width; ?>" class="<?php echo $container_class;?>" id="container_fcfield_<?php echo $field->id;?>">
				<?php
				echo ($field->description && $edithelp==3) ? '<div class="alert fc-small fc-iblock">'.$field->description.'</div>' : '';
				echo isset($field->html) ? $field->html : $noplugin;
				?>
			</div>
		</div>

		<?php
		}
		$fields_html = ob_get_contents();
		ob_end_clean();
		return $fields_html;
	}
}