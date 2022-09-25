<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

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
		$db = JFactory::getDbo();
		static $langs_cache = array();
		//static $pub_languages = array();
		//static $all_languages = array();

		if (isset($langs_cache[$published_only][$add_all]))
		{
			return $langs_cache[$published_only][$add_all];
		}


		/**
		 * Retrieve languages
		 */

		$query = $db->getQuery(true)
			->select('DISTINCT lc.lang_id as id, lc.image as image_prefix, lc.lang_code as code, lc.title_native, lc.sef')
			//->select('CASE WHEN CHAR_LENGTH(lc.title_native) THEN CONCAT(lc.title, " (", lc.title_native, ")") ELSE lc.title END as name')
			->select('lc.title as name')
			->from('#__languages as lc')
			->where($published_only ? 'lc.published = 1' : '1')
			->order('lc.ordering ASC')
			;

		$languages = $db->setQuery($query)->loadObjectList('id');
		//echo "<pre>"; print_r($languages); echo "</pre>"; exit;


		// *********************
		// Calculate image paths
		// *********************

		$imgpath	= $app->isClient('administrator') ? '../images/':'images/';
		$mediapath	= $app->isClient('administrator') ? '../media/mod_languages/images/' : 'media/mod_languages/images/';


		// ************************
		// Prepare language objects
		// ************************

		$_languages = array();

		// Add 'ALL' option
		if ($add_all)
		{
			$lang_all = new stdClass();
			$lang_all->code = '*';
			$lang_all->name = JText::_('FLEXI_ALL');
			$lang_all->shortcode = '*';
			$lang_all->id = 0;
			$_languages = array( 0 => $lang_all);
		}

		// Check if no languages found, set cache and return
		if (empty($languages))
		{
			$langs_cache[$published_only][$add_all] = $_languages;
			return $_languages;
		}

		foreach ($languages as $lang)
		{
			// Calculate/Fix languages data
			$lang->shortcode = strpos($lang->code,'-')
				? substr($lang->code, 0, strpos($lang->code,'-'))
				: $lang->code;

			//$lang->id = $lang->extension_id;
			$image_prefix = $lang->image_prefix ? $lang->image_prefix : $lang->shortcode;

			// $lang->image, holds a custom image path
			$lang->imgsrc = @$lang->image
				? $imgpath . $lang->image
				: $mediapath . $image_prefix . '.gif';

			$_languages[$lang->id] = $lang;
		}

		// Set cache and return
		$langs_cache[$published_only][$add_all] = $_languages;
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
			$db = JFactory::getDbo();
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
			$db = JFactory::getDbo();
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
		$db = JFactory::getDbo();
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
		static $status = null;

		if ($status === null)
		{
			$db = JFactory::getDbo();
			$query = "SELECT c.id,c.version,iv.version as iversion FROM #__content as c "
				." LEFT JOIN #__flexicontent_items_versions as iv ON c.id=iv.item_id AND c.version=iv.version"
				." JOIN #__categories as cat ON c.catid=cat.id"
				." WHERE c.version > '1' AND iv.version IS NULL"
				.(!FLEXI_J16GE ? " AND sectionid='".FLEXI_SECTION."'" : " AND cat.extension='".FLEXI_CAT_EXTENSION."'")
				." LIMIT 0,1";
			$db->setQuery($query);
			$rows = $db->loadObjectList("id");

			$status = !empty($rows);
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
		$db = JFactory::getDbo();
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
		$db = JFactory::getDbo();
		$query = 'SELECT COUNT(*)'
				.' FROM #__flexicontent_versions'
				.' WHERE item_id = ' . (int)$id
				;
		$db->setQuery($query);
		$versionscount = $db->loadResult();

		return $versionscount;
	}


	static function getCache($group='', $client=0)
	{
		$conf = JFactory::getConfig();

		//$client = 0;//0 is site, 1 is admin
		$options = array(
			'defaultgroup' => $group,
			'storage' => $conf->get('cache_handler', ''),
			'caching' => true,
			'cachebase' => ($client == 1 ? JPATH_ADMINISTRATOR . '/cache' : $conf->get('cache_path', JPATH_SITE . '/cache'))
		);

		jimport('joomla.cache.cache');
		$cache = JCache::getInstance('', $options);

		return $cache;
	}


	static function call_FC_Field_Func( $fieldtype, $func, $args=null )
	{
		static $fc_plgs;
		$className = 'plgFlexicontent_fields'.$fieldtype;

		if ( !isset( $fc_plgs[$fieldtype] ) )
		{
			// 1. Load Flexicontent Field (the Plugin file) if not already loaded
			$plgfolder = DS.strtolower($fieldtype);
			$path = JPATH_ROOT.DS.'plugins'.DS.'flexicontent_fields'.$plgfolder.DS.strtolower($fieldtype).'.php';
			if (!file_exists($path))
			{
				$func
					? JFactory::getApplication()->enqueueMessage(nl2br("While calling field method: $func(): cann't find field type: $fieldtype. This is internal error or wrong field name"), 'error')
					: JFactory::getApplication()->enqueueMessage(nl2br("Field of type: <b>'$fieldtype'</b> seems to have been uninstalled"), 'notice');

				return false;
			}
			require_once($path);

			if (!class_exists($className))
			{
				JFactory::getApplication()->enqueueMessage(nl2br("Could not find class: $className in file: $path\n Please correct field name"), 'error');
				return false;
			}

			// 2. Create a plugin instance, also pass the parameters so that $this->params are created too
			$dispatcher  = JEventDispatcher::getInstance();
			$plg_db_data = JPluginHelper::getPlugin('flexicontent_fields', $fieldtype);
			$fc_plgs[$fieldtype] = new $className($dispatcher, array('type'=>'flexicontent_fields', 'name'=>$fieldtype, 'params'=>$plg_db_data->params));
		}

		// 3. Execute only if it exists
		if(in_array($func, get_class_methods($className)))
		{
			return call_user_func_array(array($fc_plgs[$fieldtype], $func), $args);
		}
	}


	/* !!! FUNCTION NOT DONE YET */
	static function call_Content_Plg_Func( $plgname, $func, $args=null )
	{
		static $content_plgs;
		$className = 'plgContent'.$plgname;

		if ( !isset( $content_plgs[$plgname] ) )
		{
			// 1. Load Flexicontent Field (the Plugin file) if not already loaded
			$plgfolder = DS.strtolower($plgname);
			$path = JPATH_ROOT.DS.'plugins'.DS.'content'.$plgfolder.DS.strtolower($plgname).'.php';
			if (!file_exists($path))
			{
				JFactory::getApplication()->enqueueMessage(nl2br("Cannot load CONTENT Plugin: $plgname\n Plugin may have been uninistalled"), 'error');
				return;
			}

			require_once($path);

			if(!class_exists($className))
			{
				JFactory::getApplication()->enqueueMessage(nl2br("Could not find class: $className in file: $path\n Please correct plugin name"), 'error');
				return;
			}

			// 2. Create a plugin instance, also pass the parameters so that $this->params are created too
			$dispatcher  = JEventDispatcher::getInstance();
			$plg_db_data = JPluginHelper::getPlugin('content', $plgname);
			$content_plgs[$plgname] = new $className($dispatcher, array('type'=>'content', 'name'=>$plgname, 'params'=>$plg_db_data->params));
		}

		// 3. Execute function only if it exists
		if(in_array($func, get_class_methods($className)))
		{
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
		$h = ord($c[0]);
		if ($h <= 0x7F) {
			return $h;
		} else if ($h < 0xC2) {
			return false;
		} else if ($h <= 0xDF) {
			return ($h & 0x1F) << 6 | (ord($c[1]) & 0x3F);
		} else if ($h <= 0xEF) {
			return ($h & 0x0F) << 12 | (ord($c[1]) & 0x3F) << 6
			| (ord($c[2]) & 0x3F);
		} else if ($h <= 0xF4) {
			return ($h & 0x0F) << 18 | (ord($c[1]) & 0x3F) << 12
			| (ord($c[2]) & 0x3F) << 6
			| (ord($c[3]) & 0x3F);
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
		$db = JFactory::getDbo();
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
		$fld_sep_start = $field_separator[0];
		$fld_sep_size  = strlen( $field_separator );
		// Record (item) separator
		$rec_sep_start = $record_separator[0];
		$rec_sep_size  = strlen( $record_separator );

		for($i=0; $i<$size;$i++)
		{
			$char = $string[$i];
			$addChar = "";

			if($isEnclosured) {
				if($char==$enclosure_char) {
					if($i+1<$size && $string[$i+1]==$enclosure_char) {
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
			$value = trim($value ?? '');
			$value = !$value  ?  array()  :  preg_split($regex, $value);
		}
		if ($filterfunc) {
			$value = array_map($filterfunc, $value);
		}

		if (!is_array($value)) {
			$value = explode("|", $value);
			$value = ($value[0]=='') ? array() : $value;
		} else {
			$value = !is_array($value) ? array($value) : $value;
		}

		if ($remove_save_flag)
		{
			foreach($value as $i =>$v)
			{
				if ($v === '__SAVED__')
				{
					unset($value[$i]);
				}
			}
		}
		return $value;
	}

	/**
	 * Suppresses given plugins (= prevents them from triggering)
	 * USELESS no longer working
	 *
	 * DEPRECATED to be removed in 3.3.x
	 */
	static function suppressPlugins($name_arr, $action)
	{
	}


	/**
	 * Creates the side menu for backend managers
	 *
	 * @return void
	 * @since 3.2
	 */
	static function ManagerSideMenu($cando = null)
	{
		$perms   = FlexicontentHelperPerm::getPerm();
		$app     = JFactory::getApplication();
		$jinput  = $app->input;
		$db      = JFactory::getDbo();
		$session = JFactory::getSession();
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );

		// Redirect to Joomla backend
		if (!$perms->CanManage)
		{
			$app->redirect('index.php', JText::_('FLEXI_NO_ACCESS'), 'warning');
		}

		// Check access to current management tab
		$is_authorized = $cando === null || $perms->$cando;

		// Redirect to Flexicontent backend Dashboard
		if (!$is_authorized)
		{
			$app->redirect('index.php?option=com_flexicontent', JText::_('FLEXI_NO_ACCESS'), 'warning');
		}

		// Get post-installation FLAG (session variable), and current view (HTTP request variable)
		$dopostinstall = $session->get('flexicontent.postinstall');
		$view = $jinput->get('view', 'flexicontent', 'cmd');

		// Create Submenu, Dashboard (HOME is always added, other will appear only if post-installation tasks are done)
		$addEntry = array(FLEXI_J30GE ? 'JHtmlSidebar' : 'JSubMenuHelper', 'addEntry');

		call_user_func($addEntry, '<h2 class="fcsbnav-content-editing">'.JText::_( 'FLEXI_NAV_SD_CONTENT_EDITING' ).'</h2>', '', '');
		call_user_func($addEntry, '<span class="fcsb-icon-flexicontent icon-home"></span>'.JText::_( 'FLEXI_HOME' ), 'index.php?option=com_flexicontent', !$view || $view=='flexicontent');

		if ($dopostinstall && version_compare(PHP_VERSION, '5.0.0', '>'))
		{
			true
				? call_user_func($addEntry, '<span class="fcsb-icon-items icon-stack"></span>'.JText::_( 'FLEXI_ITEMS' ), 'index.php?option=com_flexicontent&view=items', $view=='items') : null;
			/*$perms->CanArchives
				? call_user_func($addEntry, '<span class="fcsb-icon-archive icon-archive"></span>'.JText::_( 'FLEXI_ARCHIVE' ), 'index.php?option=com_flexicontent&view=archive', $view=='archive') : null;*/
			$perms->CanCats
				? call_user_func($addEntry, '<span class="fcsb-icon-fc_categories icon-folder"></span>'.JText::_( 'FLEXI_CATEGORIES' ), 'index.php?option=com_flexicontent&view=categories', $view=='categories') : null;


			/**
			 * Comments integration to backend management
			 */

			$comments = (int) $cparams->get('comments', 0);

			if ($comments === 1 && !$perms->JComments_Installed || $comments === 3 && !$perms->Komento_Installed)
			{
				call_user_func($addEntry,
					'<span class="fcsb-icon-comments icon-comments disabled"></span>'.
						'<span class="fc_sidebar_entry disabled">' . JText::_('FLEXI_COMMENTS') .
					'</span>', '', false);
			}
			elseif ($comments === 1 && $perms->CanComments)
			{
				call_user_func($addEntry,
					'<a href="index.php?option=com_jcomments&amp;task=view&amp;fog=com_flexicontent" onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\'); return false;">'.
						'<span class="fcsb-icon-comments icon-comments"></span>' . JText::_('FLEXI_COMMENTS') .
					'</a>', '', false);
			}
			elseif ($comments === 3 && $perms->CanComments)
			{
				call_user_func($addEntry,
					'<a href="index.php?option=com_komento" onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\'); return false;">'.
						'<span class="fcsb-icon-comments icon-comments"></span>' . JText::_('FLEXI_COMMENTS') .
					'</a>', '', false);
			}
			elseif ($comments === 0 && $cparams->get('comments_admin_link'))
			{
				call_user_func($addEntry,
					'<a href="' . $cparams->get('comments_admin_link') . '" onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\'); return false;">'.
						'<span class="fcsb-icon-comments icon-comments"></span>'.JText::_('FLEXI_COMMENTS').
					'</a>', '', false);
			}

			$mediadatas_path = JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'views'.DS.'mediadatas';
			
			if (file_exists($mediadatas_path) && $perms->CanMediadatas)
			//&& version_compare(FLEXI_VERSION, '3.2.99', '>'
			{
				$query = 'SELECT COUNT(*) FROM #__flexicontent_fields WHERE field_type="mediafile" AND published=1';
				$fields_exist = (int) $db->setQuery($query)->loadResult();

				if ($fields_exist)
				{
					call_user_func($addEntry, '<span class="fcsb-icon-mediadata icon-equalizer"></span>'.JText::_( 'FLEXI_MEDIADATAS' ), 'index.php?option=com_flexicontent&view=mediadatas', $view=='mediadatas');
				}
			}

			$reviews_path = JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'views'.DS.'reviews';

			if (file_exists($reviews_path) && $perms->CanReviews)
			//&& version_compare(FLEXI_VERSION, '3.2.99', '>'
			{
				$query = 'SELECT * FROM #__flexicontent_fields WHERE field_type="voting"';
				$field = $db->setQuery($query)->loadObject();
				FlexicontentFields::loadFieldConfig($field, $item);
				$allow_reviews = (int)$field->parameters->get('allow_reviews', 0);

				if ($allow_reviews && $perms->CanReviews)
				{
					call_user_func($addEntry, '<span class="fcsb-icon-reviews icon-comments-2"></span>'.JText::_( 'FLEXI_REVIEWS' ), 'index.php?option=com_flexicontent&view=reviews', $view=='reviews');
				}
			}

			if ($perms->CanTypes || $perms->CanFields || $perms->CanTags || $perms->CanFiles)
			{
				call_user_func($addEntry, '<h2 class="fcsbnav-type-fields">'.JText::_( 'FLEXI_NAV_SD_TYPES_N_FIELDS' ).'</h2>', '', '');
			}

			$perms->CanTypes
				? call_user_func($addEntry, '<span class="fcsb-icon-types icon-briefcase"></span>'.JText::_( 'FLEXI_TYPES' ), 'index.php?option=com_flexicontent&view=types', $view=='types') : null;
			$perms->CanFields
				? call_user_func($addEntry, '<span class="fcsb-icon-fields icon-signup"></span>'.JText::_( 'FLEXI_FIELDS' ), 'index.php?option=com_flexicontent&view=fields', $view=='fields') : null;
			$perms->CanTags
				? call_user_func($addEntry, '<span class="fcsb-icon-tags icon-tags"></span>'.JText::_( 'FLEXI_TAGS' ), 'index.php?option=com_flexicontent&view=tags', $view=='tags') : null;
			$perms->CanFiles
				? call_user_func($addEntry, '<span class="fcsb-icon-filemanager icon-images"></span>'.JText::_( 'FLEXI_FILEMANAGER' ), 'index.php?option=com_flexicontent&view=filemanager', $view=='filemanager') : null;

			if ($perms->CanTemplates || $perms->CanIndex || $perms->CanStats)
			{
				call_user_func($addEntry, '<h2 class="fcsbnav-content-viewing">'.JText::_( 'FLEXI_NAV_SD_CONTENT_VIEWING' ).'</h2>', '', '');
			}

			$perms->CanTemplates
				? call_user_func($addEntry, '<span class="fcsb-icon-templates icon-eye"></span>'.JText::_( 'FLEXI_TEMPLATES' ), 'index.php?option=com_flexicontent&view=templates', $view=='templates') : null;
			$perms->CanIndex
				? call_user_func($addEntry, '<span class="fcsb-icon-search icon-search"></span>'.JText::_( 'FLEXI_SEARCH_INDEXES' ), 'index.php?option=com_flexicontent&view=search', $view=='search') : null;

			$CanSeeSearchLogs = !FLEXI_J40GE && JFactory::getUser()->authorise('core.admin', 'com_search');

			if ($CanSeeSearchLogs)
			{
				$params = JComponentHelper::getParams('com_search');
				$enable_log_searches = $params->get('enabled');
				if ($enable_log_searches)
				{
					call_user_func($addEntry,
					'<a href="index.php?option=com_search&tmpl=component" onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\'); return false;" >'.
						'<span class="fcsb-icon-book icon-book"></span>'.JText::_( 'FLEXI_NAV_SD_SEARCH_LOGS' ).
					'</a>', '', false);
				}
				else
				{
					call_user_func($addEntry,
					'<a href="index.php?option=com_config&view=component&component=com_search&path=" onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, function(){window.location.reload(false)}); return false;" >'.
						'<span class="fcsb-icon-book icon-book"></span>'.JText::_( 'FLEXI_NAV_SD_SEARCH_LOGS' ).
					'</a>', '', false);
				}
			}

			$perms->CanStats
				? call_user_func($addEntry, '<span class="fcsb-icon-stats icon-chart"></span>'.JText::_( 'FLEXI_STATISTICS' ), 'index.php?option=com_flexicontent&view=stats', $view=='stats') : null;

			if ($perms->CanAuthors || $perms->CanGroups /*|| $perms->CanArchives*/)
			{
				call_user_func($addEntry, '<h2 class="fcsbnav-users">'.JText::_( 'FLEXI_NAV_SD_USERS_N_GROUPS' ).'</h2>', '', '');
			}

			$perms->CanAuthors
				? call_user_func($addEntry, '<span class="fcsb-icon-users icon-user"></span>'.JText::_( 'FLEXI_USERS' ), 'index.php?option=com_flexicontent&view=users', $view=='users') : null;
			$perms->CanGroups
				? call_user_func($addEntry, '<span class="fcsb-icon-groups icon-users"></span>'.JText::_( 'FLEXI_GROUPS' ), 'index.php?option=com_flexicontent&view=groups', $view=='groups') : null;

			if ($perms->CanConfig || $perms->CanImport || $perms->CanPlugins)
			{
				call_user_func($addEntry, '<h2 class="fcsbnav-expert">'.JText::_( 'FLEXI_NAV_SD_EXPERT_USAGE' ).'</h2>', '', '');
			}

			$appsman_path = JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'views'.DS.'appsman';

			if (file_exists($appsman_path) && version_compare(FLEXI_VERSION, '3.3.99', '>'))
			{
				if ($perms->CanConfig)	call_user_func($addEntry, '<span class="fcsb-icon-wrench icon-wrench"></span>'.JText::_( 'FLEXI_WEBSITE_APPS_IMPORT_EXPORT' ), 'index.php?option=com_flexicontent&view=appsman', $view=='appsman');
			}

			$perms->CanImport
				? call_user_func($addEntry, '<span class="fcsb-icon-import icon-upload"></span>'.JText::_( 'FLEXI_CONTENT_IMPORT' ), 'index.php?option=com_flexicontent&view=import', $view=='import') : null;

			if ($perms->CanPlugins)
			{
				call_user_func($addEntry,
				'<a href="index.php?option=com_plugins" onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\'); return false;" >'.
					'<span class="fcsb-icon-plugins icon-power-cord"></span>'.JText::_( 'FLEXI_PLUGINS' ).
				'</a>', '', false);
			}
			
			if ($perms->CanConfig && empty($_SERVER['HTTPS']))
			{
				call_user_func($addEntry,
				'<a href="https://flexicontent.org/downloads/translations.html?tmpl=component" onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 650, 0); return false;" >'.
					'<span class="fcsb-icon-translations icon-flag"></span>'.JText::_( 'FLEXI_TRANSLATION_PACKAGES' ).
				'</a>', '', false);
			}
			elseif ($perms->CanConfig)
			{
				call_user_func($addEntry,
				'<a href="http://www.flexicontent.org/downloads/download-translation-flexicontent.html?tmpl=component" target="_blank">'.
					'<span class="fcsb-icon-translations icon-flag"></span>'.JText::_( 'FLEXI_TRANSLATION_PACKAGES' ).
				'</a>', '', false);
			}
		}
	}


	/**
	 * Check and compile all Core LESS files
	 */
	static function checkedLessCompile_coreFiles()
	{
		// Files in frontend assets folder
		$path = JPATH_SITE.'/components/com_flexicontent/assets/';
		$inc_path = $path.'less/include/';
		
		/**
		 * LESS files for fields CSS
		 */
		$less_files = array(
			'less/flexi_file_fields.less',
		);
		$force = $stale_fields = flexicontent_html::checkedLessCompile($less_files, $path, $inc_path, $force=false);

		$less_files = array('less/flexi_form_fields.less');
		flexicontent_html::checkedLessCompile($less_files, $path, $inc_path, $force);
		
		
		/**
		 * Individual LESS to CSS files in frontend folder (used by frontend only ??)
		 */
		$less_files = array(
			'less/include/config.less',   /* created file is: css/config.css */
			'less/flexi_filters.less',
			'less/fcvote.less',
			'less/tabber.less',
			'less/j3x.less',
			'less/j3x_rtl.less',
			'less/j4x.less',
			'less/j4x_rtl.less',
		);
		flexicontent_html::checkedLessCompile($less_files, $path, $inc_path, $force=false);

		/**
		 * LESS files for flexicontent.css and flexicontentbackend.css
		 */
		$less_files = array(
			'less/flexi_form.less',
			'less/flexi_containers.less',
			'less/flexi_shared.less',
			'less/flexi_frontend.less',
		);

		$stale_frontend = flexicontent_html::checkedLessCompile($less_files, $path, $inc_path, $force=false);
		$force = $stale_frontend && count($stale_frontend);
		$less_files = array('less/flexicontent.less');
		flexicontent_html::checkedLessCompile($less_files, $path, $inc_path, $force);

		// Files in backend assets folder
		$path = JPATH_ADMINISTRATOR.'/components/com_flexicontent/assets/';
		$inc_path = $path.'less/include/';

		$less_files = array('less/flexi_backend.less');
		$stale_backend = flexicontent_html::checkedLessCompile($less_files, $path, $inc_path, $force=false);

		$force = ($stale_frontend && count($stale_frontend)) || ($stale_backend && count($stale_backend)) ;
		$less_files = array('less/flexicontentbackend.less');
		flexicontent_html::checkedLessCompile($less_files, $path, $inc_path, $force);

		// Other backend less files
		$less_files = array(
			'less/j3x.less',
			'less/j4x.less',
		);
		flexicontent_html::checkedLessCompile($less_files, $path, $inc_path, $force=false);


		/* RTL BOF */

		// Files in frontend assets folder
		$path = JPATH_SITE.'/components/com_flexicontent/assets/';
		$inc_path = $path.'less/include/';

		$less_files = array(
			'less/flexi_form_rtl.less',
			'less/flexi_containers_rtl.less',
			'less/flexi_shared_rtl.less',
			'less/flexi_frontend_rtl.less'
		);

		$stale_frontend = flexicontent_html::checkedLessCompile($less_files, $path, $inc_path, $force=false);
		$force = $stale_frontend && count($stale_frontend);
		$less_files = array('less/flexicontent_rtl.less');
		flexicontent_html::checkedLessCompile($less_files, $path, $inc_path, $force);

		// Files in backend assets folder
		$path = JPATH_ADMINISTRATOR.'/components/com_flexicontent/assets/';
		$inc_path = $path.'less/include/';

		$less_files = array('less/flexi_backend_rtl.less');
		$stale_backend = flexicontent_html::checkedLessCompile($less_files, $path, $inc_path, $force=false);

		$force = ($stale_frontend && count($stale_frontend)) || ($stale_backend && count($stale_backend)) ;
		$less_files = array('less/flexicontentbackend_rtl.less');
		flexicontent_html::checkedLessCompile($less_files, $path, $inc_path, $force);

		// Other backend less files
		$less_files = array(
			'less/j3x_rtl.less',
			'less/j4x_rtl.less',
		);
		flexicontent_html::checkedLessCompile($less_files, $path, $inc_path, $force=false);

		/* RTL EOF */
	}
}
