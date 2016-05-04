<?php
/**
 * @version 1.5 stable $Id: import.php 1650 2013-03-11 10:27:06Z ggppdk $
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

defined( '_JEXEC' ) or die( 'Restricted access' );

// Register autoloader for parent controller, in case controller is executed by another component
JLoader::register('FlexicontentController', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'controller.php');

/**
 * FLEXIcontent Component Import Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerAppsman extends FlexicontentController
{
	static $allowed_tables = array(
		'flexicontent_fields',
		'flexicontent_types',
		'flexicontent_templates',
		'categories',
		'usergroups',
		'assets'
	);
	static $table_idcols = array(
		'flexicontent_fields'=>'id',
		'flexicontent_types'=>'id',
		'flexicontent_templates'=>'template',
		'categories'=>'id',
		'usergroups'=> 'id',
		'assets'=>'id'
	);
	static $no_id_tables = array();
	
	
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();

		// Register Extra task
		$this->registerTask( 'initxml',  'import');
		$this->registerTask( 'clearxml',  'import');
		$this->registerTask( 'processxml', 'import');
		
		$this->registerTask( 'exportxml', 'export' );
		$this->registerTask( 'exportsql', 'export' );
		$this->registerTask( 'exportcsv', 'export' );
	}
	
	
	
	function addtoexport()
	{
		$app      = JFactory::getApplication();
		$user     = JFactory::getUser();
		$session  = JFactory::getSession();
		
		// Get/Create the model
		$model = $this->getModel('appsman');
		
		
		// ************************
		// Calculate / check access
		// ************************
		
		$is_authorised = $user->authorise('core.admin', 'com_flexicontent');
		if ( !$is_authorised ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_NO_ACCESS' ) );
			$this->setRedirect( $_SERVER['HTTP_REFERER'] );
			return;
		}
		
		
		// ******************************************
		// Security Concern: check for allowed tables
		// ******************************************
		
		$table = strtolower(JRequest::getCmd('table', 'flexicontent_fields'));
		if ( !in_array($table, self::$allowed_tables) ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_NO_ACCESS' . ' Table: ' .$table. ' not in allowed tables' ) );
			$this->setRedirect( $_SERVER['HTTP_REFERER'] );
			return;
		}
		$ids_are_integers = $table!='flexicontent_templates';
		
		
		// ************
		// Get rows ids
		// ************
		
		$cid = JRequest::getVar( 'cid', array(), $hash='default', 'array' );
		if ($ids_are_integers) JArrayHelper::toInteger($cid, array());
		
		$conf = $session->get('appsman_export', array(), 'flexicontent');	
		
		$count_new = 0;
		$count_old = 0;
		foreach ($cid as $_id) {
			if (!isset($conf[$table][$_id]))
				$count_new++;
			else
				$count_old++;
			$conf[$table][$_id] = 1;
		}
		
		
		// ******************************************
		// Some tables have related DB data, add them
		// ******************************************
		
		$customHandler_msg = array();
		$customHandler = 'getRelatedIds_'.$table;
		if ( is_callable(array($model, $customHandler)) )
		{
			$related_ids = $model->$customHandler($cid);
			foreach ($related_ids as $_table => $_cid) {
				$customHandler_msg[] = '- added related '.count($_cid).' records from <strong>'.$_table.'</strong>';
				foreach ($_cid as $_id)  $conf[$_table][$_id] = 1;
			}
		}
		$customHandler_msg = empty($customHandler_msg) ? '' : "<br/>\n".implode("<br/>\n", $customHandler_msg);
		
		
		// ********************************************************************
		// Set row ids into session, and redirect back to refere with a message
		// ********************************************************************
		
		$session->set('appsman_export', $conf, 'flexicontent');
		
		$app->enqueueMessage( 'TABLE: <strong>'.$table.'</strong><br/>'.$count_new.' new records added, '.$count_old.' existing records updated'.$customHandler_msg, 'notice' );
		$this->setRedirect( $_SERVER['HTTP_REFERER'] );
		return;
	}
	
	
	
	function exportclear()
	{
		$app      = JFactory::getApplication();
		$user     = JFactory::getUser();
		$session  = JFactory::getSession();
		
		// calculate / check access
		$is_authorised = $user->authorise('core.admin', 'com_flexicontent');
		if ( !$is_authorised ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_NO_ACCESS' ) );
			$this->setRedirect( $_SERVER['HTTP_REFERER'] );
			return;
		}
		
		$session->set('appsman_export', "", 'flexicontent');
		
		$app->enqueueMessage( 'Export list cleared', 'notice' );
		$this->setRedirect( $_SERVER['HTTP_REFERER'] );
		return;
	}
	
	
	
	function import()
	{
		//JRequest::setVar( 'view', 'appsman' );
		//JRequest::setVar( 'hidemainmenu', 1 );
		
		$app      = JFactory::getApplication();
		$user     = JFactory::getUser();
		$session  = JFactory::getSession();
		$document = JFactory::getDocument();
		$has_zlib = version_compare(PHP_VERSION, '5.4.0', '>=');
		
		// Get/Create the view
		$viewType   = $document->getType();
		$viewName   = $this->input->get('view', $this->default_view, 'cmd');
		$viewLayout = $this->input->get('layout', 'default', 'string');
		$view = $this->getView($viewName, $viewType, '', array('base_path' => $this->basePath, 'layout' => $viewLayout));
		
		// Get/Create the model
		$model = $this->getModel('appsman');
		
		// Push the model into the view (as default), later we will call the view display method instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->setModel($model, true);
		$view->document = $document;
		
		
		
		// ************************
		// Calculate / check access
		// ************************
		
		$is_authorised = $user->authorise('core.admin', 'com_flexicontent');
		if ( !$is_authorised ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_NO_ACCESS' ) );
			$this->setRedirect( $_SERVER['HTTP_REFERER'] );
			return;
		}
		
		
		
		$link = $_SERVER['HTTP_REFERER'];  // 'index.php?option=com_flexicontent&view=appsman';
		$task = strtolower($this->input->get('task', '', 'cmd'));
		
		
		
		// *************************
		// Execute according to task
		// *************************
		
		switch ($task) {
		
		
		// ***********************************************************************************************
		// RESET/CLEAR an already started import task, e.g. import process was interrupted for some reason
		// ***********************************************************************************************
		
		case 'clearxml':
		
			// Clear any import data from session
			$conf = $has_zlib ? base64_encode(zlib_encode(serialize(null), -15)) : base64_encode(serialize(null));
			
			$session->set('appsman_config', $conf, 'flexicontent');
			
			// Set a message that import task was cleared and redirect
			$app->enqueueMessage( 'Import task cleared' , 'notice' );
			$this->setRedirect( $link );
			return;
			break;
		
		
		// *****************************************************
		// EXECUTE an already initialized (prepared) import task
		// *****************************************************
		
		case 'processxml':
		
			$conf   = $session->get('appsman_config', "", 'flexicontent');
			$conf		= unserialize( $conf ? ($has_zlib ? zlib_decode(base64_decode($conf)) : base64_decode($conf)) : "" );
			
			if ( empty($conf) ) {
				$app->enqueueMessage( 'Can not continue import, import task not initialized or already finished' , 'error');
				$this->setRedirect( $link );
				return;
			}
			
			$xml = simplexml_load_string($conf['xml']);
			$db = JFactory::getDBO();
			$nullDate = $db->getNullDate();
			
			$remap = array();
			foreach ($xml->rows as $table)
			{
				$table_name  = (string)$table->attributes()->table;
				$table_label = ucfirst(str_replace('flexicontent_', '', $table_name));
				$remap[$table_name] = array();
				
				$rows = $table->row;
				if (!count($rows)) continue;
				
				if (!isset(self::$table_idcols[$table_name])) continue;
				
				// TODO: allow here assets too
				if ($table_name=='assets' || $table_name=='flexicontent_templates') continue;
				
				
				$id_colname = self::$table_idcols[$table_name];
				
				foreach ($rows as $row)
				{
					$obj = new stdClass();
					foreach($row as $col => $val)
					{
						$val = trim((string)$val,'"');
						if ($col == $id_colname) $old_id = $val;
						
						if ($col == 'checked_out_time')  $obj->$col = $nullDate;
						else if ($col == 'checked_out')  $obj->$col = 0;
						else $obj->$col = ($col == $id_colname && $table_name!=='flexicontent_templates') ? 0 : $val;
					}
					echo $table_name." <br/><pre>";	print_r($obj); echo "</pre>";
					
					// Insert record in DB
					//$db->insertObject('#__'.$table_name, $obj);
					
					// Get id of the new record
					$new_id = (int)$db->insertid();
					$remap[$table_name][$old_id] = $new_id;
				}
			}
			
			
			// Special handling tables
			foreach ($xml->rows as $table)
			{
				$table_name  = (string)$table->attributes()->table;
				$table_label = ucfirst(str_replace('flexicontent_', '', $table_name));
				
				$rows = $table->row;
				if (!count($rows)) continue;
				
				$customHandler = 'doImport_'.$table_name;
				if (is_callable(array($model, $customHandler)))
					$model->$customHandler($rows, $remap);
			}
			
			//echo "<pre>";	print_r($xml); echo "</pre>";
			exit;
			
			// CONTINUE to do the import task
			// ...
			break;
		
		
		// *************************************************************************
		// INITIALIZE (prepare) import by getting configuration and reading XML file
		// *************************************************************************
		
		case 'initxml':
		
			// Retrieve the temporary path of the uploaded file
			$xmlfile = @$_FILES["xmlfile"]["tmp_name"];
			if( !is_file($xmlfile) ) {
				$app->enqueueMessage('Upload file error!', 'error');
				$app->redirect( $link );
			}
			
			$zip = new ZipArchive(); 
			if ( $zip->open($xmlfile) !== FALSE )
			{
				$zip->open($xmlfile); 
				$conf['xml'] = $zip->getFromName('dbdata.xml');
			}
			else
			{
				$conf['xml'] = file_get_contents($xmlfile);
			}
			
			// Set import configuration into session
			$session->set('appsman_config',
				( $has_zlib ? base64_encode(zlib_encode(serialize($conf), -15)) : base64_encode(serialize($conf)) ),
				'flexicontent');
			
			// Set a message that import task was prepared and redirect
			$app->enqueueMessage( 'Import task prepared', 'message');
			$this->setRedirect( $link );
			return;
			
			break;
		
		
		// ************************
		// UNKNWOWN task, terminate
		// ************************
		
		default:
		
			// Set an error message about unknown task and redirect
			$app->enqueueMessage('Unknown task: '.$task, 'error');
			$this->setRedirect( $link );
			return;
			
			break;
		}

		$view->display();
	}
	
	
	function export()
	{
		$app      = JFactory::getApplication();
		$user     = JFactory::getUser();
		$session  = JFactory::getSession();
		$document = JFactory::getDocument();
		
		// Get/Create the view
		$viewType   = $document->getType();
		$viewName   = $this->input->get('view', $this->default_view, 'cmd');
		$viewLayout = $this->input->get('layout', 'default', 'string');
		$view = $this->getView($viewName, $viewType, '', array('base_path' => $this->basePath, 'layout' => $viewLayout));
		
		// Get/Create the model
		$model = $this->getModel('appsman');
		
		// Push the model into the view (as default), later we will call the view display method instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->setModel($model, true);
		$view->document = $document;
		
		// calculate / check access
		$is_authorised = $user->authorise('core.admin', 'com_flexicontent');
		if ( !$is_authorised ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_NO_ACCESS' ) );
			$this->setRedirect( $_SERVER['HTTP_REFERER'] );
			return;
		}
		
		
		// Get export task
		$task = strtolower($this->input->get('task', 'exportxml', 'cmd'));
		$export_type = str_replace('export', '', $task);
		
		// Get optional filename of export file
		$filename = JRequest::getCmd('export_filename');
		
		// When export type is given, we require that specific table and specific IDs are given too
		if ( $export_type )
		{
			$table = strtolower(JRequest::getCmd('table', ''));
			$cid = JRequest::getVar( 'cid', array(), $hash='default', 'array' );
			JArrayHelper::toInteger($cid, array());
			
			if ( !$table )
				$error[500] = JText::_( 'No table name given. Export aborted' );
			else if ( !in_array($table, self::$allowed_tables) )
				$error[403] = JText::_( 'FLEXI_NO_ACCESS' ) . ' Table: ' .$table. ' not in allowed tables. Export aborted';
			else if ( !count($cid) )
				$error[500] = JText::_( 'No records IDs were specified. Export aborted' );
			
			if ( !empty($error) ) {
				foreach ($error as $error_code => $error_text)  JError::raiseWarning( $error_code, $error_text );
				$this->setRedirect( $_SERVER['HTTP_REFERER'] );
				return;
			}
		}

		// Export records from single table into the specified FILE FORMAT
		if ( $export_type )
		{
			$table_name = '#__'.$table;
			$id_colname = self::$table_idcols[$table];
			$rows = $model->getTableRows($table_name, $id_colname, $cid, $id_is_unique=true);
			
			if (empty($rows))
			{
				$app->enqueueMessage("No rows to export", 'notice');
				$this->setRedirect( $_SERVER['HTTP_REFERER'] );
				return;
			}

			switch($export_type) {
				case "xml":
					$customHandler = 'getExtraData_'.$table;
					$content = '<?xml version="1.0"?>'
						."\n<conf>\n"
						.$model->create_XML_records($rows, $table_name, $id_colname, $clear_id=false)
						.(is_callable(array($model, $customHandler)) ? $model->$customHandler($rows) : '')
						."\n</conf>";
					break;
				case "sql":
					$content = $model->create_SQL_file($rows, $table_name, $id_colname, $clear_id=false);
					break;
				case "csv":
					$content = $model->create_CSV_file($rows, $tatable_nameble, $id_colname, $clear_id=false);
					break;
				default:
					$content = false;
					$app->enqueueMessage("Unknown/unhandle file format: ".$export_type, 'error');
					break;
			}
		}
		
		// No specific file type and no specific table to export, export specific records from all TABLES into single archive file using files in XML format
		else {
			$conf = $session->get('appsman_export', array(), 'flexicontent');	
			$content = '';
			foreach(self::$allowed_tables as $table)
			{
				if ( empty($conf[$table]) ) continue;
				
				$cid = array_keys($conf[$table]);
				
				$table_name = '#__'.$table;
				$id_colname = self::$table_idcols[$table];
				$rows = $model->getTableRows($table_name, $id_colname, $cid, $id_is_unique=true);
				
				$content .= $model->create_XML_records($rows, $table_name, $id_colname, $clear_id=false);
				$customHandler = 'getExtraData_'.$table;
				if ( is_callable(array($model, $customHandler)) )
				{
					$content .= $model->$customHandler($rows);
				}
			}
			if ($content) {
				$content = '<?xml version="1.0"?>'
					."\n<conf>\n"
					.$content
					."\n</conf>";
			}
		}
		
		
		// Check for error
		if (!$content)
		{
			$this->setRedirect( $_SERVER['HTTP_REFERER'] );
			return;
		}
		
		$filename = $filename ? $filename : 'dbdata';//str_replace('#__', '', $table);
		$filename .= '.'.($export_type ? $export_type : 'xml');
		
		if ( $export_type ) {
			// Simple export from a string
			$downloadname = $filename;
			$downloadsize = strlen($content);
		} else {
			
			// *****************************
			// Create temporary archive name
			// *****************************
			
			$tmp_ffname = 'fcmd_uid_'.$user->id.'_'.date('Y-m-d__H-i-s');
			$archivename = $tmp_ffname . '.zip';
			$archivepath = JPath::clean( $app->getCfg('tmp_path').DS.$archivename );
			
			
			// *******************************************
			// Create a new Zip archive on the server disk
			// *******************************************
			
			$zip = new flexicontent_zip();  // extends ZipArchive
			$res = $zip->open($archivepath, ZipArchive::CREATE);
			if (!$res) die('failed creating temporary archive: '.$archivepath);
			$zip->addFromString($filename, $content);
			
			// ***********************************************
			// Get extra files for those tables that need this
			// ***********************************************
			
			foreach(self::$allowed_tables as $table)
			{
				if ( empty($conf[$table]) ) continue;
				$customHandler = 'getExtraFiles_'.$table;
				if ( is_callable(array($model, $customHandler)) )
				{
					// Custom file handler needs the DB rows of the current table, get them
					$cid = array_keys($conf[$table]);
					
					$id_colname = self::$table_idcols[$table];
					$table_name = '#__'.$table;
					$rows = $model->getTableRows($table_name, $id_colname, $cid, $id_is_unique=true);
					
					$model->$customHandler($rows, $zip);
				}
			}
			$zip->close();
			$downloadname = $archivename;
			$downloadsize = filesize($archivepath);
		}
		
		
		// *******************
		// Output HTTP headers
		// *******************
		
		header("Pragma: public"); // required
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private", false); // required for certain browsers
		header('Content-Type: application/force-download');  //header('Content-Type: text/'.$export_type);
		header("Content-Disposition: attachment; filename=\"".$downloadname."\";");  // quote to allow spaces in filenames
		header("Content-Transfer-Encoding: Binary");
		header("Content-Length: ".$downloadsize);
		
		
		// ***************
		// Output the file
		// ***************
		
		if ( $export_type ) {
			// Simple export from a string
			echo $content;
		}
		else {
			// Read archive file from the server disk
			$chunksize = 1 * (1024 * 1024); // 1MB, highest possible for fread should be 8MB
			if (1 || $filesize > $chunksize)
			{
				$handle = @fopen($archivepath,"rb");
				while(!feof($handle))
				{
					print(@fread($handle, $chunksize));
					ob_flush();
					flush();
				}
				fclose($handle);
			} else {
				// This is good for small files, it will read an output the file into
				// memory and output it, it will cause a memory exhausted error on large files
				ob_clean();
				flush();
				readfile($archivepath);
			}
			unlink($archivepath); // remove temporary archive file
		}
		
		$app->close();
	}
}
