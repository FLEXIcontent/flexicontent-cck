<?php
/**
 * Grouped queries name every column they return: MySQL's default mode (ONLY_FULL_GROUP_BY) rejects a column that is
 * neither grouped, nor aggregated, nor determined by the grouped columns. Where an item is in several categories,
 * the query takes its main category first, then the lowest category id.
 */
$code = function ($file) {
	$out = '';

	foreach (token_get_all(file_get_contents(__DIR__ . '/../' . $file)) as $token)
	{
		if (is_array($token) && in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) continue;
		$out .= is_array($token) ? $token[1] : $token;
	}

	return $out;
};

$checks = array(
	// file, text that must be there, text that must not come back
	array('site/models/category.php', 'MAX(CASE WHEN rel.catid = i.catid', 'rel.catid as rel_catid'),
	array('site/models/category.php', 'GROUP_CONCAT(rel.ordering ORDER BY rel.catid)', null),
	array('admin/models/items.php', 'MAX(CASE WHEN rel.catid = a.catid THEN rel.catid END), MIN(rel.catid)) as rel_catid', "', rel.catid as rel_catid'"),
	array('modules/mod_flexicontent/helper.php', 'CASE WHEN CHAR_LENGTH(mc.alias) THEN CONCAT_WS', "CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\\':\\', '.\$_cl.'.id"),
	array('site/models/favourites.php', "' WHERE (i.id = 0)'", null),
	array('admin/models/stats.php', 'MIN(DATE_FORMAT(i.created, "%Y-%b")) as year_month_text', 'i.created_by AS id, ua.name, ua.username'),
	array('admin/models/stats.php', 'MIN(i.modified_by) AS id, ua.name, MIN(ua.username) AS username', 'i.modified_by AS id, ua.name, ua.username'),
	array('admin/models/fields.php', 'MIN(rel.ordering) as typeordering, a.field_type as type, MIN(plg.name) as friendly', "'rel.ordering as typeordering"),
	array('site/classes/helpers/db.php', "' GROUP BY plg.element, plg.name'", "' GROUP BY plg.element'"),
	array('site/classes/helpers/html.php', "' GROUP BY f.uploaded_by, u.name'", 'SELECT DISTINCT f.uploaded_by AS uid'),
	array('admin/models/parentclassitem.php', '." GROUP BY iv.field_id"', '." GROUP BY f.id"'),
);
$n = 0;

foreach ($checks as $check)
{
	$text = $code($check[0]);

	if (strpos($text, $check[1]) === false)
	{
		throw new RuntimeException($check[0] . ': expected ' . $check[1]);
	}

	if ($check[2] !== null && strpos($text, $check[2]) !== false)
	{
		throw new RuntimeException($check[0] . ': the ungrouped form is back: ' . $check[2]);
	}

	$n++;
}

echo $n . " grouped query checks passed\n";
