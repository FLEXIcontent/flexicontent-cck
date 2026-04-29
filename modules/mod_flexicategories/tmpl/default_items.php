<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_flexicategories
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

$app = \Joomla\CMS\Factory::getApplication();
global $globalcats;

// ---------------------------------------------------------------------------
// Paramètres image catégorie
// ---------------------------------------------------------------------------
$show_cat_image    = $params->get('show_description_image', 0);
$cat_image_source  = $params->get('cat_image_source', 2);
$cat_link_image    = $params->get('cat_link_image', 1);
$cat_image_method  = $params->get('cat_image_method', 1);
$cat_image_width   = $params->get('cat_image_width', 24);
$cat_image_height  = $params->get('cat_image_height', 24);
$cat_default_image = $params->get('cat_default_image', '');
$cat_image_webp    = (bool) $params->get('cat_image_webp', 0);

// Layout card : top | left | right
$cat_card_layout = $params->get('cat_image_float', 'left');

if ($show_cat_image)
{
	$joomla_image_path = $app->getCfg('image_path', '');
	$joomla_image_url  = str_replace(DS, '/', $joomla_image_path);
	$joomla_image_path = $joomla_image_path ? $joomla_image_path . DS : '';
	$joomla_image_url  = $joomla_image_url  ? $joomla_image_url  . '/' : '';

	$h   = '&amp;h=' . $cat_image_height;
	$w   = '&amp;w=' . $cat_image_width;
	$aoe = '&amp;aoe=1';
	$q   = '&amp;q=95';
	$ar  = '&amp;ar=x';
	$zc  = $cat_image_method ? '&amp;zc=' . $cat_image_method : '';

	$phpThumbURL = \Joomla\CMS\Uri\Uri::base(true)
		. '/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=';
}

// ---------------------------------------------------------------------------
// Helper : encode les espaces segment par segment
// ---------------------------------------------------------------------------
$encodeSrc = static function ($src) {
	return implode('/', array_map(static function ($seg) {
		return str_replace(
			['%2E', '%2D', '%5F', '%7E'],
			['.',   '-',   '_',   '~'],
			rawurlencode($seg)
		);
	}, explode('/', $src)));
};

// ---------------------------------------------------------------------------
// Helper : construit une URL phpThumb
// ---------------------------------------------------------------------------
$buildThumbUrl = static function (
	$src, $wParam, $hParam, $aoe, $q, $ar, $zc, $ext, $forceWebp = false
) use ($phpThumbURL, $encodeSrc) {
	$safeSrc = $encodeSrc($src);
	if ($forceWebp) {
		$f = '&amp;f=webp';
	} elseif (in_array($ext, ['png','gif','jpeg','jpg','webp','wbmp','bmp','ico'])) {
		$f = '&amp;f=' . $ext;
	} else {
		$f = '';
	}
	return $phpThumbURL . $safeSrc . $wParam . $hParam . $aoe . $q . $ar . $zc . $f;
};

// ---------------------------------------------------------------------------
// Helper : construit le tag <picture> ou <img>
// ---------------------------------------------------------------------------
$buildImgTag = static function ($urlNative, $urlWebp, $alt, $layout, $imgWidth, $imgHeight) {
	$safeAlt = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');

	if ($layout === 'top') {
		$imgClass = 'img-fluid w-100';
		$style    = 'object-fit:cover;height:' . $imgHeight . 'px;';
	} else {
		$imgClass = 'img-fluid flex-shrink-0';
		$style    = 'width:' . $imgWidth . 'px;height:' . $imgHeight . 'px;object-fit:cover;';
	}

	$imgTag = '<img src="' . $urlNative . '" class="' . $imgClass . '"'
		. ' alt="' . $safeAlt . '" title="' . $safeAlt . '"'
		. ' style="' . $style . '">';

	if ($urlWebp) {
		return '<picture>'
			. '<source type="image/webp" srcset="' . $urlWebp . '">'
			. $imgTag
			. '</picture>';
	}
	return $imgTag;
};

// ---------------------------------------------------------------------------
// Image par défaut
// ---------------------------------------------------------------------------
$default_img_native = '';
$default_img_webp   = '';

if ($cat_default_image && $show_cat_image)
{
	$srcDefault = \Joomla\CMS\Uri\Uri::base(true) . '/' . $joomla_image_url . $cat_default_image;
	$extDefault = strtolower(pathinfo($srcDefault, PATHINFO_EXTENSION));
	$default_img_native = $buildThumbUrl($srcDefault, $w, $h, $aoe, $q, $ar, $zc, $extDefault, false);
	$default_img_webp   = $cat_image_webp
		? $buildThumbUrl($srcDefault, $w, $h, $aoe, $q, $ar, $zc, $extDefault, true)
		: '';
}

// ---------------------------------------------------------------------------
// Autres paramètres
// ---------------------------------------------------------------------------
$show_empty_cats = (int) $params->get('show_empty_cats', 1);
$numitems        = (int) $params->get('numitems', 0);
$item_heading    = (int) $params->get('item_heading', 4);

// ---------------------------------------------------------------------------
// Boucle catégories
// ---------------------------------------------------------------------------
foreach ($list as $cat) :

	if (isset($globalcats[$cat->id]) && isset($globalcats[$cat->id]->totalitems))
	{
		$totalitems = (int) $globalcats[$cat->id]->totalitems;
		if (!$show_empty_cats && $totalitems === 0) { continue; }
	}
	else
	{
		$totalitems = 0;
	}

	$cat->slug = $cat->id . ':' . $cat->alias;
	$cat->link = \Joomla\CMS\Router\Route::_(FlexicontentHelperRoute::getCategoryRoute($cat->slug));

	$imgNative = '';
	$imgWebp   = '';
	$src       = '';

	if ($show_cat_image)
	{
		if (!is_object($cat->params)) {
			$cat->params = new \Joomla\Registry\Registry($cat->params);
		}
		$cat->image     = $cat->params->get('image');
		$_parts         = explode('#', $cat->image ?? '');
		$cat->image     = $_parts[0];
		$cat->introtext = &$cat->description;
		$cat->fulltext  = '';
		$ext            = '';

		if ($cat_image_source && $cat->image && file_exists(JPATH_SITE . DS . $joomla_image_path . $cat->image))
		{
			$src = \Joomla\CMS\Uri\Uri::base(true) . '/' . $joomla_image_url . $cat->image;
			$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
		}
		elseif ($cat_image_source != 1 && $extracted = flexicontent_html::extractimagesrc($cat))
		{
			$ext      = strtolower(pathinfo($extracted, PATHINFO_EXTENSION));
			$base_url = (!preg_match('#^http|^https|^ftp|^/#i', $extracted))
				? \Joomla\CMS\Uri\Uri::base(true) . '/' : '';
			$src = $base_url . $extracted;
		}

		if ($src) {
			$imgNative = $buildThumbUrl($src, $w, $h, $aoe, $q, $ar, $zc, $ext, false);
			$imgWebp   = $cat_image_webp
				? $buildThumbUrl($src, $w, $h, $aoe, $q, $ar, $zc, $ext, true)
				: '';
		} elseif ($default_img_native) {
			$imgNative = $default_img_native;
			$imgWebp   = $default_img_webp;
		}
	}

	$cat->image_src = $src;

	$safeTitle = htmlspecialchars($cat->title, ENT_QUOTES, 'UTF-8');
	$levelup   = $cat->level - $startLevel - 1;
	$hLevel    = $item_heading + $levelup;
	$isActive  = ($_SERVER['REQUEST_URI'] == $cat->link);

	// Tag image brut (sans lien)
	$imgHtml = ($imgNative && $show_cat_image)
		? $buildImgTag($imgNative, $imgWebp, $cat->title, $cat_card_layout, $cat_image_width, $cat_image_height)
		: '';

	// Image avec lien optionnel
	$imgLinked = ($cat_link_image && $imgHtml)
		? '<a href="' . $cat->link . '" tabindex="-1">' . $imgHtml . '</a>'
		: $imgHtml;

	// Compteur
	$counter = ($numitems && isset($globalcats[$cat->id]))
		? ' <span class="badge bg-secondary fw-normal">' . $totalitems . '</span>'
		: '';

	// Description
	$descHtml = '';
	if ($params->get('show_description', 0)) {
		$descHtml = '<p class="card-text small text-muted mt-1 mb-0">'
			. \Joomla\CMS\HTML\HTMLHelper::_('content.prepare', $cat->description, $cat->getParams(), 'mod_flexicategories.content')
			. '</p>';
	}

	// Titre
	$titleHtml = '<h' . $hLevel . ' class="card-title h6 mb-0">'
		. '<a href="' . $cat->link . '" class="text-decoration-none stretched-link">'
		. $safeTitle . $counter
		. '</a>'
		. '</h' . $hLevel . '>';
?>

<?php if ($cat_card_layout === 'top') : ?>
	<?php /* ============================================================
		LAYOUT TOP : card verticale, image pleine largeur en haut
		Le <li> est une colonne Bootstrap (col) grâce au row du parent
	   ============================================================ */ ?>
	<li class="col<?php echo $isActive ? ' active' : ''; ?>" style="list-style:none">
		<div class="card h-100 border shadow-sm overflow-hidden">
			<?php if ($imgLinked) : ?>
				<div class="mod_fccats_catimg_block">
					<?php echo $imgLinked; ?>
				</div>
			<?php endif; ?>
			<div class="card-body d-flex flex-column position-relative">
				<?php echo $titleHtml; ?>
				<?php echo $descHtml; ?>
			</div>
		</div>
	</li>

<?php elseif ($cat_card_layout === 'right') : ?>
	<?php /* ============================================================
		LAYOUT RIGHT : card horizontale, image à droite
	   ============================================================ */ ?>
	<li class="<?php echo $isActive ? 'active ' : ''; ?>" style="list-style:none">
		<div class="card border shadow-sm overflow-hidden">
			<div class="d-flex flex-row-reverse align-items-stretch position-relative">
				<?php if ($imgLinked) : ?>
					<div class="mod_fccats_catimg_block flex-shrink-0">
						<?php echo $imgLinked; ?>
					</div>
				<?php endif; ?>
				<div class="card-body py-2 px-3 d-flex flex-column justify-content-center">
					<?php echo $titleHtml; ?>
					<?php echo $descHtml; ?>
				</div>
			</div>
		</div>
	</li>

<?php else : ?>
	<?php /* ============================================================
		LAYOUT LEFT (défaut) : card horizontale, image à gauche
	   ============================================================ */ ?>
	<li class="<?php echo $isActive ? 'active ' : ''; ?>" style="list-style:none">
		<div class="card border shadow-sm overflow-hidden">
			<div class="d-flex flex-row align-items-stretch position-relative">
				<?php if ($imgLinked) : ?>
					<div class="mod_fccats_catimg_block flex-shrink-0">
						<?php echo $imgLinked; ?>
					</div>
				<?php endif; ?>
				<div class="card-body py-2 px-3 d-flex flex-column justify-content-center">
					<?php echo $titleHtml; ?>
					<?php echo $descHtml; ?>
				</div>
			</div>
		</div>
	</li>

<?php endif; ?>

	<?php if (
		$params->get('show_children', 0)
		&& (($params->get('maxlevel', 0) == 0) || ($params->get('maxlevel') >= ($cat->level - $startLevel)))
		&& count($cat->getChildren())
	) : ?>
		<ul class="list-unstyled ps-3 mt-2 d-flex flex-column gap-2">
		<?php $temp = $list; ?>
		<?php $list = $cat->getChildren(); ?>
		<?php require \Joomla\CMS\Helper\ModuleHelper::getLayoutPath('mod_flexicategories', $params->get('layout', 'default') . '_items'); ?>
		<?php $list = $temp; ?>
		</ul>
	<?php endif; ?>

<?php endforeach; ?>