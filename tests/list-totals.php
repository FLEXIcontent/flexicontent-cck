<?php
/**
 * The front-end lists get their total from a COUNT(*) OVER() column of the list query,
 * not from SQL_CALC_FOUND_ROWS + FOUND_ROWS() (deprecated since MySQL 8.0.17).
 */
$files = array(
	'site/models/category.php',
	'plugins/search/flexiadvsearch/flexiadvsearch.php',
	'modules/mod_flexicontent/helper.php',
);
$checks = 0;

foreach ($files as $file)
{
	// The code without its comments
	$code = '';

	foreach (token_get_all(file_get_contents(__DIR__ . '/../' . $file)) as $token)
	{
		if (is_array($token) && in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) continue;
		$code .= is_array($token) ? $token[1] : $token;
	}

	if (stripos($code, 'SQL_CALC_FOUND_ROWS') !== false || stripos($code, 'FOUND_ROWS()') !== false)
	{
		throw new RuntimeException($file . ': SQL_CALC_FOUND_ROWS or FOUND_ROWS() is back');
	}

	if (substr_count($code, 'COUNT(*) OVER() AS fc_list_total') !== 1)
	{
		throw new RuntimeException($file . ': the list query has no total column');
	}

	if (strpos($code, '->fc_list_total') === false)
	{
		throw new RuntimeException($file . ': the total column is not read');
	}

	// The window function counts the rows that are left after GROUP BY: the total column needs a grouped list
	if (!preg_match('/COUNT\(\*\) OVER\(\) AS fc_list_total.*?GROUP BY i\.id/s', $code))
	{
		throw new RuntimeException($file . ': the list with the total column is not grouped by the item id');
	}

	// Without the old flag the database may stop sorting at the LIMIT: the list needs a unique last sort key
	if (!preg_match('/strpos\(\$orderby, \'i\.id\'\) === false\s*\?\s*\', i\.id\'/', $code))
	{
		throw new RuntimeException($file . ': no unique last sort key');
	}

	$checks += 5;
}

echo $checks . " list total checks passed\n";
