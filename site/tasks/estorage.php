<?php
use Joomla\String\StringHelper;

/**
 * J4 CLI
 */
use Joomla\CMS\Application\CMSApplication;      // This is for web request, does not work with CLI (command line interface)
use Joomla\CMS\Application\CliApplication;      // This is abstract class, we cannot use it
use Joomla\CMS\Application\ConsoleApplication;  // ... we can use this, it is for CLI and it is non-abstract


/**
 * Check we are running from command line interface (CLI)
 */
if (php_sapi_name() !== 'cli')
{
	die('Direct call not allowed');
}

/**
 * Define JPATH_BASE
 */
if (!defined('JPATH_BASE'))
{
	define('_JEXEC', 1);
	define('DS', DIRECTORY_SEPARATOR);

	if (file_exists('defines.php'))
	{
		require_once 'defines.php';
	}
	elseif (file_exists(realpath(__DIR__) . '/' . 'defines.php'))
	{
		require_once realpath(__DIR__) . '/' . 'defines.php';
	}
	else
	{
		define('JPATH_BASE', realpath(__DIR__.'/../../..'));
	}
}


/**
 * Create the instance of our class
 * and execute the "work" task(s)
 */
$task = new FlexicontentCronTasks();
$task->transferFiles();


class FlexicontentCronTasks
{
	var $option = 'com_flexicontent';
	var $app    = null;

	/**
	 * Constructor
	 *
	 * @since 4.0
	 */
	function __construct()
	{
		$this->_setExecConfig();

		// Saves the start time and memory usage.
		//$start_time = microtime(true);
		//$start_mem  = memory_get_usage();

		require_once JPATH_BASE . '/includes/defines.php';
		require_once JPATH_BASE . '/includes/framework.php';

		$is_admin    = preg_match('/\/administrator\//', isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
		$client_name = $is_admin ? 'administrator' : 'site';

		if (!defined('FLEXI_J40GE'))
		{
			$jversion = new JVersion;
			define('FLEXI_J40GE', version_compare( $jversion->getShortVersion(), '3.99.99', '>' ) );
		}

		/**
		 * Instantiate the application.
		 */
		if (!FLEXI_J40GE)
		{
			$app = JFactory::getApplication($client_name);
			$app->initialise();
		}
		else
		{
			/**
			 * None of the following 3 options works
			 */
			//$app = CMSApplication::getInstance($client_name);
			//$app = new CliApplication();
			//$app = new ConsoleApplication();  // This needs parameters ...
			//$app->initialise();

			// Boot the DI container
			$container = \Joomla\CMS\Factory::getContainer();

			$container->alias('session', 'session.cli')
				->alias('JSession', 'session.cli')
				->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
				->alias(\Joomla\Session\Session::class, 'session.cli')
				->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
	
			// Instantiate the application.
			$app = $container->get(\Joomla\CMS\Application\ConsoleApplication::class);

			// Alternative to use the abstract class CliApplication ...
			//$_SERVER['SCRIPT_NAME'] = basename($_SERVER['SCRIPT_NAME']);
			//$_SERVER['HTTP_HOST'] = '';
			//$app = $container->get(\Joomla\CMS\Application\CliApplication::class);

			// Set the application as global app
			\Joomla\CMS\Factory::$application = $app;
		}

		// Get Flexicontent constants
		require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/defineconstants.php';
	}


	public function transferFiles()
	{
		$log_filename = 'cron_estorage.php';
		jimport('joomla.log.log');
		JLog::addLogger(
			array(
				'text_file' => $log_filename,  // Sets the target log file
				'text_entry_format' => '{DATE} {TIME} {PRIORITY} {MESSAGE}'  // Sets the format of each line
			),
			JLog::ALL,  // Sets messages of all log levels to be sent to the file
			array('com_flexicontent.estorage')  // category of logged messages
		);

		$db  = JFactory::getDbo();


		/**
		 * Get file-based fields involved in moving files
		 */
		$query = 'SELECT DISTINCT estorage_fieldid FROM #__flexicontent_files WHERE estorage_fieldid > 0';
		$field_ids = $db->setQuery($query)->loadColumn();

		/*if (!$field_ids)
		{
			$field_ids[0] = 228;
		}*/
		if (!count($field_ids))
		{
			$msg = 'CRON TASK STARTED: terminating, no files need to be moved';
			JLog::add($msg, JLog::INFO, 'com_flexicontent.estorage');
			return;
		}

		$query = 'SELECT f.* '
			. ' FROM #__flexicontent_fields AS f '
			. ' WHERE f.id IN ('.implode(',',$field_ids).')'
			;
		$fields = $db->setQuery($query)->loadObjectList('id');
		$ftpConnID = array();

		foreach($fields as $field_id => $field)
		{
			// Create field parameters, if not already created, NOTE: for 'custom' fields loadFieldConfig() is optional
			if (empty($field->parameters))
			{
				$field->parameters = new JRegistry($field->attribs);
			}

			$estorage_mode = $field->parameters->get('estorage_mode', '0');
			
			if (!$estorage_mode || $estorage_mode !== 'FTP')
			{
				$ftpConnID[$field_id] = 0;
				continue;
			}

			$efs_ftp_host = $field->parameters->get('efs_ftp_host', 'localhost');
			$efs_ftp_port = (int) $field->parameters->get('efs_ftp_port', '21');
			$efs_ftp_user = $field->parameters->get('efs_ftp_user', 'testuser');
			$efs_ftp_pass = $field->parameters->get('efs_ftp_pass', '1234@@test');
			$efs_ftp_path = $field->parameters->get('efs_ftp_path', '/');

			$ftpConnID[$field_id] = ftp_connect($efs_ftp_host, $efs_ftp_port, $_timeout = 10);

			if (!$ftpConnID[$field_id])
			{
				$msg = 'FAILED TO CONNECT TO FTP server: (host:port) :' . $efs_ftp_host . ':' . $efs_ftp_port;
				JLog::add($msg, JLog::ERROR, 'com_flexicontent.estorage');
				$ftpConnID[$field_id] = 0;
				continue;
			}

			$login_result = ftp_login($ftpConnID[$field_id], $efs_ftp_user, $efs_ftp_pass);

			if (!$login_result)
			{
				$msg = 'FAILED TO LOGIN TO FTP SERVER: (user@host:port) :' . $efs_ftp_user . '@' . $efs_ftp_host . ':' . $efs_ftp_port;
				JLog::add($msg, JLog::ERROR, 'com_flexicontent.estorage');
				$ftpConnID[$field_id] = 0;
				continue;
			}
			
			// Turn passive mode on
			$pasv_result = ftp_pasv($ftpConnID[$field_id], true);

			if (!$pasv_result)
			{
				$msg = 'FAILED TO TURN ON PASSIVE MODE FOR FTP CONNECTION FOR FTP SERVER: (user@host:port) :' . $efs_ftp_user . '@' . $efs_ftp_host . ':' . $efs_ftp_port;
				JLog::add($msg, JLog::ERROR, 'com_flexicontent.estorage');
				$ftpConnID[$field_id] = 0;
				continue;
			}

			if ($efs_ftp_path)
			{
				$cwd_result = ftp_chdir($ftpConnID[$field_id], $efs_ftp_path);

				if (!$cwd_result)
				{
					$msg = 'FAILED TO CHANGE (REMOTE) FTP DIRECTORY TO : ' . $efs_ftp_path . ' FOR FTP SERVER: (user@host:port) :' . $efs_ftp_user . '@' . $efs_ftp_host . ':' . $efs_ftp_port;
					JLog::add($msg, JLog::ERROR, 'com_flexicontent.estorage');
					$ftpConnID[$field_id] = 0;
					continue;
				}
			}
		}


		/**
		 * Get files needed to be moved
		 */
		$query = 'SELECT * FROM #__flexicontent_files WHERE estorage_fieldid <> 0';
		$files = $db->setQuery($query)->loadObjectList();

		/*if (!$files)
		{
			$files[0] = (object) array('filename' => '03-POWX075-EN.pdf', 'estorage_fieldid' => 228, 'source_path' => 'F:/Users/george/Downloads/test');
		}*/
		if (!count($files)) return;

		$msg = 'CRON TASK STARTED: moving ' . count($files) . ' files';
		JLog::add($msg, JLog::INFO, 'com_flexicontent.estorage');

		foreach($files as $file)
		{
			$field_id = abs($file->estorage_fieldid);

			if (empty($fields[$field_id]) )
			{
				continue;
			}

			if (empty($ftpConnID[$field_id]))
			{
				continue;
			}

			if (!empty($file->checked_out))
			{
				continue;
			}
			
			$field        = $fields[$field_id];
			$efs_ftp_path = $field->parameters->get('efs_ftp_path', '/');
			$efs_ftp_path = '/' . trim($efs_ftp_path, '/');
			$efs_www_url  = $field->parameters->get('efs_www_url', 'https://some_external_servername.com/somefolder/');
			$efs_www_url  = rtrim($efs_www_url, '/') . '/';

			$assigned_item = false;

			if ($file->id > 0)
			{
				$query = 'SELECT c.id, c.created_by FROM #__flexicontent_fields_item_relations AS v'
					. ' JOIN #__content AS c ON c.id = v.item_id '
					. ' WHERE v.value = ' . (int) $file->id . ' AND v.field_id = ' . (int) $field_id
					. ' LIMIT 1 ';
				$assigned_item = $db->setQuery($query)->loadObject();
			}

			// Skip file that are not yet assigned
			if (!$assigned_item)
			{
				continue;
			}

			// Prevent editing of file record until file uploads
			if ($file->id > 0) $query = 'UPDATE #__flexicontent_files '
				. ' SET checked_out = 1, estorage_fieldid = ' . (-1 * (int) $field_id)
				. ' WHERE id = ' . (int) $file->id;
			$db->setQuery($query)->execute();


			if ($assigned_item)
			{
				$cwd_result = ftp_chdir($ftpConnID[$field_id], $efs_ftp_path . '/o_' . $assigned_item->created_by);

				if (!$cwd_result)
				{
					$mkd_result = ftp_mkdir($ftpConnID[$field_id], $efs_ftp_path . '/o_' . $assigned_item->created_by);
					if (!$mkd_result)
					{
						$msg = 'FAILED TO CREATE (REMOTE) FTP DIRECTORY TO : ' . $efs_ftp_path . '/o_' . $assigned_item->created_by . ' FOR FTP SERVER: (user@host:port) :' . $efs_ftp_user . '@' . $efs_ftp_host . ':' . $efs_ftp_port;
						JLog::add($msg, JLog::ERROR, 'com_flexicontent.estorage');
					}
					$cwd_result = ftp_chdir($ftpConnID[$field_id], $efs_ftp_path . '/o_' . $assigned_item->created_by);
				}

				if ($cwd_result)
				{
					$cwd_result = ftp_chdir($ftpConnID[$field_id], $efs_ftp_path . '/o_' . $assigned_item->created_by . '/i_' . $assigned_item->id);

					if (!$cwd_result)
					{
						$mkd_result = ftp_mkdir($ftpConnID[$field_id], $efs_ftp_path . '/o_' . $assigned_item->created_by . '/i_' . $assigned_item->id);
						if (!$mkd_result)
						{
							$msg = 'FAILED TO CREATE (REMOTE) FTP DIRECTORY TO : ' . $efs_ftp_path . '/o_' . $assigned_item->created_by . '/i_' . $assigned_item->id . ' FOR FTP SERVER: (user@host:port) :' . $efs_ftp_user . '@' . $efs_ftp_host . ':' . $efs_ftp_port;
							JLog::add($msg, JLog::ERROR, 'com_flexicontent.estorage');
						}
						$cwd_result = ftp_chdir($ftpConnID[$field_id], $efs_ftp_path . '/o_' . $assigned_item->created_by . '/i_' . $assigned_item->id);
					}

					if (!$cwd_result)
					{
						$msg = 'FAILED TO CHANGE (REMOTE) FTP DIRECTORY TO : ' . $efs_ftp_path . '/o_' . $assigned_item->created_by . '/i_' . $assigned_item->id . ' FOR FTP SERVER: (user@host:port) :' . $efs_ftp_user . '@' . $efs_ftp_host . ':' . $efs_ftp_port;
						JLog::add($msg, JLog::ERROR, 'com_flexicontent.estorage');
						continue;
					}
				}
				else
				{
					$msg = 'FAILED TO CHANGE (REMOTE) FTP DIRECTORY TO : ' . $efs_ftp_path . '/o_' . $assigned_item->created_by . ' FOR FTP SERVER: (user@host:port) :' . $efs_ftp_user . '@' . $efs_ftp_host . ':' . $efs_ftp_port;
					JLog::add($msg, JLog::ERROR, 'com_flexicontent.estorage');
					continue;
				}
			}
			else
			{
				$cwd_result = ftp_chdir($ftpConnID[$field_id], $efs_ftp_path);

				if (!$cwd_result)
				{
					$msg = 'FAILED TO CHANGE (REMOTE) FTP DIRECTORY TO : ' . $efs_ftp_path . ' FOR FTP SERVER: (user@host:port) :' . $efs_ftp_user . '@' . $efs_ftp_host . ':' . $efs_ftp_port;
					JLog::add($msg, JLog::ERROR, 'com_flexicontent.estorage');
					continue;
				}
			}

			// Add subfolders OWNER ID and ITEM ID to the new URL
			if ($assigned_item)
			{
				$efs_www_url .= 'o_' . $assigned_item->created_by . '/i_' . $assigned_item->id . '/';
			}

			$file->source_path  = isset($file->source_path)
				? $file->source_path
				:	($file->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH);  // JPATH_ROOT . DS . <media_path | file_path>

			$source_file  = JPath::clean($file->source_path . DS . $file->filename);
			$dest_file    = basename($source_file);

			/*$contents_on_server = ftp_nlist($ftpConnID[$field_id], $efs_ftp_path); //Returns an array of filenames from the specified directory on success or FALSE on error. 

			// Test if file is in the ftp_nlist array
			if (in_array($dest_file, $contents_on_server)) 
			{
				$msg = 'Files exist on remote server: ' . $dest_file;
				JLog::add($msg, JLog::WARNING, 'com_flexicontent.estorage');
				continue;
			}*/

			$ftp_result = ftp_nb_put($ftpConnID[$field_id], $dest_file, $source_file, FTP_BINARY, FTP_AUTORESUME);

			while ($ftp_result === FTP_MOREDATA)
			{
				$ftp_result = ftp_nb_continue($ftpConnID[$field_id]);
			}

			if ($ftp_result === FTP_FAILED)
			{
				$msg = 'FTP upload failed for file : ' . $source_file . ' to ' . $dest_file;
				JLog::add($msg, JLog::ERROR, 'com_flexicontent.estorage');

				if ($file->id > 0) $query = 'UPDATE #__flexicontent_files '
					. ' SET checked_out = 0, estorage_fieldid = ' . (int) $field_id
					. ' WHERE id = ' . (int) $file->id;
				$db->setQuery($query)->execute();
			}
			elseif ($ftp_result === FTP_FINISHED)
			{
				$msg = 'FTP upload succeeded for file : ' . $source_file . ' to ' . $dest_file;
				JLog::add($msg, JLog::INFO, 'com_flexicontent.estorage');

				// Do not modify original name 'filename_original' which is used during download to set appropriate HTTP header
				if ($file->id > 0) $query = 'UPDATE #__flexicontent_files '
					. ' SET url = 1, checked_out = 0, estorage_fieldid = 0'
					. ' , filename = ' . $db->Quote($efs_www_url . $dest_file)
					. ' WHERE id = ' . (int) $file->id;
				$db->setQuery($query)->execute();

				unlink($source_file);
			}
			else
			{
				$msg = 'FTP upload could not be started for uploading file : ' . $source_file . ' to ' . $dest_file;
				JLog::add($msg, JLog::ERROR, 'com_flexicontent.estorage');

				if ($file->id > 0) $query = 'UPDATE #__flexicontent_files '
					. ' SET checked_out = 0, estorage_fieldid = ' . (int) $field_id
					. ' WHERE id = ' . (int) $file->id;
				$db->setQuery($query)->execute();
			}
		}

		foreach ($ftpConnID as $ftp_conn)
		{
			if (empty($ftp_conn))
			{
				continue;
			}
			ftp_close($ftp_conn);
		}

		//echo $this->_get_system_messages_html();

		$diff = round(1000000 * 10 * (microtime(true) - $start_time)) / 10;
		//echo sprintf('<div class="alert alert-info">Time: %.3f s</div>', $diff/1000000);
		//echo sprintf('<div class="alert alert-info">Memory: %.1f MBs</div>', (memory_get_usage() - $start_mem) / (1024 * 1024));
	}


	private function _setExecConfig()
	{
		// Display fatal errors, warnings, notices
		error_reporting(E_ERROR || E_WARNING || E_NOTICE);
		ini_set('display_errors',1);

		// Try to increment some limits
		@ set_time_limit( 3600 );   // try to set execution time 60 minutes
		ignore_user_abort( true ); // continue execution if client disconnects

		// Try to increment memory limits
		$memory_limit	= trim( @ ini_get( 'memory_limit' ) );
		
		if ( $memory_limit )
		{
			switch (strtolower(substr($memory_limit, -1)))
			{
				case 'm': $memory_limit = (int)substr($memory_limit, 0, -1) * 1048576; break;
				case 'k': $memory_limit = (int)substr($memory_limit, 0, -1) * 1024; break;
				case 'g': $memory_limit = (int)substr($memory_limit, 0, -1) * 1073741824; break;
				case 'b':
				switch (strtolower(substr($memory_limit, -2, 1)))
				{
					case 'm': $memory_limit = (int)substr($memory_limit, 0, -2) * 1048576; break;
					case 'k': $memory_limit = (int)substr($memory_limit, 0, -2) * 1024; break;
					case 'g': $memory_limit = (int)substr($memory_limit, 0, -2) * 1073741824; break;
					default : break;
				} break;
				default: break;
			}

			if ( $memory_limit < 16 * 1024 * 1024 ) @ ini_set( 'memory_limit', '16M' );
			if ( $memory_limit < 32 * 1024 * 1024 ) @ ini_set( 'memory_limit', '32M' );
			if ( $memory_limit < 64 * 1024 * 1024 ) @ ini_set( 'memory_limit', '64M' );
			if ( $memory_limit < 128 * 1024 * 1024 ) @ ini_set( 'memory_limit', '128M' );
			if ( $memory_limit < 256 * 1024 * 1024 ) @ ini_set( 'memory_limit', '256M' );
		}
		//echo 'max_execution_time: ' . ini_get('max_execution_time') . '<br>';
		//echo 'memory_limit: ' . ini_get('memory_limit') . '<br>';
	}

	private function _get_system_messages_html($add_containers=false)
	{
		$msgsByType = array();  // Initialise variables.
		$messages = JFactory::getApplication()->getMessageQueue();  // Get the message queue

		// Build the sorted message list
		if (is_array($messages) && !empty($messages)) {
			foreach ($messages as $msg) {
				if (isset($msg['type']) && isset($msg['message'])) $msgsByType[$msg['type']][] = $msg['message'];
			}
		}

		$alert_class = array('error' => 'alert-error', 'warning' => 'alert-warning', 'notice' => 'alert-info', 'message' => 'alert-success');
		ob_start();
	?>
<?php if ($add_containers) : ?>
<div class="row-fluid">
	<div class="span12">
		<div id="system-message-container">
<?php endif; ?>
			<div id="fc_ajax_system_messages">
			<?php if (is_array($msgsByType) && $msgsByType) : ?>
				<?php foreach ($msgsByType as $type => $msgs) : ?>
					<div class="alert <?php echo $alert_class[$type]; ?>">
						<button type="button" class="close" data-dismiss="alert">&times;</button>
						<h4 class="alert-heading"><?php echo JText::_($type); ?></h4>
						<?php if ($msgs) : ?>
							<?php foreach ($msgs as $msg) : ?>
								<div class="alert-<?php echo $type; ?>"><?php echo $msg; ?></div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
			</div>
<?php if ($add_containers) : ?>
		</div>
	</div>
</div>
<?php endif; ?>

		<?php
		$msgs_html = ob_get_contents();
		ob_end_clean();
		return $msgs_html;
	}

}


