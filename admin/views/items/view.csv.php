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
class FlexicontentViewItems extends \Joomla\CMS\MVC\View\HtmlView
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
		$user    = \Joomla\CMS\Factory::getUser();
		$aid     = \Joomla\CMS\Access\Access::getAuthorisedViewLevels($user->id);
		$app     = \Joomla\CMS\Factory::getApplication();
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
		$cparams = \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent');
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

		// Field and item separators, expanding escape characters like '\n', '\t', e.g. \n~~ for an item separator compatible with the import tool
		$field_sep  = $this->_expandEscapes($cparams->get('csv_export_field_sep', ',')) ?: ',';
		$record_sep = $this->_expandEscapes($cparams->get('csv_export_item_record_sep', '\n')) ?: "\n";

		// Check if current view is filtered by item type
		$filter_type    = $model->getState('filter_type');
		$csv_header     = $app->isClient('administrator') ? (int) $model->getState('csv_header') : (int) $cparams->get('csv_export_header', 1);
		$csv_raw_export = $app->isClient('administrator') ? (int) $model->getState('csv_raw_export') : 2;
		$csv_all_fields = $app->isClient('administrator') ? (int) $model->getState('csv_all_fields') : 2;
		$csv_zip_media  = $app->isClient('administrator') ? (int) $model->getState('csv_zip_media') : 0;
		$err_count      = 0;
		$csv_header     = $csv_header === -1 ? (int) $cparams->get('csv_export_header', 1) : $csv_header;

		if (!$filter_type && $app->isClient('administrator'))
		{
			$app->enqueueMessage(\Joomla\CMS\Language\Text::_('FLEXI_CSV_EXPORT_PLEASE_FILTER_BY_TYPE'), 'warning');
			$app->redirect($this->_getSafeReferer());
		}

		if (!$csv_header && $app->isClient('administrator'))
		{
			$app->enqueueMessage(\Joomla\CMS\Language\Text::_('Please select Field name or Field label as header row.'), 'warning');
			$err_count++;
		}

		if (!$csv_raw_export && $app->isClient('administrator'))
		{
			$app->enqueueMessage(\Joomla\CMS\Language\Text::_('Please select to export values according to field configuration or to export raw values.'), 'warning');
			$err_count++;
		}

		if (!$csv_all_fields && $app->isClient('administrator'))
		{
			$app->enqueueMessage(\Joomla\CMS\Language\Text::_('Please select to export all fields or only configured fields'), 'warning');
			$err_count++;
		}

		if ($err_count)
		{
			$app->enqueueMessage(\Joomla\CMS\Language\Text::_('(Set this inside filters slider)'), 'warning');
			$app->redirect($this->_getSafeReferer());
		}

		if ($csv_zip_media && !class_exists('ZipArchive'))
		{
			$app->enqueueMessage('Cannot add media files to ZIP: PHP extension "zip" (ZipArchive) is not installed on the server', 'warning');
			$app->redirect($this->_getSafeReferer());
		}

		// Fields using the 'Importable' CSV format will register their media files for adding them to the ZIP file
		flexicontent_csvmedia::$enabled = (bool) $csv_zip_media;

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

		$has_pro    = \Joomla\CMS\Plugin\PluginHelper::isEnabled($extfolder = 'system', $extname = 'flexisyspro');
		$export_all = $has_pro && $app->isClient('administrator') && $jinput->getCmd('items_set', '') === 'all';

		if ($export_all)
		{
			// Create plugin instance
			$className   = 'plg' . ucfirst($extfolder) . $extname;
			$dispatcher  = JEventDispatcher::getInstance();
			$plg_db_data = \Joomla\CMS\Plugin\PluginHelper::getPlugin($extfolder, $extname);

			$plg = new $className($dispatcher, array(
				'type'   => $extfolder,
				'name'   => $extname,
				'params' => $plg_db_data->params,
			));

			if (!method_exists($plg, 'getItemsSet'))
			{
				$app->enqueueMessage(\Joomla\CMS\Language\Text::_('FLEXI_PRO_VERSION_OUTDATED'), 'warning');
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
			$app->enqueueMessage(\Joomla\CMS\Language\Text::_('FLEXI_CSV_EXPORT_ZERO_FIELDS_CONFIGURED_FOR_CSV_EXPORT'), 'warning');
			$app->redirect($this->_getSafeReferer());
		}



		/**
		 * 1. Output HTTP HEADERS
		 */

		@ob_end_clean();
		header("Pragma: no-cache");
		header("Cache-Control: no-cache");
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

		$export_name = 'EXPORT-' . rand();

		// Buffer the CSV output, to add it to the ZIP file after all items are exported
		if ($csv_zip_media)
		{
			ob_start();
		}
		else
		{
			header('Content-Encoding: UTF-8');
			header('Content-type: text/csv; charset=UTF-8');
			header('Content-Disposition: attachment; filename=' . $export_name . '.csv');
			//header("Content-Transfer-Encoding: binary");
		}
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

			echo $delim . $this->_encodeCSVField($csv_header === 1 ? $field->label : $this->_importColumnName($field), $field_sep, $record_sep);
			$delim = $field_sep;
			$total_fields++;
		}


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

				// Item separator is added before every item (and not after the last one)
				echo $record_sep;

				$delim = '';

				foreach($item0->fields as $field_name => $field)
				{
					$is_coreprops_form_field = $field->field_type === 'coreprops' && substr($field->name, 0 , 5) === 'form_';

					$include_in_csv_export = (int) $field->parameters->get('include_in_csv_export', 0);
					$csv_strip_html = (int) $cparams->get('csv_strip_html', 0);

					$include_in_csv_export = $csv_all_fields !== 2
						? $include_in_csv_export
						: ($is_coreprops_form_field ? 0 : 1);

					$csv_strip_html = $csv_all_fields !== 2
						? $csv_strip_html
						: 1;

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
						// (skip for importable format, stripping would remove its {...} tags)
						if ($csv_strip_html && $field->parameters->get('csv_export_format', 'html') !== 'importable')
						{
							$vals = flexicontent_html::striptagsandcut($vals);
						}
					}

					// Description (core field 'text'): stored in the item's introtext / fulltext columns (not in the field values table)
					// Join them with the readmore separator, the import tool splits the 'text' column back to introtext / fulltext
					elseif ($field->iscore && $field->field_type === 'maintext')
					{
						$vals = $item->introtext . (strlen(trim($item->fulltext ?? '')) ? '<hr id="system-readmore" />' . $item->fulltext : '');
					}

					// CASE 2: CORE properties (special case), TODO: Implement this as "RENDERED value display" (and make it default output for them ?)
					elseif ($field->iscore && isset($core_props[$field_name]))
					{
						// Tags and categories: comma separated ids, as expected by the import tool ('tags_raw' and 'cid' columns)
						if ($field_name === 'tags' || $field_name === 'categories')
						{
							$vals = implode(',', array_map(function ($obj) { return (int) (is_object($obj) ? $obj->id : $obj); }, (array) $item->$field_name));
						}
						elseif ($csv_raw_export === 2 && isset($item->$field_name))
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
						$vals = $item->fieldvalues[$field->id];

						// Weblink: use the multi-property format of the import tool, e.g. [-link-]=https://...!![-linktext-]=Some text
						if ($field->field_type === 'weblink')
						{
							$vals = array_map(function ($v) use ($cparams) { return $this->_importMultiPropValue($v, $cparams->get('csv_export_field_mprop_sep', '!!')); }, (array) $vals);
						}
					}

					// Make sure that $vals is array of strings
					if (is_array($vals) && (is_array(reset($vals)) || is_object(reset($vals))))
					{
						$_vals = [];
						foreach ($vals as $v)
						{
							$v = (array) $v;
							foreach($v as $k => $v2)
							{
								$v[$k] = is_string($v2) ? $v2 : json_encode($v2);
							}
							$_vals[] = implode($cparams->get('csv_export_field_mprop_sep', '!!'), $v);
						}
						$vals = $_vals;
					}
					echo $this->_encodeCSVField( is_array($vals) ? implode($cparams->get('csv_export_field_multivalue_sep', '%%'), $vals ) : $vals, $field_sep, $record_sep );
				}
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

		if ($csv_zip_media)
		{
			$this->_outputZip($export_name, ob_get_clean());
		}

		// Need to exist here !! to avoid any other output
		jexit();
	}


	/**
	 * Output a ZIP file containing the CSV file and the media files registered by the fields
	 *
	 * @param   string  $export_name  Name of the ZIP file and of the CSV file inside it (without extension)
	 * @param   string  $csv          The CSV file contents
	 */
	protected function _outputZip($export_name, $csv)
	{
		$zip_path = \Joomla\Filesystem\Path::clean(\Joomla\CMS\Factory::getApplication()->get('tmp_path', JPATH_SITE . DS . 'tmp') . DS . $export_name . '.zip');

		$zip = new ZipArchive();

		if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true)
		{
			// Headers are already sent, only the CSV file can be given
			header('Content-type: text/csv; charset=UTF-8');
			header('Content-Disposition: attachment; filename=' . $export_name . '.csv');
			echo $csv;
			return;
		}

		$zip->addFromString($export_name . '.csv', $csv);

		foreach (flexicontent_csvmedia::$files as $folder => $files)
		{
			foreach ($files as $name => $src_path)
			{
				$zip->addFile($src_path, $folder . '/' . $name);

				// Images and archives are already compressed
				$zip->setCompressionName($folder . '/' . $name, ZipArchive::CM_STORE);
			}
		}

		if (flexicontent_csvmedia::$missing)
		{
			$zip->addFromString('missing_files.txt', "Files not found on the server:\n" . implode("\n", array_keys(flexicontent_csvmedia::$missing)) . "\n");
		}

		$zip->close();

		header('Content-type: application/zip');
		header('Content-Disposition: attachment; filename=' . $export_name . '.zip');
		header('Content-Length: ' . filesize($zip_path));
		readfile($zip_path);

		@unlink($zip_path);
	}


	/**
	 * Enclose a value in double quotes when it contains the field or item separator
	 * For standard CSV (comma separator), also when it contains double quotes or new lines
	 * With multi-character separators e.g. ~~ and \n~~ values are normally not enclosed, thus the import tool needs no enclosure character
	 */
	protected function _encodeCSVField($string, $field_sep = ',', $record_sep = "\n")
	{
		$string = (string) $string;

		if (strpos($string, $field_sep) !== false || strpos($string, $record_sep) !== false
			|| ($field_sep === ',' && (strpos($string, '"') !== false || strpos($string, "\n") !== false)))
		{
			$string = '"' . str_replace('"', '""', $string) . '"';
		}

		//return mb_convert_encoding($string, 'UTF-16LE', 'UTF-8');
		return $string;
	}


	/**
	 * Convert a serialized multi-property value to the multi-property format of the import tool,
	 * e.g. [-link-]=https://...!![-linktext-]=Some text, adding only non-empty properties (hits are counted per site, thus skipped)
	 */
	protected function _importMultiPropValue($value, $mprop_sep)
	{
		$array = flexicontent_db::unserialize_array($value, $force_array = false, $force_value = false);

		if (!is_array($array))
		{
			return $value;
		}

		$props = array();

		foreach ($array as $name => $propval)
		{
			if ($name !== 'hits' && is_scalar($propval) && strlen((string) $propval))
			{
				$props[] = '[-' . $name . '-]=' . $propval;
			}
		}

		return implode($mprop_sep, $props);
	}


	/**
	 * Column name of a field, using the column names expected by the import tool for fields whose name differs
	 */
	protected function _importColumnName($field)
	{
		if ($field->iscore && $field->name === 'tags')
		{
			return 'tags_raw';    // Comma separated tag ids
		}

		if ($field->iscore && $field->name === 'categories')
		{
			return 'cid';         // Comma separated category ids (secondary categories, the main category may also be included)
		}

		if ($field->field_type === 'coreprops' && $field->parameters->get('props_type', '') === 'category')
		{
			return 'catid';       // Main category id
		}

		return $field->name;
	}


	/**
	 * Expand escape characters of a separator given in configuration, e.g. '\n~~' to a new line followed by ~~
	 */
	protected function _expandEscapes($string)
	{
		return strtr((string) $string, array('\n' => "\n", '\r' => "\r", '\t' => "\t"));
	}


	protected function _getSafeReferer()
	{
		// Get safe referer in case we abort
		$referer = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
		return flexicontent_html::is_safe_url($referer)
			? $referer
			: \Joomla\CMS\Uri\Uri::base();
	}
}
