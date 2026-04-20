<?php
/**
 * @package     FLEXIcontent — Script CLI de régénération WebP en lot (Option 2)
 * @version     1.0
 *
 * EMPLACEMENT : Placer dans le dossier  cli/  à la racine de Joomla.
 *               Ex: /var/www/html/cli/rebuild_webp.php
 *
 * UTILISATION (ligne de commande depuis la racine Joomla) :
 *   php cli/rebuild_webp.php
 *   php cli/rebuild_webp.php --field=12        Limiter à un champ image (id)
 *   php cli/rebuild_webp.php --quality=85      Qualité WebP (1-100, défaut 80)
 *   php cli/rebuild_webp.php --dry-run         Simulation : affiche sans générer
 *   php cli/rebuild_webp.php --force           Régénère même si .webp existe déjà
 *   php cli/rebuild_webp.php --verbose         Affiche chaque fichier traité
 *
 * PRÉREQUIS SERVEUR :
 *   PHP GD avec support WebP  : php -r "echo function_exists('imagewebp') ? 'OK' : 'NON';"
 *   OU ImageMagick             : convert -list format | grep -i webp
 */

// ============================================================
// Bootstrap Joomla (CLI)
// ============================================================

define('_JEXEC', 1);

// Trouver la racine Joomla (le script est dans cli/, racine est un niveau au-dessus)
$joomla_root = dirname(__DIR__);
if (!file_exists($joomla_root . '/includes/defines.php'))
{
	// Fallback : le script est directement à la racine
	$joomla_root = __DIR__;
}
if (!file_exists($joomla_root . '/includes/defines.php'))
{
	die("ERREUR : Impossible de trouver la racine Joomla.\n"
		. "Placez ce script dans le dossier cli/ de votre site Joomla.\n");
}

define('JPATH_BASE', $joomla_root);

require_once $joomla_root . '/includes/defines.php';
require_once $joomla_root . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\Filesystem\Path;

// ============================================================
// Parsing des arguments CLI
// ============================================================

$options = array(
	'field_id' => null,
	'quality'  => 80,
	'dry_run'  => false,
	'force'    => false,
	'verbose'  => true,  // verbose par défaut en CLI
);

$args = array_slice($argv, 1);
foreach ($args as $arg)
{
	if ($arg === '--dry-run')  { $options['dry_run'] = true; continue; }
	if ($arg === '--force')    { $options['force']   = true; continue; }
	if ($arg === '--quiet')    { $options['verbose'] = false; continue; }

	if (preg_match('/^--field=(\d+)$/', $arg, $m))
	{
		$options['field_id'] = (int) $m[1];
		continue;
	}
	if (preg_match('/^--quality=(\d+)$/', $arg, $m))
	{
		$options['quality'] = max(1, min(100, (int) $m[1]));
		continue;
	}
}

// ============================================================
// Vérification du support WebP avant de lancer
// ============================================================

echo "\n=== FLEXIcontent — Régénération WebP en lot ===\n\n";

$gd_ok = function_exists('imagewebp');
$im_ok = false;

if (!$gd_ok)
{
	$disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
	if (function_exists('exec') && !in_array('exec', $disabled))
	{
		exec('convert -version 2>&1', $out, $code);
		$im_ok = ($code === 0);
	}
}

echo "Moteur WebP disponible : ";
if ($gd_ok)      echo "GD natif (imagewebp)\n";
elseif ($im_ok)  echo "ImageMagick (fallback)\n";
else
{
	echo "AUCUN\n\n";
	echo "ERREUR : Ni GD avec support WebP, ni ImageMagick ne sont disponibles.\n";
	echo "  - GD    : recompiler PHP avec --with-webp\n";
	echo "  - IM    : installer ImageMagick et s'assurer que exec() est activé\n\n";
	exit(1);
}

if ($options['dry_run']) echo "Mode : SIMULATION (--dry-run, aucun fichier ne sera écrit)\n";
if ($options['force'])   echo "Mode : FORCE (régénération même si .webp existe)\n";
echo "Qualité WebP : " . $options['quality'] . "\n";

// ============================================================
// Chargement du plugin image FLEXIcontent
// ============================================================

JLoader::register(
	'plgFlexicontent_fieldsImage',
	$joomla_root . '/plugins/flexicontent_fields/image/image.php'
);
JLoader::register('FCField', $joomla_root . '/administrator/components/com_flexicontent/helpers/fcfield/parentfield.php');

// Instancier le plugin de façon minimale (on a juste besoin des méthodes helper)
$dispatcher = JEventDispatcher::getInstance();
$plugin_params = new \Joomla\Registry\Registry();
$image_plugin  = new plgFlexicontent_fieldsImage($dispatcher, array());

// ============================================================
// Récupérer le(s) champ(s) à traiter
// ============================================================

$db = Factory::getDbo();

$query = $db->getQuery(true)
	->select('f.id, f.name, f.label, fp.params')
	->from('#__extensions AS f')
	->join('LEFT', '#__flexicontent_fields AS fp ON fp.name = f.element')
	->where('f.type = ' . $db->quote('plugin'))
	->where('f.folder = ' . $db->quote('flexicontent_fields'))
	->where('f.element = ' . $db->quote('image'))
	->where('f.enabled = 1');

if ($options['field_id'])
{
	$query->where('fp.id = ' . (int) $options['field_id']);
}

// Requête directe sur flexicontent_fields pour les champs image actifs
$query2 = $db->getQuery(true)
	->select('id, name, label, params')
	->from('#__flexicontent_fields')
	->where('field_type = ' . $db->quote('image'))
	->where('published = 1');

if ($options['field_id'])
{
	$query2->where('id = ' . (int) $options['field_id']);
}

$db->setQuery($query2);
$fields_raw = $db->loadObjectList();

if (!$fields_raw)
{
	echo "\nAucun champ image FLEXIcontent publié trouvé";
	if ($options['field_id']) echo " avec l'id " . $options['field_id'];
	echo ".\n\n";
	exit(0);
}

echo "\nChamps image trouvés : " . count($fields_raw) . "\n\n";

// ============================================================
// Lancer la régénération pour chaque champ
// ============================================================

$total = array('generated' => 0, 'skipped' => 0, 'errors' => 0, 'dirs_scanned' => 0);

foreach ($fields_raw as $field_raw)
{
	$field = new stdClass();
	$field->id         = $field_raw->id;
	$field->name       = $field_raw->name;
	$field->label      = $field_raw->label;
	$field->parameters = new \Joomla\Registry\Registry($field_raw->params);

	// Vérifier que generate_webp est activé pour ce champ
	if (!(int) $field->parameters->get('generate_webp', 0) && !$options['force'])
	{
		echo "Champ #{$field->id} \"{$field->label}\" : generate_webp désactivé, ignoré "
			. "(utilisez --force pour traiter quand même)\n";
		continue;
	}

	$dir = $field->parameters->get('dir');
	if (!$dir)
	{
		echo "Champ #{$field->id} \"{$field->label}\" : pas de dossier configuré, ignoré\n";
		continue;
	}

	echo "Champ #{$field->id} \"{$field->label}\" (dossier: $dir) ...\n";

	$result = $image_plugin->rebuildAllWebp($field, array(
		'dry_run' => $options['dry_run'],
		'force'   => $options['force'],
		'quality' => $options['quality'],
		'verbose' => $options['verbose'],
	));

	echo "  -> Générés : {$result['generated']} | "
		. "Ignorés : {$result['skipped']} | "
		. "Erreurs : {$result['errors']} | "
		. "Fichiers analysés : {$result['dirs_scanned']}\n\n";

	foreach ($result as $k => $v)
	{
		$total[$k] += $v;
	}
}

// ============================================================
// Résumé final
// ============================================================

echo "=== RÉSUMÉ FINAL ===\n";
echo "WebP générés  : {$total['generated']}\n";
echo "Ignorés       : {$total['skipped']}\n";
echo "Erreurs       : {$total['errors']}\n";
echo "Fichiers vus  : {$total['dirs_scanned']}\n";

if ($options['dry_run'])
{
	echo "\n(Mode simulation — relancez sans --dry-run pour générer réellement)\n";
}

echo "\nTerminé.\n\n";
exit($total['errors'] > 0 ? 1 : 0);
