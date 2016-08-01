<?php
/**
 * @version 1.5 stable $Id: flexicontent.helper.php 1966 2014-09-21 17:33:27Z ggppdk $
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

if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');

// Try re-appling Joomla configuration of error reporting (some installed plugins may disable it)
switch ( JFactory::getConfig()->get('error_reporting') )  // Set the error_reporting
{
	case 'default': case '-1':
		break;
		
	case 'none': case '0':
		error_reporting(0);
		break;
		
	case 'simple':
		error_reporting(E_ERROR | E_WARNING | E_PARSE);
		ini_set('display_errors', 1);
		break;
		
	case 'maximum':
		error_reporting(E_ALL);
		ini_set('display_errors', 1);
		break;
		
	case 'development':
		error_reporting(-1);
		ini_set('display_errors', 1);
		break;
		
	default:
		error_reporting( JFactory::getConfig()->get('error_reporting') );
		ini_set('display_errors', 1);
		break;
}

if (!function_exists('json_encode')) { // PHP < 5.2 lack support for json
	require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'json'.DS.'jsonwrapper_inner.php');
} 

class flexicontent_html
{
	static $use_bootstrap = true;
	static $icon_classes = null;
	static $option = 'com_flexicontent';
	
	static function load_class_config()
	{
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		$icon_classes = $cparams->get('font_icon_classes');
		$icon_classes = $icon_classes ? preg_split("/[\s]*,[\s]*/", $icon_classes) : array();
		self::$icon_classes = array();
		foreach ($icon_classes as $d) {
			$data = preg_split("/[\s]*:[\s]*/", $d);
			if (count($data)!=2) { echo "Misconfigured parameter 'Icon classes': ".$d; continue; }
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
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<?php foreach ($msgsByType as $type => $msgs) : ?>
					<div class="alert <?php echo $alert_class[$type]; ?>">
						<h4 class="alert-heading"><?php echo JText::_($type); ?></h4>
						<?php if ($msgs) : ?>
							<?php foreach ($msgs as $msg) : ?>
								<p><?php echo $msg; ?></p>
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
	
	
	static function gridOrderBtn($rows, $image = 'filesave.png', $task = 'saveorder')
	{
		//return str_replace('rel="tooltip"', '', JHTML::_('grid.order', $rows, $image, $task));
		return '<a href="javascript:saveorder('
			. (count($rows) - 1) . ', \'' . $task . '\')" class="saveorder btn pull-right"><span class="icon-menu-2"></span> '.JText::_('JLIB_HTML_SAVE_ORDER') .'</a>';
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
			$conf_limit_class  = $recommended_ok ? 'badge-success' : ($required_ok ? 'badge-warning' : 'badge-important');
			
			$result[ $recommended_ok ? 'message' : ($required_ok ? 'notice' : 'warning') ][] = '
			<span class="fc-php-limits-box">
				<span class="label label-info">'.$varname.'</span>
				<span class="badge '.$conf_limit_class.'">'.$sys_value.'</span>
				&nbsp; &nbsp; &nbsp;
				<span class="fc-php-limits-box">
					<span class="label">'.JText::_('FLEXI_REQUIRED').'</span>
					<b class="">'.$required[$varname].'</b>
					<span class="label">'.JText::_('FLEXI_RECOMMENDED').'</span>
					<b class="">'.$recommended[$varname].'</b>
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
				$JTEMPLATE_SITE = JPATH_SITE.'/templates/'.(!$app->isAdmin() ? $app->getTemplate() : JFactory::getDBO()->setQuery("SELECT template FROM #__template_styles WHERE client_id = 0 AND home = 1")->loadResult());
				$less_folders = JComponentHelper::getParams('com_flexicontent')->get('less_folders', 'JPATH_COMPONENT_SITE/assets/less/ :: JTEMPLATE_SITE/less/com_flexicontent/ ::');
				$_reps = array(
					'JPATH_COMPONENT_SITE' => JPATH_COMPONENT_SITE, 'JPATH_COMPONENT_ADMINISTRATOR' => JPATH_COMPONENT_ADMINISTRATOR,
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
					//echo $confFile . " time: ".filemtime($confFile) ."<br/>";
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
	
	
	/* Checks and if needed compiles LESS files to CSS files*/
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
			$v .= $v{strlen($v) - 1} == DS ? '' : DS;
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
				//if ($debug) JFactory::getApplication()->enqueueMessage('Path not found: '.$path.$inFile, 'warning');
			} else if ( $_dirty || $force || !is_file($path.$outFile) || filemtime($path.$inFile) > filemtime($path.$outFile) || (filesize($path.$outFile)===0 && is_writable($path.$outFile)) ) {
				$stale[$inFile] = $outFile;
			}
		}
		unset($inFile);
		//print_r($stale);
		
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
				if ($debug || JFactory::getApplication()->isAdmin()) JFactory::getApplication()->enqueueMessage(
					'- LESS to CSS halted ... CSS file was not changed ... please edit LESS file(s) find offending <b>lines</b> and fix or remove<br/>'. str_replace($path.$in, '<br/><b>'.$path.$in.'</b>', $e->getMessage()), 'notice'
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
		
		$jversion = new JVersion;   // jimport('cms.version.version');  was done by defineconstants php file
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
		
		// Encode the canonical URL
		$ucanonical = $domain . $ucanonical;
		$ucanonical_encoded = htmlspecialchars($ucanonical);
		
		// Get head object
		$head_obj = $doc->mergeHeadData(array(1=>1));
			
		// Remove canonical inserted by SEF plugin, unsetting default directly may not be reliable, we will search for it
		//unset($head_obj->_links[htmlspecialchars($defaultCanonical)]);
		$addRel = true;
		foreach($head_obj->_links as $link => $data)
		{
			if ($data['relation']=='canonical' && $data['relType']=='rel') {
				if($link == $ucanonical_encoded)
					$addRel = false;
				else
					unset($head_obj->_links[$link]);
			}
		}
		
		// Add REL canonical only if different than current URL
		// * J3.5.1+ * Always add canonical, otherwise Joomla SEF plugin will add the default
		if ($addRel /*&& rawurldecode($uri->toString()) != $ucanonical*/) {
			$doc->addHeadLink( $ucanonical_encoded, 'canonical', 'rel' );
		}
	}
	
	
	// *** Output the javascript to dynamically hide/show columns of a table
	static function jscode_to_showhide_table($container_div_id, $data_tbl_id, $start_html='', $end_html='') {
		$document = JFactory::getDocument();
		$js = "
		var show_col_${data_tbl_id} = Array();
		jQuery(document).ready(function() {
		";
		
		if (isset($_POST["columnchoose_${data_tbl_id}"])) {
			foreach ($_POST["columnchoose_${data_tbl_id}"] as $colnum => $ignore) {
				$js .= "show_col_${data_tbl_id}[".$colnum."]=1; \n";
			}
		}
		else if (isset($_COOKIE["columnchoose_${data_tbl_id}"])) {
			$colnums = preg_split("/[\s]*,[\s]*/", $_COOKIE["columnchoose_${data_tbl_id}"]);
			foreach ($colnums as $colnum) {
				$colnum = (int) $colnum;
				$js .= "show_col_${data_tbl_id}[".$colnum."]=1; \n";
			}
		}
		
		$firstload = isset($_POST["columnchoose_${data_tbl_id}"]) || isset($_COOKIE["columnchoose_${data_tbl_id}"]) ? "false" : "true";
		$js .= "create_column_choosers('$container_div_id', '$data_tbl_id', $firstload, '".$start_html."', '".$end_html."'); \n";
		
		$js .= "
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
		return JHtml::tooltipText($title, $content, $translate, $escape);
	}
	
	
	static function escape($str) {
		return htmlspecialchars($str, ENT_COMPAT, 'UTF-8');
	}
	
	
	static function get_basedomain($url)
	{
		$pieces = parse_url($url);
		$domain = isset($pieces['host']) ? $pieces['host'] : '';   echo " ";
		if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
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
		$url = (!$has_prefix ? "http://" : "") . $url;

		// Require baseonly internal url: (HOST only)
		if ( $baseonly || $allowed_redirecturls == 'internal_base' )
			return flexicontent_html::get_basedomain($url) == flexicontent_html::get_basedomain(JURI::base());
		
		// Require full internal url: (HOST + this JOOMLA folder)
		else // if ( $allowed_redirecturls == 'internal_full' )
			return parse_url($url, PHP_URL_HOST) == parse_url(JURI::base(), PHP_URL_HOST);
		
		// Allow any URL, (external too) this may be considered a vulnerability for unlogged/logged users, since
		// users may be redirected to an offsite URL despite clicking an internal site URL received e.g. by an email
		//else
		//	return true;
	}


	/**
	 * Function to render the item view of a given item id
	 *
	 * @param 	int 		$item_id
	 * @return 	string  : the HTML of the item view, also the CSS / JS file would have been loaded
	 * @since 1.5
	 */
	function renderItem($item_id, $view=FLEXI_ITEMVIEW, $ilayout='')
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
		if ($ilayout=='') {
			$ilayout = $item->parameters->get('ilayout', '');
		}
		// Get type's ilayout
		if ($ilayout=='') {
			$type = JTable::getInstance('flexicontent_types', '');
			$type->id = $item->type_id;
			$type->load();
			$type->params = new JRegistry($type->attribs);
			$ilayout = $type->params->get('ilayout', 'default');
		}
		
		// Get cached template data, re-parsing XML/LESS files, also loading any template language files of a specific template
		$themes = flexicontent_tmpl::getTemplates( array($ilayout) );
		
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
		if (!isset($this->item->event)) $this->item->event = new stdClass();
		$this->item->event->beforeDisplayContent = '';
		$this->item->event->afterDisplayTitle = '';
		$this->item->event->afterDisplayContent = '';
		$this->fields = & $this->item->fields;

		// start capturing output into a buffer
		ob_start();
		// Include the requested template filename in the local scope (this will execute the view logic).
		if ( file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates'.DS.$ilayout) )
			include JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates'.DS.$ilayout.DS.'item.php';
		else if (file_exists(JPATH_COMPONENT.DS.'templates'.DS.$ilayout))
			include JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.$ilayout.DS.'item.php';
		else
			include JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.'default'.DS.'item.php';

		// done with the requested template; get the buffer and clear it.
		$item_html = ob_get_contents();
		ob_end_clean();
		$this->params = $this->params_saved;
		
		return $item_html;
	}

	static function listall_selector(&$params, $formname='adminForm', $autosubmit=1)
	{
		
		$use_limit_before = (int) $params->get('use_limit_before_search_filt', 0);
		if ($use_limit_before < 2) return '';
		
		$app = JFactory::getApplication();
		$use_limit_before_search_filt = $app->getUserState('use_limit_before_search_filt');
		if ($use_limit_before_search_filt < 2) return '';
		
		$tooltip_class = 'hasTooltip';
		return '
			<input type="checkbox" id="listall" name="listall" value="1" form="'.$formname.'"/>
			<label id="listall-lbl" for="listall" class="btn '.$tooltip_class.'" style="width:100%; margin:8px 0; box-sizing:border-box;" title="'.JText::_('FLEXI_LISTING_ONLY_FEATURED_CLICK_TO_LIST_ALL_DESC', true).'">
				'.JText::_('FLEXI_LIST_ALL_ITEMS').'
			</label>
		';
	}
	
	
	static function limit_selector(&$params, $formname='adminForm', $autosubmit=1)
	{
		if ( !$params->get('limit_override') ) return '';

		$app = JFactory::getApplication();
		
		$default_limit = $app->getUserState('use_limit_before_search_filt') ? $params->get('limit_before_search_filt') : $params->get('limit');
		$limit_given = strlen( $app->input->get('limit') );
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
		
		return $outside_label.JHTML::_('select.genericlist', $limiting, 'limit', $attribs);
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
		
		$orderby_options = $params->get('orderby_options'.$sfx, array('_preconfigured_','date','rdate','modified','alpha','ralpha','author','rauthor','hits','rhits','id','rid','order'));
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
			'random'=>'FLEXI_ORDER_RANDOM',
			'alias'=>'FLEXI_ORDER_ALIAS',
			'ralias'=>'FLEXI_ORDER_ALIAS_REVERSE'
		);
		
		$ordering = array();
		foreach ($extra_order_types as $value => $text)
		{
			$text = JText::_( $text );
			//$ordering[] = JHTML::_('select.option',  $value,  $text);
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
			//$ordering[] = JHTML::_('select.option',  $value,  $text);
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
			//$ordering[] = JHTML::_('select.option', $value,  $text);
			$attr_arr = $default_orderby == $value ? array('data-is-default-value' => '1') : array();
			$ordering[] = array(
				'value' => $value,
				'text'  => $text,
				'attr'  => $attr_arr
			);
		}
		
		return JHTML::_('select.genericlist', $ordering, 'orderby'.$sfx, $attribs);
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
		$option = JRequest::getCmd('option');
		$layout = JRequest::getCmd('layout');
		$svar = $layout ? '.'.$layout : '.category';
		$layout_typename = $layout_type=='clayout' ? 'category' : 'items';

		/*if (!$layout) $svar .= JRequest::getInt('cid');
		else if ($layout=='tags') $svar .= JRequest::getInt('tagid');
		else if ($layout=='author') $svar .= JRequest::getInt('authorid');
		if ($layout) $svar .= '.category'.JRequest::getInt('cid');*/
		
		//$layout = $app->getUserStateFromRequest( $option.$svar.'.'.$layout_type, $layout_type, $default_layout, 'cmd' );
		$layout = $app->input->get($layout_type, $default_layout, 'cmd') ;
		
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
				//$options[] = JHTML::_('select.option', $layout_name, $layout_title .$inside_label);
				$attr_arr = $default_layout == $layout_name ? array('data-is-default-value' => '1') : array();
				$options[] = array(
					'value' => $layout_name,
					'text'  => $layout_title .$inside_label,
					'attr'  => $attr_arr
				);
			}
			$html = JHTML::_('select.genericlist', $options, $layout_type, $attribs);
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
	
	
	static function searchphrase_selector(&$params, $formname='adminForm') {
		$searchphrase = '';
		if($show_searchphrase = $params->get('show_searchphrase', 1)) {
			$default_searchphrase = $params->get('default_searchphrase', 'all');
			$searchphrase = JRequest::getWord('searchphrase', JRequest::getWord('p', $default_searchphrase));
			$searchphrase_names = array(
				'all'=>'FLEXI_ALL_WORDS', 'any'=>'FLEXI_ANY_WORDS', 'natural'=>'FLEXI_NATURAL_PHRASE',
				'exact'=>'FLEXI_EXACT_PHRASE', 'natural_expanded'=>'FLEXI_NATURAL_PHRASE_GUESS_RELEVANT'
			);
		
			$searchphrases = array();
			foreach ($searchphrase_names as $searchphrase_value => $searchphrase_name) {
				$_obj = new stdClass();
				$_obj->value = $searchphrase_value;
				$_obj->text  = $searchphrase_name;
				$searchphrases[] = $_obj;
			}
			$searchphrase = JHTML::_('select.genericlist', $searchphrases, 'p',
				'class="fc_field_filter use_select2_lib"', 'value', 'text', $searchphrase, 'searchphrase', $_translate=true);
		}
		return $searchphrase;
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
		$lib_path = '/components/com_flexicontent/librairies';
		
		// Set jQuery to load in views that use it
		$JQUERY_VER    = !$params ? '1.8.3' : $params->get('jquery_ver', '1.8.3');
		$JQUERY_UI_VER = !$params ? '1.9.2' : $params->get('jquery_ui_ver', '1.9.2');
		$JQUERY_UI_THEME = !$params ? 'ui-lightness' : $params->get('jquery_ui_theme', 'ui-lightness');
		$add_remote = (FLEXI_J30GE && $add_remote==2) || (!FLEXI_J30GE && $add_remote);
		JText::script("FLEXI_FORM_IS_BEING_SUBMITTED", true);
		
		
		// **************
		// jQuery library
		// **************
		
		if ( $add_jquery && !$jquery_added && !JPluginHelper::isEnabled('system', 'jquerysupport') )
		{
			if ( $add_remote ) {
				$document->addScript('//ajax.googleapis.com/ajax/libs/jquery/'.$JQUERY_VER.'/jquery.min.js');
			} else {
				FLEXI_J30GE ?
					JHtml::_('jquery.framework') :
					$document->addScript(JURI::root(true).$lib_path.'/jquery/js/jquery-'.$JQUERY_VER.'.min.js');
			}
			// The 'noConflict()' statement must be inside a js file, to make sure it executed immediately
			if (!FLEXI_J30GE) $document->addScript(JURI::root(true).$lib_path.'/jquery/js/jquery-no-conflict.js');
			//$document->addCustomTag('<script>jQuery.noConflict();</script>');  // not placed in proper place
			$jquery_added = 1;
		}
		
		
		// *******************************
		// jQuery-UI library (and its CSS)
		// *******************************
		
		if ( $add_jquery_ui && !$jquery_ui_added ) {
			// Load all components of jQuery-UI
			if ($add_remote) {
				$document->addScript('//ajax.googleapis.com/ajax/libs/jqueryui/'.$JQUERY_UI_VER.'/jquery-ui.min.js');
			} else {
				if (FLEXI_J30GE) {
					JHtml::_('jquery.ui', array('core', 'sortable'));   // 'core' in J3+ includes all parts of jQuery-UI CORE component: Core, Widget, Mouse, Position
					if ( !$params || $params->get('load-ui-dialog', 1) )        $document->addScript(JURI::root(true).$lib_path.'/jquery/js/jquery-ui/jquery.ui.dialog.min.js');
					if ( !$params || $params->get('load-ui-menu', 1) )          $document->addScript(JURI::root(true).$lib_path.'/jquery/js/jquery-ui/jquery.ui.menu.min.js');
					if ( !$params || $params->get('load-ui-autocomplete', 1) )  $document->addScript(JURI::root(true).$lib_path.'/jquery/js/jquery-ui/jquery.ui.autocomplete.min.js');
				} else {
					$document->addScript(JURI::root(true).$lib_path.'/jquery/js/jquery-ui-'.$JQUERY_UI_VER.'.js');
				}
			}
			$jquery_ui_added = 1;
		}
		
		// Add jQuery UI theme, this is included in J3+ when executing jQuery-UI framework is called
		if ( $add_jquery_ui_css && !$jquery_ui_css_added ) {
			// FLEXI_JQUERY_UI_CSS_STYLE:  'ui-lightness', 'smoothness'
			if ($add_remote) {
				$document->addStyleSheet('//ajax.googleapis.com/ajax/libs/jqueryui/'.$JQUERY_UI_VER.'/themes/'.$JQUERY_UI_THEME.'/jquery-ui.css');
			} else {
				$document->addStyleSheet(JURI::root(true).$lib_path.'/jquery/css/'.$JQUERY_UI_THEME.'/jquery-ui-'.$JQUERY_UI_VER.'.css');
				$jquery_ui_css_added = 1;
			}
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
		$app = JFactory::getApplication();
		static $load_frameworks = null;
		static $load_jquery = null;
		if ( !isset($load_frameworks[$framework]) ) {
			$flexiparams = JComponentHelper::getParams('com_flexicontent');
			//$load_frameworks = $flexiparams->get('load_frameworks', array('jQuery','image-picker','masonry','select2','inputmask','prettyCheckable','fancybox'));
			//$load_frameworks = FLEXIUtilities::paramToArray($load_frameworks);
			//$load_frameworks = array_flip($load_frameworks);
			//$load_jquery = isset($load_frameworks['jQuery']) || !$app->isSite();
			if ( $load_jquery===null ) $load_jquery = $flexiparams->get('loadfw_jquery', 1)==1  ||  !$app->isSite();
			$load_framework = $flexiparams->get( 'loadfw_'.strtolower(str_replace('-','_',$framework)), 1 );
			$load_frameworks[$framework] = $load_framework==1  ||  ($load_framework==2 && !$app->isSite());
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
		switch ( $framework )
		{
			case 'jQuery':
				if ($load_jquery) flexicontent_html::loadJQuery(1, 1, 1, 1, $params);
				break;
			
			case 'mCSB':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				$framework_path = JURI::root(true).$lib_path.'/mCSB';
				$document->addScript($framework_path.'/jquery.mCustomScrollbar.min.js');
				$document->addStyleSheet($framework_path.'/jquery.mCustomScrollbar.css');
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
				
				$framework_path = JURI::root(true).$lib_path.'/image-picker';
				$document->addScript($framework_path.'/image-picker.min.js');
				$document->addStyleSheet($framework_path.'/image-picker.css');
				break;
			
			case 'masonry':
				$framework_path = JURI::root(true).$lib_path.'/masonry';
				$document->addScript($framework_path.'/masonry.pkgd.min.js');
				
				break;
			
			case 'select2':
				if ($load_jquery) flexicontent_html::loadJQuery();
				// Load flexi-lib, as it contains the select2 attach function: fc_attachSelect2()
				flexicontent_html::loadFramework('flexi-lib');
				
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
						jQuery(this).each(function() {
							if (jQuery(this).hasClass('use_select2_lib') || jQuery(this).hasClass('fc_no_js_attach')) return;
							jQuery(this).chosen_fc(args);
						});
					};
				}
				";
				
				$ver = '3.5.4';
				$framework_path = JURI::root(true).$lib_path.'/select2';
				$framework_folder = JPATH_SITE .DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'select2';
				$document->addScriptVersion($framework_path.'/select2.min.js', $ver);
				$document->addStyleSheetVersion($framework_path.'/select2.css', $ver);
				
				$lang_code = flexicontent_html::getUserCurrentLang();
				if ( $lang_code && $lang_code!='en' )
				{
					// Try language shortcode
					if ( file_exists($framework_folder.DS.'select2_locale_'.$lang_code.'.js') ) {
						$document->addScriptVersion($framework_path.'/select2_locale_'.$lang_code.'.js', $ver);
					}
					// select2 JS 4.0.0+
					/*if ( file_exists($framework_folder.DS.'select2'.DS.'i18n'.DS.$lang_code.'.js') ) {
						$document->addScriptVersion($framework_path.'/select2/i18n/'.$lang_code.'.js', $ver);
					}*/
					// Try country language code
					else {
						$country_code = flexicontent_html::getUserCurrentLang($short_tag=false);
						if ( $country_code && file_exists($framework_folder.DS.'select2_locale_'.$country_code.'.js') ) {
							$document->addScriptVersion($framework_path.'/select2_locale_'.$country_code.'.js', $ver);
						}
						// select2 JS 4.0.0+
						/*if ( $country_code && file_exists($framework_folder.DS.'select2'.DS.'i18n'.DS.$country_code.'.js') ) {
							$document->addScriptVersion($framework_path.'/select2/i18n/'.$country_code.'.js', $ver);
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
				
				$framework_path = JURI::root(true).$lib_path.'/inputmask';
				$document->addScript($framework_path.'/jquery.inputmask.bundle.min.js');
				
				// Extra inputmask declarations definitions, e.g. ...
				$js .= "
				";
				
				
				// Attach inputmask to all input fields that have appropriate tag parameters
				$js .= "
					jQuery(document).ready(function(){
						Inputmask.extendAliases({
							decimal: {
								alias: 'numeric',
								placeholder: '_',
								autoGroup: true,
								radixPoint: '.',
								groupSeparator: ',',
								clearMaskOnLostFocus: true,
								removeMaskOnSubmit: true,
								unmaskAsNumber: false
							},
							decimal_comma: {
								alias: 'numeric',
								placeholder: '_',
								autoGroup: true,
								radixPoint: ',',
								groupSeparator: '.',
								clearMaskOnLostFocus: true,
								removeMaskOnSubmit: true,
								unmaskAsNumber: false
							},
							currency: {
								alias: 'numeric',
								placeholder: '_',
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
								placeholder: '_',
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
								placeholder: '_',
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
								placeholder: '_',
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
								placeholder: '_',
								digits: 0,
								radixPoint: '',
								clearMaskOnLostFocus: true,
								removeMaskOnSubmit: true,
								unmaskAsNumber: false
							},
							unsigned: {
								alias: 'numeric',
								placeholder: '_',
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
								placeholder: '_',
								clearMaskOnLostFocus: true,
								removeMaskOnSubmit: true,
								unmaskAsNumber: false
							}
						});
						
						jQuery('input.has_inputmask').inputmask();
						jQuery('input.inputmask-regex').inputmask('Regex');
					});
				";
				break;
			
			case 'prettyCheckable':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				$framework_path = JURI::root(true).$lib_path.'/prettyCheckable';
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
				
				$framework_path = JURI::root(true).$lib_path.'/jmultibox';
				
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
				//$js .= "";
				break;

			case 'fancybox':
				if ($load_jquery) flexicontent_html::loadJQuery();
				$document->addScript(JURI::root(true).'/components/com_flexicontent/assets/js/jquery-easing.js');
				
				$framework_path = JURI::root(true).$lib_path.'/fancybox';
				
				// Add mousewheel plugin (this is optional)
				$document->addScript($framework_path.'/lib/jquery.mousewheel-3.0.6.pack.js');
				
				// Add fancyBox CSS / JS
				$document->addStyleSheet($framework_path.'/source/jquery.fancybox.css');
				$document->addScript($framework_path.'/source/jquery.fancybox.pack.js');
				
				// Optionally add helpers - button, thumbnail and/or media
				$document->addStyleSheet($framework_path.'/source/helpers/jquery.fancybox-buttons.css');
				$document->addScript($framework_path.'/source/helpers/jquery.fancybox-buttons.js');
				$document->addScript($framework_path.'/source/helpers/jquery.fancybox-media.js');
				$document->addStyleSheet($framework_path.'/source/helpers/jquery.fancybox-thumbs.css');
				$document->addScript($framework_path.'/source/helpers/jquery.fancybox-thumbs.js');
				
				// Attach fancybox to all elements having a specific CSS class
				$js .= "
					jQuery(document).ready(function(){
						jQuery('.fancybox').fancybox({
							'openEffect'	: 'elastic',
							'closeEffect'	: 'elastic',
							'openEasing'  : 'easeOutCubic',
							'closeEasing' : 'easeInCubic',
						});
					});
				";
				break;
			
			case 'galleriffic':
				if ($load_jquery) flexicontent_html::loadJQuery();
				//flexicontent_html::loadFramework('fancybox');
				
				$framework_path = JURI::root(true).$lib_path.'/galleriffic';
				//$document->addStyleSheet($framework_path.'/css/basic.css');  // This is too generic and should not be loaded
				$document->addStyleSheet($framework_path.'/css/galleriffic-3.css');
				$document->addScript($framework_path.'/js/jquery.galleriffic.js');
				$document->addScript($framework_path.'/js/jquery.opacityrollover.js');
				
				break;
			
			case 'elastislide':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				$framework_path = JURI::root(true).$lib_path.'/elastislide';
				$document->addStyleSheet($framework_path.'/css/style.css');
				$document->addStyleSheet($framework_path.'/css/elastislide.css');
				
				$document->addScript($framework_path.'/js/jquery.tmpl.min.js');
				$document->addScript($framework_path.'/js/jquery.easing.1.3.js');
				$document->addScript($framework_path.'/js/jquery.elastislide.js');
				//$document->addScript($framework_path.'/js/gallery.js'); // replace with field specific: gallery_tmpl.js
				break;
			
			case 'photoswipe':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				$framework_path = JURI::root(true).$lib_path.'/photoswipe';
				
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
				
				$framework_path = JURI::root(true).$lib_path.'/fcxSlide';
				$document->addScriptVersion($framework_path.'/class.fcxSlide.js', FLEXI_VHASH);
				$document->addStyleSheetVersion($framework_path.'/fcxSlide.css', FLEXI_VHASH);
				//$document->addScriptVersion($framework_path.'/class.fcxSlide.packed.js', FLEXI_VHASH);
				break;
			
			case 'imagesLoaded':
				$framework_path = JURI::root(true).$lib_path.'/imagesLoaded';
				$document->addScript($framework_path.'/imagesloaded.pkgd.min.js');
				break;
			
			case 'noobSlide':
				// Make sure mootools are loaded
				JHtml::_('behavior.framework', true);
				
				$framework_path = JURI::root(true).$lib_path.'/noobSlide';
				//$document->addScript($framework_path.'/_class.noobSlide.js');
				$document->addScript($framework_path.'/_class.noobSlide.packed.js');
				break;
			
			case 'zTree':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				$framework_path = JURI::root(true).$lib_path.'/zTree';
				$document->addStyleSheet($framework_path.'/css/flexi_ztree.css');
				$document->addStyleSheet($framework_path.'/css/zTreeStyle/zTreeStyle.css');
				$document->addScript($framework_path.'/js/jquery.ztree.all-3.5.min.js');
				//$document->addScript($framework_path.'/js/jquery.ztree.core-3.5.js');
				//$document->addScript($framework_path.'/js/jquery.ztree.excheck-3.5.js');
				//$document->addScript($framework_path.'/js/jquery.ztree.exedit-3.5.js');
				break;
			
			case 'plupload':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				$framework_path = JURI::root(true).$lib_path.'/plupload';
				$framework_folder = JPATH_SITE .DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'plupload';
				$document->addScript($framework_path.'/js/plupload.full.min.js');
				
				if ($mode=='ui') {
					$document->addStyleSheet($framework_path.'/js/jquery.ui.plupload/css/jquery.ui.plupload.css');
					$document->addScript($framework_path.'/js/jquery.ui.plupload/jquery.ui.plupload.min.js');
					$document->addScript($framework_path.'/js/themeswitcher.js');
				} else {
					$document->addStyleSheet($framework_path.'/js/jquery.plupload.queue/css/jquery.plupload.queue.css');
					$document->addScript($framework_path.'/js/jquery.plupload.queue/jquery.plupload.queue.js');
				}
				
				$lang_code = flexicontent_html::getUserCurrentLang();
				if ( $lang_code && $lang_code!='en' )
				{
					// Try language shortcode
					if ( file_exists($framework_folder.DS.'js'.DS.$lang_code.'.js') ) {
						$document->addScript($framework_path.'/js/'.$lang_code.'.js');
					}
					// Try country language code
					else {
						$country_code = flexicontent_html::getUserCurrentLang($short_tag=false);
						if ( $country_code && file_exists($framework_folder.DS.'js'.DS.$country_code.'.js') ) {
							$document->addScript($framework_path.'/js/'.$country_code.'.js');
						}
					}
				}
				// For debugging
				//$document->addScript($framework_path.'/js/moxie.min.js');
				//$document->addScript($framework_path.'/js/plupload.dev.js');
				break;
				
			case 'nouislider':
				
				$framework_path = JURI::root(true).$lib_path.'/nouislider';
				$document->addStyleSheet($framework_path.'/nouislider.min.css');
				$document->addScript($framework_path.'/nouislider.min.js');
				break;

			case 'flexi_tmpl_common':
				if ($load_jquery) flexicontent_html::loadJQuery();
				flexicontent_html::loadFramework('select2');  // make sure select2 is loaded
				
				// Make sure user cookie is set
				$jcookie = $app->input->cookie;
				$fc_uid = $jcookie->get( 'fc_uid', null);
				$hashedUA = JFactory::getUser()->id ? JUserHelper::getShortHashedUserAgent() : 'p';
				if ($fc_uid != $hashedUA)  $jcookie->set( 'fc_uid', $hashedUA, 0);
				
				$js .= "
					var _FC_GET = ".json_encode($_GET).";
					var jbase_url_fc = ".json_encode(JURI::root()).";
				";
				$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/tmpl-common.js', FLEXI_VHASH);
				$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/jquery-easing.js', FLEXI_VHASH);
				JText::script("FLEXI_APPLYING_FILTERING", true);
				JText::script("FLEXI_TYPE_TO_LIST", true);
				JText::script("FLEXI_TYPE_TO_FILTER", true);
				JText::script("FLEXI_UPDATING_CONTENTS", true);
				break;
			
			case 'flexi-lib':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/flexi-lib.js', FLEXI_VHASH);
				JText::script("FLEXI_NOT_AN_IMAGE_FILE", true);
				break;
			
			// Used only by content / configuration forms, that have form elements needing this
			case 'flexi-lib-form':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/flexi-lib-form.js', FLEXI_VHASH);
				JText::script("FLEXI_EDIT", true);
				JText::script("FLEXI_ADD", true);
				JText::script("FLEXI_NA", true);
				JText::script("FLEXI_REMOVE", true);
				JText::script("FLEXI_ORDER", true);
				JText::script("FLEXI_INDEXED_FIELD_UNUSED_COL_DISABLED", true);
				JText::script("FLEXI_INDEXED_FIELD_VALGRP_COL_DISABLED", true);
				JText::script('FLEXI_REQUIRED',true);
				JText::script('FLEXI_INVALID',true);
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
	 * Escape a string so that it can be used directly by JS source code
	 *
	 * @param 	string 		$text
	 * @param 	int 		$nb
	 * @return 	string
	 * @since 1.5
	 */
	static function escapeJsText($string, $skipquote='')
	{
		$string = (string)$string;
		$string = str_replace("\r", '', $string);
		$string = addcslashes($string, "\0..\37'\\");
		// Whether to skip single or double quotes
		if ( $skipquote!='d' )  $string = str_replace('"', '\"', $string);
		if ( $skipquote!='s' )  $string = str_replace("'", "\'", $string);
		$string = str_replace("\n", ' ', $string);
		return $string;
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
		if ($validation=='-1') return flexicontent_html::striptagsandcut( $v, $maxlength );
		
		$v = $maxlength ? substr($v, 0, $maxlength) : $v;
		if ($check_callable) {
			if (strpos($validation, '::') !== false && is_callable(explode('::', $validation)))
				return call_user_func(explode('::', $validation), $v);   // A callback class method
			
			elseif (function_exists($validation))
				return call_user_func($validation, $v);  // A callback function
		}
		
		// Map integer validation code to custom validation types
		$_map = array('1'=>'safehtml_decode_first', '2'=>'joomla_text_filters', '3'=>'safehtml_allow_encoded');
		$validation = isset($_map[$validation])  ?  $_map[$validation]  :  $validation;
		
		// Create a safe-HTML or a no-HTML filter
		if ($validation=='safehtml_decode_first' || $validation=='safehtml_allow_encoded')
			$safeHtmlFilter = JFilterInput::getInstance(null, null, 1, 1);
		else if ($validation!='joomla_ug_text_filters')
			$noHtmlFilter = JFilterInput::getInstance();
		
		// Do the filtering
		switch ($validation)
		{
			case 'safehtml_decode_first':
				// Allow safe HTML ... but also decode HTML special characters before filtering
				// Decoding allows removal of e.g. &lt;badtag&gt; ... &lt;/badtag&gt;
				$v = $safeHtmlFilter->clean($v, 'string');
				break;
				
			case 'safehtml_allow_encoded':
				// Allow safe HTML ... and allow ANY HTML if encoded, e.g. allows &lt;i&gt; ... &lt;/i&gt;
				$v = $safeHtmlFilter->clean($v, 'html');
				break;
				
			case 'joomla_text_filters':
				// Filter according to user group Text Filters
				$v = JComponentHelper::filterText($v);
				break;
				
			case 'URL': case 'url':
				// This cleans some of the more dangerous characters but leaves special characters that are valid.
				$v = trim($noHtmlFilter->clean($v, 'HTML'));
				
				// <>" are never valid in a uri see http://www.ietf.org/rfc/rfc1738.txt.
				$v = str_replace(array('<', '>', '"'), '', $v);
				
				// Convert to Punycode string
				$v = JStringPunycode::urlToPunycode( $v );
				break;
				
			case 'EMAIL': case 'email':
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
	 * Strip html tags and cut after x characters
	 *
	 * @param 	string 		$text
	 * @param 	int 		$nb
	 * @return 	string
	 * @since 1.5
	 */
	static function striptagsandcut( $text, $chars=null, &$uncut_length=0 )
	{
		// Convert html entities to characters so that they will not be removed ... by strip_tags
		$text = html_entity_decode ($text, ENT_NOQUOTES, 'UTF-8');
		
		// Strip SCRIPT tags AND their containing code
		$text = preg_replace( '#<script\b[^>]*>(.*?)<\/script>#is', '', $text );
		
		// Add whitespaces at start/end of tags so that words will not be joined,
		//$text = preg_replace('/(<\/[^>]+>((?!\P{L})|(?=[0-9])))|(<[^>\/][^>]*>)/u', ' $1', $text);
		$text = preg_replace('/(<\/[^>]+>(?![\:|\.|,|:|"|\']))|(<[^>\/][^>]*>)/u', ' $1', $text);
		
		// Strip html tags
		$cleantext = strip_tags($text);

		// clean additionnal plugin tags
		$patterns = array();
		$patterns[] = '#\[(.*?)\]#';
		$patterns[] = '#{(.*?)}#';
		$patterns[] = '#&(.*?);#';
		
		foreach ($patterns as $pattern) {
			$cleantext = preg_replace( $pattern, '', $cleantext );
		}
		
		// Replace multiple spaces, tabs, newlines, etc with a SINGLE whitespace so that text length will be calculated correctly
		$cleantext = preg_replace('/[\p{Z}\s]{2,}/u', ' ', $cleantext);  // Unicode safe whitespace replacing
		
		// Calculate length according to UTF-8 encoding
		$uncut_length = StringHelper::strlen($cleantext);
		
		// Cut off the text if required
		if ($chars) {
			if ($uncut_length > $chars) {
				$cleantext = StringHelper::substr( $cleantext, 0, $chars ).'...';
			}
		}
		
		// Reencode HTML special characters, (but do not encode UTF8 characters)
		$cleantext = htmlspecialchars($cleantext, ENT_QUOTES, 'UTF-8');
		
		return $cleantext;
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
	static function setitemstate( $controller_obj )
	{
		$id = JRequest::getInt( 'id', 0 );
		JRequest::setVar( 'cid', $id );

		$app = JFactory::getApplication();
		$modelname = $app->isAdmin() ? 'item' : FLEXI_ITEMVIEW;
		$model = $controller_obj->getModel( $modelname );
		$user = JFactory::getUser();
		$state = JRequest::getVar( 'state', 0 );

		// Get owner and other item data
		$db = JFactory::getDBO();
		$q = "SELECT id, created_by, catid FROM #__content WHERE id =".$id;
		$db->setQuery($q);
		$item = $db->loadObject();

		// Determine priveleges of the current user on the given item
		$asset = 'com_content.article.' . $item->id;
		$has_edit_state = $user->authorise('core.edit.state', $asset) || ($user->authorise('core.edit.state.own', $asset) && $item->created_by == $user->get('id'));
		$has_delete     = $user->authorise('core.delete', $asset) || ($user->authorise('core.delete.own', $asset) && $item->created_by == $user->get('id'));
		// ...
		$permission = FlexicontentHelperPerm::getPerm();
		$has_archive    = $permission->CanArchives;

		$has_edit_state = $has_edit_state && in_array($state, array(0,1,-3,-4,-5));
		$has_delete     = $has_delete     && $state == -2;
		$has_archive    = $has_archive    && $state == 2;

		// check if user can edit.state of the item
		$access_msg = '';
		if ( !$has_edit_state && !$has_delete && !$has_archive )
		{
			//echo JText::_( 'FLEXI_NO_ACCESS_CHANGE_STATE' );
			echo JText::_( 'FLEXI_DENIED' );   // must a few words
			return;
		}
		else if(!$model->setitemstate($id, $state))
		{
			$msg = JText::_('FLEXI_ERROR_SETTING_THE_ITEM_STATE');
			echo $msg . ": " .$model->getError();
			return;
		}

		// Clean cache
		$cache = FLEXIUtilities::getCache($group='', 0);
		$cache->clean('com_flexicontent_items');
		$cache->clean('com_flexicontent_filters');
		$cache = FLEXIUtilities::getCache($group='', 1);
		$cache->clean('com_flexicontent_items');
		$cache->clean('com_flexicontent_filters');

		// Output new state icon and terminate
		$tmpparams = new JRegistry();
		$tmpparams->set('stateicon_popup', 'basic');
		$stateicon = flexicontent_html::stateicon( $state, $tmpparams );
		echo $stateicon;
		exit;
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
		if ( !$params->get('show_feed_icon', 1) || JFactory::getApplication()->input->get('print', 0, 'INT') ) return;
		
		$uri    = JURI::getInstance();
		$base  	= $uri->toString( array('scheme', 'host', 'port'));

		//TODO: clean this static stuff (Probs when determining the url directly with subdomains)
		if($view == 'category')
		{
			$non_sef_link = null;
			flexicontent_html::createCatLink($slug, $non_sef_link);
			$link = $base . JRoute::_($non_sef_link.'&format=feed&type=rss');
			//$link = $base.JRoute::_( 'index.php?view='.$view.'&cid='.$slug.'&format=feed&type=rss', false );
		} elseif($view == FLEXI_ITEMVIEW) {
			$link = $base . JRoute::_(FlexicontentHelperRoute::getItemRoute($itemslug, $slug, 0, $item).'&format=feed&type=rss');
			//$link = $base.JRoute::_( 'index.php?view='.$view.'&cid='.$slug.'&id='.$itemslug.'&format=feed&type=rss', false );
		} elseif($view == 'tags') {
			$link = $base . JRoute::_(FlexicontentHelperRoute::getTagRoute($itemslug).'&format=feed&type=rss');
			//$link = $base.JRoute::_( 'index.php?view='.$view.'&id='.$slug.'&format=feed&type=rss', false );
		} else {
			$link = $base . JRoute::_( 'index.php?view='.$view.'&format=feed&type=rss', false );
		}
		
		$status = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,left=50,width=\'+(screen.width-100)+\',top=20,height=\'+(screen.height-160)+\',directories=no,location=no';
		$onclick = ' window.open(this.href,\'win2\',\''.$status.'\'); return false; ';
		
		// This checks template image directory for image, if none found, default image is returned
		$show_icons = $params->get('show_icons', 2);
		$use_font   = $params->get('use_font_icons', 1);
		if ( $show_icons && $use_font ) {
			static $icon_class = null;
			if ($icon_class == null) {
				if (self::$icon_classes==null) self::load_class_config();
				$icon_class = empty(self::$icon_classes['feed']) ? 'icon-feed' : self::$icon_classes['feed'];
				$icon_class .= ($show_icons==2 ? ' fcIconPadRight' : '');
			}
			$attribs = '';
			$image = '<i class="'.$icon_class.'"></i>';
		} else if ( $show_icons ) {
			$attribs = '';
			$image = JHTML::image(FLEXI_ICONPATH.'livemarks.png', JText::_( 'FLEXI_FEED' ), $attribs);
		} else {
			$image = '';
		}
		
		$overlib = JText::_( 'FLEXI_FEED_TIP' );
		$text = JText::_( 'FLEXI_FEED' );
		
		$button_classes = 'fc_feedbutton';
		if ( $show_icons==1 ) {
			$caption = '';
			$button_classes .= '';
			$tooltip_place = 'bottom';
		} else {
			$caption = $text;
			if ( !$params->get('btn_grp_dropdown', 0) ) $button_classes .= self::$use_bootstrap ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
			$tooltip_place = !$params->get('btn_grp_dropdown', 0) ? 'bottom' : 'left';
		}
		$button_classes .= ' hasTooltip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);
		
		// $link as set above
		$output	= ' <a href="'.$link.'" class="'.$button_classes.'" data-placement="'.$tooltip_place.'" title="'.$tooltip_title.'" onclick="'.$onclick.'" >'.$image.$caption.'</a>';
		$output	= JText::_( 'FLEXI_ICON_SEP' ) .$output. JText::_( 'FLEXI_ICON_SEP' );

		return $output;
	}
	
	
	/**
	 * Creates the delete button
	 *
	 * @param array $params
	 * @since 1.0
	 */
	static function deletebutton( $item, &$params)
	{
		if ( !$params->get('show_deletebutton', 0) || JFactory::getApplication()->input->get('print', 0, 'INT') ) return;
		
		$user	= JFactory::getUser();
		
		// Determine if current user can delete the given item
		$has_delete = false;
		$asset = 'com_content.article.' . $item->id;
		$has_delete = $user->authorise('core.delete', $asset) || ($user->authorise('core.delete.own', $asset) && $item->created_by == $user->get('id'));
		// ALTERNATIVE 1
		//$rights = FlexicontentHelperPerm::checkAllItemAccess($user->get('id'), 'item', $item->id);
		//$has_delete = in_array('delete', $rights) || (in_array('delete.own', $rights) && $item->created_by == $user->get('id')) ;

		// Create the delete button only if user can delete the give item
		if ( !$has_delete ) return;
		
		$show_icons = $params->get('show_icons', 2);
		$use_font   = $params->get('use_font_icons', 1);
		if ( $show_icons && $use_font ) {
			static $icon_class = null;
			if ($icon_class == null) {
				if (self::$icon_classes==null) self::load_class_config();
				$icon_class = empty(self::$icon_classes['delete']) ? 'icon-delete' : self::$icon_classes['delete'];
				$icon_class .= ($show_icons==2 ? ' fcIconPadRight' : '');
			}
			$attribs = '';
			$image = '<i class="'.$icon_class.'"></i>';
		} else if ( $show_icons ) {
			$attribs = '';
			$image = JHTML::image('components/com_flexicontent/assets/images/'.'delete.png', JText::_( 'FLEXI_DELETE' ), $attribs);
		} else {
			$image = '';
		}
		
		$overlib 	= JText::_( 'FLEXI_DELETE_TIP' );
		$text 		= JText::_( 'FLEXI_DELETE' );
		
		$button_classes = 'fc_deletebutton';
		if ( $show_icons==1 ) {
			$caption = '';
			$button_classes .= '';
			$tooltip_place = 'bottom';
		} else {
			$caption = $text;
			if ( !$params->get('btn_grp_dropdown', 0) ) $button_classes .= self::$use_bootstrap ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
			$tooltip_place = !$params->get('btn_grp_dropdown', 0) ? 'bottom' : 'left';
		}
		$button_classes .= ' hasTooltip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);
		
		//$Itemid = JRequest::getInt('Itemid', 0);  // Maintain menu item ? e.g. current category view, 
		$Itemid = 0;
		$item_url = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, $Itemid, $item));
		$link = $item_url  .(strstr($item_url, '?') ? '&' : '?').  'task=remove';
		$targetLink = "_self";
		$confirm_text = JText::_('FLEXI_ARE_YOU_SURE_PERMANENT_DELETE', true);
		
		$output	= ' <a href="'.$link.'" class="'.$button_classes.'" data-placement="'.$tooltip_place.'" onclick="return confirm(\''.$confirm_text.'\')" target="'.$targetLink.'" title="'.$tooltip_title.'">'.$image.$caption.'</a>';
		$output	= JText::_( 'FLEXI_ICON_SEP' ) .$output. JText::_( 'FLEXI_ICON_SEP' );
		
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
		if ( !$params->get('show_csvbutton', 0) || JFactory::getApplication()->input->get('print', 0, 'INT') ) return;
		
		$uri    = JURI::getInstance();
		$base  	= $uri->toString( array('scheme', 'host', 'port'));

		//TODO: clean this static stuff (Probs when determining the url directly with subdomains)
		if($view == 'category')
		{
			$non_sef_link = null;
			flexicontent_html::createCatLink($slug, $non_sef_link);
			$link = $base . JRoute::_($non_sef_link.'&format=csv');
			//$link = $base.JRoute::_( 'index.php?view='.$view.'&cid='.$slug.'&format=csv', false );
		} elseif($view == FLEXI_ITEMVIEW) {
			$link = $base . JRoute::_(FlexicontentHelperRoute::getItemRoute($itemslug, $slug, 0, $item).'&format=csv');
			//$link = $base.JRoute::_( 'index.php?view='.$view.'&cid='.$slug.'&id='.$itemslug.'&format=csv', false );
		} elseif($view == 'tags') {
			$link = $base . JRoute::_(FlexicontentHelperRoute::getTagRoute($itemslug).'&format=csv');
			//$link = $base.JRoute::_( 'index.php?view='.$view.'&id='.$slug.'&format=csv', false );
		} else {
			$link = $base . JRoute::_( 'index.php?view='.$view.'&format=csv', false );
		}
		
		$status = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,left=50,width=\'+(screen.width-100)+\',top=20,height=\'+(screen.height-160)+\',directories=no,location=no';
		$onclick = ' window.open(this.href,\'win2\',\''.$status.'\'); return false; ';
		
		// This checks template image directory for image, if none found, default image is returned
		$show_icons = $params->get('show_icons', 2);
		$use_font   = $params->get('use_font_icons', 1);
		if ( $show_icons && $use_font ) {
			static $icon_class = null;
			if ($icon_class == null) {
				if (self::$icon_classes==null) self::load_class_config();
				$icon_class = empty(self::$icon_classes['csv']) ? 'icon-download' : self::$icon_classes['csv'];
				$icon_class .= ($show_icons==2 ? ' fcIconPadRight' : '');
			}
			$attribs = '';
			$image = '<i class="'.$icon_class.'"></i>';
		} else if ( $show_icons ) {
			$attribs = '';
			$image = JHTML::image('components/com_flexicontent/assets/images/'.'csv.png', JText::_( 'FLEXI_CSV_EXPORT' ), $attribs);
		} else {
			$image = '';
		}
		
		$overlib = JText::_( 'FLEXI_CSV_TIP' );
		$text = JText::_( 'FLEXI_CSV' );
		
		$button_classes = 'fc_csvbutton';
		if ( $show_icons==1 ) {
			$caption = '';
			$button_classes .= '';
			$tooltip_place = 'bottom';
		} else {
			$caption = $text;
			if ( !$params->get('btn_grp_dropdown', 0) ) $button_classes .= self::$use_bootstrap ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
			$tooltip_place = !$params->get('btn_grp_dropdown', 0) ? 'bottom' : 'left';
		}
		$button_classes .= ' hasTooltip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);
		
		// $link as set above
		$output	= ' <a href="'.$link.'" class="'.$button_classes.'" data-placement="'.$tooltip_place.'" title="'.$tooltip_title.'" onclick="'.$onclick.'" >'.$image.$caption.'</a>';
		$output	= JText::_( 'FLEXI_ICON_SEP' ) .$output. JText::_( 'FLEXI_ICON_SEP' );
		
		return $output;
	}
	
	
	/**
	 * Creates the print button
	 *
	 * @param string $print_link
	 * @param array $params
	 * @since 1.0
	 */
	static function printbutton( $print_link, &$params )
	{
		if ( !$params->get('show_print_icon') || JFactory::getApplication()->input->get('print', 0, 'INT') ) return;
		
		$status = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,left=50,width=\'+(screen.width-100)+\',top=20,height=\'+(screen.height-160)+\',directories=no,location=no';
		$onclick = ' window.open(this.href,\'win2\',\''.$status.'\'); return false; ';
		$link = JRoute::_($print_link);
		
		// This checks template image directory for image, if none found, default image is returned
		$show_icons = $params->get('show_icons', 2);
		$use_font   = $params->get('use_font_icons', 1);
		if ( $show_icons && $use_font ) {
			static $icon_class = null;
			if ($icon_class == null) {
				if (self::$icon_classes==null) self::load_class_config();
				$icon_class = empty(self::$icon_classes['print']) ? 'icon-print' : self::$icon_classes['print'];
				$icon_class .= ($show_icons==2 ? ' fcIconPadRight' : '');
			}
			$attribs = '';
			$image = '<i class="'.$icon_class.'"></i>';
		} else if ( $show_icons ) {
			$attribs = '';
			$image = JHTML::image(FLEXI_ICONPATH.'printButton.png', JText::_( 'FLEXI_PRINT' ), $attribs);
		} else {
			$image = '';
		}
		
		$overlib = JText::_( 'FLEXI_PRINT_TIP' );
		$text = JText::_( 'FLEXI_PRINT' );
		
		$button_classes = 'fc_printbutton';
		if ( $show_icons==1 ) {
			$caption = '';
			$button_classes .= '';
			$tooltip_place = 'bottom';
		} else {
			$caption = $text;
			if ( !$params->get('btn_grp_dropdown', 0) ) $button_classes .= self::$use_bootstrap ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
			$tooltip_place = !$params->get('btn_grp_dropdown', 0) ? 'bottom' : 'left';
		}
		$button_classes .= ' hasTooltip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);
		
		// $link as set above
		$output	= ' <a href="'.$link.'" class="'.$button_classes.'" data-placement="'.$tooltip_place.'" title="'.$tooltip_title.'" onclick="'.$onclick.'" >'.$image.$caption.'</a>';
		$output	= JText::_( 'FLEXI_ICON_SEP' ) .$output. JText::_( 'FLEXI_ICON_SEP' );

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

		if ( !$params->get('show_email_icon') || JFactory::getApplication()->input->get('print', 0, 'INT') ) return;

		if ($initialize === null) {
			if (file_exists ( JPATH_SITE.DS.'components'.DS.'com_mailto'.DS.'helpers'.DS.'mailto.php' )) {
				require_once(JPATH_SITE.DS.'components'.DS.'com_mailto'.DS.'helpers'.DS.'mailto.php');
				$uri  = JURI::getInstance();
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
		
		// This checks template image directory for image, if none found, default image is returned
		$show_icons = $params->get('show_icons', 2);
		$use_font   = $params->get('use_font_icons', 1);
		if ( $show_icons && $use_font ) {
			static $icon_class = null;
			if ($icon_class == null) {
				if (self::$icon_classes==null) self::load_class_config();
				$icon_class = empty(self::$icon_classes['mail']) ? 'icon-envelope' : self::$icon_classes['mail'];
				$icon_class .= ($show_icons==2 ? ' fcIconPadRight' : '');
			}
			$attribs = '';
			$image = '<i class="'.$icon_class.'"></i>';
		} else if ( $show_icons ) {
			$attribs = '';
			$image = JHTML::image(FLEXI_ICONPATH.'emailButton.png', JText::_( 'FLEXI_EMAIL' ), $attribs);
		} else {
			$image = '';
		}
		
		$overlib = JText::_( 'FLEXI_EMAIL_TIP' );
		$text = JText::_( 'FLEXI_EMAIL' );
		
		$button_classes = 'fc_mailbutton';
		if ( $show_icons==1 ) {
			$caption = '';
			$button_classes .= '';
			$tooltip_place = 'bottom';
		} else {
			$caption = $text;
			if ( !$params->get('btn_grp_dropdown', 0) ) $button_classes .= self::$use_bootstrap ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
			$tooltip_place = !$params->get('btn_grp_dropdown', 0) ? 'bottom' : 'left';
		}
		$button_classes .= ' hasTooltip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);
		
		// emailed link was set above
		$output	= ' <a href="'.$mail_to_url.'" class="'.$button_classes.'" data-placement="'.$tooltip_place.'" title="'.$tooltip_title.'" onclick="'.$onclick.'" >'.$image.$caption.'</a>';
		$output	= JText::_( 'FLEXI_ICON_SEP' ) .$output. JText::_( 'FLEXI_ICON_SEP' );
		
		return $output;
	}
	
	
	/**
	 * Creates the pdf button
	 *
	 * @param int $id
	 * @param array $params
	 * @since 1.0
	 */
	static function pdfbutton( $item, &$params)
	{
		if ( FLEXI_J16GE || !$params->get('show_pdf_icon') || JFactory::getApplication()->input->get('print', 0, 'INT') ) return;
		
		$show_icons = $params->get('show_icons', 2);
		$use_font   = $params->get('use_font_icons', 1);
		if ( $show_icons && $use_font ) {
			static $icon_class = null;
			if ($icon_class == null) {
				if (self::$icon_classes==null) self::load_class_config();
				$icon_class = empty(self::$icon_classes['pdf']) ? 'icon-book' : self::$icon_classes['pdf'];
				$icon_class .= ($show_icons==2 ? ' fcIconPadRight' : '');
			}
			$attribs = '';
			$image = '<i class="'.$icon_class.'"></i>';
		} else if ( $show_icons ) {
			$attribs = '';
			$image = JHTML::image(FLEXI_ICONPATH.'pdf_button.png', JText::_( 'FLEXI_CREATE_PDF' ), $attribs);
		} else {
			$image = '';
		}
		
		$overlib = JText::_( 'FLEXI_CREATE_PDF_TIP' );
		$text = JText::_( 'FLEXI_CREATE_PDF' );
		
		$button_classes = 'fc_pdfbutton';
		if ( $show_icons==1 ) {
			$caption = '';
			$button_classes .= '';
			$tooltip_place = 'bottom';
		} else {
			$caption = $text;
			if ( !$params->get('btn_grp_dropdown', 0) ) $button_classes .= self::$use_bootstrap ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
			$tooltip_place = !$params->get('btn_grp_dropdown', 0) ? 'bottom' : 'left';
		}
		$button_classes .= ' hasTooltip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);
		
		$link 	= JRoute::_('index.php?view='.FLEXI_ITEMVIEW.'&cid='.$item->categoryslug.'&id='.$item->slug.'&format=pdf');
		$output	= ' <a href="'.$link.'" class="'.$button_classes.'" data-placement="'.$tooltip_place.'" title="'.$tooltip_title.'">'.$image.$caption.'</a>';
		$output	= JText::_( 'FLEXI_ICON_SEP' ) .$output. JText::_( 'FLEXI_ICON_SEP' );

		return $output;
	}
	
	
	/**
	 * Creates the state selector button
	 *
	 * @param int $id
	 * @param array $params
	 * @since 1.0
	 */
	static function statebutton( $item, &$params=null, $addToggler=true )
	{
		// Check for empty params too
		if ( $params && !$params->get('show_state_icon', 1) || JFactory::getApplication()->input->get('print', 0, 'INT') ) return;
		
		$user = JFactory::getUser();
		$db   = JFactory::getDBO();
		$document = JFactory::getDocument();
		$nullDate = $db->getNullDate();
		$app = JFactory::getApplication();

		// Determine general archive privilege
		static $has_archive = null;
		if ($has_archive === null) {
			$permission  = FlexicontentHelperPerm::getPerm();
			$has_archive = $permission->CanArchives;
		}
		
		// Determine edit state, delete privileges of the current user on the given item
		$asset = 'com_content.article.' . $item->id;
		$has_edit_state = $user->authorise('core.edit.state', $asset) || ($user->authorise('core.edit.state.own', $asset) && $item->created_by == $user->get('id'));
		$has_delete     = $user->authorise('core.delete', $asset) || ($user->authorise('core.delete.own', $asset) && $item->created_by == $user->get('id'));
		
		// Display state toggler if it can do any of state change
		$canChangeState = $has_edit_state || $has_delete || $has_archive;

		static $js_and_css_added = false;

		if (!$js_and_css_added && $canChangeState && $addToggler )
		{
			// File exists both in frontend & backend (and is different), so we will use 'base' method and not 'root'
			$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/stateselector.js', FLEXI_VHASH);
			$js ='				
				function fc_setitemstate(state, id)
				{
					var handler = new fc_statehandler({task: "'. ($app->isAdmin() ? 'items.setitemstate' : 'setitemstate') .'"});
					handler.setstate( state, id );
				}';
			$document->addScriptDeclaration($js);
			$js_and_css_added = true;
		}
		
		static $state_names = null;
		static $state_descrs = null;
		static $state_imgs = null;
		static $tooltip_class = null;
		static $state_tips = null;
		static $button_classes = null;
		static $icon_sep = null;
		
		if ( !$state_names )
		{
			$state_names = array(1=>JText::_('FLEXI_PUBLISHED'), -5=>JText::_('FLEXI_IN_PROGRESS'), 0=>JText::_('FLEXI_UNPUBLISHED'), -3=>JText::_('FLEXI_PENDING'), -4=>JText::_('FLEXI_TO_WRITE'), 2=>JText::_('FLEXI_ARCHIVED'), -2=>JText::_('FLEXI_TRASHED'), ''=>JText::_('FLEXI_UNKNOWN'));
			$state_descrs = array(1=>JText::_('FLEXI_PUBLISH_THIS_ITEM'), -5=>JText::_('FLEXI_SET_STATE_AS_IN_PROGRESS'), 0=>JText::_('FLEXI_UNPUBLISH_THIS_ITEM'), -3=>JText::_('FLEXI_SET_STATE_AS_PENDING'), -4=>JText::_('FLEXI_SET_STATE_AS_TO_WRITE'), 2=>JText::_('FLEXI_ARCHIVE_THIS_ITEM'), -2=>JText::_('FLEXI_TRASH_THIS_ITEM'), ''=>'FLEXI_UNKNOWN');
			$state_imgs = array(1=>'tick.png', -5=>'publish_g.png', 0=>'publish_x.png', -3=>'publish_r.png', -4=>'publish_y.png', 2=>'archive.png', -2=>'trash.png', ''=>'unknown.png');
			
			$tooltip_class = ' hasTooltip';
			$state_tips = array();
			$title_header = '';//JText::_( 'FLEXI_ACTION' );
			foreach ($state_names as $state_id => $i) {
				$state_tips[$state_id] = flexicontent_html::getToolTip($title_header, $state_descrs[$state_id], 0);
			}
			
			$button_classes = 'fc_statebutton';
			if ( !$params || !$params->get('btn_grp_dropdown', 0) )
				$button_classes .= self::$use_bootstrap ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
			else
				$button_classes .= ' btn';
			$button_classes .= ' hasTooltip';
			$icon_sep = JText::_( 'FLEXI_ICON_SEP' );
		}
		
		// Create state icon
		$state = $item->state;
		$state_text ='';
		$tmpparams = new JRegistry();
		$tmpparams->set('stateicon_popup', 'none');
		$stateicon = flexicontent_html::stateicon( $state, $tmpparams, $state_text );


		$tz_string = JFactory::getApplication()->getCfg('offset');
		$tz = new DateTimeZone( $tz_string );
		$tz_offset = $tz->getOffset(new JDate()) / 3600;

		// Calculate common variables used to produce output
		$publish_up = JFactory::getDate($item->publish_up);
		$publish_down = JFactory::getDate($item->publish_down);
		$publish_up->setTimezone($tz);
		$publish_down->setTimezone($tz);

		$img_path = JURI::root(true)."/components/com_flexicontent/assets/images/";


		// Create publish information
		$publish_info = '';
		if (isset($item->publish_up)) {
			if ($item->publish_up == $nullDate) {
				$publish_info .= JText::_( 'FLEXI_START_ALWAYS' );
			} else {
				$publish_info .= JText::_( 'FLEXI_START' ) .": ". JHTML::_('date', $publish_up->toSql(), 'Y-m-d H:i:s');
			}
		}
		if (isset($item->publish_down)) {
			if ($item->publish_down == $nullDate) {
				$publish_info .= ($publish_info ? '<br/>' : ''). JText::_( 'FLEXI_FINISH_NO_EXPIRY' );
			} else {
				$publish_info .= ($publish_info ? '<br/>' : ''). JText::_( 'FLEXI_FINISH' ) .": ". JHTML::_('date', $publish_down->toSql(), 'Y-m-d H:i:s');
			}
		}
		$publish_info = $state_text.'<br/>'.$publish_info;


		// Create the state selector button and return it
		if ( $canChangeState && $addToggler )
		{
			// Only add user's permitted states on the current item
			if ($has_edit_state) $state_ids   = array(1, -5, 0, -3, -4);
			if ($has_archive)    $state_ids[] = 2;
			if ($has_delete)     $state_ids[] = -2;

			$box_css = ''; //$app->isSite() ? 'width:182px; left:-100px;' : '';
			$publish_info .= '<br/><br/>'.JText::_('FLEXI_CLICK_TO_CHANGE_STATE');
			
			if ( !$params || !$params->get('show_icons', 2) )
				$tooltip_place = 'bottom';
			else
				$tooltip_place = !$params->get('btn_grp_dropdown', 0) ? 'bottom' : 'left';
			
			$allowed_states = array();
			foreach ($state_ids as $i => $state_id) {
				$allowed_states[] ='
						<li style="display:inline-block; float:left; clear:both;">
							<a href="javascript:void(0);" onclick="fc_setitemstate(\''.$state_id.'\', \''.$item->id.'\')" class="setstate_btn" style="text-decoration:none;">
								<img src="'.$img_path.$state_imgs[$state_id].'" width="16" height="16" style="border-width:0;" alt="State" /> '.$state_tips[$state_id].'
							</a>
						</li>';
			}
			$tooltip_title = flexicontent_html::getToolTip(JText::_( 'FLEXI_PUBLISH_INFORMATION' ), $publish_info, 0);
			$output = '
			<ul class="statetoggler">
				<li class="topLevel">
					<a href="javascript:void(0);" onclick="fc_toggleStateSelector(this)" id="row'.$item->id.'" class="stateopener '.$button_classes.'" data-placement="'.$tooltip_place.'" title="'.$tooltip_title.'">
						'.$stateicon.'
					</a>
					<div class="options" style="'.$box_css.'" onclick="fc_toggleStateSelector(this)">
						<ul>
						<li style="text-align:center;"><b>'.JText::_( 'FLEXI_ACTION' ).'</b></li>
						'.implode('', $allowed_states).'
						</ul>
					</div>
				</li>
			</ul>';
		}
		
		else if ($app->isAdmin())  // Backend, possibly with state selector disabled
		{
			if ($canChangeState) $publish_info .= '<br/><br/>'.JText::_('FLEXI_STATE_CHANGER_DISABLED');
			
			$tooltip_title = flexicontent_html::getToolTip(JText::_( 'FLEXI_PUBLISH_INFORMATION' ), $publish_info, 0);
			$output = '
				<div id="row'.$item->id.'">
					<span class="'.$tooltip_class.'" title="'.$tooltip_title.'">
						'.$stateicon.'
					</span>
				</div>';
			$output	= $icon_sep .$output. $icon_sep;
		}
		
		else
		{
			$output = '';  // frontend with no permissions to edit / delete / archive
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
	static function approvalbutton( $item, &$params)
	{
		if ( JFactory::getApplication()->input->get('print', 0, 'INT') ) return;
		
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
		$has_edit_state = $user->authorise('core.edit.state', $asset) || ($user->authorise('core.edit.state.own', $asset) && $item->created_by == $user->get('id'));
		// ALTERNATIVE 1
		//$rights = FlexicontentHelperPerm::checkAllItemAccess($user->get('id'), 'item', $item->id);
		//$has_edit_state = in_array('edit.state', $rights) || (in_array('edit.state.own', $rights) && $item->created_by == $user->get('id')) ;

		// Create the approval button only if user cannot edit the item (**note check at top of this method)
		if ( $has_edit_state ) return;
		
		$show_icons = $params->get('show_icons', 2);
		$use_font   = $params->get('use_font_icons', 1);
		if ( $show_icons && $use_font ) {
			static $icon_class = null;
			if ($icon_class == null) {
				if (self::$icon_classes==null) self::load_class_config();
				$icon_class = empty(self::$icon_classes['approval']) ? 'icon-key' : self::$icon_classes['approval'];
				$icon_class .= ($show_icons==2 ? ' fcIconPadRight' : '');
			}
			$attribs = '';
			$image = '<i class="'.$icon_class.'"></i>';
		} else if ( $show_icons ) {
			$attribs = '';
			$image = JHTML::image('components/com_flexicontent/assets/images/'.'key_add.png', JText::_( 'FLEXI_APPROVAL_REQUEST' ), $attribs);
		} else {
			$image = '';
		}
		
		$overlib 	= JText::_( 'FLEXI_APPROVAL_REQUEST_INFO' );
		$text 		= JText::_( 'FLEXI_APPROVAL_REQUEST' );
		
		$button_classes = 'fc_approvalbutton';
		if ( $show_icons==1 ) {
			$caption = '';
			$button_classes .= '';
			$tooltip_place = 'bottom';
		} else {
			$caption = $text;
			if ( !$params->get('btn_grp_dropdown', 0) ) $button_classes .= self::$use_bootstrap ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
			$tooltip_place = !$params->get('btn_grp_dropdown', 0) ? 'bottom' : 'left';
		}
		$button_classes .= ' hasTooltip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);
		
		$link = 'index.php?option=com_flexicontent&task=approval&cid='.$item->id;
		$output	= ' <a href="'.$link.'" class="'.$button_classes.'" data-placement="'.$tooltip_place.'" title="'.$tooltip_title.'">'.$image.$caption.'</a>';
		$output	= JText::_( 'FLEXI_ICON_SEP' ) .$output. JText::_( 'FLEXI_ICON_SEP' );
		
		return $output;
	}


	/**
	 * Creates the edit button
	 *
	 * @param int $id
	 * @param array $params
	 * @since 1.0
	 */
	static function editbutton( $item, &$params)
	{
		if ( !$params->get('show_editbutton', 1) || JFactory::getApplication()->input->get('print', 0, 'INT') ) return;
		
		$user	= JFactory::getUser();
		
		// Determine if current user can edit the given item
		$has_edit_state = false;
		$asset = 'com_content.article.' . $item->id;
		$has_edit_state = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $item->created_by == $user->get('id'));
		// ALTERNATIVE 1
		//$rights = FlexicontentHelperPerm::checkAllItemAccess($user->get('id'), 'item', $item->id);
		//$has_edit_state = in_array('edit', $rights) || (in_array('edit.own', $rights) && $item->created_by == $user->get('id')) ;

		// Create the edit button only if user can edit the give item
		if ( !$has_edit_state ) return;
		
		$show_icons = $params->get('show_icons', 2);
		$use_font   = $params->get('use_font_icons', 1);
		if ( $show_icons && $use_font ) {
			static $icon_class = null;
			if ($icon_class == null) {
				if (self::$icon_classes==null) self::load_class_config();
				$icon_class = empty(self::$icon_classes['edit']) ? 'icon-pencil' : self::$icon_classes['edit'];
				$icon_class .= ($show_icons==2 ? ' fcIconPadRight' : '');
			}
			$attribs = '';
			$image = '<i class="'.$icon_class.'"></i>';
		} else if ( $show_icons ) {
			$attribs = '';
			$image = JHTML::image(FLEXI_ICONPATH.'edit.png', JText::_( 'FLEXI_EDIT' ), $attribs);
		} else {
			$image = '';
		}
		
		$overlib 	= JText::_( 'FLEXI_EDIT_TIP' );
		$text 		= JText::_( 'FLEXI_EDIT' );
		
		$button_classes = 'fc_editbutton';
		if ( $show_icons==1 ) {
			$caption = '';
			$button_classes .= '';
			$tooltip_place = 'bottom';
		} else {
			$caption = $text;
			if ( !$params->get('btn_grp_dropdown', 0) ) $button_classes .= self::$use_bootstrap ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
			$tooltip_place = !$params->get('btn_grp_dropdown', 0) ? 'bottom' : 'left';
		}
		$button_classes .= ' hasTooltip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);
		
		if ( $params->get('show_editbutton', 1) == '1') {
			//$Itemid = JRequest::getInt('Itemid', 0);  // Maintain menu item ? e.g. current category view, 
			$Itemid = 0;
			$item_url = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, $Itemid, $item));
			$link = $item_url  .(strstr($item_url, '?') ? '&' : '?').  'task=edit';
			$targetLink = "_self";
		} else if ( $params->get('show_editbutton', 1) == '2' ) {
			$link = JURI::base(true).'/administrator/index.php?option=com_flexicontent&task=items.edit&cid[]='.$item->id;
			$targetLink = "_blank";
		}
		$output	= ' <a href="'.$link.'" class="'.$button_classes.'" data-placement="'.$tooltip_place.'" target="'.$targetLink.'" title="'.$tooltip_title.'">'.$image.$caption.'</a>';
		$output	= JText::_( 'FLEXI_ICON_SEP' ) .$output. JText::_( 'FLEXI_ICON_SEP' );
		
		return $output;
	}
	
	
	/**
	 * Creates the add button
	 *
	 * @param array $params
	 * @since 1.0
	 */
	static function addbutton(&$params, &$submit_cat = null, $menu_itemid = 0, $btn_text = '', $auto_relations = false, $ignore_unauthorized = false)
	{
		if ( !$params->get('show_addbutton', 1) || JFactory::getApplication()->input->get('print', 0, 'INT') ) return;
		
		// Currently add button will appear to logged users only
		// ... unless unauthorized users are allowed
		$user	= JFactory::getUser();
		if ( !$user->id && $ignore_unauthorized < 2 ) return '';
		
		
		// IF not auto-relation given ... then check if current view / layout can use ADD button
		$view = JRequest::getVar('view');
		$layout = JRequest::getVar('layout', 'default');
		if ( !$auto_relations )
		{
			if ( $view!='category' || $layout == 'author' || $layout == 'favs' ) return '';
		}
		
		
		// *********************************************************************
		// Check if user can ADD to (a) given category or to (b) at any category
		// *********************************************************************
		
		// (a) Given category
		if ( $submit_cat && $submit_cat->id )
		{
			$canAdd = $user->authorise('core.create', 'com_content.category.' . $submit_cat->id);
		}
		
		// (b) Any category (or to the CATEGORY IDS of given CATEGORY VIEW OBJECT)
		else
		{
			// Given CATEGORY VIEW OBJECT may limit to specific category ids
			$canAdd = $user->authorise('core.create', 'com_flexicontent');
			
			if ($canAdd === NULL && $user->id) {
				// Performance concern (NULL for $canAdd) means SOFT DENY, also check for logged user
				// thus to avoid checking some/ALL categories for "create" privelege for unlogged users
				$specific_catids = $submit_cat ? @ $submit_cat->ids  :  false;
				if ($specific_catids && count($specific_catids) > 3) $specific_catids = false;
				$allowedcats = FlexicontentHelperPerm::getAllowedCats( $user, $actions_allowed=array('core.create'), $require_all=true, $check_published = true, $specific_catids, $find_first = true );
				$canAdd = count($allowedcats);
			}
		}
		
		if ( !$canAdd && !$ignore_unauthorized ) return '';
		
		
		// ******************************
		// Create submit button/icon text
		// ******************************
		
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


		// ***********
		// Create link
		// ***********

		// Add Itemid (if given) and do SEF URL routing it --before-- appending more variables, so that
		// ... menu item URL variables from given menu item ID will be appended if SEF URLs are OFF
		$menu_itemid = $menu_itemid ? $menu_itemid : (int)$params->get('addbutton_menu_itemid', 0);
		$link  = 'index.php?option=com_flexicontent';
		$link .= $menu_itemid  ? '&amp;Itemid='.$menu_itemid  :  '&amp;view='.FLEXI_ITEMVIEW.'&amp;task=add';
		$link  = JRoute::_($link);
		
		// Add main category ID (if given)
		if ($submit_cat && $submit_cat->id) {
			$link .= (strstr($link, '?') ? '&amp;' : '?') . 'maincat='.$submit_cat->id;
		}
		
		// Append autorelate information to the URL (if given)
		if ($auto_relations) foreach ( $auto_relations as $auto_relation )
		{
			$link .= (strstr($link, '?') ? '&amp;' : '?') . 'autorelation_'.$auto_relation->fieldid.'='.$auto_relation->itemid;
		}


		// ***************************************
		// Finally create the submit icon / button
		// ***************************************

		$show_icons = $params->get('show_icons', 2);
		$use_font   = $params->get('use_font_icons', 1);
		if ( $show_icons && $use_font && !$auto_relations ) {
			static $icon_class = null;
			if ($icon_class == null) {
				if (self::$icon_classes==null) self::load_class_config();
				$icon_class = empty(self::$icon_classes['new']) ? 'icon-new' : self::$icon_classes['new'];
				$icon_class .= ($show_icons==2 ? ' fcIconPadRight' : '');
			}
			$attribs = '';
			$image = '<i class="'.$icon_class.'"></i>';
		} else if ( $show_icons && !$auto_relations ) {
			$attribs = '';
			$image = JHTML::image('components/com_flexicontent/assets/images/'.'plus-button.png', $btn_desc, $attribs);
		} else {
			$image = '';
		}

		$button_classes = 'fc_addbutton';
		if ( $show_icons==1 && !$auto_relations ) {
			$caption = '';
			$button_classes .= '';
			$tooltip_place = 'bottom';
		} else {
			$caption = $btn_title;
			if ( !$params->get('btn_grp_dropdown', 0) ) $button_classes .=
				(self::$use_bootstrap ? ' btn btn-small' : ' fc_button fcsimple fcsmall')
				.($auto_relations ? ' btn-success' : '');
			$tooltip_place = !$params->get('btn_grp_dropdown', 0) ? 'bottom' : 'left';
		}
		$button_classes .= ' hasTooltip';
		$tooltip_title = flexicontent_html::getToolTip($btn_title, $btn_desc, 0);

		$output	= ' <a href="'.$link.'" class="'.$button_classes.'" data-placement="'.$tooltip_place.'" title="'.$tooltip_title.'">'.$image.$caption.'</a>';
		if (!$auto_relations)
		{
			$output	= JText::_( 'FLEXI_ICON_SEP' ) .$output. JText::_( 'FLEXI_ICON_SEP' );
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
	static function stateicon( $state, &$params, &$state_text=null )
	{
		static $state_names = null;
		static $state_imgs = null;
		static $state_basictips = null;
		static $state_fulltips = null;
		if ( !$state_names ) {
			$state_names = array(1=>JText::_('FLEXI_PUBLISHED'), -5=>JText::_('FLEXI_IN_PROGRESS'), 0=>JText::_('FLEXI_UNPUBLISHED'), -3=>JText::_('FLEXI_PENDING'), -4=>JText::_('FLEXI_TO_WRITE'), 2=>JText::_('FLEXI_ARCHIVED'), -2=>JText::_('FLEXI_TRASHED'), ''=>JText::_('FLEXI_UNKNOWN'));
			$state_imgs = array(1=>'tick.png', -5=>'publish_g.png', 0=>'publish_x.png', -3=>'publish_r.png', -4=>'publish_y.png', 2=>'archive.png', -2=>'trash.png', ''=>'unknown.png');
		}
		
		if ( !$state_fulltips ) {
			$title = JText::_( 'FLEXI_STATE' );
			foreach($state_names as $state_id => $state_name) {
				$content = str_replace('::', '-', $state_name);
				$state_fulltips[$state_id] = flexicontent_html::getToolTip($title, $content, 0);
			}
		}
		
		if ( !$state_basictips ) {
			foreach($state_names as $state_id => $state_name) {
				$content = !FLEXI_J30GE ? str_replace('::', '-', $state_name) : $state_name;
				$state_basictips[$state_id] = $title.' : '.$content;
			}
		}
		
		// Check for invalid state
		if ( !isset($state_names[$state]) ) $state = '';
		
		// Create popup text
		switch ( $params->get('stateicon_popup', 'full') )
		{
			case 'basic':
				$attribs = 'title="'.$state_basictips[$state].'"';
				break;
			case 'none':
				$attribs = '';
				break;
			case 'full': default:
				$tooltip_class = ' hasTooltip';
				$attribs = 'class="fc_stateicon '.$tooltip_class.'" title="'.$state_fulltips[$state].'"';
				break;
		}
		
		// Create state icon image
		$app = JFactory::getApplication();
		$path = (!FLEXI_J16GE && $app->isAdmin() ? '../' : '').'components/com_flexicontent/assets/images/';
		if ( $params->get('show_icons', 1) ) {
			$img = $state_imgs[$state];
			$icon = JHTML::image($path.$img, $state_names[$state], $attribs);
		} else {
			$icon = $state_names[$state];
		}
		
		return $icon;
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
		//sql calculation doesn't work with negative values and thus only minus votes will not be taken into account
		if ($item->votes == 0) {
			return '<span class="badge">'.JText::_( 'FLEXI_NOT_YET_RATED' ).'</span>';
		}

		//we do the rounding here and not in the query to get better ordering results
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
			$show_icons = $params->get('show_icons');
			if ( $show_icons ) {
				$voteup = JHTML::image('components/com_flexicontent/assets/images/'.'thumb_up.png', JText::_( 'FLEXI_GOOD' ), NULL);
				$votedown = JHTML::image('components/com_flexicontent/assets/images/'.'thumb_down.png', JText::_( 'FLEXI_BAD' ), NULL);
			} else {
				$voteup = JText::_( 'FLEXI_GOOD' ). '&nbsp;';
				$votedown = '&nbsp;'.JText::_( 'FLEXI_BAD' );
			}
			$tip_vote_up = flexicontent_html::getToolTip('FLEXI_VOTE_UP', 'FLEXI_VOTE_UP_TIP', 1, 1);
			$tip_vote_down = flexicontent_html::getToolTip('FLEXI_VOTE_DOWN', 'FLEXI_VOTE_DOWN_TIP', 1, 1);
		}
		
		$item_url = JRoute::_('index.php?task=vote&vote=1&cid='.$item->categoryslug.'&id='.$item->slug.'&layout='.$params->get('ilayout'));
		$link = $item_url .(strstr($item_url, '?') ? '&' : '?');
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
	static function ItemVote( &$field, $xid, $vote )
	{
		// Check for invalid xid
		if ($xid!='main' && $xid!='extra' && $xid!='all' && !(int)$xid) {
			$html .= "ItemVote(): invalid xid '".$xid."' was given";
			return;
		}
		
		if (!$vote) {
			$vote = new stdClass();
			$vote->rating_sum = $vote->rating_count = 0;
		} else if (!isset($vote->rating_sum) || !isset($vote->rating_sum)) {
			$vote->rating_sum = $vote->rating_count = 0;
		}

		$html = '';
		$int_xid = (int)$xid;
		$item_id = $field->item_id;
		
		
		// Get extra voting option (composite voting)
		$xids = array();
		if ( ($xid=='all' || $xid=='extra' || $int_xid) && ($enable_extra_votes = $field->parameters->get('enable_extra_votes', '')) )
		{
			// Retrieve and split-up extra vote types, (removing last one if empty)
			$extra_votes = $field->parameters->get('extra_votes', '');
			$extra_votes = preg_split("/[\s]*%%[\s]*/", $extra_votes);
			if ( empty($extra_votes[count($extra_votes)-1]) )
			{
				unset( $extra_votes[count($extra_votes)-1] );
			}
			
			// Split extra voting ids (xid) and their titles
			foreach ($extra_votes as $extra_vote) {
				@list($extra_id, $extra_title, $extra_desc) = explode("##", $extra_vote);
				$xids[$extra_id] = new stdClass();
				$xids[$extra_id]->id    = (int)$extra_id;
				$xids[$extra_id]->title = JText::_($extra_title);
				$xids[$extra_id]->desc  = JText::_($extra_desc);
			}
		}
		
		// Get user current history so that it is reflected on the voting
		$vote_history = JFactory::getSession()->get('vote_history', array(),'flexicontent');
		if ( !isset($vote_history[$item_id]) || !is_array($vote_history[$item_id]) )
		{
			$vote_history[$item_id] = array();
		}
		
		// Add main vote option
		if ($xid=='main' || $xid=='all')
		{
			$vote_label = JText::_($field->parameters->get('main_label', 'FLEXI_VOTE_AVERAGE_RATING'));
			$counter_show_label = $field->parameters->get('main_counter_show_label', 1);
			$add_review_form = $field->parameters->get('allow_reviews', 0);
			$html .= flexicontent_html::ItemVoteDisplay( $field, $item_id, $vote->rating_sum, $vote->rating_count, 'main', $vote_label,
				$stars_override=0, $allow_vote=true, $vote_counter='default', $counter_show_label, $add_review_form, $xids, $review_type='item' );
		}
		
		if ( $xid=='all' || $xid=='extra' || ($int_xid && isset($xids[$xid])) )
		{
			if ( $int_xid )
				$_xids = array($int_xid => $xids[$int_xid]);
			else
				$_xids = & $xids;
			
			$counter_show_label = $field->parameters->get('extra_counter_show_label', 1);
			foreach ( $_xids as $extra_id => $xid_obj)
			{
				if ( !isset($vote->extra[$extra_id]) ) {
					$extra_vote = new stdClass();
					$extra_vote->rating_sum = $extra_vote->rating_count = 0;
					$extra_vote->extra_id = $extra_id;
				} else {
					$extra_vote = $vote->extra[$extra_id];
				}
				
				// Display incomplete vote
				if ( (int)$extra_id && !isset($vote_history[$item_id]['main']) && isset($vote_history[$item_id][$extra_id]) ) {
					$_rating_sum = $vote_history[$item_id][$extra_id];
					$rating_count = 1;
				} else {
					$_rating_sum = 0;
					$rating_count = 0;
				}
				$html .= flexicontent_html::ItemVoteDisplay( $field, $item_id, ($extra_vote->rating_sum + $_rating_sum), ($extra_vote->rating_count + $rating_count), $extra_vote->extra_id, $xid_obj,
					$stars_override=0, $allow_vote=true, $vote_counter='default', $counter_show_label );
			}
		}
		
		return '<div class="'.$field->name.'-group">' .$html. '</div>';
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
	static function ItemVoteDisplay( &$field, $id, $rating_sum, $rating_count, $xid, $xiddata='', $stars_override=0, $allow_vote=true, $vote_counter='default', $counter_show_label=true, $add_review_form=0, $xids=array(), $review_type='item' )
	{
		static $acclvl_names  = null;
		static $star_tooltips = null;
		static $star_classes  = null;
		$user = JFactory::getUser();
		$db   = JFactory::getDBO();
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		
		flexicontent_html::__DEV_check_reviews_table();
		
		if (!is_object($xiddata)) {
			// Only label given
			$label = $xiddata;
			$desc = '';
		} else {
			// label & desc
			$label = $xiddata->title;
			$desc  = $xiddata->desc;
		}
		$int_xid = (int)$xid;
		
		
		// *****************************************************
		// Find if user has the ACCESS level required for voting
		// *****************************************************
		
		static $has_acclvl;
		if ($has_acclvl===null)
		{
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$acclvl = (int) $field->parameters->get('submit_acclvl', 1);
			$has_acclvl = in_array($acclvl, $aid_arr);
		}
		
		
		// *********************************************************
		// Calculate NO access actions, (case that user cannot vote)
		// *********************************************************
		
		if ( !$has_acclvl )
		{
			if ($user->id) {
				$no_acc_msg = $field->parameters->get('logged_no_acc_msg', '');
				$no_acc_url = $field->parameters->get('logged_no_acc_url', '');
				$no_acc_doredirect  = $field->parameters->get('logged_no_acc_doredirect', 0);
				$no_acc_askredirect = $field->parameters->get('logged_no_acc_askredirect', 1);
			} else {
				$no_acc_msg  = $field->parameters->get('guest_no_acc_msg', '');
				$no_acc_url  = $field->parameters->get('guest_no_acc_url', '');
				$no_acc_doredirect  = $field->parameters->get('guest_no_acc_doredirect', 2);
				$no_acc_askredirect = $field->parameters->get('guest_no_acc_askredirect', 1);
			}
			
			// Decide no access Redirect URLs
			if ($no_acc_doredirect == 2) {
				$com_users = 'com_users';
				$no_acc_url = $cparams->get('login_page', 'index.php?option='.$com_users.'&view=login');
			} else if ($no_acc_doredirect == 0) {
				$no_acc_url = '';
			} // else unchanged
			
			
			// Decide no access Redirect Message
			$no_acc_msg = $no_acc_msg ? JText::_($no_acc_msg) : '';
			if ( !$no_acc_msg )
			{
				// Find name of required Access Level
				$acclvl_name = '';
				if ($acclvl && empty($acclvl_names)) {  // Retrieve this ONCE (static var)
					$acclvl_names = flexicontent_db::getAccessNames();
				}
				$acclvl_name =  !empty($acclvl_names[$acclvl]) ? $acclvl_names[$acclvl] : "Access Level: ".$acclvl." not found/was deleted";
				$no_acc_msg = JText::sprintf( 'FLEXI_NO_ACCESS_TO_VOTE' , $acclvl_name);
			}
			$no_acc_msg_redirect = JText::_($no_acc_doredirect==2 ? 'FLEXI_CONFIM_REDIRECT_TO_LOGIN_REGISTER' : 'FLEXI_CONFIM_REDIRECT');
		}
		
		if ($vote_counter !== 'default' &&  $vote_counter!=='')
			$counter = $vote_counter ? 1 : 0;
		else
			$counter = $field->parameters->get( ($int_xid ? 'extra_counter' : 'main_counter'), 1 );
		$show_unrated = $field->parameters->get( 'show_unrated', 0 );  // Display info e.g. counter if unrated, TODO add parameter
		$show_percentage = $field->parameters->get( ($int_xid ? 'extra_counter_show_percentage' : 'main_counter_show_percentage'), 0 );
		$class   = $field->name.'-row';
		
		// Get number of displayed stars, configuration
		$rating_resolution = (int)$field->parameters->get('rating_resolution', 5);
		$rating_resolution = $rating_resolution >= 5   ?  $rating_resolution  :  5;
		$rating_resolution = $rating_resolution <= 100  ?  $rating_resolution  :  100;
		
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
			$document->addStyleSheetVersion(JURI::root(true).'/components/com_flexicontent/assets/css/fcvote.css', FLEXI_VHASH);
			$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/fcvote.js', FLEXI_VHASH);

			$document->addScriptDeclaration('var fcvote_rfolder = "'.JURI::root(true).'";');

			$image = $field->parameters->get( 'main_image', 'components/com_flexicontent/assets/images/star-medium.png' );
			$img_path	= JURI::root(true).'/'.$image;
			
			$dim = $field->parameters->get( 'main_dimension', 24 );
			$element_width = $rating_resolution * $dim;
			if ($rating_stars) $element_width = (int) $element_width * ($rating_stars / $rating_resolution);
			
			$css = '
			/* This is via voting field parameter, please edit field configuration to override them*/
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
			$img_path	= JURI::root(true).'/'.$image;
			
			$dim = $field->parameters->get( 'extra_dimension', 24 );
			$element_width = $rating_resolution * $dim;
			if ($rating_stars) $element_width = (int) $element_width * ($rating_stars / $rating_resolution);
			
			$css .= '
			/* This is via voting field parameter, please edit field configuration to override them*/
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
			
			$star_tooltips = array();
			$star_classes  = array();
			for ($i=1; $i<=$rating_resolution; $i++) {
				$star_zindex  = $rating_resolution - $i + 2;
				$star_percent = (int) round(100 * ($i / $rating_resolution));
				$css .= '.'.$class.' div.fcvote ul.fcvote_list > .voting-links a.star'.$i.' { width: '.$star_percent.'%!important; z-index: '.$star_zindex.'; }' ."\n";
				$star_classes[$i] = 'star'.$i;
				if ($star_percent < 20)       $star_tooltips[$i] = JText::_( 'FLEXI_VERY_POOR' );
				else if ($star_percent < 40)  $star_tooltips[$i] = JText::_( 'FLEXI_POOR' );
				else if ($star_percent < 60)  $star_tooltips[$i] = JText::_( 'FLEXI_REGULAR' );
				else if ($star_percent < 80)  $star_tooltips[$i] = JText::_( 'FLEXI_GOOD' );
				else                          $star_tooltips[$i] = JText::_( 'FLEXI_VERY_GOOD' );
				$star_tooltips[$i] .= ' '.$i.'/'.$rating_resolution;
			}
			
			$document->addStyleDeclaration($css);
			$js_and_css_added = true;
		}
		
		$percent = 0;
		$factor = (int) round(100/$rating_resolution);
		if ($rating_count != 0) {
			$percent = number_format((intval($rating_sum) / intval( $rating_count ))*$factor,2);
		} elseif ($show_unrated == 0) {
			$counter = -1;
		}

		if ( $int_xid ) {
			// Disable showing vote counter in extra votes
			if ( $counter == 2 ) $counter = 0;
		} else {
			// Disable showing vote counter in main vote
			if ( $counter == 3 ) $counter = 0;
		}
		$nocursor = !$allow_vote ? 'cursor:unset!important;' : '';
		
		$html_vote_links = '';
		if ($allow_vote)
		{
			// HAS Voting ACCESS
			if ( $has_acclvl ) {
				$href = 'javascript:;';
				$onclick = '';
			}
			// NO Voting ACCESS
			else {
				// WITHOUT Redirection
				if ( !$no_acc_url ) {
					$href = 'javascript:;';
					$popup_msg = addcslashes($no_acc_msg, "'");
					$onclick = 'alert(\''.$popup_msg.'\');';
				}
				// WITH Redirection
				else {
					$href = $no_acc_url;
					$popup_msg = addcslashes($no_acc_msg . ' ... ' . $no_acc_msg_redirect, "'");
					
					if ($no_acc_askredirect==2)       $onclick = 'return confirm(\''.$popup_msg.'\');';
					else if ($no_acc_askredirect==1)  $onclick = 'alert(\''.$popup_msg.'\'); return true;';
					else                              $onclick = 'return true;';
				}
			}
			
			$dovote_class = $has_acclvl ? 'fc_dovote' : '';
			for ($i=1; $i<=$rating_resolution; $i++) {
				$html_vote_links .= '
					<li class="voting-links"><a onclick="'.$onclick.'" href="'.$href.'" title="'.$star_tooltips[$i].'" class="'.$dovote_class.' '.$star_classes[$i].'" data-rel="'.$id.'_'.$xid.'">'.$i.'</a></li>';
			}
		}
		
		return '
		<div class="'.$class.' '.$class.'_'.$xid.'">
			<div class="fcvote fcvote-box-'.$xid.'">
				<div class="nowrap_box fcvote-label-outer">
					'.($label ? '<div id="fcvote_lbl'.$id.'_'.$xid.'" class="fcvote-label xid-'.$xid.'">'.$label.'</div>' : '').'
					<div id="fcvote_cnt_'.$id.'_'.$xid.'" class="fc-mssg-inline fc-info fc-iblock fc-nobgimage fcvote-count" '.( ($counter==-1 || $counter==0) && !$show_percentage ? 'style="display:none;"' : '' ).'>'.
						($show_percentage ? ((int)$percent ? (int)$percent.'%' : '') : '').
						( $counter==-1 || $counter==0 ? '' :
							($show_percentage && (int)$percent ? ' - ' : '').
							($rating_count ? $rating_count : '0').
							($counter_show_label ? ' '.JText::_( $rating_count!=1 ? 'FLEXI_VOTES' : 'FLEXI_VOTE' ) : '')
						).'
					</div>'.
					(!(int)$percent ? '' : '
					<span itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">
						<meta itemprop="ratingValue" content="'.round($percent).'" />
						<meta itemprop="bestRating"  content="100" />
						<meta itemprop="ratingCount" content="'.$rating_count.'" />
					</span>').'
				</div>
				<ul class="fcvote_list">
					<li id="rating_'.$id.'_'.$xid.'" class="current-rating" style="width:'.(int)$percent.'%;'.$nocursor.'"></li>
					'.$html_vote_links.'
				</ul>
				<div id="fcvote_message_'.$id.'_'.$xid.'" class="fcvote_message" ></div>
				'.( $desc ? '<div class="fcvote-desc">'.$desc.'</div>' :'' ).'
			</div>
			'.($add_review_form ? '
			<input type="button" class="btn fcvote_toggle_review_form" style="vertical-align:top;"
				onclick="fcvote_open_review_form(jQuery(\'#fcvote_review_form_box_'.$id.'\').attr(\'id\'), '.$id.', \''.$review_type.'\')"
				value="'.JText::_('FLEXI_VOTE_REVIEW_THIS_ITEM').'"/>
			<span class="fcclear"></span>
			<div id="fcvote_review_form_box_'.$id.'" class="fcvote_review_form_box" style="display:none;"></div>' : '').'
		</div>';
	}
	
	
	static function __DEV_check_reviews_table()
	{
		static $check_review_table_dev = null;
		if ($check_review_table_dev !== null) return;
		$check_review_table_dev = 1;
		
		$app = JFactory::getApplication();
		$db  = JFactory::getDBO();
		$dbprefix = $app->getCfg('dbprefix');
		
		$query = 'SHOW TABLES LIKE "' . $dbprefix . 'flexicontent_reviews_dev"';
		$db->setQuery($query);
		$reviews_tbl_exists = count($db->loadObjectList());
		
		if ( !$reviews_tbl_exists )
		{
			$query = "
			CREATE TABLE IF NOT EXISTS `#__flexicontent_reviews_dev` (
				`id` int(11) NOT NULL auto_increment,
			  `content_id` int(11) NOT NULL,
			  `type` varchar(255) NOT NULL DEFAULT 'item',
			  `average_rating` float NOT NULL,
			  `custom_ratings` text NOT NULL DEFAULT '',
			  `user_id` int(11) NOT NULL DEFAULT '0',
			  `email` varchar(255) NOT NULL DEFAULT '',
			  `title` varchar(255) NOT NULL,
			  `text` mediumtext NOT NULL,
			  `state` tinyint(3) NOT NULL DEFAULT '0',
				`approved` tinyint(3) NOT NULL DEFAULT '0',
				`useful_yes` int(11) NOT NULL DEFAULT '0',
				`useful_no` int(11) NOT NULL DEFAULT '0',
			  `submit_date` datetime NOT NULL,
			  `update_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				`checked_out` int(11) unsigned NOT NULL default '0',
				`checked_out_time` datetime NOT NULL default '0000-00-00 00:00:00',
			  `attribs` mediumtext NULL,
				PRIMARY KEY  (`id`),
			  UNIQUE (`content_id`, `user_id`, `type`),
			  KEY (`content_id`, `type`),
			  KEY `user_id` (`user_id`)
			) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`;";
		}
		$db->setQuery($query);
		$db->execute();
	}
	
		
	/**
	 * Creates the favourited by user list
	 *
	 * @param array $params
	 * @since 1.0
	 */
	static function favoured_userlist(&$field, &$item,  $favourites)
	{
		$users_counter = (int) $field->parameters->get('display_favoured_usercount', 0);
		$users_list_type = (int) $field->parameters->get('display_favoured_userlist', 0);
		$users_list_limit = (int) $field->parameters->get('display_favoured_max', 12);
		
		// No user favouring the item yet
		if (!$favourites) return;
		
		// Nothing to do if all options disabled
		if (!$users_counter && !$users_list_type)  return;
		
		$favuserlist = '
			<div class="fc-mssg-inline fc-info fc-iblock fc-nobgimage fcfavs-subscribers-count">
				'.($users_counter ? JText::_('FLEXI_TOTAL').': '.$favourites.' '.JText::_('FLEXI_USERS') : '');
		
		if ( $users_list_type )
		{
			$uname = $users_list_type==1 ? "u.username" : "u.name";
			
			$db	= JFactory::getDBO();
			$query = 'SELECT '.($users_list_type==1 ? "u.username" : "u.name")
				.' FROM #__flexicontent_favourites AS ff'
				.' LEFT JOIN #__users AS u ON u.id=ff.userid '
				.' WHERE ff.itemid=' . $item->id;
			$db->setQuery($query);
			$favusers = $db->loadColumn();
			
			if (is_array($favusers) && count($favusers))
			{
				$count = 0;
				foreach($favusers as $favuser)
				{
					$_list[] = $favuser;
					if ($count++ >= $users_list_limit) break;
				}
				$favuserlist .= ($users_counter ? ': ' : '') . implode(', ', $_list) . (count($favusers) > $users_list_limit ? ' ...' : '');
			}
		}
		
		$favuserlist .= '
			</div>';
		return $favuserlist;
	}

	/**
	 * Creates the favourite icons
	 *
	 * @param array $params
	 * @since 1.0
	 */
	static function favicon($field, $favoured, $item, $type='item')
	{
		$users_counter = (int) $field->parameters->get('display_favoured_usercount', 0);
		
		$user = JFactory::getUser();
		$item_id = $item->id;  // avoid using $field, we also support favoured categories
		$item_title = $item->title;

		static $js_and_css_added = false;
		static $tooltip_class, $addremove_tip, $img_fav_add, $img_fav_delete;

		if (!$js_and_css_added)
		{
			$document	= JFactory::getDocument();
			$cparams = JComponentHelper::getParams( 'com_flexicontent' );
			
			$tooltip_class = ' hasTooltip';
			$text 		= $user->id ? 'FLEXI_ADDREMOVE_FAVOURITE' : 'FLEXI_FAVOURE';
			$overlib 	= $user->id ? 'FLEXI_ADDREMOVE_FAVOURITE_TIP' : 'FLEXI_FAVOURE_LOGIN_TIP';
			$addremove_tip = flexicontent_html::getToolTip($text, $overlib, 1, 1);
			
			// Make sure mootools are loaded before our js
			//JHtml::_('behavior.framework', true);
			
			// Load tooltips JS
			if ($cparams->get('add_tooltips', 1)) JHtml::_('bootstrap.tooltip');
			
			flexicontent_html::loadFramework('jQuery');
			flexicontent_html::loadFramework('flexi_tmpl_common');
			
			$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/fcfav.js', FLEXI_VHASH);
			
			JText::script('FLEXI_YOUR_BROWSER_DOES_NOT_SUPPORT_AJAX',true);
			JText::script('FLEXI_LOADING',true);
			JText::script('FLEXI_ADDED_TO_YOUR_FAVOURITES',true);
			JText::script('FLEXI_YOU_NEED_TO_LOGIN',true);
			JText::script('FLEXI_REMOVED_FROM_YOUR_FAVOURITES',true);
			JText::script('FLEXI_USERS',true);  //5
			JText::script('FLEXI_FAVOURE',true);
			JText::script('FLEXI_REMOVE_FAVOURITE',true); //7
			JText::script('FLEXI_FAVS_YOU_HAVE_SUBSCRIBED',true);
			JText::script('FLEXI_FAVS_CLICK_TO_SUBSCRIBE',true);
			JText::script('FLEXI_TOTAL',true);
			$js = "
				var fcfav_rfolder = '".JURI::root(true)."';
			";
			$document->addScriptDeclaration($js);
			
			$js_and_css_added = true;
		}

		$output = "";

		if ($user->id && $favoured)
		{
			$alt_text = JText::_( 'FLEXI_REMOVE_FAVOURITE' );
			if (!$img_fav_delete) {
				$img_fav_delete = JHTML::image('components/com_flexicontent/assets/images/'.'heart_delete.png', $alt_text, NULL);
			}
			$onclick 	= "javascript:FCFav(".$item_id.", '".$type."', ".$users_counter.")";
			$link 		= "javascript:void(null)";

			$output		.=
				 '<span class="fcfav_delete">'
				.' <a id="favlink_'.$type.'_'.$item_id.'" href="'.$link.'" onclick="'.$onclick.'" class="btn fcfav-reponse'.$tooltip_class.'" title="'.$addremove_tip.'">'.$img_fav_delete.'</a>'
				.' <span class="fav_item_id" style="display:none;">'.$item_id.'</span>'
				.' <span class="fav_item_title" style="display:none;">'.$item_title.'</span>'
				.'</span>';

		}
		elseif($user->id)
		{
			$alt_text = JText::_( 'FLEXI_FAVOURE' );
			if (!$img_fav_add) {
				$img_fav_add = JHTML::image('components/com_flexicontent/assets/images/'.'heart_add.png', $alt_text, NULL);
			}
			$onclick 	= "javascript:FCFav(".$item_id.", '".$type."', ".$users_counter.")";
			$link 		= "javascript:void(null)";

			$output		.=
				 '<span class="fcfav_add">'
				.' <a id="favlink_'.$type.'_'.$item_id.'" href="'.$link.'" onclick="'.$onclick.'" class="btn fcfav-reponse'.$tooltip_class.'" title="'.$addremove_tip.'">'.$img_fav_add.'</a>'
				.' <span class="fav_item_id" style="display:none;">'.$item_id.'</span>'
				.' <span class="fav_item_title" style="display:none;">'.$item_title.'</span>'
				.'</span>';
		}
		else
		{
			$attribs = 'class="btn '.$tooltip_class.'" title="'.$addremove_tip.'" onclick="alert(\''.JText::_( 'FLEXI_FAVOURE_LOGIN_TIP' ).'\')"';
			$image = JHTML::image('components/com_flexicontent/assets/images/'.'heart_login.png', JText::_( 'FLEXI_FAVOURE' ), $attribs);

			$output		= $image;
		}

		return $output;
	}
	
	
	/**
	 * Method to build a list of radio or checkbox buttons
	 *
	 * @return array
	 * @since 1.5
	 */
	static function buildradiochecklist($options, $name, $selected, $buildtype=0, $attribs = '', $tagid=null, $label_class='')
	{
		$selected = is_array($selected) ? $selected : array($selected);
		$tagid = $tagid ? $tagid : str_replace( '[', '_', preg_replace('#\]|\[\]#', '',($name)) );
		
		$n = 0;
		$html = $buildtype==1 || $buildtype==3 ? '<fieldset class="radio btn-group btn-group-yesno">' : '';
		$label_class .= (!$label_class && ($buildtype==1 || $buildtype==3)) ? ' btn': '';
		foreach ($options as $value => $text) {
			$tagid_n = $tagid.$n;
			$html .='
			<input type="'.($buildtype > 1 ? 'checkbox' : 'radio').'" '.(in_array($value, $selected) ? ' checked="checked" ' : '').' value="'.$value.'" id="'.$tagid_n.'" name="'.$name.'" '.$attribs.'/>
			<label id="'.$tagid_n.'-lbl" for="'.$tagid_n.'" class="'.$label_class.'">'.$text.'</label>
			';
			$n++;
		}
		$html .= $buildtype==1 ? '</fieldset>' : '';
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
			$_list[] = JHTML::_( 'select.option', '', $displaytype );
		else if ($displaytype)
			$_list[] = JHTML::_( 'select.option', '', JText::_( 'FLEXI_SELECT_TYPE' ) );
		
		if ($check_perms)
			$user = JFactory::getUser();
		
		foreach ($types as $type)
		{
			$allowed = true;
			if ($check_perms)
				$allowed = ! $type->itemscreatable || $user->authorise('core.create', 'com_flexicontent.type.' . $type->id);
			
			if ( !$allowed && $type->itemscreatable == 1 ) continue;
			
			if ( !$allowed && $type->itemscreatable == 2 )
				$_list[] = JHTML::_( 'select.option', $type->id, $type->name, 'value', 'text', $disabled = true );
			else
				$_list[] = JHTML::_( 'select.option', $type->id, $type->name);
		}
		
		$tagid = $tagid ? $tagid : str_replace( '[', '_', preg_replace('#\]|\[\]#', '',($name)) );
		return JHTML::_('select.genericlist', $_list, $name, $attribs, 'value', 'text', $selected, $tagid );
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
			$_list[] = JHTML::_( 'select.option', '', $displaytype );
		else if ($displaytype)
			$_list[] = JHTML::_( 'select.option', '', JText::_( 'FLEXI_SELECT_AUTHOR' ) );
		
		$user_id_str = JText::_('FLEXI_ID') .': ';
		foreach ($list as $item) {
			$_list[] = JHTML::_( 'select.option', $item->id, $item->name ? $item->name : $user_id_str . $item->id );
		}
		
		$tagid = $tagid ? $tagid : str_replace( '[', '_', preg_replace('#\]|\[\]#', '',($name)) );
		return JHTML::_('select.genericlist', $_list, $name, $attribs, 'value', 'text', $selected, $tagid );
	}


	/**
	 * Method to build the list of the autors
	 *
	 * @return array
	 * @since 1.5
	 */
	static function buildtagsselect($name, $attribs, $selected, $displaytype=1, $tagid=null)
	{
		$db = JFactory::getDBO();
		$query = 'SELECT id, name'
		. ' FROM #__flexicontent_tags'
		. ' ORDER BY name ASC'
		;
		$db->setQuery($query);
		$data = $db->loadObjectList();
		
		$options = array();
		if (!is_numeric($displaytype) && is_string($displaytype))
			$options[] = JHTML::_( 'select.option', '', $displaytype);
		else if ($displaytype)
			$options[] = JHTML::_( 'select.option', '', JText::_( 'FLEXI_SELECT_TAG' ));
		
		foreach ($data as $val)
			$options[] = JHTML::_( 'select.option', $val->id, $val->name);
		
		$tagid = $tagid ? $tagid : str_replace( '[', '_', preg_replace('#\]|\[\]#', '',($name)) );
		return JHTML::_('select.genericlist', $options, $name, $attribs, 'value', 'text', $selected, $tagid );
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
		
		if (!$displaytype) {   // $displaytype: 0, is ungrouped
			ksort( $list, SORT_STRING );
			return JHTML::_('select.genericlist', $list, $name, $attribs, 'value', 'text', $selected, $tagid );
		}
		
		else { // $displaytype: 1, is grouped
			$field_types = array();
			foreach ($list as $key => $data)
			{
				if ( is_object($data) )
					$field_types[] = $data;
				
				else if ( is_string($data) )
					$field_types[] = JHTML::_('select.optgroup', $data);
				
				else
					$field_types[] = $data;
			}
			
			$xml = new SimpleXMLElement("<element $attribs />");
			$xml = (array)$xml->attributes();
			$attribs = $xml['@attributes'];
			
			$attribs = array(
				'id' => $tagid, // HTML id for select field
				'list.attr' => $attribs, // array(),  // additional HTML attributes for select field
				'list.translate'=>false, // true to translate
				'option.key'=>'value', // key name for value in data array
				'option.text'=>'text', // key name for text in data array
				'option.attr'=>'attr', // key name for attr in data array
				'list.select'=>$selected, // value of the SELECTED field
			);
			
			return JHTML::_('select.genericlist', $field_types, $name, $attribs);
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
		$db = JFactory::getDBO();
		$query = 'SELECT DISTINCT ext'
		. ' FROM #__flexicontent_files'
		. ' ORDER BY ext ASC'
		;
		$db->setQuery($query);
		$data = $db->loadColumn();
		
		if (!is_numeric($displaytype) && is_string($displaytype))
			$options[] = JHTML::_( 'select.option', '', $displaytype);
		else if ($displaytype)
			$options[] = JHTML::_( 'select.option', '', JText::_( 'FLEXI_ALL_EXT' ));
		
		foreach ($data as $val)
			$options[] = JHTML::_( 'select.option', $val, $val);
		
		$tagid = $tagid ? $tagid : str_replace( '[', '_', preg_replace('#\]|\[\]#', '',($name)) );
		return JHTML::_('select.genericlist', $options, $name, $attribs, 'value', 'text', $selected, $tagid );
	}

	/**
	 * Method to build the uploader list
	 *
	 * @return array
	 * @since 1.5
	 */
	static function builduploaderlist($name, $attribs, $selected, $displaytype=1, $tagid=null)
	{
		$db = JFactory::getDBO();
		$query = 'SELECT DISTINCT f.uploaded_by AS uid, u.name AS name'
		. ' FROM #__flexicontent_files AS f'
		. ' LEFT JOIN #__users AS u ON u.id = f.uploaded_by'
		. ' ORDER BY f.ext ASC'
		;
		$db->setQuery($query);
		$data = $db->loadObjectList();
		
		if (!is_numeric($displaytype) && is_string($displaytype))
			$options[] = JHTML::_( 'select.option', '', $displaytype);
		else if ($displaytype)
			$options[] = JHTML::_( 'select.option', '', JText::_( 'FLEXI_ALL_UPLOADERS' ));
		
		foreach ($data as $val)
			$options[] = JHTML::_( 'select.option', $val->uid, $val->name);
		
		$tagid = $tagid ? $tagid : str_replace( '[', '_', preg_replace('#\]|\[\]#', '',($name)) );
		return JHTML::_('select.genericlist', $options, $name, $attribs, 'value', 'text', $selected, $tagid );
	}


	/**
	 * Method to build the Joomfish languages list
	 *
	 * @return object
	 * @since 1.5
	 */
	static function buildlanguageslist($name, $attribs, $selected, $displaytype=1, $allowed_langs=null, $published_only=true, $disable_langs=null, $add_all=true, $conf=false)
	{
		$db = JFactory::getDBO();
		$tagid = null; // ... not provided
		$tagid = $tagid ? $tagid : str_replace( '[', '_', preg_replace('#\]|\[\]#', '',($name)) );
		
		$selected_found = false;
		$all_langs = FLEXIUtilities::getlanguageslist($published_only, $add_all);
		$user_langs = null;
		if ($allowed_langs) {
			$_allowed = array_flip($allowed_langs);
			foreach ($all_langs as $index => $lang)
				if ( isset($_allowed[$lang->code] ) ) {
					$user_langs[] = $lang;
					// Check if selected language was added to the user langs
					$selected_found = ($lang->code == $selected) ? true : $selected_found;
				}
		} else {
			$user_langs = & $all_langs;
			$selected_found = true;
		}
		
		if ($disable_langs) {
			$_disabled = array_flip($disable_langs);
			$_user_langs = array();
			foreach ($user_langs as $index => $lang) {
				if ( !isset($_disabled[$lang->code] ) ) {
					$_user_langs[] = $lang;
					// Check if selected language was added to the user langs
					$selected_found = ($lang->code == $selected) ? true : $selected_found;
				}
			}
			$user_langs = $_user_langs;
		}
		
		if ( !count($user_langs) )  return "user is not allowed to use any language";
		if (!$selected_found) $selected = $user_langs[0]->code;  // Force first language to be selected
		
		if ( $conf && empty($conf['flags']) && empty($conf['texts']) ) {
			$conf['flags'] = $conf['texts'] = 1;
		}
		
		$required = '';
		if ( $conf && !empty($conf['required']) ) {
			$required = ' required validate-radio ';
		}
		
		$langs = array();
		switch ($displaytype)
		{
			// Drop-down SELECT of ALL languages
			case 1: case 2: default:
				if (!is_numeric($displaytype) && is_string($displaytype))
					// WITH custom prompt to select language
					$langs[] = JHTML::_('select.option',  '', $displaytype);
				
				else if ($displaytype==2)
					// WITH empty prompt to select language, e.g. used in items/category manager
					$langs[] = JHTML::_('select.option',  '', JText::_( 'FLEXI_SELECT_LANGUAGE' ));
				
				foreach ($user_langs as $lang) {
					$langs[] = JHTML::_('select.option',  $lang->code, $lang->name );
				}
				$list = JHTML::_('select.genericlist', $langs, $name, $attribs, 'value', 'text', $selected, $tagid);
				break;
			
			// RADIO selection of ALL languages , e.g. item form,
			case 3:   // flag icons only
				$checked	= '';
				$list		= '';

				foreach ($user_langs as $lang) {
					if ($lang->code == $selected) {
						$checked = ' checked="checked"';
					}
					$list 	.= '<input id="'.$tagid.'_'.$lang->id.'" type="radio" name="'.$name.'" value="'.$lang->code.'"'.$checked.' class="'.$required.'" data-element-grpid="'.$tagid.'" />';
					$list 	.= '<label class="lang_box" for="'.$tagid.'_'.$lang->id.'" title="'.$lang->name.'" >';
					if($lang->shortcode=="*") {
						$list .= JText::_('FLEXI_ALL');  // Can appear in J1.6+ only
					} else {
						// Add Flag if configure and it exists
						if (!$conf || $conf['flags']) {
							$list .= !empty($lang->imgsrc)  ?  '<img src="'.$lang->imgsrc.'" alt="'.$lang->name.'" />'  :  $lang->code;
						}
						
						// Add text if configured
						if ( !$conf || $conf['texts']==1 ) {
							$list .= $lang->code;
						} else if ( $conf['texts']==2 ) {
							$list .= $lang->title;
						} else if ( $conf['texts']==3 ) {
							$list .= $lang->title_native;
						} else if ( $conf['texts']==4 ) {
							$list .= $lang->name;
						} else {
							$list .= '';
						}
					}
					
					$list 	.= '</label>';
					$checked	= '';
				}
				break;
			case 4:   // RADIO selection of ALL languages, with empty default option "Keep original language", e.g. when copying/moving items
				$list  = '<input id="lang9999" type="radio" name="'.$name.'" class="'.$required.'" value="" checked="checked" data-element-grpid="'.$tagid.'" />';
				$list .= '<label class="lang_box" for="lang9999" title="'.JText::_( 'FLEXI_NOCHANGE_LANGUAGE_DESC' ).'" >';
				$list .= JText::_( 'FLEXI_NOCHANGE_LANGUAGE' );
				$list .= '</label><div class="fcclear"></div>';

				foreach ($user_langs as $lang) {
					$list 	.= '<input id="'.$tagid.'_'.$lang->id.'" type="radio" name="'.$name.'" class="'.$required.'" value="'.$lang->code.'" data-element-grpid="'.$tagid.'" />';
					$list 	.= '<label class="lang_box" for="'.$tagid.'_'.$lang->id.'" title="'.$lang->name.'">';
					if($lang->shortcode=="*") {
						$list 	.= JText::_('FLEXI_ALL');  // Can appear in J1.6+ only
					} else if (@$lang->imgsrc) {
						$list 	.= '<img src="'.$lang->imgsrc.'" alt="'.$lang->name.'" />';
					} else {
						$list 	.= $lang->name;
					}
					$list 	.= '&nbsp;</label>';
				}
				break;
			case 5:   // RADIO selection of ALL languages, EXCLUDE selected language, e.g. when translating items into another language
			case 7:   // also exclude '*' (ALL) language
				$list		= '';
				foreach ($user_langs as $lang) {
					if ($lang->code==$selected) continue;
					if ($displaytype==7 && $lang->shortcode=="*") continue;
					$list 	.= '<input id="'.$tagid.'_'.$lang->id.'" type="radio" name="'.$name.'" class="'.$required.'" value="'.$lang->code.'" data-element-grpid="'.$tagid.'" />';
					$list 	.= '<label class="lang_box" for="'.$tagid.'_'.$lang->id.'" title="'.$lang->name.'">';
					if($lang->shortcode=="*") {
						$list 	.= JText::_('FLEXI_ALL');  // Can appear in J1.6+ only
					} else if (@$lang->imgsrc) {
						$list 	.= '<img src="'.$lang->imgsrc.'" alt="'.$lang->name.'" />';
					} else {
						$list 	.= $lang->name;
					}
					$list 	.= '</label>';
				}
				break;
			case 6:   // RADIO selection of ALL languages, with empty option "Use language column", e.g. used in CSV import view
				$list		= '';
				
				$checked = $selected==='' ? 'checked="checked"' : '';
				$list 	.= '<input id="lang9999" type="radio" name="'.$name.'" class="'.$required.'" value="" '.$checked.' data-element-grpid="'.$tagid.'" />';
				$tooltip_class = ' hasTooltip';
				$tooltip_title = flexicontent_html::getToolTip('FLEXI_USE_LANGUAGE_COLUMN', 'FLEXI_USE_LANGUAGE_COLUMN_TIP', 1, 1);
				$list 	.= '<label class="lang_box'.$tooltip_class.'" for="lang9999" title="'.$tooltip_title.'">';
				$list 	.= JText::_( 'FLEXI_USE_LANGUAGE_COLUMN' );
				$list 	.= '</label>';
				
				foreach ($user_langs as $lang) {
					$checked = $lang->code==$selected ? 'checked="checked"' : '';
					$list 	.= '<input id="'.$tagid.'_'.$lang->id.'" type="radio" name="'.$name.'" class="'.$required.'" value="'.$lang->code.'" '.$checked.' data-element-grpid="'.$tagid.'" />';
					$list 	.= '<label class="lang_box" for="'.$tagid.'_'.$lang->id.'" title="'.$lang->name.'">';
					if($lang->shortcode=="*") {
						$list 	.= JText::_('FLEXI_ALL');  // Can appear in J1.6+ only
					/*} else if (@$lang->imgsrc) {
						$list 	.= '<img src="'.$lang->imgsrc.'" alt="'.$lang->name.'" />';*/
					} else {
						$list 	.= $lang->name;
					}
					$list 	.= '&nbsp;</label>';
				}
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
		if ( !$state_names ) {
			$state_names = array(1=>JText::_('FLEXI_PUBLISHED'), -5=>JText::_('FLEXI_IN_PROGRESS'), 0=>JText::_('FLEXI_UNPUBLISHED'), -3=>JText::_('FLEXI_PENDING'), -4=>JText::_('FLEXI_TO_WRITE'), 2=>JText::_('FLEXI_ARCHIVED'), -2=>JText::_('FLEXI_TRASHED'), ''=>JText::_('FLEXI_UNKNOWN'));
			$state_descrs = array(1=>JText::_('FLEXI_PUBLISH_THIS_ITEM'), -5=>JText::_('FLEXI_SET_STATE_AS_IN_PROGRESS'), 0=>JText::_('FLEXI_UNPUBLISH_THIS_ITEM'), -3=>JText::_('FLEXI_SET_STATE_AS_PENDING'), -4=>JText::_('FLEXI_SET_STATE_AS_TO_WRITE'), 2=>JText::_('FLEXI_ARCHIVE_THIS_ITEM'), -2=>JText::_('FLEXI_TRASH_THIS_ITEM'), ''=>'FLEXI_UNKNOWN');
			$state_imgs = array(1=>'tick.png', -5=>'publish_g.png', 0=>'publish_x.png', -3=>'publish_r.png', -4=>'publish_y.png', 2=>'archive.png', -2=>'trash.png', ''=>'unknown.png');
		}
		
		$state[] = JHTML::_('select.option',  '', JText::_( !is_numeric($displaytype) && is_string($displaytype) ? $displaytype : 'FLEXI_DO_NOT_CHANGE' ) );
		$state[] = JHTML::_('select.option',  -4, $state_names[-4] );
		$state[] = JHTML::_('select.option',  -3, $state_names[-3] );
		$state[] = JHTML::_('select.option',  -5, $state_names[-5] );
		$state[] = JHTML::_('select.option',   1, $state_names[1] );
		$state[] = JHTML::_('select.option',   0, $state_names[0] );
		$state[] = JHTML::_('select.option',   2, $state_names[2] );
		$state[] = JHTML::_('select.option',  -2, $state_names[-2] );
		
		$tagid = $tagid ? $tagid : str_replace( '[', '_', preg_replace('#\]|\[\]#', '',($name)) );
		
		if ( $displaytype==1 || (!is_numeric($displaytype) && is_string($displaytype)) )
			$list = JHTML::_('select.genericlist', $state, $name, $attribs, 'value', 'text', $selected, $tagid);
		
		else if ($displaytype==2)
		{
			$state_ids   = array(1, -5, 0, -3, -4); // published: 1, -5   unpublished: 0, -3, -4
			$state_ids[] = 2;  // archived
			$state_ids[] = -2;  // trashed
			$state_colors= array(1=>'darkgreen', -5=>'darkgreen', 0=>'darkred', -3=>'darkred', -4=>'darkred', 2=>'darkblue', -2=>'gray');

			$img_path = JURI::root(true)."/components/com_flexicontent/assets/images/";

			$list = '';
			
			$checked = $selected==='' ? ' checked="checked"' : '';
			$list 	.= '<input id="state9999" type="radio" name="state" class="state" value="" '.$checked.'/>';
			$tooltip_class = ' hasTooltip';
			$tooltip_title = flexicontent_html::getToolTip('FLEXI_USE_STATE_COLUMN', 'FLEXI_USE_STATE_COLUMN_TIP', 1, 1);
			$list 	.= '<label class="state_box'.$tooltip_class.'" for="state9999" title="'.$tooltip_title.'">';
			$list 	.= JText::_( 'FLEXI_USE_STATE_COLUMN' );
			$list 	.= '</label>';
			
			foreach ($state_ids as $i => $state_id) {
				//if ($state_id==0 || $state_id==2) $list .= "<br/>";
				$checked = $state_id==$selected ? ' checked="checked"' : '';
				$list 	.= '<input id="state'.$state_id.'" type="radio" name="state" class="state" value="'.$state_id.'" '.$checked.'/>';
				$list 	.= '<label class="state_box" for="state'.$state_id.'" title="'.$state_names[$state_id].'" style="color:'.$state_colors[$state_id].';">';
				$list 	.= $state_names[$state_id];
				//$list 	.= '<img src="'.$img_path.$state_imgs[$state_id].'" width="16" height="16" style="border-width:0;" alt="'.$state_names[$state_id].'" />';
				$list 	.= '</label>';
			}
		}
		
		else
			$list = 'Bad type in buildstateslist()';
		
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

	static function flexiHtmlDiff($old, $new, $mode=0)
	{
		$t1 = explode(" ",$old);
		$t2 = explode(" ",$new);
		$out = flexicontent_html::PHPDiff( $t1, $t2 );
		$html1 = array();
		$html2 = array();
		foreach($t1 as $k=>$o) {
			if(in_array($k, $out[0])) $html1[] = "<s>".($mode?htmlspecialchars($o, ENT_QUOTES):$o)."</s>";
			else $html1[] = ($mode?htmlspecialchars($o, ENT_QUOTES)."<br/>":$o);
		}
		foreach($t2 as $k=>$n) {
			if(in_array($k, $out[1])) $html2[] = "<u>".($mode?htmlspecialchars($n, ENT_QUOTES):$n)."</u>";
			else $html2[] = ($mode?htmlspecialchars($n, ENT_QUOTES)."<br/>":$n);
		}
		$html1 = implode(" ", $html1);
		$html2 = implode(" ", $html2);
		return array($html1, $html2);
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
			JArrayHelper::toInteger($type_ids, null);
			$type_ids_list = implode(',', $type_ids);
		}
		
		$where = array();
		if ($published)
			$where[] = 'published = 1';
		if ($type_ids_list)
			$where[] = 'id IN ('. $type_ids_list .' ) ';
		
		$db = JFactory::getDBO();
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
	static function userlevel($name, $selected, $attribs = '', $params = true, $id = false, $createlist = true) {
		static $options;
		if(!$options) {
			$db		= JFactory::getDbo();
			$query	= $db->getQuery(true);
			$query->select('a.id AS value, a.title AS text');
			$query->from('#__viewlevels AS a');
			if (!$createlist) {
				$query->where('a.id="'.$selected.'"');
			}
			$query->group('a.id');
			$query->order('a.ordering ASC');
			$query->order('`title` ASC');

			// Get the options.
			$db->setQuery($query);
			$options = $db->loadObjectList();

			// Check for a database error.
			if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
			if ( !$options ) return null;

			if (!$createlist) {
				return $options[0]->text;  // return ACCESS LEVEL NAME
			}

			// If params is an array, push these options to the array
			if (is_array($params)) {
				$options = array_merge($params,$options);
			}
			// If all levels is allowed, push it into the array.
			elseif ($params) {
				//array_unshift($options, JHtml::_('select.option', '', JText::_('JOPTION_ACCESS_SHOW_ALL_LEVELS')));
			}
		}

		return JHtml::_('select.genericlist', $options, $name,
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

	static function addToolBarButton($text='Button Text', $btn_name='btnname', $full_js='', $err_msg='', $confirm_msg='', $task='btntask', $extra_js='', $list=true, $menu=true, $confirm=true, $btn_class="", $btn_icon="", $attrs='')
	{
		$toolbar = JToolBar::getInstance('toolbar');
		$text  = JText::_( $text );
		$class = $btn_icon ? $btn_icon : 'icon-32-'.$btn_name;

		if ( !$full_js )
		{
			$err_msg = $err_msg ? $err_msg : JText::sprintf( 'FLEXI_SELECT_LIST_ITEMS_TO', $btn_name );
			$err_msg = addslashes($err_msg);
			$confirm_msg = $confirm_msg ? $confirm_msg : JText::_('FLEXI_ARE_YOU_SURE');
			$confirm_msg = addslashes($confirm_msg);

			$full_js = $extra_js ."; submitbutton('$task');";
			if ($confirm) {
				$full_js = "if (confirm('".$confirm_msg."')) { ".$full_js." }";
			}
			if (!$menu) {
				$full_js = "hideMainMenu(); " . $full_js;
			}
			if ($list) {
				$full_js = "if (document.adminForm.boxchecked.value==0) { alert('".$err_msg."') ;} else { ".$full_js." }";
			}
		}
		$full_js = "javascript: $full_js";

		$button_html	= "<a href=\"#\" onclick=\"$full_js\" class=\"toolbar btn btn-small $btn_class\" ".$attrs.">\n";
		$button_html .= "<span class=\"$class\" title=\"$text\">\n";
		$button_html .= "</span>\n";
		$button_html	.= "$text\n";
		$button_html	.= "</a>\n";

		$toolbar->appendButton('Custom', $button_html, $btn_name);
	}
	
	
	// ************************************************************************
	// Calculate CSS classes needed to add special styling markups to the items
	// ************************************************************************
	static function	calculateItemMarkups($items, $params)
	{
		global $globalcats;
		global $globalnoroute;
		$globalnoroute = !is_array($globalnoroute) ? array() : $globalnoroute;
		
		$db   = JFactory::getDBO();
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
		$layout_vars = flexicontent_html::getCatViewLayoutVars($catmodel);
		$cid  = $layout_vars['cid'];
		
		$urlvars = array();
		if ($layout_vars['layout'])   $urlvars['layout']   = $layout_vars['layout'];
		if ($layout_vars['authorid']) $urlvars['authorid'] = $layout_vars['authorid'];
		if ($layout_vars['tagid'])    $urlvars['tagid']    = $layout_vars['tagid'];
		if ($layout_vars['cids'])     $urlvars['cids']     = $layout_vars['cids'];
		
		// Category link for single/multiple category(-ies)  --OR--  "current layout" link for myitems/author/favs/tags layouts
		$non_sef_link = FlexicontentHelperRoute::getCategoryRoute($slug, $Itemid, $urlvars);
		$category_link = JRoute::_($non_sef_link);
		
		return $category_link;
	}
	
	
	static function getCatViewLayoutVars($obj=null)
	{
		static $layout_vars;
		if ($layout_vars) return $layout_vars;
		
		// Get URL variables
		$layout_vars = array();
		$layout_vars['cid'] = $obj && isset($obj->_id) ? $obj->_id : JRequest::getInt('cid', 0);
		$layout_vars['authorid'] = $obj && isset($obj->_authorid) ? $obj->_authorid : JRequest::getInt('authorid', 0);
		$layout_vars['tagid']    = $obj && isset($obj->_tagid) ? $obj->_tagid : JRequest::getInt('tagid', 0);
		$layout_vars['layout']   = $obj && isset($obj->_layout) ? $obj->_layout : JRequest::getCmd('layout', '');
		
		if ($obj && isset($obj->_ids)) {
			$layout_vars['cids'] = !is_array($obj->_ids) ? $obj->_ids : implode(',' , $obj->_ids);
		} else {
			$mcats_list = JRequest::getVar('cids', '');
			if ( !is_array($mcats_list) ) {
				$mcats_list = preg_replace( '/[^0-9,]/i', '', (string) $mcats_list );
				$mcats_list = explode(',', $mcats_list);
			}
			// make sure given data are integers ... and skipping zero values
			$cids = array();
			foreach ($mcats_list as $i => $_id)  if ((int)$_id) $cids[] = (int)$_id;
			$layout_vars['cids'] = implode(',' , $cids);
		}
		
		return $layout_vars;
	}
}

class flexicontent_upload
{
	static function makeSafe($file) {//The range \xE01-\xE5B is thai language.
		$file = str_replace(" ", "", $file);
		$regex = array('#(\.){2,}#', '#[^A-Za-z0-9\xE01-\xE5B\.\_\- ]#', '#^\.#');
		//$regex = array('#(\.){2,}#', '#[^A-Za-z0-9\.\_\- ]#', '#^\.#');
		return preg_replace($regex, '', $file);
	}
	
	
	static function parseByteLimit($limit)
	{
		if (is_numeric($limit)) return $limit;  // already in bytes
	
		$v = (int)$limit;
		$type = substr($limit, -1);
		
		switch (strtoupper($type)) {
			case 'P': $v *= 1024;
			case 'T':	$v *= 1024;
			case 'G':	$v *= 1024;
			case 'M': $v *= 1024;
			case 'K': $v *= 1024;
			break;
		}
		return $v;
	}
	
	
	/**
	 * Gets upload Limits
	 *
	 * @return array with limits
	 * @since 3.0
	 */
	static function getPHPuploadLimit()
	{
		$post_max   = flexicontent_upload::parseByteLimit(ini_get('post_max_size'));
		$upload_max = flexicontent_upload::parseByteLimit(ini_get('upload_max_filesize'));
		if ($upload_max < $post_max) {
			$limit = array('value'=>$upload_max, 'name'=>'upload_max_filesize');
		}
		else {
			$limit = array('value'=>$post_max, 'name'=>'post_max_size');
		}
		// Sucosin limitation
		if (extension_loaded('suhosin')) {
			$post_max = flexicontent_upload::parseByteLimit(ini_get('suhosin.post.max_value_length'));
			if ($post_max < $limit['value']) $limit = array('value'=>$post_max, 'name'=>'suhosin.post.max_value_length');
		}
		return $limit;
	}
	
	
	/**
	 * Gets the extension of a file name
	 *
	 * @param string $file The file name
	 * @return string The file extension
	 * @since 1.5
	 */
	static function getExt($filename)
	{
		return pathinfo($filename, PATHINFO_EXTENSION);
	}


	/**
	 * Checks uploaded file
	 *
	 * @param string $file The file name
	 * @param string $err  Set (return) the error string in it
	 * @param string $file view 's parameters
	 * @return string The file extension
	 * @since 1.5
	 */
	static function check(&$file, &$err, &$params)
	{
		if (!$params) {
			$params = JComponentHelper::getParams( 'com_flexicontent' );
		}

		if(empty($file['name'])) {
			$err = 'FLEXI_PLEASE_INPUT_A_FILE';
			return false;
		}

		jimport('joomla.filesystem.file');
		$file['altname'] = $file['name'];
		if ($file['name'] !== JFile::makesafe($file['name'])) {
			//$err = JText::_('FLEXI_WARNFILENAME').','.$file['name'].'|'.JFile::makesafe($file['name'])."<br/>";
			//return false;
			$file['name'] = date('Y-m-d-H-i-s').".".flexicontent_upload::getExt($file['name']);
		}
		
		
		// ***************************************
		// Check if the image file type is allowed
		// ***************************************
		
		$format = strtolower(flexicontent_upload::getExt($file['name']));
		
		$allowed_exts = $params->get('upload_extensions', 'bmp,csv,doc,docx,gif,ico,jpg,jpeg,odg,odp,ods,odt,pdf,png,ppt,pptx,swf,txt,xcf,xls,xlsx,zip,ics');
		$allowed_exts = preg_split("/[\s]*,[\s]*/", $allowed_exts);

		foreach($allowed_exts as $a => $allowed_ext) $allowed_exts[$a] = strtolower($allowed_ext);
		
		$ignored = explode(',', $params->get( 'ignore_extensions' ));
		foreach($ignored as $a => $ignored_ext) $ignored[$a] = strtolower($ignored_ext);
		if (!in_array($format, $allowed_exts) && !in_array($format,$ignored))
		{
			$err = 'FLEXI_WARNFILETYPE';
			return false;
		}
		
		
		// **************
		// Check filesize
		// **************
		
		$maxSize = (int) $params->get( 'upload_maxsize', 0 );
		if ($maxSize > 0 && (int) $file['size'] > $maxSize)
		{
			$err = 'FLEXI_WARNFILETOOLARGE';
			return false;
		}
		
		
		$imginfo = null;
		$images = explode( ',', $params->get( 'image_extensions' ));
		
		if ($params->get('restrict_uploads', 1) )
		{
			if (in_array($format, $images))  // if its an image run it through getimagesize
			{
				if (($imginfo = getimagesize($file['tmp_name'])) === FALSE)
				{
					$err = 'FLEXI_WARNINVALIDIMG';
					return false;
				}

			}
			
			else if (!in_array($format, $ignored))
			{
				// if its not an image...and we're not ignoring it
				$allowed_mime = explode(',', $params->get('upload_mime'));
				$illegal_mime = explode(',', $params->get('upload_mime_illegal'));

				if (function_exists('finfo_open') /*&& $params->get('check_mime',1)*/)
				{
					// We have fileinfo
					$finfo = finfo_open(FILEINFO_MIME);
					$type = finfo_file($finfo, $file['tmp_name']);
					if(strlen($type) && !in_array($type, $allowed_mime) && in_array($type, $illegal_mime)) {
						$err = 'FLEXI_WARNINVALIDMIME';
						return false;
					}
					finfo_close($finfo);

				}
				else if(function_exists('mime_content_type') /*&& $params->get('check_mime',1)*/)
				{
					// we have mime magic
					$type = mime_content_type($file['tmp_name']);

					if(strlen($type) && !in_array($type, $allowed_mime) && in_array($type, $illegal_mime)) {
						$err = 'FLEXI_WARNINVALIDMIME';
						return false;
					}

				}
			}
		}
		
		
		// ***************************
		// Check fof XSS safe contents
		// ***************************
		
		$xss_check = JFile::read($file['tmp_name'], false, 256);
		$html_tags = array('abbr','acronym','address','applet','area','audioscope','base','basefont',
			'bdo','bgsound','big','blackface','blink','blockquote','body','bq','br','button','caption',
			'center','cite','code','col','colgroup','comment','custom','dd','del','dfn','dir','div','dl','dt',
			'em','embed','fieldset','fn','font','form','frame','frameset','h1','h2','h3','h4','h5','h6','head',
			'hr','html','iframe','ilayer','img','input','ins','isindex','keygen','kbd','label','layer','legend',
			'li','limittext','link','listing','map','marquee','menu','meta','multicol','nobr','noembed','noframes',
			'noscript','nosmartquotes','object','ol','optgroup','option','param','plaintext','pre','rt','ruby','s','samp',
			'script','select','server','shadow','sidebar','small','spacer','span','strike','strong','style','sub','sup','table',
			'tbody','td','textarea','tfoot','th','thead','title','tr','tt','ul','var','wbr','xml','xmp','!DOCTYPE', '!--');
		foreach($html_tags as $tag)
		{
			// A tag is '<tagname ', so we need to add < and a space or '<tagname>'
			if(stristr($xss_check, '<'.$tag.' ') || stristr($xss_check, '<'.$tag.'>'))
			{
				$err = 'FLEXI_WARNIEXSS';
				return false;
			}
		}
		
		return true;
	}
	
	
	/**
	* Sanitize the image file name and return an unique string
	*
	* @since 1.0
	*
	* @param string $base_Dir the target directory
	* @param string $filename the unsanitized imagefile name
	*
	* @return string $filename the sanitized and unique file name
	*/
	static function sanitize($base_Dir, $filename)
	{
		jimport('joomla.filesystem.file');

		//check for any leading/trailing dots and remove them (trailing shouldn't be possible cause of the getEXT check)
		$filename = preg_replace( "/^[.]*/", '', $filename );
		$filename = preg_replace( "/[.]*$/", '', $filename ); //shouldn't be necessary, see above

		//we need to save the last dot position cause preg_replace will also replace dots
		$lastdotpos = strrpos( $filename, '.' );

		//replace invalid characters
		$chars = '[^0-9a-zA-Z()_-]';
		$filename 	= strtolower( preg_replace( "/$chars/", '-', $filename ) );

		//get the parts before and after the dot (assuming we have an extension...check was done before)
		$beforedot	= substr( $filename, 0, $lastdotpos );
		$afterdot 	= substr( $filename, $lastdotpos + 1 );

		//make a unique filename for the image and check it is not already taken
		//if it is already taken keep trying till success
		if (JFile::exists( $base_Dir . $beforedot . '.' . $afterdot ))
		{
			$version = 1;
			while( JFile::exists( $base_Dir . $beforedot . '-' . $version . '.' . $afterdot ) )
			{
				$version++;
			}
			//create out of the seperated parts the new filename
			$filename = $beforedot . '-' . $version . '.' . $afterdot;
		} else {
			$filename = $beforedot . '.' . $afterdot;
		}

		return $filename;
	}

	/**
	* Sanitize folders and return an unique string
	*
	* @since 1.5
	*
	* @param string $base_Dir the target directory
	* @param string $foler the unsanitized folder name
	*
	* @return string $foldername the sanitized and unique file name
	*/
	static function sanitizedir($base_Dir, $folder)
	{
		jimport('joomla.filesystem.folder');

		//replace invalid characters
		$chars = '[^0-9a-zA-Z()_-]';
		$folder 	= strtolower( preg_replace( "/$chars/", '-', $folder ) );

		//make a unique folder name for the image and check it is not already taken
		if (JFolder::exists( $base_Dir . $folder ))
		{
			$version = 1;
			while( JFolder::exists( $base_Dir . $folder . '-' . $version )) {
				$version++;
			}
			//create out of the seperated parts the new folder name
			$foldername = $folder . '-' . $version;
		} else {
			$foldername = $folder;
		}

		return $foldername;
	}
}



class flexicontent_tmpl
{
	/**
	 * Parse any FLEXIcontent templates files that have been modified
	 *
	 * @return 	object	object of templates
	 * @since 1.5
	 */
	static function parseTemplates_checked($tmpldir='', $unchanged_tmpls = null)
	{
		// Return cached data
		static $tmpls = null;
		
		// Set 'unchanged' layouts and return (this avoid reparsing on subsequent call)
		if ( !empty($unchanged_tmpls) )
		{
			$tmpls = $unchanged_tmpls;
			return true;
		}
		
		if ( $tmpls === null )
		{
			$tmpls = new stdClass();
			$tmpls->items = new stdClass();
			$tmpls->category = new stdClass();
		}
		
		$folders = flexicontent_tmpl::getThemes($tmpldir);
		
		foreach ($folders as $tmplname)
		{
			if ( isset($tmpls->items->$tmplname) && isset($tmpls->category->$tmplname) ) continue;  // Avoid reparsing an 'unchanged' theme
			flexicontent_tmpl::parseTemplate($tmpldir, $tmplname, $tmpls);   // Parse XML files of the template
		}
		
		return $tmpls;
	}
	
	
	/**
	 * Parses a specific FLEXIcontent template
	 * - both layout files: item.xml and category.xml are parsed
	 * - the parsed data are set into the given 'tmpls' parameter
	 * NOTE: parameter $tmpls->items and $tmpls->category needs to have been initialized
	 *
	 * @return 	nothing
	 * @since 3.0.10
	 */
	static function parseTemplate($tmpldir, $tmplname, &$themes)
	{
		static $initialized;
		if ($initialized===null)
		{
			jimport('joomla.filesystem.path' );
			jimport('joomla.filesystem.folder');
			jimport('joomla.filesystem.file');
			jimport('joomla.form.form');
			$initialized = 1;
		}
		
		$tmpldir = $tmpldir ? $tmpldir : JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'templates';
		$layout_types = array('items'=>'item', 'category'=>'category');
		
		foreach ($layout_types as $layout_type => $view)
		{
			// Parse & Load the XML file of the current layout
			$tmplxml = JPath::clean($tmpldir.DS.$tmplname.DS.$view.'.xml');
			if ( JFile::exists($tmplxml) && empty($themes->$layout_type->$tmplname) )
			{
				// Parse the XML file
				$doc = @simplexml_load_file($tmplxml);
				if (!$doc)
				{
					if (JFactory::getApplication()->isAdmin()) JFactory::getApplication()->enqueueMessage('Syntax error(s) in template XML file: '. $tmplxml, 'notice');
					continue;
				}
				
				// Create new class and alias for it
				$themes->$layout_type->$tmplname = new stdClass();
				$t = & $themes->$layout_type->$tmplname;
				
				$t->name     = $tmplname;
				$t->xmlpath  = $tmplxml;
				$t->xmlmtime = filemtime($tmplxml);
				$t->view     = $view;
				$t->tmplvar  = '.'.$layout_type.'.'.$tmplname;
				$t->thumb    = 'components/com_flexicontent/templates/'.$tmplname.'/'.$view.'.png';
				
				// *** This can be serialized and thus Joomla Cache will work
				$t->params = $doc->asXML();
				
				// *** This was moved into the template files of the forms, because JForm contains 'JXMLElement',
				// which extends the PHP built-in Class 'SimpleXMLElement', (built-in Classes cannot be serialized
				// but serialization is used by Joomla 's cache, causing problem with caching the output of this function
				
				//$t->params		= new JForm('com_flexicontent.template.'.$view, array('control' => 'jform', 'load_data' => true));
				//$t->params->loadFile($tmplxml);
				
				// Get Meta Information
				$t->author    = (string) @$doc->author;
				$t->website   = (string) @$doc->website;
				$t->email     = (string) @$doc->email;
				$t->license   = (string) @$doc->license;
				$t->version   = (string) @$doc->version;
				$t->release   = (string) @$doc->release;
				$t->microdata_support = (string) @$doc->microdata_support;
				
				// Get Display Information
				$t->defaulttitle = (string) @$doc->defaulttitle;
				$t->description  = (string) @$doc->description;
				
				// Get field positions
				$groups = & $doc->fieldgroups;
				$pos    = & $groups->group;
				if ($pos) {
					for ($n=0; $n<count($pos); $n++) {
						$t->attributes[$n] = array();
						foreach ($pos[$n]->attributes() as $_attr_name => $_attr_val) {
							$t->attributes[$n][(string)$_attr_name] = (string)$_attr_val;
						}
						$t->positions[$n] = (string)$pos[$n];
					}
				}
				
				$tmpl_path = 'components/com_flexicontent/templates/'.$tmplname.'/';
				
				// CSS files
				$cssfiles = & $doc->{'css'.$view}->file;
				if ($cssfiles) {
					$t->css = new stdClass();
					$t->less_files = array();
					for ($n=0; $n<count($cssfiles); $n++) {
						$t->css->$n = $tmpl_path. (string)$cssfiles[$n];
						$less_file = JPath::clean( preg_replace('/^css|css$/', 'less', (string)$cssfiles[$n]) );
						$t->less_files[] = $less_file;
					}
				}
				
				// JS files
				$js     = & $doc->{'js'.$view};
				$jsfile = & $js->file;
				if ($jsfile) {
					$t->js = new stdClass();
					for ($n=0; $n<count($jsfile); $n++) {
						$t->js->$n = $tmpl_path. (string)$jsfile[$n];
					}
				}
			}
		}
	}	
	
	/**
	 * Parse all FLEXIcontent templates files
	 *
	 * @return 	object	object of templates
	 * @since 1.5
	 */
	static function parseTemplates($tmpldir='', $force=false, $checked_layouts=array())
	{
		static $print_logging_info = null;
		$print_logging_info = $print_logging_info !== null  ?  $print_logging_info  :  JComponentHelper::getParams('com_flexicontent')->get('print_logging_info');
		
		$debug = JDEBUG || $print_logging_info;
		$apply_cache = 1;//FLEXI_CACHE;
		
		if ( $apply_cache )
		{
			// Get template XML data from cache
			$tmplcache = JFactory::getCache('com_flexicontent_tmpl');  // Get Joomla Cache of '...tmpl' Caching Group
			$tmplcache->setCaching(1); 		              // Force cache ON
			$tmplcache->setLifeTime(FLEXI_CACHE_TIME); 	// Set expire time (default is 1 hour)
			$tmpls = $tmplcache->call(array('flexicontent_tmpl', 'parseTemplates_checked'), $tmpldir);
			
			$folder_names = array_flip( flexicontent_tmpl::getThemes($tmpldir) );
			
			$tmpl_names = array();
			foreach($tmpls->category as $tmpl) $tmpl_names[$tmpl->name] = 1;
			foreach($tmpls->items as $tmpl) $tmpl_names[$tmpl->name] = 1;
			
			$new_layouts = array();
			foreach($folder_names as $folder_name => $i) if ( !isset($tmpl_names[$folder_name]) )  $new_layouts[] = $folder_name;
			//print_r($new_layouts);
			
			$deleted_layouts = array();
			foreach($tmpl_names as $tmpl_name => $i) if ( !isset($folder_names[$tmpl_name]) ) {
				unset( $tmpls->items->{$tmpl_name} );
				unset( $tmpls->category->{$tmpl_name} );
				$deleted_layouts[] = $tmpl_name;
			}
			//print_r($deleted_layouts);
			
			// Check for modified XML files, cleaning and updating cache only for modified templates
			$modified = array();
			if ( !empty($checked_layouts) || $force )
			{
				$modified = flexicontent_tmpl::checkXmlModified($tmpls, $checked_layouts);
				$modified_file_list = '';
				// Unset modified templates
				if ( !empty($modified) ) foreach($tmpls as $layout_type => $_tmpls) foreach($_tmpls as $tmpl) {
					if ( !isset($modified[$tmpl->name][$layout_type]) )  continue;
					unset( $tmpls->$layout_type->{$tmpl->name} );
					$modified_file_list .= '<br/>'.$modified[$tmpl->name][$layout_type];
				}
			}
			
			if ( !empty($modified) || !empty($new_layouts) || !empty($deleted_layouts) )
			{
				flexicontent_tmpl::parseTemplates_checked($tmpldir, $tmpls);   // This call only set unchanged templates so that they are not reparsed
				
				if ($debug && !empty($modified) )        JFactory::getApplication()->enqueueMessage("Re-parsing XMLs, XML file modified: ".$modified_file_list, 'message');
				if ($debug && !empty($new_layouts) )     JFactory::getApplication()->enqueueMessage("Parsing new templates: ".implode(', ', $new_layouts), 'message');
				if ($debug && !empty($deleted_layouts) ) JFactory::getApplication()->enqueueMessage("Cleaned cache from deleted templates: ".implode(', ', $deleted_layouts), 'message');
				
				// Clean and update caching re-parsing only new or changed XML files
				$tmplcache->clean();
				$tmplcache->gc();
				$tmpls = $tmplcache->call(array('flexicontent_tmpl', 'parseTemplates_checked'), $tmpldir);
			}
		}
		else {
			$tmpls = flexicontent_tmpl::parseTemplates_checked();
		}
		
		return $tmpls;
	}
	
	
	static function checkCompileLess($tmpls, $force, $checked_layouts=array())
	{
		jimport('joomla.filesystem.path' );
		jimport('joomla.filesystem.file');
		
		$templates_path = JPath::clean(JPATH_SITE.DS.'components/com_flexicontent/templates/');
		
		foreach($checked_layouts as $tmplname)
		{
			$tmpl = @ $tmpls->items->$tmplname;
			if ( $tmpl && !empty($tmpl->less_files) ) {
				$tmpl_path = $templates_path.$tmpl->name.DS;
				flexicontent_html::checkedLessCompile($tmpl->less_files, $tmpl_path, $tmpl_path.'less/include/', $force);
			}
			$tmpl = @ $tmpls->category->$tmplname;
			if ( $tmpl && !empty($tmpl->less_files) ) {
				$tmpl_path = $templates_path.$tmpl->name.DS;
				flexicontent_html::checkedLessCompile($tmpl->less_files, $tmpl_path, $tmpl_path.'less/include/', $force);
			}
		}
	}
	
	
	static function checkXmlModified($tmpls, $checked_layouts=array())
	{
		jimport('joomla.filesystem.file');
		
		$checked_tmpls = array();
		
		// Check specific templates
		if ( !empty($checked_layouts) )
		{
			foreach ($checked_layouts as $layout_name)
			{
				$layout_types = array('items', 'category');
				foreach($layout_types as $layout_type)
				{
					$tmpl = @ $tmpls->$layout_type->$layout_name;
					if ($tmpl) $checked_tmpls[$layout_type][] = $tmpl;
				}
			}
		}
		
		// Check all given templates
		else {
			foreach($tmpls as $layout_type => $_tmpls) {
				foreach($_tmpls as $tmpl) {
					$checked_tmpls[$layout_type][] = $tmpl;
				}
			}
		}
		
		$modified_files = array();
		foreach($checked_tmpls as $layout_type => $_tmpls)
		{
			foreach($_tmpls as $tmpl)
			{
				if (!JFile::exists($tmpl->xmlpath) || filemtime($tmpl->xmlpath) > $tmpl->xmlmtime)
				{
					$modified_files[$tmpl->name][$layout_type] = $tmpl->xmlpath;
				}
			}
		}
		
		return $modified_files;
	}
	
	
	/**
	 * Method to get the layout texts (cached)
	 * 
	 * @return string
	 * @since 3.0
	 */
	static function getLayoutTexts($layout_typename)
	{
		$apply_cache = 1;//FLEXI_CACHE;
		if ( $apply_cache )
		{
			$tmplcache = JFactory::getCache('com_flexicontent_tmpl');  // Get Joomla Cache of '...tmpl' Caching Group
			$tmplcache->setCaching(1); 		              // Force cache ON
			$tmplcache->setLifeTime(FLEXI_CACHE_TIME); 	// Set expire time (default is 1 hour)
			$layout_texts = $tmplcache->call(array('flexicontent_tmpl', '_getLayoutTexts'), $layout_typename);
		}
		else {
			$layout_texts = flexicontent_tmpl::_getLayoutTexts($layout_typename);
		}
		
		return $layout_texts;
	}
	
	
	/**
	 * Method to get the layout texts (non-cached)
	 * 
	 * @return string
	 * @since 3.0
	 */
	static function _getLayoutTexts($layout_typename)
	{
		static $layout_texts;
		if ( isset($layout_texts[$layout_typename]) ) return $layout_texts[$layout_typename];
		
		$layout_texts[$layout_typename] = new stdClass();
		
		// Get all templates
		$tmpls = flexicontent_tmpl::getTemplates();
		
		// Load language files of all templates if not already loaded
		FLEXIUtilities::loadTemplateLanguageFiles();
		
		// Get layout parameters and find the layout title
		foreach($tmpls->$layout_typename as $layout_folder => $tmpl)
		{
			if ( $tmpl && empty($tmpl->parameters) ) {
				//echo "CREATING PARAMETERS FOR: {$layout_typename} - {$layout_folder}<br/>";
				$tmpl->parameters = new JRegistry( flexicontent_tmpl::getLayoutparams($layout_typename, $layout_folder, '') );
			}
			$layout_texts[$layout_typename]->$layout_folder = new stdClass();
			$layout_texts[$layout_typename]->$layout_folder->title       = $tmpl ? JText::_($tmpl->parameters->get('custom_layout_title', @ $tmpl->defaulttitle)) : '';
			$layout_texts[$layout_typename]->$layout_folder->description = $tmpl ? JText::_(@ $tmpl->description) : '';
		}
		
		return $layout_texts[$layout_typename];
	}
	
	
	/**
	 * Method to get the layout parameters of an layout configuration row
	 * 
	 * @return string
	 * @since 3.0
	 */
	static function getLayoutparams($type, $folder, $cfgname, $force = false)
	{
		static $layout_params = array();
		if ( !$force && isset($layout_params[$type][$folder][$cfgname]) ) return $layout_params[$type][$folder][$cfgname];
		
		$db = JFactory::getDBO();
		$query = 'SELECT template as folder, cfgname, attribs, layout as type'
			. ' FROM #__flexicontent_layouts_conf';
		$db->setQuery($query);
		$layout_confs = $db->loadObjectList();
		
		foreach ($layout_confs as $L) {
			$layout_params[$L->type][$L->folder][$L->cfgname] = !empty($L->attribs) ? $L->attribs : '';
		}
		
		if ( !isset($layout_params[$type][$folder][$cfgname]) ) $layout_params[$type][$folder][$cfgname] = '';
		return $layout_params[$type][$folder][$cfgname];
	}
	
	
	static function getTemplates($layout_name = null, $skip_less_compile = false)
	{
		static $tmpls = null;
		
		static $print_logging_info = null;
		$print_logging_info = $print_logging_info !== null  ?  $print_logging_info  :  JComponentHelper::getParams('com_flexicontent')->get('print_logging_info');
		if ($print_logging_info) { global $fc_run_times; $start_microtime = microtime(true); }
		$debug = JDEBUG || $print_logging_info;
		
		
		// *****************************************************
		// Parse view.xml (item|category).xml files of templates
		// *****************************************************
		
		$checked_layouts = is_array($layout_name)  ?  $layout_name  :  ( $layout_name ? array($layout_name) : array() );
		//printr_r($checked_layouts);
		//echo "<pre>"; debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS); echo "</pre>";
		
		if ($tmpls === null)
		{
			// Get templates reparsing XML, checking if -specific layout(s)- files have been modified
			if ($print_logging_info) $start_microtime = microtime(true);
			$tmpls = flexicontent_tmpl::parseTemplates('', false, $checked_layouts);
			if ($print_logging_info) $fc_run_times['templates_parsing_xml'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		}
		
		
		// *******************************
		// Check/Compile LESS files to CSS
		// *******************************
		
		if ( count($checked_layouts) && !$skip_less_compile )
		{
			// Compile LESS to CSS, checking if -specific layout(s)- files have been modified
			if ($print_logging_info) $start_microtime = microtime(true);
			flexicontent_tmpl::checkCompileLess($tmpls, false, $checked_layouts);
			if ($print_logging_info) $fc_run_times['templates_parsing_less'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		}
		// Uncomment to update ALL
		//$all = array(); foreach ($tmpls->items as $tmplname => $i) $t[] = $tmplname;  flexicontent_tmpl::checkCompileLess($tmpls, false, $all);
		
		
		// *******************************************************************************
		// Load Template-Specific language file(s) to override or add new language strings
		// *******************************************************************************
		
		// Load language files of -specific layout(s)-
		//echo "SPECIFIC files: ".print_r($layout_name, true);
		foreach ($checked_layouts as $foldername)
		{
			FLEXIUtilities::loadTemplateLanguageFile( $foldername );
		}
		
		return $tmpls;
	}
	
	
	static function getThemes($tmpldir='')
	{
		jimport('joomla.filesystem.folder');
		$tmpldir = $tmpldir ? $tmpldir : JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'templates';
		$themes = JFolder::folders($tmpldir);  // Get specific template folder

		return $themes;
	}

	/**
	 * Method to get all available fields for a template in a view
	 *
	 * @access public
	 * @return object
	 */
	static function getFieldsByPositions($folder, $type) {
		if ($type=='item') $type='items';

		static $templates;
		if(!isset($templates[$folder])) {
			$templates[$folder] = array();
		}
		if(!isset($templates[$folder][$type])) {
			$db = JFactory::getDBO();
			$query  = 'SELECT *'
					. ' FROM #__flexicontent_templates'
					. ' WHERE template = ' . $db->Quote($folder)
					. ' AND layout = ' . $db->Quote($type)
					;
			$db->setQuery($query);
			$positions = $db->loadObjectList('position');
			foreach ($positions as $pos) {
				$pos->fields = explode(',', $pos->fields);
			}
			$templates[$folder][$type] = & $positions;
		}
		return $templates[$folder][$type];
	}
}

class flexicontent_images
{
	/**
	 * Get file size and icons
	 *
	 * @since 1.5
	 */
	static function BuildIcons($rows)
	{
		jimport('joomla.filesystem.path' );
		jimport('joomla.filesystem.file');
		$NA = '-';

		for ($i=0, $n=count($rows); $i < $n; $i++)
		{
			$basePath = $rows[$i]->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;

			if ($rows[$i]->url)
			{
				$size = (int)$rows[$i]->size ? (int)$rows[$i]->size : $NA;
			}
			else if (is_file($basePath.DS.$rows[$i]->filename))
			{
				$path = str_replace(DS, '/', JPath::clean($basePath.DS.$rows[$i]->filename));
				$size = filesize($path);
			}
			else
			{
				$size = $NA;
			}

			if (is_numeric($size))
			{
				if ($size < 1024) {
					$rows[$i]->size = $size . ' bytes';
				} else {
					if ($size >= 1024 && $size < 1024 * 1024) {
						$rows[$i]->size = sprintf('%01.2f', $size / 1024.0) . ' KBs';
					} else {
						$rows[$i]->size = sprintf('%01.2f', $size / (1024.0 * 1024)) . ' MBs';
					}
				}
			} else {
				$rows[$i]->size = $size;
			}

			if ($rows[$i]->url == 1)
			{
				$ext = $rows[$i]->ext;
			} else {
				$ext = strtolower(JFile::getExt($rows[$i]->filename));
			}
			switch ($ext)
			{
				// Image
				case 'jpg':
				case 'png':
				case 'gif':
				case 'xcf':
				case 'odg':
				case 'bmp':
				case 'jpeg':
					$rows[$i]->icon = 'components/com_flexicontent/assets/images/mime-icon-16/image.png';
					break;

				// Non-image document
				default:
					$icon = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'assets'.DS.'images'.DS.'mime-icon-16'.DS.$ext.'.png';
					if (file_exists($icon)) {
						$rows[$i]->icon = 'components/com_flexicontent/assets/images/mime-icon-16/'.$ext.'.png';
					} else {
						$rows[$i]->icon = 'components/com_flexicontent/assets/images/mime-icon-16/unknown.png';
					}
					break;
			}

		}

		return $rows;
	}

}


class FLEXIUtilities
{
	static function funcIsDisabled($function)
	{
		static $disabledFuncs = null;
		$func = strtolower($function);
		if ($disabledFuncs !== null) return isset($disabledFuncs[$func]);
		
		$disabledFuncs = array();
		$disable_local  = explode(',',     strtolower(@ini_get('disable_functions')));
		$disable_global = explode(',', strtolower(@get_cfg_var('disable_functions')));
		
		foreach ($disable_local as $key => $value) {
			$disabledFuncs[trim($value)] = 'local';
		}
		foreach ($disable_global as $key => $value) {
			$disabledFuncs[trim($value)] = 'global';
		}
		if (@ini_get('safe_mode')) {
			$disabledFuncs['shell_exec']     = 'local';
			$disabledFuncs['set_time_limit'] = 'local';
		}
		
		return isset($disabledFuncs[$func]);
	}
	
	
	/**
	 * Load all template language files to override or add new language strings
	 *
	 * @return object
	 * @since 3.0
	 */
	static function loadTemplateLanguageFiles( $tmplnames=null, $tmpldir='' )
	{
		if (!$tmplnames)
			$tmplnames = flexicontent_tmpl::getThemes($tmpldir);
		
		// Load all language files (iterate 'category' layout type)
		static $langs = array();
		foreach ($tmplnames as $tmplname) if ( !isset($langs[$tmplname]) )
		{
			$langs[$tmplname] = true;
			FLEXIUtilities::loadTemplateLanguageFile( $tmplname );
		}
	}
	
	
	/**
	 * Load Template-Specific language file to override or add new language strings
	 *
	 * @return object
	 * @since 2.0
	 */
	static function loadTemplateLanguageFile( $tmplname='default', $tmpldir='', $extension='', $language_tag='' )
	{
		static $print_logging_info = null;
		$print_logging_info = $print_logging_info !== null  ?  $print_logging_info  :  JComponentHelper::getParams('com_flexicontent')->get('print_logging_info');
		
		if ($print_logging_info) {
			global $fc_run_times; $start_microtime = microtime(true);
			if ( !isset($fc_run_times['templates_parsing_ini']) ) $fc_run_times['templates_parsing_ini'] = 0;
		}
		
		//echo "Loading language file for template: ". $tmplname ."<br/>";
		// Check that template name was given
		$tmplname = empty($tmplname) ? 'default' : $tmplname;
		
		// This is normally component/module/plugin name, we could use 'category', 'items', etc to have a view specific language file
		// e.g. en/en.category.ini, but this is an overkill and make result into duplication of strings ... better all in one file
		//$extension = $extension ? $extension : 'com_flexicontent';
		
		// Get current UI language, because language file paths use LL-CC (language-country)
		$language_tag = $language_tag ? $language_tag : JFactory::getLanguage()->getTag();
		
		// We will use template folder as BASE of language files instead of joomla's language folder
		// Since FLEXIcontent templates are meant to be user-editable it makes sense to place language files inside them
		$tmpldir  = $tmpldir ? $tmpldir : JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'templates';
		$base_dir = $tmpldir.DS.$tmplname;
		
		// Final use joomla's API to load our template's language files -- (load english template language file then override with current language file)
		JFactory::getLanguage()->load($extension, $base_dir, 'en-GB', $reload=true);        // Fallback to english language template file
		JFactory::getLanguage()->load($extension, $base_dir, $language_tag, $reload=true);  // User's current language template file
		
		if ($print_logging_info) $fc_run_times['templates_parsing_ini'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	}
	
	
	/**
	 * Method to get information of site languages
	 *
	 * @return object
	 * @since 1.5
	 */
	static function getlanguageslist($published_only=false, $add_all = true)
	{
		$app = JFactory::getApplication();
		$db = JFactory::getDBO();
		static $pub_languages = null;
		static $all_languages = null;
		
		if ( $published_only ) {
			if ($pub_languages) return $pub_languages;
			else $pub_languages = false;
		}
		
		if ( !$published_only ) {
			if ($all_languages) return $all_languages;
			else $all_languages = false;
		}
		
		
		// ******************
		// Retrieve languages
		// ******************
		
		$query = 'SELECT DISTINCT lc.lang_id as id, lc.image as image_prefix, lc.lang_code as code, lc.title_native, '
			//. ' CASE WHEN CHAR_LENGTH(lc.title_native) THEN CONCAT(lc.title, " (", lc.title_native, ")") ELSE lc.title END as name '
			. ' lc.title as name '
			.' FROM #__languages as lc '
			.' WHERE 1 '.($published_only ? ' AND lc.published=1' : '')
			. ' ORDER BY lc.ordering ASC '
			;
	
		if ( !empty($query) ) {
			$db->setQuery($query);
			$languages = $db->loadObjectList('id');
			//echo "<pre>"; print_r($languages); echo "</pre>"; exit;
			if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
		}
		
		
		// *********************
		// Calculate image paths
		// *********************
		
		$imgpath	= $app->isAdmin() ? '../images/':'images/';
		$mediapath	= $app->isAdmin() ? '../media/mod_languages/images/' : 'media/mod_languages/images/';
		
		
		// ************************
		// Prepare language objects
		// ************************
		
		$_languages = array();
		
		// Add 'ALL' option
		if ($add_all) {
			$lang_all = new stdClass();
			$lang_all->code = '*';
			$lang_all->name = JText::_('FLEXI_ALL');
			$lang_all->shortcode = '*';
			$lang_all->id = 0;
			$_languages = array( 0 => $lang_all);
		}
		
		// Check if no languages found and return
		if ( empty($languages) )  return $_languages;
		
		foreach ($languages as $lang) {
			// Calculate/Fix languages data
			$lang->shortcode = strpos($lang->code,'-') ?
				substr($lang->code, 0, strpos($lang->code,'-')) :
				$lang->code;
			//$lang->id = $lang->extension_id;
			$image_prefix = $lang->image_prefix ? $lang->image_prefix : $lang->shortcode;
			// $lang->image, holds a custom image path
			$lang->imgsrc = @$lang->image ? $imgpath . $lang->image : $mediapath . $image_prefix . '.gif';
			$_languages[$lang->id] = $lang;
		}

		// Also prepend '*' (ALL) language to language array
		//echo "<pre>"; print_r($languages); echo "</pre>"; exit;

		// Select language -ALL- if none selected
		//$selected = $selected ? $selected : '*';    // WRONG behavior commented out
		
		$languages = $_languages;
		
		if ( $published_only ) {
			$pub_languages = $_languages;
		} else {
			$all_languages = $_languages;
		}
		return $_languages;
	}
	
	
	/**
	 * Method to build an array of languages hashed by id or by language code
	 *
	 * @return object
	 * @since 1.5
	 */
	static function getLanguages($hash='code', $published_only=false)
	{
		static $langs = array();
		static $languages;
		
		if (isset($langs[$hash])) return $langs[$hash];
		if (!$languages) $languages = FLEXIUtilities::getlanguageslist($published_only);
		
		$langs[$hash] = new stdClass();
		foreach ($languages as $language) {
			$langs[$hash]->{$language->$hash} = $language;
		}

		return $langs[$hash];
	}


	/**
	 * Method to get the last version kept
	 *
	 * @return int
	 * @since 1.5
	 */
	static function &getLastVersions ($id=NULL, $justvalue=false, $force=false)
	{
		static $g_lastversions = NULL;
		static $all_retrieved  = false;

		if(
			$g_lastversions===NULL || $force ||
			($id && !isset($g_lastversions[$id])) ||
			(!$id && !$all_retrieved)
		) {
			if (!$id) $all_retrieved = true;
			$g_lastversions =  array();
			$db = JFactory::getDBO();
			$query = "SELECT item_id as id, max(version_id) as version"
									." FROM #__flexicontent_versions"
									." WHERE 1"
									.($id ? " AND item_id=".(int)$id : "")
									." GROUP BY item_id";
			$db->setQuery($query);
			$rows = $db->loadAssocList('id');
			foreach($rows as $row_id => $row) {
				$g_lastversions[$row_id] = $row;
			}
			unset($rows);
		}

		// Special case (version number of new item): return version zero
		if (!$id && $justvalue) { $v = 0; return $v; }

		// an item id was given return item specific data
		if ($id) {
			$return = $justvalue ? @$g_lastversions[$id]['version'] : @$g_lastversions[$id];
			return $return;
		}

		// no item id was given return all version data
		return $g_lastversions;
	}


	static function &getCurrentVersions ($id=NULL, $justvalue=false, $force=false)
	{
		static $g_currentversions;  // cache ...

		if( $g_currentversions==NULL || $force )
		{
			$db = JFactory::getDBO();
			if (!FLEXI_J16GE) {
				$query = "SELECT i.id, i.version FROM #__content AS i"
					." WHERE i.sectionid=".FLEXI_SECTION
					. ($id ? " AND i.id=".(int)$id : "")
					;
			} else {
				$query = "SELECT i.id, i.version FROM #__content as i"
						. " JOIN #__categories AS c ON i.catid=c.id"
						. " WHERE c.extension='".FLEXI_CAT_EXTENSION."'"
						. ($id ? " AND i.id=".(int)$id : "")
						;
			}
			$db->setQuery($query);
			$rows = $db->loadAssocList();
			$g_currentversions = array();
			foreach($rows as $row) {
				$g_currentversions[$row["id"]] = $row;
			}
			unset($rows);
		}

		// Special case (version number of new item): return version zero
		if (!$id && $justvalue) { $v = 0; return $v; }

		// an item id was given return item specific data
		if($id) {
			$return = $justvalue ? @$g_currentversions[$id]['version'] : @$g_currentversions[$id];
			return $return;
		}

		// no item id was given return all version data
		return $g_currentversions;
	}


	static function &getLastItemVersion($id)
	{
		$db = JFactory::getDBO();
		$query = 'SELECT max(version) as version'
				.' FROM #__flexicontent_items_versions'
				.' WHERE item_id = ' . (int)$id
				;
		$db->setQuery($query, 0, 1);
		$lastversion = $db->loadResult();

		return (int)$lastversion;
	}


	static function &currentMissing()
	{
		static $status;
		if(!$status) {
			$db = JFactory::getDBO();
			$query = "SELECT c.id,c.version,iv.version as iversion FROM #__content as c "
				." LEFT JOIN #__flexicontent_items_versions as iv ON c.id=iv.item_id AND c.version=iv.version"
				." JOIN #__categories as cat ON c.catid=cat.id"
				." WHERE c.version > '1' AND iv.version IS NULL"
				.(!FLEXI_J16GE ? " AND sectionid='".FLEXI_SECTION."'" : " AND cat.extension='".FLEXI_CAT_EXTENSION."'")
				." LIMIT 0,1";
			$db->setQuery($query);
			$rows = $db->loadObjectList("id");
			if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');

			$rows = is_array($rows) ? $rows : array();
			$status = false;
			if(count($rows)>0) {
				$status = true;
			}
			unset($rows);
		}
		return $status;
	}


	/**
	 * Method to get the first version kept
	 *
	 * @return int
	 * @since 1.5
	 */
	static function &getFirstVersion($id, $max, $current_version)
	{
		$db = JFactory::getDBO();
		$query = 'SELECT version_id'
				.' FROM #__flexicontent_versions'
				.' WHERE item_id = ' . (int)$id
				.' AND version_id!=' . (int)$current_version
				.' ORDER BY version_id DESC'
				;
		$db->setQuery($query, ($max-1), 1);
		$firstversion = (int)$db->loadResult();  // return zero if no version is found
		return $firstversion;
	}


	/**
	 * Method to get the versions count
	 *
	 * @return int
	 * @since 1.5
	 */
	static function &getVersionsCount($id)
	{
		$db = JFactory::getDBO();
		$query = 'SELECT COUNT(*)'
				.' FROM #__flexicontent_versions'
				.' WHERE item_id = ' . (int)$id
				;
		$db->setQuery($query);
		$versionscount = $db->loadResult();

		return $versionscount;
	}


	static function doPlgAct()
	{
		$plg = JRequest::getVar('plg');
		$act = JRequest::getVar('act');
		if($plg && $act) {
			$plgfolder = DS.strtolower($plg);
			$path = JPATH_ROOT.DS.'plugins'.DS.'flexicontent_fields'.$plgfolder.DS.strtolower($plg).'.php';
			if(file_exists($path)) require_once($path);
			$class = "plgFlexicontent_fields{$plg}";
			if(class_exists($class) && in_array($act, get_class_methods($class))) {
				//call_user_func("$class::$act");
				call_user_func(array($class, $act));
			}
		}
	}


	static function getCache($group='', $client=0)
	{
		$conf = JFactory::getConfig();
		//$client = 0;//0 is site, 1 is admin
		$options = array(
			'defaultgroup'	=> $group,
			'storage' 		=> $conf->get('cache_handler', ''),
			'caching'		=> true,
			'cachebase'		=> ($client == 1) ? JPATH_ADMINISTRATOR . '/cache' : $conf->get('cache_path', JPATH_SITE . '/cache')
		);

		jimport('joomla.cache.cache');
		$cache = JCache::getInstance('', $options);
		return $cache;
	}


	static function call_FC_Field_Func( $fieldtype, $func, $args=null )
	{
		static $fc_plgs;

		if ( !isset( $fc_plgs[$fieldtype] ) ) {
			// 1. Load Flexicontent Field (the Plugin file) if not already loaded
			$plgfolder = DS.strtolower($fieldtype);
			$path = JPATH_ROOT.DS.'plugins'.DS.'flexicontent_fields'.$plgfolder.DS.strtolower($fieldtype).'.php';
			if(file_exists($path)) require_once($path);
			else {
				JFactory::getApplication()->enqueueMessage(nl2br("While calling field method: $func(): cann't find field type: $fieldtype. This is internal error or wrong field name"),'error');
				return;
			}

			// 2. Create plugin instance
			$class = "plgFlexicontent_fields{$fieldtype}";
			if( class_exists($class) ) {
				// Create class name of the plugin
				$className = 'plg'.'flexicontent_fields'.$fieldtype;
				// Create a plugin instance
				$dispatcher = JDispatcher::getInstance();
				$fc_plgs[$fieldtype] =  new $className($dispatcher, array());
				// Assign plugin parameters, (most FLEXI plugins do not have plugin parameters), CHECKING if parameters exist
				$plugin_db_data = JPluginHelper::getPlugin('flexicontent_fields',$fieldtype);
				$fc_plgs[$fieldtype]->params = new JRegistry( @$plugin_db_data->params );
			} else {
				JFactory::getApplication()->enqueueMessage(nl2br("Could not find class: $className in file: $path\n Please correct field name"),'error');
				return;
			}
		}

		// 3. Execute only if it exists
		if (!$func) return;
		$class = "plgFlexicontent_fields{$fieldtype}";
		if(in_array($func, get_class_methods($class))) {
			return call_user_func_array(array($fc_plgs[$fieldtype], $func), $args);
		}
	}


	/* !!! FUNCTION NOT DONE YET */
	static function call_Content_Plg_Func( $plgname, $func, $args=null )
	{
		static $content_plgs;

		if ( !isset( $content_plgs[$plgname] ) ) {
			// 1. Load Flexicontent Field (the Plugin file) if not already loaded
			$plgfolder = DS.strtolower($plgname);
			$path = JPATH_ROOT.DS.'plugins'.DS.'content'.$plgfolder.DS.strtolower($plgname).'.php';
			if(file_exists($path)) require_once($path);
			else {
				JFactory::getApplication()->enqueueMessage(nl2br("Cannot load CONTENT Plugin: $plgname\n Plugin may have been uninistalled"),'error');
				return;
			}

			// 2. Create plugin instance
			$class = "plgContent{$plgname}";
			if( class_exists($class) ) {
				// Create class name of the plugin
				$className = 'plg'.'content'.$plgname;
				// Create a plugin instance
				$dispatcher = JDispatcher::getInstance();
				$content_plgs[$plgname] =  new $className($dispatcher, array());
				// Assign plugin parameters, (most FLEXI plugins do not have plugin parameters)
				$plugin_db_data = JPluginHelper::getPlugin('content',$plgname);
				$content_plgs[$plgname]->params = new JRegistry( @$plugin_db_data->params );
			} else {
				JFactory::getApplication()->enqueueMessage(nl2br("Could not find class: $className in file: $path\n Please correct field name"),'error');
				return;
			}
		}

		// 3. Execute only if it exists
		$class = "plgContent{$plgname}";
		if(in_array($func, get_class_methods($class))) {
			return call_user_func_array(array($content_plgs[$plgname], $func), $args);
		}
	}


	/**
	 * Return unicode char by its code
	 * Credits: ?
	 *
	 * @param int $dec
	 * @return utf8 char
	 */
	static function unichr($dec) {
		if ($dec < 128) {
			$utf = chr($dec);
		} else if ($dec < 2048) {
			$utf = chr(192 + (($dec - ($dec % 64)) / 64));
			$utf .= chr(128 + ($dec % 64));
		} else {
			$utf = chr(224 + (($dec - ($dec % 4096)) / 4096));
			$utf .= chr(128 + ((($dec % 4096) - ($dec % 64)) / 64));
			$utf .= chr(128 + ($dec % 64));
		}
		return $utf;
	}


	/**
	 * Return unicode code of a utf8 char
	 * Credits: ?
	 *
	 * @param int $c
	 * @return utf8 ord
	 */
	static function uniord($c) {
		$h = ord($c{0});
		if ($h <= 0x7F) {
			return $h;
		} else if ($h < 0xC2) {
			return false;
		} else if ($h <= 0xDF) {
			return ($h & 0x1F) << 6 | (ord($c{1}) & 0x3F);
		} else if ($h <= 0xEF) {
			return ($h & 0x0F) << 12 | (ord($c{1}) & 0x3F) << 6
			| (ord($c{2}) & 0x3F);
		} else if ($h <= 0xF4) {
			return ($h & 0x0F) << 18 | (ord($c{1}) & 0x3F) << 12
			| (ord($c{2}) & 0x3F) << 6
			| (ord($c{3}) & 0x3F);
		} else {
			return false;
		}
	}


	/**
	 * Return unicode string when giving an array of utf8 ords
	 * Credits: Darien Hager
	 *
	 * @param  $ords   utf8 ord arrray
	 * @return $str    utf8 string
	 */
	static function ords_to_unistr($ords, $encoding = 'UTF-8')
	{
		if (!extension_loaded('mbstring')) return '';
		
		// Turns an array of ordinal values into a string of unicode characters
		$str = '';
		for($i = 0; $i < sizeof($ords); $i++){
			// Pack this number into a 4-byte string
			// (Or multiple one-byte strings, depending on context.)
			$v = $ords[$i];
			$str .= pack("N",$v);
		}
		$str = mb_convert_encoding($str, $encoding, "UCS-4BE");
		
		return($str);
	}


	/**
	 * Return unicode string when giving an array of utf8 ords
	 * Credits: Darien Hager
	 *
	 * @param  $str    utf8 string
	 * @return $ords   utf8 ord arrray
	 */
	static function unistr_to_ords($str, $encoding = 'UTF-8')
	{
		if (!extension_loaded('mbstring')) return array();
		
		// Turns a string of unicode characters into an array of ordinal values,
		// Even if some of those characters are multibyte.
		$ords = array();
		$str = mb_convert_encoding($str, "UCS-4BE", $encoding);

		// Visit each unicode character
		for($i = 0; $i < mb_strlen($str, "UCS-4BE"); $i++)
		{
			// Now we have 4 bytes. Find their total
			// numeric value.
			$s2 = mb_substr($str, $i, 1, "UCS-4BE");
			$val = unpack("N",$s2);
			$ords[] = $val[1];
		}
		
		return($ords);
	}
	

	/*
	 * Method to confirm if a given string is a valid MySQL date
	 * param  string			$date
	 * return boolean			true if valid date, false otherwise
	 */
	static function isSqlValidDate($date)
	{
		$db = JFactory::getDBO();
		$q = "SELECT day(".$db->Quote($date).")";
		$db->setQuery($q);
		$num = $db->loadResult();
		$valid = $num > 0;
		return $valid;
	}

	/*
	 * Converts a string (containing a csv file) into a array of records ( [row][col] )and returns it
	 * @author: Klemen Nagode (in http://stackoverflow.com/)
	 */
	static function csvstring_to_array($string, $field_separator = ',', $enclosure_char = '"', $record_separator = "\n")
	{
		$array = array();   // [row][cols]
		$size = strlen($string);
		$columnIndex = 0;
		$rowIndex = 0;
		$fieldValue="";
		$isEnclosured = false;
		// Field separator
		$fld_sep_start = $field_separator{0};
		$fld_sep_size  = strlen( $field_separator );
		// Record (item) separator
		$rec_sep_start = $record_separator{0};
		$rec_sep_size  = strlen( $record_separator );

		for($i=0; $i<$size;$i++)
		{
			$char = $string{$i};
			$addChar = "";

			if($isEnclosured) {
				if($char==$enclosure_char) {
					if($i+1<$size && $string{$i+1}==$enclosure_char) {
						// escaped char
						$addChar=$char;
						$i++; // dont check next char
					} else {
						$isEnclosured = false;
					}
				} else {
					$addChar=$char;
				}
			}
			else
			{
				if($char==$enclosure_char) {
					$isEnclosured = true;
				} else {
					if( $char==$fld_sep_start && $i+$fld_sep_size < $size && substr($string, $i,$fld_sep_size) == $field_separator ) {
						$i = $i + ($fld_sep_size-1);
						$array[$rowIndex][$columnIndex] = $fieldValue;
						$fieldValue="";

						$columnIndex++;
					} else if( $char==$rec_sep_start && $i+$rec_sep_size < $size && substr($string, $i,$rec_sep_size) == $record_separator ) {
						$i = $i + ($rec_sep_size-1);
						echo "\n";
						$array[$rowIndex][$columnIndex] = $fieldValue;
						$fieldValue="";
						$columnIndex=0;
						$rowIndex++;
					} else {
						$addChar=$char;
					}
				}
			}
			if($addChar!="") {
				$fieldValue.=$addChar;
			}
		}

		if($fieldValue) { // save last field
			$array[$rowIndex][$columnIndex] = $fieldValue;
		}
		return $array;
	}

	/**
	 * Helper method to format a parameter value as array
	 *
	 * @return object
	 * @since 1.5
	 */
	static function paramToArray($value, $regex = false, $filterfunc = false, $remove_save_flag=false)
	{
		if ($regex && !is_array($value)) {
			$value = trim($value);
			$value = !$value  ?  array()  :  preg_split($regex, $value);
		}
		if ($filterfunc) {
			array_map($filterfunc, $value);
		}

		if (!is_array($value)) {
			$value = explode("|", $value);
			$value = ($value[0]=='') ? array() : $value;
		} else {
			$value = !is_array($value) ? array($value) : $value;
		}
		if ($remove_save_flag) foreach($value as $i =>$v) if ($v=='__SAVED__') unset($value[$i]);
		return $value;
	}

	/**
	 * Suppresses given plugins (= prevents them from triggering)
	 *
	 * @return void
	 * @since 1.5
	 */
	static function suppressPlugins( $name_arr, $action ) {
		static $plgs = array();

		foreach	($name_arr as $name)
		{
			if (!isset($plgs[$name])) {
				JPluginHelper::importPlugin('content', $name);
				$plgs[$name] = JPluginHelper::getPlugin('content', $name);
			}
			if ($plgs[$name] && $action=='suppress') {
				$plgs[$name]->type = '_suppress';
			}
			if ($plgs[$name] && $action=='restore') {
				$plgs[$name]->type = 'content';
			}
		}
	}
}


/*
 * CLASS with common methods for handling interaction with DB
 */
class flexicontent_db
{
	/**
	 * Method to get the (language filtered) name of all access levels
	 * 
	 * @return string
	 * @since 1.5
	 */
	static function getAccessNames($accessid=null)
	{
		static $access_names = array();
		
		if ( $accessid!==null && isset($access_names[$accessid]) ) return $access_names[$accessid];
		
		$db = JFactory::getDBO();
		$db->setQuery('SELECT id, title FROM #__viewlevels');
		$_arr = $db->loadObjectList();
		$access_names = array(0=>'Public');  // zero does not exist in J2.5+ but we set it for compatibility
		foreach ($_arr as $o) $access_names[$o->id] = JText::_($o->title);
		
		if ( $accessid )
			return isset($access_names[$accessid]) ? $access_names[$accessid] : 'not found access id: '.$accessid;
		else
			return $access_names;
	}
	
	
	/**
	 * Method to get the type parameters of an item
	 * 
	 * @return string
	 * @since 1.5
	 */
	static function getTypeAttribs($force = false, $typeid)
	{
		static $typeparams = array();
		
		if ( !$force && isset($typeparams[$typeid]) ) return $typeparams[$typeid];
		
		$db = JFactory::getDBO();
		$query	= 'SELECT t.id, t.attribs'
			. ' FROM #__flexicontent_types AS t'
			.( $typeid ? ' WHERE t.id = ' . (int)$typeid : '')
			;
		$db->setQuery($query);
		if ( $typeid ) {
			$data = $db->loadObject();
			if (!$data) return false;
			
			$typeid = $data->id;
			$typeparams[$typeid] = $data->attribs;
			return $typeparams[$typeid];
		}
		else {
			$rows = $db->loadObjectList();
			foreach($rows as $data) {
				$typeid = $data->id;
				$typeparams[$typeid] = $data->attribs;
			}
			return $typeparams;
		}
	}
	
	/**
	 * Method to get the nr of favourites of anitem
	 *
	 * @access	public
	 * @return	integer on success
	 * @since	1.0
	 */
	static function getFavourites($type, $item_id)
	{
		$db = JFactory::getDBO();
		
		$query = '
			SELECT COUNT(id) AS favs
			FROM #__flexicontent_favourites
			WHERE itemid = '.(int)$item_id.'
				AND type = '.(int)$type;
		$db->setQuery($query);
		
		return $db->loadResult();
	}
	
	
	/**
	 * Method to get the nr of favourites of an user
	 *
	 * @access	public
	 * @since	1.0
	 */
	static function getFavoured($type, $item_id, $user_id)
	{
		$db = JFactory::getDBO();
		
		$query = '
			SELECT COUNT(id) AS fav
			FROM #__flexicontent_favourites
			WHERE itemid = '.(int)$item_id.'
				AND userid = '.(int)$user_id.'
				AND type = '.(int)$type;
		$db->setQuery($query);
		
		return $db->loadResult();
	}
	
	
	/**
	 * Method to remove a favourite
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	static function removefav($type, $item_id, $user_id)
	{
		$db = JFactory::getDBO();
		
		$query = '
			DELETE FROM #__flexicontent_favourites
			WHERE itemid = '.(int)$item_id.'
				AND userid = '.(int)$user_id.'
				AND type = '.(int)$type;
		$db->setQuery($query);
		
		return $db->execute();
	}
	
	
	/**
	 * Method to add a favourite
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.0
	 */
	static function addfav($type, $item_id, $user_id)
	{
		$db = JFactory::getDBO();
		
		$obj = new stdClass();
		$obj->itemid = (int)$item_id;
		$obj->userid = (int)$user_id;
		$obj->type   = (int)$type;
		
		return $db->insertObject('#__flexicontent_favourites', $obj);
	}
	
	
	/*
	 * Retrieve author/user configuration
	 *
	 * @return object
	 * @since 1.5
	 */
	static function getUserConfig($user_id)
	{
		$db = JFactory::getDBO();
		
		$db->setQuery('SELECT author_basicparams FROM #__flexicontent_authors_ext WHERE user_id = ' . $user_id);
		$authorparams = $db->loadResult();
		$authorparams = new JRegistry($authorparams);
		
		return $authorparams;
	}
	
	
	/*
	 * Find stopwords and too small words
	 *
	 * @return array
	 * @since 1.5
	 */
	static function removeInvalidWords($words, &$stopwords, &$shortwords, $tbl='flexicontent_items_ext', $col='search_index', $isprefix=1)
	{
		$db     = JFactory::getDBO();
		$app    = JFactory::getApplication();
		$option = JRequest::getVar('option');
		$min_word_len = $app->getUserState( $option.'.min_word_len', 0 );
		
		$_word_clause = $isprefix ? '+%s*' : '+%s';
		$query = 'SELECT '.$col
			.' FROM #__'.$tbl
			.' WHERE MATCH ('.$col.') AGAINST ("'.$_word_clause.'" IN BOOLEAN MODE)'
			.' LIMIT 1';
		$_words = array();
		foreach ($words as $word) {
			$quoted_word = $db->escape($word, true);
			$q = sprintf($query, $quoted_word);
			$db->setQuery($q);
			$result = $db->loadAssocList();
			if ( !empty($result) ) {
				$_words[] = $word;      // word found
			} else if ( StringHelper::strlen($word) < $min_word_len ) {
				$shortwords[] = $word;  // word not found and word too short
			} else {
				$stopwords[] = $word;   // word not found
			}
		}
		return $_words;
	}
	
	/**
	 * Helper method to execute an SQL file containing multiple queries
	 *
	 * @return object
	 * @since 1.5
	 */
	static function execute_sql_file($sql_file)
	{
		$queries = file_get_contents( $sql_file );
		$queries = preg_split("/;+(?=([^'|^\\\']*['|\\\'][^'|^\\\']*['|\\\'])*[^'|^\\\']*[^'|^\\\']$)/", $queries);
		
		$db = JFactory::getDBO();
		foreach ($queries as $query) {
			$query = trim($query);
			if (!$query) continue;
			
			$db->setQuery($query);
			$result = $db->execute();
			if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
		}
	}
	
	
	/**
	 * Helper method to execute a query directly, bypassing Joomla DB Layer
	 *
	 * @return object
	 * @since 1.5
	 */
	static function & directQuery($query, $assoc = false, $unbuffered = false)
	{
		$db     = JFactory::getDBO();
		$app = JFactory::getApplication();
		$dbprefix = $app->getCfg('dbprefix');
		$dbtype   = $app->getCfg('dbtype');
		$dbtype   = $dbtype == 'mysql' && !function_exists('mysql_query') ? 'mysqli' : $dbtype;  // PHP 7 removes mysql but 'mysql' database may still be in the configuration file

		$query = $db->replacePrefix($query);  //echo "<pre>"; print_r($query); echo "\n\n";
		$db_connection = $db->getConnection();
		
		$data = array();
		if ($dbtype == 'mysqli' )
		{
			$result = $unbuffered ?
				mysqli_query( $db_connection , $query, MYSQLI_USE_RESULT ) :
				mysqli_query( $db_connection , $query ) ;
			if ($result===false)
				throw new Exception('error '.__FUNCTION__.'():: '.mysqli_error($db_connection));
			
			if ($assoc) {
				while($row = mysqli_fetch_assoc($result)) $data[] = $row;
			} else {
				while($row = mysqli_fetch_object($result)) $data[] = $row;
			}
			mysqli_free_result($result);
		}
		
		else if ( $dbtype == 'mysql' )
		{
			$result = $unbuffered ?
				mysql_unbuffered_query( $query, $db_connection ) :
				mysql_query( $query, $db_connection  ) ;
			
			if ($result===false)
				throw new Exception('error '.__FUNCTION__.'():: '.mysql_error($db_connection));
				
			if ($assoc) {
				while($row = mysql_fetch_assoc($result)) $data[] = $row;
			} else {
				while($row = mysql_fetch_object($result)) $data[] = $row;
			}
			mysql_free_result($result);
		}
		
		else
		{
			throw new Exception( __FUNCTION__.'(): direct db query, unsupported DB TYPE' );
		}

		return $data;
	}


	/**
	 * Build the order clause of item listings
	 * precedence: $request_var ==> $order ==> $config_param ==> $default_order_col (& $default_order_dir)
	 * @access private
	 * @return string
	 */
	static function buildItemOrderBy(&$params=null, &$order='', $request_var='orderby', $config_param='orderby', $i_as='i', $rel_as='rel', $default_order_col_1st='', $default_order_dir_1st='', $sfx='', $support_2nd_lvl=false)
	{
		// Use global params ordering if parameters were not given
		if (!$params) $params = JComponentHelper::getParams( 'com_flexicontent' );
		
		$order_fallback = 'rdate';  // Use as default or when an invalid ordering is requested
		$orderbycustomfield   = (int) $params->get('orderbycustomfield'.$sfx, 1);    // Backwards compatibility, defaults to enabled *
		$orderbycustomfieldid = (int) $params->get('orderbycustomfieldid'.$sfx, 0);  // * but this needs to be set in order for field ordering to be used
		
		// 1. If a FORCED -ORDER- is not given, then use ordering parameters from configuration. NOTE: custom field ordering takes priority
		if (!$order) {
			$order = ($orderbycustomfield && $orderbycustomfieldid)  ?  'field'  :  $params->get($config_param.$sfx, $order_fallback);
		}
		
		// 2. If allowing user ordering override, then get ordering from HTTP request variable
		$order = $params->get('orderby_override') && ($request_order = JRequest::getVar($request_var.$sfx)) ? $request_order : $order;
		
		// 3. Check various cases of invalid order, print warning, and reset ordering to default
		if ($order=='field' && !$orderbycustomfieldid ) {
			// This can occur only if field ordering was requested explicitly, otherwise an not set 'orderbycustomfieldid' will prevent 'field' ordering
			echo "Custom field ordering was selected, but no custom field is selected to be used for ordering<br/>";
			$order = $order_fallback;
		}
		if ($order=='commented') {
			if (!file_exists(JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'jcomments.php')) {
				echo "jcomments not installed, you need jcomments to use 'Most commented' ordering OR display comments information.<br/>\n";
				$order = $order_fallback;
			} 
		}
		
		$order_col_1st = $default_order_col_1st;
		$order_dir_1st = $default_order_dir_1st;
		flexicontent_db::_getOrderByClause($params, $order, $i_as, $rel_as, $order_col_1st, $order_dir_1st, $sfx);
		$order_arr[1] = $order;
		$orderby = ' ORDER BY '.$order_col_1st.' '.$order_dir_1st;
		
		
		// ****************************************************************
		// 2nd level ordering, (currently only supported when no SFX given)
		// ****************************************************************
		
		if ($sfx!='' || !$support_2nd_lvl) {
			$orderby .= $order_col_1st != $i_as.'.title'  ?  ', '.$i_as.'.title'  :  '';
			$order_arr[2] = '';
			$order = $order_arr;
			return $orderby;
		}
		
		$order = '';  // Clear this, thus force retrieval from parameters (below)
		$sfx='_2nd';  // Set suffix of second level ordering
		$order_fallback = 'alpha';  // Use as default or when an invalid ordering is requested
		$orderbycustomfield   = (int) $params->get('orderbycustomfield'.$sfx, 1);    // Backwards compatibility, defaults to enabled *
		$orderbycustomfieldid = (int) $params->get('orderbycustomfieldid'.$sfx, 0);  // * but this needs to be set in order for field ordering to be used
		
		// 1. If a FORCED -ORDER- is not given, then use ordering parameters from configuration. NOTE: custom field ordering takes priority
		if (!$order) {
			$order = ($orderbycustomfield && $orderbycustomfieldid)  ?  'field'  :  $params->get($config_param.$sfx, $order_fallback);
		}
		
		// 2. If allowing user ordering override, then get ordering from HTTP request variable
		$order = $request_var && ($request_order = JRequest::getVar($request_var.$sfx)) ? $request_order : $order;
		
		// 3. Check various cases of invalid order, print warning, and reset ordering to default
		if ($order=='field' && !$orderbycustomfieldid ) {
			// This can occur only if field ordering was requested explicitly, otherwise an not set 'orderbycustomfieldid' will prevent 'field' ordering
			echo "Custom field ordering was selected, but no custom field is selected to be used for ordering<br/>";
			$order = $order_fallback;
		}
		if ($order=='commented') {
			if (!file_exists(JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'jcomments.php')) {
				echo "jcomments not installed, you need jcomments to use 'Most commented' ordering OR display comments information.<br/>\n";
				$order = $order_fallback;
			} 
		}
		
		$order_col_2nd = '';
		$order_dir_2nd = '';
		if ($order!='default') {
			flexicontent_db::_getOrderByClause($params, $order, $i_as, $rel_as, $order_col_2nd, $order_dir_2nd, $sfx);
			$order_arr[2] = $order;
			$orderby .= ', '.$order_col_2nd.' '.$order_dir_2nd;
		}
		
		// Order by title after default ordering
		$orderby .= ($order_col_1st != $i_as.'.title' && $order_col_2nd != $i_as.'.title')  ?  ', '.$i_as.'.title'  :  '';
		$order = $order_arr;
		return $orderby;
	}
	
	
	// Create order clause sub-parts
	static function _getOrderByClause(&$params, &$order='', $i_as='i', $rel_as='rel', &$order_col='', &$order_dir='', $sfx='')
	{
		// 'order' contains a symbolic order name to indicate using the category / global ordering setting
		switch ($order) {
			case 'date': case 'addedrev': /* 2nd is for module (PARAMETER FORM ELEMENT: fcordering) */
				$order_col	= $i_as.'.created';
				$order_dir	= 'ASC';
				break;
			case 'rdate': case 'added': /* 2nd is for module (PARAMETER FORM ELEMENT: fcordering) */
				$order_col	= $i_as.'.created';
				$order_dir	= 'DESC';
				break;
			case 'modified': case 'updated': /* 2nd is for module (PARAMETER FORM ELEMENT: fcordering) */
				$order_col	= $i_as.'.modified';
				$order_dir	= 'DESC';
				break;
			case 'published':
				$order_col	= $i_as.'.publish_up';
				$order_dir	= 'DESC';
				break;
			case 'published_oldest':
				$order_col	= $i_as.'.publish_up';
				$order_dir	= 'ASC';
				break;
			case 'expired':
				$order_col	= $i_as.'.publish_down';
				$order_dir	= 'DESC';
				break;
			case 'expired_oldest':
				$order_col	= $i_as.'.publish_down';
				$order_dir	= 'ASC';
				break;
			case 'alpha':
				$order_col	= $i_as.'.title';
				$order_dir	= 'ASC';
				break;
			case 'ralpha': case 'alpharev': /* 2nd is for module (PARAMETER FORM ELEMENT: fcordering) */
				$order_col	= $i_as.'.title';
				$order_dir	= 'DESC';
				break;
			case 'author':
				$order_col	= 'u.name';
				$order_dir	= 'ASC';
				break;
			case 'rauthor':
				$order_col	= 'u.name';
				$order_dir	= 'DESC';
				break;
			case 'hits': case 'popular': /* 2nd is for module (PARAMETER FORM ELEMENT: fcordering) */
				$order_col	= $i_as.'.hits';
				$order_dir	= 'DESC';
				break;
			case 'rhits':
				$order_col	= $i_as.'.hits';
				$order_dir	= 'ASC';
				break;
			case 'order': case 'catorder': /* 2nd is for module (PARAMETER FORM ELEMENT: fcordering) */
				$order_col	= $rel_as.'.catid, '.$rel_as.'.ordering ASC, '.$i_as.'.id DESC';
				$order_dir	= '';
				break;

			// SPECIAL case custom field
			case 'field':
				$cf = $sfx == '_2nd' ? 'f2' : 'f';
				$order_type = $params->get('orderbycustomfieldint'.$sfx, 0);
				switch( $order_type )
				{
					case 1:  $order_col = 'CAST('.$cf.'.value AS SIGNED)';  break;  // Integer
					case 2:  $order_col = 'CAST('.$cf.'.value AS DECIMAL(65,15))'; break; // Decimal
					case 3:  $order_col = 'CAST('.$cf.'.value AS DATE)';  break;  // Date
					case 4:  $order_col = ($sfx == '_2nd' ? 'file_hits2' : 'file_hits'); break;  // Download hits
					default: $order_col = $cf.'.value'; break;  // Text
				}
				$order_dir = $params->get('orderbycustomfielddir'.$sfx, 'ASC');
				if ($order_type != 4)
				{
					$order_col = 'ISNULL('.$cf.'.value), ' . $order_col;
				}
				break;

			// NEW ADDED
			case 'random':
				$order_col	= 'RAND()';
				$order_dir	= '';
				break;
			case 'commented':
				$order_col	= 'comments_total';
				$order_dir	= 'DESC';
				break;
			case 'rated':
				$order_col	= 'votes';
				$order_dir	= 'DESC';
				break;
			case 'id':
				$order_col	= $i_as.'.id';
				$order_dir	= 'DESC';
				break;
			case 'rid':
				$order_col	= $i_as.'.id';
				$order_dir	= 'ASC';
				break;
			case 'alias':
				$order_col	= $i_as.'.alias';
				$order_dir	= 'ASC';
				break;
			case 'ralias':
				$order_col	= $i_as.'.alias';
				$order_dir	= 'DESC';
				break;

			case 'default':
			default:
				if (substr($order, 0, 7)=='custom:') {
					$order_parts = preg_split("/:/", $order);
					$_field_id = (int) @ $order_parts[1];
				}
				if (!empty($_field_id) && count($order_parts)==4) {
					$cf = $sfx == '_2nd' ? 'f2' : 'f';
					$order_type = strtolower($order_parts[2]);
					switch( $order_type )
					{
						case 'int':       $order_col = 'CAST('.$cf.'.value AS SIGNED)';  break;
						case 'decimal':   $order_col = 'CAST('.$cf.'.value AS DECIMAL(65,15))'; break;
						case 'date':      $order_col = 'CAST('.$cf.'.value AS DATE)'; break;
						case 'file_hits': $order_col = ($sfx == '_2nd' ? 'file_hits2' : 'file_hits'); break;  // Download hits
						default:          $order_col = $cf.'.value'; break;
					}
					$order_dir = strtolower($order_parts[3])=='desc' ? 'DESC' : 'ASC';
					if ($order_type != 'file_hits')
					{
						$order_col = 'ISNULL('.$cf.'.value), ' . $order_col;
					}
				} else {
					$order_col	= $order_col ? $order_col : $i_as.'.title';
					$order_dir	= $order_dir ? $order_dir : 'ASC';
				}
				break;
		}
		//echo "<br/>".$order_col." ".$order_dir."<br/>";
	}


	/**
	 * Build the order clause of category listings
	 *
	 * @access private
	 * @return string
	 */
	static function buildCatOrderBy(&$params, $order='', $request_var='', $config_param='cat_orderby', $c_as='c', $u_as='u', $default_order_col='', $default_order_dir='')
	{
		// Use global params ordering if parameters were not given
		if (!$params) $params = JComponentHelper::getParams( 'com_flexicontent' );

		// 1. If forced ordering not given, then use ordering parameters from configuration
		if (!$order) {
			$order = $params->get($config_param, 'default');
		}

		// 2. If allowing user ordering override, then get ordering from HTTP request variable
		$order = $request_var && ($request_order = JRequest::getVar($request_var.$sfx)) ? $request_order : $order;

		switch ($order) {
			case 'date':
				$order_col = $c_as.'.created_time';
				$order_dir = 'ASC';
				break;
			case 'rdate':
				$order_col = $c_as.'.created_time';
				$order_dir = 'DESC';
				break;
			case 'modified':
				$order_col = $c_as.'.modified_time';
				$order_dir = 'DESC';
				break;
			case 'alpha':
				$order_col = $c_as.'.title';
				$order_dir = 'ASC';
				break;
			case 'ralpha':
				$order_col = $c_as.'.title';
				$order_dir = 'DESC';
				break;
			case 'author':
				$order_col = $u_as.'.name';
				$order_dir = 'ASC';
				break;
			case 'rauthor':
				$order_col = $u_as.'.name';
				$order_dir = 'DESC';
				break;
			case 'hits':
				$order_col = $c_as.'.hits';
				$order_dir = 'DESC';
				break;
			case 'rhits':
				$order_col = $c_as.'.hits';
				$order_dir = 'ASC';
				break;
			case 'order':
				$order_col = $c_as.'.lft';
				$order_dir = 'ASC';
				break;
			case 'random':
				$order_col	= 'RAND()';
				$order_dir	= '';
				break;
			case 'default' :
			default:
				$order_col = $default_order_col ? $default_order_col : $i_as.'.title';
				$order_dir = $default_order_dir ? $default_order_dir : 'ASC';
				break;
		}

		$orderby 	= ' ORDER BY '.$order_col.' '.$order_dir;
		$orderby .= $order_col!=$c_as.'.title' ? ', '.$c_as.'.title' : '';   // Order by title after default ordering

		return $orderby;
	}


	/**
	 * Check in a record
	 *
	 * @since	1.5
	 */
	static function checkin($tbl, $redirect_url, & $controller)
	{
		$cid  = JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$user = JFactory::getUser();
		$controller->setRedirect( $redirect_url, '' );

		static $canCheckinRecords = null;
		if ($canCheckinRecords === null) {
			$canCheckinRecords = $user->authorise('core.admin', 'checkin');
		}

		// Only attempt to check the row in if it exists.
		$checked_in = 0;
		$diff_user = array();
		$other_err = array();
		foreach($cid as $pk)
		{
			if (!$pk) continue;
			
			// Get an instance of the row to checkin.
			$table = JTable::getInstance($tbl, '');
			if (!$table->load($pk))
			{
				$other_err .= 'ID: '.$pk. ': '.$table->getError();  //$controller->setError($table->getError());  //return; // false;
				continue;
			}

			// Record check-in is allowed if either (a) current user has Global Checkin privilege OR (b) record checked out by current user
			if (!$table->checked_out) continue;
			
			if ( !$canCheckinRecords && $table->checked_out != $user->id )
			{
				$diff_user[] = $pk;  //$controller->setError(JText::_( 'FLEXI_RECORD_CHECKED_OUT_DIFF_USER'));  //return; // false;
				continue;
			}
			
			// Attempt to check the row in.
			if ( !$table->checkin($pk) )
			{
				if (count($other_err) < 3)  $other_err[] = 'ID: '.$pk. ': '.$table->getError();  //$controller->setError($table->getError());  //return; // false;
				continue;
			}
			$checked_in++;
		}
		
		$msg = JText::sprintf('FLEXI_RECORD_CHECKED_IN_SUCCESSFULLY', $checked_in);
		if (count($diff_user))  $msg .= '<br/><br/>IDs: '.implode(', ', $diff_user).' -- '.JText::_( 'FLEXI_RECORD_CHECKED_OUT_DIFF_USER');
		if (count($other_err))  $msg .= '<br/><br/>'.implode('<br/> ', $other_err);
		
		$controller->setRedirect( $redirect_url, $msg, ($other_err ? 'error' : 'message') );
		return;// true;
	}
	
	
	/**
	 * Return field types grouped or not
	 *
	 * @return array
	 * @since 1.5
	 */
	static function getFieldTypes($group=false, $usage=false, $published=false)
	{
		$db = JFactory::getDBO();
		$query = 'SELECT plg.element AS field_type, plg.name as title'
			.($usage ? ', count(f.id) as assigned' : '')
			.' FROM #__extensions AS plg'
			.($usage ? ' LEFT JOIN #__flexicontent_fields AS f ON (plg.element = f.field_type AND f.iscore=0)' : '')
			.' WHERE '.($published ? 'plg.enabled=1' : '1')
			.'  AND plg.`type` = ' . $db->Quote('plugin')
			.'  AND plg.`folder` = ' . $db->Quote('flexicontent_fields')
			.'  AND plg.`element` <> ' . $db->Quote('core')
			.($usage ? ' GROUP BY plg.element' : '')
			.' ORDER BY title ASC'
			;
		
		$db->setQuery($query);
		$field_types = $db->loadObjectList('field_type');
		
		foreach($field_types as $field_type) {
			$field_type->friendly = preg_replace("/FLEXIcontent[ \t]*-[ \t]*/i", "", $field_type->title);
		}
		if (!$group) return $field_types;
		
		$grps = array(
			JText::_('FLEXI_SELECTION_FIELDS')          => array('radio', 'radioimage', 'checkbox', 'checkboximage', 'select', 'selectmultiple'),
			JText::_('FLEXI_SINGLE_PROP_FIELDS')        => array('date', 'text', 'textarea', 'textselect'),
			JText::_('FLEXI_MULTIPLE_PROP_FIELDS')      => array('weblink', 'email', 'extendedweblink', 'phonenumbers', 'termlist'),
			JText::_('FLEXI_MEDIA_MINI_APPS_FIELDS')    => array('file', 'image', 'minigallery', 'sharedmedia', 'addressint'),
			JText::_('FLEXI_ITEM_FORM_FIELDS')          => array('fieldgroup', 'account_via_submit', 'groupmarker', 'coreprops'),
			JText::_('FLEXI_DISPLAY_MANAGEMENT_FIELDS') => array('toolbar', 'fcloadmodule', 'fcpagenav', 'linkslist', 'authoritems', 'jprofile'),
			JText::_('FLEXI_ITEM_RELATION_FIELDS')      => array('relation', 'relation_reverse', 'autorelationfilters')
		);
		foreach($grps as $grpname => $field_type_arr)
		{
			$field_types_grp[$grpname] = array();
			foreach($field_type_arr as $field_type)
			{
				if ( !empty($field_types[$field_type]) ) {
					$field_types_grp[$grpname][$field_type] = $field_types[$field_type];
				}
				unset($field_types[$field_type]);
			}
		}
		// Remaining fields
		$field_types_grp['3rd-Party / Other Fields'] = $field_types;
		
		return $field_types_grp;
	}
	
	
	/**
	 * Method to get data/parameters of thie given or all types
	 *
	 * @access public
	 * @return object
	 */
	static function getTypeData($contenttypes_list=false)
	{
		static $cached = null;
		if ( isset($cached[$contenttypes_list]) ) return $cached[$contenttypes_list];
		
		// Retrieve item's Content Type parameters
		$db = JFactory::getDBO();
		$query = 'SELECT * '
				. ' FROM #__flexicontent_types AS t'
				. ($contenttypes_list ? ' WHERE id IN('.$contenttypes_list.')' : '')
				;
		$db->setQuery($query);
		$types = $db->loadObjectList('id');
		foreach ($types as $type) $type->params = new JRegistry($type->attribs);
		
		$cached[$contenttypes_list] = $types;
		return $types;
	}
	
	
	static function getOriginalContentItemids($_items, $ids=null)
	{
		if (empty($ids) && empty($_items)) return array();
		
		if (is_array($_items))
			$items = & $_items;
		else
			$items = array( & $_items );
		
		if (empty($ids))
		{
			$ids = array();
			foreach($items as $item) $ids[] = $item->id;
		}
		
		// Get associated translations
		$db = JFactory::getDBO();
		$query = 'SELECT a.id as id, k.id as original_id'
			. ' FROM #__associations AS a'
			. ' JOIN #__associations AS k ON a.`key`=k.`key`'
			. ' JOIN #__content AS i ON i.id = k.id AND i.language = '. $db->Quote(flexicontent_html::getSiteDefaultLang())
			. ' WHERE a.id IN ('. implode(',', $ids) .') AND a.context = "com_content.item"';
		$db->setQuery($query);
		$assoc_keys = $db->loadObjectList('id');
		
		if (!empty($items))
		{
			foreach($items as $item) $item->lang_parent_id = isset($assoc_keys[$item->id]) ? $assoc_keys[$item->id]->original_id : $item->id;
		}
		else
			return $assoc_keys;
	}
	
		
	static function getLangAssocs($ids)
	{
		$db = JFactory::getDBO();
		$query = 'SELECT a.id as item_id, i.id as id, i.title, i.created, i.modified, i.language as language, i.language as lang'
			. ' FROM #__associations AS a'
			. ' JOIN #__associations AS k ON a.`key`=k.`key`'
			. ' JOIN #__content AS i ON i.id = k.id'
			. ' WHERE a.id IN ('. implode(',', $ids) .') AND a.context = "com_content.item"';
		$db->setQuery($query);
		$associations = $db->loadObjectList();
		
		$translations = array();
		foreach ($associations as $assoc)
		{
			$translations[$assoc->item_id][] = $assoc;
		}
		
		return $translations;
	}

	/**
	 * Method to save language associations
	 *
	 * @return  boolean True if successful
	 */
	static function saveAssociations(&$item, &$data, $context)
	{
		$assoc = flexicontent_db::useAssociations();
		if (!$assoc) return true;
		
		
		// **********************************
		// Prepare / check associations array
		// **********************************
		
		// Unset empty associations from associations array, to avoid save them in the associations table
		$associations = isset($data['associations']) ? $data['associations'] : array();
		foreach ($associations as $tag => $id)
		{
			if (empty($id)) unset($associations[$tag]);
		}
		
		// Raise notice that associations should be empty if language of current item is '*' (ALL)
		$all_language = $item->language == '*';
		if ($all_language && !empty($associations))
		{
			JError::raiseNotice(403, JText::_('FLEXI_ERROR_ALL_LANGUAGE_ASSOCIATED'));
		}
		
		// Make sure that current item id, is the association id of the language of the current item
		$associations[$item->language] = $item->id;
		
		// Make sure associations ids are integers
		JArrayHelper::toInteger($associations);
		
		
		// ***********************
		// Delete old associations
		// ***********************
		
		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
			->delete('#__associations')
			->where($db->quoteName('context') . ' = ' . $db->quote($context.'.item'))
			->where($db->quoteName('id') . ' IN (' . implode(',', $associations) . ')');
		$db->setQuery($query);
		$db->execute();
		
		if ($error = $db->getErrorMsg())
		{
			$this->setError($error);
			return false;
		}
		
		
		// ********************
		// Add new associations
		// ********************
		
		// Only add language associations if item language is not '*' (ALL)
		if ($all_language || !count($associations)) return true;
		
		$key = md5(json_encode($associations));
		$query->clear()
			->insert('#__associations');
		
		foreach ($associations as $id)
		{
			$query->values($id . ',' . $db->quote($context.'.item') . ',' . $db->quote($key));
		}
		
		$db->setQuery($query);
		$db->execute();

		if ($error = $db->getErrorMsg())
		{
			$this->setError($error);
			return false;
		}
		return true;
	}
	
	
	/**
	 * Method to determine if J3.1+ associations should be used
	 *
	 * @return  boolean True if using J3 associations; false otherwise.
	 */
	static function useAssociations()
	{
		static $assoc = null;

		if (!is_null($assoc))
		{
			return $assoc;
		}

		$app = JFactory::getApplication();

		$assoc = FLEXI_J30GE && JLanguageAssociations::isEnabled();
		$component = 'com_flexicontent';
		$cname = str_replace('com_', '', $component);
		$j3x_assocs = true;
		
		if (!$assoc || !$component || !$cname || !$j3x_assocs)
		{
			$assoc = false;
		}
		else
		{
			$hname = $cname . 'HelperAssociation';
			JLoader::register($hname, JPATH_SITE . '/components/' . $component . '/helpers/association.php');

			$assoc = class_exists($hname) && !empty($hname::$category_association);
		}
		
		return $assoc;
	}
}


function FLEXISubmenu($cando)
{
	$perms   = FlexicontentHelperPerm::getPerm();
	$app     = JFactory::getApplication();
	$session = JFactory::getSession();
	$cparams = JComponentHelper::getParams( 'com_flexicontent' );
	
	// Check access to current management tab
	$not_authorized = isset($perms->$cando) && !$perms->$cando;
	if ( $not_authorized ) {
		$app->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
	}
	
	// Get post-installation FLAG (session variable), and current view (HTTP request variable)
	$dopostinstall = $session->get('flexicontent.postinstall');
	$view = JRequest::getVar('view', 'flexicontent');
	
	// Create Submenu, Dashboard (HOME is always added, other will appear only if post-installation tasks are done)
	$addEntry = array(FLEXI_J30GE ? 'JHtmlSidebar' : 'JSubMenuHelper', 'addEntry');
	
	call_user_func($addEntry, '<h2 class="fcsbnav-content-editing">'.JText::_( 'FLEXI_NAV_SD_CONTENT_EDITING' ).'</h2>', '', '');
	call_user_func($addEntry, '<span class="fcsb-icon-flexicontent"></span>'.JText::_( 'FLEXI_HOME' ), 'index.php?option=com_flexicontent', !$view || $view=='flexicontent');
	if ($dopostinstall && version_compare(PHP_VERSION, '5.0.0', '>'))
	{
		call_user_func($addEntry, '<span class="fcsb-icon-items"></span>'.JText::_( 'FLEXI_ITEMS' ), 'index.php?option=com_flexicontent&view=items', $view=='items');
		if ($perms->CanCats) 			call_user_func($addEntry, '<span class="fcsb-icon-fc_categories"></span>'.JText::_( 'FLEXI_CATEGORIES' ), 'index.php?option=com_flexicontent&view=categories', $view=='categories');
		if ($cparams->get('comments')==1 && $perms->CanComments) call_user_func($addEntry,
			'<a href="index.php?option=com_jcomments&task=view&fog=com_flexicontent" onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\'); return false;">'.
				'<span class="fcsb-icon-comments"></span>'.JText::_( 'FLEXI_COMMENTS' ).
			'</a>', '', false);
		else if ($cparams->get('comments')==1 && !$perms->JComments_Installed) call_user_func($addEntry, '<span class="fcsb-icon-comments disabled"></span><span class="fc_sidebar_entry disabled">'.JText::_( 'FLEXI_JCOMMENTS_MISSING' ).'</span>', '', false);
		
		if ($perms->CanReviews)		call_user_func($addEntry, '<span class="fcsb-icon-reviews"></span>'.JText::_( 'FLEXI_REVIEWS' ), 'index.php?option=com_flexicontent&view=reviews', $view=='reviews');
		
		call_user_func($addEntry, '<h2 class="fcsbnav-type-fields">'.JText::_( 'FLEXI_NAV_SD_TYPES_N_FIELDS' ).'</h2>', '', '');
		if ($perms->CanTypes)			call_user_func($addEntry, '<span class="fcsb-icon-types"></span>'.JText::_( 'FLEXI_TYPES' ), 'index.php?option=com_flexicontent&view=types', $view=='types');
		if ($perms->CanFields) 		call_user_func($addEntry, '<span class="fcsb-icon-fields"></span>'.JText::_( 'FLEXI_FIELDS' ), 'index.php?option=com_flexicontent&view=fields', $view=='fields');
		if ($perms->CanTags) 			call_user_func($addEntry, '<span class="fcsb-icon-tags"></span>'.JText::_( 'FLEXI_TAGS' ), 'index.php?option=com_flexicontent&view=tags', $view=='tags');
		if ($perms->CanFiles) 		call_user_func($addEntry, '<span class="fcsb-icon-filemanager"></span>'.JText::_( 'FLEXI_FILEMANAGER' ), 'index.php?option=com_flexicontent&view=filemanager', $view=='filemanager');
		
		call_user_func($addEntry, '<h2 class="fcsbnav-content-viewing">'.JText::_( 'FLEXI_NAV_SD_CONTENT_VIEWING' ).'</h2>', '', '');
		if ($perms->CanTemplates)	call_user_func($addEntry, '<span class="fcsb-icon-templates"></span>'.JText::_( 'FLEXI_TEMPLATES' ), 'index.php?option=com_flexicontent&view=templates', $view=='templates');
		if ($perms->CanIndex)			call_user_func($addEntry, '<span class="fcsb-icon-search"></span>'.JText::_( 'FLEXI_SEARCH_INDEXES' ), 'index.php?option=com_flexicontent&view=search', $view=='search');
		if ($perms->CanStats)			call_user_func($addEntry, '<span class="fcsb-icon-stats"></span>'.JText::_( 'FLEXI_STATISTICS' ), 'index.php?option=com_flexicontent&view=stats', $view=='stats');
		
		call_user_func($addEntry, '<h2 class="fcsbnav-users">'.JText::_( 'FLEXI_NAV_SD_USERS_N_GROUPS' ).'</h2>', '', '');
		if ($perms->CanAuthors)		call_user_func($addEntry, '<span class="fcsb-icon-users"></span>'.JText::_( 'FLEXI_USERS' ), 'index.php?option=com_flexicontent&view=users', $view=='users');
		if ($perms->CanGroups)		call_user_func($addEntry, '<span class="fcsb-icon-groups"></span>'.JText::_( 'FLEXI_GROUPS' ), 'index.php?option=com_flexicontent&view=groups', $view=='groups');
	//if ($perms->CanArchives)	call_user_func($addEntry, '<span class="fcsb-icon-archive"></span>'.JText::_( 'FLEXI_ARCHIVE' ), 'index.php?option=com_flexicontent&view=archive', $view=='archive');
	
		call_user_func($addEntry, '<h2 class="fcsbnav-expert">'.JText::_( 'FLEXI_NAV_SD_EXPERT_USAGE' ).'</h2>', '', '');
		$appsman_path = JPATH_COMPONENT_ADMINISTRATOR.DS.'views'.DS.'appsman';
		if (file_exists($appsman_path)) {
			if ($perms->CanConfig)	call_user_func($addEntry, '<span class="fcsb-icon-wrench"></span>'.JText::_( 'FLEXI_WEBSITE_APPS_IMPORT_EXPORT' ), 'index.php?option=com_flexicontent&view=appsman', $view=='appsman');
		}
		if ($perms->CanImport)		call_user_func($addEntry, '<span class="fcsb-icon-import"></span>'.JText::_( 'FLEXI_CONTENT_IMPORT' ), 'index.php?option=com_flexicontent&view=import', $view=='import');
		if ($perms->CanPlugins) call_user_func($addEntry,
			'<a href="index.php?option=com_plugins" onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\'); return false;" >'.
				'<span class="fcsb-icon-plugins"></span>'.JText::_( 'FLEXI_PLUGINS' ).
			'</a>', '', false);
	}
}


class flexicontent_zip extends ZipArchive
{
	/**
	 * Add a directory with files and subdirectories to the archive
	 *
	 * @param string $location Full (real) pathname
	 * @param string $name Name in Archive
	 **/
	public function addDir($pathname, $name)
	{
		$this->addEmptyDir($name);
		$this->addDirDo($pathname, $name);
	}

	/**
	 * Add files & directories to archive
	 *
	 * @param string $location Full (real) pathname
	 * @param string $name Name in Archive
	 **/
	private function addDirDo($pathname, $name)
	{
		if ($name) $name .= '/';
		$pathname .= '/';

		// Read all Files in Dir
		$dir = opendir ($pathname);
		while ($file = readdir($dir))
		{
			if ($file == '.' || $file == '..') continue;

			// Rekursiv, If dir: FlxZipArchive::addDir(), else ::File();
			$do = (filetype( $pathname . $file) == 'dir') ? 'addDir' : 'addFile';
			$this->$do($pathname . $file, $name . $file);
		}
	}
}


class flexicontent_ajax
{
	static function call_extfunc()
	{
		$exttype = JRequest::getVar( 'exttype', 'modules' );
		$extname = JRequest::getVar( 'extname', '' );
		$extfunc = JRequest::getVar( 'extfunc', '' );
		$extfolder = JRequest::getVar( 'extfolder', '' );
		
		if ($exttype!='modules' && $exttype!='plugins') { echo 'only modules and plugins are supported'; jexit(); }  // currently supporting only module and plugins
		if (!$extname || !$extfunc) { echo 'function or extension name not set'; jexit(); }  // require variable not set
		if ($exttype=='plugins' && $extfolder=='') { echo 'plugin folder is not set'; jexit(); }  // currently supporting only module and plugins		
		
		if ($exttype=='modules') {
			// Import module helper file
			$helper_path = JPATH_SITE.DS.$exttype.DS.'mod_'.$extname.DS.'helper.php';
			if ( !file_exists($helper_path) ) { echo "no helper file found at expected path, filepath is ".$helper_path; jexit(); }
			require_once ($helper_path);
			
			// Create object
			$classname = 'mod'.ucwords($extname).'Helper';
			if ( !class_exists($classname) ) { echo "no correctly named class inside helper file"; jexit(); }
			$obj = new $classname();
		}
		
		else {  // exttype is 'plugins'
			// Load Flexicontent Field (the Plugin file) if not already loaded
			$plgfolder = DS.strtolower($extname);
			$path = JPATH_ROOT.DS.'plugins'.DS.$extfolder.$plgfolder.DS.strtolower($extname).'.php';
			if ( !file_exists($path) ) { echo "no plugin file found at expected path, filepath is ".$path; jexit(); }
			require_once ($path);
			
			// Create class name of the plugin
			$classname = 'plg'. ucfirst($extfolder).$extname;
			if ( !class_exists($classname) ) { echo "no correctly named class inside plugin file"; jexit(); }
			
			// Create a plugin instance
			$dispatcher = JDispatcher::getInstance();
			$obj = new $classname($dispatcher, array());
			
			// Assign plugin parameters, (most FLEXI plugins do not have plugin parameters), CHECKING if parameters exist
			$plugin_db_data = JPluginHelper::getPlugin($extfolder,$extname);
			$obj->params = new JRegistry( @ $plugin_db_data->params );
		}
		
		// Security concern, only 'confirmed' methods will be callable
		if ( !in_array($extfunc, $obj->task_callable) ) { echo "non-allowed method called"; jexit(); }
		
		// Method actually exists
		if ( !method_exists($obj, $extfunc) ) { echo "non-existing method called "; jexit(); }
		
		// Load extension's english language file then override with current language file
		if ($exttype=='modules')
			$extension_name = 'mod_'.strtolower($extname);
		else
			$extension_name = 'plg_'.strtolower($extname);
		JFactory::getLanguage()->load($extension_name, JPATH_SITE, 'en-GB', true);
		JFactory::getLanguage()->load($extension_name, JPATH_SITE, null, true);
		
		// Call the method
		$obj->$extfunc();
	}
}