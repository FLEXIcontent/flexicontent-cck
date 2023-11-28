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
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'item.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'items.php';

/**
 * FLEXIcontent Items Controller (RAW)
 *
 * NOTE: -Only- if this controller is needed by frontend URLs, then create a derived controller in frontend 'controllers' folder
 *
 * @since 3.3
 */
class FlexicontentControllerItems extends FlexicontentControllerBaseAdmin
{
	var $records_dbtbl  = 'content';
	var $records_jtable = 'flexicontent_items';

	var $record_name = 'item';
	var $record_name_pl = 'items';

	var $_NAME = 'ITEM';
	var $record_alias = 'alias';

	var $runMode = 'standalone';

	var $exitHttpHead = null;
	var $exitMessages = array();
	var $exitLogTexts = array();
	var $exitSuccess  = true;

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
	}


	/**
	 * Logic to print a table of versions for a record
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function getversionlist()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		@ob_end_clean();
		$id     = $this->input->getInt('id', 0);
		$active = $this->input->getInt('active', 0);

		if (!$id)
		{
			return;
		}

		$revert 	= JHtml::image('administrator/components/com_flexicontent/assets/images/arrow_rotate_anticlockwise.png', JText::_('FLEXI_REVERT'));
		$view 		= JHtml::image('administrator/components/com_flexicontent/assets/images/magnifier.png', JText::_('FLEXI_VIEW'));
		$comment 	= JHtml::image('administrator/components/com_flexicontent/assets/images/comments.png', JText::_('FLEXI_COMMENT'));

		$model = $this->getModel($this->record_name);
		$model->setId($id);
		$item = $model->getItem($id);

		$cparams = JComponentHelper::getParams('com_flexicontent');
		$versionsperpage = $cparams->get('versionsperpage', 10);
		$currentversion = $item->version;
		$page = $this->input->getInt('page', 0);
		$versioncount = $model->getVersionCount();
		$numpage = ceil($versioncount / $versionsperpage);

		if ($page > $numpage)
		{
			$page = $numpage;
		}
		elseif ($page < 1)
		{
			$page = 1;
		}

		$limitstart = ($page - 1) * $versionsperpage;
		$versions = $model->getVersionList();
		$versions	= $model->getVersionList($limitstart, $versionsperpage);

		$jt_date_format = FLEXI_J16GE ? 'FLEXI_DATE_FORMAT_FLEXI_VERSIONS_J16GE' : 'FLEXI_DATE_FORMAT_FLEXI_VERSIONS';
		$df_date_format = FLEXI_J16GE ? "d/M H:i" : "%d/%m %H:%M";
		$date_format = JText::_($jt_date_format);
		$date_format = ( $date_format == $jt_date_format ) ? $df_date_format : $date_format;
		$ctrl_task = 'task=items.edit';
		$app     = JFactory::getApplication();
		$isSite  = $app->isClient('site');

		foreach ($versions as $v)
		{
			$class = ($v->nr == $active) ? ' id="active-version"' : '';
			echo '
			<tr' . $class . '>
				<td class="versions">#' . $v->nr . '</td>
				<td class="versions">' . JHtml::_('date', (($v->nr == 1) ? $item->created : $v->date), $date_format) . '</td>
				<td class="versions">' . (($v->nr == 1) ? $item->creator : $v->modifier) . '</td>
				<td class="versions" align="center">
					<a href="javascript:;" class="hasTooltip" title="' . JHtml::tooltipText(JText::_('FLEXI_COMMENT'), ($v->comment ? $v->comment : 'No comment written'), 0, 1) . '">' . $comment . '</a>
				' . (
				((int) $v->nr === (int) $currentversion) ? // Is current version ?
					'<a onclick="javascript:return clickRestore(\'index.php?option=com_flexicontent&' . $ctrl_task . '&' . ($isSite ? 'id=' : 'cid=') . $item->id . '&version=' . $v->nr . '\');" href="javascript:;">' . JText::_('FLEXI_CURRENT') . '</a>' :
					'<a class="modal-versions" href="index.php?option=com_flexicontent&view=itemcompare&cid[]=' . $item->id . '&version=' . $v->nr . '&tmpl=component" title="' . JText::_('FLEXI_COMPARE_WITH_CURRENT_VERSION') . '" rel="{handler: \'iframe\', size: {x:window.getSize().scrollSize.x-100, y: window.getSize().size.y-100}}">' . $view . '</a>
					<a onclick="javascript:return clickRestore(\'index.php?option=com_flexicontent&' . $ctrl_task . '&' . ($isSite ? 'id=' : 'cid=') . $item->id . '&version=' . $v->nr . '&' . JSession::getFormToken() . '=1\');" href="javascript:;" title="' . JText::sprintf('FLEXI_REVERT_TO_THIS_VERSION', $v->nr) . '">' . $revert . '</a>
				') . '
				</td>
			</tr>';
		}

		jexit();
	}


	/**
	 * Method to reset hits
	 *
	 * @since 1.0
	 */
	function resethits()
	{
		// Check session token, item exists, is editable
		$itemmodel = $this->_getEditorModel();

		if (!is_object($itemmodel))
		{
			jexit($itemmodel);
		}

		$itemmodel->resetHits();

		$this->_cleanCache();

		jexit('0');
	}


	/**
	 * Method to reset votes
	 *
	 * @since 1.0
	 */
	function resetvotes()
	{
		// Check session token, item exists, is editable
		$itemmodel = $this->_getEditorModel();

		if (!is_object($itemmodel))
		{
			jexit($itemmodel);
		}

		$itemmodel->resetVotes();

		$this->_cleanCache();

		jexit(JText::_('FLEXI_NOT_RATED_YET'));
	}


	/**
	 * Method to fetch the votes
	 *
	 * @since 1.5
	 */
	function getvotes()
	{
		// Check session token, item exists, is editable
		$itemmodel = $this->_getEditorModel();

		if (!is_object($itemmodel))
		{
			jexit($itemmodel);
		}

		$votes = $itemmodel->getRatingDisplay();

		jexit($votes ?: '0');
	}


	/**
	 * Method to get hits
	 *
	 * @since 1.5
	 */
	function gethits()
	{
		// Check session token, item exists, is editable
		$itemmodel = $this->_getEditorModel();

		if (!is_object($itemmodel))
		{
			jexit($itemmodel);
		}

		$hits = $itemmodel->gethits();

		jexit($hits ?: '0');
	}


	/**
	 * Method to fetch the tags for selecting in item form
	 *
	 * @since 1.5
	 */
	function viewtags()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$app    = JFactory::getApplication();
		$perms  = FlexicontentHelperPerm::getPerm();

		@ob_end_clean();

		//header('Content-type: application/json; charset=utf-8');
		header('Content-type: application/json');
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Cache-Control: no-cache");
		header("Pragma: no-cache");

		$array = array();

		if (!$perms->CanUseTags)
		{
			$array[] = (object) array(
				'id' => '0',
				'name' => JText::_('FLEXI_FIELD_NO_ACCESS')
			);
		}
		else
		{
			$q = $this->input->getString('q', '');
			$q = $q !== parse_url(@$_SERVER["REQUEST_URI"], PHP_URL_PATH) ? $q : '';

			$model = $this->getModel($this->record_name);
			$tagobjs = $model->gettags($q);

			if ($tagobjs)
			{
				foreach ($tagobjs as $tag)
				{
					$array[] = (object) array(
						'id' => $tag->id,
						'name' => $tag->name
					);
				}
			}

			if (empty($array))
			{
				$array[] = (object) array(
					'id' => '0',
					'name' => JText::_($perms->CanCreateTags ? 'FLEXI_NEW_TAG_ENTER_TO_CREATE' : 'FLEXI_NO_TAGS_FOUND')
				);
			}
		}

		jexit(json_encode($array/*, JSON_UNESCAPED_UNICODE*/));
	}


	/**
	 * Method to select new state for many items
	 *
	 * @since 1.5
	 */
	function selectstate()
	{
		// Use general permissions since we do not have examine any specific item
		$perms = FlexicontentHelperPerm::getPerm();
		$auth_publish = $perms->CanPublish || $perms->CanPublishOwn || $perms->CanPublish == null || $perms->CanPublishOwn == null;
		$auth_delete  = $perms->CanDelete  || $perms->CanDeleteOwn  || $perms->CanDelete == null  || $perms->CanDeleteOwn == null;
		$auth_archive = $perms->CanArchives;

		if ($auth_publish || $auth_archive || $auth_delete)
		{
			// Header('Content-type: application/json');
			@ob_end_clean();
			header('Content-type: text/html; charset=utf-8');
			header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
			header("Cache-Control: no-cache");
			header("Pragma: no-cache");

			$rtl_sfx = !JFactory::getLanguage()->isRtl() ? '' : '_rtl';
			$fc_css = JUri::base(true) . '/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x' . $rtl_sfx . '.css' : 'j3x' . $rtl_sfx . '.css');
			echo '
			<link rel="stylesheet" href="' . JUri::base(true) . '/components/com_flexicontent/assets/css/flexicontentbackend.css?' . FLEXI_VHASH . '" />
			<link rel="stylesheet" href="' . $fc_css . '?' . FLEXI_VHASH . '" />
			<link rel="stylesheet" href="' . JUri::root(true) . '/media/jui/css/bootstrap.min.css" />
			';
			?>
	<div id="flexicontent" class="flexicontent">

			<?php
			$btn_class = FLEXI_J30GE ? ' btn btn-small' : ' fc_button fcsimple fcsmall';

			if ($auth_publish)
			{
				$state['P']  = array( 'name' => 'FLEXI_PUBLISHED', 'desc' => 'FLEXI_PUBLISHED_DESC', 'icon' => 'tick.png', 'btn_class' => 'btn-success' );
				$state['IP'] = array( 'name' => 'FLEXI_IN_PROGRESS', 'desc' => 'FLEXI_NOT_FINISHED_YET', 'icon' => 'publish_g.png', 'btn_class' => 'btn-success', 'clear' => true );
				$state['U']  = array( 'name' => 'FLEXI_UNPUBLISHED', 'desc' => 'FLEXI_UNPUBLISHED_DESC', 'icon' => 'publish_x.png', 'btn_class' => 'btn-warning' );
				$state['PE'] = array( 'name' => 'FLEXI_PENDING', 'desc' => 'FLEXI_NEED_TO_BE_APPROVED', 'icon' => 'publish_r.png', 'btn_class' => 'btn-warning' );
				$state['OQ'] = array( 'name' => 'FLEXI_TO_WRITE', 'desc' => 'FLEXI_TO_WRITE_DESC', 'icon' => 'publish_y.png', 'btn_class' => 'btn-warning', 'clear' => true );
			}

			if ($auth_archive)
			{
				$state['A'] = array( 'name' => 'FLEXI_ARCHIVED', 'desc' => 'FLEXI_ARCHIVED_DESC', 'icon' => 'archive.png', 'btn_class' => 'btn-info' );
			}

			if ($auth_delete)
			{
				$state['T'] = array( 'name' => 'FLEXI_TRASHED', 'desc' => 'FLEXI_TRASHED_TO_BE_DELETED', 'icon' => 'trash.png', 'btn_class' => 'btn-danger' );
			}

			// echo "<b>". JText::_( 'FLEXI_SELECT_STATE' ).":</b>";
			echo "<br /><br />";
		?>

		<?php
		foreach ($state as $shortname => $statedata)
		{
			$css = "width:216px; margin:0px 12px 12px 0px;";
			$link = JUri::base(true) . "/index.php?option=com_flexicontent&task=items.changestate&newstate=" . $shortname . "&" . JSession::getFormToken() . "=1";
			$icon = "../components/com_flexicontent/assets/images/" . $statedata['icon'];
		?>
			<span class="fc-filter nowrap_box">
			<?php
				/*
				<!-- <img src="<?php echo $icon; ?>" style="margin:4px 0 0 0; border-width:0px; vertical-align:top;" alt="<?php echo JText::_($statedata['desc']); ?>" /> &nbsp; -->
				*/
				?>
				<span style="<?php echo $css; ?>" class="<?php echo $btn_class . ' ' . $statedata['btn_class']; ?>"
					onclick="window.parent.fc_parent_form_submit('fc_modal_popup_container', 'adminForm', {'newstate':'<?php echo $shortname; ?>', 'task':'items.changestate'}, {'task':'items.changestate', 'is_list':true});"
				>
					<?php echo JText::_($statedata['name']); ?>
				</span>
			</span>
		<?php
			if (isset($statedata['clear']))
			{
				echo '<div class="fcclear"></div>';
			}
		}
		?>
	</div>
		<?php
			exit();
		}
	}


	/**
	 * Method to fetch total count the unassociated items
	 *
	 * @since 1.5
	 */
	function getOrphansItems()
	{
		$model  = $this->getModel($this->record_name_pl);
		$status = $model->getUnboundedItems($limit = 1000000, $count_only = true, $checkNoExtData = true, $checkInvalidCat = false);
		echo $status;
		exit;
	}


	/**
	 * Method to fetch total count the unassociated items
	 *
	 * @since 1.5
	 */
	function getBadCatItems()
	{
		$model  = $this->getModel($this->record_name_pl);
		$status = $model->getUnboundedItems($limit = 1000000, $count_only = true, $checkNoExtData = false, $checkInvalidCat = true);
		echo $status;
		exit;
	}


	/**
	 * Bind fields, category relations and items_ext data to Joomla! com_content imported articles
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function bindextdata()
	{
		// Need to recheck post-installation / integrity tasks after,
		// this should NOT effect RAW HTTP requests, used by AJAX ITEM binding
		// JFactory::getSession()->set('flexicontent.recheck_aftersave', true);

		$bind_limit = $this->input->getInt('bind_limit', 25000);

		// Make sure bind limit is sane
		if ($bind_limit < 1 || $bind_limit > 25000)
		{
			$bind_limit = 25000;
		}

		$model = $this->getModel($this->record_name_pl);
		$rows  = $model->getUnboundedItems($bind_limit, $count_only = false, $checkNoExtData = true, $checkInvalidCat = false, $noCache = true);
		$model->bindExtData($rows);
		jexit();
	}


	/**
	 * Fix Items having bad main category
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function fixmaincat()
	{
		$default_cat = $this->input->getInt('default_cat', 0);
		$model = $this->getModel($this->record_name_pl);
		$model->fixMainCat($default_cat);
		jexit();
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
		@ob_end_clean();
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Cache-Control: no-cache");
		header("Pragma: no-cache");

		// Check for request forgeries
		JSession::checkToken('request') or jexit('fail | ' . JText::_('JINVALID_TOKEN'));

		if (!FlexicontentHelperPerm::getPerm()->CanConfig)
		{
			jexit('fail | ' . JText::_('FLEXI_ALERTNOTAUTH_TASK'));
		}

		// Test counting with limited memory
		// ini_set("memory_limit", "20M");

		$start_microtime = microtime(true);

		$has_zlib    = function_exists("zlib_encode"); // Version_compare(PHP_VERSION, '5.4.0', '>=');
		$indexer     = $this->input->getCmd('indexer', 'tag_assignments');
		$rebuildmode = $this->input->getCmd('rebuildmode', '');

		$session = JFactory::getSession();
		$db      = JFactory::getDbo();
		$app     = JFactory::getApplication();

		// Get records model to call needed methods
		$records_model = $this->getModel($this->record_name_pl);

		/**
		 * Check indexer type
		 */

		if ($indexer === 'tag_assignments')
		{
			$log_filename = 'tag_assignments_' . \JFactory::getUser()->id . '.php';
			$log_category = 'com_flexicontent.items.tag_assignments_indexer';

			// Get ids of records to process
			$records_total = 0;
			$record_ids = $records_model->getItemsWithTags(
				$records_total, 0, self::$record_limit
			);
		}

		elseif ($indexer === 'resave')
		{
			$log_filename = 'resave_' . \JFactory::getUser()->id . '.php';
			$log_category = 'com_flexicontent.items.resave_indexer';

			// Get ids of records to process
			$records_total = 0;
			$record_ids = $records_model->getAllItems(
				$records_total, 0, self::$record_limit
			);
		}
		else
		{
			jexit('fail | indexer: ' . $indexer . ' not supported');
		}

		// Get full logfile path
		$log_filename_full = JPATH::clean(\JFactory::getConfig()->get('log_path') . DS . $log_filename);

		// Clear previous log file
		if (file_exists($log_filename_full))
		{
			@ unlink($log_filename_full);
		}

		// Set record ids into session to avoid recalculation ...
		$_sz_encoded = base64_encode($has_zlib ? zlib_encode(serialize($record_ids), -15) : serialize($record_ids));
		$session->set($indexer . '_records_to_index', $_sz_encoded, 'flexicontent');

		echo 'success';
		echo '|' . $records_total;
		echo '|' . count(array());

		$elapsed_microseconds = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		$_total_runtime = $elapsed_microseconds;
		$_total_queries = 0;
		echo sprintf('|0| Server execution time: %.2f secs ', $_total_runtime / 1000000) . ' | Total DB updates: ' . $_total_queries;

		$session->set($indexer . '_total_runtime', $elapsed_microseconds, 'flexicontent');
		$session->set($indexer . '_total_queries', 0, 'flexicontent');
		$session->set($indexer . '_records_total', $records_total, 'flexicontent');
		$session->set($indexer . '_records_start', 0, 'flexicontent');
		$session->set($indexer . '_log_filename', $log_filename, 'flexicontent');
		$session->set($indexer . '_log_category', $log_filename, 'flexicontent');
		
		jexit();
	}


	public function index()
	{
		@ob_end_clean();
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Cache-Control: no-cache");
		header("Pragma: no-cache");

		// Check for request forgeries
		// JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));
		// Not need because this task need the user session data that are set by countrows that checked for forgeries

		if (!FlexicontentHelperPerm::getPerm()->CanConfig)
		{
			jexit('fail | ' . JText::_('FLEXI_ALERTNOTAUTH_TASK'));
		}

		// Test indexing with limited memory
		// ini_set("memory_limit", "20M");

		$start_microtime = microtime(true);

		$session = JFactory::getSession();
		$db      = JFactory::getDbo();
		$app     = JFactory::getApplication();

		$has_zlib      = function_exists("zlib_encode"); // Version_compare(PHP_VERSION, '5.4.0', '>=');

		$indexer     = $this->input->getCmd('indexer', 'tag_assignments');
		$rebuildmode = $this->input->getCmd('rebuildmode', '');

		$records_per_call = $this->input->getInt('records_per_call', 20);  // Number of item to index per HTTP request
		$records_cnt      = $this->input->getInt('records_cnt', 0);        // Counter of items indexed so far, this is given via HTTP request

		$log_filename = $session->get($indexer . '_log_filename', null, 'flexicontent');
		$log_category = $session->get($indexer . '_log_category', null, 'flexicontent');

		jimport('joomla.log.log');
		JLog::addLogger(
			array(
				'text_file' => $log_filename,  // Sets the target log file
				'text_entry_format' => '{DATETIME} {PRIORITY} {MESSAGE}'  // Sets the format of each line
			),
			JLog::ALL,  // Sets messages of all log levels to be sent to the file
			array($log_category)  // category of logged messages
		);

		// Get record ids to process, but use session to avoid recalculation
		$record_ids = $session->get($indexer . '_records_to_index', array(), 'flexicontent');
		$record_ids = unserialize($has_zlib ? zlib_decode(base64_decode($record_ids)) : base64_decode($record_ids));

		// Get total and current limit-start
		$records_total = $session->get($indexer . '_records_total', 0, 'flexicontent');
		$records_start = $session->get($indexer . '_records_start', 0, 'flexicontent');


		// Get query size limit
		$query = "SHOW VARIABLES LIKE 'max_allowed_packet'";
		$db->setQuery($query);
		$_dbvariable = $db->loadObject();
		$max_allowed_packet = flexicontent_upload::parseByteLimit(@ $_dbvariable->Value);
		$max_allowed_packet = $max_allowed_packet ? $max_allowed_packet : 256 * 1024;
		$query_lim          = (int) (3 * $max_allowed_packet / 4);

		// jexit('fail | ' . $query_lim);

		// Get script max
		$max_execution_time = ini_get("max_execution_time");

		// jexit('fail | ' . $max_execution_time);

		// Get records models
		$records_model = $this->getModel($this->record_name_pl);
		$record_model  = $this->getModel($this->record_name);

		$query_count         = 0;
		$max_items_per_query = 100;
		$max_items_per_query = $max_items_per_query > $records_per_call ? $records_per_call : $max_items_per_query;

		// Start item counter at given index position
		$cnt = $records_cnt;

		// Until all records of current sub-set finish or until maximum records per AJAX call are reached
		while ($cnt < $records_start + count($record_ids)  &&  $cnt < $records_cnt + $records_per_call)
		{
			// Get maximum items per SQL query
			$query_itemids = array_slice($record_ids, ($cnt - $records_start), $max_items_per_query);

			if (empty($query_itemids))
			{
				jexit('fail | current step has no items');
			}

			// Increment item counter, but detect passing past current sub-set limit
			$cnt += $max_items_per_query;

			if ($cnt > $records_start + self::$record_limit)
			{
				$cnt = $records_start + self::$record_limit;
			}

			$map_index = array();
			$errors = array();

			/**
			 * Loop through records
			 * Syncing Joomla tags assignments
			 */
			if ($indexer === 'tag_assignments')
			{
				foreach ($query_itemids as $itemid)
				{
					$query = 'SELECT DISTINCT tid FROM #__flexicontent_tags_item_relations WHERE itemid = ' . (int) $itemid;
					$fctag_ids = $db->setQuery($query)->loadColumn();

					$item = new stdClass();
					$item->id = $itemid;
					$item->tags = array_reverse($fctag_ids);

					// Set item id into the model
					$record_model->setId($item->id);

					// Merge Joomla tags assignment into FC tags assignments
					$record_model->mergeJTagsAssignments($item, $_jtags = null, $_replaceTags = false);

					// Merge FC tags assignment into Joomla tags assignments
					$record_model->saveJTagsAssignments($item->tags, $item->id);

					$query_count += 2;
				}
			}
			elseif ($indexer === 'resave')
			{
				foreach ($query_itemids as $itemid)
				{
					$item = $record_model->getTable();

					// Load table record
					$item->load($itemid);

					// Clear alias
					$item->alias = '';

					if (!$item->check())
					{
						$errors[] = $item->getError();
						continue;
					}

					if (!$item->store())
					{
						$errors[] = $item->getError();
						continue;
					}
				}
			}

			// Increment error count in session, and log errors into the log file
			if (count($errors))
			{
				$error_count = $session->get('items.indexer.error_count', 0, 'flexicontent');
				$session->set('items.indexer.error_count', $error_count + count($errors), 'flexicontent');

				foreach ($errors as $error_message)
				{
					JLog::add($error_message, JLog::WARNING, $log_category);
				}
			}


			// Create query that will update/insert data into the DB
			$queries = array();

			// ... nothing needed

			foreach ($queries as $query)
			{
				try
				{
					$db->setQuery($query)->execute();
				}
				catch (RuntimeException $e)
				{
					jexit('fail | ' . $e->getMessage());
				}
			}

			$query_count += count($queries);

			$elapsed_microseconds = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			$elapsed_seconds = $elapsed_microseconds / 1000000.0;

			if ($elapsed_seconds > $max_execution_time / 3 || $elapsed_seconds > 6)
			{
				break;
			}
		}

		// Check if items have finished, otherwise continue with -next- group of item ids
		if ($cnt >= $records_total)
		{
			// Nothing to do in here
		}

		// Get next sub-set of items
		elseif ($cnt >= $records_start + self::$record_limit)
		{
			$records_start = $records_start + self::$record_limit;

			// Get next set of records
			$record_ids = $records_model->getItemsWithTags(
				$total, $records_start, self::$record_limit
			);

			// Set item ids into session to avoid recalculation ...
			$_sz_encoded = base64_encode($has_zlib ? zlib_encode(serialize($record_ids), -15) : serialize($record_ids));
			$session->set($indexer . '_records_to_index', $_sz_encoded, 'flexicontent');

			$session->set($indexer . '_records_start', $records_start, 'flexicontent');
		}

		// Terminate if not processing any records
		if (!$records_total)
		{
			jexit(
				'fail | No tag assignments need to be synced'
			);
		}

		$elapsed_microseconds = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

		if ($session->has($indexer . '_total_runtime', 'flexicontent'))
		{
			$_total_runtime = $session->get($indexer . '_total_runtime', 0, 'flexicontent');
		}
		else
		{
			$_total_runtime = 0;
		}

		$_total_runtime += $elapsed_microseconds;
		$session->set($indexer . '_total_runtime', $_total_runtime, 'flexicontent');

		if ($session->has($indexer . '_total_queries', 'flexicontent'))
		{
			$_total_queries = $session->get($indexer . '_total_queries', 0, 'flexicontent');
		}
		else
		{
			$_total_queries = 0;
		}

		$_total_queries += $query_count;
		$session->set($indexer . '_total_queries', $_total_queries, 'flexicontent');

		jexit(sprintf('success | ' . $cnt . ' | Server execution time: %.2f secs ', $_total_runtime / 1000000) . ' | Total DB updates: ' . $_total_queries);
	}


	/**
	 * Method to check session token, item exists, is editable
	 *
	 * return string | object   return error string or item model of editable item
	 *
	 * @since 3.2.1.13
	 */
	private function _getEditorModel()
	{
		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$id = $jinput->getInt('id', 0);

		if (!$id)
		{
			return JText::_('Item not found');
		}

		$model = $this->getModel($this->record_name);
		$model->setId($id);
		$item = $model->getItem();

		if (!$item)
		{
			return JText::_('Item not found');
		}

		// Task usage reversed for editors only
		if (!$model->canEdit())
		{
			return JText::_('FLEXI_NO_ACCESS_EDIT');
		}

		return $model;
	}


	/**
	 * Method for clearing cache of data depending on records type
	 *
	 * @return void
	 *
	 * @since 3.2.0
	 */
	protected function _cleanCache()
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		parent::_cleanCache();

		$cache_site = FLEXIUtilities::getCache($group = '', $client = 0);
		$cache_site->clean('com_flexicontent_items');
		$cache_site->clean('com_flexicontent_filters');

		$cache_admin = FLEXIUtilities::getCache($group = '', $client = 1);
		$cache_admin->clean('com_flexicontent_items');
		$cache_admin->clean('com_flexicontent_filters');

		// Also clean this as it contains Joomla frontend view cache of the component)
		$cache_site->clean('com_flexicontent');
	}
}
