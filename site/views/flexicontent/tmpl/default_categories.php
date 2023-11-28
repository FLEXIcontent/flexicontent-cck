<?php
/**
 * @version 1.5 stable $Id: default_categories.php 1764 2013-09-16 08:00:21Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
jimport('joomla.filesystem.file');
?>

<?php
$showassignated = $this->params->get('showassignated', 0);

$cat_link_title = $this->params->get('cat_link_title', 1);
$hide_empty_cats = $this->params->get('hide_empty_cats', 0);
$show_cat_descr = $this->params->get('show_cat_descr', 0);
$cat_descr_cut = $this->params->get('cat_descr_cut', 100);
$show_cat_image = $this->params->get('show_cat_image', 0);
$cat_image_source = $this->params->get('cat_image_source', 2);
$cat_link_image = $this->params->get('cat_link_image', 1);
$cat_image_method = $this->params->get('cat_image_method', 1);
$cat_image_width = $this->params->get('cat_image_width', 80);
$cat_image_height = $this->params->get('cat_image_height', 80);

$hide_empty_subcats = $this->params->get('hide_empty_subcats', 0);
$show_subcat_descr = $this->params->get('show_subcat_descr', 0);
$subcat_descr_cut = $this->params->get('subcat_descr_cut', 100);
$show_subcat_image = $this->params->get('show_subcat_image', 0);
$subcat_image_source = $this->params->get('subcat_image_source', 2);
$subcat_link_image = $this->params->get('subcat_link_image', 1);
$subcat_image_method = $this->params->get('subcat_image_method', 1);
$subcat_image_width = $this->params->get('subcat_image_width', 80);
$subcat_image_height = $this->params->get('subcat_image_height', 80);

$app = JFactory::getApplication();
$joomla_image_path = $app->getCfg('image_path',  FLEXI_J16GE ? '' : 'images'.DS.'stories' );
$joomla_image_url  = str_replace (DS, '/', $joomla_image_path);
$joomla_image_path = $joomla_image_path ? $joomla_image_path.DS : '';
$joomla_image_url  = $joomla_image_url  ? $joomla_image_url.'/' : '';

// Get the directory menu parameters 
$cols = $app->input->getInt('columns_count', 0);
$cols = $cols ?: (int) $this->params->get('columns_count', 1);

// If 0 blocks for col, divide equally between columns
$items_per_column = round(count($this->categories) / $cols);

$c1 = (int) $this->params->get('column1', 0);
$c2 = (int) $this->params->get('column2', 0);
$c3 = (int) $this->params->get('column3', 0);

$c1 = $c1 ?: $items_per_column;
$c2 = $c2 ?: $items_per_column;
$c3 = $c3 ?: $items_per_column;

$i = 0;

$condition1	= $condition2	= $condition3	= $style = '';
switch ($cols) 
{
	case 1:
		$condition1	= '';
		$condition2	= '';
		$condition3	= '';
		$style		= ' style="width:100%;"';
		break;

	case 2:
		$condition1	= $c1;
		$condition2	= '';
		$condition3	= '';
		$style		= ' style="width:49%;"';
		break;

	case 3:
		$condition1	= $c1;
		$condition2	= ($c1+$c2);
		$condition3	= '';
		$style		= ' style="width:32%;"';
		break;

	case 4:
		$condition1	= $c1;
		$condition2	= ($c1+$c2);
		$condition3	= ($c1+$c2+$c3);
		$style		= ' style="width:24%;"';
		break;
}
?>

<div class="fccatcolumn "<?php echo $style; ?>>
<?php foreach ($this->categories as $cat) : ?>

	<?php
	if (!is_object($cat->params))
	{
		$cat->params = new JRegistry($cat->params);
	}

	if ($this->params->get('hide_empty_cats'))
	{
		$subcats_are_empty = 1;
		if (!$cat->assigneditems)
		{
			foreach($cat->subcats as $subcat)
			{
				if ($subcat->assignedcats || $subcat->assignedsubitems)
				{
					$subcats_are_empty = 0;
					break;
				}
			}
		}
		else
		{
			$subcats_are_empty = 0;
		}
		if ($subcats_are_empty) continue;
	}
	?>

<div class="floattext">

	<h2 class="fccat_title_box cat<?php echo $cat->id; ?>">
		<?php echo $cat_link_title ? '<a class="fccat_title" href="'.JRoute::_( FlexicontentHelperRoute::getCategoryRoute($cat->slug) ).'">' : '<span class="fccat_title">'; ?>
			
		<?php echo $this->escape($cat->title); ?>
		
		<?php if ($showassignated) : ?>
		<span class="fccat_assigned small"><?php echo $cat->assigneditems != null ? '('.$cat->assigneditems.')' : '(0)'; ?></span>
		<?php endif; ?>
		
	<?php echo $cat_link_title ? '</a>' : '</span>'; ?>
	</h2>
	
	<?php
	// category image
	$cat->image = FLEXI_J16GE ? $cat->params->get('image') : $cat->image;
	$image = "";
	if ($show_cat_image) {
		$image = "";
		$cat->introtext = & $cat->description;
		$cat->fulltext = "";
		
		if ( $cat_image_source && $cat->image && JFile::exists( JPATH_SITE .DS. $joomla_image_path . $cat->image ) ) {
			$src = JUri::base(true) ."/". $joomla_image_url . $cat->image;
	
			$h		= '&amp;h=' . $cat_image_height;
			$w		= '&amp;w=' . $cat_image_width;
			$aoe	= '&amp;aoe=1';
			$q		= '&amp;q=95';
			$ar 	= '&amp;ar=x';
			$zc		= $cat_image_method ? '&amp;zc=' . $cat_image_method : '';
			$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
			$f = in_array( $ext, array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico') ) ? '&amp;f='.$ext : '';
			$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;
	
			$image = JUri::base(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$src.$conf;
		} else if ( $cat_image_source!=1 && $src = flexicontent_html::extractimagesrc($cat) ) {

			$h		= '&amp;h=' . $cat_image_height;
			$w		= '&amp;w=' . $cat_image_width;
			$aoe	= '&amp;aoe=1';
			$q		= '&amp;q=95';
			$ar 	= '&amp;ar=x';
			$zc		= $cat_image_method ? '&amp;zc=' . $cat_image_method : '';
			$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
			$f = in_array( $ext, array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico') ) ? '&amp;f='.$ext : '';
			$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;

			$base_url = (!preg_match("#^http|^https|^ftp|^/#i", $src)) ?  JUri::base(true).'/' : '';
			$image = JUri::base(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
		}
		
		if ($image) {
			$image = '<img class="fccat_image" src="'.$image.'" alt="'.$this->escape($cat->title).'" title="'.$this->escape($cat->title).'"/>';
		} else {
			//$image = '<div class="fccat_image" style="height:'.$cat_image_height.'px;width:'.$cat_image_width.'px;" ></div>';
		}
		if ($cat_link_image && $image) {
			$image = '<a href="'.JRoute::_( FlexicontentHelperRoute::getCategoryRoute($cat->slug) ).'">'.$image.'</a>';
		}
	}
	?>
	
	<?php if ($image) : ?>
	<span class="fccat_image_box"><?php echo $image; ?></span>
	<?php endif; ?>
	
	<?php if ($show_cat_descr && $cat->description) : ?>
	<span class="fccat_descr"><?php echo flexicontent_html::striptagsandcut( $cat->description, $cat_descr_cut); ?></span>
	<?php endif; ?>
	
	<div class='clear'></div>
	
	<ul class="fcsubcats_list cat<?php echo $cat->id; ?>" >
		
		<?php $oddeven = ''; ?>
		
		<?php foreach ($cat->subcats as $subcat) : ?>
			<?php
			if (!is_object($subcat->params))
			{
				$subcat->params = new JRegistry($subcat->params);
			}

			$oddeven = $oddeven === 'even'
				? 'odd'
				: 'even';
			
			if ($hide_empty_subcats && !$subcat->assignedcats && !$subcat->assignedsubitems)
			{
				continue;
			}
			?>
		
			<li class='fcsubcat <?php echo $oddeven; ?>' >
				<a class='fcsubcat_title'  href="<?php echo JRoute::_( FlexicontentHelperRoute::getCategoryRoute($subcat->slug) ); ?>"><?php echo $this->escape($subcat->title); ?></a>
				<?php if ($showassignated) : ?>
				<span class="fcsubcat_assigned small nowrap_box">
					<?php echo '[ <b>'
						.($subcat->assignedsubitems ? '<span class="fcdir-cntitems">'.$subcat->assignedsubitems.'</span> <i class="icon-list-2 fcdir-icon-itemscnt"></i>' : '')
						.($subcat->assignedsubitems && $subcat->assignedcats ? '<span class="fcdir-cnt-sep"></span> ' : '')
						.($subcat->assignedcats ? '<span class="fcdir-subcatscnt">'.$subcat->assignedcats.'</span> <i class="icon-folder fcdir-icon-subcatscnt"></i>' : '')
						.'</b> ]';
					?>
				</span>
				<?php endif; ?>

			<?php 
			// Category image
			$subcat->image = FLEXI_J16GE ? $subcat->params->get('image') : $subcat->image;
			$image = "";
			if ($show_subcat_image) {
				
				$subcat->introtext = & $subcat->description;
				$subcat->fulltext = "";
				
				if ( $subcat_image_source && $subcat->image && JFile::exists( JPATH_SITE .DS. $joomla_image_path . $subcat->image ) ) {
					$src = JUri::base(true) ."/". $joomla_image_url . $subcat->image;
			
					$h		= '&amp;h=' . $subcat_image_height;
					$w		= '&amp;w=' . $subcat_image_width;
					$aoe	= '&amp;aoe=1';
					$q		= '&amp;q=95';
					$zc		= $subcat_image_method ? '&amp;zc=' . $subcat_image_method : '';
					$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
					$f = in_array( $ext, array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico') ) ? '&amp;f='.$ext : '';
					$conf	= $w . $h . $aoe . $q . $zc . $f;
			
					$image = JUri::base(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$src.$conf;
				} else if ( $subcat_image_source!=1 && $src = flexicontent_html::extractimagesrc($subcat) ) {
		
					$h		= '&amp;h=' . $subcat_image_height;
					$w		= '&amp;w=' . $subcat_image_width;
					$aoe	= '&amp;aoe=1';
					$q		= '&amp;q=95';
					$zc		= $subcat_image_method ? '&amp;zc=' . $subcat_image_method : '';
					$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
					$f = in_array( $ext, array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico') ) ? '&amp;f='.$ext : '';
					$conf	= $w . $h . $aoe . $q . $zc . $f;
		
					$base_url = (!preg_match("#^http|^https|^ftp|^/#i", $src)) ?  JUri::base(true).'/' : '';
					$image = JUri::base(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
				}
				
				if ($image) {
					$image = '<img class="fcsubcat_image" src="'.$image.'" alt="'.$this->escape($subcat->title).'" title="'.$this->escape($subcat->title).'"/>';
				} else {
					//$image = '<div class="fcsubcat_image" style="height:'.$subcat_image_height.'px;width:'.$subcat_image_width.'px;" ></div>';
				}
				
				if ($subcat_link_image && $image) {
					$image = '<a href="'.JRoute::_( FlexicontentHelperRoute::getCategoryRoute($subcat->slug) ).'">'.$image.'</a>';
				}
			}
			?>
			
			<?php if ($image) : ?>
			<span class="fcsubcat_image_box"><?php echo $image; ?></span>
			<?php endif; ?>
			
			<?php if ($show_subcat_descr && $subcat->description) : ?>
			<span class="fcsubcat_descr"><?php echo flexicontent_html::striptagsandcut( $subcat->description, $cat_descr_cut); ?></span>
			<?php endif; ?>
			
			<div class='clear'></div>
						
			</li>
		<?php endforeach; ?>
		
	</ul>
</div>

<?php 
		$i++;
		if ($i == $condition1 || $i == $condition2 || $i == $condition3) :
			echo '</div><div class="fccatcolumn"'.$style.'>';
		endif;
endforeach; ?>
</div>
<div class="fcclear"></div>