<?php
/**
 * @version 1.5 stable $Id: default.php 1401 2012-07-28 01:33:38Z ggppdk $
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

global $globalcats;
$cparams = & JComponentHelper::getParams( 'com_flexicontent' );
$limit = $this->pageNav->limit;
$ctrl = FLEXI_J16GE ? 'items.' : '';
$items_task = FLEXI_J16GE ? 'task=items.' : 'controller=items&task=';
$cats_task = FLEXI_J16GE ? 'task=category.' : 'controller=categories&task=';

$db 			= & JFactory::getDBO();
$config		= & JFactory::getConfig();
$nullDate	= $db->getNullDate();
$user 		= & $this->user;

$enable_translation_groups = $cparams->get("enable_translation_groups") && ( FLEXI_J16GE || FLEXI_FISH ) ;
$autologin = $cparams->get('autoflogin', 1) ? '&fcu='.$user->username . '&fcp='.$user->password : '';

$items_list_cols = 15;
if ( FLEXI_J16GE || FLEXI_FISH ) {
	$items_list_cols++;
	if ( $enable_translation_groups ) $items_list_cols++;
}

$items_list_cols += count($this->extra_fields);

$image_flag_path = !FLEXI_J16GE ? "../components/com_joomfish/images/flags/" : "../media/mod_languages/images/";
$image_zoom = '<img style="float:right;" src="components/com_flexicontent/assets/images/monitor_go.png" width="16" height="16" border="0" class="hasTip" alt="'.JText::_('FLEXI_PREVIEW').'" title="'.JText::_('FLEXI_PREVIEW').':: Click to display the frontend view of this item in a new browser window" />';

$tz_string = JFactory::getApplication()->getCfg('offset');
if (FLEXI_J16GE) {
	$tz = new DateTimeZone( $tz_string );
	$tz_offset = $tz->getOffset(new JDate()) / 3600;
} else {
	$tz_offset = $tz_string;
}
?>
<script language="javascript" type="text/javascript">

function fetchcounter()
{
	var url = "index.php?option=com_flexicontent&<?php echo $items_task; ?>getorphans&tmpl=component&format=raw";
	if(MooTools.version>="1.2.4") {
		new Request.HTML({
			url: url,
			method: 'get',
			update: $('count'),
			onSuccess:function(responseTree, responseElements, responseHTML, responseJavaScript) {
				if(responseHTML==0)
					if(confirm("<?php echo JText::_( 'FLEXI_ITEMS_REFRESH_CONFIRM' ); ?>"))
						location.href = 'index.php?option=com_flexicontent&view=items';
			}
		}).send();
	}else{
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
}

// the function overloads joomla standard event
function submitform(pressbutton)
{
	form = document.adminForm;
	// If formvalidator activated
	/*if( pressbutton == 'remove' ) {
		var answer = confirm('<?php echo addslashes(JText::_( 'FLEXI_ITEMS_DELETE_CONFIRM__' )); ?>')
		if (!answer){
			new Event(e).stop();
			return;
		}
	}*/
	
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

// delete active filter
function delFilter(name)
{
	var myForm = $('adminForm');
	if ($(name).type=='checkbox')
		$(name).checked = '';
	else
		$(name).setProperty('value', '');
}

function delAllFilters() {
	delFilter('search'); delFilter('filter_type'); delFilter('filter_state');
	delFilter('filter_cats'); delFilter('filter_authors'); delFilter('filter_id');
	delFilter('startdate'); delFilter('enddate');
	<?php echo (FLEXI_FISH || FLEXI_J16GE) ? "delFilter('filter_lang');" : ""; ?>
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
		startdate.setProperty('value', '<?php echo JText::_( 'FLEXI_FROM' ); ?>');
	}
	if (edate == '') {
		enddate.setProperty('value', '<?php echo JText::_( 'FLEXI_TO' ); ?>');
	}
	$('startdate').addEvent('focus', function() {
		if (sdate == '<?php echo JText::_( 'FLEXI_FROM' ); ?>') {
			startdate.setProperty('value', '');
		}		
	});
	$('enddate').addEvent('focus', function() {
		if (edate == '<?php echo JText::_( 'FLEXI_TO' ); ?>') {
			enddate.setProperty('value', '');
		}
	});
	$('startdate').addEvent('blur', function() {
		if (sdate == '') {
			startdate.setProperty('value', '<?php echo JText::_( 'FLEXI_FROM' ); ?>');
		}		
	});
	$('enddate').addEvent('blur', function() {
		if (edate == '') {
			enddate.setProperty('value', '<?php echo JText::_( 'FLEXI_TO' ); ?>');
		}		
	});

<?php /*
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
*/ ?>
});
</script>
<?php if ($this->unassociated) : ?>
<script type="text/javascript">
window.addEvent('domready', function() {
	$('bindForm').addEvent('submit', function(e) {
		if(MooTools.version>="1.2.4") {
			$('log-bind').set('html', '<p class="centerimg"><img src="components/com_flexicontent/assets/images/ajax-loader-orange.gif" align="center"></p>');
			e = e.stop();
		}else{
			$('log-bind').setHTML('<p class="centerimg"><img src="components/com_flexicontent/assets/images/ajax-loader-orange.gif" align="center"></p>');
			e = new Event(e).stop();
		}
		if(MooTools.version>="1.2.4") {
			new Request.HTML({
				url: this.action,
				method: 'post',
				update: $('log-bind'),
				onComplete: function() {
					fetchcounter();
				}
			}).send();
		}else{
			this.send({
				update: $('log-bind'),
				onComplete: function() {
					fetchcounter();
				}
			});
		}
	});
}); 
</script>
<?php endif; ?>
<div class="flexicontent">

<?php if ($this->unassociated) : ?>
<form action="index.php?option=com_flexicontent&<?php echo $items_task; ?>bindextdata&tmpl=component" method="post" name="bindForm" id="bindForm">
	<div class="fc-error">
	<table>
		<tr>
			<td>
			<span style="font-size:115%;">
			<?php echo JText::_( 'FLEXI_UNASSOCIATED_WARNING' ); ?>
			</span>
			</td>
			<td align="center" width="35%">
				<span style="font-size:150%;"><span id="count"></span></span>&nbsp;<?php echo count($this->unassociated); ?>&nbsp;<span style="font-size:115%;"><?php echo JText::_( 'FLEXI_ITEMS_TO_BIND' ); ?></span>&nbsp;&nbsp;
				<?php echo $this->lists['extdata']; ?>
				<?php
					$types = & $this->get( 'Typeslist' );
					echo JText::_( 'Bind to' ). flexicontent_html::buildtypesselect($types, 'typeid', $typesselected='', false, 'size="1"');
				?>
				<br/>
				<?php echo JText::_( 'FLEXI_DEFAULT_CAT_FOR_NO_CAT_ITEMS' ).': '.$this->lists['default_cat']; ?>
				<input id="button-bind" type="submit" class="fc_select_button" style='float:none; display:inline-block;' value="<?php echo JText::_( 'FLEXI_BIND' ); ?>"
				onclick="	this.form.action += '&typeid='+this.form.elements['typeid'].options[this.form.elements['typeid'].selectedIndex].value;
									this.form.action += '&default_cat='+this.form.elements['default_cat'].options[this.form.elements['default_cat'].selectedIndex].value;
									this.form.action += '&extdata='+this.form.elements['extdata'].options[this.form.elements['extdata'].selectedIndex].value;" />
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
			<th width="1%" class="center">
				<?php echo JText::_( 'FLEXI_NUM' ); ?>
			</th>
			<th width="1%" class="center">
				<input type="checkbox" name="toggle" value="" onClick="checkAll(<?php echo count( $this->rows ); ?>);" />
			</th>
			<th width="1%" class="center">&nbsp;</th>
			<th class="left">
				<?php echo JHTML::_('grid.sort', 'FLEXI_TITLE', 'i.title', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->search) : ?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC') ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER') ?>" onclick="delFilter('search');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			
    <?php foreach($this->extra_fields as $field) :?>
			<th class="center"><?php echo $field->label; ?></td>
		<?php endforeach; ?>

			<?php if (FLEXI_FISH || FLEXI_J16GE) : ?>
			<th width="" nowrap="nowrap" class="center">
				<?php echo JHTML::_('grid.sort', 'FLEXI_FLAG', 'lang', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->filter_lang) : ?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC') ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER') ?>" onclick="delFilter('filter_lang');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			<?php endif; ?>
			<th width="" nowrap="nowrap" class="center">
				<?php echo JHTML::_('grid.sort', 'FLEXI_TYPE_NAME', 'type_name', $this->lists['order_Dir'], $this->lists['order'] ); ?>
				<?php if ($this->filter_type) : ?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC') ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER') ?>" onclick="delFilter('filter_type');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			<th width="" nowrap="nowrap" class="center">
				<?php echo JText::_( 'FLEXI_STATE' ); ?>
				<?php if ($this->filter_state) : ?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC') ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER') ?>" onclick="delFilter('filter_state');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			<th width="" class="center">
				<?php echo JText::_( 'FLEXI_TEMPLATE' ); ?>
			</th>
			<?php if ( $enable_translation_groups ) : ?>
			<th width="" class="center">
				<?php echo JHTML::_('grid.sort', 'Translation Group', 'ie.lang_parent_id', $this->lists['order_Dir'], $this->lists['order'] ); ?>
			</th>
			<?php endif; ?>
			<th width="<?php echo $this->CanOrder ? '' : ''; ?>" class="center">
				<?php
				if ($this->filter_cats == '' || $this->filter_cats == 0) :
					echo JHTML::_('grid.sort', 'FLEXI_REORDER', 'i.ordering', $this->lists['order_Dir'], $this->lists['order'] );
					if ($this->CanOrder) :
						echo $this->ordering ? JHTML::_('grid.order', $this->rows, 'filesave.png', $ctrl.'saveorder' ) : '';
					endif;
				else :
					echo JHTML::_('grid.sort', 'FLEXI_REORDER', 'catsordering', $this->lists['order_Dir'], $this->lists['order'] );
					if ($this->CanOrder) :
						echo $this->ordering ? JHTML::_('grid.order', $this->rows, 'filesave.png', $ctrl.'saveorder' ) : '';
					endif;
				endif;
				?>
			</th>
			<th width="" class="center">
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
			<th width="" class="center">
				<?php echo JText::_( 'FLEXI_AUTHOR' ); ?>
				<?php if ($this->filter_authors) : ?>
				<span class="hasTip filterdel" title="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER_DESC') ?>">
					<img src="components/com_flexicontent/assets/images/bullet_delete.png" alt="<?php echo JText::_('FLEXI_REMOVE_THIS_FILTER') ?>" onclick="delFilter('filter_authors');document.adminForm.submit();" />
				</span>
				<?php endif; ?>
			</th>
			<th width="" class="center">
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
			<th width="" class="center">
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
			<td class="left col_title" colspan="4">
			  	<span class="radio"><?php echo $this->lists['scope']; ?></span>
				<input type="text" name="search" id="search" value="<?php echo $this->lists['search']; ?>" class="inputbox" />
			</td>

    <?php foreach($this->extra_fields as $field) :?>
			<td class="left"></td>
		<?php endforeach; ?>

			<?php if (FLEXI_FISH || FLEXI_J16GE) : ?>
			<td class="left col_lang">
				<?php echo $this->lists['filter_lang']; ?>
			</td>
			<?php endif; ?>
			<td class="left col_type">
				<?php echo $this->lists['filter_type']; ?>
			</td>
			<td class="left col_state">
				<?php echo $this->lists['filter_state']; ?>
			</td>
			<td class="left"></td>
		<?php if ( $enable_translation_groups ) : ?>
			<td class="left"></td>
		<?php endif; ?>
			<td class="left"></td>
			<td class="left"></td>
			<td class="left col_cats">
				<label for="filter_subcats"><?php echo '&nbsp;'.JText::_( 'FLEXI_INCLUDE_SUBS' ); ?></label>
				<span class="radio"><?php echo $this->lists['filter_subcats']; ?></span>
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
			<td colspan="<?php echo $items_list_cols; ?>" class="filterbuttons">
				<input type="submit" class="button submitbutton" onclick="this.form.submit();" value="<?php echo JText::_( 'FLEXI_APPLY_FILTERS' ); ?>" />
				<input type="button" class="button" onclick="delAllFilters();this.form.submit();" value="<?php echo JText::_( 'FLEXI_RESET_FILTERS' ); ?>" />
				<?php if (isset($this->lists['filter_stategrp'])) : ?>
					<span class="radio flexi_tabbox" style="margin-left:60px;"><?php echo '<span class="flexi_tabbox_label">'.JText::_('FLEXI_LISTING_RECORDS').': </span>'.$this->lists['filter_stategrp']; ?></span>
				<?php endif; ?>

				<div class='fc_mini_note_box' style='float:right; clear:both!important;'>
				<?php
				if (FLEXI_J16GE) {
					$tz_info =  $tz_offset > 0 ? ' UTC +'.$tz_offset : ' UTC '.$tz_offset;
					$tz_info .= ' ('.$tz_string.')';
					echo JText::sprintf( 'FLEXI_DATES_IN_USER_TIMEZONE_NOTE', '', $tz_info);
				} else {
					$tz_info =  ($tz_offset > 0) ? ' UTC +'. $tz_offset : ' UTC '. $tz_offset;
					echo JText::sprintf( 'FLEXI_DATES_IN_SITE_TIMEZONE_NOTE', '', $tz_info );
				}
				?>
				</div>

<!--
				<span style="float:right;">
					<input type="button" class="button" onclick="delAllFilters();this.form.submit();" value="<?php echo JText::_( 'FLEXI_RESET_FILTERS' ); ?>" />
					<input type="button" class="button submitbutton" onclick="this.form.submit();" value="<?php echo JText::_( 'FLEXI_APPLY_FILTERS' ); ?>" />
					
					<input type="button" class="button" id="hide_filters" value="<?php echo JText::_( 'FLEXI_HIDE_FILTERS' ); ?>" />
					<input type="button" class="button" id="show_filters" value="<?php echo JText::_( 'FLEXI_DISPLAY_FILTERS' ); ?>" />
				</span>
-->
			</td>
		</tr>
	</thead>

	<tfoot>
		<tr>
			<td colspan="<?php echo $items_list_cols; ?>">
				<?php echo $this->pageNav->getListFooter(); ?>
			</td>
		</tr>
	</tfoot>

	<tbody>
		<?php
		$k 			= 0;
		if (FLEXI_J16GE)
			$date_format = (($date_format = JText::_( 'FLEXI_DATE_FORMAT_FLEXI_ITEMS_J16GE' )) == 'FLEXI_DATE_FORMAT_FLEXI_ITEMS_J16GE') ? "d/m/y H:i" : $date_format;
		else
			$date_format = (($date_format = JText::_( 'FLEXI_DATE_FORMAT_FLEXI_ITEMS' )) == 'FLEXI_DATE_FORMAT_FLEXI_ITEMS') ? "%d/%m/%y %H:%M" : $date_format;
		
		$unpublishableFound = false;
		for ($i=0, $n=count($this->rows); $i < $n; $i++)
		{
			$row = $this->rows[$i];

			if (FLEXI_J16GE) {
				$rights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $row->id);
				
				$canEdit 			 = in_array('edit', $rights);
				$canEditOwn		 = in_array('edit.own', $rights) && $row->created_by == $user->id;
				$canPublish 	 = in_array('edit.state', $rights);
				$canPublishOwn = in_array('edit.state.own', $rights) && $row->created_by == $user->id;
			} else if ($user->gid > 24) {
				$canEdit = $canEditOwn = $canPublish = $canPublishOwn = 1;
			} else if (FLEXI_ACCESS) {
				$rights 			= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $row->id, $row->catid);
				
				$canEdit 			= /*$canEditAll ||*/ in_array('edit', $rights);
				$canEditOwn			= (in_array('editown', $rights) /*|| $canEditOwnAll)*/) && $row->created_by == $user->id;
				$canPublish 		= /*$canPublishAll ||*/ in_array('publish', $rights);
				$canPublishOwn		= (in_array('publishown', $rights) /*|| $canPublishOwnAll*/) && $row->created_by == $user->id;
			} else {
				// J1.5 with no FLEXIaccess, since backend users are at least 'manager', these should be true anyway
				$canEdit		= $user->authorize('com_content', 'edit', 'content', 'all');
				$canEditOwn	= $user->authorize('com_content', 'edit', 'content', 'own') && $row->created_by == $user->id;
				$canPublish	=	$user->authorize('com_content', 'publish', 'content', 'all');
				$canPublishOwn= 1; // due to being backend user
			}
			$canPublishCurrent = $canPublish || $canPublishOwn;
			$unpublishableFound = $unpublishableFound || !$canPublishCurrent;
			

			$publish_up =& JFactory::getDate($row->publish_up);
			$publish_down =& JFactory::getDate($row->publish_down);
			if (FLEXI_J16GE) {
				$publish_up->setTimezone($tz);
				$publish_down->setTimezone($tz);
			} else {
				$publish_up->setOffset($tz_offset);
				$publish_down->setOffset($tz_offset);
			}

			$link = 'index.php?option=com_flexicontent&'.$items_task.'edit&cid[]='. $row->id;

			if (FLEXI_J16GE) {
				if ($canPublish || $canPublishOwn) {
					$access = flexicontent_html::userlevel('access['.$row->id.']', $row->access, 'onchange="return listItemTask(\'cb'.$i.'\',\'items.access\')"');
				} else {
					$access = $this->escape($row->access_level);
				}
			} else if (FLEXI_ACCESS) {
				if ($this->CanRights) {
					$access 	= FAccess::accessswitch('item', $row, $i);
				} else {
					$access 	= FAccess::accessswitch('item', $row, $i, 'content', 1);
				}
			} else {
				$access 	= JHTML::_('grid.access', $row, $i );
			}

			$cid_checkbox = JHTML::_('grid.checkedout', $row, $i );
			
			// Check publication START/FINISH dates (publication Scheduled / Expired)
			$is_published = in_array( $row->state, array(1, -5, (FLEXI_J16GE ? 2:-1) ) );
			$extra_img = $extra_alt = '';
			
			if ( $row->publication_scheduled && $is_published ) {
				$extra_img = 'pushished_scheduled.png';
				$extra_alt = JText::_( 'FLEXI_SCHEDULED_FOR_PUBLICATION' );
			}
			if ( $row->publication_expired && $is_published ) {
				$extra_img = 'pushished_expired.png';
				$extra_alt = JText::_( 'FLEXI_PUBLICATION_EXPIRED' );
			}
			
			// Set a row language, even if empty to avoid errors
			$lang_default = !FLEXI_J16GE ? '' : '*';
			$row->lang = @$row->lang ? $row->lang : $lang_default;
   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td><?php echo $this->pageNav->getRowOffset( $i ); ?></td>
			<td width="7"><?php echo $cid_checkbox; ?></td>
			<td width="1%">
				<?php
				$previewlink = JRoute::_(JURI::root() . FlexicontentHelperRoute::getItemRoute($row->id.':'.$row->alias, $globalcats[$row->catid]->slug)) .'&preview=1' .$autologin;
				echo '<a class="preview" href="'.$previewlink.'" target="_blank">'.$image_zoom.'</a>';
				?>
			</td>
			<td align="left" class="col_title">
				<?php
				
				// Display an icon with checkin link, if current user has checked out current item
				if ($row->checked_out) {
					if (FLEXI_J16GE) {
						$canCheckin = $user->authorise('core.admin', 'checkin');
					} else if (FLEXI_ACCESS) {
						$canCheckin = ($user->gid < 25) ? FAccess::checkComponentAccess('com_checkin', 'manage', 'users', $user->gmid) : 1;
					} else {
						$canCheckin = $user->gid >= 24;
					}
					if ($canCheckin && $row->checked_out == $user->id) {
						//echo if (FLEXI_J16GE) JHtml::_('jgrid.checkedout', $i, $row->editor, $row->checked_out_time, 'items.', $canCheckin);
						$task_str = FLEXI_J16GE ? 'items.checkin' : 'checkin';
						echo JText::sprintf('FLEXI_CLICK_TO_RELEASE_YOUR_LOCK', $row->editor, $row->checked_out_time, '"cb'.$i.'"', '"'.$task_str.'"');
					}
				}
				
				// Display title with no edit link ... if row checked out by different user -OR- is uneditable
				if ( ( $row->checked_out && $row->checked_out != $user->id ) || ( !$canEdit && !$canEditOwn ) ) {
					echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8');
				
				// Display title with edit link ... (item editable and not checked out)
				} else {
				?>
					<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' );?>::<?php echo $row->title; ?>">
					<?php
					if ( $enable_translation_groups ) :
						if ($this->lists['order']=='ie.lang_parent_id'&& $row->id!=$row->lang_parent_id) echo "<sup>|</sup>--";
					endif;
					?>
					<a href="<?php echo $link; ?>">
					<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
					</a></span>
				<?php
				}
				?>
				
			</td>

    <?php foreach($this->extra_fields as $field) :?>
    
			<td align="center">
		    <?php
		    // Clear display HTML just in case
		    if (isset($field->{$field->methodname}))
		    	unset( $field->{$field->methodname} );
		    
		    // Field value for current item
		    $field_value = & $row->extra_field_value[$field->name];
		    
		    if ( !empty($field_value) )
		    {
					// Create field's display HTML, via calling FlexicontentFields::renderField() for the given method name
					FlexicontentFields::renderField($row, $field, $field_value, $method=$field->methodname);
					
					// Output the field's display HTML
					echo @$field->{$field->methodname};
				}
		    ?>
			</td>
		<?php endforeach; ?>
			
		<?php if ( (FLEXI_FISH || FLEXI_J16GE) ): ?>
			<td align="center" class="hasTip col_lang" title="<?php echo JText::_( 'FLEXI_LANGUAGE' ).'::'.($row->lang=='*' ? JText::_("All") : $this->langs->{$row->lang}->name); ?>">
				
				<?php if ( !empty($row->lang) && !empty($this->langs->{$row->lang}->imgsrc) ) : ?>
					<img src="<?php echo $this->langs->{$row->lang}->imgsrc; ?>" alt="<?php echo $row->lang; ?>" />
				<?php elseif( !empty($row->lang) ) : ?>
					<?php echo $row->lang=='*' ? JText::_("All") : $row->lang;?>
				<?php endif; ?>
				
			</td>
		<?php endif; ?>
		
			<td align="center" class="col_type">
				<?php echo $row->type_name; ?>
			</td>
			<td align="center" class="col_state">
			<?php echo flexicontent_html::statebutton( $row, $row->params, $addToggler = ($limit <= $this->inline_ss_max) ); ?>
			<?php if ($extra_img) : ?><img style='float:right;' src="components/com_flexicontent/assets/images/<?php echo $extra_img;?>" width="16" height="16" border="0" class="hasTip" alt="<?php echo $extra_alt; ?>" title="<?php echo $extra_alt; ?>" /><?php endif; ?>
			</td>
			
			<td align="center">
				<?php echo ($row->config->get("ilayout","") ? $row->config->get("ilayout") : $row->tconfig->get("ilayout")."<sup>[1]</sup>") ?>
			</td>
			<?php if ( $enable_translation_groups ) : ?>
			<td align="center">
				<?php
					if ($this->lists['order']=='ie.lang_parent_id') {
						if ($row->id==$row->lang_parent_id) echo "Main";
						else echo "+";
					} else echo "unsorted<sup>[3]</sup>";
				?>
			</td>
			<?php endif ; ?>
			
			<?php if ($this->CanOrder) : ?>
			<td class="order">
				<span><?php echo $this->pageNav->orderUpIcon( $i, true, $ctrl.'orderup', 'Move Up', $this->ordering ); ?></span>

				<span><?php echo $this->pageNav->orderDownIcon( $i, $n, true, $ctrl.'orderdown', 'Move Down', $this->ordering );?></span>

				<?php $disabled = $this->ordering ?  '' : '"disabled=disabled"'; ?>

				<?php if ($this->filter_cats == '' || $this->filter_cats == 0) : ?>
				<input type="text" name="order[]" size="5" value="<?php echo $row->ordering; ?>" <?php echo $disabled; ?> class="text_area" style="text-align:center;" />
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
					$typeofcats = ((int)$category->id == (int)$row->catid) ? ' maincat' : ' secondarycat';
					$catlink	= 'index.php?option=com_flexicontent&'.$cats_task.'edit&cid[]='. $category->id;
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
				<?php echo JHTML::_('date',  $row->created, $date_format ); ?>
			</td>
			<td nowrap="nowrap" class="col_revised">
				<?php echo ($row->modified != $this->db->getNullDate()) ? JHTML::_('date', $row->modified, $date_format) : JText::_('FLEXI_NEVER'); ?>
			</td>
			<td align="center">
				<?php echo $row->hits; ?>
			</td>
			<td align="center" class="col_id">
				<?php echo $row->id; ?>
			</td>
		</tr>
		<?php
			$k = 1 - $k;
		}
		if ( (FLEXI_ACCESS || FLEXI_J16GE) && $unpublishableFound) {
			$ctrl_task = FLEXI_J16GE ? 'items.approval' : 'approval';
			JToolBarHelper::spacer();
			JToolBarHelper::divider();
			JToolBarHelper::spacer();
			JToolBarHelper::custom( $ctrl_task, 'person2.png', 'person2_f2.png', 'FLEXI_APPROVAL_REQUEST' );
		}
		JToolBarHelper::spacer();
		JToolBarHelper::spacer();
		?>
	</tbody>

	</table>
	
	<table cellspacing="0" cellpadding="4" border="0" align="center">
		<tr>
			<td><img src="../components/com_flexicontent/assets/images/tick.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_PUBLISHED' ); ?>" /></td>
			<td><?php echo JText::_( 'FLEXI_PUBLISHED_DESC' ); ?> <u><?php echo JText::_( 'FLEXI_PUBLISHED' ); ?></u></td>
			<td><img src="../components/com_flexicontent/assets/images/publish_g.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_IN_PROGRESS' ); ?>" /></td>
			<td><?php echo JText::_( 'FLEXI_NOT_FINISHED_YET' ); ?> <u><?php echo JText::_( 'FLEXI_PUBLISHED' ); ?></u></td>
		</tr><tr>
			<td><img src="../components/com_flexicontent/assets/images/publish_x.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_UNPUBLISHED' ); ?>" /></td>
			<td><?php echo JText::_( 'FLEXI_UNPUBLISHED_DESC' ); ?></td>
			<td><img src="../components/com_flexicontent/assets/images/publish_r.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_PENDING' ); ?>" /></td>
			<td><?php echo JText::_( 'FLEXI_NEED_TO_BE_APPROVED' ); ?> <u><?php echo JText::_( 'FLEXI_UNPUBLISHED_DESC' ); ?></u></td>
			<td><img src="../components/com_flexicontent/assets/images/publish_y.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_TO_WRITE' ); ?>" /></td>
			<td><?php echo JText::_( 'FLEXI_TO_WRITE_DESC' ); ?> <u><?php echo JText::_( 'FLEXI_UNPUBLISHED_DESC' ); ?></u></td>
		</tr><tr>
			<td><img src="../components/com_flexicontent/assets/images/archive.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_ARCHIVED' ); ?>" /></td>
			<td><?php echo JText::_( 'FLEXI_ARCHIVED_STATE' ); ?> <u><?php echo JText::_( 'FLEXI_UNPUBLISHED_DESC' ); ?></u></td>
			<td><img src="../components/com_flexicontent/assets/images/trash.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'FLEXI_TRASHED' ); ?>" /></td>
			<td><?php echo JText::_( 'FLEXI_TRASHED_STATE' ); ?> <u><?php echo JText::_( 'FLEXI_UNPUBLISHED_DESC' ); ?></u></td>
		</tr>
	</table>
	
	<sup>[1]</sup> <?php echo JText::_('FLEXI_TMPL_NOT_SET_USING_TYPE_DEFAULT'); ?><br />
	<sup>[2]</sup> <?php echo JText::sprintf('FLEXI_INLINE_ITEM_STATE_SELECTOR_DISABLED', $this->inline_ss_max); ?><br />
	<?php if ( $enable_translation_groups )	: ?>
		<sup>[3]</sup> <?php echo JText::_('FLEXI_SORT_TO_GROUP_TRANSLATION'); ?><br />
	<?php endif; ?>
	<sup>[4]</sup> <?php echo JText::_('FLEXI_DEFINE_ITEM_ORDER_FILTER_BY_CAT'); ?></><br />
		
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="items" />
	<input type="hidden" name="view" value="items" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="newstate" id="newstate" value="" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<?php echo JHTML::_( 'form.token' ); ?>
</form>
</div>
