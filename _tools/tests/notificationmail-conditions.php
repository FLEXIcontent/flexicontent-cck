<?php
/**
 * Regression tests for the FLEXIcontent notification mail conditions.
 *
 * Standalone script: it only needs PHP, no Joomla bootstrap is required.
 *
 * Run from the Joomla root:
 *   php administrator/components/com_flexicontent/_tools/tests/notificationmail-conditions.php
 */
define('_JEXEC', 1);

require_once __DIR__ . '/../../admin/helpers/notificationmail.php';

$fail = 0;
$pass = 0;

function check($label, $text, array $ctx, $expected)
{
	global $fail, $pass;

	$diag = array();
	$got  = FlexicontentHelperNotificationMail::renderConditions($text, $ctx, $diag);

	if ($got === $expected)
	{
		$pass++;
		echo "ok   - $label\n";
		return;
	}

	$fail++;
	echo "FAIL - $label\n";
	echo "       expected: " . var_export($expected, true) . "\n";
	echo "       got     : " . var_export($got, true) . "\n";
	echo "       diag    : " . implode(' | ', $diag) . "\n";
}

$ctx = array(
	'catid'     => 11,
	'incat'     => array(11, 18),
	'state_id'  => 1,
	'lang'      => 'fr-FR',
	'type_id'   => 3,
	'item_id'   => 42,
	'author_id' => 7,
	'editor_id' => 8,
	'fields'    => array(
		'FIELD_FLOORS'      => array(1, 2, 3),
		'FIELD_PRICE'       => 250000,
		'FIELD_CITY'        => 'Lyon',
		'FIELD_STATUS'      => 'sold',
		'FIELD_DESCRIPTION' => '',
		'FIELD_FEATURED'    => 0,
		'FIELD_ZERO'        => '0',
		'FIELD_TAGS'        => array('maison', 'appartement'),
		'FIELD_TEXT'        => 'Belle Piscine',
		'FIELD_PHONE'       => '',
		'FIELD_HTML'        => '<b>Lyon</b>',
	),
);

check('texte sans condition', 'Bonjour', $ctx, 'Bonjour');
check('pas de brace fermante', 'Bonjour {monde', $ctx, 'Bonjour {monde');

check('catid vrai', 'A{if catid=11}B{/if}C', $ctx, 'ABC');
check('catid faux', 'A{if catid=12}B{/if}C', $ctx, 'AC');
check('catid avec espaces', 'A{if  catid = 11  }B{/if}C', $ctx, 'ABC');
check('espace dans les accolades', 'A{ if catid=11 }B{ /if }C', $ctx, 'ABC');
check('majuscules', 'A{IF catid=11}B{/IF}C', $ctx, 'ABC');

check('else', 'A{if catid=12}B{else}C{/if}D', $ctx, 'ACD');
check('elseif premiere branche', 'A{if catid=11}X{elseif catid=18}Y{else}Z{/if}', $ctx, 'AX');
check('elseif seconde branche', 'A{if catid=10}X{elseif catid=11}Y{else}Z{/if}', $ctx, 'AY');
check('elseif else', 'A{if catid=10}X{elseif catid=18}Y{else}Z{/if}', $ctx, 'AZ');

check('imbrication', '{if catid=11}{if field_city=Lyon}X{/if}{/if}', $ctx, 'X');
check('imbrication fausse', '{if catid=11}{if field_city=Paris}X{/if}{/if}', $ctx, '');

check('and vrai', '{if catid=11 and field_city=Lyon}X{/if}', $ctx, 'X');
check('and faux', '{if catid=11 and field_city=Paris}X{/if}', $ctx, '');
check('or vrai', '{if catid=10 or field_city=Lyon}X{/if}', $ctx, 'X');
check('operateur &&', '{if catid=11 && field_city=Lyon}X{/if}', $ctx, 'X');
check('operateur ||', '{if catid=10 || field_city=Lyon}X{/if}', $ctx, 'X');
check('negation !', '{if !catid=12}X{/if}', $ctx, 'X');
check('negation not', '{if not catid=12}X{/if}', $ctx, 'X');
check('precedence and sur or', '{if catid=99 and field_city=Paris or field_city=Lyon}X{/if}', $ctx, 'X');

check('presence champ tableau', '{if field_floors}X{/if}', $ctx, 'X');
check('presence champ vide', '{if field_description}X{/if}', $ctx, '');
check('negation champ vide', '{if !field_description}X{/if}', $ctx, 'X');
check('empty champ vide', '{if field_description empty}X{/if}', $ctx, 'X');
check('empty champ rempli', '{if field_city empty}X{/if}', $ctx, '');
check('negation empty zero', '{if !field_featured empty}X{/if}', $ctx, 'X');
check('zero est vide ? non', '{if field_featured empty}X{/if}', $ctx, '');
check('chaine zero empty ? non', '{if field_zero empty}X{/if}', $ctx, '');
check('chaine zero truthy ? non', '{if field_zero}X{/if}', $ctx, '');
check('champ absent presence', '{if field_inconnu}X{/if}', $ctx, '');
check('champ absent negation', '{if !field_inconnu}X{/if}', $ctx, 'X');
check('champ absent empty', '{if field_inconnu empty}X{/if}', $ctx, 'X');
check('champ absent not empty', '{if !field_inconnu empty}X{/if}', $ctx, '');

check('egalite insensible casse', '{if field_city=lyon}X{/if}', $ctx, 'X');
check('egalite insensible casse inverse', '{if field_city=LYON}X{/if}', $ctx, 'X');
check('inegalite', '{if field_city!=Paris}X{/if}', $ctx, 'X');
check('operateur ==', '{if catid==11}X{/if}', $ctx, 'X');
check('egalite numerique', '{if field_price=250000}X{/if}', $ctx, 'X');
check('egalite numerique chaine', '{if field_price=250000.0}X{/if}', $ctx, 'X');

check('in scalaire', '{if catid in 11,12,13}X{/if}', $ctx, 'X');
check('in absent', '{if catid in 12,13}X{/if}', $ctx, '');
check('not in', '{if catid not in 12,13}X{/if}', $ctx, 'X');
check('in tableau', '{if field_floors in 2,9}X{/if}', $ctx, 'X');
check('in tableau absent', '{if field_floors in 8,9}X{/if}', $ctx, '');
check('in valeurs quotees', '{if field_tags in "maison","villa"}X{/if}', $ctx, 'X');

check('contains', '{if field_text contains "piscine"}X{/if}', $ctx, 'X');
check('contains insensible casse', '{if field_text contains "PISCINE"}X{/if}', $ctx, 'X');
check('contains absent', '{if field_text contains "garage"}X{/if}', $ctx, '');
check('not contains', '{if field_text not contains "garage"}X{/if}', $ctx, 'X');
check('contains tableau', '{if field_tags contains "appart"}X{/if}', $ctx, 'X');

check('numerique >', '{if field_price>100000}X{/if}', $ctx, 'X');
check('numerique >=', '{if field_price>=250000}X{/if}', $ctx, 'X');
check('numerique <', '{if field_price<300000}X{/if}', $ctx, 'X');
check('numerique <=', '{if field_price<=100}X{/if}', $ctx, '');
check('numerique non numerique', '{if field_city>100}X{/if}', $ctx, '');
check('numerique tableau', '{if field_floors>=3}X{/if}', $ctx, 'X');

check('incat', '{if incat in 18}X{/if}', $ctx, 'X');
check('incat absent', '{if incat in 77}X{/if}', $ctx, '');
check('state_id', '{if state_id=1}X{/if}', $ctx, 'X');
check('lang', '{if lang=fr-FR}X{/if}', $ctx, 'X');
check('type_id', '{if type_id=3}X{/if}', $ctx, 'X');
check('item_id', '{if item_id=42}X{/if}', $ctx, 'X');
check('author_id', '{if author_id=7}X{/if}', $ctx, 'X');
check('editor_id', '{if editor_id=8}X{/if}', $ctx, 'X');

check('raccourci champ', '{if floors=2}X{/if}', $ctx, 'X');
check('champ html normalise', '{if field_html=Lyon}X{/if}', $ctx, 'X');

check('entites html operateur >=', '{if field_floors&gt;=3}X{/if}', $ctx, 'X');
check('entites html operateur <', '{if field_price&lt;300000}X{/if}', $ctx, 'X');
check('entites html guillemets', '{if field_city=&quot;Lyon&quot;}X{/if}', $ctx, 'X');
check('entites html comparaison fausse', '{if field_floors&gt;8}X{/if}', $ctx, '');

check('condition invalide garde le contenu', 'A{if catid=}B{/if}C', $ctx, 'ABC');
check('condition inconnue garde le contenu', 'A{if catid 11}B{/if}C', $ctx, 'ABC');
check('if non ferme garde le contenu', 'A{if catid=12}B', $ctx, 'AB');
check('endif orphelin ignore', 'A{/if}B', $ctx, 'AB');
check('else orphelin ignore', 'A{else}B', $ctx, 'AB');
check('expression vide garde le contenu', 'A{if}B{/if}C', $ctx, 'ABC');
check('guillemet non ferme', 'A{if field_city="Lyon}B{/if}C', $ctx, 'ABC');

check('valeur utilisateur non interpretee', '{FIELD_TEXT}', $ctx, '{FIELD_TEXT}');
check('accolade litterale', 'A {not-a-marker} B', $ctx, 'A {not-a-marker} B');
check('json litteral', 'A {"a":1} B', $ctx, 'A {"a":1} B');
check('valeur avec accolade entre guillemets', '{if field_city="Lyo}n"}X{/if}', array_merge($ctx, array('fields' => array('FIELD_CITY' => 'Lyo}n'))), 'X');

$diag = array();
FlexicontentHelperNotificationMail::renderConditions('{if catid=11}A{/if}{if field_description}B{/if}', $ctx, $diag);

if ($diag)
{
	$pass++;
	echo "ok   - diagnostics non vides\n";
}
else
{
	$fail++;
	echo "FAIL - diagnostics non vides\n";
}

echo "\n$pass passed, $fail failed\n";

exit($fail ? 1 : 0);
