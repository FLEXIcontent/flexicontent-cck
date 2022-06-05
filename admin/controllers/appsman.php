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

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

JLoader::register('FlexicontentControllerBaseAdmin', JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'controllers' . DS . 'base' . DS . 'baseadmin.php');

// Manually import models in case used by frontend, then models will not be autoloaded correctly via getModel('name')
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'appsman.php';

/**
 * FLEXIcontent Apps manager Controller
 *
 * @since 3.3
 */
class FlexicontentControllerAppsman extends FlexicontentControllerBaseAdmin
{
	var $records_dbtbl = null;
	var $records_jtable = null;

	var $record_name = 'appsman';
	var $record_name_pl = 'appsman';

	var $_NAME = 'APPSMAN';
	var $record_alias = 'not_applicable';

	var $runMode = 'standalone';

	var $exitHttpHead = null;
	var $exitMessages = array();
	var $exitLogTexts = array();
	var $exitSuccess  = true;

	static $allowed_tables = array(
		'flexicontent_fields',
		'flexicontent_types',
		'flexicontent_templates',
		'categories',
		'usergroups',
		'assets'
	);

	static $table_idcols = array(
		'flexicontent_fields' => 'id',
		'flexicontent_types' => 'id',
		'flexicontent_templates' => 'template',
		'categories' => 'id',
		'usergroups' => 'id',
		'assets' => 'id'
	);

	static $no_id_tables = array();


	/**
	 * Constructor
	 *
	 * @param   array   $config    associative array of configuration settings.
	 *
	 * @since 3.3
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		// Register task aliases
		$this->registerTask('initxml',  'import');
		$this->registerTask('clearxml',  'import');
		$this->registerTask('processxml', 'import');

		$this->registerTask('exportxml', 'export');
		$this->registerTask('exportsql', 'export');
		$this->registerTask('exportcsv', 'export');

		// Can manage ACL
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanConfig;
	}


	/**
	 * Logic to add to export
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	function addtoexport()
	{
		$app   = JFactory::getApplication();
		$model = $this->getModel($this->record_name);
		$user  = JFactory::getUser();

		$session  = JFactory::getSession();

		// Calculate ACL access
		$is_authorised = $this->canManage;

		// Check access
		if (!$is_authorised)
		{
			$app->setHeader('status', '403 Forbidden', true);
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->redirect($this->returnURL);
		}


		/**
		 * Security Concern: check for allowed tables
		 */

		$table = strtolower($this->input->get('table', 'flexicontent_fields', 'cmd'));

		if (!in_array($table, self::$allowed_tables))
		{
			$app->setHeader('status', '403 Forbidden', true);
			$app->enqueueMessage(JText::_('FLEXI_NO_ACCESS') . ' Table: ' . $table . ' not in allowed tables', 'error');
			$app->redirect($this->returnURL);
		}

		$ids_are_integers = $table != 'flexicontent_templates';

		/**
		 * Get rows ids
		 */

		$cid = $this->input->get('cid', array(), 'array');

		if ($ids_are_integers)
		{
			$cid = ArrayHelper::toInteger($cid);
		}

		$conf = $session->get('appsman_export', array(), 'flexicontent');

		$count_new = 0;
		$count_old = 0;

		foreach ($cid as $_id)
		{
			if (!isset($conf[$table][$_id]))
			{
				$count_new++;
			}
			else
			{
				$count_old++;
			}

			$conf[$table][$_id] = 1;
		}


		/**
		 * Some tables have related DB data, add them
		 */

		$customHandler_msg = array();
		$customHandler = 'getRelatedIds_' . $table;

		if (is_callable(array($model, $customHandler)))
		{
			$related_ids = $model->$customHandler($cid);

			foreach ($related_ids as $_table => $_cid)
			{
				$customHandler_msg[] = '- added related ' . count($_cid) . ' records from <strong>' . $_table . '</strong>';

				foreach ($_cid as $_id)
				{
					$conf[$_table][$_id] = 1;
				}
			}
		}

		$customHandler_msg = empty($customHandler_msg) ? '' : "<br/>\n" . implode("<br/>\n", $customHandler_msg);

		/**
		 * Set row ids into session, and redirect back to refere with a message
		 */

		$session->set('appsman_export', $conf, 'flexicontent');

		$app->enqueueMessage('TABLE: <strong>' . $table . '</strong><br/>' . $count_new . ' new records added, ' . $count_old . ' existing records updated' . $customHandler_msg, 'notice');
		$this->setRedirect($this->returnURL);
	}


	/**
	 * Clear export configuration from session
	 *
	 * @return void
	 *
	 * @since	3.3
	 */
	public function exportclear()
	{
		$app      = JFactory::getApplication();
		$user     = JFactory::getUser();
		$session  = JFactory::getSession();

		// Calculate access
		$is_authorised = $this->canManage;

		// Check access
		if (!$is_authorised)
		{
			$app->setHeader('status', '403 Forbidden', true);
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->redirect($this->returnURL);
		}

		$session->set('appsman_export', "", 'flexicontent');

		$app->enqueueMessage('Export list cleared', 'notice');
		$this->setRedirect($this->returnURL);
	}


	/**
	 * Logic to import the configuration of an 'application', the 'process*' tasks are aliased to this task
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function import()
	{
		$app      = JFactory::getApplication();
		$user     = JFactory::getUser();
		$session  = JFactory::getSession();
		$document = JFactory::getDocument();
		$has_zlib = version_compare(PHP_VERSION, '5.4.0', '>=');

		$this->input->set('view', $this->record_name);
		$this->input->set('hidemainmenu', 1);

		// Get/Create the view
		$viewType   = $document->getType();
		$viewName   = $this->input->get('view', $this->default_view, 'cmd');
		$viewLayout = $this->input->get('layout', 'default', 'string');
		$view = $this->getView($viewName, $viewType, '', array('base_path' => $this->basePath, 'layout' => $viewLayout));

		// Get/Create the model
		$model = $this->getModel($this->record_name);

		// Push the model into the view (as default), later we will call the view display method instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->setModel($model, true);
		$view->document = $document;

		// Calculate access
		$is_authorised = $this->canManage;

		// Check access
		if (!$is_authorised)
		{
			$app->setHeader('status', '403 Forbidden', true);
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->redirect($this->returnURL);
		}

		$task = strtolower($this->input->get('task', '', 'cmd'));

		/**
		 * Execute according to task
		 */

		switch ($task)
		{
			/**
			 * RESET/CLEAR an already started import task, e.g. import process was interrupted for some reason
			 */

			case 'clearxml':

				// Clear any import data from session
				$conf = $has_zlib ? base64_encode(zlib_encode(serialize(null), -15)) : base64_encode(serialize(null));

				$session->set('appsman_config', $conf, 'flexicontent');

				// Set a message that import task was cleared and redirect
				$app->enqueueMessage('Import task cleared', 'notice');
				$app->redirect($this->returnURL);
				break;

			/**
			 * EXECUTE an already initialized (prepared) import task
			 */

			case 'processxml':

				$conf   = $session->get('appsman_config', "", 'flexicontent');
				$conf		= unserialize($conf ? ($has_zlib ? zlib_decode(base64_decode($conf)) : base64_decode($conf)) : "");

				if (empty($conf))
				{
					$app->setHeader('status', '200 OK', true);
					$app->enqueueMessage('Can not continue import, import task not initialized or already finished', 'error');
					$app->redirect($this->returnURL);
				}

				libxml_use_internal_errors(true);
				$xml = simplexml_load_string($conf['xml']);

				if (!$xml)
				{
					foreach(libxml_get_errors() as $error)
					{
						$err_msg[] = $error->message;
					}

					$app->setHeader('status', '500', true);
					$app->enqueueMessage('Can not parse XML file: ' . implode('<br>', $err_msg), 'error');
					$app->redirect($this->returnURL);
				}

				$db = JFactory::getDbo();
				$nullDate = $db->getNullDate();

				$remap = array();

				foreach ($xml->rows as $table)
				{
					$table_name  = (string) $table->attributes()->table;
					$table_label = ucfirst(str_replace('flexicontent_', '', $table_name));
					$remap[$table_name] = array();

					$rows = $table->row;

					if (!count($rows))
					{
						continue;
					}

					if (!isset(self::$table_idcols[$table_name]))
					{
						continue;
					}

					// TODO: allow here assets too
					if ($table_name == 'assets' || $table_name == 'flexicontent_templates')
					{
						continue;
					}

					$id_colname = self::$table_idcols[$table_name];

					foreach ($rows as $row)
					{
						$obj = new stdClass;

						foreach ($row as $col => $val)
						{
							$val = trim((string) $val, '"');

							if ($col == $id_colname)
							{
								$old_id = $val;
							}

							if ($col == 'checked_out_time')
							{
								$obj->$col = $nullDate;
							}
							elseif ($col == 'checked_out')
							{
								$obj->$col = 0;
							}
							else
							{
								$obj->$col = ($col == $id_colname && $table_name !== 'flexicontent_templates') ? 0 : $val;
							}
						}

						echo $table_name . '<br/>';
						echo '<pre>' . print_r($obj, true) . '</pre>';

						// Insert record in DB
						// $db->insertObject('#__'.$table_name, $obj);

						// Get id of the new record
						$new_id = (int) $db->insertid();
						$remap[$table_name][$old_id] = $new_id;
					}
				}

				// Special handling tables
				foreach ($xml->rows as $table)
				{
					$table_name  = (string) $table->attributes()->table;
					$table_label = ucfirst(str_replace('flexicontent_', '', $table_name));

					$rows = $table->row;

					if (!count($rows))
					{
						continue;
					}

					$customHandler = 'doImport_' . $table_name;

					if (is_callable(array($model, $customHandler)))
					{
						$model->$customHandler($rows, $remap);
					}
				}

				// echo "<pre>"; print_r($xml); echo "</pre>";
				// CONTINUE to do the import task ...
				break;

			/**
			 * INITIALIZE (prepare) import by getting configuration and reading XML file
			 */

			case 'initxml':

				// Retrieve the temporary path of the uploaded file
				$xmlfile = @$_FILES["xmlfile"]["tmp_name"];

				if (!is_file($xmlfile))
				{
					$app->enqueueMessage('Upload file error!', 'error');
					$app->redirect($link);
				}

				$zip = new ZipArchive;
				$zip_result = $zip->open($xmlfile);

				if ($zip_result === true)
				{
					$conf['xml'] = $zip->getFromName('dbdata.xml');
				}
				elseif ($zip_result === ZipArchive::ER_NOZIP)
				{
					$conf['xml'] = file_get_contents($xmlfile);
				}
				else
				{
					$app->enqueueMessage('Error reading file', 'error');
					$app->redirect($link);
				}

				// Set import configuration into session
				$session->set('appsman_config',
					( $has_zlib ? base64_encode(zlib_encode(serialize($conf), -15)) : base64_encode(serialize($conf)) ),
					'flexicontent'
				);

				// Set a message that import task was prepared and redirect
				$app->setHeader('status', '200 OK', true);
				$app->enqueueMessage('Import task prepared', 'message');
				$app->redirect($this->returnURL);
				break;

			/**
			 * UNKNWOWN task, terminate
			 */

			default:

				// Set an error message about unknown task and redirect
				$app->setHeader('status', '200 OK', true);
				$app->enqueueMessage('Unknown task: ' . $task, 'error');
				$app->redirect($this->returnURL);
				break;
		}

		// Call display method of the view, instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->display();
	}


	/**
	 * Logic to export 'application' data into an archive, other export tasks are aliased to this task
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function export()
	{
		$app      = JFactory::getApplication();
		$user     = JFactory::getUser();
		$session  = JFactory::getSession();
		$document = JFactory::getDocument();

		$this->input->set('view', $this->record_name);
		$this->input->set('hidemainmenu', 1);

		// Get/Create the view
		$viewType   = $document->getType();
		$viewName   = $this->input->get('view', $this->default_view, 'cmd');
		$viewLayout = $this->input->get('layout', 'default', 'string');
		$view = $this->getView($viewName, $viewType, '', array('base_path' => $this->basePath, 'layout' => $viewLayout));

		// Get/Create the model
		$model = $this->getModel($this->record_name);

		// Push the model into the view (as default), later we will call the view display method instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->setModel($model, true);
		$view->document = $document;

		// Calculate / check access
		$is_authorised = $user->authorise('core.admin', 'com_flexicontent');

		if (!$is_authorised)
		{
			$app->setHeader('status', '403 Forbidden', true);
			$app->enqueueMessage(JText::_('FLEXI_NO_ACCESS'), 'error');
			$app->redirect($this->returnURL);
		}

		// Get export task
		$task = strtolower($this->input->get('task', 'exportxml', 'cmd'));
		$export_type = str_replace('export', '', $task);

		// Get optional filename of export file
		$filename = $this->input->get('export_filename', '', 'cmd');

		// When export type is given, we require that specific table and specific IDs are given too
		if ($export_type)
		{
			$table = strtolower($this->input->get('table', '', 'cmd'));
			$ids_are_integers = $table != 'flexicontent_templates';

			$cid = $this->input->get('cid', array(), 'array');

			if ($ids_are_integers)
			{
				$cid = ArrayHelper::toInteger($cid);
			}

			if (!$table)
			{
				$error[500] = JText::_('No table name given. Export aborted');
			}
			elseif (!in_array($table, self::$allowed_tables))
			{
				$error[403] = JText::_('FLEXI_NO_ACCESS') . ' Table: ' . $table . ' not in allowed tables. Export aborted';
			}
			elseif (!count($cid))
			{
				$error[500] = JText::_('No records IDs were specified. Export aborted');
			}

			if (!empty($error))
			{
				$err_statuses = array_keys($error);
				$app->setHeader('status', reset($err_statuses), true);
				$app->enqueueMessage(reset($error), 'error');
				$app->redirect($this->returnURL);
			}
		}

		// Export records from single table into the specified FILE FORMAT
		if ($export_type)
		{
			$table_name = '#__' . $table;
			$id_colname = self::$table_idcols[$table];
			$rows = $model->getTableRows($table_name, $id_colname, $cid, $id_is_unique = true);

			if (empty($rows))
			{
				$app->setHeader('status', '200 OK', true);
				$app->enqueueMessage("No rows to export", 'notice');
				$app->redirect($this->returnURL);
			}

			switch ($export_type)
			{
				case "xml":
					$customHandler = 'getExtraData_' . $table;
					$content = '<?xml version="1.0"?>'
						. "\n<conf>\n"
						. $model->create_XML_records($rows, $table_name, $id_colname, $clear_id = false)
						. (is_callable(array($model, $customHandler)) ? $model->$customHandler($rows) : '')
						. "\n</conf>";
					break;
				case "sql":
					$content = $model->create_SQL_file($rows, $table_name, $id_colname, $clear_id = false);
					break;
				case "csv":
					$content = $model->create_CSV_file($rows, $table_name, $id_colname, $clear_id = false);
					break;
				default:
					$content = false;
					$app->enqueueMessage("Unknown/unhandle file format: " . $export_type, 'error');
					break;
			}
		}

		// No specific file type and no specific table to export, export specific records from all TABLES into single archive file using files in XML format
		else
		{
			$conf = $session->get('appsman_export', array(), 'flexicontent');
			$content = '';

			foreach (self::$allowed_tables as $table)
			{
				if (empty($conf[$table]))
				{
					continue;
				}

				$cid = array_keys($conf[$table]);

				$table_name = '#__' . $table;
				$id_colname = self::$table_idcols[$table];
				$rows = $model->getTableRows($table_name, $id_colname, $cid, $id_is_unique = true);

				$content .= $model->create_XML_records($rows, $table_name, $id_colname, $clear_id = false);
				$customHandler = 'getExtraData_' . $table;

				if (is_callable(array($model, $customHandler)))
				{
					$content .= $model->$customHandler($rows);
				}
			}

			if ($content)
			{
				$content = '<?xml version="1.0"?>'
					. "\n<conf>\n"
					. $content
					. "\n</conf>";
			}
		}

		// Check if no content to export
		if (!$content)
		{
			$app->setHeader('status', '200 OK', true);
			$app->enqueueMessage("No rows to export", 'notice');
			$app->redirect($this->returnURL);
		}

		$filename = $filename ? $filename : 'dbdata';// Str_replace('#__', '', $table);
		$filename .= '.' . ($export_type ? $export_type : 'xml');

		// Simple export from a string
		if ($export_type)
		{
			$downloadname = $filename;
			$downloadsize = strlen($content);
		}

		else
		{
			/**
			 * Create temporary archive name
			 */

			$tmp_ffname = 'fcmd_uid_' . $user->id . '_' . date('Y-m-d__H-i-s');
			$archivename = $tmp_ffname . '.zip';
			$archivepath = JPath::clean($app->getCfg('tmp_path') . DS . $archivename);

			/**
			 * Create a new Zip archive on the server disk
			 */

			$zip = new flexicontent_zip;  // Extends ZipArchive
			$res = $zip->open($archivepath, ZipArchive::CREATE);

			if (!$res)
			{
				die('failed creating temporary archive: ' . $archivepath);
			}

			$zip->addFromString($filename, $content);

			/**
			 * Get extra files for those tables that need this
			 */

			foreach (self::$allowed_tables as $table)
			{
				if (empty($conf[$table]))
				{
					continue;
				}

				$customHandler = 'getExtraFiles_' . $table;

				if (is_callable(array($model, $customHandler)))
				{
					// Custom file handler needs the DB rows of the current table, get them
					$cid = array_keys($conf[$table]);

					$id_colname = self::$table_idcols[$table];
					$table_name = '#__' . $table;
					$rows = $model->getTableRows($table_name, $id_colname, $cid, $id_is_unique = true);

					$model->$customHandler($rows, $zip);
				}
			}

			$zip->close();
			$downloadname = $archivename;
			$downloadsize = filesize($archivepath);
		}

		/**
		 * Output HTTP headers
		 */

		header("Pragma: public"); // Required
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private", false); // Required for certain browsers
		header('Content-Type: application/force-download');  // header('Content-Type: text/'.$export_type);
		header("Content-Disposition: attachment; filename=\"" . $downloadname . "\";");  // quote to allow spaces in filenames
		header("Content-Transfer-Encoding: Binary");
		header("Content-Length: " . $downloadsize);

		/**
		 * Output the file
		 */

		if ($export_type)
		{
			// Simple export from a string
			echo $content;
		}
		else
		{
			/**
			 * When more than 1MB, (highest possible for fread should be 8MB)
			 * read archive file from the server disk in chunks
			 */
			$MB_limit  = 1;
			$chunksize = $MB_limit * (1024 * 1024);
			$filesize  = filesize($archivepath);

			if ($filesize > $chunksize)
			{
				$handle = @fopen($archivepath, "rb");

				while (!feof($handle))
				{
					print(@fread($handle, $chunksize));
					ob_flush();
					flush();
				}

				fclose($handle);
			}
			else
			{
				/**
				 * This is good for small files, it will read an output the file into
				 * memory and output it, it will cause a memory exhausted error on large files
				 */
				ob_clean();
				flush();
				readfile($archivepath);
			}

			// Remove temporary archive file
			unlink($archivepath);
		}

		$app->close();
	}
}
