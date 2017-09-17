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

use Joomla\String\StringHelper;

// Register autoloader for parent controller, in case controller is executed by another component
JLoader::register('FlexicontentController', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'controller.php');

/**
 * FLEXIcontent Component Import Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerImport extends FlexicontentController
{
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();

		// Register Extra task
		$this->registerTask( 'initcsv',   'importcsv');
		$this->registerTask( 'clearcsv',  'importcsv');
		$this->registerTask( 'testcsv',   'importcsv');
	}
	
	function processcsv() {
		parent::display();
	}
	
	/**
	 * Logic to import csv files with content item data
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function importcsv()
	{
		$app = JFactory::getApplication();
		$jinput = $app->input;

		// Check for request forgeries
		if (JRequest::getCmd( 'task' )!='importcsv') {
			JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));	
			echo '<link rel="stylesheet" href="'.JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css?'.FLEXI_VHASH.'" />';
			$fc_css = JUri::base(true).'/components/com_flexicontent/assets/css/j3x.css';
			echo '<link rel="stylesheet" href="'.$fc_css.'?'.FLEXI_VHASH.'" />';
		} else {
			// output this before every other output
			echo 'success||||'. JSession::getFormToken() .'||||';
		}
		
		// Get item model
		$itemmodel = $this->getModel('item');
		$model     = $this->getModel('import');
		
		// Set some variables
		$link  = 'index.php?option=com_flexicontent&view=import';  // $_SERVER['HTTP_REFERER'];
		$task  = $jinput->get('task', '', 'cmd');
		$db    = JFactory::getDbo();
		$user  = JFactory::getUser();
		$session = JFactory::getSession();
		$has_zlib = function_exists ( "zlib_encode" ); //version_compare(PHP_VERSION, '5.4.0', '>=');
		
		$parse_log = "\n\n\n".'<b>please click</b> <a href="'.$link.'">here</a> to return previous page'."\n\n\n";
		$log_filename = 'importcsv_'.($user->id).'.php';
		
		jimport('joomla.log.log');
		JLog::addLogger(
			array(
				'text_file' => $log_filename,  // Sets the target log file
        'text_entry_format' => '{DATETIME} {PRIORITY} {MESSAGE}'  // Sets the format of each line
			),
			JLog::ALL,  // Sets messages of all log levels to be sent to the file
			array('com_flexicontent.importcsv')  // category of logged messages
		);


		// ***
		// *** Execute according to task
		// ***

		switch ($task)
		{


		// ***
		// *** RESET/CLEAR an already started import task, e.g. import process was interrupted for some reason
		// ***
		
		case 'clearcsv':
		
			// Clear any import data from session
			$conf = $has_zlib ? base64_encode(zlib_encode(serialize(null), -15)) : base64_encode(serialize(null));
			
			$session->set('csvimport_config', $conf, 'flexicontent');
			$session->set('csvimport_lineno', 0, 'flexicontent');
			
			// Set a message that import task was cleared and redirect
			$app->enqueueMessage( 'Import task cleared' , 'notice' );
			$this->setRedirect( $link );
			return;
			break;
		
		
		// ***
		// *** CONTINUE an already started (multi-step) import task
		// ***

		case 'importcsv':
		
			$conf   = $session->get('csvimport_config', "", 'flexicontent');
			$conf		= unserialize( $conf ? ($has_zlib ? zlib_decode(base64_decode($conf)) : base64_decode($conf)) : "" );
			
			$lineno = $session->get('csvimport_lineno', 999999, 'flexicontent');
			if ( empty($conf) ) {
				$app->enqueueMessage( 'Can not continue import, import task not initialized or already finished' , 'error');
				$this->setRedirect( $link );
				return;
			}
			
			// CONTINUE to do the import
			// ...
			break;
		
		
		// ***
		// *** INITIALIZE (prepare) import by getting configuration and reading CSV file
		// ***
		
		case 'initcsv':
		case 'testcsv':
		
			$conf  = array();
			$conf['failure_count'] = $conf['success_count'] = 0;
			
			// Retrieve Basic configuration
			$conf['id_col'] = $jinput->get('id_col', 0, 'int');
			$conf['type_id']  = $jinput->get('type_id', 0, 'int');
			$conf['language'] = $jinput->get('language', '', 'string');
			$conf['state']    = $jinput->get('state', '', 'int');
			$conf['access']   = $jinput->get('access', '', 'int');
			
			// Main and secondary categories
			$conf['maincat'] 	= $jinput->get('maincat', 0, 'int');
			$conf['maincat_col'] = $jinput->get('maincat_col', 0, 'int');
			$conf['seccats'] = $jinput->get('seccats', array(), 'array');
			JArrayHelper::toInteger($conf['seccats']);
			$conf['seccats_col'] = $jinput->get('seccats_col', 0, 'int');
			
			// Tags
			$conf['tags_col'] = $jinput->get('tags_col', 0, 'int');
			
			// Publication: META data
			$conf['created_by_col'] = $jinput->get('created_by_col', 0, 'int');
			$conf['modified_by_col'] = $jinput->get('modified_by_col', 0, 'int');
			
			// Publication: META data
			$conf['metadesc_col'] = $jinput->get('metadesc_col', 0, 'int');
			$conf['metakey_col'] = $jinput->get('metakey_col', 0, 'int');
			
			// Publication: dates
			$conf['modified_col'] = $jinput->get('modified_col', 0, 'int');
			$conf['created_col'] = $jinput->get('created_col', 0, 'int');
			$conf['publish_up_col'] = $jinput->get('publish_up_col', 0, 'int');
			$conf['publish_down_col'] = $jinput->get('publish_down_col', 0, 'int');
			
			// Advanced configuration
			$conf['ignore_unused_cols'] = $jinput->get('ignore_unused_cols', 0, 'int');
			$conf['items_per_step'] = $jinput->get('items_per_step', 5, 'int');
			if ( $conf['items_per_step'] > 50 ) $conf['items_per_step'] = 50;
			if ( ! $conf['items_per_step'] ) $conf['items_per_step'] = 5;
			
			// CSV file format
			$conf['mval_separator']   = $jinput->get('mval_separator', '', 'string');
			$conf['mprop_separator']  = $jinput->get('mprop_separator', '', 'string');
			$conf['field_separator']  = $jinput->get('field_separator', '', 'string');
			$conf['enclosure_char']   = $jinput->get('enclosure_char', '', 'string');
			$conf['record_separator'] = $jinput->get('record_separator', '', 'string');
			$conf['debug_records']    = $jinput->get('debug_records', 0, 'int');  // Debug, print parsed data without importing
			
			
			// ***
			// *** Obligatory form fields, js validation should have prevented form submission but check anyway
			// ***
			
			// Check for the required Content Type Id
			if( !$conf['type_id'] ) {
				$app->enqueueMessage('Please select Content Type for the imported items', 'error');
				$app->redirect($link);
			}
			
			// Check for the required main category
			if( !$conf['maincat'] && !$conf['maincat_col'] ) {
				$app->enqueueMessage('Please select main category for the imported items', 'error');
				$app->redirect($link);
			}
			
			
			// ***
			// *** Check for (required) CSV file format variables, js validation should have prevented form submission but check anyway
			// ***

			if( $conf['mval_separator']=='' || $conf['mprop_separator']=='') {
				$app->enqueueMessage('CSV format not valid, please enter multi-value, and multi-property Separators', 'error');
				$app->redirect($link);
			}

			if( $conf['field_separator']=='' || $conf['record_separator']=='' ) {
				$app->enqueueMessage('CSV format not valid, please enter Field Separator and Item Separator', 'error');
				$app->redirect($link);
			}
			
			// Retrieve the uploaded CSV file
			$csvfile = @$_FILES["csvfile"]["tmp_name"];
			if(!is_file($csvfile)) {
				$app->enqueueMessage('Upload file error!', 'error');
				$app->redirect($link);
			}
			
			
			// ***
			// *** Retrieve CSV file format variables, EXPANDING the Escape Characters like '\n' ... provided by the form
			// ***

			$pattern = '/(?<!\\\)(\\\(?:n|r|t|v|f|[0-7]{1,3}|x[0-9a-f]{1,2}))/i';
			$replace = 'eval(\'return "$1";\')';
			
			$conf['mval_separator']  = preg_replace_callback(
				$pattern,
				function ($matches) {
					$r = $matches[1];
					eval("\$r = \"$r\";");
					return $r;
				},
				$conf['mval_separator']
			);
			$conf['mprop_separator']  = preg_replace_callback(
				$pattern,
				function ($matches) {
					$r = $matches[1];
					eval("\$r = \"$r\";");
					return $r;
				},
				$conf['mprop_separator']
			);
			$conf['field_separator']  = preg_replace_callback(
				$pattern,
				function ($matches) {
					$r = $matches[1];
					eval("\$r = \"$r\";");
					return $r;
				},
				$conf['field_separator']
			);
			$conf['enclosure_char']   = preg_replace_callback(
				$pattern,
				function ($matches) {
					$r = $matches[1];
					eval("\$r = \"$r\";");
					return $r;
				},
				$conf['enclosure_char']
			);
			$conf['record_separator'] = preg_replace_callback(
				$pattern,
				function ($matches) {
					$r = $matches[1];
					eval("\$r = \"$r\";");
					return $r;
				},
				$conf['record_separator']
			);


			// ***
			// *** Read & Parse the CSV file according the given format
			// ***

			$contents = FLEXIUtilities::csvstring_to_array(file_get_contents($csvfile), $conf['field_separator'], $conf['enclosure_char'], $conf['record_separator']);
			
			// Basic error checking, for empty data
			if(!$contents || count($contents[0])<=0) {
				$app->enqueueMessage('CSV file format is not correct!', 'error');
				$app->redirect($link);
			}


			// ***
			// *** Get field names (from the header line (row 0), and remove it form the data array
			// ***

			$conf['columns'] = flexicontent_html::arrayTrim($contents[0]);
			unset($contents[0]);
			$q = 'SELECT id, name, field_type, label FROM #__flexicontent_fields AS fi'
				.' JOIN #__flexicontent_fields_type_relations AS ftrel ON ftrel.field_id = fi.id AND ftrel.type_id='.$conf['type_id'];
			$db->setQuery($q);
			$conf['thefields'] = $db->loadObjectList('name');
			unset($conf['thefields']['tags']); // Prevent Automated Raw insertion of tags, we will use special code


			// ***
			// *** Check for REQUIRED columns and decide CORE property columns to use
			// ***

			$core_props = array();
			if ( $conf['id_col'] && !in_array('id', $conf['columns']) )
			{
				$app->enqueueMessage('CSV file lacks column <b>\'id\'</b> (Item ID)', 'error');
				$app->redirect($link);
			}
			else if ($conf['id_col']) $core_props['id'] = 'Item ID';


			if(!in_array('title', $conf['columns']))
			{
				$app->enqueueMessage('CSV file lacks column <b>\'title\'</b>', 'error');
				$app->redirect($link);
			}


			$core_props['title'] = 'Title (core)';
			$core_props['text']  = 'Description (core)';
			$core_props['alias'] = 'Alias (core)';


			if ( $conf['language'] == '-99' && !in_array('language', $conf['columns']) )
			{
				$app->enqueueMessage('CSV file lacks column <b>\'language\'</b>', 'error');
				$app->redirect($link);
			}
			else if ($conf['language'] == '-99') $core_props['language'] = 'Language';


			if ( $conf['state'] == '-99' && !in_array('state', $conf['columns']) )
			{
				$app->enqueueMessage('CSV file lacks column <b>\'state\'</b>', 'error');
				$app->redirect($link);
			}
			else if ( $conf['state'] == '-99' ) $core_props['state'] = 'State';


			if ( $conf['access']===0 && !in_array('access', $conf['columns']) )
			{
				$app->enqueueMessage('CSV file lacks column <b>\'access\'</b>', 'error');
				$app->redirect($link);
			}
			else if ( $conf['access']===0 ) $core_props['access'] = 'Access';


			if ( $conf['maincat_col'] && !in_array('catid', $conf['columns']) )
			{
				$app->enqueueMessage('CSV file lacks column <b>\'catid\'</b> (Primary category)', 'error');
				$app->redirect($link);
			}
			else if ($conf['maincat_col']) $core_props['catid'] = 'Primary category';


			if ( $conf['seccats_col'] && !in_array('cid', $conf['columns']) )
			{
				$app->enqueueMessage('CSV file lacks column <b>\'cid\'</b> (Secondary categories)', 'error');
				$app->redirect($link);
			}
			else if ($conf['seccats_col']) $core_props['cid'] = 'Secondary categories';


			if ( $conf['created_col'] && !in_array('created', $conf['columns']) )
			{
				$app->enqueueMessage('CSV file lacks column <b>\'created\'</b> (Creation date)', 'error');
				$app->redirect($link);
			}
			else if ($conf['created_col']) $core_props['created'] = 'Creation Date';


			if ( $conf['created_by_col'] && !in_array('created_by', $conf['columns']) )
			{
				$app->enqueueMessage('CSV file lacks column <b>\'created_by\'</b> (Creator - Author)', 'error');
				$app->redirect($link);
			}
			else if ($conf['created_by_col']) $core_props['created_by'] = 'Creator (Author)';


			if ( $conf['modified_col'] && !in_array('modified', $conf['columns']) )
			{
				$app->enqueueMessage('CSV file lacks column <b>\'modified\'</b> (Modification date)', 'error');
				$app->redirect($link);
			}
			else if ($conf['modified_col']) $core_props['modified'] = 'Modification Date';


			if ( $conf['modified_by_col'] && !in_array('modified_by', $conf['columns']) )
			{
				$app->enqueueMessage('CSV file lacks column <b>\'modified_by\'</b> (Last modifier)', 'error');
				$app->redirect($link);
			}
			else if ($conf['modified_by_col']) $core_props['modified_by'] = 'Last modifier';


			if ( $conf['metadesc_col'] && !in_array('metadesc', $conf['columns']) )
			{
				$app->enqueueMessage('CSV file lacks column <b>\'metadesc\'</b> (META Description)', 'error');
				$app->redirect($link);
			}
			else if ($conf['metadesc_col']) $core_props['metadesc'] = 'META Description';


			if ( $conf['metakey_col'] && !in_array('metakey', $conf['columns']) )
			{
				$app->enqueueMessage('CSV file lacks column <b>\'metakey\'</b> (META Keywords)', 'error');
				$app->redirect($link);
			}
			else if ($conf['metakey_col']) $core_props['metakey'] = 'META Keywords';


			if ( $conf['publish_up_col'] && !in_array('publish_up', $conf['columns']) )
			{
				$app->enqueueMessage('CSV file lacks column <b>\'publish_up\'</b> (Start publication date)', 'error');
				$app->redirect($link);
			}
			else if ($conf['publish_up_col']) $core_props['publish_up'] = 'Start publication date';


			if ( $conf['publish_down_col'] && !in_array('publish_down', $conf['columns']) )
			{
				$app->enqueueMessage('CSV file lacks column <b>\'publish_down\'</b> (End publication Date)', 'error');
				$app->redirect($link);
			}
			else if ($conf['publish_down_col']) $core_props['publish_down'] = 'End publication Date';


			if ( $conf['tags_col']==1 && !in_array('tags_names', $conf['columns']) )
			{
				$app->enqueueMessage('CSV file lacks column <b>\'tags_names\'</b> (Comma separated list of tag names)', 'error');
				$app->redirect($link);
			}
			else if ($conf['tags_col']==1)
			{
				$core_props['tags_names'] = 'Tag names';
				$tags_model	= $this->getModel('tags');
			}


			if ( $conf['tags_col']==2 && !in_array('tags_raw', $conf['columns']) )
			{
				$app->enqueueMessage('CSV file lacks column <b>\'tags_raw\'</b> (Comma separated list of tag ids)', 'error');
				$app->redirect($link);
			}
			else if ( $conf['tags_col']==2 )
			{
				$core_props['tags_raw'] = 'Tags';
				$tags_model	= $this->getModel('tags');
			}
			$conf['core_props'] = & $core_props;


			// ***
			// *** Verify that custom specified item ids do not already exist
			// ***

			$conf['existing_ids'] = array();
			if ( $conf['id_col'] )
			{
				// Get 'id' column no
				$id_col_no = 0;
				foreach($conf['columns'] as $col_no => $column)
				{
					if ( $conf['columns'][$col_no] == 'id' )
					{
						$id_col_no = $col_no;
						break;
					}
				}
				
				// Get custom IDs in csv file
				$custom_id_arr = array();
				foreach($contents as $fields)
				{
					$custom_id_arr[] = (int) $fields[$id_col_no];
				}
				$custom_id_list = "'" . implode("','", $custom_id_arr) ."'";

				// Cross check them if they already exist in the DB
				$q = "SELECT id FROM #__content WHERE id IN (".$custom_id_list.")";
				$db->setQuery($q);
				$conf['existing_ids'] =  array_flip($db->loadColumn());

				// Throw error if we are only IMPORTING (creating) new items but existing item ids were found
				if ( $conf['id_col'] == 1 && $conf['existing_ids'] && count($conf['existing_ids']) )
				{
					$app->enqueueMessage('File has ' . count($conf['existing_ids']).' item IDs that already exist: ' . implode(", ",$conf['existing_ids']) . ', <br/>Please fix or enable -updating- of existing items too', 'error');
					$app->redirect($link);
				}

				// Throw error if we are only UPDATING new items but not all item ids were found
				if ( $conf['id_col'] == 3 && count($conf['existing_ids']) < count($custom_id_arr) )
				{
					$existing_ids = array_keys($conf['existing_ids']);
					$missing_count = count($custom_id_arr) - count($existing_ids);
					$missing_ids   = array_diff($custom_id_arr, $existing_ids);
					$app->enqueueMessage('File has ' . $missing_count . ' item IDs that do not exist in DB : ' . implode(", ", $missing_ids) . ', <br/>Please fix or enable -creating- of existing items too', 'error');
					$app->redirect($link);
				}
			}


			// ***
			// *** Verify that all non core property columns are field names
			// ***

			$unused_columns = array();
			foreach($conf['columns'] as $colname)
			{
				if ( !isset($conf['core_props'][$colname]) && !isset($conf['thefields'][$colname]) )
				{
					$unused_columns[] = $colname;
				}
			}

			if ( count($unused_columns) )
			{
				$types = flexicontent_html::getTypesList( $_type_ids=false, $_check_perms = false, $_published=true);

				if (!$conf['ignore_unused_cols'])
				{
					$app->enqueueMessage('
						File has unused '.count($unused_columns).' columns : [ ' . implode(' ], [ ', $unused_columns) . ' ]' .
						' <br/><br/>Their fields (fieldnames in column header) are not assigned to chosen content type : <b>' . $types[$conf['type_id']]->name . '</b>' .
						' <br/><br/>Please enable option: <b>\'Ignore unused columns\'</b>',
					'warning');
					$app->redirect($link);
				}
				else
				{
					$app->enqueueMessage('
						File has unused '.count($unused_columns).' columns: <b>'.implode(', ', $unused_columns).'</b>'.
						' <br/>These columns will be ignored, because their fields (fieldnames in column header) are not assigned to chosen <b>content type</b> :' . $types[$conf['type_id']]->name,
					'notice');
				}
			}


			// Trim item's data
			foreach($contents as $fields) $fields = flexicontent_html::arrayTrim($fields);

			// Set csvfile contens and columns information
			$conf['contents']   = & $contents;


			// ***
			// *** Verify that imported files exist in the media/documents folders
			// ***

			// Get fields that use files
			$conf['media_folder'] = $jinput->get('media_folder', '', 'string');
			$conf['docs_folder']  = $jinput->get('docs_folder', '', 'string');
			
			$this->checkfiles($conf, $parse_log, $task);
			$this->parsevalues($conf, $parse_log, $task);
			
			if ($task == 'initcsv')
			{
				// Set import configuration and file data into session
				$session->set('csvimport_config',
					( $has_zlib ? base64_encode(zlib_encode(serialize($conf), -15)) : base64_encode(serialize($conf)) ),
					'flexicontent');
				
				$session->set('csvimport_lineno', 0, 'flexicontent');
				
				// Set a message that import task was prepared and redirect
				$app->enqueueMessage(
					'Import task prepared. <br/>'.
					'File has '. count($conf['contents_parsed']) .' records (content items)'.
					' and '. count($conf['columns']) .' columns (fields)' , 'message'
				);
				$this->setRedirect( $link );
				return;
			}

			// ELSE -- task == 'testcsv'
			else
			{
				$conf['debug_records'] = $conf['debug_records'] ? $conf['debug_records'] : 2;
			}
			
			break;


		// ***
		// *** UNKNWOWN task, terminate
		// ***

		default:

			// Set an error message about unknown task and redirect
			$app->enqueueMessage('Unknown task: '.$task, 'error');
			$this->setRedirect( $link );
			return;

			break;
		}


		// ***
		// *** Handle each row (item) using store() method of the item model to create the items
		// ***

		if ($conf['tags_col']) $tags_model = $this->getModel('tags');
		
		$colcount  = count($conf['columns']);
		$itemcount = count($conf['contents_parsed']);
		$items_per_call = $jinput->get('items_per_call', 0, 'int');
		$jinput->set('import_media_folder', $conf['media_folder']);
		$jinput->set('import_docs_folder', $conf['docs_folder']);
		
		$lineno  = $task=='testcsv'  ?  1  :  $lineno + 1;
		$linelim = $items_per_call ?  $lineno + $items_per_call - 1 : $itemcount;
		$linelim = $linelim > $itemcount  ?  $itemcount  :  $linelim;
		//echo "lineno: $lineno -- linelim: $linelim<br/>";
		
		for( ; $lineno <= $linelim; $lineno++)
		{
			$_d = & $conf['contents_parsed'][$lineno];
			$data = array();
			$data['custom'] = array();

			$data['type_id'] = $conf['type_id'];
			$data['language']= $conf['language'];
			$data['catid']   = $conf['maincat'];   // Default value maybe overriden by column
			$data['cid']     = $conf['seccats'];   // Default value maybe overriden by column
			$data['vstate']  = 2;
			$data['state']   = $conf['state'];
			$data['access']  = $conf['access'];

			// Prepare request variable used by the item's Model
			if ( $task != 'testcsv' ) foreach($_d as $fieldname => $field_values)
			{
				if ( $fieldname=='tags_names' )
				{
					if ($conf['tags_col']==1) {
						// Get tag names from comma separated list, filtering out bad characters
						$remove = array("\n", "\r\n", "\r");
						$tns_list = str_replace($remove, ' ', $field_values);
						$tns_list = strip_tags($tns_list);
						$tns_list = preg_replace("/[\"\\\]/u", "", $tns_list);  //  "/[\"'\\\]/u"
						$tns = array_unique(preg_split("/\s*,\s*/u", $tns_list));
						$tns_quoted = array();
						foreach($tns as $tindex => $tname)  if ($tname) $tns_quoted[] = $db->Quote($tname);
						
						if (count($tns_quoted))
						{
							$tns_list_quoted = implode(",", $tns_quoted);
							$q = "SELECT name FROM #__flexicontent_tags WHERE name IN (". $tns_list_quoted .")";
							$db->setQuery($q);
							$tns_e = $db->loadColumn();
							$tns_m = array_diff( $tns , $tns_e );
							
							if ( count($tns_m) ) {
								// Create a newline separated list of tag names and then import missing tags,
								// thus making sure they are inserted into the tags DB table if not already present
								$tns_list_m = implode("\n", $tns_m);
								$tags_model->importList($tns_list_m);
							}
							
							// Get tag ids
							$q = "SELECT id FROM #__flexicontent_tags WHERE name IN (". $tns_list_quoted .")";
							$db->setQuery($q);
							$data['tag'] = $db->loadColumn();
						}
					}
				}

				else if ( $fieldname=='tags_raw' )
				{
					if ($conf['tags_col']==2) {
						// Get tag ids from comma separated list, filtering out bad characters
						$_tis_list = preg_replace("/[\"'\\\]/u", "", $field_values);
						$_tis = array_unique(array_map('intval', $_tis));
						$_tis = array_flip( $_tis );
						
						// Check to use only existing tag ids
						$_tis_list = implode(",", array_keys($_tis));
						$q = "SELECT id FROM #__flexicontent_tags WHERE id IN (". $_tis_list .")";
						$db->setQuery($q);
						$data['tag'] = $db->loadColumn();
					}
				}

				else if ( isset($conf['core_props'][$fieldname]) ) {
					$data[$fieldname] = $field_values;
				}

				else
				{
					$data['custom'][$fieldname] = $field_values;
				}
			}

			// Before setting any new values try to load item if item ID was given
			$c_item_id = $conf['id_col'] && !empty($data['id'])
				? $data['id']
				: 0;


			// ***
			// *** CREATE CASE with / without ID column --OR-- item ID not given --OR-- item ID does not exist
			// *** NOTE: we already check above,
			// ***  that for create-only, none of the given item ID exists in the DB
			// ***  that for update-only, all given item IDs exist in the DB
			// ***

			if ( $conf['id_col'] <= 1 || empty($c_item_id) || !isset($conf['existing_ids'][$c_item_id]) )
			{
				$data['id'] = 0;
			}


			// ***
			// *** CREATE / UPDATE --OR-- UPDATE-only CASEs with item ID given
			// ***

			else
			{
				// Try to Load existing item into the ITEM model
				$item = $itemmodel->getItem($c_item_id, $check_view_access=false, $no_cache=true, $force_version=0);
				if ($item)
				{
					// Maintain content type for existing items
					$data['type_id'] = $item->type_id;

					// IMPORTANT: Get existing field values for the item
					$items = array($item);
					$items_custom_values = FlexicontentFields::getCustomFieldValues($items, 'item');
					$data_custom = $data['custom'];  // Backup field values from file
					$data['custom'] = reset($items_custom_values); // Get data of first item

					// Override existing item field values with those from file
					foreach($data_custom as $i => $v)
					{
						$data['custom'][$i] = $v;
					}
				}

				// INTERNAL ERROR, item could not be loaded, but we have checked above that it does exist, so this indicates a bug in our code
				else
				{
					$data['id'] = -1;
				}
			}
			$isNew = $data['id'] == 0;


			$session->set('csvimport_lineno', $lineno, 'flexicontent');


			// If testing format then output some information
			if ( $task == 'testcsv' )
			{
				if ($lineno==1)
				{
					$parse_log .= '
						<span class="fc-mssg fc-info">
						Testing file format <br/>
						COLUMNS: '. implode(', ', $conf['columns']) .'<br/>
						</span><hr/>
					';
				}
				foreach ($_d as $i => $flddata) if (is_string($_d[$i]))
				{
					if ( StringHelper::strlen($_d[$i]) > 80 ) $_d[$i] = StringHelper::substr(strip_tags($_d[$i]), 0, 80) . ' ... ';
				}

				if ($lineno <= $conf['debug_records'])
				{
					$parse_log .= "<pre><b>Item no $lineno:</b>\n". print_r($_d,true) ."</pre><hr/>";
				}
				else
				{
					$parse_log .= "<b>Item no $lineno:</b> <br/>".
					"<u>TITLE</u>: ". $_d['title'] ."<br/>".
					"<u>TEXT</u>: ". $_d['text'] ."<hr/>";
				}
			}

			// Internal error, this should not happen anyway, but continue with other items
			else if ($data['id'] == -1)
			{
				$msg = 'Internal Error item with ID: "' . $c_item_id . " could not be loaded in order to be updated";
				JLog::add($msg, JLog::WARNING, 'com_flexicontent.importcsv');
				echo $msg."<br/>";
			}

			// Otherwise (if not testing) try to create / update the item by using Item Model's store() method
			else if ( !$itemmodel->store($data) )
			{
				$conf['failure_count']++;
				$msg = 'Failed item no: '. $lineno . ". titled as: '" . $data['title'] . "' : ". $itemmodel->getError();
				JLog::add($msg, JLog::WARNING, 'com_flexicontent.importcsv');
				echo $msg."<br/>";
			}
			
			// Item record successfully stored
			else
			{
				$conf['success_count']++;
				$msg = 'Imported item no: '. $lineno . ". titled as: '" . $data['title'] . "'" ;
				JLog::add($msg, JLog::INFO, 'com_flexicontent.importcsv');
				echo $msg."<br/>";
				
				// Remap 'ID' of item (when 'id' column is being used)
				if ( in_array($conf['id_col'], array(1, 2)) && $c_item_id && $isNew )
				{
					$item_id = $itemmodel->getId();
					$q = "UPDATE #__content SET id='".$c_item_id."' WHERE id='".$item_id."'";
					$db->setQuery($q);
					$db->execute();
					$q = "UPDATE #__flexicontent_items_ext SET item_id='".$c_item_id."' WHERE item_id='".$item_id."'";
					$db->setQuery($q);
					$db->execute();
					$q = "UPDATE #__flexicontent_items_tmp SET id='".$c_item_id."' WHERE id='".$item_id."'";
					$db->setQuery($q);
					$db->execute();
					$q = "UPDATE #__flexicontent_tags_item_relations SET itemid='".$c_item_id."' WHERE itemid='".$item_id."'";
					$db->setQuery($q);
					$db->execute();
					$q = "UPDATE #__flexicontent_cats_item_relations SET itemid='".$c_item_id."' WHERE itemid='".$item_id."'";
					$db->setQuery($q);
					$db->execute();
					$q = "UPDATE #__flexicontent_fields_item_relations SET item_id='".$c_item_id."' WHERE item_id='".$item_id."'";
					$db->setQuery($q);
					$db->execute();
					$q = "UPDATE #__flexicontent_items_versions SET item_id='".$c_item_id."' WHERE item_id='".$item_id."'";
					$db->setQuery($q);
					$db->execute();
					$q = "UPDATE #__flexicontent_versions SET item_id='".$c_item_id."' WHERE item_id='".$item_id."'";
					$db->setQuery($q);
					$db->execute();
					$q = "UPDATE #__flexicontent_favourites SET itemid='".$c_item_id."' WHERE itemid='".$item_id."'";
					$db->setQuery($q);
					$db->execute();
					
					$q = "UPDATE #__assets SET id='".$c_item_id."' WHERE id='".$item_id."'";
					$db->setQuery($q);
					$db->execute();
				}
			}
		}
		//fclose($fp);
		
		// Done nothing more to do
		if ( $task == 'testcsv' )
		{
			echo $parse_log;
			echo "\n\n\n".'<b>please click</b> <a href="'.$link.'">here</a> to return previous page'."\n\n\n";
			jexit();
		}

		// When import is finished clean cache
		if ($lineno == $itemcount)
		{
			// Clean item's cache
			$cache = FLEXIUtilities::getCache($group='', 0);
			$cache->clean('com_flexicontent_items');
			$cache->clean('com_flexicontent_filters');
			$cache = FLEXIUtilities::getCache($group='', 1);
			$cache->clean('com_flexicontent_items');
			$cache->clean('com_flexicontent_filters');
			
			// Set a total results message and redirect
			$msg =
				'Imported items: '.$conf['success_count'].' , failed items: '.$conf['failure_count'] .
				', please review (in the logs folder) the import log file: '.$log_filename;
			//$app->enqueueMessage($msg, ($conf['failure_count']==0 && $conf['success_count']>0) ? 'message' : 'warning');
			//$this->setRedirect($link);  // commented out this via AJAX call now
		}
		
		jexit();
	}
	
	
	function checkfiles(&$conf, &$parse_log, &$task)
	{
		$app = JFactory::getApplication();
		$jinput = $app->input;

		$mfolder  = JPath::clean( JPATH_SITE .DS. $conf['media_folder'] .DS );
		$dfolder  = JPath::clean( JPATH_SITE .DS. $conf['docs_folder'] .DS );
		
		$ff_types_to_props = array('image'=>'originalname', 'file'=>'_value_');
		$ff_types_to_paths = array('image' => $mfolder, 'file'=> $dfolder);
		$ff_names_to_types = array();
		foreach ($conf['thefields'] as $_fld) {
			if ( isset($ff_types_to_props[$_fld->field_type]) )  $ff_names_to_types[$_fld->name] = $_fld->field_type;
		}
		
		// Fields that should be skipped from file checking
		$conf['skip_file_field'] = $jinput->get('skip_file_field', array(), 'array');
		
		// Get file field present in the header
		$ff_fields = array();
		foreach($conf['columns'] as $col_no => $column) {
			$fld_name = $conf['columns'][$col_no];
			if ( isset($ff_names_to_types[$fld_name]) )  $ff_fields[$col_no] = $fld_name;
		}
		
		// Get filenames from file columns
		$filedata_arr = array();
		foreach($conf['contents'] as $lineno => $fields)
		{
			foreach($ff_fields as $col_no => $fld_name) {
				$filedata_arr[$fld_name][$lineno] = $fields[$col_no];
			}
		}
		
		//echo "<pre>"; print_r($filedata_arr); jexit();
		if ( count($filedata_arr) )
		{
			$filenames_missing = array();
			
			foreach($filedata_arr as $fld_name => $filedata_arr)
			{
				$field_type = $ff_names_to_types[$fld_name];
				$prop_name = $ff_types_to_props[$field_type];
				$srcpath_original = $ff_types_to_paths[$field_type];
				
				foreach($filedata_arr as $lineno => $field_data) {
					// Split multi-value field
					$vals = strlen($field_data) ? preg_split("/[\s]*".$conf['mval_separator']."[\s]*/", $field_data) : array();
					$vals = flexicontent_html::arrayTrim($vals);
					unset($field_values);
					
					// Handle each value of the field
					$field_values = array();
					foreach ($vals as $i => $val)
					{
						// Split multiple property fields
						$props = strlen($val) ? preg_split("/[\s]*".$conf['mprop_separator']."[\s]*/", $val) : array();
						$props = flexicontent_html::arrayTrim($props);
						unset($prop_arr);
						
						// Handle each property of the value
						foreach ($props as $j => $prop) {
							if ( preg_match( '/\[-(.*)-\]=(.*)/', $prop, $matches) ) {
								$prop_arr[$matches[1]] = $matches[2];
							}
						}
						
						$filename = '';
						if ( !isset($prop_arr) ) {
							$filename = $val;
						} else {
							$filename = $prop_arr[$prop_name];
						}
						
						if ( $filename ) {
							//echo "<pre>"; print_r(JPath::clean( $srcpath_original  . $filename)); jexit();
							$srcfilepath  = JPath::clean( $srcpath_original  . $filename );
							if ( !JFile::exists($srcfilepath) ) {
								$filenames_missing[$fld_name][$filename][] = $lineno;
							}
						}
					}
				}
			}
			
			// Cross check them if they already exist in the DB
			$non_skipped_files_found = false;
			if ( count($filenames_missing) ) {
				foreach ($filenames_missing as $fld_name => $fld_files_missing) {
					if ( in_array($fld_name, $conf['skip_file_field']) ) continue;
					if (!$non_skipped_files_found) {
						$parse_log .= '<span class="fc-mssg fc-error"> CSV File has FILE references to <b>missing media / document files</b>, <br/>please fix or <b>set EACH field to be skipped</b> from checking'."\n";
						$non_skipped_files_found = true;
						if ($task != 'testcsv') {
							$parse_log .= '<br/><b>-- (DEBUG was auto enabled for first 2 records)</b>'."\n";
							$task = 'testcsv';
						}
						$parse_log .= '</span>';
					}

					$field_type = $ff_names_to_types[$fld_name];
					$srcpath_original = $ff_types_to_paths[$field_type];
					$parse_log .= '
					<span class="fc-mssg fc-warning">
					FIELD: <b> ' .$fld_name. '</b> has ' .count($fld_files_missing). ' missing filename(s) <br/>
					-- Not found in folder: <b> ' .$srcpath_original. ' </b><br/>
					-- Missing filenames list: <br/> ';
					foreach($fld_files_missing as $filename_missing => $line_nums)
						$parse_log .= 'LINE '.implode(',', $line_nums).': '.$filename_missing. ' <br/>';
					$parse_log .= '
					</span>
					';
				}
			}
			$conf['filenames_missing'] = $filenames_missing;
			$conf['ff_types_to_paths'] = $ff_types_to_paths;
		}
	}
	
	
	function parsevalues(&$conf, &$parse_log, &$task)
	{
		$colcount = count($conf['columns']);
		$conf['contents_parsed'] = array();
		foreach( $conf['contents'] as $lineno => $fields )
		{
			if (count($fields) > $colcount)
			{
				$msg = "Redundadant columns at record row ".$lineno.", Found # columns: ". count($fields) . " > expected: ". $colcount;
				JLog::add($msg, JLog::NOTICE, 'com_flexicontent.importcsv');
				if ($task == 'testcsv') $parse_log .= $msg;
			}
			
			// Handle each field of the item
			//$conf['contents_parsed'][$lineno] = array();
			
			// Prepare request variable used by the item's Model
			$data = array();
			
			foreach($fields as $col_no => $field_data)
			{
				if ($col_no >= $colcount) break;
				
				$fieldname = $conf['columns'][$col_no];
				if ( isset($conf['core_props'][$fieldname]) ) {
					$field_values = trim($field_data);
				} else {
					// Split multi-value field
					$vals = strlen($field_data) ? preg_split("/[\s]*".$conf['mval_separator']."[\s]*/", $field_data) : array();
					$vals = flexicontent_html::arrayTrim($vals);
					
					// Handle each value of the field
					$field_values = array();
					foreach ($vals as $i => $val)
					{
						// Split multiple property fields
						$props = strlen($val) ? preg_split("/[\s]*".$conf['mprop_separator']."[\s]*/", $val) : array();
						$props = flexicontent_html::arrayTrim($props);
						unset($prop_arr);
						
						// Handle each property of the value
						foreach ($props as $j => $prop) {
							if ( preg_match( '/\[-(.*)-\]=(.*)/', $prop, $matches) ) {
								$prop_arr[$matches[1]] = $matches[2];
							}
						}
						$field_values[] = isset($prop_arr) ? $prop_arr : $val;
					}
				}
				//$conf['contents_parsed'][$lineno][$fieldname] = $field_values;
				
				
				// Assign array of field values to the item data row
				if ( $fieldname=='id' )
				{
					if ( $conf['id_col'] ) $data[$fieldname] = $field_values;
				}
				else if ($fieldname=='title' || $fieldname=='text' || $fieldname=='alias')
				{
					$data[$fieldname] = $field_values;
				}
				else if ( $fieldname=='language' )
				{
					if ( $conf['language'] == '-99' ) $data[$fieldname] = $field_values;
				}
				else if ( $fieldname=='state' )
				{
					if ( $conf['state'] == '-99' ) $data[$fieldname] = $field_values;
				}
				else if ( $fieldname=='access' )
				{
					if ( $conf['access']===0 ) $data[$fieldname] = $field_values;
				}
				else if ( $fieldname=='catid' )
				{
					if ($conf['maincat_col']) $data[$fieldname] = $field_values;
				}
				else if ( $fieldname=='cid' )
				{
					if ($conf['seccats_col']) $data[$fieldname] = preg_split("/[\s]*,[\s]*/", $field_values);
				}
				else if ( $fieldname=='tags_names' || $fieldname=='tags_raw' )
				{
					$data[$fieldname] = $field_values;  // *** TODO more during insertion ... check tags exist and create missing
				}
				else if ( $fieldname=='created' )
				{
					if ($conf['created_col']) $data[$fieldname] = $field_values;
				}
				else if ( $fieldname=='created_by' )
				{
					if ($conf['created_by_col']) $data[$fieldname] = $field_values;
				}
				else if ( $fieldname=='modified' )
				{
					if ($conf['modified_col']) $data[$fieldname] = $field_values;
				}
				else if ( $fieldname=='modified_by' )
				{
					if ($conf['modified_by_col']) $data[$fieldname] = $field_values;
				}
				else if ( $fieldname=='metadesc' )
				{
					if ($conf['metadesc_col']) $data[$fieldname] = $field_values;
				}
				else if ( $fieldname=='metakey' )
				{
					if ($conf['metakey_col']) $data[$fieldname] = $field_values;
				}
				else if ( $fieldname=='publish_up' )
				{
					if ($conf['publish_up_col']) $data[$fieldname] = $field_values;
				}
				else if ( $fieldname=='publish_down' )
				{
					if ($conf['publish_down_col']) $data[$fieldname] = $field_values;
				}
				else
				{
					// Custom Fields
					$data[$fieldname] = $field_values;
				}
			}
			
			$conf['contents_parsed'][$lineno] = $data;
			
		}
	}
	
}
