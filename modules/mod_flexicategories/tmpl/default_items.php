<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_flexicategories
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

global $globalcats;
foreach ($list as $cat) :
	$cat_link = JRoute::_(FlexicontentHelperRoute::getCategoryRoute($cat->id));
	?>
	<li <?php if ($_SERVER['REQUEST_URI'] == $cat_link) echo ' class="active"';?>> <?php $levelup = $cat->level - $startLevel - 1; ?>
		<h<?php echo $params->get('item_heading') + $levelup; ?>>
		<a href="<?php echo $cat_link; ?>">
		<?php echo $cat->title;?>
			<?php if ($params->get('numitems')) : ?>
				(<?php echo $cat->numitems; ?>)
			<?php endif; ?>
		</a>
   		</h<?php echo $params->get('item_heading') + $levelup; ?>>

		<?php if ($params->get('show_description', 0)) : ?>
			<?php echo JHtml::_('content.prepare', $cat->description, $cat->getParams(), 'mod_flexicategories.content'); ?>
		<?php endif; ?>
		<?php if ($params->get('show_children', 0) && (($params->get('maxlevel', 0) == 0)
			|| ($params->get('maxlevel') >= ($cat->level - $startLevel)))
			&& count($cat->getChildren())) : ?>
			<?php echo '<ul>'; ?>
			<?php $temp = $list; ?>
			<?php $list = $cat->getChildren(); ?>
			<?php require JModuleHelper::getLayoutPath('mod_flexicategories', $params->get('layout', 'default') . '_items'); ?>
			<?php $list = $temp; ?>
			<?php echo '</ul>'; ?>
		<?php endif; ?>
	</li>
<?php endforeach; ?>
