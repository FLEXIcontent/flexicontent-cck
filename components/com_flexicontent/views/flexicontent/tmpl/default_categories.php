<?php
/**
 * @version 1.5 stable $Id$
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
?>

<?php
// Get the directory menu parameters 
$cols	= $this->params->get('columns_count');
$c1		= $this->params->get('column1');
$c2		= $this->params->get('column2');
$c3		= $this->params->get('column3');
$i = 0;
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

<div class="floattext">
    
	<h2 class="flexicontent cat<?php echo $sub->id; ?>">
		<a href="<?php echo JRoute::_( FlexicontentHelperRoute::getCategoryRoute($sub->slug) ); ?>">
			<?php echo $this->escape($sub->title); ?>
			<?php if ($this->params->get('showassignated')) : ?>
			<span class="small"><?php echo $sub->assigneditems != null ? '('.$sub->assigneditems.')' : '(0)'; ?></span>
			<?php endif; ?>
		</a>
	</h2>
	
	<ul class="catdets cat<?php echo $sub->id; ?>">
		<?php
		foreach ($sub->subcats as $subcat) :?>
		    <?php
			if ($this->params->get('hide_empty_subcats')) {
				if (!$subcat->assignedcats && !$subcat->assignedsubitems) continue;
			}
	    	?>
		
			<li>
				<a href="<?php echo JRoute::_( FlexicontentHelperRoute::getCategoryRoute($subcat->slug) ); ?>"><?php echo $this->escape($subcat->title); ?></a>
				<?php if ($this->params->get('showassignated')) : ?>
				<span class="small"><?php echo $subcat->assignedsubitems != null ? '('.$subcat->assignedsubitems.'/'.$subcat->assignedcats.')' : '(0/'.$subcat->assignedcats.')'; ?></span>
				<?php endif; ?>
			</li>
		<?php 
		endforeach; ?>
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