<?php
/**
 * @version 1.5 stable $Id: view.html.php 1959 2014-09-18 00:15:15Z ggppdk $
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

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('legacy.view.legacy');
jimport('joomla.filesystem.file');

/**
 * HTML View class for the Category View
 */
class FlexicontentViewItems extends JViewLegacy
{
	/**
	 * Creates the page's display
	 *
	 * @since 1.0
	 */
	public function display($tpl = null)
	{
		/**
		 * Try to set no limit to PHP executon
		 */
		if (!FLEXIUtilities::funcIsDisabled('set_time_limit'))
		{
			@set_time_limit(0);
		}

		$max_execution_time = ini_get("max_execution_time");
		$_total_runtime     = 0;
		$start_microtime    = microtime(true);
		$item_count         = 0;

		// Initialize framework variables
		$user    = JFactory::getUser();
		$aid     = JAccess::getAuthorisedViewLevels($user->id);
		$app     = JFactory::getApplication();
		$jinput  = $app->input;


		// Get model
		$model  = $this->getModel();

		// Indicate to model (if frontend) to merge menu parameters if menu matches
		$model->mergeMenuParams = !$app->isClient('administrator');


		/**
		 * Get configuration parameters
		 * For backend this is component parameters only
		 * For frontend this category parameters as VIEW's parameters (category parameters are merged parameters in order: layout(template-manager)/component/ancestors-cats/category/author/menu)
		 */
		$cparams = JComponentHelper::getParams('com_flexicontent');
		$params  = $app->isClient('administrator')
			? $cparams
			: $model->getCategory()->parameters;

		// Check if CSV export button is enabled for current view
		$show_csvbutton = $app->isClient('administrator')
			? $params->get('show_csvbutton_be', 0)
			: $params->get('show_csvbutton', 0) ;

		if (!$show_csvbutton)
		{
			die('CSV export not enabled for this view');
		}

		$field_sep  = $cparams->get('csv_export_field_sep', 0);

		// Check if current view is filtered by item type
		$filter_type    = $model->getState('filter_type');
		$csv_header     = $app->isClient('administrator') ? (int) $model->getState('csv_header') : (int) $cparams->get('csv_export_header', 1);
		$csv_raw_export = $app->isClient('administrator') ? (int) $model->getState('csv_raw_export') : 2;
		$csv_all_fields = $app->isClient('administrator') ? (int) $model->getState('csv_all_fields') : 2;
		$err_count      = 0;
		$csv_header     = $csv_header === -1 ? (int) $cparams->get('csv_export_header', 1) : $csv_header;

		if (!$filter_type && $app->isClient('administrator'))
		{
			$app->enqueueMessage(JText::_('FLEXI_CSV_EXPORT_PLEASE_FILTER_BY_TYPE'), 'warning');
			$app->redirect($this->_getSafeReferer());
		}

		if (!$csv_header && $app->isClient('administrator'))
		{
			$app->enqueueMessage(JText::_('Please select Field name or Field label as header row.'), 'warning');
			$err_count++;
		}

		if (!$csv_raw_export && $app->isClient('administrator'))
		{
			$app->enqueueMessage(JText::_('Please select to export values according to field configuration or to export raw values.'), 'warning');
			$err_count++;
		}

		if (!$csv_all_fields && $app->isClient('administrator'))
		{
			$app->enqueueMessage(JText::_('Please select to export all fields or only configured fields'), 'warning');
			$err_count++;
		}

		if ($err_count)
		{
			$app->enqueueMessage(JText::_('(Set this inside filters slider)'), 'warning');
			$app->redirect($this->_getSafeReferer());
		}

		// Map of CORE to item properties
		$core_props = array(

			// Core fields (Having field property 'iscore': 1)
			'title'        => 'title',
			'created_by'   => 'author',
			'modified_by'  => 'modifier',
			'created'      => 'created',
			'modified'     => 'modified',
			'modifiedby'   => 'modifiedby',
			'maintext'     => 'maintext',
			'state'        => 'state',
			'hits'         => 'hits',
			'type'         => 'type',
			'categories'   => 'categories',
			'tags'         => 'tags',

			
			// Core-property fields (Having field type: 'coreprops')
			'id'           => 'id',
			'access'       => 'access',
			'alias'        => 'alias',
			'lang'         => 'language',
			'language'     => 'language',
			'category'     => 'catid',
			'created_by_alias' => 'created_by_alias',
			'publish_up'   =>'publish_up',
			'publish_down' =>'publish_down',
			
		);

		$has_pro    = JPluginHelper::isEnabled($extfolder = 'system', $extname = 'flexisyspro');
		$export_all = $has_pro && $app->isClient('administrator') && $jinput->getCmd('items_set', '') === 'all';

		if ($export_all)
		{
			// Create plugin instance
			$className   = 'plg' . ucfirst($extfolder) . $extname;
			$dispatcher  = JEventDispatcher::getInstance();
			$plg_db_data = JPluginHelper::getPlugin($extfolder, $extname);

			$plg = new $className($dispatcher, array(
				'type'   => $extfolder,
				'name'   => $extname,
				'params' => $plg_db_data->params,
			));

			if (!method_exists($plg, 'getItemsSet'))
			{
				$app->enqueueMessage(JText::_('FLEXI_PRO_VERSION_OUTDATED'), 'warning');
				$app->redirect($this->_getSafeReferer());
			}
		}
		
		
		/**
		 * Get first set of items
		 */
		if ($export_all)
		{
			$items = $plg->getItemsSet($model, $_init = true);
		}
		else
		{
			$limit = $jinput->get('limit', $model->getState('limit'));
			$limit = $limit > 5000 ? 5000 : $limit;
			$jinput->set('limit', $limit);
			$model->setState('limit', $limit);
			$items = $model->getData();
		}

		// Get custom fields and load their values
		$_vars = null;
		FlexicontentFields::getItemFields($items, $_vars, $_view = 'category', $aid);


		/**
		 * Find fields that will be added to CSV export
		 */

		$total_fields = 0;
		$item0        = reset($items);
		
		foreach($item0->fields as $field)
		{
			FlexicontentFields::loadFieldConfig($field, $item0);

			$is_coreprops_form_field = $field->field_type === 'coreprops' && substr($field->name, 0 , 5) === 'form_';
			$include_in_csv_export = (int) $field->parameters->get('include_in_csv_export', 0);
			$include_in_csv_export = $csv_all_fields !== 2 ? $include_in_csv_export : ($is_coreprops_form_field ? 0 : 1);

			if (!$include_in_csv_export)
			{
				continue;
			}

			$total_fields++;
		}

		// Abort if no fields were configured for CSV export
		if ($total_fields === 0)
		{
			$app->enqueueMessage(JText::_('FLEXI_CSV_EXPORT_ZERO_FIELDS_CONFIGURED_FOR_CSV_EXPORT'), 'warning');
			$app->redirect($this->_getSafeReferer());
		}



		/**
		 * 1. Output HTTP HEADERS
		 */

		@ob_end_clean();
		header("Pragma: no-cache");
		header("Cache-Control: no-cache");
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header('Content-Encoding: UTF-8');
		header('Content-type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename=EXPORT-'.rand().'.csv');
		//header("Content-Transfer-Encoding: binary");
		echo "\xEF\xBB\xBF"; // UTF-8 BOM


		/**
		 * 2. Output HEADERS row
		 */
		 
		$delim = '';

		foreach($item0->fields as $field)
		{
			$is_coreprops_form_field = $field->field_type === 'coreprops' && substr($field->name, 0 , 5) === 'form_';
			$include_in_csv_export = (int) $field->parameters->get('include_in_csv_export', 0);
			$include_in_csv_export = $csv_all_fields !== 2 ? $include_in_csv_export : ($is_coreprops_form_field ? 0 : 1);

			if (!$include_in_csv_export)
			{
				continue;
			}

			echo $delim . $this->_encodeCSVField($csv_header === 1 ? $field->label : $field->name);
			$delim = $field_sep;
			$total_fields++;
		}
		echo "\n";


		// Try to create CSV export with all items
		$limitstart = 0;
		$limit      = 2000;
		
		$model->setState('limit', $limit);

		/**
		 * 3. Output data rows
		 */
		while (!empty($items))
		{
			$item_count += count($items);

			foreach($items as $item)
			{
				// Zero unneeded search index text
				$item->search_index = '';

				$delim = '';

				foreach($item0->fields as $field_name => $field)
				{
					$is_coreprops_form_field = $field->field_type === 'coreprops' && substr($field->name, 0 , 5) === 'form_';
					$include_in_csv_export = (int) $field->parameters->get('include_in_csv_export', 0);
					$include_in_csv_export = $csv_all_fields !== 2 ? $include_in_csv_export : ($is_coreprops_form_field ? 0 : 1);

					$csv_strip_html = (int) $field->parameters->get('csv_strip_html', 0);

					if (!$include_in_csv_export)
					{
						continue;
					}

					// Render field's CSV display
					elseif ($include_in_csv_export === 2)
					{
						FlexicontentFields::getFieldDisplay($items, $field->name, $values = null, $method = 'csv_export');
					}

					echo $delim;
					$delim = $field_sep;
					$vals = '';

					// CASE 1: RENDERED value display !
					if ($include_in_csv_export === 2)
					{
						// Check that field created a non-empty 'display' property named: "csv_export"
						$vals = isset($item->onDemandFields[$field->name]->csv_export)
							? $item->onDemandFields[$field->name]->csv_export
							: '';

						// Smart strip HTML tags without cutting the text
						if ($csv_strip_html)
						{
							$vals = flexicontent_html::striptagsandcut($vals);
						}
					}

					// CASE 2: CORE properties (special case), TODO: Implement this as "RENDERED value display" (and make it default output for them ?)
					elseif ($field->iscore && isset($core_props[$field_name]))
					{
						if ($csv_raw_export === 2 && isset($item->$field_name))
						{
							$vals = $item->$field_name;
						}
						else
						{
							$prop = $core_props[$field_name];
							$vals = $item->$prop;
						}
					}

					elseif ($field->field_type === 'coreprops' && $include_in_csv_export === 1)
					{
						$props_type = $field->parameters->get('props_type', '');
						$prop = isset($core_props[$props_type]) ? $core_props[$props_type] : $props_type;
						$vals = isset($item->$prop) ? $item->$prop : '';
					}

					// CASE 3: RAW value display !
					elseif (isset($item->fieldvalues[$field->id]))
					{
						if (is_array(reset($item->fieldvalues[$field->id])))
						{
							$vals = array();

							foreach ($item->fieldvalues[$field->id] as $v)
							{
								$vals = implode(' | ', $v);
							}
						}

						else
						{
							$vals = $item->fieldvalues[$field->id];
						}
					}

					echo $this->_encodeCSVField( is_array($vals) ? implode(', ', $vals ) : $vals );
				}

				echo "\n";
			}

			/**
			 * Get time spent so far and break if near max_executime time
			 */
			$elapsed_microseconds = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			$_total_runtime      += $elapsed_microseconds;
			$start_microtime      = microtime(true);

			if ($max_execution_time && $_total_runtime > 3 * $max_execution_time / 4)
			{
				// Message cannot be set when using jexit() ...
				//$app->enqueueMessage('Exported ' . $item_count . ' items. <br> 3/4 of max execution time ' . $max_execution_time . ' reached before exporting all items.', 'warning');
				break;
			}

			/**
			 * Get next set of items from the model
			 */
			$items = $export_all
				? $plg->getItemsSet($model)
				: array();

			// Get custom fields and load their values
			$_vars = null;
			FlexicontentFields::getItemFields($items, $_vars, $_view = 'category', $aid);
		}
		
		if (!count($items))
		{
			// Message cannot be set when using jexit() ...
			//$app->enqueueMessage('Exported all items' ), 'warning');
		}

		// Need to exist here !! to avoid any other output
		jexit();
	}


	protected function _encodeCSVField($string)
	{
		if (strpos($string, ',') !== false || strpos($string, '"') !== false || strpos($string, "\n") !== false) 
		{
			$string = '"' . str_replace('"', '""', $string) . '"';
		}

		//return mb_convert_encoding($string, 'UTF-16LE', 'UTF-8');
		return $string;
	}


	protected function _getSafeReferer()
	{
		// Get safe referer in case we abort
		$referer = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
		return flexicontent_html::is_safe_url($referer)
			? $referer
			: JUri::base();
	}
}
