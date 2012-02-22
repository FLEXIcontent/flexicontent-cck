<?php
/**
 * @version 1.5 stable $Id: default.php 1088 2012-01-08 16:40:44Z ggppdk $
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

<div id="flexicontent" class="flexicontent">

<p class="buttons">
	<?php echo flexicontent_html::mailbutton( 'tags', $this->params, $this->tag->slug ); ?>
</p>

<?php if ($this->params->def( 'show_page_title', 1 )) : ?>

    <h1 class="componentheading">
		<?php echo $this->params->get('page_title'); ?>
	</h1>

<?php endif; ?>

<h2 class="flexicontent tagitems<?php echo $this->tag->id; ?>">
	<?php echo JText::_( 'FLEXI_ITEMS_WITH_TAG' ).' : '.$this->escape($this->tag->name); ?>
</h2>

<?php if ($this->params->get('use_search')) : ?>
<form action="<?php echo $this->action; ?>" method="post" id="adminForm">
<div id="fc_filter" class="floattext">
	<div class="fc_fleft">
		<input type="text" name="filter" id="filter" value="<?php echo $this->lists['filter'];?>" class="text_area" onchange="document.getElementById('adminForm').submit();" />
		<button onclick="document.getElementById('adminForm').submit();"><?php echo JText::_( 'FLEXI_GO' ); ?></button>
		<button onclick="document.getElementById('filter').value='';document.getElementById('adminForm').submit();"><?php echo JText::_( 'FLEXI_RESET' ); ?></button>
	</div>
</div>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="filter_order" value="<?php echo $this->lists['filter_order']; ?>" />
<input type="hidden" name="filter_order_Dir" value="" />
<input type="hidden" name="view" value="tags" />
<input type="hidden" name="task" value="" />
<input type="hidden" name="id" value="<?php echo $this->tag->id; ?>" />
</form>
<?php endif; ?>

<table class="flexitable" width="100%" border="0" cellspacing="0" cellpadding="0" summary="<?php echo JText::_( 'FLEXI_ITEMS_WITH_TAG' ).' : '.$this->escape($this->tag->name); ?>">
	<thead>
		<tr>
			<?php if ($this->params->get('use_image', 1)) : ?>
			<th id="fc_image"><?php echo JText::_( 'FLEXI_IMAGE' ); ?></th>
			<?php endif; ?>
			<th id="fc_title"><?php echo JText::_( 'FLEXI_ITEMS' ); ?></th>
			<th id="fc_desc"><?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?></th>
		</tr>
	</thead>	
	<tbody>	
	<?php
	foreach ($this->items as $item) :
    	$src 	= flexicontent_html::extractimagesrc($item);
    	$w		= '&amp;w=40';
    	$h		= '&amp;h=40';
    	$aoe	= '';
    	$q		= '&amp;q=95';
    	$conf	= $w . $h . $aoe . $q;
			$base_url = (!preg_match("#^http|^https|^ftp#i", $src)) ?  JURI::base(true).'/' : '';
	?>
  			<tr class="sectiontableentry" >
				<?php if ($this->params->get('use_image', 1)) : ?>
    			<td headers="fc_image" align="center">
    				<?php if ($src) : ?>
    				<a href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug)); ?>" class="hasTip" title="<?php echo JText::_( 'FLEXI_READ_MORE_ABOUT' ) . '::' . $this->escape($item->title); ?>">
						<img src="<?php echo JURI::base(); ?>components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=<?php echo $base_url . $src . $conf; ?>" />
					</a>
					<?php endif; ?>
				</td>
				<?php endif; ?>
    			<td headers="fc_title">
    				<a href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug)); ?>"><?php echo $this->escape($item->title); ?></a>
				</td>
    			<td headers="fc_intro">
    				<?php echo flexicontent_html::striptagsandcut( $item->introtext, 150 ); ?>
				</td>
			</tr>
	<?php endforeach; ?>
			
	</tbody>
</table>

<!-- BOF pagination -->
<?php if ($this->params->get('show_pagination', 2) != 0) : ?>
<div class="pageslinks">
	<?php echo $this->pageNav->getPagesLinks(); ?>
</div>

<?php if ($this->params->get('show_pagination_results', 1)) : ?>
<p class="pagescounter">
	<?php echo $this->pageNav->getPagesCounter(); ?>
</p>
<?php
	endif;
endif; 
?>
<!-- EOF pagination -->

</div>