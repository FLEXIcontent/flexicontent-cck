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
$hintmage = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/comment.png', JText::_( 'FLEXI_NOTES' ), ' align="left" ' );

$start_text = '<span class="label">'.JText::_('FLEXI_COLUMNS', true).'</span>';
$end_text = '<div class="icon-arrow-up-2" title="'.JText::_('FLEXI_HIDE').'" style="cursor: pointer;" onclick="fc_toggle_box_via_btn(\\\'mainChooseColBox\\\', document.getElementById(\\\'fc_mainChooseColBox_btn\\\'), \\\'btn-primary\\\');"></div>';
flexicontent_html::jscode_to_showhide_table('mainChooseColBox', 'adminListTableFCusers', $start_text, $end_text);


// Dates displayed in the item form, are in user timezone for J2.5, and in site's default timezone for J1.5
$site_zone = JFactory::getApplication()->getCfg('offset');
$user_zone = JFactory::getUser()->getParam('timezone', $site_zone);
if (FLEXI_J16GE) {
	$tz = new DateTimeZone( $user_zone );
	$tz_offset = $tz->getOffset(new JDate()) / 3600;
} else {
	$tz_offset = $site_zone;
}
$tz_info =  $tz_offset > 0 ? ' UTC +' . $tz_offset : ' UTC ' . $tz_offset;
if (FLEXI_J16GE) $tz_info .= ' ('.$user_zone.')';
$date_note_msg = JText::sprintf( FLEXI_J16GE ? 'FLEXI_DATES_IN_USER_TIMEZONE_NOTE' : 'FLEXI_DATES_IN_SITE_TIMEZONE_NOTE', ' ', $tz_info );
$date_zone_tip = '<span class="fc-padded-image '.$tip_class.'" data-placement="bottom" title="'.flexicontent_html::getToolTip(null, $date_note_msg, 0, 1).'">'.$hintmage.'</span>';


// COMMON repeated texts
$edit_entry = JText::_('FLEXI_EDIT_TAG', true);
$view_entry = JText::_('FLEXI_VIEW', true);
$rem_filt_txt = JText::_('FLEXI_REMOVE_FILTER');
$rem_filt_tip = ' class="'.$tip_class.' filterdel" title="'.flexicontent_html::getToolTip('FLEXI_ACTIVE_FILTER', 'FLEXI_CLICK_TO_REMOVE_THIS_FILTER', 1, 1).'" ';
?>
<script type="text/javascript">

function fetchcounter()
{
	var url = "index.php?option=com_flexicontent&amp;controller=items&amp;task=getorphans&amp;format=raw";
	var ajax = new Ajax(url, {
		method: 'get',
		update: $('count'),
		onComplete:function(v) {
			if(v==0)
				if(confirm("<?php echo JText::_( 'FLEXI_ITEMS_REFRESH_CONFIRM',true ); ?>"))
					location.href = 'index.php?option=com_flexicontent&amp;view=items';
		}
	});
	ajax.request();
}

// the function overloads joomla standard event
function submitform(pressbutton)
{
	form = document.adminForm;
	// If formvalidator activated
	if( pressbutton == 'remove' ) {
		var answer = confirm('<?php echo JText::_( 'FLEXI_ITEMS_DELETE_CONFIRM',true ); ?>')
		if (!answer){
			new Event(e).stop();
			return;
		} else {
			// Store the button task into the form
			if (pressbutton) {
				form.task.value=pressbutton;
			}

			// Execute onsubmit
			if (typeof form.onsubmit == "function") {
				form.onsubmit();
			}
			// Submit the form
			form.submit();
		}
	} else {
		// Store the button task into the form
		if (pressbutton) {
			form.task.value=pressbutton;
		}

		// Execute onsubmit
		if (typeof form.onsubmit == "function") {
			form.onsubmit();
		}
		// Submit the form
		form.submit();
	}
}

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
	delFilter('search'); delFilter('filter_itemscount');
	delFilter('filter_logged'); delFilter('filter_usergrp');
	delFilter('startdate'); delFilter('enddate');
	delFilter('filter_id');
}

window.addEvent('domready', function(){
	var startdate	= $('startdate');
	var enddate 	= $('enddate');
	if(MooTools.version>="1.2.4") {
		var sdate = startdate.value;
		var edate = enddate.value;
	}else{
		var sdate = startdate.getValue();
		var edate = enddate.getValue();
	}
	if (sdate == '') {
		startdate.setProperty('value', '<?php echo JText::_( 'FLEXI_FROM',true ); ?>');
	}
	if (edate == '') {
		enddate.setProperty('value', '<?php echo JText::_( 'FLEXI_TO',true ); ?>');
	}
	$('startdate').addEvent('focus', function() {
		if (sdate == '<?php echo JText::_( 'FLEXI_FROM',true ); ?>') {
			startdate.setProperty('value', '');
		}
	});
	$('enddate').addEvent('focus', function() {
		if (edate == '<?php echo JText::_( 'FLEXI_TO',true ); ?>') {
			enddate.setProperty('value', '');
		}
	});
	$('startdate').addEvent('blur', function() {
		if (sdate == '') {
			startdate.setProperty('value', '<?php echo JText::_( 'FLEXI_FROM',true ); ?>');
		}
	});
	$('enddate').addEvent('blur', function() {
		if (edate == '') {
			enddate.setProperty('value', '<?php echo JText::_( 'FLEXI_TO',true ); ?>');
		}
	});
});
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
			<span class="btn-wrapper input-append" style="margin:0;">
				<input type="text" name="search" id="search" placeholder="<?php echo JText::_( 'FLEXI_SEARCH' ); ?>" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="inputbox" />
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
			<?php echo $this->lists['filter_itemscount']; ?>
		</span>
		
		<span class="fc-filter nowrap_box">
			<?php echo $this->lists['filter_logged']; ?>
		</span>
		
		<span class="fc-filter nowrap_box">
			<?php echo $this->lists['filter_usergrp']; ?>
		</span>
		
		<span class="fc-filter nowrap_box">
			<span class="radio"><?php echo $this->lists['date']; ?></span>
			<?php echo $this->lists['startdate']; ?>&nbsp;&nbsp;<?php echo $this->lists['enddate']; ?>
		</span>
		
		<span class="fc-filter nowrap_box">
			<label class="label"><?php echo JText::_('FLEXI_ID'); ?></label>
			<input type="text" name="filter_id" id="filter_id" value="<?php echo $this->lists['filter_id']; ?>" class="inputbox" />
		</span>
		
		<div class="icon-arrow-up-2" title="<?php echo JText::_('FLEXI_HIDE'); ?>" style="cursor: pointer;" onclick="fc_toggle_box_via_btn('fc-filters-box', document.getElementById('fc_filters_box_btn'), 'btn-primary');"></div>
	</div>
	
	<div id="mainChooseColBox" class="fc_mini_note_box well well-small" style="display:none;"></div>
	
	<div class="fcclear"></div>
	
	<table id="adminListTableFCusers" class="adminlist fcmanlist">
	<thead>
		<tr>
			<th><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			<th><input type="checkbox" name="toggle" value="" onclick="<?php echo FLEXI_J30GE ? 'Joomla.checkAll(this);' : 'checkAll('.count( $this->rows).');'; ?>" /></th>
			<th class="hideOnDemandClass left">
				<?php echo JHTML::_('grid.sort',   'FLEXI_NAME', 'a.name', @$this->lists['order_Dir'], @$this->lists['order'] ); ?>
				<?php if ($this->search) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('search');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			<th class="hideOnDemandClass left" >
				<?php echo JHTML::_('grid.sort',   'FLEXI_ITEMS', 'itemscount', @$this->lists['order_Dir'], @$this->lists['order'] ); ?>
				<?php if ($this->filter_itemscount) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('filter_itemscount');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			<th class="hideOnDemandClass center" >
				<?php echo JHTML::_('grid.sort',   'FLEXI_USER_NAME', 'a.username', @$this->lists['order_Dir'], @$this->lists['order'] ); ?>
			</th>
			<th class="hideOnDemandClass center" nowrap="nowrap">
				<?php echo JHTML::_('grid.sort',   'FLEXI_USER_LOGIN', 'loggedin', @$this->lists['order_Dir'], @$this->lists['order'] ); ?>
				<?php if ($this->filter_logged) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('filter_logged');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			<th class="hideOnDemandClass center" nowrap="nowrap">
				<?php echo JHTML::_('grid.sort',   'FLEXI_ENABLED', 'a.block', @$this->lists['order_Dir'], @$this->lists['order'] ); ?>
			</th>
			<th class="hideOnDemandClass center">
				<?php echo FLEXI_J16GE ? JText::_( 'FLEXI_USERGROUPS' ) : JHTML::_('grid.sort',   'Group', 'groupname', @$this->lists['order_Dir'], @$this->lists['order'] ); ?>
				<?php if ($this->filter_usergrp) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('filter_usergrp');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			<th class="hideOnDemandClass left">
				<?php echo JHTML::_('grid.sort',   'FLEXI_USER_EMAIL', 'a.email', @$this->lists['order_Dir'], @$this->lists['order'] ); ?>
			</th>
			<th width="110" class="hideOnDemandClass center">
				<?php echo JHTML::_('grid.sort',   'FLEXI_REGISTRED_DATE', 'a.registerDate', @$this->lists['order_Dir'], @$this->lists['order'] ); ?>
				<?php
				if ($this->date == '1') :
					if (($this->startdate && ($this->startdate != JText::_('FLEXI_FROM'))) || ($this->enddate && ($this->startdate != JText::_('FLEXI_TO')))) :
				?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('startdate');delFilter('enddate');document.adminForm.submit();" />
				</span>
				<?php
					endif;
				endif;
				?>
			</th>
			<th width="110" class="hideOnDemandClass center">
				<?php echo JHTML::_('grid.sort',   'FLEXI_USER_LAST_VISIT', 'a.lastvisitDate', @$this->lists['order_Dir'], @$this->lists['order'] ); ?>
				<?php
				if ($this->date == '2') :
					if (($this->startdate && ($this->startdate != JText::_('FLEXI_FROM'))) || ($this->enddate && ($this->startdate != JText::_('FLEXI_TO')))) :
				?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('startdate');delFilter('enddate');document.adminForm.submit();" />
				</span>
				<?php
					endif;
				endif;
				?>
			</th>
			<th class="hideOnDemandClass center" nowrap="nowrap">
				<?php echo JHTML::_('grid.sort',   'FLEXI_ID', 'a.id', @$this->lists['order_Dir'], @$this->lists['order'] ); ?>
				<?php if ($this->filter_id) : ?>
				<span <?php echo $rem_filt_tip; ?>>
					<img src="components/com_flexicontent/assets/images/delete.png" alt="<?php echo $rem_filt_txt ?>" onclick="delFilter('filter_id');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
		</tr>
		
		</thead>
		<tfoot>
			<tr>
				<td colspan="12">
					<?php echo $pagination_footer; ?>
				</td>
			</tr>
		</tfoot>
		<tbody>
		<?php
			$k = 0;
			for ($i=0, $n=count( $this->rows ); $i < $n; $i++)
			{
				$row 	=& $this->rows[$i];
				if (!$row->id) continue;
				if (FLEXI_J16GE) {
					$row->groupname = array();
					foreach($row->usergroups as $row_ugrp_id) {
						$row->groupname[] = $this->usergroups[$row_ugrp_id]->title;
					}
					$row->groupname = implode(', ', $row->groupname);
				}

				$img_path  = '../components/com_flexicontent/assets/images/';
				$tick_img  = $img_path . 'tick.png';
				$block_img = $img_path . ($row->block ? 'publish_x.png' : 'tick.png');
				$task_block= (FLEXI_J16GE ? 'users.' : '') . ($row->block ? 'unblock' : 'block');
				$users_task = FLEXI_J16GE ? 'task=users.' : 'controller=users&amp;task=';
				$alt   = $row->block ? JText::_( 'Enabled' ) : JText::_( 'Blocked' );
				$link  = 'index.php?option=com_flexicontent&amp;controller=users&amp;view=user&amp;'.$users_task.'edit&amp;cid[]='. $row->id. '';

				if ($row->lastvisitDate == "0000-00-00 00:00:00") {
					$lvisit = JText::_( 'Never' );
				} else {
					$lvisit	= JHTML::_('date', $row->lastvisitDate, FLEXI_J16GE ? 'Y-m-d H:i:s' : '%Y-%m-%d %H:%M:%S');
				}
				$registered	= JHTML::_('date', $row->registerDate, FLEXI_J16GE ? 'Y-m-d H:i:s' : '%Y-%m-%d %H:%M:%S');

				$itemscount = '<span class="badge badge-info">'.$row->itemscount.'</span>';
				if ($row->itemscount) {
					$itemscount .= '<a onclick="delAllFilters();"  href="index.php?option=com_flexicontent&amp;view=items&amp;filter_authors='.$row->id.'">
					['.$view_entry.']
					</a>';
				}
			?>
			<tr class="<?php echo "row$k"; ?>">
				<td class="center">
					<?php echo $i+1+$this->pagination->limitstart;?>
				</td>
				<td class="center">
					<?php echo JHTML::_('grid.id', $i, $row->id ); ?>
				</td>
				<td class="col_title">
					<a href="<?php echo $link; ?>">
						<?php echo $row->name; ?></a>
				</td>
				<td align="left" class="col_itemscount">
					<?php echo $itemscount; ?>
				</td>
				<td>
					<!-- <a class="modal" rel="{handler: 'iframe', size: {x: 800, y: 500}, onClose: function() {alert('hello');} }" href="<?php echo $link; ?>"> -->
					<?php echo $row->username; ?>
					<!-- </a> -->
				</td>
				<td align="center" class="col_logged">
					<?php echo $row->loggedin ? '<img src="'.$tick_img.'" width="16" height="16" border="0" alt="" />': ''; ?>
				</td>
				<td align="center">
					<a href="javascript:void(0);" onclick="return listItemTask('cb<?php echo $i;?>','<?php echo $task_block;?>')">
						<img src="images/<?php echo $block_img;?>" width="16" height="16" border="0" alt="<?php echo $alt; ?>" /></a>
				</td>
				<td align="center" class="col_usergrp">
					<?php echo JText::_( $row->groupname ); ?>
				</td>
				<td align="left">
					<a href="mailto:<?php echo $row->email; ?>">
						<?php echo $row->email; ?></a>
				</td>
				<td align="center" nowrap="nowrap" class="col_registered">
					<?php echo $registered; ?>
				</td>
				<td align="center" nowrap="nowrap" class="col_visited">
					<?php echo $lvisit; ?>
				</td>
				<td class="left col_id">
					<?php echo $row->id; ?>
				</td>
			</tr>
			<?php
				$k = 1 - $k;
				}
			?>
		</tbody>
	</table>
	
	<sup>[1]</sup> <?php echo JText::_('FLEXI_BY_DEFAULT_ONLY_AUTHORS_WITH_ITEMS_SHOWN'); ?><br />

	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="users" />
	<input type="hidden" name="view" value="users" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<?php echo JHTML::_( 'form.token' ); ?>
	
	</div>
</form>
</div>