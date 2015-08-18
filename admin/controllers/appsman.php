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

jimport('joomla.application.component.controller');

/**
 * FLEXIcontent Component Import Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerAppsman extends FlexicontentController
{
	static $allowed_tables = array('flexicontent_fields', 'flexicontent_types', 'categories', 'usergroups');
	
	
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
		
		// calculate / check access
		$is_authorised = $user->authorise('core.admin', 'com_flexicontent');
		if ( !$is_authorised ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_NO_ACCESS' ) );
			$this->setRedirect( $_SERVER['HTTP_REFERER'] );
			return;
		}
		
		$cid = JRequest::getVar( 'cid', array(), $hash='default', 'array' );
		JArrayHelper::toInteger($cid, array());
		
		$table = strtolower(JRequest::getCmd('table', 'flexicontent_fields'));
		
		if ( !in_array($table, self::$allowed_tables) ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_NO_ACCESS' . ' Table: ' .$table. ' not in allowed tables' ) );
			$this->setRedirect( $_SERVER['HTTP_REFERER'] );
			return;
		}
		
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
		
		// Some tables have related DB data, add them
		$customHandler = 'getRelatedIds_'.$table;
		if ( is_callable(array($model, $customHandler)) ) {
			$related_ids = $model->$customHandler($cid);
			foreach ($related_ids as $_table => $_cid) {
				foreach ($_cid as $_id)  $conf[$_table][$_id] = 1;
			}
		}
		
		$session->set('appsman_export', $conf, 'flexicontent');
		
		$app->enqueueMessage( '<br/>'.$count_new.' rows were added to export list <br/> '.$count_old.' rows were already in export list', 'notice' );
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
			echo "<pre>";
			print_r($xml);
			exit;
			
			// CONTINUE to do the import task
			// ...
			break;
		
		
		// *************************************************************************
		// INITIALIZE (prepare) import by getting configuration and reading XML file
		// *************************************************************************
		
		case 'initxml':
		
			// Retrieve the uploaded CSV file
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
		
		if ( $export_type )
		{
			$cid = JRequest::getVar( 'cid', array(0), $hash='default', 'array' );
			JArrayHelper::toInteger($cid, array(0));
			
			$table = strtolower(JRequest::getCmd('table', 'flexicontent_fields'));
			
			if (!in_array($table, self::$allowed_tables) ) {
				JError::raiseWarning( 403, JText::_( 'FLEXI_NO_ACCESS' . ' Table: ' .$table. ' not in allowed tables' ) );
				$this->setRedirect( $_SERVER['HTTP_REFERER'] );
				return;
			}
			
			$table_name = '#__'.$table;
			$id_colname = 'id';
			$rows = $model->getTableRows($table_name, $id_colname, $cid, true);
			
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
		
		// No specific format, get export LIST and export it
		else {
			$conf = $session->get('appsman_export', array(), 'flexicontent');	
			$content = '';
			foreach(self::$allowed_tables as $table)
			{
				if ( empty($conf[$table]) ) continue;
				$cid = array_keys($conf[$table]);
				$table_name = '#__'.$table;
				$id_colname = 'id';
				$rows = $model->getTableRows($table_name, $id_colname, $cid, true);
				$content .= $model->create_XML_records($rows, $table_name, $id_colname, $clear_id=false);
				$customHandler = 'getExtraData_'.$table;
				if ( is_callable(array($model, $customHandler)) ) {
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
		$zip->addFromString($filename, $content);
		$zip->close();
		$filesize = filesize($archivepath);
		//echo $content;exit;
		
		
		// *******************
		// Output HTTP headers
		// *******************
		
		header("Pragma: public"); // required
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private", false); // required for certain browsers
		header('Content-Type: application/force-download');  //header('Content-Type: text/'.$export_type);
		//header("Content-Disposition: attachment; filename=\"".$filename."\";");  // quote to allow spaces in filenames
		header("Content-Disposition: attachment; filename=\"".$archivename."\";");  // quote to allow spaces in filenames
		header("Content-Transfer-Encoding: Binary");
		//header("Content-Length: ".strlen($content));
		header("Content-Length: ".$filesize);
		//echo $content;
		
		
		// *******************************
		// Finally read file and output it
		// *******************************
		
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
		unlink($archivepath); // remove temporary file
		
		$app->close();
	}
}
