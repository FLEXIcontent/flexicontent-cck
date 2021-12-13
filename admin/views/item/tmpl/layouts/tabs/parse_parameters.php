<?php
defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

class FcFormLayoutParameters
{
	/**
	 * Parse form's layout configuration (of parameters in XML file of layout)
	 *
	 * @param   object             $item               The item record
	 * @param   array of objects   $fields             The array of fields (objects)
	 * @param   object             $params             The (component + type) parameters
	 * @param   array of objects   $coreprops_fields   The coreprops fields (objects) ... that could be used to place core form elements via fields manager
	 * @param   array of strings   $via_core_field     The field names of core form elements ...  that can use core field in fields manager
	 * @param   array of strings   $via_core_prop      The field names of core form elements ...  that can use "core property" field type in fields manager
	 *
	 * @return  array    The configuration
	 *
	 * @since   4.0
	 */
	function createPlacementConf( $item, & $fields, $params, $coreprops_fields, $via_core_field, $via_core_prop)
	{
		$app    = JFactory::getApplication();
		$CFGsfx = $app->isClient('site') ? '' : '_be';

		$placeable_fields = array_merge($via_core_field, $via_core_prop);


		/**
		 * Get placement of CORE properties / fields
		 */
		// Important: Get fman before other so that foreach will examine it 1st when we will check for duplicate fields
		$tab_fields['fman']  = $params->get('form_tabs_fieldsman'. $CFGsfx,'');

		$tab_fields['above']  = $params->get('form_tabs_above'. $CFGsfx,    'title, alias, category, lang, type, state, access, disable_comments, notify_subscribers, notify_owner');
		$tab_fields['tab01']  = $params->get('form_tab01_fields'. $CFGsfx,  'text');
		$tab_fields['tab02']  = $params->get('form_tab02_fields'. $CFGsfx,  'fields_manager');
		$tab_fields['tab02a'] = $params->get('form_tab02a_fields'. $CFGsfx, 'jimages, jurls');
		$tab_fields['tab03']  = $params->get('form_tab03_fields'. $CFGsfx,  'categories, tags, lang_assocs, perms');
		$tab_fields['tab04']  = $params->get('form_tab04_fields'. $CFGsfx,  'timezone_info, created, created_by, created_by_alias, publish_up, publish_down, modified, modified_by, item_screen');
		$tab_fields['tab05']  = $params->get('form_tab05_fields'. $CFGsfx,  'metadata, seoconf');
		$tab_fields['tab06']  = $params->get('form_tab06_fields'. $CFGsfx,  'display_params');
		$tab_fields['tab07']  = $params->get('form_tab07_fields'. $CFGsfx,  'layout_selection, layout_params');
		$tab_fields['tab08']  = $params->get('form_tab08_fields'. $CFGsfx,  'versions');
		$tab_fields['below']  = $params->get('form_tabs_below'. $CFGsfx,    '');


		/**
		 * Fix aliases, also replacing field types with field names
		 */
		foreach($tab_fields as $tab_name => $field_list)
		{
			$field_list = str_replace('createdby', 'created_by', $field_list);
			$field_list = str_replace('modifiedby', 'modified_by', $field_list);
			$field_list = str_replace('createdby_alias', 'created_by_alias', $field_list);
			$field_list = str_replace('maintext', 'text', $field_list);
			$field_list = str_replace('type', 'document_type', $field_list);
			$field_list = str_replace('language', 'lang_assocs', $field_list);  // LEGACY value ...
			$tab_fields[$tab_name] = $field_list;
		}

		// Split field lists
		$all_tab_fields = array();    // All placements
		$placeViaLayout = array();    // Not placed not by fields manager
		foreach($tab_fields as $i => $field_list)
		{
			// Split field names and flip the created sub-array to make field names be the indexes of the sub-array
			$tab_fields[$i] = is_array($tab_fields[$i])
				? $tab_fields[$i]
				: (trim($tab_fields[$i]) === '' || trim($tab_fields[$i]) === '_skip_'
					? array()
					: preg_split("/[\s]*,[\s]*/", $field_list )
				);
			$tab_fields[$i] = array_flip($tab_fields[$i]);

			// Find all field names of the placed fields, we can use this to find non-placed fields
			if ($i !== 'fman')
			{
				foreach ($tab_fields[$i] as $field_name => $ignore)
				{
					$all_tab_fields[$field_name] = 1;
				}
			}
		}

		// Find fields missing from configuration, and place them below the tabs
		foreach($placeable_fields as $fn => $i)
		{
			if ( !isset($all_tab_fields[$fn]) )
			{
				$tab_fields['below'][$fn] = 1;
			}
		}

		// get TAB titles and TAB icon classes
		$_titles = $params->get('form_tab_titles'. $CFGsfx, '1:FLEXI_DESCRIPTION, 2:__TYPE_NAME__, 3:FLEXI_ASSIGNMENTS, 4:FLEXI_PUBLISHING, 5:FLEXI_META_SEO, 6:FLEXI_DISPLAYING, 7:FLEXI_TEMPLATE');
		$_icons  = $params->get('form_tab_icons'. $CFGsfx,  '1:icon-file-2, 2:icon-signup, 3:icon-tree-2, 4:icon-calendar, 5:icon-bookmark, 6:icon-eye-open, 7:icon-palette');

		// Create title of the custom fields default TAB (field manager TAB)
		if ($item->type_id)
		{
			$_str = JText::_('FLEXI_DETAILS');
			$_str = StringHelper::strtoupper(StringHelper::substr($_str, 0, 1)) . StringHelper::substr($_str, 1);

			$types_arr = flexicontent_html::getTypesList();
			$type_lbl = isset($types_arr[$item->type_id]) ? $types_arr[$item->type_id]->name : '';
			$type_lbl = $type_lbl ? JText::_($type_lbl) : JText::_('FLEXI_CONTENT_TYPE');
			$type_lbl = $type_lbl .' ('. $_str .')';
		}
		else
		{
			$type_lbl = JText::_('FLEXI_TYPE_NOT_DEFINED');
		}

		// Also assign it for usage by layout
		$this->type_lbl = $type_lbl;


		// Split titles of default tabs and language filter the titles
		$tab_titles = array();

		if ($_titles !== '_ignore_')
		{
			$_tmp = preg_split("/[\s]*,[\s]*/", $_titles);
			foreach($_tmp as $_data)
			{
				$arr = preg_split("/[\s]*:[\s]*/", $_data);
				if (count($arr) !== 2)
				{
					$app->enqueueMessage('Failed parse TAB titles configuration for: ' . $_titles, 'warning');
					$tab_titles = array();
					break;
				}

				$tab_titles['tab0'.$arr[0]] = $arr[1] === '__TYPE_NAME__' ? $type_lbl : JText::_($arr[1]);
			}
		}

		// Split icon classes of default tabs
		$tab_icocss = array();

		if ($_icons !== '_ignore_')
		{
			$_tmp = preg_split("/[\s]*,[\s]*/", $_icons);
			foreach($_tmp as $_data)
			{
				$arr = preg_split("/[\s]*:[\s]*/", $_data);
				if (count($arr) !== 2)
				{
					$app->enqueueMessage('Failed parse TAB icon classes configuration for: ' . $_icons, 'warning');
					$tab_icocss = array();
					break;
				}

				$tab_icocss['tab0'.$arr[0]] = $arr[1];
			}
		}


		// 4. find if some fields are missing placement field
		$coreprop_missing = array();
		foreach($via_core_prop as $fn => $i)
		{
			// -EITHER- configured to be shown at default position -OR-
			if ( isset($tab_fields['fman'][$fn])  &&  !isset($coreprops_fields[$fn]) ) {
				$coreprop_missing[$fn] = true;
				unset($tab_fields['fman'][$fn]);
				$tab_fields['below'][$fn] = 1;
			}
		}

		$placementConf['via_core_field']   = $via_core_field;
		$placementConf['via_core_prop']    = $via_core_prop;
		$placementConf['placeable_fields'] = $placeable_fields;
		$placementConf['tab_fields']       = $tab_fields;
		$placementConf['tab_titles']       = $tab_titles;
		$placementConf['tab_icocss']       = $tab_icocss;
		$placementConf['all_tab_fields']   = $all_tab_fields;
		$placementConf['coreprop_missing'] = $coreprop_missing;

		$placementConf['placeViaFman']     = $placementConf['tab_fields']['fman'];
		$placementConf['placeViaLayout']   = $placeViaLayout;

		return $placementConf;
	}
}
