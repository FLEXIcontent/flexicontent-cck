<?php
/**
 * @version 1.5 stable $Id: default.php 184 2010-04-04 06:08:30Z emmanuel.danan $
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

defined('_JEXEC') or die('Restricted access');
$listOrder	= $this->lists['order'];
$listDirn	= $this->lists['order_Dir'];
$saveOrder 	= ($listOrder == 'c.lft' && $listDirn == 'asc');
$user		= &JFactory::getUser();
$userId		= $user->get('id');
?>
<form action="index.php" method="post" name="adminForm" id="adminForm">
	<table class="adminform">
		<tr>
			<td width="100%">
			  	<?php echo JText::_( 'FLEXI_SEARCH' ); ?>
				<input type="text" name="search" id="search" value="<?php echo $this->lists['search']; ?>" class="text_area" onChange="document.adminForm.submit();" />
				<button onclick="this.form.submit();"><?php echo JText::_( 'FLEXI_GO' ); ?></button>
				<button onclick="this.form.getElementById('search').value='';this.form.submit();"><?php echo JText::_( 'FLEXI_RESET' ); ?></button>
			</td>
			<td nowrap="nowrap">
				<div class="filter-select fltrt">
				  <?php echo $this->lists['language']; ?>
				  <?php echo $this->lists['state']; ?>
				</div>
			</td>
		</tr>
	</table>

	<table class="adminlist" cellspacing="1">
	<thead>
		<tr>
			<th width="5"><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			<th width="5"><input type="checkbox" name="toggle" value="" onClick="checkAll(<?php echo count( $this->rows ); ?>);" /></th>
			<th class="title"><?php echo JHTML::_('grid.sort', 'FLEXI_CATEGORY', 'c.title', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="20%"><?php echo JHTML::_('grid.sort', 'FLEXI_ALIAS', 'c.alias', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="10%"><?php echo JHTML::_('grid.sort', 'FLEXI_ITEMS_ASSIGNED', 'nrassigned', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JText::_( 'FLEXI_PUBLISHED' ); ?></th>
			<th width="7%"><?php echo JHTML::_('grid.sort', 'FLEXI_ACCESS', 'c.access', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="90">
				<?php echo JHTML::_('grid.sort', 'FLEXI_REORDER', 'c.lft', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php echo $this->orderingx ? JHTML::_('grid.order', $this->rows, 'filesave.png', 'categories.saveorder' ) : ''; ?>
			</th>
				<th width="5%" class="nowrap">
					<?php echo JHtml::_('grid.sort', 'JGRID_HEADING_LANGUAGE', 'language', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				</th>
			<th width="1%" nowrap="nowrap">
				<?php echo JHTML::_('grid.sort', 'FLEXI_ID', 'c.id', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>
		</tr>
	</thead>

	<tfoot>
		<tr>
			<td colspan="10">
				<?php echo $this->pagination->getListFooter(); ?>
			</td>
		</tr>
	</tfoot>

	<tbody>
		<?php
		$k = 0;
		$i = 0;
		$n = count($this->rows);
		$canCats	= $this->permission->CanCats;
		$originalOrders = array();
		foreach ($this->rows as $row) {
			$orderkey = array_search($row->id, $this->ordering[$row->parent_id]);
			$link 		= 'index.php?option=com_flexicontent&amp;task=category.edit&amp;cid[]='. $row->id;
			$access		= flexicontent_html::userlevel('access['.$row->id.']', $row->access, 'onchange="return listItemTask(\'cb'.$i.'\',\'categories.access\')"');
			$checked 	= JHTML::_('grid.checkedout', $row, $i );
			$items		= 'index.php?option=com_flexicontent&amp;view=items&amp;filter_cats='. $row->id;
			
			$extension	= 'com_content';
			$canEdit		= $user->authorise('core.edit', $extension.'.category.'.$row->id);
			$canEditOwn	= $user->authorise('core.edit.own', $extension.'.category.'.$row->id) && $row->created_user_id == $userId;
			$canEditState			= $user->authorise('core.edit.state', $extension.'.category.'.$row->id);
			$canEditStateOwn	= $user->authorise('core.edit.state.own', $extension.'.category.'.$row->id) && $row->created_user_id==$user->get('id');
			$canCheckin		= $user->authorise('core.admin', 'checkin') || $row->checked_out == $userId || $row->checked_out == 0;
			$canChange		= ($canEditState || $canEditStateOwn ) && $canCheckin;
			$published		= JHTML::_('jgrid.published', $row->published, $i, 'categories.', $canChange );
   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td><?php echo $this->pagination->getRowOffset( $i ); ?></td>
			<td width="7"><?php echo $checked; ?></td>
			<td align="left">
				<?php echo str_repeat('<span class="gi">|&mdash;</span>', $row->level-1) ?>
						<?php if ($row->checked_out) : ?>
							<?php echo JHtml::_('jgrid.checkedout', $i, $row->editor, $row->checked_out_time, 'categories.', $canCheckin); ?>
						<?php endif; ?>
						<?php if ($canEdit || $canEditOwn) : ?>
							<a href="<?php echo $link;?>">
								<?php echo $this->escape($row->title); ?></a>
						<?php else : ?>
							<?php echo $this->escape($row->title); ?>
						<?php endif; ?>
						<p class="smallsub" title="<?php echo $this->escape($row->path);?>">
							<?php echo str_repeat('<span class="gtr">|&mdash;</span>', $row->level-1) ?>
							<?php if (empty($row->note)) : ?>
								<?php echo JText::sprintf('JGLOBAL_LIST_ALIAS', $this->escape($row->alias));?>
							<?php else : ?>
								<?php echo JText::sprintf('JGLOBAL_LIST_ALIAS_NOTE', $this->escape($row->alias), $this->escape($row->note));?>
							<?php endif; ?></p>
			</td>
			<td>
				<?php
				if (JString::strlen($row->alias) > 25) {
					echo JString::substr( htmlspecialchars($row->alias, ENT_QUOTES, 'UTF-8'), 0 , 25).'...';
				} else {
					echo htmlspecialchars($row->alias, ENT_QUOTES, 'UTF-8');
				}
				?>
			</td>
			<td align="center">
				<?php echo $row->nrassigned?>
				<a href="<?php echo $items; ?>">
				[<?php echo JText::_( 'FLEXI_VIEW_ITEMS' );?>]
			</td>
			<td align="center">
				<?php echo $published; ?>
			</td>
			<td align="center">
				<?php echo $access; ?>
			</td>
			<td class="order">
			 <?php if ($canChange) : ?>
				<?php if ($saveOrder) : ?>
					<span><?php echo $this->pagination->orderUpIcon($i, isset($this->ordering[$row->parent_id][$orderkey - 1]), 'categories.orderup', 'JLIB_HTML_MOVE_UP', $this->orderingx); ?></span>
					<span><?php echo $this->pagination->orderDownIcon($i, $this->pagination->total, isset($this->ordering[$row->parent_id][$orderkey + 1]), 'categories.orderdown', 'JLIB_HTML_MOVE_DOWN', $this->orderingx); ?></span>
				<?php endif; ?>
				<?php $disabled = $saveOrder ?  '' : 'disabled="disabled"'; ?>
				<input type="text" name="order[]" size="5" value="<?php echo $orderkey + 1;?>" <?php echo $disabled ?> class="text-area-order" />
				<?php $originalOrders[] = $orderkey + 1; ?>
			<?php else : ?>
				<?php echo $orderkey + 1;?>
			<?php endif; ?>
			</td>
					<td class="center nowrap">
					<?php if ($row->language=='*'):?>
						<?php echo JText::alt('JALL','language'); ?>
					<?php else:?>
						<?php echo $row->language_title ? $this->escape($row->language_title) : JText::_('JUNDEFINED'); ?>
					<?php endif;?>
					</td>
					<td class="center">
						<span title="<?php echo sprintf('%d-%d', $item->lft, $item->rgt);?>">
							<?php echo (int) $row->id; ?></span>
					</td>
		</tr>
		<?php 
			$k = 1 - $k;
			$i++;
		} 
		?>
	</tbody>
	</table>

	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="com_flexicontent" />
	<!---input type="hidden" name="controller" value="categories" /--->
	<input type="hidden" name="view" value="categories" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<input type="hidden" name="original_order_values" value="<?php echo implode($originalOrders, ','); ?>" />
	<?php echo JHTML::_( 'form.token' ); ?>
</form>
