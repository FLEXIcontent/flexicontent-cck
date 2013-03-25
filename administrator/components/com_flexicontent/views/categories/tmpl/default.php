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

$user      = JFactory::getUser();
$cparams   = JComponentHelper::getParams( 'com_flexicontent' );
$autologin = $cparams->get('autoflogin', 1) ? '&fcu='.$user->username . '&fcp='.$user->password : '';


$image_zoom = '<img style="float:right;" src="components/com_flexicontent/assets/images/monitor_go.png" width="16" height="16" border="0" class="hasTip" alt="'.JText::_('FLEXI_PREVIEW').'" title="'.JText::_('FLEXI_PREVIEW').':: Click to display the frontend view of this item in a new browser window" />';

$image_flag_path = !FLEXI_J16GE ? "../components/com_joomfish/images/flags/" : "../media/mod_languages/images/";
$infoimage  = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/lightbulb.png', JText::_( 'FLEXI_NOTES' ) );
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
				<?php echo $this->lists['state']; ?>
			</td>
		</tr>
	</table>

	<table class="adminlist" cellspacing="1">
	<thead>
		<tr>
			<th width="5"><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			<th width="5"><input type="checkbox" name="toggle" value="" onClick="checkAll(<?php echo count( $this->rows ); ?>);" /></th>
			<th width="1%" nowrap="nowrap">&nbsp;</th>
			<th class="title"><?php echo JHTML::_('grid.sort', 'FLEXI_CATEGORY', 'c.title', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="20%"><?php echo JHTML::_('grid.sort', 'FLEXI_ALIAS', 'c.alias', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width=""><?php echo JText::_( 'FLEXI_TEMPLATE' ); ?></th>
			<th width="10%"><?php echo JHTML::_('grid.sort', 'FLEXI_ITEMS_ASSIGNED', 'nrassigned', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JText::_( 'FLEXI_PUBLISHED' ); ?></th>
			<th width="7%"><?php echo JHTML::_('grid.sort', 'FLEXI_ACCESS', 'c.access', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="90">
				<?php echo JHTML::_('grid.sort', 'FLEXI_REORDER', 'c.ordering', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php echo $this->ordering ? JHTML::_('grid.order', $this->rows, 'filesave.png', 'saveorder' ) : ''; ?>
			</th>
			<th width="1%" nowrap="nowrap"><?php echo JHTML::_('grid.sort', 'FLEXI_ID', 'c.id', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
		</tr>
	</thead>

	<tfoot>
		<tr>
			<td colspan="11">
				<?php echo $this->pagination->getListFooter(); ?>
			</td>
		</tr>
	</tfoot>

	<tbody>
		<?php
		$k = 0;
		$i = 0;
		$n = count($this->rows);
		foreach ($this->rows as $row) {

			$link 		= 'index.php?option=com_flexicontent&amp;controller=categories&amp;task=edit&amp;cid[]='. $row->id;
			$published 	= JHTML::_('grid.published', $row, $i );
			if (FLEXI_ACCESS) {
				if ($this->CanRights) {
					$access 	= FAccess::accessswitch('category', $row, $i);
				} else {
					$access 	= FAccess::accessswitch('category', $row, $i, 'content', 1);
				}
			} else {
				$access 	= JHTML::_('grid.access', $row, $i );
			}
			$checked 	= JHTML::_('grid.checkedout', $row, $i );
			$items		= 'index.php?option=com_flexicontent&amp;view=items&amp;filter_cats='. $row->id;
			$canEdit    = 1;
			$canEditOwn = 1;
   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td><?php echo $this->pagination->getRowOffset( $i ); ?></td>
			<td width="7"><?php echo $checked; ?></td>
			<td width="1%" >
				<?php
				$previewlink = JRoute::_(JURI::root() . FlexicontentHelperRoute::getCategoryRoute($row->id)) . $autologin;
				echo '<a class="preview" href="'.$previewlink.'" target="_blank">'.$image_zoom.'</a>';
				?>
			</td>
			<td align="left" class="col_title">
				<?php
				if (FLEXI_J16GE) {
					if ($row->level>1) echo str_repeat('.&nbsp;&nbsp;&nbsp;', $row->level-1)."<sup>|_</sup>";
				} else {
					echo $row->treename.' ';
				}
				
				// Display an icon with checkin link, if current user has checked out current item
				if ($row->checked_out) {
					if (FLEXI_J16GE) {
						$canCheckin = $user->authorise('core.admin', 'checkin');
					} else if (FLEXI_ACCESS) {
						$canCheckin = ($user->gid < 25) ? FAccess::checkComponentAccess('com_checkin', 'manage', 'users', $user->gmid) : 1;
					} else {
						$canCheckin = $user->gid >= 24;
					}
					if ($canCheckin) {
						//if (FLEXI_J16GE && $row->checked_out == $user->id) echo JHtml::_('jgrid.checkedout', $i, $row->editor, $row->checked_out_time, 'categories.', $canCheckin);
						$task_str = FLEXI_J16GE ? 'categories.checkin' : 'checkin';
						if ($row->checked_out == $user->id) {
							echo JText::sprintf('FLEXI_CLICK_TO_RELEASE_YOUR_LOCK', $row->editor, $row->checked_out_time, '"cb'.$i.'"', '"'.$task_str.'"');
						} else {
							echo '<input id="cb'.$i.'" type="checkbox" value="'.$row->id.'" name="cid[]" style="display:none;">';
							echo JText::sprintf('FLEXI_CLICK_TO_RELEASE_FOREIGN_LOCK', $row->editor, $row->checked_out_time, '"cb'.$i.'"', '"'.$task_str.'"');
						}
					}
				}
				
				// Display title with no edit link ... if row checked out by different user -OR- is uneditable
				if ( ( $row->checked_out && $row->checked_out != $user->id ) || ( !$canEdit && !$canEditOwn ) ) {
					echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8');
				
				// Display title with edit link ... (row editable and not checked out)
				} else {
				?>
					<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_CATEGORY' );?>::<?php echo $row->alias; ?>">
					<a href="<?php echo $link; ?>">
					<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
					</a></span>
				<?php
				}
				?>
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
				<?php echo ($row->config->get('clayout') ? $row->config->get('clayout') : "blog <sup>[1]</sup>") ?>
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
				<span><?php echo $this->pagination->orderUpIcon( $i, true, 'orderup', 'Move Up', $this->ordering ); ?></span>

				<span><?php echo $this->pagination->orderDownIcon( $i, $n, true, 'orderdown', 'Move Down', $this->ordering );?></span>

				<?php $disabled = $this->ordering ?  '' : '"disabled=disabled"'; ?>

				<input type="text" name="order[]" size="5" value="<?php echo $row->ordering; ?>" <?php echo $disabled; ?> class="text_area" style="text-align: center" />
			</td>
			<td align="center"><?php echo $row->id; ?></td>
		</tr>
		<?php 
			$k = 1 - $k;
			$i++;
		} 
		?>
	</tbody>

	</table>
  
  <sup>[1]</sup> Params not saved yet, default values will be used.
  
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="categories" />
	<input type="hidden" name="view" value="categories" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<?php echo JHTML::_( 'form.token' ); ?>
</form>