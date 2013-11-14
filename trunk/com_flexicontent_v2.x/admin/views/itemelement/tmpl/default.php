<?php
/**
 * @version 1.5 stable $Id: default.php 1267 2012-05-07 10:55:21Z ggppdk $
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
$app = JFactory::getApplication();
$option = JRequest::getVar('option');
$langparent_item  = $app->getUserStateFromRequest( $option.'.itemelement.langparent_item', 'langparent_item', 0, 'int' );
$type_id  = $app->getUserStateFromRequest( $option.'.itemelement.type_id', 'type_id', 0, 'int' );
?>

<form action="index.php?option=com_flexicontent&amp;view=itemelement&amp;tmpl=component&object=<?= JRequest::getVar('object',''); ?>" method="post" name="adminForm" id="adminForm">

<table class="adminform">
	<tr>
		<td width="100%">
			<?php echo JText::_( 'FLEXI_SEARCH' ); ?>
			<input type="text" name="search" id="search" value="<?php echo $this->lists['search']; ?>" class="text_area" onChange="document.adminForm.submit();" />
			<div id="fc-filter-buttons">
				<button onclick="this.form.submit();"><?php echo JText::_( 'FLEXI_GO' ); ?></button>
				<button onclick="this.form.getElementById('search').value='';this.form.submit();"><?php echo JText::_( 'FLEXI_RESET' ); ?></button>
			</div>
		</td>
		<td nowrap="nowrap">
			<?php if ( !$type_id) : ?>
				<?php echo $this->lists['filter_type'];	?>
			<?php endif; ?>
			<?php echo $this->lists['filter_cats'];	?>
			<?php if ((FLEXI_FISH || FLEXI_J16GE) && !$langparent_item) : ?>
				<?php echo $this->lists['filter_lang']; ?>
			<?php endif; ?>
			<?php echo $this->lists['state'];	?>
		</td>
	</tr>
</table>



	<table class="adminlist" cellspacing="1">
	<thead>
		<tr>
			<th width="5"><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			<th class="title">
				<?php echo JHTML::_('grid.sort', 'FLEXI_TITLE', 'i.title', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>
			<th width="1%" nowrap="nowrap">
				<?php echo JHTML::_('grid.sort', 'FLEXI_TYPE_NAME', 'type_name', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>
			<?php if (FLEXI_FISH || FLEXI_J16GE) : ?>
			<th width="50px" nowrap="nowrap">
				<?php echo JHTML::_('grid.sort', 'FLEXI_LANGUAGE', 'lang', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>
			<?php endif; ?>
			<th width="1%" nowrap="nowrap"><?php echo JText::_( 'FLEXI_STATE' ); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JHTML::_('grid.sort', 'FLEXI_ID', 'i.id', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
		</tr>
	</thead>

	<tfoot>
		<tr>
			<td colspan="<?php echo (FLEXI_FISH || FLEXI_J16GE) ? 5 : 4; ?>">
				<?php echo $this->pageNav->getListFooter(); ?>
			</td>
		</tr>
	</tfoot>

	<tbody>
		<?php
		$k = 0;
		for ($i=0, $n=count($this->rows); $i < $n; $i++) {
			$row = $this->rows[$i];
			
				if ( $row->state == 1 ) {
					$img = 'tick.png';
					$alt = JText::_( 'FLEXI_PUBLISHED' );
					$state = 1;
				} else if ( $row->state == 0 ) {
					$img = 'publish_x.png';
					$alt = JText::_( 'FLEXI_UNPUBLISHED' );
					$state = 0;
				} else if ( $row->state == -1 ) {
					$img = 'disabled.png';
					$alt = JText::_( 'FLEXI_ARCHIVED' );
					$state = -1;
				} else if ( $row->state == -3 ) {
					$img = 'publish_r.png';
					$alt = JText::_( 'FLEXI_PENDING' );
					$state = -3;
				} else if ( $row->state == -4 ) {
					$img = 'publish_y.png';
					$alt = JText::_( 'FLEXI_TO_WRITE' );
					$state = -4;
				} else if ( $row->state == -5 ) {
					$img = 'publish_g.png';
					$alt = JText::_( 'FLEXI_IN_PROGRESS' );
					$state = -5;
				} else {
					$img = '';
				}
   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td><?php echo $this->pageNav->getRowOffset( $i ); ?></td>
			<td align="left">
					<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' );?>::<?php echo $row->title; ?>">
					<?php if(JRequest::getVar('object','')==''): ?>
					<a style="cursor:pointer" onclick="window.parent.qfSelectItem('<?php echo $row->id; ?>', '<?php echo $this->filter_cats ? $this->filter_cats : $row->catid; ?>', '<?php echo str_replace( array("'", "\""), array("\\'", ""), $row->title ); ?>');">
					<?php else: ?>
					<a style="cursor:pointer" onclick="window.parent.jSelectArticle('<?php echo $row->id; ?>', '<?php echo str_replace( array("'", "\""), array("\\'", ""), $row->title ); ?>', '<?php echo JRequest::getVar('object',''); ?>');">
					<?php endif; ?>
					<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
					</a></span>
			</td>
			
			<td align="center" class="col_type">
				<?php echo $row->type_name; ?>
			</td>
			
		<?php if ( (FLEXI_FISH || FLEXI_J16GE) ): ?>
			<td align="center" class="hasTip col_lang" title="<?php echo JText::_( 'FLEXI_LANGUAGE' ).'::'.($row->lang=='*' ? JText::_("All") : (!empty($row->lang) ? $this->langs->{$row->lang}->name : '')); ?>">
				
				<?php if ( !empty($row->lang) && !empty($this->langs->{$row->lang}->imgsrc) ) : ?>
					<img src="<?php echo $this->langs->{$row->lang}->imgsrc; ?>" alt="<?php echo $row->lang; ?>" />
				<?php elseif( !empty($row->lang) ) : ?>
					<?php echo $row->lang=='*' ? JText::_("All") : $row->lang;?>
				<?php endif; ?>
				
			</td>
		<?php endif; ?>
					
			<td align="center">
				<?php if ($img) : ?>
					<img src="<?php  echo $app->isAdmin() ? "../" : ""; ?>components/com_flexicontent/assets/images/<?php echo $img;?>" width="16" height="16" border="0" alt="<?php echo $alt; ?>" title="<?php echo $alt; ?>" />
				<?php endif; ?>
			</td>
			<td align="center"><?php echo $row->id; ?></td>
		</tr>
		<?php $k = 1 - $k; } ?>
	</tbody>

	</table>
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
</form>