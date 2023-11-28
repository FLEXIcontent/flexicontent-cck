<?php
/**
 * @version 1.5 stable $Id: fields.php 1683 2013-06-02 07:51:11Z ggppdk $
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

// Load the helper classes
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // JHtmlSelect

jimport('joomla.form.field');  // JFormField
jimport('joomla.form.helper'); // JFormHelper

/**
 * Renders a fields element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldFields extends JFormField
{
	/**
	* Element name
	*
	* @access   protected
	* @var      string
	*/
	protected	$type = 'Fields';


	function getInput()
	{
		static $non_orderable;
		static $js_css_added = null;

		if ($js_css_added === null)
		{
			flexicontent_html::loadJQuery();
			flexicontent_html::loadFramework('flexi-lib-form');
			$js_css_added = true;
		}

		$app  = JFactory::getApplication();
		$doc	= JFactory::getDocument();
		$db		= JFactory::getDbo();
		$cparams = JComponentHelper::getParams('com_flexicontent');

		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];


		/**
		 * Option labels and option values (for the created SELECT form field)
		 */

		$fieldnameastext = (boolean) @ $attributes['fieldnameastext'];
		$groupables = (boolean) @ $attributes['groupables'];
		
		// NOTE: ELSE should always be 'id', otherwise we break compatiblity with all previous FC versions !
		$ovalue = ((boolean) @ $attributes['fieldnameasvalue']) ?
			'name' :
			'id' ;


		/**
		 * Field selection limiting FLAGs
		 */

		$and = '';

		if ((int) $this->element['isnotcore'])
		{
			$and .= ' AND iscore = 0';
		}

		$issearch = (string) $this->element['issearch'];

		if (strlen($issearch) && $issearch !== '*')
		{
			$and .= ' AND issearch='. (int)$issearch;
		}

		$isadvsearch = (string) $this->element['isadvsearch'];

		if (strlen($isadvsearch) && $isadvsearch !== '*')
		{
			$and .= ' AND isadvsearch=' . (int) $isadvsearch;
		}

		$isadvfilter = (string) $this->element['isadvfilter'];

		if (strlen($isadvfilter) && $isadvfilter !== '*')
		{
			$and .= ' AND isadvfilter=' . (int) $isadvfilter;
		}

		// For 'Filters' type not set means value 1 otherwise means any ('*')
		$isfilter = strlen((string) $this->element['isfilter'])
			? (string) $this->element['isfilter']
			: ($this->type === 'Filters' ? 1 : '*');

		if (strlen($isfilter) && $isfilter !== '*')
		{
			$and .= ' AND isfilter=' . (int) $isfilter;
		}

		// For 'Filters' type not set means true otherwise false
		$issortable = strlen(@$attributes['issortable'] ?? '') ?
			(boolean) $attributes['issortable'] :
			($this->type=='Filters' ? true : false);


		/**
		 * INCLUDE/EXCLUDE some field types
		 */

		$field_type = (string) @ $attributes['field_type'];
		if ( $field_type )
		{
			$field_type = preg_split("/[\s]*,[\s]*/", $field_type);
			foreach($field_type as $i => $ft) $field_type_quoted[$i] = $db->Quote($ft);
			$and .= ' AND field_type IN ('. implode(',', $field_type_quoted).')';
		}

		$exclude_field_type = (string) @ $attributes['exclude_field_type'];
		if ( $exclude_field_type )
		{
			$exclude_field_type = preg_split("/[\s]*,[\s]*/", $exclude_field_type);
			foreach($exclude_field_type as $i => $ft) $exclude_field_type_quoted[$i] = $db->Quote($ft);
			$and .= ' AND field_type NOT IN ('. implode(',', $exclude_field_type_quoted).')';
		}

		// Limit to current type
		$type_id_variable = (string) $this->element['type_id_variable'];

		if ($type_id_variable)
		{
			$tid = $app->input->get($type_id_variable);
			$tid = is_array($tid)
				? (int) reset($tid)
				: (int) $tid ;
		}
		else
		{
			$tid = 0;
		}

		if ($tid)
		{
			$and .= ' AND ftr.type_id = ' . (int) $tid;
		}

		// Orderable
		$orderable = (int) $this->element['orderable'];

		if ($orderable && empty($non_orderable))
		{
			$non_orderable1 = 'account_via_submit,authoritems,toolbar,image,custom_form_html,fcpagenav,weblink,email,fcloadmodule,comments';
			$non_orderable2 = trim($cparams->get('non_orderable_types', ''));

			$non_orderable = $non_orderable1.($non_orderable2 ? ','.$non_orderable2 : '');
			$non_orderable = array_unique(preg_split("/[\s]*,[\s]*/", $non_orderable));

			$non_orderable = array_flip( $non_orderable);
			unset($non_orderable['file']);  // always include 'file' fields in the orderable types
			$non_orderable = array_flip( $non_orderable);

			foreach($non_orderable as $i => $ft) $non_orderable_quoted[$i] = $db->Quote($ft);
			$and .= " AND field_type NOT IN (". implode(",", $non_orderable_quoted).")";
		}


		/**
		 * Retrieve field data for DB
		 */

		static $fields_q;
		$query = 'SELECT f.'.$ovalue.' AS value, f.label, f.id, f.name'
			.($groupables ? ', f.attribs' : '')
			.' FROM #__flexicontent_fields AS f '
			.($tid ? ' JOIN #__flexicontent_fields_type_relations AS ftr ON ftr.field_id = f.id' : '')
			.' WHERE published = 1 '
			.$and
			.' ORDER BY label ASC, id ASC'
		;

		if ( !isset($fields_q[$query]) )
		{
			$db->setQuery($query);
			$fields = $db->loadObjectList('id');
			$fields_q[$query] = $fields;
		}
		else $fields = $fields_q[$query];


		// Get only fields that are configured to be in a fieldgroup (we will need to render parameters for this)
		if ($groupables)
		{
			$_fields = array();
			foreach($fields as $field)
			{
				if ( !isset($field->params) )
				{
					$field->params = new JRegistry($field->attribs);
				}
				if ($field->params->get('use_ingroup')) $_fields[$field->id] = $field;
			}
			$fields = $_fields;
		}


		// Handle fields having the same label
		$_keys = array_keys($fields);
		$_total = count($fields);
		$_dupls = array();
		foreach($_keys as $i => $key)
		{
			if ($i == $_total-1) continue;
			if ($fields[$key]->label == $fields[ $_keys[$i+1] ]->label)
			{
				$_dupls[ $key ] = $_dupls[ $_keys[$i+1] ] = 1;
			}
		}


		/**
		 * Values, form field name and id, etc
		 */

		$values = $this->value;
		if ( empty($values) ) {
			$values = array();
		}
		if ( !is_array($values) ) {
			$values = preg_split("/[\s]*[\|,][\s]*/", $values);
		}
		//print_r($values);


		$v2f = array();
		$options = array();
		foreach($fields as $field)
		{
			$option = new stdClass();
			$option->text = JText::_($field->label) . (isset($_dupls[$field->id]) ? ' :: '.$field->name : '');
			$option->value = $field->value;
			$options[] = $option;
			$field->option_text = & $option->text;
			$v2f[$field->value] = $field;
		}


		/**
		 * HTML Tag parameters parameters, and styling
		 */

		if ($issortable)
		{
			$element_id = $this->id.'_selector';
			$fieldname  = $this->name.'[selector]';
			$fieldname_sorter  = $this->name;
			$element_id_sorter =$this->id;
		}
		else
		{
			$fieldname  = $this->name;
			$element_id = $this->id;
		}

		$classes = (string) @ $attributes['class'];
		$classes = $classes ?: (FLEXI_J40GE ? 'form-select' : '');

		$attribs = ' style="float:left;" ';

		if ( @$attributes['multiple']=='multiple' || @$attributes['multiple']=='true' )
		{
			$attribs .= ' multiple="multiple" ';
			$attribs .= (@$attributes['size']) ? ' size="'.$attributes['size'].'" ' : ' size="6" ';
		}

		else
		{
			if ((boolean) @ $attributes['display_useglobal'] && !$issortable)
			{
				array_unshift($options, JHtml::_('select.option', '' , '- '.JText::_('FLEXI_USE_GLOBAL').' -'));
				array_unshift($options, JHtml::_('select.option', '0', '- '.JText::_('FLEXI_NOT_SET').' -'));   // Compatibility with older FC versions
			}

			else
			{
				$custom_prompt = (string) @ $attributes['custom_prompt'];
				$custom_prompt = JText::_($custom_prompt ? $custom_prompt : 'FLEXI_PLEASE_SELECT');
				$custom_value = isset($attributes['custom_value']) ? (string) @ $attributes['custom_value'] : ($issortable ? '' : '0');

				array_unshift($options, JHtml::_('select.option', $custom_value, '- '.$custom_prompt.' -'));
			}
		}

		$sorter_html = $tip_text = '';
		if ($onchange = @$attributes['onchange'])
		{
			$control_name = str_replace($attributes['name'], '', $this->id);
			$onchange = str_replace('{control_name}', $control_name, $onchange);
		}

		elseif ($appendtofield = @$attributes['appendtofield'])
		{
			$appendtofield = 'jform_attribs_'.$appendtofield;
			$onchange = 'fcfield_add2list(\''.$appendtofield.'\', this);';
		}

		if ($issortable)
		{
			$sortable_id = 'sortable-'.$element_id_sorter;

			$onchange .= ' return fcfield_add_sortable_element(this);';
			$classes .= ' records_container fcfields_sorter';
			$sorter_html  = '
			<div class="fcclear"></div>
			<div class="'.$classes.'">
				<ul id="'.$sortable_id.'" class="fcrecords fcfields_list">';

			foreach($values as $val)
			{
				if( !isset($v2f[$val]) ) continue;
				$sorter_html .= '
					<li data-value="field_'.$val.'" class="fcrecord">
						<span class="fcprop_box">'.$v2f[$val]->option_text.'</span>
						<span class="delfield_handle" title="'.JText::_('FLEXI_REMOVE').'" onclick="fcfield_del_sortable_element(this);"></span>
					</li>';
			}
			$sorter_html .= '
				</ul>
				<input type="text" id="'.$element_id_sorter.'" name="'.$fieldname_sorter.'" value="'.implode(',', $values).'" class="fc_hidden_value" />
			</div>
			<div class="fcclear"></div>';

			$js = "";
			if ($js) JFactory::getDocument()->addScriptDeclaration($js);

			$attribs .= ' class="use_select2_lib" ';
			flexicontent_html::loadFramework('select2');
		}
		else
		{
			$attribs .= ' class="'.$classes.'" ';
		}

		if ($onchange)
		{
			$attribs .= ' onchange="'.$onchange.'"';
		}

		if ($inline_tip = @$attributes['inline_tip'])
		{
			$tip_img = @$attributes['tip_img'];
			$tip_img = $tip_img ? $tip_img : 'comments.png';
			$preview_img = @$attributes['preview_img'];
			$preview_img = $preview_img ? $preview_img : '';
			$tip_class = @$attributes['tip_class'];
			$tip_class .= ' hasTooltip';
			$hintmage = JHtml::image ( 'administrator/components/com_flexicontent/assets/images/'.$tip_img, JText::_( 'FLEXI_NOTES' ), ' style="vertical-align:middle; max-height:24px; padding:0px; margin:0 0 0 12px;" ' );
			$previewimage = $preview_img ? JHtml::image ( 'administrator/components/com_flexicontent/assets/images/'.$preview_img, JText::_( 'FLEXI_NOTES' ), ' style="vertical-align:middle; max-height:24px; padding:0px; margin:0 0 0 12px;" ' ) : '';
			$tip_text = '<span class="'.$tip_class.'" style="float:left;" title="'.flexicontent_html::getToolTip(null, $inline_tip, 1, 1).'">'.$hintmage.$previewimage.'</span>';
		}


		/**
		 * Create the field's HTML
		 */

		return ($issortable ? '
		<div class="container_fcfield-inner">
			' : '').

			JHtml::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', ($issortable ? array() : $values), $element_id).'
			'.$tip_text.'
			'.$sorter_html

		.($issortable ? '
		</div>
		' : '');
	}
}