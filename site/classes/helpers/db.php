<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Table\Table;

/*
 * CLASS with common methods for handling interaction with DB
 */
class flexicontent_db
{
	/**
	 * Method to get the overridden language string (frontend in backend and backend in frontend)
	 *
	 * @return string
	 * @since 4.2
	 */
	static function fe_be_lang_override($str, $oLang = null, $use_fe = true, $use_be = true)
	{
		// static variable (array) so that we load overrides only once per language
		static $lang_overrides_site;
		static $lang_overrides_admin;
		$oLang = $oLang ?: JFactory::getLanguage()->getTag();

		// Check if we have loaded the override file already, (aka we load it only once)
		if ($oLang && (!isset($lang_overrides_site[$oLang]) || !isset($lang_overrides_site[$oLang])))
		{
			$fe_file = JPATH_SITE . '/language/overrides/' . $oLang . '.override.ini';
			$be_file = JPATH_ADMINISTRATOR . '/language/overrides/' . $oLang . '.override.ini';
			$lang_overrides_site[$oLang]  = file_exists($fe_file) ? JLanguageHelper::parseIniFile($fe_file) : [];
			$lang_overrides_admin[$oLang] = file_exists($be_file) ? JLanguageHelper::parseIniFile($be_file) : [];
		}

		// Get overridden string, otherwise do not change
		$isSite   = JFactory::getApplication()->isClient('site');
		$isAdmin  = JFactory::getApplication()->isClient('administrator');
		$strUpper = strtoupper($str);
		$str_fe = $use_be || (!$isSite && !$isAdmin)
			? ($lang_overrides_admin[$oLang][$strUpper] ?? $str)
			: $str;
		$str_be = $use_fe || (!$isSite && !$isAdmin)
			? ($lang_overrides_site[$oLang][$strUpper] ?? $str)
			: $str;

		if ($isSite)
		{
			return $str_fe !== $str ? $str_fe : $str_be;
		}
		elseif ($isAdmin)
		{
			return $str_be !== $str ? $str_be : $str_fe;
		}
		else
		{
			// Prefer site
			return $str_fe !== $str ? $str_fe : $str_be;
		}
	}


	/**
	 * Method to set value for custom field 's common data types (INTEGER, DECIMAL(65,15), DATETIME)
	 *
	 * @return string
	 * @since 1.5
	 */
	static function setValues_commonDataTypes($obj, $all=false)
	{
		$db = JFactory::getDbo();
		$query = 'UPDATE IGNORE #__flexicontent_fields_item_relations'
			. ' SET value_integer = CAST(value AS SIGNED), value_decimal = CAST(value AS DECIMAL(65,15)), value_datetime = CAST(value AS DATETIME) '
			. (!$all ? ' WHERE item_id = ' . (int) $obj->item_id . ' AND field_id = ' . (int) $obj->field_id . ' AND valueorder = ' . (int) $obj->valueorder. ' AND suborder = ' . (int) $obj->suborder : '');
		$db->setQuery($query);
		$db->execute();
	}


	/**
	 * Method to verify a record has valid JSON data for the given column
	 *
	 * @return string
	 * @since 3.1
	 */
	static function check_fix_JSON_column($colname, $tblname, $idname, $id, & $attribs=null)
	{
		$db = JFactory::getDbo();

		// This extra may seem redudant, but it is to avoid clearing valid data, due to coding or other errors
		$db->setQuery('SELECT '.$colname.' FROM #__'.$tblname.' WHERE '.$idname.' = ' . $db->Quote($id));
		$attribs = $db->loadResult();

		try {
			$json_data = new JRegistry($attribs);
		}
		catch (Exception $e)
		{
			$attribs = '{}';
			$json_data = new JRegistry($attribs);
			$db->setQuery('UPDATE #__'.$tblname.' SET '.$colname.' = '.$db->Quote($attribs).' WHERE '.$idname.' = ' .  $db->Quote($id));
			$db->execute();
			$app = JFactory::getApplication();
			if ($app->isClient('administrator'))
			{
				$app->enqueueMessage('Cleared bad JSON COLUMN: <b>'.$colname.'</b>, DB TABLE: <b>'.$tblname.'</b>, RECORD: <b>'.$id.'</b>', 'warning');
			}
		}

		return $json_data;
	}


	/**
	 * Method to verify a workflow association exists for given record, and add it
	 *
	 * @return string
	 * @since 3.1
	 */
	static function assign_default_WF($pk, $record = null, $extension = 'com_content.article', $stage_id = 0)
	{
		$db = JFactory::getDbo();
		if (!FLEXI_J40GE || !$pk) return;

		// This extra may seem redudant, but it is to avoid clearing valid data, due to coding or other errors
		if (!$record)
		{
			$query = $db->getQuery(true)
				->select('i.id AS i, wa.stage_id AS stage_id')
				->from('#__content AS i')
				->join('LEFT', '#__workflow_associations AS wa ON wa.item_id = i.id')
				->where('i.id = ' . (int) $pk);
			$record = $db->setQuery($query)->loadObject();
		}

		if ($record->stage_id === null)
		{
			if (!$stage_id)
			{
				$query = $db->getQuery(true)
					->select('ws.id AS id')
					->from('#__workflows AS w')
					->join('INNER', '#__workflow_stages AS ws ON ws.workflow_id = w.id AND ws.default = 1')
					->where('w.extension = ' . $db->Quote($extension))
					->where('w.default = 1');
				$stage_id = $db->setQuery($query)->loadResult();
			}
			if ($stage_id)
			{
				$record->stage_id = $stage_id;
				$obj = (object) array(
					'item_id' => (int) $record->id,
					'stage_id' => (int) $record->stage_id,
					'extension' => $extension,
				);
				$db->insertObject('#__workflow_associations', $obj);
			}
		}

		if (JDEBUG) JFactory::getApplication()->enqueueMessage('Assigned item to Default Workflow', 'notice');
	}


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

		$db = JFactory::getDbo();
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
	static function getTypeAttribs($force = false, $typeid = 0, $itemid = 0)
	{
		static $typeparams = array();
		static $item_to_type = array();
		static $_tbl_loaded = null;

		$typeid = !$typeid && isset($item_to_type[$itemid])
			? $item_to_type[$itemid]
			: $typeid;

		if (!$force && isset($typeparams[$typeid]))
		{
			return $typeparams[$typeid];
		}

		$db = JFactory::getDbo();
		$query	= 'SELECT t.id, t.attribs'
			. ' FROM #__flexicontent_types AS t'
			. ($itemid ? ' JOIN #__flexicontent_items_ext as ie ON ie.type_id = t.id' : '')
			. ' WHERE 1'
			. ($typeid ? ' AND t.id = ' . (int) $typeid : '')
			. ($itemid ? ' AND ie.item_id = ' . (int) $itemid : '')
			;
		$db->setQuery($query);

		// Select specific type for the given typeid or the type used by the given content itemid
		if ($typeid || $itemid)
		{
			$data = $db->loadObject();

			if (!$data)
			{
				return false;
			}

			$typeid = $data->id;
			$typeparams[$typeid] = $data->attribs;

			// If loading type of specific item then cache item to type mapping
			if ($itemid)
			{
				$item_to_type[$itemid] = $typeid;
			}

			return $typeparams[$typeid];
		}

		// Select all types
		else
		{
			if (!$_tbl_loaded)
			{
				$_tbl_loaded = true;
				$rows = $db->loadObjectList();

				foreach($rows as $data)
				{
					$typeid = $data->id;
					$typeparams[$typeid] = $data->attribs;
				}
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
		$db = JFactory::getDbo();

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
		$db = JFactory::getDbo();

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
		$db = JFactory::getDbo();

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
		$db = JFactory::getDbo();

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
		static $userConfig = array();

		if (isset($userConfig[$user_id]))
		{
			return $userConfig[$user_id];
		}

		$db = JFactory::getDbo();
		$query = 'SELECT author_basicparams'
			. ' FROM #__flexicontent_authors_ext'
			. ' WHERE user_id = ' . $user_id;
		$authorparams = $db->setQuery($query)->loadResult();

		$userConfig[$user_id] = new JRegistry($authorparams);

		return $userConfig[$user_id];
	}


	/*
	 * Find stopwords and too small words
	 *
	 * @return array
	 * @since 1.5
	 */
	static function removeInvalidWords($words, &$stopwords, &$shortwords, $tbl='flexicontent_items_ext', $col='search_index', $isprefix=1)
	{
		$db     = JFactory::getDbo();
		$app    = JFactory::getApplication();
		$option = $app->input->get('option', '', 'cmd');
		$min_word_len = $app->getUserState( $option.'.min_word_len', 0 );

		$_word_clause = $isprefix ? '+%s*' : '+%s';
		$query = 'SELECT '.$col
			.' FROM #__'.$tbl
			.' WHERE MATCH ('.$col.') AGAINST ("'.$_word_clause.'" IN BOOLEAN MODE)'
			.' LIMIT 1';

		$_words = array();
		foreach ($words as $word)
		{
			$quoted_word = $db->escape($word, true);
			$q = sprintf($query, $quoted_word);
			$result = $db->setQuery($q)->loadAssocList();

			// Word found
			if (!empty($result))
			{
				$_words[] = $word;
			}
			// Word not found and word too short
			elseif (StringHelper::strlen($word) < $min_word_len)
			{
				$shortwords[] = $word;
			}
			// Word not found
			else
			{
				$stopwords[] = $word;
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

		$db = JFactory::getDbo();

		foreach ($queries as $query)
		{
			$query = trim($query);
			if (!$query) continue;

			$result = $db->setQuery($query)->execute();
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
		$db   = JFactory::getDbo();
		$app  = JFactory::getApplication();
		$dbprefix = $app->getCfg('dbprefix');
		$dbtype   = $app->getCfg('dbtype');
		$dbtype   = $dbtype == 'mysql' && !function_exists('mysql_query') ? 'mysqli' : $dbtype;  // PHP 7 removes mysql but 'mysql' database may still be in the configuration file

		$query = $db->replacePrefix($query);  //echo "<pre>"; print_r($query); echo "\n\n";
		$db_connection = $db->getConnection();

		if ($dbtype === 'pdomysql' && is_a($db_connection, 'mysqli'))
		{
			$dbtype = 'mysqli';
		}

		$data = array();

		if ($dbtype === 'mysqli')
		{
			$result = $unbuffered
				? mysqli_query($db_connection , $query, MYSQLI_USE_RESULT)
				: mysqli_query($db_connection , $query);

			if ($result === false)
			{
				throw new Exception('error '.__FUNCTION__.'():: '.mysqli_error($db_connection));
			}

			while ($row = $assoc ? mysqli_fetch_assoc($result) : mysqli_fetch_object($result))
			{
				$data[] = $row;
			}

			mysqli_free_result($result);
		}

		elseif ($dbtype === 'mysql')
		{
			$result = $unbuffered
				? mysql_unbuffered_query($query, $db_connection)
				: mysql_query($query, $db_connection);

			if ($result===false)
			{
				throw new Exception('error '.__FUNCTION__.'():: '.mysql_error($db_connection));
			}

			while ($row = $assoc ? mysql_fetch_assoc($result) : mysql_fetch_object($result))
			{
				$data[] = $row;
			}

			mysql_free_result($result);
		}

		elseif ($dbtype === 'pdomysql')
		{
			// Exceptions maybe thrown by the below statements
			if (!$unbuffered)
			{
				$result = $db_connection->query($query);
				$data = $assoc
					? $result->fetchAll(PDO::FETCH_ASSOC)
					: $result->fetchAll(PDO::FETCH_OBJ);
			}
			else
			{
				$result = $db_connection->prepare(
					$query,
					array(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false)
				);
				$result->execute();

				while ($row = $assoc ? $result->fetch(PDO::FETCH_ASSOC) : $result->fetch(PDO::FETCH_OBJ))
				{
					$data[] = $row;
				}
			}
		}

		else
		{
			throw new Exception( __FUNCTION__.'(): direct db query, unsupported DB TYPE' );
		}

		return $data;
	}


	/**
	 * Build the calculation of the rating column used for ordering an item listing
	 * @access public
	 * @return string
	 */
	static function buildRatingOrderingColumn(& $rating_join = null, $colname = 'votes', $ta = 'i')
	{
		$voting_field = reset(FlexicontentFields::getFieldsByIds(array(11)));
		$voting_field->parameters = new JRegistry($voting_field->attribs);

		$rating_resolution = (int) $voting_field->parameters->get('rating_resolution', 5);
		$rating_resolution = $rating_resolution >= 5 ? $rating_resolution : 5;
		$rating_resolution = $rating_resolution <= 100 ? $rating_resolution : 100;
		$default_rating    = (int) $voting_field->parameters->get('default_rating', 70);

		$_weights = array();

		for ($i = 1; $i <= 9; $i++)
		{
			$weight_factor = round(((int) $voting_field->parameters->get('vote_'.$i.'_weight', 100)) / 100, 2);
			//$_weights[] = 'WHEN ' . $i . ' THEN ROUND(' . $weight_factor . ' * cr.rating_sum / cr.rating_count * ' . (100 / $rating_resolution / $rating_resolution) . ') * ' . $rating_resolution;
			$_weights[] = 'WHEN ' . $i . ' THEN ROUND(' . $weight_factor . ' * cr.rating_sum / cr.rating_count * ' . (100 / $rating_resolution) . ')';
		}

		$rating_join = '#__content_rating AS cr ON cr.content_id = ' . $ta . '.id';
		$_rating_percentage = 'CASE cr.rating_count'
			. ' ' . implode(' ', $_weights)
			. ' ELSE IF (ISNULL (cr.rating_count)'
			. '   , ' . round($default_rating / $rating_resolution) * $rating_resolution
			//. '   , ROUND(cr.rating_sum / cr.rating_count * ' . (100 / $rating_resolution / $rating_resolution) . ') * ' . $rating_resolution
			. '   , ROUND(cr.rating_sum / cr.rating_count * ' . (100 / $rating_resolution) . ')'
			. ' )'
			. ' END AS ' . $colname;

		return $_rating_percentage;
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
		$app = JFactory::getApplication();

		$order_fallback = 'rdate';  // Use as default or when an invalid ordering is requested
		$orderbycustomfield   = (int) $params->get('orderbycustomfield'.$sfx, 1);    // Backwards compatibility, defaults to enabled *
		$orderbycustomfieldid = (int) $params->get('orderbycustomfieldid'.$sfx, 0);  // * but this needs to be set in order for field ordering to be used

		// 1. If a FORCED -ORDER- is not given, then use ordering parameters from configuration. NOTE: custom field ordering takes priority
		if (!$order)
		{
			$order = ($orderbycustomfield && $orderbycustomfieldid)  ?  'field'  :  $params->get($config_param.$sfx, $order_fallback);
		}

		// 2. If allowing user ordering override, then get ordering from HTTP request variable
		$request_order = $app->input->get($request_var.$sfx, '', 'string');
		$order = $params->get('orderby_override') && $request_order ? $request_order : $order;

		// 3. Check various cases of invalid order, print warning, and reset ordering to default
		if ($order=='field' && !$orderbycustomfieldid )
		{
			// This can occur only if field ordering was requested explicitly, otherwise an not set 'orderbycustomfieldid' will prevent 'field' ordering
			echo "Custom field ordering was selected, but no custom field is selected to be used for ordering<br/>";
			$order = $order_fallback;
		}
		if ($order=='commented')
		{
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


		// ***
		// *** 2nd level ordering, (currently only supported when no SFX given)
		// ***

		if ($sfx!='' || !$support_2nd_lvl)
		{
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
		if (!$order)
		{
			$order = ($orderbycustomfield && $orderbycustomfieldid)  ?  'field'  :  $params->get($config_param.$sfx, $order_fallback);
		}

		// 2. If allowing user ordering override, then get ordering from HTTP request variable
		$request_order = $app->input->get($request_var.$sfx, '', 'string');
		$order = $request_var && $request_order ? $request_order : $order;

		// 3. Check various cases of invalid order, print warning, and reset ordering to default
		if ($order=='field' && !$orderbycustomfieldid )
		{
			// This can occur only if field ordering was requested explicitly, otherwise an not set 'orderbycustomfieldid' will prevent 'field' ordering
			echo "Custom field ordering was selected, but no custom field is selected to be used for ordering<br/>";
			$order = $order_fallback;
		}
		if ($order=='commented')
		{
			if (!file_exists(JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'jcomments.php'))
			{
				echo "jcomments not installed, you need jcomments to use 'Most commented' ordering OR display comments information.<br/>\n";
				$order = $order_fallback;
			}
		}

		$order_col_2nd = '';
		$order_dir_2nd = '';
		if ($order!='default')
		{
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
				$order_col	= $i_as.'.modified DESC, ' . $i_as.'.created';
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
			case 'rorder':
				$order_col	= $rel_as.'.catid, '.$rel_as.'.ordering DESC, '.$i_as.'.id DESC';
				$order_dir	= '';
				break;
			case 'jorder':
				$order_col	= $i_as.'.ordering';
				$order_dir	= 'ASC';
				break;
			case 'rjorder':
				$order_col	= $i_as.'.ordering';
				$order_dir	= 'DESC';
				break;

			// SPECIAL case custom field
			case 'field':
				$cf = $sfx == '_2nd' ? 'f2' : 'f';
				$order_type = $params->get('orderbycustomfieldint'.$sfx, 0);
				switch( $order_type )
				{
					case 1:  $order_col = $cf.'.value_integer';  break;   // Integer  // 'CAST('.$cf.'.value AS SIGNED)'
					case 2:  $order_col = $cf.'.value_decimal'; break;    // Decimal  // 'CAST('.$cf.'.value AS DECIMAL(65,15))'
					case 3:  $order_col = $cf.'.value_datetime';  break;  // Date     // 'CAST('.$cf.'.value AS DATETIME)'
					case 4:  $order_col = ($sfx == '_2nd' ? 'file_hits2' : 'file_hits'); break;  // Download hits
					default: $order_col = $cf.'.value'; break;  // Text
				}
				$order_dir = strtoupper($params->get('orderbycustomfielddir'.$sfx, 'ASC')) == 'ASC' ? 'ASC' : 'DESC';
				if ($order_type != 4 && $order_dir=='ASC')
				{
					$order_col = 'ISNULL('.$cf.'.value), ' . $order_col;
				}
				break;

			// NEW ADDED
			case 'random_ppr':
				$order_col	= 'RAND()';
				$order_dir	= '';
				break;
			case 'random':
				// Convert session id to array of hex strings (4 bytes each)
				$sid = JFactory::getSession()->getId();
				$sid = str_split(md5($sid), 8);
				// Create a SEED doing a XOR operation on session-ID to keep SEED to 4 bytes
				$seed = null;
				foreach($sid as $b)
				{
					$seed = ($seed == null)  ?  hexdec($b)  :  ($seed ^ hexdec($b));
				}
				$order_col	= 'RAND(' . $seed . ')';
				$order_dir	= '';
				break;
			case 'commented':
				$order_col	= 'comments_total';
				$order_dir	= 'DESC';
				break;
			case 'rated':
				$order_col	= 'votes DESC, rating_count';
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
				if (substr($order, 0, 7)=='custom:')
				{
					$order_parts = preg_split("/:/", $order);
					$_field_id = (int) @ $order_parts[1];
				}
				if (!empty($_field_id) && count($order_parts)==4)
				{
					$cf = $sfx == '_2nd' ? 'f2' : 'f';
					$order_type = strtolower($order_parts[2]);
					switch( $order_type )
					{
						case 'int':       $order_col = $cf.'.value_integer';  break;   // Integer  // 'CAST('.$cf.'.value AS SIGNED)'
						case 'decimal':   $order_col = $cf.'.value_decimal'; break;    // Decimal  // 'CAST('.$cf.'.value AS DECIMAL(65,15))'
						case 'date':      $order_col = $cf.'.value_datetime'; break;   // Date     // 'CAST('.$cf.'.value AS DATETIME)'
						case 'file_hits': $order_col = ($sfx == '_2nd' ? 'file_hits2' : 'file_hits'); break;  // Download hits
						default:          $order_col = $cf.'.value'; break;
					}
					$order_dir = strtoupper($order_parts[3])=='DESC' ? 'DESC' : 'ASC';
					if ($order_type != 'file_hits' && $order_dir=='ASC')
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
		$app = JFactory::getApplication();

		// 1. If forced ordering not given, then use ordering parameters from configuration
		if (!$order)
		{
			$order = $params->get($config_param, 'default');
		}

		// 2. If allowing user ordering override, then get ordering from HTTP request variable
		$request_order = $app->input->get($request_var, '', 'string');
		$order = $request_var && $request_order ? $request_order : $order;

		switch ($order)
		{
			case 'date':
				$order_col = $c_as.'.created_time';
				$order_dir = 'ASC';
				break;
			case 'rdate':
				$order_col = $c_as.'.created_time';
				$order_dir = 'DESC';
				break;
			case 'modified':
				$order_col = $c_as.'.modified_time DESC, ' . $c_as.'.created_time';
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
			case 'random_ppr':
				$order_col	= 'RAND()';
				$order_dir	= '';
				break;
			case 'random':
				// Convert session id to array of hex strings (4 bytes each)
				$sid = JFactory::getSession()->getId();
				$sid = str_split(md5($sid), 8);
				// Create a SEED doing a XOR operation on session-ID to keep SEED to 4 bytes
				$seed = null;
				foreach($sid as $b)
				{
					$seed = ($seed == null)  ?  hexdec($b)  :  ($seed ^ hexdec($b));
				}
				$order_col	= 'RAND(' . $seed . ')';
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
	 * @param   string   $jtable_name    The name of the JTable class
	 * @param   string   $redirect_url   The redirect URL to set
	 * @param   object   $controller     The controller instance
	 *
	 * @since	1.5
	 */
	static function checkin($jtable_name, $redirect_url, $controller)
	{
		$cid = JFactory::getApplication()->input->get('cid', array(0), 'array');
		$cid = ArrayHelper::toInteger($cid);

		$user = JFactory::getUser();
		$controller->setRedirect($redirect_url, '');

		static $canCheckinRecords = null;

		if ($canCheckinRecords === null)
		{
			$canCheckinRecords = $user->authorise('core.admin', 'com_checkin');
		}

		// Only attempt to check the row in if it exists.
		$checked_in = 0;
		$diff_user = array();
		$errors = array();

		foreach($cid as $pk)
		{
			if (!$pk)
			{
				continue;
			}

			// Get a new (DB) Table instance of the row to checkin.
			$table = JTable::getInstance($jtable_name, '');

			if (!$table->load($pk))
			{
				$errors[] = 'ID: ' . $pk . ': ' . $table->getError();
				continue;
			}

			// Record check-in is allowed if either (a) current user has Global Checkin privilege OR (b) record checked out by current user
			if (!$table->checked_out)
			{
				continue;
			}

			if (!$canCheckinRecords && $table->checked_out != $user->id)
			{
				$diff_user[] = $pk;
				continue;
			}

			// Attempt to check the row in.
			if (!$table->checkin($pk))
			{
				// Do not add too many errors, limit to 3
				if (count($errors) < 3)
				{
					$errors[] = 'ID: ' . $pk . ': ' . $table->getError();
				}

				continue;
			}

			$checked_in++;
		}

		// Start by mentioning the successful checkins operations
		$msg = JText::sprintf('FLEXI_RECORD_CHECKED_IN_SUCCESSFULLY', $checked_in);

		if (count($diff_user))
		{
			$msg .= '<br/><br/>IDs: ' . implode(', ', $diff_user) . ' -- ' . JText::_('FLEXI_RECORD_CHECKED_OUT_DIFF_USER');
		}

		if (count($errors))
		{
			$msg .= '<br/><br/>' . implode('<br/> ', $errors);
		}

		$controller->setRedirect( $redirect_url, $msg, ($errors ? 'error' : 'message') );
	}


	/**
	 * Return field types grouped or not
	 *
	 * @return array
	 * @since 1.5
	 */
	static function getFieldTypes($group=false, $usage=false, $published=false)
	{
		$db = JFactory::getDbo();
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
			JText::_('FLEXI_SINGLE_PROP_FIELDS')        => array('color', 'date', 'text', 'textarea', 'textselect'),
			JText::_('FLEXI_MULTIPLE_PROP_FIELDS')      => array('weblink', 'email', 'phonenumbers', 'termlist'),
			JText::_('FLEXI_MEDIA_MINI_APPS_FIELDS')    => array('file', 'image', 'mediafile', 'sharedmedia', 'addressint'),
			JText::_('FLEXI_ITEM_FORM_FIELDS')          => array('fieldgroup', 'account_via_submit', 'custom_form_html', 'coreprops'),
			JText::_('FLEXI_DISPLAY_MANAGEMENT_FIELDS') => array('toolbar', 'fcloadmodule', 'fcpagenav', 'linkslist', 'authoritems', 'jprofile', 'comments'),
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
	static function getTypeData($contenttypes_list = false)
	{
		static $cached = null;

		if (is_array($contenttypes_list))
		{
			$contenttypes_list = ArrayHelper::toInteger($contenttypes_list);
			$contenttypes_list = implode(',', $contenttypes_list);
		}

		if (isset($cached[$contenttypes_list]))
		{
			return $cached[$contenttypes_list];
		}

		// Retrieve item's Content Type parameters
		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select('*')
			->from('#__flexicontent_types AS t');

		if ($contenttypes_list)
		{
			$query->where('id IN (' . $contenttypes_list . ')');
		}

		$types = $db->setQuery($query)->loadObjectList('id');

		foreach ($types as $type)
		{
			$type->params = new JRegistry($type->attribs);
		}

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
		$db = JFactory::getDbo();
		$query = 'SELECT a.id as id, k.id as original_id'
			. ' FROM #__associations AS a'
			. ' JOIN #__associations AS k ON a.`key`=k.`key`'
			. ' JOIN #__content AS i ON i.id = k.id AND i.language = '. $db->Quote(flexicontent_html::getSiteDefaultLang())
			. ' WHERE a.id IN ('. implode(',', $ids) .') AND a.context = "com_content.item"'
		;
		$assoc_keys = $db->setQuery($query)->loadObjectList('id');

		if (!empty($items))
		{
			foreach($items as $item) $item->lang_parent_id = isset($assoc_keys[$item->id]) ? $assoc_keys[$item->id]->original_id : $item->id;
		}
		else
			return $assoc_keys;
	}


	static function getLangAssocs($ids, $config = null)
	{
		$config = $config ?: (object) array(
			'table'    => 'content',
			'table_ext'=> null,
			'ext_id'   => null,
			'context'  => 'com_content.item',
			'created'  => 'created',
			'modified' => 'modified',
		);

		$db = JFactory::getDbo();
		$query = 'SELECT a.id as item_id, i.id as id, i.title, i.' . $config->created . ' as created, i.' . $config->modified . ' as modified, '
			. ' i.language as language, i.language as lang, ' . $db->qn('a.key') . ' as ' . $db->qn('key') 
			. (!empty($config->state) ?', i.' . $config->state . ' AS state ' : '')
			. (!empty($config->catid) ?', i.' . $config->catid . ' AS catid ' : '')
			. (!empty($config->is_uptodate) ?', ext.' . $config->is_uptodate . ' AS is_uptodate ' : '')
			. ' FROM #__associations AS a'
			. ' JOIN #__associations AS k ON a.`key`=k.`key`'
			. ' JOIN ' . $db->qn('#__' . $config->table) . ' AS i ON i.id = k.id'
			. (!empty($config->table_ext) ? ' JOIN ' . $db->qn('#__' . $config->table_ext) . ' AS ext ON i.id = ext.' . $config->ext_id : '')
			. ' WHERE a.id IN ('. implode(',', $ids) .') AND a.context = ' . $db->quote($config->context)
		;
		$associations = $db->setQuery($query)->loadObjectList();

		$translations = array();

		foreach ($associations as $assoc)
		{
			$assoc->shortcode = strpos($assoc->language,'-')  ?  substr($assoc->language, 0, strpos($assoc->language,'-'))  :  $assoc->language;
			$translations[$assoc->item_id][$assoc->id] = $assoc;
		}

		return $translations;
	}


	/**
	 * Method to save language associations
	 *
	 * @return  boolean True if successful
	 */
	static function saveAssociations(&$item, &$data, $context, $add_current = false)
	{
		// Check if associations are enabled, but also mantain associations if associations data are no present, 
		if (!flexicontent_db::useAssociations() || !isset($data['associations']))
		{
			return true;
		}


		/**
		 * Prepare / check associations array
		 */

		// Unset empty associations from associations array, to avoid save them in the associations table
		$associations = !empty($data['associations']) ? $data['associations'] : array();

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

		// Add current item to associations if this is desired
		if ($add_current)
		{
			$associations[$item->language] = $item->id;
		}

		// Make sure associations ids are integers
		$associations = ArrayHelper::toInteger($associations);

		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select($db->qn('key'))
			->from('#__associations')
			->where($db->qn('context') . ' = ' . $db->quote($context))
			->where($db->qn('id') . ' = ' . (int) $item->id)
		;
		$key = $db->setQuery($query)->loadResult();


		// ***
		// *** Delete old associations for item, and remove given associations for other association groups
		// ***
		if ($key || $associations)
		{
			$record_ids = ArrayHelper::toInteger($associations);
			$where = array();
			if ($key) $where[] = $db->qn('key') . ' = ' . $db->quote($key);
			if ($associations) $where[] = ' id IN (' . implode(',', $record_ids) . ')';

			$query = $db->getQuery(true)
				->delete('#__associations')
				->where($db->qn('context') . ' = ' . $db->quote($context))
				->where('(' . implode(' OR ', $where) . ')');
			$db->setQuery($query)->execute();

			if ($context === 'com_content.item')
			{
				$ocLang = JComponentHelper::getParams('com_flexicontent')->get('original_content_language', '_site_default_');
				$ocLang = $ocLang === '_site_default_' ? JComponentHelper::getParams('com_languages')->get('site', '*') : $ocLang;
				$ocLang = $ocLang !== '_disable_' && $ocLang !== '*' ? $ocLang : false;
				if ($item->language === $ocLang)
				{
					$query = $db->getQuery(true)
						->update('#__flexicontent_items_ext')
						->set($db->qn('is_uptodate') . ' = 0')
						->where($db->qn('item_id') . ' IN (' . implode(',', $record_ids) . ')');
					$db->setQuery($query)->execute();
				}
			}
		}


		// ***
		// *** Add new associations
		// ***

		// Only add language associations if item language is not '*' (ALL)
		if ($all_language || count($associations)<=1) return true;

		$key = md5(json_encode($associations));
		$query->clear()
			->insert('#__associations');

		foreach ($associations as $id)
		{
			$query->values($id . ',' . $db->quote($context) . ',' . $db->quote($key));
		}

		$db->setQuery($query);
		$db->execute();

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


	/**
	 * Check and fix Joomla Tags table
	 *
	 * @return  void
	 *
	 * @since   3.3.8
	 */
	static function checkFixJTagsTable()
	{
		static $tags_table_fix_needed = null;

		if ($tags_table_fix_needed !== null)
		{
			return;
		}

		$db = JFactory::getDbo();

		$query = 'SELECT COUNT(*) FROM #__tags WHERE parent_id = 0 AND id <> 1';
		$tags_table_fix_needed = (boolean) $db->setQuery($query)->execute();

		// Fix Joomla tags table
		if ($tags_table_fix_needed)
		{
			$query = 'UPDATE #__tags SET parent_id = 1 WHERE parent_id = 0 AND id <> 1';
			$db->setQuery($query)->execute();

			JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_tags/tables');
			$tbl = JTable::getInstance('Tag', 'TagsTable');

			$tbl->rebuild();
		}
	}


	/**
	 * Find Joomla tags when given respective FC tags, creating them if they do not exist
	 *
	 * @param   array   $tags       Tags text array from the field
	 * @param   array   $checkACL   Flag to indicate if tag creation ACL should be used
	 * @param   string  $indexCol   Tag table column (name) whose value to use for indexing the return array
	 *
	 * @return  array   An array of tag data (or null tag data), indexed by the value of the given tag column
	 *
	 * @since   3.4.0
	 */
	static function createFindJoomlaTags($tags, $checkACL = true, $indexCol = 'null')
	{
		flexicontent_db::checkFixJTagsTable();

		$newTags = array();

		if (empty($tags) || (count($tags) === 1 && reset($tags) === ''))
		{
			return $newTags;
		}

		// We will use the tags table to store them
		Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_tags/tables');
		$tagTable  = Table::getInstance('Tag', 'TagsTable');
		$canCreate = \JFactory::getUser()->authorise('core.create', 'com_tags');

		foreach ($tags as $key => $tag)
		{
			// Get Tag Text, removing the #new# prefix that identifies new tags
			$tagText = is_object($tag) ? $tag->name : str_replace('#new#', '', $tag);

			$loaded = false;

			// Clear old data if exist
			$tagTable->reset();

			// (A) Try to load the selected tag via id
			$jtag_found = is_object($tag)
				? ((int) $tag->jtag_id && $tagTable->load((int) $tag->jtag_id))
				: ((int) $tag && $tagTable->load((int) $tag));

			$return_index = is_object($tag) && $indexCol
				? $tag->{$indexCol}
				: $tagText;

			if ($jtag_found)
			{
				$loaded = true;
			}

			// (B) Try to load the selected tag via title
			elseif ($tagTable->load(array('title' => $tagText)))
			{
				$loaded = true;
			}

			// (C) Try to load auto-created alias, and (D) Fail if not found
			else
			{
				// If user is not allowed to create tags, don't create new tag
				$is_new = is_object($tag) ? true : strpos($tag, '#new#') !== false;

				if ($checkACL && !$canCreate && $is_new)
				{
					$newTags[$return_index] = null;
					continue;
				}

				// Set title then call check() method to auto-create an alias
				$tagTable->title = $tagText;
				$tagTable->check();

				// (C) Try to load the selected tag, via auto-created alias
				if ($tagTable->alias && $tagTable->load(array('alias' => $tagTable->alias)))
				{
					$loaded = true;
				}

				// (D) Tag not found. Create a new tag at top-level with language 'ALL', with public access
				else
				{
					// Prepare tag data
					$tagTable->id        = 0;
					$tagTable->title     = $tagText;
					$tagTable->published = 1;

					// Language ALL, Public access (assumed ... 1)
					$tagTable->language = '*';
					$tagTable->access   = 1;

					// Make this item a child of the root tag
					$tagTable->parent_id = $tagTable->getRootId();
					$tagTable->setLocation($tagTable->getRootId(), 'last-child');

					// Try to store tag
					if ($tagTable->check())
					{
						// Assign the alias as path (autogenerated tags have always level 1)
						$tagTable->path = $tagTable->alias;

						if ($tagTable->store())
						{
							$loaded = true;
						}
					}
				}
			}

			if ($loaded)
			{
				$newTags[$return_index] = (object) array(
					'id'    => (int) $tagTable->id,
					'title' => $tagTable->title,
					'alias' => $tagTable->alias
				);
			}
			else
			{
				$newTags[$return_index] = null;
			}
		}

		return $newTags;
	}


	/**
	 * Method to unserialize arrays only
	 *
	 * @return  array or empty array if invalid data (serialized inner object is found)
	 */
	static function unserialize_array($v, $force_array=false, $force_value = true)
	{
		static $pattern_obj_inner = '/o:\d+:"[a-z0-9_]+":\d+:{.*?}/i';
		static $pattern_array_outer = '/^a:\d+:{.*?}$/is';

		// ***
		// *** SANITY CHECKs
		// ***

		// Already an array, return it
		if (is_array($v))
		{
			return $v;
		}

		// Zero length value, return empty array or false (error)
		if ($v === null || !strlen($v))
		{
			return $force_array ? array() : false;
		}

		// ***
		// *** Unserialize
		// ***

		// Check if value contains a serialized (inner) object, (and set error)
		if (preg_match($pattern_obj_inner, $v))
		{
			if (JDEBUG) JFactory::getApplication()->enqueueMessage('Object not allowed inside serialized user-data', 'error');
			$result = false;
		}
		// Check if value is as expected a serialized array, (and unserialize or set error)
		else
		{
			$result = preg_match($pattern_array_outer, $v) ? @ unserialize($v) : false;
		}

		//***
		// *** Return result of unserialization
		//***

		if ($result!==false)
		{
			return $force_array && !is_array($result) ? array($result) : $result;
		}
		else if ($force_value)
		{
			return $force_array ? array($v) : $v;
		}

		return false;
	}


	/**
	 * Get a list of the user groups.
	 *
	 * @return  array
	 * @since   3.2
	 */
	private static function _getUserGroupIDs()
	{
		$db = JFactory::getDbo();

		$query = $db->getQuery(true);
		$query
			->select('a.id')
			->from('#__usergroups AS a');

		return $db->setQuery($query)->loadColumn();
	}


	/**
	 * Get a list of the user groups.
	 *
	 * @return  array
	 * @since   3.2
	 */
	static function getSuperUserID()
	{
		// Return already found super user ID
		static $superUserID = null;
		if ($superUserID !== null)
		{
			return $superUserID;
		}

		// Find usergroups with Super Admin privilege
		$db = JFactory::getDbo();
		$groupIDs = flexicontent_db::_getUserGroupIDs();
		$suGroupIDs = array();

		foreach($groupIDs as $groupID)
		{
			if ( JAccess::checkGroup($groupID, 'core.admin') )
			{
				$suGroupIDs[] = $groupID;
			}
		}

		// Find the fist user that is super id
		$query = 'SELECT DISTINCT id FROM #__users as u'
			. ' JOIN #__user_usergroup_map ugm ON u.id = ugm.user_id AND ugm.group_id IN (' . implode(',', $suGroupIDs) . ')'
			. ' LIMIT 1';

		return $db->setQuery($query)->loadResult();
	}
}
