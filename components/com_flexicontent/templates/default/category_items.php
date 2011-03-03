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
// first define the template name
$tmpl = $this->tmpl;
?>
<script type="text/javascript">
	function tableOrdering( order, dir, task )
	{
		var form = document.getElementById("adminForm");

		form.filter_order.value 	= order;
		form.filter_order_Dir.value	= dir;
		document.getElementById("adminForm").submit( task );
	}
</script>

<?php if ((($this->params->get('use_filters', 0)) && $this->filters) || ($this->params->get('use_search')) || ($this->params->get('show_alpha', 1))) : ?>
<form action="<?php echo $this->action; ?>" method="post" id="adminForm">
<?php if ((($this->params->get('use_filters', 0)) && $this->filters) || ($this->params->get('use_search'))) : ?>
<div id="fc_filter" class="floattext">
	<?php if ($this->params->get('use_search')) : ?>
	<div class="fc_fleft">
		<input type="text" name="filter" id="filter" value="<?php echo $this->lists['filter'];?>" class="text_area" onchange="document.getElementById('adminForm').submit();" />
		<button onclick="document.getElementById('adminForm').submit();"><?php echo JText::_( 'FLEXI_GO' ); ?></button>
		<button onclick="document.getElementById('filter').value='';document.getElementById('adminForm').submit();"><?php echo JText::_( 'FLEXI_RESET' ); ?></button>
	</div>
	<?php endif; ?>
	<?php if ($this->filters) : ?>
	<div class="fc_fright">
	<?php
	foreach ($this->filters as $filt) :
		echo '<span class="filter">';
		echo $filt->html;
		echo '</span>';
	endforeach;
	?>
	</div>
	<?php endif; ?>
</div>
<?php endif; ?>
<?php
if ($this->params->get('show_alpha', 1)) :
	echo $this->loadTemplate('alpha');
endif;
?>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="filter_order" value="<?php echo $this->lists['filter_order']; ?>" />
<input type="hidden" name="filter_order_Dir" value="" />
<input type="hidden" name="view" value="category" />
<input type="hidden" name="letter" value="" id="alpha_index" />
<input type="hidden" name="task" value="" />
<input type="hidden" name="id" value="<?php echo $this->category->id; ?>" />
</form>
<?php endif; ?>

<?php
if ($this->items) :
	// routine to determine all used columns for this table
	$layout = $this->params->get('clayout', 'default');
	$fbypos		= flexicontent_tmpl::getFieldsByPositions($layout, 'category');
	$columns = array();
	foreach ($this->items as $item) :
		if (isset($item->positions['table'])) :
			foreach ($fbypos['table']->fields as $f) :
				if (!in_array($f, $columns)) :
					$columns[$f] = @$item->fields[$f]->label;
				endif;
			endforeach;
		endif;
	endforeach;
?>
<table id="flexitable" class="flexitable" width="100%" border="0" cellspacing="0" cellpadding="0" summary="<?php echo $this->category->name; ?>">
	<thead>
			<tr>
				<th id="flexi_title" scope="col"><?php echo JText::_( 'FLEXI_ITEMS' ); ?></th>

				<?php foreach ($columns as $name => $label) : ?>
				<th id="field_<?php echo $name; ?>" scope="col"><?php echo $label; ?></th>
				<?php endforeach; ?>

			</tr>
	</thead>
	
	<tbody>
	
	<?php foreach ($this->items as $item) : ?>
  			<tr class="sectiontableentry">

				<!-- BOF item title -->
    			<th scope="row" class="table-titles">
    				<?php if ($this->params->get('link_titles', 0)) : ?>
    				<a href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug)); ?>"><?php echo $this->escape($item->title); ?></a>
    				<?php
    				else :
    				echo $this->escape($item->title);
    				endif;
    				?>
				</th>
				<!-- BOF item title -->

				<!-- BOF fields -->
				<?php foreach ($columns as $name => $label) : ?>
				<td><?php echo isset($item->positions['table']->{$name}->display) ? $item->positions['table']->{$name}->display : ''; ?></td>
				<?php endforeach; ?>
				<!-- EOF fields -->
				
			</tr>
	<?php endforeach; ?>
			
	</tbody>
</table>
<?php else : ?>
<div class="noitems"><?php echo JText::_( 'FLEXI_NO_ITEMS_CAT' ); ?></div>
<?php endif; ?>