<?php
/**
 * @version 1.5 stable $Id: filemanager.php 1846 2014-02-14 02:36:41Z enjoyman@gmail.com $
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
 * FLEXIcontent Component Files Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerFilemanager extends FlexicontentController
{
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();
	}
	
	
	/**
	 * count the rows
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function countrows()
	{
		$start_microtime = microtime(true);
		// Check for request forgeries
		//JRequest::checkToken() or jexit( 'Invalid Token' );
		//$params = JComponentHelper::getParams( 'com_flexicontent' );
		
		@ob_end_clean();
		$indexer = JRequest::getVar('indexer','fileman_default');
		$rebuildmode = JRequest::getVar('rebuildmode','');
		$session = JFactory::getSession();
		$db  = JFactory::getDBO();
		$app = JFactory::getApplication();
		
		// Actions according to rebuildmode
		if ($indexer!='fileman_default') {
			die("'rebuildmode': '".$rebuildmode."'. not supported");
		}
		
		// Get ids of files to index
		$fmanmodel = $this->getModel('filemanager');
		$file_ids = $fmanmodel->getFileIds($skip_urls=true);
		
		// Set file ids into session to avoid recalculation ...
		$session->set($indexer.'_items_to_index', $file_ids, 'flexicontent');
		
		echo 'success';  //echo count($fieldids)*count($itemids).'|';
		// WARNING: json_encode will output object if given an array with gaps in the indexing
		//echo '|'.json_encode($itemids);
		//echo '|'.json_encode($fieldids);
		echo '|'.count($file_ids);
		echo '|'.count(array());
		
		$elapsed_microseconds = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		$_total_runtime = $elapsed_microseconds;
		$_total_queries = 0;
		echo sprintf( '|0| Server execution time: %.2f secs ', $_total_runtime/1000000) . ' | Total DB updates: '. $_total_queries;
		
		$session->set($indexer.'_total_runtime', $elapsed_microseconds ,'flexicontent');
		$session->set($indexer.'_total_queries', 0 ,'flexicontent');
		exit;
	}
	
	
	function index()
	{
		$start_microtime = microtime(true);
		$session = JFactory::getSession();
		$db = JFactory::getDBO();
		
		@ob_end_clean();
		$indexer = JRequest::getVar('indexer','fileman_default');
		$rebuildmode = JRequest::getVar('rebuildmode','');
		
		$items_per_call = JRequest::getVar('items_per_call', 20);  // Number of item to index per HTTP request
		$itemcnt = JRequest::getVar('itemcnt', 0);                 // Counter of items indexed so far, this is given via HTTP request
		
		// Actions according to rebuildmode
		if ($indexer!='fileman_default') {
			die("'rebuildmode': '".$rebuildmode."'. not supported");
		}
		
		// Get items ids that have value for any of the searchable fields, but use session to avoid recalculation
		$itemids = $session->get($indexer.'_items_to_index', array(),'flexicontent');
		
		
		// Get query size limit
		$query = "SHOW VARIABLES LIKE 'max_allowed_packet'";
		$db->setQuery($query);
		$_dbvariable = $db->loadObject();
		$max_allowed_packet = flexicontent_upload::parseByteLimit(@ $_dbvariable->Value);
		$max_allowed_packet = $max_allowed_packet ? $max_allowed_packet : 256*1024;
		$query_lim = (int) (3 * $max_allowed_packet / 4);
		//echo 'fail|'.$query_lim; exit;
		
		// Get script max
		$max_execution_time = ini_get("max_execution_time");
		//echo 'fail|'.$max_execution_time; exit;
		
		
		$query_count = 0;
		$max_items_per_query = 100;
		$max_items_per_query = $max_items_per_query > $items_per_call ? $items_per_call : $max_items_per_query;
		$cnt = $itemcnt;
		while($cnt < count($itemids) && $cnt < $itemcnt+$items_per_call)
		{
			$query_itemids = array_slice($itemids, $cnt, $max_items_per_query);
			$cnt += $max_items_per_query;
			
			// Get files
			$data_query = "SELECT * "
				." FROM #__flexicontent_files"
				." WHERE id IN (".implode(', ',$query_itemids).")"
				;
			$db->setQuery($data_query);
			$file_data = $db->loadObjectList('id');
			
			$vindex = array();
			
			// For current item: Loop though all searchable fields according to their type
			foreach($file_data as $file_id => $file)
			{
				$path = $file->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;  // JPATH_ROOT . DS . <media_path | file_path>
				$file_path = $path . DS . $file->filename;		
				$file->size = !$file->url && file_exists($file_path) ? filesize($file_path) : 0;
				$vindex[] = ' WHEN '.$file->id.' THEN '.$file->size;
			}
			
			// Create query that will update/insert data into the DB
			unset($query);
			$query = 'UPDATE #__flexicontent_files '
				.'  SET size = CASE id '
				.  implode('', $vindex)
				. '  END '
				.' WHERE id IN ('.implode(', ',$query_itemids).')';
			$db->setQuery($query);
			$db->execute();
			$query_count++;
			
			$elapsed_microseconds = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			$elapsed_seconds = $elapsed_microseconds / 1000000.0;
			if ($elapsed_seconds > $max_execution_time/3 || $elapsed_seconds > 5) break;
		}
		
		$elapsed_microseconds = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		if ( $session->has($indexer.'_total_runtime', 'flexicontent')) {
			$_total_runtime = $session->get($indexer.'_total_runtime', 0,'flexicontent');
		} else {
			$_total_runtime = 0;
		}
		$_total_runtime += $elapsed_microseconds;
		$session->set($indexer.'_total_runtime', $_total_runtime ,'flexicontent');
		
		if ( $session->has($indexer.'_total_queries', 'flexicontent')) {
			$_total_queries = $session->get($indexer.'_total_queries', 0,'flexicontent');
		} else {
			$_total_queries = 0;
		}
		$_total_queries += $query_count;
		$session->set($indexer.'_total_queries', $_total_queries ,'flexicontent');
		
		echo sprintf( $cnt.' | Server execution time: %.2f secs ', $_total_runtime/1000000) . ' | Total DB updates: '. $_total_queries;
		exit;
	}
	
	
	/*function purge()
	{
		$model = $this->getModel('filemanager');
		$model->purge();
		$msg = JText::_('FLEXI_ITEMS_PURGED');
		$this->setRedirect('index.php?option=com_flexicontent&view=filemanager', $msg);
	}*/
	
}
