<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_flexicategories
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

$app = JFactory::getApplication();
//$cparams = clone(JComponentHelper::getComponent('com_flexicontent')->params);     // Get the COMPONENT only parameters
global $globalcats;

// category image params
$show_cat_image = $params->get('show_description_image', 0);  // we use different name for variable
$cat_image_source = $params->get('cat_image_source', 2); // 0: extract, 1: use param, 2: use both
$cat_link_image = $params->get('cat_link_image', 1);
$cat_image_method = $params->get('cat_image_method', 1);
$cat_image_width = $params->get('cat_image_width', 24);
$cat_image_height = $params->get('cat_image_height', 24);
$cat_image_float = $params->get('cat_image_float', 'left');
$cat_default_image = $params->get('cat_default_image','');

if ($show_cat_image)
{
	$joomla_image_path = $app->getCfg('image_path',  '');
	$joomla_image_url  = str_replace (DS, '/', $joomla_image_path);
	$joomla_image_path = $joomla_image_path ? $joomla_image_path.DS : '';
	$joomla_image_url  = $joomla_image_url  ? $joomla_image_url.'/' : '';

	$h		= '&amp;h=' . $cat_image_height;
	$w		= '&amp;w=' . $cat_image_width;
	$aoe	= '&amp;aoe=1';
	$q		= '&amp;q=95';
	$ar 	= '&amp;ar=x';
	$zc		= $cat_image_method ? '&amp;zc=' . $cat_image_method : '';
	$phpThumbURL = JUri::base(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=';
}



// ***
// *** Create thumbnail of default category image
// ***

if ($cat_default_image)
{
	$src = JUri::base(true) ."/". $joomla_image_url . $cat_default_image;
	
	$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
	$f = in_array( $ext, array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico') ) ? '&amp;f='.$ext : '';
	$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;
	
	$default_image = $phpThumbURL.$src.$conf;
	$default_image = '<img src="'.$default_image.'" alt="%s" title="%s" style="float: ' . $cat_image_float . '" />';
}
else
{
	$default_image = '';
}


// ***
// *** Other parameters
// ***

$show_empty_cats = (int) $params->get('show_empty_cats', 1);
$numitems = (int) $params->get('numitems', 0);
$item_heading = (int) $params->get('item_heading', 4);


// ***
// *** Loop through categories and display them according to their depth level
// ***

foreach ($list as $cat) :

	if (isset($globalcats[$cat->id]) && isset($globalcats[$cat->id]->totalitems))
	{
		$totalitems = (int) $globalcats[$cat->id]->totalitems;
		if (!$show_empty_cats && $totalitems === 0)
		{
			continue;
		}
	}
	else
	{
		$totalitems = 0;
	}
	
	$cat->slug = $cat->id.':'.$cat->alias;
	$cat->link = JRoute::_( FlexicontentHelperRoute::getCategoryRoute($cat->slug) );

	$image = '';
	$src = '';

	if ($show_cat_image)
	{
		if (!is_object($cat->params))
		{
			$cat->params = new JRegistry($cat->params);
		}
		
		$cat->image = $cat->params->get('image');
		$cat->introtext = & $cat->description;
		$cat->fulltext = '';
		
		if ( $cat_image_source && $cat->image && JFile::exists( JPATH_SITE .DS. $joomla_image_path . $cat->image ) )
		{
			$src = JUri::base(true) ."/". $joomla_image_url . $cat->image;
			
			$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
			$f = in_array( $ext, array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico') ) ? '&amp;f='.$ext : '';
			$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;
		}
		else if ( $cat_image_source!=1 && $src = flexicontent_html::extractimagesrc($cat) )
		{
			$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
			$f = in_array( $ext, array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico') ) ? '&amp;f='.$ext : '';
			$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;
			
			$base_url = (!preg_match("#^http|^https|^ftp|^/#i", $src)) ?  JUri::base(true).'/' : '';
			$src = $base_url.$src;
		}
		
		if ($src)
		{
			$image = '<img src="'.$phpThumbURL.$src.$conf.'" alt="'.$cat->title.'" title="'.$cat->title.'" style="float: ' . $cat_image_float . '" />';
		}
		elseif ($default_image)
		{
			$image = sprintf($default_image, $cat->title, $cat->title);
		}

		// Create image display and its link if category image is non-empty
		if ($cat_link_image && $image)
		{
			$image = '<a href="'.$cat->link.'">'.$image.'</a>';
		}
	}

	$cat->image = $image;
	$cat->image_src = $src;  // Also add image category URL for developers
	?>

	<li <?php if ($_SERVER['REQUEST_URI'] == $cat->link) echo ' class="active"';?>> <?php $levelup = $cat->level - $startLevel - 1; ?>

		<?php if ($cat->image):?>
			<div class="mod_fccats_catimg_block">
				<?php echo $cat->image; ?>
			</div>
		<?php endif; ?>

		<h<?php echo $item_heading + $levelup; ?>>
			<a href="<?php echo $cat->link; ?>">
			<?php echo $cat->title;?>
				<?php if ($numitems && isset($globalcats[$cat->id])) : ?>
					(<?php echo $totalitems; ?>)
				<?php endif; ?>
			</a>
		</h<?php echo $item_heading + $levelup; ?>>

		<?php if ($params->get('show_description', 0)) : ?>
			<?php echo JHtml::_('content.prepare', $cat->description, $cat->getParams(), 'mod_flexicategories.content'); ?>
		<?php endif; ?>
		<?php if (
			$params->get('show_children', 0)
			&& (($params->get('maxlevel', 0) == 0) || ($params->get('maxlevel') >= ($cat->level - $startLevel)))
			&& count($cat->getChildren())) :
		?>
			<?php echo '<ul>'; ?>
			<?php $temp = $list; ?>
			<?php $list = $cat->getChildren(); ?>
			<?php require JModuleHelper::getLayoutPath('mod_flexicategories', $params->get('layout', 'default') . '_items'); ?>
			<?php $list = $temp; ?>
			<?php echo '</ul>'; ?>
		<?php endif; ?>
	</li>
<?php endforeach; ?>
