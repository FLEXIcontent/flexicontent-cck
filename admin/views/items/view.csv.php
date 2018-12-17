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
		// Initialize framework variables
		$user    = JFactory::getUser();
		$aid     = JAccess::getAuthorisedViewLevels($user->id);
		$app     = JFactory::getApplication();
		$jinput  = $app->input;


		// Get model
		$model  = $this->getModel();


		/**
		 * Get configuration parameters
		 * For backend this is component parameters only
		 * For frontend this category parameters as VIEW's parameters (category parameters are merged parameters in order: layout(template-manager)/component/ancestors-cats/category/author/menu)
		 */
		$params = $app->isAdmin()
			? JComponentHelper::getParams('com_flexicontent')
			: $model->getCategory()->parameters;

		// Check if CSV export button is enabled for current view
		$show_csvbutton = $app->isAdmin()
			? $params->get('show_csvbutton_be', 0)
			: $params->get('show_csvbutton', 0) ;

		if (!$show_csvbutton)
		{
			die('CSV export not enabled for this view');
		}


		// Map of CORE to item properties
		$core_props = array(

			// Core fields (Having field property 'iscore': 1)
			'title'        => 'title',
			'created_by'   => 'author',
			'modified_by'  => 'modifier',
			'created'      => 'created',
			'modified'     => 'modified',
			
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
		$export_all = $has_pro && $app->isAdmin() && $jinput->getCmd('items_set', '') === 'all';

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
				$app->enqueueMessage('FLEXI_PRO_VERSION_OUTDATED', 'warning');
				$app->redirect($this->_getSafeReferer());
			}
		}

		if ($export_all)
		{
			$items = $plg->getItemsSet($model, $_init = true);
		}
		else
		{
			$limit = $model->getState('limit') > 100 ? 100 : $model->getState('limit');
			$model->setState('limit', $limit);
			$items = $model->getData();
		}

		$item0 = reset($items);

		// Use &test=1 to test / preview item data of first item
		/*if ($jinput->getInt('test', 0))
		{
			echo "<pre>"; print_r($item0); exit;
		}*/

		// Get field values
		$_vars = null;
		FlexicontentFields::getItemFields($items, $_vars, $_view = 'category', $aid);

		// Zero unneeded search index text
		foreach ($items as $item)
		{
			$item->search_index = '';
		}


		/**
		 * Find fields that will be added to CSV export
		 */

		$total_fields = 0;
		
		foreach($item0->fields as $field)
		{
			FlexicontentFields::loadFieldConfig($field, $item0);

			$include_in_csv_export = (int) $field->parameters->get('include_in_csv_export', 0);

			if (!$include_in_csv_export)
			{
				continue;
			}

			$total_fields++;

			// Render field's CSV display
			if ($include_in_csv_export === 2)
			{
				FlexicontentFields::getFieldDisplay($items, $field->name, $values = null, $method = 'csv_export');
			}
		}


		// Abort if no fields were configured for CSV export
		if ($total_fields === 0)
		{
			$app->enqueueMessage('FLEXI_CSV_EXPORT_NO_ITEMS_MARKED_FOR_CSV_EXPORT', 'warning');
			$app->redirect($this->_getSafeReferer());
		}


		/**
		 * 0. Try to set no limit to PHP executon
		 */
		if (!FLEXIUtilities::funcIsDisabled('set_time_limit'))
		{
			@set_time_limit(0);
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
		header('Content-Disposition: attachment; filename=EXPORT.csv');
		//header("Content-Transfer-Encoding: binary");
		echo "\xEF\xBB\xBF"; // UTF-8 BOM


		/**
		 * 2. Output HEADERS row
		 */

		$delim = '';

		foreach($item0->fields as $field)
		{
			$include_in_csv_export = (int) $field->parameters->get('include_in_csv_export', 0);

			if (!$include_in_csv_export)
			{
				continue;
			}

			echo $delim . $this->_encodeCSVField($field->label);
			$delim = ",";
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
			foreach($items as $item)
			{
				$delim = '';

				foreach($item0->fields as $field_name => $field)
				{
					$include_in_csv_export = (int) $field->parameters->get('include_in_csv_export', 0);
					$csv_strip_html = (int) $field->parameters->get('csv_strip_html', 0);

					if (!$include_in_csv_export)
					{
						continue;
					}

					echo $delim;
					$delim = ',';
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
						$prop = $core_props[$field_name];
						$vals = $item->$prop;
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

			// Get data from the model
			$items = $export_all
				? $plg->getItemsSet($model)
				: array();

			// Get field values
			$_vars = null;
			FlexicontentFields::getItemFields($items, $_vars, $_view = 'category', $aid);

			// Zero unneeded search index text
			foreach ($items as $item)
			{
				$item->search_index = '';
			}
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


	protected function _getSafeReferer($string)
	{
		// Get safe referer in case we abort
		$referer = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
		return flexicontent_html::is_safe_url($referer)
			? $referer
			: JUri::base();
	}
}
