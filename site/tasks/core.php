<?php
/**
 * @package     FLEXIcontent
 * @subpackage  Tasks Core Component (Direct Access Protected & Universal Security Patched)
 * @copyright   Copyright (C) FLEXIcontent. All rights reserved.
 * @license     GNU/GPL v2 or later
 */

use Joomla\String\StringHelper;

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
		define('JPATH_BASE', realpath(__DIR__ . '/../../..'));
	}
}

$task = new FlexicontentTasksCore();

class FlexicontentTasksCore
{
	var $option = 'com_flexicontent';

	/**
	 * Constructor
	 */
	function __construct()
	{
		// [SECURITY]: ป้องกัน Direct Access จาก Browser / Scanner
		// อนุญาตเฉพาะ AJAX Request ที่ส่งมาจาก Domain ของเว็บไซต์ตัวเองเท่านั้น
		$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
		
		$http_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
		$referer   = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
		$is_valid_origin = (!empty($referer) && parse_url($referer, PHP_URL_HOST) === $http_host);

		// หากเปิดผ่าน URL Browser ตรงๆ โดยไม่มี AJAX Header หรือมาจากเว็บภายนอก ให้ปฏิเสธทันที
		if (!$is_ajax || !$is_valid_origin)
		{
			header('HTTP/1.1 403 Forbidden');
			header('Content-Type: text/plain; charset=utf-8');
			exit('Access Denied: Direct browser requests to this endpoint are strictly forbidden.');
		}

		require_once JPATH_BASE . '/includes/defines.php';
		require_once JPATH_BASE . '/includes/framework.php';

		$is_admin    = preg_match('/\/administrator\//', isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
		$client_name = $is_admin ? 'administrator' : 'site';

		if (!defined('FLEXI_J40GE'))
		{
			if (class_exists('\\Joomla\\CMS\\Version'))
			{
				$jversion = new \Joomla\CMS\Version;
				define('FLEXI_J40GE', version_compare($jversion->getShortVersion(), '3.99.99', '>'));
			}
			elseif (class_exists('JVersion'))
			{
				$jversion = new JVersion;
				define('FLEXI_J40GE', version_compare($jversion->getShortVersion(), '3.99.99', '>'));
			}
			else
			{
				define('FLEXI_J40GE', false);
			}
		}

		if (!FLEXI_J40GE)
		{
			$app = class_exists('\\Joomla\\CMS\\Factory')
				? \Joomla\CMS\Factory::getApplication($client_name)
				: JFactory::getApplication($client_name);
			$app->initialise();
		}
		else
		{
			$container = \Joomla\CMS\Factory::getContainer();

			$container->alias('session.web', 'session.web.' . $client_name)
				->alias('session', 'session.web.' . $client_name)
				->alias('\Joomla\CMS\Session\Session', 'session.web.' . $client_name)
				->alias(\Joomla\CMS\Session\Session::class, 'session.web.' . $client_name)
				->alias(\Joomla\Session\Session::class, 'session.web.' . $client_name)
				->alias(\Joomla\Session\SessionInterface::class, 'session.web.' . $client_name);

			$app = $is_admin
				? $container->get(\Joomla\CMS\Application\AdministratorApplication::class)
				: $container->get(\Joomla\CMS\Application\SiteApplication::class);

			\Joomla\CMS\Factory::$application = $app;
		}

		// [FIX 1 - Broken Access Control]: Task Whitelisting
		$jinput = $app->input;
		$task   = $jinput->get('task', '', 'cmd');

		$allowed_tasks = array('txtautocomplete', 'viewtags');

		if (in_array($task, $allowed_tasks) && method_exists($this, $task))
		{
			$this->$task();
		}
		else
		{
			header('HTTP/1.1 403 Forbidden');
			jexit('Invalid task or access denied.');
		}
	}


	/**
	 * Search Autocomplete Logic
	 */
	public function txtautocomplete()
	{
		$this->_callPlugins();
		global $globalcats;

		$app = $this->_getApp();
		$jinput  = $app->input;
		
		$cparams = class_exists('\\Joomla\\CMS\\Component\\ComponentHelper')
			? \Joomla\CMS\Component\ComponentHelper::getParams($this->option)
			: JComponentHelper::getParams($this->option);
		
		$use_tmp = true;

		$type = $jinput->get('type', '', 'cmd');
		$text = $jinput->get('text', '', 'string');
		$lang = $jinput->get('lang', '', 'cmd');
		$lang = substr($lang, 0, 2);

		// [FIX 2 - Strict Integer Constraints]
		$pageSize = max(1, $jinput->get('pageSize', 20, 'int'));
		$pageNum  = max(1, $jinput->get('pageNum', 1, 'int'));
		$usesubs  = $jinput->get('usesubs', 1, 'int');

		$min_word_len = $app->getUserState($this->option . '.min_word_len', 0);
		$filtercat    = $cparams->get('filtercat', 0);
		$show_noauth  = $cparams->get('show_noauth', 0);

		$cid  = $jinput->get('cid', 0, 'int');
		$cids = $jinput->get('cids', '', 'string');

		if ($cid)
		{
			$_cids = array($cid);
		}
		elseif (!empty($cids))
		{
			if (!is_array($cids))
			{
				$_cids = preg_replace('/[^0-9,]/i', '', (string) $cids);
				$_cids = explode(',', $_cids);
			}
			else
			{
				$_cids = $cids;
			}
		}
		else
		{
			$_cids = array();
		}

		// [FIX 3 - Blind SQL Injection Prevention]
		$cids = array();
		foreach ($_cids as $_id)
		{
			$clean_id = (int) $_id;
			if ($clean_id > 0)
			{
				$cids[] = $clean_id;
			}
		}

		if ($usesubs && !empty($cids))
		{
			$subcats = array();
			foreach ($cids as $_id)
			{
				if (!isset($globalcats[$_id])) continue;
				$subcats = array_merge($subcats, $globalcats[$_id]->descendantsarray);
			}
			$cids = array_unique(array_map('intval', $subcats));
		}

		$cid_list = !empty($cids) ? implode(',', $cids) : '';

		if ($type != 'basic_index' && $type != 'adv_index') jexit();
		if (!strlen($text)) jexit();

		$search_prefix = $cparams->get('add_search_prefix') ? 'vvv' : '';
		$words = preg_split('/\s\s*/u', $text);

		$_words = array();
		foreach ($words as & $_w)
		{
			$_words[] = !$search_prefix ? trim($_w) : preg_replace('/(\b[^\s,\.]+\b)/u', $search_prefix . '$0', trim($_w));
		}
		$newtext = '+' . implode(' +', $_words) . '*';

		$db = $this->_getDbo();
		$quoted_text = $db->escape($newtext, true);
		$quoted_text = $db->Quote($quoted_text, false);
		$_text_match = ' MATCH (si.search_index) AGAINST (' . $quoted_text . ' IN BOOLEAN MODE) ';

		$limitstart = (int) ($pageSize * ($pageNum - 1));
		$limit      = (int) $pageSize;

		$lang_where = '';

		if ($filtercat)
		{
			$lta = 'i';
			$current_lang_tag = $this->_getLanguageTag();
			$lang_where .= ' AND (' . $lta . '.language LIKE ' . $db->Quote($lang . '%') . ' OR ' . $lta . '.language = ' . $db->Quote($current_lang_tag) . ' OR ' . $lta . '.language="*" ) ';
		}

		$access_where = '';
		$joinaccess   = '';

		$_nowDate  = 'UTC_TIMESTAMP()';
		$nullDate  = $db->getNullDate();

		$tbl = ($type == 'basic_index') ? 'flexicontent_items_ext' : 'flexicontent_advsearch_index';
		$query = 'SELECT si.item_id, si.search_index'
			. ' FROM #__' . $tbl . ' AS si'
			. ' JOIN ' . ($use_tmp ? '#__flexicontent_items_tmp' : '#__content') . ' AS i ON i.id = si.item_id'
			. (($access_where && !$use_tmp) || ($filtercat && !$use_tmp) || $type !== 'basic_index' ?
				' JOIN #__flexicontent_items_ext AS ie ON i.id = ie.item_id ' : '')
			. ($access_where ? ' JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id' : '')
			. ($access_where ? ' JOIN #__categories AS mc ON mc.id = i.catid' : '')
			. ($cid_list ? ' JOIN #__flexicontent_cats_item_relations AS rel ON i.id = rel.itemid AND rel.catid IN (' . $cid_list . ')' : '')
			. $joinaccess
			. ' WHERE ' . $_text_match
			. '   AND i.state IN (1,-5) '
			. '   AND ( i.publish_up is NULL OR i.publish_up = ' . $db->Quote($nullDate) . ' OR i.publish_up <= ' . $_nowDate . ' ) '
			. '   AND ( i.publish_down is NULL OR i.publish_down = ' . $db->Quote($nullDate) . ' OR i.publish_down >= ' . $_nowDate . ' ) '
			. $lang_where
			. $access_where
			. ' LIMIT ' . $limitstart . ', ' . $limit;

		$data = $db->setQuery($query)->loadAssocList();

		$word_prefix = array_pop($words);
		$complete_words = implode(' ', $words);

		$words_found = array();
		// [FIX 4 - ReDoS Protection]
		$regex = '/(\b)(' . preg_quote($search_prefix, '/') . preg_quote($word_prefix, '/') . '\w*)(\b)/iu';

		if (!empty($data))
		{
			foreach ($data as $_d)
			{
				if (preg_match_all($regex, $_d['search_index'], $matches))
				{
					foreach ($matches[2] as $_m)
					{
						if ($search_prefix)
						{
							$_m = preg_replace('/\b' . $search_prefix . '/u', '', $_m);
						}
						$_m_low = StringHelper::strtolower($_m, 'UTF-8');
						$words_found[$_m_low] = 1;
					}
				}
			}
		}

		$options = array();
		$options['Total'] = count($words_found);
		$options['Matches'] = array();
		$n = 0;

		foreach ($words_found as $_w => $i)
		{
			if (!$search_prefix)
			{
				if (StringHelper::strlen($_w) < $min_word_len)
				{
					continue;
				}

				if ($this->_isStopWord($_w, $tbl))
				{
					continue;
				}
			}

			$options['Matches'][] = array(
				'text' => $complete_words . ($complete_words ? ' ' : '') . $_w,
				'id'   => $complete_words . ($complete_words ? ' ' : '') . $_w
			);
			++$n;

			if ($n >= $pageSize)
			{
				break;
			}
		}

		jexit(json_encode($options));
	}


	/**
	 * Method to fetch tags
	 */
	public function viewtags()
	{
		$token_valid = false;
		if (class_exists('\\Joomla\\CMS\\Session\\Session'))
		{
			$token_valid = \Joomla\CMS\Session\Session::checkToken('request');
		}
		elseif (class_exists('JSession'))
		{
			$token_valid = JSession::checkToken('request');
		}

		if (!$token_valid)
		{
			$invalid_token_msg = class_exists('\\Joomla\\CMS\\Language\\Text')
				? \Joomla\CMS\Language\Text::_('JINVALID_TOKEN')
				: (class_exists('JText') ? JText::_('JINVALID_TOKEN') : 'Invalid Token');
			jexit($invalid_token_msg);
		}

		$permission_helper = JPATH_SITE . '/components/com_flexicontent/helpers/permission.php';
		if (file_exists($permission_helper))
		{
			require_once $permission_helper;
		}

		$app   = $this->_getApp();
		$perms = class_exists('FlexicontentHelperPerm') ? FlexicontentHelperPerm::getPerm() : (object) array('CanUseTags' => true, 'CanCreateTags' => true);

		@ob_end_clean();

		header('Content-type: application/json');
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Cache-Control: no-cache");
		header("Pragma: no-cache");

		$array = array();

		if (isset($perms->CanUseTags) && !$perms->CanUseTags)
		{
			$this->_loadLanguage();
			$no_access_msg = class_exists('\\Joomla\\CMS\\Language\\Text')
				? \Joomla\CMS\Language\Text::_('FLEXI_FIELD_NO_ACCESS')
				: (class_exists('JText') ? JText::_('FLEXI_FIELD_NO_ACCESS') : 'No Access');

			$array[] = (object) array(
				'id'   => '0',
				'name' => $no_access_msg
			);
		}
		else
		{
			$q = $app->input->getString('q', '');
			$q = $q !== parse_url(@$_SERVER["REQUEST_URI"], PHP_URL_PATH) ? $q : '';

			$limit = $q && !trim($q) ? 10000 : 500;

			$tagobjs = $this->_getTags($q, $limit);

			if ($tagobjs)
			{
				foreach ($tagobjs as $tag)
				{
					$array[] = (object) array(
						'id'              => $tag->id,
						'name'            => $tag->name,
						'translated_text' => isset($tag->fa_text) ? $tag->fa_text : ''
					);
				}
			}

			if (empty($array))
			{
				$this->_loadLanguage();
				$can_create = isset($perms->CanCreateTags) && $perms->CanCreateTags;
				$tag_key = $can_create ? 'FLEXI_NEW_TAG_ENTER_TO_CREATE' : 'FLEXI_NO_TAGS_FOUND';
				
				$empty_msg = class_exists('\\Joomla\\CMS\\Language\\Text')
					? \Joomla\CMS\Language\Text::_($tag_key)
					: (class_exists('JText') ? JText::_($tag_key) : ($can_create ? 'New tag: enter to create' : 'No tags found'));

				$array[] = (object) array(
					'id'              => '0',
					'name'            => $empty_msg,
					'translated_text' => ''
				);
			}
		}

		jexit(json_encode($array));
	}


	// Private & Compatibility Helpers
	private function _isStopWord($word, $tbl = 'flexicontent_items_ext', $col = 'search_index')
	{
		$app = $this->_getApp();
		$jinput = $app->input;
		if ($jinput->get('task', '', 'cmd') == __FUNCTION__) die(__FUNCTION__ . ' : direct call not allowed');

		$db = $this->_getDbo();
		$quoted_word = $db->escape($word, true);
		$query = 'SELECT ' . $col
			. ' FROM #__' . $tbl
			. ' WHERE MATCH (' . $col . ') AGAINST ("+' . $quoted_word . '")'
			. ' LIMIT 1';
		$result = $db->setQuery($query)->loadAssocList();

		return !empty($result) ? true : false;
	}


	private function _callPlugins()
	{
		$app = $this->_getApp();
		$jinput = $app->input;
		if ($jinput->get('task', '', 'cmd') == __FUNCTION__) die(__FUNCTION__ . ' : direct call not allowed');

		$extfolder = 'system';
		$extname   = 'flexisystem';
		$className = 'plg' . ucfirst($extfolder) . $extname;

		$plugin_path = JPATH_SITE . '/plugins/' . $extfolder . '/' . $extname . '/' . $extname . '.php';
		if (!file_exists($plugin_path))
		{
			return;
		}
		require_once $plugin_path;

		$plg_db_data = class_exists('\\Joomla\\CMS\\Plugin\\PluginHelper')
			? \Joomla\CMS\Plugin\PluginHelper::getPlugin($extfolder, $extname)
			: (class_exists('JPluginHelper') ? JPluginHelper::getPlugin($extfolder, $extname) : (object) array('params' => ''));

		if (class_exists('JEventDispatcher'))
		{
			$dispatcher = JEventDispatcher::getInstance();
			$plg = new $className($dispatcher, array('type' => $extfolder, 'name' => $extname, 'params' => isset($plg_db_data->params) ? $plg_db_data->params : ''));
		}
		elseif (class_exists('\\Joomla\\CMS\\Factory'))
		{
			$dispatcher = \Joomla\CMS\Factory::getApplication();
			$plg = new $className($dispatcher, array('type' => $extfolder, 'name' => $extname, 'params' => isset($plg_db_data->params) ? $plg_db_data->params : ''));
		}

		global $globalcats;
		$cache_enabled = defined('FLEXI_CACHE') && FLEXI_CACHE;
		$cache_time    = defined('FLEXI_CACHE_TIME') ? FLEXI_CACHE_TIME : 3600;

		if ($cache_enabled && isset($plg))
		{
			$catscache = class_exists('\\Joomla\\CMS\\Factory')
				? \Joomla\CMS\Factory::getCache('com_flexicontent_cats')
				: JFactory::getCache('com_flexicontent_cats');

			$catscache->setCaching(1);
			$catscache->setLifeTime($cache_time);
			$globalcats = $catscache->get(
				array($plg, 'getCategoriesTree'),
				array()
			);
		}
		elseif (isset($plg) && method_exists($plg, 'getCategoriesTree'))
		{
			$globalcats = $plg->getCategoriesTree();
		}
	}


	private function _getTags($text = '', $limit = 500)
	{
		$app = $this->_getApp();
		$jinput = $app->input;
		if ($jinput->get('task', '', 'cmd') == __FUNCTION__) die(__FUNCTION__ . ' : direct call not allowed');

		$cparams = class_exists('\\Joomla\\CMS\\Component\\ComponentHelper')
			? \Joomla\CMS\Component\ComponentHelper::getParams($this->option)
			: JComponentHelper::getParams($this->option);

		$falang_enabled = class_exists('\\Joomla\\CMS\\Plugin\\PluginHelper')
			? \Joomla\CMS\Plugin\PluginHelper::isEnabled('system', 'falangdriver')
			: (class_exists('JPluginHelper') ? JPluginHelper::isEnabled('system', 'falangdriver') : false);

		if (!defined('FLEXI_FISH'))   define('FLEXI_FISH', ($cparams->get('flexi_fish', 0) && $falang_enabled) ? 1 : 0);
		if (!defined('FLEXI_FALANG')) define('FLEXI_FALANG', defined('FLEXI_FISH') && FLEXI_FISH);

		$db = $this->_getDbo();

		$lang_code = $jinput->getString('item_lang');
		$lang_code = $lang_code && $lang_code !== '*'
			? $lang_code
			: $jinput->getString('lang', $this->_getLanguageTag());

		$query = $db->getQuery(true)
			->select('la.*')
			->from('#__languages AS la')
			->where('la.lang_code = ' . $db->quote($lang_code));

		$lang = $db->setQuery($query)->loadObject();

		$query = $db->getQuery(true)
			->select('ft.*')
			->from('#__flexicontent_tags AS ft')
			->where('ft.published = 1')
			->order('ft.name');

		if (defined('FLEXI_FALANG') && FLEXI_FALANG && $lang)
		{
			$query->select('fa.value AS fa_text')
				->leftjoin('#__falang_content AS fa ON fa.reference_table = "tags" AND fa.reference_field = "title" AND fa.reference_id = ft.jtag_id AND fa.language_id = ' . (int) $lang->lang_id);
		}
		else
		{
			$query->select('"" AS fa_text');
		}

		if (trim($text))
		{
			$escaped_text = $db->escape($text, true);
			$quoted_text  = $db->Quote('%' . $escaped_text . '%', false);

			if (defined('FLEXI_FALANG') && FLEXI_FALANG)
			{
				$query->where('ft.name LIKE ' . $quoted_text . ' OR fa.value LIKE ' . $quoted_text);
			}
			else
			{
				$query->where('name LIKE ' . $quoted_text);
			}
		}

		$tags = $db->setQuery($query, 0, (int) $limit)->loadObjectlist();

		return $tags;
	}


	private function _loadLanguage()
	{
		$app = $this->_getApp();
		$jinput = $app->input;
		if ($jinput->get('task', '', 'cmd') == __FUNCTION__) die(__FUNCTION__ . ' : direct call not allowed');

		if (class_exists('\\Joomla\\CMS\\Factory'))
		{
			\Joomla\CMS\Factory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
			\Joomla\CMS\Factory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);
		}
		elseif (class_exists('JFactory'))
		{
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);
		}
	}


	private function _getApp()
	{
		return class_exists('\\Joomla\\CMS\\Factory')
			? \Joomla\CMS\Factory::getApplication()
			: JFactory::getApplication();
	}

	private function _getDbo()
	{
		return class_exists('\\Joomla\\CMS\\Factory')
			? \Joomla\CMS\Factory::getDbo()
			: JFactory::getDbo();
	}

	private function _getLanguageTag()
	{
		if (class_exists('\\Joomla\\CMS\\Factory'))
		{
			return \Joomla\CMS\Factory::getLanguage()->getTag();
		}
		elseif (class_exists('JFactory'))
		{
			return JFactory::getLanguage()->getTag();
		}
		return 'en-GB';
	}
}
