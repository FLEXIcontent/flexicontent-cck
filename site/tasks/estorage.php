<?php
use Joomla\String\StringHelper;
$task = new FlexicontentCronTasks();

class FlexicontentCronTasks
{
	var $option = 'com_flexicontent';

	/**
	 * Constructor
	 *
	 * @since 3.1.2
	 */
	function __construct()
	{
		// Saves the start time and memory usage.
		$start_time = microtime(true);
		$start_mem  = memory_get_usage();

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

		require_once JPATH_BASE . '/includes/defines.php';
		require_once JPATH_BASE . '/includes/framework.php';

		$is_admin = preg_match('/\/administrator\//', @$_SERVER['HTTP_REFERER']);

		// Instantiate the application.
		$app = JFactory::getApplication($is_admin ? 'administrator' : 'site');
		$app->initialise();

		if (php_sapi_name() !== 'cli')
		{
			die('Direct call not allowed');
		}

		$this->_setExecConfig();

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
		$app = JFactory::getApplication();


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
				$ftpConnID[$field_id] = 0;;
				continue;
			}

			$ftpConnID[$field_id] = ftp_connect($efs_ftp_host, $efs_ftp_port = 21, $_timeout = 10);

			$efs_ftp_host = $field->parameters->get('efs_ftp_host', 'localhost');
			$efs_ftp_port = (int) $field->parameters->get('efs_ftp_port', '21');
			$efs_ftp_user = $field->parameters->get('efs_ftp_user', 'testuser');
			$efs_ftp_pass = $field->parameters->get('efs_ftp_pass', '1234@@test');

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

			// Prevent editing of file record until file uploads
			if ($file->id > 0) $query = 'UPDATE #__flexicontent_files '
				. ' SET checked_out = 1, estorage_fieldid = ' . (-1 * (int) $field_id)
				. ' WHERE id = ' . (int) $file->id;
			$db->setQuery($query)->execute();

			$field        = $fields[$field_id];
			$efs_ftp_path = $field->parameters->get('efs_ftp_path', '');
			$efs_www_url  = $field->parameters->get('efs_www_url', 'https://some_external_servername.com/somefolder/');

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

			$ftp_result = ftp_nb_put($ftpConnID[$field_id], $efs_ftp_path . $dest_file, $source_file, FTP_BINARY, FTP_AUTORESUME);

			while ($ftp_result === FTP_MOREDATA)
			{
				$ftp_result = ftp_nb_continue($ftpConnID[$field_id]);
			}

			if ($ftp_result === FTP_FAILED)
			{
				$msg = 'FTP upload failed: ' . $dest_file;
				JLog::add($msg, JLog::ERROR, 'com_flexicontent.estorage');

				if ($file->id > 0) $query = 'UPDATE #__flexicontent_files '
					. ' SET checked_out = 0, estorage_fieldid = ' . (int) $field_id
					. ' WHERE id = ' . (int) $file->id;
				$db->setQuery($query)->execute();
			}
			elseif ($ftp_result === FTP_FINISHED)
			{
				$msg = 'FTP upload succeeded: ' . $dest_file;
				JLog::add($msg, JLog::INFO, 'com_flexicontent.estorage');

				if ($file->id > 0) $query = 'UPDATE #__flexicontent_files '
					. ' SET url = 1, checked_out = 0, estorage_fieldid = 0, filename = ' . $db->Quote($efs_www_url . $dest_file)
					. ' WHERE id = ' . (int) $file->id;
				$db->setQuery($query)->execute();

				unlink($file->source_path);
			}
			else
			{
				$msg = 'FTP upload could not be started';
				JLog::add($msg, JLog::ERROR, 'com_flexicontent.estorage');

				if ($file->id > 0) $query = 'UPDATE #__flexicontent_files '
					. ' SET checked_out = 0, estorage_fieldid = ' . (int) $field_id
					. ' WHERE id = ' . (int) $file->id;
				$db->setQuery($query)->execute();
			}
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


