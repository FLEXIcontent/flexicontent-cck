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
		
		// หากเปิดผ่าน URL Browser ตรงๆ โดยไม่มี AJAX Header หรือมาจากเว็บภายนอก ให้ปฏิเสธทันที
		if (!$is_ajax)
		{
			header('HTTP/1.1 403 Forbidden');
			header('Content-Type: text/plain; charset=utf-8');
			exit('Access Denied: Direct browser requests to this endpoint are strictly forbidden.');
		}

		require_once JPATH_BASE . '/includes/defines.php';
		require_once JPATH_BASE . '/includes/framework.php';

		// Read proxy trust only after Joomla has loaded the site's configuration.
		$config = new JConfig();

		if (!self::hasSameOrigin($_SERVER, !empty($config->behind_loadbalancer)))
		{
			header('HTTP/1.1 403 Forbidden');
			header('Content-Type: text/plain; charset=utf-8');
			exit('Access Denied: Direct browser requests to this endpoint are strictly forbidden.');
		}

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
	 * Compare the browser origin with the request authority, including effective ports.
	 * Forwarded scheme headers are trusted only for a configured load balancer.
	 */
	protected static function hasSameOrigin($server, $behind_loadbalancer = false)
	{
		$host = isset($server['HTTP_HOST']) ? $server['HTTP_HOST'] : '';
		$referer = isset($server['HTTP_REFERER']) ? $server['HTTP_REFERER'] : '';

		if (!is_string($host) || !is_string($referer) || $host === '' || $referer === ''
			|| preg_match('/[\x00-\x20\x7f]/', $host . $referer)
			|| strpos($host . $referer, '\\') !== false)
		{
			return false;
		}

		$scheme = !empty($server['HTTPS']) && strtolower($server['HTTPS']) !== 'off' ? 'https' : 'http';

		if ($behind_loadbalancer && !empty($server['HTTP_X_FORWARDED_PROTO']))
		{
			$forwarded_scheme = strtolower(trim($server['HTTP_X_FORWARDED_PROTO']));

			if ($forwarded_scheme !== 'http' && $forwarded_scheme !== 'https')
			{
				return false;
			}

			$scheme = $forwarded_scheme;
		}

		$request = parse_url($scheme . '://' . $host);
		$origin = parse_url($referer);

		if (!$request || !$origin || empty($request['host']) || empty($origin['host']) || empty($origin['scheme'])
			|| isset($request['user']) || isset($request['pass']) || isset($request['path'])
			|| isset($request['query']) || isset($request['fragment'])
			|| isset($origin['user']) || isset($origin['pass']))
		{
			return false;
		}

		$default_port = $scheme === 'https' ? 443 : 80;
		$request_port = isset($request['port']) ? $request['port'] : $default_port;
		$origin_port = isset($origin['port']) ? $origin['port'] : $default_port;

		return strtolower($origin['scheme']) === $scheme
			&& strtolower($origin['host']) === strtolower($request['host'])
			&& $request_port > 0 && $request_port === $origin_port;
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

		// Content types selected in the search form (suggestions are limited to them)
		$type_ids = array_filter(array_map('intval', explode(',', preg_replace('/[^0-9,]/', '', $jinput->get('types', '', 'string')))));
		$type_join = $type_ids ? ' JOIN #__flexicontent_items_ext AS tie ON tie.item_id = i.id AND tie.type_id IN (' . implode(',', $type_ids) . ')' : '';

		if ($type != 'basic_index' && $type != 'adv_index') jexit();
		if (!strlen($text)) jexit();

		$search_prefix = $cparams->get('add_search_prefix') ? 'vvv' : '';

		// Trailing space means the last word is complete, so we suggest the NEXT word
		$next_word = (bool) preg_match('/\s$/u', $text);
		$text  = trim($text);
		if (!strlen($text)) jexit();
		$words = preg_split('/\s\s*/u', $text);

		$_words = array();
		foreach ($words as & $_w)
		{
			$_words[] = !$search_prefix ? trim($_w) : preg_replace('/(\b[^\s,\.]+\b)/u', $search_prefix . '$0', trim($_w));
		}
		unset($_w);  // break the reference left by the foreach above
		$newtext = '+' . implode(' +', $_words) . ($next_word ? '' : '*');

		$db = $this->_getDbo();
		$quoted_text = $db->escape($newtext, true);
		$quoted_text = $db->Quote($quoted_text, false);
		$_text_match = ' MATCH (si.search_index) AGAINST (' . $quoted_text . ' IN BOOLEAN MODE) ';

		// Rows of the search index to scan for candidate words, and max number of suggestions returned
		$scan_rows = max(50, min(2000, (int) $cparams->get('search_autocomplete_scan', 300)));
		$pageSize  = max(1, min(30, (int) $cparams->get('search_autocomplete_limit', 10)));

		$limitstart = 0;
		$limit      = $scan_rows;

		$titles_limit = max(1, min(30, (int) $cparams->get('search_autocomplete_titles_limit', 5)));
		$fields_limit = max(1, min(30, (int) $cparams->get('search_autocomplete_fields_limit', 3)));

		// Rich suggestions options (module configuration), used to enrich item-title suggestions
		$ac_rich = (int) $jinput->get('ac_rich', 0, 'int') === 1;

		// Read a string option, joining array input into a comma separated list
		$ac_str = function ($name, $default = '') use ($jinput)
		{
			$val = $jinput->get($name, $default, 'string');

			if (is_array($val))
			{
				$val = implode(',', array_filter($val, 'is_scalar'));
			}

			return trim((string) $val);
		};

		$ac_image_source = strtolower($ac_str('ac_image_source', 'auto'));
		if (!in_array($ac_image_source, array('auto', 'field', 'type'), true))
		{
			$ac_image_source = 'auto';
		}

		// Extra fields: 'module' = fields listed in the module, 'type' = fields set in the content type of each item
		$ac_info_source = strtolower($ac_str('ac_info_source', 'module')) === 'type' ? 'type' : 'module';

		$ac_image_field = strtolower($ac_str('ac_image_field', ''));
		if ($ac_image_field === '0' || !preg_match('/^[a-z0-9_-]{1,100}$/', $ac_image_field))
		{
			$ac_image_field = '';
		}

		$ac_image_width    = max(16, min(400, (int) $jinput->get('ac_image_width', 60, 'int')));
		$ac_image_height   = max(16, min(400, (int) $jinput->get('ac_image_height', 60, 'int')));
		$ac_image_method   = (int) $jinput->get('ac_image_method', 1, 'int') ? 1 : 0;
		$ac_title_maxlen   = max(0, min(300, (int) $jinput->get('ac_title_maxlen', 60, 'int')));
		$ac_info_maxlen    = max(0, min(300, (int) $jinput->get('ac_info_maxlen', 80, 'int')));
		$ac_info_labels    = (int) $jinput->get('ac_info_labels', 1, 'int') === 1;

		$ac_info_fields = array();
		foreach (preg_split('/\s*,\s*/', $ac_str('ac_info_fields', '')) as $_field_name)
		{
			$_field_name = strtolower(trim($_field_name));
			if (preg_match('/^[a-z0-9_-]{1,100}$/', $_field_name) && !in_array($_field_name, $ac_info_fields, true))
			{
				$ac_info_fields[] = $_field_name;
			}
			if (count($ac_info_fields) >= 12)
			{
				break;
			}
		}

		// Suggestion sources and their order (sortable option), e.g. "tags,words,titles"
		$sources_order = array();
		$_sources_cfg = (string) $cparams->get('search_autocomplete_sources', '');
		foreach (preg_split('/\s*,\s*/', trim($_sources_cfg)) as $_src)
		{
			if (in_array($_src, array('words', 'titles', 'tags', 'categories', 'fields', 'metakeys'), true) && !in_array($_src, $sources_order, true))
			{
				$sources_order[] = $_src;
			}
		}

		// Not configured yet: use the previous options (mode and orders), so that existing configurations keep working
		if (!$sources_order)
		{
			$_legacy_mode = (int) $cparams->get('search_autocomplete_titles', 0);

			if ($_legacy_mode == 1)
			{
				$sources_order = (int) $cparams->get('search_autocomplete_titles_order', 0) === 1
					? array('titles', 'words') : array('words', 'titles');
			}
			elseif ($_legacy_mode == 3)
			{
				$_orders = array(
					0 => array('tags', 'words', 'titles'),
					1 => array('tags', 'titles', 'words'),
					2 => array('words', 'tags', 'titles'),
					3 => array('titles', 'tags', 'words')
				);
				$sources_order = $_orders[(int) $cparams->get('search_autocomplete_tags_order', 0)] ?? $_orders[0];
			}
			elseif ($_legacy_mode == 2)
			{
				$sources_order = array('titles');
			}
			else
			{
				$sources_order = array('words');
			}
		}

		// Further pages (infinite scroll of the Tag-like auto-complete) list only more words, never titles / tags again
		if ($pageNum > 1)
		{
			$sources_order = in_array('words', $sources_order) ? array('words') : array();
		}

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
			. $type_join
			. $joinaccess
			. ' WHERE ' . $_text_match
			. '   AND i.state IN (1,-5) '
			. '   AND ( i.publish_up is NULL OR i.publish_up = ' . $db->Quote($nullDate) . ' OR i.publish_up <= ' . $_nowDate . ' ) '
			. '   AND ( i.publish_down is NULL OR i.publish_down = ' . $db->Quote($nullDate) . ' OR i.publish_down >= ' . $_nowDate . ' ) '
			. $lang_where
			. $access_where
			. ' LIMIT ' . $limitstart . ', ' . $limit;

		$data = !in_array('words', $sources_order) ? array() : $db->setQuery($query)->loadAssocList();

		$typed_words = array();
		foreach ($words as $_w)
		{
			$typed_words[StringHelper::strtolower($_w, 'UTF-8')] = 1;
		}

		$word_prefix = $next_word ? '' : array_pop($words);
		$complete_words = implode(' ', $words);

		$words_found = array();
		// [FIX 4 - ReDoS Protection]
		if ($next_word)
		{
			// The word that directly follows the last typed word, e.g. "vvvlaki vvvpatcher" => "patcher"
			$_last_typed = end($words);
			$regex = '/\b' . preg_quote($search_prefix . $_last_typed, '/') . '\s+' . preg_quote($search_prefix, '/') . '(\w+)/iu';
			$regex_group = 1;
		}
		else
		{
			$regex = '/(\b)(' . preg_quote($search_prefix, '/') . preg_quote($word_prefix, '/') . '\w*)(\b)/iu';
			$regex_group = 2;
		}

		if (!empty($data))
		{
			foreach ($data as $_d)
			{
				if (preg_match_all($regex, $_d['search_index'], $matches))
				{
					foreach ($matches[$regex_group] as $_m)
					{
						if (!$next_word && $search_prefix)
						{
							$_m = preg_replace('/\b' . $search_prefix . '/u', '', $_m);
						}
						$_m_low = StringHelper::strtolower($_m, 'UTF-8');
						if ($next_word && isset($typed_words[$_m_low])) continue;  // do not suggest already typed words
						$words_found[$_m_low] = isset($words_found[$_m_low]) ? $words_found[$_m_low] + 1 : 1;
					}
				}
			}
		}

		// Most frequent words first
		arsort($words_found);

		$options = array();
		$options['Total'] = count($words_found);
		$options['Matches'] = array();
		$n = 0;
		$skip = $pageSize * ($pageNum - 1);

		foreach ($words_found as $_w => $i)
		{
			if (StringHelper::strlen($_w) < $min_word_len)
			{
				continue;
			}

			if ($this->_isStopWord($_w, $tbl, 'search_index', $search_prefix))
			{
				continue;
			}

			if ($skip > 0)
			{
				--$skip;
				continue;
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

		// Item titles and tags that match the typed words
		$suggestions = array('words' => $options['Matches'], 'titles' => array(), 'tags' => array(), 'categories' => array(), 'fields' => array(), 'metakeys' => array());

		if (array_diff($sources_order, array('words')))
		{
			// Conditions for the typed words: last word is still being typed (prefix of a word), earlier words must be contained
			$_typed = preg_split('/\s+/u', $text);
			$_last_i = count($_typed) - 1;
			$_text_like = $db->Quote($db->escape($text, true) . '%', false);

			$_conds = function ($col) use ($db, $_typed, $_last_i, $next_word)
			{
				$conds = array();
				foreach ($_typed as $_i => $_tw)
				{
					$_e = $db->escape($_tw, true);
					$conds[] = ($_i == $_last_i && !$next_word)
						? '(' . $col . ' LIKE ' . $db->Quote($_e . '%', false) . ' OR ' . $col . ' LIKE ' . $db->Quote('% ' . $_e . '%', false) . ')'
						: $col . ' LIKE ' . $db->Quote('%' . $_e . '%', false);
				}
				return implode(' AND ', $conds);
			};

			// Respect the access levels of the current user
			$user   = method_exists($app, 'getIdentity') ? $app->getIdentity() : \Joomla\CMS\Factory::getUser();
			$levels = array_map('intval', $user->getAuthorisedViewLevels());
			$item_access = $levels ? ' AND i.access IN (' . implode(',', $levels) . ')' : ' AND 0 = 1';

			// Like the search results: the main category and the content type must be viewable too
			if ($levels)
			{
				$item_access .= ' AND EXISTS (SELECT 1 FROM #__categories AS acc_mc WHERE acc_mc.id = i.catid AND acc_mc.published = 1'
					. ' AND acc_mc.access IN (' . implode(',', $levels) . '))'
					. ' AND EXISTS (SELECT 1 FROM #__flexicontent_types AS acc_ty WHERE acc_ty.id = i.type_id'
					. ' AND acc_ty.access IN (' . implode(',', $levels) . '))';
			}

			$item_filters = ''
				. '   AND i.state IN (1,-5) '
				. '   AND ( i.publish_up is NULL OR i.publish_up = ' . $db->Quote($nullDate) . ' OR i.publish_up <= ' . $_nowDate . ' ) '
				. '   AND ( i.publish_down is NULL OR i.publish_down = ' . $db->Quote($nullDate) . ' OR i.publish_down >= ' . $_nowDate . ' ) '
				. $lang_where
				. $item_access;

			$cid_join = ($cid_list ? ' JOIN #__flexicontent_cats_item_relations AS crel ON i.id = crel.itemid AND crel.catid IN (' . $cid_list . ')' : '') . $type_join;

			if (in_array('titles', $sources_order))
			{
				$title_query = 'SELECT DISTINCT i.id, i.title'
					. ' FROM #__flexicontent_items_tmp AS i'
					. $cid_join
					. ' WHERE ' . $_conds('i.title')
					. $item_filters
					. ' ORDER BY (i.title LIKE ' . $_text_like . ') DESC, i.hits DESC'
					. ' LIMIT ' . $titles_limit;

				foreach ((array) $db->setQuery($title_query)->loadAssocList() as $_t)
				{
					$suggestions['titles'][] = array(
						'text'     => $_t['title'],
						'id'       => $_t['title'],
						'_item_id' => (int) $_t['id'],
					);
				}
			}

			// Falang translation of tag / category titles for the page language (same lookup as the tags / categories field search index)
			$fa_lang_id = 0;
			if (in_array('tags', $sources_order) || in_array('categories', $sources_order))
			{
				$falang_enabled = class_exists('\\Joomla\\CMS\\Plugin\\PluginHelper')
					&& \Joomla\CMS\Plugin\PluginHelper::isEnabled('system', 'falangdriver')
					&& $cparams->get('flexi_fish', 0);

				if ($falang_enabled)
				{
					$_cur_tag = $this->_getLanguageTag();
					$_lq = 'SELECT la.lang_id FROM #__languages AS la WHERE '
						. ($lang ? 'la.lang_code LIKE ' . $db->Quote($db->escape($lang, true) . '%', false) : 'la.lang_code = ' . $db->Quote($_cur_tag))
						. ' ORDER BY (la.lang_code = ' . $db->Quote($_cur_tag) . ') DESC';
					$fa_lang_id = (int) $db->setQuery($_lq, 0, 1)->loadResult();
				}
			}

			if (in_array('categories', $sources_order))
			{
				// Categories that have at least one item visible to the user, starting matches first, then most items
				$cat_query = 'SELECT c.id, c.title, c.metakey, ' . ($fa_lang_id ? 'fa.value' : '""') . ' AS fa_text, ' . ($fa_lang_id ? 'fk.value' : '""') . ' AS fa_metakey, COUNT(DISTINCT i.id) AS cnt'
					. ' FROM #__categories AS c'
					. ' JOIN #__flexicontent_cats_item_relations AS crel2 ON crel2.catid = c.id'
					. ' JOIN #__flexicontent_items_tmp AS i ON i.id = crel2.itemid'
					. ($fa_lang_id
						? ' LEFT JOIN #__falang_content AS fa ON fa.reference_table = "categories" AND fa.reference_field = "title"'
							. ' AND fa.reference_id = c.id AND fa.language_id = ' . $fa_lang_id
							. ' LEFT JOIN #__falang_content AS fk ON fk.reference_table = "categories" AND fk.reference_field = "metakey"'
							. ' AND fk.reference_id = c.id AND fk.language_id = ' . $fa_lang_id
						: '')
					. $cid_join
					. ' WHERE c.published = 1 AND c.extension = "com_content" AND c.id <> 1'
					. ' AND c.access IN (' . ($levels ? implode(',', $levels) : '0') . ') AND '
					. '(' . $_conds('c.title') . ' OR ' . $_conds('c.metakey') . ($fa_lang_id ? ' OR ' . $_conds('fa.value') . ' OR ' . $_conds('fk.value') : '') . ')'
					. $item_filters
					. ' GROUP BY c.id, c.title, c.metakey' . ($fa_lang_id ? ', fa.value, fk.value' : '')
					. ' ORDER BY (' . ($fa_lang_id ? 'COALESCE(NULLIF(fa.value, ""), c.title)' : 'c.title') . ' LIKE ' . $_text_like . ') DESC, cnt DESC'
					. ' LIMIT ' . $titles_limit;

				foreach ((array) $db->setQuery($cat_query)->loadAssocList() as $_c)
				{
					// Show the translated title when there is one, otherwise the original title
					$_cat_text = strlen(trim((string) $_c['fa_text'])) ? $_c['fa_text'] : $_c['title'];
					$_cat_hits = array();

					// Category keywords (Meta Keywords) that match the typed text are suggested too, e.g. "Pop Music"
					foreach (array_filter(array_map('trim', explode(',', $_c['metakey'] . ',' . $_c['fa_metakey']))) as $_kw)
					{
						$_ok = true;
						foreach ($_typed as $_i => $_tw)
						{
							$_pat = '/' . (($_i == $_last_i && !$next_word) ? '(^|\\s)' : '') . preg_quote($_tw, '/') . '/iu';
							if (!preg_match($_pat, $_kw)) { $_ok = false; break; }
						}
						if ($_ok) $_cat_hits[] = $_kw;
					}

					$_title_ok = true;
					foreach ($_typed as $_i => $_tw)
					{
						$_pat = '/' . (($_i == $_last_i && !$next_word) ? '(^|\\s)' : '') . preg_quote($_tw, '/') . '/iu';
						if (!preg_match($_pat, $_cat_text)) { $_title_ok = false; break; }
					}
					if ($_title_ok) array_unshift($_cat_hits, $_cat_text);
					if (!$_cat_hits) $_cat_hits[] = $_cat_text;

					foreach ($_cat_hits as $_ct)
					{
						$suggestions['categories'][] = array('text' => $_ct, 'id' => $_ct);
					}
				}
			}

			if (in_array('fields', $sources_order))
			{
				// Whole values of searchable Text fields, e.g. a cyrillic name "Лаки Патчер" of an item titled "Lucky Patcher"
				$field_query = 'SELECT fv.value, COUNT(DISTINCT i.id) AS cnt'
					. ' FROM #__flexicontent_fields_item_relations AS fv'
					. ' JOIN #__flexicontent_fields AS f ON f.id = fv.field_id AND f.field_type = "text"'
					. '  AND (f.issearch = 1 OR f.isadvsearch = 1)'
					. ' JOIN #__flexicontent_items_tmp AS i ON i.id = fv.item_id'
					. $cid_join
					. ' WHERE ' . $_conds('fv.value')
					. $item_filters
					. ' GROUP BY fv.value'
					. ' ORDER BY (fv.value LIKE ' . $_text_like . ') DESC, cnt DESC'
					. ' LIMIT ' . $fields_limit;

				foreach ((array) $db->setQuery($field_query)->loadAssocList() as $_fv)
				{
					$_fv_text = trim((string) $_fv['value']);
					if (strlen($_fv_text)) $suggestions['fields'][] = array('text' => $_fv_text, 'id' => $_fv_text);
				}
			}

			if (in_array('metakeys', $sources_order))
			{
				// Meta Keywords phrases of visible items (comma separated), a phrase is suggested when it matches the typed words
				// The metakey column exists only in #__content, not in the lightweight #__flexicontent_items_tmp
				$mk_query = 'SELECT c.metakey, COUNT(DISTINCT i.id) AS cnt'
					. ' FROM #__flexicontent_items_tmp AS i'
					. ' JOIN #__content AS c ON c.id = i.id'
					. $cid_join
					. ' WHERE ' . $_conds('c.metakey')
					. $item_filters
					. ' GROUP BY c.metakey'
					. ' LIMIT 200';

				$_mk_found = array();
				foreach ((array) $db->setQuery($mk_query)->loadAssocList() as $_mk)
				{
					foreach (array_filter(array_map('trim', explode(',', $_mk['metakey']))) as $_kw)
					{
						$_ok = true;
						foreach ($_typed as $_i => $_tw)
						{
							$_pat = '/' . (($_i == $_last_i && !$next_word) ? '(^|\\s)' : '') . preg_quote($_tw, '/') . '/iu';
							if (!preg_match($_pat, $_kw)) { $_ok = false; break; }
						}
						if (!$_ok) continue;
						$_kw_low = StringHelper::strtolower($_kw, 'UTF-8');
						if (!isset($_mk_found[$_kw_low])) $_mk_found[$_kw_low] = array('text' => $_kw, 'cnt' => 0);
						$_mk_found[$_kw_low]['cnt'] += (int) $_mk['cnt'];
					}
				}

				uasort($_mk_found, function ($a, $b) { return $b['cnt'] <=> $a['cnt']; });
				foreach (array_slice($_mk_found, 0, $fields_limit) as $_f)
				{
					$suggestions['metakeys'][] = array('text' => $_f['text'], 'id' => $_f['text']);
				}
			}

			if (in_array('tags', $sources_order))
			{
				// Tags that have at least one item visible to the user, exact / starting matches first, then most used
				$tag_query = 'SELECT t.id, t.name, jt.metakey, ' . ($fa_lang_id ? 'fa.value' : '""') . ' AS fa_text, ' . ($fa_lang_id ? 'fk.value' : '""') . ' AS fa_metakey, COUNT(DISTINCT i.id) AS cnt'
					. ' FROM #__flexicontent_tags AS t'
					. ' LEFT JOIN #__tags AS jt ON jt.id = t.jtag_id'
					. ' JOIN #__flexicontent_tags_item_relations AS trel ON trel.tid = t.id'
					. ' JOIN #__flexicontent_items_tmp AS i ON i.id = trel.itemid'
					. ($fa_lang_id
						? ' LEFT JOIN #__falang_content AS fa ON fa.reference_table = "tags" AND fa.reference_field = "title"'
							. ' AND fa.reference_id = t.jtag_id AND fa.language_id = ' . $fa_lang_id
							. ' LEFT JOIN #__falang_content AS fk ON fk.reference_table = "tags" AND fk.reference_field = "metakey"'
							. ' AND fk.reference_id = t.jtag_id AND fk.language_id = ' . $fa_lang_id
						: '')
					. $cid_join
					. ' WHERE t.published = 1 AND '
					. '(' . $_conds('t.name') . ' OR ' . $_conds('jt.metakey') . ($fa_lang_id ? ' OR ' . $_conds('fa.value') . ' OR ' . $_conds('fk.value') : '') . ')'
					. $item_filters
					. ' GROUP BY t.id, t.name, jt.metakey' . ($fa_lang_id ? ', fa.value, fk.value' : '')
					. ' ORDER BY (' . ($fa_lang_id ? 'COALESCE(NULLIF(fa.value, ""), t.name)' : 't.name') . ' LIKE ' . $_text_like . ') DESC, cnt DESC'
					. ' LIMIT ' . $titles_limit;

				foreach ((array) $db->setQuery($tag_query)->loadAssocList() as $_t)
				{
					// Show the translated title when there is one, otherwise the original name
					$_tag_text = strlen(trim((string) $_t['fa_text'])) ? $_t['fa_text'] : $_t['name'];
					$_tag_hits = array();

					// Tag keywords (Meta Keywords of the Joomla tag, and their translation) that match the typed text are suggested too
					foreach (array_filter(array_map('trim', explode(',', $_t['metakey'] . ',' . $_t['fa_metakey']))) as $_kw)
					{
						$_ok = true;
						foreach ($_typed as $_i => $_tw)
						{
							$_pat = '/' . (($_i == $_last_i && !$next_word) ? '(^|\\s)' : '') . preg_quote($_tw, '/') . '/iu';
							if (!preg_match($_pat, $_kw)) { $_ok = false; break; }
						}
						if ($_ok) $_tag_hits[] = $_kw;
					}

					$_title_ok = true;
					foreach ($_typed as $_i => $_tw)
					{
						$_pat = '/' . (($_i == $_last_i && !$next_word) ? '(^|\\s)' : '') . preg_quote($_tw, '/') . '/iu';
						if (!preg_match($_pat, $_tag_text)) { $_title_ok = false; break; }
					}
					if ($_title_ok) array_unshift($_tag_hits, $_tag_text);
					if (!$_tag_hits) $_tag_hits[] = $_tag_text;

					foreach ($_tag_hits as $_tt)
					{
						$suggestions['tags'][] = array('text' => $_tt, 'id' => $_tt);
					}
				}
			}

			// Enrich item-title suggestions with image, display title and configured field values
			if ($ac_rich && !empty($suggestions['titles']) && in_array('titles', $sources_order, true))
			{
				$this->_enrichTitleSuggestions($suggestions['titles'], array(
					'image_source' => $ac_image_source,
					'image_field'  => $ac_image_field,
					'image_width'  => $ac_image_width,
					'image_height' => $ac_image_height,
					'image_method' => $ac_image_method,
					'title_maxlen' => $ac_title_maxlen,
					'info_fields'  => $ac_info_fields,
					'info_source'  => $ac_info_source,
					'info_labels'  => $ac_info_labels,
					'info_maxlen'  => $ac_info_maxlen,
				));
			}

			// Merge the sources in the configured order, a suggestion already listed by an earlier source is skipped
			$seen = array();
			$options['Matches'] = array();
			foreach ($sources_order as $_src)
			{
				foreach ($suggestions[$_src] as $_m)
				{
					$_low = StringHelper::strtolower($_m['text'], 'UTF-8');
					if (isset($seen[$_low])) continue;
					$seen[$_low] = 1;
					$options['Matches'][] = $_m;
				}
			}

			$options['Total'] = count($options['Matches']);
		}

		// Internal fields must not be exposed to the client
		foreach ($options['Matches'] as &$_match)
		{
			unset($_match['_item_id']);
		}
		unset($_match);

		header('Content-Type: application/json; charset=utf-8');
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


	/**
	 * Add image, shortened title and configured extra field values to item-title suggestions
	 */
	private function _enrichTitleSuggestions(&$titles, $opts)
	{
		$db = $this->_getDbo();

		$opts += array(
			'image_source' => 'auto',
			'image_field'  => '',
			'image_width'  => 60,
			'image_height' => 60,
			'image_method' => 1,
			'title_maxlen' => 60,
			'info_fields'  => array(),
			'info_source'  => 'module',
			'info_labels'  => 1,
			'info_maxlen'  => 80,
		);

		$ids = array();
		foreach ($titles as $_t)
		{
			$_id = isset($_t['_item_id']) ? (int) $_t['_item_id'] : 0;
			if ($_id > 0)
			{
				$ids[$_id] = $_id;
			}
		}

		if (!$ids)
		{
			return;
		}

		$ids_list = implode(',', $ids);

		$query = 'SELECT i.id, i.title, i.type_id, i.catid, i.lang_parent_id, i.language, c.introtext, c.`fulltext`'
			. ' FROM #__flexicontent_items_tmp AS i'
			. ' LEFT JOIN #__content AS c ON c.id = i.id'
			. ' WHERE i.id IN (' . $ids_list . ')';

		$items = array();
		foreach ((array) $db->setQuery($query)->loadObjectList() as $_row)
		{
			$items[(int) $_row->id] = $_row;
		}

		if (!$items)
		{
			return;
		}

		// Item id used for field values, the language parent when the field is not translatable
		$uids = array();
		foreach ($items as $_row)
		{
			$_uid = !empty($_row->lang_parent_id) ? (int) $_row->lang_parent_id : (int) $_row->id;
			$uids[$_uid] = $_uid;
		}

		// Content type settings (Content type -> Search view), used when the source is 'type'
		$type_params = array();

		if ($opts['image_source'] === 'type' || $opts['info_source'] === 'type')
		{
			$type_ids = array();
			foreach ($items as $_row)
			{
				if ((int) $_row->type_id > 0)
				{
					$type_ids[(int) $_row->type_id] = (int) $_row->type_id;
				}
			}

			if ($type_ids)
			{
				$query = 'SELECT id, attribs FROM #__flexicontent_types WHERE id IN (' . implode(',', $type_ids) . ')';

				foreach ((array) $db->setQuery($query)->loadObjectList() as $_type)
				{
					$type_params[(int) $_type->id] = new \Joomla\Registry\Registry($_type->attribs);
				}
			}
		}

		// Sanitize a comma separated list of field names
		$clean_names = function ($list)
		{
			$names = array();

			foreach (preg_split('/\s*,\s*/', trim((string) $list)) as $_name)
			{
				$_name = strtolower(trim($_name));

				if (preg_match('/^[a-z0-9_-]{1,100}$/', $_name) && !in_array($_name, $names, true))
				{
					$names[] = $_name;
				}

				if (count($names) >= 12)
				{
					break;
				}
			}

			return $names;
		};

		// Image field and extra fields of every item, from the module or from the content type of the item
		$item_image_field = array();
		$item_info_fields = array();
		$all_info_fields  = array();

		foreach ($items as $_iid => $_row)
		{
			$_tp = isset($type_params[(int) $_row->type_id]) ? $type_params[(int) $_row->type_id] : null;

			$_img = '';
			if ($opts['image_source'] === 'field')
			{
				$_img = $opts['image_field'];
			}
			elseif ($opts['image_source'] === 'type' && $_tp)
			{
				$_names = $clean_names($_tp->get('search_image', ''));
				$_img = $_names ? $_names[0] : '';
			}
			$item_image_field[$_iid] = $_img;

			$item_info_fields[$_iid] = $opts['info_source'] === 'type'
				? ($_tp ? $clean_names($_tp->get('search_infoflds', '')) : array())
				: $opts['info_fields'];

			$all_info_fields = array_merge($all_info_fields, $item_info_fields[$_iid]);
		}

		$all_info_fields = array_values(array_unique($all_info_fields));

		// Query both the item id and its language parent, non-translatable fields are stored on the parent
		$info = !empty($all_info_fields)
			? $this->_getInfoFieldEntries($ids + $uids, $all_info_fields, $opts['info_maxlen'], $opts['info_labels'])
			: array();

		// Load every needed image field once (name => array(field, params, values), or null when not found)
		$image_fields = array();

		foreach (array_unique(array_filter($item_image_field)) as $_img_name)
		{
			$image_fields[$_img_name] = null;
			$_field = $this->_getImageField($_img_name);

			if ($_field)
			{
				$image_fields[$_img_name] = array(
					'field'  => $_field,
					'params' => $this->_decodeFieldParams($_field),
					'values' => $this->_getImageFieldValues($_field, $ids + $uids),
				);
			}
		}

		foreach ($titles as &$_t)
		{
			$_id = isset($_t['_item_id']) ? (int) $_t['_item_id'] : 0;

			if (!isset($items[$_id]))
			{
				continue;
			}

			$_row = $items[$_id];
			$_uid = !empty($_row->lang_parent_id) ? (int) $_row->lang_parent_id : (int) $_row->id;

			$_t['title_display'] = $this->_acPlainText($_row->title, $opts['title_maxlen']);

			$_image = '';

			$_ifd = !empty($item_image_field[$_id]) && !empty($image_fields[$item_image_field[$_id]])
				? $image_fields[$item_image_field[$_id]]
				: null;

			if ($_ifd)
			{
				// Prefer the image stored on the item itself (translatable field), then its language parent
				if (isset($_ifd['values'][$_id]))
				{
					$_image = $this->_imageFieldSrc($_ifd['field'], $_ifd['params'], $_id, $_ifd['values'][$_id]);
				}

				if (!$_image && $_uid !== $_id && isset($_ifd['values'][$_uid]))
				{
					$_image = $this->_imageFieldSrc($_ifd['field'], $_ifd['params'], $_uid, $_ifd['values'][$_uid]);
				}
			}

			if (!$_image)
			{
				$_image = $this->_extractTextImage((isset($_row->introtext) ? $_row->introtext : '') . ' ' . (isset($_row->fulltext) ? $_row->fulltext : ''));
			}

			if ($_image)
			{
				$_t['image'] = $this->_acThumbUrl($_image, $opts['image_width'], $opts['image_height'], $opts['image_method']);
			}

			$_by_field = array();

			foreach ((!empty($info[$_id]) ? $info[$_id] : array()) as $_entry)
			{
				if (isset($_entry['_field']))
				{
					$_by_field[$_entry['_field']] = $_entry;
				}
			}

			// Only use the parent value when the item has no value for that field
			if ($_uid !== $_id && !empty($info[$_uid]))
			{
				foreach ($info[$_uid] as $_entry)
				{
					if (isset($_entry['_field']) && !isset($_by_field[$_entry['_field']]))
					{
						$_by_field[$_entry['_field']] = $_entry;
					}
				}
			}

			$_entries = array();

			foreach ($item_info_fields[$_id] as $_field_name)
			{
				if (isset($_by_field[$_field_name]))
				{
					$_entries[] = $_by_field[$_field_name];
				}
			}

			foreach ($_entries as &$_entry)
			{
				unset($_entry['_field']);
			}
			unset($_entry);

			if (!empty($_entries))
			{
				$_t['fields'] = $_entries;
			}
		}
		unset($_t);
	}


	/**
	 * Load the metadata of published fields, indexed by their (lowercase) name
	 */
	private function _getFieldsByName($names)
	{
		$db = $this->_getDbo();
		$quoted = array();

		foreach ($names as $_name)
		{
			$quoted[] = $db->Quote(strtolower($_name));
		}

		if (!$quoted)
		{
			return array();
		}

		$query = 'SELECT id, name, label, field_type, attribs'
			. ' FROM #__flexicontent_fields'
			. ' WHERE published = 1 AND LOWER(name) IN (' . implode(',', $quoted) . ')'
			. ' AND access IN (' . $this->_viewLevelsList() . ')';

		$fields = array();
		foreach ((array) $db->setQuery($query)->loadObjectList() as $_field)
		{
			$fields[strtolower($_field->name)] = $_field;
		}

		return $fields;
	}


	/**
	 * Load a published image field by name
	 */
	private function _getImageField($name)
	{
		$db = $this->_getDbo();

		$query = 'SELECT id, name, label, field_type, attribs'
			. ' FROM #__flexicontent_fields'
			. ' WHERE published = 1 AND field_type = ' . $db->Quote('image')
			. ' AND LOWER(name) = ' . $db->Quote(strtolower($name))
			. ' AND access IN (' . $this->_viewLevelsList() . ')';

		return $db->setQuery($query, 0, 1)->loadObject();
	}


	/**
	 * Decode the JSON parameters of a field
	 */
	private function _decodeFieldParams($field)
	{
		$params = json_decode((string) $field->attribs, true);

		return is_array($params) ? $params : array();
	}


	/**
	 * First stored value of an image field for every item
	 */
	private function _getImageFieldValues($field, $item_ids)
	{
		if (!$item_ids)
		{
			return array();
		}

		$db = $this->_getDbo();
		$query = 'SELECT item_id, value'
			. ' FROM #__flexicontent_fields_item_relations'
			. ' WHERE field_id = ' . (int) $field->id
			. ' AND item_id IN (' . implode(',', $item_ids) . ')'
			. ' ORDER BY item_id, valueorder, suborder';

		$values = array();
		foreach ((array) $db->setQuery($query)->loadObjectList() as $_row)
		{
			$item_id = (int) $_row->item_id;

			if (!isset($values[$item_id]) || $values[$item_id] === '')
			{
				$values[$item_id] = $_row->value;
			}
		}

		return $values;
	}


	/**
	 * Plain-text values of the configured extra fields for each item
	 */
	private function _getInfoFieldEntries($item_ids, $names, $maxlen, $show_labels)
	{
		$fields = $this->_getFieldsByName($names);

		if (!$fields || !$item_ids)
		{
			return array();
		}

		$db = $this->_getDbo();
		$field_ids = array();

		foreach ($fields as $_field)
		{
			$field_ids[] = (int) $_field->id;
		}

		$query = 'SELECT field_id, item_id, value'
			. ' FROM #__flexicontent_fields_item_relations'
			. ' WHERE field_id IN (' . implode(',', $field_ids) . ')'
			. ' AND item_id IN (' . implode(',', $item_ids) . ')'
			. ' ORDER BY item_id, field_id, valueorder, suborder';

		$values = array();
		foreach ((array) $db->setQuery($query)->loadObjectList() as $_row)
		{
			$item_id = (int) $_row->item_id;
			$field_id = (int) $_row->field_id;

			if (!isset($values[$item_id][$field_id]) || $values[$item_id][$field_id] === '')
			{
				$values[$item_id][$field_id] = $_row->value;
			}
		}

		$entries = array();
		foreach ($item_ids as $_item_id)
		{
			foreach ($names as $_name)
			{
				if (!isset($fields[$_name]))
				{
					continue;
				}

				$_field = $fields[$_name];
				$_field_id = (int) $_field->id;

				if (!isset($values[$_item_id][$_field_id]))
				{
					continue;
				}

				$_text = $this->_acFieldValueText($_field, $values[$_item_id][$_field_id], $maxlen);

				if ($_text === '')
				{
					continue;
				}

				$_entry = array('value' => $_text, '_field' => strtolower((string) $_field->name));

				if ($show_labels)
				{
					$_label = trim((string) $_field->label);
					$_label = $_label !== '' ? \Joomla\CMS\Language\Text::_($_label) : '';
					$_entry['label'] = $_label !== '' ? $_label : $_field->name;
				}

				$entries[$_item_id][] = $_entry;

				if (count($entries[$_item_id]) >= 12)
				{
					break;
				}
			}
		}

		return $entries;
	}


	/**
	 * Convert a stored field value to a short plain-text value
	 */
	private function _acFieldValueText($field, $raw, $maxlen)
	{
		$params = $this->_decodeFieldParams($field);
		$value  = $this->_acDecodeValue($raw);
		$type   = (string) $field->field_type;

		if (is_object($value))
		{
			return '';
		}

		if ($type === 'addressint' && is_array($value))
		{
			$value = !empty($value['addr_display']) ? $value['addr_display']
				: (!empty($value['addr_formatted']) ? $value['addr_formatted']
				: (!empty($value['addr']) ? $value['addr'] : ''));
		}
		elseif (in_array($type, array('image', 'image_gallery', 'sharedmedia'), true) && is_array($value))
		{
			$_array = $value;
			$value = !empty($_array['alt']) ? $_array['alt']
				: (!empty($_array['title']) ? $_array['title']
				: (!empty($_array['desc']) ? $_array['desc']
				: (!empty($_array['url']) ? $_array['url']
				: (!empty($_array['originalname']) ? basename($_array['originalname']) : ''))));
		}
		elseif (in_array($type, array('date', 'created', 'modified', 'datefield'), true))
		{
			if (is_array($value))
			{
				$value = reset($value);
			}

			$value = trim((string) $value);

			if ($value !== '' && $value !== '0000-00-00' && $value !== '0000-00-00 00:00:00')
			{
				$value = class_exists('\\Joomla\\CMS\\HTML\\HTMLHelper')
					? \Joomla\CMS\HTML\HTMLHelper::_('date', $value, 'Y-m-d')
					: substr($value, 0, 10);
			}
		}
		elseif (in_array($type, array('select', 'radio', 'selectmultiple', 'checkbox', 'checkboximage', 'radioimage', 'selectmultipleimage'), true))
		{
			$map = $this->_acOptionMap($params);
			$texts = array();

			foreach ((array) $value as $_v)
			{
				if (is_object($_v))
				{
					continue;
				}

				if (is_array($_v))
				{
					$_v = $this->_acFlattenText($_v);
				}

				$_v = trim((string) $_v);

				if ($_v !== '')
				{
					$texts[] = isset($map[$_v]) ? $map[$_v] : $_v;
				}
			}

			$value = implode(', ', $texts);
		}
		else
		{
			$value = $this->_acFlattenText($value);
		}

		if (is_array($value))
		{
			$value = $this->_acFlattenText($value);
		}

		return $this->_acPlainText($value, $maxlen);
	}


	/**
	 * Decode serialized or JSON encoded field values
	 */
	private function _acDecodeValue($raw)
	{
		if (!is_string($raw))
		{
			return $raw;
		}

		$raw = trim($raw);

		if ($raw === '')
		{
			return '';
		}

		if (preg_match('/^(?:[aOsibdN]:|N;$)/', $raw))
		{
			$value = @unserialize($raw, array('allowed_classes' => false));

			if ($value !== false || $raw === 'b:0;')
			{
				return $value;
			}
		}

		if (($raw[0] === '{' && substr($raw, -1) === '}') || ($raw[0] === '[' && substr($raw, -1) === ']'))
		{
			$value = json_decode($raw, true);

			if (json_last_error() === JSON_ERROR_NONE)
			{
				return $value;
			}
		}

		return $raw;
	}


	/**
	 * Option value => option text map of select / radio / checkbox fields
	 */
	private function _acOptionMap($params)
	{
		$elements = isset($params['field_elements']) ? (string) $params['field_elements'] : '';

		if ($elements === '')
		{
			return array();
		}

		$_parts = preg_split('/%%\s*/', $elements);

		if (count($_parts) === 1 && strpos($elements, "\n") !== false)
		{
			$_parts = preg_split('/\r?\n/', $elements);
		}

		$map = array();
		foreach ($_parts as $_part)
		{
			$_part = trim($_part);

			if ($_part === '')
			{
				continue;
			}

			$_props = preg_split('/[\s]*::[\s]*/', $_part);
			$_key = trim((string) $_props[0]);
			$_val = isset($_props[1]) ? trim((string) $_props[1]) : $_key;

			if ($_key !== '')
			{
				$_label = $_val !== '' ? \Joomla\CMS\Language\Text::_($_val) : $_key;
				$map[$_key] = $_label !== '' ? $_label : $_key;
			}
		}

		return $map;
	}


	/**
	 * Flatten a decoded field value to a plain text string
	 */
	private function _acFlattenText($value)
	{
		if (is_object($value))
		{
			return '';
		}

		if (!is_array($value))
		{
			return (string) $value;
		}

		$parts = array();
		foreach ($value as $_v)
		{
			if (is_object($_v))
			{
				continue;
			}

			if (is_array($_v))
			{
				$_v = $this->_acFlattenText($_v);
			}

			$_v = trim((string) $_v);

			if ($_v !== '')
			{
				$parts[] = $_v;
			}
		}

		return implode(', ', $parts);
	}


	/**
	 * Shorten a value and remove any HTML from it
	 */
	private function _acPlainText($text, $maxlen)
	{
		$text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = strip_tags($text);
		$text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text);
		$text = preg_replace('/\s+/u', ' ', $text);
		$text = trim($text);

		$maxlen = (int) $maxlen;

		if ($maxlen > 0 && StringHelper::strlen($text) > $maxlen)
		{
			$text = StringHelper::substr($text, 0, $maxlen);
			$space = StringHelper::strrpos($text, ' ');

			if ($space !== false && $space > (int) ($maxlen * 0.6))
			{
				$text = StringHelper::substr($text, 0, $space);
			}

			$text = rtrim($text) . '…';
		}

		return $text;
	}


	/**
	 * First image found inside the item text
	 */
	private function _extractTextImage($text)
	{
		if (!preg_match_all('/<img\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1/is', (string) $text, $_matches))
		{
			return '';
		}

		foreach ($_matches[2] as $_src)
		{
			$_src = trim(html_entity_decode($_src, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

			if ($_src === '' || preg_match('/^data:/i', $_src))
			{
				continue;
			}

			if (preg_match('#^(?:[a-z]+:)?//#i', $_src))
			{
				return $_src;
			}

			$_rel = rawurldecode(ltrim(preg_replace('/[?#].*$/', '', $_src), '/'));

			if ($_rel !== '' && !preg_match('#(^|/)\.\.(/|$)#', $_rel) && is_file(JPATH_SITE . '/' . $_rel))
			{
				return $this->_acRootUrl() . '/' . str_replace(' ', '%20', $_rel);
			}
		}

		return '';
	}


	/**
	 * Thumbnail URL of the first value of an image field
	 */
	private function _imageFieldSrc($field, $params, $item_id, $raw)
	{
		$value = $this->_acDecodeValue($raw);

		if (!is_array($value))
		{
			$value = array('originalname' => (string) $raw);
		}

		$default_image = isset($params['default_image']) ? (string) $params['default_image'] : '';
		$default_image = trim(explode('#', $default_image)[0]);

		$image_source = isset($params['image_source']) ? (int) $params['image_source'] : 0;
		$image_source = $image_source > 1 ? 0 : $image_source;

		// Intro-full image mode stores the site-relative image path, not just the file name
		$image_subpath = $image_source === -1 && !empty($value['image_path'])
			? trim((string) $value['image_path'])
			: (isset($value['originalname']) ? trim((string) $value['originalname']) : '');
		$image_subpath = trim(explode('#', $image_subpath)[0]);
		$using_default = false;

		if ($image_subpath === '')
		{
			$image_subpath = $default_image;
			$using_default = true;
		}

		if ($image_subpath === '' || preg_match('#(^|/)\.\.(/|$)#', $image_subpath))
		{
			return '';
		}

		if (preg_match('#^(?:[a-z]+:)?//#i', $image_subpath))
		{
			return $image_subpath;
		}

		$subpath = str_replace('\\', '/', dirname($image_subpath));
		$subpath = ($subpath === '.') ? '' : trim($subpath, '/');
		$image_name = basename(!empty($value['originalname']) ? (string) $value['originalname'] : $image_subpath);

		$dir = isset($params['dir']) ? trim(str_replace('\\', '/', (string) $params['dir']), '/') : 'images/stories/flexicontent';

		if ($dir === '' || preg_match('#(^|/)\.\.(/|$)#', $dir))
		{
			return '';
		}

		$extra_folder = '';

		if ($using_default)
		{
			$extra_folder = '';
		}
		elseif ($image_source === -1)
		{
			$extra_folder = 'intro_full';
		}
		elseif ($image_source === -2)
		{
			$extra_folder = 'mediaman';
		}
		elseif ($image_source >= 1)
		{
			$extra_folder = 'item_' . (int) $item_id . '_field_' . (int) $field->id;
		}

		$thumb_dir = $dir . ($extra_folder ? '/' . $extra_folder : '');
		$multiple  = $using_default
			|| ($image_source === 0 && !empty($params['list_all_media_files']) && empty($params['unique_thumb_method']))
			|| !empty($value['default_image']);
		$extra_prefix = $multiple ? 'fld' . (int) $field->id . '_' : '';

		$candidates = array(
			$thumb_dir . '/m_' . $extra_prefix . $image_name,
			$thumb_dir . '/s_' . $extra_prefix . $image_name,
		);

		// Original image paths
		if ($using_default)
		{
			$candidates[] = ($subpath ? $subpath . '/' : '') . $image_name;
		}
		elseif ($image_source === -1)
		{
			$candidates[] = ($subpath ? $subpath . '/' : '') . $image_name;
		}
		elseif ($image_source === -2)
		{
			$candidates[] = ($subpath ? $subpath . '/' : '') . $image_name;
		}
		elseif ($image_source >= 1)
		{
			$candidates[] = $thumb_dir . '/original/' . ($subpath ? $subpath . '/' : '') . $image_name;
		}
		else
		{
			static $com_params = null;

			if ($com_params === null)
			{
				$com_params = class_exists('\\Joomla\\CMS\\Component\\ComponentHelper')
					? \Joomla\CMS\Component\ComponentHelper::getParams($this->option)
					: JComponentHelper::getParams($this->option);
			}

			$target_dir = isset($params['target_dir']) ? (int) $params['target_dir'] : 1;
			$base = $target_dir
				? $com_params->get('file_path', 'components/com_flexicontent/uploads')
				: $com_params->get('media_path', 'components/com_flexicontent/medias');
			$base = trim(str_replace('\\', '/', (string) $base), '/');
			$candidates[] = $base . '/' . ($subpath ? $subpath . '/' : '') . $image_name;
		}

		// The stored path itself may be relative to the site root (media manager / intro-full image)
		$candidates[] = ltrim($image_subpath, '/');

		if (!empty($value['default_image']))
		{
			$candidates[] = ltrim(explode('#', (string) $value['default_image'])[0], '/');
		}

		foreach ($candidates as $_rel)
		{
			$_rel = ltrim((string) $_rel, '/');

			if ($_rel !== '' && is_file(JPATH_SITE . '/' . $_rel))
			{
				return $this->_acRootUrl() . '/' . str_replace(' ', '%20', $_rel);
			}
		}

		return '';
	}


	private function _acRootUrl()
	{
		$root = '';

		if (class_exists('\\Joomla\\CMS\\Uri\\Uri'))
		{
			$root = \Joomla\CMS\Uri\Uri::root(true);
		}
		elseif (class_exists('JUri'))
		{
			$root = JUri::root(true);
		}

		$root = rtrim($root, '/');

		/*
		 * This endpoint is called directly (components/com_flexicontent/tasks/core.php),
		 * so Joomla derives the base path from SCRIPT_NAME and returns the "tasks" folder
		 * instead of the site root. Strip it so image / phpThumb URLs stay correct.
		 */
		$endpoint_path = '/components/com_flexicontent/tasks';

		if ($root !== '' && substr($root, -strlen($endpoint_path)) === $endpoint_path)
		{
			$root = rtrim(substr($root, 0, -strlen($endpoint_path)), '/');
		}

		return $root;
	}


	/**
	 * Build a phpThumb URL for a local image, external images are returned unchanged
	 */
	private function _acThumbUrl($src, $width, $height, $method)
	{
		$src = trim((string) $src);

		if ($src === '' || preg_match('/^data:/i', $src))
		{
			return $src;
		}

		// External images are kept as-is
		if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $src) || preg_match('#^//#', $src))
		{
			return $src;
		}

		$root = $this->_acRootUrl();
		$rel  = $src;

		if ($root !== '' && strpos($rel, $root . '/') === 0)
		{
			$rel = substr($rel, strlen($root) + 1);
		}

		$rel = ltrim($rel, '/');

		if ($rel === '')
		{
			return $src;
		}

		$ext  = strtolower(pathinfo(parse_url($rel, PHP_URL_PATH), PATHINFO_EXTENSION));
		$file = in_array($ext, array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico'), true) ? '&f=' . $ext : '';

		return $root . '/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='
			. $root . '/' . $rel
			. '&w=' . max(16, (int) $width)
			. '&h=' . max(16, (int) $height)
			. '&aoe=1&q=95&ar=x'
			. ($method ? '&zc=1' : '')
			. $file;
	}


	// Private & Compatibility Helpers
	private function _isStopWord($word, $tbl = 'flexicontent_items_ext', $col = 'search_index', $search_prefix = '')
	{
		$app = $this->_getApp();
		$jinput = $app->input;
		if ($jinput->get('task', '', 'cmd') == __FUNCTION__) die(__FUNCTION__ . ' : direct call not allowed');

		// A word that cannot be found in the index (ignored by the fulltext index) is a stop word or too short
		$db = $this->_getDbo();
		$quoted_word = $db->escape($search_prefix . $word, true);
		$query = 'SELECT ' . $col
			. ' FROM #__' . $tbl
			. ' WHERE MATCH (' . $col . ') AGAINST ("+' . $quoted_word . '" IN BOOLEAN MODE)'
			. ' LIMIT 1';
		$result = $db->setQuery($query)->loadAssocList();

		return empty($result);
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

	/**
	 * View access levels of the current user as a comma separated list, for field access checks
	 * (field names of rich suggestions come from the request, so field view access must be enforced here)
	 */
	private function _viewLevelsList()
	{
		$app    = $this->_getApp();
		$user   = method_exists($app, 'getIdentity') ? $app->getIdentity() : \Joomla\CMS\Factory::getUser();
		$levels = $user ? array_map('intval', $user->getAuthorisedViewLevels()) : array();

		return $levels ? implode(',', $levels) : '0';
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
