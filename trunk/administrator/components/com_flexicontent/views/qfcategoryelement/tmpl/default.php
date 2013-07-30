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

defined('_JEXEC') or die('Restricted access');
?>
<form action="index.php?option=com_flexicontent&amp;view=qfcategoryelement&amp;tmpl=component" method="post" name="adminForm" id="adminForm">

	<table class="adminform">
		<tr>
			<td width="100%">
			  	<?php echo JText::_( 'FLEXI_SEARCH' ); ?>
				<input type="text" name="search" id="search" value="<?php echo $this->lists['search']; ?>" class="text_area" onChange="document.adminForm.submit();" />
				<button onclick="this.form.submit();"><?php echo JText::_( 'FLEXI_GO' ); ?></button>
				<button onclick="this.form.getElementById('search').value='';this.form.submit();"><?php echo JText::_( 'FLEXI_RESET' ); ?></button>
			</td>
		</tr>
		<tr>
			<td nowrap="nowrap">
				<div class="filter-select fltrt">
					<?php echo $this->lists['cats']; ?>
					<?php echo $this->lists['level']; ?>
					<?php echo $this->lists['state']; ?>
					<?php echo $this->lists['access']; ?>
				  <?php if (FLEXI_J16GE) echo $this->lists['language']; ?>
				</div>
			</td>
		</tr>
	</table>

	<table class="adminlist" cellspacing="1">
	<thead>
		<tr>
			<th width="5"><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			<th class="title"><?php echo JHTML::_('grid.sort', 'FLEXI_CATEGORY', 'c.title', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width=""><?php echo JText::_( 'FLEXI_TEMPLATE' ); ?></th>
			<th width="10%"><?php echo JHTML::_('grid.sort', 'FLEXI_ITEMS_ASSIGNED', 'nrassigned', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JText::_( 'FLEXI_PUBLISHED' ); ?></th>
			<th width="7%"><?php echo JHTML::_('grid.sort', 'FLEXI_ACCESS', 'c.access', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
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
		foreach ($this->rows as $row)
		{
			if (FLEXI_J16GE) {
				$access		= $row->access_level;
			} else {
				$access 	= $row->groupname;
			}
			if (FLEXI_J16GE) {
				$published		= JHTML::_('jgrid.published', $row->published, $i, 'categories.', $canChange=0 );
			} else {
				$img  = $row->published ? 'tick.png' : 'publish_x.png';
        $alt    = $row->published ? JText::_('Published') : JText::_('Unpublished');
				$published 	= JHTML::_('image','admin/'.$img, $alt, array('border' => 0), true);
				//$published 	= JHTML::_('grid.published', $row, $i );
			}
   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td><?php echo $this->pagination->getRowOffset( $i ); ?></td>
			<td align="left" class="col_title">
				<?php
				if (FLEXI_J16GE) {
					if ($row->level>1) echo str_repeat('.&nbsp;&nbsp;&nbsp;', $row->level-1)."<sup>|_</sup>";
				} else {
					echo $row->treename.' ';
				}
				?>
				<a style="cursor:pointer" onclick="window.parent.qfSelectCategory('<?php echo $row->id; ?>', '<?php echo str_replace( array("'", "\""), array("\\'", ""), $row->title ); ?>');">
					<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
				</a>
			</td>
			<td align="center">
				<?php echo ($row->config->get('clayout') ? $row->config->get('clayout') : "blog <sup>[1]</sup>") ?>
			</td>
			<td align="center">
				<?php echo $row->nrassigned?>
			</td>
			<td align="center">
				<?php echo $published; ?>
			</td>
			<td align="center">
				<?php echo $access; ?>
			</td>
			<td align="center">
				<?php echo $row->id; ?>
			</td>
		</tr>
		<?php 
			$k = 1 - $k;
			$i++;
		} 
		?>
	</tbody>

	</table>
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
</form>