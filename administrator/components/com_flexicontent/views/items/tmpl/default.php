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
$limit = $this->pageNav->limit;
?>
<script language="javascript" type="text/javascript">
window.onDomReady(stateselector.init.bind(stateselector));

function dostate(state, id)
{	
	var change = new processstate();
    change.dostate( state, id );
}

function fetchcounter()
{
	var url = "index.php?option=com_flexicontent&controller=items&task=getorphans&format=raw";
	var ajax = new Ajax(url, {
		method: 'get',
		update: $('count'),
		onComplete:function(v) {
			if(v==0)
				if(confirm("<?php echo JText::_( 'FLEXI_ITEMS_REFRESH_CONFIRM' ); ?>"))
					location.href = 'index.php?option=com_flexicontent&view=items';
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
		var answer = confirm('<?php echo JText::_( 'FLEXI_ITEMS_DELETE_CONFIRM' ); ?>')
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
	var myForm = $('adminForm');
	$(name).setProperty('value', '');
}

window.addEvent('domready', function(){
	var startdate	= $('startdate');
	var enddate 	= $('enddate');
	if (startdate.getValue() == '') {
		startdate.setProperty('value', '<?php echo JText::_( 'FLEXI_FROM' ); ?>');
	}
	if (enddate.getValue() == '') {
		enddate.setProperty('value', '<?php echo JText::_( 'FLEXI_TO' ); ?>');
	}
	$('startdate').addEvent('focus', function() {
		if (startdate.getValue() == '<?php echo JText::_( 'FLEXI_FROM' ); ?>') {
			startdate.setProperty('value', '');
		}		
	});
	$('enddate').addEvent('focus', function() {
		if (enddate.getValue() == '<?php echo JText::_( 'FLEXI_TO' ); ?>') {
			enddate.setProperty('value', '');
		}		
	});
	$('startdate').addEvent('blur', function() {
		if (startdate.getValue() == '') {
			startdate.setProperty('value', '<?php echo JText::_( 'FLEXI_FROM' ); ?>');
		}		
	});
	$('enddate').addEvent('blur', function() {
		if (enddate.getValue() == '') {
			enddate.setProperty('value', '<?php echo JText::_( 'FLEXI_TO' ); ?>');
		}		
	});

/*
	$('show_filters').setStyle('display', 'none');
	$('hide_filters').addEvent('click', function() {
		$('filterline').setStyle('display', 'none');
		$('show_filters').setStyle('display', '');
		$('hide_filters').setStyle('display', 'none');
	});
	$('show_filters').addEvent('click', function() {
		$('filterline').setStyle('display', '');
		$('show_filters').setStyle('display', 'none');
		$('hide_filters').setStyle('display', '');
	});
*/
});
</script>
<?php if ($this->unassociated) : ?>
<script type="text/javascript">
window.addEvent('domready', function(){
	var count = fetchcounter();
	$('bindForm').addEvent('submit', function(e) {
		$('log-bind').setHTML('<p class="centerimg"><img src="components/com_flexicontent/assets/images/ajax-loader-orange.gif" align="center"></p>');
		e = new Event(e).stop();
		
		this.send({
			update: 	$('log-bind'),
			onComplete:	function() {
				fetchcounter();
			}
		});
	});
}); 
</script>
<?php endif; ?>
<div class="flexicontent">

<?php if ($this->unassociated) : ?>
<form action="index.php?option=com_flexicontent&controller=items&task=bindextdata&format=raw" method="post" name="bindForm" id="bindForm">
	<div class="fc-error">
	<table>
		<tr>
			<td>
			<span style="font-size:115%;">
			<?php echo JText::_( 'FLEXI_UNASSOCIATED_WARNING' ); ?>
			</span>
			</td>
			<td align="center" width="35%">
				<span style="font-size:150%;"><span id="count"></span></span>&nbsp;&nbsp;<span style="font-size:115%;"><?php echo JText::_( 'FLEXI_ITEMS_TO_BIND' ); ?></span>&nbsp;&nbsp;
				<?php echo $this->lists['extdata']; ?>
				<input id="button-bind" type="submit" class="button" value="<?php echo JText::_( 'FLEXI_BIND' ); ?>" />
				<div id="log-bind"></div>
			</td>
		</tr>
	</table>
	</div>
</form>
</div>

<?php endif; ?>

<form action="index.php" method="post" name="adminForm" id="adminForm">

	<table class="adminlist" cellspacing="1">
	<thead>
		<tr>
			<th width="5" class="center">
				<?php echo JText::_( 'FLEXI_NUM' ); ?>
			</th>
			<th width="5" class="center">
				<input type="checkbox" name="toggle" value="" onClick="checkAll(<?php echo count( $this->rows ); ?>);" />
			</th>
			<th class="left">
				<?php echo JHTML::_('grid.sort', 'FLEXI_TITLE', 'i.title', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->search) : ?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC') ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER') ?>" onclick="delFilter('search');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			<?php if (FLEXI_FISH) : ?>
			<th width="1%" nowrap="nowrap" class="center">
				<?php echo JHTML::_('grid.sort', 'FLEXI_FLAG', 'lang', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->filter_lang) : ?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC') ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER') ?>" onclick="delFilter('filter_lang');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			<?php endif; ?>
			<th width="1%" nowrap="nowrap" class="center">
				<?php echo JHTML::_('grid.sort', 'FLEXI_TYPE_NAME', 'type_name', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->filter_type) : ?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC') ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER') ?>" onclick="delFilter('filter_type');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			<th width="1%" nowrap="nowrap" class="center">
				<?php echo JText::_( 'FLEXI_STATE' ); ?>
				<?php if ($this->filter_state) : ?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC') ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER') ?>" onclick="delFilter('filter_state');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			<th width="<?php echo $this->CanOrder ? '90' : '60'; ?>" class="center">
				<?php
				if ($this->filter_cats == '' || $this->filter_cats == 0) :
					echo JHTML::_('grid.sort', 'FLEXI_REORDER', 'i.ordering', $this->lists['order_Dir'], $this->lists['order'] );
					if ($this->CanOrder) :
						echo $this->ordering ? JHTML::_('grid.order', $this->rows, 'filesave.png', 'saveorder' ) : '';
					endif;
				else :
					echo JHTML::_('grid.sort', 'FLEXI_REORDER', 'catsordering', $this->lists['order_Dir'], $this->lists['order'] );
					if ($this->CanOrder) :
						echo $this->ordering ? JHTML::_('grid.order', $this->rows, 'filesave.png', 'saveorder' ) : '';
					endif;
				endif;
				?>
			</th>
			<th width="7%" class="center">
				<?php echo JHTML::_('grid.sort', 'FLEXI_ACCESS', 'i.access', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>
			<th width="10%" class="left">
				<?php echo JText::_( 'FLEXI_CATEGORIES' ); ?>
				<?php if ($this->filter_cats) : ?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC') ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER') ?>" onclick="delFilter('filter_cats');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			<th width="7%" class="center">
				<?php echo JText::_( 'FLEXI_AUTHOR' ); ?>
				<?php if ($this->filter_authors) : ?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC') ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER') ?>" onclick="delFilter('filter_authors');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			<th align="center" width="85" class="center">
				<?php echo JHTML::_('grid.sort',   'FLEXI_CREATED', 'i.created', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php
				if ($this->date == '1') :
					if (($this->startdate && ($this->startdate != JText::_('FLEXI_FROM'))) || ($this->enddate && ($this->startdate != JText::_('FLEXI_TO')))) :
				?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC') ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER') ?>" onclick="delFilter('startdate');delFilter('enddate');document.adminForm.submit();" />
				</span>
				<?php
					endif;
				endif;
				?>
			</th>
			<th align="center" width="85" class="center">
				<?php echo JHTML::_('grid.sort',   'FLEXI_REVISED', 'i.modified', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php
				if ($this->date == '2') :
					if (($this->startdate && ($this->startdate != JText::_('FLEXI_FROM'))) || ($this->enddate && ($this->startdate != JText::_('FLEXI_TO')))) :
				?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC') ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER') ?>" onclick="delFilter('startdate');delFilter('enddate');document.adminForm.submit();" />
				</span>
				<?php
					endif;
				endif;
				?>
			</th>
			<th width="1%" nowrap="nowrap" class="center">
				<?php echo JHTML::_('grid.sort', 'FLEXI_HITS', 'i.hits', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>
			<th width="2%" nowrap="nowrap" class="center">
				<?php echo JHTML::_('grid.sort', 'FLEXI_ID', 'i.id', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->filter_id) : ?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC') ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER') ?>" onclick="delFilter('filter_id');document.adminForm.submit();" />
				</span>
				<?php endif; ?>

			</th>
		</tr>

		<tr id="filterline">
			<td class="left col_title" colspan="3">
			  	<span class="radio"><?php echo $this->lists['scope']; ?></span>
				<input type="text" name="search" id="search" value="<?php echo $this->lists['search']; ?>" class="inputbox" />
			</td>
			<?php if (FLEXI_FISH) : ?>
			<td class="left col_lang">
				<?php echo $this->lists['filter_lang']; ?>
			</td>
			<?php endif; ?>
			<td class="left col_type">
				<?php echo $this->lists['filter_type']; ?>
			</td>
			<td class="left col_state">
				<?php echo $this->lists['state']; ?>
			</td>
			<td class="left"></td>
			<td class="left"></td>
			<td class="left col_cats">
			<?php $checked = @$this->filter_subcats ? ' checked="checked"' : ''; ?>
				<span class="radio"><label for="filter_subcats"><input type="checkbox" name="filter_subcats" value="1" id="filter_subcats" class="inputbox"<?php echo $checked; ?> /><?php echo ' '.JText::_( 'FLEXI_INCLUDE_SUBS' ); ?></label></span>
				<?php echo $this->lists['filter_cats']; ?>
			</td>
			<td class="left col_authors">
				<?php echo $this->lists['filter_authors']; ?>
			</td>
			<td class="left col_created col_revised" colspan="2">
				<span class="radio"><?php echo $this->lists['date']; ?></span>
				<?php echo $this->lists['startdate']; ?>&nbsp;<?php echo $this->lists['enddate']; ?>
			</td>
			<td class="left"></td>
			<td class="left col_id">
				<input type="text" name="filter_id" id="filter_id" value="<?php echo $this->lists['filter_id']; ?>" class="inputbox" />
			</td>
		</tr>

		<tr>
			<td colspan="<?php echo FLEXI_FISH ? '14' : '13'; ?>" class="filterbuttons">
				<input type="submit" class="button submitbutton" onclick="this.form.submit();" value="<?php echo JText::_( 'FLEXI_APPLY_FILTERS' ); ?>" />
				<input type="button" class="button" onclick="delFilter('search');delFilter('filter_type');delFilter('filter_state');delFilter('filter_cats');delFilter('filter_authors');delFilter('filter_id');delFilter('startdate');delFilter('enddate');<?php echo FLEXI_FISH ? "delFilter('filter_lang');" : ""; ?>this.form.submit();" value="<?php echo JText::_( 'FLEXI_RESET_FILTERS' ); ?>" />
				<span style="float:right;">
					<input type="button" class="button" onclick="delFilter('search');delFilter('filter_type');delFilter('filter_state');delFilter('filter_cats');delFilter('filter_authors');delFilter('filter_id');delFilter('startdate');delFilter('enddate');<?php echo FLEXI_FISH ? "delFilter('filter_lang');" : ""; ?>this.form.submit();" value="<?php echo JText::_( 'FLEXI_RESET_FILTERS' ); ?>" />
					<input type="button" class="button submitbutton" onclick="this.form.submit();" value="<?php echo JText::_( 'FLEXI_APPLY_FILTERS' ); ?>" />
<!--
					<input type="button" class="button" id="hide_filters" value="<?php echo JText::_( 'FLEXI_HIDE_FILTERS' ); ?>" />
					<input type="button" class="button" id="show_filters" value="<?php echo JText::_( 'FLEXI_DISPLAY_FILTERS' ); ?>" />
-->
				</span>
			</td>
		</tr>
	</thead>

	<tfoot>
		<tr>
			<td colspan="<?php echo FLEXI_FISH ? '14' : '13'; ?>">
				<?php echo $this->pageNav->getListFooter(); ?>
			</td>
		</tr>
	</tfoot>

	<tbody>
		<?php
		$k 			= 0;
		$db 		=& JFactory::getDBO();
		$config		=& JFactory::getConfig();
		$nullDate 	= $db->getNullDate();
		$user 		=& $this->user;
		
		if (FLEXI_ACCESS) {
			$canEditAll 		= FAccess::checkAllContentAccess('com_content','edit','users',$user->gmid,'content','all');
			$canEditOwnAll		= FAccess::checkAllContentAccess('com_content','editown','users',$user->gmid,'content','all');
			$canPublishAll 		= FAccess::checkAllContentAccess('com_content','publish','users',$user->gmid,'content','all');
			$canPublishOwnAll	= FAccess::checkAllContentAccess('com_content','publishown','users',$user->gmid,'content','all');
		}
		
		for ($i=0, $n=count($this->rows); $i < $n; $i++) {
			$row = $this->rows[$i];

			if (FLEXI_ACCESS) {
				$rights 			= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $row->id, $row->catid);
				
				$canEdit 			= $canEditAll || in_array('edit', $rights) || ($user->gid > 24);
				$canEditOwn			= ((in_array('editown', $rights) || $canEditOwnAll) && ($row->created_by == $user->id)) || ($user->gid > 24);
				$canPublish 		= $canPublishAll || in_array('publish', $rights) || ($user->gid > 24);
				$canPublishOwn		= ((in_array('publishown', $rights) || $canPublishOwnAll) && ($row->created_by == $user->id)) || ($user->gid > 24);
			} else {
				$canEdit		= 1;
				$canEditOwn		= 1;
				$canPublish 	= 1;
				$canPublishOwn	= 1;
			}

			$publish_up =& JFactory::getDate($row->publish_up);
			$publish_down =& JFactory::getDate($row->publish_down);
			$publish_up->setOffset($config->getValue('config.offset'));
			$publish_down->setOffset($config->getValue('config.offset'));

			$link 		= 'index.php?option=com_flexicontent&amp;controller=items&amp;task=edit&amp;cid[]='. $row->id;

			if (FLEXI_ACCESS) {
				if ($this->CanRights) {
					$access 	= FAccess::accessswitch('item', $row, $i);
				} else {
					$access 	= FAccess::accessswitch('item', $row, $i, 'content', 1);
				}
			} else {
				$access 	= JHTML::_('grid.access', $row, $i );
			}

			$checked 	= JHTML::_('grid.checkedout', $row, $i );

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
				}

				$times = '';
				if (isset($row->publish_up)) {
					if ($row->publish_up == $nullDate) {
						$times .= JText::_( 'FLEXI_START_ALWAYS' );
					} else {
						$times .= JText::_( 'FLEXI_START' ) .": ". $publish_up->toFormat();
					}
				}
				if (isset($row->publish_down)) {
					if ($row->publish_down == $nullDate) {
						$times .= "<br />". JText::_( 'FLEXI_FINISH_NO_EXPIRY' );
					} else {
						$times .= "<br />". JText::_( 'FLEXI_FINISH' ) .": ". $publish_down->toFormat();
					}
				}
			$row->lang = @$row->lang ? $row->lang : 'en-GB';
   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td><?php echo $this->pageNav->getRowOffset( $i ); ?></td>
			<td width="7"><?php echo $checked; ?></td>
			<td align="left" class="col_title">
				<?php
				if ( ( $row->checked_out && ( $row->checked_out != $this->user->get('id') ) ) || ((!$canEdit) && (!$canEditOwn)) ) {
					echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8');
				} else {
				?>
					<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' );?>::<?php echo $row->title; ?>">
					<a href="<?php echo $link; ?>">
					<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
					</a></span>
				<?php
				}
				?>
				
			</td>
			<?php if (FLEXI_FISH) :
				if( isset($row->lang) && @$row->lang ) :
				?>
			<td align="center" class="hasTip col_lang" title="<?php echo JText::_( 'FLEXI_LANGUAGE' ).'::'.$this->langs->{$row->lang}->name; ?>">
				<?php if ($this->langs->{$row->lang}->image) : ?>
				<img src="../images/<?php echo $this->langs->{$row->lang}->image; ?>" alt="<?php echo $row->lang; ?>" />
				<?php else : ?>
				<img src="../components/com_joomfish/images/flags/<?php echo $this->langs->{$row->lang}->shortcode; ?>.gif" alt="<?php echo $row->lang; ?>" />
				<?php endif; ?>
			</td>
				<?php else : ?>
			<td align="center" class="hasTip col_lang" title="<?php echo JText::_( 'FLEXI_LANGUAGE' ).'::'.JText::_('Undefined');?>">
				&nbsp;
			</td>
				<?php endif;?>
			<?php endif; ?>
			<td align="center" class="col_type">
				<?php echo $row->type_name; ?>
			</td>
			<td align="center" class="col_state">
			<?php if (($canPublish || $canPublishOwn) && ($limit <= 30)) : ?>
			<ul class="statetoggler">
				<li class="topLevel">
					<a href="javascript:void(0);" class="opener" style="outline:none;">
					<div id="row<?php echo $row->id; ?>">
						<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_PUBLISH_INFORMATION' );?>::<?php echo $times; ?>">
							<img src="images/<?php echo $img;?>" width="16" height="16" border="0" alt="<?php echo $alt; ?>" />
						</span>
					</div>
					</a>
					<div class="options">
						<ul>
							<li>
								<div>
								<a href="javascript:void(0);" onclick="dostate('1', '<?php echo $row->id; ?>')" class="closer hasTip" title="<?php echo JText::_( 'FLEXI_ACTION' ); ?>::<?php echo JText::_( 'FLEXI_PUBLISH_THIS_ITEM' ); ?>">
									<img src="images/tick.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_PUBLISHED' ); ?>" />
								</a>
								</div>
							</li>
							<li>
								<div>
								<a href="javascript:void(0);" onclick="dostate('0', '<?php echo $row->id; ?>')" class="closer hasTip" title="<?php echo JText::_( 'FLEXI_ACTION' ); ?>::<?php echo JText::_( 'FLEXI_UNPUBLISH_THIS_ITEM' ); ?>">
									<img src="images/publish_x.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_UNPUBLISHED' ); ?>" />
								</a>	
								</div>
							</li>
							<li>
								<div>
								<a href="javascript:void(0);" onclick="dostate('-1', '<?php echo $row->id; ?>')" class="closer hasTip" title="<?php echo JText::_( 'FLEXI_ACTION' ); ?>::<?php echo JText::_( 'FLEXI_ARCHIVE_THIS_ITEM' ); ?>">
									<img src="images/disabled.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_ARCHIVED' ); ?>" />
								</a>
								</div>
							</li>
							<li>
								<div>
								<a href="javascript:void(0);" onclick="dostate('-3', '<?php echo $row->id; ?>')" class="closer hasTip" title="<?php echo JText::_( 'FLEXI_ACTION' ); ?>::<?php echo JText::_( 'FLEXI_SET_ITEM_PENDING' ); ?>">
									<img src="images/publish_r.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_PENDING' ); ?>" />
								</a>
								</div>
							</li>
							<li>
								<div>
								<a href="javascript:void(0);" onclick="dostate('-4', '<?php echo $row->id; ?>')" class="closer hasTip" title="<?php echo JText::_( 'FLEXI_ACTION' ); ?>::<?php echo JText::_( 'FLEXI_SET_ITEM_TO_WRITE' ); ?>">
									<img src="images/publish_y.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_TO_WRITE' ); ?>" />
								</a>	
								</div>
							</li>
							<li>
								<div>
								<a href="javascript:void(0);" onclick="dostate('-5', '<?php echo $row->id; ?>')" class="closer hasTip" title="<?php echo JText::_( 'FLEXI_ACTION' ); ?>::<?php echo JText::_( 'FLEXI_SET_ITEM_IN_PROGRESS' ); ?>">
									<img src="images/publish_g.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_IN_PROGRESS' ); ?>" />
								</a>	
								</div>
							</li>
						</ul>
					</div>
				</li>
			</ul>
			<?php else : ?>
			<div id="row<?php echo $row->id; ?>">
				<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_PUBLISH_INFORMATION' );?>::<?php echo $times; ?>">
					<img src="images/<?php echo $img;?>" width="16" height="16" border="0" alt="<?php echo $alt; ?>" />
				</span>
			</div>
			<?php endif ; ?>
			</td>
			<?php if ($this->CanOrder) : ?>
			<td class="order">
				<span><?php echo $this->pageNav->orderUpIcon( $i, true, 'orderup', 'Move Up', $this->ordering ); ?></span>

				<span><?php echo $this->pageNav->orderDownIcon( $i, $n, true, 'orderdown', 'Move Down', $this->ordering );?></span>

				<?php $disabled = $this->ordering ?  '' : '"disabled=disabled"'; ?>

				<?php if ($this->filter_cats == '' || $this->filter_cats == 0) : ?>
				<input type="text" name="order[]" size="5" value="<?php echo $row->ordering; ?>" <?php echo $disabled; ?> class="text_area" style="text-align: center" />
				<?php else : ?>
				<input type="text" name="order[]" size="5" value="<?php echo $row->catsordering; ?>" <?php echo $disabled; ?> class="text_area" style="text-align: center" />
				<?php endif; ?>
			</td>
			<?php else : ?>
			<td align="center">
				<?php
				if ($this->filter_cats == '' || $this->filter_cats == 0) {
					echo $row->ordering;
				} else {
					echo $row->catsordering;
				}
				?>
			</td>
			<?php endif; ?>
			<td align="center" class="col_access">
				<?php echo $access; ?>
			</td>
			<td class="col_cats">
				<?php 
				$nr = count($row->categories);
				$ix = 0;
				foreach ($row->categories as $key => $category) :
					$typeofcats = ((int)$category->id == (int)$row->maincat) ? ' maincat' : ' secondarycat';
					$catlink	= 'index.php?option=com_flexicontent&amp;controller=categories&amp;task=edit&amp;cid[]='. $category->id;
					$title = htmlspecialchars($category->title, ENT_QUOTES, 'UTF-8');
					if ($this->CanCats) :
				?>
					<span class="editlinktip hasTip<?php echo $typeofcats; ?>" title="<?php echo JText::_( 'FLEXI_EDIT_CATEGORY' );?>::<?php echo $title; ?>">
					<a href="<?php echo $catlink; ?>">
						<?php 
						if (JString::strlen($title) > 20) {
							echo JString::substr( $title , 0 , 20).'...';
						} else {
							echo $title;
						}
						?></a></span>
					<?php
					else :
						if (JString::strlen($title) > 20) {
							echo ($category->id != $row->catid) ? '' : '<strong>';
							echo JString::substr( $title , 0 , 20).'...';
							echo ($category->id != $row->catid) ? '' : '</strong>';
						} else {
							echo ($category->id != $row->catid) ? '' : '<strong>';
							echo $title;
							echo ($category->id != $row->catid) ? '' : '</strong>';
						}
					endif;
					$ix++;
					if ($ix != $nr) :
						echo ', ';
					endif;
				endforeach;
				?>
			</td>
			<td align="center" class="col_authors">
				<?php echo $row->author; ?>
			</td>
			<td nowrap="nowrap" class="col_created">
				<?php echo JHTML::_('date',  $row->created, JText::_( 'FLEXI_DATE_FORMAT_FLEXI_ITEMS' ) ); ?>
			</td>
			<td nowrap="nowrap" class="col_revised">
				<?php echo ($row->modified != $this->db->getNullDate()) ? JHTML::_('date', $row->modified, JText::_('FLEXI_DATE_FORMAT_FLEXI_ITEMS')) : JText::_('FLEXI_NEVER'); ?>
			</td>
			<td align="center">
				<?php echo $row->hits; ?>
			</td>
			<td align="center" class="col_id">
				<?php echo $row->id; ?>
			</td>
		</tr>
		<?php $k = 1 - $k; } ?>
	</tbody>

	</table>
	
	<table cellspacing="0" cellpadding="4" border="0" align="center">
		<tr>
			<td><img src="images/publish_y.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_TO_WRITE' ); ?>" /></td>
			<td><?php echo JText::_( 'FLEXI_TO_WRITE_DESC' ); ?> <u><?php echo JText::_( 'FLEXI_UNPUBLISHED_DESC' ); ?></u></td>
			<td><img src="images/tick.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_PUBLISHED' ); ?>" /></td>
			<td><?php echo JText::_( 'FLEXI_PUBLISHED_DESC' ); ?> <u><?php echo JText::_( 'FLEXI_PUBLISHED' ); ?></u></td>
			<td><img src="images/publish_x.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_UNPUBLISHED' ); ?>" /></td>
			<td><?php echo JText::_( 'FLEXI_UNPUBLISHED_DESC' ); ?></td>
		</tr>
		<tr>
			<td><img src="images/publish_r.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_PENDING' ); ?>" /></td>
			<td><?php echo JText::_( 'FLEXI_NEED_TO BE APROVED' ); ?> <u><?php echo JText::_( 'FLEXI_UNPUBLISHED_DESC' ); ?></u></td>
			<td><img src="images/publish_g.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_IN_PROGRESS' ); ?>" /></td>
			<td><?php echo JText::_( 'FLEXI_NOT_FINISHED_YET' ); ?> <u><?php echo JText::_( 'FLEXI_PUBLISHED' ); ?></u></td>
			<td><img src="images/disabled.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_ARCHIVED' ); ?>" /></td>
			<td><?php echo JText::_( 'FLEXI_ARCHIVED_STATE' ); ?> <u><?php echo JText::_( 'FLEXI_UNPUBLISHED_DESC' ); ?></u></td>
		</tr>
	</table>

	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="items" />
	<input type="hidden" name="view" value="items" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<?php echo JHTML::_( 'form.token' ); ?>
</form>
</div>
