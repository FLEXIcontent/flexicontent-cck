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

$ctrl = FLEXI_J16GE ? 'fields.' : '';
$fields_task = FLEXI_J16GE ? 'task=fields.' : 'controller=fields&task=';
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
				<?php echo $this->lists['assigned']; ?>
				<?php echo $this->lists['fftype']; ?>
				<?php echo $this->lists['filter_type']; ?>
				<?php echo $this->lists['state']; ?>
			</td>
		</tr>
	</table>

	<table class="adminlist" cellspacing="1">
	<thead>
		<tr>
			<th width="5"><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			<th width="5"><input type="checkbox" name="toggle" value="" onClick="checkAll(<?php echo count( $this->rows ); ?>);" /></th>
			<th class="title"><?php echo JHTML::_('grid.sort', 'FLEXI_FIELD_LABEL', 't.label', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="title"><?php echo JHTML::_('grid.sort', 'FLEXI_FIELD_NAME', 't.name', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="title"><?php echo JHTML::_('grid.sort', 'FLEXI_FIELD_TYPE', 't.field_type', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="30%"><?php echo JHTML::_('grid.sort', 'FLEXI_FIELD_DESCRIPTION', 't.description', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="1%"><?php echo JHTML::_('grid.sort', 'FLEXI_FIELD_ISFILTER', 't.isfilter', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="1%"><?php echo JHTML::_('grid.sort', 'FLEXI_FIELD_IS_SEARCHABLE', 't.issearch', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="1%"><?php echo JHTML::_('grid.sort', 'FLEXI_FIELD_IS_ADVANCED_SEARCHABLE', 't.isadvsearch', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="20"><?php echo JHTML::_('grid.sort', 'FLEXI_ASSIGNED_TYPES', 'nrassigned', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="7%"><?php echo JHTML::_('grid.sort', 'FLEXI_ACCESS', 't.access', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JHTML::_('grid.sort', 'FLEXI_PUBLISHED', 't.published', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="<?php echo $this->permission->CanOrderFields ? '90' : '60'; ?>" class="center">
				<?php if ($this->filter_type == '' || $this->filter_type == 0) : ?>
					<?php echo JHTML::_('grid.sort', 'FLEXI_GLOBAL_ORDER', 't.ordering', $this->lists['order_Dir'], $this->lists['order'] ); ?>
					<?php
					if ($this->permission->CanOrderFields) :
						echo $this->ordering ? JHTML::_('grid.order', $this->rows, 'filesave.png', $ctrl.'saveorder' ) : '';
					endif;
					?>
				<?php else : ?>
					<?php echo JHTML::_('grid.sort', 'FLEXI_TYPE_ORDER', 'typeordering', $this->lists['order_Dir'], $this->lists['order'] ); ?>
					<?php
					if ($this->permission->CanOrderFields) :
						echo $this->ordering ? JHTML::_('grid.order', $this->rows, 'filesave.png', $ctrl.'saveorder' ) : '';
					endif;
					?>
				<?php endif; ?>
			</th>
			<th width="1%" nowrap="nowrap"><?php echo JHTML::_('grid.sort', 'FLEXI_ID', 't.id', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
		</tr>
	</thead>

	<tfoot>
		<tr>
			<td colspan="14">
				<?php echo $this->pageNav->getListFooter(); ?>
			</td>
		</tr>
	</tfoot>

	<tbody>
		<?php
		$k = 0;
		$i = 0;
		$user =& $this->user;
		$n = count($this->rows);
		
		$padcount = 0;
		foreach($this->rows as $row) {
			$padspacer = '';
			
			if ($row->field_type=='groupmarker') {
				$grpm_params = new JParameter($row->attribs);
				if ( in_array ($grpm_params->get('marker_type'), array( 'tabset_start', 'tabset_end' ) ) ) {
					$row_css = 'color:black;';
				} else if ( in_array ($grpm_params->get('marker_type'), array( 'tab_open', 'fieldset_open' ) ) ) {
					$row_css = 'color:darkgreen;';
					for ($icnt=0; $icnt < $padcount; $icnt++) $padspacer .= "&nbsp;|_&nbsp;";
					$padcount++;
				} else if ( in_array ($grpm_params->get('marker_type'), array( 'tab_close', 'fieldset_close' ) ) ) {
					$row_css = 'color:darkred;';
					$padcount--;
					for ($icnt=0; $icnt < $padcount; $icnt++) $padspacer .= "&nbsp;|_&nbsp;";
				}
			} else {
				$row_css = '';
				for ($icnt=0; $icnt < $padcount; $icnt++) $padspacer .= "&nbsp;|_&nbsp;";
			}
			
			//$rights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'field', $row->id);
				
			$canEdit			= 1; //in_array('editfield', $rights);
			$canPublish		= 1; //in_array('publishfield', $rights);
			$canDelete		= 1; //in_array('deletefield', $rights);
			
			$link 		= 'index.php?option=com_flexicontent&'.$fields_task.'edit&cid[]='. $row->id;
			if ($row->id < 7) {  // First 6 core field are not unpublishable
				$published 	= JHTML::image( 'administrator/components/com_flexicontent/assets/images/tick_f2.png', JText::_ ( 'FLEXI_NOT_AVAILABLE' ) );
			} else if (!$canPublish && $row->published) {   // No privilige published
				$published 	= JHTML::image( 'administrator/components/com_flexicontent/assets/images/tick_f2.png', JText::_ ( 'FLEXI_NOT_AVAILABLE' ) );
			} else if (!$canPublish && !$row->published) {   // No privilige unpublished
				$published 	= JHTML::image( 'administrator/components/com_flexicontent/assets/images/publish_x_f2.png', JText::_ ( 'FLEXI_NOT_AVAILABLE' ) );
			} else {
				if (FLEXI_J16GE)
					$published 	= JHTML::_('jgrid.published', $row->published, $i, $ctrl );
				else
					$published 	= JHTML::_('grid.published', $row, $i );
			}
			
			if( $row->isfilter ) {
				$isfilter = "tick_f2.png";
			}else{
				$isfilter = "publish_x_f2.png";
			}
			
			if( $row->issearch ) {
				$issearch = "tick_f2.png";
			}else{
				$issearch = "publish_x_f2.png";
			}
			
			if( $row->isadvsearch ) {
				$isadvsearch = "tick_f2.png";
			}else{
				$isadvsearch = "publish_x_f2.png";
			}
			
			if (FLEXI_ACCESS) {
				$access 	= FAccess::accessswitch('field', $row, $i);
			} else {
				$access 	= JHTML::_('grid.access', $row, $i );
			}
			$checked 	= JHTML::_('grid.checkedout', $row, $i );
			$warning	= '<span class="hasTip" title="'. JText::_ ( 'FLEXI_WARNING' ) .'::'. JText::_ ( 'FLEXI_NO_TYPES_ASSIGNED' ) .'">' . JHTML::image ( 'administrator/components/com_flexicontent/assets/images/error.png', JText::_ ( 'FLEXI_NO_TYPES_ASSIGNED' ) ) . '</span>';
   		?>
		<tr class="<?php echo "row$k"; ?>" style="<?php echo $row_css; ?>">
			<td><?php echo $this->pageNav->getRowOffset( $i ); ?></td>
			<td width="7"><?php echo $checked; ?></td>
			<td align="left">
				<?php
				echo $padspacer;
				if (
					( $row->checked_out && ( $row->checked_out != $this->user->get('id') ) )
					|| !$canEdit
				 ) {
					echo htmlspecialchars($row->label, ENT_QUOTES, 'UTF-8');
				} else {
				?>
					<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_FIELD' );?>::<?php echo $row->label; ?>">
					<a href="<?php echo $link; ?>">
					<?php echo htmlspecialchars($row->label, ENT_QUOTES, 'UTF-8'); ?>
					</a></span>
				<?php
				}
				?>
			</td>
			<td align="left">
				<?php echo $row->name; ?>
			</td>
			<td align="left">
				<?php $row->field_friendlyname = str_ireplace("FLEXIcontent - ","",$row->field_friendlyname); ?>
				<?php
				echo "<strong>".$row->type."</strong><br><small>-&nbsp;";
				echo $row->iscore?"[Core]" : "{$row->field_friendlyname}";
				echo "&nbsp;-</small>";
				?>
			</td>
			<td>
				<?php
				if (JString::strlen($row->description) > 50) {
					echo JString::substr( htmlspecialchars($row->description, ENT_QUOTES, 'UTF-8'), 0 , 50).'...';
				} else {
					echo htmlspecialchars($row->description, ENT_QUOTES, 'UTF-8');
				}
				?>
			</td>
			<td align="center"><img src="components/com_flexicontent/assets/images/<?php echo $isfilter;?>" width="16" height="16" border="0" alt="<?php echo ( $row->isfilter ) ? JText::_( 'FLEXI_YES' ) : JText::_( 'FLEXI_NO' );?>" /></td>
			<td align="center"><img src="components/com_flexicontent/assets/images/<?php echo $issearch;?>" width="16" height="16" border="0" alt="<?php echo ( $row->issearch ) ? JText::_( 'FLEXI_YES' ) : JText::_( 'FLEXI_NO' );?>" /></td>
			<td align="center"><img src="components/com_flexicontent/assets/images/<?php echo $isadvsearch;?>" width="16" height="16" border="0" alt="<?php echo ( $row->isadvsearch ) ? JText::_( 'FLEXI_YES' ) : JText::_( 'FLEXI_NO' );?>" /></td>
			<td align="center"><?php echo $row->nrassigned ? $row->nrassigned : $warning; ?></td>
			<td align="center">
				<?php echo $access; ?>
			</td>
			<td align="center">
				<?php echo $published; ?>
			</td>
			<?php if ($this->permission->CanOrderFields) : ?>
			<td class="order">
				<span><?php echo $this->pageNav->orderUpIcon( $i, true, $ctrl.'orderup', 'Move Up', $this->ordering ); ?></span>

				<span><?php echo $this->pageNav->orderDownIcon( $i, $n, true, $ctrl.'orderdown', 'Move Down', $this->ordering );?></span>

				<?php $disabled = $this->ordering ?  '' : '"disabled=disabled"'; ?>
				<?php if ($this->filter_type == '' || $this->filter_type == 0) : ?>
				<input type="text" name="order[]" size="5" value="<?php echo $row->ordering; ?>" <?php echo $disabled; ?> class="text_area" style="text-align: center" />
				<?php else : ?>
				<input type="text" name="order[]" size="5" value="<?php echo $row->typeordering; ?>" <?php echo $disabled; ?> class="text_area" style="text-align: center" />
				<?php endif; ?>
			</td>
			<?php else : ?>
			<td align="center">
				<?php
				if ($this->filter_type == '' || $this->filter_type == 0) {
					echo $row->ordering;
				} else {
					echo $row->typeordering;
				}
				?>
			</td>
			<?php endif; ?>
			<td align="center"><?php echo $row->id; ?></td>
		</tr>
		<?php $k = 1 - $k; $i++;} ?>
	</tbody>

	</table>

	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="fields" />
	<input type="hidden" name="view" value="fields" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<?php echo JHTML::_( 'form.token' ); ?>
</form>