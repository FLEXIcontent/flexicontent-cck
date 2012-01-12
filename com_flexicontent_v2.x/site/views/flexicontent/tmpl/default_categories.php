<?php
/**
 * @version 1.5 stable $Id: default_categories.php 825 2011-08-17 07:13:59Z ggppdk $
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

$hide_empty_cats = $this->params->get('hide_empty_cats', 0);
$show_cat_descr = $this->params->get('show_cat_descr', 0);
$cat_descr_cut = $this->params->get('cat_descr_cut', 100);
$show_cat_image = $this->params->get('show_cat_image', 0);
$cat_image_source = $this->params->get('cat_image_source', 0);
$cat_link_image = $this->params->get('cat_link_image', 1);
$cat_image_method = $this->params->get('cat_image_method', 1);
$cat_image_width = $this->params->get('cat_image_width', 80);
$cat_image_height = $this->params->get('cat_image_height', 80);

$hide_empty_subcats = $this->params->get('hide_empty_subcats', 0);
$show_subcat_descr = $this->params->get('show_subcat_descr', 0);
$subcat_descr_cut = $this->params->get('subcat_descr_cut', 100);
$show_subcat_image = $this->params->get('show_subcat_image', 0);
$subcat_image_source = $this->params->get('subcat_image_source', 0);
$subcat_link_image = $this->params->get('subcat_link_image', 1);
$subcat_image_method = $this->params->get('subcat_image_method', 1);
$subcat_image_width = $this->params->get('subcat_image_width', 80);
$subcat_image_height = $this->params->get('subcat_image_height', 80);



// Get the directory menu parameters 
$cols = JRequest::getVar('columns_count',false);
if(!$cols) $cols = $this->params->get('columns_count',1);
$c1 = $this->params->get('column1',false);
if(!$c1) $c1 = $this->params->get('column1',200);
$c2 = $this->params->get('column2',false);
if(!$c2) $c2 = $this->params->get('column2',200);
$c3 = $this->params->get('column3',false);
if(!$c3) $c3 = $this->params->get('column3',200);
$i = 0;
$condition1	= $condition2	= $condition3	= $style = '';
switch ($cols) 
{
	case 1 :
	$condition1	= '';
	$condition2	= '';
	$condition3	= '';
	$style		= ' style="width:100%;"';
	break;

	case 2 :
	$condition1	= $c1;
	$condition2	= '';
	$condition3	= '';
	$style		= ' style="width:47%;"';
	break;

	case 3 :
	$condition1	= $c1;
	$condition2	= ($c1+$c2);
	$condition3	= '';
	$style		= ' style="width:31%;"';
	break;

	case 4 :
	$condition1	= $c1;
	$condition2	= ($c1+$c2);
	$condition3	= ($c1+$c2+$c3);
	$style		= ' style="width:24%;"';
	break;
}
?>

<div class="column"<?php echo $style; ?>>
<?php foreach ($this->categories as $sub) : ?>

  <?php
  if ($this->params->get('hide_empty_cats')) {
    $subcats_are_empty = 1;
    if (!$sub->assigneditems) foreach($sub->subcats as $subcat) {
      if ($subcat->assignedcats || $subcat->assignedsubitems) {
        $subcats_are_empty = 0;
        break;
      }
    } else {
      $subcats_are_empty = 0;
    }
    if ($subcats_are_empty) continue;
  }
  ?>

<div class="floattext fcdirectory">
    
	<h2 class="flexicontent cat<?php echo $sub->id; ?>">
		<a href="<?php echo JRoute::_( FlexicontentHelperRoute::getCategoryRoute($sub->slug) ); ?>">
			<?php echo $this->escape($sub->title); ?>
			<?php if ($showassignated) : ?>
			<span class="small"><?php echo $sub->assigneditems != null ? '('.$sub->assigneditems.')' : '(0)'; ?></span>
			<?php endif; ?>
		</a>
	</h2>
	
	<?php 
	$image = "";
	if ($show_cat_image) {
		$image = "";
		$sub->introtext = & $sub->description;
		$sub->fulltext = "";
		
		if ( $cat_image_source && @$sub->image && JFile::exists( JPATH_SITE .DS. "images" .DS. "stories" .DS. $sub->image ) ) {
			$src = JURI::base(true)."/images/stories/".$sub->image;
	
			$h		= '&amp;h=' . $cat_image_height;
			$w		= '&amp;w=' . $cat_image_width;
			$aoe	= '&amp;aoe=1';
			$q		= '&amp;q=95';
			$zc		= $cat_image_method ? '&amp;zc=' . $cat_image_method : '';
			$conf	= $w . $h . $aoe . $q . $zc;
	
			$image = JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$src.$conf;
		} else if ( $cat_image_source!=1 && $src = flexicontent_html::extractimagesrc($sub) ) {

			$h		= '&amp;h=' . $cat_image_height;
			$w		= '&amp;w=' . $cat_image_width;
			$aoe	= '&amp;aoe=1';
			$q		= '&amp;q=95';
			$zc		= $cat_image_method ? '&amp;zc=' . $cat_image_method : '';
			$conf	= $w . $h . $aoe . $q . $zc;

			$base_url = (!preg_match("#^http|^https|^ftp#i", $src)) ?  JURI::base(true).'/' : '';
			$image = JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
		}
		
		if ($image) {
			$image = '<img class="fccat_image" src="'.$image.'" alt="'.$this->escape($sub->title).'" title="'.$this->escape($sub->title).'"/>';
		} else {
			$image = '<div class="fccat_image" style="height:'.$cat_image_height.'px;width:'.$cat_image_width.'px;" ></div>';
		}
		if ($cat_link_image && $image) {
			$image = '<a href="'.JRoute::_( FlexicontentHelperRoute::getCategoryRoute($sub->slug) ).'">'.$image.'</a>';
		}
	}
	?>
	
	<?php if ($image) : ?>
		<?php echo $image; ?>
	<?php endif; ?>
	
	<?php if ($show_cat_descr) : ?>
	<span class="fccat_descr"><?php echo flexicontent_html::striptagsandcut( $sub->description, $cat_descr_cut); ?></span>
	<?php endif; ?>
	
	<div class='clear'></div>
	<div class='fcsubcats_top'></div>
	
	<ul class="catdets cat<?php echo $sub->id; ?>" >
		
		<?php $oddeven = ''; ?>
		
		<?php foreach ($sub->subcats as $subcat) : ?>
			<?php
			$oddeven = $oddeven=='even' ? 'odd' : 'even';
			
			if ($hide_empty_subcats) {
				if (!$subcat->assignedcats && !$subcat->assignedsubitems) continue;
			}
			?>
		
			<li class='fcsubcat <?php echo $oddeven; ?>' >
				<a href="<?php echo JRoute::_( FlexicontentHelperRoute::getCategoryRoute($subcat->slug) ); ?>"><?php echo $this->escape($subcat->title); ?></a>
				<?php if ($showassignated) : ?>
				<span class="small"><?php echo $subcat->assignedsubitems != null ? '('.$subcat->assignedsubitems.'/'.$subcat->assignedcats.')' : '(0/'.$subcat->assignedcats.')'; ?></span>
				<?php endif; ?>

			<?php 
			$image = "";
			if ($show_subcat_image) {
				$image = "";
				$subcat->introtext = & $subcat->description;
				$subcat->fulltext = "";
				
				if ( $subcat_image_source && @$subcat->image && JFile::exists( JPATH_SITE .DS. "images" .DS. "stories" .DS. $subcat->image ) ) {
					$src = JURI::base(true)."/images/stories/".$subcat->image;
			
					$h		= '&amp;h=' . $subcat_image_height;
					$w		= '&amp;w=' . $subcat_image_width;
					$aoe	= '&amp;aoe=1';
					$q		= '&amp;q=95';
					$zc		= $subcat_image_method ? '&amp;zc=' . $subcat_image_method : '';
					$conf	= $w . $h . $aoe . $q . $zc;
			
					$image = JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$src.$conf;
				} else if ( $subcat_image_source!=1 && $src = flexicontent_html::extractimagesrc($subcat) ) {
		
					$h		= '&amp;h=' . $subcat_image_height;
					$w		= '&amp;w=' . $subcat_image_width;
					$aoe	= '&amp;aoe=1';
					$q		= '&amp;q=95';
					$zc		= $subcat_image_method ? '&amp;zc=' . $subcat_image_method : '';
					$conf	= $w . $h . $aoe . $q . $zc;
		
					$base_url = (!preg_match("#^http|^https|^ftp#i", $src)) ?  JURI::base(true).'/' : '';
					$image = JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
				}
				
				if ($image) {
					$image = '<img class="fcsubcat_image" src="'.$image.'" alt="'.$this->escape($subcat->title).'" title="'.$this->escape($subcat->title).'"/>';
				} else {
					$image = '<div class="fcsubcat_image" style="height:'.$subcat_image_height.'px;width:'.$subcat_image_width.'px;" ></div>';
				}
				
				if ($subcat_link_image && $image) {
					$image = '<a href="'.JRoute::_( FlexicontentHelperRoute::getCategoryRoute($subcat->slug) ).'">'.$image.'</a>';
				}
			}
			?>
			
			<?php if ($image) : ?>
				<?php echo $image; ?>
			<?php endif; ?>
			
			
			<?php if ($show_subcat_descr) : ?>
			<span class="fcsubcat_descr"><?php echo flexicontent_html::striptagsandcut( $subcat->description, $cat_descr_cut); ?></span>
			<?php endif; ?>
			
			<div class='clear'></div>
			<div class='fcsubcat_bottom'></div>
			
			</li>
		<?php endforeach; ?>
		
	</ul>
</div>

<?php 
		$i++;
		if ($i == $condition1 || $i == $condition2 || $i == $condition3) :
			echo '</div><div class="column"'.$style.'>';
		endif;
endforeach; ?>
</div>
<div class="clear"></div>