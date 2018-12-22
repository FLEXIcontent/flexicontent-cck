<?php
use Joomla\String\StringHelper;
$task = new FlexicontentTasksCore();

class FlexicontentTasksCore
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

		file_exists('defines.php')  ?  require_once 'defines.php'  :   define('JPATH_BASE', realpath(__DIR__.'/../../..'));
		require_once JPATH_BASE . '/includes/defines.php';
		require_once JPATH_BASE . '/includes/framework.php';

		$is_admin = preg_match('/\/administrator\//', $_SERVER['HTTP_REFERER']);

		// Instantiate the application.
		$app = JFactory::getApplication($is_admin ? 'administrator' : 'site');
		$app->initialise();

		// Call the task
		$jinput = $app->input;
		$task   = $jinput->get('task', '', 'cmd');
		$this->$task();

		//$diff = round(1000000 * 10 * (microtime(true) - $start_time)) / 10;  echo sprintf('<br/>Time: %.3f s<br/>', $diff/1000000);  echo sprintf('<br/>Time: %.3f s<br/>', memory_get_usage() - $start_mem);
	}


	/**
	 * Logic to get text search autocomplete strings
	 *
	 * @access public
	 * @return void
	 * @since 3.1.2
	 */
	function txtautocomplete()
	{
		// Call plugin, (e.g. to load category data)
		$this->_callPlugins();
		global $globalcats;

		$app     = JFactory::getApplication();
		$jinput  = $app->input;
		$cparams = JComponentHelper::getParams($this->option);
		$use_tmp = true;

		// Get request variables
		$type    = $jinput->get('type', '', 'cmd');
		$text    = $jinput->get('text', '', 'string');
		$lang    = $jinput->get('lang', '', 'cmd');
		$lang    = substr($lang, 0,2);

		$pageSize = $jinput->get('pageSize', 20, 'int');
		$pageNum  = $jinput->get('pageNum', 1, 'int');		
		$usesubs  = $jinput->get('usesubs', 1, 'int');		

		$min_word_len = $app->getUserState( $this->option.'.min_word_len', 0 );
		$filtercat    = $cparams->get('filtercat', 0);      // Filter items using currently selected language
		$show_noauth  = $cparams->get('show_noauth', 0);   // Show unauthorized items

		// Get category ID(s)
		$cid  = $jinput->get('cid', 0, 'int');
		$cids = $jinput->get('cids', '', 'string');
		
		// CASE 1: Single category view, zero or string means ignore and use 'cids'
		if ($cid)
		{
			$_cids = array($cid);
		}
		
		// CASE 2: Multi category view
		elseif (!empty($cids))
		{
			if (!is_array($cids))
			{
				$_cids = preg_replace( '/[^0-9,]/i', '', (string) $cids );
				$_cids = explode(',', $_cids);
			}
			else
			{
				$_cids = $cids;
			}
		}

		// No category id was given
		else
		{
			$_cids = array();
		}


		// Make sure given data are integers ...
		$cids = array();

		foreach ($_cids as $i => $_id)
		{
			if ((int) $_id)
			{
				$cids[] = (int) $_id;
			}
		}

		// Sub - cats
		if ($usesubs)
		{
			// Find descendants of the categories
			$subcats = array();
			foreach ($cids as $_id)
			{
				if ( !isset($globalcats[$_id]) ) continue;
				$subcats = array_merge($subcats, $globalcats[$_id]->descendantsarray);
			}
			$cids = array_unique($subcats);
		}

		$cid_list = implode(',', $cids);

		// Nothing to do
		if ( $type!='basic_index' && $type!='adv_index' ) jexit();
		if ( !strlen($text) ) jexit();


		// All starting words are exact words but last word is a ... word prefix
		$search_prefix = $cparams->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix
		$words = preg_split('/\s\s*/u', $text);

		$_words = array();
		foreach ($words as & $_w)
		{
			$_words[] = !$search_prefix  ?  trim($_w)  :  preg_replace('/(\b[^\s,\.]+\b)/u', $search_prefix.'$0', trim($_w));
		}
		$newtext = '+' . implode( ' +', $_words ) .'*';  //print_r($_words); exit;

		// Query CLAUSE for match the given text
		$db = JFactory::getDbo();
		$quoted_text = $db->escape($newtext, true);
		$quoted_text = $db->Quote( $quoted_text, false );
		$_text_match  = ' MATCH (si.search_index) AGAINST ('.$quoted_text.' IN BOOLEAN MODE) ';

		// Query retieval limits
		$limitstart = $pageSize * ($pageNum - 1);
		$limit      = $pageSize;

		$lang_where = '';

		if ($filtercat)
		{
			$lta = 'i';
			//$lang_where .= ' AND ( '.$lta.'.language LIKE ' . $db->Quote( $lang .'%' ) . ' OR '.$lta.'.language="*" ) ';
			$lang_where .= ' AND (' . $lta . ' .language = ' . $db->Quote(JFactory::getLanguage()->getTag()) . ' OR ' . $lta . '.language = ' . $db->Quote('*') . ')';
		}

		$access_where = '';
		$joinaccess = '';

		/*if (!$show_noauth)
		{
			$user = JFactory::getUser();
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$access_where .= ' AND ty.access IN (0,'.$aid_list.')';
			$access_where .= ' AND mc.access IN (0,'.$aid_list.')';
			$access_where .= ' AND  i.access IN (0,'.$aid_list.')';
		}*/

		// Dates for publish up / down
		$_nowDate = 'UTC_TIMESTAMP()'; //$db->Quote($now);
		$nullDate = $db->getNullDate();

		// Do query ...
		$tbl = $type=='basic_index' ? 'flexicontent_items_ext' : 'flexicontent_advsearch_index';
		$query 	= 'SELECT si.item_id, si.search_index'    //.', '. $_text_match. ' AS score'  // THIS MAYBE SLOW
			.' FROM #__' . $tbl . ' AS si'
			.' JOIN '. ($use_tmp ? '#__flexicontent_items_tmp' : '#__content') .' AS i ON i.id = si.item_id'
			.(($access_where && !$use_tmp) || ($filtercat && !$use_tmp) || $type !== 'basic_index' ?
				' JOIN #__flexicontent_items_ext AS ie ON i.id = ie.item_id ' : '')
			.($access_where ? ' JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id' : '')
			.($access_where ? ' JOIN #__categories AS mc ON mc.id = i.catid' : '')
			.($cid_list ? ' JOIN #__flexicontent_cats_item_relations AS rel ON i.id = rel.itemid AND rel.catid IN ('.$cid_list.')' : '')
			.$joinaccess
			.' WHERE '. $_text_match
			.'   AND i.state IN (1,-5) '   //(FLEXI_J16GE ? 2:-1) // TODO search archived
			.'   AND ( i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= '.$_nowDate.' ) '
			.'   AND ( i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= '.$_nowDate.' ) '
			. $lang_where
			. $access_where
			//.' ORDER BY score DESC'  // THIS MAYBE SLOW
			.' LIMIT '.$limitstart.', '.$limit
			;
		$data = $db->setQuery($query)->loadAssocList();
		//print_r($data); exit;

		// Get last word (this is a word prefix) and remove it from words array
		$word_prefix = array_pop($words);

		// Reconstruct search text with complete words (not including last)
		$complete_words = implode(' ', $words);

		// Find out the words that matched
		$words_found = array();
		$regex = '/(\b)('.$search_prefix.$word_prefix.'\w*)(\b)/iu';

		foreach ($data as $_d)
		{
			//echo $_d['item_id'] . ' ';
			if (preg_match_all($regex, $_d['search_index'], $matches) )
			{
				//print_r($matches[2]); exit;
				foreach ($matches[2] as $_m)
				{
					if ($search_prefix)
					{
						$_m = preg_replace('/\b'.$search_prefix.'/u', '', $_m);
					}
					$_m_low = StringHelper::strtolower($_m, 'UTF-8');
					$words_found[$_m_low] = 1;
				}
			}
		}
		//print_r($words_found); exit;

		// Pagination not appropriate when using autocomplete ...
		$options = array();
		$options['Total'] = count($words_found);

		// Create responce and JSON encode it
		$options['Matches'] = array();
		$n = 0;
		foreach ($words_found as $_w => $i)
		{
			if (!$search_prefix)
			{
				// Word too short
				if (StringHelper::strlen($_w) < $min_word_len )
				{
					continue;
				}

				// Stopword or too common
				if ($this->_isStopWord($_w, $tbl))
				{
					continue;
				}
			}

			$options['Matches'][] = array(
				'text' => $complete_words.($complete_words ? ' ' : '').$_w,
				'id' => $complete_words.($complete_words ? ' ' : '').$_w
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
	 * Method to fetch the tags for selecting in item form
	 *
	 * @since 1.5
	 */
	public function viewtags()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		require_once JPath::clean(JPATH_BASE . '/../components/com_flexicontent/helpers/permission.php');

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
			$this->_loadLanguage();
			$array[] = (object) array(
				'id' => '0',
				'name' => JText::_('FLEXI_FIELD_NO_ACCESS')
			);
		}
		else
		{
			// If (search) text is just whitespace then set limit to a much higher value
			$q = $app->input->get('q', '', 'string');
			$limit = $q && !trim($q) ? 10000 : 500;

			$tagobjs = $this->_gettags($q, $limit);

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
				$this->_loadLanguage();
				$array[] = (object) array(
					'id' => '0',
					'name' => JText::_($perms->CanCreateTags ? 'FLEXI_NEW_TAG_ENTER_TO_CREATE' : 'FLEXI_NO_TAGS_FOUND')
				);
			}
		}

		jexit(json_encode($array/*, JSON_UNESCAPED_UNICODE*/));
	}


	// ***
	// *** Helper methods
	// ***

	private function _isStopWord($word, $tbl='flexicontent_items_ext', $col='search_index')
	{
		$app     = JFactory::getApplication();
		$jinput  = $app->input;
		if ($jinput->get('task', '', 'cmd') == __FUNCTION__) die(__FUNCTION__ . ' : direct call not allowed');

		$db = JFactory::getDbo();
		$quoted_word = $db->escape($word, true);
		$query = 'SELECT '.$col
			.' FROM #__'.$tbl
			.' WHERE MATCH ('.$col.') AGAINST ("+'.$quoted_word.'")'
			.' LIMIT 1';
		$result = $db->setQuery($query)->loadAssocList();

		return !empty($return) ? true : false;
	}


	private function _callPlugins()
	{
		$app     = JFactory::getApplication();
		$jinput  = $app->input;
		if ($jinput->get('task', '', 'cmd') == __FUNCTION__) die(__FUNCTION__ . ' : direct call not allowed');

		// Call system plugin
		$extfolder = 'system';
		$extname   = 'flexisystem';
		$className = 'plg'. ucfirst($extfolder).$extname;

		require_once JPATH_SITE . '/plugins/'.$extfolder.'/'.$extname.'/'.$extname.'.php';

		$dispatcher   = JEventDispatcher::getInstance();
		$plg_db_data  = JPluginHelper::getPlugin($extfolder, $extname);
		$plg = new $className($dispatcher, array('type'=>$extfolder, 'name'=>$extname, 'params'=>$plg_db_data->params));

		// Load cached category data
		global $globalcats;
		if (FLEXI_CACHE) 
		{
			// Add the category tree to categories cache
			$catscache = JFactory::getCache('com_flexicontent_cats');
			$catscache->setCaching(1);                  // Force cache ON
			$catscache->setLifeTime(FLEXI_CACHE_TIME);  // Set expire time (default is 1 hour)
			$globalcats = $catscache->get(
				array($plg, 'getCategoriesTree'),
				array()
			);
		}
		else
		{
			$globalcats = $plg->getCategoriesTree();
		}
	}


	/**
	 * Method to fetch tags according to a given text
	 * 
	 * @return object
	 * @since 1.0
	 */
	private function _getTags($text = '', $limit = 500)
	{
		if (JFactory::getApplication()->input->get('task', '', 'cmd') == __FUNCTION__) die(__FUNCTION__ . ' : direct call not allowed');

		$db = JFactory::getDbo();

		$query = $db->getQuery(true)
			->select('*')
			->from('#__flexicontent_tags')
			->where('published = 1')
			->order('name')
			;

		if (trim($text))
		{
			$escaped_text = $db->escape($text, true);
			$quoted_text  = $db->Quote('%' . $escaped_text . '%', false);

			$query->where('name LIKE ' . $quoted_text);
		}

		$tags = $db->setQuery($query, 0, (int) $limit)->loadObjectlist();

		return $tags;
	}


	/**
	 * Method to load language files
	 * 
	 * @return object
	 * @since 3.2.1.4
	 */
	private function _loadLanguage()
	{
		$app     = JFactory::getApplication();
		$jinput  = $app->input;
		if ($jinput->get('task', '', 'cmd') == __FUNCTION__) die(__FUNCTION__ . ' : direct call not allowed');

		// Load english language file for 'com_flexicontent' component then override with current language file
		JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
		JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);
	}
}
