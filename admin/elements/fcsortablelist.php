<?php
/**
 * @version 1.5 stable $Id: filters.php 1829 2014-01-05 22:18:17Z ggppdk $
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
jimport('joomla.form.helper'); // JFormHelper
JFormHelper::loadFieldClass('list');   // JFormFieldList


/**
 * Renders a filter element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldFcSortableList extends JFormField
{
	/**
	 * JFormField type
	 * @access	protected
	 * @var		string
	 */
	
	protected $type = 'FcSortableList';
	
	// Record list
	protected static $records = array('1'=>'example1', '2'=>'example2');
	protected static $cnt = 0;  // to do use this
	
	public function __construct($form = null)
	{
		static::$cnt = static::$cnt + 1;
		parent::__construct( $form );
	}
	
	function add_css_js()
	{
		JHtml::_('behavior.framework', true);
		flexicontent_html::loadJQuery();

		$document = JFactory::getDocument();
		$js = "
			var sorttable_fcrecord_lists = '';
			var fc_field_dialog_handle_fcrecord_list;


			function fcrecord_del_sortable_element(obj)
			{
				var list_el = jQuery(obj).closest('li');
				var list = list_el.closest('ul');

				list_el.slideUp(300, function(){
					jQuery(this).remove();
					fcrecord_store_values( list );
				});
			}


			function fcrecord_toggle_textarea_edit(container)
			{
				var value_area = container.find('textarea');
				if (value_area.attr('readonly'))
					value_area.removeAttr('readonly');
				else
					value_area.attr('readonly', 'readonly');
			}
			
			function fcrecord_direct_edit(list, in_modal)
			{
				var value_element_id = list.data('value_element_id');
				var value_area = jQuery('#'+value_element_id);
				value_area.removeAttr('readonly');
				
				in_modal = typeof in_modal !== 'undefined' ? in_modal : 0;
				if (in_modal)
				{
					fc_field_dialog_handle_fcrecord_list = fc_showAsDialog(value_area.parent(), null, null, fcrecord_toggle_textarea_edit, {'visibleOnClose': 1, 'title': '".JText::_('FLEXI_ADD')."'});
					list.parent().hide();
				}
				else
				{
					value_area.css({'height': '', 'width': ''});
					value_area.parent().css({'height': '', 'width': ''}).slideDown();
					list.parent().slideUp();
				}
			}


			function fcrecord_toggle_details_btns(btn, current_visibility)
			{
				if (typeof btn === 'undefined' || !btn) return;

				if (current_visibility)
				{
					btn.parent().find('.fcrecords_show_btn').hide();
					btn.parent().find('.fcrecords_hide_btn').show();
				}
				else
				{
					btn.parent().find('.fcrecords_show_btn').show();
					btn.parent().find('.fcrecords_hide_btn').hide();
				}
			}


			function fcrecord_ui_edit(list, in_modal, btn_show, btn_hide)
			{
				// Make sure list is empty and hidden, till we have repopulated it
				list.hide();
				list.empty();

				// Display message about unused columns
				list.parent().find('.fcrec_general_msg').html('').hide();
				var unused_labels = list.parent().find('.fcrecord_label.fcrec_unused:not(.fcrec_cascaded_col)');
				unused_labels.each(function() {
					var label = jQuery(this).html();
					list.parent().find('.fcrec_general_msg').append(label + ' ".JText::_('FLEXI_INDEXED_FIELD_UNUSED_COL_DISABLED', true)."' + '<br/>').show();
				});

				var value_element_id = list.data('value_element_id');
				jQuery('#'+value_element_id).attr('readonly', 'readonly').css('height', '64px');

				in_modal = typeof in_modal !== 'undefined' ? in_modal : 0;
				if (in_modal)
				{
					fc_field_dialog_handle_fcrecord_list = fc_showAsDialog(list.parent(), null, null, null, {'title': '".JText::_('FLEXI_EDIT', true)."'});
					fcrecord_toggle_details_btns(btn_show, 0);
				}
				else
				{
					if (list.parent().is(':visible'))
					{
						fcrecord_toggle_details_btns(btn_show, 0);
						list.parent().slideUp();
						return;
					}
					fcrecord_toggle_details_btns(btn_show, 1);
					list.parent().css('height', '').show();
				}

				var master_fieldname = list.data('master_fieldname');
				master_fieldname = master_fieldname ? jQuery('#jform_attribs_'+master_fieldname) : jQuery();
				var master_field_id = master_fieldname.length && parseInt(master_fieldname.val()) ? parseInt(master_fieldname.val()) : 0;
				
				if (!master_field_id)
				{
					list.parent().find('.fcrec_cascaded_msg').html('".JText::_('FLEXI_INDEXED_FIELD_VALGRP_COL_DISABLED', true)."').show();
					list.parent().find('.fcrec_cascaded_col').addClass('fcrec_unused');
					list.data('master_elements', null);
				}
				else
				{
					list.parent().find('.fcrec_cascaded_msg').html('').hide();
					list.parent().find('.fcrec_cascaded_col').removeClass('fcrec_unused');

					list.after( jQuery('<img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\">') );
					jQuery.ajax({
						async: false,
						type: \"GET\",
						url: 'index.php?option=com_flexicontent&task=fields.getIndexedFieldJSON&format=raw',
						data: {
							field_id: master_field_id
						},
						success: function(str) {
							var master_elements = (str ? JSON.parse(str) : false);
							list.data('master_elements', master_elements);
							list.next().remove();
						},
						error: function(str) {
							list.next().remove();
						}
					});
				}

				list.show();
				fcrecord_populate_list_elements(list);
			}


			function fcrecord_populate_list_elements(list)
			{
				var record_sep = list.data('record_sep');
				var value_element_id = list.data('value_element_id');

				var values = jQuery('#'+value_element_id).val().split( new RegExp('\\\\s*'+record_sep+'\\\\s*') );

				// Make sure list is empty and re-add the values
				list.empty();
				jQuery(values).each(function(key, value)
				{
					fcrecord_add_sortable_element_ext(list, 1, value, 0);
				});
				list.children('li').slideDown();
			}


			function fcrecord_add_sortable_element_ext(addAt, placement, record_value, shown)
			{
				if (addAt.prop('tagName') == 'UL')
				{
					var list = addAt;
					list_el = null;
				}
				else {
					var list = addAt.closest('ul');
					var list_el = addAt;
				}

				var cascaded_prop = list.data('cascaded_prop');
				cascaded_prop = cascaded_prop ? parseInt(cascaded_prop) : -1;

				var master_elements = list.data('master_elements');
				var master_select = '';
				var master_values = {};
				if (master_elements)
				{
					master_select = '<select class=\"fcrecord_prop\" style=\"__width__\" onchange=\"fcrecord_store_values(jQuery(this).closest(\\'ul\\'));\" >';
					master_select += '<option value=\"\">-</option>';
					master_select += '__current_value__';
					for (var i = 0; i < master_elements.length; i++)
					{
						master_select += '<option value=\"'+master_elements[i].value+'\">'+master_elements[i].text+'</option>';
						master_values[master_elements[i].value] = master_elements;
					}
					master_select += '</select>';
				}

				record_value = typeof record_value !== 'undefined' ? record_value : '';
				shown = typeof shown !== 'undefined' ? shown : 1;

				var record_sep  = list.data('record_sep');
				var props_sep   = list.data('props_sep');

				var prop_widths = list.data('prop_widths');
				prop_widths = prop_widths.split( new RegExp('\\\\s*,\\\\s*') );
				
				var props_used = list.data('props_used');
				props_used = props_used.split( new RegExp('\\\\s*,\\\\s*') );
				var _unused_col = ' readonly=\"readonly\" placeholder=\"".JText::_('FLEXI_NA')."\" ';

				var add_after  = list.data('add_after');
				var add_before = list.data('add_before');
				var is_elements = list.data('is_elements');

				var props = record_value.split( new RegExp('\\\\s*'+props_sep+'\\\\s*') );
				var props_html = '';
				jQuery(props_used).each(function(key, in_use)
				{
					if (key > props_used - 1) return;
					value = key > props.length - 1 ?
						'' :  props[key];
					var _unused = !parseInt(props_used[key]) || (key==cascaded_prop && !master_elements);
					var _width = 'width:'+prop_widths[key]+'!important';
					
					if (key!=cascaded_prop || !master_select)
						props_html += '<input type=\"text\" value=\"'+value+'\" class=\"fcrecord_prop '+(_unused ? ' fcrec_unused' : '')+'\" onblur=\"fcrecord_store_values(jQuery(this).closest(\\'ul\\'));\" style=\"'+_width+'\" '+(_unused ? _unused_col : '')+'/>';
					else
					{
						var select = master_select.replace('__width__', _width);
						select = value.length && !master_values.hasOwnProperty(value) ?
							select.replace('__current_value__', '<option value=\"'+value+'\">'+value+' [CURRENT]</option>') :
							select = select.replace('__current_value__', '') ;
						props_html += select;
					}
				});

				var lbl = 'empty';
				var newrec = jQuery(
					'<li class=\"fcrecord\">'+
						(is_elements ?
							props_html :
							'<span class=\"fcprop_box\">'+lbl+'</span>'
						)+
						'<span class=\"delfield_handle\" title=\"".JText::_('FLEXI_REMOVE')."\" onclick=\"fcrecord_del_sortable_element(this);\"></span>'+
						(add_after ?  '<span class=\"addfield_handle fc_after\"  title=\"".JText::_('FLEXI_ADD')."\" onclick=\"fcrecord_add_sortable_element_ext(jQuery(this).parent(), 1);\"></span>' : '')+
						(add_before ? '<span class=\"addfield_handle fc_before\" title=\"".JText::_('FLEXI_ADD')."\" onclick=\"fcrecord_add_sortable_element_ext(jQuery(this).parent(), 0);\"></span>' : '')+
						(is_elements ? '<span class=\"ordfield_handle\" title=\"".JText::_('FLEXI_ORDER')."\"></span>' : '')+
					'</li>'
				).hide();
				
				if (master_select && props.length > cascaded_prop)
				{
					if ( props[cascaded_prop] ) newrec.find('select').val(props[cascaded_prop]);
				}

				if (list_el)
					placement ?
						list_el.after(newrec) :
						list_el.before(newrec) ;
				else
					list.append(newrec);

				if (shown)
					newrec.slideDown();
			}


			function fcrecord_add_sortable_element(selector)
			{
				var selobj = jQuery(selector);
				var tagid  = selobj.attr('id').replace('_selector','');
				var list = jQuery('#sortable-' + tagid);

				var val = selobj.val();
				if (!val) return;
				var lbl = selobj.find('option:selected').text();
				list.append(
					'<li class=\"fcrecord\" data-value=\"'+val+'\">'+
						'<span class=\"fcprop_box\">'+lbl+'</span>'+
						'<span title=\"".JText::_('FLEXI_REMOVE')."\" onclick=\"fcrecord_del_sortable_element(this);\" class=\"delfield_handle\"></span>'+
					'</li>'
				);

				var field_list = jQuery('#'+tagid).val();
				field_list += field_list ? ','+val : val;
				jQuery('#'+tagid).val(field_list);
				if (selobj.hasClass('use_select2_lib')) {
					selobj.select2('val', '');
					selobj.prev().find('.select2-choice').removeClass('fc_highlight');
				} else
					selobj.prop('selectedIndex',0);
			}


			function fcrecord_store_values(list)
			{
				var values = [];
				if (list.data('is_elements'))
				{
					var props_sep = list.data('props_sep');

					list.children('li').each(function()
					{
						var v;
						var empty_cnt = 0;
						var props = [];
						jQuery(this).find('.fcrecord_prop').each(function()
						{
							v = jQuery(this).val().trim();
							if (v.length && v != '_NA_')
							{
								props.push(v);
								empty_cnt = 0;
							}
							else {
								props.push('_NA_');
								empty_cnt++;
							}
						});
						// Trim empty values at the ends
						for (var i=0; i < empty_cnt; i++) props.pop();

						values.push(props.join(props_sep));
					});
				}
				else
				{
					list.children('li').each(function()
					{
						values.push( jQuery(this).data('value') );
					});
				}

				var record_sep = list.data('record_sep');
				var value_element_id = list.data('value_element_id');

				list.data('is_elements') ?
					jQuery('#'+value_element_id).val( values.join(record_sep+'\\n') ) :
					jQuery('#'+value_element_id).val( values.join(record_sep) ) ;
			}


			// Trigger value storing function on list re-ordering
			jQuery(document).ready(function()
			{
				jQuery( sorttable_fcrecord_lists ).each(function(index, value)
				{
					if (!jQuery(this).data('is_elements')) fcrecord_store_values(jQuery(this));
				});

				jQuery( sorttable_fcrecord_lists ).sortable({
					connectWith: sorttable_fcrecord_lists,
					update: function(event, ui) {
						if (ui.sender)
							fcrecord_store_values(jQuery(ui.sender));
						else
							fcrecord_store_values(jQuery(ui.item).parent());
					}
				});

			});
		";
		if ($js) $document->addScriptDeclaration($js);
	}
	
	
	function getInput()
	{
		static $js_css_added = null;
		if ($js_css_added===null)
		{
			$this->add_css_js();
			$js_css_added = true;
		}
		
		$doc	= JFactory::getDocument();
		$db		= JFactory::getDBO();
		$attributes = $this->element->attributes();

		$iselements = $attributes->subtype =='elements';
		$issortable = $attributes->subtype =='sortable' || $iselements;
		$ismultiple = $attributes->subtype =='multiple';
		
		// Disable multiple display if field is sortable, because multiple value will go inside the sortable container
		if ($issortable) $ismultiple = 0;
		
		// Set record separator to comma if this is not set
		$record_sep = $attributes->record_sep ? $attributes->record_sep : ',';
		$props_sep  = $attributes->props_sep;
		
		$values = !is_array($this->value) && !strlen($this->value) ? array() : $this->value;
		
		// Split values if not already an array
		if ( !is_array($values) )
		{
			$values = preg_split("/\s*".$record_sep."\s*/", $values);
		}
		//echo "<pre>"; print_r($values); echo "</pre>";


		$fieldname  = $this->name;
		$element_id = $this->id;
		
		// A drop-down selector to select new values
		if ($iselements)
		{
			// Not needed
		}
		else if ($issortable)
		{
			// Not real element, real element will be a hidden input
			$fieldname_selector = $fieldname.'[selector]';
			$element_id_selector = $element_id.'_selector';
		}
		else if ($ismultiple)
		{
			// Real multi-value element
			$fieldname_selector = $fieldname.'[]';
			$element_id_selector = $element_id;
		}
		else
		{
			// Real single-value element
			$fieldname_selector = $fieldname;
			$element_id_selector = $element_id;
		}


		$attribs = '';
		$selector_classes = array();
		
		if (!$iselements)
		{
			$options = array();
			foreach(static::$records as $i => $v)
			{
				$option = new stdClass;
				$options[$i] = $option;
				$option->text = JText::_($v);
				$option->value = $i;
			}
			//print_r($options); exit;
			
			if ( $ismultiple )
			{
				$size = (int) $attributes->size ? (int) $attributes->size : 8;
				$attribs .= ' multiple="multiple" size="'.$size.'" ';
			}
			else if ($issortable) {
				array_unshift($options, JHTML::_('select.option', '', JText::_($attributes->prompt_label ? $attributes->prompt_label : 'FLEXI_ADD_MORE')));
			}
			else {  // Single drop down select
				array_unshift($options, JHTML::_('select.option', '', JText::_($attributes->prompt_label ? $attributes->prompt_label : 'FLEXI_SELECT')));
			}
			$selector_classes[] = 'use_select2_lib';
			if (!empty($selector_classes)) $attribs .= ' class ="'.implode(' ', $selector_classes).'"';
			
			$html = $sorter_html = '';
			if ($attributes->onchange) {
				$attribs .= ' onchange="'.$attributes->onchange.'"';
			}
			
			else if ($attributes->appendtofield) {
				$attribs .= ' onchange="fcrecord_add2list(\'jform_attribs_'.$attributes->appendtofield.'\', this);"';
			}
			
			if ($issortable)
			{
				$attribs .= ' onchange="fcrecord_add_sortable_element(this);"';
			}
		}
		
		if ($issortable)
		{
			$classes = 'records_container' . ($attributes->class ? ' '.$attributes->class : '');

			$skip_initial_list = strlen($attributes->skip_initial_list) ? (int) $attributes->skip_initial_list : ($iselements ? 1 : 0);

			$add_end    = strlen($attributes->add_end)    ? (int) $attributes->add_end : 0;
			$add_before = strlen($attributes->add_before) ? (int) $attributes->add_before : $iselements;
			$add_after  = strlen($attributes->add_after)  ? (int) $attributes->add_after  : $iselements;
			
			$edit_inline = strlen($attributes->edit_inline) ? (int) $attributes->edit_inline : 0;
			$edit_popup  = strlen($attributes->edit_popup)  ? (int) $attributes->edit_popup  : $iselements;
			$raw_inline  = strlen($attributes->raw_inline)  ? (int) $attributes->raw_inline  : 0;
			$raw_popup   = strlen($attributes->raw_popup)   ? (int) $attributes->raw_popup   : $iselements;

			$cascaded_prop  = strlen($attributes->cascaded_prop) ? (int) $attributes->cascaded_prop : -1;

			$list_attrs =
				($add_after  ? ' data-add_after="1" ' : '') .
				($add_before  ? ' data-add_before="1" ' : '') .
				($iselements ? ' data-is_elements="1" ' : '') .
				($record_sep ? ' data-record_sep="'.$record_sep.'" ' : '') .
				($props_sep ? ' data-props_sep="'.$props_sep.'" ' : '') .
				($attributes->prop_widths ? ' data-prop_widths="'.$attributes->prop_widths.'" ' : '') .
				($attributes->props_used ? ' data-props_used="'.$attributes->props_used.'" ' : '') .
				($attributes->master_fieldname ? ' data-master_fieldname="'.$attributes->master_fieldname.'" ' : '') .
				($attributes->cascaded_prop ? ' data-cascaded_prop="'.$attributes->cascaded_prop.'" ' : '') .
				' data-value_element_id="'.$element_id.'" ';

			$props_header = array();
			if ($iselements)
			{
				$props_used = $attributes->props_used ? preg_split("/\s*,\s*/", $attributes->props_used) : array();

				$prop_widths = $attributes->prop_widths ? preg_split("/\s*,\s*/", $attributes->prop_widths) : array();
				$prop_lbls = $attributes->prop_lbls ? preg_split("/\s*,\s*/", $attributes->prop_lbls) : array();

				if (count($prop_lbls)) foreach($prop_lbls as $i => $prop_lbl)
				{
					$_unused = empty($props_used[$i]);

					$_classes = $_unused ? ' fcrec_unused' : '';
					$_styles  = isset($prop_widths[$i]) ? ' width:'.$prop_widths[$i].'!important;' : '';

					$props_header[] = '<span class="fcrecord_label label '.($cascaded_prop==$i ? ' fcrec_cascaded_col' : '').$_classes.'" style="'.$_styles.'">'.JText::_($prop_lbl).'</span>';
				}
			}

			$sortable_id = 'sortable-'.$element_id;

			$sorter_html  = '
			'.($iselements ? '
				<div class="'.($attributes->editbtns_class ? ' '.$attributes->editbtns_class : '').'">
					<span class="btn-group">'.
						($edit_inline ? '
						<span class="btn fcrecords_show_btn" title="'.JText::_('FLEXI_SHOW').'" onclick="fcrecord_ui_edit(jQuery(\'#'.$sortable_id.'\'), 0, jQuery(this), jQuery(this).next());">
							<span class="icon-downarrow"></span>'.JText::_('FLEXI_EDIT_PROPERTIES').'
						</span>
						<span class="btn fcrecords_hide_btn" title="'.JText::_('FLEXI_HIDE').'" onclick="fcrecord_ui_edit(jQuery(\'#'.$sortable_id.'\'), 0, jQuery(this).prev(), jQuery(this));" style="display:none;">
							<span class="icon-uparrow"></span>'.JText::_('FLEXI_HIDE_PROPERTIES').'
						</span>
						' : '').
						($edit_popup ? '
						<span class="btn" title="'.JText::_('FLEXI_POPUP').'" onclick="fcrecord_ui_edit(jQuery(\'#'.$sortable_id.'\'), 1); fcrecord_toggle_details_btns(jQuery(this), 0);">
							<span class="icon-pencil" style="font-size: 80%;"></span>'.JText::_('FLEXI_EDIT').'
						</span>
						' : '').
						($raw_inline ? '
						<span class="btn" title="'.JText::_('FLEXI_POPUP').'" onclick="fcrecord_direct_edit(jQuery(\'#'.$sortable_id.'\'), 0); fcrecord_toggle_details_btns(jQuery(this), 0);">
							<span class="icon-paragraph-justify"></span>'.JText::_('FLEXI_RAW_EDIT').'
						</span>
						' : '').
						($raw_popup ? '
						<span class="btn" title="'.JText::_('FLEXI_POPUP').' '.JText::_('FLEXI_RAW_EDIT').'" onclick="fcrecord_direct_edit(jQuery(\'#'.$sortable_id.'\'), 1); fcrecord_toggle_details_btns(jQuery(this), 0);">
							<span class="'.($raw_inline ? 'icon-expand-2' : 'icon-paragraph-justify').'"></span>'.($raw_inline ? JText::_('FLEXI_POPUP').' ' : '').JText::_('FLEXI_RAW_EDIT').'
						</span>
						' : '').'
					</span>
					<div class="fcclear"></div>
				</div>
				<div>
					<textarea name="'.$fieldname.'" id="'.$element_id.'" class="fcrecords_textarea'.($attributes->value_area_class ? ' '.$attributes->value_area_class : '').'" style="min-height: 64px;">'.$this->value.'</textarea>
					<p class="fcrecords_desc alert alert-info">'.JText::_($attributes->description).'</p>
				</div>'
			: '
				<input type="hidden" value="'.implode(',', $values).'" id="'.$element_id.'" name="'.$fieldname.'" />'
			).'
			<div class="fcclear"></div>

			<div class="'.($attributes->editbtns_class ? ' '.$attributes->editbtns_class : '').'">
				<div class="'.$classes.'" style="'.($skip_initial_list ? 'display: none;' : '').'">
					<div class="fcrec_cascaded_msg alert alert-notice" style="display: none;"></div>
					<div class="fcrec_general_msg alert alert-info" style="display: none;"></div>
					'.($props_header ? '<span class="fcrecord_header"> '.implode('', $props_header).'</span>' : '').'
					<ul id="'.$sortable_id.'" class="fcrecords" '.$list_attrs.'>';

			$_unused_col = ' readonly="readonly" placeholder="'.JText::_('FLEXI_NA').'" ';
			
			if (!$skip_initial_list) foreach($values as $val)
			{
				if ($iselements)
				{
					$_v = & $val;
					$props = $props_sep ? preg_split("/\s*".$props_sep."\s*/", $_v) : array($_v);
					$props_html = array();
					foreach ($prop_lbls as $i => $prop_lbl)
					{
						$prop_v = isset($props[$i]) ? $props[$i] : '';
						$_unused = empty($props_used[$i]);

						$_classes = $_unused ? ' fcrec_unused' : '';
						$_styles  = isset($prop_widths[$i]) ? ' width:'.$prop_widths[$i].'!important;' : '';

						$props_html[] = '<input type="text"  value="'.htmlspecialchars( $prop_v, ENT_COMPAT, 'UTF-8' ).'" class="fcrecord_prop '.$_classes.'" onblur="fcrecord_store_values(jQuery(this).closest(\'ul\'));" style="'.$_styles.'" '.($_unused ? $_unused_col : '').'/>';
					}
				}
				else {
					if( !isset(static::$records[$val]) ) continue;
					$_v = & $options[$val]->text;
				}

				$sorter_html .= '
						<li class="fcrecord" data-value="'.$val.'">
							'.($iselements ?
								implode(' ', $props_html) :
								'<span class="fcprop_box">'.$_v.'</span>'
							).'
							<span class="delfield_handle" title="'.JText::_('FLEXI_REMOVE').'" onclick="fcrecord_del_sortable_element(this);"></span>
							'.($add_after  ? '<span class="addfield_handle fc_after"  title="'.JText::_('FLEXI_ADD').'" onclick="fcrecord_add_sortable_element_ext(jQuery(this).parent(), 1);"></span>' : '').'
							'.($add_before ? '<span class="addfield_handle fc_before" title="'.JText::_('FLEXI_ADD').'" onclick="fcrecord_add_sortable_element_ext(jQuery(this).parent(), 0);"></span>' : '').'
							'.($iselements ? '<span class="ordfield_handle" title="'.JText::_('FLEXI_MOVE').'"></span>' : '').'
						</li>';
			}
			$sorter_html .= '
					</ul>
				'.($add_end ? '<span class="btn" title="'.JText::_('FLEXI_ADD').'" onclick="fcrecord_add_sortable_element_ext(jQuery(\'#'.$sortable_id.'\'), 1);" style="float:right;"><span class="icon-new"></span>'.JText::_('FLEXI_ADD').'</span>' : '').'
				</div>
			</div>
			<div class="fcclear"></div>';
			
			$js = "
				if (sorttable_fcrecord_lists)
					sorttable_fcrecord_lists = sorttable_fcrecord_lists + ',#".$sortable_id."';
				else
					sorttable_fcrecord_lists = '#".$sortable_id."';
			";
			if ($js) JFactory::getDocument()->addScriptDeclaration($js);
		}

		return
			($iselements ? '' : JHTML::_('select.genericlist', $options, $fieldname_selector, $attribs, 'value', 'text', ($issortable ? array() : $values), $element_id_selector) )
			.$sorter_html;
	}
}
