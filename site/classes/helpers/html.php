<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Router\Router;

class flexicontent_html
{
	static $use_bootstrap = true;
	static $icon_classes = null;
	static $option = 'com_flexicontent';
	static $inline_links = array();

	static function getInlineLinkOnce($link, $options)
	{
		// Check if already added
		if ( isset(self::$inline_links[$link]) )
		{
			foreach(self::$inline_links[$link]['options'] as $i => $v)
			{
				if ( isset($options[$i]) && $options[$i] == $v )
				{
					return '';
				}
			}
			return '';
		}

		// Check if link already added to head object
		if( isset(JFactory::getDocument()->_styleSheets[$link]) )
		{
			$headlink = JFactory::getDocument()->_styleSheets[$link];
			if (isset($headlink['options']))
			{
				foreach($headlink['options'] as $i => $v)
				{
					if ( isset($options[$i]) && $options[$i] == $v )
					{
						return '';
					}
				}
			}
		}

		// return an inline link
		self::$inline_links[$link]['options'] = $options;
		$ops = array();
		foreach($options as $i => $v)
		{
			$ops[] =  $i . '=' . $v;
		}
		if ($ops) $link .= '?' . implode('&', $ops);
		return '<link rel="stylesheet" href="'.$link.'">';
	}


	static function load_class_config()
	{
		$cparams = JComponentHelper::getParams('com_flexicontent');

		$icon_classes = $cparams->get('font_icon_classes');
		$icon_classes = $icon_classes
			? preg_split("/[\s]*,[\s]*/", $icon_classes)
			: array();

		self::$icon_classes = array();

		foreach ($icon_classes as $d)
		{
			$data = preg_split("/[\s]*:[\s]*/", $d);

			if (count($data) !== 2)
			{
				echo "Misconfigured parameter 'Icon classes': " . $d;
				continue;
			}

			self::$icon_classes[$data[0]] = $data[1];
		}
	}

	static function get_system_messages_html($add_containers=false)
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


	static function & checkPHPLimits($required=null, $recommended=null)
	{
		if (!$required)    $required=array('max_input_vars'=>1000, 'suhosin.post.max_vars'=>1000, 'suhosin.request.max_vars'=>1000);
		if (!$recommended) $recommended=array('max_input_vars'=>2000, 'suhosin.post.max_vars'=>2000, 'suhosin.request.max_vars'=>2000);

		$suhosin_loaded = extension_loaded('suhosin');
		$result = array();
		foreach($required as $varname => $req_value)
		{
			if ( substr( $varname, 0, strlen('suhosin.') ) === 'suhosin.' && !$suhosin_loaded ) continue;
			$sys_value = flexicontent_upload::parseByteLimit(ini_get($varname));
			$required_ok    = $sys_value >=  $required[$varname];
			$recommended_ok = $sys_value >=  $recommended[$varname];
			$conf_limit_class  = $recommended_ok ? 'bg-success badge-success' : ($required_ok ? 'bg-warning badge-warning' : 'bg-info badge-important');

			$result[ $recommended_ok ? 'message' : ($required_ok ? 'notice' : 'warning') ][] = '
			<span class="fc-php-limits-box">
				<span class="badge bg-secondary">'.$varname.'</span> =
				<span class="badge '.$conf_limit_class.'">'.$sys_value.'</span>
				&nbsp;&nbsp;|&nbsp;
				<span class="fc-php-limits-box">
					<span class="label">'.JText::_('FLEXI_REQUIRED').'</span>
					<strong class="badge bg-secondary">'.$required[$varname].'</strong>
					<span class="label">'.JText::_('FLEXI_RECOMMENDED').'</span>
					<strong class="badge bg-success">'.$recommended[$varname].'</strong>
				</span>
			</span>';
		}
		return $result;
	}


	/* Returns true if any LESS file has changed in the LESS include folders */
	static function dirty_less_incPath_exists($inc_paths, $check_global = true)
	{
		static $_dirty_arr = array();
		static $less_folders = null;
		$app = JFactory::getApplication();

		static $print_logging_info = null;
		$print_logging_info = $print_logging_info !== null  ?  $print_logging_info  :  JComponentHelper::getParams('com_flexicontent')->get('print_logging_info');
		$debug = JDEBUG || $print_logging_info;

		if (!is_array($inc_paths)) $inc_paths = array($inc_paths);

		// Get global include folders
		if ($check_global) {
			if ($less_folders===null) {
				$JTEMPLATE_SITE = flexicontent_html::getSiteTemplate(true);
				$less_folders = JComponentHelper::getParams('com_flexicontent')->get('less_folders', 'JPATH_COMPONENT_SITE/assets/less/ :: JTEMPLATE_SITE/less/com_flexicontent/ ::');
				$_reps = array(
					'JPATH_COMPONENT_SITE' => JPATH_SITE.DS.'components'.DS.'com_flexicontent', 'JPATH_COMPONENT_ADMINISTRATOR' => JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent',
					'JPATH_SITE' => JPATH_SITE, 'JPATH_ADMINISTRATOR' => JPATH_ADMINISTRATOR,
					'JTEMPLATE_SITE' => $JTEMPLATE_SITE
				);
				$less_folders = str_replace(array_keys($_reps), $_reps, $less_folders);
				$less_folders = preg_split("/[\s]*::[\s]*/", $less_folders);
				foreach($less_folders as $k => $v)
				{
					if (!empty($v)) {
						$v = JPath::clean($v);
						$v .= $v[strlen($v) - 1] == DS ? '' : DS;
						$less_folders[$k] = $v;
					} else
						unset($less_folders[$k]);
				}
			}
			$inc_paths_all = array_merge($inc_paths, $less_folders);
		}

		//echo "<pre>"; debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS); echo "</pre>";
		//echo "<pre>"; print_r( $inc_paths_all); echo "</pre>";

		$_dirty_any = false;  // This FLAG is set in flag for all folders
		foreach ($inc_paths_all as $inc_path)
		{
			// Find if any "include" file has changed and set FLAG
			if ( !$inc_path )
				$_dirty = false;
			else if ( !JFolder::exists($inc_path) )
				$_dirty_arr[$inc_path] = $_dirty = false;
			else
				$_dirty = isset($_dirty_arr[$inc_path]) ? $_dirty_arr[$inc_path] : null;

			// Examine folder if not already examined
			if ($_dirty===null)
			{
				$_dirty = false;
				$inc_files = glob($inc_path.'*.{less}', GLOB_BRACE);  //print_r($inc_files);
				if (!is_array($inc_files) && $debug) JFactory::getApplication()->enqueueMessage('Reading LESS folder failed: '.$inc_path, 'notice');
				if (is_array($inc_files)) foreach ($inc_files as $confFile) {
					//echo $confFile . " time: ".filemtime($confFile) ."<br>";
					if (!JFile::exists($inc_path.'_config_fc_ts') || filemtime($confFile) > filemtime($inc_path.'_config_fc_ts')) {
						touch($inc_path.'_config_fc_ts');
						$_dirty = true;
						break;
					}
				}
			}
			$_dirty_arr[$inc_path] = $_dirty;
			$_dirty_any = $_dirty_any || $_dirty;
		}

		return $_dirty_any;
	}


	/**
	 * Checks and if needed compiles LESS files to CSS files
	 */
	static function checkedLessCompile($files, $path, $inc_paths=null, $force=false, $check_global_inc = true)
	{
		static $print_logging_info = null;
		$print_logging_info = $print_logging_info !== null  ?  $print_logging_info  :  JComponentHelper::getParams('com_flexicontent')->get('print_logging_info');
		$debug = JDEBUG || $print_logging_info;

		static $initialized;
		if ($initialized===null)
		{
			jimport('joomla.filesystem.path' );
			jimport('joomla.filesystem.folder');
			jimport('joomla.filesystem.file');
			$initialized = 1;
		}

		// Validate paths
		$path     = JPath::clean($path);
		if (!is_array($inc_paths)) $inc_paths = $inc_paths ? array($inc_paths) : array();
		foreach($inc_paths as $k => $v)
		{
			$v = JPath::clean($v);
			$v .= $v[strlen($v) - 1] == DS ? '' : DS;
			$inc_paths[$k] = $v;
		}

		// Check if LESS include paths have modified less files
		$_dirty = flexicontent_html::dirty_less_incPath_exists($inc_paths, $check_global_inc);

		// Find which LESS files have changed
		$stale = array();
		foreach ($files as & $inFile)
		{
			$inFile = JPath::clean($inFile);
			$inFilename = basename($inFile);
			$nameOnly   = basename($inFilename, '.less');
			$outFile    = 'css' .DS. $nameOnly . '.css';

			if (!JFile::exists($path.$inFile)) {
				if ($debug) JFactory::getApplication()->enqueueMessage('Path not found: '.$path.$inFile, 'warning');
			} else if ( $_dirty || $force || !is_file($path.$outFile) || filemtime($path.$inFile) > filemtime($path.$outFile) || (filesize($path.$outFile)===0 && is_writable($path.$outFile)) ) {
				$stale[$inFile] = $outFile;
			}
		}
		unset($inFile);
		//echo '<pre>'; print_r($stale); echo '</pre>';

		// We are done if no CSS files need to be updated
		if (empty($stale)) return array();

		static $prev_path = null;
		if ( $prev_path != $path && $debug )  JFactory::getApplication()->enqueueMessage('Compiling LESS files in: ' .$path, 'message');

		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'lessphp'.DS.'lessc.inc.php');
		$compiled = array();
		$msg = ''; $error = false;

		foreach ($stale as $in => $out)
		{
			// *** WARNING: Always create new object on every call, otherwise files needed more than one place, will may NOT be include
			$less = new \FLEXIcontent\lessc();  // JLess($fname = null, new JLessFormatterJoomla);
			$formater = new \FLEXIcontent\lessc_formatter_classic();
			$formater->disableSingle = true;
			$formater->breakSelectors = true;
			$formater->assignSeparator = ": ";
			$formater->selectorSeparator = ",";
			$formater->indentChar="\t";
			$less->setFormatter($formater);

			try
			{
				$wasCompiled = $less->compileFile($path.$in, $path.$out);  // $less->checkedCompile($path.$in, $path.$out);   // consider modification times
				if ($wasCompiled)  $compiled[$in] = $out;
			}
			catch (Exception $e)
			{
				$error = true;
				if ($debug || JFactory::getApplication()->isClient('administrator')) JFactory::getApplication()->enqueueMessage(
					'- LESS to CSS halted ... CSS file was not changed ... please edit LESS file(s) find offending <strong>lines</strong> and fix or remove<br>'. str_replace($path.$in, '<br><strong>'.$path.$in.'</strong>', $e->getMessage()), 'notice'
				);
				continue;
			}
		}

		if ( count($compiled) && $debug ) {
			foreach($compiled as $inPath => $outPath) $msg .= '<span class="row" style="display:block; margin:0;"><span class="span4">' . $inPath . '</span><span class="span4">' .$outPath . '</span></span>';
			JFactory::getApplication()->enqueueMessage(($prev_path != $path ? '<span class="row" style="display:block; margin:0;"><span class="span4">LESS</span><span class="span4">CSS</span></span>' : '').$msg, 'message');
		}

		$prev_path = $path;
		return !$error ? $stale : false;
	}


	/* Creates Joomla default canonical URL and also finds the configured SEF domain */
	static function getDefaultCanonical(&$_domain=null)
	{
		$app = JFactory::getApplication();
		$doc = JFactory::getDocument();

		if ($app->getName() != 'site' || $doc->getType() !== 'html') return;

		static $link   = null;
		static $domain = null;
		$_domain = $domain;  // pass it back by reference
		if ($link !== null) return $link;

		// Get SEF plugin configuration
		$plugin = JPluginHelper::getPlugin('system', 'sef');
		$pluginParams = new JRegistry($plugin ? $plugin->params : null);

		$domain = $pluginParams->get('domain');

		$jversion = new JVersion;
		$is_j35ge = version_compare( $jversion->getShortVersion(), '3.4.999', 'ge' );  // includes 3.5.0-beta* too

		if ( ($is_j35ge && $domain === false) || (!$is_j35ge && $domain === null) || $domain === '')
		{
			$domain = JUri::getInstance()->toString(array('scheme', 'host', 'port'));
		}
		$_domain = $domain;  // pass it back by reference

		$link = $domain . JRoute::_('index.php?' . http_build_query($app->getRouter()->getVars()), false);

		return $link;
	}


	/* Sets the given URL as rel canonical URL, also clearing any already set rel canonical URL */
	static function setRelCanonical($ucanonical)
	{
		$uri = JUri::getInstance();
		$doc = JFactory::getDocument();

		// Get canonical URL that SEF plugin adds, also $domain passed by reference, to get the domain configured in SEF plugin (multi-domain website)
		$domain = null;
		$defaultCanonical = flexicontent_html::getDefaultCanonical($domain);
		$domain = $domain ? $domain : $uri->toString(array('scheme', 'host', 'port'));

		// Remove trailing slash for home page without subfolder'
		if ($ucanonical === '/')
		{
			$ucanonical = '';
		}

		// Add scheme, domain, port
		$ucanonical = $domain . $ucanonical;

		// Encode the canonical URL
		$ucanonical_encoded = htmlspecialchars($ucanonical);

		// Get head object
		$head_obj = $doc->mergeHeadData(array(1=>1));

		// Remove canonical inserted by SEF plugin, unsetting default directly may not be reliable, instead we will search for it
		//unset($head_obj->_links[htmlspecialchars($defaultCanonical)]);
		$addRel = true;

		foreach($head_obj->_links as $link => $data)
		{
			if (strtolower($data['relation']) === 'canonical' && strtolower($data['relType']) === 'rel')
			{
				if($link == $ucanonical_encoded)
				{
					$addRel = false;
				}
				else
				{
					// Remove current rel canonical ... which is wrong ...
					unset($head_obj->_links[$link]);
					// Set add flag in case we found multiple canonicals ... and it was cleared by if statement ...
					$addRel = true;
				}
			}
		}

		// Add REL canonical only if different than current URL
		// * J3.5.1+ * Always add canonical, otherwise Joomla SEF plugin will add the default
		if ($addRel /*&& rawurldecode($uri->toString()) != $ucanonical*/)
		{
			$doc->addHeadLink( $ucanonical, 'canonical', 'rel' );
		}
	}


	/**
	 * Get visibility of table columns from Posted Form or from Browser Cookie
	 */
	static function getVisibleColumns($data_tbl_id)
	{
		$app  = JFactory::getApplication();
		$jinput = $app->input;
		
		static $columnchoose = false;

		if ($columnchoose === false)
		{
			// First try to find POSTED data
			$columnchoose = $jinput->post->get('columnchoose_'.$data_tbl_id, null, 'array');
			if ($columnchoose !== null)
			{
				$columnchoose = array_keys($columnchoose);
			}

			// Otherwise try to find COOKIE data
			else
			{
				$fc_columnchooser = $jinput->cookie->get('fc_columnchooser', '{}', 'string');

				// Parse the COLUMNS cookie
				try
				{
					$fc_columnchooser = json_decode($fc_columnchooser);

					// Reset cookie if it is not a class, or if the version hash does not matches (reset column chooser on every version upgrade)
					if (!is_object($fc_columnchooser) || !isset($fc_columnchooser->vhash) || $fc_columnchooser->vhash !== FLEXI_VHASH)
					{
						$fc_columnchooser = new stdClass();
						$fc_columnchooser->vhash = FLEXI_VHASH;
						$jinput->cookie->set('fc_columnchooser', json_encode($fc_columnchooser), time()+60*60*24*30, JUri::base(true), '');
					}

					// Get specific table data
					elseif (isset($fc_columnchooser->$data_tbl_id))
					{
						$columnchoose = preg_split("/[\s]*,[\s]*/", $fc_columnchooser->$data_tbl_id);
						foreach($columnchoose as $i => $id)
						{
							$columnchoose[$i] = (int) $id;
						}
					}
				}
				catch (Exception $e)
				{
					$fc_columnchooser = new stdClass();
					$fc_columnchooser->vhash = FLEXI_VHASH;
					$jinput->cookie->set('fc_columnchooser', json_encode($fc_columnchooser), time()+60*60*24*30, JUri::base(true), '');
					$columnchoose = null;
				}
			}
		}

		return $columnchoose;
	}


	/**
	 * Output the JavaScript to dynamically hide/show columns of a table
	 */
	static function jscode_to_showhide_table($container_div_id, $data_tbl_id, $start_html = '', $end_html = '', $toggle_on_init = 1)
	{
		$document = JFactory::getDocument();
		$app  = JFactory::getApplication();
		$jinput = $app->input;

		// Clear legacy cookie
		$legacy_cookie = $jinput->cookie->get('columnchoose_'.$data_tbl_id, null);
		if ($legacy_cookie) $jinput->cookie->set('columnchoose_'.$data_tbl_id, null, 1, '', '');

		$js = "
		var show_col_{$data_tbl_id} = Array();
		jQuery(document).ready(function() {
		";

		// Get visibility of table columns from Posted Form or from Browser Cookie
		$columnchoose = self::getVisibleColumns($data_tbl_id);

		if ($columnchoose) foreach ($columnchoose as $colnum)
		{
			$colnum = (int) $colnum;
			$js .= "show_col_{$data_tbl_id}[".$colnum."]=1; \n";
		}

		$firstload = $columnchoose !== null ? "false" : "true";
		$js .= "
			create_column_choosers('$container_div_id', '$data_tbl_id', $firstload, '".$start_html."', '".$end_html."', " . (int) $toggle_on_init . ");
		});
		";
		$document->addScriptDeclaration($js);
	}


	/**
	 * Function to create the tooltip text regardless of Joomla version
	 *
	 * @return 	string  : the HTML of the tooltip for usage in the title paramter of the HTML tag
	 * @since 1.5
	 */
	static function getToolTip($title = '', $content = '', $translate = 1, $escape = 1)
	{
		return JHtml::tooltipText($title ?? '', $content ?? '', $translate, $escape);
	}


	static function escape($str)
	{
		return htmlspecialchars($str, ENT_COMPAT, 'UTF-8');
	}


	static function get_basedomain($url)
	{
		$pieces = parse_url($url);
		$domain = isset($pieces['host']) ? $pieces['host'] : '';
		if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs))
		{
			return $regs['domain'];
		}
		return false;
	}


	static function is_safe_url($url, $baseonly=false)
	{
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		$allowed_redirecturls = $cparams->get('allowed_redirecturls', 'internal_base');  // Parameter does not exist YET

		// prefix the URL if needed so that parse_url will work
		$has_prefix = preg_match("#^http|^https|^ftp|^ftps#i", $url);

		if (!$has_prefix) 
		{
			$is_abs_noproto = strpos($url, "//") === 0;
			$is_abs_urlpath = strpos($url, "/") === 0;

			if ($is_abs_noproto)
			{
				$url = 'http:' . $url;
			}
			elseif (!$is_abs_urlpath)
			{
				$url = JUri::base() . $url;
			}
			else
			{
				$url = 'http://' . $url;
			}
		}

		// Require baseonly internal url: (HOST only)
		if ( $baseonly || $allowed_redirecturls == 'internal_base' )
			return flexicontent_html::get_basedomain($url) == flexicontent_html::get_basedomain(JUri::base());

		// Require full internal url: (HOST + this JOOMLA folder)
		else // if ( $allowed_redirecturls == 'internal_full' )
			return parse_url($url, PHP_URL_HOST) == parse_url(JUri::base(), PHP_URL_HOST);

		// Allow any URL, (external too) this may be considered a vulnerability for unlogged/logged users, since
		// users may be redirected to an offsite URL despite clicking an internal site URL received e.g. by an email
		//else
		//	return true;
	}


	static function make_absolute_url($link)
	{
		static $domain = null;
		if ($domain === null)
		{
			$uri = JUri::getInstance('SERVER');
			$domain   = $uri->gethost();
		}

		// Is absolute with protocol, NOTHING TO DO
		if ( parse_url($link, PHP_URL_SCHEME) ) $prefix = '';

		// Is absolute without protocol, NOTHING TO DO
		else if (strpos($link, '//') === 0) $prefix = '';

		// Has current domain but no protocol, ADD '//' (so that it is treated as absolute URL)
		else if (strpos($link, $domain) === 0) $prefix = '//';

		// Is absolute without protocol - host - port, NOTHING TO DO
		else if (strpos($link, '/') === 0) $prefix = '';

		// Anything else is relative, prepend full joomla root uri
		else
		{
			$prefix = JUri::root();
		}

		$link = empty($link) ? '' : $prefix . $link;
		return $link;
	}


	/**
	 * Function to render the item view of a given item id
	 *
	 * @param 	int 		$item_id
	 * @return 	string  : the HTML of the item view, also the CSS / JS file would have been loaded
	 * @since 1.5
	 */
	function renderItem($item_id, $view=FLEXI_ITEMVIEW, $ilayout = '')
	{
		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.fields.php');
		//require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'permission.php');
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.FLEXI_ITEMVIEW.'.php');

		$app  = JFactory::getApplication();
		$user = JFactory::getUser();

		$itemmodel = new FlexicontentModelItem();
		$item = $itemmodel->getItem($item_id, $check_view_access=false);

		$aid = JAccess::getAuthorisedViewLevels($user->id);

		// Get Item's specific ilayout
		if (!$ilayout)
		{
			$ilayout = $item->parameters->get('ilayout');
		}

		// Get type's ilayout
		if (!$ilayout)
		{
			$type = JTable::getInstance('flexicontent_types', '');
			$type->id = $item->type_id;
			$type->load();
			$type->params = new JRegistry($type->attribs);
			$ilayout = $type->params->get('ilayout') ?: 'default';
		}

		// Get cached template data, re-parsing XML/LESS files, also loading any template language files of a specific template
		$themes = flexicontent_tmpl::getTemplates(array($ilayout));

		// Get Fields
		list($item) = FlexicontentFields::getFields($item, $view, $item->parameters, $aid);

		// WE WILL NOT LOAD CSS/JS of the item as it may have undesired effects !
		// TODO add parameter for this

		// WE DO NOT TRIGGER CONTENT PLUGINS, as it may have undesired effects ?
		// TODO add parameter for this

		$this->item = $item;
		$this->params_saved = @$this->params;
		$this->params = $item->parameters;
		$this->tmpl = '.item.'.$ilayout;
		$this->print_link = JRoute::_('index.php?view='.FLEXI_ITEMVIEW.'&id='.$item->slug.'&pop=1&tmpl=component&print=1');
		$this->pageclass_sfx = '';

		if (!isset($this->item->event))
		{
			$this->item->event = new stdClass();
		}

		$this->item->event->beforeDisplayContent = '';
		$this->item->event->afterDisplayTitle = '';
		$this->item->event->afterDisplayContent = '';
		$this->fields = & $this->item->fields;

		// start capturing output into a buffer
		ob_start();

		// Include the requested template filename in the local scope (this will execute the view logic).
		if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates'.DS.$ilayout))
		{
			include JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates'.DS.$ilayout.DS.'item.php';
		}
		elseif (file_exists(JPATH_COMPONENT.DS.'templates'.DS.$ilayout))
		{
			include JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.$ilayout.DS.'item.php';
		}
		else
		{
			include JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.'default'.DS.'item.php';
		}

		// done with the requested template; get the buffer and clear it.
		$item_html = ob_get_contents();
		ob_end_clean();
		$this->params = $this->params_saved;

		return $item_html;
	}


	static function listall_selector(&$params, $formname='adminForm', $autosubmit=1)
	{
		if (flexicontent_html::initial_list_limited($params))
		{
			$tooltip_class = 'hasTooltip';
			return '
				<input type="checkbox" id="listall" name="listall" value="1" form="'.$formname.'"/>
				<label id="listall-lbl" for="listall" class="btn '.$tooltip_class.'" style="width:100%; margin:8px 0; box-sizing:border-box;" title="'.JText::_('FLEXI_LISTING_ONLY_FEATURED_CLICK_TO_LIST_ALL_DESC', true).'">
					'.JText::_('FLEXI_LIST_ALL_ITEMS').'
				</label>
			';
		}
	}


	static function initial_list_limited(&$params)
	{
		$use_limit_before = (int) $params->get('use_limit_before_search_filt', 0);

		if ($use_limit_before < 2)
		{
			return false;
		}

		$use_limit_before_search_filt = JFactory::getApplication()->getUserState('use_limit_before_search_filt');

		if ($use_limit_before_search_filt < 2)
		{
			return false;
		}

		return true;
	}


	static function limit_selector(&$params, $formname='adminForm', $autosubmit=1)
	{
		if (!$params->get('limit_override'))
		{
			return '';
		}

		$app = JFactory::getApplication();

		$default_limit = (int) ($app->getUserState('use_limit_before_search_filt') ? $params->get('limit_before_search_filt') : $params->get('limit'));
		$limit_given = strlen( $app->input->get('limit', '', 'string') );
		$limit = $limit_given ? $app->input->get('limit', 0, 'int') : $default_limit;

		flexicontent_html::loadFramework('select2');
		$classes  = "fc_field_filter use_select2_lib";

		$attribs = array(
	    'id' => 'limit', // HTML id for select field
	    'list.attr' => array( // additional HTML attributes for select field
	    ),
	    'list.translate'=>false, // true to translate
	    'option.key'=>'value',   // key name for value in data array
	    'option.text'=>'text',   // key name for text in data array
	    'option.attr'=>'attr',   // key name for attr in data array
	    'list.select'=>$limit    // value of the SELECTED field
		);
		$attribs['list.attr']['onchange'] = $autosubmit ? "adminFormPrepare(this.form, 2);" : null;
		$attribs['list.attr']['class'] = $classes;
		$attribs['list.attr']['form'] = $formname;

		$limit_options = $params->get('limit_options', '5,10,20,30,50,100,150,200');
		$limit_options = preg_split("/[\s]*,[\s]*/", $limit_options);

		$limit_options = ArrayHelper::toInteger($limit_options);

		if (!in_array($default_limit, $limit_options))
		{
			$limit_options[] = $default_limit;
		}

		$limiting = array();

		$limit_override_label = $params->get('limit_override_label', 2);
		$inside_label = $limit_override_label==2 ? ' '.JText::_('FLEXI_PER_PAGE') : '';

		if ($app->getUserState('use_limit_before_search_filt'))
		{
			$attribs['list.attr']['disabled'] = 'disabled';
			$limiting[] = array(
				'value' => $default_limit,
				'text'  => $default_limit . $inside_label,
				'attr'  => array('data-is-default-value' => '1')
			);
		}
		else foreach($limit_options as $limit_option)
		{
			$attr_arr = $default_limit == $limit_option ? array('data-is-default-value' => '1') : array();
			$limiting[] = array(
				'value' => $limit_option,
				'text'  => $limit_option . $inside_label,
				'attr'  => $attr_arr
			);
		}

		// Outside label
		$outside_label = '';
		if ($limit_override_label==1) {
			$outside_label = '<span class="flexi label limit_override_label">'.JText::_('FLEXI_PER_PAGE').'</span>';
		}

		return $outside_label.JHtml::_('select.genericlist', $limiting, 'limit', $attribs);
	}


	static function orderby_selector(&$params, $formname='adminForm', $autosubmit=1, $extra_order_types=array(), $sfx='')
	{
		if ( !$params->get('orderby_override'.$sfx, 0) ) return '';

		$app	= JFactory::getApplication();

		$default_orderby = $params->get( 'orderby'.$sfx );
		$orderby = $app->input->get('orderby'.$sfx, '', 'string');
		$orderby = $orderby ? $orderby : $default_orderby;

		flexicontent_html::loadFramework('select2');
		$classes  = "fc_field_filter use_select2_lib";

		$attribs = array(
	    'id' => 'orderby'.$sfx, // HTML id for select field
	    'list.attr' => array( // additional HTML attributes for select field
	    ),
	    'list.translate'=>false, // true to translate
	    'option.key'=>'value',   // key name for value in data array
	    'option.text'=>'text',   // key name for text in data array
	    'option.attr'=>'attr',   // key name for attr in data array
	    'list.select'=>$orderby  // value of the SELECTED field
		);
		$attribs['list.attr']['onchange'] = $autosubmit ? "adminFormPrepare(this.form, 2);" : null;
		$attribs['list.attr']['class'] = $classes;
		$attribs['list.attr']['form'] = $formname;

		$orderby_options = $params->get(
			'orderby_options'.$sfx,
			array('_preconfigured_','date','rdate','modified','alpha','ralpha','author','rauthor','hits','rhits','id','rid','order','jorder')
		);
		$orderby_options = FLEXIUtilities::paramToArray($orderby_options);

		$orderby_names = array(
			'_preconfigured_'=>'FLEXI_ORDER_DEFAULT_INITIAL',
			'date'=>'FLEXI_ORDER_OLDEST_FIRST',
			'rdate'=>'FLEXI_ORDER_MOST_RECENT_FIRST',
			'modified'=>'FLEXI_ORDER_LAST_MODIFIED_FIRST',
			'published'=>'FLEXI_ORDER_RECENTLY_PUBLISHED_FIRST',
			'published_oldest'=>'FLEXI_ORDER_OLDEST_PUBLISHED_FIRST',
			'alpha'=>'FLEXI_ORDER_TITLE_ALPHABETICAL',
			'ralpha'=>'FLEXI_ORDER_TITLE_ALPHABETICAL_REVERSE',
			'author'=>'FLEXI_ORDER_AUTHOR_ALPHABETICAL',
			'rauthor'=>'FLEXI_ORDER_AUTHOR_ALPHABETICAL_REVERSE',
			'hits'=>'FLEXI_ORDER_MOST_HITS',
			'rhits'=>'FLEXI_ORDER_LEAST_HITS',
			'id'=>'FLEXI_ORDER_HIGHEST_ITEM_ID',
			'rid'=>'FLEXI_ORDER_LOWEST_ITEM_ID',
			'commented'=>'FLEXI_ORDER_MOST_COMMENTED',
			'rated'=>'FLEXI_ORDER_BEST_RATED',
			'order'=>'FLEXI_ORDER_CONFIGURED_ORDER',
			'jorder'=>'FLEXI_ORDER_CONFIGURED_ORDER_JOOMLA',
			'random'=>'FLEXI_ORDER_RANDOM',
			'alias'=>'FLEXI_ORDER_ALIAS',
			'ralias'=>'FLEXI_ORDER_ALIAS_REVERSE'
		);

		$ordering = array();
		foreach ($extra_order_types as $value => $text)
		{
			$text = JText::_( $text );
			//$ordering[] = JHtml::_('select.option',  $value,  $text);
			$attr_arr = $default_orderby == $value ? array('data-is-default-value' => '1') : array();
			$ordering[] = array(
				'value' => $value,
				'text'  => $text,
				'attr'  => $attr_arr
			);
		}
		foreach ($orderby_options as $orderby_option)
		{
			if ($orderby_option=='__SAVED__') continue;
			$value = ($orderby_option!='_preconfigured_') ? $orderby_option : '';
			$text = JText::_( $orderby_names[$orderby_option] );
			//$ordering[] = JHtml::_('select.option',  $value,  $text);
			$attr_arr = $default_orderby == $value ? array('data-is-default-value' => '1') : array();
			$ordering[] = array(
				'value' => $value,
				'text'  => $text,
				'attr'  => $attr_arr
			);
		}


		// Add custom field orderings
		$orderby_custom = $params->get('orderby_custom'.$sfx, '');
		$orderby_custom = preg_split("/\s*,\s*/u", $orderby_custom);
		$custom_order_types = array('int'=>1, 'decimal'=>1, 'date'=>1, 'file_hits'=>1);

		$field_ids = array();
		$custom_ops = array();
		$n = 0;
		foreach ($orderby_custom as $custom_option)
		{
			$order_parts = preg_split("/:/", $custom_option);
			if (count($order_parts)!=3 && count($order_parts)!=4) continue;  // ignore order with wrong number parts
			$_field_id = (int) @ $order_parts[0];
			if (!$_field_id) continue;  // ignore order with bad fieldid
			if (!isset($custom_order_types[@ $order_parts[1]]))  continue;  // ignore order with bad type
			$field_ids[$_field_id] = 1;
			$custom_ops[$n] = $order_parts;
			$n++;
		}

		$fields = FlexicontentFields::getFieldsByIds(array_keys($field_ids));
		foreach($custom_ops as $op)
		{
			$field_id = $op[0];
			$field    = $fields[$field_id];
			$value = 'custom:'.$op[0].':'.$op[1].':'.$op[2];
			if (count($op)==4) {
				$text = JText::_( $op[3] );
			} else {
				$text = JText::_( $field->label ) .' '. JText::_(strtolower($op[2])=='asc' ? 'FLEXI_INCREASING' : 'FLEXI_DECREASING');
			}
			//$ordering[] = JHtml::_('select.option', $value,  $text);
			$attr_arr = $default_orderby == $value ? array('data-is-default-value' => '1') : array();
			$ordering[] = array(
				'value' => $value,
				'text'  => $text,
				'attr'  => $attr_arr
			);
		}

		return JHtml::_('select.genericlist', $ordering, 'orderby'.$sfx, $attribs);
	}


	static function ordery_selector(&$params, $formname='adminForm', $autosubmit=1, $extra_order_types=array(), $sfx='')
	{
		return //'Please search and replace (in your template files): flexicontent_html::ordery_selector with flexicontent_html::orderby_selector'.
			flexicontent_html::orderby_selector($params, $formname, $autosubmit, $extra_order_types, $sfx);
	}


	static function layout_selector(&$params, $formname='adminForm', $autosubmit=1, $layout_type='clayout')
	{
		if ( !$params->get($layout_type.'_switcher') ) return '';
		$default_layout = $params->get($layout_type.'_default', $layout_type=='clayout' ? 'blog' : 'default');

		if ($layout_type=='clayout')
		{
			$displayed_tmpls = $params->get('displayed_'.$layout_type.'s');
			if ( empty($displayed_tmpls) )							$displayed_tmpls = array();
			else if ( ! is_array($displayed_tmpls) )		$displayed_tmpls = explode("|", $displayed_tmpls);
			$current_layout = $params->get('clayout');
			if (count($displayed_tmpls) && $current_layout && !in_array($current_layout, $displayed_tmpls)) $displayed_tmpls[] = $current_layout;
		}

		$allowed_tmpls = $params->get('allowed_'.$layout_type.'s');
		if ( empty($allowed_tmpls) )							$allowed_tmpls = array();
		else if ( ! is_array($allowed_tmpls) )		$allowed_tmpls = explode("|", $allowed_tmpls);

		// Return if none allowed layout(s) were configured / allowed
		$layout_names = $layout_type=='clayout' ? $displayed_tmpls : $allowed_tmpls;
		if (!count($layout_names))  return false;

		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');
		$layout = $jinput->get('layout', '', 'cmd');

		$svar = $layout ? '.'.$layout : '.category';
		$layout_typename = $layout_type=='clayout' ? 'category' : 'items';

		/*if (!$layout) $svar .= $jinput->get('cid', 0, 'INT');
		else if ($layout=='tags') $svar .= $jinput->get('tagid', 0, 'INT');
		else if ($layout=='author') $svar .= $jinput->get('authorid', 0, 'INT');
		if ($layout) $svar .= '.category'.$jinput->get('cid', 0, 'INT');*/

		//$layout = $app->getUserStateFromRequest( $option.$svar.'.'.$layout_type, $layout_type, $default_layout, 'cmd' );
		$layout = $jinput->get($layout_type, $default_layout, 'cmd') ;

		$_switcher_label = $params->get($layout_type.'_switcher_label', 0);
		$inside_label  = $_switcher_label==2 ? ' '.JText::_('FLEXI_LAYOUT') : '';
		$outside_label = $_switcher_label==1 ? '<span class="flexi label limit_override_label">'.JText::_('FLEXI_LAYOUT').'</span>' : '';

		// Get layout titles
		$layout_texts = flexicontent_tmpl::getLayoutTexts($layout_typename);

		if ( $params->get('clayout_switcher_display_mode', 1) == 0 )
		{
			flexicontent_html::loadFramework('select2');
			$classes  = "fc_field_filter use_select2_lib";
			//$onchange = !$autosubmit ? '' : ' onchange="adminFormPrepare(this.form, 2);" ';
			//$attribs  = ' class="'.$classes.'" ' . $onchange . ' form="'.$formname.'" ';

			$attribs = array(
		    'id' => $layout_type, // HTML id for select field
		    'list.attr' => array( // additional HTML attributes for select field
		    ),
		    'list.translate'=>false, // true to translate
		    'option.key'=>'value',   // key name for value in data array
		    'option.text'=>'text',   // key name for text in data array
		    'option.attr'=>'attr',   // key name for attr in data array
		    'list.select'=>$layout   // value of the SELECTED field
			);
			$attribs['list.attr']['onchange'] = $autosubmit ? "adminFormPrepare(this.form, 2);" : null;
			$attribs['list.attr']['class'] = $classes;
			$attribs['list.attr']['form'] = $formname;

			$options = array();
			foreach($layout_names as $layout_name)
			{
				$layout_title = !empty($layout_texts->$layout_name->title)  ?  $layout_texts->$layout_name->title  :  $layout_name;
				//$options[] = JHtml::_('select.option', $layout_name, $layout_title .$inside_label);
				$attr_arr = $default_layout == $layout_name ? array('data-is-default-value' => '1') : array();
				$options[] = array(
					'value' => $layout_name,
					'text'  => $layout_title .$inside_label,
					'attr'  => $attr_arr
				);
			}
			$html = JHtml::_('select.genericlist', $options, $layout_type, $attribs);
		}
		else
		{
			$tmplurl = 'components/com_flexicontent/templates/';
			$tooltip_class = ' hasTooltip';

			$n = 0;
			$options = array();
			foreach($layout_names as $layout_name)
			{
				$layout_title = !empty($layout_texts->$layout_name->title)  ?  $layout_texts->$layout_name->title  :  '';
				$checked_attr = $layout==$layout_name ? ' checked=checked ' : '';
				$is_default_attr = $default_layout == $layout_name ? ' data-is-default-value="1" ' : '';
				$options[] =
					'<input form="'.$formname.'" type="radio" name="'.$layout_type.'" value="'.$layout_name.'" id="'.$layout_type.$n.'" onchange="adminFormPrepare(this.form, 2); return true;" '.$checked_attr.$is_default_attr.'>'.
					'<label for="'.$layout_type.$n.'" class="btn '.$tooltip_class.'" title="'.$layout_title.'"><img alt="'.$layout_name.'" src="'.$tmplurl.$layout_name.'/clayout.png"></label>'
					;
				$n++;
			}
			$html = '
				<fieldset class="radio btn-group group-fcinfo">
					'.implode('', $options).'
				</fieldset>
			';
			JFactory::getDocument()->addScriptDeclaration('jQuery(document).ready(function(){ jQuery(\'input[name="'.$layout_type.'"]\').click( function() { adminFormPrepare(this.form, 2); }); });');
		}
		return $outside_label.$html;
	}


	static function searchphrase_selector(&$params, $formname='adminForm')
	{
		$show_searchphrase = $params->get('show_searchphrase', 1);

		if (!$show_searchphrase)
		{
			return '';
		}

		$app = JFactory::getApplication();

		$default_searchphrase = $params->get('default_searchphrase', 'all');
		$p = $app->input->getWord('searchphrase', $app->input->getWord('p', $default_searchphrase));

		$searchphrase_names = array(
			'all'=>'FLEXI_ALL_WORDS',
			'any'=>'FLEXI_ANY_WORDS',
			'natural'=>'FLEXI_NATURAL_PHRASE',
			'natural_expanded'=>'FLEXI_NATURAL_PHRASE_MORE_RESULTS',
			'exact'=>'FLEXI_EXACT_PHRASE',
		);

		$searchphrases = array();

		foreach ($searchphrase_names as $searchphrase_value => $searchphrase_name)
		{
			$searchphrases[] = array(
				'value' => $searchphrase_value,
				'text'  => $searchphrase_name,
				'attr'  => $default_searchphrase === $searchphrase_value ? array('data-is-default-value' => '1') : array()
			);
		}

		$attribs = array(
			'id' => 'searchphrase', // HTML id for select field
			'group.id' => 'id',
			'list.attr' => array('class' => 'fc_field_filter use_select2_lib'),
			'list.translate' => true, // true to translate
			'option.key'  => 'value', // key name for value in data array
			'option.text' => 'text',  // key name for text in data array
			'option.attr' => 'attr',  // key name for attr in data array
			'list.select' => $p, // value of the SELECTED field
		);

		return JHtml::_('select.genericlist', $searchphrases, 'p', $attribs);
	}


	/**
	 * Utility function to add JQuery to current Document
	 *
	 * @param 	string 		$text
	 * @param 	int 		$nb
	 * @return 	string
	 * @since 1.5
	 */
	static function loadJQuery( $add_jquery = 1, $add_jquery_ui = 1, $add_jquery_ui_css = 1, $add_remote = 1, $params = null )
	{
		static $jquery_added = false;
		static $jquery_ui_added = false;
		static $jquery_ui_css_added = false;

		$document = JFactory::getDocument();
		$flexiparams = JComponentHelper::getParams('com_flexicontent');
		$lib_path = '/components/com_flexicontent/librairies';

		$add_remote_forced_jquery = $add_remote === 2;
		$add_remote_forced_jquery_ui = $add_remote === 2;

		if (!$params)
		{
			$params = new JRegistry;
		}

		// Set jQuery to load in views that use it
		$JQUERY_VER = FLEXI_J40GE ? '3.3.1' : $params->get('jquery_ver', $flexiparams->get('jquery_ver', '1.8.3'));
		$JQUERY_VER_URL_ATTRS = FLEXI_J40GE ? array(
			'integrity' => 'sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=',
			'crossorigin' => 'anonymous',
		) : null;

		$JQUERY_UI_VER = FLEXI_J40GE ? '1.13.1' : $params->get('jquery_ui_ver', $flexiparams->get('jquery_ui_ver', '1.9.2'));
		$JQUERY_UI_THEME = $params->get('jquery_ui_theme', $flexiparams->get('jquery_ui_theme', 'ui-lightness'));   // FLEXI_JQUERY_UI_CSS_STYLE:  'ui-lightness', 'smoothness'
		if (!in_array($JQUERY_UI_THEME, array('ui-lightness', 'smoothness')))
		{
			$JQUERY_UI_THEME = 'ui-lightness';
		}
		JText::script("FLEXI_FORM_IS_BEING_SUBMITTED", true);
		JText::script("FLEXI_LOADING", true);

		/*
		 * jQuery library
		 */

		if ( $add_jquery && !$jquery_added && !JPluginHelper::isEnabled('system', 'jquerysupport') )
		{
			if ($add_remote_forced_jquery)
			{
				JHtml::_('jquery.framework');   // add and "override" it
				$document->addScript('//code.jquery.com/jquery-'.$JQUERY_VER.'.min.js', null, $JQUERY_VER_URL_ATTRS);
			}
			else
			{
				FLEXI_J30GE
					? JHtml::_('jquery.framework')
					: $document->addScript(JUri::root(true).$lib_path.'/jquery/js/jquery-'.$JQUERY_VER.'.min.js');
			}

			// The 'noConflict()' statement must be inside a js file, to make sure it executed immediately
			if (!FLEXI_J30GE)
			{
				$document->addScript(JUri::root(true).$lib_path.'/jquery/js/jquery-no-conflict.js');
			}
			//$document->addCustomTag('<script>jQuery.noConflict();</script>');  // not placed in proper place
			$jquery_added = 1;
		}

		/*
		 * jQuery-UI library (and its CSS)
		 */

		if ($add_jquery_ui && !$jquery_ui_added)
		{
			// Load all components of jQuery-UI (add and "override" it)
			if ($add_remote_forced_jquery_ui)
			{
				!FLEXI_J40GE
					? JHtml::_('jquery.ui', array())
					: false;
				$document->addScript('//code.jquery.com/ui/'.$JQUERY_UI_VER.'/jquery-ui.min.js');
			}
			else
			{
				if (FLEXI_J40GE)
				{
					$document->addScript(JUri::root(true).$lib_path.'/jquery/js/jquery-ui-'.$JQUERY_UI_VER.'/jquery-ui.min.js');
				}
				elseif (FLEXI_J30GE)
				{
					JHtml::_('jquery.ui', array('core', 'sortable'));   // 'core' in J3+ includes all parts of jQuery-UI CORE component: Core, Widget, Mouse, Position
					if ( !$params || $params->get('load-ui-dialog', 1) )        $document->addScript(JUri::root(true).$lib_path.'/jquery/js/jquery-ui/jquery.ui.dialog.min.js');
					if ( !$params || $params->get('load-ui-menu', 1) )          $document->addScript(JUri::root(true).$lib_path.'/jquery/js/jquery-ui/jquery.ui.menu.min.js');
					if ( !$params || $params->get('load-ui-autocomplete', 1) )  $document->addScript(JUri::root(true).$lib_path.'/jquery/js/jquery-ui/jquery.ui.autocomplete.min.js');
					if ( !$params || $params->get('load-ui-progressbar', 1) )   $document->addScript(JUri::root(true).$lib_path.'/jquery/js/jquery-ui/jquery.ui.progressbar.min.js');
				}
				else
				{
					$document->addScript(JUri::root(true).$lib_path.'/jquery/js/jquery-ui-'.$JQUERY_UI_VER.'.js');
				}
			}

			$jquery_ui_added = 1;
		}

		// Add jQuery UI theme, this is included in J3+ when executing jQuery-UI framework is called
		if ( $add_jquery_ui_css && !$jquery_ui_css_added )
		{
			// FLEXI_JQUERY_UI_CSS_STYLE:  'ui-lightness', 'smoothness', ...
			$add_remote_forced_jquery_ui
				? $document->addStyleSheet('//code.jquery.com/ui/'.$JQUERY_UI_VER.'/themes/'.$JQUERY_UI_THEME.'/jquery-ui.css')
				: $document->addStyleSheet(JUri::root(true).$lib_path.'/jquery/css/'.$JQUERY_UI_THEME.'/jquery-ui-'.$JQUERY_UI_VER.'.css');
			$jquery_ui_css_added = 1;
		}
	}


	/**
	 * Utility function to get the Mobile Detector Object
	 *
	 * @param 	string 		$text
	 * @param 	int 		$nb
	 * @return 	string
	 * @since 1.5
	 */
	static function getMobileDetector()
	{
		static $mobileDetector = null;

		if ( $mobileDetector===null ) {
			require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'mobiledetect'.DS.'Mobile_Detect.php');
			$mobileDetector = new Mobile_Detect_FC();
		}

		return $mobileDetector;
	}


	/**
	 * Utility function to load each JS Frameworks once
	 *
	 * @param 	string 		$text
	 * @param 	int 		$nb
	 * @return 	string
	 * @since 1.5
	 */
	static function loadFramework( $framework, $mode='', $params=null )
	{
		// Detect already loaded framework
		static $_loaded = array();
		if ( isset($_loaded[$framework]) ) return $_loaded[$framework];
		$_loaded[$framework] = false;

		// Get frameworks that are configured to be loaded manually in frontend (e.g. via the Joomla template)
		$app     = JFactory::getApplication();
		$cparams = JComponentHelper::getParams('com_flexicontent');

		static $load_frameworks = null;
		static $load_jquery = null;

		if (!isset($load_frameworks[$framework]))
		{
			if ($load_jquery === null)
			{
				$load_jquery = (int) $cparams->get('loadfw_jquery', 1) === 1 || !$app->isClient('site');
			}

			$loadfw_param   = 'loadfw_' . strtolower(str_replace('-', '_', $framework));
			$load_framework = (int) $cparams->get($loadfw_param, 1);

			$load_frameworks[$framework] = $load_framework === 1 || ($load_framework === 2 && !$app->isClient('site'));
		}

		// Set loaded flag
		$_loaded[$framework] = $load_frameworks[$framework];
		// Do not progress further if it is disabled
		if ( !$load_frameworks[$framework] ) return false;

		// Load Framework
		$document = JFactory::getDocument();
		$lib_path = '/components/com_flexicontent/librairies';
		$js = "";
		$css = "";

		static $specific_browser_support = null;
		if ( $specific_browser_support === null )
		{
			if ( method_exists($document, 'addCustomTag') ) $document->addCustomTag('
				<!--[if IE 8]>
				<link href="'.JUri::root(true).'/components/com_flexicontent/assets/css/ie8.css?' . FLEXI_VHASH . '" rel="stylesheet" />
				<![endif]-->
			');
			$specific_browser_support = true;
		}

		static $shared_js_added = null;
		if ( $shared_js_added === null )
		{
			$js .= "
				var jbase_url_fc = ".json_encode(JUri::base()).";
				var jroot_url_fc = ".json_encode(JUri::root()).";
				var jclient_path_fc = ".json_encode(JUri::base(true)).";
			";
			$shared_js_added = true;
		}

		switch ( $framework )
		{
			case 'jQuery':
				if ($load_jquery) flexicontent_html::loadJQuery(1, 1, 1, 1, $params);
				break;

			case 'bootstrap-toggle':
				if ($load_jquery) flexicontent_html::loadJQuery();

				$framework_path = JUri::root(true).$lib_path.'/bootstrap-toggle';
				$document->addScript($framework_path.'/js/bootstrap2-toggle.min.js');
				$document->addStyleSheet($framework_path.'/css/bootstrap2-toggle.min.css');

				$js .= "
					jQuery(document).ready(function(){
						jQuery('input.fc_checkboxtoggle').bootstrapToggle();
					});
				";

				break;

			case 'touch-punch':
				$mobileDetector = flexicontent_html::getMobileDetector();
				$isMobile = $mobileDetector->isMobile() || $mobileDetector->isTablet();
				if ($isMobile)
				{
					if ($load_jquery) flexicontent_html::loadJQuery();
					$document->addScript(JUri::root(true).$lib_path.'/jquery/js/jquery-ui/jquery.ui.touch-punch.min.js');
				}
				break;

			case 'grapejs':

				$framework_path = JUri::root(true).$lib_path.'/grapesjs';

				$grapjs_vers = '0.16.22'; // need to update
				$url_grapjs_css = 'https://unpkg.com/grapesjs@' . $grapjs_vers . '/dist/css/grapes.min.css';
				$url_grapjs_js = 'https://unpkg.com/grapesjs@' . $grapjs_vers . '/dist/grapes.min.js';

				$document->addStyleSheet($framework_path.'/stylesheets/toastr.min.css');
				$document->addStyleSheet($url_grapjs_css);
				$document->addStyleSheet($framework_path.'/stylesheets/grapesjs-preset-webpage.min.css?0.1.10');
				$document->addStyleSheet($framework_path.'/stylesheets/tooltip.css');
				$document->addStyleSheet($framework_path.'/stylesheets/grapesjs-plugin-filestack.css');
				$document->addStyleSheet($framework_path.'/stylesheets/demos.css');

				$document->addScript('https://static.filestackapi.com/v3/filestack.js');
				$document->addScript($framework_path.'/js/toastr.min.js');
				$document->addScript($url_grapjs_js);


				// GrapesJS Plugins
				//$document->addScript('https://unpkg.com/grapesjs-blocks-basic');
				$document->addScript($framework_path.'/js/grapesjs-preset-webpage.min.js?0.1.10');
				$document->addScript($framework_path.'/js/grapesjs-lory-slider.min.js?0.1.5');
				$document->addScript($framework_path.'/js/grapesjs-tabs.min.js?0.1.1');
				$document->addScript($framework_path.'/js/grapesjs-custom-code.min.js?0.1.1');
				$document->addScript($framework_path.'/js/grapesjs-touch.min.js?0.1.1');
				$document->addScript($framework_path.'/js/grapesjs-parser-postcss.min.js?0.1.1');
				$document->addScript($framework_path.'/js/grapesjs-tooltip.min.js?0.1.1');
				//shapedivider module
				//$document->addScript('https://unpkg.com/grapesjs-shape-divider');  
				//ckeditor for graps
				$document->addScript($framework_path.'/js/grapesjs-plugin-ckeditor.min.js?0.0.9');
				$document->addScript('https://cdn.ckeditor.com/4.14.1/standard-all/ckeditor.js');
				//bs4
				//$document->addScript($framework_path.'/js/grapesjs-blocks-bootstrap4.min');
				break;

			case 'grapesjs_view':
				$js .= "
					jQuery(document).ready(function(){
						jQuery('.tab-container').click(function(e)
						{
							e.preventDefault();
							return true;
						});
					});
				";
				break;

			case 'sabberworm':

				$framework_folder = JPATH_ROOT.$lib_path.'/php_css_parser';
				require_once(JPath::clean($framework_folder . '/autoload.php'));
				break;

			case 'mousewheel':
				if ($load_jquery) flexicontent_html::loadJQuery();

				$framework_path = JUri::root(true).$lib_path.'/mousewheel';
				$document->addScript($framework_path.'/jquery.mousewheel.min.js');
				break;

			case 'mCSB':
				if ($load_jquery) flexicontent_html::loadJQuery();

				$framework_path = JUri::root(true).$lib_path.'/mCSB';

				// Load jQuery mouse wheel as this JS can make use of it
				flexicontent_html::loadFramework('mousewheel');

				// Add mousewheel plugin (this is optional)
				//$document->addScript($framework_path.'/jquery.mousewheel.min.js');

				$document->addScript($framework_path.'/jquery.mCustomScrollbar.min.js');
				$document->addStyleSheet($framework_path.'/jquery.mCustomScrollbar.min.css');

				$js .= "
					jQuery(document).ready(function(){
						jQuery('.fc_add_scroller').mCustomScrollbar({
							theme:'dark-thick',
							advanced:{updateOnContentResize: true}
						});
						jQuery('.fc_add_scroller_horizontal').mCustomScrollbar({
							theme:'dark-thick',
							horizontalScroll:true,
							advanced:{updateOnContentResize: true}
						});
					});
				";
				break;

			case 'image-picker':
				if ($load_jquery) flexicontent_html::loadJQuery();

				$framework_path = JUri::root(true).$lib_path.'/image-picker';
				$document->addScript($framework_path.'/image-picker.min.js');
				$document->addStyleSheet($framework_path.'/image-picker.css');
				break;

			case 'masonry':
				$framework_path = JUri::root(true).$lib_path.'/masonry';
				$document->addScript($framework_path.'/masonry.pkgd.min.js');
				break;

			case 'google-maps':
				//$force_language = $mode == 'form' ? '&language=' . flexicontent_html::getUserCurrentLang() : '';
				$force_language = '&language=' . flexicontent_html::getUserCurrentLang();
				$apikey = trim($params->get('google_maps_js_api_key', $params->get('apikey')));

				// Key is not empty
				if ($apikey)
				{
					// Get head object
					$head_obj = $document->mergeHeadData(array(1=>1));

					// Unset any previous URL that had no KEY
					unset($head_obj->_links['https://maps.google.com/maps/api/js?libraries=geometry,places' . $force_language]);
				}

				// Key is empty
				else
				{
					// Unset framework-added Flag, to allow retry with other module or field configuration, that may have configured a KEY
					unset($load_frameworks[$framework]);
				}

				// Add map link 
				$document->addScript('https://maps.google.com/maps/api/js?libraries=geometry,places' . ($apikey ? '&key=' . $apikey : '') . $force_language);
				break;

			case 'openstreetmap' :
				$framework_path = JUri::root(true).$lib_path.'/leaflet';
				$document->addStyleSheet($framework_path.'/leaflet.css');
				$document->addStyleSheet($framework_path.'/MarkerCluster.css');
				$document->addStyleSheet($framework_path.'/MarkerCluster.Default.css');
				$document->addScript($framework_path.'/leaflet.js');
				$document->addScript($framework_path.'/leaflet.markercluster.js');
				break;


			case 'pannellum' :
				$document->addStyleSheet('https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css');
				$document->addScript('https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js');
				break;

			case 'select2':
				if ($load_jquery) flexicontent_html::loadJQuery();

				// Load flexi-lib, as it contains the select2 attach function: fc_attachSelect2()
				flexicontent_html::loadFramework('flexi-lib');

				// Load jQuery mouse wheel as this JS can make use of it
				flexicontent_html::loadFramework('mousewheel');

				// Disable select2 JS in mobile devices and instead use chosen JS ...
				$mobileDetector = flexicontent_html::getMobileDetector();
				$isMobile = $mobileDetector->isMobile() || $mobileDetector->isTablet();

				// Load chosen function (if not loaded already) and target specific selector
				if ($isMobile)
				{
					JHtml::_('formbehavior.chosen', '.use_chosen_lib');
				}

				// Regardless if we loaded chosen JS or some other code loaded it, prevent it from ... attaching to elements meant for select2
				$js .= "
				if (typeof jQuery.fn.chosen == 'function') {
					jQuery.fn.chosen_fc = jQuery.fn.chosen;
					jQuery.fn.chosen = function(){
						var args = arguments;
						var result;
						jQuery(this).each(function() {
							if (jQuery(this).hasClass('use_select2_lib') || jQuery(this).hasClass('fc_no_js_attach')) return;
							result = jQuery(this).chosen_fc(args);
						});
						return result;
					};
				}
				";

				$ver = '3.5.4';
				$framework_path = JUri::root(true).$lib_path.'/select2';
				$framework_folder = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'select2';
				$document->addScript($framework_path.'/select2.min.js', array('version' => $ver));
				$document->addScript($framework_path.'/select2.sortable.js', array('version' => $ver));
				$document->addStyleSheet($framework_path.'/select2.css', array('version' => $ver));

				$lang_code = flexicontent_html::getUserCurrentLang();
				if ( $lang_code && $lang_code!='en' )
				{
					// Try language shortcode
					if ( file_exists($framework_folder.DS.'select2_locale_'.$lang_code.'.js') ) {
						$document->addScript($framework_path.'/select2_locale_'.$lang_code.'.js', array('version' => $ver));
					}
					// select2 JS 4.0.0+
					/*if ( file_exists($framework_folder.DS.'select2'.DS.'i18n'.DS.$lang_code.'.js') ) {
						$document->addScript($framework_path.'/select2/i18n/'.$lang_code.'.js', array('version' => $ver));
					}*/
					// Try country language code
					else {
						$country_code = flexicontent_html::getUserCurrentLang($short_tag=false);
						if ( $country_code && file_exists($framework_folder.DS.'select2_locale_'.$country_code.'.js') ) {
							$document->addScript($framework_path.'/select2_locale_'.$country_code.'.js', array('version' => $ver));
						}
						// select2 JS 4.0.0+
						/*if ( $country_code && file_exists($framework_folder.DS.'select2'.DS.'i18n'.DS.$country_code.'.js') ) {
							$document->addScript($framework_path.'/select2/i18n/'.$country_code.'.js', array('version' => $ver));
						}*/
					}
				}

				// Attach select2 JS but skip it in mobiles and use chosen instead
				$js .= "
					jQuery(document).ready(function()
					{
						window.skip_select2_js = ".($isMobile ? 1 : 0).";
						fc_attachSelect2('body');
					});
				";
				break;

			case 'inputmask':
				if ($load_jquery) flexicontent_html::loadJQuery();

				$framework_path = JUri::root(true).$lib_path.'/inputmask';
				$document->addScript($framework_path.'/jquery.inputmask.bundle.min.js');

				// Extra inputmask declarations definitions, e.g. ...
				$js .= "";

				// Attach inputmask to all input fields that have appropriate tag parameters
				$js .= "
					jQuery(document).ready(function(){
						Inputmask.extendAliases({
							decimal: {
								alias: 'numeric',
								autoGroup: true,
								radixPoint: '.',
								groupSeparator: ',',
								clearMaskOnLostFocus: true,
								removeMaskOnSubmit: true,
								unmaskAsNumber: false
							},
							decimal_comma: {
								alias: 'numeric',
								autoGroup: true,
								radixPoint: ',',
								groupSeparator: '.',
								clearMaskOnLostFocus: true,
								removeMaskOnSubmit: true,
								unmaskAsNumber: false
							},
							currency: {
								alias: 'numeric',
								prefix: '$ ',
								groupSeparator: ',',
								autoGroup: true,
								digits: 2,
								digitsOptional: false,
								clearMaskOnLostFocus: true,
								removeMaskOnSubmit: true,
								unmaskAsNumber: false
							},
							currency_euro: {
								alias: 'currency',
								prefix: '\u20ac ',
								groupSeparator: ',',
								autoGroup: true,
								digits: 2,
								digitsOptional: false,
								clearMaskOnLostFocus: false,
								removeMaskOnSubmit: true,
								unmaskAsNumber: false
							},
							percentage_zero_nolimit: {
								alias: 'percentage',
								digits: 2,
								radixPoint: '.',
								autoGroup: true,
								min: 0,
								max: '',
								suffix: ' %',
								allowPlus: false,
								allowMinus: false,
								clearMaskOnLostFocus: true,
								removeMaskOnSubmit: true,
								unmaskAsNumber: false
							},
							percentage_nolimit_nolimit: {
								alias: 'percentage',
								digits: 2,
								radixPoint: '.',
								autoGroup: true,
								min: '',
								max: '',
								suffix: ' %',
								allowPlus: false,
								allowMinus: true,
								clearMaskOnLostFocus: true,
								removeMaskOnSubmit: true,
								unmaskAsNumber: false
							},
							integer: {
								alias: 'numeric',
								digits: 0,
								radixPoint: '',
								clearMaskOnLostFocus: true,
								removeMaskOnSubmit: true,
								unmaskAsNumber: false
							},
							unsigned: {
								alias: 'numeric',
								digits: 0,
								radixPoint: '',
								allowPlus: false,
								allowMinus: false,
								clearMaskOnLostFocus: true,
								removeMaskOnSubmit: true,
								unmaskAsNumber: false
							},
							'mobile': {
								'mask': '9999 999 999',
								'autounmask': true,
								'insertMode': true,
								clearMaskOnLostFocus: true,
								removeMaskOnSubmit: true,
								unmaskAsNumber: false
							}
						});

						jQuery('input.has_inputmask').each(function() {
							jQuery(this).inputmask();
						});
						jQuery('input.inputmask-regex').each(function() {
							jQuery(this).inputmask('Regex');
						});
					});
				";
				break;

			case 'prettyCheckable':
				if ($load_jquery) flexicontent_html::loadJQuery();

				$framework_path = JUri::root(true).$lib_path.'/prettyCheckable';
				$document->addScript($framework_path.'/dev/prettyCheckable.js');
				$document->addStyleSheet($framework_path.'/dist/prettyCheckable.css');
				$js .= "
					jQuery(document).ready(function(){
						jQuery('input.use_prettycheckable').each(function() {
							var elem = jQuery(this);
							var lbl = elem.next('label');
							var lbl_html = elem.next('label').html();
							lbl.remove();
							elem.prettyCheckable({
								color: 'blue',
								label: lbl_html
							});
						});
					});
				";
				break;

			case 'multibox':
			case 'jmultibox':
				if ($load_jquery) flexicontent_html::loadJQuery();

				$framework_path = JUri::root(true).$lib_path.'/jmultibox';

				// Add JS
				$document->addScript($framework_path.'/js/jmultibox.js');
				$document->addScript($framework_path.'/js/jquery.vegas.js');

				// Add CSS
				$document->addStyleSheet($framework_path.'/styles/multibox.css');
				$document->addStyleSheet($framework_path.'/styles/jquery.vegas.css');
				if (substr($_SERVER['HTTP_USER_AGENT'],0,34)=="Mozilla/4.0 (compatible; MSIE 6.0;") {
					$document->addStyleSheet($framework_path.'/styles/multibox-ie6.css');
				}

				// Attach multibox to ... this will be left to the caller so that it will create a multibox object with custom options
				$js .= "";
				break;

			case 'fancybox':
				if ($load_jquery) flexicontent_html::loadJQuery();
				$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/jquery-easing.js');

				$framework_path = JUri::root(true).$lib_path.'/fancybox';

				// Load jQuery mouse wheel as this JS can make use of it
				flexicontent_html::loadFramework('mousewheel');

				// Add fancyBox CSS / JS
				$document->addStyleSheet($framework_path.'/dist/jquery.fancybox.min.css');
				$document->addScript($framework_path.'/dist/jquery.fancybox.min.js');

				// Attach fancybox to all elements having a specific CSS class
				$js .= "
					jQuery(document).ready(function(){
						jQuery('.fancybox').fancybox({
							'openEffect'	: 'elastic',
							'closeEffect'	: 'elastic',
							'openEasing'  : 'easeOutCubic',
							'closeEasing' : 'easeInCubic',
							'idleTime'    : 0
						});
					});
				";
				break;

			case 'galleriffic':
				if ($load_jquery) flexicontent_html::loadJQuery();

				$framework_path = JUri::root(true).$lib_path.'/galleriffic';
				$document->addStyleSheet($framework_path.'/css/basic.css');
				$document->addScript($framework_path.'/js/jquery.galleriffic.js');

				break;

			case 'elastislide':
				if ($load_jquery) flexicontent_html::loadJQuery();

				$framework_path = JUri::root(true).$lib_path.'/elastislide';
				$document->addStyleSheet($framework_path.'/css/gallery.css');

				$document->addScript($framework_path.'/js/jquery.tmpl.min.js');
				$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/jquery-easing.js', array('version' => FLEXI_VHASH));

				$document->addScript($framework_path.'/js/jquery.elastislide.js');
				$document->addScript($framework_path.'/js/gallery.js');
				break;

			case 'photoswipe':
				if ($load_jquery) flexicontent_html::loadJQuery();

				$framework_path = JUri::root(true).$lib_path.'/photoswipe';

				//$document->addStyleSheet($framework_path.'/lib/jquery.mobile/jquery.mobile.css');
				$document->addStyleSheet($framework_path.'/photoswipe.css');

				//$document->addScript($framework_path.'/lib/jquery.mobile/jquery.mobile.js');
				$document->addScript($framework_path.'/lib/simple-inheritance.min.js');
				//$document->addScript($framework_path.'/lib/jquery.animate-enhanced.min.js');
				$document->addScript($framework_path.'/code.photoswipe.min.js');

				$js .= "
					jQuery(document).ready(function() {
						var myPhotoSwipe = jQuery('.photoswipe_fccontainer a').photoSwipe();
					});
				";
				break;

			case 'fcxSlide':
				if ($load_jquery) flexicontent_html::loadJQuery();

				$framework_path = JUri::root(true).$lib_path.'/fcxSlide';
				$document->addScript($framework_path.'/class.fcxSlide.js', array('version' => FLEXI_VHASH));
				$document->addStyleSheet($framework_path.'/fcxSlide.css', array('version' => FLEXI_VHASH));
				//$document->addScript($framework_path.'/class.fcxSlide.packed.js', array('version' => FLEXI_VHASH));
				break;

			case 'imagesLoaded':
				$framework_path = JUri::root(true).$lib_path.'/imagesLoaded';
				$document->addScript($framework_path.'/imagesloaded.pkgd.min.js');
				break;

			case 'zTree':
				if ($load_jquery) flexicontent_html::loadJQuery();

				$framework_path = JUri::root(true).$lib_path.'/zTree';
				$document->addStyleSheet($framework_path.'/css/flexi_ztree.css');
				$document->addStyleSheet($framework_path.'/css/zTreeStyle/zTreeStyle.css');
				$document->addScript($framework_path.'/js/jquery.ztree.all-3.5.min.js');
				//$document->addScript($framework_path.'/js/jquery.ztree.core-3.5.js');
				//$document->addScript($framework_path.'/js/jquery.ztree.excheck-3.5.js');
				//$document->addScript($framework_path.'/js/jquery.ztree.exedit-3.5.js');
				break;

			case 'plupload':
				if ($load_jquery) flexicontent_html::loadJQuery();

				$framework_path = JUri::root(true).$lib_path.'/plupload';
				$framework_folder = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'plupload';
				$document->addScript($framework_path.'/js/plupload.full.min.js');

				if ($mode=='ui') {
					$document->addStyleSheet($framework_path.'/js/jquery.ui.plupload/css/jquery.ui.plupload.css');
					$document->addScript($framework_path.'/js/jquery.ui.plupload/jquery.ui.plupload.min.js');
					//$document->addScript($framework_path.'/js/themeswitcher.js');
				} else {
					$document->addStyleSheet($framework_path.'/js/jquery.plupload.queue/css/jquery.plupload.queue.css');
					$document->addScript($framework_path.'/js/jquery.plupload.queue/jquery.plupload.queue.js');
				}

				$lang_code = flexicontent_html::getUserCurrentLang();
				if ( $lang_code && $lang_code!='en' )
				{
					// Try language shortcode
					if ( file_exists($framework_folder.DS.'js'.DS.'i18n'.DS.$lang_code.'.js') ) {
						$document->addScript($framework_path.'/js/i18n/'.$lang_code.'.js');
					}
					// Try country language code
					else {
						$country_code = flexicontent_html::getUserCurrentLang($short_tag=false);
						$country_code = str_replace('-', '_', $country_code);    // Files for this library use underscore ...
						if ( $country_code && file_exists($framework_folder.DS.'js'.DS.'i18n'.DS.$country_code.'.js') ) {
							$document->addScript($framework_path.'/js/i18n/'.$country_code.'.js');
						}
					}
				}
				// For debugging
				//$document->addScript($framework_path.'/js/moxie.min.js');
				//$document->addScript($framework_path.'/js/plupload.dev.js');
				break;

			case 'nouislider':

				$framework_path = JUri::root(true).$lib_path.'/nouislider';
				$document->addStyleSheet($framework_path.'/nouislider.min.css');
				$document->addScript($framework_path.'/nouislider.min.js');
				break;

			case 'flexi_js_common':

				$site_languages = FLEXIUtilities::getLanguages();
				$default_lang_code = flexicontent_html::getSiteDefaultLang();
				$sef_lang_code = isset($site_languages->{$default_lang_code}) ? $site_languages->{$default_lang_code}->sef : '';

				$needed_vars = array('cid', 'cids', 'cc');
				$FC_URL_VARS = array();

				foreach($needed_vars as $nv)
				{
					!isset($_GET[$nv]) ?: $FC_URL_VARS[$nv] = $_GET[$nv];
				}

				$js .= "
					var fc_sef_lang = '" . $sef_lang_code . "';
					var fc_root_uri = '" . JUri::root(true) . "';
					var fc_base_uri = '" . JUri::base(true) . "';
					var FC_URL_VARS = " . json_encode($FC_URL_VARS) . ";
				";

				break;

			case 'flexi_tmpl_common':
				if ($load_jquery) flexicontent_html::loadJQuery();

				flexicontent_html::loadFramework('flexi_js_common');
				flexicontent_html::loadFramework('select2');  // make sure select2 is loaded

				// Make sure user cookie is set
				$jcookie = $app->input->cookie;
				$fc_uid = $jcookie->get( 'fc_uid', null);
				$hashedUA = JFactory::getUser()->id
					? JUserHelper::getShortHashedUserAgent()
					: 'p';

				if ($fc_uid != $hashedUA)
				{
					$jcookie->set( 'fc_uid', $hashedUA, 0);
				}

				$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/tmpl-common.js', array('version' => FLEXI_VHASH));
				$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/jquery-easing.js', array('version' => FLEXI_VHASH));
				JText::script("FLEXI_APPLYING_FILTERING", true);
				JText::script("FLEXI_TYPE_TO_LIST", true);
				JText::script("FLEXI_TYPE_TO_FILTER", true);
				JText::script("FLEXI_UPDATING_CONTENTS", true);

				break;

			case 'flexi-lib':
				if ($load_jquery) flexicontent_html::loadJQuery();

				flexicontent_html::loadFramework('flexi_js_common');

				// For J3.7.0+ , load polyfills for older IE browsers: < IE11
				if (FLEXI_J37GE)
				{
					JHtml::_('behavior.polyfill', array('event', 'classlist', 'map'), 'lte IE 11');
				}

				$js .= "";

				$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/flexi-lib.js', array('version' => FLEXI_VHASH));
				JText::script("FLEXI_NOT_AN_IMAGE_FILE", true);
				JText::script("FLEXI_IMAGE", true);
				JText::script('FLEXI_LOADING_IMAGES',true);
				JText::script('FLEXI_THUMBNAILS',true);
				JText::script("FLEXI_NO_ITEMS_SELECTED", true);
				JText::script("FLEXI_ARE_YOU_SURE", true);
				JText::script("FLEXI_APPLYING_FILTERING");

				/**
				 * MANAGE FILTERING MESSAGE DISPLAY 
				 */

				// Content Box
				$content_color    = $cparams->get('page_reloading_content_color', 'rgba(0, 0, 0, 1)');
				$content_bg_color = $cparams->get('page_reloading_content_bg_color', 'rgba(255, 255, 255, 1)');

				// Logo ... in Content Box
				$logo_imgsrc   = $cparams->get('page_reloading_logo_imgsrc', '');
				$logo_imgsrc   = $logo_imgsrc ? JUri::root() . $logo_imgsrc : '';
				$logo_opacity  = $cparams->get('page_reloading_logo_opacity', 100);
				$logo_opacity  = number_format(($logo_opacity / 100.0), 2, '.', '');

				// Text Message ... in Content Box
				$mssg_display  = $cparams->get('page_reloading_mssg_display', 1);
				$mssg_text     = $cparams->get('page_reloading_mssg_text', 'FLEXI_APPLYING_FILTERING');

				// Progress Bar ... in Content Box
				$pbar_display  = $cparams->get('page_reloading_pbar_display', 1);
				$pbar_color    = $cparams->get('page_reloading_pbar_color', 'rgba(0, 153, 255, 1)');

				// Overlay Box
				$overlay_bg_color = $cparams->get('page_reloading_overlay_bg_color', 'rgba(0, 0, 0, 0.3)');

				// Get other CSS

				// Create CSS style as an array, we will implode it using '; '
				$styles = (object) array(
					'content_box'  => 'background-color: ' . $content_bg_color . '; color: ' . $content_color . ';' ,
					'logo_box'     => 'opacity: ' . $logo_opacity . ';',
					'mssg_box'     => '',
					'progress_bar' => 'background-color: ' . $pbar_color . ';' ,
					'overlay_box'  => 'background-color: ' . $overlay_bg_color . ';',
				);

				$js .= '
					jQuery( document ).ready(function() {
						jQuery("body").prepend(\'\\
							<div id="fc_filter_form_blocker" style="display: none;">\\
								<div class="fc_blocker_overlay" style="' . $styles->overlay_box . '"></div>\\
								<div class="fc_blocker_content" style="' . $styles->content_box . '">\\
									' . ($logo_imgsrc  ? '<div class="fc_blocker_logo" style="' . $styles->logo_box . '" ><img src="' . $logo_imgsrc . '" alt="' . $mssg_text . '"></div>' : '') . '\\
									' . ($mssg_display ? '<div class="fc_blocker_mssg" style="' . $styles->mssg_box . '" >' . JText::_($mssg_text, true) . '</div>' : '') . '\\
									' . ($pbar_display ? '<div class="fc_blocker_bar"><div style="' . $styles->progress_bar . '"></div></div>' : '') . '\\
								</div>\\
							</div>\\
						\');
					});
				';
				break;

			// Used only by content / configuration forms, that have form elements needing this
			case 'flexi-lib-form':
				if ($load_jquery) flexicontent_html::loadJQuery();

				$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/flexi-lib-form.js', array('version' => FLEXI_VHASH));
				JText::script("FLEXI_EDIT", true);
				JText::script("FLEXI_ADD", true);
				JText::script("FLEXI_NA", true);
				JText::script("FLEXI_PUBLISHED", true);
				JText::script("FLEXI_UNPUBLISHED", true);
				JText::script("FLEXI_EXPIRED", true);
				JText::script("FLEXI_ARCHIVED", true);
				JText::script("FLEXI_TRASHED", true);
				JText::script("FLEXI_REMOVE", true);
				JText::script("FLEXI_ORDER", true);
				JText::script("FLEXI_INDEXED_FIELD_UNUSED_COL_DISABLED", true);
				JText::script("FLEXI_INDEXED_FIELD_VALGRP_COL_DISABLED", true);
				JText::script("FLEXI_INDEXED_FIELD_STATE_COL_DISABLED", true);
				JText::script('FLEXI_REQUIRED',true);
				JText::script('FLEXI_INVALID',true);
				JText::script('FLEXI_AUTO',true);
				JText::script('FLEXI_FIELD_SELECT_AT_LEAST_PROMPT',true);
				JText::script('FLEXI_FIELD_SELECT_UP_TO_PROMPT',true);
				JText::script('FLEXI_FIELD_SELECT_EXACTLY_PROMPT',true);
				break;

			default:
				JFactory::getApplication()->enqueueMessage(__FUNCTION__.' Cannot load unknown Framework: '.$framework, 'error');
				break;
		}

		// Add custom JS & CSS code
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);
		return $_loaded[$framework];
	}


	/**
	 * Escape an HTML string so that it can be used directly by JS source code
	 *
	 * @param 	string 		$text
	 * @param 	int 		$nb
	 * @return 	string
	 * @since 1.5
	 */
	static function encodeHTML($string, $no_quote='s')
	{
		$string = trim(json_encode($string, FLEXI_PHP_54GE ? JSON_UNESCAPED_UNICODE : 0), '"');
		if ($no_quote === 1)
		{
		}
		else if ($no_quote === 2)
		{
			$string = str_replace("\\'", "\\\\\\'", addslashes($string));
		}
		else if ($no_quote === 'd')
		{
			$string = str_replace('\\"', '"', $string);
			$string = str_replace("'", "\\'", $string);
		}

		return $string;
	}

	// DEPRECATED, instead use: encodeHTML()
	static function escapeJsText($string, $no_quote='s')
	{
		return flexicontent_html::encodeHTML($string, $no_quote);
	}


	/**
	 * Trims whitespace from an array of strings
	 *
	 * @param 	string array			$arr_str
	 * @return 	string array
	 * @since 1.5
	 */
	static function arrayTrim($arr_str) {
		if(!is_array($arr_str)) return false;
		foreach($arr_str as $k=>$a) {
			$arr_str[$k] = trim($a);
		}
		return $arr_str;
	}


	// Server-Side validation
	static function dataFilter( $v, $maxlength=0, $validation='string', $check_callable=0 )
	{
		// validation: Strip HTML and cut the text
		if ($validation=='-1') return flexicontent_html::striptagsandcut( $v, $maxlength );

		// validation: via function call
		$v = $maxlength ? substr($v, 0, $maxlength) : $v;
		if ($check_callable)
		{
			if (strpos($validation, '::') !== false && is_callable(explode('::', $validation)))
			{
				return call_user_func(explode('::', $validation), $v);   // A callback class method
			}
			elseif (function_exists($validation))
			{
				return $validation($v);
			}
		}

		// Map integer validation code to custom validation types
		$_map = array('1'=>'safehtml_decode_first', '2'=>'joomla_text_filters', '3'=>'safehtml_allow_encoded');
		$validation = isset($_map[$validation])  ?  $_map[$validation]  :  $validation;

		// Create a safe-HTML or a no-HTML filter
		if ($validation=='safehtml_decode_first' || $validation=='safehtml_allow_encoded')
		{
			$safeHtmlFilter = JFilterInput::getInstance([], [], 1, 1);
		}
		else if ($validation!='joomla_ug_text_filters')
		{
			$noHtmlFilter = JFilterInput::getInstance();
		}

		// Do the filtering
		$validation = strtoupper($validation);
		switch ($validation)
		{
			case 'SAFEHTML_DECODE_FIRST':
				// Allow safe HTML ... but also decode HTML special characters before filtering
				// Decoding allows removal of e.g. &lt;badtag&gt; ... &lt;/badtag&gt;
				$v = $safeHtmlFilter->clean($v, 'string');
				break;

			case 'SAFEHTML_ALLOW_ENCODED':
				// Allow safe HTML ... and allow ANY HTML if encoded, e.g. allows &lt;i&gt; ... &lt;/i&gt;
				$v = $safeHtmlFilter->clean($v, 'html');
				break;

			case 'JOOMLA_TEXT_FILTERS':
				// Filter according to user group Text Filters
				$v = JComponentHelper::filterText($v);
				break;

			case 'ACCESSLEVEL':
				// Filter using known access levels
				if ( ((int) $v) != $v )
				{
					$v = 1;  // Public
					break;
				}
				$v = (int) $v;

				$options = JHtml::_('access.assetgroups');
				$found = false;
				foreach ($options as $o)
				{
					if ($o->value== $v)
					{
						$found = true;
						break;
					}
				}
				if (!$found) $v = 1;
				break;

			case 'URL':
				// This cleans some of the more dangerous characters but leaves special characters that are valid.
				$v = trim($noHtmlFilter->clean($v, 'HTML'));

				// <>" are never valid in a uri see http://www.ietf.org/rfc/rfc1738.txt.
				$v = str_replace(array('<', '>', '"'), '', $v);

				// Convert to Punycode string
				$v = JStringPunycode::urlToPunycode( $v );
				break;

			case 'EMAIL':
				// Use the Joomla mail helper to validate emails
				jimport('joomla.mail.helper');
				if ( !JMailHelper::isEmailAddress($v) ) $v = '';
				break;

			default:
				// Filter using JFilterInput
				$v = $noHtmlFilter->clean($v, $validation);
				break;
		}

		$v = trim($v);
		return $v;
	}


	/**
	 * Strip html tags and cut after x characters, optionally adding a toggler button to show the trimmed text
	 *
	 * @param 	string 		$text
	 * @param 	int 		$nb
	 * @return 	string
	 * @since 1.5
	 */
	static function striptagsandcut( $text, $chars=null, &$uncut_length=0, $options = null)
	{
		$options = $options ?: array(
			'cut_at_word' => false,
			'more_box_id' => '',
			'more_toggler' => 0,
			'more_icon' => 'icon-paragraph-center',
			'more_txt' => '...',
			'modal_title'=>'...',
			'keep_jplugins_code' => false
		);

		// Convert html entities to characters so that they will not be removed ... by strip_tags
		$cleantext = html_entity_decode ($text, ENT_NOQUOTES, 'UTF-8');

		// Add whitespaces at start/end of tags so that words will not be joined,
		//$cleantext = preg_replace('/(<\/[^>]+>((?!\P{L})|(?=[0-9])))|(<[^>\/][^>]*>)/u', ' $1', $cleantext);
		//$cleantext = preg_replace('/(<\/[^>]+>(?![\:|\.|,|:|"|\']))|(<[^>\/][^>]*>)/u', ' $1', $cleantext);

		// Add whitespaces at start/end of BLOCK AND LINE BREAKING TAGS so that words will not be joined,
		$cleantext = preg_replace('/(<|<\/)('.
			'address|article|aside|blockquote|br|canvas|dd|div|dl|dt|'.
			'fieldset|figcaption|figure|footer|form|h1|h2|h3|h4|h5|h6|header|hgroup|hr|li|'.
			'main|nav|noscript|ol|output|p|pre|section|table|tfoot|ul|video'.
			')\b/i',
		' $1$2', $cleantext);

		// Strip javascript
		$cleantext = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $cleantext);

		// Strip html tags
		$cleantext = strip_tags($cleantext);

		// Strip SCRIPT tags already done above
		//$cleantext = preg_replace( '#<script\b[^>]*>(.*?)<\/script>#is', '', $cleantext );

		// Clean Joomla Plugin code
		if ( empty($options['keep_jplugins_code']) )
		{
			$patterns = array();
			$patterns[] = '#\[(.*?)\]#';
			$patterns[] = '#{(.*?)}#';
			$patterns[] = '#&(.*?);#';

			foreach ($patterns as $pattern)
			{
				$cleantext = preg_replace( $pattern, '', $cleantext );
			}
		}

		// Replace multiple spaces, tabs, newlines, etc with a SINGLE whitespace so that text length will be calculated correctly
		$cleantext = preg_replace('/[\p{Z}\s]{2,}/u', ' ', $cleantext);  // Unicode safe whitespace replacing

		// Calculate length according to UTF-8 encoding
		$uncut_length = StringHelper::strlen($cleantext);

		// Cut off the text if required
		if ($chars && $uncut_length > $chars)
		{
			// If not cutting at middle of word, then find closest whitespace, that is previous to the word
			if (!$options['cut_at_word'])
			{
				$chars = StringHelper::strrpos(StringHelper::substr($cleantext, 0, $chars), ' ');
	    }

	    // Cut the text
			$text1 = StringHelper::substr($cleantext, 0, $chars);

			// Add a toggle of the full text
			switch ($options['more_toggler'])
			{
			case 2:
				$box_js = !empty($options['more_box_id'])
					? ' var box = document.getElementById(\'' . $options['more_box_id'] . '\'); '
					: ' var box = this.parentElement.previousElementSibling; ';
				$text2 = (empty($options['more_box_id']) ? '
					<div style="display:none;">
						'. $text . '
					</div>' : '') . '
					<span class="readmore">
						<span style="cursor: pointer;" class="btn btn-mini" onclick="' . $box_js . ' fc_file_props_handle = fc_showAsDialog(box, 800, 600, null, { title: \'' . JText::_($options['modal_title']) . '\'}); return false;">
							<span class="'.$options['more_icon'].'"></span>
							'.JText::_($options['more_txt']).'
						</span>
					</span>';
				break;
			case 1:
				$text2 = '
					<span style="cursor: pointer;" class="btn btn-mini" onclick="var box = this.nextElementSibling; box.style.display = box.style.display == \'none\' ? \'block\' : \'none\'; return false;">
						<span class="'.$options['more_icon'].'"></span>
						'.JText::_($options['more_txt']).'
					</span>
					<span class="fc_cutted_text" style="display: none;">
						' . htmlspecialchars(StringHelper::substr($cleantext, $chars), ENT_COMPAT, 'UTF-8') . '
					</span>';
				break;
			default:
				$text2 = ' ' . JText::_($options['more_txt']);
				break;
			}
		}
		else
		{
			$text1 = $cleantext;
			$text2 = '';
		}

		// Reencode HTML special characters, (but do not encode UTF8 characters)
		// and RETURN cutted text, optionally adding a show all text button
		return htmlspecialchars($text1, ENT_COMPAT, 'UTF-8') . $text2;
	}


	/**
	 * Make image tag from field or extract image from introtext
	 *
	 * @param 	array 		$row
	 * @return 	string
	 * @since 1.5
	 */
	static function extractimagesrc( $row )
	{
		jimport('joomla.filesystem.file');

		$regex = '#<\s*img [^\>]*src\s*=\s*(["\'])(.*?)\1#im';

		if (isset($row->fields['text']->display)) preg_match ($regex, $row->fields['text']->display, $matches);
		if (empty($matches)) preg_match ($regex, $row->introtext, $matches);
		if (empty($matches)) preg_match ($regex, $row->fulltext, $matches);

		$image = !empty($matches) ? $matches[2] : '';

		// Case of local file, check that file exists
		if (!preg_match("#^http|^https|^ftp#i", $image))
		{
			$image = JFile::exists( JPATH_SITE . DS . $image ) ? $image : '';
		}

		return $image;
	}


	/**
	 * Logic to change the state of an item
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	static function setitemstate($controller_obj, $type = 'html', $record_name = 'item')
	{
		$app = JFactory::getApplication();
		$jinput = $app->input;

		$id = $jinput->get('id', 0, 'int');
		$jinput->set('cid', $id);

		$model  = $controller_obj->getModel($record_name);
		$record = $model->getItem($id);
		$user   = JFactory::getUser();
		$state  = $jinput->get('state', 0, 'int');
		$perms  = FlexicontentHelperPerm::getPerm();

		$archive_unsupported = !isset($model->supported_conditions[2]);
		$trash_unsupported   = !isset($model->supported_conditions[-2]);

		// Determine priveleges of the current user on the given item
		/*if (!in_array($record_name, array('item', 'category', 'tag', 'review', 'type', 'field', 'file')))
		{
			die('flexicontent_html::' . __FUNCTION__ . '() , unknown type: ' . $record_name);
		}*/

		// Check if locked, (we catch case that property checked_out does not exist using empty()
		$canCheckin = empty($record->checked_out) || $record->checked_out == $user->id || $user->authorise('core.admin', 'com_checkin');

		// Determine privileges of the current user on the given item
		$has_config     = $user->authorise('core.admin', 'com_flexicontent');
		$has_edit_state = $model->canEditState($record);
		$has_trash      = $has_edit_state;
		$has_archive    = $record_name === 'item'
			? $has_edit_state && $perms->CanArchives
			: $has_edit_state;

		// Clear access if record is locked
		$has_edit_state = $canCheckin && $has_edit_state;
		$has_trash      = $canCheckin && $has_trash;
		$has_archive    = $canCheckin && $has_archive;

		// Clear access if state is not supported
		$has_trash   = $has_trash && empty($trash_unsupported);
		$has_archive = $has_archive && empty($archive_unsupported);

		// Has access to specific state, and doing the specific state change
		$has_n_doing_edit_state = $has_edit_state && (
			in_array($state, array(0,1,-5)) || ($has_config && in_array($state, array(-3,-4)))
		);
		$has_n_doing_delete     = $has_trash && $state === -2;
		$has_n_doing_archive    = $has_archive && $state === 2;

		// Check if user can change of the item to the requested state
		if (!$has_n_doing_edit_state && !$has_n_doing_delete && !$has_n_doing_archive)
		{
			// This must be short text, so not using 'FLEXI_NO_ACCESS_CHANGE_STATE'
			jexit(JText::_('FLEXI_DENIED'));
		}

		// Set new item state (model will also handle cache cleaning)
		if (!$model->setitemstate($id, $state))
		{
			$msg = JText::_('FLEXI_ERROR_SETTING_THE_ITEM_STATE') . ' : ' . $model->getError();

			if ($type === 'json')
			{
				$app->enqueueMessage($msg, 'warning');
				$data = array('error'=>flexicontent_html::get_system_messages_html(), 'html'=>'---', 'title'=>JText::_('Aborted'));
				jexit(json_encode($data));
			}
			else
			{
				jexit($msg);
			}
		}

		// Output new state icon and terminate
		$tmpparams = new JRegistry();
		$tmpparams->set('stateicon_popup', 'basic');
		$stateicon = flexicontent_html::stateicon($state, $tmpparams, $type);

		jexit($stateicon);
	}


	/**
	 * Creates a font icon or image icon for the button according to configuration object
	 *
	 * @param  object  $params   Configuration parameters, typically of component plus some override
	 * @param  array   $config   Configuration for creating the icon
	 * @param  string  $icon     The icon HTML
	 *
	 * @since  3.3.0
	 */
	static function createFcBtnIcon($params, $config, & $icon)
	{
		$show_icons = (int) $params->get('show_icons', 2);
		$use_font   = (int) $params->get('use_font_icons', 1);

		if ($show_icons && $use_font)
		{
			if (self::$icon_classes === null)
			{
				self::load_class_config();
			}

			$icon_class = !empty(self::$icon_classes[$config->iconname])
				? self::$icon_classes[$config->iconname]
				: $config->icondefault;

			if ($show_icons === 2)
			{
				$icon_class .= ' fcIconPadRight';
			}

			$icon = '<i class="' . $icon_class . '"></i>';
		}

		elseif ($show_icons)
		{
			$iconpath = !empty($config->iconpath)
				? $config->iconpath . '/' . $config->iconimage
				: FLEXI_ICONPATH . $config->iconimage;
			$icon = JHtml::image($iconpath, JText::_($config->icontitle), $attribs = '');
		}
	}


	/**
	 * Creates the rss feed button
	 *
	 * @param string $print_link
	 * @param array $params
	 * @since 1.0
	 */
	static function feedbutton($view, &$params, $slug = null, $itemslug = null, $reserved=null, $item = null)
	{
		if ( !$params->get('show_feed_icon', 1) || JFactory::getApplication()->input->getInt('print', 0) ) return;

		$uri    = JUri::getInstance();
		$base  	= $uri->toString( array('scheme', 'host', 'port'));

		//TODO: clean this static stuff (Probs when determining the url directly with subdomains)
		if ($view == 'category')
		{
			$non_sef_link = null;
			flexicontent_html::createCatLink($slug, $non_sef_link);
			$link = $base . JRoute::_($non_sef_link . '&format=feed&type=rss');
			//$link = $base.JRoute::_( 'index.php?view='.$view.'&cid='.$slug.'&format=feed&type=rss', false );
		}
		elseif ($view == FLEXI_ITEMVIEW)
		{
			$link = $base . JRoute::_(FlexicontentHelperRoute::getItemRoute($itemslug, $slug, 0, $item).'&format=feed&type=rss');
			//$link = $base.JRoute::_( 'index.php?view='.$view.'&cid='.$slug.'&id='.$itemslug.'&format=feed&type=rss', false );
		}
		elseif ($view == 'tags')
		{
			$link = $base . JRoute::_(FlexicontentHelperRoute::getTagRoute($itemslug) . '&format=feed&type=rss');
			//$link = $base.JRoute::_( 'index.php?view='.$view.'&id='.$slug.'&format=feed&type=rss', false );
		}
		else
		{
			$link = $base . JRoute::_( 'index.php?view='.$view.'&format=feed&type=rss', false );
		}

		// Workaround for bug due 3rd party plugin calling methods that force JDocument format to be HTML
		if (strpos($link, 'format=feed') === false)
		{
			$link = $link . (strpos($link, '?') !== false ? '&' : '?') . 'format=feed';
		}

		$status = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,left=50,width=\'+(screen.width-100)+\',top=20,height=\'+(screen.height-160)+\',directories=no,location=no';
		$onclick = ' window.open(this.href,\'win2\',\''.$status.'\'); return false; ';

		// Get font icon or image icon for the button
		$config = (object) array(
			'iconname' => 'feed',
			'icondefault' => 'icon-feed',
			'iconimage' => 'livemarks.png',
			'icontitle' => 'FLEXI_FEED',
		);

		$show_icons = (int) $params->get('show_icons', 2);
		$image = '';
		self::createFcBtnIcon($params, $config, $image);

		$overlib = JText::_( 'FLEXI_FEED_TIP' );
		$text = JText::_( 'FLEXI_FEED' );

		$button_classes = 'fc_feedbutton';
		if ($show_icons === 1)
		{
			$caption = '';
			$button_classes .= '';
			$tooltip_placement = 'top';
		}
		else
		{
			$caption = $text;
			if ( !$params->get('btn_grp_dropdown', 0) ) $button_classes .= self::$use_bootstrap ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
			$tooltip_placement = !$params->get('btn_grp_dropdown', 0) ? 'top' : 'left';
		}

		$button_classes .= ' hasTooltip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);

		// $link as set above
		$output	= ' <a href="'.$link.'" class="'.$button_classes.'" data-placement="' . $tooltip_placement . '" title="'.$tooltip_title.'" onclick="'.$onclick.'" >'.$image.$caption.'</a>';
		$output = JText::_('FLEXI_ICON_SEP') . $output . JText::_('FLEXI_ICON_SEP');

		return $output;
	}


	/**
	 * Creates the delete button
	 *
	 * @param array $params
	 * @since 1.0
	 */
	static function deletebutton($item, &$params)
	{
		if ( !$params->get('show_deletebutton', 0) || JFactory::getApplication()->input->getInt('print', 0) ) return;

		$user	= JFactory::getUser();

		// Determine if current user can delete the given item
		$asset = 'com_content.article.' . $item->id;
		$has_delete = $user->authorise('core.delete', $asset) || ($item->created_by == $user->get('id') && $user->authorise('core.delete.own', $asset));

		// Create the delete button only if user can delete the give item
		if (!$has_delete)
		{
			return;
		}

		// Get font icon or image icon for the button
		$config = (object) array(
			'iconname' => 'delete',
			'icondefault' => 'icon-delete',
			'iconimage' => 'delete.png',
			'iconpath' => 'components/com_flexicontent/assets/images',  // Use icon from flexicontent assets folder
			'icontitle' => 'FLEXI_DELETE',
		);

		$show_icons = (int) $params->get('show_icons', 2);
		$image = '';
		self::createFcBtnIcon($params, $config, $image);

		$overlib 	= JText::_( 'FLEXI_DELETE_TIP' );
		$text 		= JText::_( 'FLEXI_DELETE' );

		$button_classes = 'fc_deletebutton';
		if ($show_icons === 1)
		{
			$caption = '';
			$button_classes .= '';
			$tooltip_placement = 'top';
		}
		else
		{
			$caption = $text;
			if ( !$params->get('btn_grp_dropdown', 0) ) $button_classes .= self::$use_bootstrap ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
			$tooltip_placement = !$params->get('btn_grp_dropdown', 0) ? 'top' : 'left';
		}

		$button_classes .= ' hasTooltip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);

		//$Itemid = JFactory::getApplication()->input->get('Itemid', 0, 'int');  // Maintain menu item ? e.g. current category view,
		$Itemid = 0;
		$item_url = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, $Itemid, $item));
		$link = $item_url . (strpos($item_url, '?') !== false ? '&' : '?') . 'task=remove' . '&' . JSession::getFormToken() . '=1';

		$view = JFactory::getApplication()->input->getCm('view', '');
		$link .= '&isitemview=' . ($view == 'item' ? '1' : '0');

		$targetLink = "_self";
		$confirm_text = JText::_('FLEXI_ARE_YOU_SURE_PERMANENT_DELETE', true);

		$output	= ' <a href="'.$link.'" class="'.$button_classes.'" data-placement="' . $tooltip_placement . '" onclick="return confirm(\''.$confirm_text.'\')" target="'.$targetLink.'" title="'.$tooltip_title.'">'.$image.$caption.'</a>';
		$output = JText::_('FLEXI_ICON_SEP') . $output . JText::_('FLEXI_ICON_SEP');

		return $output;
	}


	/**
	 * Creates the CSV export button
	 *
	 * @param array $params
	 * @since 1.0
	 */
	static function csvbutton($view, &$params, $slug = null, $itemslug = null, $reserved=null, $item = null)
	{
		if ( !$params->get('show_csvbutton', 0) || JFactory::getApplication()->input->getInt('print', 0) ) return;
      
      	$user	= JFactory::getUser();

		// Determine if current user can export csv
		$has_export_csv = $user->authorise('core.export', $asset);

		// Create the delete button only if user can delete the give item
		if (!$has_export_csv)
		{
			return;
		}

		$uri    = JUri::getInstance();
		$base  	= $uri->toString( array('scheme', 'host', 'port'));

		$has_export_all_btn = false;

		$memory_limit = ini_get('memory_limit');
		if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches))
		{
			switch($matches[2])
			{
				case 'G': $memory_limit = $matches[1] * 1024 * 1024 *1024; break;
				case 'M': $memory_limit = $matches[1] * 1024 * 1024; break;
				case 'K': $memory_limit = $matches[1] * 1024; break;
				default:  $memory_limit = $memory_limit; break;
			}
		}
		$export_limit = ($memory_limit - (15 * 1024 * 1024)) / (1024 * 50);
		$export_limit = $export_limit > 20000 ? 20000 : $export_limit;


		//TODO: clean this static stuff (Probs when determining the url directly with subdomains)
		if ($view == 'category')
		{
			$has_export_all_btn = true;

			$filters = flexicontent_html::getCatViewFilterVars();
			$start = JFactory::getApplication()->input->get('start', '', 'int');

			$non_sef_link = null;
			flexicontent_html::createCatLink($slug, $non_sef_link);

			// Current page
			$link = $base . JRoute::_(
				$non_sef_link
				. '&format=csv'
				. $filters
				. ($start ? "&start=".$start : '')
			);

			// All pages
			$link_all_pages = $base . JRoute::_(
				$non_sef_link
				. '&format=csv'
				. $filters
				. '&start=0&limit=' . $export_limit
			);

			//$link = $base.JRoute::_( 'index.php?view='.$view.'&cid='.$slug.'&format=csv', false );
		} elseif ($view == FLEXI_ITEMVIEW) {
			$link = $base . JRoute::_(FlexicontentHelperRoute::getItemRoute($itemslug, $slug, 0, $item).'&format=csv');
			//$link = $base.JRoute::_( 'index.php?view='.$view.'&cid='.$slug.'&id='.$itemslug.'&format=csv', false );
		} elseif ($view == 'tags') {
			$link = $base . JRoute::_(FlexicontentHelperRoute::getTagRoute($itemslug).'&format=csv');
			//$link = $base.JRoute::_( 'index.php?view='.$view.'&id='.$slug.'&format=csv', false );
		} else {
			$link = $base . JRoute::_( 'index.php?view='.$view.'&format=csv', false );
		}

		$status = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,left=50,width=\'+(screen.width-100)+\',top=20,height=\'+(screen.height-160)+\',directories=no,location=no';
		$onclick = ' window.open(this.href,\'win2\',\''.$status.'\'); return false; ';

		// Get font icon or image icon for the button
		$config = (object) array(
			'iconname' => 'csv',
			'icondefault' => 'icon-download',
			'iconimage' => 'csv.png',
			'icontitle' => 'FLEXI_CSV_EXPORT',
		);

		$show_icons = (int) $params->get('show_icons', 2);
		$image = '';
		self::createFcBtnIcon($params, $config, $image);

		$overlib = JText::_( 'FLEXI_CSV_TIP_THIS_PAGE' );
		$text = JText::_( 'FLEXI_CSV' );

		$button_classes = 'fc_csvbutton';
		if ($show_icons === 1)
		{
			$caption = '';
			$button_classes .= '';
			$tooltip_placement = 'top';
		}
		else
		{
			$caption = $text;
			if ( !$params->get('btn_grp_dropdown', 0) ) $button_classes .= self::$use_bootstrap ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
			$tooltip_placement = !$params->get('btn_grp_dropdown', 0) ? 'top' : 'left';
		}

		$button_classes .= ' hasTooltip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);

		// Export All
		$output .= JText::_('FLEXI_ICON_SEP')
			. ' <a href="'.$link.'" class="'.$button_classes.'" data-placement="' . $tooltip_placement . '" title="'.$tooltip_title.'" onclick="'.$onclick.'" >'
				. $image . ($has_export_all_btn? '<span class="icon-file"></span> ' : '')
				. $caption // . ($has_export_all_btn? ' (Current page)' : '')
				. '</a>'
			. JText::_('FLEXI_ICON_SEP');

		// Export current page
		if (!empty($link_all_pages))
		{
			$overlib = JText::_( 'FLEXI_CSV_TIP' ) . ($has_export_all_btn ? '<br>' . JText::sprintf( 'FLEXI_CSV_TIP_EXPORT_LIMIT',  $export_limit) : '');
			$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);

			$output .= JText::_('FLEXI_ICON_SEP')
				. ' <a href="'.$link_all_pages.'" class="'.$button_classes.'" data-placement="' . $tooltip_placement . '" title="'.$tooltip_title.'" onclick="'.$onclick.'" >'
					. $image . ($has_export_all_btn? '<span class="icon-book"></span> ' : '')
					. $caption // . ($has_export_all_btn? ' (All pages)' : '')
					. '</a>'
				. JText::_('FLEXI_ICON_SEP');
		}

		return $output;
	}


	/**
	 * Creates the print button
	 *
	 * @param string $print_link
	 * @param array $params
	 * @since 1.0
	 */
	static function printbutton($print_link, &$params)
	{
		if ( !$params->get('show_print_icon') || JFactory::getApplication()->input->getInt('print', 0) ) return;

		$status = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,left=50,width=\'+(screen.width-100)+\',top=20,height=\'+(screen.height-160)+\',directories=no,location=no';
		$onclick = ' window.open(this.href,\'win2\',\''.$status.'\'); return false; ';
		$link = JRoute::_($print_link);

		// Get font icon or image icon for the button
		$config = (object) array(
			'iconname' => 'print',
			'icondefault' => 'icon-print',
			'iconimage' => 'printButton.png',
			'icontitle' => 'FLEXI_PRINT',
		);

		$show_icons = (int) $params->get('show_icons', 2);
		$image = '';
		self::createFcBtnIcon($params, $config, $image);

		$overlib = JText::_( 'FLEXI_PRINT_TIP' );
		$text = JText::_( 'FLEXI_PRINT' );

		$button_classes = 'fc_printbutton';

		if ($show_icons === 1)
		{
			$caption = '';
			$button_classes .= '';
			$tooltip_placement = 'top';
		}
		else
		{
			$caption = $text;
			if ( !$params->get('btn_grp_dropdown', 0) ) $button_classes .= self::$use_bootstrap ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
			$tooltip_placement = !$params->get('btn_grp_dropdown', 0) ? 'top' : 'left';
		}

		$button_classes .= ' hasTooltip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);

		// $link as set above
		$output	= ' <a href="'.$link.'" class="'.$button_classes.'" data-placement="' . $tooltip_placement . '" title="'.$tooltip_title.'" onclick="'.$onclick.'" rel="nofollow">'.$image.$caption.'</a>';
		$output = JText::_('FLEXI_ICON_SEP') . $output . JText::_('FLEXI_ICON_SEP');

		return $output;
	}


	/**
	 * Creates the email button
	 *
	 * @param string $print_link
	 * @param array $params
	 * @since 1.0
	 */
	static function mailbutton($view, &$params, $slug = null, $itemslug = null, $reserved=null, $item = null)
	{
		static $initialize = null;
		static $uri, $base;

		if ( !$params->get('show_email_icon') || JFactory::getApplication()->input->getInt('print', 0) ) return;

		if ($initialize === null) {
			if (file_exists ( JPATH_SITE.DS.'components'.DS.'com_mailto'.DS.'helpers'.DS.'mailto.php' )) {
				require_once(JPATH_SITE.DS.'components'.DS.'com_mailto'.DS.'helpers'.DS.'mailto.php');
				$uri  = JUri::getInstance();
				$base = $uri->toString( array('scheme', 'host', 'port'));
				$initialize = true;
			} else {
				$initialize = false;
			}
		}
		if ( $initialize === false ) return;

		//TODO: clean this static stuff (Probs when determining the url directly with subdomains)
		if($view == 'category') {
			$non_sef_link = null;
			flexicontent_html::createCatLink($slug, $non_sef_link);
			$link = $base . JRoute::_($non_sef_link);
			//$link = $base . JRoute::_( 'index.php?view='.$view.'&cid='.$slug, false );
		} elseif($view == FLEXI_ITEMVIEW) {
			$link = $base . JRoute::_(FlexicontentHelperRoute::getItemRoute($itemslug, $slug, 0, $item));
			//$link = $base . JRoute::_( 'index.php?view='.$view.'&cid='.$slug.'&id='.$itemslug, false );
		} elseif($view == 'tags') {
			$link = $base . JRoute::_(FlexicontentHelperRoute::getTagRoute($itemslug));
			//$link = $base . JRoute::_( 'index.php?view='.$view.'&id='.$slug, false );
		} else {
			$link = $base . JRoute::_( 'index.php?view='.$view, false );
		}

		$mail_to_url = JRoute::_('index.php?option=com_mailto&tmpl=component&link='.MailToHelper::addLink($link));$status = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,left=50,width=\'+(screen.width-100)+\',top=20,height=\'+(screen.height-160)+\',directories=no,location=no';
		$status = 'left=50,width=\'+((screen.width-100) > 800 ? 800 : (screen.width-100))+\',top=20,height=\'+((screen.width-160) > 800 ? 800 : (screen.width-160))+\',menubar=yes,resizable=yes';
		$onclick = ' window.open(this.href,\'win2\',\''.$status.'\'); return false; ';

		// Get font icon or image icon for the button
		$config = (object) array(
			'iconname' => 'mail',
			'icondefault' => 'icon-envelope',
			'iconimage' => 'emailButton.png',
			'icontitle' => 'FLEXI_EMAIL',
		);

		$show_icons = (int) $params->get('show_icons', 2);
		$image = '';
		self::createFcBtnIcon($params, $config, $image);

		$overlib = JText::_( 'FLEXI_EMAIL_TIP' );
		$text = JText::_( 'FLEXI_EMAIL' );

		$button_classes = 'fc_mailbutton';

		if ($show_icons === 1)
		{
			$caption = '';
			$button_classes .= '';
			$tooltip_placement = 'top';
		}
		else
		{
			$caption = $text;
			if ( !$params->get('btn_grp_dropdown', 0) ) $button_classes .= self::$use_bootstrap ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
			$tooltip_placement = !$params->get('btn_grp_dropdown', 0) ? 'top' : 'left';
		}

		$button_classes .= ' hasTooltip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);

		// emailed link was set above
		$output	= ' <a href="'.$mail_to_url.'" class="'.$button_classes.'" data-placement="' . $tooltip_placement . '" title="'.$tooltip_title.'" onclick="'.$onclick.'" rel="nofollow">'.$image.$caption.'</a>';
		$output = JText::_('FLEXI_ICON_SEP') . $output . JText::_('FLEXI_ICON_SEP');

		return $output;
	}


	/**
	 * Creates the pdf button
	 *
	 * @param int $id
	 * @param array $params
	 * @since 1.0
	 */
	static function pdfbutton($item, &$params)
	{
		if ( FLEXI_J16GE || !$params->get('show_pdf_icon') || JFactory::getApplication()->input->getInt('print', 0) ) return;

		// Get font icon or image icon for the button
		$config = (object) array(
			'iconname' => 'pdf',
			'icondefault' => 'icon-book',
			'iconimage' => 'pdf_button.png',
			'icontitle' => 'FLEXI_CREATE_PDF',
		);

		$show_icons = (int) $params->get('show_icons', 2);
		$image = null;
		self::createFcBtnIcon($params, $config, $image);

		$overlib = JText::_( 'FLEXI_CREATE_PDF_TIP' );
		$text = JText::_( 'FLEXI_CREATE_PDF' );

		$button_classes = 'fc_pdfbutton';

		if ($show_icons === 1)
		{
			$caption = '';
			$button_classes .= '';
			$tooltip_placement = 'top';
		}
		else
		{
			$caption = $text;
			if ( !$params->get('btn_grp_dropdown', 0) ) $button_classes .= self::$use_bootstrap ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
			$tooltip_placement = !$params->get('btn_grp_dropdown', 0) ? 'top' : 'left';
		}

		$button_classes .= ' hasTooltip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);

		$link 	= JRoute::_('index.php?view='.FLEXI_ITEMVIEW.'&cid='.$item->categoryslug.'&id='.$item->slug.'&format=pdf');
		$output	= ' <a href="'.$link.'" class="'.$button_classes.'" data-placement="' . $tooltip_placement . '" title="'.$tooltip_title.'">'.$image.$caption.'</a>';
		$output = JText::_('FLEXI_ICON_SEP') . $output . JText::_('FLEXI_ICON_SEP');

		return $output;
	}


	/**
	 * Creates the state selector button
	 *
	 * @param int $id
	 * @param array $params
	 * @since 1.0
	 */
	static function statebutton($record, $params = null, $config = null)
	{
		$config = $config ?: (object) array(
			'controller'     => 'items',
			'record_name'    => 'item',
			'state_propname' => 'state',
			'addToggler'     => true,
			'tipPlacement'   => null,
			'class'          => null,
			'locked'         => false,
		);

		$user    = JFactory::getUser();
		$isAdmin = JFactory::getApplication()->isClient('administrator');
		$isPrint = JFactory::getApplication()->input->getInt('print', 0);

		// Check if state icon should not be shown (note: parameters are usually NULL in backend)
		if (!$isAdmin && ($params && !$params->get('show_state_icon', 1) || $isPrint))
		{
			return;
		}

		$recordClass = 'FlexicontentModel' . ucfirst($config->record_name);

		// Check if already loaded
		if (!class_exists($recordClass))
		{
			// Prefer frontend record model if it exists
			!$isAdmin && file_exists(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.$config->record_name.'.php')
				? JLoader::register($recordClass, JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.$config->record_name.'.php')
				: JLoader::register($recordClass, JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.$config->record_name.'.php');
		}

		try
		{
			$model = new $recordClass();
		}
		catch (Exception $e)
		{
			die('flexicontent_html::' . __FUNCTION__ . '() , unknown type: ' . $record_name);
		}

		$archive_unsupported = !isset($model->supported_conditions[2]);
		$trash_unsupported   = !isset($model->supported_conditions[-2]);

		$refresh_on_success  = in_array($config->record_name, array('category')) ? 'true' : 'false';

		static $state_names_added = null;
		if (!$state_names_added)
		{
			$state_names_added = true;
			$state_names_js = array();

			foreach ($model->supported_conditions as $state_id => $state_name)
			{
				JText::script($state_name, true);
				$state_names_js[] = "'" . $state_id . "' : '" . $state_name . "'";
			}

			JFactory::getDocument()->addScriptDeclaration('
				var _fc_state_titles = { ' . implode(' , ', $state_names_js) . '};
			');
		}


		/**
		 * Calculate Edit / Edit state if not already calculated, some record types
		 * do not have atomic assets, for them we will use 'canManage' permission
		 */

		$canArchives = $config->record_name == 'item' ? FlexicontentHelperPerm::getPerm()->CanArchives : null;

		$record->canCheckin = isset($record->canCheckin)
			? $record->canCheckin
			: empty($record->checked_out) || $record->checked_out == $user->id || $user->authorise('core.admin', 'com_checkin');

		$record->canEditState = isset($record->canEditState) ? $record->canEditState : $model->canEditState($record);
		$record->canDelete    = isset($record->canDelete) ? $record->canDelete : $model->canDelete($record);

		// Clear access if record is locked
		$has_edit_state = $record->canCheckin && $record->canEditState;
		$has_delete     = $record->canCheckin && $record->canDelete;
		$has_archive    = $record->canCheckin && $record->canEditState && ($canArchives || $canArchives === null);

		// Clear access if state is not supported
		$has_delete  = $has_delete && empty($trash_unsupported);
		$has_archive = $has_archive && empty($archive_unsupported);

		$canChangeState = ($has_edit_state || $has_delete || $has_archive) && !$config->locked;

		// Some string and flags
		$nullDate       = JFactory::getDbo()->getNullDate();
		$img_path       = JUri::root(true) . '/components/com_flexicontent/assets/images/';
		$use_font_icons = $isAdmin || ($params && $params->get('use_font_icons', 1));


		/**
		 * Display state toggler if it can do any of state change
		 */

		static $js_and_css_added = false;

		if (!$js_and_css_added && $canChangeState && $config->addToggler)
		{
			// File exists both in frontend & backend (and is different), so we will use 'base' method and not 'root'
			JText::script('FLEXI_ACTION', true);
			if ($config->record_name == 'item')
			{
				JText::script('FLEXI_SET_STATE_AS_IN_PROGRESS', true);
				JText::script('FLEXI_SET_STATE_AS_PENDING', true);
				JText::script('FLEXI_SET_STATE_AS_TO_WRITE', true);
			}
			JText::script('FLEXI_SET_STATE_TO', true);
			JText::script('FLEXI_PUBLISH_THIS_ITEM', true);
			JText::script('FLEXI_UNPUBLISH_THIS_ITEM', true);
			JText::script('FLEXI_ARCHIVE_THIS_ITEM', true);
			JText::script('FLEXI_TRASH_THIS_ITEM', true);

			flexicontent_html::loadFramework('flexi_tmpl_common');

			$doc = JFactory::getDocument();
			$doc->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/stateselector.js', array('version' => FLEXI_VHASH));
			$js = '
				var fc_statehandler_singleton = new fc_statehandler({
					task: ' . json_encode($isAdmin ? $config->controller . '.setitemstate' : 'setitemstate') . ',
					img_path: ' . json_encode($img_path) . ',
					font_icons: ' . ($use_font_icons ? 'true' : 'false') . ',
					refresh_on_success: ' . $refresh_on_success . '
				});
			';
			$doc->addScriptDeclaration($js);
			$js_and_css_added = true;
		}

		static $state_names = null;
		static $state_descrs = null;
		static $state_imgs = null;
		static $font_icons = null;
		static $tooltip_class = null;
		static $state_tips = null;
		static $button_classes = null;
		static $jtext = array();
		static $icon_params = null;

		if (!$state_names)
		{
			$state_names = array(
				 1  => JText::_('FLEXI_PUBLISHED'),
				-5  => JText::_('FLEXI_IN_PROGRESS'),
				 0  => JText::_('FLEXI_UNPUBLISHED'),
				-3  => JText::_('FLEXI_PENDING'),
				-4  => JText::_('FLEXI_TO_WRITE'),
				 2  => JText::_('FLEXI_ARCHIVED'),
				-2  => JText::_('FLEXI_TRASHED'),
				'u' => JText::_('FLEXI_UNKNOWN'),
			);
			$state_descrs = array(
				 1  => JText::_('FLEXI_PUBLISH_THIS_ITEM'),
				-5  => JText::_('FLEXI_SET_STATE_AS_IN_PROGRESS'),
				 0  => JText::_('FLEXI_UNPUBLISH_THIS_ITEM'),
				-3  => JText::_('FLEXI_SET_STATE_AS_PENDING'),
				-4  => JText::_('FLEXI_SET_STATE_AS_TO_WRITE'),
				 2  => JText::_('FLEXI_ARCHIVE_THIS_ITEM'),
				-2  => JText::_('FLEXI_TRASH_THIS_ITEM'),
				'u' => JText::_('FLEXI_UNKNOWN'),
			);
			$state_imgs = array(
				 1  => 'accept.png',
				-5  => 'publish_g.png',
				 0  => 'publish_x.png',
				-3  => 'publish_r.png',
				-4  => 'publish_y.png',
				 2  => 'archive.png',
				-2  => 'trash.png',
				'u' => 'unknown.png',
			);
			$font_icons = array(
				 1  => 'publish',
				-5  => 'checkmark-2',
				 0  => 'unpublish',
				-3  => 'question',
				-4  => 'pencil-2',
				 2  => 'archive',
				-2  => 'trash',
				'u' => 'question-2',
			);

			$tooltip_class = ' hasTooltip';
			$state_tips = array();
			$title_header = '';//JText::_( 'FLEXI_ACTION' );

			foreach ($state_names as $state_id => $i)
			{
				$state_tips[$state_id] = flexicontent_html::getToolTip($title_header, $state_descrs[$state_id], 0, 1);
			}

			$button_classes = 'fc_statebutton';

			if ($config->class)
			{
				$button_classes .= ' ' . $config->class;
			}
			elseif (!$params || !$params->get('btn_grp_dropdown', 0))
			{
				$button_classes .= self::$use_bootstrap ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
			}
			else
			{
				$button_classes .= ' btn';
			}

			$jtext['icon_sep']         = JText::_('FLEXI_ICON_SEP');
			$jtext['start_immediately']= JText::_('FLEXI_START_IMMEDIATELY');
			$jtext['start']            = JText::_('FLEXI_START');
			$jtext['finish_no_expiry'] = JText::_('FLEXI_FINISH_NO_EXPIRY');
			$jtext['finish']           = JText::_('FLEXI_FINISH');
			$jtext['change_state']     = JText::_('FLEXI_CLICK_TO_CHANGE_STATE');
			$jtext['action']           = JText::_('FLEXI_ACTION');

			$icon_params = new JRegistry();
			$icon_params->set('stateicon_popup', 'none');
			$icon_params->set('use_font_icons', $use_font_icons);
		}

		// Create state icon
		$state = $record->{$config->state_propname};

		if (!isset($state_names[$state]))
		{
			$state = 'u';
		}

		$state_text = !empty($model->supported_conditions[$state])
			? JText::_($model->supported_conditions[$state])
			: null;
		$stateicon = flexicontent_html::stateicon($state, $icon_params, 'html', $state_text, $record, $show_status = 2);

		$tz_string = JFactory::getApplication()->getCfg('offset');
		$tz = new DateTimeZone( $tz_string );
		$tz_offset = $tz->getOffset(new JDate()) / 3600;

		// Create publish information
		$publish_info = array();

		if (isset($record->publish_up))
		{
			if ($record->publish_up)
			{
				$publish_up = JFactory::getDate($record->publish_up);
				$publish_up->setTimezone($tz);
			}
			$publish_info[] = !$record->publish_up || $record->publish_up == $nullDate
				? $jtext['start_immediately']
				: $jtext['start'] .": ". JHtml::_('date', $publish_up->toSql(), 'Y-m-d H:i:s');
		}

		if (isset($record->publish_down))
		{
			if ($record->publish_down)
			{
				$publish_down = JFactory::getDate($record->publish_down);
				$publish_down->setTimezone($tz);
			}
			$publish_info[] = !$record->publish_down || $record->publish_down == $nullDate
				? $jtext['finish_no_expiry']
				: $jtext['finish'] .": ". JHtml::_('date', $publish_down->toSql(), 'Y-m-d H:i:s');
		}


		// Create the state selector button and return it
		if ($canChangeState && $config->addToggler)
		{
			// Only add user's permitted states on the current item
			if ($has_edit_state)
			{
				$state_ids = $config->record_name === 'item'
					? array(1, -5, 0, -3, -4)
					: array(1, 0);
			}

			if ($has_archive)
			{
				$state_ids[] = 2;
			}

			if ($has_delete)
			{
				$state_ids[] = -2;
			}

			if ($config->tipPlacement)
			{
				$tooltip_placement = $config->tipPlacement !== 'top' ? $config->tipPlacement : '';
			}
			elseif (!$params || !$params->get('show_icons', 2))
			{
				$tooltip_placement = 'top';
			}
			else
			{
				$tooltip_placement = !$params->get('btn_grp_dropdown', 0) ? 'top' : 'left';
			}

			$state_js_ids = array();
			$state_js_titles = array();

			foreach ($state_ids as $i => $state_id)
			{
				$state_js_ids[] = array('i' => $state_id);

				if (!empty($model->supported_conditions[$state_id]))
				{
					//$state_js_titles[$state_id] = JText::_($model->supported_conditions[$state_id]);
				}
			}

			$state_js_ids_list    = count($state_js_ids) ? ' data-st="' . htmlspecialchars(json_encode($state_js_ids), ENT_COMPAT, 'UTF-8') . '" ' : '';
			$state_js_titles_list = count($state_js_titles) ? ' data-tt="' . htmlspecialchars(json_encode($state_js_titles), ENT_COMPAT, 'UTF-8') . '" ' : '';

			$tooltip_title = flexicontent_html::getToolTip(
				$state_text ?: JText::_('FLEXI_PUBLISH_INFORMATION'),
				' &nbsp; ' . implode("\n<br> &nbsp; \n", $publish_info) . '<br>' . $jtext['change_state'],
				0, 1
			);

			$output = '
			<div class="statetoggler ' . $button_classes . ' ' . $tooltip_class . '" ' . ($tooltip_placement ? ' data-placement="' . $tooltip_placement . '"' : '') . ' title="' . $tooltip_title . '" onclick="fc_statehandler_singleton.toggleSelector(this)">
				<div class="statetoggler_inner">
					<div id="row' . $record->id . '" class="stateopener ntxt">
						' . $stateicon . '
					</div>
					<div class="options" data-id="' . $record->id . '" ' . $state_js_ids_list . ' ' . $state_js_titles_list . '></div>
				</div>
			</div>';
		}

		// Backend, possibly with state selector disabled
		elseif ($isAdmin)
		{
			if ($canChangeState)
			{
				$publish_info[] = '<br>' . JText::_('FLEXI_STATE_CHANGER_DISABLED');
			}

			$tooltip_title = flexicontent_html::getToolTip(JText::_( 'FLEXI_PUBLISH_INFORMATION' ), implode("\n<br>\n", $publish_info), 0);
			$output = '
				<div id="row' . $record->id . '" class="statetoggler_disabled ' . $config->class . '">
					<span class="disabled ' . $tooltip_class . '" title="' . $tooltip_title . '">
						' . $stateicon . '
					</span>
				</div>';
			$output	= $jtext['icon_sep'] .$output. $jtext['icon_sep'];
		}

		// Frontend with no permissions to edit / delete / archive
		else
		{
			$output = '';
		}

		return $output;
	}


	/**
	 * Creates the approval button
	 *
	 * @param int $id
	 * @param array $params
	 * @since 1.0
	 */
	static function approvalbutton($item, &$params)
	{
		if ( JFactory::getApplication()->input->getInt('print', 0) ) return;

		static $user = null, $requestApproval = null;
		if ($user === null) {
			$user	= JFactory::getUser();
			$requestApproval = $user->authorise('flexicontent.requestapproval',	'com_flexicontent');
		}

		// Skip items not in draft state
		if ( $item->state != -4 )  return;

		// Skip not-owned items, unless having privilege to send approval request for any item
		if ( !$requestApproval && $item->created_by != $user->get('id') )  return;

		// Determine if current user can edit state of the given item
		$asset = 'com_content.article.' . $item->id;
		$has_edit_state = $user->authorise('core.edit.state', $asset) || ($item->created_by == $user->get('id') && $user->authorise('core.edit.state.own', $asset));

		// Create the approval button only if user cannot edit the item (**note check at top of this method)
		if ($has_edit_state)
		{
			return;
		}

		// Get font icon or image icon for the button
		$config = (object) array(
			'iconname' => 'approval',
			'icondefault' => 'icon-key',
			'iconimage' => 'key_add.png',
			'iconpath' => 'components/com_flexicontent/assets/images',  // Use icon from flexicontent assets folder
			'icontitle' => 'FLEXI_APPROVAL_REQUEST',
		);

		$show_icons = (int) $params->get('show_icons', 2);
		$image = null;
		self::createFcBtnIcon($params, $config, $image);

		$overlib 	= JText::_( 'FLEXI_APPROVAL_REQUEST_INFO' );
		$text 		= JText::_( 'FLEXI_APPROVAL_REQUEST' );

		$button_classes = 'fc_approvalbutton';

		if ($show_icons === 1)
		{
			$caption = '';
			$button_classes .= '';
			$tooltip_placement = 'top';
		}
		else
		{
			$caption = $text;
			if ( !$params->get('btn_grp_dropdown', 0) ) $button_classes .= self::$use_bootstrap ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
			$tooltip_placement = !$params->get('btn_grp_dropdown', 0) ? 'top' : 'left';
		}

		$button_classes .= ' hasTooltip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);

		$Itemid = 0;
		$item_url = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, $Itemid, $item));
		$link = $item_url . (strpos($item_url, '?') !== false ? '&' : '?') . 'task=approval' . '&' . JSession::getFormToken() . '=1';

		$targetLink = "_self";
		$confirm_text = JText::_('FLEXI_CONFIRM_SEND_APPROVAL', true) . "\n" . ' (' . $overlib . ')';

		$output	= ' <a href="'.$link.'" class="'.$button_classes.'" data-placement="' . $tooltip_placement . '" onclick="return confirm(\''.$confirm_text.'\')" target="'.$targetLink.'" title="'.$tooltip_title.'">'.$image.$caption.'</a>';
		$output = JText::_('FLEXI_ICON_SEP') . $output . JText::_('FLEXI_ICON_SEP');

		return $output;
	}


	/**
	 * Creates the edit button
	 *
	 * @param int $id
	 * @param array $params
	 * @since 1.0
	 */
	static function editbutton($item, &$params)
	{
		if ( !$params->get('show_editbutton', 1) || JFactory::getApplication()->input->getInt('print', 0) ) return;

		$app  = JFactory::getApplication();
		$user	= JFactory::getUser();
		$tmpl = $app->input->getCmd('tmpl');

		// Determine if current user can edit the given item
		$asset = 'com_content.article.' . $item->id;
		$has_edit_state = $user->authorise('core.edit', $asset) || ($item->created_by == $user->get('id') && $user->authorise('core.edit.own', $asset));

		// Create the edit button only if user can edit the give item
		if (!$has_edit_state)
		{
			return;
		}

		// Get font icon or image icon for the button
		$config = (object) array(
			'iconname' => 'edit',
			'icondefault' => 'icon-pencil',
			'iconimage' => 'edit.png',
			'icontitle' => 'FLEXI_EDIT',
		);

		$show_icons = (int) $params->get('show_icons', 2);
		$image = null;
		self::createFcBtnIcon($params, $config, $image);

		$overlib 	= JText::_( 'FLEXI_EDIT_TIP' );
		$text 		= JText::_( 'FLEXI_EDIT' );

		$button_classes = 'fc_editbutton';

		if ($show_icons === 1)
		{
			$caption = '';
			$button_classes .= '';
			$tooltip_placement = 'top';
		}
		else
		{
			$caption = $text;
			if ( !$params->get('btn_grp_dropdown', 0) ) $button_classes .= self::$use_bootstrap ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
			$tooltip_placement = !$params->get('btn_grp_dropdown', 0) ? 'top' : 'left';
		}

		$button_classes .= ' hasTooltip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);

		if ((int) $params->get('show_editbutton', 1) === 1)
		{
			//$Itemid = JFactory::getApplication()->input->get('Itemid', 0, 'int');  // Maintain menu item ? e.g. current category view,
			$Itemid = 0;
			$item_url = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, $Itemid, $item));
			$link = $item_url . (strpos($item_url, '?') !== false ? '&' : '?') . 'task=edit' . ($tmpl ? '&tmpl=' . $tmpl : '');
			$targetLink = "_self";
		}
		elseif ((int) $params->get('show_editbutton', 1) === 2)
		{
			$link = JUri::base(true).'/administrator/index.php?option=com_flexicontent&task=items.edit&cid[]='.$item->id;
			$targetLink = "_blank";
		}

		$output	= ' <a href="'.$link.'" class="'.$button_classes.'" data-placement="' . $tooltip_placement . '" target="'.$targetLink.'" title="'.$tooltip_title.'">'.$image.$caption.'</a>';
		$output = JText::_('FLEXI_ICON_SEP') . $output . JText::_('FLEXI_ICON_SEP');

		return $output;
	}


	/**
	 * Creates the add button
	 *
	 * @param array $params
	 * @since 1.0
	 */
	static function addbutton(&$params, &$submit_cat = null, $menu_itemid = 0, $btn_text = '', $auto_relations = false, $ignore_unauthorized = null)
	{
		if ( !$params->get('show_addbutton', 1) || JFactory::getApplication()->input->getInt('print', 0) ) return;

		$app = JFactory::getApplication();
		$user	= JFactory::getUser();

		// If not given get from component parameters
		if ($ignore_unauthorized === null && !empty($submit_cat->parameters) && is_object($submit_cat->parameters))
		{
			$ignore_unauthorized = $submit_cat->parameters->get('nonauth_addbutton', 0);
		}

		// Convert to true / false
		$ignore_unauthorized = !$user->id
			? (int) $ignore_unauthorized === 2
			: (int) $ignore_unauthorized > 0;

		/**
		 * The 'Add' button will appear to logged users only ... unless
		 * we have a PERFORMANCE-SAFE option for showing it
		 *  - either unauthorized guest users are allowed (= aka no need to calculate 'CREATE' ACL)
		 *  - or a specific category ID was given (= aka we need to calculate 'CREATE' ACL for given category only)
		 */
		if (!$user->id)
		{
			$submit_catid_given = $submit_cat && $submit_cat->id;

			if (!$ignore_unauthorized && !$submit_catid_given)
			{
				return '';
			}
		}


		// IF not auto-relation given ... then check if current view / layout can use ADD button
		if (!$auto_relations)
		{
			$view   = $app->input->get('view', '', 'CMD');
			$layout = $app->input->get('layout', '', 'CMD');

			if ($view !== 'category' || $layout === 'author' || $layout === 'favs')
			{
				return '';
			}
		}


		/**
		 * Check if user can ADD to (a) Given category or to (b) at Any category
		 */

		// (a) Given category
		if ($submit_cat && $submit_cat->id)
		{
			$canAdd = $user->authorise('core.create', 'com_content.category.' . $submit_cat->id);
		}

		// (b) Any category (or to the CATEGORY IDS of given CATEGORY VIEW OBJECT)
		else
		{
			// Given CATEGORY VIEW OBJECT may limit to specific category ids
			$canAdd = $user->authorise('core.create', 'com_flexicontent');

			if ($canAdd === NULL && $user->id)
			{
				/**
				 * Performance concern (NULL for $canAdd) means SOFT DENY, also check for logged user
				 * thus to avoid checking some/ALL categories for "create" privelege for unlogged users
				 */
				$specific_catids = $submit_cat ? @ $submit_cat->ids  :  false;
				if ($specific_catids && count($specific_catids) > 3) $specific_catids = false;
				$allowedcats = FlexicontentHelperPerm::getAllowedCats( $user, $actions_allowed=array('core.create'), $require_all=true, $check_published = true, $specific_catids, $find_first = true );
				$canAdd = count($allowedcats);
			}
		}

		if (!$canAdd && !$ignore_unauthorized)
		{
			return '';
		}


		/**
		 * Create submit button/icon text
		 */

		if (is_object($btn_text))
		{
			$btn_title = JText::_( $btn_text->title );
			$btn_desc  = JText::_( $btn_text->tooltip );
		}
		else
		{
			$btn_title = JText::_( 'FLEXI_ADD' );
			$btn_desc  = $btn_text ?
				JText::_($btn_text) :
				JText::_( $submit_cat && $submit_cat->id  ?  'FLEXI_ADD_NEW_CONTENT_TO_CURR_CAT'  :  'FLEXI_ADD_NEW_CONTENT_TO_LIST' ) ;
		}


		/**
		 * Create link
		 */

		// Add Itemid (if given) and do SEF URL routing it --before-- appending more variables, so that
		// ... menu item URL variables from given menu item ID will be appended if SEF URLs are OFF
		$menu_itemid = $menu_itemid ? $menu_itemid : (int)$params->get('addbutton_menu_itemid', 0);
		$menu_typeid = 0;
		
		if ($menu_itemid)
		{
			$menu = $app->getMenu()->getItem($menu_itemid);
			$menu_typeid = isset($menu->query['typeid']) ? (int) $menu->query['typeid'] : 0;
		}

		if ($menu_typeid)
		{
			$link  = 'index.php?option=com_flexicontent' . ($menu_itemid
				? '&amp;Itemid='.$menu_itemid
				: '&amp;view='.FLEXI_ITEMVIEW.'&amp;task=items.add'
			);			
			$link  = JRoute::_( $link);
		}
		else
		{
			$link  = 'index.php?option=com_flexicontent' . ($menu_itemid ? '&amp;menu_id='.$menu_itemid : '')
				. '&amp;view=types&amp;tmpl=component&amp;layout=typeslist&amp;action=new';
			$link  = JUri::base() . $link;
		}

		// Add main category ID (if given)
		if ($submit_cat && $submit_cat->id)
		{
			$link .= (strstr($link, '?') ? '&amp;' : '?') . 'maincat='.$submit_cat->id;
		}

		// Append autorelate information to the URL (if given)
		if ($auto_relations)
		{
			foreach ($auto_relations as $auto_relation)
			{
				$link .= (strstr($link, '?') ? '&amp;' : '?') . 'autorelation_'.$auto_relation->fieldid.'='.$auto_relation->itemid;
			}
		}


		/**
		 * Finally create the submit icon / button
		 */


		// Get font icon or image icon for the button
		$config = (object) array(
			'iconname' => 'new',
			'icondefault' => 'icon-new',
			'iconimage' => 'plus-button.png',
			'iconpath' => 'components/com_flexicontent/assets/images',  // Use icon from flexicontent assets folder
			'icontitle' => $btn_desc,
		);

		$show_icons = (int) $params->get('show_icons', 2);
		$image = '';
		self::createFcBtnIcon($params, $config, $image);

		/*if (!$auto_relations)
		{
			$image = '';
		}*/

		$button_classes = 'fc_addbutton';

		if ($show_icons === 1 && !$auto_relations)
		{
			$caption = '';
			$button_classes .= '';
			$tooltip_placement = 'top';
		}
		else
		{
			$caption = $btn_title;
			if ( !$params->get('btn_grp_dropdown', 0) ) $button_classes .=
				(self::$use_bootstrap ? ' btn btn-small' : ' fc_button fcsimple fcsmall')
				.($auto_relations ? ' btn-success' : '');
			$tooltip_placement = !$params->get('btn_grp_dropdown', 0) ? 'top' : 'left';
		}

		$button_classes .= ' hasTooltip';
		$tooltip_title = flexicontent_html::getToolTip($btn_title, $btn_desc, 0);

		$output = ' <a href="' . $link . '" class="' . $button_classes . '" data-placement="' . $tooltip_placement . '" title="' . $tooltip_title . '"
		' . (!$menu_typeid
			? 'onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 1200, 0, false, {\'title\': \''.flexicontent_html::encodeHTML(JText::_('FLEXI_TYPE'), 2).'\'}); return false;" '
			: '') . '
		>' . $image.$caption . '</a>';

		if (!$auto_relations)
		{
			$output = JText::_('FLEXI_ICON_SEP') . $output . JText::_('FLEXI_ICON_SEP');
		}

		return $output;
	}


	/**
	 * Creates the stateicon
	 *
	 * @param int $state
	 * @param array $params
	 * @since 1.0
	 */
	static function stateicon($state, $params, $type = 'html', &$state_text = null, $item = null, $show_status = 0)
	{
		static $jtext_state = null;
		static $tooltip_class = ' hasTooltip';

		static $state_names = null;
		static $state_imgs = null;

		static $state_basictips = null;
		static $state_fulltips = null;

		static $state_icons = array();
		static $font_icons = null;

		if ($state_names === null)
		{
			$jtext_state = JText::_( 'FLEXI_STATE' );
			$state_names = array(
				 1  => JText::_('FLEXI_PUBLISHED'),
				-5  => JText::_('FLEXI_IN_PROGRESS'),
				 0  => JText::_('FLEXI_UNPUBLISHED'),
				-3  => JText::_('FLEXI_PENDING'),
				-4  => JText::_('FLEXI_TO_WRITE'),
				 2  => JText::_('FLEXI_ARCHIVED'),
				-2  => JText::_('FLEXI_TRASHED'),
				'u' => JText::_('FLEXI_UNKNOWN'),
				'e' => JText::_('FLEXI_PUBLICATION_EXPIRED'),
				's' => JText::_('FLEXI_SCHEDULED_FOR_PUBLICATION'),
			);
			$state_imgs = array(
				 1  => 'accept.png',
				-5  => 'publish_g.png',
				 0  => 'publish_x.png',
				-3  => 'publish_r.png',
				-4  => 'publish_y.png',
				 2  => 'archive.png',
				-2  => 'trash.png',
				'u' => 'unknown.png',
				'e' => 'clock.png',
				's' => 'warning.png',
			);
			$font_icons = array(
				 1  => 'publish',
				-5  => 'checkmark-2',
				 0  => 'unpublish',
				-3  => 'question',
				-4  => 'pencil-2',
				 2  => 'archive',
				-2  => 'trash',
				'u' => 'question-2',
				'e' => 'expired',
				's' => 'pending',
			);

			foreach($state_names as $state_id => $state_name)
			{
				$content = str_replace('::', '-', $state_name);
				$state_fulltips[$state_id] = flexicontent_html::getToolTip($jtext_state, $content, 0);
			}

			foreach($state_names as $state_id => $state_name)
			{
				$content = str_replace('::', '-', $state_name);
				$state_basictips[$state_id] = $jtext_state.' : '.$content;
			}
		}

		// Check for invalid state
		if (!isset($state_names[$state]))
		{
			$state = 'u';
		}

		// Check for expired that is published / in progress / archived
		if ($item && $show_status && !empty($item->publication_expired) && in_array($state, array(1, -5, 2)))
		{
			$show_status === 1
				? $state = 'e'
				: $is_expired = true;
		}

		// Check for scheduled that is published / in progress / archived
		elseif ($item && $show_status && !empty($item->publication_scheduled) && in_array($state, array(1, -5, 2)))
		{
			$show_status === 1
				? $state = 's'
				: $is_scheduled = true;
		}

		$scheduled_expired_html = !empty($is_expired)
			? '<span class="fc_expired"></span>'
			: (!empty($is_scheduled) ? '<span class="fc_scheduled"></span>' : '');

		$show_icons = (int) $params->get('show_icons', 1);
		$state_text = $state_text ?: $state_names[$state];

		// Return state name if not showing icons
		if (!$show_icons)
		{
			return $state_names[$state];
		}

		// Return cached icon if already calculated
		$popup_type = $type === 'json'
			? 'basic'
			: $params->get('stateicon_popup', 'full');

		if (isset($state_icons[$state][$popup_type]))
		{
			return $scheduled_expired_html . $state_icons[$state][$popup_type];
		}

		// If using font icons
		$use_font = $params->get('use_font_icons', 1);
		$icon_class = $use_font ? 'icon-'.$font_icons[$state] : '';

		// Create output
		$data = array();
		switch ( $popup_type )
		{
			case 'none':
				$data['class'] = $icon_class;
				break;
			case 'basic':
			case 'full': default:
				$data['class'] = $icon_class . ' ' . ($popup_type != 'basic' ? $tooltip_class : '');
				$data['title'] = $state_basictips[$state];
				break;
		}

		// Special case return uncached JSON encoded data
		if ($type === 'json')
		{
			$data['html'] = ($use_font
				? '<span class="' . $data['class'] . '"></span>'
				: JHtml::image('components/com_flexicontent/assets/images/'.$state_imgs[$state], $state_text, '')
			) . ($show_icons === 2
				? '<span class="fc-mssg-inline fc-info fc-iblock fc-nobgimage">' . $state_text . '</span>'
				: ''
			);

			unset($data['class']);
			return json_encode($data);
		}

		// Create state icon image, cache it and return it
		$tag_attribs = '';
		foreach($data as $key => $val)
		{
			$tag_attribs .= ' ' . $key . '="' . $val . '" ';
		}
		$state_icons[$state][$popup_type] = ($use_font
			? '<span ' . $tag_attribs . '></span>'
			: JHtml::image('components/com_flexicontent/assets/images/'.$state_imgs[$state], $state_text, $tag_attribs)
		) . ($show_icons === 2
				? '<span class="fc-mssg-inline fc-info fc-iblock fc-nobgimage">' . $state_text . '</span>'
				: ''
		);

		return $scheduled_expired_html . $state_icons[$state][$popup_type];
	}


	/**
	 * Creates the ratingbar
	 *
	 * @deprecated
	 * @param array $item
	 * @since 1.0
	 */
	static function ratingbar($item)
	{
		if ((int) $item->votes <= 0)
		{
			return '<span class="badge">'.JText::_( 'FLEXI_NOT_YET_RATED' ).'</span>';
		}

		// Round to integer percentage in case it was not done already in the SQL query
		$rating = round($item->votes);

		$tooltip_class = ' hasTooltip';
		$tooltip_title = flexicontent_html::getToolTip(JText::_('FLEXI_RATING'), JText::_( 'FLEXI_SCORE' ).': '.$rating.'%', 0, 1);
		$output = '<span class="qf_ratingbarcontainer'.$tooltip_class.'" title="'.$tooltip_title.'">';
		$output .= '<span class="qf_ratingbar" style="width:'.$rating.'%;">&nbsp;</span></span>';

		return $output;
	}

	/**
	 * Creates the voteicons
	 * Deprecated to ajax votes
	 *
	 * @param array $params
	 * @since 1.0
	 */
	static function voteicons($item, &$params)
	{
		static $voteup, $votedown, $tooltip_class, $tip_vote_up, $tip_vote_down;
		if (!$tooltip_class)
		{
			$tooltip_class = ' hasTooltip';
			$show_icons = (int) $params->get('show_icons');

			if ($show_icons)
			{
				$voteup = JHtml::image('components/com_flexicontent/assets/images/'.'thumb_up.png', JText::_( 'FLEXI_GOOD' ), NULL);
				$votedown = JHtml::image('components/com_flexicontent/assets/images/'.'thumb_down.png', JText::_( 'FLEXI_BAD' ), NULL);
			}
			else
			{
				$voteup = JText::_( 'FLEXI_GOOD' ). '&nbsp;';
				$votedown = '&nbsp;'.JText::_( 'FLEXI_BAD' );
			}

			$tip_vote_up = flexicontent_html::getToolTip('FLEXI_VOTE_UP', 'FLEXI_VOTE_UP_TIP', 1, 1);
			$tip_vote_down = flexicontent_html::getToolTip('FLEXI_VOTE_DOWN', 'FLEXI_VOTE_DOWN_TIP', 1, 1);
		}

		$item_url = JRoute::_('index.php?task=vote&vote=1&cid='.$item->categoryslug.'&id='.$item->slug.'&layout='.$params->get('ilayout'));
		$link = $item_url . (strpos($item_url, '?') !== false ? '&' : '?');
		$output = '<a href="'.$link.'vote=1" class="fc_vote_up'.$tooltip_class.'" title="'.$tip_vote_up.'">'.$voteup.'</a>';
		$output .= ' - ';
		$output .= '<a href="'.$link.'vote=1" class="fc_vote_down'.$tooltip_class.'" title="'.$tip_vote_down.'">'.$votedown.'</a>';

		return $output;
	}

	/**
	 * Creates the ajax voting stars system
	 *
	 * @param array $field
	 * @param int or string $xid
	 * @since 1.0
	 */
	static function ItemVote(&$field, $xid, $vote)
	{
		// Check for invalid xid
		if ($xid !== 'main' && $xid !== 'extra' && $xid !== 'all' && !(int) $xid)
		{
			return 'ItemVote(): invalid xid \'' . $xid . '\' was given';
		}

		if (!$vote)
		{
			$vote = new stdClass();
			$vote->rating_sum = $vote->rating_count = 0;
		}
		elseif (!isset($vote->rating_sum) || !isset($vote->rating_sum))
		{
			$vote->rating_sum = $vote->rating_count = 0;
		}

		$html = '';
		$int_xid = (int) $xid;
		$item_id = $field->item_id;


		// Get extra voting option (composite voting)
		$xids = array();
		$enable_extra_votes = (int) $field->parameters->get('enable_extra_votes', 0);

		if ($enable_extra_votes && ($xid === 'all' || $xid === 'extra' || $int_xid))
		{
			// Retrieve and split-up extra vote types, (removing last one if empty)
			$extra_votes = $field->parameters->get('extra_votes', '');
			$extra_votes = preg_split("/[\s]*%%[\s]*/", $extra_votes);

			if (empty($extra_votes[count($extra_votes)-1]))
			{
				unset($extra_votes[count($extra_votes)-1]);
			}

			// Split extra voting ids (xid) and their titles
			foreach ($extra_votes as $extra_vote)
			{
				@list($extra_id, $extra_title, $extra_desc) = explode("##", $extra_vote);
				$xids[$extra_id] = new stdClass();
				$xids[$extra_id]->id    = (int) $extra_id;
				$xids[$extra_id]->title = JText::_($extra_title);
				$xids[$extra_id]->desc  = JText::_($extra_desc);
			}
		}

		// Get user current history so that it is reflected on the voting
		$vote_history = JFactory::getSession()->get('vote_history', array(),'flexicontent');
		if (!isset($vote_history[$item_id]) || !is_array($vote_history[$item_id]))
		{
			$vote_history[$item_id] = array();
		}

		// Add main voting option
		if ($xid === 'main' || $xid === 'all')
		{
			$vote_label = JText::_($field->parameters->get('main_label', 'FLEXI_VOTE_AVERAGE_RATING'));
			$counter_show_label = $field->parameters->get('main_counter_show_label', 1);
			$add_review_form = (int) $field->parameters->get('allow_reviews', 0);
			$html .= flexicontent_html::ItemVoteDisplay(
				$field,
				$item_id,
				$vote->rating_sum,
				$vote->rating_count,
				'main',
				$vote_label,
				$stars_override = 0,
				($allow_vote = isset($vote->allow_vote) ? $vote->allow_vote : true),
				$vote_counter = 'default',
				$counter_show_label,
				$add_review_form,
				$xids,
				$review_type='item'
			);
		}

		// Add extra voting option
		if ($xid === 'all' || $xid === 'extra' || ($int_xid && isset($xids[$xid])))
		{
			/**
			 * If integer then add specific voting option for the given XID,
			 * if string (either 'all' or 'extra') then add all extra voting options
			 */
			$_xids = $int_xid
				? array($int_xid => $xids[$int_xid])
				: $xids;

			$counter_show_label = $field->parameters->get('extra_counter_show_label', 1);

			foreach ($_xids as $extra_id => $xid_obj)
			{
				if (!isset($vote->extra[$extra_id]))
				{
					$extra_vote = new stdClass();
					$extra_vote->rating_sum = $extra_vote->rating_count = 0;
					$extra_vote->extra_id = $extra_id;
				}
				else
				{
					$extra_vote = $vote->extra[$extra_id];
				}

				// Display incomplete vote
				if ((int) $extra_id && !isset($vote_history[$item_id]['main']) && isset($vote_history[$item_id][$extra_id]))
				{
					$rating_sum = $vote_history[$item_id][$extra_id];
					$rating_count = 1;
				}
				else
				{
					$rating_sum = 0;
					$rating_count = 0;
				}

				$html .= flexicontent_html::ItemVoteDisplay(
					$field,
					$item_id,
					($extra_vote->rating_sum + $rating_sum),
					($extra_vote->rating_count + $rating_count),
					$extra_vote->extra_id,
					$xid_obj,
					$stars_override = 0,
					($allow_vote = isset($vote->allow_vote) ? $vote->allow_vote : true),
					$vote_counter = 'default',
					$counter_show_label
				);
			}
		}

		return '
		<div class="' . $field->name . '-group">
			' . $html . '
		</div>';
	}


	/**
	 * Method that creates the stars
	 *
	 * @param array				$field
	 * @param int 				$id
	 * @param int			 	$rating_sum
	 * @param int 				$rating_count
	 * @param int or string 	$xid
	 * @since 1.0
	 */
	static function ItemVoteDisplay($field, $id, $rating_sum, $rating_count, $xid, $xiddata='', $stars_override=0, $allow_vote=true, $vote_counter='default', $counter_show_label=true, $add_review_form=0, $xids=array(), $review_type='item')
	{
		static $acclvl_names  = null;
		static $star_tooltips = null;
		static $star_classes  = null;

		$user    = JFactory::getUser();
		$db      = JFactory::getDbo();
		$cparams = JComponentHelper::getParams('com_flexicontent');
		$app     = JFactory::getApplication();
		$view    = $app->input->get('flexi_callview', '', 'cmd');

		// Only label given
		if (!is_object($xiddata))
		{
			$label = $xiddata;
			$desc  = '';
		}

		// Label & description
		else
		{
			$label = $xiddata->title;
			$desc  = $xiddata->desc;
		}

		$int_xid = (int) $xid;


		/**
		 * Find if user has the ACCESS level required for voting
		 */

		 // Note  will need to remove static calculation, if this gets implemented per type
		static $has_acclvl, $acclvl;

		if ($has_acclvl === null)
		{
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$acclvl = (int) $field->parameters->get('submit_acclvl', 1);
			$has_acclvl = in_array($acclvl, $aid_arr);
		}


		/**
		 * Calculate NO access actions, (case that user cannot vote)
		 */

		if (!$has_acclvl)
		{
			if ($user->id)
			{
				$no_acc_msg = $field->parameters->get('logged_no_acc_msg', '');
				$no_acc_url = $field->parameters->get('logged_no_acc_url', '');
				$no_acc_doredirect  = (int) $field->parameters->get('logged_no_acc_doredirect', 0);
				$no_acc_askredirect = (int) $field->parameters->get('logged_no_acc_askredirect', 1);
			}
			else
			{
				$no_acc_msg  = $field->parameters->get('guest_no_acc_msg', '');
				$no_acc_url  = $field->parameters->get('guest_no_acc_url', '');
				$no_acc_doredirect  = (int) $field->parameters->get('guest_no_acc_doredirect', 2);
				$no_acc_askredirect = (int) $field->parameters->get('guest_no_acc_askredirect', 1);
			}

			// Decide no access Redirect URLs
			if ($no_acc_doredirect === 2)
			{
				$com_users = 'com_users';
				$no_acc_url = $cparams->get('login_page', 'index.php?option='.$com_users.'&view=login');
			}
			elseif ($no_acc_doredirect === 0)
			{
				$no_acc_url = '';
			}
			// else unchanged


			// Decide no access Redirect Message
			$no_acc_msg = $no_acc_msg ? JText::_($no_acc_msg, true) : null;

			// Find name of required Access Level
			if (!$no_acc_msg)
			{
				$acclvl_name = '';

				// Retrieve this ONCE (static var)
				if ($acclvl && empty($acclvl_names))
				{
					$acclvl_names = flexicontent_db::getAccessNames();
				}

				$acclvl_name = !empty($acclvl_names[$acclvl])
					? $acclvl_names[$acclvl]
					: 'Access Level: ' . $acclvl . ' not found / was deleted';
				$no_acc_msg = JText::sprintf('FLEXI_VOTE_NO_ACCESS_TO_VOTE', $acclvl_name);
			}

			$no_acc_msg_redirect = JText::_($no_acc_doredirect === 2
				? 'FLEXI_VOTE_CONFIM_REDIRECT_TO_LOGIN_REGISTER'
				: 'FLEXI_VOTE_CONFIM_REDIRECT'
			);
		}

		if ($vote_counter !== 'default' && $vote_counter !== '')
		{
			$counter = $vote_counter ? 1 : 0;
		}
		else
		{
			$counter = (int) $field->parameters->get($int_xid ? 'extra_counter' : 'main_counter', 1);
		}

		$class = $field->name . '-row';

		// Display info e.g. counter if unrated, TODO add parameter
		$show_unrated    = (int) $field->parameters->get('show_unrated', 0);
		$show_percentage = (int) $field->parameters->get($int_xid ? 'extra_counter_show_percentage' : 'main_counter_show_percentage', 0);

		// Get number of displayed stars, configuration
		$rating_resolution = (int)$field->parameters->get('rating_resolution', 5);
		$rating_resolution = $rating_resolution >= 5 ? $rating_resolution : 5;
		$rating_resolution = $rating_resolution <= 100 ? $rating_resolution : 100;

		// Get number of displayed stars, configuration
		$rating_stars = (int) ($stars_override ? $stars_override : $field->parameters->get('rating_stars', 5));
		$rating_stars = $rating_stars > $rating_resolution ? $rating_resolution  :  $rating_stars;  // Limit stars to resolution


		static $js_and_css_added = false;

		if (!$js_and_css_added)
		{
			$cparams = JComponentHelper::getParams( 'com_flexicontent' );

			// Load tooltips JS
			if ($cparams->get('add_tooltips', 1)) JHtml::_('bootstrap.tooltip');

			flexicontent_html::loadFramework('jQuery');
			flexicontent_html::loadFramework('flexi_tmpl_common');

			$document = JFactory::getDocument();
			$document->addStyleSheet(JUri::root(true).'/components/com_flexicontent/assets/css/fcvote.css', array('version' => FLEXI_VHASH));
			$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/fcvote.js', array('version' => FLEXI_VHASH));

			$image = $field->parameters->get( 'main_image', 'components/com_flexicontent/assets/images/star-medium.png' );
			$img_path	= JUri::root(true).'/'.$image;

			$dim = $field->parameters->get( 'main_dimension', 24 );
			$element_width = $rating_resolution * $dim;
			if ($rating_stars) $element_width = (int) $element_width * ($rating_stars / $rating_resolution);

			$css = '
			/* This is via voting field parameter, please edit field configuration to override them */
			.'.$class.' div.fcvote.fcvote-box-main {
				line-height:'.$dim.'px!important;
			}
			.'.$class.' div.fcvote.fcvote-box-main > ul.fcvote_list {
				height:'.$dim.'px!important;
				width:'.$element_width.'px!important;
			}
			.'.$class.' div.fcvote.fcvote-box-main > ul.fcvote_list > li.voting-links a,
			.'.$class.' div.fcvote.fcvote-box-main > ul.fcvote_list > li.current-rating {
				height:'.$dim.'px!important;
				line-height:'.$dim.'px!important;
			}
			.'.$class.' div.fcvote.fcvote-box-main > ul.fcvote_list,
			.'.$class.' div.fcvote.fcvote-box-main > ul.fcvote_list > li.voting-links a:hover,
			.'.$class.' div.fcvote.fcvote-box-main > ul.fcvote_list > li.current-rating {
				background-image:url('.$img_path.')!important;
			}
			';

			// Always add image configuration for composite (extra) votes in case some type is using them
			$image = $field->parameters->get( 'extra_image', 'components/com_flexicontent/assets/images/star-medium.png' );
			$img_path	= JUri::root(true).'/'.$image;

			$dim = $field->parameters->get( 'extra_dimension', 24 );
			$element_width = $rating_resolution * $dim;
			if ($rating_stars) $element_width = (int) $element_width * ($rating_stars / $rating_resolution);

			$css .= '
			/* This is via voting field parameter, please edit field configuration to override them */
			.'.$class.' div.fcvote > ul.fcvote_list {
				height:'.$dim.'px!important;
				width:'.$element_width.'px!important;
			}
			.'.$class.' div.fcvote > ul.fcvote_list > li.voting-links a,
			.'.$class.' div.fcvote > ul.fcvote_list > li.current-rating {
				height:'.$dim.'px!important;
				line-height:'.$dim.'px!important;
			}
			.'.$class.' div.fcvote > ul.fcvote_list,
			.'.$class.' div.fcvote > ul.fcvote_list > li.voting-links a:hover,
			.'.$class.' div.fcvote > ul.fcvote_list > li.current-rating {
				background-image:url('.$img_path.')!important;
			}
			';

			$rating_texts = trim($field->parameters->get(
				'rating_texts',
				'FLEXI_VOTE_VERY_POOR, FLEXI_VOTE_POOR, FLEXI_VOTE_FAIR, FLEXI_VOTE_GOOD, FLEXI_VOTE_EXCELLENT'
			));
			$rating_texts = preg_split("/\s*,\s*/u", $rating_texts);

			$star_tooltips = array();
			$star_classes  = array();

			for ($i = 1; $i <= $rating_resolution; $i++)
			{
				$star_zindex  = $rating_resolution - $i + 2;
				$star_percent = (int) round(100 * ($i / $rating_resolution));
				$css .= '.' . $class . ' div.fcvote ul.fcvote_list > .voting-links a.star' . $i . ' { width: ' . $star_percent . '%!important; z-index: ' . $star_zindex . '; }' . "\n";
				$star_classes[$i] = 'star' . $i;

				switch (true)
				{
					case $star_percent <= 20:
						$star_tooltips[$i] = JText::_(isset($rating_texts[0]) ? $rating_texts[0] : 'FLEXI_VOTE_VERY_POOR');
						break;

					case $star_percent <= 40:
						$star_tooltips[$i] = JText::_(isset($rating_texts[1]) ? $rating_texts[1] : 'FLEXI_VOTE_POOR');
						break;

					case $star_percent <= 60:
						$star_tooltips[$i] = JText::_(isset($rating_texts[2]) ? $rating_texts[2] : 'FLEXI_VOTE_FAIR');
						break;

					case $star_percent <= 80:
						$star_tooltips[$i] = JText::_(isset($rating_texts[3]) ? $rating_texts[3] : 'FLEXI_VOTE_GOOD');
						break;

					default:
						$star_tooltips[$i] = JText::_(isset($rating_texts[4]) ? $rating_texts[4] : 'FLEXI_VOTE_EXCELLENT');
						break;
				}
				$star_tooltips[$i] .= ' ' . $i . '/' . $rating_resolution;
			}

			$document->addStyleDeclaration($css);
			$js_and_css_added = true;
		}

		$percent = 0;
		$factor = (int) round(100 / $rating_resolution);

		if ((int) $rating_count !== 0)
		{
			$percent = number_format(((int) $rating_sum / (int) $rating_count) * $factor, 2);
		}
		elseif ($show_unrated === 0)
		{
			$counter = -1;
		}

		// Disable showing vote counter in extra votes
		if ($int_xid)
		{
			$counter !== 2 ?: $counter = 0;
		}

		// Disable showing vote counter in main vote
		else
		{
			$counter !== 3 ?: $counter = 0;
		}

		$nocursor = !$allow_vote
			? 'cursor: unset !important;'
			: '';

		$html_vote_links = '';

		if ($allow_vote)
		{
			// HAS Voting ACCESS
			if ($has_acclvl)
			{
				$href = 'javascript:;';
				$onclick = '';
			}

			// NO Voting ACCESS
			else
			{
				// WITHOUT Redirection
				if (!$no_acc_url)
				{
					$href = 'javascript:;';
					$popup_msg = addcslashes($no_acc_msg, "'");
					$onclick = 'alert(\''.$popup_msg.'\');';
				}

				// WITH Redirection
				else
				{
					$href = $no_acc_url;
					$popup_msg = addcslashes($no_acc_msg . "\\n\\n" . $no_acc_msg_redirect, "'");

					if ($no_acc_askredirect==2)       $onclick = 'return confirm(\''.$popup_msg.'\');';
					else if ($no_acc_askredirect==1)  $onclick = 'alert(\''.$popup_msg.'\'); return true;';
					else                              $onclick = 'return true;';
				}
			}

			$dovote_class = ' hasTooltip ' . ($has_acclvl ? 'fc_dovote' : '');

			for ($i = 1; $i <= $rating_resolution; $i++)
			{
				$html_vote_links .= '
					<li class="voting-links"><a onclick="'.$onclick.'" href="'.$href.'" title="'.$star_tooltips[$i].'" class="'.$dovote_class.' '.$star_classes[$i].'" data-rel="'.$id.'_'.$xid.'">'.$i.'</a></li>';
			}
		}

		return '
		<div class="'.$class.' '.$class.'_'.$xid.'">
			<div class="fcvote fcvote-box-'.$xid.'">
				<div class="nowrap_box fcvote-label-outer">
					'.($label ? '<div class="fcvote-label xid-'.$xid.'">'.$label.'</div>' : '').'
					<div class="fc-mssg fc-info fc-iblock fc-nobgimage fcvote-count" '.( !$rating_count || (($counter==-1 || $counter==0) && !$show_percentage) ? 'style="display:none;"' : '' ).'>'.
						($show_percentage ? ((int)$percent ? (int)$percent.'%' : '') : '').
						( $counter==-1 || $counter==0 ? '' :
							($show_percentage && (int)$percent ? ' - ' : '').
							($rating_count ? $rating_count : '0').
							($counter_show_label ? ' '.JText::_( $rating_count!=1 ? 'FLEXI_VOTES' : 'FLEXI_VOTE' ) : '')
						).'
					</div>'.
					(!(int)$percent || $int_xid || $view !== 'item' ? '' : '
					<span itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">
						<meta itemprop="itemReviewed" content="'.htmlspecialchars($field->item_title, ENT_QUOTES, 'UTF-8').'" />
						<meta itemprop="ratingValue" content="'.round($percent).'" />
						<meta itemprop="bestRating"  content="100" />
						<meta itemprop="ratingCount" content="'.$rating_count.'" />
					</span>').'
				</div>
				<ul class="fcvote_list">
					<li class="current-rating" style="width:'.(int)$percent.'%;'.$nocursor.'"></li>
					'.$html_vote_links.'
				</ul>

				<div class="fcvote_message" ></div>

				'.($add_review_form ? '
				<input type="button" class="btn btn-primary fcvote_toggle_review_form"
					onclick="fcvote_open_review_form(jQuery(\'#fcvote_review_form_box_'.$id.'\').attr(\'id\'), '.$id.', \''.$review_type.'\', this)"
					value="'.JText::_('FLEXI_VOTE_REVIEW_THIS_ITEM').'"/>
				<div id="fcvote_review_form_box_'.$id.'_loading" class="fcvote_review_form_box_loading"></div>
				<span class="fcclear"></span>
				<div id="fcvote_review_form_box_'.$id.'" class="fcvote_review_form_box" style="display:none;"></div>' : '').'

				'.( $desc ? '<div class="fcvote-desc">'.$desc.'</div>' :'' ).'
			</div>
		</div>';
	}


	/**
	 * Creates the favourited by user list
	 *
	 * @param array $params
	 * @since 1.0
	 */
	static function favoured_userlist($field, $item, & $favourites, $type='item')
	{
		$layouts_path = null;

		/**
		 * field: 'Favourites' field
		 * item: item or category record
		 * type: 'item' or 'category'
		 * favourites: value (of field)
		 */

		$displayData = array(
			'field'      => $field,
			'item'       => $item,
			'type'       => 'item',
			'favourites' => $favourites,
		);

		return JLayoutHelper::render('flexicontent_fields.favourites.userlist', $displayData, $layouts_path);
	}


	/**
	 * Creates the favourite icons
	 *
	 * @param array $params
	 * @since 1.0
	 */
	static function favicon($field, & $favoured, $item, $type='item')
	{
		$layouts_path = null;

		/**
		 * field: 'Favourites' field
		 * item: item or category record
		 * type: 'item' or 'category'
		 * favoured: value (of field)
		 */

		$displayData = array(
			'field'    => $field,
			'item'     => $item,
			'type'     => 'item',
			'favoured' => $favoured,
		);

		return JLayoutHelper::render('flexicontent_fields.favourites.favicon', $displayData, $layouts_path);
	}


	/**
	 * Method to build a list of radio or checkbox buttons
	 *
	 * @return array
	 * @since 1.5
	 */
	static function buildradiochecklist($options, $name, $selected, $buildtype=0, $attribs = '', $tagid=null, $label_class='', $label_on_class='', $fset_attribs='')
	{
		$add_fset = $buildtype==1 || $buildtype==3;
		$selected = is_array($selected) ? $selected : array($selected);
		$tagid = $tagid ? $tagid : str_replace( '[', '_', preg_replace('#\]|\[\]#', '',($name)) );

		// Set input-set attributes and input's label class if not given
		$fset_attribs = !$fset_attribs && $add_fset
			? ' class="radio btn-group group-fcinfo"'
			: $fset_attribs;
		$label_class = !$label_class && $add_fset
			? ''
			: $label_class;
		$label_on_class = !$label_on_class && $add_fset
			? ''
			: $label_on_class;

		$html = $add_fset ? '<fieldset ' . $fset_attribs . '>' : '';
		$n = 0;
		foreach ($options as $value => $text) {
			$tagid_n = $tagid.$n;
			$html .='
			<input type="'.($buildtype > 1 ? 'checkbox' : 'radio').'" '.(in_array($value, $selected) ? ' checked="checked" ' : '').' value="'.$value.'" id="'.$tagid_n.'" name="'.$name.'" '.$attribs.'/>
			<label id="'.$tagid_n.'-lbl" for="'.$tagid_n.'" class="' . (in_array($value, $selected) ? $label_on_class : '') . $label_class.'">'.$text.'</label>
			';
			$n++;
		}
		$html .= $add_fset ? '</fieldset>' : '';
		return $html;
	}


	/**
	 * Method to build the list for types
	 *
	 * @return array
	 * @since 1.5
	 */
	static function buildtypesselect($types, $name, $selected, $displaytype, $attribs = 'class="inputbox"', $tagid=null, $check_perms=false)
	{
		$_list = array();

		if (!is_numeric($displaytype) && is_string($displaytype))
			$_list[] = JHtml::_( 'select.option', '', $displaytype );
		else if ($displaytype)
			$_list[] = JHtml::_( 'select.option', '', JText::_( 'FLEXI_SELECT_TYPE' ) );

		if ($check_perms)
			$user = JFactory::getUser();

		$selected_arr = is_array($selected) ? $selected : ($selected ? array($selected) : array());
		foreach ($types as $type)
		{
			$allowed = true;

			if ($check_perms)
			{
				$allowed = !$type->id || in_array($type->id, $selected_arr) || !$type->itemscreatable || $user->authorise('core.create', 'com_flexicontent.type.' . $type->id);
			}

			if ( !$allowed && $type->itemscreatable == 1 ) continue;

			if ( !$allowed && $type->itemscreatable == 2 )
				$_list[] = JHtml::_( 'select.option', $type->id, JText::_($type->name), 'value', 'text', $disabled = true );
			else
				$_list[] = JHtml::_( 'select.option', $type->id, JText::_($type->name));
		}

		$tagid = $tagid ? $tagid : str_replace( '[', '_', preg_replace('#\]|\[\]#', '',($name)) );
		return JHtml::_('select.genericlist', $_list, $name, $attribs, 'value', 'text', $selected, $tagid );
	}


	/**
	 * Method to build the list of the autors
	 *
	 * @return array
	 * @since 1.5
	 */
	static function buildauthorsselect($list, $name, $selected, $displaytype, $attribs = 'class="inputbox"', $tagid=null)
	{
		$_list = array();

		if (!is_numeric($displaytype) && is_string($displaytype))
			$_list[] = JHtml::_( 'select.option', '', $displaytype );
		else if ($displaytype)
			$_list[] = JHtml::_( 'select.option', '', JText::_( 'FLEXI_SELECT_AUTHOR' ) );

		$user_id_str = JText::_('FLEXI_ID') .': ';
		foreach ($list as $item) {
			$_list[] = JHtml::_( 'select.option', $item->id, $item->name ? $item->name : $user_id_str . $item->id );
		}

		$tagid = $tagid ? $tagid : str_replace( '[', '_', preg_replace('#\]|\[\]#', '',($name)) );
		return JHtml::_('select.genericlist', $_list, $name, $attribs, 'value', 'text', $selected, $tagid );
	}


	/**
	 * Method to build the list of the autors
	 *
	 * @return array
	 * @since 1.5
	 */
	static function buildtagsselect($name, $attribs, $selected, $displaytype=1, $tagid=null)
	{
		$db = JFactory::getDbo();
		$query = 'SELECT id, name'
		. ' FROM #__flexicontent_tags'
		. ' ORDER BY name ASC'
		;
		$db->setQuery($query);
		$data = $db->loadObjectList();

		$options = array();
		if (!is_numeric($displaytype) && is_string($displaytype))
			$options[] = JHtml::_( 'select.option', '', $displaytype);
		else if ($displaytype)
			$options[] = JHtml::_( 'select.option', '', JText::_( 'FLEXI_SELECT_TAG' ));

		foreach ($data as $val)
			$options[] = JHtml::_( 'select.option', $val->id, $val->name);

		$tagid = $tagid ? $tagid : str_replace( '[', '_', preg_replace('#\]|\[\]#', '',($name)) );
		return JHtml::_('select.genericlist', $options, $name, $attribs, 'value', 'text', $selected, $tagid );
	}


	/**
	 * Method to build the list for types when performing an edit action
	 *
	 * @return array
	 * @since 1.5
	 */
	static function buildfieldtypeslist($list, $name, $selected, $displaytype, $attribs = 'class="inputbox"', $tagid=null)
	{
		$tagid = $tagid ? $tagid : str_replace( '[', '_', preg_replace('#\]|\[\]#', '',($name)) );

		// $displaytype: 0, is ungrouped
		if (!$displaytype)
		{
			ksort( $list, SORT_STRING );
			return JHtml::_('select.genericlist', $list, $name, $attribs, 'value', 'text', $selected, $tagid );
		}

		// $displaytype: 1, is grouped
		else
		{
			$attribs = is_array($attribs)
				? ArrayHelper::toString($attribs)
				: $attribs;

			$field_types = array();
			$n = 0;
			$in_grp = false;
			foreach ($list as $key => $data)
			{
				if ( !is_numeric($key) )
				{
					$field_types[$key] = $data;
					$in_grp = true;
				}
				else
				{
					$n = !$in_grp ? $n : ($n + 1);
					$field_types[$n]['items'][] = $data;
					$in_grp = false;
				}
			}

			$xml = new SimpleXMLElement("<element $attribs />");
			$xml = (array)$xml->attributes();
			$attributes = $xml['@attributes'];

			$attribs = array(
				'id' => $tagid, // HTML id for select field
				'group.id' => 'id',
				'list.attr' => $attributes, // array(),  // additional HTML attributes for select field
				'list.translate'=>false, // true to translate
				'option.key'=>'value', // key name for value in data array
				'option.text'=>'text', // key name for text in data array
				'option.attr'=>'attr', // key name for attr in data array
				'list.select'=>$selected, // value of the SELECTED field
			);

			return JHtml::_('select.groupedlist', $field_types, $name, $attribs);
		}
	}


	/**
	 * Method to build the file extension list
	 *
	 * @return array
	 * @since 1.5
	 */
	static function buildfilesextlist($name, $attribs, $selected, $displaytype=1, $tagid=null)
	{
		$db = JFactory::getDbo();
		$query = 'SELECT DISTINCT ext'
		. ' FROM #__flexicontent_files'
		. ' ORDER BY ext ASC'
		;
		$db->setQuery($query);
		$data = $db->loadColumn();

		if (!is_numeric($displaytype) && is_string($displaytype))
			$options[] = JHtml::_( 'select.option', '', $displaytype);
		else if ($displaytype)
			$options[] = JHtml::_( 'select.option', '', JText::_( 'FLEXI_ALL_EXT' ));

		foreach ($data as $val)
			$options[] = JHtml::_( 'select.option', $val, $val);

		$tagid = $tagid ? $tagid : str_replace( '[', '_', preg_replace('#\]|\[\]#', '',($name)) );
		return JHtml::_('select.genericlist', $options, $name, $attribs, 'value', 'text', $selected, $tagid );
	}

	/**
	 * Method to build the uploader list
	 *
	 * @return array
	 * @since 1.5
	 */
	static function builduploaderlist($name, $attribs, $selected, $displaytype=1, $tagid=null)
	{
		$db = JFactory::getDbo();
		$query = 'SELECT DISTINCT f.uploaded_by AS uid, u.name AS name'
		. ' FROM #__flexicontent_files AS f'
		. ' LEFT JOIN #__users AS u ON u.id = f.uploaded_by'
		. ' ORDER BY f.ext ASC'
		;
		$db->setQuery($query);
		$data = $db->loadObjectList();

		if (!is_numeric($displaytype) && is_string($displaytype))
			$options[] = JHtml::_( 'select.option', '', $displaytype);
		else if ($displaytype)
			$options[] = JHtml::_( 'select.option', '', JText::_( 'FLEXI_ALL_UPLOADERS' ));

		foreach ($data as $val)
			$options[] = JHtml::_( 'select.option', $val->uid, $val->name ?? '' );

		$tagid = $tagid ? $tagid : str_replace( '[', '_', preg_replace('#\]|\[\]#', '',($name)) );
		return JHtml::_('select.genericlist', $options, $name, $attribs, 'value', 'text', $selected, $tagid );
	}


	/**
	 * Method to build the Joomfish languages list
	 *
	 * @return object
	 * @since 1.5
	 */
	static function buildlanguageslist($name, $attribs, $selected, $displaytype=1, $allowed_langs=null, $published_only=true, $disable_langs=null, $add_all=true, $radio_conf=false)
	{
		$db = JFactory::getDbo();
		$tagid = null; // ... not provided
		$tagid = $tagid ? $tagid : str_replace( '[', '_', preg_replace('#\]|\[\]#', '',($name)) );

		$selected_found = false;
		$all_langs = FLEXIUtilities::getlanguageslist($published_only, $add_all);
		$user_langs = null;

		if ($allowed_langs)
		{
			$_allowed = array_flip($allowed_langs);

			foreach ($all_langs as $index => $lang)
			{
				if (isset($_allowed[$lang->code]))
				{
					$user_langs[] = $lang;
					// Check if selected language was added to the user langs
					$selected_found = ($lang->code == $selected) ? true : $selected_found;
				}
			}
		}
		else
		{
			$user_langs = $all_langs;
			$selected_found = true;
		}

		if ($disable_langs)
		{
			$_disabled = array_flip($disable_langs);
			$_user_langs = array();

			foreach ($user_langs as $index => $lang)
			{
				if (!isset($_disabled[$lang->code]))
				{
					$_user_langs[] = $lang;
					// Check if selected language was added to the user langs
					$selected_found = ($lang->code == $selected) ? true : $selected_found;
				}
			}
			$user_langs = $_user_langs;
		}

		if (!count($user_langs))
		{
			return "user is not allowed to use any language";
		}

		// Force first language to be selected
		if (!$selected_found)
		{
			$selected = $user_langs[0]->code;
		}

		/**
		 * For radio cases
		 */
		if ($radio_conf && empty($radio_conf['flags']) && empty($radio_conf['texts']))
		{
			$radio_conf['flags'] = $radio_conf['texts'] = 1;
		}

		$required = $radio_conf && !empty($radio_conf['required'])
			? ' required validate-radio '
			: '';

		$langs = array();
		switch ($displaytype)
		{
			// Drop-down SELECT of ALL languages
			case 1: case 2: default:
				// WITH custom prompt to select language
				if (!is_numeric($displaytype) && is_string($displaytype))
				{
					$langs[] = JHtml::_('select.option',  '', $displaytype);
				}

				// WITH empty prompt to select language, e.g. used in items/category manager
				elseif ($displaytype == 2)
				{
					$langs[] = JHtml::_('select.option',  '', JText::_( 'FLEXI_SELECT_LANGUAGE' ));
				}

				foreach ($user_langs as $lang)
				{
					$langs[] = JHtml::_('select.option',  $lang->code, $lang->name );
				}

				$list = JHtml::_('select.genericlist', $langs, $name, $attribs, 'value', 'text', $selected, $tagid);
				break;

			// RADIO selection of ALL languages (Flag icons only) e.g. item form,
			case 3:
				$checked	= '';
				$list = '<div class="group-fcset fc_input_set">';

				foreach ($user_langs as $lang)
				{
					if ($lang->code == $selected)
					{
						$checked = ' checked="checked"';
					}

					$list .= '<input id="'.$tagid.'_'.$lang->id.'" type="radio" name="'.$name.'" value="'.$lang->code.'"'.$checked.' class="'.$required.'" data-element-grpid="'.$tagid.'" />';
					$list .= '<label class="lang_box" for="'.$tagid.'_'.$lang->id.'" title="'.$lang->name.'" >';

					if ($lang->shortcode === '*')
					{
						$list .= JText::_('FLEXI_ALL');
					}
					else
					{
						// Add Flag if configure and it exists
						if (!$radio_conf || $radio_conf['flags'])
						{
							$list .= !empty($lang->imgsrc)
								? '<img src="' . $lang->imgsrc . '" alt="' . $lang->name . '" />'
								:  $lang->code;
						}

						// Add text if configured
						if ( !$radio_conf || $radio_conf['texts']==1 ) {
							$list .= $lang->code;
						} else if ( $radio_conf['texts']==2 ) {
							$list .= $lang->title;
						} else if ( $radio_conf['texts']==3 ) {
							$list .= $lang->title_native;
						} else if ( $conf['texts']==4 ) {
							$list .= $lang->name;
						} else {
							$list .= '';
						}
					}

					$list .= '</label>';
					$checked	= '';
				}
				$list .= '</div>';
				break;

			// RADIO selection of ALL languages, with empty default option "Keep original language", e.g. when copying/moving items
			case 4:
				$list  = '<input id="lang9999" type="radio" name="'.$name.'" class="'.$required.'" value="" checked="checked" data-element-grpid="'.$tagid.'" />';
				$list .= '<label class="lang_box" for="lang9999" title="'.JText::_( 'FLEXI_NOCHANGE_LANGUAGE_DESC' ).'" >';
				$list .= JText::_( 'FLEXI_NOCHANGE_LANGUAGE' );
				$list .= '</label><div class="fcclear"></div>';
				$list .= '<div class="group-fcset fc_input_set">';

				foreach ($user_langs as $lang)
				{
					$list .= '<input id="'.$tagid.'_'.$lang->id.'" type="radio" name="'.$name.'" class="'.$required.'" value="'.$lang->code.'" data-element-grpid="'.$tagid.'" />';
					$list .= '<label class="lang_box" for="'.$tagid.'_'.$lang->id.'" title="'.$lang->name.'">';

					if ($lang->shortcode === '*')
					{
						$list .= JText::_('FLEXI_ALL');
					}
					elseif (@$lang->imgsrc)
					{
						$list .= '<img src="'.$lang->imgsrc.'" alt="'.$lang->name.'" />';
					}
					else
					{
						$list .= $lang->name;
					}
					$list .= '&nbsp;</label>';
				}
				$list .= '</div>';
				break;

			// RADIO selection of ALL languages, EXCLUDE selected language, e.g. when translating items into another language
			case 5:

			// Also exclude '*' (ALL) language
			case 7:
				$list = '<div class="group-fcset fc_input_set">';

				foreach ($user_langs as $lang)
				{
					if ($lang->code == $selected) continue;
					if ($displaytype == 7 && $lang->shortcode === '*') continue;

					$list .= '<input id="'.$tagid.'_'.$lang->id.'" type="radio" name="'.$name.'" class="'.$required.'" value="'.$lang->code.'" data-element-grpid="'.$tagid.'" />';
					$list .= '<label class="lang_box" for="'.$tagid.'_'.$lang->id.'" title="'.$lang->name.'">';

					if ($lang->shortcode === '*')
					{
						$list .= JText::_('FLEXI_ALL');
					} else if (@$lang->imgsrc) {
						$list .= '<img src="'.$lang->imgsrc.'" alt="'.$lang->name.'" />';
					} else {
						$list .= $lang->name;
					}
					$list .= '</label>';
				}
				$list .= '</div>';
				break;

			// RADIO selection of ALL languages, with empty option "Use language column", e.g. used in CSV import view
			case 6:
				$list = '<div class="group-fcset fc_input_set">';

				$checked = $selected == '-99' ? 'checked="checked"' : '';
				$list .= '<input id="lang-99" type="radio" name="'.$name.'" class="'.$required.'" value="-99" '.$checked.' data-element-grpid="'.$tagid.'" />';
				$tooltip_class = ' hasTooltip';
				$tooltip_title = flexicontent_html::getToolTip('FLEXI_USE_LANGUAGE_COLUMN', 'FLEXI_USE_LANGUAGE_COLUMN_TIP', 1, 1);
				$list .= '<label class="lang_box'.$tooltip_class.'" for="lang-99" title="'.$tooltip_title.'">';
				$list .= JText::_( 'FLEXI_USE_LANGUAGE_COLUMN' );
				$list .= '</label>';

				foreach ($user_langs as $lang)
				{
					$checked = $lang->code==$selected ? 'checked="checked"' : '';
					$list .= '<input id="'.$tagid.'_'.$lang->id.'" type="radio" name="'.$name.'" class="'.$required.'" value="'.$lang->code.'" '.$checked.' data-element-grpid="'.$tagid.'" />';
					$list .= '<label class="lang_box" for="'.$tagid.'_'.$lang->id.'" title="'.$lang->name.'">';
					if ($lang->shortcode === '*')
					{
						$list .= JText::_('FLEXI_ALL');
					}
					/*elseif (@$lang->imgsrc)
					{
						$list .= '<img src="'.$lang->imgsrc.'" alt="'.$lang->name.'" />';
					}*/
					else
					{
						$list .= $lang->name;
					}
					$list .= '&nbsp;</label>';
				}
				$list .= '</div>';
				break;
		}
		return $list;
	}


	/**
	 * Method to build the Joomfish languages list
	 *
	 * @return object
	 * @since 1.5
	 */
	static function buildstateslist($name, $attribs, $selected, $displaytype=1, $tagid=null)
	{
		static $state_names = null;
		static $state_descrs = null;
		static $state_imgs = null;

		if (!$state_names)
		{
			$state_names = array(
				 1  => JText::_('FLEXI_PUBLISHED'),
				-5  => JText::_('FLEXI_IN_PROGRESS'),
				 0  => JText::_('FLEXI_UNPUBLISHED'),
				-3  => JText::_('FLEXI_PENDING'),
				-4  => JText::_('FLEXI_TO_WRITE'),
				 2  => JText::_('FLEXI_ARCHIVED'),
				-2  => JText::_('FLEXI_TRASHED'),
				'u' => JText::_('FLEXI_UNKNOWN'),
			);
			$state_descrs = array(
				 1 => JText::_('FLEXI_PUBLISH_THIS_ITEM'),
				-5 => JText::_('FLEXI_SET_STATE_AS_IN_PROGRESS'),
				 0 => JText::_('FLEXI_UNPUBLISH_THIS_ITEM'),
				-3 => JText::_('FLEXI_SET_STATE_AS_PENDING'),
				-4 => JText::_('FLEXI_SET_STATE_AS_TO_WRITE'),
				 2 => JText::_('FLEXI_ARCHIVE_THIS_ITEM'),
				-2 => JText::_('FLEXI_TRASH_THIS_ITEM'),
				'' => 'FLEXI_UNKNOWN',
			);
			$state_imgs = array(
				 1 => 'accept.png',
				-5 => 'publish_g.png',
				 0 => 'publish_x.png',
				-3 => 'publish_r.png',
				-4 => 'publish_y.png',
				 2 => 'archive.png',
				-2 => 'trash.png',
				'' => 'unknown.png',
			);
		}

		$state[] = JHtml::_('select.option', '', JText::_(!is_numeric($displaytype) && is_string($displaytype) ? $displaytype : 'FLEXI_DO_NOT_CHANGE'));
		$state[] = JHtml::_('select.option', -4, $state_names[-4]);
		$state[] = JHtml::_('select.option', -3, $state_names[-3]);
		$state[] = JHtml::_('select.option', -5, $state_names[-5]);
		$state[] = JHtml::_('select.option',  1, $state_names[1]);
		$state[] = JHtml::_('select.option',  0, $state_names[0]);
		$state[] = JHtml::_('select.option',  2, $state_names[2]);
		$state[] = JHtml::_('select.option', -2, $state_names[-2]);

		$tagid = $tagid ? $tagid : str_replace( '[', '_', preg_replace('#\]|\[\]#', '',($name)) );

		if ($displaytype == 1 || (!is_numeric($displaytype) && is_string($displaytype)))
		{
			$list = JHtml::_('select.genericlist', $state, $name, $attribs, 'value', 'text', $selected, $tagid);
		}

		elseif ($displaytype == 2)
		{
			$state_ids   = array(1, -5, 0, -3, -4); // published: 1, -5   unpublished: 0, -3, -4
			$state_ids[] = 2;  // archived
			$state_ids[] = -2;  // trashed
			$state_colors= array(1=>'darkgreen', -5=>'darkgreen', 0=>'darkred', -3=>'darkred', -4=>'darkred', 2=>'darkblue', -2=>'gray');

			$img_path = JUri::root(true)."/components/com_flexicontent/assets/images/";

			$list = '<div class="group-fcset fc_input_set">';

			$checked = $selected == '-99' ? ' checked="checked"' : '';
			$list .= '<input id="state-99" type="radio" name="state" class="state" value="-99" '.$checked.'/>';
			$tooltip_class = ' hasTooltip';
			$tooltip_title = flexicontent_html::getToolTip('FLEXI_USE_STATE_COLUMN', 'FLEXI_USE_STATE_COLUMN_TIP', 1, 1);
			$list .= '<label class="state_box'.$tooltip_class.'" for="state-99" title="'.$tooltip_title.'">';
			$list .= JText::_( 'FLEXI_USE_STATE_COLUMN' );
			$list .= '</label>';

			foreach ($state_ids as $i => $state_id)
			{
				//if ($state_id==0 || $state_id==2) $list .= "<br>";
				$checked = $state_id==$selected ? ' checked="checked"' : '';
				$list .= '<input id="state'.$state_id.'" type="radio" name="state" class="state" value="'.$state_id.'" '.$checked.'/>';
				$list .= '<label class="state_box" for="state'.$state_id.'" title="'.$state_names[$state_id].'" style="color:'.$state_colors[$state_id].';">';
				$list .= $state_names[$state_id];
				//$list .= '<img src="'.$img_path.$state_imgs[$state_id].'" width="16" height="16" style="border-width:0;" alt="'.$state_names[$state_id].'" />';
				$list .= '</label>';
			}
			$list .= '</div>';
		}

		else
		{
			$list = 'Bad type in buildstateslist()';
		}

		return $list;
	}


	/**
	 * Method to get the user's Current Language
	 *
	 * @return string
	 * @since 1.5
	 */
	static function getUserCurrentLang($short_tag=true)
	{
		static $UILang = null;
		if ($UILang) return $UILang[$short_tag];

		// Get CURRENT user interface language. Content language can be natively switched in J2.5
		// by using (a) the language switcher module and (b) the Language Filter - System Plugin
		$UILang[false] = JFactory::getLanguage()->getTag();
		$UILang[true]  = substr($UILang[false], 0,2);

		return $UILang[$short_tag];
	}


	static function loadComponentLanguage($client = 0)
	{
		static $loaded = array();
		if (isset($loaded[$client])) return;

		if (JFactory::getApplication()->isClient('administrator') && $client = 0) return;

		// Load english language file for 'com_flexicontent' and then override with current language file. Do not force a reload for either (not needed)
		JFactory::getLanguage()->load('com_flexicontent', ($client ? JPATH_ADMINISTRATOR : JPATH_SITE), 'en-GB', $force_reload = false, $load_default = true);
		JFactory::getLanguage()->load('com_flexicontent', ($client ? JPATH_ADMINISTRATOR : JPATH_SITE), null, $force_reload = false, $load_default = true);
		$loaded[$client] = true;
	}


	static function loadModuleLanguage($modulename)
	{
		static $loaded = array();
		if (isset($loaded[$modulename])) return;

		// Load english language file for current module then override (forcing a reload) with current language file
		JFactory::getLanguage()->load($modulename, JPATH_SITE, 'en-GB', $force_reload = false, $load_default = true);
		JFactory::getLanguage()->load($modulename, JPATH_SITE, null, $force_reload = true, $load_default = true);

		// Load component frontend language file
		flexicontent_html::loadComponentLanguage($client = 0);
		$loaded[$modulename] = true;
	}


	/**
	 * Method to get Site (Frontend) default language
	 * NOTE: ... this is the default language of created content for J1.5, but in J1.6+ is '*' (=all)
	 * NOTE: ... joomfish creates translations in all other languages
	 *
	 * @return string
	 * @since 1.5
	 */
	static function getSiteDefaultLang()
	{
		$languages = JComponentHelper::getParams('com_languages');
		$lang = $languages->get('site', 'en-GB');
		return $lang;
	}

	static function nl2space($string) {
		if(gettype($string)!="string") return false;
		$strlen = strlen($string);
		$array = array();
		$str = "";
		for($i=0;$i<$strlen;$i++) {
			if(ord($string[$i])===ord("\n")) {
				$str .= ' ';
				continue;
			}
			$str .= $string[$i];
		}
		return $str;
	 }


	/**
		Diff implemented in pure php, written from scratch.
		Copyright (C) 2003  Daniel Unterberger <diff.phpnet@holomind.de>
		Copyright (C) 2005  Nils Knappmeier next version

		This program is free software; you can redistribute it and/or
		modify it under the terms of the GNU General Public License
		as published by the Free Software Foundation; either version 2
		of the License, or (at your option) any later version.

		This program is distributed in the hope that it will be useful,
		but WITHOUT ANY WARRANTY; without even the implied warranty of
		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
		GNU General Public License for more details.

		You should have received a copy of the GNU General Public License
		along with this program; if not, write to the Free Software
		Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

		http://www.gnu.org/licenses/gpl.html

		About:
		I searched a function to compare arrays and the array_diff()
		was not specific enough. It ignores the order of the array-values.
		So I reimplemented the diff-function which is found on unix-systems
		but this you can use directly in your code and adopt for your needs.
		Simply adopt the formatline-function. with the third-parameter of arr_diff()
		you can hide matching lines. Hope someone has use for this.

		Contact: d.u.diff@holomind.de <daniel unterberger>
	**/

	## PHPDiff returns the differences between $old and $new, formatted
	## in the standard diff(1) output format.

	static function PHPDiff($t1,$t2)
	{
		# split the source text into arrays of lines
		//$t1 = explode("\n",$old);
		$x=array_pop($t1);
		if ($x>'') $t1[]="$x\n\\ No newline at end of file";
		//$t2 = explode("\n",$new);
		$x=array_pop($t2);
		if ($x>'') $t2[]="$x\n\\ No newline at end of file";

		# build a reverse-index array using the line as key and line number as value
		# don't store blank lines, so they won't be targets of the shortest distance
		# search
		foreach($t1 as $i=>$x) if ($x>'') $r1[$x][]=$i;
		foreach($t2 as $i=>$x) if ($x>'') $r2[$x][]=$i;

		$a1=0; $a2=0;   # start at beginning of each list
		$actions=array();

		# walk this loop until we reach the end of one of the lists
		while ($a1<count($t1) && $a2<count($t2))
		{
			# if we have a common element, save it and go to the next
			if ($t1[$a1]==$t2[$a2]) { $actions[]=4; $a1++; $a2++; continue; }

			# otherwise, find the shortest move (Manhattan-distance) from the
			# current location
			$best1=count($t1); $best2=count($t2);
			$s1=$a1; $s2=$a2;
			while(($s1+$s2-$a1-$a2) < ($best1+$best2-$a1-$a2)) {
			$d=-1;
			foreach((array)@$r1[$t2[$s2]] as $n)
			if ($n>=$s1) { $d=$n; break; }
			if ($d>=$s1 && ($d+$s2-$a1-$a2)<($best1+$best2-$a1-$a2))
			{ $best1=$d; $best2=$s2; }
			$d=-1;
			foreach((array)@$r2[$t1[$s1]] as $n)
			if ($n>=$s2) { $d=$n; break; }
			if ($d>=$s2 && ($s1+$d-$a1-$a2)<($best1+$best2-$a1-$a2))
			{ $best1=$s1; $best2=$d; }
			$s1++; $s2++;
			}
			while ($a1<$best1) { $actions[]=1; $a1++; }  # deleted elements
			while ($a2<$best2) { $actions[]=2; $a2++; }  # added elements
		}

		# we've reached the end of one list, now walk to the end of the other
		while($a1<count($t1)) { $actions[]=1; $a1++; }  # deleted elements
		while($a2<count($t2)) { $actions[]=2; $a2++; }  # added elements

		# and this marks our ending point
		$actions[]=8;

		# now, let's follow the path we just took and report the added/deleted
		# elements into $out.
		$op = 0;
		$x0=$x1=0; $y0=$y1=0;
		$out1 = array();
		$out2 = array();
		foreach($actions as $act) {
			if ($act==1) { $op|=$act; $x1++; continue; }
			if ($act==2) { $op|=$act; $y1++; continue; }
			if ($op>0) {
				//$xstr = ($x1==($x0+1)) ? $x1 : ($x0+1).",$x1";
				//$ystr = ($y1==($y0+1)) ? $y1 : ($y0+1).",$y1";
				/*if ($op==1) $out[] = "{$xstr}d{$y1}";
				elseif ($op==3) $out[] = "{$xstr}c{$ystr}";*/
				while ($x0<$x1) { $out1[] = $x0; $x0++; }   # deleted elems
				/*if ($op==2) $out[] = "{$x1}a{$ystr}";
				elseif ($op==3) $out[] = '---';*/
				while ($y0<$y1) { $out2[] = $y0; $y0++; }   # added elems
			}
			$x1++; $x0=$x1;
			$y1++; $y0=$y1;
			$op=0;
		}
		//$out1[] = '';
		//$out2[] = '';
		return array($out1, $out2);
	}

	/*static function flexiHtmlDiff($old, $new, $mode=0)
	{
		$t1 = explode(" ",$old);
		$t2 = explode(" ",$new);
		$out = flexicontent_html::PHPDiff( $t1, $t2 );
		$html1 = array();
		$html2 = array();
		foreach($t1 as $k=>$o) {
			if(in_array($k, $out[0])) $html1[] = "<s>".($mode?htmlspecialchars($o, ENT_QUOTES):$o)."</s>";
			else $html1[] = ($mode?htmlspecialchars($o, ENT_QUOTES)."<br>":$o);
		}
		foreach($t2 as $k=>$n) {
			if(in_array($k, $out[1])) $html2[] = "<u>".($mode?htmlspecialchars($n, ENT_QUOTES):$n)."</u>";
			else $html2[] = ($mode?htmlspecialchars($n, ENT_QUOTES)."<br>":$n);
		}
		$html1 = implode(" ", $html1);
		$html2 = implode(" ", $html2);
		return array($html1, $html2);
	}*/

	static function flexiHtmlDiff($before, $after, $mode=0)
	{
		require_once (JPATH_SITE.'/components/com_flexicontent/librairies/phpdiff/diff.php');
		$diff = new fc_diff_class;
		$difference = new stdClass;
		$difference->mode = 'w';
		$difference->patch = true;
		$after_patch = new stdClass;
		if ($diff->FormatDiffAsHtml($before, $after, $difference) && $diff->Patch($before, $difference->difference, $after_patch))
		{
			//echo '<div>Difference</div><div class="frameResults">', $difference->html, '</div>';
			//echo '<div>Patch</div><div class="frameResults">', ($after === $after_patch->after ? 'OK: The patched text matches the text after.' : 'There is a BUG: The patched text (<strong>'.HtmlSpecialChars($after_patch->after).'</strong>) does not match the text after (<strong>'.HtmlSpecialChars($after).'</strong>).'), '</div>';
			return array(
				0 => ($mode ? htmlspecialchars($before, ENT_QUOTES, 'UTF-8') : $before),
				1 => ($mode ? htmlspecialchars($after, ENT_QUOTES, 'UTF-8') : $after),
				2 => ($mode ? $difference->html : htmlspecialchars_decode($difference->html)),
			);
		}
		else
		{
			echo '<div>Error: ', htmlspecialchars($diff->error), '</div>';
			return array(0 => $before . '<br>' . $diff->error, $after);
		}
	}


	/**
	 * Method to retrieve mappings of CORE fields (Names to Types and reverse)
	 *
	 * @return object
	 * @since 1.5
	 */
	static function getJCoreFields($ffield=NULL, $map_maintext_to_introtext=false, $reverse=false) {
		if(!$reverse)  // MAPPING core fields NAMEs => core field TYPEs
		{
			$flexifield = array(
				'title'=>'title',
				'categories'=>'categories',
				'tags'=>'tags',
				'text'=>'maintext',
				'created'=>'created',
				'created_by'=>'createdby',
				'modified'=>'modified',
				'modified_by'=>'modifiedby',
				'hits'=>'hits',
				'document_type'=>'type',
				'version'=>'version',
				'state'=>'state'
			);
			if ($map_maintext_to_introtext)
			{
				$flexifield['introtext'] = 'maintext';
			}
		}
		else    // MAPPING core field TYPEs => core fields NAMEs
		{
			$flexifield = array(
				'title'=>'title',
				'categories'=>'categories',
				'tags'=>'tags',
				'maintext'=>'text',
				'created'=>'created',
				'createdby'=>'created_by',
				'modified'=>'modified',
				'modifiedby'=>'modified_by',
				'hits'=>'hits',
				'type'=>'document_type',
				'version'=>'version',
				'state'=>'state'
			);
			if ($map_maintext_to_introtext)
			{
				$flexifield['maintext'] = 'introtext';
			}
		}
		if($ffield===NULL) return $flexifield;
		return isset($flexifield[$ffield])?$flexifield[$ffield]:NULL;
	}

	static function getFlexiFieldId($jfield=NULL) {
		$flexifields = array(
			'introtext'=>1,
			'text'=>1,
			'created'=>2,
			'created_by'=>3,
			'modified'=>4,
			'modified_by'=>5,
			'title'=>6,
			'hits'=>7,
			'version'=>9,
			'state'=>10,
			'catid'=>13,
		);
		if($jfield===NULL) return $flexifields;
		return isset($flexifields[$jfield])?$flexifields[$jfield]:0;
	}

	static function getFlexiField($jfield=NULL) {
		$flexifields = array(
			'introtext'=>'text',
			'fulltext'=>'text',
			'created'=>'created',
			'created_by'=>'createdby',
			'modified'=>'modified',
			'modified_by'=>'modifiedby',
			'title'=>'title',
			'hits'=>'hits',
			'version'=>'version',
			'state'=>'state'
		);
		if($jfield===NULL) return $flexifields;
		return isset($flexifields[$jfield])?$flexifields[$jfield]:0;
	}


	/**
	 * Method to get types list e.g. when performing an edit action optionally checking 'create' ACCESS for the types
	 *
	 * @return array
	 * @since 1.5
	 */
	static function getTypeslist ( $type_ids=false, $check_perms = false, $published=true )
	{
		// Return cached result
		static $all_types;
		if ( empty( $type_ids ) && isset( $all_types[$check_perms][$published] ) )   return $all_types[$check_perms][$published];

		// Custom type_ids array given, do the query
		$type_ids_list = false;
		if ( !empty($type_ids) && is_array($type_ids) )
		{
			$type_ids = ArrayHelper::toInteger($type_ids);
			$type_ids_list = implode(',', $type_ids);
		}

		$where = array();
		if ($published)
			$where[] = 'published = 1';
		if ($type_ids_list)
			$where[] = 'id IN ('. $type_ids_list .' ) ';

		$db = JFactory::getDbo();
		$query = 'SELECT * '
				. ' FROM #__flexicontent_types'
				. ($where ? ' WHERE ' . implode(' AND ', $where) : '')
				. ' ORDER BY name ASC';
		$db->setQuery($query);
		$types = $db->loadObjectList('id');
		if ($check_perms)
		{
			$user = JFactory::getUser();
			$_types = array();
			foreach ($types as $type_id => $type) {
				$allowed = ! $type->itemscreatable || $user->authorise('core.create', 'com_flexicontent.type.' . $type->id);
				if ( $allowed ) $_types[$type_id] = $type;
			}
			$types = $_types;
		}
		if (!$published) {
			foreach ($types as $type_id => $type) {
				if ( !$type->published ) $types[$type_id]->name .= ' -U-';
			}
		}

		// Cache function result
		if ( empty($type_ids ) )  $all_types[$check_perms][$published] = $types;
		return $types;
	}


	/**
	 * Displays a list of the available access view levels
	 *
	 * @param	string	The form field name.
	 * @param	string	The name of the selected section.
	 * @param	string	Additional attributes to add to the select field.
	 * @param	mixed	True to add "All Sections" option or and array of option
	 * @param	string	The form field id
	 *
	 * @return	string	The required HTML for the SELECT tag.
	 */
	static function userlevel($name, $selected, $attribs = '', $extra_options = true, $id = false, $createlist = true)
	{
		static $options;
		if (!$options)
		{
			$db		= JFactory::getDbo();
			$query	= $db->getQuery(true);
			$query->select('a.id AS value, a.title AS text');
			$query->from('#__viewlevels AS a');
			if (!$createlist)
			{
				$query->where('a.id="'.$selected.'"');
			}
			$query->group('a.id');
			$query->order('a.ordering ASC');
			$query->order('`title` ASC');

			// Get the options.
			$options = $db->setQuery($query)->loadObjectList('value');

			// Return ACCESS LEVEL NAME
			if (!$createlist)
			{
				$_option = reset($options);
				return $_option->text;
			}

			// If params is an array, push these options to the array
			if (is_array($extra_options))
			{
				$options = array_merge($extra_options, $options);
			}
			// If all levels is allowed, push it into the array.
			elseif ($extra_options)
			{
				//array_unshift($options, JHtml::_('select.option', '', JText::_('JOPTION_ACCESS_SHOW_ALL_LEVELS')));
			}
		}

		$ops = $options;

		$selected_arr = is_array($selected) ? $selected : array($selected);
		foreach($selected_arr as $sel)
		{
			if ( !isset($ops[$sel]) ) $ops[] = (object) array('value'=>$sel, 'text'=>$sel);
		}

		return JHtml::_('select.genericlist', $ops, $name,
			array(
				'list.attr' => $attribs,
				'list.select' => $selected,
				'id' => $id
			)
		);
	}


	/*
	 * Method to create a Tabset for given label-html arrays
	 * param  string			$date
	 * return boolean			true if valid date, false otherwise
	 */
	static function createFieldTabber( &$field_html, &$field_tab_labels, $class )
	{
		$not_in_tabs = "";

		$output = "<!-- tabber start --><div class='fctabber ".$class."'>"."\n";

		foreach ($field_html as $i => $html) {
			// Hide field when it has no label, and skip creating tab
			$no_label = ! isset( $field_tab_labels[$i] );
			$not_in_tabs .= $no_label ? "<div style='display:none!important'>".$field_html[$i]."</div>" : "";
			if ( $no_label ) continue;

			$output .= "	<div class='tabbertab'>"."\n";
			$output .= "		<h3 class='tabberheading'>".$field_tab_labels[$i]."</h3>"."\n";   // Current TAB LABEL
			$output .= "		".$not_in_tabs."\n";                        // Output hidden fields (no tab created), by placing them inside the next appearing tab
			$output .= "		".$field_html[$i]."\n";                     // Current TAB CONTENTS
			$output .= "	</div>"."\n";

			$not_in_tabs = "";     // Clear the hidden fields variable
		}
		$output .= "</div><!-- tabber end -->";
		$output .= $not_in_tabs;      // Output ENDING hidden fields, by placing them outside the tabbing area
		return $output;
	}


	// * Create a spacer inside Joomla Toolbar
	static function spacer($width = 32)
	{
		static $i = 0;

		$btn_name = 'fc_toolbar_spacer_' . ($i++);
		$toolbar = JToolbar::getInstance('toolbar');
		$toolbar->appendButton('Custom', '<span style="width: ' . (int) $width. 'px; height: 1px; display: inline-block;"></span>', $btn_name);
	}


	// * Create a custom button inside Joomla Toolbar
	static function addToolBarButton(
		$text='Button Text', $btn_name='btnname', $full_js='', $err_msg='', $confirm_msg='', $task='btntask', $extra_js='',
		$list=true, $menu=true, $confirm=true, $btn_class="", $btn_icon="", $attrs='', $auto_add = true, $tag_type='a',
		$tbname = 'toolbar'
	)
	{
		$toolbar = JToolbar::getInstance($tbname);
		$text  = JText::_($text);
		$class = $btn_icon ? $btn_icon : 'icon-32-'.$btn_name;
		$btn_sm_class = FLEXI_J40GE ? 'btn btn-sm' : 'btn btn-small';

		if ( !$full_js )
		{
			$err_msg = $err_msg ? $err_msg : JText::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', $btn_name);
			$err_msg = flexicontent_html::escapeJsText(strip_tags($err_msg), "d");
			$confirm_msg = $confirm_msg ? $confirm_msg : JText::_('FLEXI_ARE_YOU_SURE');
			$confirm_msg = flexicontent_html::escapeJsText(strip_tags($confirm_msg), "d");

			$full_js = $extra_js ."; Joomla.submitbutton('$task');";
			if ($confirm)
			{
				$full_js = "if (confirm('".$confirm_msg."')) { ".$full_js." }";
			}
			if (!$menu)
			{
				$full_js = "hideMainMenu(); " . $full_js;
			}
		}

		if ($list)
		{
			$full_js = "if (document.adminForm.boxchecked.value==0) { alert('".$err_msg."') ;} else { ".$full_js." }";
		}

		$full_js = "javascript: $full_js";

		$button_html	= '
		<'.$tag_type.' '.($tag_type=='a' ? 'href="javascript:;"' : '').'
			onclick="'.htmlspecialchars($full_js, ENT_QUOTES, 'UTF-8').'"
			class="toolbar ' . $btn_sm_class . ' ' . $btn_class . '"
			' . $attrs . '
		>
			<span class="'.$class.'" title="'.htmlspecialchars($text, ENT_QUOTES, 'UTF-8').'"></span>
			'.$text.'
		</'.$tag_type.'>';

		if (!$auto_add)
		{
			return $button_html;
		}
		$toolbar->appendButton('Custom', $button_html, $btn_name);
	}


	// * Create a drop down button menu inside Joomla Toolbar
	static function addToolBarDropMenu($btn_arr, $btn_group_name, $drop_btn = null, $ops = array(), $tbname = 'toolbar')
	{
		JHtml::_('bootstrap.dropdown');
		$toolbar = JToolbar::getInstance($tbname);
		static $btngroup_id = 0;
		$btngroup_id++;

		if (count($btn_arr) < 2 || !empty($ops['add_inline']))
		{
			if (count($btn_arr) === 1 || !empty($ops['add_inline']))
			{
				foreach ($btn_arr as $btn_name => $btn_html)
				{
					$btn_html = str_replace('_DDI_class_', '', $btn_html);
					$toolbar->appendButton('Custom', $btn_html, $btn_name);
				}
			}

			return;
		}

		if (!empty($ops['drop_class_full']))
		{
			$drop_btn_class = $ops['drop_class_full'];
		}
		else
		{
			$drop_btn_class =
				(FLEXI_J40GE
					? 'btn btn-sm toolbar dropdown-toggle dropdown-toggle-split'
					: 'btn btn-small toolbar dropdown-toggle'
				) .
				(!empty($ops['drop_class_extra'])
					? ' ' . $ops['drop_class_extra']
					: ''
				);
		}
		
		if ($drop_btn)
		{
			$drop_btn = str_replace('<button ', '<button id="btngroup_id_' . $btngroup_id . '" ', $drop_btn);
		}

		$buttons_html = '
			<div class="buttons btn-group' . (FLEXI_J40GE ? ' dropdown' : '') . '">
				'.array_shift($btn_arr).'
			  '.($drop_btn ?: '
			  <button class="' . $drop_btn_class . '"
					' . (FLEXI_J40GE ? ' data-bs-toggle="dropdown" ' : ' data-toggle="dropdown" ') .'
					id="btngroup_id_' . $btngroup_id . '" aria-expanded="false"
				>
			    <span class="caret"></span>
			  </button>').'
				<ul class="dropdown-menu dropdown-menu-right" role="menu" aria-labelledby="btngroup_id_' . $btngroup_id . '">
					<li>' . implode("</li>\n<li>", $btn_arr) . '</li>
				</ul>
			</div>';

		// Add drop-down class to the items
		$buttons_html = str_replace('_DDI_class_', 'dropdown-item', $buttons_html);

		$toolbar->appendButton(
			'Custom',
			$buttons_html,
			$btn_group_name
		);
	}


	// ************************************************************************
	// Calculate CSS classes needed to add special styling markups to the items
	// ************************************************************************
	static function	calculateItemMarkups($items, $params)
	{
		global $globalcats;
		global $globalnoroute;
		$globalnoroute = !is_array($globalnoroute) ? array() : $globalnoroute;

		$db   = JFactory::getDbo();
		$user = JFactory::getUser();
		$aids = JAccess::getAuthorisedViewLevels($user->id);


		// **************************************
		// Get configuration about markups to add
		// **************************************

		// Get addcss parameters
		$mu_addcss_cats = $params->get('mu_addcss_cats', array('featured'));
		$mu_addcss_cats = FLEXIUtilities::paramToArray($mu_addcss_cats);
		$mu_addcss_acclvl = $params->get('mu_addcss_acclvl', array('needed_acc', 'obtained_acc'));
		$mu_addcss_acclvl = FLEXIUtilities::paramToArray($mu_addcss_acclvl);
		$mu_addcss_radded   = $params->get('mu_addcss_radded', 0);
		$mu_addcss_rupdated = $params->get('mu_addcss_rupdated', 0);

		// Calculate addcss flags
		$add_featured_cats = in_array('featured', $mu_addcss_cats);
		$add_other_cats    = in_array('other', $mu_addcss_cats);
		$add_no_acc        = in_array('no_acc', $mu_addcss_acclvl);
		$add_free_acc      = in_array('free_acc', $mu_addcss_acclvl);
		$add_needed_acc    = in_array('needed_acc', $mu_addcss_acclvl);
		$add_obtained_acc  = in_array('obtained_acc', $mu_addcss_acclvl);

		// Get addtext parameters
		$mu_addtext_cats   = $params->get('mu_addtext_cats', 1);
		$mu_addtext_acclvl = $params->get('mu_addtext_acclvl', array('no_acc', 'free_acc', 'needed_acc', 'obtained_acc'));
		$mu_addtext_acclvl = FLEXIUtilities::paramToArray($mu_addtext_acclvl);
		$mu_addtext_radded   = $params->get('mu_addtext_radded', 1);
		$mu_addtext_rupdated = $params->get('mu_addtext_rupdated', 1);

		// Calculate addtext flags
		$add_txt_no_acc       = in_array('no_acc', $mu_addtext_acclvl);
		$add_txt_free_acc     = in_array('free_acc', $mu_addtext_acclvl);
		$add_txt_needed_acc   = in_array('needed_acc', $mu_addtext_acclvl);
		$add_txt_obtained_acc = in_array('obtained_acc', $mu_addtext_acclvl);

		$mu_add_condition_obtainded_acc = $params->get('mu_add_condition_obtainded_acc', 1);

		$mu_no_acc_text   = JText::_( $params->get('mu_no_acc_text',   'FLEXI_MU_NO_ACC') );
		$mu_free_acc_text = JText::_( $params->get('mu_free_acc_text', 'FLEXI_MU_NO_ACC') );


		// *******************************
		// Prepare data needed for markups
		// *******************************

		// a. Get Featured categories and language filter their titles
		$featured_cats_parent = $params->get('featured_cats_parent', 0);
		$disabled_cats = $params->get('featured_cats_parent_disable', 1) ? array($featured_cats_parent) : array();
		$featured_cats = array();
		if ( $add_featured_cats && $featured_cats_parent )
		{
			$where[] = isset($globalcats[$featured_cats_parent])  ?
				'id IN (' . $globalcats[$featured_cats_parent]->descendants . ')' :
				'parent_id = '. $featured_cats_parent
				;
			if (!empty($disabled_cats)) $where[] = 'id NOT IN (' . implode(", ", $disabled_cats) . ')';  // optionally exclude category root of featured subtree
			$query = 'SELECT c.id'
				. ' FROM #__categories AS c'
				. (count($where) ? ' WHERE ' . implode( ' AND ', $where ) : '')
				;
			$db->setQuery($query);

			$featured_cats = $db->loadColumn();
			$featured_cats = $featured_cats ? array_flip($featured_cats) : array();

			foreach ($featured_cats as $featured_cat => $i)
			{
				$featured_cats_titles[$featured_cat] = JText::_($globalcats[$featured_cat]->title);
			}
		}


		// b. Get Access Level names (language filter them)
		if ( $add_needed_acc || $add_obtained_acc )
		{
			$access_names = flexicontent_db::getAccessNames();
		}


		// c. Calculate creation time intervals
		if ( $mu_addcss_radded )
		{
			$nowdate_secs = time();
			$ra_timeframes = trim($params->get('mu_ra_timeframe_intervals', '24h,2d,7d,1m,3m,1y,3y'));
			$ra_timeframes = preg_split("/\s*,\s*/u", $ra_timeframes);

			$ra_names = trim($params->get('mu_ra_timeframe_names', 'FLEXI_24H_RA , FLEXI_2D_RA , FLEXI_7D_RA , FLEXI_1M_RA , FLEXI_3M_RA , FLEXI_1Y_RA , FLEXI_3Y_RA'));
			$ra_names = preg_split("/\s*,\s*/u", $ra_names);

			$unit_hour_map = array('h'=>1, 'd'=>24, 'm'=>24*30, 'y'=>24*365);
			$unit_word_map = array('h'=>'hours', 'd'=>'days', 'm'=>'months', 'y'=>'years');
			$unit_text_map = array(
				'h'=>'FLEXI_MU_HOURS', 'd'=>'FLEXI_MU_DAYS', 'm'=>'FLEXI_MU_MONTHS', 'y'=>'FLEXI_MU_YEARS'
			);
			foreach($ra_timeframes as $i => $timeframe) {
				$unit = substr($timeframe, -1);
				if ( !isset($unit_hour_map[$unit]) ) {
					echo "Improper timeframe ': ".$timeframe."' for recently added content, please fix in configuration";
					continue;
				}
				$timeframe  = (int) $timeframe;
				$ra_css_classes[$i] = '_item_added_within_' . $timeframe . $unit_word_map[$unit];
				$ra_timeframe_secs[$i] = $timeframe * $unit_hour_map[$unit] * 3600;
				$ra_timeframe_text[$i] = @ $ra_names[$i] ? JText::_($ra_names[$i]) : JText::_('FLEXI_MU_ADDED') . JText::sprintf($unit_text_map[$unit], $timeframe);
			}
		}


		// d. Calculate updated time intervals
		if ( $mu_addcss_rupdated )
		{
			$nowdate_secs = time();
			$ru_timeframes = trim($params->get('mu_ru_timeframe_intervals', '24h,2d,7d,1m,3m,1y,3y'));
			$ru_timeframes = preg_split("/\s*,\s*/u", $ru_timeframes);

			$ru_names = trim($params->get('mu_ru_timeframe_names', 'FLEXI_24H_RU , FLEXI_2D_RU , FLEXI_7D_RU , FLEXI_1M_RU , FLEXI_3M_RU , FLEXI_1Y_RU , FLEXI_3Y_RU'));
			$ru_names = preg_split("/\s*,\s*/u", $ru_names);

			$unit_hour_map = array('h'=>1, 'd'=>24, 'm'=>24*30, 'y'=>24*365);
			$unit_word_map = array('h'=>'hours', 'd'=>'days', 'm'=>'months', 'y'=>'years');
			$unit_text_map = array(
				'h'=>'FLEXI_MU_HOURS', 'd'=>'FLEXI_MU_DAYS', 'm'=>'FLEXI_MU_MONTHS', 'y'=>'FLEXI_MU_YEARS'
			);
			foreach($ru_timeframes as $i => $timeframe) {
				$unit = substr($timeframe, -1);
				if ( !isset($unit_hour_map[$unit]) ) {
					echo "Improper timeframe ': ".$timeframe."' for recently updated content, please fix in configuration";
					continue;
				}
				$timeframe  = (int) $timeframe;
				$ru_css_classes[$i] = '_item_updated_within_' . $timeframe . $unit_word_map[$unit];
				$ru_timeframe_secs[$i] = $timeframe * $unit_hour_map[$unit] * 3600;
				$ru_timeframe_text[$i] = @ $ru_names[$i] ? JText::_($ru_names[$i]) : JText::_('FLEXI_MU_UPDATED') . JText::sprintf($unit_text_map[$unit], $timeframe);
			}
		}


		// **********************************
		// Create CSS markup classes per item
		// **********************************
		$public_acclvl = 1;
		foreach ($items as $item)
		{
			$item->css_markups = array();
			//$item->categories = isset($item->categories) ? $item->categories : array();

			// Category markups
			if ( $add_featured_cats || $add_other_cats ) foreach ($item->categories as $item_cat) {
				$is_featured_cat = isset( $featured_cats[$item_cat->id] );

				if ( $is_featured_cat && !$add_featured_cats  ) continue;   // not adding featured cats
				if ( !$is_featured_cat && !$add_other_cats  )   continue;   // not adding other cats
				if ( in_array($item_cat->id, $globalnoroute) )	continue;   // non-linkable/routable 'special' category

				$item->css_markups['itemcats'][] = '_itemcat_'.$item_cat->id;
				$item->ecss_markups['itemcats'][] = ($is_featured_cat ? ' mu_featured_cat' : ' mu_normal_cat') . ($mu_addtext_cats ? ' mu_has_text' : '');
				$item->title_markups['itemcats'][] = $mu_addtext_cats  ?  ($is_featured_cat ? $featured_cats_titles[$item_cat->id] : (isset($globalcats[$item_cat->id]) ? $globalcats[$item_cat->id]->title : ''))  :  '';
			}


			// recently-added Timeframe markups
			if ($mu_addcss_radded) {
				$item_timeframe_secs = $nowdate_secs - strtotime($item->created);
				$mr = -1;

				foreach($ra_timeframe_secs as $i => $timeframe_secs) {
					// Check if item creation time has surpassed this time frame
					if ( $item_timeframe_secs > $timeframe_secs) continue;

					// Check if this time frame is more recent than the best one found so far
					if ($mr != -1 && $timeframe_secs > $ra_timeframe_secs[$mr]) continue;

					// Use current time frame
					$mr = $i;
				}
				if ($mr >= 0) {
					$item->css_markups['timeframe'][] = $ra_css_classes[$mr];
					$item->ecss_markups['timeframe'][] = ' mu_ra_timeframe' . ($mu_addtext_radded ? ' mu_has_text' : '');
					$item->title_markups['timeframe'][] = $mu_addtext_radded ? $ra_timeframe_text[$mr] : '';
				}
			}


			// recently-updated Timeframe markups
			if ($mu_addcss_rupdated) {
				$item_timeframe_secs = $nowdate_secs - strtotime($item->modified);
				$mr = -1;

				foreach($ru_timeframe_secs as $i => $timeframe_secs) {
					// Check if item creation time has surpassed this time frame
					if ( $item_timeframe_secs > $timeframe_secs) continue;

					// Check if this time frame is more recent than the best one found so far
					if ($mr != -1 && $timeframe_secs > $ru_timeframe_secs[$mr]) continue;

					// Use current time frame
					$mr = $i;
				}
				if ($mr >= 0) {
					$item->css_markups['timeframe'][] = $ru_css_classes[$mr];
					$item->ecss_markups['timeframe'][] = ' mu_ru_timeframe' . ($mu_addtext_rupdated ? ' mu_has_text' : '');
					$item->title_markups['timeframe'][] = $mu_addtext_rupdated ? $ru_timeframe_text[$mr] : '';
				}
			}


			// Get item's access levels if this is needed
			if ($add_free_acc || $add_needed_acc || $add_obtained_acc) {
				$all_acc_lvls = array();
				$all_acc_lvls[] = $item->access;
				$all_acc_lvls[] = $item->category_access;
				$all_acc_lvls[] = $item->type_access;
				$all_acc_lvls = array_unique($all_acc_lvls);
			}


			// No access markup
			if ($add_no_acc && !$item->has_access) {
				$item->css_markups['access'][]   = '_item_no_access';
				$item->ecss_markups['access'][] =  ($add_txt_no_acc ? ' mu_has_text' : '');
				$item->title_markups['access'][] = $add_txt_no_acc ? $mu_no_acc_text : '';
			}


			// Free access markup, Add ONLY if item has a single access level the public one ...
			if ( $add_free_acc && $item->has_access && count($all_acc_lvls)==1 && $public_acclvl == reset($all_acc_lvls) )
			{
				$item->css_markups['access'][]   = '_item_free_access';
				$item->ecss_markups['access'][]  = $add_txt_free_acc ? ' mu_has_text' : '';
				$item->title_markups['access'][] = $add_txt_free_acc ? $mu_free_acc_text : '';
			}


			// Needed / Obtained access levels markups
			if ($add_needed_acc || $add_obtained_acc)
			{
				foreach($all_acc_lvls as $all_acc_lvl)
				{
					if ($public_acclvl == $all_acc_lvl) continue;  // handled separately above

					$has_acclvl = in_array($all_acc_lvl, $aids);
					if (!$has_acclvl) {
						if (!$add_needed_acc) continue;   // not adding needed levels
						$item->css_markups['access'][] = '_acclvl_'.$all_acc_lvl;
						$item->ecss_markups['access'][] = ' mu_needed_acclvl' . ($add_txt_needed_acc ? ' mu_has_text' : '');
						$item->title_markups['access'][] = $add_txt_needed_acc ? $access_names[$all_acc_lvl] : '';
					} else {
						if (!$add_obtained_acc) continue; // not adding obtained levels
						if ($mu_add_condition_obtainded_acc==0 && !$item->has_access) continue;  // do not add obtained level markups if item is inaccessible
						$item->css_markups['access'][] = '_acclvl_'.$all_acc_lvl;
						$item->ecss_markups['access'][] = ' mu_obtained_acclvl' . ($add_txt_obtained_acc ? ' mu_has_text' : '');
						$item->title_markups['access'][] = $add_txt_obtained_acc ? $access_names[$all_acc_lvl] : '';
					}
				}
			}
		}
	}

	static function createCatLink($slug, &$non_sef_link, $catmodel=null)
	{
		$menus  = JFactory::getApplication()->getMenu();
		$menu   = $menus->getActive();
		$Itemid = $menu ? $menu->id : 0;

		// Get URL variables
		$urlvars = flexicontent_html::getCatViewLayoutVars($catmodel, $use_slug = true);

		// Category link for single/multiple category(-ies)  --OR--  "current layout" link for myitems/author/favs/tags layouts
		$non_sef_link = FlexicontentHelperRoute::getCategoryRoute($slug, $Itemid, $urlvars);
		$category_link = JRoute::_($non_sef_link);

		return $category_link;
	}


	static function getCatViewLayoutVars($obj=null, $use_slug = false)
	{
		static $_layout_vars = array();
		if (isset($_layout_vars[$use_slug]))
		{
			return $_layout_vars[$use_slug];
		}

		// Get URL variables
		$app = JFactory::getApplication();
		$layout_vars = array();

		$layout_vars['layout']   = $obj && isset($obj->_layout) ? $obj->_layout : $app->input->get('layout', '', 'CMD');

		if (!$use_slug)
		{
			$layout_vars['cid']      = $obj && isset($obj->_id) ? $obj->_id : $app->input->get('cid', 0, 'INT');
			$layout_vars['authorid'] = $obj && isset($obj->_authorid) ? $obj->_authorid : $app->input->get('authorid', 0, 'INT');
			$layout_vars['tagid']    = $obj && isset($obj->_tagid) ? $obj->_tagid : $app->input->get('tagid', 0, 'INT');
		}
		else
		{
			$layout_vars['cid']      = $obj && isset($obj->_id) ? $obj->_id : $app->input->get('cid', 0, 'STRING');
			$layout_vars['authorid'] = $obj && isset($obj->_authorid_slug) ? $obj->_authorid_slug : $app->input->get('authorid', 0, 'STRING');
			$layout_vars['tagid']    = $obj && isset($obj->_tagid_slug) ? $obj->_tagid_slug : $app->input->get('tagid', 0, 'STRING');
		}

		if ($obj && isset($obj->_ids))
		{
			$layout_vars['cids'] = !is_array($obj->_ids)
				? $obj->_ids
				: implode(',' , $obj->_ids);
		}
		else
		{
			$cids = $app->input->get('cids', array(), 'array');

			if (!is_array($cids))
			{
				$cids = preg_replace( '/[^0-9,]/i', '', (string) $cids );
				$cids = explode(',', $cids);
			}

			$cids = ArrayHelper::toInteger($cids);
			$layout_vars['cids'] = implode(',' , $cids);
		}

		$_layout_vars[$use_slug] = $layout_vars;
		return $layout_vars;
	}

	static function getCatViewFilterVars($obj=null)
	{
		$app = JFactory::getApplication();
		$jinput = $app->input;

		$filters_count = 0;
		$filters_string = '';

		foreach($jinput->get->get->getArray() as $i => $v)
		{
			if (substr($i, 0, 6) !== "filter")
			{
				continue;
			}

			if (is_array($v))
			{
				foreach($v as $ii => &$vv)
				{
					if (is_array($vv))
					{
						foreach($vv as $iii => &$vvv)
						{
							$filters_string .= '&' . $i . '[' . $ii . '][' . $iii . ']=' . $vvv;
						}
					}
					else
					{
						$filters_string .= '&' . $i . '[' . $ii . ']=' . $vv;
					}
				}
			}
			else
			{
				$filters_string .= '&' . $i . '=' . $v;
			}

			$filters_count++;
		}
		return $filters_string;
	}

	// Get Prefix - Suffix - Separator parameters and other common parameters
	static function getDateFieldDisplay($field_parameters, $date = '', $pfx = '')
	{
		// Some variables
		$config = JFactory::getConfig();
		$user = JFactory::getUser();

		// Timezone configuration
		$date_allowtime = $field_parameters->get( $pfx.'date_allowtime', 1 ) ;
		$use_editor_tz  = $field_parameters->get( $pfx.'use_editor_tz', 0 ) ;
		$use_editor_tz  = $date_allowtime ? $use_editor_tz : 0;  // Timezone IS disabled, if time usage is disabled
		$customdate     = $field_parameters->get( $pfx.'custom_date', 'DATE_FORMAT_LC2' ) ;
		$dateformat     = $field_parameters->get( $pfx.'date_format', '' ) ;
		$dateformat = $dateformat && $dateformat != '_custom_' ? JText::_($dateformat) :
			($field_parameters->get( $pfx.'lang_filter_format', 0) ? JText::_($customdate) : $customdate);

		$display_tz_logged   = $field_parameters->get( $pfx.'display_tz_logged', 2) ;
		$display_tz_guests   = $field_parameters->get( $pfx.'display_tz_guests', 2) ;
		$display_tz_suffix   = $field_parameters->get( $pfx.'display_tz_suffix', 1) ;

		// Get timezone to use for displaying the date,  this is a string for J2.5 and an (offset) number for J1.5
		if ($user->id) {
			$tz_suffix_type = $display_tz_logged;
		} else {
			$tz_suffix_type = $display_tz_guests;
		}

		// Decide the timezone to use
		$tz_info = '';
		switch ($tz_suffix_type)
		{
			default: // including value -1 for raw for output, see above
			case 0:
				$timezone = 'UTC';
				break;
			case 1:
				$timezone = 'UTC';   // ' UTC+0'
				break;
			case 2:
				$timezone = $config->get('offset');  // Site's timezone
				break;
			case 3:
				$timezone = $user->getParam('timezone' );  // User's local time
				break;
		}

		// Display timezone suffix if this is enabled
		if ($display_tz_suffix && $tz_suffix_type > 0)
		{
			$tz = new DateTimeZone($timezone);
			$tz_offset = $tz->getOffset(new JDate()) / 3600;
			$tz_info =  $tz_offset > 0 ? ' UTC +'.$tz_offset : ' UTC '.$tz_offset;
		}

		// Return date
		try {
			return JHtml::_('date', $date, $dateformat, $timezone ) . $tz_info;
		} catch ( Exception $e ) {
			return '';
		}
	}
	
	
	// Get site template full, or name only
	public static function getSiteTemplate($full_path = false)
	{
		static $site_template, $site_template_full_path;

		if ($site_template === null)
		{
			// This does not work in backend because JPATH_THEMES is set to backend folder and folder exists check fails ...
			//$site_template = CMSApplication::getInstance('site')->getTemplate();

			$app = JFactory::getApplication();
			$db  = JFactory::getDbo();
			$site_template = $app->isClient('site')
				? $app->getTemplate()
				: $db->setQuery('SELECT template FROM #__template_styles WHERE client_id = 0 AND home = 1')->loadResult();
			$site_template_full_path = JPath::clean(JPATH_SITE . '/templates/' . $site_template);
		}

		return $full_path ? $site_template_full_path : $site_template;
	}

	// Get SEF url regardless of being in backend / frontend
	public static function getSefUrl($url, $xhtml = true, $ssl = null)
	{
		static $site_router, $site_instance, $isAdmin, $isSH404SEF, $useSiteApp;
		
		/**
		 * J4 case
		 */

		if (FLEXI_J40GE)
		{
			// This also works in J4 
			// return $url = JRoute::link('site', $url);

			$router = Router::getInstance('site');  // ALTERNATIVE: CMSApplication::getInstance('site')->getRouter('site');
			$url = $router->build($url);
			$url = $url->toString();

			return $url;
		}

		
		/**
		 * J3 case
		 */

		// Get frontend route instance if we are in the backend and SH404SEF is not installed
		if ($site_router === null)
		{
			$isAdmin    = JFactory::getApplication()->isClient('administrator');
			$isSH404SEF = defined('SH404SEF_IS_RUNNING') && JFactory::getConfig()->get('sef');
			$useSiteApp = $isAdmin; // && $isSH404SEF;

			$site_instance = $useSiteApp
				? JApplication::getInstance('site')   // In J4 use CMSApplication::getInstance('site')
				: JFactory::getApplication();
			$site_router = $site_instance->getRouter('site');
		}

		if (!is_array($url) && (strpos($url, '&') !== 0) && (strpos($url, 'index.php') !== 0))
		{
			return $url;
		}

		// Build route
		$uri = $site_router->build($url);

		$scheme = array('path', 'query', 'fragment');

		/*
		 * Get the secure/unsecure URLs.
		 *
		 * If the first 5 characters of the BASE are 'https', then we are on an ssl connection over
		 * https and need to set our secure URL to the current request URL, if not, and the scheme is
		 * 'http', then we need to do a quick string manipulation to switch schemes.
		 */
		if ((int) $ssl || $uri->isSsl())
		{
			static $host_port;

			if (!is_array($host_port))
			{
				$uri2 = JUri::getInstance();
				$host_port = array($uri2->getHost(), $uri2->getPort());
			}

			// Determine which scheme we want.
			$uri->setScheme(((int) $ssl === 1 || $uri->isSsl()) ? 'https' : 'http');
			$uri->setHost($host_port[0]);
			$uri->setPort($host_port[1]);
			$scheme = array_merge($scheme, array('host', 'port', 'scheme'));
		}

		$url = $uri->toString($scheme);

		// Replace spaces.
		$url = preg_replace('/\s/u', '%20', $url);

		if ($xhtml)
		{
			$url = htmlspecialchars($url, ENT_COMPAT, 'UTF-8');
		}

		/**
		 * Needed in older Joomla versions like J3.7 (and maybe for J3.8, J3.9). Check if we are in the backend again
		 * and remove '/administrator' from URL as it is added even though we've set the application to the site app
		 */
		if ($useSiteApp)
		{
			$url = str_replace(JUri::base(true), JUri::root(true), $url);
		}

		return $url;
	}


	// Check and if needed install Joomla template overrides into current Joomla template
	public static function install_template_overrides($display_mssg = false)
	{
		jimport('joomla.filesystem.path' );
		jimport('joomla.filesystem.folder');
		jimport('joomla.filesystem.file');
		$app = JFactory::getApplication();

		$pathDestFolder_arr = array(
			JPath::clean(JPATH_BASE.'/templates/'.$app->getTemplate().'/html/com_media/images/'),
			JPath::clean(JPATH_BASE.'/templates/'.$app->getTemplate().'/html/com_media/imageslist/')
		);
		$pathSourceFolder_arr = array(
			JPath::clean(JPATH_ROOT.'/components/com_flexicontent/layouts/html/com_media/images'),
			JPath::clean(JPATH_ROOT.'/components/com_flexicontent/layouts/html/com_media/imageslist')
		);

		$install_count = $update_count = 0;

		foreach($pathDestFolder_arr as $i => $pathDestFolder)
		{
			$pathSourceFolder = $pathSourceFolder_arr[$i];

			// 1. Check DESTINATION folder
			if ( !JFolder::exists($pathDestFolder) && !JFolder::create($pathDestFolder) )
			{
				echo '<span class="alert alert-warning"> Error, unable to create folder: '. $pathDestFolder.'</span>';
			}

			// 2. Copy override files
			$files = glob($pathSourceFolder . '/*.*');

			foreach($files as $sourcepath)
			{
				$dest_path = $pathDestFolder . basename($sourcepath);

				$not_exists = !JFile::exists($dest_path);
				if ($not_exists || filemtime($sourcepath) > filemtime($dest_path))
				{
					$not_exists ? $install_count++ : $update_count++;
					copy($sourcepath, $dest_path);
				}
			}
		}

		if ($display_mssg)
		{
			if ($install_count)
			{
				JFactory::getApplication()->enqueueMessage('<span class="badge">' . JText::_('FLEXI_INSTALLED') . '</span> ' . $install_count . ' template overrides', 'message');
			}

			if ($display_mssg && $update_count)
			{
				JFactory::getApplication()->enqueueMessage('<span class="badge">' . JText::_('FLEXI_UPDATED') . '</span> ' . $update_count . ' template overrides', 'message');
			}
		}
	}


	/**
	 * Method to diplay field showing inherited value
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	public static function getInheritedFieldDisplay($field, $params, $_v = null)
	{
		$_v = $params ? $params->get($field->fieldname) : $_v;

		if ($_v === '' || $_v === null)
		{
			return $field->input;
		}

		elseif ($field->getAttribute('type')==='fcordering' || $field->getAttribute('type')==='list' || ($field->getAttribute('type')==='multilist' && $field->getAttribute('subtype')==='list'))
		{
			$_v = htmlspecialchars( $_v, ENT_COMPAT, 'UTF-8' );
			if (preg_match('/<option\s*value="' . preg_quote($_v, '/') . '"\s*>(.*?)<\/option>/', $field->input, $matches))
			{
				return str_replace(
					JText::_('FLEXI_USE_GLOBAL'),
					JText::_('FLEXI_USE_GLOBAL') . ' (' . $matches[1] . ')',
					$field->input);
			}
		}

		elseif ($field->getAttribute('type')==='radio' || $field->getAttribute('type')==='fcradio' || ($field->getAttribute('type')==='multilist' && $field->getAttribute('subtype')==='radio'))
		{
			$_v = htmlspecialchars( $_v, ENT_COMPAT, 'UTF-8' );
			$has_btn_check = strpos($field->input, 'btn-check') !== 0;
			return str_replace(
				'value="'.$_v.'"',
				'value="'.$_v.'" class="fc-inherited-value' . ($has_btn_check ? ' btn-check' : '') . '" ',
				$field->input);
		}

		elseif ($field->getAttribute('type')==='fccheckbox' && is_array($_v))
		{
			$_input = $field->input;
			foreach ($_v as $v)
			{
				$v = htmlspecialchars( $v, ENT_COMPAT, 'UTF-8' );
				$_input = str_replace(
					'value="'.$v.'"',
					'value="'.$v.'" class="fc-inherited-value" ',
					$_input);
			}
			return $_input;
		}

		elseif ($field->getAttribute('type')==='text' || $field->getAttribute('type')==='fcmedia' || $field->getAttribute('type')==='media')
		{
			$_v = htmlspecialchars( preg_replace('/[\n\r]/', ' ', $_v), ENT_COMPAT, 'UTF-8' );
			return str_replace(
				'<input ',
				'<input placeholder="'.$_v.'" ',
				preg_replace('/^(\s*<input\s[^>]+)placeholder="[^"]+"/i', '\1 ', $field->input)
			);
		}
		elseif ($field->getAttribute('type')==='textarea')
		{
			$_v = htmlspecialchars(preg_replace('/[\n\r]/', ' ', $_v), ENT_COMPAT, 'UTF-8' );
			return str_replace(
				'<textarea ',
				'<textarea placeholder="'.$_v.'" ',
				preg_replace('/^(\s*<textarea\s[^>]+)placeholder="[^"]+"/i', '\1 ', $field->input)
			);
		}

		elseif ( method_exists($field, 'setInherited') )
		{
			$field->setInherited($_v);
			return $field->input;
		}

		return $field->input;
	}
}
