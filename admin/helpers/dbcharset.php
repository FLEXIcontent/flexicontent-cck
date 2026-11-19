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
 * Converts FLEXIcontent's database tables from the 3-byte "utf8" character set to utf8mb4.
 *
 * A column in "utf8" (utf8mb3) cannot hold 4-byte characters such as emoji: without strict SQL mode
 * they are saved as "?" and with it the save fails. The conversion is done one table per ALTER
 * statement, so it can be repeated safely: a table that is already utf8mb4 is skipped.
 */
class FlexicontentDbCharset
{
	/**
	 * Longest indexed prefix of a character column, the same length Joomla core uses.
	 * 191 characters * 4 bytes fit both MyISAM's 1000-byte and old InnoDB row formats' 767-byte key limit.
	 */
	const KEY_CHARS = 191;

	/**
	 * The collation to convert to: the one Joomla's own content table uses in this database,
	 * so that joins between FLEXIcontent and Joomla columns never mix collations.
	 */
	public static function getTargetCollation($db)
	{
		$collation = (string) $db->setQuery(
			'SELECT c.COLLATION_NAME FROM information_schema.COLUMNS AS c'
			. ' WHERE c.TABLE_SCHEMA = DATABASE() AND c.TABLE_NAME = ' . $db->quote($db->getPrefix() . 'content')
			. ' AND c.COLUMN_NAME = ' . $db->quote('title')
		)->loadResult();

		return preg_match('/^utf8mb4_[a-z0-9_]+$/', $collation) ? $collation : 'utf8mb4_unicode_ci';
	}


	/**
	 * FLEXIcontent's tables in this database (per-field search tables included), smallest first.
	 *
	 * @return  object[]  Indexed by table name: name, engine, row_format, collation, rows, bytes, pending
	 */
	public static function getTables($db)
	{
		$old_charset = "IN ('utf8', 'utf8mb3')";

		$tables = $db->setQuery(
			'SELECT t.TABLE_NAME AS name, t.ENGINE AS engine, t.ROW_FORMAT AS row_format, t.TABLE_COLLATION AS collation,'
			. ' t.TABLE_ROWS AS `rows`, t.DATA_LENGTH + t.INDEX_LENGTH AS bytes,'
			. ' (SELECT COUNT(*) FROM information_schema.COLUMNS AS c'
			. '   WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME AND c.CHARACTER_SET_NAME ' . $old_charset
			. ' ) AS old_columns'
			. ' FROM information_schema.TABLES AS t'
			. ' WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_TYPE = ' . $db->quote('BASE TABLE')
			. '  AND t.TABLE_NAME LIKE ' . $db->quote($db->escape($db->getPrefix() . 'flexicontent_', true) . '%', false)
			. ' ORDER BY t.DATA_LENGTH + t.INDEX_LENGTH, t.TABLE_NAME'
		)->loadObjectList('name');

		foreach ($tables as $table)
		{
			$table->pending = $table->old_columns > 0 || preg_match('/^utf8(mb3)?_/', (string) $table->collation);
		}

		return $tables;
	}


	/**
	 * The tables that still have to be converted
	 */
	public static function getPendingTables($db)
	{
		return array_filter(self::getTables($db), function ($table) { return $table->pending; });
	}


	/**
	 * Convert the pending tables, smallest first.
	 *
	 * Each ALTER copies its table, so a large table can take longer than a web request may run.
	 * Tables larger than $max_bytes are therefore left alone (0 means no limit), and no further table
	 * is started once $max_seconds have passed. At least one table is converted per call, if any qualifies.
	 *
	 * @return  array  Table name => array(true|false, message). Tables that were not attempted are not listed.
	 */
	public static function convertPending($db, $max_bytes = 0, $max_seconds = 0)
	{
		$log   = array();
		$start = microtime(true);

		// Only one conversion at a time: a second ALTER would wait for the first and then copy the table again
		$lock = $db->quote($db->getPrefix() . 'flexicontent_dbcharset');

		if (!$db->setQuery('SELECT GET_LOCK(' . $lock . ', 0)')->loadResult())
		{
			return array('' => array(false, 'A conversion is already running, please wait for it to finish'));
		}

		try
		{
			foreach (self::getPendingTables($db) as $name => $table)
			{
				if ($max_bytes && $table->bytes > $max_bytes)
				{
					continue;
				}

				if ($max_seconds && $log && microtime(true) - $start > $max_seconds)
				{
					break;
				}

				$log[$name] = self::convertTable($db, $table);
			}
		}
		finally
		{
			$db->setQuery('SELECT RELEASE_LOCK(' . $lock . ')')->loadResult();
		}

		return $log;
	}


	/**
	 * Convert one table with a single ALTER TABLE statement.
	 *
	 * The columns are changed with MODIFY and not with CONVERT TO CHARACTER SET, because the latter
	 * also widens the column types (TEXT becomes MEDIUMTEXT, MEDIUMTEXT becomes LONGTEXT).
	 *
	 * @return  array  array(true|false, message)
	 */
	public static function convertTable($db, $table)
	{
		$collation = self::getTargetCollation($db);
		$start     = microtime(true);

		try
		{
			$clauses = array_merge(
				array('DEFAULT CHARACTER SET utf8mb4 COLLATE ' . $collation),
				self::getColumnClauses($db, $table->name, $collation),
				self::getIndexClauses($db, $table)
			);

			$db->setQuery('ALTER TABLE ' . $db->quoteName($table->name) . ' ' . implode(', ', $clauses))->execute();
		}
		catch (\Exception $e)
		{
			return array(false, $e->getMessage());
		}

		return array(true, sprintf('%d rows, %.1f MB, %.1f s', $table->rows, $table->bytes / 1048576, microtime(true) - $start));
	}


	/**
	 * A MODIFY clause for every column that is still utf8. The column definition is the one the
	 * database server prints itself (SHOW CREATE TABLE), with only the character set and collation replaced,
	 * so type, NULL, default value and comment stay exactly as they are.
	 */
	protected static function getColumnClauses($db, $tablename, $collation)
	{
		$columns = $db->setQuery(
			'SELECT c.COLUMN_NAME FROM information_schema.COLUMNS AS c'
			. ' WHERE c.TABLE_SCHEMA = DATABASE() AND c.TABLE_NAME = ' . $db->quote($tablename)
			. "  AND c.CHARACTER_SET_NAME IN ('utf8', 'utf8mb3')"
		)->loadColumn();

		if (!$columns)
		{
			return array();
		}

		$create  = $db->setQuery('SHOW CREATE TABLE ' . $db->quoteName($tablename))->loadRow();
		$clauses = array();

		foreach ($columns as $column)
		{
			$name = '`' . str_replace('`', '``', $column) . '`';

			// name, type with optional (length) or ('value', ...), old character set and collation if printed, the rest
			$found = preg_match(
				'/^\s*' . preg_quote($name, '/') . '\s+([a-z]+(?:\((?:[^()\']|\'(?:[^\']|\'\')*\')*\))?)'
				. '(?:\s+CHARACTER SET\s+\w+)?(?:\s+COLLATE\s+\w+)?(.*?),?\s*$/mi',
				$create[1],
				$m
			);

			if (!$found)
			{
				throw new \RuntimeException('Could not read the definition of column ' . $column);
			}

			$clauses[] = 'MODIFY ' . $name . ' ' . $m[1] . ' CHARACTER SET utf8mb4 COLLATE ' . $collation . $m[2];
		}

		return $clauses;
	}


	/**
	 * Clauses that shorten the indexed prefix of character columns to 191 characters, for the indexes
	 * that would no longer fit the storage engine's key length once every character may need 4 bytes.
	 */
	protected static function getIndexClauses($db, $table)
	{
		$engine = strtolower((string) $table->engine);
		$limit  = $engine === 'innodb'
			? (in_array(strtolower((string) $table->row_format), array('dynamic', 'compressed')) ? 3072 : 767)
			: 1000;

		$rows = $db->setQuery(
			'SELECT s.INDEX_NAME AS index_name, s.NON_UNIQUE AS non_unique, s.COLUMN_NAME AS column_name, s.SUB_PART AS sub_part, s.COLLATION AS index_order,'
			. ' c.CHARACTER_MAXIMUM_LENGTH AS char_length, c.CHARACTER_OCTET_LENGTH AS octet_length,'
			. ' c.CHARACTER_SET_NAME AS charset, c.DATA_TYPE AS data_type, c.IS_NULLABLE AS is_nullable'
			. ' FROM information_schema.STATISTICS AS s'
			. ' LEFT JOIN information_schema.COLUMNS AS c'
			. '  ON c.TABLE_SCHEMA = s.TABLE_SCHEMA AND c.TABLE_NAME = s.TABLE_NAME AND c.COLUMN_NAME = s.COLUMN_NAME'
			. ' WHERE s.TABLE_SCHEMA = DATABASE() AND s.TABLE_NAME = ' . $db->quote($table->name)
			. "  AND s.INDEX_TYPE NOT IN ('FULLTEXT', 'SPATIAL')"
			. ' ORDER BY s.INDEX_NAME, s.SEQ_IN_INDEX'
		)->loadObjectList();

		$indexes = array();

		foreach ($rows as $row)
		{
			$indexes[$row->index_name][] = $row;
		}

		$clauses = array();
		$integer_bytes = array('tinyint' => 1, 'smallint' => 2, 'mediumint' => 3, 'int' => 4, 'bigint' => 8);

		foreach ($indexes as $index_name => $parts)
		{
			$bytes = $shorter_bytes = 0;
			$cols  = array();

			foreach ($parts as $part)
			{
				// Binary and other character sets are unchanged; ENUM/SET keys store numeric values.
				$chars = $part->char_length !== null && !in_array($part->data_type, array('enum', 'set'))
					? (int) ($part->sub_part ?: $part->char_length) : 0;
				$utf8 = in_array($part->charset, array('utf8', 'utf8mb3', 'utf8mb4'), true);
				$width = $utf8 ? 4 : ($part->char_length ? $part->octet_length / $part->char_length : 1);
				$shorter = $utf8 ? min($chars, self::KEY_CHARS) : $chars;
				// Key limits count indexed bytes, not nullable flags or variable-length headers.
				$extra = $chars ? 0 : (isset($integer_bytes[$part->data_type]) ? $integer_bytes[$part->data_type] : 8);

				$bytes         += $chars * $width + $extra;
				$shorter_bytes += $shorter * $width + $extra;
				$cols[]         = $db->quoteName($part->column_name)
					. ($chars && ($part->sub_part || $shorter < $chars) ? '(' . $shorter . ')' : '')
					. ($part->index_order === 'D' ? ' DESC' : '');
			}

			if ($bytes <= $limit)
			{
				continue;
			}

			// A shorter prefix would change what a unique key accepts, so that is never done silently
			if (!$part->non_unique || $shorter_bytes > $limit)
			{
				throw new \RuntimeException('Index ' . $index_name . ' is too long for utf8mb4 and cannot be shortened automatically');
			}

			// Keep server-specific index options, including comments and visibility.
			if (in_array(null, array_column($parts, 'column_name'), true))
			{
				throw new \RuntimeException('Cannot automatically rebuild the functional index ' . $index_name);
			}
			$create = $db->setQuery('SHOW CREATE TABLE ' . $db->quoteName($table->name))->loadRow();
			if (!preg_match('/^\s*(?:KEY|INDEX)\s+' . preg_quote($db->quoteName($index_name), '/')
				. '\s+\((?:[^\r\n()]|\([^\r\n()]*\))*?\)([^\r\n]*)/m', $create[1], $definition))
			{
				throw new \RuntimeException('Could not read the definition of index ' . $index_name);
			}
			$options = rtrim(rtrim($definition[1]), ',');
			$clauses[] = 'DROP INDEX ' . $db->quoteName($index_name);
			$clauses[] = 'ADD INDEX ' . $db->quoteName($index_name) . ' (' . implode(', ', $cols) . ')' . $options;
		}

		return $clauses;
	}
}
