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

defined('_JEXEC') or die;

require_once('fcbase.php');


/**
 * Fcfields HTML helper
 *
 * @since  3.3
 */
abstract class JHtmlFcfields extends JHtmlFcbase
{
	static $tooltip_class  = FLEXI_J40GE ? 'hasTooltip' : 'hasTooltip';
	static $btn_sm_class   = FLEXI_J40GE ? 'btn btn-sm' : 'btn btn-small';
	static $btn_iv_class   = FLEXI_J40GE ? 'btn-dark' : 'btn-inverse';
	static $btn_mbar_class = FLEXI_J40GE ? 'btn-outline-info' : '';
	static $ctrl = 'fields';
	static $name = 'field';
	static $title_propname = 'label';
	static $state_propname = 'published';
	static $layout_type = null;
	static $translateable_props = array('label');


	/**
	 * Create an icon having information of field participating in a group
	 *
	 * @param   object   $row        The row
	 * @param   int      $i          Row number
	 *
	 * @return  string       HTML code of button, linking to the fieldgroup
	 */
	public static function in_group($row, $i)
	{
		if (isset($row->grouping_field) && $row->parameters->get('use_ingroup'))
		{
			$link = 'index.php?option=com_flexicontent&amp;task=fields.edit&amp;view=field&amp;id='. $row->grouping_field->id;
			$onclick = '';

			$title = in_array(static::$title_propname, static::$translateable_props)
				? JText::_($row->grouping_field->{static::$title_propname})
				: $row->grouping_field->{static::$title_propname};

			$icon_tip       = flexicontent_html::getToolTip('FLEXI_FIELD_GROUPED_INSIDE', $row->grouping_field->label, 1, 1);
			$icon_class     = 'icon-grid-2';
			$disabled_class = '';
			$disabled_btn   = '';
		}
		elseif ($row->field_type === 'fieldgroup')
		{
			$link = '';
			//$onclick = 'var url = jQuery(this).attr(\'data-href\'); var the_dialog = fc_showAsDialog(jQuery(\'#fieldgroup_fields_list_' . $row->id . '\'), null, null, null, {\'title\': Joomla.JText._(\'FLEXI_EDIT\')})';
			$onclick = 'jQuery(\'#fieldgroup_fields_list_' . $row->id . '\').slideToggle()';

			$icon_tip       = flexicontent_html::getToolTip('This field is a fieldgroup', '', 1, 1);
			$icon_class     = 'icon-stack';
			$disabled_class = '';
			$disabled_btn   = '';
		}
		else
		{
			$link = '';
			$onclick = '';

			$icon_tip       = flexicontent_html::getToolTip('FLEXI_FIELD_NOT_GROUPED', '', 1, 1);
			$icon_class     = 'icon-grid-2';
			$disabled_class = 'disabled';
			$disabled_btn   = '<span class="fc_icon_disabled"></span>';
		}

		$attribs = ''
			. ' class="fc-preview-btn ntxt ' . $disabled_class . ' ' .  static::$btn_mbar_class . ' ' . static::$btn_sm_class . ' ' . static::$tooltip_class . '"'
			. ' title="' . $icon_tip . '" '
			. ($link ? ' href="' . $link .'" ' : '')
			. ($link ? ' target="_blank" ' : '')
			. ($onclick ? ' data-href="' . $link .'" ' : '')
			. ($onclick ? ' onclick="' . $onclick .'" ' : '')
		;

		$tag = $link || $onclick ? 'a' : 'span';

		return '
		<' . $tag . ' ' . $attribs . '>
			' . $disabled_btn . '
			<span class="' . $icon_class . '"></span>
		</' . $tag . '> ';
	}


	/**
	 * Create an icon having information of field having a master field (cascading after master)
	 *
	 * @param   object   $row        The row
	 * @param   int      $i          Row number
	 *
	 * @return  string       HTML code of button, linking to the master field
	 */
	public static function cascade_after($row, $i)
	{
		if (isset($row->master_field) && $row->parameters->get('cascade_after'))
		{
			$link = 'index.php?option=com_flexicontent&amp;task=fields.edit&amp;view=field&amp;id='. $row->master_field->id;

			$title = in_array(static::$title_propname, static::$translateable_props)
				? JText::_($row->master_field->{static::$title_propname})
				: $row->master_field->{static::$title_propname};

			$icon_tip       = flexicontent_html::getToolTip('FLEXI_VALGRP_DEPENDS_ON_MASTER_FIELD', htmlspecialchars($title, ENT_QUOTES, 'UTF-8'), 1, 1);
			$icon_class     = 'icon-tree';
			$disabled_class = '';
			$disabled_btn   = '';
		}
		else
		{
			$is_cascadeable = in_array($row->field_type, array('select', 'selectmultiple', 'radio', 'radioimage', 'checkbox', 'checkboximage'));
			$link = '';
			
			$icon_tip       = flexicontent_html::getToolTip(
				(!$is_cascadeable ? 'FLEXI_VALGRP_MASTER_FIELD' : ''),
				(!$is_cascadeable ? 'FLEXI_VALGRP_MASTER_FIELD_NOT_APPICABLE' : 'FLEXI_VALGRP_NO_MASTER_FIELD'),
			1, 1);
			$icon_class     = !$is_cascadeable ? 'icon-tree' : 'icon-tree';
			$disabled_class = 'disabled';
			$disabled_btn   = '<span class="fc_icon_disabled' /*. (!$is_cascadeable ? ' fc_icon_na' : '')*/ . '"></span>';
		}

		$attribs = ''
			. ' class="fc-preview-btn ntxt ' . $disabled_class . ' ' .  static::$btn_mbar_class . ' ' . static::$btn_sm_class . ' ' . static::$tooltip_class . '"'
			. ' title="' . $icon_tip . '" '
			. ($link ? ' href="' . $link .'" ' : '')
			. ($link ? ' target="_blank" ' : '');

		$tag = $link ? 'a' : 'span';

		return '
		<' . $tag . ' ' . $attribs . '>
			' . $disabled_btn . '
			<span class="' . $icon_class . '"></span>
		</' . $tag . '> ';
	}


	/**
	 * Create an icon having information of field having a master field (cascading after master)
	 *
	 * @param   object   $row        The row
	 * @param   int      $i          Row number
	 *
	 * @return  string       HTML code
	 */
	public static function fieldtype_info($row, $i)
	{
		$row_type = $row->custom_title ?: $row->type;
		
		switch ($row->field_type)
		{
			case 'fieldgroup':
				$field_links = array();

				foreach ($row->grouped_fields as $field)
				{
					$field_links[] = static::edit_link($field, $i, $row->canEdit, $config = array(
						'nolinkPrefix' => '<span class="icon-edit"></span>',
						'useModal' => (object) array(
							'title' =>'FLEXI_EDIT',
							'onloadfunc' =>'fc_edit_fcfield_modal_load',
							'onclosefunc' =>'fc_edit_fcfield_modal_close',
						),
					));
				}

				return '
				<span class="btn btn-primary" onclick="jQuery(\'#fieldgroup_fields_list_' . $row->id . '\').slideToggle();">'
					. ucfirst($row_type) . ' ' . JText::_('FLEXI_FIELDS') . '
				</span>
				<div id="fieldgroup_fields_list_' . $row->id . '" style="display: none; position: absolute; width: 200px;">
					<div class="alert alert-info" style="margin: 0; padding: 4px 12px;">
						' . implode('<br>', $field_links) . '
					</div>
				</div>';
				break;

			case 'custom_form_html':
				return '<strong>' . $row_type . '</strong><br/>
					<small>- ' . $row->parameters->get('marker_type') . ' -</small>';
				break;

			case 'coreprops':
				return '<strong>' . $row_type . '</strong><br/>
					<small>- ' . $row->parameters->get('props_type') . ' -</small>';
				break;

			default:
				if (!empty($row->custom_desc))
				{
					return '<b>' . $row_type . '</b><br/>' .
						($row->custom_desc ? '<span style="color: darkcyan; font-weight: bold;">' . $row->custom_desc . '</span>' : '<small>- ' . $friendly_name .' -' . ' -</small>');
				}
				else
				{
					$friendly_name = str_replace('FLEXIcontent - ', '', $row->friendly ?? '');
					return
						'<strong>' . $row_type . '</strong>' .
						($row->iscore ? '' : '<br/><small>- ' . $friendly_name . ' -</small>');
				}
				break;
		}	
	}


	/**
	 * Create an icon for showing and toggling a search / filter property
	 *
	 * @param   object   $row        The row
	 * @param   int      $i          Row number
	 *
	 * @return  string       HTML code of the icon
	 */
	public static function search_filter_icons($row, $i)
	{
		//check which properties are supported by current field
		$ft_support = FlexicontentFields::getPropertySupport($row->field_type, $row->iscore);

		$supportsearch    = $ft_support->supportsearch;
		$supportfilter    = $ft_support->supportfilter;
		$supportadvsearch = $ft_support->supportadvsearch;
		$supportadvfilter = $ft_support->supportadvfilter;

		static $flexi_yes, $flexi_no, $flexi_nosupport, $flexi_rebuild, $flexi_toggle;

		if ($flexi_yes === null)
		{
			$flexi_yes       = JText::_('FLEXI_YES');
			$flexi_no        = JText::_('FLEXI_NO');
			$flexi_nosupport = JText::_('FLEXI_PROPERTY_NOT_SUPPORTED', true);
			$flexi_rebuild   = JText::_('FLEXI_REBUILD_SEARCH_INDEX', true);
			$flexi_toggle    = JText::_('FLEXI_CLICK_TO_TOGGLE', true);
		}
		
		if ($row->issearch==0 || $row->issearch==1 || !$supportsearch)
		{
			$issearch = ($row->issearch && $supportsearch) ? "icon-search" : "icon-cancel";
			$issearch_tip = ($row->issearch && $supportsearch) ? $flexi_yes.", ".$flexi_toggle : ($supportsearch ? $flexi_no.", ".$flexi_toggle : $flexi_nosupport);
		}
		else
		{
			$issearch = $row->issearch==-1 ? "icon-power-cord fc-icon-red" : "icon-power-cord fc-icon-green";
			$issearch_tip = ($row->issearch==2 ? $flexi_yes : $flexi_no) .", ".$flexi_toggle.", ". $flexi_rebuild;
		}

		if ($row->isfilter==0 || $row->isfilter==1 || !$supportfilter)
		{
			$isfilter = ($row->isfilter && $supportfilter) ? "icon-filter" : "icon-cancel";
			$isfilter_tip = ($row->isfilter && $supportfilter) ? $flexi_yes.", ".$flexi_toggle : ($supportfilter ? $flexi_no.", ".$flexi_toggle : $flexi_nosupport);
		}
		else
		{
			$isfilter = $row->isfilter==-1 ? "icon-power-cord fc-icon-red" : "icon-power-cord fc-icon-green";
			$isfilter_tip = ($row->isfilter==2 ? $flexi_yes : $flexi_no) .", ".$flexi_toggle.", ". $flexi_rebuild;
		}

		if ($row->isadvsearch==0 || $row->isadvsearch==1 || !$supportadvsearch)
		{
			$isadvsearch = ($row->isadvsearch && $supportadvsearch) ? "icon-search" : "icon-cancel";
			$isadvsearch_tip = ($row->isadvsearch && $supportadvsearch) ? $flexi_yes.", ".$flexi_toggle : ($supportadvsearch ? $flexi_no.", ".$flexi_toggle : $flexi_nosupport);
		}
		else
		{
			$isadvsearch = $row->isadvsearch==-1 ? "icon-power-cord fc-icon-red" : "icon-power-cord fc-icon-green";
			$isadvsearch_tip = ($row->isadvsearch==2 ? $flexi_yes : $flexi_no) .", ".$flexi_toggle.", ". $flexi_rebuild;
		}

		if ($row->isadvfilter==0 || $row->isadvfilter==1 || !$supportadvfilter)
		{
			$isadvfilter = ($row->isadvfilter && $supportadvfilter) ? "icon-filter" : "icon-cancel";
			$isadvfilter_tip = ($row->isadvfilter && $supportadvfilter) ? $flexi_yes : ($supportadvfilter ? $flexi_no.", ".$flexi_toggle : $flexi_nosupport);
		}
		else
		{
			$isadvfilter = $row->isadvfilter==-1 ? "icon-power-cord fc-icon-red" : "icon-power-cord fc-icon-green";
			$isadvfilter_tip = ($row->isadvfilter==2 ? $flexi_yes : $flexi_no) .", ".$flexi_toggle.", ". $flexi_rebuild;
		}

		$html = array();


		
		$icon = $supportsearch
			? '<span class="' . $issearch . ' hasTooltip" title="' . $issearch_tip . '"></span>'
			: '<span class="icon-minus" style="color: transparent" title="NA"></span>';

		if ($supportsearch && $row->canEdit)
		{
			$icon	= '
			<a title="Toggle property" class="btn btn-small" onclick="document.adminForm.propname.value=\'issearch\'; return Joomla.listItemTask(\'cb' . $i . '\',\'toggleprop\')" href="javascript:void(0);"
				>' . $icon . '</a>';
		}

		$html['search'] = $icon;


		$icon = $supportfilter
			? '<span class="' . $isfilter . ' hasTooltip" title="' . $isfilter_tip . '"></span>'
			: '<span class="icon-minus" style="color: transparent" title="NA"></span>';

		if ($supportfilter && $row->canEdit)
		{
			$icon	= '
			<a title="Toggle property" class="btn btn-small" onclick="document.adminForm.propname.value=\'isfilter\'; return Joomla.listItemTask(\'cb' . $i . '\',\'toggleprop\')" href="javascript:void(0);"
				>' . $icon . '</a>';
		}
		
		$html['filter'] = $icon;


		$icon = $supportadvsearch
			? '<span class="' . $isadvsearch . ' hasTooltip" title="' . $isadvsearch_tip . '"></span>'
			: '<span class="icon-minus" style="color: transparent" title="NA"></span>';

		if ($supportadvsearch && $row->canEdit)
		{
			$icon	= '
			<a title="Toggle property" class="btn btn-small" onclick="document.adminForm.propname.value=\'isadvsearch\'; return Joomla.listItemTask(\'cb' . $i . '\',\'toggleprop\')" href="javascript:void(0);"
				>' . $icon . '</a>';
		}

		$html['advsearch'] = $icon;

		$icon = $supportadvfilter
			? '<span class="' . $isadvfilter . ' hasTooltip" title="' . $isadvfilter_tip . '"></span>'
			: '<span class="icon-minus" style="color: transparent" title="NA"></span>';

		if ($supportadvfilter && $row->canEdit)
		{
			$icon	= '
			<a title="Toggle property" class="btn btn-small" onclick="document.adminForm.propname.value=\'isadvfilter\'; return Joomla.listItemTask(\'cb' . $i . '\',\'toggleprop\')" href="javascript:void(0);"
				>' . $icon . '</a>';
		}

		$html['advfilter'] = $icon;

		return $html;
	}
}
