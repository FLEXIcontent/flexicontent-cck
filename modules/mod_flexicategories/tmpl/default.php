<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_flexicategories
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

$cat_card_layout = $params->get('cat_image_float', 'left');

// Conteneur selon le layout
// top   → grille de cards (row / row-cols)
// left  → liste verticale (ul standard)
// right → liste verticale (ul standard)
if ($cat_card_layout === 'top') {
	$cols = $params->get('cat_card_cols', 3); // nombre de colonnes, paramètre optionnel
	echo '<ul class="categories-module' . $moduleclass_sfx . ' list-unstyled row row-cols-1 row-cols-sm-2 row-cols-md-' . (int)$cols . ' g-3" style="list-style:none;padding-left:0">';
} else {
	echo '<ul class="categories-module' . $moduleclass_sfx . ' list-unstyled d-flex flex-column gap-2" style="list-style:none;padding-left:0">';
}
?>
<?php require \Joomla\CMS\Helper\ModuleHelper::getLayoutPath('mod_flexicategories', $params->get('layout', 'default') . '_items'); ?>
</ul>