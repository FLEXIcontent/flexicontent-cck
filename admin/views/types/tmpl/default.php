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

$tip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
$btn_class = FLEXI_J30GE ? 'btn' : 'fc_button fcsimple';

$start_text = '<span class="label">'.JText::_('FLEXI_COLUMNS', true).'</span>';
$end_text = '<div class="icon-arrow-up-2" title="'.JText::_('FLEXI_HIDE').'" style="cursor: pointer;" onclick="fc_toggle_box_via_btn(\\\'mainChooseColBox\\\', document.getElementById(\\\'fc_mainChooseColBox_btn\\\'), \\\'btn-primary\\\');"></div>';
flexicontent_html::jscode_to_showhide_table('mainChooseColBox', 'adminListTableFCtypes', $start_text, $end_text);

$edit_entry = JText::_('FLEXI_EDIT_TYPE', true);

$user    = JFactory::getUser();
$cparams = JComponentHelper::getParams( 'com_flexicontent' );

$list_total_cols = 10;

$article_viewing_tip = '<img src="components/com_flexicontent/assets/images/comment.png" class="fc-padded-image '.$tip_class.'" data-placement="bottom" alt="'.JText::_('FLEXI_REDIRECT_ARTICLE_VIEW').'" title="'.flexicontent_html::getToolTip('FLEXI_REDIRECT_ARTICLE_VIEW', 'FLEXI_REDIRECT_ARTICLE_VIEW_DESC', 1, 1).'" /> ';
$default_template_tip = '<img src="components/com_flexicontent/assets/images/comment.png" class="fc-padded-image '.$tip_class.'" data-placement="bottom" alt="'.JText::_( 'FLEXI_PROPERTY_DEFAULT' )." ".JText::_( 'FLEXI_TEMPLATE_ITEM' ).'" title="'.flexicontent_html::getToolTip('FLEXI_TYPE_DEFAULT_TEMPLATE', 'FLEXI_TYPE_DEFAULT_TEMPLATE_DESC', 1, 1).'" /> ';
?>
<script type="text/javascript">

// delete active filter
function delFilter(name)
{
	//if(window.console) window.console.log('Clearing filter:'+name);
	var myForm = jQuery('#adminForm');
	var filter = jQuery('#'+name);
	if (filter.attr('type')=='checkbox')
		filter.checked = '';
	else
		filter.val('');
}

function delAllFilters() {
	delFilter('search'); delFilter('filter_state');  delFilter('filter_access');
}

</script>

<div class="flexicontent">

<form action="index.php?option=<?php echo $this->option; ?>&view=<?php echo $this->view; ?>" method="post" name="adminForm" id="adminForm">

<?php if (!empty( $this->sidebar)) : ?>
	<div id="j-sidebar-container" class="span2">
		<?php echo $this->sidebar; ?>
	</div>
	<div id="j-main-container" class="span10">
<?php else : ?>
	<div id="j-main-container">
<?php endif;?>

	<div id="fc-filters-header">
		<span class="fc-filter nowrap_box">
			<span class="filter-search btn-group">
				<input type="text" name="search" id="search" placeholder="<?php echo JText::_( 'FLEXI_SEARCH' ); ?>" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="inputbox" />
			</span>
			<span class="btn-group hidden-phone">
				<button title="<?php echo JText::_('FLEXI_APPLY_FILTERS'); ?>" class="<?php echo $btn_class; ?>" onclick="this.form.submit();"><?php echo FLEXI_J30GE ? '<i class="icon-search"></i>' : JText::_('FLEXI_GO'); ?></button>
				<button title="<?php echo JText::_('FLEXI_RESET_FILTERS'); ?>" class="<?php echo $btn_class; ?>" onclick="delAllFilters();this.form.submit();"><?php echo FLEXI_J30GE ? '<i class="icon-remove"></i>' : JText::_('FLEXI_CLEAR'); ?></button>
			</span>
		</span>
		
		<?php $_class = FLEXI_J30GE ? ' btn' : ' fc_button fcsimple fcsmall'; ?>
		<div class="btn-group" style="margin: 2px 32px 6px -3px; display:inline-block;">
			<input type="button" id="fc_filters_box_btn" class="<?php echo $_class.($this->count_filters ? ' btn-primary' : ''); ?>" onclick="fc_toggle_box_via_btn('fc-filters-box', this, 'btn-primary');" value="<?php echo JText::_( 'FLEXI_FILTERS' ); ?>" />
			<input type="button" id="fc_mainChooseColBox_btn" class="<?php echo $_class; ?>" onclick="fc_toggle_box_via_btn('mainChooseColBox', this, 'btn-primary');" value="<?php echo JText::_( 'FLEXI_COLUMNS' ); ?>" />
		</div>
		
		<span class="fc-filter nowrap_box">
			<span class="limit nowrap_box" style="display: inline-block;">
				<label class="label">
					<?php echo JText::_(FLEXI_J16GE ? 'JGLOBAL_DISPLAY_NUM' : 'DISPLAY NUM'); ?>
				</label>
				<?php
				$pagination_footer = $this->pagination->getListFooter();
				if (strpos($pagination_footer, '"limit"') === false) echo $this->pagination->getLimitBox();
				?>
			</span>
			
			<span class="fc_item_total_data nowrap_box badge badge-info">
				<?php echo @$this->resultsCounter ? $this->resultsCounter : $this->pagination->getResultsCounter(); // custom Results Counter ?>
			</span>
			
			<?php if (($getPagesCounter = $this->pagination->getPagesCounter())): ?>
			<span class="fc_pages_counter nowrap_box fc-mssg-inline fc-info fc-nobgimage">
				<?php echo $getPagesCounter; ?>
			</span>
			<?php endif; ?>
		</span>
	</div>
	
	
	<div id="fc-filters-box" <?php if (!$this->count_filters) echo 'style="display:none;"'; ?> class="">
		<!--<span class="label"><?php echo JText::_( 'FLEXI_FILTERS' ); ?></span>-->
		
		<span class="fc-filter nowrap_box">
			<?php echo $this->lists['state']; ?>
		</span>
		
		<span class="fc-filter nowrap_box">
			<?php echo $this->lists['access']; ?>
		</span>
		
		<div class="icon-arrow-up-2" title="<?php echo JText::_('FLEXI_HIDE'); ?>" style="cursor: pointer;" onclick="fc_toggle_box_via_btn('fc-filters-box', document.getElementById('fc_filters_box_btn'), 'btn-primary');"></div>
	</div>
	
	<div id="mainChooseColBox" class="fc_mini_note_box well well-small" style="display:none;"></div>
	
	<div class="fcclear"></div>
	
	<table id="adminListTableFCtypes" class="adminlist fcmanlist">
	<thead>
		<tr>
			<th><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			<th><input type="checkbox" name="toggle" value="" onclick="<?php echo FLEXI_J30GE ? 'Joomla.checkAll(this);' : 'checkAll('.count( $this->rows).');'; ?>" /></th>
			<th class="hideOnDemandClass title"><?php echo JHTML::_('grid.sort', 'FLEXI_TYPE_NAME', 't.name', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass"><?php echo $article_viewing_tip . JText::_( 'FLEXI_REDIRECT_ARTICLE_VIEW' )."<br/><small>(".JText::_( 'FLEXI_ALLOWED') .' / '. JText::_( 'FLEXI_REDIRECTED' ).")</small>"; ?></th>
			<th class="hideOnDemandClass"><?php echo $default_template_tip.JText::_( 'FLEXI_TEMPLATE' )."<br/><small>(".JText::_( 'FLEXI_PROPERTY_DEFAULT' )." ".JText::_( 'FLEXI_TEMPLATE_ITEM' ).")</small>"; ?></th>
			<th class="hideOnDemandClass"><?php echo JHTML::_('grid.sort', 'FLEXI_ALIAS', 't.alias', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass"><?php echo JHTML::_('grid.sort', 'FLEXI_FIELDS', 'fassigned', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass"><?php echo JHTML::_('grid.sort', 'FLEXI_ITEMS', 'iassigned', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<!--th class="hideOnDemandClass"><?php // echo JHTML::_('grid.sort', 'ITEMS', 'iassigned', $this->lists['order_Dir'], $this->lists['order'] ); ?></th-->
			<th class="hideOnDemandClass"><?php echo JHTML::_('grid.sort', 'FLEXI_ACCESS', 't.access', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th class="hideOnDemandClass" nowrap="nowrap"><?php echo JText::_( 'FLEXI_PUBLISHED' ); ?></th>
			<th class="hideOnDemandClass" nowrap="nowrap"><?php echo JHTML::_('grid.sort', 'FLEXI_ID', 't.id', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
		</tr>
	</thead>

	<tfoot>
		<tr>
			<td colspan="<?php echo $list_total_cols; ?>">
				<?php echo $pagination_footer; ?>
			</td>
		</tr>
	</tfoot>

	<tbody>
		<?php
		if (FLEXI_J16GE) {
			$canCheckinRecords = $user->authorise('core.admin', 'checkin');
		} else if (FLEXI_ACCESS) {
			$canCheckinRecords = ($user->gid < 25) ? FAccess::checkComponentAccess('com_checkin', 'manage', 'users', $user->gmid) : 1;
		} else {
			$canCheckinRecords = $user->gid >= 24;
		}
		
		$k = 0;
		
		if (!count($this->rows)) echo '<tr class="collapsed_row"><td colspan="'.$list_total_cols.'"></td></tr>';  // Collapsed row to allow border styling to apply		$k = 0;
		for ($i=0, $n=count($this->rows); $i < $n; $i++)
		{
			$row = & $this->rows[$i];
			if (FLEXI_J16GE) {
				$link 		= 'index.php?option=com_flexicontent&amp;task=types.edit&amp;cid[]='. $row->id;
				$published 	= JHTML::_('jgrid.published', $row->published, $i, 'types.' );
				$access		= flexicontent_html::userlevel('access['.$row->id.']', $row->access, 'onchange="return listItemTask(\'cb'.$i.'\',\'types.access\')"');
			} else {
				$link 		= 'index.php?option=com_flexicontent&amp;controller=types&amp;task=edit&amp;cid[]='. $row->id;
				$published 	= JHTML::_('grid.published', $row, $i );
				$access 	= JHTML::_('grid.access', $row, $i );
			}
			
			$checked	= @ JHTML::_('grid.checkedout', $row, $i );
			$fields		= 'index.php?option=com_flexicontent&amp;view=fields&amp;filter_type='. $row->id;
			$items		= 'index.php?option=com_flexicontent&amp;view=items&amp;filter_type='. $row->id;
			$canEdit    = 1;
			$canEditOwn = 1;
   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td><?php echo $this->pagination->getRowOffset( $i ); ?></td>
			<td><?php echo $checked; ?></td>
			<td align="left">
				<?php
				
				// Display an icon with checkin link, if current user has checked out current item
				if ($row->checked_out) {
					// Record check-in is allowed if either (a) current user has Global Checkin privilege OR (b) record checked out by current user
					$canCheckin = $canCheckinRecords || $row->checked_out == $user->id;
					if ($canCheckin) {
						//if (FLEXI_J16GE && $row->checked_out == $user->id) echo JHtml::_('jgrid.checkedout', $i, $row->editor, $row->checked_out_time, 'types.', $canCheckin);
						$task_str = 'types.checkin';
						if ($row->checked_out == $user->id) {
							$_tip_title = JText::sprintf('FLEXI_CLICK_TO_RELEASE_YOUR_LOCK_DESC', $row->editor, $row->checked_out_time);
						} else {
							echo '<input id="cb'.$i.'" type="checkbox" value="'.$row->id.'" name="cid[]" style="display:none!important;">';
							$_tip_title = JText::sprintf('FLEXI_CLICK_TO_RELEASE_FOREIGN_LOCK_DESC', $row->editor, $row->checked_out_time);
						}
						?>
						<a class="jgrid <?php echo $tip_class; ?>" title="<?php echo $_tip_title; ?>" href="javascript:;" onclick="var ccb=document.getElementById('cb<?php echo $i;?>'); ccb.checked=1; ccb.form.task.value='<?php echo $task_str; ?>'; ccb.form.submit();">
							<img src="components/com_flexicontent/assets/images/lock_delete.png" alt="Check-in" />
						</a>
						<?php
					} else {
						echo '<span class="fc-noauth">'.JText::sprintf('FLEXI_RECORD_CHECKED_OUT_DIFF_USER').'</span><br/>';
					}
				}
				
				// Display title with no edit link ... if row checked out by different user -OR- is uneditable
				if ( ( $row->checked_out && $row->checked_out != $user->id ) || ( !$canEdit ) ) {
					echo htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8');
				
				// Display title with edit link ... (row editable and not checked out)
				} else {
				?>
					<a href="<?php echo $link; ?>" title="<?php echo $edit_entry; ?>">
						<?php echo htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8'); ?>
					</a>
				<?php
				}
				?>
			</td>
			<td align="center">
				<span style="cursor:pointer;"
					<?php
					echo ' class="'.$btn_class.' btn-small" onclick="return listItemTask(\'cb'.$i.'\',\'types.toggle_jview\');">';
					echo JText::_($row->config->get("allow_jview") ? 'FLEXI_ALLOWED' : 'FLEXI_REDIRECT_TO_ITEM_VIEW');
					?>
				</span>
			</td>
			<td align="center">
				<?php echo $row->config->get("ilayout"); ?>
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
				<span class="badge"><?php echo $row->fassigned; ?></span>
				<a href="<?php echo $fields; ?>">
				[<?php echo JText::_( 'FLEXI_VIEW_FIELDS' );?>]
				</a>
			</td>
			<td align="center">
				<span class="badge badge-info"><?php echo $row->iassigned; ?></span>
				<a href="<?php echo $items; ?>">
				[<?php echo JText::_( 'FLEXI_VIEW_ITEMS' );?>]
				</a>
			</td>
			<td align="center">
				<?php echo $access; ?>
			</td>
			<td align="center">
				<?php echo $published; ?>
			</td>
			<td align="center"><?php echo $row->id; ?></td>
		</tr>
		<?php $k = 1 - $k; } ?>
	</tbody>

	</table>

	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="types" />
	<input type="hidden" name="view" value="types" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<?php echo JHTML::_( 'form.token' ); ?>
	
	</div>
</form>
</div>