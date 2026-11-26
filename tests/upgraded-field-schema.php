<?php
/**
 * Read-only assertions after running Joomla's package installer on a legacy demo.
 * Use the database connection variables from database-migration-roundtrip.php and set FC_TEST_PREFIX.
 */
require getenv('FC_TEST_AUTOLOAD');
$prefix = getenv('FC_TEST_PREFIX');
if (!$prefix || !getenv('FC_TEST_DATABASE'))
{
	throw new RuntimeException('Set FC_TEST_DATABASE and FC_TEST_PREFIX to the upgraded Joomla installation.');
}
$db = (new \Joomla\Database\DatabaseFactory)->getDriver('mysqli', [
	'host' => getenv('FC_TEST_HOST'), 'user' => getenv('FC_TEST_USER'), 'password' => getenv('FC_TEST_PASSWORD'),
	'database' => getenv('FC_TEST_DATABASE'), 'prefix' => $prefix,
]);
$fields = $db->setQuery('SHOW FULL COLUMNS FROM #__flexicontent_fields')->loadAssocList('Field');
if ((string) $fields['asset_id']['Default'] !== '0' || strtolower($fields['description']['Type']) !== 'text')
{
	throw new RuntimeException('Upgraded fields need the zero asset default and a TEXT description.');
}
if (!$db->setQuery("SHOW INDEX FROM #__flexicontent_favourites WHERE Column_name = 'type' AND Seq_in_index = 1")->loadObject())
{
	throw new RuntimeException('Missing favourites type lookup index.');
}
echo "Upgraded field and favourites schema checks passed\n";
