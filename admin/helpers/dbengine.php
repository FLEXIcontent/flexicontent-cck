<?php
/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2020, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Moves FLEXIcontent's database tables from the MyISAM to the InnoDB storage engine.
 *
 * This is an opt-in task: every ALTER copies its table, which takes minutes on large sites.
 * One table is converted per call, so the task can be continued after a timeout,
 * and every table is verified after its conversion.
 */
class FlexicontentDbEngine
{
	/**
	 * Megabytes converted per second. Measured on the project's 1,000,000-row test set (MySQL 8.0, SSD): 5.5 MB per second
	 * Only used to show the administrator a rough, cautious time estimate before starting.
	 */
	const MB_PER_SECOND = 4;

	/**
	 * An InnoDB copy needs about this many times the size of the MyISAM table while it is built
	 */
	const SPACE_FACTOR = 3;


	/**
	 * FLEXIcontent's tables in this database (per-field search tables included), smallest first.
	 *
	 * @return  object[]  Indexed by table name: name, engine, collation, rows, bytes, fulltext, pending, seconds
	 */
	public static function getTables($db)
	{
		$tables = $db->setQuery(
			'SELECT t.TABLE_NAME AS name, t.ENGINE AS engine, t.TABLE_COLLATION AS collation, t.CREATE_OPTIONS AS create_options,'
			. ' t.TABLE_ROWS AS `rows`, t.DATA_LENGTH + t.INDEX_LENGTH AS bytes,'
			. ' (SELECT COUNT(DISTINCT s.INDEX_NAME) FROM information_schema.STATISTICS AS s'
			. '   WHERE s.TABLE_SCHEMA = t.TABLE_SCHEMA AND s.TABLE_NAME = t.TABLE_NAME AND s.INDEX_TYPE = ' . $db->quote('FULLTEXT')
			. ' ) AS `fulltext`'
			. ' FROM information_schema.TABLES AS t'
			. ' WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_TYPE = ' . $db->quote('BASE TABLE')
			. '  AND t.TABLE_NAME LIKE ' . $db->quote($db->escape($db->getPrefix() . 'flexicontent_', true) . '%', false)
			. ' ORDER BY t.DATA_LENGTH + t.INDEX_LENGTH, t.TABLE_NAME'
		)->loadObjectList('name');

		foreach ($tables as $table)
		{
			$table->pending = strtolower((string) $table->engine) !== 'innodb';
			$table->seconds = 0.2 + $table->bytes / 1048576 / self::MB_PER_SECOND;
		}

		return $tables;
	}


	/**
	 * The tables that are not InnoDB yet
	 */
	public static function getPendingTables($db)
	{
		return array_filter(self::getTables($db), function ($table) { return $table->pending; });
	}


	/**
	 * Everything the administrator should see before starting, and whether the conversion may start.
	 *
	 * @return  object  ok (bool) and checks: array of object(label, value, ok) where ok is true, false or null (information only)
	 */
	public static function getPreflight($db)
	{
		$checks  = array();
		$pending = self::getPendingTables($db);
		$add     = function ($label, $value, $ok = null) use (&$checks) {
			$checks[] = (object) array('label' => $label, 'value' => $value, 'ok' => $ok);
		};

		// 1. Server version. FULLTEXT indexes on InnoDB exist since MySQL 5.6.4 and MariaDB 10.0.5
		$version    = (string) $db->setQuery('SELECT VERSION()')->loadResult();
		$is_mariadb = stripos($version, 'mariadb') !== false;
		$number     = preg_match('/^(?:5\.5\.5-)?(\d+\.\d+\.\d+)/', $version, $m) ? $m[1] : '0';
		$add('Database server', ($is_mariadb ? 'MariaDB ' : 'MySQL ') . $number);

		$innodb = $db->setQuery(
			'SELECT SUPPORT FROM information_schema.ENGINES WHERE ENGINE = ' . $db->quote('InnoDB')
		)->loadResult();
		$add('InnoDB storage engine', $innodb ? 'available' : 'not available', in_array($innodb, array('YES', 'DEFAULT')));

		$fulltext = version_compare($number, $is_mariadb ? '10.0.5' : '5.6.4', '>=');
		$add('FULLTEXT indexes on InnoDB', $fulltext ? 'supported' : 'not supported by this server version', $fulltext);

		// 2. Row format. DYNAMIC allows index prefixes up to 3072 bytes, older formats only 767 bytes (191 characters in utf8mb4)
		$variables = $db->setQuery(
			"SHOW VARIABLES WHERE Variable_name IN ('innodb_default_row_format', 'innodb_file_format', 'innodb_large_prefix',"
			. " 'innodb_ft_min_token_size', 'ft_min_word_len', 'innodb_file_per_table', 'datadir')"
		)->loadObjectList('Variable_name');
		$var = function ($name) use ($variables) { return isset($variables[$name]) ? (string) $variables[$name]->Value : null; };

		$row_format   = $var('innodb_default_row_format') ?: (strtolower((string) $var('innodb_file_format')) === 'barracuda' ? 'dynamic' : 'compact');
		$large_prefix = in_array(strtolower($row_format), array('dynamic', 'compressed'))
			&& ($var('innodb_large_prefix') === null || in_array(strtolower($var('innodb_large_prefix')), array('on', '1')));
		$add(
			'InnoDB row format',
			$row_format . ($large_prefix ? ' (index prefixes up to 3072 bytes)' : ' (index prefixes up to 767 bytes)'),
			$large_prefix ? true : null
		);

		// 3. Step 1 (utf8mb4) must be complete: an InnoDB table that is still utf8 rejects 4-byte characters under strict SQL mode
		$old_charset = (int) $db->setQuery(
			'SELECT COUNT(DISTINCT t.TABLE_NAME) FROM information_schema.TABLES AS t'
			. ' LEFT JOIN information_schema.COLUMNS AS c ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME'
			. "  AND c.CHARACTER_SET_NAME IN ('utf8', 'utf8mb3')"
			. ' WHERE t.TABLE_SCHEMA = DATABASE()'
			. '  AND t.TABLE_NAME LIKE ' . $db->quote($db->escape($db->getPrefix() . 'flexicontent_', true) . '%', false)
			. "  AND (c.COLUMN_NAME IS NOT NULL OR t.TABLE_COLLATION LIKE 'utf8\\_%' OR t.TABLE_COLLATION LIKE 'utf8mb3\\_%')"
		)->loadResult();
		$add(
			'Tables converted to utf8mb4',
			$old_charset ? $old_charset . ' table(s) still use the 3-byte utf8 character set, convert them first' : 'all',
			!$old_charset
		);

		// 4. Search word length. InnoDB and MyISAM each have their own server-wide setting
		$add(
			'Shortest word in FULLTEXT indexes',
			'InnoDB: ' . $var('innodb_ft_min_token_size') . ' characters, MyISAM: ' . $var('ft_min_word_len') . ' characters'
		);

		// 5. Disk space. Each ALTER builds a full copy of its table before it drops the old one
		$largest = 0;
		$total   = 0;
		$seconds = 0;

		foreach ($pending as $table)
		{
			$largest  = max($largest, $table->bytes);
			$total   += $table->bytes;
			$seconds += $table->seconds;
		}

		$needed = $largest * self::SPACE_FACTOR;
		$free   = self::getFreeSpace($var('datadir'));
		$add(
			'Free disk space on the database server',
			sprintf(
				'about %s needed (largest table: %s), %s',
				self::formatBytes($needed),
				self::formatBytes($largest),
				$free === null ? 'free space could not be read: please check it with your host' : self::formatBytes($free) . ' free'
			),
			$free === null ? null : $free > $needed
		);

		$add('Tables to convert', sprintf('%d of %d, %s', count($pending), count(self::getTables($db)), self::formatBytes($total)));
		$add('Estimated time', self::formatSeconds($seconds) . ' (rough estimate, depends on the server)');

		$ok = true;

		foreach ($checks as $check)
		{
			$ok = $ok && $check->ok !== false;
		}

		return (object) array('ok' => $ok, 'checks' => $checks, 'pending' => $pending);
	}


	/**
	 * Convert the next pending table (the smallest one). Call again until nothing is pending.
	 *
	 * @return  array|null  array(table name, true|false, message), or null when no table is pending
	 */
	public static function convertNext($db)
	{
		$pending = self::getPendingTables($db);

		if (!$pending)
		{
			return null;
		}

		// Only one conversion at a time: a second ALTER would wait for the first and then copy the table again
		$lock = $db->quote($db->getPrefix() . 'flexicontent_dbengine');

		if (!$db->setQuery('SELECT GET_LOCK(' . $lock . ', 0)')->loadResult())
		{
			return array('', false, 'A conversion is already running, please wait for it to finish');
		}

		try
		{
			$table  = reset($pending);
			$result = self::convertTable($db, $table);
		}
		finally
		{
			$db->setQuery('SELECT RELEASE_LOCK(' . $lock . ')')->loadResult();
		}

		return array($table->name, $result[0], $result[1]);
	}


	/**
	 * Convert one table and verify it: same number of rows, same checksum of its key columns,
	 * the same FULLTEXT indexes and the InnoDB engine.
	 *
	 * The table is write-locked from before the first count until after the last one,
	 * so a save by a visitor during the conversion cannot make the numbers differ.
	 *
	 * @return  array  array(true|false, message)
	 */
	public static function convertTable($db, $table)
	{
		$name   = $db->quoteName($table->name);
		$start  = microtime(true);
		$locked = false;

		try
		{
			$fulltext_before = self::getFulltextIndexes($db, $table->name);
			$checksum_query  = self::getChecksumQuery($db, $table->name);

			try
			{
				$db->setQuery('LOCK TABLES ' . $name . ' WRITE')->execute();
				$locked = true;
			}
			catch (\Exception $e)
			{
				// No LOCK TABLES privilege: convert without the lock, the comparison below is then only reliable on a quiet site
			}

			$before = $db->setQuery($checksum_query)->loadRow();

			// A MyISAM-only row format (FIXED) must not be carried over
			$row_format = stripos((string) $table->create_options, 'row_format') !== false ? ' ROW_FORMAT=DYNAMIC' : '';
			$db->setQuery('ALTER TABLE ' . $name . ' ENGINE=InnoDB' . $row_format)->execute();

			$after          = $db->setQuery($checksum_query)->loadRow();
			$fulltext_after = self::getFulltextIndexes($db, $table->name);

			if ($locked)
			{
				$db->setQuery('UNLOCK TABLES')->execute();
				$locked = false;
			}

			$engine = $db->setQuery(
				'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ' . $db->quote($table->name)
			)->loadResult();

			if (strtolower((string) $engine) !== 'innodb')
			{
				return array(false, 'The table was not converted, its engine is still ' . $engine);
			}

			if ($before[0] !== $after[0])
			{
				return array(false, sprintf('Row count differs after the conversion: %s before, %s after', $before[0], $after[0]));
			}

			if ($before[1] !== $after[1])
			{
				return array(false, 'The checksum of the key columns differs after the conversion');
			}

			if ($fulltext_before !== $fulltext_after)
			{
				return array(false, 'FULLTEXT indexes differ after the conversion: ' . implode(', ', array_diff($fulltext_before, $fulltext_after)));
			}
		}
		catch (\Exception $e)
		{
			if ($locked)
			{
				try { $db->setQuery('UNLOCK TABLES')->execute(); } catch (\Exception $e2) {}
			}

			return array(false, $e->getMessage());
		}

		return array(true, sprintf(
			'%s rows, %s, %.1f s, verified: rows, key checksum%s',
			$after[0],
			self::formatBytes($table->bytes),
			microtime(true) - $start,
			$fulltext_after ? ', ' . count($fulltext_after) . ' FULLTEXT index(es)' : ''
		));
	}


	/**
	 * Give a table that was just created the engine the site uses.
	 *
	 * FLEXIcontent creates some tables while it runs (a search table per filterable field).
	 * Once the site has been moved to InnoDB, these must not come back as MyISAM.
	 */
	public static function followSiteEngine($db, $tablename)
	{
		static $site_engine = null;

		if ($site_engine === null)
		{
			$site_engine = strtolower((string) $db->setQuery(
				'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
				. ' AND TABLE_NAME = ' . $db->quote($db->getPrefix() . 'flexicontent_items_ext')
			)->loadResult());
		}

		if ($site_engine !== 'innodb')
		{
			return;
		}

		$tablename = str_replace('#__', $db->getPrefix(), $tablename);
		$engine    = $db->setQuery(
			'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ' . $db->quote($tablename)
		)->loadResult();

		if ($engine && strtolower($engine) !== 'innodb')
		{
			$db->setQuery('ALTER TABLE ' . $db->quoteName($tablename) . ' ENGINE=InnoDB')->execute();
		}
	}


	/**
	 * Names of the table's FULLTEXT indexes, with their columns
	 */
	protected static function getFulltextIndexes($db, $tablename)
	{
		return $db->setQuery(
			"SELECT CONCAT(s.INDEX_NAME, ' (', GROUP_CONCAT(s.COLUMN_NAME ORDER BY s.SEQ_IN_INDEX), ')')"
			. ' FROM information_schema.STATISTICS AS s'
			. ' WHERE s.TABLE_SCHEMA = DATABASE() AND s.TABLE_NAME = ' . $db->quote($tablename)
			. '  AND s.INDEX_TYPE = ' . $db->quote('FULLTEXT')
			. ' GROUP BY s.INDEX_NAME ORDER BY s.INDEX_NAME'
		)->loadColumn();
	}


	/**
	 * A query that returns the row count and a checksum of the table's key columns: those of the primary key,
	 * or of all other indexes (FULLTEXT excluded) when the table has no primary key.
	 */
	protected static function getChecksumQuery($db, $tablename)
	{
		$rows = $db->setQuery(
			'SELECT s.INDEX_NAME AS index_name, s.COLUMN_NAME AS column_name'
			. ' FROM information_schema.STATISTICS AS s'
			. ' WHERE s.TABLE_SCHEMA = DATABASE() AND s.TABLE_NAME = ' . $db->quote($tablename)
			. "  AND s.INDEX_TYPE NOT IN ('FULLTEXT', 'SPATIAL')"
			. ' ORDER BY s.INDEX_NAME, s.SEQ_IN_INDEX'
		)->loadObjectList();

		$primary = $columns = array();

		foreach ($rows as $row)
		{
			$columns[$row->column_name] = 1;

			if ($row->index_name === 'PRIMARY')
			{
				$primary[$row->column_name] = 1;
			}
		}

		$parts = array();

		foreach (array_keys($primary ?: $columns) as $column)
		{
			$parts[] = 'COALESCE(' . $db->quoteName($column) . ", '')";
		}

		return 'SELECT COUNT(*)'
			. ($parts ? ", COALESCE(SUM(CRC32(CONCAT_WS('#', " . implode(', ', $parts) . '))), 0)' : ', 0')
			. ' FROM ' . $db->quoteName($tablename);
	}


	/**
	 * Free space of the database server's data folder, when it is on this machine and readable by PHP
	 */
	protected static function getFreeSpace($datadir)
	{
		if (!$datadir || !function_exists('disk_free_space') || !@is_dir($datadir))
		{
			return null;
		}

		$free = @disk_free_space($datadir);

		return $free === false ? null : $free;
	}


	public static function formatBytes($bytes)
	{
		return $bytes >= 1073741824
			? sprintf('%.1f GB', $bytes / 1073741824)
			: sprintf('%.1f MB', $bytes / 1048576);
	}


	public static function formatSeconds($seconds)
	{
		return $seconds >= 90
			? sprintf('%d min', ceil($seconds / 60))
			: sprintf('%d s', max(1, ceil($seconds)));
	}
}
